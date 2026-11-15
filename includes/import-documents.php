<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_import_rules_valid( $rules ) {
	if ( is_wp_error( ps_pen_paper_import_rule_schema( $rules ) ) ||
		! isset( $rules['revision'], $rules['name'], $rules['description'], $rules['dice'] ) ||
		! is_int( $rules['revision'] ) || $rules['revision'] < 1 ||
		! is_array( $rules['dice'] ) || array_values( $rules['fields'] ) !== $rules['fields'] ) { return false; }
	$input = $rules;
	foreach ( $input['fields'] as &$field ) {
		if ( ! isset( $field['required'] ) || ! is_bool( $field['required'] ) ) { return false; }
		if ( $field['required'] ) { $field['required'] = '1'; } else { unset( $field['required'] ); }
		if ( in_array( $field['type'], array( 'number', 'attribute', 'resource' ), true ) ) {
			foreach ( array( 'min', 'max', 'start' ) as $key ) {
				if ( ! isset( $field[ $key ] ) || ! is_int( $field[ $key ] ) ) { return false; }
				$field[ $key ] = (string) $field[ $key ];
			}
		}
	}
	unset( $field );
	$previous = $rules; $previous['revision']--;
	$validated = ps_pen_paper_validate_rules( $input, $previous );
	if ( is_wp_error( $validated ) ) { return false; }
	foreach ( $rules as $key => $value ) {
		if ( ! array_key_exists( $key, $validated ) || $validated[ $key ] !== $value ) { return false; }
	}
	return true;
}

function ps_pen_paper_import_character_valid( $state, $rules ) {
	if ( ! is_array( $state ) || ! isset( $state['revision'] ) || ! is_int( $state['revision'] ) || $state['revision'] < 1 ||
		! array_key_exists( 'active', $state ) || ! array_key_exists( 'pending', $state ) ) { return false; }
	foreach ( array( 'active', 'pending' ) as $key ) {
		if ( null === $state[ $key ] ) { continue; }
		$validated = ps_pen_paper_context_snapshot( $state[ $key ], $rules );
		if ( is_wp_error( $validated ) || $validated !== $state[ $key ] ) { return false; }
	}
	return ! array_key_exists( 'context', $state ) || ! is_wp_error( ps_pen_paper_validate_context( $state['context'], $rules ) );
}

function ps_pen_paper_import_documents( $parsed ) {
	$validators = array(
		'_pspp_scene' => 'ps_pen_paper_validate_scene_state', '_pspp_folio' => 'ps_pen_paper_validate_folio_state',
		'_pspp_npc' => 'ps_pen_paper_valid_npc_state', '_pspp_loot_table' => 'ps_pen_paper_valid_loot_table',
		'_pspp_loot_run' => 'ps_pen_paper_valid_loot_run', '_pspp_trade' => 'ps_pen_paper_valid_trade',
		'_pspp_trade_policy' => 'ps_pen_paper_valid_trade_policy', '_pspp_adventure' => 'ps_pen_paper_valid_adventure_state',
		'_pspp_adventure_run' => 'ps_pen_paper_valid_adventure_run', '_pspp_currency' => 'ps_pen_paper_valid_currency',
		'_pspp_wallet' => 'ps_pen_paper_valid_wallet', '_pspp_gear' => 'ps_pen_paper_validate_gear_state',
		'_pspp_wiki_entry' => 'ps_pen_paper_valid_wiki_entry', '_pspp_wiki_config' => 'ps_pen_paper_valid_wiki_config',
		'_pspp_rules' => 'ps_pen_paper_import_rules_valid',
	);
	$primary = array(
		'pspp_scene' => '_pspp_scene', 'pspp_folio' => '_pspp_folio', 'pspp_npc' => '_pspp_npc',
		'pspp_loot_table' => '_pspp_loot_table', 'pspp_loot_run' => '_pspp_loot_run', 'pspp_trade' => '_pspp_trade',
		'pspp_adventure' => '_pspp_adventure', 'pspp_adventure_run' => '_pspp_adventure_run',
		'pspp_wiki_entry' => '_pspp_wiki_entry', 'pspp_item' => '_pspp_item', 'pspp_character' => '_pspp_character',
	);
	$conflicts = array(); $checked = 0; $deferred = array();
	foreach ( $parsed['records'] as $id => $record ) {
		$metadata = $parsed['meta'][ $id ];
		if ( isset( $primary[ $record['type'] ] ) && ! isset( $metadata[ $primary[ $record['type'] ] ] ) ) {
			/* translators: %d: source record ID. */
			$conflicts[] = sprintf( __( 'Datensatz #%d enthält kein prüfbares Fachdokument.', 'ps-pen-paper' ), $id );
		}
		foreach ( $metadata as $key => $values ) {
			$value = $values[0]; $valid = null;
			if ( isset( $validators[ $key ] ) ) {
				$result = call_user_func( $validators[ $key ], $value );
				$valid = true === $result || is_array( $result );
			} elseif ( '_pspp_item' === $key ) {
				$valid = is_array( $value ) && isset( $value['revision'], $value['item'] ) &&
					is_int( $value['revision'] ) && $value['revision'] > 0 && ps_pen_paper_validate_item( $value['item'] );
			} elseif ( '_pspp_character' === $key ) {
				$rules = ps_pen_paper_import_meta( $parsed, $record['parent_id'], '_pspp_rules' );
				$valid = ps_pen_paper_import_rules_valid( $rules ) && ps_pen_paper_import_character_valid( $value, $rules );
			}
			if ( null === $valid ) { $deferred[ $key ] = true; continue; }
			$checked++;
			if ( ! $valid ) {
				/* translators: 1: source record ID, 2: metadata document key. */
				$conflicts[] = sprintf( __( 'Fachdokument %2$s in Datensatz #%1$d ist ungültig. Es wird nichts korrigiert oder zurückgeschrieben.', 'ps-pen-paper' ), $id, $key );
			}
		}
	}
	return array( 'conflicts' => $conflicts, 'checked' => $checked, 'deferred' => array_keys( $deferred ) );
}
