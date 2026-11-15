<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_scenario_error() {
	return new WP_Error( 'scenario_unavailable', __( 'Die Szenarioeinstellungen oder der bisherige Fortschritt sind ungültig. Bitte lade neu und informiere die Spielleitung. Es wurde nichts gespeichert.', 'ps-pen-paper' ) );
}

function ps_pen_paper_scenario_default() {
	return array( 'chapter_id' => 0, 'chapter_title' => '', 'order' => 0, 'mode' => 'both',
		'repeat' => false, 'rewards' => 'once', 'minimum' => null, 'prerequisites' => array() );
}

function ps_pen_paper_valid_scenario( $scenario ) {
	if ( ! is_array( $scenario ) || array_keys( ps_pen_paper_scenario_default() ) !== array_keys( $scenario ) ||
		! is_int( $scenario['chapter_id'] ) || $scenario['chapter_id'] < 0 ||
		! is_string( $scenario['chapter_title'] ) || strlen( $scenario['chapter_title'] ) > 800 ||
		( 0 === $scenario['chapter_id'] && '' !== $scenario['chapter_title'] ) ||
		! is_int( $scenario['order'] ) || $scenario['order'] < 0 || $scenario['order'] > 9999 ||
		! in_array( $scenario['mode'], array( 'solo', 'group', 'both' ), true ) || ! is_bool( $scenario['repeat'] ) ||
		! in_array( $scenario['rewards'], array( 'once', 'always', 'alternate' ), true ) ||
		! is_array( $scenario['prerequisites'] ) || array_values( $scenario['prerequisites'] ) !== $scenario['prerequisites'] ||
		count( $scenario['prerequisites'] ) > 20 ) { return false; }
	$minimum = $scenario['minimum'];
	if ( null !== $minimum && ( ! is_array( $minimum ) || array( 'field', 'value' ) !== array_keys( $minimum ) ||
		! is_string( $minimum['field'] ) || ! wp_is_uuid( $minimum['field'] ) || ! is_int( $minimum['value'] ) ||
		abs( $minimum['value'] ) > 1000000 ) ) { return false; }
	$seen = array();
	foreach ( $scenario['prerequisites'] as $row ) {
		if ( ! is_array( $row ) || array( 'adventure_id', 'result', 'title' ) !== array_keys( $row ) ||
			! is_int( $row['adventure_id'] ) || $row['adventure_id'] < 1 || isset( $seen[ $row['adventure_id'] ] ) ||
			! in_array( $row['result'], array( 'completed', 'success' ), true ) ||
			! is_string( $row['title'] ) || '' === trim( $row['title'] ) || strlen( $row['title'] ) > 800 ) { return false; }
		$seen[ $row['adventure_id'] ] = true;
	}
	return true;
}

function ps_pen_paper_scenario_validate_scope( $campaign, $id, $scenario, $rules, $published = false ) {
	if ( ! ps_pen_paper_valid_scenario( $scenario ) ) { return ps_pen_paper_scenario_error(); }
	if ( $scenario['chapter_id'] ) {
		$chapter = ps_pen_paper_get_folio( $campaign, $scenario['chapter_id'] );
		if ( is_wp_error( $chapter ) || 'chapter' !== $chapter['draft']['type'] ) { return ps_pen_paper_scenario_error(); }
	}
	if ( $scenario['minimum'] ) {
		$found = false;
		foreach ( $rules['fields'] as $field ) {
			if ( $field['id'] === $scenario['minimum']['field'] && in_array( $field['type'], array( 'number', 'attribute' ), true ) ) { $found = true; }
		}
		if ( ! $found ) { return ps_pen_paper_scenario_error(); }
	}
	$records = ps_pen_paper_adventure_records( $campaign );
	if ( is_wp_error( $records ) ) { return $records; }
	$visiting = array(); $visited = array();
	$visit = function ( $node ) use ( &$visit, &$visiting, &$visited, $records, $id, $scenario, $published ) {
		if ( isset( $visiting[ $node ] ) || ! isset( $records[ $node ] ) ) { return false; }
		if ( isset( $visited[ $node ] ) ) { return true; }
		$visiting[ $node ] = true;
		$settings = $node === $id ? $scenario : ( $records[ $node ][ $published ? 'published' : 'draft' ]['scenario'] ?? ps_pen_paper_scenario_default() );
		foreach ( $settings['prerequisites'] as $row ) { if ( ! $visit( $row['adventure_id'] ) ) { return false; } }
		unset( $visiting[ $node ] ); $visited[ $node ] = true;
		return true;
	};
	return $visit( $id ) ? true : ps_pen_paper_scenario_error();
}

function ps_pen_paper_save_adventure_scenario( $campaign, $id, $input, $revision ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) || ! is_array( $input ) ) { return ps_pen_paper_scenario_error(); }
	foreach ( array( 'chapter_id', 'order', 'mode', 'repeat', 'rewards', 'minimum_field', 'minimum_value' ) as $key ) {
		if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) { return ps_pen_paper_scenario_error(); }
	}
	foreach ( array( 'chapter_id', 'order' ) as $key ) {
		if ( ! preg_match( '/^(0|[1-9]\d{0,18})$/D', $input[ $key ] ) || (string) (int) $input[ $key ] !== $input[ $key ] ) { return ps_pen_paper_scenario_error(); }
	}
	if ( ! in_array( $input['repeat'], array( '0', '1' ), true ) || ! preg_match( '/^-?\d{1,7}$/D', $input['minimum_value'] ) ||
		(string) (int) $input['minimum_value'] !== $input['minimum_value'] || ! isset( $input['prerequisites'] ) ||
		! is_array( $input['prerequisites'] ) || count( $input['prerequisites'] ) > 20 ) { return ps_pen_paper_scenario_error(); }
	$scenario = ps_pen_paper_scenario_default();
	$scenario['chapter_id'] = (int) $input['chapter_id']; $scenario['order'] = (int) $input['order'];
	$scenario['mode'] = $input['mode']; $scenario['repeat'] = '1' === $input['repeat']; $scenario['rewards'] = $input['rewards'];
	if ( '' !== $input['minimum_field'] ) { $scenario['minimum'] = array( 'field' => $input['minimum_field'], 'value' => (int) $input['minimum_value'] ); }
	elseif ( '0' !== $input['minimum_value'] ) { return ps_pen_paper_scenario_error(); }
	if ( $scenario['chapter_id'] ) {
		$chapter = ps_pen_paper_get_folio( $campaign, $scenario['chapter_id'] );
		if ( is_wp_error( $chapter ) ) { return $chapter; }
		$scenario['chapter_title'] = $chapter['draft']['title'];
	}
	foreach ( $input['prerequisites'] as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['adventure_id'], $row['result'] ) || ! is_string( $row['adventure_id'] ) ||
			! is_string( $row['result'] ) || ! preg_match( '/^(0|[1-9]\d{0,18})$/D', $row['adventure_id'] ) ||
			(string) (int) $row['adventure_id'] !== $row['adventure_id'] ||
			! in_array( $row['result'], array( 'completed', 'success' ), true ) ) { return ps_pen_paper_scenario_error(); }
		if ( '0' === $row['adventure_id'] ) { continue; }
		$previous = ps_pen_paper_get_adventure( $campaign, (int) $row['adventure_id'] );
		if ( is_wp_error( $previous ) ) { return $previous; }
		$scenario['prerequisites'][] = array( 'adventure_id' => (int) $row['adventure_id'], 'result' => $row['result'], 'title' => $previous['draft']['title'] );
	}
	return ps_pen_paper_scope_write( $campaign, function () use ( $campaign, $id, $scenario, $revision ) {
		return ps_pen_paper_adventure_locks( array( (int) $campaign->ID, $id ), function () use ( $campaign, $id, $scenario, $revision ) {
			return ps_pen_paper_atomic_event( $id, function () use ( $campaign, $id, $scenario, $revision ) {
				$fresh = ps_pen_paper_lock_adventure_campaign( $campaign );
				if ( is_wp_error( $fresh ) ) { return $fresh; }
				$before = ps_pen_paper_get_adventure( $fresh, $id );
				if ( is_wp_error( $before ) || ! is_string( $revision ) || (string) $before['revision'] !== $revision ) { return ps_pen_paper_scenario_error(); }
				$valid = ps_pen_paper_scenario_validate_scope( $fresh, $id, $scenario, ps_pen_paper_get_rules( $fresh->ID ) );
				if ( is_wp_error( $valid ) ) { return $valid; }
				$after = $before; $after['status'] = 'draft'; $after['draft']['scenario'] = $scenario;
				return ps_pen_paper_write_adventure_event( $fresh, $id, $before, $after, 'adventure_saved' );
			} );
		} );
	} );
}

function ps_pen_paper_scenario_outcome( $snapshot, $history ) {
	if ( ! $history ) { return 'neutral'; }
	$last = end( $history );
	foreach ( $snapshot['edges'] as $edge ) {
		if ( $edge['id'] === $last['edge_id'] ) {
			return $edge['outcomes'][ null === $last['roll'] || $last['roll']['success'] ? 'success' : 'failure' ] ?? 'neutral';
		}
	}
	return 'neutral';
}

function ps_pen_paper_scenario_progress( $campaign, $owner_id ) {
	$progress = array();
	$scope_ids = array( (int) $campaign->ID ); $origins = array();
	$round = ps_pen_paper_play_round( $campaign->ID );
	if ( is_wp_error( $round ) ) { return $round; }
	if ( $round ) {
		$source = ps_pen_paper_get_campaign( $round['source_id'] );
		if ( ! $source ) { return ps_pen_paper_scenario_error(); }
		$rounds = ps_pen_paper_play_rounds( $source );
		if ( is_wp_error( $rounds ) ) { return $rounds; }
		$scope_ids = array_merge( array( $round['source_id'] ), array_map( static function ( $row ) { return (int) $row['post']->ID; }, $rounds ) );
		foreach ( get_posts( array( 'post_type' => 'pspp_adventure', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1 ) ) as $adventure ) {
			$origin = get_post_meta( $adventure->ID, '_pspp_play_origin', true );
			if ( ! is_array( $origin ) || ( $origin['campaign_id'] ?? 0 ) !== $round['source_id'] || ! is_int( $origin['adventure_id'] ?? null ) ) { return ps_pen_paper_scenario_error(); }
			$origins[ $origin['adventure_id'] ] = (int) $adventure->ID;
		}
	}
	foreach ( array( 'pspp_adventure_run' => '_pspp_adventure_run', 'pspp_adventure_group' => '_pspp_adventure_group' ) as $type => $key ) {
		foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'private', 'post_parent__in' => $scope_ids,
			'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
			$state = get_post_meta( $post->ID, $key, true );
			$valid = 'pspp_adventure_run' === $type ? ps_pen_paper_valid_adventure_run( $state ) : ps_pen_paper_valid_adventure_group( $state );
			if ( ! $valid || ! $state['revision'] ) { return ps_pen_paper_scenario_error(); }
			$joined = 'pspp_adventure_run' === $type ? $state['owner_id'] === $owner_id :
				in_array( $owner_id, array_column( $state['participants'], 'owner_id' ), true );
			if ( ! $joined ) { continue; }
			$id = $state['adventure_id'];
			$scope_round = ps_pen_paper_play_round( (int) $post->post_parent );
			if ( is_wp_error( $scope_round ) ) { return $scope_round; }
			if ( $round ) {
				$origin = $scope_round ? get_post_meta( $id, '_pspp_play_origin', true ) : array( 'adventure_id' => $id );
				if ( ! is_array( $origin ) || ! is_int( $origin['adventure_id'] ?? null ) ) { return ps_pen_paper_scenario_error(); }
				if ( ! isset( $origins[ $origin['adventure_id'] ] ) ) { continue; }
				$id = $origins[ $origin['adventure_id'] ];
			}
			if ( ! isset( $progress[ $id ] ) ) { $progress[ $id ] = array( 'attempts' => 0, 'completed' => false, 'success' => false, 'active' => array() ); }
			$progress[ $id ]['attempts']++;
			if ( 'completed' !== $state['status'] ) {
				$active = 'pspp_adventure_run' === $type;
				foreach ( $state['participants'] ?? array() as $participant ) { if ( $participant['owner_id'] === $owner_id && $participant['active'] ) { $active = true; } }
				if ( $active && ( ! $scope_round || 'completed' !== $scope_round['status'] ) ) { $progress[ $id ]['active'][] = $post->ID; }
				continue;
			}
			$history = 'pspp_adventure_run' === $type ? $state['history'] : array_column( $state['history'], 'decision' );
			if ( 'pspp_adventure_group' === $type ) {
				$last = end( $state['history'] );
				if ( ! $last || ! in_array( $owner_id, $last['electorate'], true ) ) { continue; }
			}
			$progress[ $id ]['completed'] = true;
			if ( 'success' === ps_pen_paper_scenario_outcome( $state['snapshot'], $history ) ) { $progress[ $id ]['success'] = true; }
		}
	}
	return $progress;
}

function ps_pen_paper_scenario_access( $campaign, $id, $snapshot, $character, $mode, $current_group = 0 ) {
	$scenario = $snapshot['scenario'] ?? null;
	if ( null === $scenario ) { return array( 'attempt' => 0, 'met' => true, 'messages' => array() ); }
	if ( ! ps_pen_paper_valid_scenario( $scenario ) ) { return ps_pen_paper_scenario_error(); }
	$progress = ps_pen_paper_scenario_progress( $campaign, (int) $character['post']->post_author );
	if ( is_wp_error( $progress ) ) { return $progress; }
	$own = $progress[ $id ] ?? array( 'attempts' => 0, 'active' => array() );
	$messages = array(); $met = true;
	if ( 'both' !== $scenario['mode'] && $mode !== $scenario['mode'] ) {
		$met = false; $messages[] = __( 'Die Spielleitung hat eine andere Spielweise festgelegt.', 'ps-pen-paper' );
	}
	if ( array_diff( $own['active'], array( $current_group ) ) ) {
		$met = false; $messages[] = __( 'Du hast bereits einen laufenden Durchlauf dieses Szenarios.', 'ps-pen-paper' );
	}
	if ( $own['attempts'] && ! $scenario['repeat'] && ! $current_group ) {
		$met = false; $messages[] = __( 'Die Spielleitung erlaubt keine Wiederholung dieses Szenarios.', 'ps-pen-paper' );
	}
	foreach ( $scenario['prerequisites'] as $row ) {
		$ok = ! empty( $progress[ $row['adventure_id'] ][ $row['result'] ] );
		$met = $met && $ok;
		$messages[] = $row['title'] . ' · ' . ( 'success' === $row['result'] ? __( 'gewonnen', 'ps-pen-paper' ) : __( 'abgeschlossen', 'ps-pen-paper' ) ) .
			' · ' . ( $ok ? __( 'erfüllt', 'ps-pen-paper' ) : __( 'noch nicht erfüllt', 'ps-pen-paper' ) );
	}
	if ( $scenario['minimum'] ) {
		$requirement = ps_pen_paper_adventure_requirement_value( array( 'requirement' => array(
			'attribute' => $scenario['minimum']['field'], 'minimum' => $scenario['minimum']['value'] ) ),
			ps_pen_paper_get_rules( $campaign->ID ), $character['state'] );
		if ( is_wp_error( $requirement ) ) { return $requirement; }
		$ok = $requirement['value'] >= $requirement['minimum']; $met = $met && $ok;
		$messages[] = ps_pen_paper_adventure_requirement_label( $requirement ) . ' ' .
			( $ok ? __( 'erfüllt', 'ps-pen-paper' ) : __( 'noch nicht erfüllt', 'ps-pen-paper' ) );
	}
	return array( 'attempt' => $own['attempts'] + 1, 'met' => $met, 'messages' => $messages );
}

function ps_pen_paper_scenario_access_error( $access ) {
	return new WP_Error( 'scenario_requirements', implode( ' ', $access['messages'] ) );
}

function ps_pen_paper_scenario_reward( $snapshot, $edge, $attempt ) {
	if ( ! isset( $snapshot['scenario'] ) || $attempt <= 1 ) { return $edge['reward'] ?? null; }
	if ( 'once' === $snapshot['scenario']['rewards'] ) { return null; }
	return 'alternate' === $snapshot['scenario']['rewards'] ? ( $edge['repeat_reward'] ?? null ) : ( $edge['reward'] ?? null );
}

function ps_pen_paper_sort_scenarios( &$records ) {
	uasort( $records, static function ( $left, $right ) {
		$a = $left['published']['scenario'] ?? ps_pen_paper_scenario_default();
		$b = $right['published']['scenario'] ?? ps_pen_paper_scenario_default();
		return array( $a['order'], $a['chapter_id'] ) <=> array( $b['order'], $b['chapter_id'] );
	} );
}
