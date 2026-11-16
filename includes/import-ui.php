<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_import_options_from_post() {
	$input = isset( $_POST['import'] ) ? wp_unslash( $_POST['import'] ) : null;
	if ( ! ps_pen_paper_import_keys( $input, array( 'operation', 'target_id', 'universe_mode', 'universe_id', 'users', 'bases' ) ) ) {
		return ps_pen_paper_import_error();
	}
	foreach ( array( 'operation', 'universe_mode' ) as $key ) { if ( ! is_string( $input[ $key ] ) ) { return ps_pen_paper_import_error(); } }
	foreach ( array( 'target_id', 'universe_id' ) as $key ) {
		if ( ! is_string( $input[ $key ] ) || ! preg_match( '/^(0|[1-9]\d*)$/D', $input[ $key ] ) ||
			(string) (int) $input[ $key ] !== $input[ $key ] ) { return ps_pen_paper_import_error(); }
		$input[ $key ] = (int) $input[ $key ];
	}
	foreach ( array( 'users', 'bases' ) as $key ) {
		// An empty hidden sentinel keeps the input present without inventing assignments.
		if ( '' === $input[ $key ] ) { $input[ $key ] = array(); }
		if ( ! is_array( $input[ $key ] ) ) { return ps_pen_paper_import_error(); }
		foreach ( $input[ $key ] as $id => &$value ) {
			if ( ! preg_match( '/^[1-9]\d*$/D', (string) $id ) || ! is_string( $value ) ||
				! preg_match( 'users' === $key ? '/^(-1|0|[1-9]\d*)$/D' : '/^(0|[1-9]\d*)$/D', $value ) ||
				(string) (int) $value !== $value ) { return ps_pen_paper_import_error(); }
			$value = (int) $value;
		}
		unset( $value );
	}
	return $input;
}

function ps_pen_paper_handle_import_preview_action( $operation ) {
	if ( ! ps_pen_paper_can_preview_import() ) { ps_pen_paper_fail( __( 'Du darfst keine Importvorschau verwalten.', 'ps-pen-paper' ) ); }
	$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	if ( $page_id && ! ps_pen_paper_frontend_page( $page_id ) ) { ps_pen_paper_fail( __( 'Die Spielbereich-Seite ist nicht verfügbar.', 'ps-pen-paper' ) ); }
	if ( 'preview_import' === $operation ) {
		check_admin_referer( 'pspp_import_upload' );
		$file = isset( $_FILES['import_file'] ) ? $_FILES['import_file'] : null;
		if ( ! is_array( $file ) || ! isset( $file['error'], $file['tmp_name'], $file['name'] ) ||
			UPLOAD_ERR_OK !== $file['error'] || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ||
			! is_string( $file['name'] ) || 'json' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) ) {
			ps_pen_paper_fail( __( 'Bitte lade eine vollständig übertragene JSON-Sicherungsdatei hoch. Sie wird nicht in der öffentlichen Mediathek abgelegt.', 'ps-pen-paper' ) );
		}
		$size = filesize( $file['tmp_name'] );
		if ( false === $size || $size < 1 || $size > ps_pen_paper_import_limit() ) { ps_pen_paper_fail( __( 'Die Datei überschreitet die Upload-/Speichergrenze dieses Hosts oder konnte nicht gelesen werden.', 'ps-pen-paper' ) ); }
		$json = file_get_contents( $file['tmp_name'] );
		if ( false === $json ) { ps_pen_paper_fail( __( 'Die hochgeladene Sicherungsdatei konnte nicht gelesen werden.', 'ps-pen-paper' ) ); }
		$token = ps_pen_paper_create_import_preview( $json );
		if ( is_wp_error( $token ) ) { ps_pen_paper_fail( $token->get_error_message() ); }
	} else {
		$token = ps_pen_paper_posted_text( 'import_token' );
		check_admin_referer( 'pspp_import_preview_' . $token );
		$preview = ps_pen_paper_get_import_preview( $token );
		if ( is_wp_error( $preview ) ) { ps_pen_paper_fail( $preview->get_error_message() ); }
		if ( 'discard_import_preview' === $operation ) {
			if ( ! delete_transient( ps_pen_paper_import_preview_key( $token ) ) ) { ps_pen_paper_fail( __( 'Die private Vorschau konnte nicht verworfen werden.', 'ps-pen-paper' ) ); }
			wp_safe_redirect( ps_pen_paper_dashboard_url( 'settings', $page_id ) ); exit;
		}
		$parsed = ps_pen_paper_parse_import( $preview['json'] );
		if ( is_wp_error( $parsed ) ) { ps_pen_paper_fail( $parsed->get_error_message() ); }
		$options = ps_pen_paper_import_options_from_post();
		if ( is_wp_error( $options ) ) { ps_pen_paper_fail( $options->get_error_message() ); }
		$plan = ps_pen_paper_import_configuration( $parsed, $options );
		if ( is_wp_error( $plan ) ) { ps_pen_paper_fail( $plan->get_error_message() ); }
		$changed = $preview['options'] !== $options;
		$preview['options'] = $options;
		$remaining = $preview['expires'] - time();
		if ( $remaining < 1 || ( $changed && ! set_transient( ps_pen_paper_import_preview_key( $token ), $preview, $remaining ) ) ) {
			ps_pen_paper_fail( __( 'Die Importvorschau konnte nicht aktualisiert werden oder ist abgelaufen. Bitte lade die Datei erneut hoch.', 'ps-pen-paper' ) );
		}
	}
	wp_safe_redirect( add_query_arg( 'pspp_import_preview', $token, ps_pen_paper_dashboard_url( 'settings', $page_id ) ) . '#pspp-import' ); exit;
}

function ps_pen_paper_render_import_settings( $page_id = 0 ) {
	if ( ! ps_pen_paper_can_preview_import() ) { return; }
	?>
	<section id="pspp-import" class="pspp-settings-section">
		<h2><?php esc_html_e( 'Sicherung prüfen und Wiederherstellung vorbereiten', 'ps-pen-paper' ); ?></h2>
		<p><?php esc_html_e( 'Du wählst pro Vorgang eine neue Kampagne oder den Ersatz einer bestehenden sowie ein getrenntes Universum oder die Zuordnung zu bestehenden Daten. Die Vorschau prüft Datei und Zuordnungen, verändert aber noch keine Spielstände. Erst deine ausdrückliche Bestätigung startet die Wiederherstellung; bei einem Ersatz wird der bisherige Stand privat gesichert.', 'ps-pen-paper' ); ?></p>
		<?php ps_pen_paper_form_start( 'preview_import', 'pspp_import_upload', 0, $page_id, true ); ?>
			<p><label for="pspp-import-file"><?php esc_html_e( 'Private JSON-Kampagnensicherung', 'ps-pen-paper' ); ?></label>
				<input id="pspp-import-file" type="file" name="import_file" accept=".json,application/json" required aria-describedby="pspp-import-limit">
			</p>
			<p id="pspp-import-limit"><?php
				/* translators: %s: readable upload/memory size limit. */
				echo esc_html( sprintf( __( 'Aktuelle Hostgrenze: %s. Die private Vorschau bleibt höchstens eine Stunde verfügbar und ist nur deinem Konto auf dieser Website zugänglich.', 'ps-pen-paper' ), size_format( ps_pen_paper_import_limit() ) ) );
			?></p>
			<?php submit_button( __( 'Datei prüfen und Vorschau öffnen', 'ps-pen-paper' ) ); ?>
		</form>
		<?php
		if ( isset( $_GET['pspp_import_preview'] ) ) {
			$token = is_string( $_GET['pspp_import_preview'] ) ? wp_unslash( $_GET['pspp_import_preview'] ) : '';
			ps_pen_paper_render_import_preview( $token, $page_id );
		}
		?>
	</section>
	<?php
}

function ps_pen_paper_render_import_preview( $token, $page_id = 0 ) {
	$receipt = ps_pen_paper_restore_receipt( $token );
	if ( is_wp_error( $receipt ) ) { echo '<p role="alert">' . esc_html( $receipt->get_error_message() ) . '</p>'; return; }
	if ( $receipt ) {
		?><h3><?php esc_html_e( 'Wiederherstellung abgeschlossen', 'ps-pen-paper' ); ?></h3>
		<p><?php esc_html_e( 'Dieser Vorgang wurde vollständig gespeichert und wird nicht erneut ausgeführt. Die Kampagne kann danach regulär geändert worden sein.', 'ps-pen-paper' ); ?></p>
		<p><a href="<?php echo esc_url( add_query_arg( $page_id ? 'pspp_campaign' : 'campaign_id', $receipt['campaign_id'], ps_pen_paper_dashboard_url( 'campaigns', $page_id ) ) ); ?>"><?php esc_html_e( 'Wiederhergestellte Kampagne öffnen', 'ps-pen-paper' ); ?></a></p>
		<?php if ( $receipt['backup_id'] ) : ?>
			<?php ps_pen_paper_form_start( 'download_import_backup', 'pspp_restore_backup_' . $receipt['backup_id'], 0, $page_id, false, true ); ?>
				<input type="hidden" name="backup_id" value="<?php echo esc_attr( $receipt['backup_id'] ); ?>">
				<?php submit_button( __( 'Private Sicherung vor dem Ersatz herunterladen', 'ps-pen-paper' ), 'secondary' ); ?>
			</form>
		<?php endif; ?>
		<?php return;
	}
	$preview = ps_pen_paper_get_import_preview( $token );
	if ( is_wp_error( $preview ) ) { echo '<p role="alert">' . esc_html( $preview->get_error_message() ) . '</p>'; return; }
	$parsed = ps_pen_paper_parse_import( $preview['json'] );
	if ( is_wp_error( $parsed ) ) { echo '<p role="alert">' . esc_html( $parsed->get_error_message() ) . '</p>'; return; }
	$options = $preview['options'];
	$plan = ps_pen_paper_import_configuration( $parsed, $options );
	if ( is_wp_error( $plan ) ) { echo '<p role="alert">' . esc_html( $plan->get_error_message() ) . '</p>'; return; }
	$campaign = $parsed['records'][ $parsed['archive']['payload']['source']['campaign_id'] ];
	$counts = array();
	foreach ( $parsed['records'] as $record ) {
		if ( ! isset( $counts[ $record['type'] ] ) ) { $counts[ $record['type'] ] = 0; }
		$counts[ $record['type'] ]++;
	}
	$labels = array( 'pspp_campaign' => __( 'Kampagnen', 'ps-pen-paper' ), 'pspp_universe' => __( 'Universen', 'ps-pen-paper' ),
		'pspp_character' => __( 'Charaktere und Basisbögen', 'ps-pen-paper' ), 'pspp_asset' => __( 'Geschützte Bilder', 'ps-pen-paper' ),
		'pspp_scene' => __( 'Szenen', 'ps-pen-paper' ), 'pspp_folio' => __( 'Mappeneinträge und Handouts', 'ps-pen-paper' ),
		'pspp_npc' => __( 'Gegner und NPCs', 'ps-pen-paper' ), 'pspp_item' => __( 'Gegenstandsvorlagen', 'ps-pen-paper' ),
		'pspp_wiki_entry' => __( 'Wiki-Stände', 'ps-pen-paper' ), 'pspp_roll' => __( 'Würfe', 'ps-pen-paper' ),
		'pspp_event' => __( 'Ereignisse', 'ps-pen-paper' ), 'pspp_trade' => __( 'Handelsstände', 'ps-pen-paper' ),
		'pspp_loot_table' => __( 'Beutetabellen', 'ps-pen-paper' ), 'pspp_loot_run' => __( 'Beutevorgänge', 'ps-pen-paper' ),
		'pspp_adventure' => __( 'Soloabenteuer', 'ps-pen-paper' ), 'pspp_adventure_run' => __( 'Abenteuerdurchläufe', 'ps-pen-paper' ) );
	$site_users = get_users( array( 'blog_id' => get_current_blog_id(), 'orderby' => 'display_name', 'order' => 'ASC' ) );
	?>
	<h3><?php echo esc_html( $campaign['title'] ); ?></h3>
	<p><?php esc_html_e( 'Format, Prüfsumme, Datentypen, Bilddaten und Kampagnenabgrenzung wurden geprüft. Prüfe die Kontenzuordnungen und bestätige anschließend die Wiederherstellung.', 'ps-pen-paper' ); ?></p>
	<dl>
		<dt><?php esc_html_e( 'Quellwebsite', 'ps-pen-paper' ); ?></dt><dd><?php echo esc_html( $parsed['archive']['payload']['source']['site_url'] ); ?></dd>
		<dt><?php esc_html_e( 'Exportversion', 'ps-pen-paper' ); ?></dt><dd><?php echo esc_html( $parsed['archive']['plugin_version'] ); ?></dd>
		<dt><?php esc_html_e( 'Dateiprüfsumme', 'ps-pen-paper' ); ?></dt><dd><code><?php echo esc_html( $parsed['archive']['payload_sha256'] ); ?></code></dd>
		<?php foreach ( $counts as $type => $count ) : ?><dt><?php echo esc_html( $labels[ $type ] ); ?></dt><dd><?php echo esc_html( $count ); ?></dd><?php endforeach; ?>
	</dl>
	<?php ps_pen_paper_form_start( 'configure_import_preview', 'pspp_import_preview_' . $token, 0, $page_id ); ?>
		<input type="hidden" name="import_token" value="<?php echo esc_attr( $token ); ?>">
		<p><label for="pspp-import-operation"><?php esc_html_e( 'Ziel des Vorgangs', 'ps-pen-paper' ); ?></label>
			<select id="pspp-import-operation" name="import[operation]">
				<option value=""><?php esc_html_e( 'Bitte auswählen', 'ps-pen-paper' ); ?></option>
				<option value="copy" <?php selected( $options['operation'], 'copy' ); ?> <?php disabled( ! ps_pen_paper_can_create_campaign() ); ?>><?php esc_html_e( 'Neue Kampagne anlegen', 'ps-pen-paper' ); ?></option>
				<option value="replace" <?php selected( $options['operation'], 'replace' ); ?>><?php esc_html_e( 'Bestehende Kampagne ersetzen', 'ps-pen-paper' ); ?></option>
			</select>
		</p>
		<?php if ( ! ps_pen_paper_can_create_campaign() ) : ?><p><?php esc_html_e( 'Neue Kampagnen und Universen benötigen die Erstellungsberechtigung. Verwaltbare bestehende Kampagnen bleiben als Ziel auswählbar.', 'ps-pen-paper' ); ?></p><?php endif; ?>
		<p><label for="pspp-import-target"><?php esc_html_e( 'Bestehende Zielkampagne – nur bei Ersatz', 'ps-pen-paper' ); ?></label>
			<select id="pspp-import-target" name="import[target_id]">
				<option value="0"><?php esc_html_e( 'Keine bestehende Kampagne', 'ps-pen-paper' ); ?></option>
				<?php foreach ( ps_pen_paper_get_campaigns() as $target ) : ?>
					<?php if ( ! ps_pen_paper_can_manage_campaign( $target ) ) { continue; } ?>
					<option value="<?php echo esc_attr( $target->ID ); ?>" <?php selected( $options['target_id'], $target->ID ); ?>><?php echo esc_html( $target->post_title . ' · #' . $target->ID ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p><label for="pspp-import-universe-mode"><?php esc_html_e( 'Universumsdaten', 'ps-pen-paper' ); ?></label>
			<select id="pspp-import-universe-mode" name="import[universe_mode]">
				<option value=""><?php esc_html_e( 'Bitte auswählen', 'ps-pen-paper' ); ?></option>
				<option value="copy" <?php selected( $options['universe_mode'], 'copy' ); ?>><?php esc_html_e( 'Enthaltenes Universum getrennt wiederherstellen', 'ps-pen-paper' ); ?></option>
				<option value="bind" <?php selected( $options['universe_mode'], 'bind' ); ?>><?php esc_html_e( 'Bestehendes Universum und Basisbögen zuordnen', 'ps-pen-paper' ); ?></option>
			</select>
		</p>
		<p><label for="pspp-import-universe"><?php esc_html_e( 'Bestehendes Universum – nur bei Zuordnung', 'ps-pen-paper' ); ?></label>
			<select id="pspp-import-universe" name="import[universe_id]">
				<option value="0"><?php esc_html_e( 'Kein bestehendes Universum', 'ps-pen-paper' ); ?></option>
				<?php foreach ( ps_pen_paper_get_universes() as $universe ) : ?>
					<?php $document = ps_pen_paper_universe_document( $universe->ID ); ?>
					<?php if ( is_wp_error( $document ) ) { echo '<option disabled>' . esc_html( $document->get_error_message() ) . '</option>'; continue; } ?>
					<option value="<?php echo esc_attr( $universe->ID ); ?>" <?php selected( $options['universe_id'], $universe->ID ); ?>><?php echo esc_html( $document['name'] . ' · #' . $universe->ID ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<h4><?php esc_html_e( 'Benutzer ausdrücklich zuordnen', 'ps-pen-paper' ); ?></h4>
		<input type="hidden" name="import[users]" value="">
		<?php foreach ( $parsed['users'] as $id => $requirement ) : ?>
			<p><label for="pspp-import-user-<?php echo esc_attr( $id ); ?>"><?php
				/* translators: %d: original user ID in the backup. */
				echo esc_html( sprintf( __( 'Quellbenutzer #%d', 'ps-pen-paper' ), $id ) );
			?></label>
				<select id="pspp-import-user-<?php echo esc_attr( $id ); ?>" name="import[users][<?php echo esc_attr( $id ); ?>]">
					<option value="0"><?php esc_html_e( 'Noch nicht zugeordnet', 'ps-pen-paper' ); ?></option>
					<?php if ( ! $requirement['required'] ) : ?><option value="-1" <?php selected( isset( $options['users'][ $id ] ) ? $options['users'][ $id ] : 0, -1 ); ?>><?php esc_html_e( 'Ohne Konto übernehmen / keine aktive Mitgliedschaft', 'ps-pen-paper' ); ?></option><?php endif; ?>
					<?php foreach ( $site_users as $user ) : ?>
						<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( isset( $options['users'][ $id ] ) ? $options['users'][ $id ] : 0, $user->ID ); ?>><?php echo esc_html( $user->display_name . ' · #' . $user->ID ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php endforeach; ?>
		<input type="hidden" name="import[bases]" value="">
		<?php if ( $parsed['bases'] ) : ?><h4><?php esc_html_e( 'Basisbögen – nur bei bestehender Zuordnung', 'ps-pen-paper' ); ?></h4><?php endif; ?>
		<?php foreach ( $parsed['bases'] as $id => $base ) : ?>
			<p><label for="pspp-import-base-<?php echo esc_attr( $id ); ?>"><?php
				/* translators: %d: original base-character ID in the backup. */
				echo esc_html( sprintf( __( 'Quellbasisbogen #%d', 'ps-pen-paper' ), $id ) );
			?></label>
				<select id="pspp-import-base-<?php echo esc_attr( $id ); ?>" name="import[bases][<?php echo esc_attr( $id ); ?>]">
					<option value="0"><?php esc_html_e( 'Keine bestehende Zuordnung', 'ps-pen-paper' ); ?></option>
					<?php $universe = ps_pen_paper_get_universe( $options['universe_id'] ); ?>
					<?php if ( $universe && ps_pen_paper_can_manage_universe( $universe ) ) : ?>
						<?php foreach ( ps_pen_paper_get_characters( $universe ) as $character ) : ?>
							<?php $state = ps_pen_paper_raw_character_state( $character->ID ); ?>
							<?php if ( is_wp_error( $state ) ) { echo '<option disabled>' . esc_html( $state->get_error_message() ) . '</option>'; continue; } ?>
							<?php if ( ! isset( $state['active']['name'] ) || ! is_string( $state['active']['name'] ) ) { continue; } ?>
							<option value="<?php echo esc_attr( $character->ID ); ?>" <?php selected( isset( $options['bases'][ $id ] ) ? $options['bases'][ $id ] : 0, $character->ID ); ?>><?php echo esc_html( $state['active']['name'] . ' · #' . $character->ID . ' · ' . $character->post_author ); ?></option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</p>
		<?php endforeach; ?>
		<?php submit_button( __( 'Zuordnungen und Konflikte prüfen', 'ps-pen-paper' ) ); ?>
	</form>
	<h4><?php esc_html_e( 'Fachdokumentprüfung', 'ps-pen-paper' ); ?></h4>
	<p><?php
		/* translators: %d: number of checked metadata documents. */
		echo esc_html( sprintf( __( '%d Fachdokumente wurden mit den vorhandenen Regel-, Charakter-, Story- oder Wirtschaftsvalidatoren geprüft. Es wurden keine Werte verändert und keine Vergaben ausgeführt.', 'ps-pen-paper' ), $plan['documents']['checked'] ) );
	?></p>
	<?php if ( $plan['conflicts'] ) : ?>
		<h4><?php esc_html_e( 'Offene Konflikte', 'ps-pen-paper' ); ?></h4>
		<ul role="alert"><?php foreach ( $plan['conflicts'] as $message ) : ?><li><?php echo esc_html( $message ); ?></li><?php endforeach; ?></ul>
	<?php endif; ?>
	<ul><?php foreach ( $plan['warnings'] as $message ) : ?><li><?php echo esc_html( $message ); ?></li><?php endforeach; ?></ul>
	<?php if ( $plan['target_hash'] ) : ?>
		<p><?php esc_html_e( 'Zielstand bei dieser Prüfung:', 'ps-pen-paper' ); ?> <code><?php echo esc_html( $plan['target_hash'] ); ?></code></p>
		<?php ps_pen_paper_form_start( 'export_campaign', 'pspp_export_' . $options['target_id'], $options['target_id'], $page_id, false, true ); ?>
			<input type="hidden" name="export_mode" value="local">
			<p><label><input type="checkbox" name="export_confirmation" value="confirmed" required> <?php esc_html_e( 'Die Zielsicherung enthält private Inhalte; ich bewahre sie geschützt auf.', 'ps-pen-paper' ); ?></label></p>
			<?php submit_button( __( 'Aktuelle Zielkampagne vor dem Ersatz sichern', 'ps-pen-paper' ) ); ?>
		</form>
	<?php endif; ?>
	<?php if ( ! $plan['conflicts'] && function_exists( 'ps_pen_paper_prepare_import' ) ) :
		$map = ps_pen_paper_import_identity_map( $parsed, $options );
		$check = ps_pen_paper_prepare_import( $parsed, $options, $map, false );
		$archive_required = is_wp_error( $check );
		$archived = $archive_required ? ps_pen_paper_prepare_import( $parsed, $options, $map, true ) : $check;
		if ( is_wp_error( $check ) ) : ?><p role="alert"><?php echo esc_html( $check->get_error_message() ); ?></p><?php endif;
		if ( ! is_wp_error( $archived ) ) :
			foreach ( $archived[ $parsed['archive']['payload']['source']['campaign_id'] ]['meta'] as $entry ) {
				if ( '_pspp_import_archive' === $entry['key'] && isset( $entry['value']['warnings'] ) ) {
					foreach ( $entry['value']['warnings'] as $warning ) { echo '<p>' . esc_html( $warning ) . '</p>'; }
				}
			}
			$fingerprint = ps_pen_paper_restore_fingerprint( $parsed, $options, $plan );
			ps_pen_paper_form_start( 'restore_import', 'pspp_restore_' . $token . '_' . $fingerprint, 0, $page_id );
			?>
			<input type="hidden" name="import_token" value="<?php echo esc_attr( $token ); ?>">
			<input type="hidden" name="restore_fingerprint" value="<?php echo esc_attr( $fingerprint ); ?>">
			<p><?php esc_html_e( 'Dokumente, Protokolle und typisierte Referenzen wurden vollständig aufbereitet. Spielstände und Vergaben werden übernommen, nicht noch einmal ausgeführt. Konten, globale Rollen und bestehende Universumsdaten werden nicht überschrieben; externe Wiki-Verknüpfungen bleiben deaktiviert.', 'ps-pen-paper' ); ?></p>
			<p><label><?php esc_html_e( 'Zugang nach der Wiederherstellung', 'ps-pen-paper' ); ?>
				<select name="restore_access"><option value="private"><?php esc_html_e( 'Privat und für neue Aufnahmeanfragen geschlossen', 'ps-pen-paper' ); ?></option>
				<option value="preserve"><?php esc_html_e( 'Zugangseinstellungen aus der Datei ausdrücklich übernehmen', 'ps-pen-paper' ); ?></option></select></label></p>
			<p><label><input type="checkbox" name="archive_unknown" value="1" <?php echo $archive_required ? 'required' : ''; ?>> <?php esc_html_e( 'Unbekannte Plugin-Metadaten nur privat archivieren, nicht als aktive Einstellungen übernehmen.', 'ps-pen-paper' ); ?></label></p>
			<p><label><input type="checkbox" name="restore_confirmation" value="confirmed" required> <?php esc_html_e( 'Ich habe Ziel und Kontenzuordnungen geprüft und bestätige die Übernahme privater Inhalte und Spielstände. Beim Ersatz werden sämtliche kampagneneigenen Spielstände ersetzt und vorher privat gesichert. Der bisherige Ersteller und mein Konto behalten die Spielleitung.', 'ps-pen-paper' ); ?></label></p>
			<?php submit_button( 'replace' === $options['operation'] ? __( 'Zielkampagne gesichert ersetzen', 'ps-pen-paper' ) : __( 'Als neue Kampagne wiederherstellen', 'ps-pen-paper' ) ); ?>
			</form>
		<?php elseif ( is_wp_error( $archived ) && $archived->get_error_message() !== $check->get_error_message() ) : ?>
			<p role="alert"><?php echo esc_html( $archived->get_error_message() ); ?></p>
		<?php endif;
	endif; ?>
	<?php ps_pen_paper_form_start( 'discard_import_preview', 'pspp_import_preview_' . $token, 0, $page_id ); ?>
		<input type="hidden" name="import_token" value="<?php echo esc_attr( $token ); ?>">
		<?php submit_button( __( 'Private Vorschau verwerfen', 'ps-pen-paper' ), 'secondary' ); ?>
	</form>
	<?php
}
