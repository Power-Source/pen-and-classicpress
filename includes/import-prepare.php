<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_prepare_import_error( $message = null ) {
	return new WP_Error( 'invalid_import', null === $message
		? __( 'Die Sicherungsdaten sind unvollständig, ungültig oder besitzen nicht auflösbare Verknüpfungen.', 'ps-pen-paper' )
		: $message );
}

function ps_pen_paper_prepare_import_known_meta() {
	return array(
		'_pspp_gm', '_pspp_player', '_pspp_access', '_pspp_membership', '_pspp_archived', '_pspp_rules',
		'_pspp_universe_link', '_pspp_universe', '_pspp_turn', '_pspp_scene_selection', '_pspp_backdrop',
		'_pspp_style', '_pspp_sheet_style', '_pspp_equipment_schema', '_pspp_currency', '_pspp_wallet',
		'_pspp_trade_policy', '_pspp_character', '_pspp_gear', '_pspp_portrait', '_pspp_event', '_pspp_roll',
		'_pspp_visibility', '_pspp_recipient', '_pspp_actor', '_pspp_scene', '_pspp_folio', '_pspp_npc',
		'_pspp_wiki', '_pspp_wiki_entry', '_pspp_wiki_owner', '_pspp_item', '_pspp_loot_table',
		'_pspp_loot_run', '_pspp_trade', '_pspp_adventure', '_pspp_adventure_run', '_pspp_adventure_group',
		'_pspp_import_epoch', '_pspp_import_receipt', '_pspp_import_backup', '_pspp_import_archive',
		'_pspp_invitations', '_pspp_wiki_links', '_pspp_handout_asset',
		'_pspp_item_origin', '_pspp_transfer_receipts',
		'_pspp_character_policy', '_pspp_member_invitations', '_pspp_universe_members',
		'_pspp_game_round', '_pspp_item_action_policy',
		'_pspp_play_options', '_pspp_play_round', '_pspp_play_token', '_pspp_play_origin',
	);
}

function ps_pen_paper_prepare_import_type_meta() {
	$campaign = array( '_pspp_gm', '_pspp_player', '_pspp_access', '_pspp_membership', '_pspp_archived',
		'_pspp_rules', '_pspp_invitations', '_pspp_wiki_links', '_pspp_universe_link', '_pspp_turn', '_pspp_scene_selection', '_pspp_backdrop',
		'_pspp_style', '_pspp_sheet_style', '_pspp_equipment_schema', '_pspp_currency', '_pspp_trade_policy',
		'_pspp_import_epoch', '_pspp_import_receipt', '_pspp_import_backup', '_pspp_import_archive', '_pspp_member_invitations', '_pspp_game_round', '_pspp_item_action_policy',
		'_pspp_play_options', '_pspp_play_round', '_pspp_play_token' );
	$universe = array( '_pspp_rules', '_pspp_universe', '_pspp_style', '_pspp_sheet_style', '_pspp_equipment_schema',
		'_pspp_currency', '_pspp_trade_policy', '_pspp_wiki', '_pspp_import_epoch', '_pspp_import_receipt',
		'_pspp_import_backup', '_pspp_import_archive', '_pspp_character_policy', '_pspp_member_invitations', '_pspp_universe_members', '_pspp_item_action_policy' );
	return array(
		'pspp_campaign' => $campaign,
		'pspp_universe' => $universe,
		'pspp_character' => array( '_pspp_character', '_pspp_gear', '_pspp_wallet', '_pspp_portrait', '_pspp_transfer_receipts', '_pspp_import_archive' ),
		'pspp_roll' => array( '_pspp_roll', '_pspp_visibility', '_pspp_recipient', '_pspp_actor', '_pspp_import_archive' ),
		'pspp_event' => array( '_pspp_event', '_pspp_visibility', '_pspp_recipient', '_pspp_actor', '_pspp_import_archive' ),
		'pspp_asset' => array( '_pspp_import_archive', '_pspp_handout_asset' ),
		'pspp_scene' => array( '_pspp_scene', '_pspp_import_archive' ),
		'pspp_folio' => array( '_pspp_folio', '_pspp_import_archive' ),
		'pspp_npc' => array( '_pspp_npc', '_pspp_import_archive' ),
		'pspp_wiki_entry' => array( '_pspp_wiki_entry', '_pspp_import_archive' ),
		'pspp_item' => array( '_pspp_item', '_pspp_item_origin', '_pspp_import_archive' ),
		'pspp_loot_table' => array( '_pspp_loot_table', '_pspp_import_archive' ),
		'pspp_loot_run' => array( '_pspp_loot_run', '_pspp_import_archive' ),
		'pspp_trade' => array( '_pspp_trade', '_pspp_import_archive' ),
		'pspp_adventure' => array( '_pspp_adventure', '_pspp_import_archive', '_pspp_play_origin' ),
		'pspp_adventure_run' => array( '_pspp_adventure_run', '_pspp_import_archive' ),
		'pspp_adventure_group' => array( '_pspp_adventure_group', '_pspp_import_archive' ),
	);
}

function ps_pen_paper_prepare_import_scope( $record, $records ) {
	if ( 'pspp_campaign' === $record['type'] ) { return $record['id']; }
	if ( 'pspp_universe' === $record['type'] ) { return $record['id']; }
	return isset( $records[ $record['parent_id'] ] ) ? $record['parent_id'] : 0;
}

function ps_pen_paper_prepare_import_mapped_user( $source_id, $options ) {
	if ( 0 === $source_id ) { return 0; }
	if ( ! isset( $options['users'][ $source_id ] ) ) { return new WP_Error( 'invalid_import', 'Missing user mapping.' ); }
	$mapped = $options['users'][ $source_id ];
	if ( -1 === $mapped ) {
		return ps_pen_paper_prepare_import_error();
	}
	return $mapped > 0 ? $mapped : ps_pen_paper_prepare_import_error();
}

function ps_pen_paper_prepare_import_historical_snapshot_user( $source_id, $options, &$archive, $path ) {
	if ( 0 === $source_id ) { return 0; }
	if ( ! isset( $options['users'][ $source_id ] ) ) { return ps_pen_paper_prepare_import_error(); }
	$mapped = $options['users'][ $source_id ];
	if ( -1 !== $mapped ) { return $mapped > 0 ? $mapped : ps_pen_paper_prepare_import_error(); }
	$archive['references'][] = array( 'kind' => 'historical_user', 'path' => $path, 'source_id' => $source_id );
	$archive['warnings'][] = __( 'Eine historische Benutzer-ID blieb ausschließlich als Herkunftsangabe erhalten; sie wird nicht als Zielkonto aufgelöst.', 'ps-pen-paper' );
	return $source_id;
}

function ps_pen_paper_prepare_import_historical_header_user( $source_id, $options, &$archive, $path ) {
	if ( 0 === $source_id ) { return 0; }
	if ( ! isset( $options['users'][ $source_id ] ) ) { return ps_pen_paper_prepare_import_error(); }
	if ( -1 === $options['users'][ $source_id ] ) {
		$archive['references'][] = array( 'kind' => 'unmapped_historical_user', 'path' => $path, 'source_id' => $source_id );
		$archive['warnings'][] = __( 'Ein nicht zugeordnetes historisches Konto wurde aus aktiven Protokollköpfen entfernt und nur als private Herkunftsreferenz archiviert.', 'ps-pen-paper' );
		return 0;
	}
	return ps_pen_paper_prepare_import_mapped_user( $source_id, $options );
}

function ps_pen_paper_prepare_import_id( $source_id, $expected_type, $records, $id_map, $source_scope = null ) {
	if ( ! is_int( $source_id ) || $source_id < 1 || ! isset( $id_map[ $source_id ] ) ||
		! is_int( $id_map[ $source_id ] ) || $id_map[ $source_id ] < 1 ) { return ps_pen_paper_prepare_import_error(); }
	if ( isset( $records[ $source_id ] ) ) {
		if ( $expected_type && $expected_type !== $records[ $source_id ]['type'] ) { return ps_pen_paper_prepare_import_error(); }
		if ( null !== $source_scope && $source_scope !== $records[ $source_id ]['parent_id'] &&
			$source_scope !== $records[ $source_id ]['id'] ) { return ps_pen_paper_prepare_import_error(); }
	}
	return $id_map[ $source_id ];
}

function ps_pen_paper_prepare_import_asset( $source_id, $scope_id, $records, $id_map ) {
	if ( 0 === $source_id ) { return 0; }
	if ( ! isset( $records[ $source_id ] ) || 'pspp_asset' !== $records[ $source_id ]['type'] ||
		'private' !== $records[ $source_id ]['status'] || $records[ $source_id ]['parent_id'] !== $scope_id ) { return ps_pen_paper_prepare_import_error(); }
	return ps_pen_paper_prepare_import_id( $source_id, 'pspp_asset', $records, $id_map, $scope_id );
}

function ps_pen_paper_prepare_import_asset_value( &$source_id, $scope_id, $records, $id_map, $options, &$archive, $path ) {
	if ( 0 === $source_id ) { return true; }
	if ( 'bind' === $options['universe_mode'] && isset( $records[ $scope_id ] ) &&
		$scope_id === (int) $records[ $scope_id ]['id'] &&
		'pspp_universe' === $records[ $scope_id ]['type'] ) {
		return new WP_Error( 'unresolved_bound_asset', __( 'Der Import verweist auf ein aktives Bild aus dem gebundenen, nicht überschriebenen Universum. Entferne oder ersetze diesen Bildverweis im Export oder importiere das Universum als Kopie; ein aktives Bild wird nicht stillschweigend entfernt.', 'ps-pen-paper' ) );
	}
	$asset = ps_pen_paper_prepare_import_asset( $source_id, $scope_id, $records, $id_map );
	if ( is_wp_error( $asset ) ) { return $asset; }
	$source_id = $asset;
	return true;
}

function ps_pen_paper_prepare_import_remap_item_snapshot( &$instance, $records, $id_map, $options, &$archive, $path ) {
	if ( ! is_array( $instance ) || ! isset( $instance['scope_id'], $instance['item']['asset_id'] ) ) {
		return ps_pen_paper_prepare_import_error();
	}
	$scope_id = $instance['scope_id'];
	if ( ! is_int( $scope_id ) || ! isset( $records[ $scope_id ] ) ||
		! in_array( $records[ $scope_id ]['type'], array( 'pspp_campaign', 'pspp_universe' ), true ) ||
		! isset( $id_map[ $scope_id ] ) ) { return ps_pen_paper_prepare_import_error(); }
	$result = ps_pen_paper_prepare_import_asset_value( $instance['item']['asset_id'], $scope_id,
		$records, $id_map, $options, $archive, $path . '.item.asset_id' );
	if ( is_wp_error( $result ) ) { return $result; }
	$instance['scope_id'] = $id_map[ $scope_id ];
	return true;
}

function ps_pen_paper_prepare_import_scene( $source_id, $scope_id, $records, $id_map ) {
	if ( 0 === $source_id ) { return 0; }
	if ( ! isset( $records[ $source_id ] ) || 'pspp_scene' !== $records[ $source_id ]['type'] ||
		'private' !== $records[ $source_id ]['status'] || $records[ $source_id ]['parent_id'] !== $scope_id ) { return ps_pen_paper_prepare_import_error(); }
	return ps_pen_paper_prepare_import_id( $source_id, 'pspp_scene', $records, $id_map, $scope_id );
}

function ps_pen_paper_prepare_import_remap_scene_snapshot( &$snapshot, $scope_id, $records, $id_map ) {
	if ( ! is_array( $snapshot ) ) { return ps_pen_paper_prepare_import_error(); }
	if ( isset( $snapshot['asset_id'] ) ) {
		$asset = ps_pen_paper_prepare_import_asset( $snapshot['asset_id'], $scope_id, $records, $id_map );
		if ( is_wp_error( $asset ) ) { return $asset; }
		$snapshot['asset_id'] = $asset;
	}
	return true;
}

function ps_pen_paper_prepare_import_remap_adventure_graph( &$graph, $scope_id, $records, $id_map ) {
	if ( ! is_array( $graph ) || ! isset( $graph['start_scene'], $graph['edges'] ) || ! is_array( $graph['edges'] ) ) {
		return ps_pen_paper_prepare_import_error();
	}
	$scene_map = array();
	if ( isset( $graph['scenes'] ) ) {
		if ( ! is_array( $graph['scenes'] ) ) { return ps_pen_paper_prepare_import_error(); }
		foreach ( $graph['scenes'] as $scene_id => &$scene ) {
			$scene_id = (int) $scene_id;
			$mapped = ps_pen_paper_prepare_import_scene( $scene_id, $scope_id, $records, $id_map );
			if ( is_wp_error( $mapped ) ) { return $mapped; }
			$scene_map[ $scene_id ] = $mapped;
			$check = ps_pen_paper_prepare_import_remap_scene_snapshot( $scene, $scope_id, $records, $id_map );
			if ( is_wp_error( $check ) ) { return $check; }
		}
		unset( $scene );
		$scenes = array();
		foreach ( $graph['scenes'] as $scene_id => $scene ) { $scenes[ $scene_map[ (int) $scene_id ] ] = $scene; }
		$graph['scenes'] = $scenes;
	}
	$start = ps_pen_paper_prepare_import_scene( (int) $graph['start_scene'], $scope_id, $records, $id_map );
	if ( is_wp_error( $start ) ) { return $start; }
	$graph['start_scene'] = $start;
	if ( isset( $graph['scenario'] ) ) {
		if ( $graph['scenario']['chapter_id'] ) {
			$mapped = ps_pen_paper_prepare_import_id( $graph['scenario']['chapter_id'], 'pspp_folio', $records, $id_map, $scope_id );
			if ( is_wp_error( $mapped ) ) { return $mapped; }
			$graph['scenario']['chapter_id'] = $mapped;
		}
		foreach ( $graph['scenario']['prerequisites'] as &$row ) {
			$mapped = ps_pen_paper_prepare_import_id( $row['adventure_id'], 'pspp_adventure', $records, $id_map, $scope_id );
			if ( is_wp_error( $mapped ) ) { return $mapped; }
			$row['adventure_id'] = $mapped;
		}
		unset( $row );
	}
	foreach ( $graph['encounters'] ?? array() as $index => $encounter ) {
		foreach ( $encounter['phases'] as $phase => $id ) {
			$mapped = ps_pen_paper_prepare_import_scene( $id, $scope_id, $records, $id_map );
			if ( is_wp_error( $mapped ) ) { return $mapped; }
			$graph['encounters'][ $index ]['phases'][ $phase ] = $mapped;
		}
	}
	foreach ( $graph['edges'] as &$edge ) {
		foreach ( array( 'from_scene', 'success_scene', 'failure_scene' ) as $key ) {
			if ( ! isset( $edge[ $key ] ) || ! is_int( $edge[ $key ] ) ) { return ps_pen_paper_prepare_import_error(); }
			if ( 0 === $edge[ $key ] ) { continue; }
			$mapped = ps_pen_paper_prepare_import_scene( $edge[ $key ], $scope_id, $records, $id_map );
			if ( is_wp_error( $mapped ) ) { return $mapped; }
			$edge[ $key ] = $mapped;
		}
	}
	unset( $edge );
	return true;
}

function ps_pen_paper_prepare_import_remap_item_provenance_currency( &$value, $records, $id_map ) {
	if ( is_array( $value ) && isset( $value['scope_id'], $value['document'] ) && is_array( $value['document'] ) &&
		is_int( $value['scope_id'] ) && $value['scope_id'] > 0 ) {
		if ( isset( $id_map[ $value['scope_id'] ] ) && isset( $records[ $value['scope_id'] ] ) &&
			in_array( $records[ $value['scope_id'] ]['type'], array( 'pspp_campaign', 'pspp_universe' ), true ) ) {
			$value['scope_id'] = $id_map[ $value['scope_id'] ];
		} else { return ps_pen_paper_prepare_import_error(); }
	}
	return true;
}

function ps_pen_paper_prepare_import_source_currency( $source_scope, $parsed, $id_map, $options ) {
	if ( ! isset( $parsed['records'][ $source_scope ] ) ||
		! in_array( $parsed['records'][ $source_scope ]['type'], array( 'pspp_campaign', 'pspp_universe' ), true ) ||
		! isset( $id_map[ $source_scope ] ) ) { return ps_pen_paper_prepare_import_error(); }
	if ( 'bind' === $options['universe_mode'] && $source_scope === $parsed['universe_id'] ) {
		$currency = ps_pen_paper_currency_document( $id_map[ $source_scope ] );
	} else {
		$currency = ps_pen_paper_import_meta( $parsed, $source_scope, '_pspp_currency' );
	}
	if ( is_wp_error( $currency ) || ! ps_pen_paper_valid_currency( $currency ) || $currency['revision'] < 1 ) {
		return ps_pen_paper_prepare_import_error();
	}
	return $currency;
}

function ps_pen_paper_prepare_import_remap_reward( &$reward, $scope_id, $records, $id_map, $options, &$archive, $path ) {
	if ( null === $reward ) { return true; }
	if ( ! is_array( $reward ) || ! isset( $reward['source'] ) || ! is_array( $reward['source'] ) ) { return ps_pen_paper_prepare_import_error(); }
	$source =& $reward['source'];
	if ( 'table' === ( $source['kind'] ?? null ) ) {
		$table = ps_pen_paper_prepare_import_loot_id( $source['id'], $scope_id, $records, $id_map );
		if ( is_wp_error( $table ) ) { return $table; }
		$source['id'] = $table;
		$mapped = ps_pen_paper_prepare_import_loot_snapshot( $source['table'], $scope_id, $records, $id_map, $options, $archive, $path . '.source.table' );
		if ( is_wp_error( $mapped ) ) { return $mapped; }
		$source['table'] = $mapped;
	} elseif ( 'fixed' === ( $source['kind'] ?? null ) ) {
		if ( is_array( $source['currency'] ) ) {
			$currency = ps_pen_paper_prepare_import_remap_item_provenance_currency( $source['currency'], $records, $id_map );
			if ( is_wp_error( $currency ) ) { return $currency; }
		}
		if ( null !== $source['item'] ) {
			$result = ps_pen_paper_prepare_import_remap_item_snapshot( $source['item'], $records, $id_map, $options, $archive, $path . '.source.item' );
			if ( is_wp_error( $result ) ) { return $result; }
		}
	}
	return true;
}

function ps_pen_paper_prepare_import_loot_id( $id, $scope, $records, $id_map ) {
	$owner = $records[ $id ]['parent_id'] ?? $scope;
	if ( $owner !== $scope && ( 'pspp_campaign' !== ( $records[ $scope ]['type'] ?? '' ) ||
		'pspp_universe' !== ( $records[ $owner ]['type'] ?? '' ) ) ) { return ps_pen_paper_prepare_import_error(); }
	return ps_pen_paper_prepare_import_id( $id, 'pspp_loot_table', $records, $id_map, $owner );
}

function ps_pen_paper_prepare_import_loot_snapshot( $state, $scope, $records, $id_map, $options, &$archive, $path ) {
	return ps_pen_paper_loot_snapshot_map( $state,
		function ( &$item ) use ( $records, $id_map, $options, &$archive, $path ) {
			return ps_pen_paper_prepare_import_remap_item_snapshot( $item, $records, $id_map, $options, $archive, $path . '.item' );
		},
		function ( &$currency ) use ( $records, $id_map ) {
			return ps_pen_paper_prepare_import_remap_item_provenance_currency( $currency, $records, $id_map );
		},
		function ( &$id ) use ( $scope, $records, $id_map ) {
			$mapped = ps_pen_paper_prepare_import_loot_id( $id, $scope, $records, $id_map );
			if ( is_wp_error( $mapped ) ) { return $mapped; }
			$id = $mapped; return true;
		}
	);
}

function ps_pen_paper_prepare_import_loot_node( &$node, $scope, $records, $id_map, $options, &$archive, $path ) {
	$id = ps_pen_paper_prepare_import_loot_id( $node['table_id'], $scope, $records, $id_map );
	if ( is_wp_error( $id ) ) { return $id; }
	$node['table_id'] = $id;
	$mapped = ps_pen_paper_prepare_import_loot_snapshot( $node['table'], $scope, $records, $id_map, $options, $archive, $path . '.table' );
	if ( is_wp_error( $mapped ) ) { return $mapped; }
	$node['table'] = $mapped;
	if ( ! empty( $node['nested'] ) ) {
		return ps_pen_paper_prepare_import_loot_node( $node['nested'], $scope, $records, $id_map, $options, $archive, $path . '.nested' );
	}
	return true;
}

function ps_pen_paper_prepare_import_validate_roll( $roll ) {
	if ( ! is_array( $roll ) || ! isset( $roll['version'], $roll['expression'], $roll['groups'], $roll['modifier'], $roll['total'],
		$roll['visibility'], $roll['recipient_id'], $roll['actor_name'], $roll['label'], $roll['rules_revision'] ) ||
		! array_key_exists( 'character', $roll ) || ! array_key_exists( 'attribute', $roll ) ||
		1 !== $roll['version'] || ! is_string( $roll['expression'] ) || ! is_array( $roll['groups'] ) ||
		! is_int( $roll['modifier'] ) || ! is_int( $roll['total'] ) ||
		! array_key_exists( $roll['visibility'], ps_pen_paper_roll_visibility_labels() ) ||
		! is_int( $roll['recipient_id'] ) || $roll['recipient_id'] < 0 ||
		! is_string( $roll['actor_name'] ) || ! is_string( $roll['label'] ) ||
		! ( null === $roll['character'] || is_array( $roll['character'] ) ) ||
		! ( null === $roll['attribute'] || is_array( $roll['attribute'] ) ) ||
		! is_int( $roll['rules_revision'] ) || $roll['rules_revision'] < 0 ) { return false; }
	$parsed = ps_pen_paper_parse_dice( $roll['expression'], array( '4', '6', '8', '10', '12', '20', '100' ) );
	if ( is_wp_error( $parsed ) || $parsed['modifier'] !== $roll['modifier'] || count( $roll['groups'] ) !== count( $parsed['groups'] ) ) { return false; }
	foreach ( $parsed['groups'] as $index => $expected ) {
		$actual = $roll['groups'][ $index ];
		if ( ! is_array( $actual ) || array( 'sides', 'rolls' ) !== array_keys( $actual ) ||
			$expected['sides'] !== $actual['sides'] || ! is_array( $actual['rolls'] ) ||
			array_values( $actual['rolls'] ) !== $actual['rolls'] || count( $actual['rolls'] ) !== $expected['count'] ) { return false; }
		foreach ( $actual['rolls'] as $value ) {
			if ( ! is_int( $value ) || $value < 1 || $value > $expected['sides'] ) { return false; }
		}
	}
	if ( array_key_exists( 'mechanic', $roll ) !== array_key_exists( 'threshold', $roll ) ) { return false; }
	$mechanic = $roll['mechanic'] ?? 'sum';
	if ( array_key_exists( 'mechanic', $roll ) && ! is_string( $roll['mechanic'] ) ) { return false; }
	$total = ps_pen_paper_dice_total( $roll['groups'], $roll['modifier'], $mechanic, $roll['threshold'] ?? null );
	if ( is_wp_error( $total ) || ( 'pool' === $mechanic && null !== $roll['attribute'] ) ) { return false; }
	if ( null !== $roll['attribute'] ) {
		if ( ! is_array( $roll['attribute'] ) || ! isset( $roll['attribute']['id'], $roll['attribute']['label'], $roll['attribute']['value'] ) ||
			! is_string( $roll['attribute']['id'] ) || ! wp_is_uuid( $roll['attribute']['id'] ) ||
			! is_string( $roll['attribute']['label'] ) || ! is_int( $roll['attribute']['value'] ) ) { return false; }
		$total += $roll['attribute']['value'];
	}
	if ( null !== $roll['character'] ) {
		$character = $roll['character'];
		if ( ! isset( $character['id'], $character['name'], $character['owner_id'], $character['revision'] ) ||
			! is_int( $character['id'] ) || $character['id'] < 1 || ! is_string( $character['name'] ) ||
			! is_int( $character['owner_id'] ) || $character['owner_id'] < 1 || ! is_int( $character['revision'] ) || $character['revision'] < 1 ) { return false; }
		if ( isset( $character['context'] ) ) {
			$context = $character['context'];
			if ( ! is_array( $context ) || array( 'mode', 'source_id', 'source_revision', 'revision' ) !== array_keys( $context ) ||
				! in_array( $context['mode'], array( 'copy', 'shared' ), true ) || ! is_int( $context['source_id'] ) || $context['source_id'] < 1 ||
				! is_int( $context['source_revision'] ) || $context['source_revision'] < 1 ||
				! is_int( $context['revision'] ) || $context['revision'] < 1 ) { return false; }
		}
		if ( isset( $character['equipment'] ) ) {
			$equipment = $character['equipment'];
			if ( ! is_array( $equipment ) || ! isset( $equipment['revision'], $equipment['schema_revision'], $equipment['bonuses'] ) ||
				array( 'revision', 'schema_revision', 'bonuses' ) !== array_keys( $equipment ) ||
				! is_int( $equipment['revision'] ) || $equipment['revision'] < 0 ||
				! is_int( $equipment['schema_revision'] ) || $equipment['schema_revision'] < 0 ||
				! is_array( $equipment['bonuses'] ) || count( $equipment['bonuses'] ) > 30 ) { return false; }
			foreach ( $equipment['bonuses'] as $id => $bonus ) {
				if ( ! is_string( $id ) || ! is_int( $bonus ) || abs( $bonus ) > 30000000 ) { return false; }
			}
		}
	}
	return $total === $roll['total'];
}

function ps_pen_paper_prepare_import_event_snapshot_valid( $kind, $snapshot, $event, $record, $parsed, $side = null ) {
	if ( ! is_array( $snapshot ) ) { return false; }
	$document_key = array(
		'rules_saved' => '_pspp_rules', 'access_saved' => '_pspp_access', 'universe_saved' => '_pspp_universe',
		'universe_linked' => '_pspp_universe_link', 'turn_changed' => '_pspp_turn',
		'character_created' => '_pspp_character', 'character_saved' => '_pspp_character',
		'character_requested' => '_pspp_character', 'character_approved' => '_pspp_character',
		'character_rejected' => '_pspp_character', 'character_context' => '_pspp_character',
		'character_resource_spent' => '_pspp_character',
		'scene_saved' => '_pspp_scene', 'scene_published' => '_pspp_scene', 'scene_withdrawn' => '_pspp_scene',
		'map_moved' => '_pspp_scene', 'scene_selected' => '_pspp_scene_selection',
		'folio_saved' => '_pspp_folio', 'folio_published' => '_pspp_folio', 'folio_withdrawn' => '_pspp_folio',
		'npc_saved' => '_pspp_npc', 'npc_offered' => '_pspp_npc', 'npc_approved' => '_pspp_npc', 'npc_copied' => '_pspp_npc',
		'loot_table_saved' => '_pspp_loot_table', 'loot_rolled' => '_pspp_loot_run', 'loot_awarded' => '_pspp_loot_run',
		'trade_policy' => '_pspp_trade_policy', 'trade_changed' => '_pspp_trade',
		'adventure_saved' => '_pspp_adventure', 'adventure_published' => '_pspp_adventure', 'adventure_run' => '_pspp_adventure_run',
		'adventure_group' => '_pspp_adventure_group',
		'invitation_saved' => '_pspp_invitations', 'wiki_links_saved' => '_pspp_wiki_links',
		'wiki_configured' => '_pspp_wiki', 'wiki_prepared' => '_pspp_wiki_entry', 'wiki_published' => '_pspp_wiki_entry',
		'currency_saved' => '_pspp_currency', 'wallet_saved' => '_pspp_wallet',
		'portrait_saved' => '_pspp_portrait', 'sheet_style_saved' => '_pspp_sheet_style',
		'equipment_schema' => '_pspp_equipment_schema', 'item_saved' => '_pspp_item', 'gear_saved' => '_pspp_gear',
		'character_policy_saved' => '_pspp_character_policy', 'member_invitation_saved' => '_pspp_member_invitations', 'universe_members_saved' => '_pspp_universe_members',
		'game_round' => '_pspp_game_round', 'item_action_policy' => '_pspp_item_action_policy',
		'play_options' => '_pspp_play_options', 'play_round' => '_pspp_play_round',
	);
	if ( ! isset( $document_key[ $kind ] ) ) { return false; }
	$key = $document_key[ $kind ];
	if ( '_pspp_character_policy' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		$defaults = ps_pen_paper_character_policy_defaults();
		$legacy_defaults = $defaults; $legacy_defaults['creation_access'] = 'members';
		return $snapshot === $defaults || $snapshot === $legacy_defaults;
	}
	if ( '_pspp_rules' === $key && isset( $snapshot['revision'] ) && 0 === $snapshot['revision'] ) {
		return array( 'revision', 'name', 'description', 'dice', 'fields' ) === array_keys( $snapshot ) &&
			'' === $snapshot['name'] && '' === $snapshot['description'] &&
			is_array( $snapshot['dice'] ) && ! $snapshot['dice'] && is_array( $snapshot['fields'] ) && ! $snapshot['fields'];
	}
	if ( '_pspp_access' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		return array( 'revision', 'visibility', 'admission', 'teaser' ) === array_keys( $snapshot ) &&
			'private' === $snapshot['visibility'] && 'closed' === $snapshot['admission'] && '' === $snapshot['teaser'];
	}
	if ( '_pspp_universe' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		return array( 'revision', 'name', 'description' ) === array_keys( $snapshot ) &&
			'' === $snapshot['name'] && '' === $snapshot['description'];
	}
	if ( '_pspp_universe_link' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		return array( 'revision', 'universe_id', 'name' ) === array_keys( $snapshot ) &&
			0 === $snapshot['universe_id'] && '' === $snapshot['name'];
	}
	if ( '_pspp_scene_selection' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		return array( 'revision', 'scene_id', 'name' ) === array_keys( $snapshot ) &&
			0 === $snapshot['scene_id'] && '' === $snapshot['name'];
	}
	if ( '_pspp_sheet_style' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		return array( 'revision', 'asset_id' ) === array_keys( $snapshot ) && 0 === $snapshot['asset_id'];
	}
	if ( '_pspp_scene' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) { return $snapshot === ps_pen_paper_scene_default(); }
	if ( '_pspp_folio' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) { return $snapshot === ps_pen_paper_folio_default(); }
	if ( '_pspp_npc' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		$scope = $parsed['records'][ $record['parent_id'] ] ?? null;
		$status = $scope && 'pspp_universe' === $scope['type'] ? 'template' : 'figure';
		return array( 'revision', 'status', 'source', 'npc' ) === array_keys( $snapshot ) &&
			$status === $snapshot['status'] && null === $snapshot['source'] && null === $snapshot['npc'];
	}
	if ( '_pspp_loot_table' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		return array( 'revision', 'table' ) === array_keys( $snapshot ) && null === $snapshot['table'];
	}
	if ( '_pspp_loot_run' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		return array( 'revision', 'proposal', 'award' ) === array_keys( $snapshot ) &&
			null === $snapshot['proposal'] && null === $snapshot['award'];
	}
	if ( '_pspp_equipment_schema' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		return array( 'revision', 'slots', 'rarities' ) === array_keys( $snapshot ) &&
			is_array( $snapshot['slots'] ) && ! $snapshot['slots'] &&
			is_array( $snapshot['rarities'] ) && ! $snapshot['rarities'];
	}
	if ( '_pspp_item' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		return array( 'revision', 'item' ) === array_keys( $snapshot ) && null === $snapshot['item'];
	}
	if ( '_pspp_gear' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		return array( 'revision', 'active', 'pending' ) === array_keys( $snapshot ) &&
			array( 'items' => array(), 'slots' => array() ) === $snapshot['active'] && null === $snapshot['pending'];
	}
	if ( '_pspp_portrait' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		return array( 'revision', 'active', 'pending' ) === array_keys( $snapshot ) &&
			array( 'asset_id' => 0 ) === $snapshot['active'] && null === $snapshot['pending'];
	}
	if ( '_pspp_trade' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		return array( 'revision', 'offer' ) === array_keys( $snapshot ) && null === $snapshot['offer'];
	}
	if ( '_pspp_wiki' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		return function_exists( 'ps_pen_paper_wiki_default' ) && $snapshot === ps_pen_paper_wiki_default();
	}
	if ( '_pspp_character' === $key && 'character_created' === $kind && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		return array( 'revision', 'active', 'pending' ) === array_keys( $snapshot ) &&
			null === $snapshot['active'] && null === $snapshot['pending'];
	}
	if ( '_pspp_character' === $key ) {
		if ( 'character_saved' === $kind && isset( $event['migration_rules_before'], $event['migration_rules_after'] ) ) {
			if ( ! in_array( $side, array( 'before', 'after' ), true ) ) { return false; }
			$rules = $event[ 'migration_rules_' . $side ];
			return ps_pen_paper_valid_rules_document( $rules ) && ps_pen_paper_import_character_valid( $snapshot, $rules );
		}
		$rules = ps_pen_paper_import_meta( $parsed, $record['parent_id'], '_pspp_rules' );
		if ( ! is_array( $rules ) && ! empty( $parsed['universe_id'] ) ) {
			$rules = ps_pen_paper_import_meta( $parsed, $parsed['universe_id'], '_pspp_rules' );
		}
		if ( ! is_array( $rules ) || ! is_array( $event['fields'] ) ) { return false; }
		$rules['revision'] = max( 1, $event['rules_revision'] );
		$rules['fields'] = $event['fields'];
		if ( ! ps_pen_paper_import_rules_valid( $rules ) || ! ps_pen_paper_import_character_valid( $snapshot, $rules ) ) { return false; }
		return true;
	}
	if ( '_pspp_wiki_entry' === $key && 0 === ( $snapshot['revision'] ?? -1 ) ) {
		return function_exists( 'ps_pen_paper_wiki_entry_default' ) && $snapshot === ps_pen_paper_wiki_entry_default();
	}
	$validator = array(
		'_pspp_scene' => 'ps_pen_paper_validate_scene_state', '_pspp_folio' => 'ps_pen_paper_validate_folio_state',
		'_pspp_npc' => 'ps_pen_paper_valid_npc_state', '_pspp_loot_table' => 'ps_pen_paper_valid_loot_table',
		'_pspp_loot_run' => 'ps_pen_paper_valid_loot_run', '_pspp_trade' => 'ps_pen_paper_valid_trade',
		'_pspp_adventure' => 'ps_pen_paper_valid_adventure_state', '_pspp_adventure_run' => 'ps_pen_paper_valid_adventure_run',
		'_pspp_adventure_group' => 'ps_pen_paper_valid_adventure_group',
		'_pspp_wiki_entry' => 'ps_pen_paper_valid_wiki_entry', '_pspp_wiki' => 'ps_pen_paper_valid_wiki_config',
		'_pspp_currency' => 'ps_pen_paper_valid_currency', '_pspp_wallet' => 'ps_pen_paper_valid_wallet',
		'_pspp_gear' => 'ps_pen_paper_validate_gear_state',
		'_pspp_invitations' => 'ps_pen_paper_valid_invitations', '_pspp_wiki_links' => 'ps_pen_paper_valid_wiki_links',
	);
	if ( isset( $validator[ $key ] ) ) {
		$valid = call_user_func( $validator[ $key ], $snapshot );
		return true === $valid || is_array( $valid );
	}
	$valid = ps_pen_paper_prepare_import_validate_known( $key, $snapshot, $record, $parsed );
	return true === $valid || is_array( $valid );
}

function ps_pen_paper_prepare_import_validate_event( $event, $record, $parsed ) {
	if ( ! is_array( $event ) || ! isset( $event['version'], $event['kind'], $event['visibility'], $event['recipient_id'],
		$event['actor_name'], $event['object_id'], $event['before'], $event['after'], $event['fields'], $event['rules_revision'] ) ||
		1 !== $event['version'] || ! is_string( $event['kind'] ) || ! isset( ps_pen_paper_event_labels()[ $event['kind'] ] ) ||
		! in_array( $event['visibility'], array( 'group', 'owner', 'gm' ), true ) ||
		! is_int( $event['recipient_id'] ) || $event['recipient_id'] < 0 ||
		! is_string( $event['actor_name'] ) || ! is_int( $event['object_id'] ) || $event['object_id'] < 1 ||
		! is_array( $event['before'] ) || ! is_array( $event['after'] ) || ! is_array( $event['fields'] ) ||
		! is_int( $event['rules_revision'] ) || $event['rules_revision'] < 0 ) { return false; }
	$kind = $event['kind'];
	if ( array_key_exists( 'migration_rules_before', $event ) || array_key_exists( 'migration_rules_after', $event ) ) {
		if ( 'character_saved' !== $kind ||
			! isset( $event['migration_rules_before'], $event['migration_rules_after'] ) ||
			! ps_pen_paper_valid_rules_document( $event['migration_rules_before'] ) ||
			! ps_pen_paper_valid_rules_document( $event['migration_rules_after'] ) ||
			$event['migration_rules_after']['revision'] !== $event['migration_rules_before']['revision'] + 1 ||
			$event['rules_revision'] !== $event['migration_rules_after']['revision'] ||
			$event['fields'] !== $event['migration_rules_after']['fields'] ) { return false; }
	}
	if ( 'campaign_restored' === $kind ) {
		return 'gm' === $event['visibility'] && 0 === $event['recipient_id'] &&
			array( 'revision', 'source_hash', 'backup_id' ) === array_keys( $event['after'] ) &&
			array( 'revision' ) === array_keys( $event['before'] ) &&
			0 === $event['before']['revision'] && 1 === $event['after']['revision'] &&
			is_string( $event['after']['source_hash'] ) && preg_match( '/^[a-f0-9]{64}$/D', $event['after']['source_hash'] ) &&
			is_int( $event['after']['backup_id'] ) && $event['after']['backup_id'] >= 0;
	}
	$checks = array(
		'trade_policy' => 'gm', 'trade_changed' => 'gm', 'adventure_saved' => 'gm', 'adventure_published' => 'gm',
		'adventure_run' => 'owner', 'adventure_group' => 'gm', 'wiki_configured' => 'gm', 'wiki_prepared' => 'gm', 'wiki_published' => 'gm',
		'loot_table_saved' => 'gm', 'loot_rolled' => 'gm', 'loot_awarded' => 'gm', 'npc_saved' => 'gm',
		'npc_offered' => 'gm', 'npc_approved' => 'gm', 'npc_copied' => 'gm', 'currency_saved' => 'gm',
		'wallet_saved' => 'owner', 'rules_saved' => null, 'turn_changed' => 'group', 'access_saved' => 'gm',
		'universe_saved' => 'gm', 'universe_linked' => 'gm',
		'character_created' => 'owner', 'character_saved' => 'owner', 'character_requested' => 'owner',
		'character_approved' => 'owner', 'character_rejected' => 'owner', 'character_context' => 'owner',
		'character_resource_spent' => 'owner', 'scene_saved' => 'gm', 'scene_published' => 'gm',
		'scene_withdrawn' => 'gm', 'scene_selected' => 'group', 'map_moved' => 'gm',
		'folio_saved' => 'gm', 'folio_published' => 'gm', 'folio_withdrawn' => 'gm',
		'portrait_saved' => 'owner', 'sheet_style_saved' => 'gm', 'equipment_schema' => 'gm',
		'item_saved' => 'gm', 'gear_saved' => 'owner',
		'invitation_saved' => 'gm', 'wiki_links_saved' => 'gm',
		'character_policy_saved' => 'gm', 'member_invitation_saved' => 'gm', 'universe_members_saved' => 'gm',
		'game_round' => 'group', 'item_action_policy' => 'gm',
		'play_options' => 'gm', 'play_round' => 'group',
	);
	if ( array_key_exists( $kind, $checks ) ) {
		$visibility = $checks[ $kind ];
		if ( ( null !== $visibility && $visibility !== $event['visibility'] ) ||
			! ps_pen_paper_prepare_import_event_snapshot_valid( $kind, $event['before'], $event, $record, $parsed, 'before' ) ||
			! ps_pen_paper_prepare_import_event_snapshot_valid( $kind, $event['after'], $event, $record, $parsed, 'after' ) ) { return false; }
		if ( 'rules_saved' === $kind ) {
			$scope = $parsed['records'][ $record['parent_id'] ] ?? null;
			$expected_visibility = $scope && 'pspp_universe' === $scope['type'] ? 'gm' : 'group';
			if ( $expected_visibility !== $event['visibility'] ||
				$event['after']['revision'] !== $event['rules_revision'] ||
				$event['after']['revision'] !== $event['before']['revision'] + 1 ) { return false; }
		}
	}
	if ( 'membership_saved' === $kind && ( 'gm' !== $event['visibility'] ||
		array( 'revision' ) !== array_keys( $event['before'] ) || array( 'revision' ) !== array_keys( $event['after'] ) ||
		! is_int( $event['before']['revision'] ) || ! is_int( $event['after']['revision'] ) ||
		$event['after']['revision'] !== $event['before']['revision'] + 1 ||
		! isset( $event['member_id'], $event['member_name'], $event['member_before'], $event['member_after'] ) ||
		! is_int( $event['member_id'] ) || $event['member_id'] < 1 || ! is_string( $event['member_name'] ) ||
		! ps_pen_paper_valid_membership_snapshot( $event['member_before'] ) || ! ps_pen_paper_valid_membership_snapshot( $event['member_after'] ) ) ) { return false; }
	if ( in_array( $kind, array( 'character_policy_saved', 'member_invitation_saved', 'universe_members_saved' ), true ) &&
		! ps_pen_paper_valid_policy_event( $event ) ) { return false; }
	if ( in_array( $kind, array( 'game_round', 'item_action_policy', 'play_options', 'play_round' ), true ) &&
		( 0 !== $event['recipient_id'] || $event['after']['revision'] !== $event['before']['revision'] + 1 ) ) { return false; }
	if ( in_array( $kind, array( 'turn_changed', 'access_saved', 'universe_saved', 'universe_linked', 'character_created',
		'character_saved', 'character_requested', 'character_approved', 'character_rejected', 'character_context',
		'scene_saved', 'scene_published', 'scene_withdrawn', 'map_moved', 'scene_selected', 'folio_saved',
		'folio_published', 'folio_withdrawn', 'npc_saved', 'npc_offered', 'npc_approved', 'npc_copied',
		'loot_table_saved', 'loot_rolled', 'loot_awarded', 'trade_policy', 'trade_changed', 'adventure_saved',
		'adventure_published', 'adventure_run', 'adventure_group', 'wiki_configured', 'wiki_prepared', 'wiki_published',
		'portrait_saved', 'sheet_style_saved', 'equipment_schema', 'item_saved', 'gear_saved', 'currency_saved', 'wallet_saved' ), true ) &&
		isset( $event['before']['revision'], $event['after']['revision'] ) &&
		$event['after']['revision'] !== $event['before']['revision'] + 1 ) { return false; }
	if ( 'character_resource_spent' === $kind ) {
		$cost = $event['resource_cost'] ?? null;
		if ( 'owner' !== $event['visibility'] || ! ps_pen_paper_valid_character_resource_cost( $cost ) ||
			! isset( $event['before']['revision'], $event['after']['revision'], $event['before']['active'], $event['after']['active'] ) ||
			$event['before']['revision'] !== $cost['character_revision'] ||
			$event['after']['revision'] !== $cost['character_revision'] + 1 ||
			! isset( $event['before']['active']['values'][ $cost['id'] ]['current'], $event['after']['active']['values'][ $cost['id'] ]['current'] ) ||
			$event['before']['active']['values'][ $cost['id'] ]['current'] !== $cost['before'] ||
			$event['after']['active']['values'][ $cost['id'] ]['current'] !== $cost['after'] ) { return false; }
		if ( isset( $cost['shared_base'] ) &&
			( $cost['shared_base']['character_id'] !== $event['object_id'] ||
				$cost['shared_base']['universe_id'] !== $record['parent_id'] ||
				! isset( $parsed['records'][ $event['object_id'] ], $parsed['records'][ $record['parent_id'] ] ) ||
				'pspp_character' !== $parsed['records'][ $event['object_id'] ]['type'] ||
				$record['parent_id'] !== $parsed['records'][ $event['object_id'] ]['parent_id'] ||
				'pspp_universe' !== $parsed['records'][ $record['parent_id'] ]['type'] ) ) { return false; }
	}
	if ( 'wallet_saved' === $kind && ( ! isset( $event['reason'], $event['character_name'] ) ||
		! is_string( $event['reason'] ) || '' === trim( $event['reason'] ) || strlen( $event['reason'] ) > 1000 ||
		! is_string( $event['character_name'] ) ) ) { return false; }
	if ( 'character_context' === $kind ) {
		$rules = ps_pen_paper_import_meta( $parsed, $record['parent_id'], '_pspp_rules' );
		if ( ! is_array( $rules ) && ! empty( $parsed['universe_id'] ) ) {
			$rules = ps_pen_paper_import_meta( $parsed, $parsed['universe_id'], '_pspp_rules' );
		}
		if ( ! is_array( $rules ) ) { return false; }
		$rules['revision'] = max( 1, $event['rules_revision'] );
		$rules['fields'] = $event['fields'];
		if ( ! ps_pen_paper_import_rules_valid( $rules ) ||
			! isset( $event['effective_before'], $event['effective_after'] ) ||
			is_wp_error( $before = ps_pen_paper_context_snapshot( $event['effective_before'], $rules ) ) ||
			is_wp_error( $after = ps_pen_paper_context_snapshot( $event['effective_after'], $rules ) ) ||
			$before !== $event['effective_before'] || $after !== $event['effective_after'] ) { return false; }
	}
	return true;
}

function ps_pen_paper_prepare_import_validate_known( $key, $value, $record, $parsed ) {
	$parent_id = $record['parent_id'];
	switch ( $key ) {
		case '_pspp_rules': return ps_pen_paper_import_rules_valid( $value );
		case '_pspp_universe':
			return is_array( $value ) && isset( $value['revision'], $value['name'], $value['description'] ) &&
				is_int( $value['revision'] ) && $value['revision'] > 0 && is_string( $value['name'] ) &&
				'' !== $value['name'] && is_string( $value['description'] ) &&
				( ! array_key_exists( 'introduction', $value ) || ( is_string( $value['introduction'] ) && strlen( $value['introduction'] ) <= 20000 ) );
		case '_pspp_character':
			$rules = ps_pen_paper_import_meta( $parsed, $parent_id, '_pspp_rules' );
			return ps_pen_paper_import_rules_valid( $rules ) && ps_pen_paper_import_character_valid( $value, $rules );
		case '_pspp_event': return ps_pen_paper_prepare_import_validate_event( $value, $record, $parsed );
		case '_pspp_roll': return ps_pen_paper_prepare_import_validate_roll( $value );
		case '_pspp_backdrop':
		case '_pspp_sheet_style':
			return is_array( $value ) && isset( $value['revision'], $value['asset_id'] ) &&
				is_int( $value['revision'] ) && $value['revision'] > 0 && is_int( $value['asset_id'] ) && $value['asset_id'] >= 0;
		case '_pspp_portrait':
			if ( ! is_array( $value ) || ! isset( $value['revision'], $value['active'] ) || ! array_key_exists( 'pending', $value ) ||
				! is_int( $value['revision'] ) || $value['revision'] < 1 ) { return false; }
			foreach ( array( $value['active'], $value['pending'] ) as $snapshot ) {
				if ( null !== $snapshot && ( ! is_array( $snapshot ) || ! isset( $snapshot['asset_id'] ) ||
					! is_int( $snapshot['asset_id'] ) || $snapshot['asset_id'] < 0 ) ) { return false; }
			}
			return true;
		case '_pspp_style':
			return is_array( $value ) && isset( $value['revision'], $value['colors'] ) &&
				is_int( $value['revision'] ) && $value['revision'] > 0 && ! is_wp_error( ps_pen_paper_validate_colors( $value['colors'] ) );
		case '_pspp_access':
			return is_array( $value ) && isset( $value['revision'], $value['visibility'], $value['admission'], $value['teaser'] ) &&
				is_int( $value['revision'] ) && $value['revision'] > 0 &&
				in_array( $value['visibility'], array( 'private', 'teaser' ), true ) &&
				in_array( $value['admission'], array( 'closed', 'approval' ), true ) &&
				is_string( $value['teaser'] ) && strlen( $value['teaser'] ) <= 6000;
		case '_pspp_membership': return is_array( $value ) && isset( $value['revision'] ) && is_int( $value['revision'] ) && $value['revision'] >= 1;
		case '_pspp_universe_link':
			return function_exists( 'ps_pen_paper_valid_universe_link' ) && ps_pen_paper_valid_universe_link( $value );
		case '_pspp_turn':
			if ( ! is_array( $value ) || ! isset( $value['revision'], $value['user_id'], $value['name'] ) ||
				! is_int( $value['revision'] ) || $value['revision'] < 0 || ! is_int( $value['user_id'] ) ||
				$value['user_id'] < 0 || ! is_string( $value['name'] ) ) { return false; }
			return true;
		case '_pspp_scene_selection':
			return is_array( $value ) && isset( $value['revision'], $value['scene_id'], $value['name'] ) &&
				is_int( $value['revision'] ) && $value['revision'] >= 1 && is_int( $value['scene_id'] ) &&
				$value['scene_id'] >= 0 && is_string( $value['name'] );
		case '_pspp_equipment_schema':
			$schema = ps_pen_paper_prepare_import_equipment_schema_valid( $value );
			return $schema;
		case '_pspp_currency': return ps_pen_paper_valid_currency( $value );
		case '_pspp_wallet': return ps_pen_paper_valid_wallet( $value );
		case '_pspp_transfer_receipts': return ps_pen_paper_valid_transfer_receipts( $value );
		case '_pspp_gear': return ! is_wp_error( ps_pen_paper_validate_gear_state( $value ) );
		case '_pspp_trade_policy': return ps_pen_paper_valid_trade_policy( $value );
		case '_pspp_scene': return ! is_wp_error( ps_pen_paper_validate_scene_state( $value ) );
		case '_pspp_folio': return ! is_wp_error( ps_pen_paper_validate_folio_state( $value ) );
		case '_pspp_npc': return ps_pen_paper_valid_npc_state( $value ) && $value['revision'] > 0;
		case '_pspp_item':
			return is_array( $value ) && isset( $value['revision'], $value['item'] ) &&
				is_int( $value['revision'] ) && $value['revision'] > 0 && ps_pen_paper_validate_item( $value['item'] );
		case '_pspp_loot_table': return ps_pen_paper_valid_loot_table( $value ) && $value['revision'] > 0;
		case '_pspp_loot_run': return ps_pen_paper_valid_loot_run( $value ) && $value['revision'] > 0;
		case '_pspp_trade': return ps_pen_paper_valid_trade( $value ) && $value['revision'] > 0;
		case '_pspp_adventure': return ps_pen_paper_valid_adventure_state( $value ) && $value['revision'] > 0;
		case '_pspp_adventure_run': return ps_pen_paper_valid_adventure_run( $value ) && $value['revision'] > 0;
		case '_pspp_adventure_group': return ps_pen_paper_valid_adventure_group( $value ) && $value['revision'] > 0;
		case '_pspp_wiki':
			return ps_pen_paper_valid_wiki_config( $value );
		case '_pspp_invitations': return ps_pen_paper_valid_invitations( $value );
		case '_pspp_universe_members': return ps_pen_paper_valid_invitations( $value );
		case '_pspp_member_invitations': return ps_pen_paper_valid_member_invitations( $value );
		case '_pspp_character_policy': return ps_pen_paper_valid_character_policy( $value ) && $value['revision'] > 0;
		case '_pspp_game_round': return ps_pen_paper_validate_game_round( $value );
		case '_pspp_play_options': return ps_pen_paper_valid_play_options( $value );
		case '_pspp_play_round': return ps_pen_paper_valid_play_round( $value );
		case '_pspp_play_token': return is_string( $value ) && preg_match( '/^[a-f0-9]{64}$/D', $value );
		case '_pspp_play_origin':
			return is_array( $value ) && array_keys( $value ) === array( 'campaign_id', 'adventure_id' ) &&
				is_int( $value['campaign_id'] ) && $value['campaign_id'] > 0 && is_int( $value['adventure_id'] ) && $value['adventure_id'] > 0;
		case '_pspp_item_action_policy': return ps_pen_paper_validate_item_action_policy( $value );
		case '_pspp_wiki_links': return ps_pen_paper_valid_wiki_links( $value );
		case '_pspp_handout_asset': return '1' === $value;
		case '_pspp_item_origin': return ps_pen_paper_valid_item_origin( $value );
		case '_pspp_wiki_entry':
			return ps_pen_paper_valid_wiki_entry( $value ) && $value['revision'] > 0;
		case '_pspp_visibility': return in_array( $value, array( 'group', 'owner', 'gm', 'private' ), true );
		case '_pspp_recipient':
		case '_pspp_actor':
			return is_string( $value ) && preg_match( '/^(0|[1-9]\d*)$/D', $value ) && (string) (int) $value === $value;
		case '_pspp_gm':
		case '_pspp_player':
			return is_string( $value ) && preg_match( '/^(0|[1-9]\d*)$/D', $value ) && (string) (int) $value === $value;
		case '_pspp_archived': return in_array( $value, array( '0', '1' ), true );
		case '_pspp_import_epoch': return is_string( $value ) && wp_is_uuid( $value );
		case '_pspp_import_receipt':
			if ( is_string( $value ) ) {
				$json = json_decode( $value, true, 16 );
				if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $json ) ) { return false; }
				$value = $json;
			}
			if ( ! is_array( $value ) ) { return false; }
			$target_key = isset( $value['new_target_id'] ) ? 'new_target_id' : 'target_id';
			$has_extra_hash = isset( $value['request_hash'] ) || isset( $value['confirmation_hash'] );
			$has_target = isset( $value['new_target_id'] ) || isset( $value['target_id'] ) || isset( $value['confirmation_hash'] );
			$allowed = array( 'owner_id', 'blog_id', 'token_hash', 'request_hash', 'confirmation_hash', 'target_id', 'new_target_id', 'backup_id' );
			return ! array_diff( array_keys( $value ), $allowed ) && isset( $value['owner_id'], $value['blog_id'], $value['token_hash'], $value['backup_id'] ) &&
				$has_extra_hash && $has_target &&
				is_int( $value['owner_id'] ) && $value['owner_id'] > 0 &&
				is_int( $value['blog_id'] ) && $value['blog_id'] > 0 &&
				is_string( $value['token_hash'] ) && preg_match( '/^[a-f0-9]{64}$/D', $value['token_hash'] ) &&
				( ! isset( $value['request_hash'] ) || ( is_string( $value['request_hash'] ) && preg_match( '/^[a-f0-9]{64}$/D', $value['request_hash'] ) ) ) &&
				( ! isset( $value['confirmation_hash'] ) || ( is_string( $value['confirmation_hash'] ) && preg_match( '/^[a-f0-9]{64}$/D', $value['confirmation_hash'] ) ) ) &&
				( ! isset( $value['target_id'] ) || ( is_int( $value['target_id'] ) && $value['target_id'] > 0 ) ) &&
				( ! isset( $value['new_target_id'] ) || ( is_int( $value['new_target_id'] ) && $value['new_target_id'] > 0 ) ) &&
				is_int( $value['backup_id'] ) && $value['backup_id'] >= 0;
		case '_pspp_import_backup': return is_string( $value ) || is_array( $value );
		case '_pspp_import_archive': return is_array( $value );
		case '_pspp_wiki_owner':
			return is_array( $value ) && 2 === count( $value ) && array_values( $value ) === $value &&
				is_int( $value[0] ) && $value[0] > 0 && is_int( $value[1] ) && $value[1] > 0;
		default:
			if ( preg_match( '/^_pspp_join_([1-9]\d*)$/D', $key, $match ) ) {
				return array( 'pending' ) === array( $value );
			}
			return null;
	}
}

function ps_pen_paper_prepare_import_equipment_schema_valid( $value ) {
	return ! is_wp_error( ps_pen_paper_validate_equipment_schema( $value ) );
}

function ps_pen_paper_prepare_import_required_meta() {
	return array(
		'pspp_universe' => '_pspp_universe',
		'pspp_character' => '_pspp_character', 'pspp_roll' => '_pspp_roll', 'pspp_event' => '_pspp_event',
		'pspp_scene' => '_pspp_scene', 'pspp_folio' => '_pspp_folio', 'pspp_npc' => '_pspp_npc',
		'pspp_wiki_entry' => '_pspp_wiki_entry', 'pspp_item' => '_pspp_item',
		'pspp_loot_table' => '_pspp_loot_table', 'pspp_loot_run' => '_pspp_loot_run',
		'pspp_trade' => '_pspp_trade', 'pspp_adventure' => '_pspp_adventure',
		'pspp_adventure_run' => '_pspp_adventure_run',
		'pspp_adventure_group' => '_pspp_adventure_group',
	);
}

function ps_pen_paper_prepare_import_required_record_meta( $record, $meta ) {
	if ( isset( ps_pen_paper_prepare_import_required_meta()[ $record['type'] ] ) &&
		! isset( $meta[ ps_pen_paper_prepare_import_required_meta()[ $record['type'] ] ] ) ) { return false; }
	if ( in_array( $record['type'], array( 'pspp_event', 'pspp_roll' ), true ) ) {
		foreach ( array( '_pspp_visibility', '_pspp_recipient', '_pspp_actor' ) as $key ) {
			if ( ! isset( $meta[ $key ][0] ) ) { return false; }
		}
		$document_key = 'pspp_roll' === $record['type'] ? '_pspp_roll' : '_pspp_event';
		$document = $meta[ $document_key ][0] ?? null;
		if ( ! is_array( $document ) || ! isset( $document['visibility'], $document['recipient_id'] ) ||
			! is_string( $meta['_pspp_visibility'][0] ) || ! is_string( $meta['_pspp_recipient'][0] ) ) { return false; }
		if ( $meta['_pspp_visibility'][0] !== $document['visibility'] ||
			$meta['_pspp_recipient'][0] !== (string) $document['recipient_id'] ) { return false; }
	}
	return true;
}

function ps_pen_paper_prepare_import_adventure_items( &$edge, $records, $id_map, $options, &$archive, $path ) {
	foreach ( $edge['items']['rows'] ?? array() as $index => $row ) {
		$result = ps_pen_paper_prepare_import_remap_item_snapshot( $edge['items']['rows'][ $index ]['item'], $records, $id_map, $options, $archive,
			$path . '.items.rows.' . $index );
		if ( is_wp_error( $result ) ) { return $result; }
	}
	return true;
}

function ps_pen_paper_prepare_import_adventure_snapshot( &$snapshot, $scope, $records, $id_map, $options, &$archive, $path ) {
	$result = ps_pen_paper_prepare_import_remap_adventure_graph( $snapshot, $scope, $records, $id_map );
	if ( is_wp_error( $result ) ) { return $result; }
	foreach ( $snapshot['edges'] as &$edge ) {
		$result = ps_pen_paper_prepare_import_adventure_items( $edge, $records, $id_map, $options, $archive, $path . '.edges.' . $edge['id'] );
		if ( is_wp_error( $result ) ) { return $result; }
		foreach ( array( 'reward', 'repeat_reward' ) as $reward_key ) {
			if ( ! isset( $edge[ $reward_key ] ) ) { continue; }
			$user = ps_pen_paper_prepare_import_mapped_user( $edge[ $reward_key ]['authorizer_id'], $options );
			if ( is_wp_error( $user ) ) { return $user; }
			$edge[ $reward_key ]['authorizer_id'] = $user;
			$result = ps_pen_paper_prepare_import_remap_reward( $edge[ $reward_key ], $scope, $records, $id_map, $options, $archive,
				$path . '.edges.' . $edge['id'] . '.' . $reward_key );
			if ( is_wp_error( $result ) ) { return $result; }
		}
	}
	unset( $edge );
	return true;
}

function ps_pen_paper_prepare_import_adventure_history( &$history, $owner, $scope, $parsed, $options, $id_map ) {
	$records = $parsed['records'];
	foreach ( $history as &$entry ) {
		foreach ( $entry['items']['rows'] ?? array() as $index => $row ) {
			if ( ! isset( $id_map[ $row['scope_id'] ], $records[ $row['scope_id'] ] ) ||
				! in_array( $records[ $row['scope_id'] ]['type'], array( 'pspp_campaign', 'pspp_universe' ), true ) ) { return ps_pen_paper_prepare_import_error(); }
			$entry['items']['rows'][ $index ]['scope_id'] = $id_map[ $row['scope_id'] ];
		}
		if ( isset( $entry['review'] ) ) {
			$reviewer = ps_pen_paper_prepare_import_mapped_user( $entry['review']['reviewer_id'], $options );
			if ( is_wp_error( $reviewer ) ) { return $reviewer; }
			$entry['review']['requester_id'] = $owner; $entry['review']['reviewer_id'] = $reviewer;
		}
		foreach ( array( 'source_scene', 'target_scene' ) as $field ) {
			if ( 0 === $entry[ $field ] ) { continue; }
			$scene = ps_pen_paper_prepare_import_scene( $entry[ $field ], $scope, $records, $id_map );
			if ( is_wp_error( $scene ) ) { return $scene; }
			$entry[ $field ] = $scene;
		}
		if ( isset( $entry['reward']['wallet']['character_id'] ) ) {
			$source = $entry['reward']['wallet']['character_id']; $character = $records[ $source ] ?? null;
			if ( ! $character || 'pspp_character' !== $character['type'] ||
				! in_array( $character['parent_id'], array( $scope, $parsed['universe_id'] ), true ) ) { return ps_pen_paper_prepare_import_error(); }
			$mapped = ps_pen_paper_prepare_import_id( $source, 'pspp_character', $records, $id_map );
			if ( is_wp_error( $mapped ) ) { return $mapped; }
			$entry['reward']['wallet']['character_id'] = $mapped;
			foreach ( array( 'before', 'after' ) as $slot ) {
				$source_scope = $entry['reward']['wallet'][ $slot ]['currency_scope_id'];
				if ( ! isset( $records[ $source_scope ] ) || ! in_array( $records[ $source_scope ]['type'], array( 'pspp_campaign', 'pspp_universe' ), true ) ) { return ps_pen_paper_prepare_import_error(); }
				$mapped_scope = ps_pen_paper_prepare_import_id( $source_scope, null, $records, $id_map );
				if ( is_wp_error( $mapped_scope ) ) { return $mapped_scope; }
				$entry['reward']['wallet'][ $slot ]['currency_scope_id'] = $mapped_scope;
			}
		}
	}
	unset( $entry );
	return true;
}

function ps_pen_paper_prepare_import_remap_value( $key, &$value, $record, $parsed, $options, $id_map, &$archive ) {
	$records = $parsed['records']; $scope_id = ps_pen_paper_prepare_import_scope( $record, $records );
	if ( '_pspp_gm' === $key || '_pspp_player' === $key ) {
		$user = ps_pen_paper_prepare_import_mapped_user( (int) $value, $options );
		if ( is_wp_error( $user ) ) { return $user; }
		$value = (string) $user;
		return true;
	}
	if ( '_pspp_actor' === $key || '_pspp_recipient' === $key ) {
		$user = ps_pen_paper_prepare_import_historical_header_user( (int) $value, $options, $archive, $key );
		if ( is_wp_error( $user ) ) { return $user; }
		$value = (string) $user;
		return true;
	}
	if ( preg_match( '/^_pspp_join_([1-9]\d*)$/D', $key, $match ) ) {
		$user = ps_pen_paper_prepare_import_mapped_user( (int) $match[1], $options );
		if ( is_wp_error( $user ) ) { return $user; }
		$value = $value;
		return array( 'key' => '_pspp_join_' . $user, 'value' => $value );
	}
	switch ( $key ) {
		case '_pspp_universe_link':
			$source_universe = $value['universe_id'];
			if ( 0 === $source_universe ) { return true; }
			$mapped = ps_pen_paper_prepare_import_id( $source_universe, 'pspp_universe', $records, $id_map );
			if ( is_wp_error( $mapped ) ) { return $mapped; }
			$value['universe_id'] = $mapped;
			if ( 'bind' === $options['universe_mode'] && function_exists( 'ps_pen_paper_universe_document' ) ) {
				$document = ps_pen_paper_universe_document( $mapped );
				if ( ! is_wp_error( $document ) ) { $value['name'] = $document['name']; }
			}
			return true;
		case '_pspp_turn':
			$user = ps_pen_paper_prepare_import_mapped_user( $value['user_id'], $options );
			if ( is_wp_error( $user ) ) { return $user; }
			$value['user_id'] = $user;
			return true;
		case '_pspp_scene_selection':
			$scene = ps_pen_paper_prepare_import_scene( $value['scene_id'], $record['id'], $records, $id_map );
			if ( is_wp_error( $scene ) ) { return $scene; }
			$value['scene_id'] = $scene;
			return true;
		case '_pspp_backdrop':
		case '_pspp_sheet_style':
			$asset = ps_pen_paper_prepare_import_asset( $value['asset_id'], $record['id'], $records, $id_map );
			if ( is_wp_error( $asset ) ) { return $asset; }
			$value['asset_id'] = $asset;
			return true;
		case '_pspp_portrait':
			foreach ( array( 'active', 'pending' ) as $slot ) {
				if ( null === $value[ $slot ] ) { continue; }
				$asset = ps_pen_paper_prepare_import_asset_value( $value[ $slot ]['asset_id'], $scope_id, $records,
					$id_map, $options, $archive, $key . '.' . $slot . '.asset_id' );
				if ( is_wp_error( $asset ) ) { return $asset; }
			}
			return true;
		case '_pspp_character':
			if ( isset( $value['context'] ) ) {
				$context =& $value['context'];
				if ( 'shared' === $context['mode'] ) {
					$universe = ps_pen_paper_prepare_import_id( $context['universe_id'], 'pspp_universe', $records, $id_map );
					if ( is_wp_error( $universe ) ) { return $universe; }
					$context['universe_id'] = $universe;
					if ( 'bind' === $options['universe_mode'] &&
						( ! isset( $options['bases'][ $context['source_id'] ] ) ||
							$options['bases'][ $context['source_id'] ] !== $id_map[ $context['source_id'] ] ) ) {
						return ps_pen_paper_prepare_import_error();
					}
					$base = ps_pen_paper_prepare_import_id( $context['source_id'], 'pspp_character', $records, $id_map,
						$parsed['universe_id'] );
					if ( is_wp_error( $base ) ) { return $base; }
					$context['source_id'] = $base;
				}
				// Copies retain immutable provenance, not a live universe or base lookup.
				unset( $context );
			}
			return true;
		case '_pspp_wallet':
			$source_currency_scope = $value['currency_scope_id'];
			$owner_scope = $records[ $record['parent_id'] ] ?? null;
			if ( ! $owner_scope ) { return ps_pen_paper_prepare_import_error(); }
			if ( 'pspp_campaign' === $owner_scope['type'] ) {
				$link = ps_pen_paper_import_meta( $parsed, $owner_scope['id'], '_pspp_universe_link' );
				if ( null !== $link && ( ! function_exists( 'ps_pen_paper_valid_universe_link' ) ||
					! ps_pen_paper_valid_universe_link( $link ) ) ) { return ps_pen_paper_prepare_import_error(); }
				$linked_universe = is_array( $link ) ? $link['universe_id'] : 0;
				$economy_mode = is_array( $link ) && isset( $link['economy_mode'] ) ? $link['economy_mode'] : 'universe';
				$expected_scope = $linked_universe && 'local' !== $economy_mode ? $linked_universe : $owner_scope['id'];
			} elseif ( 'pspp_universe' === $owner_scope['type'] ) {
				$expected_scope = $owner_scope['id'];
			} else { return ps_pen_paper_prepare_import_error(); }
			$scope = ps_pen_paper_prepare_import_id( $source_currency_scope, null, $records, $id_map );
			if ( $source_currency_scope !== $expected_scope || is_wp_error( $scope ) || ! isset( $records[ $source_currency_scope ] ) ||
				! in_array( $records[ $source_currency_scope ]['type'], array( 'pspp_campaign', 'pspp_universe' ), true ) ) {
				return ps_pen_paper_prepare_import_error();
			}
			$currency = ps_pen_paper_prepare_import_source_currency( $source_currency_scope, $parsed, $id_map, $options );
			if ( is_wp_error( $currency ) || $value['currency_id'] !== $currency['currency_id'] ) {
				return ps_pen_paper_prepare_import_error();
			}
			$value['currency_scope_id'] = $scope;
			return true;
		case '_pspp_currency':
			if ( 'pspp_campaign' === $record['type'] && 'bind' === $options['universe_mode'] ) { return true; }
			return true;
		case '_pspp_scene':
			foreach ( array( 'draft', 'published' ) as $slot ) {
				if ( null === $value[ $slot ] ) { continue; }
				$result = ps_pen_paper_prepare_import_asset_value( $value[ $slot ]['asset_id'], $record['parent_id'],
					$records, $id_map, $options, $archive, $key . '.' . $slot . '.asset_id' );
				if ( is_wp_error( $result ) ) { return $result; }
				if ( isset( $value[ $slot ]['tokens'] ) ) {
					foreach ( $value[ $slot ]['tokens'] as &$token ) {
						if ( ! $token['owner_id'] ) { continue; }
						$user = ps_pen_paper_prepare_import_mapped_user( $token['owner_id'], $options );
						if ( is_wp_error( $user ) ) { return $user; }
						$token['owner_id'] = $user;
					}
					unset( $token );
				}
			}
			return true;
		case '_pspp_folio':
			foreach ( $value['audience']['users'] ?? array() as $index => $id ) {
				$user = ps_pen_paper_prepare_import_mapped_user( $id, $options );
				if ( is_wp_error( $user ) ) { return $user; }
				$value['audience']['users'][ $index ] = $user;
			}
			foreach ( array( 'draft', 'published' ) as $slot ) {
				if ( null === $value[ $slot ] ) { continue; }
				$snapshot =& $value[ $slot ];
				$asset = ps_pen_paper_prepare_import_asset_value( $snapshot['asset_id'], $record['parent_id'],
					$records, $id_map, $options, $archive, $key . '.' . $slot . '.asset_id' );
				if ( is_wp_error( $asset ) ) { return $asset; }
				if ( isset( $snapshot['chapter_id'] ) && $snapshot['chapter_id'] ) {
					$chapter = ps_pen_paper_prepare_import_id( $snapshot['chapter_id'], 'pspp_folio', $records, $id_map, $record['parent_id'] );
					if ( is_wp_error( $chapter ) ) { return $chapter; }
					$snapshot['chapter_id'] = $chapter;
				}
				foreach ( array( 'scene_id' ) as $field ) {
					$scene = ps_pen_paper_prepare_import_scene( $snapshot[ $field ], $record['parent_id'], $records, $id_map );
					if ( is_wp_error( $scene ) ) { return $scene; }
					$snapshot[ $field ] = $scene;
				}
				foreach ( $snapshot['scene_ids'] as &$scene_id ) {
					$scene = ps_pen_paper_prepare_import_scene( $scene_id, $record['parent_id'], $records, $id_map );
					if ( is_wp_error( $scene ) ) { return $scene; }
					$scene_id = $scene;
				}
				unset( $scene_id, $snapshot );
			}
			return true;
		case '_pspp_character_policy':
			$scope_id = 'pspp_event' === $record['type'] ? $record['parent_id'] : $record['id'];
			foreach ( $value['presets'] as $index => &$preset ) {
				$asset = ps_pen_paper_prepare_import_asset_value( $preset['asset_id'], $scope_id,
					$records, $id_map, $options, $archive, $key . '.presets.' . $index . '.asset_id' );
				if ( is_wp_error( $asset ) ) { return $asset; }
			}
			unset( $preset );
			return ps_pen_paper_valid_character_policy( $value ) ? true : ps_pen_paper_prepare_import_error();
		case '_pspp_member_invitations':
			foreach ( $value['entries'] as $index => &$entry ) {
				foreach ( array( 'user_id', 'inviter_id' ) as $field ) {
					$user = 'pspp_event' === $record['type']
						? ps_pen_paper_prepare_import_historical_snapshot_user( $entry[ $field ], $options, $archive, $key . '.entries.' . $index . '.' . $field )
						: ps_pen_paper_prepare_import_mapped_user( $entry[ $field ], $options );
					if ( is_wp_error( $user ) ) { return $user; }
					$entry[ $field ] = $user;
				}
			}
			unset( $entry );
			return ps_pen_paper_valid_member_invitations( $value ) ? true : ps_pen_paper_prepare_import_error();
		case '_pspp_universe_members':
		case '_pspp_invitations':
			foreach ( $value['users'] as $index => $id ) {
				$user = 'pspp_event' === $record['type']
					? ps_pen_paper_prepare_import_historical_snapshot_user( $id, $options, $archive, $key . '.users.' . $index )
					: ps_pen_paper_prepare_import_mapped_user( $id, $options );
				if ( is_wp_error( $user ) ) { return $user; }
				$value['users'][ $index ] = $user;
			}
			return ps_pen_paper_valid_invitations( $value ) ? true : ps_pen_paper_prepare_import_error();
		case '_pspp_item_origin': return true;
		case '_pspp_handout_asset': return true;
		case '_pspp_npc':
			if ( null !== $value['source'] ) {
				// Template provenance is a frozen origin pointer, not an active link.
				$value['source']['scope_id'] = isset( $id_map[ $value['source']['scope_id'] ] ) ? $id_map[ $value['source']['scope_id'] ] : $value['source']['scope_id'];
			}
			if ( 'figure' === $value['status'] ) {
				$npc =& $value['npc'];
				$asset = ps_pen_paper_prepare_import_asset_value( $npc['asset_id'], $record['parent_id'],
					$records, $id_map, $options, $archive, $key . '.npc.asset_id' );
				if ( is_wp_error( $asset ) ) { return $asset; }
				$scene = ps_pen_paper_prepare_import_scene( $npc['scene_id'], $record['parent_id'], $records, $id_map );
				if ( is_wp_error( $scene ) ) { return $scene; }
				$npc['scene_id'] = $scene;
				if ( ! empty( $npc['loot_table_id'] ) ) {
					$table = ps_pen_paper_prepare_import_loot_id( $npc['loot_table_id'], $record['parent_id'], $records, $id_map );
					if ( is_wp_error( $table ) ) { return $table; }
					$npc['loot_table_id'] = $table;
				}
				unset( $npc );
			}
			return true;
		case '_pspp_item':
			if ( null === $value['item'] ) { return true; }
			$asset = ps_pen_paper_prepare_import_asset_value( $value['item']['asset_id'], $record['parent_id'],
				$records, $id_map, $options, $archive, $key . '.item.asset_id' );
			if ( is_wp_error( $asset ) ) { return $asset; }
			return true;
		case '_pspp_equipment_schema':
			$scope_id = 'pspp_event' === $record['type'] ? $record['parent_id'] : $record['id'];
			foreach ( $value['slots'] as $slot_id => &$slot ) {
				if ( empty( $slot['asset_id'] ) ) { continue; }
				$asset = ps_pen_paper_prepare_import_asset_value( $slot['asset_id'], $scope_id, $records, $id_map, $options, $archive, $key . '.slots.' . $slot_id . '.asset_id' );
				if ( is_wp_error( $asset ) ) { return $asset; }
			}
			unset( $slot );
			return true;
		case '_pspp_gear':
			foreach ( array( 'active', 'pending' ) as $slot ) {
				if ( null === $value[ $slot ] ) { continue; }
				foreach ( $value[ $slot ]['items'] as $uuid => &$instance ) {
					$result = ps_pen_paper_prepare_import_remap_item_snapshot( $instance, $records, $id_map, $options, $archive,
						$key . '.' . $slot . '.items.' . $uuid );
					if ( is_wp_error( $result ) ) { return $result; }
				}
				unset( $instance );
			}
			foreach ( array( 'pending', 'receipts' ) as $section ) {
				foreach ( $value['uses'][ $section ] ?? array() as $uuid => $entry ) {
					$user = 'pspp_event' === $record['type']
						? ps_pen_paper_prepare_import_historical_snapshot_user( $entry['actor_id'], $options, $archive, $key . '.uses.' . $section . '.' . $uuid . '.actor_id' )
						: ps_pen_paper_prepare_import_mapped_user( $entry['actor_id'], $options );
					if ( is_wp_error( $user ) ) { return $user; }
					$value['uses'][ $section ][ $uuid ]['actor_id'] = $user;
				}
			}
			return true;
		case '_pspp_loot_table':
			$mapped = ps_pen_paper_prepare_import_loot_snapshot( $value, $record['parent_id'], $records, $id_map, $options, $archive, $key );
			if ( is_wp_error( $mapped ) ) { return $mapped; }
			$value = $mapped;
			return true;
		case '_pspp_loot_run':
			$proposal =& $value['proposal'];
			$result = ps_pen_paper_prepare_import_loot_node( $proposal, $record['parent_id'], $records, $id_map, $options, $archive, $key . '.proposal' );
			if ( is_wp_error( $result ) ) { return $result; }
			$actor = ps_pen_paper_prepare_import_historical_snapshot_user( $proposal['actor_id'], $options, $archive,
				$key . '.proposal.actor_id' );
			if ( is_wp_error( $actor ) ) { return $actor; }
			$proposal['actor_id'] = $actor;
			if ( null !== $value['award'] ) {
				$award_actor = ps_pen_paper_prepare_import_historical_snapshot_user( $value['award']['actor_id'], $options, $archive,
					$key . '.award.actor_id' );
				if ( is_wp_error( $award_actor ) ) { return $award_actor; }
				$value['award']['actor_id'] = $award_actor;
			}
			if ( isset( $value['award']['allocations'] ) ) {
				foreach ( $value['award']['allocations'] as &$allocation ) {
					$character = ps_pen_paper_prepare_import_id( $allocation['character_id'], 'pspp_character', $records, $id_map, $record['parent_id'] );
					if ( is_wp_error( $character ) ) { return $character; }
					$allocation['character_id'] = $character;
				}
				unset( $allocation );
			} elseif ( null !== $value['award'] && $value['award']['character_id'] ) {
				$character = ps_pen_paper_prepare_import_id( $value['award']['character_id'], 'pspp_character', $records, $id_map, $record['parent_id'] );
				if ( is_wp_error( $character ) ) { return $character; }
				$value['award']['character_id'] = $character;
			}
			return true;
		case '_pspp_trade':
			$offer =& $value['offer'];
			foreach ( array( 'from', 'to' ) as $field ) {
				$character = ps_pen_paper_prepare_import_id( $offer[ $field ], 'pspp_character', $records, $id_map, $record['parent_id'] );
				if ( is_wp_error( $character ) ) { return $character; }
				$offer[ $field ] = $character;
			}
			foreach ( array( 'from_owner', 'to_owner' ) as $field ) {
				$user = ps_pen_paper_prepare_import_mapped_user( $offer[ $field ], $options );
				if ( is_wp_error( $user ) ) { return $user; }
				$offer[ $field ] = $user;
			}
			if ( isset( $offer['policy_scope'] ) ) {
				$policy_scope = $offer['policy_scope'];
				if ( ! isset( $records[ $policy_scope ] ) ||
					! in_array( $records[ $policy_scope ]['type'], array( 'pspp_campaign', 'pspp_universe' ), true ) ||
					! isset( $id_map[ $policy_scope ] ) ) { return ps_pen_paper_prepare_import_error(); }
				$offer['policy_scope'] = $id_map[ $policy_scope ];
			}
			foreach ( array( 'give', 'take' ) as $side ) {
				if ( null !== $offer[ $side ] ) {
					$result = ps_pen_paper_prepare_import_remap_item_snapshot( $offer[ $side ], $records, $id_map, $options, $archive, $key . '.offer.' . $side );
					if ( is_wp_error( $result ) ) { return $result; }
				}
				foreach ( $offer[ $side . '_more' ] ?? array() as $uuid => $instance ) {
					$result = ps_pen_paper_prepare_import_remap_item_snapshot( $instance, $records, $id_map, $options, $archive, $key . '.offer.' . $side . '_more.' . $uuid );
					if ( is_wp_error( $result ) ) { return $result; }
					$offer[ $side . '_more' ][ $uuid ] = $instance;
				}
			}
			if ( is_array( $offer['currency'] ) ) {
				$currency = array( 'scope_id' => $policy_scope, 'document' => $offer['currency'] );
				$result = ps_pen_paper_prepare_import_remap_item_provenance_currency( $currency, $records, $id_map );
				if ( is_wp_error( $result ) ) { return $result; }
				$offer['currency'] = $currency['document'];
			}
			foreach ( $value['history'] as $history_index => &$entry ) {
				$user = ps_pen_paper_prepare_import_historical_snapshot_user( $entry['actor_id'], $options, $archive,
					$key . '.history.' . $history_index . '.actor_id' );
				if ( is_wp_error( $user ) ) { return $user; }
				$entry['actor_id'] = $user;
			}
			unset( $entry, $offer );
			return true;
		case '_pspp_adventure':
			$state =& $value;
			$result = ps_pen_paper_prepare_import_adventure_snapshot( $state['draft'], $record['parent_id'], $records, $id_map, $options, $archive, $key . '.draft' );
			if ( is_wp_error( $result ) ) { return $result; }
			if ( null !== $state['published'] ) {
				$result = ps_pen_paper_prepare_import_adventure_snapshot( $state['published'], $record['parent_id'], $records, $id_map, $options, $archive, $key . '.published' );
				if ( is_wp_error( $result ) ) { return $result; }
			}
			unset( $state );
			return true;
		case '_pspp_adventure_run':
			$adventure = ps_pen_paper_prepare_import_id( $value['adventure_id'], 'pspp_adventure', $records, $id_map, $record['parent_id'] );
			$character = ps_pen_paper_prepare_import_id( $value['character_id'], 'pspp_character', $records, $id_map, $record['parent_id'] );
			$owner = ps_pen_paper_prepare_import_mapped_user( $value['owner_id'], $options );
			if ( is_wp_error( $adventure ) || is_wp_error( $character ) || is_wp_error( $owner ) ) { return ps_pen_paper_prepare_import_error(); }
			$value['adventure_id'] = $adventure; $value['character_id'] = $character; $value['owner_id'] = $owner;
			if ( isset( $value['approval'] ) ) {
				$value['approval']['requester_id'] = $owner;
				if ( $value['approval']['reviewer_id'] ) {
					$reviewer = ps_pen_paper_prepare_import_mapped_user( $value['approval']['reviewer_id'], $options );
					if ( is_wp_error( $reviewer ) ) { return $reviewer; }
					$value['approval']['reviewer_id'] = $reviewer;
				}
			}
			$scene = ps_pen_paper_prepare_import_adventure_snapshot( $value['snapshot'], $record['parent_id'], $records, $id_map, $options, $archive, $key . '.snapshot' );
			if ( is_wp_error( $scene ) ) { return $scene; }
			if ( $value['scene_id'] ) {
				$scene_id = ps_pen_paper_prepare_import_scene( $value['scene_id'], $record['parent_id'], $records, $id_map );
				if ( is_wp_error( $scene_id ) ) { return $scene_id; }
				$value['scene_id'] = $scene_id;
			}
			$result = ps_pen_paper_prepare_import_adventure_history( $value['history'], $owner, $record['parent_id'], $parsed, $options, $id_map );
			if ( is_wp_error( $result ) ) { return $result; }
			return ps_pen_paper_valid_adventure_run( $value ) ? true : ps_pen_paper_prepare_import_error();
		case '_pspp_adventure_group':
			if ( 0 === $value['revision'] ) { return $value === ps_pen_paper_adventure_group_default() ? true : ps_pen_paper_prepare_import_error(); }
			$adventure = ps_pen_paper_prepare_import_id( $value['adventure_id'], 'pspp_adventure', $records, $id_map, $record['parent_id'] );
			if ( is_wp_error( $adventure ) ) { return $adventure; }
			$value['adventure_id'] = $adventure;
			$result = ps_pen_paper_prepare_import_adventure_snapshot( $value['snapshot'], $record['parent_id'], $records, $id_map, $options, $archive, $key . '.snapshot' );
			if ( is_wp_error( $result ) ) { return $result; }
			if ( $value['scene_id'] ) {
				$scene = ps_pen_paper_prepare_import_scene( $value['scene_id'], $record['parent_id'], $records, $id_map );
				if ( is_wp_error( $scene ) ) { return $scene; }
				$value['scene_id'] = $scene;
			}
			$entries =& $value['participants'];
			foreach ( $entries as &$entry ) {
				$character = ps_pen_paper_prepare_import_id( $entry['character_id'], 'pspp_character', $records, $id_map, $record['parent_id'] );
				$owner = ps_pen_paper_prepare_import_mapped_user( $entry['owner_id'], $options );
				if ( is_wp_error( $character ) || is_wp_error( $owner ) ) { return ps_pen_paper_prepare_import_error(); }
				$entry['character_id'] = $character; $entry['owner_id'] = $owner;
			}
			unset( $entry, $entries );
			foreach ( array( 'proposal', 'history' ) as $slot ) {
				if ( null === $value[ $slot ] ) { continue; }
				$entries = 'proposal' === $slot ? array( $value[ $slot ] ) : $value[ $slot ];
				foreach ( $entries as &$entry ) {
					$character = ps_pen_paper_prepare_import_id( $entry['character_id'], 'pspp_character', $records, $id_map, $record['parent_id'] );
					$owner = ps_pen_paper_prepare_import_mapped_user( $entry['owner_id'], $options );
					if ( is_wp_error( $character ) || is_wp_error( $owner ) ) { return ps_pen_paper_prepare_import_error(); }
					$entry['character_id'] = $character; $entry['owner_id'] = $owner;
					if ( $entry['reviewer_id'] ) {
						$reviewer = ps_pen_paper_prepare_import_mapped_user( $entry['reviewer_id'], $options );
						if ( is_wp_error( $reviewer ) ) { return $reviewer; }
						$entry['reviewer_id'] = $reviewer;
					}
					foreach ( array( 'votes', 'electorate' ) as $list ) {
						if ( ! isset( $entry[ $list ] ) ) { continue; }
						foreach ( $entry[ $list ] as &$user ) {
							$mapped = ps_pen_paper_prepare_import_mapped_user( $user, $options );
							if ( is_wp_error( $mapped ) ) { return $mapped; }
							$user = $mapped;
						}
						unset( $user );
					}
					if ( isset( $entry['decision'] ) ) {
						$history = array( $entry['decision'] );
						$result = ps_pen_paper_prepare_import_adventure_history( $history, $owner, $record['parent_id'], $parsed, $options, $id_map );
						if ( is_wp_error( $result ) ) { return $result; }
						$entry['decision'] = $history[0];
					}
				}
				unset( $entry );
				$value[ $slot ] = 'proposal' === $slot ? $entries[0] : $entries;
			}
			return ps_pen_paper_valid_adventure_group( $value ) ? true : ps_pen_paper_prepare_import_error();
		case '_pspp_event':
			if ( 'play_round' === $value['kind'] ) {
				$archive['references'][] = array( 'kind' => 'historical_play_round', 'snapshots' => array( $value['before'], $value['after'] ) );
				$archive['warnings'][] = __( 'Historische Rundenverknüpfungen bleiben Herkunftsangaben und werden nicht als aktuelle Kampagnen oder Teilnehmer aufgelöst.', 'ps-pen-paper' );
			}
			$policy_documents = array( 'character_policy_saved' => '_pspp_character_policy',
				'member_invitation_saved' => '_pspp_member_invitations', 'universe_members_saved' => '_pspp_universe_members',
				'gear_saved' => '_pspp_gear', 'item_saved' => '_pspp_item', 'equipment_schema' => '_pspp_equipment_schema' );
			if ( isset( $policy_documents[ $value['kind'] ] ) ) {
				foreach ( array( 'before', 'after' ) as $side ) {
					$remapped = ps_pen_paper_prepare_import_remap_value( $policy_documents[ $value['kind'] ], $value[ $side ], $record, $parsed, $options, $id_map, $archive );
					if ( is_wp_error( $remapped ) ) { return $remapped; }
				}
			}
			$recipient = ps_pen_paper_prepare_import_historical_header_user( $value['recipient_id'], $options, $archive,
				$key . '.recipient_id' );
			if ( is_wp_error( $recipient ) ) { return $recipient; }
			$value['recipient_id'] = $recipient;
			if ( 'membership_saved' === $value['kind'] ) {
				$member = ps_pen_paper_prepare_import_historical_snapshot_user( $value['member_id'], $options, $archive,
					$key . '.member_id' );
				if ( is_wp_error( $member ) ) { return $member; }
				$value['member_id'] = $member;
			}
			if ( 'character_resource_spent' === $value['kind'] && isset( $value['resource_cost']['shared_base'] ) ) {
				$shared =& $value['resource_cost']['shared_base'];
				$universe = ps_pen_paper_prepare_import_id( $shared['universe_id'], 'pspp_universe', $records, $id_map );
				$base = ps_pen_paper_prepare_import_id( $shared['character_id'], 'pspp_character', $records, $id_map,
					$shared['universe_id'] );
				if ( is_wp_error( $universe ) || is_wp_error( $base ) ) { return ps_pen_paper_prepare_import_error(); }
				$shared['universe_id'] = $universe;
				$shared['character_id'] = $base;
				unset( $shared );
			}
			$object = ps_pen_paper_prepare_import_id( $value['object_id'], null, $records, $id_map );
			if ( is_wp_error( $object ) ) { return $object; }
			$object_source = $records[ $value['object_id'] ] ?? null;
			if ( ! $object_source || ( $record['parent_id'] !== $object_source['id'] && $record['parent_id'] !== $object_source['parent_id'] ) ) {
				return ps_pen_paper_prepare_import_error();
			}
			$value['object_id'] = $object;
			return true;
		case '_pspp_roll':
			$value['recipient_id'] = ps_pen_paper_prepare_import_historical_header_user( $value['recipient_id'], $options, $archive,
				$key . '.recipient_id' );
			if ( is_wp_error( $value['recipient_id'] ) ) { return $value['recipient_id']; }
			if ( is_array( $value['character'] ) ) {
				$source_character = $value['character']['id'];
				if ( ! isset( $records[ $source_character ] ) || 'pspp_character' !== $records[ $source_character ]['type'] ||
					$record['parent_id'] !== $records[ $source_character ]['parent_id'] || ! isset( $id_map[ $source_character ] ) ) {
					return ps_pen_paper_prepare_import_error();
				}
				$value['character']['id'] = $id_map[ $source_character ];
				$owner = ps_pen_paper_prepare_import_historical_snapshot_user( $value['character']['owner_id'], $options, $archive,
					$key . '.character.owner_id' );
				if ( is_wp_error( $owner ) ) { return $owner; }
				$value['character']['owner_id'] = $owner;
				if ( isset( $value['character']['context'] ) && 'shared' === $value['character']['context']['mode'] ) {
					$source_base = $value['character']['context']['source_id'];
					if ( isset( $records[ $source_base ] ) ) {
						if ( 'pspp_character' !== $records[ $source_base ]['type'] ||
							$records[ $source_base ]['parent_id'] !== $parsed['universe_id'] || ! isset( $id_map[ $source_base ] ) ) {
							return ps_pen_paper_prepare_import_error();
						}
						$value['character']['context']['source_id'] = $id_map[ $source_base ];
					} elseif ( 'bind' === $options['universe_mode'] && isset( $options['bases'][ $source_base ], $id_map[ $source_base ] ) &&
						$options['bases'][ $source_base ] === $id_map[ $source_base ] ) {
						$value['character']['context']['source_id'] = $id_map[ $source_base ];
					} else {
						$archive['references'][] = array( 'kind' => 'historical_base', 'path' => $key . '.character.context.source_id', 'source_id' => $source_base );
						$archive['warnings'][] = __( 'Eine historische Basisbogen-ID bleibt ausschließlich als Herkunftsnachweis erhalten; sie wird nicht als aktive Basisverbindung verwendet.', 'ps-pen-paper' );
					}
				}
			}
			return true;
		case '_pspp_wiki':
		case '_pspp_wiki_entry':
		case '_pspp_wiki_owner':
			return true;
	}
	return true;
}

function ps_pen_paper_prepare_import( $parsed, $options, $id_map, $archive_unknown = false ) {
	if ( ! is_array( $parsed ) || ! isset( $parsed['records'], $parsed['meta'], $parsed['archive'], $parsed['universe_id'], $parsed['bases'] ) ||
		! is_array( $options ) || ! ps_pen_paper_import_keys( $options, array( 'operation', 'target_id', 'universe_mode', 'universe_id', 'users', 'bases' ) ) ||
		! in_array( $options['operation'], array( 'copy', 'replace' ), true ) ||
		! in_array( $options['universe_mode'], array( 'none', 'copy', 'bind' ), true ) ||
		! is_int( $options['target_id'] ) || $options['target_id'] < 0 || ! is_int( $options['universe_id'] ) || $options['universe_id'] < 0 ||
		! is_array( $options['users'] ) || ! is_array( $options['bases'] ) ||
		! is_array( $id_map ) || ! is_bool( $archive_unknown ) ) { return ps_pen_paper_prepare_import_error(); }
	$source_campaign = (int) $parsed['archive']['payload']['source']['campaign_id'];
	if ( ! isset( $parsed['records'][ $source_campaign ] ) || ! isset( $id_map[ $source_campaign ] ) ||
		! is_int( $id_map[ $source_campaign ] ) || $id_map[ $source_campaign ] < 1 ) { return ps_pen_paper_prepare_import_error(); }
	if ( 'replace' === $options['operation'] && $options['target_id'] !== $id_map[ $source_campaign ] ) { return ps_pen_paper_prepare_import_error(); }
	if ( 'copy' === $options['operation'] && $options['target_id'] ) { return ps_pen_paper_prepare_import_error(); }
	if ( 'bind' === $options['universe_mode'] && ( ! $parsed['universe_id'] || ! $options['universe_id'] ||
		! isset( $id_map[ $parsed['universe_id'] ] ) || $id_map[ $parsed['universe_id'] ] !== $options['universe_id'] ) ) { return ps_pen_paper_prepare_import_error(); }
	foreach ( $parsed['users'] as $user_id => $unused ) {
		if ( ! array_key_exists( $user_id, $options['users'] ) || ! is_int( $options['users'][ $user_id ] ) ||
			$options['users'][ $user_id ] < -1 ) { return ps_pen_paper_prepare_import_error(); }
	}
	foreach ( $parsed['records'] as $id => $record ) {
		if ( 'bind' === $options['universe_mode'] && $parsed['universe_id'] &&
			( $id === $parsed['universe_id'] || $record['parent_id'] === $parsed['universe_id'] ) ) { continue; }
		if ( ! isset( $id_map[ $id ] ) || ! is_int( $id_map[ $id ] ) || $id_map[ $id ] < 1 ) { return ps_pen_paper_prepare_import_error(); }
	}
	$document_report = ps_pen_paper_import_documents( $parsed );
	if ( ! empty( $document_report['conflicts'] ) ) { return ps_pen_paper_prepare_import_error(); }
	$known = ps_pen_paper_prepare_import_known_meta();
	$type_meta = ps_pen_paper_prepare_import_type_meta();
	$output = array();
	$source_universe = (int) $parsed['universe_id'];
	$bound = 'bind' === $options['universe_mode'];
	$warnings = array();
	if ( $bound ) {
		foreach ( $parsed['bases'] as $base_id => $base ) {
			if ( ! isset( $options['bases'][ $base_id ], $id_map[ $base_id ] ) ||
				! is_int( $options['bases'][ $base_id ] ) || $options['bases'][ $base_id ] < 1 ||
				$options['bases'][ $base_id ] !== $id_map[ $base_id ] ) { return ps_pen_paper_prepare_import_error(); }
		}
	}
	$destination_ids = array();
	foreach ( $parsed['records'] as $id => $record ) {
		if ( $bound && $source_universe && ( $id === $source_universe || $record['parent_id'] === $source_universe ) ) { continue; }
		if ( isset( $destination_ids[ $id_map[ $id ] ] ) ) { return ps_pen_paper_prepare_import_error(); }
		$destination_ids[ $id_map[ $id ] ] = true;
	}

	foreach ( $parsed['records'] as $source_id => $record ) {
		if ( $bound && $source_universe && $record['id'] === $source_universe ) { continue; }
		if ( $bound && $source_universe && $record['parent_id'] === $source_universe ) { continue; }
		if ( ! ps_pen_paper_prepare_import_required_record_meta( $record, $parsed['meta'][ $source_id ] ) ) { return ps_pen_paper_prepare_import_error(); }
		$archive = array( 'metadata' => array(), 'warnings' => array(), 'references' => array() );
		$author = 'pspp_character' === $record['type']
			? ps_pen_paper_prepare_import_mapped_user( $record['author_id'], $options )
			: ps_pen_paper_prepare_import_historical_header_user( $record['author_id'], $options, $archive, 'post_author' );
		if ( is_wp_error( $author ) ) { return ps_pen_paper_prepare_import_error(); }
		$parent = 0;
		if ( $record['parent_id'] ) {
			$parent = ps_pen_paper_prepare_import_id( $record['parent_id'], null, $parsed['records'], $id_map );
			if ( is_wp_error( $parent ) ) { return $parent; }
		}
		$dest = array(
			'type' => $record['type'], 'parent_id' => $parent, 'author_id' => $author, 'status' => $record['status'],
			'title' => $record['title'], 'content' => $record['content'], 'excerpt' => $record['excerpt'],
			'mime_type' => $record['mime_type'], 'date_gmt' => $record['date_gmt'], 'modified_gmt' => $record['modified_gmt'],
			'menu_order' => $record['menu_order'], 'meta' => array(),
		);
		$metadata = $parsed['records'][ $source_id ]['meta']; $meta_offsets = array();
		foreach ( $metadata as $entry ) {
			$key = $entry['key']; $offset = $meta_offsets[ $key ] ?? 0;
			$value = $parsed['meta'][ $source_id ][ $key ][ $offset ]; $meta_offsets[ $key ] = $offset + 1;
			if ( $source_id === (int) $parsed['archive']['payload']['source']['campaign_id'] && '_pspp_access' === $key ) {
				$archive['source_access'] = array( 'key' => $key, 'value' => $entry['value'] );
				$archive['warnings'][] = __( 'Die ursprünglichen Zugriffseinstellungen wurden als private Herkunftsdaten archiviert und nicht auf die Zielkampagne angewendet.', 'ps-pen-paper' );
			}
			if ( ! in_array( $key, $known, true ) && ! preg_match( '/^_pspp_join_[1-9]\d*$/D', $key ) ) {
				if ( ! $archive_unknown ) { return ps_pen_paper_prepare_import_error(); }
				$archive['metadata'][] = array( 'key' => $key, 'value' => $entry['value'] );
				$archive['warnings'][] = __( 'Unbekannte Metadaten wurden unverändert als private Herkunftsdaten archiviert und nicht aktiviert.', 'ps-pen-paper' );
				continue;
			}
			$allowed = isset( $type_meta[ $record['type'] ] ) && in_array( $key, $type_meta[ $record['type'] ], true );
			if ( preg_match( '/^_pspp_join_[1-9]\d*$/D', $key ) ) { $allowed = 'pspp_campaign' === $record['type']; }
			if ( ! $allowed ) { return ps_pen_paper_prepare_import_error(); }
			$valid = ps_pen_paper_prepare_import_validate_known( $key, $value, $record, $parsed );
			if ( null === $valid || ! $valid ) { return ps_pen_paper_prepare_import_error(); }
			if ( in_array( $key, array( '_pspp_play_round', '_pspp_play_token', '_pspp_play_origin' ), true ) ) {
				$archive['metadata'][] = array( 'key' => $key, 'value' => $entry['value'] );
				$archive['warnings'][] = __( 'Die importierte Runde wird als eigenständige Kampagne vorbereitet. Frühere Rundenverknüpfungen und Startkennungen werden nicht aktiviert.', 'ps-pen-paper' );
				continue;
			}
			if ( ! empty( $parsed['meta'][ $parsed['archive']['payload']['source']['campaign_id'] ]['_pspp_play_round'] ) &&
				( '_pspp_game_round' === $key || ( in_array( $key, array( '_pspp_adventure_run', '_pspp_adventure_group' ), true ) && 'active' === $value['status'] ) ) ) {
				$archive['metadata'][] = array( 'key' => $key, 'value' => $entry['value'] );
				if ( '_pspp_game_round' === $key ) { $value['active'] = false; }
				else { $value['status'] = 'paused'; }
				$archive['warnings'][] = __( 'Die frühere Spielsitzung wird beim Import nicht automatisch fortgesetzt.', 'ps-pen-paper' );
			}
			if ( '_pspp_import_epoch' === $key || '_pspp_import_receipt' === $key || '_pspp_import_backup' === $key ||
				'_pspp_wiki' === $key || '_pspp_wiki_entry' === $key || '_pspp_wiki_owner' === $key || '_pspp_wiki_links' === $key ) {
				$archive['metadata'][] = array( 'key' => $key, 'value' => $entry['value'] );
				if ( 0 === strpos( $key, '_pspp_wiki' ) ) {
					$archive['warnings'][] = __( 'Externe Wiki-Konfiguration und Artikelverknüpfungen wurden als private Herkunftsdaten archiviert und nicht aktiviert.', 'ps-pen-paper' );
				}
				continue;
			}
			$remapped = ps_pen_paper_prepare_import_remap_value( $key, $value, $record, $parsed, $options, $id_map, $archive );
			if ( is_wp_error( $remapped ) ) { return $remapped; }
			if ( is_array( $remapped ) && isset( $remapped['key'], $remapped['value'] ) ) {
				$dest['meta'][] = $remapped;
			} else {
				$dest['meta'][] = array( 'key' => $key, 'value' => $value );
			}
		}
		if ( 'pspp_item' === $record['type'] ) {
			$origin = $parsed['meta'][ $source_id ]['_pspp_item_origin'][0] ?? array( 'aliases' => array() );
			if ( ! in_array( $source_id, $origin['aliases'], true ) ) { $origin['aliases'][] = $source_id; }
			if ( ! ps_pen_paper_valid_item_origin( $origin ) ) { return ps_pen_paper_prepare_import_error(); }
			$dest['meta'] = array_values( array_filter( $dest['meta'], function ( $entry ) { return '_pspp_item_origin' !== $entry['key']; } ) );
			$dest['meta'][] = array( 'key' => '_pspp_item_origin', 'value' => $origin );
		}
		$document_key = 'pspp_roll' === $record['type'] ? '_pspp_roll' : ( 'pspp_event' === $record['type'] ? '_pspp_event' : null );
		if ( $document_key ) {
			foreach ( $dest['meta'] as &$entry ) {
				if ( $document_key !== $entry['key'] ) { continue; }
				if ( 0 === $entry['value']['recipient_id'] &&
					( ( '_pspp_roll' === $document_key && 'private' === $entry['value']['visibility'] ) ||
						( '_pspp_event' === $document_key && 'owner' === $entry['value']['visibility'] ) ) ) {
					$entry['value']['visibility'] = 'gm';
				}
				$visibility = $entry['value']['visibility'];
				$recipient = (string) $entry['value']['recipient_id'];
			}
			unset( $entry );
			foreach ( $dest['meta'] as &$entry ) {
				if ( '_pspp_visibility' === $entry['key'] ) { $entry['value'] = $visibility; }
				if ( '_pspp_recipient' === $entry['key'] ) { $entry['value'] = $recipient; }
			}
			unset( $entry );
		}
		if ( $archive['metadata'] || $archive['warnings'] || $archive['references'] || isset( $archive['source_access'] ) ) {
			if ( isset( $parsed['meta'][ $source_id ]['_pspp_import_archive'][0] ) ) {
				$archive['previous'] = $parsed['meta'][ $source_id ]['_pspp_import_archive'][0];
			}
			$archive['version'] = 1;
			$dest['meta'][] = array( 'key' => '_pspp_import_archive', 'value' => $archive );
		} elseif ( isset( $parsed['meta'][ $source_id ]['_pspp_import_archive'][0] ) ) {
			$dest['meta'][] = array( 'key' => '_pspp_import_archive', 'value' => $parsed['meta'][ $source_id ]['_pspp_import_archive'][0] );
		}
		$output[ $source_id ] = $dest;
	}

	// Scope membership and exact live references are checked against the source graph before any values are returned.
	foreach ( $output as $source_id => &$record ) {
		foreach ( $record['meta'] as &$entry ) {
			$key =& $entry['key']; $value =& $entry['value'];
			if ( '_pspp_import_archive' === $key ) { unset( $key, $value ); continue; }
			if ( '_pspp_wiki' === $key || '_pspp_wiki_entry' === $key || '_pspp_wiki_owner' === $key ||
				'_pspp_import_epoch' === $key || '_pspp_import_receipt' === $key || '_pspp_import_backup' === $key ) {
				unset( $key, $value ); return ps_pen_paper_prepare_import_error();
			}
			unset( $key, $value );
		}
		unset( $entry );
	}
	unset( $record );
	return $output;
}
