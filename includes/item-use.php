<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_validate_item_use_rule( $rule ) {
	if ( ! is_array( $rule ) || array_keys( $rule ) !== array( 'condition', 'expression', 'attribute', 'threshold', 'failure', 'effects', 'temporary', 'rounds' ) ||
		! in_array( $rule['condition'], array( 'immediate', 'approval', 'test', 'round' ), true ) ||
		! is_string( $rule['expression'] ) || strlen( $rule['expression'] ) > 200 ||
		! is_string( $rule['attribute'] ) || ( '' !== $rule['attribute'] && ! wp_is_uuid( $rule['attribute'] ) ) ||
		! is_int( $rule['threshold'] ) || abs( $rule['threshold'] ) > 1000000 ||
		! in_array( $rule['failure'], array( 'keep', 'consume' ), true ) ||
		! is_array( $rule['effects'] ) || ! $rule['effects'] || count( $rule['effects'] ) > 30 ||
		! is_bool( $rule['temporary'] ) || ! is_int( $rule['rounds'] ) || $rule['rounds'] < 0 || $rule['rounds'] > 1000 ||
		( ! $rule['temporary'] && 0 !== $rule['rounds'] ) ||
		( 'test' === $rule['condition'] ? ( '' === $rule['expression'] && '' === $rule['attribute'] ) : ( '' !== $rule['expression'] || '' !== $rule['attribute'] || 0 !== $rule['threshold'] || 'keep' !== $rule['failure'] ) ) ) { return false; }
	foreach ( $rule['effects'] as $id => $delta ) {
		if ( ! is_string( $id ) || ! wp_is_uuid( $id ) || ! is_int( $delta ) || 0 === $delta || abs( $delta ) > 1000000 ) { return false; }
	}
	if ( '' !== $rule['expression'] && is_wp_error( ps_pen_paper_parse_dice( $rule['expression'], array( '4', '6', '8', '10', '12', '20', '100' ) ) ) ) { return false; }
	return true;
}

function ps_pen_paper_item_effect_fields( $rules ) {
	$fields = array();
	foreach ( $rules['fields'] as $field ) {
		if ( in_array( $field['type'], array( 'attribute', 'number', 'resource' ), true ) &&
			( empty( $rules['progression'] ) || ! in_array( $field['id'], array( $rules['progression']['experience_field'], $rules['progression']['level_field'] ), true ) ) ) { $fields[ $field['id'] ] = $field; }
	}
	return $fields;
}

function ps_pen_paper_validate_item_use_for_rules( $rule, $rules ) {
	if ( ! ps_pen_paper_validate_item_use_rule( $rule ) ) { return false; }
	$fields = ps_pen_paper_item_effect_fields( $rules );
	foreach ( $rule['effects'] as $id => $delta ) { if ( ! isset( $fields[ $id ] ) ) { return false; } }
	if ( '' !== $rule['attribute'] && ( ! isset( $fields[ $rule['attribute'] ] ) || 'resource' === $fields[ $rule['attribute'] ]['type'] ) ) { return false; }
	return '' === $rule['expression'] || ! is_wp_error( ps_pen_paper_parse_dice( $rule['expression'], $rules['dice'] ) );
}

function ps_pen_paper_parse_item_use_rule( $scope, $input ) {
	if ( ! is_array( $input ) || ! isset( $input['condition'] ) || ! is_string( $input['condition'] ) ) { return ps_pen_paper_equipment_error(); }
	if ( 'equipment' === $input['condition'] ) { return null; }
	foreach ( array( 'expression', 'attribute', 'threshold', 'failure', 'temporary', 'rounds' ) as $key ) {
		if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) { return ps_pen_paper_equipment_error(); }
	}
	if ( ! preg_match( '/^-?\d{1,7}$/D', $input['threshold'] ) || ! preg_match( '/^\d{1,4}$/D', $input['rounds'] ) ||
		! in_array( $input['temporary'], array( 'permanent', 'temporary' ), true ) || ! isset( $input['effects'] ) || ! is_array( $input['effects'] ) ) { return ps_pen_paper_equipment_error(); }
	$rule = array( 'condition' => $input['condition'], 'expression' => $input['expression'], 'attribute' => $input['attribute'], 'threshold' => (int) $input['threshold'],
		'failure' => $input['failure'], 'effects' => array(), 'temporary' => 'temporary' === $input['temporary'], 'rounds' => (int) $input['rounds'] );
	$rules = ps_pen_paper_get_rules( $scope->ID );
	$fields = ps_pen_paper_item_effect_fields( $rules );
	foreach ( $input['effects'] as $id => $delta ) {
		if ( ! isset( $fields[ $id ] ) || ! is_string( $delta ) || ! preg_match( '/^-?\d{1,7}$/D', $delta ) ) { return ps_pen_paper_equipment_error(); }
		if ( 0 !== (int) $delta ) { $rule['effects'][ $id ] = (int) $delta; }
	}
	if ( 'test' !== $rule['condition'] ) { $rule['expression'] = ''; $rule['attribute'] = ''; $rule['threshold'] = 0; $rule['failure'] = 'keep'; }
	if ( ! $rule['temporary'] ) { $rule['rounds'] = 0; }
	if ( ! ps_pen_paper_validate_item_use_rule( $rule ) || ( '' !== $rule['attribute'] && ( ! isset( $fields[ $rule['attribute'] ] ) || 'resource' === $fields[ $rule['attribute'] ]['type'] ) ) ) { return ps_pen_paper_equipment_error(); }
	if ( '' !== $rule['expression'] ) {
		$parsed = ps_pen_paper_parse_dice( $rule['expression'], $rules['dice'] );
		if ( is_wp_error( $parsed ) ) { return $parsed; }
		$rule['expression'] = $parsed['expression'];
	}
	return $rule;
}

function ps_pen_paper_empty_item_uses() {
	return array( 'pending' => array(), 'effects' => array(), 'receipts' => array() );
}

function ps_pen_paper_validate_item_use_roll( $roll ) {
	if ( ! is_array( $roll ) || array_keys( $roll ) !== array( 'expression', 'modifier', 'total', 'groups' ) ||
		! is_int( $roll['modifier'] ) || ! is_int( $roll['total'] ) || ! is_array( $roll['groups'] ) || array_values( $roll['groups'] ) !== $roll['groups'] ) { return false; }
	$parsed = ps_pen_paper_parse_dice( $roll['expression'], array( '4', '6', '8', '10', '12', '20', '100' ) );
	if ( is_wp_error( $parsed ) || $parsed['modifier'] !== $roll['modifier'] || count( $parsed['groups'] ) !== count( $roll['groups'] ) ) { return false; }
	foreach ( $roll['groups'] as $i => $group ) {
		if ( ! is_array( $group ) || array_keys( $group ) !== array( 'sides', 'rolls' ) || ! is_array( $group['rolls'] ) ||
			array_values( $group['rolls'] ) !== $group['rolls'] || $parsed['groups'][ $i ]['sides'] !== $group['sides'] ||
			$parsed['groups'][ $i ]['count'] !== count( $group['rolls'] ) ) { return false; }
		foreach ( $group['rolls'] as $value ) { if ( ! is_int( $value ) || $value < 1 || $value > $group['sides'] ) { return false; } }
	}
	$total = ps_pen_paper_dice_total( $roll['groups'], $roll['modifier'] );
	return ! is_wp_error( $total ) && $total === $roll['total'];
}

// Shared contexts read raw basis values, not its independent inventory modifiers.
function ps_pen_paper_item_shared_aliases( $universe_id, $basis_id ) {
	$aliases = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_character', 'post_status' => 'private', 'posts_per_page' => -1 ) ) as $post ) {
		$raw = get_post_meta( $post->ID, '_pspp_character', true );
		if ( is_array( $raw ) && isset( $raw['context'] ) && 'shared' === ( $raw['context']['mode'] ?? null ) &&
			$universe_id === ( $raw['context']['universe_id'] ?? null ) && $basis_id === ( $raw['context']['source_id'] ?? null ) ) { $aliases[] = $post; }
	}
	return $aliases;
}

function ps_pen_paper_with_item_character_locks( $scope, $characters, $callback ) {
	$ids = array();
	$scopes = array( (int) $scope->ID );
	$contexts = array();
	if ( 'pspp_universe' === $scope->post_type ) {
		$aliases = array();
		foreach ( $characters as $character ) { $aliases = array_merge( $aliases, ps_pen_paper_item_shared_aliases( (int) $scope->ID, (int) $character->ID ) ); }
		$characters = array_merge( $characters, $aliases );
	}
	foreach ( $characters as $character ) {
		$ids[] = (int) $character->ID;
		$parent = ps_pen_paper_record_post( $character->post_parent );
		if ( ! $parent || ! in_array( $parent->post_type, array( 'pspp_campaign', 'pspp_universe' ), true ) ) { return ps_pen_paper_equipment_error( 'invalid_context' ); }
		$scopes[] = (int) $parent->ID;
		$raw = ps_pen_paper_raw_character_state( $character->ID );
		if ( is_wp_error( $raw ) ) { return $raw; }
		if ( array_key_exists( 'context', $raw ) ) {
			$valid = ps_pen_paper_validate_context( $raw['context'], ps_pen_paper_get_rules( $parent->ID ) );
			if ( is_wp_error( $valid ) ) { return $valid; }
		}
		$contexts[ $character->ID ] = $raw['context'] ?? null;
		if ( isset( $raw['context'] ) && 'shared' === ( $raw['context']['mode'] ?? null ) ) {
			$ids[] = (int) $raw['context']['source_id'];
			$scopes[] = (int) $raw['context']['universe_id'];
		}
	}
	$scopes = array_values( array_unique( $scopes ) );
	sort( $scopes, SORT_NUMERIC );
	$ids = array_values( array_unique( $ids ) );
	sort( $ids, SORT_NUMERIC );
	$lock_scopes = function ( $index ) use ( &$lock_scopes, $scopes, $callback, $contexts ) {
		if ( ! isset( $scopes[ $index ] ) ) {
			foreach ( $scopes as $scope_id ) { clean_post_cache( $scope_id ); }
			foreach ( $contexts as $id => $context ) {
				$current = ps_pen_paper_raw_character_state( $id );
				if ( is_wp_error( $current ) ) { return $current; }
				if ( ( $current['context'] ?? null ) !== $context ) { return ps_pen_paper_equipment_error( 'conflict' ); }
			}
			return $callback();
		}
		return ps_pen_paper_scope_lock( $scopes[ $index ], function () use ( &$lock_scopes, $index ) { return $lock_scopes( $index + 1 ); } );
	};
	return ps_pen_paper_with_character_locks( $ids, function () use ( $lock_scopes ) { return $lock_scopes( 0 ); } );
}

function ps_pen_paper_validate_item_uses( $uses ) {
	if ( ! is_array( $uses ) || array_keys( $uses ) !== array( 'pending', 'effects', 'receipts' ) ) { return false; }
	foreach ( $uses as $rows ) { if ( ! is_array( $rows ) || count( $rows ) > 1000 ) { return false; } }
	foreach ( $uses['pending'] as $uuid => $request ) {
		if ( ! is_string( $uuid ) || ! wp_is_uuid( $uuid ) || ! is_array( $request ) ||
			array_keys( $request ) !== array( 'context', 'condition', 'round', 'actor_id' ) ||
			! in_array( $request['context'], array( 'game', 'profile' ), true ) ||
			! in_array( $request['condition'], array( 'approval', 'round' ), true ) ||
			! is_int( $request['round'] ) || $request['round'] < 0 ||
			! is_int( $request['actor_id'] ) || $request['actor_id'] < 1 ) { return false; }
	}
	foreach ( $uses['effects'] as $uuid => $effect ) {
		if ( ! is_string( $uuid ) || ! wp_is_uuid( $uuid ) || ! is_array( $effect ) ||
			array_keys( $effect ) !== array( 'name', 'deltas', 'types', 'temporary', 'expires', 'ended' ) ||
			! is_string( $effect['name'] ) || strlen( $effect['name'] ) > 200 ||
			! is_bool( $effect['temporary'] ) || ! is_bool( $effect['ended'] ) ||
			! is_int( $effect['expires'] ) || $effect['expires'] < 0 ||
			( ! $effect['temporary'] && ( $effect['ended'] || $effect['expires'] ) ) ||
			! is_array( $effect['deltas'] ) || ! $effect['deltas'] || count( $effect['deltas'] ) > 30 ||
			! is_array( $effect['types'] ) || array_keys( $effect['types'] ) !== array_keys( $effect['deltas'] ) ) { return false; }
		foreach ( $effect['deltas'] as $id => $delta ) {
			if ( ! is_string( $id ) || ! wp_is_uuid( $id ) || ! is_int( $delta ) || 0 === $delta || abs( $delta ) > 1000000 ||
				! in_array( $effect['types'][ $id ], array( 'attribute', 'number', 'resource' ), true ) ) { return false; }
		}
	}
	foreach ( $uses['receipts'] as $uuid => $receipt ) {
		if ( ! is_string( $uuid ) || ! wp_is_uuid( $uuid ) || ! is_array( $receipt ) ||
			array_keys( $receipt ) !== ( array_key_exists( 'roll', $receipt ) ? array( 'context', 'outcome', 'total', 'threshold', 'round', 'actor_id', 'roll' ) : array( 'context', 'outcome', 'total', 'threshold', 'round', 'actor_id' ) ) ||
			! in_array( $receipt['context'], array( 'game', 'profile' ), true ) ||
			! in_array( $receipt['outcome'], array( 'applied', 'failed', 'consumed_failed', 'rejected' ), true ) ||
			( null !== $receipt['total'] && ! is_int( $receipt['total'] ) ) ||
			! is_int( $receipt['threshold'] ) || abs( $receipt['threshold'] ) > 1000000 ||
			! is_int( $receipt['round'] ) || $receipt['round'] < 0 ||
			! is_int( $receipt['actor_id'] ) || $receipt['actor_id'] < 1 ||
			( isset( $receipt['roll'] ) && ! ps_pen_paper_validate_item_use_roll( $receipt['roll'] ) ) ) { return false; }
	}
	return true;
}

function ps_pen_paper_apply_item_effects( $scope, $character, $state, $gear ) {
	$uses = isset( $gear['uses'] ) ? $gear['uses'] : ps_pen_paper_empty_item_uses();
	if ( ! ps_pen_paper_validate_item_uses( $uses ) ) { return ps_pen_paper_equipment_error(); }
	$fields = ps_pen_paper_item_effect_fields( ps_pen_paper_get_rules( $scope->ID ) );
	$totals = array();
	foreach ( $uses['effects'] as $effect ) {
		if ( $effect['ended'] || ! $effect['temporary'] ) { continue; }
		foreach ( $effect['deltas'] as $id => $delta ) {
			if ( ! isset( $fields[ $id ] ) || $effect['types'][ $id ] !== $fields[ $id ]['type'] ) { return ps_pen_paper_equipment_error( 'effect_field_changed' ); }
			// Resource changes are native so ordinary costs can spend restored values.
			if ( 'resource' === $fields[ $id ]['type'] ) { continue; }
			$totals[ $id ] = ( isset( $totals[ $id ] ) ? $totals[ $id ] : 0 ) + $delta;
		}
	}
	foreach ( $totals as $id => $delta ) {
		if ( ! $state['active'] || ! isset( $state['active']['values'][ $id ] ) ) { return ps_pen_paper_equipment_error(); }
		$resource = 'resource' === $fields[ $id ]['type'];
		$current = $resource ? ( $state['active']['values'][ $id ]['current'] ?? null ) : $state['active']['values'][ $id ];
		if ( ! is_int( $current ) ) { return ps_pen_paper_equipment_error(); }
		$value = $current + $delta;
		$maximum = $resource ? min( $fields[ $id ]['max'], $state['active']['values'][ $id ]['maximum'] ) : $fields[ $id ]['max'];
		if ( $value < $fields[ $id ]['min'] || $value > $maximum ) { return ps_pen_paper_equipment_error( 'effect_range' ); }
		if ( $resource ) { $state['active']['values'][ $id ]['current'] = $value; }
		else { $state['active']['values'][ $id ] = $value; }
	}
	$state['item_effects'] = array( 'revision' => $gear['revision'], 'modifiers' => $totals );
	return $state;
}

function ps_pen_paper_validate_item_action_policy( $policy ) {
	return is_array( $policy ) && array_keys( $policy ) === array( 'revision', 'enabled', 'during_active', 'equip', 'use' ) &&
		is_int( $policy['revision'] ) && $policy['revision'] >= 0 && is_bool( $policy['enabled'] ) && is_bool( $policy['during_active'] ) &&
		in_array( $policy['equip'], array( 'allow', 'deny', 'approval' ), true ) && in_array( $policy['use'], array( 'allow', 'deny', 'approval' ), true );
}

function ps_pen_paper_item_action_policy( $scope ) {
	$value = get_post_meta( $scope->ID, '_pspp_item_action_policy', true );
	if ( '' === $value ) { return array( 'revision' => 0, 'enabled' => false, 'during_active' => false, 'equip' => 'deny', 'use' => 'deny' ); }
	return ps_pen_paper_validate_item_action_policy( $value ) ? $value : ps_pen_paper_equipment_error( 'invalid_action_policy' );
}

function ps_pen_paper_character_sitting_out( $campaign, $character_id ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_get_character( $campaign, $character_id ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	$gear = ps_pen_paper_gear_state( $character_id );
	$round = ps_pen_paper_game_round( $campaign->ID );
	if ( is_wp_error( $gear ) || is_wp_error( $round ) ) { return is_wp_error( $gear ) ? $gear : $round; }
	foreach ( $gear['uses']['pending'] ?? array() as $request ) {
		if ( 'game' === $request['context'] && 'round' === $request['condition'] && $round['round'] <= $request['round'] ) { return true; }
	}
	return false;
}

function ps_pen_paper_require_character_game_action( $campaign, $character_id, $condition_access = array() ) {
	$round = ps_pen_paper_play_round( $campaign->ID );
	if ( is_wp_error( $round ) ) { return $round; }
	if ( $round && 'active' !== $round['status'] ) {
		return ps_pen_paper_play_error( __( 'Spielaktionen sind erst nach dem Spielstart und nicht während einer Pause möglich.', 'ps-pen-paper' ) );
	}
	if ( $round && ! in_array( (int) $character_id, array_column( array_filter( $round['participants'], static function ( $participant ) { return $participant['active']; } ), 'character_id' ), true ) ) {
		return ps_pen_paper_play_error( __( 'Dieser Charakter nimmt nicht an der laufenden Runde teil.', 'ps-pen-paper' ) );
	}
	$allowed = ps_pen_paper_require_character_conditions( $campaign, $character_id, 'actions', $condition_access['allows'] ?? array(), $condition_access['requires'] ?? array() );
	if ( is_wp_error( $allowed ) ) { return $allowed; }
	$blocked = ps_pen_paper_character_sitting_out( $campaign, $character_id );
	return is_wp_error( $blocked ) ? $blocked : ( $blocked ? new WP_Error( 'character_sitting_out', __( 'Dieser Charakter setzt für einen Gegenstandsverbrauch die aktuelle Spielrunde aus. Erst die ausdrücklich weitergezählte Spielrunde gibt Spielaktionen wieder frei.', 'ps-pen-paper' ), array( 'status' => 409 ) ) : true );
}

// Context is supplied by the server dispatcher after verifying its signed profile action.
// Never derive it from a URL, shortcode, hidden field, or submitted context value.
function ps_pen_paper_item_action_permission( $scope, $context, $kind, $entries = array(), $character_id = 0 ) {
	if ( ! in_array( $context, array( 'game', 'profile' ), true ) || ! in_array( $kind, array( 'equip', 'use' ), true ) ) { return ps_pen_paper_equipment_error( 'invalid_action_context' ); }
	if ( 'game' === $context ) { return 'allow'; }
	$policy = ps_pen_paper_item_action_policy( $scope );
	$round = ps_pen_paper_game_round( $scope->ID );
	if ( is_wp_error( $policy ) || is_wp_error( $round ) ) { return is_wp_error( $policy ) ? $policy : $round; }
	if ( ! $policy['enabled'] || ( $round['active'] && ! $policy['during_active'] ) ) { return ps_pen_paper_equipment_error( 'outside_game_denied' ); }
	if ( 'pspp_universe' === $scope->post_type && ! $policy['during_active'] && $character_id ) {
		foreach ( ps_pen_paper_item_shared_aliases( (int) $scope->ID, (int) $character_id ) as $alias ) {
			$round_state = ps_pen_paper_play_round( (int) $alias->post_parent );
			if ( is_wp_error( $round_state ) ) { return $round_state; }
			if ( $round_state && ! ps_pen_paper_play_participant( $round_state, (int) $alias->post_author ) ) { continue; }
			$play = ps_pen_paper_game_round( (int) $alias->post_parent );
			if ( is_wp_error( $play ) ) { return $play; }
			if ( $play['active'] ) { return ps_pen_paper_equipment_error( 'outside_game_denied' ); }
		}
	}
	$choices = array( $policy[ $kind ] );
	foreach ( $entries as $entry ) {
		if ( isset( $entry['outside'] ) && 'inherit' !== $entry['outside'] ) { $choices[] = $entry['outside']; }
	}

	if ( in_array( 'deny', $choices, true ) ) { return ps_pen_paper_equipment_error( 'outside_game_denied' ); }
	return in_array( 'approval', $choices, true ) ? 'approval' : 'allow';
}

function ps_pen_paper_equipment_action_permission( $campaign, $context, $before, $after, $character_id = 0 ) {
	$schema = ps_pen_paper_equipment_schema( $campaign );
	if ( is_wp_error( $schema ) ) { return $schema; }
	$entries = array();
	foreach ( array_unique( array_merge( array_keys( $before['slots'] ), array_keys( $after['slots'] ) ) ) as $slot ) {
		$old = $before['slots'][ $slot ] ?? null;
		$new = $after['slots'][ $slot ] ?? null;
		if ( $old === $new ) { continue; }
		if ( ! isset( $schema['slots'][ $slot ] ) ) { return ps_pen_paper_equipment_error(); }
		$entries[] = $schema['slots'][ $slot ];
		if ( $old ) { $entries[] = $before['items'][ $old ]['item']; }
		if ( $new ) { $entries[] = $after['items'][ $new ]['item']; }
	}
	return ps_pen_paper_item_action_permission( $campaign, $context, 'equip', $entries, $character_id );
}

function ps_pen_paper_save_item_action_policy( $scope, $input, $revision ) {
	return ps_pen_paper_atomic_event( $scope->ID, function () use ( $scope, $input, $revision ) {
		if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_character_scope( $scope ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
		$before = ps_pen_paper_item_action_policy( $scope );
		if ( is_wp_error( $before ) ) { return $before; }
		if ( ! is_string( $revision ) || $revision !== (string) $before['revision'] || ! is_array( $input ) ) { return ps_pen_paper_equipment_error( 'conflict' ); }
		foreach ( array( 'enabled', 'during_active' ) as $key ) {
			if ( array_key_exists( $key, $input ) && 'yes' !== $input[ $key ] ) { return ps_pen_paper_equipment_error(); }
		}
		$after = array( 'revision' => $before['revision'] + 1, 'enabled' => isset( $input['enabled'] ) && 'yes' === $input['enabled'],
			'during_active' => isset( $input['during_active'] ) && 'yes' === $input['during_active'], 'equip' => $input['equip'] ?? null, 'use' => $input['use'] ?? null );
		if ( ! ps_pen_paper_validate_item_action_policy( $after ) ) { return ps_pen_paper_equipment_error(); }
		return ps_pen_paper_write_document_event( $scope->ID, $scope->ID, '_pspp_item_action_policy', $before, $after,
			array( 'kind' => 'item_action_policy', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $scope->ID )['revision'] ), false );
	} );
}

function ps_pen_paper_use_item( $campaign, $character_id, $operation, $input, $revision, $context = 'game' ) {
	$character = ps_pen_paper_get_character( $campaign, $character_id );
	$allowed = ps_pen_paper_require_equipment_character_scope( $campaign, $character, $context );
	if ( is_wp_error( $allowed ) ) { return $allowed; }
	if ( 'pspp_universe' === $campaign->post_type && 'game' === $context && ps_pen_paper_can_manage_character_scope( $campaign ) &&
		in_array( $operation, array( 'review_item_use', 'end_item_effect' ), true ) ) { $context = 'profile'; }
	if ( 'pspp_universe' === $campaign->post_type && 'profile' !== $context ) { return ps_pen_paper_equipment_error( 'invalid_action_context' ); }
	$write = function () use ( $campaign, $character_id, $operation, $input, $revision, $context ) {
		$raw = ps_pen_paper_raw_character_state( $character_id );
		$source_id = ! is_wp_error( $raw ) ? ( $raw['context']['source_id'] ?? 0 ) : 0;
		clean_post_cache( $campaign->ID );
		if ( ! is_wp_error( $raw ) && isset( $raw['context']['universe_id'] ) ) { clean_post_cache( $raw['context']['universe_id'] ); }
		try {
			return ps_pen_paper_atomic_event( $character_id, function () use ( $campaign, $character_id, $operation, $input, $revision, $context ) {
				return ps_pen_paper_use_item_locked( $campaign, $character_id, $operation, $input, $revision, $context );
			} );
		} finally {
			clean_post_cache( $character_id );
			if ( $source_id ) { clean_post_cache( $source_id ); }
		}
	};
	$raw = ps_pen_paper_raw_character_state( $character_id );
	if ( is_wp_error( $raw ) ) { return $raw; }
	if ( array_key_exists( 'context', $raw ) ) {
		$valid = ps_pen_paper_validate_context( $raw['context'], ps_pen_paper_get_rules( $campaign->ID ) );
		if ( is_wp_error( $valid ) ) { return $valid; }
	}
	if ( 'pspp_universe' === $campaign->post_type ) { return ps_pen_paper_with_item_character_locks( $campaign, array( $character ), $write ); }
	return isset( $raw['context'] ) && 'shared' === ( $raw['context']['mode'] ?? null )
		? ps_pen_paper_with_character_context_locks( $campaign->ID, $character_id, $raw['context']['source_id'], $write )
		: ps_pen_paper_with_character_resource_locks( $campaign, $character, null, $write );
}

function ps_pen_paper_use_item_locked( $campaign, $character_id, $operation, $input, $revision, $context ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	$character = ps_pen_paper_get_character( $campaign, $character_id );
	$allowed = ps_pen_paper_require_equipment_character_scope( $campaign, $character, $context );
	if ( is_wp_error( $allowed ) ) { return $allowed; }
	if ( ( 'pspp_universe' === $campaign->post_type && 'profile' !== $context ) ||
		! in_array( $context, array( 'game', 'profile' ), true ) || ! is_array( $input ) ||
		! isset( $input['instance_id'] ) || ! is_string( $input['instance_id'] ) || ! wp_is_uuid( $input['instance_id'] ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	$is_gm = ps_pen_paper_can_manage_character_scope( $campaign );
	if ( ! $is_gm && (int) $character->post_author !== get_current_user_id() ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	if ( ! $is_gm && 'gm_only' === ps_pen_paper_character_mode( ps_pen_paper_get_rules( $campaign->ID ) ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	$before = ps_pen_paper_gear_state( $character_id );
	$round = ps_pen_paper_game_round( $campaign->ID );
	if ( is_wp_error( $before ) || is_wp_error( $round ) ) { return is_wp_error( $before ) ? $before : $round; }
	if ( ! is_string( $revision ) || $revision !== (string) $before['revision'] ) { return ps_pen_paper_equipment_error( 'conflict' ); }
	$uuid = $input['instance_id'];
	if ( 'game' === $context && 'use_item' === $operation ) {
		foreach ( $before['uses']['pending'] ?? array() as $pending_uuid => $request ) {
			if ( $pending_uuid !== $uuid && 'round' === $request['condition'] && 'game' === $request['context'] && $round['round'] <= $request['round'] ) { return ps_pen_paper_equipment_error( 'character_sitting_out' ); }
		}
	}
	$raw = ps_pen_paper_raw_character_state( $character_id );
	$resolved = ps_pen_paper_character_state( $campaign, $character, false );
	if ( is_wp_error( $raw ) || is_wp_error( $resolved ) ) { return is_wp_error( $raw ) ? $raw : $resolved; }
	if ( ! $resolved['active'] || null !== $raw['pending'] ) { return ps_pen_paper_equipment_error( 'character_pending' ); }
	if ( ! isset( $input['character_revision'], $input['rules_revision'] ) || ! is_string( $input['character_revision'] ) ||
		! is_string( $input['rules_revision'] ) || $input['character_revision'] !== ps_pen_paper_item_use_character_revision( $resolved ) ||
		$input['rules_revision'] !== (string) ps_pen_paper_get_rules( $campaign->ID )['revision'] ) { return ps_pen_paper_equipment_error( 'conflict' ); }
	$policy = ps_pen_paper_item_action_policy( $campaign );
	if ( is_wp_error( $policy ) ) { return $policy; }
	if ( ! isset( $input['round_revision'], $input['policy_revision'] ) || ! is_string( $input['round_revision'] ) || ! is_string( $input['policy_revision'] ) ||
		$input['round_revision'] !== (string) $round['revision'] || $input['policy_revision'] !== (string) $policy['revision'] ) { return ps_pen_paper_equipment_error( 'conflict' ); }
	$after = $before;
	$after['uses'] = isset( $before['uses'] ) ? $before['uses'] : ps_pen_paper_empty_item_uses();
	$uses = &$after['uses'];
	if ( 'end_item_effect' === $operation ) {
		if ( ! $is_gm || ! isset( $uses['effects'][ $uuid ] ) || ! $uses['effects'][ $uuid ]['temporary'] ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
		if ( $uses['effects'][ $uuid ]['ended'] ) { return 0; }
		$ended = ps_pen_paper_write_item_effect_ending( $campaign, $character, $uses['effects'][ $uuid ] );
		if ( is_wp_error( $ended ) ) { return $ended; }
		$uses['effects'][ $uuid ]['ended'] = true;
	} else {
		if ( ! in_array( $operation, array( 'use_item', 'review_item_use' ), true ) || ! isset( $before['active']['items'][ $uuid ] ) ) { return ps_pen_paper_equipment_error( 'item_unavailable' ); }
		if ( isset( $uses['receipts'][ $uuid ] ) ) { return ps_pen_paper_equipment_error( 'item_use_already_resolved' ); }
		$item = $before['active']['items'][ $uuid ]['item'];
		if ( ! isset( $item['use_rule'] ) || ! ps_pen_paper_validate_item_use_rule( $item['use_rule'] ) ) { return ps_pen_paper_equipment_error( 'not_consumable' ); }
		$rule = $item['use_rule'];
		if ( ! ps_pen_paper_validate_item_use_for_rules( $rule, ps_pen_paper_get_rules( $campaign->ID ) ) ) { return ps_pen_paper_equipment_error( 'item_rule_changed' ); }
		$pending = $uses['pending'][ $uuid ] ?? null;
		if ( 'review_item_use' === $operation && $is_gm && $pending ) { $context = $pending['context']; }
		$reject_request = 'review_item_use' === $operation && $is_gm && 'reject' === ( $input['decision'] ?? null );
		$permission = ps_pen_paper_item_action_permission( $campaign, $context, 'use', array( $item ), $character_id );
		if ( is_wp_error( $permission ) ) {
			if ( ! $reject_request ) { return $permission; }
			$permission = 'allow';
		}
		$condition = 'profile' === $context ? ( 'approval' === $permission ? 'approval' : 'immediate' ) : $rule['condition'];
		$reserved = ps_pen_paper_equipment_reserved_items( $campaign, $character_id );
		if ( is_wp_error( $reserved ) ) { return $reserved; }
		if ( ! $reject_request && ( in_array( $uuid, $reserved, true ) || in_array( $uuid, $before['active']['slots'], true ) ||
			( $before['pending'] && in_array( $uuid, $before['pending']['slots'], true ) ) ) ) { return ps_pen_paper_equipment_error( 'item_unavailable' ); }
		$rejected = false;
		if ( 'review_item_use' === $operation ) {
			if ( ! $is_gm || ! $pending || 'approval' !== $pending['condition'] || $pending['context'] !== $context ||
				! isset( $input['decision'] ) || ! in_array( $input['decision'], array( 'approve', 'reject' ), true ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
			$rejected = 'reject' === $input['decision'];
		} elseif ( $pending ) {
			if ( $pending['context'] !== $context || $pending['condition'] !== $condition ) { return ps_pen_paper_equipment_error( 'prerequisite_changed' ); }
			if ( 'approval' === $condition || ! $round['active'] || $round['round'] <= $pending['round'] ) { return ps_pen_paper_equipment_error( 'prerequisite_pending' ); }
		} elseif ( in_array( $condition, array( 'approval', 'round' ), true ) ) {
			if ( 'round' === $condition && ! $round['active'] ) { return ps_pen_paper_equipment_error( 'game_session_inactive' ); }
			$uses['pending'][ $uuid ] = array( 'context' => $context, 'condition' => $condition, 'round' => $round['round'], 'actor_id' => get_current_user_id() );
			$after['revision']++;
			return ps_pen_paper_write_item_use_event( $campaign, $character, $before, $after );
		}
		if ( count( $uses['receipts'] ) >= 1000 || count( $uses['effects'] ) >= 1000 ) { return ps_pen_paper_equipment_error( 'item_use_history_full' ); }
		$total = null;
		$rolled = null;
		$passed = ! $rejected;
		if ( 'test' === $condition && ! $rejected ) {
			$total = 0;
			if ( '' !== $rule['expression'] ) {
				$parsed = ps_pen_paper_parse_dice( $rule['expression'], ps_pen_paper_get_rules( $campaign->ID )['dice'] );
				if ( is_wp_error( $parsed ) ) { return $parsed; }
				$rolled = ps_pen_paper_evaluate_dice( $parsed );
				$total = $rolled['total'];
			}
			if ( '' !== $rule['attribute'] ) {
				$state = ps_pen_paper_character_state( $campaign, $character );
				if ( is_wp_error( $state ) ) { return $state; }
				$fields = ps_pen_paper_item_effect_fields( ps_pen_paper_get_rules( $campaign->ID ) );
				if ( ! isset( $fields[ $rule['attribute'] ], $state['active']['values'][ $rule['attribute'] ] ) || 'resource' === $fields[ $rule['attribute'] ]['type'] || ! is_int( $state['active']['values'][ $rule['attribute'] ] ) ) { return ps_pen_paper_equipment_error(); }
				$total += $state['active']['values'][ $rule['attribute'] ];
			}
			$passed = $total >= $rule['threshold'];
		}
		$consume = $passed || ( ! $rejected && 'consume' === $rule['failure'] );
		$uses['receipts'][ $uuid ] = array( 'context' => $context, 'outcome' => $rejected ? 'rejected' : ( $passed ? 'applied' : ( $consume ? 'consumed_failed' : 'failed' ) ),
			'total' => $total, 'threshold' => $rule['threshold'], 'round' => $round['round'], 'actor_id' => get_current_user_id(), 'roll' => $rolled );
		unset( $uses['pending'][ $uuid ] );
		if ( $consume ) {
			foreach ( array( 'active', 'pending' ) as $key ) { if ( null !== $after[ $key ] ) { unset( $after[ $key ]['items'][ $uuid ] ); } }
		}
		if ( $passed ) {
			$native = $rule['temporary'] ? ps_pen_paper_item_resource_deltas( $campaign, $rule['effects'] ) : $rule['effects'];
			if ( $native ) {
				$saved = ps_pen_paper_write_permanent_item_effect( $campaign, $character, $native );
				if ( is_wp_error( $saved ) ) { return $saved; }
			}
			$fields = ps_pen_paper_item_effect_fields( ps_pen_paper_get_rules( $campaign->ID ) );
			$types = array();
			foreach ( $rule['effects'] as $id => $delta ) { $types[ $id ] = $fields[ $id ]['type']; }
			$uses['effects'][ $uuid ] = array( 'name' => $item['name'], 'deltas' => $rule['effects'], 'types' => $types, 'temporary' => $rule['temporary'],
				'expires' => 'pspp_campaign' === $campaign->post_type && $rule['temporary'] && $rule['rounds'] ? $round['round'] + $rule['rounds'] : 0, 'ended' => false );
		}
	}
	$after['revision']++;
	$state = ps_pen_paper_character_state( $campaign, $character, false );
	if ( is_wp_error( $state ) ) { return $state; }
	$bonus = ps_pen_paper_gear_bonus( $campaign, $after['active'], $state['active'] );
	if ( is_wp_error( $bonus ) ) { return $bonus; }
	foreach ( $bonus as $id => $delta ) { $state['active']['values'][ $id ] += $delta; }
	$valid = ps_pen_paper_apply_item_effects( $campaign, $character, $state, $after );
	if ( is_wp_error( $valid ) ) { return $valid; }
	return ps_pen_paper_write_item_use_event( $campaign, $character, $before, $after );
}

function ps_pen_paper_item_use_character_revision( $state ) {
	return (string) $state['revision'] . ':' . ( isset( $state['base_revision'] ) ? $state['base_revision'] : 0 );
}

function ps_pen_paper_item_resource_deltas( $campaign, $deltas ) {
	$fields = ps_pen_paper_item_effect_fields( ps_pen_paper_get_rules( $campaign->ID ) );
	$result = array();
	foreach ( $deltas as $id => $delta ) { if ( isset( $fields[ $id ] ) && 'resource' === $fields[ $id ]['type'] ) { $result[ $id ] = $delta; } }
	return $result;
}

function ps_pen_paper_write_item_effect_ending( $campaign, $character, $effect ) {
	$fields = ps_pen_paper_item_effect_fields( ps_pen_paper_get_rules( $campaign->ID ) );
	foreach ( $effect['deltas'] as $id => $delta ) {
		if ( ! isset( $fields[ $id ] ) || $effect['types'][ $id ] !== $fields[ $id ]['type'] ) { return ps_pen_paper_equipment_error( 'effect_field_changed' ); }
	}
	$deltas = ps_pen_paper_item_resource_deltas( $campaign, $effect['deltas'] );
	foreach ( $deltas as &$delta ) { $delta = -$delta; }
	unset( $delta );
	return $deltas ? ps_pen_paper_write_permanent_item_effect( $campaign, $character, $deltas ) : 0;
}

// Internal writer: the caller holds character/context locks and the event transaction.
function ps_pen_paper_write_permanent_item_effect( $campaign, $character, $deltas ) {
	$before = ps_pen_paper_raw_character_state( $character->ID );
	$state = ps_pen_paper_resolve_character_state( $campaign, $character, false );
	if ( is_wp_error( $before ) || is_wp_error( $state ) ) { return is_wp_error( $before ) ? $before : $state; }
	$fields = ps_pen_paper_item_effect_fields( ps_pen_paper_get_rules( $campaign->ID ) );
	$after = $before;
	$source = null;
	$source_before = null;
	$source_after = null;
	foreach ( $deltas as $id => $delta ) {
		if ( ! isset( $fields[ $id ], $state['active']['values'][ $id ] ) ) { return ps_pen_paper_equipment_error( 'effect_field_changed' ); }
		$resource = 'resource' === $fields[ $id ]['type'];
		$current = $resource ? $state['active']['values'][ $id ]['current'] : $state['active']['values'][ $id ];
		$value = $current + $delta;
		$maximum = $resource ? min( $fields[ $id ]['max'], $state['active']['values'][ $id ]['maximum'] ) : $fields[ $id ]['max'];
		if ( ! is_int( $current ) || $value < $fields[ $id ]['min'] || $value > $maximum ) { return ps_pen_paper_equipment_error( 'effect_range' ); }
		if ( $resource && 'local' === ( $before['context']['resources'][ $id ]['mode'] ?? '' ) ) {
			$after['context']['resources'][ $id ]['current'] = $value;
		} elseif ( isset( $before['context'] ) && ! $resource ) {
			$after['context']['overrides'][ $id ] = $value;
		} elseif ( isset( $before['context'] ) && 'shared' === $before['context']['mode'] && $resource ) {
			if ( ! $source ) {
				$source = ps_pen_paper_record_post( $before['context']['source_id'] );
				$source_before = ps_pen_paper_raw_character_state( $source->ID );
				if ( is_wp_error( $source_before ) ) { return $source_before; }
				if ( null !== $source_before['pending'] ) { return ps_pen_paper_equipment_error( 'character_pending' ); }
				$source_after = $source_before;
			}
			$source_after['active']['values'][ $id ]['current'] = $value;
		} elseif ( $resource ) {
			$after['active']['values'][ $id ]['current'] = $value;
		} else {
			$after['active']['values'][ $id ] = $value;
		}
	}
	$result = 0;
	if ( $source ) {
		$valid = ps_pen_paper_context_snapshot( $source_after['active'], ps_pen_paper_get_rules( $source->post_parent ) );
		if ( is_wp_error( $valid ) ) { return $valid; }
		$source_after['revision']++;
		$result = ps_pen_paper_write_document_event( (int) $source->post_parent, $source->ID, '_pspp_character', $source_before, $source_after,
			array( 'kind' => 'character_saved', 'visibility' => 'owner', 'recipient_id' => (int) $source->post_author, 'fields' => ps_pen_paper_get_rules( $source->post_parent )['fields'], 'rules_revision' => ps_pen_paper_get_rules( $source->post_parent )['revision'] ), false );
		if ( is_wp_error( $result ) ) { return $result; }
	}
	if ( $after !== $before ) {
		$valid = ps_pen_paper_context_snapshot( $after['active'], ps_pen_paper_get_rules( $campaign->ID ) );
		if ( is_wp_error( $valid ) ) { return $valid; }
		if ( isset( $after['context'] ) ) {
			$valid = ps_pen_paper_validate_context( $after['context'], ps_pen_paper_get_rules( $campaign->ID ) );
			if ( is_wp_error( $valid ) ) { return $valid; }
		}
		$after['revision']++;
		$result = ps_pen_paper_write_document_event( $campaign->ID, $character->ID, '_pspp_character', $before, $after,
			array( 'kind' => 'character_saved', 'visibility' => 'owner', 'recipient_id' => (int) $character->post_author, 'fields' => ps_pen_paper_get_rules( $campaign->ID )['fields'], 'rules_revision' => ps_pen_paper_get_rules( $campaign->ID )['revision'] ), false );
	}
	if ( ! is_wp_error( $result ) && 'pspp_universe' === $campaign->post_type ) {
		foreach ( ps_pen_paper_item_shared_aliases( (int) $campaign->ID, (int) $character->ID ) as $alias ) {
			$scope = ps_pen_paper_record_post( $alias->post_parent );
			if ( ! $scope || 'pspp_campaign' !== $scope->post_type ) { return ps_pen_paper_equipment_error( 'invalid_context' ); }
			$valid = ps_pen_paper_resolve_character_state( $scope, $alias );
			if ( is_wp_error( $valid ) ) { return $valid; }
		}
	}
	return $result;
}

function ps_pen_paper_write_item_use_event( $campaign, $character, $before, $after ) {
	return ps_pen_paper_write_document_event( $campaign->ID, $character->ID, '_pspp_gear', $before, $after,
		array( 'kind' => 'gear_saved', 'visibility' => 'owner', 'recipient_id' => (int) $character->post_author, 'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $campaign->ID )['revision'] ), false );
}
