<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_get_access( $campaign_id ) {
	$access = get_post_meta( $campaign_id, '_pspp_access', true );
	if ( '' === $access ) {
		return array( 'revision' => 0, 'visibility' => 'private', 'admission' => 'closed', 'teaser' => '' );
	}
	if ( ! is_array( $access ) || ! isset( $access['revision'], $access['visibility'], $access['admission'], $access['teaser'] ) ||
		! is_int( $access['revision'] ) || $access['revision'] < 1 ||
		! in_array( $access['visibility'], array( 'private', 'teaser' ), true ) ||
		! in_array( $access['admission'], array( 'closed', 'approval' ), true ) || ! is_string( $access['teaser'] ) ) {
		return new WP_Error( 'invalid_access', __( 'Die Zugangseinstellungen sind beschädigt. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	return $access;
}

function ps_pen_paper_save_access( $campaign_id, $input, $revision ) {
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! current_user_can( 'read' ) || ! $campaign || ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die Spielleitung darf den Zugang ändern.', 'ps-pen-paper' ) );
	}
	$previous = ps_pen_paper_get_access( $campaign_id );
	if ( is_wp_error( $previous ) ) {
		return $previous;
	}
	if ( ! is_string( $revision ) || (string) $previous['revision'] !== $revision ) {
		return new WP_Error( 'conflict', __( 'Die Zugangseinstellungen wurden inzwischen geändert. Bitte lade die Seite neu.', 'ps-pen-paper' ) );
	}
	if ( ! is_array( $input ) || count( $input ) !== 3 || ! isset( $input['visibility'], $input['admission'], $input['teaser'] ) ||
		! in_array( $input['visibility'], array( 'private', 'teaser' ), true ) ||
		! in_array( $input['admission'], array( 'closed', 'approval' ), true ) ||
		! is_string( $input['teaser'] ) || strlen( $input['teaser'] ) > 6000 ) {
		return new WP_Error( 'invalid_access', __( 'Bitte wähle gültige Zugangseinstellungen und einen Teaser mit höchstens 6000 Bytes.', 'ps-pen-paper' ) );
	}
	$after = array( 'revision' => $previous['revision'] + 1, 'visibility' => $input['visibility'], 'admission' => $input['admission'], 'teaser' => sanitize_textarea_field( $input['teaser'] ) );
	$saved = ps_pen_paper_save_document_event( $campaign_id, $campaign_id, '_pspp_access', $previous, $after, array(
		'kind' => 'access_saved', 'visibility' => 'gm', 'recipient_id' => 0,
		'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $campaign_id )['revision'],
	) );
	return is_wp_error( $saved ) ? $saved : $after;
}

function ps_pen_paper_request_membership( $campaign_id ) {
	return ps_pen_paper_change_membership( $campaign_id, get_current_user_id(), 'request' );
}

function ps_pen_paper_review_membership( $campaign_id, $user_id, $decision, $revision = null ) {
	if ( ! in_array( $decision, array( 'approved', 'rejected' ), true ) ) {
		return new WP_Error( 'invalid_request', __( 'Die Person oder Entscheidung ist ungültig.', 'ps-pen-paper' ) );
	}
	return ps_pen_paper_change_membership( $campaign_id, $user_id, $decision, '', $revision );
}

function ps_pen_paper_pending_memberships( $campaign ) {
	if ( ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return array();
	}
	$requests = array();
	foreach ( get_post_meta( $campaign->ID ) as $key => $values ) {
		if ( preg_match( '/^_pspp_join_([1-9]\d*)$/D', $key, $match ) && array( 'pending' ) === $values ) {
			$user = ps_pen_paper_site_user( (int) $match[1] );
			if ( $user && user_can( $user, 'read' ) ) {
				$requests[] = $user;
			}
		}
	}
	return $requests;
}
