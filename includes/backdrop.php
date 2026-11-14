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
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Dieses Bild ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	$asset = get_post( $asset_id );
	if ( ! $asset || 'pspp_asset' !== $asset->post_type || 'private' !== $asset->post_status ||
		(int) $asset->post_parent !== (int) $campaign->ID ||
		! in_array( $asset->post_mime_type, array( 'image/png', 'image/jpeg', 'image/webp' ), true ) ||
		! preg_match( '/^[A-Za-z0-9+\/]+=*$/D', $asset->post_content ) ) {
		return new WP_Error( 'invalid_backdrop', __( 'Der Hintergrund ist nicht mehr verfügbar. Bitte lade ihn in den Rundeneinstellungen erneut hoch.', 'ps-pen-paper' ) );
	}
	return 'data:' . $asset->post_mime_type . ';base64,' . $asset->post_content;
}

function ps_pen_paper_store_private_image( $campaign_id, $path ) {
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! ps_pen_paper_can_access_site() || ! $campaign || ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
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
		$old = get_post( $previous['asset_id'] );
		if ( $old && ( 'pspp_asset' !== $old->post_type || (int) $old->post_parent !== (int) $campaign_id || ! wp_delete_post( $old->ID, true ) ) ) {
			return new WP_Error( 'cleanup_failed', __( 'Der neue Hintergrund ist gespeichert, aber der alte konnte nicht entfernt werden. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
		}
	}
	return $after;
}

function ps_pen_paper_delete_campaign_assets( $post_id, $post ) {
	if ( 'pspp_campaign' !== $post->post_type ) {
		return;
	}
	$assets = get_posts( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $post_id, 'posts_per_page' => -1, 'fields' => 'ids' ) );
	foreach ( $assets as $id ) {
		if ( ! wp_delete_post( $id, true ) ) {
			ps_pen_paper_fail( __( 'Ein privates Kampagnenbild konnte nicht gelöscht werden. Die Kampagnenlöschung wurde angehalten.', 'ps-pen-paper' ) );
		}
	}
}
