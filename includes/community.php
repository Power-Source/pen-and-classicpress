<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_community_available() {
	return defined( 'CPC_CORE_PLUGINS' ) &&
		in_array( 'core-profile', explode( ',', CPC_CORE_PLUGINS ), true ) &&
		function_exists( 'cpc_get_profile_tabs' ) && function_exists( 'cpc_render_profile_tab_content' );
}

function ps_pen_paper_community_url( $campaign_id = 0, $character_id = 0 ) {
	$page_id = (int) get_option( 'cpccom_profile_page' );
	if ( ! ps_pen_paper_community_available() || ! $page_id || 'publish' !== get_post_status( $page_id ) ) { return ''; }
	$url = get_permalink( $page_id );
	if ( ! get_option( 'cpccom_profile_permalinks' ) ) {
		$url = trailingslashit( $url ) . rawurlencode( wp_get_current_user()->user_login );
	} else {
		$url = add_query_arg( 'user_id', get_current_user_id(), $url );
	}
	$args = array( 'tab' => 'pspp' );
	if ( $campaign_id ) { $args['pspp_campaign'] = $campaign_id; }
	if ( $character_id ) { $args['pspp_character'] = $character_id; }
	return add_query_arg( $args, $url );
}

function ps_pen_paper_community_tabs( $tabs, $user_id, $viewer_id ) {
	if ( ps_pen_paper_community_available() && ps_pen_paper_can_access_site() &&
		(int) $user_id === get_current_user_id() && (int) $viewer_id === get_current_user_id() ) {
		$tabs['pspp'] = array( 'label' => __( 'Pen & Paper', 'ps-pen-paper' ), 'icon' => 'id-alt', 'priority' => 45 );
	}
	return $tabs;
}

function ps_pen_paper_community_content( $html, $tab, $user_id, $atts ) {
	if ( 'pspp' !== $tab ) { return $html; }
	if ( ! ps_pen_paper_community_available() || ! ps_pen_paper_can_access_site() || (int) $user_id !== get_current_user_id() ) {
		return '<p role="alert">' . esc_html__( 'Dieser Charakterbereich ist privat.', 'ps-pen-paper' ) . '</p>';
	}
	ob_start();
	$campaign_id = ps_pen_paper_frontend_id( 'pspp_campaign' );
	$character_id = ps_pen_paper_frontend_id( 'pspp_character' );
	$colors = ps_pen_paper_style_defaults();
	if ( ! is_wp_error( $campaign_id ) && ! is_wp_error( $character_id ) && $character_id ) {
		$scope = ps_pen_paper_get_campaign( $campaign_id );
		$selected = $scope ? ps_pen_paper_get_character( $scope, $character_id ) : null;
		if ( $selected && (int) $selected->post_author === get_current_user_id() ) {
			$style = ps_pen_paper_effective_style( $campaign_id );
			if ( is_wp_error( $style ) ) { ob_end_clean(); return '<p role="alert">' . esc_html( $style->get_error_message() ) . '</p>'; }
			$colors = $style['colors'];
		}
	}
	echo '<div class="pspp-game pspp-profile" style="' . esc_attr( ps_pen_paper_style_variables( $colors ) ) . '"><h2>' . esc_html__( 'Meine Charaktere', 'ps-pen-paper' ) . '</h2>';
	if ( is_wp_error( $campaign_id ) || is_wp_error( $character_id ) ) {
		echo '<p role="alert">' . esc_html__( 'Die Charakterauswahl ist ungültig.', 'ps-pen-paper' ) . '</p>';
	} elseif ( $character_id ) {
		$campaign = ps_pen_paper_get_campaign( $campaign_id );
		$character = $campaign ? ps_pen_paper_get_character( $campaign, $character_id ) : null;
		if ( ! $character || (int) $character->post_author !== get_current_user_id() ) {
			echo '<p role="alert">' . esc_html__( 'Dieser Charakter gehört nicht zu deinem zugänglichen Profilbereich.', 'ps-pen-paper' ) . '</p>';
		} else {
			$game_page = ps_pen_paper_find_frontend_page();
			if ( $game_page ) {
				echo '<p><a class="button button-primary" href="' . esc_url( ps_pen_paper_game_url( $game_page->ID, $campaign->ID ) . '#pspp-play' ) . '">' . esc_html__( 'Zum Spieltisch', 'ps-pen-paper' ) . '</a></p>';
			} else {
				echo '<p role="alert">' . esc_html__( 'Es ist keine veröffentlichte Spielbereich-Seite eingerichtet. Bitte wende dich an den Website-Betreiber.', 'ps-pen-paper' ) . '</p>';
			}
			// Existing forms keep their authorization; only their signed return context changes.
			add_action( 'pspp_form_context', 'ps_pen_paper_profile_form_context' );
			add_filter( 'pspp_form_action', 'ps_pen_paper_profile_form_action' );
			ps_pen_paper_render_characters( $campaign, $character_id, 0, ps_pen_paper_community_url() );
			remove_filter( 'pspp_form_action', 'ps_pen_paper_profile_form_action' );
			remove_action( 'pspp_form_context', 'ps_pen_paper_profile_form_context' );
		}
	} else {
		$found = false;
		foreach ( ps_pen_paper_get_campaigns() as $campaign ) {
			foreach ( ps_pen_paper_get_characters( $campaign ) as $character ) {
				if ( (int) $character->post_author !== get_current_user_id() ) { continue; }
				if ( ! $found ) { echo '<ul class="pspp-character-list">'; $found = true; }
				$state = ps_pen_paper_character_state( $campaign, $character );
				if ( is_wp_error( $state ) ) {
					echo '<li role="alert">' . esc_html( $state->get_error_message() ) . ' <a href="' . esc_url( ps_pen_paper_community_url( $campaign->ID, $character->ID ) ) . '">' . esc_html__( 'Bogen und Inventar prüfen', 'ps-pen-paper' ) . '</a><br>' . esc_html( $campaign->post_title ) . '</li>';
					continue;
				}
				$snapshot = $state['active'] ?: $state['pending'];
				$name = $snapshot ? $snapshot['name'] : __( 'Charakter ohne freigegebenen Stand', 'ps-pen-paper' );
				echo '<li><a href="' . esc_url( ps_pen_paper_community_url( $campaign->ID, $character->ID ) ) . '">' . esc_html( $name ) . '</a><br><span>' . esc_html( __( 'Kampagne:', 'ps-pen-paper' ) . ' ' . $campaign->post_title ) . '</span>';
				if ( ! $state['active'] ) { echo '<br>' . esc_html__( 'Noch nicht freigegeben', 'ps-pen-paper' ); }
				elseif ( $state['pending'] ) { echo '<br>' . esc_html__( 'Änderungsantrag offen', 'ps-pen-paper' ); }
				echo '</li>';
			}
		}
		if ( $found ) { echo '</ul>'; }
		else { echo '<p>' . esc_html__( 'Du hast noch keine eigenen Charaktere in deinen zugänglichen Kampagnen.', 'ps-pen-paper' ) . '</p>'; }
	}
	echo '</div>';
	return ob_get_clean();
}

function ps_pen_paper_profile_form_context() {
	echo '<input type="hidden" name="pspp_profile" value="' . esc_attr( wp_create_nonce( 'pspp_profile_' . get_current_user_id() ) ) . '">';
}

function ps_pen_paper_profile_form_action( $url ) {
	return ps_pen_paper_community_url();
}

function ps_pen_paper_profile_return( $campaign_id, $character_id ) {
	if ( ! isset( $_POST['pspp_profile'] ) ) { return ''; }
	if ( ! is_string( $_POST['pspp_profile'] ) || ! wp_verify_nonce( $_POST['pspp_profile'], 'pspp_profile_' . get_current_user_id() ) ||
		! ( $url = ps_pen_paper_community_url( $campaign_id, $character_id ) ) ) {
		ps_pen_paper_fail( __( 'Die Profilintegration ist nicht mehr verfügbar. Bitte öffne deinen Charakter im Spielbereich erneut.', 'ps-pen-paper' ) );
	}
	return $url;
}

function ps_pen_paper_community_prepare() {
	if ( ! ps_pen_paper_community_available() || ! is_singular( 'page' ) || (int) get_option( 'cpccom_profile_page' ) !== get_queried_object_id() ) { return; }
	if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
	nocache_headers();
	header( 'Cache-Control: no-store, private, max-age=0, must-revalidate' );
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['action'] ) && 'ps_pen_paper' === $_POST['action'] ) {
		$operation = ps_pen_paper_posted_text( 'pspp_operation' );
		$campaign_id = ps_pen_paper_posted_id( 'campaign_id' );
		$character_id = ps_pen_paper_posted_id( 'character_id' );
		$campaign = ps_pen_paper_get_campaign( $campaign_id );
		$character = $campaign ? ps_pen_paper_get_character( $campaign, $character_id ) : null;
		if ( ! in_array( $operation, array( 'save_character', 'save_portrait', 'equip_items', 'offer_trade', 'change_trade', 'start_adventure', 'advance_adventure' ), true ) || ! $character ||
			(int) $character->post_author !== get_current_user_id() || ! ps_pen_paper_profile_return( $campaign_id, $character_id ) ) {
			ps_pen_paper_fail( __( 'Diese Profilaktion ist nicht erlaubt. Bitte öffne deinen eigenen Charakter erneut.', 'ps-pen-paper' ) );
		}
		ps_pen_paper_handle_admin_action();
	}
}

function ps_pen_paper_community_assets() {
	if ( ps_pen_paper_community_available() && is_singular( 'page' ) && (int) get_option( 'cpccom_profile_page' ) === get_queried_object_id() ) {
		$url = plugin_dir_url( dirname( __DIR__ ) . '/ps-pen-paper.php' );
		wp_enqueue_style( 'ps-pen-paper-game', $url . 'assets/frontend.css', array(), '0.9.2' );
		wp_enqueue_script( 'ps-pen-paper-admin', $url . 'assets/admin.js', array(), '0.26.0', true );
		wp_enqueue_style( 'ps-pen-paper-equipment', $url . 'assets/equipment.css', array( 'ps-pen-paper-game' ), '0.16.1' );
		wp_enqueue_script( 'ps-pen-paper-character', $url . 'assets/character.js', array(), '0.1.0', true );
	}
}
