<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_handle_character_policy_action( $operation ) {
	$id = ps_pen_paper_posted_id( 'campaign_id' );
	$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	if ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) { ps_pen_paper_fail( __( 'Die Spielbereich-Seite ist nicht mehr verfügbar.', 'ps-pen-paper' ) ); }
	check_admin_referer( 'pspp_campaign_' . $id );
	$character_id = in_array( $operation, array( 'select_portrait_preset', 'profile_portrait' ), true ) ? ps_pen_paper_posted_id( 'character_id' ) : 0;
	$profile_url = ps_pen_paper_profile_return( $id, $character_id );
	if ( 'profile_create_character' === $operation ) {
		if ( 'complete' !== ps_pen_paper_posted_text( 'character_complete' ) ) { ps_pen_paper_fail( __( 'Der Charakter wurde nicht vollständig übertragen.', 'ps-pen-paper' ) ); }
		$result = ps_pen_paper_profile_character_request( 'create', array( 'universe_id' => $id,
			'universe_epoch' => ps_pen_paper_posted_text( 'universe_epoch' ),
			'character' => isset( $_POST['character'] ) ? wp_unslash( $_POST['character'] ) : null,
			'rules_revision' => ps_pen_paper_posted_text( 'character_rules_revision' ) ) );
	} elseif ( 'save_character_policy' === $operation ) {
		if ( 'complete' !== ps_pen_paper_posted_text( 'policy_complete' ) ) { ps_pen_paper_fail( __( 'Die Vorgaben wurden nicht vollständig übertragen.', 'ps-pen-paper' ) ); }
		$result = ps_pen_paper_save_character_policy( $id, isset( $_POST['policy'] ) ? wp_unslash( $_POST['policy'] ) : null, ps_pen_paper_posted_text( 'policy_revision' ) );
	} elseif ( 'member_invitation' === $operation ) {
		$decision = ps_pen_paper_posted_text( 'invitation_operation' );
		$user_id = in_array( $decision, array( 'accept', 'decline' ), true ) ? get_current_user_id() : ps_pen_paper_posted_id( 'user_id' );
		$result = ps_pen_paper_change_member_invitation( $id, $user_id, $decision, ps_pen_paper_posted_text( 'invitation_revision' ) );
	} elseif ( 'upload_portrait_preset' === $operation ) {
		$file = $_FILES['portrait_preset'] ?? null;
		if ( ! is_array( $file ) || ! isset( $file['error'], $file['tmp_name'] ) || UPLOAD_ERR_OK !== $file['error'] ||
			! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) { ps_pen_paper_fail( __( 'Das Bild wurde nicht vollständig hochgeladen.', 'ps-pen-paper' ) ); }
		$result = ps_pen_paper_add_portrait_preset( $id, ps_pen_paper_posted_text( 'preset_name' ), $file['tmp_name'], ps_pen_paper_posted_text( 'policy_revision' ) );
	} elseif ( 'select_portrait_preset' === $operation ) {
		$result = ps_pen_paper_save_portrait( ps_pen_paper_get_character_scope( $id ), ps_pen_paper_posted_id( 'character_id' ),
			ps_pen_paper_posted_text( 'portrait_revision' ), 'preset', ps_pen_paper_posted_id( 'portrait_asset_id' ) );
	} elseif ( 'profile_portrait' === $operation ) {
		$mode = ps_pen_paper_posted_text( 'portrait_operation' );
		$path = null;
		if ( 'upload' === $mode ) {
			$file = $_FILES['portrait_upload'] ?? null;
			if ( ! is_array( $file ) || ! isset( $file['error'], $file['tmp_name'] ) || UPLOAD_ERR_OK !== $file['error'] ||
				! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) { ps_pen_paper_fail( __( 'Das Porträt wurde nicht vollständig hochgeladen.', 'ps-pen-paper' ) ); }
			$path = $file['tmp_name'];
		} elseif ( 'presentation' === $mode ) {
			$view = ps_pen_paper_posted_image_view();
			if ( is_wp_error( $view ) ) { ps_pen_paper_fail( $view->get_error_message() ); }
			$path = array( 'view' => $view, 'share' => '1' === ps_pen_paper_posted_text( 'share_character' ) );
		}
		$result = ps_pen_paper_save_portrait( ps_pen_paper_get_character_scope( $id ), ps_pen_paper_posted_id( 'character_id' ),
			ps_pen_paper_posted_text( 'portrait_revision' ), $mode, $path );
	} else { ps_pen_paper_fail( __( 'Diese Aktion ist ungültig.', 'ps-pen-paper' ) ); }
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	if ( $profile_url && 'profile_create_character' === $operation ) {
		$profile_url = ps_pen_paper_profile_return( $id, $result );
	}
	$notice = 'profile_create_character' === $operation ? 'character_saved' :
		( 'member_invitation' === $operation ? 'member_invitation_saved' : 'character_policy_saved' );
	$return_url = ps_pen_paper_dashboard_url( 'universes', $page_id );
	if ( 'member_invitation' === $operation && ps_pen_paper_get_campaign( $id ) ) {
		$return_url = $page_id ? ps_pen_paper_game_url( $page_id, $id ) . '#pspp-settings' : ps_pen_paper_admin_url( $id );
	}
	if ( 'member_invitation' === $operation && $page_id && in_array( $decision, array( 'accept', 'decline' ), true ) ) {
		$return_url = ps_pen_paper_game_url( $page_id ) . '#pspp-lounge-invitations';
	}
	wp_safe_redirect( add_query_arg( 'pspp_notice', $notice, $profile_url ?: $return_url ) );
	exit;
}

function ps_pen_paper_render_character_policy( $universe, $page_id = 0 ) {
	if ( ! ps_pen_paper_can_manage_universe( $universe ) ) { return; }
	$policy = ps_pen_paper_character_policy( $universe->ID );
	if ( is_wp_error( $policy ) ) { echo '<p role="alert">' . esc_html( $policy->get_error_message() ) . '</p>'; return; }
	echo '<h3>' . esc_html__( 'Charaktererstellung, Einladungen & Porträts', 'ps-pen-paper' ) . '</h3>';
	ps_pen_paper_form_start( 'save_character_policy', 'pspp_campaign_' . $universe->ID, $universe->ID, $page_id );
	?>
		<input type="hidden" name="policy_revision" value="<?php echo esc_attr( $policy['revision'] ); ?>">
		<p><label><?php esc_html_e( 'Maximale Charaktere pro Person', 'ps-pen-paper' ); ?>
			<input type="number" name="policy[max_characters]" value="<?php echo esc_attr( $policy['max_characters'] ); ?>" min="1" max="100" step="1" required></label></p>
		<p><?php esc_html_e( 'Offene Erstfreigaben zählen mit. Gemeinsame Bögen und ausdrücklich abgeleitete Kopien zählen nur einmal; unabhängige historische Kampagnenbögen zählen einzeln. Bestehende Bögen werden bei einer niedrigeren Grenze nicht gelöscht oder verändert.', 'ps-pen-paper' ); ?></p>
		<p><label><?php esc_html_e( 'Neue Charaktere erstellen', 'ps-pen-paper' ); ?>
			<select name="policy[creation_access]">
				<option value="members" <?php selected( $policy['creation_access'], 'members' ); ?>><?php esc_html_e( 'Universumsmitglieder und Mitglieder zugeordneter Kampagnen', 'ps-pen-paper' ); ?></option>
				<option value="site" <?php selected( $policy['creation_access'], 'site' ); ?>><?php esc_html_e( 'Alle leseberechtigten Website-Mitglieder (nur Weltname sichtbar)', 'ps-pen-paper' ); ?></option>
			</select></label></p>
		<p><label><?php esc_html_e( 'Einladungen anderer Mitglieder', 'ps-pen-paper' ); ?>
			<select name="policy[member_invites]">
				<option value="approval" <?php selected( $policy['member_invites'], 'approval' ); ?>><?php esc_html_e( 'Zuerst Freigabe der Spielleitung (Standard)', 'ps-pen-paper' ); ?></option>
				<option value="direct" <?php selected( $policy['member_invites'], 'direct' ); ?>><?php esc_html_e( 'Direkt anbieten; Annahme durch eingeladene Person nötig', 'ps-pen-paper' ); ?></option>
			</select></label></p>
		<p><label><?php esc_html_e( 'Eigene Porträtuploads durch Spieler', 'ps-pen-paper' ); ?>
			<select name="policy[custom_portraits]">
				<option value="disabled" <?php selected( $policy['custom_portraits'], false ); ?>><?php esc_html_e( 'Deaktiviert (Standard)', 'ps-pen-paper' ); ?></option>
				<option value="allowed" <?php selected( $policy['custom_portraits'], true ); ?>><?php esc_html_e( 'Erlaubt', 'ps-pen-paper' ); ?></option>
			</select></label></p>
		<p><?php esc_html_e( 'Die Spielleitung kann weiterhin Bilder verwalten. Bereits gespeicherte Porträts und offene Bildfreigaben bleiben erhalten.', 'ps-pen-paper' ); ?></p>
		<?php foreach ( $policy['presets'] as $index => $preset ) : ?>
			<p><label><?php esc_html_e( 'Porträtname', 'ps-pen-paper' ); ?>
				<input name="<?php echo esc_attr( 'policy[presets][' . $index . '][name]' ); ?>" value="<?php echo esc_attr( $preset['name'] ); ?>" maxlength="200" required></label>
				<input type="hidden" name="<?php echo esc_attr( 'policy[presets][' . $index . '][asset_id]' ); ?>" value="<?php echo esc_attr( $preset['asset_id'] ); ?>">
				<label><input type="checkbox" name="<?php echo esc_attr( 'policy[presets][' . $index . '][remove]' ); ?>" value="confirmed">
					<?php esc_html_e( 'Aus Auswahl entfernen (gespeicherte Charakterbilder bleiben erhalten)', 'ps-pen-paper' ); ?></label>
			</p>
		<?php endforeach; ?>
		<input type="hidden" name="policy_complete" value="complete">
		<?php submit_button( __( 'Charaktervorgaben speichern', 'ps-pen-paper' ) ); ?>
	</form>
	<p><?php esc_html_e( 'Frei benannte Auswahlfelder richtest du in der Charaktervorlage als kurze Textfelder mit optional erlaubten Werten ein. Eine Änderung bestehender Felder erfordert die ausdrückliche Datenmigration.', 'ps-pen-paper' ); ?></p>
	<?php ps_pen_paper_form_start( 'upload_portrait_preset', 'pspp_campaign_' . $universe->ID, $universe->ID, $page_id, true ); ?>
		<input type="hidden" name="policy_revision" value="<?php echo esc_attr( $policy['revision'] ); ?>">
		<p><label><?php esc_html_e( 'Neues Auswahlporträt: Name', 'ps-pen-paper' ); ?> <input name="preset_name" maxlength="200" required></label></p>
		<p><label><?php esc_html_e( 'Privates Bild (PNG/JPEG/WebP, bis 2 MiB)', 'ps-pen-paper' ); ?> <input type="file" name="portrait_preset" accept="image/png,image/jpeg,image/webp" required></label></p>
		<?php submit_button( __( 'Geschütztes Auswahlporträt hinzufügen', 'ps-pen-paper' ) ); ?>
	</form>
	<?php ps_pen_paper_render_member_invitation_form( $universe, $page_id ); ?>
	<?php
}

function ps_pen_paper_render_member_invitation_form( $scope, $page_id = 0 ) {
	if ( ! ps_pen_paper_can_access_site() || ( ! ps_pen_paper_can_manage_character_scope( $scope ) && ! ps_pen_paper_invitation_scope_member( $scope, get_current_user_id() ) ) ) { return; }
	$state = ps_pen_paper_member_invitations( $scope->ID );
	if ( is_wp_error( $state ) ) { echo '<p role="alert">' . esc_html( $state->get_error_message() ) . '</p>'; return; }
	ps_pen_paper_form_start( 'member_invitation', 'pspp_campaign_' . $scope->ID, $scope->ID, $page_id );
	?>
		<h4><?php echo esc_html( 'pspp_campaign' === $scope->post_type ? __( 'Zur Spielrunde einladen', 'ps-pen-paper' ) : __( 'Zum Universum einladen', 'ps-pen-paper' ) ); ?></h4>
		<?php
		$document = 'pspp_universe' === $scope->post_type ? ps_pen_paper_universe_document( $scope->ID ) : null;
		if ( is_wp_error( $document ) ) { echo '<p role="alert">' . esc_html( $document->get_error_message() ) . '</p>'; }
		else { echo '<p><strong>' . esc_html( $document ? $document['name'] : $scope->post_title ) . '</strong></p>'; }
		?>
		<input type="hidden" name="invitation_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
		<input type="hidden" name="invitation_operation" value="invite">
		<p><label><?php esc_html_e( 'Spieler auswählen', 'ps-pen-paper' ); ?>
			<select name="user_id" required>
				<option value=""><?php esc_html_e( 'Person wählen', 'ps-pen-paper' ); ?></option>
				<?php foreach ( get_users( array( 'blog_id' => get_current_blog_id(), 'orderby' => 'display_name' ) ) as $user ) : ?>
					<?php if ( ! user_can( $user, 'read' ) || ps_pen_paper_invitation_scope_member( $scope, (int) $user->ID ) ) { continue; } ?>
					<option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->display_name ); ?></option>
				<?php endforeach; ?>
			</select></label></p>
		<p><?php esc_html_e( 'Mitgliedereinladungen benötigen standardmäßig zuerst die Freigabe der Spielleitung. Die eingeladene Person sieht den offenen Freigabestatus im Profil.', 'ps-pen-paper' ); ?></p>
		<?php submit_button( __( 'Einladung senden', 'ps-pen-paper' ) ); ?>
	</form>
	<?php if ( ps_pen_paper_management_view( $page_id ) ) { ps_pen_paper_render_profile_invitations( $page_id, $scope ); } ?>
	<?php
}

function ps_pen_paper_render_profile_invitations( $page_id = 0, $management_scope = null ) {
	$invitations = ps_pen_paper_profile_invitations();
	echo '<h3>' . esc_html( $management_scope ? __( 'Offene Einladungen verwalten', 'ps-pen-paper' ) : __( 'Universums- und Kampagneneinladungen', 'ps-pen-paper' ) ) . '</h3>';
	foreach ( $invitations['errors'] as $error ) { echo '<p role="alert">' . esc_html( $error ) . '</p>'; }
	$buckets = $management_scope ? array( 'approvals', 'sent' ) : array( 'received' );
	$shown = false;
	foreach ( $buckets as $bucket ) {
		foreach ( $invitations[ $bucket ] as $row ) {
			if ( $management_scope && $row['scope_id'] !== (int) $management_scope->ID ) { continue; }
			$shown = true;
			echo '<section class="pspp-card"><h4>' . esc_html( ( 'pspp_campaign' === $row['scope_type'] ? __( 'Spielrunde:', 'ps-pen-paper' ) : __( 'Universum:', 'ps-pen-paper' ) ) . ' ' . $row['name'] ) . '</h4>';
			$inviter = ps_pen_paper_site_user( $row['inviter_id'] );
			echo '<p>' . esc_html__( 'Eingeladen von:', 'ps-pen-paper' ) . ' ' . esc_html( $inviter ? $inviter->display_name : __( 'Ehemaliges Mitglied', 'ps-pen-paper' ) ) . '</p>';
			if ( $management_scope ) {
				$invitee = ps_pen_paper_site_user( $row['user_id'] );
				echo '<p>' . esc_html__( 'Eingeladener Spieler:', 'ps-pen-paper' ) . ' ' . esc_html( $invitee ? $invitee->display_name : __( 'Ehemaliges Mitglied', 'ps-pen-paper' ) ) . '</p>';
			}
			if ( 'approval' === $row['status'] ) { echo '<p>' . esc_html__( 'Die Spielleitung muss diese Einladung noch bestätigen.', 'ps-pen-paper' ) . '</p>'; }
			$actions = 'approvals' === $bucket ? array( 'approve' => __( 'Freigeben', 'ps-pen-paper' ), 'reject' => __( 'Ablehnen', 'ps-pen-paper' ) ) :
				( 'sent' === $bucket ? array( 'revoke' => __( 'Zurücknehmen', 'ps-pen-paper' ) ) :
					array( 'accept' => __( 'Annehmen', 'ps-pen-paper' ), 'decline' => __( 'Ablehnen', 'ps-pen-paper' ) ) );
			if ( 'received' === $bucket && 'approval' === $row['status'] ) { unset( $actions['accept'] ); }
			foreach ( $actions as $action => $label ) {
				$legacy = ! empty( $row['legacy'] );
				ps_pen_paper_form_start( $legacy ? ( 'accept' === $action ? 'accept_invitation' : 'decline_invitation' ) : 'member_invitation',
					( $legacy ? 'pspp_join_' : 'pspp_campaign_' ) . $row['scope_id'], $row['scope_id'], $page_id );
				foreach ( array( 'user_id' => $row['user_id'], 'invitation_operation' => $action, 'invitation_revision' => $row['revision'],
					'membership_revision' => $row['membership_revision'] ?? '' ) as $key => $value ) {
					echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
				}
				echo '<button type="submit">' . esc_html( $label ) . '</button></form>';
			}
			echo '</section>';
		}
	}
	if ( ! $shown ) { echo '<p>' . esc_html__( 'Keine offenen Universums- oder Kampagneneinladungen.', 'ps-pen-paper' ) . '</p>'; }
}

function ps_pen_paper_render_portrait_presets( $scope, $character, $page_id = 0, $profile = false ) {
	if ( ! ps_pen_paper_can_edit_portrait( $scope, $character->ID ) ) { return; }
	$universe = ps_pen_paper_portrait_policy_scope( $scope );
	if ( is_wp_error( $universe ) ) { echo '<p role="alert">' . esc_html( $universe->get_error_message() ) . '</p>'; return; }
	if ( ! $universe ) { return; }
	$policy = ps_pen_paper_character_policy( $universe->ID );
	$state = ps_pen_paper_portrait_state( $character->ID );
	$gm = ! $profile && ps_pen_paper_management_view( $page_id ) && ps_pen_paper_can_manage_character_scope( $scope );
	if ( is_wp_error( $policy ) || is_wp_error( $state ) ) { echo '<p role="alert">' . esc_html( is_wp_error( $policy ) ? $policy->get_error_message() : $state->get_error_message() ) . '</p>'; return; }
	if ( ! $policy['custom_portraits'] && ! $gm ) { echo '<p>' . esc_html__( 'Eigene Porträtuploads sind deaktiviert. Bereits gespeicherte Bilder bleiben erhalten.', 'ps-pen-paper' ) . '</p>'; }
	if ( 'pspp_universe' === $scope->post_type ) {
		if ( $policy['custom_portraits'] || $gm ) {
			ps_pen_paper_form_start( 'profile_portrait', 'pspp_campaign_' . $scope->ID, $scope->ID, $page_id, true );
			?>
			<input type="hidden" name="character_id" value="<?php echo esc_attr( $character->ID ); ?>">
			<input type="hidden" name="portrait_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
			<input type="hidden" name="portrait_operation" value="upload">
			<p><label><?php esc_html_e( 'Eigenes Porträt (PNG/JPEG/WebP, bis 2 MiB)', 'ps-pen-paper' ); ?>
				<input type="file" name="portrait_upload" accept="image/png,image/jpeg,image/webp" required></label></p>
			<?php submit_button( __( 'Porträt hochladen', 'ps-pen-paper' ) ); ?>
			</form>
			<?php
		}
		$actions = array( 'remove' => __( 'Porträt entfernen', 'ps-pen-paper' ) );
		if ( $gm && $state['pending'] ) {
			$pending_image = $state['pending']['asset_id'] ? ps_pen_paper_private_image_data( $scope, $state['pending']['asset_id'] ) : '';
			if ( is_wp_error( $pending_image ) ) { echo '<p role="alert">' . esc_html( $pending_image->get_error_message() ) . '</p>'; }
			if ( $pending_image && ! is_wp_error( $pending_image ) ) { echo '<span class="pspp-portrait"><img src="' . esc_attr( $pending_image ) . '" alt=""></span>'; }
			$actions['approve'] = __( 'Porträt freigeben', 'ps-pen-paper' );
			$actions['reject'] = __( 'Porträtantrag ablehnen', 'ps-pen-paper' );
		}
		foreach ( $actions as $action => $label ) {
			ps_pen_paper_form_start( 'profile_portrait', 'pspp_campaign_' . $scope->ID, $scope->ID, $page_id );
			foreach ( array( 'character_id' => $character->ID, 'portrait_revision' => $state['revision'], 'portrait_operation' => $action ) as $key => $value ) {
				echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
			}
			echo '<button type="submit">' . esc_html( $label ) . '</button></form>';
		}
	}
	if ( ! $policy['presets'] ) { echo '<p>' . esc_html__( 'Die Universumsleitung hat noch keine Auswahlporträts hinterlegt.', 'ps-pen-paper' ) . '</p>'; return; }
	ps_pen_paper_form_start( 'select_portrait_preset', 'pspp_campaign_' . $scope->ID, $scope->ID, $page_id );
	?>
		<input type="hidden" name="character_id" value="<?php echo esc_attr( $character->ID ); ?>">
		<input type="hidden" name="portrait_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
		<fieldset><legend><?php esc_html_e( 'Geschütztes Auswahlporträt', 'ps-pen-paper' ); ?></legend>
		<?php foreach ( $policy['presets'] as $preset ) :
			$image = ps_pen_paper_private_image_data( $universe, $preset['asset_id'] );
			if ( is_wp_error( $image ) ) {
				echo '<p role="alert">' . esc_html( $preset['name'] . ': ' . $image->get_error_message() ) . '</p>';
				continue;
			}
			?>
			<label><input type="radio" name="portrait_asset_id" value="<?php echo esc_attr( $preset['asset_id'] ); ?>" required>
				<span class="pspp-portrait"><img src="<?php echo esc_attr( $image ); ?>" alt=""></span>
				<?php echo esc_html( $preset['name'] ); ?></label>
		<?php endforeach; ?>
		</fieldset>
		<p><?php esc_html_e( 'Die Auswahl wird geschützt als eigenes Bild eingefroren. Spätere Vorlagenänderungen ersetzen dein Bild nicht.', 'ps-pen-paper' ); ?></p>
		<?php submit_button( __( 'Porträt auswählen', 'ps-pen-paper' ) ); ?>
	</form>
	<?php
}

function ps_pen_paper_render_profile_universe_characters( $universe_id, $page_id = 0 ) {
	$choice = null;
	foreach ( ps_pen_paper_profile_universe_choices() as $row ) { if ( $row['id'] === $universe_id ) { $choice = $row; break; } }
	if ( ! $choice ) { echo '<p role="alert">' . esc_html__( 'Dieses Universum ist nicht verfügbar.', 'ps-pen-paper' ) . '</p>'; return; }
	$universe = ps_pen_paper_get_universe( $universe_id );
	echo '<h3>' . esc_html( $choice['name'] ) . '</h3>';
	/* translators: 1: existing character count, 2: configured character limit. */
	echo '<p>' . esc_html( sprintf( __( 'Eigene Charaktere: %1$d / %2$d (einschließlich offener Erstfreigaben)', 'ps-pen-paper' ), $choice['count'], $choice['limit'] ) ) . '</p>';
	if ( $choice['can_create'] ) { ps_pen_paper_render_character_form( $universe, ps_pen_paper_get_rules( $universe_id ), null, $page_id, null, true ); }
	else { echo '<p role="' . ( ! empty( $choice['error'] ) ? 'alert' : 'status' ) . '">' . esc_html( $choice['reason'] ) . '</p>'; }
}
