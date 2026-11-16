<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/lounge.php';

function ps_pen_paper_frontend_page( $page_id ) {
	$page = get_post( $page_id );
	return $page && 'page' === $page->post_type && 'publish' === $page->post_status && has_shortcode( $page->post_content, 'ps_pen_paper' ) ? $page : null;
}

function ps_pen_paper_find_frontend_page( $page_id = 0 ) {
	$page = ps_pen_paper_frontend_page( $page_id );
	if ( $page ) { return $page; }
	foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 's' => 'ps_pen_paper', 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $candidate ) {
		if ( ps_pen_paper_frontend_page( $candidate->ID ) ) { return $candidate; }
	}
	return null;
}

function ps_pen_paper_is_frontend_page() {
	return is_singular( 'page' ) && ! is_feed() && ! is_embed() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && (bool) ps_pen_paper_frontend_page( get_queried_object_id() );
}

function ps_pen_paper_page_templates( $templates ) {
	$templates['pspp-game'] = __( 'PS Pen & Paper – Spielbereich', 'ps-pen-paper' );
	return $templates;
}

function ps_pen_paper_frontend_template( $template ) {
	if ( ps_pen_paper_is_frontend_page() && 'pspp-game' === get_page_template_slug( get_queried_object_id() ) ) {
		return dirname( __DIR__ ) . '/templates/game.php';
	}
	return $template;
}

function ps_pen_paper_prepare_frontend() {
	if ( ! ps_pen_paper_is_frontend_page() ) {
		return;
	}
	// Emoticon parsing can exhaust PCRE limits on protected inline images.
	remove_filter( 'the_content', 'convert_smilies', 20 );
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
	header( 'Cache-Control: no-store, private, max-age=0, must-revalidate' );
	header( 'X-Robots-Tag: noindex, nofollow' );
}

function ps_pen_paper_handle_frontend_action() {
	if ( ! ps_pen_paper_is_frontend_page() || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['action'] ) || 'ps_pen_paper' !== $_POST['action'] ) {
		return;
	}
	$operation = ps_pen_paper_posted_text( 'pspp_operation' );
	if ( in_array( $operation, array( 'save_play_options', 'create_play_round', 'change_play_round' ), true ) &&
		ps_pen_paper_posted_id( 'pspp_frontend_page' ) === get_queried_object_id() ) { ps_pen_paper_handle_admin_action(); }
	if ( 'leave_campaign' === $operation && ps_pen_paper_posted_id( 'pspp_frontend_page' ) === get_queried_object_id() ) { ps_pen_paper_handle_admin_action(); }
	if ( in_array( $operation, array( 'save_slot_icon', 'save_item_action_policy', 'set_item_outside', 'use_item', 'review_item_use', 'end_item_effect', 'advance_game_round', 'start_game_session', 'end_game_session' ), true ) &&
		ps_pen_paper_posted_id( 'pspp_frontend_page' ) === get_queried_object_id() ) { ps_pen_paper_handle_admin_action(); }
	if ( in_array( $operation, array( 'save_universe_style', 'profile_create_character', 'save_character_policy', 'member_invitation', 'upload_portrait_preset', 'select_portrait_preset', 'profile_portrait' ), true ) &&
		ps_pen_paper_posted_id( 'pspp_frontend_page' ) === get_queried_object_id() ) { ps_pen_paper_handle_admin_action(); }
	if ( in_array( $operation, array( 'preview_import', 'configure_import_preview', 'discard_import_preview', 'restore_import', 'download_import_backup' ), true ) && ps_pen_paper_posted_id( 'pspp_frontend_page' ) === get_queried_object_id() ) { ps_pen_paper_handle_admin_action(); }
	if ( in_array( $operation, array( 'save_adventure', 'save_adventure_edge', 'save_adventure_world', 'save_adventure_scenario', 'remove_adventure_edge', 'publish_adventure', 'pause_adventure', 'resume_adventure', 'start_adventure', 'advance_adventure', 'request_adventure_review', 'review_adventure_run', 'cancel_adventure_review', 'group_adventure' ), true ) && ps_pen_paper_posted_id( 'pspp_frontend_page' ) === get_queried_object_id() ) { ps_pen_paper_handle_admin_action(); }
	if ( in_array( $operation, array( 'save_trade_policy', 'offer_trade', 'change_trade' ), true ) && ps_pen_paper_posted_id( 'pspp_frontend_page' ) === get_queried_object_id() ) { ps_pen_paper_handle_admin_action(); }
	if ( 'transfer_campaign_assets' === $operation && ps_pen_paper_posted_id( 'pspp_frontend_page' ) === get_queried_object_id() ) { ps_pen_paper_handle_admin_action(); }
	if ( in_array( $operation, array( 'configure_wiki', 'prepare_wiki', 'publish_wiki', 'save_wiki_link' ), true ) && ps_pen_paper_posted_id( 'pspp_frontend_page' ) === get_queried_object_id() ) { ps_pen_paper_handle_admin_action(); }
	if ( in_array( $operation, array( 'request_base_character', 'request_universe_character', 'review_base_character', 'reattach_character', 'save_rules_migration', 'invite_user', 'revoke_invitation', 'accept_invitation', 'decline_invitation' ), true ) && ps_pen_paper_posted_id( 'pspp_frontend_page' ) === get_queried_object_id() ) { ps_pen_paper_handle_admin_action(); }
	if ( in_array( $operation, array( 'save_loot_table', 'roll_loot', 'award_loot' ), true ) && ps_pen_paper_posted_id( 'pspp_frontend_page' ) === get_queried_object_id() ) { ps_pen_paper_handle_admin_action(); }
	if ( in_array( $operation, array( 'save_npc', 'offer_npc', 'copy_npc', 'approve_npc', 'save_currency', 'change_wallet', 'save_equipment_schema', 'save_item', 'grant_item', 'remove_items', 'equip_items', 'review_gear', 'save_portrait', 'save_sheet_style' ), true ) && ps_pen_paper_posted_id( 'pspp_frontend_page' ) === get_queried_object_id() ) {
		ps_pen_paper_handle_admin_action();
	}
	if ( ! in_array( $operation, array( 'create', 'update', 'save_rules', 'add_member', 'remove_member', 'export_campaign', 'save_character', 'review_character', 'roll_dice', 'request_membership', 'review_membership', 'save_access', 'save_backdrop', 'save_style', 'save_universe', 'link_universe', 'save_universe_rules', 'save_base_character', 'adopt_universe_rules', 'attach_base_character', 'save_character_context', 'copy_shared_character', 'save_scene', 'publish_scene', 'withdraw_scene', 'select_scene', 'save_folio', 'publish_folio', 'withdraw_folio' ), true ) || ps_pen_paper_posted_id( 'pspp_frontend_page' ) !== get_queried_object_id() ) {
		ps_pen_paper_fail( __( 'Ungültige Spielbereich-Aktion. Bitte öffne das Formular erneut.', 'ps-pen-paper' ) );
	}
	ps_pen_paper_handle_admin_action();
}

function ps_pen_paper_enqueue_frontend() {
	if ( ! ps_pen_paper_is_frontend_page() ) {
		return;
	}
	$url = plugin_dir_url( dirname( __DIR__ ) . '/ps-pen-paper.php' );
	wp_enqueue_style( 'ps-pen-paper-game', $url . 'assets/frontend.css', array(), '0.29.1' );
	wp_enqueue_script( 'ps-pen-paper-admin', $url . 'assets/admin.js', array(), '0.26.0', true );
	wp_enqueue_script( 'ps-pen-paper-dice', $url . 'assets/dice.js', array( 'ps-pen-paper-admin' ), '0.7.0', true );
	wp_enqueue_script( 'ps-pen-paper-table', $url . 'assets/table.js', array(), '0.29.1', true );
	wp_enqueue_style( 'ps-pen-paper-folio', $url . 'assets/folio.css', array( 'ps-pen-paper-game' ), '0.17.0' );
	wp_enqueue_style( 'ps-pen-paper-equipment', $url . 'assets/equipment.css', array( 'ps-pen-paper-game' ), '0.16.2' );
	wp_enqueue_script( 'ps-pen-paper-character', $url . 'assets/character.js', array(), '0.1.0', true );
	wp_enqueue_style( 'ps-pen-paper-maps', $url . 'assets/maps.css', array( 'ps-pen-paper-game' ), '0.15.0' );
	wp_enqueue_script( 'ps-pen-paper-maps', $url . 'assets/maps.js', array( 'ps-pen-paper-table' ), '0.13.0', true );
	wp_enqueue_script( 'ps-pen-paper-style', $url . 'assets/styles.js', array(), '0.7.0', true );
}

function ps_pen_paper_game_url( $page_id, $campaign_id = 0, $character_id = 0 ) {
	$args = array();
	if ( $campaign_id ) {
		$args['pspp_campaign'] = $campaign_id;
	}
	if ( $character_id ) {
		$args['pspp_character'] = $character_id;
	}
	return add_query_arg( $args, get_permalink( $page_id ) );
}

function ps_pen_paper_frontend_id( $key ) {
	if ( ! isset( $_GET[ $key ] ) ) {
		return 0;
	}
	if ( ! is_string( $_GET[ $key ] ) || ! preg_match( '/^[1-9]\d*$/D', $_GET[ $key ] ) || (string) (int) $_GET[ $key ] !== $_GET[ $key ] ) {
		return new WP_Error( 'invalid_id', __( 'Die angeforderte Kampagne oder der Charakter ist ungültig.', 'ps-pen-paper' ) );
	}
	return (int) $_GET[ $key ];
}

function ps_pen_paper_frontend_message( $message ) {
	return '<div class="pspp-game"><p role="status">' . esc_html( $message ) . '</p></div>';
}

function ps_pen_paper_frontend_login( $page_id, $campaign_id = 0 ) {
	$registration = is_multisite() ? in_array( get_site_option( 'registration' ), array( 'user', 'all' ), true ) : get_option( 'users_can_register' );
	return '<div class="pspp-game pspp-lobby"><h2>' . esc_html__( 'Game-Lounge', 'ps-pen-paper' ) . '</h2><p>' .
		esc_html__( 'Melde dich an, um deine Welten, Einladungen und Spiele zu sehen.', 'ps-pen-paper' ) .
		'</p><a class="button button-primary" href="' . esc_url( wp_login_url( ps_pen_paper_game_url( $page_id, $campaign_id ) ) ) . '">' .
		esc_html__( 'Anmelden', 'ps-pen-paper' ) . '</a>' . ( $registration ? '<a class="button" href="' . esc_url( wp_registration_url() ) . '">' .
			esc_html__( 'Konto erstellen', 'ps-pen-paper' ) . '</a>' : '' ) . '</div>';
}

function ps_pen_paper_frontend_shortcode() {
	if ( ! ps_pen_paper_is_frontend_page() ) {
		return ps_pen_paper_frontend_message( __( 'Bitte verwende den Spielbereich auf einer veröffentlichten Seite.', 'ps-pen-paper' ) );
	}
	$page_id = get_queried_object_id();
	$view = isset( $_GET['pspp_view'] ) ? $_GET['pspp_view'] : 'overview';
	if ( ! is_string( $view ) || ! in_array( $view, array( 'overview', 'universes', 'campaigns', 'characters', 'settings' ), true ) ) {
		return ps_pen_paper_frontend_message( __( 'Dieser Verwaltungsbereich ist nicht verfügbar.', 'ps-pen-paper' ) );
	}
	$campaign_id = ps_pen_paper_frontend_id( 'pspp_campaign' );
	$character_id = ps_pen_paper_frontend_id( 'pspp_character' );
	if ( is_wp_error( $campaign_id ) || is_wp_error( $character_id ) || ( $character_id && ! $campaign_id ) ) {
		return ps_pen_paper_frontend_message( __( 'Die angeforderte Kampagne oder der Charakter ist ungültig.', 'ps-pen-paper' ) );
	}
	$campaign = $campaign_id ? ps_pen_paper_get_campaign( $campaign_id ) : null;
	if ( $campaign && ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) ) ) {
		return ps_pen_paper_render_campaign_preview( $campaign, $page_id );
	}
	if ( ! is_user_logged_in() ) {
		return ps_pen_paper_frontend_login( $page_id, $campaign_id );
	}
	if ( ! ps_pen_paper_can_access_site() ) {
		return ps_pen_paper_frontend_message( __( 'Du hast keinen Zugriff auf den Spielbereich dieser Website.', 'ps-pen-paper' ) );
	}
	if ( $campaign_id && ! $campaign ) {
		return ps_pen_paper_frontend_message( __( 'Diese Kampagne ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	$round_id = ps_pen_paper_frontend_id( 'pspp_round' );
	$mode = $_GET['pspp_mode'] ?? '';
	if ( is_wp_error( $round_id ) || ! is_string( $mode ) || ( '' !== $mode && 'choose' !== $mode && ! isset( ps_pen_paper_play_modes()[ $mode ] ) ) ||
		( ( $round_id || $mode ) && ! $campaign ) ) { return ps_pen_paper_frontend_message( ps_pen_paper_play_error()->get_error_message() ); }
	$round = null; $round_state = $campaign ? ps_pen_paper_play_round( $campaign->ID ) : null;
	if ( is_wp_error( $round_state ) ) { return ps_pen_paper_frontend_message( $round_state->get_error_message() ); }
	if ( $round_state ) {
		$round = $campaign; $campaign = ps_pen_paper_get_campaign( $round_state['source_id'] );
		if ( ! $campaign || ! ps_pen_paper_can_view_campaign( $campaign ) ) { return ps_pen_paper_frontend_message( ps_pen_paper_play_error()->get_error_message() ); }
	} elseif ( $round_id ) {
		$round = ps_pen_paper_get_campaign( $round_id ); $round_state = $round ? ps_pen_paper_play_round( $round_id ) : null;
		if ( ! $round_state || is_wp_error( $round_state ) || $round_state['source_id'] !== (int) $campaign->ID ) { return ps_pen_paper_frontend_message( ps_pen_paper_play_error()->get_error_message() ); }
	}
	if ( $character_id && ! ps_pen_paper_get_character( $round ? $round : $campaign, $character_id ) ) {
		return ps_pen_paper_frontend_message( __( 'Dieser Charakter ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	$lounge = ! $campaign && 'overview' === $view ? ps_pen_paper_lounge_data() : null;
	if ( is_wp_error( $lounge ) ) { return ps_pen_paper_frontend_message( $lounge->get_error_message() ); }
	$universe_id = $lounge ? ps_pen_paper_lounge_universe_id( $lounge ) : 0;
	if ( is_wp_error( $universe_id ) ) { return ps_pen_paper_frontend_message( $universe_id->get_error_message() ); }
	$style_scope = $campaign ? $campaign_id : $universe_id;
	$style = $style_scope ? ps_pen_paper_effective_style( $style_scope ) : array( 'colors' => ps_pen_paper_style_defaults() );
	if ( is_wp_error( $style ) ) {
		return ps_pen_paper_frontend_message( $style->get_error_message() );
	}
	// Shared forms use the core submit_button() helper, also outside the backend.
	require_once ABSPATH . 'wp-admin/includes/template.php';
	ob_start();
	?>
	<div class="pspp-game<?php echo $campaign ? ' pspp-workspace' : ''; ?>" style="<?php echo esc_attr( ps_pen_paper_style_variables( $style['colors'] ) ); ?>">
		<?php if ( ! $campaign ) : ?>
			<?php ps_pen_paper_render_notice(); ?>
			<?php if ( 'overview' === $view ) { ps_pen_paper_render_lounge( $page_id, $lounge, $universe_id ); } ?>
			<?php if ( 'overview' !== $view || ps_pen_paper_can_create_campaign() || ps_pen_paper_get_universes() ||
				array_filter( $lounge['campaigns'], static function ( $row ) { return 'gm' === $row['role']; } ) ) : ?>
			<?php if ( 'overview' === $view ) : ?><details class="pspp-fold"><summary><?php esc_html_e( 'Vorbereiten & verwalten', 'ps-pen-paper' ); ?></summary><?php endif; ?>
			<?php ps_pen_paper_render_dashboard_navigation( $page_id ); ?>
			<?php if ( 'overview' === $view ) : ?></details><?php endif; ?>
			<?php endif; ?>
			<?php if ( 'universes' === $view ) : ?>
				<?php ps_pen_paper_render_universes( $page_id ); ?>
			<?php elseif ( 'characters' === $view ) : ?>
				<?php ps_pen_paper_render_character_library( $page_id ); ?>
			<?php elseif ( 'settings' === $view ) : ?>
				<?php ps_pen_paper_render_settings_directory( $page_id ); ?>
			<?php elseif ( 'campaigns' === $view ) : ?>
			<h2><?php esc_html_e( 'Deine Kampagnen', 'ps-pen-paper' ); ?></h2>
			<?php $campaigns = ps_pen_paper_get_campaigns(); ?>
			<?php if ( ! $campaigns ) : ?>
				<p><?php esc_html_e( 'Du bist noch keiner Kampagne zugeordnet.', 'ps-pen-paper' ); ?></p>
				<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id ) . '#pspp-lounge-invitations' ); ?>"><?php esc_html_e( 'Zur Game-Lounge und meinen Einladungen', 'ps-pen-paper' ); ?></a>
			<?php else : ?>
				<div class="pspp-grid">
					<?php foreach ( $campaigns as $item ) : ?>
						<?php if ( ! ps_pen_paper_can_view_campaign( $item ) ) { continue; } ?>
						<article class="pspp-card">
							<h3><a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $item->ID ) ); ?>"><?php echo esc_html( $item->post_title ); ?></a></h3>
							<p class="pspp-multiline"><?php echo esc_html( wp_html_excerpt( $item->post_content, 220, '…' ) ); ?></p>
							<a class="button button-primary" href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $item->ID ) . '#pspp-play' ); ?>"><?php esc_html_e( 'Spielen', 'ps-pen-paper' ); ?></a>
							<?php if ( ps_pen_paper_can_manage_campaign( $item ) ) : ?><a class="button" href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $item->ID ) . '#pspp-story' ); ?>"><?php esc_html_e( 'Vorbereiten', 'ps-pen-paper' ); ?></a><?php endif; ?>
							<?php if ( '1' === get_post_meta( $item->ID, '_pspp_archived', true ) ) : ?>
								<p><?php esc_html_e( 'Archiviert', 'ps-pen-paper' ); ?></p>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php ps_pen_paper_render_campaign_create_form( $page_id ); ?>
			<?php endif; ?>
		<?php else : ?>
			<?php if ( $round ) { ps_pen_paper_render_play_round( $campaign, $round, $page_id, $round_state, $character_id ); }
			elseif ( $mode ) { ps_pen_paper_render_play_directory( $campaign, $page_id, $mode ); }
			else { ps_pen_paper_render_table( $campaign, $page_id, $character_id ); } ?>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
