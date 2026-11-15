<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_adventure_action_locks( $campaign, $character_id, $edge ) {
	$locks = array( (int) $campaign->ID, (int) $character_id );
	if ( isset( $edge['reward'] ) && ps_pen_paper_adventure_reward_currency( $edge['reward'] ) ) {
		$wallet = ps_pen_paper_wallet_context( $campaign, $character_id );
		if ( is_wp_error( $wallet ) ) { return $wallet; }
		$locks[] = (int) $wallet['character']->ID; $locks[] = (int) $wallet['currency_scope']->ID;
	}
	return $locks;
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
	$conditions = ps_pen_paper_evaluate_adventure_conditions( $edge, $rules, $character['state'], $history, $snapshot['edges'] );
	if ( is_wp_error( $conditions ) ) { return $conditions; }
	if ( $conditions && ! $conditions['met'] ) {
		return new WP_Error( 'adventure_conditions_unmet', __( 'Die Wertebedingungen für diese Entscheidung sind nicht erfüllt. Es wurden weder Kosten, Belohnung noch Fortschritt gespeichert.', 'ps-pen-paper' ) );
	}
	$cost = isset( $edge['resource_cost'] ) ? ps_pen_paper_character_resource_cost( $campaign, $character['post'], $edge['resource_cost']['resource'], $edge['resource_cost']['amount'] ) : null;
	if ( is_wp_error( $cost ) ) { return $cost; }
	if ( $cost && $cost['after'] < $cost['minimum'] ) {
		return new WP_Error( 'adventure_resource_insufficient', __( 'Für diesen Versuch reicht die freigegebene Ressource nicht aus. Es wurden weder Kosten noch Fortschritt gespeichert.', 'ps-pen-paper' ) );
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
	return $entry;
}
