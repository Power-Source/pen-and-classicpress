<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_play_friends_available() {
	return defined( 'CPC_CORE_PLUGINS' ) && in_array( 'core-friendships', explode( ',', CPC_CORE_PLUGINS ), true ) &&
		function_exists( 'cpc_get_friends' ) && function_exists( 'cpc_are_friends' ) && function_exists( 'cpc_is_blocked_either_direction' );
}

function ps_pen_paper_play_friend_allowed( $user_id, $friend_id ) {
	if ( ! ps_pen_paper_play_friends_available() || $user_id === $friend_id || ! ps_pen_paper_site_user( $friend_id ) || ! user_can( $friend_id, 'read' ) ||
		cpc_is_blocked_either_direction( $user_id, $friend_id ) ) { return false; }
	$friendship = cpc_are_friends( $user_id, $friend_id );
	return is_array( $friendship ) && 'publish' === ( $friendship['status'] ?? '' );
}

function ps_pen_paper_play_friend_choices() {
	if ( ! ps_pen_paper_play_friends_available() ) {
		return new WP_Error( 'community_friends_required', __( 'Hierfür muss die Freundschaftsfunktion von PS Community aktiviert sein.', 'ps-pen-paper' ) );
	}
	$query = $GLOBALS['wp_query']; $post = $GLOBALS['post'] ?? null;
	try { $friends = cpc_get_friends( get_current_user_id(), false ); }
	finally {
		$GLOBALS['wp_query'] = $query; $GLOBALS['post'] = $post;
		if ( $post instanceof WP_Post ) { setup_postdata( $post ); }
	}
	$result = array();
	foreach ( $friends as $friend ) {
		$id = (int) $friend['ID'];
		if ( ps_pen_paper_play_friend_allowed( get_current_user_id(), $id ) ) {
			$user = ps_pen_paper_site_user( $id );
			if ( user_can( $user, 'read' ) ) { $result[ $id ] = $user->display_name; }
		}
	}
	return $result;
}

function ps_pen_paper_valid_play_invitations( $entries ) {
	if ( ! is_array( $entries ) || array_values( $entries ) !== $entries || ! $entries || count( $entries ) > 99 ) { return false; }
	$ids = array();
	foreach ( $entries as $entry ) {
		if ( ! is_array( $entry ) || array( 'user_id', 'inviter_id', 'status' ) !== array_keys( $entry ) ||
			! is_int( $entry['user_id'] ) || $entry['user_id'] < 1 || isset( $ids[ $entry['user_id'] ] ) ||
			! is_int( $entry['inviter_id'] ) || $entry['inviter_id'] < 1 || $entry['user_id'] === $entry['inviter_id'] ||
			! in_array( $entry['status'], array( 'offered', 'accepted', 'declined', 'revoked' ), true ) ) { return false; }
		$ids[ $entry['user_id'] ] = true;
	}
	return true;
}

function ps_pen_paper_prepare_play_friends( $source, $friends ) {
	if ( ! is_array( $friends ) || array_values( $friends ) !== $friends || ! $friends || count( $friends ) > 99 ||
		count( array_unique( $friends, SORT_REGULAR ) ) !== count( $friends ) ) { return ps_pen_paper_play_error(); }
	$entries = array();
	foreach ( $friends as $id ) {
		if ( ! is_int( $id ) || $id < 1 || ! ps_pen_paper_play_friend_allowed( get_current_user_id(), $id ) ) {
			return ps_pen_paper_play_error( __( 'Wähle bestehende, nicht blockierte PS-Community-Freunde dieser Website.', 'ps-pen-paper' ) );
		}
		if ( ! ps_pen_paper_campaign_role( $source, $id ) ) {
			$invitations = ps_pen_paper_member_invitations( $source->ID );
			$legacy = ps_pen_paper_invitations( $source->ID );
			if ( is_wp_error( $invitations ) || is_wp_error( $legacy ) ) { return ps_pen_paper_play_error(); }
			if ( ! in_array( $id, array_column( $invitations['entries'], 'user_id' ), true ) && ! in_array( $id, $legacy['users'], true ) ) {
				$sent = ps_pen_paper_write_member_invitation( $source->ID, $id, 'invite', (string) $invitations['revision'] );
				if ( is_wp_error( $sent ) ) { return $sent; }
			}
		}
		$entries[] = array( 'user_id' => $id, 'inviter_id' => get_current_user_id(), 'status' => 'offered' );
	}
	return $entries;
}

function ps_pen_paper_play_invitation_index( $state, $user_id ) {
	return array_search( $user_id, array_column( $state['invitations'] ?? array(), 'user_id' ), true );
}

function ps_pen_paper_can_decline_play_invitation( $state ) {
	$index = ps_pen_paper_play_invitation_index( $state, get_current_user_id() );
	return false !== $index && 'offered' === $state['invitations'][ $index ]['status'];
}

function ps_pen_paper_can_open_friend_round( $state ) {
	return ! isset( $state['invitations'] ) || $state['host_id'] === get_current_user_id() ||
		ps_pen_paper_play_participant( $state, get_current_user_id() ) ||
		( false !== ( $index = ps_pen_paper_play_invitation_index( $state, get_current_user_id() ) ) &&
			in_array( $state['invitations'][ $index ]['status'], array( 'offered', 'accepted' ), true ) &&
			ps_pen_paper_play_friend_allowed( get_current_user_id(), $state['invitations'][ $index ]['inviter_id'] ) );
}
