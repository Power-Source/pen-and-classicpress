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

function ps_pen_paper_render_event_details( $event ) {
	?>
	<?php if ( ! in_array( $event['kind'], array( 'universe_saved', 'universe_linked' ), true ) ) : ?>
		<p><?php echo esc_html( sprintf( __( 'Objekt #%1$d · Regelrevision %2$d', 'ps-pen-paper' ), $event['object_id'], $event['rules_revision'] ) ); ?></p>
	<?php endif; ?>
	<div class="pspp-grid">
		<?php foreach ( array( 'before' => __( 'Vorher', 'ps-pen-paper' ), 'after' => __( 'Nachher', 'ps-pen-paper' ) ) as $key => $label ) : ?>
			<section class="pspp-snapshot">
				<h4><?php echo esc_html( $label . ' · ' . sprintf( __( 'Revision %d', 'ps-pen-paper' ), $event[ $key ]['revision'] ) ); ?></h4>
				<?php if ( in_array( $event['kind'], array( 'scene_saved', 'scene_published', 'scene_withdrawn', 'map_moved' ), true ) ) : ?>
					<p><?php echo esc_html( $event[ $key ]['draft']['title'] ); ?></p>
					<p class="pspp-multiline"><?php echo esc_html( $event[ $key ]['draft']['text'] ); ?></p>
					<p class="pspp-multiline"><?php echo esc_html( $event[ $key ]['draft']['secret'] ); ?></p>
					<pre class="pspp-map-history"><?php echo esc_html( wp_json_encode( array( 'draft_tokens' => $event[ $key ]['draft']['tokens'], 'published' => $event[ $key ]['published'] ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
				<?php elseif ( 'scene_selected' === $event['kind'] ) : ?>
					<p><?php echo esc_html( $event[ $key ]['name'] ); ?></p>
				<?php elseif ( 'universe_saved' === $event['kind'] ) : ?>
					<p><?php echo esc_html( $event[ $key ]['name'] ); ?></p>
					<p class="pspp-multiline"><?php echo esc_html( $event[ $key ]['description'] ); ?></p>
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
	$log = is_wp_error( $page ) ? $page : ps_pen_paper_get_events( $campaign, $page );
	$base_url = $page_id ? ps_pen_paper_game_url( $page_id, $campaign->ID ) : ps_pen_paper_admin_url( $campaign->ID );
	$labels = ps_pen_paper_event_labels();
	$visibility_labels = ps_pen_paper_roll_visibility_labels();
	?>
	<h3 id="pspp-log"><?php esc_html_e( 'Spielprotokoll', 'ps-pen-paper' ); ?></h3>
	<p><?php esc_html_e( 'Würfe, Charakteränderungen, Freigaben und Regeländerungen. Du siehst nur Einträge, für die du berechtigt bist. Der Änderungsverlauf beginnt mit der Einführung dieser Funktion.', 'ps-pen-paper' ); ?></p>
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
						if ( in_array( $event['kind'], array( 'scene_saved', 'scene_published', 'scene_withdrawn', 'map_moved' ), true ) ) {
							$snapshot = array( 'name' => $event['after']['draft']['title'] );
						} elseif ( 'scene_selected' === $event['kind'] ) {
							$snapshot = $event['after'];
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
						<details><summary><?php esc_html_e( 'Änderung ansehen: Vorher / Nachher', 'ps-pen-paper' ); ?></summary><?php ps_pen_paper_render_event_details( $event ); ?></details>
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
