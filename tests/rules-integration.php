<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
ps_pen_paper_register_campaign_type();
ps_pen_paper_register_character_type();
ps_pen_paper_register_roll_type();
ps_pen_paper_register_event_type();

$check = static function ( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
};
$original_user = get_current_user_id();
$users = array();
$parents = array();
$suffix = strtolower( wp_generate_password( 12, false, false ) );
try {
	foreach ( array( 'gm', 'player', 'outsider' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-rules-' . $role . '-' . $suffix, 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber' ) );
		$check( is_int( $id ), 'Rules fixture user creation failed.' ); $users[ $role ] = $id;
	}
	wp_set_current_user( $users['gm'] );
	$id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_title' => 'Rules fixture ' . $suffix, 'post_author' => $users['gm'] ), true );
	$check( is_int( $id ) && $id > 0, 'Rules fixture campaign failed.' ); $parents[] = $id;
	add_post_meta( $id, '_pspp_player', (string) $users['player'] );
	$campaign = ps_pen_paper_get_campaign( $id );
	$input = array( 'name' => 'Systemoffene Regeln', 'description' => '', 'character_mode' => 'direct', 'dice' => array( '6', '20' ),
		'dice_mechanics' => array( 'sum', 'pool', 'highest', 'lowest' ), 'fields' => array(
			array( 'id' => '', 'label' => 'Hintergrund', 'description' => '', 'type' => 'textarea', 'edit_mode' => 'direct' ),
			array( 'id' => '', 'label' => 'Kraft', 'description' => '', 'type' => 'attribute', 'edit_mode' => 'approval', 'min' => '0', 'max' => '20', 'start' => '2' ),
			array( 'id' => '', 'label' => 'Lebenspunkte', 'description' => '', 'type' => 'resource', 'edit_mode' => 'gm_only', 'min' => '0', 'max' => '20', 'start' => '10' ),
			array( 'id' => '', 'label' => 'Erfahrung', 'description' => '', 'type' => 'number', 'edit_mode' => 'approval', 'min' => '0', 'max' => '1000', 'start' => '0' ),
			array( 'id' => '', 'label' => 'Stufe', 'description' => '', 'type' => 'number', 'min' => '1', 'max' => '10', 'start' => '1' ),
		) );
	$rules = ps_pen_paper_save_rules( $id, $input, '0' );
	$check( ! is_wp_error( $rules ) && ps_pen_paper_valid_rules_document( $rules ), 'Rules document with field rights failed.' );
	foreach ( $input['fields'] as $index => &$field ) { $field['id'] = $rules['fields'][ $index ]['id']; } unset( $field );
	list( $text, $strength, $life, $xp, $level ) = array_column( $rules['fields'], 'id' );
	$values = array( 'name' => 'Testfigur', 'values' => array( $text => 'Alt', $strength => '2', $life => array( 'current' => '10', 'maximum' => '20' ), $xp => '150', $level => '1' ) );
	$character = ps_pen_paper_save_character( $id, 0, $users['player'], $values, '0', '1' );
	$check( is_int( $character ), 'GM could not create rules fixture sheet.' );
	$input['progression_mode'] = 'configured';
	$input['progression'] = array( 'experience_field' => $xp, 'level_field' => $level, 'thresholds' => "0 : 1\n100 : 2\n300 : 4" );
	$check( is_wp_error( ps_pen_paper_save_rules( $id, $input, '1' ) ), 'Progression changed an existing sheet without preview.' );
	$preview = ps_pen_paper_preview_rules_migration( $id, $input, '1' );
	$check( ! is_wp_error( $preview ) && 2 === $preview['characters'][0]['conflicts'][0]['default'], 'Progression migration did not show the derived level.' );
	ob_start(); ps_pen_paper_render_rules_migration_preview( $campaign, $preview ); $html = ob_get_clean();
	$document = new DOMDocument(); @$document->loadHTML( '<?xml encoding="UTF-8">' . $html );
	$body = array();
	foreach ( $document->getElementsByTagName( 'input' ) as $element ) {
		$name = $element->getAttribute( 'name' );
		if ( '' !== $name ) { $body[] = rawurlencode( $name ) . '=' . rawurlencode( $element->getAttribute( 'value' ) ); }
	}
	parse_str( implode( '&', $body ), $posted );
	$posted_preview = ps_pen_paper_preview_rules_migration( $id, $posted['rules'], '1' );
	$check( ! is_wp_error( $posted_preview ) && $preview['hash'] === $posted_preview['hash'], 'Migration form round-trip changed thresholds, field rights or mechanics.' );
	$check( true === ps_pen_paper_save_rules_migration( $id, $posted['rules'], '1', $posted['resolutions'], 'confirmed', $preview['hash'] ), 'Confirmed progression migration failed.' );
	$rules = ps_pen_paper_get_rules( $id );
	$check( ps_pen_paper_valid_rules_document( $rules ) && 2 === ps_pen_paper_raw_character_state( $character )['active']['values'][ $level ], 'Migrated level or native rule validation failed.' );
	foreach ( array( "0:1\n100:2\n100:3", "0:1\n100:1", "1:1\n100:2", "0:1\n1001:2", "0:11", "0:1\nbad" ) as $thresholds ) {
		$bad = $input; $bad['progression']['thresholds'] = $thresholds;
		$check( is_wp_error( ps_pen_paper_validate_rules( $bad, $rules ) ), 'Invalid progression threshold was accepted.' );
	}
	$bad = $input; $bad['progression']['level_field'] = $xp;
	$check( is_wp_error( ps_pen_paper_validate_rules( $bad, $rules ) ), 'Experience and level could use the same field.' );
	$bad = $input; $bad['fields'][0]['edit_mode'] = 'anything';
	$check( is_wp_error( ps_pen_paper_validate_rules( $bad, $rules ) ), 'Unknown field rights were accepted.' );
	$bad = $input; $bad['dice_mechanics'] = null;
	$check( is_wp_error( ps_pen_paper_validate_rules( $bad, $rules ) ), 'Malformed dice permissions were accepted.' );
	wp_set_current_user( $users['player'] );
	$values['values'][ $level ] = '2'; $values['values'][ $text ] = 'Sofort'; $values['values'][ $strength ] = '7'; $values['values'][ $xp ] = '300';
	$revision = (string) ps_pen_paper_raw_character_state( $character )['revision'];
	$check( $character === ps_pen_paper_save_character( $id, $character, $users['player'], $values, $revision, '2' ), 'Mixed direct and approval save failed.' );
	$state = ps_pen_paper_raw_character_state( $character );
	$check( 'Sofort' === $state['active']['values'][ $text ] && 2 === $state['active']['values'][ $strength ] &&
		150 === $state['active']['values'][ $xp ] && 2 === $state['active']['values'][ $level ] &&
		7 === $state['pending']['values'][ $strength ] && 300 === $state['pending']['values'][ $xp ] && 4 === $state['pending']['values'][ $level ],
		'Direct values, requested XP or derived pending level leaked between snapshots.' );
	$values['values'][ $life ]['current'] = '11';
	$check( is_wp_error( ps_pen_paper_save_character( $id, $character, $users['player'], $values, (string) $state['revision'], '2' ) ) &&
		$state === ps_pen_paper_raw_character_state( $character ), 'Protected resource change was saved.' );
	$values['values'][ $life ]['current'] = '10'; $values['values'][ $level ] = '4'; $values['values'][ $text ] = 'Direkt trotz Antrag';
	$check( $character === ps_pen_paper_save_character( $id, $character, $users['player'], $values, (string) $state['revision'], '2' ), 'Direct edit while pending failed.' );
	$state = ps_pen_paper_raw_character_state( $character );
	$check( 'Direkt trotz Antrag' === $state['active']['values'][ $text ] && 150 === $state['active']['values'][ $xp ] &&
		'Direkt trotz Antrag' === $state['pending']['values'][ $text ], 'Direct edit approved XP or was lost from pending state.' );
	ob_start(); ps_pen_paper_render_character_form( $campaign, $rules, get_post( $character ) ); $html = ob_get_clean();
	$check( false === strpos( $html, 'name="character[values][' . $life . '][current]" type="number"' ) &&
		false !== strpos( $html, 'Wird automatisch aus dem Erfahrungsstand berechnet.' ), 'Protected fields or computed level remained editable.' );
	wp_set_current_user( $users['outsider'] );
	$check( is_wp_error( ps_pen_paper_save_character( $id, $character, $users['player'], $values, (string) $state['revision'], '2' ) ), 'Outsider could change field values.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_review_character( $id, $character, 'reject', (string) $state['revision'] ), 'Mixed request rejection failed.' );
	$state = ps_pen_paper_raw_character_state( $character );
	$check( 'Direkt trotz Antrag' === $state['active']['values'][ $text ] && 2 === $state['active']['values'][ $level ] && null === $state['pending'], 'Rejection undid direct changes or applied requested levels.' );
	wp_set_current_user( $users['player'] );
	$check( $character === ps_pen_paper_save_character( $id, $character, $users['player'], $values, (string) $state['revision'], '2' ), 'Second XP request failed.' );
	$state = ps_pen_paper_raw_character_state( $character );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_review_character( $id, $character, 'approve', (string) $state['revision'] ) &&
		4 === ps_pen_paper_raw_character_state( $character )['active']['values'][ $level ], 'Approval did not apply derived requested level.' );
	foreach ( array( '0' => 1, '99' => 1, '100' => 2, '299' => 2, '300' => 4, '1000' => 4 ) as $experience => $expected ) {
		$probe = $values; $probe['values'][ $xp ] = (string) $experience;
		$snapshot = ps_pen_paper_validate_character( $probe, $rules );
		$check( ! is_wp_error( $snapshot ) && $expected === $snapshot['values'][ $level ], 'XP threshold boundary calculation failed.' );
	}
	$disabled = $input; $disabled['progression_mode'] = 'none';
	$disabled_rules = ps_pen_paper_validate_rules( $disabled, $rules );
	$check( 4 === ps_pen_paper_validate_character( $values, $disabled_rules )['values'][ $level ], 'Disabled progression overwrote an attained level.' );
	$new_values = $values; $new_values['values'][ $strength ] = '2'; $new_values['values'][ $xp ] = '0'; $new_values['values'][ $level ] = '1';
	wp_set_current_user( $users['player'] );
	$new_character = ps_pen_paper_save_character( $id, 0, $users['player'], $new_values, '0', '2' );
	$check( is_int( $new_character ) && null === ps_pen_paper_raw_character_state( $new_character )['active'] &&
		ps_pen_paper_valid_character_snapshot( ps_pen_paper_raw_character_state( $new_character )['pending'], $rules ), 'Mixed first sheet bypassed full initial approval.' );
	wp_set_current_user( $users['gm'] );
	$point_campaign_id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'post_title' => 'Level point fixture ' . $suffix, 'post_author' => $users['gm'] ), true );
	$check( is_int( $point_campaign_id ) && $point_campaign_id > 0, 'Level point fixture creation failed.' ); $parents[] = $point_campaign_id;
	add_post_meta( $point_campaign_id, '_pspp_player', (string) $users['player'] );
	$point_input = $input; $point_input['progression_mode'] = 'none'; $point_input['allocation_mode'] = 'none';
	foreach ( $point_input['fields'] as &$field ) { $field['id'] = ''; } unset( $field );
	$point_rules = ps_pen_paper_save_rules( $point_campaign_id, $point_input, '0' );
	$check( ! is_wp_error( $point_rules ), 'Level point fields failed.' );
	foreach ( $point_input['fields'] as $index => &$field ) { $field['id'] = $point_rules['fields'][ $index ]['id']; } unset( $field );
	list( $point_text, $point_strength, $point_life, $point_xp, $point_level ) = array_column( $point_rules['fields'], 'id' );
	$point_input['progression_mode'] = 'configured';
	$point_input['progression'] = array( 'experience_field' => $point_xp, 'level_field' => $point_level, 'thresholds' => "0:1\n100:2\n300:4" );
	$point_input['allocation_mode'] = 'level';
	$point_input['allocation'] = array( 'points' => '5', 'per_level' => '2', 'fields' => array( $point_strength ) );
	$point_rules = ps_pen_paper_save_rules( $point_campaign_id, $point_input, '1' );
	$check( ! is_wp_error( $point_rules ) && ps_pen_paper_valid_rules_document( $point_rules ) && ps_pen_paper_import_rules_valid( $point_rules ), 'Level point rules failed native or import validation.' );
	$point_values = array( 'name' => 'Level point hero', 'values' => array(
		$point_text => 'Start', $point_strength => '5', $point_life => array( 'current' => '10', 'maximum' => '20' ), $point_xp => '0', $point_level => '1',
	) );
	wp_set_current_user( $users['player'] );
	foreach ( array(
		array( $point_strength => '6' ), array( $point_xp => '100', $point_strength => '7' ),
		array( $point_level => '4' ), array( $point_life => array( 'current' => '11', 'maximum' => '19' ) ),
	) as $changes ) {
		$forged = $point_values; $forged['values'] = array_replace( $forged['values'], $changes );
		$check( is_wp_error( ps_pen_paper_save_character( $point_campaign_id, 0, $users['player'], $forged, '0', '2' ) ), 'Player invented start points, experience, level or resources.' );
	}
	$point_character = ps_pen_paper_save_character( $point_campaign_id, 0, $users['player'], $point_values, '0', '2' );
	$check( is_int( $point_character ), 'Valid initial point distribution failed.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_review_character( $point_campaign_id, $point_character, 'approve', '1' ), 'Initial point distribution approval failed.' );
	$point_values['values'][ $point_xp ] = '100';
	$check( $point_character === ps_pen_paper_save_character( $point_campaign_id, $point_character, $users['player'], $point_values, '2', '2' ), 'Level-up required spending new points in the same operation.' );
	$point_state = ps_pen_paper_raw_character_state( $point_character );
	$check( 2 === $point_state['active']['values'][ $point_level ] &&
		7 === ps_pen_paper_character_point_budget( $point_state['active'], $point_rules ) &&
		5 === $point_state['active']['values'][ $point_strength ], 'Level-up failed to leave two earned points available.' );
	wp_set_current_user( $users['player'] );
	$forged = $point_values; $forged['values'][ $point_strength ] = '8';
	$check( is_wp_error( ps_pen_paper_save_character( $point_campaign_id, $point_character, $users['player'], $forged, '3', '2' ) ),
		'Level 2 accepted more than the five start points plus two earned points.' );
	$forged = $point_values; $forged['values'][ $point_xp ] = '300'; $forged['values'][ $point_strength ] = '11';
	$check( is_wp_error( ps_pen_paper_save_character( $point_campaign_id, $point_character, $users['player'], $forged, '3', '2' ) ) &&
		$point_state === ps_pen_paper_raw_character_state( $point_character ), 'Forged experience unlocked unearned points.' );
	$point_values['values'][ $point_strength ] = '7'; $point_values['values'][ $point_level ] = '2';
	$check( $point_character === ps_pen_paper_save_character( $point_campaign_id, $point_character, $users['player'], $point_values, '3', '2' ), 'Earned point distribution failed.' );
	$point_state = ps_pen_paper_raw_character_state( $point_character );
	$check( 5 === $point_state['active']['values'][ $point_strength ] && 7 === $point_state['pending']['values'][ $point_strength ], 'Point distribution bypassed configured approval.' );
	wp_set_current_user( $users['gm'] );
	$check( true === ps_pen_paper_review_character( $point_campaign_id, $point_character, 'approve', '4' ), 'Earned point approval failed.' );
	$point_values['values'][ $point_xp ] = '300';
	$check( $point_character === ps_pen_paper_save_character( $point_campaign_id, $point_character, $users['player'], $point_values, '5', '2' ), 'Multi-level advancement failed.' );
	$point_state = ps_pen_paper_raw_character_state( $point_character );
	$check( 4 === $point_state['active']['values'][ $point_level ] &&
		11 === ps_pen_paper_character_point_budget( $point_state['active'], $point_rules ) &&
		ps_pen_paper_import_character_valid( $point_state, $point_rules ), 'Multi-level point budget or import validity failed.' );
	ob_start(); ps_pen_paper_render_character_form( ps_pen_paper_get_campaign( $point_campaign_id ), $point_rules, get_post( $point_character ), 0, null, false, true ); $point_html = ob_get_clean();
	$check( false !== strpos( $point_html, 'data-point-budget="11"' ) &&
		false === strpos( $point_html, 'name="character[values][' . $point_life . '][maximum]" type="number"' ) &&
		false === strpos( $point_html, 'name="character[values][' . $point_xp . ']" type="number"' ), 'Profile exposed resource or experience overrides instead of earned points.' );
	$own_point_character = ps_pen_paper_save_character( $point_campaign_id, 0, $users['gm'], $point_values, '0', '2' );
	$check( is_int( $own_point_character ), 'GM own profile fixture failed.' );
	$forged = $point_values; $forged['values'][ $point_life ]['maximum'] = '19';
	$check( is_wp_error( ps_pen_paper_save_character( $point_campaign_id, $own_point_character, $users['gm'], $forged, '1', '2', true ) ),
		'GM profile bypassed player resource restrictions.' );
	$migration_input = $point_input; $migration_input['allocation']['per_level'] = '3';
	$point_preview = ps_pen_paper_preview_rules_migration( $point_campaign_id, $migration_input, '2' );
	$check( ! is_wp_error( $point_preview ), 'Level point migration preview failed.' );
	ob_start(); ps_pen_paper_render_rules_migration_preview( ps_pen_paper_get_campaign( $point_campaign_id ), $point_preview ); $point_preview_html = ob_get_clean();
	$document = new DOMDocument(); @$document->loadHTML( '<?xml encoding="UTF-8">' . $point_preview_html );
	$body = array();
	foreach ( $document->getElementsByTagName( 'input' ) as $element ) {
		$name = $element->getAttribute( 'name' );
		if ( '' !== $name ) { $body[] = rawurlencode( $name ) . '=' . rawurlencode( $element->getAttribute( 'value' ) ); }
	}
	parse_str( implode( '&', $body ), $point_posted );
	$posted_point_preview = ps_pen_paper_preview_rules_migration( $point_campaign_id, $point_posted['rules'], '2' );
	$check( ! is_wp_error( $posted_point_preview ) && $posted_point_preview['hash'] === $point_preview['hash'],
		'Migration form dropped or changed the per-level point budget.' );
	wp_set_current_user( $users['player'] );
	$groups = array( array( 'sides' => 6, 'rolls' => array( 1, 4, 4, 6 ) ) );
	$check( 3 === ps_pen_paper_dice_total( $groups, 0, 'pool', 4 ) &&
		4 === ps_pen_paper_dice_total( $groups, 0, 'pool', 1 ) && 1 === ps_pen_paper_dice_total( $groups, 0, 'pool', 6 ) &&
		8 === ps_pen_paper_dice_total( $groups, 2, 'highest' ) && -1 === ps_pen_paper_dice_total( $groups, -2, 'lowest' ), 'Pool or highest/lowest arithmetic failed.' );
	foreach ( array( 'pool', 'highest', 'lowest' ) as $mechanic ) {
		$roll_input = array( 'expression' => '4W6', 'visibility' => 'private', 'character' => '', 'attribute' => '', 'label' => 'Mechanik', 'mechanic' => $mechanic, 'threshold' => '4' );
		$roll_id = ps_pen_paper_roll_dice( $id, $roll_input, '2' );
		$check( is_int( $roll_id ), 'Allowed dice mechanic was not saved.' );
		$roll = get_post_meta( $roll_id, '_pspp_roll', true );
		$check( ps_pen_paper_prepare_import_validate_roll( $roll ) &&
			$roll['total'] === ps_pen_paper_dice_total( $roll['groups'], 0, $mechanic, 'pool' === $mechanic ? 4 : null ), 'Saved mechanic or native arithmetic validation failed.' );
		$bad_roll = $roll; $bad_roll['total']++;
		$check( ! ps_pen_paper_prepare_import_validate_roll( $bad_roll ), 'Corrupted mechanic result passed native validation.' );
		$roll_input['expression'] = 'W6+W20';
		$check( is_wp_error( ps_pen_paper_roll_dice( $id, $roll_input, '2' ) ), 'Additional mechanic accepted mixed groups.' );
	}
	$roll_input['mechanic'] = 'pool'; $roll_input['expression'] = '4W6+1';
	$check( is_wp_error( ps_pen_paper_roll_dice( $id, $roll_input, '2' ) ), 'Pool accepted an additive modifier.' );
	$roll_input['expression'] = '4W6'; $roll_input['threshold'] = '7';
	$check( is_wp_error( ps_pen_paper_roll_dice( $id, $roll_input, '2' ) ), 'Pool accepted threshold above die sides.' );
	$roll_input['threshold'] = '4'; $roll_input['attribute'] = $strength;
	$check( is_wp_error( ps_pen_paper_roll_dice( $id, $roll_input, '2' ) ), 'Pool accepted attribute addition.' );
	$roll_input['attribute'] = ''; $roll_input['mechanic'] = 'exploding';
	$check( is_wp_error( ps_pen_paper_roll_dice( $id, $roll_input, '2' ) ), 'Unimplemented mechanic was accepted.' );
	$restricted = $input; $restricted['dice_mechanics'] = array( 'sum' );
	wp_set_current_user( $users['gm'] );
	$check( ! is_wp_error( ps_pen_paper_save_rules( $id, $restricted, '2' ) ), 'Mechanic restriction failed.' );
	$roll_input['mechanic'] = 'highest';
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_roll_dice( $id, $roll_input, '3' ) ), 'Disabled mechanic could still be rolled.' );
	$historical = ps_pen_paper_get_roll( $campaign, $roll_id );
	$check( ! is_wp_error( $historical ) && 'lowest' === $historical['mechanic'], 'Rule change invalidated a historical mechanic.' );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $parents as $parent ) {
		$children = get_posts( array( 'post_type' => array( 'pspp_character', 'pspp_roll', 'pspp_event' ), 'post_status' => array( 'private', 'draft', 'trash' ), 'post_parent' => $parent, 'posts_per_page' => -1, 'fields' => 'ids' ) );
		foreach ( $children as $child ) { wp_delete_post( $child, true ); }
		wp_delete_post( $parent, true );
	}
	foreach ( $users as $user ) { is_multisite() ? wpmu_delete_user( $user ) : wp_delete_user( $user ); }
}
WP_CLI::success( 'Per-field rights, mixed approvals, protected resources, XP threshold migration and boundaries, pool/highest/lowest results and native validation passed.' );
