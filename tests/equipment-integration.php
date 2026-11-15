<?php
/**
 * Run with WP-CLI --skip-plugins --skip-themes against a development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'character', 'item', 'asset', 'roll', 'event' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
$original_user = get_current_user_id();
$original_get = $_GET;
$users = array();
$parents = array();
$path = '';
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
try {
	foreach ( array( 'gm', 'player', 'outsider' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-gear-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( ! is_wp_error( $id ), 'Fixture account failed.' );
		$users[ $role ] = $id;
	}
	wp_set_current_user( $users['gm'] );
	$id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Equipment fixture' ), true );
	$check( is_int( $id ) && $id > 0, 'Fixture campaign failed.' );
	$parents[] = $id;
	add_post_meta( $id, '_pspp_gm', (string) $users['gm'] );
	add_post_meta( $id, '_pspp_player', (string) $users['player'] );
	$campaign = ps_pen_paper_get_campaign( $id );
	$input = array( 'name' => 'Equipment rules', 'description' => '', 'dice' => array( '6' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Mut', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '10', 'start' => '5' ),
	) );
	$rules = ps_pen_paper_save_rules( $id, $input, '0' );
	$check( ! is_wp_error( $rules ), 'Fixture rules failed.' );
	$field = $rules['fields'][0]['id'];
	$base_input = array( 'name' => 'PRIVATE_HERO', 'values' => array( $field => '5' ) );
	$character_id = ps_pen_paper_save_character( $id, 0, $users['player'], $base_input, '0', '1' );
	$check( is_int( $character_id ), 'Fixture character failed.' );
	$character = get_post( $character_id );
	$raw = get_post_meta( $character_id, '_pspp_character', true );
	$path = wp_tempnam( 'pspp-gear-test.png' );
	$check( false !== file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=' ) ), 'Image fixture failed.' );
	$check( ! ps_pen_paper_community_available() && is_wp_error( ps_pen_paper_save_equipment_schema( $campaign, array(), '0' ) ), 'Inventory worked without Community.' );
	ob_start(); ps_pen_paper_render_table_sheet( $campaign, 0 ); $standalone_sheet = ob_get_clean();
	$check( false !== strpos( $standalone_sheet, 'Neuen Charakter erstellen' ), 'Removing duplicate profile creation also removed the standalone fallback.' );
	wp_set_current_user( $users['player'] );
	$check( true === ps_pen_paper_save_portrait( $campaign, $character_id, '0', 'upload', $path ), 'Core portrait requires Community.' );
	$check( '' === ps_pen_paper_portrait_image( $campaign, $character ), 'Pending portrait exposed as approved.' );
	wp_set_current_user( $users['outsider'] );
	$check( is_wp_error( ps_pen_paper_portrait_image( $campaign, $character ) ) && is_wp_error( ps_pen_paper_save_portrait( $campaign, $character_id, '1', 'remove' ) ), 'Portrait ownership failed.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_save_portrait( $campaign, $character_id, '1', 'approve' ) && 0 === strpos( ps_pen_paper_portrait_image( $campaign, $character ), 'data:image/png;base64,' ), 'Portrait approval failed.' );
	$check( true === ps_pen_paper_save_sheet_style( $campaign, '0', $path ), 'Sheet background failed.' );
	$frozen_id = wp_generate_uuid4();
	$frozen = array( 'revision' => 4, 'active' => array( 'items' => array( $frozen_id => array(
		'source_id' => 1, 'scope_id' => $id, 'revision' => 1, 'item' => array( 'name' => 'Frozen item', 'description' => '', 'kind' => 'hut', 'rarity' => 'normal', 'rarity_label' => 'Normal', 'color' => '#718096', 'asset_id' => 0, 'properties' => array(), 'bonuses' => array( $field => 2 ) ),
	) ), 'slots' => array( 'kopf' => $frozen_id ) ), 'pending' => null );
	update_post_meta( $character_id, '_pspp_gear', $frozen );
	$check( 5 === ps_pen_paper_character_state( $campaign, $character )['active']['values'][ $field ] && is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array(), '4' ) ) && $frozen === get_post_meta( $character_id, '_pspp_gear', true ), 'Community deactivation applied bonuses, allowed mutations or lost stored gear.' );
	delete_post_meta( $character_id, '_pspp_gear' );
	define( 'CPC_CORE_PLUGINS', 'core-profile,core-activity' );
	require_once dirname( __DIR__, 2 ) . '/ps-community/activity/cpc_profile_tabs.php';
	$check( ps_pen_paper_community_available(), 'Real profile API not recognized.' );
	$schema = array( 'slots' => "kopf | Kopf | hut\nhand | Hand | hut\nbrust | Brust | ruestung", 'rarities' => "normal | Normal | #718096\nepisch | Episch | #805ad5" );
	$check( true === ps_pen_paper_save_equipment_schema( $campaign, $schema, '0' ), 'Schema save failed.' );
	$bad_schema = $schema; $bad_schema['slots'] = 'kopf | Kopf | ruestung';
	$check( is_wp_error( ps_pen_paper_save_equipment_schema( $campaign, $bad_schema, '1' ) ), 'Existing slots removed/retyped.' );
	$item_input = array( 'name' => 'PRIVATE_HAT', 'description' => 'Warm', 'kind' => 'hut', 'rarity' => 'episch', 'binding' => 'tradeable', 'properties' => 'Gewicht | Leicht', 'bonuses' => array( $field => '2' ) );
	$item_id = ps_pen_paper_save_item( $campaign, 0, $item_input, '0', $path );
	$check( is_int( $item_id ), 'Item creation failed.' );
	$check( true === ps_pen_paper_save_gear( $campaign, $character_id, 'grant_item', array( 'item_id' => (string) $item_id, 'item_revision' => '1' ), '0' ), 'Issuance failed.' );
	$gear = ps_pen_paper_gear_state( $character_id );
	$instance = array_key_first( $gear['active']['items'] );
	$item_input['bonuses'][ $field ] = '3';
	$item_input['binding'] = 'bound';
	$check( $item_id === ps_pen_paper_save_item( $campaign, $item_id, $item_input, '1' ) && 2 === ps_pen_paper_gear_state( $character_id )['active']['items'][ $instance ]['item']['bonuses'][ $field ], 'Template edit mutated issued instance.' );
	$check( 'tradeable' === ps_pen_paper_gear_state( $character_id )['active']['items'][ $instance ]['item']['binding'] && 'bound' === ps_pen_paper_get_item( $campaign, $item_id )['item']['binding'], 'Template edit changed the issued binding.' );
	$bad_item = $item_input; $bad_item['binding'] = 'anything';
	$check( is_wp_error( ps_pen_paper_save_item( $campaign, 0, $bad_item, '0' ) ), 'Invalid binding accepted.' );
	wp_set_current_user( $users['player'] );
	$check( isset( cpc_get_profile_tabs( $users['player'] )['pspp'] ) && ! isset( cpc_get_profile_tabs( $users['outsider'] )['pspp'] ), 'Own-profile tab visibility failed.' );
	$check( false === strpos( cpc_render_profile_tab_content( $users['outsider'], 'pspp' ), 'PRIVATE_HERO' ), 'Forged profile tab exposed data.' );
	wp_set_current_user( $users['gm'] );
	foreach ( array( 'PROFILE_EMPTY_CAMPAIGN', 'PROFILE_FOREIGN_CHARACTERS' ) as $title ) {
		$profile_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => $title ), true );
		$check( is_int( $profile_id ) && $profile_id > 0, 'Profile campaign fixture failed.' );
		$parents[] = $profile_id;
		add_post_meta( $profile_id, '_pspp_player', (string) $users['player'] );
	}
	$foreign_rules = ps_pen_paper_save_rules( $profile_id, $input, '0' );
	$check( ! is_wp_error( $foreign_rules ), 'Foreign profile rules fixture failed.' );
	$foreign_values = array( 'name' => 'PROFILE_FOREIGN_HERO', 'values' => array( $foreign_rules['fields'][0]['id'] => '5' ) );
	$check( is_int( ps_pen_paper_save_character( $profile_id, 0, $users['gm'], $foreign_values, '0', '1' ) ), 'Foreign profile character fixture failed.' );
	wp_set_current_user( $users['player'] );
	$profile_html = cpc_render_profile_tab_content( $users['player'], 'pspp' );
	$check( 1 === preg_match( '/<form\b[^>]*class="pspp-character-picker"[^>]*>.*?<\/form>/s', $profile_html, $picker_match ), 'Own character picker is missing.' );
	$check( false !== strpos( $profile_html, 'PRIVATE_HERO' ) && false !== strpos( $profile_html, 'Kampagne: Equipment fixture' ) &&
		false === strpos( $picker_match[0], 'PROFILE_EMPTY_CAMPAIGN' ) && false === strpos( $picker_match[0], 'PROFILE_FOREIGN_CHARACTERS' ) &&
		false === strpos( $profile_html, 'PROFILE_FOREIGN_HERO' ), 'Own profile displayed empty/foreign campaign headings or lost character association.' );
	wp_set_current_user( $users['outsider'] );
	$profile_html = cpc_render_profile_tab_content( $users['outsider'], 'pspp' );
	$check( false !== strpos( $profile_html, 'Du hast noch keine eigenen Charaktere' ) &&
		false === strpos( $profile_html, 'PRIVATE_HERO' ), 'Empty own profile did not show an explicit empty state.' );
	wp_set_current_user( $users['player'] );
	foreach ( array( '[ps_pen_paper]', '[cpc_profile]' ) as $content ) {
		$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Navigation fixture', 'post_content' => $content ), true );
		$check( is_int( $page_id ) && $page_id > 0, 'Navigation page fixture failed.' );
		$parents[] = $page_id;
	}
	$game_page = ps_pen_paper_find_frontend_page( $page_id );
	$check( $game_page && $game_page->ID !== $page_id, 'Profile was selected as a game page.' );
	$_GET = array( 'pspp_character' => (string) $character_id );
	$selected_profile = cpc_render_profile_tab_content( $users['player'], 'pspp' );
	$check( false !== strpos( $selected_profile, 'PRIVATE_HERO' ) &&
		false !== strpos( $selected_profile, 'name="pspp_profile"' ) &&
		false !== strpos( $selected_profile, 'name="tab" value="pspp"' ) &&
		false === strpos( $selected_profile, 'Meine Spiele' ) &&
		false === strpos( $selected_profile, 'Zum Spieltisch' ) &&
		false === strpos( $selected_profile, 'Zur Spielrunde einladen' ) &&
		false === strpos( $selected_profile, 'Dieser Charakter gehört nicht' ), 'Profile failed to open the owned character or included game navigation/invitation administration.' );
	$_GET['pspp_campaign'] = (string) $profile_id;
	$mismatched_profile = cpc_render_profile_tab_content( $users['player'], 'pspp' );
	$check( false !== strpos( $mismatched_profile, 'Dieser Charakter gehört nicht' ) &&
		false === strpos( $mismatched_profile, 'name="pspp_profile"' ), 'Mismatched profile scope opened editable character forms.' );
	$_GET = $original_get;
	ob_start(); ps_pen_paper_render_character_adventures( $campaign, $character, $page_id ); $adventure_html = ob_get_clean();
	$check( false !== strpos( $adventure_html, esc_url( ps_pen_paper_game_url( $game_page->ID, $campaign->ID ) . '#pspp-group-adventures' ) ) &&
		false === strpos( $adventure_html, esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-group-adventures' ) ), 'Profile adventure link did not reach the actual game page.' );
	$check( is_wp_error( ps_pen_paper_item_catalogue( $campaign ) ) && is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'grant_item', array(), '1' ) ), 'Player accessed catalogue/issuance.' );
	$check( is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array( 'slots' => array( 'kopf' => wp_generate_uuid4() ) ), '1' ) ), 'Forged item equipped.' );
	$check( is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array( 'slots' => array( 'kopf' => $instance, 'hand' => $instance ) ), '1' ) ), 'Same instance equipped twice.' );
	$check( is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array( 'slots' => array( 'brust' => $instance ) ), '1' ) ), 'Incompatible slot accepted.' );
	$old_token = ps_pen_paper_character_token( $character, ps_pen_paper_character_state( $campaign, $character ) );
	$equip = array( 'slots' => array( 'kopf' => $instance ) );
	$check( true === ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', $equip, '1' ) && 5 === ps_pen_paper_character_state( $campaign, $character )['active']['values'][ $field ], 'Pending equip altered effective values.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_save_gear( $campaign, $character_id, 'review_gear', array( 'decision' => 'approve' ), '2' ), 'Equipment approval failed.' );
	$state = ps_pen_paper_character_state( $campaign, $character );
	$check( 7 === $state['active']['values'][ $field ] && $raw === get_post_meta( $character_id, '_pspp_character', true ), 'Effective bonuses changed base state or were not applied.' );
	wp_set_current_user( $users['player'] );
	ob_start(); ps_pen_paper_render_characters( $campaign, $character_id ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'value="equip_items"' ) && false !== strpos( $html, 'value="save_portrait"' ), 'Approved character without a pending base request has no equipment/portrait controls.' );
	wp_set_current_user( $users['gm'] );
	$roll_input = array( 'expression' => 'W6', 'visibility' => 'group', 'character' => $old_token, 'attribute' => $field, 'label' => 'Equipment proof' );
	$check( is_wp_error( ps_pen_paper_roll_dice( $id, $roll_input, '1' ) ), 'Stale equipment token accepted.' );
	$roll_input['character'] = ps_pen_paper_character_token( $character, $state );
	$roll_id = ps_pen_paper_roll_dice( $id, $roll_input, '1' );
	$check( is_int( $roll_id ), 'Equipped roll failed.' );
	$roll = ps_pen_paper_get_roll( $campaign, $roll_id );
	$check( 7 === $roll['attribute']['value'] && 2 === $roll['character']['equipment']['bonuses'][ $field ], 'Roll lost equipment provenance.' );
	$stored_roll = get_post_meta( $roll_id, '_pspp_roll', true );
	$damaged_roll = $stored_roll; $damaged_roll['character']['equipment'] = null;
	update_post_meta( $roll_id, '_pspp_roll', $damaged_roll );
	$check( is_wp_error( ps_pen_paper_get_roll( $campaign, $roll_id ) ), 'Malformed optional equipment provenance accepted.' );
	update_post_meta( $roll_id, '_pspp_roll', $stored_roll );
	$bad_base = $base_input; $bad_base['values'][ $field ] = '9';
	$check( is_wp_error( ps_pen_paper_save_character( $id, $character_id, $users['player'], $bad_base, '1', '1' ) ), 'Base edit stranded equipped character.' );
	$changed = $raw; $changed['active']['values'][ $field ] = 9;
	update_post_meta( $character_id, '_pspp_character', $changed );
	$check( is_wp_error( ps_pen_paper_character_state( $campaign, $character ) ), 'Invalid combined values silently accepted.' );
	wp_set_current_user( $users['player'] );
	$check( true === ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array(), '3' ), 'Owner could not request recovery from invalid active equipment.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_save_gear( $campaign, $character_id, 'review_gear', array( 'decision' => 'reject' ), '4' ), 'Invalid active state blocked rejection.' );
	$check( true === ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array(), '5' ) && 9 === ps_pen_paper_character_state( $campaign, $character )['active']['values'][ $field ], 'GM could not unequip invalid active gear.' );
	$check( 7 === ps_pen_paper_get_roll( $campaign, $roll_id )['attribute']['value'], 'Old roll changed after unequipping.' );
	update_post_meta( $character_id, '_pspp_character', $raw );
	$failure = function ( $data ) { if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) { $data['post_status'] = 'draft'; } return $data; };
	add_filter( 'wp_insert_post_data', $failure );
	try {
		$check( is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', $equip, '6' ) ) && 6 === ps_pen_paper_gear_state( $character_id )['revision'], 'Journal failure did not roll back equipment.' );
		$assets_before = get_posts( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $id, 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$check( is_wp_error( ps_pen_paper_save_portrait( $campaign, $character_id, '2', 'upload', $path ) ) && 2 === ps_pen_paper_portrait_state( $character_id )['revision'], 'Portrait journal rollback failed.' );
		$check( $assets_before === get_posts( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $id, 'posts_per_page' => -1, 'fields' => 'ids' ) ), 'Failed portrait left orphan image.' );
	} finally { remove_filter( 'wp_insert_post_data', $failure ); }
	$check( ! is_wp_error( ps_pen_paper_get_events( $campaign ) ), 'New event kinds broke journal.' );
	ob_start();
	foreach ( ps_pen_paper_get_events( $campaign )['events'] as $event ) { if ( 'roll' !== $event['kind'] ) { ps_pen_paper_render_event_details( $event ); } }
	$html = ob_get_clean();
	$check( false !== strpos( $html, 'PRIVATE_HAT' ), 'Equipment history not rendered.' );
	$direct_rules = $rules; $direct_rules['character_mode'] = 'direct';
	update_post_meta( $campaign->ID, '_pspp_rules', $direct_rules );
	wp_set_current_user( $users['player'] );
	$check( true === ps_pen_paper_save_portrait( $campaign, $character_id, '2', 'remove' ) && 0 === ps_pen_paper_portrait_state( $character_id )['active']['asset_id'], 'Direct portrait removal failed.' );
	$check( true === ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', $equip, '6' ) && 7 === ps_pen_paper_character_state( $campaign, $character )['active']['values'][ $field ], 'Direct equipment policy failed.' );
	$direct_rules['character_mode'] = 'gm_only';
	update_post_meta( $campaign->ID, '_pspp_rules', $direct_rules );
	$check( is_wp_error( ps_pen_paper_save_portrait( $campaign, $character_id, '3', 'upload', $path ) ) && is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array(), '7' ) ), 'GM-only policy allowed owner changes.' );
	update_post_meta( $campaign->ID, '_pspp_rules', $rules );
	wp_set_current_user( $users['outsider'] );
	$check( is_wp_error( ps_pen_paper_save_gear( $campaign, $character_id, 'equip_items', array(), '6' ) ), 'Nonmember changed equipment.' );
	wp_set_current_user( $users['gm'] );
	wp_get_current_user()->add_cap( 'ps_pen_paper_create_campaigns' );
	$universe_id = ps_pen_paper_save_universe( 0, array( 'name' => 'Private item universe', 'description' => '' ), '0' );
	$check( is_int( $universe_id ), 'Universe fixture failed.' );
	$parents[] = $universe_id;
	$universe = ps_pen_paper_get_universe( $universe_id );
	$world_rules = ps_pen_paper_save_rules( $universe_id, $input, '0' );
	$world_field = $world_rules['fields'][0]['id'];
	$world_input = array( 'name' => 'Shared equipment hero', 'values' => array( $world_field => '5' ) );
	$base_id = ps_pen_paper_save_character( $universe_id, 0, $users['player'], $world_input, '0', '1' );
	ob_start();
	ps_pen_paper_render_character_form( $universe, $world_rules, null, 0, null, true );
	$gm_profile_form = ob_get_clean();
	$check( false !== strpos( $gm_profile_form, 'Charakter speichern' ) &&
		false === strpos( $gm_profile_form, 'Zur Freigabe einreichen' ) &&
		false === strpos( $gm_profile_form, '<select id="pspp-character-owner"' ), 'Own-profile GM creation misstated approval or offered a different owner.' );
	$check( false === strpos( $gm_profile_form, 'name="character[values][' . $world_rules['fields'][0]['id'] . ']" type="number"' ),
		'GM profile creation exposed arbitrary numeric start values.' );
	$forged_profile = $world_input; $forged_profile['values'][ $world_rules['fields'][0]['id'] ] = '6';
	$check( is_wp_error( ps_pen_paper_create_own_universe_character( $universe_id, $forged_profile, '1' ) ),
		'GM profile creation accepted an invented numeric start value.' );
	ob_start(); ps_pen_paper_render_campaign_create_form(); $campaign_form = ob_get_clean();
	$check( false !== strpos( $campaign_form, '>Private item universe</option>' ) &&
		false === strpos( $campaign_form, '>PS Pen &amp; Paper Universe</option>' ), 'Campaign creation offered technical universe titles instead of the configured world names.' );
	$_GET = array( 'pspp_character' => (string) $base_id );
	$gm_base_id = ps_pen_paper_save_character( $universe_id, 0, $users['gm'], $world_input, '0', '1' );
	$check( is_int( $gm_base_id ), 'Own GM base fixture failed.' );
	$_GET['pspp_character'] = (string) $gm_base_id;
	$base_profile = cpc_render_profile_tab_content( $users['gm'], 'pspp' );
	$check( false !== strpos( $base_profile, 'Universum: Private item universe' ) &&
		false === strpos( $base_profile, 'Universum: PS Pen &amp; Paper Universe' ) &&
		false === strpos( $base_profile, 'Website-Mitglied einladen' ) &&
		false === strpos( $base_profile, 'Zum Universum einladen' ), 'Selected universe character lost its world name or included membership administration.' );
	$_GET = array();
	$default_profile = cpc_render_profile_tab_content( $users['gm'], 'pspp' );
	$check( false !== strpos( $default_profile, 'Dein Charakter' ) && false !== strpos( $default_profile, 'Inventar' ) &&
		false !== strpos( $default_profile, 'Werte' ), 'Own profile did not open an existing character with stats and inventory by default.' );
	$linked_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['outsider'], 'post_title' => 'Linked equipment fixture' ), true );
	$check( is_int( $base_id ) && is_int( $linked_id ), 'Linked fixture failed.' );
	$parents[] = $linked_id;
	add_post_meta( $linked_id, '_pspp_gm', (string) $users['gm'] );
	add_post_meta( $linked_id, '_pspp_gm', (string) $users['outsider'] );
	add_post_meta( $linked_id, '_pspp_player', (string) $users['player'] );
	$linked = ps_pen_paper_get_campaign( $linked_id );
	$check( ! is_wp_error( ps_pen_paper_save_universe_link( $linked_id, $universe_id, '0', '1', 'confirmed' ) ) && ! is_wp_error( ps_pen_paper_adopt_universe_rules( $linked_id, '1', 'confirmed' ) ), 'Universe link/adoption failed.' );
	$alias_id = ps_pen_paper_attach_base_character( $linked_id, $base_id, '1', '1', 'shared', 'confirmed' );
	$check( is_int( $alias_id ) && true === ps_pen_paper_save_equipment_schema( $universe, $schema, '0' ) && true === ps_pen_paper_save_equipment_schema( $linked, $schema, '0' ), 'Shared alias/schema failed.' );
	ob_start(); ps_pen_paper_render_table_sheet( $linked, 0 ); $linked_sheet = ob_get_clean();
	$check( false === strpos( $linked_sheet, 'Neuen Charakter erstellen' ) &&
		false !== strpos( $linked_sheet, 'Charakter im Profil erstellen' ), 'Linked Community game retained a duplicate independent character creation form.' );
	$item_input['bonuses'] = array( $world_field => '2' );
	$world_item = ps_pen_paper_save_item( $universe, 0, $item_input, '0', $path );
	$check( is_int( $world_item ), 'Universe item failed.' );
	wp_set_current_user( $users['outsider'] );
	$check( ! ps_pen_paper_can_manage_universe( $universe ) && isset( ps_pen_paper_item_catalogue( $linked )[ $world_item ] ), 'Linked campaign catalogue requires unrelated universe administration.' );
	$check( true === ps_pen_paper_save_gear( $linked, $alias_id, 'grant_item', array( 'item_id' => (string) $world_item, 'item_revision' => '1' ), '0' ), 'Universe issuance failed.' );
	$world_instance = array_key_first( ps_pen_paper_gear_state( $alias_id )['active']['items'] );
	$check( true === ps_pen_paper_save_gear( $linked, $alias_id, 'equip_items', array( 'slots' => array( 'kopf' => $world_instance ) ), '1' ), 'Shared campaign loadout failed.' );
	$check( is_wp_error( ps_pen_paper_save_character_context( $linked_id, $alias_id, '1', '1', '1', array( $world_field => '9' ), '' ) ), 'Context edit bypassed equipment bounds.' );
	$check( 7 === ps_pen_paper_character_state( $linked, get_post( $alias_id ) )['active']['values'][ $world_field ], 'Shared equipment was not applied.' );
	ob_start(); ps_pen_paper_render_character_equipment( $linked, get_post( $alias_id ), 0 ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'data:image/png;base64,' ), 'Authorized universe item image unavailable in owned inventory.' );
	wp_set_current_user( $users['gm'] );
	$world_input['values'][ $world_field ] = '9';
	$check( $base_id === ps_pen_paper_save_character( $universe_id, $base_id, $users['player'], $world_input, '1', '1' ), 'Shared base progress failed.' );
	$check( is_wp_error( ps_pen_paper_character_state( $linked, get_post( $alias_id ) ) ), 'Invalid dependent equipment silently dropped or exceeded bounds.' );
	$check( true === ps_pen_paper_save_gear( $linked, $alias_id, 'equip_items', array(), '2' ) && 9 === ps_pen_paper_character_state( $linked, get_post( $alias_id ) )['active']['values'][ $world_field ], 'Shared-base progress stranded loadout repair.' );

	$race_id = ps_pen_paper_save_character( $campaign->ID, 0, $users['player'], $base_input, '0', '1' );
	$race_item_input = $item_input; $race_item_input['bonuses'] = array( $field => '2' );
	$race_item = ps_pen_paper_save_item( $campaign, 0, $race_item_input, '0' );
	$check( is_int( $race_id ) && is_int( $race_item ), 'Concurrency fixtures failed.' );
	$check( true === ps_pen_paper_save_gear( $campaign, $race_id, 'grant_item', array( 'item_id' => (string) $race_item, 'item_revision' => '1' ), '0' ), 'Concurrency issuance failed.' );
	$race_instance = array_key_first( ps_pen_paper_gear_state( $race_id )['active']['items'] );
	$race_equip = array( 'slots' => array( 'kopf' => $race_instance ) );
	$stale_meta = get_post_meta( $race_id );
	$nested_result = null;
	$interleave = function ( $check, $object_id, $key ) use ( $campaign, $race_id, $users, $bad_base, &$nested_result ) {
		if ( $object_id === $race_id && '_pspp_gear' === $key ) {
			$nested_result = ps_pen_paper_save_character( $campaign->ID, $race_id, $users['player'], $bad_base, '1', '1' );
		}
		return $check;
	};
	add_filter( 'update_post_metadata', $interleave, 10, 3 );
	try {
		$race_result = ps_pen_paper_save_gear( $campaign, $race_id, 'equip_items', $race_equip, '1' );
	} finally { remove_filter( 'update_post_metadata', $interleave, 10 ); }
	$check( true === $race_result && is_wp_error( $nested_result ) && 'conflict' === $nested_result->get_error_code(), 'Character edit during equipment storage was not rejected as a conflict.' );
	$check( 7 === ps_pen_paper_character_state( $campaign, get_post( $race_id ) )['active']['values'][ $field ], 'Interleaved equipment/character edit exceeded the field maximum of 10.' );
	wp_cache_set( $race_id, $stale_meta, 'post_meta' );
	$stale_result = ps_pen_paper_save_character( $campaign->ID, $race_id, $users['player'], $bad_base, '1', '1' );
	$check( is_wp_error( $stale_result ) && 'equipment_range' === $stale_result->get_error_code() && 7 === ps_pen_paper_character_state( $campaign, get_post( $race_id ) )['active']['values'][ $field ], 'Cached pre-lock equipment allowed an invalid base edit.' );
	$check( true === ps_pen_paper_save_gear( $campaign, $race_id, 'equip_items', array(), '2' ), 'Concurrency unequip failed.' );
	$interleave = function ( $check, $object_id, $key ) use ( $campaign, $race_id, $race_equip, &$nested_result ) {
		if ( $object_id === $race_id && '_pspp_character' === $key ) {
			$nested_result = ps_pen_paper_save_gear( $campaign, $race_id, 'equip_items', $race_equip, '3' );
		}
		return $check;
	};
	add_filter( 'update_post_metadata', $interleave, 10, 3 );
	try {
		$race_result = ps_pen_paper_save_character( $campaign->ID, $race_id, $users['player'], $bad_base, '1', '1' );
	} finally { remove_filter( 'update_post_metadata', $interleave, 10 ); }
	$check( $race_id === $race_result && is_wp_error( $nested_result ) && 'conflict' === $nested_result->get_error_code() && 9 === ps_pen_paper_character_state( $campaign, get_post( $race_id ) )['active']['values'][ $field ], 'Equipment edit during character storage was not rejected safely.' );

	wp_set_current_user( $users['player'] );
	$check( $race_id === ps_pen_paper_save_character( $campaign->ID, $race_id, $users['player'], $base_input, '2', '1' ), 'Concurrent approval fixture failed.' );
	wp_set_current_user( $users['gm'] );
	$before_race_meta = get_post_meta( $race_id );
	$before_race_events = ps_pen_paper_get_events( $campaign );
	$blocker = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$lock_name = ps_pen_paper_character_lock_name( $race_id );
	try {
		$check( '1' === $blocker->get_var( $blocker->prepare( 'SELECT GET_LOCK(%s, 0)', $lock_name ) ), 'Independent database lock failed.' );
		foreach ( array(
			function () use ( $campaign, $race_id, $users, $base_input ) { return ps_pen_paper_save_character( $campaign->ID, $race_id, $users['player'], $base_input, '3', '1' ); },
			function () use ( $campaign, $race_id ) { return ps_pen_paper_review_character( $campaign->ID, $race_id, 'approve', '3' ); },
			function () use ( $campaign, $race_id ) { return ps_pen_paper_save_gear( $campaign, $race_id, 'equip_items', array(), '3' ); },
		) as $operation ) {
			$result = $operation();
			$check( is_wp_error( $result ) && 'conflict' === $result->get_error_code(), 'A competing database connection did not block a character mutation.' );
		}
		$check( $before_race_meta === get_post_meta( $race_id ) && $before_race_events === ps_pen_paper_get_events( $campaign ), 'Rejected competing mutations changed character data or journal entries.' );
	} finally {
		$released = $blocker->get_var( $blocker->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		$blocker->close();
		$check( '1' === $released, 'Independent database lock cleanup failed.' );
	}
	$fail_lock = function ( $query ) { return 0 === strpos( $query, 'SELECT GET_LOCK(' ) ? 'SELECT NULL' : $query; };
	add_filter( 'query', $fail_lock );
	try {
		$result = ps_pen_paper_save_character( $campaign->ID, $race_id, $users['player'], $base_input, '3', '1' );
		$check( is_wp_error( $result ) && 'character_lock_failed' === $result->get_error_code() && $before_race_meta === get_post_meta( $race_id ) && $before_race_events === ps_pen_paper_get_events( $campaign ), 'Unavailable database locks allowed an unprotected mutation.' );
	} finally { remove_filter( 'query', $fail_lock ); }
	$check( true === ps_pen_paper_review_character( $campaign->ID, $race_id, 'approve', '3' ) && 5 === ps_pen_paper_character_state( $campaign, get_post( $race_id ) )['active']['values'][ $field ], 'Character approval stayed blocked after lock release.' );
	$check( true === ps_pen_paper_save_character_context( $linked_id, $alias_id, '1', '2', '1', array( $world_field => '5' ), '' ), 'Context concurrency fixture failed.' );
	$nested_result = null;
	$interleave = function ( $check, $object_id, $key ) use ( $linked_id, $alias_id, $world_field, &$nested_result ) {
		if ( $object_id === $alias_id && '_pspp_gear' === $key ) {
			$nested_result = ps_pen_paper_save_character_context( $linked_id, $alias_id, '2', '2', '1', array( $world_field => '9' ), '' );
		}
		return $check;
	};
	add_filter( 'update_post_metadata', $interleave, 10, 3 );
	try {
		$race_result = ps_pen_paper_save_gear( $linked, $alias_id, 'equip_items', array( 'slots' => array( 'kopf' => $world_instance ) ), '3' );
	} finally { remove_filter( 'update_post_metadata', $interleave, 10 ); }
	$check( true === $race_result && is_wp_error( $nested_result ) && 'conflict' === $nested_result->get_error_code() && 7 === ps_pen_paper_character_state( $linked, get_post( $alias_id ) )['active']['values'][ $world_field ], 'Context edit bypassed the equipment consistency lock.' );
	WP_CLI::success( 'Community gating, real profile API ownership, core portrait approvals/privacy, private images, slots/rarity, frozen issuance, forged/duplicate/type guards, effective bonuses, stale dice tokens, immutable roll provenance, bounds, recovery, journal rollback, image cleanup and cross-document character/equipment conflicts passed.' );
} finally {
	wp_set_current_user( $original_user );
	$_GET = $original_get;
	foreach ( $parents as $parent ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_item', 'pspp_asset', 'pspp_roll', 'pspp_event' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $parent, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
		wp_delete_post( $parent, true );
	}
	if ( $path && is_file( $path ) ) { unlink( $path ); }
	foreach ( $users as $id ) { is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ); }
}
