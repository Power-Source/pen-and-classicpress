<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_export_error() {
	return new WP_Error( 'export_failed', __( 'Der Export konnte nicht vollständig erstellt werden. Es wurde keine Sicherungsdatei ausgegeben. Bitte informiere die Administration.', 'ps-pen-paper' ) );
}

function ps_pen_paper_campaign_record_types() {
	return array( 'pspp_character', 'pspp_roll', 'pspp_event', 'pspp_asset', 'pspp_scene', 'pspp_folio',
		'pspp_npc', 'pspp_wiki_entry', 'pspp_item', 'pspp_loot_table', 'pspp_loot_run', 'pspp_trade', 'pspp_adventure', 'pspp_adventure_run', 'pspp_adventure_group' );
}

function ps_pen_paper_export_meta( $record, $key ) {
	$values = array();
	foreach ( $record['meta'] as $entry ) {
		if ( $key === $entry['key'] ) { $values[] = $entry['value']; }
	}
	if ( count( $values ) > 1 ) { return ps_pen_paper_export_error(); }
	if ( ! $values ) { return null; }
	if ( ! is_serialized( $values[0] ) ) { return $values[0]; }
	// Only inspect dependency metadata; the archive preserves the original storage strings.
	$value = @unserialize( $values[0], array( 'allowed_classes' => false ) );
	return false === $value && 'b:0;' !== $values[0] ? ps_pen_paper_export_error() : $value;
}

function ps_pen_paper_export_asset_ids( $value ) {
	$ids = array();
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $part ) {
			if ( 'asset_id' === $key && is_int( $part ) && $part > 0 ) { $ids[] = $part; }
			if ( is_array( $part ) ) { $ids = array_merge( $ids, ps_pen_paper_export_asset_ids( $part ) ); }
		}
	}
	return array_values( array_unique( $ids ) );
}

function ps_pen_paper_export_campaign( $campaign_id, $mode ) {
	global $wpdb;
	if ( ! is_int( $campaign_id ) || $campaign_id < 1 || ! is_string( $mode ) || ! in_array( $mode, array( 'local', 'complete' ), true ) ) {
		return new WP_Error( 'invalid_export', __( 'Bitte wähle eine gültige Kampagne und Exportvariante.', 'ps-pen-paper' ) );
	}
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! ps_pen_paper_can_access_site() || ! $campaign || ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Nur die Kampagnenleitung darf diese Sicherung herunterladen.', 'ps-pen-paper' ) );
	}
	$link = ps_pen_paper_get_universe_link( $campaign_id );
	if ( is_wp_error( $link ) ) { return $link; }
	$universe_id = 'complete' === $mode ? $link['universe_id'] : 0;
	if ( $universe_id && ! ps_pen_paper_can_manage_universe( ps_pen_paper_get_universe( $universe_id ) ) ) {
		return new WP_Error( 'forbidden', __( 'Für den vollständigen Export musst du auch das zugeordnete Universum verwalten dürfen. Alternativ kannst du die lokale Variante mit ausgewiesenen Abhängigkeiten herunterladen.', 'ps-pen-paper' ) );
	}
	$types = ps_pen_paper_campaign_record_types();
	$scopes = array( $campaign_id );
	if ( $universe_id ) { $scopes[] = $universe_id; }
	$placeholders = implode( ',', array_fill( 0, count( $scopes ), '%d' ) );
	// One uncached SELECT keeps posts, metadata and shared dependencies at the same database snapshot.
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT p.ID, p.post_type, p.post_parent, p.post_author, p.post_status, p.post_title,
			p.post_content, p.post_excerpt, p.post_mime_type, p.post_date_gmt, p.post_modified_gmt, p.menu_order,
			m.meta_key, m.meta_value
		FROM $wpdb->posts p
		LEFT JOIN $wpdb->postmeta m ON m.post_id = p.ID AND m.meta_key LIKE %s
		WHERE p.ID IN ($placeholders) OR
			(p.post_parent IN ($placeholders) AND p.post_type IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . '))
		ORDER BY p.ID, m.meta_id',
		array_merge( array( $wpdb->esc_like( '_pspp_' ) . '%' ), $scopes, $scopes, $types )
	), ARRAY_A );
	if ( $wpdb->last_error || ! is_array( $rows ) || ! $rows ) { return ps_pen_paper_export_error(); }
	$records = array();
	foreach ( $rows as $row ) {
		$id = (int) $row['ID'];
		if ( ! isset( $records[ $id ] ) ) {
			$records[ $id ] = array(
				'id' => $id, 'type' => $row['post_type'], 'parent_id' => (int) $row['post_parent'],
				'author_id' => (int) $row['post_author'], 'status' => $row['post_status'], 'title' => $row['post_title'],
				'content' => $row['post_content'], 'excerpt' => $row['post_excerpt'], 'mime_type' => $row['post_mime_type'],
				'date_gmt' => $row['post_date_gmt'], 'modified_gmt' => $row['post_modified_gmt'], 'menu_order' => (int) $row['menu_order'],
				'meta' => array(),
			);
		}
		if ( null !== $row['meta_key'] && 0 === strpos( $row['meta_key'], '_pspp_' ) ) {
			$records[ $id ]['meta'][] = array( 'key' => $row['meta_key'], 'value' => $row['meta_value'] );
		}
	}
	if ( ! isset( $records[ $campaign_id ] ) || 'pspp_campaign' !== $records[ $campaign_id ]['type'] ||
		'private' !== $records[ $campaign_id ]['status'] ) { return ps_pen_paper_export_error(); }
	$root = $records[ $campaign_id ];
	$gm = $root['author_id'] === get_current_user_id() || current_user_can( 'manage_options' );
	foreach ( $root['meta'] as $entry ) {
		if ( '_pspp_gm' === $entry['key'] && (string) get_current_user_id() === $entry['value'] ) { $gm = true; }
	}
	if ( ! $gm ) { return new WP_Error( 'forbidden', __( 'Die Exportberechtigung wurde inzwischen geändert. Bitte lade die Seite neu.', 'ps-pen-paper' ) ); }
	$snapshot_link = ps_pen_paper_export_meta( $root, '_pspp_universe_link' );
	if ( is_wp_error( $snapshot_link ) ) { return $snapshot_link; }
	if ( ( null === $snapshot_link && 0 !== $link['revision'] ) || ( null !== $snapshot_link && $snapshot_link !== $link ) ) {
		return new WP_Error( 'export_conflict', __( 'Die Universumszuordnung wurde inzwischen geändert. Bitte lade die Seite neu und starte den Export erneut.', 'ps-pen-paper' ) );
	}
	if ( $universe_id && ( ! isset( $records[ $universe_id ] ) || 'pspp_universe' !== $records[ $universe_id ]['type'] ||
		'private' !== $records[ $universe_id ]['status'] ||
		( ! current_user_can( 'manage_options' ) && $records[ $universe_id ]['author_id'] !== get_current_user_id() ) ) ) {
		return new WP_Error( 'forbidden', __( 'Die Universumsberechtigung wurde inzwischen geändert. Bitte lade die Seite neu.', 'ps-pen-paper' ) );
	}
	$selected = array( $campaign_id => true );
	$dependencies = array();
	if ( $link['universe_id'] ) {
		$dependencies[] = array( 'kind' => 'universe', 'id' => $link['universe_id'], 'included' => (bool) $universe_id );
	}
	if ( $universe_id ) { $selected[ $universe_id ] = true; }
	foreach ( $records as $id => $record ) {
		if ( $record['parent_id'] !== $campaign_id ) { continue; }
		$selected[ $id ] = true;
		if ( 'pspp_character' !== $record['type'] ) { continue; }
		$state = ps_pen_paper_export_meta( $record, '_pspp_character' );
		if ( is_wp_error( $state ) ) { return $state; }
		if ( ! is_array( $state ) || ! array_key_exists( 'context', $state ) ) { continue; }
		$context = $state['context'];
		if ( ! is_array( $context ) || ! isset( $context['mode'], $context['universe_id'], $context['source_id'] ) ||
			! in_array( $context['mode'], array( 'shared', 'copy' ), true ) || ! is_int( $context['universe_id'] ) ||
			! is_int( $context['source_id'] ) || $context['universe_id'] < 1 || $context['source_id'] < 1 ) {
			return ps_pen_paper_export_error();
		}
		if ( 'copy' === $context['mode'] ) { continue; }
		$source_id = $context['source_id'];
		$dependencies[] = array( 'kind' => 'shared_character', 'id' => $source_id,
			'universe_id' => $context['universe_id'], 'included' => (bool) $universe_id );
		if ( 'local' === $mode ) { continue; }
		if ( ! $universe_id || $context['universe_id'] !== $universe_id || ! isset( $records[ $source_id ] ) ||
			'pspp_character' !== $records[ $source_id ]['type'] || 'private' !== $records[ $source_id ]['status'] ||
			$records[ $source_id ]['parent_id'] !== $universe_id || $records[ $source_id ]['author_id'] !== $record['author_id'] ) {
			return new WP_Error( 'export_dependency', __( 'Ein benötigter Basisbogen ist nicht mehr eindeutig dem Universum und seinem Besitzer zugeordnet. Bitte kläre die Verbindung vor einem vollständigen Export. Die lokale Variante bleibt zur Sicherung der Kampagnendaten verfügbar.', 'ps-pen-paper' ) );
		}
		$selected[ $source_id ] = true;
	}
	if ( $universe_id ) {
		foreach ( $records as $id => $record ) {
			if ( $record['parent_id'] === $universe_id && in_array( $record['type'], array( 'pspp_item', 'pspp_npc', 'pspp_wiki_entry' ), true ) ) {
				$selected[ $id ] = true;
			}
		}
		foreach ( $records as $id => $record ) {
			if ( $record['parent_id'] !== $universe_id || 'pspp_event' !== $record['type'] ) { continue; }
			$event = ps_pen_paper_export_meta( $record, '_pspp_event' );
			if ( is_wp_error( $event ) ) { return $event; }
			if ( ! is_array( $event ) || ! isset( $event['object_id'] ) || ! is_int( $event['object_id'] ) ) { return ps_pen_paper_export_error(); }
			if ( isset( $selected[ $event['object_id'] ] ) ) {
				$selected[ $id ] = true;
			}
		}
	}
	foreach ( array_keys( $selected ) as $id ) {
		$asset_ids = array();
		foreach ( $records[ $id ]['meta'] as $entry ) {
			$value = ps_pen_paper_export_meta( array( 'meta' => array( $entry ) ), $entry['key'] );
			if ( is_wp_error( $value ) ) { return $value; }
			$asset_ids = array_merge( $asset_ids, ps_pen_paper_export_asset_ids( $value ) );
		}
		foreach ( array_unique( $asset_ids ) as $asset_id ) {
			$available = isset( $records[ $asset_id ] ) && 'pspp_asset' === $records[ $asset_id ]['type'] &&
				in_array( $records[ $asset_id ]['parent_id'], $scopes, true );
			$historical = 'pspp_event' === $records[ $id ]['type'];
			if ( 'complete' === $mode && ! $available && ! $historical ) {
				return new WP_Error( 'export_dependency', __( 'Für den vollständigen Export fehlt ein benötigtes geschütztes Bild. Bitte prüfe die Bildzuordnung. Die lokale Variante weist fehlende Bilddaten ausdrücklich als Abhängigkeiten aus.', 'ps-pen-paper' ) );
			}
			if ( $available ) { $selected[ $asset_id ] = true; }
			$dependencies[] = array( 'kind' => 'asset', 'id' => $asset_id, 'referenced_by' => $id,
				'historical' => $historical, 'included' => $available );
		}
	}
	$epoch = ps_pen_paper_export_meta( $root, '_pspp_import_epoch' );
	if ( is_wp_error( $epoch ) ) { return $epoch; }
	$chat_id = ps_pen_paper_chat_id( $campaign_id, null === $epoch ? '' : $epoch );
	if ( is_wp_error( $chat_id ) ) { return $chat_id; }
	$payload = array(
		'source' => array( 'site_url' => home_url( '/' ), 'blog_id' => get_current_blog_id(), 'campaign_id' => $campaign_id ),
		'mode' => $mode, 'meta_encoding' => 'wordpress-raw',
		'records' => array_values( array_intersect_key( $records, $selected ) ), 'dependencies' => $dependencies,
		'external_data' => array( 'user_accounts' => 'not_included', 'user_assignment' => 'required_on_restore',
			'ps_community' => 'not_included', 'ps_chat' => array( 'included' => false, 'session_id' => $chat_id ),
			'ps_wiki' => 'references_and_pspp_snapshots_only' ),
	);
	$encoded = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( false === $encoded ) { return ps_pen_paper_export_error(); }
	$version = get_file_data( dirname( __DIR__ ) . '/ps-pen-paper.php', array( 'version' => 'Version' ) );
	return array( 'format' => 'ps-pen-paper-campaign', 'format_version' => 1, 'plugin_version' => $version['version'],
		'created_at' => gmdate( 'c' ), 'payload' => $payload, 'payload_sha256' => hash( 'sha256', $encoded ) );
}

function ps_pen_paper_handle_export_action() {
	$campaign_id = ps_pen_paper_posted_id( 'campaign_id' );
	check_admin_referer( 'pspp_export_' . $campaign_id );
	if ( 'confirmed' !== ps_pen_paper_posted_text( 'export_confirmation' ) ) {
		ps_pen_paper_fail( __( 'Bitte bestätige, dass die Sicherungsdatei private Inhalte enthält und geschützt aufbewahrt werden muss.', 'ps-pen-paper' ) );
	}
	$result = ps_pen_paper_export_campaign( $campaign_id, ps_pen_paper_posted_text( 'export_mode' ) );
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	$json = json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( false === $json || headers_sent() ) { ps_pen_paper_fail( ps_pen_paper_export_error()->get_error_message() ); }
	nocache_headers();
	header( 'Cache-Control: no-store, private, max-age=0, must-revalidate' );
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="pspp-campaign-' . $campaign_id . '-' . $result['payload']['mode'] . '-' . gmdate( 'Ymd-His' ) . '.json"' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	echo $json;
	exit;
}

function ps_pen_paper_render_export_settings( $campaign, $page_id = 0 ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return; }
	$link = ps_pen_paper_get_universe_link( $campaign->ID );
	if ( is_wp_error( $link ) ) { echo '<p role="alert">' . esc_html( $link->get_error_message() ) . '</p>'; return; }
	$complete = ! $link['universe_id'] || ps_pen_paper_can_manage_universe( ps_pen_paper_get_universe( $link['universe_id'] ) );
	?>
	<section class="pspp-settings-section">
		<h3><?php esc_html_e( 'Kampagne sichern', 'ps-pen-paper' ); ?></h3>
		<p id="pspp-export-help"><?php esc_html_e( 'Die versionierte JSON-Datei sichert Kampagnendaten einschließlich Entwürfen, Geheimnissen, Inventaren, Guthaben, Protokollen, Abenteuerdurchläufen und geschützten Bildern. Import und Wiederherstellung stehen nach privater Vorschau auf der Übersicht des Spielbereichs zur Verfügung. Die Datei ersetzt kein vollständiges Website-Backup.', 'ps-pen-paper' ); ?></p>
		<p id="pspp-export-dependencies"><?php esc_html_e( 'Lokal: Gemeinsame Universumsdaten werden nur als Abhängigkeiten ausgewiesen. Vollständig: Zusätzlich werden Universumsdefinitionen, gemeinsame Gegenstands-/NPC-/Wiki-Kataloge, zugeordnete Basisbögen und deren benötigte Bilder und Protokolle gesichert. Andere Kampagnen und unbeteiligte Basisbögen bleiben ausgeschlossen.', 'ps-pen-paper' ); ?></p>
		<p><?php esc_html_e( 'Fehlende benötigte Bilder verhindern den vollständigen Export. Bereits gelöschte Bilder aus alten Protokollen bleiben ausdrücklich als nicht enthaltene historische Abhängigkeiten ausgewiesen.', 'ps-pen-paper' ); ?></p>
		<p><?php esc_html_e( 'Benutzerkonten, Community-Profile, Chatnachrichten und externe Wiki-Artikel werden nicht übernommen. Ihre Verknüpfungen beziehungsweise die plugininternen Wiki-Stände bleiben erhalten und benötigen bei einer späteren Wiederherstellung eine kontrollierte Zuordnung.', 'ps-pen-paper' ); ?></p>
		<p><?php esc_html_e( 'Die Anfrage öffnet sich in einer neuen Registerkarte, damit ungespeicherte Eingaben auf dieser Seite erhalten bleiben.', 'ps-pen-paper' ); ?></p>
		<?php ps_pen_paper_form_start( 'export_campaign', 'pspp_export_' . $campaign->ID, $campaign->ID, $page_id, false, true ); ?>
			<p><label for="pspp-export-mode"><?php esc_html_e( 'Exportvariante', 'ps-pen-paper' ); ?></label>
				<select id="pspp-export-mode" name="export_mode" aria-describedby="pspp-export-help pspp-export-dependencies<?php echo $complete ? '' : ' pspp-export-permission'; ?>">
					<option value="local"><?php esc_html_e( 'Lokale Kampagnendaten mit ausgewiesenen Abhängigkeiten', 'ps-pen-paper' ); ?></option>
					<option value="complete" <?php disabled( ! $complete ); ?>><?php esc_html_e( 'Vollständig einschließlich benötigter Universumsdaten', 'ps-pen-paper' ); ?></option>
				</select>
			</p>
			<?php if ( ! $complete ) : ?><p id="pspp-export-permission"><?php esc_html_e( 'Die vollständige Variante benötigt zusätzlich Verwaltungsrechte für das zugeordnete Universum.', 'ps-pen-paper' ); ?></p><?php endif; ?>
			<p><label><input type="checkbox" name="export_confirmation" value="confirmed" required> <?php esc_html_e( 'Die Datei enthält private Inhalte. Ich bewahre sie geschützt auf und veröffentliche sie nicht.', 'ps-pen-paper' ); ?></label></p>
			<?php submit_button( __( 'JSON-Sicherung herunterladen', 'ps-pen-paper' ) ); ?>
		</form>
	</section>
	<?php
}
