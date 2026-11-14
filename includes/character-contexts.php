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
	foreach ( $context['overrides'] as $id => $value ) {
		if ( ! isset( $fields[ $id ] ) || ! in_array( $fields[ $id ]['type'], array( 'number', 'attribute' ), true ) || ! is_int( $value ) ||
			$value < $fields[ $id ]['min'] || $value > $fields[ $id ]['max'] ) {
			return new WP_Error( 'invalid_context', __( 'Eine Kampagnenauflage passt nicht zum Regelwerk. Bitte informiere die Spielleitung.', 'ps-pen-paper' ) );
		}
	}
	return $context;
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
		$source = get_post( $context['source_id'] );
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
	if ( $state['active'] ) {
		foreach ( $context['overrides'] as $id => $value ) {
			$state['active']['values'][ $id ] = $value;
		}
	}
	return $with_equipment ? ps_pen_paper_apply_gear( $scope, $character, $state ) : $state;
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
	$result = ps_pen_paper_save_document_event( $campaign_id, $id, '_pspp_character', $before, $after, array(
		'kind' => 'character_created', 'visibility' => 'owner', 'recipient_id' => (int) $source->post_author,
		'rules_revision' => $rules['revision'], 'fields' => $rules['fields'],
	), true );
	if ( is_wp_error( $result ) ) {
		return 'event_recovery_required' === $result->get_error_code() ? $result : ps_pen_paper_discard_record( $id, $result );
	}
	return $id;
}

function ps_pen_paper_save_character_context( $campaign_id, $id, $revision, $base_revision, $rules_revision, $overrides, $note ) {
	return ps_pen_paper_with_character_lock( $id, function () use ( $campaign_id, $id, $revision, $base_revision, $rules_revision, $overrides, $note ) {
		return ps_pen_paper_save_character_context_locked( $campaign_id, $id, $revision, $base_revision, $rules_revision, $overrides, $note );
	} );
}

function ps_pen_paper_save_character_context_locked( $campaign_id, $id, $revision, $base_revision, $rules_revision, $overrides, $note ) {
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	$character = $campaign ? ps_pen_paper_get_character( $campaign, $id ) : null;
	if ( ! ps_pen_paper_can_access_site() || ! $character || ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die Spielleitung darf Kampagnenauflagen freigeben.', 'ps-pen-paper' ) );
	}
	$effective = ps_pen_paper_character_state( $campaign, $character, false );
	if ( is_wp_error( $effective ) ) {
		return $effective;
	}
	$before = ps_pen_paper_raw_character_state( $id );
	if ( is_wp_error( $before ) ) { return $before; }
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
	foreach ( $context['overrides'] as $field => $value ) {
		$source['values'][ $field ] = $value;
	}
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
