<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_admin_sections() {
	return array(
		'ps-pen-paper' => __( 'Übersicht', 'ps-pen-paper' ),
		'ps-pen-paper-universes' => __( 'Universen', 'ps-pen-paper' ),
		'ps-pen-paper-campaigns' => __( 'Kampagnen', 'ps-pen-paper' ),
		'ps-pen-paper-characters' => __( 'Charakterverwaltung', 'ps-pen-paper' ),
		'ps-pen-paper-settings' => __( 'Einstellungen', 'ps-pen-paper' ),
	);
}

function ps_pen_paper_dashboard_url( $section, $page_id = 0 ) {
	return $page_id
		? add_query_arg( 'pspp_view', $section, get_permalink( $page_id ) )
		: add_query_arg( 'page', 'overview' === $section ? 'ps-pen-paper' : 'ps-pen-paper-' . $section, admin_url( 'admin.php' ) );
}

function ps_pen_paper_render_dashboard_navigation( $page_id = 0 ) {
	?>
	<nav class="pspp-navigation" aria-label="<?php esc_attr_e( 'Verwaltungsbereiche', 'ps-pen-paper' ); ?>">
		<?php foreach ( array( 'overview' => __( 'Übersicht', 'ps-pen-paper' ), 'universes' => __( 'Universen', 'ps-pen-paper' ), 'campaigns' => __( 'Kampagnen', 'ps-pen-paper' ), 'characters' => __( 'Charakterverwaltung', 'ps-pen-paper' ), 'settings' => __( 'Einstellungen', 'ps-pen-paper' ) ) as $section => $label ) : ?>
			<a href="<?php echo esc_url( ps_pen_paper_dashboard_url( $section, $page_id ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php
}

function ps_pen_paper_render_dashboard( $page_id = 0 ) {
	?>
	<h2><?php esc_html_e( 'Vorbereiten & verwalten', 'ps-pen-paper' ); ?></h2>
	<?php $game_page = ps_pen_paper_find_frontend_page( $page_id ); ?>
	<?php if ( $game_page ) : ?><p><a href="<?php echo esc_url( ps_pen_paper_game_url( $game_page->ID ) ); ?>"><?php esc_html_e( 'Zur Game-Lounge', 'ps-pen-paper' ); ?></a></p><?php endif; ?>
	<?php ps_pen_paper_render_booking_agenda( $game_page ? $game_page->ID : 0 ); ?>
	<?php
}

function ps_pen_paper_render_character_library( $page_id = 0 ) {
	?>
	<h2><?php esc_html_e( 'Charakterverwaltung', 'ps-pen-paper' ); ?></h2>
	<p><?php esc_html_e( 'Hier erscheinen deine eigenen Kampagnenbögen sowie die Bögen der Kampagnen und Universen, die du leitest. Administratoren sehen alle Bögen dieser Website. Basisbögen werden durch die Universumsleitung verwaltet.', 'ps-pen-paper' ); ?></p>
	<div class="pspp-grid">
		<?php $found = false; ?>
		<?php foreach ( array_merge( ps_pen_paper_get_campaigns(), ps_pen_paper_get_universes() ) as $campaign ) : ?>
			<?php foreach ( ps_pen_paper_get_characters( $campaign ) as $character ) :
				$state = ps_pen_paper_character_state( $campaign, $character );
				if ( is_wp_error( $state ) ) {
					echo '<p role="alert">' . esc_html( $state->get_error_message() ) . '</p>';
					continue;
				}
				$scope_name = $campaign->post_title;
				if ( 'pspp_universe' === $campaign->post_type ) {
					$document = ps_pen_paper_universe_document( $campaign->ID );
					if ( is_wp_error( $document ) ) {
						echo '<p role="alert">' . esc_html( $document->get_error_message() ) . '</p>';
						continue;
					}
					$scope_name = $document['name'];
				}
				$snapshot = is_array( $state ) && ! empty( $state['active'] ) ? $state['active'] : ( is_array( $state ) && ! empty( $state['pending'] ) ? $state['pending'] : null );
				$found = true;
				if ( ! is_array( $snapshot ) || ! isset( $snapshot['name'] ) || ! is_string( $snapshot['name'] ) ) {
					echo '<p role="alert">' . esc_html__( 'Ein Charakterstand ist unvollständig. Bitte informiere die Spielleitung.', 'ps-pen-paper' ) . '</p>';
					continue;
				}
				?>
				<article class="pspp-card">
					<span class="pspp-eyebrow"><?php echo esc_html( $scope_name ); ?></span>
					<h3><a href="<?php echo esc_url( ps_pen_paper_character_url( $campaign->ID, $character->ID, $page_id ) ); ?>"><?php echo esc_html( $snapshot['name'] ); ?></a></h3>
					<?php if ( $state['pending'] ) : ?><p><?php esc_html_e( 'Freigabe ausstehend', 'ps-pen-paper' ); ?></p><?php endif; ?>
				</article>
			<?php endforeach; ?>
		<?php endforeach; ?>
	</div>
	<?php if ( ! $found ) : ?><p><?php esc_html_e( 'Noch keine Bögen vorhanden. Wähle eine Kampagne, der du bereits angehörst, und erstelle dort deinen ersten Charakter.', 'ps-pen-paper' ); ?></p><?php endif; ?>
	<?php
}

function ps_pen_paper_render_settings_directory( $page_id = 0 ) {
	?>
	<h2><?php esc_html_e( 'Einstellungen', 'ps-pen-paper' ); ?></h2>
	<p><?php esc_html_e( 'Zugang, Aufnahme neuer Spieler, Bilder und Farben werden für jede Runde getrennt eingestellt. Spieler können diese Einstellungen nicht ändern.', 'ps-pen-paper' ); ?></p>
	<ul class="pspp-campaign-list">
		<?php $found = false; ?>
		<?php foreach ( ps_pen_paper_get_campaigns() as $campaign ) : ?>
			<?php if ( ! ps_pen_paper_can_manage_campaign( $campaign ) ) { continue; } ?>
			<?php $found = true; ?>
			<li><a href="<?php echo esc_url( ( $page_id ? ps_pen_paper_game_url( $page_id, $campaign->ID ) : ps_pen_paper_admin_url( $campaign->ID ) ) . '#pspp-settings' ); ?>"><?php echo esc_html( $campaign->post_title ); ?></a></li>
		<?php endforeach; ?>
	</ul>
	<?php if ( ! $found ) : ?><p><?php esc_html_e( 'Du leitest derzeit keine Runde. Für Änderungen wende dich an deine Spielleitung.', 'ps-pen-paper' ); ?></p><?php endif; ?>
	<?php ps_pen_paper_render_import_settings( $page_id ); ?>
	<?php
}
