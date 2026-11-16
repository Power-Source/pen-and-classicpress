<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_event_labels() {
	return array(
		'condition_policy_saved' => __( 'Universumsregeln für Charakterfolgen geändert', 'ps-pen-paper' ),
		'character_condition_changed' => __( 'Charakterfolgen geändert', 'ps-pen-paper' ),
		'rules_saved'         => __( 'Regelwerk gespeichert', 'ps-pen-paper' ),
		'invitation_saved'    => __( 'Einladung geändert', 'ps-pen-paper' ),
		'character_policy_saved' => __( 'Charaktervorgaben geändert', 'ps-pen-paper' ),
		'member_invitation_saved' => __( 'Mitgliedereinladung geändert', 'ps-pen-paper' ),
		'universe_members_saved' => __( 'Universumsmitgliedschaft geändert', 'ps-pen-paper' ),
		'game_round' => __( 'Spielsitzung oder Runde geändert', 'ps-pen-paper' ),
		'play_options' => __( 'Angebotene Spielarten geändert', 'ps-pen-paper' ),
		'play_round' => __( 'Spielrunde geändert', 'ps-pen-paper' ),
		'item_action_policy' => __( 'Profilrechte für Gegenstände geändert', 'ps-pen-paper' ),
		'wiki_links_saved'    => __( 'Wiki-Verknüpfungen geändert', 'ps-pen-paper' ),
		'campaign_restored'   => __( 'Kampagne wiederhergestellt', 'ps-pen-paper' ),
		'turn_changed'        => __( 'Zug gewechselt', 'ps-pen-paper' ),
		'access_saved'        => __( 'Zugangseinstellungen geändert', 'ps-pen-paper' ),
		'membership_saved'    => __( 'Mitgliedschaft oder Beitrittsanfrage geändert', 'ps-pen-paper' ),
		'universe_saved'      => __( 'Universum gespeichert', 'ps-pen-paper' ),
		'universe_linked'     => __( 'Universumszuordnung geändert', 'ps-pen-paper' ),
		'character_created'   => __( 'Charakter angelegt', 'ps-pen-paper' ),
		'character_saved'     => __( 'Charakter gespeichert', 'ps-pen-paper' ),
		'character_resource_spent' => __( 'Abenteuer-Ressource verbraucht', 'ps-pen-paper' ),
		'character_requested' => __( 'Charakteränderung beantragt', 'ps-pen-paper' ),
		'character_approved'  => __( 'Charakteränderung freigegeben', 'ps-pen-paper' ),
		'character_rejected'  => __( 'Charakteränderung abgelehnt', 'ps-pen-paper' ),
		'character_context'   => __( 'Kampagnenkontext geändert', 'ps-pen-paper' ),
		'scene_saved'         => __( 'Szenenentwurf gespeichert', 'ps-pen-paper' ),
		'scene_published'     => __( 'Szene freigegeben', 'ps-pen-paper' ),
		'scene_withdrawn'     => __( 'Szenenfreigabe zurückgenommen', 'ps-pen-paper' ),
		'scene_selected'      => __( 'Aktive Szene gewechselt', 'ps-pen-paper' ),
		'map_moved'           => __( 'Figur auf der Karte bewegt', 'ps-pen-paper' ),
		'folio_saved'         => __( 'Mappeneintrag gespeichert', 'ps-pen-paper' ),
		'npc_saved'           => __( 'Figur gespeichert', 'ps-pen-paper' ),
		'npc_offered'         => __( 'Figur dem Universum angeboten', 'ps-pen-paper' ),
		'npc_approved'        => __( 'Figur ins Universum aufgenommen', 'ps-pen-paper' ),
		'npc_copied'          => __( 'Universumsvorlage als Figur übernommen', 'ps-pen-paper' ),
		'loot_table_saved'    => __( 'Beutetabelle gespeichert', 'ps-pen-paper' ),
		'loot_rolled'         => __( 'Beutevorschlag ausgewürfelt', 'ps-pen-paper' ),
		'loot_awarded'        => __( 'Beutevorgang abgeschlossen', 'ps-pen-paper' ),
		'wiki_configured'     => __( 'Weltlexikon konfiguriert', 'ps-pen-paper' ),
		'wiki_prepared'       => __( 'Wiki-Artikel vorbereitet', 'ps-pen-paper' ),
		'wiki_published'      => __( 'Wiki-Artikel veröffentlicht', 'ps-pen-paper' ),
		'trade_policy'        => __( 'Handelsregeln geändert', 'ps-pen-paper' ),
		'trade_changed'       => __( 'Handel geändert', 'ps-pen-paper' ),
		'adventure_saved'     => __( 'Soloabenteuer vorbereitet', 'ps-pen-paper' ),
		'adventure_published' => __( 'Soloabenteuer freigegeben oder pausiert', 'ps-pen-paper' ),
		'adventure_run'       => __( 'Soloabenteuer fortgesetzt', 'ps-pen-paper' ),
		'adventure_group'     => __( 'Gruppenabenteuer geändert', 'ps-pen-paper' ),
		'folio_published'     => __( 'Handout freigegeben', 'ps-pen-paper' ),
		'folio_withdrawn'     => __( 'Handout zurückgenommen', 'ps-pen-paper' ),
		'portrait_saved'      => __( 'Charakterporträt geändert oder geprüft', 'ps-pen-paper' ),
		'sheet_style_saved'   => __( 'Bogengestaltung geändert', 'ps-pen-paper' ),
		'equipment_schema'    => __( 'Slots und Seltenheiten geändert', 'ps-pen-paper' ),
		'item_saved'          => __( 'Gegenstandsvorlage gespeichert', 'ps-pen-paper' ),
		'gear_saved'          => __( 'Inventar oder Ausrüstung geändert', 'ps-pen-paper' ),
		'currency_saved'      => __( 'Währungseinstellungen gespeichert', 'ps-pen-paper' ),
		'wallet_saved'        => __( 'Guthaben gebucht', 'ps-pen-paper' ),
	);
}

function ps_pen_paper_register_event_type() {
	register_post_type( 'pspp_event', array(
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => false,
		'show_in_rest'        => false,
		'capability_type'     => array( 'pspp_event', 'pspp_events' ),
		'map_meta_cap'        => true,
		'supports'            => false,
	) );
}

function ps_pen_paper_discard_record( $id, $error ) {
	if ( ! wp_delete_post( $id, true ) ) {
		return new WP_Error( 'cleanup_failed', __( 'Ein unvollständiger Protokolleintrag konnte nicht entfernt werden. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	return $error;
}

function ps_pen_paper_publish_record( $id ) {
	$result = wp_update_post( array( 'ID' => $id, 'post_status' => 'private' ), true );
	if ( is_wp_error( $result ) || ! $result || 'private' !== get_post_status( $id ) ) {
		return new WP_Error( 'save_failed', __( 'Der Protokolleintrag konnte nicht veröffentlicht werden.', 'ps-pen-paper' ) );
	}
	return true;
}

function ps_pen_paper_insert_record( $type, $campaign_id, $metadata, $publish = true ) {
	$id = wp_insert_post( wp_slash( array(
		'post_type'   => $type,
		'post_status' => 'draft',
		'post_parent' => $campaign_id,
		'post_author' => get_current_user_id(),
		'post_title'  => 'PS Pen & Paper Record',
		'meta_input'  => $metadata,
	) ), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	if ( ! $id ) {
		return new WP_Error( 'save_failed', __( 'Der Protokolleintrag konnte nicht gespeichert werden.', 'ps-pen-paper' ) );
	}
	foreach ( $metadata as $key => $value ) {
		if ( $value !== get_post_meta( $id, $key, true ) ) {
			return ps_pen_paper_discard_record( $id, new WP_Error( 'save_failed', __( 'Der Protokolleintrag konnte nicht vollständig gespeichert werden. Es wurde nichts übernommen.', 'ps-pen-paper' ) ) );
		}
	}
	if ( $publish ) {
		$result = ps_pen_paper_publish_record( $id );
		if ( is_wp_error( $result ) ) {
			return ps_pen_paper_discard_record( $id, $result );
		}
	}
	return $id;
}

function ps_pen_paper_save_document_event( $campaign_id, $object_id, $key, $before, $after, $event, $publish_object = false ) {
	return ps_pen_paper_atomic_event( $object_id, function () use ( $campaign_id, $object_id, $key, $before, $after, $event, $publish_object ) {
		return ps_pen_paper_write_document_event( $campaign_id, $object_id, $key, $before, $after, $event, $publish_object );
	} );
}

function ps_pen_paper_atomic_event( $object_id, $write, $extra_tables = array() ) {
	global $wpdb;
	if ( 'wpdb' !== get_class( $wpdb ) || ! ( $wpdb->dbh instanceof mysqli ) ) { return ps_pen_paper_atomic_event_unlocked( $object_id, $write, $extra_tables ); }
	$post = ps_pen_paper_record_post( $object_id );
	$scope_id = $post ? ( in_array( $post->post_type, array( 'pspp_campaign', 'pspp_universe' ), true ) ? (int) $post->ID : (int) $post->post_parent ) : 0;
	if ( ! $scope_id ) { return ps_pen_paper_atomic_event_unlocked( $object_id, $write, $extra_tables ); }
	return ps_pen_paper_scope_lock( $scope_id, function () use ( $scope_id, $object_id, $write, $extra_tables ) {
		if ( ! ps_pen_paper_scope_observe( $scope_id ) ) {
			return new WP_Error( 'scope_changed', __( 'Die Runde wurde inzwischen wiederhergestellt. Alte Eingaben wurden nicht übernommen. Bitte lade die Seite neu.', 'ps-pen-paper' ) );
		}
		return ps_pen_paper_atomic_event_unlocked( $object_id, $write, $extra_tables );
	} );
}

function ps_pen_paper_atomic_event_unlocked( $object_id, $write, $extra_tables = array() ) {
	global $wpdb;
	$unsupported = new WP_Error( 'transaction_unavailable', __( 'Diese Änderung benötigt eine sichere Datenbanktransaktion. Sie wurde nicht übernommen. Bitte informiere den Administrator über die erforderliche InnoDB-Unterstützung und die Standard-Datenbankverbindung.', 'ps-pen-paper' ) );
	if ( 'wpdb' !== get_class( $wpdb ) || ! ( $wpdb->dbh instanceof mysqli ) || ! is_int( $wpdb->reconnect_retries ) ) {
		return $unsupported;
	}
	if ( ! is_array( $extra_tables ) || array_diff( $extra_tables, array( $wpdb->terms, $wpdb->term_taxonomy, $wpdb->term_relationships, $wpdb->termmeta ) ) ) { return $unsupported; }
	$required_tables = array_values( array_unique( array_merge( array( $wpdb->posts, $wpdb->postmeta ), $extra_tables ) ) );
	$tables = $wpdb->get_results( $wpdb->prepare(
		'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . implode( ',', array_fill( 0, count( $required_tables ), '%s' ) ) . ')',
		$required_tables
	), ARRAY_A );
	if ( $wpdb->last_error || ! is_array( $tables ) || count( $required_tables ) !== count( $tables ) || array( 'InnoDB' ) !== array_values( array_unique( array_column( $tables, 'ENGINE' ) ) ) || '1' !== $wpdb->get_var( 'SELECT @@session.autocommit' ) ) {
		return $unsupported;
	}
	// This configures our next transaction and refuses an already active transaction.
	$suppressed = $wpdb->suppress_errors( true );
	try {
		$ready = false !== $wpdb->query( 'SET TRANSACTION READ WRITE' );
	} finally {
		$wpdb->suppress_errors( $suppressed );
	}
	if ( ! $ready ) { return $unsupported; }
	$connection = $wpdb->dbh;
	$retries = $wpdb->reconnect_retries;
	$cache_suspended = wp_suspend_cache_addition();
	$disable_query_cache = function ( $query ) { $query->set( 'cache_results', false ); };
	$recovery = new WP_Error( 'event_recovery_required', __( 'Der Abschluss der Änderung konnte nicht eindeutig bestätigt werden. Bitte ändere diesen Stand nicht erneut und informiere den Administrator.', 'ps-pen-paper' ), array( 'object_id' => (int) $object_id ) );
	$result = new WP_Error( 'save_failed', __( 'Die Änderung konnte nicht innerhalb einer sicheren Datenbanktransaktion gespeichert werden. Sie wurde nicht übernommen.', 'ps-pen-paper' ) );
	$record_id = 0;
	$started = false;
	// Reconnecting would continue writes outside the original transaction.
	$wpdb->reconnect_retries = 0;
	wp_suspend_cache_addition( true );
	add_action( 'pre_get_posts', $disable_query_cache, PHP_INT_MAX );
	try {
		$started = mysqli_begin_transaction( $connection );
		if ( $started && (int) $object_id === (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $object_id ) ) ) {
			clean_post_cache( $object_id );
			$result = $write();
			if ( is_int( $result ) && $result >= 0 ) {
				$record_id = $result;
				if ( mysqli_commit( $connection ) ) {
					$started = false;
					$result = true;
				} else {
					$result = $recovery;
				}
			} elseif ( ! is_wp_error( $result ) ) {
				$result = new WP_Error( 'save_failed', __( 'Der Schreibvorgang lieferte keinen gültigen Protokollstand. Die Änderung wurde nicht übernommen.', 'ps-pen-paper' ) );
			}
		}
	} finally {
		$rolled_back = ! $started || mysqli_rollback( $connection );
		if ( ! $rolled_back ) {
			error_log( 'PS Pen & Paper: transaction rollback failed for object #' . (int) $object_id );
		}
		$wpdb->reconnect_retries = $retries;
		remove_action( 'pre_get_posts', $disable_query_cache, PHP_INT_MAX );
		wp_suspend_cache_addition( $cache_suspended );
		clean_post_cache( $object_id );
		if ( $record_id ) { clean_post_cache( $record_id ); }
	}
	return $rolled_back ? $result : $recovery;
}

function ps_pen_paper_write_document_event( $campaign_id, $object_id, $key, $before, $after, $event, $publish_object ) {
	if ( '_pspp_gear' === $key ) {
		$target = ps_pen_paper_shared_gear_target( $object_id );
		if ( is_wp_error( $target ) ) { return $target; }
		if ( $target ) { $campaign_id = $target['scope_id']; $object_id = $target['character_id']; }
	}
	$event['version'] = 1;
	$event['actor_name'] = wp_get_current_user()->display_name;
	$event['object_id'] = (int) $object_id;
	$event['before'] = $before;
	$event['after'] = $after;
	$id = ps_pen_paper_insert_record( 'pspp_event', $campaign_id, array(
		'_pspp_event'      => $event,
		'_pspp_visibility' => $event['visibility'],
		'_pspp_recipient'  => (string) $event['recipient_id'],
		'_pspp_actor'      => (string) get_current_user_id(),
	), false );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	$initial = 0 === $before['revision'];
	$saved = $initial
		? add_post_meta( $object_id, $key, wp_slash( $after ), true )
		: update_post_meta( $object_id, $key, wp_slash( $after ), $before );
	if ( ! $saved ) {
		return ps_pen_paper_discard_record( $id, new WP_Error( 'save_failed', __( 'Die Änderung konnte nicht gespeichert werden. Lade die Seite neu, um zwischenzeitliche Änderungen zu prüfen.', 'ps-pen-paper' ) ) );
	}
	$result = $publish_object ? ps_pen_paper_publish_record( $object_id ) : true;
	if ( ! is_wp_error( $result ) ) {
		$result = ps_pen_paper_publish_record( $id );
	}
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	return $id;
}

function ps_pen_paper_get_event( $campaign, $id, $player_view = false ) {
	$error = new WP_Error( 'forbidden', __( 'Dieser Protokolleintrag ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	$universe = $campaign && 'pspp_universe' === $campaign->post_type;
	if ( ! current_user_can( 'read' ) || ! ( $universe ? ps_pen_paper_can_manage_universe( $campaign ) : ps_pen_paper_can_view_campaign( $campaign ) ) ) {
		return $error;
	}
	$post = ps_pen_paper_record_post( $id );
	if ( ! $post || 'pspp_event' !== $post->post_type || 'private' !== $post->post_status || (int) $post->post_parent !== (int) $campaign->ID ) {
		return $error;
	}
	$event = get_post_meta( $id, '_pspp_event', true );
	if ( ! is_array( $event ) || ! isset( $event['version'], $event['kind'], $event['visibility'], $event['recipient_id'], $event['actor_name'], $event['object_id'], $event['before'], $event['after'], $event['fields'], $event['rules_revision'] )
		|| 1 !== $event['version'] || ! is_string( $event['kind'] ) || ! array_key_exists( $event['kind'], ps_pen_paper_event_labels() )
		|| ! in_array( $event['visibility'], array( 'group', 'owner', 'gm' ), true ) || ! is_array( $event['before'] ) || ! is_array( $event['after'] ) || ! is_array( $event['fields'] ) ) {
		return new WP_Error( 'invalid_event', __( 'Ein Protokolleintrag ist beschädigt. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	if ( ( $player_view || ! ( $universe ? ps_pen_paper_can_manage_universe( $campaign ) : ps_pen_paper_can_manage_campaign( $campaign ) ) ) && 'group' !== $event['visibility'] && ( 'gm' === $event['visibility'] || get_current_user_id() !== $event['recipient_id'] ) ) {
		return $error;
	}
	$event['id'] = $post->ID;
	if ( in_array( $event['kind'], array( 'condition_policy_saved', 'character_condition_changed' ), true ) && ! ps_pen_paper_valid_condition_event( $event ) ) {
		return ps_pen_paper_condition_error();
	}
	if ( in_array( $event['kind'], array( 'play_options', 'play_round' ), true ) ) {
		$validator = 'play_options' === $event['kind'] ? 'ps_pen_paper_valid_play_options' : 'ps_pen_paper_valid_play_round';
		$visibility = 'play_options' === $event['kind'] ? 'gm' : 'group';
		if ( $visibility !== $event['visibility'] || 0 !== $event['recipient_id'] || ! $validator( $event['before'] ) || ! $validator( $event['after'] ) ||
			$event['after']['revision'] !== $event['before']['revision'] + 1 ) { return ps_pen_paper_play_error(); }
	}
	if ( in_array( $event['kind'], array( 'game_round', 'item_action_policy' ), true ) ) {
		$validator = 'game_round' === $event['kind'] ? 'ps_pen_paper_validate_game_round' : 'ps_pen_paper_validate_item_action_policy';
		$visibility = 'game_round' === $event['kind'] ? 'group' : 'gm';
		if ( $visibility !== $event['visibility'] || 0 !== $event['recipient_id'] || ! $validator( $event['before'] ) || ! $validator( $event['after'] ) ||
			$event['after']['revision'] !== $event['before']['revision'] + 1 ) {
			return new WP_Error( 'invalid_event', __( 'Die Spielrunden- oder Gegenstandsrechte im Protokoll sind beschädigt.', 'ps-pen-paper' ) );
		}
	}
	if ( in_array( $event['kind'], array( 'character_policy_saved', 'member_invitation_saved', 'universe_members_saved' ), true ) &&
		! ps_pen_paper_valid_policy_event( $event ) ) {
		return new WP_Error( 'invalid_event', __( 'Die Charaktervorgaben oder Einladungsstände im Protokoll sind beschädigt.', 'ps-pen-paper' ) );
	}
	if ( in_array( $event['kind'], array( 'invitation_saved', 'wiki_links_saved' ), true ) ) {
		$validator = 'invitation_saved' === $event['kind'] ? 'ps_pen_paper_valid_invitations' : 'ps_pen_paper_valid_wiki_links';
		if ( 'gm' !== $event['visibility'] || ! $validator( $event['before'] ) || ! $validator( $event['after'] ) ||
			$event['after']['revision'] !== $event['before']['revision'] + 1 ) {
			return new WP_Error( 'invalid_event', __( 'Die Einladungs- oder Wiki-Stände im Protokoll sind beschädigt.', 'ps-pen-paper' ) );
		}
	}
	if ( 'character_resource_spent' === $event['kind'] ) {
		$cost = isset( $event['resource_cost'] ) ? $event['resource_cost'] : null;
		if ( 'owner' !== $event['visibility'] || ! ps_pen_paper_valid_character_resource_cost( $cost ) ||
			! isset( $event['before']['revision'], $event['after']['revision'], $event['before']['active'], $event['after']['active'] ) ||
			$event['before']['revision'] !== $cost['character_revision'] || $event['after']['revision'] !== $cost['character_revision'] + 1 ||
			ps_pen_paper_resource_snapshot_current( $event['before'], $cost['id'] ) !== $cost['before'] ||
			ps_pen_paper_resource_snapshot_current( $event['after'], $cost['id'] ) !== $cost['after'] ) {
			return new WP_Error( 'invalid_event', __( 'Der Ressourcenverbrauch im Protokoll ist beschädigt. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
		}
	}
	if ( in_array( $event['kind'], array( 'trade_policy', 'trade_changed' ), true ) ) {
		$validate = 'trade_policy' === $event['kind'] ? 'ps_pen_paper_valid_trade_policy' : 'ps_pen_paper_valid_trade';
		if ( 'gm' !== $event['visibility'] || ! $validate( $event['before'] ) || ! $validate( $event['after'] ) ) {
			return new WP_Error( 'invalid_event', __( 'Die Handelsstände im Protokoll sind beschädigt.', 'ps-pen-paper' ) );
		}
	}
	if ( in_array( $event['kind'], array( 'adventure_saved', 'adventure_published', 'adventure_run' ), true ) ) {
		$validate = 'adventure_run' === $event['kind'] ? 'ps_pen_paper_valid_adventure_run' : 'ps_pen_paper_valid_adventure_state';
		if ( ! function_exists( $validate ) || ! $validate( $event['before'] ) || ! $validate( $event['after'] ) ||
			( 'adventure_run' === $event['kind'] && ( 'owner' !== $event['visibility'] || ! isset( $event['after']['owner_id'] ) || $event['recipient_id'] !== $event['after']['owner_id'] ) ) ||
			( 'adventure_run' !== $event['kind'] && 'gm' !== $event['visibility'] ) ) {
			return new WP_Error( 'invalid_event', __( 'Die Abenteuerstände im Protokoll sind beschädigt.', 'ps-pen-paper' ) );
		}
	}
	if ( 'adventure_group' === $event['kind'] && ( 'gm' !== $event['visibility'] || 0 !== $event['recipient_id'] ||
		! ps_pen_paper_valid_adventure_group( $event['before'] ) || ! ps_pen_paper_valid_adventure_group( $event['after'] ) ) ) {
		return new WP_Error( 'invalid_event', __( 'Der Gruppenabenteuerstand im Protokoll ist beschädigt.', 'ps-pen-paper' ) );
	}
	if ( in_array( $event['kind'], array( 'wiki_configured', 'wiki_prepared', 'wiki_published' ), true ) ) {
		$validate = 'wiki_configured' === $event['kind'] ? 'ps_pen_paper_valid_wiki_config' : 'ps_pen_paper_valid_wiki_entry';
		if ( ! $universe || 'gm' !== $event['visibility'] || ! $validate( $event['before'] ) || ! $validate( $event['after'] ) ) {
			return new WP_Error( 'invalid_event', __( 'Die Lexikonstände im Protokoll sind beschädigt.', 'ps-pen-paper' ) );
		}
	}
	if ( in_array( $event['kind'], array( 'loot_table_saved', 'loot_rolled', 'loot_awarded' ), true ) ) {
		$validate = 'loot_table_saved' === $event['kind'] ? 'ps_pen_paper_valid_loot_table' : 'ps_pen_paper_valid_loot_run';
		if ( 'gm' !== $event['visibility'] || ! $validate( $event['before'] ) || ! $validate( $event['after'] ) ) {
			return new WP_Error( 'invalid_event', __( 'Die Beutestände im Protokoll sind beschädigt.', 'ps-pen-paper' ) );
		}
	}
	if ( in_array( $event['kind'], array( 'npc_saved', 'npc_offered', 'npc_approved', 'npc_copied' ), true ) &&
		( 'gm' !== $event['visibility'] || ! ps_pen_paper_valid_npc_state( $event['before'] ) || ! ps_pen_paper_valid_npc_state( $event['after'] ) ) ) {
		return new WP_Error( 'invalid_event', __( 'Die Figurenstände im Protokoll sind beschädigt.', 'ps-pen-paper' ) );
	}
	if ( ( 'currency_saved' === $event['kind'] && ( 'gm' !== $event['visibility'] || ! ps_pen_paper_valid_currency( $event['before'] ) || ! ps_pen_paper_valid_currency( $event['after'] ) ) ) ||
		( 'wallet_saved' === $event['kind'] && ( 'owner' !== $event['visibility'] || ! ps_pen_paper_valid_wallet( $event['before'] ) || ! ps_pen_paper_valid_wallet( $event['after'] ) ||
			! isset( $event['reason'], $event['character_name'] ) || ! is_string( $event['reason'] ) || '' === trim( $event['reason'] ) || strlen( $event['reason'] ) > 1000 || ! is_string( $event['character_name'] ) ) ) ) {
		return new WP_Error( 'invalid_event', __( 'Die Währungs- oder Guthabenstände im Protokoll sind beschädigt.', 'ps-pen-paper' ) );
	}
	if ( 'membership_saved' === $event['kind'] && ( 'gm' !== $event['visibility'] || ! isset( $event['member_id'], $event['member_name'], $event['member_before'], $event['member_after'] )
		|| ! is_int( $event['member_id'] ) || $event['member_id'] < 1 || ! is_string( $event['member_name'] )
		|| ! ps_pen_paper_valid_membership_snapshot( $event['member_before'] ) || ! ps_pen_paper_valid_membership_snapshot( $event['member_after'] ) ) ) {
		return new WP_Error( 'invalid_event', __( 'Die Mitgliedschaftsdaten im Protokoll sind beschädigt. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	if ( 'character_context' === $event['kind'] && ( ! isset( $event['effective_before'], $event['effective_after'] ) || ! is_array( $event['effective_before'] ) || ! is_array( $event['effective_after'] ) ) ) {
		return new WP_Error( 'invalid_event', __( 'Die wirksamen Kontextstände im Protokoll sind unvollständig. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	$event['date'] = get_date_from_gmt( $post->post_date_gmt, 'Y-m-d H:i:s' );
	return $event;
}

function ps_pen_paper_journal_query( $campaign, $page, $types, $player_view = false ) {
	$universe = $campaign && 'pspp_universe' === $campaign->post_type;
	if ( ! current_user_can( 'read' ) || ! ( $universe ? ps_pen_paper_can_manage_universe( $campaign ) : ps_pen_paper_can_view_campaign( $campaign ) ) ) {
		return new WP_Error( 'forbidden', __( 'Du darfst dieses Spielprotokoll nicht lesen.', 'ps-pen-paper' ) );
	}
	if ( ! is_int( $page ) || $page < 1 || $page > 1000000 ) {
		return new WP_Error( 'invalid_page', __( 'Ungültige Protokollseite.', 'ps-pen-paper' ) );
	}
	$args = array(
		'post_type' => $types, 'post_status' => 'private', 'post_parent' => $campaign->ID,
		'posts_per_page' => 20, 'paged' => $page, 'orderby' => 'ID', 'order' => 'DESC',
	);
	if ( $player_view || ( ! $universe && ! ps_pen_paper_can_manage_campaign( $campaign ) ) ) {
		$args['meta_query'] = array(
			'relation' => 'OR',
			array( 'key' => '_pspp_visibility', 'value' => 'group' ),
			array(
				'relation' => 'AND',
				array( 'key' => '_pspp_visibility', 'value' => 'owner' ),
				array( 'key' => '_pspp_recipient', 'value' => (string) get_current_user_id() ),
			),
			array(
				'relation' => 'AND',
				array( 'key' => '_pspp_visibility', 'value' => 'private' ),
				array(
					'relation' => 'OR',
					array( 'key' => '_pspp_recipient', 'value' => (string) get_current_user_id() ),
					array( 'key' => '_pspp_actor', 'value' => (string) get_current_user_id() ),
				),
			),
		);
	}
	return new WP_Query( $args );
}

function ps_pen_paper_get_events( $campaign, $page = 1, $player_view = false ) {
	$query = ps_pen_paper_journal_query( $campaign, $page, array( 'pspp_roll', 'pspp_event' ), $player_view );
	if ( is_wp_error( $query ) ) {
		return $query;
	}
	$events = array();
	foreach ( $query->posts as $post ) {
		$event = 'pspp_roll' === $post->post_type ? ps_pen_paper_get_roll( $campaign, $post->ID, $player_view ) : ps_pen_paper_get_event( $campaign, $post->ID, $player_view );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		if ( 'pspp_roll' === $post->post_type ) {
			$event['kind'] = 'roll';
		}
		$events[] = $event;
	}
	return array( 'events' => $events, 'pages' => (int) $query->max_num_pages );
}
