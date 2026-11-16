<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_handle_transfer_action() {
	$id = ps_pen_paper_posted_id( 'campaign_id' ); $page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	check_admin_referer( 'pspp_campaign_' . $id );
	if ( 'complete' !== ps_pen_paper_posted_text( 'transfer_complete' ) ||
		( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) ) {
		ps_pen_paper_fail( ps_pen_paper_transfer_error()->get_error_message() );
	}
	$parts = explode( ':', ps_pen_paper_posted_text( 'transfer_target' ) );
	if ( 6 !== count( $parts ) || ! preg_match( '/^[1-9]\d{0,10}$/D', $parts[0] ) ||
		! preg_match( '/^[1-9]\d{0,10}$/D', $parts[1] ) ) { ps_pen_paper_fail( ps_pen_paper_transfer_error()->get_error_message() ); }
	$source = ps_pen_paper_get_campaign( $id ); $target = ps_pen_paper_get_campaign( (int) $parts[0] );
	$link = $source ? ps_pen_paper_get_universe_link( $id ) : ps_pen_paper_transfer_error();
	if ( is_wp_error( $link ) || ! $link['universe_id'] ) { ps_pen_paper_fail( ps_pen_paper_transfer_error()->get_error_message() ); }
	$input = array(
		'items' => isset( $_POST['transfer_items'] ) ? wp_unslash( $_POST['transfer_items'] ) : array(),
		'amount' => ps_pen_paper_posted_text( 'transfer_amount' ), 'confirmation' => ps_pen_paper_posted_text( 'transfer_confirmation' ),
		'token' => ps_pen_paper_posted_text( 'transfer_token' ), 'policy_revision' => ps_pen_paper_posted_text( 'transfer_policy_revision' ),
		'gear_revision' => ps_pen_paper_posted_text( 'transfer_gear_revision' ), 'target_gear_revision' => $parts[2],
		'wallet_revision' => ps_pen_paper_posted_text( 'transfer_wallet_revision' ), 'target_wallet_revision' => $parts[3],
		'currency_revision' => ps_pen_paper_posted_text( 'transfer_currency_revision' ), 'target_currency_revision' => $parts[4],
		'epochs' => array( $id => ps_pen_paper_posted_text( 'transfer_source_epoch' ), (int) $parts[0] => $parts[5],
			$link['universe_id'] => ps_pen_paper_posted_text( 'transfer_universe_epoch' ) ),
	);
	$character_id = ps_pen_paper_posted_id( 'character_id' );
	$result = ps_pen_paper_transfer_campaign_assets( $source, $target, $character_id, (int) $parts[1], $input );
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	$url = ps_pen_paper_profile_return( $id, $character_id );
	wp_safe_redirect( $url ?: ps_pen_paper_character_url( $id, $character_id, $page_id ) );
	exit;
}

function ps_pen_paper_render_character_transfers( $source, $character, $page_id ) {
	if ( ! ps_pen_paper_community_available() || ! ps_pen_paper_can_access_site() || ! ps_pen_paper_management_view( $page_id ) || ! ps_pen_paper_can_manage_campaign( $source ) ) { return; }
	$link = ps_pen_paper_get_universe_link( $source->ID );
	if ( is_wp_error( $link ) ) { echo '<p role="alert">' . esc_html( $link->get_error_message() ) . '</p>'; return; }
	if ( ! $link['universe_id'] ) { return; }
	$universe = ps_pen_paper_get_universe( $link['universe_id'] );
	$policy = $universe ? ps_pen_paper_trade_policy( $universe ) : ps_pen_paper_transfer_error();
	if ( is_wp_error( $policy ) ) { echo '<p role="alert">' . esc_html( $policy->get_error_message() ) . '</p>'; return; }
	if ( empty( $policy['policy']['transfers'] ) ) { return; }
	$gear = ps_pen_paper_gear_state( $character->ID ); $epoch = ps_pen_paper_scope_epoch( $source->ID ); $universe_epoch = ps_pen_paper_scope_epoch( $universe->ID );
	if ( is_wp_error( $gear ) || is_wp_error( $epoch ) || is_wp_error( $universe_epoch ) ) { echo '<p role="alert">' . esc_html( ps_pen_paper_transfer_error()->get_error_message() ) . '</p>'; return; }
	$targets = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'posts_per_page' => -1,
		'post__not_in' => array( $source->ID ), 'orderby' => 'ID', 'order' => 'ASC' ) ) as $candidate ) {
		if ( ! ps_pen_paper_can_manage_campaign( $candidate ) ) { continue; }
		$target_link = ps_pen_paper_get_universe_link( $candidate->ID );
		if ( is_wp_error( $target_link ) ) { echo '<p role="alert">' . esc_html( $target_link->get_error_message() ) . '</p>'; return; }
		if ( $target_link['universe_id'] !== $universe->ID ) { continue; }
		$target_epoch = ps_pen_paper_scope_epoch( $candidate->ID );
		if ( is_wp_error( $target_epoch ) ) { echo '<p role="alert">' . esc_html( $target_epoch->get_error_message() ) . '</p>'; return; }
		foreach ( ps_pen_paper_get_characters( $candidate ) as $recipient ) {
			$state = ps_pen_paper_character_state( $candidate, $recipient, false );
			$target_gear = ps_pen_paper_gear_state( $recipient->ID );
			if ( is_wp_error( $state ) || is_wp_error( $target_gear ) ) { echo '<p role="alert">' . esc_html( ps_pen_paper_transfer_error()->get_error_message() ) . '</p>'; return; }
			if ( ! $state['active'] ) { continue; }
			$wallet = ps_pen_paper_wallet_context( $candidate, $recipient->ID );
			$wallet_ok = ! is_wp_error( $wallet );
			$value = implode( ':', array( $candidate->ID, $recipient->ID, $target_gear['revision'],
				$wallet_ok ? $wallet['wallet']['revision'] : '', $wallet_ok ? $wallet['currency']['revision'] : '', $target_epoch ) );
			$targets[ $value ] = get_the_title( $candidate ) . ' · ' . $state['active']['name'] .
				( $wallet_ok ? '' : ' · ' . __( 'Guthaben nicht verfügbar', 'ps-pen-paper' ) );
		}
	}
	echo '<details class="pspp-fold"><summary>' . esc_html__( 'In eine andere Kampagne dieses Universums übertragen', 'ps-pen-paper' ) . '</summary>';
	echo '<p>' . esc_html__( 'Nur mit Verwaltungsrecht für beide Kampagnen. Handelbare, abgelegte und nicht reservierte Exemplare sowie Geld in derselben Universumswährung übertragen. Beide Bestände und Protokolle werden gemeinsam geändert. Gemeinsames Basisguthaben wird nicht dupliziert.', 'ps-pen-paper' ) . '</p>';
	if ( ! $targets ) { echo '<p>' . esc_html__( 'Keine freigegebenen Empfänger in einer anderen, von dir verwalteten Kampagne dieses Universums.', 'ps-pen-paper' ) . '</p></details>'; return; }
	$wallet = ps_pen_paper_wallet_context( $source, $character->ID ); $wallet_ok = ! is_wp_error( $wallet );
	ps_pen_paper_form_start( 'transfer_campaign_assets', 'pspp_campaign_' . $source->ID, $source->ID, $page_id );
	foreach ( array( 'character_id' => $character->ID, 'transfer_complete' => 'complete', 'transfer_token' => wp_generate_uuid4(),
		'transfer_policy_revision' => $policy['policy']['revision'], 'transfer_gear_revision' => $gear['revision'],
		'transfer_wallet_revision' => $wallet_ok ? $wallet['wallet']['revision'] : '',
		'transfer_currency_revision' => $wallet_ok ? $wallet['currency']['revision'] : '',
		'transfer_source_epoch' => $epoch, 'transfer_universe_epoch' => $universe_epoch ) as $key => $value ) {
		echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
	}
	foreach ( $gear['active']['items'] as $uuid => $instance ) {
		$item = ps_pen_paper_trade_item( $source, $character->ID, $uuid );
		if ( is_wp_error( $item ) ) {
			if ( 'item_unavailable' === $item->get_error_code() ) { continue; }
			echo '<p role="alert">' . esc_html( $item->get_error_message() ) . '</p></form></details>'; return;
		}
		echo '<label class="pspp-check"><input type="checkbox" name="transfer_items[]" value="' . esc_attr( $uuid ) . '">' .
			esc_html( $instance['item']['name'] . ' · ' . substr( $uuid, 0, 8 ) ) . '</label>';
	}
	echo '<label>' . esc_html__( 'Zielkampagne und Empfänger', 'ps-pen-paper' ) . '<select name="transfer_target" required>';
	foreach ( $targets as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>'; }
	echo '</select></label><label>' . esc_html__( 'Geldbetrag (0 für reine Gegenstandsübertragung)', 'ps-pen-paper' ) .
		'<input type="number" name="transfer_amount" min="0" max="999999999" step="1" value="0" required></label>';
	if ( ! $wallet_ok ) { echo '<p role="alert">' . esc_html( $wallet->get_error_message() ) . '</p>'; }
	echo '<label class="pspp-check"><input type="checkbox" name="transfer_confirmation" value="confirmed" required>' .
		esc_html__( 'Diese Exemplare und diesen Betrag verbindlich an den gewählten Empfänger übertragen.', 'ps-pen-paper' ) . '</label><button type="submit">' .
		esc_html__( 'Übertragung bestätigen', 'ps-pen-paper' ) . '</button></form></details>';
}
