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
ps_pen_paper_register_scene_type();
ps_pen_paper_register_event_type();
ps_pen_paper_register_adventure_types();
ps_pen_paper_register_item_type();
ps_pen_paper_register_loot_types();
ps_pen_paper_register_folio_type();

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

	wp_set_current_user( $users['gm'] );
	$required_adventure = ps_pen_paper_save_adventure( $campaign, 0, 'Minimum route', (string) $scenes['START_ORIGINAL'], '0' );
	$check( is_int( $required_adventure ), 'Requirement adventure fixture failed.' );
	$required_edge = array( 'from_scene' => (string) $scenes['START_ORIGINAL'], 'choice' => 'MINIMUM_CHOICE', 'expression' => '',
		'attribute' => '', 'threshold' => '0', 'success_scene' => (string) $scenes['SUCCESS_SCENE'], 'failure_scene' => '0',
		'requirement_attribute' => $attribute, 'requirement_minimum' => '6' );
	$invalid_edge = $required_edge;
	$invalid_edge['requirement_attribute'] = $resource;
	$check( is_wp_error( ps_pen_paper_save_adventure_edge( $campaign, $required_adventure, $invalid_edge, '1' ) ), 'Resource field was accepted as a numeric prerequisite.' );
	$invalid_edge = $required_edge;
	$invalid_edge['requirement_minimum'] = '1000001';
	$check( is_wp_error( ps_pen_paper_save_adventure_edge( $campaign, $required_adventure, $invalid_edge, '1' ) ), 'Out-of-range prerequisite was accepted.' );
	$invalid_edge['requirement_minimum'] = '6.5';
	$check( is_wp_error( ps_pen_paper_save_adventure_edge( $campaign, $required_adventure, $invalid_edge, '1' ) ), 'Fractional prerequisite was accepted.' );
	unset( $invalid_edge['requirement_minimum'] );
	$check( is_wp_error( ps_pen_paper_save_adventure_edge( $campaign, $required_adventure, $invalid_edge, '1' ) ), 'Incomplete prerequisite was silently removed.' );
	$check( true === ps_pen_paper_save_adventure_edge( $campaign, $required_adventure, $required_edge, '1' ) &&
		true === ps_pen_paper_publish_adventure( $campaign, $required_adventure, '2', 'confirmed' ), 'Numeric prerequisite could not be published.' );

	wp_set_current_user( $users['player'] );
	$required_run_id = ps_pen_paper_start_adventure_run( $campaign, $required_adventure, $characters['player'], '3', '1' );
	$check( is_int( $required_run_id ), 'Prerequisite run could not start.' );
	$required_run = get_post_meta( $required_run_id, '_pspp_adventure_run', true );
	$required_edge_id = $required_run['snapshot']['edges'][0]['id'];
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_save_adventure_edge( $campaign, $required_adventure, array(), '3', $required_edge_id ), 'Prerequisite draft edge could not be replaced.' );
	$revised_edge = $required_edge;
	$revised_edge['requirement_minimum'] = '8';
	$check( true === ps_pen_paper_save_adventure_edge( $campaign, $required_adventure, $revised_edge, '4' ) &&
		true === ps_pen_paper_publish_adventure( $campaign, $required_adventure, '5', 'confirmed' ) &&
		$required_run === get_post_meta( $required_run_id, '_pspp_adventure_run', true ), 'Republication changed the running prerequisite snapshot.' );
	wp_set_current_user( $users['player'] );
	$before_requirement_events = ps_pen_paper_get_events( $campaign );
	$result = ps_pen_paper_advance_adventure_run( $campaign, $required_run_id, $required_edge_id, '1' );
	$check( is_wp_error( $result ) && 'adventure_requirement_unmet' === $result->get_error_code() &&
		$required_run === get_post_meta( $required_run_id, '_pspp_adventure_run', true ) &&
		$before_requirement_events === ps_pen_paper_get_events( $campaign ), 'Unmet prerequisite advanced or wrote an event.' );
	ob_start();
	ps_pen_paper_render_character_adventures( $campaign, get_post( $characters['player'] ), 0 );
	$blocked_html = ob_get_clean();
	$check( preg_match( '/<button[^>]*disabled[^>]*>MINIMUM_CHOICE<\/button>/', $blocked_html ) &&
		false !== strpos( $blocked_html, 'freigegeben 5, mindestens 6' ), 'Unmet decision was hidden, enabled or lacked an explanation.' );

	$requirement_character = array( 'name' => 'Player', 'values' => array( $attribute => '7', $resource => array( 'current' => '7', 'maximum' => '20' ) ) );
	$check( $characters['player'] === ps_pen_paper_save_character( $campaign_id, $characters['player'], $users['player'],
		$requirement_character, '1', (string) $changed_rules['revision'] ), 'Pending prerequisite value could not be proposed.' );
	$result = ps_pen_paper_advance_adventure_run( $campaign, $required_run_id, $required_edge_id, '1' );
	$check( is_wp_error( $result ) && 'adventure_requirement_unmet' === $result->get_error_code(), 'Pending value satisfied the prerequisite before approval.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_review_character( $campaign_id, $characters['player'], 'approve', '2' ), 'Prerequisite value could not be approved.' );
	wp_set_current_user( $users['player'] );
	ob_start();
	ps_pen_paper_render_character_adventures( $campaign, get_post( $characters['player'] ), 0 );
	$enabled_html = ob_get_clean();
	$check( preg_match( '/<button(?![^>]*disabled)[^>]*>MINIMUM_CHOICE<\/button>/', $enabled_html ), 'Approved value did not enable the decision.' );
	wp_set_current_user( $users['gm'] );
	$requirement_character['values'][ $attribute ] = '5';
	$check( $characters['player'] === ps_pen_paper_save_character( $campaign_id, $characters['player'], $users['player'],
		$requirement_character, '3', (string) $changed_rules['revision'] ), 'Concurrent prerequisite correction failed.' );
	wp_set_current_user( $users['player'] );
	$result = ps_pen_paper_advance_adventure_run( $campaign, $required_run_id, $required_edge_id, '1' );
	$check( is_wp_error( $result ) && 'adventure_requirement_unmet' === $result->get_error_code(), 'Previously enabled form bypassed the current prerequisite.' );
	wp_set_current_user( $users['gm'] );
	$requirement_character['values'][ $attribute ] = '6';
	$check( $characters['player'] === ps_pen_paper_save_character( $campaign_id, $characters['player'], $users['player'],
		$requirement_character, '4', (string) $changed_rules['revision'] ), 'Exact minimum correction failed.' );
	wp_set_current_user( $users['player'] );
	$before_requirement_events = ps_pen_paper_get_events( $campaign );
	add_filter( 'wp_insert_post_data', $fail_publish );
	try {
		$check( is_wp_error( ps_pen_paper_advance_adventure_run( $campaign, $required_run_id, $required_edge_id, '1' ) ), 'Prerequisite transition reported success without its event.' );
	} finally { remove_filter( 'wp_insert_post_data', $fail_publish ); }
	$check( $required_run === get_post_meta( $required_run_id, '_pspp_adventure_run', true ) &&
		$before_requirement_events === ps_pen_paper_get_events( $campaign ), 'Failed prerequisite event retained partial progress.' );
	$before_requirement_character = ps_pen_paper_character_state( $campaign, get_post( $characters['player'] ) );
	$check( true === ps_pen_paper_advance_adventure_run( $campaign, $required_run_id, $required_edge_id, '1' ), 'Exact minimum did not satisfy the prerequisite.' );
	$completed_requirement = get_post_meta( $required_run_id, '_pspp_adventure_run', true );
	$check( ps_pen_paper_valid_adventure_run( $completed_requirement ) && 'completed' === $completed_requirement['status'] &&
		6 === $completed_requirement['history'][0]['requirement']['value'] &&
		6 === $completed_requirement['history'][0]['requirement']['minimum'] &&
		$before_requirement_character === ps_pen_paper_character_state( $campaign, get_post( $characters['player'] ) ) &&
		$baseline_scene === ps_pen_paper_map_state( $campaign ), 'Prerequisite evidence was missing or character/shared scene changed.' );
	$requirement_event = ps_pen_paper_get_events( $campaign )['events'][0];
	$check( 'adventure_run' === $requirement_event['kind'] && $completed_requirement === $requirement_event['after'], 'Central event omitted the prerequisite evidence.' );
	ob_start();
	ps_pen_paper_render_event_details( $requirement_event, $campaign );
	$requirement_event_html = ob_get_clean();
	$check( false !== strpos( $requirement_event_html, 'freigegeben 6, mindestens 6' ), 'Private event omitted the historical prerequisite value.' );
	$invalid_run = $completed_requirement;
	unset( $invalid_run['history'][0]['requirement'] );
	$check( ! ps_pen_paper_valid_adventure_run( $invalid_run ), 'Required historical evidence could be removed.' );
	$invalid_run = $completed_requirement;
	$invalid_run['history'][0]['requirement']['value'] = 5;
	$check( ! ps_pen_paper_valid_adventure_run( $invalid_run ), 'Historical prerequisite below minimum was accepted.' );
	$check( is_wp_error( ps_pen_paper_advance_adventure_run( $campaign, $required_run_id, $required_edge_id, '1' ) ), 'Prerequisite transition was replayed.' );
	$check( $success_run === get_post_meta( $runs['other']['id'], '_pspp_adventure_run', true ) && ps_pen_paper_valid_adventure_run( $success_run ),
		'Legacy run without prerequisites was rewritten or rejected.' );

	$cost_cases = array(
		array( 'name' => 'ATTEMPT_FAILURE', 'timing' => 'attempt', 'expression' => 'W6-1000000', 'amount' => '2', 'remaining' => 5 ),
		array( 'name' => 'SUCCESS_FAILURE', 'timing' => 'success', 'expression' => 'W6-1000000', 'amount' => '2', 'remaining' => 5 ),
		array( 'name' => 'SUCCESS_SUCCESS', 'timing' => 'success', 'expression' => 'W6+999990', 'amount' => '2', 'remaining' => 3 ),
		array( 'name' => 'SUCCESS_WITHOUT_ROLL', 'timing' => 'success', 'expression' => '', 'amount' => '3', 'remaining' => 0 ),
		array( 'name' => 'INSUFFICIENT_COST', 'timing' => 'attempt', 'expression' => '', 'amount' => '2', 'remaining' => 0 ),
		array( 'name' => 'INSUFFICIENT_SUCCESS', 'timing' => 'success', 'expression' => 'W6-1000000', 'amount' => '2', 'remaining' => 0 ),
	);
	foreach ( $cost_cases as $case_index => $case ) {
		wp_set_current_user( $users['gm'] );
		$cost_adventure = ps_pen_paper_save_adventure( $campaign, 0, $case['name'], (string) $scenes['START_ORIGINAL'], '0' );
		$check( is_int( $cost_adventure ), 'Cost adventure creation failed.' );
		$cost_edge_input = array( 'from_scene' => (string) $scenes['START_ORIGINAL'], 'choice' => $case['name'],
			'expression' => $case['expression'], 'attribute' => '', 'threshold' => '0',
			'success_scene' => (string) $scenes['SUCCESS_SCENE'], 'failure_scene' => '0',
			'cost_resource' => $resource, 'cost_amount' => $case['amount'], 'cost_timing' => $case['timing'] );
		if ( 0 === $case_index ) {
			$invalid_cost = $cost_edge_input; $invalid_cost['cost_resource'] = $attribute;
			$check( is_wp_error( ps_pen_paper_save_adventure_edge( $campaign, $cost_adventure, $invalid_cost, '1' ) ), 'Attribute accepted as a consumable resource.' );
			$invalid_cost = $cost_edge_input; $invalid_cost['cost_amount'] = '0';
			$check( is_wp_error( ps_pen_paper_save_adventure_edge( $campaign, $cost_adventure, $invalid_cost, '1' ) ), 'Zero cost with a resource was accepted.' );
			$invalid_cost['cost_amount'] = '1000001';
			$check( is_wp_error( ps_pen_paper_save_adventure_edge( $campaign, $cost_adventure, $invalid_cost, '1' ) ), 'Unbounded resource cost was accepted.' );
			$invalid_cost = $cost_edge_input; $invalid_cost['cost_timing'] = 'sometimes';
			$check( is_wp_error( ps_pen_paper_save_adventure_edge( $campaign, $cost_adventure, $invalid_cost, '1' ) ), 'Unknown cost timing was accepted.' );
		}
		$check( true === ps_pen_paper_save_adventure_edge( $campaign, $cost_adventure, $cost_edge_input, '1' ) &&
			true === ps_pen_paper_publish_adventure( $campaign, $cost_adventure, '2', 'confirmed' ), 'Cost rule publication failed.' );
		wp_set_current_user( $users['player'] );
		$cost_character_before = ps_pen_paper_raw_character_state( $characters['player'] );
		$cost_run_id = ps_pen_paper_start_adventure_run( $campaign, $cost_adventure, $characters['player'], '3', (string) $cost_character_before['revision'] );
		$check( is_int( $cost_run_id ), 'Cost run creation failed.' );
		$cost_run_before = get_post_meta( $cost_run_id, '_pspp_adventure_run', true );
		$cost_edge_id = $cost_run_before['snapshot']['edges'][0]['id'];
		if ( 0 === $case_index ) {
			$pending_cost_input = $requirement_character;
			$pending_cost_input['values'][ $resource ]['current'] = '20';
			$check( $characters['player'] === ps_pen_paper_save_character( $campaign_id, $characters['player'], $users['player'],
				$pending_cost_input, (string) $cost_character_before['revision'], (string) $changed_rules['revision'] ), 'Pending cost fixture failed.' );
			$pending_before = ps_pen_paper_raw_character_state( $characters['player'] );
			$result = ps_pen_paper_advance_adventure_run( $campaign, $cost_run_id, $cost_edge_id, '1' );
			$check( is_wp_error( $result ) && 'resource_cost_pending' === $result->get_error_code() &&
				$pending_before === ps_pen_paper_raw_character_state( $characters['player'] ), 'Open value request was overwritten or used for spending.' );
			wp_set_current_user( $users['gm'] );
			$check( true === ps_pen_paper_review_character( $campaign_id, $characters['player'], 'reject', (string) $pending_before['revision'] ), 'Pending cost fixture could not be rejected.' );
			wp_set_current_user( $users['player'] );
			$cost_character_before = ps_pen_paper_raw_character_state( $characters['player'] );
			foreach ( array( 'character_write', 'character_resource_spent', 'adventure_run' ) as $failed_kind ) {
				$cost_events_before = ps_pen_paper_get_events( $campaign );
				$fail_cost_event = static function ( $check, $id, $key, $value ) use ( $failed_kind ) {
					if ( 'character_write' === $failed_kind && '_pspp_character' === $key ) { return false; }
					return '_pspp_event' === $key && isset( $value['kind'] ) && $failed_kind === $value['kind'] ? false : $check;
				};
				add_filter( 'update_post_metadata', $fail_cost_event, 10, 4 );
				try {
					$check( is_wp_error( ps_pen_paper_advance_adventure_run( $campaign, $cost_run_id, $cost_edge_id, '1' ) ), 'Cost transition succeeded despite a failed mandatory event.' );
				} finally { remove_filter( 'update_post_metadata', $fail_cost_event, 10 ); }
				$check( $cost_character_before === ps_pen_paper_raw_character_state( $characters['player'] ) &&
					$cost_run_before === get_post_meta( $cost_run_id, '_pspp_adventure_run', true ) &&
					$cost_events_before === ps_pen_paper_get_events( $campaign ), 'Cost/event failure left partial spending or progress.' );
			}
		}
		if ( in_array( $case['name'], array( 'INSUFFICIENT_COST', 'INSUFFICIENT_SUCCESS' ), true ) ) {
			$cost_events_before = ps_pen_paper_get_events( $campaign );
			$result = ps_pen_paper_advance_adventure_run( $campaign, $cost_run_id, $cost_edge_id, '1' );
			$check( is_wp_error( $result ) && 'adventure_resource_insufficient' === $result->get_error_code() &&
				$cost_character_before === ps_pen_paper_raw_character_state( $characters['player'] ) &&
				$cost_run_before === get_post_meta( $cost_run_id, '_pspp_adventure_run', true ) &&
				$cost_events_before === ps_pen_paper_get_events( $campaign ), 'Insufficient cost overdrew resources or advanced.' );
			ob_start();
			ps_pen_paper_render_character_adventures( $campaign, get_post( $characters['player'] ), 0 );
			$cost_html = ob_get_clean();
			$check( preg_match( '/<button[^>]*disabled[^>]*>' . $case['name'] . '<\/button>/', $cost_html ) &&
				false !== strpos( $cost_html, 'verfügbar 0' ), 'Unaffordable decision was hidden, enabled or unexplained.' );
			continue;
		}
		$check( true === ps_pen_paper_advance_adventure_run( $campaign, $cost_run_id, $cost_edge_id, '1' ), 'Configured cost transition failed.' );
		$cost_character_after = ps_pen_paper_raw_character_state( $characters['player'] );
		$cost_run_after = get_post_meta( $cost_run_id, '_pspp_adventure_run', true );
		$expected_character = $cost_character_before;
		if ( $case['remaining'] !== $cost_character_before['active']['values'][ $resource ]['current'] ) {
			$expected_character['revision']++;
			$expected_character['active']['values'][ $resource ]['current'] = $case['remaining'];
			$check( ps_pen_paper_valid_character_resource_cost( $cost_run_after['history'][0]['resource_cost'] ), 'Paid cost evidence is invalid.' );
			$cost_event = ps_pen_paper_get_events( $campaign )['events'][1];
			$check( 'character_resource_spent' === $cost_event['kind'] && $cost_character_before === $cost_event['before'] &&
				$cost_character_after === $cost_event['after'], 'Resource event does not match the actual character change.' );
			wp_set_current_user( $users['other'] );
			$check( is_wp_error( ps_pen_paper_get_event( $campaign, $cost_event['id'] ) ), 'Another owner read a private resource event.' );
			wp_set_current_user( $users['player'] );
			$invalid_run = $cost_run_after;
			$invalid_run['history'][0]['resource_cost']['after']++;
			$check( ! ps_pen_paper_valid_adventure_run( $invalid_run ), 'Wrong cost arithmetic was accepted.' );
		} else {
			$check( array_key_exists( 'resource_cost', $cost_run_after['history'][0] ) &&
				null === $cost_run_after['history'][0]['resource_cost'], 'Failed success-only probe invented paid cost evidence.' );
		}
		$check( $expected_character === $cost_character_after && ps_pen_paper_valid_adventure_run( $cost_run_after ) &&
			'completed' === $cost_run_after['status'] && $baseline_scene === ps_pen_paper_map_state( $campaign ), 'Cost timing, revision, other values or shared scene were incorrect.' );
		$check( is_wp_error( ps_pen_paper_advance_adventure_run( $campaign, $cost_run_id, $cost_edge_id, '1' ) ) &&
			$cost_character_after === ps_pen_paper_raw_character_state( $characters['player'] ), 'Replayed cost was charged again.' );
	}

	wp_set_current_user( $users['gm'] );
	wp_get_current_user()->add_cap( 'ps_pen_paper_create_campaigns' );
	$cost_universe_id = ps_pen_paper_save_universe( 0, array( 'name' => 'Resource universe fixture', 'description' => '' ), '0' );
	$check( is_int( $cost_universe_id ), 'Cost universe fixture failed.' );
	$parents[] = $cost_universe_id;
	$cost_base_rules = ps_pen_paper_save_rules( $cost_universe_id, array( 'name' => 'Resource base rules', 'description' => '', 'dice' => array( '6' ),
		'fields' => array( array( 'id' => '', 'label' => 'Mana', 'type' => 'resource', 'description' => '', 'min' => '2', 'max' => '10', 'start' => '4' ) ) ), '0' );
	$check( ! is_wp_error( $cost_base_rules ), 'Cost base rules fixture failed.' );
	$base_resource = $cost_base_rules['fields'][0]['id'];
	$cost_base_id = ps_pen_paper_save_character( $cost_universe_id, 0, $users['player'],
		array( 'name' => 'Resource base', 'values' => array( $base_resource => array( 'current' => '4', 'maximum' => '10' ) ) ), '0', '1' );
	$check( is_int( $cost_base_id ), 'Cost base character fixture failed.' );
	$cost_base_before = ps_pen_paper_raw_character_state( $cost_base_id );
	$context_campaigns = array(); $context_characters = array();
	foreach ( array( 'copy', 'shared' ) as $context_mode ) {
		wp_set_current_user( $users['gm'] );
		$context_campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Cost context fixture' ), true );
		$check( is_int( $context_campaign_id ), 'Cost context campaign fixture failed.' );
		$parents[] = $context_campaign_id;
		add_post_meta( $context_campaign_id, '_pspp_gm', (string) $users['gm'] );
		add_post_meta( $context_campaign_id, '_pspp_player', (string) $users['player'] );
		$context_campaign = ps_pen_paper_get_campaign( $context_campaign_id );
		$context_campaigns[ $context_mode ] = $context_campaign;
		$check( ! is_wp_error( ps_pen_paper_save_universe_link( $context_campaign_id, $cost_universe_id, '0', '1', 'confirmed' ) ) &&
			! is_wp_error( ps_pen_paper_adopt_universe_rules( $context_campaign_id, '1', 'confirmed' ) ), 'Cost context universe association failed.' );
		$context_character_id = ps_pen_paper_attach_base_character( $context_campaign_id, $cost_base_id, '1', '1', $context_mode, 'confirmed' );
		$check( is_int( $context_character_id ), 'Cost context attachment failed.' );
		$context_characters[ $context_mode ] = $context_character_id;
		$context_scene_id = ps_pen_paper_save_scene( $context_campaign, 0, array( 'title' => 'Resource scene', 'text' => '', 'secret' => '', 'grid' => '0' ), '0' );
		$check( is_int( $context_scene_id ) && true === ps_pen_paper_publish_scene( $context_campaign, $context_scene_id, '1', 'confirmed' ), 'Cost context scene fixture failed.' );
		$context_adventure_id = ps_pen_paper_save_adventure( $context_campaign, 0, 'Resource context adventure', (string) $context_scene_id, '0' );
		$context_edge = array( 'from_scene' => (string) $context_scene_id, 'choice' => 'CONTEXT_COST', 'expression' => '', 'attribute' => '', 'threshold' => '0',
			'success_scene' => '0', 'failure_scene' => '0', 'cost_resource' => $base_resource, 'cost_amount' => '2', 'cost_timing' => 'attempt' );
		$check( is_int( $context_adventure_id ) && true === ps_pen_paper_save_adventure_edge( $context_campaign, $context_adventure_id, $context_edge, '1' ), 'Cost context edge fixture failed.' );
		$free_context_edge = $context_edge; $free_context_edge['choice'] = 'CONTEXT_FREE'; $free_context_edge['cost_resource'] = ''; $free_context_edge['cost_amount'] = '0';
		$check( true === ps_pen_paper_save_adventure_edge( $context_campaign, $context_adventure_id, $free_context_edge, '2' ), 'Free context alternative fixture failed.' );
		$bound_context_edge = $context_edge; $bound_context_edge['choice'] = 'CONTEXT_BELOW_MINIMUM'; $bound_context_edge['cost_amount'] = '3';
		$check( true === ps_pen_paper_save_adventure_edge( $context_campaign, $context_adventure_id, $bound_context_edge, '3' ) &&
			true === ps_pen_paper_publish_adventure( $context_campaign, $context_adventure_id, '4', 'confirmed' ), 'Resource lower-bound fixture failed.' );
		wp_set_current_user( $users['player'] );
		$context_run_id = ps_pen_paper_start_adventure_run( $context_campaign, $context_adventure_id, $context_character_id, '5', '1' );
		$check( is_int( $context_run_id ), 'Cost context run fixture failed.' );
		$context_before = ps_pen_paper_raw_character_state( $context_character_id );
		$context_run = get_post_meta( $context_run_id, '_pspp_adventure_run', true );
		if ( 'copy' === $context_mode ) {
			$result = ps_pen_paper_advance_adventure_run( $context_campaign, $context_run_id, $context_run['snapshot']['edges'][2]['id'], '1' );
			$check( is_wp_error( $result ) && 'adventure_resource_insufficient' === $result->get_error_code() &&
				$context_before === ps_pen_paper_raw_character_state( $context_character_id ) &&
				$context_run === get_post_meta( $context_run_id, '_pspp_adventure_run', true ), 'Cost crossed the configured positive lower bound.' );
		}
		$result = ps_pen_paper_advance_adventure_run( $context_campaign, $context_run_id, $context_run['snapshot']['edges'][0]['id'], '1' );
		if ( 'shared' === $context_mode ) {
			$spent_run = get_post_meta( $context_run_id, '_pspp_adventure_run', true );
			$check( true === $result && $context_before === ps_pen_paper_raw_character_state( $context_character_id ) &&
				ps_pen_paper_valid_adventure_run( $spent_run ) &&
				$cost_base_id === $spent_run['history'][0]['resource_cost']['shared_base']['character_id'] &&
				2 === ps_pen_paper_raw_character_state( $cost_base_id )['active']['values'][ $base_resource ]['current'],
				'Shared cost did not spend exactly once through the base while preserving the alias.' );
		} else {
			$context_after = ps_pen_paper_raw_character_state( $context_character_id );
			$expected_context = $context_before; $expected_context['revision']++; $expected_context['active']['values'][ $base_resource ]['current'] = 2;
			$check( true === $result && $expected_context === $context_after, 'Independent copy did not spend locally down to its minimum.' );
		}
		if ( 'copy' === $context_mode ) { $check( $cost_base_before === ps_pen_paper_raw_character_state( $cost_base_id ), 'Local cost changed the universe base character.' ); }
	}
	$cost_base_before = ps_pen_paper_raw_character_state( $cost_base_id );

	wp_set_current_user( $users['gm'] );
	$condition_input = array( 'mode' => 'all', 'rows' => array( array( 'target' => $attribute . ':value', 'operator' => 'eq', 'value' => '6' ) ) );
	foreach ( array(
		array( 'operator', 'sometimes' ), array( 'value', '6.5' ), array( 'value', '1000001' ),
		array( 'target', $resource . ':value' ), array( 'target', $attribute . ':maximum' ), array( 'target', wp_generate_uuid4() . ':value' ),
	) as $bad ) {
		$invalid_conditions = $condition_input; $invalid_conditions['rows'][0][ $bad[0] ] = $bad[1];
		$check( is_wp_error( ps_pen_paper_prepare_adventure_conditions( $changed_rules, $invalid_conditions ) ), 'Invalid value condition accepted.' );
	}
	$invalid_conditions = $condition_input; $invalid_conditions['mode'] = 'sometimes';
	$check( is_wp_error( ps_pen_paper_prepare_adventure_conditions( $changed_rules, $invalid_conditions ) ), 'Unknown condition mode accepted.' );
	$invalid_conditions = $condition_input; $invalid_conditions['rows'] = array_fill( 0, 21, $condition_input['rows'][0] );
	$check( is_wp_error( ps_pen_paper_prepare_adventure_conditions( $changed_rules, $invalid_conditions ) ), 'Unbounded condition list accepted.' );
	$condition_cases = array(
		array( 'operator' => 'gte', 'value' => '6', 'pass' => true ),
		array( 'operator' => 'gt', 'value' => '6', 'pass' => false ),
		array( 'operator' => 'gt', 'value' => '5', 'pass' => true ),
		array( 'operator' => 'lte', 'value' => '6', 'pass' => true ),
		array( 'operator' => 'lt', 'value' => '6', 'pass' => false ),
		array( 'operator' => 'lt', 'value' => '7', 'pass' => true ),
		array( 'operator' => 'eq', 'value' => '6', 'pass' => true ),
		array( 'operator' => 'ne', 'value' => '6', 'pass' => false ),
		array( 'operator' => 'ne', 'value' => '7', 'pass' => true ),
		array( 'operator' => 'eq', 'value' => '6', 'pass' => false, 'mode' => 'all' ),
		array( 'operator' => 'eq', 'value' => '6', 'pass' => true, 'mode' => 'any' ),
	);
	foreach ( $condition_cases as $case_index => $case ) {
		wp_set_current_user( $users['gm'] );
		$condition_adventure = ps_pen_paper_save_adventure( $campaign, 0, 'Comparison fixture ' . $case_index, (string) $scenes['START_ORIGINAL'], '0' );
		$check( is_int( $condition_adventure ), 'Comparison adventure failed.' );
		$conditions_input = $condition_input;
		$conditions_input['rows'][0]['operator'] = $case['operator']; $conditions_input['rows'][0]['value'] = $case['value'];
		if ( isset( $case['mode'] ) ) {
			$conditions_input['mode'] = $case['mode'];
			$conditions_input['rows'][] = array( 'target' => $resource . ':current', 'operator' => 'gte', 'value' => '1' );
		}
		$condition_edge_input = array( 'from_scene' => (string) $scenes['START_ORIGINAL'], 'choice' => 'COMPARISON_' . $case_index,
			'expression' => '', 'attribute' => '', 'threshold' => '0', 'success_scene' => '0', 'failure_scene' => '0', 'conditions' => $conditions_input );
		$check( true === ps_pen_paper_save_adventure_edge( $campaign, $condition_adventure, $condition_edge_input, '1' ) &&
			true === ps_pen_paper_publish_adventure( $campaign, $condition_adventure, '2', 'confirmed' ), 'Comparison publication failed.' );
		wp_set_current_user( $users['player'] );
		$condition_character_before = ps_pen_paper_raw_character_state( $characters['player'] );
		$condition_run_id = ps_pen_paper_start_adventure_run( $campaign, $condition_adventure, $characters['player'], '3', (string) $condition_character_before['revision'] );
		$check( is_int( $condition_run_id ), 'Comparison run failed.' );
		$condition_run_before = get_post_meta( $condition_run_id, '_pspp_adventure_run', true );
		$condition_edge_id = $condition_run_before['snapshot']['edges'][0]['id'];
		if ( 0 === $case_index ) {
			wp_set_current_user( $users['gm'] );
			$changed_character = array( 'name' => 'Player', 'values' => array( $attribute => '5', $resource => array( 'current' => '0', 'maximum' => '20' ) ) );
			$check( $characters['player'] === ps_pen_paper_save_character( $campaign_id, $characters['player'], $users['player'], $changed_character,
				(string) $condition_character_before['revision'], (string) $changed_rules['revision'] ), 'Stale comparison correction failed.' );
			wp_set_current_user( $users['player'] );
			$result = ps_pen_paper_advance_adventure_run( $campaign, $condition_run_id, $condition_edge_id, '1' );
			$check( is_wp_error( $result ) && 'adventure_conditions_unmet' === $result->get_error_code() &&
				$condition_run_before === get_post_meta( $condition_run_id, '_pspp_adventure_run', true ), 'Stale condition form used old approved values.' );
			wp_set_current_user( $users['gm'] );
			$changed_character['values'][ $attribute ] = '6';
			$check( $characters['player'] === ps_pen_paper_save_character( $campaign_id, $characters['player'], $users['player'], $changed_character,
				(string) ps_pen_paper_raw_character_state( $characters['player'] )['revision'], (string) $changed_rules['revision'] ), 'Comparison value restoration failed.' );
			$changed_edge = $condition_edge_input; $changed_edge['conditions']['rows'][0]['value'] = '100';
			$check( true === ps_pen_paper_save_adventure_edge( $campaign, $condition_adventure, array(), '3', $condition_edge_id ) &&
				true === ps_pen_paper_save_adventure_edge( $campaign, $condition_adventure, $changed_edge, '4' ) &&
				true === ps_pen_paper_publish_adventure( $campaign, $condition_adventure, '5', 'confirmed' ) &&
				$condition_run_before === get_post_meta( $condition_run_id, '_pspp_adventure_run', true ), 'Republication changed running condition rules.' );
			wp_set_current_user( $users['player'] );
			$condition_character_before = ps_pen_paper_raw_character_state( $characters['player'] );
		}
		if ( 1 === $case_index ) {
			$pending_input = array( 'name' => 'Player', 'values' => array( $attribute => '7', $resource => array( 'current' => '0', 'maximum' => '20' ) ) );
			$check( $characters['player'] === ps_pen_paper_save_character( $campaign_id, $characters['player'], $users['player'], $pending_input,
				(string) $condition_character_before['revision'], (string) $changed_rules['revision'] ), 'Pending comparison fixture failed.' );
			$pending_state = ps_pen_paper_raw_character_state( $characters['player'] );
			$result = ps_pen_paper_advance_adventure_run( $campaign, $condition_run_id, $condition_edge_id, '1' );
			$check( is_wp_error( $result ) && 'adventure_conditions_unmet' === $result->get_error_code() &&
				$pending_state === ps_pen_paper_raw_character_state( $characters['player'] ), 'Pending values satisfied a comparison.' );
			wp_set_current_user( $users['gm'] );
			$check( true === ps_pen_paper_review_character( $campaign_id, $characters['player'], 'reject', (string) $pending_state['revision'] ), 'Pending comparison cleanup failed.' );
			wp_set_current_user( $users['player'] );
			$condition_character_before = ps_pen_paper_raw_character_state( $characters['player'] );
		}
		$condition_events_before = ps_pen_paper_get_events( $campaign );
		if ( $case_index === count( $condition_cases ) - 1 ) {
			$fail_condition_event = static function ( $check, $id, $key, $value ) {
				return '_pspp_event' === $key && isset( $value['kind'] ) && 'adventure_run' === $value['kind'] ? false : $check;
			};
			add_filter( 'update_post_metadata', $fail_condition_event, 10, 4 );
			try { $check( is_wp_error( ps_pen_paper_advance_adventure_run( $campaign, $condition_run_id, $condition_edge_id, '1' ) ), 'Unlogged conditional progress succeeded.' ); }
			finally { remove_filter( 'update_post_metadata', $fail_condition_event, 10 ); }
			$check( $condition_run_before === get_post_meta( $condition_run_id, '_pspp_adventure_run', true ) &&
				$condition_events_before === ps_pen_paper_get_events( $campaign ), 'Conditional rollback changed progress/history.' );
		}
		$result = ps_pen_paper_advance_adventure_run( $campaign, $condition_run_id, $condition_edge_id, '1' );
		if ( $case['pass'] ) {
			$condition_run_after = get_post_meta( $condition_run_id, '_pspp_adventure_run', true );
			$check( true === $result && ps_pen_paper_valid_adventure_run( $condition_run_after ) &&
				6 === $condition_run_after['history'][0]['conditions']['rows'][0]['value'], 'Exact value comparison or condition proof failed.' );
			if ( isset( $case['mode'] ) ) {
				$check( false === $condition_run_after['history'][0]['conditions']['rows'][1]['met'], 'OR discarded its unmet condition evidence.' );
			}
			$invalid_run = $condition_run_after; $invalid_run['history'][0]['conditions']['rows'][0]['met'] = false;
			$check( ! ps_pen_paper_valid_adventure_run( $invalid_run ), 'Forged comparison result accepted.' );
			$invalid_run = $condition_run_after; unset( $invalid_run['history'][0]['conditions'] );
			$check( ! ps_pen_paper_valid_adventure_run( $invalid_run ), 'Missing condition proof accepted.' );
			$invalid_run = $condition_run_after; $invalid_run['snapshot']['edges'][0]['conditions']['rows'][0]['operator'] = true;
			$check( ! ps_pen_paper_valid_adventure_run( $invalid_run ), 'Untyped comparison operator accepted.' );
		} else {
			$check( is_wp_error( $result ) && 'adventure_conditions_unmet' === $result->get_error_code() &&
				$condition_run_before === get_post_meta( $condition_run_id, '_pspp_adventure_run', true ) &&
				$condition_events_before === ps_pen_paper_get_events( $campaign ), 'Unmet comparison advanced.' );
			ob_start(); ps_pen_paper_render_character_adventures( $campaign, get_post( $characters['player'] ), 0 ); $condition_html = ob_get_clean();
			$check( preg_match( '/<button[^>]*disabled[^>]*>COMPARISON_' . $case_index . '<\/button>/', $condition_html ) &&
				false !== strpos( $condition_html, 'nicht erfüllt' ), 'Blocked comparison was hidden or unexplained.' );
		}
		$check( $condition_character_before === ps_pen_paper_raw_character_state( $characters['player'] ), 'Read-only conditions consumed character values.' );
	}
	wp_set_current_user( $users['gm'] );
	$resource_condition_adventure = ps_pen_paper_save_adventure( $campaign, 0, 'Resource condition fixture', (string) $scenes['START_ORIGINAL'], '0' );
	$resource_conditions = array( 'mode' => 'all', 'rows' => array(
		array( 'target' => $resource . ':current', 'operator' => 'eq', 'value' => '7' ),
		array( 'target' => $resource . ':maximum', 'operator' => 'eq', 'value' => '20' ),
	) );
	$resource_condition_edge = array( 'from_scene' => (string) $scenes['START_ORIGINAL'], 'choice' => 'RESOURCE_COMPARISON', 'expression' => '',
		'attribute' => '', 'threshold' => '0', 'success_scene' => '0', 'failure_scene' => '0', 'conditions' => $resource_conditions,
		'cost_resource' => $resource, 'cost_amount' => '2', 'cost_timing' => 'attempt' );
	$check( is_int( $resource_condition_adventure ) && true === ps_pen_paper_save_adventure_edge( $campaign, $resource_condition_adventure, $resource_condition_edge, '1' ) &&
		true === ps_pen_paper_publish_adventure( $campaign, $resource_condition_adventure, '2', 'confirmed' ), 'Resource condition publication failed.' );
	wp_set_current_user( $users['other'] );
	$resource_character_before = ps_pen_paper_raw_character_state( $characters['other'] );
	$resource_condition_run = ps_pen_paper_start_adventure_run( $campaign, $resource_condition_adventure, $characters['other'], '3', (string) $resource_character_before['revision'] );
	$check( is_int( $resource_condition_run ), 'Resource condition run failed.' );
	$resource_run_before = get_post_meta( $resource_condition_run, '_pspp_adventure_run', true );
	$check( true === ps_pen_paper_advance_adventure_run( $campaign, $resource_condition_run, $resource_run_before['snapshot']['edges'][0]['id'], '1' ), 'Resource current/maximum condition failed.' );
	$resource_run_after = get_post_meta( $resource_condition_run, '_pspp_adventure_run', true );
	$check( ps_pen_paper_valid_adventure_run( $resource_run_after ) &&
		7 === $resource_run_after['history'][0]['conditions']['rows'][0]['value'] &&
		20 === $resource_run_after['history'][0]['conditions']['rows'][1]['value'] &&
		5 === ps_pen_paper_raw_character_state( $characters['other'] )['active']['values'][ $resource ]['current'],
		'Resource conditions did not record the approved pre-cost values.' );

	wp_set_current_user( $users['gm'] );
	$reward_input = array( 'kind' => 'fixed', 'timing' => 'decision', 'table_id' => '0', 'item_id' => '0', 'quantity' => '0', 'money' => '3' );
	$check( is_wp_error( ps_pen_paper_prepare_adventure_reward( $campaign, $reward_input ) ), 'Reward bypassed missing Community.' );
	define( 'CPC_CORE_PLUGINS', 'core-profile,core-activity' );
	require_once dirname( __DIR__, 2 ) . '/ps-community/activity/cpc_profile_tabs.php';
	$check( true === ps_pen_paper_save_currency( $campaign, array( 'name' => 'Reward marks', 'symbol' => 'RM', 'wallet_mode' => 'campaign' ), '0' ) &&
		true === ps_pen_paper_save_equipment_schema( $campaign, array( 'slots' => '', 'rarities' => 'normal | Normal | #abcdef' ), '0' ), 'Reward currency/schema failed.' );
	$reward_item_input = array( 'name' => 'Original reward compass', 'description' => '', 'kind' => 'tool', 'rarity' => 'normal', 'properties' => '', 'binding' => 'tradeable' );
	$reward_item_id = ps_pen_paper_save_item( $campaign, 0, $reward_item_input, '0' );
	$check( is_int( $reward_item_id ), 'Reward item failed.' );
	$reward_input['item_id'] = (string) $reward_item_id; $reward_input['quantity'] = '2';
	$table_input = array( 'name' => 'Selected reward table', 'die' => '6', 'rows' => array(
		array( 'from' => '1', 'to' => '6', 'label' => 'Selected outcome', 'item_id' => (string) $reward_item_id, 'quantity' => 'W6', 'money' => 'W6+4' ),
	) );
	$reward_table_id = ps_pen_paper_save_loot_table( $campaign, 0, $table_input, '0' );
	$check( is_int( $reward_table_id ), 'Reward table failed.' );
	ob_start(); ps_pen_paper_render_adventure_reward_fields( $campaign ); $reward_fields = ob_get_clean();
	$check( false !== strpos( $reward_fields, 'value="fixed"' ) && false !== strpos( $reward_fields, 'value="table"' ) &&
		false !== strpos( $reward_fields, 'value="' . $reward_item_id . '"' ) &&
		false !== strpos( $reward_fields, 'value="' . $reward_table_id . '"' ), 'Reward editor cannot select fixed items and loot tables.' );
	$private_table_input = $table_input; $private_table_input['name'] = 'UNSELECTED_REWARD_TABLE_SECRET';
	$check( is_int( ps_pen_paper_save_loot_table( $campaign, 0, $private_table_input, '0' ) ), 'Unselected table fixture failed.' );
	foreach ( array( array( 'money', '1000000000' ), array( 'quantity', '101' ), array( 'timing', 'sometimes' ), array( 'item_id', (string) $characters['player'] ) ) as $bad ) {
		$invalid_reward = $reward_input; $invalid_reward[ $bad[0] ] = $bad[1];
		$check( is_wp_error( ps_pen_paper_prepare_adventure_reward( $campaign, $invalid_reward ) ), 'Invalid reward accepted.' );
	}
	$reward_cases = array(
		array( 'name' => 'FIXED_COMBINED', 'kind' => 'fixed', 'timing' => 'decision', 'expression' => '', 'grant' => true ),
		array( 'name' => 'FAILED_SUCCESS_REWARD', 'kind' => 'fixed', 'timing' => 'success', 'expression' => 'W6-1000000', 'grant' => false ),
		array( 'name' => 'FAILED_DECISION_REWARD', 'kind' => 'fixed', 'timing' => 'decision', 'expression' => 'W6-1000000', 'grant' => true ),
		array( 'name' => 'SUCCESS_REWARD', 'kind' => 'fixed', 'timing' => 'success', 'expression' => 'W6+999990', 'grant' => true ),
		array( 'name' => 'TABLE_REWARD', 'kind' => 'table', 'timing' => 'success', 'expression' => '', 'grant' => true ),
	);
	foreach ( $reward_cases as $case_index => $case ) {
		wp_set_current_user( $users['gm'] );
		$reward_adventure_id = ps_pen_paper_save_adventure( $campaign, 0, $case['name'], (string) $scenes['START_ORIGINAL'], '0' );
		$check( is_int( $reward_adventure_id ), 'Reward adventure failed.' );
		$case_reward = $reward_input; $case_reward['kind'] = $case['kind']; $case_reward['timing'] = $case['timing'];
		if ( 'table' === $case['kind'] ) { $case_reward['table_id'] = (string) $reward_table_id; $case_reward['item_id'] = '0'; $case_reward['quantity'] = '0'; $case_reward['money'] = '0'; }
		$reward_edge_input = array( 'from_scene' => (string) $scenes['START_ORIGINAL'], 'choice' => $case['name'], 'expression' => $case['expression'], 'attribute' => '',
			'threshold' => '0', 'success_scene' => (string) $scenes['SUCCESS_SCENE'], 'failure_scene' => '0', 'reward' => $case_reward );
		if ( 0 === $case_index ) { $reward_edge_input['cost_resource'] = $resource; $reward_edge_input['cost_amount'] = '2'; $reward_edge_input['cost_timing'] = 'attempt'; }
		$check( true === ps_pen_paper_save_adventure_edge( $campaign, $reward_adventure_id, $reward_edge_input, '1' ) &&
			true === ps_pen_paper_publish_adventure( $campaign, $reward_adventure_id, '2', 'confirmed' ), 'Reward publication failed.' );
		if ( 0 === $case_index ) {
			$reward_item_input['name'] = 'Edited reward compass'; $reward_item_input['binding'] = 'bound';
			$check( $reward_item_id === ps_pen_paper_save_item( $campaign, $reward_item_id, $reward_item_input, '1' ), 'Reward template edit failed.' );
		}
		if ( 'table' === $case['kind'] ) {
			$table_input['rows'][0]['item_id'] = '0'; $table_input['rows'][0]['quantity'] = '0'; $table_input['rows'][0]['money'] = '0';
			$check( $reward_table_id === ps_pen_paper_save_loot_table( $campaign, $reward_table_id, $table_input, '1' ), 'Reward table edit failed.' );
		}
		wp_set_current_user( $users['other'] );
		$reward_char_id = $characters['other'];
		$reward_character_before = ps_pen_paper_raw_character_state( $reward_char_id );
		$reward_run_id = ps_pen_paper_start_adventure_run( $campaign, $reward_adventure_id, $reward_char_id, '3', (string) $reward_character_before['revision'] );
		$check( is_int( $reward_run_id ), 'Reward run failed.' );
		$reward_run_before = get_post_meta( $reward_run_id, '_pspp_adventure_run', true );
		$reward_edge = $reward_run_before['snapshot']['edges'][0]; $reward_edge_id = $reward_edge['id'];
		$reward_wallet_before = ps_pen_paper_wallet_context( $campaign, $reward_char_id )['wallet'];
		$reward_gear_before = ps_pen_paper_gear_state( $reward_char_id );
		if ( 0 === $case_index ) {
			foreach ( array( 'wallet_saved', 'gear_saved', 'adventure_run' ) as $failed_kind ) {
				$reward_events_before = ps_pen_paper_get_events( $campaign );
				$fail_reward_event = static function ( $check, $id, $key, $value ) use ( $failed_kind ) {
					return '_pspp_event' === $key && isset( $value['kind'] ) && $failed_kind === $value['kind'] ? false : $check;
				};
				add_filter( 'update_post_metadata', $fail_reward_event, 10, 4 );
				try { $check( is_wp_error( ps_pen_paper_advance_adventure_run( $campaign, $reward_run_id, $reward_edge_id, '1' ) ), 'Unlogged reward succeeded.' ); }
				finally { remove_filter( 'update_post_metadata', $fail_reward_event, 10 ); }
				$check( $reward_character_before === ps_pen_paper_raw_character_state( $reward_char_id ) &&
					$reward_wallet_before === ps_pen_paper_wallet_context( $campaign, $reward_char_id )['wallet'] &&
					$reward_gear_before === ps_pen_paper_gear_state( $reward_char_id ) &&
					$reward_run_before === get_post_meta( $reward_run_id, '_pspp_adventure_run', true ) &&
					$reward_events_before === ps_pen_paper_get_events( $campaign ) &&
					! ps_pen_paper_currency_document( $campaign_id )['in_use'], 'Reward failure left costs, money, items, currency freeze or progress.' );
			}
			$full = ps_pen_paper_append_item_instances( $reward_gear_before, $reward_edge['reward']['source']['item'], 100 );
			foreach ( array( 'active', 'pending' ) as $full_kind ) {
				$full_state = $full;
				if ( 'pending' === $full_kind ) { $full_state['pending'] = $full_state['active']; $full_state['active'] = $reward_gear_before['active']; }
				update_post_meta( $reward_char_id, '_pspp_gear', wp_slash( $full_state ) );
				$check( is_wp_error( ps_pen_paper_advance_adventure_run( $campaign, $reward_run_id, $reward_edge_id, '1' ) ) &&
					$full_state === ps_pen_paper_gear_state( $reward_char_id ) &&
					$reward_run_before === get_post_meta( $reward_run_id, '_pspp_adventure_run', true ), 'Full inventory was bypassed.' );
				delete_post_meta( $reward_char_id, '_pspp_gear' );
			}
			$overflow = $reward_wallet_before; $overflow['revision'] = 1; $overflow['balance'] = 999999998;
			update_post_meta( $reward_char_id, '_pspp_wallet', wp_slash( $overflow ) );
			$check( is_wp_error( ps_pen_paper_advance_adventure_run( $campaign, $reward_run_id, $reward_edge_id, '1' ) ) &&
				$overflow === ps_pen_paper_wallet_context( $campaign, $reward_char_id )['wallet'], 'Reward exceeded balance limit.' );
			delete_post_meta( $reward_char_id, '_pspp_wallet' );
		}
		$reward_advanced = ps_pen_paper_advance_adventure_run( $campaign, $reward_run_id, $reward_edge_id, '1' );
		$check( true === $reward_advanced, 'Configured reward failed: ' . $case['name'] . ( is_wp_error( $reward_advanced ) ? ' / ' . $reward_advanced->get_error_message() : '' ) );
		$reward_run_after = get_post_meta( $reward_run_id, '_pspp_adventure_run', true );
		$reward_result = $reward_run_after['history'][0]['reward'];
		$reward_wallet_after = ps_pen_paper_wallet_context( $campaign, $reward_char_id )['wallet'];
		$reward_gear_after = ps_pen_paper_gear_state( $reward_char_id );
		$check( ps_pen_paper_valid_adventure_run( $reward_run_after ) && 'completed' === $reward_run_after['status'], 'Reward history invalid.' );
		if ( $case['grant'] ) {
			$check( ps_pen_paper_valid_adventure_reward_result( $reward_edge['reward'], $reward_result, $reward_char_id ) &&
				$reward_wallet_after['balance'] - $reward_wallet_before['balance'] === $reward_result['money_roll']['total'] &&
				count( $reward_gear_after['active']['items'] ) - count( $reward_gear_before['active']['items'] ) === $reward_result['quantity_roll']['total'],
				'Reward differs from stored result.' );
			foreach ( $reward_result['item_ids'] as $uuid ) {
				$expected_item = ps_pen_paper_adventure_reward_rows( $reward_edge['reward'] )[ $reward_result['row'] ]['item'];
				$check( $expected_item === $reward_gear_after['active']['items'][ $uuid ], 'Reward ignored frozen item identity/binding.' );
			}
			$invalid_run = $reward_run_after; $invalid_run['history'][0]['reward']['wallet']['after']['balance']++;
			$check( ! ps_pen_paper_valid_adventure_run( $invalid_run ), 'Forged reward balance accepted.' );
			$invalid_run = $reward_run_after; $invalid_run['history'][0]['reward']['item_ids'][] = $reward_result['item_ids'][0];
			$check( ! ps_pen_paper_valid_adventure_run( $invalid_run ), 'Forged reward item count accepted.' );
		} else {
			$check( null === $reward_result && $reward_wallet_before === $reward_wallet_after && $reward_gear_before === $reward_gear_after &&
				$reward_character_before === ps_pen_paper_raw_character_state( $reward_char_id ), 'Failed success-only reward changed holdings.' );
		}
		if ( 0 === $case_index ) {
			$expected_character = $reward_character_before; $expected_character['revision']++; $expected_character['active']['values'][ $resource ]['current'] -= 2;
			$check( $expected_character === ps_pen_paper_raw_character_state( $reward_char_id ), 'Combined cost and reward failed.' );
		}
		$check( is_wp_error( ps_pen_paper_advance_adventure_run( $campaign, $reward_run_id, $reward_edge_id, '1' ) ) &&
			$reward_wallet_after === ps_pen_paper_wallet_context( $campaign, $reward_char_id )['wallet'] &&
			$reward_gear_after === ps_pen_paper_gear_state( $reward_char_id ), 'Reward replay duplicated holdings.' );
		$reward_event = ps_pen_paper_get_events( $campaign )['events'][0];
		$check( false === strpos( wp_json_encode( $reward_event ), 'UNSELECTED_REWARD_TABLE_SECRET' ), 'Unselected loot table leaked.' );
		ob_start(); ps_pen_paper_render_character_adventures( $campaign, get_post( $reward_char_id ), 0 ); $reward_html = ob_get_clean();
		$check( false !== strpos( $reward_html, $case['grant'] ? 'Belohnung:' : 'Bei Misserfolg keine Belohnung' ), 'Reward history UI missing.' );
		wp_set_current_user( $users['player'] );
		$check( is_wp_error( ps_pen_paper_get_event( $campaign, $reward_event['id'] ) ) &&
			is_wp_error( ps_pen_paper_get_loot_table( $campaign, $reward_table_id ) ), 'Other owner accessed private rewards/table.' );
	}

	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_save_currency( ps_pen_paper_get_universe( $cost_universe_id ),
		array( 'name' => 'Shared reward marks', 'symbol' => 'SRM', 'wallet_mode' => 'universe' ), '0' ), 'Shared reward currency failed.' );
	$shared_reward_input = array( 'kind' => 'fixed', 'timing' => 'decision', 'table_id' => '0', 'item_id' => '0', 'quantity' => '0', 'money' => '10' );
	add_post_meta( $context_campaigns['shared']->ID, '_pspp_gm', (string) $users['other'] );
	wp_set_current_user( $users['other'] );
	$check( is_wp_error( ps_pen_paper_prepare_adventure_reward( $context_campaigns['shared'], $shared_reward_input ) ), 'Campaign GM authorized shared money without universe rights.' );
	foreach ( array( 'shared', 'copy' ) as $mode ) {
		wp_set_current_user( $users['gm'] );
		$reward_campaign = $context_campaigns[ $mode ];
		$scene_posts = get_posts( array( 'post_type' => 'pspp_scene', 'post_status' => 'private', 'post_parent' => $reward_campaign->ID, 'posts_per_page' => 1 ) );
		$scene_id = $scene_posts[0]->ID;
		$shared_reward_adventure = ps_pen_paper_save_adventure( $reward_campaign, 0, 'Shared reward test', (string) $scene_id, '0' );
		$check( is_int( $shared_reward_adventure ) &&
			true === ps_pen_paper_save_adventure_edge( $reward_campaign, $shared_reward_adventure,
				array( 'from_scene' => (string) $scene_id, 'choice' => 'SHARED_REWARD', 'expression' => '', 'attribute' => '', 'threshold' => '0', 'success_scene' => '0', 'failure_scene' => '0', 'reward' => $shared_reward_input ), '1' ) &&
			true === ps_pen_paper_publish_adventure( $reward_campaign, $shared_reward_adventure, '2', 'confirmed' ), 'Shared reward publication failed.' );
		wp_set_current_user( $users['player'] );
		$shared_reward_char = $context_characters[ $mode ];
		$shared_reward_run = ps_pen_paper_start_adventure_run( $reward_campaign, $shared_reward_adventure, $shared_reward_char, '3',
			(string) ps_pen_paper_raw_character_state( $shared_reward_char )['revision'] );
		$check( is_int( $shared_reward_run ), 'Shared reward run failed.' );
		$shared_run_before = get_post_meta( $shared_reward_run, '_pspp_adventure_run', true ); $shared_edge_id = $shared_run_before['snapshot']['edges'][0]['id'];
		if ( 'shared' === $mode ) {
			wp_update_post( array( 'ID' => $cost_universe_id, 'post_author' => $users['other'] ) );
			$check( is_wp_error( ps_pen_paper_advance_adventure_run( $reward_campaign, $shared_reward_run, $shared_edge_id, '1' ) ) &&
				'' === get_post_meta( $cost_base_id, '_pspp_wallet', true ) &&
				$shared_run_before === get_post_meta( $shared_reward_run, '_pspp_adventure_run', true ), 'Revoked universe authority still paid.' );
			wp_update_post( array( 'ID' => $cost_universe_id, 'post_author' => $users['gm'] ) );
			$check( true === ps_pen_paper_advance_adventure_run( $reward_campaign, $shared_reward_run, $shared_edge_id, '1' ) &&
				10 === get_post_meta( $cost_base_id, '_pspp_wallet', true )['balance'] &&
				'' === get_post_meta( $shared_reward_char, '_pspp_wallet', true ), 'Authorized reward did not reach shared basis wallet.' );
		} else {
			$check( is_wp_error( ps_pen_paper_advance_adventure_run( $reward_campaign, $shared_reward_run, $shared_edge_id, '1' ) ) &&
				'' === get_post_meta( $shared_reward_char, '_pspp_wallet', true ) &&
				$shared_run_before === get_post_meta( $shared_reward_run, '_pspp_adventure_run', true ), 'Copy received unauthorized replacement wallet.' );
		}
		$check( $cost_base_before === ps_pen_paper_raw_character_state( $cost_base_id ), 'Reward changed base resources.' );
	}

	wp_set_current_user( $users['gm'] );
	$hybrid_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Hybrid foundation fixture' ), true );
	$check( is_int( $hybrid_id ) && $hybrid_id > 0, 'Hybrid campaign failed.' ); $parents[] = $hybrid_id;
	add_post_meta( $hybrid_id, '_pspp_gm', (string) $users['gm'] );
	add_post_meta( $hybrid_id, '_pspp_gm', (string) $users['other'] );
	add_post_meta( $hybrid_id, '_pspp_player', (string) $users['player'] );
	$hybrid = ps_pen_paper_get_campaign( $hybrid_id );
	$hybrid_rules = ps_pen_paper_save_rules( $hybrid_id, array( 'name' => 'Hybrid rules', 'description' => '', 'dice' => array( '4' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Access note', 'type' => 'text', 'description' => '' ),
		array( 'id' => '', 'label' => 'Energy', 'type' => 'resource', 'description' => '', 'min' => '0', 'max' => '10', 'start' => '7' ),
	) ), '0' );
	$check( ! is_wp_error( $hybrid_rules ), 'Hybrid rules failed.' );
	$text_id = $hybrid_rules['fields'][0]['id']; $energy_id = $hybrid_rules['fields'][1]['id'];
	$hybrid_char = ps_pen_paper_save_character( $hybrid_id, 0, $users['player'], array( 'name' => 'Hybrid player',
		'values' => array( $text_id => 'Access granted', $energy_id => array( 'current' => '7', 'maximum' => '10' ) ) ), '0', '1' );
	$check( is_int( $hybrid_char ), 'Hybrid character failed.' );
	$hybrid_scenes = array();
	foreach ( array( 'Hybrid opening', 'Hybrid continuation' ) as $title ) {
		$scene = ps_pen_paper_save_scene( $hybrid, 0, array( 'title' => $title, 'text' => $title, 'secret' => '', 'grid' => '0' ), '0' );
		$check( is_int( $scene ) && true === ps_pen_paper_publish_scene( $hybrid, $scene, '1', 'confirmed' ), 'Hybrid scene failed.' );
		$hybrid_scenes[] = $scene;
	}
	$hybrid_adventure = ps_pen_paper_save_adventure( $hybrid, 0, 'Hybrid route', (string) $hybrid_scenes[0], '0' );
	$hybrid_edge = array( 'from_scene' => (string) $hybrid_scenes[0], 'choice' => 'Ask for access', 'expression' => 'W4',
		'attribute' => '', 'threshold' => '0', 'success_scene' => (string) $hybrid_scenes[1], 'failure_scene' => '0',
		'cost_resource' => $energy_id, 'cost_amount' => '2', 'cost_timing' => 'attempt',
		'conditions' => array( 'mode' => 'all', 'rows' => array( array( 'target' => $text_id . ':text', 'operator' => 'contains', 'value' => 'granted' ) ) ),
		'gm_review' => '1' );
	$bad_text_edge = $hybrid_edge; $bad_text_edge['conditions']['rows'][0]['operator'] = 'gte';
	$check( is_wp_error( ps_pen_paper_save_adventure_edge( $hybrid, $hybrid_adventure, $bad_text_edge, '1' ) ), 'Numeric text comparison accepted.' );
	$check( true === ps_pen_paper_save_adventure_edge( $hybrid, $hybrid_adventure, $hybrid_edge, '1' ), 'Hybrid choice failed.' );
	$hybrid_edge_id = ps_pen_paper_get_adventure( $hybrid, $hybrid_adventure )['draft']['edges'][0]['id'];
	$follow_edge = array( 'from_scene' => (string) $hybrid_scenes[1], 'choice' => 'Use prior access', 'expression' => '',
		'attribute' => '', 'threshold' => '0', 'success_scene' => '0', 'failure_scene' => '0',
		'conditions' => array( 'mode' => 'all', 'rows' => array( array( 'target' => $hybrid_edge_id . ':history', 'operator' => 'eq', 'value' => '1' ) ) ) );
	$check( true === ps_pen_paper_save_adventure_edge( $hybrid, $hybrid_adventure, $follow_edge, '2' ) &&
		true === ps_pen_paper_publish_adventure( $hybrid, $hybrid_adventure, '3', 'confirmed' ), 'Historical choice publication failed.' );
	$hybrid_snapshot = ps_pen_paper_get_adventure( $hybrid, $hybrid_adventure )['published'];
	ob_start(); ps_pen_paper_render_adventure_condition_fields( $hybrid_rules, $hybrid_snapshot['edges'] ); $condition_editor = ob_get_clean();
	$check( 3 === substr_count( $condition_editor, $hybrid_edge_id . ':history' ), 'History options duplicated per character field.' );
	$forged_snapshot = $hybrid_snapshot; $forged_snapshot['edges'][1]['conditions']['rows'][0]['field'] = wp_generate_uuid4();
	$check( ! ps_pen_paper_valid_adventure_publication( $forged_snapshot ), 'Unknown historical decision accepted.' );
	wp_set_current_user( $users['player'] );
	$hybrid_run = ps_pen_paper_start_adventure_run( $hybrid, $hybrid_adventure, $hybrid_char, '4', '1' );
	$check( is_int( $hybrid_run ), 'Hybrid run failed.' );
	$read_hybrid = static function () use ( $hybrid_run ) { return get_post_meta( $hybrid_run, '_pspp_adventure_run', true ); };
	$hybrid_before = $read_hybrid(); $hybrid_character_before = ps_pen_paper_raw_character_state( $hybrid_char );
	$check( is_wp_error( ps_pen_paper_advance_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '1' ) ) &&
		$hybrid_before === $read_hybrid(), 'Unapproved decision advanced.' );
	$fail_review_event = static function ( $check, $id, $key, $value ) {
		return '_pspp_event' === $key && isset( $value['kind'] ) && 'adventure_run' === $value['kind'] ? false : $check;
	};
	add_filter( 'update_post_metadata', $fail_review_event, 10, 4 );
	try { $check( is_wp_error( ps_pen_paper_review_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '1' ) ), 'Unlogged review request succeeded.' ); }
	finally { remove_filter( 'update_post_metadata', $fail_review_event, 10 ); }
	$check( $hybrid_before === $read_hybrid(), 'Request rollback changed run.' );
	wp_set_current_user( $users['other'] );
	$check( is_wp_error( ps_pen_paper_review_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '1' ) ), 'Nonowner requested a decision.' );
	wp_set_current_user( $users['player'] );
	$check( true === ps_pen_paper_review_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '1' ), 'Owner request failed.' );
	$pending = $read_hybrid();
	$check( ps_pen_paper_valid_adventure_run( $pending ) && 'pending' === $pending['approval']['status'] && ! $pending['history'] &&
		$hybrid_character_before === ps_pen_paper_raw_character_state( $hybrid_char ) &&
		is_wp_error( ps_pen_paper_review_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '2', 'approved' ) ) &&
		is_wp_error( ps_pen_paper_advance_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '2' ) ), 'Pending request changed values or let a player approve.' );
	wp_set_current_user( $users['gm'] );
	ob_start(); ps_pen_paper_render_adventure_reviews( $hybrid, 0 ); $queue = ob_get_clean();
	$check( false !== strpos( $queue, 'Entscheidung freigeben' ) && false !== strpos( $queue, 'Hybrid player' ), 'GM queue missing.' );
	$check( true === ps_pen_paper_review_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '2', 'rejected' ), 'Review rejection failed.' );
	wp_set_current_user( $users['player'] );
	$check( true === ps_pen_paper_review_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '3' ), 'Rejected decision could not be requested again.' );
	wp_set_current_user( $users['other'] );
	$check( is_wp_error( ps_pen_paper_review_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '2', 'approved' ) ) &&
		true === ps_pen_paper_review_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '4', 'approved' ), 'Review revision checks failed.' );
	delete_post_meta( $hybrid_id, '_pspp_gm', (string) $users['other'] );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_advance_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '5' ) ) &&
		true === ps_pen_paper_review_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '5', 'cancel' ) &&
		true === ps_pen_paper_review_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '6' ), 'Revoked reviewer authority or re-request failed.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_review_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '7', 'approved' ), 'Replacement approval failed.' );
	wp_set_current_user( $users['player'] );
	$approved = $read_hybrid();
	add_filter( 'update_post_metadata', $fail_review_event, 10, 4 );
	try { $check( is_wp_error( ps_pen_paper_advance_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '8' ) ), 'Unlogged approved advancement succeeded.' ); }
	finally { remove_filter( 'update_post_metadata', $fail_review_event, 10 ); }
	$check( $approved === $read_hybrid() && $hybrid_character_before === ps_pen_paper_raw_character_state( $hybrid_char ), 'Advancement rollback consumed approval/resources.' );
	$check( true === ps_pen_paper_advance_adventure_run( $hybrid, $hybrid_run, $hybrid_edge_id, '8' ), 'Approved decision failed.' );
	$advanced = $read_hybrid();
	$check( null === $advanced['approval'] && count( $advanced['history'] ) === 1 &&
		$advanced['history'][0]['review']['reviewer_id'] === $users['gm'], 'Approval was not consumed and recorded.' );
	$check( $advanced['history'][0]['conditions']['rows'][0]['value'] === 'Access granted', 'Incorrect text proof: ' . wp_json_encode( $advanced['history'][0]['conditions'] ) );
	$check( ps_pen_paper_raw_character_state( $hybrid_char )['active']['values'][ $energy_id ]['current'] === 5,
		'Incorrect resource charge: ' . wp_json_encode( ps_pen_paper_raw_character_state( $hybrid_char )['active']['values'][ $energy_id ] ) );
	$last_edge = $advanced['snapshot']['edges'][1];
	$without_history = ps_pen_paper_evaluate_adventure_conditions( $last_edge, $hybrid_rules, ps_pen_paper_raw_character_state( $hybrid_char ), array(), $advanced['snapshot']['edges'] );
	$check( ! is_wp_error( $without_history ) && ! $without_history['met'] &&
		true === ps_pen_paper_advance_adventure_run( $hybrid, $hybrid_run, $last_edge['id'], '9' ), 'Prior-decision condition failed.' );
	$finished_hybrid = $read_hybrid();
	$check( ps_pen_paper_valid_adventure_run( $finished_hybrid ) && 'completed' === $finished_hybrid['status'] &&
		1 === $finished_hybrid['history'][1]['conditions']['rows'][0]['value'] &&
		is_wp_error( ps_pen_paper_advance_adventure_run( $hybrid, $hybrid_run, $last_edge['id'], '9' ) ), 'Completed history or replay validation failed.' );
	$forged_hybrid = $finished_hybrid; $forged_hybrid['reviews'] = 1; $forged_hybrid['revision'] = 4;
	$check( ! ps_pen_paper_valid_adventure_run( $forged_hybrid ), 'History approval accepted without request and approval revisions.' );

	wp_set_current_user( $users['gm'] );
	add_post_meta( $hybrid_id, '_pspp_player', (string) $users['other'] );
	add_post_meta( $hybrid_id, '_pspp_player', (string) $users['outsider'] );
	ob_start(); ps_pen_paper_render_adventure_groups( $hybrid, 0 ); $group_start_html = ob_get_clean();
	$document = new DOMDocument(); @$document->loadHTML( '<?xml encoding="UTF-8">' . $group_start_html );
	$xpath = new DOMXPath( $document );
	$form = $xpath->query( '//form[input[@name="group_operation" and @value="create"] and input[@name="adventure_id" and @value="' . $hybrid_adventure . '"]]' )->item( 0 );
	$check( null === $form && false !== strpos( $group_start_html, 'Game-Lounge' ), 'Old group creation form bypasses the lounge.' );
	$default_group = ps_pen_paper_write_adventure_group( $hybrid, 0, '0', 'create', array(
		'title' => 'Legacy group', 'rule' => 'any', 'adventure_id' => $hybrid_adventure,
		'adventure_revision' => (string) get_post_meta( $hybrid_adventure, '_pspp_adventure', true )['revision'],
	) );
	$check( is_int( $default_group ), 'Legacy group API no longer creates a valid group.' );
	foreach ( array( 'any', 'majority', 'unanimous', 'gm' ) as $group_rule ) {
		$group_chars = array();
		foreach ( array( 'player', 'other', 'outsider' ) as $owner ) {
			$group_chars[ $owner ] = ps_pen_paper_save_character( $hybrid_id, 0, $users[ $owner ], array( 'name' => 'Group ' . $owner . ' ' . $group_rule,
				'values' => array( $text_id => 'Access granted', $energy_id => array( 'current' => '7', 'maximum' => '10' ) ) ), '0', '1' );
			$check( is_int( $group_chars[ $owner ] ), 'Group character failed.' );
		}
		$group_id = ps_pen_paper_write_adventure_group( $hybrid, 0, '0', 'create', array( 'title' => 'Group ' . $group_rule,
			'rule' => $group_rule, 'adventure_id' => $hybrid_adventure, 'adventure_revision' => '4' ) );
		$check( is_int( $group_id ), 'Group creation failed: ' . $group_rule . ( is_wp_error( $group_id ) ? ' / ' . $group_id->get_error_message() : ' / ' . wp_json_encode( $group_id ) ) );
		$read_group = static function () use ( $group_id ) { return get_post_meta( $group_id, '_pspp_adventure_group', true ); };
		$group_action = static function ( $operation, $input = array() ) use ( $hybrid, $group_id, $read_group ) {
			return ps_pen_paper_write_adventure_group( $hybrid, $group_id, (string) $read_group()['revision'], $operation, $input );
		};
		wp_set_current_user( $users['player'] );
		$check( is_wp_error( $group_action( 'join', array( 'character_id' => $group_chars['other'] ) ) ), 'Player joined using another character.' );
		$check( true === $group_action( 'join', array( 'character_id' => $group_chars['player'] ) ), 'First group join failed.' );
		wp_set_current_user( $users['other'] );
		$check( true === $group_action( 'join', array( 'character_id' => $group_chars['other'] ) ), 'Second group join failed.' );
		$check_world_foundations = function () use ( $check, $users, $campaign, $scenes, $characters ) {
		wp_set_current_user( $users['gm'] );
		$world_key = ps_pen_paper_save_item( $campaign, 0, array( 'name' => 'World prerequisite key', 'description' => '',
			'kind' => 'tool', 'rarity' => 'normal', 'properties' => '', 'binding' => 'tradeable' ), '0' );
		$check( is_int( $world_key ), 'World prerequisite template failed.' );
		$world_id = ps_pen_paper_save_adventure( $campaign, 0, 'Independent world route', (string) $scenes['START_ORIGINAL'], '0' );
		$world_input = array( 'states' => array( array( 'id' => '', 'label' => 'Gate state', 'initial' => 'closed' ) ),
			'encounter' => array( 'title' => 'Gate encounter', 'phases' => array( (string) $scenes['START_ORIGINAL'], (string) $scenes['SUCCESS_SCENE'] ),
				'npcs' => array(), 'confirmation' => 'confirmed' ) );
		$check( true === ps_pen_paper_save_adventure_world( $campaign, $world_id, $world_input, '1' ), 'World definitions failed.' );
		$world_state = ps_pen_paper_get_adventure( $campaign, $world_id ); $state_id = $world_state['draft']['states'][0]['id'];
		$world_edge = array( 'from_scene' => (string) $scenes['START_ORIGINAL'], 'choice' => 'Open gate', 'expression' => '',
			'attribute' => '', 'threshold' => '0', 'success_scene' => (string) $scenes['SUCCESS_SCENE'], 'failure_scene' => '0',
			'items' => array( 'mode' => 'all', 'rows' => array( array( 'item_id' => (string) $world_key, 'minimum' => '1' ) ) ),
			'effects' => array( array( 'state' => $state_id, 'value' => 'open', 'when' => 'decision' ) ) );
		$check( true === ps_pen_paper_save_adventure_edge( $campaign, $world_id, $world_edge, '2' ), 'World decision failed.' );
		$world_finish = array( 'from_scene' => (string) $scenes['SUCCESS_SCENE'], 'choice' => 'Pass gate', 'expression' => '',
			'attribute' => '', 'threshold' => '0', 'success_scene' => '0', 'failure_scene' => '0',
			'conditions' => array( 'mode' => 'all', 'rows' => array( array( 'target' => $state_id . ':state', 'operator' => 'eq', 'value' => 'open' ) ) ) );
		$check( true === ps_pen_paper_save_adventure_edge( $campaign, $world_id, $world_finish, '3' ) &&
			true === ps_pen_paper_publish_adventure( $campaign, $world_id, '4', 'confirmed' ), 'World publication failed.' );
		$world_state = ps_pen_paper_get_adventure( $campaign, $world_id ); $world_edge_id = $world_state['published']['edges'][0]['id'];
		$bad_world = $world_state['published']; $bad_world['encounters'][0]['phases'] = array_reverse( $bad_world['encounters'][0]['phases'] );
		$check( ! ps_pen_paper_valid_adventure_publication( $bad_world ), 'Disconnected encounter phases accepted.' );
		$bad_world = $world_state['published']; $bad_world['edges'][0]['effects'] = 'invalid';
		$check( ! ps_pen_paper_valid_adventure_publication( $bad_world ), 'Malformed state effects accepted.' );
		wp_set_current_user( $users['player'] );
		$world_run = ps_pen_paper_start_adventure_run( $campaign, $world_id, $characters['player'], '5', (string) ps_pen_paper_raw_character_state( $characters['player'] )['revision'] );
		$check( is_int( $world_run ), 'World run failed.' );
		$world_before = get_post_meta( $world_run, '_pspp_adventure_run', true );
		$character_before = ps_pen_paper_raw_character_state( $characters['player'] );
		$check( is_wp_error( ps_pen_paper_advance_adventure_run( $campaign, $world_run, $world_edge_id, '1' ) ) &&
			$world_before === get_post_meta( $world_run, '_pspp_adventure_run', true ) &&
			$character_before === ps_pen_paper_raw_character_state( $characters['player'] ), 'Missing item changed run or character.' );
		wp_set_current_user( $users['gm'] );
		$gear_revision = ps_pen_paper_gear_state( $characters['player'] )['revision'];
		$check( true === ps_pen_paper_save_gear( $campaign, $characters['player'], 'grant_item',
			array( 'item_id' => (string) $world_key, 'item_revision' => '1' ), (string) $gear_revision ), 'Approved world key grant failed.' );
		wp_set_current_user( $users['player'] );
		$check( true === ps_pen_paper_advance_adventure_run( $campaign, $world_run, $world_edge_id, '1' ), 'Approved item did not permit world choice.' );
		$world_after = get_post_meta( $world_run, '_pspp_adventure_run', true );
		$check( ps_pen_paper_valid_adventure_run( $world_after ) && 1 === $world_after['history'][0]['items']['rows'][0]['count'] &&
			'closed' === $world_after['history'][0]['effects'][0]['before'] && 'open' === $world_after['history'][0]['effects'][0]['after'] &&
			'closed' === ps_pen_paper_adventure_state_values( $world_after['snapshot'], array() )[ $state_id ], 'World receipts or run isolation failed.' );
		$forged_world = $world_after; $forged_world['history'][0]['effects'][0]['before'] = 'open';
		$check( ! ps_pen_paper_valid_adventure_run( $forged_world ), 'Forged prior state accepted.' );
		$check( true === ps_pen_paper_advance_adventure_run( $campaign, $world_run, $world_state['published']['edges'][1]['id'], '2' ), 'State-conditioned continuation failed.' );
	};
		wp_set_current_user( $users['outsider'] );
		$check( null === ps_pen_paper_adventure_group_records( $hybrid )[ $group_id ]['state'] &&
			is_wp_error( $group_action( 'vote' ) ), 'Unjoined campaign member accessed private group history or voted.' );
		$check( true === $group_action( 'join', array( 'character_id' => $group_chars['outsider'] ) ), 'Third participant join failed.' );
		wp_set_current_user( $users['player'] );
		$check( true === $group_action( 'propose', array( 'character_id' => $group_chars['player'], 'edge_id' => $hybrid_edge_id ) ), 'Group proposal failed.' );
		$proposal_before = $read_group(); $personal_before = ps_pen_paper_raw_character_state( $group_chars['player'] );
		$check( is_wp_error( $group_action( 'execute' ) ) && $proposal_before === $read_group() &&
			$personal_before === ps_pen_paper_raw_character_state( $group_chars['player'] ), 'Unapproved or unagreed group proposal executed.' );
		wp_set_current_user( $users['gm'] );
		$check( is_wp_error( $group_action( 'execute' ) ) && true === $group_action( 'approve' ), 'GM impersonated the executor or could not approve.' );
		if ( 'any' === $group_rule ) {
			$check( true === $group_action( 'cancel' ), 'GM could not reset a proposal.' );
			add_post_meta( $hybrid_id, '_pspp_gm', (string) $users['other'] );
			wp_set_current_user( $users['player'] );
			$check( true === $group_action( 'propose', array( 'character_id' => $group_chars['player'], 'edge_id' => $hybrid_edge_id ) ), 'Replacement proposal failed.' );
			wp_set_current_user( $users['other'] );
			$check( true === $group_action( 'approve' ), 'Additional GM approval failed.' );
			delete_post_meta( $hybrid_id, '_pspp_gm', (string) $users['other'] );
			wp_set_current_user( $users['player'] );
			$check( is_wp_error( $group_action( 'execute' ) ) && true === $group_action( 'cancel' ) &&
				true === $group_action( 'propose', array( 'character_id' => $group_chars['player'], 'edge_id' => $hybrid_edge_id ) ), 'Revoked approval did not block or could not be replaced.' );
			wp_set_current_user( $users['gm'] ); $check( true === $group_action( 'approve' ), 'Current GM could not replace a revoked approval.' );
		}
		if ( in_array( $group_rule, array( 'majority', 'unanimous' ), true ) ) {
			wp_set_current_user( $users['player'] );
			$check( is_wp_error( $group_action( 'execute' ) ), 'Insufficient group votes advanced.' );
			wp_set_current_user( $users['other'] );
			$check( true === $group_action( 'vote' ) && is_wp_error( $group_action( 'vote' ) ) &&
				true === $group_action( 'unvote' ) && true === $group_action( 'vote' ) &&
				is_wp_error( $group_action( 'execute' ) ), 'Voting, duplicate protection or executor ownership failed.' );
			if ( 'unanimous' === $group_rule ) {
				wp_set_current_user( $users['player'] ); $check( is_wp_error( $group_action( 'execute' ) ), 'Two out of three votes satisfied unanimity.' );
				wp_set_current_user( $users['outsider'] ); $check( true === $group_action( 'vote' ), 'Third unanimity vote failed.' );
			}
		}
		wp_set_current_user( $users['player'] );
		$approved_group = $read_group();
		$fail_group_event = static function ( $check, $id, $key, $value ) {
			return '_pspp_event' === $key && isset( $value['kind'] ) && 'adventure_group' === $value['kind'] ? false : $check;
		};
		add_filter( 'update_post_metadata', $fail_group_event, 10, 4 );
		try { $check( is_wp_error( $group_action( 'execute' ) ), 'Unlogged group progression succeeded.' ); }
		finally { remove_filter( 'update_post_metadata', $fail_group_event, 10 ); }
		$check( $approved_group === $read_group() && $personal_before === ps_pen_paper_raw_character_state( $group_chars['player'] ),
			'Group rollback consumed approval, resources or progress.' );
		$stale_revision = (string) $approved_group['revision'];
		$check( true === $group_action( 'execute' ), 'Agreed group execution failed: ' . $group_rule );
		$shared = $read_group();
		$check( ps_pen_paper_valid_adventure_group( $shared ) && 1 === count( $shared['history'] ) &&
			$shared['scene_id'] === $hybrid_scenes[1] && $shared['history'][0]['owner_id'] === $users['player'] &&
			5 === ps_pen_paper_raw_character_state( $group_chars['player'] )['active']['values'][ $energy_id ]['current'] &&
			7 === ps_pen_paper_raw_character_state( $group_chars['other'] )['active']['values'][ $energy_id ]['current'] &&
			is_wp_error( ps_pen_paper_write_adventure_group( $hybrid, $group_id, $stale_revision, 'execute' ) ),
			'Shared progress, personal resources or replay protection failed.' );
		$shared_history = $shared['history'];
		wp_set_current_user( $users['other'] );
		ob_start(); ps_pen_paper_render_adventure_groups( $hybrid, 0 ); $other_group_html = ob_get_clean();
		$check( false === strpos( $other_group_html, 'freigegeben Access granted' ), 'Another actor saw private text values through group history.' );
		wp_set_current_user( $users['player'] );
		$check( true === $group_action( 'leave', array( 'character_id' => $group_chars['player'] ) ), 'Participant departure failed.' );
		$check( $shared_history === $read_group()['history'] && $hybrid_scenes[1] === $read_group()['scene_id'], 'Departure lost group progress.' );
		wp_set_current_user( $users['gm'] );
		$check( true === $group_action( 'pause' ), 'Group pause failed.' );
		wp_set_current_user( $users['player'] );
		$check( true === $group_action( 'join', array( 'character_id' => $group_chars['player'] ) ) &&
			is_wp_error( $group_action( 'propose', array( 'character_id' => $group_chars['player'], 'edge_id' => $last_edge['id'] ) ) ),
			'Reentry or paused group guard failed.' );
		wp_set_current_user( $users['gm'] );
		$check( true === $group_action( 'resume' ) && $shared_history === $read_group()['history'], 'Resumption lost progress.' );
		wp_set_current_user( $users['other'] );
		$check( true === $group_action( 'propose', array( 'character_id' => $group_chars['other'], 'edge_id' => $last_edge['id'] ) ), 'Another participant could not propose the next step.' );
		if ( 'gm' === $group_rule ) {
			wp_set_current_user( $users['gm'] ); $check( true === $group_action( 'approve' ), 'GM decision mode failed.' );
		} elseif ( in_array( $group_rule, array( 'majority', 'unanimous' ), true ) ) {
			wp_set_current_user( $users['player'] ); $check( true === $group_action( 'vote' ), 'Continuation vote failed.' );
			if ( 'unanimous' === $group_rule ) { wp_set_current_user( $users['outsider'] ); $check( true === $group_action( 'vote' ), 'Third continuation vote failed.' ); }
		}
		wp_set_current_user( $users['other'] );
		$check( true === $group_action( 'execute' ), 'Shared prior-decision requirement failed.' );
		$completed_group = $read_group();
		$check( ps_pen_paper_valid_adventure_group( $completed_group ) && 'completed' === $completed_group['status'] &&
			2 === count( $completed_group['history'] ) && $completed_group['history'][1]['owner_id'] === $users['other'] &&
			1 === $completed_group['history'][1]['decision']['conditions']['rows'][0]['value'] &&
			is_wp_error( $group_action( 'execute' ) ), 'Shared completion, different actor or history proof failed.' );
		$forged_group = $completed_group;
		$forged_group['history'][0]['votes'][] = $users['gm'];
		$check( ! ps_pen_paper_valid_adventure_group( $forged_group ), 'Forged nonparticipant vote accepted.' );
		wp_set_current_user( $users['gm'] );
		$group_event = ps_pen_paper_get_events( $hybrid )['events'][0];
		$check( 'adventure_group' === $group_event['kind'] && $completed_group === $group_event['after'], 'Group event omitted the shared receipt.' );
		wp_set_current_user( $users['player'] );
		$check( is_wp_error( ps_pen_paper_get_event( $hybrid, $group_event['id'] ) ), 'Participant accessed GM-private personal group proofs.' );
		wp_set_current_user( $users['gm'] );
		$raw_event = get_post_meta( $group_event['id'], '_pspp_event', true );
		$bad_event = $raw_event; $bad_event['after']['history'] = array();
		update_post_meta( $group_event['id'], '_pspp_event', $bad_event );
		try { $check( is_wp_error( ps_pen_paper_get_event( $hybrid, $group_event['id'] ) ), 'Corrupted group event bypassed native validation.' ); }
		finally { update_post_meta( $group_event['id'], '_pspp_event', $raw_event ); }
	}

	wp_set_current_user( $users['player'] );
	$check( true === ps_pen_paper_write_adventure_group( $hybrid, $default_group, '1', 'join', array( 'character_id' => $group_chars['player'] ) ),
		'Departure regression group join failed.' );
	$departure_group = get_post_meta( $default_group, '_pspp_adventure_group', true );
	wp_set_current_user( $users['gm'] );
	$leading_runs = ps_pen_paper_lounge_running_adventures( $hybrid );
	$check( ! is_wp_error( $leading_runs ) && in_array( 'pspp-adventure-group-' . $default_group, array_column( $leading_runs, 'anchor' ), true ),
		'Lounge hides the ongoing group from its nonparticipating game master.' );
	wp_set_current_user( $users['player'] );
	$check( true === ps_pen_paper_write_adventure_group( $hybrid, $default_group, (string) $departure_group['revision'], 'propose',
		array( 'character_id' => $group_chars['player'], 'edge_id' => $hybrid_edge_id ) ), 'Departure proposal failed.' );
	$departure_group = get_post_meta( $default_group, '_pspp_adventure_group', true );
	$departure_character = ps_pen_paper_raw_character_state( $group_chars['player'] );
	$membership_revision = (string) ps_pen_paper_membership_revision( $hybrid_id )['revision'];
	$fail_departure = static function ( $data ) {
		if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) { $data['post_status'] = 'draft'; }
		return $data;
	};
	add_filter( 'wp_insert_post_data', $fail_departure );
	try {
		$check( is_wp_error( ps_pen_paper_change_membership( $hybrid_id, $users['player'], 'leave', '', $membership_revision ) ) &&
			$departure_group === get_post_meta( $default_group, '_pspp_adventure_group', true ) &&
			'player' === ps_pen_paper_campaign_role( $hybrid, $users['player'] ), 'Unlogged departure changed membership or group proposal.' );
	} finally { remove_filter( 'wp_insert_post_data', $fail_departure ); }
	$check( true === ps_pen_paper_change_membership( $hybrid_id, $users['player'], 'leave', '', $membership_revision ), 'Campaign departure failed.' );
	$left_group = get_post_meta( $default_group, '_pspp_adventure_group', true );
	$check( null === $left_group['proposal'] && ! $left_group['participants'][0]['active'] &&
		$departure_group['history'] === $left_group['history'] && $departure_group['scene_id'] === $left_group['scene_id'] &&
		$departure_character === ps_pen_paper_raw_character_state( $group_chars['player'] ), 'Departure lost progress or left an active voter.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_change_membership( $hybrid_id, $users['player'], 'add', 'player',
		(string) ps_pen_paper_membership_revision( $hybrid_id )['revision'] ), 'Regression membership restoration failed.' );
	wp_set_current_user( $users['outsider'] );
	$check( is_wp_error( ps_pen_paper_start_adventure_run( $campaign, $adventure_id, $characters['player'], (string) $published['revision'], '1' ) ), 'Nonmember started an adventure run.' );
	$check_world_foundations();
	$check_scenarios = function () use ( $check, $users, &$parents ) {
		wp_set_current_user( $users['gm'] );
		$scope_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Scenario integration fixture' ), true );
		$check( is_int( $scope_id ), 'Scenario scope failed.' ); $parents[] = $scope_id;
		add_post_meta( $scope_id, '_pspp_gm', (string) $users['gm'] );
		foreach ( array( 'player', 'other' ) as $owner ) { add_post_meta( $scope_id, '_pspp_player', (string) $users[ $owner ] ); }
		$scope = ps_pen_paper_get_campaign( $scope_id );
		$rules = ps_pen_paper_save_rules( $scope_id, array( 'name' => 'Scenario rules', 'description' => '', 'dice' => array( '6' ),
			'fields' => array( array( 'id' => '', 'label' => 'Stufe', 'type' => 'number', 'description' => '', 'min' => '0', 'max' => '20', 'start' => '2' ) ) ), '0' );
		$check( ! is_wp_error( $rules ), 'Scenario rules failed.' ); $level = $rules['fields'][0]['id'];
		$chars = array();
		foreach ( array( 'player', 'other' ) as $owner ) {
			$chars[ $owner ] = ps_pen_paper_save_character( $scope_id, 0, $users[ $owner ], array( 'name' => $owner, 'values' => array( $level => '2' ) ), '0', '1' );
			$check( is_int( $chars[ $owner ] ), 'Scenario character failed.' );
		}
		$scene_ids = array();
		foreach ( array( 'Opening', 'Victory', 'Defeat' ) as $title ) {
			$id = ps_pen_paper_save_scene( $scope, 0, array( 'title' => $title, 'text' => '', 'secret' => '', 'grid' => '0' ), '0' );
			$check( is_int( $id ) && true === ps_pen_paper_publish_scene( $scope, $id, '1', 'confirmed' ), 'Scenario scene failed.' );
			$scene_ids[] = $id;
		}
		$chapter = ps_pen_paper_save_folio( $scope, 0, array( 'type' => 'chapter', 'title' => 'Chapter One', 'text' => 'PRIVATE_CHAPTER_TEXT',
			'secret' => 'PRIVATE_CHAPTER_SECRET', 'order' => '1', 'chapter_id' => '0', 'scene_id' => '0' ), '0' );
		$check( is_int( $chapter ), 'Scenario chapter failed.' );
		$check( true === ps_pen_paper_save_currency( $scope, array( 'name' => 'Scenario gold', 'symbol' => 'SG', 'wallet_mode' => 'campaign' ), '0' ) &&
			true === ps_pen_paper_save_equipment_schema( $scope, array( 'slots' => '', 'rarities' => 'normal | Normal | #abcdef' ), '0' ), 'Scenario economy failed.' );
		$item = ps_pen_paper_save_item( $scope, 0, array( 'name' => 'First reward', 'description' => '', 'kind' => 'tool', 'rarity' => 'normal', 'properties' => '', 'binding' => 'tradeable' ), '0' );
		$check( is_int( $item ), 'Scenario reward item failed.' );
		$policy = array( 'chapter_id' => (string) $chapter, 'order' => '1', 'mode' => 'both', 'repeat' => '1', 'rewards' => 'once',
			'minimum_field' => '', 'minimum_value' => '0', 'prerequisites' => array() );
		$edge = array( 'from_scene' => (string) $scene_ids[0], 'choice' => 'Win scenario', 'expression' => 'W6+100', 'attribute' => '',
			'threshold' => '10', 'success_scene' => (string) $scene_ids[1], 'failure_scene' => (string) $scene_ids[2],
			'outcomes' => array( 'success' => 'success', 'failure' => 'failure' ) );
		$create = function ( $title, $settings, $rewarded = false ) use ( $check, $scope, $scene_ids, $edge, $item ) {
			$id = ps_pen_paper_save_adventure( $scope, 0, $title, (string) $scene_ids[0], '0' );
			$check( is_int( $id ) && true === ps_pen_paper_save_adventure_scenario( $scope, $id, $settings, '1' ), 'Scenario settings failed: ' . $title );
			$input = $edge;
			if ( $rewarded ) {
				$input['reward'] = array( 'kind' => 'fixed', 'timing' => 'decision', 'table_id' => '0', 'item_id' => (string) $item, 'quantity' => '1', 'money' => '10' );
				$input['repeat_reward'] = array( 'kind' => 'fixed', 'timing' => 'decision', 'table_id' => '0', 'item_id' => '0', 'quantity' => '0', 'money' => '2' );
			}
			$check( true === ps_pen_paper_save_adventure_edge( $scope, $id, $input, '2' ) &&
				true === ps_pen_paper_publish_adventure( $scope, $id, '3', 'confirmed' ), 'Scenario publication failed: ' . $title );
			return $id;
		};
		$start = function ( $id, $owner = 'player', $character = null ) use ( $scope, $chars, $users ) {
			wp_set_current_user( $users[ $owner ] ); $character = $character ?? $chars[ $owner ];
			return ps_pen_paper_start_adventure_run( $scope, $id, $character, (string) ps_pen_paper_get_adventure( $scope, $id )['revision'],
				(string) ps_pen_paper_character_state( $scope, get_post( $character ) )['revision'] );
		};
		$finish = function ( $run_id ) use ( $check, $scope ) {
			$before = get_post_meta( $run_id, '_pspp_adventure_run', true );
			$check( true === ps_pen_paper_advance_adventure_run( $scope, $run_id, $before['snapshot']['edges'][0]['id'], '1' ), 'Scenario advance failed.' );
			$after = get_post_meta( $run_id, '_pspp_adventure_run', true );
			$check( ps_pen_paper_valid_adventure_run( $after ) && 'completed' === $after['status'], 'Scenario completion proof failed.' );
			return $after;
		};
		$first = null;
		foreach ( array( 'once' => 0, 'always' => 10, 'alternate' => 2 ) as $mode => $expected ) {
			wp_set_current_user( $users['gm'] ); $settings = $policy; $settings['rewards'] = $mode;
			$id = $create( 'Reward mode ' . $mode, $settings, true );
			if ( null === $first ) { $first = $id; }
			$run = $start( $id ); $check( is_int( $run ) && $run === $start( $id ), 'Start replay duplicated an active scenario.' );
			$receipt = $finish( $run );
			$check( 1 === $receipt['scenario_attempt'] && 10 === $receipt['history'][0]['reward']['money_roll']['total'], 'First scenario reward incorrect.' );
			wp_set_current_user( $users['gm'] );
			$check( true === ps_pen_paper_save_adventure_scenario( $scope, $id, $settings, '4' ) &&
				true === ps_pen_paper_publish_adventure( $scope, $id, '5', 'confirmed' ), 'Scenario re-publication failed.' );
			$again = $start( $id ); $check( is_int( $again ) && $again !== $run, 'Allowed repetition did not create an independent run.' );
			$before_money = ps_pen_paper_wallet_context( $scope, $chars['player'] )['wallet'];
			$before_gear = ps_pen_paper_gear_state( $chars['player'] );
			$repeat = $finish( $again ); $reward = $repeat['history'][0]['reward'];
			$check( 2 === $repeat['scenario_attempt'] && ( 0 === $expected ? null === $reward : $expected === $reward['money_roll']['total'] ), 'Repeat reward policy or publication counter failed.' );
			if ( 'once' === $mode ) {
				$check( $before_money === ps_pen_paper_wallet_context( $scope, $chars['player'] )['wallet'] &&
					$before_gear === ps_pen_paper_gear_state( $chars['player'] ), 'Once-only repetition changed money or inventory.' );
			}
			$forged = $repeat; $forged['scenario_attempt'] = 1;
			$check( ! ps_pen_paper_valid_adventure_run( $forged ), 'Forged first-attempt receipt was accepted.' );
			wp_set_current_user( $users['gm'] );
			$group = ps_pen_paper_write_adventure_group( $scope, 0, '0', 'create', array( 'adventure_id' => $id, 'adventure_revision' => '6', 'title' => 'Repeat group', 'rule' => 'any' ) );
			$check( is_int( $group ), 'Scenario group creation failed.' );
			wp_set_current_user( $users['player'] );
			$group_action = function ( $operation, $input = array() ) use ( $scope, $group ) {
				return ps_pen_paper_write_adventure_group( $scope, $group, (string) get_post_meta( $group, '_pspp_adventure_group', true )['revision'], $operation, $input );
			};
			$check( true === $group_action( 'join', array( 'character_id' => $chars['player'] ) ) &&
				true === $group_action( 'leave', array( 'character_id' => $chars['player'] ) ) &&
				true === $group_action( 'join', array( 'character_id' => $chars['player'] ) ), 'Group re-entry failed.' );
			$group_state = get_post_meta( $group, '_pspp_adventure_group', true );
			$check( 3 === $group_state['participants'][0]['scenario_attempt'], 'Solo/group counter or re-entry protection failed.' );
			$check( true === $group_action( 'propose', array( 'character_id' => $chars['player'], 'edge_id' => $group_state['snapshot']['edges'][0]['id'] ) ) &&
				true === $group_action( 'execute' ), 'Scenario group execution failed.' );
			$group_state = get_post_meta( $group, '_pspp_adventure_group', true );
			$reward = $group_state['history'][0]['decision']['reward'];
			$check( ps_pen_paper_valid_adventure_group( $group_state ) &&
				( 0 === $expected ? null === $reward : $expected === $reward['money_roll']['total'] ), 'Group bypassed repetition reward policy.' );
			if ( 'alternate' === $mode ) {
				$retry = $start( $id ); $retry_state = get_post_meta( $retry, '_pspp_adventure_run', true );
				$wallet_before = ps_pen_paper_wallet_context( $scope, $chars['player'] )['wallet'];
				$fail_event = static function ( $check, $object, $key, $value ) {
					return '_pspp_event' === $key && ( $value['kind'] ?? '' ) === 'adventure_run' ? false : $check;
				};
				add_filter( 'update_post_metadata', $fail_event, 10, 4 );
				try { $check( is_wp_error( ps_pen_paper_advance_adventure_run( $scope, $retry, $retry_state['snapshot']['edges'][0]['id'], '1' ) ), 'Failed repeat event was reported as successful.' ); }
				finally { remove_filter( 'update_post_metadata', $fail_event, 10 ); }
				$check( $wallet_before === ps_pen_paper_wallet_context( $scope, $chars['player'] )['wallet'] &&
					$retry_state === get_post_meta( $retry, '_pspp_adventure_run', true ), 'Failed repeat event left partial reward or progress.' );
				$finish( $retry );
			}
		}
		wp_set_current_user( $users['gm'] );
		$blocked = $policy; $blocked['repeat'] = '0'; $blocked['minimum_field'] = $level; $blocked['minimum_value'] = '3';
		$blocked['prerequisites'] = array( array( 'adventure_id' => (string) $first, 'result' => 'success' ) );
		$next = $create( 'Chapter Two', $blocked );
		$check( is_wp_error( $start( $next ) ) && is_wp_error( $start( $next, 'other' ) ), 'Minimum value or missing predecessor did not block entry.' );
		wp_set_current_user( $users['gm'] );
		$check( $chars['player'] === ps_pen_paper_save_character( $scope_id, $chars['player'], $users['player'],
			array( 'name' => 'player', 'values' => array( $level => '3' ) ), '1', '1' ), 'Scenario minimum adjustment failed.' );
		$check( is_int( $start( $next ) ), 'Won predecessor and minimum did not unlock next chapter.' );
		$progress = ps_pen_paper_scenario_progress( $scope, $users['player'] );
		$check( $progress[ $first ]['success'], 'Scenario victory was not retained.' );
		wp_set_current_user( $users['gm'] );
		$cycle = $policy; $cycle['prerequisites'] = array( array( 'adventure_id' => (string) $next, 'result' => 'completed' ) );
		$check( is_wp_error( ps_pen_paper_save_adventure_scenario( $scope, $first, $cycle, '6' ) ), 'Cyclic scenario prerequisites were accepted.' );
		$check( is_wp_error( ps_pen_paper_save_adventure_scenario( $scope, $next, $blocked, '1' ) ), 'Stale scenario settings overwrote newer state.' );
		wp_set_current_user( $users['player'] ); $finish( $start( $next ) );
		wp_set_current_user( $users['gm'] );
		$copy = ps_pen_paper_save_character( $scope_id, 0, $users['player'], array( 'name' => 'Another figure', 'values' => array( $level => '3' ) ), '0', '1' );
		$check( is_int( $copy ) && is_wp_error( $start( $next, 'player', $copy ) ), 'Another figure bypassed repeat prohibition.' );
		$another = $finish( $start( $first, 'player', $copy ) );
		$check( 4 === $another['scenario_attempt'] && null === $another['history'][0]['reward'], 'Another figure received the same first reward again.' );
		$check( is_wp_error( ps_pen_paper_save_adventure_scenario( $scope, $next, $blocked, '4' ) ), 'Player changed scenario policy.' );
		wp_set_current_user( $users['gm'] );
		$forbidden_group = ps_pen_paper_write_adventure_group( $scope, 0, '0', 'create',
			array( 'adventure_id' => $next, 'adventure_revision' => '4', 'title' => 'Forbidden repetition', 'rule' => 'any' ) );
		$check( is_int( $forbidden_group ), 'Repeat-prohibition group fixture failed.' );
		wp_set_current_user( $users['player'] );
		$check( is_wp_error( ps_pen_paper_write_adventure_group( $scope, $forbidden_group, '1', 'join', array( 'character_id' => $copy ) ) ), 'Group entry bypassed repeat prohibition.' );
		wp_set_current_user( $users['gm'] );
		$losing = $create( 'Defeat scenario', $policy );
		$losing_state = ps_pen_paper_get_adventure( $scope, $losing );
		$lose = $edge; $lose['expression'] = 'W6-100';
		$check( true === ps_pen_paper_save_adventure_edge( $scope, $losing, array(), '4', $losing_state['draft']['edges'][0]['id'] ) &&
			true === ps_pen_paper_save_adventure_edge( $scope, $losing, $lose, '5' ) &&
			true === ps_pen_paper_publish_adventure( $scope, $losing, '6', 'confirmed' ), 'Defeat path failed.' );
		$defeat = $finish( $start( $losing ) );
		$check( 'failure' === ps_pen_paper_scenario_outcome( $defeat['snapshot'], $defeat['history'] ), 'Completed defeat counted as victory.' );
		wp_set_current_user( $users['gm'] );
		$require_loss = $policy; $require_loss['prerequisites'] = array( array( 'adventure_id' => (string) $losing, 'result' => 'success' ) );
		$won_only = $create( 'Victory required', $require_loss );
		$require_loss['prerequisites'][0]['result'] = 'completed'; $completed_only = $create( 'Completion required', $require_loss );
		$check( is_wp_error( $start( $won_only ) ) && is_int( $start( $completed_only ) ), 'Won/completed prerequisites were not distinguished.' );
		wp_set_current_user( $users['gm'] );
		$solo = $policy; $solo['mode'] = 'solo'; $solo_id = $create( 'Solo only', $solo );
		$check( is_wp_error( ps_pen_paper_write_adventure_group( $scope, 0, '0', 'create',
			array( 'adventure_id' => $solo_id, 'adventure_revision' => '4', 'title' => 'Invalid group', 'rule' => 'any' ) ) ), 'Solo-only scenario allowed group creation.' );
		$solo['mode'] = 'group'; $group_only = $create( 'Group only', $solo );
		$check( is_wp_error( $start( $group_only ) ), 'Group-only scenario allowed solo entry.' );
		ob_start(); ps_pen_paper_render_character_adventures( $scope, get_post( $chars['player'] ), 0 ); $html = ob_get_clean();
		$check( false !== strpos( $html, 'Chapter One' ) && false !== strpos( $html, 'Szenario wiederholen' ) &&
			false === strpos( $html, 'PRIVATE_CHAPTER_' ), 'Chapter/continue UI missing or private chapter content leaked.' );
		wp_set_current_user( $users['gm'] );
		ob_start(); ps_pen_paper_render_scenario_editor( $scope, $next, ps_pen_paper_get_adventure( $scope, $next ),
			ps_pen_paper_adventure_records( $scope ), $rules, 0 ); $html = ob_get_clean();
		$check( false !== strpos( $html, 'name="adventure_scenario[minimum_field]"' ) &&
			false !== strpos( $html, 'name="adventure_scenario[prerequisites][0][adventure_id]"' ), 'Scenario editor lost configuration controls.' );
		$cycle_a = $create( 'Live cycle A', $policy );
		$cycle_policy = $policy; $cycle_policy['prerequisites'] = array( array( 'adventure_id' => (string) $cycle_a, 'result' => 'completed' ) );
		$cycle_b = $create( 'Live cycle B', $cycle_policy );
		$check( true === ps_pen_paper_save_adventure_scenario( $scope, $cycle_b, $policy, '4' ), 'Could not prepare removal of live predecessor.' );
		$cycle_policy['prerequisites'][0]['adventure_id'] = (string) $cycle_b;
		$check( true === ps_pen_paper_save_adventure_scenario( $scope, $cycle_a, $cycle_policy, '4' ) &&
			is_wp_error( ps_pen_paper_publish_adventure( $scope, $cycle_a, '5', 'confirmed' ) ), 'Unpublished predecessor edit concealed a live cycle.' );
		$check( true === ps_pen_paper_publish_adventure( $scope, $cycle_b, '5', 'confirmed' ) &&
			true === ps_pen_paper_publish_adventure( $scope, $cycle_a, '5', 'confirmed' ), 'Removing a published cycle dependency did not permit publication.' );
		$sorted = array(
			2 => array( 'published' => array( 'scenario' => array( 'order' => 2, 'chapter_id' => 0 ) ) ),
			1 => array( 'published' => array( 'scenario' => array( 'order' => 1, 'chapter_id' => $chapter ) ) ),
		);
		ps_pen_paper_sort_scenarios( $sorted );
		$check( array( 1, 2 ) === array_keys( $sorted ), 'Loose scenarios overrode configured chapter order.' );
		$records = array(); $map = array(); $archive = array( 'references' => array(), 'warnings' => array() );
		foreach ( array_merge( array( get_post( $scope_id ) ), get_posts( array( 'post_type' => array( 'pspp_scene', 'pspp_adventure', 'pspp_folio', 'pspp_item' ),
			'post_status' => 'private', 'post_parent' => $scope_id, 'posts_per_page' => -1 ) ) ) as $post ) {
			$records[ $post->ID ] = array( 'id' => $post->ID, 'type' => $post->post_type, 'status' => $post->post_status, 'parent_id' => (int) $post->post_parent );
			$map[ $post->ID ] = $post->ID + 1000000;
		}
		$options = array( 'users' => array( $users['gm'] => $users['other'] ), 'universe_mode' => 'copy' );
		foreach ( array( $first, $next ) as $id ) {
			$graph = ps_pen_paper_get_adventure( $scope, $id )['published'];
			$check( true === ps_pen_paper_prepare_import_adventure_snapshot( $graph, $scope_id, $records, $map, $options, $archive, 'scenario' ) &&
				ps_pen_paper_valid_adventure_publication( $graph ) && $graph['scenario']['chapter_id'] === $map[ $chapter ], 'Native import broke scenario chapter or publication.' );
			if ( $id === $next ) {
				$check( $graph['scenario']['prerequisites'][0]['adventure_id'] === $map[ $first ] &&
					$graph['scenario']['minimum']['field'] === $level, 'Native import broke predecessor or stable rule field.' );
			} else {
				$check( $graph['edges'][0]['repeat_reward']['authorizer_id'] === $users['other'] &&
					$graph['edges'][0]['repeat_reward']['source']['currency']['scope_id'] === $map[ $scope_id ], 'Native import did not remap repeat reward authority and currency.' );
			}
		}
	};
	$check_scenarios();
	echo "Adventure integration checks passed.\n";
} finally {
	wp_set_current_user( $original_user );
	$errors = array();
	$children = $parents ? get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_scene', 'pspp_folio', 'pspp_adventure', 'pspp_adventure_run', 'pspp_adventure_group', 'pspp_item', 'pspp_loot_table', 'pspp_event' ),
		'post_status' => array( 'private', 'draft' ), 'post_parent__in' => $parents, 'posts_per_page' => -1, 'fields' => 'ids' ) ) : array();
	foreach ( array_merge( $children, $parents ) as $id ) { if ( ! wp_delete_post( $id, true ) ) { $errors[] = 'Cannot remove adventure fixture ' . $id; } }
	foreach ( $users as $id ) { if ( ! ( is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ) ) ) { $errors[] = 'Cannot remove fixture account ' . $id; } }
	if ( $errors ) { throw new RuntimeException( implode( '; ', $errors ) ); }
}
