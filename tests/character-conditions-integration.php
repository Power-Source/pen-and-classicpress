<?php
/** Run with WP-CLI against a disposable development site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( 'campaign', 'universe', 'character', 'scene', 'event' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
ps_pen_paper_register_adventure_types();
$original_user = get_current_user_id(); $users = array(); $parents = array();
set_error_handler( static function ( $severity, $message, $file, $line ) {
	if ( error_reporting() & $severity ) { throw new ErrorException( $message, 0, $severity, $file, $line ); }
	return false;
} );
$check = static function ( $value, $message ) {
	if ( is_wp_error( $value ) || ! $value ) { throw new RuntimeException( $message . ( is_wp_error( $value ) ? ': ' . $value->get_error_message() : '' ) ); }
};
try {
	foreach ( array( 'world', 'gm', 'player', 'other' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-condition-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( $id, 'User fixture' ); $users[ $role ] = $id;
	}
	get_userdata( $users['world'] )->add_cap( 'ps_pen_paper_create_campaigns' );
	wp_set_current_user( $users['world'] );
	$universe_id = ps_pen_paper_save_universe( 0, array( 'name' => 'Condition universe', 'description' => '' ), '0' );
	$check( $universe_id, 'Universe fixture' ); $parents[] = $universe_id; $world = get_post( $universe_id );
	$rules = ps_pen_paper_save_rules( $universe_id, array( 'name' => 'Free rules', 'description' => '', 'dice' => array( '4' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Mut', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '20', 'start' => '5' ),
		array( 'id' => '', 'label' => 'Energie', 'type' => 'resource', 'description' => '', 'min' => '0', 'max' => '20', 'start' => '7' ),
	) ), '0' );
	$check( $rules, 'Rule fixture' ); $attribute = $rules['fields'][0]['id']; $resource = $rules['fields'][1]['id'];
	$base_id = ps_pen_paper_save_character( $universe_id, 0, $users['player'], array( 'name' => 'Condition hero', 'values' => array(
		$attribute => '5', $resource => array( 'current' => '7', 'maximum' => '20' ),
	) ), '0', '1' );
	$check( $base_id, 'Base fixture' );
	$campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Condition campaign' ), true );
	$check( $campaign_id, 'Campaign fixture' ); $parents[] = $campaign_id; $campaign = get_post( $campaign_id );
	add_post_meta( $campaign_id, '_pspp_gm', (string) $users['gm'] );
	add_post_meta( $campaign_id, '_pspp_gm', (string) $users['world'] );
	foreach ( array( 'player', 'other' ) as $role ) { add_post_meta( $campaign_id, '_pspp_player', (string) $users[ $role ] ); }
	$check( ps_pen_paper_save_universe_link( $campaign_id, $universe_id, '0', '1', 'confirmed' ), 'Link fixture' );
	$check( ps_pen_paper_adopt_universe_rules( $campaign_id, '1', 'confirmed' ), 'Rule adoption' );
	$character_id = ps_pen_paper_attach_base_character( $campaign_id, $base_id, '1', '1', 'shared', 'confirmed' );
	$check( $character_id, 'Shared fixture' );
	$character = get_post( $character_id );
	$raw = static function ( $id ) { return ps_pen_paper_raw_character_state( $id ); };
	$change = static function ( $id, $duration, $operation, $revision = null ) use ( $campaign, $character_id, $base_id, $raw ) {
		return ps_pen_paper_change_character_condition( $campaign, $character_id, $id, $duration, $operation,
			$revision ?? (string) $raw( 'universe' === $duration ? $base_id : $character_id )['revision'] );
	};
	$make = static function ( $name, $block = false ) {
		return array( 'id' => wp_generate_uuid4(), 'label' => $name, 'description' => 'Frei vorbereitete Folgen', 'block_actions' => $block,
			'block_join' => $block, 'modifiers' => array(), 'threshold' => null );
	};
	$injury = $make( 'Verletzt nach eigenem Regelwerk' ); $injury['modifiers'][ $attribute ] = -2;
	$captured = $make( 'Gefangen', true ); $dead = $make( 'Gestorben', true ); $empty = $make( 'Erschöpft', true );
	$empty['threshold'] = array( 'resource' => $resource, 'maximum' => 0 );
	$policy = ps_pen_paper_condition_policy_default(); $policy['revision'] = 1;
	foreach ( array( $injury, $captured, $dead, $empty ) as $definition ) { $policy['definitions'][ $definition['id'] ] = $definition; }
	$check( ps_pen_paper_save_condition_policy( $world, $policy, '0' ), 'World policy' );
	wp_set_current_user( $users['gm'] );
	$check( is_wp_error( ps_pen_paper_save_condition_policy( $campaign, $policy, '1' ) ), 'Campaign GM overrode world authority' );
	$before_base = $raw( $base_id );
	$check( is_wp_error( $change( $dead['id'], 'universe', 'set' ) ) && $before_base === $raw( $base_id ), 'Permanent effect bypassed world policy' );
	$check( $change( $injury['id'], 'local', 'set' ), 'Local injury' );
	$check( 3 === ps_pen_paper_character_state( $campaign, $character )['active']['values'][ $attribute ] &&
		5 === $raw( $character_id )['active']['values'][ $attribute ] && $before_base === $raw( $base_id ), 'Injury changed base values instead of effective values' );
	$check( is_wp_error( $change( $injury['id'], 'local', 'remove', '1' ) ), 'Stale correction accepted' );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( $change( $injury['id'], 'local', 'remove' ) ), 'Owner removed a GM consequence' );
	ob_start(); ps_pen_paper_render_character_conditions( $campaign, $character, 0, true ); $html = ob_get_clean();
	$check( false !== strpos( $html, $injury['label'] ) && false === strpos( $html, 'change_character_condition' ), 'Player projection leaked correction controls' );
	wp_set_current_user( $users['gm'] );
	$check( $change( $injury['id'], 'local', 'remove' ), 'Injury healing' );
	$check( 5 === ps_pen_paper_character_state( $campaign, $character )['active']['values'][ $attribute ], 'Healing did not remove modifier' );
	$check( $change( $captured['id'], 'local', 'set' ), 'Capture' );
	$check( is_wp_error( ps_pen_paper_require_character_game_action( $campaign, $character_id ) ), 'Capture did not block normal actions' );
	wp_set_current_user( $users['world'] );
	$policy['revision']++; $policy['campaign_universe'] = true; $policy['automatic'] = true;
	$check( ps_pen_paper_save_condition_policy( $world, $policy, '1' ), 'World delegation' );
	$check( true === ps_pen_paper_require_character_game_action( $campaign, $character_id, array( 'allows' => array( $captured['id'] ), 'requires' => array( $captured['id'] ) ) ), 'Prepared rescue was blocked' );
	wp_set_current_user( $users['gm'] );
	$check( $change( $captured['id'], 'local', 'remove' ), 'Release' );
	$check( $change( $dead['id'], 'universe', 'set' ), 'Delegated permanent death' );
	$check( isset( $raw( $base_id )['conditions'][ $dead['id'] ] ) &&
		is_wp_error( ps_pen_paper_require_character_conditions( $world, $base_id, 'join' ) ) &&
		is_wp_error( ps_pen_paper_require_character_game_action( $campaign, $character_id ) ) && get_post( $base_id ), 'Death did not persist or deleted the figure' );
	$check( $change( $dead['id'], 'universe', 'remove' ), 'Resurrection' );
	$base_zero = $raw( $base_id ); $base_zero['active']['values'][ $resource ]['current'] = 0;
	update_post_meta( $base_id, '_pspp_character', wp_slash( $base_zero ) );
	$values = ps_pen_paper_character_conditions( $campaign, $character, ps_pen_paper_character_state( $campaign, $character, false ) );
	$check( isset( $values[ $empty['id'] ] ) && ! isset( $values[ $dead['id'] ] ) &&
		! isset( $raw( $base_id )['conditions'][ $empty['id'] ] ), 'Zero resource imposed death or permanently latched the threshold rule' );
	$base_zero['active']['values'][ $resource ]['current'] = 7; update_post_meta( $base_id, '_pspp_character', wp_slash( $base_zero ) );
	$check( true === ps_pen_paper_require_character_game_action( $campaign, $character_id ), 'Recovery did not clear threshold restriction' );

	$scenes = array();
	foreach ( array( 'Start', 'Danach' ) as $name ) {
		$id = ps_pen_paper_save_scene( $campaign, 0, array( 'title' => $name, 'text' => $name, 'secret' => '', 'grid' => '0' ), '0' );
		$check( $id, 'Scene fixture' ); $check( ps_pen_paper_publish_scene( $campaign, $id, '1', 'confirmed' ), 'Scene publication' ); $scenes[] = $id;
	}
	$adventure_id = ps_pen_paper_save_adventure( $campaign, 0, 'Consequences', (string) $scenes[0], '0' ); $check( $adventure_id, 'Adventure fixture' );
	$first = array( 'from_scene' => (string) $scenes[0], 'choice' => 'Risk', 'expression' => 'W4', 'attribute' => '', 'threshold' => '100',
		'success_scene' => (string) $scenes[1], 'failure_scene' => (string) $scenes[1],
		'resource_cost' => array( 'resource' => $resource, 'amount' => 1, 'timing' => 'attempt' ),
		'character_conditions' => array( 'effects' => array( array( 'id' => $captured['id'], 'scope' => 'local', 'operation' => 'set', 'when' => 'failure' ) ) ) );
	// Cost preparation uses the editor's flat POST fields.
	unset( $first['resource_cost'] ); $first['cost_resource'] = $resource; $first['cost_amount'] = '1'; $first['cost_timing'] = 'attempt';
	$check( ps_pen_paper_save_adventure_edge( $campaign, $adventure_id, $first, '1' ), 'Consequence edge' );
	$last = array( 'from_scene' => (string) $scenes[1], 'choice' => 'Escape', 'expression' => '', 'attribute' => '', 'threshold' => '0', 'success_scene' => '0', 'failure_scene' => '0',
		'outcomes' => array( 'success' => 'failure', 'failure' => 'failure' ),
		'character_conditions' => array( 'allows' => array( $captured['id'] ), 'requires' => array( $captured['id'] ),
			'effects' => array( array( 'id' => $captured['id'], 'scope' => 'local', 'operation' => 'remove', 'when' => 'success' ) ) ) );
	$check( ps_pen_paper_save_adventure_edge( $campaign, $adventure_id, $last, '2' ), 'Rescue edge' );
	$check( ps_pen_paper_publish_adventure( $campaign, $adventure_id, '3', 'confirmed' ), 'Adventure publication' );
	$published = get_post_meta( $adventure_id, '_pspp_adventure', true );
	wp_set_current_user( $users['player'] );
	$run_id = ps_pen_paper_start_adventure_run( $campaign, $adventure_id, $character_id, (string) $published['revision'], (string) $raw( $character_id )['revision'] );
	$check( $run_id, 'Solo start' );
	$run_before = get_post_meta( $run_id, '_pspp_adventure_run', true ); $char_before = $raw( $character_id ); $base_before = $raw( $base_id );
	$fail = static function ( $data, $postarr ) {
		if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] && ! empty( $postarr['ID'] ) &&
			'adventure_run' === ( get_post_meta( $postarr['ID'], '_pspp_event', true )['kind'] ?? null ) ) { $data['post_status'] = 'draft'; }
		return $data;
	};
	add_filter( 'wp_insert_post_data', $fail, 10, 2 );
	try { $failed = ps_pen_paper_advance_adventure_run( $campaign, $run_id, $published['published']['edges'][0]['id'], '1' ); }
	finally { remove_filter( 'wp_insert_post_data', $fail, 10 ); }
	$check( is_wp_error( $failed ) && $run_before === get_post_meta( $run_id, '_pspp_adventure_run', true ) &&
		$char_before === $raw( $character_id ) && $base_before === $raw( $base_id ), 'Late event failure did not roll back consequences, cost and progress together' );
	$check( ps_pen_paper_advance_adventure_run( $campaign, $run_id, $published['published']['edges'][0]['id'], '1' ), 'Solo consequence' );
	$check( isset( $raw( $character_id )['conditions'][ $captured['id'] ] ), 'Failed roll did not capture acting character' );
	$check( ps_pen_paper_advance_adventure_run( $campaign, $run_id, $published['published']['edges'][1]['id'], '2' ), 'Solo rescue' );
	$run = get_post_meta( $run_id, '_pspp_adventure_run', true );
	$check( 'completed' === $run['status'] && 'failure' === ps_pen_paper_scenario_outcome( $run['snapshot'], $run['history'] ) &&
		empty( $raw( $character_id )['conditions'] ) && empty( $raw( $base_id )['conditions'] ), 'Adventure defeat was confused with permanent death' );

	wp_set_current_user( $users['gm'] );
	$group_id = ps_pen_paper_write_adventure_group( $campaign, 0, '0', 'create', array( 'title' => 'Consequence group', 'rule' => 'any',
		'adventure_id' => $adventure_id, 'adventure_revision' => (string) $published['revision'] ) );
	$check( $group_id, 'Group fixture' );
	$group_action = static function ( $operation, $input = array() ) use ( $group_id, $campaign ) {
		return ps_pen_paper_write_adventure_group( $campaign, $group_id, (string) get_post_meta( $group_id, '_pspp_adventure_group', true )['revision'], $operation, $input );
	};
	wp_set_current_user( $users['player'] );
	$check( $group_action( 'join', array( 'character_id' => $character_id ) ), 'Group join' );
	foreach ( $published['published']['edges'] as $edge ) {
		$check( $group_action( 'propose', array( 'character_id' => $character_id, 'edge_id' => $edge['id'] ) ), 'Group proposal including rescue' );
		$check( $group_action( 'execute' ), 'Group execution including rescue' );
	}
	$check( empty( $raw( $character_id )['conditions'] ) && 'completed' === get_post_meta( $group_id, '_pspp_adventure_group', true )['status'], 'Group consequences/rescue did not persist correctly' );
	wp_set_current_user( $users['world'] );
	$export = ps_pen_paper_export_campaign( $campaign_id, 'complete' );
	$check( $export, 'Export with consequences' );
	$parsed = ps_pen_paper_parse_import( wp_json_encode( $export ) ); $check( $parsed, 'Import parsing of status snapshots and events' );
	$documents = ps_pen_paper_import_documents( $parsed );
	$check( empty( $documents['conflicts'] ), 'Import document validation: ' . implode( ', ', $documents['conflicts'] ) );
	echo "Character conditions: permissions, local/permanent effects, rescue, thresholds, modifiers, rollback, solo/group and backup passed.\n";
} finally {
	wp_set_current_user( $original_user );
	foreach ( array_reverse( $parents ) as $parent ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_event', 'pspp_scene', 'pspp_adventure', 'pspp_adventure_run', 'pspp_adventure_group' ),
			'post_status' => array( 'private', 'draft', 'trash' ), 'post_parent' => $parent, 'posts_per_page' => -1 ) ) as $post ) { wp_delete_post( $post->ID, true ); }
		wp_delete_post( $parent, true );
	}
	foreach ( $users as $id ) { wp_delete_user( $id ); }
	restore_error_handler();
}
