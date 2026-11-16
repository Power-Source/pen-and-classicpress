<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_import_error() {
	return new WP_Error( 'invalid_import', __( 'Die Sicherungsdatei ist beschädigt, enthält nicht unterstützte Daten oder passt nicht zum Exportformat. Es wurden keine Spielstände geändert.', 'ps-pen-paper' ) );
}

function ps_pen_paper_import_limit() {
	$limit = (int) wp_max_upload_size();
	$memory = ini_get( 'memory_limit' );
	if ( '-1' !== $memory ) {
		$available = max( 0, wp_convert_hr_to_bytes( $memory ) - memory_get_usage( true ) );
		$limit = min( $limit, (int) floor( $available / 32 ) );
	}
	return max( 0, $limit );
}

function ps_pen_paper_import_keys( $value, $keys ) {
	return is_array( $value ) && count( $value ) === count( $keys ) && ! array_diff( array_keys( $value ), $keys );
}

function ps_pen_paper_import_tree( $value, $depth = 0 ) {
	if ( $depth > 64 || is_object( $value ) || is_resource( $value ) || ( is_float( $value ) && ! is_finite( $value ) ) ) { return false; }
	if ( is_array( $value ) ) {
		foreach ( $value as $part ) { if ( ! ps_pen_paper_import_tree( $part, $depth + 1 ) ) { return false; } }
	}
	return true;
}

function ps_pen_paper_import_meta( $parsed, $id, $key ) {
	return isset( $parsed['meta'][ $id ][ $key ] ) ? $parsed['meta'][ $id ][ $key ][0] : null;
}

function ps_pen_paper_import_serialized_value( $raw, &$offset, $depth = 0 ) {
	if ( $depth > 64 || $offset >= strlen( $raw ) ) { return false; }
	if ( 'N;' === substr( $raw, $offset, 2 ) ) { $offset += 2; return true; }
	if ( preg_match( '/\Gi:(-?\d+);/', $raw, $match, 0, $offset ) ) {
		if ( (string) (int) $match[1] !== $match[1] ) { return false; }
		$offset += strlen( $match[0] ); return true;
	}
	if ( preg_match( '/\G(?:b:[01]|d:[-+0-9.eE]+);/', $raw, $match, 0, $offset ) ) {
		$offset += strlen( $match[0] ); return true;
	}
	if ( preg_match( '/\Gs:(0|[1-9]\d*):"/', $raw, $match, 0, $offset ) ) {
		$length = (int) $match[1];
		if ( (string) $length !== $match[1] || $length > strlen( $raw ) - $offset - strlen( $match[0] ) - 2 ) { return false; }
		$offset += strlen( $match[0] ) + $length;
		if ( '";' !== substr( $raw, $offset, 2 ) ) { return false; }
		$offset += 2; return true;
	}
	if ( preg_match( '/\Ga:(0|[1-9]\d*):\{/', $raw, $match, 0, $offset ) ) {
		$count = (int) $match[1];
		if ( (string) $count !== $match[1] || $count > strlen( $raw ) - $offset ) { return false; }
		$offset += strlen( $match[0] );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! in_array( substr( $raw, $offset, 1 ), array( 'i', 's' ), true ) ||
				! ps_pen_paper_import_serialized_value( $raw, $offset, $depth + 1 ) ||
				! ps_pen_paper_import_serialized_value( $raw, $offset, $depth + 1 ) ) { return false; }
		}
		if ( '}' !== substr( $raw, $offset, 1 ) ) { return false; }
		$offset++; return true;
	}
	return false;
}

function ps_pen_paper_parse_import( $json ) {
	if ( ! is_string( $json ) || '' === $json || strlen( $json ) > ps_pen_paper_import_limit() ) {
		return new WP_Error( 'import_size', __( 'Die Sicherungsdatei fehlt oder überschreitet die Upload-/Speichergrenze dieses Hosts. Bitte prüfe die PHP-Limits mit der Administration.', 'ps-pen-paper' ) );
	}
	$archive = json_decode( $json, true, 64 );
	if ( JSON_ERROR_NONE !== json_last_error() ||
		! ps_pen_paper_import_keys( $archive, array( 'format', 'format_version', 'plugin_version', 'created_at', 'payload', 'payload_sha256' ) ) ||
		'ps-pen-paper-campaign' !== $archive['format'] || 1 !== $archive['format_version'] ||
		! is_string( $archive['plugin_version'] ) || ! is_string( $archive['created_at'] ) ||
		! is_string( $archive['payload_sha256'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $archive['payload_sha256'] ) ) { return ps_pen_paper_import_error(); }
	$payload = $archive['payload'];
	if ( ! ps_pen_paper_import_keys( $payload, array( 'source', 'mode', 'meta_encoding', 'records', 'dependencies', 'external_data' ) ) ||
		! ps_pen_paper_import_keys( $payload['source'], array( 'site_url', 'blog_id', 'campaign_id' ) ) ||
		! is_string( $payload['source']['site_url'] ) || ! filter_var( $payload['source']['site_url'], FILTER_VALIDATE_URL ) ||
		! in_array( wp_parse_url( $payload['source']['site_url'], PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ||
		null !== wp_parse_url( $payload['source']['site_url'], PHP_URL_USER ) ||
		! is_int( $payload['source']['blog_id'] ) || $payload['source']['blog_id'] < 1 ||
		! is_int( $payload['source']['campaign_id'] ) || $payload['source']['campaign_id'] < 1 ||
		! in_array( $payload['mode'], array( 'local', 'complete' ), true ) || 'wordpress-raw' !== $payload['meta_encoding'] ||
		! is_array( $payload['records'] ) || ! $payload['records'] || array_values( $payload['records'] ) !== $payload['records'] ||
		! is_array( $payload['dependencies'] ) || array_values( $payload['dependencies'] ) !== $payload['dependencies'] ||
		! ps_pen_paper_import_keys( $payload['external_data'], array( 'user_accounts', 'user_assignment', 'ps_community', 'ps_chat', 'ps_wiki' ) ) ) {
		return ps_pen_paper_import_error();
	}
	$encoded = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( false === $encoded || ! hash_equals( $archive['payload_sha256'], hash( 'sha256', $encoded ) ) ||
		! ps_pen_paper_import_tree( $payload ) ) { return ps_pen_paper_import_error(); }
	$parsed = array( 'archive' => $archive, 'records' => array(), 'meta' => array(), 'universe_id' => 0,
		'bases' => array(), 'users' => array(), 'missing_assets' => array() );
	$types = array_merge( array( 'pspp_campaign', 'pspp_universe' ), ps_pen_paper_campaign_record_types() );
	foreach ( $payload['records'] as $record ) {
		if ( ! ps_pen_paper_import_keys( $record, array( 'id', 'type', 'parent_id', 'author_id', 'status', 'title', 'content', 'excerpt',
			'mime_type', 'date_gmt', 'modified_gmt', 'menu_order', 'meta' ) ) ||
			! is_int( $record['id'] ) || $record['id'] < 1 || isset( $parsed['records'][ $record['id'] ] ) ||
			! in_array( $record['type'], $types, true ) || ! is_int( $record['parent_id'] ) || $record['parent_id'] < 0 ||
			! is_int( $record['author_id'] ) || $record['author_id'] < 0 ||
			! in_array( $record['status'], array( 'private', 'draft', 'trash' ), true ) || ! is_int( $record['menu_order'] ) ||
			! is_array( $record['meta'] ) || array_values( $record['meta'] ) !== $record['meta'] ) { return ps_pen_paper_import_error(); }
		foreach ( array( 'title', 'content', 'excerpt', 'mime_type', 'date_gmt', 'modified_gmt' ) as $key ) {
			if ( ! is_string( $record[ $key ] ) ) { return ps_pen_paper_import_error(); }
		}
		$id = $record['id']; $parsed['records'][ $id ] = $record; $parsed['meta'][ $id ] = array();
		if ( $record['author_id'] ) {
			if ( ! isset( $parsed['users'][ $record['author_id'] ] ) ) { $parsed['users'][ $record['author_id'] ] = array( 'required' => false ); }
			if ( 'pspp_character' === $record['type'] ) { $parsed['users'][ $record['author_id'] ]['required'] = true; }
		}
		foreach ( $record['meta'] as $entry ) {
			if ( ! ps_pen_paper_import_keys( $entry, array( 'key', 'value' ) ) || ! is_string( $entry['key'] ) ||
				0 !== strpos( $entry['key'], '_pspp_' ) || ! is_string( $entry['value'] ) ) { return ps_pen_paper_import_error(); }
			if ( isset( $parsed['meta'][ $id ][ $entry['key'] ] ) && ! in_array( $entry['key'], array( '_pspp_gm', '_pspp_player' ), true ) ) {
				return ps_pen_paper_import_error();
			}
			$value = $entry['value'];
			if ( preg_match( '/^[OCE]:\d+:/', $value ) ) { return ps_pen_paper_import_error(); }
			if ( is_serialized( $value ) ) {
				$offset = 0;
				// Reject objects, enums and references before PHP can invoke any class loader.
				if ( ! ps_pen_paper_import_serialized_value( $value, $offset ) || $offset !== strlen( $value ) ) { return ps_pen_paper_import_error(); }
				$value = @unserialize( $value, array( 'allowed_classes' => false, 'max_depth' => 64 ) );
				if ( ( false === $value && 'b:0;' !== $entry['value'] ) || ! ps_pen_paper_import_tree( $value ) ) { return ps_pen_paper_import_error(); }
			}
			$parsed['meta'][ $id ][ $entry['key'] ][] = $value;
			if ( in_array( $entry['key'], array( '_pspp_gm', '_pspp_player', '_pspp_actor', '_pspp_recipient' ), true ) ) {
				if ( ! is_string( $value ) || ! preg_match( '/^(0|[1-9]\d*)$/D', $value ) || (string) (int) $value !== $value ) {
					return ps_pen_paper_import_error();
				}
				if ( (int) $value && ! isset( $parsed['users'][ (int) $value ] ) ) { $parsed['users'][ (int) $value ] = array( 'required' => false ); }
			}
			if ( preg_match( '/^_pspp_join_([1-9]\d*)$/D', $entry['key'], $match ) ) {
				if ( (string) (int) $match[1] !== $match[1] ) { return ps_pen_paper_import_error(); }
				if ( ! isset( $parsed['users'][ (int) $match[1] ] ) ) { $parsed['users'][ (int) $match[1] ] = array( 'required' => false ); }
			}
			ps_pen_paper_import_user_references( $value, $parsed['users'], ! in_array( $record['type'], array( 'pspp_event', 'pspp_roll' ), true ) );
		}
		if ( 'pspp_asset' === $record['type'] ) {
			if ( ! ps_pen_paper_valid_private_asset_content( $record['mime_type'], $record['content'] ) ) { return ps_pen_paper_import_error(); }
			if ( ! in_array( $record['mime_type'], array( 'image/png', 'image/jpeg', 'image/webp' ), true ) ) {
				$markers = array_values( array_filter( $record['meta'], function ( $entry ) { return '_pspp_handout_asset' === $entry['key']; } ) );
				if ( 1 !== count( $markers ) || '1' !== $markers[0]['value'] ) { return ps_pen_paper_import_error(); }
			}
		}
	}
	$campaign_id = $payload['source']['campaign_id'];
	if ( ! isset( $parsed['records'][ $campaign_id ] ) || 'pspp_campaign' !== $parsed['records'][ $campaign_id ]['type'] ||
		'private' !== $parsed['records'][ $campaign_id ]['status'] || 0 !== $parsed['records'][ $campaign_id ]['parent_id'] ) { return ps_pen_paper_import_error(); }
	foreach ( $parsed['records'] as $id => $record ) {
		if ( 'pspp_campaign' === $record['type'] && $id !== $campaign_id ) { return ps_pen_paper_import_error(); }
		if ( 'pspp_universe' === $record['type'] ) {
			if ( $parsed['universe_id'] || 'complete' !== $payload['mode'] || 'private' !== $record['status'] || $record['parent_id'] ) {
				return ps_pen_paper_import_error();
			}
			$parsed['universe_id'] = $id;
		}
	}
	$link = ps_pen_paper_import_meta( $parsed, $campaign_id, '_pspp_universe_link' );
	if ( null !== $link && ( ! is_array( $link ) || ! isset( $link['universe_id'] ) || ! is_int( $link['universe_id'] ) || $link['universe_id'] < 0 ) ) {
		return ps_pen_paper_import_error();
	}
	if ( $parsed['universe_id'] && ( null === $link || $parsed['universe_id'] !== $link['universe_id'] ) ) { return ps_pen_paper_import_error(); }
	foreach ( $parsed['records'] as $id => $record ) {
		if ( in_array( $record['type'], array( 'pspp_campaign', 'pspp_universe' ), true ) ) { continue; }
		if ( $record['parent_id'] !== $campaign_id && ( ! $parsed['universe_id'] || $record['parent_id'] !== $parsed['universe_id'] ) ) {
			return ps_pen_paper_import_error();
		}
		if ( 'pspp_character' !== $record['type'] || $record['parent_id'] !== $campaign_id ) { continue; }
		$state = ps_pen_paper_import_meta( $parsed, $id, '_pspp_character' );
		if ( ! is_array( $state ) || ! isset( $state['context'] ) ) { continue; }
		$context = $state['context'];
		if ( ! is_array( $context ) || ! isset( $context['mode'], $context['source_id'], $context['universe_id'] ) ||
			! in_array( $context['mode'], array( 'shared', 'copy' ), true ) || ! is_int( $context['source_id'] ) || $context['source_id'] < 1 ||
			! is_int( $context['universe_id'] ) || $context['universe_id'] < 1 ) { return ps_pen_paper_import_error(); }
		if ( 'copy' === $context['mode'] ) { continue; }
		if ( null === $link || $link['universe_id'] !== $context['universe_id'] ) { return ps_pen_paper_import_error(); }
		$base = $context['source_id'];
		if ( isset( $parsed['bases'][ $base ] ) && $parsed['bases'][ $base ]['owner_id'] !== $record['author_id'] ) { return ps_pen_paper_import_error(); }
		$parsed['bases'][ $base ] = array( 'owner_id' => $record['author_id'], 'included' => isset( $parsed['records'][ $base ] ) );
		if ( $parsed['universe_id'] && ( ! isset( $parsed['records'][ $base ] ) ||
			'pspp_character' !== $parsed['records'][ $base ]['type'] || $parsed['records'][ $base ]['parent_id'] !== $parsed['universe_id'] ||
			$parsed['records'][ $base ]['author_id'] !== $record['author_id'] ) ) { return ps_pen_paper_import_error(); }
	}
	foreach ( $parsed['records'] as $id => $record ) {
		if ( $parsed['universe_id'] && 'pspp_character' === $record['type'] && $record['parent_id'] === $parsed['universe_id'] && ! isset( $parsed['bases'][ $id ] ) ) {
			return ps_pen_paper_import_error();
		}
		if ( $parsed['universe_id'] && $record['parent_id'] === $parsed['universe_id'] && ! in_array( $record['type'],
			array( 'pspp_character', 'pspp_item', 'pspp_npc', 'pspp_wiki_entry', 'pspp_loot_table', 'pspp_event', 'pspp_asset' ), true ) ) { return ps_pen_paper_import_error(); }
	}
	$references = array();
	foreach ( $parsed['meta'] as $id => $metadata ) {
		$references[ $id ] = array();
		foreach ( $metadata as $values ) {
			foreach ( $values as $value ) { $references[ $id ] = array_merge( $references[ $id ], ps_pen_paper_export_asset_ids( $value ) ); }
		}
		foreach ( array_unique( $references[ $id ] ) as $asset_id ) {
			if ( ! isset( $parsed['records'][ $asset_id ] ) || 'pspp_asset' !== $parsed['records'][ $asset_id ]['type'] ) {
				$parsed['missing_assets'][] = array( 'id' => $asset_id, 'referenced_by' => $id, 'historical' => 'pspp_event' === $parsed['records'][ $id ]['type'] );
			}
		}
	}
	$referenced_assets = $references ? array_unique( array_merge( ...array_values( $references ) ) ) : array();
	foreach ( $parsed['records'] as $id => $record ) {
		if ( $record['parent_id'] !== $parsed['universe_id'] ) { continue; }
		if ( 'pspp_asset' === $record['type'] && ! in_array( $id, $referenced_assets, true ) ) { return ps_pen_paper_import_error(); }
		if ( 'pspp_event' === $record['type'] ) {
			$event = ps_pen_paper_import_meta( $parsed, $id, '_pspp_event' );
			if ( ! is_array( $event ) || ! isset( $event['object_id'] ) || ! is_int( $event['object_id'] ) ||
				! isset( $parsed['records'][ $event['object_id'] ] ) ) { return ps_pen_paper_import_error(); }
		}
	}
	foreach ( $payload['dependencies'] as $dependency ) {
		if ( ! is_array( $dependency ) || ! isset( $dependency['kind'], $dependency['id'], $dependency['included'] ) ||
			! is_string( $dependency['kind'] ) || ! is_int( $dependency['id'] ) || $dependency['id'] < 1 || ! is_bool( $dependency['included'] ) ) {
			return ps_pen_paper_import_error();
		}
		if ( 'universe' === $dependency['kind'] ) {
			if ( ! ps_pen_paper_import_keys( $dependency, array( 'kind', 'id', 'included' ) ) || null === $link ||
				$dependency['id'] !== $link['universe_id'] || $dependency['included'] !== (bool) $parsed['universe_id'] ) { return ps_pen_paper_import_error(); }
		} elseif ( 'shared_character' === $dependency['kind'] ) {
			if ( ! ps_pen_paper_import_keys( $dependency, array( 'kind', 'id', 'universe_id', 'included' ) ) ||
				! isset( $parsed['bases'][ $dependency['id'] ] ) || null === $link || $dependency['universe_id'] !== $link['universe_id'] ||
				$dependency['included'] !== $parsed['bases'][ $dependency['id'] ]['included'] ) { return ps_pen_paper_import_error(); }
		} elseif ( 'asset' === $dependency['kind'] ) {
			if ( ! ps_pen_paper_import_keys( $dependency, array( 'kind', 'id', 'referenced_by', 'historical', 'included' ) ) ||
				! is_int( $dependency['referenced_by'] ) || ! isset( $references[ $dependency['referenced_by'] ] ) ||
				! in_array( $dependency['id'], $references[ $dependency['referenced_by'] ], true ) ||
				$dependency['historical'] !== ( 'pspp_event' === $parsed['records'][ $dependency['referenced_by'] ]['type'] ) ||
				$dependency['included'] !== ( isset( $parsed['records'][ $dependency['id'] ] ) && 'pspp_asset' === $parsed['records'][ $dependency['id'] ]['type'] ) ) {
				return ps_pen_paper_import_error();
			}
		} else { return ps_pen_paper_import_error(); }
	}
	ksort( $parsed['users'] ); ksort( $parsed['bases'] );
	return $parsed;
}

function ps_pen_paper_import_user_references( $value, &$users, $required = true ) {
	if ( ! is_array( $value ) ) { return; }
	if ( isset( $value['users'] ) && is_array( $value['users'] ) &&
		( ( isset( $value['mode'] ) && in_array( $value['mode'], array( 'all', 'users' ), true ) ) ||
			array( 'revision', 'users' ) === array_keys( $value ) ) ) {
		foreach ( $value['users'] as $id ) {
			if ( ! is_int( $id ) || $id < 1 ) { continue; }
			if ( ! isset( $users[ $id ] ) ) { $users[ $id ] = array( 'required' => false ); }
			if ( $required ) { $users[ $id ]['required'] = true; }
		}
	}
	foreach ( $value as $key => $part ) {
		if ( in_array( $key, array( 'owner_id', 'authorizer_id', 'recipient_id', 'actor_id', 'user_id', 'requester_id', 'reviewer_id' ), true ) && is_int( $part ) && $part > 0 ) {
			if ( ! isset( $users[ $part ] ) ) { $users[ $part ] = array( 'required' => false ); }
			if ( $required && in_array( $key, array( 'owner_id', 'authorizer_id', 'requester_id', 'reviewer_id' ), true ) ) { $users[ $part ]['required'] = true; }
		}
		if ( is_array( $part ) ) { ps_pen_paper_import_user_references( $part, $users, $required ); }
	}
}

function ps_pen_paper_can_preview_import() {
	if ( ! ps_pen_paper_can_access_site() ) { return false; }
	if ( ps_pen_paper_can_create_campaign() ) { return true; }
	foreach ( ps_pen_paper_get_campaigns() as $campaign ) { if ( ps_pen_paper_can_manage_campaign( $campaign ) ) { return true; } }
	return false;
}

function ps_pen_paper_import_rule_schema( $rules ) {
	if ( ! is_array( $rules ) || ! isset( $rules['fields'] ) || ! is_array( $rules['fields'] ) ) { return ps_pen_paper_import_error(); }
	$ids = array();
	foreach ( $rules['fields'] as $field ) {
		if ( ! is_array( $field ) || ! isset( $field['id'], $field['type'] ) || ! is_string( $field['id'] ) ||
			! wp_is_uuid( $field['id'] ) || isset( $ids[ $field['id'] ] ) || ! is_string( $field['type'] ) ||
			! isset( ps_pen_paper_field_types()[ $field['type'] ] ) ) { return ps_pen_paper_import_error(); }
		$ids[ $field['id'] ] = true;
	}
	return ps_pen_paper_character_schema( $rules );
}

function ps_pen_paper_import_configuration( $parsed, $options ) {
	if ( ! ps_pen_paper_can_preview_import() ) {
		return new WP_Error( 'forbidden', __( 'Nur berechtigte Administration und Spielleitung dürfen Importvorschauen vorbereiten.', 'ps-pen-paper' ) );
	}
	if ( ! ps_pen_paper_import_keys( $options, array( 'operation', 'target_id', 'universe_mode', 'universe_id', 'users', 'bases' ) ) ||
		! in_array( $options['operation'], array( '', 'copy', 'replace' ), true ) ||
		! in_array( $options['universe_mode'], array( '', 'none', 'copy', 'bind' ), true ) ||
		! is_int( $options['target_id'] ) || $options['target_id'] < 0 || ! is_int( $options['universe_id'] ) || $options['universe_id'] < 0 ||
		! is_array( $options['users'] ) || ! is_array( $options['bases'] ) ||
		array_diff( array_keys( $options['users'] ), array_keys( $parsed['users'] ) ) ||
		array_diff( array_keys( $options['bases'] ), array_keys( $parsed['bases'] ) ) ) { return ps_pen_paper_import_error(); }
	$document_check = ps_pen_paper_import_documents( $parsed );
	$conflicts = $document_check['conflicts']; $warnings = array(); $target_hash = ''; $universe_hash = '';
	if ( '' === $options['operation'] ) { $conflicts[] = __( 'Wähle eine neue Kampagne oder den Ersatz einer bestehenden Kampagne.', 'ps-pen-paper' ); }
	if ( 'copy' === $options['operation'] ) {
		if ( ! ps_pen_paper_can_create_campaign() ) { $conflicts[] = __( 'Für eine neue Kampagne fehlt die Erstellungsberechtigung.', 'ps-pen-paper' ); }
		if ( $options['target_id'] ) { $conflicts[] = __( 'Bei einer neuen Kampagne darf keine bestehende Zielkampagne ausgewählt sein.', 'ps-pen-paper' ); }
	}
	if ( 'replace' === $options['operation'] ) {
		$target = ps_pen_paper_get_campaign( $options['target_id'] );
		if ( ! $target || ! ps_pen_paper_can_manage_campaign( $target ) ) {
			$conflicts[] = __( 'Wähle eine bestehende Kampagne, die du verwalten darfst.', 'ps-pen-paper' );
		} else {
			$backup = ps_pen_paper_export_campaign( $target->ID, 'local' );
			if ( is_wp_error( $backup ) ) { return $backup; }
			$target_hash = $backup['payload_sha256'];
			$booking_links = get_post_meta( $target->ID, '_pspp_booking_links', false );
			if ( $booking_links ) {
				$target_hash = hash( 'sha256', serialize( array( $target_hash, $booking_links ) ) );
				$warnings[] = __( 'Lokale Terminverknüpfungen werden beim Ersatz entfernt und sind nicht in der Kampagnensicherung enthalten. Verknüpfe anschließend passende Veranstaltungen in PS Events neu.', 'ps-pen-paper' );
			}
			$warnings[] = __( 'Der Ersatz betrifft alle kampagneneigenen Spielstände. Vor dem schreibenden Import sind eine aktuelle Sicherung und eine gesonderte Bestätigung erforderlich. Der bisherige Kampagnenersteller bleibt geschützt.', 'ps-pen-paper' );
		}
	}
	foreach ( $parsed['users'] as $id => $requirement ) {
		$mapped = isset( $options['users'][ $id ] ) ? $options['users'][ $id ] : 0;
		if ( ! is_int( $mapped ) || $mapped < -1 ) { return ps_pen_paper_import_error(); }
		if ( 0 === $mapped || ( -1 === $mapped && $requirement['required'] ) ) {
			/* translators: %d: original user ID in the backup. */
			$conflicts[] = sprintf( __( 'Benutzer #%d benötigt eine ausdrückliche Kontozuordnung.', 'ps-pen-paper' ), $id );
		} elseif ( $mapped > 0 && ! ps_pen_paper_site_user( $mapped ) ) {
			$conflicts[] = __( 'Ein zugeordnetes Benutzerkonto ist auf dieser Website nicht verfügbar.', 'ps-pen-paper' );
		}
	}
	foreach ( $options['bases'] as $mapped ) { if ( ! is_int( $mapped ) || $mapped < 0 ) { return ps_pen_paper_import_error(); } }
	$source_id = $parsed['archive']['payload']['source']['campaign_id'];
	$link = ps_pen_paper_import_meta( $parsed, $source_id, '_pspp_universe_link' );
	$needs_universe = is_array( $link ) && ! empty( $link['universe_id'] );
	if ( '' === $options['universe_mode'] ) { $conflicts[] = __( 'Wähle den Umgang mit Universumsdaten.', 'ps-pen-paper' ); }
	if ( 'none' === $options['universe_mode'] ) { $conflicts[] = __( 'Jede wiederhergestellte Kampagne benötigt ein Universum. Ordne ein bestehendes zu oder stelle das enthaltene Universum als Kopie wieder her.', 'ps-pen-paper' ); }
	if ( 'copy' === $options['universe_mode'] ) {
		if ( ! $parsed['universe_id'] ) { $conflicts[] = __( 'Die Datei enthält keine Universumsdaten für eine getrennte Wiederherstellung.', 'ps-pen-paper' ); }
		if ( ! ps_pen_paper_can_create_campaign() ) { $conflicts[] = __( 'Für ein neues Universum fehlt die Erstellungsberechtigung.', 'ps-pen-paper' ); }
	}
	if ( 'bind' !== $options['universe_mode'] && ( $options['universe_id'] || array_filter( $options['bases'] ) ) ) {
		$conflicts[] = __( 'Bestehende Universen und Basisbögen dürfen nur bei ausdrücklich gewählter Zuordnung ausgewählt sein.', 'ps-pen-paper' );
	}
	if ( 'bind' === $options['universe_mode'] ) {
		$universe = ps_pen_paper_get_universe( $options['universe_id'] );
		if ( ! $universe || ! ps_pen_paper_can_manage_universe( $universe ) ) {
			$conflicts[] = __( 'Wähle ein benötigtes Universum, das du verwalten darfst.', 'ps-pen-paper' );
		} else {
			$rules = ps_pen_paper_get_rules( $universe->ID );
			$source_rules = ps_pen_paper_import_meta( $parsed, $source_id, '_pspp_rules' );
			$current_schema = ps_pen_paper_import_rule_schema( $rules );
			$source_schema = ps_pen_paper_import_rule_schema( $source_rules );
			if ( $needs_universe && ( is_wp_error( $current_schema ) || is_wp_error( $source_schema ) || $current_schema !== $source_schema ) ) {
				$conflicts[] = __( 'Die Feldstruktur des bestehenden Universums passt nicht zur Sicherung. Eine Regelmigration ist ein gesonderter Vorgang.', 'ps-pen-paper' );
			}
			$documents = array( 'rules' => $rules, 'universe' => ps_pen_paper_universe_document( $universe->ID ),
				'currency' => ps_pen_paper_currency_document( $universe->ID ) );
			foreach ( $documents as $document ) { if ( is_wp_error( $document ) ) { return $document; } }
			$universe_hash = hash( 'sha256', serialize( $documents ) );
			foreach ( $parsed['bases'] as $id => $base ) {
				$mapped = isset( $options['bases'][ $id ] ) ? $options['bases'][ $id ] : 0;
				$post = $mapped ? ps_pen_paper_record_post( $mapped ) : null;
				$owner = isset( $options['users'][ $base['owner_id'] ] ) ? $options['users'][ $base['owner_id'] ] : 0;
				if ( ! $post || 'pspp_character' !== $post->post_type || 'private' !== $post->post_status ||
					(int) $post->post_parent !== $universe->ID || $owner < 1 || (int) $post->post_author !== $owner ) {
					/* translators: %d: original base-character ID in the backup. */
					$conflicts[] = sprintf( __( 'Basisbogen #%d benötigt eine passende Zuordnung zu Universum und Besitzer.', 'ps-pen-paper' ), $id );
				} else {
					$state = ps_pen_paper_raw_character_state( $post->ID );
					if ( is_wp_error( $state ) ) { return $state; }
					if ( null === $state['active'] || isset( $state['context'] ) ) {
						$conflicts[] = __( 'Ein gewählter Basisbogen besitzt keinen unabhängigen freigegebenen Stand.', 'ps-pen-paper' );
					}
				}
			}
			$wallet_currency = ! $needs_universe || ( isset( $link['economy_mode'] ) && 'local' === $link['economy_mode'] )
				? ps_pen_paper_import_meta( $parsed, $source_id, '_pspp_currency' ) : $documents['currency'];
			if ( is_wp_error( $wallet_currency ) ) { return $wallet_currency; }
			foreach ( $parsed['records'] as $id => $record ) {
				$wallet = ps_pen_paper_import_meta( $parsed, $id, '_pspp_wallet' );
				if ( $record['parent_id'] !== $source_id || null === $wallet ) { continue; }
				if ( ! ps_pen_paper_valid_wallet( $wallet ) || ! ps_pen_paper_valid_currency( $wallet_currency ) || $wallet['currency_id'] !== $wallet_currency['currency_id'] ) {
					$conflicts[] = __( 'Ein lokales Guthaben passt nicht zur ausdrücklich gewählten Währung. Es wird keine automatische Währungsumrechnung vorgenommen.', 'ps-pen-paper' );
				}
			}
			$warnings[] = __( 'Beim Zuordnen bleiben vorhandene Universumsdaten, Basiswerte und gemeinsame Guthaben unverändert. Archivierte gemeinsame Stände werden nicht über sie geschrieben.', 'ps-pen-paper' );
			if ( ! $needs_universe ) {
				$warnings[] = __( 'Die bisher unabhängige Kampagne wird ausdrücklich dem gewählten Universum zugeordnet. Ihre Regeln, Charaktere, lokale Währung und Handelsregeln bleiben eigenständig erhalten.', 'ps-pen-paper' );
			}
		}
	}
	foreach ( $parsed['missing_assets'] as $dependency ) {
		if ( ! $dependency['historical'] ) { $conflicts[] = __( 'Ein benötigtes Bild fehlt in der Sicherung.', 'ps-pen-paper' ); }
		else { $warnings[] = __( 'Ein bereits fehlendes historisches Bild kann nicht wiederhergestellt werden.', 'ps-pen-paper' ); }
	}
	$warnings[] = __( 'Konten werden nicht erstellt und globale Rollen nicht vergeben. Externe Wiki-Artikel, Chatnachrichten und Community-Profile benötigen eigene Sicherungen beziehungsweise spätere Verknüpfungen.', 'ps-pen-paper' );
	if ( 'bind' === $options['universe_mode'] && ! $conflicts ) {
		global $wpdb;
		$ids = array_values( array_unique( array_merge( array( $options['universe_id'] ), array_values( $options['bases'] ) ) ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID,p.post_type,p.post_parent,p.post_author,p.post_status,p.post_title,p.post_content,p.post_excerpt,p.post_mime_type,p.menu_order,m.meta_key,m.meta_value
			FROM $wpdb->posts p LEFT JOIN $wpdb->postmeta m ON m.post_id=p.ID AND m.meta_key LIKE %s
			WHERE p.ID IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ")
			OR (p.post_parent=%d AND p.post_type IN ('pspp_item','pspp_npc','pspp_loot_table','pspp_asset','pspp_wiki_entry'))
			ORDER BY p.ID,m.meta_id",
			array_merge( array( $wpdb->esc_like( '_pspp_' ) . '%' ), $ids, array( $options['universe_id'] ) )
		), ARRAY_A );
		if ( $wpdb->last_error || ! is_array( $rows ) || ! $rows ) { return ps_pen_paper_import_error(); }
		$universe_hash = hash( 'sha256', serialize( $rows ) );
	}
	return array( 'options' => $options, 'documents' => $document_check, 'conflicts' => array_values( array_unique( $conflicts ) ),
		'warnings' => array_values( array_unique( $warnings ) ), 'target_hash' => $target_hash, 'universe_hash' => $universe_hash );
}

function ps_pen_paper_import_preview_key( $token ) {
	return 'pspp_import_' . get_current_blog_id() . '_' . get_current_user_id() . '_' . $token;
}

function ps_pen_paper_create_import_preview( $json ) {
	if ( ! ps_pen_paper_can_preview_import() ) { return new WP_Error( 'forbidden', __( 'Du darfst keine Importvorschau erstellen.', 'ps-pen-paper' ) ); }
	$parsed = ps_pen_paper_parse_import( $json );
	if ( is_wp_error( $parsed ) ) { return $parsed; }
	$token = wp_generate_password( 32, false, false );
	$preview = array( 'owner_id' => get_current_user_id(), 'blog_id' => get_current_blog_id(), 'expires' => time() + HOUR_IN_SECONDS,
		'json' => $json, 'options' => array( 'operation' => '', 'target_id' => 0, 'universe_mode' => '', 'universe_id' => 0, 'users' => array(), 'bases' => array() ) );
	if ( ! set_transient( ps_pen_paper_import_preview_key( $token ), $preview, HOUR_IN_SECONDS ) ) {
		return new WP_Error( 'import_preview_failed', __( 'Die private Vorschau konnte nicht gespeichert werden. Es wurden keine Spielstände geändert.', 'ps-pen-paper' ) );
	}
	return $token;
}

function ps_pen_paper_get_import_preview( $token ) {
	if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9]{32}$/D', $token ) || ! ps_pen_paper_can_preview_import() ) {
		return new WP_Error( 'forbidden', __( 'Diese Importvorschau ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	$preview = get_transient( ps_pen_paper_import_preview_key( $token ) );
	if ( ! ps_pen_paper_import_keys( $preview, array( 'owner_id', 'blog_id', 'expires', 'json', 'options' ) ) ||
		! is_int( $preview['expires'] ) || ! is_string( $preview['json'] ) || ! is_array( $preview['options'] ) ||
		$preview['owner_id'] !== get_current_user_id() || $preview['blog_id'] !== get_current_blog_id() || $preview['expires'] <= time() ) {
		return new WP_Error( 'import_preview_expired', __( 'Die Importvorschau fehlt oder ist abgelaufen. Bitte lade die Sicherungsdatei erneut hoch.', 'ps-pen-paper' ) );
	}
	return $preview;
}
