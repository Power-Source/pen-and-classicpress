<?php
/** Run with WP-CLI against a disposable development site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'folio', 'event', 'asset' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
$original_user = get_current_user_id();
$users = array(); $campaign_id = 0; $path = '';
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
try {
	foreach ( array( 'gm', 'recipient', 'other' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-audience-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( ! is_wp_error( $id ), 'User creation failed.' ); $users[ $role ] = $id;
	}
	$campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_title' => 'Audience fixture', 'post_author' => $users['gm'] ), true );
	$check( ! is_wp_error( $campaign_id ) && $campaign_id, 'Campaign creation failed.' );
	add_post_meta( $campaign_id, '_pspp_player', (string) $users['recipient'] );
	add_post_meta( $campaign_id, '_pspp_player', (string) $users['other'] );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	wp_set_current_user( $users['gm'] );
	$input = array( 'type' => 'handout', 'title' => 'PRIVATE_TARGETED_TITLE', 'text' => 'PRIVATE_TARGETED_TEXT', 'secret' => '', 'order' => '1', 'chapter_id' => '0', 'scene_id' => '0' );
	$id = ps_pen_paper_save_folio( $campaign, 0, $input, '0' );
	$check( is_int( $id ), 'Handout creation failed.' );
	$input['audience_mode'] = 'users'; $input['audience_users'] = array( (string) $users['recipient'] );
	$check( $id === ps_pen_paper_save_folio( $campaign, $id, $input, '1' ) && true === ps_pen_paper_publish_folio( $campaign, $id, '2', 'confirmed' ), 'Restricted publication failed.' );
	wp_set_current_user( $users['recipient'] );
	$check( isset( ps_pen_paper_visible_handouts( $campaign )[ $id ] ), 'Recipient cannot see handout.' );
	wp_set_current_user( $users['other'] );
	ob_start(); ps_pen_paper_render_handouts( $campaign, 1 ); $html = ob_get_clean();
	$check( ! isset( ps_pen_paper_visible_handouts( $campaign )[ $id ] ) && false === strpos( $html, 'PRIVATE_TARGETED_' ), 'Other member saw restricted handout.' );
	wp_set_current_user( $users['gm'] );
	$bad = $input; $bad['audience_users'][] = (string) $users['recipient'];
	$check( is_wp_error( ps_pen_paper_save_folio( $campaign, $id, $bad, '3' ) ), 'Duplicate recipients accepted.' );
	$bad = $input; $bad['audience_mode'] = 'all';
	$check( is_wp_error( ps_pen_paper_save_folio( $campaign, $id, $bad, '3' ) ), 'Contradictory audience accepted.' );
	$pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n";
	$pdf .= "xref\n0 2\n0000000000 65535 f \n0000000009 00000 n \ntrailer\n<< /Size 2 /Root 1 0 R >>\nstartxref\n" . strlen( $pdf ) . "\n%%EOF\n";
	$check( ps_pen_paper_valid_private_asset_content( 'application/pdf', base64_encode( $pdf ) ) &&
		! ps_pen_paper_valid_private_asset_content( 'application/pdf', base64_encode( substr( $pdf, 0, -6 ) ) ) &&
		ps_pen_paper_valid_private_asset_content( 'text/plain', base64_encode( "Hinweis für dich\n" ) ) &&
		! ps_pen_paper_valid_private_asset_content( 'text/plain', base64_encode( "bad\0text" ) ) &&
		ps_pen_paper_valid_private_asset_content( 'text/plain', base64_encode( str_repeat( 'a', 2 * MB_IN_BYTES ) ) ) &&
		! ps_pen_paper_valid_private_asset_content( 'text/plain', base64_encode( str_repeat( 'a', 2 * MB_IN_BYTES + 1 ) ) ) &&
		! ps_pen_paper_valid_private_asset_content( 'image/png', base64_encode( $pdf ) ), 'Asset format validation failed.' );
	$path = dirname( __DIR__ ) . '/tests/.pspp-handout-' . wp_generate_uuid4() . '.pdf';
	$check( false !== file_put_contents( $path, $pdf ), 'PDF fixture failed.' );
	$check( $id === ps_pen_paper_save_folio( $campaign, $id, $input, '3', $path, 'brief.pdf' ) &&
		true === ps_pen_paper_publish_folio( $campaign, $id, '4', 'confirmed' ), 'Restricted PDF publication failed.' );
	$asset_id = ps_pen_paper_get_folio( $campaign, $id )['draft']['asset_id'];
	$check( is_wp_error( ps_pen_paper_private_image_data( $campaign, $asset_id ) ), 'Generic image API exposed handout asset.' );
	wp_set_current_user( $users['recipient'] );
	$asset = ps_pen_paper_handout_asset( $campaign, $id );
	$check( ! is_wp_error( $asset ) && 'application/pdf' === $asset->post_mime_type && $pdf === base64_decode( $asset->post_content, true ) &&
		is_wp_error( ps_pen_paper_handout_asset( $campaign, $id, true ) ), 'Recipient download or draft restriction failed.' );
	ob_start(); ps_pen_paper_render_handouts( $campaign, 1 ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'pspp_handout_download' ) && false === strpos( $html, 'data:application/pdf' ), 'Released PDF did not use the authorized download endpoint.' );
	wp_set_current_user( $users['other'] );
	$check( is_wp_error( ps_pen_paper_handout_asset( $campaign, $id ) ) && is_wp_error( ps_pen_paper_private_image_data( $campaign, $asset_id ) ), 'Nonrecipient retrieved restricted asset.' );
	wp_set_current_user( $users['gm'] );
	$check( false !== file_put_contents( $path, "Geheimer Hinweis\n" ) &&
		$id === ps_pen_paper_save_folio( $campaign, $id, $input, '5', $path, 'brief.txt' ), 'Restricted text file save failed.' );
	wp_set_current_user( $users['recipient'] );
	$check( 'application/pdf' === ps_pen_paper_handout_asset( $campaign, $id )->post_mime_type, 'Draft upload changed released file.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_publish_folio( $campaign, $id, '6', 'confirmed' ), 'Text file publication failed.' );
	wp_set_current_user( $users['recipient'] );
	$check( 'text/plain' === ps_pen_paper_handout_asset( $campaign, $id )->post_mime_type, 'Released text file unavailable.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_publish_folio( $campaign, $id, '7', 'confirmed', true ), 'Withdrawal failed.' );
	wp_set_current_user( $users['recipient'] );
	$check( ! isset( ps_pen_paper_visible_handouts( $campaign )[ $id ] ) && is_wp_error( ps_pen_paper_handout_asset( $campaign, $id ) ), 'Withdrawn handout remained visible or downloadable.' );
	WP_CLI::success( 'Targeted text handouts, validation, privacy and withdrawal passed.' );
} finally {
	wp_set_current_user( $original_user );
	if ( $campaign_id && ! is_wp_error( $campaign_id ) ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_folio', 'pspp_asset', 'pspp_event' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $campaign_id, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
		wp_delete_post( $campaign_id, true );
	}
	foreach ( $users as $id ) { is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ); }
	if ( $path && is_file( $path ) ) { unlink( $path ); }
}
