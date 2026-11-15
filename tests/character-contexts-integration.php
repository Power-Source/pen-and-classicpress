<?php
/**
 * Run with WP-CLI against a disposable development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once dirname( __DIR__ ) . '/includes/import-prepare.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
ps_pen_paper_register_campaign_type();
ps_pen_paper_register_universe_type();
ps_pen_paper_register_character_type();
ps_pen_paper_register_event_type();
ps_pen_paper_register_roll_type();
ps_pen_paper_register_item_type();
$original_user = get_current_user_id();
$users = array();
$parents = array();
$check = function ( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
};
try {
	foreach ( array( 'gm', 'assistant', 'player', 'outsider', 'newcomer' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-context-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( ! is_wp_error( $id ), 'Fixture user failed.' );
		$users[ $role ] = $id;
	}
	get_userdata( $users['gm'] )->add_cap( 'ps_pen_paper_create_campaigns' );
	wp_set_current_user( $users['gm'] );
	$universe_id = ps_pen_paper_save_universe( 0, array( 'name' => 'WORLD_SECRET', 'description' => 'WORLD_DESCRIPTION_SECRET' ), '0' );
	$check( ! is_wp_error( $universe_id ), 'Universe fixture failed.' );
	$parents[] = $universe_id;
	$universe = ps_pen_paper_get_universe( $universe_id );
	$input = array( 'name' => 'Basisvorlage', 'description' => '', 'dice' => array( '6' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Mut', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '20', 'start' => '5' ),
		array( 'id' => '', 'label' => 'Stufe', 'type' => 'number', 'description' => '', 'min' => '1', 'max' => '20', 'start' => '3' ),
		array( 'id' => '', 'label' => 'Ausdauer', 'type' => 'resource', 'description' => '', 'min' => '0', 'max' => '100', 'start' => '10' ),
	) );
	$rules = ps_pen_paper_save_rules( $universe_id, $input, '0' );
	$check( ! is_wp_error( $rules ), 'Universe rules did not save.' );
	$attribute = $rules['fields'][0]['id'];
	$level = $rules['fields'][1]['id'];
	$resource = $rules['fields'][2]['id'];
	$import_input = $input;
	foreach ( $import_input['fields'] as $index => &$field ) { $field['id'] = $rules['fields'][ $index ]['id']; }
	unset( $field );
	$import_input['fields'][] = array( 'id' => '', 'label' => 'Herkunft', 'type' => 'choice', 'description' => '', 'choices' => "Küste\nBerge", 'start' => 'Küste' );
	$import_input['allocation_mode'] = 'configured';
	$import_input['allocation'] = array( 'fields' => array( $attribute ), 'points' => '5' );
	$import_rules = ps_pen_paper_validate_rules( $import_input, $rules );
	$check( ! is_wp_error( $import_rules ) && ps_pen_paper_import_rules_valid( $import_rules ), 'Import rejected canonical choice and allocation rules.' );
	$import_snapshot = array( 'name' => 'Importtest', 'values' => ps_pen_paper_character_values( $import_rules ) );
	$import_state = array( 'revision' => 1, 'active' => $import_snapshot, 'pending' => null );
	$check( ps_pen_paper_import_character_valid( $import_state, $import_rules ), 'Import rejected a canonical allocated choice snapshot.' );
	$bad_import_state = $import_state;
	$bad_import_state['active']['values'][ $import_rules['fields'][3]['id'] ] = 'Unbekannt';
	$check( ! ps_pen_paper_import_character_valid( $bad_import_state, $import_rules ), 'Import accepted an unknown choice.' );
	$bad_import_state = $import_state;
	$bad_import_state['active']['values'][ $attribute ] = 4;
	$check( ! ps_pen_paper_import_character_valid( $bad_import_state, $import_rules ), 'Import bypassed the configured attribute allocation.' );
	$bad_import_state = $import_state;
	$bad_import_state['active']['extra'] = true;
	$check( ! ps_pen_paper_import_character_valid( $bad_import_state, $import_rules ), 'Import accepted an extra snapshot key.' );
	$base_input = array( 'name' => 'Basis C:\Welt', 'values' => array( $attribute => '5', $level => '3', $resource => array( 'current' => '10', 'maximum' => '20' ) ) );
	$base_id = ps_pen_paper_save_character( $universe_id, 0, $users['player'], $base_input, '0', '1' );
	$check( ! is_wp_error( $base_id ), 'Universe base character did not save.' );
	$base = get_post( $base_id );
	$source = ps_pen_paper_raw_character_state( $base_id );
	$universe_migration_input = $input;
	foreach ( $universe_migration_input['fields'] as $index => &$field ) { $field['id'] = $rules['fields'][ $index ]['id']; }
	unset( $field );
	$universe_migration_input['fields'][] = array( 'id' => '', 'label' => 'Notiz', 'type' => 'text', 'description' => '', 'start' => '' );
	$universe_migration_preview = ps_pen_paper_preview_rules_migration( $universe_id, $universe_migration_input, '1' );
	$check( ! is_wp_error( $universe_migration_preview ), 'Universe migration did not produce a usable preview.' );
	ob_start();
	ps_pen_paper_render_rules_migration_preview( $universe, $universe_migration_preview );
	$universe_migration_html = ob_get_clean();
	$check( false !== strpos( $universe_migration_html, 'name="pspp_operation" value="save_universe_rules"' ) &&
		false !== strpos( $universe_migration_html, 'name="rules_migration" value="1"' ) &&
		false !== strpos( $universe_migration_html, 'value="' . wp_create_nonce( 'pspp_campaign_' . $universe_id ) . '"' ) &&
		false !== strpos( $universe_migration_html, 'name="migration_preview_hash" value="' . $universe_migration_preview['hash'] . '"' ),
		'Universe migration posted to the campaign-only action or lost its nonce/preview hash.' );
	$source['revision']++;
	$source['pending'] = $source['active'];
	$source['pending']['name'] = 'PENDING_SECRET';
	update_post_meta( $base_id, '_pspp_character', wp_slash( $source ) );
	$other_base_id = ps_pen_paper_save_character( $universe_id, 0, $users['outsider'], $base_input, '0', '1' );
	$check( ! is_wp_error( $other_base_id ), 'Nonmember base fixture failed.' );
	$campaigns = array();
	for ( $i = 0; $i < 4; $i++ ) {
		$id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Context fixture ' . $i ), true );
		$check( ! is_wp_error( $id ) && $id, 'Campaign fixture failed.' );
		$parents[] = $id;
		$campaigns[] = ps_pen_paper_get_campaign( $id );
		$check( (bool) add_post_meta( $id, '_pspp_gm', (string) $users['gm'] ) && (bool) add_post_meta( $id, '_pspp_player', (string) $users['player'] ), 'Fixture membership failed.' );
		$check( ! is_wp_error( ps_pen_paper_save_universe_link( $id, $universe_id, '0', '1', 'confirmed' ) ), 'Campaign association failed.' );
	}
	add_post_meta( $campaigns[0]->ID, '_pspp_gm', (string) $users['assistant'] );
	$check( is_wp_error( ps_pen_paper_adopt_universe_rules( $campaigns[0]->ID, '1', '' ) ) && 0 === ps_pen_paper_get_rules( $campaigns[0]->ID )['revision'], 'Unconfirmed rule adoption wrote data.' );
	foreach ( array_slice( $campaigns, 0, 3 ) as $campaign ) {
		$adopted = ps_pen_paper_adopt_universe_rules( $campaign->ID, '1', 'confirmed' );
		$check( ! is_wp_error( $adopted ) && $adopted['fields'] === $rules['fields'], 'Template adoption changed stable IDs or values.' );
	}
	$check( is_wp_error( ps_pen_paper_adopt_universe_rules( $campaigns[0]->ID, '1', 'confirmed' ) ), 'Existing campaign rules overwritten.' );
	$check( is_wp_error( ps_pen_paper_attach_base_character( $campaigns[0]->ID, $other_base_id, '1', '1', 'shared', 'confirmed' ) ), 'Import silently admitted a nonmember.' );
	$check( is_wp_error( ps_pen_paper_attach_base_character( $campaigns[0]->ID, $base_id, '1', '1', 'shared', 'confirmed' ) ), 'Stale source revision accepted.' );
	$check( is_wp_error( ps_pen_paper_attach_base_character( $campaigns[0]->ID, $base_id, '2', '1', 'shared', '' ) ), 'Unconfirmed import accepted.' );
	$ids = array();
	foreach ( array( 'shared', 'copy', 'shared' ) as $i => $mode ) {
		$ids[ $i ] = ps_pen_paper_attach_base_character( $campaigns[ $i ]->ID, $base_id, '2', '1', $mode, 'confirmed' );
		$check( ! is_wp_error( $ids[ $i ] ), 'Character attachment failed.' );
		$state = ps_pen_paper_character_state( $campaigns[ $i ], get_post( $ids[ $i ] ) );
		$check( 'Basis C:\Welt' === $state['active']['name'] && null === $state['pending'], 'Import leaked unapproved base data or corrupted escaping.' );
	}
	$check( $source === ps_pen_paper_raw_character_state( $base_id ), 'Import changed the base character.' );
	$shared_migration_input = $input;
	foreach ( $shared_migration_input['fields'] as $index => &$field ) { $field['id'] = $rules['fields'][ $index ]['id']; }
	unset( $field );
	$shared_migration_input['fields'][0]['max'] = '30';
	$shared_migration = ps_pen_paper_preview_rules_migration( $universe_id, $shared_migration_input, '1' );
	$check( is_wp_error( $shared_migration ) && 'shared_schema' === $shared_migration->get_error_code() &&
		$source === ps_pen_paper_raw_character_state( $base_id ), 'Universe schema migration orphaned active shared campaign sheets.' );
	wp_set_current_user( $users['assistant'] );
	$check( is_wp_error( ps_pen_paper_save_character( $universe_id, $base_id, $users['player'], $base_input, '2', '1' ) ) && is_wp_error( ps_pen_paper_adopt_universe_rules( $campaigns[3]->ID, '1', 'confirmed' ) ), 'Campaign GM gained universe administration.' );
	$shared_character = get_post( $ids[0] );
	$check( ! ps_pen_paper_can_edit_character( $campaigns[0], $shared_character ) && is_wp_error( ps_pen_paper_save_character( $campaigns[0]->ID, $ids[0], $users['player'], $base_input, '1', '1' ) ), 'Campaign edited shared base through alias.' );
	wp_set_current_user( $users['player'] );
	$check( null === ps_pen_paper_get_character( $universe, $base_id ) && is_wp_error( ps_pen_paper_get_events( $universe ) ), 'Base owner gained private universe access.' );
	$state = ps_pen_paper_character_state( $campaigns[0], $shared_character );
	$old_token = ps_pen_paper_character_token( $shared_character, $state );
	$roll_input = array( 'expression' => 'W6', 'visibility' => 'group', 'character' => $old_token, 'attribute' => $attribute, 'label' => '' );
	$old_roll_id = ps_pen_paper_roll_dice( $campaigns[0]->ID, $roll_input, '1' );
	$check( ! is_wp_error( $old_roll_id ), 'Shared character could not roll.' );
	$old_roll = get_post_meta( $old_roll_id, '_pspp_roll', true );
	$check( 5 === $old_roll['attribute']['value'] && 2 === $old_roll['character']['context']['source_revision'], 'Initial roll lost source snapshot.' );
	$check( is_wp_error( ps_pen_paper_save_character_context( $campaigns[0]->ID, $ids[0], '1', '2', '1', array( $attribute => '2' ), '' ) ), 'Player granted their own context override.' );
	ob_start();
	ps_pen_paper_render_table_sheet( $campaigns[0], 0 );
	$html = ob_get_clean();
	$check( false === strpos( $html, 'PENDING_SECRET' ) && false === strpos( $html, 'WORLD_SECRET' ) && false === strpos( $html, 'WORLD_DESCRIPTION_SECRET' ), 'Effective sheet exposed private universe information.' );
	wp_set_current_user( $users['gm'] );
	$base_input['values'][ $attribute ] = '8';
	$check( $base_id === ps_pen_paper_save_character( $universe_id, $base_id, $users['player'], $base_input, '2', '1' ), 'Base progress did not save.' );
	$check( 8 === ps_pen_paper_character_state( $campaigns[0], $shared_character )['active']['values'][ $attribute ] &&
		8 === ps_pen_paper_character_state( $campaigns[2], get_post( $ids[2] ) )['active']['values'][ $attribute ] &&
		5 === ps_pen_paper_character_state( $campaigns[1], get_post( $ids[1] ) )['active']['values'][ $attribute ], 'Shared progress or independent copy behavior failed.' );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_roll_dice( $campaigns[0]->ID, $roll_input, '1' ) ), 'Roll accepted stale shared base version.' );
	wp_set_current_user( $users['assistant'] );
	foreach ( array( array( $attribute => '21' ), array( $resource => '2' ), array( 'unknown' => '' ), array( $attribute => array( '2' ) ) ) as $bad ) {
		$check( is_wp_error( ps_pen_paper_save_character_context( $campaigns[0]->ID, $ids[0], '1', '3', '1', $bad, '' ) ), 'Invalid or out-of-range override accepted.' );
	}
	$check( is_wp_error( ps_pen_paper_save_character_context( $campaigns[0]->ID, $ids[0], '1', '2', '1', array(), '' ) ), 'Context saved against stale base.' );
	$before_base = ps_pen_paper_raw_character_state( $base_id );
	$check( true === ps_pen_paper_save_character_context( $campaigns[0]->ID, $ids[0], '1', '3', '1', array( $attribute => '2', $level => '1' ), 'NOTE_PRIVATE' ), 'Campaign-only GM could not set context.' );
	$state = ps_pen_paper_character_state( $campaigns[0], $shared_character );
	wp_set_current_user( $users['gm'] );
	$check( 2 === $state['active']['values'][ $attribute ] && 1 === $state['active']['values'][ $level ] &&
		$before_base === ps_pen_paper_raw_character_state( $base_id ) &&
		8 === ps_pen_paper_character_state( $campaigns[2], get_post( $ids[2] ) )['active']['values'][ $attribute ], 'Context damaged base or another campaign.' );
	$event = ps_pen_paper_get_events( $campaigns[0] )['events'][0];
	$check( 'character_context' === $event['kind'] && 8 === $event['effective_before']['values'][ $attribute ] && 2 === $event['effective_after']['values'][ $attribute ], 'Context history did not capture actual effective before/after values.' );
	ob_start();
	ps_pen_paper_render_characters( $campaigns[0], $ids[0] );
	$html = ob_get_clean();
	$check( false !== strpos( $html, 'Kampagnenauflagen bearbeiten' ) && false !== strpos( $html, 'NOTE_PRIVATE' ) && false === strpos( $html, 'name="pspp_operation" value="save_character"' ), 'Shared character context form missing or ordinary edit form exposed.' );
	wp_set_current_user( $users['player'] );
	$roll_input['character'] = ps_pen_paper_character_token( $shared_character, $state );
	$new_roll_id = ps_pen_paper_roll_dice( $campaigns[0]->ID, $roll_input, '1' );
	$check( ! is_wp_error( $new_roll_id ), 'Effective context roll failed.' );
	$new_roll = get_post_meta( $new_roll_id, '_pspp_roll', true );
	$check( 2 === $new_roll['attribute']['value'] && $new_roll['total'] === array_sum( $new_roll['groups'][0]['rolls'] ) + 2 &&
		3 === $new_roll['character']['context']['source_revision'] && 2 === $new_roll['character']['context']['revision'] &&
		false === strpos( wp_json_encode( $new_roll ), 'NOTE_PRIVATE' ), 'Roll did not capture effective value/provenance or leaked context note.' );
	$broken_roll = $new_roll;
	$broken_roll['character']['context']['mode'] = 'unknown';
	update_post_meta( $new_roll_id, '_pspp_roll', wp_slash( $broken_roll ) );
	$check( is_wp_error( ps_pen_paper_get_roll( $campaigns[0], $new_roll_id ) ), 'Corrupt stored roll context was displayed.' );
	update_post_meta( $new_roll_id, '_pspp_roll', wp_slash( $new_roll ) );
	wp_set_current_user( $users['outsider'] );
	add_post_meta( $campaigns[0]->ID, '_pspp_player', (string) $users['outsider'] );
	$check( null === ps_pen_paper_get_character( $campaigns[0], $ids[0] ), 'Another member read private contextual character.' );
	$check( ! is_wp_error( ps_pen_paper_get_roll( $campaigns[0], $new_roll_id ) ) && false === strpos( wp_json_encode( ps_pen_paper_get_events( $campaigns[0] ) ), 'NOTE_PRIVATE' ), 'Public roll or private context history visibility failed.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_save_character_context( $campaigns[0]->ID, $ids[0], '2', '3', '1', array( $attribute => '', $level => '' ), '' ) &&
		8 === ps_pen_paper_character_state( $campaigns[0], $shared_character )['active']['values'][ $attribute ], 'Clearing context did not restore base value.' );
	$check( $old_roll === get_post_meta( $old_roll_id, '_pspp_roll', true ) && $new_roll === get_post_meta( $new_roll_id, '_pspp_roll', true ), 'Later progress or context changed historical rolls.' );
	$check( is_wp_error( ps_pen_paper_save_universe_link( $campaigns[0]->ID, 0, '1', '0', 'confirmed' ) ), 'Shared association was orphaned by unlink.' );
	$check( ! is_wp_error( ps_pen_paper_save_universe_link( $campaigns[1]->ID, 0, '1', '0', 'confirmed' ) ), 'Independent copy prevented harmless unlink.' );
	wp_set_current_user( $users['player'] );
	$copy_input = $base_input;
	$copy_input['values'][ $attribute ] = '11';
	$check( $ids[1] === ps_pen_paper_save_character( $campaigns[1]->ID, $ids[1], $users['player'], $copy_input, '1', '1' ), 'Copy owner could not request progress.' );
	$check( 5 === ps_pen_paper_character_state( $campaigns[1], get_post( $ids[1] ) )['active']['values'][ $attribute ], 'Unapproved copy progress became active.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_review_character( $campaigns[1]->ID, $ids[1], 'approve', '2' ) &&
		11 === ps_pen_paper_character_state( $campaigns[1], get_post( $ids[1] ) )['active']['values'][ $attribute ] &&
		$before_base === ps_pen_paper_raw_character_state( $base_id ), 'Copy approval changed original base.' );
	wp_update_post( array( 'ID' => $base_id, 'post_status' => 'draft' ) );
	$check( is_wp_error( ps_pen_paper_character_state( $campaigns[0], $shared_character ) ) &&
		11 === ps_pen_paper_character_state( $campaigns[1], get_post( $ids[1] ) )['active']['values'][ $attribute ], 'Missing shared base used fallback or broke independent copy.' );
	wp_update_post( array( 'ID' => $base_id, 'post_status' => 'private' ) );
	$baseline = ps_pen_paper_raw_character_state( $ids[0] );
	$journal = ps_pen_paper_get_events( $campaigns[0] );
	$fail_publish = static function ( $data ) {
		if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) { $data['post_status'] = 'draft'; }
		return $data;
	};
	add_filter( 'wp_insert_post_data', $fail_publish );
	try {
		$check( is_wp_error( ps_pen_paper_save_character_context( $campaigns[0]->ID, $ids[0], '3', '3', '1', array( $attribute => '1' ), 'failure' ) ), 'Failed context event reported success.' );
		$check( is_wp_error( ps_pen_paper_attach_base_character( $campaigns[0]->ID, $base_id, '3', '1', 'copy', 'confirmed' ) ) && 1 === count( ps_pen_paper_get_characters( $campaigns[0] ) ), 'Failed attachment left a character.' );
		$check( is_wp_error( ps_pen_paper_adopt_universe_rules( $campaigns[3]->ID, '1', 'confirmed' ) ) && 0 === ps_pen_paper_get_rules( $campaigns[3]->ID )['revision'], 'Failed template publication left rules.' );
	} finally {
		remove_filter( 'wp_insert_post_data', $fail_publish );
	}
	$check( $baseline === ps_pen_paper_raw_character_state( $ids[0] ) && $journal === ps_pen_paper_get_events( $campaigns[0] ), 'Failure rollback damaged existing state/history.' );
	$copy_campaign = $campaigns[2];
	$copy_id = $ids[2];
	$check( true === ps_pen_paper_save_character_context( $copy_campaign->ID, $copy_id, '1', '3', '1', array( $attribute => '2' ), 'COPY_NOTE' ), 'Copy transition overrides failed.' );
	$copy_before = ps_pen_paper_raw_character_state( $copy_id );
	$copy_journal = ps_pen_paper_get_events( $copy_campaign );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_copy_shared_character( $copy_campaign->ID, $copy_id, '2', '3', '1', 'confirmed' ) ), 'Owner detached a shared character without GM authority.' );
	ob_start(); ps_pen_paper_render_characters( $copy_campaign, $copy_id ); $copy_html = ob_get_clean();
	$check( false === strpos( $copy_html, 'value="copy_shared_character"' ), 'Owner received the GM copy form.' );
	wp_set_current_user( $users['assistant'] );
	$check( is_wp_error( ps_pen_paper_copy_shared_character( $copy_campaign->ID, $copy_id, '2', '3', '1', 'confirmed' ) ), 'GM of another campaign detached the character.' );
	wp_set_current_user( $users['gm'] );
	foreach ( array( array( '1', '3', '1', 'confirmed' ), array( '2', '2', '1', 'confirmed' ), array( '2', '3', '0', 'confirmed' ), array( '2', '3', '1', '' ) ) as $tokens ) {
		$check( is_wp_error( ps_pen_paper_copy_shared_character( $copy_campaign->ID, $copy_id, ...$tokens ) ), 'Stale or unconfirmed copy transition succeeded.' );
	}
	add_filter( 'wp_insert_post_data', $fail_publish );
	try {
		$check( is_wp_error( ps_pen_paper_copy_shared_character( $copy_campaign->ID, $copy_id, '2', '3', '1', 'confirmed' ) ), 'Failed transition publication reported success.' );
	} finally { remove_filter( 'wp_insert_post_data', $fail_publish ); }
	$check( $copy_before === ps_pen_paper_raw_character_state( $copy_id ) && $copy_journal === ps_pen_paper_get_events( $copy_campaign ), 'Rejected transition changed the character or its history.' );

	define( 'CPC_CORE_PLUGINS', 'core-profile,core-activity' );
	require_once dirname( __DIR__, 2 ) . '/ps-community/activity/cpc_profile_tabs.php';
	$check( true === ps_pen_paper_save_equipment_schema( $copy_campaign, array( 'slots' => 'kopf | Kopf | hut', 'rarities' => 'normal | Normal | #718096' ), '0' ), 'Transition gear schema failed.' );
	$copy_item = ps_pen_paper_save_item( $copy_campaign, 0, array( 'name' => 'Copy hat', 'description' => '', 'kind' => 'hut', 'rarity' => 'normal', 'binding' => 'tradeable', 'properties' => '', 'bonuses' => array( $attribute => '2' ) ), '0' );
	$check( is_int( $copy_item ) && true === ps_pen_paper_save_gear( $copy_campaign, $copy_id, 'grant_item', array( 'item_id' => (string) $copy_item, 'item_revision' => '1' ), '0' ), 'Transition item issuance failed.' );
	$copy_instance = array_key_first( ps_pen_paper_gear_state( $copy_id )['active']['items'] );
	$check( true === ps_pen_paper_save_gear( $copy_campaign, $copy_id, 'equip_items', array( 'slots' => array( 'kopf' => $copy_instance ) ), '1' ), 'Transition equipment failed.' );
	$copy_gear = ps_pen_paper_gear_state( $copy_id );
	$check( true === ps_pen_paper_save_currency( $universe, array( 'name' => 'Credits', 'symbol' => 'CR', 'wallet_mode' => 'campaign' ), '0' ) &&
		true === ps_pen_paper_change_wallet( $copy_campaign, $copy_id, 'grant', '7', 'Transition balance', '0', '1' ), 'Transition wallet fixture failed.' );
	$copy_wallet = ps_pen_paper_wallet_context( $copy_campaign, $copy_id )['wallet'];
	$copy_currency = ps_pen_paper_currency_document( $universe_id );
	$shared_currency = $copy_currency; $shared_currency['wallet_mode'] = 'universe';
	$shared_wallet = $copy_wallet; $shared_wallet['balance'] = 19;
	update_post_meta( $universe_id, '_pspp_currency', $shared_currency );
	update_post_meta( $base_id, '_pspp_wallet', $shared_wallet );
	$shared_copy_result = ps_pen_paper_copy_shared_character( $copy_campaign->ID, $copy_id, '2', '3', '1', 'confirmed' );
	$check( is_wp_error( $shared_copy_result ) && 'shared_wallet' === $shared_copy_result->get_error_code() &&
		$shared_wallet === get_post_meta( $base_id, '_pspp_wallet', true ) && $copy_before === ps_pen_paper_raw_character_state( $copy_id ), 'Transition disconnected or copied a shared universe account.' );
	update_post_meta( $universe_id, '_pspp_currency', $copy_currency );
	delete_post_meta( $base_id, '_pspp_wallet' );
	$copy_base_before = ps_pen_paper_raw_character_state( $base_id );
	$copy_base_before['revision']++;
	$copy_base_before['pending'] = $copy_base_before['active'];
	$copy_base_before['pending']['name'] = 'TRANSITION_PENDING_SECRET';
	update_post_meta( $base_id, '_pspp_character', wp_slash( $copy_base_before ) );
	$check( 4 === ps_pen_paper_character_state( $copy_campaign, get_post( $copy_id ) )['active']['values'][ $attribute ], 'Transition fixture did not apply the override and equipment bonus.' );
	ob_start(); ps_pen_paper_render_characters( $copy_campaign, $copy_id ); $copy_html = ob_get_clean();
	$check( false !== strpos( $copy_html, 'value="copy_shared_character"' ) && false === strpos( $copy_html, 'TRANSITION_PENDING_SECRET' ), 'Transition form missing or exposed an unapproved base request.' );
	add_post_meta( $copy_campaign->ID, '_pspp_gm', (string) $users['assistant'] );
	wp_set_current_user( $users['assistant'] );
	$check( true === ps_pen_paper_copy_shared_character( $copy_campaign->ID, $copy_id, '2', '4', '1', 'confirmed' ), 'Campaign-only GM could not independently continue an approved shared character.' );
	$copied = ps_pen_paper_character_state( $copy_campaign, get_post( $copy_id ) );
	$copy_raw = ps_pen_paper_raw_character_state( $copy_id );
	$check( 2 === $copy_raw['active']['values'][ $attribute ] && 4 === $copied['active']['values'][ $attribute ] &&
		'copy' === $copy_raw['context']['mode'] && 'COPY_NOTE' === $copy_raw['context']['note'] && null === $copy_raw['pending'] &&
		$copy_base_before === ps_pen_paper_raw_character_state( $base_id ) && $copy_gear === ps_pen_paper_gear_state( $copy_id ) &&
		$copy_wallet === ps_pen_paper_wallet_context( $copy_campaign, $copy_id )['wallet'], 'Transition lost overrides, copied pending data, doubled bonuses, changed the base, gear or balance.' );
	$cost = ps_pen_paper_character_resource_cost( $copy_campaign, get_post( $copy_id ), $resource, 2 );
	$check( ! is_wp_error( $cost ) && 10 === $cost['before'] && 8 === $cost['after'], 'Independent continuation did not enable personal adventure resource costs.' );
	$copy_event = ps_pen_paper_get_events( $copy_campaign )['events'][0];
	$check( 'shared' === $copy_event['before']['context']['mode'] && 'copy' === $copy_event['after']['context']['mode'] &&
		2 === $copy_event['effective_after']['values'][ $attribute ], 'Transition history lost mode change or recorded an equipment bonus as a base value.' );
	$check( is_wp_error( ps_pen_paper_copy_shared_character( $copy_campaign->ID, $copy_id, '2', '4', '1', 'confirmed' ) ), 'Transition replay changed an independent copy.' );
	wp_set_current_user( $users['gm'] );
	$base_input['values'][ $attribute ] = '9';
	$check( $base_id === ps_pen_paper_save_character( $universe_id, $base_id, $users['player'], $base_input, '4', '1' ) &&
		4 === ps_pen_paper_character_state( $copy_campaign, get_post( $copy_id ) )['active']['values'][ $attribute ] &&
		9 === ps_pen_paper_character_state( $campaigns[0], $shared_character )['active']['values'][ $attribute ], 'Later base changes altered the detached copy or stopped another shared campaign.' );
	$broken = $baseline;
	$broken['context']['overrides']['unknown'] = 1;
	update_post_meta( $ids[0], '_pspp_character', wp_slash( $broken ) );
	$check( is_wp_error( ps_pen_paper_character_state( $campaigns[0], $shared_character ) ), 'Corrupt context silently ignored.' );
	update_post_meta( $ids[0], '_pspp_character', wp_slash( $baseline ) );
	$check( true === ps_pen_paper_copy_shared_character( $campaigns[0]->ID, $ids[0], (string) $baseline['revision'], '5', '1', 'confirmed' ),
		'Could not prepare the independent copy for explicit reattachment.' );
	$detached = ps_pen_paper_raw_character_state( $ids[0] );
	$detached['revision']++;
	$detached['active']['values'][ $attribute ] = 7;
	update_post_meta( $ids[0], '_pspp_character', wp_slash( $detached ) );
	$reattach_preview = ps_pen_paper_preview_character_reattachment( $campaigns[0]->ID, $ids[0] );
	$check( ! is_wp_error( $reattach_preview ) && 1 === count( $reattach_preview['conflicts'] ) &&
		$attribute === $reattach_preview['conflicts'][0]['field_id'], 'Reattachment preview omitted the divergent attribute.' );
	$reattach = ps_pen_paper_reattach_character( $campaigns[0]->ID, $ids[0], (string) $detached['revision'], '5', '1',
		array( $attribute => 'copy' ), 'confirmed' );
	$reattached = ps_pen_paper_raw_character_state( $ids[0] );
	$check( true === $reattach && 'shared' === $reattached['context']['mode'] && 7 === $reattached['context']['overrides'][ $attribute ] &&
		9 === ps_pen_paper_raw_character_state( $base_id )['active']['values'][ $attribute ], 'Reattachment did not preserve the explicitly selected copy value or changed the source.' );
	$check( true === ps_pen_paper_copy_shared_character( $campaigns[0]->ID, $ids[0], (string) $reattached['revision'], '5', '1', 'confirmed' ) &&
		! is_wp_error( ps_pen_paper_save_universe_link( $campaigns[0]->ID, 0, '1', '0', 'confirmed' ) ) &&
		7 === ps_pen_paper_character_state( $campaigns[0], $shared_character )['active']['values'][ $attribute ], 'Detaching the last shared character did not permit a safe universe unlink.' );
	$standalone = ps_pen_paper_export_campaign( $campaigns[0]->ID, 'local' );
	$check( ! is_wp_error( $standalone ), 'Detached campaign could not be exported.' );
	$standalone_parsed = ps_pen_paper_parse_import( json_encode( $standalone, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	$check( ! is_wp_error( $standalone_parsed ), 'Detached campaign export did not parse.' );
	$standalone_options = array( 'operation' => 'copy', 'target_id' => 0, 'universe_mode' => 'none', 'universe_id' => 0, 'users' => array(), 'bases' => array() );
	foreach ( $standalone_parsed['users'] as $source_user => $requirement ) { $standalone_options['users'][ $source_user ] = $source_user; }
	$standalone_map = array(); $standalone_next = 100000;
	foreach ( array_keys( $standalone_parsed['records'] ) as $source_id ) { $standalone_map[ $source_id ] = $standalone_next++; }
	$standalone_prepared = ps_pen_paper_prepare_import( $standalone_parsed, $standalone_options, $standalone_map, true );
	$check( ! is_wp_error( $standalone_prepared ), 'Standalone import required the detached universe or base instead of preserving historical provenance.' );
	$historical_roll = null;
	foreach ( $standalone_prepared[ $old_roll_id ]['meta'] as $entry ) {
		if ( '_pspp_roll' === $entry['key'] ) { $historical_roll = $entry['value']; }
	}
	$check( is_array( $historical_roll ) && $standalone_map[ $ids[0] ] === $historical_roll['character']['id'] &&
		$base_id === $historical_roll['character']['context']['source_id'] && 5 === $historical_roll['attribute']['value'], 'Standalone import changed the old roll, lost its historical base or retained its old active character ID.' );
	wp_set_current_user( $users['gm'] );
	$check( ! is_wp_error( ps_pen_paper_adopt_universe_rules( $campaigns[3]->ID, '1', 'confirmed' ) ), 'Could not prepare the base-request campaign.' );
	wp_set_current_user( $users['player'] );
	$base_before_request = ps_pen_paper_raw_character_state( $base_id );
	$request_id = ps_pen_paper_request_base_character( $campaigns[3]->ID, $base_id, (string) $base_before_request['revision'], '1' );
	$check( is_int( $request_id ) && $request_id > 0, 'Player could not request their own universe base sheet: ' .
		( is_wp_error( $request_id ) ? $request_id->get_error_code() . ': ' . $request_id->get_error_message() :
			gettype( $request_id ) . ' ' . wp_json_encode( $request_id ) ) );
	$request_state = ps_pen_paper_raw_character_state( $request_id );
	$check( null === $request_state['active'] && $base_before_request['active'] === $request_state['pending'] &&
		is_wp_error( ps_pen_paper_request_base_character( $campaigns[3]->ID, $other_base_id, '1', '1' ) ), 'Base request exposed or copied another owner’s sheet.' );
	$check( is_wp_error( ps_pen_paper_character_resource_cost( $campaigns[3], get_post( $request_id ), $resource, 2 ) ),
		'An unapproved base request spent shared resources.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_review_character( $campaigns[3]->ID, $request_id, 'approve', (string) $request_state['revision'] ) &&
		'shared' === ps_pen_paper_raw_character_state( $request_id )['context']['mode'] &&
		$base_before_request === ps_pen_paper_raw_character_state( $base_id ), 'Base request approval changed the universe sheet or failed to attach it.' );
	wp_set_current_user( $users['player'] );
	$resource_character = get_post( $request_id );
	$alias_before_spend = ps_pen_paper_raw_character_state( $request_id );
	$shared_cost = ps_pen_paper_character_resource_cost( $campaigns[3], $resource_character, $resource, 2 );
	$check( ! is_wp_error( $shared_cost ) && isset( $shared_cost['shared_base'] ) &&
		$base_id === $shared_cost['shared_base']['character_id'] &&
		$base_before_request['revision'] === $shared_cost['character_revision'], 'Shared cost did not resolve to the authoritative universe sheet and revision.' );
	$action_edge = array( 'resource_cost' => array( 'resource' => $resource, 'amount' => 2 ) );
	$action_locks = ps_pen_paper_adventure_action_locks( $campaigns[3], $request_id, $action_edge );
	$check( ! is_wp_error( $action_locks ) && in_array( $base_id, $action_locks, true ) && in_array( $universe_id, $action_locks, true ),
		'Adventure action locks omitted the shared base or its universe.' );
	$shared_spend = ps_pen_paper_adventure_action_transaction( $campaigns[3], $action_locks, $campaigns[3]->ID, function () use ( $campaigns, $resource_character, $shared_cost ) {
		return ps_pen_paper_write_character_resource_cost( $campaigns[3], $resource_character, $shared_cost );
	} );
	$check( true === $shared_spend && 8 === ps_pen_paper_raw_character_state( $base_id )['active']['values'][ $resource ]['current'] &&
		8 === ps_pen_paper_character_state( $campaigns[3], $resource_character )['active']['values'][ $resource ]['current'] &&
		$alias_before_spend === ps_pen_paper_raw_character_state( $request_id ),
		'Shared resource cost was not charged once to the base sheet or duplicated alias resources.' );
	$replay = ps_pen_paper_adventure_action_transaction( $campaigns[3], $action_locks, $campaigns[3]->ID, function () use ( $campaigns, $resource_character, $shared_cost ) {
		return ps_pen_paper_write_character_resource_cost( $campaigns[3], $resource_character, $shared_cost );
	} );
	$check( is_wp_error( $replay ) && 8 === ps_pen_paper_raw_character_state( $base_id )['active']['values'][ $resource ]['current'],
		'A stale shared-cost preview spent the resource twice.' );
	add_post_meta( $campaigns[3]->ID, '_pspp_player', (string) $users['newcomer'] );
	wp_set_current_user( $users['newcomer'] );
	ob_start();
	ps_pen_paper_render_campaign_base_requests( $campaigns[3], 0 );
	$onboarding_html = ob_get_clean();
	$check( false !== strpos( $onboarding_html, 'name="pspp_operation" value="request_universe_character"' ) &&
		false !== strpos( $onboarding_html, 'name="universe_id" value="' . $universe_id . '"' ) &&
		false !== strpos( $onboarding_html, 'name="character[name]"' ),
		'A member without an existing base sheet did not receive the universe request form.' );
	$check( is_wp_error( ps_pen_paper_request_universe_character( $campaigns[0]->ID, $universe_id, $base_input, '1' ) ) &&
		is_wp_error( ps_pen_paper_request_universe_character( $campaigns[3]->ID, $universe_id, $base_input, '0' ) ),
		'Universe onboarding accepted a foreign campaign or stale template.' );
	$new_base_id = ps_pen_paper_request_universe_character( $campaigns[3]->ID, $universe_id, $base_input, '1' );
	$check( is_int( $new_base_id ) && $new_base_id > 0, 'New universe-sheet onboarding failed: ' .
		( is_wp_error( $new_base_id ) ? $new_base_id->get_error_code() . ': ' . $new_base_id->get_error_message() :
			gettype( $new_base_id ) . ' ' . wp_json_encode( $new_base_id ) ) );
	$new_base_state = ps_pen_paper_raw_character_state( $new_base_id );
	$check( null === $new_base_state['active'] && null !== $new_base_state['pending'] &&
		$users['newcomer'] === (int) get_post( $new_base_id )->post_author && $universe_id === (int) get_post( $new_base_id )->post_parent &&
		! array_key_exists( 'context', $new_base_state ) && null === ps_pen_paper_get_character( $universe, $new_base_id ),
		'Onboarding activated the unapproved base, assigned another owner or exposed private universe access.' );
	wp_set_current_user( $users['assistant'] );
	$check( is_wp_error( ps_pen_paper_review_character( $universe_id, $new_base_id, 'approve', '1' ) ),
		'A campaign-only GM approved a universe-sheet request.' );
	wp_set_current_user( $users['gm'] );
	ob_start();
	ps_pen_paper_render_characters( $universe, $new_base_id );
	$base_review_html = ob_get_clean();
	$check( false !== strpos( $base_review_html, 'name="pspp_operation" value="review_base_character"' ) &&
		false === strpos( $base_review_html, 'name="pspp_operation" value="review_character"' ),
		'Universe approval posted to the campaign-only review action.' );
	$original_post = $_POST;
	$original_request = $_REQUEST;
	$redirected = false;
	$intercept_redirect = static function ( $location ) { throw new RuntimeException( 'universe-review-redirect:' . $location ); };
	add_filter( 'wp_redirect', $intercept_redirect );
	try {
		$_POST = array( 'campaign_id' => (string) $universe_id, 'character_id' => (string) $new_base_id,
			'character_revision' => '1', 'decision' => 'approve', '_wpnonce' => wp_create_nonce( 'pspp_campaign_' . $universe_id ) );
		$_REQUEST = $_POST;
		try { ps_pen_paper_handle_character_context_action( 'review_base_character' ); }
		catch ( RuntimeException $error ) { $redirected = 0 === strpos( $error->getMessage(), 'universe-review-redirect:' ); if ( ! $redirected ) { throw $error; } }
	} finally {
		remove_filter( 'wp_redirect', $intercept_redirect );
		$_POST = $original_post;
		$_REQUEST = $original_request;
	}
	$check( $redirected && $new_base_state['pending'] === ps_pen_paper_raw_character_state( $new_base_id )['active'] &&
		null === ps_pen_paper_raw_character_state( $new_base_id )['pending'],
		'The universe approval UI controller did not approve the pending new sheet.' );
	delete_post_meta( $campaigns[0]->ID, '_pspp_player', (string) $users['player'] );
	wp_set_current_user( $users['player'] );
	$check( null === ps_pen_paper_get_character( $campaigns[0], $ids[0] ) && is_wp_error( ps_pen_paper_roll_dice( $campaigns[0]->ID, $roll_input, '1' ) ), 'Removed member retained contextual access.' );
} finally {
	wp_set_current_user( $original_user );
	$errors = array();
	$children = $parents ? get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_roll', 'pspp_event', 'pspp_item' ), 'post_status' => array( 'private', 'draft' ), 'post_parent__in' => $parents, 'posts_per_page' => -1, 'fields' => 'ids' ) ) : array();
	foreach ( array_merge( $children, $parents ) as $id ) {
		if ( ! wp_delete_post( $id, true ) ) { $errors[] = 'Cannot remove context fixture ' . $id; }
	}
	foreach ( $users as $id ) {
		if ( ! ( is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ) ) ) { $errors[] = 'Cannot remove context user ' . $id; }
	}
	if ( $errors ) { WP_CLI::error( implode( '; ', $errors ) ); }
}
WP_CLI::success( 'Base templates, shared progress, independent continuation, retained equipment and wallets, context overrides, effective dice snapshots, privacy, conflicts and publication rollback passed.' );
