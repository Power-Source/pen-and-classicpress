<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_scenario_modes() {
	return array( 'both' => __( 'Einzeln oder gemeinsam', 'ps-pen-paper' ), 'solo' => __( 'Nur einzeln', 'ps-pen-paper' ),
		'group' => __( 'Nur in einer Gruppe', 'ps-pen-paper' ) );
}

function ps_pen_paper_scenario_reward_modes() {
	return array( 'once' => __( 'Nur im ersten Durchlauf belohnen', 'ps-pen-paper' ),
		'always' => __( 'In jedem Durchlauf gleich belohnen', 'ps-pen-paper' ),
		'alternate' => __( 'Ab Wiederholung andere Belohnungen', 'ps-pen-paper' ) );
}

function ps_pen_paper_scenario_outcomes() {
	return array( 'neutral' => __( 'Neutral abgeschlossen', 'ps-pen-paper' ),
		'success' => __( 'Gewonnen', 'ps-pen-paper' ), 'failure' => __( 'Verloren', 'ps-pen-paper' ) );
}

function ps_pen_paper_render_scenario_prerequisite( $records, $id, $index, $row = null ) {
	$row = $row ?? array( 'adventure_id' => 0, 'result' => 'completed' );
	?>
	<div data-condition-row>
		<label><?php esc_html_e( 'Vorheriges Szenario', 'ps-pen-paper' ); ?><select name="adventure_scenario[prerequisites][<?php echo esc_attr( $index ); ?>][adventure_id]">
			<option value="0"><?php esc_html_e( 'Kein Vorgänger', 'ps-pen-paper' ); ?></option>
			<?php foreach ( $records as $other_id => $record ) : if ( $other_id === $id ) { continue; } ?>
				<option value="<?php echo esc_attr( $other_id ); ?>"<?php selected( $row['adventure_id'], $other_id ); ?>><?php echo esc_html( $record['draft']['title'] ); ?></option>
			<?php endforeach; ?>
		</select></label>
		<label><?php esc_html_e( 'Erforderliches Ergebnis', 'ps-pen-paper' ); ?><select name="adventure_scenario[prerequisites][<?php echo esc_attr( $index ); ?>][result]">
			<option value="completed"<?php selected( $row['result'], 'completed' ); ?>><?php esc_html_e( 'Abgeschlossen, unabhängig vom Ausgang', 'ps-pen-paper' ); ?></option>
			<option value="success"<?php selected( $row['result'], 'success' ); ?>><?php esc_html_e( 'Gewonnen', 'ps-pen-paper' ); ?></option>
		</select></label>
		<button type="button" data-remove-condition hidden><?php esc_html_e( 'Voraussetzung entfernen', 'ps-pen-paper' ); ?></button>
	</div>
	<?php
}

function ps_pen_paper_render_scenario_editor( $campaign, $id, $state, $records, $rules, $page_id ) {
	$scenario = $state['draft']['scenario'] ?? ps_pen_paper_scenario_default();
	$folios = ps_pen_paper_folio_records( $campaign );
	if ( is_wp_error( $folios ) || is_wp_error( $rules ) ) {
		echo '<p role="alert">' . esc_html( is_wp_error( $folios ) ? $folios->get_error_message() : $rules->get_error_message() ) . '</p>'; return;
	}
	$rows = $scenario['prerequisites']; $count = max( 2, count( $rows ) );
	?>
	<details class="pspp-fold"><summary><?php esc_html_e( 'Szenario: Kapitel, Zugang und Wiederholungen', 'ps-pen-paper' ); ?></summary>
		<p><?php esc_html_e( 'Verbinde Szenarien zu einer Kapitelgeschichte oder lasse sie unabhängig spielbar. Ohne Vorgänger gibt es keine erzwungene Reihenfolge. Die Einstellungen gelten nach der nächsten Abenteuerfreigabe; laufende Durchläufe behalten ihren Stand.', 'ps-pen-paper' ); ?></p>
		<?php ps_pen_paper_form_start( 'save_adventure_scenario', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
		<input type="hidden" name="adventure_complete" value="complete"><input type="hidden" name="adventure_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="adventure_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
		<label><?php esc_html_e( 'Kapitel (optional)', 'ps-pen-paper' ); ?><select name="adventure_scenario[chapter_id]">
			<option value="0"><?php esc_html_e( 'Loses Szenario ohne Kapitel', 'ps-pen-paper' ); ?></option>
			<?php foreach ( $folios as $chapter_id => $folio ) : if ( 'chapter' !== $folio['draft']['type'] ) { continue; } ?>
				<option value="<?php echo esc_attr( $chapter_id ); ?>"<?php selected( $scenario['chapter_id'], $chapter_id ); ?>><?php echo esc_html( $folio['draft']['title'] ); ?></option>
			<?php endforeach; ?>
		</select></label>
		<p><?php esc_html_e( 'Bei Freigabe wird nur der gewählte Kapiteltitel für Spieler sichtbar, niemals Kapitelnotizen oder Geheimnisse.', 'ps-pen-paper' ); ?></p>
		<label><?php esc_html_e( 'Reihenfolge der Anzeige (keine Zugangssperre)', 'ps-pen-paper' ); ?><input type="number" name="adventure_scenario[order]" min="0" max="9999" value="<?php echo esc_attr( $scenario['order'] ); ?>" required></label>
		<label><?php esc_html_e( 'Spielweise', 'ps-pen-paper' ); ?><select name="adventure_scenario[mode]">
			<?php foreach ( ps_pen_paper_scenario_modes() as $mode => $label ) : ?><option value="<?php echo esc_attr( $mode ); ?>"<?php selected( $scenario['mode'], $mode ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
		</select></label>
		<label><?php esc_html_e( 'Wiederholung', 'ps-pen-paper' ); ?><select name="adventure_scenario[repeat]">
			<option value="0"<?php selected( $scenario['repeat'], false ); ?>><?php esc_html_e( 'Nicht erlaubt', 'ps-pen-paper' ); ?></option>
			<option value="1"<?php selected( $scenario['repeat'], true ); ?>><?php esc_html_e( 'Erlaubt', 'ps-pen-paper' ); ?></option>
		</select></label>
		<label><?php esc_html_e( 'Belohnungsregel', 'ps-pen-paper' ); ?><select name="adventure_scenario[rewards]">
			<?php foreach ( ps_pen_paper_scenario_reward_modes() as $mode => $label ) : ?><option value="<?php echo esc_attr( $mode ); ?>"<?php selected( $scenario['rewards'], $mode ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
		</select></label>
		<p><?php esc_html_e( 'Der erste Durchlauf zählt je Spielerkonto und Szenario, auch über andere eigene Figuren und Gruppenteilnahmen hinweg. Eine neue Abenteuerfreigabe setzt ihn nicht zurück. Bei „andere Belohnungen“ die Wiederholungsbelohnung an den betreffenden Entscheidungen einrichten; ohne solche bleibt eine Wiederholung unbelohnt.', 'ps-pen-paper' ); ?></p>
		<label><?php esc_html_e( 'Optionaler Mindestwert: Level, Rang oder anderes Zahlenfeld', 'ps-pen-paper' ); ?><select name="adventure_scenario[minimum_field]">
			<option value=""><?php esc_html_e( 'Kein Mindestwert', 'ps-pen-paper' ); ?></option>
			<?php foreach ( $rules['fields'] as $field ) : if ( ! in_array( $field['type'], array( 'number', 'attribute' ), true ) ) { continue; } ?>
				<option value="<?php echo esc_attr( $field['id'] ); ?>"<?php selected( $scenario['minimum']['field'] ?? '', $field['id'] ); ?>><?php echo esc_html( $field['label'] ); ?></option>
			<?php endforeach; ?>
		</select></label>
		<label><?php esc_html_e( 'Mindestwert (ohne Feld: 0)', 'ps-pen-paper' ); ?><input type="number" name="adventure_scenario[minimum_value]" min="-1000000" max="1000000" value="<?php echo esc_attr( $scenario['minimum']['value'] ?? 0 ); ?>" required></label>
		<fieldset data-condition-editor data-next-index="<?php echo esc_attr( $count ); ?>"><legend><?php esc_html_e( 'Vorherige Szenarien (alle erforderlich)', 'ps-pen-paper' ); ?></legend>
			<div data-condition-rows><?php for ( $index = 0; $index < $count; $index++ ) { ps_pen_paper_render_scenario_prerequisite( $records, $id, $index, $rows[ $index ] ?? null ); } ?></div>
			<template data-condition-template><?php ps_pen_paper_render_scenario_prerequisite( $records, $id, '__INDEX__' ); ?></template>
			<button type="button" data-add-condition hidden><?php esc_html_e( 'Weiteren Vorgänger ergänzen', 'ps-pen-paper' ); ?></button>
		</fieldset>
		<button type="submit"><?php esc_html_e( 'Szenarioeinstellungen speichern', 'ps-pen-paper' ); ?></button>
		</form>
	</details>
	<?php
}

function ps_pen_paper_render_scenario_access( $snapshot, $access ) {
	if ( ! isset( $snapshot['scenario'] ) ) { return; }
	if ( is_wp_error( $access ) ) { echo '<p role="alert">' . esc_html( $access->get_error_message() ) . '</p>'; return; }
	$scenario = $snapshot['scenario'];
	if ( $scenario['chapter_title'] ) { echo '<p class="pspp-eyebrow">' . esc_html( $scenario['chapter_title'] ) . '</p>'; }
	echo '<p>' . esc_html( ps_pen_paper_scenario_modes()[ $scenario['mode'] ] . ' · ' .
		( $scenario['repeat'] ? __( 'Wiederholung erlaubt', 'ps-pen-paper' ) : __( 'Keine Wiederholung', 'ps-pen-paper' ) ) .
		' · ' . ps_pen_paper_scenario_reward_modes()[ $scenario['rewards'] ] ) . '</p>';
	foreach ( $access['messages'] as $message ) { echo '<p>' . esc_html( $message ) . '</p>'; }
}

function ps_pen_paper_render_scenario_character_links( $campaign, $page_id, $show_heading = true ) {
	$heading_shown = false;
	foreach ( ps_pen_paper_get_characters( $campaign ) as $character ) {
		if ( (int) $character->post_author !== get_current_user_id() ) { continue; }
		$state = ps_pen_paper_character_state( $campaign, $character );
		if ( is_wp_error( $state ) ) { echo '<p role="alert">' . esc_html( $state->get_error_message() ) . '</p>'; continue; }
		if ( ! $state['active'] ) { continue; }
		if ( ! $heading_shown && $show_heading ) { echo '<h3>' . esc_html__( 'Allein spielen', 'ps-pen-paper' ) . '</h3>'; }
		$heading_shown = true;
		/* translators: %s: player's approved character name. */
		$label = sprintf( __( 'Mit %s spielen', 'ps-pen-paper' ), $state['active']['name'] );
		echo '<p><a href="' . esc_url( ps_pen_paper_character_url( $campaign->ID, $character->ID, $page_id ) . '#pspp-adventures' ) . '">' .
			esc_html( $label ) . '</a></p>';
	}
	if ( ! $heading_shown && ! $show_heading ) {
		$url = ps_pen_paper_community_url();
		echo '<p>' . esc_html__( 'Du brauchst einen eigenen freigegebenen Charakter für diese Kampagne.', 'ps-pen-paper' ) . '</p>';
		if ( $url ) { echo '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Meine Charaktere öffnen', 'ps-pen-paper' ) . '</a>'; }
	}
}
