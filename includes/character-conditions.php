<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_condition_error( $message = null ) {
	return new WP_Error( 'character_condition_conflict', $message ?? __( 'Die Charakterzustände oder ihre Berechtigungen wurden geändert oder sind ungültig. Bitte prüfe die Vorgaben der Universumsleitung und lade neu. Es wurde nichts übernommen.', 'ps-pen-paper' ) );
}

function ps_pen_paper_condition_policy_default() {
	return array( 'revision' => 0, 'campaign_local' => true, 'campaign_universe' => false, 'automatic' => false, 'definitions' => array() );
}

function ps_pen_paper_valid_condition_definition( $value ) {
	if ( ! is_array( $value ) || array( 'id', 'label', 'description', 'block_actions', 'block_join', 'modifiers', 'threshold' ) !== array_keys( $value ) ||
		! is_string( $value['id'] ) || ! wp_is_uuid( $value['id'] ) || ! is_string( $value['label'] ) || '' === trim( $value['label'] ) || strlen( $value['label'] ) > 480 ||
		! is_string( $value['description'] ) || strlen( $value['description'] ) > 8000 || ! is_bool( $value['block_actions'] ) || ! is_bool( $value['block_join'] ) ||
		! is_array( $value['modifiers'] ) || count( $value['modifiers'] ) > 100 ) { return false; }
	foreach ( $value['modifiers'] as $id => $amount ) {
		if ( ! is_string( $id ) || ! wp_is_uuid( $id ) || ! is_int( $amount ) || abs( $amount ) > 1000000 ) { return false; }
	}
	$threshold = $value['threshold'];
	return null === $threshold || ( is_array( $threshold ) && array( 'resource', 'maximum' ) === array_keys( $threshold ) &&
		is_string( $threshold['resource'] ) && wp_is_uuid( $threshold['resource'] ) && is_int( $threshold['maximum'] ) && abs( $threshold['maximum'] ) <= 1000000 );
}

function ps_pen_paper_valid_character_conditions( $values ) {
	if ( ! is_array( $values ) || count( $values ) > 30 ) { return false; }
	foreach ( $values as $id => $definition ) {
		if ( ! ps_pen_paper_valid_condition_definition( $definition ) || $id !== $definition['id'] ) { return false; }
	}
	return true;
}

function ps_pen_paper_valid_condition_policy( $policy ) {
	return is_array( $policy ) && array_keys( ps_pen_paper_condition_policy_default() ) === array_keys( $policy ) &&
		is_int( $policy['revision'] ) && $policy['revision'] >= 0 && is_bool( $policy['campaign_local'] ) &&
		is_bool( $policy['campaign_universe'] ) && is_bool( $policy['automatic'] ) && ps_pen_paper_valid_character_conditions( $policy['definitions'] ) &&
		( $policy['revision'] > 0 || $policy === ps_pen_paper_condition_policy_default() );
}

function ps_pen_paper_condition_scope( $scope ) {
	if ( ! $scope || ! in_array( $scope->post_type, array( 'pspp_universe', 'pspp_campaign' ), true ) ) { return ps_pen_paper_condition_error(); }
	if ( 'pspp_universe' === $scope->post_type ) { return $scope; }
	$link = ps_pen_paper_get_universe_link( $scope->ID );
	if ( is_wp_error( $link ) ) { return $link; }
	if ( ! $link['universe_id'] ) { return $scope; }
	$universe = ps_pen_paper_get_universe( $link['universe_id'] );
	return $universe ?: ps_pen_paper_condition_error();
}

function ps_pen_paper_condition_policy( $scope ) {
	$owner = ps_pen_paper_condition_scope( $scope );
	if ( is_wp_error( $owner ) ) { return $owner; }
	$policy = get_post_meta( $owner->ID, '_pspp_condition_policy', true );
	if ( '' === $policy ) { return ps_pen_paper_condition_policy_default(); }
	return ps_pen_paper_valid_condition_policy( $policy ) ? $policy : ps_pen_paper_condition_error();
}

function ps_pen_paper_condition_context( $scope, $character ) {
	if ( ! $character || 'pspp_character' !== $character->post_type || ! in_array( $character->post_status, array( 'private', 'draft' ), true ) ||
		(int) $character->post_parent !== (int) $scope->ID ) { return ps_pen_paper_condition_error(); }
	$owner = ps_pen_paper_condition_scope( $scope );
	$raw = ps_pen_paper_raw_character_state( $character->ID );
	if ( is_wp_error( $owner ) || is_wp_error( $raw ) ) { return is_wp_error( $owner ) ? $owner : $raw; }
	$base = 'pspp_universe' === $scope->post_type ? $character : null;
	if ( isset( $raw['context'] ) ) {
		$context = $raw['context'];
		$valid = ps_pen_paper_validate_context( $context, ps_pen_paper_get_rules( $scope->ID ) );
		if ( is_wp_error( $valid ) ) { return $valid; }
		if ( 'pspp_universe' === $scope->post_type ) { return ps_pen_paper_condition_error(); }
		if ( 'copy' === $context['mode'] ) { return array( 'scope' => $owner, 'character' => $character, 'base' => null ); }
		$base = ps_pen_paper_record_post( $context['source_id'] );
		if ( (int) $owner->ID !== $context['universe_id'] || ! $base || 'pspp_character' !== $base->post_type ||
			'private' !== $base->post_status || (int) $base->post_parent !== (int) $owner->ID ||
			(int) $base->post_author !== (int) $character->post_author ) { return ps_pen_paper_condition_error(); }
		$base_state = ps_pen_paper_raw_character_state( $base->ID );
		if ( is_wp_error( $base_state ) || ! $base_state['active'] || isset( $base_state['context'] ) ) { return ps_pen_paper_condition_error(); }
	}
	return array( 'scope' => $owner, 'character' => $character, 'base' => $base );
}

function ps_pen_paper_condition_lock_ids( $scope, $character ) {
	$context = ps_pen_paper_condition_context( $scope, $character );
	if ( is_wp_error( $context ) ) { return $context; }
	$ids = array( (int) $scope->ID, (int) $context['scope']->ID, (int) $character->ID );
	if ( $context['base'] ) { $ids[] = (int) $context['base']->ID; }
	return array_values( array_unique( $ids ) );
}

function ps_pen_paper_character_conditions( $scope, $character, $state ) {
	$context = ps_pen_paper_condition_context( $scope, $character );
	$policy = ps_pen_paper_condition_policy( $scope );
	if ( is_wp_error( $context ) || is_wp_error( $policy ) ) { return is_wp_error( $context ) ? $context : $policy; }
	$raw = ps_pen_paper_raw_character_state( $character->ID );
	if ( is_wp_error( $raw ) ) { return $raw; }
	$values = array();
	if ( $context['base'] ) {
		$base = ps_pen_paper_raw_character_state( $context['base']->ID );
		if ( is_wp_error( $base ) ) { return $base; }
		foreach ( $base['conditions'] ?? array() as $id => $definition ) { $values[ $id ] = array( 'definition' => $definition, 'scope' => 'universe' ); }
	}
	foreach ( $raw['conditions'] ?? array() as $id => $definition ) {
		if ( ! isset( $values[ $id ] ) ) { $values[ $id ] = array( 'definition' => $definition, 'scope' => 'local' ); }
	}
	foreach ( $policy['definitions'] as $id => $definition ) {
		if ( null === $definition['threshold'] || isset( $values[ $id ] ) || ! $state['active'] ) { continue; }
		$threshold = $definition['threshold'];
		$value = $state['active']['values'][ $threshold['resource'] ] ?? null;
		if ( ! is_array( $value ) || ! isset( $value['current'] ) || ! is_int( $value['current'] ) ) { return ps_pen_paper_condition_error(); }
		if ( $value['current'] <= $threshold['maximum'] ) { $values[ $id ] = array( 'definition' => $definition, 'scope' => 'threshold' ); }
	}
	return $values;
}

function ps_pen_paper_require_character_conditions( $scope, $character_id, $kind = 'actions', $exceptions = array(), $required = array() ) {
	if ( $exceptions ) {
		$policy = ps_pen_paper_condition_policy( $scope );
		if ( is_wp_error( $policy ) ) { return $policy; }
		if ( ! $policy['automatic'] ) { return ps_pen_paper_condition_error(); }
	}
	$character = ps_pen_paper_record_post( $character_id );
	if ( ! $character ) { return ps_pen_paper_condition_error(); }
	$state = ps_pen_paper_resolve_character_state( $scope, $character, false );
	if ( is_wp_error( $state ) ) { return $state; }
	$values = ps_pen_paper_character_conditions( $scope, $character, $state );
	if ( is_wp_error( $values ) ) { return $values; }
	if ( array_diff( $required, array_keys( $values ) ) ) { return ps_pen_paper_condition_error( __( 'Die für diese Handlung vorbereiteten Charakterzustände liegen nicht vor.', 'ps-pen-paper' ) ); }
	foreach ( $values as $id => $value ) {
		if ( $value['definition'][ 'block_' . ( 'join' === $kind ? 'join' : 'actions' ) ] && ! in_array( $id, $exceptions, true ) ) {
			return new WP_Error( 'character_condition_blocked', sprintf(
				/* translators: %s: freely configured character condition. */
				'join' === $kind ? __( 'Dieser Charakter kann wegen „%s“ keiner neuen Runde beitreten.', 'ps-pen-paper' ) : __( '„%s“ verhindert diese Spielaktion. Möglich bleiben ausdrücklich vorbereitete Rettungsaktionen und Korrekturen der zuständigen Leitung.', 'ps-pen-paper' ),
				$value['definition']['label'] ), array( 'status' => 409 ) );
		}
	}
	return true;
}

function ps_pen_paper_apply_character_conditions( $scope, $character, $state ) {
	if ( is_wp_error( $state ) || ! $state['active'] ) { return $state; }
	$values = ps_pen_paper_character_conditions( $scope, $character, $state );
	if ( is_wp_error( $values ) ) { return $values; }
	$fields = array_column( ps_pen_paper_get_rules( $scope->ID )['fields'], null, 'id' );
	foreach ( $values as $value ) {
		foreach ( $value['definition']['modifiers'] as $id => $amount ) {
			if ( ! isset( $fields[ $id ], $state['active']['values'][ $id ] ) ||
				! in_array( $fields[ $id ]['type'], array( 'attribute', 'number' ), true ) || ! is_int( $state['active']['values'][ $id ] ) ) { return ps_pen_paper_condition_error(); }
			$state['active']['values'][ $id ] += $amount;
		}
	}
	$state['condition_effects'] = $values;
	return $state;
}

function ps_pen_paper_save_condition_policy( $scope, $policy, $revision ) {
	$owner = ps_pen_paper_condition_scope( $scope );
	if ( is_wp_error( $owner ) ) { return $owner; }
	if ( (int) $owner->ID !== (int) $scope->ID || ! ps_pen_paper_can_manage_character_scope( $owner ) ) { return ps_pen_paper_condition_error(); }
	return ps_pen_paper_scope_lock( $owner->ID, function () use ( $owner, $policy, $revision ) {
		return ps_pen_paper_atomic_event( $owner->ID, function () use ( $owner, $policy, $revision ) {
			$before = ps_pen_paper_condition_policy( $owner );
			if ( is_wp_error( $before ) ) { return $before; }
			if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision || ! ps_pen_paper_valid_condition_policy( $policy ) ||
				$policy['revision'] !== $before['revision'] + 1 ) { return ps_pen_paper_condition_error(); }
			$fields = array_column( ps_pen_paper_get_rules( $owner->ID )['fields'], null, 'id' );
			foreach ( $policy['definitions'] as $definition ) {
				foreach ( $definition['modifiers'] as $id => $amount ) {
					if ( ! isset( $fields[ $id ] ) || ! in_array( $fields[ $id ]['type'], array( 'attribute', 'number' ), true ) ) { return ps_pen_paper_condition_error(); }
				}
				if ( $definition['threshold'] && 'resource' !== ( $fields[ $definition['threshold']['resource'] ]['type'] ?? null ) ) { return ps_pen_paper_condition_error(); }
			}
			return ps_pen_paper_write_document_event( $owner->ID, $owner->ID, '_pspp_condition_policy', $before, $policy,
				array( 'kind' => 'condition_policy_saved', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
		} );
	} );
}

// The caller holds all condition locks and the encompassing event transaction.
function ps_pen_paper_write_character_condition( $scope, $character, $definition, $duration, $operation, $automatic = false, $revision = null ) {
	$context = ps_pen_paper_condition_context( $scope, $character );
	$policy = ps_pen_paper_condition_policy( $scope );
	if ( is_wp_error( $context ) || is_wp_error( $policy ) ) { return is_wp_error( $context ) ? $context : $policy; }
	if ( ! ps_pen_paper_valid_condition_definition( $definition ) || ! in_array( $duration, array( 'local', 'universe' ), true ) ||
		! in_array( $operation, array( 'set', 'remove' ), true ) ) { return ps_pen_paper_condition_error(); }
	$manager = ps_pen_paper_can_manage_character_scope( $scope );
	$world_manager = ps_pen_paper_can_manage_character_scope( $context['scope'] );
	if ( $automatic ? ! $policy['automatic'] : ! $manager ) { return ps_pen_paper_condition_error(); }
	if ( ! $world_manager || $automatic ) {
		if ( ! $policy[ 'universe' === $duration ? 'campaign_universe' : 'campaign_local' ] ) { return ps_pen_paper_condition_error(); }
	}
	if ( ( 'set' === $operation || $automatic ) && ( ! isset( $policy['definitions'][ $definition['id'] ] ) ||
		$policy['definitions'][ $definition['id'] ] !== $definition ) ) { return ps_pen_paper_condition_error(); }
	$target = 'universe' === $duration ? $context['base'] : $character;
	if ( ! $target || ( 'local' === $duration && 'pspp_universe' === $scope->post_type ) ) { return ps_pen_paper_condition_error(); }
	$before = ps_pen_paper_raw_character_state( $target->ID );
	if ( is_wp_error( $before ) ) { return $before; }
	if ( null !== $revision && ( ! is_string( $revision ) || (string) $before['revision'] !== $revision ) ) { return ps_pen_paper_condition_error(); }
	$after = $before; $conditions = $before['conditions'] ?? array();
	if ( 'set' === $operation ) { $conditions[ $definition['id'] ] = $definition; } else { unset( $conditions[ $definition['id'] ] ); }
	if ( $conditions === ( $before['conditions'] ?? array() ) ) { return 0; }
	if ( ! ps_pen_paper_valid_character_conditions( $conditions ) ) { return ps_pen_paper_condition_error(); }
	$after['conditions'] = $conditions; $after['revision']++;
	$target_scope = 'universe' === $duration ? $context['scope'] : $scope;
	$rules = ps_pen_paper_get_rules( $target_scope->ID );
	return ps_pen_paper_write_document_event( $target_scope->ID, $target->ID, '_pspp_character', $before, $after,
		array( 'kind' => 'character_condition_changed', 'visibility' => 'owner', 'recipient_id' => (int) $target->post_author,
			'fields' => $rules['fields'], 'rules_revision' => $rules['revision'] ), false );
}

function ps_pen_paper_valid_condition_event( $event ) {
	if ( ! isset( $event['before']['revision'], $event['after']['revision'] ) ||
		! is_int( $event['before']['revision'] ) || $event['after']['revision'] !== $event['before']['revision'] + 1 ) { return false; }
	if ( 'condition_policy_saved' === $event['kind'] ) {
		return 'gm' === $event['visibility'] && 0 === $event['recipient_id'] &&
			ps_pen_paper_valid_condition_policy( $event['before'] ) && ps_pen_paper_valid_condition_policy( $event['after'] );
	}
	if ( 'character_condition_changed' !== $event['kind'] || 'owner' !== $event['visibility'] ||
		! is_int( $event['recipient_id'] ) || $event['recipient_id'] < 0 ||
		! ps_pen_paper_valid_character_conditions( $event['before']['conditions'] ?? array() ) ||
		! ps_pen_paper_valid_character_conditions( $event['after']['conditions'] ?? null ) ) { return false; }
	$before = $event['before']; $after = $event['after'];
	unset( $before['conditions'], $after['conditions'], $before['revision'], $after['revision'] );
	return $before === $after;
}

function ps_pen_paper_change_character_condition( $scope, $character_id, $condition_id, $duration, $operation, $revision ) {
	if ( ! ps_pen_paper_can_manage_character_scope( $scope ) ) { return ps_pen_paper_condition_error(); }
	$character = ps_pen_paper_get_character( $scope, $character_id );
	$locks = ps_pen_paper_condition_lock_ids( $scope, $character );
	if ( is_wp_error( $locks ) ) { return $locks; }
	return ps_pen_paper_adventure_action_transaction( $scope, $locks, (int) $character_id, function () use ( $scope, $character_id, $condition_id, $duration, $operation, $revision, $locks ) {
		$character = ps_pen_paper_get_character( $scope, $character_id );
		$current_locks = ps_pen_paper_condition_lock_ids( $scope, $character );
		if ( is_wp_error( $current_locks ) ) { return $current_locks; }
		if ( array_diff( $current_locks, $locks ) ) { return ps_pen_paper_condition_error(); }
		$context = ps_pen_paper_condition_context( $scope, $character );
		$policy = ps_pen_paper_condition_policy( $scope );
		if ( is_wp_error( $context ) || is_wp_error( $policy ) ) { return is_wp_error( $context ) ? $context : $policy; }
		$target = 'universe' === $duration ? $context['base'] : $character;
		$raw = $target ? ps_pen_paper_raw_character_state( $target->ID ) : ps_pen_paper_condition_error();
		if ( is_wp_error( $raw ) ) { return $raw; }
		$definition = 'remove' === $operation ? ( $raw['conditions'][ $condition_id ] ?? null ) : ( $policy['definitions'][ $condition_id ] ?? null );
		return ps_pen_paper_write_character_condition( $scope, $character, $definition, $duration, $operation, false, $revision );
	} );
}

function ps_pen_paper_valid_adventure_character_conditions( $value ) {
	if ( ! is_array( $value ) || array( 'requires', 'allows', 'effects' ) !== array_keys( $value ) ) { return false; }
	foreach ( array( 'requires', 'allows' ) as $key ) {
		if ( ! is_array( $value[ $key ] ) || array_values( array_unique( $value[ $key ], SORT_REGULAR ) ) !== $value[ $key ] || count( $value[ $key ] ) > 30 ) { return false; }
		foreach ( $value[ $key ] as $id ) { if ( ! is_string( $id ) || ! wp_is_uuid( $id ) ) { return false; } }
	}
	if ( ! is_array( $value['effects'] ) || array_values( $value['effects'] ) !== $value['effects'] || count( $value['effects'] ) > 20 ) { return false; }
	$seen = array();
	foreach ( $value['effects'] as $effect ) {
		if ( ! is_array( $effect ) || array( 'definition', 'scope', 'operation', 'when' ) !== array_keys( $effect ) ||
			! ps_pen_paper_valid_condition_definition( $effect['definition'] ) || ! in_array( $effect['scope'], array( 'local', 'universe' ), true ) ||
			! in_array( $effect['operation'], array( 'set', 'remove' ), true ) || ! in_array( $effect['when'], array( 'decision', 'success', 'failure' ), true ) ) { return false; }
		$key = $effect['definition']['id'] . ':' . $effect['scope'] . ':' . $effect['when'];
		if ( isset( $seen[ $key ] ) ) { return false; }
		$seen[ $key ] = true;
	}
	return true;
}

function ps_pen_paper_prepare_adventure_character_conditions( $scope, $input ) {
	if ( ! is_array( $input ) ) { return ps_pen_paper_condition_error(); }
	$policy = ps_pen_paper_condition_policy( $scope );
	if ( is_wp_error( $policy ) ) { return $policy; }
	$value = array( 'requires' => $input['requires'] ?? array(), 'allows' => $input['allows'] ?? array(), 'effects' => array() );
	if ( ! is_array( $input['effects'] ?? array() ) ) { return ps_pen_paper_condition_error(); }
	foreach ( $input['effects'] ?? array() as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['id'] ) || ! is_string( $row['id'] ) ) { return ps_pen_paper_condition_error(); }
		if ( '' === $row['id'] ) { continue; }
		if ( ! isset( $policy['definitions'][ $row['id'] ], $row['scope'], $row['operation'], $row['when'] ) ) { return ps_pen_paper_condition_error(); }
		$value['effects'][] = array( 'definition' => $policy['definitions'][ $row['id'] ], 'scope' => $row['scope'], 'operation' => $row['operation'], 'when' => $row['when'] );
	}
	if ( ! ps_pen_paper_valid_adventure_character_conditions( $value ) ) { return ps_pen_paper_condition_error(); }
	foreach ( array_merge( $value['requires'], $value['allows'] ) as $id ) {
		if ( ! isset( $policy['definitions'][ $id ] ) ) { return ps_pen_paper_condition_error(); }
	}
	foreach ( $value['effects'] as $effect ) {
		if ( ! $policy['automatic'] || ! $policy[ 'universe' === $effect['scope'] ? 'campaign_universe' : 'campaign_local' ] ) { return ps_pen_paper_condition_error(); }
	}
	if ( $value['allows'] && ! $policy['automatic'] ) { return ps_pen_paper_condition_error(); }
	return $value['requires'] || $value['allows'] || $value['effects'] ? $value : null;
}

function ps_pen_paper_write_adventure_character_conditions( $scope, $character, $edge, $roll, $locks ) {
	$required_locks = ps_pen_paper_condition_lock_ids( $scope, $character );
	if ( is_wp_error( $required_locks ) ) { return $required_locks; }
	if ( array_diff( $required_locks, $locks ) ) { return ps_pen_paper_condition_error(); }
	foreach ( $edge['character_conditions']['effects'] ?? array() as $effect ) {
		if ( 'decision' !== $effect['when'] && $effect['when'] !== ( null === $roll || $roll['success'] ? 'success' : 'failure' ) ) { continue; }
		$result = ps_pen_paper_write_character_condition( $scope, $character, $effect['definition'], $effect['scope'], $effect['operation'], true );
		if ( is_wp_error( $result ) ) { return $result; }
	}
	return true;
}

function ps_pen_paper_group_condition_access( $state, $operation, $input ) {
	$id = 'propose' === $operation ? ( $input['edge_id'] ?? null ) : ( $state['proposal']['edge_id'] ?? null );
	foreach ( $state['snapshot']['edges'] as $edge ) {
		if ( $id === $edge['id'] ) {
			$access = $edge['character_conditions'] ?? array();
			if ( 'vote' === $operation ) { $access['requires'] = array(); }
			return $access;
		}
	}
	return array();
}
