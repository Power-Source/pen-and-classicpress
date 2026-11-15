<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_record_post( $id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID=%d", (int) $id ) );
	if ( $wpdb->last_error ) { error_log( 'PS Pen & Paper: record lookup failed.' ); return null; }
	if ( ! $row ) { return null; }
	if ( 0 === strpos( $row->post_type, 'pspp_' ) ) {
		$scope_id = in_array( $row->post_type, array( 'pspp_campaign', 'pspp_universe' ), true ) ? (int) $row->ID : (int) $row->post_parent;
		if ( $scope_id && ! ps_pen_paper_scope_observe( $scope_id ) ) { return null; }
	}
	wp_cache_delete( (int) $id, 'post_meta' );
	return new WP_Post( sanitize_post( $row, 'raw' ) );
}

function ps_pen_paper_scope_epoch( $id ) {
	global $wpdb;
	$values = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta WHERE post_id=%d AND meta_key=%s", $id, '_pspp_import_epoch' ) );
	if ( $wpdb->last_error || count( $values ) > 1 || ( $values && ! wp_is_uuid( $values[0] ) ) ) {
		return new WP_Error( 'scope_state_failed', __( 'Der Wiederherstellungsstand der Runde konnte nicht sicher geprüft werden. Bitte informiere die Administration.', 'ps-pen-paper' ) );
	}
	return $values ? $values[0] : '';
}

function ps_pen_paper_scope_observe( $id, $reset = false ) {
	static $epochs = array();
	$epoch = ps_pen_paper_scope_epoch( $id );
	if ( is_wp_error( $epoch ) ) { error_log( 'PS Pen & Paper: invalid scope epoch #' . (int) $id ); return false; }
	if ( $reset || ! array_key_exists( $id, $epochs ) ) { $epochs[ $id ] = $epoch; }
	return $epochs[ $id ] === $epoch;
}

function ps_pen_paper_scope_lock( $id, $callback ) {
	global $wpdb;
	static $active = array();
	$name = 'pspp_scope_' . md5( DB_NAME . ':' . $wpdb->posts . ':' . (int) $id );
	if ( isset( $active[ $name ] ) ) { return $callback(); }
	$locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $name ) );
	if ( '1' !== $locked ) {
		return new WP_Error( 'scope_busy', __( 'Diese Runde wird gerade geändert oder wiederhergestellt. Bitte lade sie danach neu; es wurde nichts übernommen.', 'ps-pen-paper' ) );
	}
	$active[ $name ] = true;
	try { $result = $callback(); }
	finally {
		$released = $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		unset( $active[ $name ] );
	}
	if ( '1' !== $released ) {
		error_log( 'PS Pen & Paper: scope lock release failed #' . (int) $id );
		return new WP_Error( 'scope_recovery_required', __( 'Die Rundensperre konnte nicht bestätigt freigegeben werden. Änderungen können bereits gespeichert sein. Bitte informiere die Administration und wiederhole den Vorgang nicht.', 'ps-pen-paper' ) );
	}
	return $result;
}

function ps_pen_paper_scope_queries( $query ) {
	foreach ( (array) $query->get( 'post_type' ) as $type ) {
		if ( is_string( $type ) && 0 === strpos( $type, 'pspp_' ) ) { $query->set( 'cache_results', false ); break; }
	}

}

function ps_pen_paper_scope_write( $scope, $callback ) {
	$id = $scope instanceof WP_Post ? $scope->ID : $scope;
	return is_int( $id ) && $id > 0 ? ps_pen_paper_scope_lock( $id, $callback ) : $callback();
}
