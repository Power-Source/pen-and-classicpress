<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_character_context_modes() {
	return array( 'shared' => __( 'Gemeinsamer Basisstand', 'ps-pen-paper' ), 'copy' => __( 'Unabhängige Kampagnenkopie', 'ps-pen-paper' ) );
}

function ps_pen_paper_raw_character_state( $id ) {
	$state = get_post_meta( $id, '_pspp_character', true );
	if ( ! is_array( $state ) || ! isset( $state['revision'] ) || ! is_int( $state['revision'] ) || $state['revision'] < 1 ||
		! array_key_exists( 'active', $state ) || ! array_key_exists( 'pending', $state ) ||
		( null !== $state['active'] && ! is_array( $state['active'] ) ) || ( null !== $state['pending'] && ! is_array( $state['pending'] ) ) ) {
		return new WP_Error( 'invalid_character', __( 'Der Charakterstand ist beschädigt. Bitte informiere die Spielleitung.', 'ps-pen-paper' ) );
	}
	if ( array_key_exists( 'conditions', $state ) && ! ps_pen_paper_valid_character_conditions( $state['conditions'] ) ) {
		return ps_pen_paper_condition_error();
	}
	return $state;
}

function ps_pen_paper_validate_context( $context, $rules ) {
	if ( ! is_array( $context ) || ! isset( $context['universe_id'], $context['source_id'], $context['source_revision'], $context['mode'], $context['overrides'], $context['note'] ) ||
		! is_int( $context['universe_id'] ) || $context['universe_id'] < 1 || ! is_int( $context['source_id'] ) || $context['source_id'] < 1 ||
		! is_int( $context['source_revision'] ) || $context['source_revision'] < 1 || ! is_string( $context['mode'] ) || ! isset( ps_pen_paper_character_context_modes()[ $context['mode'] ] ) ||
		! is_array( $context['overrides'] ) || ! is_string( $context['note'] ) || strlen( $context['note'] ) > 2000 ) {
		return new WP_Error( 'invalid_context', __( 'Der Kampagnenkontext ist beschädigt. Bitte informiere die Spielleitung.', 'ps-pen-paper' ) );
	}
	$fields = array_column( $rules['fields'], null, 'id' );
	if ( array_key_exists( 'gear_shared', $context ) && ( true !== $context['gear_shared'] || 'shared' !== $context['mode'] ) ) {
		return new WP_Error( 'invalid_context', __( 'Das gemeinsame Inventar braucht eine gültige Verbindung zum Basisbogen.', 'ps-pen-paper' ) );
	}
	foreach ( $context['overrides'] as $id => $value ) {
		if ( ! isset( $fields[ $id ] ) || ! in_array( $fields[ $id ]['type'], array( 'number', 'attribute' ), true ) || ! is_int( $value ) ||
			$value < $fields[ $id ]['min'] || $value > $fields[ $id ]['max'] ||
			( ! empty( $rules['progression'] ) && in_array( $id, array( $rules['progression']['experience_field'], $rules['progression']['level_field'] ), true ) ) ) {
			return new WP_Error( 'invalid_context', __( 'Eine Kampagnenauflage passt nicht zum Regelwerk. Bitte informiere die Spielleitung.', 'ps-pen-paper' ) );
		}
	}
	if ( isset( $context['resources'] ) ) {
		if ( ! is_array( $context['resources'] ) || count( $context['resources'] ) > 30 ) {
			return new WP_Error( 'invalid_context', __( 'Die Ressourcenauflagen sind ungültig.', 'ps-pen-paper' ) );
		}
		foreach ( $context['resources'] as $id => $resource ) {
			if ( ! isset( $fields[ $id ] ) || 'resource' !== $fields[ $id ]['type'] || ! is_array( $resource ) ||
				! isset( $resource['mode'], $resource['maximum'] ) || ! in_array( $resource['mode'], array( 'local', 'shared' ), true ) ||
				( 'shared' === $resource['mode'] && 'shared' !== $context['mode'] ) ||
				! is_int( $resource['maximum'] ) || $resource['maximum'] < $fields[ $id ]['min'] || $resource['maximum'] > $fields[ $id ]['max'] ||
				count( $resource ) !== ( 'local' === $resource['mode'] ? 3 : 2 ) ||
				( 'local' === $resource['mode'] && ( ! isset( $resource['current'] ) || ! is_int( $resource['current'] ) ||
					$resource['current'] < $fields[ $id ]['min'] || $resource['current'] > $resource['maximum'] ) ) ) {
				return new WP_Error( 'invalid_context', __( 'Bitte prüfe Ressourcenart, aktuellen Wert und Höchstwert der Kampagnenauflage.', 'ps-pen-paper' ) );
			}
		}
	}
	return $context;
}

function ps_pen_paper_apply_context_values( $snapshot, $context ) {
	if ( ! $snapshot ) { return $snapshot; }
	foreach ( $context['overrides'] as $id => $value ) { $snapshot['values'][ $id ] = $value; }
	foreach ( $context['resources'] ?? array() as $id => $resource ) {
		if ( ! isset( $snapshot['values'][ $id ]['current'], $snapshot['values'][ $id ]['maximum'] ) ) {
			return new WP_Error( 'invalid_context', __( 'Eine Ressourcenauflage passt nicht mehr zum Charakterbogen.', 'ps-pen-paper' ) );
		}
		$current = 'local' === $resource['mode'] ? $resource['current'] : $snapshot['values'][ $id ]['current'];
		if ( $current > $resource['maximum'] ||
			( 'shared' === $resource['mode'] && $resource['maximum'] > $snapshot['values'][ $id ]['maximum'] ) ) {
			return new WP_Error( 'resource_limit_conflict', __( 'Der gemeinsame Ressourcenstand passt nicht zum Kampagnenmaximum. Passe die Auflage an oder wähle eigene Kampagnenwerte. Es werden keine Werte abgeschnitten.', 'ps-pen-paper' ) );
		}
		$snapshot['values'][ $id ] = array( 'current' => $current, 'maximum' => $resource['maximum'] );
	}
	return $snapshot;
}

function ps_pen_paper_context_snapshot( $snapshot, $rules ) {
	if ( ! is_array( $snapshot ) || ! isset( $snapshot['name'], $snapshot['values'] ) || ! is_array( $snapshot['values'] ) ) {
		return new WP_Error( 'invalid_context', __( 'Der Grundstand des Kampagnenbogens ist unvollständig.', 'ps-pen-paper' ) );
	}
	foreach ( $snapshot['values'] as &$value ) {
		if ( is_int( $value ) ) {
			$value = (string) $value;
		} elseif ( is_array( $value ) ) {
			foreach ( $value as &$number ) {
				if ( is_int( $number ) ) { $number = (string) $number; }
			}
			unset( $number );
		}
	}
	unset( $value );
	return ps_pen_paper_validate_character( $snapshot, $rules );
}

function ps_pen_paper_character_state( $scope, $character, $with_equipment = true ) {
	if ( ! ps_pen_paper_get_character( $scope, $character->ID ) ) {
		return new WP_Error( 'forbidden', __( 'Dieser Charakterstand ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	return ps_pen_paper_resolve_character_state( $scope, $character, $with_equipment );
}

// Internal resolver; public callers must first check access to the character.
function ps_pen_paper_resolve_character_state( $scope, $character, $with_equipment = true ) {
	$state = ps_pen_paper_resolve_character_values( $scope, $character, $with_equipment );
	return $with_equipment ? ps_pen_paper_apply_character_conditions( $scope, $character, $state ) : $state;
}

function ps_pen_paper_resolve_character_values( $scope, $character, $with_equipment = true ) {
	$state = ps_pen_paper_raw_character_state( $character->ID );
	if ( is_wp_error( $state ) ) {
		return $state;
	}
	if ( ! array_key_exists( 'context', $state ) ) { return $with_equipment ? ps_pen_paper_apply_gear( $scope, $character, $state ) : $state; }
	$rules = ps_pen_paper_get_rules( $scope->ID );
	$context = ps_pen_paper_validate_context( $state['context'], $rules );
	if ( is_wp_error( $context ) ) {
		return $context;
	}
	if ( 'shared' === $context['mode'] ) {
		$link = ps_pen_paper_get_universe_link( $scope->ID );
		$source = ps_pen_paper_record_post( $context['source_id'] );
		$base = $source ? ps_pen_paper_raw_character_state( $source->ID ) : null;
		$base_request = null === $state['active'] && null !== $state['pending'];
		if ( $base_request ) {
			$pending = ps_pen_paper_context_snapshot( $state['pending'], $rules );
			if ( is_wp_error( $pending ) ) { return $pending; }
			$is_current = ! is_wp_error( $link ) && $link['universe_id'] === $context['universe_id'] &&
				ps_pen_paper_get_universe( $context['universe_id'] ) && $source && 'pspp_character' === $source->post_type &&
				'private' === $source->post_status && (int) $source->post_parent === $context['universe_id'] &&
				(int) $source->post_author === (int) $character->post_author && is_array( $base ) &&
				$base['active'] && ! array_key_exists( 'context', $base ) && $base['revision'] === $context['source_revision'] &&
				ps_pen_paper_character_schema( $rules ) === ps_pen_paper_character_schema( ps_pen_paper_get_rules( $context['universe_id'] ) );
			$state['active'] = null;
			$state['pending'] = $pending;
			if ( ! $is_current ) { $state['base_request_stale'] = true; }
			return $with_equipment ? ps_pen_paper_apply_gear( $scope, $character, $state ) : $state;
		}
		if ( is_wp_error( $link ) || $link['universe_id'] !== $context['universe_id'] || ! ps_pen_paper_get_universe( $context['universe_id'] ) ||
			! $source || 'pspp_character' !== $source->post_type || 'private' !== $source->post_status ||
			(int) $source->post_parent !== $context['universe_id'] || (int) $source->post_author !== (int) $character->post_author ||
			ps_pen_paper_character_schema( $rules ) !== ps_pen_paper_character_schema( ps_pen_paper_get_rules( $context['universe_id'] ) ) ) {
			return new WP_Error( 'invalid_context', __( 'Die Verbindung zum Basisbogen oder dessen Regelwerk ist nicht mehr gültig. Es werden keine Ersatzwerte verwendet.', 'ps-pen-paper' ) );
		}
		// Admission exposes only this assigned base's approved snapshot, never its pending data or world.
		$base = ps_pen_paper_raw_character_state( $source->ID );
		if ( is_wp_error( $base ) || null === $base['active'] || array_key_exists( 'context', $base ) ) {
			return new WP_Error( 'invalid_context', __( 'Der freigegebene Basisbogen ist nicht verfügbar. Bitte informiere die Universumsleitung.', 'ps-pen-paper' ) );
		}
		$state['active'] = $base['active'];
		$state['pending'] = null;
		$state['base_revision'] = $base['revision'];
	}

	$snapshot = ps_pen_paper_context_snapshot( $state['active'], $rules );
	if ( is_wp_error( $snapshot ) ) { return $snapshot; }
	$state['active'] = $snapshot;
	$state['active'] = ps_pen_paper_apply_context_values( $state['active'], $context );
	if ( is_wp_error( $state['active'] ) ) { return $state['active']; }
	return $with_equipment ? ps_pen_paper_apply_gear( $scope, $character, $state ) : $state;
}

function ps_pen_paper_with_character_context_locks( $campaign_id, $character_id, $source_id, $callback ) {
	$source = ps_pen_paper_record_post( $source_id );
	$universe_id = $source ? (int) $source->post_parent : 0;
	if ( ! $universe_id ) { return new WP_Error( 'invalid_context', __( 'Der Basisbogen ist nicht mehr verfügbar.', 'ps-pen-paper' ) ); }
	$character_ids = array_values( array_unique( array( (int) $character_id, (int) $source_id ) ) );
	sort( $character_ids, SORT_NUMERIC );
	$scope_ids = array_values( array_unique( array( (int) $campaign_id, $universe_id ) ) );
	sort( $scope_ids, SORT_NUMERIC );
	$lock_scopes = function ( $index ) use ( &$lock_scopes, $scope_ids, $callback ) {
		if ( ! isset( $scope_ids[ $index ] ) ) { return $callback(); }
		return ps_pen_paper_scope_lock( $scope_ids[ $index ], function () use ( &$lock_scopes, $index ) { return $lock_scopes( $index + 1 ); } );
	};
	return ps_pen_paper_with_character_locks( $character_ids, function () use ( $lock_scopes ) { return $lock_scopes( 0 ); } );
}

function ps_pen_paper_request_base_character( $campaign_id, $source_id, $source_revision, $rules_revision ) {
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	$link = $campaign ? ps_pen_paper_get_universe_link( $campaign_id ) : null;
	if ( ! ps_pen_paper_can_access_site() || ! $campaign || is_wp_error( $link ) || ! $link['universe_id'] ||
		! ps_pen_paper_campaign_role( $campaign, get_current_user_id() ) ) {
		return new WP_Error( 'forbidden', __( 'Nur ein Kampagnenmitglied kann einen eigenen Basisbogen anfragen.', 'ps-pen-paper' ) );
	}
	$universe = ps_pen_paper_get_universe( $link['universe_id'] );
	$source = $universe ? ps_pen_paper_record_post( $source_id ) : null;
	if ( ! $source || 'pspp_character' !== $source->post_type || 'private' !== $source->post_status ||
		(int) $source->post_parent !== (int) $universe->ID || (int) $source->post_author !== get_current_user_id() ) {
		return new WP_Error( 'forbidden', __( 'Du kannst nur deinen eigenen Basisbogen anfragen.', 'ps-pen-paper' ) );
	}
	$created_id = 0;
	$result = ps_pen_paper_with_character_context_locks( $campaign_id, $source_id, $source_id, function () use ( $campaign_id, $source_id, $source_revision, $rules_revision, $campaign, $universe, &$created_id ) {
		return ps_pen_paper_atomic_event( $campaign_id, function () use ( $campaign_id, $source_id, $source_revision, $rules_revision, $campaign, $universe, &$created_id ) {
			global $wpdb;
			if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $source_id ) ) !== (int) $source_id ) {
				return new WP_Error( 'conflict', __( 'Der Basisbogen wurde geändert. Bitte lade die Seite neu.', 'ps-pen-paper' ) );
			}
			clean_post_cache( $source_id );
			$source_state = ps_pen_paper_raw_character_state( $source_id );
			$rules = ps_pen_paper_get_rules( $campaign_id );
			$source_rules = ps_pen_paper_get_rules( $universe->ID );
			if ( is_wp_error( $source_state ) || ! $source_state['active'] || array_key_exists( 'context', $source_state ) ||
				! is_string( $source_revision ) || (string) $source_state['revision'] !== $source_revision ||
				! is_string( $rules_revision ) || (string) $rules['revision'] !== $rules_revision ||
				ps_pen_paper_character_schema( $rules ) !== ps_pen_paper_character_schema( $source_rules ) ) {
				return new WP_Error( 'conflict', __( 'Basisbogen oder Kampagnenregeln wurden geändert oder passen nicht zusammen. Bitte prüfe die Anfrage erneut.', 'ps-pen-paper' ) );
			}
			foreach ( get_posts( array( 'post_type' => 'pspp_character', 'post_status' => array( 'private', 'draft' ), 'post_parent' => $campaign_id, 'author' => get_current_user_id(), 'posts_per_page' => -1 ) ) as $existing ) {
				$existing_state = ps_pen_paper_raw_character_state( $existing->ID );
				if ( ! is_wp_error( $existing_state ) && isset( $existing_state['context']['source_id'] ) && $existing_state['context']['source_id'] === (int) $source_id &&
					( $existing_state['active'] || $existing_state['pending'] ) ) {
					return new WP_Error( 'request_exists', __( 'Für diesen Basisbogen besteht bereits ein Kampagnenbogen oder eine offene Anfrage.', 'ps-pen-paper' ) );
				}
			}
			$id = wp_insert_post( array(
				'post_type' => 'pspp_character', 'post_status' => 'draft', 'post_parent' => $campaign_id,
				'post_author' => get_current_user_id(), 'post_title' => 'PS Pen & Paper Character',
			), true );
			if ( is_wp_error( $id ) ) { return $id; }
			if ( ! $id ) { return new WP_Error( 'save_failed', __( 'Die Basisbogen-Anfrage konnte nicht angelegt werden.', 'ps-pen-paper' ) ); }
			$created_id = (int) $id;
			$after = array(
				'revision' => 1,
				'active' => null,
				'pending' => $source_state['active'],
				'context' => array(
					'universe_id' => (int) $universe->ID, 'source_id' => (int) $source_id,
					'source_revision' => $source_state['revision'], 'mode' => 'shared', 'overrides' => array(), 'note' => '',
				),
			);
			$result = ps_pen_paper_write_document_event( $campaign_id, $id, '_pspp_character', array( 'revision' => 0, 'active' => null, 'pending' => null ), $after, array(
				'kind' => 'character_requested', 'visibility' => 'owner', 'recipient_id' => get_current_user_id(),
				'rules_revision' => $rules['revision'], 'fields' => $rules['fields'],
			), true );
			if ( is_wp_error( $result ) ) { return $result; }
			$portrait_event = ps_pen_paper_write_base_portrait( $campaign, (int) $id, $source_id );
			return is_wp_error( $portrait_event ) ? $portrait_event : ( $portrait_event ?: $result );
		} );
	} );
	if ( is_wp_error( $result ) ) { return $result; }
	return true === $result && $created_id > 0 ? $created_id :
		new WP_Error( 'save_failed', __( 'Die Basisbogen-Anfrage konnte nicht bestätigt gespeichert werden.', 'ps-pen-paper' ) );
}

function ps_pen_paper_character_token( $character, $state ) {
	return $character->ID . ':' . $state['revision'] . ( isset( $state['base_revision'] ) ? ':' . $state['base_revision'] : '' ) .
		( isset( $state['equipment'] ) ? ':g' . $state['equipment']['revision'] . ':' . $state['equipment']['schema_revision'] : '' );
}

function ps_pen_paper_context_campaign( $campaign_id ) {
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! ps_pen_paper_can_access_site() || ! $campaign || ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die Kampagnenleitung darf Basisbögen übernehmen.', 'ps-pen-paper' ) );
	}
	$link = ps_pen_paper_get_universe_link( $campaign_id );
	if ( is_wp_error( $link ) ) {
		return $link;
	}
	$universe = ps_pen_paper_get_universe( $link['universe_id'] );
	if ( ! $universe || ! ps_pen_paper_can_manage_universe( $universe ) ) {
		return new WP_Error( 'forbidden', __( 'Für eine Übernahme musst du auch das zugeordnete Universum verwalten dürfen.', 'ps-pen-paper' ) );
	}
	return $universe;
}

function ps_pen_paper_adopt_universe_rules( $campaign_id, $source_revision, $confirmed ) {
	$universe = ps_pen_paper_context_campaign( $campaign_id );
	if ( is_wp_error( $universe ) ) {
		return $universe;
	}
	$before = ps_pen_paper_get_rules( $campaign_id );
	$source = ps_pen_paper_get_rules( $universe->ID );
	if ( 'confirmed' !== $confirmed || ! is_string( $source_revision ) || 0 === $source['revision'] || (string) $source['revision'] !== $source_revision ||
		0 !== $before['revision'] || ps_pen_paper_has_characters( $campaign_id ) ) {
		return new WP_Error( 'rules_conflict', __( 'Die Vorlage fehlt, wurde geändert oder die Kampagne besitzt bereits Regeln beziehungsweise Bögen. Nur eine ausdrücklich bestätigte leere Kampagne kann die Vorlage übernehmen.', 'ps-pen-paper' ) );
	}
	$after = $source;
	$after['revision'] = 1;
	$result = ps_pen_paper_save_document_event( $campaign_id, $campaign_id, '_pspp_rules', $before, $after, array(
		'kind' => 'rules_saved', 'visibility' => 'group', 'recipient_id' => 0, 'rules_revision' => 1, 'fields' => array(),
	) );
	return is_wp_error( $result ) ? $result : $after;
}

function ps_pen_paper_attach_base_character( $campaign_id, $source_id, $source_revision, $rules_revision, $mode, $confirmed ) {
	return ps_pen_paper_with_character_context_locks( $campaign_id, $source_id, $source_id, function () use ( $campaign_id, $source_id, $source_revision, $rules_revision, $mode, $confirmed ) {
		return ps_pen_paper_attach_base_character_locked( $campaign_id, $source_id, $source_revision, $rules_revision, $mode, $confirmed );
	} );
}

function ps_pen_paper_attach_base_character_locked( $campaign_id, $source_id, $source_revision, $rules_revision, $mode, $confirmed ) {
	$universe = ps_pen_paper_context_campaign( $campaign_id );
	if ( is_wp_error( $universe ) ) {
		return $universe;
	}
	$source = ps_pen_paper_get_character( $universe, $source_id );
	$base = $source ? ps_pen_paper_raw_character_state( $source->ID ) : null;
	if ( is_wp_error( $base ) ) {
		return $base;
	}
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	$rules = ps_pen_paper_get_rules( $campaign_id );
	if ( ! $source || ! $base['active'] || array_key_exists( 'context', $base ) || ! is_string( $source_revision ) || (string) $base['revision'] !== $source_revision ||
		! is_string( $rules_revision ) || 0 === $rules['revision'] || (string) $rules['revision'] !== $rules_revision ||
		ps_pen_paper_character_schema( $rules ) !== ps_pen_paper_character_schema( ps_pen_paper_get_rules( $universe->ID ) ) ) {
		return new WP_Error( 'conflict', __( 'Basisbogen oder Vorlage fehlen, wurden geändert oder passen nicht zur Kampagne. Bitte lade die Übernahme neu.', 'ps-pen-paper' ) );
	}
	if ( ! is_user_member_of_blog( (int) $source->post_author ) || ! ps_pen_paper_campaign_role( $campaign, (int) $source->post_author ) ) {
		return new WP_Error( 'invalid_owner', __( 'Der Besitzer des Basisbogens muss bereits Mitglied dieser Kampagne sein. Die Übernahme fügt niemanden hinzu.', 'ps-pen-paper' ) );
	}
	if ( ! is_string( $mode ) || ! isset( ps_pen_paper_character_context_modes()[ $mode ] ) || 'confirmed' !== $confirmed ) {
		return new WP_Error( 'confirmation_required', __( 'Bitte wähle gemeinsamen Fortschritt oder eine unabhängige Kopie und bestätige die Übernahme.', 'ps-pen-paper' ) );
	}
	$snapshot = ps_pen_paper_context_snapshot( $base['active'], $rules );
	if ( is_wp_error( $snapshot ) ) { return $snapshot; }
	$before = array( 'revision' => 0, 'active' => null, 'pending' => null );
	$after = array( 'revision' => 1, 'active' => $snapshot, 'pending' => null, 'context' => array(
		'universe_id' => $universe->ID, 'source_id' => $source->ID, 'source_revision' => $base['revision'],
		'mode' => $mode, 'overrides' => array(), 'note' => '',
	) );
	$id = wp_insert_post( array( 'post_type' => 'pspp_character', 'post_status' => 'draft', 'post_parent' => $campaign_id, 'post_author' => $source->post_author, 'post_title' => 'PS Pen & Paper Character' ), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	if ( ! $id ) {
		return new WP_Error( 'save_failed', __( 'Der Kampagnenbogen konnte nicht erstellt werden.', 'ps-pen-paper' ) );
	}
	$result = ps_pen_paper_atomic_event( (int) $id, function () use ( $campaign_id, $campaign, $id, $source, $before, $after, $rules ) {
		$event = ps_pen_paper_write_document_event( $campaign_id, $id, '_pspp_character', $before, $after, array(
			'kind' => 'character_created', 'visibility' => 'owner', 'recipient_id' => (int) $source->post_author,
			'rules_revision' => $rules['revision'], 'fields' => $rules['fields'],
		), true );
		if ( is_wp_error( $event ) ) { return $event; }
		$portrait_event = ps_pen_paper_write_base_portrait( $campaign, (int) $id, (int) $source->ID );
		return is_wp_error( $portrait_event ) ? $portrait_event : ( $portrait_event ?: $event );
	} );
	if ( is_wp_error( $result ) ) {
		return 'event_recovery_required' === $result->get_error_code() ? $result : ps_pen_paper_discard_record( $id, $result );
	}
	return $id;
}

function ps_pen_paper_save_character_context( $campaign_id, $id, $revision, $base_revision, $rules_revision, $overrides, $note, $resources = null ) {
	$write = function () use ( $campaign_id, $id, $revision, $base_revision, $rules_revision, $overrides, $note, $resources ) {
		return ps_pen_paper_save_character_context_locked( $campaign_id, $id, $revision, $base_revision, $rules_revision, $overrides, $note, $resources );
	};
	$state = ps_pen_paper_raw_character_state( $id );
	if ( ! is_wp_error( $state ) && isset( $state['context']['mode'] ) && 'shared' === $state['context']['mode'] ) {
		return ps_pen_paper_with_character_context_locks( $campaign_id, $id, $state['context']['source_id'], $write );
	}
	return ps_pen_paper_with_character_lock( $id, $write );
}

function ps_pen_paper_save_character_context_locked( $campaign_id, $id, $revision, $base_revision, $rules_revision, $overrides, $note, $resources = null ) {
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	$character = $campaign ? ps_pen_paper_get_character( $campaign, $id ) : null;
	if ( ! ps_pen_paper_can_access_site() || ! $character || ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die Spielleitung darf Kampagnenauflagen freigeben.', 'ps-pen-paper' ) );
	}
	$effective = ps_pen_paper_character_state( $campaign, $character, false );
	if ( is_wp_error( $effective ) && 'resource_limit_conflict' !== $effective->get_error_code() ) {
		return $effective;
	}
	$before = ps_pen_paper_raw_character_state( $id );
	if ( is_wp_error( $before ) ) { return $before; }
	if ( is_wp_error( $effective ) ) {
		$effective = $before;
		if ( 'shared' === $before['context']['mode'] ) {
			$source = ps_pen_paper_raw_character_state( $before['context']['source_id'] );
			if ( is_wp_error( $source ) ) { return $source; }
			$effective['base_revision'] = $source['revision'];
		}
	}
	$rules = ps_pen_paper_get_rules( $campaign_id );
	if ( $before['revision'] !== $effective['revision'] || ! isset( $before['context'] ) || ! is_string( $revision ) || (string) $before['revision'] !== $revision ||
		! is_string( $rules_revision ) || (string) $rules['revision'] !== $rules_revision ||
		! is_string( $base_revision ) || ( isset( $effective['base_revision'] ) ? (string) $effective['base_revision'] : '' ) !== $base_revision ) {
		return new WP_Error( 'conflict', __( 'Bogen, Basisstand oder Regeln wurden inzwischen geändert. Bitte lade die Kampagnenauflagen neu.', 'ps-pen-paper' ) );
	}
	if ( ! is_array( $overrides ) || count( $overrides ) > 30 || ! is_string( $note ) || strlen( $note ) > 2000 ) {
		return new WP_Error( 'invalid_context', __( 'Bitte prüfe die Auflagen und die Notiz mit höchstens 2000 Bytes.', 'ps-pen-paper' ) );
	}
	$context = $before['context'];
	$context['overrides'] = array();
	$context['note'] = sanitize_textarea_field( $note );
	$fields = array_column( array_filter( $rules['fields'], function ( $field ) { return in_array( $field['type'], array( 'number', 'attribute' ), true ); } ), null, 'id' );
	foreach ( $overrides as $field => $value ) {
		if ( ! isset( $fields[ $field ] ) || ! is_string( $value ) || ( '' !== $value && ! preg_match( '/^-?\d{1,7}$/D', $value ) ) ) {
			return new WP_Error( 'invalid_context', __( 'Auflagen müssen ganze Zahlen sein; ein leeres Feld übernimmt den Grundwert.', 'ps-pen-paper' ) );
		}
		if ( '' !== $value ) {
			$context['overrides'][ $field ] = (int) $value;
		}
	}
	if ( null !== $resources ) {
		if ( ! is_array( $resources ) || count( $resources ) > 30 ) { return new WP_Error( 'invalid_context', __( 'Die Ressourcenauflagen wurden nicht vollständig übertragen.', 'ps-pen-paper' ) ); }
		$context['resources'] = array();
		foreach ( $resources as $field => $resource ) {
			if ( ! is_array( $resource ) || ! isset( $resource['mode'] ) || ! in_array( $resource['mode'], array( 'inherit', 'local', 'shared' ), true ) ) {
				return new WP_Error( 'invalid_context', __( 'Bitte wähle das Ressourcenmodell.', 'ps-pen-paper' ) );
			}
			if ( 'inherit' === $resource['mode'] ) { continue; }
			$entry = array( 'mode' => $resource['mode'] );
			foreach ( 'local' === $resource['mode'] ? array( 'current', 'maximum' ) : array( 'maximum' ) as $key ) {
				if ( ! isset( $resource[ $key ] ) || ! is_string( $resource[ $key ] ) || ! preg_match( '/^-?\d{1,7}$/D', $resource[ $key ] ) ) {
					return new WP_Error( 'invalid_context', __( 'Ressourcenauflagen benötigen ganze Zahlen.', 'ps-pen-paper' ) );
				}
				$entry[ $key ] = (int) $resource[ $key ];
			}
			$context['resources'][ $field ] = $entry;
		}
	}
	$validated = ps_pen_paper_validate_context( $context, $rules );
	if ( is_wp_error( $validated ) ) {
		return $validated;
	}
	$after = $before;
	$after['revision']++;
	$after['context'] = $context;
	$source = $before['active'];
	if ( isset( $effective['base_revision'] ) ) {
		$source = ps_pen_paper_raw_character_state( $context['source_id'] );
		if ( is_wp_error( $source ) || $source['revision'] !== $effective['base_revision'] ) {
			return new WP_Error( 'conflict', __( 'Der Basisbogen wurde während der Bearbeitung geändert. Bitte lade die Seite neu.', 'ps-pen-paper' ) );
		}
		$after['active'] = $source['active'];
		$after['context']['source_revision'] = $source['revision'];
		$source = $source['active'];
	}
	$source = ps_pen_paper_apply_context_values( $source, $context );
	if ( is_wp_error( $source ) ) { return $source; }
	if ( ps_pen_paper_community_available() ) {
		$gear = ps_pen_paper_gear_state( $id );
		if ( is_wp_error( $gear ) ) { return $gear; }
		$gear_check = ps_pen_paper_gear_bonus( $campaign, $gear['active'], $source );
		if ( is_wp_error( $gear_check ) ) { return $gear_check; }
	}
	return ps_pen_paper_save_document_event( $campaign_id, $id, '_pspp_character', $before, $after, array(
		'kind' => 'character_context', 'visibility' => 'owner', 'recipient_id' => (int) $character->post_author,
		'rules_revision' => $rules['revision'], 'fields' => $rules['fields'],
		'effective_before' => $effective['active'], 'effective_after' => $source,
	) );
}

function ps_pen_paper_preview_character_copy( $campaign_id, $id ) {
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	$character = $campaign ? ps_pen_paper_get_character( $campaign, $id ) : null;
	if ( ! ps_pen_paper_can_access_site() || ! $character || ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die Kampagnenleitung darf einen gemeinsamen Basisbogen unabhängig weiterführen.', 'ps-pen-paper' ) );
	}
	$before = ps_pen_paper_raw_character_state( $id );
	if ( is_wp_error( $before ) ) { return $before; }
	if ( ! empty( $before['context']['gear_shared'] ) ) {
		return new WP_Error( 'invalid_context', __( 'Diese Rundenfigur verwendet das Inventar ihres Basisbogens. Verwende für einen unabhängigen Charakter einen eigenen Basisbogen.', 'ps-pen-paper' ) );
	}
	if ( ! isset( $before['context']['mode'] ) || 'shared' !== $before['context']['mode'] || null !== $before['pending'] ) {
		return new WP_Error( 'invalid_context', __( 'Nur ein gemeinsamer Basisbogen ohne offenen Kampagnenantrag kann unabhängig weitergeführt werden.', 'ps-pen-paper' ) );
	}
	// Equipment bonuses remain derived values, not the new independent base.
	$effective = ps_pen_paper_character_state( $campaign, $character, false );
	if ( is_wp_error( $effective ) ) { return $effective; }
	$scope = ps_pen_paper_currency_scope( $campaign );
	if ( is_wp_error( $scope ) ) { return $scope; }
	$currency = ps_pen_paper_currency_document( $scope->ID );
	if ( is_wp_error( $currency ) ) { return $currency; }
	if ( $scope->ID !== $campaign->ID && 'universe' === $currency['wallet_mode'] ) {
		return new WP_Error( 'shared_wallet', __( 'Dieser Bogen nutzt ein gemeinsames Universumskonto. Ohne gesonderte Kontenmigration darf die Basisverbindung nicht gelöst werden; Guthaben wird weder kopiert noch übertragen.', 'ps-pen-paper' ) );
	}
	return array( 'before' => $before, 'effective' => $effective, 'rules' => ps_pen_paper_get_rules( $campaign_id ), 'owner_id' => (int) $character->post_author, 'currency' => $currency );
}

function ps_pen_paper_copy_shared_character( $campaign_id, $id, $revision, $base_revision, $rules_revision, $confirmation ) {
	if ( 'confirmed' !== $confirmation ) {
		return new WP_Error( 'confirmation_required', __( 'Bitte prüfe und bestätige die unabhängige Weiterführung des aktuellen Basisstands.', 'ps-pen-paper' ) );
	}
	return ps_pen_paper_scope_write( $campaign_id, function () use ( $campaign_id, $id, $revision, $base_revision, $rules_revision ) {
		$initial = ps_pen_paper_preview_character_copy( $campaign_id, $id );
		if ( is_wp_error( $initial ) ) { return $initial; }
		$locks = array_values( array_unique( array( (int) $campaign_id, (int) $id, $initial['before']['context']['source_id'], $initial['before']['context']['universe_id'] ) ) );
		sort( $locks, SORT_NUMERIC );
		return ps_pen_paper_with_character_locks( $locks, function () use ( $campaign_id, $id, $revision, $base_revision, $rules_revision, $initial, $locks ) {
			return ps_pen_paper_atomic_event( $id, function () use ( $campaign_id, $id, $revision, $base_revision, $rules_revision, $initial, $locks ) {
				global $wpdb;
				foreach ( $locks as $lock_id ) {
					if ( $lock_id !== (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $lock_id ) ) ) {
						return new WP_Error( 'conflict', __( 'Der Basis- oder Kampagnenstand ist nicht mehr verfügbar. Bitte lade neu.', 'ps-pen-paper' ) );
					}
					clean_post_cache( $lock_id );
				}
				$preview = ps_pen_paper_preview_character_copy( $campaign_id, $id );
				if ( is_wp_error( $preview ) ) { return $preview; }
				if ( $preview !== $initial || ! is_string( $revision ) || (string) $preview['before']['revision'] !== $revision ||
					! is_string( $base_revision ) || (string) $preview['effective']['base_revision'] !== $base_revision ||
					! is_string( $rules_revision ) || (string) $preview['rules']['revision'] !== $rules_revision ) {
					return new WP_Error( 'conflict', __( 'Bogen, Basisstand oder Regeln wurden inzwischen geändert. Bitte prüfe die Vorschau erneut.', 'ps-pen-paper' ) );
				}
				$after = $preview['before'];
				$after['revision']++;
				$after['active'] = $preview['effective']['active'];
				$after['context']['mode'] = 'copy';
				$after['context']['source_revision'] = $preview['effective']['base_revision'];
				$after['context']['overrides'] = array();
				unset( $after['context']['resources'] );
				return ps_pen_paper_write_document_event( $campaign_id, $id, '_pspp_character', $preview['before'], $after, array(
					'kind' => 'character_context', 'visibility' => 'owner', 'recipient_id' => $preview['owner_id'],
					'rules_revision' => $preview['rules']['revision'], 'fields' => $preview['rules']['fields'],
					'effective_before' => $preview['effective']['active'], 'effective_after' => $after['active'],
				), false );
			} );
		} );
	} );
}

function ps_pen_paper_preview_character_reattachment( $campaign_id, $id ) {
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	$character = $campaign ? ps_pen_paper_get_character( $campaign, $id ) : null;
	if ( ! ps_pen_paper_can_access_site() || ! $character || ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die Kampagnenleitung darf eine unabhängige Kopie wieder verbinden.', 'ps-pen-paper' ) );
	}
	$before = ps_pen_paper_raw_character_state( $id );
	if ( is_wp_error( $before ) ) { return $before; }
	if ( ! isset( $before['context']['mode'] ) || 'copy' !== $before['context']['mode'] || null !== $before['pending'] ) {
		return new WP_Error( 'invalid_context', __( 'Nur eine unabhängige Kampagnenkopie ohne offenen Antrag kann wieder verbunden werden.', 'ps-pen-paper' ) );
	}
	$context = $before['context'];
	$universe = ps_pen_paper_get_universe( $context['universe_id'] );
	$source = $universe ? ps_pen_paper_get_character( $universe, $context['source_id'] ) : null;
	$base = $source ? ps_pen_paper_raw_character_state( $source->ID ) : null;
	$rules = ps_pen_paper_get_rules( $campaign_id );
	if ( ! $source || is_wp_error( $base ) || ! $base['active'] || array_key_exists( 'context', $base ) || null !== $base['pending'] ||
		(int) $source->post_author !== (int) $character->post_author ||
		ps_pen_paper_character_schema( $rules ) !== ps_pen_paper_character_schema( ps_pen_paper_get_rules( $universe->ID ) ) ) {
		return new WP_Error( 'reattach_conflict', __( 'Der Basisbogen, sein Besitzer oder die Feldstruktur passt nicht mehr. Die Kopie bleibt unverändert.', 'ps-pen-paper' ) );
	}
	$currency_scope = ps_pen_paper_currency_scope( $campaign );
	if ( is_wp_error( $currency_scope ) ) { return $currency_scope; }
	$currency = ps_pen_paper_currency_document( $currency_scope->ID );
	if ( is_wp_error( $currency ) ) { return $currency; }
	if ( $currency_scope->ID !== $campaign->ID && 'universe' === $currency['wallet_mode'] ) {
		return new WP_Error( 'shared_wallet', __( 'Diese Kopie nutzt ein gemeinsames Universumskonto. Eine Rückverbindung darf das Konto weder wechseln noch doppelt verfügbar machen; kläre das Konto zuerst ausdrücklich.', 'ps-pen-paper' ) );
	}
	$effective = ps_pen_paper_character_state( $campaign, $character, false );
	if ( is_wp_error( $effective ) ) { return $effective; }
	$base_snapshot = ps_pen_paper_context_snapshot( $base['active'], $rules );
	if ( is_wp_error( $base_snapshot ) ) { return $base_snapshot; }
	$conflicts = array();
	if ( $effective['active']['name'] !== $base_snapshot['name'] ) {
		$conflicts[] = array( 'field_id' => 'name', 'label' => __( 'Charaktername', 'ps-pen-paper' ),
			'copy_value' => $effective['active']['name'], 'base_value' => $base_snapshot['name'], 'copy_override_allowed' => false );
	}
	foreach ( $rules['fields'] as $field ) {
		if ( $effective['active']['values'][ $field['id'] ] !== $base_snapshot['values'][ $field['id'] ] ) {
			$conflicts[] = array(
				'field_id' => $field['id'], 'label' => $field['label'],
				'copy_value' => $effective['active']['values'][ $field['id'] ],
				'base_value' => $base_snapshot['values'][ $field['id'] ],
				'copy_override_allowed' => in_array( $field['type'], array( 'number', 'attribute', 'resource' ), true ),
			);
		}
	}
	return array(
		'campaign_id' => (int) $campaign_id, 'character_id' => (int) $id, 'owner_id' => (int) $character->post_author,
		'before' => $before, 'effective' => $effective['active'], 'base' => $base['active'],
		'base_revision' => $base['revision'], 'source_id' => (int) $source->ID, 'universe_id' => (int) $universe->ID,
		'rules' => $rules, 'conflicts' => $conflicts,
	);
}

function ps_pen_paper_reattach_character( $campaign_id, $id, $revision, $base_revision, $rules_revision, $resolutions, $confirmation ) {
	if ( 'confirmed' !== $confirmation || ! is_array( $resolutions ) ) {
		return new WP_Error( 'confirmation_required', __( 'Prüfe die Zusammenführung von Basisbogen und Kampagnenkopie und bestätige sie ausdrücklich.', 'ps-pen-paper' ) );
	}
	$initial = ps_pen_paper_preview_character_reattachment( $campaign_id, $id );
	if ( is_wp_error( $initial ) ) { return $initial; }
	$known = array_column( $initial['conflicts'], 'field_id' );
	if ( array_diff( $known, array_keys( $resolutions ) ) || array_diff( array_keys( $resolutions ), $known ) ) {
		return new WP_Error( 'resolution_required', __( 'Löse jeden angezeigten Feldkonflikt ausdrücklich.', 'ps-pen-paper' ) );
	}
	foreach ( $initial['conflicts'] as $conflict ) {
		if ( ! in_array( $resolutions[ $conflict['field_id'] ], $conflict['copy_override_allowed'] ? array( 'base', 'copy' ) : array( 'base' ), true ) ) {
			return new WP_Error( 'invalid_resolution', __( 'Diese Feldänderung kann nicht als Kampagnenauflage übernommen werden. Wähle den Basiswert.', 'ps-pen-paper' ) );
		}
	}
	$locks = array( $initial['character_id'], $initial['source_id'] );
	sort( $locks, SORT_NUMERIC );
	$scope_locks = array( $initial['campaign_id'], $initial['universe_id'] );
	sort( $scope_locks, SORT_NUMERIC );
	return ps_pen_paper_with_character_locks( $locks, function () use ( $scope_locks, $initial, $campaign_id, $id, $revision, $base_revision, $rules_revision, $resolutions ) {
		$run = function ( $index ) use ( &$run, $scope_locks, $initial, $campaign_id, $id, $revision, $base_revision, $rules_revision, $resolutions ) {
			if ( isset( $scope_locks[ $index ] ) ) {
				return ps_pen_paper_scope_lock( $scope_locks[ $index ], function () use ( &$run, $index ) { return $run( $index + 1 ); } );
			}
			return ps_pen_paper_atomic_event( $campaign_id, function () use ( $initial, $campaign_id, $id, $revision, $base_revision, $rules_revision, $resolutions ) {
				global $wpdb;
				foreach ( array( $id, $initial['source_id'] ) as $locked_id ) {
					if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $locked_id ) ) !== (int) $locked_id ) {
						return new WP_Error( 'conflict', __( 'Kampagnenkopie oder Basisbogen wurde inzwischen geändert. Prüfe eine neue Vorschau.', 'ps-pen-paper' ) );
					}
					clean_post_cache( $locked_id );
				}
				$current = ps_pen_paper_preview_character_reattachment( $campaign_id, $id );
				if ( is_wp_error( $current ) || $current['before'] !== $initial['before'] || $current['base_revision'] !== $initial['base_revision'] ||
					! is_string( $revision ) || (string) $current['before']['revision'] !== $revision ||
					! is_string( $base_revision ) || (string) $current['base_revision'] !== $base_revision ||
					! is_string( $rules_revision ) || (string) $current['rules']['revision'] !== $rules_revision ||
					$current['conflicts'] !== $initial['conflicts'] ) {
					return new WP_Error( 'conflict', __( 'Kampagnenkopie, Basisbogen oder Regeln wurden geändert. Prüfe eine neue Vorschau.', 'ps-pen-paper' ) );
				}
				$context = $current['before']['context'];
				$context['mode'] = 'shared';
				$context['source_revision'] = $current['base_revision'];
				$context['overrides'] = array();
				foreach ( $current['conflicts'] as $conflict ) {
					if ( 'copy' === $resolutions[ $conflict['field_id'] ] ) {
						if ( is_array( $conflict['copy_value'] ) ) {
							$context['resources'][ $conflict['field_id'] ] = array_merge( array( 'mode' => 'local' ), $conflict['copy_value'] );
						} else { $context['overrides'][ $conflict['field_id'] ] = $conflict['copy_value']; }
					} else {
						unset( $context['resources'][ $conflict['field_id'] ] );
					}
				}
				$effective = ps_pen_paper_apply_context_values( $current['base'], $context );
				if ( is_wp_error( $effective ) ) { return $effective; }
				$campaign = ps_pen_paper_get_campaign( $campaign_id );
				$character = ps_pen_paper_get_character( $campaign, $id );
				$gear_check = ps_pen_paper_check_character_gear( $campaign, $character, $effective, true );
				if ( is_wp_error( $gear_check ) ) { return $gear_check; }
				$after = $current['before'];
				$after['revision']++;
				$after['active'] = $current['base'];
				$after['context'] = $context;
				return ps_pen_paper_write_document_event( $campaign_id, $id, '_pspp_character', $current['before'], $after, array(
					'kind' => 'character_context', 'visibility' => 'owner', 'recipient_id' => $current['owner_id'],
					'rules_revision' => $current['rules']['revision'], 'fields' => $current['rules']['fields'],
					'effective_before' => $current['effective'], 'effective_after' => $effective,
				), false );
			} );
		};
		return $run( 0 );
	} );
}

function ps_pen_paper_has_shared_characters( $campaign_id ) {
	$characters = get_posts( array( 'post_type' => 'pspp_character', 'post_status' => array( 'private', 'draft', 'trash' ), 'post_parent' => $campaign_id, 'posts_per_page' => -1 ) );
	foreach ( $characters as $character ) {
		$state = get_post_meta( $character->ID, '_pspp_character', true );
		if ( is_array( $state ) && array_key_exists( 'context', $state ) && ( ! is_array( $state['context'] ) || ! isset( $state['context']['mode'] ) || 'copy' !== $state['context']['mode'] ) ) {
			return true;
		}

	}
	return false;
}

function ps_pen_paper_has_shared_base_references( $universe_id ) {
	foreach ( get_posts( array( 'post_type' => 'pspp_character', 'post_status' => array( 'private', 'draft', 'trash' ), 'posts_per_page' => -1 ) ) as $character ) {
		$state = get_post_meta( $character->ID, '_pspp_character', true );
		if ( is_array( $state ) && isset( $state['context']['universe_id'], $state['context']['mode'] ) &&
			(int) $state['context']['universe_id'] === (int) $universe_id && 'shared' === $state['context']['mode'] &&
			( ! empty( $state['active'] ) || ! empty( $state['pending'] ) ) ) { return true; }
	}
	return false;
}

function ps_pen_paper_request_universe_character( $campaign_id, $universe_id, $input, $rules_revision ) {
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	$link = $campaign ? ps_pen_paper_get_universe_link( $campaign_id ) : null;
	$owner_id = get_current_user_id();
	if ( ! ps_pen_paper_can_access_site() || ! $campaign || ! is_int( $universe_id ) || $universe_id < 1 ||
		is_wp_error( $link ) || $link['universe_id'] !== $universe_id || ! ps_pen_paper_campaign_role( $campaign, $owner_id ) ||
		! is_user_member_of_blog( $owner_id ) || ! current_user_can( 'read' ) || ! ps_pen_paper_get_universe( $universe_id ) ) {
		return new WP_Error( 'forbidden', __( 'Nur ein Mitglied einer zugeordneten Kampagne kann einen eigenen Universumsbogen beantragen.', 'ps-pen-paper' ) );
	}
	$scope_ids = array( (int) $campaign_id, $universe_id );
	sort( $scope_ids, SORT_NUMERIC );
	$created_id = 0;
	$lock_scope = function ( $index ) use ( &$lock_scope, $scope_ids, $campaign_id, $universe_id, $owner_id, $input, $rules_revision, &$created_id ) {
		if ( isset( $scope_ids[ $index ] ) ) {
			return ps_pen_paper_scope_lock( $scope_ids[ $index ], function () use ( &$lock_scope, $index, $scope_ids ) {
				if ( ! ps_pen_paper_scope_observe( $scope_ids[ $index ] ) ) {
					return new WP_Error( 'scope_changed', __( 'Die Kampagne oder das Universum wurde wiederhergestellt. Bitte lade das Antragsformular neu.', 'ps-pen-paper' ) );
				}
				return $lock_scope( $index + 1 );
			} );
		}
		return ps_pen_paper_atomic_event( $universe_id, function () use ( $campaign_id, $universe_id, $owner_id, $input, $rules_revision, &$created_id ) {
			$fresh_campaign = ps_pen_paper_get_campaign( $campaign_id );
			$fresh_link = $fresh_campaign ? ps_pen_paper_get_universe_link( $campaign_id ) : null;
			if ( ! $fresh_campaign || is_wp_error( $fresh_link ) || $fresh_link['universe_id'] !== $universe_id ||
				! ps_pen_paper_campaign_role( $fresh_campaign, $owner_id ) || ! ps_pen_paper_get_universe( $universe_id ) ||
				! ps_pen_paper_can_access_site() ) {
				return new WP_Error( 'forbidden', __( 'Die Kampagnenaufnahme oder Universumszuordnung ist nicht mehr gültig. Es wurde kein Antrag gespeichert.', 'ps-pen-paper' ) );
			}
			$rules = ps_pen_paper_get_rules( $universe_id );
			$limit = ps_pen_paper_check_character_creation_limit( $universe_id, $owner_id );
			if ( is_wp_error( $limit ) ) { return $limit; }
			if ( ! is_string( $rules_revision ) || 0 === $rules['revision'] || (string) $rules['revision'] !== $rules_revision ) {
				return new WP_Error( 'rules_conflict', __( 'Die Universumsvorlage fehlt oder wurde geändert. Bitte lade den Antrag neu.', 'ps-pen-paper' ) );
			}
			$snapshot = ps_pen_paper_validate_character_creation( $input, $rules );
			if ( is_wp_error( $snapshot ) ) { return $snapshot; }
			$id = wp_insert_post( array(
				'post_type' => 'pspp_character', 'post_status' => 'draft', 'post_parent' => $universe_id,
				'post_author' => $owner_id, 'post_title' => 'PS Pen & Paper Character',
			), true );
			if ( is_wp_error( $id ) ) { return $id; }
			if ( ! $id ) { return new WP_Error( 'save_failed', __( 'Der Universumsantrag konnte nicht angelegt werden.', 'ps-pen-paper' ) ); }
			$created_id = (int) $id;
			return ps_pen_paper_write_document_event( $universe_id, $created_id, '_pspp_character',
				array( 'revision' => 0, 'active' => null, 'pending' => null ),
				array( 'revision' => 1, 'active' => null, 'pending' => $snapshot ),
				array(
					'kind' => 'character_requested', 'visibility' => 'owner', 'recipient_id' => $owner_id,
					'rules_revision' => $rules['revision'], 'fields' => $rules['fields'],
				), true );
		} );
	};
	$result = $lock_scope( 0 );
	if ( is_wp_error( $result ) ) { return $result; }
	return true === $result && $created_id > 0 ? $created_id :
		new WP_Error( 'save_failed', __( 'Der Universumsantrag konnte nicht bestätigt gespeichert werden.', 'ps-pen-paper' ) );
}
