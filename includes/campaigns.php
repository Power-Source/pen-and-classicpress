<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_register_campaign_type() {
	register_post_type(
		'pspp_campaign',
		array(
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => false,
			'show_in_rest'        => false,
			'capability_type'     => array( 'pspp_campaign', 'pspp_campaigns' ),
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'editor', 'author' ),
		)
	);
}

function ps_pen_paper_get_campaign( $campaign_id ) {
	$campaign = ps_pen_paper_record_post( $campaign_id );
	if ( ! $campaign || 'pspp_campaign' !== $campaign->post_type || 'private' !== $campaign->post_status ) {
		return null;
	}
	if ( ! ps_pen_paper_scope_observe( $campaign->ID ) ) { return null; }
	return $campaign;
}

function ps_pen_paper_campaign_role( $campaign, $user_id ) {
	global $wpdb;
	$campaign = $campaign ? ps_pen_paper_get_campaign( (int) $campaign->ID ) : null;
	if ( ! $campaign ) { return ''; }
	$roles = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM $wpdb->postmeta WHERE post_id=%d AND meta_value=%s AND meta_key IN ('_pspp_gm','_pspp_player')", $campaign->ID, (string) $user_id ) );
	if ( $wpdb->last_error ) { error_log( 'PS Pen & Paper: membership lookup failed.' ); return ''; }
	if ( (int) $campaign->post_author === (int) $user_id || in_array( '_pspp_gm', $roles, true ) ) {
		return 'gm';
	}
	if ( in_array( '_pspp_player', $roles, true ) ) {
		return 'player';
	}
	return '';
}

function ps_pen_paper_can_view_campaign( $campaign ) {
	if ( ! $campaign || ! ps_pen_paper_scope_observe( $campaign->ID ) ) { return false; }
	return current_user_can( 'manage_options' ) || '' !== ps_pen_paper_campaign_role( $campaign, get_current_user_id() );
}

function ps_pen_paper_can_access_site() {
	return current_user_can( 'read' ) && ( ps_pen_paper_site_user( get_current_user_id() ) || ( is_multisite() && is_super_admin() ) );
}

function ps_pen_paper_can_manage_campaign( $campaign ) {
	if ( ! $campaign || ! ps_pen_paper_scope_observe( $campaign->ID ) ) { return false; }
	return current_user_can( 'manage_options' ) || 'gm' === ps_pen_paper_campaign_role( $campaign, get_current_user_id() );
}

function ps_pen_paper_can_create_campaign() {
	return current_user_can( 'manage_options' ) || current_user_can( 'ps_pen_paper_create_campaigns' );
}

function ps_pen_paper_get_campaigns() {
	$args = array(
		'post_type'      => 'pspp_campaign',
		'post_status'    => 'private',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);
	if ( ! current_user_can( 'manage_options' ) ) {
		$args['meta_query'] = array(
			'relation' => 'OR',
			array(
				'key'   => '_pspp_gm',
				'value' => (string) get_current_user_id(),
			),
			array(
				'key'   => '_pspp_player',
				'value' => (string) get_current_user_id(),
			),
		);
	}
	return get_posts( $args );
}

function ps_pen_paper_membership_revision( $campaign_id ) {
	$state = get_post_meta( $campaign_id, '_pspp_membership', true );
	if ( '' === $state ) { return array( 'revision' => 0 ); }
	if ( ! is_array( $state ) || ! isset( $state['revision'] ) || ! is_int( $state['revision'] ) || $state['revision'] < 1 ) {
		return new WP_Error( 'invalid_membership', __( 'Der Stand der Mitgliederverwaltung ist beschädigt. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	return $state;
}

function ps_pen_paper_valid_membership_snapshot( $snapshot ) {
	return is_array( $snapshot ) && isset( $snapshot['role'], $snapshot['request'] )
		&& in_array( $snapshot['role'], array( '', 'gm', 'player' ), true )
		&& in_array( $snapshot['request'], array( '', 'pending', 'approved', 'rejected' ), true );
}

function ps_pen_paper_change_membership( $campaign_id, $user_id, $operation, $role = '', $revision = null ) {
	if ( ! is_int( $user_id ) || $user_id < 1 ) {
		return new WP_Error( 'invalid_member', __( 'Bitte wähle einen gültigen Benutzer.', 'ps-pen-paper' ) );
	}
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! ps_pen_paper_can_access_site() || ! $campaign || ( 'request' === $operation
		? get_current_user_id() !== $user_id || ! ps_pen_paper_site_user( $user_id )
		: ! ps_pen_paper_can_manage_campaign( $campaign ) ) ) {
		return new WP_Error( 'forbidden', __( 'Du darfst diese Mitgliedschaft nicht ändern.', 'ps-pen-paper' ) );
	}
	if ( ! in_array( $operation, array( 'request', 'approved', 'rejected', 'add', 'remove' ), true ) || ( in_array( $operation, array( 'add', 'remove' ), true ) && ! in_array( $role, array( 'gm', 'player' ), true ) ) ) {
		return new WP_Error( 'invalid_membership', __( 'Bitte wähle eine gültige Mitgliederaktion und Kampagnenrolle.', 'ps-pen-paper' ) );
	}
	return ps_pen_paper_atomic_event( $campaign_id, function () use ( $campaign_id, $user_id, $operation, $role, $revision ) {
		$campaign = ps_pen_paper_get_campaign( $campaign_id );
		if ( ! $campaign || ( 'request' === $operation ? ! ps_pen_paper_site_user( $user_id ) : ! ps_pen_paper_can_manage_campaign( $campaign ) ) ) {
			return new WP_Error( 'forbidden', __( 'Du darfst diese Mitgliedschaft nicht ändern.', 'ps-pen-paper' ) );
		}
		$state = ps_pen_paper_membership_revision( $campaign_id );
		if ( is_wp_error( $state ) ) { return $state; }
		if ( ( null !== $revision || in_array( $operation, array( 'add', 'remove' ), true ) ) && ( ! is_string( $revision ) || (string) $state['revision'] !== $revision ) ) {
			return new WP_Error( 'conflict', __( 'Die Mitgliederverwaltung wurde inzwischen geändert. Bitte lade den aktuellen Stand neu.', 'ps-pen-paper' ) );
		}
		$user = get_userdata( $user_id );
		if ( 'remove' !== $operation && ( ! $user || ! ps_pen_paper_site_user( $user_id ) || ! user_can( $user, 'read' ) ) ) {
			return new WP_Error( 'invalid_member', __( 'Bitte wähle einen leseberechtigten Benutzer dieser Website.', 'ps-pen-paper' ) );
		}
		$key = '_pspp_join_' . $user_id;
		$before = array( 'role' => ps_pen_paper_campaign_role( $campaign, $user_id ), 'request' => get_post_meta( $campaign_id, $key, true ) );
		if ( ! ps_pen_paper_valid_membership_snapshot( $before ) ) {
			return new WP_Error( 'invalid_membership', __( 'Die Mitglieds- oder Antragsdaten sind beschädigt. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
		}
		$after = $before;
		if ( 'request' === $operation ) {
			$access = ps_pen_paper_get_access( $campaign_id );
			if ( is_wp_error( $access ) ) { return $access; }
			if ( 'teaser' !== $access['visibility'] || 'approval' !== $access['admission'] || ps_pen_paper_can_view_campaign( $campaign ) ) {
				return new WP_Error( 'forbidden', __( 'Für diese Runde ist keine Beitrittsanfrage möglich.', 'ps-pen-paper' ) );
			}
			if ( 'pending' === $before['request'] ) { return 0; }
			if ( '' !== $before['request'] ) {
				return new WP_Error( 'conflict', __( 'Diese Anfrage wurde bereits entschieden. Bitte kontaktiere die Spielleitung.', 'ps-pen-paper' ) );
			}
			$after['request'] = 'pending';
		} elseif ( in_array( $operation, array( 'approved', 'rejected' ), true ) ) {
			if ( 'pending' !== $before['request'] ) {
				return new WP_Error( 'conflict', __( 'Diese Anfrage wurde bereits entschieden. Bitte lade die Seite neu.', 'ps-pen-paper' ) );
			}
			$after['request'] = $operation;
			if ( 'approved' === $operation && '' === $before['role'] ) { $after['role'] = 'player'; }
		} elseif ( 'add' === $operation ) {
			if ( '' !== $before['role'] ) {
				return new WP_Error( 'conflict', __( 'Dieser Benutzer ist bereits Mitglied. Entferne ihn zuerst, um seine Rolle zu ändern.', 'ps-pen-paper' ) );
			}
			$after['role'] = $role;
		} else {
			if ( (int) $campaign->post_author === $user_id ) {
				return new WP_Error( 'forbidden', __( 'Der Ersteller der Kampagne kann nicht entfernt werden.', 'ps-pen-paper' ) );
			}
			if ( $before['role'] !== $role ) {
				return new WP_Error( 'conflict', __( 'Dieses Kampagnenmitglied wurde nicht gefunden oder seine Rolle wurde geändert. Bitte lade die Seite neu.', 'ps-pen-paper' ) );
			}
			$after['role'] = '';
		}
		$saved = true;
		if ( $before['request'] !== $after['request'] ) {
			$saved = '' === $before['request'] ? add_post_meta( $campaign_id, $key, $after['request'], true ) : update_post_meta( $campaign_id, $key, $after['request'], $before['request'] );
		}
		if ( $saved && $before['role'] !== $after['role'] ) {
			$saved = '' === $after['role']
				? delete_post_meta( $campaign_id, '_pspp_' . $before['role'], (string) $user_id )
				: add_post_meta( $campaign_id, '_pspp_' . $after['role'], (string) $user_id );
		}
		if ( ! $saved || $after['role'] !== ps_pen_paper_campaign_role( $campaign, $user_id ) || $after['request'] !== get_post_meta( $campaign_id, $key, true ) ) {
			return new WP_Error( 'save_failed', __( 'Mitgliedschaft und Anfrage konnten nicht vollständig gespeichert werden. Es wurde nichts übernommen.', 'ps-pen-paper' ) );
		}
		return ps_pen_paper_write_document_event( $campaign_id, $campaign_id, '_pspp_membership', $state, array( 'revision' => $state['revision'] + 1 ), array(
			'kind' => 'membership_saved', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0,
			'member_id' => $user_id, 'member_name' => $user ? $user->display_name : __( 'Gelöschter Benutzer', 'ps-pen-paper' ) . ' #' . $user_id,
			'member_before' => $before, 'member_after' => $after,
		), false );
	} );
}
