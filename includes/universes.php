<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/character-policy.php';

function ps_pen_paper_register_universe_type() {
	register_post_type( 'pspp_universe', array(
		'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
		'show_ui' => false, 'show_in_rest' => false,
		'capability_type' => array( 'pspp_universe', 'pspp_universes' ),
		'map_meta_cap' => true, 'supports' => false,
	) );
}

function ps_pen_paper_get_universe( $id ) {
	$post = ps_pen_paper_record_post( $id );
	if ( $post && ! ps_pen_paper_scope_observe( $post->ID ) ) { return null; }
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
		! is_string( $document['name'] ) || '' === $document['name'] || ! is_string( $document['description'] ) ||
		( array_key_exists( 'introduction', $document ) && ( ! is_string( $document['introduction'] ) || strlen( $document['introduction'] ) > 20000 ) ) ) {
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
	if ( ! is_array( $input ) || array_diff( array_keys( $input ), array( 'name', 'description', 'introduction' ) ) || ! isset( $input['name'], $input['description'] ) ||
		! is_string( $input['name'] ) || ! is_string( $input['description'] ) ||
		'' === sanitize_text_field( $input['name'] ) || strlen( $input['name'] ) > 200 || strlen( $input['description'] ) > 20000 ||
		( array_key_exists( 'introduction', $input ) && ( ! is_string( $input['introduction'] ) || strlen( $input['introduction'] ) > 20000 ) ) ) {
		return new WP_Error( 'invalid_universe', __( 'Bitte gib einen Universumsnamen mit höchstens 200 Bytes und eine Beschreibung mit höchstens 20000 Bytes ein.', 'ps-pen-paper' ) );
	}
	$after = array( 'revision' => $before['revision'] + 1, 'name' => sanitize_text_field( $input['name'] ), 'description' => sanitize_textarea_field( $input['description'] ) );
	if ( array_key_exists( 'introduction', $input ) || array_key_exists( 'introduction', $before ) ) {
		$after['introduction'] = sanitize_textarea_field( $input['introduction'] ?? $before['introduction'] );
	}
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

function ps_pen_paper_valid_universe_link( $link ) {
	return is_array( $link ) && isset( $link['revision'], $link['universe_id'], $link['name'] ) &&
		is_int( $link['revision'] ) && $link['revision'] >= 1 && is_int( $link['universe_id'] ) &&
		$link['universe_id'] >= 0 && is_string( $link['name'] ) &&
		( ! array_key_exists( 'economy_mode', $link ) || in_array( $link['economy_mode'], array( 'universe', 'local' ), true ) ) &&
		( ! array_key_exists( 'rules_base', $link ) || ( $link['universe_id'] > 0 && ps_pen_paper_valid_inheritance_base( $link['rules_base'] ) ) );
}

function ps_pen_paper_get_universe_link( $campaign_id ) {
	$link = get_post_meta( $campaign_id, '_pspp_universe_link', true );
	if ( '' === $link ) {
		return array( 'revision' => 0, 'universe_id' => 0, 'name' => '' );
	}
	if ( ! ps_pen_paper_valid_universe_link( $link ) ) {
		return new WP_Error( 'invalid_universe_link', __( 'Die Universumszuordnung ist beschädigt. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	return $link;
}

function ps_pen_paper_preview_universe_link( $campaign_id, $universe_id, $economy_mode = 'universe' ) {
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! ps_pen_paper_can_access_site() || ! $campaign || ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die Kampagnenleitung darf eine Universumszuordnung prüfen.', 'ps-pen-paper' ) );
	}
	if ( ! is_int( $universe_id ) || $universe_id < 1 || ! in_array( $economy_mode, array( 'universe', 'local' ), true ) ) {
		return new WP_Error( 'invalid_universe', __( 'Bitte wähle ein gültiges Universum.', 'ps-pen-paper' ) );
	}
	$before = ps_pen_paper_get_universe_link( $campaign_id );
	if ( is_wp_error( $before ) ) {
		return $before;
	}
	$current_mode = isset( $before['economy_mode'] ) ? $before['economy_mode'] : 'universe';
	$economy_change = isset( $before['rules_base'] ) && $before['universe_id'] === $universe_id && $current_mode !== $economy_mode;
	if ( isset( $before['rules_base'] ) && $before['universe_id'] !== $universe_id ) {
		return new WP_Error( 'rules_conflict', __( 'Eine Kampagne mit aktiver Regelvererbung kann nicht ohne Weiteres einem anderen Universum zugeordnet werden. Erstelle dafür eine neue Kampagne.', 'ps-pen-paper' ) );
	}
	if ( $economy_change && 'local' === $economy_mode ) {
		$source = ps_pen_paper_get_rules( $universe_id );
		if ( ! ps_pen_paper_inheritance_allows( $source, 'economy' ) ) {
			return new WP_Error( 'fixed_universe_rule', __( 'Die Universumsleitung hat keine eigene Kampagnenwirtschaft freigegeben.', 'ps-pen-paper' ) );
		}
		$ready = ps_pen_paper_require_inherited_rules( $campaign_id );
		if ( is_wp_error( $ready ) ) { return $ready; }
	}
	if ( ( $before['universe_id'] !== $universe_id || $current_mode !== $economy_mode ) && ps_pen_paper_has_shared_characters( $campaign_id ) ) {
		return new WP_Error( 'context_in_use', __( 'Diese Kampagne nutzt gemeinsame Basisbögen. Führe sie im Kampagnenkontext zunächst ausdrücklich unabhängig weiter, bevor du die Universumszuordnung wechselst oder löst.', 'ps-pen-paper' ) );
	}
	if ( ( $before['universe_id'] !== $universe_id || $current_mode !== $economy_mode ) && ps_pen_paper_campaign_has_wallets( $campaign_id ) ) {
		$currency_scope = ps_pen_paper_currency_scope( $campaign );
		if ( is_wp_error( $currency_scope ) ) { return $currency_scope; }
		if ( $currency_scope->ID !== $campaign->ID || ( $universe_id && 'local' !== $economy_mode ) ) {
			return new WP_Error( 'wallets_in_use', __( 'Diese Kampagne hat bereits Guthabenkonten. Wähle „Lokale Währung und Handelsregeln behalten“, wenn die Konten zur Kampagne gehören. Bestehende Konten werden nicht übertragen oder in eine andere Währung umgerechnet.', 'ps-pen-paper' ) );
		}
	}
	if ( $before['universe_id'] !== $universe_id || $current_mode !== $economy_mode ) {
		$trades = ps_pen_paper_trade_records_internal( $campaign );
		if ( is_wp_error( $trades ) ) { return $trades; }
		foreach ( $trades as $trade ) {
			if ( ps_pen_paper_trade_open( $trade ) ) {
				return new WP_Error( 'trades_in_use', __( 'Offene Handelsangebote müssen vor dem Wechsel der Wirtschaftszuordnung abgeschlossen oder zurückgezogen werden.', 'ps-pen-paper' ) );
			}
		}
	}
	$documents = array( 0 => array( 'revision' => 0, 'name' => '' ) );
	foreach ( array_unique( array( $before['universe_id'], $universe_id ) ) as $id ) {
		if ( $id && ! $economy_change && ! ps_pen_paper_can_manage_universe( ps_pen_paper_get_universe( $id ) ) ) {
			return new WP_Error( 'forbidden', __( 'Du musst das bisherige und das gewählte Universum verwalten dürfen. Bei einer fehlenden Zuordnung wende dich an den Administrator.', 'ps-pen-paper' ) );
		}
		if ( $id ) {
			$documents[ $id ] = ps_pen_paper_universe_document( $id );
			if ( is_wp_error( $documents[ $id ] ) ) {
				return $documents[ $id ];
			}
			if ( $economy_change && ! ps_pen_paper_can_manage_universe( ps_pen_paper_get_universe( $id ) ) ) {
				$documents[ $id ] = array_intersect_key( $documents[ $id ], array_flip( array( 'revision', 'name' ) ) );
			}
		}
	}
	return array( 'before' => $before, 'universe' => $documents[ $universe_id ], 'source_universe' => $documents[ $before['universe_id'] ], 'campaign' => $campaign, 'economy_mode' => $economy_mode );
}

function ps_pen_paper_save_universe_link( $campaign_id, $universe_id, $revision, $universe_revision, $confirmed, $economy_mode = 'universe', $copy_new_rules = false ) {
	if ( $universe_id ) {
		return ps_pen_paper_scope_lock( $universe_id, function () use ( $campaign_id, $universe_id, $revision, $universe_revision, $confirmed, $economy_mode, $copy_new_rules ) {
			return ps_pen_paper_save_universe_link_locked( $campaign_id, $universe_id, $revision, $universe_revision, $confirmed, $economy_mode, $copy_new_rules );
		} );
	}
	return ps_pen_paper_save_universe_link_locked( $campaign_id, $universe_id, $revision, $universe_revision, $confirmed, $economy_mode, false );
}

function ps_pen_paper_save_universe_link_locked( $campaign_id, $universe_id, $revision, $universe_revision, $confirmed, $economy_mode, $copy_new_rules ) {
	$preview = ps_pen_paper_preview_universe_link( $campaign_id, $universe_id, $economy_mode );
	if ( is_wp_error( $preview ) ) {
		return $preview;
	}
	if ( ! is_string( $revision ) || (string) $preview['before']['revision'] !== $revision ||
		! is_string( $universe_revision ) || (string) $preview['universe']['revision'] !== $universe_revision ) {
		return new WP_Error( 'conflict', __( 'Die Zuordnung oder das Universum wurde inzwischen geändert. Bitte prüfe eine neue Vorschau.', 'ps-pen-paper' ) );
	}
	$current_mode = isset( $preview['before']['economy_mode'] ) ? $preview['before']['economy_mode'] : 'universe';
	if ( 'confirmed' !== $confirmed || ( $preview['before']['universe_id'] === $universe_id && ( ! $universe_id || $current_mode === $economy_mode ) ) ) {
		return new WP_Error( 'confirmation_required', __( 'Bitte prüfe und bestätige eine geänderte Universumszuordnung.', 'ps-pen-paper' ) );
	}
	if ( ps_pen_paper_has_shared_characters( $campaign_id ) ) {
		return new WP_Error( 'context_in_use', __( 'Diese Kampagne nutzt gemeinsame Basisbögen. Führe sie im Kampagnenkontext zunächst ausdrücklich unabhängig weiter, bevor du die Universumszuordnung wechselst oder löst.', 'ps-pen-paper' ) );
	}
	$after = array( 'revision' => $preview['before']['revision'] + 1, 'universe_id' => $universe_id, 'name' => $preview['universe']['name'] );
	if ( isset( $preview['before']['rules_base'] ) && $preview['before']['universe_id'] === $universe_id ) { $after['rules_base'] = $preview['before']['rules_base']; }
	if ( $universe_id && 'local' === $economy_mode ) { $after['economy_mode'] = 'local'; }
	$result = ps_pen_paper_atomic_event( $campaign_id, function () use ( $campaign_id, $universe_id, $preview, $after, $economy_mode, $copy_new_rules ) {
		$current = ps_pen_paper_preview_universe_link( $campaign_id, $universe_id, $economy_mode );
		if ( is_wp_error( $current ) ) { return $current; }
		if ( $current['before'] !== $preview['before'] || $current['universe'] !== $preview['universe'] ) {
			return new WP_Error( 'conflict', __( 'Die Zuordnung wurde geändert. Bitte prüfe eine neue Vorschau.', 'ps-pen-paper' ) );
		}
		$source = null;
		if ( $copy_new_rules ) {
			$source = ps_pen_paper_get_rules( $universe_id );
			if ( 0 !== $preview['before']['revision'] || metadata_exists( 'post', $campaign_id, '_pspp_rules' ) ||
				metadata_exists( 'post', $campaign_id, '_pspp_play_round' ) || ps_pen_paper_has_characters( $campaign_id ) ||
				! is_array( $source ) || ! isset( $source['revision'] ) || ! is_int( $source['revision'] ) ||
				( 0 === $source['revision'] && metadata_exists( 'post', $universe_id, '_pspp_rules' ) ) ||
				( 0 !== $source['revision'] && ! ps_pen_paper_valid_rules_document( $source ) ) ) {
				return new WP_Error( 'rules_conflict', __( 'Das Universumsregelwerk oder die neue Kampagne ist nicht für eine automatische Übernahme geeignet. Es wurde nichts übernommen.', 'ps-pen-paper' ) );
			}
			$after['rules_base'] = $source;
		}
		$link_event = ps_pen_paper_write_document_event( $campaign_id, $campaign_id, '_pspp_universe_link', $preview['before'], $after, array(
			'kind' => 'universe_linked', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0,
		), false );
		if ( is_wp_error( $link_event ) ) { return $link_event; }
		if ( isset( $preview['before']['rules_base'] ) && 'local' === $economy_mode &&
			'local' !== ( $preview['before']['economy_mode'] ?? 'universe' ) ) {
			$economy = ps_pen_paper_initialize_campaign_economy( $campaign_id, $universe_id );
			if ( is_wp_error( $economy ) ) { return $economy; }
		}
		if ( ! $copy_new_rules || 0 === $source['revision'] ) { return $link_event; }
		$before_rules = ps_pen_paper_get_rules( $campaign_id );
		$source['revision'] = 1;
		return ps_pen_paper_write_document_event( $campaign_id, $campaign_id, '_pspp_rules', $before_rules, $source, array(
			'kind' => 'rules_saved', 'visibility' => 'group', 'recipient_id' => 0, 'rules_revision' => 1, 'fields' => array(),
		), false );
	} );
	return is_wp_error( $result ) ? $result : ( $copy_new_rules ? ps_pen_paper_get_universe_link( $campaign_id ) : $after );
}

function ps_pen_paper_initialize_campaign_economy( $campaign_id, $universe_id ) {
	if ( ! metadata_exists( 'post', $campaign_id, '_pspp_currency' ) ) {
		$before = ps_pen_paper_currency_document( $campaign_id );
		$currency = ps_pen_paper_currency_document( $universe_id );
		if ( is_wp_error( $before ) || is_wp_error( $currency ) ) { return is_wp_error( $before ) ? $before : $currency; }
		if ( $currency['revision'] ) {
			$currency['revision'] = 1; $currency['currency_id'] = wp_generate_uuid4();
			$currency['wallet_mode'] = 'campaign'; $currency['in_use'] = false;
			$saved = ps_pen_paper_write_document_event( $campaign_id, $campaign_id, '_pspp_currency', $before, $currency,
				array( 'kind' => 'currency_saved', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
			if ( is_wp_error( $saved ) ) { return $saved; }
		}
	}
	if ( ! metadata_exists( 'post', $campaign_id, '_pspp_trade_policy' ) ) {
		$universe = ps_pen_paper_get_universe( $universe_id );
		$trade = $universe ? ps_pen_paper_trade_policy( $universe ) : ps_pen_paper_trade_error();
		if ( is_wp_error( $trade ) ) { return $trade; }
		$before = ps_pen_paper_trade_policy_default(); $after = $trade['policy'];
		$after['revision'] = 1; unset( $after['transfers'] );
		return ps_pen_paper_write_document_event( $campaign_id, $campaign_id, '_pspp_trade_policy', $before, $after,
			array( 'kind' => 'trade_policy', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
	}
	return 0;
}
