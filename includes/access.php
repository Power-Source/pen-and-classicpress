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

function ps_pen_paper_valid_invitations( $state ) {
	if ( ! is_array( $state ) || array( 'revision', 'users' ) !== array_keys( $state ) ||
		! is_int( $state['revision'] ) || $state['revision'] < 0 || ! is_array( $state['users'] ) ||
		count( $state['users'] ) > 500 || ( 0 === $state['revision'] && $state['users'] ) ) { return false; }
	foreach ( $state['users'] as $user_id ) {
		if ( ! is_int( $user_id ) || $user_id < 1 ) { return false; }
	}
	return array_values( $state['users'] ) === $state['users'] &&
		count( $state['users'] ) === count( array_unique( $state['users'], SORT_REGULAR ) );
}

function ps_pen_paper_invitations( $campaign_id ) {
	$state = get_post_meta( $campaign_id, '_pspp_invitations', true );
	if ( '' === $state ) { return array( 'revision' => 0, 'users' => array() ); }
	return ps_pen_paper_valid_invitations( $state ) && $state['revision'] > 0 ? $state :
		new WP_Error( 'invalid_invitations', __( 'Die Einladungen sind beschädigt. Bitte informiere die Administration.', 'ps-pen-paper' ) );
}

function ps_pen_paper_change_invitation( $campaign_id, $user_id, $operation, $revision, $membership_revision = null ) {
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	$self = in_array( $operation, array( 'accept', 'decline' ), true );
	if ( ! in_array( $operation, array( 'invite', 'revoke', 'accept', 'decline' ), true ) || ! is_int( $user_id ) || $user_id < 1 ||
		! $campaign || ! ps_pen_paper_can_access_site() ||
		( $self ? get_current_user_id() !== $user_id : ! ps_pen_paper_can_manage_campaign( $campaign ) ) ) {
		return new WP_Error( 'forbidden', __( 'Diese Einladung ist nicht verfügbar.', 'ps-pen-paper' ) );
	}
	return ps_pen_paper_atomic_event( $campaign_id, function () use ( $campaign_id, $user_id, $operation, $revision, $membership_revision, $self ) {
		$campaign = ps_pen_paper_get_campaign( $campaign_id );
		$user = ps_pen_paper_site_user( $user_id );
		if ( ! $campaign || ! ps_pen_paper_can_access_site() || ( 'revoke' !== $operation && ( ! $user || ! user_can( $user, 'read' ) ) ) ||
			( $self ? get_current_user_id() !== $user_id : ! ps_pen_paper_can_manage_campaign( $campaign ) ) ) {
			return new WP_Error( 'forbidden', __( 'Diese Einladung ist nicht verfügbar.', 'ps-pen-paper' ) );
		}
		$before = ps_pen_paper_invitations( $campaign_id );
		if ( is_wp_error( $before ) ) { return $before; }
		if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision ) {
			return new WP_Error( 'conflict', __( 'Die Einladungen wurden geändert. Bitte lade die Seite neu.', 'ps-pen-paper' ) );
		}
		$index = array_search( $user_id, $before['users'], true );
		if ( ( 'invite' === $operation && ( false !== $index || ps_pen_paper_campaign_role( $campaign, $user_id ) ) ) ||
			( 'invite' !== $operation && false === $index ) ) {
			return new WP_Error( 'conflict', __( 'Die Einladung ist nicht mehr offen oder die Person ist bereits Mitglied.', 'ps-pen-paper' ) );
		}
		$after = $before; $after['revision']++;
		if ( 'invite' === $operation ) { $after['users'][] = $user_id; }
		else { array_splice( $after['users'], $index, 1 ); }
		if ( ! ps_pen_paper_valid_invitations( $after ) ) {
			return new WP_Error( 'invalid_invitations', __( 'Es können höchstens 500 Einladungen offen sein.', 'ps-pen-paper' ) );
		}
		if ( 'accept' === $operation ) {
			$membership = ps_pen_paper_membership_revision( $campaign_id );
			if ( is_wp_error( $membership ) ) { return $membership; }
			if ( ! is_string( $membership_revision ) || (string) $membership['revision'] !== $membership_revision ||
				ps_pen_paper_campaign_role( $campaign, $user_id ) ) {
				return new WP_Error( 'conflict', __( 'Die Mitgliederverwaltung wurde geändert. Bitte lade die Einladung neu.', 'ps-pen-paper' ) );
			}
			$request = get_post_meta( $campaign_id, '_pspp_join_' . $user_id, true );
			$member_before = array( 'role' => '', 'request' => $request );
			$member_after = array( 'role' => 'player', 'request' => 'pending' === $request ? 'approved' : $request );
			if ( ! ps_pen_paper_valid_membership_snapshot( $member_before ) ||
				( 'pending' === $request && ! update_post_meta( $campaign_id, '_pspp_join_' . $user_id, 'approved', 'pending' ) ) ||
				! add_post_meta( $campaign_id, '_pspp_player', (string) $user_id ) ||
				'player' !== ps_pen_paper_campaign_role( $campaign, $user_id ) ) {
				return new WP_Error( 'save_failed', __( 'Die Mitgliedschaft konnte nicht gespeichert werden.', 'ps-pen-paper' ) );
			}
			$event = ps_pen_paper_write_document_event( $campaign_id, $campaign_id, '_pspp_membership', $membership,
				array( 'revision' => $membership['revision'] + 1 ), array(
					'kind' => 'membership_saved', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0,
					'member_id' => $user_id, 'member_name' => $user->display_name, 'member_before' => $member_before, 'member_after' => $member_after,
				), false );
			if ( is_wp_error( $event ) ) { return $event; }
		}
		return ps_pen_paper_write_document_event( $campaign_id, $campaign_id, '_pspp_invitations', $before, $after,
			array( 'kind' => 'invitation_saved', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
	} );
}
