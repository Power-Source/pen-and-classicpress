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
ps_pen_paper_register_roll_type();

$original_user = get_current_user_id();
$users = array();
$campaign_id = 0;
$check = function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};
$call = function ( $method, $input = null ) use ( &$campaign_id ) {
	$request = new WP_REST_Request( $method, '/ps-pen-paper/v1/campaigns/' . $campaign_id . '/table' );
	if ( null !== $input ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $input ) );
	}
	return rest_do_request( $request );
};
try {
	foreach ( array( 'owner', 'gm', 'player', 'outsider' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-table-' . $role . '-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( ! is_wp_error( $id ), 'Fixture user failed.' );
		$users[ $role ] = $id;
	}
	$campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_title' => 'Table fixture', 'post_author' => $users['owner'] ), true );
	$check( ! is_wp_error( $campaign_id ) && $campaign_id, 'Fixture campaign failed.' );
	add_post_meta( $campaign_id, '_pspp_gm', (string) $users['gm'] );
	add_post_meta( $campaign_id, '_pspp_player', (string) $users['player'] );
	$room = array( 'id' => ps_pen_paper_chat_id( $campaign_id ), 'blog_id' => get_current_blog_id(), 'session_type' => 'page', 'access_provider' => 'ps-pen-paper', 'moderator_roles' => array( 'subscriber' ) );

	wp_set_current_user( $users['outsider'] );
	$check( 403 === $call( 'GET' )->get_status(), 'Nonmember can read table.' );
	$check( ! psource_chat_session_access( $room ) && ! psource_chat_is_moderator( $room ), 'Nonmember can enter or moderate chat.' );
	wp_set_current_user( 0 );
	$check( 403 === $call( 'GET' )->get_status() && ! psource_chat_session_access( $room ), 'Guest can read table or chat.' );

	wp_set_current_user( $users['player'] );
	$state = $call( 'GET' )->get_data();
	$check( 3 === count( $state['members'] ) && ! array_diff( array_column( $state['members'], 'status' ), array( 'unreported' ) ), 'Roster leaks outsiders or fabricates online status.' );
	$check( psource_chat_session_access( $room ) && ! psource_chat_is_moderator( $room ), 'Chat membership failed or client roles grant moderation.' );
	$check( 403 === $call( 'POST', array( 'operation' => 'turn', 'user_id' => $users['player'], 'revision' => 0 ) )->get_status(), 'Player changed turn.' );
	$check( 409 === $call( 'POST', array( 'operation' => 'heartbeat' ) )->get_status(), 'Heartbeat enabled presence without consent.' );
	$check( 400 === $call( 'POST', array( 'operation' => 'unknown' ) )->get_status(), 'Unknown action accepted.' );
	$check( 200 === $call( 'POST', array( 'operation' => 'join' ) )->get_status(), 'Presence join failed.' );
	$away = $call( 'POST', array( 'operation' => 'afk', 'note' => '<b>Küche</b>' ) )->get_data();
	$player = array_values( array_filter( $away['members'], function ( $member ) use ( $users ) { return $member['id'] === $users['player']; } ) )[0];
	$check( 'afk' === $player['status'] && 'Küche' === $player['note'], 'AFK status or plain-text note failed.' );
	$call( 'POST', array( 'operation' => 'heartbeat' ) );
	$check( 'Küche' === get_transient( ps_pen_paper_table_key( $campaign_id, $users['player'], 'away' ) ), 'Heartbeat cleared deliberate AFK.' );
	set_transient( ps_pen_paper_table_key( $campaign_id, $users['player'], 'seen' ), time() - 121, DAY_IN_SECONDS );
	$offline = $call( 'GET' )->get_data();
	$check( 'offline' === $offline['members'][2]['status'] && '' === $offline['members'][2]['note'], 'Expired connection still appears AFK online or exposes old note.' );
	$check( 200 === $call( 'POST', array( 'operation' => 'back' ) )->get_status() && false === get_transient( ps_pen_paper_table_key( $campaign_id, $users['player'], 'away' ) ), 'Return did not clear AFK.' );
	$call( 'POST', array( 'operation' => 'leave' ) );
	$check( 409 === $call( 'POST', array( 'operation' => 'heartbeat' ) )->get_status(), 'Another tab reenabled presence after leaving.' );

	wp_set_current_user( $users['gm'] );
	$check( psource_chat_is_moderator( $room ), 'Campaign GM is not room moderator.' );
	$check( 400 === $call( 'POST', array( 'operation' => 'turn', 'user_id' => $users['outsider'], 'revision' => 0 ) )->get_status(), 'Outsider can be assigned a turn.' );
	$turn = $call( 'POST', array( 'operation' => 'turn', 'user_id' => $users['player'], 'revision' => 0 ) );
	$check( 200 === $turn->get_status() && 1 === $turn->get_data()['turn']['revision'], 'GM turn assignment did not persist.' );
	$check( 409 === $call( 'POST', array( 'operation' => 'turn', 'user_id' => 0, 'revision' => 0 ) )->get_status(), 'Stale turn overwrite accepted.' );
	$events = ps_pen_paper_get_events( ps_pen_paper_get_campaign( $campaign_id ), 1 );
	$check( 1 === count( $events['events'] ) && 'turn_changed' === $events['events'][0]['kind'], 'Turn event was not recorded.' );
	ob_start();
	ps_pen_paper_render_event_details( $events['events'][0] );
	$details = ob_get_clean();
	$check( false !== strpos( $details, 'Freies Spiel' ) && false !== strpos( $details, esc_html( get_userdata( $users['player'] )->display_name ) ), 'Turn before/after details missing.' );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	$roll_rules = ps_pen_paper_save_rules( $campaign_id, array( 'name' => 'Chat dice', 'description' => '', 'dice' => array( '6' ),
		'roll_visibilities' => array( 'group', 'private', 'gm' ), 'fields' => array() ), '0' );
	$check( ! is_wp_error( $roll_rules ), 'Chat dice rules failed.' );
	$public_roll = 0;
	foreach ( array( 'group', 'private', 'gm' ) as $visibility ) {
		$roll = ps_pen_paper_roll_dice( $campaign_id, array( 'expression' => 'W6', 'visibility' => $visibility,
			'recipient' => (string) $users['owner'], 'character' => '', 'attribute' => '', 'label' => $visibility ), '1' );
		$check( is_int( $roll ), 'Chat dice fixture failed: ' . $visibility );
		if ( 'group' === $visibility ) { $public_roll = $roll; }
	}
	$chat_rolls = ps_pen_paper_chat_rolls( $campaign );
	$check( array( $public_roll ) === array_column( $chat_rolls, 'id' ), 'GM chat received private or hidden rolls.' );
	wp_set_current_user( $users['player'] );
	$check( $chat_rolls === $call( 'GET' )->get_data()['chat_rolls'], 'Player and GM do not receive the same public roll feed.' );
	wp_set_current_user( $users['gm'] );

	delete_post_meta( $campaign_id, '_pspp_gm', (string) $users['gm'] );
	$check( ! psource_chat_session_access( $room ) && ! psource_chat_is_moderator( $room ) && 403 === $call( 'GET' )->get_status(), 'Removed GM retained chat or table access.' );
	wp_set_current_user( $users['owner'] );
	delete_post_meta( $campaign_id, '_pspp_player', (string) $users['player'] );
	$check( false === $call( 'GET' )->get_data()['turn']['available'], 'Removed turn participant still appears available.' );
	$forged = $room;
	$forged['blog_id'] = get_current_blog_id() + 1;
	$check( ! psource_chat_session_access( $forged ), 'Foreign-site room accepted.' );
	remove_filter( 'psource_chat_session_access', 'ps_pen_paper_chat_access', 10 );
	$check( ! psource_chat_session_access( $room ), 'Protected room opens when integration is absent.' );
	add_filter( 'psource_chat_session_access', 'ps_pen_paper_chat_access', 10, 2 );
} finally {
	wp_set_current_user( $original_user );
	$errors = array();
	if ( $campaign_id && ! is_wp_error( $campaign_id ) ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_event', 'pspp_roll' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $campaign_id, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
			if ( ! wp_delete_post( $id, true ) ) { $errors[] = 'Event cleanup failed.'; }
		}
		foreach ( $users as $id ) {
			delete_transient( ps_pen_paper_table_key( $campaign_id, $id, 'seen' ) );
			delete_transient( ps_pen_paper_table_key( $campaign_id, $id, 'away' ) );
		}
		if ( ! wp_delete_post( $campaign_id, true ) ) { $errors[] = 'Campaign cleanup failed.'; }
	}
	foreach ( $users as $id ) {
		if ( ! ( is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ) ) ) { $errors[] = 'User cleanup failed.'; }
	}
	if ( $errors ) { WP_CLI::error( implode( ' ', $errors ) ); }
}
WP_CLI::success( 'Table authorization, AFK lifecycle, turn conflicts/history, room moderation and revocation passed.' );
