<?php
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( ! defined( 'CPC_CORE_PLUGINS' ) ) { define( 'CPC_CORE_PLUGINS', 'core-profile,core-activity' ); }
require_once dirname( __DIR__, 2 ) . '/ps-community/activity/cpc_profile_tabs.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
ps_pen_paper_register_campaign_type();
ps_pen_paper_register_universe_type();
ps_pen_paper_register_character_type();
ps_pen_paper_register_scene_type();
ps_pen_paper_register_event_type();
ps_pen_paper_register_adventure_types();
ps_pen_paper_register_item_type();
ps_pen_paper_register_trade_type();

$original_user = get_current_user_id();
$users = array(); $parents = array();
$check = static function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$success = static function ( $value, $message ) use ( $check ) {
	$check( ! is_wp_error( $value ), $message . ( is_wp_error( $value ) ? ': ' . $value->get_error_code() . ': ' . $value->get_error_message() : '' ) );
	return $value;
};
try {
	foreach ( array( 'gm', 'player', 'other', 'outsider' ) as $role ) {
		$users[ $role ] = $success( wp_insert_user( array( 'user_login' => 'pspp-round-' . $role . '-' . wp_generate_password( 12, false, false ),
			'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber' ) ), 'Account fixture failed' );
	}
	wp_set_current_user( $users['gm'] );
	wp_get_current_user()->add_cap( 'ps_pen_paper_create_campaigns' );
	$universe_id = $success( ps_pen_paper_save_universe( 0, array( 'name' => 'Round test universe', 'description' => '' ), '0' ), 'Universe fixture failed' );
	$parents[] = $universe_id;
	$rules = $success( ps_pen_paper_save_rules( $universe_id, array( 'name' => 'Round rules', 'description' => '', 'dice' => array( '6' ),
		'fields' => array( array( 'id' => '', 'label' => 'Mana', 'type' => 'resource', 'description' => '', 'min' => '0', 'max' => '20', 'start' => '10' ) ) ), '0' ), 'Rules fixture failed' );
	$field = $rules['fields'][0]['id']; $characters = array();
	$world = ps_pen_paper_get_universe( $universe_id );
	$success( ps_pen_paper_save_equipment_schema( $world, array( 'slots' => 'hand | Hand | potion', 'rarities' => 'normal | Normal | #718096' ), '0' ), 'World equipment schema failed' );
	$item_id = $success( ps_pen_paper_save_item( $world, 0, array( 'name' => 'Mana potion', 'description' => '', 'kind' => 'potion',
		'rarity' => 'normal', 'binding' => 'bound', 'properties' => '', 'bonuses' => array(),
		'use' => array( 'condition' => 'immediate', 'expression' => '', 'attribute' => '', 'threshold' => '0', 'failure' => 'keep',
			'effects' => array( $field => '1' ), 'temporary' => 'permanent', 'rounds' => '0' ) ), '0' ), 'Consumable fixture failed' );
	$trade_item = $success( ps_pen_paper_save_item( $world, 0, array( 'name' => 'Trade token', 'description' => '', 'kind' => 'potion',
		'rarity' => 'normal', 'binding' => 'tradeable', 'properties' => '', 'bonuses' => array() ), '0' ), 'Trade item fixture failed' );
	$success( ps_pen_paper_save_trade_policy( $world, array( 'kinds' => array( 'gift' ), 'approval' => '0' ), '0' ), 'World trade policy failed' );
	foreach ( array( 'player', 'other' ) as $owner ) {
		$characters[ $owner ] = $success( ps_pen_paper_save_character( $universe_id, 0, $users[ $owner ], array( 'name' => $owner,
			'values' => array( $field => array( 'current' => '10', 'maximum' => '20' ) ) ), '0', '1' ), 'Character fixture failed' );
	}
	$source_id = $success( wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private',
		'post_author' => $users['gm'], 'post_title' => 'Reusable round fixture' ), true ), 'Campaign fixture failed' );
	$parents[] = $source_id;
	foreach ( array( 'player', 'other' ) as $owner ) { add_post_meta( $source_id, '_pspp_player', (string) $users[ $owner ] ); }
	$source = ps_pen_paper_get_campaign( $source_id );
	$success( ps_pen_paper_save_gear( $world, $characters['player'], 'grant_item', array( 'item_id' => (string) $item_id, 'item_revision' => '1' ), '0', 'profile' ), 'Base inventory grant failed' );
	$item_uuid = array_key_first( ps_pen_paper_gear_state( $characters['player'] )['active']['items'] );
	$tokens = array();
	for ( $n = 0; $n < 2; $n++ ) {
		$success( ps_pen_paper_save_gear( $world, $characters['player'], 'grant_item', array( 'item_id' => (string) $trade_item, 'item_revision' => '1' ),
			(string) ps_pen_paper_gear_state( $characters['player'] )['revision'], 'profile' ), 'Trade token grant failed' );
		$all_items = ps_pen_paper_gear_state( $characters['player'] )['active']['items']; $tokens[] = array_key_last( $all_items );
	}
	$success( ps_pen_paper_save_universe_link( $source_id, $universe_id, '0', '1', 'confirmed' ), 'Universe binding failed' );
	$success( ps_pen_paper_adopt_universe_rules( $source_id, '1', 'confirmed' ), 'Rules adoption failed' );
	$scene_id = $success( ps_pen_paper_save_scene( $source, 0, array( 'title' => 'START', 'text' => 'Visible story', 'secret' => 'SECRET_ROUND_NOTE', 'grid' => '0' ), '0' ), 'Scene fixture failed' );
	$success( ps_pen_paper_publish_scene( $source, $scene_id, '1', 'confirmed' ), 'Scene publication failed' );
	$adventure_id = $success( ps_pen_paper_save_adventure( $source, 0, 'Round adventure', (string) $scene_id, '0' ), 'Adventure fixture failed' );
	$success( ps_pen_paper_save_adventure_edge( $source, $adventure_id, array( 'from_scene' => (string) $scene_id, 'choice' => 'Finish',
		'expression' => '', 'attribute' => '', 'threshold' => '0', 'success_scene' => '0', 'failure_scene' => '0',
		'cost_resource' => $field, 'cost_amount' => '2', 'cost_timing' => 'attempt' ), '1' ), 'Adventure edge failed' );
	$success( ps_pen_paper_publish_adventure( $source, $adventure_id, '2', 'confirmed' ), 'Adventure publication failed' );
	$success( ps_pen_paper_save_play_options( $source, array( 'guided', 'solo', 'group' ), true, '0' ), 'Mode options failed' );
	$token = wp_generate_uuid4();
	$a = $success( ps_pen_paper_create_play_round( $source, 'guided', 0, $token, '1' ), 'First guided round failed' ); $parents[] = $a;
	$check( $a === ps_pen_paper_create_play_round( $source, 'guided', 0, $token, '1' ), 'Repeated create produced a second round.' );
	$check( is_wp_error( ps_pen_paper_create_play_round( $source, 'solo', $adventure_id, $token, '1' ) ), 'Changed create payload reused an action token.' );
	$b = $success( ps_pen_paper_create_play_round( $source, 'guided', 0, wp_generate_uuid4(), '1' ), 'Second guided round failed' ); $parents[] = $b;
	$round_a = ps_pen_paper_get_campaign( $a ); $round_b = ps_pen_paper_get_campaign( $b );
	$check( ! ps_pen_paper_play_round( $source_id ) && 'lobby' === ps_pen_paper_play_round( $a )['status'] &&
		! ps_pen_paper_game_round( $a )['active'] && ! get_post_meta( $a, '_pspp_player', false ), 'Creating a round started play or copied players.' );
	$check( ! in_array( $a, wp_list_pluck( ps_pen_paper_get_campaigns(), 'ID' ), true ), 'Runtime appeared as a prepared campaign.' );
	$check( is_wp_error( ps_pen_paper_change_play_round( $round_a, 'start', '1' ) ), 'Empty lobby started.' );
	foreach ( array( 'player' => $round_a, 'other' => $round_b ) as $owner => $round ) {
		wp_set_current_user( $users[ $owner ] );
		$success( ps_pen_paper_change_play_round( $round, 'join', '1', $characters[ $owner ], '1' ), 'Joining own character failed' );
		$check( ! ps_pen_paper_can_manage_campaign( $round ), 'Joining granted GM rights.' );
	}
	wp_set_current_user( $users['gm'] );
	foreach ( array( $round_a, $round_b ) as $round ) { $success( ps_pen_paper_change_play_round( $round, 'start', '2' ), 'Starting guided round failed' ); }
	wp_set_current_user( $users['player'] );
	$alias_id = ps_pen_paper_play_round( $a )['participants'][0]['character_id'];
	$check( isset( ps_pen_paper_gear_state( $alias_id )['active']['items'][ $item_uuid ] ) && '' === get_post_meta( $alias_id, '_pspp_gear', true ), 'Joining copied inventory instead of using the same item instance.' );
	$hero_state = ps_pen_paper_character_state( $round_a, ps_pen_paper_record_post( $alias_id ), false );
	$use_input = array( 'instance_id' => $item_uuid, 'character_revision' => ps_pen_paper_item_use_character_revision( $hero_state ),
		'rules_revision' => (string) ps_pen_paper_get_rules( $a )['revision'], 'round_revision' => (string) ps_pen_paper_game_round( $a )['revision'],
		'policy_revision' => (string) ps_pen_paper_item_action_policy( $round_a )['revision'] );
	$success( ps_pen_paper_use_item( $round_a, $alias_id, 'use_item', $use_input, (string) ps_pen_paper_gear_state( $alias_id )['revision'], 'game' ), 'Shared inventory consume failed' );
	$check( 11 === ps_pen_paper_raw_character_state( $characters['player'] )['active']['values'][ $field ]['current'] &&
		! isset( ps_pen_paper_gear_state( $characters['player'] )['active']['items'][ $item_uuid ] ) &&
		'' === get_post_meta( $alias_id, '_pspp_gear', true ) && is_wp_error( ps_pen_paper_use_item( $round_a, $alias_id, 'use_item', $use_input, '1', 'game' ) ),
		'Consume failed to persist to the profile or replayed the item.' );
	wp_set_current_user( $users['gm'] );
	$map_a = ps_pen_paper_map_selection( $round_a ); $map_b = ps_pen_paper_map_selection( $round_b );
	$check( $map_a['scene_id'] && $map_b['scene_id'] && $map_a['scene_id'] !== $map_b['scene_id'] &&
		0 === ps_pen_paper_map_selection( $source )['scene_id'], 'Guided rounds shared their scene or changed the template.' );
	$success( ps_pen_paper_control_game_round( $round_a, 'advance_game_round', (string) ps_pen_paper_game_round( $a )['revision'] ), 'Advancing one round failed' );
	$check( ps_pen_paper_game_round( $a )['round'] === ps_pen_paper_game_round( $b )['round'] + 1 &&
		0 === ps_pen_paper_game_round( $source_id )['round'], 'Turn counter leaked into another round.' );
	wp_set_current_user( $users['outsider'] );
	$check( ! ps_pen_paper_can_view_campaign( $round_a ) &&
		is_wp_error( ps_pen_paper_change_play_round( $round_a, 'join', '3', $characters['player'], '1' ) ), 'Outsider joined or read another round.' );
	wp_set_current_user( $users['player'] );
	$check( ! ps_pen_paper_can_view_campaign( $round_b ) && is_wp_error( ps_pen_paper_create_play_round( $source, 'guided', 0, wp_generate_uuid4(), '1' ) ), 'Player accessed another group or started guided play.' );
	$success( ps_pen_paper_change_play_round( $round_a, 'leave', (string) ps_pen_paper_play_round( $a )['revision'] ), 'Leaving guided round failed' );
	$check( ! ps_pen_paper_can_view_campaign( $round_a ) && ps_pen_paper_record_post( $characters['player'] ), 'Leaving retained access or deleted the base character.' );
	wp_set_current_user( $users['gm'] );
	$success( ps_pen_paper_change_play_round( $round_b, 'complete', (string) ps_pen_paper_play_round( $b )['revision'] ), 'Closing second guided round failed' );
	$export = $success( ps_pen_paper_export_campaign( $b, 'complete' ), 'Round backup failed' );
	$parsed = $success( ps_pen_paper_parse_import( json_encode( $export, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ), 'Round backup parse failed' );
	$options = array( 'operation' => 'copy', 'target_id' => 0, 'universe_mode' => 'copy', 'universe_id' => 0, 'users' => array(), 'bases' => array() );
	foreach ( $parsed['users'] as $user_id => $requirement ) { $options['users'][ $user_id ] = $user_id; }
	$map = array(); $next_id = 1000000;
	foreach ( array_keys( $parsed['records'] ) as $record_id ) { $map[ $record_id ] = $next_id++; }
	$prepared = $success( ps_pen_paper_prepare_import( $parsed, $options, $map, true ), 'Round backup preparation failed' );
	$keys = array_column( $prepared[ $b ]['meta'], 'key' );
	$check( ! in_array( '_pspp_play_round', $keys, true ) && ! in_array( '_pspp_play_token', $keys, true ) &&
		in_array( '_pspp_import_archive', $keys, true ), 'Imported runtime activated old campaign authority or start tokens.' );
	wp_set_current_user( $users['player'] );
	foreach ( array( 'solo', 'group' ) as $mode ) {
		$id = $success( ps_pen_paper_create_play_round( $source, $mode, $adventure_id, wp_generate_uuid4(), (string) ps_pen_paper_play_options( $source_id )['revision'] ), 'Automatic round creation failed' ); $parents[] = $id;
		$round = ps_pen_paper_get_campaign( $id );
		$check( ! ps_pen_paper_can_manage_campaign( $round ), 'Automatic host obtained GM rights.' );
		$success( ps_pen_paper_change_play_round( $round, 'join', '1', $characters['player'], (string) ps_pen_paper_raw_character_state( $characters['player'] )['revision'] ), 'Automatic character selection failed' );
		if ( 'group' === $mode ) {
			wp_set_current_user( $users['other'] );
			$success( ps_pen_paper_change_play_round( $round, 'join', '2', $characters['other'], '1' ), 'Second group participant failed' );
			wp_set_current_user( $users['player'] );
		} else {
			wp_set_current_user( $users['gm'] );
			$success( ps_pen_paper_save_play_options( $source, array( 'guided', 'solo', 'group' ), false, '1' ), 'Disabling new player starts failed' );
			wp_set_current_user( $users['player'] );
			$check( is_wp_error( ps_pen_paper_change_play_round( $round, 'start', '2' ) ) && ! ps_pen_paper_play_round( $id )['run_id'], 'A waiting player bypassed revoked start permission.' );
			wp_set_current_user( $users['gm'] );
			$success( ps_pen_paper_save_play_options( $source, array( 'guided', 'solo', 'group' ), true, '2' ), 'Re-enabling player starts failed' );
			wp_set_current_user( $users['player'] );
		}
		$success( ps_pen_paper_change_play_round( $round, 'start', (string) ps_pen_paper_play_round( $id )['revision'] ), 'Automatic start failed' );
		$state = ps_pen_paper_play_round( $id );
		$check( 'active' === $state['status'] && $state['run_id'] && $state['adventure_id'] !== $adventure_id, 'Automatic round did not start a separate snapshot.' );
		$before_base = ps_pen_paper_raw_character_state( $characters['player'] );
		if ( 'solo' === $mode ) {
			$run = get_post_meta( $state['run_id'], '_pspp_adventure_run', true ); $edge = $run['snapshot']['edges'][0]['id'];
			$success( ps_pen_paper_advance_adventure_run( $round, $state['run_id'], $edge, '1' ), 'Solo decision failed' );
			$check( is_wp_error( ps_pen_paper_advance_adventure_run( $round, $state['run_id'], $edge, '1' ) ), 'Solo decision replay was accepted.' );
		} else {
			$from = $state['participants'][0]['character_id']; $to = $state['participants'][1]['character_id'];
			$policy_revision = (string) ps_pen_paper_trade_policy( $round )['policy']['revision'];
			$gift = $success( ps_pen_paper_offer_trade( $round, $from, $to, 'gift', $tokens[0], '0',
				(string) ps_pen_paper_gear_state( $from )['revision'], $policy_revision, wp_generate_uuid4() ), 'Shared inventory gift offer failed' );
			wp_set_current_user( $users['other'] );
			$success( ps_pen_paper_change_trade( $round, $gift, 'accept', '1', 'confirmed' ), 'Shared gift acceptance failed' );
			$check( ! isset( ps_pen_paper_gear_state( $characters['player'] )['active']['items'][ $tokens[0] ] ) &&
				isset( ps_pen_paper_gear_state( $characters['other'] )['active']['items'][ $tokens[0] ] ), 'Trading copied an item or missed the recipient profile.' );
			wp_set_current_user( $users['player'] );
			$open_gift = $success( ps_pen_paper_offer_trade( $round, $from, $to, 'gift', $tokens[1], '0',
				(string) ps_pen_paper_gear_state( $from )['revision'], $policy_revision, wp_generate_uuid4() ), 'Second gift offer failed' );
			$check( in_array( $tokens[1], ps_pen_paper_equipment_reserved_items( $world, $characters['player'] ), true ), 'Profile did not see the round inventory reservation.' );
			$group = get_post_meta( $state['run_id'], '_pspp_adventure_group', true );
			$success( ps_pen_paper_write_adventure_group( $round, $state['run_id'], '1', 'propose',
				array( 'character_id' => $state['participants'][0]['character_id'], 'edge_id' => $group['snapshot']['edges'][0]['id'] ) ), 'Single-click group decision failed' );
			$group = get_post_meta( $state['run_id'], '_pspp_adventure_group', true );
			$check( 1 === count( $group['history'] ) && null === $group['proposal'] && 'completed' === $group['status'], 'Group action still needed a second execute click.' );
			$check( 10 === ps_pen_paper_raw_character_state( $characters['other'] )['active']['values'][ $field ]['current'], 'Executing player spent another participant resource.' );
			$check( 'cancelled' === get_post_meta( $open_gift, '_pspp_trade', true )['status'] &&
				! ps_pen_paper_equipment_reserved_items( $world, $characters['player'] ) &&
				isset( ps_pen_paper_gear_state( $characters['player'] )['active']['items'][ $tokens[1] ] ), 'Finishing play left an inventory reservation or consumed an unaccepted gift.' );
		}
		$check( 'completed' === ps_pen_paper_play_round( $id )['status'] && ! ps_pen_paper_game_round( $id )['active'] &&
			$before_base['active']['values'][ $field ]['current'] - 2 === ps_pen_paper_raw_character_state( $characters['player'] )['active']['values'][ $field ]['current'],
			'Completion, session stop or persistent profile resource cost failed.' );
	}
	$success( ps_pen_paper_change_play_round( $round_a, 'join', (string) ps_pen_paper_play_round( $a )['revision'],
		$characters['player'], (string) ps_pen_paper_raw_character_state( $characters['player'] )['revision'] ), 'Returning to paused round failed' );
	$success( ps_pen_paper_change_membership( $source_id, $users['player'], 'leave', '', (string) ps_pen_paper_membership_revision( $source_id )['revision'] ), 'Campaign departure failed' );
	$check( ! ps_pen_paper_play_participant( ps_pen_paper_play_round( $a ), $users['player'] ) &&
		! ps_pen_paper_can_view_campaign( $round_a ) && ps_pen_paper_record_post( $characters['player'] ), 'Campaign departure retained a live round participant or deleted the profile character.' );
	WP_CLI::success( 'Play round integration tests passed.' );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $parents as $parent ) {
		$children = get_posts( array( 'post_type' => ps_pen_paper_campaign_record_types(), 'post_status' => array( 'private', 'draft', 'trash' ),
			'post_parent' => $parent, 'posts_per_page' => -1 ) );
		foreach ( $children as $child ) { wp_delete_post( $child->ID, true ); }
		wp_delete_post( $parent, true );
	}
	foreach ( $users as $id ) { if ( is_multisite() ) { wpmu_delete_user( $id ); } else { wp_delete_user( $id ); } }
}
