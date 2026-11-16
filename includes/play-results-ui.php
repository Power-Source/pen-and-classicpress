<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_render_play_result( $round, $state, $page_id ) {
	$result = ps_pen_paper_own_play_result( $round, $state );
	?>
	<section class="pspp-card pspp-play-result" id="pspp-result" aria-labelledby="pspp-result-heading">
		<span class="pspp-eyebrow"><?php esc_html_e( 'Runde beendet', 'ps-pen-paper' ); ?></span>
		<h2 id="pspp-result-heading"><?php esc_html_e( 'Deine Abschlussübersicht', 'ps-pen-paper' ); ?></h2>
		<?php if ( is_wp_error( $result ) ) : ?><p role="alert"><?php echo esc_html( $result->get_error_message() ); ?></p>
		<?php elseif ( null === $result ) : ?><p><?php esc_html_e( 'Du hast in dieser Runde keinen eigenen Charakter gespielt. Persönliche Abrechnungen sehen die jeweiligen Spieler.', 'ps-pen-paper' ); ?></p>
		<?php else : ?>
			<p role="status"><strong><?php echo esc_html( $result['name'] . ' · ' . ( null === $result['outcome'] ?
				__( 'Beendet, ohne festgelegtes Abenteuerergebnis', 'ps-pen-paper' ) : ps_pen_paper_scenario_outcomes()[ $result['outcome'] ] ) ); ?></strong></p>
			<details class="pspp-fold"><summary><?php esc_html_e( 'Wie diese Bilanz entsteht', 'ps-pen-paper' ); ?></summary>
			<?php if ( $result['legacy'] ) : ?>
				<p><?php esc_html_e( 'Ältere Runde: Diese Übersicht zählt ausschließlich belegte Abenteuerkosten und ausgegebene Belohnungen. Weitere Änderungen an Inventar, Guthaben, Zahlenwerten oder Charakterfolgen wurden damals nicht vollständig als Abschlussstand erfasst.', 'ps-pen-paper' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Bilanz deiner Teilnahme: gespeicherte Grundwerte, Ressourcen, Inventar, Guthaben und Zustände beim Einstieg im Vergleich zum Ende. Änderungen der Leitung sind enthalten; Zeiten außerhalb deiner Teilnahme nicht. Spätere Änderungen verändern diese Abrechnung nicht.', 'ps-pen-paper' ); ?></p>
			<?php endif; ?>
			</details>
			<div class="pspp-grid">
			<?php foreach ( array( 'resource' => __( 'Ressourcen', 'ps-pen-paper' ), 'value' => __( 'Weitere Zahlenwerte', 'ps-pen-paper' ),
				'item' => __( 'Gegenstände', 'ps-pen-paper' ), 'money' => __( 'Guthaben', 'ps-pen-paper' ), 'condition' => __( 'Charakterfolgen', 'ps-pen-paper' ) ) as $kind => $label ) :
				$rows = array_filter( $result['rows'], static function ( $row ) use ( $kind ) { return $kind === $row['kind']; } ); ?>
				<div><h3><?php echo esc_html( $label ); ?></h3>
					<?php if ( $rows ) : ?><ul><?php foreach ( $rows as $row ) : ?>
						<li><?php echo esc_html( ( 'condition' === $kind ? ( $row['amount'] > 0 ? __( 'Hinzugekommen:', 'ps-pen-paper' ) : __( 'Aufgehoben:', 'ps-pen-paper' ) ) :
							( $row['amount'] > 0 ? '+' : '−' ) . number_format_i18n( abs( $row['amount'] ) ) ) . ' ' . $row['label'] ); ?></li>
					<?php endforeach; ?></ul>
					<?php else : ?><p><?php echo esc_html( in_array( $kind, $result['incomplete'], true ) ? __( 'Kein vollständiger Vergleich möglich.', 'ps-pen-paper' ) :
						( $result['legacy'] ? __( 'Keine Änderungen belegt.', 'ps-pen-paper' ) : __( 'Keine Nettoänderung.', 'ps-pen-paper' ) ) ); ?></p><?php endif; ?>
				</div>
			<?php endforeach; ?>
			</div>
			<?php foreach ( $result['notes'] as $note ) : ?><p role="note"><?php echo esc_html( $note ); ?></p><?php endforeach; ?>
		<?php endif; ?>
		<nav class="pspp-actions" aria-label="<?php esc_attr_e( 'Nach dem Spiel', 'ps-pen-paper' ); ?>">
			<a class="button button-primary" href="<?php echo esc_url( remove_query_arg( 'pspp_view', ps_pen_paper_game_url( $page_id ) ) ); ?>"><?php esc_html_e( 'Zurück zur Lounge', 'ps-pen-paper' ); ?></a>
			<?php if ( is_array( $result ) ) : ?><a class="button" href="#pspp-sheet" data-table-view="sheet"><?php esc_html_e( 'Meinen Charakterbogen ansehen', 'ps-pen-paper' ); ?></a><?php endif; ?>
		</nav>
		<p><?php esc_html_e( 'Die Runde ist bereits beendet. Die Rückkehr zur Lounge verlässt keine Kampagne und vergibt keine Belohnung erneut. Unter „Abgeschlossene Runden“ kannst du das Ergebnis wieder öffnen.', 'ps-pen-paper' ); ?></p>
	</section>
	<?php
}

function ps_pen_paper_render_completed_play_rounds( $campaigns, $page_id ) {
	?>
	<section id="pspp-lounge-results"><h2><?php esc_html_e( 'Abgeschlossene Runden', 'ps-pen-paper' ); ?></h2>
	<ul class="pspp-lounge-running">
	<?php $found = false;
	foreach ( $campaigns as $campaign ) {
		$rounds = ps_pen_paper_play_rounds( $campaign );
		if ( is_wp_error( $rounds ) ) { echo '<li role="alert">' . esc_html( $rounds->get_error_message() ) . '</li>'; continue; }
		foreach ( $rounds as $row ) {
			if ( 'completed' !== $row['state']['status'] ) { continue; }
			$own = array_filter( $row['state']['participants'], static function ( $participant ) { return $participant['user_id'] === get_current_user_id(); } );
			if ( ! $own || ! ps_pen_paper_can_view_campaign( $row['post'] ) ) { continue; }
			$found = true;
			echo '<li><strong>' . esc_html( $campaign->post_title . ' · ' . $row['post']->post_title ) . '</strong> <a class="button" href="' .
				esc_url( ps_pen_paper_play_url( $page_id, $campaign->ID, 'choose', $row['post']->ID ) . '#pspp-result' ) . '">' .
				esc_html__( 'Ergebnis ansehen', 'ps-pen-paper' ) . '</a></li>';
		}
	}
	if ( ! $found ) { echo '<li>' . esc_html__( 'Noch keine abgeschlossene Runde mit deinem Charakter.', 'ps-pen-paper' ) . '</li>'; }
	?>
	</ul></section>
	<?php
}
