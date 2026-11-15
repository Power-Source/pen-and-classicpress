<?php
/** Run serially with WP-CLI --skip-plugins --skip-themes on the development site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once dirname( __DIR__ ) . '/includes/item-use.php';
require_once dirname( __DIR__ ) . '/includes/item-use-ui.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( ! defined( 'CPC_CORE_PLUGINS' ) ) { define( 'CPC_CORE_PLUGINS', 'core-profile,core-activity' ); }
require_once dirname( __DIR__, 2 ) . '/ps-community/activity/cpc_profile_tabs.php';
foreach ( array( 'campaign', 'universe', 'character', 'item', 'roll', 'event' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
$original_user = get_current_user_id();
$users = array();
$campaign_id = 0;
$world_id = 0;
$check = function ( $condition, $message, $result = null ) {
	if ( ! $condition ) { throw new RuntimeException( $message . ( is_wp_error( $result ) ? ' ' . $result->get_error_code() . ': ' . $result->get_error_message() : '' ) ); }
};
$fixture_success = function ( $result, $message ) use ( $check ) { $check( true === $result, $message, $result ); };
try {
	foreach ( array( 'gm', 'player', 'outsider' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-use-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( is_int( $id ), 'User fixture failed.', $id ); $users[ $role ] = $id;
	}
	wp_set_current_user( $users['gm'] );
	$campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Item use fixture' ), true );
	$check( is_int( $campaign_id ), 'Campaign fixture failed.', $campaign_id );
	add_post_meta( $campaign_id, '_pspp_gm', (string) $users['gm'] );
	add_post_meta( $campaign_id, '_pspp_player', (string) $users['player'] );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	$rules = ps_pen_paper_save_rules( $campaign_id, array( 'name' => 'Use rules', 'description' => '', 'dice' => array( '6' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Attribute', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '10', 'start' => '5' ),
		array( 'id' => '', 'label' => 'Resource', 'type' => 'resource', 'description' => '', 'min' => '0', 'max' => '10', 'start' => '3' ),
	) ), '0' );
	$check( ! is_wp_error( $rules ), 'Rules fixture failed.', $rules );
	$attribute = $rules['fields'][0]['id']; $resource = $rules['fields'][1]['id'];
	$rules['character_mode'] = 'direct'; update_post_meta( $campaign_id, '_pspp_rules', $rules );
	$character_id = ps_pen_paper_save_character( $campaign_id, 0, $users['player'], array( 'name' => 'Use hero', 'values' => array( $attribute => '5', $resource => array( 'current' => '3', 'maximum' => '10' ) ) ), '0', '1' );
	$check( is_int( $character_id ), 'Character fixture failed.', $character_id );
	$character = get_post( $character_id );
	$grant = function ( $condition, $effects, $temporary = false, $rounds = 0, $threshold = 0, $failure = 'keep', $outside = 'inherit', $expression = '' ) use ( $campaign, $character_id, $attribute, $check ) {
		$uuid = wp_generate_uuid4();
		$item = array( 'name' => 'Native consumable', 'description' => '', 'kind' => 'configured_item', 'rarity' => 'normal', 'rarity_label' => 'Normal', 'color' => '#718096',
			'asset_id' => 0, 'properties' => array(), 'bonuses' => array(), 'binding' => 'bound', 'outside' => $outside,
			'use_rule' => array( 'condition' => $condition, 'expression' => $expression, 'attribute' => 'test' === $condition && '' === $expression ? $attribute : '', 'threshold' => $threshold,
				'failure' => $failure, 'effects' => $effects, 'temporary' => $temporary, 'rounds' => $rounds ) );
		$check( ps_pen_paper_validate_item( $item ), 'Native item fixture invalid.' );
		$gear = ps_pen_paper_gear_state( $character_id ); $gear['revision']++;
		$instance = array( 'source_id' => 1, 'scope_id' => (int) $campaign->ID, 'revision' => 1, 'item' => $item );
		$gear['active']['items'][ $uuid ] = $instance;
		if ( $gear['pending'] ) { $gear['pending']['items'][ $uuid ] = $instance; }
		update_post_meta( $character_id, '_pspp_gear', $gear );
		return $uuid;
	};
	$use = function ( $uuid, $operation = 'use_item', $context = 'game', $decision = '', $revision = null ) use ( $campaign, $character, $character_id ) {
		$state = ps_pen_paper_character_state( $campaign, $character, false );
		if ( is_wp_error( $state ) ) { throw new RuntimeException( 'Unexpected character state: ' . $state->get_error_code() . ': ' . $state->get_error_message() ); }
		$input = array( 'instance_id' => $uuid, 'character_revision' => ps_pen_paper_item_use_character_revision( $state ), 'rules_revision' => (string) ps_pen_paper_get_rules( $campaign->ID )['revision'] );
		$input['round_revision'] = (string) ps_pen_paper_game_round( $campaign->ID )['revision'];
		$input['policy_revision'] = (string) ps_pen_paper_item_action_policy( $campaign )['revision'];
		if ( $decision ) { $input['decision'] = $decision; }
		return ps_pen_paper_use_item( $campaign, $character_id, $operation, $input, null === $revision ? (string) ps_pen_paper_gear_state( $character_id )['revision'] : $revision, $context );
	};
	$value = function ( $id ) use ( $campaign, $character ) {
		$state = ps_pen_paper_character_state( $campaign, $character );
		if ( is_wp_error( $state ) ) { throw new RuntimeException( 'Unexpected effective state: ' . $state->get_error_code() . ': ' . $state->get_error_message() ); }
		return $state['active']['values'][ $id ];
	};
	$failed = $grant( 'test', array( $attribute => 1 ), false, 0, 10 );
	wp_set_current_user( $users['player'] );
	$check( true === $use( $failed ) && 5 === $value( $attribute ) && isset( ps_pen_paper_gear_state( $character_id )['active']['items'][ $failed ] ), 'Failed test consumed/applied.' );
	$check( 'failed' === ps_pen_paper_gear_state( $character_id )['uses']['receipts'][ $failed ]['outcome'] && is_wp_error( $use( $failed ) ), 'Failed attempt replayed.' );
	$dice_failed = $grant( 'test', array( $attribute => 1 ), false, 0, 7, 'consume', 'inherit', 'W6' );
	$check( true === $use( $dice_failed ) && ! isset( ps_pen_paper_gear_state( $character_id )['active']['items'][ $dice_failed ] ) && 5 === $value( $attribute ), 'Configured failed-test consumption wrong.' );
	$approval = $grant( 'approval', array( $attribute => 1 ) );
	$check( true === $use( $approval ) && 5 === $value( $attribute ) && is_wp_error( $use( $approval ) ), 'Approval prerequisite bypassed.' );
	wp_set_current_user( $users['gm'] );
	$check( true === $use( $approval, 'review_item_use', 'game', 'approve' ) && 6 === $value( $attribute ), 'Approval did not atomically apply.' );
	$permanent = $grant( 'immediate', array( $resource => 2 ) );
	$authorized_state = ps_pen_paper_character_state( $campaign, $character, false );
	$unauthorized_input = array( 'instance_id' => $permanent, 'character_revision' => ps_pen_paper_item_use_character_revision( $authorized_state ),
		'rules_revision' => (string) $rules['revision'], 'round_revision' => (string) ps_pen_paper_game_round( $campaign_id )['revision'], 'policy_revision' => (string) ps_pen_paper_item_action_policy( $campaign )['revision'] );
	$unauthorized_revision = (string) ps_pen_paper_gear_state( $character_id )['revision'];
	wp_set_current_user( $users['outsider'] );
	$unauthorized = ps_pen_paper_use_item( $campaign, $character_id, 'use_item', $unauthorized_input, $unauthorized_revision );
	$check( is_wp_error( $unauthorized ) && 'forbidden' === $unauthorized->get_error_code(), 'Outsider consumed item or did not get native forbidden error.' );
	wp_set_current_user( $users['player'] );
	$stale = (string) ps_pen_paper_gear_state( $character_id )['revision'];
	$check( true === $use( $permanent ) && 5 === $value( $resource )['current'], 'Resource restore failed.' );
	$check( 5 === get_post_meta( $character_id, '_pspp_character', true )['active']['values'][ $resource ]['current'], 'Permanent resource was not native.' );
	$check( is_wp_error( $use( $permanent, 'use_item', 'game', '', $stale ) ) && is_wp_error( $use( $permanent ) ), 'Stale concurrent/replayed consumption succeeded.' );
	$range = $grant( 'immediate', array( $resource => 6 ) );
	$before = ps_pen_paper_gear_state( $character_id ); $raw = ps_pen_paper_raw_character_state( $character_id );
	$check( is_wp_error( $use( $range ) ) && $before === ps_pen_paper_gear_state( $character_id ) && $raw === ps_pen_paper_raw_character_state( $character_id ), 'Resource overflow did not roll back.' );
	$atomic = $grant( 'immediate', array( $attribute => 1, $resource => 1 ) );
	$before = ps_pen_paper_gear_state( $character_id ); $raw = ps_pen_paper_raw_character_state( $character_id );
	$failure = function ( $data, $postarr ) {
		$event = isset( $postarr['ID'] ) ? get_post_meta( $postarr['ID'], '_pspp_event', true ) : array();
		if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] && 'gear_saved' === ( $event['kind'] ?? null ) ) { $data['post_status'] = 'draft'; }
		return $data;
	};
	add_filter( 'wp_insert_post_data', $failure, 10, 2 );
	try { $check( is_wp_error( $use( $atomic ) ), 'Audit failure accepted.' ); } finally { remove_filter( 'wp_insert_post_data', $failure ); }
	$check( $before === ps_pen_paper_gear_state( $character_id ) && $raw === ps_pen_paper_raw_character_state( $character_id ), 'Consume/effect journal rollback was partial.' );
	$temp = $grant( 'immediate', array( $attribute => 2 ), true );
	$check( true === $use( $temp ) && 8 === $value( $attribute ), 'Temporary effect missing.' );
	$raw = ps_pen_paper_raw_character_state( $character_id ); $raw['active']['values'][ $attribute ] = 7; $raw['revision']++; update_post_meta( $character_id, '_pspp_character', $raw );
	wp_set_current_user( $users['gm'] );
	$check( true === $use( $temp, 'end_item_effect' ) && 7 === $value( $attribute ) && true === $use( $temp, 'end_item_effect' ), 'Temporary ending undid unrelated later edit or replay was not idempotent.' );
	$round_item = $grant( 'round', array( $attribute => 1 ), true, 1 );
	$check( is_wp_error( $use( $round_item ) ), 'Round prerequisite ran with no session.' );
	$check( true === ps_pen_paper_control_game_round( $campaign, 'start_game_session', '0' ), 'Session start failed.' );
	$check( true === $use( $round_item ) && is_wp_error( $use( $round_item ) ) && 7 === $value( $attribute ), 'Round click bypassed sitting out.' );
	$check( true === ps_pen_paper_character_sitting_out( $campaign, $character_id ) && is_wp_error( ps_pen_paper_require_character_game_action( $campaign, $character_id ) ), 'Sit-out request did not block gameplay action guard.' );
	wp_set_current_user( $users['player'] );
	$movement = new WP_REST_Request( 'POST', '/ps-pen-paper/v1/campaigns/' . $campaign_id . '/map' );
	$movement->set_url_params( array( 'id' => (string) $campaign_id ) );
	$movement->set_header( 'Content-Type', 'application/json' );
	$movement->set_body( wp_json_encode( array( 'operation' => 'move', 'character_id' => 0, 'context' => 'profile', 'x' => 1, 'y' => 1 ) ) );
	$blocked_movement = ps_pen_paper_table_guard_map_callback( null, $movement, $movement->get_route(), array( 'callback' => 'ps_pen_paper_map_request' ) );
	$check( is_wp_error( $blocked_movement ) && 'character_sitting_out' === $blocked_movement->get_error_code(), 'Native map callback did not block user-owned markers under sit-out locks, or trusted posted context.' );
	$blocked_response = rest_do_request( $movement );
	$check( 409 === $blocked_response->get_status() && 'character_sitting_out' === $blocked_response->get_data()['code'],
		'REST dispatch bypassed the sit-out movement guard.' );
	$roll_input = array( 'expression' => 'W6', 'visibility' => 'group', 'character' => ps_pen_paper_character_token( $character, ps_pen_paper_character_state( $campaign, $character ) ), 'attribute' => '', 'label' => 'Sit-out rejection' );
	$blocked_roll = ps_pen_paper_roll_dice( $campaign_id, $roll_input, '1' );
	$check( is_wp_error( $blocked_roll ) && 'character_sitting_out' === $blocked_roll->get_error_code(), 'Native dice action was not blocked by sitting out: ' . ( is_wp_error( $blocked_roll ) ? $blocked_roll->get_error_message() : '' ) );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_control_game_round( $campaign, 'advance_game_round', '1' ) && true === $use( $round_item ) && 8 === $value( $attribute ), 'Round advance did not satisfy prerequisite.' );
	$check( true === ps_pen_paper_require_character_game_action( $campaign, $character_id ), 'Explicit round advance did not release gameplay action guard.' );
	wp_set_current_user( $users['player'] );
	$roll_input['character'] = ps_pen_paper_character_token( $character, ps_pen_paper_character_state( $campaign, $character ) );
	$roll_input['label'] = 'Explicit round re-enable';
	$enabled_roll = ps_pen_paper_roll_dice( $campaign_id, $roll_input, '1' );
	$check( is_int( $enabled_roll ) && $enabled_roll > 0, 'Explicit GM round advance did not re-enable native dice: ' . ( is_wp_error( $enabled_roll ) ? $enabled_roll->get_error_message() : '' ) );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_control_game_round( $campaign, 'advance_game_round', '2' ) && 7 === $value( $attribute ), 'Configured expiry did not end effect.' );
	$profile = $grant( 'test', array( $attribute => 1 ), false, 0, 100 );
	$check( is_wp_error( $use( $profile, 'use_item', 'profile' ) ), 'Profile gate not explicit.' );
	$check( true === ps_pen_paper_save_item_action_policy( $campaign, array( 'enabled' => 'yes', 'equip' => 'allow', 'use' => 'allow' ), '0' ), 'Profile policy save failed.' );
	$check( is_wp_error( $use( $profile, 'use_item', 'profile' ) ), 'Active session allowed profile bypass.' );
	$check( true === ps_pen_paper_control_game_round( $campaign, 'end_game_session', '3' ), 'Session end failed.' );
	wp_set_current_user( $users['player'] );
	$check( true === $use( $profile, 'use_item', 'profile' ) && 8 === $value( $attribute ), 'Allowed profile use demanded impossible game probe.' );
	$denied = $grant( 'immediate', array( $attribute => 1 ), false, 0, 0, 'keep', 'deny' );
	$check( is_wp_error( $use( $denied, 'use_item', 'profile' ) ), 'Item deny did not override campaign allow.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_save_gear( $campaign, $character_id, 'set_item_outside', array( 'instance_id' => $denied, 'outside' => 'allow' ), (string) ps_pen_paper_gear_state( $character_id )['revision'] ), 'GM could not configure an already-issued instance outside policy.' );
	wp_set_current_user( $users['player'] );
	$check( 'allow' === ps_pen_paper_item_action_permission( $campaign, 'profile', 'use', array( ps_pen_paper_gear_state( $character_id )['active']['items'][ $denied ]['item'] ) ) &&
		is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'set_item_outside', array( 'instance_id' => $denied, 'outside' => 'deny' ), (string) ps_pen_paper_gear_state( $character_id )['revision'] ) ), 'Issued policy did not update or player changed GM policy.' );
	$profile_round = $grant( 'round', array( $resource => 1 ) );
	$check( true === $use( $profile_round, 'use_item', 'profile' ) && 6 === $value( $resource )['current'], 'Profile round rule was not replaced by permitted immediate use.' );
	$temp_resource = $grant( 'immediate', array( $resource => 2 ), true );
	$check( true === $use( $temp_resource ) && 8 === $value( $resource )['current'], 'Temporary resource not available natively.' );
	$cost = ps_pen_paper_character_resource_cost( $campaign, $character, $resource, 3 );
	$spent = ps_pen_paper_with_character_resource_locks( $campaign, $character, $cost, function () use ( $campaign, $character, $cost ) {
		return ps_pen_paper_atomic_event( $character->ID, function () use ( $campaign, $character, $cost ) { return ps_pen_paper_write_character_resource_cost( $campaign, $character, $cost ); } );
	} );
	$check( true === $spent && 5 === $value( $resource )['current'], 'Normal resource spending could not spend temporary restoration.' );
	wp_set_current_user( $users['gm'] );
	$check( true === $use( $temp_resource, 'end_item_effect' ) && 3 === $value( $resource )['current'], 'Ending resource effect undid unrelated later spending.' );
	$profile_approval = $grant( 'test', array( $attribute => 1 ), false, 0, 100, 'keep', 'approval' );
	wp_set_current_user( $users['player'] );
	$check( true === $use( $profile_approval, 'use_item', 'profile' ) && 8 === $value( $attribute ) && is_wp_error( $use( $profile_approval ) ), 'Profile approval bypassed prerequisite or changed its trusted context.' );
	wp_set_current_user( $users['gm'] );
	$check( true === $use( $profile_approval, 'review_item_use', 'game', 'approve' ) && 9 === $value( $attribute ) &&
		'profile' === ps_pen_paper_gear_state( $character_id )['uses']['receipts'][ $profile_approval ]['context'], 'GM gameplay review reinterpreted profile prerequisite or log origin.' );
	$ending_range = $grant( 'immediate', array( $resource => 2 ), true );
	$check( true === $use( $ending_range ), 'Range-ending fixture failed.' );
	$raw = ps_pen_paper_raw_character_state( $character_id ); $raw['active']['values'][ $resource ]['current'] = 1; $raw['revision']++; update_post_meta( $character_id, '_pspp_character', $raw );
	$gear_before = ps_pen_paper_gear_state( $character_id );
	$check( is_wp_error( $use( $ending_range, 'end_item_effect' ) ) && $raw === ps_pen_paper_raw_character_state( $character_id ) && $gear_before === ps_pen_paper_gear_state( $character_id ), 'Invalid temporary ending violated bounds or partially rolled back.' );
	$world_id = wp_insert_post( array( 'post_type' => 'pspp_universe', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Basis inventory fixture' ), true );
	$check( is_int( $world_id ), 'Universe fixture failed.', $world_id );
	$world = ps_pen_paper_get_universe( $world_id );
	update_post_meta( $world_id, '_pspp_rules', $rules );
	update_post_meta( $world_id, '_pspp_universe_members', array( 'revision' => 1, 'users' => array( $users['player'] ) ) );
	$basis_id = ps_pen_paper_save_character( $world_id, 0, $users['player'], array( 'name' => 'Independent basis', 'values' => array( $attribute => '5', $resource => array( 'current' => '3', 'maximum' => '10' ) ) ), '0', '1' );
	$check( is_int( $basis_id ), 'Universe basis creation failed.', $basis_id );
	$basis = get_post( $basis_id );
	$fixture_success( ps_pen_paper_save_equipment_schema( $world, array( 'slots' => 'kopf | Kopf | hat', 'rarities' => 'normal | Normal | #718096' ), '0' ), 'Universe slot configuration failed.' );
	$fixture_success( ps_pen_paper_save_item_action_policy( $world, array( 'enabled' => 'yes', 'equip' => 'allow', 'use' => 'allow' ), '0' ), 'Universe outside policy failed.' );
	$hat_input = array( 'name' => 'Basis hat', 'description' => '', 'kind' => 'hat', 'rarity' => 'normal', 'properties' => '', 'binding' => 'bound', 'bonuses' => array( $attribute => '2' ) );
	$hat_id = ps_pen_paper_save_item( $world, 0, $hat_input, '0' );
	$check( is_int( $hat_id ), 'Universe item creation failed.', $hat_id );
	$issue = function ( $scope, $post, $item_id ) use ( $check ) {
		$before = ps_pen_paper_gear_state( $post->ID );
		$result = ps_pen_paper_save_gear( $scope, $post->ID, 'grant_item', array( 'item_id' => (string) $item_id, 'item_revision' => '1' ), (string) $before['revision'] );
		$check( true === $result, 'Native scoped issuance failed.', $result );
		$new = array_diff( array_keys( ps_pen_paper_gear_state( $post->ID )['active']['items'] ), array_keys( $before['active']['items'] ) );
		return reset( $new );
	};
	$scoped_input = function ( $scope, $post, $uuid ) {
		$state = ps_pen_paper_character_state( $scope, $post, false );
		if ( is_wp_error( $state ) ) { throw new RuntimeException( 'Unexpected scoped state: ' . $state->get_error_code() . ': ' . $state->get_error_message() ); }
		return array( 'instance_id' => $uuid, 'character_revision' => ps_pen_paper_item_use_character_revision( $state ), 'rules_revision' => (string) ps_pen_paper_get_rules( $scope->ID )['revision'],
			'round_revision' => (string) ps_pen_paper_game_round( $scope->ID )['revision'], 'policy_revision' => (string) ps_pen_paper_item_action_policy( $scope )['revision'] );
	};
	$scoped_use = function ( $scope, $post, $uuid, $operation = 'use_item', $context = 'profile', $decision = '' ) use ( $scoped_input ) {
		$input = $scoped_input( $scope, $post, $uuid );
		if ( $decision ) { $input['decision'] = $decision; }
		return ps_pen_paper_use_item( $scope, $post->ID, $operation, $input, (string) ps_pen_paper_gear_state( $post->ID )['revision'], $context );
	};
	$basis_hat = $issue( $world, $basis, $hat_id );
	wp_set_current_user( $users['player'] );
	$check( true === ps_pen_paper_save_gear( $world, $basis_id, 'equip_items', array( 'slots' => array( 'kopf' => $basis_hat ) ), '1', 'profile' ) &&
		7 === ps_pen_paper_character_state( $world, $basis )['active']['values'][ $attribute ], 'Member could not equip a universe basis before campaign admission.' );
	$check( is_wp_error( ps_pen_paper_save_gear( $world, $basis_id, 'equip_items', array(), '2', 'game' ) ), 'Universe member forged game context to bypass profile policy.' );
	wp_set_current_user( $users['gm'] );
	$resource_item = $hat_input; $resource_item['kind'] = 'supply'; $resource_item['bonuses'] = array();
	$resource_item['use'] = array( 'condition' => 'test', 'expression' => 'W6', 'attribute' => '', 'threshold' => '7', 'failure' => 'keep', 'effects' => array( $resource => '2' ), 'temporary' => 'permanent', 'rounds' => '0' );
	$resource_item_id = ps_pen_paper_save_item( $world, 0, $resource_item, '0' );
	$check( is_int( $resource_item_id ), 'Universe consumable template failed.', $resource_item_id );
	$basis_restore = $issue( $world, $basis, $resource_item_id );
	wp_set_current_user( $users['player'] );
	$check( true === $scoped_use( $world, $basis, $basis_restore ) && 5 === ps_pen_paper_character_state( $world, $basis )['active']['values'][ $resource ]['current'], 'Basis profile restore demanded a failing game probe or did not update native resource.' );
	wp_set_current_user( $users['gm'] );
	$temp_item = $resource_item; $temp_item['outside'] = 'approval'; $temp_item['use']['effects'] = array( $attribute => '1' ); $temp_item['use']['temporary'] = 'temporary'; $temp_item['use']['rounds'] = '2';
	$temp_item_id = ps_pen_paper_save_item( $world, 0, $temp_item, '0' );
	$check( is_int( $temp_item_id ), 'Basis temporary template failed.', $temp_item_id );
	$basis_temp = $issue( $world, $basis, $temp_item_id );
	$basis_input = $scoped_input( $world, $basis, $basis_temp ); $basis_revision = (string) ps_pen_paper_gear_state( $basis_id )['revision'];
	update_post_meta( $world_id, '_pspp_universe_members', array( 'revision' => 2, 'users' => array() ) );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_use_item( $world, $basis_id, 'use_item', $basis_input, $basis_revision, 'profile' ) ) &&
		is_wp_error( ps_pen_paper_save_gear( $world, $basis_id, 'equip_items', array(), $basis_revision, 'profile' ) ), 'Preserved owner read access granted revoked membership mutation rights.' );
	wp_set_current_user( $users['gm'] );
	update_post_meta( $world_id, '_pspp_universe_members', array( 'revision' => 3, 'users' => array( $users['player'] ) ) );
	wp_set_current_user( $users['player'] );
	$check( true === $scoped_use( $world, $basis, $basis_temp ) && 7 === ps_pen_paper_character_state( $world, $basis )['active']['values'][ $attribute ] &&
		'profile' === ps_pen_paper_gear_state( $basis_id )['uses']['pending'][ $basis_temp ]['context'], 'Basis profile approval applied before GM consent.' );
	wp_set_current_user( $users['gm'] );
	$check( true === $scoped_use( $world, $basis, $basis_temp, 'review_item_use', 'game', 'approve' ) && 8 === ps_pen_paper_character_state( $world, $basis )['active']['values'][ $attribute ] &&
		0 === ps_pen_paper_gear_state( $basis_id )['uses']['effects'][ $basis_temp ]['expires'], 'Basis GM approval demanded the game probe or did not support explicit temporary ending.' );
	$link = array( 'revision' => 1, 'universe_id' => $world_id, 'name' => 'Basis inventory fixture', 'economy_mode' => 'local' );
	$check( ps_pen_paper_valid_universe_link( $link ), 'Shared universe link fixture is not native-valid.' );
	update_post_meta( $campaign_id, '_pspp_universe_link', $link );
	$alias_id = ps_pen_paper_attach_base_character( $campaign_id, $basis_id, (string) ps_pen_paper_raw_character_state( $basis_id )['revision'], '1', 'shared', 'confirmed' );
	$check( is_int( $alias_id ), 'Shared alias fixture failed.', $alias_id ); $alias = get_post( $alias_id );
	$alias_state = ps_pen_paper_character_state( $campaign, $alias );
	$check( ! is_wp_error( $alias_state ) && 5 === $alias_state['active']['values'][ $attribute ] && 5 === $alias_state['active']['values'][ $resource ]['current'], 'Shared alias duplicated basis equipment/temporary numeric modifiers or lost native restoration.', $alias_state );
	$fixture_success( ps_pen_paper_save_equipment_schema( $campaign, array( 'slots' => 'kopf | Kopf | hat', 'rarities' => 'normal | Normal | #718096' ), '0' ), 'Alias equipment schema failed.' );
	$alias_hat_input = $hat_input; $alias_hat_input['bonuses'][ $attribute ] = '4';
	$alias_hat_id = ps_pen_paper_save_item( $campaign, 0, $alias_hat_input, '0' ); $check( is_int( $alias_hat_id ), 'Alias hat fixture failed.', $alias_hat_id );
	$alias_hat = $issue( $campaign, $alias, $alias_hat_id );
	$fixture_success( ps_pen_paper_save_gear( $campaign, $alias_id, 'equip_items', array( 'slots' => array( 'kopf' => $alias_hat ) ), '1' ), 'Alias equipment failed.' );
	$blocked_restore = $issue( $world, $basis, $resource_item_id );
	$round_revision = (string) ps_pen_paper_game_round( $campaign_id )['revision'];
	$fixture_success( ps_pen_paper_control_game_round( $campaign, 'start_game_session', $round_revision ), 'Shared active session fixture failed.' );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( $scoped_use( $world, $basis, $blocked_restore ) ), 'Basis profile bypassed active shared campaign session gate.' );
	wp_set_current_user( $users['gm'] );
	$fixture_success( ps_pen_paper_save_item_action_policy( $world, array( 'enabled' => 'yes', 'during_active' => 'yes', 'equip' => 'allow', 'use' => 'allow' ), '1' ), 'Explicit active-play basis policy failed.' );
	wp_set_current_user( $users['player'] );
	$check( true === $scoped_use( $world, $basis, $blocked_restore ) && 7 === ps_pen_paper_character_state( $campaign, $alias )['active']['values'][ $resource ]['current'] &&
		9 === ps_pen_paper_character_state( $campaign, $alias )['active']['values'][ $attribute ], 'Shared native effect was applied twice or inherited unrelated basis bonuses.' );
	wp_set_current_user( $users['gm'] );
	$overflow_item = $resource_item; $overflow_item['use']['effects'] = array( $attribute => '2' );
	$overflow_item_id = ps_pen_paper_save_item( $world, 0, $overflow_item, '0' ); $check( is_int( $overflow_item_id ), 'Coupled-range template failed.', $overflow_item_id );
	$coupled = $issue( $world, $basis, $overflow_item_id ); $basis_before = ps_pen_paper_raw_character_state( $basis_id ); $gear_before = ps_pen_paper_gear_state( $basis_id );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( $scoped_use( $world, $basis, $coupled ) ) && $basis_before === ps_pen_paper_raw_character_state( $basis_id ) &&
		$gear_before === ps_pen_paper_gear_state( $basis_id ), 'Basis mutation stranded an equipped shared alias or consumed on coupled bounds failure.' );
	$wrong_scope = ps_pen_paper_use_item( $campaign, $basis_id, 'use_item', $scoped_input( $world, $basis, $coupled ), (string) $gear_before['revision'], 'profile' );
	$check( is_wp_error( $wrong_scope ) && 'forbidden' === $wrong_scope->get_error_code(), 'Universe basis was silently rerouted to a campaign.' );
	wp_set_current_user( $users['gm'] );
	$check( true === $scoped_use( $world, $basis, $basis_temp, 'end_item_effect', 'game' ) &&
		7 === ps_pen_paper_character_state( $world, $basis )['active']['values'][ $attribute ] &&
		9 === ps_pen_paper_character_state( $campaign, $alias )['active']['values'][ $attribute ], 'Explicit basis ending changed an independent alias loadout.' );
	$world_events = ps_pen_paper_get_events( $world );
	$check( ! is_wp_error( $world_events ), 'Universe item-use journal failed native validation.', $world_events );
	$campaign_events = ps_pen_paper_get_events( $campaign );
	$check( ! is_wp_error( $campaign_events ), 'Native journal rejected new documents.', $campaign_events );
	WP_CLI::success( 'Item-use prerequisites, profile policy, replay/conflict, atomic rollback, resource bounds and temporary expiry passed.' );
} finally {
	wp_set_current_user( $original_user );
	if ( $campaign_id ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_item', 'pspp_roll', 'pspp_event' ), 'post_status' => 'any', 'post_parent' => $campaign_id, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
		wp_delete_post( $campaign_id, true );
	}
	if ( $world_id ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_item', 'pspp_asset', 'pspp_event' ), 'post_status' => 'any', 'post_parent' => $world_id, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
		wp_delete_post( $world_id, true );
	}
	foreach ( $users as $id ) { wp_delete_user( $id ); }
}
