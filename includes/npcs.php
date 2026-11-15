<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_register_npc_type() {
	register_post_type( 'pspp_npc', array(
		'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
		'show_ui' => false, 'show_in_rest' => false, 'supports' => false,
		'capability_type' => array( 'pspp_npc', 'pspp_npcs' ), 'map_meta_cap' => true,
	) );
}

function ps_pen_paper_npc_kinds() {
	return array( 'npc' => __( 'NPC', 'ps-pen-paper' ), 'enemy' => __( 'Gegner', 'ps-pen-paper' ) );
}

function ps_pen_paper_npc_statuses() {
	return array( 'figure' => __( 'Kampagnenfigur', 'ps-pen-paper' ), 'proposed' => __( 'Aufnahme vorgeschlagen', 'ps-pen-paper' ), 'template' => __( 'Universumsvorlage', 'ps-pen-paper' ) );
}

function ps_pen_paper_npc_error() {
	return new WP_Error( 'invalid_npc', __( 'Diese Figur ist ungültig, wurde geändert oder ist nicht verfügbar. Bitte lade den aktuellen Stand neu.', 'ps-pen-paper' ) );
}

function ps_pen_paper_npc_default( $scope ) {
	return array( 'revision' => 0, 'status' => 'pspp_universe' === $scope->post_type ? 'template' : 'figure', 'source' => null, 'npc' => null );
}

function ps_pen_paper_valid_npc_state( $state ) {
	if ( ! is_array( $state ) || ! isset( $state['revision'], $state['status'] ) || ! array_key_exists( 'source', $state ) || ! array_key_exists( 'npc', $state ) ||
		! is_int( $state['revision'] ) || $state['revision'] < 0 || ! is_string( $state['status'] ) || ! isset( ps_pen_paper_npc_statuses()[ $state['status'] ] ) ) { return false; }
	if ( 0 === $state['revision'] ) { return null === $state['npc'] && null === $state['source']; }
	if ( null !== $state['source'] ) {
		if ( ! is_array( $state['source'] ) ) { return false; }
		foreach ( array( 'scope_id', 'record_id', 'revision' ) as $key ) {
			if ( ! isset( $state['source'][ $key ] ) || ! is_int( $state['source'][ $key ] ) || $state['source'][ $key ] < 1 ) { return false; }
		}
	}
	$npc = $state['npc'];
	if ( ! is_array( $npc ) || ! isset( $npc['kind'], $npc['name'], $npc['description'], $npc['secret'], $npc['asset_id'], $npc['scene_id'], $npc['properties'], $npc['values'] ) ||
		! is_string( $npc['kind'] ) || ! isset( ps_pen_paper_npc_kinds()[ $npc['kind'] ] ) ||
		! is_string( $npc['name'] ) || '' === trim( $npc['name'] ) || strlen( $npc['name'] ) > 200 ||
		! is_string( $npc['description'] ) || strlen( $npc['description'] ) > 20000 ||
		! is_string( $npc['secret'] ) || strlen( $npc['secret'] ) > 20000 ||
		! is_int( $npc['asset_id'] ) || $npc['asset_id'] < 0 || ! is_int( $npc['scene_id'] ) || $npc['scene_id'] < 0 ||
		( 'figure' !== $state['status'] && 0 !== $npc['scene_id'] ) ) { return false; }
	if ( isset( $npc['loot_table_id'] ) && ( ! is_int( $npc['loot_table_id'] ) || $npc['loot_table_id'] < 0 || ( 'figure' !== $state['status'] && $npc['loot_table_id'] ) ) ) { return false; }
	foreach ( array( 'properties', 'values' ) as $key ) {
		if ( ! is_array( $npc[ $key ] ) || count( $npc[ $key ] ) > 30 ) { return false; }
		$labels = array();
		foreach ( $npc[ $key ] as $field ) {
			if ( ! is_array( $field ) || ! isset( $field['label'], $field['value'] ) || ! is_string( $field['label'] ) ||
				'' === trim( $field['label'] ) || strlen( $field['label'] ) > 200 || in_array( $field['label'], $labels, true ) ||
				( 'properties' === $key ? ! is_string( $field['value'] ) || strlen( $field['value'] ) > 1000 : ! is_int( $field['value'] ) || abs( $field['value'] ) > 1000000 ) ) { return false; }
			$labels[] = $field['label'];
		}
	}
	return true;
}

function ps_pen_paper_can_read_npcs( $scope, $campaign = null ) {
	if ( ! ps_pen_paper_can_access_site() || ! $scope || ! ps_pen_paper_get_character_scope( $scope->ID ) ) { return false; }
	if ( ps_pen_paper_can_manage_character_scope( $scope ) ) { return true; }
	if ( 'pspp_universe' !== $scope->post_type || ! $campaign || 'pspp_campaign' !== $campaign->post_type || ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return false; }
	$link = ps_pen_paper_get_universe_link( $campaign->ID );
	return ! is_wp_error( $link ) && $scope->ID === $link['universe_id'];
}

function ps_pen_paper_get_npc( $scope, $id, $campaign = null ) {
	if ( ! ps_pen_paper_can_read_npcs( $scope, $campaign ) ) { return ps_pen_paper_npc_error(); }
	$post = get_post( $id );
	$state = get_post_meta( $id, '_pspp_npc', true );
	if ( ! $post || 'pspp_npc' !== $post->post_type || 'private' !== $post->post_status || (int) $post->post_parent !== (int) $scope->ID ||
		! ps_pen_paper_valid_npc_state( $state ) || $state['revision'] < 1 ||
		( 'pspp_campaign' === $scope->post_type ? 'figure' !== $state['status'] : 'figure' === $state['status'] ) ||
		( 'proposed' === $state['status'] && ! ps_pen_paper_can_manage_universe( $scope ) ) ) { return ps_pen_paper_npc_error(); }
	return $state;
}

function ps_pen_paper_npc_records( $scope, $campaign = null ) {
	if ( ! ps_pen_paper_can_read_npcs( $scope, $campaign ) ) { return ps_pen_paper_npc_error(); }
	$records = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_npc', 'post_status' => 'private', 'post_parent' => $scope->ID, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
		$state = get_post_meta( $post->ID, '_pspp_npc', true );
		if ( ! ps_pen_paper_valid_npc_state( $state ) ) { return ps_pen_paper_npc_error(); }
		if ( 'pspp_universe' === $scope->post_type && ! ps_pen_paper_can_manage_universe( $scope ) && 'proposed' === $state['status'] ) { continue; }
		$state = ps_pen_paper_get_npc( $scope, $post->ID, $campaign );
		if ( is_wp_error( $state ) ) { return $state; }
		$records[ $post->ID ] = $state;
	}
	return $records;
}

function ps_pen_paper_npc_fields( $text, $numeric ) {
	if ( ! is_string( $text ) || strlen( $text ) > 20000 ) { return ps_pen_paper_npc_error(); }
	$fields = array();
	foreach ( preg_split( '/\r\n|\r|\n/', trim( $text ) ) as $line ) {
		if ( '' === trim( $line ) ) { continue; }
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( 2 !== count( $parts ) || ( $numeric && ( ! preg_match( '/^-?\d{1,7}$/D', $parts[1] ) || abs( (int) $parts[1] ) > 1000000 ) ) ) { return ps_pen_paper_npc_error(); }
		$fields[] = array( 'label' => sanitize_text_field( $parts[0] ), 'value' => $numeric ? (int) $parts[1] : sanitize_text_field( $parts[1] ) );
	}
	return $fields;
}

function ps_pen_paper_npc_transaction( $scopes, $write ) {
	global $wpdb;
	$ids = array_unique( array_map( 'intval', wp_list_pluck( $scopes, 'ID' ) ) );
	sort( $ids, SORT_NUMERIC );
	return ps_pen_paper_atomic_event( $ids[0], function () use ( $ids, $write, $wpdb ) {
		foreach ( $ids as $id ) {
			if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $id ) ) !== $id ) { return ps_pen_paper_npc_error(); }
			clean_post_cache( $id );
		}
		return $write();
	} );
}

function ps_pen_paper_write_npc( $scope, &$id, $before, $after, $kind ) {
	$new = ! $id;
	if ( $new ) {
		$id = ps_pen_paper_insert_record( 'pspp_npc', $scope->ID, array(), false );
		if ( is_wp_error( $id ) ) { return $id; }
	}
	return ps_pen_paper_write_document_event( $scope->ID, $id, '_pspp_npc', $before, $after, array(
		'kind' => $kind, 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0,
	), $new );
}

function ps_pen_paper_save_npc( $scope, $id, $input, $revision, $image_path = null ) {
	if ( ! ps_pen_paper_can_read_npcs( $scope ) || ! ps_pen_paper_can_manage_character_scope( $scope ) || ! is_array( $input ) ) { return ps_pen_paper_npc_error(); }
	foreach ( array( 'kind', 'name', 'description', 'secret', 'scene_id', 'properties', 'values' ) as $key ) {
		if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) { return ps_pen_paper_npc_error(); }
	}
	if ( ! preg_match( '/^\d{1,10}$/D', $input['scene_id'] ) ||
		( isset( $input['remove_image'] ) && ( '1' !== $input['remove_image'] || null !== $image_path ) ) ) { return ps_pen_paper_npc_error(); }
	$properties = ps_pen_paper_npc_fields( $input['properties'], false );
	$values = ps_pen_paper_npc_fields( $input['values'], true );
	if ( is_wp_error( $properties ) || is_wp_error( $values ) ) { return ps_pen_paper_npc_error(); }
	$asset_id = null === $image_path ? 0 : ps_pen_paper_store_private_image( $scope->ID, $image_path );
	if ( is_wp_error( $asset_id ) ) { return $asset_id; }
	$result = ps_pen_paper_npc_transaction( array( $scope ), function () use ( $scope, &$id, $input, $revision, $asset_id, $properties, $values ) {
		$scope = ps_pen_paper_get_character_scope( $scope->ID );
		if ( ! ps_pen_paper_can_read_npcs( $scope ) || ! ps_pen_paper_can_manage_character_scope( $scope ) ) { return ps_pen_paper_npc_error(); }
		if ( $id ) { clean_post_cache( $id ); }
		$before = $id ? ps_pen_paper_get_npc( $scope, $id ) : ps_pen_paper_npc_default( $scope );
		if ( is_wp_error( $before ) || ! is_string( $revision ) || (string) $before['revision'] !== $revision ) { return ps_pen_paper_npc_error(); }
		$after = $before;
		$after['revision']++;
		$after['npc'] = array(
			'kind' => $input['kind'], 'name' => sanitize_text_field( $input['name'] ), 'description' => sanitize_textarea_field( $input['description'] ),
			'secret' => sanitize_textarea_field( $input['secret'] ), 'scene_id' => (int) $input['scene_id'],
			'asset_id' => $asset_id ?: ( isset( $input['remove_image'] ) ? 0 : ( $before['npc'] ? $before['npc']['asset_id'] : 0 ) ),
			'properties' => $properties, 'values' => $values,
		);
		$loot_id = isset( $input['loot_table_id'] ) ? $input['loot_table_id'] : (string) ( isset( $before['npc']['loot_table_id'] ) ? $before['npc']['loot_table_id'] : 0 );
		if ( ! is_string( $loot_id ) || ! preg_match( '/^(0|[1-9]\d{0,10})$/D', $loot_id ) ) { return ps_pen_paper_npc_error(); }
		$after['npc']['loot_table_id'] = (int) $loot_id;
		if ( $after['npc']['loot_table_id'] && is_wp_error( ps_pen_paper_get_loot_table( $scope, $after['npc']['loot_table_id'] ) ) ) { return ps_pen_paper_npc_error(); }
		if ( ! ps_pen_paper_valid_npc_state( $after ) || ( $after['npc']['scene_id'] && is_wp_error( ps_pen_paper_get_scene( $scope, $after['npc']['scene_id'] ) ) ) ) { return ps_pen_paper_npc_error(); }
		return ps_pen_paper_write_npc( $scope, $id, $before, $after, 'npc_saved' );
	} );
	if ( is_int( $id ) && $id ) { clean_post_cache( $id ); }
	if ( is_wp_error( $result ) && $asset_id && 'event_recovery_required' !== $result->get_error_code() && ! wp_delete_post( $asset_id, true ) ) {
		return new WP_Error( 'cleanup_failed', __( 'Das nicht zugeordnete Figurenbild konnte nicht entfernt werden.', 'ps-pen-paper' ) );
	}
	return is_wp_error( $result ) ? $result : $id;
}

function ps_pen_paper_transfer_npc( $campaign, $source_id, $revision, $confirmation, $offer = false ) {
	if ( ! ps_pen_paper_can_read_npcs( $campaign ) || 'pspp_campaign' !== $campaign->post_type || 'confirmed' !== $confirmation ) { return ps_pen_paper_npc_error(); }
	$link = ps_pen_paper_get_universe_link( $campaign->ID );
	$universe = ! is_wp_error( $link ) && $link['universe_id'] ? ps_pen_paper_get_universe( $link['universe_id'] ) : null;
	if ( ! $universe ) { return ps_pen_paper_npc_error(); }
	$id = 0;
	$asset_id = 0;
	$result = ps_pen_paper_npc_transaction( array( $campaign, $universe ), function () use ( $campaign, $universe, $source_id, $revision, $offer, &$id, &$asset_id ) {
		$campaign = ps_pen_paper_get_campaign( $campaign->ID );
		$universe = ps_pen_paper_get_universe( $universe->ID );
		$link = $campaign ? ps_pen_paper_get_universe_link( $campaign->ID ) : null;
		if ( ! ps_pen_paper_can_read_npcs( $campaign ) || ! $universe || is_wp_error( $link ) || $link['universe_id'] !== $universe->ID ) { return ps_pen_paper_npc_error(); }
		$source = $offer ? $campaign : $universe;
		$target = $offer ? $universe : $campaign;
		clean_post_cache( $source_id );
		$state = ps_pen_paper_get_npc( $source, $source_id, $campaign );
		if ( is_wp_error( $state ) || ! is_string( $revision ) || (string) $state['revision'] !== $revision || ( ! $offer && 'template' !== $state['status'] ) ) { return ps_pen_paper_npc_error(); }
		if ( $offer ) {
			foreach ( get_posts( array( 'post_type' => 'pspp_npc', 'post_status' => 'private', 'post_parent' => $universe->ID, 'posts_per_page' => -1 ) ) as $post ) {
				$existing = get_post_meta( $post->ID, '_pspp_npc', true );
				if ( ! ps_pen_paper_valid_npc_state( $existing ) ) { return ps_pen_paper_npc_error(); }
				if ( $existing['source'] && $existing['source']['record_id'] === $source_id && $existing['source']['scope_id'] === $campaign->ID ) {
					return new WP_Error( 'npc_already_offered', __( 'Diese Figur wurde bereits ins Universum aufgenommen oder vorgeschlagen. Vorlagen werden nicht automatisch durch spätere Figurenänderungen überschrieben.', 'ps-pen-paper' ) );
				}
			}
		}
		$after = $state;
		$after['revision'] = 1;
		$after['status'] = $offer ? ( ps_pen_paper_can_manage_universe( $universe ) ? 'template' : 'proposed' ) : 'figure';
		$after['source'] = array( 'scope_id' => (int) $source->ID, 'record_id' => (int) $source_id, 'revision' => $state['revision'] );
		$after['npc']['scene_id'] = 0;
		$after['npc']['loot_table_id'] = 0;
		if ( $state['npc']['asset_id'] ) {
			if ( is_wp_error( ps_pen_paper_private_image_data( $source, $state['npc']['asset_id'] ) ) ) { return ps_pen_paper_npc_error(); }
			$asset = get_post( $state['npc']['asset_id'] );
			$asset_id = wp_insert_post( wp_slash( array(
				'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $target->ID,
				'post_title' => 'PS Pen & Paper NPC Image', 'post_author' => get_current_user_id(),
				'post_mime_type' => $asset->post_mime_type, 'post_content' => $asset->post_content,
			) ), true );
			if ( is_wp_error( $asset_id ) || ! $asset_id ) { return ps_pen_paper_npc_error(); }
			$after['npc']['asset_id'] = $asset_id;
		}
		return ps_pen_paper_write_npc( $target, $id, ps_pen_paper_npc_default( $target ), $after, $offer ? 'npc_offered' : 'npc_copied' );
	} );
	foreach ( array( $id, $asset_id ) as $record_id ) { if ( is_int( $record_id ) && $record_id ) { clean_post_cache( $record_id ); } }
	return is_wp_error( $result ) ? $result : $id;
}

function ps_pen_paper_review_npc( $universe, $id, $revision, $confirmation ) {
	if ( ! ps_pen_paper_can_read_npcs( $universe ) || ! ps_pen_paper_can_manage_universe( $universe ) || 'confirmed' !== $confirmation ) { return ps_pen_paper_npc_error(); }
	$result = ps_pen_paper_npc_transaction( array( $universe ), function () use ( $universe, &$id, $revision ) {
		$universe = ps_pen_paper_get_universe( $universe->ID );
		if ( ! $universe || ! ps_pen_paper_can_manage_universe( $universe ) ) { return ps_pen_paper_npc_error(); }
		clean_post_cache( $id );
		$before = ps_pen_paper_get_npc( $universe, $id );
		if ( is_wp_error( $before ) || 'proposed' !== $before['status'] || ! is_string( $revision ) || (string) $before['revision'] !== $revision ) { return ps_pen_paper_npc_error(); }
		$after = $before;
		$after['revision']++;
		$after['status'] = 'template';
		return ps_pen_paper_write_npc( $universe, $id, $before, $after, 'npc_approved' );
	} );
	clean_post_cache( $id );
	return $result;
}
