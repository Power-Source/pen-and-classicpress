<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_adventure_reaction_error() {
	return new WP_Error( 'invalid_adventure_reaction', __( 'Prüfe NPC-Auswahl, aktuellen Zahlenwert, Würfelregeln, Zustandsfolgen und ausdrückliche Freigabe der Reaktion. Es wurde nichts übernommen.', 'ps-pen-paper' ) );
}

function ps_pen_paper_valid_adventure_reaction( $reaction ) {
	if ( null === $reaction ) { return true; }
	if ( ! is_array( $reaction ) || array( 'npc', 'expression', 'threshold', 'when', 'success_text', 'failure_text', 'effects' ) !== array_keys( $reaction ) ||
		! is_array( $reaction['npc'] ) || array( 'name', 'kind', 'value' ) !== array_keys( $reaction['npc'] ) ||
		! is_string( $reaction['npc']['name'] ) || '' === trim( $reaction['npc']['name'] ) || strlen( $reaction['npc']['name'] ) > 200 ||
		! is_string( $reaction['npc']['kind'] ) || ! isset( ps_pen_paper_npc_kinds()[ $reaction['npc']['kind'] ] ) ||
		! is_string( $reaction['expression'] ) || strlen( $reaction['expression'] ) > 200 ||
		! is_int( $reaction['threshold'] ) || abs( $reaction['threshold'] ) > 1000000 ||
		! in_array( $reaction['when'], array( 'decision', 'success', 'failure' ), true ) ||
		! is_string( $reaction['success_text'] ) || strlen( $reaction['success_text'] ) > 2000 ||
		! is_string( $reaction['failure_text'] ) || strlen( $reaction['failure_text'] ) > 2000 ||
		! ps_pen_paper_valid_adventure_effects( $reaction['effects'] ) ) { return false; }
	$value = $reaction['npc']['value'];
	if ( null !== $value && ( ! is_array( $value ) || array( 'label', 'value' ) !== array_keys( $value ) ||
		! is_string( $value['label'] ) || '' === trim( $value['label'] ) || strlen( $value['label'] ) > 200 ||
		! is_int( $value['value'] ) || abs( $value['value'] ) > 1000000 ) ) { return false; }
	if ( '' !== $reaction['expression'] ) {
		$parsed = ps_pen_paper_parse_dice( $reaction['expression'], array( '4', '6', '8', '10', '12', '20', '100' ) );
		if ( is_wp_error( $parsed ) || $parsed['expression'] !== $reaction['expression'] ) { return false; }
	}
	return true;
}

function ps_pen_paper_prepare_adventure_reaction( $campaign, $input, $states, $rules ) {
	if ( ! is_array( $input ) ) { return ps_pen_paper_adventure_reaction_error(); }
	foreach ( array( 'npc', 'expression', 'threshold', 'when', 'success_text', 'failure_text' ) as $key ) {
		if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) { return ps_pen_paper_adventure_reaction_error(); }
	}
	$effects = ps_pen_paper_prepare_adventure_effects( $input['effects'] ?? array(), $states );
	if ( is_wp_error( $effects ) ) { return $effects; }
	if ( '' === $input['npc'] ) {
		return '' === $input['expression'] && '0' === $input['threshold'] && 'decision' === $input['when'] &&
			'' === $input['success_text'] && '' === $input['failure_text'] && null === $effects
			? null : ps_pen_paper_adventure_reaction_error();
	}
	if ( 'confirmed' !== ( $input['confirmation'] ?? '' ) ||
		! preg_match( '/^([1-9]\d{0,18}):([1-9]\d{0,18}):(none|[0-9]{1,2})$/D', $input['npc'], $match ) ||
		(string) (int) $match[1] !== $match[1] || ! preg_match( '/^-?\d{1,7}$/D', $input['threshold'] ) ||
		(string) (int) $input['threshold'] !== $input['threshold'] ) { return ps_pen_paper_adventure_reaction_error(); }
	$npc = ps_pen_paper_get_npc( $campaign, (int) $match[1] );
	if ( is_wp_error( $npc ) || (string) $npc['revision'] !== $match[2] ) { return ps_pen_paper_adventure_reaction_error(); }
	$value = null;
	if ( 'none' !== $match[3] ) {
		if ( ! isset( $npc['npc']['values'][ (int) $match[3] ] ) ) { return ps_pen_paper_adventure_reaction_error(); }
		$value = $npc['npc']['values'][ (int) $match[3] ];
	}
	$expression = '';
	if ( '' !== trim( $input['expression'] ) ) {
		$parsed = ps_pen_paper_parse_dice( $input['expression'], $rules['dice'] );
		if ( is_wp_error( $parsed ) ) { return $parsed; }
		$expression = $parsed['expression'];
	}
	$reaction = array( 'npc' => array( 'name' => $npc['npc']['name'], 'kind' => $npc['npc']['kind'], 'value' => $value ),
		'expression' => $expression, 'threshold' => (int) $input['threshold'], 'when' => $input['when'],
		'success_text' => sanitize_textarea_field( $input['success_text'] ), 'failure_text' => sanitize_textarea_field( $input['failure_text'] ), 'effects' => $effects );
	return ps_pen_paper_valid_adventure_reaction( $reaction ) ? $reaction : ps_pen_paper_adventure_reaction_error();
}

function ps_pen_paper_adventure_reaction_triggered( $reaction, $roll ) {
	$success = null === $roll || $roll['success'];
	return 'decision' === $reaction['when'] || ( 'success' === $reaction['when'] ? $success : ! $success );
}

function ps_pen_paper_adventure_reaction_result( $reaction, $snapshot, $history, $entry ) {
	if ( ! ps_pen_paper_adventure_reaction_triggered( $reaction, $entry['roll'] ) ) { return null; }
	$roll = null;
	if ( '' !== $reaction['expression'] ) {
		$parsed = ps_pen_paper_parse_dice( $reaction['expression'], array( '4', '6', '8', '10', '12', '20', '100' ) );
		if ( is_wp_error( $parsed ) ) { return $parsed; }
		$roll = ps_pen_paper_evaluate_dice( $parsed );
	}
	return ps_pen_paper_adventure_reaction_receipt( $reaction, $snapshot, $history, $entry, $roll );
}

function ps_pen_paper_adventure_reaction_receipt( $reaction, $snapshot, $history, $entry, $roll ) {
	$total = ( $roll ? $roll['total'] : 0 ) + ( $reaction['npc']['value']['value'] ?? 0 );
	$success = $total >= $reaction['threshold'];
	$history[] = array( 'effects' => $entry['effects'] ?? array() );
	$effects = ps_pen_paper_adventure_effects_result( $reaction, $snapshot, $history, array( 'success' => $success ) );
	if ( is_wp_error( $effects ) ) { return $effects; }
	return array( 'name' => $reaction['npc']['name'], 'roll' => $roll, 'total' => $total, 'success' => $success,
		'text' => $reaction[ $success ? 'success_text' : 'failure_text' ], 'effects' => $effects );
}

function ps_pen_paper_valid_adventure_reaction_result( $reaction, $result, $snapshot, $history, $entry ) {
	if ( ! ps_pen_paper_adventure_reaction_triggered( $reaction, $entry['roll'] ) ) { return null === $result; }
	if ( ! is_array( $result ) || array( 'name', 'roll', 'total', 'success', 'text', 'effects' ) !== array_keys( $result ) ) { return false; }
	$roll = $result['roll'];
	if ( '' === $reaction['expression'] ) {
		if ( null !== $roll ) { return false; }
	} else {
		$parsed = ps_pen_paper_parse_dice( $reaction['expression'], array( '4', '6', '8', '10', '12', '20', '100' ) );
		if ( ! is_array( $roll ) || array( 'expression', 'modifier', 'total', 'groups' ) !== array_keys( $roll ) ||
			! is_array( $roll['groups'] ) || array_values( $roll['groups'] ) !== $roll['groups'] ) { return false; }
		foreach ( $roll['groups'] as $group ) {
			if ( ! is_array( $group ) || array( 'sides', 'rolls' ) !== array_keys( $group ) ||
				! is_array( $group['rolls'] ) || array_values( $group['rolls'] ) !== $group['rolls'] ) { return false; }
		}
		if ( ! ps_pen_paper_valid_loot_roll( $roll, $parsed ) ) { return false; }
	}
	$expected = ps_pen_paper_adventure_reaction_receipt( $reaction, $snapshot, $history, $entry, $roll );
	return ! is_wp_error( $expected ) && $expected === $result;
}

function ps_pen_paper_adventure_reaction_label( $result ) {
	/* translators: 1: NPC name, 2: reaction total, 3: outcome. */
	$label = sprintf( __( '%1$s reagiert: %2$d · %3$s', 'ps-pen-paper' ), $result['name'], $result['total'],
		$result['success'] ? __( 'Erfolg', 'ps-pen-paper' ) : __( 'Misserfolg', 'ps-pen-paper' ) );
	return '' === $result['text'] ? $label : $label . ' · ' . $result['text'];
}
