<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'character', 'event', 'asset', 'npc', 'item' ) as $type ) {
	call_user_func( 'ps_pen_paper_register_' . $type . '_type' );
}
ps_pen_paper_register_loot_types();
$check = static function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$original_user = get_current_user_id();
$users = array(); $scopes = array();
try {
	foreach ( array( 'gm', 'player' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-inheritance-' . $role . '-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber' ) );
		$check( is_int( $id ), 'Fixture user creation failed.' ); $users[ $role ] = $id;
	}
	get_userdata( $users['gm'] )->add_cap( 'ps_pen_paper_create_campaigns' );
	wp_set_current_user( $users['gm'] );
	$universe = ps_pen_paper_save_universe( 0, array( 'name' => 'Inheritance fixture', 'description' => '' ), '0' );
	$check( is_int( $universe ), 'Fixture universe creation failed.' ); $scopes[] = $universe;
	$input = array( 'name' => 'World rules', 'description' => 'World description', 'dice' => array( '20' ), 'character_mode' => 'direct',
		'fields' => array( array( 'id' => '', 'label' => 'Strength', 'description' => '', 'type' => 'attribute', 'min' => '0', 'max' => '20', 'start' => '2' ) ) );
	$world = ps_pen_paper_save_rules( $universe, $input, '0' );
	$check( ! is_wp_error( $world ), 'Fixture rules creation failed.' );
	$field = $world['fields'][0]['id'];
	$input = ps_pen_paper_inheritance_rules_input( $world );
	$input['campaign_policy'] = array( 'extra_fields' => false, 'globals' => array( 'description' ), 'fields' => array( $field => array( 'min' => 0, 'max' => 20 ) ) );
	$world = ps_pen_paper_save_rules( $universe, $input, '1' );
	$check( ! is_wp_error( $world ) && ps_pen_paper_valid_rules_document( $world ), 'Flexible policy was not saved as a valid document.' );
	$campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Inheritance fixture campaign' ), true );
	$check( is_int( $campaign_id ), 'Fixture campaign creation failed.' ); $scopes[] = $campaign_id;
	add_post_meta( $campaign_id, '_pspp_player', (string) $users['player'] );
	$check( ! is_wp_error( ps_pen_paper_link_new_campaign( $campaign_id, $universe, '1' ) ), 'Initial inheritance failed.' );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	$local = ps_pen_paper_get_rules( $campaign_id );
	$edit = ps_pen_paper_inheritance_rules_input( $local );
	$edit['name'] = 'Forbidden fixed name';
	$check( is_wp_error( ps_pen_paper_save_rules( $campaign_id, $edit, '1' ) ), 'Fixed world rule was editable.' );
	$edit = ps_pen_paper_inheritance_rules_input( $local ); $edit['fields'][0]['max'] = '21';
	$check( is_wp_error( ps_pen_paper_save_rules( $campaign_id, $edit, '1' ) ), 'Flexible field exceeded its world limit.' );
	$edit['fields'][0]['max'] = '15'; $edit['description'] = 'Campaign override';
	$local = ps_pen_paper_save_rules( $campaign_id, $edit, '1' );
	$check( ! is_wp_error( $local ), 'Allowed campaign override failed.' );
	$world_input = ps_pen_paper_inheritance_rules_input( $world ); $world_input['description'] = 'New world description';
	$world = ps_pen_paper_save_rules( $universe, $world_input, '2' );
	$local = ps_pen_paper_get_rules( $campaign_id );
	$check( ! is_wp_error( $world ) && 'Campaign override' === $local['description'] && 15 === $local['fields'][0]['max'] &&
		$world === ps_pen_paper_get_universe_link( $campaign_id )['rules_base'], 'Automatic update lost overrides or failed to advance its base.' );
	$character = ps_pen_paper_save_character( $campaign_id, 0, $users['player'], array( 'name' => 'Fixture hero', 'values' => array( $field => '2' ) ), '0', (string) $local['revision'] );
	$check( is_int( $character ), 'Fixture character creation failed.' );
	$old_state = ps_pen_paper_raw_character_state( $character );
	$old_rules = $local;
	$world_input = ps_pen_paper_inheritance_rules_input( $world );
	$world_input['fields'][0]['min'] = '5'; $world_input['fields'][0]['start'] = '5';
	$world = ps_pen_paper_save_rules( $universe, $world_input, '3' );
	$check( ! is_wp_error( $world ) && $old_rules === ps_pen_paper_get_rules( $campaign_id ) &&
		is_wp_error( ps_pen_paper_require_inherited_rules( $campaign_id ) ) && $old_state === ps_pen_paper_raw_character_state( $character ),
		'Conflicting world change silently altered campaign or character data.' );
	$preview = ps_pen_paper_preview_rules_migration( $campaign_id, null, (string) $old_rules['revision'], true );
	$check( ! is_wp_error( $preview ) && count( $preview['characters'][0]['conflicts'] ) === 1, 'Inherited migration did not expose the conflicting value.' );
	ob_start(); ps_pen_paper_render_rules( $campaign, true ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'sync_inherited_rules' ) && false !== strpos( $html, 'Universumsänderungen prüfen' ),
		'The campaign did not expose pending rule adoption.' );
	ob_start(); ps_pen_paper_render_rules_migration_preview( $campaign, $preview ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'name="rules_inheritance" value="1"' ) && false !== strpos( $html, $preview['hash'] ),
		'The inherited migration confirmation lost its preview identity.' );
	$token = $preview['characters'][0]['conflicts'][0]['token'];
	$check( is_wp_error( ps_pen_paper_save_rules_migration( $campaign_id, null, (string) $old_rules['revision'], array( $token => 'use_default' ), 'confirmed', str_repeat( '0', 64 ), true ) ) &&
		$old_state === ps_pen_paper_raw_character_state( $character ), 'Stale inherited preview was accepted.' );
	$old_link = ps_pen_paper_get_universe_link( $campaign_id );
	$published = 0;
	$fail_link_event = static function ( $data ) use ( &$published ) {
		if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] && 3 === ++$published ) { $data['post_status'] = 'draft'; }
		return $data;
	};
	add_filter( 'wp_insert_post_data', $fail_link_event );
	try {
		$failed = ps_pen_paper_save_rules_migration( $campaign_id, null, (string) $old_rules['revision'], array( $token => 'use_default' ), 'confirmed', $preview['hash'], true );
	} finally { remove_filter( 'wp_insert_post_data', $fail_link_event ); }
	$check( is_wp_error( $failed ) && $old_state === ps_pen_paper_raw_character_state( $character ) &&
		$old_rules === ps_pen_paper_get_rules( $campaign_id ) && $old_link === ps_pen_paper_get_universe_link( $campaign_id ),
		'Failed inheritance migration did not roll back all documents.' );
	$check( true === ps_pen_paper_save_rules_migration( $campaign_id, null, (string) $old_rules['revision'], array( $token => 'use_default' ), 'confirmed', $preview['hash'], true ),
		'Confirmed inherited migration failed.' );
	$local = ps_pen_paper_get_rules( $campaign_id );
	$check( 5 === ps_pen_paper_raw_character_state( $character )['active']['values'][ $field ] &&
		15 === $local['fields'][0]['max'] && 'Campaign override' === $local['description'] &&
		$world === ps_pen_paper_get_universe_link( $campaign_id )['rules_base'] &&
		true === ps_pen_paper_require_inherited_rules( $campaign_id ), 'Confirmed migration did not atomically update character, rules and base.' );
	$world_input = ps_pen_paper_inheritance_rules_input( $world );
	$world_input['campaign_policy']['globals'] = array();
	$world = ps_pen_paper_save_rules( $universe, $world_input, '4' );
	$check( ! is_wp_error( $world ) && is_wp_error( ps_pen_paper_require_inherited_rules( $campaign_id ) ),
		'Revoking flexibility did not block a conflicting campaign override.' );
	$edit = ps_pen_paper_inheritance_rules_input( $local ); $edit['description'] = 'New world description';
	$local = ps_pen_paper_save_rules( $campaign_id, $edit, (string) $local['revision'] );
	$check( ! is_wp_error( $local ) && true === ps_pen_paper_synchronize_inherited_rules( $campaign ) &&
		$world === ps_pen_paper_get_universe_link( $campaign_id )['rules_base'] &&
		true === ps_pen_paper_require_inherited_rules( $campaign_id ), 'A conflicting override could not be reset and synchronized.' );
	$events = get_posts( array( 'post_type' => 'pspp_event', 'post_status' => 'private', 'post_parent' => $campaign_id, 'posts_per_page' => -1 ) );
	$link_events = 0;
	foreach ( $events as $event_post ) {
		$event = get_post_meta( $event_post->ID, '_pspp_event', true );
		if ( 'universe_linked' !== ( $event['kind'] ?? '' ) ) { continue; }
		$link_events++;
		$check( ps_pen_paper_prepare_import_validate_event( $event, array( 'id' => $event_post->ID, 'parent_id' => $campaign_id ), array() ),
			'Inherited universe link event failed backup validation.' );
	}
	$check( $link_events >= 3, 'Expected inheritance history was not recorded.' );
	$historical_rules = ps_pen_paper_get_rules( $campaign_id );
	$new_field = wp_generate_uuid4();
	$historical_rules['fields'][] = array( 'id' => $new_field, 'label' => 'Later field', 'type' => 'text', 'description' => '', 'required' => false, 'start' => '' );
	$historical_rules['campaign_policy']['fields'][ $new_field ] = array( 'min' => null, 'max' => null );
	$check( ps_pen_paper_valid_rules_document( $historical_rules ), 'Historical backup fixture rules are invalid.' );
	$parsed = array( 'meta' => array( $campaign_id => array( '_pspp_rules' => array( $historical_rules ) ) ) );
	$created_event = null;
	foreach ( $events as $event_post ) {
		$event = get_post_meta( $event_post->ID, '_pspp_event', true );
		if ( 'character_created' === ( $event['kind'] ?? '' ) ) { $created_event = $event; break; }
	}
	$check( null !== $created_event && ps_pen_paper_prepare_import_validate_event( $created_event, array( 'id' => $event_post->ID, 'parent_id' => $campaign_id ), $parsed ),
		'A later flexible field invalidated historical character events in backups.' );
	$record = array( 'id' => $campaign_id, 'type' => 'pspp_campaign', 'parent_id' => 0 );
	$parsed = array( 'records' => array( $universe => array( 'id' => $universe, 'type' => 'pspp_universe' ) ) );
	$archive = array( 'references' => array(), 'warnings' => array() );
	$options = array( 'universe_mode' => 'bind' ); $map = array( $universe => $universe );
	$link = ps_pen_paper_get_universe_link( $campaign_id );
	$check( true === ps_pen_paper_prepare_import_remap_value( '_pspp_universe_link', $link, $record, $parsed, $options, $map, $archive ),
		'An identical inherited rule base could not be rebound.' );
	$link = $old_link;
	$check( is_wp_error( ps_pen_paper_prepare_import_remap_value( '_pspp_universe_link', $link, $record, $parsed, $options, $map, $archive ) ),
		'Import silently rebound an inherited campaign to a different rule base.' );
	$legacy_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Legacy inheritance fixture' ), true );
	$check( is_int( $legacy_id ), 'Legacy campaign creation failed.' ); $scopes[] = $legacy_id;
	add_post_meta( $legacy_id, '_pspp_player', (string) $users['player'] );
	$check( ! is_wp_error( ps_pen_paper_save_universe_link( $legacy_id, $universe, '0', '1', 'confirmed' ) ) &&
		! is_wp_error( ps_pen_paper_adopt_universe_rules( $legacy_id, (string) $world['revision'], 'confirmed' ) ), 'Legacy fixture setup failed.' );
	$legacy = ps_pen_paper_get_rules( $legacy_id );
	$legacy_character = ps_pen_paper_save_character( $legacy_id, 0, $users['gm'], array( 'name' => 'Legacy hero', 'values' => array( $field => '6' ) ), '0', (string) $legacy['revision'] );
	$check( is_int( $legacy_character ), 'Legacy character setup failed: ' . ( is_wp_error( $legacy_character ) ? $legacy_character->get_error_message() : '' ) );
	$legacy_state = ps_pen_paper_raw_character_state( $legacy_character );
	$schema_edit = ps_pen_paper_inheritance_rules_input( $legacy ); $schema_edit['fields'][0]['min'] = '6'; $schema_edit['fields'][0]['start'] = '6';
	foreach ( array( 'draft', 'trash' ) as $status ) {
		wp_update_post( array( 'ID' => $legacy_character, 'post_status' => $status ) );
		$attempt = ps_pen_paper_save_rules( $legacy_id, $schema_edit, (string) $legacy['revision'] );
		$check( is_wp_error( $attempt ) && 'schema_preview_required' === $attempt->get_error_code() &&
			$legacy_state === ps_pen_paper_raw_character_state( $legacy_character ), 'A non-private character bypassed the migration preview.' );
	}
	wp_update_post( array( 'ID' => $legacy_character, 'post_status' => 'private' ) );
	$edit = ps_pen_paper_inheritance_rules_input( $legacy ); $edit['name'] = 'Conflicting legacy name';
	$legacy = ps_pen_paper_save_rules( $legacy_id, $edit, (string) $legacy['revision'] );
	$check( ! is_wp_error( $legacy ) && is_wp_error( ps_pen_paper_preview_legacy_inheritance( $legacy_id ) ),
		'Legacy rules conflicting with fixed world rules were accepted.' );
	$edit['name'] = $world['name'];
	$legacy = ps_pen_paper_save_rules( $legacy_id, $edit, (string) $legacy['revision'] );
	$legacy_preview = ps_pen_paper_preview_legacy_inheritance( $legacy_id );
	$check( ! is_wp_error( $legacy_preview ) &&
		is_wp_error( ps_pen_paper_activate_legacy_inheritance( $legacy_id, $legacy_preview['hash'], '' ) ) &&
		is_wp_error( ps_pen_paper_activate_legacy_inheritance( $legacy_id, str_repeat( '0', 64 ), 'confirmed' ) ),
		'Legacy connection did not require a current confirmed preview.' );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_preview_legacy_inheritance( $legacy_id ) ) &&
		is_wp_error( ps_pen_paper_activate_legacy_inheritance( $legacy_id, $legacy_preview['hash'], 'confirmed' ) ),
		'A campaign player could activate legacy inheritance.' );
	wp_set_current_user( $users['gm'] );
	ob_start(); ps_pen_paper_render_rules( ps_pen_paper_get_campaign( $legacy_id ), true ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'activate_legacy_inheritance' ), 'Legacy connection form was not shown.' );
	$legacy_link = ps_pen_paper_get_universe_link( $legacy_id ); $published = 0;
	$fail_legacy_link = static function ( $data ) use ( &$published ) {
		if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] && 2 === ++$published ) { $data['post_status'] = 'draft'; }
		return $data;
	};
	add_filter( 'wp_insert_post_data', $fail_legacy_link );
	try { $failed = ps_pen_paper_activate_legacy_inheritance( $legacy_id, $legacy_preview['hash'], 'confirmed' ); }
	finally { remove_filter( 'wp_insert_post_data', $fail_legacy_link ); }
	$check( is_wp_error( $failed ) && $legacy === ps_pen_paper_get_rules( $legacy_id ) &&
		$legacy_link === ps_pen_paper_get_universe_link( $legacy_id ) && $legacy_state === ps_pen_paper_raw_character_state( $legacy_character ),
		'Failed legacy connection did not roll back its documents.' );
	$check( true === ps_pen_paper_activate_legacy_inheritance( $legacy_id, $legacy_preview['hash'], 'confirmed' ) &&
		$world === ps_pen_paper_get_universe_link( $legacy_id )['rules_base'] &&
		$legacy_state === ps_pen_paper_raw_character_state( $legacy_character ) &&
		true === ps_pen_paper_require_inherited_rules( $legacy_id ), 'Legacy connection changed character data or failed to activate.' );
	$content_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Content fixture' ), true );
	$check( is_int( $content_id ), 'Content campaign creation failed.' ); $scopes[] = $content_id;
	$check( ! is_wp_error( ps_pen_paper_link_new_campaign( $content_id, $universe, '1' ) ), 'Content campaign linking failed.' );
	$npc = wp_insert_post( array( 'post_type' => 'pspp_npc', 'post_status' => 'private', 'post_parent' => $content_id, 'post_title' => 'Content fixture NPC' ), true );
	$check( is_int( $npc ), 'NPC fixture creation failed.' );
	$before_content = ps_pen_paper_get_rules( $content_id );
	$wider = $before_content; $wider['fields'][0]['max']++; $wider['dice'][] = '6';
	$narrower = $before_content; $narrower['fields'][0]['min']++;
	$check( true === ps_pen_paper_inheritance_content_conflict( $content_id, $before_content, $narrower ),
		'An independently defined NPC blocked an unrelated rule change.' );
	$loot = wp_insert_post( array( 'post_type' => 'pspp_loot_table', 'post_status' => 'private', 'post_parent' => $content_id, 'post_title' => 'Content fixture loot' ), true );
	$check( is_int( $loot ), 'Loot fixture creation failed.' );
	$loot_row = array( 'from' => 1, 'to' => 20, 'label' => 'Nothing', 'quantity' => '0', 'money' => '0', 'item' => null );
	$loot_state = array( 'revision' => 1, 'table' => array( 'name' => 'Empty rewards', 'die' => '20', 'rows' => array( $loot_row ), 'currency' => null ) );
	$check( ps_pen_paper_valid_loot_table( $loot_state ), 'Loot compatibility fixture invalid.' );
	update_post_meta( $loot, '_pspp_loot_table', $loot_state );
	$without_die = $before_content; $without_die['dice'] = array( '6' );
	$check( true === ps_pen_paper_inheritance_content_conflict( $content_id, $before_content, $wider ) &&
		true === ps_pen_paper_inheritance_content_conflict( $content_id, $before_content, $narrower ) &&
		is_wp_error( ps_pen_paper_inheritance_content_conflict( $content_id, $before_content, $without_die ) ),
		'Loot compatibility did not distinguish unrelated changes from a removed die.' );
	$loot_conflict = ps_pen_paper_inheritance_content_conflict( $content_id, $before_content, $without_die );
	$check( false !== strpos( $loot_conflict->get_error_message(), '#' . $loot ), 'Loot conflict did not identify the affected table to its manager.' );
	wp_set_current_user( $users['player'] );
	$loot_conflict = ps_pen_paper_inheritance_content_conflict( $content_id, $before_content, $without_die );
	$check( false === strpos( $loot_conflict->get_error_message(), '#' . $loot ), 'Loot conflict exposed a private table identifier to a player.' );
	wp_set_current_user( $users['gm'] );
	$nested_row = $loot_row; $nested_row['to'] = 6;
	$nested = array( 'revision' => 1, 'table' => array( 'name' => 'Nested', 'die' => '6', 'rows' => array( $nested_row ), 'currency' => null ) );
	$loot_state['table']['rows'][0]['nested'] = array( 'table_id' => 999999, 'table' => $nested );
	$check( ps_pen_paper_valid_loot_table( $loot_state ), 'Nested loot fixture invalid.' );
	update_post_meta( $loot, '_pspp_loot_table', $loot_state );
	$check( is_wp_error( ps_pen_paper_inheritance_content_conflict( $content_id, $wider, $narrower ) ),
		'Nested loot die removal was not detected.' );
	unset( $loot_state['table']['rows'][0]['nested'] );
	$item = array( 'name' => 'Reward', 'description' => '', 'kind' => 'supply', 'rarity' => 'normal', 'rarity_label' => 'Normal', 'color' => '#718096',
		'asset_id' => 0, 'properties' => array(), 'bonuses' => array(), 'binding' => 'bound',
		'use_rule' => array( 'condition' => 'test', 'expression' => 'W6', 'attribute' => '', 'threshold' => 1,
			'failure' => 'keep', 'effects' => array( $field => 1 ), 'temporary' => false, 'rounds' => 0 ) );
	$loot_state['table']['rows'][0]['item'] = array( 'source_id' => 1, 'scope_id' => (int) $content_id, 'revision' => 1, 'item' => $item );
	$loot_state['table']['rows'][0]['quantity'] = '1';
	$check( ps_pen_paper_valid_loot_table( $loot_state ) && ps_pen_paper_inheritance_loot_compatible( $loot_state, $wider ) &&
		! ps_pen_paper_inheritance_loot_compatible( $loot_state, $narrower ), 'Loot reward use-rule die compatibility was not checked.' );
	$removed_field = $wider; $removed_field['fields'] = array();
	$check( ! ps_pen_paper_inheritance_loot_compatible( $loot_state, $removed_field ), 'Loot reward use-rule field removal was not detected.' );
	$loot_state['table']['rows'][0]['item'] = null; $loot_state['table']['rows'][0]['quantity'] = '0';
	update_post_meta( $loot, '_pspp_loot_table', $loot_state );
	$item_id = wp_insert_post( array( 'post_type' => 'pspp_item', 'post_status' => 'private', 'post_parent' => $content_id, 'post_title' => 'Item fixture' ), true );
	$check( is_int( $item_id ), 'Item template fixture creation failed.' );
	update_post_meta( $item_id, '_pspp_item', array( 'revision' => 1, 'item' => $item ) );
	$check( true === ps_pen_paper_inheritance_content_conflict( $content_id, $narrower, $wider ) &&
		is_wp_error( ps_pen_paper_inheritance_content_conflict( $content_id, $wider, $narrower ) ),
		'Item templates did not distinguish compatible rules from a removed use-rule die.' );
	$check( (bool) wp_delete_post( $item_id, true ), 'Item template fixture cleanup failed.' );
	$world_input = ps_pen_paper_inheritance_rules_input( $world );
	$world_input['fields'][0]['max'] = '21'; $world_input['campaign_policy']['fields'][ $field ]['max'] = 25; $world_input['dice'][] = '6';
	$world = ps_pen_paper_save_rules( $universe, $world_input, (string) $world['revision'] );
	$check( ! is_wp_error( $world ) && $world === ps_pen_paper_get_universe_link( $content_id )['rules_base'] &&
		21 === ps_pen_paper_get_rules( $content_id )['fields'][0]['max'],
		'Compatible changes were blocked merely because prepared content exists.' );
	$pending_character = wp_insert_post( array( 'post_type' => 'pspp_character', 'post_status' => 'private', 'post_parent' => $content_id, 'post_author' => $users['gm'], 'post_title' => 'Pending use fixture' ), true );
	$check( is_int( $pending_character ), 'Pending-use character fixture failed.' );
	update_post_meta( $pending_character, '_pspp_character', $legacy_state );
	$uuid = wp_generate_uuid4();
	$gear = array( 'revision' => 1, 'active' => array( 'items' => array( $uuid => array( 'source_id' => 1, 'scope_id' => (int) $content_id, 'revision' => 1, 'item' => $item ) ), 'slots' => array() ),
		'pending' => null, 'uses' => array( 'pending' => array( $uuid => array( 'context' => 'profile', 'condition' => 'approval', 'round' => 0, 'actor_id' => $users['gm'] ) ), 'effects' => array(), 'receipts' => array() ) );
	$check( ! is_wp_error( ps_pen_paper_validate_gear_state( $gear ) ), 'Pending-use gear fixture invalid.' );
	update_post_meta( $pending_character, '_pspp_gear', $gear );
	$previous_content_rules = ps_pen_paper_get_rules( $content_id );
	$world_input = ps_pen_paper_inheritance_rules_input( $world ); $world_input['dice'] = array( '20' );
	$world = ps_pen_paper_save_rules( $universe, $world_input, (string) $world['revision'] );
	$check( ! is_wp_error( $world ) && $previous_content_rules === ps_pen_paper_get_rules( $content_id ) &&
		$gear === ps_pen_paper_gear_state( $pending_character ) && is_wp_error( ps_pen_paper_require_inherited_rules( $content_id ) ),
		'Automatic inheritance broke a pending item-use request.' );
	unset( $gear['uses']['pending'][ $uuid ] ); $gear['revision']++;
	update_post_meta( $pending_character, '_pspp_gear', $gear );
	$check( true === ps_pen_paper_synchronize_inherited_rules( ps_pen_paper_get_campaign( $content_id ) ) &&
		$world === ps_pen_paper_get_universe_link( $content_id )['rules_base'],
		'Inheritance remained blocked after the pending request was cleared.' );
	$world_input = ps_pen_paper_inheritance_rules_input( $world ); $world_input['campaign_policy']['globals'][] = 'conditions';
	$world = ps_pen_paper_save_rules( $universe, $world_input, (string) $world['revision'] );
	$check( ! is_wp_error( $world ), 'Condition flexibility could not be enabled.' );
	$world_policy = ps_pen_paper_condition_policy_default(); $world_policy['revision'] = 1; $world_policy['campaign_local'] = true;
	$check( true === ps_pen_paper_save_condition_policy( ps_pen_paper_get_universe( $universe ), $world_policy, '0' ), 'World condition policy fixture failed.' );
	$content_campaign = ps_pen_paper_get_campaign( $content_id );
	$local_policy = $world_policy; $condition = wp_generate_uuid4();
	$local_policy['definitions'][ $condition ] = array( 'id' => $condition, 'label' => 'Campaign condition', 'description' => '',
		'block_actions' => false, 'block_join' => false, 'modifiers' => array( $field => 1 ), 'threshold' => null );
	$check( true === ps_pen_paper_save_condition_policy( $content_campaign, $local_policy, '0' ) &&
		isset( ps_pen_paper_condition_policy( $content_campaign )['definitions'][ $condition ] ) &&
		empty( ps_pen_paper_condition_policy( ps_pen_paper_get_universe( $universe ) )['definitions'] ),
		'Flexible campaign conditions were not independent of the universe.' );
	$condition_rules = ps_pen_paper_get_rules( $content_id );
	$without_field = $condition_rules;
	$without_field['fields'] = array_values( array_filter( $condition_rules['fields'], static function ( $entry ) use ( $field ) { return $entry['id'] !== $field; } ) );
	$check( is_wp_error( ps_pen_paper_inheritance_content_conflict( $content_id, $condition_rules, $without_field ) ),
		'Inheritance removed a field still used by a campaign condition.' );
	$escalated = $local_policy; $escalated['revision'] = 2; $escalated['campaign_universe'] = true;
	$check( is_wp_error( ps_pen_paper_save_condition_policy( $content_campaign, $escalated, '1' ) ),
		'Campaign condition policy exceeded universe permissions.' );
	$world_input = ps_pen_paper_inheritance_rules_input( $world );
	$world_input['campaign_policy']['globals'] = array_values( array_diff( $world_input['campaign_policy']['globals'], array( 'conditions' ) ) );
	$world = ps_pen_paper_save_rules( $universe, $world_input, (string) $world['revision'] );
	$check( ! is_wp_error( $world ) && is_wp_error( ps_pen_paper_require_inherited_rules( $content_id ) ) &&
		isset( ps_pen_paper_condition_policy( $content_campaign )['definitions'][ $condition ] ) &&
		is_wp_error( ps_pen_paper_save_condition_policy( $content_campaign, $escalated, '1' ) ),
		'Revoking condition flexibility silently discarded the campaign policy or allowed editing.' );
	ob_start(); ps_pen_paper_render_condition_policy( $content_campaign, 0 ); $condition_html = ob_get_clean();
	$check( false !== strpos( $condition_html, 'reset_condition_policy' ), 'A revoked condition override could not be reset in the UI.' );
	$check( is_wp_error( ps_pen_paper_reset_condition_policy( $content_campaign, '0' ) ) &&
		true === ps_pen_paper_reset_condition_policy( $content_campaign, '1' ) &&
		true === ps_pen_paper_synchronize_inherited_rules( $content_campaign ) &&
		empty( ps_pen_paper_condition_policy( $content_campaign )['definitions'] ),
		'Confirmed reset did not restore inherited conditions.' );
	WP_CLI::success( 'Fixed and flexible rules, bounds, automatic rebasing and confirmed character migration passed.' );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $scopes as $scope ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_asset', 'pspp_event', 'pspp_npc', 'pspp_item', 'pspp_loot_table' ), 'post_status' => array( 'private', 'draft', 'trash' ), 'post_parent' => $scope, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
		wp_delete_post( $scope, true );
	}
	foreach ( $users as $id ) { is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ); }
}
