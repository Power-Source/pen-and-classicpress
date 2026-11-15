<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_transfer_error() {
	return new WP_Error( 'transfer_conflict', __( 'Diese Übertragung ist nicht zulässig oder der Stand wurde geändert. Prüfe Universum, Freigabe, Rechte für beide Kampagnen, Ausrüstung und Guthaben. Lade neu; es wurde nichts teilweise übertragen.', 'ps-pen-paper' ) );
}

function ps_pen_paper_valid_transfer_receipts( $value ) {
	if ( ! is_array( $value ) ) { return false; }
	foreach ( $value as $token => $hash ) {
		if ( ! is_string( $token ) || ! wp_is_uuid( $token ) || ! is_string( $hash ) ||
			! preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) { return false; }
	}
	return true;
}

function ps_pen_paper_transfer_context( $source, $target, $from, $to, $money ) {
	if ( ! ps_pen_paper_can_access_site() || ! $source || ! $target || 'pspp_campaign' !== $source->post_type ||
		'pspp_campaign' !== $target->post_type || $source->ID === $target->ID ||
		! ps_pen_paper_can_manage_campaign( $source ) || ! ps_pen_paper_can_manage_campaign( $target ) ) { return ps_pen_paper_transfer_error(); }
	$a = ps_pen_paper_get_universe_link( $source->ID ); $b = ps_pen_paper_get_universe_link( $target->ID );
	if ( is_wp_error( $a ) || is_wp_error( $b ) || ! $a['universe_id'] || $a['universe_id'] !== $b['universe_id'] ) { return ps_pen_paper_transfer_error(); }
	$universe = ps_pen_paper_get_universe( $a['universe_id'] );
	$policy = $universe ? ps_pen_paper_trade_policy( $universe ) : ps_pen_paper_transfer_error();
	if ( is_wp_error( $policy ) || empty( $policy['policy']['transfers'] ) ) { return ps_pen_paper_transfer_error(); }
	$parties = array( ps_pen_paper_trade_party( $source, $from ), ps_pen_paper_trade_party( $target, $to ) );
	if ( is_wp_error( $parties[0] ) || is_wp_error( $parties[1] ) ) { return ps_pen_paper_transfer_error(); }
	$wallets = array();
	if ( $money ) {
		foreach ( array( $source, $target ) as $i => $campaign ) {
			$wallet = ps_pen_paper_resolve_wallet_context( $campaign, $parties[ $i ]['post'] );
			if ( is_wp_error( $wallet ) ) { return $wallet; }
			if ( ! ps_pen_paper_can_manage_character_scope( $wallet['scope'] ) ) { return ps_pen_paper_transfer_error(); }
			$wallets[] = $wallet;
		}
		if ( $wallets[0]['character']->ID === $wallets[1]['character']->ID ) {
			return new WP_Error( 'shared_account', __( 'Beide Bögen verwenden bereits dasselbe Basisguthaben. Es gibt kein zweites Konto für eine Geldübertragung.', 'ps-pen-paper' ) );
		}
		if ( $wallets[0]['currency_scope']->ID !== $universe->ID || $wallets[1]['currency_scope']->ID !== $universe->ID ||
			$wallets[0]['currency']['currency_id'] !== $wallets[1]['currency']['currency_id'] ) {
			return new WP_Error( 'currency_mismatch', __( 'Geldübertragungen benötigen auf beiden Seiten dieselbe Universumswährung. Lokale Währungen werden nicht umgerechnet.', 'ps-pen-paper' ) );
		}
	}
	return array( 'universe' => $universe, 'policy' => $policy['policy'], 'parties' => $parties, 'wallets' => $wallets );
}

function ps_pen_paper_transfer_scope_locks( $ids, $write ) {
	if ( ! $ids ) { return $write(); }
	$id = array_shift( $ids );
	return ps_pen_paper_scope_lock( $id, function () use ( $ids, $write ) { return ps_pen_paper_transfer_scope_locks( $ids, $write ); } );
}

function ps_pen_paper_transfer_campaign_assets( $source, $target, $from, $to, $input ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( ! is_int( $from ) || ! is_int( $to ) || $from < 1 || $to < 1 || $from === $to || ! is_array( $input ) ||
		! isset( $input['items'], $input['amount'], $input['confirmation'], $input['token'], $input['epochs'] ) ||
		! is_array( $input['items'] ) || ! is_array( $input['epochs'] ) || 'confirmed' !== $input['confirmation'] ||
		! is_string( $input['token'] ) || ! wp_is_uuid( $input['token'] ) || ! is_string( $input['amount'] ) ||
		! preg_match( '/^(0|[1-9]\d{0,8})$/D', $input['amount'] ) ) { return ps_pen_paper_transfer_error(); }
	$items = $input['items'] ? ps_pen_paper_trade_selection( $input['items'] ) : array();
	if ( is_wp_error( $items ) || ( ! $items && '0' === $input['amount'] ) ) { return ps_pen_paper_transfer_error(); }
	foreach ( array( 'gear_revision', 'target_gear_revision', 'policy_revision' ) as $key ) {
		if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) { return ps_pen_paper_transfer_error(); }
	}
	foreach ( array( 'wallet_revision', 'target_wallet_revision', 'currency_revision', 'target_currency_revision' ) as $key ) {
		if ( isset( $input[ $key ] ) && ! is_string( $input[ $key ] ) ) { return ps_pen_paper_transfer_error(); }
	}
	$money = '0' !== $input['amount'];
	$context = ps_pen_paper_transfer_context( $source, $target, $from, $to, $money );
	if ( is_wp_error( $context ) ) { return $context; }
	$scopes = array( (int) $source->ID, (int) $target->ID, (int) $context['universe']->ID ); sort( $scopes, SORT_NUMERIC );
	$locks = array( $from, $to );
	foreach ( $context['wallets'] as $wallet ) { $locks[] = (int) $wallet['character']->ID; }
	$locks = array_values( array_unique( $locks ) ); sort( $locks, SORT_NUMERIC );
	$terms = array( $source->ID, $target->ID, $from, $to, $items, $input['amount'] );
	foreach ( array( 'gear_revision', 'target_gear_revision', 'policy_revision', 'wallet_revision', 'target_wallet_revision',
		'currency_revision', 'target_currency_revision' ) as $key ) { $terms[] = $input[ $key ] ?? ''; }
	foreach ( $scopes as $id ) { $terms[] = $input['epochs'][ $id ] ?? null; }
	$hash = hash( 'sha256', serialize( $terms ) );
	$assets = array();
	try {
		return ps_pen_paper_transfer_scope_locks( $scopes, function () use ( $scopes, $locks, $source, $target, $from, $to, $input, $items, $money, $hash, &$assets ) {
			foreach ( $scopes as $id ) {
				if ( ! isset( $input['epochs'][ $id ] ) || ! is_string( $input['epochs'][ $id ] ) ||
					$input['epochs'][ $id ] !== ps_pen_paper_scope_epoch( $id ) || ! ps_pen_paper_scope_observe( $id ) ) { return ps_pen_paper_transfer_error(); }
			}
			return ps_pen_paper_trade_locks( $locks, function () use ( $scopes, $locks, $source, $target, $from, $to, $input, $items, $money, $hash, &$assets ) {
				return ps_pen_paper_atomic_event( $scopes[0], function () use ( $scopes, $locks, $source, $target, $from, $to, $input, $items, $money, $hash, &$assets ) {
					global $wpdb;
					$rows = array_merge( $scopes, $locks ); sort( $rows, SORT_NUMERIC );
					foreach ( array_unique( $rows ) as $id ) {
						if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID=%d FOR UPDATE", $id ) ) !== $id ) { return ps_pen_paper_transfer_error(); }
						clean_post_cache( $id );
					}
					$source = ps_pen_paper_get_campaign( $source->ID ); $target = ps_pen_paper_get_campaign( $target->ID );
					$context = ps_pen_paper_transfer_context( $source, $target, $from, $to, $money );
					if ( is_wp_error( $context ) ) { return $context; }
					if ( ! in_array( (int) $context['universe']->ID, $scopes, true ) ) { return ps_pen_paper_transfer_error(); }
					$receipts = get_post_meta( $from, '_pspp_transfer_receipts', true );
					if ( '' === $receipts ) { $receipts = array(); }
					if ( ! ps_pen_paper_valid_transfer_receipts( $receipts ) ) { return ps_pen_paper_transfer_error(); }
					if ( isset( $receipts[ $input['token'] ] ) ) {
						return $receipts[ $input['token'] ] === $hash ? 0 : ps_pen_paper_transfer_error();
					}
					if ( (string) $context['policy']['revision'] !== $input['policy_revision'] ) { return ps_pen_paper_transfer_error(); }
					foreach ( array( $from, $to ) as $i => $id ) {
						$gear = ps_pen_paper_gear_state( $id );
						$key = $i ? 'target_gear_revision' : 'gear_revision';
						if ( is_wp_error( $gear ) || (string) $gear['revision'] !== $input[ $key ] ) { return ps_pen_paper_transfer_error(); }
					}
					foreach ( $context['wallets'] as $i => $wallet ) {
						$wallet_key = $i ? 'target_wallet_revision' : 'wallet_revision'; $currency_key = $i ? 'target_currency_revision' : 'currency_revision';
						if ( ! in_array( (int) $wallet['character']->ID, $locks, true ) ||
							! isset( $input[ $wallet_key ], $input[ $currency_key ] ) || ! is_string( $input[ $wallet_key ] ) || ! is_string( $input[ $currency_key ] ) ||
							(string) $wallet['wallet']['revision'] !== $input[ $wallet_key ] || (string) $wallet['currency']['revision'] !== $input[ $currency_key ] ) { return ps_pen_paper_transfer_error(); }
					}
					$frozen = array(); $incoming = array(); $copied_assets = array();
					$rules = ps_pen_paper_get_rules( $target->ID );
					$fields = array_column( $rules['fields'], null, 'id' );
					foreach ( $items as $uuid ) {
						$instance = ps_pen_paper_trade_item( $source, $from, $uuid );
						if ( is_wp_error( $instance ) ) { return $instance; }
						if ( ! in_array( $instance['scope_id'], array( (int) $source->ID, (int) $context['universe']->ID ), true ) ) { return ps_pen_paper_transfer_error(); }
						foreach ( $instance['item']['bonuses'] as $field => $bonus ) {
							if ( ! isset( $fields[ $field ] ) || ! in_array( $fields[ $field ]['type'], array( 'attribute', 'number' ), true ) ) { return ps_pen_paper_transfer_error(); }
						}
						$frozen[ $uuid ] = $instance;
						if ( $instance['scope_id'] === (int) $source->ID ) {
							$asset_id = $instance['item']['asset_id'];
							if ( $asset_id ) {
								if ( ! isset( $copied_assets[ $asset_id ] ) ) {
									$image = ps_pen_paper_private_image_data( $source, $asset_id );
									if ( is_wp_error( $image ) ) { return $image; }
									$asset = ps_pen_paper_record_post( $asset_id );
									$copy = wp_insert_post( wp_slash( array( 'post_type' => 'pspp_asset', 'post_status' => 'private',
										'post_parent' => $target->ID, 'post_author' => get_current_user_id(), 'post_title' => 'PS Pen & Paper Item Image',
										'post_mime_type' => $asset->post_mime_type, 'post_content' => $asset->post_content ) ), true );
									if ( is_wp_error( $copy ) || ! $copy ) { return ps_pen_paper_transfer_error(); }
									$assets[] = $copy; $copied_assets[ $asset_id ] = $copy;
								}
								$instance['item']['asset_id'] = $copied_assets[ $asset_id ];
							}
							$instance['scope_id'] = (int) $target->ID;
						}
						$incoming[ $uuid ] = $instance;
					}
					if ( $money ) {
						$reason = __( 'Bestätigte Übertragung zwischen Kampagnen', 'ps-pen-paper' ) . ' #' . $source->ID . ' → #' . $target->ID;
						$result = ps_pen_paper_write_wallet_transfer( $context['wallets'][0], $context['wallets'][1], (int) $input['amount'], $reason );
						if ( is_wp_error( $result ) ) { return $result; }
					}
					if ( $items ) {
						$result = ps_pen_paper_write_item_exchange( array( $source, $target ),
							array( $context['parties'][0]['post'], $context['parties'][1]['post'] ), array( $items, array() ),
							array( $frozen, array() ), array( $incoming, array() ) );
						if ( is_wp_error( $result ) ) { return $result; }
					}
					$receipts[ $input['token'] ] = $hash;
					update_post_meta( $from, '_pspp_transfer_receipts', $receipts );
					return $receipts === get_post_meta( $from, '_pspp_transfer_receipts', true ) ? 0 : ps_pen_paper_transfer_error();
				} );
			} );
		} );
	} finally {
		foreach ( array_unique( array_merge( $scopes, $locks, $assets ) ) as $id ) { clean_post_cache( $id ); }
	}
}
