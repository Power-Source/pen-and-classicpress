<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_register_loot_types() {
	foreach ( array( 'pspp_loot_table', 'pspp_loot_run' ) as $type ) {
		register_post_type( $type, array( 'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
			'show_ui' => false, 'show_in_rest' => false, 'supports' => false, 'capability_type' => array( $type, $type . 's' ), 'map_meta_cap' => true ) );
	}
}

function ps_pen_paper_loot_error() {
	return new WP_Error( 'invalid_loot', __( 'Die Beutedaten sind ungültig, wurden geändert oder sind nicht verfügbar. Bitte lade den aktuellen Stand neu.', 'ps-pen-paper' ) );
}

function ps_pen_paper_can_manage_loot( $campaign ) {
	return ps_pen_paper_can_access_site() && $campaign && 'pspp_campaign' === $campaign->post_type &&
		'private' === $campaign->post_status && ps_pen_paper_can_manage_campaign( $campaign );
}

function ps_pen_paper_loot_spec( $expression, $limit, $zero = true, $dice = null ) {
	if ( ! is_string( $expression ) ) { return ps_pen_paper_loot_error(); }
	if ( preg_match( '/^(0|[1-9]\d{0,8})$/D', $expression ) ) {
		$parsed = array( 'expression' => $expression, 'groups' => array(), 'modifier' => (int) $expression );
	} else {
		$parsed = ps_pen_paper_parse_dice( $expression, null === $dice ? array( '4', '6', '8', '10', '12', '20', '100' ) : $dice );
		if ( is_wp_error( $parsed ) ) { return $parsed; }
	}
	$min = $parsed['modifier']; $max = $min;
	foreach ( $parsed['groups'] as $group ) { $min += $group['count']; $max += $group['count'] * $group['sides']; }
	return $min < ( $zero ? 0 : 1 ) || $max > $limit ? ps_pen_paper_loot_error() : $parsed;
}

function ps_pen_paper_valid_loot_item( $item ) {
	return ps_pen_paper_valid_item_instance( $item );
}

function ps_pen_paper_valid_loot_table( $state ) {
	if ( ! is_array( $state ) || ! isset( $state['revision'] ) || ! is_int( $state['revision'] ) || $state['revision'] < 0 || ! array_key_exists( 'table', $state ) ) { return false; }
	if ( 0 === $state['revision'] ) { return null === $state['table']; }
	$table = $state['table'];
	if ( ! is_array( $table ) || ! isset( $table['name'], $table['die'], $table['rows'] ) || ! array_key_exists( 'currency', $table ) ||
		! is_string( $table['name'] ) || '' === trim( $table['name'] ) || strlen( $table['name'] ) > 200 ||
		! in_array( $table['die'], array( '4', '6', '8', '10', '12', '20', '100' ), true ) || ! is_array( $table['rows'] ) ||
		! $table['rows'] || count( $table['rows'] ) > 30 || array_values( $table['rows'] ) !== $table['rows'] ) { return false; }
	if ( null !== $table['currency'] && ( ! is_array( $table['currency'] ) || ! isset( $table['currency']['scope_id'], $table['currency']['document'] ) ||
		! is_int( $table['currency']['scope_id'] ) || $table['currency']['scope_id'] < 1 || ! ps_pen_paper_valid_currency( $table['currency']['document'] ) || $table['currency']['document']['revision'] < 1 ) ) { return false; }
	$next = 1;
	foreach ( $table['rows'] as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['from'], $row['to'], $row['label'], $row['quantity'], $row['money'] ) || ! array_key_exists( 'item', $row ) ||
			! is_int( $row['from'] ) || ! is_int( $row['to'] ) || $row['from'] !== $next || $row['to'] < $row['from'] || $row['to'] > (int) $table['die'] ||
			! is_string( $row['label'] ) || '' === trim( $row['label'] ) || strlen( $row['label'] ) > 200 ||
			is_wp_error( ps_pen_paper_loot_spec( $row['quantity'], 100, null === $row['item'] ) ) ||
			is_wp_error( ps_pen_paper_loot_spec( $row['money'], 999999999 ) ) ||
			( null === $row['item'] ? '0' !== $row['quantity'] : ! ps_pen_paper_valid_loot_item( $row['item'] ) ) ||
			( '0' !== $row['money'] && null === $table['currency'] ) ) { return false; }
		$next = $row['to'] + 1;
	}
	return $next === (int) $table['die'] + 1;
}

function ps_pen_paper_get_loot_table( $campaign, $id ) {
	if ( ! ps_pen_paper_can_manage_loot( $campaign ) ) { return ps_pen_paper_loot_error(); }
	$post = ps_pen_paper_record_post( $id ); $state = get_post_meta( $id, '_pspp_loot_table', true );
	return $post && 'pspp_loot_table' === $post->post_type && 'private' === $post->post_status && (int) $post->post_parent === (int) $campaign->ID &&
		ps_pen_paper_valid_loot_table( $state ) && $state['revision'] > 0 ? $state : ps_pen_paper_loot_error();
}

function ps_pen_paper_loot_tables( $campaign ) {
	if ( ! ps_pen_paper_can_manage_loot( $campaign ) ) { return ps_pen_paper_loot_error(); }
	$tables = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_loot_table', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
		$state = ps_pen_paper_get_loot_table( $campaign, $post->ID );
		if ( is_wp_error( $state ) ) { return $state; }
		$tables[ $post->ID ] = $state;
	}
	return $tables;
}

function ps_pen_paper_write_loot( $campaign, &$id, $type, $before, $after, $kind ) {
	$new = ! $id;
	if ( $new ) {
		$id = ps_pen_paper_insert_record( 'pspp_loot_' . $type, $campaign->ID, array(), false );
		if ( is_wp_error( $id ) ) { return $id; }
	}
	return ps_pen_paper_write_document_event( $campaign->ID, $id, '_pspp_loot_' . $type, $before, $after,
		array( 'kind' => $kind, 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $campaign->ID )['revision'] ), $new );
}

function ps_pen_paper_save_loot_table( $campaign, $id, $input, $revision ) {
	return ps_pen_paper_scope_write( $campaign, function () use ( $campaign, $id, $input, $revision ) {
		return ps_pen_paper_save_loot_table_locked( $campaign, $id, $input, $revision );
	} );
}

function ps_pen_paper_save_loot_table_locked( $campaign, $id, $input, $revision ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( ! ps_pen_paper_can_manage_loot( $campaign ) || ! is_array( $input ) || ! isset( $input['name'], $input['die'], $input['rows'] ) ||
		! is_string( $input['name'] ) || ! is_string( $input['die'] ) || ! is_array( $input['rows'] ) || count( $input['rows'] ) > 30 ) { return ps_pen_paper_loot_error(); }
	$result = ps_pen_paper_atomic_event( $campaign->ID, function () use ( $campaign, &$id, $input, $revision ) {
		$campaign = ps_pen_paper_get_campaign( $campaign->ID );
		if ( ! ps_pen_paper_can_manage_loot( $campaign ) ) { return ps_pen_paper_loot_error(); }
		if ( $id ) { clean_post_cache( $id ); }
		$before = $id ? ps_pen_paper_get_loot_table( $campaign, $id ) : array( 'revision' => 0, 'table' => null );
		if ( is_wp_error( $before ) || ! is_string( $revision ) || (string) $before['revision'] !== $revision ) { return ps_pen_paper_loot_error(); }
		$rules = ps_pen_paper_get_rules( $campaign->ID );
		if ( is_wp_error( ps_pen_paper_parse_dice( 'W' . $input['die'], $rules['dice'] ) ) ) { return ps_pen_paper_loot_error(); }
		$catalogue = ps_pen_paper_item_catalogue( $campaign );
		if ( is_wp_error( $catalogue ) ) { return $catalogue; }
		$table = array( 'name' => sanitize_text_field( $input['name'] ), 'die' => $input['die'], 'rows' => array(), 'currency' => null );
		foreach ( $input['rows'] as $row ) {
			foreach ( array( 'from', 'to', 'label', 'item_id', 'quantity', 'money' ) as $key ) { if ( ! isset( $row[ $key ] ) || ! is_string( $row[ $key ] ) ) { return ps_pen_paper_loot_error(); } }
			if ( '' === $row['from'] && '' === $row['to'] && '' === trim( $row['label'] ) && '0' === $row['item_id'] && '0' === $row['quantity'] && '0' === $row['money'] ) { continue; }
			if ( ! preg_match( '/^[1-9]\d{0,2}$/D', $row['from'] ) || ! preg_match( '/^[1-9]\d{0,2}$/D', $row['to'] ) || ! preg_match( '/^(0|[1-9]\d{0,10})$/D', $row['item_id'] ) ) { return ps_pen_paper_loot_error(); }
			$item = null; $item_id = (int) $row['item_id'];
			if ( $item_id ) {
				if ( ! isset( $catalogue[ $item_id ] ) ) { return ps_pen_paper_loot_error(); }
				$source = $catalogue[ $item_id ];
				$item = array( 'source_id' => $item_id, 'scope_id' => $source['scope_id'], 'revision' => $source['revision'], 'item' => $source['item'] );
				$item['item']['binding'] = ps_pen_paper_item_binding( $item['item'] );
			}
			$quantity = ps_pen_paper_loot_spec( $row['quantity'], 100, null === $item, $rules['dice'] );
			$money = ps_pen_paper_loot_spec( $row['money'], 999999999, true, $rules['dice'] );
			if ( is_wp_error( $quantity ) || is_wp_error( $money ) ) { return ps_pen_paper_loot_error(); }
			$table['rows'][] = array( 'from' => (int) $row['from'], 'to' => (int) $row['to'], 'label' => sanitize_text_field( $row['label'] ), 'item' => $item, 'quantity' => $quantity['expression'], 'money' => $money['expression'] );
			if ( '0' !== $money['expression'] ) {
				$scope = ps_pen_paper_currency_scope( $campaign );
				$currency = is_wp_error( $scope ) ? $scope : ps_pen_paper_currency_document( $scope->ID );
				if ( is_wp_error( $currency ) || ! $currency['revision'] ) { return new WP_Error( 'currency_missing', __( 'Für Geldbeute muss zuerst eine Währung eingerichtet sein.', 'ps-pen-paper' ) ); }
				$table['currency'] = array( 'scope_id' => (int) $scope->ID, 'document' => $currency );
			}
		}
		usort( $table['rows'], function ( $a, $b ) { return $a['from'] <=> $b['from']; } );
		$after = array( 'revision' => $before['revision'] + 1, 'table' => $table );
		if ( ! ps_pen_paper_valid_loot_table( $after ) ) { return new WP_Error( 'loot_ranges', __( 'Die Bereiche müssen jeden Würfelwert genau einmal abdecken: keine Lücken oder Überschneidungen. Prüfe Bezeichnungen, Mengen und Beträge.', 'ps-pen-paper' ) ); }
		return ps_pen_paper_write_loot( $campaign, $id, 'table', $before, $after, 'loot_table_saved' );
	} );
	if ( is_int( $id ) && $id ) { clean_post_cache( $id ); }
	return is_wp_error( $result ) ? $result : $id;
}

function ps_pen_paper_valid_loot_roll( $roll, $parsed ) {
	if ( is_wp_error( $parsed ) || ! is_array( $roll ) || ! isset( $roll['expression'], $roll['modifier'], $roll['groups'], $roll['total'] ) ||
		$roll['expression'] !== $parsed['expression'] || $roll['modifier'] !== $parsed['modifier'] || ! is_int( $roll['total'] ) ||
		! is_array( $roll['groups'] ) || count( $roll['groups'] ) !== count( $parsed['groups'] ) ) { return false; }
	$total = $parsed['modifier'];
	foreach ( $parsed['groups'] as $i => $group ) {
		if ( ! isset( $roll['groups'][ $i ]['sides'], $roll['groups'][ $i ]['rolls'] ) || $roll['groups'][ $i ]['sides'] !== $group['sides'] ||
			! is_array( $roll['groups'][ $i ]['rolls'] ) || count( $roll['groups'][ $i ]['rolls'] ) !== $group['count'] ) { return false; }
		foreach ( $roll['groups'][ $i ]['rolls'] as $value ) { if ( ! is_int( $value ) || $value < 1 || $value > $group['sides'] ) { return false; } $total += $value; }
	}
	return $total === $roll['total'];
}

function ps_pen_paper_valid_loot_run( $state ) {
	if ( ! is_array( $state ) || ! isset( $state['revision'] ) || ! array_key_exists( 'proposal', $state ) || ! array_key_exists( 'award', $state ) ) { return false; }
	if ( 0 === $state['revision'] ) { return null === $state['proposal'] && null === $state['award']; }
	if ( ! in_array( $state['revision'], array( 1, 2 ), true ) ) { return false; }
	$p = $state['proposal'];
	if ( ! is_array( $p ) || ! isset( $p['token'], $p['actor_id'], $p['reason'], $p['table_id'], $p['table'], $p['rules_revision'], $p['roll'], $p['row'], $p['quantity_roll'], $p['money_roll'] ) ||
		! is_string( $p['token'] ) || ! wp_is_uuid( $p['token'] ) || ! is_int( $p['actor_id'] ) || $p['actor_id'] < 1 ||
		! is_string( $p['reason'] ) || '' === trim( $p['reason'] ) || strlen( $p['reason'] ) > 1000 || ! is_int( $p['table_id'] ) || $p['table_id'] < 1 ||
		! is_int( $p['rules_revision'] ) || $p['rules_revision'] < 1 || ! ps_pen_paper_valid_loot_table( $p['table'] ) || $p['table']['revision'] < 1 ||
		! is_int( $p['row'] ) || ! isset( $p['table']['table']['rows'][ $p['row'] ] ) ) { return false; }
	$table = $p['table']['table']; $row = $table['rows'][ $p['row'] ];
	if ( ! ps_pen_paper_valid_loot_roll( $p['roll'], ps_pen_paper_loot_spec( 'W' . $table['die'], 100, false ) ) ||
		$p['roll']['total'] < $row['from'] || $p['roll']['total'] > $row['to'] ||
		! ps_pen_paper_valid_loot_roll( $p['quantity_roll'], ps_pen_paper_loot_spec( $row['quantity'], 100, null === $row['item'] ) ) ||
		! ps_pen_paper_valid_loot_roll( $p['money_roll'], ps_pen_paper_loot_spec( $row['money'], 999999999 ) ) ) { return false; }
	if ( null === $state['award'] ) { return 1 === $state['revision']; }
	$a = $state['award'];
	return 2 === $state['revision'] && is_array( $a ) && isset( $a['character_id'], $a['character_name'], $a['actor_id'] ) &&
		is_int( $a['character_id'] ) && $a['character_id'] >= 0 && is_string( $a['character_name'] ) && is_int( $a['actor_id'] ) && $a['actor_id'] > 0 &&
		( $a['character_id'] > 0 || ( 0 === $p['money_roll']['total'] && 0 === $p['quantity_roll']['total'] ) );
}

function ps_pen_paper_get_loot_run( $campaign, $id ) {
	if ( ! ps_pen_paper_can_manage_loot( $campaign ) ) { return ps_pen_paper_loot_error(); }
	$post = ps_pen_paper_record_post( $id ); $state = get_post_meta( $id, '_pspp_loot_run', true );
	return $post && 'pspp_loot_run' === $post->post_type && 'private' === $post->post_status && (int) $post->post_parent === (int) $campaign->ID &&
		ps_pen_paper_valid_loot_run( $state ) && $state['revision'] > 0 ? $state : ps_pen_paper_loot_error();
}

function ps_pen_paper_roll_loot( $campaign, $table_id, $revision, $rules_revision, $reason, $token ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( ! ps_pen_paper_can_manage_loot( $campaign ) || ! is_string( $reason ) || '' === trim( sanitize_textarea_field( $reason ) ) || strlen( $reason ) > 1000 ||
		! is_string( $token ) || ! wp_is_uuid( $token ) ) { return ps_pen_paper_loot_error(); }
	$id = 0;
	$result = ps_pen_paper_atomic_event( $campaign->ID, function () use ( $campaign, $table_id, $revision, $rules_revision, $reason, $token, &$id ) {
		$campaign = ps_pen_paper_get_campaign( $campaign->ID );
		if ( ! ps_pen_paper_can_manage_loot( $campaign ) ) { return ps_pen_paper_loot_error(); }
		foreach ( get_posts( array( 'post_type' => 'pspp_loot_run', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1 ) ) as $post ) {
			$existing = ps_pen_paper_get_loot_run( $campaign, $post->ID );
			if ( is_wp_error( $existing ) ) { return $existing; }
			$p = $existing['proposal'];
			if ( $p['token'] !== $token ) { continue; }
			if ( $p['actor_id'] !== get_current_user_id() || $p['table_id'] !== $table_id || (string) $p['table']['revision'] !== $revision || (string) $p['rules_revision'] !== $rules_revision || $p['reason'] !== sanitize_textarea_field( $reason ) ) { return ps_pen_paper_loot_error(); }
			$id = $post->ID; return 0;
		}
		clean_post_cache( $table_id );
		$table = ps_pen_paper_get_loot_table( $campaign, $table_id ); $rules = ps_pen_paper_get_rules( $campaign->ID );
		if ( is_wp_error( $table ) || ! is_string( $revision ) || (string) $table['revision'] !== $revision || ! is_string( $rules_revision ) || (string) $rules['revision'] !== $rules_revision ) { return ps_pen_paper_loot_error(); }
		$parsed = ps_pen_paper_parse_dice( 'W' . $table['table']['die'], $rules['dice'] );
		if ( is_wp_error( $parsed ) ) { return $parsed; }
		$roll = ps_pen_paper_evaluate_dice( $parsed );
		foreach ( $table['table']['rows'] as $index => $row ) { if ( $roll['total'] >= $row['from'] && $roll['total'] <= $row['to'] ) { break; } }
		$quantity = ps_pen_paper_loot_spec( $row['quantity'], 100, null === $row['item'], $rules['dice'] );
		$money = ps_pen_paper_loot_spec( $row['money'], 999999999, true, $rules['dice'] );
		if ( is_wp_error( $quantity ) || is_wp_error( $money ) ) { return ps_pen_paper_loot_error(); }
		$after = array( 'revision' => 1, 'award' => null, 'proposal' => array(
			'token' => $token, 'actor_id' => get_current_user_id(), 'reason' => sanitize_textarea_field( $reason ), 'table_id' => $table_id, 'table' => $table,
			'rules_revision' => $rules['revision'], 'roll' => $roll, 'row' => $index,
			'quantity_roll' => ps_pen_paper_evaluate_dice( $quantity ), 'money_roll' => ps_pen_paper_evaluate_dice( $money ),
		) );
		return ps_pen_paper_write_loot( $campaign, $id, 'run', array( 'revision' => 0, 'proposal' => null, 'award' => null ), $after, 'loot_rolled' );
	} );
	if ( is_int( $id ) && $id ) { clean_post_cache( $id ); }
	return is_wp_error( $result ) ? $result : $id;
}

function ps_pen_paper_award_loot( $campaign, $id, $character_id, $gear_revision, $wallet_revision, $currency_revision, $confirmation ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( ! ps_pen_paper_can_manage_loot( $campaign ) || 'confirmed' !== $confirmation ) { return ps_pen_paper_loot_error(); }
	$run = ps_pen_paper_get_loot_run( $campaign, $id );
	if ( is_wp_error( $run ) ) { return $run; }
	$has_money = $run['proposal']['money_roll']['total'] > 0;
	$has_items = $run['proposal']['quantity_roll']['total'] > 0;
	$current = $has_money ? ps_pen_paper_wallet_context( $campaign, $character_id ) : null;
	if ( is_wp_error( $current ) ) { return $current; }
	$target_id = $has_money ? (int) $current['character']->ID : $character_id;
	$scope_id = $has_money ? (int) $current['currency_scope']->ID : (int) $campaign->ID;
	$write = function () use ( $campaign, $id, $character_id, $gear_revision, $wallet_revision, $currency_revision, $target_id, $scope_id, $has_money, $has_items ) {
		try {
			return ps_pen_paper_atomic_event( $scope_id, function () use ( $campaign, $id, $character_id, $gear_revision, $wallet_revision, $currency_revision, $target_id, $scope_id, $has_money, $has_items ) {
				global $wpdb;
				foreach ( array_unique( array( (int) $campaign->ID, $character_id, $target_id, $id ) ) as $lock_id ) {
					if ( ! $lock_id || $lock_id === $scope_id ) { continue; }
					if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $lock_id ) ) !== $lock_id ) { return ps_pen_paper_loot_error(); }
					clean_post_cache( $lock_id );
				}
				$campaign = ps_pen_paper_get_campaign( $campaign->ID );
				if ( ! ps_pen_paper_can_manage_loot( $campaign ) ) { return ps_pen_paper_loot_error(); }
				$before = ps_pen_paper_get_loot_run( $campaign, $id );
				if ( is_wp_error( $before ) || null !== $before['award'] ) { return new WP_Error( 'loot_awarded', __( 'Dieser Beutevorgang ist bereits abgeschlossen oder nicht verfügbar. Es wird nichts erneut vergeben.', 'ps-pen-paper' ) ); }
				$p = $before['proposal']; $row = $p['table']['table']['rows'][ $p['row'] ];
				$character = $character_id ? ps_pen_paper_get_character( $campaign, $character_id ) : null;
				$state = $character ? ps_pen_paper_character_state( $campaign, $character, false ) : null;
				if ( ( $has_money || $has_items ) && ( ! $character || is_wp_error( $state ) || ! $state['active'] ||
					! is_user_member_of_blog( (int) $character->post_author ) || ! ps_pen_paper_campaign_role( $campaign, (int) $character->post_author ) ) ) { return ps_pen_paper_loot_error(); }
				if ( $has_money ) {
					$wallet = ps_pen_paper_wallet_context( $campaign, $character_id );
					$currency = $p['table']['table']['currency'];
					if ( is_wp_error( $wallet ) ) { return $wallet; }
					if ( (int) $wallet['character']->ID !== $target_id || (int) $wallet['currency_scope']->ID !== $scope_id ||
						$scope_id !== $currency['scope_id'] || $wallet['currency']['currency_id'] !== $currency['document']['currency_id'] ||
						! is_string( $wallet_revision ) || (string) $wallet['wallet']['revision'] !== $wallet_revision ||
						! is_string( $currency_revision ) || (string) $wallet['currency']['revision'] !== $currency_revision ) { return ps_pen_paper_loot_error(); }
					$result = ps_pen_paper_write_wallet_booking( $wallet, 'grant', $p['money_roll']['total'], __( 'Bestätigte Beutevergabe', 'ps-pen-paper' ) . ' #' . $id );
					if ( is_wp_error( $result ) ) { return $result; }
				}
				if ( $has_items ) {
					$result = ps_pen_paper_write_item_award( $campaign, $character, $row['item'], $p['quantity_roll']['total'], $gear_revision );
					if ( is_wp_error( $result ) ) { return $result; }
				}
				$after = $before; $after['revision']++;
				$after['award'] = array( 'character_id' => $character_id, 'character_name' => $state && ! is_wp_error( $state ) ? $state['active']['name'] : __( 'Keine Beute', 'ps-pen-paper' ), 'actor_id' => get_current_user_id() );
				return ps_pen_paper_write_loot( $campaign, $id, 'run', $before, $after, 'loot_awarded' );
			} );
		} finally { foreach ( array_unique( array( $id, $character_id, $target_id, $scope_id, (int) $campaign->ID ) ) as $cache_id ) { if ( $cache_id ) { clean_post_cache( $cache_id ); } } }
	};
	if ( ! $has_money && ! $has_items ) { return 0 === $character_id ? $write() : ps_pen_paper_loot_error(); }
	return ps_pen_paper_with_character_lock( $character_id, function () use ( $character_id, $target_id, $write ) {
		return $character_id === $target_id ? $write() : ps_pen_paper_with_character_lock( $target_id, $write );
	} );
}
