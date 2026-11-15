<?php
/**
 * Run with WP-CLI --skip-plugins --skip-themes against a development site.
 * PSPP_TEST_HTTP=1 additionally checks downloads with the plugin active in HTTP requests.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'character', 'event', 'asset', 'scene', 'folio', 'npc', 'wiki', 'item', 'roll' ) as $type ) {
	call_user_func( 'ps_pen_paper_register_' . $type . '_type' );
}
ps_pen_paper_register_loot_types();
ps_pen_paper_register_trade_type();
ps_pen_paper_register_adventure_types();
global $wpdb;
$original_user = get_current_user_id();
$original_cookie = isset( $_COOKIE[LOGGED_IN_COOKIE] ) ? $_COOKIE[LOGGED_IN_COOKIE] : null;
$users = array(); $parents = array(); $extra_posts = array();
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$post = function ( $type, $parent, $author, $title = 'Export fixture' ) use ( $check ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'private', 'post_parent' => $parent,
		'post_author' => $author, 'post_title' => $title ), true );
	$check( is_int( $id ) && $id > 0, 'Fixture record failed.' );
	return $id;
};
$ids = function ( $export ) { return array_column( $export['payload']['records'], 'id' ); };
$record = function ( $export, $id ) {
	foreach ( $export['payload']['records'] as $entry ) { if ( $id === $entry['id'] ) { return $entry; } }
	throw new RuntimeException( 'Expected exported record missing: ' . $id );
};
try {
	foreach ( array( 'world_gm', 'gm', 'player', 'other' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-export-' . wp_generate_password( 12, false ),
			'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( is_int( $id ), 'Fixture account failed.' ); $users[ $role ] = $id;
	}
	get_userdata( $users['world_gm'] )->add_cap( 'ps_pen_paper_create_campaigns' );
	wp_set_current_user( $users['world_gm'] );
	$universe = ps_pen_paper_save_universe( 0, array( 'name' => 'Export universe', 'description' => 'PRIVATE_UNIVERSE' ), '0' );
	$check( is_int( $universe ), 'Universe fixture failed.' ); $parents[] = $universe;
	$rules = ps_pen_paper_save_rules( $universe, array( 'name' => 'Export rules', 'description' => '', 'dice' => array( '6' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Mut', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '10', 'start' => '2' ),
	) ), '0' );
	$check( ! is_wp_error( $rules ), 'Rules fixture failed.' );
	$base = ps_pen_paper_save_character( $universe, 0, $users['player'],
		array( 'name' => 'Shared hero', 'values' => array( $rules['fields'][0]['id'] => '2' ) ), '0', '1' );
	$unused_base = ps_pen_paper_save_character( $universe, 0, $users['other'],
		array( 'name' => 'UNRELATED_BASE', 'values' => array( $rules['fields'][0]['id'] => '2' ) ), '0', '1' );
	$check( is_int( $base ) && is_int( $unused_base ), 'Base fixtures failed.' );
	$campaign = $post( 'pspp_campaign', 0, $users['gm'], 'Export campaign' ); $parents[] = $campaign;
	add_post_meta( $campaign, '_pspp_gm', (string) $users['world_gm'] );
	add_post_meta( $campaign, '_pspp_player', (string) $users['player'] );
	add_post_meta( $campaign, '_pspp_player', (string) $users['other'] );
	$check( ! is_wp_error( ps_pen_paper_save_universe_link( $campaign, $universe, '0', '1', 'confirmed' ) ) &&
		! is_wp_error( ps_pen_paper_adopt_universe_rules( $campaign, '1', 'confirmed' ) ), 'Link/rules fixtures failed.' );
	$shared = ps_pen_paper_attach_base_character( $campaign, $base, '1', '1', 'shared', 'confirmed' );
	$copy = ps_pen_paper_attach_base_character( $campaign, $unused_base, '1', '1', 'copy', 'confirmed' );
	$check( is_int( $shared ) && is_int( $copy ), 'Context fixtures failed.' );
	add_post_meta( $unused_base, '_pspp_export_private', 'NON_IMPORTED_BASE_SECRET' );
	$other_campaign = $post( 'pspp_campaign', 0, $users['gm'], 'UNRELATED_CAMPAIGN' ); $parents[] = $other_campaign;
	$foreign_scene = $post( 'pspp_scene', $other_campaign, $users['gm'], 'UNRELATED_SCENE' );
	$ordinary_post = $post( 'post', $campaign, $users['gm'], 'NON_PLUGIN_CHILD' ); $extra_posts[] = $ordinary_post;
	add_post_meta( $campaign, '_unrelated_plugin_secret', 'NOT_EXPORTABLE' );
	$image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=';
	$images = array();
	foreach ( array( 'local' => $campaign, 'world' => $universe, 'unused' => $universe ) as $name => $scope ) {
		$images[ $name ] = $post( 'pspp_asset', $scope, $users['world_gm'] );
		wp_update_post( array( 'ID' => $images[ $name ], 'post_content' => $image, 'post_mime_type' => 'image/png' ) );
	}
	add_post_meta( $campaign, '_pspp_backdrop', array( 'revision' => 1, 'asset_id' => $images['local'] ) );
	add_post_meta( $universe, '_pspp_sheet_style', array( 'revision' => 1, 'asset_id' => $images['world'] ) );
	add_post_meta( $unused_base, '_pspp_portrait', array( 'revision' => 1, 'active' => array( 'asset_id' => $images['unused'] ), 'pending' => null ) );
	add_post_meta( $base, '_pspp_wallet', array( 'revision' => 1, 'balance' => 13 ) );
	add_post_meta( $base, '_pspp_gear', array( 'revision' => 1, 'active' => array( 'items' => array(), 'slots' => array() ), 'pending' => null ) );
	$base_log = $post( 'pspp_event', $universe, $users['world_gm'] );
	add_post_meta( $base_log, '_pspp_event', array( 'object_id' => $base, 'before' => array( 'balance' => 0 ), 'after' => array( 'balance' => 13 ) ) );
	$unused_log = $post( 'pspp_event', $universe, $users['world_gm'] );
	add_post_meta( $unused_log, '_pspp_event', array( 'object_id' => $unused_base, 'note' => 'UNRELATED_HISTORY' ) );
	$catalog = $post( 'pspp_item', $universe, $users['world_gm'] );
	add_post_meta( $catalog, '_pspp_item', array( 'revision' => 1, 'item' => array( 'name' => 'Shared catalog item' ) ) );
	$local_records = array( $images['local'], $shared, $copy );
	$snapshot = array( 'revision' => 3, 'name' => 'Prüfung mit Umlauten: Größe',
		'null' => null, 'flag' => false, 'integer' => 0, 'string' => '0', 'quotes' => "O'Neil\\Pfad\nNeue Zeile",
		'rows' => array( array( 'id' => wp_generate_uuid4(), 'amount' => '2' ), array( 'id' => wp_generate_uuid4(), 'amount' => '1' ) ) );
	foreach ( array( 'pspp_scene', 'pspp_folio', 'pspp_npc', 'pspp_item', 'pspp_roll', 'pspp_event',
		'pspp_loot_table', 'pspp_loot_run', 'pspp_trade', 'pspp_adventure', 'pspp_adventure_run' ) as $type ) {
		$id = $post( $type, $campaign, $users['gm'] ); $local_records[] = $id;
		add_post_meta( $id, '_pspp_export_fixture', wp_slash( $snapshot ) );
	}
	$draft = $post( 'pspp_scene', $campaign, $users['gm'] ); $local_records[] = $draft;
	wp_update_post( array( 'ID' => $draft, 'post_status' => 'draft' ) );
	foreach ( array( 'player', 'other' ) as $role ) {
		wp_set_current_user( $users[ $role ] );
		$check( is_wp_error( ps_pen_paper_export_campaign( $campaign, 'local' ) ), 'Player exported private campaign data.' );
	}
	wp_set_current_user( 0 );
	$check( is_wp_error( ps_pen_paper_export_campaign( $campaign, 'complete' ) ), 'Guest exported data.' );
	wp_set_current_user( $users['gm'] );
	$standalone = ps_pen_paper_export_campaign( $other_campaign, 'complete' );
	$check( is_array( $standalone ) && $ids( $standalone ) === array( $other_campaign, $foreign_scene ), 'Standalone campaign required universe permissions or leaked another scope.' );
	$local = ps_pen_paper_export_campaign( $campaign, 'local' );
	$check( is_array( $local ), 'Local export failed.' );
	$check( array() === array_diff( $local_records, $ids( $local ) ) && ! in_array( $universe, $ids( $local ), true ) &&
		! in_array( $base, $ids( $local ), true ), 'Local scope incomplete or leaked base data.' );
	$check( in_array( array( 'kind' => 'universe', 'id' => $universe, 'included' => false ), $local['payload']['dependencies'], true ) &&
		in_array( array( 'kind' => 'shared_character', 'id' => $base, 'universe_id' => $universe, 'included' => false ), $local['payload']['dependencies'], true ),
		'Local export concealed external dependencies.' );
	$check( is_wp_error( ps_pen_paper_export_campaign( $campaign, 'complete' ) ), 'Campaign GM exported private universe data.' );
	ob_start(); ps_pen_paper_render_export_settings( ps_pen_paper_get_campaign( $campaign ) ); $html = ob_get_clean();
	$check( 1 === preg_match( '/value="complete"\s+disabled/', $html ) && false !== strpos( $html, 'export_confirmation' ), 'Permission/confirmation missing in export UI.' );
	wp_set_current_user( $users['world_gm'] );
	$complete = ps_pen_paper_export_campaign( $campaign, 'complete' );
	$check( is_array( $complete ), 'Complete export failed.' );
	$check( array() === array_diff( array( $universe, $base, $base_log, $catalog, $images['world'] ), $ids( $complete ) ), 'Required universe data missing.' );
	$check( array() === array_intersect( array( $other_campaign, $foreign_scene, $ordinary_post, $unused_base, $unused_log, $images['unused'] ), $ids( $complete ) ),
		'Complete export included unrelated records.' );
	foreach ( array( 'UNRELATED_CAMPAIGN', 'UNRELATED_SCENE', 'UNRELATED_HISTORY', 'NON_IMPORTED_BASE_SECRET', 'NOT_EXPORTABLE', 'NON_PLUGIN_CHILD' ) as $secret ) {
		$check( false === strpos( json_encode( $complete ), $secret ), 'Unrelated private data leaked: ' . $secret );
	}
	$roundtrip = json_decode( json_encode( $complete, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), true );
	$check( $roundtrip === $complete && $image === $record( $roundtrip, $images['local'] )['content'], 'JSON round-trip changed stored data or image bytes.' );
	$check( $record( $roundtrip, $local_records[3] )['meta'][0]['value'] === serialize( $snapshot ), 'Serialized snapshot changed types, ordering or escaping.' );
	$check( count( array_filter( $record( $complete, $campaign )['meta'], function ( $entry ) { return '_pspp_player' === $entry['key']; } ) ) === 2,
		'Repeated membership metadata collapsed.' );
	$check( $complete['payload_sha256'] === hash( 'sha256', json_encode( $roundtrip['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ), 'Checksum cannot be reproduced.' );
	$modified = $roundtrip['payload']; $modified['records'][0]['title'] .= ' changed';
	$check( $complete['payload_sha256'] !== hash( 'sha256', json_encode( $modified, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ), 'Checksum missed a changed document.' );
	$check( 'draft' === $record( $complete, $draft )['status'] && 13 === ps_pen_paper_export_meta( $record( $complete, $base ), '_pspp_wallet' )['balance'],
		'Draft or shared balance was not preserved.' );
	$before = $complete['payload'];
	$again = ps_pen_paper_export_campaign( $campaign, 'complete' );
	$check( is_array( $again ) && $before === $again['payload'], 'Export changed stored data or identifiers.' );
	add_post_meta( $campaign, '_pspp_sheet_style', array( 'revision' => 1, 'asset_id' => 2147483647 ) );
	$check( is_wp_error( ps_pen_paper_export_campaign( $campaign, 'complete' ) ), 'Complete export silently omitted a required image.' );
	$missing = ps_pen_paper_export_campaign( $campaign, 'local' );
	$check( is_array( $missing ) && in_array( array( 'kind' => 'asset', 'id' => 2147483647, 'referenced_by' => $campaign,
		'historical' => false, 'included' => false ), $missing['payload']['dependencies'], true ), 'Local export hid unavailable image data.' );
	delete_post_meta( $campaign, '_pspp_sheet_style' );
	$history = $post( 'pspp_event', $campaign, $users['gm'] );
	add_post_meta( $history, '_pspp_event', array( 'object_id' => $campaign, 'before' => array( 'asset_id' => 2147483647 ) ) );
	$historical = ps_pen_paper_export_campaign( $campaign, 'complete' );
	$check( is_array( $historical ) && in_array( array( 'kind' => 'asset', 'id' => 2147483647, 'referenced_by' => $history,
		'historical' => true, 'included' => false ), $historical['payload']['dependencies'], true ), 'Removed historical image was hidden or blocked the current backup.' );
	wp_delete_post( $history, true );
	$raw = get_post_meta( $shared, '_pspp_character', true );
	$bad = $raw; $bad['context']['source_id'] = $copy;
	update_post_meta( $shared, '_pspp_character', $bad );
	$check( is_wp_error( ps_pen_paper_export_campaign( $campaign, 'complete' ) ) && is_array( ps_pen_paper_export_campaign( $campaign, 'local' ) ),
		'Foreign-scope base reference was accepted or prevented local backup.' );
	update_post_meta( $shared, '_pspp_character', $raw );
	$link = get_post_meta( $campaign, '_pspp_universe_link', true );
	update_post_meta( $campaign, '_pspp_universe_link', array( 'revision' => 2, 'universe_id' => 0, 'name' => '' ) );
	$check( is_wp_error( ps_pen_paper_export_campaign( $campaign, 'complete' ) ), 'Orphaned shared context was exported as complete.' );
	update_post_meta( $campaign, '_pspp_universe_link', $link );
	wp_set_current_user( $users['world_gm'] );
	ob_start(); ps_pen_paper_render_export_settings( ps_pen_paper_get_campaign( $campaign ), 42 ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'pspp_frontend_page' ) && false !== strpos( $html, 'pspp-export-dependencies' ) &&
		false !== strpos( $html, 'target="_blank" rel="noopener" data-pspp-download' ) &&
		0 === preg_match( '/value="complete"\s+disabled/', $html ), 'Frontend export UI missing, navigating away or full export incorrectly disabled.' );
	wp_set_current_user( $users['player'] );
	ob_start(); ps_pen_paper_render_export_settings( ps_pen_paper_get_campaign( $campaign ) ); $html = ob_get_clean();
	$check( '' === $html, 'Player saw export controls.' );
	wp_set_current_user( $users['gm'] );
	$revoke = function ( $query ) use ( $campaign, $users ) {
		if ( false !== strpos( $query, 'p.post_mime_type, p.post_date_gmt' ) ) {
			wp_update_post( array( 'ID' => $campaign, 'post_author' => $users['other'] ) );
		}
		return $query;
	};
	add_filter( 'query', $revoke );
	try { $check( is_wp_error( ps_pen_paper_export_campaign( $campaign, 'local' ) ), 'Cached authorization survived a revoked campaign role.' ); }
	finally { remove_filter( 'query', $revoke ); wp_update_post( array( 'ID' => $campaign, 'post_author' => $users['gm'] ) ); }
	wp_set_current_user( $users['world_gm'] );
	$change_link = function ( $query ) use ( $campaign ) {
		if ( false !== strpos( $query, 'p.post_mime_type, p.post_date_gmt' ) ) {
			update_post_meta( $campaign, '_pspp_universe_link', array( 'revision' => 2, 'universe_id' => 0, 'name' => '' ) );
		}
		return $query;
	};
	$link = get_post_meta( $campaign, '_pspp_universe_link', true );
	add_filter( 'query', $change_link );
	try { $check( is_wp_error( ps_pen_paper_export_campaign( $campaign, 'complete' ) ), 'Changed scope generated a mixed backup.' ); }
	finally { remove_filter( 'query', $change_link ); update_post_meta( $campaign, '_pspp_universe_link', $link ); }
	$fail_query = function ( $query ) {
		return false !== strpos( $query, 'p.post_mime_type, p.post_date_gmt' ) ? 'SELECT pspp_export_invalid_column' : $query;
	};
	$suppressed = $wpdb->suppress_errors( true ); add_filter( 'query', $fail_query );
	try { $check( is_wp_error( ps_pen_paper_export_campaign( $campaign, 'local' ) ), 'Database failure produced a success-shaped backup.' ); }
	finally { remove_filter( 'query', $fail_query ); $wpdb->suppress_errors( $suppressed ); }
	if ( '1' === getenv( 'PSPP_TEST_HTTP' ) ) {
		$token = WP_Session_Tokens::get_instance( $users['world_gm'] )->create( time() + 300 );
		$_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $users['world_gm'], time() + 300, 'logged_in', $token );
		$auth = wp_generate_auth_cookie( $users['world_gm'], time() + 300, 'auth', $token );
		$cookie = LOGGED_IN_COOKIE . '=' . $_COOKIE[LOGGED_IN_COOKIE] . '; ' . AUTH_COOKIE . '=' . $auth;
		// Model an initialized Easy Blogging session instead of its first-login start screen.
		$cookie .= '; ' . ( is_multisite() ? 'wdeb_on_site_' . get_current_blog_id() : 'wdeb_on' ) . '=1';
		$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Export HTTP ' . wp_generate_password( 8, false ),
			'post_content' => '[ps_pen_paper]', 'post_author' => $users['world_gm'] ), true );
		$check( is_int( $page ), 'HTTP fixture page failed.' ); $extra_posts[] = $page;
		$body = array( 'action' => 'ps_pen_paper', 'pspp_operation' => 'export_campaign', 'campaign_id' => $campaign,
			'export_mode' => 'complete', 'export_confirmation' => 'confirmed', '_wpnonce' => wp_create_nonce( 'pspp_export_' . $campaign ) );
		foreach ( array( admin_url( 'admin-post.php' ), get_permalink( $page ) ) as $index => $url ) {
			$input = $body;
			if ( $index ) { $input['pspp_frontend_page'] = $page; }
			$response = wp_remote_post( $url, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Cookie' => $cookie ), 'body' => $input ) );
			$diagnostic = is_wp_error( $response ) ? $response->get_error_message() :
				'status ' . wp_remote_retrieve_response_code( $response ) . ', redirect ' .
				wp_parse_url( wp_remote_retrieve_header( $response, 'location' ), PHP_URL_PATH );
			$check( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ), 'HTTP download ' . $index . ' failed: ' . $diagnostic );
			$download = json_decode( wp_remote_retrieve_body( $response ), true );
			$check( is_array( $download ), 'HTTP download ' . $index . ' is not JSON: ' . json_last_error_msg() );
			$expected = ps_pen_paper_export_campaign( $campaign, 'complete' );
			$check( 'ps-pen-paper-campaign' === $download['format'] && $download['payload']['records'] === $expected['payload']['records'],
				'HTTP download ' . $index . ' differs from snapshot; record IDs: ' . implode( ',', $ids( $download ) ) . ' versus ' . implode( ',', $ids( $expected ) ) );
			$check( false !== strpos( wp_remote_retrieve_header( $response, 'content-disposition' ), 'attachment;' ) &&
				false !== strpos( wp_remote_retrieve_header( $response, 'cache-control' ), 'no-store' ) &&
				'nosniff' === wp_remote_retrieve_header( $response, 'x-content-type-options' ), 'Download/privacy headers missing.' );
		}
		foreach ( array( array( '_wpnonce' => 'invalid' ), array( 'export_confirmation' => '' ), array( 'export_mode' => 'invalid' ) ) as $invalid ) {
			$response = wp_remote_post( admin_url( 'admin-post.php' ), array( 'timeout' => 30, 'redirection' => 0,
				'headers' => array( 'Cookie' => $cookie ), 'body' => array_merge( $body, $invalid ) ) );
			$check( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) >= 400 &&
				'' === wp_remote_retrieve_header( $response, 'content-disposition' ), 'Invalid request generated a download.' );
		}
	}
	WP_CLI::success( 'Campaign export integration passed' . ( '1' === getenv( 'PSPP_TEST_HTTP' ) ? ' including backend/frontend HTTP downloads.' : '.' ) );
} finally {
	wp_set_current_user( $original_user );
	if ( null === $original_cookie ) { unset( $_COOKIE[LOGGED_IN_COOKIE] ); } else { $_COOKIE[LOGGED_IN_COOKIE] = $original_cookie; }
	foreach ( $extra_posts as $id ) { if ( ! wp_delete_post( $id, true ) ) { WP_CLI::warning( 'Fixture post cleanup failed: ' . $id ); } }
	foreach ( $parents as $id ) {
		$children = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent = %d", $id ) );
		foreach ( $children as $child ) { if ( ! wp_delete_post( (int) $child, true ) ) { WP_CLI::warning( 'Fixture child cleanup failed: ' . $child ); } }
		if ( ! wp_delete_post( $id, true ) ) { WP_CLI::warning( 'Fixture scope cleanup failed: ' . $id ); }
	}
	foreach ( $users as $id ) {
		if ( ! ( is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ) ) ) { WP_CLI::warning( 'Fixture user cleanup failed: ' . $id ); }
	}
}
