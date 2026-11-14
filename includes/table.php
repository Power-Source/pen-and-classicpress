<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_table_members( $campaign ) {
	$ids = array_unique( array_map( 'intval', array_merge( array( $campaign->post_author ), get_post_meta( $campaign->ID, '_pspp_gm', false ), get_post_meta( $campaign->ID, '_pspp_player', false ) ) ) );
	$members = array();
	foreach ( $ids as $id ) {
		$user = ps_pen_paper_site_user( $id );
		if ( $user && user_can( $user, 'read' ) ) {
			$members[ $id ] = array( 'id' => $id, 'name' => $user->display_name, 'role' => ps_pen_paper_campaign_role( $campaign, $id ) );
		}
	}
	return $members;
}

function ps_pen_paper_table_key( $campaign_id, $user_id, $kind ) {
	return 'pspp_' . $kind . '_' . get_current_blog_id() . '_' . $campaign_id . '_' . $user_id;
}

function ps_pen_paper_table_turn( $campaign_id ) {
	$turn = get_post_meta( $campaign_id, '_pspp_turn', true );
	return $turn ? $turn : array( 'revision' => 0, 'user_id' => 0, 'name' => __( 'Freies Spiel', 'ps-pen-paper' ) );
}

function ps_pen_paper_table_state( $campaign ) {
	$members = ps_pen_paper_table_members( $campaign );
	foreach ( $members as &$member ) {
		$seen = get_transient( ps_pen_paper_table_key( $campaign->ID, $member['id'], 'seen' ) );
		$away = get_transient( ps_pen_paper_table_key( $campaign->ID, $member['id'], 'away' ) );
		$member['status'] = ! $seen ? 'unreported' : ( time() - (int) $seen > 120 ? 'offline' : ( false !== $away ? 'afk' : 'online' ) );
		$member['note'] = 'afk' === $member['status'] && is_string( $away ) ? $away : '';
	}
	unset( $member );
	$turn = ps_pen_paper_table_turn( $campaign->ID );
	$turn['available'] = ! $turn['user_id'] || isset( $members[ $turn['user_id'] ] );
	return array( 'members' => array_values( $members ), 'turn' => $turn );
}

function ps_pen_paper_table_permission( $request ) {
	$campaign = ps_pen_paper_get_campaign( (int) $request['id'] );
	return $campaign && ps_pen_paper_can_access_site() && ps_pen_paper_can_view_campaign( $campaign )
		? true : new WP_Error( 'forbidden', __( 'Diese Runde ist für dich nicht verfügbar.', 'ps-pen-paper' ), array( 'status' => 403 ) );
}

function ps_pen_paper_table_request( $request ) {
	$campaign = ps_pen_paper_get_campaign( (int) $request['id'] );
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
			$seen_key = ps_pen_paper_table_key( $campaign->ID, get_current_user_id(), 'seen' );
			$away_key = ps_pen_paper_table_key( $campaign->ID, get_current_user_id(), 'away' );
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
	$response = rest_ensure_response( ps_pen_paper_table_state( $campaign ) );
	$response->header( 'Cache-Control', 'no-store, private' );
	return $response;
}

function ps_pen_paper_register_table_routes() {
	register_rest_route( 'ps-pen-paper/v1', '/campaigns/(?P<id>[1-9]\d*)/table', array(
		'methods' => array( 'GET', 'POST' ),
		'callback' => 'ps_pen_paper_table_request',
		'permission_callback' => 'ps_pen_paper_table_permission',
	) );
}
