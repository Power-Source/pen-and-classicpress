<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_register_scene_type() {
	register_post_type( 'pspp_scene', array(
		'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
		'show_ui' => false, 'show_in_rest' => false, 'supports' => false,
		'capability_type' => array( 'pspp_scene', 'pspp_scenes' ), 'map_meta_cap' => true,
	) );
}

function ps_pen_paper_scene_default() {
	return array( 'revision' => 0, 'publication' => 0, 'draft' => array(
		'title' => '', 'text' => '', 'secret' => '', 'asset_id' => 0, 'width' => 1000, 'height' => 700, 'grid' => 50, 'tokens' => array(),
	), 'published' => null );
}

function ps_pen_paper_scene_error() {
	return new WP_Error( 'invalid_scene', __( 'Der Szenenstand ist unvollständig oder beschädigt. Bitte prüfe die Szene als Spielleitung.', 'ps-pen-paper' ), array( 'status' => 400 ) );
}

function ps_pen_paper_validate_scene_snapshot( $value, $draft = false ) {
	if ( ! is_array( $value ) || ! isset( $value['title'], $value['text'], $value['asset_id'], $value['width'], $value['height'], $value['grid'], $value['tokens'] ) ||
		! is_string( $value['title'] ) || '' === trim( $value['title'] ) || strlen( $value['title'] ) > 800 || ! is_string( $value['text'] ) || strlen( $value['text'] ) > 20000 ||
		! is_int( $value['asset_id'] ) || $value['asset_id'] < 0 || ! is_int( $value['width'] ) || ! is_int( $value['height'] ) ||
		$value['width'] < 1 || $value['height'] < 1 || $value['width'] > 3000 || $value['height'] > 3000 ||
		! is_int( $value['grid'] ) || ( 0 !== $value['grid'] && ( $value['grid'] < 10 || $value['grid'] > 500 ) ) ||
		! is_array( $value['tokens'] ) || count( $value['tokens'] ) > 30 ||
		( $draft && ( ! isset( $value['secret'] ) || ! is_string( $value['secret'] ) || strlen( $value['secret'] ) > 20000 ) ) ||
		( ! $draft && array_key_exists( 'secret', $value ) ) ) {
		return ps_pen_paper_scene_error();
	}
	$ids = array();
	foreach ( $value['tokens'] as $token ) {
		if ( ! is_array( $token ) || ! isset( $token['id'], $token['name'], $token['type'], $token['owner_id'], $token['x'], $token['y'], $token['color'] ) ||
			! is_string( $token['id'] ) || ! wp_is_uuid( $token['id'] ) || isset( $ids[ $token['id'] ] ) ||
			! is_string( $token['name'] ) || '' === trim( $token['name'] ) || strlen( $token['name'] ) > 320 ||
			! in_array( $token['type'], array( 'player', 'npc', 'object' ), true ) ||
			! is_int( $token['owner_id'] ) || $token['owner_id'] < 0 || ! is_int( $token['x'] ) || ! is_int( $token['y'] ) ||
			$token['x'] < 0 || $token['x'] > 10000 || $token['y'] < 0 || $token['y'] > 10000 ||
			! is_string( $token['color'] ) || ! preg_match( '/^#[a-f0-9]{6}$/iD', $token['color'] ) ) {
			return ps_pen_paper_scene_error();
		}
		$ids[ $token['id'] ] = true;
	}
	return true;
}

function ps_pen_paper_get_scene( $campaign, $id ) {
	$post = ps_pen_paper_record_post( $id );
	if ( ! $post || 'pspp_scene' !== $post->post_type || 'private' !== $post->post_status || (int) $post->post_parent !== (int) $campaign->ID ) {
		return ps_pen_paper_scene_error();
	}
	$state = get_post_meta( $id, '_pspp_scene', true );
	return ps_pen_paper_validate_scene_state( $state );
}

function ps_pen_paper_validate_scene_state( $state ) {
	if ( ! is_array( $state ) || ! isset( $state['revision'], $state['publication'], $state['draft'] ) || ! array_key_exists( 'published', $state ) ||
		! is_int( $state['revision'] ) || $state['revision'] < 1 || ! is_int( $state['publication'] ) || $state['publication'] < 0 ||
		is_wp_error( ps_pen_paper_validate_scene_snapshot( $state['draft'], true ) ) ||
		( null !== $state['published'] && ( $state['publication'] < 1 || is_wp_error( ps_pen_paper_validate_scene_snapshot( $state['published'] ) ) ) ) ) {
		return ps_pen_paper_scene_error();
	}
	return $state;
}

function ps_pen_paper_map_selection( $campaign ) {
	$value = get_post_meta( $campaign->ID, '_pspp_scene_selection', true );
	if ( '' === $value ) { return array( 'revision' => 0, 'scene_id' => 0, 'name' => '' ); }
	if ( ! is_array( $value ) || ! isset( $value['revision'], $value['scene_id'], $value['name'] ) ||
		! is_int( $value['revision'] ) || $value['revision'] < 1 || ! is_int( $value['scene_id'] ) || $value['scene_id'] < 0 || ! is_string( $value['name'] ) ) {
		return ps_pen_paper_scene_error();
	}
	return $value;
}

function ps_pen_paper_scene_event( $campaign, $id, $before, $after, $kind, $new = false ) {
	return ps_pen_paper_save_document_event( $campaign->ID, $id, '_pspp_scene', $before, $after, array(
		'kind' => $kind, 'visibility' => 'gm', 'recipient_id' => 0,
		'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $campaign->ID )['revision'],
	), $new );
}

function ps_pen_paper_save_scene( $campaign, $id, $input, $revision, $image_path = null ) {
	return ps_pen_paper_scope_write( $campaign, function () use ( $campaign, $id, $input, $revision, $image_path ) {
		return ps_pen_paper_save_scene_locked( $campaign, $id, $input, $revision, $image_path );
	} );
}

function ps_pen_paper_save_scene_locked( $campaign, $id, $input, $revision, $image_path = null ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die Spielleitung darf Szenen vorbereiten.', 'ps-pen-paper' ) );
	}
	$before = $id ? ps_pen_paper_get_scene( $campaign, $id ) : ps_pen_paper_scene_default();
	if ( is_wp_error( $before ) ) { return $before; }
	if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision ) {
		return new WP_Error( 'conflict', __( 'Szene oder Figurenpositionen wurden geändert. Bitte lade die Szene neu; nichts wurde überschrieben.', 'ps-pen-paper' ) );
	}
	if ( ! is_array( $input ) || ! isset( $input['title'], $input['text'], $input['secret'], $input['grid'] ) ||
		! is_string( $input['title'] ) || ! is_string( $input['text'] ) || ! is_string( $input['secret'] ) || ! is_string( $input['grid'] ) || ! preg_match( '/^\d{1,3}$/D', $input['grid'] ) ||
		( isset( $input['tokens'] ) && ( ! is_array( $input['tokens'] ) || count( $input['tokens'] ) > 30 ) ) ) { return ps_pen_paper_scene_error(); }
	$draft = $before['draft'];
	$draft['title'] = sanitize_text_field( $input['title'] );
	$draft['text'] = sanitize_textarea_field( $input['text'] );
	$draft['secret'] = sanitize_textarea_field( $input['secret'] );
	$draft['grid'] = (int) $input['grid'];
	$draft['tokens'] = array();
	$members = ps_pen_paper_table_members( $campaign );
	foreach ( isset( $input['tokens'] ) ? $input['tokens'] : array() as $token ) {
		if ( ! is_array( $token ) || ! isset( $token['id'], $token['name'], $token['type'], $token['color'], $token['owner_id'], $token['x'], $token['y'] ) ) { return ps_pen_paper_scene_error(); }
		foreach ( $token as $value ) { if ( ! is_string( $value ) ) { return ps_pen_paper_scene_error(); } }
		if ( ! preg_match( '/^\d{1,10}$/D', $token['owner_id'] ) ) { return ps_pen_paper_scene_error(); }
		$token['owner_id'] = (int) $token['owner_id'];
		foreach ( array( 'x', 'y' ) as $key ) {
			if ( ! preg_match( '/^(?:\d{1,2}(?:\.\d{1,2})?|100(?:\.0{1,2})?)$/D', $token[ $key ] ) ) { return ps_pen_paper_scene_error(); }
			$token[ $key ] = (int) round( (float) $token[ $key ] * 100 );
		}
		if ( $token['owner_id'] && ! isset( $members[ $token['owner_id'] ] ) ) {
			return new WP_Error( 'invalid_member', __( 'Marker dürfen nur aktuellen Rundenteilnehmern zugewiesen werden.', 'ps-pen-paper' ) );
		}
		$draft['tokens'][] = array(
			'id' => '' === $token['id'] ? wp_generate_uuid4() : $token['id'], 'name' => sanitize_text_field( $token['name'] ),
			'type' => $token['type'], 'color' => $token['color'], 'owner_id' => $token['owner_id'], 'x' => $token['x'], 'y' => $token['y'],
		);
	}
	$valid = ps_pen_paper_validate_scene_snapshot( $draft, true );
	if ( is_wp_error( $valid ) ) { return $valid; }
	$asset_id = 0;
	if ( null !== $image_path ) {
		$asset_id = ps_pen_paper_store_private_image( $campaign->ID, $image_path );
		if ( is_wp_error( $asset_id ) ) { return $asset_id; }
		$size = wp_getimagesize( $image_path );
		$draft['asset_id'] = $asset_id;
		$draft['width'] = $size[0];
		$draft['height'] = $size[1];
	}
	$after = $before;
	$after['revision']++;
	$after['draft'] = $draft;
	$new = ! $id;
	if ( $new ) {
		$id = wp_insert_post( array( 'post_type' => 'pspp_scene', 'post_status' => 'draft', 'post_parent' => $campaign->ID, 'post_title' => 'PS Pen & Paper Scene' ), true );
	}
	$result = is_wp_error( $id ) || ! $id ? new WP_Error( 'save_failed', __( 'Die Szene konnte nicht angelegt werden.', 'ps-pen-paper' ) )
		: ps_pen_paper_scene_event( $campaign, $id, $before, $after, 'scene_saved', $new );
	if ( is_wp_error( $result ) ) {
		if ( 'event_recovery_required' === $result->get_error_code() ) { return $result; }
		if ( $asset_id && ! wp_delete_post( $asset_id, true ) ) { return new WP_Error( 'cleanup_failed', __( 'Ein nicht zugeordnetes Kartenbild konnte nicht entfernt werden.', 'ps-pen-paper' ) ); }
		return $new && is_int( $id ) && $id ? ps_pen_paper_discard_record( $id, $result ) : $result;
	}
	return $id;
}

function ps_pen_paper_publish_scene( $campaign, $id, $revision, $confirmed, $withdraw = false ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return new WP_Error( 'forbidden', __( 'Nur die Spielleitung darf Szenen freigeben.', 'ps-pen-paper' ) ); }
	$before = ps_pen_paper_get_scene( $campaign, $id );
	if ( is_wp_error( $before ) ) { return $before; }
	if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision || 'confirmed' !== $confirmed ) {
		return new WP_Error( 'conflict', __( 'Bitte lade den aktuellen Stand und bestätige die Szenenfreigabe beziehungsweise Rücknahme.', 'ps-pen-paper' ) );
	}
	$after = $before;
	$after['revision']++;
	$after['publication']++;
	$after['published'] = $withdraw ? null : $before['draft'];
	if ( ! $withdraw ) { unset( $after['published']['secret'] ); }
	return ps_pen_paper_scene_event( $campaign, $id, $before, $after, $withdraw ? 'scene_withdrawn' : 'scene_published' );
}

function ps_pen_paper_select_scene( $campaign, $id, $revision ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return new WP_Error( 'forbidden', __( 'Nur die Spielleitung darf die aktive Szene wechseln.', 'ps-pen-paper' ) ); }
	$before = ps_pen_paper_map_selection( $campaign );
	$scene = $id ? ps_pen_paper_get_scene( $campaign, $id ) : null;
	if ( is_wp_error( $before ) || is_wp_error( $scene ) ) { return is_wp_error( $before ) ? $before : $scene; }
	if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision || ( $id && ! $scene['published'] ) ) {
		return new WP_Error( 'conflict', __( 'Die Szenenauswahl wurde geändert oder die Szene ist nicht freigegeben.', 'ps-pen-paper' ) );
	}
	$after = array( 'revision' => $before['revision'] + 1, 'scene_id' => $id, 'name' => $id ? $scene['published']['title'] : __( 'Rundeneinleitung', 'ps-pen-paper' ) );
	return ps_pen_paper_save_document_event( $campaign->ID, $campaign->ID, '_pspp_scene_selection', $before, $after, array(
		'kind' => 'scene_selected', 'visibility' => 'group', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $campaign->ID )['revision'],
	) );
}

function ps_pen_paper_map_state( $campaign, $player_view = false ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) ) { return new WP_Error( 'forbidden', __( 'Diese Karte ist für dich nicht verfügbar.', 'ps-pen-paper' ), array( 'status' => 403 ) ); }
	$selection = ps_pen_paper_map_selection( $campaign );
	if ( is_wp_error( $selection ) ) { return $selection; }
	if ( ! $selection['scene_id'] ) { return array( 'scene' => null, 'ping' => null ); }
	$state = ps_pen_paper_get_scene( $campaign, $selection['scene_id'] );
	if ( is_wp_error( $state ) ) { return $state; }
	if ( ! $state['published'] ) { return array( 'scene' => null, 'ping' => null ); }
	$scene = array_intersect_key( $state['published'], array_flip( array( 'title', 'text', 'asset_id', 'width', 'height', 'grid', 'tokens' ) ) );
	$scene['id'] = $selection['scene_id'];
	$scene['revision'] = $state['revision'];
	$scene['publication'] = $state['publication'];
	$members = ps_pen_paper_table_members( $campaign );
	foreach ( $scene['tokens'] as &$token ) {
		$token = array_intersect_key( $token, array_flip( array( 'id', 'name', 'type', 'owner_id', 'x', 'y', 'color' ) ) );
		$token['can_move'] = ( ! $player_view && ps_pen_paper_can_manage_campaign( $campaign ) ) || ( $token['owner_id'] === get_current_user_id() && isset( $members[ $token['owner_id'] ] ) );
	}
	unset( $token );
	$ping = get_transient( 'pspp_ping_' . $campaign->ID );
	if ( ! is_array( $ping ) || $ping['scene_id'] !== $scene['id'] || $ping['publication'] !== $scene['publication'] ) { $ping = null; }
	if ( $ping ) { $ping['expires_in'] = max( 0, $ping['created'] + 15 - time() ); }
	return array( 'scene' => $scene, 'ping' => $ping );
}

function ps_pen_paper_map_request( $request ) {
	$player_view = ps_pen_paper_request_player_view( $request );
	if ( is_wp_error( $player_view ) ) { return $player_view; }
	$campaign = ps_pen_paper_get_campaign( (int) $request['id'] );
	$state = ps_pen_paper_map_state( $campaign, $player_view );
	if ( is_wp_error( $state ) ) { return $state; }
	if ( 'POST' === $request->get_method() ) {
		$input = $request->get_json_params();
		$scene = $state['scene'];
		if ( ! $scene || ! is_array( $input ) || ! isset( $input['scene_id'], $input['revision'], $input['publication'], $input['operation'], $input['x'], $input['y'] ) ||
			$input['scene_id'] !== $scene['id'] || $input['revision'] !== $scene['revision'] || $input['publication'] !== $scene['publication'] ||
			! is_int( $input['x'] ) || ! is_int( $input['y'] ) || $input['x'] < 0 || $input['x'] > 10000 || $input['y'] < 0 || $input['y'] > 10000 ) {
			return new WP_Error( 'conflict', __( 'Die Karte wurde geändert oder die Position ist ungültig. Bitte aktualisiere den Kartenstand.', 'ps-pen-paper' ), array( 'status' => 409 ) );
		}
		if ( 'ping' === $input['operation'] ) {
			$ping = array( 'id' => wp_generate_uuid4(), 'scene_id' => $scene['id'], 'publication' => $scene['publication'], 'x' => $input['x'], 'y' => $input['y'], 'name' => wp_get_current_user()->display_name, 'created' => time() );
			if ( ! set_transient( 'pspp_ping_' . $campaign->ID, $ping, 15 ) ) { return new WP_Error( 'save_failed', __( 'Der Kartenhinweis konnte nicht geteilt werden.', 'ps-pen-paper' ) ); }
		} elseif ( 'move' === $input['operation'] ) {
			$before = ps_pen_paper_get_scene( $campaign, $scene['id'] );
			if ( is_wp_error( $before ) ) { return $before; }
			if ( $before['revision'] !== $scene['revision'] ) { return new WP_Error( 'conflict', __( 'Die Figuren wurden inzwischen bewegt. Bitte aktualisiere die Karte.', 'ps-pen-paper' ), array( 'status' => 409 ) ); }
			$after = $before;
			$found = false;
			foreach ( $after['published']['tokens'] as &$token ) {
				if ( ! isset( $input['token_id'] ) || $token['id'] !== $input['token_id'] ) { continue; }
				if ( ( $player_view || ! ps_pen_paper_can_manage_campaign( $campaign ) ) && ( $token['owner_id'] !== get_current_user_id() || ! isset( ps_pen_paper_table_members( $campaign )[ get_current_user_id() ] ) ) ) {
					return new WP_Error( 'forbidden', __( 'Du darfst nur deine zugewiesenen Figuren bewegen.', 'ps-pen-paper' ), array( 'status' => 403 ) );
				}
				foreach ( array( 'x' => 'width', 'y' => 'height' ) as $axis => $dimension ) {
					$token[ $axis ] = $scene['grid'] ? (int) max( 0, min( 10000, round( $input[ $axis ] * $scene[ $dimension ] / 10000 / $scene['grid'] ) * $scene['grid'] / $scene[ $dimension ] * 10000 ) ) : $input[ $axis ];
				}
				$found = true;
			}
			unset( $token );
			if ( ! $found ) { return ps_pen_paper_scene_error(); }
			$after['revision']++;
			$result = ps_pen_paper_scene_event( $campaign, $scene['id'], $before, $after, 'map_moved' );
			if ( is_wp_error( $result ) ) { return $result; }
		} else { return ps_pen_paper_scene_error(); }
		$state = ps_pen_paper_map_state( $campaign, $player_view );
		if ( is_wp_error( $state ) ) { return $state; }
	}
	$response = rest_ensure_response( $state );
	$response->header( 'Cache-Control', 'no-store, private' );
	return $response;
}

function ps_pen_paper_map_image_request( $request ) {
	$player_view = ps_pen_paper_request_player_view( $request );
	if ( is_wp_error( $player_view ) ) { return $player_view; }
	$campaign = ps_pen_paper_get_campaign( (int) $request['id'] );
	$asset_id = (int) $request['asset'];
	$public = ps_pen_paper_map_state( $campaign, $player_view );
	if ( is_wp_error( $public ) ) { return $public; }
	if ( ! $player_view && ps_pen_paper_can_manage_campaign( $campaign ) ) {
		$scene = ps_pen_paper_get_scene( $campaign, (int) $request['scene'] );
		if ( is_wp_error( $scene ) ) { return $scene; }
		$allowed = $asset_id === $scene['draft']['asset_id'] || ( $scene['published'] && $asset_id === $scene['published']['asset_id'] );
	} else {
		$allowed = $public['scene'] && $public['scene']['id'] === (int) $request['scene'] && $public['scene']['asset_id'] === $asset_id;
	}
	if ( ! $allowed ) { return new WP_Error( 'image_not_released', __( 'Dieses Kartenbild ist nicht freigegeben.', 'ps-pen-paper' ), array( 'status' => 403 ) ); }
	$image = ps_pen_paper_private_image( $campaign, $asset_id );
	if ( is_wp_error( $image ) ) { return $image; }
	$response = rest_ensure_response( array( 'image' => $image ) );
	$response->header( 'Cache-Control', 'no-store, private' );
	return $response;
}

function ps_pen_paper_register_map_routes() {
	register_rest_route( 'ps-pen-paper/v1', '/campaigns/(?P<id>[1-9]\d*)/map', array( 'methods' => array( 'GET', 'POST' ), 'callback' => 'ps_pen_paper_map_request', 'permission_callback' => 'ps_pen_paper_table_permission' ) );
	register_rest_route( 'ps-pen-paper/v1', '/campaigns/(?P<id>[1-9]\d*)/maps/(?P<scene>[1-9]\d*)/images/(?P<asset>[1-9]\d*)', array( 'methods' => 'GET', 'callback' => 'ps_pen_paper_map_image_request', 'permission_callback' => 'ps_pen_paper_table_permission' ) );
}
