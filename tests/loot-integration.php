<?php
/** Run with WP-CLI against a disposable development site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'character', 'npc', 'event', 'item' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
ps_pen_paper_register_loot_types();
$original_user = get_current_user_id(); $users = array(); $parents = array();
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
try {
	foreach ( array( 'gm', 'player', 'world' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-loot-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( is_int( $id ), 'Fixture account failed.' ); $users[ $role ] = $id;
	}
	$id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Loot fixture' ), true );
	$check( is_int( $id ), 'Campaign failed.' ); $parents[] = $id; $campaign = ps_pen_paper_get_campaign( $id );
	add_post_meta( $id, '_pspp_player', (string) $users['player'] );
	wp_set_current_user( $users['gm'] );
	$check( is_wp_error( ps_pen_paper_save_loot_table( $campaign, 0, array(), '0' ) ), 'Community gate bypassed.' );
	define( 'CPC_CORE_PLUGINS', 'core-profile,core-activity' );
	require_once dirname( __DIR__, 2 ) . '/ps-community/activity/cpc_profile_tabs.php';
	$rules_input = array( 'name' => 'Loot rules', 'description' => '', 'dice' => array( '6' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Mut', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '10', 'start' => '2' ),
	) );
	$rules = ps_pen_paper_save_rules( $campaign->ID, $rules_input, '0' ); $check( ! is_wp_error( $rules ), 'Rules failed.' );
	$character_input = array( 'name' => 'Loot recipient', 'values' => array( $rules['fields'][0]['id'] => '2' ) );
	$char = ps_pen_paper_save_character( $campaign->ID, 0, $users['player'], $character_input, '0', '1' ); $check( is_int( $char ), 'Character failed.' );
	$check( true === ps_pen_paper_save_currency( $campaign, array( 'name' => 'Marks', 'symbol' => 'M', 'wallet_mode' => 'campaign' ), '0' ), 'Currency failed.' );
	$check( true === ps_pen_paper_save_equipment_schema( $campaign, array( 'slots' => '', 'rarities' => 'normal | Normal | #abcdef' ), '0' ), 'Schema failed.' );
	$item_input = array( 'name' => 'Frozen compass', 'description' => '', 'kind' => 'tool', 'rarity' => 'normal', 'properties' => '', 'binding' => 'tradeable' );
	$item = ps_pen_paper_save_item( $campaign, 0, $item_input, '0' ); $check( is_int( $item ), 'Item failed.' );
	$row = array( 'from' => '1', 'to' => '6', 'label' => 'PRIVATE_LOOT_ROW', 'item_id' => (string) $item, 'quantity' => '2', 'money' => '2W6' );
	$input = array( 'name' => 'PRIVATE_LOOT_NAME', 'die' => '6', 'rows' => array( $row ) );
	foreach ( array(
		array_merge( $row, array( 'from' => '2' ) ), array_merge( $row, array( 'to' => '7' ) ),
		array_merge( $row, array( 'quantity' => '101' ) ), array_merge( $row, array( 'money' => '1.5' ) ),
		array_merge( $row, array( 'quantity' => 'W6-1' ) ), array_merge( $row, array( 'item_id' => '0' ) ),
	) as $bad ) { $bad_input = $input; $bad_input['rows'] = array( $bad ); $check( is_wp_error( ps_pen_paper_save_loot_table( $campaign, 0, $bad_input, '0' ) ), 'Bad range/amount/item accepted.' ); }
	$bad_input = $input; $bad_input['rows'][] = $row;
	$check( is_wp_error( ps_pen_paper_save_loot_table( $campaign, 0, $bad_input, '0' ) ), 'Overlapping ranges accepted.' );
	$table = ps_pen_paper_save_loot_table( $campaign, 0, $input, '0' ); $check( is_int( $table ), 'Table failed.' );
	$item_input['binding'] = 'bound'; $item_input['name'] = 'Changed compass';
	$check( $item === ps_pen_paper_save_item( $campaign, $item, $item_input, '1' ), 'Template edit failed.' );
	$token = wp_generate_uuid4();
	$run = ps_pen_paper_roll_loot( $campaign, $table, '1', '1', 'PRIVATE_REASON', $token ); $check( is_int( $run ), 'Roll failed.' );
	$proposal = ps_pen_paper_get_loot_run( $campaign, $run );
	$check( ps_pen_paper_valid_loot_run( $proposal ) && 2 === $proposal['proposal']['quantity_roll']['total'] && '' === get_post_meta( $char, '_pspp_wallet', true ) && '' === get_post_meta( $char, '_pspp_gear', true ), 'Proposal altered holdings or failed validation.' );
	$check( $run === ps_pen_paper_roll_loot( $campaign, $table, '1', '1', 'PRIVATE_REASON', $token ) && $proposal === ps_pen_paper_get_loot_run( $campaign, $run ), 'Repeated POST rerolled proposal.' );
	$empty = array( 'from' => '1', 'to' => '6', 'label' => 'Nothing', 'item_id' => '0', 'quantity' => '0', 'money' => '0' );
	$changed = $input; $changed['rows'] = array( $empty );
	$check( $table === ps_pen_paper_save_loot_table( $campaign, $table, $changed, '1' ) && $proposal === ps_pen_paper_get_loot_run( $campaign, $run ), 'Table edit rewrote existing proposal.' );
	$check( $run === ps_pen_paper_roll_loot( $campaign, $table, '1', '1', 'PRIVATE_REASON', $token ), 'Retry lost original result after table edit.' );
	$check( is_wp_error( ps_pen_paper_award_loot( $campaign, $run, $char, '0', '0', '1', '' ) ), 'Unconfirmed grant accepted.' );
	foreach ( array( 'gear_saved', 'loot_awarded' ) as $kind ) {
		$fail = function ( $data, $postarr ) use ( $kind ) {
			if ( isset( $postarr['ID'] ) && 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) {
				$event = get_post_meta( $postarr['ID'], '_pspp_event', true );
				if ( isset( $event['kind'] ) && $kind === $event['kind'] ) { $data['post_status'] = 'draft'; }
			}
			return $data;
		};
		add_filter( 'wp_insert_post_data', $fail, 10, 2 );
		try {
			$check( is_wp_error( ps_pen_paper_award_loot( $campaign, $run, $char, '0', '0', '1', 'confirmed' ) ), 'Unlogged reward succeeded.' );
			$check( '' === get_post_meta( $char, '_pspp_wallet', true ) && '' === get_post_meta( $char, '_pspp_gear', true ) && ! ps_pen_paper_currency_document( $campaign->ID )['in_use'] && $proposal === ps_pen_paper_get_loot_run( $campaign, $run ), 'Partial grant/freeze survived rollback.' );
		} finally { remove_filter( 'wp_insert_post_data', $fail, 10 ); }
	}
	$instance = $proposal['proposal']['table']['table']['rows'][0]['item'];
	$full = ps_pen_paper_append_item_instances( array( 'revision' => 0, 'active' => array( 'items' => array(), 'slots' => array() ), 'pending' => null ), $instance, 100 );
	update_post_meta( $char, '_pspp_gear', $full );
	$check( is_wp_error( ps_pen_paper_award_loot( $campaign, $run, $char, '1', '0', '1', 'confirmed' ) ) && '' === get_post_meta( $char, '_pspp_wallet', true ), 'Full inventory left money paid.' );
	delete_post_meta( $char, '_pspp_gear' );
	$check( is_wp_error( ps_pen_paper_with_character_lock( $char, function () use ( $campaign, $run, $char ) { return ps_pen_paper_award_loot( $campaign, $run, $char, '0', '0', '1', 'confirmed' ); } ) ), 'Concurrent character grant accepted.' );
	$check( true === ps_pen_paper_award_loot( $campaign, $run, $char, '0', '0', '1', 'confirmed' ), 'Combined award failed.' );
	$wallet = ps_pen_paper_wallet_context( $campaign, $char )['wallet']; $gear = ps_pen_paper_gear_state( $char );
	$check( $wallet['balance'] === $proposal['proposal']['money_roll']['total'] && 2 === count( $gear['active']['items'] ) && ! $gear['active']['slots'], 'Award did not match frozen totals.' );
	foreach ( $gear['active']['items'] as $entry ) { $check( 'tradeable' === $entry['item']['binding'] && 'Frozen compass' === $entry['item']['name'], 'Award followed edited item instead of frozen table snapshot.' ); }
	$check( is_wp_error( ps_pen_paper_award_loot( $campaign, $run, $char, '1', '1', '2', 'confirmed' ) ) && $wallet === ps_pen_paper_wallet_context( $campaign, $char )['wallet'] && $gear === ps_pen_paper_gear_state( $char ), 'Repeated award duplicated rewards.' );
	$none = ps_pen_paper_roll_loot( $campaign, $table, '2', '1', 'No loot', wp_generate_uuid4() );
	$check( is_int( $none ) && true === ps_pen_paper_award_loot( $campaign, $none, 0, '0', '0', '0', 'confirmed' ), 'No-loot completion failed.' );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_get_loot_run( $campaign, $run ) ) && is_wp_error( ps_pen_paper_get_loot_table( $campaign, $table ) ) && is_wp_error( ps_pen_paper_award_loot( $campaign, $run, $char, '1', '1', '2', 'confirmed' ) ), 'Player accessed private loot.' );
	ob_start(); ps_pen_paper_render_loot( $campaign, 27 ); $html = ob_get_clean(); $check( '' === $html, 'Player UI leaked loot tables.' );
	$visible = wp_json_encode( ps_pen_paper_get_events( $campaign ) );
	$check( false === strpos( $visible, 'PRIVATE_REASON' ) && false === strpos( $visible, 'PRIVATE_LOOT_NAME' ) && false !== strpos( $visible, 'Frozen compass' ), 'Owner journal leaked tables or hid received inventory.' );
	get_userdata( $users['world'] )->add_cap( 'ps_pen_paper_create_campaigns' );
	wp_set_current_user( $users['world'] );
	$u = ps_pen_paper_save_universe( 0, array( 'name' => 'Shared loot', 'description' => '' ), '0' ); $check( is_int( $u ), 'Universe failed.' ); $parents[] = $u; $universe = ps_pen_paper_get_universe( $u );
	$urules = ps_pen_paper_save_rules( $u, $rules_input, '0' );
	$check( ! is_wp_error( $urules ) && true === ps_pen_paper_save_currency( $universe, array( 'name' => 'Shared marks', 'symbol' => 'SM', 'wallet_mode' => 'universe' ), '0' ), 'Shared rules/currency failed.' );
	$base = ps_pen_paper_save_character( $u, 0, $users['player'], array( 'name' => 'Shared recipient', 'values' => array( $urules['fields'][0]['id'] => '2' ) ), '0', '1' );
	$c = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Shared loot fixture' ), true ); $check( is_int( $c ), 'Shared campaign failed.' ); $parents[] = $c;
	add_post_meta( $c, '_pspp_gm', (string) $users['world'] ); add_post_meta( $c, '_pspp_player', (string) $users['player'] );
	$shared = ps_pen_paper_get_campaign( $c );
	$check( ! is_wp_error( ps_pen_paper_save_universe_link( $c, $u, '0', '1', 'confirmed' ) ) && ! is_wp_error( ps_pen_paper_adopt_universe_rules( $c, '1', 'confirmed' ) ), 'Shared link failed.' );
	$alias = ps_pen_paper_attach_base_character( $c, $base, '1', '1', 'shared', 'confirmed' ); $check( is_int( $alias ), 'Alias failed.' );
	$money_input = $changed; $money_input['rows'][0]['money'] = '2';
	$st = ps_pen_paper_save_loot_table( $shared, 0, $money_input, '0' ); $check( is_int( $st ), 'Shared table failed.' );
	wp_set_current_user( $users['gm'] );
	$sr = ps_pen_paper_roll_loot( $shared, $st, '1', '1', 'Shared reward', wp_generate_uuid4() );
	$check( is_int( $sr ) && is_wp_error( ps_pen_paper_award_loot( $shared, $sr, $alias, '0', '0', '1', 'confirmed' ) ) && '' === get_post_meta( $base, '_pspp_wallet', true ), 'Local GM bypassed shared-account authority.' );
	wp_set_current_user( $users['world'] );
	$check( true === ps_pen_paper_award_loot( $shared, $sr, $alias, '0', '0', '1', 'confirmed' ) && 2 === get_post_meta( $base, '_pspp_wallet', true )['balance'] && '' === get_post_meta( $alias, '_pspp_wallet', true ), 'Shared award did not reach the base wallet.' );
	WP_CLI::success( 'Complete ranges, dice bounds, frozen rolls/items, replay protection, confirmed exactly-once grants, full-inventory and event rollback, private history and shared-account authority passed.' );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $parents as $parent ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_loot_table', 'pspp_loot_run', 'pspp_character', 'pspp_npc', 'pspp_item', 'pspp_event' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $parent, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $child ) { wp_delete_post( $child, true ); }
		wp_delete_post( $parent, true );
	}
	foreach ( $users as $user ) { is_multisite() ? wpmu_delete_user( $user ) : wp_delete_user( $user ); }
}
