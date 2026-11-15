<?php
/** Run with WP-CLI against a disposable development site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'universe', 'campaign', 'npc', 'event', 'wiki', 'scene' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
$original_user = get_current_user_id(); $users = array(); $parents = array(); $native_ids = array(); $term_ids = array();
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
try {
	foreach ( array( 'gm', 'other', 'admin' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-wiki-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'admin' === $role ? 'administrator' : 'subscriber' ) );
		$check( is_int( $id ), 'Fixture account failed.' ); $users[ $role ] = $id;
	}
	get_userdata( $users['gm'] )->add_cap( 'ps_pen_paper_create_campaigns' ); wp_set_current_user( $users['gm'] );
	$u = ps_pen_paper_save_universe( 0, array( 'name' => 'PRIVATE_WORLD', 'description' => 'PRIVATE_WORLD_SECRET' ), '0' );
	$check( is_int( $u ), 'Universe failed.' ); $parents[] = $u; $universe = ps_pen_paper_get_universe( $u );
	$config_input = array( 'name' => 'Public test world ' . wp_generate_uuid4(), 'categories' => ps_pen_paper_wiki_categories(), 'enabled' => '1' );
	$check( ! ps_pen_paper_wiki_available() && is_wp_error( ps_pen_paper_configure_wiki( $universe, $config_input, '0', 'confirmed' ) ), 'Missing integration gate failed.' );
	require_once dirname( __DIR__, 2 ) . '/ps-wiki/wiki.php';
	Wiki::get_instance()->settings = get_option( 'wiki_settings' );
	Wiki_Features::get_instance()->register_taxonomies(); Wiki::get_instance()->register_post_types();
	$check( ps_pen_paper_wiki_available(), 'Real native API unavailable.' );
	$check( is_wp_error( ps_pen_paper_configure_wiki( $universe, $config_input, '0', 'confirmed' ) ), 'Universe GM bypassed native Wiki activation rights.' );
	wp_set_current_user( $users['admin'] );
	$shortcode_ran = false;
	add_shortcode( 'pspp_wiki_probe', function () use ( &$shortcode_ran ) { $shortcode_ran = true; return 'EXECUTED'; } );
	do_shortcode( ps_pen_paper_wiki_public_html( '[pspp_wiki_probe]' ) );
	remove_shortcode( 'pspp_wiki_probe' );
	$check( ! $shortcode_ran, 'Plain article text executed a shortcode.' );
	$fail_kind = '';
	$fail = function ( $data, $postarr ) use ( &$fail_kind ) {
		if ( $fail_kind && isset( $postarr['ID'] ) && 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) {
			$event = get_post_meta( $postarr['ID'], '_pspp_event', true );
			if ( isset( $event['kind'] ) && $event['kind'] === $fail_kind ) { $data['post_status'] = 'draft'; }
		}
		return $data;
	};
	add_filter( 'wp_insert_post_data', $fail, 10, 2 );
	global $wpdb;
	$term_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->terms" );
	$fail_kind = 'wiki_configured';
	$check( is_wp_error( ps_pen_paper_configure_wiki( $universe, $config_input, '0', 'confirmed' ) ) &&
		$term_count === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->terms" ) && ps_pen_paper_wiki_default() === ps_pen_paper_wiki_config( $universe ), 'Unlogged public categories survived rollback.' );
	$fail_kind = '';
	$check( true === ps_pen_paper_configure_wiki( $universe, $config_input, '0', 'confirmed' ), 'Lexicon setup failed.' );
	$config = ps_pen_paper_wiki_config( $universe );
	$term_ids = array_merge( array( $config['root_id'] ), array_column( $config['categories'], 'id' ) );
	$check( 7 === count( array_unique( $term_ids ) ) && ps_pen_paper_wiki_config_terms_valid( $config ), 'Scoped category tree failed.' );
	$check( is_wp_error( ps_pen_paper_configure_wiki( $universe, $config_input, '0', 'confirmed' ) ), 'Stale setup accepted.' );
	wp_set_current_user( $users['gm'] );
	$npc_input = array( 'kind' => 'npc', 'name' => 'PRIVATE_IDENTITY', 'description' => 'PRIVATE_DESCRIPTION', 'secret' => 'PRIVATE_SECRET',
		'properties' => 'PRIVATE_PROPERTY | PRIVATE_VALUE', 'values' => 'PRIVATE_STAT | 7', 'scene_id' => '0' );
	$npc = ps_pen_paper_save_npc( $universe, 0, $npc_input, '0' ); $check( is_int( $npc ), 'Template failed.' );
	$input = array( 'source_id' => (string) $npc, 'source_revision' => '1', 'title' => 'Public pilot', 'content' => 'Only this chosen public description.', 'category' => 'people' );
	$check( is_wp_error( ps_pen_paper_save_wiki_entry( $universe, 0, $input, '0', '1', '' ) ), 'Unconfirmed draft accepted.' );
	$before_native = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s", 'psource_wiki' ) );
	$fail_kind = 'wiki_prepared';
	$check( is_wp_error( ps_pen_paper_save_wiki_entry( $universe, 0, $input, '0', '1', 'confirmed' ) ) &&
		$before_native === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s", 'psource_wiki' ) ) &&
		array() === ps_pen_paper_wiki_entries( $universe ), 'Failed preparation left native article or mapping.' );
	$fail_kind = '';
	$entry = ps_pen_paper_save_wiki_entry( $universe, 0, $input, '0', '1', 'confirmed' ); $check( is_int( $entry ), 'Draft preparation failed.' );
	$state = ps_pen_paper_get_wiki_entry( $universe, $entry ); $wiki_id = $state['wiki_id']; $native_ids[] = $wiki_id;
	$check( 'draft' === get_post_status( $wiki_id ) && false === strpos( serialize( get_post( $wiki_id ) ), 'PRIVATE_' ), 'Draft published or private fields copied.' );
	$check( is_wp_error( ps_pen_paper_save_wiki_entry( $universe, 0, $input, '0', '1', 'confirmed' ) ), 'Duplicate template mapping accepted.' );
	$fail_kind = 'wiki_published';
	$check( is_wp_error( ps_pen_paper_save_wiki_entry( $universe, $entry, $input, '1', '1', 'confirmed', true ) ) &&
		'draft' === get_post_status( $wiki_id ) && $state === ps_pen_paper_get_wiki_entry( $universe, $entry ), 'Unlogged publication survived rollback.' );
	$fail_kind = '';
	$check( $entry === ps_pen_paper_save_wiki_entry( $universe, $entry, $input, '1', '1', 'confirmed', true ) &&
		'publish' === get_post_status( $wiki_id ), 'Explicit publication failed.' );
	$check( is_wp_error( ps_pen_paper_save_wiki_entry( $universe, $entry, $input, '1', '1', 'confirmed', true ) ), 'Publication replay accepted.' );
	$c = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['gm'], 'post_title' => 'Wiki link fixture' ), true );
	$check( is_int( $c ), 'Campaign fixture failed.' ); $parents[] = $c; add_post_meta( $c, '_pspp_player', (string) $users['other'] );
	$campaign = ps_pen_paper_get_campaign( $c );
	$check( ! is_wp_error( ps_pen_paper_save_universe_link( $c, $u, '0', '1', 'confirmed' ) ), 'Universe link failed.' );
	$rules = ps_pen_paper_save_rules( $c, array( 'name' => 'Wiki links', 'description' => '', 'dice' => array( '6' ),
		'fields' => array( array( 'id' => '', 'label' => 'Lore', 'type' => 'text', 'description' => '' ) ) ), '0' );
	$scene = ps_pen_paper_save_scene( $campaign, 0, array( 'title' => 'PRIVATE_LINK_SCENE', 'text' => '', 'secret' => '', 'grid' => '0' ), '0' );
	$check( ! is_wp_error( $rules ) && is_int( $scene ), 'Wiki target fixtures failed.' );
	$field = 'field:' . $rules['fields'][0]['id']; $scene_target = 'scene:' . $scene;
	$check( true === ps_pen_paper_save_wiki_link( $campaign, $field, $entry, '0', '1' ) &&
		true === ps_pen_paper_save_wiki_link( $campaign, $scene_target, $entry, '1', '1' ) &&
		is_wp_error( ps_pen_paper_save_wiki_link( $campaign, $field, $entry, '1', '1' ) ), 'Wiki linking or revision conflict failed.' );
	wp_set_current_user( $users['other'] );
	$check( 'Public pilot' === ps_pen_paper_wiki_link_for_target( $campaign, $field )['title'] &&
		null === ps_pen_paper_wiki_link_for_target( $campaign, $scene_target ) && is_wp_error( ps_pen_paper_wiki_links( $campaign ) ), 'Private scene link or management leaked.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_publish_scene( $campaign, $scene, '1', 'confirmed' ) &&
		true === ps_pen_paper_select_scene( $campaign, $scene, '0' ), 'Scene release failed.' );
	wp_set_current_user( $users['other'] );
	$check( 'Public pilot' === ps_pen_paper_wiki_link_for_target( $campaign, $scene_target )['title'], 'Released active scene link unavailable.' );
	wp_set_current_user( $users['gm'] );
	$public = get_post( $wiki_id )->post_content;
	$input['content'] = 'Prepared second public description.';
	$check( $entry === ps_pen_paper_save_wiki_entry( $universe, $entry, $input, '2', '1', 'confirmed' ) &&
		$public === get_post( $wiki_id )->post_content && 'publish' === get_post_status( $wiki_id ), 'Preparing update changed public article.' );
	$check( $entry === ps_pen_paper_save_wiki_entry( $universe, $entry, $input, '3', '1', 'confirmed', true ) &&
		ps_pen_paper_wiki_public_html( $input['content'] ) === get_post( $wiki_id )->post_content, 'Prepared update publication failed.' );
	$npc_input['name'] = 'PRIVATE_RENAMED';
	$check( $npc === ps_pen_paper_save_npc( $universe, $npc, $npc_input, '1' ), 'Template rename failed.' );
	$input['source_revision'] = '2'; $input['title'] = 'Renamed public pilot';
	$check( $entry === ps_pen_paper_save_wiki_entry( $universe, $entry, $input, '4', '1', 'confirmed' ) &&
		$wiki_id === ps_pen_paper_get_wiki_entry( $universe, $entry )['wiki_id'], 'Rename lost stable article identity.' );
	$config_input['enabled'] = '0';
	wp_set_current_user( $users['admin'] );
	$check( true === ps_pen_paper_configure_wiki( $universe, $config_input, '1', 'confirmed' ) &&
		is_wp_error( ps_pen_paper_save_wiki_entry( $universe, $entry, $input, '5', '2', 'confirmed', true ) ) &&
		'publish' === get_post_status( $wiki_id ), 'Pause changed public article or allowed writes.' );
	$config_input['enabled'] = '1';
	$check( true === ps_pen_paper_configure_wiki( $universe, $config_input, '2', 'confirmed' ), 'Reactivation failed.' );
	wp_set_current_user( $users['gm'] );
	wp_update_post( array( 'ID' => $wiki_id, 'post_content' => 'Native editorial addition.' ) );
	$check( is_wp_error( ps_pen_paper_save_wiki_entry( $universe, $entry, $input, '5', '3', 'confirmed', true ) ) &&
		'Native editorial addition.' === get_post( $wiki_id )->post_content, 'Native editorial text overwritten.' );
	$log = ps_pen_paper_get_events( $universe ); $check( ! is_wp_error( $log ), 'Wiki history unreadable.' );
	foreach ( $log['events'] as $event ) { ob_start(); ps_pen_paper_render_event_details( $event, $universe ); ob_end_clean(); }
	wp_set_current_user( $users['other'] );
	$check( is_wp_error( ps_pen_paper_get_wiki_entry( $universe, $entry ) ) && is_wp_error( ps_pen_paper_wiki_config( $universe ) ) &&
		is_wp_error( ps_pen_paper_save_wiki_entry( $universe, $entry, $input, '5', '3', 'confirmed', true ) ), 'Foreign account accessed adapter.' );
	ob_start(); ps_pen_paper_render_wiki( $universe, 27 ); $html = ob_get_clean(); $check( '' === $html, 'Foreign UI leaked private source names.' );
	wp_set_current_user( 0 );
	$query = new WP_Query( array( 'post_type' => 'psource_wiki', 'post_status' => 'publish', 'p' => $wiki_id ) );
	$check( 1 === count( $query->posts ) && false === strpos( serialize( $query->posts ), 'PRIVATE_' ), 'Public article missing or leaked private fields.' );
	WP_CLI::success( 'Real PS Wiki categories, opt-in, transactional taxonomy/draft/publication rollback, explicit publication, stable mappings, staged updates, native editorial conflicts, pause and private management passed.' );
} finally {
	if ( isset( $fail ) ) { remove_filter( 'wp_insert_post_data', $fail, 10 ); }
	wp_set_current_user( $original_user );
	foreach ( $native_ids as $id ) { wp_delete_post( $id, true ); }
	foreach ( array_reverse( $term_ids ) as $id ) { wp_delete_term( $id, 'psource_wiki_category' ); }
	foreach ( $parents as $parent ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_npc', 'pspp_wiki_entry', 'pspp_scene', 'pspp_event' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $parent, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $child ) { wp_delete_post( $child, true ); }
		wp_delete_post( $parent, true );
	}
	foreach ( $users as $user ) { is_multisite() ? wpmu_delete_user( $user ) : wp_delete_user( $user ); }
}
