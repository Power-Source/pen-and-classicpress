<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_render_adventure_world_editor( $campaign, $id, $state, $page_id ) {
	$draft = $state['draft'];
	$npcs = ps_pen_paper_npc_records( $campaign );
	if ( is_wp_error( $npcs ) ) { echo '<p role="alert">' . esc_html( $npcs->get_error_message() ) . '</p>'; return; }
	?>
	<details class="pspp-fold"><summary><?php esc_html_e( 'Abenteuerzustände und Begegnungsabläufe', 'ps-pen-paper' ); ?></summary>
	<p><?php esc_html_e( 'Eigene Zustände können beispielsweise einen Zugang, ein Ereignis oder eine NPC-Reaktion beschreiben. Namen und Werte bestimmst du. Jede Ausführung speichert die tatsächlichen Zustandsänderungen im eigenen beziehungsweise gemeinsamen Durchlauf; andere Durchläufe bleiben unverändert.', 'ps-pen-paper' ); ?></p>
	<?php ps_pen_paper_form_start( 'save_adventure_world', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
		<input type="hidden" name="adventure_complete" value="complete"><input type="hidden" name="adventure_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="adventure_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
		<fieldset data-condition-editor data-max-rows="30" data-next-index="<?php echo esc_attr( min( 30, count( $draft['states'] ?? array() ) + 2 ) ); ?>">
			<legend><?php esc_html_e( 'Frei benannte Abenteuerzustände', 'ps-pen-paper' ); ?></legend>
			<div data-condition-rows>
			<?php foreach ( $draft['states'] ?? array() as $index => $value ) { ps_pen_paper_render_adventure_state_row( $index, $value ); } ?>
			<?php for ( $index = count( $draft['states'] ?? array() ); $index < min( 30, count( $draft['states'] ?? array() ) + 2 ); $index++ ) { ps_pen_paper_render_adventure_state_row( $index ); } ?>
			</div>
			<template data-condition-template><?php ps_pen_paper_render_adventure_state_row( '__INDEX__' ); ?></template>
			<button type="button" data-add-condition hidden><?php esc_html_e( 'Weiterer Zustand', 'ps-pen-paper' ); ?></button>
			<p><?php esc_html_e( 'Startwerte gelten für neue Veröffentlichungen. Laufende Durchläufe behalten ihre Version. Zustände lassen sich in Entscheidungen vergleichen und bei Ausführung, Erfolg oder Misserfolg setzen. Verwendete Zustände können nicht stillschweigend gelöscht werden.', 'ps-pen-paper' ); ?></p>
		</fieldset>
		<?php foreach ( $draft['encounters'] ?? array() as $encounter ) : ?>
			<p><strong><?php echo esc_html( $encounter['title'] ); ?></strong>
			<label><input type="checkbox" name="adventure_world[remove_encounters][]" value="<?php echo esc_attr( $encounter['id'] ); ?>"><?php esc_html_e( 'Diesen Begegnungsablauf aus dem Entwurf entfernen', 'ps-pen-paper' ); ?></label></p>
		<?php endforeach; ?>
		<fieldset><legend><?php esc_html_e( 'Begegnungsablauf ergänzen', 'ps-pen-paper' ); ?></legend>
			<label><?php esc_html_e( 'Begegnungstitel (leer: keine neue Begegnung)', 'ps-pen-paper' ); ?><input name="adventure_world[encounter][title]" maxlength="200"></label>
			<p><?php esc_html_e( 'Wähle die vorbereiteten Szenen in Phasenreihenfolge. Entscheidungen und Würfelproben bestimmen den Übergang; es gibt keine fest vorgeschriebene Kampfregel. Alle Phasen müssen vor der Abenteuerfreigabe über den Entscheidungspfad erreichbar sein.', 'ps-pen-paper' ); ?></p>
			<fieldset data-condition-editor data-max-rows="30" data-next-index="2"><legend><?php esc_html_e( 'Geordnete Szenenphasen', 'ps-pen-paper' ); ?></legend>
				<div data-condition-rows><?php ps_pen_paper_render_adventure_phase_row( $campaign, 0 ); ps_pen_paper_render_adventure_phase_row( $campaign, 1 ); ?></div>
				<template data-condition-template><?php ps_pen_paper_render_adventure_phase_row( $campaign, '__INDEX__' ); ?></template>
				<button type="button" data-add-condition hidden><?php esc_html_e( 'Weitere Begegnungsphase', 'ps-pen-paper' ); ?></button>
			</fieldset>
			<label><?php esc_html_e( 'Beteiligte NPCs (optional)', 'ps-pen-paper' ); ?><select multiple name="adventure_world[encounter][npcs][]">
				<?php foreach ( $npcs as $npc_id => $npc ) : ?><option value="<?php echo esc_attr( $npc_id ); ?>"><?php echo esc_html( $npc['npc']['name'] ); ?></option><?php endforeach; ?>
			</select></label>
			<label class="pspp-check"><input type="checkbox" name="adventure_world[encounter][confirmation]" value="confirmed"><?php esc_html_e( 'Die gewählten Phasen und NPC-Namen/Beschreibungen dürfen im freigegebenen Abenteuer erscheinen. NPC-Geheimnisse, persönliche Werte und interne Herkunftsangaben werden nicht übernommen.', 'ps-pen-paper' ); ?></label>
		</fieldset>
		<button type="submit"><?php esc_html_e( 'Zustände und Begegnungsabläufe speichern', 'ps-pen-paper' ); ?></button>
	</form>
	</details>
	<?php
}

function ps_pen_paper_render_adventure_phase_row( $campaign, $index ) {
	echo '<div data-condition-row><label>' . esc_html__( 'Begegnungsphase', 'ps-pen-paper' ) . '<select name="adventure_world[encounter][phases][' . esc_attr( $index ) . ']"><option value="0">' . esc_html__( 'Keine Phase', 'ps-pen-paper' ) . '</option>';
	ps_pen_paper_adventure_scene_options( $campaign );
	echo '</select></label><button type="button" data-remove-condition hidden>' . esc_html__( 'Phase entfernen', 'ps-pen-paper' ) . '</button></div>';
}

function ps_pen_paper_render_adventure_state_row( $index, $state = null ) {
	?>
	<div data-condition-row>
		<input type="hidden" name="adventure_world[states][<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $state['id'] ?? '' ); ?>">
		<label><?php esc_html_e( 'Zustandsname', 'ps-pen-paper' ); ?><input name="adventure_world[states][<?php echo esc_attr( $index ); ?>][label]" maxlength="200" value="<?php echo esc_attr( $state['label'] ?? '' ); ?>"></label>
		<label><?php esc_html_e( 'Startwert', 'ps-pen-paper' ); ?><input name="adventure_world[states][<?php echo esc_attr( $index ); ?>][initial]" maxlength="2000" value="<?php echo esc_attr( $state['initial'] ?? '' ); ?>"></label>
		<?php if ( $state ) : ?><label><input type="checkbox" name="adventure_world[states][<?php echo esc_attr( $index ); ?>][remove]" value="1"><?php esc_html_e( 'Zustand entfernen', 'ps-pen-paper' ); ?></label><?php else : ?>
			<button type="button" data-remove-condition hidden><?php esc_html_e( 'Leere Zeile entfernen', 'ps-pen-paper' ); ?></button>
		<?php endif; ?>
	</div>
	<?php
}

function ps_pen_paper_render_adventure_world_fields( $campaign, $draft ) {
	$items = ps_pen_paper_community_available() ? ps_pen_paper_item_catalogue( $campaign ) : array();
	if ( is_wp_error( $items ) ) { echo '<p role="alert">' . esc_html( $items->get_error_message() ) . '</p>'; return; }
	if ( ps_pen_paper_community_available() ) : ?>
		<fieldset data-condition-editor data-next-index="2"><legend><?php esc_html_e( 'Gegenstandsvoraussetzungen', 'ps-pen-paper' ); ?></legend>
			<label><?php esc_html_e( 'Verknüpfung', 'ps-pen-paper' ); ?><select name="adventure_edge[items][mode]"><option value="all"><?php esc_html_e( 'Alle Gegenstände', 'ps-pen-paper' ); ?></option><option value="any"><?php esc_html_e( 'Mindestens einer', 'ps-pen-paper' ); ?></option></select></label>
			<div data-condition-rows><?php ps_pen_paper_render_adventure_item_row( $items, 0 ); ps_pen_paper_render_adventure_item_row( $items, 1 ); ?></div>
			<template data-condition-template><?php ps_pen_paper_render_adventure_item_row( $items, '__INDEX__' ); ?></template>
			<button type="button" data-add-condition hidden><?php esc_html_e( 'Weiterer benötigter Gegenstand', 'ps-pen-paper' ); ?></button>
			<p><?php esc_html_e( 'Geprüft werden freigegebene Exemplare im persönlichen Inventar der ausführenden Figur, auch angelegte Gegenstände. Offene Inventaranträge zählen nicht. Die Prüfung verbraucht keine Gegenstände und gibt keine fremden Inventare preis.', 'ps-pen-paper' ); ?></p>
		</fieldset>
	<?php endif; ?>
	<fieldset data-condition-editor data-next-index="2"><legend><?php esc_html_e( 'Zustandsänderungen als Entscheidungsfolgen', 'ps-pen-paper' ); ?></legend>
		<div data-condition-rows><?php ps_pen_paper_render_adventure_effect_row( $draft['states'] ?? array(), 0 ); ps_pen_paper_render_adventure_effect_row( $draft['states'] ?? array(), 1 ); ?></div>
		<template data-condition-template><?php ps_pen_paper_render_adventure_effect_row( $draft['states'] ?? array(), '__INDEX__' ); ?></template>
		<button type="button" data-add-condition hidden><?php esc_html_e( 'Weitere Zustandsfolge', 'ps-pen-paper' ); ?></button>
	</fieldset>
	<?php
}

function ps_pen_paper_render_adventure_item_row( $items, $index ) {
	?>
	<div data-condition-row>
		<label><?php esc_html_e( 'Benötigter Gegenstand', 'ps-pen-paper' ); ?><select name="adventure_edge[items][rows][<?php echo esc_attr( $index ); ?>][item_id]"><option value="0"><?php esc_html_e( 'Keine Voraussetzung', 'ps-pen-paper' ); ?></option>
			<?php foreach ( $items as $id => $item ) : ?><option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $item['item']['name'] ); ?></option><?php endforeach; ?>
		</select></label>
		<label><?php esc_html_e( 'Mindestens vorhandene Exemplare', 'ps-pen-paper' ); ?><input type="number" name="adventure_edge[items][rows][<?php echo esc_attr( $index ); ?>][minimum]" min="1" max="100" value="1" required></label>
		<button type="button" data-remove-condition hidden><?php esc_html_e( 'Voraussetzung entfernen', 'ps-pen-paper' ); ?></button>
	</div>
	<?php
}

function ps_pen_paper_render_adventure_effect_row( $states, $index ) {
	?>
	<div data-condition-row>
		<label><?php esc_html_e( 'Zustand setzen', 'ps-pen-paper' ); ?><select name="adventure_edge[effects][<?php echo esc_attr( $index ); ?>][state]"><option value=""><?php esc_html_e( 'Keine Zustandsfolge', 'ps-pen-paper' ); ?></option>
			<?php foreach ( $states as $state ) : ?><option value="<?php echo esc_attr( $state['id'] ); ?>"><?php echo esc_html( $state['label'] ); ?></option><?php endforeach; ?>
		</select></label>
		<label><?php esc_html_e( 'Neuer Wert', 'ps-pen-paper' ); ?><input name="adventure_edge[effects][<?php echo esc_attr( $index ); ?>][value]" maxlength="2000"></label>
		<label><?php esc_html_e( 'Wann?', 'ps-pen-paper' ); ?><select name="adventure_edge[effects][<?php echo esc_attr( $index ); ?>][when]"><option value="decision"><?php esc_html_e( 'Bei Ausführung', 'ps-pen-paper' ); ?></option><option value="success"><?php esc_html_e( 'Bei Erfolg', 'ps-pen-paper' ); ?></option><option value="failure"><?php esc_html_e( 'Bei Misserfolg', 'ps-pen-paper' ); ?></option></select></label>
		<button type="button" data-remove-condition hidden><?php esc_html_e( 'Zustandsfolge entfernen', 'ps-pen-paper' ); ?></button>
	</div>
	<?php
}

function ps_pen_paper_adventure_items_label( $result ) {
	$labels = array();
	foreach ( $result['rows'] as $row ) {
		/* translators: 1: item name, 2: owned quantity, 3: minimum quantity. */
		$labels[] = sprintf( __( '%1$s: vorhanden %2$d, benötigt %3$d', 'ps-pen-paper' ), $row['name'], $row['count'], $row['minimum'] );
	}
	return implode( '; ', $labels );
}

function ps_pen_paper_render_adventure_world_progress( $snapshot, $scene_id, $history ) {
	foreach ( $snapshot['encounters'] ?? array() as $encounter ) {
		$phase = array_search( $scene_id, $encounter['phases'], true );
		if ( false === $phase ) { continue; }
		/* translators: 1: encounter title, 2: current phase, 3: phase count. */
		echo '<h5>' . esc_html( sprintf( __( '%1$s – Phase %2$d von %3$d', 'ps-pen-paper' ), $encounter['title'], $phase + 1, count( $encounter['phases'] ) ) ) . '</h5>';
		foreach ( $encounter['npcs'] as $npc ) { echo '<p><strong>' . esc_html( $npc['name'] ) . '</strong></p><p class="pspp-multiline">' . esc_html( $npc['description'] ) . '</p>'; }
	}
	$values = ps_pen_paper_adventure_state_values( $snapshot, $history );
	if ( is_wp_error( $values ) ) { echo '<p role="alert">' . esc_html( $values->get_error_message() ) . '</p>'; return; }
	if ( $values ) {
		echo '<details class="pspp-fold"><summary>' . esc_html__( 'Abenteuerzustände', 'ps-pen-paper' ) . '</summary><dl>';
		foreach ( $snapshot['states'] as $state ) { echo '<dt>' . esc_html( $state['label'] ) . '</dt><dd>' . esc_html( $values[ $state['id'] ] ) . '</dd>'; }
		echo '</dl></details>';
	}
}
