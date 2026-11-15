<?php
/**
 * Run with WP-CLI against a disposable development site.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'npc', 'scene', 'folio', 'asset', 'event' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
$original_user = get_current_user_id();
$users = array();
$parents = array();
$path = '';
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$count = function ( $scope ) {
	return count( get_posts( array( 'post_type' => array( 'pspp_npc', 'pspp_asset', 'pspp_event' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $scope->ID, 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
};
$failure = function ( $data, $postarr ) { if ( isset( $postarr['ID'] ) && 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) { $data['post_status'] = 'draft'; } return $data; };
try {
	foreach ( array( 'world', 'gm', 'other', 'player' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-npc-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( ! is_wp_error( $id ), 'Fixture account failed.' );
		$users[ $role ] = $id;
	}
	foreach ( array( 'pspp_universe' => 'world', 'pspp_campaign' => 'gm' ) as $type => $role ) {
		$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'private', 'post_author' => $users[ $role ], 'post_title' => 'NPC fixture' ), true );
		$check( is_int( $id ) && $id > 0, 'Scope fixture failed.' );
		$parents[] = $id;
	}
	$universe = ps_pen_paper_get_universe( $parents[0] );
	$campaign = ps_pen_paper_get_campaign( $parents[1] );
	$id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_author' => $users['other'], 'post_title' => 'Other NPC fixture' ), true );
	$check( is_int( $id ) && $id > 0, 'Other scope fixture failed.' );
	$parents[] = $id;
	$other = ps_pen_paper_get_campaign( $id );
	add_post_meta( $campaign->ID, '_pspp_player', (string) $users['player'] );
	$path = wp_tempnam( 'pspp-npc.png' );
	$check( false !== file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=' ) ), 'Image fixture failed.' );
	$input = array( 'kind' => 'enemy', 'name' => 'PRIVATE_DOCK_GUARD', 'description' => 'VISIBLE_ONLY_TO_GMS', 'secret' => 'HIDDEN_IDENTITY', 'scene_id' => '0', 'properties' => "Fraktion | Hafenwache\n0 | Ziffernname", 'values' => "Mut | 3\nVorrat | 8" );
	wp_set_current_user( $users['gm'] );
	$scene = ps_pen_paper_save_scene( $campaign, 0, array( 'title' => 'Dock scene', 'text' => 'Public dock', 'secret' => '', 'grid' => '0' ), '0' );
	$check( is_int( $scene ), 'Scene fixture failed.' );
	$input['scene_id'] = (string) $scene;
	$npc = ps_pen_paper_save_npc( $campaign, 0, $input, '0', $path );
	$check( is_int( $npc ), 'NPC creation failed: ' . ( is_wp_error( $npc ) ? $npc->get_error_message() : '' ) );
	$initial = ps_pen_paper_get_npc( $campaign, $npc );
	$check( 3 === $initial['npc']['values'][0]['value'] && '0' === $initial['npc']['properties'][1]['label'], 'Free structured values lost.' );
	$check( is_wp_error( ps_pen_paper_save_npc( $campaign, $npc, $input, '0' ) ) && is_wp_error( ps_pen_paper_get_npc( $other, $npc ) ), 'Stale or foreign record accepted.' );
	$check( is_wp_error( ps_pen_paper_transfer_npc( $campaign, $npc, '1', 'confirmed', true ) ), 'Standalone campaign offered NPC.' );
	foreach ( array( 'properties' => "Name | a\nName | b", 'values' => 'Mut | 1.5', 'name' => '', 'kind' => 'fixed_monster_class' ) as $key => $value ) {
		$bad = $input; $bad[ $key ] = $value;
		$check( is_wp_error( ps_pen_paper_save_npc( $campaign, $npc, $bad, '1' ) ), 'Malformed field accepted: ' . $key );
	}
	foreach ( array( $campaign, $other ) as $scope ) {
		update_post_meta( $scope->ID, '_pspp_universe_link', array( 'revision' => 1, 'universe_id' => $universe->ID, 'name' => 'NPC fixture' ) );
	}
	$check( is_wp_error( ps_pen_paper_transfer_npc( $campaign, $npc, '1', '', true ) ), 'Unconfirmed sharing accepted.' );
	$template = ps_pen_paper_transfer_npc( $campaign, $npc, '1', 'confirmed', true );
	$check( is_int( $template ), 'Proposal failed.' );
	$check( is_wp_error( ps_pen_paper_get_npc( $universe, $template, $campaign ) ) && array() === ps_pen_paper_npc_records( $universe, $campaign ), 'Pending proposal leaked to campaign catalogue.' );
	$check( 'npc_already_offered' === ps_pen_paper_transfer_npc( $campaign, $npc, '1', 'confirmed', true )->get_error_code(), 'Repeated offer duplicated the template.' );
	$check( is_wp_error( ps_pen_paper_review_npc( $universe, $template, '1', 'confirmed' ) ), 'Campaign GM approved universe proposal.' );
	wp_set_current_user( $users['world'] );
	$proposal = ps_pen_paper_get_npc( $universe, $template );
	$check( 'proposed' === $proposal['status'] && 0 === $proposal['npc']['scene_id'] && $proposal['npc']['asset_id'] !== $initial['npc']['asset_id'], 'Proposal did not freeze an independent snapshot/image.' );
	$check( is_wp_error( ps_pen_paper_review_npc( $universe, $template, '0', 'confirmed' ) ), 'Stale admission accepted.' );
	add_filter( 'wp_insert_post_data', $failure, 10, 2 );
	try {
		$before_count = $count( $universe );
		$check( is_wp_error( ps_pen_paper_review_npc( $universe, $template, '1', 'confirmed' ) ) &&
			$proposal === ps_pen_paper_get_npc( $universe, $template ) && $before_count === $count( $universe ), 'Failed journal publication admitted the proposal.' );
	} finally { remove_filter( 'wp_insert_post_data', $failure, 10 ); }
	$check( true === ps_pen_paper_review_npc( $universe, $template, '1', 'confirmed' ), 'Admission failed.' );
	$check( is_wp_error( ps_pen_paper_get_npc( $campaign, $npc ) ), 'Universe GM gained campaign editing rights.' );
	$template_state = ps_pen_paper_get_npc( $universe, $template );
	wp_set_current_user( $users['other'] );
	$check( isset( ps_pen_paper_npc_records( $universe, $other )[ $template ] ), 'Approved template missing from linked campaign catalogue.' );
	$check( is_wp_error( ps_pen_paper_transfer_npc( $other, $template, '1', 'confirmed' ) ), 'Stale template copied.' );
	$copy = ps_pen_paper_transfer_npc( $other, $template, '2', 'confirmed' );
	$check( is_int( $copy ), 'Template reuse failed.' );
	$copied = ps_pen_paper_get_npc( $other, $copy );
	$check( 0 === $copied['npc']['scene_id'] && 2 === $copied['source']['revision'] && $copied['npc']['asset_id'] !== $template_state['npc']['asset_id'], 'Copy lost frozen provenance or image isolation.' );
	$changed = $input;
	$changed['scene_id'] = '0'; $changed['values'] = 'Mut | 1'; $changed['name'] = 'INJURED_LOCAL_COPY';
	$check( $copy === ps_pen_paper_save_npc( $other, $copy, $changed, '1' ), 'Local state edit failed.' );
	$changed['scene_id'] = (string) $scene;
	$check( is_wp_error( ps_pen_paper_save_npc( $other, $copy, $changed, '2' ) ), 'Foreign scene accepted.' );
	wp_set_current_user( $users['world'] );
	$check( $template_state === ps_pen_paper_get_npc( $universe, $template ), 'Local edit mutated universe template.' );
	$changed['scene_id'] = '0'; $changed['name'] = 'REVISED_UNIVERSE_TEMPLATE'; $changed['values'] = 'Mut | 9';
	$check( $template === ps_pen_paper_save_npc( $universe, $template, $changed, '2' ), 'Template update failed.' );
	wp_set_current_user( $users['other'] );
	$check( 1 === ps_pen_paper_get_npc( $other, $copy )['npc']['values'][0]['value'], 'Template edit rewrote existing figure.' );
	$before_count = $count( $other );
	add_filter( 'wp_insert_post_data', $failure, 10, 2 );
	try {
		$check( is_wp_error( ps_pen_paper_save_npc( $other, 0, $changed, '0', $path ) ) && $before_count === $count( $other ), 'Failed creation left figure, image or event.' );
		$check( is_wp_error( ps_pen_paper_save_npc( $other, $copy, $changed, '2' ) ) && 2 === ps_pen_paper_get_npc( $other, $copy )['revision'], 'Failed edit did not roll back.' );
		$check( is_wp_error( ps_pen_paper_transfer_npc( $other, $template, '3', 'confirmed' ) ) && $before_count === $count( $other ), 'Failed copy left figure/image/event.' );
	} finally { remove_filter( 'wp_insert_post_data', $failure, 10 ); }
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_publish_scene( $campaign, $scene, '1', 'confirmed' ) && true === ps_pen_paper_select_scene( $campaign, $scene, '0' ), 'Scene activation failed.' );
	ob_start(); ps_pen_paper_render_adventure_plan( $campaign, 27, ps_pen_paper_adventure_plan( $campaign ) ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'HIDDEN_IDENTITY' ) && false !== strpos( $html, 'Figur öffnen' ), 'GM scene cues omitted linked figure.' );
	$event = ps_pen_paper_get_events( $campaign )['events'];
	$event = array_values( array_filter( $event, function ( $entry ) { return 'npc_saved' === $entry['kind']; } ) )[0];
	ob_start(); ps_pen_paper_render_event_details( $event ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'HIDDEN_IDENTITY' ) && false !== strpos( $html, 'PRIVATE_DOCK_GUARD' ), 'NPC history lost snapshot details.' );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_get_npc( $campaign, $npc ) ) && is_wp_error( ps_pen_paper_npc_records( $universe, $campaign ) ) && is_wp_error( ps_pen_paper_get_event( $campaign, $event['id'] ) ), 'Player accessed private figures/catalogue/history.' );
	$check( is_wp_error( ps_pen_paper_save_npc( $campaign, 0, $input, '0' ) ) && is_wp_error( ps_pen_paper_transfer_npc( $campaign, $npc, '1', 'confirmed', true ) ), 'Player changed figures.' );
	ob_start(); ps_pen_paper_render_npcs( $campaign, 27 ); ps_pen_paper_render_scene_npcs( $campaign, $scene, 27 ); $html = ob_get_clean();
	$check( '' === $html && false === strpos( wp_json_encode( ps_pen_paper_map_state( $campaign ) ), 'HIDDEN_IDENTITY' ), 'Player UI/map API leaked figures.' );
	wp_set_current_user( $users['other'] );
	delete_post_meta( $other->ID, '_pspp_universe_link' );
	$check( is_wp_error( ps_pen_paper_get_npc( $universe, $template, $other ) ) && is_wp_error( ps_pen_paper_transfer_npc( $other, $template, '3', 'confirmed' ) ), 'Unlinked campaign retained library access.' );
	$check( ! is_wp_error( ps_pen_paper_get_npc( $other, $copy ) ), 'Unlinking destroyed independent figure.' );
	wp_set_current_user( 0 );
	$check( is_wp_error( ps_pen_paper_get_npc( $campaign, $npc ) ), 'Guest read NPC.' );
	WP_CLI::success( 'Private structured NPCs, free values, protected images, explicit universe admission, proposal privacy, duplicate offers, frozen independent reuse, scene cues, event rollback and revoked catalogue access passed.' );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $parents as $parent ) {
		foreach ( get_posts( array( 'post_type' => array( 'pspp_npc', 'pspp_scene', 'pspp_asset', 'pspp_event' ), 'post_status' => array( 'private', 'draft' ), 'post_parent' => $parent, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
		wp_delete_post( $parent, true );
	}
	if ( $path && is_file( $path ) ) { unlink( $path ); }
	foreach ( $users as $id ) { is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ); }
}
