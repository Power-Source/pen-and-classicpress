<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/universe-members.php';

function ps_pen_paper_character_policy_defaults() {
	return array( 'revision' => 0, 'max_characters' => 1, 'creation_access' => 'site', 'custom_portraits' => false, 'member_invites' => 'approval', 'presets' => array() );
}

function ps_pen_paper_valid_character_policy( $state ) {
	if ( ! is_array( $state ) || array_keys( ps_pen_paper_character_policy_defaults() ) !== array_keys( $state ) ||
		! is_int( $state['revision'] ) || $state['revision'] < 0 || ! is_int( $state['max_characters'] ) ||
		$state['max_characters'] < 1 || $state['max_characters'] > 100 ||
		! in_array( $state['creation_access'], array( 'members', 'site' ), true ) || ! is_bool( $state['custom_portraits'] ) ||
		! in_array( $state['member_invites'], array( 'approval', 'direct' ), true ) ||
		! is_array( $state['presets'] ) || count( $state['presets'] ) > 100 ) { return false; }
	foreach ( $state['presets'] as $preset ) {
		if ( ! is_array( $preset ) || array( 'asset_id', 'name' ) !== array_keys( $preset ) || ! is_int( $preset['asset_id'] ) ||
			$preset['asset_id'] < 1 || ! is_string( $preset['name'] ) || '' === $preset['name'] || strlen( $preset['name'] ) > 200 ) { return false; }
	}
	return array_values( $state['presets'] ) === $state['presets'] &&
		count( array_unique( array_column( $state['presets'], 'asset_id' ) ) ) === count( $state['presets'] );
}

function ps_pen_paper_character_policy( $universe_id ) {
	$state = get_post_meta( $universe_id, '_pspp_character_policy', true );
	if ( '' === $state ) { return ps_pen_paper_character_policy_defaults(); }
	return ps_pen_paper_valid_character_policy( $state ) && $state['revision'] > 0 ? $state :
		new WP_Error( 'invalid_character_policy', __( 'Die Charaktervorgaben sind beschädigt. Bitte informiere die Universumsleitung.', 'ps-pen-paper' ) );
}

function ps_pen_paper_valid_policy_event( $event ) {
	$validators = array( 'character_policy_saved' => 'ps_pen_paper_valid_character_policy',
		'member_invitation_saved' => 'ps_pen_paper_valid_member_invitations', 'universe_members_saved' => 'ps_pen_paper_valid_invitations' );
	if ( ! is_array( $event ) || ! isset( $event['kind'], $event['visibility'], $event['recipient_id'], $event['before'], $event['after'] ) ||
		! isset( $validators[ $event['kind'] ] ) || 'gm' !== $event['visibility'] || 0 !== $event['recipient_id'] ) { return false; }
	$validate = $validators[ $event['kind'] ];
	return $validate( $event['before'] ) && $validate( $event['after'] ) &&
		$event['after']['revision'] === $event['before']['revision'] + 1;
}

function ps_pen_paper_save_character_policy( $universe_id, $input, $revision ) {
	$universe = ps_pen_paper_get_universe( $universe_id );
	if ( ! ps_pen_paper_can_manage_universe( $universe ) ) { return new WP_Error( 'forbidden', __( 'Nur die Universumsleitung darf die Charaktervorgaben ändern.', 'ps-pen-paper' ) ); }
	$before = ps_pen_paper_character_policy( $universe_id );
	if ( is_wp_error( $before ) ) { return $before; }
	if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision ) { return new WP_Error( 'conflict', __( 'Die Charaktervorgaben wurden geändert. Bitte lade das Formular neu.', 'ps-pen-paper' ) ); }
	if ( ! is_array( $input ) || array_diff( array_keys( $input ), array( 'max_characters', 'creation_access', 'custom_portraits', 'member_invites', 'presets' ) ) ||
		! isset( $input['max_characters'], $input['creation_access'], $input['custom_portraits'], $input['member_invites'] ) ||
		! is_string( $input['max_characters'] ) || ! preg_match( '/^[1-9]\d{0,2}$/D', $input['max_characters'] ) ||
		! in_array( $input['custom_portraits'], array( 'allowed', 'disabled' ), true ) ) {
		return new WP_Error( 'invalid_character_policy', __( 'Bitte prüfe die Charaktervorgaben.', 'ps-pen-paper' ) );
	}
	$after = array( 'revision' => $before['revision'] + 1, 'max_characters' => (int) $input['max_characters'],
		'creation_access' => $input['creation_access'], 'custom_portraits' => 'allowed' === $input['custom_portraits'],
		'member_invites' => $input['member_invites'], 'presets' => array() );
	if ( isset( $input['presets'] ) && ! is_array( $input['presets'] ) ) { return new WP_Error( 'invalid_character_policy', __( 'Die Porträtauswahl ist ungültig.', 'ps-pen-paper' ) ); }
	foreach ( $input['presets'] ?? array() as $preset ) {
		if ( ! is_array( $preset ) || ! isset( $preset['asset_id'], $preset['name'] ) || ! is_string( $preset['asset_id'] ) ||
			! preg_match( '/^[1-9]\d*$/D', $preset['asset_id'] ) || ! is_string( $preset['name'] ) ) {
			return new WP_Error( 'invalid_character_policy', __( 'Die Porträtauswahl ist ungültig.', 'ps-pen-paper' ) );
		}
		if ( array_diff( array_keys( $preset ), array( 'asset_id', 'name', 'remove' ) ) ||
			( isset( $preset['remove'] ) && 'confirmed' !== $preset['remove'] ) ) { return new WP_Error( 'invalid_character_policy', __( 'Die Porträtbestätigung ist ungültig.', 'ps-pen-paper' ) ); }
		if ( isset( $preset['remove'] ) ) {
			if ( ! in_array( (int) $preset['asset_id'], array_column( $before['presets'], 'asset_id' ), true ) ) { return new WP_Error( 'conflict', __( 'Das zu entfernende Porträt ist nicht mehr in der Auswahl.', 'ps-pen-paper' ) ); }
			continue;
		}
		$after['presets'][] = array( 'asset_id' => (int) $preset['asset_id'], 'name' => sanitize_text_field( $preset['name'] ) );
	}

	if ( ! ps_pen_paper_valid_character_policy( $after ) ) { return new WP_Error( 'invalid_character_policy', __( 'Erlaubt sind 1–100 Charaktere und höchstens 100 benannte Porträts.', 'ps-pen-paper' ) ); }
	return ps_pen_paper_atomic_event( $universe_id, function () use ( $universe_id, $universe, $before, $after ) {
		if ( ! ps_pen_paper_can_manage_universe( ps_pen_paper_get_universe( $universe_id ) ) || ps_pen_paper_character_policy( $universe_id ) !== $before ) {
			return new WP_Error( 'conflict', __( 'Die Vorgaben wurden geändert. Bitte lade das Formular neu.', 'ps-pen-paper' ) );
		}
		foreach ( $after['presets'] as $preset ) {
			$image = ps_pen_paper_private_image_data( $universe, $preset['asset_id'] );
			if ( is_wp_error( $image ) ) { return $image; }
			$asset = ps_pen_paper_record_post( $preset['asset_id'] );
			if ( ! ps_pen_paper_valid_private_asset_content( $asset->post_mime_type, $asset->post_content ) ) { return new WP_Error( 'invalid_image', __( 'Ein Porträtbild ist beschädigt.', 'ps-pen-paper' ) ); }
		}
		return ps_pen_paper_write_document_event( $universe_id, $universe_id, '_pspp_character_policy', $before, $after,
			array( 'kind' => 'character_policy_saved', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
	} );
}

function ps_pen_paper_is_universe_member( $universe, $user_id = null ) {
	$user_id = null === $user_id ? get_current_user_id() : $user_id;
	$user = ps_pen_paper_site_user( $user_id );
	if ( ! $universe || ! $user || ! user_can( $user, 'read' ) ) { return false; }
	if ( (int) $universe->post_author === $user_id || ( $user_id === get_current_user_id() && ps_pen_paper_can_manage_universe( $universe ) ) ) { return true; }
	$members = ps_pen_paper_universe_members( $universe->ID );
	if ( ! is_wp_error( $members ) && in_array( $user_id, $members['users'], true ) ) { return true; }
	foreach ( get_posts( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'posts_per_page' => -1 ) ) as $campaign ) {
		$link = ps_pen_paper_get_universe_link( $campaign->ID );
		if ( ! is_wp_error( $link ) && $link['universe_id'] === (int) $universe->ID && ps_pen_paper_campaign_role( $campaign, $user_id ) ) { return true; }
	}
	return false;
}

function ps_pen_paper_can_use_universe_characters( $universe ) {
	if ( ! ps_pen_paper_can_access_site() || ! $universe ) { return false; }
	if ( ps_pen_paper_can_manage_universe( $universe ) || ps_pen_paper_is_universe_member( $universe ) ) { return true; }
	// An owner may still read their preserved sheets after membership changes.
	if ( get_posts( array( 'post_type' => 'pspp_character', 'post_status' => 'private', 'post_parent' => $universe->ID,
		'author' => get_current_user_id(), 'posts_per_page' => 1, 'fields' => 'ids' ) ) ) { return true; }
	$policy = ps_pen_paper_character_policy( $universe->ID );
	return ! is_wp_error( $policy ) && 'site' === $policy['creation_access'];
}

function ps_pen_paper_universe_character_count( $universe_id, $owner_id ) {
	$scopes = array( $universe_id );
	foreach ( get_posts( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'posts_per_page' => -1 ) ) as $campaign ) {
		$link = ps_pen_paper_get_universe_link( $campaign->ID );
		if ( ! is_wp_error( $link ) && $link['universe_id'] === $universe_id ) { $scopes[] = (int) $campaign->ID; }
	}
	$identities = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_character', 'post_status' => array( 'private', 'draft' ), 'post_parent__in' => $scopes,
		'author' => $owner_id, 'posts_per_page' => -1 ) ) as $character ) {
		$state = get_post_meta( $character->ID, '_pspp_character', true );
		$source = is_array( $state ) && isset( $state['context']['source_id'], $state['context']['universe_id'] ) &&
			$state['context']['universe_id'] === $universe_id ? $state['context']['source_id'] : (int) $character->ID;
		$identities[ $source ] = true;
	}
	return count( $identities );
}

function ps_pen_paper_check_character_creation_limit( $universe_id, $owner_id ) {
	$policy = ps_pen_paper_character_policy( $universe_id );
	if ( is_wp_error( $policy ) ) { return $policy; }
	return ps_pen_paper_universe_character_count( $universe_id, $owner_id ) < $policy['max_characters'] ? true :
		new WP_Error( 'character_limit', __( 'Die maximale Charakterzahl in diesem Universum ist erreicht. Offene Erstfreigaben zählen mit; bestehende Bögen bleiben unverändert.', 'ps-pen-paper' ) );
}

function ps_pen_paper_profile_universe_choices() {
	if ( ! ps_pen_paper_can_access_site() ) { return array(); }
	$choices = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_universe', 'post_status' => 'private', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $universe ) {
		if ( ! ps_pen_paper_can_use_universe_characters( $universe ) ) { continue; }
		$document = ps_pen_paper_universe_document( $universe->ID );
		$policy = ps_pen_paper_character_policy( $universe->ID );
		$reason = is_wp_error( $document ) ? $document->get_error_message() : ( is_wp_error( $policy ) ? $policy->get_error_message() : '' );
		$count = ps_pen_paper_universe_character_count( (int) $universe->ID, get_current_user_id() );
		$limit = is_wp_error( $policy ) ? 0 : $policy['max_characters'];
		if ( ! $reason && ! ps_pen_paper_is_universe_member( $universe ) && 'site' !== $policy['creation_access'] ) { $reason = __( 'Neue Charaktere benötigen eine Universumsmitgliedschaft.', 'ps-pen-paper' ); }
		if ( ! $reason && $count >= $limit ) { $reason = __( 'Maximale Charakterzahl erreicht (offene Freigaben zählen mit).', 'ps-pen-paper' ); }
		if ( ! $reason && ! ps_pen_paper_get_rules( $universe->ID )['revision'] ) { $reason = __( 'Die Universumsleitung muss zuerst die Charaktervorlage einrichten.', 'ps-pen-paper' ); }
		/* translators: %d: universe identifier. */
		$choices[] = array( 'id' => (int) $universe->ID, 'name' => is_wp_error( $document ) ? sprintf( __( 'Universum #%d – Prüfung nötig', 'ps-pen-paper' ), $universe->ID ) : $document['name'], 'count' => $count, 'limit' => $limit,
			'member' => ps_pen_paper_is_universe_member( $universe ), 'can_create' => '' === $reason, 'reason' => $reason,
			'error' => is_wp_error( $document ) || is_wp_error( $policy ) );
	}
	return $choices;
}

function ps_pen_paper_create_own_universe_character( $universe_id, $input, $rules_revision ) {
	$created_id = 0;
	$result = ps_pen_paper_atomic_event( $universe_id, function () use ( $universe_id, $input, $rules_revision, &$created_id ) {
		$universe = ps_pen_paper_get_universe( $universe_id );
		$policy = $universe ? ps_pen_paper_character_policy( $universe_id ) : null;
		if ( ! ps_pen_paper_can_access_site() || ! $universe || is_wp_error( $policy ) ||
			( ! ps_pen_paper_is_universe_member( $universe ) && 'site' !== $policy['creation_access'] ) ) {
			return new WP_Error( 'forbidden', __( 'Du darfst in diesem Universum keinen neuen Charakter erstellen.', 'ps-pen-paper' ) );
		}
		$limit = ps_pen_paper_check_character_creation_limit( $universe_id, get_current_user_id() );
		if ( is_wp_error( $limit ) ) { return $limit; }
		$rules = ps_pen_paper_get_rules( $universe_id );
		if ( ! is_string( $rules_revision ) || ! $rules['revision'] || (string) $rules['revision'] !== $rules_revision ) {
			return new WP_Error( 'rules_conflict', __( 'Die Charaktervorlage fehlt oder wurde geändert. Bitte lade das Formular neu.', 'ps-pen-paper' ) );
		}
		$snapshot = ps_pen_paper_validate_character_creation( $input, $rules );
		if ( is_wp_error( $snapshot ) ) { return $snapshot; }
		$is_gm = ps_pen_paper_can_manage_universe( $universe );
		$id = wp_insert_post( array( 'post_type' => 'pspp_character', 'post_status' => 'draft', 'post_parent' => $universe_id,
			'post_author' => get_current_user_id(), 'post_title' => 'PS Pen & Paper Character' ), true );
		if ( is_wp_error( $id ) || ! $id ) { return is_wp_error( $id ) ? $id : new WP_Error( 'save_failed', __( 'Der Charakter konnte nicht erstellt werden.', 'ps-pen-paper' ) ); }
		$created_id = (int) $id;
		return ps_pen_paper_write_document_event( $universe_id, $created_id, '_pspp_character',
			array( 'revision' => 0, 'active' => null, 'pending' => null ),
			array( 'revision' => 1, 'active' => $is_gm ? $snapshot : null, 'pending' => $is_gm ? null : $snapshot ),
			array( 'kind' => $is_gm ? 'character_created' : 'character_requested', 'visibility' => 'owner', 'recipient_id' => get_current_user_id(),
				'fields' => $rules['fields'], 'rules_revision' => $rules['revision'] ), true );
	} );
	return is_wp_error( $result ) ? $result : $created_id;
}

// Trusted profile dispatchers must authenticate the surrounding signed request.
function ps_pen_paper_profile_character_request( $operation, $input ) {
	if ( ! is_array( $input ) || ! isset( $input['universe_id'] ) || ! is_int( $input['universe_id'] ) || $input['universe_id'] < 1 ) { return new WP_Error( 'invalid_request', __( 'Bitte wähle ein gültiges Universum.', 'ps-pen-paper' ) ); }
	$id = $input['universe_id'];
	if ( array_key_exists( 'universe_epoch', $input ) && ( ! is_string( $input['universe_epoch'] ) || ps_pen_paper_scope_epoch( $id ) !== $input['universe_epoch'] ) ) {
		return new WP_Error( 'scope_changed', __( 'Das Universum wurde wiederhergestellt. Bitte lade das Formular neu.', 'ps-pen-paper' ) );
	}
	if ( 'create' === $operation ) { return ps_pen_paper_create_own_universe_character( $id, $input['character'] ?? null, $input['rules_revision'] ?? null ); }
	if ( ! isset( $input['character_id'] ) || ! is_int( $input['character_id'] ) || $input['character_id'] < 1 ) { return new WP_Error( 'invalid_request', __( 'Bitte wähle einen gültigen Charakter.', 'ps-pen-paper' ) ); }
	if ( 'review' === $operation ) { return ps_pen_paper_review_character( $id, $input['character_id'] ?? 0, $input['decision'] ?? '', $input['revision'] ?? null ); }
	if ( 'update' === $operation ) { return ps_pen_paper_save_character( $id, $input['character_id'] ?? 0, get_current_user_id(), $input['character'] ?? null, $input['revision'] ?? null, $input['rules_revision'] ?? null, true ); }
	return new WP_Error( 'invalid_request', __( 'Diese Charakteraktion ist ungültig.', 'ps-pen-paper' ) );
}

function ps_pen_paper_profile_characters( $universe_id = 0 ) {
	if ( ! ps_pen_paper_can_access_site() || ! is_int( $universe_id ) || $universe_id < 0 ) { return array(); }
	$rows = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_character', 'post_status' => 'private', 'author' => get_current_user_id(),
		'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $character ) {
		$scope = ps_pen_paper_get_character_scope( (int) $character->post_parent );
		if ( ! $scope || ! ps_pen_paper_get_character( $scope, (int) $character->ID ) ) { continue; }
		$link = 'pspp_universe' === $scope->post_type ? array( 'universe_id' => (int) $scope->ID ) : ps_pen_paper_get_universe_link( $scope->ID );
		if ( $universe_id && ( is_wp_error( $link ) || $link['universe_id'] !== $universe_id ) ) { continue; }
		$rows[] = array( 'scope' => $scope, 'character' => $character, 'state' => ps_pen_paper_character_state( $scope, $character ),
			'universe_id' => is_wp_error( $link ) ? 0 : $link['universe_id'], 'legacy_unassigned' => is_wp_error( $link ) || ! $link['universe_id'] );
	}
	return $rows;
}

function ps_pen_paper_add_portrait_preset( $universe_id, $name, $path, $revision ) {
	return ps_pen_paper_scope_lock( $universe_id, function () use ( $universe_id, $name, $path, $revision ) {
		$universe = ps_pen_paper_get_universe( $universe_id );
		if ( ! ps_pen_paper_can_manage_universe( $universe ) || ! is_string( $name ) || '' === sanitize_text_field( $name ) || strlen( $name ) > 200 ) {
			return new WP_Error( 'forbidden', __( 'Nur die Universumsleitung darf benannte Porträts hinzufügen.', 'ps-pen-paper' ) );
		}
		$policy = ps_pen_paper_character_policy( $universe_id );
		if ( is_wp_error( $policy ) ) { return $policy; }
		if ( (string) $policy['revision'] !== $revision || count( $policy['presets'] ) >= 100 ) { return new WP_Error( 'conflict', __( 'Die Porträtauswahl wurde geändert oder ist voll.', 'ps-pen-paper' ) ); }
		$asset_id = ps_pen_paper_store_private_image( $universe_id, $path );
		if ( is_wp_error( $asset_id ) ) { return $asset_id; }
		$input = $policy; unset( $input['revision'] );
		$input['max_characters'] = (string) $input['max_characters'];
		$input['custom_portraits'] = $input['custom_portraits'] ? 'allowed' : 'disabled';
		$input['presets'][] = array( 'asset_id' => $asset_id, 'name' => $name );
		foreach ( $input['presets'] as &$preset ) { $preset['asset_id'] = (string) $preset['asset_id']; } unset( $preset );
		$result = ps_pen_paper_save_character_policy( $universe_id, $input, $revision );
		if ( is_wp_error( $result ) && 'event_recovery_required' !== $result->get_error_code() && ! wp_delete_post( $asset_id, true ) ) {
			return new WP_Error( 'cleanup_failed', __( 'Ein nicht zugeordnetes Porträt konnte nicht entfernt werden.', 'ps-pen-paper' ) );
		}
		return $result;
	} );
}

function ps_pen_paper_validate_campaign_universe( $universe_id ) {
	$universe = is_int( $universe_id ) && $universe_id > 0 ? ps_pen_paper_get_universe( $universe_id ) : null;
	if ( ! ps_pen_paper_can_create_campaign() || ! ps_pen_paper_can_manage_universe( $universe ) ) {
		return new WP_Error( 'universe_required', __( 'Jede neue Kampagne benötigt ein vorhandenes Universum, das du verwalten darfst. Lege bei Bedarf zuerst ein Universum an.', 'ps-pen-paper' ) );
	}
	return ps_pen_paper_universe_document( $universe_id );
}

function ps_pen_paper_link_new_campaign( $campaign_id, $universe_id, $universe_revision ) {
	$document = ps_pen_paper_validate_campaign_universe( $universe_id );
	if ( is_wp_error( $document ) ) { return $document; }
	$link = ps_pen_paper_get_universe_link( $campaign_id );
	if ( is_wp_error( $link ) ) { return $link; }
	if ( $link['revision'] || ps_pen_paper_has_characters( $campaign_id ) ) {
		return new WP_Error( 'legacy_confirmation_required', __( 'Bestehende Kampagnen müssen über die ausdrücklich bestätigte Zuordnung repariert werden.', 'ps-pen-paper' ) );
	}
	return ps_pen_paper_save_universe_link( $campaign_id, $universe_id, '0', $universe_revision, 'confirmed' );
}
