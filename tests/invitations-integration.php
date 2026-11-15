<?php
/**
 * Run with WP-CLI against a disposable development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
ps_pen_paper_register_campaign_type();
ps_pen_paper_register_event_type();
$original_user = get_current_user_id();
$users = array();
$campaign_id = 0;
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
try {
	foreach ( array( 'gm', 'invited', 'other' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-invite-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( ! is_wp_error( $id ), 'User creation failed.' );
		$users[ $role ] = $id;
	}
	$campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_title' => 'INVITATION_PRIVATE_TITLE', 'post_author' => $users['gm'] ), true );
	$check( ! is_wp_error( $campaign_id ) && $campaign_id, 'Campaign creation failed.' );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_change_invitation( $campaign_id, $users['invited'], 'invite', '0' ), 'Invitation failed.' );
	$check( is_wp_error( ps_pen_paper_change_invitation( $campaign_id, $users['other'], 'invite', '0' ) ), 'Stale invitation revision accepted.' );
	wp_set_current_user( $users['other'] );
	$check( is_wp_error( ps_pen_paper_change_invitation( $campaign_id, $users['invited'], 'accept', '1', '0' ) ) &&
		false === strpos( ps_pen_paper_render_campaign_preview( $campaign, 1 ), 'INVITATION_PRIVATE_TITLE' ), 'Uninvited user gained access or saw private title.' );
	wp_set_current_user( $users['invited'] );
	$html = ps_pen_paper_render_campaign_preview( $campaign, 1 );
	$check( false !== strpos( $html, 'INVITATION_PRIVATE_TITLE' ) && false !== strpos( $html, 'accept_invitation' ) &&
		! ps_pen_paper_can_view_campaign( $campaign ), 'Invitation not shown privately or granted access prematurely.' );
	$check( is_wp_error( ps_pen_paper_change_invitation( $campaign_id, $users['invited'], 'accept', '1', '1' ) ) &&
		! ps_pen_paper_can_view_campaign( $campaign ), 'Stale membership revision accepted.' );
	$failure = static function ( $data ) { if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) { $data['post_status'] = 'draft'; } return $data; };
	add_filter( 'wp_insert_post_data', $failure );
	try {
		$check( is_wp_error( ps_pen_paper_change_invitation( $campaign_id, $users['invited'], 'accept', '1', '0' ) ) &&
			! ps_pen_paper_can_view_campaign( $campaign ) && array( $users['invited'] ) === ps_pen_paper_invitations( $campaign_id )['users'], 'Journal failure did not roll back acceptance.' );
	} finally { remove_filter( 'wp_insert_post_data', $failure ); }
	$check( true === ps_pen_paper_change_invitation( $campaign_id, $users['invited'], 'accept', '1', '0' ) &&
		'player' === ps_pen_paper_campaign_role( $campaign, $users['invited'] ) && ! ps_pen_paper_invitations( $campaign_id )['users'], 'Acceptance did not atomically grant only the player role.' );
	$check( is_wp_error( ps_pen_paper_change_invitation( $campaign_id, $users['invited'], 'accept', '1', '0' ) ), 'Invitation reused.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_change_invitation( $campaign_id, $users['other'], 'invite', '2' ), 'Second invitation failed.' );
	$events = ps_pen_paper_get_events( $campaign );
	$check( ! is_wp_error( $events ) && 'invitation_saved' === $events['events'][0]['kind'], 'Private invitation history unavailable.' );
	$check( true === ps_pen_paper_change_invitation( $campaign_id, $users['other'], 'revoke', '3' ), 'Revocation failed.' );
	wp_set_current_user( $users['other'] );
	$check( is_wp_error( ps_pen_paper_change_invitation( $campaign_id, $users['other'], 'accept', '4', '1' ) ) &&
		! ps_pen_paper_can_view_campaign( $campaign ), 'Revoked invitation remained usable.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_change_invitation( $campaign_id, $users['other'], 'invite', '4' ), 'Reinvitation failed.' );
	wp_set_current_user( $users['other'] );
	$check( true === ps_pen_paper_change_invitation( $campaign_id, $users['other'], 'decline', '5', '1' ) &&
		! ps_pen_paper_can_view_campaign( $campaign ), 'Declined invitation granted access.' );
	$check( ! ps_pen_paper_valid_invitations( array( 'revision' => 1, 'users' => array( $users['other'], $users['other'] ) ) ), 'Duplicate recipients accepted.' );
	WP_CLI::success( 'Private invitations, revisions, rollback, acceptance, revocation and decline passed.' );
} finally {
	wp_set_current_user( $original_user );
	if ( $campaign_id && ! is_wp_error( $campaign_id ) ) {
		foreach ( get_posts( array( 'post_type' => 'pspp_event', 'post_status' => array( 'private', 'draft' ), 'post_parent' => $campaign_id, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
		wp_delete_post( $campaign_id, true );
	}
	foreach ( $users as $id ) { is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ); }
}
