<?php
/** Run serially with WP-CLI against a disposable development site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'character', 'event' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
$original_user = get_current_user_id(); $original_get = $_GET; $original_post = $_POST; $original_request = $_REQUEST;
$users = array(); $scopes = array(); $page = 0;
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$capture = function ( $page_id ) {
	ob_start();
	try { ps_pen_paper_render_settings_directory( $page_id ); return ob_get_contents(); }
	finally { ob_end_clean(); }
};
$xpath = function ( $html ) {
	$document = new DOMDocument(); $previous = libxml_use_internal_errors( true );
	try { $document->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>' ); }
	finally { libxml_clear_errors(); libxml_use_internal_errors( $previous ); }
	return new DOMXPath( $document );
};
try {
	foreach ( array( 'gm', 'other', 'player' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-settings-' . wp_generate_password( 14, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( is_int( $id ), 'Fixture account creation failed.' ); $users[ $role ] = $id;
	}
	foreach ( array( 'gm', 'other' ) as $role ) { get_userdata( $users[ $role ] )->add_cap( 'ps_pen_paper_create_campaigns' ); }
	$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Settings test page', 'post_content' => '[ps_pen_paper]' ), true );
	$check( is_int( $page ), 'Fixture page failed.' );
	wp_set_current_user( $users['gm'] );
	$world_a = ps_pen_paper_save_universe( 0, array( 'name' => 'Settings world A', 'description' => '' ), '0' );
	$world_b = ps_pen_paper_save_universe( 0, array( 'name' => 'Settings world B', 'description' => '' ), '0' );
	$check( is_int( $world_a ) && is_int( $world_b ), 'Owned worlds failed.' ); $scopes[] = $world_a; $scopes[] = $world_b;
	$campaign_a = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Campaign A' ), true );
	$check( is_int( $campaign_a ), 'Campaign A failed.' ); $scopes[] = $campaign_a;
	add_post_meta( $campaign_a, '_pspp_gm', (string) $users['gm'] );
	$check( ! is_wp_error( ps_pen_paper_link_new_campaign( $campaign_a, $world_a, '1' ) ), 'Campaign A association failed.' );
	$legacy = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Legacy fixture' ), true );
	$check( is_int( $legacy ), 'Legacy fixture failed.' ); $scopes[] = $legacy; add_post_meta( $legacy, '_pspp_gm', (string) $users['gm'] );
	$round = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Private runtime round' ), true );
	$check( is_int( $round ), 'Round fixture failed.' ); $scopes[] = $round;
	add_post_meta( $round, '_pspp_gm', (string) $users['gm'] ); add_post_meta( $round, '_pspp_play_round', array( 'fixture' => true ) );
	wp_set_current_user( $users['other'] );
	$world_c = ps_pen_paper_save_universe( 0, array( 'name' => 'External settings world', 'description' => 'WORLD_SECRET' ), '0' );
	$check( is_int( $world_c ), 'External world failed.' ); $scopes[] = $world_c;
	$campaign_c = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['other'], 'post_title' => 'Shared campaign' ), true );
	$check( is_int( $campaign_c ), 'Shared campaign failed.' ); $scopes[] = $campaign_c;
	add_post_meta( $campaign_c, '_pspp_gm', (string) $users['other'] ); add_post_meta( $campaign_c, '_pspp_gm', (string) $users['gm'] );
	$check( ! is_wp_error( ps_pen_paper_link_new_campaign( $campaign_c, $world_c, '1' ) ), 'Shared campaign association failed.' );
	$player_campaign = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['other'], 'post_title' => 'Player only campaign' ), true );
	$check( is_int( $player_campaign ), 'Player campaign failed.' ); $scopes[] = $player_campaign;
	add_post_meta( $player_campaign, '_pspp_gm', (string) $users['other'] ); add_post_meta( $player_campaign, '_pspp_player', (string) $users['gm'] );
	$check( ! is_wp_error( ps_pen_paper_link_new_campaign( $player_campaign, $world_c, '1' ) ), 'Player campaign association failed.' );
	wp_set_current_user( $users['gm'] );
	foreach ( array( $page, 0 ) as $page_id ) {
		$html = $capture( $page_id ); $dom = $xpath( $html );
		$group_a = '//details[@data-settings-universe="' . $world_a . '"]';
		$group_b = '//details[@data-settings-universe="' . $world_b . '"]';
		$group_c = '//details[@data-settings-universe="' . $world_c . '"]';
		$check( 1 === $dom->query( $group_a . '/summary[text()="Settings world A"]' )->length &&
			1 === $dom->query( $group_b . '/summary[text()="Settings world B"]' )->length &&
			1 === $dom->query( $group_c . '/summary[text()="External settings world"]' )->length,
			'Worlds were not grouped by their actual universe.' );
		$check( 1 === $dom->query( $group_a . '//option[@value="' . $campaign_a . '"]' )->length &&
			0 === $dom->query( $group_b . '//option[@value="' . $campaign_a . '"]' )->length &&
			1 === $dom->query( $group_c . '//option[@value="' . $campaign_c . '"]' )->length &&
			0 === $dom->query( '//option[@value="' . $player_campaign . '" or @value="' . $round . '"]' )->length &&
			1 === $dom->query( '//option[@value="' . $legacy . '"]' )->length,
			'Campaign grouping, role filtering, runtime exclusion or legacy access failed.' );
		$check( 1 === $dom->query( $group_a . '//input[@name="universe[name]"]' )->length &&
			1 === $dom->query( $group_a . '//input[@name="policy[max_characters]"]' )->length &&
			1 === $dom->query( $group_a . '//form[input[@name="pspp_settings_return" and @value="universe"]]//input[@name="universe[name]"]' )->length &&
			0 === $dom->query( $group_c . '//input[@name="universe[name]" or @name="policy[max_characters]"]' )->length &&
			false === strpos( $html, 'WORLD_SECRET' ), 'Universe settings leaked or were missing.' );
		$name = $page_id ? 'pspp_campaign' : 'campaign_id';
		$check( 1 === $dom->query( $group_a . '//form[@method="get" and contains(@action,"#pspp-settings")]//select[@name="' . $name . '"]' )->length,
			'Campaign settings selector does not target the correct interface.' );
		$check( 1 === $dom->query( '//details[summary[text()="Sicherung und Wiederherstellung"] and not(@open)]//section[@id="pspp-import"]' )->length,
			'Import settings were not available in a collapsed section.' );
	}
	$_GET = array( 'pspp_import_preview' => 'invalid' );
	$dom = $xpath( $capture( $page ) );
	$check( 1 === $dom->query( '//details[@open and summary[text()="Sicherung und Wiederherstellung"]]//section[@id="pspp-import"]' )->length,
		'The import preview section did not reopen.' );
	$_GET = array();
	$redirect_url = ''; $finished = new RuntimeException( 'Redirect captured.' );
	$redirect = function ( $url ) use ( &$redirect_url, $finished ) { $redirect_url = $url; throw $finished; };
	add_filter( 'wp_redirect', $redirect, 0 );
	try {
		$_POST = array( 'universe_id' => (string) $world_a, 'universe_revision' => '1', 'universe_complete' => 'complete',
			'universe' => array( 'name' => 'Settings world A edited', 'description' => '' ),
			'pspp_settings_return' => 'universe', 'pspp_frontend_page' => (string) $page, '_wpnonce' => wp_create_nonce( 'pspp_universe_' . $world_a ) );
		$_REQUEST = $_POST;
		try { ps_pen_paper_handle_universe_action( 'save_universe' ); } catch ( RuntimeException $error ) { if ( $error !== $finished ) { throw $error; } }
		$check( 'Settings world A edited' === ps_pen_paper_universe_document( $world_a )['name'] &&
			false !== strpos( $redirect_url, 'pspp_view=settings' ) && false !== strpos( $redirect_url, 'pspp_settings_panel=world' ) &&
			false !== strpos( $redirect_url, '#pspp-settings-universe-' . $world_a ), 'World save did not return to its settings.' );
		$_GET = array( 'pspp_settings_universe' => (string) $world_a, 'pspp_settings_panel' => 'world' );
		$dom = $xpath( $capture( $page ) );
		$check( 1 === $dom->query( '//details[@data-settings-universe="' . $world_a . '" and @open]/details[@open]/summary[text()="Universum bearbeiten"]' )->length,
			'Saved world and its settings did not reopen.' );
		$redirect_url = '';
		$_POST = array( 'campaign_id' => (string) $world_a, 'policy_revision' => '0', 'policy_complete' => 'complete',
			'policy' => array( 'max_characters' => '2', 'creation_access' => 'site', 'custom_portraits' => 'disabled', 'member_invites' => 'approval' ),
			'pspp_settings_return' => 'universe', '_wpnonce' => wp_create_nonce( 'pspp_campaign_' . $world_a ) );
		$_REQUEST = $_POST;
		try { ps_pen_paper_handle_character_policy_action( 'save_character_policy' ); } catch ( RuntimeException $error ) { if ( $error !== $finished ) { throw $error; } }
		$check( 2 === ps_pen_paper_character_policy( $world_a )['max_characters'] &&
			false !== strpos( $redirect_url, 'page=ps-pen-paper-settings' ) && false !== strpos( $redirect_url, 'pspp_settings_panel=characters' ),
			'Backend character settings did not persist and return to the correct panel.' );
		$base_post = array( 'campaign_id' => (string) $world_a, 'pspp_frontend_page' => (string) $page,
			'pspp_settings_return' => 'universe', '_wpnonce' => wp_create_nonce( 'pspp_campaign_' . $world_a ) );
		$redirect_url = '';
		$_POST = $base_post + array( 'style_revision' => '0', 'style_complete' => 'complete', 'style_colors' => ps_pen_paper_style_defaults() ); $_REQUEST = $_POST;
		try { ps_pen_paper_handle_universe_style_action(); } catch ( RuntimeException $error ) { if ( $error !== $finished ) { throw $error; } }
		$check( 1 === ps_pen_paper_get_style( $world_a )['revision'] && false !== strpos( $redirect_url, 'pspp_settings_panel=style' ),
			'World colors did not persist and return to their panel.' );
		$redirect_url = '';
		$_POST = $base_post + array( 'condition_revision' => '0', 'conditions_complete' => 'complete', 'condition_policy' => array( 'definitions' => array() ) ); $_REQUEST = $_POST;
		try { ps_pen_paper_handle_condition_action( 'save_condition_policy', $page, $world_a ); } catch ( RuntimeException $error ) { if ( $error !== $finished ) { throw $error; } }
		$check( 1 === ps_pen_paper_condition_policy( ps_pen_paper_get_universe( $world_a ) )['revision'] &&
			false !== strpos( $redirect_url, 'pspp_settings_panel=conditions' ), 'Character conditions did not persist and return to their panel.' );
		$_GET = array( 'pspp_settings_universe' => (string) $world_a, 'pspp_settings_panel' => 'conditions' );
		$dom = $xpath( $capture( $page ) );
		$check( 1 === $dom->query( '//details[@data-settings-universe="' . $world_a . '" and @open]/details[@open]/details[@open]/summary[contains(text(),"Charakterzustände")]' )->length,
			'Saved condition settings did not reopen.' );
		define( 'CPC_CORE_PLUGINS', 'core-profile,core-activity' );
		require_once dirname( __DIR__, 2 ) . '/ps-community/activity/cpc_profile_tabs.php';
		$redirect_url = '';
		$_POST = $base_post + array( 'economy_complete' => 'complete', 'currency_revision' => '0',
			'currency' => array( 'name' => 'Test credits', 'symbol' => 'TC', 'wallet_mode' => 'campaign' ) ); $_REQUEST = $_POST;
		try { ps_pen_paper_handle_economy_action( 'save_currency' ); } catch ( RuntimeException $error ) { if ( $error !== $finished ) { throw $error; } }
		$check( 'Test credits' === ps_pen_paper_currency_document( $world_a )['name'] && false !== strpos( $redirect_url, 'pspp_settings_panel=economy' ),
			'Currency did not persist and return to its panel.' );
		$redirect_url = '';
		$_POST = $base_post + array( 'trade_complete' => 'complete', 'trade_policy_revision' => '0',
			'trade_policy' => array( 'kinds' => array( 'gift' ), 'approval' => '1' ) ); $_REQUEST = $_POST;
		try { ps_pen_paper_handle_trade_action( 'save_trade_policy' ); } catch ( RuntimeException $error ) { if ( $error !== $finished ) { throw $error; } }
		$check( array( 'gift' ) === ps_pen_paper_trade_policy( ps_pen_paper_get_universe( $world_a ) )['policy']['kinds'] &&
			false !== strpos( $redirect_url, 'pspp_settings_panel=economy' ), 'Trade settings did not persist and return to their panel.' );
		$_POST = array(); $check( '' === ps_pen_paper_settings_return_url( $world_a, $page, 'world' ), 'Other forms unexpectedly redirect to settings.' );
	} finally { remove_filter( 'wp_redirect', $redirect, 0 ); }
	wp_set_current_user( $users['player'] );
	$html = $capture( $page );
	$check( false === strpos( $html, 'Settings world A' ) && false === strpos( $html, 'Campaign A' ),
		'Unrelated player saw management settings.' );
	WP_CLI::success( 'Settings universe grouping, nested campaign selection, role separation, runtime exclusion and legacy access passed.' );
} finally {
	wp_set_current_user( $original_user );
	$_GET = $original_get; $_POST = $original_post; $_REQUEST = $original_request;
	foreach ( $scopes as $scope ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_event', 'pspp_asset' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $scope, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $child ) { wp_delete_post( $child, true ); }
		wp_delete_post( $scope, true );
	}
	if ( $page ) { wp_delete_post( $page, true ); }
	foreach ( $users as $id ) { is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ); }
}
