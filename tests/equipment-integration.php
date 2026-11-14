<?php
/**
 * Run with WP-CLI --skip-plugins --skip-themes against a development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'character', 'item', 'asset', 'roll', 'event' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
$original_user = get_current_user_id();
$users = array();
$parents = array();
$path = '';
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
try {
	foreach ( array( 'gm', 'player', 'outsider' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-gear-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( ! is_wp_error( $id ), 'Fixture account failed.' );
		$users[ $role ] = $id;
	}
	wp_set_current_user( $users['gm'] );
	$id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Equipment fixture' ), true );
	$check( is_int( $id ) && $id > 0, 'Fixture campaign failed.' );
	$parents[] = $id;
	add_post_meta( $id, '_pspp_gm', (string) $users['gm'] );
	add_post_meta( $id, '_pspp_player', (string) $users['player'] );
	$campaign = ps_pen_paper_get_campaign( $id );
	$input = array( 'name' => 'Equipment rules', 'description' => '', 'dice' => array( '6' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Mut', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '10', 'start' => '5' ),
	) );
	$rules = ps_pen_paper_save_rules( $id, $input, '0' );
	$check( ! is_wp_error( $rules ), 'Fixture rules failed.' );
	$field = $rules['fields'][0]['id'];
	$base_input = array( 'name' => 'PRIVATE_HERO', 'values' => array( $field => '5' ) );
	$character_id = ps_pen_paper_save_character( $id, 0, $users['player'], $base_input, '0', '1' );
	$check( is_int( $character_id ), 'Fixture character failed.' );
	$character = get_post( $character_id );
	$raw = get_post_meta( $character_id, '_pspp_character', true );
	$path = wp_tempnam( 'pspp-gear-test.png' );
	$check( false !== file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=' ) ), 'Image fixture failed.' );
	$check( ! ps_pen_paper_community_available() && is_wp_error( ps_pen_paper_save_equipment_schema( $campaign, array(), '0' ) ), 'Inventory worked without Community.' );
	wp_set_current_user( $users['player'] );
	$check( true === ps_pen_paper_save_portrait( $campaign, $character_id, '0', 'upload', $path ), 'Core portrait requires Community.' );
	$check( '' === ps_pen_paper_portrait_image( $campaign, $character ), 'Pending portrait exposed as approved.' );
	wp_set_current_user( $users['outsider'] );
	$check( is_wp_error( ps_pen_paper_portrait_image( $campaign, $character ) ) && is_wp_error( ps_pen_paper_save_portrait( $campaign, $character_id, '1', 'remove' ) ), 'Portrait ownership failed.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_save_portrait( $campaign, $character_id, '1', 'approve' ) && 0 === strpos( ps_pen_paper_portrait_image( $campaign, $character ), 'data:image/png;base64,' ), 'Portrait approval failed.' );
	$check( true === ps_pen_paper_save_sheet_style( $campaign, '0', $path ), 'Sheet background failed.' );
	$frozen_id = wp_generate_uuid4();
	$frozen = array( 'revision' => 4, 'active' => array( 'items' => array( $frozen_id => array(
		'source_id' => 1, 'scope_id' => $id, 'revision' => 1, 'item' => array( 'name' => 'Frozen item', 'description' => '', 'kind' => 'hut', 'rarity' => 'normal', 'rarity_label' => 'Normal', 'color' => '#718096', 'asset_id' => 0, 'properties' => array(), 'bonuses' => array( $field => 2 ) ),
	) ), 'slots' => array( 'kopf' => $frozen_id ) ), 'pending' => null );
	update_post_meta( $character_id, '_pspp_gear', $frozen );
	$check( 5 === ps_pen_paper_character_state( $campaign, $character )['active']['values'][ $field ] && is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array(), '4' ) ) && $frozen === get_post_meta( $character_id, '_pspp_gear', true ), 'Community deactivation applied bonuses, allowed mutations or lost stored gear.' );
	delete_post_meta( $character_id, '_pspp_gear' );
	define( 'CPC_CORE_PLUGINS', 'core-profile,core-activity' );
	require_once dirname( __DIR__, 2 ) . '/ps-community/activity/cpc_profile_tabs.php';
	$check( ps_pen_paper_community_available(), 'Real profile API not recognized.' );
	$schema = array( 'slots' => "kopf | Kopf | hut\nhand | Hand | hut\nbrust | Brust | ruestung", 'rarities' => "normal | Normal | #718096\nepisch | Episch | #805ad5" );
	$check( true === ps_pen_paper_save_equipment_schema( $campaign, $schema, '0' ), 'Schema save failed.' );
	$bad_schema = $schema; $bad_schema['slots'] = 'kopf | Kopf | ruestung';
	$check( is_wp_error( ps_pen_paper_save_equipment_schema( $campaign, $bad_schema, '1' ) ), 'Existing slots removed/retyped.' );
	$item_input = array( 'name' => 'PRIVATE_HAT', 'description' => 'Warm', 'kind' => 'hut', 'rarity' => 'episch', 'properties' => 'Gewicht | Leicht', 'bonuses' => array( $field => '2' ) );
	$item_id = ps_pen_paper_save_item( $campaign, 0, $item_input, '0', $path );
	$check( is_int( $item_id ), 'Item creation failed.' );
	$check( true === ps_pen_paper_save_gear( $campaign, $character_id, 'grant_item', array( 'item_id' => (string) $item_id, 'item_revision' => '1' ), '0' ), 'Issuance failed.' );
	$gear = ps_pen_paper_gear_state( $character_id );
	$instance = array_key_first( $gear['active']['items'] );
	$item_input['bonuses'][ $field ] = '3';
	$check( $item_id === ps_pen_paper_save_item( $campaign, $item_id, $item_input, '1' ) && 2 === ps_pen_paper_gear_state( $character_id )['active']['items'][ $instance ]['item']['bonuses'][ $field ], 'Template edit mutated issued instance.' );
	wp_set_current_user( $users['player'] );
	$check( isset( cpc_get_profile_tabs( $users['player'] )['pspp'] ) && ! isset( cpc_get_profile_tabs( $users['outsider'] )['pspp'] ), 'Own-profile tab visibility failed.' );
	$check( false === strpos( cpc_render_profile_tab_content( $users['outsider'], 'pspp' ), 'PRIVATE_HERO' ), 'Forged profile tab exposed data.' );
	$check( is_wp_error( ps_pen_paper_item_catalogue( $campaign ) ) && is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'grant_item', array(), '1' ) ), 'Player accessed catalogue/issuance.' );
	$check( is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array( 'slots' => array( 'kopf' => wp_generate_uuid4() ) ), '1' ) ), 'Forged item equipped.' );
	$check( is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array( 'slots' => array( 'kopf' => $instance, 'hand' => $instance ) ), '1' ) ), 'Same instance equipped twice.' );
	$check( is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array( 'slots' => array( 'brust' => $instance ) ), '1' ) ), 'Incompatible slot accepted.' );
	$old_token = ps_pen_paper_character_token( $character, ps_pen_paper_character_state( $campaign, $character ) );
	$equip = array( 'slots' => array( 'kopf' => $instance ) );
	$check( true === ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', $equip, '1' ) && 5 === ps_pen_paper_character_state( $campaign, $character )['active']['values'][ $field ], 'Pending equip altered effective values.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_save_gear( $campaign, $character_id, 'review_gear', array( 'decision' => 'approve' ), '2' ), 'Equipment approval failed.' );
	$state = ps_pen_paper_character_state( $campaign, $character );
	$check( 7 === $state['active']['values'][ $field ] && $raw === get_post_meta( $character_id, '_pspp_character', true ), 'Effective bonuses changed base state or were not applied.' );
	wp_set_current_user( $users['player'] );
	ob_start(); ps_pen_paper_render_characters( $campaign, $character_id ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'value="equip_items"' ) && false !== strpos( $html, 'value="save_portrait"' ), 'Approved character without a pending base request has no equipment/portrait controls.' );
	wp_set_current_user( $users['gm'] );
	$roll_input = array( 'expression' => 'W6', 'visibility' => 'group', 'character' => $old_token, 'attribute' => $field, 'label' => 'Equipment proof' );
	$check( is_wp_error( ps_pen_paper_roll_dice( $id, $roll_input, '1' ) ), 'Stale equipment token accepted.' );
	$roll_input['character'] = ps_pen_paper_character_token( $character, $state );
	$roll_id = ps_pen_paper_roll_dice( $id, $roll_input, '1' );
	$check( is_int( $roll_id ), 'Equipped roll failed.' );
	$roll = ps_pen_paper_get_roll( $campaign, $roll_id );
	$check( 7 === $roll['attribute']['value'] && 2 === $roll['character']['equipment']['bonuses'][ $field ], 'Roll lost equipment provenance.' );
	$stored_roll = get_post_meta( $roll_id, '_pspp_roll', true );
	$damaged_roll = $stored_roll; $damaged_roll['character']['equipment'] = null;
	update_post_meta( $roll_id, '_pspp_roll', $damaged_roll );
	$check( is_wp_error( ps_pen_paper_get_roll( $campaign, $roll_id ) ), 'Malformed optional equipment provenance accepted.' );
	update_post_meta( $roll_id, '_pspp_roll', $stored_roll );
	$bad_base = $base_input; $bad_base['values'][ $field ] = '9';
	$check( is_wp_error( ps_pen_paper_save_character( $id, $character_id, $users['player'], $bad_base, '1', '1' ) ), 'Base edit stranded equipped character.' );
	$changed = $raw; $changed['active']['values'][ $field ] = 9;
	update_post_meta( $character_id, '_pspp_character', $changed );
	$check( is_wp_error( ps_pen_paper_character_state( $campaign, $character ) ), 'Invalid combined values silently accepted.' );
	wp_set_current_user( $users['player'] );
	$check( true === ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array(), '3' ), 'Owner could not request recovery from invalid active equipment.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_save_gear( $campaign, $character_id, 'review_gear', array( 'decision' => 'reject' ), '4' ), 'Invalid active state blocked rejection.' );
	$check( true === ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array(), '5' ) && 9 === ps_pen_paper_character_state( $campaign, $character )['active']['values'][ $field ], 'GM could not unequip invalid active gear.' );
	$check( 7 === ps_pen_paper_get_roll( $campaign, $roll_id )['attribute']['value'], 'Old roll changed after unequipping.' );
	update_post_meta( $character_id, '_pspp_character', $raw );
	$failure = function ( $data ) { if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) { $data['post_status'] = 'draft'; } return $data; };
	add_filter( 'wp_insert_post_data', $failure );
	try {
		$check( is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', $equip, '6' ) ) && 6 === ps_pen_paper_gear_state( $character_id )['revision'], 'Journal failure did not roll back equipment.' );
		$assets_before = get_posts( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $id, 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$check( is_wp_error( ps_pen_paper_save_portrait( $campaign, $character_id, '2', 'upload', $path ) ) && 2 === ps_pen_paper_portrait_state( $character_id )['revision'], 'Portrait journal rollback failed.' );
		$check( $assets_before === get_posts( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $id, 'posts_per_page' => -1, 'fields' => 'ids' ) ), 'Failed portrait left orphan image.' );
	} finally { remove_filter( 'wp_insert_post_data', $failure ); }
	$check( ! is_wp_error( ps_pen_paper_get_events( $campaign ) ), 'New event kinds broke journal.' );
	ob_start();
	foreach ( ps_pen_paper_get_events( $campaign )['events'] as $event ) { if ( 'roll' !== $event['kind'] ) { ps_pen_paper_render_event_details( $event ); } }
	$html = ob_get_clean();
	$check( false !== strpos( $html, 'PRIVATE_HAT' ), 'Equipment history not rendered.' );
	$direct_rules = $rules; $direct_rules['character_mode'] = 'direct';
	update_post_meta( $campaign->ID, '_pspp_rules', $direct_rules );
	wp_set_current_user( $users['player'] );
	$check( true === ps_pen_paper_save_portrait( $campaign, $character_id, '2', 'remove' ) && 0 === ps_pen_paper_portrait_state( $character_id )['active']['asset_id'], 'Direct portrait removal failed.' );
	$check( true === ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', $equip, '6' ) && 7 === ps_pen_paper_character_state( $campaign, $character )['active']['values'][ $field ], 'Direct equipment policy failed.' );
	$direct_rules['character_mode'] = 'gm_only';
	update_post_meta( $campaign->ID, '_pspp_rules', $direct_rules );
	$check( is_wp_error( ps_pen_paper_save_portrait( $campaign, $character_id, '3', 'upload', $path ) ) && is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array(), '7' ) ), 'GM-only policy allowed owner changes.' );
	update_post_meta( $campaign->ID, '_pspp_rules', $rules );
	wp_set_current_user( $users['outsider'] );
	$check( is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array(), '6' ) ), 'Nonmember changed equipment.' );
	wp_set_current_user( $users['gm'] );
	wp_get_current_user()->add_cap( 'ps_pen_paper_create_campaigns' );
	$universe_id = ps_pen_paper_save_universe( 0, array( 'name' => 'Private item universe', 'description' => '' ), '0' );
	$check( is_int( $universe_id ), 'Universe fixture failed.' );
	$parents[] = $universe_id;
	$universe = ps_pen_paper_get_universe( $universe_id );
	$world_rules = ps_pen_paper_save_rules( $universe_id, $input, '0' );
	$world_field = $world_rules['fields'][0]['id'];
	$world_input = array( 'name' => 'Shared equipment hero', 'values' => array( $world_field => '5' ) );
	$base_id = ps_pen_paper_save_character( $universe_id, 0, $users['player'], $world_input, '0', '1' );
	$linked_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['outsider'], 'post_title' => 'Linked equipment fixture' ), true );
	$check( is_int( $base_id ) && is_int( $linked_id ), 'Linked fixture failed.' );
	$parents[] = $linked_id;
	add_post_meta( $linked_id, '_pspp_gm', (string) $users['gm'] );
	add_post_meta( $linked_id, '_pspp_gm', (string) $users['outsider'] );
	add_post_meta( $linked_id, '_pspp_player', (string) $users['player'] );
	$linked = ps_pen_paper_get_campaign( $linked_id );
	$check( ! is_wp_error( ps_pen_paper_save_universe_link( $linked_id, $universe_id, '0', '1', 'confirmed' ) ) && ! is_wp_error( ps_pen_paper_adopt_universe_rules( $linked_id, '1', 'confirmed' ) ), 'Universe link/adoption failed.' );
	$alias_id = ps_pen_paper_attach_base_character( $linked_id, $base_id, '1', '1', 'shared', 'confirmed' );
	$check( is_int( $alias_id ) && true === ps_pen_paper_save_equipment_schema( $universe, $schema, '0' ) && true === ps_pen_paper_save_equipment_schema( $linked, $schema, '0' ), 'Shared alias/schema failed.' );
	$item_input['bonuses'] = array( $world_field => '2' );
	$world_item = ps_pen_paper_save_item( $universe, 0, $item_input, '0', $path );
	$check( is_int( $world_item ), 'Universe item failed.' );
	wp_set_current_user( $users['outsider'] );
	$check( ! ps_pen_paper_can_manage_universe( $universe ) && isset( ps_pen_paper_item_catalogue( $linked )[ $world_item ] ), 'Linked campaign catalogue requires unrelated universe administration.' );
	$check( true === ps_pen_paper_save_gear( $linked, $alias_id, 'grant_item', array( 'item_id' => (string) $world_item, 'item_revision' => '1' ), '0' ), 'Universe issuance failed.' );
	$world_instance = array_key_first( ps_pen_paper_gear_state( $alias_id )['active']['items'] );
	$check( true === ps_pen_paper_save_gear( $linked, $alias_id, 'equip_items', array( 'slots' => array( 'kopf' => $world_instance ) ), '1' ), 'Shared campaign loadout failed.' );
	$check( is_wp_error( ps_pen_paper_save_character_context( $linked_id, $alias_id, '1', '1', '1', array( $world_field => '9' ), '' ) ), 'Context edit bypassed equipment bounds.' );
	$check( 7 === ps_pen_paper_character_state( $linked, get_post( $alias_id ) )['active']['values'][ $world_field ], 'Shared equipment was not applied.' );
	ob_start(); ps_pen_paper_render_character_equipment( $linked, get_post( $alias_id ), 0 ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'data:image/png;base64,' ), 'Authorized universe item image unavailable in owned inventory.' );
	wp_set_current_user( $users['gm'] );
	$world_input['values'][ $world_field ] = '9';
	$check( $base_id === ps_pen_paper_save_character( $universe_id, $base_id, $users['player'], $world_input, '1', '1' ), 'Shared base progress failed.' );
	$check( is_wp_error( ps_pen_paper_character_state( $linked, get_post( $alias_id ) ) ), 'Invalid dependent equipment silently dropped or exceeded bounds.' );
	$check( true === ps_pen_paper_save_gear( $linked, $alias_id, 'equip_items', array(), '2' ) && 9 === ps_pen_paper_character_state( $linked, get_post( $alias_id ) )['active']['values'][ $world_field ], 'Shared-base progress stranded loadout repair.' );
	WP_CLI::success( 'Community gating, real profile API ownership, core portrait approvals/privacy, private images, slots/rarity, frozen issuance, forged/duplicate/type guards, effective bonuses, stale dice tokens, immutable roll provenance, bounds, recovery, journal rollback and image cleanup passed.' );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $parents as $parent ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_item', 'pspp_asset', 'pspp_roll', 'pspp_event' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $parent, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
		wp_delete_post( $parent, true );
	}
	if ( $path && is_file( $path ) ) { unlink( $path ); }
	foreach ( $users as $id ) { is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ); }
}
