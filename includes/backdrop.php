<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_register_asset_type() {
	register_post_type( 'pspp_asset', array(
		'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
		'show_ui' => false, 'show_in_rest' => false,
		'capability_type' => array( 'pspp_asset', 'pspp_assets' ), 'map_meta_cap' => true,
		'supports' => false,
	) );
}

function ps_pen_paper_get_backdrop( $campaign_id ) {
	$state = get_post_meta( $campaign_id, '_pspp_backdrop', true );
	if ( '' === $state ) {
		return array( 'revision' => 0, 'asset_id' => 0 );
	}
	if ( ! is_array( $state ) || ! isset( $state['revision'], $state['asset_id'] ) ||
		! is_int( $state['revision'] ) || $state['revision'] < 1 || ! is_int( $state['asset_id'] ) || $state['asset_id'] < 0 ) {
		return new WP_Error( 'invalid_backdrop', __( 'Die Hintergrundkonfiguration ist beschädigt. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	return $state;
}

function ps_pen_paper_backdrop_image( $campaign ) {
	if ( ! ps_pen_paper_can_view_campaign( $campaign ) || ! current_user_can( 'read' ) ) {
		return new WP_Error( 'forbidden', __( 'Du darfst diesen Hintergrund nicht ansehen.', 'ps-pen-paper' ) );
	}
	$state = ps_pen_paper_get_backdrop( $campaign->ID );
	if ( is_wp_error( $state ) || ! $state['asset_id'] ) {
		return is_wp_error( $state ) ? $state : '';
	}
	return ps_pen_paper_private_image( $campaign, $state['asset_id'] );
}

function ps_pen_paper_private_image( $campaign, $asset_id ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_character_scope( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Dieses Bild ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	return ps_pen_paper_private_image_data( $campaign, $asset_id );
}

function ps_pen_paper_private_image_data( $campaign, $asset_id ) {
	$asset = ps_pen_paper_record_post( $asset_id );
	if ( ! $asset || 'pspp_asset' !== $asset->post_type || 'private' !== $asset->post_status ||
		(int) $asset->post_parent !== (int) $campaign->ID ||
		metadata_exists( 'post', $asset_id, '_pspp_handout_asset' ) ||
		! in_array( $asset->post_mime_type, array( 'image/png', 'image/jpeg', 'image/webp' ), true ) ||
		! preg_match( '/^[A-Za-z0-9+\/]+=*$/D', $asset->post_content ) ) {
		return new WP_Error( 'invalid_backdrop', __( 'Der Hintergrund ist nicht mehr verfügbar. Bitte lade ihn in den Rundeneinstellungen erneut hoch.', 'ps-pen-paper' ) );
	}
	return 'data:' . $asset->post_mime_type . ';base64,' . $asset->post_content;
}

function ps_pen_paper_valid_private_asset_content( $mime, $content ) {
	if ( ! is_string( $mime ) || ! is_string( $content ) || '' === $content || strlen( $content ) > 4 * (int) ceil( 2 * MB_IN_BYTES / 3 ) ) { return false; }
	$data = base64_decode( $content, true );
	if ( false === $data || base64_encode( $data ) !== $content || strlen( $data ) > 2 * MB_IN_BYTES ) { return false; }
	if ( in_array( $mime, array( 'image/png', 'image/jpeg', 'image/webp' ), true ) ) {
		$info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $data ) : false;
		return is_array( $info ) && isset( $info['mime'], $info[0], $info[1] ) && $info['mime'] === $mime &&
			$info[0] > 0 && $info[1] > 0 && $info[0] <= 3000 && $info[1] <= 3000;
	}
	if ( 'text/plain' === $mime ) {
		return 1 === preg_match( '//u', $data ) && ! preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $data );
	}
	if ( 'application/pdf' === $mime ) {
		if ( 1 !== preg_match( '/^%PDF-(?:1\.[0-7]|2\.0)[\r\n]/', $data ) ||
			1 !== preg_match( '/\b\d+\s+\d+\s+obj\b/', $data ) ||
			1 !== preg_match( '/startxref\s+(\d+)\s+%%EOF\s*$/D', $data, $match ) || strlen( $match[1] ) > 10 ) { return false; }
		$offset = (int) $match[1];
		if ( $offset < 1 || $offset >= strlen( $data ) ) { return false; }
		$xref = substr( $data, $offset );
		return 1 === preg_match( '/^xref(?:\r|\n|\s)/', $xref ) ||
			1 === preg_match( '/^\d+\s+\d+\s+obj\b.{0,65535}?\/Type\s*\/XRef\b/s', $xref );
	}
	return false;
}

function ps_pen_paper_store_handout_asset( $campaign, $path, $filename = null ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ||
		! is_string( $path ) || ! is_file( $path ) || ! is_readable( $path ) || filesize( $path ) > 2 * MB_IN_BYTES ) {
		return new WP_Error( 'invalid_handout_asset', __( 'Die Handoutdatei ist nicht verfügbar oder überschreitet 2 MiB.', 'ps-pen-paper' ) );
	}
	$data = file_get_contents( $path );
	if ( ! is_string( $data ) ) { return new WP_Error( 'invalid_handout_asset', __( 'Die Handoutdatei konnte nicht gelesen werden.', 'ps-pen-paper' ) ); }
	$encoded = base64_encode( $data );
	$extension = null === $filename ? '' : strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	$types = array( 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'pdf' => 'application/pdf', 'txt' => 'text/plain' );
	if ( null === $filename ) {
		$info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $data ) : false;
		$mime = is_array( $info ) && isset( $info['mime'] ) ? $info['mime'] : '';
	} else { $mime = isset( $types[ $extension ] ) ? $types[ $extension ] : ''; }
	if ( ! ps_pen_paper_valid_private_asset_content( $mime, $encoded ) ) {
		return new WP_Error( 'invalid_handout_asset', __( 'Bitte verwende ein gültiges PNG-, JPEG-, WebP-, PDF- oder UTF-8-Textdokument bis 2 MiB.', 'ps-pen-paper' ) );
	}
	$id = wp_insert_post( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $campaign->ID,
		'post_title' => 'PS Pen & Paper Handout', 'post_author' => get_current_user_id(), 'post_mime_type' => $mime,
		'post_content' => $encoded, 'meta_input' => array( '_pspp_handout_asset' => '1' ) ), true );
	if ( is_wp_error( $id ) || ! $id ) { return is_wp_error( $id ) ? $id : new WP_Error( 'save_failed', __( 'Die Handoutdatei konnte nicht gespeichert werden.', 'ps-pen-paper' ) ); }
	if ( array( '1' ) !== get_post_meta( $id, '_pspp_handout_asset', false ) ) {
		wp_delete_post( $id, true );
		return new WP_Error( 'save_failed', __( 'Die Handoutdatei konnte nicht geschützt gespeichert werden.', 'ps-pen-paper' ) );
	}
	return $id;
}

function ps_pen_paper_store_private_image( $campaign_id, $path, $character_id = 0 ) {
	$campaign = ps_pen_paper_get_character_scope( $campaign_id );
	if ( ! ps_pen_paper_can_access_site() || ! $campaign || ( ! ps_pen_paper_can_manage_character_scope( $campaign ) && ! ( $character_id && ps_pen_paper_can_edit_portrait( $campaign, $character_id ) ) ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die Spielleitung darf Bilder hochladen.', 'ps-pen-paper' ) );
	}
	$info = is_string( $path ) && is_file( $path ) && is_readable( $path ) ? wp_getimagesize( $path ) : false;
	if ( ! $info || ! in_array( $info['mime'], array( 'image/png', 'image/jpeg', 'image/webp' ), true ) ||
		filesize( $path ) > 2 * MB_IN_BYTES || $info[0] > 3000 || $info[1] > 3000 ) {
		return new WP_Error( 'invalid_image', __( 'Bitte verwende ein PNG-, JPEG- oder WebP-Bild mit höchstens 2 MiB und 3000 × 3000 Pixeln. SVG und externe Bildadressen sind nicht erlaubt.', 'ps-pen-paper' ) );
	}
	$data = file_get_contents( $path );
	if ( false === $data ) {
		return new WP_Error( 'read_failed', __( 'Das hochgeladene Bild konnte nicht gelesen werden.', 'ps-pen-paper' ) );
	}
	$id = wp_insert_post( array(
		'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $campaign_id,
		'post_title' => 'PS Pen & Paper Image', 'post_author' => get_current_user_id(),
		'post_mime_type' => $info['mime'], 'post_content' => base64_encode( $data ),
	), true );
	return is_wp_error( $id ) || $id ? $id : new WP_Error( 'save_failed', __( 'Das Bild konnte nicht gespeichert werden.', 'ps-pen-paper' ) );
}

function ps_pen_paper_save_backdrop( $campaign_id, $path, $revision ) {
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! current_user_can( 'read' ) || ! $campaign || ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die Spielleitung darf den Hintergrund ändern.', 'ps-pen-paper' ) );
	}
	$previous = ps_pen_paper_get_backdrop( $campaign_id );
	if ( is_wp_error( $previous ) ) {
		return $previous;
	}
	if ( ! is_string( $revision ) || (string) $previous['revision'] !== $revision ) {
		return new WP_Error( 'conflict', __( 'Der Hintergrund wurde inzwischen geändert. Bitte lade die Seite neu.', 'ps-pen-paper' ) );
	}
	$asset_id = 0;
	if ( null !== $path ) {
		$asset_id = ps_pen_paper_store_private_image( $campaign_id, $path );
		if ( is_wp_error( $asset_id ) ) { return $asset_id; }
	}
	$after = array( 'revision' => $previous['revision'] + 1, 'asset_id' => $asset_id );
	$saved = 0 === $previous['revision']
		? add_post_meta( $campaign_id, '_pspp_backdrop', $after, true )
		: update_post_meta( $campaign_id, '_pspp_backdrop', $after, $previous );
	if ( ! $saved ) {
		if ( $asset_id && ! wp_delete_post( $asset_id, true ) ) {
			return new WP_Error( 'cleanup_failed', __( 'Ein nicht zugeordneter Hintergrund konnte nicht entfernt werden. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
		}
		return new WP_Error( 'conflict', __( 'Der Hintergrund konnte nicht zugeordnet werden. Bitte lade die Seite neu.', 'ps-pen-paper' ) );
	}
	if ( $previous['asset_id'] ) {
		$old = ps_pen_paper_record_post( $previous['asset_id'] );
		if ( $old && ( 'pspp_asset' !== $old->post_type || (int) $old->post_parent !== (int) $campaign_id || ! wp_delete_post( $old->ID, true ) ) ) {
			return new WP_Error( 'cleanup_failed', __( 'Der neue Hintergrund ist gespeichert, aber der alte konnte nicht entfernt werden. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
		}
	}
	return $after;
}

function ps_pen_paper_delete_campaign_assets( $post_id, $post ) {
	if ( ! in_array( $post->post_type, array( 'pspp_campaign', 'pspp_universe' ), true ) ) {
		return;
	}
	$assets = get_posts( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $post_id, 'posts_per_page' => -1, 'fields' => 'ids' ) );
	foreach ( $assets as $id ) {
		if ( ! wp_delete_post( $id, true ) ) {
			ps_pen_paper_fail( __( 'Ein privates Kampagnenbild konnte nicht gelöscht werden. Die Kampagnenlöschung wurde angehalten.', 'ps-pen-paper' ) );
		}
	}
}
