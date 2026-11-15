<?php
/** Run with WP-CLI against a disposable development site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'character', 'event', 'item', 'trade' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
$original_user = get_current_user_id(); $users = array(); $parents = array();
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
try {
	foreach ( array( 'gm', 'a', 'b', 'other' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-trade-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( is_int( $id ), 'Fixture account failed.' ); $users[ $role ] = $id;
	}
	get_userdata( $users['gm'] )->add_cap( 'ps_pen_paper_create_campaigns' ); wp_set_current_user( $users['gm'] );
	$c = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Trade fixture' ), true );
	$check( is_int( $c ), 'Campaign failed.' ); $parents[] = $c; $campaign = ps_pen_paper_get_campaign( $c );
	foreach ( array( 'a', 'b', 'other' ) as $key ) { add_post_meta( $c, '_pspp_player', (string) $users[ $key ] ); }
	$check( is_wp_error( ps_pen_paper_offer_trade( $campaign, 1, 2, 'gift', wp_generate_uuid4(), '0', '0', '0', wp_generate_uuid4() ) ), 'Community gate bypassed.' );
	define( 'CPC_CORE_PLUGINS', 'core-profile,core-activity' ); require_once dirname( __DIR__, 2 ) . '/ps-community/activity/cpc_profile_tabs.php';
	$rules_input = array( 'name' => 'Trade rules', 'description' => '', 'dice' => array( '6' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Mut', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '10', 'start' => '2' ),
	) );
	$rules = ps_pen_paper_save_rules( $c, $rules_input, '0' ); $check( ! is_wp_error( $rules ), 'Rules failed.' ); $chars = array();
	foreach ( array( 'a', 'b' ) as $key ) {
		$chars[ $key ] = ps_pen_paper_save_character( $c, 0, $users[ $key ], array( 'name' => 'Trader ' . $key, 'values' => array( $rules['fields'][0]['id'] => '2' ) ), '0', '1' );
		$check( is_int( $chars[ $key ] ), 'Character failed.' );
	}
	$a = $chars['a']; $b = $chars['b'];
	$check( true === ps_pen_paper_save_currency( $campaign, array( 'name' => 'Marks', 'symbol' => 'M', 'wallet_mode' => 'campaign' ), '0' ), 'Currency failed.' );
	$check( true === ps_pen_paper_save_equipment_schema( $campaign, array( 'slots' => 'hand | Hand | tool', 'rarities' => 'normal | Normal | #abcdef' ), '0' ), 'Schema failed.' );
	$item_input = array( 'name' => 'Trade compass', 'description' => 'Only offered item.', 'kind' => 'tool', 'rarity' => 'normal', 'properties' => '', 'binding' => 'tradeable' );
	$item = ps_pen_paper_save_item( $campaign, 0, $item_input, '0' ); $check( is_int( $item ), 'Item failed.' );
	foreach ( array( $a, $b, $a ) as $id ) { $g = ps_pen_paper_gear_state( $id ); $check( true === ps_pen_paper_save_gear( $campaign, $id, 'grant_item', array( 'item_id' => (string) $item, 'item_revision' => '1' ), (string) $g['revision'] ), 'Grant failed.' ); }
	$give = array_keys( ps_pen_paper_gear_state( $a )['active']['items'] )[0];
	$other_item = array_keys( ps_pen_paper_gear_state( $a )['active']['items'] )[1];
	$take = array_keys( ps_pen_paper_gear_state( $b )['active']['items'] )[0];
	$check( true === ps_pen_paper_change_wallet( $campaign, $b, 'grant', '20', 'Fixture', '0', '1' ), 'Funds failed.' );
	$policy = array( 'kinds' => array( 'gift', 'sale', 'swap' ), 'approval' => '1' );
	wp_set_current_user( $users['a'] );
	$check( is_wp_error( ps_pen_paper_offer_trade( $campaign, $a, $b, 'gift', $give, '0', '2', '0', wp_generate_uuid4() ) ) &&
		is_wp_error( ps_pen_paper_save_trade_policy( $campaign, $policy, '0' ) ), 'Default-disabled/GM-only policy bypassed.' );
	wp_set_current_user( $users['gm'] ); $check( true === ps_pen_paper_save_trade_policy( $campaign, $policy, '0' ), 'Policy failed.' );
	wp_set_current_user( $users['a'] ); $token = wp_generate_uuid4();
	$trade = ps_pen_paper_offer_trade( $campaign, $a, $b, 'sale', $give, '5', '2', '1', $token ); $check( is_int( $trade ), 'Offer failed.' );
	$check( $trade === ps_pen_paper_offer_trade( $campaign, $a, $b, 'sale', $give, '5', '2', '1', $token ), 'Offer retry duplicated reservation.' );
	$check( is_wp_error( ps_pen_paper_offer_trade( $campaign, $a, $b, 'gift', $give, '0', '2', '1', wp_generate_uuid4() ) ), 'Reserved item offered twice.' );
	$check( is_wp_error( ps_pen_paper_save_gear( $campaign, $a, 'equip_items', array( 'slots' => array( 'hand' => $give ) ), '2' ) ), 'Reserved item equipped.' );
	$check( true === ps_pen_paper_save_gear( $campaign, $a, 'equip_items', array( 'slots' => array() ), '2' ) && null !== ps_pen_paper_gear_state( $a )['pending'], 'Sender pending inventory fixture failed.' );
	$check( is_wp_error( ps_pen_paper_change_trade( $campaign, $trade, 'accept', '1', 'confirmed' ) ), 'Sender accepted own sale.' );
	wp_set_current_user( $users['other'] ); $check( array() === ps_pen_paper_trade_records( $campaign ), 'Other member saw private offers.' );
	wp_set_current_user( $users['gm'] ); $check( is_wp_error( ps_pen_paper_change_trade( $campaign, $trade, 'approve', '1', 'confirmed' ) ), 'GM replaced buyer consent.' );
	wp_set_current_user( $users['b'] );
	$check( true === ps_pen_paper_save_gear( $campaign, $b, 'equip_items', array( 'slots' => array() ), '1' ), 'Recipient pending fixture failed.' );
	$check( true === ps_pen_paper_change_trade( $campaign, $trade, 'accept', '1', 'confirmed' ), 'Buyer acceptance failed.' );
	$check( isset( ps_pen_paper_gear_state( $a )['active']['items'][ $give ] ), 'Acceptance moved item before GM review.' );
	wp_set_current_user( $users['gm'] );
	$recipient_before = ps_pen_paper_gear_state( $b ); $full = $recipient_before;
	while ( count( $full['active']['items'] ) < 100 ) { $full['active']['items'][ wp_generate_uuid4() ] = $full['active']['items'][ $take ]; }
	$full['pending']['items'] = $full['active']['items']; update_post_meta( $b, '_pspp_gear', $full );
	$check( is_wp_error( ps_pen_paper_change_trade( $campaign, $trade, 'approve', '2', 'confirmed' ) ) && 20 === get_post_meta( $b, '_pspp_wallet', true )['balance'] &&
		'' === get_post_meta( $a, '_pspp_wallet', true ) && isset( ps_pen_paper_gear_state( $a )['active']['items'][ $give ] ), 'Full recipient inventory left money/item partially transferred.' );
	update_post_meta( $b, '_pspp_gear', $recipient_before );
	$check( true === ps_pen_paper_change_wallet( $campaign, $b, 'debit', '20', 'Funds spent while offer open', '1', '2' ), 'Spending funds failed.' );
	$check( is_wp_error( ps_pen_paper_change_trade( $campaign, $trade, 'approve', '2', 'confirmed' ) ) && isset( ps_pen_paper_gear_state( $a )['active']['items'][ $give ] ) &&
		'accepted' === get_post_meta( $trade, '_pspp_trade', true )['status'] && 0 === get_post_meta( $b, '_pspp_wallet', true )['balance'], 'Unfunded sale partially completed.' );
	$check( true === ps_pen_paper_change_wallet( $campaign, $b, 'grant', '20', 'Restore fixture funds', '2', '2' ), 'Restoring funds failed.' );
	$before = array( get_post_meta( $a, '_pspp_gear', true ), get_post_meta( $b, '_pspp_gear', true ), get_post_meta( $a, '_pspp_wallet', true ), get_post_meta( $b, '_pspp_wallet', true ), get_post_meta( $trade, '_pspp_trade', true ) );
	foreach ( array( 'wallet_saved', 'gear_saved', 'trade_changed' ) as $kind ) {
		$fail = function ( $data, $postarr ) use ( $kind ) {
			if ( isset( $postarr['ID'] ) && 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) {
				$event = get_post_meta( $postarr['ID'], '_pspp_event', true ); if ( isset( $event['kind'] ) && $kind === $event['kind'] ) { $data['post_status'] = 'draft'; }
			}
			return $data;
		};
		add_filter( 'wp_insert_post_data', $fail, 10, 2 );
		try {
			$check( is_wp_error( ps_pen_paper_change_trade( $campaign, $trade, 'approve', '2', 'confirmed' ) ), 'Unlogged trade succeeded.' );
			$check( $before === array( get_post_meta( $a, '_pspp_gear', true ), get_post_meta( $b, '_pspp_gear', true ), get_post_meta( $a, '_pspp_wallet', true ), get_post_meta( $b, '_pspp_wallet', true ), get_post_meta( $trade, '_pspp_trade', true ) ), 'Trade rollback left partial inventory/money/state.' );
		} finally { remove_filter( 'wp_insert_post_data', $fail, 10 ); }
	}
	$check( true === ps_pen_paper_change_trade( $campaign, $trade, 'approve', '2', 'confirmed' ), 'Sale completion failed.' );
	$check( ! isset( ps_pen_paper_gear_state( $a )['active']['items'][ $give ] ) && isset( ps_pen_paper_gear_state( $b )['active']['items'][ $give ] ) &&
		5 === get_post_meta( $a, '_pspp_wallet', true )['balance'] && 15 === get_post_meta( $b, '_pspp_wallet', true )['balance'], 'Sale did not preserve item identity or conserve money.' );
	$check( ! isset( ps_pen_paper_gear_state( $a )['pending']['items'][ $give ] ) && isset( ps_pen_paper_gear_state( $b )['pending']['items'][ $give ] ), 'Pending approval could resurrect/remove transferred item.' );
	$check( is_wp_error( ps_pen_paper_change_trade( $campaign, $trade, 'approve', '2', 'confirmed' ) ), 'Completed trade replayed.' );
	$policy['approval'] = '0'; $check( true === ps_pen_paper_save_trade_policy( $campaign, $policy, '1' ), 'Direct policy failed.' );
	wp_set_current_user( $users['a'] );
	$swap = ps_pen_paper_offer_trade( $campaign, $a, $b, 'swap', $other_item, '0', (string) ps_pen_paper_gear_state( $a )['revision'], '2', wp_generate_uuid4() ); $check( is_int( $swap ), 'Swap offer failed.' );
	wp_set_current_user( $users['b'] );
	$check( is_wp_error( ps_pen_paper_change_trade( $campaign, $swap, 'accept', '1', 'confirmed' ) ), 'Unknown swap consideration accepted.' );
	$check( true === ps_pen_paper_change_trade( $campaign, $swap, 'counter', '1', 'confirmed', $take ), 'Swap counterproposal failed.' );
	wp_set_current_user( $users['a'] ); $check( true === ps_pen_paper_change_trade( $campaign, $swap, 'accept', '2', 'confirmed' ), 'Exact swap acceptance failed.' );
	$check( isset( ps_pen_paper_gear_state( $a )['active']['items'][ $take ] ) && isset( ps_pen_paper_gear_state( $b )['active']['items'][ $other_item ] ), 'Swap did not preserve both UUIDs.' );
	$g = ps_pen_paper_gear_state( $a );
	$gift = ps_pen_paper_offer_trade( $campaign, $a, $b, 'gift', $take, '0', (string) $g['revision'], '2', wp_generate_uuid4() ); $check( is_int( $gift ), 'Gift offer failed.' );
	$check( true === ps_pen_paper_change_trade( $campaign, $gift, 'cancel', '1', 'confirmed' ) && array() === ps_pen_paper_reserved_trade_items( $campaign, $a ), 'Cancellation kept reservation.' );
	wp_set_current_user( $users['b'] );
	$gift = ps_pen_paper_offer_trade( $campaign, $b, $a, 'gift', $other_item, '0', (string) ps_pen_paper_gear_state( $b )['revision'], '2', wp_generate_uuid4() );
	wp_set_current_user( $users['a'] );
	$check( is_int( $gift ) && true === ps_pen_paper_change_trade( $campaign, $gift, 'accept', '1', 'confirmed' ) && isset( ps_pen_paper_gear_state( $a )['active']['items'][ $other_item ] ), 'Gift did not complete after exact recipient consent.' );
	wp_set_current_user( $users['gm'] );
	$item_input['binding'] = 'bound'; $bound = ps_pen_paper_save_item( $campaign, 0, $item_input, '0' );
	$check( true === ps_pen_paper_save_gear( $campaign, $a, 'grant_item', array( 'item_id' => (string) $bound, 'item_revision' => '1' ), (string) ps_pen_paper_gear_state( $a )['revision'] ), 'Bound grant failed.' );
	$bound_uuid = array_key_last( ps_pen_paper_gear_state( $a )['active']['items'] );
	wp_set_current_user( $users['a'] );
	$check( is_wp_error( ps_pen_paper_offer_trade( $campaign, $a, $b, 'gift', $bound_uuid, '0', (string) ps_pen_paper_gear_state( $a )['revision'], '2', wp_generate_uuid4() ) ), 'Bound item traded.' );
	$stale = ps_pen_paper_offer_trade( $campaign, $a, $b, 'gift', $take, '0', (string) ps_pen_paper_gear_state( $a )['revision'], '2', wp_generate_uuid4() );
	wp_set_current_user( $users['gm'] ); $policy['kinds'] = array(); $check( true === ps_pen_paper_save_trade_policy( $campaign, $policy, '2' ), 'Disable policy failed.' );
	wp_set_current_user( $users['b'] ); $check( is_wp_error( ps_pen_paper_change_trade( $campaign, $stale, 'accept', '1', 'confirmed' ) ), 'Old offer bypassed changed policy.' );
	$check( true === ps_pen_paper_change_trade( $campaign, $stale, 'decline', '1', 'confirmed' ), 'Disabled-policy offer could not be declined.' );
	wp_set_current_user( $users['gm'] );
	$log = ps_pen_paper_get_events( $campaign ); $check( ! is_wp_error( $log ), 'Trade journal unreadable.' );
	foreach ( $log['events'] as $event ) { if ( 'roll' !== $event['kind'] ) { ob_start(); ps_pen_paper_render_event_details( $event, $campaign ); ob_end_clean(); } }
	$u = ps_pen_paper_save_universe( 0, array( 'name' => 'Shared trade fixture', 'description' => '' ), '0' );
	$check( is_int( $u ), 'Shared universe failed.' ); $parents[] = $u; $universe = ps_pen_paper_get_universe( $u );
	$urules = ps_pen_paper_save_rules( $u, $rules_input, '0' ); $check( ! is_wp_error( $urules ), 'Universe rules failed.' );
	$check( true === ps_pen_paper_save_currency( $universe, array( 'name' => 'Shared marks', 'symbol' => 'SM', 'wallet_mode' => 'universe' ), '0' ), 'Shared currency failed.' );
	$policy['kinds'] = array( 'sale' ); $check( true === ps_pen_paper_save_trade_policy( $universe, $policy, '0' ), 'Shared policy failed.' );
	$sc = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Shared trade campaign' ), true );
	$check( is_int( $sc ), 'Shared campaign failed.' ); $parents[] = $sc; $shared = ps_pen_paper_get_campaign( $sc );
	foreach ( array( 'a', 'b' ) as $key ) { add_post_meta( $sc, '_pspp_player', (string) $users[ $key ] ); }
	$check( ! is_wp_error( ps_pen_paper_save_universe_link( $sc, $u, '0', '1', 'confirmed' ) ) && ! is_wp_error( ps_pen_paper_adopt_universe_rules( $sc, '1', 'confirmed' ) ), 'Shared rules admission failed.' );
	$bases = array(); $aliases = array();
	foreach ( array( 'a', 'b' ) as $key ) {
		$bases[ $key ] = ps_pen_paper_save_character( $u, 0, $users[ $key ], array( 'name' => 'Shared ' . $key, 'values' => array( $urules['fields'][0]['id'] => '2' ) ), '0', '1' );
		$aliases[ $key ] = is_int( $bases[ $key ] ) ? ps_pen_paper_attach_base_character( $sc, $bases[ $key ], '1', '1', 'shared', 'confirmed' ) : $bases[ $key ];
		$check( is_int( $aliases[ $key ] ), 'Shared character failed.' );
	}
	$check( is_wp_error( ps_pen_paper_save_trade_policy( $shared, $policy, '0' ) ), 'Campaign overrode universe trade policy.' );
	$check( true === ps_pen_paper_save_equipment_schema( $universe, array( 'slots' => 'hand | Hand | tool', 'rarities' => 'normal | Normal | #abcdef' ), '0' ), 'Shared equipment failed.' );
	$item_input['binding'] = 'tradeable'; $si = ps_pen_paper_save_item( $universe, 0, $item_input, '0' );
	$check( is_int( $si ) && true === ps_pen_paper_save_gear( $shared, $aliases['a'], 'grant_item', array( 'item_id' => (string) $si, 'item_revision' => '1' ), '0' ), 'Shared grant failed.' );
	$check( true === ps_pen_paper_change_wallet( $shared, $aliases['b'], 'grant', '10', 'Shared funds', '0', '1' ), 'Shared funds failed.' );
	$uuid = array_key_first( ps_pen_paper_gear_state( $aliases['a'] )['active']['items'] );
	wp_set_current_user( $users['a'] );
	$st = ps_pen_paper_offer_trade( $shared, $aliases['a'], $aliases['b'], 'sale', $uuid, '3', '1', '1', wp_generate_uuid4() ); $check( is_int( $st ), 'Shared offer failed.' );
	wp_set_current_user( $users['b'] );
	$locked = ps_pen_paper_with_character_lock( $bases['b'], function () use ( $shared, $st ) { return ps_pen_paper_change_trade( $shared, $st, 'accept', '1', 'confirmed' ); } );
	$check( is_wp_error( $locked ) && 'offered' === get_post_meta( $st, '_pspp_trade', true )['status'], 'Trade bypassed actual shared account lock.' );
	$check( true === ps_pen_paper_change_trade( $shared, $st, 'accept', '1', 'confirmed' ) && 7 === get_post_meta( $bases['b'], '_pspp_wallet', true )['balance'] &&
		3 === get_post_meta( $bases['a'], '_pspp_wallet', true )['balance'] && '' === get_post_meta( $aliases['b'], '_pspp_wallet', true ) &&
		'' === get_post_meta( $aliases['a'], '_pspp_wallet', true ), 'Shared sale used replacement accounts or lost currency.' );
	WP_CLI::success( 'Configurable disabled-by-default gift/sale/swap, owner-only exact consent, GM review, reservations/equipment guards, private offers, atomic journal rollback, conserved money, preserved UUIDs, replay refusal, bindings and changed-policy cancellation passed.' );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $parents as $parent ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_trade', 'pspp_character', 'pspp_item', 'pspp_event' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $parent, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $child ) { wp_delete_post( $child, true ); }
		wp_delete_post( $parent, true );
	}
	foreach ( $users as $user ) { is_multisite() ? wpmu_delete_user( $user ) : wp_delete_user( $user ); }
}
