<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_field_types() {
	return array(
		'text'      => __( 'Kurzer Text', 'ps-pen-paper' ),
		'textarea'  => __( 'Langer Text', 'ps-pen-paper' ),
		'number'    => __( 'Zahl', 'ps-pen-paper' ),
		'attribute' => __( 'Attribut', 'ps-pen-paper' ),
		'resource'  => __( 'Ressource (aktueller Wert und Maximum)', 'ps-pen-paper' ),
	);
}

function ps_pen_paper_get_rules( $campaign_id ) {
	$rules = get_post_meta( $campaign_id, '_pspp_rules', true );
	if ( '' === $rules ) {
		return array( 'revision' => 0, 'name' => '', 'description' => '', 'dice' => array(), 'fields' => array() );
	}
	return $rules;
}

function ps_pen_paper_validate_rules( $input, $previous ) {
	$error = new WP_Error( 'invalid_rules', __( 'Bitte prüfe das Regelwerk: Name, Felder oder Würfel sind ungültig.', 'ps-pen-paper' ) );
	if ( ! is_array( $input ) || ! isset( $input['name'], $input['description'] ) || ! is_string( $input['name'] ) || ! is_string( $input['description'] ) ) {
		return $error;
	}
	$name = sanitize_text_field( $input['name'] );
	if ( '' === $name || strlen( $name ) > 200 || strlen( $input['description'] ) > 20000 ) {
		return $error;
	}
	$dice = isset( $input['dice'] ) ? $input['dice'] : array();
	$fields = isset( $input['fields'] ) ? $input['fields'] : array();
	if ( ! is_array( $dice ) || count( $dice ) > 7 || ! is_array( $fields ) || count( $fields ) > 30 ) {
		return $error;
	}
	$allowed_dice = array( '4', '6', '8', '10', '12', '20', '100' );
	foreach ( $dice as $die ) {
		if ( ! is_string( $die ) || ! in_array( $die, $allowed_dice, true ) ) {
			return $error;
		}
	}
	$validated = array(
		'revision'    => $previous['revision'] + 1,
		'name'        => $name,
		'description' => sanitize_textarea_field( $input['description'] ),
		'dice'        => array_values( array_unique( $dice ) ),
		'fields'      => array(),
	);
	$mode = isset( $input['character_mode'] ) ? $input['character_mode'] : ps_pen_paper_character_mode( $previous );
	if ( ! is_string( $mode ) || ! array_key_exists( $mode, ps_pen_paper_character_modes() ) ) {
		return $error;
	}
	$validated['character_mode'] = $mode;
	if ( array_key_exists( 'roll_visibilities_present', $input ) && '1' !== $input['roll_visibilities_present'] ) {
		return $error;
	}
	$visibilities = array_key_exists( 'roll_visibilities', $input ) ? $input['roll_visibilities'] : ( isset( $input['roll_visibilities_present'] ) ? array() : ps_pen_paper_allowed_roll_visibilities( $previous ) );
	if ( ! is_array( $visibilities ) || count( $visibilities ) > 3 ) {
		return $error;
	}
	foreach ( $visibilities as $visibility ) {
		if ( ! is_string( $visibility ) || ! array_key_exists( $visibility, ps_pen_paper_roll_visibility_labels() ) ) {
			return $error;
		}
	}
	$validated['roll_visibilities'] = array_values( array_unique( $visibilities ) );
	$existing_ids = array_column( $previous['fields'], 'id' );
	$seen_ids = array();
	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			return $error;
		}
		foreach ( array( 'id', 'label', 'type', 'description' ) as $key ) {
			if ( ! isset( $field[ $key ] ) || ! is_string( $field[ $key ] ) ) {
				return $error;
			}
		}
		$id = $field['id'];
		if ( '' === $id ) {
			$id = wp_generate_uuid4();
		} elseif ( ! in_array( $id, $existing_ids, true ) ) {
			return $error;
		}
		$label = sanitize_text_field( $field['label'] );
		if ( in_array( $id, $seen_ids, true ) || '' === $label || strlen( $label ) > 200 || strlen( $field['description'] ) > 2000 || ! array_key_exists( $field['type'], ps_pen_paper_field_types() ) ) {
			return $error;
		}
		if ( isset( $field['required'] ) && '1' !== $field['required'] ) {
			return $error;
		}
		$clean = array(
			'id'          => $id,
			'label'       => $label,
			'type'        => $field['type'],
			'description' => sanitize_textarea_field( $field['description'] ),
			'required'    => isset( $field['required'] ),
		);
		if ( in_array( $clean['type'], array( 'number', 'attribute', 'resource' ), true ) ) {
			foreach ( array( 'min', 'max', 'start' ) as $key ) {
				if ( ! isset( $field[ $key ] ) || ! is_string( $field[ $key ] ) || ! preg_match( '/^-?\d{1,7}$/D', $field[ $key ] ) || abs( (int) $field[ $key ] ) > 1000000 ) {
					return new WP_Error( 'invalid_range', __( 'Zahlenfelder benötigen ganzzahlige Grenzen und Startwerte zwischen −1000000 und 1000000.', 'ps-pen-paper' ) );
				}
				$clean[ $key ] = (int) $field[ $key ];
			}
			if ( $clean['min'] > $clean['max'] || $clean['start'] < $clean['min'] || $clean['start'] > $clean['max'] ) {
				return new WP_Error( 'invalid_range', __( 'Der Startwert muss zwischen Minimum und Maximum liegen.', 'ps-pen-paper' ) );
			}
		}
		$seen_ids[] = $id;
		$validated['fields'][] = $clean;
	}
	return $validated;
}

function ps_pen_paper_save_rules( $campaign_id, $input, $revision ) {
	$campaign = ps_pen_paper_get_character_scope( $campaign_id );
	if ( ! current_user_can( 'read' ) || ! $campaign || ! ps_pen_paper_can_manage_character_scope( $campaign ) ) {
		return new WP_Error( 'forbidden', __( 'Du darfst dieses Regelwerk nicht bearbeiten.', 'ps-pen-paper' ) );
	}
	$previous = ps_pen_paper_get_rules( $campaign_id );
	if ( ! is_string( $revision ) || (string) $previous['revision'] !== $revision ) {
		return new WP_Error( 'conflict', __( 'Das Regelwerk wurde zwischenzeitlich geändert. Lade die Seite neu und übernimm deine Änderungen erneut.', 'ps-pen-paper' ) );
	}
	$rules = ps_pen_paper_validate_rules( $input, $previous );
	if ( is_wp_error( $rules ) ) {
		return $rules;
	}
	if ( ps_pen_paper_has_characters( $campaign_id ) && ps_pen_paper_character_schema( $rules ) !== ps_pen_paper_character_schema( $previous ) ) {
		return new WP_Error( 'schema_in_use', __( 'Es existieren bereits Charaktere. Feldtypen, Pflichtstatus, Wertebereiche und die Feldliste können deshalb noch nicht geändert werden. Bezeichnungen, Reihenfolge, Startwerte und Bearbeitungsrechte bleiben anpassbar.', 'ps-pen-paper' ) );
	}
	$saved = ps_pen_paper_save_document_event( $campaign_id, $campaign_id, '_pspp_rules', $previous, $rules, array(
		'kind' => 'rules_saved', 'visibility' => 'pspp_universe' === $campaign->post_type ? 'gm' : 'group', 'recipient_id' => 0,
		'rules_revision' => $rules['revision'], 'fields' => array(),
	) );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	return $rules;
}
