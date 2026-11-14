<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( "Run this test with WP-CLI.\n" );
}

require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) {
	require_once ABSPATH . 'wp-admin/includes/ms.php';
}
ps_pen_paper_register_campaign_type();
ps_pen_paper_register_character_type();
ps_pen_paper_register_roll_type();
ps_pen_paper_register_event_type();

function pspp_event_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$original_user = get_current_user_id();
$original_get = $_GET;
$users = array();
$campaigns = array();
$suffix = strtolower( wp_generate_password( 12, false, false ) );

try {
	foreach ( array( 'gm', 'assistant', 'player', 'other', 'outsider' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-event-' . $role . '-' . $suffix, 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber' ) );
		pspp_event_assert( ! is_wp_error( $id ), 'Could not create temporary journal user.' );
		$users[ $role ] = $id;
	}
	wp_set_current_user( $users['gm'] );
	for ( $i = 0; $i < 2; $i++ ) {
		$id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_title' => 'Journal test ' . $suffix, 'post_author' => $users['gm'] ), true );
		pspp_event_assert( ! is_wp_error( $id ) && $id, 'Could not create temporary journal campaign.' );
		$campaigns[] = $id;
	}
	$campaign_id = $campaigns[0];
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	foreach ( array( 'assistant', 'player', 'other' ) as $role ) {
		add_post_meta( $campaign_id, 'assistant' === $role ? '_pspp_gm' : '_pspp_player', (string) $users[ $role ] );
	}
	$input = array( 'name' => 'Freie Regeln', 'description' => 'C:\Regeln', 'dice' => array( '6' ), 'roll_visibilities' => array( 'group', 'private', 'gm' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Kraft', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '20', 'start' => '2' ),
		array( 'id' => '', 'label' => 'Wut', 'type' => 'resource', 'description' => '', 'min' => '0', 'max' => '20', 'start' => '3' ),
	) );
	$rules = ps_pen_paper_save_rules( $campaign_id, $input, '0' );
	pspp_event_assert( ! is_wp_error( $rules ), 'Initial rules did not save with a journal record.' );
	$first = ps_pen_paper_get_events( $campaign )['events'][0];
	pspp_event_assert( 'rules_saved' === $first['kind'] && 0 === $first['before']['revision'] && $rules === $first['after'], 'Rule event did not preserve the exact revision transition.' );
	foreach ( $input['fields'] as $index => &$field ) {
		$field['id'] = $rules['fields'][ $index ]['id'];
	}
	unset( $field );
	$attribute = $rules['fields'][0]['id'];
	$resource = $rules['fields'][1]['id'];
	$character_input = array( 'name' => 'C:\Probe & Geheim', 'values' => array( $attribute => '2', $resource => array( 'current' => '3', 'maximum' => '10' ) ) );
	wp_set_current_user( $users['assistant'] );
	$character_id = ps_pen_paper_save_character( $campaign_id, 0, $users['player'], $character_input, '0', '1' );
	pspp_event_assert( ! is_wp_error( $character_id ), 'Character creation with an event failed.' );
	$created = ps_pen_paper_get_events( $campaign )['events'][0];
	pspp_event_assert( 'character_created' === $created['kind'] && $character_id === $created['object_id'] && 'C:\Probe & Geheim' === $created['after']['active']['name'], 'Character event lost ownership, identity or literal backslashes.' );
	wp_set_current_user( $users['player'] );
	$character_input['values'][ $attribute ] = '4';
	$character_input['values'][ $resource ]['current'] = '7';
	pspp_event_assert( $character_id === ps_pen_paper_save_character( $campaign_id, $character_id, $users['player'], $character_input, '1', '1' ), 'Pending event could not be saved.' );
	$requested = ps_pen_paper_get_events( $campaign )['events'][0];
	pspp_event_assert( 'character_requested' === $requested['kind'] && 2 === $requested['after']['active']['values'][ $attribute ] && 4 === $requested['after']['pending']['values'][ $attribute ] && 7 === $requested['after']['pending']['values'][ $resource ]['current'], 'Requested values were confused with active values or resources were lost.' );
	wp_set_current_user( $users['assistant'] );
	pspp_event_assert( true === ps_pen_paper_review_character( $campaign_id, $character_id, 'approve', '2' ), 'Approval could not be recorded.' );
	$approved = ps_pen_paper_get_events( $campaign )['events'][0];
	pspp_event_assert( 'character_approved' === $approved['kind'] && 4 === $approved['after']['active']['values'][ $attribute ] && null === $approved['after']['pending'], 'Approval history is incorrect.' );
	wp_set_current_user( $users['player'] );
	$character_input['values'][ $attribute ] = '6';
	ps_pen_paper_save_character( $campaign_id, $character_id, $users['player'], $character_input, '3', '1' );
	wp_set_current_user( $users['gm'] );
	pspp_event_assert( true === ps_pen_paper_review_character( $campaign_id, $character_id, 'reject', '4' ), 'Rejection could not be recorded.' );
	$rejected = ps_pen_paper_get_events( $campaign )['events'][0];
	pspp_event_assert( 'character_rejected' === $rejected['kind'] && 6 === $rejected['before']['pending']['values'][ $attribute ] && 4 === $rejected['after']['active']['values'][ $attribute ] && null === $rejected['after']['pending'], 'Rejection lost the rejected proposal or changed active values.' );
	$baseline = ps_pen_paper_get_events( $campaign );
	pspp_event_assert( is_wp_error( ps_pen_paper_review_character( $campaign_id, $character_id, 'approve', '4' ) ) && $baseline === ps_pen_paper_get_events( $campaign ), 'Rejected stale action wrote a success-shaped event.' );
	$input['fields'][0]['label'] = 'Mut';
	pspp_event_assert( ! is_wp_error( ps_pen_paper_save_rules( $campaign_id, $input, '1' ) ), 'Rule rename could not be logged.' );
	pspp_event_assert( 'Kraft' === ps_pen_paper_get_event( $campaign, $created['id'] )['fields'][0]['label'] && 2 === ps_pen_paper_get_event( $campaign, $created['id'] )['after']['active']['values'][ $attribute ], 'New rules or values rewrote historical character snapshots.' );

	$roll_input = array( 'expression' => 'W6', 'visibility' => 'private', 'character' => $character_id . ':5', 'attribute' => '', 'label' => '' );
	wp_set_current_user( $users['assistant'] );
	$private_roll = ps_pen_paper_roll_dice( $campaign_id, $roll_input, '2' );
	$roll_input['visibility'] = 'gm';
	$roll_input['label'] = 'Blindes Geheimnis ' . $suffix;
	wp_set_current_user( $users['player'] );
	$hidden_roll = ps_pen_paper_roll_dice( $campaign_id, $roll_input, '2' );
	$roll_input['visibility'] = 'group';
	$roll_input['character'] = '';
	$roll_input['label'] = '';
	$public_roll = ps_pen_paper_roll_dice( $campaign_id, $roll_input, '2' );
	pspp_event_assert( ! is_wp_error( $private_roll ) && ! is_wp_error( $hidden_roll ) && ! is_wp_error( $public_roll ), 'Could not create mixed journal fixtures.' );
	delete_post_meta( $campaign_id, '_pspp_gm', (string) $users['assistant'] );
	add_post_meta( $campaign_id, '_pspp_player', (string) $users['assistant'] );
	wp_set_current_user( $users['assistant'] );
	pspp_event_assert( is_wp_error( ps_pen_paper_get_event( $campaign, $created['id'] ) ) && 4 === count( ps_pen_paper_get_events( $campaign )['events'] ), 'Former GM retained private character history or lost private roll actor access.' );
	wp_set_current_user( $users['other'] );
	pspp_event_assert( 3 === count( ps_pen_paper_get_events( $campaign )['events'] ) && is_wp_error( ps_pen_paper_get_event( $campaign, $created['id'] ) ), 'Another member sees private character history or private/hidden roll counts.' );
	$_GET = array();
	ob_start();
	ps_pen_paper_render_journal( $campaign );
	$html = ob_get_clean();
	pspp_event_assert( false === strpos( $html, 'Probe &amp; Geheim' ) && false === strpos( $html, 'Blindes Geheimnis' ), 'Private values leaked into hidden HTML details.' );
	wp_set_current_user( $users['player'] );
	$own = ps_pen_paper_get_events( $campaign );
	pspp_event_assert( ! in_array( $hidden_roll, array_column( $own['events'], 'id' ), true ) && 1 === count( array_keys( array_column( $own['events'], 'id' ), $public_roll, true ) ), 'Mixed journal exposes a hidden roll or duplicates old roll records.' );
	ob_start();
	ps_pen_paper_render_journal( $campaign );
	$html = ob_get_clean();
	pspp_event_assert( false !== strpos( $html, 'C:\Probe &amp; Geheim' ) && false !== strpos( $html, 'Vorher' ) && false !== strpos( $html, 'Nachher' ), 'Owner cannot inspect escaped historical changes.' );
	delete_post_meta( $campaign_id, '_pspp_player', (string) $users['player'] );
	pspp_event_assert( is_wp_error( ps_pen_paper_get_events( $campaign ) ) && is_wp_error( ps_pen_paper_get_event( $campaign, $created['id'] ) ), 'Removed owner retains event access.' );
	add_post_meta( $campaign_id, '_pspp_player', (string) $users['player'] );
	foreach ( array( $users['outsider'], 0 ) as $user_id ) {
		wp_set_current_user( $user_id );
		pspp_event_assert( is_wp_error( ps_pen_paper_get_events( $campaign ) ) && ! current_user_can( 'read_post', $created['id'] ), 'Guest or nonmember can read events through a direct access path.' );
	}
	wp_set_current_user( $users['gm'] );
	pspp_event_assert( is_wp_error( ps_pen_paper_get_event( ps_pen_paper_get_campaign( $campaigns[1] ), $created['id'] ) ) && array() === ps_pen_paper_get_events( ps_pen_paper_get_campaign( $campaigns[1] ) )['events'], 'Events cross campaign boundaries.' );

	$before = get_post_meta( $character_id, '_pspp_character', true );
	$baseline = ps_pen_paper_get_events( $campaign );
	$fail_event = static function( $check, $id, $key ) use ( $campaign ) {
		if ( '_pspp_event' === $key ) {
			pspp_event_assert( 1 === count( ps_pen_paper_get_characters( $campaign ) ), 'An uninitialized character became visible before its state was stored.' );
			return false;
		}
		return $check;
	};
	add_filter( 'update_post_metadata', $fail_event, 10, 3 );
	try {
		pspp_event_assert( is_wp_error( ps_pen_paper_save_character( $campaign_id, $character_id, $users['player'], $character_input, '5', '2' ) ), 'Incomplete event storage reported success.' );
		pspp_event_assert( is_wp_error( ps_pen_paper_save_character( $campaign_id, 0, $users['player'], $character_input, '0', '2' ) ), 'Incomplete creation event reported success.' );
	} finally {
		remove_filter( 'update_post_metadata', $fail_event, 10 );
	}
	pspp_event_assert( $before === get_post_meta( $character_id, '_pspp_character', true ) && $baseline === ps_pen_paper_get_events( $campaign ) && 1 === count( ps_pen_paper_get_characters( $campaign ) ), 'Event preparation failure changed data or left an incomplete character.' );
	$fail_state = static function( $check, $id, $key ) { return '_pspp_character' === $key ? false : $check; };
	add_filter( 'update_post_metadata', $fail_state, 10, 3 );
	try {
		pspp_event_assert( is_wp_error( ps_pen_paper_save_character( $campaign_id, $character_id, $users['player'], $character_input, '5', '2' ) ), 'Failed state write reported success.' );
	} finally {
		remove_filter( 'update_post_metadata', $fail_state, 10 );
	}
	pspp_event_assert( $baseline === ps_pen_paper_get_events( $campaign ), 'Failed state write left a published event.' );
	$fail_publish = static function( $data ) {
		if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) { $data['post_status'] = 'draft'; }
		return $data;
	};
	add_filter( 'wp_insert_post_data', $fail_publish );
	try {
		pspp_event_assert( is_wp_error( ps_pen_paper_save_character( $campaign_id, $character_id, $users['player'], $character_input, '5', '2' ) ), 'Failed publication reported success.' );
		pspp_event_assert( is_wp_error( ps_pen_paper_save_rules( $campaign_id, $input, '2' ) ) && 2 === ps_pen_paper_get_rules( $campaign_id )['revision'], 'Failed publication did not restore existing rules.' );
		$initial_input = $input;
		foreach ( $initial_input['fields'] as &$field ) { $field['id'] = ''; }
		unset( $field );
		pspp_event_assert( is_wp_error( ps_pen_paper_save_rules( $campaigns[1], $initial_input, '0' ) ) && ! metadata_exists( 'post', $campaigns[1], '_pspp_rules' ), 'Initial rule rollback left a stored document.' );
		pspp_event_assert( is_wp_error( ps_pen_paper_save_character( $campaign_id, 0, $users['player'], $character_input, '0', '2' ) ) && 1 === count( ps_pen_paper_get_characters( $campaign ) ), 'Initial character rollback left a character.' );
	} finally {
		remove_filter( 'wp_insert_post_data', $fail_publish );
	}
	pspp_event_assert( $before === get_post_meta( $character_id, '_pspp_character', true ) && $baseline === ps_pen_paper_get_events( $campaign ), 'Publication failure did not restore the previous character and journal.' );
	$fail_character_publish = static function( $data ) {
		if ( 'pspp_character' === $data['post_type'] && 'private' === $data['post_status'] ) { $data['post_status'] = 'draft'; }
		return $data;
	};
	add_filter( 'wp_insert_post_data', $fail_character_publish );
	try {
		pspp_event_assert( is_wp_error( ps_pen_paper_save_character( $campaign_id, 0, $users['player'], $character_input, '0', '2' ) ) && $baseline === ps_pen_paper_get_events( $campaign ), 'Failed character publication left a successful creation event.' );
	} finally {
		remove_filter( 'wp_insert_post_data', $fail_character_publish );
	}

	for ( $i = 0; $i < 21; $i++ ) {
		pspp_event_assert( $character_id === ps_pen_paper_save_character( $campaign_id, $character_id, $users['player'], $character_input, (string) ( 5 + $i ), '2' ), 'Could not create journal pagination fixtures.' );
	}
	wp_set_current_user( $users['other'] );
	pspp_event_assert( 1 === ps_pen_paper_get_events( $campaign )['pages'] && 3 === count( ps_pen_paper_get_events( $campaign )['events'] ), 'Hidden character events affect another member’s page count.' );
	wp_set_current_user( $users['player'] );
	$page1 = ps_pen_paper_get_events( $campaign, 1 );
	$page2 = ps_pen_paper_get_events( $campaign, 2 );
	pspp_event_assert( 20 === count( $page1['events'] ) && 10 === count( $page2['events'] ) && 2 === $page1['pages'] && ! array_intersect( array_column( $page1['events'], 'id' ), array_column( $page2['events'], 'id' ) ), 'Mixed journal pagination duplicates, omits or counts unauthorized records.' );
	wp_set_current_user( $users['gm'] );
	$concurrent = get_post_meta( $character_id, '_pspp_character', true );
	$concurrent['revision'] += 2;
	$concurrent['active']['values'][ $attribute ] = 9;
	$race = static function( $data ) use ( $character_id, $concurrent ) {
		if ( 'pspp_event' === $data['post_type'] && 'private' === $data['post_status'] ) {
			update_post_meta( $character_id, '_pspp_character', wp_slash( $concurrent ) );
			$data['post_status'] = 'draft';
		}
		return $data;
	};
	add_filter( 'wp_insert_post_data', $race );
	try {
		$result = ps_pen_paper_save_character( $campaign_id, $character_id, $users['player'], $character_input, '26', '2' );
		pspp_event_assert( is_wp_error( $result ) && 'event_recovery_required' === $result->get_error_code() && $concurrent === get_post_meta( $character_id, '_pspp_character', true ), 'Recovery overwrote a concurrent revision or hid a partial failure.' );
	} finally {
		remove_filter( 'wp_insert_post_data', $race );
	}
	$drafts = get_posts( array( 'post_type' => 'pspp_event', 'post_status' => 'draft', 'post_parent' => $campaign_id, 'posts_per_page' => -1 ) );
	pspp_event_assert( 1 === count( $drafts ) && is_wp_error( ps_pen_paper_get_event( $campaign, $drafts[0]->ID ) ), 'Recovery evidence was discarded or an incomplete event became readable.' );
	wp_set_current_user( $users['player'] );
	$pending_id = ps_pen_paper_save_character( $campaign_id, 0, $users['player'], $character_input, '0', '2' );
	pspp_event_assert( ! is_wp_error( $pending_id ), 'Could not create an initial approval request.' );
	wp_set_current_user( $users['gm'] );
	pspp_event_assert( true === ps_pen_paper_review_character( $campaign_id, $pending_id, 'reject', '1' ), 'Could not reject an initial approval request.' );
	wp_set_current_user( $users['player'] );
	ob_start();
	ps_pen_paper_render_journal( $campaign );
	$html = ob_get_clean();
	pspp_event_assert( false !== strpos( $html, 'Charakteränderung abgelehnt' ) && false !== strpos( $html, 'C:\Probe &amp; Geheim' ), 'Rejection of an initial character has no readable historical identity.' );
} finally {
	wp_set_current_user( $original_user );
	$_GET = $original_get;
	$errors = array();
	$children = $campaigns ? get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_roll', 'pspp_event' ), 'post_status' => array( 'private', 'draft' ), 'post_parent__in' => $campaigns, 'posts_per_page' => -1, 'fields' => 'ids' ) ) : array();
	foreach ( array_merge( $children, $campaigns ) as $id ) {
		if ( ! wp_delete_post( $id, true ) ) { $errors[] = 'Could not remove journal fixture ' . $id; }
	}
	foreach ( $users as $id ) {
		if ( ! ( is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ) ) ) { $errors[] = 'Could not remove journal user ' . $id; }
	}
	if ( $errors ) { WP_CLI::error( implode( '; ', $errors ) ); }
}
WP_CLI::success( 'Event snapshots, mixed history, recipient privacy, pagination, failure rollback and concurrent recovery passed.' );
