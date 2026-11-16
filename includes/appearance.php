<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_can_edit_portrait( $scope, $id ) {
	return $scope && ps_pen_paper_can_access_site() && ps_pen_paper_get_character( $scope, $id ) &&
		( ps_pen_paper_can_manage_character_scope( $scope ) || 'pspp_universe' === $scope->post_type ||
			'gm_only' !== ps_pen_paper_character_mode( ps_pen_paper_get_rules( $scope->ID ) ) );
}

function ps_pen_paper_portrait_state( $id ) {
	$state = get_post_meta( $id, '_pspp_portrait', true );
	if ( '' === $state ) { return array( 'revision' => 0, 'active' => array( 'asset_id' => 0 ), 'pending' => null ); }
	if ( ! is_array( $state ) || ! isset( $state['revision'], $state['active'] ) || ! array_key_exists( 'pending', $state ) || ! is_int( $state['revision'] ) || $state['revision'] < 1 ) { return ps_pen_paper_equipment_error(); }
	foreach ( array( $state['active'], $state['pending'] ) as $snapshot ) {
		if ( null !== $snapshot && ( ! is_array( $snapshot ) || ! isset( $snapshot['asset_id'] ) || ! is_int( $snapshot['asset_id'] ) || $snapshot['asset_id'] < 0 ||
			( isset( $snapshot['view'] ) && ! ps_pen_paper_valid_image_view( $snapshot['view'] ) ) ||
			( isset( $snapshot['share'] ) && ! is_bool( $snapshot['share'] ) ) ) ) { return ps_pen_paper_equipment_error(); }
	}
	return $state;
}

function ps_pen_paper_save_portrait( $scope, $id, $revision, $operation, $path = null ) {
	if ( ! $scope ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	return ps_pen_paper_with_character_lock( $id, function () use ( $scope, $id, $revision, $operation, $path ) {
		$policy_scope = ps_pen_paper_portrait_policy_scope( $scope );
		if ( is_wp_error( $policy_scope ) ) { return $policy_scope; }
		$scopes = array( (int) $scope->ID );
		if ( $policy_scope ) { $scopes[] = (int) $policy_scope->ID; }
		$scopes = array_values( array_unique( $scopes ) ); sort( $scopes, SORT_NUMERIC );
		$run = function ( $index ) use ( &$run, $scopes, $scope, $id, $revision, $operation, $path ) {
			if ( isset( $scopes[ $index ] ) ) { return ps_pen_paper_scope_lock( $scopes[ $index ], function () use ( &$run, $index ) { return $run( $index + 1 ); } ); }
			return ps_pen_paper_atomic_event( $id, function () use ( $scope, $id, $revision, $operation, $path ) {
				return ps_pen_paper_save_portrait_locked( $scope, $id, $revision, $operation, $path );
			} );
		};
		return $run( 0 );
	} );
}

function ps_pen_paper_portrait_policy_scope( $scope ) {
	if ( 'pspp_universe' === $scope->post_type ) { return $scope; }
	$link = ps_pen_paper_get_universe_link( $scope->ID );
	if ( is_wp_error( $link ) ) { return $link; }
	if ( ! $link['universe_id'] ) { return null; }
	return ps_pen_paper_get_universe( $link['universe_id'] ) ?: new WP_Error( 'invalid_universe', __( 'Bitte repariere zuerst die Universumszuordnung.', 'ps-pen-paper' ) );
}

function ps_pen_paper_freeze_portrait_asset( $source_scope, $target_scope, $asset_id ) {
	$image = ps_pen_paper_private_image_data( $source_scope, $asset_id );
	if ( is_wp_error( $image ) ) { return $image; }
	$asset = ps_pen_paper_record_post( $asset_id );
	if ( ! ps_pen_paper_valid_private_asset_content( $asset->post_mime_type, $asset->post_content ) ) { return new WP_Error( 'invalid_image', __( 'Das Porträtbild ist beschädigt.', 'ps-pen-paper' ) ); }
	$id = wp_insert_post( array( 'post_type' => 'pspp_asset', 'post_status' => 'private', 'post_parent' => $target_scope->ID,
		'post_author' => get_current_user_id(), 'post_title' => 'PS Pen & Paper Frozen Portrait',
		'post_mime_type' => $asset->post_mime_type, 'post_content' => $asset->post_content ), true );
	return is_wp_error( $id ) || $id ? $id : new WP_Error( 'save_failed', __( 'Das Porträt konnte nicht geschützt kopiert werden.', 'ps-pen-paper' ) );
}

// Called inside the enclosing character-creation transaction, after its writer.
function ps_pen_paper_write_base_portrait( $scope, $character_id, $source_id ) {
	$source = ps_pen_paper_record_post( $source_id );
	$universe = $source ? ps_pen_paper_get_universe( (int) $source->post_parent ) : null;
	if ( ! $source || ! $universe || 'pspp_character' !== $source->post_type ||
		'private' !== $source->post_status ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	$before = ps_pen_paper_portrait_state( $character_id );
	$portrait = ps_pen_paper_portrait_state( $source_id );
	if ( is_wp_error( $before ) || is_wp_error( $portrait ) ) { return is_wp_error( $before ) ? $before : $portrait; }
	if ( $before['revision'] || ( ! $portrait['active']['asset_id'] && empty( $portrait['active']['share'] ) ) ) { return 0; }
	$asset_id = $portrait['active']['asset_id'] ? ps_pen_paper_freeze_portrait_asset( $universe, $scope, $portrait['active']['asset_id'] ) : 0;
	if ( is_wp_error( $asset_id ) ) { return $asset_id; }
	$snapshot = $portrait['active']; $snapshot['asset_id'] = (int) $asset_id;
	return ps_pen_paper_write_document_event( $scope->ID, $character_id, '_pspp_portrait', $before,
		array( 'revision' => 1, 'active' => $snapshot, 'pending' => null ),
		array( 'kind' => 'portrait_saved', 'visibility' => 'owner', 'recipient_id' => (int) $source->post_author,
			'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $scope->ID )['revision'] ), false );
}

function ps_pen_paper_save_portrait_locked( $scope, $id, $revision, $operation, $path = null ) {
	if ( ! ps_pen_paper_can_edit_portrait( $scope, $id ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	$before = ps_pen_paper_portrait_state( $id );
	if ( is_wp_error( $before ) ) { return $before; }
	if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision ) { return ps_pen_paper_equipment_error( 'conflict' ); }
	$after = $before;
	$after['revision']++;
	$is_gm = ps_pen_paper_can_manage_character_scope( $scope );
	$policy_scope = ps_pen_paper_portrait_policy_scope( $scope );
	if ( is_wp_error( $policy_scope ) ) { return $policy_scope; }
	$policy = $policy_scope ? ps_pen_paper_character_policy( $policy_scope->ID ) : null;
	if ( is_wp_error( $policy ) ) { return $policy; }
	$asset_id = 0;
	if ( 'presentation' === $operation ) {
		if ( ! is_array( $path ) || count( $path ) !== 2 || ! isset( $path['view'], $path['share'] ) ||
			! ps_pen_paper_valid_image_view( $path['view'] ) || ! is_bool( $path['share'] ) ) { return ps_pen_paper_equipment_error(); }
		foreach ( array( 'active', 'pending' ) as $key ) {
			if ( null === $after[ $key ] ) { continue; }
			$after[ $key ]['view'] = $path['view']; $after[ $key ]['share'] = $path['share'];
		}
	} elseif ( in_array( $operation, array( 'approve', 'reject' ), true ) ) {
		if ( ! $is_gm || null === $before['pending'] ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
		if ( 'approve' === $operation ) { $after['active'] = $before['pending']; }
		$after['pending'] = null;
	} elseif ( in_array( $operation, array( 'upload', 'remove', 'preset' ), true ) ) {
		if ( 'upload' === $operation ) {
			if ( ! $is_gm && $policy && ! $policy['custom_portraits'] ) { return new WP_Error( 'portrait_upload_disabled', __( 'Eigene Porträtuploads sind in diesem Universum deaktiviert. Bitte wähle ein freigegebenes Porträt.', 'ps-pen-paper' ) ); }
			$asset_id = ps_pen_paper_store_private_image( $scope->ID, $path, $id );
			if ( is_wp_error( $asset_id ) ) { return $asset_id; }
		} elseif ( 'preset' === $operation ) {
			if ( ! $policy || ! is_int( $path ) || ! in_array( $path, array_column( $policy['presets'], 'asset_id' ), true ) ) {
				return new WP_Error( 'invalid_portrait_preset', __( 'Dieses Porträt ist nicht zur Auswahl freigegeben.', 'ps-pen-paper' ) );
			}
			$asset_id = ps_pen_paper_freeze_portrait_asset( $policy_scope, $scope, $path );
			if ( is_wp_error( $asset_id ) ) { return $asset_id; }
		} elseif ( null !== $path ) { return ps_pen_paper_equipment_error(); }
		$snapshot = array( 'asset_id' => $asset_id );
		if ( ! $is_gm && ( 'pspp_universe' === $scope->post_type || 'approval' === ps_pen_paper_character_mode( ps_pen_paper_get_rules( $scope->ID ) ) ) ) {
			$after['pending'] = $snapshot;
		} else { $after['active'] = $snapshot; $after['pending'] = null; }
	} else { return ps_pen_paper_equipment_error(); }
	$character = ps_pen_paper_get_character( $scope, $id );
	$result = ps_pen_paper_write_document_event( $scope->ID, $id, '_pspp_portrait', $before, $after, array(
		'kind' => 'portrait_saved', 'visibility' => 'owner', 'recipient_id' => (int) $character->post_author,
		'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $scope->ID )['revision'],
	), false );
	return $result;
}

function ps_pen_paper_portrait_image( $scope, $character ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_get_character( $scope, $character->ID ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	$state = ps_pen_paper_portrait_state( $character->ID );
	if ( is_wp_error( $state ) ) { return $state; }
	return $state['active']['asset_id'] ? ps_pen_paper_private_image_data( $scope, $state['active']['asset_id'] ) : '';
}

function ps_pen_paper_render_portrait( $scope, $character, $name ) {
	$image = ps_pen_paper_portrait_image( $scope, $character );
	if ( is_wp_error( $image ) ) { echo '<p role="alert">' . esc_html( $image->get_error_message() ) . '</p>'; return; }
	$state = ps_pen_paper_portrait_state( $character->ID );
	$style = ps_pen_paper_image_view_style( $state['active']['view'] ?? null );
	if ( is_wp_error( $style ) ) { echo '<p role="alert">' . esc_html( $style->get_error_message() ) . '</p>'; return; }
	echo '<span class="pspp-portrait" aria-hidden="true">' . ( $image ? '<img src="' . esc_attr( $image ) . '" style="' . esc_attr( $style ) . '" alt="">' : esc_html( wp_html_excerpt( $name, 1, '' ) ) ) . '</span>';
}

function ps_pen_paper_render_portrait_presentation( $scope, $character, $page_id ) {
	if ( ! ps_pen_paper_can_edit_portrait( $scope, $character->ID ) ) { return; }
	$character_state = ps_pen_paper_raw_character_state( $character->ID );
	if ( is_wp_error( $character_state ) ) { echo '<p role="alert">' . esc_html( $character_state->get_error_message() ) . '</p>'; return; }
	if ( 'shared' === ( $character_state['context']['mode'] ?? '' ) ) {
		echo '<p>' . esc_html__( 'Die Darstellung für die Rundenteilnehmer wird im Profil des Basischarakters freigegeben.', 'ps-pen-paper' ) . '</p>';
		if ( (int) $character->post_author === get_current_user_id() && ps_pen_paper_community_url() ) {
			echo '<a href="' . esc_url( ps_pen_paper_community_url( 0, $character_state['context']['source_id'], $character_state['context']['universe_id'] ) ) . '">' .
				esc_html__( 'Charakterdarstellung im Profil öffnen', 'ps-pen-paper' ) . '</a>';
		}
		return;
	}
	$state = ps_pen_paper_portrait_state( $character->ID );
	$image = ps_pen_paper_portrait_image( $scope, $character );
	if ( is_wp_error( $state ) || is_wp_error( $image ) ) { echo '<p role="alert">' . esc_html( is_wp_error( $state ) ? $state->get_error_message() : $image->get_error_message() ) . '</p>'; return; }
	ps_pen_paper_form_start( 'profile_portrait', 'pspp_campaign_' . $scope->ID, $scope->ID, $page_id );
	echo '<input type="hidden" name="character_id" value="' . esc_attr( $character->ID ) . '"><input type="hidden" name="portrait_revision" value="' . esc_attr( $state['revision'] ) . '"><input type="hidden" name="portrait_operation" value="presentation">';
	ps_pen_paper_render_image_view_fields( $state['active']['view'] ?? null, $image );
	echo '<label class="pspp-check"><input type="checkbox" name="share_character" value="1" ' . checked( ! empty( $state['active']['share'] ), true, false ) . '>' .
		esc_html__( 'Freigegebenen Charakternamen und freigegebenes Porträt am Spieltisch mit meinen Rundenteilnehmern teilen. Werte und Inventar bleiben privat.', 'ps-pen-paper' ) . '</label><button>' .
		esc_html__( 'Charakterdarstellung speichern', 'ps-pen-paper' ) . '</button></form>';
}

function ps_pen_paper_sheet_style( $scope ) {
	$value = get_post_meta( $scope->ID, '_pspp_sheet_style', true );
	if ( '' === $value ) { return array( 'revision' => 0, 'asset_id' => 0 ); }
	if ( ! is_array( $value ) || ! isset( $value['revision'], $value['asset_id'] ) || ! is_int( $value['revision'] ) || $value['revision'] < 1 ||
		! is_int( $value['asset_id'] ) || $value['asset_id'] < 0 ||
		( isset( $value['view'] ) && ! ps_pen_paper_valid_image_view( $value['view'] ) ) ) { return ps_pen_paper_equipment_error(); }
	return $value;
}

function ps_pen_paper_save_sheet_style( $scope, $revision, $path = null, $view = null ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $scope ) ) { return ps_pen_paper_equipment_error( 'forbidden' ); }
	$before = ps_pen_paper_sheet_style( $scope );
	if ( is_wp_error( $before ) ) { return $before; }
	if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision ) { return ps_pen_paper_equipment_error( 'conflict' ); }
	if ( null !== $view && ( ! ps_pen_paper_valid_image_view( $view ) || ! $before['asset_id'] || null !== $path ) ) { return ps_pen_paper_equipment_error(); }
	$asset_id = null !== $view ? $before['asset_id'] : ( null === $path ? 0 : ps_pen_paper_store_private_image( $scope->ID, $path ) );
	if ( is_wp_error( $asset_id ) ) { return $asset_id; }
	$after = array( 'revision' => $before['revision'] + 1, 'asset_id' => $asset_id );
	if ( null !== $view ) { $after['view'] = $view; }
	$result = ps_pen_paper_save_document_event( $scope->ID, $scope->ID, '_pspp_sheet_style', $before, $after, array(
		'kind' => 'sheet_style_saved', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => ps_pen_paper_get_rules( $scope->ID )['revision'],
	) );
	if ( is_wp_error( $result ) && 'event_recovery_required' !== $result->get_error_code() && null !== $path && $asset_id && ! wp_delete_post( $asset_id, true ) ) { return ps_pen_paper_equipment_error( 'cleanup_failed' ); }
	return $result;
}

function ps_pen_paper_sheet_attributes( $scope ) {
	$style = ps_pen_paper_sheet_style( $scope );
	if ( is_wp_error( $style ) ) { echo ' data-sheet-error="true"'; return $style; }
	if ( $style['asset_id'] ) {
		$image = ps_pen_paper_private_image( $scope, $style['asset_id'] );
		if ( is_wp_error( $image ) ) { return $image; }
		$view = $style['view'] ?? array( 'x' => 50, 'y' => 50, 'fit' => 'cover' );
		echo ' style="' . esc_attr( '--pspp-sheet-image:url("' . $image . '");--pspp-sheet-position:' . $view['x'] . '% ' . $view['y'] . '%;--pspp-sheet-size:' . $view['fit'] ) . '"';
	}
	return true;
}
