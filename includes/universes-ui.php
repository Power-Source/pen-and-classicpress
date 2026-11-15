<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_handle_universe_action( $operation ) {
	$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	if ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) {
		ps_pen_paper_fail( __( 'Die Spielbereich-Seite ist nicht mehr verfügbar.', 'ps-pen-paper' ) );
	}
	$id = ps_pen_paper_posted_id( 'universe_id' );
	$revision = isset( $_POST['universe_revision'] ) ? $_POST['universe_revision'] : null;
	if ( 'save_universe' === $operation ) {
		check_admin_referer( 'pspp_universe_' . $id );
		if ( 'complete' !== ps_pen_paper_posted_text( 'universe_complete' ) ) {
			ps_pen_paper_fail( __( 'Das Universum wurde nicht vollständig übertragen. Es wurde nichts gespeichert.', 'ps-pen-paper' ) );
		}
		$result = ps_pen_paper_save_universe( $id, isset( $_POST['universe'] ) ? wp_unslash( $_POST['universe'] ) : null, $revision );
		$notice = 'universe_saved';
	} else {
		$campaign_id = ps_pen_paper_posted_id( 'campaign_id' );
		check_admin_referer( 'pspp_universe_link_' . $campaign_id );
		$result = ps_pen_paper_save_universe_link(
			$campaign_id, $id, isset( $_POST['link_revision'] ) ? $_POST['link_revision'] : null,
			$revision, ps_pen_paper_posted_text( 'link_confirmation' ),
			isset( $_POST['economy_mode'] ) ? ps_pen_paper_posted_text( 'economy_mode' ) : 'universe'
		);
		$notice = 'universe_linked';
	}
	if ( is_wp_error( $result ) ) {
		ps_pen_paper_fail( $result->get_error_message() );
	}
	wp_safe_redirect( add_query_arg( 'pspp_notice', $notice, ps_pen_paper_dashboard_url( 'universes', $page_id ) ) );
	exit;
}

function ps_pen_paper_render_universe_form( $id, $document, $page_id ) {
	ps_pen_paper_form_start( 'save_universe', 'pspp_universe_' . $id, 0, $page_id );
	?>
		<input type="hidden" name="universe_id" value="<?php echo esc_attr( $id ); ?>">
		<input type="hidden" name="universe_revision" value="<?php echo esc_attr( $document['revision'] ); ?>">
		<p><label><?php esc_html_e( 'Name des Universums', 'ps-pen-paper' ); ?><br>
			<input name="universe[name]" type="text" class="regular-text" value="<?php echo esc_attr( $document['name'] ); ?>" required></label></p>
		<p><label><?php esc_html_e( 'Interne Weltbeschreibung', 'ps-pen-paper' ); ?><br>
			<textarea name="universe[description]" rows="4" class="large-text"><?php echo esc_textarea( $document['description'] ); ?></textarea></label></p>
		<input type="hidden" name="universe_complete" value="complete">
		<?php submit_button( $id ? __( 'Universum speichern', 'ps-pen-paper' ) : __( 'Universum erstellen', 'ps-pen-paper' ) ); ?>
	</form>
	<?php
}

function ps_pen_paper_render_universes( $page_id = 0 ) {
	if ( ! ps_pen_paper_can_access_site() ) {
		echo '<p role="alert">' . esc_html__( 'Du hast keinen Zugriff auf die Universumsverwaltung.', 'ps-pen-paper' ) . '</p>';
		return;
	}
	if ( isset( $_GET['pspp_universe'] ) ) {
		$id = ps_pen_paper_frontend_id( 'pspp_universe' );
		$universe = ! is_wp_error( $id ) && $id ? ps_pen_paper_get_universe( $id ) : null;
		if ( ! $universe || ! ps_pen_paper_can_manage_universe( $universe ) ) {
			echo '<p role="alert">' . esc_html__( 'Dieses Universum ist für dich nicht verfügbar.', 'ps-pen-paper' ) . '</p>';
			return;
		}
		ps_pen_paper_render_universe_characters( $universe, $page_id );
		return;
	}
	?>
	<h2><?php esc_html_e( 'Universen', 'ps-pen-paper' ); ?></h2>
	<p><?php esc_html_e( 'Fasse deine Kampagnen zu einer eigenen Welt zusammen. Beschreibung und Verwaltung bleiben der Universumsleitung und Administration vorbehalten. Eine Zuordnung gewährt keine zusätzlichen Kampagnen- oder Chat-Rechte.', 'ps-pen-paper' ); ?></p>
	<p><?php esc_html_e( 'Verwalte Regelvorlage und Basisbögen im Universum. Die Übernahme als gemeinsamer Stand oder unabhängige Kampagnenkopie erfolgt ausdrücklich; bestehende Kampagnen werden nicht automatisch umgestellt.', 'ps-pen-paper' ); ?></p>
	<?php ps_pen_paper_render_universe_link_preview( $page_id ); ?>
	<?php
	$universes = ps_pen_paper_get_universes();
	$campaigns = ps_pen_paper_get_campaigns();
	foreach ( $campaigns as $campaign ) {
		if ( ! ps_pen_paper_can_manage_campaign( $campaign ) ) { continue; }
		$link = ps_pen_paper_get_universe_link( $campaign->ID );
		if ( is_wp_error( $link ) ) {
			echo '<p role="alert">' . esc_html( $link->get_error_message() ) . '</p>';
		}
	}
	?>
	<?php if ( ! $universes ) : ?><p><?php esc_html_e( 'Du verwaltest noch kein Universum.', 'ps-pen-paper' ); ?></p><?php endif; ?>
	<?php foreach ( $universes as $universe ) :
		$document = ps_pen_paper_universe_document( $universe->ID );
		if ( is_wp_error( $document ) ) {
			echo '<p role="alert">' . esc_html( $document->get_error_message() ) . '</p>';
			continue;
		}
		?>
		<section class="pspp-card">
			<h3><?php echo esc_html( $document['name'] ); ?></h3>
			<p><a class="button" href="<?php echo esc_url( ps_pen_paper_universe_url( $universe->ID, $page_id ) ); ?>"><?php esc_html_e( 'Regelwerk & Basischaraktere', 'ps-pen-paper' ); ?></a></p>
			<p class="pspp-multiline"><?php echo esc_html( $document['description'] ); ?></p>
			<h4><?php esc_html_e( 'Zugeordnete Kampagnen', 'ps-pen-paper' ); ?></h4>
			<ul>
				<?php $found = false; ?>
				<?php foreach ( $campaigns as $campaign ) :
					$link = ps_pen_paper_get_universe_link( $campaign->ID );
					if ( is_wp_error( $link ) || $link['universe_id'] !== $universe->ID || ! ps_pen_paper_can_view_campaign( $campaign ) ) { continue; }
					$found = true;
					?>
					<li><a href="<?php echo esc_url( $page_id ? ps_pen_paper_game_url( $page_id, $campaign->ID ) : ps_pen_paper_admin_url( $campaign->ID ) ); ?>"><?php echo esc_html( $campaign->post_title ); ?></a></li>
				<?php endforeach; ?>
			</ul>
			<?php if ( ! $found ) : ?><p><?php esc_html_e( 'Keine für dich sichtbaren Kampagnen zugeordnet.', 'ps-pen-paper' ); ?></p><?php endif; ?>
			<details class="pspp-fold">
				<summary><?php esc_html_e( 'Universum bearbeiten', 'ps-pen-paper' ); ?></summary>
				<?php ps_pen_paper_render_universe_form( $universe->ID, $document, $page_id ); ?>
			</details>
			<details class="pspp-fold">
				<summary><?php esc_html_e( 'Änderungsverlauf des Universums', 'ps-pen-paper' ); ?></summary>
				<?php $log = ps_pen_paper_get_events( $universe ); ?>
				<?php if ( is_wp_error( $log ) ) : ?>
					<p role="alert"><?php echo esc_html( $log->get_error_message() ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Die letzten 20 Änderungen; Kampagnenzuordnungen stehen im jeweiligen Kampagnenprotokoll.', 'ps-pen-paper' ); ?></p>
					<?php foreach ( $log['events'] as $event ) : ?>
						<p><?php echo esc_html( $event['date'] . ' · ' . $event['actor_name'] ); ?></p>
						<?php ps_pen_paper_render_event_details( $event, $universe ); ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</details>
		</section>
	<?php endforeach; ?>
	<?php if ( ps_pen_paper_can_create_campaign() ) : ?>
		<h3><?php esc_html_e( 'Neues Universum', 'ps-pen-paper' ); ?></h3>
		<?php ps_pen_paper_render_universe_form( 0, array( 'revision' => 0, 'name' => '', 'description' => '' ), $page_id ); ?>
	<?php endif; ?>
	<h3><?php esc_html_e( 'Kampagne zuordnen oder lösen', 'ps-pen-paper' ); ?></h3>
	<p><?php esc_html_e( 'Du benötigst Verwaltungsrechte für die Kampagne und die beteiligten Universen. Zuerst wird eine Vorschau angezeigt; erst deine Bestätigung speichert die Zuordnung.', 'ps-pen-paper' ); ?></p>
	<form method="get" action="<?php echo esc_url( $page_id ? get_permalink( $page_id ) : admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="<?php echo $page_id ? 'pspp_view' : 'page'; ?>" value="<?php echo $page_id ? 'universes' : 'ps-pen-paper-universes'; ?>">
		<p><label><?php esc_html_e( 'Kampagne', 'ps-pen-paper' ); ?>
			<select name="pspp_link_campaign" required>
				<option value=""><?php esc_html_e( 'Kampagne auswählen', 'ps-pen-paper' ); ?></option>
				<?php foreach ( $campaigns as $campaign ) : ?>
					<?php if ( ps_pen_paper_can_manage_campaign( $campaign ) ) : ?><option value="<?php echo esc_attr( $campaign->ID ); ?>"><?php echo esc_html( $campaign->post_title ); ?></option><?php endif; ?>
				<?php endforeach; ?>
			</select></label></p>
		<p><label><?php esc_html_e( 'Zieluniversum', 'ps-pen-paper' ); ?>
			<select name="pspp_link_universe">
				<option value="0"><?php esc_html_e( 'Ohne Universum', 'ps-pen-paper' ); ?></option>
				<?php foreach ( $universes as $universe ) :
					$document = ps_pen_paper_universe_document( $universe->ID );
					if ( is_wp_error( $document ) ) { continue; }
					?>
					<option value="<?php echo esc_attr( $universe->ID ); ?>"><?php echo esc_html( $document['name'] ); ?></option>
				<?php endforeach; ?>
			</select></label></p>
		<p><label><?php esc_html_e( 'Währung und Handelsregeln', 'ps-pen-paper' ); ?>
			<select name="pspp_link_economy">
				<option value="universe"><?php esc_html_e( 'Einstellungen des Zieluniversums verwenden', 'ps-pen-paper' ); ?></option>
				<option value="local"><?php esc_html_e( 'Lokale Währung und Handelsregeln behalten', 'ps-pen-paper' ); ?></option>
			</select></label></p>
		<p class="description"><?php esc_html_e( 'Mit lokalen Einstellungen bleiben bestehende Kampagnenkonten und Guthaben unverändert. Es werden keine Konten zusammengeführt oder Beträge umgerechnet. Gemeinsame Basisbögen müssen zuerst ausdrücklich unabhängig weitergeführt werden; gemeinsame Universumskonten benötigen weiterhin eine gesonderte Kontenmigration.', 'ps-pen-paper' ); ?></p>
		<?php submit_button( __( 'Zuordnung prüfen', 'ps-pen-paper' ), 'secondary' ); ?>
	</form>
	<?php
}

function ps_pen_paper_render_universe_link_preview( $page_id ) {
	if ( ! isset( $_GET['pspp_link_campaign'] ) ) {
		return;
	}
	$campaign_id = ps_pen_paper_frontend_id( 'pspp_link_campaign' );
	$universe_id = isset( $_GET['pspp_link_universe'] ) && '0' === $_GET['pspp_link_universe'] ? 0 : ps_pen_paper_frontend_id( 'pspp_link_universe' );
	$economy_mode = isset( $_GET['pspp_link_economy'] ) ? $_GET['pspp_link_economy'] : 'universe';
	$preview = is_wp_error( $campaign_id ) || is_wp_error( $universe_id ) || ! $campaign_id || ! isset( $_GET['pspp_link_universe'] )
		? new WP_Error( 'invalid_id', __( 'Die gewählte Zuordnung ist ungültig.', 'ps-pen-paper' ) )
		: ps_pen_paper_preview_universe_link( $campaign_id, $universe_id, $economy_mode );
	if ( is_wp_error( $preview ) ) {
		echo '<p role="alert">' . esc_html( $preview->get_error_message() ) . '</p>';
		return;
	}
	$none = __( 'Ohne Universum', 'ps-pen-paper' );
	?>
	<section class="pspp-card">
		<h3><?php esc_html_e( 'Vorschau der Zuordnung', 'ps-pen-paper' ); ?></h3>
		<p><strong><?php echo esc_html( $preview['campaign']->post_title ); ?></strong></p>
		<p><?php echo esc_html( ( $preview['source_universe']['name'] ? $preview['source_universe']['name'] : $none ) . ' → ' . ( $preview['universe']['name'] ? $preview['universe']['name'] : $none ) ); ?></p>
		<p><?php esc_html_e( 'Geändert wird ausschließlich die organisatorische Zuordnung. Regeln, Charakterkennungen, Werte, offene Freigaben, Würfe, Mitgliedschaften und Chat bleiben unverändert. Es werden keine Charaktere kopiert oder zusammengeführt.', 'ps-pen-paper' ); ?></p>
		<p><?php echo esc_html( 'local' === $economy_mode || ! $universe_id
			? __( 'Währung und Handelsregeln bleiben lokal bei der Kampagne. Vorhandene Konten und Guthaben werden nicht verändert.', 'ps-pen-paper' )
			: __( 'Die Kampagne verwendet die Währung und Handelsregeln des Zieluniversums. Lokale Einstellungen werden nicht gelöscht; bestehende Konten werden nicht automatisch übertragen.', 'ps-pen-paper' ) ); ?></p>
		<?php ps_pen_paper_form_start( 'link_universe', 'pspp_universe_link_' . $campaign_id, $campaign_id, $page_id ); ?>
			<input type="hidden" name="universe_id" value="<?php echo esc_attr( $universe_id ); ?>">
			<input type="hidden" name="universe_revision" value="<?php echo esc_attr( $preview['universe']['revision'] ); ?>">
			<input type="hidden" name="link_revision" value="<?php echo esc_attr( $preview['before']['revision'] ); ?>">
			<input type="hidden" name="economy_mode" value="<?php echo esc_attr( $economy_mode ); ?>">
			<p><label class="pspp-check"><input type="checkbox" name="link_confirmation" value="confirmed" required>
				<?php esc_html_e( 'Ich bestätige diese Zuordnung ohne Datenübernahme oder Rechteänderung.', 'ps-pen-paper' ); ?></label></p>
			<?php submit_button( __( 'Zuordnung bestätigen', 'ps-pen-paper' ) ); ?>
		</form>
	</section>
	<?php
}
