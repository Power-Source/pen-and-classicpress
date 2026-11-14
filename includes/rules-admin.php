<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_render_rule_field( $field, $index, $locked = false ) {
	$prefix = 'rules[fields][' . $index . ']';
	$element_id = 'pspp-field-' . $index;
	?>
	<fieldset class="pspp-field">
		<legend><?php esc_html_e( 'Charakterfeld', 'ps-pen-paper' ); ?> <span data-field-number></span></legend>
		<input type="hidden" name="<?php echo esc_attr( $prefix . '[id]' ); ?>" value="<?php echo esc_attr( $field['id'] ); ?>">
		<div class="pspp-grid">
			<p>
				<label for="<?php echo esc_attr( $element_id . '-label' ); ?>"><?php esc_html_e( 'Bezeichnung', 'ps-pen-paper' ); ?></label>
				<input id="<?php echo esc_attr( $element_id . '-label' ); ?>" name="<?php echo esc_attr( $prefix . '[label]' ); ?>" type="text" value="<?php echo esc_attr( $field['label'] ); ?>" maxlength="200" required data-field-label>
			</p>
			<p>
				<label for="<?php echo esc_attr( $element_id . '-type' ); ?>"><?php esc_html_e( 'Feldtyp', 'ps-pen-paper' ); ?></label>
				<?php if ( $locked ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $prefix . '[type]' ); ?>" value="<?php echo esc_attr( $field['type'] ); ?>">
				<?php endif; ?>
				<select id="<?php echo esc_attr( $element_id . '-type' ); ?>" name="<?php echo esc_attr( $prefix . '[type]' ); ?>" data-field-type <?php disabled( $locked ); ?>>
					<?php foreach ( ps_pen_paper_field_types() as $type => $label ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $field['type'], $type ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		</div>
		<p>
			<label for="<?php echo esc_attr( $element_id . '-description' ); ?>"><?php esc_html_e( 'Erläuterung', 'ps-pen-paper' ); ?></label>
			<textarea id="<?php echo esc_attr( $element_id . '-description' ); ?>" name="<?php echo esc_attr( $prefix . '[description]' ); ?>" rows="2" maxlength="2000"><?php echo esc_textarea( $field['description'] ); ?></textarea>
		</p>
		<fieldset class="pspp-grid pspp-numeric" data-numeric>
			<legend><?php esc_html_e( 'Wertebereich (nur für Zahlen, Attribute und Ressourcen)', 'ps-pen-paper' ); ?></legend>
			<?php
			foreach ( array(
				'min'   => __( 'Minimum', 'ps-pen-paper' ),
				'max'   => __( 'Maximum', 'ps-pen-paper' ),
				'start' => __( 'Startwert', 'ps-pen-paper' ),
			) as $key => $label ) :
				$value = isset( $field[ $key ] ) ? $field[ $key ] : ( 'max' === $key ? 100 : 0 );
				?>
				<p>
					<label for="<?php echo esc_attr( $element_id . '-' . $key ); ?>"><?php echo esc_html( $label ); ?></label>
					<input id="<?php echo esc_attr( $element_id . '-' . $key ); ?>" name="<?php echo esc_attr( $prefix . '[' . $key . ']' ); ?>" type="number" step="1" min="-1000000" max="1000000" value="<?php echo esc_attr( $value ); ?>" required data-number="<?php echo esc_attr( $key ); ?>" <?php echo $locked && 'start' !== $key ? 'readonly' : ''; ?>>
				</p>
			<?php endforeach; ?>
		</fieldset>
		<p class="description" data-resource-note><?php esc_html_e( 'Bei Ressourcen ist das Maximum zugleich der anfängliche Höchstwert des Ressourcenbalkens.', 'ps-pen-paper' ); ?></p>
		<?php if ( $locked && $field['required'] ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $prefix . '[required]' ); ?>" value="1">
		<?php endif; ?>
		<p><label class="pspp-check"><input type="checkbox" name="<?php echo esc_attr( $prefix . '[required]' ); ?>" value="1" <?php checked( $field['required'] ); ?> <?php disabled( $locked ); ?>> <?php esc_html_e( 'Pflichtfeld', 'ps-pen-paper' ); ?></label></p>
		<div class="pspp-actions" data-field-actions hidden>
			<button type="button" class="button" data-move="up"><?php esc_html_e( 'Nach oben', 'ps-pen-paper' ); ?></button>
			<button type="button" class="button" data-move="down"><?php esc_html_e( 'Nach unten', 'ps-pen-paper' ); ?></button>
			<?php if ( ! $locked ) : ?>
				<button type="button" class="button" data-remove><?php esc_html_e( 'Feld entfernen', 'ps-pen-paper' ); ?></button>
			<?php endif; ?>
		</div>
	</fieldset>
	<?php
}

function ps_pen_paper_render_rules( $campaign, $can_manage, $page_id = 0 ) {
	$rules = ps_pen_paper_get_rules( $campaign->ID );
	$locked = ps_pen_paper_has_characters( $campaign->ID );
	?>
	<section class="pspp-rules" aria-labelledby="pspp-rules-heading">
		<h2 id="pspp-rules-heading"><?php esc_html_e( 'Regelwerk und Charakterbogen-Vorlage', 'ps-pen-paper' ); ?></h2>
		<?php if ( ! $can_manage ) : ?>
			<?php if ( 0 === $rules['revision'] ) : ?>
				<p><?php esc_html_e( 'Noch kein Regelwerk eingerichtet.', 'ps-pen-paper' ); ?></p>
			<?php else : ?>
				<h3><?php echo esc_html( $rules['name'] ); ?></h3>
				<p class="pspp-multiline"><?php echo esc_html( $rules['description'] ); ?></p>
				<p><?php esc_html_e( 'Erlaubte Würfel:', 'ps-pen-paper' ); ?> <?php echo esc_html( $rules['dice'] ? 'W' . implode( ', W', $rules['dice'] ) : __( 'Keine', 'ps-pen-paper' ) ); ?></p>
				<ul>
					<?php foreach ( $rules['fields'] as $field ) : ?>
						<li><strong><?php echo esc_html( $field['label'] ); ?></strong> – <?php echo esc_html( ps_pen_paper_field_types()[ $field['type'] ] ); ?><br><?php echo esc_html( $field['description'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php else : ?>
			<p><?php echo esc_html( 'pspp_universe' === $campaign->post_type ? __( 'Diese Vorlage gilt für die Basisbögen. Kampagnen übernehmen sie nur ausdrücklich; bestehende Kampagnenregeln werden nicht synchronisiert. Die Spielerbearbeitung wird als Vorgabe mitkopiert; Basisbögen bleiben ausschließlich durch die Universumsleitung bearbeitbar.', 'ps-pen-paper' ) : __( 'Diese Einstellungen gelten nur für diese Kampagne und ihre Charaktere und Würfe.', 'ps-pen-paper' ) ); ?></p>
			<?php ps_pen_paper_form_start( 'pspp_universe' === $campaign->post_type ? 'save_universe_rules' : 'save_rules', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
				<div data-rules-editor data-limit="30" data-structure-locked="<?php echo $locked ? 'true' : 'false'; ?>"
					data-limit-message="<?php esc_attr_e( 'Es sind höchstens 30 Felder möglich.', 'ps-pen-paper' ); ?>"
					data-range-message="<?php esc_attr_e( 'Der Startwert muss zwischen Minimum und Maximum liegen.', 'ps-pen-paper' ); ?>"
					data-remove-message="<?php esc_attr_e( 'Dieses Feld aus der Vorlage entfernen? Erst beim Speichern wird die Änderung übernommen.', 'ps-pen-paper' ); ?>"
					data-unsaved-message="<?php esc_attr_e( 'Es gibt ungespeicherte Änderungen.', 'ps-pen-paper' ); ?>"
					data-change-message="<?php esc_attr_e( 'Feldliste geändert. Bitte speichere das Regelwerk.', 'ps-pen-paper' ); ?>">
					<input type="hidden" name="rules_revision" value="<?php echo esc_attr( $rules['revision'] ); ?>">
					<p><label for="pspp-rule-name"><?php esc_html_e( 'Name des Regelwerks', 'ps-pen-paper' ); ?></label>
						<input id="pspp-rule-name" name="rules[name]" type="text" value="<?php echo esc_attr( $rules['name'] ); ?>" maxlength="200" required></p>
					<p><label for="pspp-rule-description"><?php esc_html_e( 'Regelbeschreibung und Hausregeln', 'ps-pen-paper' ); ?></label>
						<textarea id="pspp-rule-description" name="rules[description]" rows="4" maxlength="20000"><?php echo esc_textarea( $rules['description'] ); ?></textarea></p>
					<p><label for="pspp-character-mode"><?php esc_html_e( 'Charakterbearbeitung durch Spieler', 'ps-pen-paper' ); ?></label>
						<select id="pspp-character-mode" name="rules[character_mode]">
							<?php foreach ( ps_pen_paper_character_modes() as $mode => $label ) : ?>
								<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( ps_pen_paper_character_mode( $rules ), $mode ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p class="description"><?php esc_html_e( 'Bei Freigabepflicht werden Name und sämtliche Werte erst nach Bestätigung wirksam. Spielleiter dürfen sofort speichern. Ein Moduswechsel genehmigt oder verwirft offene Anträge nicht automatisch.', 'ps-pen-paper' ); ?></p>
					<fieldset>
						<legend><strong><?php esc_html_e( 'Erlaubte Würfel', 'ps-pen-paper' ); ?></strong></legend>
						<div class="pspp-dice">
							<?php foreach ( array( '4', '6', '8', '10', '12', '20', '100' ) as $die ) : ?>
								<label class="pspp-check"><input type="checkbox" name="rules[dice][]" value="<?php echo esc_attr( $die ); ?>" <?php checked( in_array( $die, $rules['dice'], true ) ); ?>> <?php echo esc_html( 'W' . $die ); ?></label>
							<?php endforeach; ?>
						</div>
						<p class="description"><?php esc_html_e( 'Ohne Auswahl sind keine Würfe möglich. Erlaubte Würfel können in Ausdrücken wie 3W6 + 2 kombiniert werden.', 'ps-pen-paper' ); ?></p>
					</fieldset>
					<fieldset>
						<legend><strong><?php esc_html_e( 'Erlaubte Wurfsichtbarkeiten', 'ps-pen-paper' ); ?></strong></legend>
						<input type="hidden" name="rules[roll_visibilities_present]" value="1">
						<?php foreach ( ps_pen_paper_roll_visibility_labels() as $visibility => $label ) : ?>
							<label class="pspp-check"><input type="checkbox" name="rules[roll_visibilities][]" value="<?php echo esc_attr( $visibility ); ?>" <?php checked( in_array( $visibility, ps_pen_paper_allowed_roll_visibilities( $rules ), true ) ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Ohne Auswahl ist Würfeln deaktiviert. „Gruppe“ meint nur Kampagnenmitglieder. Bei verdeckten Proben sehen Spieler auch ihr eigenes Ergebnis nicht. Änderungen gelten nur für neue Würfe; ältere Würfe behalten ihre Sichtbarkeit. Aktuelle Spielleiter und Administratoren dürfen alle Würfe lesen.', 'ps-pen-paper' ); ?></p>
					</fieldset>
					<h3><?php esc_html_e( 'Charakterfelder', 'ps-pen-paper' ); ?></h3>
					<p><?php esc_html_e( 'Bis zu 30 frei benennbare Felder. Umbenennen und Umsortieren behalten die interne Kennung und vorhandene Charakterwerte bei. Punkteverteilung folgt später.', 'ps-pen-paper' ); ?></p>
					<?php if ( $locked ) : ?>
						<p role="note"><?php esc_html_e( 'Zum Schutz vorhandener Charaktere sind Feldliste, Typen, Pflichtstatus und Wertebereiche derzeit gesperrt. Bezeichnungen, Reihenfolge, Startwerte und Bearbeitungsrechte bleiben änderbar. Eine Übernahme struktureller Regeländerungen folgt später.', 'ps-pen-paper' ); ?></p>
					<?php endif; ?>
					<div data-fields>
						<?php foreach ( $rules['fields'] as $index => $field ) : ?>
							<?php ps_pen_paper_render_rule_field( $field, $index, $locked ); ?>
						<?php endforeach; ?>
					</div>
					<template data-field-template>
						<?php ps_pen_paper_render_rule_field( array( 'id' => '', 'label' => '', 'type' => 'text', 'description' => '', 'required' => false ), '__INDEX__' ); ?>
					</template>
					<button type="button" class="button" data-add-field hidden><?php esc_html_e( 'Feld hinzufügen', 'ps-pen-paper' ); ?></button>
					<p class="pspp-status" role="status" aria-live="polite" data-rules-status></p>
					<noscript><p><?php esc_html_e( 'Zum Hinzufügen, Entfernen und Umsortieren von Feldern wird JavaScript benötigt. Vorhandene Felder und die übrigen Einstellungen können ohne JavaScript bearbeitet werden.', 'ps-pen-paper' ); ?></p></noscript>
					<input type="hidden" name="rules_complete" value="complete">
					<?php submit_button( __( 'Regelwerk speichern', 'ps-pen-paper' ) ); ?>
				</div>
			</form>
		<?php endif; ?>
	</section>
	<?php
}
