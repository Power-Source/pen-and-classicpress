<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_register_trade_type() {
	register_post_type( 'pspp_trade', array( 'public' => false, 'publicly_queryable' => false, 'exclude_from_search' => true,
		'show_ui' => false, 'show_in_rest' => false, 'supports' => false, 'capability_type' => array( 'pspp_trade', 'pspp_trades' ), 'map_meta_cap' => true ) );
}

function ps_pen_paper_trade_kinds() {
	return array( 'gift' => __( 'Geschenk', 'ps-pen-paper' ), 'sale' => __( 'Verkauf', 'ps-pen-paper' ), 'swap' => __( 'Tausch', 'ps-pen-paper' ),
		'money_gift' => __( 'Geldgeschenk', 'ps-pen-paper' ) );
}

function ps_pen_paper_trade_statuses() {
	return array( 'offered' => __( 'Angeboten', 'ps-pen-paper' ), 'countered' => __( 'Tauschgegenstand vorgeschlagen', 'ps-pen-paper' ),
		'accepted' => __( 'Wartet auf Spielleiterfreigabe', 'ps-pen-paper' ), 'completed' => __( 'Abgeschlossen', 'ps-pen-paper' ),
		'cancelled' => __( 'Zurückgezogen', 'ps-pen-paper' ), 'declined' => __( 'Abgelehnt', 'ps-pen-paper' ), 'rejected' => __( 'Durch Spielleitung abgelehnt', 'ps-pen-paper' ) );
}

function ps_pen_paper_trade_actions() {
	return array( 'offer' => __( 'Angebot erstellt', 'ps-pen-paper' ), 'counter' => __( 'Tauschgegenstand bestätigt', 'ps-pen-paper' ),
		'play_ended' => __( 'Teilnahme oder Runde beendet', 'ps-pen-paper' ),
		'accept' => __( 'Bedingungen angenommen', 'ps-pen-paper' ), 'approve' => __( 'Durch Spielleitung freigegeben', 'ps-pen-paper' ),
		'cancel' => __( 'Angebot zurückgezogen', 'ps-pen-paper' ), 'decline' => __( 'Angebot abgelehnt', 'ps-pen-paper' ), 'reject' => __( 'Durch Spielleitung abgelehnt', 'ps-pen-paper' ) );
}

function ps_pen_paper_trade_error() {
	return new WP_Error( 'trade_conflict', __( 'Dieser Handel ist nicht zulässig oder der Stand wurde geändert. Prüfe Rechte, Handelsregeln, Reservierungen, Ausrüstung und Guthaben und lade neu. Es wurde nichts teilweise übertragen.', 'ps-pen-paper' ) );
}

function ps_pen_paper_trade_policy_default() {
	return array( 'revision' => 0, 'kinds' => array(), 'approval' => true );
}

function ps_pen_paper_valid_trade_policy( $state ) {
	if ( ! is_array( $state ) || ! isset( $state['kinds'] ) || ! is_array( $state['kinds'] ) ) { return false; }
	if ( array_key_exists( 'transfers', $state ) && ! is_bool( $state['transfers'] ) ) { return false; }
	foreach ( $state['kinds'] as $kind ) { if ( ! is_string( $kind ) || ! isset( ps_pen_paper_trade_kinds()[ $kind ] ) ) { return false; } }
	return is_array( $state ) && isset( $state['revision'], $state['kinds'], $state['approval'] ) && is_int( $state['revision'] ) && $state['revision'] >= 0 &&
		is_array( $state['kinds'] ) && array_values( $state['kinds'] ) === $state['kinds'] && count( array_unique( $state['kinds'], SORT_REGULAR ) ) === count( $state['kinds'] ) &&
		! array_diff( $state['kinds'], array_keys( ps_pen_paper_trade_kinds() ) ) && is_bool( $state['approval'] ) &&
		( $state['revision'] > 0 || $state === ps_pen_paper_trade_policy_default() );
}

function ps_pen_paper_trade_policy( $scope ) {
	$owner = ps_pen_paper_currency_scope( $scope );
	if ( is_wp_error( $owner ) ) { return $owner; }
	$state = get_post_meta( $owner->ID, '_pspp_trade_policy', true );
	if ( '' === $state ) { $state = ps_pen_paper_trade_policy_default(); }
	return ps_pen_paper_valid_trade_policy( $state ) ? array( 'scope' => $owner, 'policy' => $state ) : ps_pen_paper_trade_error();
}

function ps_pen_paper_save_trade_policy( $scope, $input, $revision ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( ! $scope || ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_character_scope( $scope ) || ! is_array( $input ) ||
		! isset( $input['kinds'], $input['approval'] ) || ! is_array( $input['kinds'] ) || ! in_array( $input['approval'], array( '0', '1' ), true ) ) { return ps_pen_paper_trade_error(); }
	return ps_pen_paper_atomic_event( $scope->ID, function () use ( $scope, $input, $revision ) {
		$scope = ps_pen_paper_get_character_scope( $scope->ID );
		if ( ! $scope || ! ps_pen_paper_can_manage_character_scope( $scope ) ) { return ps_pen_paper_trade_error(); }
		$current = ps_pen_paper_trade_policy( $scope );
		if ( is_wp_error( $current ) || $current['scope']->ID !== $scope->ID || ! is_string( $revision ) || (string) $current['policy']['revision'] !== $revision ) { return ps_pen_paper_trade_error(); }
		$before = $current['policy']; $after = array( 'revision' => $before['revision'] + 1, 'kinds' => array_values( $input['kinds'] ), 'approval' => '1' === $input['approval'] );
		if ( 'pspp_universe' === $scope->post_type ) {
			if ( isset( $input['transfers'] ) && '1' !== $input['transfers'] ) { return ps_pen_paper_trade_error(); }
			$after['transfers'] = isset( $input['transfers'] );
		} elseif ( isset( $input['transfers'] ) ) { return ps_pen_paper_trade_error(); }
		if ( ! ps_pen_paper_valid_trade_policy( $after ) ) { return ps_pen_paper_trade_error(); }
		return ps_pen_paper_write_document_event( $scope->ID, $scope->ID, '_pspp_trade_policy', $before, $after,
			array( 'kind' => 'trade_policy', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
	} );
}

// Exposes only the identity needed to address an offer, never a foreign sheet.
function ps_pen_paper_trade_party( $campaign, $id ) {
	if ( ! ps_pen_paper_can_access_site() || ! $campaign || 'pspp_campaign' !== $campaign->post_type || ! ps_pen_paper_can_view_campaign( $campaign ) ) { return ps_pen_paper_trade_error(); }
	$post = ps_pen_paper_record_post( $id );
	if ( ! $post || 'pspp_character' !== $post->post_type || 'private' !== $post->post_status || (int) $post->post_parent !== (int) $campaign->ID ||
		! is_user_member_of_blog( (int) $post->post_author ) || ! ps_pen_paper_campaign_role( $campaign, (int) $post->post_author ) ) { return ps_pen_paper_trade_error(); }
	$state = ps_pen_paper_resolve_character_state( $campaign, $post, false );
	return is_wp_error( $state ) || ! $state['active'] ? ps_pen_paper_trade_error() : array( 'post' => $post, 'name' => $state['active']['name'] );
}

function ps_pen_paper_valid_trade( $state ) {
	if ( ! is_array( $state ) || ! isset( $state['revision'] ) || ! is_int( $state['revision'] ) || $state['revision'] < 0 || ! array_key_exists( 'offer', $state ) ) { return false; }
	if ( ! $state['revision'] ) { return null === $state['offer']; }
	if ( ! isset( $state['status'], $state['history'] ) || ! is_string( $state['status'] ) || ! isset( ps_pen_paper_trade_statuses()[ $state['status'] ] ) || ! is_array( $state['history'] ) ||
		count( $state['history'] ) !== $state['revision'] || count( $state['history'] ) > 5 ) { return false; }
	foreach ( $state['history'] as $entry ) {
		if ( ! is_array( $entry ) || ! isset( $entry['action'], $entry['actor_id'], $entry['date'] ) || ! is_string( $entry['action'] ) ||
			! isset( ps_pen_paper_trade_actions()[ $entry['action'] ] ) || ! is_int( $entry['actor_id'] ) || $entry['actor_id'] < 1 || ! is_string( $entry['date'] ) ) { return false; }
	}
	$o = $state['offer'];
	if ( ! is_array( $o ) ) { return false; }
	foreach ( array( 'from', 'to', 'from_owner', 'to_owner', 'policy_scope', 'policy_revision', 'amount' ) as $key ) {
		if ( ! isset( $o[ $key ] ) || ! is_int( $o[ $key ] ) || $o[ $key ] < ( 'amount' === $key ? 0 : 1 ) ) { return false; }
	}
	if ( $o['from'] === $o['to'] || $o['from_owner'] === $o['to_owner'] || $o['amount'] > 999999999 ||
		! isset( $o['kind'], $o['token'], $o['request_hash'], $o['from_name'], $o['to_name'], $o['give_uuid'], $o['take_uuid'] ) ||
		! is_string( $o['kind'] ) || ! isset( ps_pen_paper_trade_kinds()[ $o['kind'] ] ) || ! is_string( $o['token'] ) || ! wp_is_uuid( $o['token'] ) ||
		! is_string( $o['request_hash'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $o['request_hash'] ) ||
		! is_string( $o['from_name'] ) || ! is_string( $o['to_name'] ) || ! is_string( $o['give_uuid'] ) || ! array_key_exists( 'give', $o ) ||
		! is_string( $o['take_uuid'] ) || ! array_key_exists( 'take', $o ) || ! array_key_exists( 'currency', $o ) ) { return false; }
	if ( 'money_gift' === $o['kind'] ) {
		if ( '' !== $o['give_uuid'] || null !== $o['give'] ) { return false; }
	} elseif ( ! wp_is_uuid( $o['give_uuid'] ) || ! ps_pen_paper_valid_item_instance( $o['give'] ) ||
		'tradeable' !== ps_pen_paper_item_binding( $o['give']['item'] ) ) { return false; }
	if ( '' !== $o['take_uuid'] && ( ! wp_is_uuid( $o['take_uuid'] ) || ! ps_pen_paper_valid_item_instance( $o['take'] ) || 'swap' !== $o['kind'] ||
		'tradeable' !== ps_pen_paper_item_binding( $o['take']['item'] ) ) ) { return false; }
	if ( '' === $o['take_uuid'] && null !== $o['take'] ) { return false; }
	foreach ( array( 'give', 'take' ) as $side ) {
		if ( ! array_key_exists( $side . '_more', $o ) ) { continue; }
		$more = $o[ $side . '_more' ];
		if ( ! is_array( $more ) || count( $more ) > 99 || 'money_gift' === $o['kind'] ||
			( 'take' === $side && ( 'swap' !== $o['kind'] || '' === $o['take_uuid'] ) ) ) { return false; }
		foreach ( $more as $uuid => $instance ) {
			if ( ! is_string( $uuid ) || ! wp_is_uuid( $uuid ) || $uuid === $o[ $side . '_uuid' ] ||
				! ps_pen_paper_valid_item_instance( $instance ) || 'tradeable' !== ps_pen_paper_item_binding( $instance['item'] ) ) { return false; }
		}
	}
	return in_array( $o['kind'], array( 'sale', 'money_gift' ), true ) ? $o['amount'] > 0 && ps_pen_paper_valid_currency( $o['currency'] ) && $o['currency']['revision'] > 0
		: 0 === $o['amount'] && null === $o['currency'];
}

function ps_pen_paper_trade_items( $offer, $side ) {
	$items = $offer[ $side . '_uuid' ] ? array( $offer[ $side . '_uuid' ] => $offer[ $side ] ) : array();
	return $items + ( $offer[ $side . '_more' ] ?? array() );
}

function ps_pen_paper_trade_selection( $input, $money = false ) {
	$ids = is_array( $input ) ? $input : ( '' === $input ? array() : array( $input ) );
	if ( array_values( $ids ) !== $ids || count( $ids ) > 100 || ( $money ? count( $ids ) > 0 : ! $ids ) ) { return ps_pen_paper_trade_error(); }
	$seen = array();
	foreach ( $ids as $id ) {
		if ( ! is_string( $id ) || ! wp_is_uuid( $id ) || isset( $seen[ $id ] ) ) { return ps_pen_paper_trade_error(); }
		$seen[ $id ] = true;
	}
	return $ids;
}

function ps_pen_paper_trade_open( $state ) {
	return in_array( $state['status'], array( 'offered', 'countered', 'accepted' ), true );
}

// Internal reservation scan; participant-facing reads use trade_records().
function ps_pen_paper_trade_records_internal( $campaign ) {
	if ( ! ps_pen_paper_can_access_site() || ! $campaign || 'pspp_campaign' !== $campaign->post_type || ! ps_pen_paper_can_view_campaign( $campaign ) ) { return ps_pen_paper_trade_error(); }
	$records = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_trade', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'DESC' ) ) as $post ) {
		$state = get_post_meta( $post->ID, '_pspp_trade', true );
		if ( ! ps_pen_paper_valid_trade( $state ) || ! $state['revision'] ) { return ps_pen_paper_trade_error(); }
		$records[ $post->ID ] = $state;
	}
	return $records;
}

function ps_pen_paper_trade_records( $campaign ) {
	$records = ps_pen_paper_trade_records_internal( $campaign );
	if ( is_wp_error( $records ) ) { return $records; }
	return array_filter( $records, function ( $state ) use ( $campaign ) { return ps_pen_paper_can_read_trade( $campaign, $state ); } );
}

function ps_pen_paper_can_read_trade( $campaign, $state ) {
	return ps_pen_paper_can_access_site() && ps_pen_paper_can_view_campaign( $campaign ) &&
		( ps_pen_paper_can_manage_campaign( $campaign ) || in_array( get_current_user_id(), array( $state['offer']['from_owner'], $state['offer']['to_owner'] ), true ) );
}

function ps_pen_paper_reserved_trade_items( $campaign, $character_id, $except = 0 ) {
	$target = ps_pen_paper_shared_gear_target( $character_id );
	if ( is_wp_error( $target ) ) { return $target; }
	if ( $target ) { return ps_pen_paper_play_reserved_items( $target, $except ); }
	$records = ps_pen_paper_trade_records_internal( $campaign );
	if ( is_wp_error( $records ) ) { return $records; }
	$ids = array();
	foreach ( $records as $id => $state ) {
		if ( $id === $except || ! ps_pen_paper_trade_open( $state ) ) { continue; }
		$o = $state['offer'];
		if ( $o['from'] === $character_id ) { $ids = array_merge( $ids, array_keys( ps_pen_paper_trade_items( $o, 'give' ) ) ); }
		if ( $o['to'] === $character_id ) { $ids = array_merge( $ids, array_keys( ps_pen_paper_trade_items( $o, 'take' ) ) ); }
	}
	return $ids;
}

function ps_pen_paper_check_reserved_gear( $campaign, $character_id, $snapshot ) {
	$ids = ps_pen_paper_reserved_trade_items( $campaign, $character_id );
	return is_wp_error( $ids ) ? $ids : ( array_intersect( $ids, array_values( $snapshot['slots'] ) ) ? new WP_Error( 'item_reserved', __( 'Für einen Handel reservierte Exemplare können nicht angelegt werden. Ziehe zuerst das Angebot zurück.', 'ps-pen-paper' ) ) : true );
}

function ps_pen_paper_trade_item( $campaign, $character_id, $uuid, $except = 0 ) {
	$gear = ps_pen_paper_gear_state( $character_id ); $reserved = ps_pen_paper_reserved_trade_items( $campaign, $character_id, $except );
	if ( is_wp_error( $gear ) ) { return $gear; }
	if ( is_wp_error( $reserved ) ) { return $reserved; }
	if ( ! is_string( $uuid ) || ! isset( $gear['active']['items'][ $uuid ] ) ||
		in_array( $uuid, $reserved, true ) || in_array( $uuid, $gear['active']['slots'], true ) ||
		( $gear['pending'] && ( ! isset( $gear['pending']['items'][ $uuid ] ) || in_array( $uuid, $gear['pending']['slots'], true ) ) ) ||
		'tradeable' !== ps_pen_paper_item_binding( $gear['active']['items'][ $uuid ]['item'] ) ) { return new WP_Error( 'item_unavailable', ps_pen_paper_trade_error()->get_error_message() ); }
	return $gear['active']['items'][ $uuid ];
}

function ps_pen_paper_trade_locks( $ids, $write ) {
	if ( ! $ids ) { return $write(); }
	$id = array_shift( $ids );
	return ps_pen_paper_with_character_lock( $id, function () use ( $ids, $write ) { return ps_pen_paper_trade_locks( $ids, $write ); } );
}

function ps_pen_paper_trade_transaction( $campaign, $from, $to, $sale, $write ) {
	$policy = ps_pen_paper_trade_policy( $campaign );
	if ( is_wp_error( $policy ) ) { return $policy; }
	$ids = array( $from, $to ); $scope_id = (int) $policy['scope']->ID;
	$shared = false; $targets = array();
	foreach ( array( $from, $to ) as $id ) {
		$target = ps_pen_paper_shared_gear_target( $id );
		if ( is_wp_error( $target ) ) { return $target; }
		$targets[ $id ] = $target;
		if ( $target ) { $shared = true; $ids[] = $target['character_id']; $ids[] = $target['scope_id']; }
	}
	if ( $sale ) {
		foreach ( array( $from, $to ) as $id ) {
			$party = ps_pen_paper_trade_party( $campaign, $id );
			if ( is_wp_error( $party ) ) { return $party; }
			$wallet = ps_pen_paper_resolve_wallet_context( $campaign, $party['post'] );
			if ( is_wp_error( $wallet ) ) { return $wallet; }
			$ids[] = (int) $wallet['character']->ID;
		}
	}
	$ids = array_values( array_unique( $ids ) ); sort( $ids, SORT_NUMERIC );
	$transaction = function () use ( $campaign, $ids, $scope_id, $write, $from, $to, $sale, $shared, $targets ) {
				global $wpdb;
				foreach ( array_unique( array_merge( array( (int) $campaign->ID ), $ids ) ) as $id ) {
					if ( $id !== $scope_id && (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $id ) ) !== $id ) { return ps_pen_paper_trade_error(); }
					clean_post_cache( $id );
				}
				$fresh = ps_pen_paper_get_campaign( $campaign->ID ); $policy = $fresh ? ps_pen_paper_trade_policy( $fresh ) : ps_pen_paper_trade_error();
				if ( is_wp_error( $policy ) || (int) $policy['scope']->ID !== $scope_id ) { return ps_pen_paper_trade_error(); }
				if ( $shared ) {
					$round = ps_pen_paper_play_round( $fresh->ID );
					if ( is_wp_error( $round ) || ( $round && ( 'active' !== $round['status'] ||
						array_diff( array( $from, $to ), array_column( array_filter( $round['participants'], static function ( $participant ) { return $participant['active']; } ), 'character_id' ) ) ) ) ) { return ps_pen_paper_trade_error(); }
					foreach ( array( $from, $to ) as $id ) {
						$target = ps_pen_paper_shared_gear_target( $id );
						if ( is_wp_error( $target ) || $target !== $targets[ $id ] ) { return ps_pen_paper_trade_error(); }
						if ( (int) ps_pen_paper_record_post( $id )->post_author === get_current_user_id() ) {
							$allowed = ps_pen_paper_require_character_game_action( $fresh, $id );
							if ( is_wp_error( $allowed ) ) { return $allowed; }
						}
					}
				}
				if ( $sale ) {
					foreach ( array( $from, $to ) as $id ) {
						$party = ps_pen_paper_trade_party( $fresh, $id );
						$wallet = is_wp_error( $party ) ? $party : ps_pen_paper_resolve_wallet_context( $fresh, $party['post'] );
						if ( is_wp_error( $wallet ) || ! in_array( (int) $wallet['character']->ID, $ids, true ) || (int) $wallet['currency_scope']->ID !== $scope_id ) { return ps_pen_paper_trade_error(); }
					}
				}
				return $write( $fresh, $policy );
	};
	try {
		if ( $shared ) { return ps_pen_paper_adventure_action_transaction( $campaign, array_merge( $ids, array( $scope_id ) ), $scope_id, $transaction ); }
		return ps_pen_paper_trade_locks( $ids, function () use ( $scope_id, $transaction ) { return ps_pen_paper_atomic_event( $scope_id, $transaction ); } );
	} finally { foreach ( array_merge( $ids, array( $scope_id, (int) $campaign->ID ) ) as $id ) { clean_post_cache( $id ); } }
}

function ps_pen_paper_write_trade( $campaign, &$id, $before, $after, $action ) {
	$new = ! $id;
	if ( $new ) { $id = ps_pen_paper_insert_record( 'pspp_trade', $campaign->ID, array(), false ); if ( is_wp_error( $id ) ) { return $id; } }
	$after['revision']++;
	$after['history'][] = array( 'action' => $action, 'actor_id' => get_current_user_id(), 'date' => current_time( 'mysql', true ) );
	if ( ! ps_pen_paper_valid_trade( $after ) ) { return ps_pen_paper_trade_error(); }
	return ps_pen_paper_write_document_event( $campaign->ID, $id, '_pspp_trade', $before, $after,
		array( 'kind' => 'trade_changed', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), $new );
}

function ps_pen_paper_offer_trade( $campaign, $from, $to, $kind, $uuid, $amount, $gear_revision, $policy_revision, $token ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	if ( ! ps_pen_paper_can_access_site() || ! $campaign || 'pspp_campaign' !== $campaign->post_type || ! ps_pen_paper_can_view_campaign( $campaign ) ||
		! is_int( $from ) || ! is_int( $to ) || $from < 1 || $to < 1 || $from === $to || ! is_string( $kind ) || ! isset( ps_pen_paper_trade_kinds()[ $kind ] ) ||
		! is_string( $amount ) || ! preg_match( '/^(0|[1-9]\d{0,8})$/D', $amount ) || ! is_string( $token ) || ! wp_is_uuid( $token ) ) { return ps_pen_paper_trade_error(); }
	$id = 0; $hash = hash( 'sha256', serialize( array( $from, $to, $kind, $uuid, $amount, $gear_revision, $policy_revision ) ) );
	$selection = ps_pen_paper_trade_selection( $uuid, 'money_gift' === $kind );
	if ( is_wp_error( $selection ) ) { return $selection; }
	$result = ps_pen_paper_trade_transaction( $campaign, $from, $to, in_array( $kind, array( 'sale', 'money_gift' ), true ), function ( $campaign, $policy ) use ( $from, $to, $kind, $selection, $amount, $gear_revision, $policy_revision, $token, $hash, &$id ) {
		$a = ps_pen_paper_trade_party( $campaign, $from ); $b = ps_pen_paper_trade_party( $campaign, $to );
		if ( is_wp_error( $a ) || is_wp_error( $b ) || (int) $a['post']->post_author !== get_current_user_id() || $a['post']->post_author === $b['post']->post_author ) { return ps_pen_paper_trade_error(); }
		$records = ps_pen_paper_trade_records( $campaign ); if ( is_wp_error( $records ) ) { return $records; }
		foreach ( $records as $record_id => $state ) {
			if ( $state['offer']['token'] === $token ) {
				if ( $state['offer']['request_hash'] !== $hash || $state['offer']['from_owner'] !== get_current_user_id() ) { return ps_pen_paper_trade_error(); }
				$id = $record_id; return 0;
			}
		}
		$gear = ps_pen_paper_gear_state( $from ); $items = array();
		foreach ( $selection as $uuid ) {
			$item = ps_pen_paper_trade_item( $campaign, $from, $uuid );
			if ( is_wp_error( $item ) ) { return $item; }
			$items[ $uuid ] = $item;
		}
		if ( is_wp_error( $gear ) || ! is_string( $gear_revision ) || (string) $gear['revision'] !== $gear_revision ||
			! is_string( $policy_revision ) || (string) $policy['policy']['revision'] !== $policy_revision || ! in_array( $kind, $policy['policy']['kinds'], true ) ||
			( in_array( $kind, array( 'sale', 'money_gift' ), true ) ? (int) $amount < 1 : '0' !== $amount ) ) { return ps_pen_paper_trade_error(); }
		$currency = in_array( $kind, array( 'sale', 'money_gift' ), true ) ? ps_pen_paper_currency_document( $policy['scope']->ID ) : null;
		if ( is_wp_error( $currency ) || ( $currency && ! $currency['revision'] ) ) { return ps_pen_paper_trade_error(); }
		$before = array( 'revision' => 0, 'offer' => null );
		$after = array( 'revision' => 0, 'status' => 'offered', 'history' => array(), 'offer' => array(
			'from' => $from, 'to' => $to, 'from_owner' => (int) $a['post']->post_author, 'to_owner' => (int) $b['post']->post_author,
			'from_name' => $a['name'], 'to_name' => $b['name'], 'kind' => $kind, 'amount' => (int) $amount, 'currency' => $currency,
			'policy_scope' => (int) $policy['scope']->ID, 'policy_revision' => $policy['policy']['revision'], 'token' => $token, 'request_hash' => $hash,
			'give_uuid' => $items ? array_key_first( $items ) : '', 'give' => $items ? reset( $items ) : null, 'take_uuid' => '', 'take' => null,
		) );
		if ( count( $items ) > 1 ) { $after['offer']['give_more'] = array_slice( $items, 1, null, true ); }
		return ps_pen_paper_write_trade( $campaign, $id, $before, $after, 'offer' );
	} );
	if ( is_int( $id ) && $id ) { clean_post_cache( $id ); }
	return is_wp_error( $result ) ? $result : $id;
}

function ps_pen_paper_change_trade( $campaign, $id, $action, $revision, $confirmation, $uuid = '' ) {
	if ( is_wp_error( $gate = ps_pen_paper_require_community() ) ) { return $gate; }
	$records = ps_pen_paper_trade_records( $campaign );
	if ( is_wp_error( $records ) || ! isset( $records[ $id ] ) || ! ps_pen_paper_can_read_trade( $campaign, $records[ $id ] ) || 'confirmed' !== $confirmation ) { return ps_pen_paper_trade_error(); }
	$initial = $records[ $id ]['offer'];
	$cancelling = in_array( $action, array( 'cancel', 'decline', 'reject' ), true );
	$result = ps_pen_paper_trade_transaction( $campaign, $initial['from'], $initial['to'], ! $cancelling && in_array( $initial['kind'], array( 'sale', 'money_gift' ), true ), function ( $campaign, $policy ) use ( $id, $action, $revision, $uuid, $cancelling ) {
		clean_post_cache( $id ); $before = get_post_meta( $id, '_pspp_trade', true );
		if ( ! ps_pen_paper_valid_trade( $before ) || ! ps_pen_paper_trade_open( $before ) || ! ps_pen_paper_can_read_trade( $campaign, $before ) ||
			! is_string( $revision ) || (string) $before['revision'] !== $revision ) { return ps_pen_paper_trade_error(); }
		$o = $before['offer']; $actor = get_current_user_id(); $gm = ps_pen_paper_can_manage_campaign( $campaign ); $after = $before;
		if ( $cancelling ) {
			if ( ( 'cancel' === $action && $actor !== $o['from_owner'] ) || ( 'decline' === $action && $actor !== $o['to_owner'] ) || ( 'reject' === $action && ! $gm ) ) { return ps_pen_paper_trade_error(); }
			$after['status'] = array( 'cancel' => 'cancelled', 'decline' => 'declined', 'reject' => 'rejected' )[ $action ];
		} else {
			if ( $o['policy_scope'] !== (int) $policy['scope']->ID || $o['policy_revision'] !== $policy['policy']['revision'] || ! in_array( $o['kind'], $policy['policy']['kinds'], true ) ) { return ps_pen_paper_trade_error(); }
			$a = ps_pen_paper_trade_party( $campaign, $o['from'] ); $b = ps_pen_paper_trade_party( $campaign, $o['to'] );
			if ( is_wp_error( $a ) || is_wp_error( $b ) || (int) $a['post']->post_author !== $o['from_owner'] || (int) $b['post']->post_author !== $o['to_owner'] ) { return ps_pen_paper_trade_error(); }
			foreach ( ps_pen_paper_trade_items( $o, 'give' ) as $item_id => $instance ) {
				$give = ps_pen_paper_trade_item( $campaign, $o['from'], $item_id, $id );
				if ( is_wp_error( $give ) || $give !== $instance ) { return ps_pen_paper_trade_error(); }
			}
			if ( 'counter' === $action && 'swap' === $o['kind'] && 'offered' === $before['status'] && $actor === $o['to_owner'] ) {
				$selection = ps_pen_paper_trade_selection( $uuid );
				if ( is_wp_error( $selection ) ) { return $selection; }
				$items = array();
				foreach ( $selection as $item_id ) {
					$take = ps_pen_paper_trade_item( $campaign, $o['to'], $item_id, $id );
					if ( is_wp_error( $take ) ) { return $take; }
					$items[ $item_id ] = $take;
				}
				$after['offer']['take_uuid'] = array_key_first( $items ); $after['offer']['take'] = reset( $items );
				if ( count( $items ) > 1 ) { $after['offer']['take_more'] = array_slice( $items, 1, null, true ); }
				$after['status'] = 'countered';
			} elseif ( 'accept' === $action && ( ( 'offered' === $before['status'] && 'swap' !== $o['kind'] && $actor === $o['to_owner'] ) ||
				( 'countered' === $before['status'] && 'swap' === $o['kind'] && $actor === $o['from_owner'] ) ) ) {
				$after['status'] = $policy['policy']['approval'] ? 'accepted' : 'completed';
			} elseif ( 'approve' === $action && 'accepted' === $before['status'] && $gm ) { $after['status'] = 'completed'; }
			else { return ps_pen_paper_trade_error(); }
			if ( 'completed' === $after['status'] ) {
				if ( 'swap' === $o['kind'] ) {
					foreach ( ps_pen_paper_trade_items( $o, 'take' ) as $item_id => $instance ) {
						$take = ps_pen_paper_trade_item( $campaign, $o['to'], $item_id, $id );
						if ( is_wp_error( $take ) || $take !== $instance ) { return ps_pen_paper_trade_error(); }
					}
				}
				if ( in_array( $o['kind'], array( 'sale', 'money_gift' ), true ) ) {
					$payer = ps_pen_paper_resolve_wallet_context( $campaign, 'money_gift' === $o['kind'] ? $a['post'] : $b['post'] );
					$payee = ps_pen_paper_resolve_wallet_context( $campaign, 'money_gift' === $o['kind'] ? $b['post'] : $a['post'] );
					if ( is_wp_error( $payer ) || is_wp_error( $payee ) || (int) $payer['currency_scope']->ID !== $o['policy_scope'] ||
						$payer['currency']['currency_id'] !== $o['currency']['currency_id'] ) { return ps_pen_paper_trade_error(); }
					$paid = ps_pen_paper_write_wallet_transfer( $payer, $payee, $o['amount'], __( 'Bestätigter Handel', 'ps-pen-paper' ) . ' #' . $id );
					if ( is_wp_error( $paid ) ) { return $paid; }
				}
				if ( 'money_gift' !== $o['kind'] ) {
					$transfer = ps_pen_paper_write_item_exchange( $campaign, array( $a['post'], $b['post'] ),
						array( array_keys( ps_pen_paper_trade_items( $o, 'give' ) ), array_keys( ps_pen_paper_trade_items( $o, 'take' ) ) ),
						array( ps_pen_paper_trade_items( $o, 'give' ), ps_pen_paper_trade_items( $o, 'take' ) ) );
					if ( is_wp_error( $transfer ) ) { return $transfer; }
				}
			}
		}
		return ps_pen_paper_write_trade( $campaign, $id, $before, $after, $action );
	} );
	clean_post_cache( $id );
	return $result;
}
