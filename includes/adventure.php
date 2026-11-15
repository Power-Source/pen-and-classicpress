<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_register_adventure_types() {
	foreach ( array( 'pspp_adventure', 'pspp_adventure_run', 'pspp_adventure_group' ) as $type ) {
		register_post_type( $type, array( 'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
			'show_ui' => false, 'show_in_rest' => false, 'supports' => false,
			'capability_type' => array( $type, $type . 's' ), 'map_meta_cap' => true ) );
	}
}

function ps_pen_paper_adventure_error() {
	return new WP_Error( 'adventure_conflict', __( 'Dieser Abenteuerstand ist ungültig oder wurde inzwischen geändert. Prüfe Freigaben, Würfelregeln und Charakter und lade neu. Es wurde kein Teilfortschritt gespeichert.', 'ps-pen-paper' ) );
}

function ps_pen_paper_adventure_default() {
	return array( 'revision' => 0, 'status' => 'draft', 'publication' => 0,
		'draft' => array( 'title' => '', 'start_scene' => 0, 'edges' => array() ), 'published' => null );
}

function ps_pen_paper_valid_adventure_edge( $edge ) {
	$keys = array( 'id', 'from_scene', 'choice', 'expression', 'attribute', 'threshold', 'success_scene', 'failure_scene' );
	if ( is_array( $edge ) && array_key_exists( 'requirement', $edge ) ) {
		$keys[] = 'requirement';
		if ( ! ps_pen_paper_valid_adventure_requirement( $edge['requirement'] ) ) { return false; }
	}
	if ( is_array( $edge ) && array_key_exists( 'resource_cost', $edge ) ) {
		$keys[] = 'resource_cost';
		if ( ! ps_pen_paper_valid_adventure_resource_cost( $edge['resource_cost'] ) ) { return false; }
	}
	if ( is_array( $edge ) && array_key_exists( 'reward', $edge ) ) {
		$keys[] = 'reward';
		if ( ! ps_pen_paper_valid_adventure_reward( $edge['reward'] ) ) { return false; }
	}
	if ( is_array( $edge ) && array_key_exists( 'conditions', $edge ) ) {
		$keys[] = 'conditions';
		if ( ! ps_pen_paper_valid_adventure_conditions( $edge['conditions'] ) ) { return false; }
	}
	if ( is_array( $edge ) && array_key_exists( 'gm_review', $edge ) ) {
		$keys[] = 'gm_review';
		if ( ! is_bool( $edge['gm_review'] ) ) { return false; }
	}
	if ( is_array( $edge ) && array_key_exists( 'items', $edge ) ) {
		$keys[] = 'items';
		if ( ! ps_pen_paper_valid_adventure_items( $edge['items'] ) ) { return false; }
	}
	if ( is_array( $edge ) && array_key_exists( 'effects', $edge ) ) {
		$keys[] = 'effects';
		if ( ! ps_pen_paper_valid_adventure_effects( $edge['effects'] ) ) { return false; }
	}
	if ( ! is_array( $edge ) || $keys !== array_keys( $edge ) ||
		! is_string( $edge['id'] ) || ! wp_is_uuid( $edge['id'] ) ||
		! is_int( $edge['from_scene'] ) || $edge['from_scene'] < 1 ||
		! is_string( $edge['choice'] ) || '' === trim( $edge['choice'] ) || strlen( $edge['choice'] ) > 480 ||
		! is_string( $edge['expression'] ) || strlen( $edge['expression'] ) > 200 ||
		! is_string( $edge['attribute'] ) || strlen( $edge['attribute'] ) > 100 ||
		! is_int( $edge['threshold'] ) || abs( $edge['threshold'] ) > 1000000 ||
		! is_int( $edge['success_scene'] ) || $edge['success_scene'] < 0 ||
		! is_int( $edge['failure_scene'] ) || $edge['failure_scene'] < 0 ) { return false; }
	if ( '' === $edge['expression'] ) {
		return '' === $edge['attribute'] && 0 === $edge['threshold'] && 0 === $edge['failure_scene'];
	}
	return ! ( 0 === $edge['success_scene'] && 0 === $edge['failure_scene'] ) &&
		( '' === $edge['attribute'] || wp_is_uuid( $edge['attribute'] ) );
}

function ps_pen_paper_valid_adventure_requirement( $requirement ) {
	return null === $requirement || ( is_array( $requirement ) && array( 'attribute', 'minimum' ) === array_keys( $requirement ) &&
		is_string( $requirement['attribute'] ) && wp_is_uuid( $requirement['attribute'] ) &&
		is_int( $requirement['minimum'] ) && abs( $requirement['minimum'] ) <= 1000000 );
}

function ps_pen_paper_valid_adventure_resource_cost( $cost ) {
	return null === $cost || ( is_array( $cost ) && array( 'resource', 'amount', 'timing' ) === array_keys( $cost ) &&
		is_string( $cost['resource'] ) && wp_is_uuid( $cost['resource'] ) &&
		is_int( $cost['amount'] ) && $cost['amount'] > 0 && $cost['amount'] <= 1000000 &&
		in_array( $cost['timing'], array( 'attempt', 'success' ), true ) );
}

function ps_pen_paper_adventure_requirement_value( $edge, $rules, $state ) {
	if ( ! isset( $edge['requirement'] ) ) { return null; }
	$requirement = $edge['requirement'];
	foreach ( $rules['fields'] as $field ) {
		if ( $field['id'] === $requirement['attribute'] && in_array( $field['type'], array( 'attribute', 'number' ), true ) &&
			isset( $state['active']['values'][ $field['id'] ] ) && is_int( $state['active']['values'][ $field['id'] ] ) ) {
			return array( 'id' => $field['id'], 'label' => $field['label'], 'value' => $state['active']['values'][ $field['id'] ],
				'minimum' => $requirement['minimum'] );
		}
	}
	return ps_pen_paper_adventure_error();
}

function ps_pen_paper_adventure_conditions_error() {
	return new WP_Error( 'invalid_adventure_conditions', __( 'Die Wertebedingungen sind ungültig oder passen nicht mehr zum freigegebenen Charakter. Prüfe Felder, Vergleiche und Werte. Es wurde kein Fortschritt gespeichert.', 'ps-pen-paper' ) );
}

function ps_pen_paper_compare_adventure_value( $value, $operator, $expected ) {
	if ( ! is_string( $operator ) ) { return ps_pen_paper_adventure_conditions_error(); }
	if ( is_string( $value ) && is_string( $expected ) ) {
		switch ( $operator ) {
			case 'eq': return $value === $expected;
			case 'ne': return $value !== $expected;
			case 'contains': return false !== strpos( $value, $expected );
			case 'not_contains': return false === strpos( $value, $expected );
			default: return ps_pen_paper_adventure_conditions_error();
		}
	}
	if ( ! is_int( $value ) || ! is_int( $expected ) ) { return ps_pen_paper_adventure_conditions_error(); }
	switch ( $operator ) {
		case 'gte': return $value >= $expected;
		case 'gt': return $value > $expected;
		case 'lte': return $value <= $expected;
		case 'lt': return $value < $expected;
		case 'eq': return $value === $expected;
		case 'ne': return $value !== $expected;
		default: return ps_pen_paper_adventure_conditions_error();
	}
}

function ps_pen_paper_valid_adventure_conditions( $conditions ) {
	if ( null === $conditions ) { return true; }
	if ( ! is_array( $conditions ) || array( 'mode', 'rows' ) !== array_keys( $conditions ) ||
		! in_array( $conditions['mode'], array( 'all', 'any' ), true ) || ! is_array( $conditions['rows'] ) ||
		! $conditions['rows'] || count( $conditions['rows'] ) > 20 || array_values( $conditions['rows'] ) !== $conditions['rows'] ) { return false; }
	foreach ( $conditions['rows'] as $row ) {
		if ( ! is_array( $row ) || array( 'field', 'part', 'operator', 'value' ) !== array_keys( $row ) ||
			! is_string( $row['field'] ) || ! wp_is_uuid( $row['field'] ) || ! in_array( $row['part'], array( 'value', 'current', 'maximum', 'text', 'history', 'state' ), true ) ) { return false; }
		if ( in_array( $row['part'], array( 'text', 'state' ), true ) ) {
			if ( ! is_string( $row['value'] ) || strlen( $row['value'] ) > 2000 ||
				is_wp_error( ps_pen_paper_compare_adventure_value( '', $row['operator'], $row['value'] ) ) ) { return false; }
		} elseif ( ! is_int( $row['value'] ) || abs( $row['value'] ) > 1000000 ||
			( 'history' === $row['part'] && ! in_array( $row['value'], array( 0, 1 ), true ) ) ||
			is_wp_error( ps_pen_paper_compare_adventure_value( 0, $row['operator'], $row['value'] ) ) ) { return false; }
	}
	return true;
}

function ps_pen_paper_adventure_condition_field( $row, $rules, $edges = array(), $states = array() ) {
	if ( 'state' === $row['part'] ) {
		foreach ( $states as $state ) { if ( $state['id'] === $row['field'] ) { return array( 'id' => $state['id'], 'label' => $state['label'], 'type' => 'state' ); } }
		return ps_pen_paper_adventure_conditions_error();
	}
	if ( 'history' === $row['part'] ) {
		foreach ( $edges as $edge ) {
			if ( $edge['id'] === $row['field'] ) { return array( 'id' => $edge['id'], 'label' => wp_html_excerpt( $edge['choice'], 50, '' ), 'type' => 'history' ); }
		}
		return ps_pen_paper_adventure_conditions_error();
	}
	foreach ( $rules['fields'] as $field ) {
		if ( $field['id'] === $row['field'] &&
			( ( in_array( $field['type'], array( 'number', 'attribute' ), true ) && 'value' === $row['part'] ) ||
				( 'resource' === $field['type'] && in_array( $row['part'], array( 'current', 'maximum' ), true ) ) ||
				( in_array( $field['type'], array( 'text', 'textarea' ), true ) && 'text' === $row['part'] ) ) ) { return $field; }
	}
	return ps_pen_paper_adventure_conditions_error();
}

function ps_pen_paper_prepare_adventure_conditions( $rules, $input, $edges = array(), $states = array() ) {
	if ( ! is_array( $input ) || ! isset( $input['mode'], $input['rows'] ) ||
		! in_array( $input['mode'], array( 'all', 'any' ), true ) || ! is_array( $input['rows'] ) || count( $input['rows'] ) > 20 ) {
		return ps_pen_paper_adventure_conditions_error();
	}
	$conditions = array( 'mode' => $input['mode'], 'rows' => array() );
	foreach ( $input['rows'] as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['target'], $row['operator'], $row['value'] ) ||
			! is_string( $row['target'] ) || ! is_string( $row['operator'] ) || ! is_string( $row['value'] ) ) { return ps_pen_paper_adventure_conditions_error(); }
		if ( '' === $row['target'] ) {
			if ( '0' !== $row['value'] || is_wp_error( ps_pen_paper_compare_adventure_value( 0, $row['operator'], 0 ) ) ) { return ps_pen_paper_adventure_conditions_error(); }
			continue;
		}
		$target = explode( ':', $row['target'] );
		if ( 2 !== count( $target ) ) { return ps_pen_paper_adventure_conditions_error(); }
		$text = in_array( $target[1], array( 'text', 'state' ), true );
		if ( ! $text && ( ! preg_match( '/^-?\d{1,7}$/D', $row['value'] ) || (string) (int) $row['value'] !== $row['value'] ) ) { return ps_pen_paper_adventure_conditions_error(); }
		$condition = array( 'field' => $target[0], 'part' => $target[1], 'operator' => $row['operator'], 'value' => $text ? $row['value'] : (int) $row['value'] );
		if ( ! ps_pen_paper_valid_adventure_conditions( array( 'mode' => 'all', 'rows' => array( $condition ) ) ) ||
			is_wp_error( ps_pen_paper_adventure_condition_field( $condition, $rules, $edges, $states ) ) ) { return ps_pen_paper_adventure_conditions_error(); }
		$conditions['rows'][] = $condition;
	}
	if ( ! $conditions['rows'] ) { return null; }
	return ps_pen_paper_valid_adventure_conditions( $conditions ) ? $conditions : ps_pen_paper_adventure_conditions_error();
}

function ps_pen_paper_evaluate_adventure_conditions( $edge, $rules, $state, $history = array(), $edges = array(), $states = array() ) {
	if ( ! isset( $edge['conditions'] ) ) { return null; }
	$result = array( 'mode' => $edge['conditions']['mode'], 'rows' => array(), 'met' => false ); $matches = array();
	foreach ( $edge['conditions']['rows'] as $row ) {
		$field = ps_pen_paper_adventure_condition_field( $row, $rules, $edges, $states );
		if ( is_wp_error( $field ) ) { return $field; }
		$value = $state['active']['values'][ $row['field'] ] ?? null;
		if ( 'state' === $row['part'] ) {
			$values = ps_pen_paper_adventure_state_values( array( 'states' => $states, 'edges' => $edges ), $history );
			if ( is_wp_error( $values ) ) { return $values; }
			$value = $values[ $row['field'] ] ?? null;
		} elseif ( 'history' === $row['part'] ) {
			$value = 0;
			foreach ( $history as $entry ) { if ( $entry['edge_id'] === $row['field'] ) { $value = 1; break; } }
		} elseif ( in_array( $row['part'], array( 'current', 'maximum' ), true ) ) { $value = is_array( $value ) ? ( $value[ $row['part'] ] ?? null ) : null; }
		$met = ps_pen_paper_compare_adventure_value( $value, $row['operator'], $row['value'] );
		if ( is_wp_error( $met ) ) { return $met; }
		$result['rows'][] = array( 'field' => $row['field'], 'part' => $row['part'], 'operator' => $row['operator'],
			'expected' => $row['value'], 'value' => $value, 'label' => $field['label'], 'met' => $met );
		$matches[] = $met;
	}
	$result['met'] = 'all' === $result['mode'] ? ! in_array( false, $matches, true ) : in_array( true, $matches, true );
	return $result;
}

function ps_pen_paper_valid_adventure_conditions_result( $conditions, $result ) {
	if ( ! is_array( $result ) || array( 'mode', 'rows', 'met' ) !== array_keys( $result ) ||
		$result['mode'] !== $conditions['mode'] || true !== $result['met'] || ! is_array( $result['rows'] ) ||
		array_values( $result['rows'] ) !== $result['rows'] || count( $result['rows'] ) !== count( $conditions['rows'] ) ) { return false; }
	$matches = array();
	foreach ( $conditions['rows'] as $index => $condition ) {
		$row = $result['rows'][ $index ];
		if ( ! is_array( $row ) || array( 'field', 'part', 'operator', 'expected', 'value', 'label', 'met' ) !== array_keys( $row ) ||
			$row['field'] !== $condition['field'] || $row['part'] !== $condition['part'] || $row['operator'] !== $condition['operator'] ||
			$row['expected'] !== $condition['value'] || ! is_string( $row['label'] ) || '' === $row['label'] || strlen( $row['label'] ) > 200 ||
			( in_array( $condition['part'], array( 'text', 'state' ), true ) ? ! is_string( $row['value'] ) || strlen( $row['value'] ) > 20000 :
				! is_int( $row['value'] ) || abs( $row['value'] ) > 1000000 ) ) { return false; }
		$met = ps_pen_paper_compare_adventure_value( $row['value'], $row['operator'], $row['expected'] );
		if ( is_wp_error( $met ) || $row['met'] !== $met ) { return false; }
		$matches[] = $met;
	}
	return 'all' === $result['mode'] ? ! in_array( false, $matches, true ) : in_array( true, $matches, true );
}

function ps_pen_paper_valid_adventure_publication( $published ) {
	if ( ! is_array( $published ) || ps_pen_paper_adventure_graph_keys( $published, array( 'publication', 'rules_revision', 'title', 'start_scene', 'edges', 'scenes' ) ) !== array_keys( $published ) ||
		! is_int( $published['publication'] ) || $published['publication'] < 1 ||
		! is_int( $published['rules_revision'] ) || $published['rules_revision'] < 1 ||
		! is_string( $published['title'] ) || '' === trim( $published['title'] ) || strlen( $published['title'] ) > 800 ||
		! is_int( $published['start_scene'] ) || $published['start_scene'] < 1 ||
		! is_array( $published['edges'] ) || ! $published['edges'] || count( $published['edges'] ) > 100 ||
		! is_array( $published['scenes'] ) || ! $published['scenes'] || count( $published['scenes'] ) > 30 ||
		! isset( $published['scenes'][ $published['start_scene'] ] ) || ! ps_pen_paper_valid_adventure_world( $published ) ) { return false; }
	$ids = array(); $choices = array();
	foreach ( $published['edges'] as $edge ) {
		if ( ! ps_pen_paper_valid_adventure_edge( $edge ) || isset( $ids[ $edge['id'] ] ) || ! isset( $published['scenes'][ $edge['from_scene'] ] ) ) { return false; }
		$ids[ $edge['id'] ] = true;
		$key = $edge['from_scene'] . ':' . strtolower( $edge['choice'] );
		if ( isset( $choices[ $key ] ) ) { return false; }
		$choices[ $key ] = true;
		foreach ( array( $edge['success_scene'], $edge['failure_scene'] ) as $target ) {
			if ( $target && ! isset( $published['scenes'][ $target ] ) ) { return false; }
		}
	}
	foreach ( $published['edges'] as $edge ) {
		foreach ( $edge['conditions']['rows'] ?? array() as $condition ) {
			if ( 'history' === $condition['part'] && ! isset( $ids[ $condition['field'] ] ) ) { return false; }
		}
	}
	foreach ( $published['scenes'] as $id => $scene ) {
		if ( ! ctype_digit( (string) $id ) || (int) $id < 1 || ! is_array( $scene ) ||
			is_wp_error( ps_pen_paper_validate_scene_snapshot( $scene ) ) ) { return false; }
	}
	return true;
}

function ps_pen_paper_valid_adventure_state( $state ) {
	if ( ! is_array( $state ) || ! isset( $state['revision'], $state['status'], $state['publication'], $state['draft'] ) ||
		! array_key_exists( 'published', $state ) || ! is_int( $state['revision'] ) || $state['revision'] < 0 ||
		! is_string( $state['status'] ) || ! in_array( $state['status'], array( 'draft', 'published', 'paused' ), true ) ||
		! is_int( $state['publication'] ) || $state['publication'] < 0 ||
		! is_array( $state['draft'] ) || ps_pen_paper_adventure_graph_keys( $state['draft'], array( 'title', 'start_scene', 'edges' ) ) !== array_keys( $state['draft'] ) ||
		! is_string( $state['draft']['title'] ) || strlen( $state['draft']['title'] ) > 800 ||
		! is_int( $state['draft']['start_scene'] ) || $state['draft']['start_scene'] < 0 ||
		! is_array( $state['draft']['edges'] ) || count( $state['draft']['edges'] ) > 100 ||
		( null !== $state['published'] && ( ! ps_pen_paper_valid_adventure_publication( $state['published'] ) ||
			$state['publication'] !== $state['published']['publication'] ) ) ||
		( 'published' === $state['status'] && null === $state['published'] ) ||
		( $state['revision'] > 0 && ( '' === trim( $state['draft']['title'] ) || $state['draft']['start_scene'] < 1 ) ) ||
		( 0 === $state['revision'] && $state !== ps_pen_paper_adventure_default() ) ) { return false; }
	foreach ( $state['draft']['edges'] as $edge ) { if ( ! ps_pen_paper_valid_adventure_edge( $edge ) ) { return false; } }
	return ps_pen_paper_valid_adventure_world( $state['draft'] );
}

function ps_pen_paper_adventure_character( $campaign, $character_id, $owner_only = false ) {
	$character = $campaign ? ps_pen_paper_get_character( $campaign, $character_id ) : null;
	if ( ! $character || ! is_user_member_of_blog( (int) $character->post_author ) || ! ps_pen_paper_campaign_role( $campaign, (int) $character->post_author ) ||
		( $owner_only && (int) $character->post_author !== get_current_user_id() ) ) { return ps_pen_paper_adventure_error(); }
	$state = ps_pen_paper_character_state( $campaign, $character );
	if ( is_wp_error( $state ) || ! $state['active'] ) { return is_wp_error( $state ) ? $state : ps_pen_paper_adventure_error(); }
	return array( 'post' => $character, 'state' => $state );
}

function ps_pen_paper_adventure_scene( $campaign, $id ) {
	$state = ps_pen_paper_get_scene( $campaign, $id );
	if ( is_wp_error( $state ) ) { return $state; }
	return $state['published'] ? $state['published'] : ps_pen_paper_adventure_error();
}

function ps_pen_paper_adventure_validate_draft( $campaign, $draft, $rules ) {
	if ( ! is_array( $draft ) || ! isset( $draft['title'], $draft['start_scene'], $draft['edges'] ) ||
		! is_string( $draft['title'] ) || '' === trim( $draft['title'] ) || strlen( $draft['title'] ) > 800 ||
		! is_int( $draft['start_scene'] ) || $draft['start_scene'] < 1 || ! is_array( $draft['edges'] ) || count( $draft['edges'] ) > 100 ||
		is_wp_error( ps_pen_paper_adventure_scene( $campaign, $draft['start_scene'] ) ) ) { return ps_pen_paper_adventure_error(); }
	$choices = array(); $scene_ids = array( $draft['start_scene'] => true );
	foreach ( $draft['edges'] as $edge ) {
		if ( ! ps_pen_paper_valid_adventure_edge( $edge ) || is_wp_error( ps_pen_paper_adventure_scene( $campaign, $edge['from_scene'] ) ) ) { return ps_pen_paper_adventure_error(); }
		$choices[ $edge['from_scene'] ] = isset( $choices[ $edge['from_scene'] ] ) ? $choices[ $edge['from_scene'] ] : array();
		$key = strtolower( $edge['choice'] );
		if ( isset( $choices[ $edge['from_scene'] ][ $key ] ) ) { return ps_pen_paper_adventure_error(); }
		$choices[ $edge['from_scene'] ][ $key ] = true; $scene_ids[ $edge['from_scene'] ] = true;
		if ( isset( $edge['requirement'] ) ) {
			$field = null;
			foreach ( $rules['fields'] as $candidate ) {
				if ( $candidate['id'] === $edge['requirement']['attribute'] && in_array( $candidate['type'], array( 'attribute', 'number' ), true ) ) { $field = $candidate; break; }
			}
			if ( ! $field ) { return ps_pen_paper_adventure_error(); }
		}
		if ( isset( $edge['resource_cost'] ) ) {
			$field = null;
			foreach ( $rules['fields'] as $candidate ) {
				if ( $candidate['id'] === $edge['resource_cost']['resource'] && 'resource' === $candidate['type'] ) { $field = $candidate; break; }
			}
			if ( ! $field ) { return ps_pen_paper_adventure_error(); }
		}
		if ( isset( $edge['reward'] ) ) {
			if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
			$reward = $edge['reward']; $reward['authorizer_id'] = get_current_user_id();
			$authority = ps_pen_paper_adventure_reward_authority( $campaign, $reward );
			if ( is_wp_error( $authority ) ) { return $authority; }
		}
		if ( isset( $edge['conditions'] ) ) {
			foreach ( $edge['conditions']['rows'] as $condition ) {
				if ( is_wp_error( $error = ps_pen_paper_adventure_condition_field( $condition, $rules, $draft['edges'], $draft['states'] ?? array() ) ) ) { return $error; }
			}
		}
		if ( '' !== $edge['expression'] ) {
			$parsed = ps_pen_paper_parse_dice( $edge['expression'], $rules['dice'] );
			if ( is_wp_error( $parsed ) || $parsed['expression'] !== $edge['expression'] ) { return ps_pen_paper_adventure_error(); }
			if ( $edge['attribute'] ) {
				$field = null;
				foreach ( $rules['fields'] as $candidate ) { if ( $candidate['id'] === $edge['attribute'] && in_array( $candidate['type'], array( 'attribute', 'number' ), true ) ) { $field = $candidate; break; } }
				if ( ! $field ) { return ps_pen_paper_adventure_error(); }
			}
		}
		foreach ( array( $edge['success_scene'], $edge['failure_scene'] ) as $target ) {
			if ( $target ) {
				if ( is_wp_error( ps_pen_paper_adventure_scene( $campaign, $target ) ) ) { return ps_pen_paper_adventure_error(); }
				$scene_ids[ $target ] = true;
			}
		}
	}
	if ( ! $draft['edges'] || ! isset( $choices[ $draft['start_scene'] ] ) || count( $scene_ids ) > 30 ) { return ps_pen_paper_adventure_error(); }
	$adjacency = array(); foreach ( $draft['edges'] as $edge ) {
		foreach ( array( $edge['success_scene'], $edge['failure_scene'] ) as $target ) { if ( $target ) { $adjacency[ $edge['from_scene'] ][ $target ] = true; } }
	}
	$visiting = array(); $visited = array(); $visit = function ( $node ) use ( &$visit, &$visiting, &$visited, $adjacency ) {
		if ( isset( $visiting[ $node ] ) ) { return false; }
		if ( isset( $visited[ $node ] ) ) { return true; }
		$visiting[ $node ] = true;
		foreach ( isset( $adjacency[ $node ] ) ? array_keys( $adjacency[ $node ] ) : array() as $next ) { if ( ! $visit( $next ) ) { return false; } }
		unset( $visiting[ $node ] ); $visited[ $node ] = true; return true;
	};
	if ( ! $visit( $draft['start_scene'] ) || array_diff_key( $scene_ids, $visited ) ) { return ps_pen_paper_adventure_error(); }
	return true;
}

function ps_pen_paper_get_adventure( $campaign, $id ) {
	$post = ps_pen_paper_record_post( $id ); $state = get_post_meta( $id, '_pspp_adventure', true );
	if ( ! $post || 'pspp_adventure' !== $post->post_type || 'private' !== $post->post_status ||
		(int) $post->post_parent !== (int) $campaign->ID || ! ps_pen_paper_valid_adventure_state( $state ) || ! $state['revision'] ) { return ps_pen_paper_adventure_error(); }
	return $state;
}

function ps_pen_paper_adventure_records( $campaign, $gm_only = true ) {
	if ( ! ps_pen_paper_can_access_site() || ( $gm_only ? ! ps_pen_paper_can_manage_campaign( $campaign ) : ! ps_pen_paper_can_view_campaign( $campaign ) ) ) { return ps_pen_paper_adventure_error(); }
	$records = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_adventure', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
		$state = ps_pen_paper_get_adventure( $campaign, $post->ID ); if ( is_wp_error( $state ) ) { return $state; }
		if ( $gm_only || null !== $state['published'] ) { $records[ $post->ID ] = $state; }
	}
	return $records;
}

function ps_pen_paper_adventure_locks( $ids, $write ) {
	return ps_pen_paper_with_character_locks( $ids, $write );
}

function ps_pen_paper_lock_adventure_campaign( $campaign ) {
	global $wpdb;
	if ( ! $campaign || (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $campaign->ID ) ) !== (int) $campaign->ID ) { return ps_pen_paper_adventure_error(); }
	clean_post_cache( $campaign->ID ); $fresh = ps_pen_paper_get_campaign( $campaign->ID );
	return $fresh && ps_pen_paper_can_manage_campaign( $fresh ) ? $fresh : ps_pen_paper_adventure_error();
}

function ps_pen_paper_write_adventure_event( $campaign, $id, $before, $after, $kind, $visibility = 'gm', $recipient = 0, $new = false ) {
	$after['revision'] = $before['revision'] + 1;
	$validate = 'adventure_run' === $kind ? 'ps_pen_paper_valid_adventure_run' : 'ps_pen_paper_valid_adventure_state';
	if ( ! $validate( $before ) || ! $validate( $after ) ||
		( 'adventure_run' === $kind && ( 'owner' !== $visibility || ! isset( $after['owner_id'] ) || $recipient !== $after['owner_id'] ) ) ||
		( 'adventure_run' !== $kind && 'gm' !== $visibility ) ) { return ps_pen_paper_adventure_error(); }
	$key = 'adventure_run' === $kind ? '_pspp_adventure_run' : '_pspp_adventure';
	return ps_pen_paper_write_document_event( $campaign->ID, $id, $key, $before, $after,
		array( 'kind' => $kind, 'visibility' => $visibility, 'recipient_id' => $recipient,
			'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $campaign->ID )['revision'] ), $new );
}

function ps_pen_paper_save_adventure( $campaign, $id, $title, $scene_id, $revision ) {
	return ps_pen_paper_scope_write( $campaign, function () use ( $campaign, $id, $title, $scene_id, $revision ) {
		return ps_pen_paper_save_adventure_locked( $campaign, $id, $title, $scene_id, $revision );
	} );
}

function ps_pen_paper_save_adventure_locked( $campaign, $id, $title, $scene_id, $revision ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) || ! is_string( $title ) ||
		! is_string( $scene_id ) || ! preg_match( '/^[1-9]\d{0,18}$/D', $scene_id ) || (string) (int) $scene_id !== $scene_id ) { return ps_pen_paper_adventure_error(); }
	$id = (int) $id; $locks = array_filter( array( (int) $campaign->ID, $id ) ); sort( $locks, SORT_NUMERIC );
	$result = ps_pen_paper_adventure_locks( $locks, function () use ( $campaign, &$id, $title, $scene_id, $revision ) {
		$before = $id ? ps_pen_paper_get_adventure( $campaign, $id ) : ps_pen_paper_adventure_default();
		if ( is_wp_error( $before ) || ! is_string( $revision ) || (string) $before['revision'] !== $revision ||
			is_wp_error( ps_pen_paper_adventure_scene( $campaign, (int) $scene_id ) ) ) { return ps_pen_paper_adventure_error(); }
		$after = $before; $after['draft']['title'] = sanitize_text_field( $title ); $after['draft']['start_scene'] = (int) $scene_id;
		if ( '' === trim( $after['draft']['title'] ) || strlen( $after['draft']['title'] ) > 800 ) { return ps_pen_paper_adventure_error(); }
		if ( $after['draft']['edges'] && is_wp_error( ps_pen_paper_adventure_validate_draft( $campaign, $after['draft'], ps_pen_paper_get_rules( $campaign->ID ) ) ) ) { return ps_pen_paper_adventure_error(); }
		$after['status'] = 'draft';
		return ps_pen_paper_atomic_event( $id ? $id : (int) $campaign->ID, function () use ( $campaign, &$id, $before, $after ) {
			$fresh = ps_pen_paper_lock_adventure_campaign( $campaign );
			if ( is_wp_error( $fresh ) ) { return $fresh; }
			global $wpdb;
			$lock_id = $id ? $id : (int) $campaign->ID;
			if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $lock_id ) ) !== $lock_id ) { return ps_pen_paper_adventure_error(); }
			if ( ! $id ) {
				$id = wp_insert_post( array( 'post_type' => 'pspp_adventure', 'post_status' => 'draft', 'post_parent' => $campaign->ID, 'post_title' => 'PS Pen & Paper Adventure' ), true );
				if ( is_wp_error( $id ) || ! $id ) { return ps_pen_paper_adventure_error(); }
			}
			return ps_pen_paper_write_adventure_event( $fresh, $id, $before, $after, 'adventure_saved', 'gm', 0, 0 === $before['revision'] );
		} );
	} );
	return is_wp_error( $result ) ? $result : $id;
}

function ps_pen_paper_save_adventure_edge( $campaign, $id, $input, $revision, $remove_id = '' ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) || ! is_array( $input ) ) { return ps_pen_paper_adventure_error(); }
	return ps_pen_paper_adventure_locks( array( (int) $id ), function () use ( $campaign, $id, $input, $revision, $remove_id ) {
		$before = ps_pen_paper_get_adventure( $campaign, $id ); $rules = ps_pen_paper_get_rules( $campaign->ID );
		if ( is_wp_error( $before ) || ! is_string( $revision ) || (string) $before['revision'] !== $revision ) { return ps_pen_paper_adventure_error(); }
		$after = $before; $after['status'] = 'draft';
		if ( $remove_id ) {
			$after['draft']['edges'] = array_values( array_filter( $after['draft']['edges'], function ( $edge ) use ( $remove_id ) { return $edge['id'] !== $remove_id; } ) );
			if ( count( $after['draft']['edges'] ) === count( $before['draft']['edges'] ) ) { return ps_pen_paper_adventure_error(); }
		} else {
			foreach ( array( 'from_scene', 'choice', 'expression', 'attribute', 'threshold', 'success_scene', 'failure_scene' ) as $key ) { if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) { return ps_pen_paper_adventure_error(); } }
			foreach ( array( 'from_scene', 'success_scene', 'failure_scene', 'threshold' ) as $key ) {
				if ( ! preg_match( $key === 'threshold' ? '/^-?\d{1,7}$/D' : '/^(?:0|[1-9]\d{0,18})$/D', $input[ $key ] ) || (string) (int) $input[ $key ] !== $input[ $key ] ) { return ps_pen_paper_adventure_error(); }
			}
			$expression = strtoupper( preg_replace( '/\s+/', '', $input['expression'] ) );
			if ( '' !== $expression ) {
				$parsed = ps_pen_paper_parse_dice( $expression, $rules['dice'] );
				if ( is_wp_error( $parsed ) ) { return ps_pen_paper_adventure_error(); }
				$expression = $parsed['expression'];
			}
			$edge = array( 'id' => wp_generate_uuid4(), 'from_scene' => (int) $input['from_scene'], 'choice' => sanitize_text_field( $input['choice'] ),
				'expression' => $expression, 'attribute' => $input['attribute'],
				'threshold' => (int) $input['threshold'], 'success_scene' => (int) $input['success_scene'], 'failure_scene' => (int) $input['failure_scene'] );
			if ( array_key_exists( 'requirement_attribute', $input ) || array_key_exists( 'requirement_minimum', $input ) ) {
				if ( ! isset( $input['requirement_attribute'], $input['requirement_minimum'] ) ||
					! is_string( $input['requirement_attribute'] ) || ! is_string( $input['requirement_minimum'] ) ||
					! preg_match( '/^-?\d{1,7}$/D', $input['requirement_minimum'] ) ||
					(string) (int) $input['requirement_minimum'] !== $input['requirement_minimum'] ||
					abs( (int) $input['requirement_minimum'] ) > 1000000 ) { return ps_pen_paper_adventure_error(); }
				$edge['requirement'] = '' === $input['requirement_attribute'] ? null :
					array( 'attribute' => $input['requirement_attribute'], 'minimum' => (int) $input['requirement_minimum'] );
			}
			if ( array_key_exists( 'cost_resource', $input ) || array_key_exists( 'cost_amount', $input ) || array_key_exists( 'cost_timing', $input ) ) {
				if ( ! isset( $input['cost_resource'], $input['cost_amount'], $input['cost_timing'] ) ||
					! is_string( $input['cost_resource'] ) || ! is_string( $input['cost_amount'] ) ||
					! is_string( $input['cost_timing'] ) || ! in_array( $input['cost_timing'], array( 'attempt', 'success' ), true ) ||
					! preg_match( '/^(?:0|[1-9]\d{0,6})$/D', $input['cost_amount'] ) ||
					(int) $input['cost_amount'] > 1000000 ||
					( '' === $input['cost_resource'] ) !== ( '0' === $input['cost_amount'] ) ) { return ps_pen_paper_adventure_error(); }
				$edge['resource_cost'] = '' === $input['cost_resource'] ? null :
					array( 'resource' => $input['cost_resource'], 'amount' => (int) $input['cost_amount'], 'timing' => $input['cost_timing'] );
			}
			if ( array_key_exists( 'reward', $input ) ) {
				$edge['reward'] = ps_pen_paper_prepare_adventure_reward( $campaign, $input['reward'] );
				if ( is_wp_error( $edge['reward'] ) ) { return $edge['reward']; }
			}
			if ( array_key_exists( 'conditions', $input ) ) {
				$edge['conditions'] = ps_pen_paper_prepare_adventure_conditions( $rules, $input['conditions'], $before['draft']['edges'], $before['draft']['states'] ?? array() );
				if ( is_wp_error( $edge['conditions'] ) ) { return $edge['conditions']; }
			}
			if ( array_key_exists( 'gm_review', $input ) ) {
				if ( '1' !== $input['gm_review'] ) { return ps_pen_paper_adventure_error(); }
				$edge['gm_review'] = true;
			}
			if ( array_key_exists( 'items', $input ) ) {
				$edge['items'] = ps_pen_paper_prepare_adventure_items( $campaign, $input['items'] );
				if ( is_wp_error( $edge['items'] ) ) { return $edge['items']; }
			}
			if ( array_key_exists( 'effects', $input ) ) {
				$edge['effects'] = ps_pen_paper_prepare_adventure_effects( $input['effects'], $before['draft']['states'] ?? array() );
				if ( is_wp_error( $edge['effects'] ) ) { return $edge['effects']; }
			}
			if ( ! ps_pen_paper_valid_adventure_edge( $edge ) ) { return ps_pen_paper_adventure_error(); }
			$after['draft']['edges'][] = $edge;
		}
		if ( count( $after['draft']['edges'] ) > 100 || ( $after['draft']['edges'] && is_wp_error( ps_pen_paper_adventure_validate_draft( $campaign, $after['draft'], $rules ) ) ) ) { return ps_pen_paper_adventure_error(); }
		return ps_pen_paper_atomic_event( $id, function () use ( $campaign, $id, $before, $after ) {
			$fresh = ps_pen_paper_lock_adventure_campaign( $campaign );
			if ( is_wp_error( $fresh ) ) { return $fresh; }
			$current = ps_pen_paper_get_adventure( $fresh, $id );
			if ( is_wp_error( $current ) || $current['revision'] !== $before['revision'] ) { return ps_pen_paper_adventure_error(); }
			return ps_pen_paper_write_adventure_event( $fresh, $id, $before, $after, 'adventure_saved' );
		} );
	} );
}

function ps_pen_paper_publish_adventure( $campaign, $id, $revision, $confirmation, $pause = false ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) || 'confirmed' !== $confirmation ) { return ps_pen_paper_adventure_error(); }
	$lock_ids = array( (int) $id ); sort( $lock_ids, SORT_NUMERIC );
	return ps_pen_paper_adventure_locks( $lock_ids, function () use ( $campaign, $id, $revision, $pause ) {
		$before = ps_pen_paper_get_adventure( $campaign, $id ); $rules = ps_pen_paper_get_rules( $campaign->ID );
		if ( is_wp_error( $before ) || ! is_string( $revision ) || (string) $before['revision'] !== $revision ) { return ps_pen_paper_adventure_error(); }
		$after = $before;
		if ( $pause ) {
			if ( 'published' !== $before['status'] ) { return ps_pen_paper_adventure_error(); }
			$after['status'] = 'paused';
		} elseif ( 'paused' === $before['status'] && null !== $before['published'] ) {
			$after['status'] = 'published';
		} else {
			if ( is_wp_error( ps_pen_paper_adventure_validate_draft( $campaign, $before['draft'], $rules ) ) ) { return ps_pen_paper_adventure_error(); }
			$after['status'] = 'published';
		}
		return ps_pen_paper_atomic_event( $id, function () use ( $campaign, $id, $before, $after ) {
			global $wpdb;
			$fresh = ps_pen_paper_lock_adventure_campaign( $campaign );
			if ( is_wp_error( $fresh ) ) { return $fresh; }
			$current = ps_pen_paper_get_adventure( $fresh, $id );
			if ( is_wp_error( $current ) || $current['revision'] !== $before['revision'] ) { return ps_pen_paper_adventure_error(); }
			if ( 'published' === $after['status'] && 'paused' !== $before['status'] ) {
				$rules = ps_pen_paper_get_rules( $fresh->ID );
				if ( is_wp_error( ps_pen_paper_adventure_validate_draft( $fresh, $current['draft'], $rules ) ) ) { return ps_pen_paper_adventure_error(); }
				$scene_ids = array( $current['draft']['start_scene'] );
				foreach ( $current['draft']['edges'] as $edge ) { $scene_ids[] = $edge['from_scene']; if ( $edge['success_scene'] ) { $scene_ids[] = $edge['success_scene']; } if ( $edge['failure_scene'] ) { $scene_ids[] = $edge['failure_scene']; } }
				$scene_ids = array_unique( $scene_ids );
				sort( $scene_ids, SORT_NUMERIC );
				$scenes = array();
				foreach ( $scene_ids as $scene_id ) {
					if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $scene_id ) ) !== $scene_id ) { return ps_pen_paper_adventure_error(); }
					clean_post_cache( $scene_id );
					$scenes[ $scene_id ] = ps_pen_paper_adventure_scene( $fresh, $scene_id );
				}
				$after = $current; $after['publication']++;
				$edges = $current['draft']['edges'];
				foreach ( $edges as &$edge ) {
					if ( isset( $edge['reward'] ) ) { $edge['reward']['authorizer_id'] = get_current_user_id(); }
				}
				unset( $edge );
				$after['published'] = array( 'publication' => $after['publication'], 'rules_revision' => $rules['revision'],
					'title' => $current['draft']['title'], 'start_scene' => $current['draft']['start_scene'],
					'edges' => $edges, 'scenes' => $scenes );
				foreach ( array( 'states', 'encounters' ) as $key ) {
					if ( array_key_exists( $key, $current['draft'] ) ) { $after['published'][ $key ] = $current['draft'][ $key ]; }
				}
				$after['status'] = 'published';
				if ( ! ps_pen_paper_valid_adventure_publication( $after['published'] ) ) { return ps_pen_paper_adventure_error(); }
			}
			return ps_pen_paper_write_adventure_event( $fresh, $id, $before, $after, 'adventure_published' );
		} );
	} );
}

function ps_pen_paper_valid_adventure_run( $run, $prior_history = array() ) {
	if ( ! is_array( $prior_history ) || ! is_array( $run ) || ! isset( $run['revision'], $run['status'], $run['history'] ) ||
		! is_int( $run['revision'] ) || $run['revision'] < 0 || ! is_array( $run['history'] ) ||
		array_values( $run['history'] ) !== $run['history'] ) { return false; }
	if ( 0 === $run['revision'] ) { return array( 'revision', 'status', 'history' ) === array_keys( $run ) && 'new' === $run['status'] && ! $run['history']; }
	$keys = array( 'revision', 'status', 'adventure_id', 'adventure_publication', 'character_id', 'owner_id', 'scene_id', 'snapshot', 'history' );
	$reviews = 0;
	if ( array_key_exists( 'reviews', $run ) || array_key_exists( 'approval', $run ) ) {
		$keys[] = 'reviews'; $keys[] = 'approval';
		if ( ! isset( $run['reviews'] ) || ! is_int( $run['reviews'] ) || $run['reviews'] < 1 ||
			! array_key_exists( 'approval', $run ) || ! isset( $run['owner_id'] ) ||
			( null !== $run['approval'] && ! ps_pen_paper_valid_adventure_review( $run['approval'], $run['owner_id'] ) ) ) { return false; }
		$reviews = $run['reviews'];
	}
	if ( $keys !== array_keys( $run ) ||
		! in_array( $run['status'], array( 'active', 'completed' ), true ) ||
		! isset( $run['adventure_id'], $run['adventure_publication'], $run['character_id'], $run['owner_id'], $run['scene_id'], $run['snapshot'] ) ||
		! is_int( $run['adventure_id'] ) || $run['adventure_id'] < 1 || ! is_int( $run['adventure_publication'] ) || $run['adventure_publication'] < 1 ||
		! is_int( $run['character_id'] ) || $run['character_id'] < 1 || ! is_int( $run['owner_id'] ) || $run['owner_id'] < 1 ||
		! is_int( $run['scene_id'] ) || $run['scene_id'] < 0 || count( $run['history'] ) > 30 ||
		$run['revision'] !== count( $run['history'] ) + 1 + $reviews || ! ps_pen_paper_valid_adventure_publication( $run['snapshot'] ) ||
		$run['adventure_publication'] !== $run['snapshot']['publication'] ||
		( 'active' === $run['status'] && ( ! $run['scene_id'] || ! isset( $run['snapshot']['scenes'][ $run['scene_id'] ] ) ) ) ||
		( 'completed' === $run['status'] && $run['scene_id'] && ! isset( $run['snapshot']['scenes'][ $run['scene_id'] ] ) ) ||
		! is_array( $run['history'] ) ) { return false; }
	$scene_id = $run['snapshot']['start_scene'];
	$seen = array();
	foreach ( $prior_history as $prior ) {
		if ( ! is_array( $prior ) || ! isset( $prior['edge_id'] ) || ! is_string( $prior['edge_id'] ) || ! wp_is_uuid( $prior['edge_id'] ) ) { return false; }
		$seen[ $prior['edge_id'] ] = true;
	}
	$required_reviews = isset( $run['approval'] ) ? ( 'approved' === $run['approval']['status'] ? 2 : 1 ) : 0;
	foreach ( $run['history'] as $entry ) {
		$keys = array( 'edge_id', 'source_scene', 'choice', 'roll', 'target_scene', 'date' );
		if ( is_array( $entry ) && array_key_exists( 'requirement', $entry ) ) { $keys[] = 'requirement'; }
		if ( is_array( $entry ) && array_key_exists( 'resource_cost', $entry ) ) { $keys[] = 'resource_cost'; }
		if ( is_array( $entry ) && array_key_exists( 'reward', $entry ) ) { $keys[] = 'reward'; }
		if ( is_array( $entry ) && array_key_exists( 'conditions', $entry ) ) { $keys[] = 'conditions'; }
		if ( is_array( $entry ) && array_key_exists( 'review', $entry ) ) { $keys[] = 'review'; }
		if ( is_array( $entry ) && array_key_exists( 'items', $entry ) ) { $keys[] = 'items'; }
		if ( is_array( $entry ) && array_key_exists( 'effects', $entry ) ) { $keys[] = 'effects'; }
		if ( ! is_array( $entry ) || $keys !== array_keys( $entry ) ||
			! is_string( $entry['edge_id'] ) || ! wp_is_uuid( $entry['edge_id'] ) || ! is_int( $entry['source_scene'] ) ||
			! is_string( $entry['choice'] ) || ! ( null === $entry['roll'] || ( is_array( $entry['roll'] ) && isset( $entry['roll']['total'], $entry['roll']['threshold'], $entry['roll']['success'] ) &&
				is_int( $entry['roll']['total'] ) && is_int( $entry['roll']['threshold'] ) && is_bool( $entry['roll']['success'] ) ) ) ||
			! is_int( $entry['target_scene'] ) || $entry['target_scene'] < 0 || ! is_string( $entry['date'] ) ||
			! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $entry['date'] ) ||
			$entry['source_scene'] !== $scene_id ) { return false; }
		$edge = null;
		foreach ( $run['snapshot']['edges'] as $candidate ) {
			if ( $candidate['id'] === $entry['edge_id'] && $candidate['from_scene'] === $scene_id ) { $edge = $candidate; break; }
		}
		if ( ! $edge || $edge['choice'] !== $entry['choice'] ) { return false; }
		if ( ! empty( $edge['gm_review'] ) ) {
			$required_reviews += 2;
			if ( ! isset( $entry['review'] ) || ! ps_pen_paper_valid_adventure_review( $entry['review'], $run['owner_id'] ) ||
				'approved' !== $entry['review']['status'] || $entry['review']['edge_id'] !== $edge['id'] ) { return false; }
		} elseif ( array_key_exists( 'review', $entry ) ) { return false; }
		if ( isset( $edge['requirement'] ) ) {
			if ( ! isset( $entry['requirement'] ) || ! is_array( $entry['requirement'] ) ||
				array( 'id', 'label', 'value', 'minimum' ) !== array_keys( $entry['requirement'] ) ||
				$entry['requirement']['id'] !== $edge['requirement']['attribute'] || ! is_string( $entry['requirement']['label'] ) ||
				! is_int( $entry['requirement']['value'] ) || $entry['requirement']['minimum'] !== $edge['requirement']['minimum'] ||
				$entry['requirement']['value'] < $entry['requirement']['minimum'] ) { return false; }
		} elseif ( isset( $entry['requirement'] ) ) { return false; }
		if ( isset( $edge['conditions'] ) ) {
			if ( ! isset( $entry['conditions'] ) || ! ps_pen_paper_valid_adventure_conditions_result( $edge['conditions'], $entry['conditions'] ) ) { return false; }
			foreach ( $edge['conditions']['rows'] as $index => $condition ) {
				if ( 'history' === $condition['part'] && $entry['conditions']['rows'][ $index ]['value'] !== ( isset( $seen[ $condition['field'] ] ) ? 1 : 0 ) ) { return false; }
				if ( 'state' === $condition['part'] ) {
					$values = ps_pen_paper_adventure_state_values( $run['snapshot'], $prior_history );
					if ( is_wp_error( $values ) || ( $values[ $condition['field'] ] ?? null ) !== $entry['conditions']['rows'][ $index ]['value'] ) { return false; }
				}
			}
		} elseif ( isset( $entry['conditions'] ) ) { return false; }
		if ( '' === $edge['expression'] ) {
			if ( null !== $entry['roll'] || $edge['success_scene'] !== $entry['target_scene'] ) { return false; }
		} else {
			$roll = $entry['roll'];
			$parsed = ps_pen_paper_parse_dice( $edge['expression'], array( '4', '6', '8', '10', '12', '20', '100' ) );
			if ( is_wp_error( $parsed ) || ! is_array( $roll ) ||
				array( 'expression', 'groups', 'modifier', 'attribute', 'total', 'threshold', 'success', 'rules_revision' ) !== array_keys( $roll ) ||
				$parsed['expression'] !== $roll['expression'] || ! is_array( $roll['groups'] ) || array_values( $roll['groups'] ) !== $roll['groups'] ||
				count( $parsed['groups'] ) !== count( $roll['groups'] ) ||
				! is_int( $roll['modifier'] ) || $roll['modifier'] !== $parsed['modifier'] ||
				! is_int( $roll['total'] ) || $roll['threshold'] !== $edge['threshold'] ||
				$roll['rules_revision'] !== $run['snapshot']['rules_revision'] ||
				( '' === $edge['attribute'] ) !== ( null === $roll['attribute'] ) ||
				! ( null === $roll['attribute'] || ( is_array( $roll['attribute'] ) && array( 'id', 'label', 'value' ) === array_keys( $roll['attribute'] ) &&
					$roll['attribute']['id'] === $edge['attribute'] && is_string( $roll['attribute']['label'] ) && is_int( $roll['attribute']['value'] ) ) ) ) { return false; }
			$total = $roll['modifier'];
			foreach ( $parsed['groups'] as $index => $group ) {
				$rolled = $roll['groups'][ $index ];
				if ( ! is_array( $rolled ) || array( 'sides', 'rolls' ) !== array_keys( $rolled ) ||
					$group['sides'] !== $rolled['sides'] || ! is_array( $rolled['rolls'] ) || array_values( $rolled['rolls'] ) !== $rolled['rolls'] ||
					count( $rolled['rolls'] ) !== $group['count'] ) { return false; }
				foreach ( $rolled['rolls'] as $value ) {
					if ( ! is_int( $value ) || $value < 1 || $value > $group['sides'] ) { return false; }
					$total += $value;
				}
			}
			if ( $roll['attribute'] ) { $total += $roll['attribute']['value']; }
			if ( $roll['total'] !== $total || ! is_bool( $roll['success'] ) || $roll['success'] !== ( $total >= $edge['threshold'] ) ) { return false; }
			$target = $roll['success'] ? $edge['success_scene'] : $edge['failure_scene'];
			if ( $target !== $entry['target_scene'] ) { return false; }
		}
		if ( isset( $edge['resource_cost'] ) ) {
			$charge = 'attempt' === $edge['resource_cost']['timing'] || null === $entry['roll'] || $entry['roll']['success'];
			if ( ! array_key_exists( 'resource_cost', $entry ) ||
				( $charge && ( ! ps_pen_paper_valid_character_resource_cost( $entry['resource_cost'] ) ||
					$entry['resource_cost']['id'] !== $edge['resource_cost']['resource'] ||
					$entry['resource_cost']['amount'] !== $edge['resource_cost']['amount'] ) ) ||
				( ! $charge && null !== $entry['resource_cost'] ) ) { return false; }
		} elseif ( isset( $entry['resource_cost'] ) ) { return false; }
		if ( isset( $edge['reward'] ) ) {
			$grant = 'decision' === $edge['reward']['timing'] || null === $entry['roll'] || $entry['roll']['success'];
			if ( ! array_key_exists( 'reward', $entry ) ||
				( $grant && ! ps_pen_paper_valid_adventure_reward_result( $edge['reward'], $entry['reward'], $run['character_id'] ) ) ||
				( ! $grant && null !== $entry['reward'] ) ) { return false; }
		} elseif ( isset( $entry['reward'] ) ) { return false; }
		if ( isset( $edge['items'] ) ) {
			if ( ! isset( $entry['items'] ) || ! ps_pen_paper_valid_adventure_items_result( $edge['items'], $entry['items'] ) ) { return false; }
		} elseif ( array_key_exists( 'items', $entry ) ) { return false; }
		if ( isset( $edge['effects'] ) ) {
			$effects = ps_pen_paper_adventure_effects_result( $edge, $run['snapshot'], $prior_history, $entry['roll'] );
			if ( is_wp_error( $effects ) || ! array_key_exists( 'effects', $entry ) || $entry['effects'] !== $effects ) { return false; }
		} elseif ( array_key_exists( 'effects', $entry ) ) { return false; }
		$prior_history[] = $entry;
		$scene_id = $entry['target_scene'];
		$seen[ $entry['edge_id'] ] = true;
	}
	$next_edges = array_filter( $run['snapshot']['edges'], function ( $edge ) use ( $scene_id ) { return $scene_id && $edge['from_scene'] === $scene_id; } );
	if ( isset( $run['approval'] ) ) {
		$pending_edge = null;
		foreach ( $next_edges as $candidate ) {
			if ( $candidate['id'] === $run['approval']['edge_id'] && ! empty( $candidate['gm_review'] ) ) { $pending_edge = $candidate; break; }
		}
		if ( ! $pending_edge || 'active' !== $run['status'] ) { return false; }
	}
	return $reviews >= $required_reviews && $run['scene_id'] === $scene_id && ( $scene_id && $next_edges ? 'active' : 'completed' ) === $run['status'];
}

function ps_pen_paper_adventure_run_records( $campaign, $character_id ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) ) { return ps_pen_paper_adventure_error(); }
	$records = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_adventure_run', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
		$run = get_post_meta( $post->ID, '_pspp_adventure_run', true );
		if ( ! ps_pen_paper_valid_adventure_run( $run ) ) { return ps_pen_paper_adventure_error(); }
		if ( $run['revision'] && $run['character_id'] === (int) $character_id &&
			( $run['owner_id'] === get_current_user_id() || ps_pen_paper_can_manage_campaign( $campaign ) ) ) { $records[ $post->ID ] = $run; }
	}
	return $records;
}

function ps_pen_paper_start_adventure_run( $campaign, $adventure_id, $character_id, $adventure_revision, $character_revision ) {
	return ps_pen_paper_scope_write( $campaign, function () use ( $campaign, $adventure_id, $character_id, $adventure_revision, $character_revision ) {
		return ps_pen_paper_start_adventure_run_locked( $campaign, $adventure_id, $character_id, $adventure_revision, $character_revision );
	} );
}

function ps_pen_paper_start_adventure_run_locked( $campaign, $adventure_id, $character_id, $adventure_revision, $character_revision ) {
	if ( ! $campaign || 'pspp_campaign' !== $campaign->post_type || ! ps_pen_paper_can_view_campaign( $campaign ) ) { return ps_pen_paper_adventure_error(); }
	$run_id = 0; $locks = array( (int) $adventure_id, (int) $character_id ); sort( $locks, SORT_NUMERIC );
	$result = ps_pen_paper_adventure_locks( $locks, function () use ( $campaign, $adventure_id, $character_id, $adventure_revision, $character_revision, &$run_id ) {
		return ps_pen_paper_atomic_event( (int) $campaign->ID, function () use ( $campaign, $adventure_id, $character_id, $adventure_revision, $character_revision, &$run_id ) {
			global $wpdb;
			foreach ( array( (int) $adventure_id, (int) $character_id ) as $id ) {
				if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $id ) ) !== $id ) { return ps_pen_paper_adventure_error(); }
				clean_post_cache( $id );
			}
			$adventure = ps_pen_paper_get_adventure( $campaign, $adventure_id );
			$character = ps_pen_paper_adventure_character( $campaign, $character_id, true );
			if ( is_wp_error( $adventure ) || is_wp_error( $character ) || 'published' !== $adventure['status'] ||
				! is_string( $adventure_revision ) || (string) $adventure['revision'] !== $adventure_revision ||
				! is_string( $character_revision ) || (string) $character['state']['revision'] !== $character_revision ) { return ps_pen_paper_adventure_error(); }
			foreach ( get_posts( array( 'post_type' => 'pspp_adventure_run', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1 ) ) as $existing ) {
				$state = get_post_meta( $existing->ID, '_pspp_adventure_run', true );
				if ( ! ps_pen_paper_valid_adventure_run( $state ) ) { return ps_pen_paper_adventure_error(); }
				if ( $state['revision'] && $state['adventure_id'] === (int) $adventure_id && $state['character_id'] === (int) $character_id ) {
					if ( $state['owner_id'] !== get_current_user_id() ) { return ps_pen_paper_adventure_error(); }
					$run_id = (int) $existing->ID; return 0;
				}
			}
			$snapshot = $adventure['published'];
			$before = array( 'revision' => 0, 'status' => 'new', 'history' => array() );
			$after = array( 'revision' => 0, 'status' => 'active', 'adventure_id' => (int) $adventure_id,
				'adventure_publication' => $snapshot['publication'], 'character_id' => (int) $character_id,
				'owner_id' => get_current_user_id(), 'scene_id' => $snapshot['start_scene'], 'snapshot' => $snapshot, 'history' => array() );
			if ( ! ps_pen_paper_valid_adventure_run( array_merge( $after, array( 'revision' => 1 ) ) ) ) { return ps_pen_paper_adventure_error(); }
			$run_id = wp_insert_post( array( 'post_type' => 'pspp_adventure_run', 'post_status' => 'draft', 'post_parent' => $campaign->ID, 'post_title' => 'PS Pen & Paper Adventure Run' ), true );
			if ( is_wp_error( $run_id ) || ! $run_id ) { return ps_pen_paper_adventure_error(); }
			$result = ps_pen_paper_write_adventure_event( $campaign, $run_id, $before, $after, 'adventure_run', 'owner', (int) $character['post']->post_author, true );
			return is_wp_error( $result ) ? $result : (int) $run_id;
		} );
	} );
	if ( is_wp_error( $result ) ) { return $result; }
	if ( is_int( $result ) && $result > 0 ) { return $result; }
	return $run_id;
}

function ps_pen_paper_advance_adventure_run( $campaign, $run_id, $edge_id, $revision ) {
	if ( ! $campaign || 'pspp_campaign' !== $campaign->post_type || ! ps_pen_paper_can_view_campaign( $campaign ) ) { return ps_pen_paper_adventure_error(); }
	$post = ps_pen_paper_record_post( $run_id ); $initial = get_post_meta( $run_id, '_pspp_adventure_run', true );
	if ( ! $post || 'pspp_adventure_run' !== $post->post_type || 'private' !== $post->post_status ||
		(int) $post->post_parent !== (int) $campaign->ID || ! ps_pen_paper_valid_adventure_run( $initial ) || ! $initial['revision'] ||
		$initial['owner_id'] !== get_current_user_id() ) { return ps_pen_paper_adventure_error(); }
	$locks = array( $initial['adventure_id'], $initial['character_id'], (int) $run_id, (int) $campaign->ID );
	foreach ( $initial['snapshot']['edges'] as $edge ) {
		if ( $edge['id'] === $edge_id && $edge['from_scene'] === $initial['scene_id'] ) {
			$action_locks = ps_pen_paper_adventure_action_locks( $campaign, $initial['character_id'], $edge );
			if ( is_wp_error( $action_locks ) ) { return $action_locks; }
			$locks = array_merge( $locks, $action_locks );
		}
	}
	$locks = array_values( array_unique( $locks ) ); sort( $locks, SORT_NUMERIC );
	return ps_pen_paper_adventure_action_transaction( $campaign, $locks, (int) $run_id, function () use ( $campaign, $run_id, $edge_id, $revision, $initial, $locks ) {
			global $wpdb;
			foreach ( $locks as $id ) {
				if ( $id !== (int) $run_id && (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $id ) ) !== $id ) { return ps_pen_paper_adventure_error(); }
				clean_post_cache( $id );
			}
			clean_post_cache( $run_id ); $before = get_post_meta( $run_id, '_pspp_adventure_run', true );
			if ( ! ps_pen_paper_valid_adventure_run( $before ) ) { return ps_pen_paper_adventure_error(); }
			$character = ps_pen_paper_adventure_character( $campaign, $before['character_id'], true );
			if ( is_wp_error( $character ) ||
				$before['owner_id'] !== get_current_user_id() || 'active' !== $before['status'] ||
				! is_string( $revision ) || (string) $before['revision'] !== $revision || ! is_string( $edge_id ) || ! wp_is_uuid( $edge_id ) ||
				! ps_pen_paper_can_view_campaign( $campaign ) ) { return ps_pen_paper_adventure_error(); }
			$edge = null;
			foreach ( $before['snapshot']['edges'] as $candidate ) { if ( $candidate['id'] === $edge_id && $candidate['from_scene'] === $before['scene_id'] ) { $edge = $candidate; break; } }
			if ( ! $edge ) { return ps_pen_paper_adventure_error(); }
			$review = null;
			if ( ! empty( $edge['gm_review'] ) ) {
				$review = $before['approval'] ?? null;
				if ( ! $review || $review['edge_id'] !== $edge_id || 'approved' !== $review['status'] ||
					! ps_pen_paper_adventure_reviewer_authority( $campaign, $review['reviewer_id'] ) ) {
					return new WP_Error( 'adventure_review_required', __( 'Diese Entscheidung benötigt eine aktuelle Freigabe der Spielleitung. Es wurden weder Kosten noch Fortschritt gespeichert.', 'ps-pen-paper' ) );
				}
			}
			$entry = ps_pen_paper_execute_adventure_edge( $campaign, $character, $before['snapshot'], $edge, $before['history'], $locks, $run_id, $review );
			if ( is_wp_error( $entry ) ) { return $entry; }
			$target = $entry['target_scene'];
			$after = $before; $after['revision']++; $after['scene_id'] = $target;
			$next_edges = array_filter( $before['snapshot']['edges'], function ( $item ) use ( $target ) { return $target && $item['from_scene'] === $target; } );
			if ( ! $target || ! $next_edges ) { $after['status'] = 'completed'; }
			$after['history'][] = $entry;
			if ( array_key_exists( 'approval', $after ) ) { $after['approval'] = null; }
			if ( ! ps_pen_paper_valid_adventure_run( $after ) ) { return ps_pen_paper_adventure_error(); }
			return ps_pen_paper_write_adventure_event( $campaign, $run_id, $before, $after, 'adventure_run', 'owner', $before['owner_id'] );
	} );
}
