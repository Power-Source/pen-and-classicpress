<?php
/**
 * Run with WP-CLI against a disposable development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( "Run this test with WP-CLI.\n" );
}
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) {
	require_once ABSPATH . 'wp-admin/includes/ms.php';
}
ps_pen_paper_register_campaign_type();
ps_pen_paper_register_character_type();
ps_pen_paper_register_roll_type();
ps_pen_paper_register_event_type();
ps_pen_paper_register_universe_type();
$original_user = get_current_user_id();
$original_query = $GLOBALS['wp_query'];
$original_get = $_GET;
$users = array();
$parents = array();
$page_id = 0;
$check = function ( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
};
try {
	foreach ( array( 'owner', 'other', 'player', 'admin' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-universe-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'admin' === $role ? 'administrator' : 'subscriber' ) );
		$check( ! is_wp_error( $id ), 'Fixture user failed.' );
		$users[ $role ] = $id;
	}
	wp_set_current_user( $users['owner'] );
	$input = array( 'name' => 'UNIVERSE_SECRET', 'description' => 'Interne Welt C:\Probe' );
	$check( is_wp_error( ps_pen_paper_save_universe( 0, $input, '0' ) ), 'Unprivileged subscriber created a universe.' );
	foreach ( array( 'owner', 'other' ) as $role ) {
		get_userdata( $users[ $role ] )->add_cap( 'ps_pen_paper_create_campaigns' );
	}
	wp_set_current_user( 0 );
	wp_set_current_user( $users['owner'] );
	$universe_id = ps_pen_paper_save_universe( 0, $input, '0' );
	$check( ! is_wp_error( $universe_id ), 'Universe creation failed.' );
	$parents[] = $universe_id;
	$universe = ps_pen_paper_get_universe( $universe_id );
	$document = ps_pen_paper_universe_document( $universe_id );
	$check( $input['description'] === $document['description'] && 1 === $document['revision'], 'Universe escaping or initial revision failed.' );
	$check( ! is_wp_error( ps_pen_paper_get_events( $universe ) ) && 'universe_saved' === ps_pen_paper_get_events( $universe )['events'][0]['kind'], 'Universe creation was not journaled.' );
	$check( is_wp_error( ps_pen_paper_save_universe( $universe_id, $input, '0' ) ), 'Stale universe update accepted.' );
	$invalid = $input;
	$invalid['name'] = array( 'broken' );
	$check( is_wp_error( ps_pen_paper_save_universe( $universe_id, $invalid, '1' ) ) && $document === ps_pen_paper_universe_document( $universe_id ), 'Invalid universe input mutated data.' );
	wp_set_current_user( $users['other'] );
	$other_id = ps_pen_paper_save_universe( 0, array( 'name' => 'OTHER_SECRET', 'description' => '' ), '0' );
	$check( ! is_wp_error( $other_id ), 'Second universe fixture failed.' );
	$parents[] = $other_id;
	$check( is_wp_error( ps_pen_paper_get_events( $universe ) ) && is_wp_error( ps_pen_paper_save_universe( $universe_id, $input, '1' ) ), 'Foreign universe or history became available.' );
	$check( ! in_array( $universe_id, wp_list_pluck( ps_pen_paper_get_universes(), 'ID' ), true ), 'Foreign universe appeared in directory.' );

	foreach ( array( 'owner', 'other' ) as $role ) {
		$id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_title' => 'Universe fixture ' . $role, 'post_author' => $users[ $role ] ), true );
		$check( ! is_wp_error( $id ) && $id, 'Campaign fixture failed.' );
		$campaigns[ $role ] = $id;
		$parents[] = $id;
		$check( (bool) add_post_meta( $id, '_pspp_gm', (string) $users[ $role ] ), 'Campaign GM fixture failed.' );
	}
	$campaign_id = $campaigns['owner'];
	add_post_meta( $campaign_id, '_pspp_player', (string) $users['player'] );
	wp_set_current_user( $users['owner'] );
	$rules = ps_pen_paper_save_rules( $campaign_id, array( 'name' => 'Regeln', 'description' => '', 'dice' => array( '6' ), 'fields' => array() ), '0' );
	$check( ! is_wp_error( $rules ), 'Rules fixture failed.' );
	wp_set_current_user( $users['player'] );
	$character_id = ps_pen_paper_save_character( $campaign_id, 0, $users['player'], array( 'name' => 'Pending character', 'values' => array() ), '0', '1' );
	$check( ! is_wp_error( $character_id ), 'Pending character fixture failed.' );
	$character = get_post_meta( $character_id, '_pspp_character', true );
	$roll_id = ps_pen_paper_roll_dice( $campaign_id, array( 'expression' => 'W6', 'visibility' => 'group', 'character' => '', 'attribute' => '', 'label' => 'Historical roll' ), '1' );
	$check( ! is_wp_error( $roll_id ), 'Historical roll fixture failed.' );
	$roll = get_post_meta( $roll_id );
	$members = get_post_meta( $campaign_id, '_pspp_player', false );
	$check( is_wp_error( ps_pen_paper_preview_universe_link( $campaign_id, $universe_id ) ), 'Player previewed an internal universe.' );
	wp_set_current_user( $users['owner'] );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	$check( is_wp_error( ps_pen_paper_preview_universe_link( $campaign_id, $other_id ) ), 'Campaign GM linked someone else’s universe.' );
	$check( is_wp_error( ps_pen_paper_preview_universe_link( $campaigns['other'], $universe_id ) ), 'Universe owner could take a foreign campaign.' );
	$preview = ps_pen_paper_preview_universe_link( $campaign_id, $universe_id );
	$check( ! is_wp_error( $preview ) && 0 === ps_pen_paper_get_universe_link( $campaign_id )['revision'], 'Preview mutated campaign association.' );
	$check( is_wp_error( ps_pen_paper_save_universe_link( $campaign_id, $universe_id, '0', '1', '' ) ), 'Unconfirmed association saved.' );
	$check( is_wp_error( ps_pen_paper_save_universe_link( $campaign_id, $universe_id, '0', '0', 'confirmed' ) ), 'Outdated universe preview accepted.' );
	$link = ps_pen_paper_save_universe_link( $campaign_id, $universe_id, '0', '1', 'confirmed' );
	$check( ! is_wp_error( $link ) && $link === ps_pen_paper_get_universe_link( $campaign_id ), 'Confirmed association failed.' );
	$check( $rules === ps_pen_paper_get_rules( $campaign_id ) && $character === get_post_meta( $character_id, '_pspp_character', true ) && $roll === get_post_meta( $roll_id ) && $members === get_post_meta( $campaign_id, '_pspp_player', false ), 'Association changed existing rules, pending character, roll or members.' );
	$check( 'universe_linked' === ps_pen_paper_get_events( $campaign )['events'][0]['kind'], 'Association event missing.' );
	ob_start();
	ps_pen_paper_render_journal( $campaign );
	$html = ob_get_clean();
	$check( false !== strpos( $html, 'UNIVERSE_SECRET' ) && false !== strpos( $html, 'Ohne Universum' ), 'Association before/after details missing.' );
	$check( is_wp_error( ps_pen_paper_save_universe_link( $campaign_id, 0, '0', '0', 'confirmed' ) ), 'Stale unlink accepted.' );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_get_events( $universe ) ) && array() === ps_pen_paper_get_universes(), 'Campaign player gained universe access.' );
	ob_start();
	ps_pen_paper_render_journal( $campaign );
	$html = ob_get_clean();
	$check( false === strpos( $html, 'UNIVERSE_SECRET' ), 'Association event leaked universe name to a player.' );

	$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Universe fixture page', 'post_content' => '[ps_pen_paper]' ), true );
	$check( ! is_wp_error( $page_id ) && $page_id, 'Frontend fixture failed.' );
	$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $page_id ) );
	$_GET = array( 'pspp_view' => 'universes', 'pspp_link_campaign' => (string) $campaign_id, 'pspp_link_universe' => (string) $universe_id );
	$check( false === strpos( ps_pen_paper_frontend_shortcode(), 'UNIVERSE_SECRET' ), 'Forged frontend preview leaked universe to player.' );
	wp_set_current_user( 0 );
	$check( false === strpos( ps_pen_paper_frontend_shortcode(), 'UNIVERSE_SECRET' ), 'Guest gained universe access.' );
	wp_set_current_user( $users['owner'] );
	$check( false !== strpos( ps_pen_paper_frontend_shortcode(), 'UNIVERSE_SECRET' ), 'Owner frontend did not render universe.' );
	$_GET['pspp_link_universe'] = array( '0' );
	$check( false !== strpos( ps_pen_paper_frontend_shortcode(), 'Die gewählte Zuordnung ist ungültig.' ), 'Malformed preview was not rejected.' );
	get_userdata( $users['owner'] )->remove_cap( 'ps_pen_paper_create_campaigns' );
	wp_set_current_user( 0 );
	wp_set_current_user( $users['owner'] );
	$input['name'] = 'Renamed universe';
	$check( $universe_id === ps_pen_paper_save_universe( $universe_id, $input, '1' ), 'Revoked creation permission prevented editing an owned universe.' );
	$check( is_wp_error( ps_pen_paper_save_universe( 0, $input, '0' ) ), 'Revoked creator still created universes.' );
	$check( 'Renamed universe' === ps_pen_paper_preview_universe_link( $campaign_id, 0 )['source_universe']['name'], 'Preview used a stale source name after rename.' );
	$fail_publish = static function ( $data ) {
		if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) { $data['post_status'] = 'draft'; }
		return $data;
	};
	$baseline = ps_pen_paper_get_events( $campaign );
	$universe_baseline = ps_pen_paper_universe_document( $universe_id );
	add_filter( 'wp_insert_post_data', $fail_publish );
	try {
		$check( is_wp_error( ps_pen_paper_save_universe_link( $campaign_id, 0, '1', '0', 'confirmed' ) ) && $link === ps_pen_paper_get_universe_link( $campaign_id ), 'Failed event publication left changed association.' );
		$check( is_wp_error( ps_pen_paper_save_universe( $universe_id, $input, '2' ) ) && $universe_baseline === ps_pen_paper_universe_document( $universe_id ), 'Failed event publication changed universe.' );
		wp_set_current_user( $users['other'] );
		$ids = wp_list_pluck( ps_pen_paper_get_universes(), 'ID' );
		$check( is_wp_error( ps_pen_paper_save_universe( 0, $input, '0' ) ) && $ids === wp_list_pluck( ps_pen_paper_get_universes(), 'ID' ), 'Failed creation left a published universe.' );
	} finally {
		remove_filter( 'wp_insert_post_data', $fail_publish );
	}
	wp_set_current_user( $users['owner'] );
	$check( $baseline === ps_pen_paper_get_events( $campaign ), 'Failed association left a successful event.' );
	update_post_meta( $campaign_id, '_pspp_universe_link', array( 'revision' => 'broken' ) );
	$check( is_wp_error( ps_pen_paper_save_universe_link( $campaign_id, 0, '1', '0', 'confirmed' ) ), 'Corrupt association was silently reset.' );
	update_post_meta( $campaign_id, '_pspp_universe_link', wp_slash( $link ) );
	wp_set_current_user( $users['admin'] );
	$check( ps_pen_paper_can_manage_universe( $universe ) && in_array( $universe_id, wp_list_pluck( ps_pen_paper_get_universes(), 'ID' ), true ), 'Administrator lacks universe access.' );
	$check( ! is_wp_error( ps_pen_paper_save_universe_link( $campaigns['other'], $universe_id, '0', '2', 'confirmed' ) ), 'Administrator could not associate independently owned campaign and universe.' );
	wp_set_current_user( $users['owner'] );
	$check( ! ps_pen_paper_can_view_campaign( ps_pen_paper_get_campaign( $campaigns['other'] ) ), 'Universe ownership automatically granted private campaign access.' );
	$check( ! is_wp_error( ps_pen_paper_save_universe_link( $campaign_id, 0, '1', '0', 'confirmed' ) ) && 0 === ps_pen_paper_get_universe_link( $campaign_id )['universe_id'], 'Confirmed unlink failed.' );
} finally {
	wp_set_current_user( $original_user );
	$GLOBALS['wp_query'] = $original_query;
	$_GET = $original_get;
	$errors = array();
	$children = $parents ? get_posts( array( 'post_type' => array( 'pspp_event', 'pspp_roll', 'pspp_character' ), 'post_status' => array( 'draft', 'private' ), 'post_parent__in' => $parents, 'posts_per_page' => -1, 'fields' => 'ids' ) ) : array();
	foreach ( array_merge( $children, $parents, $page_id ? array( $page_id ) : array() ) as $id ) {
		if ( ! wp_delete_post( $id, true ) ) { $errors[] = 'Could not remove universe fixture ' . $id; }
	}
	foreach ( $users as $id ) {
		if ( ! ( is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ) ) ) { $errors[] = 'Could not remove universe fixture user ' . $id; }
	}
	if ( $errors ) { WP_CLI::error( implode( '; ', $errors ) ); }
}
WP_CLI::success( 'Private universes, revision conflicts, confirmed associations, unchanged campaign data, history privacy and failure rollback passed.' );
