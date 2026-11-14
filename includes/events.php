<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_event_labels() {
	return array(
		'rules_saved'         => __( 'Regelwerk gespeichert', 'ps-pen-paper' ),
		'turn_changed'        => __( 'Zug gewechselt', 'ps-pen-paper' ),
		'access_saved'        => __( 'Zugangseinstellungen geändert', 'ps-pen-paper' ),
		'universe_saved'      => __( 'Universum gespeichert', 'ps-pen-paper' ),
		'universe_linked'     => __( 'Universumszuordnung geändert', 'ps-pen-paper' ),
		'character_created'   => __( 'Charakter angelegt', 'ps-pen-paper' ),
		'character_saved'     => __( 'Charakter gespeichert', 'ps-pen-paper' ),
		'character_requested' => __( 'Charakteränderung beantragt', 'ps-pen-paper' ),
		'character_approved'  => __( 'Charakteränderung freigegeben', 'ps-pen-paper' ),
		'character_rejected'  => __( 'Charakteränderung abgelehnt', 'ps-pen-paper' ),
		'character_context'   => __( 'Kampagnenauflagen freigegeben', 'ps-pen-paper' ),
		'scene_saved'         => __( 'Szenenentwurf gespeichert', 'ps-pen-paper' ),
		'scene_published'     => __( 'Szene freigegeben', 'ps-pen-paper' ),
		'scene_withdrawn'     => __( 'Szenenfreigabe zurückgenommen', 'ps-pen-paper' ),
		'scene_selected'      => __( 'Aktive Szene gewechselt', 'ps-pen-paper' ),
		'map_moved'           => __( 'Figur auf der Karte bewegt', 'ps-pen-paper' ),
		'folio_saved'         => __( 'Mappeneintrag gespeichert', 'ps-pen-paper' ),
		'folio_published'     => __( 'Handout freigegeben', 'ps-pen-paper' ),
		'folio_withdrawn'     => __( 'Handout zurückgenommen', 'ps-pen-paper' ),
		'portrait_saved'      => __( 'Charakterporträt geändert oder geprüft', 'ps-pen-paper' ),
		'sheet_style_saved'   => __( 'Bogengestaltung geändert', 'ps-pen-paper' ),
		'equipment_schema'    => __( 'Slots und Seltenheiten geändert', 'ps-pen-paper' ),
		'item_saved'          => __( 'Gegenstandsvorlage gespeichert', 'ps-pen-paper' ),
		'gear_saved'          => __( 'Inventar oder Ausrüstung geändert', 'ps-pen-paper' ),
	);
}

function ps_pen_paper_register_event_type() {
	register_post_type( 'pspp_event', array(
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => false,
		'show_in_rest'        => false,
		'capability_type'     => array( 'pspp_event', 'pspp_events' ),
		'map_meta_cap'        => true,
		'supports'            => false,
	) );
}

function ps_pen_paper_discard_record( $id, $error ) {
	if ( ! wp_delete_post( $id, true ) ) {
		return new WP_Error( 'cleanup_failed', __( 'Ein unvollständiger Protokolleintrag konnte nicht entfernt werden. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	return $error;
}

function ps_pen_paper_publish_record( $id ) {
	$result = wp_update_post( array( 'ID' => $id, 'post_status' => 'private' ), true );
	if ( is_wp_error( $result ) || ! $result || 'private' !== get_post_status( $id ) ) {
		return new WP_Error( 'save_failed', __( 'Der Protokolleintrag konnte nicht veröffentlicht werden.', 'ps-pen-paper' ) );
	}
	return true;
}

function ps_pen_paper_insert_record( $type, $campaign_id, $metadata, $publish = true ) {
	$id = wp_insert_post( wp_slash( array(
		'post_type'   => $type,
		'post_status' => 'draft',
		'post_parent' => $campaign_id,
		'post_author' => get_current_user_id(),
		'post_title'  => 'PS Pen & Paper Record',
		'meta_input'  => $metadata,
	) ), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	if ( ! $id ) {
		return new WP_Error( 'save_failed', __( 'Der Protokolleintrag konnte nicht gespeichert werden.', 'ps-pen-paper' ) );
	}
	foreach ( $metadata as $key => $value ) {
		if ( $value !== get_post_meta( $id, $key, true ) ) {
			return ps_pen_paper_discard_record( $id, new WP_Error( 'save_failed', __( 'Der Protokolleintrag konnte nicht vollständig gespeichert werden. Es wurde nichts übernommen.', 'ps-pen-paper' ) ) );
		}
	}
	if ( $publish ) {
		$result = ps_pen_paper_publish_record( $id );
		if ( is_wp_error( $result ) ) {
			return ps_pen_paper_discard_record( $id, $result );
		}
	}
	return $id;
}

function ps_pen_paper_save_document_event( $campaign_id, $object_id, $key, $before, $after, $event, $publish_object = false ) {
	$event['version'] = 1;
	$event['actor_name'] = wp_get_current_user()->display_name;
	$event['object_id'] = (int) $object_id;
	$event['before'] = $before;
	$event['after'] = $after;
	$id = ps_pen_paper_insert_record( 'pspp_event', $campaign_id, array(
		'_pspp_event'      => $event,
		'_pspp_visibility' => $event['visibility'],
		'_pspp_recipient'  => (string) $event['recipient_id'],
		'_pspp_actor'      => (string) get_current_user_id(),
	), false );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$initial = 0 === $before['revision'];
	$saved = $initial
		? add_post_meta( $object_id, $key, wp_slash( $after ), true )
		: update_post_meta( $object_id, $key, wp_slash( $after ), $before );
	if ( ! $saved ) {
		return ps_pen_paper_discard_record( $id, new WP_Error( 'save_failed', __( 'Die Änderung konnte nicht gespeichert werden. Lade die Seite neu, um zwischenzeitliche Änderungen zu prüfen.', 'ps-pen-paper' ) ) );
	}
	$result = $publish_object ? ps_pen_paper_publish_record( $object_id ) : true;
	if ( ! is_wp_error( $result ) ) {
		$result = ps_pen_paper_publish_record( $id );
	}
	if ( is_wp_error( $result ) ) {
		// Restore only our own revision; never overwrite a later concurrent change.
		$restored = $initial
			? delete_post_meta( $object_id, $key, wp_slash( $after ) )
			: update_post_meta( $object_id, $key, wp_slash( $before ), $after );
		if ( ! $restored ) {
			return new WP_Error( 'event_recovery_required', sprintf( __( 'Die Änderung wurde gespeichert, aber ihre Protokollierung ist unvollständig. Eine sichere Rücknahme war nicht möglich. Bitte informiere den Administrator über Protokolleintrag #%d.', 'ps-pen-paper' ), $id ) );
		}
		return ps_pen_paper_discard_record( $id, new WP_Error( 'save_failed', __( 'Die Änderung konnte nicht vollständig gespeichert werden und wurde zurückgenommen. Bitte lade die Seite neu.', 'ps-pen-paper' ) ) );
	}
	return true;
}

function ps_pen_paper_get_event( $campaign, $id ) {
	$error = new WP_Error( 'forbidden', __( 'Dieser Protokolleintrag ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	$universe = $campaign && 'pspp_universe' === $campaign->post_type;
	if ( ! current_user_can( 'read' ) || ! ( $universe ? ps_pen_paper_can_manage_universe( $campaign ) : ps_pen_paper_can_view_campaign( $campaign ) ) ) {
		return $error;
	}
	$post = get_post( $id );
	if ( ! $post || 'pspp_event' !== $post->post_type || 'private' !== $post->post_status || (int) $post->post_parent !== (int) $campaign->ID ) {
		return $error;
	}
	$event = get_post_meta( $id, '_pspp_event', true );
	if ( ! is_array( $event ) || ! isset( $event['version'], $event['kind'], $event['visibility'], $event['recipient_id'], $event['actor_name'], $event['object_id'], $event['before'], $event['after'], $event['fields'], $event['rules_revision'] )
		|| 1 !== $event['version'] || ! is_string( $event['kind'] ) || ! array_key_exists( $event['kind'], ps_pen_paper_event_labels() )
		|| ! in_array( $event['visibility'], array( 'group', 'owner', 'gm' ), true ) || ! is_array( $event['before'] ) || ! is_array( $event['after'] ) || ! is_array( $event['fields'] ) ) {
		return new WP_Error( 'invalid_event', __( 'Ein Protokolleintrag ist beschädigt. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	if ( ! ( $universe ? ps_pen_paper_can_manage_universe( $campaign ) : ps_pen_paper_can_manage_campaign( $campaign ) ) && 'group' !== $event['visibility'] && ( 'gm' === $event['visibility'] || get_current_user_id() !== $event['recipient_id'] ) ) {
		return $error;
	}
	$event['id'] = $post->ID;
	if ( 'character_context' === $event['kind'] && ( ! isset( $event['effective_before'], $event['effective_after'] ) || ! is_array( $event['effective_before'] ) || ! is_array( $event['effective_after'] ) ) ) {
		return new WP_Error( 'invalid_event', __( 'Die wirksamen Kontextstände im Protokoll sind unvollständig. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	$event['date'] = get_date_from_gmt( $post->post_date_gmt, 'Y-m-d H:i:s' );
	return $event;
}

function ps_pen_paper_journal_query( $campaign, $page, $types ) {
	$universe = $campaign && 'pspp_universe' === $campaign->post_type;
	if ( ! current_user_can( 'read' ) || ! ( $universe ? ps_pen_paper_can_manage_universe( $campaign ) : ps_pen_paper_can_view_campaign( $campaign ) ) ) {
		return new WP_Error( 'forbidden', __( 'Du darfst dieses Spielprotokoll nicht lesen.', 'ps-pen-paper' ) );
	}
	if ( ! is_int( $page ) || $page < 1 || $page > 1000000 ) {
		return new WP_Error( 'invalid_page', __( 'Ungültige Protokollseite.', 'ps-pen-paper' ) );
	}
	$args = array(
		'post_type' => $types, 'post_status' => 'private', 'post_parent' => $campaign->ID,
		'posts_per_page' => 20, 'paged' => $page, 'orderby' => 'ID', 'order' => 'DESC',
	);
	if ( ! $universe && ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		$args['meta_query'] = array(
			'relation' => 'OR',
			array( 'key' => '_pspp_visibility', 'value' => 'group' ),
			array(
				'relation' => 'AND',
				array( 'key' => '_pspp_visibility', 'value' => 'owner' ),
				array( 'key' => '_pspp_recipient', 'value' => (string) get_current_user_id() ),
			),
			array(
				'relation' => 'AND',
				array( 'key' => '_pspp_visibility', 'value' => 'private' ),
				array(
					'relation' => 'OR',
					array( 'key' => '_pspp_recipient', 'value' => (string) get_current_user_id() ),
					array( 'key' => '_pspp_actor', 'value' => (string) get_current_user_id() ),
				),
			),
		);
	}
	return new WP_Query( $args );
}

function ps_pen_paper_get_events( $campaign, $page = 1 ) {
	$query = ps_pen_paper_journal_query( $campaign, $page, array( 'pspp_roll', 'pspp_event' ) );
	if ( is_wp_error( $query ) ) {
		return $query;
	}
	$events = array();
	foreach ( $query->posts as $post ) {
		$event = 'pspp_roll' === $post->post_type ? ps_pen_paper_get_roll( $campaign, $post->ID ) : ps_pen_paper_get_event( $campaign, $post->ID );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		if ( 'pspp_roll' === $post->post_type ) {
			$event['kind'] = 'roll';
		}
		$events[] = $event;
	}
	return array( 'events' => $events, 'pages' => (int) $query->max_num_pages );
}
