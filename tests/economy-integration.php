<?php
/**
 * Run with WP-CLI --skip-plugins --skip-themes against a development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'character', 'event' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
$original_user = get_current_user_id();
$users = array();
$parents = array();
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
try {
	foreach ( array( 'world_gm', 'gm', 'player', 'other' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-economy-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( is_int( $id ), 'Fixture account failed.' ); $users[ $role ] = $id;
	}
	get_userdata( $users['world_gm'] )->add_cap( 'ps_pen_paper_create_campaigns' );
	wp_set_current_user( $users['world_gm'] );
	$universes = array();
	foreach ( array( 'campaign', 'universe' ) as $mode ) {
		$id = ps_pen_paper_save_universe( 0, array( 'name' => 'Economy ' . $mode, 'description' => '' ), '0' );
		$check( is_int( $id ), 'Universe fixture failed.' ); $parents[] = $id;
		$universes[ $mode ] = ps_pen_paper_get_universe( $id );
	}
	$input = array( 'name' => 'Credits', 'symbol' => 'CR', 'wallet_mode' => 'campaign' );
	$check( is_wp_error( ps_pen_paper_save_currency( $universes['campaign'], $input, '0' ) ), 'Currency mutation without Community accepted.' );
	define( 'CPC_CORE_PLUGINS', 'core-profile,core-activity' );
	require_once dirname( __DIR__, 2 ) . '/ps-community/activity/cpc_profile_tabs.php';
	$rules_input = array( 'name' => 'Economy rules', 'description' => '', 'dice' => array( '6' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Mut', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '10', 'start' => '2' ),
	) );
	$campaigns = array(); $characters = array(); $bases = array();
	foreach ( $universes as $mode => $universe ) {
		$rules = ps_pen_paper_save_rules( $universe->ID, $rules_input, '0' );
		$check( ! is_wp_error( $rules ), 'Universe rules failed.' );
		$input['wallet_mode'] = $mode;
		$check( true === ps_pen_paper_save_currency( $universe, $input, '0' ), 'Currency setup failed.' );
		$character_input = array( 'name' => 'PRIVATE_ACCOUNT_' . $mode, 'values' => array( $rules['fields'][0]['id'] => '2' ) );
		$bases[ $mode ] = ps_pen_paper_save_character( $universe->ID, 0, $users['player'], $character_input, '0', '1' );
		$check( is_int( $bases[ $mode ] ), 'Base character failed.' );
		foreach ( array( 0, 1 ) as $index ) {
			$id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Economy campaign' ), true );
			$check( is_int( $id ), 'Campaign fixture failed.' ); $parents[] = $id;
			add_post_meta( $id, '_pspp_gm', (string) $users['world_gm'] );
			foreach ( array( 'player', 'other' ) as $role ) { add_post_meta( $id, '_pspp_player', (string) $users[ $role ] ); }
			$campaigns[ $mode ][ $index ] = ps_pen_paper_get_campaign( $id );
			$check( ! is_wp_error( ps_pen_paper_save_universe_link( $id, $universe->ID, '0', '1', 'confirmed' ) ) && ! is_wp_error( ps_pen_paper_adopt_universe_rules( $id, '1', 'confirmed' ) ), 'Campaign link/rules failed.' );
			$characters[ $mode ][ $index ] = ps_pen_paper_attach_base_character( $id, $bases[ $mode ], '1', '1', 'shared', 'confirmed' );
			$check( is_int( $characters[ $mode ][ $index ] ), 'Shared character fixture failed.' );
		}
	}
	$campaign = $campaigns['campaign'][0]; $character_id = $characters['campaign'][0];
	$raw = get_post_meta( $character_id, '_pspp_character', true );
	wp_set_current_user( $users['gm'] );
	$check( is_wp_error( ps_pen_paper_save_currency( $campaign, array( 'name' => 'Foreign', 'symbol' => 'F', 'wallet_mode' => 'campaign' ), '0' ) ), 'Campaign GM replaced universe settings.' );
	$fail = function ( $data, $postarr ) {
		if ( isset( $postarr['ID'] ) && 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) {
			$event = get_post_meta( $postarr['ID'], '_pspp_event', true );
			if ( isset( $event['kind'] ) && 'wallet_saved' === $event['kind'] ) { $data['post_status'] = 'draft'; }
		}
		return $data;
	};
	add_filter( 'wp_insert_post_data', $fail, 10, 2 );
	try {
		$check( is_wp_error( ps_pen_paper_change_wallet( $campaign, $character_id, 'grant', '20', 'Failed grant', '0', '1' ) ), 'Unlogged grant succeeded.' );
		$check( '' === get_post_meta( $character_id, '_pspp_wallet', true ) && ! ps_pen_paper_currency_document( $universes['campaign']->ID )['in_use'] && 1 === ps_pen_paper_currency_document( $universes['campaign']->ID )['revision'], 'Failed first booking left a wallet or froze settings.' );
	} finally { remove_filter( 'wp_insert_post_data', $fail, 10 ); }
	$check( true === ps_pen_paper_change_wallet( $campaign, $character_id, 'grant', '20', 'Dock payment', '0', '1' ), 'GM grant failed.' );
	$check( is_wp_error( ps_pen_paper_change_wallet( $campaign, $character_id, 'grant', '20', 'Repeated POST', '0', '1' ) ), 'Repeated POST paid twice.' );
	$check( 20 === ps_pen_paper_wallet_context( $campaign, $character_id )['wallet']['balance'] && 0 === ps_pen_paper_wallet_context( $campaigns['campaign'][1], $characters['campaign'][1] )['wallet']['balance'], 'Campaign wallets were not isolated.' );
	$check( true === ps_pen_paper_change_wallet( $campaign, $character_id, 'debit', '7', 'Supplies', '1', '2' ), 'GM debit failed.' );
	foreach ( array( '1.5', '1,5', '-1', '0', '1000000000', '1e2' ) as $amount ) {
		$check( is_wp_error( ps_pen_paper_change_wallet( $campaign, $character_id, 'grant', $amount, 'Invalid amount', '2', '2' ) ), 'Invalid/non-integer amount accepted: ' . $amount );
	}
	$check( is_wp_error( ps_pen_paper_change_wallet( $campaign, $character_id, 'debit', '14', 'Overdraft', '2', '2' ) ) && is_wp_error( ps_pen_paper_change_wallet( $campaign, $character_id, 'grant', '999999999', 'Overflow', '2', '2' ) ) && 13 === ps_pen_paper_wallet_context( $campaign, $character_id )['wallet']['balance'], 'Balance bounds failed.' );
	$check( true === ps_pen_paper_change_wallet( $campaign, $character_id, 'grant', '999999986', 'Upper boundary', '2', '2' ) && 999999999 === ps_pen_paper_wallet_context( $campaign, $character_id )['wallet']['balance'], 'Exact maximum rejected.' );
	$check( true === ps_pen_paper_change_wallet( $campaign, $character_id, 'debit', '999999999', 'Exact zero', '3', '2' ) && 0 === ps_pen_paper_wallet_context( $campaign, $character_id )['wallet']['balance'], 'Exact zero rejected.' );
	wp_set_current_user( $users['world_gm'] );
	$check( is_wp_error( ps_pen_paper_save_currency( $universes['campaign'], array( 'name' => 'Credits', 'symbol' => 'CR', 'wallet_mode' => 'universe' ), '2' ) ) && is_wp_error( ps_pen_paper_save_universe_link( $campaign->ID, 0, '1', '0', 'confirmed' ) ), 'Used wallets were re-scoped.' );
	$check( true === ps_pen_paper_save_currency( $universes['campaign'], array( 'name' => 'Credits renamed', 'symbol' => 'NEW', 'wallet_mode' => 'campaign' ), '2' ) && 0 === ps_pen_paper_wallet_context( $campaign, $character_id )['wallet']['balance'], 'Label edit changed balance.' );
	wp_set_current_user( $users['player'] );
	$check( ! is_wp_error( ps_pen_paper_wallet_context( $campaign, $character_id ) ) && is_wp_error( ps_pen_paper_change_wallet( $campaign, $character_id, 'grant', '1', 'Forgery', '4', '3' ) ), 'Owner could not read or could mutate money.' );
	$log = ps_pen_paper_get_events( $campaign );
	$wallet_events = array_values( array_filter( $log['events'], function ( $event ) { return 'wallet_saved' === $event['kind']; } ) );
	$check( count( $wallet_events ) >= 4 && 'CR' === $wallet_events[0]['after']['symbol'], 'Owner history lost frozen currency labels.' );
	ob_start(); ps_pen_paper_render_wallet( $campaign, get_post( $character_id ), 27 ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'Guthaben' ) && false === strpos( $html, 'name="wallet_amount"' ), 'Owner balance UI missing or booking controls exposed.' );
	wp_set_current_user( $users['other'] );
	$check( is_wp_error( ps_pen_paper_wallet_context( $campaign, $character_id ) ), 'Other player read wallet.' );
	$other_log = ps_pen_paper_get_events( $campaign );
	$check( ! is_wp_error( $other_log ) && array() === array_filter( $other_log['events'], function ( $event ) { return in_array( $event['kind'], array( 'wallet_saved', 'currency_saved' ), true ); } ), 'Other player read private booking history.' );
	$shared = $campaigns['universe'][0]; $alias = $characters['universe'][0];
	wp_set_current_user( $users['world_gm'] );
	$shared_rules = ps_pen_paper_get_rules( $shared->ID );
	$local_id = ps_pen_paper_save_character( $shared->ID, 0, $users['other'], array( 'name' => 'Independent local hero', 'values' => array( $shared_rules['fields'][0]['id'] => '2' ) ), '0', '1' );
	$local_wallet = is_int( $local_id ) ? ps_pen_paper_wallet_context( $shared, $local_id ) : $local_id;
	$check( is_wp_error( $local_wallet ) && 'shared_wallet_required' === $local_wallet->get_error_code() && '' === get_post_meta( $local_id, '_pspp_wallet', true ), 'Shared policy silently opened a local replacement account.' );
	wp_set_current_user( $users['gm'] );
	$check( is_wp_error( ps_pen_paper_change_wallet( $shared, $alias, 'grant', '5', 'Unauthorized universe grant', '0', '1' ) ), 'Campaign GM mutated global money.' );
	wp_set_current_user( $users['world_gm'] );
	$check( true === ps_pen_paper_change_wallet( $shared, $alias, 'grant', '5', 'Global grant', '0', '1' ) && 5 === ps_pen_paper_wallet_context( $campaigns['universe'][1], $characters['universe'][1] )['wallet']['balance'] && '' === get_post_meta( $alias, '_pspp_wallet', true ), 'Shared wallet did not use the single base account.' );
	$nested = null;
	$race = function ( $value, $object_id, $key ) use ( &$nested, $campaigns, $characters ) {
		if ( $characters['universe'][0] !== (int) $object_id && '_pspp_wallet' === $key ) {
			$nested = ps_pen_paper_change_wallet( $campaigns['universe'][1], $characters['universe'][1], 'grant', '1', 'Concurrent alias', '1', '2' );
		}
		return $value;
	};
	add_filter( 'update_post_metadata', $race, 10, 3 );
	try { $check( true === ps_pen_paper_change_wallet( $shared, $alias, 'grant', '1', 'Outer grant', '1', '2' ), 'Outer shared booking failed.' ); }
	finally { remove_filter( 'update_post_metadata', $race, 10 ); }
	$check( is_wp_error( $nested ) && 'conflict' === $nested->get_error_code() && 6 === ps_pen_paper_wallet_context( $shared, $alias )['wallet']['balance'], 'Two aliases bypassed the base account lock.' );
	$check( $raw === get_post_meta( $character_id, '_pspp_character', true ), 'Money altered character values or approvals.' );
	$local_campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private',
		'post_title' => 'Local economy association fixture', 'post_author' => $users['world_gm'] ), true );
	$check( is_int( $local_campaign_id ), 'Local association fixture failed.' );
	$parents[] = $local_campaign_id;
	add_post_meta( $local_campaign_id, '_pspp_player', (string) $users['player'] );
	$local_campaign = ps_pen_paper_get_campaign( $local_campaign_id );
	$local_rules = ps_pen_paper_save_rules( $local_campaign_id, array( 'name' => 'Local rules', 'description' => '',
		'dice' => array( '6' ), 'fields' => array() ), '0' );
	$check( ! is_wp_error( $local_rules ), 'Local rules fixture failed.' );
	$local_character = ps_pen_paper_save_character( $local_campaign_id, 0, $users['player'],
		array( 'name' => 'Local account hero', 'values' => array() ), '0', '1' );
	$check( is_int( $local_character ), 'Local character fixture failed.' );
	$check( true === ps_pen_paper_save_currency( $local_campaign,
		array( 'name' => 'Local tokens', 'symbol' => 'LT', 'wallet_mode' => 'campaign' ), '0' ) &&
		true === ps_pen_paper_save_trade_policy( $local_campaign, array( 'kinds' => array( 'gift', 'sale' ), 'approval' => '1' ), '0' ) &&
		true === ps_pen_paper_change_wallet( $local_campaign, $local_character, 'grant', '17', 'Local fixture', '0', '1' ),
		'Local economic fixture failed.' );
	$local_wallet_before = get_post_meta( $local_character, '_pspp_wallet', true );
	$local_currency_before = ps_pen_paper_currency_document( $local_campaign_id );
	$local_policy_before = ps_pen_paper_trade_policy( $local_campaign )['policy'];
	$target_universe_id = $universes['campaign']->ID;
	$target_currency_before = ps_pen_paper_currency_document( $target_universe_id );
	$target_revision = (string) ps_pen_paper_universe_document( $target_universe_id )['revision'];
	$check( is_wp_error( ps_pen_paper_save_universe_link( $local_campaign_id, $target_universe_id, '0', $target_revision, 'confirmed' ) ),
		'Existing local money was silently reassigned to a universe currency.' );
	$local_link = ps_pen_paper_save_universe_link( $local_campaign_id, $target_universe_id, '0', $target_revision, 'confirmed', 'local' );
	$check( ! is_wp_error( $local_link ) && 'local' === $local_link['economy_mode'] &&
		$local_wallet_before === ps_pen_paper_wallet_context( $local_campaign, $local_character )['wallet'] &&
		$local_currency_before === ps_pen_paper_currency_document( $local_campaign_id ) &&
		$local_policy_before === ps_pen_paper_trade_policy( $local_campaign )['policy'] &&
		$target_currency_before === ps_pen_paper_currency_document( $target_universe_id ),
		'Explicit local association changed money, rules or the target universe.' );
	$check( is_wp_error( ps_pen_paper_save_universe_link( $local_campaign_id, $target_universe_id, '1', $target_revision, 'confirmed', 'universe' ) ) &&
		is_wp_error( ps_pen_paper_save_universe_link( $local_campaign_id, $target_universe_id, '1', $target_revision, 'confirmed', array( 'local' ) ) ) &&
		$local_link === ps_pen_paper_get_universe_link( $local_campaign_id ),
		'An unsafe or malformed economy-mode change was accepted.' );
	$check( true === ps_pen_paper_change_wallet( $local_campaign, $local_character, 'grant', '5', 'After association', '1', '2' ) &&
		22 === ps_pen_paper_wallet_context( $local_campaign, $local_character )['wallet']['balance'],
		'Associated local wallets were no longer usable.' );
	$fail_local_link = function ( $data ) {
		if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) { $data['post_status'] = 'draft'; }
		return $data;
	};
	add_filter( 'wp_insert_post_data', $fail_local_link );
	try {
		$check( is_wp_error( ps_pen_paper_save_universe_link( $local_campaign_id, $target_universe_id, '1', $target_revision, 'confirmed', 'local' ) ) &&
			$local_link === ps_pen_paper_get_universe_link( $local_campaign_id ), 'Failed local association left a partial change.' );
	} finally { remove_filter( 'wp_insert_post_data', $fail_local_link ); }
	$check( is_wp_error( ps_pen_paper_save_universe_link( $local_campaign_id, 0, '1', '0', 'confirmed', 'local' ) ) &&
		$local_link === ps_pen_paper_get_universe_link( $local_campaign_id ) &&
		22 === ps_pen_paper_wallet_context( $local_campaign, $local_character )['wallet']['balance'],
		'A locally funded campaign lost its required universe or its account.' );
	delete_post_meta( $campaign->ID, '_pspp_player', (string) $users['player'] );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_wallet_context( $campaign, $character_id ) ), 'Removed member retained wallet access.' );
	WP_CLI::success( 'Whole-unit bounds, explicit GM bookings, atomic first-use rollback, replay conflicts, private frozen history, separate/shared accounts, universe authority, shared-base locking and membership revocation passed.' );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $parents as $parent ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_event' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $parent, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
		wp_delete_post( $parent, true );
	}
	foreach ( $users as $id ) { is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ); }
}
