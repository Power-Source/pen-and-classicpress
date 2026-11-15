<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_adventure_reward_error() {
	return new WP_Error( 'adventure_reward_unavailable', __( 'Die Abenteuerbelohnung ist ungültig oder nicht verfügbar. Prüfe Community, Vergaberechte, Inventar und Währung. Es wurden weder Belohnung noch Fortschritt gespeichert.', 'ps-pen-paper' ) );
}

function ps_pen_paper_adventure_reward_currency( $reward ) {
	return 'table' === $reward['source']['kind'] ? $reward['source']['table']['table']['currency'] : $reward['source']['currency'];
}

function ps_pen_paper_adventure_reward_rows( $reward ) {
	return 'table' === $reward['source']['kind'] ? $reward['source']['table']['table']['rows'] : array( $reward['source'] );
}

function ps_pen_paper_valid_adventure_reward( $reward ) {
	if ( null === $reward ) { return true; }
	if ( ! is_array( $reward ) || array( 'timing', 'authorizer_id', 'source' ) !== array_keys( $reward ) ||
		! in_array( $reward['timing'], array( 'decision', 'success' ), true ) || ! is_int( $reward['authorizer_id'] ) || $reward['authorizer_id'] < 1 ||
		! is_array( $reward['source'] ) || ! isset( $reward['source']['kind'] ) ) { return false; }
	$source = $reward['source'];
	if ( 'table' === $source['kind'] ) {
		return array( 'kind', 'id', 'table' ) === array_keys( $source ) && is_int( $source['id'] ) && $source['id'] > 0 &&
			ps_pen_paper_valid_loot_table( $source['table'] ) && $source['table']['revision'] > 0;
	}
	if ( 'fixed' !== $source['kind'] || array( 'kind', 'item', 'quantity', 'money', 'currency' ) !== array_keys( $source ) ||
		! is_string( $source['quantity'] ) || ! preg_match( '/^(0|[1-9]\d{0,2})$/D', $source['quantity'] ) ||
		! is_string( $source['money'] ) || ! preg_match( '/^(0|[1-9]\d{0,8})$/D', $source['money'] ) ||
		is_wp_error( ps_pen_paper_loot_spec( $source['quantity'], 100, null === $source['item'] ) ) ||
		( null === $source['item'] ? '0' !== $source['quantity'] : ! ps_pen_paper_valid_item_instance( $source['item'] ) ) ||
		( null === $source['item'] && '0' === $source['money'] ) ) { return false; }
	$currency = $source['currency'];
	return '0' === $source['money'] ? null === $currency : ( is_array( $currency ) &&
		array( 'scope_id', 'document' ) === array_keys( $currency ) && is_int( $currency['scope_id'] ) && $currency['scope_id'] > 0 &&
		ps_pen_paper_valid_currency( $currency['document'] ) && $currency['document']['revision'] > 0 );
}

function ps_pen_paper_adventure_reward_authority( $campaign, $reward ) {
	$id = $reward['authorizer_id'];
	$admin = user_can( $id, 'manage_options' );
	if ( ! user_can( $id, 'read' ) || ! ( ps_pen_paper_site_user( $id ) || ( is_multisite() && is_super_admin( $id ) ) ) ||
		! ( $admin || 'gm' === ps_pen_paper_campaign_role( $campaign, $id ) ) ) { return ps_pen_paper_adventure_reward_error(); }
	$currency = ps_pen_paper_adventure_reward_currency( $reward );
	if ( $currency ) {
		$scope = ps_pen_paper_currency_scope( $campaign );
		if ( is_wp_error( $scope ) ) { return $scope; }
		$current = ps_pen_paper_currency_document( $scope->ID );
		if ( is_wp_error( $current ) ) { return $current; }
		if ( (int) $scope->ID !== $currency['scope_id'] || $current['currency_id'] !== $currency['document']['currency_id'] ||
			$current['wallet_mode'] !== $currency['document']['wallet_mode'] ||
			( 'universe' === $current['wallet_mode'] && ! ( $admin || (int) $scope->post_author === $id ) ) ) {
			return ps_pen_paper_adventure_reward_error();
		}
	}
	return true;
}

function ps_pen_paper_prepare_adventure_reward( $campaign, $input ) {
	if ( ! is_array( $input ) ) { return ps_pen_paper_adventure_reward_error(); }
	foreach ( array( 'kind', 'timing', 'table_id', 'item_id', 'quantity', 'money' ) as $key ) {
		if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) { return ps_pen_paper_adventure_reward_error(); }
	}
	if ( ! in_array( $input['kind'], array( 'none', 'fixed', 'table' ), true ) ||
		! in_array( $input['timing'], array( 'decision', 'success' ), true ) ) { return ps_pen_paper_adventure_reward_error(); }
	foreach ( array( 'table_id', 'item_id', 'quantity', 'money' ) as $key ) {
		if ( ! preg_match( '/^(0|[1-9]\d{0,10})$/D', $input[ $key ] ) ) { return ps_pen_paper_adventure_reward_error(); }
	}
	if ( 'none' === $input['kind'] ) {
		return '0' === $input['table_id'] && '0' === $input['item_id'] && '0' === $input['quantity'] && '0' === $input['money'] ?
			null : ps_pen_paper_adventure_reward_error();
	}
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( 'table' === $input['kind'] ) {
		if ( '0' !== $input['item_id'] || '0' !== $input['quantity'] || '0' !== $input['money'] ) { return ps_pen_paper_adventure_reward_error(); }
		$table = ps_pen_paper_get_loot_table( $campaign, (int) $input['table_id'] );
		if ( is_wp_error( $table ) ) { return $table; }
		$source = array( 'kind' => 'table', 'id' => (int) $input['table_id'], 'table' => $table );
	} else {
		if ( '0' !== $input['table_id'] ) { return ps_pen_paper_adventure_reward_error(); }
		$item = null;
		if ( '0' !== $input['item_id'] ) {
			$catalogue = ps_pen_paper_item_catalogue( $campaign );
			if ( is_wp_error( $catalogue ) ) { return $catalogue; }
			if ( ! isset( $catalogue[ (int) $input['item_id'] ] ) ) { return ps_pen_paper_adventure_reward_error(); }
			$template = $catalogue[ (int) $input['item_id'] ];
			$item = array( 'source_id' => (int) $input['item_id'], 'scope_id' => $template['scope_id'], 'revision' => $template['revision'], 'item' => $template['item'] );
			$item['item']['binding'] = ps_pen_paper_item_binding( $item['item'] );
		}
		$currency = null;
		if ( '0' !== $input['money'] ) {
			$scope = ps_pen_paper_currency_scope( $campaign );
			if ( is_wp_error( $scope ) ) { return $scope; }
			$document = ps_pen_paper_currency_document( $scope->ID );
			if ( is_wp_error( $document ) ) { return $document; }
			$currency = array( 'scope_id' => (int) $scope->ID, 'document' => $document );
		}
		$source = array( 'kind' => 'fixed', 'item' => $item, 'quantity' => $input['quantity'], 'money' => $input['money'], 'currency' => $currency );
	}
	$reward = array( 'timing' => $input['timing'], 'authorizer_id' => get_current_user_id(), 'source' => $source );
	if ( ! ps_pen_paper_valid_adventure_reward( $reward ) ) { return ps_pen_paper_adventure_reward_error(); }
	$authority = ps_pen_paper_adventure_reward_authority( $campaign, $reward );
	return is_wp_error( $authority ) ? $authority : $reward;
}

function ps_pen_paper_adventure_reward_context( $campaign, $character, $reward ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	$authority = ps_pen_paper_adventure_reward_authority( $campaign, $reward );
	if ( is_wp_error( $authority ) ) { return $authority; }
	$gear = ps_pen_paper_gear_state( $character->ID );
	if ( is_wp_error( $gear ) ) { return $gear; }
	$currency = ps_pen_paper_adventure_reward_currency( $reward );
	$wallet = $currency ? ps_pen_paper_wallet_context( $campaign, (int) $character->ID ) : null;
	if ( is_wp_error( $wallet ) ) { return $wallet; }
	// Reserve enough capacity for every possible result, before any random roll.
	foreach ( ps_pen_paper_adventure_reward_rows( $reward ) as $row ) {
		foreach ( array( 'quantity' => 100, 'money' => 999999999 ) as $key => $limit ) {
			$parsed = ps_pen_paper_loot_spec( $row[ $key ], $limit, 'money' === $key || null === $row['item'] );
			if ( is_wp_error( $parsed ) ) { return $parsed; }
			$maximum = $parsed['modifier'];
			foreach ( $parsed['groups'] as $group ) { $maximum += $group['count'] * $group['sides']; }
			if ( 'quantity' === $key && ( count( $gear['active']['items'] ) + $maximum > 100 ||
				( $gear['pending'] && count( $gear['pending']['items'] ) + $maximum > 100 ) ) ) {
				return new WP_Error( 'inventory_full', __( 'Für alle möglichen Abenteuerbelohnungen muss im aktiven und beantragten Inventar genügend Platz sein. Es wurde noch nicht gewürfelt.', 'ps-pen-paper' ) );
			}
			if ( 'money' === $key && $maximum > 0 && ( ! $wallet || 999999999 - $wallet['wallet']['balance'] < $maximum ) ) {
				return new WP_Error( 'balance_range', __( 'Das Guthaben muss jede mögliche Abenteuerbelohnung aufnehmen können. Es wurde noch nicht gewürfelt.', 'ps-pen-paper' ) );
			}
		}
	}
	return array( 'gear' => $gear, 'wallet' => $wallet );
}

function ps_pen_paper_roll_adventure_reward( $reward ) {
	$roll = null; $index = 0; $rows = ps_pen_paper_adventure_reward_rows( $reward );
	if ( 'table' === $reward['source']['kind'] ) {
		$parsed = ps_pen_paper_loot_spec( 'W' . $reward['source']['table']['table']['die'], 100, false );
		if ( is_wp_error( $parsed ) ) { return $parsed; }
		$roll = ps_pen_paper_evaluate_dice( $parsed );
		foreach ( $rows as $index => $row ) { if ( $roll['total'] >= $row['from'] && $roll['total'] <= $row['to'] ) { break; } }
	}
	$row = $rows[ $index ];
	$quantity = ps_pen_paper_loot_spec( $row['quantity'], 100, null === $row['item'] );
	$money = ps_pen_paper_loot_spec( $row['money'], 999999999 );
	if ( is_wp_error( $quantity ) || is_wp_error( $money ) ) { return ps_pen_paper_adventure_reward_error(); }
	return array( 'roll' => $roll, 'row' => $index, 'quantity_roll' => ps_pen_paper_evaluate_dice( $quantity ),
		'money_roll' => ps_pen_paper_evaluate_dice( $money ), 'item_ids' => array(), 'wallet' => null );
}

function ps_pen_paper_valid_adventure_reward_result( $reward, $result, $character_id ) {
	if ( ! is_array( $result ) || array( 'roll', 'row', 'quantity_roll', 'money_roll', 'item_ids', 'wallet' ) !== array_keys( $result ) ||
		! is_int( $result['row'] ) || ! is_array( $result['item_ids'] ) || array_values( $result['item_ids'] ) !== $result['item_ids'] ) { return false; }
	$rows = ps_pen_paper_adventure_reward_rows( $reward );
	if ( ! isset( $rows[ $result['row'] ] ) ) { return false; }
	$row = $rows[ $result['row'] ];
	if ( 'table' === $reward['source']['kind'] ) {
		if ( ! ps_pen_paper_valid_loot_roll( $result['roll'], ps_pen_paper_loot_spec( 'W' . $reward['source']['table']['table']['die'], 100, false ) ) ||
			$result['roll']['total'] < $row['from'] || $result['roll']['total'] > $row['to'] ) { return false; }
	} elseif ( null !== $result['roll'] || 0 !== $result['row'] ) { return false; }
	if ( ! ps_pen_paper_valid_loot_roll( $result['quantity_roll'], ps_pen_paper_loot_spec( $row['quantity'], 100, null === $row['item'] ) ) ||
		! ps_pen_paper_valid_loot_roll( $result['money_roll'], ps_pen_paper_loot_spec( $row['money'], 999999999 ) ) ||
		count( $result['item_ids'] ) !== $result['quantity_roll']['total'] || count( array_unique( $result['item_ids'], SORT_REGULAR ) ) !== count( $result['item_ids'] ) ) { return false; }
	foreach ( $result['item_ids'] as $id ) { if ( ! is_string( $id ) || ! wp_is_uuid( $id ) ) { return false; } }
	$money = $result['money_roll']['total'];
	if ( 0 === $money ) { return null === $result['wallet']; }
	$wallet = $result['wallet']; $currency = ps_pen_paper_adventure_reward_currency( $reward );
	return $currency && is_array( $wallet ) && array( 'character_id', 'before', 'after' ) === array_keys( $wallet ) &&
		is_int( $wallet['character_id'] ) && $wallet['character_id'] > 0 &&
		( 'universe' === $currency['document']['wallet_mode'] || $wallet['character_id'] === $character_id ) &&
		ps_pen_paper_valid_wallet( $wallet['before'] ) && ps_pen_paper_valid_wallet( $wallet['after'] ) &&
		$wallet['before']['currency_id'] === $currency['document']['currency_id'] && $wallet['after']['currency_id'] === $wallet['before']['currency_id'] &&
		$wallet['before']['currency_scope_id'] === $currency['scope_id'] && $wallet['after']['currency_scope_id'] === $currency['scope_id'] &&
		$wallet['after']['revision'] === $wallet['before']['revision'] + 1 && $wallet['after']['balance'] === $wallet['before']['balance'] + $money;
}

// The caller holds the run, character and currency locks in the progress transaction.
function ps_pen_paper_write_adventure_reward( $campaign, $character, $reward, $context, $run_id ) {
	if ( (int) $character->post_author !== get_current_user_id() || ! ps_pen_paper_get_character( $campaign, $character->ID ) ) { return ps_pen_paper_adventure_reward_error(); }
	$current = ps_pen_paper_adventure_reward_context( $campaign, $character, $reward );
	if ( is_wp_error( $current ) ) { return $current; }
	if ( $current['gear'] !== $context['gear'] || ( null === $current['wallet'] ) !== ( null === $context['wallet'] ) ) { return ps_pen_paper_adventure_reward_error(); }
	if ( $current['wallet'] && ( $current['wallet']['wallet'] !== $context['wallet']['wallet'] ||
		$current['wallet']['currency'] !== $context['wallet']['currency'] ||
		(int) $current['wallet']['character']->ID !== (int) $context['wallet']['character']->ID ||
		(int) $current['wallet']['scope']->ID !== (int) $context['wallet']['scope']->ID ||
		(int) $current['wallet']['currency_scope']->ID !== (int) $context['wallet']['currency_scope']->ID ) ) { return ps_pen_paper_adventure_reward_error(); }
	$result = ps_pen_paper_roll_adventure_reward( $reward );
	if ( is_wp_error( $result ) ) { return $result; }
	$row = ps_pen_paper_adventure_reward_rows( $reward )[ $result['row'] ];
	if ( $result['money_roll']['total'] > 0 ) {
		$wallet = $context['wallet'];
		$written = ps_pen_paper_write_wallet_delta( $wallet, 'grant', $result['money_roll']['total'],
			/* translators: %d: private adventure run ID. */
			sprintf( __( 'Freigegebene Abenteuerbelohnung für Durchlauf #%d', 'ps-pen-paper' ), $run_id ) );
		if ( is_wp_error( $written ) ) { return $written; }
		$result['wallet'] = array( 'character_id' => (int) $wallet['character']->ID, 'before' => $wallet['wallet'],
			'after' => get_post_meta( $wallet['character']->ID, '_pspp_wallet', true ) );
	}
	if ( $result['quantity_roll']['total'] > 0 ) {
		$written = ps_pen_paper_write_item_award_delta( $campaign, $character, $row['item'], $result['quantity_roll']['total'], (string) $context['gear']['revision'] );
		if ( is_wp_error( $written ) ) { return $written; }
		$after = ps_pen_paper_gear_state( $character->ID );
		if ( is_wp_error( $after ) ) { return $after; }
		$result['item_ids'] = array_values( array_diff( array_keys( $after['active']['items'] ), array_keys( $context['gear']['active']['items'] ) ) );
	}
	return ps_pen_paper_valid_adventure_reward_result( $reward, $result, (int) $character->ID ) ? $result : ps_pen_paper_adventure_reward_error();
}
