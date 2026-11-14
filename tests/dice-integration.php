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

function pspp_dice_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$original_user = get_current_user_id();
$original_get = $_GET;
$post_ids = array();
$user_ids = array();
$suffix = strtolower( wp_generate_password( 12, false, false ) );
$all_dice = array( '4', '6', '8', '10', '12', '20', '100' );

try {
	$parsed = ps_pen_paper_parse_dice( ' d100 + 2w6 - 2 ', $all_dice );
	pspp_dice_assert( ! is_wp_error( $parsed ) && 100 === $parsed['groups'][0]['sides'] && '1W100 + 2W6 - 2' === $parsed['expression'], 'W100 was confused with W10 or notation was not normalized.' );
	pspp_dice_assert( ! is_wp_error( ps_pen_paper_parse_dice( '100W100 + 1000000', $all_dice ) ), 'Maximum dice count or modifier was rejected.' );
	pspp_dice_assert( ! is_wp_error( ps_pen_paper_parse_dice( implode( '+', array_fill( 0, 10, 'W6' ) ) . '-1000000', $all_dice ) ), 'Ten groups or negative modifier boundary was rejected.' );
	foreach ( array( '', '0W6', '01W6', '101W6', '100W6+W4', 'W20-W6', 'W6+1+W4', 'W6*2', 'W6+1000001', 'W6-1000001', 'W3', "W6\n", 'W6;phpinfo()', array( 'W6' ), str_repeat( 'W6+', 70 ), implode( '+', array_fill( 0, 11, 'W6' ) ) ) as $invalid ) {
		pspp_dice_assert( is_wp_error( ps_pen_paper_parse_dice( $invalid, $all_dice ) ), 'Invalid or excessive dice expression was accepted.' );
	}
	pspp_dice_assert( is_wp_error( ps_pen_paper_parse_dice( 'W20', array( '6' ) ) ) && is_wp_error( ps_pen_paper_parse_dice( 'W6', array() ) ), 'Campaign dice restrictions were ignored.' );
	foreach ( array( 'gm', 'player', 'other', 'outsider', 'assistant' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-dice-' . $role . '-' . $suffix, 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber' ) );
		pspp_dice_assert( ! is_wp_error( $id ), 'Could not create temporary user.' );
		$user_ids[ $role ] = $id;
	}
	wp_set_current_user( $user_ids['gm'] );
	$campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_title' => 'Dice integration ' . $suffix, 'post_author' => $user_ids['gm'] ), true );
	pspp_dice_assert( ! is_wp_error( $campaign_id ) && $campaign_id, 'Could not create dice campaign.' );
	$post_ids[] = $campaign_id;
	foreach ( array( 'gm', 'assistant', 'player', 'other' ) as $role ) {
		add_post_meta( $campaign_id, in_array( $role, array( 'gm', 'assistant' ), true ) ? '_pspp_gm' : '_pspp_player', (string) $user_ids[ $role ] );
	}
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	$input_rules = array(
		'name' => 'Freies Würfelsystem', 'description' => '', 'dice' => $all_dice,
		'fields' => array(
			array( 'id' => '', 'label' => 'Kraft', 'type' => 'attribute', 'description' => '', 'min' => '-20', 'max' => '20', 'start' => '5' ),
			array( 'id' => '', 'label' => 'Wut', 'type' => 'resource', 'description' => '', 'min' => '0', 'max' => '100', 'start' => '10' ),
		),
	);
	$rules = ps_pen_paper_save_rules( $campaign_id, $input_rules, '0' );
	pspp_dice_assert( ! is_wp_error( $rules ), 'Could not save dice rules.' );
	$roll_input = array( 'expression' => 'W6', 'visibility' => 'gm', 'character' => '', 'attribute' => '', 'label' => '' );
	pspp_dice_assert( is_wp_error( ps_pen_paper_roll_dice( $campaign_id, $roll_input, '1' ) ), 'Unconfigured blind rolls were allowed.' );
	foreach ( $input_rules['fields'] as $index => &$field ) {
		$field['id'] = $rules['fields'][ $index ]['id'];
	}
	unset( $field );
	$input_rules['roll_visibilities'] = array( 'group', 'private', 'gm' );
	$rules = ps_pen_paper_save_rules( $campaign_id, $input_rules, '1' );
	pspp_dice_assert( ! is_wp_error( $rules ), 'GM could not configure roll visibility.' );
	$invalid_rules = $input_rules;
	$invalid_rules['roll_visibilities'] = array( 'internet' );
	pspp_dice_assert( is_wp_error( ps_pen_paper_save_rules( $campaign_id, $invalid_rules, '2' ) ), 'Unknown roll visibility was stored.' );
	$invalid_rules['roll_visibilities'] = null;
	pspp_dice_assert( is_wp_error( ps_pen_paper_save_rules( $campaign_id, $invalid_rules, '2' ) ), 'Malformed visibility settings were accepted.' );
	$attribute = $rules['fields'][0]['id'];
	$resource = $rules['fields'][1]['id'];
	$character_input = array( 'name' => 'Kämpfer C:\Probe', 'values' => array( $attribute => '5', $resource => array( 'current' => '7', 'maximum' => '10' ) ) );
	$character_id = ps_pen_paper_save_character( $campaign_id, 0, $user_ids['player'], $character_input, '0', '2' );
	pspp_dice_assert( ! is_wp_error( $character_id ), 'Could not create approved test character.' );
	$post_ids[] = $character_id;
	wp_set_current_user( $user_ids['player'] );
	$character_input['values'][ $attribute ] = '17';
	pspp_dice_assert( $character_id === ps_pen_paper_save_character( $campaign_id, $character_id, $user_ids['player'], $character_input, '1', '2' ), 'Could not create pending attribute change.' );
	$pending_id = ps_pen_paper_save_character( $campaign_id, 0, $user_ids['player'], $character_input, '0', '2' );
	pspp_dice_assert( ! is_wp_error( $pending_id ), 'Could not create unapproved character fixture.' );
	$post_ids[] = $pending_id;
	$roll_input = array( 'expression' => 'W4+W6+W8+W10+W12+W20+W100+2', 'visibility' => 'group', 'character' => $character_id . ':2', 'attribute' => $attribute, 'label' => 'Probe C:\Welt' );
	$public = ps_pen_paper_roll_dice( $campaign_id, $roll_input, '2' );
	pspp_dice_assert( ! is_wp_error( $public ), 'Player could not roll.' );
	$post_ids[] = $public;
	$event = ps_pen_paper_get_roll( $campaign, $public );
	pspp_dice_assert( ! is_wp_error( $event ) && 5 === $event['attribute']['value'] && 'Kämpfer C:\Probe' === $event['character']['name'] && 'Probe C:\Welt' === $event['label'], 'Roll used pending values or lost literal backslashes.' );
	$sum = 7;
	foreach ( $event['groups'] as $group ) {
		foreach ( $group['rolls'] as $value ) {
			pspp_dice_assert( is_int( $value ) && $value >= 1 && $value <= $group['sides'], 'Individual die result is outside its range.' );
			$sum += $value;
		}
	}
	pspp_dice_assert( $event['total'] === $sum && 7 === count( $event['groups'] ), 'Stored result does not equal dice plus modifier plus approved attribute.' );
	$baseline = count( ps_pen_paper_get_rolls( $campaign )['rolls'] );
	foreach ( array( array( 'character', $character_id . ':1' ), array( 'character', '0:2' ), array( 'attribute', $resource ), array( 'visibility', 'internet' ), array( 'expression', '101W6' ), array( 'label', array( 'bad' ) ) ) as $change ) {
		$invalid = $roll_input;
		$invalid[ $change[0] ] = $change[1];
		pspp_dice_assert( is_wp_error( ps_pen_paper_roll_dice( $campaign_id, $invalid, '2' ) ), 'Invalid roll request was accepted.' );
	}
	pspp_dice_assert( is_wp_error( ps_pen_paper_roll_dice( $campaign_id, $roll_input, '1' ) ) && $baseline === count( ps_pen_paper_get_rolls( $campaign )['rolls'] ), 'Rejected requests wrote events or stale rules were allowed.' );
	$invalid = $roll_input;
	$invalid['character'] = $pending_id . ':1';
	pspp_dice_assert( is_wp_error( ps_pen_paper_roll_dice( $campaign_id, $invalid, '2' ) ), 'Unapproved first character can be used for rolls.' );
	$roll_input['visibility'] = 'private';
	$roll_input['expression'] = '100W100';
	$private = ps_pen_paper_roll_dice( $campaign_id, $roll_input, '2' );
	pspp_dice_assert( ! is_wp_error( $private ), 'Private roll failed.' );
	$post_ids[] = $private;
	$maximum = ps_pen_paper_get_roll( $campaign, $private );
	pspp_dice_assert( 100 === count( $maximum['groups'][0]['rolls'] ) && $maximum['total'] === array_sum( $maximum['groups'][0]['rolls'] ) + 5, 'Maximum-size roll was truncated or summed incorrectly.' );
	$_GET = array( 'pspp_roll' => (string) $private );
	ob_start();
	ps_pen_paper_render_dice( $campaign );
	$maximum_html = ob_get_clean();
	pspp_dice_assert( 12 === substr_count( $maximum_html, 'class="pspp-die"' ) && false !== strpos( $maximum_html, '88 weitere' ), 'Large rolls do not bound animated dice while preserving remaining results.' );
	pspp_dice_assert( 1 === preg_match( '/<option value="private"\s+selected\b/', $maximum_html ), 'Private visibility is not retained for the next roll.' );
	$roll_input['visibility'] = 'gm';
	$roll_input['label'] = 'Unsichtbare Probe ' . $suffix;
	$blind = ps_pen_paper_roll_dice( $campaign_id, $roll_input, '2' );
	pspp_dice_assert( ! is_wp_error( $blind ), 'Blind roll failed.' );
	$post_ids[] = $blind;
	pspp_dice_assert( is_wp_error( ps_pen_paper_get_roll( $campaign, $blind ) ) && 2 === count( ps_pen_paper_get_rolls( $campaign )['rolls'] ), 'Player can read own blind result or blind roll count.' );
	$_GET = array( 'pspp_roll' => (string) $blind );
	ob_start();
	ps_pen_paper_render_dice( $campaign );
	$html = ob_get_clean();
	pspp_dice_assert( false === strpos( $html, 'data-roll-total' ) && false === strpos( $html, $roll_input['label'] ), 'Blind result leaked into HTML or animation data.' );
	wp_set_current_user( $user_ids['other'] );
	pspp_dice_assert( ! is_wp_error( ps_pen_paper_get_roll( $campaign, $public ) ) && is_wp_error( ps_pen_paper_get_roll( $campaign, $private ) ) && is_wp_error( ps_pen_paper_get_roll( $campaign, $blind ) ) && 1 === count( ps_pen_paper_get_rolls( $campaign )['rolls'] ), 'Another member sees private results or cannot see group results.' );
	pspp_dice_assert( is_wp_error( ps_pen_paper_roll_dice( $campaign_id, $roll_input, '2' ) ), 'Player can roll for another player’s character.' );
	wp_set_current_user( $user_ids['assistant'] );
	$roll_input['visibility'] = 'private';
	$gm_private = ps_pen_paper_roll_dice( $campaign_id, $roll_input, '2' );
	pspp_dice_assert( ! is_wp_error( $gm_private ) && ! is_wp_error( ps_pen_paper_get_roll( $campaign, $blind ) ), 'GM cannot roll for a player or read blind results.' );
	$post_ids[] = $gm_private;
	delete_post_meta( $campaign_id, '_pspp_gm', (string) $user_ids['assistant'] );
	add_post_meta( $campaign_id, '_pspp_player', (string) $user_ids['assistant'] );
	pspp_dice_assert( ! is_wp_error( ps_pen_paper_get_roll( $campaign, $gm_private ) ) && 2 === count( ps_pen_paper_get_rolls( $campaign )['rolls'] ), 'Former GM lost private actor access or gained unrelated private results.' );
	wp_set_current_user( $user_ids['player'] );
	pspp_dice_assert( ! is_wp_error( ps_pen_paper_get_roll( $campaign, $gm_private ) ), 'Affected player cannot read private GM roll.' );
	delete_post_meta( $campaign_id, '_pspp_player', (string) $user_ids['player'] );
	pspp_dice_assert( is_wp_error( ps_pen_paper_get_roll( $campaign, $private ) ) && is_wp_error( ps_pen_paper_get_rolls( $campaign ) ), 'Removed member retains log access.' );
	add_post_meta( $campaign_id, '_pspp_player', (string) $user_ids['player'] );
	wp_set_current_user( $user_ids['outsider'] );
	pspp_dice_assert( is_wp_error( ps_pen_paper_get_rolls( $campaign ) ) && is_wp_error( ps_pen_paper_get_roll( $campaign, $public ) ) && is_wp_error( ps_pen_paper_roll_dice( $campaign_id, $roll_input, '2' ) ), 'Nonmember can read or create rolls.' );
	wp_set_current_user( 0 );
	pspp_dice_assert( is_wp_error( ps_pen_paper_get_rolls( $campaign ) ) && is_wp_error( ps_pen_paper_roll_dice( $campaign_id, $roll_input, '2' ) ) && ! current_user_can( 'read_post', $public ), 'Guest has dice access.' );
	$type = get_post_type_object( 'pspp_roll' );
	pspp_dice_assert( ! $type->publicly_queryable && ! $type->show_in_rest && ! $type->show_ui, 'Roll post type is exposed.' );
	wp_set_current_user( $user_ids['gm'] );
	$other_campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_title' => 'Other dice campaign ' . $suffix, 'post_author' => $user_ids['gm'] ), true );
	pspp_dice_assert( ! is_wp_error( $other_campaign_id ) && $other_campaign_id, 'Could not create isolation fixture.' );
	$post_ids[] = $other_campaign_id;
	$other_campaign = ps_pen_paper_get_campaign( $other_campaign_id );
	pspp_dice_assert( is_wp_error( ps_pen_paper_get_roll( $other_campaign, $public ) ) && array() === ps_pen_paper_get_rolls( $other_campaign )['rolls'], 'Rolls cross campaign boundaries.' );
	pspp_dice_assert( true === ps_pen_paper_review_character( $campaign_id, $character_id, 'approve', '2' ), 'Could not approve changed attribute.' );
	$input_rules['fields'][0]['label'] = 'Mut';
	$input_rules['roll_visibilities'] = array( 'group' );
	$rules = ps_pen_paper_save_rules( $campaign_id, $input_rules, '2' );
	pspp_dice_assert( ! is_wp_error( $rules ), 'Could not update roll policy.' );
	$historical = ps_pen_paper_get_roll( $campaign, $public );
	pspp_dice_assert( 5 === $historical['attribute']['value'] && 'Kraft' === $historical['attribute']['label'] && 'private' === ps_pen_paper_get_roll( $campaign, $private )['visibility'], 'History changed when rules, policy or character changed.' );
	$roll_input = array( 'expression' => '2W6-1', 'visibility' => 'group', 'character' => '', 'attribute' => '', 'label' => '' );
	$fail_meta = static function( $check, $object_id, $key ) {
		return 'pspp_roll' === get_post_type( $object_id ) && '_pspp_recipient' === $key ? false : $check;
	};
	add_filter( 'update_post_metadata', $fail_meta, 10, 3 );
	try {
		pspp_dice_assert( is_wp_error( ps_pen_paper_roll_dice( $campaign_id, $roll_input, '3' ) ), 'Incomplete event storage reported success.' );
	} finally {
		remove_filter( 'update_post_metadata', $fail_meta, 10 );
	}
	pspp_dice_assert( 4 === count( ps_pen_paper_get_rolls( $campaign )['rolls'] ), 'Failed event storage left an incomplete roll.' );
	for ( $i = 0; $i < 21; $i++ ) {
		$id = ps_pen_paper_roll_dice( $campaign_id, $roll_input, '3' );
		pspp_dice_assert( ! is_wp_error( $id ), 'Could not create pagination fixture.' );
		$post_ids[] = $id;
	}
	wp_set_current_user( $user_ids['other'] );
	$first = ps_pen_paper_get_rolls( $campaign, 1 );
	$second = ps_pen_paper_get_rolls( $campaign, 2 );
	pspp_dice_assert( 20 === count( $first['rolls'] ) && 2 === $first['pages'] && 2 === count( $second['rolls'] ) && ! array_intersect( array_column( $first['rolls'], 'id' ), array_column( $second['rolls'], 'id' ) ), 'Pagination duplicates records, omits group rolls or counts private rolls.' );
	wp_set_current_user( $user_ids['gm'] );
	unset( $input_rules['roll_visibilities'] );
	$input_rules['roll_visibilities_present'] = '1';
	pspp_dice_assert( ! is_wp_error( ps_pen_paper_save_rules( $campaign_id, $input_rules, '3' ) ) && is_wp_error( ps_pen_paper_roll_dice( $campaign_id, $roll_input, '4' ) ), 'GM cannot disable all rolling visibilities.' );
	$input_rules['roll_visibilities'] = array( 'group' );
	$input_rules['dice'] = array();
	pspp_dice_assert( ! is_wp_error( ps_pen_paper_save_rules( $campaign_id, $input_rules, '4' ) ) && is_wp_error( ps_pen_paper_roll_dice( $campaign_id, $roll_input, '5' ) ), 'Diceless campaign can still create rolls.' );
} finally {
	wp_set_current_user( $original_user );
	$_GET = $original_get;
	$errors = array();
	$events = $post_ids ? get_posts( array( 'post_type' => 'pspp_event', 'post_status' => array( 'private', 'draft' ), 'post_parent__in' => $post_ids, 'posts_per_page' => -1, 'fields' => 'ids' ) ) : array();
	foreach ( $events as $id ) {
		if ( ! wp_delete_post( $id, true ) ) { $errors[] = 'Could not remove test event ' . $id; }
	}
	foreach ( array_reverse( $post_ids ) as $id ) {
		if ( ! wp_delete_post( $id, true ) ) { $errors[] = 'Could not remove post ' . $id; }
	}
	foreach ( $user_ids as $id ) {
		if ( ! ( is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ) ) ) { $errors[] = 'Could not remove user ' . $id; }
	}
	if ( $errors ) { WP_CLI::error( implode( '; ', $errors ) ); }
}
WP_CLI::success( 'Dice grammar, limits, snapshots, roll arithmetic, visibility, storage failures and pagination passed.' );
