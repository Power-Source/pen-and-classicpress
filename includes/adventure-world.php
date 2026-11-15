<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_adventure_world_error() {
	return new WP_Error( 'invalid_adventure_world', __( 'Prüfe Gegenstandsvoraussetzungen, Abenteuerzustände und Begegnungsphasen. Es wurde kein Teilfortschritt gespeichert.', 'ps-pen-paper' ) );
}

function ps_pen_paper_adventure_graph_keys( $graph, $keys ) {
	foreach ( array( 'states', 'encounters' ) as $key ) {
		if ( is_array( $graph ) && array_key_exists( $key, $graph ) ) { $keys[] = $key; }
	}
	return $keys;
}

function ps_pen_paper_valid_adventure_world( $graph ) {
	$states = $graph['states'] ?? array();
	if ( ! is_array( $states ) || array_values( $states ) !== $states || count( $states ) > 30 ) { return false; }
	$ids = array();
	foreach ( $states as $state ) {
		if ( ! is_array( $state ) || array( 'id', 'label', 'initial' ) !== array_keys( $state ) ||
			! is_string( $state['id'] ) || ! wp_is_uuid( $state['id'] ) || isset( $ids[ $state['id'] ] ) ||
			! is_string( $state['label'] ) || '' === trim( $state['label'] ) || strlen( $state['label'] ) > 200 ||
			! is_string( $state['initial'] ) || strlen( $state['initial'] ) > 2000 ) { return false; }
		$ids[ $state['id'] ] = true;
	}
	$encounters = $graph['encounters'] ?? array();
	if ( ! is_array( $encounters ) || array_values( $encounters ) !== $encounters || count( $encounters ) > 30 ) { return false; }
	$encounter_ids = array();
	foreach ( $encounters as $encounter ) {
		if ( ! is_array( $encounter ) || array( 'id', 'title', 'phases', 'npcs' ) !== array_keys( $encounter ) ||
			! is_string( $encounter['id'] ) || ! wp_is_uuid( $encounter['id'] ) || isset( $encounter_ids[ $encounter['id'] ] ) ||
			! is_string( $encounter['title'] ) || '' === trim( $encounter['title'] ) || strlen( $encounter['title'] ) > 200 ||
			! is_array( $encounter['phases'] ) || array_values( $encounter['phases'] ) !== $encounter['phases'] ||
			count( $encounter['phases'] ) < 2 || count( $encounter['phases'] ) > 30 ||
			count( array_unique( $encounter['phases'], SORT_REGULAR ) ) !== count( $encounter['phases'] ) ||
			! is_array( $encounter['npcs'] ) || array_values( $encounter['npcs'] ) !== $encounter['npcs'] || count( $encounter['npcs'] ) > 20 ) { return false; }
		$encounter_ids[ $encounter['id'] ] = true;
		foreach ( $encounter['phases'] as $scene_id ) { if ( ! is_int( $scene_id ) || $scene_id < 1 ) { return false; } }
		foreach ( $encounter['npcs'] as $npc ) {
			if ( ! is_array( $npc ) || array( 'name', 'description' ) !== array_keys( $npc ) ||
				! is_string( $npc['name'] ) || '' === trim( $npc['name'] ) || strlen( $npc['name'] ) > 200 ||
				! is_string( $npc['description'] ) || strlen( $npc['description'] ) > 20000 ) { return false; }
		}
	}
	if ( isset( $graph['edges'] ) && ! is_array( $graph['edges'] ) ) { return false; }
	foreach ( $graph['edges'] ?? array() as $edge ) {
		if ( ! ps_pen_paper_valid_adventure_edge( $edge ) ) { return false; }
		foreach ( $edge['effects'] ?? array() as $effect ) { if ( ! isset( $ids[ $effect['state'] ] ) ) { return false; } }
		foreach ( $edge['conditions']['rows'] ?? array() as $condition ) {
			if ( 'state' === $condition['part'] && ! isset( $ids[ $condition['field'] ] ) ) { return false; }
		}
	}
	if ( isset( $graph['scenes'] ) ) {
		foreach ( $encounters as $encounter ) {
			foreach ( $encounter['phases'] as $index => $scene_id ) {
				if ( ! isset( $graph['scenes'][ $scene_id ] ) ) { return false; }
				if ( ! $index ) { continue; }
				$pending = array( $encounter['phases'][ $index - 1 ] ); $visited = array();
				while ( $pending && ! isset( $visited[ $scene_id ] ) ) {
					$current = array_pop( $pending );
					if ( isset( $visited[ $current ] ) ) { continue; }
					$visited[ $current ] = true;
					foreach ( $graph['edges'] as $edge ) {
						if ( $edge['from_scene'] !== $current ) { continue; }
						foreach ( array( $edge['success_scene'], $edge['failure_scene'] ) as $next ) { if ( $next && ! isset( $visited[ $next ] ) ) { $pending[] = $next; } }
					}
				}
				if ( ! isset( $visited[ $scene_id ] ) ) { return false; }
			}
		}
	}
	return true;
}

function ps_pen_paper_valid_adventure_items( $items ) {
	if ( null === $items ) { return true; }
	if ( ! is_array( $items ) || array( 'mode', 'rows' ) !== array_keys( $items ) ||
		! in_array( $items['mode'], array( 'all', 'any' ), true ) || ! is_array( $items['rows'] ) ||
		! $items['rows'] || array_values( $items['rows'] ) !== $items['rows'] || count( $items['rows'] ) > 20 ) { return false; }
	foreach ( $items['rows'] as $row ) {
		if ( ! is_array( $row ) || array( 'item', 'minimum' ) !== array_keys( $row ) ||
			! ps_pen_paper_valid_item_instance( $row['item'] ) || ! is_int( $row['minimum'] ) ||
			$row['minimum'] < 1 || $row['minimum'] > 100 ) { return false; }
	}
	return true;
}

function ps_pen_paper_prepare_adventure_items( $campaign, $input ) {
	if ( ! is_array( $input ) || ! isset( $input['mode'], $input['rows'] ) ||
		! in_array( $input['mode'], array( 'all', 'any' ), true ) || ! is_array( $input['rows'] ) || count( $input['rows'] ) > 20 ) { return ps_pen_paper_adventure_world_error(); }
	$result = array( 'mode' => $input['mode'], 'rows' => array() ); $catalogue = null;
	foreach ( $input['rows'] as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['item_id'], $row['minimum'] ) ||
			! is_string( $row['item_id'] ) || ! is_string( $row['minimum'] ) ||
			! preg_match( '/^(0|[1-9]\d{0,18})$/D', $row['item_id'] ) ||
			(string) (int) $row['item_id'] !== $row['item_id'] || ! preg_match( '/^[1-9]\d{0,2}$/D', $row['minimum'] ) ||
			(int) $row['minimum'] > 100 ) { return ps_pen_paper_adventure_world_error(); }
		if ( '0' === $row['item_id'] ) { continue; }
		if ( null === $catalogue ) {
			$catalogue = ps_pen_paper_item_catalogue( $campaign );
			if ( is_wp_error( $catalogue ) ) { return $catalogue; }
		}
		$id = (int) $row['item_id'];
		if ( ! isset( $catalogue[ $id ] ) ) { return ps_pen_paper_adventure_world_error(); }
		$template = $catalogue[ $id ];
		$item = array( 'source_id' => $id, 'scope_id' => $template['scope_id'], 'revision' => $template['revision'], 'item' => $template['item'] );
		$item['item']['binding'] = ps_pen_paper_item_binding( $item['item'] );
		$result['rows'][] = array( 'item' => $item, 'minimum' => (int) $row['minimum'] );
	}
	return $result['rows'] ? $result : null;
}

function ps_pen_paper_adventure_items_result( $items, $character_id ) {
	if ( null === $items ) { return null; }
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( ! ps_pen_paper_valid_adventure_items( $items ) ) { return ps_pen_paper_adventure_world_error(); }
	$gear = ps_pen_paper_gear_state( $character_id );
	if ( is_wp_error( $gear ) ) { return $gear; }
	$result = array( 'mode' => $items['mode'], 'rows' => array(), 'gear_revision' => $gear['revision'], 'met' => false );
	$matches = array(); $origins = array();
	foreach ( $items['rows'] as $row ) {
		$count = 0;
		foreach ( $gear['active']['items'] as $instance ) {
			if ( $instance['scope_id'] !== $row['item']['scope_id'] ) { continue; }
			if ( $instance['source_id'] === $row['item']['source_id'] ) { $count++; continue; }
			$scope = $instance['scope_id'];
			if ( ! isset( $origins[ $scope ] ) ) {
				$origins[ $scope ] = array();
				foreach ( get_posts( array( 'post_type' => 'pspp_item', 'post_status' => 'private', 'post_parent' => $scope, 'posts_per_page' => -1 ) ) as $template ) {
					$origin = get_post_meta( $template->ID, '_pspp_item_origin', true );
					if ( '' === $origin ) { continue; }
					if ( ! ps_pen_paper_valid_item_origin( $origin ) ) { return ps_pen_paper_adventure_world_error(); }
					$origins[ $scope ][] = array_merge( array( (int) $template->ID ), $origin['aliases'] );
				}
			}
			foreach ( $origins[ $scope ] as $ids ) {
				if ( in_array( $instance['source_id'], $ids, true ) && in_array( $row['item']['source_id'], $ids, true ) ) { $count++; break; }
			}
		}
		$result['rows'][] = array( 'source_id' => $row['item']['source_id'], 'scope_id' => $row['item']['scope_id'],
			'name' => $row['item']['item']['name'], 'minimum' => $row['minimum'], 'count' => $count );
		$matches[] = $count >= $row['minimum'];
	}
	$result['met'] = 'all' === $items['mode'] ? ! in_array( false, $matches, true ) : in_array( true, $matches, true );
	return $result;
}

function ps_pen_paper_valid_item_origin( $origin ) {
	if ( ! is_array( $origin ) || array( 'aliases' ) !== array_keys( $origin ) || ! is_array( $origin['aliases'] ) ||
		array_values( $origin['aliases'] ) !== $origin['aliases'] || ! $origin['aliases'] || count( $origin['aliases'] ) > 100 ||
		count( array_unique( $origin['aliases'], SORT_REGULAR ) ) !== count( $origin['aliases'] ) ) { return false; }
	foreach ( $origin['aliases'] as $id ) { if ( ! is_int( $id ) || $id < 1 ) { return false; } }
	return true;
}

function ps_pen_paper_valid_adventure_items_result( $items, $result ) {
	if ( ! is_array( $result ) || array( 'mode', 'rows', 'gear_revision', 'met' ) !== array_keys( $result ) ||
		$result['mode'] !== $items['mode'] || ! is_int( $result['gear_revision'] ) || $result['gear_revision'] < 0 ||
		true !== $result['met'] || ! is_array( $result['rows'] ) || array_values( $result['rows'] ) !== $result['rows'] ||
		count( $result['rows'] ) !== count( $items['rows'] ) ) { return false; }
	$matches = array();
	foreach ( $items['rows'] as $index => $condition ) {
		$row = $result['rows'][ $index ];
		if ( ! is_array( $row ) || array( 'source_id', 'scope_id', 'name', 'minimum', 'count' ) !== array_keys( $row ) ||
			$row['source_id'] !== $condition['item']['source_id'] || $row['scope_id'] !== $condition['item']['scope_id'] ||
			$row['name'] !== $condition['item']['item']['name'] || $row['minimum'] !== $condition['minimum'] ||
			! is_int( $row['count'] ) || $row['count'] < 0 || $row['count'] > 100 ) { return false; }
		$matches[] = $row['count'] >= $row['minimum'];
	}
	return 'all' === $items['mode'] ? ! in_array( false, $matches, true ) : in_array( true, $matches, true );
}

function ps_pen_paper_valid_adventure_effects( $effects ) {
	if ( null === $effects ) { return true; }
	if ( ! is_array( $effects ) || ! $effects || array_values( $effects ) !== $effects || count( $effects ) > 20 ) { return false; }
	$seen = array();
	foreach ( $effects as $effect ) {
		if ( ! is_array( $effect ) || array( 'state', 'value', 'when' ) !== array_keys( $effect ) ||
			! is_string( $effect['state'] ) || ! wp_is_uuid( $effect['state'] ) || isset( $seen[ $effect['state'] ] ) ||
			! is_string( $effect['value'] ) || strlen( $effect['value'] ) > 2000 ||
			! in_array( $effect['when'], array( 'decision', 'success', 'failure' ), true ) ) { return false; }
		$seen[ $effect['state'] ] = true;
	}
	return true;
}

function ps_pen_paper_prepare_adventure_effects( $input, $states ) {
	if ( ! is_array( $input ) || count( $input ) > 20 ) { return ps_pen_paper_adventure_world_error(); }
	$ids = array_column( $states, 'id' ); $effects = array();
	foreach ( $input as $effect ) {
		if ( ! is_array( $effect ) || ! isset( $effect['state'], $effect['value'], $effect['when'] ) ) { return ps_pen_paper_adventure_world_error(); }
		if ( '' === $effect['state'] && '' === $effect['value'] && 'decision' === $effect['when'] ) { continue; }
		if ( ! in_array( $effect['state'], $ids, true ) ) { return ps_pen_paper_adventure_world_error(); }
		$effects[] = array( 'state' => $effect['state'], 'value' => $effect['value'], 'when' => $effect['when'] );
	}
	return ! $effects ? null : ( ps_pen_paper_valid_adventure_effects( $effects ) ? $effects : ps_pen_paper_adventure_world_error() );
}

function ps_pen_paper_adventure_state_values( $snapshot, $history ) {
	if ( ! ps_pen_paper_valid_adventure_world( $snapshot ) || ! is_array( $history ) ) { return ps_pen_paper_adventure_world_error(); }
	$values = array_column( $snapshot['states'] ?? array(), 'initial', 'id' );
	foreach ( $history as $entry ) {
		foreach ( $entry['effects'] ?? array() as $effect ) {
			if ( ! is_array( $effect ) || ! isset( $effect['state'], $effect['after'] ) || ! array_key_exists( $effect['state'], $values ) || ! is_string( $effect['after'] ) ) { return ps_pen_paper_adventure_world_error(); }
			$values[ $effect['state'] ] = $effect['after'];
		}
	}
	return $values;
}

function ps_pen_paper_adventure_effects_result( $edge, $snapshot, $history, $roll ) {
	$values = ps_pen_paper_adventure_state_values( $snapshot, $history );
	if ( is_wp_error( $values ) ) { return $values; }
	$labels = array_column( $snapshot['states'] ?? array(), 'label', 'id' ); $result = array();
	foreach ( $edge['effects'] ?? array() as $effect ) {
		$success = null === $roll || $roll['success'];
		if ( ( 'success' === $effect['when'] && ! $success ) || ( 'failure' === $effect['when'] && $success ) ) { continue; }
		if ( ! array_key_exists( $effect['state'], $values ) ) { return ps_pen_paper_adventure_world_error(); }
		$result[] = array( 'state' => $effect['state'], 'label' => $labels[ $effect['state'] ], 'before' => $values[ $effect['state'] ], 'after' => $effect['value'] );
	}
	return $result;
}

function ps_pen_paper_save_adventure_world( $campaign, $id, $input, $revision ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) || ! is_array( $input ) ) { return ps_pen_paper_adventure_world_error(); }
	return ps_pen_paper_adventure_locks( array( (int) $id ), function () use ( $campaign, $id, $input, $revision ) {
		return ps_pen_paper_atomic_event( $id, function () use ( $campaign, $id, $input, $revision ) {
			$fresh = ps_pen_paper_lock_adventure_campaign( $campaign );
			if ( is_wp_error( $fresh ) ) { return $fresh; }
			$before = ps_pen_paper_get_adventure( $fresh, $id );
			if ( is_wp_error( $before ) || ! is_string( $revision ) || (string) $before['revision'] !== $revision ||
				! isset( $input['states'], $input['encounter'] ) || ! is_array( $input['states'] ) || count( $input['states'] ) > 30 || ! is_array( $input['encounter'] ) ) { return ps_pen_paper_adventure_world_error(); }
			$states = array(); $known = array_column( $before['draft']['states'] ?? array(), 'id' );
			foreach ( $input['states'] as $state ) {
				if ( ! is_array( $state ) || ! isset( $state['id'], $state['label'], $state['initial'] ) ||
					! is_string( $state['id'] ) || ! is_string( $state['label'] ) || ! is_string( $state['initial'] ) ||
					( '' !== $state['id'] && ! in_array( $state['id'], $known, true ) ) ) { return ps_pen_paper_adventure_world_error(); }
				if ( isset( $state['remove'] ) ) { if ( '1' !== $state['remove'] || '' === $state['id'] ) { return ps_pen_paper_adventure_world_error(); } continue; }
				if ( '' === $state['id'] && '' === $state['label'] && '' === $state['initial'] ) { continue; }
				$states[] = array( 'id' => $state['id'] ?: wp_generate_uuid4(), 'label' => sanitize_text_field( $state['label'] ), 'initial' => sanitize_textarea_field( $state['initial'] ) );
			}
			$encounters = $before['draft']['encounters'] ?? array();
			if ( isset( $input['remove_encounters'] ) && ! is_array( $input['remove_encounters'] ) ) { return ps_pen_paper_adventure_world_error(); }
			foreach ( $input['remove_encounters'] ?? array() as $remove_id ) {
				if ( ! is_string( $remove_id ) || ! in_array( $remove_id, array_column( $encounters, 'id' ), true ) ) { return ps_pen_paper_adventure_world_error(); }
				$encounters = array_values( array_filter( $encounters, function ( $row ) use ( $remove_id ) { return $row['id'] !== $remove_id; } ) );
			}
			$new = $input['encounter'];
			if ( ! isset( $new['title'], $new['phases'], $new['npcs'] ) || ! is_string( $new['title'] ) || ! is_array( $new['phases'] ) || ! is_array( $new['npcs'] ) ||
				count( $new['phases'] ) > 30 || count( $new['npcs'] ) > 20 ) { return ps_pen_paper_adventure_world_error(); }
			$phases = array();
			foreach ( $new['phases'] as $scene_id ) {
				if ( ! is_string( $scene_id ) || ! preg_match( '/^(0|[1-9]\d{0,18})$/D', $scene_id ) || (string) (int) $scene_id !== $scene_id ) { return ps_pen_paper_adventure_world_error(); }
				if ( '0' === $scene_id ) { continue; }
				if ( is_wp_error( ps_pen_paper_adventure_scene( $fresh, (int) $scene_id ) ) ) { return ps_pen_paper_adventure_world_error(); }
				$phases[] = (int) $scene_id;
			}
			$npcs = array();
			foreach ( $new['npcs'] as $npc_id ) {
				if ( ! is_string( $npc_id ) || ! preg_match( '/^[1-9]\d{0,18}$/D', $npc_id ) || (string) (int) $npc_id !== $npc_id ) { return ps_pen_paper_adventure_world_error(); }
				$npc = ps_pen_paper_get_npc( $fresh, (int) $npc_id );
				if ( is_wp_error( $npc ) ) { return $npc; }
				$npcs[] = array( 'name' => $npc['npc']['name'], 'description' => $npc['npc']['description'] );
			}
			if ( '' !== $new['title'] ) {
				if ( 'confirmed' !== ( $new['confirmation'] ?? '' ) ) { return ps_pen_paper_adventure_world_error(); }
				$encounters[] = array( 'id' => wp_generate_uuid4(), 'title' => sanitize_text_field( $new['title'] ), 'phases' => $phases, 'npcs' => $npcs );
			} elseif ( $phases || $npcs ) { return ps_pen_paper_adventure_world_error(); }
			$after = $before; $after['draft']['states'] = $states; $after['draft']['encounters'] = $encounters; $after['status'] = 'draft';
			if ( ! ps_pen_paper_valid_adventure_world( $after['draft'] ) ) { return ps_pen_paper_adventure_world_error(); }
			return ps_pen_paper_write_adventure_event( $fresh, $id, $before, $after, 'adventure_saved' );
		} );
	} );
}
