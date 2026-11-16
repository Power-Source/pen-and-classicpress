<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_handle_booking_action( $page_id, $id ) {
	check_admin_referer( 'pspp_campaign_' . $id );
	if ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) {
		ps_pen_paper_fail( __( 'Die Spielbereich-Seite ist nicht mehr verfügbar. Bitte öffne das Formular erneut.', 'ps-pen-paper' ) );
	}
	$result = ps_pen_paper_change_booking_link( $id, ps_pen_paper_posted_id( 'booking_event' ),
		ps_pen_paper_posted_text( 'booking_action' ), ps_pen_paper_posted_text( 'booking_revision' ) );
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	$round = ps_pen_paper_play_round( $id );
	if ( is_wp_error( $round ) ) { ps_pen_paper_fail( $round->get_error_message() ); }
	$url = $page_id && $round ? ps_pen_paper_play_url( $page_id, $round['source_id'], 'choose', $id ) :
		( $page_id ? ps_pen_paper_play_url( $page_id, $id ) : ps_pen_paper_admin_url( $id ) );
	wp_safe_redirect( add_query_arg( 'pspp_notice', 'settings_saved', $url ) . '#pspp-bookings-' . $id );
	exit;
}

function ps_pen_paper_render_booking_rows( $events, $page_id = 0, $agenda = false ) {
	$statuses = array( 'open' => __( 'Anmeldung offen', 'ps-pen-paper' ), 'closed' => __( 'Anmeldung geschlossen', 'ps-pen-paper' ),
		'cancel' => __( 'Abgesagt', 'ps-pen-paper' ), 'expired' => __( 'Beendet', 'ps-pen-paper' ), 'archived' => __( 'Archiviert', 'ps-pen-paper' ) );
	echo '<ul class="pspp-bookings">';
	foreach ( $events as $row ) {
		$event = $row['post']; $scope = $row['scope'];
		echo '<li><strong><a href="' . esc_url( get_permalink( $event->ID ) ) . '">' . esc_html( $event->post_title ) . '</a></strong>';
		echo '<div>' . wp_kses_post( $row['dates'] ) . '</div>';
		echo '<p>' . esc_html( $row['response'] );
		if ( isset( $statuses[ $row['status'] ] ) ) { echo ' · ' . esc_html( $statuses[ $row['status'] ] ); }
		echo '</p><a class="button" href="' . esc_url( get_permalink( $event->ID ) ) . '">' . esc_html__( 'Termin & Teilnahme öffnen', 'ps-pen-paper' ) . '</a>';
		if ( $agenda ) {
			$round = ps_pen_paper_play_round( $scope->ID );
			if ( is_wp_error( $round ) ) { echo '<p role="alert">' . esc_html( $round->get_error_message() ) . '</p>'; }
			else {
				$url = $page_id ? ( $round ? ps_pen_paper_play_url( $page_id, $round['source_id'], 'choose', $scope->ID ) :
					ps_pen_paper_play_url( $page_id, $scope->ID ) ) : ps_pen_paper_admin_url( $scope->ID );
				$source = $round ? ps_pen_paper_get_campaign( $round['source_id'] ) : null;
				echo ' <a href="' . esc_url( $url ) . '">' . esc_html( $source ? $source->post_title . ' · ' . $scope->post_title : $scope->post_title ) . '</a>';
			}
		}
		echo '</li>';
	}
	echo '</ul>';
}

function ps_pen_paper_render_bookings( $scope ) {
	$events = ps_pen_paper_booking_events( $scope );
	if ( is_wp_error( $events ) ) { echo '<p role="alert">' . esc_html( $events->get_error_message() ) . '</p>'; return; }
	if ( ! $events ) { return; }
	echo '<section><h3>' . esc_html__( 'Spieltermine', 'ps-pen-paper' ) . '</h3>';
	ps_pen_paper_render_booking_rows( $events );
	echo '<p>' . esc_html__( 'Deine Terminantwort ersetzt nicht den Rundenbeitritt und die Charakterauswahl. Eine Absage beendet deine Spielteilnahme nicht.', 'ps-pen-paper' ) . '</p></section>';
}

function ps_pen_paper_render_booking_agenda( $page_id = 0 ) {
	$events = ps_pen_paper_booking_agenda();
	if ( is_wp_error( $events ) ) { echo '<p role="alert">' . esc_html( $events->get_error_message() ) . '</p>'; return; }
	if ( ! $events ) { return; }
	echo '<section id="pspp-booking-agenda"><h2>' . esc_html__( 'Anstehende Spieltermine · alle Universen', 'ps-pen-paper' ) . '</h2>';
	ps_pen_paper_render_booking_rows( $events, $page_id, true );
	echo '<p>' . esc_html__( 'Zu- und Absagen gelten nur für den Termin. Den Spielzugang und deinen Charakter wählst du in der Runde.', 'ps-pen-paper' ) . '</p></section>';
}

function ps_pen_paper_render_booking_settings( $scope, $page_id = 0 ) {
	if ( ! ps_pen_paper_bookings_available() || ! ps_pen_paper_can_manage_campaign( $scope ) || ! ps_pen_paper_management_view( $page_id ) ) { return; }
	$links = ps_pen_paper_booking_links( $scope->ID );
	if ( is_wp_error( $links ) ) { echo '<p role="alert">' . esc_html( $links->get_error_message() ) . '</p>'; return; }
	?>
	<details class="pspp-fold" id="pspp-bookings-<?php echo esc_attr( $scope->ID ); ?>">
		<summary><?php esc_html_e( 'Spieltermine verwalten', 'ps-pen-paper' ); ?></summary>
		<p><?php esc_html_e( 'Verknüpfe Veranstaltungen aus PS Events mit dieser Kampagne oder Runde. Zeiten, Anmeldungen und Absagen verwaltest du dort.', 'ps-pen-paper' ); ?></p>
		<p><?php esc_html_e( 'Die Sichtbarkeit der Veranstaltung wird separat in PS Events festgelegt. Eine geschlossene Spielrunde macht eine öffentliche Veranstaltung nicht privat. Trage dort keine Spielgeheimnisse ein.', 'ps-pen-paper' ); ?></p>
		<?php foreach ( $links['events'] as $event_id ) :
			$event = get_post( $event_id );
			$readable = ps_pen_paper_booking_event_visible( $event ) ||
				( $event && 'psource_event' === $event->post_type && current_user_can( 'edit_post', $event_id ) );
			ps_pen_paper_form_start( 'change_booking_link', 'pspp_campaign_' . $scope->ID, $scope->ID, $page_id ); ?>
			<input type="hidden" name="booking_revision" value="<?php echo esc_attr( $links['revision'] ); ?>">
			<input type="hidden" name="booking_event" value="<?php echo esc_attr( $event_id ); ?>">
			<input type="hidden" name="booking_action" value="unlink">
			<p><?php if ( $readable ) : ?><?php echo esc_html( $event->post_title ); ?>
				<?php else : ?><?php esc_html_e( 'Veranstaltung nicht mehr verfügbar', 'ps-pen-paper' ); ?><?php endif; ?>
				<button type="submit"><?php esc_html_e( 'Verknüpfung lösen', 'ps-pen-paper' ); ?></button></p>
			</form>
		<?php endforeach; ?>
		<?php $choices = array();
		foreach ( get_posts( array( 'post_type' => 'psource_event', 'post_status' => array( 'publish', 'private' ),
			'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $event ) {
			if ( ! in_array( (int) $event->ID, $links['events'], true ) && current_user_can( 'edit_post', $event->ID ) ) { $choices[] = $event; }
		}
		if ( $choices ) :
			ps_pen_paper_form_start( 'change_booking_link', 'pspp_campaign_' . $scope->ID, $scope->ID, $page_id ); ?>
			<input type="hidden" name="booking_revision" value="<?php echo esc_attr( $links['revision'] ); ?>">
			<input type="hidden" name="booking_action" value="link">
			<label><?php esc_html_e( 'Veranstaltung auswählen', 'ps-pen-paper' ); ?>
				<select name="booking_event" required>
					<option value=""><?php esc_html_e( 'Bitte auswählen', 'ps-pen-paper' ); ?></option>
					<?php foreach ( $choices as $event ) : ?>
						<?php $dates = ps_pen_paper_booking_dates( new Eab_EventModel( $event ) ); ?>
						<option value="<?php echo esc_attr( $event->ID ); ?>" <?php disabled( is_wp_error( $dates ) ); ?>><?php
							echo esc_html( $event->post_title . ' · ' . ( is_wp_error( $dates ) ? $dates->get_error_message() : wp_strip_all_tags( $dates['html'] ) ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="submit"><?php esc_html_e( 'Termin verknüpfen', 'ps-pen-paper' ); ?></button>
			</form>
		<?php else : ?><p><?php esc_html_e( 'Keine weiteren Veranstaltungen zum Verknüpfen vorhanden. Du brauchst auch die Bearbeitungsrechte für die Veranstaltung.', 'ps-pen-paper' ); ?></p><?php endif; ?>
		<?php $type = get_post_type_object( 'psource_event' );
		if ( current_user_can( $type->cap->create_posts ) ) : ?>
			<p><a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=psource_event' ) ); ?>"><?php esc_html_e( 'Veranstaltung in PS Events anlegen', 'ps-pen-paper' ); ?></a></p>
		<?php endif; ?>
	</details>
	<?php
}
