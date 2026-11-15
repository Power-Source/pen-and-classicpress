<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_equipment_error( $code = 'invalid_equipment' ) {
	return new WP_Error( $code, __( 'Ausrüstungsdaten fehlen, sind ungültig oder wurden geändert. Bitte lade den aktuellen Stand neu.', 'ps-pen-paper' ) );
}

function ps_pen_paper_require_community() {
	return ps_pen_paper_community_available() ? true : new WP_Error( 'community_required', __( 'Gegenstandskatalog, Inventar und Ausrüstung benötigen aktives PS Community mit Profil-Schnittstellen.', 'ps-pen-paper' ) );
}

function ps_pen_paper_register_item_type() {
	register_post_type( 'pspp_item', array(
		'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
		'show_ui' => false, 'show_in_rest' => false, 'supports' => false,
		'capability_type' => array( 'pspp_item', 'pspp_items' ), 'map_meta_cap' => true,
	) );
}

function ps_pen_paper_equipment_schema( $scope ) {
	$value = get_post_meta( $scope->ID, '_pspp_equipment_schema', true );
	if ( '' === $value ) { return array( 'revision' => 0, 'slots' => array(), 'rarities' => array() ); }
	if ( ! is_array( $value ) || ! isset( $value['revision'], $value['slots'], $value['rarities'] ) ||
		! is_int( $value['revision'] ) || $value['revision'] < 1 ||
		! is_array( $value['slots'] ) || count( $value['slots'] ) > 30 ||
		! is_array( $value['rarities'] ) || count( $value['rarities'] ) > 30 ) { return ps_pen_paper_equipment_error(); }
	foreach ( array( 'slots', 'rarities' ) as $key ) {
		foreach ( $value[ $key ] as $id => $entry ) {
			if ( ! is_string( $id ) || ! preg_match( '/^[a-z][a-z0-9_-]{0,39}$/D', $id ) || ! is_array( $entry ) ||
				! isset( $entry['label'] ) || ! is_string( $entry['label'] ) || '' === trim( $entry['label'] ) || strlen( $entry['label'] ) > 200 ||
				( 'slots' === $key && ( ! isset( $entry['kind'] ) || ! is_string( $entry['kind'] ) || ! preg_match( '/^[a-z][a-z0-9_-]{0,39}$/D', $entry['kind'] ) ) ) ||
				( 'rarities' === $key && ( ! isset( $entry['color'] ) || ! is_string( $entry['color'] ) || ! preg_match( '/^#[a-f0-9]{6}$/iD', $entry['color'] ) ) ) ) { return ps_pen_paper_equipment_error(); }
		}
	}
	return $value;
}

function ps_pen_paper_save_equipment_schema( $scope, $input, $revision ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_character_scope( $scope ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	$before = ps_pen_paper_equipment_schema( $scope );
	if ( is_wp_error( $before ) ) { return $before; }
	if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision || ! is_array( $input ) ) { return ps_pen_paper_equipment_error(); }
	$after = array( 'revision' => $before['revision'] + 1, 'slots' => array(), 'rarities' => array() );
	foreach ( array( 'slots', 'rarities' ) as $key ) {
		if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) || strlen( $input[ $key ] ) > 10000 ) { return ps_pen_paper_equipment_error(); }
		foreach ( preg_split( '/\r\n|\r|\n/', trim( $input[ $key ] ) ) as $line ) {
			if ( '' === trim( $line ) ) { continue; }
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( 3 !== count( $parts ) || isset( $after[ $key ][ $parts[0] ] ) || ! preg_match( '/^[a-z][a-z0-9_-]{0,39}$/D', $parts[0] ) ) { return ps_pen_paper_equipment_error(); }
			$after[ $key ][ $parts[0] ] = array( 'label' => sanitize_text_field( $parts[1] ), 'slots' === $key ? 'kind' : 'color' => $parts[2] );
		}
	}
	foreach ( $after['slots'] as $entry ) {
		if ( '' === $entry['label'] || strlen( $entry['label'] ) > 200 || ! preg_match( '/^[a-z][a-z0-9_-]{0,39}$/D', $entry['kind'] ) ) { return ps_pen_paper_equipment_error(); }
	}
	foreach ( $after['rarities'] as $entry ) {
		if ( '' === $entry['label'] || strlen( $entry['label'] ) > 200 || ! preg_match( '/^#[a-f0-9]{6}$/iD', $entry['color'] ) ) { return ps_pen_paper_equipment_error(); }
	}
	if ( count( $after['slots'] ) > 30 || count( $after['rarities'] ) > 30 ) { return ps_pen_paper_equipment_error(); }
	// Stable keys cannot disappear or change meaning while items or inventories may refer to them.
	foreach ( array( 'slots', 'rarities' ) as $key ) {
		foreach ( $before[ $key ] as $id => $entry ) {
			if ( ! isset( $after[ $key ][ $id ] ) || ( 'slots' === $key && $entry['kind'] !== $after[ $key ][ $id ]['kind'] ) ) {
				return new WP_Error( 'schema_in_use', __( 'Vorhandene Slot- und Seltenheitskennungen müssen erhalten bleiben; Slottypen dürfen nicht umgedeutet werden.', 'ps-pen-paper' ) );
			}
		}
	}
	return ps_pen_paper_save_document_event( $scope->ID, $scope->ID, '_pspp_equipment_schema', $before, $after, array(
		'kind' => 'equipment_schema', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $scope->ID )['revision'],
	) );
}

function ps_pen_paper_item_bindings() {
	return array( 'bound' => __( 'Charaktergebunden', 'ps-pen-paper' ), 'tradeable' => __( 'Handelbar', 'ps-pen-paper' ) );
}

function ps_pen_paper_item_binding( $item ) {
	return isset( $item['binding'] ) ? $item['binding'] : 'bound';
}

function ps_pen_paper_validate_item( $item ) {
	if ( ! is_array( $item ) || ! isset( $item['name'], $item['description'], $item['kind'], $item['rarity'], $item['rarity_label'], $item['color'], $item['asset_id'], $item['properties'], $item['bonuses'] ) ||
		! is_string( $item['name'] ) || '' === trim( $item['name'] ) || strlen( $item['name'] ) > 200 ||
		! is_string( $item['description'] ) || strlen( $item['description'] ) > 20000 ||
		! is_string( $item['kind'] ) || ! preg_match( '/^[a-z][a-z0-9_-]{0,39}$/D', $item['kind'] ) ||
		! is_string( $item['rarity'] ) || ! preg_match( '/^[a-z][a-z0-9_-]{0,39}$/D', $item['rarity'] ) ||
		! is_string( $item['rarity_label'] ) || strlen( $item['rarity_label'] ) > 200 ||
		! is_string( $item['color'] ) || ! preg_match( '/^#[a-f0-9]{6}$/iD', $item['color'] ) ||
		! is_int( $item['asset_id'] ) || $item['asset_id'] < 0 ||
		! is_array( $item['properties'] ) || count( $item['properties'] ) > 30 ||
		! is_array( $item['bonuses'] ) || count( $item['bonuses'] ) > 30 ) { return false; }
	if ( array_key_exists( 'binding', $item ) && ( ! is_string( $item['binding'] ) || ! isset( ps_pen_paper_item_bindings()[ $item['binding'] ] ) ) ) { return false; }
	foreach ( $item['properties'] as $label => $value ) {
		if ( ! is_string( $label ) || '' === trim( $label ) || strlen( $label ) > 200 || ! is_string( $value ) || strlen( $value ) > 1000 ) { return false; }
	}
	foreach ( $item['bonuses'] as $id => $bonus ) {
		if ( ! is_string( $id ) || ! is_int( $bonus ) || abs( $bonus ) > 1000000 ) { return false; }
	}
	return true;
}

function ps_pen_paper_get_item( $scope, $id ) {
	$post = ps_pen_paper_record_post( $id );
	$state = get_post_meta( $id, '_pspp_item', true );
	if ( ! $post || 'pspp_item' !== $post->post_type || 'private' !== $post->post_status || (int) $post->post_parent !== (int) $scope->ID ||
		! is_array( $state ) || ! isset( $state['revision'], $state['item'] ) || ! is_int( $state['revision'] ) || $state['revision'] < 1 ||
		! ps_pen_paper_validate_item( $state['item'] ) ) { return ps_pen_paper_equipment_error(); }
	return $state;
}

function ps_pen_paper_item_catalogue( $scope ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_character_scope( $scope ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	$scopes = array( $scope->ID => $scope );
	if ( 'pspp_campaign' === $scope->post_type ) {
		$link = ps_pen_paper_get_universe_link( $scope->ID );
		if ( is_wp_error( $link ) ) { return $link; }
		if ( $link['universe_id'] ) {
			$universe = ps_pen_paper_get_universe( $link['universe_id'] );
			if ( ! $universe ) { return ps_pen_paper_equipment_error(); }
			$scopes[ $universe->ID ] = $universe;
		}
	}
	$items = array();
	foreach ( $scopes as $parent ) {
		foreach ( get_posts( array( 'post_type' => 'pspp_item', 'post_status' => 'private', 'post_parent' => $parent->ID, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
			$item = ps_pen_paper_get_item( $parent, $post->ID );
			if ( is_wp_error( $item ) ) { return $item; }
			$item['scope_id'] = (int) $parent->ID;
			$items[ $post->ID ] = $item;
		}
	}
	return $items;
}

function ps_pen_paper_save_item( $scope, $id, $input, $revision, $image_path = null ) {
	return ps_pen_paper_scope_write( $scope, function () use ( $scope, $id, $input, $revision, $image_path ) {
		return ps_pen_paper_save_item_locked( $scope, $id, $input, $revision, $image_path );
	} );
}

function ps_pen_paper_save_item_locked( $scope, $id, $input, $revision, $image_path = null ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_character_scope( $scope ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	$before = $id ? ps_pen_paper_get_item( $scope, $id ) : array( 'revision' => 0, 'item' => null );
	$schema = ps_pen_paper_equipment_schema( $scope );
	if ( is_wp_error( $before ) || is_wp_error( $schema ) ) { return is_wp_error( $before ) ? $before : $schema; }
	if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision || ! is_array( $input ) ) { return ps_pen_paper_equipment_error(); }
	foreach ( array( 'name', 'description', 'kind', 'rarity', 'properties', 'binding' ) as $key ) { if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) { return ps_pen_paper_equipment_error(); } }
	if ( ! isset( $schema['rarities'][ $input['rarity'] ] ) || strlen( $input['properties'] ) > 20000 ||
		( isset( $input['bonuses'] ) && ! is_array( $input['bonuses'] ) ) ) { return ps_pen_paper_equipment_error(); }
	$rarity = $schema['rarities'][ $input['rarity'] ];
	$item = array(
		'name' => sanitize_text_field( $input['name'] ), 'description' => sanitize_textarea_field( $input['description'] ),
		'kind' => $input['kind'], 'rarity' => $input['rarity'], 'rarity_label' => $rarity['label'], 'color' => $rarity['color'], 'binding' => $input['binding'],
		'asset_id' => $before['item'] ? $before['item']['asset_id'] : 0, 'properties' => array(), 'bonuses' => array(),
	);
	foreach ( preg_split( '/\r\n|\r|\n/', trim( $input['properties'] ) ) as $line ) {
		if ( '' === trim( $line ) ) { continue; }
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( 2 !== count( $parts ) || '' === $parts[0] || isset( $item['properties'][ $parts[0] ] ) ) { return ps_pen_paper_equipment_error(); }
		$label = sanitize_text_field( $parts[0] );
		if ( isset( $item['properties'][ $label ] ) ) { return ps_pen_paper_equipment_error(); }
		$item['properties'][ $label ] = sanitize_text_field( $parts[1] );
	}
	$rules = ps_pen_paper_get_rules( $scope->ID );
	$fields = array_column( $rules['fields'], null, 'id' );
	foreach ( isset( $input['bonuses'] ) ? $input['bonuses'] : array() as $field_id => $bonus ) {
		if ( ! isset( $fields[ $field_id ] ) || ! in_array( $fields[ $field_id ]['type'], array( 'attribute', 'number' ), true ) ||
			! is_string( $bonus ) || ! preg_match( '/^-?\d{1,7}$/D', $bonus ) || abs( (int) $bonus ) > 1000000 ) { return ps_pen_paper_equipment_error(); }
		if ( 0 !== (int) $bonus ) { $item['bonuses'][ $field_id ] = (int) $bonus; }
	}
	if ( ! ps_pen_paper_validate_item( $item ) ) { return ps_pen_paper_equipment_error(); }
	$asset_id = 0;
	if ( null !== $image_path ) {
		$asset_id = ps_pen_paper_store_private_image( $scope->ID, $image_path );
		if ( is_wp_error( $asset_id ) ) { return $asset_id; }
		$item['asset_id'] = $asset_id;
	}
	$new = ! $id;
	if ( $new ) { $id = wp_insert_post( array( 'post_type' => 'pspp_item', 'post_status' => 'draft', 'post_parent' => $scope->ID, 'post_title' => 'PS Pen & Paper Item' ), true ); }
	$result = is_wp_error( $id ) || ! $id ? ps_pen_paper_equipment_error( 'save_failed' ) : ps_pen_paper_save_document_event( $scope->ID, $id, '_pspp_item', $before, array( 'revision' => $before['revision'] + 1, 'item' => $item ), array(
		'kind' => 'item_saved', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => $rules['revision'],
	), $new );
	if ( is_wp_error( $result ) ) {
		if ( 'event_recovery_required' === $result->get_error_code() ) { return $result; }
		if ( $asset_id && ! wp_delete_post( $asset_id, true ) ) { return ps_pen_paper_equipment_error( 'cleanup_failed' ); }
		return $new && is_int( $id ) && $id ? ps_pen_paper_discard_record( $id, $result ) : $result;
	}
	return $id;
}

function ps_pen_paper_valid_item_instance( $item ) {
	if ( ! is_array( $item ) || ! isset( $item['source_id'], $item['scope_id'], $item['revision'], $item['item'] ) ) { return false; }
	foreach ( array( 'source_id', 'scope_id', 'revision' ) as $key ) { if ( ! is_int( $item[ $key ] ) || $item[ $key ] < 1 ) { return false; } }
	return ps_pen_paper_validate_item( $item['item'] );
}

function ps_pen_paper_gear_state( $character_id ) {
	$state = get_post_meta( $character_id, '_pspp_gear', true );
	if ( '' === $state ) { return array( 'revision' => 0, 'active' => array( 'items' => array(), 'slots' => array() ), 'pending' => null ); }
	return ps_pen_paper_validate_gear_state( $state );
}

function ps_pen_paper_validate_gear_state( $state ) {
	if ( ! is_array( $state ) || ! isset( $state['revision'], $state['active'] ) || ! array_key_exists( 'pending', $state ) ||
		! is_int( $state['revision'] ) || $state['revision'] < 1 ) { return ps_pen_paper_equipment_error(); }
	foreach ( array( $state['active'], $state['pending'] ) as $snapshot ) {
		if ( null === $snapshot ) { continue; }
		if ( ! is_array( $snapshot ) || ! isset( $snapshot['items'], $snapshot['slots'] ) ||
			! is_array( $snapshot['items'] ) || count( $snapshot['items'] ) > 100 ||
			! is_array( $snapshot['slots'] ) || count( $snapshot['slots'] ) > 30 ||
			count( array_unique( $snapshot['slots'], SORT_REGULAR ) ) !== count( $snapshot['slots'] ) ) { return ps_pen_paper_equipment_error(); }
		foreach ( $snapshot['items'] as $id => $instance ) {
			if ( ! is_string( $id ) || ! wp_is_uuid( $id ) || ! is_array( $instance ) ||
				! isset( $instance['source_id'], $instance['scope_id'], $instance['revision'], $instance['item'] ) ||
				! is_int( $instance['source_id'] ) || $instance['source_id'] < 1 ||
				! is_int( $instance['scope_id'] ) || $instance['scope_id'] < 1 ||
				! is_int( $instance['revision'] ) || $instance['revision'] < 1 ||
				! ps_pen_paper_validate_item( $instance['item'] ) ) { return ps_pen_paper_equipment_error(); }
		}
		foreach ( $snapshot['slots'] as $slot => $instance_id ) {
			if ( ! is_string( $slot ) || ! is_string( $instance_id ) || ! isset( $snapshot['items'][ $instance_id ] ) ) { return ps_pen_paper_equipment_error(); }
		}
	}
	return $state;
}

function ps_pen_paper_gear_bonus( $campaign, $snapshot, $character_snapshot ) {
	$schema = ps_pen_paper_equipment_schema( $campaign );
	if ( is_wp_error( $schema ) ) { return $schema; }
	$rules = ps_pen_paper_get_rules( $campaign->ID );
	$fields = array_column( $rules['fields'], null, 'id' );
	$totals = array();
	foreach ( $snapshot['slots'] as $slot_id => $instance_id ) {
		$item = $snapshot['items'][ $instance_id ]['item'];
		if ( ! isset( $schema['slots'][ $slot_id ] ) || $schema['slots'][ $slot_id ]['kind'] !== $item['kind'] ) { return ps_pen_paper_equipment_error(); }
		foreach ( $item['bonuses'] as $field_id => $bonus ) {
			if ( ! isset( $fields[ $field_id ] ) || ! in_array( $fields[ $field_id ]['type'], array( 'attribute', 'number' ), true ) ) { return ps_pen_paper_equipment_error(); }
			$totals[ $field_id ] = ( isset( $totals[ $field_id ] ) ? $totals[ $field_id ] : 0 ) + $bonus;
		}
	}
	foreach ( $totals as $field_id => $bonus ) {
		if ( ! $character_snapshot || ! isset( $character_snapshot['values'][ $field_id ] ) || ! is_int( $character_snapshot['values'][ $field_id ] ) ) { return ps_pen_paper_equipment_error(); }
		$value = $character_snapshot['values'][ $field_id ] + $bonus;
		if ( $value < $fields[ $field_id ]['min'] || $value > $fields[ $field_id ]['max'] ) {
			return new WP_Error( 'equipment_range', __( 'Die angelegten Boni würden einen erlaubten Charakterwertebereich überschreiten. Bitte Ausrüstung oder Grundwerte zuerst anpassen.', 'ps-pen-paper' ) );
		}
	}
	return $totals;
}

function ps_pen_paper_apply_gear( $scope, $character, $state ) {
	if ( 'pspp_campaign' !== $scope->post_type || ! ps_pen_paper_community_available() ) { return $state; }
	$gear = ps_pen_paper_gear_state( $character->ID );
	if ( is_wp_error( $gear ) ) { return $gear; }
	$bonus = ps_pen_paper_gear_bonus( $scope, $gear['active'], $state['active'] );
	if ( is_wp_error( $bonus ) ) { return $bonus; }
	$state['equipment'] = array( 'revision' => $gear['revision'], 'schema_revision' => ps_pen_paper_equipment_schema( $scope )['revision'], 'bonuses' => $bonus );
	foreach ( $bonus as $id => $value ) { $state['active']['values'][ $id ] += $value; }
	return $state;
}

function ps_pen_paper_save_gear( $campaign, $character_id, $operation, $input, $revision ) {
	return ps_pen_paper_with_character_lock( $character_id, function () use ( $campaign, $character_id, $operation, $input, $revision ) {
		return ps_pen_paper_save_gear_locked( $campaign, $character_id, $operation, $input, $revision );
	} );
}

function ps_pen_paper_save_gear_locked( $campaign, $character_id, $operation, $input, $revision ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	$character = ps_pen_paper_get_character( $campaign, $character_id );
	if ( ! ps_pen_paper_can_access_site() || ! $character ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	$is_gm = ps_pen_paper_can_manage_campaign( $campaign );
	if ( ! $is_gm && 'gm_only' === ps_pen_paper_character_mode( ps_pen_paper_get_rules( $campaign->ID ) ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	$before = ps_pen_paper_gear_state( $character_id );
	if ( is_wp_error( $before ) ) { return $before; }
	if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision || ! is_array( $input ) ) { return ps_pen_paper_equipment_error( 'conflict' ); }
	$after = $before;
	$after['revision']++;
	$validate = array();
	if ( 'grant_item' === $operation ) {
		if ( ! $is_gm || count( $before['active']['items'] ) >= 100 || ! isset( $input['item_id'], $input['item_revision'] ) ||
			! is_string( $input['item_id'] ) || ! ctype_digit( $input['item_id'] ) || ! is_string( $input['item_revision'] ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
		$catalogue = ps_pen_paper_item_catalogue( $campaign );
		if ( is_wp_error( $catalogue ) ) { return $catalogue; }
		$id = (int) $input['item_id'];
		if ( ! isset( $catalogue[ $id ] ) || (string) $catalogue[ $id ]['revision'] !== $input['item_revision'] ) { return ps_pen_paper_equipment_error(); }
		$source = $catalogue[ $id ];
		$instance = array( 'source_id' => $id, 'scope_id' => $source['scope_id'], 'revision' => $source['revision'], 'item' => $source['item'] );
		$instance['item']['binding'] = ps_pen_paper_item_binding( $source['item'] );
		$after = ps_pen_paper_append_item_instances( $before, $instance, 1 );
		if ( is_wp_error( $after ) ) { return $after; }
	} elseif ( 'remove_items' === $operation ) {
		if ( ! $is_gm || ! isset( $input['ids'], $input['confirmation'] ) || 'confirmed' !== $input['confirmation'] ||
			! is_array( $input['ids'] ) || ! $input['ids'] || array_values( $input['ids'] ) !== $input['ids'] ||
			count( $input['ids'] ) > 100 ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
		$reserved = ps_pen_paper_reserved_trade_items( $campaign, $character_id );
		if ( is_wp_error( $reserved ) ) { return $reserved; }
		$seen = array();
		foreach ( $input['ids'] as $uuid ) {
			if ( ! is_string( $uuid ) || ! wp_is_uuid( $uuid ) || isset( $seen[ $uuid ] ) ||
				! isset( $before['active']['items'][ $uuid ] ) || in_array( $uuid, $reserved, true ) ) {
				return new WP_Error( 'item_unavailable', __( 'Ein ausgewähltes Exemplar fehlt oder ist für einen Handel reserviert. Ziehe das Angebot zuerst zurück und lade neu.', 'ps-pen-paper' ) );
			}
			$seen[ $uuid ] = true;
			foreach ( array( 'active', 'pending' ) as $key ) {
				if ( null === $after[ $key ] ) { continue; }
				unset( $after[ $key ]['items'][ $uuid ] );
				$after[ $key ]['slots'] = array_diff( $after[ $key ]['slots'], array( $uuid ) );
			}
		}
		$validate[] = $after['active'];
		if ( null !== $after['pending'] ) { $validate[] = $after['pending']; }
	} elseif ( 'review_gear' === $operation ) {
		if ( ! $is_gm || null === $before['pending'] || ! isset( $input['decision'] ) || ! in_array( $input['decision'], array( 'approve', 'reject' ), true ) ) { return ps_pen_paper_equipment_error(); }
		if ( 'approve' === $input['decision'] ) { $after['active'] = $before['pending']; $validate[] = $after['active']; }
		$after['pending'] = null;
	} elseif ( 'equip_items' === $operation ) {
		$slots = isset( $input['slots'] ) ? $input['slots'] : array();
		if ( ! is_array( $slots ) || count( $slots ) > 30 ) { return ps_pen_paper_equipment_error(); }
		$snapshot = $before['active'];
		$snapshot['slots'] = array();
		foreach ( $slots as $slot => $id ) {
			if ( ! is_string( $id ) ) { return ps_pen_paper_equipment_error(); }
			if ( '' === $id ) { continue; }
			if ( ! is_string( $slot ) || ! isset( $snapshot['items'][ $id ] ) || in_array( $id, $snapshot['slots'], true ) ) { return ps_pen_paper_equipment_error(); }
			$snapshot['slots'][ $slot ] = $id;
		}
		if ( ! $is_gm && 'approval' === ps_pen_paper_character_mode( ps_pen_paper_get_rules( $campaign->ID ) ) ) {
			$after['pending'] = $snapshot;
		} else {
			$after['active'] = $snapshot;
			$after['pending'] = null;
		}
		$validate[] = $snapshot;
	} else { return ps_pen_paper_equipment_error(); }
	if ( $validate ) {
		$state = ps_pen_paper_character_state( $campaign, $character, false );
		if ( is_wp_error( $state ) ) { return $state; }
		foreach ( $validate as $snapshot ) {
			$reserved = ps_pen_paper_check_reserved_gear( $campaign, $character_id, $snapshot );
			if ( is_wp_error( $reserved ) ) { return $reserved; }
			$valid = ps_pen_paper_gear_bonus( $campaign, $snapshot, $state['active'] );
			if ( is_wp_error( $valid ) ) { return $valid; }
		}
	}
	return ps_pen_paper_save_document_event( $campaign->ID, $character_id, '_pspp_gear', $before, $after, array(
		'kind' => 'gear_saved', 'visibility' => 'owner', 'recipient_id' => (int) $character->post_author,
		'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $campaign->ID )['revision'],
	) );
}

// Internal writer: both inventories are locked and the exchange is authorized.
function ps_pen_paper_write_item_exchange( $campaign, $characters, $uuids, $instances, $incoming = null ) {
	foreach ( array( 0, 1 ) as $i ) {
		if ( is_string( $uuids[ $i ] ) ) {
			$instances[ $i ] = $uuids[ $i ] ? array( $uuids[ $i ] => $instances[ $i ] ) : array();
			$uuids[ $i ] = array_keys( $instances[ $i ] );
		}
		$incoming = null === $incoming ? $instances : $incoming;
		$campaigns = is_array( $campaign ) ? $campaign : array( $campaign, $campaign );
	}
	$states = array();
	foreach ( $characters as $i => $character ) {
		$before = ps_pen_paper_gear_state( $character->ID );
		if ( is_wp_error( $before ) ) { return $before; }
		$out = $uuids[ $i ]; $in = $uuids[ 1 - $i ];
		foreach ( array( $before['active'], $before['pending'] ) as $snapshot ) {
			if ( null === $snapshot ) { continue; }
			foreach ( $out as $uuid ) {
				if ( ! isset( $snapshot['items'][ $uuid ], $instances[ $i ][ $uuid ] ) || $snapshot['items'][ $uuid ] !== $instances[ $i ][ $uuid ] ||
					in_array( $uuid, $snapshot['slots'], true ) || 'tradeable' !== ps_pen_paper_item_binding( $snapshot['items'][ $uuid ]['item'] ) ) { return ps_pen_paper_equipment_error(); }
			}
			foreach ( $in as $uuid ) { if ( isset( $snapshot['items'][ $uuid ] ) ) { return ps_pen_paper_equipment_error(); } }
		}
		$after = $before; $after['revision']++;
		foreach ( array( 'active', 'pending' ) as $key ) {
			if ( null === $after[ $key ] ) { continue; }
			foreach ( $out as $uuid ) { unset( $after[ $key ]['items'][ $uuid ] ); }
			foreach ( $in as $uuid ) { $after[ $key ]['items'][ $uuid ] = $incoming[ 1 - $i ][ $uuid ]; }
			if ( count( $after[ $key ]['items'] ) > 100 ) { return new WP_Error( 'inventory_full', __( 'Das Zielinventar ist voll. Der Handel wurde nicht ausgeführt.', 'ps-pen-paper' ) ); }
		}
		$states[ $i ] = array( $before, $after );
	}
	foreach ( $characters as $i => $character ) {
		$scope = $campaigns[ $i ];
		$result = ps_pen_paper_write_document_event( $scope->ID, $character->ID, '_pspp_gear', $states[ $i ][0], $states[ $i ][1], array(
			'kind' => 'gear_saved', 'visibility' => 'owner', 'recipient_id' => (int) $character->post_author,
			'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $scope->ID )['revision'],
		), false );
		if ( is_wp_error( $result ) ) { return $result; }
	}
	return $result;
}

function ps_pen_paper_append_item_instances( $before, $instance, $quantity ) {
	if ( ! is_int( $quantity ) || $quantity < 1 || $quantity > 100 || ! ps_pen_paper_validate_item( $instance['item'] ) ||
		count( $before['active']['items'] ) + $quantity > 100 || ( $before['pending'] && count( $before['pending']['items'] ) + $quantity > 100 ) ) {
		return new WP_Error( 'inventory_full', __( 'Diese Vergabe überschreitet die Grenze von 100 Exemplaren oder enthält ungültige Gegenstände.', 'ps-pen-paper' ) );
	}
	$after = $before; $after['revision']++;
	$instance['item']['binding'] = ps_pen_paper_item_binding( $instance['item'] );
	for ( $i = 0; $i < $quantity; $i++ ) {
		$uuid = wp_generate_uuid4();
		$after['active']['items'][ $uuid ] = $instance;
		if ( $after['pending'] ) { $after['pending']['items'][ $uuid ] = $instance; }
	}
	return $after;
}

// Internal writer: the caller holds the character lock and event transaction.
function ps_pen_paper_write_item_award( $campaign, $character, $instance, $quantity, $revision ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) || ! ps_pen_paper_get_character( $campaign, $character->ID ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	return ps_pen_paper_write_item_award_delta( $campaign, $character, $instance, $quantity, $revision );
}

// Internal writer for an authorized award; the caller holds the lock and transaction.
function ps_pen_paper_write_item_award_delta( $campaign, $character, $instance, $quantity, $revision ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	$before = ps_pen_paper_gear_state( $character->ID );
	if ( is_wp_error( $before ) || ! is_string( $revision ) || (string) $before['revision'] !== $revision ) { return ps_pen_paper_equipment_error( 'conflict' ); }
	$after = ps_pen_paper_append_item_instances( $before, $instance, $quantity );
	if ( is_wp_error( $after ) ) { return $after; }
	return ps_pen_paper_write_document_event( $campaign->ID, $character->ID, '_pspp_gear', $before, $after, array(
		'kind' => 'gear_saved', 'visibility' => 'owner', 'recipient_id' => (int) $character->post_author,
		'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $campaign->ID )['revision'],
	), false );
}

function ps_pen_paper_check_character_gear( $scope, $character, $snapshot ) {
	if ( ! $character || 'pspp_campaign' !== $scope->post_type || ! ps_pen_paper_community_available() ) { return true; }
	$gear = ps_pen_paper_gear_state( $character->ID );
	if ( is_wp_error( $gear ) ) { return $gear; }
	$context = get_post_meta( $character->ID, '_pspp_character', true );
	if ( $snapshot && isset( $context['context']['overrides'] ) ) {
		foreach ( $context['context']['overrides'] as $id => $value ) { $snapshot['values'][ $id ] = $value; }
	}
	return ps_pen_paper_gear_bonus( $scope, $gear['active'], $snapshot );
}
