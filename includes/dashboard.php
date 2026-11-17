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
		? add_query_arg( 'pspp_view', 'overview' === $section ? 'lead' : $section, get_permalink( $page_id ) )
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
	<?php if ( $game_page ) : ?><p><a href="<?php echo esc_url( remove_query_arg( 'pspp_view', ps_pen_paper_game_url( $game_page->ID ) ) ); ?>"><?php esc_html_e( 'Zur Game-Lounge', 'ps-pen-paper' ); ?></a></p><?php endif; ?>
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
	$open_universe = ps_pen_paper_frontend_id( 'pspp_settings_universe' );
	if ( is_wp_error( $open_universe ) ) { echo '<p role="alert">' . esc_html( $open_universe->get_error_message() ) . '</p>'; $open_universe = 0; }
	$panel = isset( $_GET['pspp_settings_panel'] ) && is_string( $_GET['pspp_settings_panel'] ) ? sanitize_key( wp_unslash( $_GET['pspp_settings_panel'] ) ) : '';
	$groups = array();
	foreach ( ps_pen_paper_get_universes() as $universe ) {
		if ( ! ps_pen_paper_can_manage_universe( $universe ) ) { continue; }
		$document = ps_pen_paper_universe_document( $universe->ID );
		if ( is_wp_error( $document ) ) {
			echo '<p role="alert">' . esc_html( $document->get_error_message() ) . '</p>';
			continue;
		}
		$groups[ $universe->ID ] = array( 'name' => $document['name'], 'universe' => $universe, 'document' => $document, 'campaigns' => array() );
	}
	$unassigned = array(); $unreadable = array();
	foreach ( ps_pen_paper_get_campaigns() as $campaign ) {
		if ( ! ps_pen_paper_can_manage_campaign( $campaign ) ) { continue; }
		$link = ps_pen_paper_get_universe_link( $campaign->ID );
		if ( is_wp_error( $link ) ) {
			echo '<p role="alert">' . esc_html( $link->get_error_message() ) . '</p>';
			$unreadable[] = $campaign;
			continue;
		}
		if ( ! $link['universe_id'] ) { $unassigned[] = $campaign; continue; }
		$id = $link['universe_id'];
		if ( ! isset( $groups[ $id ] ) ) {
			$groups[ $id ] = array( 'name' => $link['name'], 'universe' => null, 'document' => null, 'campaigns' => array() );
		}
		$groups[ $id ]['campaigns'][] = $campaign;
	}
	?>
	<h2><?php esc_html_e( 'Einstellungen', 'ps-pen-paper' ); ?></h2>
	<p><?php esc_html_e( 'Öffne ein Universum für seine Einstellungen und wähle darunter eine zugehörige Kampagne. Die Universumsleitung verwaltet die Welt; die jeweilige Kampagnenleitung verwaltet ihre Kampagne. Administratoren dürfen beides.', 'ps-pen-paper' ); ?></p>
	<?php foreach ( $groups as $universe_id => $group ) : ?>
		<details class="pspp-fold pspp-settings-universe" id="pspp-settings-universe-<?php echo esc_attr( $universe_id ); ?>" data-settings-universe="<?php echo esc_attr( $universe_id ); ?>" <?php if ( $open_universe === (int) $universe_id ) { echo 'open'; } ?>>
			<summary><?php echo esc_html( $group['name'] ); ?></summary>
			<?php if ( $group['universe'] ) : ?>
				<?php $form_context = static function () { echo '<input type="hidden" name="pspp_settings_return" value="universe">'; }; add_action( 'pspp_form_context', $form_context ); ?>
				<details class="pspp-fold" <?php if ( $open_universe === (int) $universe_id && 'world' === $panel ) { echo 'open'; } ?>><summary><?php esc_html_e( 'Universum bearbeiten', 'ps-pen-paper' ); ?></summary>
					<?php ps_pen_paper_render_universe_form( $group['universe']->ID, $group['document'], $page_id ); ?>
				</details>
				<details class="pspp-fold" <?php if ( $open_universe === (int) $universe_id && 'style' === $panel ) { echo 'open'; } ?>><summary><?php esc_html_e( 'UI-Farben des Universums (optional)', 'ps-pen-paper' ); ?></summary>
					<?php ps_pen_paper_render_style_settings( $group['universe'], $page_id ); ?>
				</details>
				<details class="pspp-fold" <?php if ( $open_universe === (int) $universe_id && in_array( $panel, array( 'characters', 'conditions' ), true ) ) { echo 'open'; } ?>><summary><?php esc_html_e( 'Charaktervorgaben, Einladungen und Porträts', 'ps-pen-paper' ); ?></summary>
					<?php ps_pen_paper_render_character_policy( $group['universe'], $page_id ); ?>
					<?php ps_pen_paper_render_condition_policy( $group['universe'], $page_id, $open_universe === (int) $universe_id && 'conditions' === $panel ); ?>
				</details>
				<details class="pspp-fold" <?php if ( $open_universe === (int) $universe_id && 'economy' === $panel ) { echo 'open'; } ?>><summary><?php esc_html_e( 'Währung und Handelsregeln', 'ps-pen-paper' ); ?></summary>
					<?php if ( ! ps_pen_paper_community_available() ) : ?><p><?php esc_html_e( 'Währung und Handelsänderungen benötigen aktives PS Community.', 'ps-pen-paper' ); ?></p><?php endif; ?>
					<?php ps_pen_paper_render_currency_settings( $group['universe'], $page_id ); ?>
					<?php ps_pen_paper_render_trade_policy( $group['universe'], $page_id ); ?>
				</details>
				<?php remove_action( 'pspp_form_context', $form_context ); ?>
				<p><a href="<?php echo esc_url( ps_pen_paper_universe_url( $group['universe']->ID, $page_id ) ); ?>"><?php esc_html_e( 'Regelwerk & Basischaraktere', 'ps-pen-paper' ); ?></a></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Die Einstellungen dieses Universums sind für dich nicht verfügbar. Wende dich an die Universumsleitung oder Administration.', 'ps-pen-paper' ); ?></p>
			<?php endif; ?>
			<?php ps_pen_paper_render_settings_campaign_select( $group['campaigns'], $page_id ); ?>
		</details>
	<?php endforeach; ?>
	<?php if ( $unassigned ) : ?>
		<details class="pspp-fold pspp-settings-universe">
			<summary><?php esc_html_e( 'Ältere Kampagnen ohne Universumszuordnung', 'ps-pen-paper' ); ?></summary>
			<p><?php esc_html_e( 'Diese älteren Kampagnen benötigen eine ausdrücklich bestätigte Universumszuordnung.', 'ps-pen-paper' ); ?></p>
			<p><a href="<?php echo esc_url( ps_pen_paper_dashboard_url( 'universes', $page_id ) ); ?>"><?php esc_html_e( 'Universumszuordnung prüfen', 'ps-pen-paper' ); ?></a></p>
			<?php ps_pen_paper_render_settings_campaign_select( $unassigned, $page_id ); ?>
		</details>
	<?php endif; ?>
	<?php if ( $unreadable ) : ?>
		<details class="pspp-fold pspp-settings-universe">
			<summary><?php esc_html_e( 'Kampagnen mit fehlerhafter Universumszuordnung', 'ps-pen-paper' ); ?></summary>
			<p><?php esc_html_e( 'Bitte wende dich zur Prüfung an die Administration.', 'ps-pen-paper' ); ?></p>
			<?php ps_pen_paper_render_settings_campaign_select( $unreadable, $page_id ); ?>
		</details>
	<?php endif; ?>
	<?php if ( ! $groups && ! $unassigned && ! $unreadable ) : ?><p><?php esc_html_e( 'Du verwaltest derzeit kein Universum und keine Kampagne.', 'ps-pen-paper' ); ?></p><?php endif; ?>
	<?php if ( ps_pen_paper_can_preview_import() ) : ?>
		<details class="pspp-fold" <?php if ( isset( $_GET['pspp_import_preview'] ) ) { echo 'open'; } ?>>
			<summary><?php esc_html_e( 'Sicherung und Wiederherstellung', 'ps-pen-paper' ); ?></summary>
			<?php ps_pen_paper_render_import_settings( $page_id ); ?>
		</details>
	<?php endif; ?>
	<?php
}

function ps_pen_paper_settings_return_url( $universe_id, $page_id, $panel ) {
	if ( 'universe' !== ps_pen_paper_posted_text( 'pspp_settings_return' ) || ! ps_pen_paper_get_universe( $universe_id ) ) { return ''; }
	return add_query_arg( array( 'pspp_settings_universe' => $universe_id, 'pspp_settings_panel' => $panel ),
		ps_pen_paper_dashboard_url( 'settings', $page_id ) ) . '#pspp-settings-universe-' . $universe_id;
}

function ps_pen_paper_render_settings_campaign_select( $campaigns, $page_id ) {
	?>
	<details class="pspp-fold">
		<summary><?php esc_html_e( 'Kampagneneinstellungen', 'ps-pen-paper' ); ?></summary>
		<?php if ( ! $campaigns ) : ?>
			<p><?php esc_html_e( 'Keine von dir verwalteten Kampagnen zugeordnet.', 'ps-pen-paper' ); ?></p>
		<?php else : ?>
			<form method="get" action="<?php echo esc_url( ( $page_id ? get_permalink( $page_id ) : admin_url( 'admin.php' ) ) . '#pspp-settings' ); ?>">
				<input type="hidden" name="<?php echo $page_id ? 'pspp_view' : 'page'; ?>" value="<?php echo $page_id ? 'settings' : 'ps-pen-paper'; ?>">
				<label><?php esc_html_e( 'Kampagne', 'ps-pen-paper' ); ?>
					<select name="<?php echo $page_id ? 'pspp_campaign' : 'campaign_id'; ?>" required>
						<option value=""><?php esc_html_e( 'Bitte wählen', 'ps-pen-paper' ); ?></option>
						<?php foreach ( $campaigns as $campaign ) : ?><option value="<?php echo esc_attr( $campaign->ID ); ?>"><?php echo esc_html( $campaign->post_title ); ?></option><?php endforeach; ?>
					</select>
				</label>
				<button type="submit"><?php esc_html_e( 'Einstellungen öffnen', 'ps-pen-paper' ); ?></button>
			</form>
		<?php endif; ?>
	</details>
	<?php
}
