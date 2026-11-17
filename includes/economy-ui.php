<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_handle_economy_action( $operation ) {
	$id = ps_pen_paper_posted_id( 'campaign_id' );
	check_admin_referer( 'pspp_campaign_' . $id );
	$scope = ps_pen_paper_get_character_scope( $id );
	$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	if ( ! $scope || ! ps_pen_paper_can_access_site() || ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) ) {
		ps_pen_paper_fail( __( 'Dieser Wirtschaftsbereich ist nicht verfügbar.', 'ps-pen-paper' ) );
	}
	if ( 'complete' !== ps_pen_paper_posted_text( 'economy_complete' ) ) { ps_pen_paper_fail( __( 'Das Wirtschaftsformular wurde nicht vollständig übertragen.', 'ps-pen-paper' ) ); }
	if ( 'save_currency' === $operation ) {
		$result = ps_pen_paper_save_currency( $scope, isset( $_POST['currency'] ) ? wp_unslash( $_POST['currency'] ) : null, ps_pen_paper_posted_text( 'currency_revision' ) );
		$url = 'pspp_universe' === $scope->post_type ? ps_pen_paper_universe_url( $id, $page_id ) : ( $page_id ? ps_pen_paper_game_url( $page_id, $id ) : ps_pen_paper_admin_url( $id ) );
		$url .= '#pspp-settings';
		if ( 'pspp_universe' === $scope->post_type ) { $url = ps_pen_paper_settings_return_url( $id, $page_id, 'economy' ) ?: $url; }
	} else {
		$character_id = ps_pen_paper_posted_id( 'character_id' );
		if ( 'confirmed' !== ps_pen_paper_posted_text( 'wallet_confirmation' ) ) { ps_pen_paper_fail( __( 'Bitte bestätige die Guthabenbuchung ausdrücklich.', 'ps-pen-paper' ) ); }
		$result = ps_pen_paper_change_wallet( $scope, $character_id, ps_pen_paper_posted_text( 'wallet_operation' ), ps_pen_paper_posted_text( 'wallet_amount' ),
			ps_pen_paper_posted_text( 'wallet_reason' ), ps_pen_paper_posted_text( 'wallet_revision' ), ps_pen_paper_posted_text( 'currency_revision' ) );
		$url = ps_pen_paper_profile_return( $id, $character_id );
		if ( ! $url ) { $url = ps_pen_paper_character_url( $id, $character_id, $page_id ); }
	}
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	wp_safe_redirect( $url );
	exit;
}

function ps_pen_paper_render_currency_settings( $scope, $page_id ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_character_scope( $scope ) ) { return; }
	$source = ps_pen_paper_currency_scope( $scope );
	$currency = is_wp_error( $source ) ? $source : ps_pen_paper_currency_document( $source->ID );
	?>
	<section class="pspp-currency-settings">
	<h3><?php esc_html_e( 'Währung & Guthaben', 'ps-pen-paper' ); ?></h3>
	<?php if ( 'pspp_campaign' === $scope->post_type && ! metadata_exists( 'post', $scope->ID, '_pspp_play_round' ) ) :
		$link = ps_pen_paper_get_universe_link( $scope->ID );
		$world = ! is_wp_error( $link ) && isset( $link['rules_base'] ) ? ps_pen_paper_get_universe( $link['universe_id'] ) : null;
		$world_document = $world ? ps_pen_paper_universe_document( $world->ID ) : null;
		if ( is_wp_error( $link ) || is_wp_error( $world_document ) ) :
			echo '<p role="alert">' . esc_html( is_wp_error( $link ) ? $link->get_error_message() : $world_document->get_error_message() ) . '</p>';
		elseif ( $world ) :
			$local = 'local' === ( $link['economy_mode'] ?? 'universe' );
			$flexible = ps_pen_paper_inheritance_allows( ps_pen_paper_get_rules( $world->ID ), 'economy' );
			if ( $flexible || $local ) :
				ps_pen_paper_form_start( 'link_universe', 'pspp_universe_link_' . $scope->ID, $scope->ID, $page_id ); ?>
				<input type="hidden" name="universe_id" value="<?php echo esc_attr( $world->ID ); ?>">
				<input type="hidden" name="universe_revision" value="<?php echo esc_attr( $world_document['revision'] ); ?>">
				<input type="hidden" name="link_revision" value="<?php echo esc_attr( $link['revision'] ); ?>">
				<input type="hidden" name="economy_mode" value="<?php echo $local ? 'universe' : 'local'; ?>">
				<input type="hidden" name="economy_return" value="campaign">
				<p><?php esc_html_e( 'Beim Wechsel werden keine Guthaben übertragen oder umgerechnet. Bestehende Konten, gemeinsame Basisbögen und offene Handelsangebote können den Wechsel sperren.', 'ps-pen-paper' ); ?></p>
				<label class="pspp-check"><input type="checkbox" name="link_confirmation" value="confirmed" required><?php echo esc_html( $local ? __( 'Universumswirtschaft wieder verwenden', 'ps-pen-paper' ) : __( 'Eigene Kampagnenwirtschaft verwenden; vorhandene lokale Vorlagen bleiben erhalten', 'ps-pen-paper' ) ); ?></label>
				<button type="submit"><?php esc_html_e( 'Wirtschaftszuordnung ändern', 'ps-pen-paper' ); ?></button>
				</form>
			<?php endif;
		endif;
	endif; ?>
	<?php if ( ! ps_pen_paper_community_available() ) { echo '<p>' . esc_html__( 'Währung und Handelsänderungen benötigen aktives PS Community.', 'ps-pen-paper' ) . '</p></section>'; return; }
	if ( is_wp_error( $currency ) ) { echo '<p role="alert">' . esc_html( $currency->get_error_message() ) . '</p></section>'; return; }
	$editable = ps_pen_paper_require_campaign_economy_edit( $scope );
	if ( is_wp_error( $editable ) ) {
		echo '<p role="alert">' . esc_html( $editable->get_error_message() ) . '</p>';
		echo '<p>' . esc_html( $currency['name'] . ' (' . $currency['symbol'] . ')' ) . '</p></section>';
		return;
	}
	if ( $source->ID !== $scope->ID ) : ?>
		<p><?php echo esc_html( $currency['revision'] ? $currency['name'] . ' (' . $currency['symbol'] . ') · ' . ps_pen_paper_wallet_modes()[ $currency['wallet_mode'] ] : __( 'Die Universumsleitung hat noch keine Währung eingerichtet.', 'ps-pen-paper' ) ); ?></p>
		<p><?php esc_html_e( 'Währung und Guthabenmodell stammen aus dem Universum. Getrennte Konten verwaltet die Kampagnenleitung; gemeinsame Konten ausschließlich die Universumsleitung.', 'ps-pen-paper' ); ?></p>
		<?php if ( ps_pen_paper_can_manage_universe( $source ) ) : ?><a href="<?php echo esc_url( ps_pen_paper_universe_url( $source->ID, $page_id ) . '#pspp-settings' ); ?>"><?php esc_html_e( 'Währung im Universum einstellen', 'ps-pen-paper' ); ?></a><?php endif; ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nur ganze Einheiten. Guthaben liegen zwischen 0 und 999999999. Keine Nachkommastellen, automatische Umrechnung oder Überziehung.', 'ps-pen-paper' ); ?></p>
		<?php ps_pen_paper_form_start( 'save_currency', 'pspp_campaign_' . $scope->ID, $scope->ID, $page_id ); ?>
			<input type="hidden" name="currency_revision" value="<?php echo esc_attr( $currency['revision'] ); ?>"><input type="hidden" name="economy_complete" value="complete">
			<label><?php esc_html_e( 'Währungsname', 'ps-pen-paper' ); ?><input name="currency[name]" type="text" maxlength="200" value="<?php echo esc_attr( $currency['name'] ); ?>" required></label>
			<label><?php esc_html_e( 'Währungskürzel', 'ps-pen-paper' ); ?><input name="currency[symbol]" type="text" maxlength="24" value="<?php echo esc_attr( $currency['symbol'] ); ?>" required></label>
			<?php if ( 'pspp_universe' === $scope->post_type && ! $currency['in_use'] ) : ?>
				<label><?php esc_html_e( 'Guthaben führen', 'ps-pen-paper' ); ?><select name="currency[wallet_mode]"><?php foreach ( ps_pen_paper_wallet_modes() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $currency['wallet_mode'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<?php else : ?>
				<input type="hidden" name="currency[wallet_mode]" value="<?php echo esc_attr( $currency['wallet_mode'] ); ?>"><p><?php echo esc_html( ps_pen_paper_wallet_modes()[ $currency['wallet_mode'] ] ); ?></p>
			<?php endif; ?>
			<p><?php echo esc_html( 'pspp_universe' === $scope->post_type
				? __( 'Gemeinsame Konten benötigen gemeinsame Basisbögen und werden nur von der Universumsleitung bebucht. Bei getrennten Konten bucht die jeweilige Kampagnenleitung.', 'ps-pen-paper' )
				: __( 'Diese Kampagne führt ihre Währung und Guthaben unabhängig. Bestehende Konten werden nicht übertragen oder in eine andere Währung umgerechnet.', 'ps-pen-paper' ) ); ?></p>
			<p><?php esc_html_e( 'Nach der ersten Buchung bleibt das Guthabenmodell zum Schutz der Konten fest; Name und Kürzel bleiben änderbar.', 'ps-pen-paper' ); ?></p>
			<button type="submit"><?php esc_html_e( 'Währungseinstellungen speichern', 'ps-pen-paper' ); ?></button>
		</form>
	<?php endif; ?>
	</section>
	<?php
}

function ps_pen_paper_render_wallet( $scope, $character, $page_id, $context = 'game' ) {
	if ( ! ps_pen_paper_community_available() ) { return; }
	$current = ps_pen_paper_wallet_context( $scope, $character->ID );
	if ( is_wp_error( $current ) ) {
		echo '<p role="alert">' . esc_html( $current->get_error_message() ) . '</p>';
		return;
	}
	?>
	<section class="pspp-wallet">
		<h3><?php esc_html_e( 'Guthaben', 'ps-pen-paper' ); ?></h3>
		<p class="pspp-wallet-balance"><strong><?php echo esc_html( number_format_i18n( $current['wallet']['balance'], 0 ) . ' ' . $current['currency']['symbol'] ); ?></strong> · <?php echo esc_html( $current['currency']['name'] ); ?></p>
		<p><?php echo esc_html( ps_pen_paper_wallet_modes()[ $current['currency']['wallet_mode'] ] ); ?></p>
		<?php if ( 'profile' !== $context && ps_pen_paper_management_view( $page_id ) && ps_pen_paper_can_manage_character_scope( $current['scope'] ) ) : ?>
			<details class="pspp-fold"><summary><?php esc_html_e( 'Guthaben vergeben oder abziehen', 'ps-pen-paper' ); ?></summary>
			<?php ps_pen_paper_form_start( 'change_wallet', 'pspp_campaign_' . $scope->ID, $scope->ID, $page_id ); ?>
				<input type="hidden" name="economy_complete" value="complete"><input type="hidden" name="character_id" value="<?php echo esc_attr( $character->ID ); ?>">
				<input type="hidden" name="wallet_revision" value="<?php echo esc_attr( $current['wallet']['revision'] ); ?>"><input type="hidden" name="currency_revision" value="<?php echo esc_attr( $current['currency']['revision'] ); ?>">
				<label><?php esc_html_e( 'Buchungsart', 'ps-pen-paper' ); ?><select name="wallet_operation"><option value="grant"><?php esc_html_e( 'Betrag vergeben', 'ps-pen-paper' ); ?></option><option value="debit"><?php esc_html_e( 'Betrag abziehen', 'ps-pen-paper' ); ?></option></select></label>
				<label><?php echo esc_html( __( 'Betrag in', 'ps-pen-paper' ) . ' ' . $current['currency']['symbol'] ); ?><input name="wallet_amount" type="number" min="1" max="999999999" step="1" required></label>
				<label><?php esc_html_e( 'Begründung der Buchung', 'ps-pen-paper' ); ?><textarea name="wallet_reason" rows="3" maxlength="1000" required></textarea></label>
				<label class="pspp-check"><input name="wallet_confirmation" type="checkbox" value="confirmed" required><?php esc_html_e( 'Betrag und Buchungsart geprüft. Diese Buchung mit Begründung protokollieren.', 'ps-pen-paper' ); ?></label>
				<button type="submit"><?php esc_html_e( 'Guthabenbuchung speichern', 'ps-pen-paper' ); ?></button>
			</form></details>
		<?php elseif ( 'universe' === $current['currency']['wallet_mode'] ) : ?><p><?php esc_html_e( 'Buchungen und den gemeinsamen Änderungsverlauf verwaltet die Universumsleitung.', 'ps-pen-paper' ); ?></p><?php endif; ?>
	</section>
	<?php
}

function ps_pen_paper_render_economy_history( $kind, $state ) {
	if ( 'currency_saved' === $kind ) {
		if ( ! $state['revision'] ) { echo '<p>' . esc_html__( 'Noch keine Währung eingerichtet.', 'ps-pen-paper' ) . '</p>'; return; }
		echo '<p>' . esc_html( $state['name'] . ' (' . $state['symbol'] . ') · ' . ps_pen_paper_wallet_modes()[ $state['wallet_mode'] ] ) . '</p>';
	} else {
		echo '<p>' . esc_html( number_format_i18n( $state['balance'], 0 ) . ' ' . $state['symbol'] . ' · ' . $state['name'] ) . '</p>';
	}
}
