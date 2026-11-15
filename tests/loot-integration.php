<?php
/** Run with WP-CLI against a disposable development site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'character', 'npc', 'event', 'item' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
ps_pen_paper_register_loot_types();
$original_user = get_current_user_id(); $users = array(); $parents = array(); $tokens = array();
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
	$char2 = ps_pen_paper_save_character( $campaign->ID, 0, $users['player'], array_merge( $character_input, array( 'name' => 'Second recipient' ) ), '0', '1' );
	$child_row = array_merge( $row, array( 'label' => 'Nested', 'quantity' => '1', 'money' => '3' ) );
	$child_input = array( 'name' => 'Nested table', 'die' => '6', 'rows' => array( $child_row ) );
	$child = ps_pen_paper_save_loot_table( $campaign, 0, $child_input, '0' );
	$group_row = array_merge( $row, array( 'label' => 'Group', 'quantity' => '2', 'money' => '4', 'items' => $item . ':1', 'nested_id' => (string) $child ) );
	$group_input = array( 'name' => 'Group table', 'die' => '6', 'rows' => array( $group_row ) );
	$group = ps_pen_paper_save_loot_table( $campaign, 0, $group_input, '0' );
	$check( is_int( $char2 ) && is_int( $child ) && is_int( $group ), 'Grouped/nested fixtures failed.' );
	$cycle = $child_input; $cycle['rows'][0]['items'] = ''; $cycle['rows'][0]['nested_id'] = (string) $group;
	$check( is_wp_error( ps_pen_paper_save_loot_table( $campaign, $child, $cycle, '1' ) ), 'Nested cycle accepted.' );
	$group_run = ps_pen_paper_roll_loot( $campaign, $group, '1', '1', 'Grouped split', wp_generate_uuid4() );
	$group_state = ps_pen_paper_get_loot_run( $campaign, $group_run );
	$components = ps_pen_paper_loot_components( $group_state['proposal'] );
	$check( ps_pen_paper_valid_loot_run( $group_state ) && array( 2, 1, 1 ) === array_column( $components['items'], 'quantity' ) && 7 === $components['money'], 'Nested/grouped rolls did not preserve all rewards.' );
	$split = array(
		$char => array( 'gear_revision' => '1', 'wallet_revision' => '1', 'currency_revision' => '2', 'items' => array( '1', '1', '0' ), 'money' => '2' ),
		$char2 => array( 'gear_revision' => '0', 'wallet_revision' => '0', 'currency_revision' => '2', 'items' => array( '1', '0', '1' ), 'money' => '5' ),
	);
	$bad_split = $split; $bad_split[ $char2 ]['items'][2] = '0';
	$check( is_wp_error( ps_pen_paper_award_loot_split( $campaign, $group_run, $bad_split, 'confirmed' ) ), 'Incomplete split accepted.' );
	$fail = function ( $data, $postarr ) {
		if ( isset( $postarr['ID'] ) && 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) {
			$event = get_post_meta( $postarr['ID'], '_pspp_event', true );
			if ( isset( $event['kind'] ) && 'loot_awarded' === $event['kind'] ) { $data['post_status'] = 'draft'; }
		}
		return $data;
	};
	add_filter( 'wp_insert_post_data', $fail, 10, 2 );
	try {
		$check( is_wp_error( ps_pen_paper_award_loot_split( $campaign, $group_run, $split, 'confirmed' ) ) &&
			$group_state === ps_pen_paper_get_loot_run( $campaign, $group_run ) && '' === get_post_meta( $char2, '_pspp_gear', true ) &&
			'' === get_post_meta( $char2, '_pspp_wallet', true ) && $wallet === ps_pen_paper_wallet_context( $campaign, $char )['wallet'], 'Split failure left partial awards.' );
	} finally { remove_filter( 'wp_insert_post_data', $fail, 10 ); }
	$check( true === ps_pen_paper_award_loot_split( $campaign, $group_run, $split, 'confirmed' ) &&
		4 === count( ps_pen_paper_gear_state( $char )['active']['items'] ) && 2 === count( ps_pen_paper_gear_state( $char2 )['active']['items'] ) &&
		5 === ps_pen_paper_wallet_context( $campaign, $char2 )['wallet']['balance'] &&
		is_wp_error( ps_pen_paper_award_loot_split( $campaign, $group_run, $split, 'confirmed' ) ), 'Atomic split or replay protection failed.' );
	$group_table = $group_state['proposal']['table'];
	$reward = array( 'timing' => 'decision', 'authorizer_id' => $users['gm'], 'source' => array( 'kind' => 'table', 'id' => $group, 'table' => $group_table ) );
	$check( ps_pen_paper_valid_adventure_reward( $reward ) && array( 'items' => 4, 'money' => 7 ) === ps_pen_paper_loot_snapshot_limits( $group_table ) &&
		3 === count( ps_pen_paper_adventure_reward_items( $reward ) ), 'Adventure recursive maxima or snapshot traversal failed.' );
	$mapped = ps_pen_paper_loot_snapshot_map( $group_table, function ( &$instance ) { $instance['source_id'] += 10000; return true; },
		function ( &$currency ) { $currency['scope_id'] += 10000; return true; },
		function ( &$nested_id ) { $nested_id += 10000; return true; } );
	$check( ! is_wp_error( $mapped ) && $group_table !== $mapped && array( 'items' => 4, 'money' => 7 ) === ps_pen_paper_loot_snapshot_limits( $mapped ) &&
		$child + 10000 === $mapped['table']['rows'][0]['nested']['table_id'] &&
		count( ps_pen_paper_loot_snapshot_items( $mapped ) ) === 3, 'Recursive item/currency/table mapping failed.' );
	$rejected_map = ps_pen_paper_loot_snapshot_map( $group_table, function ( &$instance ) { $instance['source_id'] = 0; return true; } );
	$check( is_wp_error( $rejected_map ) && $group_table === $group_state['proposal']['table'], 'Invalid mapping succeeded or mutated the source snapshot.' );
	wp_get_current_user()->add_cap( 'ps_pen_paper_create_campaigns' );
	$pending_run = ps_pen_paper_roll_loot( $campaign, $group, '1', '1', 'Pending nested restoration', wp_generate_uuid4() );
	$check( is_int( $pending_run ), 'Pending nested restoration fixture failed.' );
	$source_awarded = ps_pen_paper_get_loot_run( $campaign, $group_run );
	$source_pending = ps_pen_paper_get_loot_run( $campaign, $pending_run );
	$source_wallets = array(); $source_counts = array();
	foreach ( array( $char, $char2 ) as $recipient ) {
		$source_wallets[] = ps_pen_paper_wallet_context( $campaign, $recipient )['wallet']['balance'];
		$source_counts[] = count( ps_pen_paper_gear_state( $recipient )['active']['items'] );
	}
	$archive = ps_pen_paper_export_campaign( $campaign->ID, 'local' );
	$check( ! is_wp_error( $archive ), 'Nested loot export failed.' );
	$json = wp_json_encode( $archive, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	$parsed = ps_pen_paper_parse_import( $json );
	$check( ! is_wp_error( $parsed ), 'Nested loot archive did not parse.' );
	$token = ps_pen_paper_create_import_preview( $json );
	$check( is_string( $token ), 'Nested loot preview failed: ' . ( is_wp_error( $token ) ? $token->get_error_message() : '' ) ); $tokens[] = $token;
	$options = array( 'operation' => 'copy', 'target_id' => 0, 'universe_mode' => 'none', 'universe_id' => 0, 'users' => array(), 'bases' => array() );
	foreach ( $parsed['users'] as $owner => $requirement ) { $options['users'][ $owner ] = $owner === $users['gm'] ? $users['world'] : $owner; }
	$preview = ps_pen_paper_get_import_preview( $token );
	$check( ! is_wp_error( $preview ), 'Nested loot preview disappeared.' ); $preview['options'] = $options;
	$check( set_transient( ps_pen_paper_import_preview_key( $token ), $preview, $preview['expires'] - time() ), 'Nested loot preview configuration failed.' );
	$plan = ps_pen_paper_import_configuration( $parsed, $options );
	$check( ! is_wp_error( $plan ), 'Nested loot import configuration failed.' );
	$fingerprint = ps_pen_paper_restore_fingerprint( $parsed, $options, $plan );
	$restored = ps_pen_paper_restore_campaign( $token, $fingerprint, false, 'private' );
	$check( ! is_wp_error( $restored ), 'Nested loot restoration failed: ' . ( is_wp_error( $restored ) ? $restored->get_error_message() : '' ) );
	$parents[] = $restored['campaign_id']; $restored_campaign = ps_pen_paper_get_campaign( $restored['campaign_id'] );
	$restored_runs = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_loot_run', 'post_status' => 'private',
		'post_parent' => $restored_campaign->ID, 'posts_per_page' => -1 ) ) as $copied_run ) {
		$state = get_post_meta( $copied_run->ID, '_pspp_loot_run', true );
		$check( ps_pen_paper_valid_loot_run( $state ), 'Restored loot run is invalid.' );
		$restored_runs[ $state['proposal']['token'] ] = array( 'id' => $copied_run->ID, 'state' => $state );
	}
	$copied_awarded = $restored_runs[ $source_awarded['proposal']['token'] ];
	$copied_pending = $restored_runs[ $source_pending['proposal']['token'] ];
	$node = $copied_awarded['state']['proposal'];
	$check( $node['table_id'] !== $group && $node['nested']['table_id'] !== $child &&
		$node['nested']['table_id'] === $node['table']['table']['rows'][0]['nested']['table_id'] &&
		$source_awarded['proposal']['roll'] === $node['roll'] &&
		$source_awarded['proposal']['nested']['roll'] === $node['nested']['roll'] &&
		$users['world'] === $node['actor_id'] && $users['world'] === $copied_awarded['state']['award']['actor_id'],
		'Restoration lost nested references, original dice or mapped actors.' );
	foreach ( ps_pen_paper_loot_snapshot_items( $node['table'] ) as $snapshot_item ) {
		$check( $snapshot_item['scope_id'] === $restored_campaign->ID, 'Restoration left an item-group or nested item in the original scope.' );
	}
	$check( 7 === ps_pen_paper_loot_components( $node )['money'] &&
		$restored_campaign->ID === $node['nested']['table']['table']['currency']['scope_id'], 'Nested currency or total changed during restoration.' );
	wp_set_current_user( $users['world'] );
	$check( ! is_wp_error( ps_pen_paper_get_loot_table( $restored_campaign, $node['table_id'] ) ) &&
		! is_wp_error( ps_pen_paper_get_events( $restored_campaign ) ), 'Restored catalog or split history cannot be read.' );
	$restored_split = array();
	foreach ( $copied_awarded['state']['award']['allocations'] as $index => $allocation ) {
		$recipient = $allocation['character_id'];
		$recipient_wallet = ps_pen_paper_wallet_context( $restored_campaign, $recipient );
		$recipient_gear = ps_pen_paper_gear_state( $recipient );
		$check( ! in_array( $recipient, array( $char, $char2 ), true ) && (int) get_post( $recipient )->post_parent === $restored_campaign->ID &&
			$source_wallets[ $index ] === $recipient_wallet['wallet']['balance'] &&
			$source_counts[ $index ] === count( $recipient_gear['active']['items'] ), 'Restoration retained old recipients or replayed an award.' );
		$restored_split[ $recipient ] = array( 'gear_revision' => (string) $recipient_gear['revision'],
			'wallet_revision' => (string) $recipient_wallet['wallet']['revision'], 'currency_revision' => (string) $recipient_wallet['currency']['revision'],
			'items' => array_map( 'strval', $allocation['items'] ), 'money' => (string) $allocation['money'] );
	}
	$check( is_wp_error( ps_pen_paper_award_loot_split( $restored_campaign, $copied_awarded['id'], $restored_split, 'confirmed' ) ) &&
		true === ps_pen_paper_award_loot_split( $restored_campaign, $copied_pending['id'], $restored_split, 'confirmed' ),
		'Restored completed loot paid again or a pending nested proposal could not be awarded.' );
	foreach ( array_keys( $restored_split ) as $index => $recipient ) {
		$check( $source_wallets[ $index ] + (int) $restored_split[ $recipient ]['money'] === ps_pen_paper_wallet_context( $restored_campaign, $recipient )['wallet']['balance'] &&
			$source_counts[ $index ] + array_sum( $restored_split[ $recipient ]['items'] ) === count( ps_pen_paper_gear_state( $recipient )['active']['items'] ),
			'Restored pending distribution did not pay the exact assigned amounts.' );
	}
	wp_set_current_user( $users['gm'] );
	$check( $source_awarded === ps_pen_paper_get_loot_run( $campaign, $group_run ) &&
		$source_pending === ps_pen_paper_get_loot_run( $campaign, $pending_run ) &&
		$archive['payload_sha256'] === ps_pen_paper_export_campaign( $campaign->ID, 'local' )['payload_sha256'], 'Restored play changed the original campaign.' );
	$alternatives = $group_table; $first = $alternatives['table']['rows'][0]; $first['to'] = 3;
	$second = $first; $second['from'] = 4; $second['to'] = 6; $second['quantity'] = '50'; $second['money'] = '0'; $second['items'] = array(); $second['nested'] = null;
	$alternatives['table']['rows'] = array( $first, $second );
	$check( array( 'items' => 50, 'money' => 7 ) === ps_pen_paper_loot_snapshot_limits( $alternatives ), 'Alternative row maxima were incorrectly summed.' );
	wp_set_current_user( $users['player'] );
	$character2 = ps_pen_paper_get_character( $campaign, $char2 );
	$reward_context = ps_pen_paper_adventure_reward_context( $campaign, $character2, $reward );
	$check( ! is_wp_error( $reward_context ), 'Adventure grouped reward context failed.' );
	$award_result = null;
	$award = function () use ( $campaign, $character2, $reward, $reward_context, &$award_result ) {
		return ps_pen_paper_with_character_locks( array( (int) $character2->ID ), function () use ( $campaign, $character2, $reward, $reward_context, &$award_result ) {
			return ps_pen_paper_atomic_event( $campaign->ID, function () use ( $campaign, $character2, $reward, $reward_context, &$award_result ) {
				$award_result = ps_pen_paper_write_adventure_reward( $campaign, $character2, $reward, $reward_context, 123 );
				return is_wp_error( $award_result ) ? $award_result : 0;
			} );
		} );
	};
	$gear_events = 0;
	$fail = function ( $data, $postarr ) use ( &$gear_events ) {
		if ( isset( $postarr['ID'] ) && 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) {
			$event = get_post_meta( $postarr['ID'], '_pspp_event', true );
			if ( isset( $event['kind'] ) && 'gear_saved' === $event['kind'] && 2 === ++$gear_events ) { $data['post_status'] = 'draft'; }
		}
		return $data;
	};
	add_filter( 'wp_insert_post_data', $fail, 10, 2 );
	try {
		$check( is_wp_error( $award() ) && $reward_context['gear'] === ps_pen_paper_gear_state( $char2 ) &&
			$reward_context['wallet']['wallet'] === ps_pen_paper_wallet_context( $campaign, $char2 )['wallet'], 'Adventure grouped grant failure left partial inventory or money.' );
	} finally { remove_filter( 'wp_insert_post_data', $fail, 10 ); }
	$check( true === $award() && ps_pen_paper_valid_adventure_reward_result( $reward, $award_result, $char2 ) &&
		3 === count( $award_result['component_ids'] ) && 4 === count( $award_result['item_ids'] ) &&
		12 === ps_pen_paper_wallet_context( $campaign, $char2 )['wallet']['balance'] &&
		6 === count( ps_pen_paper_gear_state( $char2 )['active']['items'] ), 'Adventure grouped/nested award failed.' );
	$damaged = $award_result; $damaged['component_ids'][1][] = $damaged['item_ids'][0];
	$check( ! ps_pen_paper_valid_adventure_reward_result( $reward, $damaged, $char2 ), 'Invalid per-component instance IDs accepted.' );
	$damaged = $award_result; $damaged['wallet']['after']['balance']--;
	$check( ! ps_pen_paper_valid_adventure_reward_result( $reward, $damaged, $char2 ), 'Incorrect combined money accepted.' );
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
	$universe_child = ps_pen_paper_save_loot_table( $universe, 0, $money_input, '0' );
	$check( is_int( $universe_child ), 'Universe child table could not be created.' );
	$universe_input = $money_input; $universe_input['name'] = 'Universe grouped loot';
	$universe_input['rows'][0] = array_merge( $universe_input['rows'][0], array( 'money' => '3', 'items' => '', 'nested_id' => (string) $universe_child ) );
	$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_author' => $users['world'],
		'post_title' => 'Universe loot frontend fixture', 'post_content' => '[ps_pen_paper]' ), true );
	$check( is_int( $page_id ), 'Universe loot page fixture failed.' ); $parents[] = $page_id;
	$old_post = $_POST; $old_request = $_REQUEST; $old_query = $GLOBALS['wp_query'];
	$old_method = $_SERVER['REQUEST_METHOD'] ?? null; $redirected = false;
	$redirect = static function ( $url ) { throw new RuntimeException( 'loot-form-redirect:' . $url ); };
	$die_handler = static function () {
		return static function ( $message ) { throw new RuntimeException( 'loot-form-failure:' . $message ); };
	};
	add_filter( 'wp_redirect', $redirect, 1 ); add_filter( 'wp_die_handler', $die_handler, 1000 );
	try {
		$_POST = array( 'action' => 'ps_pen_paper', 'pspp_operation' => 'save_loot_table',
			'pspp_frontend_page' => (string) $page_id, 'campaign_id' => (string) $u,
			'pspp_scope_epoch' => ps_pen_paper_scope_epoch( $u ), '_wpnonce' => wp_create_nonce( 'pspp_campaign_' . $u ),
			'loot_table_id' => '0', 'loot_revision' => '0', 'loot_complete' => 'complete', 'loot' => $universe_input );
		$_REQUEST = $_POST; $_SERVER['REQUEST_METHOD'] = 'POST';
		$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $page_id ) );
		try { ps_pen_paper_handle_frontend_action(); }
		catch ( RuntimeException $error ) {
			if ( 0 !== strpos( $error->getMessage(), 'loot-form-redirect:' ) ) { throw $error; }
			$redirected = false !== strpos( $error->getMessage(), 'pspp_universe=' . $u );
		}
	} finally {
		remove_filter( 'wp_redirect', $redirect, 1 ); remove_filter( 'wp_die_handler', $die_handler, 1000 );
		$_POST = $old_post; $_REQUEST = $old_request; $GLOBALS['wp_query'] = $old_query;
		if ( null === $old_method ) { unset( $_SERVER['REQUEST_METHOD'] ); } else { $_SERVER['REQUEST_METHOD'] = $old_method; }
	}
	$universe_tables = ps_pen_paper_loot_tables( $universe );
	$check( $redirected && 2 === count( $universe_tables ), 'Universe frontend save did not persist and return to its own administration.' );
	$universe_table = array_key_last( $universe_tables ); $universe_before = $universe_tables[ $universe_table ];
	$check( is_wp_error( ps_pen_paper_roll_loot( $universe, $universe_table, '1', '1', 'Not a campaign', wp_generate_uuid4() ) ),
		'Universe catalogue created a campaign reward run.' );
	wp_set_current_user( $users['gm'] );
	$check( isset( ps_pen_paper_loot_tables( $shared )[ $universe_table ] ) &&
		is_wp_error( ps_pen_paper_get_loot_table( $campaign, $universe_table ) ) &&
		is_wp_error( ps_pen_paper_save_loot_table( $shared, $universe_table, $money_input, '1' ) ) &&
		is_wp_error( ps_pen_paper_save_loot_table( $universe, $universe_table, $money_input, '1' ) ) &&
		$universe_before === get_post_meta( $universe_table, '_pspp_loot_table', true ),
		'Catalogue leaked to an unrelated campaign or campaign GM overwrote a universe table.' );
	$old_get = $_GET;
	try {
		$_GET['pspp_loot_table'] = (string) $universe_table;
		ob_start(); ps_pen_paper_render_loot( $shared, $page_id ); $catalogue_html = ob_get_clean();
	} finally { $_GET = $old_get; }
	$check( false !== strpos( $catalogue_html, 'name="pspp_operation" value="roll_loot"' ) &&
		false === strpos( $catalogue_html, 'name="pspp_operation" value="save_loot_table"' ),
		'Inherited catalogue did not offer rolling or exposed an editable shared template.' );
	$universe_run = ps_pen_paper_roll_loot( $shared, $universe_table, '1', '1', 'Universe catalogue roll', wp_generate_uuid4() );
	$check( is_int( $universe_run ), 'Connected campaign could not use universe loot.' );
	$universe_proposal = ps_pen_paper_get_loot_run( $shared, $universe_run );
	$check( 5 === ps_pen_paper_loot_components( $universe_proposal['proposal'] )['money'], 'Universe nested table lost a reward.' );
	wp_set_current_user( $users['world'] );
	$universe_input['rows'][0]['money'] = '9';
	$check( $universe_table === ps_pen_paper_save_loot_table( $universe, $universe_table, $universe_input, '1' ) &&
		$universe_proposal === ps_pen_paper_get_loot_run( $shared, $universe_run ),
		'Universe table edit rewrote an existing campaign proposal.' );
	$archive = ps_pen_paper_export_campaign( $c, 'complete' );
	$check( ! is_wp_error( $archive ), 'Universe catalogue export failed.' );
	$parsed = ps_pen_paper_parse_import( wp_json_encode( $archive, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	$check( ! is_wp_error( $parsed ) && isset( $parsed['records'][ $universe_child ], $parsed['records'][ $universe_table ] ),
		'Complete native archive omitted or rejected universe tables.' );
	$options = array( 'operation' => 'copy', 'target_id' => 0, 'universe_mode' => 'copy', 'universe_id' => 0, 'users' => array(), 'bases' => array() );
	foreach ( $parsed['users'] as $owner => $requirement ) { $options['users'][ $owner ] = $owner; }
	$map = array(); foreach ( array_keys( $parsed['records'] ) as $source_id ) { $map[ $source_id ] = $source_id + 1000000; }
	$prepared = ps_pen_paper_prepare_import( $parsed, $options, $map, false );
	$check( ! is_wp_error( $prepared ), 'Universe table and campaign proposal import mapping failed: ' .
		( is_wp_error( $prepared ) ? $prepared->get_error_message() : '' ) );
	$mapped_run = null;
	foreach ( $prepared[ $universe_run ]['meta'] as $entry ) { if ( '_pspp_loot_run' === $entry['key'] ) { $mapped_run = $entry['value']; } }
	$check( ps_pen_paper_valid_loot_run( $mapped_run ) && $map[ $universe_table ] === $mapped_run['proposal']['table_id'] &&
		$map[ $universe_child ] === $mapped_run['proposal']['nested']['table_id'] &&
		$map[ $u ] === $mapped_run['proposal']['table']['table']['currency']['scope_id'] &&
		5 === ps_pen_paper_loot_components( $mapped_run['proposal'] )['money'], 'Restored universe references or frozen rewards changed.' );
	wp_get_current_user()->add_cap( 'ps_pen_paper_import_campaigns' );
	$token = ps_pen_paper_create_import_preview( wp_json_encode( $archive, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	$check( is_string( $token ), 'Universe catalogue preview failed: ' . ( is_wp_error( $token ) ? $token->get_error_message() : '' ) ); $tokens[] = $token;
	$preview = ps_pen_paper_get_import_preview( $token );
	$check( ! is_wp_error( $preview ), 'Universe catalogue preview disappeared.' ); $preview['options'] = $options;
	$check( set_transient( ps_pen_paper_import_preview_key( $token ), $preview, $preview['expires'] - time() ), 'Universe preview configuration failed.' );
	$plan = ps_pen_paper_import_configuration( $parsed, $options );
	$check( ! is_wp_error( $plan ), 'Universe restoration configuration failed.' );
	$restored = ps_pen_paper_restore_campaign( $token, ps_pen_paper_restore_fingerprint( $parsed, $options, $plan ), false, 'private' );
	$check( ! is_wp_error( $restored ), 'Universe catalogue restoration failed: ' .
		( is_wp_error( $restored ) ? $restored->get_error_message() : '' ) );
	$parents[] = $restored['campaign_id']; $restored_campaign = ps_pen_paper_get_campaign( $restored['campaign_id'] );
	$restored_link = ps_pen_paper_get_universe_link( $restored_campaign->ID );
	$check( ! is_wp_error( $restored_link ) && $restored_link['universe_id'] > 0 && $u !== $restored_link['universe_id'],
		'Restoration did not create an independent universe.' ); $parents[] = $restored_link['universe_id'];
	$copied = null; $copied_id = 0;
	foreach ( get_posts( array( 'post_type' => 'pspp_loot_run', 'post_status' => 'private', 'post_parent' => $restored_campaign->ID,
		'posts_per_page' => -1 ) ) as $copied_run ) {
		$state = ps_pen_paper_get_loot_run( $restored_campaign, $copied_run->ID );
		$check( ! is_wp_error( $state ), 'Restored universe proposal is unreadable.' );
		if ( $state['proposal']['token'] === $universe_proposal['proposal']['token'] ) { $copied = $state; $copied_id = $copied_run->ID; }
	}
	$check( null !== $copied && $universe_table !== $copied['proposal']['table_id'] &&
		(int) get_post_field( 'post_parent', $copied['proposal']['table_id'] ) === $restored_link['universe_id'] &&
		! is_wp_error( ps_pen_paper_get_loot_table( $restored_campaign, $copied['proposal']['table_id'] ) ) &&
		5 === ps_pen_paper_loot_components( $copied['proposal'] )['money'], 'Restored campaign cannot use its copied universe catalogue.' );
	$recipients = ps_pen_paper_get_characters( $restored_campaign );
	$check( 1 === count( $recipients ), 'Restored shared recipient is ambiguous.' ); $recipient = reset( $recipients );
	$check( true === ps_pen_paper_award_loot_split( $restored_campaign, $copied_id, array( $recipient->ID => array(
		'gear_revision' => '0', 'wallet_revision' => '1', 'currency_revision' => '2', 'items' => array(), 'money' => '5' ) ), 'confirmed' ) &&
		7 === ps_pen_paper_wallet_context( $restored_campaign, $recipient->ID )['wallet']['balance'] &&
		2 === get_post_meta( $base, '_pspp_wallet', true )['balance'] && $universe_proposal === ps_pen_paper_get_loot_run( $shared, $universe_run ),
		'Restored universe reward altered the source campaign or paid the wrong amount.' );
	$check( true === ps_pen_paper_award_loot_split( $shared, $universe_run, array( $alias => array(
		'gear_revision' => '0', 'wallet_revision' => '1', 'currency_revision' => '2', 'items' => array(), 'money' => '5' ) ), 'confirmed' ) &&
		7 === get_post_meta( $base, '_pspp_wallet', true )['balance'], 'Universe reward did not reach the actual shared wallet.' );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_loot_tables( $universe ) ) && is_wp_error( ps_pen_paper_get_loot_table( $shared, $universe_table ) ),
		'Player accessed the private universe catalogue.' );
	WP_CLI::success( 'Campaign and universe tables, central frontend save, inherited read-only use, nested native mapping, frozen rewards, shared-account authority, private history and atomic rollback passed.' );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $tokens as $token ) { delete_transient( ps_pen_paper_import_preview_key( $token ) ); }
	foreach ( $parents as $parent ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_loot_table', 'pspp_loot_run', 'pspp_character', 'pspp_npc', 'pspp_item', 'pspp_event' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $parent, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $child ) { wp_delete_post( $child, true ); }
		wp_delete_post( $parent, true );
	}
	foreach ( $users as $user ) { is_multisite() ? wpmu_delete_user( $user ) : wp_delete_user( $user ); }
}
