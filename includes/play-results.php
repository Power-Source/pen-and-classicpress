<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_play_result_error() {
	return new WP_Error( 'invalid_play_result', __( 'Die Abschlussbelege sind unvollständig oder ungültig. Bitte informiere die Spielleitung. Es wird keine Ersatzabrechnung berechnet.', 'ps-pen-paper' ) );
}

function ps_pen_paper_play_result_default() {
	return array( 'revision' => 0, 'periods' => array() );
}

function ps_pen_paper_valid_play_result_snapshot( $value ) {
	if ( ! is_array( $value ) || array( 'name', 'values', 'items', 'wallet', 'conditions' ) !== array_keys( $value ) ||
		! is_string( $value['name'] ) || '' === trim( $value['name'] ) || strlen( $value['name'] ) > 800 ||
		! is_array( $value['values'] ) || count( $value['values'] ) > 100 ||
		! is_array( $value['items'] ) || count( $value['items'] ) > 100 ||
		! is_array( $value['conditions'] ) || count( $value['conditions'] ) > 90 ) { return false; }
	foreach ( $value['values'] as $id => $field ) {
		if ( ! is_string( $id ) || ! wp_is_uuid( $id ) || ! is_array( $field ) ||
			array( 'label', 'type', 'value', 'maximum' ) !== array_keys( $field ) ||
			! is_string( $field['label'] ) || '' === trim( $field['label'] ) || strlen( $field['label'] ) > 800 ||
			! in_array( $field['type'], array( 'number', 'attribute', 'resource' ), true ) || ! is_int( $field['value'] ) || abs( $field['value'] ) > 1000000000 ||
			( 'resource' === $field['type'] ? ( ! is_int( $field['maximum'] ) || abs( $field['maximum'] ) > 1000000000 ) : null !== $field['maximum'] ) ) { return false; }
	}
	foreach ( $value['items'] as $id => $label ) {
		if ( ! is_string( $id ) || ! wp_is_uuid( $id ) || ! is_string( $label ) || '' === trim( $label ) || strlen( $label ) > 800 ) { return false; }
	}
	foreach ( $value['conditions'] as $id => $label ) {
		$parts = explode( ':', (string) $id );
		if ( 2 !== count( $parts ) || ! wp_is_uuid( $parts[0] ) || ! in_array( $parts[1], array( 'local', 'universe', 'threshold' ), true ) ||
			! is_string( $label ) || '' === trim( $label ) || strlen( $label ) > 480 ) { return false; }
	}
	$wallet = $value['wallet'];
	return null === $wallet || ( is_array( $wallet ) && array( 'currency_id', 'mode', 'symbol', 'balance' ) === array_keys( $wallet ) &&
		is_string( $wallet['currency_id'] ) && wp_is_uuid( $wallet['currency_id'] ) &&
		in_array( $wallet['mode'], array( 'campaign', 'universe' ), true ) && is_string( $wallet['symbol'] ) &&
		'' !== trim( $wallet['symbol'] ) && strlen( $wallet['symbol'] ) <= 24 &&
		is_int( $wallet['balance'] ) && $wallet['balance'] >= 0 && $wallet['balance'] <= 999999999 );
}

function ps_pen_paper_valid_play_result( $value ) {
	if ( ! is_array( $value ) || array( 'revision', 'periods' ) !== array_keys( $value ) ||
		! is_int( $value['revision'] ) || $value['revision'] < 0 ||
		! is_array( $value['periods'] ) || array_values( $value['periods'] ) !== $value['periods'] ) { return false; }
	if ( ! $value['periods'] ) { return $value === ps_pen_paper_play_result_default(); }
	$last = count( $value['periods'] ) - 1;
	foreach ( $value['periods'] as $index => $period ) {
		if ( ! is_array( $period ) || array( 'before', 'after' ) !== array_keys( $period ) ||
			! ps_pen_paper_valid_play_result_snapshot( $period['before'] ) ||
			( null === $period['after'] ? $index !== $last : ! ps_pen_paper_valid_play_result_snapshot( $period['after'] ) ) ) { return false; }
	}
	return $value['revision'] === count( $value['periods'] ) * 2 - ( null === $value['periods'][ $last ]['after'] ? 1 : 0 );
}

function ps_pen_paper_play_result_document( $character_id ) {
	$value = get_post_meta( $character_id, '_pspp_play_result', true );
	if ( '' === $value ) { return ps_pen_paper_play_result_default(); }
	return ps_pen_paper_valid_play_result( $value ) ? $value : ps_pen_paper_play_result_error();
}

function ps_pen_paper_valid_play_result_event( $event ) {
	if ( 'owner' !== $event['visibility'] || ! ps_pen_paper_valid_play_result( $event['before'] ) ||
		! ps_pen_paper_valid_play_result( $event['after'] ) || $event['after']['revision'] !== $event['before']['revision'] + 1 ) { return false; }
	$before = $event['before']['periods']; $after = $event['after']['periods'];
	$last = count( $before ) - 1;
	if ( $last >= 0 && null === $before[ $last ]['after'] ) {
		if ( count( $after ) !== count( $before ) || null === $after[ $last ]['after'] ) { return false; }
		$before[ $last ]['after'] = $after[ $last ]['after'];
		return $before === $after;
	}
	if ( count( $after ) !== count( $before ) + 1 ) { return false; }
	$new = array_pop( $after );
	return null === $new['after'] && $before === $after;
}

function ps_pen_paper_play_result_snapshot( $round, $participant ) {
	$character = ps_pen_paper_record_post( $participant['character_id'] );
	if ( ! $character || 'pspp_character' !== $character->post_type || 'private' !== $character->post_status ||
		(int) $character->post_parent !== (int) $round->ID || (int) $character->post_author !== $participant['user_id'] ) { return ps_pen_paper_play_result_error(); }
	$state = ps_pen_paper_resolve_character_state( $round, $character, false );
	if ( is_wp_error( $state ) ) { return $state; }
	if ( ! $state['active'] ) { return ps_pen_paper_play_result_error(); }
	$result = array( 'name' => $state['active']['name'], 'values' => array(), 'items' => array(), 'wallet' => null, 'conditions' => array() );
	$rules = ps_pen_paper_get_rules( $round->ID );
	foreach ( $rules['fields'] as $field ) {
		if ( ! in_array( $field['type'], array( 'number', 'attribute', 'resource' ), true ) ) { continue; }
		$value = $state['active']['values'][ $field['id'] ];
		$result['values'][ $field['id'] ] = array( 'label' => $field['label'], 'type' => $field['type'],
			'value' => 'resource' === $field['type'] ? $value['current'] : $value,
			'maximum' => 'resource' === $field['type'] ? $value['maximum'] : null );
	}
	$gear = ps_pen_paper_gear_state( $character->ID );
	if ( is_wp_error( $gear ) ) { return $gear; }
	foreach ( $gear['active']['items'] as $id => $instance ) { $result['items'][ $id ] = $instance['item']['name']; }
	$currency_scope = ps_pen_paper_currency_scope( $round );
	if ( is_wp_error( $currency_scope ) ) { return $currency_scope; }
	$currency = ps_pen_paper_currency_document( $currency_scope->ID );
	if ( is_wp_error( $currency ) ) { return $currency; }
	if ( $currency['revision'] ) {
		$context = ps_pen_paper_resolve_wallet_context( $round, $character );
		if ( is_wp_error( $context ) ) { return $context; }
		$result['wallet'] = array( 'currency_id' => $currency['currency_id'], 'mode' => $currency['wallet_mode'],
			'symbol' => $currency['symbol'], 'balance' => $context['wallet']['balance'] );
	}
	$effective = ps_pen_paper_resolve_character_state( $round, $character );
	if ( is_wp_error( $effective ) ) { return $effective; }
	foreach ( $effective['condition_effects'] as $id => $condition ) {
		$result['conditions'][ $id . ':' . $condition['scope'] ] = $condition['definition']['label'];
		if ( ! empty( $condition['also_local'] ) ) { $result['conditions'][ $id . ':local' ] = $condition['definition']['label']; }
	}
	return ps_pen_paper_valid_play_result_snapshot( $result ) ? $result : ps_pen_paper_play_result_error();
}

// Called inside the round transaction and its character locks, never while rendering.
function ps_pen_paper_write_play_result( $round, $participant, $closing ) {
	$before = ps_pen_paper_play_result_document( $participant['character_id'] );
	if ( is_wp_error( $before ) ) { return $before; }
	$last = count( $before['periods'] ) - 1;
	$open = $last >= 0 && null === $before['periods'][ $last ]['after'];
	if ( $closing !== $open ) { return 0; }
	$snapshot = ps_pen_paper_play_result_snapshot( $round, $participant );
	if ( is_wp_error( $snapshot ) ) { return $snapshot; }
	$after = $before; $after['revision']++;
	if ( $closing ) { $after['periods'][ $last ]['after'] = $snapshot; }
	else { $after['periods'][] = array( 'before' => $snapshot, 'after' => null ); }
	return ps_pen_paper_write_document_event( $round->ID, $participant['character_id'], '_pspp_play_result', $before, $after,
		array( 'kind' => 'play_result', 'visibility' => 'owner', 'recipient_id' => $participant['user_id'], 'fields' => array(), 'rules_revision' => 0 ), false );
}

function ps_pen_paper_write_play_results( $id, $before, $after ) {
	$round = ps_pen_paper_record_post( $id );
	if ( ! $round ) { return ps_pen_paper_play_result_error(); }
	$previous = array(); $next = array();
	foreach ( $before['participants'] as $participant ) { if ( $participant['active'] ) { $previous[ $participant['character_id'] ] = $participant; } }
	foreach ( $after['participants'] as $participant ) { if ( $participant['active'] ) { $next[ $participant['character_id'] ] = $participant; } }
	foreach ( $previous as $character_id => $participant ) {
		if ( 'completed' !== $after['status'] && isset( $next[ $character_id ] ) ) { continue; }
		$written = ps_pen_paper_write_play_result( $round, $participant, true );
		if ( is_wp_error( $written ) ) { return $written; }
	}
	foreach ( $next as $character_id => $participant ) {
		if ( ! in_array( $after['status'], array( 'active', 'paused' ), true ) ||
			( isset( $previous[ $character_id ] ) && 'lobby' !== $before['status'] ) ) { continue; }
		if ( empty( $after['result_tracking'] ) && 'lobby' !== $before['status'] && in_array( $character_id, array_column( $before['participants'], 'character_id' ), true ) ) {
			$document = ps_pen_paper_play_result_document( $character_id );
			if ( is_wp_error( $document ) ) { return $document; }
			// Old attendance without a baseline must remain explicitly incomplete.
			if ( ! $document['revision'] ) { continue; }
		}
		$written = ps_pen_paper_write_play_result( $round, $participant, false );
		if ( is_wp_error( $written ) ) { return $written; }
	}
	return 0;
}

function ps_pen_paper_play_result_add( &$rows, $id, $kind, $label, $amount ) {
	if ( ! isset( $rows[ $id ] ) ) { $rows[ $id ] = array( 'kind' => $kind, 'label' => $label, 'amount' => 0 ); }
	$rows[ $id ]['amount'] += $amount;
}

function ps_pen_paper_play_result_rows( $document ) {
	if ( ! ps_pen_paper_valid_play_result( $document ) ) { return ps_pen_paper_play_result_error(); }
	$rows = array(); $notes = array(); $incomplete = array();
	foreach ( $document['periods'] as $period ) {
		if ( null === $period['after'] ) { return ps_pen_paper_play_result_error(); }
		$before = $period['before']; $after = $period['after'];
		foreach ( $after['values'] as $id => $field ) {
			$old = $before['values'][ $id ] ?? null;
			if ( ! $old || $old['type'] !== $field['type'] ) {
				$incomplete[] = 'resource' === $field['type'] ? 'resource' : 'value';
				if ( $old ) { $incomplete[] = 'resource' === $old['type'] ? 'resource' : 'value'; }
				$notes[] = sprintf( __( 'Für „%s“ fehlt ein vergleichbarer Anfangswert.', 'ps-pen-paper' ), $field['label'] ); continue;
			}
			ps_pen_paper_play_result_add( $rows, 'value:' . $field['type'] . ':' . $id, 'resource' === $field['type'] ? 'resource' : 'value', $field['label'], $field['value'] - $old['value'] );
			if ( 'resource' === $field['type'] && $old['maximum'] !== $field['maximum'] ) {
				ps_pen_paper_play_result_add( $rows, 'maximum:' . $id, 'value', sprintf( __( '%s (Maximum)', 'ps-pen-paper' ), $field['label'] ), $field['maximum'] - $old['maximum'] );
			}
		}
		foreach ( array_diff_key( $before['values'], $after['values'] ) as $field ) {
			$incomplete[] = 'resource' === $field['type'] ? 'resource' : 'value';
			$notes[] = sprintf( __( '„%s“ wird am Ende nicht mehr als vergleichbares Zahlenfeld geführt.', 'ps-pen-paper' ), $field['label'] );
		}
		foreach ( array( 'items' => 'item', 'conditions' => 'condition' ) as $key => $kind ) {
			foreach ( array( 'before' => -1, 'after' => 1 ) as $side => $sign ) {
				$other = 'before' === $side ? 'after' : 'before';
				foreach ( array_diff_key( $period[ $side ][ $key ], $period[ $other ][ $key ] ) as $uuid => $label ) {
					if ( 'condition' === $kind ) {
						$scope = explode( ':', $uuid )[1];
						$labels = array( 'local' => __( 'rundenbezogen', 'ps-pen-paper' ), 'universe' => __( 'dauerhaft', 'ps-pen-paper' ), 'threshold' => __( 'ressourcenabhängig', 'ps-pen-paper' ) );
						$label .= ' (' . $labels[ $scope ] . ')';
					}
					ps_pen_paper_play_result_add( $rows, $kind . ':' . $uuid, $kind, $label, $sign );
				}
			}
		}
		$a = $before['wallet']; $b = $after['wallet'];
		if ( $a && $b && $a['currency_id'] === $b['currency_id'] && $a['mode'] === $b['mode'] ) {
			ps_pen_paper_play_result_add( $rows, 'wallet:' . $b['currency_id'] . ':' . $b['mode'], 'money', $b['symbol'], $b['balance'] - $a['balance'] );
		} elseif ( null !== $a || null !== $b ) {
			$incomplete[] = 'money';
			$notes[] = __( 'Das Guthabenmodell hat sich während der Teilnahme geändert. Es wird kein Geldbetrag aus unvergleichbaren Konten berechnet.', 'ps-pen-paper' );
		}
	}
	return array( 'rows' => array_values( array_filter( $rows, static function ( $row ) { return 0 !== $row['amount']; } ) ),
		'notes' => array_values( array_unique( $notes ) ), 'incomplete' => array_values( array_unique( $incomplete ) ) );
}

function ps_pen_paper_play_result_history_rows( $snapshot, $history ) {
	$rows = array();
	foreach ( $history as $entry ) {
		$cost = $entry['resource_cost'] ?? null;
		if ( $cost ) { ps_pen_paper_play_result_add( $rows, 'resource:' . $cost['id'], 'resource', $cost['label'], -$cost['amount'] ); }
		if ( empty( $entry['reward'] ) ) { continue; }
		$edge = null;
		foreach ( $snapshot['edges'] as $candidate ) { if ( $candidate['id'] === $entry['edge_id'] ) { $edge = $candidate; break; } }
		if ( ! $edge ) { return ps_pen_paper_play_result_error(); }
		$reward = ps_pen_paper_scenario_reward( $snapshot, $edge, $entry['scenario_attempt'] ?? 0 );
		$components = ps_pen_paper_adventure_reward_result_components( $reward, $entry['reward'] );
		if ( is_wp_error( $components ) ) { return $components; }
		foreach ( $components['items'] as $component ) {
			ps_pen_paper_play_result_add( $rows, 'reward:' . hash( 'sha256', wp_json_encode( $component['item'] ) ), 'item', $component['item']['item']['name'], $component['quantity'] );
		}
		$wallet = $entry['reward']['wallet'];
		if ( $wallet ) {
			$money = $wallet['after'];
			ps_pen_paper_play_result_add( $rows, 'wallet:' . $money['currency_scope_id'] . ':' . $money['currency_id'], 'money',
				$money['symbol'], $money['balance'] - $wallet['before']['balance'] );
		}
	}
	return array( 'rows' => array_values( array_filter( $rows, static function ( $row ) { return 0 !== $row['amount']; } ) ), 'notes' => array(), 'incomplete' => array() );
}

function ps_pen_paper_own_play_result( $round, $state ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $round ) || 'completed' !== $state['status'] ) { return ps_pen_paper_play_result_error(); }
	$participant = null;
	foreach ( $state['participants'] as $row ) { if ( $row['user_id'] === get_current_user_id() ) { $participant = $row; break; } }
	if ( ! $participant ) { return null; }
	$character = ps_pen_paper_get_character( $round, $participant['character_id'] );
	if ( ! $character || (int) $character->post_author !== get_current_user_id() ) { return ps_pen_paper_play_result_error(); }
	$document = ps_pen_paper_play_result_document( $character->ID );
	if ( is_wp_error( $document ) ) { return $document; }
	$history = array(); $snapshot = null; $all_history = array(); $completed = false;
	if ( $state['run_id'] ) {
		$key = 'solo' === $state['mode'] ? '_pspp_adventure_run' : '_pspp_adventure_group';
		$post = ps_pen_paper_record_post( $state['run_id'] );
		$type = 'solo' === $state['mode'] ? 'pspp_adventure_run' : 'pspp_adventure_group';
		if ( ! $post || $type !== $post->post_type || 'private' !== $post->post_status || (int) $post->post_parent !== (int) $round->ID ) { return ps_pen_paper_play_result_error(); }
		$run = get_post_meta( $state['run_id'], $key, true );
		$valid = 'solo' === $state['mode'] ? ps_pen_paper_valid_adventure_run( $run ) : ps_pen_paper_valid_adventure_group( $run );
		if ( ! $valid || ! $run['revision'] ) { return ps_pen_paper_play_result_error(); }
		$completed = 'completed' === $run['status'];
		$snapshot = $run['snapshot'];
		if ( 'solo' === $state['mode'] ) {
			if ( $run['owner_id'] !== get_current_user_id() || $run['character_id'] !== $participant['character_id'] ) { return ps_pen_paper_play_result_error(); }
			$history = $run['history']; $all_history = $history;
		} else {
			foreach ( $run['history'] as $entry ) {
				$all_history[] = $entry['decision'];
				if ( $entry['owner_id'] === get_current_user_id() && $entry['character_id'] === $participant['character_id'] ) { $history[] = $entry['decision']; }
			}
		}
	}
	$result = $document['revision'] ? ps_pen_paper_play_result_rows( $document ) :
		( $snapshot ? ps_pen_paper_play_result_history_rows( $snapshot, $history ) : array( 'rows' => array(), 'notes' => array(), 'incomplete' => array() ) );
	if ( is_wp_error( $result ) ) { return $result; }
	$last = $document['periods'] ? end( $document['periods'] ) : null;
	if ( $last ) { $result['name'] = $last['after']['name']; }
	else {
		$current = ps_pen_paper_character_state( $round, $character, false );
		if ( is_wp_error( $current ) ) { return $current; }
		if ( ! $current['active'] ) { return ps_pen_paper_play_result_error(); }
		$result['name'] = $current['active']['name'];
	}
	$result['legacy'] = 0 === $document['revision'];
	$result['outcome'] = $completed ? ps_pen_paper_scenario_outcome( $snapshot, $all_history ) : null;
	return $result;
}
