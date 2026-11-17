<?php
/**
 * Run serially with WP-CLI against a disposable development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'character', 'event', 'asset' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
$original_user = get_current_user_id();
$users = array(); $scopes = array(); $fixture = './.pspp-policy-image-' . wp_generate_password( 12, false ) . '.png';
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$capture = function ( $callback ) { ob_start(); try { $callback(); return ob_get_contents(); } finally { ob_end_clean(); } };
$hidden = function ( $html, $name ) {
	if ( preg_match( '/<input[^>]+name="' . preg_quote( $name, '/' ) . '"[^>]+value="([^"]*)"/', $html, $match ) ) { return html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ); }
	throw new RuntimeException( 'Missing form field: ' . $name );
};
$create_campaign = function ( $fields = array() ) use ( &$scopes ) {
	$post = $_POST; $request = $_REQUEST;
	$result = array( 'redirect' => null, 'error' => null );
	$finished = new RuntimeException( 'Campaign request finished.' );
	$redirect = function ( $location ) use ( &$result, $finished ) { $result['redirect'] = $location; throw $finished; };
	$die = function () use ( &$result, $finished ) {
		return function ( $message ) use ( &$result, $finished ) { $result['error'] = $message; throw $finished; };
	};
	$_POST = array_merge( array( 'pspp_operation' => 'create', 'title' => 'Universe selection fixture', '_wpnonce' => wp_create_nonce( 'pspp_create' ) ), $fields );
	$_REQUEST = $_POST;
	add_filter( 'wp_redirect', $redirect, 0 );
	add_filter( 'wp_die_handler', $die );
	try {
		ps_pen_paper_handle_admin_action();
		throw new RuntimeException( 'Campaign creation did not terminate with a redirect or an error.' );
	} catch ( RuntimeException $error ) {
		if ( $error !== $finished ) { throw $error; }
	} finally {
		remove_filter( 'wp_redirect', $redirect, 0 ); remove_filter( 'wp_die_handler', $die );
		$_POST = $post; $_REQUEST = $request;
		$scopes = array_values( array_unique( array_merge( $scopes, get_posts( array(
			'post_type' => 'pspp_campaign', 'post_status' => array( 'private', 'draft' ), 'author' => get_current_user_id(),
			'posts_per_page' => -1, 'fields' => 'ids',
		) ) ) ) );
	}
	return $result;
};
$failure = static function ( $data ) { if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) { $data['post_status'] = 'draft'; } return $data; };
try {
	foreach ( array( 'member_invitation', 'start_adventure', 'advance_adventure', 'review_base_character', 'review_item_use', 'end_item_effect', 'change_wallet' ) as $operation ) {
		$check( ! ps_pen_paper_profile_operation_allowed( $operation, array() ), 'Non-character operation accepted in profile: ' . $operation );
	}
	$check( ps_pen_paper_profile_operation_allowed( 'use_item', array() ) &&
		ps_pen_paper_profile_operation_allowed( 'change_trade', array( 'trade_action' => 'accept' ) ) &&
		! ps_pen_paper_profile_operation_allowed( 'change_trade', array( 'trade_action' => 'approve' ) ) &&
		! ps_pen_paper_profile_operation_allowed( 'profile_portrait', array( 'portrait_operation' => 'approve' ) ),
		'Profile mixed owner actions with leadership approvals.' );
	$check( function_exists( 'ps_pen_paper_valid_policy_event' ), 'Policy event validator must be available before the first save/import.' );
	foreach ( array( 'gm', 'player', 'invited', 'outsider', 'site_player' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-policy-' . wp_generate_password( 14, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( ! is_wp_error( $id ), 'User creation failed.' ); $users[ $role ] = $id;
	}
	get_userdata( $users['gm'] )->add_cap( 'ps_pen_paper_create_campaigns' );
	wp_set_current_user( $users['gm'] );
	$html = $capture( function () { ps_pen_paper_render_campaign_create_form(); } );
	$result = $create_campaign();
	$check( false === strpos( $html, 'name="universe_id"' ) && null !== $result['error'] && ! ps_pen_paper_get_campaigns(),
		'Campaign creation without an available universe was accepted.' );
	$universe_id = ps_pen_paper_save_universe( 0, array( 'name' => 'Profile universe', 'description' => 'DO_NOT_EXPOSE_WORLD_DESCRIPTION' ), '0' );
	$check( is_int( $universe_id ) && $universe_id > 0, 'Universe creation failed.' ); $scopes[] = $universe_id;
	$universe = ps_pen_paper_get_universe( $universe_id );
	$html = $capture( function () { ps_pen_paper_render_campaign_create_form(); } );
	$check( 1 === preg_match( '/<option value="' . $universe_id . '"\s+selected(?:\s|=|>)/', $html ) &&
		false === strpos( $html, '<option value="">' ), 'The only available universe was not selected in the campaign form.' );
	$result = $create_campaign();
	$created = ps_pen_paper_get_campaigns();
	$check( null !== $result['redirect'] && null === $result['error'] && 1 === count( $created ) &&
		$universe_id === ps_pen_paper_get_universe_link( $created[0]->ID )['universe_id'],
		'Campaign creation did not persist its sole available universe when the field was omitted.' );
	$result = $create_campaign( array( 'universe_id' => array( (string) $universe_id ) ) );
	$check( null !== $result['error'] && 1 === count( ps_pen_paper_get_campaigns() ),
		'Malformed universe input silently fell back to automatic assignment.' );
	$second_universe = ps_pen_paper_save_universe( 0, array( 'name' => 'Second creation universe', 'description' => '' ), '0' );
	$check( is_int( $second_universe ), 'Second creation universe failed.' ); $scopes[] = $second_universe;
	$html = $capture( function () { ps_pen_paper_render_campaign_create_form(); } );
	$result = $create_campaign( array( 'universe_id' => '' ) );
	$check( false !== strpos( $html, '<option value="">' ) && null !== $result['error'] && 1 === count( ps_pen_paper_get_campaigns() ),
		'Multiple universes did not require an explicit campaign selection.' );
	$result = $create_campaign( array( 'universe_id' => (string) $second_universe ) );
	$selected = array_values( array_diff( wp_list_pluck( ps_pen_paper_get_campaigns(), 'ID' ), wp_list_pluck( $created, 'ID' ) ) );
	$check( null !== $result['redirect'] && null === $result['error'] && 1 === count( $selected ) &&
		$second_universe === ps_pen_paper_get_universe_link( $selected[0] )['universe_id'],
		'Explicit campaign universe selection was not persisted.' );
	$rules_input = array( 'name' => 'Named text selections', 'description' => '', 'dice' => array( '20' ), 'character_mode' => 'approval',
		'fields' => array( array( 'id' => '', 'label' => 'Freie Herkunft', 'type' => 'text', 'description' => '',
			'required' => '1', 'start' => 'Wanderer', 'allowed_choices' => "Wanderer\nWächter" ) ) );
	$rules = ps_pen_paper_save_rules( $universe_id, $rules_input, '0' );
	$check( ! is_wp_error( $rules ), 'Native rules creation failed: ' . ( is_wp_error( $rules ) ? $rules->get_error_message() : '' ) );
	$check( 'text' === $rules['fields'][0]['type'] && array( 'Wanderer', 'Wächter' ) === $rules['fields'][0]['choices'], 'Text choices introduced a fixed system or failed validation.' );
	$before_creation = wp_list_pluck( ps_pen_paper_get_campaigns(), 'ID' );
	$result = $create_campaign( array( 'universe_id' => (string) $universe_id ) );
	$new_campaigns = array_values( array_diff( wp_list_pluck( ps_pen_paper_get_campaigns(), 'ID' ), $before_creation ) );
	$check( null !== $result['redirect'] && null === $result['error'] && 1 === count( $new_campaigns ) &&
		$rules === ps_pen_paper_get_rules( $new_campaigns[0] ) &&
		$rules === ps_pen_paper_get_rules( $created[0]->ID ),
		'Campaign creation or subsequent universe setup failed to provide inherited rules.' );
	$second_rules = ps_pen_paper_save_rules( $second_universe, $rules_input, '0' );
	$check( ! is_wp_error( $second_rules ), 'Second world rule fixture failed.' );
	$second_input = $rules_input; $second_input['fields'][0]['id'] = $second_rules['fields'][0]['id'];
	$second_input['description'] = 'Second world revision';
	$second_rules = ps_pen_paper_save_rules( $second_universe, $second_input, '1' );
	$check( ! is_wp_error( $second_rules ) && 2 === $second_rules['revision'], 'Second world revision fixture failed.' );
	$before_creation = wp_list_pluck( ps_pen_paper_get_campaigns(), 'ID' );
	$result = $create_campaign( array( 'universe_id' => (string) $second_universe ) );
	$new_campaigns = array_values( array_diff( wp_list_pluck( ps_pen_paper_get_campaigns(), 'ID' ), $before_creation ) );
	$second_rules['revision'] = 1;
	$updated_existing = $second_rules; $updated_existing['revision'] = 2;
	$check( null !== $result['redirect'] && null === $result['error'] && 1 === count( $new_campaigns ) &&
		$second_rules === ps_pen_paper_get_rules( $new_campaigns[0] ) &&
		$updated_existing === ps_pen_paper_get_rules( $selected[0] ), 'Campaign rule revision or automatic universe updates failed.' );
	$invalid_source_campaign = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Invalid rule source fixture' ), true );
	$scopes[] = $invalid_source_campaign;
	$valid_source = get_post_meta( $second_universe, '_pspp_rules', true );
	update_post_meta( $second_universe, '_pspp_rules', array( 'revision' => 0 ) );
	try { $invalid_source = ps_pen_paper_link_new_campaign( $invalid_source_campaign, $second_universe, '1' ); }
	finally { update_post_meta( $second_universe, '_pspp_rules', $valid_source ); }
	$check( is_wp_error( $invalid_source ) && 0 === ps_pen_paper_get_universe_link( $invalid_source_campaign )['revision'] &&
		0 === ps_pen_paper_get_rules( $invalid_source_campaign )['revision'], 'Invalid universe rules were accepted or partially linked.' );
	$field_id = $rules['fields'][0]['id'];
	wp_set_current_user( $users['outsider'] );
	$default_choices = array_values( array_filter( ps_pen_paper_profile_universe_choices(), function ( $choice ) use ( $universe_id ) { return $choice['id'] === $universe_id; } ) );
	$check( 1 === count( $default_choices ) && $default_choices[0]['can_create'], 'Default universe added an unrequested membership prerequisite to profile character creation.' );
	$default_form = $capture( function () use ( $universe_id ) { ps_pen_paper_render_profile_universe_characters( $universe_id ); } );
	$check( false !== strpos( $default_form, 'profile_create_character' ) &&
		false === strpos( $default_form, 'invitation_operation' ), 'Profile character creation was replaced by an invitation administration form.' );
	wp_set_current_user( $users['site_player'] );
	$site_character = ps_pen_paper_profile_character_request( 'create', array(
		'universe_id' => $universe_id, 'universe_epoch' => $hidden( $default_form, 'universe_epoch' ),
		'rules_revision' => $hidden( $default_form, 'character_rules_revision' ),
		'character' => array( 'name' => 'Direct profile character', 'values' => array( $field_id => 'Wanderer' ) ),
	) );
	$check( is_int( $site_character ) && $site_character > 0 &&
		! ps_pen_paper_is_universe_member( get_post( $universe_id ), $users['site_player'] ),
		'Direct profile character creation required an additional universe membership.' );
	wp_set_current_user( $users['gm'] );
	$html = $capture( function () use ( $universe ) { ps_pen_paper_render_character_policy( $universe ); } );
	$check( false !== strpos( $html, 'enctype="multipart/form-data"' ) && wp_verify_nonce( $hidden( $html, '_wpnonce' ), 'pspp_campaign_' . $universe_id ), 'Policy upload/native nonce form broken.' );
	$policy_input = array( 'max_characters' => '1', 'creation_access' => 'members', 'custom_portraits' => 'disabled', 'member_invites' => 'approval' );
	$check( true === ps_pen_paper_save_character_policy( $universe_id, $policy_input, $hidden( $html, 'policy_revision' ) ), 'Native policy writer rejected rendered revision.' );
	wp_set_current_user( $users['outsider'] );
	$check( ! in_array( $universe_id, array_column( ps_pen_paper_profile_universe_choices(), 'id' ), true ) &&
		! ps_pen_paper_profile_characters(), 'Private universe exposed to outsider.' );
	$check( is_wp_error( ps_pen_paper_validate_campaign_universe( $universe_id ) ) && is_wp_error( ps_pen_paper_validate_campaign_universe( 0 ) ), 'Unauthorized/new campaign universe accepted.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_change_member_invitation( $universe_id, $users['player'], 'invite', '0' ), 'GM universe invitation failed.' );
	wp_set_current_user( $users['player'] );
	$html = $capture( function () { ps_pen_paper_render_profile_invitations(); } );
	$check( false !== strpos( $html, 'member_invitation' ) && false === strpos( $html, 'DO_NOT_EXPOSE_WORLD_DESCRIPTION' ), 'Received invitation form leaked description or absent.' );
	add_filter( 'wp_insert_post_data', $failure );
	try { $check( is_wp_error( ps_pen_paper_change_member_invitation( $universe_id, $users['player'], 'accept', '1' ) ) && ! ps_pen_paper_is_universe_member( $universe ), 'Failed acceptance did not roll back universe membership.' ); }
	finally { remove_filter( 'wp_insert_post_data', $failure ); }
	$check( true === ps_pen_paper_change_member_invitation( $universe_id, $users['player'], 'accept', $hidden( $html, 'invitation_revision' ) ) && ps_pen_paper_is_universe_member( $universe ), 'Invitee could not join without campaign-first navigation.' );
	$choices = array_values( array_filter( ps_pen_paper_profile_universe_choices(), function ( $choice ) use ( $universe_id ) { return $choice['id'] === $universe_id; } ) );
	$check( 1 === count( $choices ) && $choices[0]['member'] && $choices[0]['can_create'] && ! isset( $choices[0]['description'] ), 'Profile choices missing safe eligibility.' );
	$html = $capture( function () use ( $universe_id ) { ps_pen_paper_render_profile_universe_characters( $universe_id ); } );
	$check( false !== strpos( $html, 'profile_create_character' ) && false !== strpos( $html, '<select' ) && false === strpos( $html, 'DO_NOT_EXPOSE_WORLD_DESCRIPTION' ), 'Direct universe creation/selection form broken.' );
	$input = array( 'universe_id' => $universe_id, 'universe_epoch' => $hidden( $html, 'universe_epoch' ),
		'rules_revision' => $hidden( $html, 'character_rules_revision' ), 'character' => array( 'name' => 'Own profile hero', 'values' => array( $field_id => 'Wanderer' ) ) );
	$invalid = $input; $invalid['character']['values'][ $field_id ] = 'Invented';
	$check( is_wp_error( ps_pen_paper_profile_character_request( 'create', $invalid ) ), 'Unlisted named text choice accepted.' );
	$invalid = $input; $invalid['universe_epoch'] = wp_generate_uuid4();
	$check( is_wp_error( ps_pen_paper_profile_character_request( 'create', $invalid ) ), 'Stale imported universe epoch accepted.' );
	add_filter( 'wp_insert_post_data', $failure );
	try { $check( is_wp_error( ps_pen_paper_profile_character_request( 'create', $input ) ) && 0 === ps_pen_paper_universe_character_count( $universe_id, $users['player'] ), 'Failed creation left a counted draft or sheet.' ); }
	finally { remove_filter( 'wp_insert_post_data', $failure ); }
	$character_id = ps_pen_paper_profile_character_request( 'create', $input );
	$check( is_int( $character_id ) && $character_id > 0, 'Native profile creation failed.' );
	$rows = ps_pen_paper_profile_characters( $universe_id );
	$check( 1 === count( $rows ) && $rows[0]['scope'] instanceof WP_Post && $rows[0]['character'] instanceof WP_Post &&
		null === $rows[0]['state']['active'] && $rows[0]['state']['pending']['name'] === 'Own profile hero', 'Own pending base rows absent or prematurely approved.' );
	$limit = ps_pen_paper_profile_character_request( 'create', $input );
	$check( is_wp_error( $limit ) && 'character_limit' === $limit->get_error_code(), 'Pending first approval did not count toward limit.' );
	$html = $capture( function () use ( $universe_id ) { ps_pen_paper_render_profile_universe_characters( $universe_id ); } );
	$check( false === strpos( $html, 'Own profile hero' ), 'Creation renderer duplicates the common selected-character sheet.' );
	$document_before = get_post_meta( $universe_id, '_pspp_universe', true );
	update_post_meta( $universe_id, '_pspp_universe', array( 'revision' => 1 ) );
	$damaged_choices = array_values( array_filter( ps_pen_paper_profile_universe_choices(), function ( $choice ) use ( $universe_id ) { return $choice['id'] === $universe_id; } ) );
	$check( 1 === count( $damaged_choices ) && ! $damaged_choices[0]['can_create'] && '' !== $damaged_choices[0]['reason'], 'Damaged accessible universe disappeared silently.' );
	$html = $capture( function () use ( $universe_id ) { ps_pen_paper_render_profile_universe_characters( $universe_id ); } );
	$check( false !== strpos( $html, 'role="alert"' ), 'Corrupt selected universe failed to render an explicit alert.' );
	update_post_meta( $universe_id, '_pspp_universe', $document_before );
	$check( is_wp_error( ps_pen_paper_save_portrait( $universe, $character_id, '0', 'upload', $fixture ) ), 'Disabled custom upload allowed.' );
	$check( true === ps_pen_paper_change_member_invitation( $universe_id, $users['invited'], 'invite', '2' ), 'Member invitation request failed.' );
	wp_set_current_user( $users['invited'] );
	$invitations = ps_pen_paper_profile_invitations();
	$check( 'approval' === $invitations['received'][0]['status'] && is_wp_error( ps_pen_paper_change_member_invitation( $universe_id, $users['invited'], 'accept', '3' ) ), 'Member invite bypassed GM or pending status hidden from invitee.' );
	wp_set_current_user( $users['gm'] );
	$profile_sheet = $capture( function () use ( $universe, $character_id ) {
		ps_pen_paper_render_characters( $universe, $character_id, 0, '', 'profile' );
	} );
	$check( false === strpos( $profile_sheet, 'data-character-tab="adventures"' ) &&
		false === strpos( $profile_sheet, 'data-character-panel="adventures"' ) &&
		false === strpos( $profile_sheet, 'value="review_base_character"' ) &&
		false === strpos( $profile_sheet, 'value="approve"' ),
		'Shared profile sheet exposed adventures or leadership approval controls.' );
	$gm_profile_invitations = $capture( function () { ps_pen_paper_render_profile_invitations(); } );
	$gm_scope_invitations = $capture( function () use ( $universe ) { ps_pen_paper_render_profile_invitations( 0, $universe ); } );
	$check( false === strpos( $gm_profile_invitations, 'value="approve"' ) &&
		false !== strpos( $gm_scope_invitations, 'value="approve"' ), 'Invitation approval was exposed in the player profile or removed from scope administration.' );
	$check( 1 === count( ps_pen_paper_profile_invitations()['approvals'] ) && true === ps_pen_paper_change_member_invitation( $universe_id, $users['invited'], 'approve', '3' ), 'GM did not see/approve member invitation.' );
	$check( true === ps_pen_paper_review_character( $universe_id, $character_id, 'approve', '1' ), 'Universe base review failed.' );
	$image_content = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9xkAAAAASUVORK5CYII=';
	$check( false !== file_put_contents( $fixture, base64_decode( $image_content ) ), 'Fixture creation failed.' );
	$check( true === ps_pen_paper_add_portrait_preset( $universe_id, 'Protected portrait', $fixture, '1' ), 'Protected portrait upload failed.' );
	$preset = ps_pen_paper_character_policy( $universe_id )['presets'][0]['asset_id'];
	wp_update_post( array( 'ID' => $preset, 'post_status' => 'draft' ) );
	$html = $capture( function () use ( $universe, $character_id ) { ps_pen_paper_render_portrait_presets( $universe, get_post( $character_id ) ); } );
	$check( false !== strpos( $html, 'role="alert"' ) && false !== strpos( $html, 'Protected portrait' ), 'Missing preset image failed silently.' );
	wp_update_post( array( 'ID' => $preset, 'post_status' => 'private' ) );
	$foreign_asset = wp_insert_post( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $universe_id,
		'post_content' => $image_content, 'post_mime_type' => 'image/png' ), true );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_save_portrait( $universe, $character_id, '0', 'preset', $foreign_asset ) ), 'Unlisted private world asset selectable as portrait.' );
	$check( is_wp_error( ps_pen_paper_private_image( $universe, $foreign_asset ) ), 'Universe membership opened arbitrary world assets.' );
	$check( true === ps_pen_paper_save_portrait( $universe, $character_id, '0', 'preset', $preset ), 'Allowed preset selection failed.' );
	$portrait = ps_pen_paper_portrait_state( $character_id );
	$check( $portrait['pending']['asset_id'] !== $preset && null !== $portrait['pending'] && 0 === $portrait['active']['asset_id'], 'Preset not frozen or bypassed pending portrait review.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_save_portrait( $universe, $character_id, '1', 'approve' ), 'GM portrait approval failed.' );
	$events = ps_pen_paper_get_events( $universe );
	$check( ! is_wp_error( $events ), 'Policy/invitation journal kinds are not integrated.' );
	$campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Legacy independent campaign' ), true );
	$scopes[] = $campaign_id;
	add_post_meta( $campaign_id, '_pspp_player', (string) $users['player'] );
	$campaign_rules = ps_pen_paper_save_rules( $campaign_id, $rules_input, '0' );
	$check( ! is_wp_error( $campaign_rules ), 'Legacy independent rules setup failed.' );
	$legacy_id = ps_pen_paper_save_character( $campaign_id, 0, $users['gm'], array( 'name' => 'Preserved independent hero', 'values' => array( $campaign_rules['fields'][0]['id'] => 'Wächter' ) ), '0', '1' );
	$check( is_int( $legacy_id ), 'Legacy independent sheet setup failed.' );
	$legacy_before = get_post_meta( $legacy_id );
	$campaign_before = get_post_meta( $campaign_id );
	$check( is_wp_error( ps_pen_paper_link_new_campaign( $campaign_id, $universe_id, '1' ) ), 'Creation link silently migrated legacy character campaign.' );
	$check( ! is_wp_error( ps_pen_paper_save_universe_link( $campaign_id, $universe_id, '0', '1', 'confirmed', 'local' ) ) &&
		$legacy_before === get_post_meta( $legacy_id ) && ! isset( get_post_meta( $legacy_id, '_pspp_character', true )['context'] ) &&
		$campaign_before['_pspp_rules'] === get_post_meta( $campaign_id )['_pspp_rules'], 'Explicit legacy repair overwrote independent sheet or rules.' );
	$copy_campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Explicit universe copy campaign' ), true );
	$scopes[] = $copy_campaign_id;
	add_post_meta( $copy_campaign_id, '_pspp_player', (string) $users['player'] );
	$check( ! is_wp_error( ps_pen_paper_link_new_campaign( $copy_campaign_id, $universe_id, '1' ) ), 'New campaign association failed.' );
	$inherited = ps_pen_paper_get_rules( $universe_id ); $inherited['revision'] = 1;
	$check( $inherited === ps_pen_paper_get_rules( $copy_campaign_id ), 'New campaign did not inherit the universe rulebook.' );
	$rules_only_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Existing rules without characters' ), true );
	$scopes[] = $rules_only_id;
	$rules_only = ps_pen_paper_save_rules( $rules_only_id, $rules_input, '0' );
	$check( ! is_wp_error( $rules_only ) && is_wp_error( ps_pen_paper_link_new_campaign( $rules_only_id, $universe_id, '1' ) ) &&
		$rules_only === ps_pen_paper_get_rules( $rules_only_id ) && 0 === ps_pen_paper_get_universe_link( $rules_only_id )['revision'],
		'Automatic adoption overwrote existing campaign rules or partially linked the campaign.' );
	$rollback_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Rule adoption rollback' ), true );
	$scopes[] = $rollback_id;
	$publications = 0;
	$fail_rule_event = static function ( $data ) use ( &$publications ) {
		if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] && 2 === ++$publications ) { $data['post_status'] = 'draft'; }
		return $data;
	};
	add_filter( 'wp_insert_post_data', $fail_rule_event );
	try { $failed_link = ps_pen_paper_link_new_campaign( $rollback_id, $universe_id, '1' ); }
	finally { remove_filter( 'wp_insert_post_data', $fail_rule_event ); }
	$check( is_wp_error( $failed_link ) && 0 === ps_pen_paper_get_universe_link( $rollback_id )['revision'] &&
		0 === ps_pen_paper_get_rules( $rollback_id )['revision'] &&
		! get_posts( array( 'post_type' => 'pspp_event', 'post_status' => array( 'private', 'draft' ), 'post_parent' => $rollback_id, 'fields' => 'ids' ) ),
		'Failed rule adoption did not roll back the universe link, rulebook and events.' );
	$copy_id = ps_pen_paper_attach_base_character( $copy_campaign_id, $character_id, '2', '1', 'copy', 'confirmed' );
	$check( is_int( $copy_id ), 'Explicit base copy failed.' );
	$copy_portrait = ps_pen_paper_portrait_state( $copy_id );
	$copy_asset = get_post( $copy_portrait['active']['asset_id'] );
	$check( $copy_asset && (int) $copy_asset->post_parent === $copy_campaign_id && $copy_portrait['active']['asset_id'] !== ps_pen_paper_portrait_state( $character_id )['active']['asset_id'], 'Base portrait not frozen privately to campaign scope.' );
	wp_set_current_user( $users['invited'] );
	$check( true === ps_pen_paper_change_member_invitation( $universe_id, $users['invited'], 'accept', '4' ) && ps_pen_paper_is_universe_member( $universe ), 'Approved member invitation not accepted.' );
	wp_set_current_user( $users['outsider'] );
	$check( ! ps_pen_paper_profile_characters() && ! ps_pen_paper_get_character( $universe, $character_id ), 'Another player obtained private base sheets.' );
	WP_CLI::success( 'Mandatory universes, atomic initial rule adoption, profile forms, choices, invitation approval/rollback, private frozen portraits and explicit legacy repair passed.' );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $scopes as $scope_id ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_asset', 'pspp_event' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $scope_id, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
		wp_delete_post( $scope_id, true );
	}
	foreach ( $users as $id ) { is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ); }
	if ( is_file( $fixture ) ) { unlink( $fixture ); }
}
