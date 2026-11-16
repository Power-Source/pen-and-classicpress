<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/play-friends.php';

function ps_pen_paper_play_modes() {
	return array( 'guided' => __( 'Mit Spielleiter', 'ps-pen-paper' ), 'solo' => __( 'Solo-Abenteuer', 'ps-pen-paper' ),
		'group' => __( 'Automatisches Gruppenabenteuer', 'ps-pen-paper' ) );
}

function ps_pen_paper_play_error( $message = null ) {
	return new WP_Error( 'play_round_conflict', $message ?: __( 'Die Runde wurde geändert oder ist nicht verfügbar. Bitte lade den aktuellen Stand neu.', 'ps-pen-paper' ) );
}

function ps_pen_paper_play_options_default() {
	return array( 'revision' => 0, 'modes' => array( 'guided' ), 'player_starts' => false );
}

function ps_pen_paper_valid_play_options( $value ) {
	return is_array( $value ) && array_keys( $value ) === array( 'revision', 'modes', 'player_starts' ) &&
		is_int( $value['revision'] ) && $value['revision'] >= 0 && is_array( $value['modes'] ) &&
		array_values( $value['modes'] ) === $value['modes'] && ! array_diff( $value['modes'], array_keys( ps_pen_paper_play_modes() ) ) &&
		count( $value['modes'] ) === count( array_unique( $value['modes'] ) ) && is_bool( $value['player_starts'] );
}

function ps_pen_paper_play_options( $id ) {
	$value = get_post_meta( $id, '_pspp_play_options', true );
	return '' === $value ? ps_pen_paper_play_options_default() :
		( ps_pen_paper_valid_play_options( $value ) ? $value : ps_pen_paper_play_error() );
}

function ps_pen_paper_save_play_options( $campaign, $modes, $player_starts, $revision ) {
	if ( ! ps_pen_paper_can_manage_campaign( $campaign ) || ! is_array( $modes ) || ! is_bool( $player_starts ) ) { return ps_pen_paper_play_error(); }
	return ps_pen_paper_atomic_event( (int) $campaign->ID, function () use ( $campaign, $modes, $player_starts, $revision ) {
		$before = ps_pen_paper_play_options( $campaign->ID );
		if ( is_wp_error( $before ) || ! is_string( $revision ) || (string) $before['revision'] !== $revision ||
			! ps_pen_paper_can_manage_campaign( $campaign ) || '' !== get_post_meta( $campaign->ID, '_pspp_play_round', true ) ) { return ps_pen_paper_play_error(); }
		$after = array( 'revision' => $before['revision'] + 1, 'modes' => array_values( $modes ), 'player_starts' => $player_starts );
		if ( ! ps_pen_paper_valid_play_options( $after ) ) { return ps_pen_paper_play_error(); }
		return ps_pen_paper_write_document_event( $campaign->ID, $campaign->ID, '_pspp_play_options', $before, $after,
			array( 'kind' => 'play_options', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
	} );
}

function ps_pen_paper_valid_play_round( $value ) {
	if ( array( 'revision' => 0 ) === $value ) { return true; }
	$keys = array( 'revision', 'source_id', 'mode', 'status', 'host_id', 'adventure_id', 'run_id', 'participants' );
	if ( is_array( $value ) && array_key_exists( 'invitations', $value ) ) {
		$keys[] = 'invitations';
		if ( 'group' !== ( $value['mode'] ?? '' ) || ! ps_pen_paper_valid_play_invitations( $value['invitations'] ) ) { return false; }
	}
	if ( ! is_array( $value ) || array_keys( $value ) !== $keys ||
		! is_int( $value['revision'] ) || $value['revision'] < 1 || ! is_int( $value['source_id'] ) || $value['source_id'] < 1 ||
		! is_string( $value['mode'] ) || ! isset( ps_pen_paper_play_modes()[ $value['mode'] ] ) || ! in_array( $value['status'], array( 'lobby', 'active', 'paused', 'completed' ), true ) ||
		! is_int( $value['host_id'] ) || $value['host_id'] < 1 || ! is_int( $value['adventure_id'] ) || $value['adventure_id'] < 0 ||
		! is_int( $value['run_id'] ) || $value['run_id'] < 0 || ! is_array( $value['participants'] ) ||
		array_values( $value['participants'] ) !== $value['participants'] || count( $value['participants'] ) > 100 ||
		( 'guided' === $value['mode'] ? 0 !== $value['adventure_id'] || 0 !== $value['run_id'] : $value['adventure_id'] < 1 ) ||
		( 'lobby' === $value['status'] && 0 !== $value['run_id'] ) ) { return false; }
	$users = array(); $characters = array();
	foreach ( $value['participants'] as $participant ) {
		if ( ! is_array( $participant ) || array_keys( $participant ) !== array( 'user_id', 'source_id', 'character_id', 'active' ) ||
			! is_int( $participant['user_id'] ) || $participant['user_id'] < 1 || isset( $users[ $participant['user_id'] ] ) ||
			! is_int( $participant['source_id'] ) || $participant['source_id'] < 1 ||
			! is_int( $participant['character_id'] ) || $participant['character_id'] < 1 || isset( $characters[ $participant['character_id'] ] ) ||
			! is_bool( $participant['active'] ) ) { return false; }
		$users[ $participant['user_id'] ] = true; $characters[ $participant['character_id'] ] = true;
	}
	return 'solo' !== $value['mode'] || ( count( $users ) <= 1 && ( ! $users || isset( $users[ $value['host_id'] ] ) ) );
}

function ps_pen_paper_play_round( $id ) {
	$value = get_post_meta( $id, '_pspp_play_round', true );
	return '' === $value ? null : ( ps_pen_paper_valid_play_round( $value ) && $value['revision'] > 0 ? $value : ps_pen_paper_play_error() );
}

function ps_pen_paper_play_participant( $state, $user_id ) {
	foreach ( $state['participants'] as $participant ) { if ( $participant['user_id'] === $user_id && $participant['active'] ) { return $participant; } }
	return null;
}

function ps_pen_paper_round_clear_player_tokens( &$value ) {
	if ( ! is_array( $value ) ) { return; }
	if ( isset( $value['tokens'] ) && is_array( $value['tokens'] ) ) {
		$value['tokens'] = array_values( array_filter( $value['tokens'], static function ( $token ) { return 'player' !== ( $token['type'] ?? '' ); } ) );
	}
	foreach ( $value as &$part ) { if ( is_array( $part ) ) { ps_pen_paper_round_clear_player_tokens( $part ); } }
	unset( $part );
}

function ps_pen_paper_play_rounds( $source ) {
	$rounds = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'meta_key' => '_pspp_play_round',
		'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'DESC' ) ) as $round ) {
		$state = ps_pen_paper_play_round( $round->ID );
		if ( is_wp_error( $state ) ) { return $state; }
		if ( $state['source_id'] === (int) $source->ID ) { $rounds[] = array( 'post' => $round, 'state' => $state ); }
	}
	return $rounds;
}

// Only prepared content is copied. Membership, inventories, logs and live map state are not templates.
function ps_pen_paper_copy_round_content( $source, $target_id, $universe_id ) {
	$types = array( 'pspp_scene', 'pspp_folio', 'pspp_npc', 'pspp_item', 'pspp_loot_table', 'pspp_adventure' );
	$root_keys = array( '_pspp_rules', '_pspp_equipment_schema', '_pspp_backdrop', '_pspp_sheet_style', '_pspp_currency', '_pspp_trade_policy', '_pspp_item_action_policy' );
	$posts = array_merge( array( $source, ps_pen_paper_get_universe( $universe_id ) ),
		get_posts( array( 'post_type' => array_merge( $types, array( 'pspp_asset', 'pspp_wiki_entry' ) ),
			'post_parent__in' => array( $source->ID, $universe_id ), 'post_status' => 'private', 'posts_per_page' => -1 ) ) );
	$parsed = array( 'records' => array(), 'meta' => array(), 'universe_id' => $universe_id,
		'archive' => array( 'payload' => array( 'source' => array( 'campaign_id' => (int) $source->ID ) ) ) );
	$copy = array(); $map = array( (int) $source->ID => $target_id ); $assets = array();
	foreach ( $posts as $post ) {
		if ( ! $post ) { return ps_pen_paper_play_error(); }
		$record = array( 'id' => (int) $post->ID, 'type' => $post->post_type, 'parent_id' => (int) $post->post_parent,
			'author_id' => (int) $post->post_author, 'status' => 'private', 'title' => $post->post_title, 'content' => $post->post_content,
			'excerpt' => $post->post_excerpt, 'mime_type' => $post->post_mime_type, 'menu_order' => (int) $post->menu_order,
			'date_gmt' => $post->post_date_gmt, 'modified_gmt' => $post->post_modified_gmt );
		$parsed['records'][ $post->ID ] = $record; $map[ $post->ID ] = $map[ $post->ID ] ?? (int) $post->ID;
		$parsed['meta'][ $post->ID ] = get_post_meta( $post->ID );
		foreach ( $parsed['meta'][ $post->ID ] as $key => &$values ) {
			$values = array_map( 'maybe_unserialize', $values );
			if ( (int) $post->ID !== (int) $source->ID && ! in_array( $post->post_type, $types, true ) ) { continue; }
			if ( '_pspp_import_archive' === $key || ( (int) $post->ID === (int) $source->ID && ! in_array( $key, $root_keys, true ) ) ) { continue; }
			foreach ( $values as $value ) { $assets = array_merge( $assets, ps_pen_paper_export_asset_ids( $value ) ); }
		}
		unset( $values );
		if ( (int) $post->post_parent === (int) $source->ID && in_array( $post->post_type, $types, true ) ) { $copy[] = (int) $post->ID; }
	}
	foreach ( array_unique( $assets ) as $id ) {
		if ( isset( $parsed['records'][ $id ] ) && 'pspp_asset' === $parsed['records'][ $id ]['type'] &&
			$parsed['records'][ $id ]['parent_id'] === (int) $source->ID ) { $copy[] = $id; }
	}
	foreach ( array_unique( $copy ) as $id ) {
		$new_id = ps_pen_paper_import_insert_post( $parsed['records'][ $id ], $target_id, $parsed['records'][ $id ]['author_id'], 'private' );
		if ( is_wp_error( $new_id ) ) { return $new_id; }
		$map[ $id ] = $new_id;
		if ( 'pspp_adventure' === $parsed['records'][ $id ]['type'] &&
			! ps_pen_paper_import_insert_meta( $new_id, '_pspp_play_origin', array( 'campaign_id' => (int) $source->ID, 'adventure_id' => $id ) ) ) { return ps_pen_paper_play_error(); }
	}
	$options = array( 'universe_mode' => 'bind', 'universe_id' => $universe_id, 'users' => array(), 'bases' => array() );
	if ( empty( $parsed['meta'][ $source->ID ]['_pspp_equipment_schema'] ) && ! empty( $parsed['meta'][ $universe_id ]['_pspp_equipment_schema'] ) ) {
		$parsed['meta'][ $source->ID ]['_pspp_equipment_schema'] = $parsed['meta'][ $universe_id ]['_pspp_equipment_schema'];
	}
	foreach ( get_users( array( 'blog_id' => get_current_blog_id(), 'fields' => 'ID' ) ) as $user_id ) { $options['users'][ $user_id ] = (int) $user_id; }
	$allowed = ps_pen_paper_prepare_import_type_meta();
	foreach ( array_merge( array( (int) $source->ID ), array_unique( $copy ) ) as $id ) {
		$record = $parsed['records'][ $id ];
		foreach ( $parsed['meta'][ $id ] as $key => $values ) {
			if ( ( $id === (int) $source->ID && ! in_array( $key, $root_keys, true ) ) ||
				in_array( $key, array( '_pspp_import_archive', '_pspp_play_origin' ), true ) ||
				( $id !== (int) $source->ID && ! in_array( $key, $allowed[ $record['type'] ] ?? array(), true ) ) ) { continue; }
			foreach ( $values as $value ) {
				ps_pen_paper_round_clear_player_tokens( $value );
				$valid = ps_pen_paper_prepare_import_validate_known( $key, $value, $record, $parsed );
				if ( ! $valid || is_wp_error( $valid ) ) { return ps_pen_paper_play_error(); }
				$archive = array( 'references' => array(), 'warnings' => array() );
				$remapped = ps_pen_paper_prepare_import_remap_value( $key, $value, $record, $parsed, $options, $map, $archive );
				if ( is_wp_error( $remapped ) ) { return $remapped; }
				if ( ! ps_pen_paper_import_insert_meta( $map[ $id ], $key, $value ) ) { return ps_pen_paper_play_error(); }
			}
		}
	}
	return $map;
}

function ps_pen_paper_create_play_round( $source, $mode, $adventure_id, $token, $revision, $character_id = 0, $character_revision = '', $friends = null ) {
	if ( ! $source || ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $source ) ||
		! is_int( $character_id ) || $character_id < 0 ||
		( $character_id && 'solo' !== $mode && ! ( 'group' === $mode && is_array( $friends ) ) ) ||
		( null !== $friends && ( 'group' !== $mode || ! $character_id || ! is_array( $friends ) ) ) ) { return ps_pen_paper_play_error(); }
	$link = ps_pen_paper_get_universe_link( $source->ID );
	if ( is_wp_error( $link ) ) { return $link; }
	if ( ! $link['universe_id'] ) { return ps_pen_paper_play_error( __( 'Die Spielleitung muss zuerst ein Universum zuordnen.', 'ps-pen-paper' ) ); }
	$create = function () use ( $source, $mode, $adventure_id, $token, $revision, $link, $character_id, $character_revision, $friends ) {
		return ps_pen_paper_scope_lock( $link['universe_id'], function () use ( $source, $mode, $adventure_id, $token, $revision, $link, $character_id, $character_revision, $friends ) {
			return ps_pen_paper_create_play_round_locked( $source, $mode, $adventure_id, $token, $revision, $link['universe_id'], $character_id, $character_revision, $friends );
		} );
	};
	if ( $character_id ) {
		$choices = ps_pen_paper_play_characters( $source );
		if ( is_wp_error( $choices ) ) { return $choices; }
		if ( ! isset( $choices[ $character_id ] ) ) { return ps_pen_paper_play_error( __( 'Wähle einen eigenen freigegebenen Charakter mit passenden Universumsregeln.', 'ps-pen-paper' ) ); }
		return ps_pen_paper_with_item_character_locks( $source, array( $choices[ $character_id ]['post'] ), $create );
	}
	return $create();
}

function ps_pen_paper_create_play_round_locked( $source, $mode, $adventure_id, $token, $revision, $universe_id, $character_id = 0, $character_revision = '', $friends = null ) {
	if ( ! $source || ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $source ) ||
		! is_string( $mode ) || ! isset( ps_pen_paper_play_modes()[ $mode ] ) || ! is_int( $adventure_id ) ||
		! is_string( $token ) || ! wp_is_uuid( $token ) ) { return ps_pen_paper_play_error(); }
	$id = 0;
	$result = ps_pen_paper_atomic_event( (int) $source->ID, function () use ( $source, $mode, $adventure_id, $token, $revision, $universe_id, $character_id, $character_revision, $friends, &$id ) {
		$options = ps_pen_paper_play_options( $source->ID ); $link = ps_pen_paper_get_universe_link( $source->ID );
		if ( is_wp_error( $options ) || is_wp_error( $link ) || $link['universe_id'] !== $universe_id || ! ps_pen_paper_get_universe( $link['universe_id'] ) ||
			! is_string( $revision ) || (string) $options['revision'] !== $revision || ! in_array( $mode, $options['modes'], true ) ||
			ps_pen_paper_play_round( $source->ID ) || ! ps_pen_paper_can_view_campaign( $source ) ||
			( ! ps_pen_paper_can_manage_campaign( $source ) && ( 'guided' === $mode || ! $options['player_starts'] ) ) ) {
			return ps_pen_paper_play_error( __( 'Diese Spielart oder das Anlegen einer Runde ist nicht freigegeben. Die Kampagne braucht ein zugeordnetes Universum.', 'ps-pen-paper' ) );
		}
		$hash = hash( 'sha256', $source->ID . ':' . get_current_user_id() . ':' . $token );
		$existing = get_posts( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'meta_key' => '_pspp_play_token', 'meta_value' => $hash, 'posts_per_page' => 1 ) );
		if ( $existing ) {
			$previous = ps_pen_paper_play_round( $existing[0]->ID );
			$origin = $previous && ! is_wp_error( $previous ) && $previous['adventure_id']
				? get_post_meta( $previous['adventure_id'], '_pspp_play_origin', true ) : array( 'adventure_id' => 0 );
			if ( ! $previous || is_wp_error( $previous ) || $previous['mode'] !== $mode || ( $origin['adventure_id'] ?? -1 ) !== $adventure_id ) { return ps_pen_paper_play_error(); }
			if ( $character_id && ( ( 'solo' === $mode && ! $previous['run_id'] ) || ! isset( $previous['participants'][0] ) ||
				$previous['participants'][0]['source_id'] !== $character_id ) ) { return ps_pen_paper_play_error(); }
			if ( null !== $friends && array_column( $previous['invitations'] ?? array(), 'user_id' ) !== $friends ) { return ps_pen_paper_play_error(); }
			$id = (int) $existing[0]->ID; return 0;
		}
		if ( 'guided' !== $mode ) {
			$records = ps_pen_paper_adventure_records( $source, false );
			$adventure = ! is_wp_error( $records ) ? ( $records[ $adventure_id ] ?? null ) : null;
			if ( ! $adventure || 'published' !== $adventure['status'] ||
				! in_array( $adventure['published']['scenario']['mode'] ?? 'both', array( 'both', $mode ), true ) ||
				array_filter( $adventure['published']['edges'], static function ( $edge ) { return ! empty( $edge['gm_review'] ); } ) ) {
				return ps_pen_paper_play_error( __( 'Wähle ein freigegebenes Abenteuer dieser Spielart ohne erforderliche Spielleiterentscheidungen.', 'ps-pen-paper' ) );
			}
		} elseif ( 0 !== $adventure_id ) { return ps_pen_paper_play_error(); }
		$rules = ps_pen_paper_get_rules( $source->ID );
		if ( ! ps_pen_paper_valid_rules_document( $rules ) || ! $rules['revision'] ) { return ps_pen_paper_play_error( __( 'Die Spielleitung muss zuerst die Charaktervorlage einrichten.', 'ps-pen-paper' ) ); }
		$schema = ps_pen_paper_equipment_schema( $source ); $world_schema = ps_pen_paper_equipment_schema( ps_pen_paper_get_universe( $link['universe_id'] ) );
		if ( is_wp_error( $schema ) || is_wp_error( $world_schema ) ||
			( $schema['revision'] && ( $schema['slots'] !== $world_schema['slots'] || $schema['rarities'] !== $world_schema['rarities'] ) ) ) {
			return ps_pen_paper_play_error( __( 'Die Ausrüstungsvorlagen der Kampagne und ihres Universums müssen für gemeinsame Charaktere zusammenpassen.', 'ps-pen-paper' ) );
		}
		$rounds = ps_pen_paper_play_rounds( $source );
		if ( is_wp_error( $rounds ) ) { return $rounds; }
		$invitations = null !== $friends ? ps_pen_paper_prepare_play_friends( $source, $friends ) : null;
		if ( is_wp_error( $invitations ) ) { return $invitations; }
		$id = wp_insert_post( array( 'post_type' => 'pspp_campaign', 'post_status' => 'draft', 'post_title' => sprintf( __( 'Runde %d', 'ps-pen-paper' ), count( $rounds ) + 1 ),
			'post_content' => $source->post_content, 'post_author' => 'guided' === $mode ? get_current_user_id() : (int) $source->post_author ), true );
		if ( is_wp_error( $id ) || ! $id ) { return ps_pen_paper_play_error(); }
		$map = ps_pen_paper_copy_round_content( $source, (int) $id, $link['universe_id'] );
		if ( is_wp_error( $map ) ) { return $map; }
		if ( ! ps_pen_paper_import_insert_meta( $id, '_pspp_universe_link', $link ) || ! ps_pen_paper_import_insert_meta( $id, '_pspp_play_token', $hash ) ) { return ps_pen_paper_play_error(); }
		$state = array( 'revision' => 1, 'source_id' => (int) $source->ID, 'mode' => $mode, 'status' => 'lobby',
			'host_id' => get_current_user_id(), 'adventure_id' => $adventure_id ? $map[ $adventure_id ] : 0, 'run_id' => 0, 'participants' => array() );
		if ( null !== $invitations ) { $state['invitations'] = $invitations; }
		$written = ps_pen_paper_write_play_round( (int) $id, array( 'revision' => 0 ), $state, true );
		if ( is_wp_error( $written ) || ! $character_id ) { return $written; }
		$round = ps_pen_paper_get_campaign( (int) $id );
		$choices = ps_pen_paper_play_characters( $source, (int) $id );
		if ( is_wp_error( $choices ) ) { return $choices; }
		if ( ! isset( $choices[ $character_id ] ) || ! is_string( $character_revision ) ||
			(string) $choices[ $character_id ]['state']['revision'] !== $character_revision ) { return ps_pen_paper_play_error(); }
		$busy = ps_pen_paper_play_characters_busy( $round, array( $character_id ) );
		if ( is_wp_error( $busy ) ) { return $busy; }
		$participant = ps_pen_paper_write_play_participant( $round, $choices[ $character_id ] );
		if ( is_wp_error( $participant ) ) { return $participant; }
		if ( ! add_post_meta( $id, '_pspp_player', (string) get_current_user_id() ) ) { return ps_pen_paper_play_error(); }
		$before = $state;
		$state['participants'][] = $participant;
		$state['revision']++;
		$joined = ps_pen_paper_write_play_round( (int) $id, $before, $state );
		if ( is_wp_error( $joined ) || 'group' === $mode ) { return $joined; }
		$before = $state;
		$state['status'] = 'active';
		$started = ps_pen_paper_write_play_start( $round, $state );
		if ( is_wp_error( $started ) ) { return $started; }
		$clock = ps_pen_paper_write_play_clock( $round, $state );
		if ( is_wp_error( $clock ) ) { return $clock; }
		$state['revision']++;
		return ps_pen_paper_write_play_round( (int) $id, $before, $state );
	} );
	return is_wp_error( $result ) ? $result : $id;
}

function ps_pen_paper_write_play_round( $id, $before, $after, $new = false ) {
	if ( ! ps_pen_paper_valid_play_round( $after ) ) { return ps_pen_paper_play_error(); }
	return ps_pen_paper_write_document_event( $id, $id, '_pspp_play_round', $before, $after,
		array( 'kind' => 'play_round', 'visibility' => 'group', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), $new );
}

function ps_pen_paper_play_characters( $source, $rules_id = 0 ) {
	$link = ps_pen_paper_get_universe_link( $source->ID );
	if ( is_wp_error( $link ) ) { return $link; }
	if ( $rules_id ) {
		$round_link = ps_pen_paper_get_universe_link( $rules_id );
		if ( is_wp_error( $round_link ) ) { return $round_link; }
		if ( $round_link['universe_id'] !== $link['universe_id'] ) {
			return ps_pen_paper_play_error( __( 'Kampagne und Runde gehören nicht mehr zum selben Universum. Bitte informiere die Spielleitung.', 'ps-pen-paper' ) );
		}
	}
	$choices = array(); $rules = ps_pen_paper_get_rules( $rules_id ?: $source->ID );
	if ( ! $link['universe_id'] ) { return $choices; }
	foreach ( get_posts( array( 'post_type' => 'pspp_character', 'post_status' => 'private', 'post_parent' => $link['universe_id'],
		'author' => get_current_user_id(), 'posts_per_page' => -1 ) ) as $character ) {
		$state = ps_pen_paper_raw_character_state( $character->ID );
		if ( is_wp_error( $state ) ) { return $state; }
		if ( ! $state['active'] || isset( $state['context'] ) ||
			ps_pen_paper_character_schema( $rules ) !== ps_pen_paper_character_schema( ps_pen_paper_get_rules( $link['universe_id'] ) ) ) { continue; }
		$choices[ $character->ID ] = array( 'post' => $character, 'state' => $state );
		$allowed = ps_pen_paper_require_character_conditions( ps_pen_paper_record_post( $link['universe_id'] ), (int) $character->ID, 'join' );
		if ( is_wp_error( $allowed ) ) {
			if ( 'character_condition_blocked' !== $allowed->get_error_code() ) { return $allowed; }
			$choices[ $character->ID ]['unavailable'] = $allowed->get_error_message();
		}
	}
	return $choices;
}

function ps_pen_paper_write_play_participant( $round, $choice ) {
	$base = $choice['state']; $base_post = $choice['post']; $user_id = get_current_user_id();
	if ( (int) $base_post->post_author !== $user_id ) { return ps_pen_paper_play_error(); }
	$allowed = ps_pen_paper_require_character_conditions( ps_pen_paper_record_post( $base_post->post_parent ), (int) $base_post->ID, 'join' );
	if ( is_wp_error( $allowed ) ) { return $allowed; }
	$rules = ps_pen_paper_get_rules( $round->ID );
	$snapshot = ps_pen_paper_context_snapshot( $base['active'], $rules );
	if ( is_wp_error( $snapshot ) ) { return $snapshot; }
	$alias = wp_insert_post( array( 'post_type' => 'pspp_character', 'post_status' => 'draft', 'post_parent' => $round->ID,
		'post_author' => $user_id, 'post_title' => 'PS Pen & Paper Character' ), true );
	if ( is_wp_error( $alias ) || ! $alias ) { return ps_pen_paper_play_error(); }
	$state = array( 'revision' => 1, 'active' => $snapshot, 'pending' => null, 'context' => array( 'universe_id' => (int) $base_post->post_parent,
		'source_id' => (int) $base_post->ID, 'source_revision' => $base['revision'], 'mode' => 'shared', 'overrides' => array(), 'note' => '', 'gear_shared' => true ) );
	$event = ps_pen_paper_write_document_event( $round->ID, $alias, '_pspp_character', array( 'revision' => 0, 'active' => null, 'pending' => null ), $state,
		array( 'kind' => 'character_created', 'visibility' => 'owner', 'recipient_id' => $user_id, 'fields' => $rules['fields'], 'rules_revision' => $rules['revision'] ), true );
	if ( is_wp_error( $event ) ) { return $event; }
	$portrait = ps_pen_paper_write_base_portrait( $round, (int) $alias, (int) $base_post->ID );
	if ( is_wp_error( $portrait ) ) { return $portrait; }
	return array( 'user_id' => $user_id, 'source_id' => (int) $base_post->ID, 'character_id' => (int) $alias, 'active' => true );
}

function ps_pen_paper_write_play_clock( $round, $state ) {
	$clock = ps_pen_paper_game_round( $round->ID );
	if ( is_wp_error( $clock ) ) { return $clock; }
	$next = $clock; $next['revision']++; $next['active'] = in_array( $state['status'], array( 'active', 'paused' ), true );
	return ps_pen_paper_write_document_event( $round->ID, $round->ID, '_pspp_game_round', $clock, $next,
		array( 'kind' => 'game_round', 'visibility' => 'group', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
}

function ps_pen_paper_change_play_round( $round, $operation, $revision, $source_id = 0, $source_revision = '' ) {
	$before = $round ? ps_pen_paper_play_round( $round->ID ) : null;
	if ( ! $before || is_wp_error( $before ) || ! in_array( $operation, array( 'join', 'leave', 'start', 'pause', 'complete', 'decline_invite', 'revoke_invite' ), true ) ) { return ps_pen_paper_play_error(); }
	$source = ps_pen_paper_get_campaign( $before['source_id'] );
	if ( ! $source || ! ps_pen_paper_can_access_site() ||
		( ! ps_pen_paper_can_view_campaign( $source ) && ! ( 'decline_invite' === $operation && ps_pen_paper_can_decline_play_invitation( $before ) ) ) ) { return ps_pen_paper_play_error(); }
	$characters = get_posts( array( 'post_type' => 'pspp_character', 'post_status' => 'private', 'post_parent' => $round->ID, 'posts_per_page' => -1 ) );
	if ( 'join' === $operation ) {
		$choices = ps_pen_paper_play_characters( $source, $round->ID );
		if ( is_wp_error( $choices ) || ! isset( $choices[ $source_id ] ) ) { return ps_pen_paper_play_error( __( 'Wähle einen eigenen freigegebenen Charakter mit passenden Universumsregeln.', 'ps-pen-paper' ) ); }
		$characters[] = $choices[ $source_id ]['post'];
	}
	return ps_pen_paper_with_item_character_locks( $round, $characters, function () use ( $round, $source, $operation, $revision, $source_id, $source_revision ) {
		return ps_pen_paper_scope_lock( $source->ID, function () use ( $round, $source, $operation, $revision, $source_id, $source_revision ) {
		return ps_pen_paper_atomic_event( (int) $round->ID, function () use ( $round, $source, $operation, $revision, $source_id, $source_revision ) {
			$before = ps_pen_paper_play_round( $round->ID );
			if ( is_wp_error( $before ) || ! $before || ! is_string( $revision ) || (string) $before['revision'] !== $revision ||
				( ! ps_pen_paper_can_view_campaign( $source ) && ! ( 'decline_invite' === $operation && ps_pen_paper_can_decline_play_invitation( $before ) ) ) ) { return ps_pen_paper_play_error(); }
			$after = $before; $user_id = get_current_user_id();
			$member = ps_pen_paper_play_participant( $before, $user_id );
			$controller = ps_pen_paper_can_manage_campaign( $source ) || ( 'guided' !== $before['mode'] && $before['host_id'] === $user_id );
			if ( in_array( $operation, array( 'start', 'pause', 'complete' ), true ) && ! $controller ) { return ps_pen_paper_play_error(); }
			if ( 'join' === $operation ) {
				if ( isset( $before['invitations'] ) && $before['host_id'] !== $user_id && ! $member ) {
					$index = ps_pen_paper_play_invitation_index( $before, $user_id );
					if ( false === $index || ! in_array( $before['invitations'][ $index ]['status'], array( 'offered', 'accepted' ), true ) ||
						! ps_pen_paper_play_friend_allowed( $user_id, $before['invitations'][ $index ]['inviter_id'] ) ) {
						return ps_pen_paper_play_error( __( 'Für diese Gruppe brauchst du eine gültige Einladung eines PS-Community-Freundes.', 'ps-pen-paper' ) );
					}
					$after['invitations'][ $index ]['status'] = 'accepted';
				}
				if ( ! in_array( $before['status'], array( 'lobby', 'paused' ), true ) ||
					( 'solo' === $before['mode'] && $before['host_id'] !== $user_id ) ) { return ps_pen_paper_play_error( __( 'Die Runde muss vor einer neuen Teilnahme pausiert werden.', 'ps-pen-paper' ) ); }
				$choices = ps_pen_paper_play_characters( $source, $round->ID ); $choice = ! is_wp_error( $choices ) ? ( $choices[ $source_id ] ?? null ) : null;
				if ( ! $choice || (string) $choice['state']['revision'] !== $source_revision ) { return ps_pen_paper_play_error(); }
				if ( 'paused' === $before['status'] ) {
					$busy = ps_pen_paper_play_characters_busy( $round, array( $source_id ) );
					if ( is_wp_error( $busy ) ) { return $busy; }
				}
				$index = array_search( $user_id, array_column( $before['participants'], 'user_id' ), true );
				if ( false !== $index && $before['participants'][ $index ]['source_id'] === $source_id ) {
					if ( $member ) { return 0; }
					$after['participants'][ $index ]['active'] = true;
				} else {
					if ( $member && 'paused' === $before['status'] ) {
						if ( in_array( $before['status'], array( 'active', 'paused' ), true ) ) {
							$expired = ps_pen_paper_write_expired_item_effects( $round, array( ps_pen_paper_record_post( $member['character_id'] ) ), 0, true );
							if ( is_wp_error( $expired ) ) { return $expired; }
						}
					}
					$participant = ps_pen_paper_write_play_participant( $round, $choice );
					if ( is_wp_error( $participant ) ) { return $participant; }
					if ( false === $index ) { $after['participants'][] = $participant; }
					else { $after['participants'][ $index ] = $participant; }
				}
				if ( ! get_post_meta( $round->ID, '_pspp_player', false ) || ! in_array( (string) $user_id, get_post_meta( $round->ID, '_pspp_player', false ), true ) ) {
					if ( ! add_post_meta( $round->ID, '_pspp_player', (string) $user_id ) ) { return ps_pen_paper_play_error(); }
				}
			} elseif ( in_array( $operation, array( 'decline_invite', 'revoke_invite' ), true ) ) {
				$target = 'decline_invite' === $operation ? $user_id : $source_id;
				$index = ps_pen_paper_play_invitation_index( $before, $target );
				if ( false === $index || 'offered' !== $before['invitations'][ $index ]['status'] ||
					( 'revoke_invite' === $operation && ! $controller ) || ! in_array( $before['status'], array( 'lobby', 'paused' ), true ) ) { return ps_pen_paper_play_error(); }
				$after['invitations'][ $index ]['status'] = 'decline_invite' === $operation ? 'declined' : 'revoked';
			} elseif ( 'leave' === $operation ) {
				if ( ! $member ) { return ps_pen_paper_play_error(); }
				$expired = ps_pen_paper_write_expired_item_effects( $round, array( ps_pen_paper_record_post( $member['character_id'] ) ), 0, true );
				if ( is_wp_error( $expired ) ) { return $expired; }
				foreach ( $after['participants'] as &$participant ) { if ( $participant['user_id'] === $user_id ) { $participant['active'] = false; } }
				unset( $participant );
				if ( ! delete_post_meta( $round->ID, '_pspp_player', (string) $user_id ) && (int) $round->post_author !== $user_id ) { return ps_pen_paper_play_error(); }
				ps_pen_paper_play_after_departure( $after, $user_id );
			} elseif ( 'pause' === $operation ) {
				if ( 'active' !== $before['status'] ) { return ps_pen_paper_play_error(); }
				$after['status'] = 'paused';
			} elseif ( 'complete' === $operation ) {
				if ( 'completed' === $before['status'] ) { return ps_pen_paper_play_error(); }
				$after['status'] = 'completed';
			} else {
				if ( isset( $before['invitations'] ) && 'lobby' === $before['status'] &&
					in_array( 'offered', array_column( $before['invitations'], 'status' ), true ) ) {
					return ps_pen_paper_play_error( __( 'Warte auf offene Einladungen oder ziehe sie vor dem gemeinsamen Start zurück.', 'ps-pen-paper' ) );
				}
				if ( ! in_array( $before['status'], array( 'lobby', 'paused' ), true ) ||
					! array_filter( $before['participants'], static function ( $participant ) { return $participant['active']; } ) ) { return ps_pen_paper_play_error( __( 'Mindestens ein Spieler muss zuerst einen Charakter auswählen.', 'ps-pen-paper' ) ); }
				$options = ps_pen_paper_play_options( $source->ID );
				if ( is_wp_error( $options ) || ( 'lobby' === $before['status'] &&
					( ! in_array( $before['mode'], $options['modes'], true ) || ( ! ps_pen_paper_can_manage_campaign( $source ) && ! $options['player_starts'] ) ) ) ) { return ps_pen_paper_play_error(); }
				$busy = ps_pen_paper_play_characters_busy( $round, array_column( array_filter( $before['participants'], static function ( $participant ) { return $participant['active']; } ), 'source_id' ) );
				if ( is_wp_error( $busy ) ) { return $busy; }
				$after['status'] = 'active';
				if ( 'lobby' === $before['status'] ) {
					$started = ps_pen_paper_write_play_start( $round, $after );
					if ( is_wp_error( $started ) ) { return $started; }
				}
			}
			$synced = ps_pen_paper_write_play_group_participants( $round, $after );
			if ( is_wp_error( $synced ) ) { return $synced; }
			if ( 'completed' === $after['status'] && in_array( $before['status'], array( 'active', 'paused' ), true ) ) {
				$characters = get_posts( array( 'post_type' => 'pspp_character', 'post_status' => 'private', 'post_parent' => $round->ID, 'posts_per_page' => -1 ) );
				$ids = array_column( array_filter( $before['participants'], static function ( $participant ) { return $participant['active']; } ), 'character_id' );
				$characters = array_filter( $characters, static function ( $character ) use ( $ids ) { return in_array( (int) $character->ID, $ids, true ); } );
				$expired = ps_pen_paper_write_expired_item_effects( $round, $characters, 0, true );
				if ( is_wp_error( $expired ) ) { return $expired; }
			}
			$event = ps_pen_paper_write_play_clock( $round, $after );
			if ( is_wp_error( $event ) ) { return $event; }
			$after['revision']++;
			return ps_pen_paper_write_play_round( (int) $round->ID, $before, $after );
		} );
		} );
	} );
}

function ps_pen_paper_play_after_departure( &$state, $user_id ) {
	$active = array_values( array_filter( $state['participants'], static function ( $participant ) { return $participant['active']; } ) );
	if ( ! $active && 'active' === $state['status'] ) { $state['status'] = 'paused'; }
	if ( 'group' === $state['mode'] && $state['host_id'] === $user_id && $active ) { $state['host_id'] = $active[0]['user_id']; }
}

function ps_pen_paper_write_play_campaign_departure( $source, $user_id ) {
	$rounds = ps_pen_paper_play_rounds( $source );
	if ( is_wp_error( $rounds ) ) { return $rounds; }
	foreach ( $rounds as $row ) {
		$round = $row['post']; $before = $row['state']; $after = $before;
		$member = ps_pen_paper_play_participant( $before, $user_id );
		if ( ! $member && $before['host_id'] !== $user_id ) { continue; }
		if ( $member ) {
			if ( in_array( $before['status'], array( 'active', 'paused' ), true ) ) {
				$expired = ps_pen_paper_write_expired_item_effects( $round, array( ps_pen_paper_record_post( $member['character_id'] ) ), 0, true );
				if ( is_wp_error( $expired ) ) { return $expired; }
			}
			foreach ( $after['participants'] as &$participant ) { if ( $participant['user_id'] === $user_id ) { $participant['active'] = false; } }
			unset( $participant );
			if ( ! delete_post_meta( $round->ID, '_pspp_player', (string) $user_id ) ) { return ps_pen_paper_play_error(); }
		}
		ps_pen_paper_play_after_departure( $after, $user_id );
		if ( 'guided' === $after['mode'] && $after['host_id'] === $user_id ) { $after['host_id'] = (int) $source->post_author; }
		if ( 'guided' !== $after['mode'] && $after['host_id'] === $user_id &&
			! array_filter( $after['participants'], static function ( $participant ) { return $participant['active']; } ) ) { $after['status'] = 'completed'; }
		$synced = ps_pen_paper_write_play_group_participants( $round, $after );
		if ( is_wp_error( $synced ) ) { return $synced; }
		$clock = ps_pen_paper_game_round( $round->ID );
		if ( is_wp_error( $clock ) ) { return $clock; }
		$next = $clock; $next['revision']++; $next['active'] = in_array( $after['status'], array( 'active', 'paused' ), true );
		$written = ps_pen_paper_write_document_event( $round->ID, $round->ID, '_pspp_game_round', $clock, $next,
			array( 'kind' => 'game_round', 'visibility' => 'group', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
		if ( is_wp_error( $written ) ) { return $written; }
		$after['revision']++;
		$written = ps_pen_paper_write_play_round( (int) $round->ID, $before, $after );
		if ( is_wp_error( $written ) ) { return $written; }
	}
	return 0;
}

function ps_pen_paper_play_characters_busy( $round, $ids ) {
	foreach ( get_posts( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'meta_key' => '_pspp_play_round', 'posts_per_page' => -1 ) ) as $other ) {
		if ( $other->ID === $round->ID ) { continue; }
		$other_state = ps_pen_paper_play_round( $other->ID );
		if ( is_wp_error( $other_state ) ) { return $other_state; }
		if ( ! in_array( $other_state['status'], array( 'active', 'paused' ), true ) ) { continue; }
		foreach ( $other_state['participants'] as $participant ) {
			if ( $participant['active'] && in_array( $participant['source_id'], $ids, true ) ) {
				return ps_pen_paper_play_error( __( 'Ein ausgewählter Charakter spielt bereits in einer anderen laufenden Runde. Beende dort zuerst seine Teilnahme.', 'ps-pen-paper' ) );
			}
		}
	}
	foreach ( $ids as $id ) {
		$base = ps_pen_paper_record_post( $id );
		if ( ! $base ) { return ps_pen_paper_play_error(); }
		foreach ( ps_pen_paper_item_shared_aliases( (int) $base->post_parent, $id ) as $alias ) {
			if ( (int) $alias->post_parent === (int) $round->ID ) { continue; }
			$other = ps_pen_paper_get_campaign( (int) $alias->post_parent );
			if ( ! $other ) { return ps_pen_paper_play_error(); }
			$other_state = ps_pen_paper_play_round( $other->ID );
			if ( is_wp_error( $other_state ) ) { return $other_state; }
			if ( $other_state ) { continue; }
			$clock = ps_pen_paper_game_round( $other->ID );
			if ( is_wp_error( $clock ) ) { return $clock; }
			if ( $clock['active'] && ps_pen_paper_campaign_role( $other, (int) $alias->post_author ) ) {
				return ps_pen_paper_play_error( __( 'Dieser Charakter nimmt noch an einer bisherigen Spielsitzung teil. Beende diese zuerst.', 'ps-pen-paper' ) );
			}
		}
	}
	return true;
}

function ps_pen_paper_write_play_start( $round, &$state ) {
	if ( 'guided' === $state['mode'] ) {
		foreach ( get_posts( array( 'post_type' => 'pspp_scene', 'post_status' => 'private', 'post_parent' => $round->ID,
			'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $scene ) {
			$value = ps_pen_paper_get_scene( $round, $scene->ID );
			if ( is_wp_error( $value ) ) { return $value; }
			if ( ! $value['published'] ) { continue; }
			return ps_pen_paper_write_document_event( $round->ID, $round->ID, '_pspp_scene_selection',
				array( 'revision' => 0, 'scene_id' => 0, 'name' => '' ), array( 'revision' => 1, 'scene_id' => (int) $scene->ID, 'name' => $value['published']['title'] ),
				array( 'kind' => 'scene_selected', 'visibility' => 'group', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
		}
		return 0;
	}
	$adventure = get_post_meta( $state['adventure_id'], '_pspp_adventure', true );
	if ( ! ps_pen_paper_valid_adventure_state( $adventure ) || 'published' !== $adventure['status'] ) { return ps_pen_paper_play_error(); }
	$snapshot = $adventure['published'];
	if ( 'solo' === $state['mode'] ) {
		$participant = ps_pen_paper_play_participant( $state, $state['host_id'] );
		if ( ! $participant ) { return ps_pen_paper_play_error(); }
		$character = ps_pen_paper_adventure_character( $round, $participant['character_id'], true );
		if ( is_wp_error( $character ) ) { return $character; }
		$access = ps_pen_paper_scenario_access( $round, $state['adventure_id'], $snapshot, $character, 'solo' );
		if ( is_wp_error( $access ) || ! $access['met'] ) { return is_wp_error( $access ) ? $access : ps_pen_paper_scenario_access_error( $access ); }
		$state['run_id'] = ps_pen_paper_write_adventure_start( $round, $state['adventure_id'], $snapshot, $character, $access );
		return is_wp_error( $state['run_id'] ) ? $state['run_id'] : 0;
	}
	$group = ps_pen_paper_adventure_group_initial( $state['adventure_id'], $snapshot, $snapshot['title'], 'any' );
	foreach ( $state['participants'] as $participant ) {
		if ( ! $participant['active'] ) { continue; }
		$character = ps_pen_paper_play_internal_character( $round, $participant );
		if ( is_wp_error( $character ) ) { return $character; }
		$access = ps_pen_paper_scenario_access( $round, $state['adventure_id'], $snapshot, $character, 'group' );
		if ( is_wp_error( $access ) || ! $access['met'] ) { return is_wp_error( $access ) ? $access : ps_pen_paper_scenario_access_error( $access ); }
		$row = array( 'character_id' => $participant['character_id'], 'owner_id' => $participant['user_id'], 'active' => true );
		if ( isset( $snapshot['scenario'] ) ) { $row['scenario_attempt'] = $access['attempt']; }
		$group['participants'][] = $row;
	}
	$id = wp_insert_post( array( 'post_type' => 'pspp_adventure_group', 'post_status' => 'draft', 'post_parent' => $round->ID, 'post_title' => 'PS Pen & Paper Adventure Group' ), true );
	if ( is_wp_error( $id ) || ! $id || ! ps_pen_paper_valid_adventure_group( $group ) ) { return ps_pen_paper_play_error(); }
	$state['run_id'] = (int) $id;
	return ps_pen_paper_write_document_event( $round->ID, $id, '_pspp_adventure_group', ps_pen_paper_adventure_group_default(), $group,
		array( 'kind' => 'adventure_group', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => $snapshot['rules_revision'] ), true );
}

function ps_pen_paper_write_play_group_participants( $round, $state ) {
	if ( 'group' !== $state['mode'] || ! $state['run_id'] ) { return 0; }
	$before = get_post_meta( $state['run_id'], '_pspp_adventure_group', true );
	if ( ! ps_pen_paper_valid_adventure_group( $before ) ) { return ps_pen_paper_play_error(); }
	$after = $before; $after['proposal'] = null;
	if ( 'completed' !== $before['status'] ) { $after['status'] = in_array( $state['status'], array( 'paused', 'completed' ), true ) ? 'paused' : 'active'; }
	foreach ( $after['participants'] as &$participant ) {
		$member = ps_pen_paper_play_participant( $state, $participant['owner_id'] );
		$participant['active'] = $member && $member['character_id'] === $participant['character_id'];
	}
	unset( $participant );
	foreach ( $state['participants'] as $participant ) {
		if ( ! $participant['active'] || in_array( $participant['character_id'], array_column( $after['participants'], 'character_id' ), true ) ) { continue; }
		$character = ps_pen_paper_play_internal_character( $round, $participant );
		if ( is_wp_error( $character ) ) { return $character; }
		$access = ps_pen_paper_scenario_access( $round, $state['adventure_id'], $after['snapshot'], $character, 'group', $state['run_id'] );
		if ( is_wp_error( $access ) || ! $access['met'] ) { return is_wp_error( $access ) ? $access : ps_pen_paper_scenario_access_error( $access ); }
		$row = array( 'character_id' => $participant['character_id'], 'owner_id' => $participant['user_id'], 'active' => true );
		if ( isset( $after['snapshot']['scenario'] ) ) {
			$row['scenario_attempt'] = $access['attempt'];
			foreach ( $before['participants'] as $previous ) {
				if ( $previous['owner_id'] === $participant['user_id'] ) { $row['scenario_attempt'] = $previous['scenario_attempt']; break; }
			}
		}
		$after['participants'][] = $row;
	}
	if ( $after === $before ) { return 0; }
	$after['revision']++;
	if ( ! ps_pen_paper_valid_adventure_group( $after ) ) { return ps_pen_paper_play_error(); }
	return ps_pen_paper_write_document_event( $round->ID, $state['run_id'], '_pspp_adventure_group', $before, $after,
		array( 'kind' => 'adventure_group', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => $after['snapshot']['rules_revision'] ), false );
}

// The authenticated round controller checks entry requirements without receiving other players' private sheets.
function ps_pen_paper_play_internal_character( $round, $participant ) {
	$post = ps_pen_paper_record_post( $participant['character_id'] );
	if ( ! $post || 'pspp_character' !== $post->post_type || 'private' !== $post->post_status ||
		(int) $post->post_parent !== (int) $round->ID || (int) $post->post_author !== $participant['user_id'] ||
		! is_user_member_of_blog( $participant['user_id'] ) ) { return ps_pen_paper_play_error(); }
	$raw = ps_pen_paper_raw_character_state( $post->ID );
	if ( is_wp_error( $raw ) || ( $raw['context']['source_id'] ?? 0 ) !== $participant['source_id'] ) { return ps_pen_paper_play_error(); }
	$state = ps_pen_paper_resolve_character_state( $round, $post );
	return is_wp_error( $state ) ? $state : array( 'post' => $post, 'state' => $state );
}

function ps_pen_paper_write_play_progress( $round, $status ) {
	$before = ps_pen_paper_play_round( $round->ID );
	if ( ! $before || is_wp_error( $before ) ) { return is_wp_error( $before ) ? $before : 0; }
	if ( 'active' !== $before['status'] || 'guided' === $before['mode'] ) { return ps_pen_paper_play_error(); }
	$clock = ps_pen_paper_game_round( $round->ID );
	if ( is_wp_error( $clock ) || $clock['round'] >= 1000000000 ) { return ps_pen_paper_play_error(); }
	$next = $clock; $next['revision']++; $next['round']++; $next['active'] = 'completed' !== $status;
	$characters = get_posts( array( 'post_type' => 'pspp_character', 'post_status' => 'private', 'post_parent' => $round->ID, 'posts_per_page' => -1 ) );
	$ids = array_column( array_filter( $before['participants'], static function ( $participant ) { return $participant['active']; } ), 'character_id' );
	$characters = array_filter( $characters, static function ( $character ) use ( $ids ) { return in_array( (int) $character->ID, $ids, true ); } );
	$expired = ps_pen_paper_write_expired_item_effects( $round, $characters, $next['round'], 'completed' === $status );
	if ( is_wp_error( $expired ) ) { return $expired; }
	$event = ps_pen_paper_write_document_event( $round->ID, $round->ID, '_pspp_game_round', $clock, $next,
		array( 'kind' => 'game_round', 'visibility' => 'group', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0 ), false );
	if ( is_wp_error( $event ) || 'completed' !== $status ) { return $event; }
	$after = $before; $after['revision']++; $after['status'] = 'completed';
	return ps_pen_paper_write_play_round( (int) $round->ID, $before, $after );
}

function ps_pen_paper_shared_gear_target( $id ) {
	$character = ps_pen_paper_record_post( $id );
	if ( ! $character || 'pspp_character' !== $character->post_type ) { return null; }
	$raw = get_post_meta( $id, '_pspp_character', true );
	if ( ! is_array( $raw ) ) { return ps_pen_paper_play_error(); }
	if ( isset( $raw['context'] ) && ! is_array( $raw['context'] ) ) { return ps_pen_paper_play_error(); }
	if ( ! array_key_exists( 'gear_shared', $raw['context'] ?? array() ) ) { return null; }
	$scope = ps_pen_paper_get_campaign( (int) $character->post_parent );
	if ( ! $scope ) { return ps_pen_paper_play_error(); }
	$context = ps_pen_paper_validate_context( $raw['context'], ps_pen_paper_get_rules( $scope->ID ) );
	if ( is_wp_error( $context ) ) { return $context; }
	$source = ps_pen_paper_record_post( $context['source_id'] ); $link = ps_pen_paper_get_universe_link( $scope->ID );
	if ( ! $source || 'pspp_character' !== $source->post_type || 'private' !== $source->post_status ||
		(int) $source->post_parent !== $context['universe_id'] || (int) $source->post_author !== (int) $character->post_author ||
		is_wp_error( $link ) || $link['universe_id'] !== $context['universe_id'] ||
		ps_pen_paper_character_schema( ps_pen_paper_get_rules( $scope->ID ) ) !== ps_pen_paper_character_schema( ps_pen_paper_get_rules( $context['universe_id'] ) ) ) { return ps_pen_paper_play_error(); }
	return array( 'scope_id' => $context['universe_id'], 'character_id' => $context['source_id'] );
}

function ps_pen_paper_play_reserved_items( $target, $except = 0 ) {
	$ids = array(); $scopes = array();
	foreach ( ps_pen_paper_item_shared_aliases( $target['scope_id'], $target['character_id'] ) as $alias ) {
		$raw = ps_pen_paper_raw_character_state( $alias->ID );
		if ( is_wp_error( $raw ) ) { return $raw; }
		if ( empty( $raw['context']['gear_shared'] ) ) { continue; }
		$ids[] = (int) $alias->ID; $scopes[] = (int) $alias->post_parent;
	}
	if ( ! $scopes ) { return array(); }
	$reserved = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_trade', 'post_status' => 'private', 'post_parent__in' => array_unique( $scopes ), 'posts_per_page' => -1 ) ) as $post ) {
		if ( (int) $post->ID === $except ) { continue; }
		$state = get_post_meta( $post->ID, '_pspp_trade', true );
		if ( ! ps_pen_paper_valid_trade( $state ) || ! $state['revision'] ) { return ps_pen_paper_trade_error(); }
		if ( ! ps_pen_paper_trade_open( $state ) ) { continue; }
		foreach ( array( 'from' => 'give', 'to' => 'take' ) as $party => $side ) {
			if ( in_array( $state['offer'][ $party ], $ids, true ) ) {
				$reserved = array_merge( $reserved, array_keys( ps_pen_paper_trade_items( $state['offer'], $side ) ) );
			}
		}
	}
	return array_values( array_unique( $reserved ) );
}

function ps_pen_paper_write_play_trade_closures( $round, $character_id ) {
	foreach ( get_posts( array( 'post_type' => 'pspp_trade', 'post_status' => 'private', 'post_parent' => $round->ID, 'posts_per_page' => -1 ) ) as $post ) {
		$before = get_post_meta( $post->ID, '_pspp_trade', true );
		if ( ! ps_pen_paper_valid_trade( $before ) || ! $before['revision'] ) { return ps_pen_paper_trade_error(); }
		if ( ! ps_pen_paper_trade_open( $before ) || ! in_array( $character_id, array( $before['offer']['from'], $before['offer']['to'] ), true ) ) { continue; }
		$after = $before; $after['status'] = 'cancelled'; $id = (int) $post->ID;
		$result = ps_pen_paper_write_trade( $round, $id, $before, $after, 'play_ended' );
		if ( is_wp_error( $result ) ) { return $result; }
	}
	return 0;
}
