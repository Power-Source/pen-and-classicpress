<?php
/**
 * Run with WP-CLI against a disposable development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'scene', 'folio', 'asset', 'event' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
$original_user = get_current_user_id();
$users = array();
$parents = array();
$path = '';
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
try {
	foreach ( array( 'gm', 'player', 'outsider' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-folio-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( ! is_wp_error( $id ), 'Fixture account failed.' );
		$users[ $role ] = $id;
	}
	foreach ( array( 'A', 'B' ) as $name ) {
		$id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Folio fixture ' . $name ), true );
		$check( ! is_wp_error( $id ) && $id, 'Campaign fixture failed.' );
		$parents[] = $id;
		add_post_meta( $id, '_pspp_gm', (string) $users['gm'] );
		add_post_meta( $id, '_pspp_player', (string) $users['player'] );
	}
	$campaign = ps_pen_paper_get_campaign( $parents[0] );
	$other = ps_pen_paper_get_campaign( $parents[1] );
	$path = wp_tempnam( 'pspp-folio-test.png' );
	$check( false !== file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=' ) ), 'Image fixture failed.' );
	$input = array( 'type' => 'handout', 'title' => 'PRIVATE_DRAFT', 'text' => 'PUBLIC_LETTER', 'secret' => 'HIDDEN_SECRET', 'order' => '1', 'chapter_id' => '0', 'scene_id' => '0' );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_save_folio( $campaign, 0, $input, '0', $path ) ), 'Player created a folio.' );
	wp_set_current_user( $users['gm'] );
	$id = ps_pen_paper_save_folio( $campaign, 0, $input, '0', $path );
	$check( is_int( $id ), 'Handout save failed.' );
	$check( array() === ps_pen_paper_visible_handouts( $campaign ), 'Draft exposed.' );
	$check( is_wp_error( ps_pen_paper_publish_folio( $campaign, $id, '1', '' ) ), 'Unconfirmed publication accepted.' );
	$check( true === ps_pen_paper_publish_folio( $campaign, $id, '1', 'confirmed' ), 'Publication failed.' );
	wp_set_current_user( $users['player'] );
	$visible = ps_pen_paper_visible_handouts( $campaign );
	$check( array( 'title', 'text', 'asset_id' ) === array_keys( $visible[ $id ] ) && false === strpos( wp_json_encode( $visible ), 'HIDDEN_SECRET' ), 'Public projection leaked private data.' );
	$check( ! is_wp_error( ps_pen_paper_private_image( $campaign, $visible[ $id ]['asset_id'] ) ), 'Released image inaccessible.' );
	$check( is_wp_error( ps_pen_paper_folio_records( $campaign ) ) && is_wp_error( ps_pen_paper_publish_folio( $campaign, $id, '2', 'confirmed', true ) ), 'Player accessed private folio or withdrew handout.' );
	ob_start(); ps_pen_paper_render_handouts( $campaign, 27 ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'PUBLIC_LETTER' ) && false === strpos( $html, 'HIDDEN_SECRET' ), 'Handout HTML privacy failed.' );
	$check( ! ps_pen_paper_get_events( $campaign )['events'], 'Private folio history exposed.' );
	wp_set_current_user( $users['gm'] );
	$input['title'] = 'REVISED_PRIVATE_TITLE';
	$input['text'] = 'REVISED_PRIVATE_TEXT';
	$check( $id === ps_pen_paper_save_folio( $campaign, $id, $input, '2' ) && is_wp_error( ps_pen_paper_save_folio( $campaign, $id, $input, '2' ) ), 'Draft update/stale rejection failed.' );
	$check( 'PUBLIC_LETTER' === ps_pen_paper_visible_handouts( $campaign )[ $id ]['text'], 'Draft update changed publication.' );
	$chapter_input = array_merge( $input, array( 'type' => 'chapter', 'title' => 'PRIVATE_CHAPTER' ) );
	$chapter = ps_pen_paper_save_folio( $campaign, 0, $chapter_input, '0' );
	$scene_input = array( 'title' => 'PRIVATE_SCENE', 'text' => 'Narrative', 'secret' => 'SCENE_SECRET', 'grid' => '0' );
	$scene_a = ps_pen_paper_save_scene( $campaign, 0, $scene_input, '0' );
	$scene_b = ps_pen_paper_save_scene( $campaign, 0, $scene_input, '0' );
	$chapter_input['scenes'] = array( $scene_a => '20', $scene_b => '10' );
	$check( $chapter === ps_pen_paper_save_folio( $campaign, $chapter, $chapter_input, '1' ) && array( $scene_b, $scene_a ) === ps_pen_paper_get_folio( $campaign, $chapter )['draft']['scene_ids'], 'Chapter scene ordering failed.' );
	$check( null === ps_pen_paper_map_state( $campaign )['scene'] && is_wp_error( ps_pen_paper_publish_folio( $campaign, $chapter, '2', 'confirmed' ) ), 'Chapter changed active scene or was published.' );
	$input['chapter_id'] = (string) $chapter;
	$input['scene_id'] = (string) $scene_a;
	$check( $id === ps_pen_paper_save_folio( $campaign, $id, $input, '3' ), 'Valid scene/chapter association failed.' );
	$check( is_wp_error( ps_pen_paper_save_folio( $other, 0, $input, '0' ) ), 'Cross-campaign references accepted.' );
	$bad = $input; $bad['type'] = 'person';
	$check( is_wp_error( ps_pen_paper_save_folio( $campaign, $id, $bad, '4' ) ), 'Record type changed after publication.' );
	$failure = function ( $data, $postarr ) { if ( isset( $postarr['ID'] ) && 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) { $data['post_status'] = 'draft'; } return $data; };
	add_filter( 'wp_insert_post_data', $failure, 10, 2 );
	try {
		$check( is_wp_error( ps_pen_paper_publish_folio( $campaign, $id, '4', 'confirmed', true ) ), 'Journal failure accepted.' );
		$check( 4 === ps_pen_paper_get_folio( $campaign, $id )['revision'] && isset( ps_pen_paper_visible_handouts( $campaign )[ $id ] ), 'Journal failure did not restore publication.' );
		$count = count( get_posts( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1 ) ) );
		$check( is_wp_error( ps_pen_paper_save_folio( $campaign, 0, array_merge( $input, array( 'chapter_id' => '0', 'scene_id' => '0' ) ), '0', $path ) ), 'Failed creation succeeded.' );
		$check( $count === count( get_posts( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1 ) ) ), 'Failed creation left image.' );
	} finally { remove_filter( 'wp_insert_post_data', $failure, 10 ); }
	$check( true === ps_pen_paper_publish_folio( $campaign, $id, '4', 'confirmed', true ) && array() === ps_pen_paper_visible_handouts( $campaign ), 'Withdrawal failed.' );
	$check( true === ps_pen_paper_publish_folio( $campaign, $id, '5', 'confirmed' ), 'Republication failed.' );
	wp_set_current_user( $users['outsider'] );
	$check( is_wp_error( ps_pen_paper_visible_handouts( $campaign ) ), 'Nonmember read handouts.' );
	wp_set_current_user( $users['player'] );
	delete_post_meta( $campaign->ID, '_pspp_player', (string) $users['player'] );
	$check( is_wp_error( ps_pen_paper_visible_handouts( $campaign ) ), 'Removed member retained access.' );
	wp_set_current_user( 0 );
	$check( is_wp_error( ps_pen_paper_visible_handouts( $campaign ) ), 'Guest read handouts.' );
	WP_CLI::success( 'Private folio, ordered chapters, cross-campaign guards, handout/image projection, explicit publication, immutable released drafts, revisions, history privacy, rollback and revocation passed.' );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $parents as $parent ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_folio', 'pspp_scene', 'pspp_asset', 'pspp_event' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $parent, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
		wp_delete_post( $parent, true );
	}
	if ( $path && is_file( $path ) ) { unlink( $path ); }
	foreach ( $users as $id ) { is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ); }
}
