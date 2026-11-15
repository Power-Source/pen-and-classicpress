<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_adventure_action_locks( $campaign, $character_id, $edge ) {
	$locks = array( (int) $campaign->ID, (int) $character_id );
	if ( isset( $edge['resource_cost'] ) ) {
		$character = ps_pen_paper_get_character( $campaign, $character_id );
		if ( ! $character ) { return ps_pen_paper_adventure_error(); }
		$cost = ps_pen_paper_character_resource_cost( $campaign, $character, $edge['resource_cost']['resource'], $edge['resource_cost']['amount'] );
		if ( is_wp_error( $cost ) ) { return $cost; }
		$resource_locks = ps_pen_paper_character_resource_lock_ids( $campaign, $character, $cost );
		$locks = array_merge( $locks, $resource_locks['character_ids'], $resource_locks['scope_ids'] );
	}
	if ( isset( $edge['reward'] ) && ps_pen_paper_adventure_reward_currency( $edge['reward'] ) ) {
		$wallet = ps_pen_paper_wallet_context( $campaign, $character_id );
		if ( is_wp_error( $wallet ) ) { return $wallet; }
		$locks[] = (int) $wallet['character']->ID; $locks[] = (int) $wallet['currency_scope']->ID;
	}
	$locks = array_values( array_unique( $locks ) );
	sort( $locks, SORT_NUMERIC );
	return $locks;
}

// Hold both scopes through commit, not just through the resource document write.
function ps_pen_paper_adventure_action_transaction( $campaign, $locks, $object_id, $write ) {
	if ( ! $campaign || ! is_array( $locks ) || ! is_int( $object_id ) || $object_id < 1 || ! is_callable( $write ) ) {
		return ps_pen_paper_adventure_error();
	}
	$locks = array_values( array_unique( $locks ) );
	sort( $locks, SORT_NUMERIC );
	$scope_ids = array( (int) $campaign->ID );
	foreach ( $locks as $id ) {
		if ( ! is_int( $id ) || $id < 1 ) { return ps_pen_paper_adventure_error(); }
		$post = ps_pen_paper_record_post( $id );
		if ( ! $post ) { return ps_pen_paper_adventure_error(); }
		if ( in_array( $post->post_type, array( 'pspp_campaign', 'pspp_universe' ), true ) ) { $scope_ids[] = (int) $post->ID; }
		elseif ( 0 === strpos( $post->post_type, 'pspp_' ) ) { $scope_ids[] = (int) $post->post_parent; }
	}
	$scope_ids = array_values( array_unique( $scope_ids ) );
	sort( $scope_ids, SORT_NUMERIC );
	return ps_pen_paper_adventure_locks( $locks, function () use ( $scope_ids, $object_id, $write ) {
		$lock_scope = function ( $index ) use ( &$lock_scope, $scope_ids, $object_id, $write ) {
			if ( ! isset( $scope_ids[ $index ] ) ) { return ps_pen_paper_atomic_event( $object_id, $write ); }
			return ps_pen_paper_scope_lock( $scope_ids[ $index ], function () use ( &$lock_scope, $index, $scope_ids ) {
				if ( ! ps_pen_paper_scope_observe( $scope_ids[ $index ] ) ) {
					return new WP_Error( 'scope_changed', __( 'Die Runde oder das Universum wurde inzwischen wiederhergestellt. Bitte lade den aktuellen Stand neu.', 'ps-pen-paper' ) );
				}
				return $lock_scope( $index + 1 );
			} );
		};
		return $lock_scope( 0 );
	} );
}

function ps_pen_paper_execute_adventure_edge( $campaign, $character, $snapshot, $edge, $history, $locks, $event_id, $review = null ) {
	if ( (int) $character['post']->post_author !== get_current_user_id() || ! ps_pen_paper_can_access_site() ||
		! ps_pen_paper_can_view_campaign( $campaign ) ) { return ps_pen_paper_adventure_error(); }
	if ( ! empty( $edge['gm_review'] ) && ( ! ps_pen_paper_valid_adventure_review( $review, get_current_user_id() ) ||
		$review['edge_id'] !== $edge['id'] || 'approved' !== $review['status'] ||
		! ps_pen_paper_adventure_reviewer_authority( $campaign, $review['reviewer_id'] ) ) ) {
		return new WP_Error( 'adventure_review_required', __( 'Diese Entscheidung benötigt eine aktuelle Freigabe der Spielleitung. Es wurden weder Kosten noch Fortschritt gespeichert.', 'ps-pen-paper' ) );
	}
	$rules = ps_pen_paper_get_rules( $campaign->ID );
	$requirement = ps_pen_paper_adventure_requirement_value( $edge, $rules, $character['state'] );
	if ( is_wp_error( $requirement ) ) { return $requirement; }
	if ( $requirement && $requirement['value'] < $requirement['minimum'] ) {
		return new WP_Error( 'adventure_requirement_unmet', __( 'Die Voraussetzung für diese Entscheidung ist nicht erfüllt. Lade den Charakterbogen neu. Es wurde kein Fortschritt gespeichert.', 'ps-pen-paper' ) );
	}
	$conditions = ps_pen_paper_evaluate_adventure_conditions( $edge, $rules, $character['state'], $history, $snapshot['edges'], $snapshot['states'] ?? array() );
	if ( is_wp_error( $conditions ) ) { return $conditions; }
	if ( $conditions && ! $conditions['met'] ) {
		return new WP_Error( 'adventure_conditions_unmet', __( 'Die Wertebedingungen für diese Entscheidung sind nicht erfüllt. Es wurden weder Kosten, Belohnung noch Fortschritt gespeichert.', 'ps-pen-paper' ) );
	}
	$items = isset( $edge['items'] ) ? ps_pen_paper_adventure_items_result( $edge['items'], (int) $character['post']->ID ) : null;
	if ( is_wp_error( $items ) ) { return $items; }
	if ( $items && ! $items['met'] ) {
		return new WP_Error( 'adventure_items_unmet', __( 'Die benötigten Gegenstände fehlen im freigegebenen Inventar dieser Figur. Es wurden weder Kosten noch Fortschritt gespeichert.', 'ps-pen-paper' ) );
	}
	$cost = isset( $edge['resource_cost'] ) ? ps_pen_paper_character_resource_cost( $campaign, $character['post'], $edge['resource_cost']['resource'], $edge['resource_cost']['amount'] ) : null;
	if ( is_wp_error( $cost ) ) { return $cost; }
	if ( $cost && $cost['after'] < $cost['minimum'] ) {
		return new WP_Error( 'adventure_resource_insufficient', __( 'Für diesen Versuch reicht die freigegebene Ressource nicht aus. Es wurden weder Kosten noch Fortschritt gespeichert.', 'ps-pen-paper' ) );
	}
	if ( $cost ) {
		$resource_locks = ps_pen_paper_character_resource_lock_ids( $campaign, $character['post'], $cost );
		if ( array_diff( array_merge( $resource_locks['character_ids'], $resource_locks['scope_ids'] ), $locks ) ) {
			return new WP_Error( 'resource_cost_shared_conflict', __( 'Die Verbindung zum Basisbogen wurde inzwischen geändert. Bitte lade den Charakterbogen neu; es wurde nichts übernommen.', 'ps-pen-paper' ) );
		}
	}
	$reward_context = isset( $edge['reward'] ) ? ps_pen_paper_adventure_reward_context( $campaign, $character['post'], $edge['reward'] ) : null;
	if ( is_wp_error( $reward_context ) ) { return $reward_context; }
	if ( $reward_context && $reward_context['wallet'] &&
		( ! in_array( (int) $reward_context['wallet']['character']->ID, $locks, true ) ||
			! in_array( (int) $reward_context['wallet']['currency_scope']->ID, $locks, true ) ) ) { return ps_pen_paper_adventure_reward_error(); }
	$roll = null; $target = $edge['success_scene'];
	if ( '' !== $edge['expression'] ) {
		$parsed = ps_pen_paper_parse_dice( $edge['expression'], array( '4', '6', '8', '10', '12', '20', '100' ) );
		if ( is_wp_error( $parsed ) ) { return $parsed; }
		$attribute = null;
		if ( $edge['attribute'] ) {
			foreach ( $rules['fields'] as $field ) {
				if ( $field['id'] === $edge['attribute'] && in_array( $field['type'], array( 'attribute', 'number' ), true ) ) {
					$attribute = array( 'id' => $field['id'], 'label' => $field['label'], 'value' => $character['state']['active']['values'][ $field['id'] ] ); break;
				}
			}
			if ( ! $attribute || ! is_int( $attribute['value'] ) ) { return ps_pen_paper_adventure_error(); }
		}
		$result = ps_pen_paper_evaluate_dice( $parsed );
		$total = $result['total'] + ( $attribute ? $attribute['value'] : 0 );
		$success = $total >= $edge['threshold']; $target = $success ? $edge['success_scene'] : $edge['failure_scene'];
		$roll = array( 'expression' => $parsed['expression'], 'groups' => $result['groups'], 'modifier' => $result['modifier'],
			'attribute' => $attribute, 'total' => $total, 'threshold' => $edge['threshold'], 'success' => $success,
			'rules_revision' => $snapshot['rules_revision'] );
	}
	$entry = array( 'edge_id' => $edge['id'], 'source_scene' => $edge['from_scene'], 'choice' => $edge['choice'],
		'roll' => $roll, 'target_scene' => $target, 'date' => current_time( 'mysql', true ) );
	if ( $requirement ) { $entry['requirement'] = $requirement; }
	$charge = $cost && ( 'attempt' === $edge['resource_cost']['timing'] || null === $roll || $roll['success'] );
	if ( $cost ) { $entry['resource_cost'] = $charge ? $cost : null; }
	if ( $charge ) {
		$spent = ps_pen_paper_write_character_resource_cost( $campaign, $character['post'], $cost );
		if ( is_wp_error( $spent ) ) { return $spent; }
	}
	if ( $reward_context ) {
		$grant = 'decision' === $edge['reward']['timing'] || null === $roll || $roll['success'];
		$reward = $grant ? ps_pen_paper_write_adventure_reward( $campaign, $character['post'], $edge['reward'], $reward_context, (int) $event_id ) : null;
		if ( is_wp_error( $reward ) ) { return $reward; }
		$entry['reward'] = $reward;
	}
	if ( $conditions ) { $entry['conditions'] = $conditions; }
	if ( ! empty( $edge['gm_review'] ) ) { $entry['review'] = $review; }
	if ( $items ) { $entry['items'] = $items; }
	if ( isset( $edge['effects'] ) ) {
		$entry['effects'] = ps_pen_paper_adventure_effects_result( $edge, $snapshot, $history, $roll );
		if ( is_wp_error( $entry['effects'] ) ) { return $entry['effects']; }
	}
	return $entry;
}
