<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_condition_modifiers_text( $definition, $rules ) {
	$lines = array(); $labels = array_count_values( array_column( $rules['fields'], 'label' ) );
	$fields = array_column( $rules['fields'], null, 'id' );
	foreach ( $definition['modifiers'] as $id => $amount ) {
		$label = $fields[ $id ]['label'] ?? $id;
		$lines[] = ( 1 === ( $labels[ $label ] ?? 0 ) && false === strpos( $label, '|' ) ? $label : $id ) . ' | ' . $amount;
	}
	return implode( "\n", $lines );
}

function ps_pen_paper_prepare_condition_policy( $scope, $input ) {
	$before = ps_pen_paper_condition_policy( $scope );
	if ( is_wp_error( $before ) ) { return $before; }
	if ( ! is_array( $input ) || ! is_array( $input['definitions'] ?? null ) ) { return ps_pen_paper_condition_error(); }
	$policy = ps_pen_paper_condition_policy_default(); $policy['revision'] = $before['revision'] + 1;
	foreach ( array( 'campaign_local', 'campaign_universe', 'automatic' ) as $key ) {
		if ( isset( $input[ $key ] ) && '1' !== $input[ $key ] ) { return ps_pen_paper_condition_error(); }
		$policy[ $key ] = isset( $input[ $key ] );
	}
	$rules = ps_pen_paper_get_rules( $scope->ID ); $fields = array_column( $rules['fields'], null, 'id' );
	foreach ( $input['definitions'] as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['id'], $row['label'], $row['description'], $row['modifiers'], $row['resource'], $row['maximum'] ) ) { return ps_pen_paper_condition_error(); }
		foreach ( array( 'id', 'label', 'description', 'modifiers', 'resource', 'maximum' ) as $key ) {
			if ( ! is_string( $row[ $key ] ) ) { return ps_pen_paper_condition_error(); }
		}
		if ( isset( $row['remove'] ) ) {
			if ( '1' !== $row['remove'] || ! isset( $before['definitions'][ $row['id'] ] ) ) { return ps_pen_paper_condition_error(); }
			continue;
		}
		if ( '' === trim( $row['label'] ) && '' === $row['id'] && '' === $row['modifiers'] && '' === $row['resource'] && '' === $row['description'] &&
			empty( $row['block_actions'] ) && empty( $row['block_join'] ) ) { continue; }
		$id = '' === $row['id'] ? wp_generate_uuid4() : $row['id'];
		$definition = array( 'id' => $id, 'label' => sanitize_text_field( $row['label'] ), 'description' => sanitize_textarea_field( $row['description'] ),
			'block_actions' => false, 'block_join' => false, 'modifiers' => array(), 'threshold' => null );
		foreach ( array( 'block_actions', 'block_join' ) as $key ) {
			if ( isset( $row[ $key ] ) && '1' !== $row[ $key ] ) { return ps_pen_paper_condition_error(); }
			$definition[ $key ] = isset( $row[ $key ] );
		}
		foreach ( preg_split( '/\r\n|\r|\n/', $row['modifiers'] ) as $line ) {
			if ( '' === trim( $line ) ) { continue; }
			$parts = explode( '|', $line );
			if ( 2 !== count( $parts ) || ! preg_match( '/^-?[0-9]{1,7}$/D', trim( $parts[1] ) ) ) { return ps_pen_paper_condition_error(); }
			$key = trim( $parts[0] );
			$matches = isset( $fields[ $key ] ) ? array( $fields[ $key ] ) : array_values( array_filter( $rules['fields'], static function ( $field ) use ( $key ) { return $field['label'] === $key; } ) );
			if ( 1 !== count( $matches ) || ! in_array( $matches[0]['type'], array( 'attribute', 'number' ), true ) || isset( $definition['modifiers'][ $matches[0]['id'] ] ) ) { return ps_pen_paper_condition_error(); }
			$definition['modifiers'][ $matches[0]['id'] ] = (int) trim( $parts[1] );
		}
		if ( '' !== $row['resource'] ) {
			if ( ! preg_match( '/^-?[0-9]{1,7}$/D', $row['maximum'] ) ) { return ps_pen_paper_condition_error(); }
			$definition['threshold'] = array( 'resource' => $row['resource'], 'maximum' => (int) $row['maximum'] );
		}
		if ( ! ps_pen_paper_valid_condition_definition( $definition ) || isset( $policy['definitions'][ $id ] ) ) { return ps_pen_paper_condition_error(); }
		$policy['definitions'][ $id ] = $definition;
	}
	return ps_pen_paper_valid_condition_policy( $policy ) ? $policy : ps_pen_paper_condition_error();
}

function ps_pen_paper_handle_condition_action( $operation, $page_id, $scope_id ) {
	$scope = ps_pen_paper_get_character_scope( $scope_id );
	if ( ! $scope || ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_character_scope( $scope ) ||
		( $page_id && ( ! ps_pen_paper_frontend_page( $page_id ) || ! ps_pen_paper_management_view( $page_id ) ) ) ) { ps_pen_paper_fail( __( 'Charakterfolgen werden ausschließlich in der zuständigen Spielverwaltung bearbeitet.', 'ps-pen-paper' ) ); }
	check_admin_referer( 'pspp_campaign_' . $scope_id );
	if ( 'save_condition_policy' === $operation ) {
		if ( 'complete' !== ps_pen_paper_posted_text( 'conditions_complete' ) ) { ps_pen_paper_fail( __( 'Die Zustandsvorgaben wurden nicht vollständig übertragen.', 'ps-pen-paper' ) ); }
		$policy = ps_pen_paper_prepare_condition_policy( $scope, isset( $_POST['condition_policy'] ) ? wp_unslash( $_POST['condition_policy'] ) : null );
		$result = is_wp_error( $policy ) ? $policy : ps_pen_paper_save_condition_policy( $scope, $policy, ps_pen_paper_posted_text( 'condition_revision' ) );
	} else {
		$result = ps_pen_paper_change_character_condition( $scope, ps_pen_paper_posted_id( 'character_id' ), ps_pen_paper_posted_text( 'condition_id' ),
			ps_pen_paper_posted_text( 'condition_scope' ), ps_pen_paper_posted_text( 'condition_operation' ), ps_pen_paper_posted_text( 'character_revision' ) );
	}
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	$url = 'pspp_universe' === $scope->post_type ? ps_pen_paper_universe_url( $scope->ID, $page_id ) : ps_pen_paper_game_url( $page_id, $scope->ID );
	if ( 'save_condition_policy' === $operation && 'pspp_universe' === $scope->post_type ) {
		$url = ps_pen_paper_settings_return_url( $scope_id, $page_id, 'conditions' ) ?: $url;
	}
	wp_safe_redirect( $url ); exit;
}

function ps_pen_paper_render_condition_row( $index, $definition, $rules ) {
	$prefix = 'condition_policy[definitions][' . $index . ']';
	?>
	<fieldset data-condition-row><legend><?php echo esc_html( $definition['label'] ?? __( 'Neuer Charakterzustand', 'ps-pen-paper' ) ); ?></legend>
		<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[id]" value="<?php echo esc_attr( $definition['id'] ?? '' ); ?>">
		<label><?php esc_html_e( 'Zustandsname', 'ps-pen-paper' ); ?><input name="<?php echo esc_attr( $prefix ); ?>[label]" maxlength="120" value="<?php echo esc_attr( $definition['label'] ?? '' ); ?>"></label>
		<label><?php esc_html_e( 'Folgen und verbleibende Möglichkeiten', 'ps-pen-paper' ); ?><textarea name="<?php echo esc_attr( $prefix ); ?>[description]" maxlength="2000"><?php echo esc_textarea( $definition['description'] ?? '' ); ?></textarea></label>
		<?php foreach ( array( 'block_actions' => __( 'Normale Spielaktionen sperren', 'ps-pen-paper' ), 'block_join' => __( 'Beitritt zu neuen Runden sperren', 'ps-pen-paper' ) ) as $key => $label ) : ?>
			<label><input type="checkbox" name="<?php echo esc_attr( $prefix . '[' . $key . ']' ); ?>" value="1" <?php checked( ! empty( $definition[ $key ] ) ); ?>><?php echo esc_html( $label ); ?></label>
		<?php endforeach; ?>
		<label><?php esc_html_e( 'Optionale Wertänderungen: je Zeile Feldname | Änderung', 'ps-pen-paper' ); ?><textarea name="<?php echo esc_attr( $prefix ); ?>[modifiers]" placeholder="<?php esc_attr_e( 'Beispiel: Mut | -2', 'ps-pen-paper' ); ?>"><?php echo esc_textarea( $definition ? ps_pen_paper_condition_modifiers_text( $definition, $rules ) : '' ); ?></textarea></label>
		<label><?php esc_html_e( 'Zusätzlich automatisch wirksam, solange diese Ressource den Grenzwert nicht überschreitet', 'ps-pen-paper' ); ?><select name="<?php echo esc_attr( $prefix ); ?>[resource]"><option value=""><?php esc_html_e( 'Keine automatische Ressourcenregel', 'ps-pen-paper' ); ?></option>
			<?php foreach ( $rules['fields'] as $field ) : if ( 'resource' !== $field['type'] ) { continue; } ?><option value="<?php echo esc_attr( $field['id'] ); ?>" <?php selected( $definition['threshold']['resource'] ?? '', $field['id'] ); ?>><?php echo esc_html( $field['label'] ); ?></option><?php endforeach; ?>
		</select></label>
		<label><?php esc_html_e( 'Ressourcengrenzwert', 'ps-pen-paper' ); ?><input type="number" name="<?php echo esc_attr( $prefix ); ?>[maximum]" min="-1000000" max="1000000" value="<?php echo esc_attr( $definition['threshold']['maximum'] ?? 0 ); ?>"></label>
		<?php if ( $definition ) : ?><label><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[remove]" value="1"><?php esc_html_e( 'Vorlage entfernen; gespeicherte Charakterfolgen bleiben erhalten', 'ps-pen-paper' ); ?></label><?php endif; ?>
	</fieldset>
	<?php
}

function ps_pen_paper_render_condition_policy( $scope, $page_id, $open = false ) {
	$owner = ps_pen_paper_condition_scope( $scope ); $policy = ps_pen_paper_condition_policy( $scope );
	if ( is_wp_error( $owner ) || is_wp_error( $policy ) ) { echo '<p role="alert">' . esc_html( is_wp_error( $owner ) ? $owner->get_error_message() : $policy->get_error_message() ) . '</p>'; return; }
	if ( (int) $owner->ID !== (int) $scope->ID || ! ps_pen_paper_can_manage_character_scope( $owner ) ) { return; }
	$rules = ps_pen_paper_get_rules( $owner->ID );
	?>
	<details class="pspp-fold" <?php if ( $open ) { echo 'open'; } ?>><summary><?php esc_html_e( 'Charakterzustände, Verletzung und Tod selbst regeln', 'ps-pen-paper' ); ?></summary>
	<p><?php esc_html_e( 'Es gibt keine fest eingebaute Todesregel. Namen, Auswirkungen, Rettung und Wiederbelebung bestimmst du. Abenteuererfolg und Charakterzustand sind unabhängig. Keine Figur wird durch einen Zustand gelöscht.', 'ps-pen-paper' ); ?></p>
	<?php ps_pen_paper_form_start( 'save_condition_policy', 'pspp_campaign_' . $owner->ID, $owner->ID, $page_id ); ?>
		<input type="hidden" name="condition_revision" value="<?php echo esc_attr( $policy['revision'] ); ?>">
		<?php foreach ( array( 'campaign_local' => __( 'Kampagnenleitungen dürfen lokale Charakterfolgen setzen und aufheben', 'ps-pen-paper' ),
			'campaign_universe' => __( 'Kampagnenleitungen dürfen dauerhafte Folgen am Universumscharakter setzen und aufheben', 'ps-pen-paper' ),
			'automatic' => __( 'Vorbereitete Abenteuer dürfen Rettungsausnahmen und automatische Folgen innerhalb dieser Befugnisse ausführen', 'ps-pen-paper' ) ) as $key => $label ) : ?>
			<label><input type="checkbox" name="condition_policy[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $policy[ $key ] ); ?>><?php echo esc_html( $label ); ?></label>
		<?php endforeach; ?>
		<p><?php esc_html_e( 'Die Universumsleitung kann gespeicherte Folgen immer korrigieren. Vorbereitete Abenteuer übernehmen eine feste Version; geänderte Vorlagen müssen vor einer weiteren automatischen Anwendung neu veröffentlicht werden. Gespeicherte Folgen behalten ihre Wirkung bis zur ausdrücklichen Aufhebung. Ressourcenregeln wirken dagegen nur solange der Grenzwert erreicht ist und enden bei Erholung automatisch.', 'ps-pen-paper' ); ?></p>
		<p><?php esc_html_e( 'Wertänderungen gelten zusätzlich zu Grundwerten und Ausrüstung, ohne Grundwerte zu überschreiben. Nur Zahlenfelder und Attribute sind zulässig; bei gleichnamigen Feldern verwende die Feld-ID aus dem Regelwerk.', 'ps-pen-paper' ); ?></p>
		<div data-condition-editor data-max-rows="30" data-next-index="<?php echo esc_attr( count( $policy['definitions'] ) + 1 ); ?>"><div data-condition-rows>
			<?php foreach ( array_values( $policy['definitions'] ) as $index => $definition ) { ps_pen_paper_render_condition_row( $index, $definition, $rules ); } ?>
			<?php if ( count( $policy['definitions'] ) < 30 ) { ps_pen_paper_render_condition_row( count( $policy['definitions'] ), null, $rules ); } ?>
		</div><template data-condition-template><?php ps_pen_paper_render_condition_row( '__INDEX__', null, $rules ); ?></template>
		<button type="button" data-add-condition hidden><?php esc_html_e( 'Weiterer Charakterzustand', 'ps-pen-paper' ); ?></button></div>
		<input type="hidden" name="conditions_complete" value="complete">
		<button type="submit"><?php esc_html_e( 'Zustandsregeln speichern', 'ps-pen-paper' ); ?></button>
	</form></details>
	<?php
}

function ps_pen_paper_render_character_conditions( $scope, $character, $page_id = 0, $compact = false, $management = false ) {
	$state = ps_pen_paper_resolve_character_state( $scope, $character, false );
	$values = is_wp_error( $state ) ? $state : ps_pen_paper_character_conditions( $scope, $character, $state );
	$durations = array( 'local' => __( 'Nur dieser Kampagnen-/Rundenbogen', 'ps-pen-paper' ), 'universe' => __( 'Dauerhaft am Universumscharakter', 'ps-pen-paper' ), 'threshold' => __( 'Solange die Ressourcengrenze erreicht ist', 'ps-pen-paper' ) );
	if ( is_wp_error( $values ) ) { echo '<p role="alert">' . esc_html( $values->get_error_message() ) . '</p>'; }
	elseif ( $values ) {
		echo '<div class="pspp-character-conditions"><h4>' . esc_html__( 'Charakterfolgen', 'ps-pen-paper' ) . '</h4><ul>';
		foreach ( $values as $value ) {
			$definition = $value['definition'];
			echo '<li><strong>' . esc_html( $definition['label'] ) . '</strong> · ' . esc_html( $durations[ $value['scope'] ] );
			if ( ! empty( $value['also_local'] ) ) { echo ' · ' . esc_html__( 'Zusätzlich lokal gespeichert; getrennt aufheben', 'ps-pen-paper' ); }
			if ( $definition['block_actions'] ) { echo ' · ' . esc_html__( 'Nur vorbereitete Rettungsaktionen', 'ps-pen-paper' ); }
			if ( $definition['block_join'] ) { echo ' · ' . esc_html__( 'Keine neue Runde', 'ps-pen-paper' ); }
			if ( ! $compact ) {
				if ( $definition['description'] ) { echo '<p>' . nl2br( esc_html( $definition['description'] ) ) . '</p>'; }
				if ( $definition['modifiers'] ) { echo '<pre>' . esc_html( ps_pen_paper_condition_modifiers_text( $definition, ps_pen_paper_get_rules( $scope->ID ) ) ) . '</pre>'; }
			}
			echo '</li>';
		}
		echo '</ul></div>';
	}
	if ( $compact || ! $management || ! ps_pen_paper_can_manage_character_scope( $scope ) ) { return; }
	$context = ps_pen_paper_condition_context( $scope, $character ); $policy = ps_pen_paper_condition_policy( $scope );
	if ( is_wp_error( $context ) || is_wp_error( $policy ) ) { return; }
	$world_manager = ps_pen_paper_can_manage_character_scope( $context['scope'] );
	foreach ( array( 'local' => $character, 'universe' => $context['base'] ) as $duration => $target ) {
		if ( ! $target || ( 'local' === $duration && 'pspp_universe' === $scope->post_type ) || ( ! $world_manager && ! $policy[ 'universe' === $duration ? 'campaign_universe' : 'campaign_local' ] ) ) { continue; }
		$raw = ps_pen_paper_raw_character_state( $target->ID );
		if ( is_wp_error( $raw ) ) { echo '<p role="alert">' . esc_html( $raw->get_error_message() ) . '</p>'; continue; }
		echo '<details class="pspp-fold"><summary>' . esc_html( $durations[ $duration ] . ' · ' . __( 'Folgen korrigieren', 'ps-pen-paper' ) ) . '</summary>';
		foreach ( array( 'set' => $policy['definitions'], 'remove' => $raw['conditions'] ?? array() ) as $operation => $definitions ) {
			if ( ! $definitions ) { continue; }
			ps_pen_paper_form_start( 'change_character_condition', 'pspp_campaign_' . $scope->ID, $scope->ID, $page_id );
			echo '<input type="hidden" name="character_id" value="' . esc_attr( $character->ID ) . '"><input type="hidden" name="character_revision" value="' . esc_attr( $raw['revision'] ) . '"><input type="hidden" name="condition_scope" value="' . esc_attr( $duration ) . '"><input type="hidden" name="condition_operation" value="' . esc_attr( $operation ) . '"><select name="condition_id" aria-label="' . esc_attr__( 'Charakterzustand', 'ps-pen-paper' ) . '">';
			foreach ( $definitions as $id => $definition ) { echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $definition['label'] ) . '</option>'; }
			echo '</select><button type="submit">' . esc_html( 'set' === $operation ? __( 'Zustand ausdrücklich setzen/aktualisieren', 'ps-pen-paper' ) : __( 'Zustand aufheben / heilen / wiederbeleben', 'ps-pen-paper' ) ) . '</button></form>';
		}
		echo '</details>';
	}
}

function ps_pen_paper_render_adventure_character_condition_fields( $scope ) {
	$policy = ps_pen_paper_condition_policy( $scope );
	if ( is_wp_error( $policy ) ) { echo '<p role="alert">' . esc_html( $policy->get_error_message() ) . '</p>'; return; }
	if ( ! $policy['definitions'] ) { return; }
	?>
	<details class="pspp-fold"><summary><?php esc_html_e( 'Charakterfolgen und Rettungsaktionen', 'ps-pen-paper' ); ?></summary>
	<?php foreach ( array( 'requires' => __( 'Diese Charakterzustände müssen vorliegen (alle)', 'ps-pen-paper' ), 'allows' => __( 'Diese Aktionssperren darf genau diese Rettungsaktion überwinden', 'ps-pen-paper' ) ) as $key => $label ) : ?>
		<label><?php echo esc_html( $label ); ?><select multiple name="adventure_edge[character_conditions][<?php echo esc_attr( $key ); ?>][]"><?php foreach ( $policy['definitions'] as $id => $definition ) : ?><option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $definition['label'] ); ?></option><?php endforeach; ?></select></label>
	<?php endforeach; ?>
	<p><?php esc_html_e( 'Folgen betreffen nur die handelnde Figur, niemals ungefragt die ganze Gruppe. Für Heilung, Flucht oder Wiederbelebung kann dieselbe Entscheidung einen Zustand ausdrücklich aufheben. Permanente Folgen brauchen einen zugeordneten Universumsbasisbogen und die Erlaubnis der Universumsleitung.', 'ps-pen-paper' ); ?></p>
	<div data-condition-editor data-max-rows="20" data-next-index="1"><div data-condition-rows><?php ps_pen_paper_render_adventure_character_condition_row( $policy, 0 ); ?></div>
	<template data-condition-template><?php ps_pen_paper_render_adventure_character_condition_row( $policy, '__INDEX__' ); ?></template>
	<button type="button" data-add-condition hidden><?php esc_html_e( 'Weitere Charakterfolge', 'ps-pen-paper' ); ?></button></div>
	</details>
	<?php
}

function ps_pen_paper_render_adventure_character_condition_row( $policy, $index ) {
	$prefix = 'adventure_edge[character_conditions][effects][' . $index . ']';
	echo '<div data-condition-row><label>' . esc_html__( 'Zustand', 'ps-pen-paper' ) . '<select name="' . esc_attr( $prefix ) . '[id]"><option value="">' . esc_html__( 'Keine Charakterfolge', 'ps-pen-paper' ) . '</option>';
	foreach ( $policy['definitions'] as $id => $definition ) { echo '<option value="' . esc_attr( $id ) . '">' . esc_html( $definition['label'] ) . '</option>'; }
	echo '</select></label>';
	foreach ( array(
		'scope' => array( 'local' => __( 'Nur dieser Kampagnen-/Rundenbogen', 'ps-pen-paper' ), 'universe' => __( 'Dauerhaft am Universumscharakter', 'ps-pen-paper' ) ),
		'operation' => array( 'set' => __( 'Zustand setzen', 'ps-pen-paper' ), 'remove' => __( 'Zustand aufheben', 'ps-pen-paper' ) ),
		'when' => array( 'decision' => __( 'Bei Ausführung', 'ps-pen-paper' ), 'success' => __( 'Bei Erfolg', 'ps-pen-paper' ), 'failure' => __( 'Bei Misserfolg', 'ps-pen-paper' ) ),
	) as $key => $options ) {
		echo '<select name="' . esc_attr( $prefix . '[' . $key . ']' ) . '" aria-label="' . esc_attr( array( 'scope' => __( 'Geltungsbereich', 'ps-pen-paper' ), 'operation' => __( 'Zustandsänderung', 'ps-pen-paper' ), 'when' => __( 'Auslöser', 'ps-pen-paper' ) )[ $key ] ) . '">';
		foreach ( $options as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>'; }
		echo '</select>';
	}
	echo '</div>';
}

function ps_pen_paper_render_adventure_character_condition_summary( $scope, $value ) {
	$policy = ps_pen_paper_condition_policy( $scope );
	if ( is_wp_error( $policy ) ) { echo '<p role="alert">' . esc_html( $policy->get_error_message() ) . '</p>'; return; }
	echo '<details class="pspp-fold"><summary>' . esc_html__( 'Vorbereitete Charakterfolgen und Rettung', 'ps-pen-paper' ) . '</summary><ul>';
	foreach ( array( 'requires' => __( 'Voraussetzung:', 'ps-pen-paper' ), 'allows' => __( 'Erlaubte Rettungsausnahme:', 'ps-pen-paper' ) ) as $key => $label ) {
		foreach ( $value[ $key ] as $id ) { echo '<li>' . esc_html( $label . ' ' . ( $policy['definitions'][ $id ]['label'] ?? $id ) ) . '</li>'; }
	}
	$timings = array( 'decision' => __( 'Bei Ausführung', 'ps-pen-paper' ), 'success' => __( 'Bei Erfolg', 'ps-pen-paper' ), 'failure' => __( 'Bei Misserfolg', 'ps-pen-paper' ) );
	foreach ( $value['effects'] as $effect ) {
		echo '<li>' . esc_html( $timings[ $effect['when'] ] . ' · ' . $effect['definition']['label'] . ' · ' .
			( 'set' === $effect['operation'] ? __( 'Zustand setzen', 'ps-pen-paper' ) : __( 'Zustand aufheben', 'ps-pen-paper' ) ) . ' · ' .
			( 'universe' === $effect['scope'] ? __( 'Dauerhaft am Universumscharakter', 'ps-pen-paper' ) : __( 'Nur dieser Kampagnen-/Rundenbogen', 'ps-pen-paper' ) ) ) . '</li>';
	}
	echo '</ul></details>';
}
