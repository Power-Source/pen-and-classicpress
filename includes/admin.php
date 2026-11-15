<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_register_admin_menu() {
	add_menu_page(
		__( 'PS Pen & Paper', 'ps-pen-paper' ),
		__( 'Pen & Paper', 'ps-pen-paper' ),
		'read',
		'ps-pen-paper',
		'ps_pen_paper_render_admin_page',
		'dashicons-book-alt'
	);
	foreach ( ps_pen_paper_admin_sections() as $slug => $label ) {
		add_submenu_page( 'ps-pen-paper', $label, $label, 'read', $slug, 'ps_pen_paper_render_admin_page' );
	}
}

function ps_pen_paper_enqueue_admin_assets( $hook ) {
	$hooks = array( 'toplevel_page_ps-pen-paper' );
	foreach ( array_keys( ps_pen_paper_admin_sections() ) as $slug ) {
		$hooks[] = get_plugin_page_hookname( $slug, 'ps-pen-paper' );
	}
	if ( ! in_array( $hook, $hooks, true ) ) {
		return;
	}
	$url = plugin_dir_url( dirname( __DIR__ ) . '/ps-pen-paper.php' );
	wp_enqueue_style( 'ps-pen-paper-admin', $url . 'assets/admin.css', array(), '0.7.0' );
	wp_enqueue_style( 'ps-pen-paper-game', $url . 'assets/frontend.css', array(), '0.9.3' );
	wp_enqueue_script( 'ps-pen-paper-admin', $url . 'assets/admin.js', array(), '0.26.0', true );
	wp_enqueue_script( 'ps-pen-paper-style', $url . 'assets/styles.js', array(), '0.7.0', true );
	wp_enqueue_script( 'ps-pen-paper-dice', $url . 'assets/dice.js', array( 'ps-pen-paper-admin' ), '0.7.0', true );
	wp_enqueue_style( 'ps-pen-paper-maps', $url . 'assets/maps.css', array( 'ps-pen-paper-game' ), '0.15.0' );
	wp_enqueue_script( 'ps-pen-paper-maps', $url . 'assets/maps.js', array(), '0.13.0', true );
	wp_enqueue_style( 'ps-pen-paper-folio', $url . 'assets/folio.css', array( 'ps-pen-paper-game' ), '0.17.0' );
	wp_enqueue_style( 'ps-pen-paper-equipment', $url . 'assets/equipment.css', array( 'ps-pen-paper-game' ), '0.16.2' );
	wp_enqueue_script( 'ps-pen-paper-character', $url . 'assets/character.js', array(), '0.1.0', true );
}

function ps_pen_paper_admin_url( $campaign_id = 0 ) {
	$args = array( 'page' => 'ps-pen-paper' );
	if ( $campaign_id ) {
		$args['campaign_id'] = $campaign_id;
	}
	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

function ps_pen_paper_fail( $message ) {
	wp_die( esc_html( $message ) );
}

function ps_pen_paper_redirect( $notice, $campaign_id = 0, $page_id = 0, $section = '' ) {
	$url = $page_id ? ps_pen_paper_game_url( $page_id, $campaign_id ) : ps_pen_paper_admin_url( $campaign_id );
	wp_safe_redirect( add_query_arg( 'pspp_notice', $notice, $url ) . ( $page_id && $campaign_id && $section ? '#' . $section : '' ) );
	exit;
}

function ps_pen_paper_posted_id( $key ) {
	return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? absint( $_POST[ $key ] ) : 0;
}

function ps_pen_paper_posted_text( $key, $multiline = false ) {
	if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
		return '';
	}
	$value = wp_unslash( $_POST[ $key ] );
	return $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
}

function ps_pen_paper_site_user( $user_id ) {
	$user = get_userdata( $user_id );
	return $user && is_user_member_of_blog( $user_id, get_current_blog_id() ) ? $user : null;
}

function ps_pen_paper_handle_admin_action() {
	if ( ! current_user_can( 'read' ) ) {
		ps_pen_paper_fail( __( 'Du hast keine Berechtigung für diese Aktion.', 'ps-pen-paper' ) );
	}

	$operation = ps_pen_paper_posted_text( 'pspp_operation' );
	$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	if ( in_array( $operation, array( 'create', 'update', 'save_rules', 'save_rules_migration', 'add_member', 'remove_member' ), true ) ) {
		if ( ! ps_pen_paper_can_access_site() ) {
			ps_pen_paper_fail( __( 'Du hast keinen Zugriff auf den Spielbereich dieser Website.', 'ps-pen-paper' ) );
		}
		if ( isset( $_POST['pspp_frontend_page'] ) && ( ! $page_id || ! ps_pen_paper_frontend_page( $page_id ) ) ) {
			ps_pen_paper_fail( __( 'Die Spielbereich-Seite ist nicht mehr verfügbar. Bitte öffne das Formular erneut.', 'ps-pen-paper' ) );
		}
	}
	if ( 'restore_import' === $operation || 'download_import_backup' === $operation ) { ps_pen_paper_handle_restore_action( $operation ); }
	if ( in_array( $operation, array( 'preview_import', 'configure_import_preview', 'discard_import_preview' ), true ) ) { ps_pen_paper_handle_import_preview_action( $operation ); }
	if ( 'export_campaign' === $operation ) { ps_pen_paper_handle_export_action(); }
	$scope_id = ps_pen_paper_posted_id( 'campaign_id' );
	if ( $scope_id ) {
		$epoch = ps_pen_paper_scope_epoch( $scope_id );
		if ( is_wp_error( $epoch ) || $epoch !== ps_pen_paper_posted_text( 'pspp_scope_epoch' ) ) {
			ps_pen_paper_fail( __( 'Die Runde wurde inzwischen wiederhergestellt. Alte Eingaben wurden nicht übernommen. Bitte lade die Seite neu.', 'ps-pen-paper' ) );
		}
	}
	if ( in_array( $operation, array( 'save_play_options', 'create_play_round', 'change_play_round' ), true ) ) { ps_pen_paper_handle_play_action( $operation, $page_id, $scope_id ); }
	if ( 'group_adventure' === $operation ) { ps_pen_paper_handle_adventure_group_action(); }
	if ( 'leave_campaign' === $operation ) {
		if ( ! ps_pen_paper_frontend_page( $page_id ) ) { ps_pen_paper_fail( __( 'Die Spielbereich-Seite ist nicht mehr verfügbar.', 'ps-pen-paper' ) ); }
		check_admin_referer( 'pspp_campaign_' . $scope_id );
		if ( 'confirmed' !== ps_pen_paper_posted_text( 'leave_confirmation' ) ) { ps_pen_paper_fail( __( 'Bitte bestätige, dass du diese Kampagne verlassen möchtest.', 'ps-pen-paper' ) ); }
		$result = ps_pen_paper_change_membership( $scope_id, get_current_user_id(), 'leave', '', ps_pen_paper_posted_text( 'membership_revision' ) );
		if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
		wp_safe_redirect( ps_pen_paper_game_url( $page_id ) ); exit;
	}
	if ( 'save_universe_style' === $operation ) { ps_pen_paper_handle_universe_style_action(); }
	if ( in_array( $operation, array( 'profile_create_character', 'save_character_policy', 'member_invitation', 'upload_portrait_preset', 'select_portrait_preset', 'profile_portrait' ), true ) ) {
		ps_pen_paper_handle_character_policy_action( $operation );
	}
	if ( in_array( $operation, array( 'save_adventure', 'save_adventure_edge', 'save_adventure_world', 'save_adventure_scenario', 'remove_adventure_edge', 'publish_adventure', 'pause_adventure', 'resume_adventure', 'start_adventure', 'advance_adventure', 'request_adventure_review', 'review_adventure_run', 'cancel_adventure_review' ), true ) ) { ps_pen_paper_handle_adventure_action( $operation ); }
	if ( in_array( $operation, array( 'save_trade_policy', 'offer_trade', 'change_trade' ), true ) ) { ps_pen_paper_handle_trade_action( $operation ); }
	if ( 'transfer_campaign_assets' === $operation ) { ps_pen_paper_handle_transfer_action(); }
	if ( 'save_wiki_link' === $operation ) { ps_pen_paper_handle_wiki_link_action(); }
	if ( in_array( $operation, array( 'configure_wiki', 'prepare_wiki', 'publish_wiki' ), true ) ) { ps_pen_paper_handle_wiki_action( $operation ); }
	if ( in_array( $operation, array( 'save_loot_table', 'roll_loot', 'award_loot' ), true ) ) { ps_pen_paper_handle_loot_action( $operation ); }
	if ( in_array( $operation, array( 'save_npc', 'offer_npc', 'copy_npc', 'approve_npc' ), true ) ) {
		ps_pen_paper_handle_npc_action( $operation );
	}
	if ( in_array( $operation, array( 'save_currency', 'change_wallet' ), true ) ) {
		ps_pen_paper_handle_economy_action( $operation );
	}
	if ( in_array( $operation, array( 'save_equipment_schema', 'save_slot_icon', 'save_item_action_policy', 'set_item_outside', 'use_item', 'review_item_use', 'end_item_effect', 'advance_game_round', 'start_game_session', 'end_game_session', 'save_item', 'grant_item', 'remove_items', 'equip_items', 'review_gear', 'save_portrait', 'save_sheet_style' ), true ) ) {
		ps_pen_paper_handle_equipment_action( $operation );
	}
	if ( in_array( $operation, array( 'save_folio', 'publish_folio', 'withdraw_folio' ), true ) ) {
		ps_pen_paper_handle_folio_action( $operation );
	}
	if ( in_array( $operation, array( 'save_scene', 'publish_scene', 'withdraw_scene', 'select_scene' ), true ) ) {
		ps_pen_paper_handle_scene_action( $operation );
	}
	if ( in_array( $operation, array( 'save_universe_rules', 'save_base_character', 'request_universe_character', 'review_base_character', 'adopt_universe_rules', 'attach_base_character', 'save_character_context', 'copy_shared_character', 'request_base_character', 'reattach_character' ), true ) ) {
		ps_pen_paper_handle_character_context_action( $operation );
	}
	if ( in_array( $operation, array( 'save_universe', 'link_universe' ), true ) ) {
		ps_pen_paper_handle_universe_action( $operation );
	}
	if ( in_array( $operation, array( 'request_membership', 'review_membership', 'save_access', 'save_backdrop', 'invite_user', 'revoke_invitation', 'accept_invitation', 'decline_invitation' ), true ) ) {
		ps_pen_paper_handle_access_action( $operation );
	}
	if ( 'create' === $operation ) {
		check_admin_referer( 'pspp_create' );
		if ( ! ps_pen_paper_can_create_campaign() ) {
			ps_pen_paper_fail( __( 'Du darfst keine Kampagnen erstellen.', 'ps-pen-paper' ) );
		}
		$title = ps_pen_paper_posted_text( 'title' );
		if ( '' === $title ) {
			ps_pen_paper_fail( __( 'Bitte gib einen Kampagnennamen ein.', 'ps-pen-paper' ) );
		}
		$universe_id = ps_pen_paper_posted_id( 'universe_id' );
		$universe = ps_pen_paper_validate_campaign_universe( $universe_id );
		if ( is_wp_error( $universe ) ) { ps_pen_paper_fail( $universe->get_error_message() ); }
		$campaign_id = wp_insert_post(
			wp_slash( array(
				'post_type'    => 'pspp_campaign',
				'post_status'  => 'private',
				'post_title'   => $title,
				'post_content' => ps_pen_paper_posted_text( 'description', true ),
				'post_author'  => get_current_user_id(),
			) ),
			true
		);
		if ( is_wp_error( $campaign_id ) ) {
			ps_pen_paper_fail( $campaign_id->get_error_message() );
		}
		if ( ! $campaign_id ) {
			ps_pen_paper_fail( __( 'Die Kampagne konnte nicht erstellt werden.', 'ps-pen-paper' ) );
		}
		if ( ! add_post_meta( $campaign_id, '_pspp_gm', (string) get_current_user_id() ) ) {
			$deleted = wp_delete_post( $campaign_id, true );
			if ( ! $deleted ) {
				ps_pen_paper_fail( __( 'Die Mitgliedschaft konnte nicht gespeichert werden; die unvollständige Kampagne konnte nicht entfernt werden. Bitte prüfe die Website.', 'ps-pen-paper' ) );
			}
			ps_pen_paper_fail( __( 'Die Kampagne konnte nicht vollständig gespeichert werden.', 'ps-pen-paper' ) );
		}
		$linked = ps_pen_paper_link_new_campaign( $campaign_id, $universe_id, (string) $universe['revision'] );
		if ( is_wp_error( $linked ) ) {
			if ( 'event_recovery_required' === $linked->get_error_code() ) { ps_pen_paper_fail( $linked->get_error_message() ); }
			if ( ! wp_delete_post( $campaign_id, true ) ) { ps_pen_paper_fail( __( 'Die unvollständig zugeordnete Kampagne konnte nicht entfernt werden. Bitte informiere die Administration.', 'ps-pen-paper' ) ); }
			ps_pen_paper_fail( $linked->get_error_message() );
		}
		ps_pen_paper_redirect( 'created', $campaign_id, $page_id, $page_id ? 'pspp-story' : 'pspp-rulebook' );
	}

	if ( 'creator_permission' === $operation ) {
		check_admin_referer( 'pspp_creator_permission' );
		if ( ! current_user_can( 'manage_options' ) ) {
			ps_pen_paper_fail( __( 'Nur Administratoren dürfen diese Berechtigung ändern.', 'ps-pen-paper' ) );
		}
		$user = ps_pen_paper_site_user( ps_pen_paper_posted_id( 'user_id' ) );
		$permission = ps_pen_paper_posted_text( 'permission' );
		if ( ! $user || ! in_array( $permission, array( 'grant', 'revoke' ), true ) ) {
			ps_pen_paper_fail( __( 'Bitte wähle einen gültigen Benutzer und eine gültige Aktion.', 'ps-pen-paper' ) );
		}
		if ( 'grant' === $permission ) {
			$user->add_cap( 'ps_pen_paper_create_campaigns' );
		} else {
			$user->remove_cap( 'ps_pen_paper_create_campaigns' );
		}
		$updated_user = get_userdata( $user->ID );
		$granted = ! empty( $updated_user->caps['ps_pen_paper_create_campaigns'] );
		if ( $granted !== ( 'grant' === $permission ) ) {
			ps_pen_paper_fail( __( 'Die Berechtigung konnte nicht gespeichert werden.', 'ps-pen-paper' ) );
		}
		wp_safe_redirect( add_query_arg( 'pspp_notice', 'permission', ps_pen_paper_dashboard_url( 'settings' ) ) );
		exit;
	}

	$campaign_id = ps_pen_paper_posted_id( 'campaign_id' );
	check_admin_referer( 'pspp_campaign_' . $campaign_id );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! $campaign || ! ps_pen_paper_can_view_campaign( $campaign ) ) {
		ps_pen_paper_fail( __( 'Du darfst diese Kampagne nicht aufrufen.', 'ps-pen-paper' ) );
	}

	if ( 'roll_dice' === $operation ) {
		$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
		if ( isset( $_POST['pspp_frontend_page'] ) && ( ! $page_id || ! ps_pen_paper_frontend_page( $page_id ) ) ) {
			ps_pen_paper_fail( __( 'Die Spielbereich-Seite ist nicht mehr verfügbar. Es wurde nicht gewürfelt.', 'ps-pen-paper' ) );
		}
		if ( 'complete' !== ps_pen_paper_posted_text( 'roll_complete' ) ) {
			ps_pen_paper_fail( __( 'Die Wurfdaten wurden nicht vollständig übertragen. Es wurde nicht gewürfelt.', 'ps-pen-paper' ) );
		}
		$input = isset( $_POST['roll'] ) ? wp_unslash( $_POST['roll'] ) : null;
		$revision = isset( $_POST['roll_rules_revision'] ) ? $_POST['roll_rules_revision'] : null;
		$result = ps_pen_paper_roll_dice( $campaign_id, $input, $revision );
		if ( is_wp_error( $result ) ) {
			ps_pen_paper_fail( $result->get_error_message() );
		}
		$hidden = 'gm' === $input['visibility'] && ! ps_pen_paper_can_manage_campaign( $campaign );
		$args = array( 'pspp_notice' => $hidden ? 'roll_hidden_saved' : 'roll_saved' );
		if ( ! $hidden ) {
			$args['pspp_roll'] = $result;
		}
		$url = $page_id ? ps_pen_paper_game_url( $page_id, $campaign_id ) : ps_pen_paper_admin_url( $campaign_id );
		wp_safe_redirect( add_query_arg( $args, $url ) . '#pspp-dice' );
		exit;
	}

	if ( 'save_character' === $operation || 'review_character' === $operation ) {
		$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
		if ( isset( $_POST['pspp_frontend_page'] ) && ( ! $page_id || ! ps_pen_paper_frontend_page( $page_id ) ) ) {
			ps_pen_paper_fail( __( 'Die Spielbereich-Seite ist nicht mehr verfügbar. Bitte öffne den Charakterbogen erneut.', 'ps-pen-paper' ) );
		}
		$character_id = ps_pen_paper_posted_id( 'character_id' );
		$profile_url = ps_pen_paper_profile_return( $campaign_id, $character_id );
		$revision = isset( $_POST['character_revision'] ) ? $_POST['character_revision'] : null;
		if ( 'save_character' === $operation ) {
			if ( 'complete' !== ps_pen_paper_posted_text( 'character_complete' ) ) {
				ps_pen_paper_fail( __( 'Der Charakterbogen wurde nicht vollständig übertragen. Es wurde nichts gespeichert.', 'ps-pen-paper' ) );
			}
			$input = isset( $_POST['character'] ) ? wp_unslash( $_POST['character'] ) : null;
			$rules_revision = isset( $_POST['character_rules_revision'] ) ? $_POST['character_rules_revision'] : null;
			$result = ps_pen_paper_save_character( $campaign_id, $character_id, ps_pen_paper_posted_id( 'owner_id' ), $input, $revision, $rules_revision, '' !== $profile_url );
			if ( ! is_wp_error( $result ) ) {
				$character_id = $result;
			}
		} else {
			$result = ps_pen_paper_review_character( $campaign_id, $character_id, ps_pen_paper_posted_text( 'decision' ), $revision );
		}
		if ( is_wp_error( $result ) ) {
			ps_pen_paper_fail( $result->get_error_message() );
		}
		$url = $profile_url;
		wp_safe_redirect( add_query_arg( 'pspp_notice', 'character_saved', $url ? $url : ps_pen_paper_character_url( $campaign_id, $character_id, $page_id ) ) );
		exit;
	}

	if ( ! ps_pen_paper_can_manage_campaign( $campaign ) ) {
		ps_pen_paper_fail( __( 'Du darfst diese Kampagne nicht verwalten.', 'ps-pen-paper' ) );
	}

	if ( 'save_style' === $operation ) {
		$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
		if ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) {
			ps_pen_paper_fail( __( 'Die Spielbereich-Seite ist nicht mehr verfügbar.', 'ps-pen-paper' ) );
		}
		if ( 'complete' !== ps_pen_paper_posted_text( 'style_complete' ) ) {
			ps_pen_paper_fail( __( 'Die Farben wurden nicht vollständig übertragen. Es wurde nichts gespeichert.', 'ps-pen-paper' ) );
		}
		$colors = isset( $_POST['style_colors'] ) ? wp_unslash( $_POST['style_colors'] ) : null;
		$revision = isset( $_POST['style_revision'] ) ? $_POST['style_revision'] : null;
		$result = ps_pen_paper_save_style( $campaign_id, $colors, $revision );
		if ( is_wp_error( $result ) ) {
			ps_pen_paper_fail( $result->get_error_message() );
		}
		if ( $page_id ) {
			wp_safe_redirect( add_query_arg( 'pspp_notice', 'style_saved', ps_pen_paper_game_url( $page_id, $campaign_id ) ) . '#pspp-settings' );
			exit;
		}
		ps_pen_paper_redirect( 'style_saved', $campaign_id );
	}

	if ( 'save_rules' === $operation || 'save_rules_migration' === $operation ) {
		if ( 'complete' !== ps_pen_paper_posted_text( 'rules_complete' ) ) {
			ps_pen_paper_fail( __( 'Das Formular wurde nicht vollständig übertragen. Es wurde nichts gespeichert.', 'ps-pen-paper' ) );
		}
		$input = isset( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : null;
		$revision = isset( $_POST['rules_revision'] ) ? $_POST['rules_revision'] : null;
		if ( 'save_rules' === $operation && '1' === ps_pen_paper_posted_text( 'pspp_schema_preview' ) ) {
			ps_pen_paper_show_rules_migration_preview( $campaign, $input, $revision, $page_id );
		}
		$result = 'save_rules_migration' === $operation
			? ps_pen_paper_save_rules_migration( $campaign_id, $input, $revision, isset( $_POST['resolutions'] ) ? wp_unslash( $_POST['resolutions'] ) : array(),
				ps_pen_paper_posted_text( 'migration_confirmation' ), ps_pen_paper_posted_text( 'migration_preview_hash' ) )
			: ps_pen_paper_save_rules( $campaign_id, $input, $revision );
		if ( is_wp_error( $result ) ) {
			ps_pen_paper_fail( $result->get_error_message() );
		}
		ps_pen_paper_redirect( 'rules_saved', $campaign_id, $page_id, 'pspp-rulebook' );
	}

	if ( 'update' === $operation ) {
		$title = ps_pen_paper_posted_text( 'title' );
		if ( '' === $title ) {
			ps_pen_paper_fail( __( 'Bitte gib einen Kampagnennamen ein.', 'ps-pen-paper' ) );
		}
		$result = wp_update_post(
			wp_slash( array(
				'ID'           => $campaign_id,
				'post_title'   => $title,
				'post_content' => ps_pen_paper_posted_text( 'description', true ),
			) ),
			true
		);
		if ( is_wp_error( $result ) ) {
			ps_pen_paper_fail( $result->get_error_message() );
		}
		if ( ! $result ) {
			ps_pen_paper_fail( __( 'Die Kampagne konnte nicht gespeichert werden.', 'ps-pen-paper' ) );
		}
		$archived = isset( $_POST['archived'] ) ? '1' : '0';
		update_post_meta( $campaign_id, '_pspp_archived', $archived );
		if ( get_post_meta( $campaign_id, '_pspp_archived', true ) !== $archived ) {
			ps_pen_paper_fail( __( 'Name und Beschreibung wurden gespeichert, aber der Archivstatus konnte nicht gespeichert werden.', 'ps-pen-paper' ) );
		}
		ps_pen_paper_redirect( 'updated', $campaign_id, $page_id, 'pspp-settings' );
	}

	if ( 'add_member' === $operation ) {
		$result = ps_pen_paper_change_membership( $campaign_id, ps_pen_paper_posted_id( 'user_id' ), 'add', ps_pen_paper_posted_text( 'member_role' ), ps_pen_paper_posted_text( 'membership_revision' ) );
		if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
		ps_pen_paper_redirect( 'member_added', $campaign_id, $page_id, 'pspp-settings' );
	}

	if ( 'remove_member' === $operation ) {
		$user_id = ps_pen_paper_posted_id( 'user_id' );
		$result = ps_pen_paper_change_membership( $campaign_id, $user_id, 'remove', ps_pen_paper_posted_text( 'member_role' ), ps_pen_paper_posted_text( 'membership_revision' ) );
		if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
		ps_pen_paper_redirect( 'member_removed', $user_id === get_current_user_id() && ! current_user_can( 'manage_options' ) ? 0 : $campaign_id, $page_id, 'pspp-settings' );
	}

	ps_pen_paper_fail( __( 'Unbekannte Aktion.', 'ps-pen-paper' ) );
}

function ps_pen_paper_form_start( $operation, $nonce, $campaign_id = 0, $page_id = 0, $multipart = false, $download = false ) {
	$action = apply_filters( 'pspp_form_action', $page_id ? get_permalink( $page_id ) : admin_url( 'admin-post.php' ) );
	?>
	<form method="post" action="<?php echo esc_url( $action ); ?>"<?php if ( $multipart ) { echo ' enctype="multipart/form-data"'; } ?><?php if ( $download ) { echo ' target="_blank" rel="noopener" data-pspp-download'; } ?> data-pspp-form data-unsaved-message="<?php esc_attr_e( 'Es gibt ungespeicherte Änderungen. Möchtest du sie verwerfen?', 'ps-pen-paper' ); ?>">
		<input type="hidden" name="action" value="ps_pen_paper">
		<input type="hidden" name="pspp_operation" value="<?php echo esc_attr( $operation ); ?>">
		<?php if ( $page_id ) : ?>
			<input type="hidden" name="pspp_frontend_page" value="<?php echo esc_attr( $page_id ); ?>">
		<?php endif; ?>
		<?php if ( $campaign_id ) : ?>
			<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign_id ); ?>">
			<?php $epoch = ps_pen_paper_scope_epoch( $campaign_id ); ?>
			<?php if ( is_wp_error( $epoch ) ) { ps_pen_paper_fail( $epoch->get_error_message() ); } ?>
			<input type="hidden" name="pspp_scope_epoch" value="<?php echo esc_attr( $epoch ); ?>">
		<?php endif; ?>
		<?php wp_nonce_field( $nonce ); ?>
		<?php do_action( 'pspp_form_context' ); ?>
	<?php
}

function ps_pen_paper_user_select() {
	$users = get_users( array( 'blog_id' => get_current_blog_id(), 'orderby' => 'display_name', 'order' => 'ASC' ) );
	?>
	<select name="user_id" required>
		<option value=""><?php esc_html_e( 'Benutzer auswählen', 'ps-pen-paper' ); ?></option>
		<?php foreach ( $users as $user ) : ?>
			<option value="<?php echo esc_attr( $user->ID ); ?>"><?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}

function ps_pen_paper_render_notice() {
	$notices = array(
		'created'        => __( 'Kampagne erstellt.', 'ps-pen-paper' ),
		'updated'        => __( 'Kampagne gespeichert.', 'ps-pen-paper' ),
		'member_added'   => __( 'Mitglied hinzugefügt.', 'ps-pen-paper' ),
		'member_removed' => __( 'Mitglied entfernt.', 'ps-pen-paper' ),
		'permission'     => __( 'Berechtigung aktualisiert.', 'ps-pen-paper' ),
		'rules_saved'    => __( 'Regelwerk gespeichert.', 'ps-pen-paper' ),
		'character_saved' => __( 'Charakterstand gespeichert. Offene Anträge werden erst durch Freigabe wirksam.', 'ps-pen-paper' ),
		'style_saved'     => __( 'Frontend-Gestaltung gespeichert.', 'ps-pen-paper' ),
		'roll_saved'      => __( 'Wurf gespeichert.', 'ps-pen-paper' ),
		'roll_hidden_saved' => __( 'Verdeckter Wurf gespeichert. Nur Spielleiter sehen das Ergebnis.', 'ps-pen-paper' ),
		'join_requested' => __( 'Teilnahme angefragt. Die Spielleitung entscheidet über deine Aufnahme.', 'ps-pen-paper' ),
		'join_reviewed' => __( 'Beitrittsanfrage entschieden.', 'ps-pen-paper' ),
		'settings_saved' => __( 'Rundeneinstellungen gespeichert.', 'ps-pen-paper' ),
		'member_invitation_saved' => __( 'Einladungsaktion gespeichert.', 'ps-pen-paper' ),
		'character_policy_saved' => __( 'Charaktervorgaben oder Porträt gespeichert.', 'ps-pen-paper' ),
		'universe_saved' => __( 'Universum gespeichert.', 'ps-pen-paper' ),
		'universe_linked' => __( 'Universumszuordnung gespeichert. Kampagnendaten und Rechte bleiben unverändert.', 'ps-pen-paper' ),
		'context_saved' => __( 'Universumsvorlage, Basisbogen oder Kampagnenkontext gespeichert.', 'ps-pen-paper' ),
		'scene_saved' => __( 'Szenenaktion gespeichert. Freigabe und Anzeige am Tisch sind getrennte Schritte.', 'ps-pen-paper' ),
		'folio_saved' => __( 'Mappenaktion gespeichert. Nur ausdrücklich freigegebene Handouts sind für die Runde sichtbar.', 'ps-pen-paper' ),
		'npc_saved' => __( 'Figur privat gespeichert. Spielerbögen und Personenkarten bleiben unverändert.', 'ps-pen-paper' ),
		'npc_offered' => __( 'Figur an das Universum übergeben. Ohne eigene Universumsleitung bleibt sie bis zu deren Zustimmung ein privater Aufnahmevorschlag.', 'ps-pen-paper' ),
		'npc_copied' => __( 'Vorlagenstand als unabhängige Kampagnenfigur übernommen.', 'ps-pen-paper' ),
		'npc_approved' => __( 'Aufnahme bestätigt. Die Vorlage ist jetzt für Spielleitungen zugeordneter Kampagnen verfügbar.', 'ps-pen-paper' ),
	);
	$notice = isset( $_GET['pspp_notice'] ) && is_string( $_GET['pspp_notice'] ) ? sanitize_key( wp_unslash( $_GET['pspp_notice'] ) ) : '';
	if ( isset( $notices[ $notice ] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notices[ $notice ] ) . '</p></div>';
	}
}

function ps_pen_paper_render_admin_page() {
	if ( ! current_user_can( 'read' ) ) {
		ps_pen_paper_fail( __( 'Du hast keine Berechtigung, diese Seite aufzurufen.', 'ps-pen-paper' ) );
	}
	$campaign_id = isset( $_GET['campaign_id'] ) && is_scalar( $_GET['campaign_id'] ) ? absint( $_GET['campaign_id'] ) : 0;
	$campaign = $campaign_id ? ps_pen_paper_get_campaign( $campaign_id ) : null;
	if ( $campaign_id && ( ! $campaign || ! ps_pen_paper_can_view_campaign( $campaign ) ) ) {
		ps_pen_paper_fail( __( 'Du darfst diese Kampagne nicht ansehen.', 'ps-pen-paper' ) );
	}
	?>
	<div class="wrap pspp-admin">
		<h1><?php esc_html_e( 'PS Pen & Paper', 'ps-pen-paper' ); ?></h1>
		<p><?php esc_html_e( 'Frontend-Spielbereich: Veröffentliche eine Seite mit [ps_pen_paper]. Optional steht die Seitenvorlage „PS Pen & Paper – Spielbereich“ ohne Theme-Layout zur Verfügung.', 'ps-pen-paper' ); ?></p>
		<?php ps_pen_paper_render_notice(); ?>
		<?php ps_pen_paper_render_dashboard_navigation(); ?>
		<?php if ( $campaign ) : ?>
			<p class="pspp-navigation"><a href="<?php echo esc_url( ps_pen_paper_admin_url() ); ?>"><?php esc_html_e( '← Alle Kampagnen', 'ps-pen-paper' ); ?></a></p>
			<?php ps_pen_paper_render_campaign( $campaign ); ?>
		<?php elseif ( isset( $_GET['page'] ) && 'ps-pen-paper-universes' === $_GET['page'] ) : ?>
			<div class="pspp-game"><?php ps_pen_paper_render_universes(); ?></div>
		<?php elseif ( isset( $_GET['page'] ) && 'ps-pen-paper-campaigns' === $_GET['page'] ) : ?>
			<?php ps_pen_paper_render_campaign_list(); ?>
		<?php elseif ( isset( $_GET['page'] ) && 'ps-pen-paper-characters' === $_GET['page'] ) : ?>
			<div class="pspp-game"><?php ps_pen_paper_render_character_library(); ?></div>
		<?php elseif ( isset( $_GET['page'] ) && 'ps-pen-paper-settings' === $_GET['page'] ) : ?>
			<?php ps_pen_paper_render_settings_directory(); ?>
			<?php ps_pen_paper_render_creator_permissions(); ?>
		<?php else : ?>
			<div class="pspp-game"><?php ps_pen_paper_render_dashboard(); ?></div>
		<?php endif; ?>
	</div>
	<?php
}

function ps_pen_paper_render_campaign_list() {
	$campaigns = ps_pen_paper_get_campaigns();
	?>
	<h2><?php esc_html_e( 'Kampagnen', 'ps-pen-paper' ); ?></h2>
	<?php if ( empty( $campaigns ) ) : ?>
		<p><?php esc_html_e( 'Noch keine Kampagnen vorhanden.', 'ps-pen-paper' ); ?></p>
	<?php else : ?>
		<ul class="pspp-campaign-list">
			<?php foreach ( $campaigns as $campaign ) : ?>
				<?php if ( ! ps_pen_paper_can_view_campaign( $campaign ) ) { continue; } ?>
				<li>
					<a href="<?php echo esc_url( ps_pen_paper_admin_url( $campaign->ID ) ); ?>"><?php echo esc_html( $campaign->post_title ); ?></a>
					<?php if ( '1' === get_post_meta( $campaign->ID, '_pspp_archived', true ) ) : ?>
						<?php esc_html_e( '(archiviert)', 'ps-pen-paper' ); ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php ps_pen_paper_render_campaign_create_form(); ?>
	<?php
}

function ps_pen_paper_render_campaign_create_form( $page_id = 0 ) {
	if ( ! ps_pen_paper_can_create_campaign() ) { return; }
	$universes = array();
	foreach ( ps_pen_paper_get_universes() as $universe ) {
		$document = ps_pen_paper_universe_document( $universe->ID );
		if ( is_wp_error( $document ) ) { echo '<p role="alert">' . esc_html( $document->get_error_message() ) . '</p>'; }
		else { $universes[ $universe->ID ] = $document['name']; }
	}
	if ( ! $universes ) {
		echo '<h2>' . esc_html__( 'Zuerst ein Universum erstellen', 'ps-pen-paper' ) . '</h2><p>' .
			esc_html__( 'Jede Kampagne gehört zu einem Universum. Lege zunächst deine Welt an; danach kannst du Kampagnen hinzufügen.', 'ps-pen-paper' ) .
			'</p><a class="button button-primary" href="' . esc_url( ps_pen_paper_dashboard_url( 'universes', $page_id ) ) . '">' . esc_html__( 'Universum erstellen', 'ps-pen-paper' ) . '</a>';
		return;
	}
	?>
		<h2><?php esc_html_e( 'Neue Kampagne', 'ps-pen-paper' ); ?></h2>
		<p><?php esc_html_e( 'Name eingeben und anlegen. Danach schreibst du die erste Szene; eine Karte ist optional. Regeln, Charaktere und weitere Einstellungen kannst du anschließend ergänzen.', 'ps-pen-paper' ); ?></p>
		<?php ps_pen_paper_form_start( 'create', 'pspp_create', 0, $page_id ); ?>
			<p><label><?php esc_html_e( 'Universum', 'ps-pen-paper' ); ?><select name="universe_id" required>
				<option value=""><?php esc_html_e( 'Bitte wählen', 'ps-pen-paper' ); ?></option>
				<?php foreach ( $universes as $universe_id => $name ) : ?><option value="<?php echo esc_attr( $universe_id ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?>
			</select></label></p>
			<p><label for="pspp-new-title"><?php esc_html_e( 'Name', 'ps-pen-paper' ); ?></label><br>
			<input id="pspp-new-title" name="title" type="text" class="regular-text" required></p>
			<p><label for="pspp-new-description"><?php esc_html_e( 'Beschreibung', 'ps-pen-paper' ); ?></label><br>
			<textarea id="pspp-new-description" name="description" rows="4" class="large-text"></textarea></p>
			<?php submit_button( __( 'Kampagne erstellen', 'ps-pen-paper' ) ); ?>
		</form>
	<?php
}

function ps_pen_paper_render_creator_permissions() {
	if ( current_user_can( 'manage_options' ) ) : ?>
		<h2><?php esc_html_e( 'Berechtigung zum Erstellen von Universen und Kampagnen', 'ps-pen-paper' ); ?></h2>
		<p><?php esc_html_e( 'Administratoren können immer Universen und Kampagnen erstellen. Die bestehende Erstellberechtigung gilt für beide Bereiche. Hier kannst du sie weiteren Mitgliedern dieser Website geben oder entziehen.', 'ps-pen-paper' ); ?></p>
		<?php
		$creators = get_users( array( 'blog_id' => get_current_blog_id(), 'orderby' => 'display_name', 'order' => 'ASC' ) );
		$creator_names = array();
		foreach ( $creators as $creator ) {
			if ( ! empty( $creator->caps['ps_pen_paper_create_campaigns'] ) ) {
				$creator_names[] = $creator->display_name . ' (' . $creator->user_login . ')';
			}
		}
		?>
		<p>
			<strong><?php esc_html_e( 'Zusätzlich berechtigt:', 'ps-pen-paper' ); ?></strong>
			<?php echo esc_html( $creator_names ? implode( ', ', $creator_names ) : __( 'Niemand', 'ps-pen-paper' ) ); ?>
		</p>
		<?php ps_pen_paper_form_start( 'creator_permission', 'pspp_creator_permission' ); ?>
			<p><label><?php esc_html_e( 'Benutzer', 'ps-pen-paper' ); ?> <?php ps_pen_paper_user_select(); ?></label></p>
			<p><label for="pspp-permission"><?php esc_html_e( 'Aktion', 'ps-pen-paper' ); ?></label>
			<select id="pspp-permission" name="permission">
				<option value="grant"><?php esc_html_e( 'Berechtigung erteilen', 'ps-pen-paper' ); ?></option>
				<option value="revoke"><?php esc_html_e( 'Berechtigung entziehen', 'ps-pen-paper' ); ?></option>
			</select></p>
			<?php submit_button( __( 'Berechtigung speichern', 'ps-pen-paper' ) ); ?>
		</form>
	<?php endif; ?>
	<?php
}

function ps_pen_paper_render_campaign( $campaign ) {
	$can_manage = ps_pen_paper_can_manage_campaign( $campaign );
	$character_id = isset( $_GET['character_id'] ) && is_scalar( $_GET['character_id'] ) ? absint( $_GET['character_id'] ) : 0;
	if ( $character_id ) {
		ps_pen_paper_render_characters( $campaign, $character_id );
		return;
	}
	?>
	<h2><?php echo esc_html( $campaign->post_title ); ?></h2>
	<nav class="pspp-navigation" aria-label="<?php esc_attr_e( 'Kampagneneinstellungen', 'ps-pen-paper' ); ?>">
		<a href="#pspp-dice"><?php esc_html_e( 'Würfel / Protokoll', 'ps-pen-paper' ); ?></a>
		<a href="#pspp-log"><?php esc_html_e( 'Spielprotokoll', 'ps-pen-paper' ); ?></a>
		<a href="#pspp-characters"><?php esc_html_e( 'Charaktere', 'ps-pen-paper' ); ?></a>
		<a href="#pspp-rules-heading"><?php esc_html_e( 'Regelwerk', 'ps-pen-paper' ); ?></a>
		<?php if ( $can_manage ) : ?>
			<a href="#pspp-style"><?php esc_html_e( 'Style', 'ps-pen-paper' ); ?></a>
		<?php endif; ?>
	</nav>
	<?php if ( $can_manage ) : ?>
		<?php ps_pen_paper_render_campaign_settings( $campaign ); ?>
	<?php else : ?>
		<p><?php echo nl2br( esc_html( $campaign->post_content ) ); ?></p>
	<?php endif; ?>
	<?php
	$style = ps_pen_paper_get_style( $campaign->ID );
	if ( is_wp_error( $style ) ) { ps_pen_paper_fail( $style->get_error_message() ); }
	?>
	<div class="pspp-game" style="<?php echo esc_attr( ps_pen_paper_style_variables( $style['colors'] ) ); ?>">
		<?php ps_pen_paper_render_dice( $campaign ); ?>
	</div>
	<?php ps_pen_paper_render_characters( $campaign ); ?>
	<?php ps_pen_paper_render_rules( $campaign, $can_manage ); ?>
	<?php if ( $can_manage ) : ?><section id="pspp-story" class="pspp-game"><?php ps_pen_paper_render_story( $campaign, 0 ); ?></section><?php endif; ?>
	<?php if ( $can_manage ) : ?><section id="pspp-folio" class="pspp-game"><?php ps_pen_paper_render_folio( $campaign, 0 ); ?></section><?php endif; ?>
	<?php if ( $can_manage ) : ?><section id="pspp-equipment" class="pspp-game"><?php ps_pen_paper_render_equipment_catalogue( $campaign, 0 ); ?></section><?php endif; ?>
	<?php if ( $can_manage ) : ?><section id="pspp-settings"><?php ps_pen_paper_render_access_settings( $campaign ); ?></section><?php endif; ?>
	<?php ps_pen_paper_render_campaign_members( $campaign ); ?>
	<?php
}

function ps_pen_paper_render_campaign_settings( $campaign, $page_id = 0 ) {
	if ( ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return; }
	?>
		<?php ps_pen_paper_form_start( 'update', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<p><label for="pspp-title"><?php esc_html_e( 'Name', 'ps-pen-paper' ); ?></label><br>
			<input id="pspp-title" name="title" type="text" class="regular-text" value="<?php echo esc_attr( $campaign->post_title ); ?>" required></p>
			<p><label for="pspp-description"><?php esc_html_e( 'Beschreibung', 'ps-pen-paper' ); ?></label><br>
			<textarea id="pspp-description" name="description" rows="4" class="large-text"><?php echo esc_textarea( $campaign->post_content ); ?></textarea></p>
			<p><label class="pspp-check"><input type="checkbox" name="archived" value="1" <?php checked( '1', get_post_meta( $campaign->ID, '_pspp_archived', true ) ); ?>> <?php esc_html_e( 'Kampagne archivieren', 'ps-pen-paper' ); ?></label></p>
			<?php submit_button( __( 'Kampagne speichern', 'ps-pen-paper' ) ); ?>
		</form>
	<?php
}

function ps_pen_paper_render_campaign_members( $campaign, $page_id = 0 ) {
	$can_manage = ps_pen_paper_can_manage_campaign( $campaign );
	?>
	<h3><?php esc_html_e( 'Mitglieder', 'ps-pen-paper' ); ?></h3>
	<?php $membership = ps_pen_paper_membership_revision( $campaign->ID ); ?>
	<?php if ( is_wp_error( $membership ) ) { ps_pen_paper_fail( $membership->get_error_message() ); } ?>
	<?php if ( $can_manage ) : ?>
		<p><?php esc_html_e( 'Der Ersteller bleibt Spielleiter und kann nicht entfernt werden. Alle Mitglieder müssen bereits Benutzer dieser Website sein.', 'ps-pen-paper' ); ?></p>
	<?php endif; ?>
	<?php
	$members = array(
		'gm'     => get_post_meta( $campaign->ID, '_pspp_gm', false ),
		'player' => get_post_meta( $campaign->ID, '_pspp_player', false ),
	);
	foreach ( $members as $role => $user_ids ) :
		foreach ( $user_ids as $user_id ) :
			$user = get_userdata( (int) $user_id );
			?>
			<div>
				<?php
				echo esc_html( $user ? $user->display_name . ' (' . $user->user_login . ')' : sprintf( __( 'Gelöschter Benutzer #%d', 'ps-pen-paper' ), $user_id ) );
				echo ' – ' . esc_html( 'gm' === $role ? __( 'Spielleiter', 'ps-pen-paper' ) : __( 'Spieler', 'ps-pen-paper' ) );
				?>
				<?php if ( $can_manage && (int) $campaign->post_author !== (int) $user_id ) : ?>
					<?php ps_pen_paper_form_start( 'remove_member', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
						<input type="hidden" name="membership_revision" value="<?php echo esc_attr( $membership['revision'] ); ?>">
						<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
						<input type="hidden" name="member_role" value="<?php echo esc_attr( $role ); ?>">
						<?php submit_button( __( 'Entfernen', 'ps-pen-paper' ), 'secondary', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endforeach; ?>
	<?php if ( $can_manage ) : ?>
		<h3><?php esc_html_e( 'Mitglied hinzufügen', 'ps-pen-paper' ); ?></h3>
		<?php ps_pen_paper_form_start( 'add_member', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<input type="hidden" name="membership_revision" value="<?php echo esc_attr( $membership['revision'] ); ?>">
			<p><label><?php esc_html_e( 'Benutzer', 'ps-pen-paper' ); ?> <?php ps_pen_paper_user_select(); ?></label></p>
			<p><label for="pspp-member-role"><?php esc_html_e( 'Rolle in dieser Kampagne', 'ps-pen-paper' ); ?></label>
			<select id="pspp-member-role" name="member_role">
				<option value="player"><?php esc_html_e( 'Spieler', 'ps-pen-paper' ); ?></option>
				<option value="gm"><?php esc_html_e( 'Spielleiter', 'ps-pen-paper' ); ?></option>
			</select></p>
			<?php submit_button( __( 'Mitglied hinzufügen', 'ps-pen-paper' ) ); ?>
		</form>
	<?php endif; ?>
	<?php
}
