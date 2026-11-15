<?php
/** Run serially with WP-CLI --skip-plugins --skip-themes on the development site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once dirname( __DIR__ ) . '/includes/item-use.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( ! defined( 'CPC_CORE_PLUGINS' ) ) { define( 'CPC_CORE_PLUGINS', 'core-profile,core-activity' ); }
require_once dirname( __DIR__, 2 ) . '/ps-community/activity/cpc_profile_tabs.php';
foreach ( array( 'campaign', 'character', 'item', 'asset', 'event' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
$original_user = get_current_user_id(); $users = array(); $parents = array();
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
try {
	foreach ( array( 'gm', 'player' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-slots-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( is_int( $id ), 'User fixture failed.' ); $users[ $role ] = $id;
	}
	wp_set_current_user( $users['gm'] );
	$id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Slot fixture' ), true );
	$check( is_int( $id ), 'Campaign fixture failed.' ); $parents[] = $id;
	add_post_meta( $id, '_pspp_gm', (string) $users['gm'] ); add_post_meta( $id, '_pspp_player', (string) $users['player'] );
	$campaign = ps_pen_paper_get_campaign( $id );
	$rules = ps_pen_paper_save_rules( $id, array( 'name' => 'Slot rules', 'description' => '', 'dice' => array( '6' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Attribute', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '10', 'start' => '5' ),
	) ), '0' );
	$check( ! is_wp_error( $rules ), 'Rules fixture failed.' ); $field = $rules['fields'][0]['id'];
	$character_id = ps_pen_paper_save_character( $id, 0, $users['player'], array( 'name' => 'Slot hero', 'values' => array( $field => '5' ) ), '0', '1' );
	$check( is_int( $character_id ), 'Character fixture failed.' ); $character = get_post( $character_id );
	$defaults = ps_pen_paper_default_equipment_slots();
	$check( 6 === count( $defaults ) && 3 === count( array_filter( $defaults, function ( $slot ) { return 'left' === $slot['side']; } ) ), 'Defaults are not six / three per side.' );
	$schema = array( 'revision' => 1, 'slots' => array( 'kopf' => array( 'label' => 'Kopf', 'kind' => 'hat', 'hidden' => false ), 'hand' => array( 'label' => 'Hand', 'kind' => 'hat' ) ), 'rarities' => array( 'normal' => array( 'label' => 'Normal', 'color' => '#718096' ) ) );
	$check( ! is_wp_error( ps_pen_paper_validate_equipment_schema( $schema ) ), 'Legacy slots rejected.' );
	update_post_meta( $id, '_pspp_equipment_schema', $schema );
	$item = array( 'name' => 'Hat', 'description' => '', 'kind' => 'hat', 'rarity' => 'normal', 'rarity_label' => 'Normal', 'color' => '#718096', 'asset_id' => 0, 'properties' => array(), 'bonuses' => array( $field => 2 ) );
	$a = wp_generate_uuid4(); $b = wp_generate_uuid4();
	$instance = array( 'source_id' => 1, 'scope_id' => $id, 'revision' => 1, 'item' => $item );
	$gear = array( 'revision' => 1, 'active' => array( 'items' => array( $a => $instance, $b => $instance ), 'slots' => array( 'kopf' => $a ) ),
		'pending' => array( 'items' => array( $a => $instance, $b => $instance ), 'slots' => array( 'kopf' => $b ) ) );
	update_post_meta( $character_id, '_pspp_gear', $gear );
	$input = array( 'slot_rows' => array( 'kopf' => array( 'label' => 'Renamed', 'kind' => 'hat', 'side' => 'right', 'outside' => 'deny' ), 'hand' => array( 'label' => 'Hand', 'kind' => 'hat', 'side' => 'left' ) ),
		'rarities' => 'normal | Normal | #718096', 'slot_visibility_complete' => 'complete', 'hidden_slots' => array( 'kopf' => 'hidden' ) );
	$check( true === ps_pen_paper_save_equipment_schema( $campaign, $input, '1' ) && 7 === ps_pen_paper_character_state( $campaign, $character )['active']['values'][ $field ], 'Rename/hide/layout changed bonuses.' );
	$check( 'right' === ps_pen_paper_equipment_slot_side( ps_pen_paper_equipment_schema( $campaign ), 'kopf' ), 'Explicit side ignored.' );
	$check( true === ps_pen_paper_save_item_action_policy( $campaign, array( 'enabled' => 'yes', 'equip' => 'allow', 'use' => 'allow' ), '0' ), 'Profile slot policy fixture failed.' );
	$empty = $gear['active']; $empty['slots'] = array();
	$check( is_wp_error( ps_pen_paper_equipment_action_permission( $campaign, 'profile', $gear['active'], $empty ) ) &&
		'allow' === ps_pen_paper_equipment_action_permission( $campaign, 'game', $gear['active'], $empty ), 'Per-slot profile deny changed gameplay rights or allowed profile unequipping.' );
	$bad = $input; $bad['slot_rows']['kopf']['kind'] = 'armor';
	$check( is_wp_error( ps_pen_paper_save_equipment_schema( $campaign, $bad, '2' ) ), 'Stable kind silently reinterpreted.' );
	$bad = $input; $bad['slot_rows']['kopf']['side'] = 'center';
	$check( is_wp_error( ps_pen_paper_save_equipment_schema( $campaign, $bad, '2' ) ), 'Unknown layout side accepted.' );
	$native_png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
	$asset = wp_insert_post( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $id, 'post_mime_type' => 'image/png', 'post_content' => $native_png, 'post_title' => 'Slot image' ), true );
	$check( is_int( $asset ), 'Asset fixture failed.' );
	$input['slot_rows']['hand']['asset_id'] = (string) $asset;
	$check( true === ps_pen_paper_save_equipment_schema( $campaign, $input, '2' ) && 0 === strpos( ps_pen_paper_slot_image( $campaign, $asset ), 'data:image/png;base64,' ), 'Protected native slot image failed.' );
	$bad_asset = wp_insert_post( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $id, 'post_mime_type' => 'image/png', 'post_content' => base64_encode( 'not an image' ), 'post_title' => 'Bad image' ), true );
	$bad = $input; $bad['slot_rows']['hand']['asset_id'] = (string) $bad_asset;
	$check( is_wp_error( ps_pen_paper_save_equipment_schema( $campaign, $bad, '3' ) ), 'Non-native slot content accepted.' );
	$remove = $input; $remove['slot_rows']['kopf']['remove'] = 'remove';
	$check( is_wp_error( ps_pen_paper_save_equipment_schema( $campaign, $remove, '3' ) ), 'Removal needed no explicit slot confirmation.' );
	$remove['remove_slots'] = array( 'kopf' );
	$impact = ps_pen_paper_slot_removal_impact( $campaign, array( 'kopf' ) );
	$check( 2 === count( $impact ) && is_wp_error( ps_pen_paper_save_equipment_schema( $campaign, $remove, '3' ) ), 'Occupied active/pending slot did not require affected-item consent.' );
	$remove['affected_items'] = array_keys( $impact );
	$before_schema = ps_pen_paper_equipment_schema( $campaign );
	$failure = function ( $data, $postarr ) {
		$event = isset( $postarr['ID'] ) ? get_post_meta( $postarr['ID'], '_pspp_event', true ) : array();
		if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] && 'equipment_schema' === ( $event['kind'] ?? null ) ) { $data['post_status'] = 'draft'; }
		return $data;
	};
	add_filter( 'wp_insert_post_data', $failure, 10, 2 );
	try { $check( is_wp_error( ps_pen_paper_save_equipment_schema( $campaign, $remove, '3' ) ), 'Slot audit failure accepted.' ); } finally { remove_filter( 'wp_insert_post_data', $failure ); }
	$check( $gear === ps_pen_paper_gear_state( $character_id ) && $before_schema === ps_pen_paper_equipment_schema( $campaign ), 'Slot removal rollback was partial.' );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_save_equipment_schema( $campaign, $remove, '3' ) ), 'Player removed slot.' );
	wp_set_current_user( $users['gm'] );
	$changed = $gear; $changed['revision']++; update_post_meta( $character_id, '_pspp_gear', $changed );
	$check( is_wp_error( ps_pen_paper_save_equipment_schema( $campaign, $remove, '3' ) ), 'Stale affected-item confirmation accepted.' );
	$remove['affected_items'] = array_keys( ps_pen_paper_slot_removal_impact( $campaign, array( 'kopf' ) ) );
	$check( true === ps_pen_paper_save_equipment_schema( $campaign, $remove, '3' ), 'Confirmed slot removal failed.' );
	$next = ps_pen_paper_gear_state( $character_id );
	$check( $gear['active']['items'] === $next['active']['items'] && $gear['pending']['items'] === $next['pending']['items'] &&
		array() === $next['active']['slots'] && array() === $next['pending']['slots'] && 5 === ps_pen_paper_character_state( $campaign, $character )['active']['values'][ $field ], 'Removal lost inventory/pending request or left effective bonuses.' );
	$check( ! isset( ps_pen_paper_equipment_schema( $campaign )['slots']['kopf'] ) && ! is_wp_error( ps_pen_paper_get_events( $campaign ) ), 'Slot removal/journal invalid.' );
	$restore = array( 'slot_rows' => array( 'hand' => array( 'label' => 'Hand', 'kind' => 'hat', 'side' => 'left' ) ), 'new_slot' => array( 'id' => 'kopf', 'label' => 'Kopf', 'kind' => 'armor', 'side' => 'right' ), 'rarities' => 'normal | Normal | #718096' );
	$check( is_wp_error( ps_pen_paper_save_equipment_schema( $campaign, $restore, '4' ) ), 'Removed stable slot key silently changed meaning.' );
	$restore['new_slot']['kind'] = 'hat';
	$check( true === ps_pen_paper_save_equipment_schema( $campaign, $restore, '4' ) && 'right' === ps_pen_paper_equipment_slot_side( ps_pen_paper_equipment_schema( $campaign ), 'kopf' ), 'Structured slot addition/layout failed.' );
	WP_CLI::success( 'Slot layout, legacy schemas, native graphics, confirmation, concurrency and atomic safe removal passed.' );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $parents as $parent ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_item', 'pspp_asset', 'pspp_event' ), 'post_status' => 'any', 'post_parent' => $parent, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
		wp_delete_post( $parent, true );
	}
	foreach ( $users as $id ) { wp_delete_user( $id ); }
}
