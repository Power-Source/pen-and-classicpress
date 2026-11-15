<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_register_wiki_type() {
	register_post_type( 'pspp_wiki_entry', array( 'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
		'show_ui' => false, 'show_in_rest' => false, 'supports' => false, 'capability_type' => array( 'pspp_wiki_entry', 'pspp_wiki_entries' ), 'map_meta_cap' => true ) );
}

function ps_pen_paper_wiki_categories() {
	return array( 'people' => __( 'Personen', 'ps-pen-paper' ), 'enemies' => __( 'Gegner', 'ps-pen-paper' ),
		'places' => __( 'Orte', 'ps-pen-paper' ), 'factions' => __( 'Fraktionen', 'ps-pen-paper' ),
		'items' => __( 'Gegenstände', 'ps-pen-paper' ), 'rules' => __( 'Regeln', 'ps-pen-paper' ) );
}

function ps_pen_paper_wiki_available() {
	return class_exists( 'Wiki', false ) && post_type_exists( 'psource_wiki' ) && taxonomy_exists( 'psource_wiki_category' ) &&
		is_object_in_taxonomy( 'psource_wiki', 'psource_wiki_category' );
}

function ps_pen_paper_can_configure_wiki( $universe ) {
	if ( ! ps_pen_paper_wiki_available() || ! ps_pen_paper_can_manage_wiki( $universe ) ) { return false; }
	$type = get_post_type_object( 'psource_wiki' ); $taxonomy = get_taxonomy( 'psource_wiki_category' );
	return current_user_can( $type->cap->publish_posts ) && current_user_can( $taxonomy->cap->manage_terms );
}

function ps_pen_paper_wiki_error() {
	return new WP_Error( 'wiki_conflict', __( 'Das Lexikon ist nicht verfügbar, wurde zwischenzeitlich geändert oder der Zugriff fehlt. Bitte lade den aktuellen Stand neu. Direkt im Wiki redaktionell geänderte Artikel werden nicht überschrieben.', 'ps-pen-paper' ) );
}

function ps_pen_paper_can_manage_wiki( $universe ) {
	return ps_pen_paper_can_access_site() && $universe && 'pspp_universe' === $universe->post_type && ps_pen_paper_can_manage_universe( $universe );
}

function ps_pen_paper_wiki_default() {
	return array( 'revision' => 0, 'enabled' => false, 'name' => '', 'root_id' => 0, 'categories' => array() );
}

function ps_pen_paper_valid_wiki_config( $state ) {
	if ( ! is_array( $state ) || ! isset( $state['revision'], $state['enabled'], $state['name'], $state['root_id'], $state['categories'] ) ||
		! is_int( $state['revision'] ) || $state['revision'] < 0 || ! is_bool( $state['enabled'] ) || ! is_string( $state['name'] ) ||
		! is_int( $state['root_id'] ) || ! is_array( $state['categories'] ) ) { return false; }
	if ( ! $state['revision'] ) { return $state === ps_pen_paper_wiki_default(); }
	if ( $state['root_id'] < 1 || '' === trim( $state['name'] ) || strlen( $state['name'] ) > 200 ||
		array_keys( $state['categories'] ) !== array_keys( ps_pen_paper_wiki_categories() ) ) { return false; }
	foreach ( $state['categories'] as $category ) {
		if ( ! is_array( $category ) || ! isset( $category['id'], $category['name'] ) || ! is_int( $category['id'] ) || $category['id'] < 1 ||
			! is_string( $category['name'] ) || '' === trim( $category['name'] ) || strlen( $category['name'] ) > 200 ) { return false; }
	}
	return true;
}

function ps_pen_paper_wiki_config( $universe ) {
	if ( ! ps_pen_paper_can_manage_wiki( $universe ) ) { return ps_pen_paper_wiki_error(); }
	$state = get_post_meta( $universe->ID, '_pspp_wiki', true );
	if ( '' === $state ) { return ps_pen_paper_wiki_default(); }
	return ps_pen_paper_valid_wiki_config( $state ) ? $state : ps_pen_paper_wiki_error();
}

function ps_pen_paper_wiki_entry_default() {
	return array( 'revision' => 0, 'source_id' => 0, 'source_revision' => 0, 'wiki_id' => 0, 'title' => '', 'content' => '', 'category' => 'people', 'fingerprint' => '', 'published' => false );
}

function ps_pen_paper_valid_wiki_entry( $state ) {
	if ( ! is_array( $state ) || ! isset( $state['revision'], $state['source_id'], $state['source_revision'], $state['wiki_id'], $state['title'], $state['content'], $state['category'], $state['fingerprint'], $state['published'] ) ) { return false; }
	foreach ( array( 'revision', 'source_id', 'source_revision', 'wiki_id' ) as $key ) { if ( ! is_int( $state[ $key ] ) || $state[ $key ] < 0 ) { return false; } }
	if ( ! $state['revision'] ) { return $state === ps_pen_paper_wiki_entry_default(); }
	return $state['wiki_id'] > 0 && is_string( $state['title'] ) && '' !== trim( $state['title'] ) && strlen( $state['title'] ) <= 200 &&
		is_string( $state['content'] ) && '' !== trim( $state['content'] ) && strlen( $state['content'] ) <= 20000 &&
		is_string( $state['category'] ) && isset( ps_pen_paper_wiki_categories()[ $state['category'] ] ) &&
		is_string( $state['fingerprint'] ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $state['fingerprint'] ) && is_bool( $state['published'] ) &&
		( $state['source_id'] ? $state['source_revision'] > 0 : 0 === $state['source_revision'] );
}

function ps_pen_paper_get_wiki_entry( $universe, $id ) {
	if ( ! ps_pen_paper_can_manage_wiki( $universe ) ) { return ps_pen_paper_wiki_error(); }
	$post = ps_pen_paper_record_post( $id ); $state = get_post_meta( $id, '_pspp_wiki_entry', true );
	return $post && 'pspp_wiki_entry' === $post->post_type && 'private' === $post->post_status && (int) $post->post_parent === (int) $universe->ID &&
		ps_pen_paper_valid_wiki_entry( $state ) && $state['revision'] > 0 ? $state : ps_pen_paper_wiki_error();
}

function ps_pen_paper_wiki_entries( $universe ) {
	if ( ! ps_pen_paper_can_manage_wiki( $universe ) ) { return ps_pen_paper_wiki_error(); }
	$entries = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_wiki_entry', 'post_status' => 'private', 'post_parent' => $universe->ID, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
		$entry = ps_pen_paper_get_wiki_entry( $universe, $post->ID );
		if ( is_wp_error( $entry ) ) { return $entry; }
		$entries[ $post->ID ] = $entry;
	}
	return $entries;
}

function ps_pen_paper_wiki_term_valid( $id, $name, $parent ) {
	clean_term_cache( $id, 'psource_wiki_category' );
	$term = get_term( $id, 'psource_wiki_category' );
	return $term && ! is_wp_error( $term ) && $name === $term->name && $parent === (int) $term->parent;
}

function ps_pen_paper_wiki_config_terms_valid( $state ) {
	if ( ! ps_pen_paper_wiki_term_valid( $state['root_id'], $state['name'], 0 ) ) { return false; }
	foreach ( $state['categories'] as $category ) {
		if ( ! ps_pen_paper_wiki_term_valid( $category['id'], $category['name'], $state['root_id'] ) ) { return false; }
	}
	return true;
}

function ps_pen_paper_wiki_transaction( $universe, $write ) {
	global $wpdb;
	return ps_pen_paper_atomic_event( $universe->ID, $write, array( $wpdb->terms, $wpdb->term_taxonomy, $wpdb->term_relationships, $wpdb->termmeta ) );
}

function ps_pen_paper_configure_wiki( $universe, $input, $revision, $confirmation ) {
	if ( ! ps_pen_paper_can_configure_wiki( $universe ) || 'confirmed' !== $confirmation || ! is_array( $input ) ||
		! isset( $input['name'], $input['categories'], $input['enabled'] ) || ! is_string( $input['name'] ) || ! is_array( $input['categories'] ) ||
		! in_array( $input['enabled'], array( '0', '1' ), true ) ) { return ps_pen_paper_wiki_error(); }
	$term_ids = array();
	try {
		return ps_pen_paper_wiki_transaction( $universe, function () use ( $universe, $input, $revision, &$term_ids ) {
			$universe = ps_pen_paper_get_universe( $universe->ID ); $before = ps_pen_paper_wiki_config( $universe );
			if ( ! ps_pen_paper_can_configure_wiki( $universe ) || is_wp_error( $before ) || ! is_string( $revision ) || (string) $before['revision'] !== $revision ||
				( ! $before['revision'] && '0' === $input['enabled'] ) || ( $before['revision'] && ! ps_pen_paper_wiki_config_terms_valid( $before ) ) ) { return ps_pen_paper_wiki_error(); }
			$after = $before; $after['revision']++; $after['enabled'] = '1' === $input['enabled']; $after['name'] = sanitize_text_field( $input['name'] );
			$names = array( 'root' => $after['name'] );
			foreach ( ps_pen_paper_wiki_categories() as $key => $unused ) {
				if ( ! isset( $input['categories'][ $key ] ) || ! is_string( $input['categories'][ $key ] ) ) { return ps_pen_paper_wiki_error(); }
				$names[ $key ] = sanitize_text_field( $input['categories'][ $key ] );
			}
			if ( count( array_unique( $names ) ) !== count( $names ) ) { return ps_pen_paper_wiki_error(); }
			foreach ( $names as $key => $name ) {
				if ( '' === trim( $name ) || strlen( $name ) > 200 ) { return ps_pen_paper_wiki_error(); }
				$id = 'root' === $key ? $before['root_id'] : ( isset( $before['categories'][ $key ] ) ? $before['categories'][ $key ]['id'] : 0 );
				$parent = 'root' === $key ? 0 : $after['root_id'];
				$result = $id ? wp_update_term( $id, 'psource_wiki_category', array( 'name' => $name, 'parent' => $parent ) )
					: wp_insert_term( $name, 'psource_wiki_category', array( 'parent' => $parent, 'slug' => 'lexikon-' . wp_generate_uuid4() ) );
				if ( is_wp_error( $result ) ) { return $result; }
				$id = (int) $result['term_id']; $term_ids[] = $id;
				if ( ! ps_pen_paper_wiki_term_valid( $id, $name, $parent ) ) { return ps_pen_paper_wiki_error(); }
				if ( 'root' === $key ) { $after['root_id'] = $id; }
				else { $after['categories'][ $key ] = array( 'id' => $id, 'name' => $name ); }
			}
			if ( ! ps_pen_paper_valid_wiki_config( $after ) ) { return ps_pen_paper_wiki_error(); }
			return ps_pen_paper_write_document_event( $universe->ID, $universe->ID, '_pspp_wiki', $before, $after,
				array( 'kind' => 'wiki_configured', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
		} );
	} finally { if ( $term_ids ) { clean_term_cache( $term_ids, 'psource_wiki_category' ); } }
}

function ps_pen_paper_wiki_fingerprint( $wiki_id, $universe_id, $entry_id ) {
	clean_post_cache( $wiki_id );
	$post = get_post( $wiki_id );
	if ( ! $post || 'psource_wiki' !== $post->post_type || ! in_array( $post->post_status, array( 'draft', 'publish' ), true ) ||
		array( $universe_id, $entry_id ) !== get_post_meta( $wiki_id, '_pspp_wiki_owner', true ) ) { return ps_pen_paper_wiki_error(); }
	$terms = wp_get_object_terms( $wiki_id, 'psource_wiki_category', array( 'fields' => 'ids', 'orderby' => 'term_id' ) );
	if ( is_wp_error( $terms ) ) { return $terms; }
	return hash( 'sha256', serialize( array( $post->post_title, $post->post_content, $post->post_excerpt, $post->post_status, $post->post_parent, $post->post_password, $terms ) ) );
}

function ps_pen_paper_wiki_public_html( $text ) {
	// Escaping HTML alone would still let the_content execute shortcodes.
	return wpautop( str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), esc_html( $text ) ) );
}

function ps_pen_paper_valid_wiki_links( $state ) {
	if ( ! is_array( $state ) || array( 'revision', 'links' ) !== array_keys( $state ) ||
		! is_int( $state['revision'] ) || $state['revision'] < 0 || ! is_array( $state['links'] ) ||
		count( $state['links'] ) > 200 || ( ! $state['revision'] && $state['links'] ) ) { return false; }
	foreach ( $state['links'] as $target => $entry_id ) {
		if ( ! is_string( $target ) || ! preg_match( '/^(?:scene:[1-9]\d{0,10}|field:[a-f0-9-]{36})$/D', $target ) ||
			! is_int( $entry_id ) || $entry_id < 1 ) { return false; }
		if ( 0 === strpos( $target, 'field:' ) && ! wp_is_uuid( substr( $target, 6 ) ) ) { return false; }
	}
	return true;
}

function ps_pen_paper_wiki_links_state( $campaign ) {
	$state = get_post_meta( $campaign->ID, '_pspp_wiki_links', true );
	if ( '' === $state ) { return array( 'revision' => 0, 'links' => array() ); }
	return ps_pen_paper_valid_wiki_links( $state ) && $state['revision'] > 0 ? $state : ps_pen_paper_wiki_error();
}

function ps_pen_paper_wiki_links( $campaign ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return ps_pen_paper_wiki_error(); }
	return ps_pen_paper_wiki_links_state( $campaign );
}

function ps_pen_paper_public_wiki_link_entry( $campaign, $entry_id ) {
	if ( ! ps_pen_paper_wiki_available() ) { return ps_pen_paper_wiki_error(); }
	$link = ps_pen_paper_get_universe_link( $campaign->ID );
	if ( is_wp_error( $link ) || ! $link['universe_id'] ) { return ps_pen_paper_wiki_error(); }
	$post = ps_pen_paper_record_post( $entry_id );
	$state = get_post_meta( $entry_id, '_pspp_wiki_entry', true );
	if ( ! $post || 'pspp_wiki_entry' !== $post->post_type || 'private' !== $post->post_status ||
		(int) $post->post_parent !== $link['universe_id'] || ! ps_pen_paper_valid_wiki_entry( $state ) ) { return ps_pen_paper_wiki_error(); }
	$wiki = get_post( $state['wiki_id'] );
	if ( ! $wiki || 'psource_wiki' !== $wiki->post_type || 'publish' !== $wiki->post_status || '' !== $wiki->post_password ||
		array( $link['universe_id'], $entry_id ) !== get_post_meta( $wiki->ID, '_pspp_wiki_owner', true ) ) { return ps_pen_paper_wiki_error(); }
	return array( 'id' => (int) $wiki->ID, 'title' => $wiki->post_title, 'url' => get_permalink( $wiki->ID ) );
}

function ps_pen_paper_wiki_link_for_target( $campaign, $target ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) ) { return ps_pen_paper_wiki_error(); }
	if ( is_string( $target ) && 0 === strpos( $target, 'scene:' ) && ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		$selection = ps_pen_paper_map_selection( $campaign );
		$scene = ! is_wp_error( $selection ) && $selection['scene_id'] ? ps_pen_paper_get_scene( $campaign, $selection['scene_id'] ) : null;
		if ( ! $scene || is_wp_error( $scene ) || ! $scene['published'] || $target !== 'scene:' . $selection['scene_id'] ) { return null; }
	}
	$state = ps_pen_paper_wiki_links_state( $campaign );
	if ( is_wp_error( $state ) ) { return $state; }
	if ( ! is_string( $target ) || ! isset( $state['links'][ $target ] ) ) { return null; }
	return ps_pen_paper_public_wiki_link_entry( $campaign, $state['links'][ $target ] );
}

function ps_pen_paper_save_wiki_link( $campaign, $target, $entry_id, $revision, $rules_revision ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ||
		! is_string( $target ) || ! is_int( $entry_id ) || $entry_id < 0 ) { return ps_pen_paper_wiki_error(); }
	return ps_pen_paper_atomic_event( $campaign->ID, function () use ( $campaign, $target, $entry_id, $revision, $rules_revision ) {
		$campaign = ps_pen_paper_get_campaign( $campaign->ID );
		if ( ! $campaign || ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return ps_pen_paper_wiki_error(); }
		$before = ps_pen_paper_wiki_links( $campaign ); $rules = ps_pen_paper_get_rules( $campaign->ID );
		if ( is_wp_error( $before ) || ! is_string( $revision ) || (string) $before['revision'] !== $revision ||
			! is_string( $rules_revision ) || (string) $rules['revision'] !== $rules_revision ) { return ps_pen_paper_wiki_error(); }
		if ( $entry_id ) {
			if ( 0 === strpos( $target, 'scene:' ) ) {
				$id = substr( $target, 6 );
				if ( ! preg_match( '/^[1-9]\d{0,10}$/D', $id ) || is_wp_error( ps_pen_paper_get_scene( $campaign, (int) $id ) ) ) { return ps_pen_paper_wiki_error(); }
			} elseif ( 0 === strpos( $target, 'field:' ) ) {
				if ( ! in_array( substr( $target, 6 ), array_column( $rules['fields'], 'id' ), true ) ) { return ps_pen_paper_wiki_error(); }
			} else { return ps_pen_paper_wiki_error(); }
			if ( is_wp_error( ps_pen_paper_public_wiki_link_entry( $campaign, $entry_id ) ) ) { return ps_pen_paper_wiki_error(); }
		} elseif ( ! isset( $before['links'][ $target ] ) ) { return ps_pen_paper_wiki_error(); }
		$after = $before; $after['revision']++;
		if ( $entry_id ) { $after['links'][ $target ] = $entry_id; } else { unset( $after['links'][ $target ] ); }
		if ( ! ps_pen_paper_valid_wiki_links( $after ) ) { return ps_pen_paper_wiki_error(); }
		return ps_pen_paper_write_document_event( $campaign->ID, $campaign->ID, '_pspp_wiki_links', $before, $after,
			array( 'kind' => 'wiki_links_saved', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => $rules['revision'] ), false );
	} );
}

function ps_pen_paper_save_wiki_entry( $universe, $id, $input, $revision, $config_revision, $confirmation, $publish = false ) {
	if ( ! ps_pen_paper_wiki_available() || ! ps_pen_paper_can_manage_wiki( $universe ) || 'confirmed' !== $confirmation || ! is_array( $input ) ) { return ps_pen_paper_wiki_error(); }
	foreach ( array( 'source_id', 'source_revision', 'title', 'content', 'category' ) as $key ) {
		if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) { return ps_pen_paper_wiki_error(); }
	}
	if ( ! preg_match( '/^(0|[1-9]\d{0,10})$/D', $input['source_id'] ) || ! preg_match( '/^(0|[1-9]\d{0,10})$/D', $input['source_revision'] ) ) { return ps_pen_paper_wiki_error(); }
	$wiki_id = 0; $term_ids = array(); $notify = false;
	$notifications = array( Wiki::get_instance()->notifications, 'send_notifications' );
	$notification_priority = has_action( 'pre_post_update', $notifications );
	// PS Wiki otherwise mails subscribers before the transaction can commit.
	if ( false !== $notification_priority ) { remove_action( 'pre_post_update', $notifications, $notification_priority ); }
	try {
		$result = ps_pen_paper_wiki_transaction( $universe, function () use ( $universe, &$id, $input, $revision, $config_revision, $publish, &$wiki_id, &$term_ids, &$notify ) {
			global $wpdb;
			$universe = ps_pen_paper_get_universe( $universe->ID ); $config = ps_pen_paper_wiki_config( $universe );
			if ( is_wp_error( $config ) || ! $config['enabled'] || ! is_string( $config_revision ) || (string) $config['revision'] !== $config_revision || ! ps_pen_paper_wiki_config_terms_valid( $config ) ) { return ps_pen_paper_wiki_error(); }
			if ( $id ) { clean_post_cache( $id ); }
			$before = $id ? ps_pen_paper_get_wiki_entry( $universe, $id ) : ps_pen_paper_wiki_entry_default();
			if ( is_wp_error( $before ) || ! is_string( $revision ) || (string) $before['revision'] !== $revision || ( $publish && ! $id ) ) { return ps_pen_paper_wiki_error(); }
			$after = $before; $after['revision']++;
			if ( $publish ) {
				foreach ( array( 'source_id', 'source_revision', 'title', 'content', 'category' ) as $key ) {
					if ( (string) $before[ $key ] !== $input[ $key ] ) { return ps_pen_paper_wiki_error(); }
				}
				if ( $before['published'] ) { return ps_pen_paper_wiki_error(); }
			} else {
				$after['source_id'] = (int) $input['source_id']; $after['source_revision'] = (int) $input['source_revision'];
				$after['title'] = sanitize_text_field( $input['title'] ); $after['content'] = sanitize_textarea_field( $input['content'] ); $after['category'] = $input['category'];
				$after['published'] = false;
				if ( $id && $after['source_id'] !== $before['source_id'] ) { return ps_pen_paper_wiki_error(); }
			}
			if ( ! isset( $config['categories'][ $after['category'] ] ) || '' === trim( $after['title'] ) || strlen( $after['title'] ) > 200 ||
				'' === trim( $after['content'] ) || strlen( $after['content'] ) > 20000 ) { return ps_pen_paper_wiki_error(); }
			if ( $after['source_id'] ) {
				clean_post_cache( $after['source_id'] );
				$source = ps_pen_paper_get_npc( $universe, $after['source_id'] );
				if ( is_wp_error( $source ) || 'template' !== $source['status'] || (string) $source['revision'] !== $input['source_revision'] ) { return ps_pen_paper_wiki_error(); }
				if ( ! $id ) {
					$entries = ps_pen_paper_wiki_entries( $universe );
					if ( is_wp_error( $entries ) ) { return $entries; }
					foreach ( $entries as $entry ) { if ( $entry['source_id'] === $after['source_id'] ) { return new WP_Error( 'wiki_duplicate', __( 'Diese Universumsvorlage besitzt bereits einen Artikel. Bitte bearbeite dessen bestehenden Eintrag.', 'ps-pen-paper' ) ); } }
				}
			} elseif ( $after['source_revision'] ) { return ps_pen_paper_wiki_error(); }
			$new = ! $id;
			if ( $new ) {
				$id = ps_pen_paper_insert_record( 'pspp_wiki_entry', $universe->ID, array(), false );
				if ( is_wp_error( $id ) ) { return $id; }
			} else {
				$wiki_id = $before['wiki_id'];
				if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $wiki_id ) ) !== $wiki_id ) { return ps_pen_paper_wiki_error(); }
				$fingerprint = ps_pen_paper_wiki_fingerprint( $wiki_id, $universe->ID, $id );
				if ( is_wp_error( $fingerprint ) || $fingerprint !== $before['fingerprint'] ) { return ps_pen_paper_wiki_error(); }
			}
			$term_id = $config['categories'][ $after['category'] ]['id']; $term_ids[] = $term_id;
			if ( $new || $publish ) {
				$post = array( 'post_type' => 'psource_wiki', 'post_title' => $after['title'], 'post_content' => ps_pen_paper_wiki_public_html( $after['content'] ),
					'post_status' => $publish ? 'publish' : 'draft', 'post_excerpt' => '', 'post_parent' => 0, 'post_password' => '', 'comment_status' => 'closed' );
				if ( $new ) {
					$post['post_author'] = get_current_user_id(); $post['post_name'] = 'weltartikel-' . wp_generate_uuid4();
					$post['meta_input'] = array( '_pspp_wiki_owner' => array( $universe->ID, $id ) );
				} else { $post['ID'] = $wiki_id; }
				$saved = wp_insert_post( wp_slash( $post ), true );
				if ( is_wp_error( $saved ) || ! $saved ) { return is_wp_error( $saved ) ? $saved : ps_pen_paper_wiki_error(); }
				$wiki_id = $saved; clean_post_cache( $wiki_id );
				$terms = wp_set_object_terms( $wiki_id, array( $term_id ), 'psource_wiki_category' );
				if ( is_wp_error( $terms ) ) { return $terms; }
				$assigned = wp_get_object_terms( $wiki_id, 'psource_wiki_category', array( 'fields' => 'ids' ) );
				if ( is_wp_error( $assigned ) || array( $term_id ) !== $assigned ) { return ps_pen_paper_wiki_error(); }
				$actual = get_post( $wiki_id );
				if ( $actual->post_status !== $post['post_status'] || $actual->post_title !== $after['title'] || $actual->post_content !== $post['post_content'] ) { return ps_pen_paper_wiki_error(); }
				$notify = $publish;
			}
			$after['wiki_id'] = $wiki_id; $after['fingerprint'] = ps_pen_paper_wiki_fingerprint( $wiki_id, $universe->ID, $id );
			if ( $publish ) { $after['published'] = true; }
			if ( ! ps_pen_paper_valid_wiki_entry( $after ) ) { return ps_pen_paper_wiki_error(); }
			return ps_pen_paper_write_document_event( $universe->ID, $id, '_pspp_wiki_entry', $before, $after,
				array( 'kind' => $publish ? 'wiki_published' : 'wiki_prepared', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), $new );
		} );
	} finally {
		if ( false !== $notification_priority ) { add_action( 'pre_post_update', $notifications, $notification_priority, 1 ); }
		foreach ( array( $id, $wiki_id ) as $cache_id ) { if ( is_int( $cache_id ) && $cache_id ) { clean_post_cache( $cache_id ); } }
		if ( $term_ids ) { clean_term_cache( $term_ids, 'psource_wiki_category' ); }
	}
	if ( true === $result && $notify && false !== $notification_priority ) { call_user_func( $notifications, $wiki_id ); }
	return is_wp_error( $result ) ? $result : $id;
}
