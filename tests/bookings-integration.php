<?php
/**
 * Run serially with WP-CLI --skip-plugins --skip-themes.
 * Requires the sibling events-and-bookings plugin; does not activate it.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'event' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
$provider = dirname( __DIR__, 2 ) . '/events-and-bookings/events-and-bookings.php';
if ( ! is_file( $provider ) ) { WP_CLI::error( 'Install events-and-bookings beside this plugin to run its integration test.' ); }

global $wpdb;
$original_user = get_current_user_id(); $users = array(); $posts = array(); $tables = array();
$check = static function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$success = static function ( $value, $message ) use ( $check ) {
	$check( ! is_wp_error( $value ) && false !== $value && 0 !== $value,
		$message . ( is_wp_error( $value ) ? ': ' . $value->get_error_message() : '' ) );
	return $value;
};
$render = static function ( $callback ) { ob_start(); $callback(); return ob_get_clean(); };
$post = static function ( $type, $author, $title, $status = 'private' ) use ( &$posts, $success ) {
	$id = $success( wp_insert_post( array( 'post_type' => $type, 'post_status' => $status,
		'post_author' => $author, 'post_title' => $title ), true ), 'Post fixture failed' );
	$posts[] = $id; return $id;
};
try {
	$check( ! ps_pen_paper_bookings_available() && '' === $render( static function () { ps_pen_paper_render_booking_agenda(); } ),
		'Inactive provider exposed an empty calendar or installation prompt.' );
	add_filter( 'pre_schedule_event', '__return_false' );
	require_once $provider;
	remove_filter( 'pre_schedule_event', '__return_false' );
	( new Eab_Taxonomies() )->register_post_type();
	$check( ps_pen_paper_bookings_available(), 'Real provider was not recognized.' );

	// Connection-local tables shadow any real bookings without touching existing event registrations.
	$table = Eab_EventsHub::tablename( Eab_EventsHub::BOOKING_TABLE );
	$check( false !== $wpdb->query( "CREATE TEMPORARY TABLE `$table` (
		id BIGINT AUTO_INCREMENT PRIMARY KEY, event_id BIGINT NOT NULL, user_id BIGINT NOT NULL,
		timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status ENUM('paid','yes','maybe','no') NOT NULL,
		UNIQUE KEY event_user(event_id,user_id))" ), 'Temporary bookings table failed' ); $tables[] = $table;
	$table = Eab_EventsHub::tablename( Eab_EventsHub::BOOKING_META_TABLE );
	$check( false !== $wpdb->query( "CREATE TEMPORARY TABLE `$table` (
		id BIGINT AUTO_INCREMENT PRIMARY KEY, booking_id BIGINT NOT NULL, meta_key VARCHAR(255), meta_value TEXT)" ),
		'Temporary booking metadata table failed' ); $tables[] = $table;
	foreach ( array( 'gm', 'player', 'outsider' ) as $role ) {
		$users[ $role ] = $success( wp_insert_user( array( 'user_login' => 'pspp-booking-' . $role . '-' . wp_generate_password( 12, false, false ),
			'user_pass' => wp_generate_password( 32 ), 'role' => 'gm' === $role ? 'author' : 'subscriber' ) ), 'Account fixture failed' );
	}
	wp_set_current_user( $users['gm'] );
	$scope_id = $post( 'pspp_campaign', $users['gm'], 'Schedule campaign' );
	add_post_meta( $scope_id, '_pspp_player', (string) $users['player'] );
	$scope = ps_pen_paper_get_campaign( $scope_id );
	$round_id = $post( 'pspp_campaign', $users['gm'], 'Schedule round' );
	$state = array( 'revision' => 1, 'source_id' => $scope_id, 'mode' => 'guided', 'status' => 'lobby',
		'host_id' => $users['gm'], 'adventure_id' => 0, 'run_id' => 0, 'participants' => array() );
	update_post_meta( $round_id, '_pspp_play_round', $state );
	$round = ps_pen_paper_get_campaign( $round_id );
	$event_id = $post( 'psource_event', $users['gm'], 'Booking appointment', 'publish' );
	$other_event = $post( 'psource_event', $users['outsider'], 'Other organizer appointment', 'publish' );
	foreach ( array( $event_id, $other_event ) as $id ) {
		update_post_meta( $id, 'psource_event_start', gmdate( 'Y-m-d H:i:s', eab_current_time() + 86400 ) );
		update_post_meta( $id, 'psource_event_end', gmdate( 'Y-m-d H:i:s', eab_current_time() + 90000 ) );
		update_post_meta( $id, 'psource_event_status', Eab_EventModel::STATUS_OPEN );
	}
	$check( is_wp_error( ps_pen_paper_change_booking_link( $scope_id, $other_event, 'link', '0' ) ),
		'Game management bypassed native event editing rights.' );
	$success( ps_pen_paper_change_booking_link( $scope_id, $event_id, 'link', '0' ), 'Campaign event linking failed' );
	$success( ps_pen_paper_change_booking_link( $round_id, $event_id, 'link', '0' ), 'Round event linking failed' );
	$check( is_wp_error( ps_pen_paper_change_booking_link( $scope_id, $event_id, 'unlink', '0' ) ), 'Stale form changed the links.' );
	$archive = $success( ps_pen_paper_export_campaign( $scope_id, 'local' ), 'Export with scheduling failed' );
	$check( false === strpos( wp_json_encode( $archive ), '_pspp_booking_links' ), 'Local event references leaked into a portable backup.' );
	$settings = $render( static function () use ( $scope ) { ps_pen_paper_render_booking_settings( $scope ); } );
	$check( false !== strpos( $settings, 'value="unlink"' ) && false === strpos( $settings, 'Other organizer appointment' ),
		'Schedule settings omitted unlinking or offered an unauthorized event.' );

	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_change_booking_link( $scope_id, $event_id, 'unlink', '1' ) ), 'Player changed event associations.' );
	$rows = $success( ps_pen_paper_booking_events( $round ), 'Lobby events failed' );
	$check( 1 === count( $rows ) && 'Noch keine Antwort' === $rows[0]['response'], 'An accessible lobby hid its appointment or invented a response.' );
	$model = new Eab_EventModel( get_post( $event_id ) );
	$success( $model->add_attendance( $users['player'], Eab_EventModel::BOOKING_YES ), 'Native RSVP failed' );
	$rows = $success( ps_pen_paper_booking_events( $round ), 'Response loading failed' );
	$check( Eab_Template::get_rsvp_status_list()['yes'] === $rows[0]['response'] &&
		$state === ps_pen_paper_play_round( $round_id ) && ! ps_pen_paper_can_view_campaign( $round ), 'RSVP changed game membership or selected a character.' );
	$native_form = Eab_Template::get_rsvp_form( get_post( $event_id ) );
	$check( false !== strpos( $native_form, 'name="action_no"' ) && false !== strpos( $native_form, 'name="_wpnonce"' ),
		'Native participation actions were unavailable.' );
	$success( $model->cancel_attendance( $users['player'] ), 'Native RSVP cancellation failed' );
	$check( Eab_Template::get_rsvp_status_list()['no'] === ps_pen_paper_booking_events( $round )[0]['response'] &&
		$state === ps_pen_paper_play_round( $round_id ), 'Cancellation changed the game state or left a stale RSVP.' );
	$html = $render( static function () use ( $round ) { ps_pen_paper_render_bookings( $round ); } );
	$check( false !== strpos( $html, esc_url( get_permalink( $event_id ) ) ) &&
		false !== strpos( $html, 'Termin &amp; Teilnahme' ) && false === strpos( $html, 'name="action_yes"' ), 'UI did not delegate participation to the native event page.' );
	$check( 2 === count( ps_pen_paper_booking_agenda() ), 'Cross-context agenda omitted accessible appointments.' );

	wp_set_current_user( $users['outsider'] );
	$check( array() === ps_pen_paper_booking_agenda() && array() === ps_pen_paper_booking_events( $round ),
		'Outsider received private game scheduling information.' );
	wp_set_current_user( $users['player'] );
	wp_update_post( array( 'ID' => $event_id, 'post_status' => 'private' ) );
	$check( array() === ps_pen_paper_booking_events( $round ), 'Private event bypassed native read permissions.' );
	wp_update_post( array( 'ID' => $event_id, 'post_status' => 'publish', 'post_password' => 'fixture-only' ) );
	$check( array() === ps_pen_paper_booking_events( $round ), 'Password-protected event details were exposed.' );
	wp_update_post( array( 'ID' => $event_id, 'post_password' => '' ) );
	update_post_meta( $event_id, 'psource_event_status', 'cancel' );
	$check( false !== strpos( $render( static function () use ( $round ) { ps_pen_paper_render_bookings( $round ); } ), 'Abgesagt' ) &&
		$state === ps_pen_paper_play_round( $round_id ), 'Event cancellation was hidden or changed the game.' );
	update_post_meta( $event_id, 'psource_event_start', gmdate( 'Y-m-d H:i:s', eab_current_time() - 90000 ) );
	update_post_meta( $event_id, 'psource_event_end', gmdate( 'Y-m-d H:i:s', eab_current_time() - 86400 ) );
	$past_agenda = ps_pen_paper_booking_agenda(); $past_context = ps_pen_paper_booking_events( $round );
	$check( array() === $past_agenda && is_array( $past_context ) && 1 === count( $past_context ),
		'Past events remained upcoming or disappeared from their context: ' . wp_json_encode( array( $past_agenda, $past_context,
			get_post_meta( $event_id, 'psource_event_start' ), get_post_meta( $event_id, 'psource_event_end' ) ) ) );
	$solo = $state; $solo['mode'] = 'solo'; $solo['adventure_id'] = 1; $solo['host_id'] = $users['outsider'];
	update_post_meta( $round_id, '_pspp_play_round', $solo );
	$check( array() === ps_pen_paper_booking_events( $round ), 'Another player received private solo appointments.' );
	update_post_meta( $round_id, '_pspp_play_round', $state );
	wp_trash_post( $event_id );
	$check( array() === ps_pen_paper_booking_events( $round ), 'Trashed event remained visible.' );
	wp_set_current_user( $users['gm'] );
	$success( ps_pen_paper_change_booking_link( $scope_id, $event_id, 'unlink', '1' ), 'Dead reference could not be removed' );
	$success( ps_pen_paper_change_booking_link( $round_id, $event_id, 'unlink', '1' ), 'Round reference could not be removed' );
	$success( wp_untrash_post( $event_id ), 'Restore event fixture failed' );
	delete_post_meta( $event_id, 'psource_event_end' );
	$check( is_wp_error( ps_pen_paper_change_booking_link( $scope_id, $event_id, 'link', '2' ) ), 'Invalid event dates were accepted.' );
	update_post_meta( $scope_id, '_pspp_booking_links', array( 'revision' => 3, 'events' => array( 'bad' ) ) );
	$check( is_wp_error( ps_pen_paper_booking_links( $scope_id ) ), 'Corrupt links became a successful empty schedule.' );
	update_post_meta( $scope_id, '_pspp_booking_links', array( 'revision' => 3, 'events' => array( $event_id ) ) );
	unregister_post_type( 'psource_event' );
	$check( ! ps_pen_paper_bookings_available() && array() === ps_pen_paper_booking_agenda() &&
		'' === $render( static function () use ( $scope ) { ps_pen_paper_render_booking_settings( $scope ); } ) &&
		$state === ps_pen_paper_play_round( $round_id ) && array( $event_id ) === ps_pen_paper_booking_links( $scope_id )['events'],
		'Provider deactivation damaged the game, discarded links or exposed controls.' );
	$check( is_wp_error( ps_pen_paper_change_booking_link( $scope_id, $event_id, 'unlink', '3' ) ), 'Inactive provider accepted a stale action.' );
} finally {
	remove_filter( 'pre_schedule_event', '__return_false' );
	( new Eab_Taxonomies() )->register_post_type();
	foreach ( array_reverse( $posts ) as $id ) {
		$children = get_posts( array( 'post_type' => 'any', 'post_status' => 'any', 'post_parent' => $id, 'posts_per_page' => -1 ) );
		foreach ( $children as $child ) { wp_delete_post( $child->ID, true ); }
		wp_delete_post( $id, true );
	}
	foreach ( $users as $id ) { wp_delete_user( $id ); }
	wp_set_current_user( $original_user );
	foreach ( $tables as $table ) { $wpdb->query( "DROP TEMPORARY TABLE `$table`" ); }
}
WP_CLI::success( 'Booking integration: native events, RSVP separation, access, stale links, export and inactive provider passed.' );
