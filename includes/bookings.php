<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_bookings_available() {
	return class_exists( 'Eab_EventsHub', false ) && class_exists( 'Eab_EventModel', false ) &&
		class_exists( 'Eab_Template', false ) && post_type_exists( 'psource_event' );
}

function ps_pen_paper_booking_links( $id ) {
	$values = get_post_meta( $id, '_pspp_booking_links', false );
	if ( ! $values ) { return array( 'revision' => 0, 'events' => array() ); }
	$value = $values[0];
	if ( count( $values ) !== 1 || ! is_array( $value ) || array_keys( $value ) !== array( 'revision', 'events' ) ||
		! is_int( $value['revision'] ) || $value['revision'] < 1 || ! is_array( $value['events'] ) ||
		array_values( $value['events'] ) !== $value['events'] ||
		count( $value['events'] ) !== count( array_unique( $value['events'], SORT_REGULAR ) ) ) {
		return new WP_Error( 'invalid_booking_links', __( 'Die Terminverknüpfungen sind beschädigt. Bitte informiere die Administration.', 'ps-pen-paper' ) );
	}
	foreach ( $value['events'] as $event_id ) {
		if ( ! is_int( $event_id ) || $event_id < 1 ) {
			return new WP_Error( 'invalid_booking_links', __( 'Die Terminverknüpfungen sind beschädigt. Bitte informiere die Administration.', 'ps-pen-paper' ) );
		}
	}
	return $value;
}

function ps_pen_paper_booking_can_view_scope( $scope ) {
	if ( ! ps_pen_paper_can_access_site() || ! $scope ) { return false; }
	$round = ps_pen_paper_play_round( $scope->ID );
	if ( is_wp_error( $round ) ) { error_log( 'PS Pen & Paper: invalid round in event schedule.' ); return false; }
	if ( ! $round ) { return ps_pen_paper_can_view_campaign( $scope ); }
	$source = ps_pen_paper_get_campaign( $round['source_id'] );
	return ps_pen_paper_can_view_campaign( $source ) && ( 'solo' !== $round['mode'] ||
		$round['host_id'] === get_current_user_id() || ps_pen_paper_can_manage_campaign( $source ) );
}

function ps_pen_paper_booking_event_visible( $event ) {
	return $event instanceof WP_Post && 'psource_event' === $event->post_type &&
		( 'publish' === $event->post_status || ( 'private' === $event->post_status && current_user_can( 'read_post', $event->ID ) ) ) &&
		! post_password_required( $event );
}

function ps_pen_paper_booking_dates( $event ) {
	$starts = $event->get_start_dates(); $ends = $event->get_end_dates();
	$first = null; $last = null;
	if ( $starts && array_keys( $starts ) === array_keys( $ends ) ) {
		foreach ( $starts as $key => $date ) {
			$start = is_string( $date ) ? $event->get_start_timestamp( $key ) : false;
			$end = is_string( $ends[ $key ] ) ? $event->get_end_timestamp( $key ) : false;
			if ( false === $start || false === $end || $end < $start ) { $first = null; break; }
			$first = null === $first ? $start : min( $first, $start );
			$last = null === $last ? $end : max( $last, $end );
		}
	}
	if ( null === $first ) {
		return new WP_Error( 'booking_dates_missing', __( 'Bei einer Veranstaltung fehlen gültige Start- oder Endzeiten. Bitte korrigiere den Termin in PS Events.', 'ps-pen-paper' ) );
	}
	return array( 'html' => Eab_Template::get_event_dates( $event ), 'start' => $first, 'end' => $last );
}

function ps_pen_paper_change_booking_link( $id, $event_id, $action, $revision ) {
	if ( ! is_int( $id ) || $id < 1 || ! is_int( $event_id ) || $event_id < 1 ||
		! in_array( $action, array( 'link', 'unlink' ), true ) || ! is_string( $revision ) ) {
		return new WP_Error( 'invalid_booking_link', __( 'Bitte wähle einen gültigen Termin und eine gültige Aktion.', 'ps-pen-paper' ) );
	}
	return ps_pen_paper_scope_lock( $id, function () use ( $id, $event_id, $action, $revision ) {
		$scope = ps_pen_paper_get_campaign( $id );
		if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $scope ) ) {
			return new WP_Error( 'forbidden', __( 'Nur die Spielleitung darf Termine verknüpfen oder lösen.', 'ps-pen-paper' ) );
		}
		if ( ! ps_pen_paper_bookings_available() ) {
			return new WP_Error( 'bookings_unavailable', __( 'Die Terminverwaltung ist momentan nicht verfügbar. Die Spielrunde bleibt unverändert.', 'ps-pen-paper' ) );
		}
		$before = ps_pen_paper_booking_links( $id );
		if ( is_wp_error( $before ) ) { return $before; }
		if ( (string) $before['revision'] !== $revision ) {
			return new WP_Error( 'booking_link_conflict', __( 'Die Terminverknüpfungen wurden inzwischen geändert. Bitte lade die Seite neu.', 'ps-pen-paper' ) );
		}
		$linked = in_array( $event_id, $before['events'], true );
		$event = get_post( $event_id );
		if ( 'link' === $action && ( ! $event || 'psource_event' !== $event->post_type ||
			! in_array( $event->post_status, array( 'publish', 'private' ), true ) || ! current_user_can( 'edit_post', $event_id ) ) ) {
			return new WP_Error( 'forbidden', __( 'Du kannst nur veröffentlichte oder private Veranstaltungen verknüpfen, die du bearbeiten darfst.', 'ps-pen-paper' ) );
		}
		if ( 'link' === $action ) {
			$dates = ps_pen_paper_booking_dates( new Eab_EventModel( $event ) );
			if ( is_wp_error( $dates ) ) { return $dates; }
		}
		if ( ( 'link' === $action && $linked ) || ( 'unlink' === $action && ! $linked ) ) {
			return new WP_Error( 'booking_link_conflict', __( 'Die Terminverknüpfungen wurden inzwischen geändert. Bitte lade die Seite neu.', 'ps-pen-paper' ) );
		}
		$after = array( 'revision' => $before['revision'] + 1, 'events' => $before['events'] );
		if ( 'link' === $action ) { $after['events'][] = $event_id; }
		else { $after['events'] = array_values( array_diff( $after['events'], array( $event_id ) ) ); }
		$saved = $before['revision'] ? update_post_meta( $id, '_pspp_booking_links', $after, $before ) :
			add_post_meta( $id, '_pspp_booking_links', $after, true );
		return $saved ? true : new WP_Error( 'booking_link_failed', __( 'Die Terminverknüpfung konnte nicht gespeichert werden. Bitte lade den aktuellen Stand neu.', 'ps-pen-paper' ) );
	} );
}

function ps_pen_paper_booking_events( $scope ) {
	global $wpdb;
	if ( ! ps_pen_paper_bookings_available() || ! ps_pen_paper_booking_can_view_scope( $scope ) ) { return array(); }
	$links = ps_pen_paper_booking_links( $scope->ID );
	if ( is_wp_error( $links ) ) { return $links; }
	$events = array();
	foreach ( $links['events'] as $id ) {
		$post = get_post( $id );
		if ( ! ps_pen_paper_booking_event_visible( $post ) ) { continue; }
		$event = new Eab_EventModel( $post );
		$dates = ps_pen_paper_booking_dates( $event );
		if ( is_wp_error( $dates ) ) { return $dates; }
		$status = $event->get_user_booking_status( get_current_user_id() );
		if ( $wpdb->last_error ) {
			error_log( 'PS Pen & Paper: event booking lookup failed #' . $id );
			return new WP_Error( 'booking_read_failed', __( 'Deine Terminantwort konnte nicht geladen werden. Bitte öffne die Veranstaltung direkt.', 'ps-pen-paper' ) );
		}
		$statuses = Eab_Template::get_rsvp_status_list();
		$statuses['paid'] = __( 'Teilnahme bezahlt', 'ps-pen-paper' );
		if ( null !== $status && false !== $status && '' !== $status && ! isset( $statuses[ $status ] ) ) {
			return new WP_Error( 'invalid_booking_status', __( 'Deine Terminantwort hat einen unbekannten Status. Bitte öffne die Veranstaltung direkt.', 'ps-pen-paper' ) );
		}
		$events[] = array( 'post' => $post, 'scope' => $scope, 'dates' => $dates['html'],
			'start' => $dates['start'], 'end' => $dates['end'],
			'status' => $event->get_status(), 'response' => $status ? $statuses[ $status ] : __( 'Noch keine Antwort', 'ps-pen-paper' ) );
	}
	usort( $events, static function ( $a, $b ) { return $a['start'] <=> $b['start']; } );
	return $events;
}

function ps_pen_paper_booking_agenda() {
	if ( ! ps_pen_paper_bookings_available() ) { return array(); }
	$events = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private',
		'meta_key' => '_pspp_booking_links', 'posts_per_page' => -1 ) ) as $scope ) {
		$rows = ps_pen_paper_booking_events( $scope );
		if ( is_wp_error( $rows ) ) { return $rows; }
		foreach ( $rows as $row ) {
			if ( $row['end'] >= eab_current_time() ) { $events[] = $row; }
		}
	}
	usort( $events, static function ( $a, $b ) { return $a['start'] <=> $b['start']; } );
	return $events;
}
