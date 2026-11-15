<?php
/**
 * Run with WP-CLI --skip-plugins --skip-themes against a development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
ps_pen_paper_register_campaign_type();
ps_pen_paper_register_universe_type();
$original_user = get_current_user_id();
$users = array();
$posts = array();
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
try {
	foreach ( array( 'gm', 'outsider' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-style-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( is_int( $id ), 'Style account fixture failed.' );
		$users[ $role ] = $id;
	}
	wp_set_current_user( $users['gm'] );
	foreach ( array( 'pspp_universe', 'pspp_universe', 'pspp_campaign' ) as $type ) {
		$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Universe style fixture' ), true );
		$check( is_int( $id ) && $id > 0, 'Style scope fixture failed.' );
		$posts[] = $id;
		if ( 'pspp_universe' === $type ) { update_post_meta( $id, '_pspp_universe', array( 'revision' => 1, 'name' => 'Universe style fixture', 'description' => '' ) ); }
		else { add_post_meta( $id, '_pspp_gm', (string) $users['gm'] ); }
	}
	$world_id = $posts[0];
	$other_id = $posts[1];
	$campaign_id = $posts[2];
	$legacy = ps_pen_paper_style_defaults();
	$legacy['accent'] = '#123456';
	$check( ! is_wp_error( ps_pen_paper_save_style( $campaign_id, $legacy, '0' ) ), 'Legacy style fixture failed.' );
	update_post_meta( $campaign_id, '_pspp_universe_link', array( 'revision' => 1, 'universe_id' => $world_id, 'universe_revision' => 1, 'name' => 'Universe style fixture', 'economy_mode' => 'local' ) );
	$check( ps_pen_paper_effective_style( $campaign_id )['colors'] === ps_pen_paper_style_defaults(), 'Unconfigured universe did not supply standard colors.' );
	$colors = ps_pen_paper_style_defaults();
	$colors['background'] = '#182635';
	$colors['accent'] = '#e3b45f';
	$check( ! is_wp_error( ps_pen_paper_save_style( $world_id, $colors, '0' ) ), 'GM could not save universe colors.' );
	$check( ps_pen_paper_effective_style( $campaign_id )['colors'] === $colors &&
		ps_pen_paper_effective_style( $other_id )['colors'] !== $colors &&
		ps_pen_paper_get_style( $campaign_id )['colors'] === $legacy, 'Inheritance leaked into another world or rewrote legacy colors.' );
	$check( is_wp_error( ps_pen_paper_save_style( $campaign_id, $legacy, '1' ) ), 'Campaign overwrote inherited universe colors.' );
	ob_start();
	ps_pen_paper_render_style_settings( get_post( $world_id ) );
	ps_pen_paper_render_style_settings( get_post( $other_id ) );
	$html = ob_get_clean();
	$document = new DOMDocument(); @$document->loadHTML( '<?xml encoding="UTF-8">' . $html );
	$ids = array();
	foreach ( $document->getElementsByTagName( '*' ) as $element ) {
		if ( $element->hasAttribute( 'id' ) && 0 === strpos( $element->getAttribute( 'id' ), 'pspp-style-' ) ) { $ids[] = $element->getAttribute( 'id' ); }
	}
	$check( count( $ids ) === count( array_unique( $ids ) ), 'Multiple universe editors generated duplicate IDs.' );
	$xpath = new DOMXPath( $document );
	$check( 2 === $xpath->query( '//input[@name="pspp_operation" and @value="save_universe_style"]' )->length &&
		12 === $xpath->query( '//input[starts-with(@name,"style_colors[")]' )->length, 'Universe forms do not submit the native color action/fields.' );
	wp_set_current_user( $users['outsider'] );
	$check( is_wp_error( ps_pen_paper_save_style( $world_id, $legacy, '1' ) ) &&
		ps_pen_paper_get_style( $world_id )['colors'] === $colors, 'Outsider changed universe colors.' );
} finally {
	wp_set_current_user( $original_user );
	$errors = array();
	foreach ( $posts as $id ) { if ( ! wp_delete_post( $id, true ) ) { $errors[] = 'Style fixture cleanup failed.'; } }
	foreach ( $users as $id ) { if ( ! ( is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ) ) ) { $errors[] = 'Style user cleanup failed.'; } }
	if ( $errors ) { WP_CLI::error( implode( ' ', $errors ) ); }
}
WP_CLI::success( 'Universe colors inherit without rewriting legacy styles; editor forms and ownership passed.' );
