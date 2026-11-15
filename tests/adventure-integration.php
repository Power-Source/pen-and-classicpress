<?php
/**
 * Run with WP-CLI against a disposable development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
ps_pen_paper_register_campaign_type();
ps_pen_paper_register_character_type();
ps_pen_paper_register_scene_type();
ps_pen_paper_register_event_type();
ps_pen_paper_register_adventure_types();

$original_user = get_current_user_id();
$users = array();
$parents = array();
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
try {
	foreach ( array( 'gm', 'player', 'other', 'outsider' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-adventure-' . $role . '-' . wp_generate_password( 12, false, false ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber' ) );
		$check( ! is_wp_error( $id ), 'Fixture account failed.' );
		$users[ $role ] = $id;
	}
	wp_set_current_user( $users['gm'] );
	$campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Solo adventure fixture' ), true );
	$check( ! is_wp_error( $campaign_id ) && $campaign_id, 'Campaign fixture failed.' );
	$parents[] = $campaign_id;
	add_post_meta( $campaign_id, '_pspp_gm', (string) $users['gm'] );
	add_post_meta( $campaign_id, '_pspp_player', (string) $users['player'] );
	add_post_meta( $campaign_id, '_pspp_player', (string) $users['other'] );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );

	$rules = ps_pen_paper_save_rules( $campaign_id, array( 'name' => 'Test rules', 'description' => '', 'dice' => array( '4' ),
		'fields' => array(
			array( 'id' => '', 'label' => 'Mut', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '20', 'start' => '5' ),
			array( 'id' => '', 'label' => 'Ausdauer', 'type' => 'resource', 'description' => '', 'min' => '0', 'max' => '20', 'start' => '7' ),
		) ), '0' );
	$check( ! is_wp_error( $rules ), 'Rules fixture failed.' );
	$attribute = $rules['fields'][0]['id'];
	$resource = $rules['fields'][1]['id'];
	$characters = array();
	foreach ( array( 'player', 'other' ) as $owner ) {
		$id = ps_pen_paper_save_character( $campaign_id, 0, $users[ $owner ], array( 'name' => ucfirst( $owner ), 'values' => array(
			$attribute => '5', $resource => array( 'current' => '7', 'maximum' => '20' ),
		) ), '0', (string) $rules['revision'] );
		$check( ! is_wp_error( $id ), 'Character fixture failed.' );
		$characters[ $owner ] = $id;
	}

	$scenes = array();
	foreach ( array( 'START_ORIGINAL', 'SUCCESS_SCENE', 'FAILURE_SCENE' ) as $title ) {
		$id = ps_pen_paper_save_scene( $campaign, 0, array( 'title' => $title, 'text' => $title . '_TEXT', 'secret' => '', 'grid' => '0' ), '0' );
		$check( ! is_wp_error( $id ) && true === ps_pen_paper_publish_scene( $campaign, $id, '1', 'confirmed' ), 'Scene fixture failed.' );
		$scenes[ $title ] = $id;
	}
	$check( true === ps_pen_paper_select_scene( $campaign, $scenes['START_ORIGINAL'], '0' ), 'Could not set baseline campaign scene.' );
	$baseline_scene = ps_pen_paper_map_state( $campaign );
	$draft_scene = ps_pen_paper_save_scene( $campaign, 0, array( 'title' => 'UNPUBLISHED_SCENE', 'text' => 'UNPUBLISHED_TEXT', 'secret' => '', 'grid' => '0' ), '0' );
	$check( is_int( $draft_scene ), 'Unpublished scene fixture failed.' );

	$adventure_id = ps_pen_paper_save_adventure( $campaign, 0, 'Threshold route', (string) $scenes['START_ORIGINAL'], '0' );
	$check( is_int( $adventure_id ), 'Adventure draft failed.' );
	$fail_edge = array( 'from_scene' => (string) $scenes['START_ORIGINAL'], 'choice' => 'TEST_FAIL_ROUTE', 'expression' => 'W4-1000000',
		'attribute' => $attribute, 'threshold' => '0', 'success_scene' => (string) $scenes['SUCCESS_SCENE'], 'failure_scene' => (string) $scenes['FAILURE_SCENE'] );
	$check( true === ps_pen_paper_save_adventure_edge( $campaign, $adventure_id, $fail_edge, '1' ), 'Failure route failed.' );
	$success_edge = $fail_edge;
	$success_edge['choice'] = 'TEST_SUCCESS_ROUTE';
	$success_edge['expression'] = 'W4+999990';
	$success_edge['threshold'] = '999996';
	$check( true === ps_pen_paper_save_adventure_edge( $campaign, $adventure_id, $success_edge, '2' ), 'Success route failed.' );
	$continue_edge = array( 'from_scene' => (string) $scenes['FAILURE_SCENE'], 'choice' => 'TEST_CONTINUE', 'expression' => '',
		'attribute' => '', 'threshold' => '0', 'success_scene' => (string) $scenes['SUCCESS_SCENE'], 'failure_scene' => '0' );
	$check( true === ps_pen_paper_save_adventure_edge( $campaign, $adventure_id, $continue_edge, '3' ), 'Failure continuation failed.' );
	$unpublished_edge = $continue_edge;
	$unpublished_edge['choice'] = 'TEST_UNPUBLISHED';
	$unpublished_edge['success_scene'] = (string) $draft_scene;
	$check( is_wp_error( ps_pen_paper_save_adventure_edge( $campaign, $adventure_id, $unpublished_edge, '4' ) ), 'Unpublished scene was added to an adventure.' );
	$cycle = $continue_edge;
	$cycle['from_scene'] = (string) $scenes['SUCCESS_SCENE'];
	$cycle['choice'] = 'TEST_CYCLE';
	$cycle['success_scene'] = (string) $scenes['START_ORIGINAL'];
	$check( is_wp_error( ps_pen_paper_save_adventure_edge( $campaign, $adventure_id, $cycle, '4' ) ), 'Cyclic adventure path was accepted.' );
	$check( true === ps_pen_paper_publish_adventure( $campaign, $adventure_id, '4', 'confirmed' ), 'Adventure publication failed.' );
	$published = ps_pen_paper_get_adventure( $campaign, $adventure_id );
	$check( 'START_ORIGINAL' === $published['published']['scenes'][ $scenes['START_ORIGINAL'] ]['title'], 'Publication did not capture the locked current scene snapshot.' );

	$changed_scene = ps_pen_paper_save_scene( $campaign, $scenes['START_ORIGINAL'], array( 'title' => 'START_CHANGED', 'text' => 'START_CHANGED_TEXT', 'secret' => '', 'grid' => '0' ), '2' );
	$check( $scenes['START_ORIGINAL'] === $changed_scene, 'Scene update fixture failed.' );
	wp_set_current_user( $users['player'] );
	$baseline_scene = ps_pen_paper_map_state( $campaign );
	foreach ( array( 'player', 'other' ) as $owner ) {
		wp_set_current_user( $users[ $owner ] );
		$character = get_post( $characters[ $owner ] );
		$character_state = ps_pen_paper_character_state( $campaign, $character );
		$check( is_wp_error( ps_pen_paper_start_adventure_run( $campaign, $adventure_id, $character->ID, (string) $published['revision'], '0' ) ), 'Stale character revision started a run.' );
		$run_id = ps_pen_paper_start_adventure_run( $campaign, $adventure_id, $character->ID, (string) $published['revision'], (string) $character_state['revision'] );
		$check( is_int( $run_id ) && $run_id > 0, 'Owner could not start a private adventure run.' );
		$check( $run_id === ps_pen_paper_start_adventure_run( $campaign, $adventure_id, $character->ID, (string) $published['revision'], (string) $character_state['revision'] ), 'Starting again duplicated or reset the run.' );
		$run = get_post_meta( $run_id, '_pspp_adventure_run', true );
		$check( ps_pen_paper_valid_adventure_run( $run ) && 'START_ORIGINAL' === $run['snapshot']['scenes'][ $scenes['START_ORIGINAL'] ]['title'], 'Run did not keep its private publication snapshot.' );
		$runs[ $owner ] = array( 'id' => $run_id, 'state' => $run );
	}
	wp_set_current_user( $users['gm'] );
	$changed_rule_fields = array();
	foreach ( $rules['fields'] as $field ) {
		$changed_rule_fields[] = array( 'id' => $field['id'], 'label' => $field['label'], 'type' => $field['type'], 'description' => $field['description'],
			'min' => (string) $field['min'], 'max' => (string) $field['max'], 'start' => (string) $field['start'] );
	}
	$changed_rules = ps_pen_paper_save_rules( $campaign_id, array( 'name' => $rules['name'], 'description' => $rules['description'],
		'dice' => array( '6' ), 'fields' => $changed_rule_fields ), (string) $rules['revision'] );
	$check( ! is_wp_error( $changed_rules ), 'Allowed dice could not be changed after adventure publication.' );
	$check( true === ps_pen_paper_publish_adventure( $campaign, $adventure_id, (string) $published['revision'], 'confirmed', true ), 'Pausing new runs failed.' );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_start_adventure_run( $campaign, $adventure_id, $characters['player'], (string) ( $published['revision'] + 1 ), '1' ) ), 'A new run started while the adventure was paused.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_publish_adventure( $campaign, $adventure_id, (string) ( $published['revision'] + 1 ), 'confirmed' ), 'Resuming new runs failed.' );

	$event_ids = get_posts( array( 'post_type' => 'pspp_event', 'post_status' => 'private', 'post_parent' => $campaign_id, 'posts_per_page' => -1, 'fields' => 'ids' ) );
	$run_event_id = 0;
	foreach ( $event_ids as $event_id ) {
		$event = get_post_meta( $event_id, '_pspp_event', true );
		if ( isset( $event['kind'], $event['object_id'] ) && 'adventure_run' === $event['kind'] && $event['object_id'] === $runs['player']['id'] ) { $run_event_id = $event_id; break; }
	}
	wp_set_current_user( $users['player'] );
	$check( $run_event_id && ! is_wp_error( ps_pen_paper_get_event( $campaign, $run_event_id ) ), 'Owner could not read their run event.' );
	wp_set_current_user( $users['other'] );
	$check( $run_event_id && is_wp_error( ps_pen_paper_get_event( $campaign, $run_event_id ) ), 'Another member read a private run event.' );
	$check( is_wp_error( ps_pen_paper_advance_adventure_run( $campaign, $runs['player']['id'], $runs['player']['state']['snapshot']['edges'][0]['id'], '1' ) ), 'Another member advanced a private run.' );
	wp_set_current_user( $users['gm'] );
	delete_post_meta( $campaign_id, '_pspp_player', (string) $users['player'] );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_advance_adventure_run( $campaign, $runs['player']['id'], $runs['player']['state']['snapshot']['edges'][0]['id'], '1' ) ), 'Removed member retained run access.' );
	wp_set_current_user( $users['gm'] );
	add_post_meta( $campaign_id, '_pspp_player', (string) $users['player'] );

	wp_set_current_user( $users['player'] );
	ob_start();
	ps_pen_paper_render_character_adventures( $campaign, get_post( $characters['player'] ), 0 );
	$html = ob_get_clean();
	$check( false !== strpos( $html, 'START_ORIGINAL_TEXT' ) && false === strpos( $html, 'SUCCESS_SCENE_TEXT' ) && false === strpos( $html, 'FAILURE_SCENE_TEXT' ), 'Player view revealed a future scene.' );
	$roll_edge = $runs['player']['state']['snapshot']['edges'][0];
	$run_before = get_post_meta( $runs['player']['id'], '_pspp_adventure_run', true );
	$journal_before = ps_pen_paper_get_events( $campaign );
	$fail_publish = static function ( $data ) { if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) { $data['post_status'] = 'draft'; } return $data; };
	add_filter( 'wp_insert_post_data', $fail_publish );
	try {
		$check( is_wp_error( ps_pen_paper_advance_adventure_run( $campaign, $runs['player']['id'], $roll_edge['id'], '1' ) ), 'Failed event publication reported successful progress.' );
	} finally { remove_filter( 'wp_insert_post_data', $fail_publish ); }
	$check( $run_before === get_post_meta( $runs['player']['id'], '_pspp_adventure_run', true ) && $journal_before === ps_pen_paper_get_events( $campaign ), 'Failed transaction changed progress or event history.' );

	$character_before = ps_pen_paper_character_state( $campaign, get_post( $characters['player'] ) );
	$check( true === ps_pen_paper_advance_adventure_run( $campaign, $runs['player']['id'], $roll_edge['id'], '1' ), 'Failure choice did not advance.' );
	$failed = get_post_meta( $runs['player']['id'], '_pspp_adventure_run', true );
	$check( $failed['scene_id'] === $scenes['FAILURE_SCENE'] && false === $failed['history'][0]['roll']['success'] &&
		$failed['history'][0]['roll']['total'] < 0, 'Failure branch or attribute check was incorrect.' );
	$check( is_wp_error( ps_pen_paper_advance_adventure_run( $campaign, $runs['player']['id'], $roll_edge['id'], '1' ) ), 'Stale or replayed choice was accepted.' );
	$continue = $failed['snapshot']['edges'][2];
	$check( true === ps_pen_paper_advance_adventure_run( $campaign, $runs['player']['id'], $continue['id'], '2' ) &&
		'completed' === get_post_meta( $runs['player']['id'], '_pspp_adventure_run', true )['status'], 'Failure continuation did not complete.' );
	$check( $character_before === ps_pen_paper_character_state( $campaign, get_post( $characters['player'] ) ) &&
		$baseline_scene === ps_pen_paper_map_state( $campaign ), 'Adventure mutated character resources or the shared campaign scene.' );

	wp_set_current_user( $users['other'] );
	$success_edge = $runs['other']['state']['snapshot']['edges'][1];
	$check( true === ps_pen_paper_advance_adventure_run( $campaign, $runs['other']['id'], $success_edge['id'], '1' ), 'Success choice did not advance.' );
	$success_run = get_post_meta( $runs['other']['id'], '_pspp_adventure_run', true );
	$check( $success_run['scene_id'] === $scenes['SUCCESS_SCENE'] && true === $success_run['history'][0]['roll']['success'] &&
		$success_run['history'][0]['roll']['total'] >= $success_run['history'][0]['roll']['threshold'], 'Success branch or threshold comparison was incorrect.' );
	$check( is_wp_error( ps_pen_paper_advance_adventure_run( $campaign, $runs['other']['id'], $success_edge['id'], '1' ) ), 'Completed choice was replayed.' );
	$check( 1 === count( $success_run['history'][0]['roll']['groups'][0]['rolls'] ), 'Stored roll result is malformed.' );
	$invalid_run = $success_run;
	$invalid_run['history'] = array( 1 => $success_run['history'][0] );
	$check( ! ps_pen_paper_valid_adventure_run( $invalid_run ), 'Sparse run history was accepted.' );
	$invalid_run = $success_run;
	$invalid_run['history'][0]['roll']['groups'] = array( 1 => $success_run['history'][0]['roll']['groups'][0] );
	$check( ! ps_pen_paper_valid_adventure_run( $invalid_run ), 'Sparse roll groups were accepted.' );
	$invalid_run = $success_run;
	$invalid_run['history'][0]['roll']['groups'][0]['rolls'] = array( 1 => $success_run['history'][0]['roll']['groups'][0]['rolls'][0] );
	$check( ! ps_pen_paper_valid_adventure_run( $invalid_run ), 'Sparse die results were accepted.' );

	wp_set_current_user( $users['outsider'] );
	$check( is_wp_error( ps_pen_paper_start_adventure_run( $campaign, $adventure_id, $characters['player'], (string) $published['revision'], '1' ) ), 'Nonmember started an adventure run.' );
	echo "Adventure integration checks passed.\n";
} finally {
	wp_set_current_user( $original_user );
	$errors = array();
	$children = $parents ? get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_scene', 'pspp_adventure', 'pspp_adventure_run', 'pspp_event' ),
		'post_status' => array( 'private', 'draft' ), 'post_parent__in' => $parents, 'posts_per_page' => -1, 'fields' => 'ids' ) ) : array();
	foreach ( array_merge( $children, $parents ) as $id ) { if ( ! wp_delete_post( $id, true ) ) { $errors[] = 'Cannot remove adventure fixture ' . $id; } }
	foreach ( $users as $id ) { if ( ! wp_delete_user( $id ) ) { $errors[] = 'Cannot remove fixture account ' . $id; } }
	if ( $errors ) { throw new RuntimeException( implode( '; ', $errors ) ); }
}
