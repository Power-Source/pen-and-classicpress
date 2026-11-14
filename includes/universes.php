<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_register_universe_type() {
	register_post_type( 'pspp_universe', array(
		'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
		'show_ui' => false, 'show_in_rest' => false,
		'capability_type' => array( 'pspp_universe', 'pspp_universes' ),
		'map_meta_cap' => true, 'supports' => false,
	) );
}

function ps_pen_paper_get_universe( $id ) {
	$post = get_post( $id );
	return $post && 'pspp_universe' === $post->post_type && 'private' === $post->post_status ? $post : null;
}

function ps_pen_paper_can_manage_universe( $universe ) {
	return ps_pen_paper_can_access_site() && $universe && 'pspp_universe' === $universe->post_type && 'private' === $universe->post_status &&
		( current_user_can( 'manage_options' ) || (int) $universe->post_author === get_current_user_id() );
}

function ps_pen_paper_get_universes() {
	if ( ! ps_pen_paper_can_access_site() ) {
		return array();
	}
	$args = array( 'post_type' => 'pspp_universe', 'post_status' => 'private', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'DESC' );
	if ( ! current_user_can( 'manage_options' ) ) {
		$args['author'] = get_current_user_id();
	}
	return get_posts( $args );
}

function ps_pen_paper_universe_document( $id ) {
	$document = get_post_meta( $id, '_pspp_universe', true );
	if ( ! is_array( $document ) || ! isset( $document['revision'], $document['name'], $document['description'] ) ||
		! is_int( $document['revision'] ) || $document['revision'] < 1 ||
		! is_string( $document['name'] ) || '' === $document['name'] || ! is_string( $document['description'] ) ) {
		return new WP_Error( 'invalid_universe', __( 'Das Universum ist unvollständig. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	return $document;
}

function ps_pen_paper_save_universe( $id, $input, $revision ) {
	$universe = $id ? ps_pen_paper_get_universe( $id ) : null;
	if ( ! ps_pen_paper_can_access_site() || ( $id ? ! ps_pen_paper_can_manage_universe( $universe ) : ! ps_pen_paper_can_create_campaign() ) ) {
		return new WP_Error( 'forbidden', __( 'Du darfst dieses Universum nicht verwalten oder anlegen.', 'ps-pen-paper' ) );
	}
	$before = $id ? ps_pen_paper_universe_document( $id ) : array( 'revision' => 0, 'name' => '', 'description' => '' );
	if ( is_wp_error( $before ) ) {
		return $before;
	}
	if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision ) {
		return new WP_Error( 'conflict', __( 'Das Universum wurde inzwischen geändert. Bitte lade die Seite neu.', 'ps-pen-paper' ) );
	}
	if ( ! is_array( $input ) || count( $input ) !== 2 || ! isset( $input['name'], $input['description'] ) ||
		! is_string( $input['name'] ) || ! is_string( $input['description'] ) ||
		'' === sanitize_text_field( $input['name'] ) || strlen( $input['name'] ) > 200 || strlen( $input['description'] ) > 20000 ) {
		return new WP_Error( 'invalid_universe', __( 'Bitte gib einen Universumsnamen mit höchstens 200 Bytes und eine Beschreibung mit höchstens 20000 Bytes ein.', 'ps-pen-paper' ) );
	}
	$after = array( 'revision' => $before['revision'] + 1, 'name' => sanitize_text_field( $input['name'] ), 'description' => sanitize_textarea_field( $input['description'] ) );
	$creating = ! $id;
	if ( $creating ) {
		$id = wp_insert_post( array( 'post_type' => 'pspp_universe', 'post_status' => 'draft', 'post_author' => get_current_user_id(), 'post_title' => 'PS Pen & Paper Universe' ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( ! $id ) {
			return new WP_Error( 'save_failed', __( 'Das Universum konnte nicht angelegt werden.', 'ps-pen-paper' ) );
		}
	}
	$result = ps_pen_paper_save_document_event( $id, $id, '_pspp_universe', $before, $after, array(
		'kind' => 'universe_saved', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0,
	), $creating );
	if ( is_wp_error( $result ) ) {
		return $creating && 'event_recovery_required' !== $result->get_error_code() ? ps_pen_paper_discard_record( $id, $result ) : $result;
	}
	return $id;
}

function ps_pen_paper_get_universe_link( $campaign_id ) {
	$link = get_post_meta( $campaign_id, '_pspp_universe_link', true );
	if ( '' === $link ) {
		return array( 'revision' => 0, 'universe_id' => 0, 'name' => '' );
	}
	if ( ! is_array( $link ) || ! isset( $link['revision'], $link['universe_id'], $link['name'] ) ||
		! is_int( $link['revision'] ) || $link['revision'] < 1 || ! is_int( $link['universe_id'] ) || $link['universe_id'] < 0 || ! is_string( $link['name'] ) ) {
		return new WP_Error( 'invalid_universe_link', __( 'Die Universumszuordnung ist beschädigt. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	return $link;
}

function ps_pen_paper_preview_universe_link( $campaign_id, $universe_id ) {
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! ps_pen_paper_can_access_site() || ! $campaign || ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die Kampagnenleitung darf eine Universumszuordnung prüfen.', 'ps-pen-paper' ) );
	}
	if ( ! is_int( $universe_id ) || $universe_id < 0 ) {
		return new WP_Error( 'invalid_universe', __( 'Bitte wähle ein gültiges Universum.', 'ps-pen-paper' ) );
	}
	$before = ps_pen_paper_get_universe_link( $campaign_id );
	if ( is_wp_error( $before ) ) {
		return $before;
	}
	if ( $before['universe_id'] !== $universe_id && ps_pen_paper_has_shared_characters( $campaign_id ) ) {
		return new WP_Error( 'context_in_use', __( 'Diese Kampagne nutzt gemeinsame Basisbögen. Ein Universumswechsel oder Lösen ist zum Schutz dieser Verbindungen derzeit gesperrt.', 'ps-pen-paper' ) );
	}
	if ( $before['universe_id'] !== $universe_id && ps_pen_paper_campaign_has_wallets( $campaign_id ) ) {
		return new WP_Error( 'wallets_in_use', __( 'Diese Kampagne hat bereits Guthabenkonten. Eine andere Universumszuordnung benötigt eine ausdrückliche Guthabenmigration und ist derzeit gesperrt.', 'ps-pen-paper' ) );
	}
	$documents = array( 0 => array( 'revision' => 0, 'name' => '' ) );
	foreach ( array_unique( array( $before['universe_id'], $universe_id ) ) as $id ) {
		if ( $id && ! ps_pen_paper_can_manage_universe( ps_pen_paper_get_universe( $id ) ) ) {
			return new WP_Error( 'forbidden', __( 'Du musst das bisherige und das gewählte Universum verwalten dürfen. Bei einer fehlenden Zuordnung wende dich an den Administrator.', 'ps-pen-paper' ) );
		}
		if ( $id ) {
			$documents[ $id ] = ps_pen_paper_universe_document( $id );
			if ( is_wp_error( $documents[ $id ] ) ) {
				return $documents[ $id ];
			}
		}
	}
	return array( 'before' => $before, 'universe' => $documents[ $universe_id ], 'source_universe' => $documents[ $before['universe_id'] ], 'campaign' => $campaign );
}

function ps_pen_paper_save_universe_link( $campaign_id, $universe_id, $revision, $universe_revision, $confirmed ) {
	$preview = ps_pen_paper_preview_universe_link( $campaign_id, $universe_id );
	if ( is_wp_error( $preview ) ) {
		return $preview;
	}
	if ( ! is_string( $revision ) || (string) $preview['before']['revision'] !== $revision ||
		! is_string( $universe_revision ) || (string) $preview['universe']['revision'] !== $universe_revision ) {
		return new WP_Error( 'conflict', __( 'Die Zuordnung oder das Universum wurde inzwischen geändert. Bitte prüfe eine neue Vorschau.', 'ps-pen-paper' ) );
	}
	if ( 'confirmed' !== $confirmed || $preview['before']['universe_id'] === $universe_id ) {
		return new WP_Error( 'confirmation_required', __( 'Bitte prüfe und bestätige eine geänderte Universumszuordnung.', 'ps-pen-paper' ) );
	}
	if ( ps_pen_paper_has_shared_characters( $campaign_id ) ) {
		return new WP_Error( 'context_in_use', __( 'Diese Kampagne nutzt gemeinsame Basisbögen. Ein Universumswechsel oder Lösen ist zum Schutz dieser Verbindungen derzeit gesperrt.', 'ps-pen-paper' ) );
	}
	$after = array( 'revision' => $preview['before']['revision'] + 1, 'universe_id' => $universe_id, 'name' => $preview['universe']['name'] );
	$result = ps_pen_paper_atomic_event( $campaign_id, function () use ( $campaign_id, $universe_id, $preview, $after ) {
		$current = ps_pen_paper_preview_universe_link( $campaign_id, $universe_id );
		if ( is_wp_error( $current ) ) { return $current; }
		if ( $current['before'] !== $preview['before'] || $current['universe'] !== $preview['universe'] ) {
			return new WP_Error( 'conflict', __( 'Die Zuordnung wurde geändert. Bitte prüfe eine neue Vorschau.', 'ps-pen-paper' ) );
		}
		return ps_pen_paper_write_document_event( $campaign_id, $campaign_id, '_pspp_universe_link', $preview['before'], $after, array(
			'kind' => 'universe_linked', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0,
		), false );
	} );
	return is_wp_error( $result ) ? $result : $after;
}
