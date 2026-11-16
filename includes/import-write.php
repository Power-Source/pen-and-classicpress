<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_restore_error() {
	return new WP_Error( 'restore_failed', __( 'Die Wiederherstellung konnte nicht vollständig gespeichert werden. Es wurde kein neuer Spielstand übernommen.', 'ps-pen-paper' ) );
}

function ps_pen_paper_restore_fingerprint( $parsed, $options, $plan ) {
	ksort( $options['users'] ); ksort( $options['bases'] );
	return hash( 'sha256', serialize( array( $parsed['archive']['payload_sha256'], $options, $plan['target_hash'], $plan['universe_hash'] ) ) );
}

function ps_pen_paper_restore_lock( $token, $callback ) {
	global $wpdb;
	$name = 'pspp_restore_' . md5( DB_NAME . ':' . $wpdb->posts . ':' . get_current_user_id() . ':' . $token );
	if ( '1' !== $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $name ) ) ) {
		return new WP_Error( 'restore_busy', __( 'Diese Wiederherstellung läuft bereits. Bitte warte und lade die Seite neu.', 'ps-pen-paper' ) );
	}
	try { $result = $callback(); }
	finally { $released = $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); }
	if ( '1' !== $released ) {
		error_log( 'PS Pen & Paper: restore lock release failed.' );
		return new WP_Error( 'restore_recovery_required', __( 'Der Abschluss konnte nicht sicher bestätigt werden. Bitte informiere die Administration und starte keine zweite Wiederherstellung.', 'ps-pen-paper' ) );
	}
	return $result;
}

function ps_pen_paper_restore_receipt( $token ) {
	if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9]{32}$/D', $token ) ) { return null; }
	global $wpdb;
	$hash = hash( 'sha256', $token );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT p.ID,m.meta_value FROM $wpdb->posts p JOIN $wpdb->postmeta m ON m.post_id=p.ID
		WHERE p.post_type='pspp_campaign' AND p.post_status IN ('private','draft') AND m.meta_key='_pspp_import_receipt' AND m.meta_value LIKE %s",
		'%' . $wpdb->esc_like( $hash ) . '%'
	), ARRAY_A );
	if ( $wpdb->last_error ) { return ps_pen_paper_restore_error(); }
	foreach ( $rows as $row ) {
		$receipt = json_decode( $row['meta_value'], true );
		if ( ! is_array( $receipt ) || ! isset( $receipt['token_hash'], $receipt['owner_id'], $receipt['blog_id'], $receipt['confirmation_hash'], $receipt['backup_id'], $receipt['campaign_id'] ) ||
			! is_int( $receipt['campaign_id'] ) || $receipt['campaign_id'] < 1 ) { return ps_pen_paper_restore_error(); }
		if ( $receipt['token_hash'] !== $hash || $receipt['owner_id'] !== get_current_user_id() || $receipt['blog_id'] !== get_current_blog_id() ) { continue; }
		$campaign = ps_pen_paper_get_campaign( $receipt['campaign_id'] );
		if ( ! $campaign || ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return new WP_Error( 'forbidden', __( 'Die wiederhergestellte Kampagne ist für dich nicht mehr verfügbar.', 'ps-pen-paper' ) ); }
		return $receipt;
	}
	return null;
}

function ps_pen_paper_import_insert_post( $record, $parent_id, $author_id, $status = 'draft' ) {
	global $wpdb;
	$values = array( 'post_type' => $record['type'], 'post_parent' => $parent_id, 'post_author' => $author_id, 'post_status' => $status,
		'post_title' => $record['title'], 'post_content' => $record['content'], 'post_excerpt' => $record['excerpt'],
		'post_mime_type' => $record['mime_type'], 'menu_order' => $record['menu_order'], 'post_date' => get_date_from_gmt( $record['date_gmt'] ),
		'post_modified' => get_date_from_gmt( $record['modified_gmt'] ), 'post_date_gmt' => $record['date_gmt'], 'post_modified_gmt' => $record['modified_gmt'],
		'comment_status' => 'closed', 'ping_status' => 'closed' );
	if ( false === $wpdb->insert( $wpdb->posts, $values ) ) { return ps_pen_paper_restore_error(); }
	return (int) $wpdb->insert_id;
}

function ps_pen_paper_import_insert_meta( $id, $key, $value ) {
	global $wpdb;
	return false !== $wpdb->insert( $wpdb->postmeta, array( 'post_id' => $id, 'meta_key' => $key, 'meta_value' => maybe_serialize( $value ) ) );
}

function ps_pen_paper_import_identity_map( $parsed, $options ) {
	$map = array();
	if ( 'replace' === $options['operation'] ) {
		$source = $parsed['archive']['payload']['source']['campaign_id'];
		$map[ $source ] = $options['target_id'];
	}
	if ( 'bind' === $options['universe_mode'] ) {
		$link = ps_pen_paper_import_meta( $parsed, $parsed['archive']['payload']['source']['campaign_id'], '_pspp_universe_link' );
		if ( ! empty( $link['universe_id'] ) ) { $map[ $link['universe_id'] ] = $options['universe_id']; }
		foreach ( $options['bases'] as $source => $target ) { $map[ $source ] = $target; }
	}
	$used = array_fill_keys( array_values( $map ), true ); $next = 1;
	foreach ( $parsed['records'] as $id => $record ) {
		if ( isset( $map[ $id ] ) ) { continue; }
		$destination = $id;
		if ( isset( $used[ $destination ] ) ) {
			while ( isset( $used[ $next ] ) ) { $next++; }
			$destination = $next++;
		}
		$map[ $id ] = $destination; $used[ $destination ] = true;
	}
	return $map;
}

function ps_pen_paper_restore_campaign( $token, $fingerprint, $archive_unknown, $access_policy ) {
	if ( ! function_exists( 'ps_pen_paper_prepare_import' ) ) {
		return new WP_Error( 'restore_unavailable', __( 'Das Importmodul ist noch nicht vollständig geladen. Bitte informiere die Administration.', 'ps-pen-paper' ) );
	}
	if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9]{32}$/D', $token ) ||
		! is_string( $fingerprint ) || ! preg_match( '/^[a-f0-9]{64}$/D', $fingerprint ) ||
		! is_bool( $archive_unknown ) || ! in_array( $access_policy, array( 'private', 'preserve' ), true ) || ! ps_pen_paper_can_preview_import() ) {
		return new WP_Error( 'invalid_restore', __( 'Die Wiederherstellung benötigt eine gültige private Vorschau und ausdrückliche Bestätigung.', 'ps-pen-paper' ) );
	}
	$confirmation = hash( 'sha256', $fingerprint . ':' . (int) $archive_unknown . ':' . $access_policy );
	return ps_pen_paper_restore_lock( $token, function () use ( $token, $fingerprint, $archive_unknown, $access_policy, $confirmation ) {
		$previous = ps_pen_paper_restore_receipt( $token );
		if ( is_wp_error( $previous ) ) { return $previous; }
		if ( $previous ) {
			return hash_equals( $previous['confirmation_hash'], $confirmation ) ? $previous :
				new WP_Error( 'restore_used', __( 'Diese Vorschau wurde bereits verwendet. Für einen anderen Vorgang lade die Datei erneut hoch.', 'ps-pen-paper' ) );
		}
		$preview = ps_pen_paper_get_import_preview( $token );
		if ( is_wp_error( $preview ) ) { return $preview; }
		$parsed = ps_pen_paper_parse_import( $preview['json'] );
		if ( is_wp_error( $parsed ) ) { return $parsed; }
		$options = $preview['options'];
		$scopes = array_filter( array( $options['target_id'], 'bind' === $options['universe_mode'] ? $options['universe_id'] : 0 ) );
		sort( $scopes, SORT_NUMERIC );
		$write = function () use ( $token, $fingerprint, $archive_unknown, $access_policy, $confirmation, $parsed, $options ) {
			return ps_pen_paper_restore_campaign_locked( $token, $fingerprint, $archive_unknown, $access_policy, $confirmation, $parsed, $options );
		};
		foreach ( array_reverse( array_unique( $scopes ) ) as $id ) {
			$next = $write; $write = function () use ( $id, $next ) { return ps_pen_paper_scope_lock( $id, $next ); };
		}
		return $write();
	} );
}

function ps_pen_paper_restore_campaign_locked( $token, $fingerprint, $archive_unknown, $access_policy, $confirmation, $parsed, $options ) {
	global $wpdb;
	$plan = ps_pen_paper_import_configuration( $parsed, $options );
	if ( is_wp_error( $plan ) ) { return $plan; }
	if ( $plan['conflicts'] ) { return new WP_Error( 'restore_conflict', implode( "\n", $plan['conflicts'] ) ); }
	if ( ! hash_equals( $fingerprint, ps_pen_paper_restore_fingerprint( $parsed, $options, $plan ) ) ) {
		return new WP_Error( 'restore_changed', __( 'Ziel, Zuordnungen oder Universumsdaten wurden seit der Vorschau geändert. Bitte prüfe und bestätige den aktuellen Stand erneut.', 'ps-pen-paper' ) );
	}
	$identity = ps_pen_paper_import_identity_map( $parsed, $options );
	$prepared = ps_pen_paper_prepare_import( $parsed, $options, $identity, $archive_unknown );
	if ( is_wp_error( $prepared ) ) { return $prepared; }
	$source_id = $parsed['archive']['payload']['source']['campaign_id'];
	$creating = 'copy' === $options['operation'];
	$target_id = $options['target_id'];
	$allocated = array(); $touched = array(); $backup_id = 0;
	if ( $creating ) {
		$target_id = ps_pen_paper_import_insert_post( $parsed['records'][ $source_id ], 0, get_current_user_id() );
		if ( is_wp_error( $target_id ) ) { return $target_id; }
	}
	$result = ps_pen_paper_atomic_event( $target_id, function () use ( $parsed, $options, $source_id, $target_id, $creating, $archive_unknown, $access_policy, $token, $confirmation, $fingerprint, &$allocated, &$touched, &$backup_id ) {
		global $wpdb;
		$plan = ps_pen_paper_import_configuration( $parsed, $options );
		if ( is_wp_error( $plan ) ) { return $plan; }
		if ( $plan['conflicts'] || ! hash_equals( $fingerprint, ps_pen_paper_restore_fingerprint( $parsed, $options, $plan ) ) ) {
			return new WP_Error( 'restore_changed', __( 'Der geprüfte Stand ist nicht mehr aktuell. Die Wiederherstellung wurde nicht übernommen.', 'ps-pen-paper' ) );
		}
		$target = ps_pen_paper_record_post( $target_id );
		if ( ! $target || ( ! $creating && ! ps_pen_paper_can_manage_campaign( $target ) ) ) { return ps_pen_paper_restore_error(); }
		$owner = $creating ? get_current_user_id() : (int) $target->post_author;
		$map = array( $source_id => $target_id );
		if ( 'bind' === $options['universe_mode'] ) {
			$link = ps_pen_paper_import_meta( $parsed, $source_id, '_pspp_universe_link' );
			if ( ! empty( $link['universe_id'] ) ) { $map[ $link['universe_id'] ] = $options['universe_id']; }
			foreach ( $options['bases'] as $source => $target_base ) { $map[ $source ] = $target_base; }
		}
		foreach ( $parsed['records'] as $id => $record ) {
			if ( isset( $map[ $id ] ) || ( 'bind' === $options['universe_mode'] && $parsed['universe_id'] && $record['parent_id'] === $parsed['universe_id'] ) ) { continue; }
			$mapped = ps_pen_paper_import_insert_post( $record, 0, get_current_user_id() );
			if ( is_wp_error( $mapped ) ) { return $mapped; }
			$map[ $id ] = $mapped; $allocated[] = $mapped;
		}
		$prepared = ps_pen_paper_prepare_import( $parsed, $options, $map, $archive_unknown );
		if ( is_wp_error( $prepared ) ) { return $prepared; }
		if ( ! $creating ) {
			$old_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_parent=%d AND post_type IN (" . implode( ',', array_fill( 0, count( ps_pen_paper_campaign_record_types() ), '%s' ) ) . ') FOR UPDATE',
				array_merge( array( $target_id ), ps_pen_paper_campaign_record_types() )
			) );
			if ( $wpdb->last_error ) { return ps_pen_paper_restore_error(); }
			$backup = ps_pen_paper_export_campaign( $target_id, 'local' );
			if ( is_wp_error( $backup ) ) { return $backup; }
			$backup_record = $parsed['records'][ $source_id ];
			$backup_record['title'] = $target->post_title; $backup_record['content'] = json_encode( $backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$backup_record['date_gmt'] = current_time( 'mysql', true ); $backup_record['modified_gmt'] = $backup_record['date_gmt'];
			if ( false === $backup_record['content'] ) { return ps_pen_paper_restore_error(); }
			$backup_id = ps_pen_paper_import_insert_post( $backup_record, 0, $owner );
			if ( is_wp_error( $backup_id ) ) { return $backup_id; }
			$allocated[] = $backup_id;
			if ( false === $wpdb->query( $wpdb->prepare(
				"INSERT INTO $wpdb->postmeta (post_id,meta_key,meta_value) SELECT %d,meta_key,meta_value FROM $wpdb->postmeta WHERE post_id=%d AND meta_key<>'_pspp_import_backup'",
				$backup_id, $target_id
			) ) || ! ps_pen_paper_import_insert_meta( $backup_id, '_pspp_import_backup', array(
				'owners' => array_values( array_unique( array( $owner, get_current_user_id() ) ) ), 'campaign_id' => $target_id,
				'payload_sha256' => $backup['payload_sha256'],
			) ) ) { return ps_pen_paper_restore_error(); }
			foreach ( $old_ids as $old_id ) {
				$touched[] = (int) $old_id;
				if ( false === $wpdb->update( $wpdb->posts, array( 'post_parent' => $backup_id ), array( 'ID' => (int) $old_id ) ) ) { return ps_pen_paper_restore_error(); }
			}
			if ( false === $wpdb->delete( $wpdb->postmeta, array( 'post_id' => $target_id ) ) ) { return ps_pen_paper_restore_error(); }
		}
		foreach ( $prepared as $source => $record ) {
			$id = $map[ $source ]; $touched[] = $id;
			$author = $source === $source_id ? $owner : ( 'pspp_universe' === $record['type'] ? get_current_user_id() : $record['author_id'] );
			if ( false === $wpdb->update( $wpdb->posts, array(
				'post_parent' => $record['parent_id'], 'post_author' => $author, 'post_status' => $record['status'],
				'post_title' => $record['title'], 'post_content' => $record['content'], 'post_excerpt' => $record['excerpt'],
				'post_mime_type' => $record['mime_type'], 'menu_order' => $record['menu_order'],
				'post_date_gmt' => $record['date_gmt'], 'post_modified_gmt' => $record['modified_gmt'],
				'post_date' => get_date_from_gmt( $record['date_gmt'] ), 'post_modified' => get_date_from_gmt( $record['modified_gmt'] ),
			), array( 'ID' => $id ) ) ) { return ps_pen_paper_restore_error(); }
			foreach ( $record['meta'] as $entry ) {
				if ( ! ps_pen_paper_import_insert_meta( $id, $entry['key'], $entry['value'] ) ) { return ps_pen_paper_restore_error(); }
			}
		}
		$existing_gms = array();
		foreach ( $prepared[ $source_id ]['meta'] as $entry ) { if ( '_pspp_gm' === $entry['key'] ) { $existing_gms[] = (int) $entry['value']; } }
		foreach ( array_unique( array( get_current_user_id(), $prepared[ $source_id ]['author_id'] ) ) as $gm ) {
			if ( $gm > 0 && ! in_array( $gm, $existing_gms, true ) && ! ps_pen_paper_import_insert_meta( $target_id, '_pspp_gm', (string) $gm ) ) { return ps_pen_paper_restore_error(); }
		}
		if ( 'private' === $access_policy ) {
			$access = ps_pen_paper_get_access( $target_id );
			if ( is_wp_error( $access ) ) { return $access; }
			if ( false === $wpdb->delete( $wpdb->postmeta, array( 'post_id' => $target_id, 'meta_key' => '_pspp_access' ) ) ||
				! ps_pen_paper_import_insert_meta( $target_id, '_pspp_access', array( 'revision' => $access['revision'] + 1, 'visibility' => 'private', 'admission' => 'closed', 'teaser' => '' ) ) ) { return ps_pen_paper_restore_error(); }
		}
		$event = array( 'version' => 1, 'kind' => 'campaign_restored', 'visibility' => 'gm', 'recipient_id' => 0,
			'actor_name' => wp_get_current_user()->display_name, 'object_id' => $target_id, 'before' => array( 'revision' => 0 ),
			'after' => array( 'revision' => 1, 'source_hash' => $parsed['archive']['payload_sha256'], 'backup_id' => $backup_id ),
			'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $target_id )['revision'] );
		$event_record = $parsed['records'][ $source_id ]; $event_record['type'] = 'pspp_event'; $event_record['content'] = ''; $event_record['title'] = 'PS Pen & Paper Restore';
		$event_record['date_gmt'] = current_time( 'mysql', true ); $event_record['modified_gmt'] = $event_record['date_gmt'];
		$event_id = ps_pen_paper_import_insert_post( $event_record, $target_id, get_current_user_id(), 'private' );
		if ( is_wp_error( $event_id ) ) { return $event_id; }
		$allocated[] = $event_id;
		foreach ( array( '_pspp_event' => $event, '_pspp_visibility' => 'gm', '_pspp_recipient' => '0', '_pspp_actor' => (string) get_current_user_id() ) as $key => $value ) {
			if ( ! ps_pen_paper_import_insert_meta( $event_id, $key, $value ) ) { return ps_pen_paper_restore_error(); }
		}
		$receipt = array( 'token_hash' => hash( 'sha256', $token ), 'owner_id' => get_current_user_id(), 'blog_id' => get_current_blog_id(),
			'confirmation_hash' => $confirmation, 'backup_id' => $backup_id, 'campaign_id' => $target_id );
		if ( ! ps_pen_paper_import_insert_meta( $target_id, '_pspp_import_receipt', json_encode( $receipt ) ) ||
			! ps_pen_paper_import_insert_meta( $target_id, '_pspp_import_epoch', wp_generate_uuid4() ) ) { return ps_pen_paper_restore_error(); }
		return $event_id;
	} );
	foreach ( array_unique( array_merge( array( $target_id ), $allocated, $touched ) ) as $id ) { clean_post_cache( $id ); }
	if ( true !== $result ) {
		if ( $creating && is_wp_error( $result ) && ! in_array( $result->get_error_code(), array( 'event_recovery_required', 'scope_recovery_required' ), true ) ) {
			if ( ! wp_delete_post( $target_id, true ) ) { error_log( 'PS Pen & Paper: restore draft cleanup failed #' . $target_id ); return new WP_Error( 'cleanup_failed', __( 'Ein Wiederherstellungsentwurf konnte nicht entfernt werden. Bitte informiere die Administration.', 'ps-pen-paper' ) ); }
		}
		return $result;
	}
	ps_pen_paper_scope_observe( $target_id, true );
	return array( 'campaign_id' => $target_id, 'backup_id' => $backup_id, 'confirmation_hash' => $confirmation );
}

function ps_pen_paper_get_import_backup( $id ) {
	$post = ps_pen_paper_record_post( $id ); $backup = get_post_meta( $id, '_pspp_import_backup', true );
	if ( ! ps_pen_paper_can_access_site() || ! $post || 'draft' !== $post->post_status || 'pspp_campaign' !== $post->post_type ||
		! is_array( $backup ) || ! isset( $backup['owners'], $backup['payload_sha256'] ) || ! is_array( $backup['owners'] ) ||
		( ! current_user_can( 'manage_options' ) && ! in_array( get_current_user_id(), $backup['owners'], true ) ) ) {
		return new WP_Error( 'forbidden', __( 'Diese private Zielsicherung ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	$archive = json_decode( $post->post_content, true );
	if ( ! is_array( $archive ) || ! isset( $archive['payload'], $archive['payload_sha256'] ) ||
		! is_string( $backup['payload_sha256'] ) || ! is_string( $archive['payload_sha256'] ) ||
		! hash_equals( $backup['payload_sha256'], $archive['payload_sha256'] ) ||
		! hash_equals( $backup['payload_sha256'], hash( 'sha256', json_encode( $archive['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ) ) {
		return new WP_Error( 'invalid_backup', __( 'Die private Zielsicherung ist beschädigt. Bitte informiere die Administration.', 'ps-pen-paper' ) );
	}
	return $post->post_content;
}

function ps_pen_paper_handle_restore_action( $operation ) {
	$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	if ( $page_id && ! ps_pen_paper_frontend_page( $page_id ) ) { ps_pen_paper_fail( __( 'Die Spielbereich-Seite ist nicht verfügbar.', 'ps-pen-paper' ) ); }
	if ( 'download_import_backup' === $operation ) {
		$id = ps_pen_paper_posted_id( 'backup_id' ); check_admin_referer( 'pspp_restore_backup_' . $id );
		$json = ps_pen_paper_get_import_backup( $id );
		if ( is_wp_error( $json ) ) { ps_pen_paper_fail( $json->get_error_message() ); }
		nocache_headers(); header( 'Cache-Control: no-store, private' ); header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="pspp-before-restore-' . $id . '.json"' ); header( 'X-Content-Type-Options: nosniff' );
		echo $json; exit;
	}
	$token = ps_pen_paper_posted_text( 'import_token' ); $fingerprint = ps_pen_paper_posted_text( 'restore_fingerprint' );
	check_admin_referer( 'pspp_restore_' . $token . '_' . $fingerprint );
	if ( 'confirmed' !== ps_pen_paper_posted_text( 'restore_confirmation' ) ) { ps_pen_paper_fail( __( 'Bitte bestätige Ziel, Kontenzuordnungen und die Übernahme privater Spielstände ausdrücklich.', 'ps-pen-paper' ) ); }
	$result = ps_pen_paper_restore_campaign( $token, $fingerprint, '1' === ps_pen_paper_posted_text( 'archive_unknown' ), ps_pen_paper_posted_text( 'restore_access' ) );
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	wp_safe_redirect( add_query_arg( array( 'pspp_import_preview' => $token, 'pspp_restored' => $result['campaign_id'] ), ps_pen_paper_dashboard_url( 'settings', $page_id ) ) . '#pspp-import' ); exit;
}
