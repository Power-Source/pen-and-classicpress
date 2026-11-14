<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_frontend_page( $page_id ) {
	$page = get_post( $page_id );
	return $page && 'page' === $page->post_type && 'publish' === $page->post_status && has_shortcode( $page->post_content, 'ps_pen_paper' ) ? $page : null;
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
	if ( in_array( $operation, array( 'save_currency', 'change_wallet', 'save_equipment_schema', 'save_item', 'grant_item', 'equip_items', 'review_gear', 'save_portrait', 'save_sheet_style' ), true ) && ps_pen_paper_posted_id( 'pspp_frontend_page' ) === get_queried_object_id() ) {
		ps_pen_paper_handle_admin_action();
	}
	if ( ! in_array( $operation, array( 'save_character', 'review_character', 'roll_dice', 'request_membership', 'review_membership', 'save_access', 'save_backdrop', 'save_style', 'save_universe', 'link_universe', 'save_universe_rules', 'save_base_character', 'adopt_universe_rules', 'attach_base_character', 'save_character_context', 'save_scene', 'publish_scene', 'withdraw_scene', 'select_scene', 'save_folio', 'publish_folio', 'withdraw_folio' ), true ) || ps_pen_paper_posted_id( 'pspp_frontend_page' ) !== get_queried_object_id() ) {
		ps_pen_paper_fail( __( 'Ungültige Spielbereich-Aktion. Bitte öffne das Formular erneut.', 'ps-pen-paper' ) );
	}
	ps_pen_paper_handle_admin_action();
}

function ps_pen_paper_enqueue_frontend() {
	if ( ! ps_pen_paper_is_frontend_page() ) {
		return;
	}
	$url = plugin_dir_url( dirname( __DIR__ ) . '/ps-pen-paper.php' );
	wp_enqueue_style( 'ps-pen-paper-game', $url . 'assets/frontend.css', array(), '0.9.1' );
	wp_enqueue_script( 'ps-pen-paper-admin', $url . 'assets/admin.js', array(), '0.7.0', true );
	wp_enqueue_script( 'ps-pen-paper-dice', $url . 'assets/dice.js', array( 'ps-pen-paper-admin' ), '0.7.0', true );
	wp_enqueue_script( 'ps-pen-paper-table', $url . 'assets/table.js', array(), '0.14.0', true );
	wp_enqueue_style( 'ps-pen-paper-folio', $url . 'assets/folio.css', array( 'ps-pen-paper-game' ), '0.13.0' );
	wp_enqueue_style( 'ps-pen-paper-equipment', $url . 'assets/equipment.css', array( 'ps-pen-paper-game' ), '0.16.0' );
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
	return '<div class="pspp-game pspp-lobby"><p>' . esc_html__( 'Bitte melde dich an, um deine Kampagnen zu sehen.', 'ps-pen-paper' ) . '</p><a class="button button-primary" href="' . esc_url( wp_login_url( ps_pen_paper_game_url( $page_id, $campaign_id ) ) ) . '">' . esc_html__( 'Anmelden', 'ps-pen-paper' ) . '</a><p>' . esc_html__( 'Noch kein Konto? Nutze die normale Registrierung dieser Website, falls angeboten, oder kontaktiere den Betreiber. Anschließend brauchst du die Aufnahme in deine Runde durch die Spielleitung.', 'ps-pen-paper' ) . '</p></div>';
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
	if ( $character_id && ! ps_pen_paper_get_character( $campaign, $character_id ) ) {
		return ps_pen_paper_frontend_message( __( 'Dieser Charakter ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	$style = $campaign ? ps_pen_paper_get_style( $campaign_id ) : array( 'colors' => ps_pen_paper_style_defaults() );
	if ( is_wp_error( $style ) ) {
		return ps_pen_paper_frontend_message( $style->get_error_message() );
	}
	// Shared forms use the core submit_button() helper, also outside the backend.
	require_once ABSPATH . 'wp-admin/includes/template.php';
	ob_start();
	?>
	<div class="pspp-game<?php echo $campaign ? ' pspp-workspace' : ''; ?>" style="<?php echo esc_attr( ps_pen_paper_style_variables( $style['colors'] ) ); ?>">
		<?php if ( ! $campaign ) : ?>
			<?php ps_pen_paper_render_dashboard_navigation( $page_id ); ?>
			<?php if ( 'universes' === $view ) : ?>
				<?php ps_pen_paper_render_notice(); ?>
				<?php ps_pen_paper_render_universes( $page_id ); ?>
			<?php elseif ( 'characters' === $view ) : ?>
				<?php ps_pen_paper_render_character_library( $page_id ); ?>
			<?php elseif ( 'settings' === $view ) : ?>
				<?php ps_pen_paper_render_settings_directory( $page_id ); ?>
			<?php else : ?>
			<?php if ( 'overview' === $view ) { ps_pen_paper_render_dashboard( $page_id ); } ?>
			<h2><?php esc_html_e( 'Deine Kampagnen', 'ps-pen-paper' ); ?></h2>
			<?php $campaigns = ps_pen_paper_get_campaigns(); ?>
			<?php if ( ! $campaigns ) : ?>
				<p><?php esc_html_e( 'Du bist noch keiner Kampagne zugeordnet.', 'ps-pen-paper' ); ?></p>
				<ol class="pspp-onboarding">
					<li><?php esc_html_e( 'Lass dir von der Spielleitung den Kampagnenlink geben.', 'ps-pen-paper' ); ?></li>
					<li><?php esc_html_e( 'Öffne den Link und wähle „Teilnahme beantragen“, sofern die Runde Anfragen erlaubt. Andernfalls nimmt dich die Spielleitung persönlich auf.', 'ps-pen-paper' ); ?></li>
					<li><?php esc_html_e( 'Nach der Freigabe erscheint die Runde hier. Unter „Mein Bogen“ kannst du deinen Charakter erstellen und gegebenenfalls zur Freigabe einreichen.', 'ps-pen-paper' ); ?></li>
				</ol>
			<?php else : ?>
				<div class="pspp-grid">
					<?php foreach ( $campaigns as $item ) : ?>
						<?php if ( ! ps_pen_paper_can_view_campaign( $item ) ) { continue; } ?>
						<article class="pspp-card">
							<h3><a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $item->ID ) ); ?>"><?php echo esc_html( $item->post_title ); ?></a></h3>
							<p class="pspp-multiline"><?php echo esc_html( $item->post_content ); ?></p>
							<?php if ( '1' === get_post_meta( $item->ID, '_pspp_archived', true ) ) : ?>
								<p><?php esc_html_e( 'Archiviert', 'ps-pen-paper' ); ?></p>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php endif; ?>
		<?php else : ?>
			<?php ps_pen_paper_render_table( $campaign, $page_id, $character_id ); ?>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
