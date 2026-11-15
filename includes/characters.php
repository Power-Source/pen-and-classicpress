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

function ps_pen_paper_field_edit_modes() {
	return array(
		'inherit' => __( 'Vorgabe des Regelwerks', 'ps-pen-paper' ),
		'direct' => __( 'Spieler direkt', 'ps-pen-paper' ),
		'approval' => __( 'Mit Freigabe', 'ps-pen-paper' ),
		'gm_only' => __( 'Nur Spielleitung', 'ps-pen-paper' ),
	);
}

function ps_pen_paper_field_edit_mode( $field, $rules ) {
	if ( ! empty( $rules['progression'] ) && $field['id'] === $rules['progression']['level_field'] ) { return 'gm_only'; }
	if ( isset( $rules['allocation']['per_level'] ) && ! ps_pen_paper_character_creation_field_editable( $field, $rules ) ) { return 'gm_only'; }
	return $field['edit_mode'] ?? ps_pen_paper_character_mode( $rules );
}

function ps_pen_paper_character_creation_field_editable( $field, $rules ) {
	if ( 'gm_only' === ( $field['edit_mode'] ?? 'inherit' ) ) { return false; }
	if ( ! empty( $rules['progression'] ) && in_array( $field['id'], array( $rules['progression']['experience_field'], $rules['progression']['level_field'] ), true ) ) { return false; }
	return in_array( $field['type'], array( 'text', 'textarea', 'choice' ), true ) ||
		( 'attribute' === $field['type'] && in_array( $field['id'], $rules['allocation']['fields'] ?? array(), true ) );
}

function ps_pen_paper_validate_character_creation( $input, $rules ) {
	$snapshot = ps_pen_paper_validate_character( $input, $rules );
	if ( is_wp_error( $snapshot ) ) { return $snapshot; }
	$defaults = ps_pen_paper_character_values( $rules );
	foreach ( $rules['fields'] as $field ) {
		if ( ps_pen_paper_character_creation_field_editable( $field, $rules ) ) { continue; }
		$value = $snapshot['values'][ $field['id'] ];
		if ( ! empty( $rules['progression'] ) && $field['id'] === $rules['progression']['level_field'] ) { $value = (int) $input['values'][ $field['id'] ]; }
		if ( $value !== $defaults[ $field['id'] ] ) {
			return new WP_Error( 'creation_values_forbidden', __( 'Startwerte, Ressourcen, Erfahrung und Level werden von der Spielleitung vorgegeben. Du kannst nur die freigegebenen Attributpunkte verteilen.', 'ps-pen-paper' ) );
		}
	}
	return $snapshot;
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
		$schema[ $field['id'] ] = array_intersect_key( $field, array_flip( array( 'type', 'required', 'min', 'max', 'choices' ) ) );
	}
	ksort( $schema );
	$result = array( 'fields' => $schema, 'allocation' => isset( $rules['allocation'] ) ? $rules['allocation'] : null );
	if ( ! empty( $rules['progression'] ) ) { $result['progression'] = $rules['progression']; }
	return $result;
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
	if ( ! current_user_can( 'read' ) || ! ( 'pspp_universe' === $campaign->post_type ? ps_pen_paper_can_use_universe_characters( $campaign ) : ps_pen_paper_can_view_character_scope( $campaign ) ) ) {
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
	$character = ps_pen_paper_record_post( $character_id );
	if ( ! current_user_can( 'read' ) || ! ( 'pspp_universe' === $campaign->post_type ? ps_pen_paper_can_use_universe_characters( $campaign ) : ps_pen_paper_can_view_character_scope( $campaign ) ) || ! $character || 'pspp_character' !== $character->post_type || 'private' !== $character->post_status || (int) $character->post_parent !== (int) $campaign->ID ) {
		return null;
	}
	if ( ! ps_pen_paper_can_manage_character_scope( $campaign ) && (int) $character->post_author !== get_current_user_id() ) {
		return null;
	}
	return $character;
}

function ps_pen_paper_can_edit_character( $campaign, $character = null ) {
	if ( ! current_user_can( 'read' ) || ! ( 'pspp_universe' === $campaign->post_type ? ps_pen_paper_can_use_universe_characters( $campaign ) : ps_pen_paper_can_view_character_scope( $campaign ) ) ) {
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
	if ( ps_pen_paper_can_manage_character_scope( $campaign ) ) { return true; }
	$rules = ps_pen_paper_get_rules( $campaign->ID );
	if ( 'gm_only' !== ps_pen_paper_character_mode( $rules ) ) { return true; }
	foreach ( $rules['fields'] as $field ) {
		if ( 'gm_only' !== ps_pen_paper_field_edit_mode( $field, $rules ) ) { return true; }
	}
	return false;
}

function ps_pen_paper_character_lock_name( $character_id ) {
	global $wpdb;
	return 'pspp_character_' . md5( DB_NAME . ':' . $wpdb->postmeta . ':' . (int) $character_id );
}

function ps_pen_paper_with_character_lock( $character_id, $callback ) {
	if ( ! $character_id ) { return $callback(); }
	global $wpdb;
	static $active = array();
	$name = ps_pen_paper_character_lock_name( $character_id );
	$conflict = new WP_Error( 'conflict', __( 'Dieser Charakter oder seine Ausrüstung wird gerade geändert. Es wurde nichts übernommen. Bitte lade den aktuellen Stand neu.', 'ps-pen-paper' ) );
	// Named database locks are reentrant; hooks must not re-enter the same mutation.
	if ( isset( $active[ $name ] ) ) { return $conflict; }
	$acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) );
	if ( '1' !== $acquired ) {
		return '0' === $acquired ? $conflict : new WP_Error( 'character_lock_failed', __( 'Die Datenbanksperre für diesen Charakter ist nicht verfügbar. Es wurde nichts übernommen. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	$active[ $name ] = true;
	try {
		// Earlier reads in this request may predate another writer's completed change.
		wp_cache_delete( (int) $character_id, 'post_meta' );
		$result = $callback();
	} finally {
		$released = $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		unset( $active[ $name ] );
	}
	if ( '1' !== $released ) {
		return new WP_Error( 'character_lock_recovery_required', __( 'Die Datenbanksperre konnte nicht freigegeben werden. Änderungen können bereits gespeichert sein. Bitte lade den aktuellen Stand neu und informiere den Administrator.', 'ps-pen-paper' ) );
	}
	return $result;
}

function ps_pen_paper_with_character_locks( $ids, $write ) {
	// Callers supply unique IDs in numeric order to avoid opposite lock orders.
	if ( ! $ids ) { return $write(); }
	$id = array_shift( $ids );
	return ps_pen_paper_with_character_lock( $id, function () use ( $ids, $write ) {
		return ps_pen_paper_with_character_locks( $ids, $write );
	} );
}

function ps_pen_paper_character_values( $rules ) {
	$values = array();
	foreach ( $rules['fields'] as $field ) {
		if ( 'resource' === $field['type'] ) {
			$values[ $field['id'] ] = array( 'current' => $field['start'], 'maximum' => $field['max'] );
		} elseif ( 'choice' === $field['type'] ) {
			$values[ $field['id'] ] = $field['start'];
		} else {
			$values[ $field['id'] ] = isset( $field['start'] ) ? $field['start'] : '';
		}
	}
	if ( ! empty( $rules['allocation'] ) ) {
		$defaults = ps_pen_paper_character_allocation_defaults( $rules );
		if ( ! is_wp_error( $defaults ) ) { $values = array_merge( $values, $defaults ); }
	}
	return ps_pen_paper_apply_progression( array( 'values' => $values ), $rules )['values'];
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
		if ( 'choice' === $field['type'] ) {
			if ( ! is_string( $value ) || ( '' !== $value && ! in_array( $value, $field['choices'], true ) ) || ( $field['required'] && '' === $value ) ) { return $error; }
			$clean['values'][ $field['id'] ] = $value;
			continue;
		}
		if ( in_array( $field['type'], array( 'text', 'textarea' ), true ) ) {
			if ( ! is_string( $value ) || strlen( $value ) > ( 'text' === $field['type'] ? 200 : 20000 ) ) {
				return $error;
			}
			$value = 'text' === $field['type'] ? sanitize_text_field( $value ) : sanitize_textarea_field( $value );
			if ( $field['required'] && '' === $value ) {
				return $error;
			}
			if ( ! empty( $field['choices'] ) && '' !== $value && ! in_array( $value, $field['choices'], true ) ) { return $error; }
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
	if ( ! ps_pen_paper_character_allocation_valid( $clean, $rules ) ) {
		return new WP_Error( 'invalid_allocation', __( 'Die ausgewählten Attribute müssen zusammen genau die festgelegte Punktzahl ergeben.', 'ps-pen-paper' ) );
	}
	return ps_pen_paper_apply_progression( $clean, $rules );
}

function ps_pen_paper_player_character_state( $snapshot, $previous, $rules ) {
	$defaults = array( 'name' => '', 'values' => ps_pen_paper_character_values( $rules ) );
	$active = $previous['active'] ?? $defaults;
	$form = $previous['pending'] ?? $active;
	$state = $previous;
	$pending = $previous['pending'];
	$changes = array( 'name' => ps_pen_paper_character_mode( $rules ) );
	foreach ( $rules['fields'] as $field ) { $changes[ $field['id'] ] = ps_pen_paper_field_edit_mode( $field, $rules ); }
	foreach ( $changes as $id => $mode ) {
		$is_name = 'name' === $id;
		$value = $is_name ? $snapshot['name'] : $snapshot['values'][ $id ];
		$before = $is_name ? $form['name'] : $form['values'][ $id ];
		$current = $is_name ? $active['name'] : $active['values'][ $id ];
		if ( ! empty( $rules['progression'] ) && $id === $rules['progression']['level_field'] ) { continue; }
		if ( 'gm_only' === $mode ) {
			if ( $value !== $current ) { return new WP_Error( 'field_forbidden', __( 'Ein geschütztes Feld darf nur die Spielleitung ändern.', 'ps-pen-paper' ) ); }
			continue;
		}
		if ( $value === $before && null !== $previous['active'] ) { continue; }
		if ( 'approval' === $mode ) {
			if ( null === $pending ) { $pending = $active; }
			if ( $is_name ) { $pending['name'] = $value; } else { $pending['values'][ $id ] = $value; }
		} else {
			if ( $is_name ) { $active['name'] = $value; } else { $active['values'][ $id ] = $value; }
			if ( null !== $pending ) {
				if ( $is_name ) { $pending['name'] = $value; } else { $pending['values'][ $id ] = $value; }
			}
		}
	}
	$active = ps_pen_paper_apply_progression( $active, $rules );
	if ( null !== $pending ) { $pending = ps_pen_paper_apply_progression( $pending, $rules ); }
	if ( null === $previous['active'] ) {
		// A new mixed-mode sheet stays pending until its complete initial state is approved.
		if ( null !== $pending ) { $pending = $snapshot; $active = null; }
	}
	if ( null !== $active && ! ps_pen_paper_valid_character_snapshot( $active, $rules ) ) {
		return new WP_Error( 'mixed_character_conflict', __( 'Die sofortigen Änderungen passen nicht zum gültigen Bogen. Prüfe Pflichtfelder und Punkteverteilung oder bitte die Spielleitung um Freigabe.', 'ps-pen-paper' ) );
	}
	if ( null !== $pending && ! ps_pen_paper_valid_character_snapshot( $pending, $rules ) ) {
		return new WP_Error( 'mixed_character_conflict', __( 'Der Antrag passt nicht zum Regelwerk. Bitte prüfe die Feldwerte und Punkteverteilung.', 'ps-pen-paper' ) );
	}
	$state['active'] = $active;
	$state['pending'] = $pending;
	return $state;
}

function ps_pen_paper_save_character( $campaign_id, $character_id, $owner_id, $input, $revision, $rules_revision, $player_context = false ) {
	return ps_pen_paper_with_character_lock( $character_id, function () use ( $campaign_id, $character_id, $owner_id, $input, $revision, $rules_revision, $player_context ) {
		$write = function () use ( $campaign_id, $character_id, $owner_id, $input, $revision, $rules_revision, $player_context ) {
			return ps_pen_paper_save_character_locked( $campaign_id, $character_id, $owner_id, $input, $revision, $rules_revision, $player_context );
		};
		if ( ! $character_id && ps_pen_paper_get_campaign( $campaign_id ) ) {
			$link = ps_pen_paper_get_universe_link( $campaign_id );
			if ( is_wp_error( $link ) ) { return $link; }
			if ( $link['universe_id'] ) {
				$scopes = array( (int) $campaign_id, $link['universe_id'] ); sort( $scopes, SORT_NUMERIC );
				return ps_pen_paper_scope_lock( $scopes[0], function () use ( $scopes, $write ) { return ps_pen_paper_scope_lock( $scopes[1], $write ); } );
			}
		}
		return ps_pen_paper_scope_write( $campaign_id, $write );
	} );
}

function ps_pen_paper_save_character_locked( $campaign_id, $character_id, $owner_id, $input, $revision, $rules_revision, $player_context = false ) {
	$campaign = ps_pen_paper_get_character_scope( $campaign_id );
	$character = $campaign && $character_id ? ps_pen_paper_get_character( $campaign, $character_id ) : null;
	if ( ! $campaign || ( $character_id && ! $character ) || ! ps_pen_paper_can_edit_character( $campaign, $character ) ) {
		return new WP_Error( 'forbidden', __( 'Du darfst diesen Charakter nicht bearbeiten.', 'ps-pen-paper' ) );
	}
	if ( $character ) {
		$current = ps_pen_paper_character_state( $campaign, $character, false );
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
	$is_gm = ! $player_context && ps_pen_paper_can_manage_character_scope( $campaign );
	$universe = 'pspp_universe' === $campaign->post_type;
	if ( ! get_userdata( $owner_id ) || ! is_user_member_of_blog( $owner_id ) || ( $universe ? ! user_can( $owner_id, 'read' ) : ! ps_pen_paper_campaign_role( $campaign, $owner_id ) ) || ( ! $is_gm && $owner_id !== get_current_user_id() ) ) {
		return new WP_Error( 'invalid_owner', $universe ? __( 'Der Basisbogen muss einem leseberechtigten Mitglied dieser Website gehören.', 'ps-pen-paper' ) : __( 'Der Charakter muss einem Mitglied dieser Kampagne gehören.', 'ps-pen-paper' ) );
	}
	if ( ! $character ) {
		if ( $universe && ! $is_gm ) {
			$policy = ps_pen_paper_character_policy( $campaign_id );
			if ( is_wp_error( $policy ) ) { return $policy; }
			if ( ! ps_pen_paper_is_universe_member( $campaign ) && 'site' !== $policy['creation_access'] ) { return new WP_Error( 'forbidden', __( 'Neue Charaktere benötigen eine Universumsmitgliedschaft.', 'ps-pen-paper' ) ); }
		}
		$link = $universe ? array( 'universe_id' => (int) $campaign_id ) : ps_pen_paper_get_universe_link( $campaign_id );
		if ( is_wp_error( $link ) ) { return $link; }
		if ( $link['universe_id'] ) {
			if ( ! ps_pen_paper_get_universe( $link['universe_id'] ) ) { return new WP_Error( 'invalid_universe', __( 'Bitte repariere zuerst die Universumszuordnung.', 'ps-pen-paper' ) ); }
			$limit = ps_pen_paper_check_character_creation_limit( $link['universe_id'], $owner_id );
			if ( is_wp_error( $limit ) ) { return $limit; }
		}
	}
	$snapshot = ! $character && ! $is_gm ? ps_pen_paper_validate_character_creation( $input, $rules ) : ps_pen_paper_validate_character( $input, $rules );
	if ( is_wp_error( $snapshot ) ) {
		return $snapshot;
	}
	$state = $previous;
	if ( ! $is_gm ) {
		$state = ps_pen_paper_player_character_state( $snapshot, $previous, $rules );
		if ( is_wp_error( $state ) ) { return $state; }
	} else {
		$state['active'] = $snapshot;
		$state['pending'] = null;
	}
	$state['revision']++;
	foreach ( array( 'active', 'pending' ) as $slot ) {
		if ( null === $state[ $slot ] ) { continue; }
		$gear_check = ps_pen_paper_check_character_gear( $campaign, $character, $state[ $slot ] );
		if ( is_wp_error( $gear_check ) ) { return $gear_check; }
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
		if ( 'event_recovery_required' === $saved->get_error_code() ) { return $saved; }
		if ( ! $character && ! metadata_exists( 'post', $character_id, '_pspp_character' ) && ! wp_delete_post( $character_id, true ) ) {
			return new WP_Error( 'cleanup_failed', __( 'Der Charakter ist unvollständig gespeichert und konnte nicht entfernt werden. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
		}
		return $saved;
	}
	return $character_id;
}

function ps_pen_paper_review_character( $campaign_id, $character_id, $decision, $revision ) {
	$stored = get_post_meta( $character_id, '_pspp_character', true );
	$is_base_request = is_array( $stored ) && null === ( $stored['active'] ?? null ) && null !== ( $stored['pending'] ?? null ) &&
		isset( $stored['context']['mode'], $stored['context']['source_id'] ) && 'shared' === $stored['context']['mode'];
	if ( $is_base_request ) {
		if ( 'reject' === $decision && ! ps_pen_paper_record_post( $stored['context']['source_id'] ) ) {
			return ps_pen_paper_with_character_lock( $character_id, function () use ( $campaign_id, $character_id, $decision, $revision ) {
				return ps_pen_paper_review_character_locked( $campaign_id, $character_id, $decision, $revision );
			} );
		}
		return ps_pen_paper_with_character_context_locks( $campaign_id, $character_id, $stored['context']['source_id'], function () use ( $campaign_id, $character_id, $decision, $revision ) {
			return ps_pen_paper_review_character_locked( $campaign_id, $character_id, $decision, $revision );
		} );
	}
	return ps_pen_paper_with_character_lock( $character_id, function () use ( $campaign_id, $character_id, $decision, $revision ) {
		return ps_pen_paper_review_character_locked( $campaign_id, $character_id, $decision, $revision );
	} );
}

function ps_pen_paper_character_resource_cost( $campaign, $character, $resource_id, $amount ) {
	$error = new WP_Error( 'invalid_resource_cost', __( 'Die Ressourcenkosten oder der Charakterstand sind ungültig. Bitte informiere die Spielleitung.', 'ps-pen-paper' ) );
	if ( ! $campaign || 'pspp_campaign' !== $campaign->post_type || ! $character ||
		! ps_pen_paper_can_access_site() || ! ps_pen_paper_get_character( $campaign, $character->ID ) ||
		! is_string( $resource_id ) || ! wp_is_uuid( $resource_id ) || ! is_int( $amount ) || $amount < 1 || $amount > 1000000 ) { return $error; }
	$state = ps_pen_paper_raw_character_state( $character->ID );
	if ( is_wp_error( $state ) ) { return $state; }
	if ( ! $state['active'] ) { return $error; }
	if ( null !== $state['pending'] ) {
		return new WP_Error( 'resource_cost_pending', __( 'Vor einem kostenpflichtigen Versuch muss der offene Werteantrag freigegeben oder abgelehnt werden.', 'ps-pen-paper' ) );
	}
	$rules = ps_pen_paper_get_rules( $campaign->ID );
	$shared_base = null;
	if ( isset( $state['context'] ) ) {
		$context = ps_pen_paper_validate_context( $state['context'], $rules );
		if ( is_wp_error( $context ) ) { return $context; }
		if ( 'shared' === $context['mode'] ) {
			$link = ps_pen_paper_get_universe_link( $campaign->ID );
			$base_post = ps_pen_paper_record_post( $context['source_id'] );
			$base = $base_post ? ps_pen_paper_raw_character_state( $base_post->ID ) : null;
			if ( is_wp_error( $link ) || $link['universe_id'] !== $context['universe_id'] || ! $base_post ||
				'pspp_character' !== $base_post->post_type || 'private' !== $base_post->post_status ||
				(int) $base_post->post_parent !== $context['universe_id'] || is_wp_error( $base ) || ! $base ||
				! $base['active'] || null !== $base['pending'] || array_key_exists( 'context', $base ) ||
				! ps_pen_paper_get_universe( $context['universe_id'] ) ||
				ps_pen_paper_character_schema( $rules ) !== ps_pen_paper_character_schema( ps_pen_paper_get_rules( $context['universe_id'] ) ) ||
				(int) $base_post->post_author !== (int) $character->post_author ) {
				return new WP_Error( 'resource_cost_shared_conflict', __( 'Der gemeinsame Basisbogen hat einen offenen Antrag oder wurde geändert. Bitte lade den Charakterbogen neu.', 'ps-pen-paper' ) );
			}
			$state['active'] = $base['active'];
			$state['revision'] = $base['revision'];
			$shared_base = array(
				'universe_id' => (int) $context['universe_id'],
				'character_id' => (int) $base_post->ID,
				'alias_revision' => (int) ps_pen_paper_raw_character_state( $character->ID )['revision'],
				'source_revision' => (int) $base['revision'],
			);
		} elseif ( 'copy' !== $context['mode'] ) {
			return new WP_Error( 'resource_cost_shared', __( 'Für diesen Kampagnenbogen ist kein Ressourcenverbrauch freigegeben.', 'ps-pen-paper' ) );
		}
	}
	$snapshot = ps_pen_paper_context_snapshot( $state['active'], $rules );
	if ( is_wp_error( $snapshot ) ) { return $snapshot; }
	foreach ( $rules['fields'] as $field ) {
		if ( $field['id'] === $resource_id && 'resource' === $field['type'] ) {
			$value = $snapshot['values'][ $resource_id ];
			$cost = array( 'id' => $resource_id, 'label' => $field['label'], 'amount' => $amount,
				'before' => $value['current'], 'after' => $value['current'] - $amount,
				'minimum' => max( 0, $field['min'] ), 'maximum' => $value['maximum'], 'character_revision' => $state['revision'] );
			if ( $shared_base ) { $cost['shared_base'] = $shared_base; }
			return $cost;
		}
	}
	return $error;
}

function ps_pen_paper_valid_character_resource_cost( $cost ) {
	$keys = is_array( $cost ) && array_key_exists( 'shared_base', $cost )
		? array( 'id', 'label', 'amount', 'before', 'after', 'minimum', 'maximum', 'character_revision', 'shared_base' )
		: array( 'id', 'label', 'amount', 'before', 'after', 'minimum', 'maximum', 'character_revision' );
	if ( ! is_array( $cost ) || $keys !== array_keys( $cost ) ||
		! is_string( $cost['id'] ) || ! wp_is_uuid( $cost['id'] ) || ! is_string( $cost['label'] ) || '' === $cost['label'] ) { return false; }
	foreach ( array( 'amount', 'before', 'after', 'minimum', 'maximum', 'character_revision' ) as $key ) { if ( ! is_int( $cost[ $key ] ) ) { return false; } }
	$shared_valid = ! array_key_exists( 'shared_base', $cost ) || ( is_array( $cost['shared_base'] ) &&
		array( 'universe_id', 'character_id', 'alias_revision', 'source_revision' ) === array_keys( $cost['shared_base'] ) &&
		is_int( $cost['shared_base']['universe_id'] ) && $cost['shared_base']['universe_id'] > 0 &&
		is_int( $cost['shared_base']['character_id'] ) && $cost['shared_base']['character_id'] > 0 &&
		is_int( $cost['shared_base']['alias_revision'] ) && $cost['shared_base']['alias_revision'] > 0 &&
		is_int( $cost['shared_base']['source_revision'] ) && $cost['shared_base']['source_revision'] === $cost['character_revision'] );
	return $shared_valid && $cost['amount'] > 0 && $cost['amount'] <= 1000000 && $cost['minimum'] >= 0 && $cost['maximum'] <= 1000000 &&
		$cost['after'] >= $cost['minimum'] && $cost['before'] <= $cost['maximum'] &&
		$cost['after'] === $cost['before'] - $cost['amount'] && $cost['character_revision'] > 0;
}

function ps_pen_paper_character_resource_lock_ids( $campaign, $character, $cost = null ) {
	$character_ids = array( (int) $character->ID );
	$scope_ids = array( (int) $campaign->ID );
	if ( is_array( $cost ) && isset( $cost['shared_base']['character_id'], $cost['shared_base']['universe_id'] ) ) {
		$character_ids[] = (int) $cost['shared_base']['character_id'];
		$scope_ids[] = (int) $cost['shared_base']['universe_id'];
	}
	$character_ids = array_values( array_unique( $character_ids ) );
	$scope_ids = array_values( array_unique( $scope_ids ) );
	sort( $character_ids, SORT_NUMERIC );
	sort( $scope_ids, SORT_NUMERIC );
	return array( 'character_ids' => $character_ids, 'scope_ids' => $scope_ids );
}

function ps_pen_paper_with_character_resource_locks( $campaign, $character, $cost, $callback ) {
	$locks = ps_pen_paper_character_resource_lock_ids( $campaign, $character, $cost );
	$lock_scopes = function ( $index ) use ( &$lock_scopes, $locks, $callback ) {
		if ( ! isset( $locks['scope_ids'][ $index ] ) ) { return $callback(); }
		return ps_pen_paper_scope_lock( $locks['scope_ids'][ $index ], function () use ( &$lock_scopes, $index ) { return $lock_scopes( $index + 1 ); } );
	};
	return ps_pen_paper_with_character_locks( $locks['character_ids'], function () use ( $lock_scopes ) { return $lock_scopes( 0 ); } );
}

// The caller authorizes the cost and holds all returned locks and one enclosing transaction.
function ps_pen_paper_write_character_resource_cost( $campaign, $character, $cost ) {
	if ( ! $character || (int) $character->post_author !== get_current_user_id() || ! ps_pen_paper_valid_character_resource_cost( $cost ) ) {
		return new WP_Error( 'invalid_resource_cost', __( 'Dieser Ressourcenverbrauch ist nicht zulässig. Es wurde nichts gespeichert.', 'ps-pen-paper' ) );
	}
	$current = ps_pen_paper_character_resource_cost( $campaign, $character, $cost['id'], $cost['amount'] );
	if ( is_wp_error( $current ) ) { return $current; }
	if ( $current !== $cost ) {
		return new WP_Error( 'conflict', __( 'Die Ressource wurde inzwischen geändert. Lade den Charakterbogen neu.', 'ps-pen-paper' ) );
	}
	if ( isset( $cost['shared_base'] ) ) {
		$shared = $cost['shared_base'];
		$base = ps_pen_paper_record_post( $shared['character_id'] );
		$before = $base ? ps_pen_paper_raw_character_state( $base->ID ) : null;
		$alias_state = ps_pen_paper_raw_character_state( $character->ID );
		if ( ! $base || is_wp_error( $before ) || is_wp_error( $alias_state ) ||
			(int) $base->post_parent !== $shared['universe_id'] || (int) $base->post_author !== (int) $character->post_author ||
			$before['revision'] !== $shared['source_revision'] || $alias_state['revision'] !== $shared['alias_revision'] ||
			! isset( $alias_state['context']['source_id'] ) || $alias_state['context']['source_id'] !== $base->ID ) {
			return new WP_Error( 'conflict', __( 'Der gemeinsame Basisbogen wurde inzwischen geändert. Lade die Seite neu.', 'ps-pen-paper' ) );
		}
		$after = $before;
		$after['revision']++;
		$after['active']['values'][ $cost['id'] ]['current'] = $cost['after'];
		$effective = ps_pen_paper_character_state( $campaign, $character, false );
		if ( is_wp_error( $effective ) ) { return $effective; }
		$effective['active']['values'][ $cost['id'] ]['current'] = $cost['after'];
		$check = ps_pen_paper_check_character_gear( $campaign, $character, $effective['active'] );
		if ( is_wp_error( $check ) ) { return $check; }
		$universe_rules = ps_pen_paper_get_rules( $shared['universe_id'] );
		return ps_pen_paper_write_document_event( $shared['universe_id'], $base->ID, '_pspp_character', $before, $after, array(
			'kind' => 'character_resource_spent', 'visibility' => 'owner', 'recipient_id' => (int) $base->post_author,
			'rules_revision' => $universe_rules['revision'], 'fields' => $universe_rules['fields'], 'resource_cost' => $cost,
		), false );
	}
	$before = ps_pen_paper_raw_character_state( $character->ID );
	if ( is_wp_error( $before ) ) { return $before; }
	$after = $before;
	$after['revision']++;
	$after['active']['values'][ $cost['id'] ]['current'] = $cost['after'];
	$check = ps_pen_paper_check_character_gear( $campaign, $character, $after['active'] );
	if ( is_wp_error( $check ) ) { return $check; }
	$rules = ps_pen_paper_get_rules( $campaign->ID );
	return ps_pen_paper_write_document_event( $campaign->ID, $character->ID, '_pspp_character', $before, $after, array(
		'kind' => 'character_resource_spent', 'visibility' => 'owner', 'recipient_id' => (int) $character->post_author,
		'rules_revision' => $rules['revision'], 'fields' => $rules['fields'], 'resource_cost' => $cost,
	), false );
}

function ps_pen_paper_review_character_locked( $campaign_id, $character_id, $decision, $revision ) {
	$campaign = ps_pen_paper_get_character_scope( $campaign_id );
	$character = $campaign ? ps_pen_paper_get_character( $campaign, $character_id ) : null;
	$previous = $character ? get_post_meta( $character_id, '_pspp_character', true ) : array();
	$is_base_request = is_array( $previous ) && null === ( $previous['active'] ?? null ) && null !== ( $previous['pending'] ?? null ) &&
		isset( $previous['context']['mode'], $previous['context']['source_id'] ) && 'shared' === $previous['context']['mode'];
	if ( ! $character || ! ps_pen_paper_can_manage_character_scope( $campaign ) || ( ! $is_base_request && ! ps_pen_paper_can_edit_character( $campaign, $character ) ) ) {
		return new WP_Error( 'forbidden', __( 'Nur Spielleiter dürfen Charakteränderungen freigeben.', 'ps-pen-paper' ) );
	}
	if ( ! is_string( $revision ) || (string) $previous['revision'] !== $revision || null === $previous['pending'] ) {
		return new WP_Error( 'conflict', __( 'Der Antrag wurde inzwischen geändert oder bereits bearbeitet. Lade die Seite neu.', 'ps-pen-paper' ) );
	}
	if ( ! in_array( $decision, array( 'approve', 'reject' ), true ) ) {
		return new WP_Error( 'invalid_decision', __( 'Ungültige Freigabeaktion.', 'ps-pen-paper' ) );
	}
	$state = $previous;
	$state['revision']++;
	if ( 'approve' === $decision ) {
		if ( ! ps_pen_paper_valid_character_snapshot( $state['pending'], ps_pen_paper_get_rules( $campaign_id ) ) ) {
			return new WP_Error( 'invalid_character', __( 'Der offene Antrag passt nicht zum aktuellen Regelwerk. Bitte prüfe den Bogen vor der Freigabe.', 'ps-pen-paper' ) );
		}
		if ( $is_base_request ) {
			$source = ps_pen_paper_raw_character_state( $previous['context']['source_id'] );
			if ( is_wp_error( $source ) || $source['revision'] !== $previous['context']['source_revision'] ||
				! $source['active'] || $source['active'] !== $previous['pending'] ) {
				return new WP_Error( 'base_request_conflict', __( 'Der Basisbogen wurde seit der Anfrage geändert. Bitte lehne die Anfrage ab und bitte das Mitglied, sie erneut einzureichen.', 'ps-pen-paper' ) );
			}
		}
		$gear_check = ps_pen_paper_check_character_gear( $campaign, $character, $state['pending'] );
		if ( is_wp_error( $gear_check ) ) { return $gear_check; }
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
