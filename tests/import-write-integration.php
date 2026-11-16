<?php
/**
 * Run serially with WP-CLI --skip-plugins --skip-themes on a development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'character', 'event', 'roll', 'scene', 'asset', 'folio', 'npc', 'item' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
ps_pen_paper_register_adventure_types(); ps_pen_paper_register_loot_types(); ps_pen_paper_register_trade_type();
global $wpdb;
$original_user = get_current_user_id(); $users = array(); $tokens = array(); $image_path = null; $pages = array(); $presence_keys = array();
$original_cookie = isset( $_COOKIE[LOGGED_IN_COOKIE] ) ? $_COOKIE[LOGGED_IN_COOKIE] : null;
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$assert_result = function ( $result, $message ) use ( $check ) {
	$check( ! is_wp_error( $result ), $message . ( is_wp_error( $result ) ? ': ' . $result->get_error_message() : '' ) );
	return $result;
};
$post = function ( $type, $parent, $author, $title ) use ( $assert_result ) {
	return $assert_result( wp_insert_post( array( 'post_type' => $type, 'post_status' => 'private', 'post_parent' => $parent,
		'post_author' => $author, 'post_title' => $title ), true ), 'Fixture post failed' );
};
$encode = function ( $archive ) {
	$archive['payload_sha256'] = hash( 'sha256', json_encode( $archive['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	return json_encode( $archive, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
};
$restore_world = 0;
$preview = function ( $json, $operation, $target = 0, $world_mode = 'bind', $world = 0, $bases = array(), $user_map = array() ) use ( &$tokens, &$users, &$restore_world, $assert_result, $check ) {
	if ( 'bind' === $world_mode && ! $world ) { $world = $restore_world; }
	$parsed = $assert_result( ps_pen_paper_parse_import( $json ), 'Parse failed' );
	$token = $assert_result( ps_pen_paper_create_import_preview( $json ), 'Preview failed' ); $tokens[] = $token;
	$options = array( 'operation' => $operation, 'target_id' => $target, 'universe_mode' => $world_mode, 'universe_id' => $world, 'users' => array(), 'bases' => $bases );
	foreach ( $parsed['users'] as $id => $required ) { $options['users'][ $id ] = $id === $users['gm'] ? $users['new_gm'] : $id; }
	$options['users'] = array_replace( $options['users'], $user_map );
	$stored = $assert_result( ps_pen_paper_get_import_preview( $token ), 'Private preview disappeared' ); $stored['options'] = $options;
	$check( set_transient( ps_pen_paper_import_preview_key( $token ), $stored, $stored['expires'] - time() ), 'Configure failed.' );
	$plan = $assert_result( ps_pen_paper_import_configuration( $parsed, $options ), 'Plan failed' );
	return array( $token, ps_pen_paper_restore_fingerprint( $parsed, $options, $plan ), $parsed, $options, $plan );
};
try {
	foreach ( array( 'gm', 'player', 'old_owner', 'new_gm', 'new_player' ) as $role ) {
		$users[ $role ] = $assert_result( wp_insert_user( array( 'user_login' => 'pspp-import-write-' . wp_generate_password( 12, false ),
			'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) ), 'Fixture account failed' );
	}
	get_userdata( $users['gm'] )->add_cap( 'ps_pen_paper_create_campaigns' );
	wp_set_current_user( $users['gm'] );
	$restore_world = $assert_result( ps_pen_paper_save_universe( 0, array( 'name' => 'Explicit legacy restore destination', 'description' => '' ), '0' ), 'Restore universe failed' );
	$source = $post( 'pspp_campaign', 0, $users['gm'], 'Restore source' );
	add_post_meta( $source, '_pspp_gm', (string) $users['gm'] ); add_post_meta( $source, '_pspp_player', (string) $users['player'] );
	$rules = $assert_result( ps_pen_paper_save_rules( $source, array( 'name' => 'Flexible restore rules', 'description' => '', 'dice' => array( '6' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Mut', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '10', 'start' => '2' ),
	) ), '0' ), 'Rules fixture failed' );
	$character = $assert_result( ps_pen_paper_save_character( $source, 0, $users['player'],
		array( 'name' => 'Restore hero', 'values' => array( $rules['fields'][0]['id'] => '2' ) ), '0', '1' ), 'Character fixture failed' );
	$assert_result( ps_pen_paper_save_access( $source, array( 'visibility' => 'teaser', 'admission' => 'approval', 'teaser' => 'Deliberate public teaser' ), '0' ), 'Access fixture failed' );
	add_post_meta( $source, '_pspp_booking_links', array( 'revision' => 1, 'events' => array( 123 ) ) );
	$archive = $assert_result( ps_pen_paper_export_campaign( $source, 'local' ), 'Export fixture failed' );
	$json = $encode( $archive );
	$copy = $preview( $json, 'copy' );
	$without_universe = $copy[3]; $without_universe['universe_mode'] = 'none'; $without_universe['universe_id'] = 0;
	$check( ps_pen_paper_import_configuration( $copy[2], $without_universe )['conflicts'], 'Legacy archive could be restored without an explicit universe.' );
	$result = $assert_result( ps_pen_paper_restore_campaign( $copy[0], $copy[1], false, 'private' ), 'New campaign restore failed' );
	$restored = ps_pen_paper_get_campaign( $result['campaign_id'] );
	$check( array() === ps_pen_paper_booking_links( $restored->ID )['events'], 'Imported campaign adopted foreign scheduling references.' );
	$restored_link = ps_pen_paper_get_universe_link( $result['campaign_id'] );
	$check( $restore_world === $restored_link['universe_id'] && 'local' === $restored_link['economy_mode'], 'Legacy archive lost its selected universe or local economic rules.' );
	$check( $restored && $restored->ID !== $source && 'private' === ps_pen_paper_get_access( $restored->ID )['visibility'], 'Copy did not create a private independent campaign.' );
	$children = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent=%d AND post_type='pspp_character'", $restored->ID ) );
	$check( 1 === count( $children ) && (int) $children[0] !== $character &&
		ps_pen_paper_raw_character_state( (int) $children[0] ) === ps_pen_paper_raw_character_state( $character ), 'Character IDs or state were not restored correctly.' );
	$check( ! is_wp_error( ps_pen_paper_get_events( $restored ) ), 'Restored native history cannot be read.' );
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" );
	$again = $assert_result( ps_pen_paper_restore_campaign( $copy[0], $copy[1], false, 'private' ), 'Repeated restore failed' );
	$check( $again['campaign_id'] === $result['campaign_id'] && $count === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" ), 'Retry duplicated campaign or history.' );
	$check( is_wp_error( ps_pen_paper_restore_campaign( $copy[0], $copy[1], false, 'preserve' ) ), 'Used confirmation accepted a changed policy.' );
	$preserve = $preview( $json, 'copy' );
	$preserved = $assert_result( ps_pen_paper_restore_campaign( $preserve[0], $preserve[1], false, 'preserve' ), 'Explicit audience restore failed' );
	$check( 'teaser' === ps_pen_paper_get_access( $preserved['campaign_id'] )['visibility'], 'Explicit source access policy was not honored.' );

	$target = $post( 'pspp_campaign', 0, $users['old_owner'], 'Original target secret' );
	add_post_meta( $target, '_pspp_gm', (string) $users['gm'] );
	add_post_meta( $target, '_foreign_fixture', 'Foreign metadata retained in the backup' );
	add_post_meta( $target, '_pspp_booking_links', array( 'revision' => 1, 'events' => array( 987 ) ) );
	$target_rules = $assert_result( ps_pen_paper_save_rules( $target, array( 'name' => 'Old target rules', 'description' => '', 'dice' => array( '6' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Old attribute', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '10', 'start' => '2' ),
	) ), '0' ), 'Target rules failed' );
	$old_child = $assert_result( ps_pen_paper_save_character( $target, 0, $users['old_owner'],
		array( 'name' => 'Old target child', 'values' => array( $target_rules['fields'][0]['id'] => '2' ) ), '0', '1' ), 'Old target character failed' );
	$before = $assert_result( ps_pen_paper_export_campaign( $target, 'local' ), 'Target snapshot failed' );
	$old_chat = ps_pen_paper_chat_id( $target );
	foreach ( array( 'seen' => time(), 'away' => 'Private old target pause' ) as $kind => $value ) {
		$key = ps_pen_paper_table_key( $target, $users['gm'], $kind ); $presence_keys[] = $key; set_transient( $key, $value, DAY_IN_SECONDS );
	}
	$replacement = $preview( $json, 'replace', $target );
	update_post_meta( $target, '_pspp_booking_links', array( 'revision' => 2, 'events' => array( 988 ) ) );
	$stale_schedule = ps_pen_paper_restore_campaign( $replacement[0], $replacement[1], false, 'private' );
	$check( is_wp_error( $stale_schedule ) && 'restore_changed' === $stale_schedule->get_error_code(),
		'Changed local event associations did not invalidate a stale replacement confirmation.' );
	$replacement = $preview( $json, 'replace', $target );
	$assert_result( ps_pen_paper_prepare_import( $replacement[2], $replacement[3], ps_pen_paper_import_identity_map( $replacement[2], $replacement[3] ), false ), 'Replacement identity preparation failed' );
	$replaced = $assert_result( ps_pen_paper_restore_campaign( $replacement[0], $replacement[1], false, 'private' ), 'Target replacement failed' );
	$check( $target === $replaced['campaign_id'] && $users['old_owner'] === (int) ps_pen_paper_get_campaign( $target )->post_author, 'Replacement changed target identity or original creator.' );
	$check( array() === ps_pen_paper_booking_links( $target )['events'], 'Replacement retained scheduling references from an older game state.' );
	$check( $old_chat !== ps_pen_paper_chat_id( $target ) &&
		! ps_pen_paper_chat_access( true, array( 'id' => $old_chat, 'blog_id' => get_current_blog_id(), 'session_type' => 'page' ) ), 'Replacement reused an old private chat session.' );
	$check( ! in_array( 'Private old target pause', array_column( ps_pen_paper_table_state( ps_pen_paper_get_campaign( $target ) )['members'], 'note' ), true ), 'Old private presence notes leaked into the restored campaign.' );
	$table_request = new WP_REST_Request( 'POST', '/ps-pen-paper/v1/campaigns/' . $target . '/table' );
	$table_request->set_param( 'id', $target ); $table_request->set_header( 'content-type', 'application/json' );
	$table_request->set_body( wp_json_encode( array( 'operation' => 'turn', 'user_id' => $users['new_gm'], 'revision' => 0, 'scope_epoch' => '' ) ) );
	$check( is_wp_error( ps_pen_paper_table_request( $table_request ) ) && 0 === ps_pen_paper_table_turn( $target )['revision'], 'Stale REST table request changed a restored root.' );
	$table_request->set_body( wp_json_encode( array( 'operation' => 'turn', 'user_id' => $users['new_gm'], 'revision' => 0, 'scope_epoch' => ps_pen_paper_scope_epoch( $target ) ) ) );
	$check( ! is_wp_error( ps_pen_paper_table_request( $table_request ) ) && 1 === ps_pen_paper_table_turn( $target )['revision'], 'Fresh REST table context did not preserve turn behavior.' );
	$check( (int) get_post( $old_child )->post_parent === $replaced['backup_id'] &&
		'Foreign metadata retained in the backup' === get_post_meta( $replaced['backup_id'], '_foreign_fixture', true ), 'Previous records or foreign metadata were destroyed.' );
	$backup = json_decode( $assert_result( ps_pen_paper_get_import_backup( $replaced['backup_id'] ), 'Backup retrieval failed' ), true );
	$check( $backup['payload_sha256'] === $before['payload_sha256'] && ! ps_pen_paper_get_campaign( $replaced['backup_id'] ), 'Backup is changed or visible as an active campaign.' );
	$check( ! ps_pen_paper_get_character( ps_pen_paper_get_campaign( $target ), $old_child ), 'Old child remains accessible through replacement campaign.' );
	wp_set_current_user( $users['new_gm'] );
	$check( ps_pen_paper_can_manage_campaign( ps_pen_paper_get_campaign( $target ) ) &&
		is_wp_error( ps_pen_paper_get_import_backup( $replaced['backup_id'] ) ), 'Imported GM lost management or gained access to old target secrets.' );
	wp_set_current_user( $users['old_owner'] );
	$check( ! is_wp_error( ps_pen_paper_get_import_backup( $replaced['backup_id'] ) ), 'Original creator lost access to their private target backup.' );
	wp_set_current_user( $users['gm'] );
	$recover = $preview( $encode( $backup ), 'copy' );
	$recovered = $assert_result( ps_pen_paper_restore_campaign( $recover[0], $recover[1], false, 'private' ), 'Private pre-replacement backup cannot be restored' );
	$check( 'Original target secret' === ps_pen_paper_get_campaign( $recovered['campaign_id'] )->post_title, 'Backup recovery did not recover the original campaign.' );
	$second = $preview( $json, 'replace', $restored->ID );
	$assert_result( ps_pen_paper_restore_campaign( $second[0], $second[1], false, 'private' ), 'Second replacement failed' );
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" );
	$old_retry = $assert_result( ps_pen_paper_restore_campaign( $copy[0], $copy[1], false, 'private' ), 'Receipt was lost during a later replacement' );
	$check( $old_retry['campaign_id'] === $restored->ID && $count === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts" ), 'A previous token duplicated data after a subsequent replacement.' );
	$rollback = $preview( $json, 'replace', $target );
	$baseline = ps_pen_paper_export_campaign( $target, 'local' )['payload_sha256'];
	$armed = true;
	$fail = function ( $query ) use ( &$armed ) {
		if ( $armed && 0 === strpos( $query, 'INSERT INTO' ) && false !== strpos( $query, "'_pspp_event'" ) ) {
			$armed = false; return 'PSPP_TEST_RESTORE_FAILURE';
		}
		return $query;
	};
	$suppressed = $wpdb->suppress_errors( true ); add_filter( 'query', $fail );
	try { $failed = ps_pen_paper_restore_campaign( $rollback[0], $rollback[1], false, 'private' ); }
	finally { remove_filter( 'query', $fail ); $wpdb->suppress_errors( $suppressed ); }
	$check( ! $armed && is_wp_error( $failed ) && $baseline === ps_pen_paper_export_campaign( $target, 'local' )['payload_sha256'], 'A failed restore partially changed target data.' );
	$changed = $preview( $json, 'replace', $target );
	wp_update_post( array( 'ID' => $target, 'post_title' => 'Changed after confirmation' ) );
	$check( is_wp_error( ps_pen_paper_restore_campaign( $changed[0], $changed[1], false, 'private' ) ), 'Changed target accepted an obsolete confirmation.' );
	$check( $archive['payload_sha256'] === ps_pen_paper_export_campaign( $source, 'local' )['payload_sha256'], 'Restoration changed source campaign.' );

	$unknown = $archive;
	foreach ( $unknown['payload']['records'] as &$record ) {
		if ( $source === $record['id'] ) { $record['meta'][] = array( 'key' => '_pspp_unknown_restore_fixture', 'value' => 'Private archival data' ); }
	}
	unset( $record );
	$unknown_copy = $preview( $encode( $unknown ), 'copy' );
	$check( is_wp_error( ps_pen_paper_restore_campaign( $unknown_copy[0], $unknown_copy[1], false, 'private' ) ), 'Unknown active metadata was silently accepted.' );
	$archived = $assert_result( ps_pen_paper_restore_campaign( $unknown_copy[0], $unknown_copy[1], true, 'private' ), 'Explicit unknown metadata archive failed' );
	$check( '' === get_post_meta( $archived['campaign_id'], '_pspp_unknown_restore_fixture', true ) &&
		'' !== get_post_meta( $archived['campaign_id'], '_pspp_import_archive', true ), 'Unknown metadata became active or was lost.' );
	$bad = $archive;
	foreach ( $bad['payload']['records'] as &$record ) {
		if ( $character !== $record['id'] ) { continue; }
		foreach ( $record['meta'] as &$meta ) {
			if ( '_pspp_character' !== $meta['key'] ) { continue; }
			$state = unserialize( $meta['value'], array( 'allowed_classes' => false ) );
			$state['active']['values'][ $rules['fields'][0]['id'] ] = 'broken'; $meta['value'] = serialize( $state );
		}
		unset( $meta );
	}
	unset( $record );
	$bad_copy = $preview( $encode( $bad ), 'copy' );
	$check( is_wp_error( ps_pen_paper_restore_campaign( $bad_copy[0], $bad_copy[1], true, 'private' ) ), 'Archive policy concealed an invalid known document.' );
	if ( ! defined( 'CPC_CORE_PLUGINS' ) ) { define( 'CPC_CORE_PLUGINS', 'core-profile,core-activity' ); }
	require_once dirname( __DIR__, 2 ) . '/ps-community/activity/cpc_profile_tabs.php';
	$world = $assert_result( ps_pen_paper_save_universe( 0, array( 'name' => 'Rich restoration universe', 'description' => '' ), '0' ), 'Rich world failed' );
	$world_rules = $assert_result( ps_pen_paper_save_rules( $world, array( 'name' => 'Rich rules', 'description' => '', 'dice' => array( '6' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Mut', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '10', 'start' => '2' ),
	) ), '0' ), 'Rich rules failed' );
	$universe = ps_pen_paper_get_universe( $world );
	$assert_result( ps_pen_paper_save_currency( $universe, array( 'name' => 'Coins', 'symbol' => 'C', 'wallet_mode' => 'campaign' ), '0' ), 'Rich currency failed' );
	$base = $assert_result( ps_pen_paper_save_character( $world, 0, $users['player'],
		array( 'name' => 'Rich base', 'values' => array( $world_rules['fields'][0]['id'] => '2' ) ), '0', '1' ), 'Rich base failed' );
	$rich_id = $post( 'pspp_campaign', 0, $users['gm'], 'Rich restoration source' );
	add_post_meta( $rich_id, '_pspp_gm', (string) $users['gm'] ); add_post_meta( $rich_id, '_pspp_player', (string) $users['player'] );
	$assert_result( ps_pen_paper_save_universe_link( $rich_id, $world, '0', '1', 'confirmed' ), 'Rich link failed' );
	$assert_result( ps_pen_paper_adopt_universe_rules( $rich_id, '1', 'confirmed' ), 'Rich rule adoption failed' );
	$alias = $assert_result( ps_pen_paper_attach_base_character( $rich_id, $base, '1', '1', 'shared', 'confirmed' ), 'Rich alias failed' );
	$rich = ps_pen_paper_get_campaign( $rich_id );
	$assert_result( ps_pen_paper_save_equipment_schema( $rich, array( 'slots' => 'hand | Hand | tool', 'rarities' => 'normal | Normal | #718096' ), '0' ), 'Rich inventory schema failed' );
	$item = $assert_result( ps_pen_paper_save_item( $rich, 0, array( 'name' => 'One-time reward', 'description' => '', 'kind' => 'tool', 'rarity' => 'normal',
		'binding' => 'tradeable', 'properties' => '', 'bonuses' => array() ), '0' ), 'Rich item failed' );
	$assert_result( ps_pen_paper_save_gear( $rich, $alias, 'grant_item', array( 'item_id' => (string) $item, 'item_revision' => '1' ), '0' ), 'Rich item grant failed' );
	$assert_result( ps_pen_paper_change_wallet( $rich, $alias, 'grant', '7', 'Restore grant', '0', '1' ), 'Rich wallet grant failed' );
	$image_path = wp_tempnam( 'pspp-import-write.png' );
	$check( $image_path && false !== file_put_contents( $image_path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=' ) ), 'Rich image fixture failed.' );
	$assert_result( ps_pen_paper_save_sheet_style( $rich, '0', $image_path ), 'Rich protected image failed' );
	$assert_result( ps_pen_paper_add_portrait_preset( $world, 'Restore portrait', $image_path, '0' ), 'Portrait policy fixture failed' );
	$assert_result( ps_pen_paper_save_slot_icon( $rich, 'hand', '1', $image_path ), 'Slot image fixture failed' );
	$assert_result( ps_pen_paper_change_member_invitation( $world, $users['old_owner'], 'invite', '0' ), 'World invitation fixture failed' );
	wp_set_current_user( $users['old_owner'] );
	$assert_result( ps_pen_paper_change_member_invitation( $world, $users['old_owner'], 'accept', '1' ), 'World membership fixture failed' );
	wp_set_current_user( $users['gm'] );
	$assert_result( ps_pen_paper_change_member_invitation( $world, $users['new_player'], 'invite', '2' ), 'Pending invitation fixture failed' );
	$assert_result( ps_pen_paper_save_item_action_policy( $rich, array( 'enabled' => 'yes', 'during_active' => 'yes', 'equip' => 'allow', 'use' => 'allow' ), '0' ), 'Outside action policy fixture failed' );
	$assert_result( ps_pen_paper_control_game_round( $rich, 'start_game_session', '0' ), 'Round fixture failed' );
	$consumables = array();
	foreach ( array( 'immediate', 'approval' ) as $condition ) {
		$consumable = $assert_result( ps_pen_paper_save_item( $rich, 0, array( 'name' => 'Restore consumable ' . $condition, 'description' => '', 'kind' => 'consumable',
			'rarity' => 'normal', 'binding' => 'tradeable', 'properties' => '', 'bonuses' => array(),
			'use' => array( 'condition' => $condition, 'expression' => '', 'attribute' => '', 'threshold' => '0', 'failure' => 'keep',
				'temporary' => 'temporary', 'rounds' => '0', 'effects' => array( $world_rules['fields'][0]['id'] => '1' ) ) ), '0' ), 'Consumable fixture failed' );
		$before_items = ps_pen_paper_gear_state( $alias )['active']['items'];
		$assert_result( ps_pen_paper_save_gear( $rich, $alias, 'grant_item', array( 'item_id' => (string) $consumable, 'item_revision' => '1' ),
			(string) ps_pen_paper_gear_state( $alias )['revision'] ), 'Consumable grant failed' );
		$uuid = array_key_first( array_diff_key( ps_pen_paper_gear_state( $alias )['active']['items'], $before_items ) );
		$consumables[ $condition ] = $uuid;
		if ( 'approval' === $condition ) { wp_set_current_user( $users['player'] ); }
		$use_state = $assert_result( ps_pen_paper_character_state( $rich, get_post( $alias ), false ), 'Consumable character unavailable' );
		$assert_result( ps_pen_paper_use_item( $rich, $alias, 'use_item', array( 'instance_id' => $uuid,
			'character_revision' => ps_pen_paper_item_use_character_revision( $use_state ), 'rules_revision' => '1',
			'round_revision' => '1', 'policy_revision' => '1' ), (string) ps_pen_paper_gear_state( $alias )['revision'],
			'immediate' === $condition ? 'profile' : 'game' ), 'Consumable use fixture failed' );
	}
	$tool_uuid = array_key_first( ps_pen_paper_gear_state( $alias )['active']['items'] );
	$assert_result( ps_pen_paper_save_gear( $rich, $alias, 'equip_items', array( 'slots' => array( 'hand' => $tool_uuid ) ),
		(string) ps_pen_paper_gear_state( $alias )['revision'], 'profile' ), 'Pending profile equipment fixture failed' );
	wp_set_current_user( $users['gm'] );
	$scenes = array();
	foreach ( array( 'Restored start', 'Restored ending' ) as $title ) {
		$scene = $assert_result( ps_pen_paper_save_scene( $rich, 0, array( 'title' => $title, 'text' => $title, 'secret' => '', 'grid' => '0' ), '0' ), 'Rich scene failed' );
		$assert_result( ps_pen_paper_publish_scene( $rich, $scene, '1', 'confirmed' ), 'Rich scene publication failed' ); $scenes[] = $scene;
	}
	$adventure = $assert_result( ps_pen_paper_save_adventure( $rich, 0, 'Restored adventure', (string) $scenes[0], '0' ), 'Rich adventure failed' );
	$assert_result( ps_pen_paper_save_adventure_edge( $rich, $adventure, array( 'from_scene' => (string) $scenes[0], 'choice' => 'Finish',
		'expression' => '', 'attribute' => '', 'threshold' => '0', 'success_scene' => (string) $scenes[1], 'failure_scene' => '0', 'gm_review' => '1' ), '1' ), 'Rich adventure choice failed' );
	$assert_result( ps_pen_paper_publish_adventure( $rich, $adventure, '2', 'confirmed' ), 'Rich adventure publication failed' );
	wp_set_current_user( $users['player'] );
	$run = $assert_result( ps_pen_paper_start_adventure_run( $rich, $adventure, $alias, '3', '1' ), 'Rich active run failed' );
	$rich_edge_id = ps_pen_paper_adventure_run_records( $rich, $alias )[ $run ]['snapshot']['edges'][0]['id'];
	$assert_result( ps_pen_paper_review_adventure_run( $rich, $run, $rich_edge_id, '1' ), 'Rich review request failed' );
	wp_set_current_user( $users['gm'] );
	$assert_result( ps_pen_paper_review_adventure_run( $rich, $run, $rich_edge_id, '2', 'approved' ), 'Rich review approval failed' );
	$rich_archive = $assert_result( ps_pen_paper_export_campaign( $rich_id, 'complete' ), 'Rich full export failed' );
	$rich_copy = $preview( $encode( $rich_archive ), 'copy', 0, 'copy' );
	$rich_result = $assert_result( ps_pen_paper_restore_campaign( $rich_copy[0], $rich_copy[1], false, 'private' ), 'Rich full restore failed' );
	$new_campaign = ps_pen_paper_get_campaign( $rich_result['campaign_id'] );
	$new_world = ps_pen_paper_get_universe_link( $new_campaign->ID )['universe_id'];
	$check( $new_world !== $world && ps_pen_paper_get_universe( $new_world ), 'Complete copy did not create an independent universe.' );
	$new_aliases = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent=%d AND post_type='pspp_character'", $new_campaign->ID ) );
	$check( 1 === count( $new_aliases ), 'Rich copy lost its shared alias.' ); $new_alias = (int) $new_aliases[0];
	$new_context = ps_pen_paper_raw_character_state( $new_alias )['context'];
	$check( $new_context['universe_id'] === $new_world && $new_context['source_id'] !== $base &&
		(int) get_post( $new_context['source_id'] )->post_parent === $new_world, 'Shared alias was not rebound to the copied base.' );
	$gear = ps_pen_paper_gear_state( $new_alias );
	$check( 2 === count( $gear['active']['items'] ) && array_keys( $gear['active']['items'] ) === array_keys( ps_pen_paper_gear_state( $alias )['active']['items'] ), 'Copy changed item UUIDs or executed the grant again.' );
	$check( 'profile' === $gear['pending_context'] && $gear['pending']['slots']['hand'] === $tool_uuid &&
		$gear['uses']['pending'][ $consumables['approval'] ]['actor_id'] === $users['player'] &&
		$gear['uses']['receipts'][ $consumables['immediate'] ]['actor_id'] === $users['new_gm'] &&
		'profile' === $gear['uses']['receipts'][ $consumables['immediate'] ]['context'] &&
		3 === ps_pen_paper_character_state( $new_campaign, get_post( $new_alias ) )['active']['values'][ $world_rules['fields'][0]['id'] ],
		'Restore lost pending context, actors, consumption receipt or temporary effect.' );
	$new_policy = $assert_result( ps_pen_paper_character_policy( $new_world ), 'Copied character policy unreadable' );
	$source_portrait = ps_pen_paper_character_policy( $world )['presets'][0]['asset_id'];
	$copied_portrait = $new_policy['presets'][0]['asset_id'];
	$copied_slot = ps_pen_paper_equipment_schema( $new_campaign )['slots']['hand']['asset_id'];
	$check( $copied_portrait !== $source_portrait && (int) get_post( $copied_portrait )->post_parent === $new_world &&
		(int) get_post( $copied_slot )->post_parent === $new_campaign->ID &&
		ps_pen_paper_universe_members( $new_world )['users'] === array( $users['old_owner'] ) &&
		ps_pen_paper_member_invitations( $new_world )['entries'][0]['inviter_id'] === $users['new_gm'] &&
		ps_pen_paper_game_round( $new_campaign->ID ) === ps_pen_paper_game_round( $rich_id ) &&
		ps_pen_paper_item_action_policy( $new_campaign ) === ps_pen_paper_item_action_policy( $rich ),
		'Restore lost private asset ownership, membership, invitation actors, rounds or outside policy.' );
	$new_wallet = $assert_result( ps_pen_paper_wallet_context( $new_campaign, $new_alias ), 'Restored wallet cannot be read' );
	$check( 7 === $new_wallet['wallet']['balance'], 'Rich copy changed wallet balance.' );
	$new_runs = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent=%d AND post_type='pspp_adventure_run'", $new_campaign->ID ) );
	$check( 1 === count( $new_runs ) && (int) $new_runs[0] !== $run, 'Active adventure run was lost or duplicated.' );
	$new_run_records = $assert_result( ps_pen_paper_adventure_run_records( $new_campaign, $new_alias ), 'Restored active adventure run cannot be read' );
	$check( isset( $new_run_records[ (int) $new_runs[0] ] ), 'Restored run is not available through the native reader.' );
	$new_run = $new_run_records[ (int) $new_runs[0] ];
	$check( $new_run['character_id'] === $new_alias && $new_run['scene_id'] !== $scenes[0], 'Active adventure references old IDs.' );
	$check( 'approved' === $new_run['approval']['status'] && $new_run['approval']['requester_id'] === $users['player'] &&
		$new_run['approval']['reviewer_id'] === $users['new_gm'] && 3 === $new_run['revision'], 'Restored approval lost its mapped participants or revision.' );
	wp_set_current_user( $users['player'] );
	$edge_id = $new_run['snapshot']['edges'][0]['id'];
	$assert_result( ps_pen_paper_advance_adventure_run( $new_campaign, (int) $new_runs[0], $edge_id, '3' ), 'Restored adventure cannot advance' );
	$finished = ps_pen_paper_adventure_run_records( $new_campaign, $new_alias )[ (int) $new_runs[0] ];
	$check( null === $finished['approval'] && $finished['history'][0]['review']['reviewer_id'] === $users['new_gm'], 'Restored approval was not consumed and recorded.' );
	$check( 'completed' === $finished['status'] && is_wp_error( ps_pen_paper_advance_adventure_run( $new_campaign, (int) $new_runs[0], $edge_id, '1' ) ), 'Restored adventure did not complete or accepted a stale repeated decision.' );
	$check( 'active' === ps_pen_paper_adventure_run_records( $rich, $alias )[ $run ]['status'], 'Playing the restored adventure changed the original run.' );
	$assert_result( ps_pen_paper_advance_adventure_run( $rich, $run, $rich_edge_id, '3' ), 'Original approved adventure cannot finish' );
	wp_set_current_user( $users['gm'] );
	$finished_archive = $assert_result( ps_pen_paper_export_campaign( $rich_id, 'complete' ), 'Completed reviewed run export failed' );
	$finished_preview = $preview( $encode( $finished_archive ), 'copy', 0, 'copy' );
	$finished_copy = $assert_result( ps_pen_paper_restore_campaign( $finished_preview[0], $finished_preview[1], false, 'private' ), 'Completed reviewed run restore failed' );
	$copied_runs = get_posts( array( 'post_type' => 'pspp_adventure_run', 'post_status' => 'private', 'post_parent' => $finished_copy['campaign_id'], 'posts_per_page' => -1 ) );
	$check( 1 === count( $copied_runs ), 'Completed reviewed run missing.' );
	$copied_run = get_post_meta( $copied_runs[0]->ID, '_pspp_adventure_run', true );
	$check( ps_pen_paper_valid_adventure_run( $copied_run ) && 'completed' === $copied_run['status'] &&
		$copied_run['history'][0]['review']['reviewer_id'] === $users['new_gm'] &&
		$copied_run['history'][0]['review']['requester_id'] === $users['player'], 'Completed review history participants were not remapped.' );
	$assert_result( ps_pen_paper_get_events( $new_campaign ), 'Rich copied history cannot be read' );

	$group_campaign_id = $post( 'pspp_campaign', 0, $users['gm'], 'Group restoration source' );
	add_post_meta( $group_campaign_id, '_pspp_gm', (string) $users['gm'] );
	foreach ( array( 'player', 'old_owner' ) as $owner ) { add_post_meta( $group_campaign_id, '_pspp_player', (string) $users[ $owner ] ); }
	$group_rules = $assert_result( ps_pen_paper_save_rules( $group_campaign_id, array( 'name' => 'Group restore rules', 'description' => '', 'dice' => array( '4' ),
		'fields' => array( array( 'id' => '', 'label' => 'Energy', 'type' => 'resource', 'description' => '', 'min' => '0', 'max' => '10', 'start' => '7' ) ) ), '0' ), 'Group rules failed' );
	$group_campaign = ps_pen_paper_get_campaign( $group_campaign_id ); $group_characters = array(); $energy = $group_rules['fields'][0]['id'];
	$assert_result( ps_pen_paper_save_currency( $group_campaign, array( 'name' => 'Group coins', 'symbol' => 'G', 'wallet_mode' => 'campaign' ), '0' ), 'Group currency failed' );
	foreach ( array( 'player', 'old_owner' ) as $owner ) {
		$group_characters[ $owner ] = $assert_result( ps_pen_paper_save_character( $group_campaign_id, 0, $users[ $owner ], array( 'name' => 'Group ' . $owner,
			'values' => array( $energy => array( 'current' => '7', 'maximum' => '10' ) ) ), '0', '1' ), 'Group character failed' );
		$wallet = $assert_result( ps_pen_paper_wallet_context( $group_campaign, $group_characters[ $owner ] ), 'Group wallet unavailable' );
		$assert_result( ps_pen_paper_change_wallet( $group_campaign, $group_characters[ $owner ], 'grant', '7', 'Group start', '0', (string) $wallet['currency']['revision'] ), 'Group wallet failed' );
	}
	$group_scenes = array();
	foreach ( array( 'Group opening', 'Group continuation' ) as $title ) {
		$scene = $assert_result( ps_pen_paper_save_scene( $group_campaign, 0, array( 'title' => $title, 'text' => $title, 'secret' => '', 'grid' => '0' ), '0' ), 'Group scene failed' );
		$assert_result( ps_pen_paper_publish_scene( $group_campaign, $scene, '1', 'confirmed' ), 'Group scene publication failed' ); $group_scenes[] = $scene;
	}
	$group_adventure = $assert_result( ps_pen_paper_save_adventure( $group_campaign, 0, 'Group restore route', (string) $group_scenes[0], '0' ), 'Group adventure failed' );
	$group_edge = array( 'from_scene' => (string) $group_scenes[0], 'choice' => 'Group first step', 'expression' => 'W4', 'attribute' => '', 'threshold' => '0',
		'success_scene' => (string) $group_scenes[1], 'failure_scene' => '0', 'gm_review' => '1', 'cost_resource' => $energy, 'cost_amount' => '2', 'cost_timing' => 'attempt',
		'reward' => array( 'kind' => 'fixed', 'timing' => 'decision', 'table_id' => '0', 'item_id' => '0', 'quantity' => '0', 'money' => '3' ) );
	$assert_result( ps_pen_paper_save_adventure_edge( $group_campaign, $group_adventure, $group_edge, '1' ), 'Group first choice failed' );
	$first_group_edge = ps_pen_paper_get_adventure( $group_campaign, $group_adventure )['draft']['edges'][0]['id'];
	$group_edge['from_scene'] = (string) $group_scenes[1]; $group_edge['success_scene'] = '0'; $group_edge['choice'] = 'Group second step'; $group_edge['expression'] = '';
	$group_edge['conditions'] = array( 'mode' => 'all', 'rows' => array( array( 'target' => $first_group_edge . ':history', 'operator' => 'eq', 'value' => '1' ) ) );
	$assert_result( ps_pen_paper_save_adventure_edge( $group_campaign, $group_adventure, $group_edge, '2' ), 'Group continuation failed' );
	$assert_result( ps_pen_paper_publish_adventure( $group_campaign, $group_adventure, '3', 'confirmed' ), 'Group publication failed' );
	$group_id = $assert_result( ps_pen_paper_write_adventure_group( $group_campaign, 0, '0', 'create', array( 'title' => 'Restored group', 'rule' => 'unanimous',
		'adventure_id' => $group_adventure, 'adventure_revision' => '4' ) ), 'Group creation failed' );
	$group_action = function ( $operation, $input = array() ) use ( $group_campaign, $group_id, $assert_result ) {
		return $assert_result( ps_pen_paper_write_adventure_group( $group_campaign, $group_id, (string) get_post_meta( $group_id, '_pspp_adventure_group', true )['revision'],
			$operation, $input ), 'Group operation failed: ' . $operation );
	};
	foreach ( array( 'player', 'old_owner' ) as $owner ) { wp_set_current_user( $users[ $owner ] ); $group_action( 'join', array( 'character_id' => $group_characters[ $owner ] ) ); }
	wp_set_current_user( $users['player'] ); $group_action( 'propose', array( 'character_id' => $group_characters['player'], 'edge_id' => $first_group_edge ) );
	wp_set_current_user( $users['old_owner'] ); $group_action( 'vote' );
	wp_set_current_user( $users['gm'] ); $group_action( 'approve' );
	wp_set_current_user( $users['player'] ); $group_action( 'execute' );
	$group_state = get_post_meta( $group_id, '_pspp_adventure_group', true ); $second_group_edge = $group_state['snapshot']['edges'][1]['id'];
	wp_set_current_user( $users['old_owner'] ); $group_action( 'propose', array( 'character_id' => $group_characters['old_owner'], 'edge_id' => $second_group_edge ) );
	wp_set_current_user( $users['player'] ); $group_action( 'vote' );
	wp_set_current_user( $users['gm'] ); $group_action( 'approve' );
	$group_state = get_post_meta( $group_id, '_pspp_adventure_group', true );
	$group_archive = $assert_result( ps_pen_paper_export_campaign( $group_campaign_id, 'local' ), 'Group export failed' );
	$group_copy = $preview( $encode( $group_archive ), 'copy', 0, 'bind', $restore_world, array(), array( $users['old_owner'] => $users['new_player'] ) );
	$collapsed = $group_copy[3]; $collapsed['users'][ $users['old_owner'] ] = $users['player'];
	$check( is_wp_error( ps_pen_paper_prepare_import( $group_copy[2], $collapsed, ps_pen_paper_import_identity_map( $group_copy[2], $collapsed ), false ) ),
		'Merging group voters silently changed the quorum.' );
	$group_result = $assert_result( ps_pen_paper_restore_campaign( $group_copy[0], $group_copy[1], false, 'private' ), 'Group restore failed' );
	$group_restored_campaign = ps_pen_paper_get_campaign( $group_result['campaign_id'] );
	wp_set_current_user( $users['new_gm'] ); $group_records = $assert_result( ps_pen_paper_adventure_group_records( $group_restored_campaign ), 'Restored group unreadable' );
	$check( 1 === count( $group_records ), 'Group record missing after restore.' ); $restored_group_id = (int) array_key_first( $group_records );
	$restored_group = $group_records[ $restored_group_id ]['state'];
	$check( $restored_group_id !== $group_id && $restored_group['adventure_id'] !== $group_adventure && $restored_group['scene_id'] !== $group_scenes[1] &&
		$restored_group['proposal']['owner_id'] === $users['new_player'] && $restored_group['proposal']['reviewer_id'] === $users['new_gm'] &&
		$restored_group['proposal']['votes'] === array( $users['new_player'], $users['player'] ) &&
		$restored_group['history'][0]['electorate'] === array( $users['player'], $users['new_player'] ) &&
		$restored_group['history'][0]['decision']['review']['reviewer_id'] === $users['new_gm'], 'Group references, voters or historical approval were not remapped.' );
	$first_actor = $restored_group['history'][0]['character_id']; $second_actor = $restored_group['proposal']['character_id'];
	$check( $first_actor !== $group_characters['player'] && $second_actor !== $group_characters['old_owner'] &&
		5 === ps_pen_paper_raw_character_state( $first_actor )['active']['values'][ $energy ]['current'] &&
		7 === ps_pen_paper_raw_character_state( $second_actor )['active']['values'][ $energy ]['current'] &&
		10 === ps_pen_paper_wallet_context( $group_restored_campaign, $first_actor )['wallet']['balance'] &&
		7 === ps_pen_paper_wallet_context( $group_restored_campaign, $second_actor )['wallet']['balance'], 'Restore replayed personal costs or rewards.' );
	$assert_result( ps_pen_paper_get_events( $group_restored_campaign ), 'Group events were not restored as native history' );
	wp_set_current_user( $users['new_player'] );
	foreach ( $restored_group['participants'] as $participant ) {
		$check( ps_pen_paper_adventure_group_member( $group_restored_campaign, $restored_group, $participant['owner_id'] ),
			'Restored participant ownership or membership missing: ' . wp_json_encode( array(
				'participant' => $participant, 'author' => get_post( $participant['character_id'] )->post_author,
				'role' => ps_pen_paper_campaign_role( $group_restored_campaign, $participant['owner_id'] ),
				'read' => user_can( $participant['owner_id'], 'read' ), 'site' => (bool) ps_pen_paper_site_user( $participant['owner_id'] ) ) ) );
	}
	$check( ps_pen_paper_adventure_group_quorum( $restored_group['rule'], ps_pen_paper_adventure_group_electorate( $restored_group ),
		$restored_group['proposal']['votes'], $restored_group['proposal']['reviewer_id'] ) &&
		ps_pen_paper_adventure_reviewer_authority( $group_restored_campaign, $users['new_gm'] ), 'Restored quorum or reviewer authority missing.' );
	ob_start(); ps_pen_paper_render_adventure_groups( $group_restored_campaign, 0 ); $restored_group_html = ob_get_clean();
	$check( false !== strpos( $restored_group_html, 'Belohnung: 3 G' ) && false !== strpos( $restored_group_html, 'verfügbar 7' ),
		'Group execution omitted the personal reward and cost preview.' );
	$assert_result( ps_pen_paper_write_adventure_group( $group_restored_campaign, $restored_group_id, (string) $restored_group['revision'], 'execute' ), 'Mapped owner could not continue group' );
	$restored_group = get_post_meta( $restored_group_id, '_pspp_adventure_group', true );
	$check( 'completed' === $restored_group['status'] && 2 === count( $restored_group['history'] ) &&
		1 === $restored_group['history'][1]['decision']['conditions']['rows'][0]['value'] &&
		5 === ps_pen_paper_raw_character_state( $second_actor )['active']['values'][ $energy ]['current'] &&
		10 === ps_pen_paper_wallet_context( $group_restored_campaign, $second_actor )['wallet']['balance'] &&
		$group_state === get_post_meta( $group_id, '_pspp_adventure_group', true ), 'Restored continuation lost shared history or changed the source.' );
	wp_set_current_user( $users['gm'] );
	$group_action( 'cancel' ); wp_set_current_user( $users['old_owner'] );
	$group_action( 'propose', array( 'character_id' => $group_characters['old_owner'], 'edge_id' => $second_group_edge ) );
	wp_set_current_user( $users['gm'] );
	$pending_archive = $assert_result( ps_pen_paper_export_campaign( $group_campaign_id, 'local' ), 'Pending group export failed' );
	$pending_copy = $preview( $encode( $pending_archive ), 'copy' );
	$pending_result = $assert_result( ps_pen_paper_restore_campaign( $pending_copy[0], $pending_copy[1], false, 'private' ), 'Pending group restore failed' );
	wp_set_current_user( $users['new_gm'] );
	$pending_records = $assert_result( ps_pen_paper_adventure_group_records( ps_pen_paper_get_campaign( $pending_result['campaign_id'] ) ), 'Pending group unreadable' );
	$pending_group = reset( $pending_records )['state'];
	$check( 0 === $pending_group['proposal']['reviewer_id'] && 1 === count( $pending_group['proposal']['votes'] ) &&
		1 === count( $pending_group['history'] ), 'Restore manufactured votes, approval or progress.' );
	wp_set_current_user( $users['gm'] );
	$bound = $preview( $encode( $rich_archive ), 'copy', 0, 'bind', $world, array( $base => $base ) );
	$world_hash = $bound[4]['universe_hash']; $base_state = ps_pen_paper_raw_character_state( $base );
	$assert_result( ps_pen_paper_restore_campaign( $bound[0], $bound[1], false, 'private' ), 'Existing universe binding failed' );
	$check( $base_state === ps_pen_paper_raw_character_state( $base ) &&
		$world_hash === ps_pen_paper_import_configuration( $bound[2], $bound[3] )['universe_hash'], 'Binding changed the existing universe or base.' );
	if ( '1' === getenv( 'PSPP_TEST_HTTP' ) ) {
		$session = WP_Session_Tokens::get_instance( $users['gm'] )->create( time() + 300 );
		$_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $users['gm'], time() + 300, 'logged_in', $session );
		$cookie = LOGGED_IN_COOKIE . '=' . $_COOKIE[LOGGED_IN_COOKIE] . '; ' . AUTH_COOKIE . '=' .
			wp_generate_auth_cookie( $users['gm'], time() + 300, 'auth', $session ) . '; ' .
			( is_multisite() ? 'wdeb_on_site_' . get_current_blog_id() : 'wdeb_on' ) . '=1';
		$page = $assert_result( wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_author' => $users['gm'],
			'post_title' => 'Restore HTTP ' . wp_generate_password( 8, false ), 'post_content' => '[ps_pen_paper]' ), true ), 'HTTP page failed' ); $pages[] = $page;
		$http_target = 0;
		foreach ( array( admin_url( 'admin-post.php' ), get_permalink( $page ) ) as $index => $url ) {
			$boundary = 'pspp' . wp_generate_password( 20, false );
			$fields = array( 'action' => 'ps_pen_paper', 'pspp_operation' => 'preview_import', '_wpnonce' => wp_create_nonce( 'pspp_import_upload' ) );
			if ( $index ) { $fields['pspp_frontend_page'] = (string) $page; }
			$body = '';
			foreach ( $fields as $name => $value ) { $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n"; }
			$body .= "--$boundary\r\nContent-Disposition: form-data; name=\"import_file\"; filename=\"backup.json\"\r\nContent-Type: application/json\r\n\r\n$json\r\n--$boundary--\r\n";
			$response = wp_remote_post( $url, array( 'timeout' => 30, 'redirection' => 0, 'headers' =>
				array( 'Cookie' => $cookie, 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ), 'body' => $body ) );
			$check( ! is_wp_error( $response ) && 302 === wp_remote_retrieve_response_code( $response ), 'HTTP restore upload failed.' );
			parse_str( (string) wp_parse_url( wp_remote_retrieve_header( $response, 'location' ), PHP_URL_QUERY ), $query );
			$check( isset( $query['pspp_import_preview'] ), 'HTTP upload did not yield a private token.' );
			$http_token = $query['pspp_import_preview']; $tokens[] = $http_token;
			$http_options = $copy[3]; $http_options['operation'] = $index ? 'replace' : 'copy'; $http_options['target_id'] = $index ? $http_target : 0;
			$form_options = $http_options;
			foreach ( array( 'target_id', 'universe_id' ) as $field ) { $form_options[ $field ] = (string) $form_options[ $field ]; }
			foreach ( $form_options['users'] as &$value ) { $value = (string) $value; } unset( $value ); $form_options['bases'] = '';
			$input = array( 'action' => 'ps_pen_paper', 'pspp_operation' => 'configure_import_preview', 'import_token' => $http_token,
				'_wpnonce' => wp_create_nonce( 'pspp_import_preview_' . $http_token ), 'import' => $form_options );
			if ( $index ) { $input['pspp_frontend_page'] = $page; }
			$response = wp_remote_post( $url, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Cookie' => $cookie ), 'body' => $input ) );
			$check( ! is_wp_error( $response ) && 302 === wp_remote_retrieve_response_code( $response ), 'HTTP restore configuration failed.' );
			$http_fingerprint = ps_pen_paper_restore_fingerprint( $copy[2], $http_options, ps_pen_paper_import_configuration( $copy[2], $http_options ) );
			$old_epoch = $http_target ? ps_pen_paper_scope_epoch( $http_target ) : '';
			$input = array( 'action' => 'ps_pen_paper', 'pspp_operation' => 'restore_import', 'import_token' => $http_token,
				'restore_fingerprint' => $http_fingerprint, 'restore_access' => 'private', 'restore_confirmation' => 'confirmed',
				'_wpnonce' => wp_create_nonce( 'pspp_restore_' . $http_token . '_' . $http_fingerprint ) );
			if ( $index ) { $input['pspp_frontend_page'] = $page; }
			$bad_input = $input; $bad_input['_wpnonce'] = 'invalid';
			$response = wp_remote_post( $url, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Cookie' => $cookie ), 'body' => $bad_input ) );
			$check( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) >= 400, 'HTTP restore accepted an invalid nonce.' );
			for ( $repeat = 0; $repeat < 2; $repeat++ ) {
				$response = wp_remote_post( $url, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Cookie' => $cookie ), 'body' => $input ) );
				$check( ! is_wp_error( $response ) && 302 === wp_remote_retrieve_response_code( $response ), 'HTTP restore or retry failed.' );
			}
			if ( $http_target ) { clean_post_cache( $http_target ); ps_pen_paper_scope_observe( $http_target, true ); }
			$http_receipt = $assert_result( ps_pen_paper_restore_receipt( $http_token ), 'HTTP receipt failed' );
			$check( is_array( $http_receipt ), 'HTTP restore did not persist its receipt.' ); $http_target = $http_receipt['campaign_id'];
			if ( $index ) {
				$check( 2 === ps_pen_paper_get_access( $http_target )['revision'], 'Stale-epoch fixture does not have matching old/new access revisions.' );
				$stale = array( 'action' => 'ps_pen_paper', 'pspp_operation' => 'save_access', 'pspp_frontend_page' => $page, 'campaign_id' => $http_target,
					'pspp_scope_epoch' => $old_epoch, '_wpnonce' => wp_create_nonce( 'pspp_campaign_' . $http_target ), 'settings_revision' => '2', 'settings_complete' => 'complete',
					'access' => array( 'visibility' => 'teaser', 'admission' => 'closed', 'teaser' => 'Stale form' ) );
				$response = wp_remote_post( $url, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Cookie' => $cookie ), 'body' => $stale ) );
				$check( ! is_wp_error( $response ) && false !== strpos( wp_remote_retrieve_body( $response ), 'Alte Eingaben wurden nicht übernommen' ),
					'Old epoch was not explicitly rejected; HTTP status ' . ( is_wp_error( $response ) ? $response->get_error_code() : wp_remote_retrieve_response_code( $response ) ) . '.' );
				clean_post_cache( $http_target );
				$check( 'private' === ps_pen_paper_get_access( $http_target )['visibility'], 'Old epoch form changed a restored root despite matching revision.' );
				$stale['pspp_scope_epoch'] = ps_pen_paper_scope_epoch( $http_target );
				$response = wp_remote_post( $url, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Cookie' => $cookie ), 'body' => $stale ) );
				$check( ! is_wp_error( $response ) && 302 === wp_remote_retrieve_response_code( $response ), 'Equivalent fresh-epoch form was not accepted.' );
				clean_post_cache( $http_target );
				$check( 'teaser' === ps_pen_paper_get_access( $http_target )['visibility'], 'Fresh epoch did not allow the intended settings change.' );
				$download = array( 'action' => 'ps_pen_paper', 'pspp_operation' => 'download_import_backup', 'pspp_frontend_page' => $page, 'backup_id' => $http_receipt['backup_id'],
					'_wpnonce' => wp_create_nonce( 'pspp_restore_backup_' . $http_receipt['backup_id'] ) );
				$response = wp_remote_post( $url, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Cookie' => $cookie ), 'body' => $download ) );
				$check( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) &&
					false !== strpos( wp_remote_retrieve_header( $response, 'cache-control' ), 'no-store' ) &&
					is_array( json_decode( wp_remote_retrieve_body( $response ), true ) ), 'Private HTTP backup download failed.' );
				$success_url = add_query_arg( array( 'pspp_view' => 'settings', 'pspp_import_preview' => $http_token ), get_permalink( $page ) );
				$response = wp_remote_get( $success_url, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Cookie' => $cookie ) ) );
				$check( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) &&
					false !== strpos( wp_remote_retrieve_body( $response ), 'Wiederherstellung abgeschlossen' ), 'Frontend restore completion panel failed.' );
			}
		}
	}
	WP_CLI::success( 'New/replaced campaign restoration, native history, distinct IDs, deliberate audience, protected backup, retry protection, stale confirmation and explicit unknown archival passed.' );
} finally {
	wp_set_current_user( $users ? $users['gm'] : $original_user );
	foreach ( $tokens as $token ) { delete_transient( ps_pen_paper_import_preview_key( $token ) ); }
	foreach ( $presence_keys as $key ) { delete_transient( $key ); }
	foreach ( $pages as $page ) { wp_delete_post( $page, true ); }
	if ( $users ) {
		$roots = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM $wpdb->posts WHERE post_type IN ('pspp_campaign','pspp_universe') AND post_author IN (" . implode( ',', array_fill( 0, count( $users ), '%d' ) ) . ')',
			array_values( $users )
		) );
		foreach ( $roots as $root ) {
			$children = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent=%d AND post_type LIKE %s", $root, $wpdb->esc_like( 'pspp_' ) . '%' ) );
			foreach ( $children as $child ) { wp_delete_post( (int) $child, true ); }
			wp_delete_post( (int) $root, true );
		}
	}
	foreach ( $users as $user ) { is_multisite() ? wpmu_delete_user( $user ) : wp_delete_user( $user ); }
	if ( $image_path && file_exists( $image_path ) ) { wp_delete_file( $image_path ); }
	if ( null === $original_cookie ) { unset( $_COOKIE[LOGGED_IN_COOKIE] ); } else { $_COOKIE[LOGGED_IN_COOKIE] = $original_cookie; }
	wp_set_current_user( $original_user );
}
