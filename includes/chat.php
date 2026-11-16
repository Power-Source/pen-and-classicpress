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
		if ( isset( $_SERVER['HTTP_X_PSPP_VIEW'] ) ) {
			$view = $_SERVER['HTTP_X_PSPP_VIEW'];
			if ( ! in_array( $view, array( 'overview', 'lead' ), true ) ) {
				error_log( 'PS Pen & Paper: invalid chat workspace header.' );
				return false;
			}
			if ( 'overview' === $view ) { return false; }
		} elseif ( ps_pen_paper_is_frontend_page() && ! ps_pen_paper_management_view( get_queried_object_id() ) ) {
			return false;
		}
		$campaign = ps_pen_paper_chat_campaign( $session );
		return $campaign && ps_pen_paper_chat_access( false, $session ) && ps_pen_paper_can_manage_campaign( $campaign );
	}
	return $moderator;
}

function ps_pen_paper_chat_rolls( $campaign ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Diese Gruppenwürfe sind für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	$query = new WP_Query( array( 'post_type' => 'pspp_roll', 'post_status' => 'private', 'post_parent' => $campaign->ID,
		'posts_per_page' => 20, 'orderby' => 'ID', 'order' => 'DESC', 'no_found_rows' => true,
		'meta_query' => array( array( 'key' => '_pspp_visibility', 'value' => 'group' ) ) ) );
	$rolls = array();
	foreach ( array_reverse( $query->posts ) as $post ) {
		$event = ps_pen_paper_get_roll( $campaign, $post->ID );
		if ( is_wp_error( $event ) ) { return $event; }
		if ( 'group' !== $event['visibility'] ) { continue; }
		$rolls[] = array( 'id' => $post->ID, 'text' => $event['actor_name'] . ' · ' .
			( $event['label'] ? $event['label'] . ' · ' : '' ) . ps_pen_paper_roll_description( $event ) . ' = ' . $event['total'] );
	}
	return $rolls;
}

function ps_pen_paper_render_chat( $campaign, $page_id = 0 ) {
	global $psource_chat;
	?>
	<details class="pspp-chat-dock" data-table-chat data-chat-view="<?php echo ps_pen_paper_management_view( $page_id ) ? 'lead' : 'overview'; ?>">
		<summary><?php esc_html_e( 'Rundenchat', 'ps-pen-paper' ); ?> <span data-chat-unread hidden></span></summary>
		<div class="pspp-chat-body">
			<section aria-label="<?php esc_attr_e( 'Öffentliche Gruppenwürfe', 'ps-pen-paper' ); ?>">
				<h2><?php esc_html_e( 'Gruppenwürfe', 'ps-pen-paper' ); ?></h2>
				<p><?php esc_html_e( 'Die letzten 20 öffentlichen Würfe dieser Runde. Private und verdeckte Würfe erscheinen hier nicht.', 'ps-pen-paper' ); ?></p>
				<ol data-chat-rolls aria-live="polite" aria-relevant="additions"><?php
					$rolls = ps_pen_paper_chat_rolls( $campaign );
					if ( is_wp_error( $rolls ) ) { echo '<li role="alert">' . esc_html( $rolls->get_error_message() ) . '</li>'; }
					else { foreach ( $rolls as $roll ) { echo '<li data-roll-id="' . esc_attr( $roll['id'] ) . '">' . esc_html( $roll['text'] ) . '</li>'; } }
				?></ol>
			</section>
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
				wp_enqueue_script( 'pspp-chat-view', plugins_url( '../assets/chat.js', __FILE__ ), array( 'jquery' ), '0.29.3', true );
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
