<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_character_modes() {
	return array(
		'approval' => __( 'Spieler bearbeiten eigene Charaktere mit Spielleiterfreigabe', 'ps-pen-paper' ),
		'direct'   => __( 'Spieler bearbeiten eigene Charaktere direkt', 'ps-pen-paper' ),
		'gm_only'  => __( 'Nur Spielleiter erstellen und bearbeiten Charaktere', 'ps-pen-paper' ),
	);
}

function ps_pen_paper_character_mode( $rules ) {
	return isset( $rules['character_mode'] ) ? $rules['character_mode'] : 'approval';
}

function ps_pen_paper_register_character_type() {
	register_post_type(
		'pspp_character',
		array(
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => false,
			'show_in_rest'        => false,
			'capability_type'     => array( 'pspp_character', 'pspp_characters' ),
			'map_meta_cap'        => true,
			'supports'            => array( 'author' ),
		)
	);
}

function ps_pen_paper_has_characters( $campaign_id ) {
	return (bool) get_posts( array(
		'post_type'      => 'pspp_character',
		'post_status'    => 'private',
		'post_parent'    => $campaign_id,
		'fields'         => 'ids',
		'posts_per_page' => 1,
	) );
}

function ps_pen_paper_character_schema( $rules ) {
	$schema = array();
	foreach ( $rules['fields'] as $field ) {
		$schema[ $field['id'] ] = array_intersect_key( $field, array_flip( array( 'type', 'required', 'min', 'max' ) ) );
	}
	ksort( $schema );
	return $schema;
}

function ps_pen_paper_get_character_scope( $id ) {
	return ps_pen_paper_get_campaign( $id ) ?: ps_pen_paper_get_universe( $id );
}

function ps_pen_paper_can_manage_character_scope( $scope ) {
	return 'pspp_universe' === $scope->post_type ? ps_pen_paper_can_manage_universe( $scope ) : ps_pen_paper_can_manage_campaign( $scope );
}

function ps_pen_paper_can_view_character_scope( $scope ) {
	return 'pspp_universe' === $scope->post_type ? ps_pen_paper_can_manage_universe( $scope ) : ps_pen_paper_can_view_campaign( $scope );
}

function ps_pen_paper_get_characters( $campaign ) {
	if ( ! current_user_can( 'read' ) || ! ps_pen_paper_can_view_character_scope( $campaign ) ) {
		return array();
	}
	$args = array(
		'post_type'      => 'pspp_character',
		'post_status'    => 'private',
		'post_parent'    => $campaign->ID,
		'posts_per_page' => -1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
	);
	if ( ! ps_pen_paper_can_manage_character_scope( $campaign ) ) {
		$args['author'] = get_current_user_id();
	}
	return get_posts( $args );
}

function ps_pen_paper_get_character( $campaign, $character_id ) {
	$character = get_post( $character_id );
	if ( ! current_user_can( 'read' ) || ! ps_pen_paper_can_view_character_scope( $campaign ) || ! $character || 'pspp_character' !== $character->post_type || 'private' !== $character->post_status || (int) $character->post_parent !== (int) $campaign->ID ) {
		return null;
	}
	if ( ! ps_pen_paper_can_manage_character_scope( $campaign ) && (int) $character->post_author !== get_current_user_id() ) {
		return null;
	}
	return $character;
}

function ps_pen_paper_can_edit_character( $campaign, $character = null ) {
	if ( ! current_user_can( 'read' ) || ! ps_pen_paper_can_view_character_scope( $campaign ) ) {
		return false;
	}
	if ( $character && ! ps_pen_paper_get_character( $campaign, $character->ID ) ) {
		return false;
	}
	if ( $character ) {
		$state = get_post_meta( $character->ID, '_pspp_character', true );
		if ( is_array( $state ) && array_key_exists( 'context', $state ) && ( ! is_array( $state['context'] ) || ! isset( $state['context']['mode'] ) || 'shared' === $state['context']['mode'] ) ) {
			return false;
		}
	}
	return ps_pen_paper_can_manage_character_scope( $campaign ) || 'gm_only' !== ps_pen_paper_character_mode( ps_pen_paper_get_rules( $campaign->ID ) );
}

function ps_pen_paper_character_values( $rules ) {
	$values = array();
	foreach ( $rules['fields'] as $field ) {
		if ( 'resource' === $field['type'] ) {
			$values[ $field['id'] ] = array( 'current' => $field['start'], 'maximum' => $field['max'] );
		} else {
			$values[ $field['id'] ] = isset( $field['start'] ) ? $field['start'] : '';
		}
	}
	return $values;
}

function ps_pen_paper_validate_character( $input, $rules ) {
	$error = new WP_Error( 'invalid_character', __( 'Bitte prüfe Name, Pflichtfelder und Wertebereiche des Charakters.', 'ps-pen-paper' ) );
	if ( ! is_array( $input ) || ! isset( $input['name'] ) || ! is_string( $input['name'] ) ) {
		return $error;
	}
	$name = sanitize_text_field( $input['name'] );
	$values = isset( $input['values'] ) ? $input['values'] : array();
	if ( '' === $name || strlen( $name ) > 200 || ! is_array( $values ) || array_diff( array_keys( $values ), array_column( $rules['fields'], 'id' ) ) ) {
		return $error;
	}
	$clean = array( 'name' => $name, 'values' => array() );
	foreach ( $rules['fields'] as $field ) {
		$value = isset( $values[ $field['id'] ] ) ? $values[ $field['id'] ] : '';
		if ( in_array( $field['type'], array( 'text', 'textarea' ), true ) ) {
			if ( ! is_string( $value ) || strlen( $value ) > ( 'text' === $field['type'] ? 200 : 20000 ) ) {
				return $error;
			}
			$value = 'text' === $field['type'] ? sanitize_text_field( $value ) : sanitize_textarea_field( $value );
			if ( $field['required'] && '' === $value ) {
				return $error;
			}
			$clean['values'][ $field['id'] ] = $value;
			continue;
		}
		$numbers = 'resource' === $field['type'] ? $value : array( 'value' => $value );
		$keys = 'resource' === $field['type'] ? array( 'current', 'maximum' ) : array( 'value' );
		if ( ! is_array( $numbers ) || count( $numbers ) !== count( $keys ) ) {
			return $error;
		}
		foreach ( $keys as $key ) {
			if ( ! isset( $numbers[ $key ] ) || ! is_string( $numbers[ $key ] ) || ! preg_match( '/^-?\d{1,7}$/D', $numbers[ $key ] ) || (int) $numbers[ $key ] < $field['min'] || (int) $numbers[ $key ] > $field['max'] ) {
				return $error;
			}
			$numbers[ $key ] = (int) $numbers[ $key ];
		}
		if ( 'resource' === $field['type'] && $numbers['current'] > $numbers['maximum'] ) {
			return $error;
		}
		$clean['values'][ $field['id'] ] = 'resource' === $field['type'] ? $numbers : $numbers['value'];
	}
	return $clean;
}

function ps_pen_paper_save_character( $campaign_id, $character_id, $owner_id, $input, $revision, $rules_revision ) {
	$campaign = ps_pen_paper_get_character_scope( $campaign_id );
	$character = $campaign && $character_id ? ps_pen_paper_get_character( $campaign, $character_id ) : null;
	if ( ! $campaign || ( $character_id && ! $character ) || ! ps_pen_paper_can_edit_character( $campaign, $character ) ) {
		return new WP_Error( 'forbidden', __( 'Du darfst diesen Charakter nicht bearbeiten.', 'ps-pen-paper' ) );
	}
	if ( $character ) {
		$current = ps_pen_paper_character_state( $campaign, $character );
		if ( is_wp_error( $current ) ) { return $current; }
	}
	$rules = ps_pen_paper_get_rules( $campaign_id );
	if ( 0 === $rules['revision'] || (string) $rules['revision'] !== $rules_revision ) {
		return new WP_Error( 'rules_conflict', __( 'Bitte lade den Charakterbogen neu. Das Regelwerk fehlt oder wurde inzwischen geändert.', 'ps-pen-paper' ) );
	}
	$previous = $character ? get_post_meta( $character_id, '_pspp_character', true ) : array( 'revision' => 0, 'active' => null, 'pending' => null );
	if ( ! is_string( $revision ) || (string) $previous['revision'] !== $revision ) {
		return new WP_Error( 'conflict', __( 'Der Charakter wurde inzwischen geändert. Lade die Seite neu.', 'ps-pen-paper' ) );
	}
	$owner_id = $character ? (int) $character->post_author : (int) $owner_id;
	$is_gm = ps_pen_paper_can_manage_character_scope( $campaign );
	$universe = 'pspp_universe' === $campaign->post_type;
	if ( ! get_userdata( $owner_id ) || ! is_user_member_of_blog( $owner_id ) || ( $universe ? ! user_can( $owner_id, 'read' ) : ! ps_pen_paper_campaign_role( $campaign, $owner_id ) ) || ( ! $is_gm && $owner_id !== get_current_user_id() ) ) {
		return new WP_Error( 'invalid_owner', $universe ? __( 'Der Basisbogen muss einem leseberechtigten Mitglied dieser Website gehören.', 'ps-pen-paper' ) : __( 'Der Charakter muss einem Mitglied dieser Kampagne gehören.', 'ps-pen-paper' ) );
	}
	$snapshot = ps_pen_paper_validate_character( $input, $rules );
	if ( is_wp_error( $snapshot ) ) {
		return $snapshot;
	}
	$state = $previous;
	$state['revision']++;
	if ( ! $is_gm && 'approval' === ps_pen_paper_character_mode( $rules ) ) {
		$state['pending'] = $snapshot;
	} else {
		$state['active'] = $snapshot;
		$state['pending'] = null;
	}
	if ( ! $character ) {
		$character_id = wp_insert_post( array(
			'post_type'   => 'pspp_character',
			'post_status' => 'draft',
			'post_parent' => $campaign_id,
			'post_author' => $owner_id,
			'post_title'  => 'PS Pen & Paper Character',
		), true );
		if ( is_wp_error( $character_id ) ) {
			return $character_id;
		}
		if ( ! $character_id ) {
			return new WP_Error( 'save_failed', __( 'Der Charakter konnte nicht erstellt werden.', 'ps-pen-paper' ) );
		}
	}
	$kind = ! $character ? 'character_created' : ( $state['pending'] ? 'character_requested' : 'character_saved' );
	$saved = ps_pen_paper_save_document_event( $campaign_id, $character_id, '_pspp_character', $previous, $state, array(
		'kind' => $kind, 'visibility' => 'owner', 'recipient_id' => $owner_id,
		'rules_revision' => $rules['revision'], 'fields' => $rules['fields'],
	), ! $character );
	if ( is_wp_error( $saved ) ) {
		if ( ! $character && ! metadata_exists( 'post', $character_id, '_pspp_character' ) && ! wp_delete_post( $character_id, true ) ) {
			return new WP_Error( 'cleanup_failed', __( 'Der Charakter ist unvollständig gespeichert und konnte nicht entfernt werden. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
		}
		return $saved;
	}
	return $character_id;
}

function ps_pen_paper_review_character( $campaign_id, $character_id, $decision, $revision ) {
	$campaign = ps_pen_paper_get_character_scope( $campaign_id );
	$character = $campaign ? ps_pen_paper_get_character( $campaign, $character_id ) : null;
	if ( ! $character || ! ps_pen_paper_can_manage_character_scope( $campaign ) || ! ps_pen_paper_can_edit_character( $campaign, $character ) ) {
		return new WP_Error( 'forbidden', __( 'Nur Spielleiter dürfen Charakteränderungen freigeben.', 'ps-pen-paper' ) );
	}
	$previous = get_post_meta( $character_id, '_pspp_character', true );
	if ( ! is_string( $revision ) || (string) $previous['revision'] !== $revision || null === $previous['pending'] ) {
		return new WP_Error( 'conflict', __( 'Der Antrag wurde inzwischen geändert oder bereits bearbeitet. Lade die Seite neu.', 'ps-pen-paper' ) );
	}
	if ( ! in_array( $decision, array( 'approve', 'reject' ), true ) ) {
		return new WP_Error( 'invalid_decision', __( 'Ungültige Freigabeaktion.', 'ps-pen-paper' ) );
	}
	$state = $previous;
	$state['revision']++;
	if ( 'approve' === $decision ) {
		$state['active'] = $state['pending'];
	}
	$state['pending'] = null;
	$rules = ps_pen_paper_get_rules( $campaign_id );
	return ps_pen_paper_save_document_event( $campaign_id, $character_id, '_pspp_character', $previous, $state, array(
		'kind' => 'approve' === $decision ? 'character_approved' : 'character_rejected',
		'visibility' => 'owner', 'recipient_id' => (int) $character->post_author,
		'rules_revision' => $rules['revision'], 'fields' => $rules['fields'],
	) );
}
