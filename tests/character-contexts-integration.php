<?php
/**
 * Run with WP-CLI against a disposable development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
ps_pen_paper_register_campaign_type();
ps_pen_paper_register_universe_type();
ps_pen_paper_register_character_type();
ps_pen_paper_register_event_type();
ps_pen_paper_register_roll_type();
$original_user = get_current_user_id();
$users = array();
$parents = array();
$check = function ( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
};
try {
	foreach ( array( 'gm', 'assistant', 'player', 'outsider' ) as $role ) {
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
	$base_input = array( 'name' => 'Basis C:\Welt', 'values' => array( $attribute => '5', $level => '3', $resource => array( 'current' => '10', 'maximum' => '20' ) ) );
	$base_id = ps_pen_paper_save_character( $universe_id, 0, $users['player'], $base_input, '0', '1' );
	$check( ! is_wp_error( $base_id ), 'Universe base character did not save.' );
	$base = get_post( $base_id );
	$source = ps_pen_paper_raw_character_state( $base_id );
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
	$broken = $baseline;
	$broken['context']['overrides']['unknown'] = 1;
	update_post_meta( $ids[0], '_pspp_character', wp_slash( $broken ) );
	$check( is_wp_error( ps_pen_paper_character_state( $campaigns[0], $shared_character ) ), 'Corrupt context silently ignored.' );
	update_post_meta( $ids[0], '_pspp_character', wp_slash( $baseline ) );
	delete_post_meta( $campaigns[0]->ID, '_pspp_player', (string) $users['player'] );
	wp_set_current_user( $users['player'] );
	$check( null === ps_pen_paper_get_character( $campaigns[0], $ids[0] ) && is_wp_error( ps_pen_paper_roll_dice( $campaigns[0]->ID, $roll_input, '1' ) ), 'Removed member retained contextual access.' );
} finally {
	wp_set_current_user( $original_user );
	$errors = array();
	$children = $parents ? get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_roll', 'pspp_event' ), 'post_status' => array( 'private', 'draft' ), 'post_parent__in' => $parents, 'posts_per_page' => -1, 'fields' => 'ids' ) ) : array();
	foreach ( array_merge( $children, $parents ) as $id ) {
		if ( ! wp_delete_post( $id, true ) ) { $errors[] = 'Cannot remove context fixture ' . $id; }
	}
	foreach ( $users as $id ) {
		if ( ! ( is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ) ) ) { $errors[] = 'Cannot remove context user ' . $id; }
	}
	if ( $errors ) { WP_CLI::error( implode( '; ', $errors ) ); }
}
WP_CLI::success( 'Base templates, shared progress, independent copies, context overrides, effective dice snapshots, privacy, conflicts and publication rollback passed.' );
