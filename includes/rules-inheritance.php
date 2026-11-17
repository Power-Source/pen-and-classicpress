<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_inheritance_globals() {
	return array(
		'name' => __( 'Name des Regelwerks', 'ps-pen-paper' ),
		'description' => __( 'Regelbeschreibung', 'ps-pen-paper' ),
		'character_mode' => __( 'Charakterbearbeitung', 'ps-pen-paper' ),
		'dice' => __( 'Erlaubte Würfel', 'ps-pen-paper' ),
		'dice_mechanics' => __( 'Würfelmechaniken', 'ps-pen-paper' ),
		'roll_visibilities' => __( 'Wurfsichtbarkeiten', 'ps-pen-paper' ),
		'allocation' => __( 'Punkteverteilung', 'ps-pen-paper' ),
		'progression' => __( 'Erfahrung und Stufen', 'ps-pen-paper' ),
	);
}

function ps_pen_paper_inheritance_policy_default() {
	return array( 'extra_fields' => false, 'globals' => array(), 'fields' => array() );
}

function ps_pen_paper_valid_inheritance_base( $rules ) {
	return array( 'revision' => 0, 'name' => '', 'description' => '', 'dice' => array(), 'fields' => array() ) === $rules ||
		ps_pen_paper_valid_rules_document( $rules );
}

function ps_pen_paper_parse_inheritance_policy( $input, $rules, $form = false ) {
	$error = new WP_Error( 'invalid_inheritance_policy', __( 'Die Freigaben oder Grenzen für Kampagnenregeln sind ungültig. Bitte prüfe die Universumsvorgaben.', 'ps-pen-paper' ) );
	if ( ! is_array( $input ) || array_diff( array_keys( $input ), array( 'extra_fields', 'globals', 'fields' ) ) ) { return $error; }
	$extra = $input['extra_fields'] ?? ( $form ? false : null );
	if ( $form ) {
		if ( false !== $extra && '1' !== $extra ) { return $error; }
		$extra = '1' === $extra;
	}
	if ( ! is_bool( $extra ) ) { return $error; }
	$globals = $input['globals'] ?? array();
	if ( ! is_array( $globals ) || array_values( $globals ) !== $globals ||
		count( $globals ) !== count( array_unique( $globals, SORT_REGULAR ) ) ) { return $error; }
	foreach ( $globals as $key ) {
		if ( ! is_string( $key ) || ! isset( ps_pen_paper_inheritance_globals()[ $key ] ) ) { return $error; }
	}
	$fields = $input['fields'] ?? array();
	if ( ! is_array( $fields ) || count( $fields ) > 30 ) { return $error; }
	$known = array_column( $rules['fields'], null, 'id' );
	$clean = array();
	foreach ( $fields as $id => $configuration ) {
		if ( ! is_string( $id ) || ! isset( $known[ $id ] ) || ! is_array( $configuration ) ||
			array_diff( array_keys( $configuration ), array( 'enabled', 'min', 'max' ) ) ) { return $error; }
		if ( $form && ! isset( $configuration['enabled'] ) ) { continue; }
		if ( $form && '1' !== $configuration['enabled'] ) { return $error; }
		if ( ! $form && ( isset( $configuration['enabled'] ) || ! array_key_exists( 'min', $configuration ) || ! array_key_exists( 'max', $configuration ) ) ) { return $error; }
		$bounds = array();
		foreach ( array( 'min', 'max' ) as $key ) {
			$value = $configuration[ $key ] ?? null;
			if ( $form && '' === $value ) { $value = null; }
			if ( $form && is_string( $value ) && preg_match( '/^-?\d{1,7}$/D', $value ) ) { $value = (int) $value; }
			if ( null !== $value && ( ! is_int( $value ) || abs( $value ) > 1000000 ) ) { return $error; }
			$bounds[ $key ] = $value;
		}
		$numeric = in_array( $known[ $id ]['type'], array( 'number', 'attribute', 'resource' ), true );
		if ( ( ! $numeric && ( null !== $bounds['min'] || null !== $bounds['max'] ) ) ||
			( null !== $bounds['min'] && null !== $bounds['max'] && $bounds['min'] > $bounds['max'] ) ||
			( $numeric && ( ( null !== $bounds['min'] && $known[ $id ]['min'] < $bounds['min'] ) ||
				( null !== $bounds['max'] && $known[ $id ]['max'] > $bounds['max'] ) ) ) ) { return $error; }
		$clean[ $id ] = $bounds;
	}
	return array( 'extra_fields' => $extra, 'globals' => $globals, 'fields' => $clean );
}

function ps_pen_paper_inheritance_source( $campaign_id ) {
	$link = ps_pen_paper_get_universe_link( $campaign_id );
	if ( is_wp_error( $link ) ) { return $link; }
	if ( ! isset( $link['rules_base'] ) ) { return null; }
	$universe = ps_pen_paper_get_universe( $link['universe_id'] );
	if ( ! $universe ) { return new WP_Error( 'invalid_inheritance', __( 'Das Universum der Regelvererbung ist nicht verfügbar.', 'ps-pen-paper' ) ); }
	$source = ps_pen_paper_get_rules( $universe->ID );
	if ( ! ps_pen_paper_valid_inheritance_base( $source ) ) {
		return new WP_Error( 'invalid_inheritance', __( 'Das Universumsregelwerk ist beschädigt. Bitte informiere die Administration.', 'ps-pen-paper' ) );
	}
	return array( 'link' => $link, 'rules' => $source );
}

function ps_pen_paper_inheritance_value( $rules, $key ) {
	if ( 'character_mode' === $key ) { return ps_pen_paper_character_mode( $rules ); }
	if ( 'dice_mechanics' === $key ) { return ps_pen_paper_allowed_dice_mechanics( $rules ); }
	if ( 'roll_visibilities' === $key ) { return ps_pen_paper_allowed_roll_visibilities( $rules ); }
	return $rules[ $key ] ?? null;
}

function ps_pen_paper_validate_inherited_rules( $rules, $source ) {
	$policy = $source['campaign_policy'] ?? ps_pen_paper_inheritance_policy_default();
	$error = new WP_Error( 'fixed_universe_rule', __( 'Diese Änderung widerspricht einer festen Universumsregel oder einer vorgegebenen Grenze. Bitte prüfe die Freigaben der Universumsleitung.', 'ps-pen-paper' ) );
	if ( ( $rules['campaign_policy'] ?? null ) !== ( $source['campaign_policy'] ?? null ) ) { return $error; }
	foreach ( ps_pen_paper_inheritance_globals() as $key => $label ) {
		if ( ! in_array( $key, $policy['globals'], true ) && ps_pen_paper_inheritance_value( $rules, $key ) !== ps_pen_paper_inheritance_value( $source, $key ) ) { return $error; }
	}
	$world_fields = array_column( $source['fields'], null, 'id' );
	$campaign_fields = array_column( $rules['fields'], null, 'id' );
	foreach ( $world_fields as $id => $field ) {
		if ( ! isset( $campaign_fields[ $id ] ) ) { return $error; }
		$changed = $campaign_fields[ $id ];
		if ( ! isset( $policy['fields'][ $id ] ) ) {
			if ( $field !== $changed ) { return $error; }
			continue;
		}
		if ( $changed['type'] !== $field['type'] ) { return $error; }
		$bounds = $policy['fields'][ $id ];
		if ( in_array( $field['type'], array( 'number', 'attribute', 'resource' ), true ) &&
			( ( null !== $bounds['min'] && $changed['min'] < $bounds['min'] ) ||
				( null !== $bounds['max'] && $changed['max'] > $bounds['max'] ) ) ) { return $error; }
	}
	if ( ! $policy['extra_fields'] && array_diff( array_keys( $campaign_fields ), array_keys( $world_fields ) ) ) { return $error; }
	return true;
}

function ps_pen_paper_require_inherited_rules( $campaign_id, $rules = null ) {
	$source = ps_pen_paper_inheritance_source( $campaign_id );
	if ( null === $source || is_wp_error( $source ) ) { return $source ?: true; }
	if ( $source['link']['rules_base'] !== $source['rules'] ) {
		return new WP_Error( 'universe_rules_changed', __( 'Die Universumsregeln wurden geändert. Die Spielleitung muss die Übernahme und mögliche Konflikte zuerst prüfen; neue Spiele sind bis dahin gesperrt.', 'ps-pen-paper' ) );
	}
	if ( 0 === $source['rules']['revision'] ) {
		return new WP_Error( 'universe_rules_missing', __( 'Die Universumsleitung muss zuerst ein Regelwerk einrichten.', 'ps-pen-paper' ) );
	}
	return ps_pen_paper_validate_inherited_rules( null === $rules ? ps_pen_paper_get_rules( $campaign_id ) : $rules, $source['rules'] );
}

function ps_pen_paper_validate_inherited_edit( $campaign_id, $rules ) {
	$source = ps_pen_paper_inheritance_source( $campaign_id );
	if ( null === $source || is_wp_error( $source ) ) { return $source ?: true; }
	$base = $source['link']['rules_base'];
	if ( 0 === $base['revision'] ) {
		return new WP_Error( 'universe_rules_missing', __( 'Die Universumsleitung muss zuerst ein Regelwerk einrichten.', 'ps-pen-paper' ) );
	}
	return ps_pen_paper_validate_inherited_rules( $rules, $base );
}

function ps_pen_paper_with_inheritance_lock( $scope_id, $callback ) {
	$scope = ps_pen_paper_get_character_scope( $scope_id );
	if ( ! $scope || 'pspp_campaign' !== $scope->post_type ) { return $callback(); }
	$link = ps_pen_paper_get_universe_link( $scope_id );
	if ( is_wp_error( $link ) ) { return $link; }
	return isset( $link['rules_base'] ) ? ps_pen_paper_scope_lock( $link['universe_id'], $callback ) : $callback();
}

function ps_pen_paper_rebase_inherited_rules( $base, $local, $source ) {
	$error = new WP_Error( 'inheritance_conflict', __( 'Die Universumsänderung kollidiert mit Kampagnenanpassungen oder Charakterdaten. Die Spielleitung muss die Übernahme ausdrücklich prüfen.', 'ps-pen-paper' ) );
	if ( ! ps_pen_paper_valid_inheritance_base( $base ) || ! ps_pen_paper_valid_inheritance_base( $local ) ||
		! ps_pen_paper_valid_rules_document( $source ) ) { return $error; }
	$policy = $source['campaign_policy'] ?? ps_pen_paper_inheritance_policy_default();
	$merged = $source;
	$merged['revision'] = $local['revision'] + 1;
	foreach ( ps_pen_paper_inheritance_globals() as $key => $label ) {
		$old = ps_pen_paper_inheritance_value( $base, $key );
		$override = ps_pen_paper_inheritance_value( $local, $key );
		if ( $old === $override ) { continue; }
		if ( ! in_array( $key, $policy['globals'], true ) ) { return $error; }
		$merged[ $key ] = $override;
	}
	$old_fields = array_column( $base['fields'], null, 'id' );
	$local_fields = array_column( $local['fields'], null, 'id' );
	$new_fields = array_column( $source['fields'], null, 'id' );
	foreach ( $old_fields as $id => $field ) {
		if ( ! isset( $local_fields[ $id ] ) ) { return $error; }
		$override = $local_fields[ $id ];
		if ( $override === $field ) { continue; }
		if ( ! isset( $new_fields[ $id ], $policy['fields'][ $id ] ) ||
			$field['type'] !== $override['type'] || $field['type'] !== $new_fields[ $id ]['type'] ) { return $error; }
		foreach ( array_unique( array_merge( array_keys( $field ), array_keys( $override ) ) ) as $key ) {
			if ( in_array( $key, array( 'id', 'type' ), true ) || ( $field[ $key ] ?? null ) === ( $override[ $key ] ?? null ) ) { continue; }
			if ( array_key_exists( $key, $override ) ) { $new_fields[ $id ][ $key ] = $override[ $key ]; }
			else { unset( $new_fields[ $id ][ $key ] ); }
		}
	}
	foreach ( $local_fields as $id => $field ) {
		if ( isset( $old_fields[ $id ] ) ) { continue; }
		if ( ! $policy['extra_fields'] || isset( $new_fields[ $id ] ) ) { return $error; }
		$new_fields[ $id ] = $field;
	}
	$merged['fields'] = array_values( $new_fields );
	if ( ! ps_pen_paper_valid_rules_document( $merged ) || is_wp_error( ps_pen_paper_validate_inherited_rules( $merged, $source ) ) ) { return $error; }
	return $merged;
}

function ps_pen_paper_inheritance_rules_input( $rules ) {
	$input = $rules;
	foreach ( $input['fields'] as &$field ) {
		if ( $field['required'] ) { $field['required'] = '1'; } else { unset( $field['required'] ); }
		if ( in_array( $field['type'], array( 'number', 'attribute', 'resource' ), true ) ) {
			foreach ( array( 'min', 'max', 'start' ) as $key ) { $field[ $key ] = (string) $field[ $key ]; }
		}
	}
	unset( $field );
	$input['allocation_mode'] = empty( $rules['allocation'] ) ? 'none' : ( isset( $rules['allocation']['per_level'] ) ? 'level' : 'configured' );
	if ( ! empty( $input['allocation'] ) ) {
		$input['allocation']['points'] = (string) $input['allocation']['points'];
		if ( isset( $input['allocation']['per_level'] ) ) { $input['allocation']['per_level'] = (string) $input['allocation']['per_level']; }
	}
	if ( array_key_exists( 'progression', $rules ) ) {
		$input['progression_mode'] = empty( $rules['progression'] ) ? 'none' : 'configured';
	}
	return $input;
}

function ps_pen_paper_inheritance_content_conflict( $campaign_id, $before, $after ) {
	if ( ps_pen_paper_character_schema( $before ) === ps_pen_paper_character_schema( $after ) && $before['dice'] === $after['dice'] ) { return true; }
	$dependent = get_posts( array(
		'post_type' => array( 'pspp_adventure', 'pspp_item', 'pspp_loot_table', 'pspp_npc', 'pspp_adventure_run', 'pspp_adventure_group' ),
		'post_status' => array( 'private', 'draft', 'trash' ), 'post_parent' => $campaign_id, 'posts_per_page' => 1, 'fields' => 'ids',
	) );
	return $dependent
		? new WP_Error( 'inheritance_content_conflict', __( 'Vorbereitete Abenteuer, Gegenstände oder andere Spielinhalte können von der Universumsänderung betroffen sein. Ihre Auswirkungen können noch nicht automatisch geprüft werden; die Übernahme bleibt gesperrt.', 'ps-pen-paper' ) )
		: true;
}

function ps_pen_paper_pending_inherited_campaigns( $universe_id ) {
	$pending = 0;
	$source = ps_pen_paper_get_rules( $universe_id );
	foreach ( get_posts( array(
		'post_type' => 'pspp_campaign', 'post_status' => 'private', 'posts_per_page' => -1,
		'meta_query' => array( array( 'key' => '_pspp_play_round', 'compare' => 'NOT EXISTS' ) ),
	) ) as $campaign ) {
		if ( ! ps_pen_paper_can_view_campaign( $campaign ) ) { continue; }
		$link = ps_pen_paper_get_universe_link( $campaign->ID );
		if ( is_wp_error( $link ) ) { continue; }
		if ( $link['universe_id'] === (int) $universe_id && isset( $link['rules_base'] ) && $link['rules_base'] !== $source ) { $pending++; }
	}
	return $pending;
}

function ps_pen_paper_synchronize_inherited_rules( $campaign ) {
	if ( ! $campaign || 'pspp_campaign' !== $campaign->post_type || ! ps_pen_paper_can_access_site() ) {
		return new WP_Error( 'forbidden', __( 'Diese Kampagne ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	$link = ps_pen_paper_get_universe_link( $campaign->ID );
	if ( is_wp_error( $link ) ) { return $link; }
	$can_sync = ps_pen_paper_can_view_campaign( $campaign ) ||
		( $link['universe_id'] && ps_pen_paper_can_manage_universe( ps_pen_paper_get_universe( $link['universe_id'] ) ) );
	if ( ! $can_sync ) { return new WP_Error( 'forbidden', __( 'Diese Kampagne ist für dich nicht verfügbar.', 'ps-pen-paper' ) ); }
	if ( ! isset( $link['rules_base'] ) ) { return true; }
	return ps_pen_paper_scope_lock( $link['universe_id'], function () use ( $campaign, $link ) {
		return ps_pen_paper_atomic_event( (int) $campaign->ID, function () use ( $campaign, $link ) {
			$current = ps_pen_paper_inheritance_source( $campaign->ID );
			if ( is_wp_error( $current ) ) { return $current; }
			if ( ! $current || $current['link'] !== $link ||
				( ! ps_pen_paper_can_view_campaign( $campaign ) && ! ps_pen_paper_can_manage_universe( ps_pen_paper_get_universe( $link['universe_id'] ) ) ) ) {
				return new WP_Error( 'conflict', __( 'Die Universumszuordnung wurde geändert. Bitte lade die Kampagne neu.', 'ps-pen-paper' ) );
			}
			$before = ps_pen_paper_get_rules( $campaign->ID );
			if ( $link['rules_base'] === $current['rules'] ) {
				$valid = ps_pen_paper_require_inherited_rules( $campaign->ID, $before );
				return is_wp_error( $valid ) ? $valid : 0;
			}
			$after = ps_pen_paper_rebase_inherited_rules( $link['rules_base'], $before, $current['rules'] );
			if ( is_wp_error( $after ) ) { return $after; }
			$schema_changed = ps_pen_paper_character_schema( $before ) !== ps_pen_paper_character_schema( $after );
			$characters = get_posts( array(
				'post_type' => 'pspp_character', 'post_status' => array( 'private', 'draft', 'trash' ),
				'post_parent' => $campaign->ID, 'posts_per_page' => 1, 'fields' => 'ids',
			) );
			if ( $schema_changed && $characters ) {
				return new WP_Error( 'inheritance_migration_required', __( 'Die Universumsregel ändert verwendete Charakterfelder. Die Spielleitung muss die Übernahme mit einer Charaktervorschau prüfen; neue Spiele bleiben bis dahin gesperrt.', 'ps-pen-paper' ) );
			}
			$content_check = ps_pen_paper_inheritance_content_conflict( $campaign->ID, $before, $after );
			if ( is_wp_error( $content_check ) ) { return $content_check; }
			$event = ps_pen_paper_write_document_event( $campaign->ID, $campaign->ID, '_pspp_rules', $before, $after, array(
				'kind' => 'rules_saved', 'visibility' => 'group', 'recipient_id' => 0, 'rules_revision' => $after['revision'], 'fields' => array(),
			), false );
			if ( is_wp_error( $event ) ) { return $event; }
			$updated = $link; $updated['revision']++; $updated['rules_base'] = $current['rules'];
			return ps_pen_paper_write_document_event( $campaign->ID, $campaign->ID, '_pspp_universe_link', $link, $updated, array(
				'kind' => 'universe_linked', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0,
			), false );
		} );
	} );
}

function ps_pen_paper_sync_universe_campaigns( $universe_id ) {
	foreach ( get_posts( array(
		'post_type' => 'pspp_campaign', 'post_status' => 'private', 'posts_per_page' => -1,
		'meta_query' => array( array( 'key' => '_pspp_play_round', 'compare' => 'NOT EXISTS' ) ),
	) ) as $campaign ) {
		$link = ps_pen_paper_get_universe_link( $campaign->ID );
		if ( is_wp_error( $link ) ) { error_log( 'PS Pen & Paper: invalid universe link for campaign #' . (int) $campaign->ID ); continue; }
		if ( $link['universe_id'] !== (int) $universe_id || ! isset( $link['rules_base'] ) ) { continue; }
		$result = ps_pen_paper_synchronize_inherited_rules( $campaign );
		if ( is_wp_error( $result ) ) {
			error_log( 'PS Pen & Paper: universe rule synchronization pending for campaign #' . (int) $campaign->ID . ' (' . $result->get_error_code() . ').' );
		}
	}
}
