<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_render_table_sheet( $campaign, $page_id ) {
	$rules = ps_pen_paper_get_rules( $campaign->ID );
	$characters = ps_pen_paper_get_characters( $campaign );
	?>
	<h2><?php echo esc_html( ps_pen_paper_can_manage_campaign( $campaign ) ? __( 'Bögen & Freigaben', 'ps-pen-paper' ) : __( 'Mein Charakterbogen', 'ps-pen-paper' ) ); ?></h2>
	<p class="description"><?php esc_html_e( 'Nur du und die Spielleitung sehen deine Werte.', 'ps-pen-paper' ); ?></p>
	<?php foreach ( $characters as $character ) : ?>
		<?php
		$state = ps_pen_paper_character_state( $campaign, $character );
		if ( is_wp_error( $state ) ) {
			echo '<p role="alert">' . esc_html( $state->get_error_message() ) . '</p>';
			continue;
		}
		?>
		<article class="pspp-sheet"<?php $sheet_style_result = ps_pen_paper_sheet_attributes( $campaign ); ?>>
			<?php if ( is_wp_error( $sheet_style_result ) ) { echo '<p role="alert">' . esc_html( $sheet_style_result->get_error_message() ) . '</p>'; } ?>
			<div class="pspp-sheet-heading">
				<?php ps_pen_paper_render_portrait( $campaign, $character, $state['active'] ? $state['active']['name'] : __( 'Neu', 'ps-pen-paper' ) ); ?>
				<div><span class="pspp-eyebrow"><?php esc_html_e( 'Charakterbogen', 'ps-pen-paper' ); ?></span>
					<h3><?php echo esc_html( $state['active'] ? $state['active']['name'] : __( 'Noch nicht freigegeben', 'ps-pen-paper' ) ); ?></h3>
				</div>
			</div>
			<?php if ( $state['active'] ) : ?>
				<?php foreach ( array(
					__( 'Profil & Hintergrund', 'ps-pen-paper' ) => array( 'text', 'textarea' ),
					__( 'Attribute & Werte', 'ps-pen-paper' ) => array( 'attribute', 'number' ),
					__( 'Ressourcen', 'ps-pen-paper' ) => array( 'resource' ),
				) as $heading => $types ) :
					$fields = array_filter( $rules['fields'], function ( $field ) use ( $types ) { return in_array( $field['type'], $types, true ); } );
					if ( ! $fields ) { continue; }
					?>
				<section class="pspp-sheet-section">
					<h4><?php echo esc_html( $heading ); ?></h4>
					<dl class="pspp-sheet-fields">
					<?php foreach ( $fields as $field ) : ?>
						<?php $value = $state['active']['values'][ $field['id'] ]; ?>
						<div class="<?php echo in_array( $field['type'], array( 'resource', 'text', 'textarea' ), true ) ? 'pspp-sheet-wide' : 'pspp-sheet-stat'; ?>">
							<dt><?php echo esc_html( $field['label'] ); ?></dt>
							<dd>
								<?php if ( 'resource' === $field['type'] ) : ?>
									<strong><?php echo esc_html( $value['current'] . ' / ' . $value['maximum'] ); ?></strong>
									<?php if ( $value['maximum'] > $field['min'] ) : ?>
										<meter min="<?php echo esc_attr( $field['min'] ); ?>" max="<?php echo esc_attr( $value['maximum'] ); ?>" value="<?php echo esc_attr( $value['current'] ); ?>" aria-label="<?php echo esc_attr( $field['label'] ); ?>"><?php echo esc_html( $value['current'] . ' / ' . $value['maximum'] ); ?></meter>
									<?php endif; ?>
								<?php else : ?>
									<span class="pspp-multiline"><?php echo esc_html( $value ); ?></span>
									<?php if ( 'attribute' === $field['type'] && 0 === $field['min'] && $field['max'] > 0 && $field['max'] <= 10 ) : ?>
										<span class="pspp-rating" aria-hidden="true"><?php for ( $point = 1; $point <= $field['max']; $point++ ) : ?><i<?php echo $point <= $value ? ' class="is-filled"' : ''; ?>></i><?php endfor; ?></span>
									<?php endif; ?>
								<?php endif; ?>
							</dd>
						</div>
					<?php endforeach; ?>
					</dl>
				</section>
				<?php endforeach; ?>
			<?php endif; ?>
			<?php if ( $state['pending'] ) : ?><p class="pspp-pending"><?php esc_html_e( 'Freigabe ausstehend – die gültigen Werte bleiben unverändert.', 'ps-pen-paper' ); ?></p><?php endif; ?>
			<?php if ( isset( $state['equipment'] ) && $state['equipment']['bonuses'] ) : ?><p><?php esc_html_e( 'Freigegebene Ausrüstungsboni sind in den angezeigten Werten enthalten. Grundwerte bleiben unverändert.', 'ps-pen-paper' ); ?></p><?php endif; ?>
			<?php if ( isset( $state['context'] ) ) : ?><p><?php echo esc_html( ps_pen_paper_character_context_modes()[ $state['context']['mode'] ] . ' · ' . __( 'Kampagnenauflagen sind berücksichtigt.', 'ps-pen-paper' ) ); ?></p><?php endif; ?>
			<a class="button" href="<?php echo esc_url( ps_pen_paper_character_url( $campaign->ID, $character->ID, $page_id ) ); ?>"><?php echo esc_html( $state['pending'] && ps_pen_paper_can_manage_campaign( $campaign ) ? __( 'Antrag prüfen', 'ps-pen-paper' ) : __( 'Bogen öffnen / bearbeiten', 'ps-pen-paper' ) ); ?></a>
		</article>
	<?php endforeach; ?>
	<?php if ( ! $characters ) : ?><p><?php echo esc_html( ps_pen_paper_can_edit_character( $campaign ) ? __( 'Dein erster Bogen wartet noch auf dich. Öffne unten „Neuen Charakter erstellen“.', 'ps-pen-paper' ) : __( 'In dieser Runde erstellt die Spielleitung deinen Charakter. Bitte wende dich an sie.', 'ps-pen-paper' ) ); ?></p><?php endif; ?>
	<?php if ( $rules['revision'] && ps_pen_paper_can_edit_character( $campaign ) ) : ?>
		<details class="pspp-fold"><summary><?php esc_html_e( 'Neuen Charakter erstellen', 'ps-pen-paper' ); ?></summary>
			<?php ps_pen_paper_render_character_form( $campaign, $rules, null, $page_id ); ?>
		</details>
	<?php elseif ( ! $rules['revision'] ) : ?>
		<p><?php esc_html_e( 'Die Spielleitung muss zuerst das Regelwerk einrichten.', 'ps-pen-paper' ); ?></p>
	<?php endif; ?>
	<?php
}

function ps_pen_paper_render_table( $campaign, $page_id, $character_id ) {
	$state = ps_pen_paper_table_state( $campaign );
	$is_gm = ps_pen_paper_can_manage_campaign( $campaign );
	$backdrop = ps_pen_paper_backdrop_image( $campaign );
	$labels = array(
		'online' => __( 'Am Tisch', 'ps-pen-paper' ), 'offline' => __( 'Offline', 'ps-pen-paper' ),
		'afk' => __( 'Kurze Pause', 'ps-pen-paper' ), 'unreported' => __( 'Nicht gemeldet', 'ps-pen-paper' ),
		'free' => __( 'Freies Spiel', 'ps-pen-paper' ), 'turn' => __( 'Am Zug:', 'ps-pen-paper' ),
		'unavailable' => __( 'Zugzuordnung nicht mehr verfügbar', 'ps-pen-paper' ),
		'error' => __( 'Die Runde konnte nicht aktualisiert werden. Bitte erneut versuchen.', 'ps-pen-paper' ),
		'sharing' => __( 'Anwesenheit wird geteilt', 'ps-pen-paper' ),
		'stopped' => __( 'Anwesenheitsanzeige beendet', 'ps-pen-paper' ),
		'new' => __( 'Neue Nachrichten', 'ps-pen-paper' ),
		'gm' => __( 'Spielleitung', 'ps-pen-paper' ), 'player' => __( 'Spieler', 'ps-pen-paper' ),
	);
	?>
	<div class="pspp-table" data-table-endpoint="<?php echo esc_url( rest_url( 'ps-pen-paper/v1/campaigns/' . $campaign->ID . '/table' ) ); ?>" data-table-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" data-table-user="<?php echo esc_attr( get_current_user_id() ); ?>" data-table-labels="<?php echo esc_attr( wp_json_encode( $labels ) ); ?>">
		<header class="pspp-table-header">
			<div><span class="pspp-eyebrow"><?php echo esc_html( $is_gm ? __( 'PS Pen & Paper / Spielleitung', 'ps-pen-paper' ) : __( 'PS Pen & Paper / Spieltisch', 'ps-pen-paper' ) ); ?></span>
				<h2><?php echo esc_html( $campaign->post_title ); ?></h2>
			</div>
			<div class="pspp-turn-banner" data-table-turn><?php echo esc_html( $state['turn']['available'] ? ( $state['turn']['user_id'] ? $labels['turn'] . ' ' : '' ) . $state['turn']['name'] : $labels['unavailable'] ); ?></div>
		</header>
		<nav class="pspp-table-tools" aria-label="<?php esc_attr_e( 'Spieltisch', 'ps-pen-paper' ); ?>">
			<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id ) ); ?>"><?php esc_html_e( 'Kampagnen wechseln', 'ps-pen-paper' ); ?></a>
			<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-log' ); ?>"><?php esc_html_e( 'Spielprotokoll', 'ps-pen-paper' ); ?></a>
			<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-rulebook' ); ?>"><?php esc_html_e( 'Regelwerk', 'ps-pen-paper' ); ?></a>
			<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-handouts' ); ?>"><?php esc_html_e( 'Handouts', 'ps-pen-paper' ); ?></a>
			<?php if ( $is_gm ) : ?><a data-table-view="folio" href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-folio' ); ?>"><?php esc_html_e( 'Spielleitermappe', 'ps-pen-paper' ); ?></a><?php endif; ?>
			<?php if ( $is_gm ) : ?><a data-table-view="settings" href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-settings' ); ?>"><?php esc_html_e( 'Rundeneinstellungen', 'ps-pen-paper' ); ?></a><?php endif; ?>
			<?php if ( $is_gm ) : ?><a data-table-view="story" href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-story' ); ?>"><?php esc_html_e( 'Abenteuer & Szenen', 'ps-pen-paper' ); ?></a><?php endif; ?>
			<?php if ( $is_gm && ps_pen_paper_community_available() ) : ?><a data-table-view="equipment" href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-equipment' ); ?>"><?php esc_html_e( 'Gegenstände', 'ps-pen-paper' ); ?></a><?php endif; ?>
			<?php if ( ps_pen_paper_community_url() ) : ?><a href="<?php echo esc_url( ps_pen_paper_community_url() ); ?>"><?php esc_html_e( 'Mein Charakterprofil', 'ps-pen-paper' ); ?></a><?php endif; ?>
		</nav>
		<?php ps_pen_paper_render_notice(); ?>
		<?php if ( is_wp_error( $backdrop ) ) : ?><p role="alert"><?php echo esc_html( $backdrop->get_error_message() ); ?></p><?php endif; ?>
		<p role="status" class="pspp-table-feedback" data-table-feedback></p>
		<?php if ( $character_id ) : ?>
			<?php ps_pen_paper_render_characters( $campaign, $character_id, $page_id ); ?>
		<?php else : ?>
			<nav class="pspp-table-tabs" aria-label="<?php esc_attr_e( 'Spieltisch-Bereiche', 'ps-pen-paper' ); ?>">
				<span class="pspp-mobile-turn" data-table-turn><?php echo esc_html( $state['turn']['available'] ? ( $state['turn']['user_id'] ? $labels['turn'] . ' ' : '' ) . $state['turn']['name'] : $labels['unavailable'] ); ?></span>
				<a href="#pspp-play" data-table-view="play"><?php esc_html_e( 'Spiel', 'ps-pen-paper' ); ?></a>
				<a href="#pspp-sheet" data-table-view="sheet"><?php echo esc_html( $is_gm ? __( 'Bögen', 'ps-pen-paper' ) : __( 'Mein Bogen', 'ps-pen-paper' ) ); ?></a>
				<a href="#pspp-dice" data-table-view="dice"><?php esc_html_e( 'Würfel', 'ps-pen-paper' ); ?></a>
				<a href="#pspp-group" data-table-view="group"><?php esc_html_e( 'Gruppe', 'ps-pen-paper' ); ?></a>
			</nav>
			<div class="pspp-table-layout">
				<aside id="pspp-group" class="pspp-table-panel pspp-table-group" data-table-panel="group">
					<div class="pspp-party-heading"><h2><?php esc_html_e( 'Am Spieltisch', 'ps-pen-paper' ); ?></h2>
						<a href="#pspp-group" data-group-toggle><?php esc_html_e( 'Anwesenheit & Pause', 'ps-pen-paper' ); ?></a>
					</div>
					<ul class="pspp-party" data-table-members>
						<?php foreach ( $state['members'] as $member ) : ?>
							<li data-member-id="<?php echo esc_attr( $member['id'] ); ?>" data-member-status="<?php echo esc_attr( $member['status'] ); ?>" <?php echo $state['turn']['user_id'] === $member['id'] ? 'class="is-turn"' : ''; ?>>
								<span class="pspp-portrait" aria-hidden="true"><?php echo esc_html( wp_html_excerpt( $member['name'], 1, '' ) ); ?></span>
								<div><strong><?php echo esc_html( $member['name'] ); ?></strong><small><?php echo esc_html( $labels[ $member['role'] ] ); ?></small>
									<span data-member-label><?php echo esc_html( $labels[ $member['status'] ] . ( $member['note'] ? ' · ' . $member['note'] : '' ) ); ?></span>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
					<details class="pspp-fold pspp-group-controls" data-group-controls>
					<summary><?php echo esc_html( $is_gm ? __( 'Anwesenheit, Pausen & Zugsteuerung', 'ps-pen-paper' ) : __( 'Anwesenheit & kurze Pause', 'ps-pen-paper' ) ); ?></summary>
					<div class="pspp-presence-controls" data-presence-controls hidden>
						<button type="button" data-presence="join"><?php esc_html_e( 'Anwesenheit teilen', 'ps-pen-paper' ); ?></button>
						<div data-presence-active hidden>
							<label for="pspp-away-note"><?php esc_html_e( 'Pausennotiz (optional)', 'ps-pen-paper' ); ?></label>
							<input type="text" id="pspp-away-note" maxlength="80" placeholder="<?php esc_attr_e( 'Kurz in der Küche …', 'ps-pen-paper' ); ?>">
							<button type="button" data-presence="afk"><?php esc_html_e( 'Kurze Pause / AFK', 'ps-pen-paper' ); ?></button>
							<button type="button" data-presence="back"><?php esc_html_e( 'Wieder da', 'ps-pen-paper' ); ?></button>
							<button type="button" data-presence="leave"><?php esc_html_e( 'Status nicht mehr teilen', 'ps-pen-paper' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Freiwillig. Nach zwei Minuten ohne Meldung erscheint dein Status als offline. Keine Anwesenheitshistorie.', 'ps-pen-paper' ); ?></p>
					</div>
					<?php if ( $is_gm ) : ?>
						<form data-turn-form hidden>
							<label for="pspp-turn-user"><?php esc_html_e( 'Wer ist am Zug?', 'ps-pen-paper' ); ?></label>
							<select id="pspp-turn-user"><option value="0"><?php esc_html_e( 'Freies Spiel', 'ps-pen-paper' ); ?></option>
								<?php foreach ( $state['members'] as $member ) : ?><option value="<?php echo esc_attr( $member['id'] ); ?>" <?php selected( $state['turn']['user_id'], $member['id'] ); ?>><?php echo esc_html( $member['name'] ); ?></option><?php endforeach; ?>
							</select>
							<button type="submit"><?php esc_html_e( 'Zug festlegen', 'ps-pen-paper' ); ?></button>
						</form>
					<?php endif; ?>
					</details>
				</aside>
				<section id="pspp-play" class="pspp-table-panel pspp-table-stage pspp-scene" data-table-panel="play"<?php if ( ! is_wp_error( $backdrop ) && $backdrop ) : ?> style="<?php echo esc_attr( '--pspp-table-image:url("' . $backdrop . '")' ); ?>"<?php endif; ?>>
					<?php ps_pen_paper_render_map( $campaign, ps_pen_paper_map_state( $campaign ) ); ?>
					<div class="pspp-scene-caption" data-map-fallback>
					<span class="pspp-eyebrow"><?php esc_html_e( 'Eure Welt. Eure Geschichte.', 'ps-pen-paper' ); ?></span>
					<h2><?php echo esc_html( $campaign->post_title ); ?></h2>
					<p class="pspp-scene-lead"><?php echo esc_html( wp_html_excerpt( $campaign->post_content, 220, '…' ) ); ?></p>
					<details class="pspp-stage-story"><summary><?php esc_html_e( 'Vollständige Rundeneinleitung lesen', 'ps-pen-paper' ); ?></summary>
						<div class="pspp-table-intro pspp-multiline"><?php echo esc_html( $campaign->post_content ); ?></div>
					</details>
					<?php if ( '1' === get_post_meta( $campaign->ID, '_pspp_archived', true ) ) : ?><p><?php esc_html_e( 'Diese Kampagne ist als archiviert gekennzeichnet.', 'ps-pen-paper' ); ?></p><?php endif; ?>
					<div class="pspp-table-prompts">
						<?php if ( ! $is_gm && ps_pen_paper_can_edit_character( $campaign ) && ! ps_pen_paper_get_characters( $campaign ) ) : ?>
							<a href="#pspp-sheet"><?php esc_html_e( 'Ersten Charakter erstellen', 'ps-pen-paper' ); ?></a>
						<?php else : ?>
							<a href="#pspp-sheet"><?php echo esc_html( $is_gm ? __( 'Bögen & Freigaben', 'ps-pen-paper' ) : __( 'Meinen Charakterbogen öffnen', 'ps-pen-paper' ) ); ?></a>
						<?php endif; ?>
						<a href="#pspp-dice"><?php esc_html_e( 'Probe würfeln', 'ps-pen-paper' ); ?></a>
						<a href="#pspp-log"><?php esc_html_e( 'Was bisher geschah', 'ps-pen-paper' ); ?></a>
					</div>
					</div>
				</section>
				<section id="pspp-sheet" class="pspp-table-panel pspp-table-character" data-table-panel="sheet"><?php ps_pen_paper_render_table_sheet( $campaign, $page_id ); ?></section>
				<div class="pspp-table-panel pspp-table-stage" data-table-panel="dice"><?php ps_pen_paper_render_dice( $campaign, $page_id, false ); ?></div>
				<section class="pspp-table-panel pspp-table-stage" data-table-panel="log"><?php ps_pen_paper_render_journal( $campaign, $page_id ); ?></section>
				<section id="pspp-rulebook" class="pspp-table-panel pspp-table-stage" data-table-panel="rules"><?php ps_pen_paper_render_rules( $campaign, false ); ?></section>
				<section id="pspp-handouts" class="pspp-table-panel pspp-table-stage" data-table-panel="handouts"><?php ps_pen_paper_render_handouts( $campaign, $page_id ); ?></section>
				<?php if ( $is_gm ) : ?>
					<section id="pspp-folio" class="pspp-table-panel pspp-table-stage" data-table-panel="folio"><?php ps_pen_paper_render_folio( $campaign, $page_id ); ?></section>
					<?php if ( ps_pen_paper_community_available() ) : ?><section id="pspp-equipment" class="pspp-table-panel pspp-table-stage" data-table-panel="equipment"><?php ps_pen_paper_render_equipment_catalogue( $campaign, $page_id ); ?></section><?php endif; ?>
					<section id="pspp-story" class="pspp-table-panel pspp-table-stage" data-table-panel="story"><?php ps_pen_paper_render_story( $campaign, $page_id ); ?></section>
					<section id="pspp-settings" class="pspp-table-panel pspp-table-stage pspp-table-settings" data-table-panel="settings"><?php ps_pen_paper_render_access_settings( $campaign, $page_id ); ?></section>
				<?php endif; ?>
			</div>
			<noscript><p><?php esc_html_e( 'Ohne JavaScript stehen die Bereiche untereinander zur Verfügung. Anwesenheit und Zugsteuerung benötigen JavaScript.', 'ps-pen-paper' ); ?></p></noscript>
		<?php endif; ?>
		<?php ps_pen_paper_render_chat( $campaign ); ?>
	</div>
	<?php
}
