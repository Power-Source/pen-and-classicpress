<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_handle_scene_action( $operation ) {
	$campaign_id = ps_pen_paper_posted_id( 'campaign_id' );
	$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	check_admin_referer( 'pspp_campaign_' . $campaign_id );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! $campaign || ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ||
		( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) ) { ps_pen_paper_fail( __( 'Diese Szenenverwaltung ist für dich nicht verfügbar.', 'ps-pen-paper' ) ); }
	$id = ps_pen_paper_posted_id( 'scene_id' );
	$revision = ps_pen_paper_posted_text( 'scene_revision' );
	if ( 'save_scene' === $operation ) {
		if ( 'complete' !== ps_pen_paper_posted_text( 'scene_complete' ) ) { ps_pen_paper_fail( __( 'Das Szenenformular wurde nicht vollständig übertragen.', 'ps-pen-paper' ) ); }
		$path = null;
		$file = isset( $_FILES['scene_image'] ) ? $_FILES['scene_image'] : null;
		if ( null !== $file && ( ! is_array( $file ) || ! isset( $file['error'] ) ) ) { ps_pen_paper_fail( __( 'Das Kartenbild wurde nicht vollständig hochgeladen.', 'ps-pen-paper' ) ); }
		if ( is_array( $file ) && isset( $file['error'] ) && UPLOAD_ERR_NO_FILE !== $file['error'] ) {
			if ( UPLOAD_ERR_OK !== $file['error'] || ! isset( $file['tmp_name'] ) || ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
				ps_pen_paper_fail( __( 'Das Kartenbild wurde nicht vollständig hochgeladen.', 'ps-pen-paper' ) );
			}
			$path = $file['tmp_name'];
		}
		$result = ps_pen_paper_save_scene( $campaign, $id, isset( $_POST['scene'] ) ? wp_unslash( $_POST['scene'] ) : null, $revision, $path );
		if ( ! is_wp_error( $result ) ) { $id = $result; }
	} elseif ( 'select_scene' === $operation ) {
		$result = ps_pen_paper_select_scene( $campaign, $id, $revision );
	} else {
		$result = ps_pen_paper_publish_scene( $campaign, $id, $revision, ps_pen_paper_posted_text( 'scene_confirmation' ), 'withdraw_scene' === $operation );
	}
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	$url = $page_id ? ps_pen_paper_game_url( $page_id, $campaign_id ) : ps_pen_paper_admin_url( $campaign_id );
	$args = array( 'pspp_notice' => 'scene_saved' );
	if ( $id && 'select_scene' !== $operation ) { $args['pspp_scene'] = $id; }
	wp_safe_redirect( add_query_arg( $args, $url ) . '#pspp-story' );
	exit;
}

function ps_pen_paper_render_map( $campaign, $state, $preview = false ) {
	if ( is_wp_error( $state ) ) {
		echo '<p role="alert">' . esc_html( $state->get_error_message() ) . '</p>';
		$state = array( 'scene' => null, 'ping' => null );
	}
	$scene = $state['scene'];
	$canvas = $scene && ( $scene['asset_id'] || $scene['grid'] || $scene['tokens'] );
	$image = $scene && $scene['asset_id'] ? ps_pen_paper_private_image( $campaign, $scene['asset_id'] ) : '';
	if ( is_wp_error( $image ) ) { echo '<p role="alert">' . esc_html( $image->get_error_message() ) . '</p>'; $image = ''; }
	$labels = array(
		'error' => __( 'Die Karte konnte nicht aktualisiert werden. Bitte versuche es erneut.', 'ps-pen-paper' ),
		'selected' => __( 'Gewählte Figur:', 'ps-pen-paper' ), 'moved' => __( 'Figurenposition gespeichert.', 'ps-pen-paper' ),
		'ping' => __( 'Kartenhinweis geteilt.', 'ps-pen-paper' ), 'image' => __( 'Das Kartenbild konnte nicht geladen werden.', 'ps-pen-paper' ),
		'none' => __( 'Keine eigene Figur verfügbar', 'ps-pen-paper' ),
		'choose' => __( 'Figur auswählen', 'ps-pen-paper' ),
		'wait' => __( 'Die Karte wird gerade aktualisiert. Bitte versuche die Bewegung anschließend erneut.', 'ps-pen-paper' ),
		'ping_from' => __( 'Kartenhinweis von', 'ps-pen-paper' ),
	);
	?>
	<div class="pspp-map" data-map data-map-endpoint="<?php echo esc_url( rest_url( 'ps-pen-paper/v1/campaigns/' . $campaign->ID . '/map' ) ); ?>"
		data-map-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" data-map-preview="<?php echo $preview ? 'true' : 'false'; ?>"
		data-map-state="<?php echo esc_attr( wp_json_encode( $state ) ); ?>" data-map-labels="<?php echo esc_attr( wp_json_encode( $labels ) ); ?>"<?php if ( ! $scene ) { echo ' hidden'; } ?>>
		<header class="pspp-map-heading"><span class="pspp-eyebrow"><?php echo esc_html( $preview ? __( 'Private Spielervorschau – gespeicherter Entwurf', 'ps-pen-paper' ) : __( 'Aktuelle Szene', 'ps-pen-paper' ) ); ?></span>
			<h2 data-map-title><?php echo $scene ? esc_html( $scene['title'] ) : ''; ?></h2>
			<p class="pspp-multiline" data-map-text><?php echo $scene ? esc_html( $scene['text'] ) : ''; ?></p>
		</header>
		<div class="pspp-map-tools" data-map-tools hidden>
			<button type="button" data-map-zoom="in" aria-label="<?php esc_attr_e( 'Karte vergrößern', 'ps-pen-paper' ); ?>">+</button>
			<button type="button" data-map-zoom="out" aria-label="<?php esc_attr_e( 'Karte verkleinern', 'ps-pen-paper' ); ?>">−</button>
			<button type="button" data-map-zoom="fit"><?php esc_html_e( 'Einpassen', 'ps-pen-paper' ); ?></button>
			<?php if ( ! $preview ) : ?><button type="button" data-map-ping aria-pressed="false"><?php esc_html_e( 'Ping setzen', 'ps-pen-paper' ); ?></button><?php endif; ?>
			<span data-map-selection></span>
		</div>
		<div class="pspp-map-viewport"<?php if ( ! $canvas ) { echo ' hidden'; } ?>>
			<svg data-map-svg role="group" aria-label="<?php esc_attr_e( 'Szenenkarte mit Figurenmarkern', 'ps-pen-paper' ); ?>" viewBox="0 0 <?php echo esc_attr( $scene ? $scene['width'] . ' ' . $scene['height'] : '1000 700' ); ?>" tabindex="0">
				<image data-map-image width="<?php echo esc_attr( $scene ? $scene['width'] : 1000 ); ?>" height="<?php echo esc_attr( $scene ? $scene['height'] : 700 ); ?>" href="<?php echo esc_attr( $image ); ?>"></image>
				<g data-map-grid aria-hidden="true"></g>
				<g data-map-tokens></g>
				<g data-map-pings aria-hidden="true"></g>
			</svg>
		</div>
		<?php if ( ! $preview ) : ?>
			<details class="pspp-map-movement"<?php if ( ! $canvas ) { echo ' hidden'; } ?>><summary><?php esc_html_e( 'Figur ohne Ziehen bewegen', 'ps-pen-paper' ); ?></summary>
				<form data-map-move>
					<label><?php esc_html_e( 'Eigene Figur', 'ps-pen-paper' ); ?><select name="token"></select></label>
					<label><?php esc_html_e( 'X (%)', 'ps-pen-paper' ); ?><input name="x" type="number" min="0" max="100" step="0.01" required></label>
					<label><?php esc_html_e( 'Y (%)', 'ps-pen-paper' ); ?><input name="y" type="number" min="0" max="100" step="0.01" required></label>
					<button type="submit"><?php esc_html_e( 'Figur bewegen', 'ps-pen-paper' ); ?></button>
				</form>
			</details>
		<?php endif; ?>
		<p class="pspp-map-help"<?php if ( ! $canvas ) { echo ' hidden'; } ?>><?php echo esc_html( $preview ? __( 'Private Inhaltsvorschau ohne Figurensteuerung. Verschieben und Zoomen sind möglich; Startpositionen werden im Entwurf festgelegt.', 'ps-pen-paper' ) : __( 'Karte ziehen zum Verschieben; Mausrad oder zwei Finger zum Zoomen. Eigene Figur wählen und Ziel antippen oder die Positionsfelder nutzen. Raster und Bewegung sind keine Regel- oder Reichweitenprüfung.', 'ps-pen-paper' ) ); ?></p>
		<p role="status" data-map-feedback></p>
		<noscript><p><?php esc_html_e( 'Interaktive Marker, Kartenwechsel und Pings benötigen JavaScript. Lade die Seite für neue Szenentexte erneut.', 'ps-pen-paper' ); ?></p></noscript>
	</div>
	<?php
}

function ps_pen_paper_render_scene_token( $token, $index, $members ) {
	$prefix = 'scene[tokens][' . $index . ']';
	?>
	<fieldset class="pspp-field pspp-map-token-row" data-map-token-row>
		<legend><?php esc_html_e( 'Figurenmarker', 'ps-pen-paper' ); ?></legend>
		<input type="hidden" name="<?php echo esc_attr( $prefix . '[id]' ); ?>" value="<?php echo esc_attr( $token['id'] ); ?>">
		<label><?php esc_html_e( 'Sichtbarer Markername', 'ps-pen-paper' ); ?><input type="text" name="<?php echo esc_attr( $prefix . '[name]' ); ?>" value="<?php echo esc_attr( $token['name'] ); ?>" maxlength="80" required></label>
		<label><?php esc_html_e( 'Markertyp', 'ps-pen-paper' ); ?><select name="<?php echo esc_attr( $prefix . '[type]' ); ?>"><?php foreach ( array( 'player' => __( 'Spielerfigur', 'ps-pen-paper' ), 'npc' => __( 'NPC', 'ps-pen-paper' ), 'object' => __( 'Gegenstand', 'ps-pen-paper' ) ) as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $token['type'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
		<label><?php esc_html_e( 'Darf diese Figur bewegen', 'ps-pen-paper' ); ?><select name="<?php echo esc_attr( $prefix . '[owner_id]' ); ?>">
			<option value="0"><?php esc_html_e( 'Nur die Spielleitung', 'ps-pen-paper' ); ?></option>
			<?php foreach ( $members as $member ) : ?><option value="<?php echo esc_attr( $member['id'] ); ?>" <?php selected( $token['owner_id'], $member['id'] ); ?>><?php echo esc_html( $member['name'] ); ?></option><?php endforeach; ?>
		</select></label>
		<label><?php esc_html_e( 'Markerfarbe', 'ps-pen-paper' ); ?><input type="color" name="<?php echo esc_attr( $prefix . '[color]' ); ?>" value="<?php echo esc_attr( $token['color'] ); ?>"></label>
		<label><?php esc_html_e( 'X (%)', 'ps-pen-paper' ); ?><input type="number" min="0" max="100" step="0.01" name="<?php echo esc_attr( $prefix . '[x]' ); ?>" value="<?php echo esc_attr( $token['x'] / 100 ); ?>" required></label>
		<label><?php esc_html_e( 'Y (%)', 'ps-pen-paper' ); ?><input type="number" min="0" max="100" step="0.01" name="<?php echo esc_attr( $prefix . '[y]' ); ?>" value="<?php echo esc_attr( $token['y'] / 100 ); ?>" required></label>
		<button type="button" data-map-remove-token><?php esc_html_e( 'Marker entfernen', 'ps-pen-paper' ); ?></button>
	</fieldset>
	<?php
}

function ps_pen_paper_render_scene_start( $campaign, $page_id, $id, $selection, $label ) {
	ps_pen_paper_form_start( 'select_scene', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id );
	?>
	<input type="hidden" name="scene_id" value="<?php echo esc_attr( $id ); ?>">
	<input type="hidden" name="scene_revision" value="<?php echo esc_attr( $selection['revision'] ); ?>">
	<button type="submit"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
}

function ps_pen_paper_render_adventure_plan( $campaign, $page_id, $plan ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return; }
	$url = $page_id ? ps_pen_paper_game_url( $page_id, $campaign->ID ) : ps_pen_paper_admin_url( $campaign->ID );
	$reload_args = array();
	wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $reload_args );
	$active = $plan['selection']['scene_id'];
	$groups = array();
	$assigned = array();
	foreach ( $plan['records'] as $id => $record ) {
		if ( 'chapter' !== $record['draft']['type'] ) { continue; }
		$groups[] = array( 'id' => $id, 'title' => $record['draft']['title'], 'scenes' => $record['draft']['scene_ids'] );
		foreach ( $record['draft']['scene_ids'] as $scene_id ) { $assigned[ $scene_id ] = true; }
	}
	$unassigned = array_keys( array_diff_key( $plan['scenes'], $assigned ) );
	if ( $unassigned ) { $groups[] = array( 'id' => 0, 'title' => __( 'Szenen ohne Kapitel', 'ps-pen-paper' ), 'scenes' => $unassigned ); }
	?>
	<section class="pspp-adventure-plan" aria-label="<?php esc_attr_e( 'Privater Abenteuerplan', 'ps-pen-paper' ); ?>">
		<h3><?php esc_html_e( 'Dein Abenteuer im Überblick', 'ps-pen-paper' ); ?></h3>
		<p><?php esc_html_e( 'Kapitelreihenfolge und Freigaben auf einen Blick. „Am Tisch anzeigen“ wechselt nur zur bereits freigegebenen Fassung; private Entwürfe und Handouts werden nicht automatisch freigegeben.', 'ps-pen-paper' ); ?></p>
		<?php if ( ! $groups ) : ?><p><?php esc_html_e( 'Lege unten deine erste Szene an. Kapitel und ihre Szenenfolge ordnest du in der Spielleitermappe.', 'ps-pen-paper' ); ?></p><?php endif; ?>
		<?php foreach ( $groups as $group ) : ?>
			<section class="pspp-adventure-chapter">
				<h4><?php echo esc_html( $group['title'] ); ?></h4>
				<?php if ( $group['id'] ) : ?><a href="<?php echo esc_url( add_query_arg( 'pspp_folio', $group['id'], $url ) . '#pspp-folio' ); ?>"><?php esc_html_e( 'Kapitel und Szenenfolge bearbeiten', 'ps-pen-paper' ); ?></a><?php endif; ?>
				<?php if ( ! $group['scenes'] ) : ?><p><?php esc_html_e( 'Diesem Kapitel sind noch keine Szenen zugeordnet.', 'ps-pen-paper' ); ?></p><?php endif; ?>
				<ol class="pspp-adventure-scenes">
					<?php $next = false; foreach ( $group['scenes'] as $scene_id ) :
						if ( ! isset( $plan['scenes'][ $scene_id ] ) ) { echo '<li role="alert">' . esc_html__( 'Eine zugeordnete Szene fehlt. Bitte prüfe die Szenenfolge.', 'ps-pen-paper' ) . '</li>'; $next = false; continue; }
						$scene = $plan['scenes'][ $scene_id ];
						$is_active = $active === $scene_id && null !== $scene['published'];
						$public_draft = $scene['draft']; unset( $public_draft['secret'] );
						?>
						<li class="pspp-adventure-scene<?php if ( $is_active ) { echo ' is-active'; } ?>" data-adventure-scene="<?php echo esc_attr( $scene_id ); ?>"<?php if ( $is_active ) { echo ' aria-current="step"'; } ?>>
							<strong><?php echo esc_html( $scene['draft']['title'] ); ?></strong>
							<p class="pspp-adventure-status"><?php echo esc_html( $is_active ? __( 'Jetzt am Tisch', 'ps-pen-paper' ) : ( $scene['published'] ? __( 'Freigegeben, nicht aktiv', 'ps-pen-paper' ) : __( 'Privater Entwurf', 'ps-pen-paper' ) ) ); ?>
								<?php if ( $next ) : ?> · <strong><?php esc_html_e( 'Nächste Szene im Kapitel', 'ps-pen-paper' ); ?></strong><?php endif; ?>
								<?php if ( $scene['published'] && $public_draft !== $scene['published'] ) : ?><br><?php esc_html_e( 'Entwurf weicht vom Spielstand ab. Ein Wechsel zeigt weiterhin die freigegebene Fassung.', 'ps-pen-paper' ); ?><?php endif; ?>
							</p>
							<div class="pspp-adventure-actions">
								<a class="button" href="<?php echo esc_url( add_query_arg( 'pspp_scene', $scene_id, $url ) . '#pspp-story' ); ?>"><?php esc_html_e( 'Vorbereiten / Vorschau / Freigabe', 'ps-pen-paper' ); ?></a>
								<?php if ( $is_active ) : ?><a class="button" href="<?php echo esc_url( $url . '#pspp-play' ); ?>"><?php esc_html_e( 'Zum Spieltisch', 'ps-pen-paper' ); ?></a>
								<?php elseif ( $scene['published'] ) : ps_pen_paper_render_scene_start( $campaign, $page_id, $scene_id, $plan['selection'], __( 'Am Tisch anzeigen', 'ps-pen-paper' ) ); endif; ?>
							</div>
						</li>
						<?php $next = $is_active && 0 !== $group['id']; ?>
					<?php endforeach; ?>
				</ol>
			</section>
		<?php endforeach; ?>
		<?php if ( isset( $plan['scenes'][ $active ] ) && $plan['scenes'][ $active ]['published'] ) : $current = $plan['scenes'][ $active ]; ?>
			<details class="pspp-fold pspp-adventure-cues" open>
				<summary><?php echo esc_html( __( 'Aktuelle Szene leiten:', 'ps-pen-paper' ) . ' ' . $current['published']['title'] ); ?></summary>
				<p><strong><?php esc_html_e( 'Nur Spielleitung – gespeicherte Notizen', 'ps-pen-paper' ); ?></strong></p>
				<?php if ( '' !== $current['draft']['secret'] ) : ?><p class="pspp-multiline"><?php echo esc_html( $current['draft']['secret'] ); ?></p><?php endif; ?>
				<?php foreach ( $plan['records'] as $record_id => $record ) :
					if ( $record['draft']['scene_id'] !== $active ) { continue; }
					?>
					<details class="pspp-fold">
						<summary><?php echo esc_html( ps_pen_paper_folio_types()[ $record['draft']['type'] ] . ': ' . $record['draft']['title'] ); ?></summary>
						<p class="pspp-multiline"><?php echo esc_html( $record['draft']['text'] ); ?></p>
						<?php if ( '' !== $record['draft']['secret'] ) : ?><p class="pspp-multiline"><?php echo esc_html( $record['draft']['secret'] ); ?></p><?php endif; ?>
						<a href="<?php echo esc_url( add_query_arg( 'pspp_folio', $record_id, $url ) . '#pspp-folio' ); ?>"><?php esc_html_e( 'Mappeneintrag öffnen', 'ps-pen-paper' ); ?></a>
						<?php if ( 'handout' === $record['draft']['type'] ) : ?>
							<p><?php echo esc_html( $record['published'] ? __( 'Dieses Handout ist bereits für die Runde verfügbar.', 'ps-pen-paper' ) : __( 'Dieses Handout ist noch privat.', 'ps-pen-paper' ) ); ?></p>
							<?php if ( ! $record['published'] ) : ps_pen_paper_form_start( 'publish_folio', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
								<input type="hidden" name="folio_id" value="<?php echo esc_attr( $record_id ); ?>"><input type="hidden" name="folio_revision" value="<?php echo esc_attr( $record['revision'] ); ?>"><input type="hidden" name="folio_return" value="story">
								<label class="pspp-check"><input type="checkbox" name="folio_confirmation" value="confirmed" required><?php esc_html_e( 'Gespeicherten Titel, Text und das optionale Bild ausdrücklich für die Runde freigeben.', 'ps-pen-paper' ); ?></label>
								<button type="submit"><?php esc_html_e( 'Handout auf den Tisch legen', 'ps-pen-paper' ); ?></button>
							</form><?php endif; ?>
						<?php endif; ?>
					</details>
				<?php endforeach; ?>
			</details>
		<?php endif; ?>
		<p><?php esc_html_e( 'Dieser Plan zeigt den Stand beim Laden. Nach Änderungen durch eine andere Spielleitung bitte neu laden.', 'ps-pen-paper' ); ?></p>
		<form method="get" action="<?php echo esc_url( $url . '#pspp-story' ); ?>">
			<?php foreach ( $reload_args as $key => $value ) : ?><input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"><?php endforeach; ?>
			<button type="submit"><?php esc_html_e( 'Abenteuerplan und Notizen neu laden', 'ps-pen-paper' ); ?></button>
		</form>
	</section>
	<?php
}

function ps_pen_paper_render_story( $campaign, $page_id ) {
	if ( ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return; }
	$id = ps_pen_paper_frontend_id( 'pspp_scene' );
	$state = is_wp_error( $id ) ? $id : ( $id ? ps_pen_paper_get_scene( $campaign, $id ) : ps_pen_paper_scene_default() );
	$plan = ps_pen_paper_adventure_plan( $campaign );
	if ( is_wp_error( $state ) || is_wp_error( $plan ) ) { echo '<p role="alert">' . esc_html__( 'Die gewählte Szene oder der Abenteuerplan ist nicht verfügbar.', 'ps-pen-paper' ) . '</p>'; return; }
	$selection = $plan['selection'];
	$base_url = $page_id ? ps_pen_paper_game_url( $page_id, $campaign->ID ) : ps_pen_paper_admin_url( $campaign->ID );
	$draft = $state['draft'];
	if ( ! $id ) { $draft['grid'] = 0; }
	?>
	<h2><?php esc_html_e( 'Abenteuer vorbereiten & leiten', 'ps-pen-paper' ); ?></h2>
	<details class="pspp-fold"<?php if ( ! $id ) { echo ' open'; } ?>>
		<summary><?php esc_html_e( 'Abenteuerplan und aktuelle Spielleitungsnotizen', 'ps-pen-paper' ); ?></summary>
		<?php ps_pen_paper_render_adventure_plan( $campaign, $page_id, $plan ); ?>
	</details>
	<p><a href="<?php echo esc_url( $base_url . '#pspp-folio' ); ?>"><?php esc_html_e( 'Kapitel und Spielleitermappe öffnen', 'ps-pen-paper' ); ?></a></p>
	<p><?php esc_html_e( 'Entwurf speichern → private Spielervorschau prüfen → freigeben → am Tisch anzeigen. Entwurfsänderungen verändern nie sofort die Spielersicht. Alle freigegebenen Marker sind für die Runde sichtbar; private Charakterwerte werden nicht übertragen.', 'ps-pen-paper' ); ?></p>
	<nav class="pspp-navigation"><a href="<?php echo esc_url( $base_url . '#pspp-story' ); ?>"><?php esc_html_e( 'Neue Szene', 'ps-pen-paper' ); ?></a>
	<?php foreach ( $plan['scenes'] as $scene_id => $item ) :
		?><a href="<?php echo esc_url( add_query_arg( 'pspp_scene', $scene_id, $base_url ) . '#pspp-story' ); ?>"><?php echo esc_html( $item['draft']['title'] ); ?></a>
	<?php endforeach; ?></nav>
	<?php ps_pen_paper_form_start( 'select_scene', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
		<input type="hidden" name="scene_id" value="0"><input type="hidden" name="scene_revision" value="<?php echo esc_attr( $selection['revision'] ); ?>">
		<button type="submit"><?php esc_html_e( 'Zur Rundeneinleitung wechseln', 'ps-pen-paper' ); ?></button>
	</form>
	<?php if ( $id ) : ?>
		<details class="pspp-fold"><summary><?php esc_html_e( 'Gespeicherten Entwurf als Spieler ansehen', 'ps-pen-paper' ); ?></summary>
			<?php $preview = $draft; unset( $preview['secret'] ); $preview['id'] = $id; $preview['revision'] = $state['revision']; $preview['publication'] = $state['publication'];
			foreach ( $preview['tokens'] as &$token ) { $token['can_move'] = false; } unset( $token );
			ps_pen_paper_render_map( $campaign, array( 'scene' => $preview, 'ping' => null ), true ); ?>
		</details>
		<?php foreach ( array( 'publish_scene' => __( 'Gespeicherten Entwurf freigeben', 'ps-pen-paper' ), 'withdraw_scene' => __( 'Freigabe zurücknehmen', 'ps-pen-paper' ) ) as $operation => $label ) : ?>
			<?php if ( 'withdraw_scene' === $operation && ! $state['published'] ) { continue; } ?>
			<?php ps_pen_paper_form_start( $operation, 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
				<input type="hidden" name="scene_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="scene_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
				<label class="pspp-check"><input type="checkbox" name="scene_confirmation" value="confirmed" required><?php echo esc_html( 'publish_scene' === $operation ? __( 'Vorbereiteten Stand einschließlich Startpositionen freigeben. Ein zuvor freigegebener Stand wird ersetzt; Szenenwechsel erfolgt separat.', 'ps-pen-paper' ) : __( 'Diese Szene ist anschließend nicht mehr für Spieler abrufbar.', 'ps-pen-paper' ) ); ?></label>
				<button type="submit"><?php echo esc_html( $label ); ?></button>
			</form>
		<?php endforeach; ?>
		<?php if ( $state['published'] ) : ?>
			<?php ps_pen_paper_render_scene_start( $campaign, $page_id, $id, $selection, __( 'Freigegebene Szene am Tisch anzeigen', 'ps-pen-paper' ) ); ?>
		<?php endif; ?>
	<?php endif; ?>
	<h3><?php esc_html_e( 'Privater Szenenentwurf', 'ps-pen-paper' ); ?></h3>
	<?php ps_pen_paper_form_start( 'save_scene', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id, true ); ?>
		<input type="hidden" name="scene_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="scene_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
		<label><?php esc_html_e( 'Szenentitel', 'ps-pen-paper' ); ?><input type="text" name="scene[title]" maxlength="200" value="<?php echo esc_attr( $draft['title'] ); ?>" required></label>
		<label><?php esc_html_e( 'Freizugebender Szenentext', 'ps-pen-paper' ); ?><textarea name="scene[text]" rows="5"><?php echo esc_textarea( $draft['text'] ); ?></textarea></label>
		<label><?php esc_html_e( 'Geheimnisse – nur Spielleitung', 'ps-pen-paper' ); ?><textarea name="scene[secret]" rows="4"><?php echo esc_textarea( $draft['secret'] ); ?></textarea></label>
		<details class="pspp-fold"<?php if ( $draft['asset_id'] || $draft['grid'] || $draft['tokens'] ) { echo ' open'; } ?>><summary><?php esc_html_e( 'Optionale Karte und Figurenmarker', 'ps-pen-paper' ); ?></summary>
		<p><?php esc_html_e( 'Für eine reine Erzählszene brauchst du keine Karte: Bild weglassen, Raster auf 0 und keine Marker anlegen.', 'ps-pen-paper' ); ?></p>
		<label><?php esc_html_e( 'Kartenbild hochladen oder ersetzen', 'ps-pen-paper' ); ?><input name="scene_image" type="file" accept="image/png,image/jpeg,image/webp"></label>
		<p><?php esc_html_e( 'Optional. PNG, JPEG oder WebP bis 2 MiB und 3000 × 3000 Pixel. Ohne Bild ist eine neutrale Rasterfläche möglich. Bestehende Bilder bleiben ohne neuen Upload erhalten.', 'ps-pen-paper' ); ?></p>
		<label><?php esc_html_e( 'Quadratraster in Bildpixeln (0 = aus)', 'ps-pen-paper' ); ?><input name="scene[grid]" type="number" min="0" max="500" step="1" value="<?php echo esc_attr( $draft['grid'] ); ?>" required></label>
		<p><?php esc_html_e( 'Rasterweite: 10–500 Pixel oder 0. Positionen: 0–100 Prozent; 50 ist die Kartenmitte. Höchstens 30 Marker. Freigeben übernimmt die vorbereiteten Positionen, nicht die zwischenzeitlichen Bewegungen am Tisch.', 'ps-pen-paper' ); ?></p>
		<div data-map-token-editor data-limit-message="<?php esc_attr_e( 'Es sind höchstens 30 Marker möglich.', 'ps-pen-paper' ); ?>">
			<div data-map-token-rows><?php foreach ( $draft['tokens'] as $index => $token ) { ps_pen_paper_render_scene_token( $token, $index, ps_pen_paper_table_members( $campaign ) ); } ?></div>
			<button type="button" data-map-add-token hidden><?php esc_html_e( 'Marker hinzufügen', 'ps-pen-paper' ); ?></button>
			<template data-map-token-template><?php ps_pen_paper_render_scene_token( array( 'id' => '', 'name' => '', 'type' => 'player', 'owner_id' => 0, 'color' => '#d6a858', 'x' => 5000, 'y' => 5000 ), '__INDEX__', ps_pen_paper_table_members( $campaign ) ); ?></template>
			<p role="status" data-map-editor-feedback></p>
		</div>
		</details>
		<input type="hidden" name="scene_complete" value="complete">
		<button type="submit"><?php esc_html_e( 'Entwurf speichern', 'ps-pen-paper' ); ?></button>
	</form>
	<?php
}
