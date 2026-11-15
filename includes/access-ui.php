<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_handle_access_action( $operation ) {
	$campaign_id = ps_pen_paper_posted_id( 'campaign_id' );
	$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	if ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) {
		ps_pen_paper_fail( __( 'Die Spielbereich-Seite ist nicht mehr verfügbar. Bitte öffne das Formular erneut.', 'ps-pen-paper' ) );
	}
	check_admin_referer( ( in_array( $operation, array( 'request_membership', 'accept_invitation', 'decline_invitation' ), true ) ? 'pspp_join_' : 'pspp_campaign_' ) . $campaign_id );
	$revision = isset( $_POST['settings_revision'] ) ? $_POST['settings_revision'] : null;
	if ( in_array( $operation, array( 'invite_user', 'revoke_invitation', 'accept_invitation', 'decline_invitation' ), true ) ) {
		$operations = array( 'invite_user' => 'invite', 'revoke_invitation' => 'revoke', 'accept_invitation' => 'accept', 'decline_invitation' => 'decline' );
		$self = in_array( $operation, array( 'accept_invitation', 'decline_invitation' ), true );
		$result = ps_pen_paper_change_invitation( $campaign_id, $self ? get_current_user_id() : ps_pen_paper_posted_id( 'user_id' ),
			$operations[ $operation ], ps_pen_paper_posted_text( 'invitation_revision' ), ps_pen_paper_posted_text( 'membership_revision' ) );
	} elseif ( 'request_membership' === $operation ) {
		$result = ps_pen_paper_request_membership( $campaign_id );
	} elseif ( 'review_membership' === $operation ) {
		$result = ps_pen_paper_review_membership( $campaign_id, ps_pen_paper_posted_id( 'user_id' ), ps_pen_paper_posted_text( 'decision' ), ps_pen_paper_posted_text( 'membership_revision' ) );
	} elseif ( 'save_access' === $operation ) {
		if ( 'complete' !== ps_pen_paper_posted_text( 'settings_complete' ) ) {
			ps_pen_paper_fail( __( 'Die Einstellungen wurden nicht vollständig übertragen. Es wurde nichts gespeichert.', 'ps-pen-paper' ) );
		}
		$result = ps_pen_paper_save_access( $campaign_id, isset( $_POST['access'] ) ? wp_unslash( $_POST['access'] ) : null, $revision );
	} else {
		$mode = ps_pen_paper_posted_text( 'backdrop_action' );
		$file = isset( $_FILES['backdrop'] ) ? $_FILES['backdrop'] : null;
		if ( ! in_array( $mode, array( 'upload', 'remove' ), true ) || ( 'upload' === $mode &&
			( ! is_array( $file ) || ! isset( $file['error'], $file['tmp_name'] ) || UPLOAD_ERR_OK !== $file['error'] ||
			! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) ) ) {
			ps_pen_paper_fail( __( 'Das Bild wurde nicht vollständig hochgeladen. Bitte prüfe die Dateigröße und wähle es erneut aus.', 'ps-pen-paper' ) );
		}
		$result = ps_pen_paper_save_backdrop( $campaign_id, 'remove' === $mode ? null : $file['tmp_name'], $revision );
	}
	if ( is_wp_error( $result ) ) {
		ps_pen_paper_fail( $result->get_error_message() );
	}
	$notice = 'request_membership' === $operation ? 'join_requested' : ( 'review_membership' === $operation ? 'join_reviewed' : 'settings_saved' );
	$url = $page_id ? ps_pen_paper_game_url( $page_id, $campaign_id ) : ps_pen_paper_admin_url( $campaign_id );
	$self_service = in_array( $operation, array( 'request_membership', 'accept_invitation', 'decline_invitation' ), true );
	wp_safe_redirect( add_query_arg( 'pspp_notice', $notice, $url ) . ( $self_service ? '' : '#pspp-settings' ) );
	exit;
}

function ps_pen_paper_render_campaign_preview( $campaign, $page_id ) {
	$access = ps_pen_paper_get_access( $campaign->ID );
	$invitations = ps_pen_paper_can_access_site() ? ps_pen_paper_invitations( $campaign->ID ) : null;
	if ( is_wp_error( $invitations ) ) { return ps_pen_paper_frontend_message( $invitations->get_error_message() ); }
	if ( $invitations && in_array( get_current_user_id(), $invitations['users'], true ) ) {
		$membership = ps_pen_paper_membership_revision( $campaign->ID );
		if ( is_wp_error( $membership ) ) { return ps_pen_paper_frontend_message( $membership->get_error_message() ); }
		ob_start();
		?><div class="pspp-game pspp-lobby">
			<h2><?php echo esc_html( $campaign->post_title ); ?></h2>
			<p><?php esc_html_e( 'Du wurdest als Spieler eingeladen. Erst nach deiner Annahme erhältst du Zugriff auf die Runde.', 'ps-pen-paper' ); ?></p>
			<?php foreach ( array( 'accept_invitation' => __( 'Einladung annehmen', 'ps-pen-paper' ), 'decline_invitation' => __( 'Einladung ablehnen', 'ps-pen-paper' ) ) as $action => $label ) :
				ps_pen_paper_form_start( $action, 'pspp_join_' . $campaign->ID, $campaign->ID, $page_id ); ?>
				<input type="hidden" name="invitation_revision" value="<?php echo esc_attr( $invitations['revision'] ); ?>">
				<input type="hidden" name="membership_revision" value="<?php echo esc_attr( $membership['revision'] ); ?>">
				<button type="submit"><?php echo esc_html( $label ); ?></button>
				</form>
			<?php endforeach; ?>
		</div><?php
		return ob_get_clean();
	}
	if ( is_wp_error( $access ) || 'teaser' !== $access['visibility'] ) {
		return ! is_user_logged_in() ? ps_pen_paper_frontend_login( $page_id, $campaign->ID ) : ps_pen_paper_frontend_message( __( 'Diese Kampagne ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	ob_start();
	?>
	<div class="pspp-game pspp-lobby">
		<span class="pspp-eyebrow"><?php esc_html_e( 'Öffentliche Kampagnenvorschau', 'ps-pen-paper' ); ?></span>
		<h2><?php echo esc_html( $campaign->post_title ); ?></h2>
		<p class="pspp-multiline"><?php echo esc_html( $access['teaser'] ); ?></p>
		<p><?php esc_html_e( 'Spieltisch, Teilnehmer, Bilder, Charaktere, Würfe und Chat bleiben den Mitgliedern vorbehalten.', 'ps-pen-paper' ); ?></p>
		<?php ps_pen_paper_render_notice(); ?>
		<?php if ( ! is_user_logged_in() ) : ?>
			<a class="button button-primary" href="<?php echo esc_url( wp_login_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) ) ); ?>"><?php esc_html_e( 'Anmelden', 'ps-pen-paper' ); ?></a>
		<?php elseif ( ! current_user_can( 'read' ) || ! ps_pen_paper_site_user( get_current_user_id() ) ) : ?>
			<p><?php esc_html_e( 'Für eine Teilnahme brauchst du ein Benutzerkonto mit Zugriff auf diese Website. Bitte kontaktiere deren Betreiber.', 'ps-pen-paper' ); ?></p>
		<?php elseif ( 'approval' === $access['admission'] ) : ?>
			<?php $request = get_post_meta( $campaign->ID, '_pspp_join_' . get_current_user_id(), true ); ?>
			<?php if ( 'pending' === $request ) : ?>
				<p role="status"><?php esc_html_e( 'Deine Anfrage wartet auf die Spielleitung. Du erhältst erst nach ihrer Freigabe Zugriff.', 'ps-pen-paper' ); ?></p>
			<?php elseif ( '' !== $request ) : ?>
				<p><?php esc_html_e( 'Deine Anfrage wurde bereits entschieden. Falls du noch keinen Zugriff hast, kontaktiere bitte die Spielleitung.', 'ps-pen-paper' ); ?></p>
			<?php else : ?>
				<?php ps_pen_paper_form_start( 'request_membership', 'pspp_join_' . $campaign->ID, $campaign->ID, $page_id ); ?>
					<p><?php esc_html_e( 'Mit der Anfrage teilst du der Spielleitung deinen Anzeigenamen mit. Sie entscheidet über die Aufnahme als Spieler. Anfrage und Entscheidung werden nur für die Spielleitung protokolliert.', 'ps-pen-paper' ); ?></p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Teilnahme beantragen', 'ps-pen-paper' ); ?></button>
				</form>
			<?php endif; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Diese Runde nimmt derzeit keine Beitrittsanfragen an. Die Spielleitung kann Mitglieder persönlich hinzufügen.', 'ps-pen-paper' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

function ps_pen_paper_render_access_settings( $campaign, $page_id = 0 ) {
	if ( ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return;
	}
	$access = ps_pen_paper_get_access( $campaign->ID );
	$backdrop = ps_pen_paper_get_backdrop( $campaign->ID );
	$membership = ps_pen_paper_membership_revision( $campaign->ID );
	$invitations = ps_pen_paper_invitations( $campaign->ID );
	foreach ( array( $access, $backdrop, $membership, $invitations ) as $error ) {
		if ( is_wp_error( $error ) ) {
			echo '<p role="alert">' . esc_html( $error->get_error_message() ) . '</p>';
			return;
		}
	}
	?>
	<h2><?php esc_html_e( 'Deine Runde einrichten', 'ps-pen-paper' ); ?></h2>
	<p><?php esc_html_e( 'Die Spielleitung entscheidet, wer mitspielt. Eine freigegebene Vorschau öffnet niemals den Spieltisch.', 'ps-pen-paper' ); ?></p>
	<?php if ( $page_id ) : ?>
		<section class="pspp-settings-section">
			<h3><?php esc_html_e( 'Kampagne', 'ps-pen-paper' ); ?></h3>
			<?php ps_pen_paper_render_campaign_settings( $campaign, $page_id ); ?>
		</section>
		<section class="pspp-settings-section">
			<?php ps_pen_paper_render_campaign_members( $campaign, $page_id ); ?>
		</section>
	<?php endif; ?>
	<?php ps_pen_paper_render_currency_settings( $campaign, $page_id ); ?>
	<?php ps_pen_paper_render_trade_policy( $campaign, $page_id ); ?>
	<?php ps_pen_paper_render_export_settings( $campaign, $page_id ); ?>
	<section class="pspp-settings-section">
		<h3><?php esc_html_e( 'Zugang & Privatsphäre', 'ps-pen-paper' ); ?></h3>
		<?php ps_pen_paper_form_start( 'save_access', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<input type="hidden" name="settings_revision" value="<?php echo esc_attr( $access['revision'] ); ?>">
			<p><label for="pspp-access-visibility"><?php esc_html_e( 'Gäste und Nichtmitglieder', 'ps-pen-paper' ); ?></label>
				<select id="pspp-access-visibility" name="access[visibility]">
					<option value="private" <?php selected( $access['visibility'], 'private' ); ?>><?php esc_html_e( 'Geschlossen – keine Kampagnendaten', 'ps-pen-paper' ); ?></option>
					<option value="teaser" <?php selected( $access['visibility'], 'teaser' ); ?>><?php esc_html_e( 'Titel und separaten Teaser über den Kampagnenlink freigeben', 'ps-pen-paper' ); ?></option>
				</select>
			</p>
			<p><label for="pspp-access-teaser"><?php esc_html_e( 'Öffentlicher Teaser – keine Geheimnisse eintragen', 'ps-pen-paper' ); ?></label>
				<textarea id="pspp-access-teaser" name="access[teaser]" rows="4" maxlength="6000"><?php echo esc_textarea( $access['teaser'] ); ?></textarea>
			</p>
			<p><?php esc_html_e( 'Die eigentliche Kampagnenbeschreibung bleibt intern. Bereits veröffentlichte Teaser können von Besuchern kopiert worden sein; das Schließen holt solche Kopien nicht zurück.', 'ps-pen-paper' ); ?></p>
			<p><label for="pspp-access-admission"><?php esc_html_e( 'Neue Teilnehmer', 'ps-pen-paper' ); ?></label>
				<select id="pspp-access-admission" name="access[admission]">
					<option value="closed" <?php selected( $access['admission'], 'closed' ); ?>><?php esc_html_e( 'Nur persönlich durch die Spielleitung hinzufügen', 'ps-pen-paper' ); ?></option>
					<option value="approval" <?php selected( $access['admission'], 'approval' ); ?>><?php esc_html_e( 'Anfragen erlauben – Aufnahme erst nach Freigabe', 'ps-pen-paper' ); ?></option>
				</select>
			</p>
			<p class="description"><?php esc_html_e( 'Anfragen sind nur bei freigegebener Vorschau und mit einem bestehenden Benutzerkonto dieser Website möglich. Es gibt keinen automatischen Beitritt.', 'ps-pen-paper' ); ?></p>
			<?php if ( $page_id ) : ?><p><label for="pspp-invite-link"><?php esc_html_e( 'Kampagnenlink zum Weitergeben', 'ps-pen-paper' ); ?></label><input id="pspp-invite-link" type="text" readonly value="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) ); ?>"></p><?php endif; ?>
			<input type="hidden" name="settings_complete" value="complete">
			<?php submit_button( __( 'Zugang speichern', 'ps-pen-paper' ) ); ?>
		</form>
	</section>
	<section class="pspp-settings-section">
		<h3><?php esc_html_e( 'Einladungen', 'ps-pen-paper' ); ?></h3>
		<p><?php esc_html_e( 'Lade bestehende Website-Benutzer als Spieler ein und sende ihnen den Kampagnenlink. Erst ihre Annahme gewährt Zugriff; es wird keine Nachricht automatisch versendet.', 'ps-pen-paper' ); ?></p>
		<?php ps_pen_paper_form_start( 'invite_user', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<input type="hidden" name="invitation_revision" value="<?php echo esc_attr( $invitations['revision'] ); ?>">
			<label><?php esc_html_e( 'Benutzer', 'ps-pen-paper' ); ?> <?php ps_pen_paper_user_select(); ?></label>
			<button type="submit"><?php esc_html_e( 'Einladen', 'ps-pen-paper' ); ?></button>
		</form>
		<?php if ( ! $invitations['users'] ) : ?><p><?php esc_html_e( 'Keine offenen Einladungen.', 'ps-pen-paper' ); ?></p><?php endif; ?>
		<?php foreach ( $invitations['users'] as $user_id ) : $user = get_userdata( $user_id ); ?>
			<div><strong><?php echo esc_html( $user ? $user->display_name . ' · #' . $user_id : sprintf( __( 'Gelöschter Benutzer #%d', 'ps-pen-paper' ), $user_id ) ); ?></strong>
				<?php ps_pen_paper_form_start( 'revoke_invitation', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
					<input type="hidden" name="invitation_revision" value="<?php echo esc_attr( $invitations['revision'] ); ?>">
					<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
					<button type="submit"><?php esc_html_e( 'Einladung zurückziehen', 'ps-pen-paper' ); ?></button>
				</form>
			</div>
		<?php endforeach; ?>
	</section>
	<section class="pspp-settings-section">
		<h3><?php esc_html_e( 'Beitrittsanfragen', 'ps-pen-paper' ); ?></h3>
		<?php $requests = ps_pen_paper_pending_memberships( $campaign ); ?>
		<?php if ( ! $requests ) : ?><p><?php esc_html_e( 'Keine offenen Anfragen.', 'ps-pen-paper' ); ?></p><?php endif; ?>
		<?php foreach ( $requests as $user ) : ?>
			<div class="pspp-admission">
				<strong><?php echo esc_html( $user->display_name . ' · #' . $user->ID ); ?></strong>
				<?php ps_pen_paper_form_start( 'review_membership', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
					<input type="hidden" name="membership_revision" value="<?php echo esc_attr( $membership['revision'] ); ?>">
					<input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>">
					<button type="submit" name="decision" value="approved"><?php esc_html_e( 'Als Spieler aufnehmen', 'ps-pen-paper' ); ?></button>
					<button type="submit" name="decision" value="rejected"><?php esc_html_e( 'Ablehnen', 'ps-pen-paper' ); ?></button>
				</form>
			</div>
		<?php endforeach; ?>
	</section>
	<section class="pspp-settings-section">
		<h3><?php esc_html_e( 'Bild für den Spieltisch', 'ps-pen-paper' ); ?></h3>
		<p><?php esc_html_e( 'Nur Mitglieder sehen das Bild. Es wird geschützt in der Datenbank gespeichert, nicht unter einer öffentlichen Mediathek-Adresse. Verwende nur Bilder, für die du die nötigen Rechte besitzt.', 'ps-pen-paper' ); ?></p>
		<p><?php echo esc_html( $backdrop['asset_id'] ? __( 'Ein eigenes Hintergrundbild ist aktiv.', 'ps-pen-paper' ) : __( 'Aktuell wird der bildfreie Spieltisch verwendet.', 'ps-pen-paper' ) ); ?></p>
		<?php ps_pen_paper_form_start( 'save_backdrop', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id, true ); ?>
			<input type="hidden" name="settings_revision" value="<?php echo esc_attr( $backdrop['revision'] ); ?>">
			<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo esc_attr( 2 * MB_IN_BYTES ); ?>">
			<label for="pspp-backdrop"><?php esc_html_e( 'PNG, JPEG oder WebP · höchstens 2 MiB und 3000 × 3000 Pixel', 'ps-pen-paper' ); ?></label>
			<input id="pspp-backdrop" type="file" name="backdrop" accept="image/png,image/jpeg,image/webp">
			<p><button type="submit" name="backdrop_action" value="upload"><?php esc_html_e( 'Hintergrund speichern', 'ps-pen-paper' ); ?></button>
				<?php if ( $backdrop['asset_id'] ) : ?><button type="submit" name="backdrop_action" value="remove"><?php esc_html_e( 'Hintergrund entfernen', 'ps-pen-paper' ); ?></button><?php endif; ?>
			</p>
		</form>
	</section>
	<details class="pspp-fold">
		<summary><?php esc_html_e( 'Farben & Lesbarkeit anpassen', 'ps-pen-paper' ); ?></summary>
		<?php ps_pen_paper_render_style_settings( $campaign, $page_id ); ?>
	</details>
	<?php ps_pen_paper_render_sheet_style_form( $campaign, $page_id ); ?>
	<?php if ( $page_id ) : ?>
		<p><a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-rulebook' ); ?>"><?php esc_html_e( 'Regelwerk einrichten oder bearbeiten', 'ps-pen-paper' ); ?></a></p>
	<?php else : ?>
		<p><a href="<?php echo esc_url( ps_pen_paper_admin_url( $campaign->ID ) ); ?>"><?php esc_html_e( 'Weitere Vorbereitung: Regeln und Mitgliederverwaltung', 'ps-pen-paper' ); ?></a></p>
	<?php endif; ?>
	<?php
}
