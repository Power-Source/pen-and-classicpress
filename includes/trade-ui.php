<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_handle_trade_action( $operation ) {
	$id = ps_pen_paper_posted_id( 'campaign_id' ); $page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	check_admin_referer( 'pspp_campaign_' . $id );
	$scope = ps_pen_paper_get_character_scope( $id ); $character_id = ps_pen_paper_posted_id( 'character_id' );
	if ( ! $scope || ! ps_pen_paper_can_access_site() || ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) ||
		'complete' !== ps_pen_paper_posted_text( 'trade_complete' ) ) { ps_pen_paper_fail( ps_pen_paper_trade_error()->get_error_message() ); }
	if ( 'save_trade_policy' === $operation ) {
		$input = isset( $_POST['trade_policy'] ) ? wp_unslash( $_POST['trade_policy'] ) : null;
		if ( is_array( $input ) && ! isset( $input['kinds'] ) ) { $input['kinds'] = array(); }
		$result = ps_pen_paper_save_trade_policy( $scope, $input, ps_pen_paper_posted_text( 'trade_policy_revision' ) );
		$url = 'pspp_universe' === $scope->post_type ? ps_pen_paper_universe_url( $id, $page_id ) : ( $page_id ? ps_pen_paper_game_url( $page_id, $id ) : ps_pen_paper_admin_url( $id ) );
		$url .= '#pspp-settings';
		if ( 'pspp_universe' === $scope->post_type ) { $url = ps_pen_paper_settings_return_url( $id, $page_id, 'economy' ) ?: $url; }
	} else {
		if ( 'pspp_campaign' !== $scope->post_type || ! ps_pen_paper_get_character( $scope, $character_id ) || 'confirmed' !== ps_pen_paper_posted_text( 'trade_confirmation' ) ) { ps_pen_paper_fail( ps_pen_paper_trade_error()->get_error_message() ); }
		$items = isset( $_POST['trade_items'] ) ? wp_unslash( $_POST['trade_items'] ) : ps_pen_paper_posted_text( 'trade_item' );
		$result = 'offer_trade' === $operation
			? ps_pen_paper_offer_trade( $scope, $character_id, ps_pen_paper_posted_id( 'trade_target' ), ps_pen_paper_posted_text( 'trade_kind' ), $items, ps_pen_paper_posted_text( 'trade_amount' ), ps_pen_paper_posted_text( 'trade_gear_revision' ), ps_pen_paper_posted_text( 'trade_policy_revision' ), ps_pen_paper_posted_text( 'trade_token' ) )
			: ps_pen_paper_change_trade( $scope, ps_pen_paper_posted_id( 'trade_id' ), ps_pen_paper_posted_text( 'trade_action' ), ps_pen_paper_posted_text( 'trade_revision' ), ps_pen_paper_posted_text( 'trade_confirmation' ), $items );
		$url = ps_pen_paper_profile_return( $id, $character_id );
		if ( ! $url ) { $url = ps_pen_paper_character_url( $id, $character_id, $page_id ) . '#pspp-trade-' . $character_id; }
	}
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	wp_safe_redirect( $url ); exit;
}

function ps_pen_paper_render_trade_policy( $scope, $page_id ) {
	if ( ! ps_pen_paper_can_manage_character_scope( $scope ) ) { return; }
	$current = ps_pen_paper_trade_policy( $scope );
	if ( is_wp_error( $current ) ) { echo '<p role="alert">' . esc_html( $current->get_error_message() ) . '</p>'; return; }
	$p = $current['policy'];
	?>
	<section class="pspp-settings-section"><h3><?php esc_html_e( 'Handelsregeln', 'ps-pen-paper' ); ?></h3>
	<p><?php esc_html_e( 'Spielerhandel bleibt innerhalb derselben Kampagne. Die Spielleitung bestimmt erlaubte Arten und zusätzliche Freigabe. Standardmäßig ist keine Handelsart aktiviert und eine Spielleiterfreigabe erforderlich. Eine Regeländerung macht offene Angebote nicht mehr ausführbar; sie können zurückgezogen oder abgelehnt werden.', 'ps-pen-paper' ); ?></p>
	<?php if ( (int) $current['scope']->ID !== (int) $scope->ID ) : ?>
		<p><?php esc_html_e( 'Diese Kampagne übernimmt die Handelsregeln ihres Universums ohne lokale Abweichung.', 'ps-pen-paper' ); ?></p>
		<?php ps_pen_paper_render_trade_snapshot( $p ); ?>
	<?php elseif ( ! ps_pen_paper_community_available() ) : ?>
		<p><?php esc_html_e( 'Handelsänderungen benötigen aktives PS Community.', 'ps-pen-paper' ); ?></p>
	<?php else : ps_pen_paper_form_start( 'save_trade_policy', 'pspp_campaign_' . $scope->ID, $scope->ID, $page_id ); ?>
		<input type="hidden" name="trade_complete" value="complete"><input type="hidden" name="trade_policy_revision" value="<?php echo esc_attr( $p['revision'] ); ?>">
		<?php foreach ( ps_pen_paper_trade_kinds() as $key => $label ) : ?><label class="pspp-check"><input type="checkbox" name="trade_policy[kinds][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $p['kinds'], true ) ); ?>><?php echo esc_html( $label ); ?></label><?php endforeach; ?>
		<?php if ( 'pspp_universe' === $scope->post_type ) : ?>
			<label class="pspp-check"><input type="checkbox" name="trade_policy[transfers]" value="1" <?php checked( ! empty( $p['transfers'] ) ); ?>><?php esc_html_e( 'Kontrollierte Übertragungen zwischen Kampagnen dieses Universums erlauben', 'ps-pen-paper' ); ?></label>
			<p><?php esc_html_e( 'Nur eine für beide Kampagnen berechtigte Spielleitung oder Administration kann eine Übertragung ausdrücklich bestätigen. Spielerhandel bleibt in der eigenen Kampagne. Getrennte Universen und Währungsumrechnungen sind ausgeschlossen.', 'ps-pen-paper' ); ?></p>
		<?php endif; ?>
		<label><?php esc_html_e( 'Nach Zustimmung beider Besitzer', 'ps-pen-paper' ); ?><select name="trade_policy[approval]"><option value="1" <?php selected( $p['approval'], true ); ?>><?php esc_html_e( 'Zusätzliche Spielleiterfreigabe erforderlich', 'ps-pen-paper' ); ?></option><option value="0" <?php selected( $p['approval'], false ); ?>><?php esc_html_e( 'Direkt abschließen', 'ps-pen-paper' ); ?></option></select></label>
		<button type="submit"><?php esc_html_e( 'Handelsregeln speichern', 'ps-pen-paper' ); ?></button>
	</form><?php endif; ?></section>
	<?php
}

function ps_pen_paper_render_trade_snapshot( $state ) {
	if ( isset( $state['kinds'] ) ) {
		echo '<p>' . esc_html( implode( ', ', array_intersect_key( ps_pen_paper_trade_kinds(), array_flip( $state['kinds'] ) ) ) ) . '</p>';
		echo '<p>' . esc_html( $state['approval'] ? __( 'Spielleiterfreigabe erforderlich', 'ps-pen-paper' ) : __( 'Abschluss nach beiderseitiger Zustimmung', 'ps-pen-paper' ) ) . '</p>';
		if ( array_key_exists( 'transfers', $state ) ) {
			echo '<p>' . esc_html( $state['transfers'] ? __( 'Kontrollierte Übertragungen im Universum erlaubt', 'ps-pen-paper' ) : __( 'Kampagnenübergreifende Übertragungen ausgeschaltet', 'ps-pen-paper' ) ) . '</p>';
		}
		return;
	}
	if ( ! $state['offer'] ) { echo '<p>' . esc_html__( 'Noch kein Angebot.', 'ps-pen-paper' ) . '</p>'; return; }
	$o = $state['offer'];
	echo '<h4>' . esc_html( ps_pen_paper_trade_kinds()[ $o['kind'] ] . ' · ' . $o['from_name'] . ' → ' . $o['to_name'] ) . '</h4>';
	foreach ( array( 'give', 'take' ) as $key ) {
		foreach ( ps_pen_paper_trade_items( $o, $key ) as $uuid => $instance ) {
			echo '<p><strong>' . esc_html( ( 'give' === $key ? __( 'Angeboten:', 'ps-pen-paper' ) : __( 'Gegenangebot:', 'ps-pen-paper' ) ) . ' ' . $instance['item']['name'] . ' · ' . $uuid ) . '</strong></p>';
			echo '<p class="pspp-multiline">' . esc_html( $instance['item']['description'] ) . '</p>';
			echo '<p>' . esc_html( $instance['item']['kind'] . ' · ' . $instance['item']['rarity_label'] ) . '</p><dl>';
			foreach ( $instance['item']['properties'] as $label => $value ) { echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>'; }
			foreach ( $instance['item']['bonuses'] as $field => $value ) { echo '<dt>' . esc_html( $field ) . '</dt><dd>' . esc_html( $value ) . '</dd>'; }
			echo '</dl>';
		}
	}
	if ( $o['amount'] ) { echo '<p><strong>' . esc_html( ( 'money_gift' === $o['kind'] ? __( 'Anbieter schenkt dem Empfänger:', 'ps-pen-paper' ) : __( 'Käufer zahlt an Anbieter:', 'ps-pen-paper' ) ) . ' ' . $o['amount'] . ' ' . $o['currency']['symbol'] ) . '</strong></p>'; }
	echo '<p><strong>' . esc_html( ps_pen_paper_trade_statuses()[ $state['status'] ] ) . '</strong></p>';
	echo '<ul>'; foreach ( $state['history'] as $entry ) { echo '<li>' . esc_html( $entry['date'] . ' UTC · ' . ps_pen_paper_trade_actions()[ $entry['action'] ] . ' · #' . $entry['actor_id'] ) . '</li>'; } echo '</ul>';
}

function ps_pen_paper_render_trade_items( $campaign, $character_id ) {
	$gear = ps_pen_paper_gear_state( $character_id );
	if ( is_wp_error( $gear ) ) { echo '<p role="alert">' . esc_html( $gear->get_error_message() ) . '</p>'; return; }
	echo '<fieldset><legend>' . esc_html__( 'Eigene handelbare, abgelegte Exemplare', 'ps-pen-paper' ) . '</legend>';
	foreach ( $gear['active']['items'] as $uuid => $instance ) {
		$available = ps_pen_paper_trade_item( $campaign, $character_id, $uuid );
		if ( is_wp_error( $available ) ) {
			if ( 'item_unavailable' === $available->get_error_code() ) { continue; }
			echo '</fieldset><p role="alert">' . esc_html( $available->get_error_message() ) . '</p>'; return;
		}
		echo '<label class="pspp-check"><input type="checkbox" name="trade_items[]" value="' . esc_attr( $uuid ) . '">' . esc_html( $instance['item']['name'] . ' · ' . substr( $uuid, 0, 8 ) ) . '</label>';
	}
	echo '</fieldset>';
}

function ps_pen_paper_render_character_trade( $campaign, $character, $page_id, $context = 'game' ) {
	if ( ! ps_pen_paper_can_access_site() || 'pspp_campaign' !== $campaign->post_type || ! ps_pen_paper_get_character( $campaign, $character->ID ) ) { return; }
	$policy = ps_pen_paper_trade_policy( $campaign ); $records = ps_pen_paper_trade_records( $campaign ); $gear = ps_pen_paper_gear_state( $character->ID );
	if ( is_wp_error( $policy ) || is_wp_error( $records ) || is_wp_error( $gear ) ) { echo '<p role="alert">' . esc_html( ps_pen_paper_trade_error()->get_error_message() ) . '</p>'; return; }
	$actor = get_current_user_id(); $gm = 'profile' !== $context && ps_pen_paper_management_view( $page_id ) && ps_pen_paper_can_manage_campaign( $campaign ); $p = $policy['policy'];
	?>
	<section id="pspp-trade-<?php echo esc_attr( $character->ID ); ?>" data-character-trade="<?php echo esc_attr( $character->ID ); ?>"><h3><?php esc_html_e( 'Handel', 'ps-pen-paper' ); ?></h3>
	<p><?php esc_html_e( 'Nur handelbare, weder aktiv noch in einem Antrag angelegte Exemplare. Angebote reservieren Gegenstände, nicht Geld. Fremde Inventare und Guthaben bleiben privat. Bei einem Tausch wählt die Gegenseite ihre Exemplare; erst danach bestätigt der Anbieter die konkreten Bedingungen.', 'ps-pen-paper' ); ?></p>
	<?php foreach ( $records as $id => $state ) : $o = $state['offer']; if ( ! in_array( (int) $character->ID, array( $o['from'], $o['to'] ), true ) ) { continue; } ?>
		<article data-trade-id="<?php echo esc_attr( $id ); ?>">
		<?php ps_pen_paper_render_trade_snapshot( $state );
		if ( ps_pen_paper_trade_open( $state ) && ps_pen_paper_community_available() ) :
			$actions = array();
			if ( $actor === $o['from_owner'] ) { $actions['cancel'] = __( 'Angebot zurückziehen', 'ps-pen-paper' ); }
			if ( $actor === $o['to_owner'] ) { $actions['decline'] = __( 'Angebot ablehnen', 'ps-pen-paper' ); }
			if ( $gm ) { $actions['reject'] = __( 'Als Spielleitung ablehnen', 'ps-pen-paper' ); }
			if ( $o['policy_scope'] === (int) $policy['scope']->ID && $o['policy_revision'] === $p['revision'] ) {
				if ( 'offered' === $state['status'] && $actor === $o['to_owner'] ) {
					$actions[ 'swap' === $o['kind'] ? 'counter' : 'accept' ] = 'swap' === $o['kind'] ? __( 'Dieses Tauschgegenangebot bestätigen', 'ps-pen-paper' ) : __( 'Angebot verbindlich annehmen', 'ps-pen-paper' );
				}
				if ( 'countered' === $state['status'] && $actor === $o['from_owner'] ) { $actions['accept'] = __( 'Konkreten Tausch verbindlich bestätigen', 'ps-pen-paper' ); }
				if ( 'accepted' === $state['status'] && $gm ) { $actions['approve'] = __( 'Handel freigeben und abschließen', 'ps-pen-paper' ); }
			}
			foreach ( $actions as $action => $label ) :
				ps_pen_paper_form_start( 'change_trade', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
				<input type="hidden" name="character_id" value="<?php echo esc_attr( $character->ID ); ?>"><input type="hidden" name="trade_complete" value="complete">
				<input type="hidden" name="trade_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="trade_revision" value="<?php echo esc_attr( $state['revision'] ); ?>"><input type="hidden" name="trade_action" value="<?php echo esc_attr( $action ); ?>">
				<?php if ( 'counter' === $action ) { ps_pen_paper_render_trade_items( $campaign, $o['to'] ); } ?>
				<label class="pspp-check"><input type="checkbox" name="trade_confirmation" value="confirmed" required><?php esc_html_e( 'Gespeicherte Exemplare, Gegenleistung und diese Aktion geprüft.', 'ps-pen-paper' ); ?></label>
				<button type="submit"><?php echo esc_html( $label ); ?></button></form>
			<?php endforeach;
		endif; ?></article>
	<?php endforeach;
	if ( ! ps_pen_paper_community_available() ) : ?><p><?php esc_html_e( 'Ohne PS Community bleibt der Handel inaktiv; vorhandene Angebote bleiben gespeichert.', 'ps-pen-paper' ); ?></p>
	<?php elseif ( ! $p['kinds'] ) : ?><p><?php esc_html_e( 'Die zuständige Spielleitung hat keine Handelsarten erlaubt.', 'ps-pen-paper' ); ?></p>
	<?php elseif ( (int) $character->post_author === $actor ) :
		ps_pen_paper_form_start( 'offer_trade', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
		<h4><?php esc_html_e( 'Neues Angebot', 'ps-pen-paper' ); ?></h4>
		<input type="hidden" name="character_id" value="<?php echo esc_attr( $character->ID ); ?>"><input type="hidden" name="trade_complete" value="complete">
		<input type="hidden" name="trade_gear_revision" value="<?php echo esc_attr( $gear['revision'] ); ?>"><input type="hidden" name="trade_policy_revision" value="<?php echo esc_attr( $p['revision'] ); ?>"><input type="hidden" name="trade_token" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
		<label><?php esc_html_e( 'Handelsart', 'ps-pen-paper' ); ?><select name="trade_kind"><?php foreach ( $p['kinds'] as $kind ) : ?><option value="<?php echo esc_attr( $kind ); ?>"><?php echo esc_html( ps_pen_paper_trade_kinds()[ $kind ] ); ?></option><?php endforeach; ?></select></label>
		<?php ps_pen_paper_render_trade_items( $campaign, $character->ID ); ?>
		<label><?php esc_html_e( 'Empfänger in dieser Kampagne', 'ps-pen-paper' ); ?><select name="trade_target" required><option value=""><?php esc_html_e( 'Charakter auswählen', 'ps-pen-paper' ); ?></option>
		<?php foreach ( get_posts( array( 'post_type' => 'pspp_character', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1 ) ) as $candidate ) :
			if ( (int) $candidate->post_author === $actor ) { continue; }
			$party = ps_pen_paper_trade_party( $campaign, $candidate->ID ); if ( is_wp_error( $party ) ) { continue; } ?>
			<option value="<?php echo esc_attr( $candidate->ID ); ?>"><?php echo esc_html( $party['name'] ); ?></option>
		<?php endforeach; ?></select></label>
		<label><?php esc_html_e( 'Gesamtpreis oder Geldgeschenk; bei Gegenstandsgeschenk/Tausch 0', 'ps-pen-paper' ); ?><input type="number" name="trade_amount" min="0" max="999999999" step="1" value="0" required></label>
		<p><?php esc_html_e( 'Mehrere Exemplare gemeinsam auswählen. Bei einem reinen Geldgeschenk keine Exemplare auswählen. Der Verkaufspreis gilt für das gesamte Angebot.', 'ps-pen-paper' ); ?></p>
		<p><?php echo esc_html( $p['approval'] ? __( 'Nach beiderseitiger Zustimmung bestätigt zusätzlich die Spielleitung.', 'ps-pen-paper' ) : __( 'Nach beiderseitiger Zustimmung wird direkt übertragen.', 'ps-pen-paper' ) ); ?></p>
		<label class="pspp-check"><input type="checkbox" name="trade_confirmation" value="confirmed" required><?php esc_html_e( 'Dieses Angebot zu den angegebenen Bedingungen bestätigen; ausgewählte Exemplare reservieren.', 'ps-pen-paper' ); ?></label>
		<button type="submit"><?php esc_html_e( 'Handelsangebot erstellen', 'ps-pen-paper' ); ?></button>
	</form>
	<?php endif; ?></section>
	<?php
}
