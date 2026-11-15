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

function ps_pen_paper_valid_handout_audience( $audience ) {
	if ( ! is_array( $audience ) || array( 'mode', 'users' ) !== array_keys( $audience ) ||
		! in_array( $audience['mode'], array( 'all', 'users' ), true ) || ! is_array( $audience['users'] ) ||
		array_values( $audience['users'] ) !== $audience['users'] || count( $audience['users'] ) > 500 ||
		count( $audience['users'] ) !== count( array_unique( $audience['users'], SORT_REGULAR ) ) ) { return false; }
	foreach ( $audience['users'] as $user_id ) { if ( ! is_int( $user_id ) || $user_id < 1 ) { return false; } }
	return 'all' === $audience['mode'] ? ! $audience['users'] : (bool) $audience['users'];
}

function ps_pen_paper_handout_audience( $snapshot ) {
	return isset( $snapshot['audience'] ) ? $snapshot['audience'] : array( 'mode' => 'all', 'users' => array() );
}

function ps_pen_paper_validate_folio_snapshot( $value, $published = false ) {
	if ( ! is_array( $value ) || ! isset( $value['title'], $value['text'], $value['asset_id'] ) ||
		! is_string( $value['title'] ) || '' === trim( $value['title'] ) || strlen( $value['title'] ) > 800 ||
		! is_string( $value['text'] ) || strlen( $value['text'] ) > 20000 ||
		! is_int( $value['asset_id'] ) || $value['asset_id'] < 0 ) { return false; }
	if ( array_key_exists( 'audience', $value ) && ! ps_pen_paper_valid_handout_audience( $value['audience'] ) ) { return false; }
	if ( $published ) {
		return in_array( array_keys( $value ), array( array( 'title', 'text', 'asset_id' ), array( 'title', 'text', 'asset_id', 'audience' ) ), true );
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
	$post = ps_pen_paper_record_post( $id );
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

function ps_pen_paper_save_folio( $campaign, $id, $input, $revision, $image_path = null, $filename = null ) {
	return ps_pen_paper_scope_write( $campaign, function () use ( $campaign, $id, $input, $revision, $image_path, $filename ) {
		return ps_pen_paper_save_folio_locked( $campaign, $id, $input, $revision, $image_path, $filename );
	} );
}

function ps_pen_paper_save_folio_locked( $campaign, $id, $input, $revision, $image_path = null, $filename = null ) {
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
	if ( isset( $input['audience_mode'] ) || isset( $input['audience_users'] ) ) {
		if ( ! isset( $input['audience_mode'] ) || ! is_string( $input['audience_mode'] ) ||
			( isset( $input['audience_users'] ) && ! is_array( $input['audience_users'] ) ) ) { return ps_pen_paper_folio_error(); }
		$users = array();
		foreach ( isset( $input['audience_users'] ) ? $input['audience_users'] : array() as $user_id ) {
			if ( ! is_string( $user_id ) || ! preg_match( '/^[1-9]\d{0,10}$/D', $user_id ) ) { return ps_pen_paper_folio_error(); }
			$user_id = (int) $user_id;
			$user = ps_pen_paper_site_user( $user_id );
			if ( ! $user || ! user_can( $user, 'read' ) || ! ps_pen_paper_campaign_role( $campaign, $user_id ) ) { return ps_pen_paper_folio_error(); }
			$users[] = $user_id;
		}
		$draft['audience'] = array( 'mode' => $input['audience_mode'], 'users' => $users );
		if ( 'handout' !== $draft['type'] || ! ps_pen_paper_valid_handout_audience( $draft['audience'] ) ) { return ps_pen_paper_folio_error(); }
	} elseif ( isset( $before['draft']['audience'] ) ) {
		$draft['audience'] = $before['draft']['audience'];
	}
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
		$asset_id = ps_pen_paper_store_handout_asset( $campaign, $image_path, $filename );
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
	$audience = ps_pen_paper_handout_audience( $before['draft'] );
	if ( ! $withdraw && 'users' === $audience['mode'] && $before['draft']['asset_id'] &&
		array( '1' ) !== get_post_meta( $before['draft']['asset_id'], '_pspp_handout_asset', false ) ) {
		return new WP_Error( 'restricted_asset', __( 'Dieses ältere Handoutbild muss für einen eingeschränkten Empfängerkreis erneut hochgeladen werden.', 'ps-pen-paper' ) );
	}
	if ( ! $withdraw && 'users' === $audience['mode'] ) {
		foreach ( $audience['users'] as $user_id ) {
			$user = ps_pen_paper_site_user( $user_id );
			if ( ! $user || ! user_can( $user, 'read' ) || ! ps_pen_paper_campaign_role( $campaign, $user_id ) ) {
				return new WP_Error( 'invalid_audience', __( 'Ein ausgewählter Empfänger ist kein leseberechtigtes Mitglied mehr. Bitte bearbeite den Entwurf.', 'ps-pen-paper' ) );
			}
		}
	}
	$after = $before;
	$after['revision']++;
	$after['published'] = $withdraw ? null : array(
		'title' => $before['draft']['title'], 'text' => $before['draft']['text'], 'asset_id' => $before['draft']['asset_id'],
	);
	if ( ! $withdraw && isset( $before['draft']['audience'] ) ) { $after['published']['audience'] = $audience; }
	return ps_pen_paper_folio_event( $campaign, $id, $before, $after, $withdraw ? 'folio_withdrawn' : 'folio_published' );
}

function ps_pen_paper_handout_asset( $campaign, $folio_id, $draft = false ) {
	$error = new WP_Error( 'forbidden', __( 'Diese Handoutdatei ist nicht verfügbar.', 'ps-pen-paper' ) );
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) || ( $draft && ! ps_pen_paper_can_manage_campaign( $campaign ) ) ) { return $error; }
	$folio = ps_pen_paper_get_folio( $campaign, $folio_id );
	if ( is_wp_error( $folio ) || 'handout' !== $folio['draft']['type'] ) { return $error; }
	$snapshot = $draft ? $folio['draft'] : $folio['published'];
	if ( ! $snapshot || ! $snapshot['asset_id'] ) { return $error; }
	$audience = ps_pen_paper_handout_audience( $snapshot );
	if ( ! $draft && 'users' === $audience['mode'] && ! in_array( get_current_user_id(), $audience['users'], true ) && ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return $error; }
	$asset = ps_pen_paper_record_post( $snapshot['asset_id'] );
	if ( ! $asset || 'pspp_asset' !== $asset->post_type || 'private' !== $asset->post_status ||
		(int) $asset->post_parent !== (int) $campaign->ID ||
		( 'users' === $audience['mode'] && array( '1' ) !== get_post_meta( $asset->ID, '_pspp_handout_asset', false ) ) ||
		! ps_pen_paper_valid_private_asset_content( $asset->post_mime_type, $asset->post_content ) ) { return $error; }
	return $asset;
}

function ps_pen_paper_handout_download_url( $campaign, $folio_id, $draft = false ) {
	$args = array( 'action' => 'pspp_handout_download', 'campaign_id' => $campaign->ID, 'folio_id' => $folio_id, 'draft' => $draft ? '1' : '0' );
	return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'pspp_handout_' . $campaign->ID . '_' . $folio_id . '_' . $args['draft'] );
}

function ps_pen_paper_download_handout() {
	$campaign_id = isset( $_GET['campaign_id'] ) && is_string( $_GET['campaign_id'] ) && preg_match( '/^[1-9]\d{0,10}$/D', $_GET['campaign_id'] ) ? (int) $_GET['campaign_id'] : 0;
	$folio_id = isset( $_GET['folio_id'] ) && is_string( $_GET['folio_id'] ) && preg_match( '/^[1-9]\d{0,10}$/D', $_GET['folio_id'] ) ? (int) $_GET['folio_id'] : 0;
	$draft = isset( $_GET['draft'] ) && is_string( $_GET['draft'] ) ? $_GET['draft'] : '';
	if ( ! $campaign_id || ! $folio_id || ! in_array( $draft, array( '0', '1' ), true ) ||
		! isset( $_GET['_wpnonce'] ) || ! is_string( $_GET['_wpnonce'] ) ||
		! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'pspp_handout_' . $campaign_id . '_' . $folio_id . '_' . $draft ) ) {
		wp_die( esc_html__( 'Diese Handoutdatei ist nicht verfügbar.', 'ps-pen-paper' ), '', array( 'response' => 403 ) );
	}
	$asset = ps_pen_paper_handout_asset( ps_pen_paper_get_campaign( $campaign_id ), $folio_id, '1' === $draft );
	if ( is_wp_error( $asset ) ) { wp_die( esc_html( $asset->get_error_message() ), '', array( 'response' => 403 ) ); }
	$extensions = array( 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'application/pdf' => 'pdf', 'text/plain' => 'txt' );
	$data = base64_decode( $asset->post_content, true );
	nocache_headers();
	header( 'Cache-Control: private, no-store, max-age=0' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Security-Policy: sandbox; default-src \'none\'' );
	header( 'Content-Type: ' . $asset->post_mime_type );
	header( 'Content-Disposition: ' . ( 0 === strpos( $asset->post_mime_type, 'image/' ) ? 'inline' : 'attachment' ) . '; filename="handout.' . $extensions[ $asset->post_mime_type ] . '"' );
	header( 'Content-Length: ' . strlen( $data ) );
	echo $data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}

function ps_pen_paper_visible_handouts( $campaign ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Diese Handouts sind für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	$handouts = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_folio', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
		$value = ps_pen_paper_get_folio( $campaign, $post->ID );
		if ( is_wp_error( $value ) ) { return $value; }
		$audience = null === $value['published'] ? null : ps_pen_paper_handout_audience( $value['published'] );
		if ( null !== $audience && ( 'all' === $audience['mode'] || in_array( get_current_user_id(), $audience['users'], true ) || ps_pen_paper_can_manage_campaign( $campaign ) ) ) {
			$handouts[ $post->ID ] = array_intersect_key( $value['published'], array_flip( array( 'title', 'text', 'asset_id' ) ) );
		}
	}
	return $handouts;
}
