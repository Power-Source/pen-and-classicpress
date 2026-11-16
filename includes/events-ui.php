<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_render_rule_snapshot( $rules ) {
	if ( 0 === $rules['revision'] ) {
		echo '<p>' . esc_html__( 'Noch kein Regelwerk eingerichtet.', 'ps-pen-paper' ) . '</p>';
		return;
	}
	?>
	<h4><?php echo esc_html( $rules['name'] ); ?></h4>
	<p class="pspp-multiline"><?php echo esc_html( $rules['description'] ); ?></p>
	<p><?php echo esc_html( __( 'Erlaubte Würfel:', 'ps-pen-paper' ) . ' ' . ( $rules['dice'] ? 'W' . implode( ', W', $rules['dice'] ) : __( 'Keine', 'ps-pen-paper' ) ) ); ?></p>
	<p><?php echo esc_html( ps_pen_paper_character_modes()[ ps_pen_paper_character_mode( $rules ) ] ); ?></p>
	<p><?php echo esc_html( __( 'Wurfsichtbarkeiten:', 'ps-pen-paper' ) . ' ' . implode( ', ', array_intersect_key( ps_pen_paper_roll_visibility_labels(), array_flip( ps_pen_paper_allowed_roll_visibilities( $rules ) ) ) ) ); ?></p>
	<dl class="pspp-character-values">
		<?php foreach ( $rules['fields'] as $field ) : ?>
			<dt><?php echo esc_html( $field['label'] . ' · ' . ps_pen_paper_field_types()[ $field['type'] ] ); ?></dt>
			<dd class="pspp-multiline">
				<?php echo esc_html( $field['description'] ); ?>
				<?php if ( $field['required'] ) : ?><br><?php esc_html_e( 'Pflichtfeld', 'ps-pen-paper' ); ?><?php endif; ?>
				<?php if ( isset( $field['min'] ) ) : ?>
					<br><?php echo esc_html( sprintf( __( 'Minimum: %1$d · Maximum: %2$d · Startwert: %3$d', 'ps-pen-paper' ), $field['min'], $field['max'], $field['start'] ) ); ?>
				<?php endif; ?>
				<br><small><?php echo esc_html( __( 'Feldkennung:', 'ps-pen-paper' ) . ' ' . $field['id'] ); ?></small>
			</dd>
		<?php endforeach; ?>
	</dl>
	<?php
}

function ps_pen_paper_render_event_details( $event, $scope = null ) {
	?>
	<?php if ( 'character_resource_spent' === $event['kind'] ) : ?><p><?php echo esc_html( ps_pen_paper_adventure_cost_label( $event['resource_cost'] ) ); ?></p><?php endif; ?>
	<?php if ( 'wallet_saved' === $event['kind'] ) : ?><p class="pspp-multiline"><?php echo esc_html( $event['reason'] ); ?></p><?php endif; ?>
	<?php if ( ! in_array( $event['kind'], array( 'universe_saved', 'universe_linked', 'membership_saved' ), true ) ) : ?>
		<p><?php echo esc_html( sprintf( __( 'Objekt #%1$d · Regelrevision %2$d', 'ps-pen-paper' ), $event['object_id'], $event['rules_revision'] ) ); ?></p>
	<?php endif; ?>
	<div class="pspp-grid">
		<?php foreach ( array( 'before' => __( 'Vorher', 'ps-pen-paper' ), 'after' => __( 'Nachher', 'ps-pen-paper' ) ) as $key => $label ) : ?>
			<section class="pspp-snapshot">
				<h4><?php echo esc_html( $label . ' · ' . sprintf( __( 'Revision %d', 'ps-pen-paper' ), $event[ $key ]['revision'] ) ); ?></h4>
				<?php if ( in_array( $event['kind'], array( 'trade_policy', 'trade_changed' ), true ) ) : ?>
					<?php ps_pen_paper_render_trade_snapshot( $event[ $key ] ); ?>
				<?php elseif ( 'play_options' === $event['kind'] ) : ?>
					<p><?php echo esc_html( implode( ', ', array_intersect_key( ps_pen_paper_play_modes(), array_flip( $event[ $key ]['modes'] ) ) ) ); ?></p>
					<p><?php echo esc_html( $event[ $key ]['player_starts'] ? __( 'Spieler dürfen neue Runden beginnen.', 'ps-pen-paper' ) : __( 'Neue Runden beginnt die Spielleitung.', 'ps-pen-paper' ) ); ?></p>
				<?php elseif ( 'play_round' === $event['kind'] ) : ?>
					<?php if ( $event[ $key ]['revision'] ) : ?>
						<p><?php echo esc_html( ps_pen_paper_play_modes()[ $event[ $key ]['mode'] ] . ' · ' . ps_pen_paper_play_statuses()[ $event[ $key ]['status'] ] ); ?></p>
					<?php else : ?>
						<p><?php esc_html_e( 'Noch keine Runde angelegt.', 'ps-pen-paper' ); ?></p>
					<?php endif; ?>
				<?php elseif ( 'invitation_saved' === $event['kind'] ) : ?>
					<p><?php echo esc_html( __( 'Einladungen', 'ps-pen-paper' ) . ': ' . implode( ', ', $event[ $key ]['users'] ) ); ?></p>
				<?php elseif ( 'wiki_links_saved' === $event['kind'] ) : ?>
					<ul><?php foreach ( $event[ $key ]['links'] as $target => $entry_id ) : ?><li><?php echo esc_html( $target . ' → #' . $entry_id ); ?></li><?php endforeach; ?></ul>
				<?php elseif ( 'campaign_restored' === $event['kind'] ) : ?>
					<?php if ( $event[ $key ]['revision'] ) : ?>
						<p><?php echo esc_html( __( 'Sicherungs-ID:', 'ps-pen-paper' ) . ' ' . $event[ $key ]['backup_id'] ); ?></p>
						<p><?php echo esc_html( 'SHA-256: ' . $event[ $key ]['source_hash'] ); ?></p>
					<?php else : ?>
						<p><?php esc_html_e( 'Noch keine Wiederherstellung.', 'ps-pen-paper' ); ?></p>
					<?php endif; ?>
				<?php elseif ( 'adventure_group' === $event['kind'] ) : ?>
					<?php $group = $event[ $key ]; ?>
					<?php if ( $group['revision'] ) : ?>
						<p><?php echo esc_html( $group['title'] . ' · ' . ps_pen_paper_play_statuses()[ $group['status'] ] ); ?></p>
						<p><?php echo esc_html( ps_pen_paper_adventure_group_rules()[ $group['rule'] ] ); ?></p>
						<?php if ( $group['scene_id'] ) : ?>
							<p><?php echo esc_html( __( 'Aktuelle Szene:', 'ps-pen-paper' ) . ' ' . $group['snapshot']['scenes'][ $group['scene_id'] ]['title'] ); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<p><?php esc_html_e( 'Noch kein Durchlauf.', 'ps-pen-paper' ); ?></p>
					<?php endif; ?>
				<?php elseif ( 'character_resource_spent' === $event['kind'] ) : ?>
					<?php
					$snapshot = $event[ $key ]['active'];
					$snapshot['values'][ $event['resource_cost']['id'] ] = array(
						'current' => $event['resource_cost'][ $key ], 'maximum' => $event['resource_cost']['maximum'],
					);
					ps_pen_paper_render_character_snapshot( $snapshot, array( 'fields' => $event['fields'] ) );
					?>
				<?php elseif ( in_array( $event['kind'], array( 'adventure_saved', 'adventure_published' ), true ) ) : ?>
					<?php $adventure = $event[ $key ]; ?>
					<p><?php echo esc_html( $adventure['draft']['title'] . ' · ' . array( 'draft' => __( 'Entwurf', 'ps-pen-paper' ), 'published' => __( 'Freigegeben', 'ps-pen-paper' ), 'paused' => __( 'Pausiert', 'ps-pen-paper' ) )[ $adventure['status'] ] ); ?></p>
					<p><?php echo esc_html( __( 'Startszene:', 'ps-pen-paper' ) . ' ' . $adventure['draft']['start_scene'] . ' · ' . __( 'Entscheidungen:', 'ps-pen-paper' ) . ' ' . count( $adventure['draft']['edges'] ) ); ?></p>
				<?php elseif ( 'adventure_run' === $event['kind'] ) : ?>
					<?php
					$run = $event[ $key ];
					if ( 0 === $run['revision'] ) { echo '<p>' . esc_html__( 'Noch kein Durchlauf.', 'ps-pen-paper' ) . '</p>'; }
					else {
					echo '<p>' . esc_html( $run['snapshot']['title'] . ' · ' . __( 'Charakter:', 'ps-pen-paper' ) . ' #' . $run['character_id'] . ' · ' .
						( 'completed' === $run['status'] ? __( 'Abgeschlossen', 'ps-pen-paper' ) : __( 'Läuft', 'ps-pen-paper' ) ) ) . '</p>';
					if ( $run['scene_id'] && isset( $run['snapshot']['scenes'][ $run['scene_id'] ] ) ) {
						echo '<p>' . esc_html( __( 'Aktuelle Szene:', 'ps-pen-paper' ) . ' ' . $run['snapshot']['scenes'][ $run['scene_id'] ]['title'] ) . '</p>';
					}
					if ( $run['history'] ) {
						$step = end( $run['history'] );
						echo '<p>' . esc_html( $step['choice'] . ( $step['roll'] ? ' · ' . $step['roll']['total'] . ' / ' . $step['roll']['threshold'] : '' ) ) . '</p>';
						if ( isset( $step['requirement'] ) ) { echo '<p>' . esc_html( ps_pen_paper_adventure_requirement_label( $step['requirement'] ) ) . '</p>'; }
						if ( isset( $step['conditions'] ) ) { echo '<p>' . esc_html( ps_pen_paper_adventure_conditions_label( $step['conditions'] ) ) . '</p>'; }
						if ( array_key_exists( 'resource_cost', $step ) ) { echo '<p>' . esc_html( $step['resource_cost'] ? ps_pen_paper_adventure_cost_label( $step['resource_cost'] ) : __( 'Bei Misserfolg keine Kosten', 'ps-pen-paper' ) ) . '</p>'; }
						if ( array_key_exists( 'reward', $step ) ) { echo '<p>' . esc_html( ps_pen_paper_adventure_reward_history_label( $run['snapshot'], $step ) ) . '</p>'; }
						if ( isset( $step['reaction'] ) ) { echo '<p>' . esc_html( ps_pen_paper_adventure_reaction_label( $step['reaction'] ) ) . '</p>'; }
					}
					}
					?>
				<?php elseif ( in_array( $event['kind'], array( 'wiki_configured', 'wiki_prepared', 'wiki_published' ), true ) ) : ?>
					<?php ps_pen_paper_render_wiki_snapshot( $event[ $key ] ); ?>
				<?php elseif ( in_array( $event['kind'], array( 'loot_table_saved', 'loot_rolled', 'loot_awarded' ), true ) ) : ?>
					<?php ps_pen_paper_render_loot_snapshot( $event[ $key ], 'loot_table_saved' === $event['kind'] ); ?>
				<?php elseif ( in_array( $event['kind'], array( 'npc_saved', 'npc_offered', 'npc_approved', 'npc_copied' ), true ) ) : ?>
					<?php
					if ( ! $scope && isset( $event['id'] ) ) {
						$record = get_post( $event['id'] );
						$scope = $record ? ps_pen_paper_get_character_scope( $record->post_parent ) : null;
					}
					if ( ! $scope ) { echo '<p role="alert">' . esc_html__( 'Der Figurenverlauf ist nicht verfügbar.', 'ps-pen-paper' ) . '</p>'; }
					elseif ( $event[ $key ]['npc'] ) { ps_pen_paper_render_npc_card( $scope, $event[ $key ] ); }
					else { echo '<p>' . esc_html__( 'Noch keine Figur vorhanden.', 'ps-pen-paper' ) . '</p>'; }
					?>
				<?php elseif ( in_array( $event['kind'], array( 'currency_saved', 'wallet_saved' ), true ) ) : ?>
					<?php ps_pen_paper_render_economy_history( $event['kind'], $event[ $key ] ); ?>
				<?php elseif ( 'membership_saved' === $event['kind'] ) : ?>
					<?php
					$member = $event[ 'member_' . $key ];
					$roles = array( '' => __( 'Kein Kampagnenmitglied', 'ps-pen-paper' ), 'gm' => __( 'Spielleiter', 'ps-pen-paper' ), 'player' => __( 'Spieler', 'ps-pen-paper' ) );
					$requests = array( '' => __( 'Keine Anfrage', 'ps-pen-paper' ), 'pending' => __( 'Offene Anfrage', 'ps-pen-paper' ), 'approved' => __( 'Anfrage angenommen', 'ps-pen-paper' ), 'rejected' => __( 'Anfrage abgelehnt', 'ps-pen-paper' ) );
					?>
					<p><?php echo esc_html( $event['member_name'] . ' · #' . $event['member_id'] ); ?></p>
					<p><?php echo esc_html( $roles[ $member['role'] ] . ' · ' . $requests[ $member['request'] ] ); ?></p>
				<?php elseif ( in_array( $event['kind'], array( 'portrait_saved', 'sheet_style_saved', 'equipment_schema', 'item_saved', 'gear_saved', 'game_round', 'item_action_policy' ), true ) ) : ?>
					<?php ps_pen_paper_render_equipment_history( $event['kind'], $event[ $key ] ); ?>
				<?php elseif ( in_array( $event['kind'], array( 'scene_saved', 'scene_published', 'scene_withdrawn', 'map_moved' ), true ) ) : ?>
					<?php foreach ( array( 'draft' => __( 'Entwurf', 'ps-pen-paper' ), 'published' => __( 'Freigegebener Stand', 'ps-pen-paper' ) ) as $scope => $heading ) :
						$scene = $event[ $key ][ $scope ];
						?>
						<h5><?php echo esc_html( $heading ); ?></h5>
						<?php if ( null === $scene ) : ?>
							<p><?php esc_html_e( 'Nicht freigegeben', 'ps-pen-paper' ); ?></p>
						<?php else : ?>
							<p><strong><?php echo esc_html( $scene['title'] ); ?></strong></p>
							<p class="pspp-multiline"><?php echo esc_html( $scene['text'] ); ?></p>
							<?php if ( isset( $scene['secret'] ) && '' !== $scene['secret'] ) : ?><p class="pspp-multiline"><?php echo esc_html( __( 'Geheimnisse – nur Spielleitung', 'ps-pen-paper' ) . ': ' . $scene['secret'] ); ?></p><?php endif; ?>
							<p><?php echo esc_html( __( 'Kartenbild:', 'ps-pen-paper' ) . ' #' . $scene['asset_id'] . ' · ' . __( 'Raster:', 'ps-pen-paper' ) . ' ' . $scene['grid'] ); ?></p>
							<ul><?php foreach ( $scene['tokens'] as $token ) : ?><li><?php echo esc_html( $token['name'] . ' · X ' . $token['x'] / 100 . ' % · Y ' . $token['y'] / 100 . ' %' ); ?></li><?php endforeach; ?></ul>
						<?php endif; ?>
					<?php endforeach; ?>
				<?php elseif ( in_array( $event['kind'], array( 'folio_saved', 'folio_published', 'folio_withdrawn' ), true ) ) : ?>
					<?php foreach ( array( 'draft' => __( 'Privater Entwurf', 'ps-pen-paper' ), 'published' => __( 'Freigegebenes Handout', 'ps-pen-paper' ) ) as $scope => $heading ) : $entry = $event[ $key ][ $scope ]; ?>
						<h5><?php echo esc_html( $heading ); ?></h5>
						<?php if ( ! $entry ) : ?><p><?php esc_html_e( 'Nicht freigegeben', 'ps-pen-paper' ); ?></p>
						<?php else : ?>
							<p><strong><?php echo esc_html( $entry['title'] ); ?></strong></p>
							<p class="pspp-multiline"><?php echo esc_html( $entry['text'] ); ?></p>
							<?php if ( isset( $entry['secret'] ) ) : ?><p class="pspp-multiline"><?php echo esc_html( $entry['secret'] ); ?></p>
								<p><?php echo esc_html( ps_pen_paper_folio_types()[ $entry['type'] ] . ' · ' . __( 'Sortiernummer:', 'ps-pen-paper' ) . ' ' . $entry['order'] ); ?></p>
								<p><?php echo esc_html( __( 'Kapitel / Szene:', 'ps-pen-paper' ) . ' ' . $entry['chapter_id'] . ' / ' . $entry['scene_id'] ); ?></p>
								<p><?php echo esc_html( __( 'Szenenfolge:', 'ps-pen-paper' ) . ' ' . implode( ', ', $entry['scene_ids'] ) ); ?></p>
							<?php endif; ?>
							<p><?php echo esc_html( __( 'Geschütztes Bild:', 'ps-pen-paper' ) . ' ' . $entry['asset_id'] ); ?></p>
						<?php endif; ?>
					<?php endforeach; ?>
				<?php elseif ( 'scene_selected' === $event['kind'] ) : ?>
					<p><?php echo esc_html( $event[ $key ]['name'] ); ?></p>
				<?php elseif ( 'universe_saved' === $event['kind'] ) : ?>
					<p><?php echo esc_html( $event[ $key ]['name'] ); ?></p>
					<p class="pspp-multiline"><?php echo esc_html( $event[ $key ]['description'] ); ?></p>
				<?php elseif ( 'character_policy_saved' === $event['kind'] ) : ?>
					<p><?php echo esc_html( sprintf( __( 'Maximal %d Charaktere je Spieler', 'ps-pen-paper' ), $event[ $key ]['max_characters'] ) ); ?></p>
					<p><?php echo esc_html( 'members' === $event[ $key ]['creation_access'] ? __( 'Erstellung nur für Mitglieder', 'ps-pen-paper' ) : __( 'Erstellung für Website-Mitglieder', 'ps-pen-paper' ) ); ?></p>
					<p><?php echo esc_html( $event[ $key ]['custom_portraits'] ? __( 'Eigene Charakterbilder erlaubt', 'ps-pen-paper' ) : __( 'Eigene Charakterbilder deaktiviert', 'ps-pen-paper' ) ); ?></p>
					<p><?php echo esc_html( 'approval' === $event[ $key ]['member_invites'] ? __( 'Spielereinladungen benötigen Freigabe', 'ps-pen-paper' ) : __( 'Direkte Spielereinladungen erlaubt', 'ps-pen-paper' ) ); ?></p>
					<ul><?php foreach ( $event[ $key ]['presets'] as $preset ) : ?><li><?php echo esc_html( $preset['name'] . ' · #' . $preset['asset_id'] ); ?></li><?php endforeach; ?></ul>
				<?php elseif ( 'member_invitation_saved' === $event['kind'] ) : ?>
					<ul><?php foreach ( $event[ $key ]['entries'] as $entry ) : ?><li><?php echo esc_html( '#' . $entry['user_id'] . ' · ' . __( 'Eingeladen von', 'ps-pen-paper' ) . ' #' . $entry['inviter_id'] . ' · ' . ( 'approval' === $entry['status'] ? __( 'Freigabe ausstehend', 'ps-pen-paper' ) : __( 'Einladung offen', 'ps-pen-paper' ) ) ); ?></li><?php endforeach; ?></ul>
				<?php elseif ( 'universe_members_saved' === $event['kind'] ) : ?>
					<p><?php echo esc_html( __( 'Mitglieder:', 'ps-pen-paper' ) . ' ' . implode( ', ', $event[ $key ]['users'] ) ); ?></p>
				<?php elseif ( 'universe_linked' === $event['kind'] ) : ?>
					<p><?php echo esc_html( $event[ $key ]['universe_id'] ? $event[ $key ]['name'] : __( 'Ohne Universum', 'ps-pen-paper' ) ); ?></p>
				<?php elseif ( 'turn_changed' === $event['kind'] ) : ?>
					<p><?php echo esc_html( $event[ $key ]['name'] ); ?></p>
				<?php elseif ( 'access_saved' === $event['kind'] ) : ?>
					<p><?php echo esc_html( 'teaser' === $event[ $key ]['visibility'] ? __( 'Öffentlicher Teaser', 'ps-pen-paper' ) : __( 'Geschlossene Kampagne', 'ps-pen-paper' ) ); ?></p>
					<p><?php echo esc_html( 'approval' === $event[ $key ]['admission'] ? __( 'Anfragen mit Freigabe', 'ps-pen-paper' ) : __( 'Persönliche Aufnahme', 'ps-pen-paper' ) ); ?></p>
					<p class="pspp-multiline"><?php echo esc_html( $event[ $key ]['teaser'] ); ?></p>
				<?php elseif ( 'rules_saved' === $event['kind'] ) : ?>
					<?php ps_pen_paper_render_rule_snapshot( $event[ $key ] ); ?>
				<?php elseif ( 'character_context' === $event['kind'] ) : ?>
					<?php ps_pen_paper_render_character_snapshot( $event[ 'effective_' . $key ], array( 'fields' => $event['fields'] ) ); ?>
					<p class="pspp-multiline"><?php echo esc_html( $event[ $key ]['context']['note'] ); ?></p>
				<?php else : ?>
					<p><strong><?php esc_html_e( 'Gültiger Stand', 'ps-pen-paper' ); ?></strong></p>
					<?php ps_pen_paper_render_character_snapshot( $event[ $key ]['active'], array( 'fields' => $event['fields'] ) ); ?>
					<?php if ( $event[ $key ]['pending'] ) : ?>
						<p><strong><?php esc_html_e( 'Offener Antrag', 'ps-pen-paper' ); ?></strong></p>
						<?php ps_pen_paper_render_character_snapshot( $event[ $key ]['pending'], array( 'fields' => $event['fields'] ) ); ?>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( isset( $event[ $key ]['context'] ) ) : ?>
					<p><?php echo esc_html( ps_pen_paper_character_context_modes()[ $event[ $key ]['context']['mode'] ] ); ?></p>
					<dl>
						<?php foreach ( $event['fields'] as $field ) : ?>
							<?php if ( ! array_key_exists( $field['id'], $event[ $key ]['context']['overrides'] ) ) { continue; } ?>
							<dt><?php echo esc_html( $field['label'] ); ?></dt><dd><?php echo esc_html( $event[ $key ]['context']['overrides'][ $field['id'] ] ); ?></dd>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>
	<?php
}

function ps_pen_paper_render_journal( $campaign, $page_id = 0 ) {
	$page = ps_pen_paper_frontend_id( 'pspp_log_page' );
	$page = $page ? $page : 1;
	$log = is_wp_error( $page ) ? $page : ps_pen_paper_get_events( $campaign, $page, ! ps_pen_paper_management_view( $page_id ) );
	$base_url = $page_id ? ps_pen_paper_game_url( $page_id, $campaign->ID ) : ps_pen_paper_admin_url( $campaign->ID );
	$labels = ps_pen_paper_event_labels();
	$visibility_labels = ps_pen_paper_roll_visibility_labels();
	?>
	<h3 id="pspp-log"><?php esc_html_e( 'Spielprotokoll', 'ps-pen-paper' ); ?></h3>
	<p><?php esc_html_e( 'Würfe, Charakteränderungen, Freigaben, Regeländerungen und geschützte Mitgliederverwaltung. Du siehst nur Einträge, für die du berechtigt bist. Der jeweilige Änderungsverlauf beginnt mit der Einführung seiner Protokollierung.', 'ps-pen-paper' ); ?></p>
	<?php if ( is_wp_error( $log ) ) : ?>
		<p role="alert"><?php echo esc_html( $log->get_error_message() ); ?></p>
	<?php elseif ( ! $log['events'] ) : ?>
		<p><?php esc_html_e( 'Diese Protokollseite enthält keine für dich sichtbaren Ereignisse.', 'ps-pen-paper' ); ?></p>
	<?php else : ?>
		<ol class="pspp-roll-log">
			<?php foreach ( $log['events'] as $event ) : ?>
				<li class="pspp-card">
					<p><strong><?php echo esc_html( $event['actor_name'] ); ?></strong> · <?php echo esc_html( $event['date'] ); ?><br>
						<?php echo esc_html( 'owner' === $event['visibility'] ? __( 'Privat: Charakterbesitzer und Spielleiter', 'ps-pen-paper' ) : $visibility_labels[ $event['visibility'] ] ); ?></p>
					<?php if ( 'roll' === $event['kind'] ) : ?>
						<?php if ( $event['label'] ) : ?><p><?php echo esc_html( $event['label'] ); ?></p><?php endif; ?>
						<p><a href="<?php echo esc_url( add_query_arg( array( 'pspp_roll' => $event['id'], 'pspp_log_page' => $page ), $base_url ) . '#pspp-dice' ); ?>"><?php echo esc_html( ps_pen_paper_roll_description( $event ) ); ?></a>
							<strong><?php echo esc_html( '= ' . $event['total'] ); ?></strong></p>
						<details><summary><?php esc_html_e( 'Wurfdetails', 'ps-pen-paper' ); ?></summary><?php ps_pen_paper_render_roll_details( $event ); ?></details>
					<?php else : ?>
						<p><strong><?php echo esc_html( $labels[ $event['kind'] ] ); ?></strong></p>
						<?php
						if ( in_array( $event['kind'], array( 'trade_policy', 'trade_changed' ), true ) ) {
							$snapshot = array( 'name' => __( 'Handel', 'ps-pen-paper' ) );
						} elseif ( in_array( $event['kind'], array( 'adventure_saved', 'adventure_published' ), true ) ) {
							$snapshot = array( 'name' => $event['after']['draft']['title'] );
						} elseif ( 'adventure_run' === $event['kind'] ) {
							$snapshot = array( 'name' => $event['after']['snapshot']['title'] );
						} elseif ( 'adventure_group' === $event['kind'] ) {
							$snapshot = array( 'name' => $event['after']['title'] );
						} elseif ( 'character_context' === $event['kind'] ) {
							$snapshot = $event['effective_after'];
						} elseif ( in_array( $event['kind'], array( 'wiki_configured', 'wiki_prepared', 'wiki_published' ), true ) ) {
							$snapshot = array( 'name' => 'wiki_configured' === $event['kind'] ? $event['after']['name'] : $event['after']['title'] );
						} elseif ( in_array( $event['kind'], array( 'loot_table_saved', 'loot_rolled', 'loot_awarded' ), true ) ) {
							$snapshot = array( 'name' => 'loot_table_saved' === $event['kind'] ? $event['after']['table']['name'] : $event['after']['proposal']['table']['table']['name'] );
						} elseif ( in_array( $event['kind'], array( 'npc_saved', 'npc_offered', 'npc_approved', 'npc_copied' ), true ) ) {
							$snapshot = array( 'name' => $event['after']['npc']['name'] );
						} elseif ( 'wallet_saved' === $event['kind'] ) {
							$snapshot = array( 'name' => $event['character_name'] );
						} elseif ( 'currency_saved' === $event['kind'] ) {
							$snapshot = array( 'name' => $event['after']['name'] );
						} elseif ( in_array( $event['kind'], array( 'play_options', 'play_round', 'portrait_saved', 'sheet_style_saved', 'equipment_schema', 'item_saved', 'gear_saved', 'game_round', 'item_action_policy', 'character_policy_saved', 'member_invitation_saved', 'universe_members_saved', 'invitation_saved', 'wiki_links_saved', 'campaign_restored' ), true ) ) {
							$snapshot = array( 'name' => $labels[ $event['kind'] ] );
						} elseif ( in_array( $event['kind'], array( 'scene_saved', 'scene_published', 'scene_withdrawn', 'map_moved' ), true ) ) {
							$snapshot = array( 'name' => $event['after']['draft']['title'] );
						} elseif ( in_array( $event['kind'], array( 'folio_saved', 'folio_published', 'folio_withdrawn' ), true ) ) {
							$snapshot = array( 'name' => $event['after']['draft']['title'] );
						} elseif ( 'scene_selected' === $event['kind'] ) {
							$snapshot = $event['after'];
						} elseif ( 'membership_saved' === $event['kind'] ) {
							$snapshot = array( 'name' => $event['member_name'] );
						} elseif ( 'access_saved' === $event['kind'] ) {
							$snapshot = array( 'name' => __( 'Zugang & Privatsphäre', 'ps-pen-paper' ) );
						} elseif ( 'universe_linked' === $event['kind'] ) {
							$snapshot = array( 'name' => $event['after']['universe_id'] ? $event['after']['name'] : __( 'Ohne Universum', 'ps-pen-paper' ) );
						} elseif ( in_array( $event['kind'], array( 'rules_saved', 'turn_changed', 'universe_saved' ), true ) ) {
							$snapshot = $event['after'];
						} else {
							$snapshot = $event['after']['active'] ? $event['after']['active'] : ( $event['after']['pending'] ? $event['after']['pending'] : $event['before']['pending'] );
						}
						?>
						<p><?php echo esc_html( $snapshot['name'] . ' · ' . sprintf( __( 'Revision %1$d → %2$d', 'ps-pen-paper' ), $event['before']['revision'], $event['after']['revision'] ) ); ?></p>
						<details><summary><?php esc_html_e( 'Änderung ansehen: Vorher / Nachher', 'ps-pen-paper' ); ?></summary><?php ps_pen_paper_render_event_details( $event, $campaign ); ?></details>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
		<?php if ( $log['pages'] > 1 ) : ?>
			<nav class="pspp-navigation" aria-label="<?php esc_attr_e( 'Protokollseiten', 'ps-pen-paper' ); ?>">
				<?php if ( $page > 1 ) : ?><a href="<?php echo esc_url( add_query_arg( 'pspp_log_page', $page - 1, $base_url ) . '#pspp-log' ); ?>"><?php esc_html_e( 'Neuere Ereignisse', 'ps-pen-paper' ); ?></a><?php endif; ?>
				<?php if ( $page < $log['pages'] ) : ?><a href="<?php echo esc_url( add_query_arg( 'pspp_log_page', $page + 1, $base_url ) . '#pspp-log' ); ?>"><?php esc_html_e( 'Ältere Ereignisse', 'ps-pen-paper' ); ?></a><?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
	<?php
}
