<?php
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( ! defined( 'CPC_CORE_PLUGINS' ) ) { define( 'CPC_CORE_PLUGINS', 'core-profile,core-activity,core-friendships' ); }
require_once dirname( __DIR__, 2 ) . '/ps-community/activity/cpc_profile_tabs.php';
require_once dirname( __DIR__, 2 ) . '/ps-community/friendships/cpc_friendships_core.php';
require_once dirname( __DIR__, 2 ) . '/ps-community/friendships/cpc_custom_post_friendships.php';
cpc_custom_post_friendship();
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
ps_pen_paper_register_campaign_type();
ps_pen_paper_register_universe_type();
ps_pen_paper_register_character_type();
ps_pen_paper_register_scene_type();
ps_pen_paper_register_event_type();
ps_pen_paper_register_adventure_types();
ps_pen_paper_register_item_type();
ps_pen_paper_register_trade_type();
ps_pen_paper_register_npc_type();

$original_user = get_current_user_id();
$users = array(); $parents = array(); $friendships = array();
$normal_friends = static function () { return 0; };
add_filter( 'pre_option_cpc_friendships_all', $normal_friends );
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
	$npc_input = array( 'kind' => 'enemy', 'name' => 'Prepared watcher', 'description' => 'A watcher',
		'secret' => 'REACTION_PRIVATE_SECRET', 'scene_id' => '0', 'properties' => '', 'values' => 'Attention | 4' );
	$npc_id = $success( ps_pen_paper_save_npc( $source, 0, $npc_input, '0' ), 'Reaction NPC fixture failed' );
	$reaction = array( 'npc' => $npc_id . ':1:0', 'expression' => '1W6', 'threshold' => '100', 'when' => 'decision',
		'success_text' => 'Watcher reacts', 'failure_text' => 'Watcher waits', 'confirmation' => 'confirmed' );
	$stale_reaction = $reaction; $stale_reaction['npc'] = $npc_id . ':999:0';
	$check( is_wp_error( ps_pen_paper_prepare_adventure_reaction( $source, $stale_reaction, array(), $rules ) ), 'Stale NPC choice accepted.' );
	$unconfirmed = $reaction; unset( $unconfirmed['confirmation'] );
	$check( is_wp_error( ps_pen_paper_prepare_adventure_reaction( $source, $unconfirmed, array(), $rules ) ), 'NPC value published without explicit confirmation.' );
	$success( ps_pen_paper_save_adventure_edge( $source, $adventure_id, array( 'from_scene' => (string) $scene_id, 'choice' => 'Finish',
		'expression' => '', 'attribute' => '', 'threshold' => '0', 'success_scene' => '0', 'failure_scene' => '0',
		'cost_resource' => $field, 'cost_amount' => '2', 'cost_timing' => 'attempt', 'reaction' => $reaction ), '1' ), 'Adventure edge failed' );
	$scenario_input = array( 'chapter_id' => '0', 'order' => '0', 'mode' => 'both', 'repeat' => '1', 'rewards' => 'once',
		'minimum_field' => '', 'minimum_value' => '0', 'prerequisites' => array() );
	$success( ps_pen_paper_save_adventure_scenario( $source, $adventure_id, $scenario_input, '2' ), 'Scenario fixture failed' );
	$success( ps_pen_paper_publish_adventure( $source, $adventure_id, '3', 'confirmed' ), 'Adventure publication failed' );
	$next_adventure = $success( ps_pen_paper_save_adventure( $source, 0, 'Next adventure', (string) $scene_id, '0' ), 'Dependent adventure fixture failed' );
	$success( ps_pen_paper_save_adventure_edge( $source, $next_adventure, array( 'from_scene' => (string) $scene_id, 'choice' => 'Finish next',
		'expression' => '', 'attribute' => '', 'threshold' => '0', 'success_scene' => '0', 'failure_scene' => '0' ), '1' ), 'Dependent edge fixture failed' );
	$next_input = $scenario_input; $next_input['prerequisites'] = array( array( 'adventure_id' => (string) $adventure_id, 'result' => 'completed' ) );
	$success( ps_pen_paper_save_adventure_scenario( $source, $next_adventure, $next_input, '2' ), 'Prerequisite fixture failed' );
	$success( ps_pen_paper_publish_adventure( $source, $next_adventure, '3', 'confirmed' ), 'Dependent publication failed' );
	$success( ps_pen_paper_save_play_options( $source, array( 'guided', 'solo', 'group' ), true, '0' ), 'Mode options failed' );
	$token = wp_generate_uuid4();
	add_post_meta( $source_id, '_pspp_booking_links', array( 'revision' => 1, 'events' => array( 123 ) ) );
	$a = $success( ps_pen_paper_create_play_round( $source, 'guided', 0, $token, '1' ), 'First guided round failed' ); $parents[] = $a;
	$check( array() === ps_pen_paper_booking_links( $a )['events'], 'New round inherited scheduling references from the template.' );
	$check( $a === ps_pen_paper_create_play_round( $source, 'guided', 0, $token, '1' ), 'Repeated create produced a second round.' );
	$check( is_wp_error( ps_pen_paper_create_play_round( $source, 'solo', $adventure_id, $token, '1' ) ), 'Changed create payload reused an action token.' );
	$b = $success( ps_pen_paper_create_play_round( $source, 'guided', 0, wp_generate_uuid4(), '1' ), 'Second guided round failed' ); $parents[] = $b;
	$round_a = ps_pen_paper_get_campaign( $a ); $round_b = ps_pen_paper_get_campaign( $b );
	$check( ! ps_pen_paper_play_round( $source_id ) && 'lobby' === ps_pen_paper_play_round( $a )['status'] &&
		! ps_pen_paper_game_round( $a )['active'] && ! get_post_meta( $a, '_pspp_player', false ), 'Creating a round started play or copied players.' );
	$check( ! in_array( $a, wp_list_pluck( ps_pen_paper_get_campaigns(), 'ID' ), true ), 'Runtime appeared as a prepared campaign.' );
	$check( is_wp_error( ps_pen_paper_change_play_round( $round_a, 'start', '1' ) ), 'Empty lobby started.' );
	ob_start(); ps_pen_paper_render_play_round( $source, $round_a, 0, ps_pen_paper_play_round( $a ) ); $empty_lobby = ob_get_clean();
	$check( false === strpos( $empty_lobby, 'name="play_action" value="start"' ) &&
		false === strpos( $empty_lobby, 'Du brauchst einen eigenen' ), 'Empty guided lobby offered an empty start or required a GM character.' );
	foreach ( array( 'player' => $round_a, 'other' => $round_b ) as $owner => $round ) {
		wp_set_current_user( $users[ $owner ] );
		$success( ps_pen_paper_change_play_round( $round, 'join', '1', $characters[ $owner ], '1' ), 'Joining own character failed' );
		$check( ! ps_pen_paper_can_manage_campaign( $round ), 'Joining granted GM rights.' );
	}
	wp_set_current_user( $users['gm'] );
	ob_start(); ps_pen_paper_render_play_round( $source, $round_a, 0, ps_pen_paper_play_round( $a ) ); $joined_lobby = ob_get_clean();
	$check( false === strpos( $joined_lobby, ' · player</li>' ), 'Round roster disclosed an unshared character name.' );
	ob_start(); ps_pen_paper_render_play_directory( $source, 0, 'guided' ); $directory = ob_get_clean();
	$check( false !== strpos( $directory, esc_html( ps_pen_paper_adventure_group_user_label( $users['player'] ) ) ) &&
		false !== strpos( $directory, esc_html( $round_a->post_title ) ), 'Round directory failed to render the participant card.' );
	foreach ( array( $round_a, $round_b ) as $round ) { $success( ps_pen_paper_change_play_round( $round, 'start', '2' ), 'Starting guided round failed' ); }
	set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
	ob_start();
	try {
		ps_pen_paper_render_journal( $round_a );
		$journal = ob_get_contents();
		$check( false !== strpos( $journal, esc_html( ps_pen_paper_play_statuses()['active'] ) ), 'Round history omitted its lifecycle status.' );
	} finally {
		ob_end_clean(); restore_error_handler();
	}
	wp_set_current_user( $users['player'] );
	$alias_id = ps_pen_paper_play_round( $a )['participants'][0]['character_id'];
	$frontend_page = $success( wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish',
		'post_author' => $users['gm'], 'post_title' => 'Round sheet routing fixture', 'post_content' => '[ps_pen_paper]' ), true ), 'Frontend page fixture failed' );
	$saved_query = $GLOBALS['wp_query']; $saved_get = $_GET;
	try {
		$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $frontend_page ) );
		$smilies_priority = has_filter( 'the_content', 'convert_smilies' );
		ps_pen_paper_prepare_frontend();
		$large_image_data = 'data:image/png;base64,' . str_repeat( 'A', 4 * (int) ceil( 2 * MB_IN_BYTES / 3 ) );
		$check( false !== strpos( apply_filters( 'the_content', '<img src="' . $large_image_data . '">' ), $large_image_data ), 'Large protected images broke the frontend content pipeline.' );
		wp_set_current_user( $users['gm'] );
		$_GET = array();
		$lounge = ps_pen_paper_frontend_shortcode();
		$check( false !== strpos( $lounge, 'pspp_view=lead' ) && false === strpos( $lounge, 'aria-label="Verwaltungsbereiche"' ) &&
			false === strpos( $lounge, 'Welt vorbereiten' ), 'GM player lounge still embedded management navigation.' );
		$_GET = array( 'pspp_campaign' => (string) $source_id );
		$entry = ps_pen_paper_frontend_shortcode();
		$check( false !== strpos( $entry, 'id="pspp-play"' ) && false !== strpos( $entry, 'id="pspp-log"' ) &&
			false === strpos( $entry, 'value="save_scene"' ), 'Campaign entry lost existing information anchors or exposed the scene editor.' );
		$check( false === strpos( $entry, 'Round adventure · Revision' ) && false === strpos( $entry, 'SECRET_ROUND_NOTE' ),
			'Player campaign entry exposed privileged preparation history.' );
		$_GET = array( 'pspp_campaign' => (string) $source_id, 'pspp_mode' => 'guided' );
		$player_directory = ps_pen_paper_frontend_shortcode();
		$check( false === strpos( $player_directory, 'value="save_play_options"' ) &&
			false === strpos( $player_directory, 'value="create_play_round"' ) &&
			false === strpos( $player_directory, 'value="change_booking_link"' ), 'GM player view exposed preparation or guided round creation.' );
		$_GET['pspp_view'] = 'lead';
		$lead_directory = ps_pen_paper_frontend_shortcode();
		$check( false !== strpos( $lead_directory, 'value="save_play_options"' ) &&
			false !== strpos( $lead_directory, 'value="create_play_round"' ) &&
			false !== strpos( $lead_directory, 'name="pspp_view" value="lead"' ), 'Leadership lost its forms or their return context.' );
		$check( false !== strpos( ps_pen_paper_game_url( $frontend_page, $source_id ), 'pspp_view=lead' ), 'Leadership links lost the selected workspace.' );
		unset( $_GET['pspp_mode'] );
		$preparation = ps_pen_paper_frontend_shortcode();
		$check( false !== strpos( $preparation, 'value="save_scene"' ) && false !== strpos( $preparation, 'value="save_npc"' ) &&
			false !== strpos( $preparation, 'Kampagnenvorbereitung' ), 'Dedicated campaign preparation was unavailable.' );
		$check( false !== strpos( $preparation, 'Round adventure · Revision' ) &&
			false !== strpos( $preparation, 'SECRET_ROUND_NOTE' ), 'Leadership lost private preparation history.' );
		set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
		ob_start();
		try {
			foreach ( array(
				'invitation_saved' => array( array( 'revision' => 0, 'users' => array() ), array( 'revision' => 1, 'users' => array( $users['player'] ) ) ),
				'wiki_links_saved' => array( array( 'revision' => 0, 'links' => array() ), array( 'revision' => 1, 'links' => array( 'scene:123' => 456 ) ) ),
				'campaign_restored' => array( array( 'revision' => 0 ), array( 'revision' => 1, 'backup_id' => 'test-backup', 'source_hash' => str_repeat( 'a', 64 ) ) ),
			) as $kind => $states ) {
				ps_pen_paper_render_event_details( array( 'kind' => $kind, 'object_id' => $source_id, 'rules_revision' => 1,
					'before' => $states[0], 'after' => $states[1] ), $source );
			}
			$history = ob_get_contents();
			$check( false !== strpos( $history, 'scene:123' ) && false !== strpos( $history, 'test-backup' ) &&
				false !== strpos( $history, (string) $users['player'] ), 'Preparation audit details omitted non-character records.' );
		} finally { ob_end_clean(); restore_error_handler(); }
		$gm_base = $success( ps_pen_paper_save_character( $universe_id, 0, $users['gm'], array( 'name' => 'Playing GM',
			'values' => array( $field => array( 'current' => '10', 'maximum' => '20' ) ) ), '0', '1' ), 'GM player character failed' );
		$gm_alias = $success( ps_pen_paper_attach_base_character( $source_id, $gm_base, '1', '1', 'shared', 'confirmed' ), 'GM player attachment failed' );
		$_GET = array( 'pspp_campaign' => (string) $source_id, 'pspp_character' => (string) $gm_alias );
		$playing_gm = ps_pen_paper_frontend_shortcode();
		$chat_room = array( 'id' => ps_pen_paper_chat_id( $source_id ), 'blog_id' => get_current_blog_id(), 'session_type' => 'page' );
		$check( ! ps_pen_paper_chat_moderator( true, $chat_room ), 'Initial player chat render retained GM moderation.' );
		$check( false !== strpos( $playing_gm, 'Playing GM' ) &&
			false === strpos( $playing_gm, 'value="save_character_context"' ) &&
			false === strpos( $playing_gm, 'value="grant_item"' ) &&
			false === strpos( $playing_gm, 'value="change_wallet"' ), 'Own GM character exposed privileged inventory, currency or context actions in player view.' );
		$_GET = array( 'pspp_campaign' => (string) $source_id, 'pspp_mode' => 'solo' );
		$solo_entry = ps_pen_paper_frontend_shortcode();
		$check( false !== strpos( $solo_entry, 'value="begin_solo_play"' ) &&
			false !== strpos( $solo_entry, 'name="play_character"' ) &&
			false === strpos( $solo_entry, 'value="create_play_round"' ), 'Solo player entry still required technical lobby creation or omitted character selection.' );
		$_GET = array( 'pspp_campaign' => (string) $source_id, 'pspp_character' => (string) $gm_alias );
		$_GET['pspp_view'] = 'lead';
		$managing_gm = ps_pen_paper_frontend_shortcode();
		$check( ps_pen_paper_chat_moderator( false, $chat_room ), 'Initial leadership chat render lost GM moderation.' );
		$check( false !== strpos( $managing_gm, 'value="save_character_context"' ) &&
			false !== strpos( $managing_gm, 'value="grant_item"' ), 'Leadership lost its own character management actions.' );
		$saved_post = $_POST;
		try {
			$_GET = array(); $_POST['pspp_view'] = 'lead';
			$check( false !== strpos( ps_pen_paper_game_url( $frontend_page, $source_id ), 'pspp_view=lead' ),
				'POST actions lost the workspace on return links.' );
		} finally { $_POST = $saved_post; }
		wp_set_current_user( $users['other'] );
		wp_get_current_user()->add_cap( 'ps_pen_paper_create_campaigns' );
		$_GET = array( 'pspp_campaign' => (string) $source_id, 'pspp_view' => 'lead' );
		$check( ps_pen_paper_can_open_leadership() &&
			false !== strpos( ps_pen_paper_frontend_shortcode(), 'Für diesen Spielleitungsbereich hast du keine Berechtigung.' ),
			'Leadership access elsewhere granted management of a foreign campaign.' );
		wp_set_current_user( $users['gm'] );
		$_GET = array( 'pspp_campaign' => (string) $source_id, 'pspp_round' => (string) $a, 'pspp_character' => (string) $alias_id );
		$check( false !== strpos( ps_pen_paper_frontend_shortcode(), 'Fremde Charaktere öffnest du im Bereich Spielleitung.' ),
			'Player workspace opened a foreign character using GM privileges.' );
		$_GET['pspp_view'] = 'lead';
		$sheet = ps_pen_paper_frontend_shortcode();
		$check( false !== strpos( $sheet, 'value="save_character_context"' ) &&
			false !== strpos( $sheet, 'context_resources[' . $field . '][mode]' ), 'Selected runtime sheet did not expose resource overrides to its GM.' );
		wp_set_current_user( $users['player'] );
		$check( false !== strpos( ps_pen_paper_frontend_shortcode(), 'Für diesen Spielleitungsbereich hast du keine Berechtigung.' ),
			'Player accessed leadership through a crafted URL.' );
		unset( $_GET['pspp_view'] );
		$sheet = ps_pen_paper_frontend_shortcode();
		$check( false !== strpos( $sheet, 'name="character_id" value="' . $alias_id . '"' ) &&
			false === strpos( $sheet, 'value="save_character_context"' ), 'Selected runtime sheet was lost or exposed GM overrides to its owner.' );
	} finally {
		$GLOBALS['wp_query'] = $saved_query; $_GET = $saved_get;
		if ( isset( $smilies_priority ) && false !== $smilies_priority ) { add_filter( 'the_content', 'convert_smilies', $smilies_priority ); }
		wp_set_current_user( $users['player'] );
	}
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
	$export = $success( ps_pen_paper_export_campaign( $a, 'complete' ), 'Round backup failed' );
	$parsed = $success( ps_pen_paper_parse_import( json_encode( $export, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ), 'Round backup parse failed' );
	$options = array( 'operation' => 'copy', 'target_id' => 0, 'universe_mode' => 'copy', 'universe_id' => 0, 'users' => array(), 'bases' => array() );
	foreach ( $parsed['users'] as $user_id => $requirement ) { $options['users'][ $user_id ] = $user_id; }
	$map = array(); $next_id = 1000000;
	foreach ( array_keys( $parsed['records'] ) as $record_id ) { $map[ $record_id ] = $next_id++; }
	$prepared = $success( ps_pen_paper_prepare_import( $parsed, $options, $map, true ), 'Round backup preparation failed' );
	$keys = array_column( $prepared[ $a ]['meta'], 'key' );
	$check( ! in_array( '_pspp_play_round', $keys, true ) && ! in_array( '_pspp_play_token', $keys, true ) &&
		in_array( '_pspp_import_archive', $keys, true ), 'Imported runtime activated old campaign authority or start tokens.' );
	foreach ( $prepared[ $a ]['meta'] as $entry ) {
		if ( '_pspp_game_round' === $entry['key'] ) { $check( ! $entry['value']['active'], 'Import automatically resumed a prior session.' ); }
	}
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
		wp_set_current_user( $users['gm'] );
		set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
		ob_start();
		try {
			ps_pen_paper_render_journal( $round );
			$check( false !== strpos( ob_get_contents(), 'Round adventure' ), 'Automatic round history lost its adventure identity.' );
		} finally {
			ob_end_clean(); restore_error_handler(); wp_set_current_user( $users['player'] );
		}
		if ( 'group' === $mode ) {
			wp_set_current_user( $users['gm'] );
			$check( ! in_array( $id, array_column( ps_pen_paper_lounge_running_adventures( $source, true ), 'round_id' ), true ) &&
				in_array( $id, array_column( ps_pen_paper_lounge_running_adventures( $source ), 'round_id' ), true ),
				'Player lounge exposed an unjoined group through GM privileges or leadership lost it.' );
			$check( null === ps_pen_paper_adventure_group_records( $round, true )[ $state['run_id'] ]['state'] &&
				null !== ps_pen_paper_adventure_group_records( $round )[ $state['run_id'] ]['state'],
				'GM player projection exposed an unjoined group or leadership lost its view.' );
			wp_set_current_user( $users['player'] );
			$check( null !== ps_pen_paper_adventure_group_records( $round, true )[ $state['run_id'] ]['state'],
				'Player projection hid an enrolled group from its participant.' );
		}
		$before_base = ps_pen_paper_raw_character_state( $characters['player'] );
		if ( 'solo' === $mode ) {
			$run = get_post_meta( $state['run_id'], '_pspp_adventure_run', true ); $edge = $run['snapshot']['edges'][0]['id'];
			$next = null;
			foreach ( ps_pen_paper_adventure_records( $round, false ) as $candidate_id => $candidate ) {
				if ( $next_adventure === get_post_meta( $candidate_id, '_pspp_play_origin', true )['adventure_id'] ) { $next = array( 'id' => $candidate_id, 'snapshot' => $candidate['published'] ); }
			}
			$hero = ps_pen_paper_adventure_character( $round, $run['character_id'], true );
			$check( $next && ! ps_pen_paper_scenario_access( $round, $next['id'], $next['snapshot'], $hero, 'solo' )['met'], 'Prerequisite disappeared when the campaign was copied.' );
			$success( ps_pen_paper_advance_adventure_run( $round, $state['run_id'], $edge, '1' ), 'Solo decision failed' );
			$check( ps_pen_paper_scenario_access( $round, $next['id'], $next['snapshot'], $hero, 'solo' )['met'], 'Completed adventure did not unlock its copied successor.' );
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
			$check( 2 === $group['participants'][0]['scenario_attempt'] && 1 === $group['participants'][1]['scenario_attempt'],
				'A new round reset the prior player attempt count.' );
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
		$progress = get_post_meta( $state['run_id'], 'solo' === $mode ? '_pspp_adventure_run' : '_pspp_adventure_group', true );
		$decision = 'solo' === $mode ? $progress['history'][0] : $progress['history'][0]['decision'];
		$check( false === $decision['reaction']['success'] && 'Watcher waits' === $decision['reaction']['text'] &&
			$decision['reaction']['total'] >= 5 && $decision['reaction']['total'] <= 10,
			'Prepared NPC value, die or failure response was lost in solo/group execution.' );
	}
	$entry_revision = (string) ps_pen_paper_play_options( $source_id )['revision'];
	$base_revision = (string) ps_pen_paper_raw_character_state( $characters['player'] )['revision'];
	$fixture_posts = static function () use ( $users ) {
		return get_posts( array( 'post_type' => array_merge( array( 'pspp_campaign', 'pspp_universe' ), ps_pen_paper_campaign_record_types() ),
			'post_status' => array( 'private', 'draft' ), 'author__in' => array_values( $users ), 'fields' => 'ids',
			'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
	};
	$before_entry = $fixture_posts();
	$check( is_wp_error( ps_pen_paper_create_play_round( $source, 'solo', $adventure_id, wp_generate_uuid4(),
		$entry_revision, $characters['other'], '1' ) ) && $before_entry === $fixture_posts(), 'Direct solo entry accepted a foreign character or left a round behind.' );
	$check( is_wp_error( ps_pen_paper_create_play_round( $source, 'solo', $adventure_id, wp_generate_uuid4(),
		$entry_revision, $characters['player'], '999' ) ) && $before_entry === $fixture_posts(), 'Stale direct entry retained partial content.' );
	$reject_run = static function ( $data ) {
		if ( 'pspp_adventure_run' === $data['post_type'] && 'private' === $data['post_status'] ) { $data['post_status'] = 'draft'; }
		return $data;
	};
	add_filter( 'wp_insert_post_data', $reject_run );
	try {
		$check( is_wp_error( ps_pen_paper_create_play_round( $source, 'solo', $adventure_id, wp_generate_uuid4(),
			$entry_revision, $characters['player'], $base_revision ) ), 'Failed solo run publication reported success.' );
	} finally { remove_filter( 'wp_insert_post_data', $reject_run ); }
	$check( $before_entry === $fixture_posts(), 'Failed direct solo start retained cloned scenes, a lobby, a character or history.' );
	$entry_token = wp_generate_uuid4();
	$direct_id = $success( ps_pen_paper_create_play_round( $source, 'solo', $adventure_id, $entry_token,
		$entry_revision, $characters['player'], $base_revision ), 'Direct solo entry failed' ); $parents[] = $direct_id;
	$direct = ps_pen_paper_play_round( $direct_id );
	$check( 'active' === $direct['status'] && $direct['run_id'] > 0 && 1 === count( $direct['participants'] ) &&
		$direct['participants'][0]['source_id'] === $characters['player'] && ps_pen_paper_game_round( $direct_id )['active'],
		'Direct solo entry stopped in a lobby or omitted its character, run or active session.' );
	$before_retry = $fixture_posts();
	$check( $direct_id === ps_pen_paper_create_play_round( $source, 'solo', $adventure_id, $entry_token,
		$entry_revision, $characters['player'], $base_revision ) && $before_retry === $fixture_posts(), 'Repeated solo submit created duplicate content.' );
	$check( is_wp_error( ps_pen_paper_create_play_round( $source, 'solo', $adventure_id, wp_generate_uuid4(),
		$entry_revision, $characters['player'], $base_revision ) ) && $before_retry === $fixture_posts(), 'Direct solo entry started an already active character or retained partial content.' );
	$direct_round = ps_pen_paper_get_campaign( $direct_id );
	$direct_run = get_post_meta( $direct['run_id'], '_pspp_adventure_run', true );
	$before_reaction_character = ps_pen_paper_raw_character_state( $characters['player'] );
	$before_reaction_events = ps_pen_paper_get_events( $direct_round );
	$fail_reaction = static function ( $check, $id, $key, $value ) {
		return '_pspp_event' === $key && 'adventure_run' === ( $value['kind'] ?? '' ) ? false : $check;
	};
	add_filter( 'update_post_metadata', $fail_reaction, 10, 4 );
	try {
		$check( is_wp_error( ps_pen_paper_advance_adventure_run( $direct_round, $direct['run_id'], $direct_run['snapshot']['edges'][0]['id'], '1' ) ),
			'Failed reaction history write reported success.' );
	} finally { remove_filter( 'update_post_metadata', $fail_reaction, 10 ); }
	$check( $direct_run === get_post_meta( $direct['run_id'], '_pspp_adventure_run', true ) &&
		$before_reaction_character === ps_pen_paper_raw_character_state( $characters['player'] ) &&
		$before_reaction_events === ps_pen_paper_get_events( $direct_round ), 'NPC reaction failure retained costs, history or completion.' );
	$success( ps_pen_paper_advance_adventure_run( $direct_round, $direct['run_id'], $direct_run['snapshot']['edges'][0]['id'], '1' ), 'Direct solo action failed' );
	$check( 'completed' === ps_pen_paper_play_round( $direct_id )['status'] &&
		1 === count( get_post_meta( $direct['run_id'], '_pspp_adventure_run', true )['history'] ), 'Direct solo action did not persist and complete its prepared flow.' );
	$reaction_run = get_post_meta( $direct['run_id'], '_pspp_adventure_run', true );
	$check( ps_pen_paper_valid_adventure_run( $reaction_run ) &&
		false === strpos( wp_json_encode( $reaction_run['snapshot'] ), 'REACTION_PRIVATE_SECRET' ), 'Reaction exposed NPC secrets or invalidated direct solo history.' );
	$forged = $reaction_run; $forged['history'][0]['reaction']['total']++;
	$check( ! ps_pen_paper_valid_adventure_run( $forged ), 'Forged NPC total accepted.' );
	$forged = $reaction_run; unset( $forged['history'][0]['reaction'] );
	$check( ! ps_pen_paper_valid_adventure_run( $forged ), 'Required NPC reaction could be removed from history.' );
	$forged = $reaction_run; $forged['history'][0]['reaction']['roll']['groups'] = 'invalid';
	$check( ! ps_pen_paper_valid_adventure_run( $forged ), 'Malformed NPC dice groups accepted.' );
	$triggered = $direct_run['snapshot']['edges'][0]['reaction']; $triggered['when'] = 'failure';
	$entry = $reaction_run['history'][0];
	$check( null === ps_pen_paper_adventure_reaction_result( $triggered, $reaction_run['snapshot'], array(), $entry ),
		'Failure-only reaction ran after a successful/no-probe action.' );
	$fixed_reaction = $triggered; $fixed_reaction['when'] = 'decision'; $fixed_reaction['expression'] = ''; $fixed_reaction['threshold'] = 4;
	$fixed = ps_pen_paper_adventure_reaction_result( $fixed_reaction, $reaction_run['snapshot'], array(), $entry );
	$check( 4 === $fixed['total'] && true === $fixed['success'] && null === $fixed['roll'] &&
		ps_pen_paper_valid_adventure_reaction_result( $fixed_reaction, $fixed, $reaction_run['snapshot'], array(), $entry ),
		'Deterministic NPC value comparison at the exact threshold failed.' );
	$friend_revision = (string) ps_pen_paper_raw_character_state( $characters['player'] )['revision'];
	$before_friends = $fixture_posts();
	$check( is_wp_error( ps_pen_paper_create_play_round( $source, 'group', $adventure_id, wp_generate_uuid4(),
		$entry_revision, $characters['player'], $friend_revision, array( $users['other'] ) ) ) &&
		$before_friends === $fixture_posts(), 'A non-friend received a group invitation or left partial content.' );
	foreach ( array( 'other', 'outsider' ) as $friend ) {
		$friendships[ $friend ] = $success( wp_insert_post( array( 'post_type' => 'cpc_friendship', 'post_status' => 'publish',
			'post_author' => $users['player'], 'meta_input' => array( 'cpc_member1' => $users['player'], 'cpc_member2' => $users[ $friend ] ) ), true ), 'Friendship fixture failed' );
	}
	wp_update_post( array( 'ID' => $friendships['other'], 'post_status' => 'pending' ) );
	$check( ! ps_pen_paper_play_friend_allowed( $users['player'], $users['other'] ), 'Pending friendship was accepted.' );
	wp_update_post( array( 'ID' => $friendships['other'], 'post_status' => 'publish' ) );
	cpc_set_blocked_users( $users['other'], array( $users['player'] ) );
	$check( is_wp_error( ps_pen_paper_create_play_round( $source, 'group', $adventure_id, wp_generate_uuid4(),
		$entry_revision, $characters['player'], $friend_revision, array( $users['other'] ) ) ), 'Reverse blocking did not prevent an invitation.' );
	cpc_set_blocked_users( $users['other'], array() );
	$member_offers = ps_pen_paper_member_invitations( $source_id );
	$before_friends = $fixture_posts();
	$check( is_wp_error( ps_pen_paper_create_play_round( $source, 'group', $adventure_id, wp_generate_uuid4(),
		$entry_revision, $characters['player'], '999', array( $users['outsider'] ) ) ) &&
		$before_friends === $fixture_posts() && $member_offers === ps_pen_paper_member_invitations( $source_id ),
		'Failed friend-group creation retained a round or campaign invitation.' );
	$friend_token = wp_generate_uuid4();
	$friend_id = $success( ps_pen_paper_create_play_round( $source, 'group', $adventure_id, $friend_token,
		$entry_revision, $characters['player'], $friend_revision, array( $users['other'], $users['outsider'] ) ), 'Friend-group entry failed' );
	$parents[] = $friend_id; $friend_round = ps_pen_paper_get_campaign( $friend_id ); $friend_state = ps_pen_paper_play_round( $friend_id );
	$check( 'lobby' === $friend_state['status'] && 1 === count( $friend_state['participants'] ) &&
		$friend_id === ps_pen_paper_create_play_round( $source, 'group', $adventure_id, $friend_token,
			$entry_revision, $characters['player'], $friend_revision, array( $users['other'], $users['outsider'] ) ), 'Friend entry omitted its host or duplicated a submitted group.' );
	$check( is_wp_error( ps_pen_paper_change_play_round( $friend_round, 'start', (string) $friend_state['revision'] ) ), 'Start silently discarded unanswered invitations.' );
	$member_offers = ps_pen_paper_member_invitations( $source_id );
	$check( 'approval' === $member_offers['entries'][0]['status'] && ! ps_pen_paper_campaign_role( $source, $users['outsider'] ), 'Friendship bypassed campaign approval.' );
	wp_set_current_user( $users['outsider'] );
	$check( is_wp_error( ps_pen_paper_change_member_invitation( $source_id, $users['outsider'], 'accept', (string) $member_offers['revision'] ) ),
		'Unapproved campaign invitation was accepted.' );
	ob_start(); ps_pen_paper_render_friend_round_offers( ps_pen_paper_lounge_data(), $frontend_page ); $offers_html = ob_get_clean();
	$check( false !== strpos( $offers_html, 'Nimm zuerst die Kampagneneinladung an' ), 'Pending campaign access hid the friend invitation or offered a premature entry.' );
	$success( ps_pen_paper_change_play_round( $friend_round, 'decline_invite', (string) $friend_state['revision'] ), 'Declining a friend invitation required campaign membership' );
	wp_set_current_user( $users['gm'] );
	$success( ps_pen_paper_change_member_invitation( $source_id, $users['outsider'], 'approve', (string) $member_offers['revision'] ), 'Campaign invitation approval failed' );
	$member_offers = ps_pen_paper_member_invitations( $source_id );
	$characters['outsider'] = $success( ps_pen_paper_save_character( $universe_id, 0, $users['outsider'], array( 'name' => 'Uninvited participant',
		'values' => array( $field => array( 'current' => '10', 'maximum' => '20' ) ) ), '0', '1' ), 'Outsider character failed' );
	$foreign_world = $success( ps_pen_paper_save_universe( 0, array( 'name' => 'Different universe with identical rules', 'description' => '' ), '0' ), 'Foreign universe failed' );
	$parents[] = $foreign_world;
	update_post_meta( $foreign_world, '_pspp_rules', $rules );
	$foreign_character = $success( ps_pen_paper_save_character( $foreign_world, 0, $users['other'], array( 'name' => 'Wrong universe',
		'values' => array( $field => array( 'current' => '10', 'maximum' => '20' ) ) ), '0', '1' ), 'Foreign character failed' );
	$check( ! ps_pen_paper_can_open_friend_round( ps_pen_paper_play_round( $friend_id ) ), 'Uninvited GM gained a friend-group player projection.' );
	wp_set_current_user( $users['outsider'] );
	$success( ps_pen_paper_change_member_invitation( $source_id, $users['outsider'], 'accept', (string) $member_offers['revision'] ), 'Approved campaign access failed' );
	$friend_state = ps_pen_paper_play_round( $friend_id );
	$check( ! ps_pen_paper_can_open_friend_round( $friend_state ) &&
		is_wp_error( ps_pen_paper_change_play_round( $friend_round, 'join', (string) $friend_state['revision'], $characters['outsider'], '1' ) ),
		'Campaign membership bypassed a declined friend-group invitation.' );
	wp_set_current_user( $users['other'] );
	$choices = $success( ps_pen_paper_play_characters( $source, $friend_id ), 'Friend character choices failed' );
	$check( isset( $choices[ $characters['other'] ] ) && ! isset( $choices[ $foreign_character ] ) &&
		is_wp_error( ps_pen_paper_change_play_round( $friend_round, 'join', (string) $friend_state['revision'], $foreign_character, '1' ) ) &&
		is_wp_error( ps_pen_paper_change_play_round( $friend_round, 'join', (string) $friend_state['revision'], $characters['player'], $friend_revision ) ) &&
		is_wp_error( ps_pen_paper_change_play_round( $friend_round, 'join', (string) $friend_state['revision'], $characters['other'], '999' ) ),
		'An invitation allowed a foreign-universe, foreign-owned or stale character.' );
	cpc_set_blocked_users( $users['player'], array( $users['other'] ) );
	$check( is_wp_error( ps_pen_paper_change_play_round( $friend_round, 'join', (string) $friend_state['revision'], $characters['other'], '1' ) ) &&
		$friend_state === ps_pen_paper_play_round( $friend_id ), 'A newly blocked friendship was accepted or partially changed the round.' );
	cpc_set_blocked_users( $users['player'], array() );
	$success( ps_pen_paper_change_play_round( $friend_round, 'join', (string) $friend_state['revision'], $characters['other'], '1' ), 'Friend invitation acceptance with own universe character failed' );
	$friend_state = ps_pen_paper_play_round( $friend_id );
	$check( 'accepted' === $friend_state['invitations'][0]['status'] && 2 === count( $friend_state['participants'] ) &&
		'player' === ps_pen_paper_campaign_role( $friend_round, $users['other'] ), 'Accepting did not persist participation or granted GM access.' );
	wp_set_current_user( $users['player'] );
	$success( ps_pen_paper_change_play_round( $friend_round, 'start', (string) $friend_state['revision'] ), 'Shared friend-group start failed' );
	$friend_state = ps_pen_paper_play_round( $friend_id ); $friend_group = get_post_meta( $friend_state['run_id'], '_pspp_adventure_group', true );
	$success( ps_pen_paper_write_adventure_group( $friend_round, $friend_state['run_id'], (string) $friend_group['revision'], 'propose',
		array( 'character_id' => $friend_state['participants'][0]['character_id'], 'edge_id' => $friend_group['snapshot']['edges'][0]['id'] ) ), 'Prepared friend-group decision failed' );
	$friend_group = get_post_meta( $friend_state['run_id'], '_pspp_adventure_group', true );
	$check( 'completed' === ps_pen_paper_play_round( $friend_id )['status'] && 1 === count( $friend_group['history'] ) &&
		'Watcher waits' === $friend_group['history'][0]['decision']['reaction']['text'], 'Friend-group execution lost shared progress or its prepared NPC reaction.' );
	$revoke_id = $success( ps_pen_paper_create_play_round( $source, 'group', $adventure_id, wp_generate_uuid4(),
		$entry_revision, $characters['player'], (string) ps_pen_paper_raw_character_state( $characters['player'] )['revision'],
		array( $users['outsider'] ) ), 'Revocation group fixture failed' );
	$parents[] = $revoke_id; $revoke_round = ps_pen_paper_get_campaign( $revoke_id );
	$success( ps_pen_paper_change_play_round( $revoke_round, 'revoke_invite', (string) ps_pen_paper_play_round( $revoke_id )['revision'],
		$users['outsider'] ), 'Host could not revoke an unanswered invitation' );
	wp_set_current_user( $users['outsider'] );
	$check( ! ps_pen_paper_can_open_friend_round( ps_pen_paper_play_round( $revoke_id ) ) &&
		is_wp_error( ps_pen_paper_change_play_round( $revoke_round, 'join', (string) ps_pen_paper_play_round( $revoke_id )['revision'],
			$characters['outsider'], '1' ) ), 'Revoked invitation remained usable.' );
	wp_set_current_user( $users['player'] );
	$success( ps_pen_paper_change_play_round( $round_a, 'join', (string) ps_pen_paper_play_round( $a )['revision'],
		$characters['player'], (string) ps_pen_paper_raw_character_state( $characters['player'] )['revision'] ), 'Returning to paused round failed' );
	$success( ps_pen_paper_change_membership( $source_id, $users['player'], 'leave', '', (string) ps_pen_paper_membership_revision( $source_id )['revision'] ), 'Campaign departure failed' );
	$check( ! ps_pen_paper_play_participant( ps_pen_paper_play_round( $a ), $users['player'] ) &&
		! ps_pen_paper_can_view_campaign( $round_a ) && ps_pen_paper_record_post( $characters['player'] ), 'Campaign departure retained a live round participant or deleted the profile character.' );
	WP_CLI::success( 'Play round integration tests passed.' );
} finally {
	remove_filter( 'pre_option_cpc_friendships_all', $normal_friends );
	wp_set_current_user( $original_user );
	foreach ( $friendships as $id ) { wp_delete_post( $id, true ); }
	foreach ( $parents as $parent ) {
		$children = get_posts( array( 'post_type' => ps_pen_paper_campaign_record_types(), 'post_status' => array( 'private', 'draft', 'trash' ),
			'post_parent' => $parent, 'posts_per_page' => -1 ) );
		foreach ( $children as $child ) { wp_delete_post( $child->ID, true ); }
		wp_delete_post( $parent, true );
	}
	if ( isset( $frontend_page ) ) { wp_delete_post( $frontend_page, true ); }
	foreach ( $users as $id ) { if ( is_multisite() ) { wpmu_delete_user( $id ); } else { wp_delete_user( $id ); } }
}
