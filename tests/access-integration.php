<?php
/**
 * Run with WP-CLI against a disposable development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( "Run this test with WP-CLI.\n" );
}
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once dirname( dirname( __DIR__ ) ) . '/ps-chat/lib/psource_chat_utilities.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) {
	require_once ABSPATH . 'wp-admin/includes/ms.php';
}
ps_pen_paper_register_campaign_type();
ps_pen_paper_register_event_type();
ps_pen_paper_register_asset_type();
$original_user = get_current_user_id();
$original_query = $GLOBALS['wp_query'];
$original_get = $_GET;
$had_super_admins = array_key_exists( 'super_admins', $GLOBALS );
$original_super_admins = $had_super_admins ? $GLOBALS['super_admins'] : null;
$users = array();
$campaign_id = 0;
$page_id = 0;
$file = tmpfile();
$check = function ( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
};
try {
	$check( false !== $file, 'Cannot create private fixture file.' );
	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a0y8AAAAASUVORK5CYII=' );
	fwrite( $file, $png );
	$path = stream_get_meta_data( $file )['uri'];
	foreach ( array( 'owner', 'applicant', 'rejected' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-access-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( ! is_wp_error( $id ), 'Fixture user failed.' );
		$users[ $role ] = $id;
	}
	$campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_title' => 'PREVIEW_TITLE', 'post_content' => 'INTERNAL_STORY_SECRET', 'post_author' => $users['owner'] ), true );
	$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Access fixture', 'post_content' => '[ps_pen_paper]' ), true );
	$check( ! is_wp_error( $campaign_id ) && ! is_wp_error( $page_id ) && $campaign_id && $page_id, 'Fixture posts failed.' );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $page_id ) );
	$_GET = array( 'pspp_campaign' => (string) $campaign_id );
	wp_set_current_user( 0 );
	$check( false === strpos( ps_pen_paper_frontend_shortcode(), 'PREVIEW_TITLE' ), 'Existing private campaign became public.' );
	$check( is_wp_error( ps_pen_paper_request_membership( $campaign_id ) ), 'Guest could apply.' );
	wp_set_current_user( $users['owner'] );
	$access = array( 'visibility' => 'teaser', 'admission' => 'approval', 'teaser' => "PUBLIC_TEASER\nA separate text with a \\ slash." );
	$check( ! is_wp_error( ps_pen_paper_save_access( $campaign_id, $access, '0' ) ), 'Access settings did not save.' );
	$check( $access['teaser'] === ps_pen_paper_get_access( $campaign_id )['teaser'], 'Teaser escaping corrupted text.' );
	$check( is_wp_error( ps_pen_paper_save_access( $campaign_id, $access, '0' ) ), 'Stale access settings overwrote newer revision.' );
	$events = ps_pen_paper_get_events( $campaign );
	$check( ! is_wp_error( $events ) && 'access_saved' === $events['events'][0]['kind'], 'Access history missing.' );
	ob_start();
	ps_pen_paper_render_event_details( $events['events'][0] );
	$details = ob_get_clean();
	$check( false !== strpos( $details, 'PUBLIC_TEASER' ), 'Access before/after details missing.' );
	$event_id = $events['events'][0]['id'];
	$backdrop = ps_pen_paper_save_backdrop( $campaign_id, $path, '0' );
	$check( ! is_wp_error( $backdrop ) && $backdrop['asset_id'] > 0, 'Private backdrop did not save.' );
	$asset_id = $backdrop['asset_id'];
	$check( 'data:image/png;base64,' . base64_encode( $png ) === ps_pen_paper_backdrop_image( $campaign ), 'Backdrop bytes changed or are publicly linked.' );
	$chunk = function ( $type, $data ) { return pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) ); };
	$image = function ( $width, $height ) use ( $chunk ) {
		return "\x89PNG\r\n\x1a\n" . $chunk( 'IHDR', pack( 'NNCCCCC', $width, $height, 8, 0, 0, 0, 0 ) )
			. $chunk( 'IDAT', gzcompress( str_repeat( "\0", ( $width + 1 ) * $height ) ) ) . $chunk( 'IEND', '' );
	};
	foreach ( array( array( 3001, 1 ), array( 1, 3001 ) ) as $dimensions ) {
		ftruncate( $file, 0 );
		rewind( $file );
		fwrite( $file, $image( $dimensions[0], $dimensions[1] ) );
		clearstatcache( true, $path );
		$result = ps_pen_paper_save_backdrop( $campaign_id, $path, '1' );
		$check( is_wp_error( $result ) && 'invalid_image' === $result->get_error_code(), 'Image dimension limit was not enforced.' );
	}
	ftruncate( $file, 0 );
	rewind( $file );
	fwrite( $file, $png );
	ftruncate( $file, 2 * MB_IN_BYTES + 1 );
	clearstatcache( true, $path );
	$result = ps_pen_paper_save_backdrop( $campaign_id, $path, '1' );
	$check( is_wp_error( $result ) && 'invalid_image' === $result->get_error_code(), 'Oversized image accepted.' );
	ftruncate( $file, 0 );
	rewind( $file );
	fwrite( $file, $image( 3000, 1 ) );
	clearstatcache( true, $path );
	$boundary = ps_pen_paper_save_backdrop( $campaign_id, $path, '1' );
	$check( ! is_wp_error( $boundary ) && ! get_post( $asset_id ), 'Valid 3000px image rejected or replacement retained the old bytes.' );
	$asset_id = $boundary['asset_id'];
	$check( is_wp_error( ps_pen_paper_save_backdrop( $campaign_id, null, '0' ) ) && $asset_id === ps_pen_paper_get_backdrop( $campaign_id )['asset_id'], 'Stale backdrop removal accepted.' );
	ftruncate( $file, 0 );
	rewind( $file );
	fwrite( $file, '<svg xmlns="http://www.w3.org/2000/svg"></svg>' );
	clearstatcache( true, $path );
	$result = ps_pen_paper_save_backdrop( $campaign_id, $path, '2' );
	$check( is_wp_error( $result ) && 'invalid_image' === $result->get_error_code(), 'SVG upload accepted.' );
	wp_set_current_user( 0 );
	$html = ps_pen_paper_frontend_shortcode();
	$check( false !== strpos( $html, 'PREVIEW_TITLE' ) && false !== strpos( $html, 'PUBLIC_TEASER' ), 'Explicit guest preview unavailable.' );
	foreach ( array( 'INTERNAL_STORY_SECRET', 'data:image/', 'data-table-endpoint', 'data-table-members', 'pspp-chat-body', 'Teilnahme beantragen' ) as $private ) {
		$check( false === strpos( $html, $private ), 'Guest preview leaks: ' . $private );
	}
	$_GET['pspp_character'] = (string) $asset_id;
	$check( false === strpos( ps_pen_paper_frontend_shortcode(), 'data:image/' ), 'Character parameter bypassed public preview boundary.' );
	unset( $_GET['pspp_character'] );
	$check( is_wp_error( ps_pen_paper_backdrop_image( $campaign ) ), 'Guest read backdrop.' );
	$check( ! get_post_type_object( 'pspp_asset' )->publicly_queryable && ! get_post_type_object( 'pspp_asset' )->show_in_rest, 'Private asset has public core endpoints.' );
	wp_set_current_user( $users['applicant'] );
	$check( is_wp_error( ps_pen_paper_save_access( $campaign_id, $access, '1' ) ) && is_wp_error( ps_pen_paper_save_backdrop( $campaign_id, null, '2' ) ), 'Applicant changed settings.' );
	$check( true === ps_pen_paper_request_membership( $campaign_id ) && true === ps_pen_paper_request_membership( $campaign_id ), 'Request or idempotent retry failed.' );
	$check( 1 === count( get_post_meta( $campaign_id, '_pspp_join_' . $users['applicant'], false ) ), 'Duplicate requests were created.' );
	$check( ! ps_pen_paper_can_view_campaign( $campaign ) && is_wp_error( ps_pen_paper_backdrop_image( $campaign ) ), 'Pending applicant gained private access.' );
	$check( array() === ps_pen_paper_pending_memberships( $campaign ), 'Applicant read other applicants.' );
	$check( is_wp_error( ps_pen_paper_review_membership( $campaign_id, $users['applicant'], 'approved' ) ), 'Applicant self-approved.' );
	$check( is_wp_error( ps_pen_paper_get_event( $campaign, $event_id ) ), 'Applicant read access history.' );
	wp_set_current_user( $users['rejected'] );
	$check( true === ps_pen_paper_request_membership( $campaign_id ), 'Second request failed.' );
	wp_set_current_user( $users['owner'] );
	$check( 2 === count( ps_pen_paper_pending_memberships( $campaign ) ), 'GM cannot see pending requests.' );
	$check( true === ps_pen_paper_review_membership( $campaign_id, $users['applicant'], 'approved' ), 'GM approval failed.' );
	$check( true === ps_pen_paper_review_membership( $campaign_id, $users['rejected'], 'rejected' ), 'GM rejection failed.' );
	$check( is_wp_error( ps_pen_paper_review_membership( $campaign_id, $users['applicant'], 'rejected' ) ), 'Stale review changed an existing decision.' );
	wp_set_current_user( $users['applicant'] );
	$check( ps_pen_paper_can_view_campaign( $campaign ) && ! ps_pen_paper_can_manage_campaign( $campaign ), 'Approval did not grant only the player role.' );
	$check( false !== strpos( ps_pen_paper_frontend_shortcode(), 'INTERNAL_STORY_SECRET' ), 'Approved player cannot enter table.' );
	$check( is_wp_error( ps_pen_paper_get_event( $campaign, $event_id ) ), 'Player read GM-only privacy history.' );
	$room = array( 'id' => ps_pen_paper_chat_id( $campaign_id ), 'blog_id' => get_current_blog_id(), 'session_type' => 'page', 'access_provider' => 'ps-pen-paper' );
	$check( psource_chat_session_access( $room ) && ! psource_chat_is_moderator( $room ), 'Admission did not integrate with protected chat.' );
	wp_set_current_user( $users['rejected'] );
	$check( ! ps_pen_paper_can_view_campaign( $campaign ) && ! psource_chat_session_access( $room ) && is_wp_error( ps_pen_paper_request_membership( $campaign_id ) ), 'Rejected person retained access or can spam repeated requests.' );
	wp_set_current_user( $users['owner'] );
	$access['visibility'] = 'private';
	$check( ! is_wp_error( ps_pen_paper_save_access( $campaign_id, $access, '1' ) ), 'Could not close preview.' );
	$check( ! is_wp_error( ps_pen_paper_save_backdrop( $campaign_id, null, '2' ) ) && ! get_post( $asset_id ), 'Removing backdrop left stored private bytes.' );
	wp_set_current_user( $users['rejected'] );
	$check( false === strpos( ps_pen_paper_frontend_shortcode(), 'PUBLIC_TEASER' ), 'Closing preview still exposes teaser.' );
	if ( is_multisite() ) {
		remove_user_from_blog( $users['rejected'], get_current_blog_id() );
		$GLOBALS['super_admins'] = array_merge( get_super_admins(), array( get_userdata( $users['rejected'] )->user_login ) );
		wp_set_current_user( 0 );
		wp_set_current_user( $users['rejected'] );
		$check( is_super_admin() && ! ps_pen_paper_site_user( $users['rejected'] ), 'Nonmember network administrator fixture failed.' );
		$check( false !== strpos( ps_pen_paper_frontend_shortcode(), 'INTERNAL_STORY_SECRET' ), 'Network superadmin is gated by subsite membership.' );
		$check( true === ps_pen_paper_table_permission( array( 'id' => $campaign_id ) ), 'Network superadmin cannot access table API.' );
		$check( psource_chat_session_access( $room ) && psource_chat_is_moderator( $room ), 'Network superadmin lacks protected chat access/moderation.' );
		$check( ! is_wp_error( ps_pen_paper_get_event( $campaign, $event_id ) ), 'Network superadmin cannot read private administrative history.' );
	}
} finally {
	if ( $had_super_admins ) { $GLOBALS['super_admins'] = $original_super_admins; } else { unset( $GLOBALS['super_admins'] ); }
	$_GET = $original_get;
	$GLOBALS['wp_query'] = $original_query;
	wp_set_current_user( $original_user );
	if ( is_resource( $file ) ) { fclose( $file ); }
	$errors = array();
	if ( $campaign_id && ! is_wp_error( $campaign_id ) ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_event', 'pspp_asset' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $campaign_id, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
			if ( ! wp_delete_post( $id, true ) ) { $errors[] = 'Record cleanup failed.'; }
		}
		if ( ! wp_delete_post( $campaign_id, true ) ) { $errors[] = 'Campaign cleanup failed.'; }
	}
	if ( $page_id && ! is_wp_error( $page_id ) && ! wp_delete_post( $page_id, true ) ) { $errors[] = 'Page cleanup failed.'; }
	foreach ( $users as $id ) {
		if ( ! ( is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ) ) ) { $errors[] = 'User cleanup failed.'; }
	}
	if ( $errors ) { WP_CLI::error( implode( ' ', $errors ) ); }
}
WP_CLI::success( 'Private defaults, explicit teaser boundaries, GM approvals, chat admission, protected images and revision conflicts passed.' );
