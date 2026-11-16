<?php
/**
 * Run with WP-CLI against a disposable development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
ps_pen_paper_register_campaign_type();
ps_pen_paper_register_scene_type();
ps_pen_paper_register_asset_type();
ps_pen_paper_register_event_type();
$original_user = get_current_user_id();
$users = array();
$parents = array();
$path = '';
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$call = function ( $campaign_id, $suffix = '/map', $input = null, $view = null ) {
	$request = new WP_REST_Request( null === $input ? 'GET' : 'POST', '/ps-pen-paper/v1/campaigns/' . $campaign_id . $suffix );
	if ( null !== $view ) { $request->set_header( 'X-PSPP-View', $view ); }
	if ( null !== $input ) { $request->set_header( 'Content-Type', 'application/json' ); $request->set_body( wp_json_encode( $input ) ); }
	return rest_do_request( $request );
};
try {
	foreach ( array( 'gm', 'player', 'other', 'outsider' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-map-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( ! is_wp_error( $id ), 'Fixture account failed.' );
		$users[ $role ] = $id;
	}
	foreach ( array( 'A', 'B' ) as $name ) {
		$id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Map fixture ' . $name ), true );
		$check( ! is_wp_error( $id ) && $id, 'Campaign fixture failed.' );
		$parents[] = $id;
		add_post_meta( $id, '_pspp_gm', (string) $users['gm'] );
		add_post_meta( $id, '_pspp_player', (string) $users['player'] );
		add_post_meta( $id, '_pspp_player', (string) $users['other'] );
	}
	$campaign = ps_pen_paper_get_campaign( $parents[0] );
	$path = wp_tempnam( 'pspp-map-test.png' );
	$check( false !== file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=' ) ), 'Image fixture failed.' );
	$input = array( 'title' => 'PRIVATE_DRAFT', 'text' => 'PUBLIC_TEXT', 'secret' => 'GM_SECRET', 'grid' => '50', 'tokens' => array(
		array( 'id' => '', 'name' => 'Mara', 'type' => 'player', 'owner_id' => (string) $users['player'], 'color' => '#d6a858', 'x' => '50', 'y' => '50' ),
		array( 'id' => '', 'name' => 'Wache', 'type' => 'npc', 'owner_id' => '0', 'color' => '#c76b73', 'x' => '70', 'y' => '50' ),
		array( 'id' => '', 'name' => 'Leitungsfigur', 'type' => 'player', 'owner_id' => (string) $users['gm'], 'color' => '#d6a858', 'x' => '30', 'y' => '50' ),
	) );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_save_scene( $campaign, 0, $input, '0', $path ) ), 'Player created a scene.' );
	wp_set_current_user( $users['gm'] );
	$scene_id = ps_pen_paper_save_scene( $campaign, 0, $input, '0', $path );
	$check( ! is_wp_error( $scene_id ), 'Scene creation failed.' );
	$scene = ps_pen_paper_get_scene( $campaign, $scene_id );
	$asset_id = $scene['draft']['asset_id'];
	$check( null === ps_pen_paper_map_state( $campaign )['scene'], 'Draft automatically shown.' );
	$check( is_wp_error( ps_pen_paper_publish_scene( $campaign, $scene_id, '1', '' ) ) && is_wp_error( ps_pen_paper_select_scene( $campaign, $scene_id, '0' ) ), 'Unconfirmed/unpublished scene exposed.' );
	$bad = $input;
	$bad['tokens'][0]['owner_id'] = (string) $users['outsider'];
	$check( is_wp_error( ps_pen_paper_save_scene( $campaign, $scene_id, $bad, '1' ) ), 'Outsider received a marker.' );
	wp_set_current_user( $users['player'] );
	$check( 403 === $call( $campaign->ID, '/maps/' . $scene_id . '/images/' . $asset_id )->get_status(), 'Draft image exposed.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_publish_scene( $campaign, $scene_id, '1', 'confirmed' ), 'Publish failed.' );
	$check( null === ps_pen_paper_map_state( $campaign )['scene'] && true === ps_pen_paper_select_scene( $campaign, $scene_id, '0' ), 'Publish unexpectedly switched scene or explicit selection failed.' );
	$player_map = $call( $campaign->ID, '/map', null, 'overview' )->get_data()['scene'];
	$check( ! $player_map['tokens'][0]['can_move'] && ! $player_map['tokens'][1]['can_move'] && $player_map['tokens'][2]['can_move'] &&
		ps_pen_paper_map_state( $campaign )['scene']['tokens'][1]['can_move'], 'GM player map regained foreign markers or lost its own marker.' );
	$check( 400 === $call( $campaign->ID, '/map', null, 'invalid' )->get_status(), 'Invalid map perspective was silently accepted.' );
	$gm_player_move = array( 'operation' => 'move', 'scene_id' => $scene_id, 'publication' => 1, 'revision' => 2,
		'token_id' => $player_map['tokens'][1]['id'], 'x' => 6500, 'y' => 3500 );
	$check( 403 === $call( $campaign->ID, '/map', $gm_player_move, 'overview' )->get_status(), 'GM player map moved a foreign marker.' );
	wp_set_current_user( $users['player'] );
	$check( ! $call( $campaign->ID, '/map', null, 'lead' )->get_data()['scene']['tokens'][1]['can_move'], 'Leadership view header granted authority to a player.' );
	$response = $call( $campaign->ID );
	$visible = $response->get_data()['scene'];
	$check( 200 === $response->get_status() && false === strpos( wp_json_encode( $response->get_data() ), 'GM_SECRET' ) && ! isset( $visible['draft'] ) && $visible['tokens'][0]['can_move'] && ! $visible['tokens'][1]['can_move'], 'Published view leaks secret or movement authority.' );
	$check( 200 === $call( $campaign->ID, '/maps/' . $scene_id . '/images/' . $asset_id )->get_status() && 403 === $call( $parents[1], '/maps/' . $scene_id . '/images/' . $asset_id )->get_status(), 'Image rights/campaign boundary failed.' );
	$move = array( 'operation' => 'move', 'scene_id' => $scene_id, 'publication' => 1, 'revision' => 2, 'token_id' => $visible['tokens'][1]['id'], 'x' => 6500, 'y' => 3500 );
	$check( 403 === $call( $campaign->ID, '/map', $move )->get_status(), 'Player moved an unassigned NPC.' );
	$move['token_id'] = $visible['tokens'][0]['id'];
	$move['x'] = 10001;
	$check( 409 === $call( $campaign->ID, '/map', $move )->get_status(), 'Out-of-map movement accepted.' );
	$move['x'] = 6500;
	// Use a neutral 1000x700 board to verify actual pixel-grid snapping.
	wp_set_current_user( $users['gm'] );
	$raw = ps_pen_paper_get_scene( $campaign, $scene_id );
	$raw['published']['width'] = 1000;
	$raw['published']['height'] = 700;
	update_post_meta( $scene_id, '_pspp_scene', wp_slash( $raw ) );
	wp_set_current_user( $users['player'] );
	$moved = $call( $campaign->ID, '/map', $move );
	$check( 200 === $moved->get_status(), 'Assigned figure did not move.' );
	$visible = $moved->get_data()['scene'];
	$check( 6500 === $visible['tokens'][0]['x'] && 3571 === $visible['tokens'][0]['y'] && 3 === $visible['revision'], 'Grid snapping/position persistence failed.' );
	$check( 409 === $call( $campaign->ID, '/map', $move )->get_status(), 'Stale move overwrote newer position.' );
	$ping = $move;
	$ping['operation'] = 'ping';
	$ping['revision'] = 3;
	$ping['name'] = 'FORGED_NAME';
	$pinged = $call( $campaign->ID, '/map', $ping )->get_data();
	$check( isset( $pinged['ping'] ) && 'FORGED_NAME' !== $pinged['ping']['name'] && $pinged['ping']['expires_in'] <= 15, 'Ping identity/expiry failed.' );
	$events = ps_pen_paper_get_events( $campaign );
	$check( 1 === count( $events['events'] ) && 'scene_selected' === $events['events'][0]['kind'] && false === strpos( wp_json_encode( $events ), 'GM_SECRET' ), 'GM snapshots leaked into player journal.' );
	wp_set_current_user( $users['gm'] );
	$scene = ps_pen_paper_get_scene( $campaign, $scene_id );
	$edit = $input;
	$edit['title'] = 'UNRELEASED_CHANGE';
	$edit['text'] = 'UNRELEASED_TEXT';
	$check( $scene_id === ps_pen_paper_save_scene( $campaign, $scene_id, $edit, '3', $path ), 'Draft update failed.' );
	$new_asset = ps_pen_paper_get_scene( $campaign, $scene_id )['draft']['asset_id'];
	$check( 403 === $call( $campaign->ID, '/maps/' . $scene_id . '/images/' . $new_asset, null, 'overview' )->get_status() &&
		200 === $call( $campaign->ID, '/maps/' . $scene_id . '/images/' . $new_asset, null, 'lead' )->get_status(),
		'GM player image refresh exposed a draft or leadership lost its preview.' );
	$check( is_wp_error( ps_pen_paper_save_scene( $campaign, $scene_id, $edit, '3' ) ), 'Stale draft accepted.' );
	ob_start();
	ps_pen_paper_render_journal( $campaign );
	$history = ob_get_clean();
	$check( false !== strpos( $history, 'GM_SECRET' ), 'GM history lost private preparation.' );
	wp_set_current_user( $users['player'] );
	$check( 'PRIVATE_DRAFT' === $call( $campaign->ID )->get_data()['scene']['title'] && 403 === $call( $campaign->ID, '/maps/' . $scene_id . '/images/' . $new_asset )->get_status(), 'Unpublished draft/image change reached player.' );
	wp_set_current_user( $users['gm'] );
	$before = ps_pen_paper_get_scene( $campaign, $scene_id );
	$asset_count = count( get_posts( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
	$fail = static function ( $data ) { if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) { $data['post_status'] = 'draft'; } return $data; };
	add_filter( 'wp_insert_post_data', $fail );
	try {
		$check( is_wp_error( ps_pen_paper_publish_scene( $campaign, $scene_id, '4', 'confirmed' ) ) && $before === ps_pen_paper_get_scene( $campaign, $scene_id ), 'Failed publication did not roll back.' );
		$check( is_wp_error( ps_pen_paper_save_scene( $campaign, 0, $input, '0', $path ) ) && 1 === count( get_posts( array( 'post_type' => 'pspp_scene', 'post_parent' => $campaign->ID, 'post_status' => array( 'private', 'draft' ), 'posts_per_page' => -1 ) ) ), 'Failed creation left a scene.' );
		$check( $asset_count === count( get_posts( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1, 'fields' => 'ids' ) ) ), 'Failed scene creation left an image.' );
	} finally { remove_filter( 'wp_insert_post_data', $fail ); }
	$check( true === ps_pen_paper_publish_scene( $campaign, $scene_id, '4', 'confirmed', true ), 'Withdrawal failed.' );
	wp_set_current_user( $users['player'] );
	$check( null === $call( $campaign->ID )->get_data()['scene'] && 403 === $call( $campaign->ID, '/maps/' . $scene_id . '/images/' . $asset_id )->get_status(), 'Withdrawn scene/image remained accessible.' );
	delete_post_meta( $campaign->ID, '_pspp_player', (string) $users['player'] );
	$check( 403 === $call( $campaign->ID )->get_status(), 'Removed member retained map access.' );
	wp_set_current_user( $users['outsider'] );
	$check( 403 === $call( $campaign->ID )->get_status(), 'Outsider accessed map.' );
	wp_get_current_user()->add_cap( 'manage_options' );
	$check( 200 === $call( $campaign->ID )->get_status(), 'Website administrator needed a campaign membership.' );
	wp_set_current_user( 0 );
	$check( 403 === $call( $campaign->ID )->get_status(), 'Guest accessed map.' );
} finally {
	wp_set_current_user( $original_user );
	$errors = array();
	foreach ( $parents as $parent ) {
		delete_transient( 'pspp_ping_' . $parent );
		foreach ( get_posts( array( 'post_type' => array( 'pspp_scene', 'pspp_event', 'pspp_asset' ), 'post_parent' => $parent, 'post_status' => array( 'private', 'draft' ), 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
			if ( ! wp_delete_post( $id, true ) ) { $errors[] = 'Cannot remove map child ' . $id; }
		}
		if ( ! wp_delete_post( $parent, true ) ) { $errors[] = 'Cannot remove map campaign ' . $parent; }
	}
	foreach ( $users as $id ) { if ( ! ( is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ) ) ) { $errors[] = 'Cannot remove map user ' . $id; } }
	if ( $path && is_file( $path ) && ! unlink( $path ) ) { $errors[] = 'Cannot remove test image.'; }
	if ( $errors ) { WP_CLI::error( implode( '; ', $errors ) ); }
}
WP_CLI::success( 'Private drafts/images, deliberate publication/selection, owned movement, pixel-grid snapping, revision conflicts, pings, history privacy, withdrawal and publication rollback passed.' );
