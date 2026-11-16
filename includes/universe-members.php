<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_universe_members( $id ) {
	$state = get_post_meta( $id, '_pspp_universe_members', true );
	if ( '' === $state ) { return array( 'revision' => 0, 'users' => array() ); }
	return ps_pen_paper_valid_invitations( $state ) && $state['revision'] > 0 ? $state :
		new WP_Error( 'invalid_members', __( 'Die Universumsmitgliedschaften sind beschädigt.', 'ps-pen-paper' ) );
}

function ps_pen_paper_valid_member_invitations( $state ) {
	if ( ! is_array( $state ) || array( 'revision', 'entries' ) !== array_keys( $state ) || ! is_int( $state['revision'] ) ||
		$state['revision'] < 0 || ! is_array( $state['entries'] ) || count( $state['entries'] ) > 500 ||
		array_values( $state['entries'] ) !== $state['entries'] || ( 0 === $state['revision'] && $state['entries'] ) ) { return false; }
	$users = array();
	foreach ( $state['entries'] as $entry ) {
		if ( ! is_array( $entry ) || array( 'user_id', 'inviter_id', 'status' ) !== array_keys( $entry ) ||
			! is_int( $entry['user_id'] ) || $entry['user_id'] < 1 || ! is_int( $entry['inviter_id'] ) || $entry['inviter_id'] < 1 ||
			! in_array( $entry['status'], array( 'approval', 'offered' ), true ) || in_array( $entry['user_id'], $users, true ) ) { return false; }
		$users[] = $entry['user_id'];
	}
	return true;
}

function ps_pen_paper_member_invitations( $id ) {
	$state = get_post_meta( $id, '_pspp_member_invitations', true );
	if ( '' === $state ) { return array( 'revision' => 0, 'entries' => array() ); }
	return ps_pen_paper_valid_member_invitations( $state ) && $state['revision'] > 0 ? $state :
		new WP_Error( 'invalid_invitations', __( 'Die Mitgliedereinladungen sind beschädigt.', 'ps-pen-paper' ) );
}

function ps_pen_paper_invitation_scope_member( $scope, $user_id ) {
	return 'pspp_universe' === $scope->post_type ? ps_pen_paper_is_universe_member( $scope, $user_id ) : (bool) ps_pen_paper_campaign_role( $scope, $user_id );
}

function ps_pen_paper_change_member_invitation( $scope_id, $user_id, $operation, $revision ) {
	$scope = ps_pen_paper_get_character_scope( $scope_id );
	if ( ! $scope || ! ps_pen_paper_can_access_site() || ! is_int( $user_id ) || $user_id < 1 ||
		! in_array( $operation, array( 'invite', 'approve', 'reject', 'accept', 'decline', 'revoke' ), true ) ) {
		return new WP_Error( 'forbidden', __( 'Diese Einladung ist nicht verfügbar.', 'ps-pen-paper' ) );
	}
	return ps_pen_paper_atomic_event( $scope_id, function () use ( $scope_id, $user_id, $operation, $revision ) {
		return ps_pen_paper_write_member_invitation( $scope_id, $user_id, $operation, $revision );
	} );
}

function ps_pen_paper_write_member_invitation( $scope_id, $user_id, $operation, $revision ) {
		$scope = ps_pen_paper_get_character_scope( $scope_id );
		$user = ps_pen_paper_site_user( $user_id );
		if ( ! $scope || ! ps_pen_paper_can_access_site() || ! $user || ! user_can( $user, 'read' ) ) { return new WP_Error( 'forbidden', __( 'Diese Website-Person ist nicht verfügbar.', 'ps-pen-paper' ) ); }
		$is_gm = ps_pen_paper_can_manage_character_scope( $scope );
		$self = get_current_user_id() === $user_id;
		$before = ps_pen_paper_member_invitations( $scope_id );
		if ( is_wp_error( $before ) ) { return $before; }
		if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision ) { return new WP_Error( 'conflict', __( 'Die Einladungen wurden geändert. Bitte lade die Übersicht neu.', 'ps-pen-paper' ) ); }
		$index = array_search( $user_id, array_column( $before['entries'], 'user_id' ), true );
		$entry = false === $index ? null : $before['entries'][ $index ];
		if ( ( 'invite' === $operation && ( ! ps_pen_paper_invitation_scope_member( $scope, get_current_user_id() ) && ! $is_gm ) ) ||
			( in_array( $operation, array( 'approve', 'reject' ), true ) && ! $is_gm ) ||
			( in_array( $operation, array( 'accept', 'decline' ), true ) && ! $self ) ||
			( 'revoke' === $operation && ! $is_gm && ( ! $entry || $entry['inviter_id'] !== get_current_user_id() ) ) ) {
			return new WP_Error( 'forbidden', __( 'Du darfst diese Einladung nicht ändern.', 'ps-pen-paper' ) );
		}
		$after = $before; $after['revision']++;
		if ( 'invite' === $operation ) {
			if ( $self || $entry || ps_pen_paper_invitation_scope_member( $scope, $user_id ) ) { return new WP_Error( 'conflict', __( 'Die Person ist bereits Mitglied oder eingeladen.', 'ps-pen-paper' ) ); }
			if ( 'pspp_campaign' === $scope->post_type ) {
				$legacy = ps_pen_paper_invitations( $scope_id );
				if ( is_wp_error( $legacy ) ) { return $legacy; }
				if ( in_array( $user_id, $legacy['users'], true ) ) { return new WP_Error( 'conflict', __( 'Für diese Person besteht bereits eine Einladung.', 'ps-pen-paper' ) ); }
			}
			$universe_id = 'pspp_universe' === $scope->post_type ? $scope_id : 0;
			if ( ! $universe_id ) {
				$link = ps_pen_paper_get_universe_link( $scope_id );
				if ( is_wp_error( $link ) ) { return $link; }
				$universe_id = $link['universe_id'];
			}
			$policy = $universe_id ? ps_pen_paper_character_policy( $universe_id ) : ps_pen_paper_character_policy_defaults();
			if ( is_wp_error( $policy ) ) { return $policy; }
			$after['entries'][] = array( 'user_id' => $user_id, 'inviter_id' => get_current_user_id(),
				'status' => $is_gm || 'direct' === $policy['member_invites'] ? 'offered' : 'approval' );
		} else {
			if ( ! $entry || ( in_array( $operation, array( 'approve', 'reject' ), true ) && 'approval' !== $entry['status'] ) ||
				( 'accept' === $operation && 'offered' !== $entry['status'] ) ) { return new WP_Error( 'approval_required', __( 'Die Einladung ist nicht mehr offen oder benötigt zuerst die Freigabe der Spielleitung.', 'ps-pen-paper' ) ); }
			if ( 'approve' === $operation ) { $after['entries'][ $index ]['status'] = 'offered'; }
			else { array_splice( $after['entries'], $index, 1 ); }
			if ( 'accept' === $operation ) {
				if ( ps_pen_paper_invitation_scope_member( $scope, $user_id ) ) { return new WP_Error( 'conflict', __( 'Du bist bereits Mitglied.', 'ps-pen-paper' ) ); }
				if ( 'pspp_universe' === $scope->post_type ) {
					$members = ps_pen_paper_universe_members( $scope_id );
					if ( is_wp_error( $members ) ) { return $members; }
					$joined = $members; $joined['revision']++; $joined['users'][] = $user_id;
					if ( ! ps_pen_paper_valid_invitations( $joined ) ) { return new WP_Error( 'member_limit', __( 'Die maximale Zahl expliziter Universumsmitglieder ist erreicht.', 'ps-pen-paper' ) ); }
					$event = ps_pen_paper_write_document_event( $scope_id, $scope_id, '_pspp_universe_members', $members, $joined,
						array( 'kind' => 'universe_members_saved', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
				} else {
					$members = ps_pen_paper_membership_revision( $scope_id );
					if ( is_wp_error( $members ) ) { return $members; }
					$request = get_post_meta( $scope_id, '_pspp_join_' . $user_id, true );
					$member_before = array( 'role' => '', 'request' => $request );
					$member_after = array( 'role' => 'player', 'request' => 'pending' === $request ? 'approved' : $request );
					if ( ! ps_pen_paper_valid_membership_snapshot( $member_before ) ||
						( 'pending' === $request && ! update_post_meta( $scope_id, '_pspp_join_' . $user_id, 'approved', 'pending' ) ) ||
						! add_post_meta( $scope_id, '_pspp_player', (string) $user_id ) || 'player' !== ps_pen_paper_campaign_role( $scope, $user_id ) ) {
						return new WP_Error( 'save_failed', __( 'Die Mitgliedschaft konnte nicht gespeichert werden.', 'ps-pen-paper' ) );
					}
					$event = ps_pen_paper_write_document_event( $scope_id, $scope_id, '_pspp_membership', $members, array( 'revision' => $members['revision'] + 1 ),
						array( 'kind' => 'membership_saved', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0,
							'member_id' => $user_id, 'member_name' => $user->display_name, 'member_before' => $member_before, 'member_after' => $member_after ), false );
				}
				if ( is_wp_error( $event ) ) { return $event; }
			}
		}
		if ( ! ps_pen_paper_valid_member_invitations( $after ) ) { return new WP_Error( 'invitation_limit', __( 'Es können höchstens 500 Einladungen offen sein.', 'ps-pen-paper' ) ); }
		return ps_pen_paper_write_document_event( $scope_id, $scope_id, '_pspp_member_invitations', $before, $after,
			array( 'kind' => 'member_invitation_saved', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
}

function ps_pen_paper_profile_invitations() {
	$result = array( 'received' => array(), 'approvals' => array(), 'sent' => array(), 'errors' => array() );
	if ( ! ps_pen_paper_can_access_site() ) { return $result; }
	foreach ( get_posts( array( 'post_type' => array( 'pspp_universe', 'pspp_campaign' ), 'post_status' => 'private', 'posts_per_page' => -1 ) ) as $scope ) {
		$state = ps_pen_paper_member_invitations( $scope->ID );
		$is_gm = ps_pen_paper_can_manage_character_scope( $scope );
		if ( is_wp_error( $state ) ) { if ( $is_gm ) { $result['errors'][] = $state->get_error_message(); } continue; }
		foreach ( $state['entries'] as $entry ) {
			$self = $entry['user_id'] === get_current_user_id();
			$sent = $entry['inviter_id'] === get_current_user_id();
			if ( ! $self && ! $sent && ! $is_gm ) { continue; }
			$document = 'pspp_universe' === $scope->post_type ? ps_pen_paper_universe_document( $scope->ID ) : null;
			$row = array_merge( $entry, array( 'scope_id' => (int) $scope->ID, 'scope_type' => $scope->post_type,
				'name' => $document && ! is_wp_error( $document ) ? $document['name'] : $scope->post_title, 'revision' => (string) $state['revision'] ) );
			if ( $self ) { $result['received'][] = $row; }
			if ( $sent ) { $result['sent'][] = $row; }
			if ( $is_gm && 'approval' === $entry['status'] ) { $result['approvals'][] = $row; }
		}
		if ( 'pspp_campaign' === $scope->post_type ) {
			$legacy = ps_pen_paper_invitations( $scope->ID );
			if ( ! is_wp_error( $legacy ) && in_array( get_current_user_id(), $legacy['users'], true ) ) {
				$membership = ps_pen_paper_membership_revision( $scope->ID );
				if ( ! is_wp_error( $membership ) ) {
					$result['received'][] = array( 'scope_id' => (int) $scope->ID, 'scope_type' => $scope->post_type, 'name' => $scope->post_title,
						'user_id' => get_current_user_id(), 'inviter_id' => (int) $scope->post_author, 'status' => 'offered', 'revision' => (string) $legacy['revision'],
						'legacy' => true, 'membership_revision' => (string) $membership['revision'] );
				}
			}
		}
	}
	return $result;
}
