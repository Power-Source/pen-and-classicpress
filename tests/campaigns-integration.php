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

function pspp_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$original_user = get_current_user_id();
$original_query = $GLOBALS['wp_query'];
$original_get = $_GET;
$original_post = $_POST;
$original_request = $_REQUEST;
$user_ids = array();
$post_ids = array();
$suffix = strtolower( wp_generate_password( 12, false, false ) );

try {
	foreach ( array( 'owner', 'gm', 'player', 'outsider' ) as $role ) {
		$user_id = wp_insert_user(
			array(
				'user_login' => 'pspp-test-' . $role . '-' . $suffix,
				'user_pass'  => wp_generate_password( 32 ),
				'user_email' => 'pspp-test-' . $role . '-' . $suffix . '@example.invalid',
				'role'       => 'outsider' === $role ? 'editor' : 'subscriber',
			)
		);
		pspp_test_assert( ! is_wp_error( $user_id ), 'Could not create test user.' );
		$user_ids[ $role ] = $user_id;
	}

	$campaign_id = wp_insert_post(
		array(
			'post_type'    => 'pspp_campaign',
			'post_status'  => 'private',
			'post_title'   => 'PSPP integration test ' . $suffix,
			'post_content' => 'Private test content.',
			'post_author'  => $user_ids['owner'],
		),
		true
	);
	pspp_test_assert( ! is_wp_error( $campaign_id ) && $campaign_id, 'Could not create test campaign.' );
	$post_ids[] = $campaign_id;
	foreach ( array( 'owner' => 'gm', 'gm' => 'gm', 'player' => 'player' ) as $user => $role ) {
		pspp_test_assert( (bool) add_post_meta( $campaign_id, '_pspp_' . $role, (string) $user_ids[ $user ] ), 'Could not add test membership.' );
	}
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	pspp_test_assert( (bool) $campaign, 'Campaign could not be loaded.' );

	wp_set_current_user( $user_ids['owner'] );
	$rule_input = array(
		'name'        => 'Testregelwerk',
		'description' => 'Ein Pfad: C:\Welt',
		'dice'        => array( '6', '20' ),
		'fields'      => array(
			array( 'id' => '', 'label' => 'Mana', 'type' => 'resource', 'description' => 'Frei benennbar', 'min' => '0', 'max' => '100', 'start' => '10' ),
			array( 'id' => '', 'label' => 'Hintergrund', 'type' => 'textarea', 'description' => '' ),
		),
	);
	$saved_rules = ps_pen_paper_save_rules( $campaign_id, $rule_input, '0' );
	pspp_test_assert( ! is_wp_error( $saved_rules ) && $saved_rules === ps_pen_paper_get_rules( $campaign_id ), 'Rules did not persist correctly.' );
	$resource_id = $saved_rules['fields'][0]['id'];
	$rename_input = $rule_input;
	$rename_input['fields'] = array_reverse( $saved_rules['fields'] );
	foreach ( $rename_input['fields'] as &$field ) {
		unset( $field['required'] );
		foreach ( array( 'min', 'max', 'start' ) as $key ) {
			if ( isset( $field[ $key ] ) ) {
				$field[ $key ] = (string) $field[ $key ];
			}
		}
	}
	unset( $field );
	$rename_input['fields'][1]['label'] = 'Wut';
	$renamed = ps_pen_paper_save_rules( $campaign_id, $rename_input, '1' );
	pspp_test_assert( ! is_wp_error( $renamed ) && $renamed['fields'][1]['id'] === $resource_id && 'Wut' === $renamed['fields'][1]['label'], 'Rename or reorder changed the stable field ID.' );
	$conflict = ps_pen_paper_save_rules( $campaign_id, $rename_input, '1' );
	pspp_test_assert( is_wp_error( $conflict ) && 'conflict' === $conflict->get_error_code(), 'Stale rule edits were accepted.' );
	$bad_inputs = array();
	$invalid = $rule_input;
	$invalid['dice'] = array( '999' );
	$bad_inputs[] = $invalid;
	$invalid = $rule_input;
	$invalid['character_mode'] = 'unrestricted';
	$bad_inputs[] = $invalid;
	$invalid = $rule_input;
	$invalid['fields'][0]['start'] = '101';
	$bad_inputs[] = $invalid;
	$invalid = $rule_input;
	$invalid['fields'][0]['min'] = array( '0' );
	$bad_inputs[] = $invalid;
	$invalid = $rule_input;
	$invalid['fields'][0]['id'] = wp_generate_uuid4();
	$bad_inputs[] = $invalid;
	$invalid = $rule_input;
	$invalid['fields'] = array_fill( 0, 31, $rule_input['fields'][0] );
	$bad_inputs[] = $invalid;
	$invalid = $rename_input;
	$invalid['fields'][] = $invalid['fields'][0];
	$bad_inputs[] = $invalid;
	foreach ( $bad_inputs as $invalid ) {
		pspp_test_assert( is_wp_error( ps_pen_paper_save_rules( $campaign_id, $invalid, '2' ) ), 'Invalid rule definitions were accepted.' );
	}
	pspp_test_assert( ps_pen_paper_get_rules( $campaign_id ) === $renamed, 'Rejected edits changed saved rules.' );
	$boundary = $rule_input;
	$boundary['fields'] = array_fill( 0, 30, $rule_input['fields'][0] );
	pspp_test_assert( ! is_wp_error( ps_pen_paper_validate_rules( $boundary, $renamed ) ), 'Thirty fields were incorrectly rejected.' );
	$boundary['dice'] = array();
	$boundary['fields'] = array();
	pspp_test_assert( ! is_wp_error( ps_pen_paper_validate_rules( $boundary, $renamed ) ), 'Diceless or fieldless rules were rejected.' );
	$other_campaign_id = wp_insert_post(
		array(
			'post_type'   => 'pspp_campaign',
			'post_status' => 'private',
			'post_title'  => 'PSPP second test campaign ' . $suffix,
			'post_author' => $user_ids['owner'],
		),
		true
	);
	pspp_test_assert( ! is_wp_error( $other_campaign_id ) && $other_campaign_id, 'Could not create second test campaign.' );
	$post_ids[] = $other_campaign_id;
	pspp_test_assert( ! is_wp_error( ps_pen_paper_save_rules( $other_campaign_id, $boundary, '0' ) ), 'Could not save independent campaign rules.' );
	pspp_test_assert( ps_pen_paper_get_rules( $campaign_id ) === $renamed && array() === ps_pen_paper_get_rules( $other_campaign_id )['dice'], 'Rules leaked between campaigns.' );

	pspp_test_assert( ! ps_pen_paper_can_create_campaign(), 'Unprivileged user can create campaigns.' );
	$owner = get_userdata( $user_ids['owner'] );
	$owner->add_cap( 'ps_pen_paper_create_campaigns' );
	wp_set_current_user( 0 );
	wp_set_current_user( $user_ids['owner'] );
	pspp_test_assert( ps_pen_paper_can_create_campaign(), 'Granted creation permission was not recognized.' );
	pspp_test_assert( ps_pen_paper_can_manage_campaign( $campaign ), 'Campaign owner cannot manage their campaign.' );
	$owner->remove_cap( 'ps_pen_paper_create_campaigns' );
	wp_set_current_user( 0 );
	wp_set_current_user( $user_ids['owner'] );
	pspp_test_assert( ! ps_pen_paper_can_create_campaign() && ps_pen_paper_can_manage_campaign( $campaign ), 'Revocation changed existing campaign management.' );

	wp_set_current_user( $user_ids['gm'] );
	pspp_test_assert( ps_pen_paper_can_view_campaign( $campaign ) && ps_pen_paper_can_manage_campaign( $campaign ), 'Assigned game master lacks access.' );
	pspp_test_assert( in_array( $campaign_id, wp_list_pluck( ps_pen_paper_get_campaigns(), 'ID' ), true ), 'Game master cannot list the campaign.' );
	pspp_test_assert( ! is_wp_error( ps_pen_paper_save_rules( $campaign_id, $rename_input, '2' ) ), 'Game master cannot save rules.' );

	wp_set_current_user( $user_ids['player'] );
	pspp_test_assert( ps_pen_paper_can_view_campaign( $campaign ) && ! ps_pen_paper_can_manage_campaign( $campaign ), 'Player permissions are incorrect.' );
	pspp_test_assert( in_array( $campaign_id, wp_list_pluck( ps_pen_paper_get_campaigns(), 'ID' ), true ), 'Player cannot list the campaign.' );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_rules( $campaign_id, $rename_input, '3' ) ), 'Player can save rules.' );
	$character_input = array(
		'name'   => 'Testcharakter',
		'values' => array(
			$renamed['fields'][0]['id'] => 'Hintergrund mit einem Pfad C:\Welt',
			$resource_id => array( 'current' => '10', 'maximum' => '50' ),
		),
	);
	$character_id = ps_pen_paper_save_character( $campaign_id, 0, $user_ids['player'], $character_input, '0', '3' );
	pspp_test_assert( ! is_wp_error( $character_id ), 'Player could not submit their own character.' );
	$post_ids[] = $character_id;
	$state = get_post_meta( $character_id, '_pspp_character', true );
	pspp_test_assert( null === $state['active'] && 'Testcharakter' === $state['pending']['name'], 'Approval mode activated an unapproved character.' );
	pspp_test_assert( $character_input['values'][ $renamed['fields'][0]['id'] ] === $state['pending']['values'][ $renamed['fields'][0]['id'] ], 'Character text was changed during persistence.' );
	$required_rules = $renamed;
	$required_rules['fields'][0]['required'] = true;
	$missing_text = $character_input;
	unset( $missing_text['values'][ $renamed['fields'][0]['id'] ] );
	pspp_test_assert( is_wp_error( ps_pen_paper_validate_character( $missing_text, $required_rules ) ), 'Required character text was not validated.' );
	pspp_test_assert( is_wp_error( ps_pen_paper_review_character( $campaign_id, $character_id, 'approve', '1' ) ), 'Player approved their own character.' );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_character( $campaign_id, 0, $user_ids['gm'], $character_input, '0', '3' ) ), 'Player created a character for someone else.' );
	pspp_test_assert( null === ps_pen_paper_get_character( ps_pen_paper_get_campaign( $other_campaign_id ), $character_id ), 'Character crossed campaign boundaries.' );

	wp_set_current_user( $user_ids['gm'] );
	pspp_test_assert( true === ps_pen_paper_review_character( $campaign_id, $character_id, 'approve', '1' ), 'Game master could not approve the character.' );
	$state = get_post_meta( $character_id, '_pspp_character', true );
	pspp_test_assert( 'Testcharakter' === $state['active']['name'] && null === $state['pending'], 'Approval did not activate the pending values.' );
	pspp_test_assert( is_wp_error( ps_pen_paper_review_character( $campaign_id, $character_id, 'reject', '1' ) ), 'Stale review was accepted.' );
	$invalid_schema = $rename_input;
	$invalid_schema['fields'][1]['max'] = '200';
	$schema_result = ps_pen_paper_save_rules( $campaign_id, $invalid_schema, '3' );
	pspp_test_assert( is_wp_error( $schema_result ) && 'schema_in_use' === $schema_result->get_error_code(), 'Existing character schema was changed incompatibly.' );

	wp_set_current_user( $user_ids['player'] );
	$character_input['values'][ $resource_id ]['current'] = '20';
	pspp_test_assert( $character_id === ps_pen_paper_save_character( $campaign_id, $character_id, $user_ids['player'], $character_input, '2', '3' ), 'Player could not request changed values.' );
	$state = get_post_meta( $character_id, '_pspp_character', true );
	pspp_test_assert( 10 === $state['active']['values'][ $resource_id ]['current'] && 20 === $state['pending']['values'][ $resource_id ]['current'], 'Pending changes replaced active values.' );
	$invalid_character = $character_input;
	$invalid_character['values'][ $resource_id ]['current'] = '51';
	pspp_test_assert( is_wp_error( ps_pen_paper_save_character( $campaign_id, $character_id, $user_ids['player'], $invalid_character, '3', '3' ) ), 'Resource value above personal maximum was accepted.' );
	$invalid_character = $character_input;
	$invalid_character['values'][ $resource_id ]['current'] = array( '20' );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_character( $campaign_id, $character_id, $user_ids['player'], $invalid_character, '3', '3' ) ), 'Malformed character input was accepted.' );

	wp_set_current_user( $user_ids['gm'] );
	$direct_rules_input = $rename_input;
	$direct_rules_input['character_mode'] = 'direct';
	$direct_rules_input['fields'][1]['label'] = 'Zorn';
	pspp_test_assert( ! is_wp_error( ps_pen_paper_save_rules( $campaign_id, $direct_rules_input, '3' ) ), 'Game master could not configure direct editing or rename a used field.' );
	$state = get_post_meta( $character_id, '_pspp_character', true );
	pspp_test_assert( null !== $state['pending'] && 10 === $state['active']['values'][ $resource_id ]['current'], 'Changing mode automatically resolved a pending request.' );
	pspp_test_assert( true === ps_pen_paper_review_character( $campaign_id, $character_id, 'reject', '3' ), 'Game master could not reject a request after switching mode.' );

	wp_set_current_user( $user_ids['player'] );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_character( $campaign_id, $character_id, $user_ids['player'], $character_input, '4', '3' ) ), 'Stale rule revision was accepted.' );
	pspp_test_assert( $character_id === ps_pen_paper_save_character( $campaign_id, $character_id, $user_ids['player'], $character_input, '4', '4' ), 'Direct player editing failed.' );
	$state = get_post_meta( $character_id, '_pspp_character', true );
	pspp_test_assert( null === $state['pending'] && 20 === $state['active']['values'][ $resource_id ]['current'], 'Direct mode did not immediately activate changes.' );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_character( $campaign_id, $character_id, $user_ids['player'], $character_input, '4', '4' ) ), 'Stale character write was accepted.' );

	wp_set_current_user( $user_ids['gm'] );
	$gm_rules_input = $direct_rules_input;
	$gm_rules_input['character_mode'] = 'gm_only';
	pspp_test_assert( ! is_wp_error( ps_pen_paper_save_rules( $campaign_id, $gm_rules_input, '4' ) ), 'Game master could not configure GM-only editing.' );
	pspp_test_assert( 'approval' === ps_pen_paper_character_mode( ps_pen_paper_get_rules( $other_campaign_id ) ), 'Editing policy leaked to another campaign.' );
	wp_set_current_user( $user_ids['player'] );
	pspp_test_assert( ! ps_pen_paper_can_edit_character( $campaign, get_post( $character_id ) ), 'GM-only mode exposes editing to players.' );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_character( $campaign_id, $character_id, $user_ids['player'], $character_input, '5', '5' ) ), 'GM-only mode allowed a player to save.' );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_character( $campaign_id, 0, $user_ids['player'], $character_input, '0', '5' ) ), 'GM-only mode allowed a player to create a character.' );
	pspp_test_assert( null !== ps_pen_paper_get_character( $campaign, $character_id ), 'GM-only mode removed the owner’s read access.' );
	wp_set_current_user( $user_ids['gm'] );
	pspp_test_assert( $character_id === ps_pen_paper_save_character( $campaign_id, $character_id, $user_ids['player'], $character_input, '5', '5' ), 'Game master could not correct the character.' );
	pspp_test_assert( count( ps_pen_paper_get_characters( $campaign ) ) === 1, 'Game master cannot list characters.' );

	wp_set_current_user( $user_ids['outsider'] );
	pspp_test_assert( ! ps_pen_paper_can_view_campaign( $campaign ) && ! ps_pen_paper_can_manage_campaign( $campaign ), 'Nonmember has campaign access.' );
	pspp_test_assert( ! in_array( $campaign_id, wp_list_pluck( ps_pen_paper_get_campaigns(), 'ID' ), true ), 'Nonmember can list the campaign.' );
	pspp_test_assert( ! current_user_can( 'read_post', $campaign_id ), 'Unrelated editor can read the campaign through WordPress.' );
	pspp_test_assert( ! current_user_can( 'delete_post', $campaign_id ), 'Unrelated editor can delete the campaign through WordPress.' );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_rules( $campaign_id, $rename_input, '3' ) ), 'Nonmember can save rules.' );
	pspp_test_assert( null === ps_pen_paper_get_character( $campaign, $character_id ) && array() === ps_pen_paper_get_characters( $campaign ), 'Nonmember can read characters.' );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_character( $campaign_id, $character_id, $user_ids['player'], $character_input, '6', '5' ) ), 'Nonmember can edit characters.' );
	add_post_meta( $campaign_id, '_pspp_player', (string) $user_ids['outsider'] );
	pspp_test_assert( null === ps_pen_paper_get_character( $campaign, $character_id ) && array() === ps_pen_paper_get_characters( $campaign ), 'Another campaign player can read the owner’s character.' );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_character( $campaign_id, $character_id, $user_ids['player'], $character_input, '6', '5' ) ), 'Another campaign player can edit the owner’s character.' );

	wp_set_current_user( $user_ids['gm'] );
	$colors = ps_pen_paper_style_defaults();
	$colors['background'] = '#ABCDEF';
	$colors['primary'] = '#ffff00';
	$style = ps_pen_paper_save_style( $campaign_id, $colors, '0' );
	pspp_test_assert( ! is_wp_error( $style ) && '#abcdef' === $style['colors']['background'] && $style === ps_pen_paper_get_style( $campaign_id ), 'Campaign colors were not normalized and persisted.' );
	pspp_test_assert( ps_pen_paper_style_defaults() === ps_pen_paper_get_style( $other_campaign_id )['colors'], 'Colors leaked into another campaign.' );
	pspp_test_assert( false !== strpos( ps_pen_paper_style_variables( $style['colors'] ), '--pspp-primary-text:#000000' ), 'Bright buttons did not receive contrasting black text.' );
	pspp_test_assert( ps_pen_paper_contrast( '#777777', '#ffffff' ) < 4.5 && ps_pen_paper_contrast( '#767676', '#ffffff' ) >= 4.5, 'Text contrast threshold is incorrect.' );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_style( $campaign_id, $colors, '0' ) ), 'Stale colors were accepted.' );
	foreach ( array( '#fff', '#xyzxyz', '#ffffff;background:red', "#ffffff\n", array( '#ffffff' ), null ) as $bad_color ) {
		$invalid_colors = $colors;
		$invalid_colors['text'] = $bad_color;
		pspp_test_assert( is_wp_error( ps_pen_paper_save_style( $campaign_id, $invalid_colors, '1' ) ), 'Invalid colors or injected CSS were accepted.' );
	}
	$invalid_colors = $colors;
	unset( $invalid_colors['text'] );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_style( $campaign_id, $invalid_colors, '1' ) ), 'Missing colors were accepted.' );
	$invalid_colors = $colors;
	$invalid_colors['unknown'] = '#ffffff';
	pspp_test_assert( is_wp_error( ps_pen_paper_save_style( $campaign_id, $invalid_colors, '1' ) ) && $style === ps_pen_paper_get_style( $campaign_id ), 'Rejected colors modified the saved palette.' );
	$reset_style = ps_pen_paper_save_style( $campaign_id, ps_pen_paper_style_defaults(), '1' );
	pspp_test_assert( ! is_wp_error( $reset_style ) && 2 === $reset_style['revision'] && ps_pen_paper_style_defaults() === $reset_style['colors'], 'Resetting colors did not persist defaults.' );
	wp_set_current_user( $user_ids['player'] );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_style( $campaign_id, $colors, '2' ) ), 'Player can change campaign colors.' );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_style( $other_campaign_id, $colors, '0' ) ), 'Nonmember can change another campaign’s colors.' );

	$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'PSPP frontend test ' . $suffix, 'post_content' => '[ps_pen_paper]' ), true );
	pspp_test_assert( ! is_wp_error( $page_id ) && $page_id, 'Could not create shortcode test page.' );
	$post_ids[] = $page_id;
	$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $page_id ) );
	pspp_test_assert( '/theme/page.php' === ps_pen_paper_frontend_template( '/theme/page.php' ), 'Game template replaced the theme without being selected.' );
	update_post_meta( $page_id, '_wp_page_template', 'pspp-game' );
	pspp_test_assert( '/theme/page.php' !== ps_pen_paper_frontend_template( '/theme/page.php' ) && is_file( ps_pen_paper_frontend_template( '/theme/page.php' ) ), 'Selected game template is not available.' );
	$_GET = array( 'pspp_campaign' => (string) $campaign_id, 'pspp_character' => (string) $character_id );
	$html = ps_pen_paper_frontend_shortcode();
	pspp_test_assert( false !== strpos( $html, 'Testcharakter' ) && false === strpos( $html, 'name="character[name]"' ), 'GM-only frontend did not preserve owner read-only access.' );
	wp_set_current_user( $user_ids['gm'] );
	$html = ps_pen_paper_frontend_shortcode();
	pspp_test_assert( false !== strpos( $html, 'name="pspp_frontend_page" value="' . $page_id . '"' ) && false !== strpos( $html, 'name="character[name]"' ), 'Frontend character form lost return context or GM edit rights.' );
	pspp_test_assert( false !== strpos( $html, '<form method="post" action="' . esc_url( get_permalink( $page_id ) ) . '"' ) && false === strpos( $html, 'admin-post.php' ), 'Frontend forms still depend on backend access.' );
	$return_url = ps_pen_paper_character_url( $campaign_id, $character_id, $page_id );
	pspp_test_assert( false !== strpos( $return_url, 'pspp_campaign=' . $campaign_id ) && false !== strpos( $return_url, 'pspp_character=' . $character_id ) && false === strpos( $return_url, 'admin.php' ), 'Frontend character links lead into the backend.' );
	$approval_input = $gm_rules_input;
	$approval_input['character_mode'] = 'approval';
	pspp_test_assert( ! is_wp_error( ps_pen_paper_save_rules( $campaign_id, $approval_input, '5' ) ), 'Could not configure frontend approval test.' );
	wp_set_current_user( $user_ids['player'] );
	pspp_test_assert( false !== strpos( ps_pen_paper_frontend_shortcode(), 'Zur Freigabe einreichen' ), 'Frontend does not honor campaign approval policy.' );
	wp_set_current_user( $user_ids['outsider'] );
	$html = ps_pen_paper_frontend_shortcode();
	pspp_test_assert( false === strpos( $html, 'Testcharakter' ) && false === strpos( $html, $campaign->post_title ), 'Another player’s private character was disclosed.' );
	delete_post_meta( $campaign_id, '_pspp_player', (string) $user_ids['outsider'] );
	$html = ps_pen_paper_frontend_shortcode();
	pspp_test_assert( false === strpos( $html, $campaign->post_title ) && false === strpos( $html, 'Testcharakter' ), 'Nonmember frontend disclosed private content.' );
	$_GET = array();
	pspp_test_assert( false === strpos( ps_pen_paper_frontend_shortcode(), $campaign->post_title ), 'Nonmember frontend lists private campaigns.' );
	wp_set_current_user( $user_ids['player'] );
	$html = ps_pen_paper_frontend_shortcode();
	pspp_test_assert( false !== strpos( $html, $campaign->post_title ) && false === strpos( $html, 'PSPP second test campaign' ), 'Frontend campaign list ignores membership.' );
	$_GET = array( 'pspp_view' => 'characters' );
	$html = ps_pen_paper_frontend_shortcode();
	pspp_test_assert( false !== strpos( $html, 'Testcharakter' ) && false !== strpos( $html, esc_url( $return_url ) ), 'Character library lost the owner’s character or frontend detail link.' );
	wp_set_current_user( $user_ids['outsider'] );
	$html = ps_pen_paper_frontend_shortcode();
	pspp_test_assert( false === strpos( $html, 'Testcharakter' ) && false === strpos( $html, $campaign->post_title ), 'Character library exposed another campaign’s private character.' );
	wp_set_current_user( $user_ids['gm'] );
	$html = ps_pen_paper_frontend_shortcode();
	pspp_test_assert( false !== strpos( $html, 'Testcharakter' ), 'Character library hides GM-managed characters.' );
	$_GET = array( 'pspp_campaign' => (string) $campaign_id );
	$html = ps_pen_paper_frontend_shortcode();
	foreach ( array( 'save_rules', 'update', 'add_member', 'remove_member' ) as $operation ) {
		pspp_test_assert( false !== strpos( $html, 'name="pspp_operation" value="' . $operation . '"' ), 'Frontend GM preparation is missing operation ' . $operation );
	}
	pspp_test_assert( false === strpos( $html, 'admin-post.php' ) && false === strpos( $html, 'Weitere Vorbereitung: Regeln und Mitgliederverwaltung' ), 'Frontend campaign preparation still requires backend access.' );
	pspp_test_assert( false !== strpos( $html, 'name="rules_revision"' ) && false !== strpos( $html, 'name="membership_revision"' ) && false !== strpos( $html, 'name="pspp_scope_epoch"' ), 'Frontend preparation lost stale-input protection.' );
	wp_set_current_user( $user_ids['owner'] );
	$_GET = array( 'pspp_view' => 'campaigns' );
	pspp_test_assert( false === strpos( ps_pen_paper_frontend_shortcode(), 'name="pspp_operation" value="create"' ), 'Frontend offers campaign creation without permission.' );
	$owner->add_cap( 'ps_pen_paper_create_campaigns' );
	wp_set_current_user( 0 );
	wp_set_current_user( $user_ids['owner'] );
	$html = ps_pen_paper_frontend_shortcode();
	pspp_test_assert( false !== strpos( $html, 'name="pspp_operation" value="create"' ) && false !== strpos( $html, 'name="pspp_frontend_page" value="' . $page_id . '"' ) && false === strpos( $html, 'admin-post.php' ), 'Permitted frontend campaign creation is absent or posts into the backend.' );
	$owner->remove_cap( 'ps_pen_paper_create_campaigns' );
	wp_set_current_user( $user_ids['player'] );
	$_GET = array( 'pspp_campaign' => (string) $campaign_id );
	$html = ps_pen_paper_frontend_shortcode();
	foreach ( array( 'save_rules', 'update', 'add_member', 'remove_member' ) as $operation ) {
		pspp_test_assert( false === strpos( $html, 'name="pspp_operation" value="' . $operation . '"' ), 'Frontend exposes campaign management to a player: ' . $operation );
	}
	pspp_test_assert( false !== strpos( $html, 'pspp-rules-heading' ), 'Players lost read-only access to campaign rules.' );
	$_GET = array( 'pspp_view' => array( 'characters' ) );
	pspp_test_assert( false === strpos( ps_pen_paper_frontend_shortcode(), 'Testcharakter' ), 'Malformed dashboard view exposes content.' );
	foreach ( array( '-1', '1oops', array( '1' ), '999999999999999999999999999999' ) as $bad_id ) {
		$_GET = array( 'pspp_campaign' => $bad_id );
		pspp_test_assert( false === strpos( ps_pen_paper_frontend_shortcode(), $campaign->post_title ), 'Malformed frontend campaign ID exposes content.' );
	}
	$_GET = array( 'pspp_campaign' => (string) $campaign_id );
	$GLOBALS['wp_query']->is_feed = true;
	pspp_test_assert( false === strpos( ps_pen_paper_frontend_shortcode(), $campaign->post_title ), 'Shortcode renders private content in feeds.' );
	$GLOBALS['wp_query']->is_feed = false;
	wp_set_current_user( 0 );
	pspp_test_assert( false === strpos( ps_pen_paper_frontend_shortcode(), $campaign->post_title ), 'Guest frontend disclosed a private campaign.' );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_style( $campaign_id, $colors, '2' ) ), 'Anonymous visitor can save colors.' );
	pspp_test_assert( is_wp_error( ps_pen_paper_save_rules( $campaign_id, $rename_input, '3' ) ), 'Anonymous visitor can save rules.' );
	wp_update_post( array( 'ID' => $page_id, 'post_status' => 'draft' ) );
	pspp_test_assert( null === ps_pen_paper_frontend_page( $page_id ), 'Draft page is accepted as frontend return context.' );
	wp_set_current_user( $user_ids['gm'] );
	$die_handler = static function () {
		return static function ( $message ) { throw new RuntimeException( $message ); };
	};
	add_filter( 'wp_die_handler', $die_handler, 1000 );
	try {
		foreach ( array( 'create', 'update', 'save_rules', 'add_member', 'remove_member' ) as $operation ) {
			$_POST = array( 'pspp_operation' => $operation, 'pspp_frontend_page' => (string) $page_id, '_wpnonce' => 'invalid' );
			$_REQUEST = $_POST;
			$rejected = false;
			try {
				ps_pen_paper_handle_admin_action();
			} catch ( RuntimeException $error ) {
				$rejected = __( 'Die Spielbereich-Seite ist nicht mehr verfügbar. Bitte öffne das Formular erneut.', 'ps-pen-paper' ) === $error->getMessage();
			}
			pspp_test_assert( $rejected, 'Invalid frontend context reached campaign operation ' . $operation );
		}
	} finally {
		remove_filter( 'wp_die_handler', $die_handler, 1000 );
	}

	$type = get_post_type_object( 'pspp_campaign' );
	pspp_test_assert( ! $type->publicly_queryable && ! $type->show_in_rest && ! $type->show_ui, 'Campaign post type is exposed.' );
	$character_type = get_post_type_object( 'pspp_character' );
	pspp_test_assert( ! $character_type->publicly_queryable && ! $character_type->show_in_rest && ! current_user_can( 'read_post', $character_id ), 'Character is exposed through WordPress.' );
} finally {
	wp_set_current_user( $original_user );
	$GLOBALS['wp_query'] = $original_query;
	$_GET = $original_get;
	$_POST = $original_post;
	$_REQUEST = $original_request;
	$cleanup_errors = array();
	$events = $post_ids ? get_posts( array( 'post_type' => 'pspp_event', 'post_status' => array( 'private', 'draft' ), 'post_parent__in' => $post_ids, 'posts_per_page' => -1, 'fields' => 'ids' ) ) : array();
	foreach ( $events as $id ) {
		if ( ! wp_delete_post( $id, true ) ) { $cleanup_errors[] = 'Could not remove test event ' . $id; }
	}
	foreach ( $post_ids as $post_id ) {
		if ( ! wp_delete_post( $post_id, true ) ) {
			$cleanup_errors[] = 'Could not remove test campaign ' . $post_id;
		}
	}
	foreach ( $user_ids as $user_id ) {
		$removed = is_multisite() ? wpmu_delete_user( $user_id ) : wp_delete_user( $user_id );
		if ( ! $removed ) {
			$cleanup_errors[] = 'Could not remove test user ' . $user_id;
		}
	}
	if ( $cleanup_errors ) {
		WP_CLI::error( implode( '; ', $cleanup_errors ) );
	}
}
WP_CLI::success( 'Campaign rules, character policies, approvals, ownership, frontend privacy, colors and conflict checks passed.' );
