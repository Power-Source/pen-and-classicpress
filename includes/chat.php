<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_chat_id( $campaign_id, $epoch = null ) {
	if ( null === $epoch ) { $epoch = ps_pen_paper_scope_epoch( $campaign_id ); }
	if ( is_wp_error( $epoch ) ) { return $epoch; }
	if ( ! is_string( $epoch ) || ( '' !== $epoch && ! wp_is_uuid( $epoch ) ) ) {
		return new WP_Error( 'scope_state_failed', __( 'Der Wiederherstellungsstand der Runde konnte nicht sicher geprüft werden. Bitte informiere die Administration.', 'ps-pen-paper' ) );
	}
	return 'pspp-' . get_current_blog_id() . '-' . $campaign_id . ( $epoch ? '-' . str_replace( '-', '', $epoch ) : '' );
}

function ps_pen_paper_chat_campaign( $session ) {
	if ( empty( $session['id'] ) || ! is_string( $session['id'] ) || ! preg_match( '/^pspp-([1-9]\d*)-([1-9]\d*)(?:-[a-fA-F0-9]{32})?$/D', $session['id'], $match ) ) {
		return null;
	}
	if ( (int) $match[1] !== get_current_blog_id() || ! isset( $session['blog_id'], $session['session_type'] ) || (int) $session['blog_id'] !== get_current_blog_id() || 'page' !== $session['session_type'] ) {
		return null;
	}
	$campaign = ps_pen_paper_get_campaign( (int) $match[2] );
	$id = $campaign ? ps_pen_paper_chat_id( $campaign->ID ) : null;
	return is_string( $id ) && $id === $session['id'] ? $campaign : null;
}

function ps_pen_paper_chat_access( $allowed, $session ) {
	if ( isset( $session['id'] ) && is_string( $session['id'] ) && 0 === strpos( $session['id'], 'pspp-' ) ) {
		$campaign = ps_pen_paper_chat_campaign( $session );
		return $campaign && ps_pen_paper_can_access_site() && ps_pen_paper_can_view_campaign( $campaign );
	}
	return $allowed;
}

function ps_pen_paper_chat_moderator( $moderator, $session ) {
	if ( isset( $session['id'] ) && is_string( $session['id'] ) && 0 === strpos( $session['id'], 'pspp-' ) ) {
		$campaign = ps_pen_paper_chat_campaign( $session );
		return $campaign && ps_pen_paper_chat_access( false, $session ) && ps_pen_paper_can_manage_campaign( $campaign );
	}
	return $moderator;
}

function ps_pen_paper_render_chat( $campaign ) {
	global $psource_chat;
	?>
	<details class="pspp-chat-dock" data-table-chat>
		<summary><?php esc_html_e( 'Rundenchat', 'ps-pen-paper' ); ?> <span data-chat-unread hidden></span></summary>
		<div class="pspp-chat-body">
			<h2><?php esc_html_e( 'Eure Gespräche', 'ps-pen-paper' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Nur Mitglieder dieser Kampagne. Die Spielleitung moderiert.', 'ps-pen-paper' ); ?></p>
			<?php if ( ! is_object( $psource_chat ) || ! method_exists( $psource_chat, 'process_chat_shortcode' ) || ! function_exists( 'psource_chat_session_access' ) ) : ?>
				<p><?php esc_html_e( 'Der Rundenchat benötigt PS Chat mit der Spieltisch-Schnittstelle. Bitte wende dich an die Website-Administration.', 'ps-pen-paper' ); ?></p>
			<?php else : ?>
				<?php
				$style = ps_pen_paper_get_style( $campaign->ID );
				$chat_id = ps_pen_paper_chat_id( $campaign->ID );
				if ( is_wp_error( $style ) ) {
					echo '<p role="alert">' . esc_html( $style->get_error_message() ) . '</p>';
				} elseif ( is_wp_error( $chat_id ) ) {
					echo '<p role="alert">' . esc_html( $chat_id->get_error_message() ) . '</p>';
				} else {
				$colors = $style['colors'];
				echo $psource_chat->process_chat_shortcode( array(
					'id' => $chat_id,
					'access_provider' => 'ps-pen-paper',
					'session_type' => 'page',
					'login_options' => array_keys( wp_roles()->roles ),
					'moderator_roles' => array(),
					'box_title' => __( 'Rundenchat', 'ps-pen-paper' ),
					'box_width' => '100%',
					'box_height' => '320px',
					'users_list_show' => 'disabled',
					'log_display' => 'disabled',
					'box_popout' => 'disabled',
					'blocked_ip_addresses_active' => 'disabled',
					'box_send_button_enable' => 'enabled',
					'box_send_button_position' => 'right',
					'row_name_avatar' => 'name',
					'row_time' => 'enabled',
					'box_text_color' => $colors['text'],
					'box_background_color' => $colors['surface'],
					'box_border_color' => $colors['border'],
					'row_area_background_color' => $colors['background'],
					'row_background_color' => $colors['surface'],
					'row_border_color' => $colors['border'],
					'row_text_color' => $colors['text'],
					'row_name_color' => $colors['accent'],
					'row_moderator_name_color' => $colors['accent'],
					'row_date_text_color' => $colors['text'],
					'row_date_color' => $colors['surface'],
					'row_message_input_background_color' => $colors['background'],
					'row_message_input_text_color' => $colors['text'],
				) );
				}
				?>
			<?php endif; ?>
		</div>
	</details>
	<?php
}
