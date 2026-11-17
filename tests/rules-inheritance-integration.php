<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'character', 'event', 'asset' ) as $type ) {
	call_user_func( 'ps_pen_paper_register_' . $type . '_type' );
}
$check = static function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$original_user = get_current_user_id();
$users = array(); $scopes = array();
try {
	foreach ( array( 'gm', 'player' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-inheritance-' . $role . '-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber' ) );
		$check( is_int( $id ), 'Fixture user creation failed.' ); $users[ $role ] = $id;
	}
	get_userdata( $users['gm'] )->add_cap( 'ps_pen_paper_create_campaigns' );
	wp_set_current_user( $users['gm'] );
	$universe = ps_pen_paper_save_universe( 0, array( 'name' => 'Inheritance fixture', 'description' => '' ), '0' );
	$check( is_int( $universe ), 'Fixture universe creation failed.' ); $scopes[] = $universe;
	$input = array( 'name' => 'World rules', 'description' => 'World description', 'dice' => array( '20' ), 'character_mode' => 'direct',
		'fields' => array( array( 'id' => '', 'label' => 'Strength', 'description' => '', 'type' => 'attribute', 'min' => '0', 'max' => '20', 'start' => '2' ) ) );
	$world = ps_pen_paper_save_rules( $universe, $input, '0' );
	$check( ! is_wp_error( $world ), 'Fixture rules creation failed.' );
	$field = $world['fields'][0]['id'];
	$input = ps_pen_paper_inheritance_rules_input( $world );
	$input['campaign_policy'] = array( 'extra_fields' => false, 'globals' => array( 'description' ), 'fields' => array( $field => array( 'min' => 0, 'max' => 20 ) ) );
	$world = ps_pen_paper_save_rules( $universe, $input, '1' );
	$check( ! is_wp_error( $world ) && ps_pen_paper_valid_rules_document( $world ), 'Flexible policy was not saved as a valid document.' );
	$campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Inheritance fixture campaign' ), true );
	$check( is_int( $campaign_id ), 'Fixture campaign creation failed.' ); $scopes[] = $campaign_id;
	add_post_meta( $campaign_id, '_pspp_player', (string) $users['player'] );
	$check( ! is_wp_error( ps_pen_paper_link_new_campaign( $campaign_id, $universe, '1' ) ), 'Initial inheritance failed.' );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	$local = ps_pen_paper_get_rules( $campaign_id );
	$edit = ps_pen_paper_inheritance_rules_input( $local );
	$edit['name'] = 'Forbidden fixed name';
	$check( is_wp_error( ps_pen_paper_save_rules( $campaign_id, $edit, '1' ) ), 'Fixed world rule was editable.' );
	$edit = ps_pen_paper_inheritance_rules_input( $local ); $edit['fields'][0]['max'] = '21';
	$check( is_wp_error( ps_pen_paper_save_rules( $campaign_id, $edit, '1' ) ), 'Flexible field exceeded its world limit.' );
	$edit['fields'][0]['max'] = '15'; $edit['description'] = 'Campaign override';
	$local = ps_pen_paper_save_rules( $campaign_id, $edit, '1' );
	$check( ! is_wp_error( $local ), 'Allowed campaign override failed.' );
	$world_input = ps_pen_paper_inheritance_rules_input( $world ); $world_input['description'] = 'New world description';
	$world = ps_pen_paper_save_rules( $universe, $world_input, '2' );
	$local = ps_pen_paper_get_rules( $campaign_id );
	$check( ! is_wp_error( $world ) && 'Campaign override' === $local['description'] && 15 === $local['fields'][0]['max'] &&
		$world === ps_pen_paper_get_universe_link( $campaign_id )['rules_base'], 'Automatic update lost overrides or failed to advance its base.' );
	$character = ps_pen_paper_save_character( $campaign_id, 0, $users['player'], array( 'name' => 'Fixture hero', 'values' => array( $field => '2' ) ), '0', (string) $local['revision'] );
	$check( is_int( $character ), 'Fixture character creation failed.' );
	$old_state = ps_pen_paper_raw_character_state( $character );
	$old_rules = $local;
	$world_input = ps_pen_paper_inheritance_rules_input( $world );
	$world_input['fields'][0]['min'] = '5'; $world_input['fields'][0]['start'] = '5';
	$world = ps_pen_paper_save_rules( $universe, $world_input, '3' );
	$check( ! is_wp_error( $world ) && $old_rules === ps_pen_paper_get_rules( $campaign_id ) &&
		is_wp_error( ps_pen_paper_require_inherited_rules( $campaign_id ) ) && $old_state === ps_pen_paper_raw_character_state( $character ),
		'Conflicting world change silently altered campaign or character data.' );
	$preview = ps_pen_paper_preview_rules_migration( $campaign_id, null, (string) $old_rules['revision'], true );
	$check( ! is_wp_error( $preview ) && count( $preview['characters'][0]['conflicts'] ) === 1, 'Inherited migration did not expose the conflicting value.' );
	ob_start(); ps_pen_paper_render_rules( $campaign, true ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'sync_inherited_rules' ) && false !== strpos( $html, 'Universumsänderungen prüfen' ),
		'The campaign did not expose pending rule adoption.' );
	ob_start(); ps_pen_paper_render_rules_migration_preview( $campaign, $preview ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'name="rules_inheritance" value="1"' ) && false !== strpos( $html, $preview['hash'] ),
		'The inherited migration confirmation lost its preview identity.' );
	$token = $preview['characters'][0]['conflicts'][0]['token'];
	$check( is_wp_error( ps_pen_paper_save_rules_migration( $campaign_id, null, (string) $old_rules['revision'], array( $token => 'use_default' ), 'confirmed', str_repeat( '0', 64 ), true ) ) &&
		$old_state === ps_pen_paper_raw_character_state( $character ), 'Stale inherited preview was accepted.' );
	$old_link = ps_pen_paper_get_universe_link( $campaign_id );
	$published = 0;
	$fail_link_event = static function ( $data ) use ( &$published ) {
		if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] && 3 === ++$published ) { $data['post_status'] = 'draft'; }
		return $data;
	};
	add_filter( 'wp_insert_post_data', $fail_link_event );
	try {
		$failed = ps_pen_paper_save_rules_migration( $campaign_id, null, (string) $old_rules['revision'], array( $token => 'use_default' ), 'confirmed', $preview['hash'], true );
	} finally { remove_filter( 'wp_insert_post_data', $fail_link_event ); }
	$check( is_wp_error( $failed ) && $old_state === ps_pen_paper_raw_character_state( $character ) &&
		$old_rules === ps_pen_paper_get_rules( $campaign_id ) && $old_link === ps_pen_paper_get_universe_link( $campaign_id ),
		'Failed inheritance migration did not roll back all documents.' );
	$check( true === ps_pen_paper_save_rules_migration( $campaign_id, null, (string) $old_rules['revision'], array( $token => 'use_default' ), 'confirmed', $preview['hash'], true ),
		'Confirmed inherited migration failed.' );
	$local = ps_pen_paper_get_rules( $campaign_id );
	$check( 5 === ps_pen_paper_raw_character_state( $character )['active']['values'][ $field ] &&
		15 === $local['fields'][0]['max'] && 'Campaign override' === $local['description'] &&
		$world === ps_pen_paper_get_universe_link( $campaign_id )['rules_base'] &&
		true === ps_pen_paper_require_inherited_rules( $campaign_id ), 'Confirmed migration did not atomically update character, rules and base.' );
	$world_input = ps_pen_paper_inheritance_rules_input( $world );
	$world_input['campaign_policy']['globals'] = array();
	$world = ps_pen_paper_save_rules( $universe, $world_input, '4' );
	$check( ! is_wp_error( $world ) && is_wp_error( ps_pen_paper_require_inherited_rules( $campaign_id ) ),
		'Revoking flexibility did not block a conflicting campaign override.' );
	$edit = ps_pen_paper_inheritance_rules_input( $local ); $edit['description'] = 'New world description';
	$local = ps_pen_paper_save_rules( $campaign_id, $edit, (string) $local['revision'] );
	$check( ! is_wp_error( $local ) && true === ps_pen_paper_synchronize_inherited_rules( $campaign ) &&
		$world === ps_pen_paper_get_universe_link( $campaign_id )['rules_base'] &&
		true === ps_pen_paper_require_inherited_rules( $campaign_id ), 'A conflicting override could not be reset and synchronized.' );
	$events = get_posts( array( 'post_type' => 'pspp_event', 'post_status' => 'private', 'post_parent' => $campaign_id, 'posts_per_page' => -1 ) );
	$link_events = 0;
	foreach ( $events as $event_post ) {
		$event = get_post_meta( $event_post->ID, '_pspp_event', true );
		if ( 'universe_linked' !== ( $event['kind'] ?? '' ) ) { continue; }
		$link_events++;
		$check( ps_pen_paper_prepare_import_validate_event( $event, array( 'id' => $event_post->ID, 'parent_id' => $campaign_id ), array() ),
			'Inherited universe link event failed backup validation.' );
	}
	$check( $link_events >= 3, 'Expected inheritance history was not recorded.' );
	$historical_rules = ps_pen_paper_get_rules( $campaign_id );
	$new_field = wp_generate_uuid4();
	$historical_rules['fields'][] = array( 'id' => $new_field, 'label' => 'Later field', 'type' => 'text', 'description' => '', 'required' => false, 'start' => '' );
	$historical_rules['campaign_policy']['fields'][ $new_field ] = array( 'min' => null, 'max' => null );
	$check( ps_pen_paper_valid_rules_document( $historical_rules ), 'Historical backup fixture rules are invalid.' );
	$parsed = array( 'meta' => array( $campaign_id => array( '_pspp_rules' => array( $historical_rules ) ) ) );
	$created_event = null;
	foreach ( $events as $event_post ) {
		$event = get_post_meta( $event_post->ID, '_pspp_event', true );
		if ( 'character_created' === ( $event['kind'] ?? '' ) ) { $created_event = $event; break; }
	}
	$check( null !== $created_event && ps_pen_paper_prepare_import_validate_event( $created_event, array( 'id' => $event_post->ID, 'parent_id' => $campaign_id ), $parsed ),
		'A later flexible field invalidated historical character events in backups.' );
	$record = array( 'id' => $campaign_id, 'type' => 'pspp_campaign', 'parent_id' => 0 );
	$parsed = array( 'records' => array( $universe => array( 'id' => $universe, 'type' => 'pspp_universe' ) ) );
	$archive = array( 'references' => array(), 'warnings' => array() );
	$options = array( 'universe_mode' => 'bind' ); $map = array( $universe => $universe );
	$link = ps_pen_paper_get_universe_link( $campaign_id );
	$check( true === ps_pen_paper_prepare_import_remap_value( '_pspp_universe_link', $link, $record, $parsed, $options, $map, $archive ),
		'An identical inherited rule base could not be rebound.' );
	$link = $old_link;
	$check( is_wp_error( ps_pen_paper_prepare_import_remap_value( '_pspp_universe_link', $link, $record, $parsed, $options, $map, $archive ) ),
		'Import silently rebound an inherited campaign to a different rule base.' );
	WP_CLI::success( 'Fixed and flexible rules, bounds, automatic rebasing and confirmed character migration passed.' );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $scopes as $scope ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_asset', 'pspp_event' ), 'post_status' => array( 'private', 'draft', 'trash' ), 'post_parent' => $scope, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
		wp_delete_post( $scope, true );
	}
	foreach ( $users as $id ) { is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ); }
}
