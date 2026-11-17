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
		'choice'    => __( 'Auswahl', 'ps-pen-paper' ),
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
	$old_fields = array_column( $previous['fields'], null, 'id' );
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
		} elseif ( ! in_array( $id, $existing_ids, true ) && ! wp_is_uuid( $id ) ) {
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
		$edit_mode = array_key_exists( 'edit_mode', $field ) ? $field['edit_mode'] : ( $old_fields[ $id ]['edit_mode'] ?? 'inherit' );
		if ( ! is_string( $edit_mode ) || ! array_key_exists( $edit_mode, ps_pen_paper_field_edit_modes() ) ) { return $error; }
		if ( 'inherit' !== $edit_mode ) { $clean['edit_mode'] = $edit_mode; }
		if ( 'choice' === $clean['type'] ) {
			$choices = isset( $field['choices'] ) ? $field['choices'] : array();
			if ( is_string( $choices ) ) { $choices = preg_split( '/\r\n|\r|\n/', $choices ); }
			if ( ! is_array( $choices ) || ! $choices || count( $choices ) > 50 ) { return $error; }
			$clean['choices'] = array();
			foreach ( $choices as $choice ) {
				if ( ! is_string( $choice ) || '' === sanitize_text_field( $choice ) || strlen( $choice ) > 200 ) { return $error; }
				$choice = sanitize_text_field( $choice );
				if ( in_array( $choice, $clean['choices'], true ) ) { return $error; }
				$clean['choices'][] = $choice;
			}
			$clean['start'] = isset( $field['start'] ) && is_string( $field['start'] ) ? sanitize_text_field( $field['start'] ) : '';
			if ( '' !== $clean['start'] && ! in_array( $clean['start'], $clean['choices'], true ) ) { return $error; }
		} elseif ( in_array( $clean['type'], array( 'text', 'textarea' ), true ) ) {
			$clean['start'] = isset( $field['start'] ) && is_string( $field['start'] )
				? ( 'text' === $clean['type'] ? sanitize_text_field( $field['start'] ) : sanitize_textarea_field( $field['start'] ) )
				: '';
			if ( strlen( $clean['start'] ) > ( 'text' === $clean['type'] ? 200 : 20000 ) ) { return $error; }
			if ( 'text' === $clean['type'] ) {
				$choices = $field['allowed_choices'] ?? ( $field['choices'] ?? array() );
				if ( is_string( $choices ) ) { $choices = '' === trim( $choices ) ? array() : preg_split( '/\r\n|\r|\n/', trim( $choices ) ); }
				if ( ! is_array( $choices ) || count( $choices ) > 50 ) { return $error; }
				if ( $choices ) {
					$clean['choices'] = array();
					foreach ( $choices as $choice ) {
						if ( ! is_string( $choice ) || '' === sanitize_text_field( $choice ) || strlen( $choice ) > 200 ) { return $error; }
						$choice = sanitize_text_field( $choice );
						if ( in_array( $choice, $clean['choices'], true ) ) { return $error; }
						$clean['choices'][] = $choice;
					}
					if ( '' !== $clean['start'] && ! in_array( $clean['start'], $clean['choices'], true ) ) { return $error; }
				}
			}
		} elseif ( in_array( $clean['type'], array( 'number', 'attribute', 'resource' ), true ) ) {
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
	$allocation_mode = isset( $input['allocation_mode'] ) ? $input['allocation_mode'] : ( ! empty( $previous['allocation'] ) ? ( isset( $previous['allocation']['per_level'] ) ? 'level' : 'configured' ) : 'none' );
	if ( ! in_array( $allocation_mode, array( 'none', 'configured', 'level' ), true ) ) { return $error; }
	$allocation = 'none' !== $allocation_mode ? ( isset( $input['allocation'] ) ? $input['allocation'] : null ) : null;
	if ( null !== $allocation ) {
		if ( ! is_array( $allocation ) || array_diff( array_keys( $allocation ), array( 'fields', 'points', 'per_level' ) ) || ! isset( $allocation['fields'], $allocation['points'] ) ||
			! is_array( $allocation['fields'] ) || ! $allocation['fields'] || count( $allocation['fields'] ) > 30 ||
			! is_string( $allocation['points'] ) || ! preg_match( '/^-?\d{1,7}$/D', $allocation['points'] ) || abs( (int) $allocation['points'] ) > 1000000 ) { return $error; }
		$attribute_fields = array();
		foreach ( $validated['fields'] as $field ) {
			if ( 'attribute' === $field['type'] ) { $attribute_fields[ $field['id'] ] = $field; }
		}
		$ids = array();
		$minimum = 0;
		$maximum = 0;
		foreach ( $allocation['fields'] as $id ) {
			if ( ! is_string( $id ) || ! isset( $attribute_fields[ $id ] ) || in_array( $id, $ids, true ) ) { return $error; }
			$ids[] = $id;
			$minimum += $attribute_fields[ $id ]['min'];
			$maximum += $attribute_fields[ $id ]['max'];
		}
		$points = (int) $allocation['points'];
		if ( $points < $minimum || $points > $maximum ) { return $error; }
		$validated['allocation'] = array( 'fields' => $ids, 'points' => $points );
		if ( 'level' === $allocation_mode ) {
			if ( ! isset( $allocation['per_level'] ) || ! is_string( $allocation['per_level'] ) ||
				! preg_match( '/^\d{1,7}$/D', $allocation['per_level'] ) || (int) $allocation['per_level'] > 1000000 || $points < 0 ) { return $error; }
			$validated['allocation']['per_level'] = (int) $allocation['per_level'];
		}
	} else {
		$validated['allocation'] = null;
	}
	if ( array_key_exists( 'dice_mechanics_present', $input ) && '1' !== $input['dice_mechanics_present'] ) { return $error; }
	if ( array_key_exists( 'dice_mechanics', $input ) || isset( $input['dice_mechanics_present'] ) || isset( $previous['dice_mechanics'] ) ) {
		$mechanics = array_key_exists( 'dice_mechanics', $input ) ? $input['dice_mechanics'] : ( isset( $input['dice_mechanics_present'] ) ? array() : $previous['dice_mechanics'] );
		if ( ! is_array( $mechanics ) || count( $mechanics ) > 4 ) { return $error; }
		foreach ( $mechanics as $mechanic ) {
			if ( ! is_string( $mechanic ) || ! isset( ps_pen_paper_dice_mechanic_labels()[ $mechanic ] ) ) { return $error; }
		}
		$validated['dice_mechanics'] = array_values( array_unique( $mechanics ) );
	}
	$progression_mode = $input['progression_mode'] ?? ( ! empty( $previous['progression'] ) ? 'configured' : 'none' );
	if ( ! in_array( $progression_mode, array( 'none', 'configured' ), true ) ) { return $error; }
	if ( 'configured' === $progression_mode ) {
		$progression = ps_pen_paper_validate_progression( $input['progression'] ?? ( isset( $input['progression_mode'] ) ? null : ( $previous['progression'] ?? null ) ), $validated );
		if ( is_wp_error( $progression ) ) { return $progression; }
		$validated['progression'] = $progression;
	} elseif ( array_key_exists( 'progression', $previous ) || isset( $input['progression_mode'] ) ) {
		$validated['progression'] = null;
	}
	if ( 'level' === $allocation_mode ) {
		$fields = array_column( $validated['fields'], null, 'id' );
		if ( empty( $validated['progression'] ) || 1 !== $validated['progression']['thresholds'][0]['level'] ||
			$fields[ $validated['progression']['experience_field'] ]['start'] !== $fields[ $validated['progression']['experience_field'] ]['min'] ) {
			return new WP_Error( 'invalid_progression', __( 'Für Startpunkte und Punkte je Level brauchst du Stufenregeln, die bei Level 1 und dem Erfahrungsminimum beginnen.', 'ps-pen-paper' ) );
		}
	}
	if ( isset( $input['campaign_policy_present'] ) && '1' !== $input['campaign_policy_present'] ) { return $error; }
	if ( array_key_exists( 'campaign_policy', $input ) || isset( $input['campaign_policy_present'] ) || array_key_exists( 'campaign_policy', $previous ) ) {
		$policy = isset( $input['campaign_policy_present'] ) ? ( $input['campaign_policy'] ?? array() ) :
			( $input['campaign_policy'] ?? $previous['campaign_policy'] );
		$policy = ps_pen_paper_parse_inheritance_policy( $policy, $validated, isset( $input['campaign_policy_present'] ) );
		if ( is_wp_error( $policy ) ) { return $policy; }
		$validated['campaign_policy'] = $policy;
	}
	return $validated;
}

function ps_pen_paper_validate_progression( $input, $rules ) {
	$error = new WP_Error( 'invalid_progression', __( 'Wähle unterschiedliche Zahlen-/Attributfelder für Erfahrung und Stufe. Die Schwellen müssen aufsteigend sein, beim Minimum des Erfahrungsfeldes beginnen und innerhalb der Feldgrenzen liegen. Punkteverteilungsfelder sind nicht erlaubt.', 'ps-pen-paper' ) );
	if ( ! is_array( $input ) || count( $input ) !== 3 || ! isset( $input['experience_field'], $input['level_field'], $input['thresholds'] ) ||
		! is_string( $input['experience_field'] ) || ! is_string( $input['level_field'] ) || $input['experience_field'] === $input['level_field'] ) { return $error; }
	$fields = array_column( $rules['fields'], null, 'id' );
	foreach ( array( 'experience_field', 'level_field' ) as $key ) {
		if ( ! isset( $fields[ $input[ $key ] ] ) || ! in_array( $fields[ $input[ $key ] ]['type'], array( 'number', 'attribute' ), true ) ||
			in_array( $input[ $key ], $rules['allocation']['fields'] ?? array(), true ) ) { return $error; }
	}
	$thresholds = $input['thresholds'];
	if ( is_string( $thresholds ) ) {
		if ( strlen( $thresholds ) > 20000 ) { return $error; }
		$rows = preg_split( '/\r\n|\r|\n/', trim( $thresholds ) );
		$thresholds = array();
		foreach ( $rows as $row ) {
			if ( ! preg_match( '/^\s*(-?\d{1,7})\s*:\s*(-?\d{1,7})\s*$/D', $row, $match ) ) { return $error; }
			$thresholds[] = array( 'experience' => (int) $match[1], 'level' => (int) $match[2] );
		}
	}
	if ( ! is_array( $thresholds ) || ! $thresholds || array_values( $thresholds ) !== $thresholds || count( $thresholds ) > 1000 ) { return $error; }
	$experience = $fields[ $input['experience_field'] ];
	$level = $fields[ $input['level_field'] ];
	$last = null;
	foreach ( $thresholds as &$row ) {
		if ( is_array( $row ) ) {
			foreach ( array( 'experience', 'level' ) as $key ) {
				if ( isset( $row[ $key ] ) && is_string( $row[ $key ] ) && preg_match( '/^-?\d{1,7}$/D', $row[ $key ] ) ) { $row[ $key ] = (int) $row[ $key ]; }
			}
		}
		if ( ! is_array( $row ) || array( 'experience', 'level' ) !== array_keys( $row ) ||
			! is_int( $row['experience'] ) || ! is_int( $row['level'] ) ||
			$row['experience'] < $experience['min'] || $row['experience'] > $experience['max'] ||
			$row['level'] < $level['min'] || $row['level'] > $level['max'] ||
			( null === $last ? $row['experience'] !== $experience['min'] : ( $row['experience'] <= $last['experience'] || $row['level'] <= $last['level'] ) ) ) { return $error; }
		$last = $row;
	}
	unset( $row );
	return array( 'experience_field' => $input['experience_field'], 'level_field' => $input['level_field'], 'thresholds' => $thresholds );
}

function ps_pen_paper_apply_progression( $snapshot, $rules ) {
	if ( empty( $rules['progression'] ) ) { return $snapshot; }
	$progression = $rules['progression'];
	$experience = $snapshot['values'][ $progression['experience_field'] ];
	foreach ( $progression['thresholds'] as $row ) {
		if ( $experience < $row['experience'] ) { break; }
		$snapshot['values'][ $progression['level_field'] ] = $row['level'];
	}
	return $snapshot;
}

function ps_pen_paper_save_rules( $campaign_id, $input, $revision ) {
	return ps_pen_paper_with_inheritance_lock( $campaign_id, function () use ( $campaign_id, $input, $revision ) {
		return ps_pen_paper_scope_lock( $campaign_id, function () use ( $campaign_id, $input, $revision ) {
			return ps_pen_paper_save_rules_locked( $campaign_id, $input, $revision );
		} );
	} );
}

function ps_pen_paper_check_pending_item_uses_for_rules( $scope_id, $before, $after ) {
	if ( ! array_diff( $before['dice'], $after['dice'] ) ) { return true; }
	$characters = get_posts( array( 'post_type' => 'pspp_character', 'post_status' => array( 'private', 'draft', 'trash' ),
		'post_parent' => $scope_id, 'posts_per_page' => -1, 'fields' => 'ids' ) );
	foreach ( $characters as $character_id ) {
		$gear = ps_pen_paper_gear_state( $character_id );
		if ( is_wp_error( $gear ) ) { return $gear; }
		foreach ( $gear['uses']['pending'] ?? array() as $uuid => $request ) {
			if ( ! ps_pen_paper_validate_item_use_for_rules( $gear['active']['items'][ $uuid ]['item']['use_rule'], $after ) ) {
				return new WP_Error( 'equipment_conflict', __( 'Ein offener Gegenstandsnutzungsantrag passt nicht zu den neuen Regeln. Kläre ihn vor der Regeländerung.', 'ps-pen-paper' ) );
			}
		}
	}
	return true;
}

function ps_pen_paper_save_rules_locked( $campaign_id, $input, $revision ) {
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
	if ( 'pspp_campaign' === $campaign->post_type ) {
		$allowed = ps_pen_paper_validate_inherited_edit( $campaign_id, $rules );
		if ( is_wp_error( $allowed ) ) { return $allowed; }
	}
	$conditions = ps_pen_paper_check_condition_rules( $campaign, $rules );
	if ( is_wp_error( $conditions ) ) { return $conditions; }
	$existing_ids = array_column( $previous['fields'], 'id' );
	foreach ( $input['fields'] ?? array() as $field ) {
		if ( '' !== $field['id'] && ! in_array( $field['id'], $existing_ids, true ) ) {
			return new WP_Error( 'invalid_rules', __( 'Ein neues Feld benötigt eine leere Kennung. Bereits erzeugte Vorschaukennungen dürfen nur über die bestätigte Migration übernommen werden.', 'ps-pen-paper' ) );
		}
	}
	if ( ps_pen_paper_character_schema( $rules ) !== ps_pen_paper_character_schema( $previous ) ) {
		$characters = get_posts( array( 'post_type' => 'pspp_character', 'post_status' => array( 'private', 'draft', 'trash' ),
			'post_parent' => $campaign_id, 'posts_per_page' => 1, 'fields' => 'ids' ) );
		if ( $characters ) {
			return new WP_Error( 'schema_preview_required', __( 'Die verwendeten Charakterfelder haben sich geändert. Prüfe zuerst die Vorschau und löse alle angezeigten Datenkonflikte ausdrücklich.', 'ps-pen-paper' ) );
		}
	}
	$pending_check = ps_pen_paper_check_pending_item_uses_for_rules( $campaign_id, $previous, $rules );
	if ( is_wp_error( $pending_check ) ) { return $pending_check; }
	$saved = ps_pen_paper_save_document_event( $campaign_id, $campaign_id, '_pspp_rules', $previous, $rules, array(
		'kind' => 'rules_saved', 'visibility' => 'pspp_universe' === $campaign->post_type ? 'gm' : 'group', 'recipient_id' => 0,
		'rules_revision' => $rules['revision'], 'fields' => array(),
	) );
	if ( is_wp_error( $saved ) ) { return $saved; }
	if ( 'pspp_universe' === $campaign->post_type ) { ps_pen_paper_sync_universe_campaigns( (int) $campaign_id ); }
	return $rules;
}

function ps_pen_paper_character_allocation_valid( $snapshot, $rules ) {
		if ( empty( $rules['allocation'] ) ) { return true; }
		$total = 0;
		foreach ( $rules['allocation']['fields'] as $id ) {
			if ( ! isset( $snapshot['values'][ $id ] ) || ! is_int( $snapshot['values'][ $id ] ) ) { return false; }
			$total += $snapshot['values'][ $id ];
		}
		return isset( $rules['allocation']['per_level'] ) ? $total <= ps_pen_paper_character_point_budget( $snapshot, $rules ) : $total === $rules['allocation']['points'];
	}

function ps_pen_paper_character_point_budget( $snapshot, $rules ) {
	$points = $rules['allocation']['points'];
	if ( isset( $rules['allocation']['per_level'] ) ) {
		$snapshot = ps_pen_paper_apply_progression( $snapshot, $rules );
		$points += ( $snapshot['values'][ $rules['progression']['level_field'] ] - 1 ) * $rules['allocation']['per_level'];
	}
	return $points;
}

	function ps_pen_paper_character_allocation_defaults( $rules ) {
		$values = array();
		if ( empty( $rules['allocation'] ) ) { return $values; }
		$left = $rules['allocation']['points'];
		foreach ( $rules['allocation']['fields'] as $id ) {
			$field = null;
			foreach ( $rules['fields'] as $candidate ) { if ( $candidate['id'] === $id ) { $field = $candidate; break; } }
			if ( ! $field ) { return new WP_Error( 'invalid_allocation', __( 'Die Punkteverteilung passt nicht zum Regelwerk.', 'ps-pen-paper' ) ); }
			$values[ $id ] = $field['min'];
			$left -= $field['min'];
		}
		if ( isset( $rules['allocation']['per_level'] ) ) { return $values; }
		foreach ( $rules['allocation']['fields'] as $id ) {
			$field = null;
			foreach ( $rules['fields'] as $candidate ) { if ( $candidate['id'] === $id ) { $field = $candidate; break; } }
			$add = min( $left, $field['max'] - $values[ $id ] );
			$values[ $id ] += $add;
			$left -= $add;
		}
		return 0 === $left ? $values : new WP_Error( 'invalid_allocation', __( 'Die Punkteverteilung kann nicht auf die Feldgrenzen verteilt werden.', 'ps-pen-paper' ) );
	}

	function ps_pen_paper_valid_rules_document( $rules ) {
		if ( ! is_array( $rules ) || ! isset( $rules['revision'], $rules['name'], $rules['description'], $rules['dice'], $rules['fields'] ) ||
			! is_int( $rules['revision'] ) || $rules['revision'] < 1 || ! is_string( $rules['name'] ) || ! is_string( $rules['description'] ) ||
			! is_array( $rules['dice'] ) || ! is_array( $rules['fields'] ) ) { return false; }
		foreach ( $rules['fields'] as &$legacy_field ) {
			if ( is_array( $legacy_field ) && in_array( $legacy_field['type'] ?? '', array( 'text', 'textarea', 'choice' ), true ) && ! array_key_exists( 'start', $legacy_field ) ) {
				$legacy_field['start'] = '';
			}
		}
		unset( $legacy_field );
		$input = $rules;
		foreach ( $input['fields'] as &$field ) {
			if ( ! is_array( $field ) || ! isset( $field['id'], $field['label'], $field['type'], $field['description'], $field['required'] ) ||
				! is_bool( $field['required'] ) ) { return false; }
			$field['required'] = $field['required'] ? '1' : null;
			if ( null === $field['required'] ) { unset( $field['required'] ); }
			if ( in_array( $field['type'], array( 'number', 'attribute', 'resource' ), true ) ) {
				foreach ( array( 'min', 'max', 'start' ) as $key ) {
					if ( ! isset( $field[ $key ] ) || ! is_int( $field[ $key ] ) ) { return false; }
					$field[ $key ] = (string) $field[ $key ];
				}
			}
		}
		unset( $field );
		$previous = $rules;
		$previous['revision']--;
		$input['allocation_mode'] = ! empty( $rules['allocation'] ) ? ( isset( $rules['allocation']['per_level'] ) ? 'level' : 'configured' ) : 'none';
		if ( isset( $input['allocation']['points'] ) && is_int( $input['allocation']['points'] ) ) { $input['allocation']['points'] = (string) $input['allocation']['points']; }
		if ( isset( $input['allocation']['per_level'] ) && is_int( $input['allocation']['per_level'] ) ) { $input['allocation']['per_level'] = (string) $input['allocation']['per_level']; }
		$validated = ps_pen_paper_validate_rules( $input, $previous );
		if ( is_wp_error( $validated ) ) { return false; }
		foreach ( $rules as $key => $value ) {
			if ( ! array_key_exists( $key, $validated ) || $validated[ $key ] !== $value ) { return false; }
		}
		foreach ( $validated as $key => $value ) {
			if ( ! array_key_exists( $key, $rules ) && ! in_array( $key, array( 'allocation', 'character_mode', 'roll_visibilities' ), true ) ) { return false; }
		}
		return true;
	}

	function ps_pen_paper_valid_character_snapshot( $snapshot, $rules ) {
		if ( ! is_array( $snapshot ) || array( 'name', 'values' ) !== array_keys( $snapshot ) ||
			! is_string( $snapshot['name'] ) || ! is_array( $snapshot['values'] ) ) { return false; }
		$input = $snapshot;
		foreach ( $input['values'] as &$value ) {
			if ( is_int( $value ) ) { $value = (string) $value; }
			elseif ( is_array( $value ) ) {
				foreach ( $value as &$number ) { if ( is_int( $number ) ) { $number = (string) $number; } }
				unset( $number );
			}
		}
		unset( $value );
		$validated = ps_pen_paper_validate_character( $input, $rules );
		return ! is_wp_error( $validated ) && $validated === $snapshot;
	}

	function ps_pen_paper_apply_migration_conflicts( $snapshot, $conflicts ) {
		foreach ( $conflicts as $conflict ) {
			if ( 'allocation' === $conflict['reason'] ) {
				foreach ( $conflict['default'] as $field_id => $value ) { $snapshot['values'][ $field_id ] = $value; }
			} elseif ( 'removed' === $conflict['reason'] ) {
				unset( $snapshot['values'][ $conflict['field_id'] ] );
			} else {
				$snapshot['values'][ $conflict['field_id'] ] = $conflict['default'];
			}
		}
		return $snapshot;
	}

	function ps_pen_paper_migrate_character_snapshot( $snapshot, $old_rules, $new_rules, $character_id, $slot ) {
		$values = array();
		$conflicts = array();
		$old_fields = array_column( $old_rules['fields'], null, 'id' );
		$new_fields = array_column( $new_rules['fields'], null, 'id' );
		foreach ( $new_rules['fields'] as $field_index => $field ) {
			$id = $field['id'];
			$previous = isset( $old_fields[ $id ] ) ? $old_fields[ $id ] : null;
			$value = isset( $snapshot['values'][ $id ] ) ? $snapshot['values'][ $id ] : null;
			$compatible = $previous && $previous['type'] === $field['type'] && null !== $value;
			if ( $compatible ) {
				$single_snapshot = array( 'name' => $snapshot['name'], 'values' => array( $id => $value ) );
				$single_rules = array( 'fields' => array( $field ), 'allocation' => null );
				$compatible = ps_pen_paper_valid_character_snapshot( $single_snapshot, $single_rules );
			}
			if ( $compatible ) {
				$values[ $id ] = $value;
				continue;
			}
			$default = in_array( $field['type'], array( 'choice', 'text', 'textarea' ), true ) ? $field['start'] :
				( 'resource' === $field['type'] ? array( 'current' => $field['start'], 'maximum' => $field['max'] ) :
					( in_array( $field['type'], array( 'number', 'attribute' ), true ) ? $field['start'] : '' ) );
			$values[ $id ] = $default;
			$conflicts[] = array(
				'token' => $character_id . ':' . $slot . ':field:' . $field_index,
				'character_id' => (int) $character_id,
				'slot' => $slot,
				'field_id' => $id,
				'field_label' => $field['label'],
				'reason' => $previous ? 'incompatible' : 'added',
				'old_value' => $value,
				'default' => $default,
			);
		}
		foreach ( $old_rules['fields'] as $field ) {
			if ( ! isset( $new_fields[ $field['id'] ] ) && isset( $snapshot['values'][ $field['id'] ] ) ) {
				$conflicts[] = array(
					'token' => $character_id . ':' . $slot . ':removed:' . $field['id'],
					'character_id' => (int) $character_id,
					'slot' => $slot,
					'field_id' => $field['id'],
					'field_label' => $field['label'],
					'reason' => 'removed',
					'old_value' => $snapshot['values'][ $field['id'] ],
					'default' => null,
				);
			}
		}
		$migrated = array( 'name' => $snapshot['name'], 'values' => $values );
		if ( ! ps_pen_paper_character_allocation_valid( $migrated, $new_rules ) ) {
			$defaults = ps_pen_paper_character_allocation_defaults( $new_rules );
			if ( is_wp_error( $defaults ) ) { return $defaults; }
			$conflicts[] = array(
				'token' => $character_id . ':' . $slot . ':allocation',
				'character_id' => (int) $character_id,
				'slot' => $slot,
				'field_id' => 'allocation',
				'field_label' => __( 'Punkteverteilung', 'ps-pen-paper' ),
				'reason' => 'allocation',
				'old_value' => array_intersect_key( $snapshot['values'], array_fill_keys( $new_rules['allocation']['fields'], true ) ),
				'default' => $defaults,
			);
		}
		$progressed = ps_pen_paper_apply_progression( $migrated, $new_rules );
		if ( $progressed !== $migrated ) {
			$id = $new_rules['progression']['level_field'];
			$conflicts[] = array(
				'token' => $character_id . ':' . $slot . ':progression', 'character_id' => (int) $character_id,
				'slot' => $slot, 'field_id' => $id, 'field_label' => $new_fields[ $id ]['label'],
				'reason' => 'progression', 'old_value' => $migrated['values'][ $id ], 'default' => $progressed['values'][ $id ],
			);
		}
		return array( 'snapshot' => $migrated, 'conflicts' => $conflicts );
	}

	function ps_pen_paper_check_character_gear_for_rules( $scope, $character, $snapshot, $pending_character_snapshot, $rules ) {
		if ( ! in_array( $scope->post_type, array( 'pspp_campaign', 'pspp_universe' ), true ) ) { return true; }
		$gear = ps_pen_paper_gear_state( $character->ID );
		if ( is_wp_error( $gear ) ) { return $gear; }
		$character_state = ps_pen_paper_raw_character_state( $character->ID );
		if ( is_wp_error( $character_state ) ) { return $character_state; }
		if ( isset( $character_state['context'] ) ) {
			if ( null !== $snapshot ) { $snapshot = ps_pen_paper_apply_context_values( $snapshot, $character_state['context'] ); }
			if ( is_wp_error( $snapshot ) ) { return $snapshot; }
			if ( null !== $pending_character_snapshot ) { $pending_character_snapshot = ps_pen_paper_apply_context_values( $pending_character_snapshot, $character_state['context'] ); }
			if ( is_wp_error( $pending_character_snapshot ) ) { return $pending_character_snapshot; }
		}
		$fields = array_column( $rules['fields'], null, 'id' );
		$effect_fields = ps_pen_paper_item_effect_fields( $rules );
		$effects = array();
		$resource_effects = array();
		foreach ( $gear['uses']['pending'] ?? array() as $uuid => $request ) {
			if ( ! ps_pen_paper_validate_item_use_for_rules( $gear['active']['items'][ $uuid ]['item']['use_rule'], $rules ) ) {
				return new WP_Error( 'equipment_conflict', __( 'Ein offener Gegenstandsnutzungsantrag passt nicht zu den neuen Regeln. Kläre ihn vor der Regeländerung.', 'ps-pen-paper' ) );
			}
		}
		foreach ( $gear['uses']['effects'] ?? array() as $effect ) {
			if ( ! $effect['temporary'] || $effect['ended'] ) { continue; }
			foreach ( $effect['deltas'] as $field_id => $delta ) {
				if ( ! isset( $effect_fields[ $field_id ] ) || $effect_fields[ $field_id ]['type'] !== $effect['types'][ $field_id ] ) {
					return new WP_Error( 'equipment_conflict', __( 'Eine aktive Gegenstandswirkung verweist auf ein entferntes oder inkompatibles Feld. Beende sie vor der Regeländerung.', 'ps-pen-paper' ) );
				}
				if ( 'resource' !== $effect_fields[ $field_id ]['type'] ) {
					$effects[ $field_id ] = ( $effects[ $field_id ] ?? 0 ) + $delta;
				} else {
					$resource_effects[] = array( 'field' => $field_id, 'delta' => $delta );
				}
			}
		}
		$check = function ( $gear_snapshot, $character_snapshot ) use ( $fields, $rules, $effects, $resource_effects ) {
			if ( ! $gear_snapshot || ! $character_snapshot ) { return true; }
			$bonuses = array();
			foreach ( $gear_snapshot['slots'] as $instance_id ) {
				if ( ! isset( $gear_snapshot['items'][ $instance_id ]['item']['bonuses'] ) ) {
					return new WP_Error( 'equipment_conflict', __( 'Die angelegte Ausrüstung ist ungültig. Die Feldstruktur wurde nicht geändert.', 'ps-pen-paper' ) );
				}
				foreach ( $gear_snapshot['items'][ $instance_id ]['item']['bonuses'] as $field_id => $bonus ) {
					if ( ! isset( $fields[ $field_id ] ) || ! in_array( $fields[ $field_id ]['type'], array( 'attribute', 'number' ), true ) || ! is_int( $bonus ) ) {
						return new WP_Error( 'equipment_conflict', __( 'Ein Ausrüstungsbonus verweist auf ein entferntes oder inkompatibles Feld. Passe die Ausrüstung zuerst an.', 'ps-pen-paper' ) );
					}
					if ( ! empty( $rules['progression'] ) && in_array( $field_id, array( $rules['progression']['experience_field'], $rules['progression']['level_field'] ), true ) ) {
						return new WP_Error( 'progression_bonus', __( 'Lege Ausrüstung mit Erfahrungs- oder Stufenboni vor dem Aktivieren der Stufenregeln ab.', 'ps-pen-paper' ) );
					}
					$bonuses[ $field_id ] = ( isset( $bonuses[ $field_id ] ) ? $bonuses[ $field_id ] : 0 ) + $bonus;
				}
			}
			foreach ( $bonuses as $field_id => $bonus ) {
				if ( ! isset( $character_snapshot['values'][ $field_id ] ) || ! is_int( $character_snapshot['values'][ $field_id ] ) ||
					$character_snapshot['values'][ $field_id ] + $bonus < $fields[ $field_id ]['min'] ||
					$character_snapshot['values'][ $field_id ] + $bonus > $fields[ $field_id ]['max'] ) {
					return new WP_Error( 'equipment_conflict', __( 'Die angelegte Ausrüstung würde den neuen Wertebereich verletzen. Passe zuerst Ausrüstung oder Feldgrenzen an.', 'ps-pen-paper' ) );
				}
			}
			foreach ( $effects as $field_id => $delta ) {
				if ( ! isset( $character_snapshot['values'][ $field_id ] ) || ! is_int( $character_snapshot['values'][ $field_id ] ) ||
					$character_snapshot['values'][ $field_id ] + ( $bonuses[ $field_id ] ?? 0 ) + $delta < $fields[ $field_id ]['min'] ||
					$character_snapshot['values'][ $field_id ] + ( $bonuses[ $field_id ] ?? 0 ) + $delta > $fields[ $field_id ]['max'] ) {
					return new WP_Error( 'equipment_conflict', __( 'Eine aktive Gegenstandswirkung würde den neuen Wertebereich verletzen. Beende sie oder passe die Feldgrenzen an.', 'ps-pen-paper' ) );
				}
			}
			foreach ( $resource_effects as $effect ) {
				$field_id = $effect['field'];
				$value = $character_snapshot['values'][ $field_id ] ?? null;
				if ( ! is_array( $value ) || ! isset( $value['current'], $value['maximum'] ) ||
					! is_int( $value['current'] ) || ! is_int( $value['maximum'] ) ||
					$value['current'] - $effect['delta'] < $fields[ $field_id ]['min'] ||
					$value['current'] - $effect['delta'] > min( $fields[ $field_id ]['max'], $value['maximum'] ) ) {
					return new WP_Error( 'equipment_conflict', __( 'Eine aktive Ressourcenwirkung könnte unter den neuen Grenzen nicht beendet werden. Kläre sie vor der Regeländerung.', 'ps-pen-paper' ) );
				}
			}
			return true;
		};
		$active_check = $check( $gear['active'], $snapshot );
		if ( is_wp_error( $active_check ) ) { return $active_check; }
		if ( $pending_character_snapshot ) {
			$pending_check = $check( $gear['active'], $pending_character_snapshot );
			if ( is_wp_error( $pending_check ) ) { return $pending_check; }
		}
		if ( $gear['pending'] ) {
			foreach ( array( $snapshot, $pending_character_snapshot ) as $candidate ) {
				$pending_check = $check( $gear['pending'], $candidate );
				if ( is_wp_error( $pending_check ) ) { return $pending_check; }
			}
		}
		return true;
	}

	function ps_pen_paper_preview_rules_migration( $scope_id, $input, $revision, $inherited = false ) {
		$scope = ps_pen_paper_get_character_scope( $scope_id );
		if ( ! current_user_can( 'read' ) || ! $scope || ! ps_pen_paper_can_manage_character_scope( $scope ) ) {
			return new WP_Error( 'forbidden', __( 'Du darfst dieses Regelwerk nicht bearbeiten.', 'ps-pen-paper' ) );
		}
		$before = ps_pen_paper_get_rules( $scope_id );
		if ( ! is_string( $revision ) || (string) $before['revision'] !== $revision ) {
			return new WP_Error( 'conflict', __( 'Das Regelwerk wurde inzwischen geändert. Lade die Seite neu.', 'ps-pen-paper' ) );
		}
		$source = null;
		if ( $inherited ) {
			if ( 'pspp_campaign' !== $scope->post_type ) { return new WP_Error( 'invalid_inheritance', __( 'Für diese Vorlage ist keine Universumsübernahme möglich.', 'ps-pen-paper' ) ); }
			$source = ps_pen_paper_inheritance_source( $scope_id );
			if ( is_wp_error( $source ) ) { return $source; }
			if ( ! $source || $source['link']['rules_base'] === $source['rules'] ) { return new WP_Error( 'no_migration', __( 'Es gibt keine ausstehende Universumsänderung.', 'ps-pen-paper' ) ); }
			$area_check = ps_pen_paper_inheritance_area_conflict( $scope_id, $source['rules'] );
			if ( is_wp_error( $area_check ) ) { return $area_check; }
			$merged = ps_pen_paper_rebase_inherited_rules( $source['link']['rules_base'], $before, $source['rules'] );
			if ( is_wp_error( $merged ) ) { return $merged; }
			$input = ps_pen_paper_inheritance_rules_input( $merged );
		}
		$after = ps_pen_paper_validate_rules( $input, $before );
		if ( is_wp_error( $after ) ) { return $after; }
		$conditions = ps_pen_paper_check_condition_rules( $scope, $after );
		if ( is_wp_error( $conditions ) ) { return $conditions; }
		if ( 'pspp_campaign' === $scope->post_type ) {
			$allowed = $inherited ? ps_pen_paper_validate_inherited_rules( $after, $source['rules'] ) : ps_pen_paper_validate_inherited_edit( $scope_id, $after );
			if ( is_wp_error( $allowed ) ) { return $allowed; }
		}
		if ( $inherited ) {
			$content_check = ps_pen_paper_inheritance_content_conflict( $scope_id, $before, $after );
			if ( is_wp_error( $content_check ) ) { return $content_check; }
		}
		if ( ps_pen_paper_character_schema( $before ) === ps_pen_paper_character_schema( $after ) ) {
			return new WP_Error( 'no_migration', __( 'Die Feldstruktur hat sich nicht geändert; speichere das Regelwerk wie gewohnt.', 'ps-pen-paper' ) );
		}
		if ( 'pspp_campaign' === $scope->post_type && function_exists( 'ps_pen_paper_has_shared_characters' ) && ps_pen_paper_has_shared_characters( $scope_id ) ) {
			return new WP_Error( 'shared_schema', __( 'Gemeinsame Universumsbögen müssen dieselbe Feldstruktur wie ihre Basis behalten. Führe sie zuerst ausdrücklich als Kopien weiter.', 'ps-pen-paper' ) );
		}
		if ( 'pspp_universe' === $scope->post_type && ps_pen_paper_has_shared_base_references( $scope_id ) ) {
			return new WP_Error( 'shared_schema', __( 'Diese Basisvorlage wird von gemeinsamen Kampagnenbögen oder offenen Anfragen verwendet. Führe die Bögen zuerst ausdrücklich als Kopien weiter und kläre offene Anfragen; ihre Feldstruktur darf nicht unbemerkt entkoppelt werden.', 'ps-pen-paper' ) );
		}
		$characters = get_posts( array( 'post_type' => 'pspp_character', 'post_status' => array( 'private', 'draft', 'trash' ), 'post_parent' => $scope_id, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
		$rows = array();
		$character_states = array();
		foreach ( $characters as $character ) {
			$state = ps_pen_paper_raw_character_state( $character->ID );
			if ( is_wp_error( $state ) ) { return $state; }
			$character_states[ (int) $character->ID ] = $state;
			if ( isset( $state['context'] ) ) {
				$context = ps_pen_paper_validate_context( $state['context'], $after );
				if ( is_wp_error( $context ) ) {
					return new WP_Error( 'context_migration_conflict', __( 'Eine Kampagnenauflage passt nicht mehr zur neuen Feldstruktur. Bereinige sie ausdrücklich und erstelle eine neue Vorschau.', 'ps-pen-paper' ) );
				}
			}
			foreach ( array( 'active', 'pending' ) as $slot ) {
				if ( null === $state[ $slot ] ) { continue; }
				$migrated = ps_pen_paper_migrate_character_snapshot( $state[ $slot ], $before, $after, $character->ID, $slot );
				if ( is_wp_error( $migrated ) ) { return $migrated; }
				$rows[] = array(
					'character_id' => (int) $character->ID,
					'owner_id' => (int) $character->post_author,
					'slot' => $slot,
					'before' => $state[ $slot ],
					'after' => $migrated['snapshot'],
					'conflicts' => $migrated['conflicts'],
				);
			}
			$planned = $state;
			foreach ( $rows as $row ) {
				if ( $row['character_id'] !== (int) $character->ID ) { continue; }
				$planned[ $row['slot'] ] = ps_pen_paper_apply_migration_conflicts( $row['after'], $row['conflicts'] );
			}
			$gear_check = ps_pen_paper_check_character_gear_for_rules( $scope, $character, $planned['active'], $planned['pending'], $after );
			if ( is_wp_error( $gear_check ) ) { return $gear_check; }
		}
		$preview = array( 'scope_id' => (int) $scope_id, 'before' => $before, 'after' => $after, 'characters' => $rows, 'character_states' => $character_states );
		if ( $inherited ) { $preview['inheritance'] = $source; }
		$preview['hash'] = hash( 'sha256', wp_json_encode( $preview ) );
		return $preview;
	}

	function ps_pen_paper_save_rules_migration( $scope_id, $input, $revision, $resolutions, $confirmation, $preview_hash = null, $inherited = false ) {
		return ps_pen_paper_with_inheritance_lock( $scope_id, function () use ( $scope_id, $input, $revision, $resolutions, $confirmation, $preview_hash, $inherited ) {
			return ps_pen_paper_save_rules_migration_locked( $scope_id, $input, $revision, $resolutions, $confirmation, $preview_hash, $inherited );
		} );
	}

	function ps_pen_paper_save_rules_migration_locked( $scope_id, $input, $revision, $resolutions, $confirmation, $preview_hash = null, $inherited = false ) {
		$preview = ps_pen_paper_preview_rules_migration( $scope_id, $input, $revision, $inherited );
		if ( is_wp_error( $preview ) ) { return $preview; }
		if ( 'confirmed' !== $confirmation ) { return new WP_Error( 'confirmation_required', __( 'Bestätige die angezeigte Vorschau und alle Konfliktlösungen ausdrücklich.', 'ps-pen-paper' ) ); }
		if ( ! is_string( $preview_hash ) || ! hash_equals( $preview['hash'], $preview_hash ) ) {
			return new WP_Error( 'conflict', __( 'Die bestätigte Vorschau ist nicht mehr aktuell. Bitte prüfe die Charakterstände und Konflikte erneut.', 'ps-pen-paper' ) );
		}
		if ( ! is_array( $resolutions ) ) { return new WP_Error( 'resolution_required', __( 'Löse jeden angezeigten Feldkonflikt ausdrücklich.', 'ps-pen-paper' ) ); }
		$known = array();
		foreach ( $preview['characters'] as $row ) {
			foreach ( $row['conflicts'] as $conflict ) { $known[ $conflict['token'] ] = $conflict; }
		}
		if ( array_diff( array_keys( $known ), array_keys( $resolutions ) ) || array_diff( array_keys( $resolutions ), array_keys( $known ) ) ) {
			return new WP_Error( 'resolution_required', __( 'Löse jeden angezeigten Feldkonflikt ausdrücklich. Nicht bestätigte oder zusätzliche Entscheidungen werden abgelehnt.', 'ps-pen-paper' ) );
		}
		foreach ( $resolutions as $choice ) {
			if ( 'use_default' !== $choice ) { return new WP_Error( 'invalid_resolution', __( 'Ungültige Konfliktlösung.', 'ps-pen-paper' ) ); }
		}
		$ids = array_keys( $preview['character_states'] );
		sort( $ids, SORT_NUMERIC );
		$scope = ps_pen_paper_get_character_scope( $scope_id );
		$result = ps_pen_paper_with_character_locks( $ids, function () use ( $preview, $scope_id, $scope ) {
			return ps_pen_paper_atomic_event( $scope_id, function () use ( $preview, $scope_id, $scope ) {
				global $wpdb;
				if ( ps_pen_paper_get_rules( $scope_id ) !== $preview['before'] ) {
					return new WP_Error( 'conflict', __( 'Das Regelwerk wurde inzwischen geändert. Bitte erstelle eine neue Vorschau.', 'ps-pen-paper' ) );
				}
				$conditions = ps_pen_paper_check_condition_rules( $scope, $preview['after'] );
				if ( is_wp_error( $conditions ) ) { return $conditions; }
				if ( isset( $preview['inheritance'] ) ) {
					$source = ps_pen_paper_inheritance_source( $scope_id );
					if ( is_wp_error( $source ) ) { return $source; }
					if ( $source !== $preview['inheritance'] ) { return new WP_Error( 'conflict', __( 'Das Universumsregelwerk wurde inzwischen geändert. Bitte erstelle eine neue Vorschau.', 'ps-pen-paper' ) ); }
					$area_check = ps_pen_paper_inheritance_area_conflict( $scope_id, $source['rules'] );
					if ( is_wp_error( $area_check ) ) { return $area_check; }
					$content_check = ps_pen_paper_inheritance_content_conflict( $scope_id, $preview['before'], $preview['after'] );
					if ( is_wp_error( $content_check ) ) { return $content_check; }
				}
				if ( ( 'pspp_universe' === $scope->post_type && ps_pen_paper_has_shared_base_references( $scope_id ) ) ||
					( 'pspp_campaign' === $scope->post_type && ps_pen_paper_has_shared_characters( $scope_id ) ) ) {
					return new WP_Error( 'shared_schema', __( 'Die Vorlage wird inzwischen von einem gemeinsamen Bogen oder Antrag verwendet. Bitte kläre die Verbindung und erstelle eine neue Vorschau.', 'ps-pen-paper' ) );
				}
				$current_ids = get_posts( array( 'post_type' => 'pspp_character', 'post_status' => array( 'private', 'draft', 'trash' ), 'post_parent' => $scope_id, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids' ) );
				$expected_ids = array_keys( $preview['character_states'] );
				$current_ids = array_map( 'intval', $current_ids );
				sort( $current_ids, SORT_NUMERIC );
				sort( $expected_ids, SORT_NUMERIC );
				if ( $current_ids !== $expected_ids ) {
					return new WP_Error( 'conflict', __( 'Die Charakterliste wurde inzwischen geändert. Bitte erstelle eine neue Vorschau.', 'ps-pen-paper' ) );
				}
				$rows = array();
				foreach ( $preview['characters'] as $row ) { $rows[ $row['character_id'] ][ $row['slot'] ] = $row; }
				foreach ( $preview['character_states'] as $id => $expected_state ) {
					if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = %d FOR UPDATE", $id ) ) !== (int) $id ) {
						return new WP_Error( 'conflict', __( 'Ein Charakter wurde inzwischen geändert oder entfernt. Bitte erstelle eine neue Vorschau.', 'ps-pen-paper' ) );
					}
					clean_post_cache( $id );
					$before = ps_pen_paper_raw_character_state( $id );
					if ( is_wp_error( $before ) ) { return $before; }
					if ( $before !== $expected_state ) {
						return new WP_Error( 'conflict', __( 'Ein Charakterstand oder dessen offener Antrag wurde inzwischen geändert. Bitte erstelle eine neue Vorschau.', 'ps-pen-paper' ) );
					}
					if ( ! isset( $rows[ $id ] ) ) { continue; }
					$slots = $rows[ $id ];
					$after = $before;
					$after['revision']++;
					foreach ( $slots as $slot => $row ) {
						if ( $before[ $slot ] !== $row['before'] ) { return new WP_Error( 'conflict', __( 'Ein aktiver oder offener Charakterstand wurde inzwischen geändert. Bitte erstelle eine neue Vorschau.', 'ps-pen-paper' ) ); }
						$after[ $slot ] = ps_pen_paper_apply_migration_conflicts( $row['after'], $row['conflicts'] );
						if ( ! ps_pen_paper_valid_character_snapshot( $after[ $slot ], $preview['after'] ) ||
							! ps_pen_paper_character_allocation_valid( $after[ $slot ], $preview['after'] ) ) {
							return new WP_Error( 'migration_conflict', __( 'Die gewählte Feldauflösung ergibt keinen gültigen Charakterstand. Es wurde nichts übernommen.', 'ps-pen-paper' ) );
						}
					}
					$character = get_post( $id );
					$gear_check = ps_pen_paper_check_character_gear_for_rules( $scope, $character, $after['active'], $after['pending'], $preview['after'] );
					if ( is_wp_error( $gear_check ) ) { return $gear_check; }
					$saved = ps_pen_paper_write_document_event( $scope_id, $id, '_pspp_character', $before, $after, array(
						'kind' => 'character_saved', 'visibility' => 'owner', 'recipient_id' => (int) $character->post_author,
						'rules_revision' => $preview['after']['revision'], 'fields' => $preview['after']['fields'],
						'migration_rules_before' => $preview['before'], 'migration_rules_after' => $preview['after'],
					), false );
					if ( is_wp_error( $saved ) ) { return $saved; }
				}
				$saved = ps_pen_paper_write_document_event( $scope_id, $scope_id, '_pspp_rules', $preview['before'], $preview['after'], array(
					'kind' => 'rules_saved', 'visibility' => 'pspp_universe' === $scope->post_type ? 'gm' : 'group', 'recipient_id' => 0,
					'rules_revision' => $preview['after']['revision'], 'fields' => array(),
				), false );
				if ( is_wp_error( $saved ) || ! isset( $preview['inheritance'] ) ) { return $saved; }
				$link = $preview['inheritance']['link'];
				$updated = $link; $updated['revision']++; $updated['rules_base'] = $preview['inheritance']['rules'];
				return ps_pen_paper_write_document_event( $scope_id, $scope_id, '_pspp_universe_link', $link, $updated, array(
					'kind' => 'universe_linked', 'visibility' => 'gm', 'recipient_id' => 0, 'fields' => array(), 'rules_revision' => 0,
				), false );
			} );
	} );
	if ( true === $result && 'pspp_universe' === $scope->post_type ) { ps_pen_paper_sync_universe_campaigns( (int) $scope_id ); }
	return $result;
}
