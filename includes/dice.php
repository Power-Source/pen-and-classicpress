<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_roll_visibility_labels() {
	return array(
		'group'   => __( 'Gruppe: alle Kampagnenmitglieder', 'ps-pen-paper' ),
		'private' => __( 'Privat: Würfelnder, betroffener Spieler und Spielleiter', 'ps-pen-paper' ),
		'gm'      => __( 'Verdeckt: nur Spielleiter', 'ps-pen-paper' ),
	);
}

function ps_pen_paper_allowed_roll_visibilities( $rules ) {
	return isset( $rules['roll_visibilities'] ) ? $rules['roll_visibilities'] : array( 'group', 'private' );
}

function ps_pen_paper_register_roll_type() {
	register_post_type( 'pspp_roll', array(
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => false,
		'show_in_rest'        => false,
		'capability_type'     => array( 'pspp_roll', 'pspp_rolls' ),
		'map_meta_cap'        => true,
		'supports'            => false,
	) );
}

function ps_pen_paper_parse_dice( $expression, $allowed_dice ) {
	$error = new WP_Error( 'invalid_expression', __( 'Verwende einen Ausdruck wie 3W6 + 2 oder W20 + 2W6 - 1. Höchstens 10 Würfelgruppen, insgesamt 100 Würfel und ein abschließender Modifikator zwischen −1000000 und 1000000 sind erlaubt.', 'ps-pen-paper' ) );
	if ( ! is_string( $expression ) || strlen( $expression ) > 200 ) {
		return $error;
	}
	$expression = str_replace( array( ' ', "\t" ), '', $expression );
	$die = '(?:[1-9]\d{0,2})?[wWdD](?:100|20|12|10|8|6|4)';
	if ( ! preg_match( '/^(' . $die . '(?:\+' . $die . ')*)([+-]\d{1,7})?$/D', $expression, $parts ) ) {
		return $error;
	}
	$modifier = isset( $parts[2] ) ? (int) $parts[2] : 0;
	if ( abs( $modifier ) > 1000000 ) {
		return $error;
	}
	preg_match_all( '/([1-9]\d{0,2})?[wWdD](100|20|12|10|8|6|4)/', $parts[1], $matches, PREG_SET_ORDER );
	$groups = array();
	$labels = array();
	$count = 0;
	foreach ( $matches as $match ) {
		if ( ! in_array( $match[2], $allowed_dice, true ) ) {
			return new WP_Error( 'die_not_allowed', __( 'Dieser Würfel ist in der Kampagne nicht erlaubt.', 'ps-pen-paper' ) );
		}
		$quantity = '' === $match[1] ? 1 : (int) $match[1];
		$count += $quantity;
		$groups[] = array( 'count' => $quantity, 'sides' => (int) $match[2] );
		$labels[] = $quantity . 'W' . $match[2];
	}
	if ( $count > 100 || count( $groups ) > 10 ) {
		return $error;
	}
	$canonical = implode( ' + ', $labels );
	if ( $modifier ) {
		$canonical .= ( $modifier > 0 ? ' + ' : ' - ' ) . abs( $modifier );
	}
	return array( 'expression' => $canonical, 'groups' => $groups, 'modifier' => $modifier );
}

function ps_pen_paper_evaluate_dice( $parsed ) {
	$result = array( 'expression' => $parsed['expression'], 'modifier' => $parsed['modifier'], 'total' => $parsed['modifier'], 'groups' => array() );
	foreach ( $parsed['groups'] as $group ) {
		$rolls = array();
		for ( $i = 0; $i < $group['count']; $i++ ) { $rolls[] = wp_rand( 1, $group['sides'] ); }
		$result['total'] += array_sum( $rolls );
		$result['groups'][] = array( 'sides' => $group['sides'], 'rolls' => $rolls );
	}
	return $result;
}

function ps_pen_paper_roll_dice( $campaign_id, $input, $rules_revision ) {
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! current_user_can( 'read' ) || ! $campaign || ! ps_pen_paper_can_view_campaign( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Du darfst in dieser Kampagne nicht würfeln.', 'ps-pen-paper' ) );
	}
	$rules = ps_pen_paper_get_rules( $campaign_id );
	if ( ! is_string( $rules_revision ) || (string) $rules['revision'] !== $rules_revision ) {
		return new WP_Error( 'conflict', __( 'Das Regelwerk wurde geändert. Lade die Würfeloberfläche neu.', 'ps-pen-paper' ) );
	}
	if ( ! is_array( $input ) ) {
		return new WP_Error( 'invalid_roll', __( 'Die Wurfdaten sind unvollständig.', 'ps-pen-paper' ) );
	}
	foreach ( array( 'expression', 'visibility', 'character', 'attribute', 'label' ) as $key ) {
		if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) {
			return new WP_Error( 'invalid_roll', __( 'Bitte prüfe Ausdruck, Sichtbarkeit und Charakterauswahl.', 'ps-pen-paper' ) );
		}
	}
	if ( ! in_array( $input['visibility'], ps_pen_paper_allowed_roll_visibilities( $rules ), true ) || ! array_key_exists( $input['visibility'], ps_pen_paper_roll_visibility_labels() ) ) {
		return new WP_Error( 'invalid_visibility', __( 'Diese Wurfsichtbarkeit ist nicht freigegeben.', 'ps-pen-paper' ) );
	}
	if ( strlen( $input['label'] ) > 200 ) {
		return new WP_Error( 'invalid_label', __( 'Der Anlass darf höchstens 200 Zeichen lang sein.', 'ps-pen-paper' ) );
	}
	$parsed = ps_pen_paper_parse_dice( $input['expression'], $rules['dice'] );
	if ( is_wp_error( $parsed ) ) {
		return $parsed;
	}
	$character_snapshot = null;
	$attribute_snapshot = null;
	$recipient = get_current_user_id();
	if ( '' !== $input['character'] ) {
		if ( ! preg_match( '/^([1-9]\d{0,18}):([1-9]\d{0,18})(?::([1-9]\d{0,18}))?(?::g(?:0|[1-9]\d{0,18}):(?:0|[1-9]\d{0,18}))?$/D', $input['character'], $ids ) || (string) (int) $ids[1] !== $ids[1] || (string) (int) $ids[2] !== $ids[2] ) {
			return new WP_Error( 'invalid_character', __( 'Bitte wähle einen gültigen Charakter.', 'ps-pen-paper' ) );
		}
		$character = ps_pen_paper_get_character( $campaign, (int) $ids[1] );
		if ( ! $character || ! is_user_member_of_blog( (int) $character->post_author ) || '' === ps_pen_paper_campaign_role( $campaign, (int) $character->post_author ) ) {
			return new WP_Error( 'invalid_character', __( 'Dieser Charakter steht für deinen Wurf nicht zur Verfügung.', 'ps-pen-paper' ) );
		}
		$state = ps_pen_paper_character_state( $campaign, $character );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		if ( ps_pen_paper_character_token( $character, $state ) !== $input['character'] || ! $state['active'] ) {
			return new WP_Error( 'conflict', __( 'Der Charakter wurde geändert oder ist noch nicht freigegeben. Lade die Würfeloberfläche neu.', 'ps-pen-paper' ) );
		}
		$recipient = (int) $character->post_author;
		$character_snapshot = array( 'id' => $character->ID, 'name' => $state['active']['name'], 'owner_id' => $recipient, 'revision' => $state['revision'] );
		if ( isset( $state['equipment'] ) ) { $character_snapshot['equipment'] = $state['equipment']; }
		if ( isset( $state['context'] ) ) {
			$character_snapshot['context'] = array(
				'mode' => $state['context']['mode'], 'source_id' => $state['context']['source_id'],
				'source_revision' => isset( $state['base_revision'] ) ? $state['base_revision'] : $state['context']['source_revision'],
				'revision' => $state['revision'],
			);
		}
		if ( '' !== $input['attribute'] ) {
			foreach ( $rules['fields'] as $field ) {
				if ( $input['attribute'] === $field['id'] && in_array( $field['type'], array( 'attribute', 'number' ), true ) ) {
					$attribute_snapshot = array( 'id' => $field['id'], 'label' => $field['label'], 'value' => $state['active']['values'][ $field['id'] ] );
					break;
				}
			}
			if ( null === $attribute_snapshot || ! is_int( $attribute_snapshot['value'] ) ) {
				return new WP_Error( 'invalid_attribute', __( 'Wähle ein Zahlen- oder Attributfeld dieses Charakters.', 'ps-pen-paper' ) );
			}
		}
	} elseif ( '' !== $input['attribute'] ) {
		return new WP_Error( 'invalid_attribute', __( 'Für einen Attributwert musst du einen Charakter auswählen.', 'ps-pen-paper' ) );
	}
	$rolled = ps_pen_paper_evaluate_dice( $parsed );
	$total = $rolled['total'] + ( $attribute_snapshot ? $attribute_snapshot['value'] : 0 );
	$event = array(
		'version'        => 1,
		'expression'     => $parsed['expression'],
		'groups'         => $rolled['groups'],
		'modifier'       => $parsed['modifier'],
		'total'          => $total,
		'visibility'     => $input['visibility'],
		'recipient_id'   => $recipient,
		'actor_name'     => wp_get_current_user()->display_name,
		'label'          => sanitize_text_field( $input['label'] ),
		'character'      => $character_snapshot,
		'attribute'      => $attribute_snapshot,
		'rules_revision' => $rules['revision'],
	);
	$metadata = array( '_pspp_roll' => $event, '_pspp_visibility' => $event['visibility'], '_pspp_recipient' => (string) $recipient, '_pspp_actor' => (string) get_current_user_id() );
	return ps_pen_paper_insert_record( 'pspp_roll', $campaign_id, $metadata );
}

function ps_pen_paper_get_roll( $campaign, $roll_id ) {
	$error = new WP_Error( 'forbidden', __( 'Dieser Wurf ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	if ( ! current_user_can( 'read' ) || ! ps_pen_paper_can_view_campaign( $campaign ) ) {
		return $error;
	}
	$post = ps_pen_paper_record_post( $roll_id );
	if ( ! $post || 'pspp_roll' !== $post->post_type || 'private' !== $post->post_status || (int) $post->post_parent !== (int) $campaign->ID ) {
		return $error;
	}
	$event = get_post_meta( $roll_id, '_pspp_roll', true );
	if ( ! is_array( $event ) || ! isset( $event['version'], $event['visibility'], $event['recipient_id'] ) || 1 !== $event['version'] || ! is_string( $event['visibility'] ) || ! array_key_exists( $event['visibility'], ps_pen_paper_roll_visibility_labels() ) ) {
		return new WP_Error( 'invalid_roll', __( 'Ein gespeicherter Wurf ist beschädigt. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
	}
	if ( isset( $event['character'] ) && is_array( $event['character'] ) && array_key_exists( 'context', $event['character'] ) ) {
		$context = $event['character']['context'];
		if ( ! is_array( $context ) || ! isset( $context['mode'], $context['source_id'], $context['source_revision'], $context['revision'] ) ||
			! is_string( $context['mode'] ) || ! isset( ps_pen_paper_character_context_modes()[ $context['mode'] ] ) ||
			! is_int( $context['source_id'] ) || $context['source_id'] < 1 || ! is_int( $context['source_revision'] ) || $context['source_revision'] < 1 ||
			! is_int( $context['revision'] ) || $context['revision'] < 1 ) {
			return new WP_Error( 'invalid_roll', __( 'Der gespeicherte Wurfkontext ist beschädigt. Bitte informiere den Administrator.', 'ps-pen-paper' ) );
		}
	}
	$is_private_reader = 'private' === $event['visibility'] && ( get_current_user_id() === (int) $post->post_author || get_current_user_id() === $event['recipient_id'] );
	if ( isset( $event['character'] ) && is_array( $event['character'] ) && array_key_exists( 'equipment', $event['character'] ) ) {
		$gear = $event['character']['equipment'];
		if ( ! is_array( $gear ) || ! isset( $gear['revision'], $gear['schema_revision'], $gear['bonuses'] ) ||
			! is_int( $gear['revision'] ) || $gear['revision'] < 0 || ! is_int( $gear['schema_revision'] ) || $gear['schema_revision'] < 0 ||
			! is_array( $gear['bonuses'] ) || count( $gear['bonuses'] ) > 30 ) {
			return new WP_Error( 'invalid_roll', __( 'Die gespeicherte Ausrüstungsherkunft des Wurfs ist beschädigt.', 'ps-pen-paper' ) );
		}
		foreach ( $gear['bonuses'] as $id => $bonus ) {
			if ( ! is_string( $id ) || ! is_int( $bonus ) || abs( $bonus ) > 30000000 ) { return new WP_Error( 'invalid_roll', __( 'Die gespeicherten Ausrüstungsboni des Wurfs sind beschädigt.', 'ps-pen-paper' ) ); }
		}
	}
	if ( ! ps_pen_paper_can_manage_campaign( $campaign ) && 'group' !== $event['visibility'] && ! $is_private_reader ) {
		return $error;
	}
	$event['id'] = $post->ID;
	$event['date'] = get_date_from_gmt( $post->post_date_gmt, 'Y-m-d H:i:s' );
	return $event;
}

function ps_pen_paper_get_rolls( $campaign, $page = 1 ) {
	$query = ps_pen_paper_journal_query( $campaign, $page, array( 'pspp_roll' ) );
	if ( is_wp_error( $query ) ) {
		return $query;
	}
	$rolls = array();
	foreach ( $query->posts as $post ) {
		$event = ps_pen_paper_get_roll( $campaign, $post->ID );
		if ( is_wp_error( $event ) ) {
			return $event;
		}
		$rolls[] = $event;
	}
	return array( 'rolls' => $rolls, 'pages' => (int) $query->max_num_pages );
}
