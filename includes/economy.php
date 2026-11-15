<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_wallet_modes() {
	return array(
		'campaign' => __( 'Getrennt je Kampagnencharakter', 'ps-pen-paper' ),
		'universe' => __( 'Gemeinsam am Universumscharakter', 'ps-pen-paper' ),
	);
}

function ps_pen_paper_currency_scope( $scope ) {
	if ( 'pspp_universe' === $scope->post_type ) { return $scope; }
	$link = ps_pen_paper_get_universe_link( $scope->ID );
	if ( is_wp_error( $link ) ) { return $link; }
	if ( ! $link['universe_id'] ) { return $scope; }
	$universe = ps_pen_paper_get_universe( $link['universe_id'] );
	return $universe ?: new WP_Error( 'invalid_currency', __( 'Das Universum der Währung fehlt. Es wird keine Ersatzwährung verwendet.', 'ps-pen-paper' ) );
}

function ps_pen_paper_valid_currency( $value ) {
	return is_array( $value ) && isset( $value['revision'], $value['currency_id'], $value['name'], $value['symbol'], $value['wallet_mode'], $value['in_use'] ) &&
		is_int( $value['revision'] ) && $value['revision'] >= 0 && is_string( $value['currency_id'] ) &&
		is_string( $value['name'] ) && strlen( $value['name'] ) <= 200 && is_string( $value['symbol'] ) && strlen( $value['symbol'] ) <= 24 &&
		is_string( $value['wallet_mode'] ) && isset( ps_pen_paper_wallet_modes()[ $value['wallet_mode'] ] ) && is_bool( $value['in_use'] ) &&
		( 0 === $value['revision'] ? ( '' === $value['currency_id'] && '' === $value['name'] && '' === $value['symbol'] && ! $value['in_use'] )
			: ( wp_is_uuid( $value['currency_id'] ) && '' !== trim( $value['name'] ) && '' !== trim( $value['symbol'] ) ) );
}

function ps_pen_paper_currency_document( $scope_id ) {
	$value = get_post_meta( $scope_id, '_pspp_currency', true );
	if ( '' === $value ) { return array( 'revision' => 0, 'currency_id' => '', 'name' => '', 'symbol' => '', 'wallet_mode' => 'campaign', 'in_use' => false ); }
	return ps_pen_paper_valid_currency( $value ) ? $value : new WP_Error( 'invalid_currency', __( 'Die Währungseinstellungen sind beschädigt. Bitte informiere die Spielleitung.', 'ps-pen-paper' ) );
}

function ps_pen_paper_save_currency( $scope, $input, $revision ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_character_scope( $scope ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die zuständige Spielleitung darf die Währung einstellen.', 'ps-pen-paper' ) );
	}
	if ( ! is_array( $input ) || ! isset( $input['name'], $input['symbol'], $input['wallet_mode'] ) ||
		! is_string( $input['name'] ) || ! is_string( $input['symbol'] ) || ! is_string( $input['wallet_mode'] ) ) {
		return new WP_Error( 'invalid_currency', __( 'Bitte übertrage Name, Kürzel und Guthabenmodell vollständig.', 'ps-pen-paper' ) );
	}
	return ps_pen_paper_atomic_event( $scope->ID, function () use ( $scope, $input, $revision ) {
		$fresh = ps_pen_paper_get_character_scope( $scope->ID );
		if ( ! $fresh || ! ps_pen_paper_can_manage_character_scope( $fresh ) ) { return new WP_Error( 'forbidden', __( 'Die Verwaltungsberechtigung ist nicht mehr gültig.', 'ps-pen-paper' ) ); }
		$source = ps_pen_paper_currency_scope( $fresh );
		if ( is_wp_error( $source ) ) { return $source; }
		if ( $source->ID !== $fresh->ID ) { return new WP_Error( 'universe_currency', __( 'Diese Kampagne verwendet die Währungseinstellungen ihres Universums.', 'ps-pen-paper' ) ); }
		$before = ps_pen_paper_currency_document( $fresh->ID );
		if ( is_wp_error( $before ) ) { return $before; }
		if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision ) { return new WP_Error( 'conflict', __( 'Die Währung wurde geändert. Bitte lade die Einstellungen neu.', 'ps-pen-paper' ) ); }
		$after = array( 'revision' => $before['revision'] + 1, 'currency_id' => $before['currency_id'] ?: wp_generate_uuid4(),
			'name' => sanitize_text_field( $input['name'] ), 'symbol' => sanitize_text_field( $input['symbol'] ),
			'wallet_mode' => $input['wallet_mode'], 'in_use' => $before['in_use'] );
		if ( ! ps_pen_paper_valid_currency( $after ) || ( 'pspp_campaign' === $fresh->post_type && 'campaign' !== $after['wallet_mode'] ) ) {
			return new WP_Error( 'invalid_currency', __( 'Bitte gib einen Namen (höchstens 200 Bytes), ein Kürzel (24 Bytes) und ein zulässiges Guthabenmodell ein.', 'ps-pen-paper' ) );
		}
		if ( $before['in_use'] && $before['wallet_mode'] !== $after['wallet_mode'] ) {
			return new WP_Error( 'currency_in_use', __( 'Nach der ersten Buchung bleibt das Guthabenmodell fest. Ein Wechsel benötigt eine gesonderte Übernahme der Guthaben.', 'ps-pen-paper' ) );
		}
		return ps_pen_paper_write_document_event( $fresh->ID, $fresh->ID, '_pspp_currency', $before, $after,
			array( 'kind' => 'currency_saved', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
	} );
}

function ps_pen_paper_valid_wallet( $value ) {
	return is_array( $value ) && isset( $value['revision'], $value['currency_id'], $value['currency_scope_id'], $value['name'], $value['symbol'], $value['balance'] ) &&
		is_int( $value['revision'] ) && $value['revision'] >= 0 && is_string( $value['currency_id'] ) && wp_is_uuid( $value['currency_id'] ) &&
		is_int( $value['currency_scope_id'] ) && $value['currency_scope_id'] > 0 &&
		is_string( $value['name'] ) && '' !== trim( $value['name'] ) && strlen( $value['name'] ) <= 200 &&
		is_string( $value['symbol'] ) && '' !== trim( $value['symbol'] ) && strlen( $value['symbol'] ) <= 24 &&
		is_int( $value['balance'] ) && $value['balance'] >= 0 && $value['balance'] <= 999999999 &&
		( $value['revision'] > 0 || 0 === $value['balance'] );
}

function ps_pen_paper_wallet_context( $scope, $character_id ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	$character = ps_pen_paper_can_access_site() ? ps_pen_paper_get_character( $scope, $character_id ) : null;
	if ( ! $character ) { return new WP_Error( 'forbidden', __( 'Dieses Guthaben ist für dich nicht verfügbar.', 'ps-pen-paper' ) ); }
	return ps_pen_paper_resolve_wallet_context( $scope, $character );
}

// Internal resolver; the caller checks the character and the operation's authority.
function ps_pen_paper_resolve_wallet_context( $scope, $character ) {
	$state = ps_pen_paper_resolve_character_state( $scope, $character, false );
	if ( is_wp_error( $state ) ) { return $state; }
	if ( ! $state['active'] ) { return new WP_Error( 'unapproved_character', __( 'Guthaben benötigen einen freigegebenen Charakter.', 'ps-pen-paper' ) ); }
	$currency_scope = ps_pen_paper_currency_scope( $scope );
	if ( is_wp_error( $currency_scope ) ) { return $currency_scope; }
	$currency = ps_pen_paper_currency_document( $currency_scope->ID );
	if ( is_wp_error( $currency ) ) { return $currency; }
	if ( ! $currency['revision'] ) { return new WP_Error( 'currency_missing', __( 'Die Spielleitung hat noch keine Währung eingerichtet.', 'ps-pen-paper' ) ); }
	$target = $character;
	$wallet_scope = $scope;
	if ( 'universe' === $currency['wallet_mode'] && 'pspp_campaign' === $scope->post_type ) {
		if ( ! isset( $state['context'] ) || 'shared' !== $state['context']['mode'] ) {
			return new WP_Error( 'shared_wallet_required', __( 'Dieses Universum führt gemeinsame Guthaben. Dafür muss der Charakter als gemeinsamer Basisstand aufgenommen sein; lokale Bögen und unabhängige Kopien erhalten kein Ersatzkonto.', 'ps-pen-paper' ) );
		}
		$target = get_post( $state['context']['source_id'] );
		$wallet_scope = $currency_scope;
	} elseif ( 'campaign' === $currency['wallet_mode'] && 'pspp_universe' === $scope->post_type ) {
		return new WP_Error( 'campaign_wallet', __( 'Guthaben werden nach diesen Einstellungen getrennt an den Kampagnenbögen geführt.', 'ps-pen-paper' ) );
	}
	$wallet = get_post_meta( $target->ID, '_pspp_wallet', true );
	if ( '' === $wallet ) {
		$wallet = array( 'revision' => 0, 'currency_id' => $currency['currency_id'], 'currency_scope_id' => (int) $currency_scope->ID,
			'name' => $currency['name'], 'symbol' => $currency['symbol'], 'balance' => 0 );
	}
	if ( ! ps_pen_paper_valid_wallet( $wallet ) || $wallet['currency_id'] !== $currency['currency_id'] || $wallet['currency_scope_id'] !== (int) $currency_scope->ID ) {
		return new WP_Error( 'invalid_wallet', __( 'Das gespeicherte Guthaben passt nicht zur Währung. Es wird nicht umgerechnet oder zurückgesetzt.', 'ps-pen-paper' ) );
	}
	return array( 'character' => $target, 'character_name' => $state['active']['name'], 'scope' => $wallet_scope, 'currency_scope' => $currency_scope, 'currency' => $currency, 'wallet' => $wallet );
}

function ps_pen_paper_change_wallet( $scope, $character_id, $operation, $amount, $reason, $revision, $currency_revision ) {
	$initial = ps_pen_paper_wallet_context( $scope, $character_id );
	if ( is_wp_error( $initial ) ) { return $initial; }
	if ( ! ps_pen_paper_can_manage_character_scope( $initial['scope'] ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die zuständige Spielleitung darf buchen; gemeinsame Universumsguthaben verwaltet ausschließlich die Universumsleitung.', 'ps-pen-paper' ) );
	}
	if ( ! in_array( $operation, array( 'grant', 'debit' ), true ) || ! is_string( $amount ) || ! preg_match( '/^[1-9]\d{0,8}$/D', $amount ) ||
		! is_string( $reason ) || '' === trim( sanitize_textarea_field( $reason ) ) || strlen( $reason ) > 1000 ) {
		return new WP_Error( 'invalid_amount', __( 'Bitte wähle eine Buchungsart, einen ganzen Betrag von 1 bis 999999999 und eine Begründung mit höchstens 1000 Bytes. Keine Nachkommastellen.', 'ps-pen-paper' ) );
	}
	$target_id = (int) $initial['character']->ID;
	$currency_scope_id = (int) $initial['currency_scope']->ID;
	$write = function () use ( $scope, $character_id, $target_id, $currency_scope_id, $initial, $operation, $amount, $reason, $revision, $currency_revision ) {
		try {
			return ps_pen_paper_atomic_event( $currency_scope_id, function () use ( $scope, $character_id, $target_id, $currency_scope_id, $initial, $operation, $amount, $reason, $revision, $currency_revision ) {
				global $wpdb;
				// Lock the campaign link and account before reading either; currency settings are already locked.
				foreach ( array_unique( array( (int) $scope->ID, (int) $character_id, $target_id ) ) as $id ) {
					if ( $id === $currency_scope_id ) { continue; }
					if ( $id !== (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $id ) ) ) {
						return new WP_Error( 'conflict', __( 'Der Spiel- oder Charakterstand ist nicht mehr verfügbar. Bitte lade neu.', 'ps-pen-paper' ) );
					}
					clean_post_cache( $id );
				}
				$fresh = ps_pen_paper_get_character_scope( $scope->ID );
				if ( ! $fresh ) { return new WP_Error( 'forbidden', __( 'Der Spielbereich ist nicht mehr verfügbar.', 'ps-pen-paper' ) ); }
				$current = ps_pen_paper_wallet_context( $fresh, $character_id );
				if ( is_wp_error( $current ) ) { return $current; }
				if ( ! ps_pen_paper_can_manage_character_scope( $current['scope'] ) ) { return new WP_Error( 'forbidden', __( 'Die Buchungsberechtigung ist nicht mehr gültig.', 'ps-pen-paper' ) ); }
				if ( $target_id !== (int) $current['character']->ID || $currency_scope_id !== (int) $current['currency_scope']->ID ||
					$current['currency']['currency_id'] !== $initial['currency']['currency_id'] || ! is_string( $revision ) || (string) $current['wallet']['revision'] !== $revision ||
					! is_string( $currency_revision ) || (string) $current['currency']['revision'] !== $currency_revision ) {
					return new WP_Error( 'conflict', __( 'Guthaben, Währung oder Zuordnung wurden geändert. Bitte lade neu; es wurde nichts gebucht.', 'ps-pen-paper' ) );
				}
				return ps_pen_paper_write_wallet_booking( $current, $operation, (int) $amount, $reason );
			} );
		} finally {
			foreach ( array_unique( array( (int) $scope->ID, (int) $character_id, $target_id ) ) as $id ) { clean_post_cache( $id ); }
		}
	};
	return ps_pen_paper_with_character_lock( $character_id, function () use ( $character_id, $target_id, $write ) {
		return (int) $character_id === $target_id ? $write() : ps_pen_paper_with_character_lock( $target_id, $write );
	} );
}

// Internal writer: the caller holds the account locks and the surrounding event transaction.
function ps_pen_paper_write_wallet_booking( $current, $operation, $units, $reason ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( ! ps_pen_paper_can_manage_character_scope( $current['scope'] ) || ! in_array( $operation, array( 'grant', 'debit' ), true ) ||
		! is_int( $units ) || $units < 1 || $units > 999999999 || ! is_string( $reason ) || '' === trim( $reason ) || strlen( $reason ) > 1000 ) {
		return new WP_Error( 'invalid_booking', __( 'Die Guthabenbuchung ist nicht zulässig.', 'ps-pen-paper' ) );
	}
	return ps_pen_paper_write_wallet_delta( $current, $operation, $units, $reason );
}

// Internal transaction writer for an authorized booking or mutually confirmed trade.
function ps_pen_paper_write_wallet_delta( $current, $operation, $units, $reason ) {
	$before = $current['wallet'];
	if ( ( 'debit' === $operation && $before['balance'] < $units ) || ( 'grant' === $operation && 999999999 - $before['balance'] < $units ) ) {
		return new WP_Error( 'balance_range', __( 'Die Buchung würde das Guthaben unter 0 oder über 999999999 bringen. Es wurde nichts gebucht.', 'ps-pen-paper' ) );
	}
	$scope_id = (int) $current['currency_scope']->ID;
	if ( ! $current['currency']['in_use'] ) {
		$frozen = $current['currency']; $frozen['revision']++; $frozen['in_use'] = true;
		$result = ps_pen_paper_write_document_event( $scope_id, $scope_id, '_pspp_currency', $current['currency'], $frozen,
			array( 'kind' => 'currency_saved', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
		if ( is_wp_error( $result ) ) { return $result; }
	}
	$after = $before; $after['revision']++;
	$after['balance'] += 'grant' === $operation ? $units : -$units;
	$after['name'] = $current['currency']['name']; $after['symbol'] = $current['currency']['symbol'];
	return ps_pen_paper_write_document_event( $current['scope']->ID, $current['character']->ID, '_pspp_wallet', $before, $after,
		array( 'kind' => 'wallet_saved', 'visibility' => 'owner', 'recipient_id' => (int) $current['character']->post_author,
			'fields' => array(), 'rules_revision' => 0, 'reason' => sanitize_textarea_field( $reason ), 'character_name' => $current['character_name'] ), false );
}

function ps_pen_paper_write_wallet_transfer( $payer, $payee, $units, $reason ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( ! is_int( $units ) || $units < 1 || $units > 999999999 || $payer['character']->ID === $payee['character']->ID ||
		$payer['currency_scope']->ID !== $payee['currency_scope']->ID || $payer['currency']['currency_id'] !== $payee['currency']['currency_id'] ) { return new WP_Error( 'invalid_transfer', __( 'Diese Guthabenübertragung ist nicht zulässig.', 'ps-pen-paper' ) ); }
	$result = ps_pen_paper_write_wallet_delta( $payer, 'debit', $units, $reason );
	if ( is_wp_error( $result ) ) { return $result; }
	// The debit may have frozen the currency's account model.
	$payee['currency'] = ps_pen_paper_currency_document( $payee['currency_scope']->ID );
	if ( is_wp_error( $payee['currency'] ) ) { return $payee['currency']; }
	return ps_pen_paper_write_wallet_delta( $payee, 'grant', $units, $reason );
}

function ps_pen_paper_campaign_has_wallets( $campaign_id ) {
	return (bool) get_posts( array( 'post_type' => 'pspp_character', 'post_status' => 'private', 'post_parent' => $campaign_id,
		'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_pspp_wallet', 'compare' => 'EXISTS' ) ) ) );
}
