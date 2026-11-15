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

function ps_pen_paper_loot_table_has_extended_rewards( $state ) {
	if ( ! ps_pen_paper_valid_loot_table( $state ) || ! $state['revision'] ) { return true; }
	foreach ( $state['table']['rows'] as $row ) {
		if ( ! empty( $row['items'] ) || ! empty( $row['nested'] ) ) { return true; }
	}
	return false;
}

function ps_pen_paper_valid_loot_table( $state, $depth = 0, $ancestors = array() ) {
	if ( $depth > 5 || ( 0 === $depth && strlen( serialize( $state ) ) > 1048576 ) ) { return false; }
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
		if ( array_key_exists( 'items', $row ) ) {
			if ( ! is_array( $row['items'] ) || array_values( $row['items'] ) !== $row['items'] || count( $row['items'] ) > 10 ) { return false; }
			foreach ( $row['items'] as $item ) {
				if ( ! is_array( $item ) || array( 'item', 'quantity' ) !== array_keys( $item ) ||
					! ps_pen_paper_valid_loot_item( $item['item'] ) || is_wp_error( ps_pen_paper_loot_spec( $item['quantity'], 100, false ) ) ) { return false; }
			}
		}
		if ( isset( $row['nested'] ) && null !== $row['nested'] ) {
			$nested = $row['nested'];
			if ( ! is_array( $nested ) || array( 'table_id', 'table' ) !== array_keys( $nested ) || ! is_int( $nested['table_id'] ) ||
				$nested['table_id'] < 1 || in_array( $nested['table_id'], $ancestors, true ) ||
				! ps_pen_paper_valid_loot_table( $nested['table'], $depth + 1, array_merge( $ancestors, array( $nested['table_id'] ) ) ) ||
				$nested['table']['revision'] < 1 ) { return false; }
			$currency = $nested['table']['table']['currency'];
			if ( null !== $currency && ( null === $table['currency'] || $currency['scope_id'] !== $table['currency']['scope_id'] ||
				$currency['document']['currency_id'] !== $table['currency']['document']['currency_id'] ) ) { return false; }
		}
		$next = $row['to'] + 1;
	}
	return $next === (int) $table['die'] + 1;
}

function ps_pen_paper_loot_snapshot_map( $state, $item_callback, $currency_callback = null, $table_id_callback = null ) {
	if ( ! ps_pen_paper_valid_loot_table( $state ) || ! $state['revision'] || ! is_callable( $item_callback ) ||
		( null !== $currency_callback && ! is_callable( $currency_callback ) ) ||
		( null !== $table_id_callback && ! is_callable( $table_id_callback ) ) ) { return ps_pen_paper_loot_error(); }
	$visit = function ( &$snapshot ) use ( &$visit, $item_callback, $currency_callback, $table_id_callback ) {
		if ( null !== $snapshot['table']['currency'] && null !== $currency_callback ) {
			$result = $currency_callback( $snapshot['table']['currency'] );
			if ( true !== $result ) { return is_wp_error( $result ) ? $result : ps_pen_paper_loot_error(); }
		}
		foreach ( $snapshot['table']['rows'] as &$row ) {
			if ( null !== $row['item'] ) {
				$result = $item_callback( $row['item'] );
				if ( true !== $result ) { unset( $row ); return is_wp_error( $result ) ? $result : ps_pen_paper_loot_error(); }
			}
			if ( isset( $row['items'] ) ) {
				foreach ( $row['items'] as &$extra ) {
					$result = $item_callback( $extra['item'] );
					if ( true !== $result ) { unset( $extra, $row ); return is_wp_error( $result ) ? $result : ps_pen_paper_loot_error(); }
				}
				unset( $extra );
			}
			if ( ! empty( $row['nested'] ) ) {
				if ( null !== $table_id_callback ) {
					$result = $table_id_callback( $row['nested']['table_id'] );
					if ( true !== $result ) { unset( $row ); return is_wp_error( $result ) ? $result : ps_pen_paper_loot_error(); }
				}
				$result = $visit( $row['nested']['table'] );
				if ( is_wp_error( $result ) ) { unset( $row ); return $result; }
			}
		}
		unset( $row );
		return true;
	};
	$copy = $state; $result = $visit( $copy );
	if ( is_wp_error( $result ) ) { return $result; }
	return ps_pen_paper_valid_loot_table( $copy ) ? $copy : ps_pen_paper_loot_error();
}

function ps_pen_paper_loot_snapshot_items( $state ) {
	$items = array();
	$result = ps_pen_paper_loot_snapshot_map( $state, function ( &$item ) use ( &$items ) { $items[] = $item; return true; } );
	return is_wp_error( $result ) ? $result : $items;
}

function ps_pen_paper_loot_spec_maximum( $expression, $limit, $zero = true ) {
	$parsed = ps_pen_paper_loot_spec( $expression, $limit, $zero );
	if ( is_wp_error( $parsed ) ) { return $parsed; }
	$maximum = $parsed['modifier'];
	foreach ( $parsed['groups'] as $group ) { $maximum += $group['count'] * $group['sides']; }
	return $maximum;
}

function ps_pen_paper_loot_snapshot_limits( $state ) {
	if ( ! ps_pen_paper_valid_loot_table( $state ) || ! $state['revision'] ) { return ps_pen_paper_loot_error(); }
	$visit = function ( $snapshot ) use ( &$visit ) {
		$maximum = array( 'items' => 0, 'money' => 0 );
		foreach ( $snapshot['table']['rows'] as $row ) {
			$items = ps_pen_paper_loot_spec_maximum( $row['quantity'], 100, null === $row['item'] );
			$money = ps_pen_paper_loot_spec_maximum( $row['money'], 999999999 );
			if ( is_wp_error( $items ) || is_wp_error( $money ) ) { return ps_pen_paper_loot_error(); }
			foreach ( isset( $row['items'] ) ? $row['items'] : array() as $extra ) {
				$quantity = ps_pen_paper_loot_spec_maximum( $extra['quantity'], 100, false );
				if ( is_wp_error( $quantity ) ) { return $quantity; }
				$items += $quantity;
			}
			if ( ! empty( $row['nested'] ) ) {
				$child = $visit( $row['nested']['table'] );
				if ( is_wp_error( $child ) ) { return $child; }
				$items += $child['items']; $money += $child['money'];
			}
			$maximum['items'] = max( $maximum['items'], $items );
			$maximum['money'] = max( $maximum['money'], $money );
		}
		return $maximum;
	};
	return $visit( $state );
}

function ps_pen_paper_get_loot_table( $campaign, $id ) {
	if ( ! ps_pen_paper_can_manage_loot( $campaign ) ) { return ps_pen_paper_loot_error(); }
	$post = ps_pen_paper_record_post( $id ); $state = get_post_meta( $id, '_pspp_loot_table', true );
	return $post && 'pspp_loot_table' === $post->post_type && 'private' === $post->post_status && (int) $post->post_parent === (int) $campaign->ID &&
		ps_pen_paper_valid_loot_table( $state, 0, array( (int) $id ) ) && $state['revision'] > 0 ? $state : ps_pen_paper_loot_error();
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
			if ( '' === $row['from'] && '' === $row['to'] && '' === trim( $row['label'] ) && '0' === $row['item_id'] && '0' === $row['quantity'] && '0' === $row['money'] &&
				( ! isset( $row['items'] ) || ( is_string( $row['items'] ) && '' === trim( $row['items'] ) ) ) && ( ! isset( $row['nested_id'] ) || '0' === $row['nested_id'] ) ) { continue; }
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
			$line = array( 'from' => (int) $row['from'], 'to' => (int) $row['to'], 'label' => sanitize_text_field( $row['label'] ), 'item' => $item, 'quantity' => $quantity['expression'], 'money' => $money['expression'] );
			if ( isset( $row['items'] ) || isset( $row['nested_id'] ) ) {
				if ( ! isset( $row['items'], $row['nested_id'] ) || ! is_string( $row['items'] ) || strlen( $row['items'] ) > 3000 ||
					! is_string( $row['nested_id'] ) || ! preg_match( '/^(?:0|[1-9]\d{0,10})$/D', $row['nested_id'] ) ) { return ps_pen_paper_loot_error(); }
				$line['items'] = array();
				foreach ( preg_split( '/\r\n|\r|\n/', $row['items'] ) as $extra ) {
					if ( '' === trim( $extra ) ) { continue; }
					if ( ! preg_match( '/^([1-9]\d{0,10}):(.+)$/D', trim( $extra ), $parts ) || ! isset( $catalogue[ (int) $parts[1] ] ) ) { return ps_pen_paper_loot_error(); }
					$source = $catalogue[ (int) $parts[1] ];
					$instance = array( 'source_id' => (int) $parts[1], 'scope_id' => $source['scope_id'], 'revision' => $source['revision'], 'item' => $source['item'] );
					$instance['item']['binding'] = ps_pen_paper_item_binding( $instance['item'] );
					$spec = ps_pen_paper_loot_spec( trim( $parts[2] ), 100, false, $rules['dice'] );
					if ( is_wp_error( $spec ) ) { return $spec; }
					$line['items'][] = array( 'item' => $instance, 'quantity' => $spec['expression'] );
				}
				$line['nested'] = null;
				if ( '0' !== $row['nested_id'] ) {
					$nested_id = (int) $row['nested_id'];
					$nested = $nested_id === $id ? ps_pen_paper_loot_error() : ps_pen_paper_get_loot_table( $campaign, $nested_id );
					if ( is_wp_error( $nested ) || ! ps_pen_paper_valid_loot_table( $nested, 1, array( $id, $nested_id ) ) ) { return ps_pen_paper_loot_error(); }
					$line['nested'] = array( 'table_id' => $nested_id, 'table' => $nested );
					$nested_currency = $nested['table']['currency'];
					if ( $nested_currency ) {
						if ( $table['currency'] && ( $table['currency']['scope_id'] !== $nested_currency['scope_id'] ||
							$table['currency']['document']['currency_id'] !== $nested_currency['document']['currency_id'] ) ) { return ps_pen_paper_loot_error(); }
						$table['currency'] = $nested_currency;
					}
				}
			}
			$table['rows'][] = $line;
			if ( '0' !== $money['expression'] ) {
				$scope = ps_pen_paper_currency_scope( $campaign );
				$currency = is_wp_error( $scope ) ? $scope : ps_pen_paper_currency_document( $scope->ID );
				if ( is_wp_error( $currency ) || ! $currency['revision'] ) { return new WP_Error( 'currency_missing', __( 'Für Geldbeute muss zuerst eine Währung eingerichtet sein.', 'ps-pen-paper' ) ); }
				if ( $table['currency'] && ( $table['currency']['scope_id'] !== (int) $scope->ID ||
					$table['currency']['document']['currency_id'] !== $currency['currency_id'] ) ) { return ps_pen_paper_loot_error(); }
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

function ps_pen_paper_loot_roll_node( $table_id, $table, $dice, $depth = 0 ) {
	if ( $depth > 5 || ! ps_pen_paper_valid_loot_table( $table, $depth ) ) { return ps_pen_paper_loot_error(); }
	$parsed = ps_pen_paper_parse_dice( 'W' . $table['table']['die'], $dice );
	if ( is_wp_error( $parsed ) ) { return $parsed; }
	$roll = ps_pen_paper_evaluate_dice( $parsed );
	foreach ( $table['table']['rows'] as $index => $row ) { if ( $roll['total'] >= $row['from'] && $roll['total'] <= $row['to'] ) { break; } }
	$quantity = ps_pen_paper_loot_spec( $row['quantity'], 100, null === $row['item'], $dice );
	$money = ps_pen_paper_loot_spec( $row['money'], 999999999, true, $dice );
	if ( is_wp_error( $quantity ) || is_wp_error( $money ) ) { return ps_pen_paper_loot_error(); }
	$node = array( 'table_id' => $table_id, 'table' => $table, 'roll' => $roll, 'row' => $index,
		'quantity_roll' => ps_pen_paper_evaluate_dice( $quantity ), 'money_roll' => ps_pen_paper_evaluate_dice( $money ) );
	if ( isset( $row['items'] ) || isset( $row['nested'] ) ) {
		$node['extra_rolls'] = array();
		foreach ( isset( $row['items'] ) ? $row['items'] : array() as $item ) {
			$spec = ps_pen_paper_loot_spec( $item['quantity'], 100, false, $dice );
			if ( is_wp_error( $spec ) ) { return $spec; }
			$node['extra_rolls'][] = ps_pen_paper_evaluate_dice( $spec );
		}
		$node['nested'] = null;
		if ( ! empty( $row['nested'] ) ) {
			$node['nested'] = ps_pen_paper_loot_roll_node( $row['nested']['table_id'], $row['nested']['table'], $dice, $depth + 1 );
			if ( is_wp_error( $node['nested'] ) ) { return $node['nested']; }
		}
	}
	return $node;
}

function ps_pen_paper_valid_loot_node( $node, $depth = 0 ) {
	if ( $depth > 5 || ! is_array( $node ) || ! isset( $node['table_id'], $node['table'], $node['roll'], $node['row'], $node['quantity_roll'], $node['money_roll'] ) ||
		! is_int( $node['table_id'] ) || $node['table_id'] < 1 || ! ps_pen_paper_valid_loot_table( $node['table'], $depth, array( $node['table_id'] ) ) ||
		! $node['table']['revision'] || ! is_int( $node['row'] ) || ! isset( $node['table']['table']['rows'][ $node['row'] ] ) ) { return false; }
	$table = $node['table']['table']; $row = $table['rows'][ $node['row'] ];
	if ( ! ps_pen_paper_valid_loot_roll( $node['roll'], ps_pen_paper_loot_spec( 'W' . $table['die'], 100, false ) ) ||
		$node['roll']['total'] < $row['from'] || $node['roll']['total'] > $row['to'] ||
		! ps_pen_paper_valid_loot_roll( $node['quantity_roll'], ps_pen_paper_loot_spec( $row['quantity'], 100, null === $row['item'] ) ) ||
		! ps_pen_paper_valid_loot_roll( $node['money_roll'], ps_pen_paper_loot_spec( $row['money'], 999999999 ) ) ) { return false; }
	$extended = isset( $row['items'] ) || isset( $row['nested'] );
	if ( ! $extended ) { return ! array_key_exists( 'extra_rolls', $node ) && ! array_key_exists( 'nested', $node ); }
	if ( ! isset( $node['extra_rolls'] ) || ! is_array( $node['extra_rolls'] ) || ! array_key_exists( 'nested', $node ) ||
		count( $node['extra_rolls'] ) !== count( isset( $row['items'] ) ? $row['items'] : array() ) ) { return false; }
	foreach ( isset( $row['items'] ) ? $row['items'] : array() as $index => $item ) {
		if ( ! isset( $node['extra_rolls'][ $index ] ) || ! ps_pen_paper_valid_loot_roll( $node['extra_rolls'][ $index ], ps_pen_paper_loot_spec( $item['quantity'], 100, false ) ) ) { return false; }
	}
	if ( empty( $row['nested'] ) ) { return null === $node['nested']; }
	return is_array( $node['nested'] ) && isset( $node['nested']['table_id'], $node['nested']['table'] ) && $node['nested']['table_id'] === $row['nested']['table_id'] &&
		$node['nested']['table'] === $row['nested']['table'] && ps_pen_paper_valid_loot_node( $node['nested'], $depth + 1 );
}

function ps_pen_paper_loot_components( $node ) {
	$row = $node['table']['table']['rows'][ $node['row'] ]; $items = array(); $money = $node['money_roll']['total'];
	if ( $row['item'] ) { $items[] = array( 'item' => $row['item'], 'quantity' => $node['quantity_roll']['total'] ); }
	foreach ( isset( $row['items'] ) ? $row['items'] : array() as $index => $item ) {
		$items[] = array( 'item' => $item['item'], 'quantity' => $node['extra_rolls'][ $index ]['total'] );
	}
	if ( ! empty( $node['nested'] ) ) {
		$child = ps_pen_paper_loot_components( $node['nested'] );
		$items = array_merge( $items, $child['items'] ); $money += $child['money'];
	}
	return array( 'items' => $items, 'money' => $money );
}

function ps_pen_paper_valid_loot_allocations( $proposal, $award ) {
	if ( ! is_array( $award ) || array( 'actor_id', 'allocations' ) !== array_keys( $award ) ||
		! is_int( $award['actor_id'] ) || $award['actor_id'] < 1 || ! is_array( $award['allocations'] ) ||
		array_values( $award['allocations'] ) !== $award['allocations'] || count( $award['allocations'] ) > 30 ) { return false; }
	$components = ps_pen_paper_loot_components( $proposal ); $totals = array_fill( 0, count( $components['items'] ), 0 ); $money = 0; $seen = array();
	foreach ( $award['allocations'] as $allocation ) {
		if ( ! is_array( $allocation ) || array( 'character_id', 'character_name', 'items', 'money' ) !== array_keys( $allocation ) ||
			! is_int( $allocation['character_id'] ) || $allocation['character_id'] < 1 || isset( $seen[ $allocation['character_id'] ] ) ||
			! is_string( $allocation['character_name'] ) || '' === trim( $allocation['character_name'] ) || strlen( $allocation['character_name'] ) > 800 ||
			! is_array( $allocation['items'] ) || array_values( $allocation['items'] ) !== $allocation['items'] ||
			count( $allocation['items'] ) !== count( $totals ) || ! is_int( $allocation['money'] ) || $allocation['money'] < 0 ||
			$allocation['money'] > 999999999 ) { return false; }
		$seen[ $allocation['character_id'] ] = true; $money += $allocation['money']; $nonzero = $allocation['money'] > 0;
		foreach ( $allocation['items'] as $index => $quantity ) {
			if ( ! is_int( $quantity ) || $quantity < 0 || $quantity > 100 ) { return false; }
			$totals[ $index ] += $quantity; $nonzero = $nonzero || $quantity > 0;
		}
		if ( ! $nonzero ) { return false; }
	}
	if ( $money !== $components['money'] ) { return false; }
	foreach ( $components['items'] as $index => $item ) { if ( $totals[ $index ] !== $item['quantity'] ) { return false; } }
	return true;
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
	if ( ! ps_pen_paper_valid_loot_node( $p ) ) { return false; }
	if ( null === $state['award'] ) { return 1 === $state['revision']; }
	$a = $state['award'];
	if ( isset( $a['allocations'] ) ) { return 2 === $state['revision'] && ps_pen_paper_valid_loot_allocations( $p, $a ); }
	if ( ! empty( $p['extra_rolls'] ) || ! empty( $p['nested'] ) ) { return false; }
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
		$node = ps_pen_paper_loot_roll_node( $table_id, $table, $rules['dice'] );
		if ( is_wp_error( $node ) ) { return $node; }
		$after = array( 'revision' => 1, 'award' => null, 'proposal' => array(
			'token' => $token, 'actor_id' => get_current_user_id(), 'reason' => sanitize_textarea_field( $reason ),
			'rules_revision' => $rules['revision'],
		) + $node );
		return ps_pen_paper_write_loot( $campaign, $id, 'run', array( 'revision' => 0, 'proposal' => null, 'award' => null ), $after, 'loot_rolled' );
	} );
	if ( is_int( $id ) && $id ) { clean_post_cache( $id ); }
	return is_wp_error( $result ) ? $result : $id;
}

function ps_pen_paper_award_loot_split( $campaign, $id, $input, $confirmation ) {
					if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
					if ( ! ps_pen_paper_can_manage_loot( $campaign ) || 'confirmed' !== $confirmation || ! is_array( $input ) || count( $input ) > 30 ) { return ps_pen_paper_loot_error(); }
					$run = ps_pen_paper_get_loot_run( $campaign, $id );
					if ( is_wp_error( $run ) || null !== $run['award'] ) { return ps_pen_paper_loot_error(); }
					$proposal = $run['proposal']; $components = ps_pen_paper_loot_components( $proposal );
					$rows = array(); $test = array( 'actor_id' => get_current_user_id(), 'allocations' => array() );
					foreach ( $input as $character_id => $row ) {
						if ( is_array( $row ) && ! isset( $row['items'] ) && ! $components['items'] ) { $row['items'] = array(); }
						if ( ! preg_match( '/^[1-9]\d{0,10}$/D', (string) $character_id ) || ! is_array( $row ) ||
							! isset( $row['gear_revision'], $row['wallet_revision'], $row['currency_revision'], $row['money'], $row['items'] ) ||
							! is_array( $row['items'] ) || array_values( $row['items'] ) !== $row['items'] || count( $row['items'] ) !== count( $components['items'] ) ) { return ps_pen_paper_loot_error(); }
						foreach ( array( 'gear_revision', 'wallet_revision', 'currency_revision', 'money' ) as $key ) {
							if ( ! is_string( $row[ $key ] ) || ! preg_match( '/^(?:0|[1-9]\d{0,8})$/D', $row[ $key ] ) ) { return ps_pen_paper_loot_error(); }
						}
						$amounts = array();
						foreach ( $row['items'] as $quantity ) {
							if ( ! is_string( $quantity ) || ! preg_match( '/^(?:0|[1-9]\d{0,2})$/D', $quantity ) || (int) $quantity > 100 ) { return ps_pen_paper_loot_error(); }
							$amounts[] = (int) $quantity;
						}
						if ( ! ps_pen_paper_get_character( $campaign, (int) $character_id ) ) { return ps_pen_paper_loot_error(); }
						$money = (int) $row['money'];
						if ( ! $money && ! array_sum( $amounts ) ) { continue; }
						$character_id = (int) $character_id;
						$rows[ $character_id ] = array( 'gear_revision' => $row['gear_revision'], 'wallet_revision' => $row['wallet_revision'],
							'currency_revision' => $row['currency_revision'], 'items' => $amounts, 'money' => $money );
						$test['allocations'][] = array( 'character_id' => $character_id, 'character_name' => 'x', 'items' => $amounts, 'money' => $money );
					}
					if ( ! ps_pen_paper_valid_loot_allocations( $proposal, $test ) ) {
						return new WP_Error( 'invalid_loot_split', __( 'Verteile jeden Gegenstand und den gesamten Geldbetrag genau einmal. Es wurde nichts vergeben.', 'ps-pen-paper' ) );
					}
					$scope_id = $components['money'] ? $proposal['table']['table']['currency']['scope_id'] : (int) $campaign->ID;
					$locks = array_keys( $rows ); $targets = array();
					foreach ( $rows as $character_id => $row ) {
						if ( ! $row['money'] ) { continue; }
						$wallet = ps_pen_paper_wallet_context( $campaign, $character_id );
						if ( is_wp_error( $wallet ) ) { return $wallet; }
						$target = (int) $wallet['character']->ID;
						if ( isset( $targets[ $target ] ) || (int) $wallet['currency_scope']->ID !== $scope_id ) { return ps_pen_paper_loot_error(); }
						$targets[ $target ] = $character_id; $rows[ $character_id ]['target_id'] = $target; $locks[] = $target;
					}
					$locks = array_values( array_unique( $locks ) ); sort( $locks, SORT_NUMERIC );
					$scope_ids = array_values( array_unique( array( (int) $campaign->ID, (int) $scope_id ) ) ); sort( $scope_ids, SORT_NUMERIC );
					$write = function () use ( $campaign, $id, $proposal, $components, $rows, $scope_id, $locks, $scope_ids ) {
						$lock_scopes = function ( $index ) use ( &$lock_scopes, $scope_ids, $campaign, $id, $proposal, $components, $rows, $scope_id, $locks ) {
							if ( isset( $scope_ids[ $index ] ) ) { return ps_pen_paper_scope_lock( $scope_ids[ $index ], function () use ( &$lock_scopes, $index ) { return $lock_scopes( $index + 1 ); } ); }
							return ps_pen_paper_atomic_event( $scope_id, function () use ( $campaign, $id, $proposal, $components, $rows, $scope_id, $locks ) {
								global $wpdb;
								$post_locks = array_values( array_unique( array_merge( $locks, array( (int) $campaign->ID, (int) $id ) ) ) ); sort( $post_locks, SORT_NUMERIC );
								foreach ( $post_locks as $lock_id ) {
									if ( $lock_id === $scope_id ) { continue; }
									if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $lock_id ) ) !== $lock_id ) { return ps_pen_paper_loot_error(); }
									clean_post_cache( $lock_id );
								}
								$campaign = ps_pen_paper_get_campaign( $campaign->ID );
								$before = $campaign ? ps_pen_paper_get_loot_run( $campaign, $id ) : ps_pen_paper_loot_error();
								if ( ! ps_pen_paper_can_manage_loot( $campaign ) || is_wp_error( $before ) || null !== $before['award'] || $before['proposal'] !== $proposal ) { return ps_pen_paper_loot_error(); }
								$characters = array(); $wallets = array(); $award = array( 'actor_id' => get_current_user_id(), 'allocations' => array() );
								foreach ( $rows as $character_id => $row ) {
									$character = ps_pen_paper_get_character( $campaign, $character_id );
									$state = $character ? ps_pen_paper_character_state( $campaign, $character, false ) : null;
									if ( ! $character || is_wp_error( $state ) || ! $state['active'] || ! ps_pen_paper_site_user( (int) $character->post_author ) ||
										! ps_pen_paper_campaign_role( $campaign, (int) $character->post_author ) ) { return ps_pen_paper_loot_error(); }
									if ( array_sum( $row['items'] ) ) {
										$gear = ps_pen_paper_gear_state( $character_id );
										if ( is_wp_error( $gear ) || (string) $gear['revision'] !== $row['gear_revision'] ) { return ps_pen_paper_loot_error(); }
									}
									if ( $row['money'] ) {
										$wallet = ps_pen_paper_wallet_context( $campaign, $character_id ); $currency = $proposal['table']['table']['currency'];
										if ( is_wp_error( $wallet ) || ! ps_pen_paper_can_manage_character_scope( $wallet['scope'] ) ||
											(int) $wallet['character']->ID !== $row['target_id'] || (int) $wallet['currency_scope']->ID !== $scope_id ||
											$wallet['currency']['currency_id'] !== $currency['document']['currency_id'] ||
											(string) $wallet['wallet']['revision'] !== $row['wallet_revision'] || (string) $wallet['currency']['revision'] !== $row['currency_revision'] ) { return ps_pen_paper_loot_error(); }
										$wallets[ $character_id ] = $wallet;
									}
									$characters[ $character_id ] = $character;
									$award['allocations'][] = array( 'character_id' => $character_id, 'character_name' => $state['active']['name'], 'items' => $row['items'], 'money' => $row['money'] );
								}
								if ( ! ps_pen_paper_valid_loot_allocations( $proposal, $award ) ) { return ps_pen_paper_loot_error(); }
								foreach ( $rows as $character_id => $row ) {
									if ( $row['money'] ) {
										$wallets[ $character_id ]['currency'] = ps_pen_paper_currency_document( $scope_id );
										if ( is_wp_error( $wallets[ $character_id ]['currency'] ) ) { return $wallets[ $character_id ]['currency']; }
										$result = ps_pen_paper_write_wallet_booking( $wallets[ $character_id ], 'grant', $row['money'], __( 'Bestätigte Beutevergabe', 'ps-pen-paper' ) . ' #' . $id );
										if ( is_wp_error( $result ) ) { return $result; }
									}
									$revision = (int) $row['gear_revision'];
									foreach ( $row['items'] as $index => $quantity ) {
										if ( ! $quantity ) { continue; }
										$result = ps_pen_paper_write_item_award( $campaign, $characters[ $character_id ], $components['items'][ $index ]['item'], $quantity, (string) $revision );
										if ( is_wp_error( $result ) ) { return $result; }
										$revision++;
									}
								}
								$after = $before; $after['revision']++; $after['award'] = $award;
								return ps_pen_paper_write_loot( $campaign, $id, 'run', $before, $after, 'loot_awarded' );
							} );
						};
						try { return $lock_scopes( 0 ); }
						finally { foreach ( array_unique( array_merge( $locks, $scope_ids, array( (int) $id ) ) ) as $post_id ) { clean_post_cache( $post_id ); } }
					};
					return ps_pen_paper_with_character_locks( $locks, $write );
}

function ps_pen_paper_award_loot( $campaign, $id, $character_id, $gear_revision, $wallet_revision, $currency_revision, $confirmation ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( ! ps_pen_paper_can_manage_loot( $campaign ) || 'confirmed' !== $confirmation ) { return ps_pen_paper_loot_error(); }
	$run = ps_pen_paper_get_loot_run( $campaign, $id );
	if ( is_wp_error( $run ) ) { return $run; }
	if ( array_key_exists( 'extra_rolls', $run['proposal'] ) ) { return ps_pen_paper_loot_error(); }
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
