<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_register_folio_type() {
	register_post_type( 'pspp_folio', array(
		'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
		'show_ui' => false, 'show_in_rest' => false, 'supports' => false,
		'capability_type' => array( 'pspp_folio', 'pspp_folios' ), 'map_meta_cap' => true,
	) );
}

function ps_pen_paper_folio_types() {
	return array(
		'chapter' => __( 'Kapitel', 'ps-pen-paper' ), 'handout' => __( 'Handout', 'ps-pen-paper' ),
		'person' => __( 'Person', 'ps-pen-paper' ), 'place' => __( 'Ort', 'ps-pen-paper' ),
		'note' => __( 'Notiz', 'ps-pen-paper' ),
	);
}

function ps_pen_paper_folio_error() {
	return new WP_Error( 'invalid_folio', __( 'Dieser Mappeneintrag ist ungültig oder nicht verfügbar. Bitte prüfe die Spielleitermappe.', 'ps-pen-paper' ) );
}

function ps_pen_paper_folio_default() {
	return array( 'revision' => 0, 'draft' => array(
		'type' => 'note', 'title' => '', 'text' => '', 'secret' => '', 'order' => 0,
		'chapter_id' => 0, 'scene_id' => 0, 'scene_ids' => array(), 'asset_id' => 0,
	), 'published' => null );
}

function ps_pen_paper_validate_folio_snapshot( $value, $published = false ) {
	if ( ! is_array( $value ) || ! isset( $value['title'], $value['text'], $value['asset_id'] ) ||
		! is_string( $value['title'] ) || '' === trim( $value['title'] ) || strlen( $value['title'] ) > 800 ||
		! is_string( $value['text'] ) || strlen( $value['text'] ) > 20000 ||
		! is_int( $value['asset_id'] ) || $value['asset_id'] < 0 ) { return false; }
	if ( $published ) {
		return array( 'title', 'text', 'asset_id' ) === array_keys( $value );
	}
	if ( ! isset( $value['type'], $value['secret'], $value['order'], $value['chapter_id'], $value['scene_id'], $value['scene_ids'] ) ||
		! is_string( $value['type'] ) || ! isset( ps_pen_paper_folio_types()[ $value['type'] ] ) ||
		! is_string( $value['secret'] ) || strlen( $value['secret'] ) > 20000 ||
		! is_int( $value['order'] ) || $value['order'] < 0 || $value['order'] > 9999 ||
		! is_int( $value['chapter_id'] ) || $value['chapter_id'] < 0 ||
		! is_int( $value['scene_id'] ) || $value['scene_id'] < 0 ||
		! is_array( $value['scene_ids'] ) || count( $value['scene_ids'] ) > 100 ||
		count( $value['scene_ids'] ) !== count( array_unique( $value['scene_ids'], SORT_REGULAR ) ) ) { return false; }
	foreach ( $value['scene_ids'] as $id ) { if ( ! is_int( $id ) || $id < 1 ) { return false; } }
	return true;
}

function ps_pen_paper_get_folio( $campaign, $id ) {
	$post = get_post( $id );
	$value = get_post_meta( $id, '_pspp_folio', true );
	if ( ! $post || 'pspp_folio' !== $post->post_type || 'private' !== $post->post_status ||
		(int) $post->post_parent !== (int) $campaign->ID ) { return ps_pen_paper_folio_error(); }
	return ps_pen_paper_validate_folio_state( $value );
}

function ps_pen_paper_validate_folio_state( $value ) {
	if ( ! is_array( $value ) || ! isset( $value['revision'], $value['draft'] ) || ! array_key_exists( 'published', $value ) ||
		! is_int( $value['revision'] ) || $value['revision'] < 1 ||
		! ps_pen_paper_validate_folio_snapshot( $value['draft'] ) ||
		( null !== $value['published'] && ( 'handout' !== $value['draft']['type'] || ! ps_pen_paper_validate_folio_snapshot( $value['published'], true ) ) ) ) {
		return ps_pen_paper_folio_error();
	}
	return $value;
}

function ps_pen_paper_folio_records( $campaign ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Die Spielleitermappe ist nur für die Spielleitung verfügbar.', 'ps-pen-paper' ) );
	}
	$records = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_folio', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
		$value = ps_pen_paper_get_folio( $campaign, $post->ID );
		if ( is_wp_error( $value ) ) { return $value; }
		$records[ $post->ID ] = $value;
	}
	uasort( $records, function ( $a, $b ) { return $a['draft']['order'] <=> $b['draft']['order']; } );
	return $records;
}

function ps_pen_paper_adventure_plan( $campaign ) {
	$records = ps_pen_paper_folio_records( $campaign );
	if ( is_wp_error( $records ) ) { return $records; }
	$selection = ps_pen_paper_map_selection( $campaign );
	if ( is_wp_error( $selection ) ) { return $selection; }
	$scenes = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_scene', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
		$scene = ps_pen_paper_get_scene( $campaign, $post->ID );
		if ( is_wp_error( $scene ) ) { return $scene; }
		$scenes[ $post->ID ] = $scene;
	}
	return array( 'records' => $records, 'scenes' => $scenes, 'selection' => $selection );
}

function ps_pen_paper_save_folio( $campaign, $id, $input, $revision, $image_path = null ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die Spielleitung darf die Mappe bearbeiten.', 'ps-pen-paper' ) );
	}
	$before = $id ? ps_pen_paper_get_folio( $campaign, $id ) : ps_pen_paper_folio_default();
	if ( is_wp_error( $before ) ) { return $before; }
	if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision ) {
		return new WP_Error( 'conflict', __( 'Der Mappeneintrag wurde inzwischen geändert. Bitte lade ihn neu; nichts wurde überschrieben.', 'ps-pen-paper' ) );
	}
	if ( ! is_array( $input ) ) { return ps_pen_paper_folio_error(); }
	foreach ( array( 'type', 'title', 'text', 'secret', 'order', 'chapter_id', 'scene_id' ) as $key ) {
		if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) { return ps_pen_paper_folio_error(); }
	}
	foreach ( array( 'order', 'chapter_id', 'scene_id' ) as $key ) {
		if ( ! preg_match( '/^\d{1,10}$/D', $input[ $key ] ) ) { return ps_pen_paper_folio_error(); }
	}
	$draft = array(
		'type' => $input['type'], 'title' => sanitize_text_field( $input['title'] ),
		'text' => sanitize_textarea_field( $input['text'] ), 'secret' => sanitize_textarea_field( $input['secret'] ),
		'order' => (int) $input['order'], 'chapter_id' => (int) $input['chapter_id'], 'scene_id' => (int) $input['scene_id'],
		'scene_ids' => array(), 'asset_id' => $before['draft']['asset_id'],
	);
	if ( $id && $draft['type'] !== $before['draft']['type'] ) { return ps_pen_paper_folio_error(); }
	if ( $draft['chapter_id'] ) {
		$chapter = ps_pen_paper_get_folio( $campaign, $draft['chapter_id'] );
		if ( is_wp_error( $chapter ) || 'chapter' !== $chapter['draft']['type'] || 'chapter' === $draft['type'] ) { return ps_pen_paper_folio_error(); }
	}
	if ( $draft['scene_id'] && is_wp_error( ps_pen_paper_get_scene( $campaign, $draft['scene_id'] ) ) ) { return ps_pen_paper_folio_error(); }
	if ( isset( $input['scenes'] ) ) {
		if ( 'chapter' !== $draft['type'] || ! is_array( $input['scenes'] ) ) { return ps_pen_paper_folio_error(); }
		$ordered = array();
		foreach ( $input['scenes'] as $scene_id => $position ) {
			if ( ! ctype_digit( (string) $scene_id ) || ! is_string( $position ) ) { return ps_pen_paper_folio_error(); }
			if ( '' === $position ) { continue; }
			if ( ! preg_match( '/^\d{1,4}$/D', $position ) || is_wp_error( ps_pen_paper_get_scene( $campaign, (int) $scene_id ) ) ) { return ps_pen_paper_folio_error(); }
			$ordered[ $scene_id ] = (int) $position;
		}
		if ( count( $ordered ) > 100 ) { return ps_pen_paper_folio_error(); }
		asort( $ordered, SORT_NUMERIC );
		$draft['scene_ids'] = array_map( 'intval', array_keys( $ordered ) );
	}
	if ( ! ps_pen_paper_validate_folio_snapshot( $draft ) || ( null !== $image_path && 'handout' !== $draft['type'] ) ) { return ps_pen_paper_folio_error(); }
	if ( isset( $input['remove_image'] ) ) {
		if ( '1' !== $input['remove_image'] || null !== $image_path ) { return ps_pen_paper_folio_error(); }
		$draft['asset_id'] = 0;
	}
	$asset_id = 0;
	if ( null !== $image_path ) {
		$asset_id = ps_pen_paper_store_private_image( $campaign->ID, $image_path );
		if ( is_wp_error( $asset_id ) ) { return $asset_id; }
		$draft['asset_id'] = $asset_id;
	}
	$after = array( 'revision' => $before['revision'] + 1, 'draft' => $draft, 'published' => $before['published'] );
	$new = ! $id;
	if ( $new ) { $id = wp_insert_post( array( 'post_type' => 'pspp_folio', 'post_status' => 'draft', 'post_parent' => $campaign->ID, 'post_title' => 'PS Pen & Paper Folio' ), true ); }
	$result = is_wp_error( $id ) || ! $id ? new WP_Error( 'save_failed', __( 'Der Mappeneintrag konnte nicht angelegt werden.', 'ps-pen-paper' ) )
		: ps_pen_paper_folio_event( $campaign, $id, $before, $after, 'folio_saved', $new );
	if ( is_wp_error( $result ) ) {
		if ( 'event_recovery_required' === $result->get_error_code() ) { return $result; }
		if ( $asset_id && ! wp_delete_post( $asset_id, true ) ) { return new WP_Error( 'cleanup_failed', __( 'Ein nicht zugeordnetes Handoutbild konnte nicht entfernt werden.', 'ps-pen-paper' ) ); }
		return $new && is_int( $id ) && $id ? ps_pen_paper_discard_record( $id, $result ) : $result;
	}
	return $id;
}

function ps_pen_paper_folio_event( $campaign, $id, $before, $after, $kind, $new = false ) {
	return ps_pen_paper_save_document_event( $campaign->ID, $id, '_pspp_folio', $before, $after, array(
		'kind' => $kind, 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(),
		'rules_revision' => ps_pen_paper_get_rules( $campaign->ID )['revision'],
	), $new );
}

function ps_pen_paper_publish_folio( $campaign, $id, $revision, $confirmed, $withdraw = false ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die Spielleitung darf Handouts freigeben.', 'ps-pen-paper' ) );
	}
	$before = ps_pen_paper_get_folio( $campaign, $id );
	if ( is_wp_error( $before ) ) { return $before; }
	if ( 'handout' !== $before['draft']['type'] || ! is_string( $revision ) || (string) $before['revision'] !== $revision || 'confirmed' !== $confirmed ) {
		return new WP_Error( 'conflict', __( 'Bitte lade den aktuellen Handoutstand und bestätige die Freigabe beziehungsweise Rücknahme.', 'ps-pen-paper' ) );
	}
	$after = $before;
	$after['revision']++;
	$after['published'] = $withdraw ? null : array(
		'title' => $before['draft']['title'], 'text' => $before['draft']['text'], 'asset_id' => $before['draft']['asset_id'],
	);
	return ps_pen_paper_folio_event( $campaign, $id, $before, $after, $withdraw ? 'folio_withdrawn' : 'folio_published' );
}

function ps_pen_paper_visible_handouts( $campaign ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Diese Handouts sind für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	$handouts = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_folio', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
		$value = ps_pen_paper_get_folio( $campaign, $post->ID );
		if ( is_wp_error( $value ) ) { return $value; }
		if ( null !== $value['published'] ) {
			$handouts[ $post->ID ] = array_intersect_key( $value['published'], array_flip( array( 'title', 'text', 'asset_id' ) ) );
		}
	}
	return $handouts;
}
