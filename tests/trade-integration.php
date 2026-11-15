<?php
/** Run with WP-CLI against a disposable development site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'character', 'event', 'item', 'trade' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
ps_pen_paper_register_asset_type();
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
	$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_author' => $users['gm'],
		'post_title' => 'Trade frontend fixture', 'post_content' => '[ps_pen_paper]' ), true );
	$check( is_int( $page_id ), 'Trade frontend page failed.' ); $parents[] = $page_id;
	$submit_frontend = static function ( $fields ) use ( $page_id, $check ) {
		$scope = (int) $fields['campaign_id'];
		$fields = array_merge( array( 'action' => 'ps_pen_paper', 'pspp_frontend_page' => (string) $page_id,
			'pspp_scope_epoch' => ps_pen_paper_scope_epoch( $scope ), '_wpnonce' => wp_create_nonce( 'pspp_campaign_' . $scope ) ), $fields );
		if ( '1' === getenv( 'PSPP_TEST_HTTP' ) ) {
			$actor = get_current_user_id(); $expires = time() + 300;
			$session = WP_Session_Tokens::get_instance( $actor )->create( $expires );
			$old_cookie = $_COOKIE[LOGGED_IN_COOKIE] ?? null;
			$_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $actor, $expires, 'logged_in', $session );
			$fields['_wpnonce'] = wp_create_nonce( 'pspp_campaign_' . $scope );
			$cookie = LOGGED_IN_COOKIE . '=' . $_COOKIE[LOGGED_IN_COOKIE] . '; ' . AUTH_COOKIE . '=' .
				wp_generate_auth_cookie( $actor, $expires, 'auth', $session ) . '; ' .
				( is_multisite() ? 'wdeb_on_site_' . get_current_blog_id() : 'wdeb_on' ) . '=1';
			try {
				$response = wp_remote_post( get_permalink( $page_id ), array( 'timeout' => 30, 'redirection' => 0,
					'headers' => array( 'Cookie' => $cookie ), 'body' => $fields ) );
				$check( ! is_wp_error( $response ) && 302 === wp_remote_retrieve_response_code( $response ), 'Economic HTTP form failed.' );
			} finally {
				WP_Session_Tokens::get_instance( $actor )->destroy( $session );
				if ( null === $old_cookie ) { unset( $_COOKIE[LOGGED_IN_COOKIE] ); } else { $_COOKIE[LOGGED_IN_COOKIE] = $old_cookie; }
			}
			foreach ( array( 'campaign_id', 'character_id', 'trade_target', 'trade_id' ) as $key ) {
				if ( isset( $fields[ $key ] ) ) { clean_post_cache( (int) $fields[ $key ] ); }
			}
			return;
		}
		global $wp_query;
		$old_query = $wp_query; $old_post = $_POST; $old_request = $_REQUEST;
		$old_method = $_SERVER['REQUEST_METHOD'] ?? null; $redirected = false;
		$redirect = static function ( $url ) { throw new RuntimeException( 'trade-form-redirect:' . $url ); };
		$die_handler = static function () {
			return static function ( $message ) { throw new RuntimeException( 'trade-form-failure:' . $message ); };
		};
		add_filter( 'wp_redirect', $redirect, 1 ); add_filter( 'wp_die_handler', $die_handler, 1000 );
		try {
			$_POST = $fields; $_REQUEST = $fields; $_SERVER['REQUEST_METHOD'] = 'POST';
			$wp_query = new WP_Query( array( 'page_id' => $page_id ) );
			try { ps_pen_paper_handle_frontend_action(); }
			catch ( RuntimeException $error ) {
				if ( 0 !== strpos( $error->getMessage(), 'trade-form-redirect:' ) ) { throw $error; }
				$redirected = true;
			}
		} finally {
			remove_filter( 'wp_redirect', $redirect, 1 ); remove_filter( 'wp_die_handler', $die_handler, 1000 );
			$wp_query = $old_query; $_POST = $old_post; $_REQUEST = $old_request;
			if ( null === $old_method ) { unset( $_SERVER['REQUEST_METHOD'] ); } else { $_SERVER['REQUEST_METHOD'] = $old_method; }
		}
		$check( $redirected, 'Economic frontend action did not redirect after saving.' );
	};
	$policy = array( 'kinds' => array( 'gift', 'sale', 'swap', 'money_gift' ), 'approval' => '1' );
	$check( true === ps_pen_paper_save_trade_policy( $campaign, $policy, '3' ), 'Expanded trade policy failed.' );
	wp_set_current_user( $users['a'] );
	$selection = array( $take, $other_item ); $gear_revision = (string) ps_pen_paper_gear_state( $a )['revision'];
	$check( is_wp_error( ps_pen_paper_offer_trade( $campaign, $a, $b, 'sale', array( $take, $take ), '4', $gear_revision, '4', wp_generate_uuid4() ) ),
		'Duplicate quantity selection accepted.' );
	ob_start(); ps_pen_paper_render_character_trade( $campaign, get_post( $a ), $page_id ); $trade_html = ob_get_clean();
	$check( 2 === substr_count( $trade_html, 'name="trade_items[]"' ), 'Trade form did not offer both available concrete instances.' );
	$batch_token = wp_generate_uuid4();
	$submit_frontend( array( 'pspp_operation' => 'offer_trade', 'campaign_id' => (string) $c, 'character_id' => (string) $a,
		'trade_complete' => 'complete', 'trade_confirmation' => 'confirmed', 'trade_target' => (string) $b,
		'trade_kind' => 'sale', 'trade_items' => $selection, 'trade_amount' => '4', 'trade_gear_revision' => $gear_revision,
		'trade_policy_revision' => '4', 'trade_token' => $batch_token ) );
	$batch = null;
	foreach ( ps_pen_paper_trade_records( $campaign ) as $id => $state ) { if ( $batch_token === $state['offer']['token'] ) { $batch = $id; } }
	$check( is_int( $batch ) && $selection === ps_pen_paper_reserved_trade_items( $campaign, $a ), 'Batch did not reserve every selected instance.' );
	wp_set_current_user( $users['gm'] );
	$remove = array( 'ids' => $selection, 'confirmation' => 'confirmed' );
	$check( is_wp_error( ps_pen_paper_save_gear( $campaign, $a, 'remove_items', $remove, $gear_revision ) ), 'Reserved batch items were removed.' );
	wp_set_current_user( $users['b'] );
	$check( true === ps_pen_paper_change_trade( $campaign, $batch, 'accept', '1', 'confirmed' ), 'Batch recipient consent failed.' );
	wp_set_current_user( $users['gm'] );
	$recipient_before = ps_pen_paper_gear_state( $b ); $full = $recipient_before;
	while ( count( $full['active']['items'] ) < 99 ) { $full['active']['items'][ wp_generate_uuid4() ] = $full['active']['items'][ $give ]; }
	$full['pending']['items'] = $full['active']['items']; update_post_meta( $b, '_pspp_gear', $full );
	$check( is_wp_error( ps_pen_paper_change_trade( $campaign, $batch, 'approve', '2', 'confirmed' ) ) &&
		5 === get_post_meta( $a, '_pspp_wallet', true )['balance'] && 15 === get_post_meta( $b, '_pspp_wallet', true )['balance'] &&
		$full === ps_pen_paper_gear_state( $b ), 'Batch exceeding capacity left a partial transfer.' );
	update_post_meta( $b, '_pspp_gear', $recipient_before );
	$before = array( ps_pen_paper_gear_state( $a ), ps_pen_paper_gear_state( $b ), get_post_meta( $a, '_pspp_wallet', true ),
		get_post_meta( $b, '_pspp_wallet', true ), get_post_meta( $batch, '_pspp_trade', true ) );
	$fail = static function ( $check, $object, $key, $value ) {
		return '_pspp_event' === $key && 'trade_changed' === ( $value['kind'] ?? '' ) ? false : $check;
	};
	add_filter( 'update_post_metadata', $fail, 10, 4 );
	try {
		$check( is_wp_error( ps_pen_paper_change_trade( $campaign, $batch, 'approve', '2', 'confirmed' ) ) &&
			$before === array( ps_pen_paper_gear_state( $a ), ps_pen_paper_gear_state( $b ), get_post_meta( $a, '_pspp_wallet', true ),
				get_post_meta( $b, '_pspp_wallet', true ), get_post_meta( $batch, '_pspp_trade', true ) ), 'Batch failure left partial items or payment.' );
	} finally { remove_filter( 'update_post_metadata', $fail, 10 ); }
	$check( true === ps_pen_paper_change_trade( $campaign, $batch, 'approve', '2', 'confirmed' ) &&
		! array_intersect( $selection, array_keys( ps_pen_paper_gear_state( $a )['active']['items'] ) ) &&
		2 === count( array_intersect( $selection, array_keys( ps_pen_paper_gear_state( $b )['active']['items'] ) ) ) &&
		9 === get_post_meta( $a, '_pspp_wallet', true )['balance'] && 11 === get_post_meta( $b, '_pspp_wallet', true )['balance'],
		'Batch sale lost UUIDs or charged the total price more than once.' );
	wp_set_current_user( $users['a'] );
	$gear_revision = (string) ps_pen_paper_gear_state( $a )['revision'];
	$check( is_wp_error( ps_pen_paper_offer_trade( $campaign, $a, $b, 'money_gift', array( $bound_uuid ), '4', $gear_revision, '4', wp_generate_uuid4() ) ) &&
		is_wp_error( ps_pen_paper_offer_trade( $campaign, $a, $b, 'money_gift', '', '0', $gear_revision, '4', wp_generate_uuid4() ) ), 'Invalid money gift accepted.' );
	$money_token = wp_generate_uuid4();
	$submit_frontend( array( 'pspp_operation' => 'offer_trade', 'campaign_id' => (string) $c, 'character_id' => (string) $a,
		'trade_complete' => 'complete', 'trade_confirmation' => 'confirmed', 'trade_target' => (string) $b,
		'trade_kind' => 'money_gift', 'trade_amount' => '4', 'trade_gear_revision' => $gear_revision,
		'trade_policy_revision' => '4', 'trade_token' => $money_token ) );
	$money = null;
	foreach ( ps_pen_paper_trade_records( $campaign ) as $id => $state ) { if ( $money_token === $state['offer']['token'] ) { $money = $id; } }
	$check( is_int( $money ) && array() === ps_pen_paper_reserved_trade_items( $campaign, $a ), 'Money gift required or reserved an item.' );
	$money_gear = array( ps_pen_paper_gear_state( $a ), ps_pen_paper_gear_state( $b ) );
	wp_set_current_user( $users['b'] );
	$check( true === ps_pen_paper_change_trade( $campaign, $money, 'accept', '1', 'confirmed' ), 'Money gift consent failed.' );
	wp_set_current_user( $users['gm'] );
	add_filter( 'update_post_metadata', $fail, 10, 4 );
	try {
		$check( is_wp_error( ps_pen_paper_change_trade( $campaign, $money, 'approve', '2', 'confirmed' ) ) &&
			9 === get_post_meta( $a, '_pspp_wallet', true )['balance'] && 11 === get_post_meta( $b, '_pspp_wallet', true )['balance'],
			'Failed gift changed either balance.' );
	} finally { remove_filter( 'update_post_metadata', $fail, 10 ); }
	$check( true === ps_pen_paper_change_trade( $campaign, $money, 'approve', '2', 'confirmed' ) &&
		5 === get_post_meta( $a, '_pspp_wallet', true )['balance'] && 15 === get_post_meta( $b, '_pspp_wallet', true )['balance'] &&
		$money_gear === array( ps_pen_paper_gear_state( $a ), ps_pen_paper_gear_state( $b ) ) &&
		is_wp_error( ps_pen_paper_change_trade( $campaign, $money, 'approve', '2', 'confirmed' ) ), 'Money gift changed inventory, paid in the wrong direction or replayed.' );
	wp_set_current_user( $users['a'] );
	$unfunded = ps_pen_paper_offer_trade( $campaign, $a, $b, 'money_gift', '', '6', $gear_revision, '4', wp_generate_uuid4() );
	$check( is_int( $unfunded ), 'Unfunded gift fixture failed.' );
	wp_set_current_user( $users['b'] );
	$check( true === ps_pen_paper_change_trade( $campaign, $unfunded, 'accept', '1', 'confirmed' ), 'Unfunded gift consent failed.' );
	wp_set_current_user( $users['gm'] );
	$check( is_wp_error( ps_pen_paper_change_trade( $campaign, $unfunded, 'approve', '2', 'confirmed' ) ) &&
		5 === get_post_meta( $a, '_pspp_wallet', true )['balance'] && 15 === get_post_meta( $b, '_pspp_wallet', true )['balance'] &&
		true === ps_pen_paper_change_trade( $campaign, $unfunded, 'reject', '2', 'confirmed' ), 'Unfunded gift paid or could not be rejected.' );
	$check( true === ps_pen_paper_save_gear( $campaign, $a, 'equip_items', array( 'slots' => array( 'hand' => $bound_uuid ) ),
		(string) ps_pen_paper_gear_state( $a )['revision'] ), 'Removal equipment fixture failed.' );
	wp_set_current_user( $users['a'] );
	$check( true === ps_pen_paper_save_gear( $campaign, $a, 'equip_items', array( 'slots' => array( 'hand' => $bound_uuid ) ),
		(string) ps_pen_paper_gear_state( $a )['revision'] ), 'Removal pending fixture failed.' );
	$remove = array( 'ids' => array( $bound_uuid ), 'confirmation' => 'confirmed' );
	$gear_before = ps_pen_paper_gear_state( $a );
	$check( is_wp_error( ps_pen_paper_save_gear( $campaign, $a, 'remove_items', $remove, (string) $gear_before['revision'] ) ), 'Player removed an item without GM authority.' );
	wp_set_current_user( $users['gm'] );
	$check( is_wp_error( ps_pen_paper_save_gear( $campaign, $a, 'remove_items', array( 'ids' => array( $bound_uuid ) ), (string) $gear_before['revision'] ) ),
		'Unconfirmed removal succeeded.' );
	$fail_remove = static function ( $check, $object, $key, $value ) {
		return '_pspp_event' === $key && 'gear_saved' === ( $value['kind'] ?? '' ) ? false : $check;
	};
	add_filter( 'update_post_metadata', $fail_remove, 10, 4 );
	try {
		$check( is_wp_error( ps_pen_paper_save_gear( $campaign, $a, 'remove_items', $remove, (string) $gear_before['revision'] ) ) &&
			$gear_before === ps_pen_paper_gear_state( $a ), 'Failed removal left a changed inventory or pending equipment.' );
	} finally { remove_filter( 'update_post_metadata', $fail_remove, 10 ); }
	$submit_frontend( array( 'pspp_operation' => 'remove_items', 'campaign_id' => (string) $c, 'character_id' => (string) $a,
		'equipment_complete' => 'complete', 'equipment_revision' => (string) $gear_before['revision'], 'equipment' => $remove ) );
	$check( array() === ps_pen_paper_gear_state( $a )['active']['items'] && array() === ps_pen_paper_gear_state( $a )['active']['slots'] &&
		array() === ps_pen_paper_gear_state( $a )['pending']['items'] && array() === ps_pen_paper_gear_state( $a )['pending']['slots'],
		'Removal left equipped bonuses or an item that could return through approval.' );
	$check( true === ps_pen_paper_save_gear( $campaign, $a, 'review_gear', array( 'decision' => 'approve' ),
		(string) ps_pen_paper_gear_state( $a )['revision'] ) && array() === ps_pen_paper_gear_state( $a )['active']['items'] &&
		is_wp_error( ps_pen_paper_save_gear( $campaign, $a, 'remove_items', $remove, (string) $gear_before['revision'] ) ),
		'Approval or stale removal restored a withdrawn item.' );
	foreach ( array( $a, $a ) as $id ) {
		$check( true === ps_pen_paper_save_gear( $campaign, $id, 'grant_item', array( 'item_id' => (string) $item, 'item_revision' => '1' ),
			(string) ps_pen_paper_gear_state( $id )['revision'] ), 'Batch counteroffer fixture failed.' );
	}
	$counter_items = array_keys( ps_pen_paper_gear_state( $a )['active']['items'] );
	wp_set_current_user( $users['b'] );
	$batch_swap = ps_pen_paper_offer_trade( $campaign, $b, $a, 'swap', $selection, '0',
		(string) ps_pen_paper_gear_state( $b )['revision'], '4', wp_generate_uuid4() );
	$check( is_int( $batch_swap ), 'Batch swap offer failed.' );
	wp_set_current_user( $users['a'] );
	$submit_frontend( array( 'pspp_operation' => 'change_trade', 'campaign_id' => (string) $c, 'character_id' => (string) $a,
		'trade_complete' => 'complete', 'trade_confirmation' => 'confirmed', 'trade_id' => (string) $batch_swap,
		'trade_action' => 'counter', 'trade_revision' => '1', 'trade_items' => $counter_items ) );
	$check( $counter_items === ps_pen_paper_reserved_trade_items( $campaign, $a ), 'Batch counteroffer did not reserve every instance.' );
	wp_set_current_user( $users['b'] );
	$check( true === ps_pen_paper_change_trade( $campaign, $batch_swap, 'accept', '2', 'confirmed' ), 'Batch counteroffer consent failed.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_change_trade( $campaign, $batch_swap, 'approve', '3', 'confirmed' ) &&
		$selection === array_keys( ps_pen_paper_gear_state( $a )['active']['items'] ) &&
		2 === count( array_intersect( $counter_items, array_keys( ps_pen_paper_gear_state( $b )['active']['items'] ) ) ),
		'Batch swap lost an offered or counteroffered instance.' );
	$archive = ps_pen_paper_export_campaign( $c, 'local' );
	$check( ! is_wp_error( $archive ), 'Expanded trade export failed.' );
	$parsed = ps_pen_paper_parse_import( wp_json_encode( $archive, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	$check( ! is_wp_error( $parsed ), 'Expanded trade export did not parse.' );
	$options = array( 'operation' => 'copy', 'target_id' => 0, 'universe_mode' => 'none', 'universe_id' => 0, 'users' => array(), 'bases' => array() );
	foreach ( $parsed['users'] as $owner => $requirement ) { $options['users'][ $owner ] = $owner; }
	$map = array(); foreach ( array_keys( $parsed['records'] ) as $source_id ) { $map[ $source_id ] = $source_id + 1000000; }
	$prepared = ps_pen_paper_prepare_import( $parsed, $options, $map, false );
	$check( ! is_wp_error( $prepared ), 'Expanded trade preparation failed: ' . ( is_wp_error( $prepared ) ? $prepared->get_error_message() : '' ) );
	foreach ( array( $batch, $money, $batch_swap ) as $source_id ) {
		$found = false;
		foreach ( $prepared[ $source_id ]['meta'] as $entry ) {
			if ( '_pspp_trade' !== $entry['key'] ) { continue; }
			$found = true;
			$check( ps_pen_paper_valid_trade( $entry['value'] ), 'Expanded restored trade is invalid.' );
			foreach ( array( 'give', 'take' ) as $side ) {
				foreach ( ps_pen_paper_trade_items( $entry['value']['offer'], $side ) as $instance ) {
					$check( $map[ $c ] === $instance['scope_id'], 'Restored batch item retained the original scope.' );
				}
			}
		}
		$check( $found, 'Expanded restored trade was not preserved.' );
	}
	$u = ps_pen_paper_save_universe( 0, array( 'name' => 'Shared trade fixture', 'description' => '' ), '0' );
	$check( is_int( $u ), 'Shared universe failed.' ); $parents[] = $u; $universe = ps_pen_paper_get_universe( $u );
	$urules = ps_pen_paper_save_rules( $u, $rules_input, '0' ); $check( ! is_wp_error( $urules ), 'Universe rules failed.' );
	$check( true === ps_pen_paper_save_currency( $universe, array( 'name' => 'Shared marks', 'symbol' => 'SM', 'wallet_mode' => 'universe' ), '0' ), 'Shared currency failed.' );
	$policy = array( 'kinds' => array( 'sale', 'money_gift' ), 'approval' => '0' );
	$check( true === ps_pen_paper_save_trade_policy( $universe, $policy, '0' ), 'Shared policy failed.' );
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
	$shared_gear = array( ps_pen_paper_gear_state( $aliases['a'] ), ps_pen_paper_gear_state( $aliases['b'] ) );
	wp_set_current_user( $users['a'] );
	$shared_gift = ps_pen_paper_offer_trade( $shared, $aliases['a'], $aliases['b'], 'money_gift', '', '2',
		(string) $shared_gear[0]['revision'], '1', wp_generate_uuid4() );
	$check( is_int( $shared_gift ), 'Shared money gift failed.' );
	wp_set_current_user( $users['b'] );
	$locked = ps_pen_paper_with_character_lock( $bases['a'], function () use ( $shared, $shared_gift ) {
		return ps_pen_paper_change_trade( $shared, $shared_gift, 'accept', '1', 'confirmed' );
	} );
	$check( is_wp_error( $locked ) && 'offered' === get_post_meta( $shared_gift, '_pspp_trade', true )['status'],
		'Money gift bypassed the actual payer base lock.' );
	$check( true === ps_pen_paper_change_trade( $shared, $shared_gift, 'accept', '1', 'confirmed' ) &&
		1 === get_post_meta( $bases['a'], '_pspp_wallet', true )['balance'] && 9 === get_post_meta( $bases['b'], '_pspp_wallet', true )['balance'] &&
		'' === get_post_meta( $aliases['a'], '_pspp_wallet', true ) && '' === get_post_meta( $aliases['b'], '_pspp_wallet', true ) &&
		$shared_gear === array( ps_pen_paper_gear_state( $aliases['a'] ), ps_pen_paper_gear_state( $aliases['b'] ) ), 'Shared gift used alias wallets or changed inventory.' );
	wp_set_current_user( $users['gm'] );
	$tc = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'],
		'post_title' => 'Transfer target fixture' ), true );
	$check( is_int( $tc ), 'Transfer target campaign failed.' ); $parents[] = $tc; $target = ps_pen_paper_get_campaign( $tc );
	foreach ( array( 'a', 'b' ) as $key ) { add_post_meta( $tc, '_pspp_player', (string) $users[ $key ] ); }
	$check( ! is_wp_error( ps_pen_paper_save_universe_link( $tc, $u, '0', '1', 'confirmed' ) ) &&
		! is_wp_error( ps_pen_paper_adopt_universe_rules( $tc, '1', 'confirmed' ) ), 'Transfer target link failed.' );
	$target_chars = array();
	foreach ( array( 'a', 'b' ) as $key ) {
		$target_chars[ $key ] = ps_pen_paper_attach_base_character( $tc, $bases[ $key ], '1', '1', 'shared', 'confirmed' );
		$check( is_int( $target_chars[ $key ] ), 'Transfer target character failed.' );
	}
	$check( is_wp_error( ps_pen_paper_transfer_context( $shared, $target, $aliases['a'], $target_chars['b'], false ) ),
		'Cross-campaign transfers were enabled by default.' );
	$policy['transfers'] = '1';
	$check( true === ps_pen_paper_save_trade_policy( $universe, $policy, '1' ), 'Universe transfer permission failed.' );
	$image = wp_insert_post( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $sc,
		'post_author' => $users['gm'], 'post_title' => 'Transfer image fixture', 'post_mime_type' => 'image/png',
		'post_content' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=' ), true );
	$check( is_int( $image ), 'Transfer image failed.' );
	$check( true === ps_pen_paper_save_equipment_schema( $shared, array( 'slots' => 'hand | Hand | tool',
		'rarities' => 'normal | Normal | #abcdef' ), '0' ), 'Local transfer schema failed.' );
	$local_item = ps_pen_paper_save_item( $shared, 0, $item_input, '0' );
	$check( is_int( $local_item ), 'Local transfer item failed.' );
	$local_document = get_post_meta( $local_item, '_pspp_item', true ); $local_document['item']['asset_id'] = $image;
	update_post_meta( $local_item, '_pspp_item', $local_document );
	foreach ( array( $local_item, $local_item ) as $catalogue_id ) {
		$check( true === ps_pen_paper_save_gear( $shared, $aliases['a'], 'grant_item',
			array( 'item_id' => (string) $catalogue_id, 'item_revision' => '1' ),
			(string) ps_pen_paper_gear_state( $aliases['a'] )['revision'] ), 'Transfer grant failed.' );
	}
	$check( true === ps_pen_paper_change_wallet( $shared, $aliases['a'], 'grant', '9', 'Transfer funds',
		(string) get_post_meta( $bases['a'], '_pspp_wallet', true )['revision'], (string) ps_pen_paper_currency_document( $u )['revision'] ), 'Transfer funds failed.' );
	wp_set_current_user( $users['a'] );
	$check( true === ps_pen_paper_save_gear( $shared, $aliases['a'], 'equip_items', array( 'slots' => array() ),
		(string) ps_pen_paper_gear_state( $aliases['a'] )['revision'] ), 'Transfer sender pending fixture failed.' );
	wp_set_current_user( $users['b'] );
	$check( true === ps_pen_paper_save_gear( $target, $target_chars['b'], 'equip_items', array( 'slots' => array() ), '0' ), 'Transfer recipient pending fixture failed.' );
	wp_set_current_user( $users['gm'] );
	$transfer_items = array_keys( ps_pen_paper_gear_state( $aliases['a'] )['active']['items'] );
	$transfer = array( 'items' => $transfer_items, 'amount' => '3', 'confirmation' => 'confirmed', 'token' => wp_generate_uuid4(),
		'gear_revision' => (string) ps_pen_paper_gear_state( $aliases['a'] )['revision'],
		'target_gear_revision' => (string) ps_pen_paper_gear_state( $target_chars['b'] )['revision'],
		'policy_revision' => '2', 'wallet_revision' => (string) get_post_meta( $bases['a'], '_pspp_wallet', true )['revision'],
		'target_wallet_revision' => (string) get_post_meta( $bases['b'], '_pspp_wallet', true )['revision'],
		'currency_revision' => (string) ps_pen_paper_currency_document( $u )['revision'],
		'target_currency_revision' => (string) ps_pen_paper_currency_document( $u )['revision'],
		'epochs' => array( $sc => ps_pen_paper_scope_epoch( $sc ), $tc => ps_pen_paper_scope_epoch( $tc ), $u => ps_pen_paper_scope_epoch( $u ) ) );
	wp_set_current_user( $users['a'] );
	$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $transfer ) ),
		'Player bypassed transfer control.' );
	ob_start(); ps_pen_paper_render_character_transfers( $shared, get_post( $aliases['a'] ), $page_id );
	$check( '' === ob_get_clean(), 'Transfer form was exposed to a player.' );
	add_post_meta( $sc, '_pspp_gm', (string) $users['other'] ); wp_set_current_user( $users['other'] );
	$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $transfer ) ),
		'A GM of only the source campaign transferred into another campaign.' );
	wp_set_current_user( $users['gm'] );
	$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $campaign, $aliases['a'], $a, $transfer ) ),
		'Transfer reached a campaign outside the universe.' );
	$foreign_u = ps_pen_paper_save_universe( 0, array( 'name' => 'Foreign transfer universe', 'description' => '' ), '0' );
	$check( is_int( $foreign_u ), 'Foreign transfer universe failed.' ); $parents[] = $foreign_u;
	$fc = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'],
		'post_title' => 'Foreign transfer campaign' ), true ); $check( is_int( $fc ), 'Foreign transfer campaign failed.' ); $parents[] = $fc;
	$check( ! is_wp_error( ps_pen_paper_save_universe_link( $fc, $foreign_u, '0', '1', 'confirmed' ) ) &&
		is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, ps_pen_paper_get_campaign( $fc ), $aliases['a'], $a, $transfer ) ),
		'Transfer crossed into a different universe.' );
	$bad = $transfer; $bad['confirmation'] = '';
	$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $bad ) ), 'Unconfirmed transfer succeeded.' );
	$bad = $transfer; $bad['epochs'][ $tc ] = wp_generate_uuid4();
	$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $bad ) ), 'Transfer used a stale target epoch.' );
	$bad = $transfer; $bad['items'] = array( $transfer_items[0], $transfer_items[0] );
	$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $bad ) ), 'Transfer duplicated an instance.' );
	$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['a'], $transfer ) ),
		'Same shared wallet was debited and credited as separate accounts.' );
	$locked = ps_pen_paper_with_character_lock( $bases['b'], function () use ( $shared, $target, $aliases, $target_chars, $transfer ) {
		return ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $transfer );
	} );
	$check( is_wp_error( $locked ), 'Transfer bypassed the actual recipient wallet lock.' );
	$bad = $transfer; $bad['policy_revision'] = '1';
	$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $bad ) ), 'Transfer ignored a stale universe policy.' );
	$bad = $transfer; $bad['amount'] = '11';
	$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $bad ) ) &&
		10 === get_post_meta( $bases['a'], '_pspp_wallet', true )['balance'], 'Unfunded transfer debited the source.' );
	wp_set_current_user( $users['a'] );
	$reservation = ps_pen_paper_offer_trade( $shared, $aliases['a'], $aliases['b'], 'sale', $transfer_items[0], '1',
		$transfer['gear_revision'], '2', wp_generate_uuid4() );
	$check( is_int( $reservation ), 'Transfer reservation fixture failed.' );
	wp_set_current_user( $users['gm'] );
	$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $transfer ) ),
		'Transfer bypassed a pending trade reservation.' );
	wp_set_current_user( $users['a'] );
	$check( true === ps_pen_paper_change_trade( $shared, $reservation, 'cancel', '1', 'confirmed' ), 'Transfer reservation cleanup failed.' );
	wp_set_current_user( $users['gm'] );
	$original_gear = ps_pen_paper_gear_state( $aliases['a'] );
	foreach ( array( 'active', 'pending' ) as $slot_state ) {
		$equipped = $original_gear; $equipped[ $slot_state ]['slots']['hand'] = $transfer_items[0];
		update_post_meta( $aliases['a'], '_pspp_gear', $equipped );
		$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $transfer ) ),
			'Transfer moved an active or pending equipped item.' );
	}
	$bound_gear = $original_gear;
	foreach ( array( 'active', 'pending' ) as $state ) { $bound_gear[ $state ]['items'][ $transfer_items[0] ]['item']['binding'] = 'bound'; }
	update_post_meta( $aliases['a'], '_pspp_gear', $bound_gear );
	$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $transfer ) ), 'Transfer moved a character-bound instance.' );
	$incompatible = $original_gear; $missing_bonus = wp_generate_uuid4();
	foreach ( array( 'active', 'pending' ) as $state ) {
		$incompatible[ $state ]['items'][ $transfer_items[0] ]['item']['bonuses'][ $missing_bonus ] = 5;
	}
	update_post_meta( $aliases['a'], '_pspp_gear', $incompatible );
	$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $transfer ) ),
		'Transfer accepted bonus fields absent from the target rules.' );
	update_post_meta( $aliases['a'], '_pspp_gear', $original_gear );
	$before = array( ps_pen_paper_gear_state( $aliases['a'] ), ps_pen_paper_gear_state( $target_chars['b'] ),
		get_post_meta( $bases['a'], '_pspp_wallet', true ), get_post_meta( $bases['b'], '_pspp_wallet', true ) );
	foreach ( array( 'wallet_saved', 'gear_saved' ) as $kind ) {
		$fail_event = static function ( $check, $object, $key, $value ) use ( $kind ) {
			return '_pspp_event' === $key && $kind === ( $value['kind'] ?? '' ) ? false : $check;
		};
		add_filter( 'update_post_metadata', $fail_event, 10, 4 );
		try {
			$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $transfer ) ) &&
				$before === array( ps_pen_paper_gear_state( $aliases['a'] ), ps_pen_paper_gear_state( $target_chars['b'] ),
					get_post_meta( $bases['a'], '_pspp_wallet', true ), get_post_meta( $bases['b'], '_pspp_wallet', true ) ),
				'Transfer journal failure left a partial transfer.' );
		} finally { remove_filter( 'update_post_metadata', $fail_event, 10 ); }
	}
	$fail_receipt = static function ( $check, $object, $key ) { return '_pspp_transfer_receipts' === $key ? false : $check; };
	add_filter( 'update_post_metadata', $fail_receipt, 10, 3 );
	try {
		$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $transfer ) ) &&
			$before === array( ps_pen_paper_gear_state( $aliases['a'] ), ps_pen_paper_gear_state( $target_chars['b'] ),
				get_post_meta( $bases['a'], '_pspp_wallet', true ), get_post_meta( $bases['b'], '_pspp_wallet', true ) ) &&
			! get_posts( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $tc ) ),
			'Failed transfer receipt left partial inventories, payments or copied private images.' );
	} finally { remove_filter( 'update_post_metadata', $fail_receipt, 10 ); }
	$recipient_before = ps_pen_paper_gear_state( $target_chars['b'] ); $full = $recipient_before;
	while ( count( $full['active']['items'] ) < 99 ) { $full['active']['items'][ wp_generate_uuid4() ] = $local_document + array( 'source_id' => $local_item, 'scope_id' => $sc ); }
	$full['pending']['items'] = $full['active']['items']; update_post_meta( $target_chars['b'], '_pspp_gear', $full );
	$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $transfer ) ) &&
		10 === get_post_meta( $bases['a'], '_pspp_wallet', true )['balance'] &&
		9 === get_post_meta( $bases['b'], '_pspp_wallet', true )['balance'], 'Full target inventory left a transfer payment behind.' );
	update_post_meta( $target_chars['b'], '_pspp_gear', $recipient_before );
	$target_value = implode( ':', array( $tc, $target_chars['b'], $transfer['target_gear_revision'],
		$transfer['target_wallet_revision'], $transfer['target_currency_revision'], $transfer['epochs'][ $tc ] ) );
	ob_start(); ps_pen_paper_render_character_transfers( $shared, get_post( $aliases['a'] ), $page_id ); $transfer_html = ob_get_clean();
	$check( false !== strpos( $transfer_html, 'value="' . $target_value . '"' ) && 2 === substr_count( $transfer_html, 'name="transfer_items[]"' ),
		'Transfer form omitted the eligible target or concrete instances.' );
	$submit_frontend( array( 'pspp_operation' => 'transfer_campaign_assets', 'campaign_id' => (string) $sc,
		'character_id' => (string) $aliases['a'], 'transfer_complete' => 'complete', 'transfer_target' => $target_value,
		'transfer_items' => $transfer_items, 'transfer_amount' => '3', 'transfer_confirmation' => 'confirmed',
		'transfer_token' => $transfer['token'], 'transfer_policy_revision' => '2', 'transfer_gear_revision' => $transfer['gear_revision'],
		'transfer_wallet_revision' => $transfer['wallet_revision'], 'transfer_currency_revision' => $transfer['currency_revision'],
		'transfer_source_epoch' => $transfer['epochs'][ $sc ], 'transfer_universe_epoch' => $transfer['epochs'][ $u ] ) );
	foreach ( array( $aliases['a'], $target_chars['b'], $bases['a'], $bases['b'] ) as $id ) { clean_post_cache( $id ); }
	$received = ps_pen_paper_gear_state( $target_chars['b'] );
	$check( array() === ps_pen_paper_gear_state( $aliases['a'] )['active']['items'] &&
		array() === ps_pen_paper_gear_state( $aliases['a'] )['pending']['items'] &&
		$transfer_items === array_keys( $received['active']['items'] ) && $received['active']['items'] === $received['pending']['items'] &&
		7 === get_post_meta( $bases['a'], '_pspp_wallet', true )['balance'] && 12 === get_post_meta( $bases['b'], '_pspp_wallet', true )['balance'],
		'Frontend transfer lost UUIDs, pending inventory or conserved balances.' );
	foreach ( $received['active']['items'] as $instance ) {
		$check( $tc === $instance['scope_id'] && $local_item === $instance['source_id'] &&
			$image !== $instance['item']['asset_id'] && ! is_wp_error( ps_pen_paper_private_image_data( $target, $instance['item']['asset_id'] ) ),
			'Transferred private image or frozen source identity is invalid.' );
	}
	$check( true === ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $transfer ) &&
		7 === get_post_meta( $bases['a'], '_pspp_wallet', true )['balance'], 'Transfer retry paid twice or lost its receipt.' );
	$bad = $transfer; $bad['amount'] = '4';
	$check( is_wp_error( ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $bad ) ), 'Transfer token accepted changed terms.' );
	$money_only = $transfer; $money_only['items'] = array(); $money_only['amount'] = '2'; $money_only['token'] = wp_generate_uuid4();
	$money_only['gear_revision'] = (string) ps_pen_paper_gear_state( $aliases['a'] )['revision'];
	$money_only['target_gear_revision'] = (string) $received['revision'];
	$money_only['wallet_revision'] = (string) get_post_meta( $bases['a'], '_pspp_wallet', true )['revision'];
	$money_only['target_wallet_revision'] = (string) get_post_meta( $bases['b'], '_pspp_wallet', true )['revision'];
	$money_gear = array( ps_pen_paper_gear_state( $aliases['a'] ), $received );
	$check( true === ps_pen_paper_transfer_campaign_assets( $shared, $target, $aliases['a'], $target_chars['b'], $money_only ) &&
		$money_gear === array( ps_pen_paper_gear_state( $aliases['a'] ), ps_pen_paper_gear_state( $target_chars['b'] ) ) &&
		5 === get_post_meta( $bases['a'], '_pspp_wallet', true )['balance'] && 14 === get_post_meta( $bases['b'], '_pspp_wallet', true )['balance'],
		'Money-only transfer changed inventory or failed to conserve balances.' );
	unset( $policy['transfers'] );
	$check( true === ps_pen_paper_save_trade_policy( $universe, $policy, '2' ) &&
		is_wp_error( ps_pen_paper_transfer_context( $shared, $target, $aliases['a'], $target_chars['b'], false ) ),
		'Universe transfer permission could not be revoked.' );
	$policy['transfers'] = '1';
	$check( true === ps_pen_paper_save_trade_policy( $universe, $policy, '3' ), 'Transfer permission reactivation failed.' );
	$lc = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'],
		'post_title' => 'Local currency transfer target' ), true );
	$check( is_int( $lc ), 'Local currency campaign failed.' ); $parents[] = $lc; $local_target = ps_pen_paper_get_campaign( $lc );
	add_post_meta( $lc, '_pspp_player', (string) $users['b'] );
	$check( true === ps_pen_paper_save_currency( $local_target, array( 'name' => 'Local transfer currency', 'symbol' => 'LC', 'wallet_mode' => 'campaign' ), '0' ) &&
		! is_wp_error( ps_pen_paper_save_universe_link( $lc, $u, '0', '1', 'confirmed', 'local' ) ) &&
		! is_wp_error( ps_pen_paper_adopt_universe_rules( $lc, '1', 'confirmed' ) ), 'Local currency transfer fixture failed.' );
	$local_character = ps_pen_paper_attach_base_character( $lc, $bases['b'], '1', '1', 'shared', 'confirmed' );
	$check( is_int( $local_character ), 'Local currency recipient failed.' );
	$check( is_wp_error( ps_pen_paper_transfer_context( $shared, $local_target, $aliases['a'], $local_character, true ) ) &&
		! is_wp_error( ps_pen_paper_transfer_context( $target, $local_target, $target_chars['b'], $local_character, false ) ),
		'Transfer converted local currency or unnecessarily blocked item-only transfers.' );
	$item_only = array( 'items' => $transfer_items, 'amount' => '0', 'confirmation' => 'confirmed', 'token' => wp_generate_uuid4(),
		'gear_revision' => (string) ps_pen_paper_gear_state( $target_chars['b'] )['revision'],
		'target_gear_revision' => '0', 'policy_revision' => '4',
		'epochs' => array( $tc => ps_pen_paper_scope_epoch( $tc ), $lc => ps_pen_paper_scope_epoch( $lc ), $u => ps_pen_paper_scope_epoch( $u ) ) );
	$check( true === ps_pen_paper_transfer_campaign_assets( $target, $local_target, $target_chars['b'], $local_character, $item_only ) &&
		$transfer_items === array_keys( ps_pen_paper_gear_state( $local_character )['active']['items'] ) &&
		array() === ps_pen_paper_gear_state( $target_chars['b'] )['active']['items'] &&
		5 === get_post_meta( $bases['a'], '_pspp_wallet', true )['balance'] && 14 === get_post_meta( $bases['b'], '_pspp_wallet', true )['balance'] &&
		'' === get_post_meta( $local_character, '_pspp_wallet', true ), 'Item-only transfer changed balances or created a replacement wallet.' );
	$archive = ps_pen_paper_export_campaign( $sc, 'complete' );
	$check( ! is_wp_error( $archive ), 'Transferred source export failed.' );
	$parsed = ps_pen_paper_parse_import( wp_json_encode( $archive, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	$check( ! is_wp_error( $parsed ), 'Transfer receipt archive did not parse.' );
	$options = array( 'operation' => 'copy', 'target_id' => 0, 'universe_mode' => 'copy', 'universe_id' => 0, 'users' => array(), 'bases' => array() );
	foreach ( $parsed['users'] as $owner => $requirement ) { $options['users'][ $owner ] = $owner; }
	$map = array(); foreach ( array_keys( $parsed['records'] ) as $source_id ) { $map[ $source_id ] = $source_id + 1000000; }
	$prepared = ps_pen_paper_prepare_import( $parsed, $options, $map, false );
	$check( ! is_wp_error( $prepared ), 'Transfer receipts failed native restoration preparation.' ); $receipt_found = false;
	foreach ( $prepared[ $aliases['a'] ]['meta'] as $entry ) {
		if ( '_pspp_transfer_receipts' !== $entry['key'] ) { continue; }
		$receipt_found = isset( $entry['value'][ $transfer['token'] ] ) &&
			$entry['value'] === get_post_meta( $aliases['a'], '_pspp_transfer_receipts', true ) && ps_pen_paper_valid_transfer_receipts( $entry['value'] );
	}
	$check( $receipt_found, 'Native restoration discarded transfer replay protection.' );
	WP_CLI::success( 'Single/batch trade, controlled same-universe transfers, both-campaign authority, private image copies, shared wallet locks, atomic receipts, native mapping and replay guards passed.' );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $parents as $parent ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_trade', 'pspp_character', 'pspp_item', 'pspp_event', 'pspp_asset' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $parent, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $child ) { wp_delete_post( $child, true ); }
		wp_delete_post( $parent, true );
	}
	foreach ( $users as $user ) { is_multisite() ? wpmu_delete_user( $user ) : wp_delete_user( $user ); }
}
