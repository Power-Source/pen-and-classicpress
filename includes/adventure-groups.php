<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_adventure_group_error() {
	return new WP_Error( 'adventure_group_conflict', __( 'Der Gruppenstand oder die Teilnahme wurde geändert oder ist ungültig. Bitte lade neu und prüfe die Teilnahme. Es wurde kein Teilfortschritt gespeichert.', 'ps-pen-paper' ) );
}

function ps_pen_paper_adventure_group_default() {
	return array( 'revision' => 0, 'status' => 'new', 'history' => array() );
}

function ps_pen_paper_adventure_group_initial( $id, $snapshot, $title, $rule ) {
	return array( 'revision' => 1, 'title' => sanitize_text_field( $title ), 'status' => 'active',
		'adventure_id' => $id, 'snapshot' => $snapshot, 'scene_id' => $snapshot['start_scene'],
		'rule' => $rule, 'participants' => array(), 'proposal' => null, 'history' => array() );
}

function ps_pen_paper_adventure_group_rules() {
	return array( 'any' => __( 'Ein Teilnehmer entscheidet', 'ps-pen-paper' ),
		'majority' => __( 'Mehrheit der aktiven Teilnehmer', 'ps-pen-paper' ),
		'unanimous' => __( 'Alle aktiven Teilnehmer stimmen zu', 'ps-pen-paper' ),
		'gm' => __( 'Die Spielleitung gibt die Entscheidung frei', 'ps-pen-paper' ) );
}

function ps_pen_paper_adventure_group_users( $ids ) {
	return is_array( $ids ) && array_values( $ids ) === $ids && count( $ids ) <= 100 &&
		! array_filter( $ids, static function ( $id ) { return ! is_int( $id ) || $id < 1; } ) && count( array_unique( $ids ) ) === count( $ids );
}

function ps_pen_paper_adventure_group_quorum( $rule, $electorate, $votes, $reviewer_id ) {
	if ( ! is_string( $rule ) || ! isset( ps_pen_paper_adventure_group_rules()[ $rule ] ) || ! ps_pen_paper_adventure_group_users( $electorate ) ||
		! $electorate || ! ps_pen_paper_adventure_group_users( $votes ) || array_diff( $votes, $electorate ) ) { return false; }
	switch ( $rule ) {
		case 'any': return count( $votes ) > 0;
		case 'majority': return count( $votes ) * 2 > count( $electorate );
		case 'unanimous': return count( $votes ) === count( $electorate );
		case 'gm': return is_int( $reviewer_id ) && $reviewer_id > 0;
	}
	return false;
}

function ps_pen_paper_adventure_group_edge( $group, $edge_id ) {
	foreach ( $group['snapshot']['edges'] as $edge ) {
		if ( $edge['id'] === $edge_id && $edge['from_scene'] === $group['scene_id'] ) { return $edge; }
	}
	return null;
}

function ps_pen_paper_adventure_group_electorate( $group ) {
	$users = array();
	foreach ( $group['participants'] as $participant ) { if ( $participant['active'] ) { $users[] = $participant['owner_id']; } }
	return $users;
}

function ps_pen_paper_valid_adventure_group( $group ) {
	if ( $group === ps_pen_paper_adventure_group_default() ) { return true; }
	if ( ! is_array( $group ) || array( 'revision', 'title', 'status', 'adventure_id', 'snapshot', 'scene_id', 'rule', 'participants', 'proposal', 'history' ) !== array_keys( $group ) ||
		! is_int( $group['revision'] ) || $group['revision'] < 1 || ! is_string( $group['title'] ) || '' === trim( $group['title'] ) || strlen( $group['title'] ) > 800 ||
		! in_array( $group['status'], array( 'active', 'paused', 'completed' ), true ) ||
		! is_int( $group['adventure_id'] ) || $group['adventure_id'] < 1 || ! ps_pen_paper_valid_adventure_publication( $group['snapshot'] ) ||
		! is_int( $group['scene_id'] ) || $group['scene_id'] < 0 || ! is_string( $group['rule'] ) || ! isset( ps_pen_paper_adventure_group_rules()[ $group['rule'] ] ) ||
		! is_array( $group['participants'] ) || array_values( $group['participants'] ) !== $group['participants'] || count( $group['participants'] ) > 100 ||
		! is_array( $group['history'] ) || array_values( $group['history'] ) !== $group['history'] || count( $group['history'] ) > 30 ||
		$group['revision'] < count( $group['history'] ) + 1 ) { return false; }
	$characters = array(); $owners = array(); $active = array();
	foreach ( $group['participants'] as $participant ) {
		$keys = array( 'character_id', 'owner_id', 'active' );
		if ( isset( $group['snapshot']['scenario'] ) ) { $keys[] = 'scenario_attempt'; }
		if ( ! is_array( $participant ) || $keys !== array_keys( $participant ) ||
			( isset( $group['snapshot']['scenario'] ) && ( ! is_int( $participant['scenario_attempt'] ) || $participant['scenario_attempt'] < 1 ) ) ||
			! is_int( $participant['character_id'] ) || $participant['character_id'] < 1 || isset( $characters[ $participant['character_id'] ] ) ||
			! is_int( $participant['owner_id'] ) || $participant['owner_id'] < 1 || ! is_bool( $participant['active'] ) ||
			( $participant['active'] && in_array( $participant['owner_id'], $active, true ) ) ) { return false; }
		$characters[ $participant['character_id'] ] = $participant;
		$owners[ $participant['owner_id'] ] = true;
		if ( $participant['active'] ) { $active[] = $participant['owner_id']; }
	}
	$scene_id = $group['snapshot']['start_scene']; $history = array();
	foreach ( $group['history'] as $entry ) {
		if ( ! is_array( $entry ) || array( 'character_id', 'owner_id', 'rule', 'electorate', 'votes', 'reviewer_id', 'decision' ) !== array_keys( $entry ) ||
			! is_int( $entry['character_id'] ) || ! isset( $characters[ $entry['character_id'] ] ) ||
			$characters[ $entry['character_id'] ]['owner_id'] !== $entry['owner_id'] ||
			! is_string( $entry['rule'] ) || ! is_int( $entry['reviewer_id'] ) || $entry['reviewer_id'] < 0 ||
			! ps_pen_paper_adventure_group_quorum( $entry['rule'], $entry['electorate'], $entry['votes'], $entry['reviewer_id'] ) ||
			array_diff( $entry['electorate'], array_keys( $owners ) ) || ! in_array( $entry['owner_id'], $entry['electorate'], true ) ||
			! is_array( $entry['decision'] ) || ( $entry['decision']['source_scene'] ?? null ) !== $scene_id ) { return false; }
		$edge = ps_pen_paper_adventure_group_edge( array_merge( $group, array( 'scene_id' => $scene_id ) ), $entry['decision']['edge_id'] ?? '' );
		if ( ! $edge || ( ( 'gm' === $entry['rule'] || ! empty( $edge['gm_review'] ) ) !== ( $entry['reviewer_id'] > 0 ) ) ||
			( ! empty( $edge['gm_review'] ) && ( $entry['decision']['review']['reviewer_id'] ?? null ) !== $entry['reviewer_id'] ) ) { return false; }
		// Validate this actor's receipt without assigning earlier actors' resources to this character.
		$snapshot = $group['snapshot']; $snapshot['start_scene'] = $scene_id;
		$target = $entry['decision']['target_scene'] ?? -1;
		$next = array_filter( $snapshot['edges'], static function ( $candidate ) use ( $target ) { return $target && $candidate['from_scene'] === $target; } );
		$run = array( 'revision' => 2, 'status' => $target && $next ? 'active' : 'completed', 'adventure_id' => $group['adventure_id'],
			'adventure_publication' => $snapshot['publication'], 'character_id' => $entry['character_id'], 'owner_id' => $entry['owner_id'],
			'scene_id' => $target, 'snapshot' => $snapshot, 'history' => array( $entry['decision'] ) );
		if ( isset( $group['snapshot']['scenario'] ) ) { $run['scenario_attempt'] = $characters[ $entry['character_id'] ]['scenario_attempt']; }
		if ( ! empty( $edge['gm_review'] ) ) { $run['revision'] += 2; $run['reviews'] = 2; $run['approval'] = null; }
		if ( ! ps_pen_paper_valid_adventure_run( $run, $history ) ) { return false; }
		$history[] = $entry['decision']; $scene_id = $target;
	}
	$next = array_filter( $group['snapshot']['edges'], static function ( $edge ) use ( $scene_id ) { return $scene_id && $edge['from_scene'] === $scene_id; } );
	if ( $group['scene_id'] !== $scene_id || ( 'completed' === $group['status'] ) !== ( ! $scene_id || ! $next ) ) { return false; }
	if ( null === $group['proposal'] ) { return true; }
	$proposal = $group['proposal'];
	if ( 'active' !== $group['status'] || ! is_array( $proposal ) ||
		array( 'edge_id', 'character_id', 'owner_id', 'votes', 'reviewer_id' ) !== array_keys( $proposal ) ||
		! is_string( $proposal['edge_id'] ) || ! is_int( $proposal['character_id'] ) || ! isset( $characters[ $proposal['character_id'] ] ) ||
		! $characters[ $proposal['character_id'] ]['active'] || $proposal['owner_id'] !== $characters[ $proposal['character_id'] ]['owner_id'] ||
		! ps_pen_paper_adventure_group_users( $proposal['votes'] ) || array_diff( $proposal['votes'], $active ) ||
		! is_int( $proposal['reviewer_id'] ) || $proposal['reviewer_id'] < 0 ) { return false; }
	$edge = ps_pen_paper_adventure_group_edge( $group, $proposal['edge_id'] );
	return $edge && ( ! $proposal['reviewer_id'] || 'gm' === $group['rule'] || ! empty( $edge['gm_review'] ) );
}

function ps_pen_paper_adventure_group_member( $campaign, $group, $user_id ) {
	foreach ( $group['participants'] as $participant ) {
		if ( $participant['owner_id'] === $user_id && $participant['active'] &&
			user_can( $user_id, 'read' ) && ps_pen_paper_site_user( $user_id ) && ps_pen_paper_campaign_role( $campaign, $user_id ) ) {
			$character = ps_pen_paper_record_post( $participant['character_id'] );
			if ( $character && 'pspp_character' === $character->post_type && 'private' === $character->post_status &&
				(int) $character->post_parent === (int) $campaign->ID && (int) $character->post_author === $user_id ) { return $participant; }
		}
	}
	return null;
}

function ps_pen_paper_adventure_group_records( $campaign, $player_view = false ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) ) { return ps_pen_paper_adventure_group_error(); }
	$records = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_adventure_group', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
		$group = get_post_meta( $post->ID, '_pspp_adventure_group', true );
		if ( ! ps_pen_paper_valid_adventure_group( $group ) || ! $group['revision'] ) { return ps_pen_paper_adventure_group_error(); }
		$joined = false;
		foreach ( $group['participants'] as $participant ) { if ( $participant['owner_id'] === get_current_user_id() ) { $joined = true; } }
		$records[ $post->ID ] = array( 'title' => $group['title'], 'status' => $group['status'], 'revision' => $group['revision'],
			'state' => $joined || ( ! $player_view && ps_pen_paper_can_manage_campaign( $campaign ) ) ? $group : null );
	}
	return $records;
}

function ps_pen_paper_write_adventure_group( $campaign, $id, $revision, $operation, $input = array() ) {
	if ( ! $campaign ) { return ps_pen_paper_adventure_group_error(); }
	$round = ps_pen_paper_play_round( $campaign->ID );
	if ( is_wp_error( $round ) ) { return $round; }
	if ( ! $round ) { return ps_pen_paper_write_adventure_group_locked( $campaign, $id, $revision, $operation, $input ); }
	if ( 'group' !== $round['mode'] || $round['run_id'] !== $id || 'active' !== $round['status'] ||
		! in_array( $operation, array( 'propose', 'vote', 'unvote', 'cancel', 'execute' ), true ) ) { return ps_pen_paper_play_error(); }
	return ps_pen_paper_write_adventure_group_locked( $campaign, $id, $revision, $operation, $input );
}

function ps_pen_paper_write_adventure_group_locked( $campaign, $id, $revision, $operation, $input = array() ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) || ! is_int( $id ) || $id < 0 ||
		! is_string( $revision ) || ! is_array( $input ) || ! in_array( $operation,
			array( 'create', 'join', 'leave', 'propose', 'vote', 'unvote', 'cancel', 'approve', 'reject', 'execute', 'pause', 'resume', 'configure' ), true ) ||
		( ( 'create' === $operation ) !== ( 0 === $id ) ) ) { return ps_pen_paper_adventure_group_error(); }
	return ps_pen_paper_scope_write( $campaign, function () use ( $campaign, $id, $revision, $operation, $input ) {
		$initial = $id ? get_post_meta( $id, '_pspp_adventure_group', true ) : ps_pen_paper_adventure_group_default();
		if ( ! ps_pen_paper_valid_adventure_group( $initial ) || ( $id && ! $initial['revision'] ) ) { return ps_pen_paper_adventure_group_error(); }
		$locks = array( (int) $campaign->ID );
		if ( $id ) { $locks[] = $id; $locks[] = $initial['adventure_id']; }
		else {
			if ( ! isset( $input['adventure_id'] ) || ! is_int( $input['adventure_id'] ) || $input['adventure_id'] < 1 ) { return ps_pen_paper_adventure_group_error(); }
			$locks[] = $input['adventure_id'];
		}
		if ( in_array( $operation, array( 'join', 'leave', 'propose' ), true ) ) {
			if ( ! isset( $input['character_id'] ) || ! is_int( $input['character_id'] ) || $input['character_id'] < 1 ) { return ps_pen_paper_adventure_group_error(); }
			$locks[] = $input['character_id'];
		}
		if ( 'execute' === $operation ) {
			if ( ! isset( $initial['proposal'] ) ) { return ps_pen_paper_adventure_group_error(); }
			$edge = ps_pen_paper_adventure_group_edge( $initial, $initial['proposal']['edge_id'] );
			$attempt = 0;
			foreach ( $initial['participants'] as $participant ) { if ( $participant['character_id'] === $initial['proposal']['character_id'] ) { $attempt = $participant['scenario_attempt'] ?? 0; } }
			$action_locks = ps_pen_paper_adventure_action_locks( $campaign, $initial['proposal']['character_id'], $edge, $initial['snapshot'], $attempt );
			if ( is_wp_error( $action_locks ) ) { return $action_locks; }
			$locks = array_merge( $locks, $action_locks );
		}
		$round = ps_pen_paper_play_round( $campaign->ID );
		if ( is_wp_error( $round ) ) { return $round; }
		if ( $round ) {
			if ( 'group' !== $round['mode'] || $round['run_id'] !== $id || 'active' !== $round['status'] ||
				! in_array( $operation, array( 'propose', 'vote', 'unvote', 'cancel', 'execute' ), true ) ) { return ps_pen_paper_play_error(); }
			$locks[] = $round['source_id'];
			foreach ( get_posts( array( 'post_type' => 'pspp_character', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1 ) ) as $character ) {
				$raw = ps_pen_paper_raw_character_state( $character->ID );
				if ( is_wp_error( $raw ) ) { return $raw; }
				$context = ps_pen_paper_validate_context( $raw['context'] ?? null, ps_pen_paper_get_rules( $campaign->ID ) );
				if ( is_wp_error( $context ) ) { return $context; }
				$locks = array_merge( $locks, array( (int) $character->ID, $context['source_id'], $context['universe_id'] ) );
			}
		}
		if ( $round && 'propose' === $operation && 'any' === $initial['rule'] ) {
			$edge = ps_pen_paper_adventure_group_edge( $initial, $input['edge_id'] ?? '' );
			if ( ! $edge ) { return ps_pen_paper_adventure_group_error(); }
			$attempt = 0;
			foreach ( $initial['participants'] as $participant ) { if ( $participant['character_id'] === $input['character_id'] ) { $attempt = $participant['scenario_attempt'] ?? 0; } }
			$action_locks = ps_pen_paper_adventure_action_locks( $campaign, $input['character_id'], $edge, $initial['snapshot'], $attempt );
			if ( is_wp_error( $action_locks ) ) { return $action_locks; }
			$locks = array_merge( $locks, $action_locks );
		}
		$locks = array_values( array_unique( $locks ) ); sort( $locks, SORT_NUMERIC );
		$created_id = 0;
		$result = ps_pen_paper_adventure_action_transaction( $campaign, $locks, (int) $campaign->ID, function () use ( $campaign, $id, $revision, $operation, $input, $initial, $locks, &$created_id ) {
				global $wpdb;
				foreach ( $locks as $lock_id ) {
					if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID=%d FOR UPDATE", $lock_id ) ) !== $lock_id ) { return ps_pen_paper_adventure_group_error(); }
					clean_post_cache( $lock_id );
				}
				$before = $id ? get_post_meta( $id, '_pspp_adventure_group', true ) : ps_pen_paper_adventure_group_default();
				$post = $id ? ps_pen_paper_record_post( $id ) : null;
				if ( ! ps_pen_paper_valid_adventure_group( $before ) || $before['revision'] !== $initial['revision'] ||
					(string) $before['revision'] !== $revision || ! ps_pen_paper_can_view_campaign( $campaign ) ||
					( $id && ( ! $post || 'pspp_adventure_group' !== $post->post_type || 'private' !== $post->post_status || (int) $post->post_parent !== (int) $campaign->ID ) ) ) { return ps_pen_paper_adventure_group_error(); }
				$gm = ps_pen_paper_can_manage_campaign( $campaign ); $user_id = get_current_user_id();
				if ( 'create' === $operation ) {
					$adventure = ps_pen_paper_get_adventure( $campaign, $input['adventure_id'] );
					if ( ! $gm || is_wp_error( $adventure ) || 'published' !== $adventure['status'] ||
						( isset( $adventure['published']['scenario'] ) && 'solo' === $adventure['published']['scenario']['mode'] ) ||
						! isset( $input['adventure_revision'], $input['title'], $input['rule'] ) ||
						! is_string( $input['adventure_revision'] ) || (string) $adventure['revision'] !== $input['adventure_revision'] ||
						! is_string( $input['title'] ) || ! is_string( $input['rule'] ) ) { return ps_pen_paper_adventure_group_error(); }
					$after = ps_pen_paper_adventure_group_initial( $input['adventure_id'], $adventure['published'], $input['title'], $input['rule'] );
				} else {
					$after = $before; $after['revision']++;
					$member = ps_pen_paper_adventure_group_member( $campaign, $before, $user_id );
					if ( $member && in_array( $operation, array( 'propose', 'vote', 'execute' ), true ) ) {
						$allowed = ps_pen_paper_require_character_game_action( $campaign, $member['character_id'] );
						if ( is_wp_error( $allowed ) ) { return $allowed; }
					}
					if ( in_array( $operation, array( 'pause', 'resume', 'configure' ), true ) ) {
						if ( ! $gm || 'completed' === $before['status'] ||
							( 'pause' === $operation && 'active' !== $before['status'] ) ||
							( 'resume' === $operation && 'paused' !== $before['status'] ) ) { return ps_pen_paper_adventure_group_error(); }
						if ( 'configure' === $operation ) {
							if ( ! isset( $input['rule'] ) || ! is_string( $input['rule'] ) ) { return ps_pen_paper_adventure_group_error(); }
							$after['rule'] = $input['rule'];
						} else { $after['status'] = 'pause' === $operation ? 'paused' : 'active'; }
						$after['proposal'] = null;
					} elseif ( 'join' === $operation ) {
						$character = ps_pen_paper_adventure_character( $campaign, $input['character_id'], true );
						if ( $member || 'completed' === $before['status'] || is_wp_error( $character ) ) { return ps_pen_paper_adventure_group_error(); }
						$enrolled = false; $attempt = 0;
						foreach ( $before['participants'] as $participant ) {
							if ( $participant['owner_id'] === $user_id ) { $enrolled = true; $attempt = $participant['scenario_attempt'] ?? 0; }
						}
						$access = ps_pen_paper_scenario_access( $campaign, $before['adventure_id'], $before['snapshot'], $character, 'group', $enrolled ? $id : 0 );
						if ( is_wp_error( $access ) ) { return $access; }
						if ( ! $access['met'] ) { return ps_pen_paper_scenario_access_error( $access ); }
						$found = false;
						foreach ( $after['participants'] as &$participant ) {
							if ( $participant['character_id'] !== $input['character_id'] ) { continue; }
							if ( $participant['owner_id'] !== $user_id || $participant['active'] ) { return ps_pen_paper_adventure_group_error(); }
							$participant['active'] = true; $found = true;
						}
						unset( $participant );
						if ( ! $found ) {
							$participant = array( 'character_id' => $input['character_id'], 'owner_id' => $user_id, 'active' => true );
							if ( isset( $before['snapshot']['scenario'] ) ) { $participant['scenario_attempt'] = $enrolled ? $attempt : $access['attempt']; }
							$after['participants'][] = $participant;
						}
						$after['proposal'] = null;
					} elseif ( 'leave' === $operation ) {
						$found = false;
						foreach ( $after['participants'] as &$participant ) {
							if ( $participant['character_id'] !== $input['character_id'] || ! $participant['active'] ) { continue; }
							if ( ! $gm && $participant['owner_id'] !== $user_id ) { return ps_pen_paper_adventure_group_error(); }
							$participant['active'] = false; $found = true;
						}
						unset( $participant );
						if ( ! $found ) { return ps_pen_paper_adventure_group_error(); }
						$after['proposal'] = null;
					} else {
						if ( 'active' !== $before['status'] ) { return ps_pen_paper_adventure_group_error(); }
						$proposal = $before['proposal'];
						if ( 'propose' === $operation ) {
							$edge = isset( $input['edge_id'] ) && is_string( $input['edge_id'] ) ? ps_pen_paper_adventure_group_edge( $before, $input['edge_id'] ) : null;
							if ( ! $member || $member['character_id'] !== $input['character_id'] || $proposal || ! $edge ) { return ps_pen_paper_adventure_group_error(); }
							$after['proposal'] = array( 'edge_id' => $edge['id'], 'character_id' => $member['character_id'], 'owner_id' => $user_id,
								'votes' => array( $user_id ), 'reviewer_id' => 0 );
						} elseif ( ! $proposal ) { return ps_pen_paper_adventure_group_error(); }
						elseif ( in_array( $operation, array( 'vote', 'unvote' ), true ) ) {
							if ( ! $member || ( 'vote' === $operation ) === in_array( $user_id, $proposal['votes'], true ) ) { return ps_pen_paper_adventure_group_error(); }
							$after['proposal']['votes'] = 'vote' === $operation ? array_merge( $proposal['votes'], array( $user_id ) ) : array_values( array_diff( $proposal['votes'], array( $user_id ) ) );
						} elseif ( in_array( $operation, array( 'cancel', 'reject' ), true ) ) {
							if ( ! $gm && ( 'reject' === $operation || ! $member || $proposal['owner_id'] !== $user_id ) ) { return ps_pen_paper_adventure_group_error(); }
							$after['proposal'] = null;
						} elseif ( 'approve' === $operation ) {
							$edge = ps_pen_paper_adventure_group_edge( $before, $proposal['edge_id'] );
							if ( ! $gm || $proposal['reviewer_id'] || ( 'gm' !== $before['rule'] && empty( $edge['gm_review'] ) ) ) { return ps_pen_paper_adventure_group_error(); }
							$after['proposal']['reviewer_id'] = $user_id;
						} elseif ( 'execute' === $operation ) {
							$executed = ps_pen_paper_execute_group_proposal( $campaign, $id, $before, $after, $locks );
							if ( is_wp_error( $executed ) ) { return $executed; }
						}
					}
				}
				$round = ps_pen_paper_play_round( $campaign->ID );
				if ( is_wp_error( $round ) ) { return $round; }
				if ( $round && 'active' !== $round['status'] ) { return ps_pen_paper_play_error(); }
				if ( $round && 'propose' === $operation && 'any' === $before['rule'] ) {
					$proposed = $before; $proposed['proposal'] = $after['proposal'];
					$executed = ps_pen_paper_execute_group_proposal( $campaign, $id, $proposed, $after, $locks );
					if ( is_wp_error( $executed ) ) { return $executed; }
				}
				if ( ! ps_pen_paper_valid_adventure_group( $after ) ) { return ps_pen_paper_adventure_group_error(); }
				$new = ! $id;
				if ( $new ) {
					$id = wp_insert_post( array( 'post_type' => 'pspp_adventure_group', 'post_status' => 'draft', 'post_parent' => $campaign->ID,
						'post_title' => 'PS Pen & Paper Adventure Group' ), true );
					if ( is_wp_error( $id ) || ! $id ) { return ps_pen_paper_adventure_group_error(); }
					$created_id = (int) $id;
				}
				$result = ps_pen_paper_write_document_event( $campaign->ID, $id, '_pspp_adventure_group', $before, $after,
					array( 'kind' => 'adventure_group', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => $after['snapshot']['rules_revision'] ), $new );
				if ( is_wp_error( $result ) ) { return $result; }
				if ( $round && count( $after['history'] ) > count( $before['history'] ) ) {
					$progress = ps_pen_paper_write_play_progress( $campaign, $after['status'] );
					if ( is_wp_error( $progress ) ) { return $progress; }
				}
				return $result;
		} );
		return is_wp_error( $result ) ? $result : ( $created_id ? $created_id : true );
	} );
}

function ps_pen_paper_execute_group_proposal( $campaign, $id, $before, &$after, $locks ) {
	$proposal = $before['proposal']; $user_id = get_current_user_id();
	$member = ps_pen_paper_adventure_group_member( $campaign, $before, $user_id );
	$edge = ps_pen_paper_adventure_group_edge( $before, $proposal['edge_id'] );
	$electorate = ps_pen_paper_adventure_group_electorate( $before );
	if ( ! $member || ! $edge || $proposal['owner_id'] !== $user_id ||
		! ps_pen_paper_adventure_group_quorum( $before['rule'], $electorate, $proposal['votes'], $proposal['reviewer_id'] ) ||
		( ( 'gm' === $before['rule'] || ! empty( $edge['gm_review'] ) ) && ! ps_pen_paper_adventure_reviewer_authority( $campaign, $proposal['reviewer_id'] ) ) ) { return ps_pen_paper_adventure_group_error(); }
	foreach ( $electorate as $voter ) { if ( ! ps_pen_paper_adventure_group_member( $campaign, $before, $voter ) ) { return ps_pen_paper_adventure_group_error(); } }
	$character = ps_pen_paper_adventure_character( $campaign, $proposal['character_id'], true );
	if ( is_wp_error( $character ) ) { return $character; }
	$review = ! empty( $edge['gm_review'] ) ? array( 'edge_id' => $edge['id'], 'status' => 'approved', 'requester_id' => $user_id, 'reviewer_id' => $proposal['reviewer_id'] ) : null;
	$decision = ps_pen_paper_execute_adventure_edge( $campaign, $character, $before['snapshot'], $edge, array_column( $before['history'], 'decision' ),
		$locks, $id, $review, $member['scenario_attempt'] ?? 0 );
	if ( is_wp_error( $decision ) ) { return $decision; }
	$after['history'][] = array( 'character_id' => $proposal['character_id'], 'owner_id' => $user_id, 'rule' => $before['rule'],
		'electorate' => $electorate, 'votes' => $proposal['votes'], 'reviewer_id' => $proposal['reviewer_id'], 'decision' => $decision );
	$after['scene_id'] = $decision['target_scene']; $after['proposal'] = null;
	$next = array_filter( $before['snapshot']['edges'], static function ( $candidate ) use ( $after ) { return $after['scene_id'] && $candidate['from_scene'] === $after['scene_id']; } );
	if ( ! $after['scene_id'] || ! $next ) { $after['status'] = 'completed'; }
	return 0;
}
