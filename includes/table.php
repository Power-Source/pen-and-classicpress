<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_table_members( $campaign ) {
	$ids = array_unique( array_map( 'intval', array_merge( array( $campaign->post_author ), get_post_meta( $campaign->ID, '_pspp_gm', false ), get_post_meta( $campaign->ID, '_pspp_player', false ) ) ) );
	$round = ps_pen_paper_play_round( $campaign->ID );
	if ( $round && ! is_wp_error( $round ) ) {
		$ids = array_column( array_filter( $round['participants'], static function ( $participant ) { return $participant['active']; } ), 'user_id' );
		if ( 'guided' === $round['mode'] ) { $ids[] = $round['host_id']; }
	}
	$members = array();
	foreach ( $ids as $id ) {
		$user = ps_pen_paper_site_user( $id );
		if ( $user && user_can( $user, 'read' ) ) {
			$members[ $id ] = array( 'id' => $id, 'name' => $user->display_name, 'role' => ps_pen_paper_campaign_role( $campaign, $id ) );
		}
	}
	return $members;
}

function ps_pen_paper_table_key( $campaign_id, $user_id, $kind, $epoch = null ) {
	if ( null === $epoch ) { $epoch = ps_pen_paper_scope_epoch( $campaign_id ); }
	if ( is_wp_error( $epoch ) ) { return $epoch; }
	return 'pspp_' . $kind . '_' . get_current_blog_id() . '_' . $campaign_id . '_' . $user_id . ( $epoch ? '_' . md5( $epoch ) : '' );
}

function ps_pen_paper_table_identity( $campaign, $user_id, $with_image = false ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) ) { return new WP_Error( 'forbidden', __( 'Diese Runde ist für dich nicht verfügbar.', 'ps-pen-paper' ) ); }
	$round = ps_pen_paper_play_round( $campaign->ID );
	if ( is_wp_error( $round ) ) { return $round; }
	$participant = $round ? ps_pen_paper_play_participant( $round, $user_id ) : null;
	$characters = $round ? ( $participant ? array( ps_pen_paper_record_post( $participant['character_id'] ) ) : array() ) :
		get_posts( array( 'post_type' => 'pspp_character', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'author' => $user_id, 'posts_per_page' => 2 ) );
	if ( count( $characters ) !== 1 ) { return array(); }
	$character = $characters[0]; $scope = $campaign;
	if ( ! $character || 'pspp_character' !== $character->post_type || 'private' !== $character->post_status ||
		(int) $character->post_parent !== (int) $campaign->ID || (int) $character->post_author !== $user_id ) { return array(); }
	$raw = ps_pen_paper_raw_character_state( $character->ID );
	if ( is_wp_error( $raw ) ) { return $raw; }
	if ( ! $raw['active'] ) { return array(); }
	if ( 'shared' === ( $raw['context']['mode'] ?? '' ) ) {
		$link = ps_pen_paper_get_universe_link( $campaign->ID );
		if ( is_wp_error( $link ) ) { return $link; }
		$source = ps_pen_paper_record_post( $raw['context']['source_id'] );
		$scope = ps_pen_paper_get_universe( $link['universe_id'] );
		if ( ! $scope || ! $source || $raw['context']['universe_id'] !== $scope->ID ||
			'pspp_character' !== $source->post_type || 'private' !== $source->post_status ||
			(int) $source->post_parent !== (int) $scope->ID || (int) $source->post_author !== $user_id ) { return array(); }
		$character = $source; $raw = ps_pen_paper_raw_character_state( $character->ID );
		if ( is_wp_error( $raw ) ) { return $raw; }
	}
	$portrait = ps_pen_paper_portrait_state( $character->ID );
	if ( is_wp_error( $portrait ) ) { return $portrait; }
	if ( empty( $portrait['active']['share'] ) || ! $raw['active'] ) { return array(); }
	$style = ps_pen_paper_image_view_style( $portrait['active']['view'] ?? null );
	if ( is_wp_error( $style ) ) { return $style; }
	$identity = array( 'name' => $raw['active']['name'], 'portrait_style' => $style,
		'portrait_key' => $portrait['active']['asset_id'] ? hash( 'sha256', wp_json_encode( array( $character->ID, $portrait ) ) ) : '' );
	if ( $with_image && $portrait['active']['asset_id'] ) {
		$image = ps_pen_paper_private_image_data( $scope, $portrait['active']['asset_id'] );
		if ( is_wp_error( $image ) ) { return $image; }
		$identity['image'] = $image;
	}
	return $identity;
}

function ps_pen_paper_table_portrait_request( $request ) {
	return ps_pen_paper_scope_write( (int) $request['id'], function () use ( $request ) {
		$campaign = ps_pen_paper_get_campaign( (int) $request['id'] );
		if ( ! $campaign || ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) ) {
			return new WP_Error( 'forbidden', __( 'Diese Runde ist für dich nicht verfügbar.', 'ps-pen-paper' ), array( 'status' => 403 ) );
		}
		$epoch = ps_pen_paper_scope_epoch( $campaign->ID );
		if ( is_wp_error( $epoch ) ) { return $epoch; }
		if ( $epoch !== ( $request->get_param( 'scope_epoch' ) ?? '' ) ) { return new WP_Error( 'scope_changed', __( 'Bitte lade den Spieltisch neu.', 'ps-pen-paper' ), array( 'status' => 409 ) ); }
		$members = ps_pen_paper_table_members( $campaign );
		if ( ! isset( $members[ (int) $request['user'] ] ) ) { return new WP_Error( 'forbidden', __( 'Diese Person nimmt nicht an der Runde teil.', 'ps-pen-paper' ), array( 'status' => 403 ) ); }
		$identity = ps_pen_paper_table_identity( $campaign, (int) $request['user'], true );
		if ( is_wp_error( $identity ) ) { return $identity; }
		if ( empty( $identity['image'] ) || $identity['portrait_key'] !== $request->get_param( 'key' ) ) {
			return new WP_Error( 'portrait_not_shared', __( 'Dieses Porträt ist nicht mehr freigegeben. Bitte aktualisiere die Runde.', 'ps-pen-paper' ), array( 'status' => 403 ) );
		}
		$response = rest_ensure_response( array( 'image' => $identity['image'] ) );
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	} );
}

function ps_pen_paper_table_turn( $campaign_id ) {
	$turn = get_post_meta( $campaign_id, '_pspp_turn', true );
	return $turn ? $turn : array( 'revision' => 0, 'user_id' => 0, 'name' => __( 'Freies Spiel', 'ps-pen-paper' ) );
}

function ps_pen_paper_table_state( $campaign ) {
	$epoch = ps_pen_paper_scope_epoch( $campaign->ID );
	if ( is_wp_error( $epoch ) ) { return $epoch; }
	if ( ! ps_pen_paper_scope_observe( $campaign->ID ) ) { return new WP_Error( 'scope_changed', __( 'Die Runde wurde wiederhergestellt. Bitte lade den Spieltisch neu.', 'ps-pen-paper' ), array( 'status' => 409 ) ); }
	$members = ps_pen_paper_table_members( $campaign );
	foreach ( $members as &$member ) {
		$identity = ps_pen_paper_table_identity( $campaign, (int) $member['id'] );
		if ( is_wp_error( $identity ) ) { return $identity; }
		$member = array_merge( $member, $identity );
		$seen = get_transient( ps_pen_paper_table_key( $campaign->ID, $member['id'], 'seen', $epoch ) );
		$away = get_transient( ps_pen_paper_table_key( $campaign->ID, $member['id'], 'away', $epoch ) );
		$member['status'] = ! $seen ? 'unreported' : ( time() - (int) $seen > 120 ? 'offline' : ( false !== $away ? 'afk' : 'online' ) );
		$member['note'] = 'afk' === $member['status'] && is_string( $away ) ? $away : '';
	}
	unset( $member );
	$turn = ps_pen_paper_table_turn( $campaign->ID );
	if ( isset( $members[ $turn['user_id'] ] ) ) { $turn['name'] = $members[ $turn['user_id'] ]['name']; }
	$turn['available'] = ! $turn['user_id'] || isset( $members[ $turn['user_id'] ] );
	$round = ps_pen_paper_game_round( $campaign->ID );
	if ( is_wp_error( $round ) ) { return $round; }
	$rolls = ps_pen_paper_chat_rolls( $campaign );
	if ( is_wp_error( $rolls ) ) { return $rolls; }
	return array( 'members' => array_values( $members ), 'turn' => $turn, 'game_round' => $round, 'chat_rolls' => $rolls );
}

function ps_pen_paper_table_permission( $request ) {
	$campaign = ps_pen_paper_get_campaign( (int) $request['id'] );
	return $campaign && ps_pen_paper_can_access_site() && ps_pen_paper_can_view_campaign( $campaign )
		? true : new WP_Error( 'forbidden', __( 'Diese Runde ist für dich nicht verfügbar.', 'ps-pen-paper' ), array( 'status' => 403 ) );
}

function ps_pen_paper_table_request( $request ) {
	return ps_pen_paper_scope_write( (int) $request['id'], function () use ( $request ) { return ps_pen_paper_table_request_locked( $request ); } );
}

function ps_pen_paper_table_request_locked( $request ) {
	$campaign = ps_pen_paper_get_campaign( (int) $request['id'] );
	if ( ! $campaign || ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) ) { return new WP_Error( 'forbidden', __( 'Diese Runde ist für dich nicht verfügbar.', 'ps-pen-paper' ), array( 'status' => 403 ) ); }
	$epoch = ps_pen_paper_scope_epoch( $campaign->ID );
	if ( is_wp_error( $epoch ) ) { return $epoch; }
	$expected = $request->get_param( 'scope_epoch' );
	if ( $epoch !== ( null === $expected ? '' : $expected ) ) { return new WP_Error( 'scope_changed', __( 'Die Runde wurde wiederhergestellt. Bitte lade den Spieltisch neu.', 'ps-pen-paper' ), array( 'status' => 409 ) ); }
	if ( 'POST' === $request->get_method() ) {
		$input = $request->get_json_params();
		$operation = is_array( $input ) && isset( $input['operation'] ) && is_string( $input['operation'] ) ? $input['operation'] : '';
		$members = ps_pen_paper_table_members( $campaign );
		if ( 'turn' === $operation ) {
			if ( ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
				return new WP_Error( 'forbidden', __( 'Nur die Spielleitung darf den Zug wechseln.', 'ps-pen-paper' ), array( 'status' => 403 ) );
			}
			$before = ps_pen_paper_table_turn( $campaign->ID );
			if ( ! isset( $input['user_id'], $input['revision'] ) || ! is_int( $input['user_id'] ) || ! is_int( $input['revision'] ) || $input['revision'] !== $before['revision'] ) {
				return new WP_Error( 'conflict', __( 'Der Zugstand ist ungültig oder wurde inzwischen geändert. Bitte aktualisiere die Runde.', 'ps-pen-paper' ), array( 'status' => 409 ) );
			}
			$id = $input['user_id'];
			if ( 0 !== $id && ! isset( $members[ $id ] ) ) {
				return new WP_Error( 'invalid_member', __( 'Diese Person gehört nicht zur Runde.', 'ps-pen-paper' ), array( 'status' => 400 ) );
			}
			if ( $id !== $before['user_id'] ) {
				$after = array( 'revision' => $before['revision'] + 1, 'user_id' => $id, 'name' => $id ? $members[ $id ]['name'] : __( 'Freies Spiel', 'ps-pen-paper' ) );
				$saved = ps_pen_paper_save_document_event( $campaign->ID, $campaign->ID, '_pspp_turn', $before, $after, array(
					'kind' => 'turn_changed', 'visibility' => 'group', 'recipient_id' => 0,
					'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $campaign->ID )['revision'],
				) );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
			}
		} elseif ( in_array( $operation, array( 'join', 'heartbeat', 'afk', 'back', 'leave' ), true ) ) {
			if ( ! isset( $members[ get_current_user_id() ] ) ) {
				return new WP_Error( 'not_member', __( 'Nur eingetragene Rundenteilnehmer können ihre Anwesenheit teilen.', 'ps-pen-paper' ), array( 'status' => 403 ) );
			}
			$seen_key = ps_pen_paper_table_key( $campaign->ID, get_current_user_id(), 'seen', $epoch );
			$away_key = ps_pen_paper_table_key( $campaign->ID, get_current_user_id(), 'away', $epoch );
			if ( ! in_array( $operation, array( 'join', 'leave' ), true ) && ! get_transient( $seen_key ) ) {
				return new WP_Error( 'presence_inactive', __( 'Die Anwesenheitsanzeige ist beendet. Bitte teile deinen Status bei Bedarf erneut.', 'ps-pen-paper' ), array( 'status' => 409 ) );
			}
			if ( 'afk' === $operation ) {
				if ( ! isset( $input['note'] ) || ! is_string( $input['note'] ) || strlen( $input['note'] ) > 160 ) {
					return new WP_Error( 'invalid_note', __( 'Bitte verwende eine kurze Pausennotiz.', 'ps-pen-paper' ), array( 'status' => 400 ) );
				}
				if ( ! set_transient( $away_key, sanitize_text_field( $input['note'] ), DAY_IN_SECONDS ) ) {
					// Repeating an unchanged status need not change its stored value.
					if ( get_transient( $away_key ) !== sanitize_text_field( $input['note'] ) ) {
						return new WP_Error( 'save_failed', __( 'Der Pausenstatus konnte nicht gespeichert werden.', 'ps-pen-paper' ) );
					}
				}
			} elseif ( 'back' === $operation || 'leave' === $operation ) {
				delete_transient( $away_key );
				if ( false !== get_transient( $away_key ) ) {
					return new WP_Error( 'save_failed', __( 'Der Pausenstatus konnte nicht aufgehoben werden.', 'ps-pen-paper' ) );
				}
			}
			if ( 'leave' === $operation ) {
				delete_transient( $seen_key );
				if ( false !== get_transient( $seen_key ) ) {
					return new WP_Error( 'save_failed', __( 'Die Anwesenheitsanzeige konnte nicht beendet werden.', 'ps-pen-paper' ) );
				}
			} else {
				$seen_at = time();
				set_transient( $seen_key, $seen_at, DAY_IN_SECONDS );
				if ( (int) get_transient( $seen_key ) !== $seen_at ) {
					return new WP_Error( 'save_failed', __( 'Die Anwesenheit konnte nicht gespeichert werden.', 'ps-pen-paper' ) );
				}
			}
		} else {
			return new WP_Error( 'invalid_operation', __( 'Ungültige Spieltisch-Aktion.', 'ps-pen-paper' ), array( 'status' => 400 ) );
		}
	}
	$state = ps_pen_paper_table_state( $campaign );
	if ( is_wp_error( $state ) ) { return $state; }
	$response = rest_ensure_response( $state );
	$response->header( 'Cache-Control', 'no-store, private' );
	return $response;
}

function ps_pen_paper_register_table_routes() {
	register_rest_route( 'ps-pen-paper/v1', '/campaigns/(?P<id>[1-9]\d*)/participants/(?P<user>[1-9]\d*)/portrait', array(
		'methods' => 'GET', 'callback' => 'ps_pen_paper_table_portrait_request', 'permission_callback' => 'ps_pen_paper_table_permission',
	) );
	register_rest_route( 'ps-pen-paper/v1', '/campaigns/(?P<id>[1-9]\d*)/table', array(
		'methods' => array( 'GET', 'POST' ),
		'callback' => 'ps_pen_paper_table_request',
		'permission_callback' => 'ps_pen_paper_table_permission',
	) );
}

function ps_pen_paper_table_map_request( $request ) {
	$campaign = ps_pen_paper_get_campaign( (int) $request['id'] );
	if ( ! $campaign || ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	$characters = get_posts( array( 'post_type' => 'pspp_character', 'post_status' => 'private', 'post_parent' => $campaign->ID,
		'author' => get_current_user_id(), 'posts_per_page' => -1 ) );
	return ps_pen_paper_with_item_character_locks( $campaign, $characters, function () use ( $request, $campaign, $characters ) {
		if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
		// Native markers belong to users, not characters: suspend that user's movement.
		foreach ( $characters as $character ) {
			$allowed = ps_pen_paper_require_character_game_action( $campaign, (int) $character->ID );
			if ( is_wp_error( $allowed ) ) { return $allowed; }
		}
		return ps_pen_paper_map_request( $request );
	} );
}

function ps_pen_paper_table_guard_map_callback( $response, $request, $route, $handler ) {
	if ( null !== $response || 'ps_pen_paper_map_request' !== ( $handler['callback'] ?? null ) || 'POST' !== $request->get_method() ) { return $response; }
	$input = $request->get_json_params();
	return is_array( $input ) && 'move' === ( $input['operation'] ?? null ) ? ps_pen_paper_table_map_request( $request ) : $response;
}

add_filter( 'rest_dispatch_request', 'ps_pen_paper_table_guard_map_callback', 10, 4 );

function ps_pen_paper_validate_game_round( $value ) {
	return is_array( $value ) && array_keys( $value ) === array( 'revision', 'round', 'active' ) &&
		is_int( $value['revision'] ) && $value['revision'] >= 0 &&
		is_int( $value['round'] ) && $value['round'] >= 0 && $value['round'] <= 1000000000 && is_bool( $value['active'] );
}

function ps_pen_paper_game_round( $campaign_id ) {
	$value = get_post_meta( $campaign_id, '_pspp_game_round', true );
	if ( '' === $value ) { return array( 'revision' => 0, 'round' => 0, 'active' => false ); }
	return ps_pen_paper_validate_game_round( $value ) ? $value : ps_pen_paper_equipment_error( 'invalid_game_round' );
}

function ps_pen_paper_control_game_round( $campaign, $operation, $revision ) {
	if ( 'start_game_session' === $operation ) {
		return ps_pen_paper_with_inheritance_lock( (int) $campaign->ID, function () use ( $campaign, $operation, $revision ) {
			return ps_pen_paper_control_game_round_locked( $campaign, $operation, $revision );
		} );
	}
	return ps_pen_paper_control_game_round_locked( $campaign, $operation, $revision );
}

function ps_pen_paper_control_game_round_locked( $campaign, $operation, $revision ) {
	if ( 'pspp_campaign' !== $campaign->post_type || ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	$play = ps_pen_paper_play_round( $campaign->ID );
	if ( is_wp_error( $play ) ) { return $play; }
	if ( $play && ( 'guided' !== $play['mode'] || 'active' !== $play['status'] || 'advance_game_round' !== $operation ) ) {
		return ps_pen_paper_play_error( __( 'Nutze den Start, die Pause oder den Abschluss dieser Runde. Automatische Abenteuer zählen ihre Spielzüge selbst.', 'ps-pen-paper' ) );
	}
	$characters = ps_pen_paper_get_characters( $campaign );
	if ( $play ) {
		$ids = array_column( array_filter( $play['participants'], static function ( $participant ) { return $participant['active']; } ), 'character_id' );
		$characters = array_filter( $characters, static function ( $character ) use ( $ids ) { return in_array( (int) $character->ID, $ids, true ); } );
	}
	return ps_pen_paper_with_item_character_locks( $campaign, $characters, function () use ( $campaign, $operation, $revision, $characters ) {
		try {
			return ps_pen_paper_atomic_event( $campaign->ID, function () use ( $campaign, $operation, $revision, $characters ) {
			if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
			$play = ps_pen_paper_play_round( $campaign->ID );
			if ( is_wp_error( $play ) ) { return $play; }
			if ( $play && ( 'guided' !== $play['mode'] || 'active' !== $play['status'] || 'advance_game_round' !== $operation ) ) { return ps_pen_paper_play_error(); }
			$before = ps_pen_paper_game_round( $campaign->ID );
			if ( is_wp_error( $before ) ) { return $before; }
			if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision ) { return ps_pen_paper_equipment_error( 'conflict' ); }
			$after = $before;
			if ( 'advance_game_round' === $operation ) {
				if ( ! $before['active'] || $before['round'] >= 1000000000 ) { return ps_pen_paper_equipment_error( 'game_session_inactive' ); }
				$after['round']++;
			} elseif ( 'start_game_session' === $operation ) {
				if ( $before['active'] ) { return 0; }
				$inherited = ps_pen_paper_require_inherited_rules( $campaign->ID );
				if ( is_wp_error( $inherited ) ) { return $inherited; }
				$after['active'] = true;
			} elseif ( 'end_game_session' === $operation ) {
				if ( ! $before['active'] ) { return 0; }
				$after['active'] = false;
			} else { return ps_pen_paper_equipment_error(); }
			$after['revision']++;
			$expired = ps_pen_paper_write_expired_item_effects( $campaign, $characters, $after['round'] );
			if ( is_wp_error( $expired ) ) { return $expired; }
			return ps_pen_paper_write_document_event( $campaign->ID, $campaign->ID, '_pspp_game_round', $before, $after,
				array( 'kind' => 'game_round', 'visibility' => 'group', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $campaign->ID )['revision'] ), false );
			} );
		} finally {
			foreach ( $characters as $character ) {
				$raw = ps_pen_paper_raw_character_state( $character->ID );
				clean_post_cache( $character->ID );
				if ( ! is_wp_error( $raw ) && isset( $raw['context']['source_id'] ) ) { clean_post_cache( $raw['context']['source_id'] ); }
			}
		}
	} );
}

// Internal writer; callers hold all affected character/context locks and the event transaction.
function ps_pen_paper_write_expired_item_effects( $campaign, $characters, $round, $all = false ) {
	foreach ( $characters as $character ) {
		$gear = ps_pen_paper_gear_state( $character->ID );
		if ( is_wp_error( $gear ) ) { return $gear; }
		$canonical = ps_pen_paper_shared_gear_target( $character->ID );
		if ( is_wp_error( $canonical ) ) { return $canonical; }
		if ( $all && $canonical ) {
			$closed = ps_pen_paper_write_play_trade_closures( $campaign, (int) $character->ID );
			if ( is_wp_error( $closed ) ) { return $closed; }
		}
		if ( ! isset( $gear['uses'] ) ) { continue; }
		$next = $gear;
		if ( $all && $canonical ) {
			foreach ( $next['uses']['pending'] ?? array() as $uuid => $request ) {
				if ( 'game' === $request['context'] ) { unset( $next['uses']['pending'][ $uuid ] ); }
			}
			if ( 'game' === ( $next['pending_context'] ?? '' ) ) { $next['pending'] = null; unset( $next['pending_context'] ); }
		}
		foreach ( $next['uses']['effects'] as $uuid => &$effect ) {
			if ( $canonical && 'profile' === $next['uses']['receipts'][ $uuid ]['context'] ) { continue; }
			if ( $effect['temporary'] && ! $effect['ended'] && ( $all || ( $effect['expires'] && $effect['expires'] <= $round ) ) ) {
				$ended = ps_pen_paper_write_item_effect_ending( $campaign, $character, $effect );
				if ( is_wp_error( $ended ) ) { return $ended; }
				$effect['ended'] = true;
			}
		}
		unset( $effect );
		if ( $next === $gear ) { continue; }
		$next['revision']++;
		$state = ps_pen_paper_resolve_character_state( $campaign, $character, false );
		if ( is_wp_error( $state ) ) { return $state; }
		$bonus = ps_pen_paper_gear_bonus( $campaign, $next['active'], $state['active'] );
		if ( is_wp_error( $bonus ) ) { return $bonus; }
		foreach ( $bonus as $id => $delta ) { $state['active']['values'][ $id ] += $delta; }
		$valid = ps_pen_paper_apply_item_effects( $campaign, $character, $state, $next );
		if ( is_wp_error( $valid ) ) { return $valid; }
		$result = ps_pen_paper_write_item_use_event( $campaign, $character, $gear, $next );
		if ( is_wp_error( $result ) ) { return $result; }
	}
	return 0;
}
