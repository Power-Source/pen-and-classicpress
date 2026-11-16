<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_community_available() {
	return defined( 'CPC_CORE_PLUGINS' ) &&
		in_array( 'core-profile', explode( ',', CPC_CORE_PLUGINS ), true ) &&
		function_exists( 'cpc_get_profile_tabs' ) && function_exists( 'cpc_render_profile_tab_content' );
}

function ps_pen_paper_community_url( $campaign_id = 0, $character_id = 0, $universe_id = 0 ) {
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
	if ( $universe_id ) { $args['pspp_universe'] = $universe_id; }
	return add_query_arg( $args, $url );
}

function ps_pen_paper_community_tabs( $tabs, $user_id, $viewer_id ) {
	if ( ps_pen_paper_community_available() && ps_pen_paper_can_access_site() &&
		(int) $user_id === get_current_user_id() && (int) $viewer_id === get_current_user_id() ) {
		$tabs['pspp'] = array( 'label' => __( 'Pen & Paper', 'ps-pen-paper' ), 'icon' => 'id-alt', 'priority' => 45 );
	}
	return $tabs;
}

function ps_pen_paper_render_character_picker( $choices, $action, $selected_id = 0, $args = array() ) {
	if ( ! $choices ) { return; }
	$errors = array();
	?>
	<form method="get" action="<?php echo esc_url( $action ); ?>" class="pspp-character-picker">
		<?php foreach ( $args as $key => $value ) : ?><input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"><?php endforeach; ?>
		<label><?php esc_html_e( 'Charakter auswählen', 'ps-pen-paper' ); ?>
			<select name="pspp_character" required>
				<option value=""><?php esc_html_e( 'Bitte wählen', 'ps-pen-paper' ); ?></option>
				<?php foreach ( $choices as $choice ) :
					$character = $choice['character'];
					$scope = $choice['scope'];
					$state = $choice['state'];
					$scope_name = $scope->post_title;
					if ( 'pspp_universe' === $scope->post_type ) {
						$document = ps_pen_paper_universe_document( $scope->ID );
						if ( is_wp_error( $document ) ) {
							$errors[] = $document;
							$scope_name = $document->get_error_message();
						} else { $scope_name = $document['name']; }
					}
					if ( is_wp_error( $state ) ) {
						/* translators: %d: character identifier. */
						$name = sprintf( __( 'Charakter #%d – Prüfung nötig', 'ps-pen-paper' ), $character->ID );
					} else {
						$snapshot = $state['active'] ? $state['active'] : $state['pending'];
						$name = $snapshot ? $snapshot['name'] : __( 'Noch nicht freigegeben', 'ps-pen-paper' );
						if ( ! $state['active'] ) { $name .= ' · ' . __( 'Freigabe ausstehend', 'ps-pen-paper' ); }
					}
					?>
					<option value="<?php echo esc_attr( $character->ID ); ?>" <?php selected( $selected_id, $character->ID ); ?>><?php echo esc_html( $name . ' · ' . ( 'pspp_universe' === $scope->post_type ? __( 'Universum:', 'ps-pen-paper' ) : __( 'Kampagne:', 'ps-pen-paper' ) ) . ' ' . $scope_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<button type="submit"><?php esc_html_e( 'Öffnen', 'ps-pen-paper' ); ?></button>
	</form>
	<?php
	foreach ( $errors as $error ) { echo '<p role="alert">' . esc_html( $error->get_error_message() ) . '</p>'; }
	foreach ( $choices as $choice ) {
		if ( is_wp_error( $choice['state'] ) ) { echo '<p role="alert">' . esc_html( $choice['state']->get_error_message() ) . '</p>'; }
	}
}

function ps_pen_paper_community_content( $html, $tab, $user_id, $atts ) {
	if ( 'pspp' !== $tab ) { return $html; }
	if ( ! ps_pen_paper_community_available() || ! ps_pen_paper_can_access_site() || (int) $user_id !== get_current_user_id() ) {
		return '<p role="alert">' . esc_html__( 'Dieser Charakterbereich ist privat.', 'ps-pen-paper' ) . '</p>';
	}
	ob_start();
	$campaign_id = ps_pen_paper_frontend_id( 'pspp_campaign' );
	$character_id = ps_pen_paper_frontend_id( 'pspp_character' );
	$universe_id = ps_pen_paper_frontend_id( 'pspp_universe' );
	$choices = ps_pen_paper_profile_characters();
	$universes = ps_pen_paper_profile_universe_choices();
	$selection = null;
	foreach ( $choices as $choice ) {
		if ( ! is_wp_error( $character_id ) && (int) $choice['character']->ID === $character_id ) { $selection = $choice; break; }
	}
	if ( 0 === $character_id && ! is_wp_error( $campaign_id ) && 0 === $universe_id ) {
		foreach ( $choices as $choice ) {
			if ( ( $campaign_id && $campaign_id !== (int) $choice['scope']->ID ) ||
				( $universe_id && $universe_id !== $choice['universe_id'] ) ) { continue; }
			$selection = $choice;
			$character_id = (int) $choice['character']->ID;
			break;
		}
	}
	$valid_selection = $selection && ! is_wp_error( $campaign_id ) && ! is_wp_error( $universe_id ) &&
		( ! $campaign_id || $campaign_id === (int) $selection['scope']->ID ) &&
		( ! $universe_id || $universe_id === $selection['universe_id'] );
	$colors = ps_pen_paper_style_defaults();
	if ( ! is_wp_error( $campaign_id ) && ! is_wp_error( $character_id ) && ! is_wp_error( $universe_id ) ) {
		$style_scope = $valid_selection ? $selection['scope']->ID : 0;
		foreach ( $universes as $universe ) {
			if ( ! $style_scope && $universe['id'] === $universe_id ) { $style_scope = $universe_id; }
		}
		if ( $style_scope ) {
			$style = ps_pen_paper_effective_style( $style_scope );
			if ( is_wp_error( $style ) ) { ob_end_clean(); return '<p role="alert">' . esc_html( $style->get_error_message() ) . '</p>'; }
			$colors = $style['colors'];
		}
	}
	echo '<div class="pspp-game pspp-profile" style="' . esc_attr( ps_pen_paper_style_variables( $colors ) ) . '"><h2>' . esc_html__( 'Meine Charaktere', 'ps-pen-paper' ) . '</h2>';
	ps_pen_paper_render_notice();
	$args = array( 'tab' => 'pspp' );
	if ( get_option( 'cpccom_profile_permalinks' ) ) { $args['user_id'] = get_current_user_id(); }
	ps_pen_paper_render_character_picker( $choices, ps_pen_paper_community_url(), is_wp_error( $character_id ) ? 0 : $character_id, $args );
	add_action( 'pspp_form_context', 'ps_pen_paper_profile_form_context' );
	add_filter( 'pspp_form_action', 'ps_pen_paper_profile_form_action' );
	if ( is_wp_error( $campaign_id ) || is_wp_error( $character_id ) || is_wp_error( $universe_id ) ) {
		echo '<p role="alert">' . esc_html__( 'Die Charakterauswahl ist ungültig.', 'ps-pen-paper' ) . '</p>';
	} elseif ( $character_id ) {
		$campaign = $selection ? $selection['scope'] : null;
		$character = $selection ? $selection['character'] : null;
		if ( ! $valid_selection ) {
			echo '<p role="alert">' . esc_html__( 'Dieser Charakter gehört nicht zu deinem zugänglichen Profilbereich.', 'ps-pen-paper' ) . '</p>';
		} else {
			ps_pen_paper_render_characters( $campaign, $character_id, 0, ps_pen_paper_community_url(), 'profile' );
		}
	} elseif ( ! $choices ) {
		echo '<p>' . esc_html__( 'Du hast noch keine eigenen Charaktere. Wähle ein verfügbares Universum. Einladungen und Spielrunden findest du in der Game-Lounge.', 'ps-pen-paper' ) . '</p>';
	}
	echo '<section id="pspp-profile-create"><h3>' . esc_html__( 'Charakter erstellen', 'ps-pen-paper' ) . '</h3>';
	if ( $universes ) : ?>
		<form method="get" action="<?php echo esc_url( ps_pen_paper_community_url() ); ?>" class="pspp-character-picker">
			<?php foreach ( $args as $key => $value ) : ?><input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"><?php endforeach; ?>
			<label><?php esc_html_e( 'Universum auswählen', 'ps-pen-paper' ); ?><select name="pspp_universe" required>
				<option value=""><?php esc_html_e( 'Bitte wählen', 'ps-pen-paper' ); ?></option>
				<?php foreach ( $universes as $universe ) : ?><option value="<?php echo esc_attr( $universe['id'] ); ?>" <?php selected( is_wp_error( $universe_id ) ? 0 : $universe_id, $universe['id'] ); ?>><?php echo esc_html( $universe['name'] ); ?></option><?php endforeach; ?>
			</select></label>
			<button type="submit"><?php esc_html_e( 'Weiter', 'ps-pen-paper' ); ?></button>
		</form>
		<?php if ( ! is_wp_error( $universe_id ) && $universe_id && ! $character_id ) { ps_pen_paper_render_profile_universe_characters( $universe_id ); } ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Noch kein Universum verfügbar. Bitte die Spielleitung um eine Einladung; danach kannst du hier deinen Charakter erstellen.', 'ps-pen-paper' ); ?></p>
	<?php endif;
	echo '</section>';
	remove_filter( 'pspp_form_action', 'ps_pen_paper_profile_form_action' );
	remove_action( 'pspp_form_context', 'ps_pen_paper_profile_form_context' );
	echo '</div>';
	return ob_get_clean();
}

function ps_pen_paper_profile_form_context() {
	echo '<input type="hidden" name="pspp_profile" value="' . esc_attr( wp_create_nonce( 'pspp_profile_' . get_current_user_id() ) ) . '">';
}

function ps_pen_paper_profile_form_action( $url ) {
	return ps_pen_paper_community_url();
}

function ps_pen_paper_profile_operation_allowed( $operation, $input ) {
	if ( in_array( $operation, array( 'profile_create_character', 'save_character', 'save_base_character', 'select_portrait_preset', 'equip_items', 'use_item', 'offer_trade' ), true ) ) { return true; }
	if ( 'profile_portrait' === $operation ) { return in_array( $input['portrait_operation'] ?? '', array( 'upload', 'remove', 'presentation' ), true ); }
	if ( 'save_portrait' === $operation ) { return in_array( $input['portrait_action'] ?? '', array( 'upload', 'remove' ), true ); }
	if ( 'change_trade' === $operation ) { return in_array( $input['trade_action'] ?? '', array( 'accept', 'decline', 'cancel', 'counter' ), true ); }
	return false;
}

function ps_pen_paper_profile_return( $campaign_id, $character_id ) {
	if ( ! isset( $_POST['pspp_profile'] ) ) { return ''; }
	if ( ! ps_pen_paper_profile_operation_allowed( ps_pen_paper_posted_text( 'pspp_operation' ), wp_unslash( $_POST ) ) ) {
		ps_pen_paper_fail( __( 'Diese Aktion gehört nicht ins Charakterprofil. Öffne dafür die Game-Lounge oder die Spielverwaltung.', 'ps-pen-paper' ) );
	}
	$url = ps_pen_paper_get_universe( $campaign_id )
		? ps_pen_paper_community_url( 0, $character_id, $campaign_id )
		: ps_pen_paper_community_url( $campaign_id, $character_id );
	if ( ! is_string( $_POST['pspp_profile'] ) || ! wp_verify_nonce( $_POST['pspp_profile'], 'pspp_profile_' . get_current_user_id() ) ||
		! $url ) {
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
		if ( ! ps_pen_paper_profile_return( $campaign_id, $character_id ) ) { ps_pen_paper_fail( __( 'Diese Profilaktion ist nicht erlaubt.', 'ps-pen-paper' ) ); }
		if ( ! ps_pen_paper_profile_operation_allowed( $operation, wp_unslash( $_POST ) ) ) {
			ps_pen_paper_fail( __( 'Im Profil verwaltest du nur deinen Charakter. Nutze für Spiele und Einladungen die Game-Lounge und für Leitungsaktionen die Spielverwaltung.', 'ps-pen-paper' ) );
		}
		$entry_actions = array( 'profile_create_character' );
		if ( in_array( $operation, $entry_actions, true ) ) { ps_pen_paper_handle_admin_action(); }
		$campaign = ps_pen_paper_get_character_scope( $campaign_id );
		$character = $campaign ? ps_pen_paper_get_character( $campaign, $character_id ) : null;
		if ( ! $character ||
			(int) $character->post_author !== get_current_user_id() || ! ps_pen_paper_profile_return( $campaign_id, $character_id ) ) {
			ps_pen_paper_fail( __( 'Diese Profilaktion ist nicht erlaubt. Bitte öffne deinen eigenen Charakter erneut.', 'ps-pen-paper' ) );
		}
		if ( in_array( $operation, array( 'equip_items', 'use_item', 'review_item_use', 'end_item_effect' ), true ) ) {
			ps_pen_paper_handle_equipment_action( $operation, 'profile' );
		}
		ps_pen_paper_handle_admin_action();
	}
}

function ps_pen_paper_community_assets() {
	if ( ps_pen_paper_community_available() && is_singular( 'page' ) && (int) get_option( 'cpccom_profile_page' ) === get_queried_object_id() ) {
		$url = plugin_dir_url( dirname( __DIR__ ) . '/ps-pen-paper.php' );
		wp_enqueue_style( 'ps-pen-paper-game', $url . 'assets/frontend.css', array(), '0.9.3' );
		wp_enqueue_script( 'ps-pen-paper-admin', $url . 'assets/admin.js', array(), '0.26.0', true );
		wp_enqueue_style( 'ps-pen-paper-equipment', $url . 'assets/equipment.css', array( 'ps-pen-paper-game' ), '0.16.2' );
		wp_enqueue_script( 'ps-pen-paper-character', $url . 'assets/character.js', array(), '0.1.0', true );
	}
}
