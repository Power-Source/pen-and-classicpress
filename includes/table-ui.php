<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_render_table_sheet( $campaign, $page_id ) {
	$management = ps_pen_paper_management_view( $page_id );
	$rules = ps_pen_paper_get_rules( $campaign->ID );
	$characters = ps_pen_paper_get_characters( $campaign );
	$link = ps_pen_paper_get_universe_link( $campaign->ID );
	$universe = ! is_wp_error( $link ) && $link['universe_id'] ? ps_pen_paper_get_universe( $link['universe_id'] ) : null;
	$profile_creation = $universe && ps_pen_paper_community_available();
	$choices = array();
	$picker_args = array( 'pspp_campaign' => $campaign->ID );
	$view = ps_pen_paper_frontend_view();
	if ( $management && ! is_wp_error( $view ) && 'overview' !== $view ) { $picker_args['pspp_view'] = $view; }
	$has_own_character = false;
	foreach ( $characters as $character ) {
		if ( ! $management && (int) $character->post_author !== get_current_user_id() ) { continue; }
		if ( (int) $character->post_author === get_current_user_id() ) { $has_own_character = true; }
		$choices[] = array( 'scope' => $campaign, 'character' => $character, 'state' => ps_pen_paper_character_state( $campaign, $character ) );
	}
	?>
	<h2><?php echo esc_html( $management && ps_pen_paper_can_manage_campaign( $campaign ) ? __( 'Bögen & Freigaben', 'ps-pen-paper' ) : __( 'Mein Charakterbogen', 'ps-pen-paper' ) ); ?></h2>
	<p class="description"><?php esc_html_e( 'Nur du und die Spielleitung sehen deine Werte.', 'ps-pen-paper' ); ?></p>
	<?php ps_pen_paper_render_character_picker( $choices, get_permalink( $page_id ), 0, $picker_args ); ?>
	<?php if ( ! $has_own_character ) { ps_pen_paper_render_campaign_base_requests( $campaign, $page_id ); } ?>
	<?php if ( ! $characters && $rules['revision'] && ! $profile_creation ) : ?><p><?php echo esc_html( ps_pen_paper_can_edit_character( $campaign ) ? __( 'Dein erster Bogen wartet noch auf dich. Öffne unten „Neuen Charakter erstellen“.', 'ps-pen-paper' ) : __( 'In dieser Runde erstellt die Spielleitung deinen Charakter. Bitte wende dich an sie.', 'ps-pen-paper' ) ); ?></p><?php endif; ?>
	<?php if ( $rules['revision'] && ps_pen_paper_can_edit_character( $campaign ) && ! $profile_creation ) : ?>
		<details class="pspp-fold"><summary><?php esc_html_e( 'Neuen Charakter erstellen', 'ps-pen-paper' ); ?></summary>
			<?php ps_pen_paper_render_character_form( $campaign, $rules, null, $page_id, null, false, ! $management ); ?>
		</details>
	<?php elseif ( ! $rules['revision'] ) : ?>
		<p><?php esc_html_e( 'Die Spielleitung muss zuerst das Regelwerk einrichten.', 'ps-pen-paper' ); ?></p>
		<?php
		$source_rules = $universe ? ps_pen_paper_get_rules( $universe->ID ) : null;
		if ( $management && $source_rules && $source_rules['revision'] && ps_pen_paper_can_manage_campaign( $campaign ) && ps_pen_paper_can_manage_universe( $universe ) ) {
			ps_pen_paper_form_start( 'adopt_universe_rules', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id );
			echo '<input type="hidden" name="source_rules_revision" value="' . esc_attr( $source_rules['revision'] ) . '"><input type="hidden" name="pspp_table_adoption" value="1"><button name="context_confirmation" value="confirmed">' . esc_html__( 'Regeln dieses Universums übernehmen', 'ps-pen-paper' ) . '</button></form>';
		}
		?>
	<?php endif; ?>
	<?php
}

function ps_pen_paper_render_table( $campaign, $page_id, $character_id ) {
	$state = ps_pen_paper_table_state( $campaign );
	$epoch = ps_pen_paper_scope_epoch( $campaign->ID );
	if ( is_wp_error( $state ) || is_wp_error( $epoch ) ) { echo '<p role="alert">' . esc_html( is_wp_error( $state ) ? $state->get_error_message() : $epoch->get_error_message() ) . '</p>'; return; }
	$is_gm = ps_pen_paper_can_manage_campaign( $campaign ) && ps_pen_paper_management_view( $page_id );
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
	<div class="pspp-table" data-table-endpoint="<?php echo esc_url( rest_url( 'ps-pen-paper/v1/campaigns/' . $campaign->ID . '/table' ) ); ?>" data-table-epoch="<?php echo esc_attr( $epoch ); ?>" data-table-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" data-table-user="<?php echo esc_attr( get_current_user_id() ); ?>" data-table-labels="<?php echo esc_attr( wp_json_encode( $labels ) ); ?>">
		<header class="pspp-table-header">
			<div><span class="pspp-eyebrow"><?php echo esc_html( $is_gm ? __( 'PS Pen & Paper / Spielleitung', 'ps-pen-paper' ) : __( 'PS Pen & Paper / Spieltisch', 'ps-pen-paper' ) ); ?></span>
				<h2><?php echo esc_html( $campaign->post_title ); ?></h2>
			</div>
			<div class="pspp-turn-banner" data-table-turn><?php echo esc_html( $state['turn']['available'] ? ( $state['turn']['user_id'] ? $labels['turn'] . ' ' : '' ) . $state['turn']['name'] : $labels['unavailable'] ); ?></div>
		</header>
		<nav class="pspp-table-tools" aria-label="<?php esc_attr_e( 'Spieltisch', 'ps-pen-paper' ); ?>">
			<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id ) ); ?>"><?php esc_html_e( 'Spiele wechseln', 'ps-pen-paper' ); ?></a>
			<?php if ( $character_id ) : ?><a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-play' ); ?>"><?php esc_html_e( 'Zurück zum Spieltisch', 'ps-pen-paper' ); ?></a><?php endif; ?>
			<details data-table-management>
			<summary><?php echo esc_html( $is_gm ? __( 'Vorbereiten & verwalten', 'ps-pen-paper' ) : __( 'Weitere Informationen', 'ps-pen-paper' ) ); ?></summary>
			<div class="pspp-table-tools">
			<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-log' ); ?>"><?php esc_html_e( 'Spielprotokoll', 'ps-pen-paper' ); ?></a>
			<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-rulebook' ); ?>"><?php esc_html_e( 'Regelwerk', 'ps-pen-paper' ); ?></a>
			<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-handouts' ); ?>"><?php esc_html_e( 'Handouts', 'ps-pen-paper' ); ?></a>
			<?php if ( $is_gm ) : ?><a data-table-view="folio" href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-folio' ); ?>"><?php esc_html_e( 'Spielleitermappe', 'ps-pen-paper' ); ?></a><?php endif; ?>
			<?php if ( $is_gm ) : ?><a data-table-view="settings" href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-settings' ); ?>"><?php esc_html_e( 'Rundeneinstellungen', 'ps-pen-paper' ); ?></a><?php endif; ?>
			<?php if ( $is_gm ) : ?><a data-table-view="story" href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-story' ); ?>"><?php esc_html_e( 'Szenen, Karten & Abenteuer vorbereiten', 'ps-pen-paper' ); ?></a><?php endif; ?>
			<?php if ( $is_gm && ps_pen_paper_community_available() ) : ?><a data-table-view="equipment" href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-equipment' ); ?>"><?php esc_html_e( 'Gegenstände', 'ps-pen-paper' ); ?></a><?php endif; ?>
			<?php if ( ps_pen_paper_community_url() ) : ?><a href="<?php echo esc_url( ps_pen_paper_community_url() ); ?>"><?php esc_html_e( 'Mein Charakterprofil', 'ps-pen-paper' ); ?></a><?php endif; ?>
			</div></details>
		</nav>
		<?php ps_pen_paper_render_notice(); ?>
		<?php if ( is_wp_error( $backdrop ) ) : ?><p role="alert"><?php echo esc_html( $backdrop->get_error_message() ); ?></p><?php endif; ?>
		<p role="status" class="pspp-table-feedback" data-table-feedback></p>
		<?php if ( $character_id ) : ?>
			<?php ps_pen_paper_render_characters( $campaign, $character_id, $page_id ); ?>
		<?php else : ?>
			<nav class="pspp-table-tabs" aria-label="<?php esc_attr_e( 'Spieltisch-Bereiche', 'ps-pen-paper' ); ?>">
				<span class="pspp-mobile-turn" data-table-turn><?php echo esc_html( $state['turn']['available'] ? ( $state['turn']['user_id'] ? $labels['turn'] . ' ' : '' ) . $state['turn']['name'] : $labels['unavailable'] ); ?></span>
				<a href="#pspp-play" data-table-view="play"><?php esc_html_e( 'Spieltisch', 'ps-pen-paper' ); ?></a>
				<a href="#pspp-group-adventures" data-table-view="adventures"><?php esc_html_e( 'Abenteuer spielen', 'ps-pen-paper' ); ?></a>
				<a href="#pspp-sheet" data-table-view="sheet"><?php echo esc_html( $is_gm ? __( 'Bögen', 'ps-pen-paper' ) : __( 'Mein Bogen', 'ps-pen-paper' ) ); ?></a>
				<a href="#pspp-dice" data-table-view="dice"><?php esc_html_e( 'Würfel', 'ps-pen-paper' ); ?></a>
			</nav>
			<div class="pspp-table-layout">
				<aside id="pspp-group" class="pspp-table-panel pspp-table-group" data-table-panel="group">
					<div class="pspp-party-heading"><h2><?php esc_html_e( 'Am Spieltisch', 'ps-pen-paper' ); ?></h2>
						<a href="#pspp-group" data-group-toggle data-table-view="group"><?php esc_html_e( 'Anwesenheit & Pause', 'ps-pen-paper' ); ?></a>
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
					<details class="pspp-fold">
						<summary><?php esc_html_e( 'Mitspieler zu dieser Runde einladen', 'ps-pen-paper' ); ?></summary>
						<?php ps_pen_paper_render_member_invitation_form( $campaign, $page_id ); ?>
					</details>
				</aside>
				<section id="pspp-play" class="pspp-table-panel pspp-table-stage pspp-scene" data-table-panel="play"<?php if ( ! is_wp_error( $backdrop ) && $backdrop ) :
					$backdrop_state = ps_pen_paper_get_backdrop( $campaign->ID );
					$view_style = ps_pen_paper_image_view_style( $backdrop_state['view'] ?? null, true );
					?> style="<?php echo esc_attr( '--pspp-table-image:url("' . $backdrop . '");' . $view_style ); ?>"<?php endif; ?>>
					<div class="pspp-table-prompts">
						<a href="#pspp-group-adventures"><?php esc_html_e( 'Abenteuer starten / fortsetzen', 'ps-pen-paper' ); ?></a>
						<a href="#pspp-dice"><?php esc_html_e( 'Würfeln', 'ps-pen-paper' ); ?></a>
					</div>
					<p class="description"><?php esc_html_e( 'Hier zeigt die Spielleitung die aktuelle Szene – mit Karte, wenn eine vorbereitet wurde. Für gespeicherte Abenteuerentscheidungen öffne „Abenteuer spielen“.', 'ps-pen-paper' ); ?></p>
					<?php ps_pen_paper_render_map( $campaign, ps_pen_paper_map_state( $campaign, ! $is_gm ), false, $page_id ); ?>
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
					<?php if ( $is_gm ) : ?><details class="pspp-fold"><summary><?php esc_html_e( 'Spielsitzung und Runden steuern', 'ps-pen-paper' ); ?></summary><?php ps_pen_paper_render_game_round_controls( $campaign, $page_id ); ?></details><?php endif; ?>
				</section>
				<section id="pspp-sheet" class="pspp-table-panel pspp-table-character" data-table-panel="sheet"><?php ps_pen_paper_render_table_sheet( $campaign, $page_id ); ?></section>
				<section id="pspp-group-adventures" class="pspp-table-panel pspp-table-stage" data-table-panel="adventures"><?php ps_pen_paper_render_scenario_character_links( $campaign, $page_id ); ps_pen_paper_render_adventure_groups( $campaign, $page_id ); ?></section>
				<div class="pspp-table-panel pspp-table-stage" data-table-panel="dice"><?php ps_pen_paper_render_dice( $campaign, $page_id, false ); ?></div>
				<section class="pspp-table-panel pspp-table-stage" data-table-panel="log"><?php ps_pen_paper_render_journal( $campaign, $page_id ); ?></section>
				<section id="pspp-rulebook" class="pspp-table-panel pspp-table-stage" data-table-panel="rules"><?php ps_pen_paper_render_rules( $campaign, $is_gm, $page_id ); ?></section>
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
		<?php ps_pen_paper_render_chat( $campaign, $page_id ); ?>
	</div>
	<?php
}
