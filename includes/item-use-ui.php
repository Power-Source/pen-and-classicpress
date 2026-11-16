<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_render_outside_choice( $name, $value ) {
	echo '<label>' . esc_html__( 'Außerhalb des Spiels / Community-Profil', 'ps-pen-paper' ) . '<select name="' . esc_attr( $name ) . '">';
	foreach ( array( 'inherit' => __( 'Übergeordnete Regel', 'ps-pen-paper' ), 'allow' => __( 'Erlauben', 'ps-pen-paper' ), 'deny' => __( 'Verbieten (hat Vorrang)', 'ps-pen-paper' ), 'approval' => __( 'Nur mit SL-Freigabe', 'ps-pen-paper' ) ) as $key => $label ) {
		echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></label>';
}

function ps_pen_paper_render_item_action_policy( $scope, $page_id ) {
	if ( ! ps_pen_paper_can_manage_character_scope( $scope ) ) { return; }
	$policy = ps_pen_paper_item_action_policy( $scope );
	if ( is_wp_error( $policy ) ) { echo '<p role="alert">' . esc_html( $policy->get_error_message() ) . '</p>'; return; }
	echo '<details class="pspp-fold"><summary>' . esc_html__( 'Ausrüstung und Verbrauch außerhalb des Spiels', 'ps-pen-paper' ) . '</summary>';
	ps_pen_paper_equipment_form_start( $scope, 'save_item_action_policy', $policy['revision'], $page_id );
	foreach ( array( 'enabled' => __( 'Profilaktionen ausdrücklich erlauben', 'ps-pen-paper' ), 'during_active' => __( 'Profilaktionen auch während einer gestarteten Spielsitzung erlauben', 'ps-pen-paper' ) ) as $key => $label ) {
		echo '<label class="pspp-check"><input type="checkbox" name="equipment[' . esc_attr( $key ) . ']" value="yes" ' . checked( $policy[ $key ], true, false ) . '>' . esc_html( $label ) . '</label>';
	}
	foreach ( array( 'equip' => __( 'Ausrüstung wechseln', 'ps-pen-paper' ), 'use' => __( 'Gegenstände verbrauchen (auch Heilung / Ressourcen)', 'ps-pen-paper' ) ) as $key => $label ) {
		echo '<label>' . esc_html( $label ) . '<select name="equipment[' . esc_attr( $key ) . ']">';
		foreach ( array( 'allow' => __( 'Sofort erlauben', 'ps-pen-paper' ), 'deny' => __( 'Verbieten', 'ps-pen-paper' ), 'approval' => __( 'SL-Freigabe', 'ps-pen-paper' ) ) as $value => $title ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $policy[ $key ], $value, false ) . '>' . esc_html( $title ) . '</option>';
		}
		echo '</select></label>';
	}
	echo '<p>' . esc_html__( 'Verbote der Kampagne, eines Slots oder Gegenstands haben Vorrang. Profilverbrauch verlangt nie Würfel oder Aussetzen einer Spielrunde; Bestände, Wirkungen und Protokoll bleiben dieselben. Ohne ausdrückliche Erlaubnis sind Profilaktionen gesperrt.', 'ps-pen-paper' ) . '</p><button>' . esc_html__( 'Profilregeln speichern', 'ps-pen-paper' ) . '</button></form></details>';
}

function ps_pen_paper_render_item_use_rule_form( $scope, $item ) {
	$rule = $item['use_rule'] ?? array( 'condition' => 'equipment', 'expression' => '', 'attribute' => '', 'threshold' => 0, 'failure' => 'keep', 'effects' => array(), 'temporary' => false, 'rounds' => 0 );
	$fields = ps_pen_paper_item_effect_fields( ps_pen_paper_get_rules( $scope->ID ) );
	echo '<fieldset><legend>' . esc_html__( 'Ausrüstung oder konfigurierbarer Verbrauch', 'ps-pen-paper' ) . '</legend><label>' . esc_html__( 'Voraussetzung im Spiel', 'ps-pen-paper' ) . '<select name="equipment[use][condition]">';
	foreach ( array( 'equipment' => __( 'Slotgebundene Ausrüstung (kein Verbrauch)', 'ps-pen-paper' ), 'immediate' => __( 'Sofort verbrauchen', 'ps-pen-paper' ), 'approval' => __( 'Erst nach SL-Freigabe', 'ps-pen-paper' ), 'test' => __( 'Probe: Summe ≥ ausdrücklicher Zielwert', 'ps-pen-paper' ), 'round' => __( 'Eine echte Spielrunde aussetzen', 'ps-pen-paper' ) ) as $key => $label ) {
		echo '<option value="' . esc_attr( $key ) . '" ' . selected( $rule['condition'], $key, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></label><label>' . esc_html__( 'Würfelausdruck für Probe (optional, z. B. W6)', 'ps-pen-paper' ) . '<input name="equipment[use][expression]" maxlength="200" value="' . esc_attr( $rule['expression'] ) . '"></label><label>' . esc_html__( 'Additiver Attributwert für Probe (optional)', 'ps-pen-paper' ) . '<select name="equipment[use][attribute]"><option value="">' . esc_html__( 'Ohne Attribut', 'ps-pen-paper' ) . '</option>';
	foreach ( $fields as $id => $field ) {
		if ( 'resource' !== $field['type'] ) { echo '<option value="' . esc_attr( $id ) . '" ' . selected( $rule['attribute'], $id, false ) . '>' . esc_html( $field['label'] ) . '</option>'; }
	}
	echo '</select></label><label>' . esc_html__( 'Zielwert (Probe besteht ab diesem Wert)', 'ps-pen-paper' ) . '<input type="number" name="equipment[use][threshold]" min="-1000000" max="1000000" value="' . esc_attr( $rule['threshold'] ) . '"></label><label>' . esc_html__( 'Fehlgeschlagene Probe', 'ps-pen-paper' ) . '<select name="equipment[use][failure]">';
	foreach ( array( 'keep' => __( 'Kein Verbrauch, keine Wirkung', 'ps-pen-paper' ), 'consume' => __( 'Verbrauchen, aber keine Wirkung', 'ps-pen-paper' ) ) as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '" ' . selected( $rule['failure'], $key, false ) . '>' . esc_html( $label ) . '</option>'; }
	echo '</select></label><label>' . esc_html__( 'Wirkungsdauer', 'ps-pen-paper' ) . '<select name="equipment[use][temporary]"><option value="permanent" ' . selected( $rule['temporary'], false, false ) . '>' . esc_html__( 'Dauerhafte native Werteänderung', 'ps-pen-paper' ) . '</option><option value="temporary" ' . selected( $rule['temporary'], true, false ) . '>' . esc_html__( 'Temporärer additiver Effekt (SL beendet)', 'ps-pen-paper' ) . '</option></select></label><label>' . esc_html__( 'Zusätzliche automatische Dauer in Spielrunden (0 = nur SL-Ende)', 'ps-pen-paper' ) . '<input type="number" name="equipment[use][rounds]" min="0" max="1000" value="' . esc_attr( $rule['rounds'] ) . '"></label>';
	foreach ( $fields as $id => $field ) {
		echo '<label>' . esc_html( $field['label'] . ( 'resource' === $field['type'] ? ' (aktueller Ressourcenwert)' : '' ) ) . '<input type="number" name="equipment[use][effects][' . esc_attr( $id ) . ']" min="-1000000" max="1000000" step="1" value="' . esc_attr( $rule['effects'][ $id ] ?? 0 ) . '"></label>';
	}
	echo '<p>' . esc_html__( 'Nur native Zahlen, Attribute und aktuelle Ressourcenwerte; keine Skripte, keine erfundenen Geldgegenstände. Währungen bleiben im Guthaben. Feldgrenzen, Maximum und Fortschrittsregeln gelten unverändert. Bei einer Probe ist pro Exemplar nur ein Versuch erlaubt; Fehlschlag und Ablehnung werden protokolliert und sind nicht wiederholbar.', 'ps-pen-paper' ) . '</p></fieldset>';
}

function ps_pen_paper_render_item_use_summary( $campaign, $rule, $context = 'game', $permission = 'allow' ) {
	$labels = array( 'immediate' => __( 'Sofort', 'ps-pen-paper' ), 'approval' => __( 'SL-Freigabe', 'ps-pen-paper' ), 'test' => __( 'Probe', 'ps-pen-paper' ), 'round' => __( 'Eine Spielrunde aussetzen', 'ps-pen-paper' ) );
	$fields = ps_pen_paper_item_effect_fields( ps_pen_paper_get_rules( $campaign->ID ) );
	$condition = 'profile' === $context ? ( 'approval' === $permission ? 'approval' : 'immediate' ) : $rule['condition'];
	echo '<p>' . esc_html( __( 'Voraussetzung:', 'ps-pen-paper' ) . ' ' . $labels[ $condition ] ) . '</p>';
	if ( 'test' === $condition ) { echo '<p>' . esc_html( ( $rule['expression'] ?: '0' ) . ' + ' . ( $rule['attribute'] ? ( $fields[ $rule['attribute'] ]['label'] ?? $rule['attribute'] ) : '0' ) . ' ≥ ' . $rule['threshold'] . ' · ' . ( 'keep' === $rule['failure'] ? __( 'Bei Fehlschlag: kein Verbrauch / keine Wirkung.', 'ps-pen-paper' ) : __( 'Bei Fehlschlag: Verbrauch / keine Wirkung.', 'ps-pen-paper' ) ) ) . '</p>'; }
	echo '<ul>';
	foreach ( $rule['effects'] as $id => $delta ) { echo '<li>' . esc_html( ( $fields[ $id ]['label'] ?? $id ) . ': ' . ( $delta > 0 ? '+' : '' ) . $delta ) . '</li>'; }
	echo '</ul><p>' . esc_html( $rule['temporary'] ? __( 'Temporär; SL beendet ausdrücklich.', 'ps-pen-paper' ) . ( 'pspp_campaign' === $campaign->post_type && $rule['rounds'] ? ' ' . $rule['rounds'] . ' ' . __( 'Spielrunden.', 'ps-pen-paper' ) : '' ) : __( 'Dauerhafte Werteänderung.', 'ps-pen-paper' ) ) . '</p><p>' . esc_html__( 'Bei Erfolg wird genau dieses Exemplar verbraucht. Werte außerhalb erlaubter Grenzen verhindern den gesamten Vorgang. Effekte enden additiv, ohne spätere Änderungen zurückzusetzen; ein unzulässiges Ende blockiert die Aktion bis zur SL-Korrektur.', 'ps-pen-paper' ) . '</p>';
	if ( 'pspp_universe' === $campaign->post_type ) { echo '<p>' . esc_html__( 'Basisinventar ist unabhängig von Kampagnenausrüstung. Temporäre Basiseffekte enden ausdrücklich durch die Universumsleitung; Spielrundendauer gilt nur für Kampagnenexemplare.', 'ps-pen-paper' ) . '</p>'; }
}

function ps_pen_paper_item_use_form_start( $campaign, $character, $operation, $gear, $page_id, $uuid ) {
	$state = ps_pen_paper_character_state( $campaign, $character, false );
	$round = ps_pen_paper_game_round( $campaign->ID );
	$policy = ps_pen_paper_item_action_policy( $campaign );
	if ( is_wp_error( $state ) || is_wp_error( $round ) || is_wp_error( $policy ) ) { echo '<p role="alert">' . esc_html__( 'Der aktuelle Charakter- oder Regelstand ist nicht verfügbar.', 'ps-pen-paper' ) . '</p>'; return false; }
	ps_pen_paper_equipment_form_start( $campaign, $operation, $gear['revision'], $page_id, $character->ID );
	echo '<input type="hidden" name="equipment[instance_id]" value="' . esc_attr( $uuid ) . '"><input type="hidden" name="equipment[character_revision]" value="' . esc_attr( ps_pen_paper_item_use_character_revision( $state ) ) . '"><input type="hidden" name="equipment[rules_revision]" value="' . esc_attr( ps_pen_paper_get_rules( $campaign->ID )['revision'] ) . '">';
	echo '<input type="hidden" name="equipment[round_revision]" value="' . esc_attr( $round['revision'] ) . '"><input type="hidden" name="equipment[policy_revision]" value="' . esc_attr( $policy['revision'] ) . '">';
	return true;
}

function ps_pen_paper_render_item_use_actions( $campaign, $character, $uuid, $gear, $page_id, $context = 'game' ) {
	$item = $gear['active']['items'][ $uuid ]['item'];
	if ( ! isset( $item['use_rule'] ) ) { return; }
	$uses = $gear['uses'] ?? ps_pen_paper_empty_item_uses();
	$request = $uses['pending'][ $uuid ] ?? null;
	$action_context = $request ? $request['context'] : $context;
	$permission = ps_pen_paper_item_action_permission( $campaign, $action_context, 'use', array( $item ), (int) $character->ID );
	ps_pen_paper_render_item_use_summary( $campaign, $item['use_rule'], $action_context, is_wp_error( $permission ) ? 'approval' : $permission );
	if ( is_wp_error( $permission ) ) {
		echo '<p>' . esc_html( $permission->get_error_message() ) . '</p>';
		if ( 'profile' === $context || ! $request || 'approval' !== $request['condition'] || ! ps_pen_paper_can_manage_character_scope( $campaign ) ) { return; }
		if ( ps_pen_paper_item_use_form_start( $campaign, $character, 'review_item_use', $gear, $page_id, $uuid ) ) { echo '<button name="equipment[decision]" value="reject">' . esc_html__( 'Gesperrten Verbrauchsantrag ablehnen', 'ps-pen-paper' ) . '</button></form>'; }
		return;
	}
	if ( isset( $uses['receipts'][ $uuid ] ) ) { echo '<p>' . esc_html__( 'Dieser Versuch ist abgeschlossen und kann nicht wiederholt werden.', 'ps-pen-paper' ) . '</p>'; return; }
	if ( $request ) {
		echo '<p>' . esc_html__( 'Verbrauch beantragt; bisher keine Wirkung und kein Verbrauch.', 'ps-pen-paper' ) . '</p>';
		if ( 'approval' === $request['condition'] ) {
			if ( 'profile' !== $context && ps_pen_paper_can_manage_character_scope( $campaign ) && ps_pen_paper_item_use_form_start( $campaign, $character, 'review_item_use', $gear, $page_id, $uuid ) ) {
				echo '<button name="equipment[decision]" value="approve">' . esc_html__( 'Verbrauch und Wirkung freigeben', 'ps-pen-paper' ) . '</button><button name="equipment[decision]" value="reject">' . esc_html__( 'Ablehnen (Versuch beenden)', 'ps-pen-paper' ) . '</button></form>';
			}
			return;
		}
		if ( $request['context'] !== $context ) { return; }
		$round = ps_pen_paper_game_round( $campaign->ID );
		if ( is_wp_error( $round ) || ! $round['active'] || $round['round'] <= $request['round'] ) { return; }
	}
	if ( ps_pen_paper_item_use_form_start( $campaign, $character, 'use_item', $gear, $page_id, $uuid ) ) {
		echo '<button>' . esc_html__( 'Gezeigte Folgen bestätigen / Gegenstand verwenden', 'ps-pen-paper' ) . '</button></form>';
	}
}

function ps_pen_paper_render_item_effects( $campaign, $character, $gear, $page_id, $context = 'game' ) {
	foreach ( $gear['uses']['effects'] ?? array() as $uuid => $effect ) {
		if ( ! $effect['temporary'] || $effect['ended'] ) { continue; }
		echo '<p>' . esc_html( __( 'Aktiver temporärer Effekt:', 'ps-pen-paper' ) . ' ' . $effect['name'] . ( $effect['expires'] ? ' · ' . __( 'Endrunde:', 'ps-pen-paper' ) . ' ' . $effect['expires'] : '' ) ) . '</p>';
		if ( 'profile' !== $context && ps_pen_paper_can_manage_character_scope( $campaign ) && ps_pen_paper_item_use_form_start( $campaign, $character, 'end_item_effect', $gear, $page_id, $uuid ) ) { echo '<button>' . esc_html__( 'Effekt ausdrücklich beenden', 'ps-pen-paper' ) . '</button></form>'; }
	}
}

function ps_pen_paper_render_game_round_controls( $campaign, $page_id ) {
	$round = ps_pen_paper_game_round( $campaign->ID );
	if ( is_wp_error( $round ) ) { echo '<p role="alert">' . esc_html( $round->get_error_message() ) . '</p>'; return; }
	$play = ps_pen_paper_play_round( $campaign->ID );
	if ( is_wp_error( $play ) ) { echo '<p role="alert">' . esc_html( $play->get_error_message() ) . '</p>'; return; }
	if ( $play ) {
		if ( 'guided' !== $play['mode'] ) { return; }
		echo '<section class="pspp-game-round"><h3>' . esc_html__( 'Spielzüge', 'ps-pen-paper' ) . '</h3><p>' . esc_html( sprintf( __( 'Aktueller Zug: %d', 'ps-pen-paper' ), $round['round'] ) ) . '</p>';
		if ( 'active' === $play['status'] && ps_pen_paper_can_manage_campaign( $campaign ) ) {
			ps_pen_paper_equipment_form_start( $campaign, 'advance_game_round', $round['revision'], $page_id );
			echo '<button>' . esc_html__( 'Nächster Spielzug', 'ps-pen-paper' ) . '</button></form>';
		}
		echo '</section>';
		return;
	}
	echo '<section class="pspp-game-round"><h3>' . esc_html__( 'Echte Spielrunden', 'ps-pen-paper' ) . '</h3><p>' . esc_html( __( 'Spielrunde:', 'ps-pen-paper' ) . ' ' . $round['round'] . ' · ' . ( $round['active'] ? __( 'Sitzung aktiv', 'ps-pen-paper' ) : __( 'Sitzung beendet', 'ps-pen-paper' ) ) ) . '</p><p>' . esc_html__( 'Nur die Spielleitung zählt Runden weiter. Anwesenheit, AFK, Uhrzeit und Seitenaufrufe zählen niemals als Spielrunde. Ende der Sitzung hält die Rundenzählung an; Effekte mit Dauer laufen nur beim ausdrücklichen Weiterzählen ab.', 'ps-pen-paper' ) . '</p>';
	if ( ps_pen_paper_can_manage_campaign( $campaign ) ) {
		foreach ( $round['active'] ? array( 'advance_game_round' => __( 'Nächste Spielrunde / fällige Effekte beenden', 'ps-pen-paper' ), 'end_game_session' => __( 'Spielsitzung beenden', 'ps-pen-paper' ) ) : array( 'start_game_session' => __( 'Spielsitzung ausdrücklich starten', 'ps-pen-paper' ) ) as $operation => $label ) {
			ps_pen_paper_equipment_form_start( $campaign, $operation, $round['revision'], $page_id );
			echo '<button>' . esc_html( $label ) . '</button></form>';
		}
	}
	echo '</section>';
}
