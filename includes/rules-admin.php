<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_render_rule_field( $field, $index, $locked = false, $policy = null ) {
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
		<p><label for="<?php echo esc_attr( $element_id . '-edit-mode' ); ?>"><?php esc_html_e( 'Bearbeitungsrecht', 'ps-pen-paper' ); ?></label>
			<select id="<?php echo esc_attr( $element_id . '-edit-mode' ); ?>" name="<?php echo esc_attr( $prefix . '[edit_mode]' ); ?>">
				<?php foreach ( ps_pen_paper_field_edit_modes() as $mode => $label ) : ?>
					<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $field['edit_mode'] ?? 'inherit', $mode ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select></p>
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
		<div data-choice-settings <?php echo 'choice' !== $field['type'] ? 'hidden' : ''; ?>>
			<p>
				<label for="<?php echo esc_attr( $element_id . '-choices' ); ?>"><?php esc_html_e( 'Auswahlmöglichkeiten (eine pro Zeile)', 'ps-pen-paper' ); ?></label>
				<textarea id="<?php echo esc_attr( $element_id . '-choices' ); ?>" name="<?php echo esc_attr( $prefix . '[choices]' ); ?>" rows="4" maxlength="10000" data-choice-options><?php echo esc_textarea( isset( $field['choices'] ) ? implode( "\n", $field['choices'] ) : '' ); ?></textarea>
			</p>
			<p>
				<label for="<?php echo esc_attr( $element_id . '-choice-start' ); ?>"><?php esc_html_e( 'Vorauswahl für neue Charaktere und Datenmigration', 'ps-pen-paper' ); ?></label>
				<input id="<?php echo esc_attr( $element_id . '-choice-start' ); ?>" name="<?php echo esc_attr( $prefix . '[start]' ); ?>" type="text" value="<?php echo esc_attr( isset( $field['start'] ) ? $field['start'] : '' ); ?>" maxlength="200">
			</p>
		</div>
		<div data-text-settings <?php echo in_array( $field['type'], array( 'text', 'textarea' ), true ) ? '' : 'hidden'; ?>>
			<p>
				<label for="<?php echo esc_attr( $element_id . '-allowed-choices' ); ?>"><?php esc_html_e( 'Erlaubte Werte für kurzen Text (optional, einer pro Zeile)', 'ps-pen-paper' ); ?></label>
				<textarea id="<?php echo esc_attr( $element_id . '-allowed-choices' ); ?>" name="<?php echo esc_attr( $prefix . '[allowed_choices]' ); ?>" rows="4" maxlength="10000"><?php echo esc_textarea( isset( $field['choices'] ) ? implode( "\n", $field['choices'] ) : '' ); ?></textarea>
				<span class="description"><?php esc_html_e( 'Leer bedeutet freie Eingabe. Name und Bedeutung des Feldes bestimmst du selbst, etwa Klasse oder Herkunft. Änderungen verwendeter Felder benötigen eine Migrationsvorschau.', 'ps-pen-paper' ); ?></span>
			</p>
			<p>
				<label for="<?php echo esc_attr( $element_id . '-text-start' ); ?>"><?php esc_html_e( 'Vorgabewert für neue Charaktere und Datenmigration', 'ps-pen-paper' ); ?></label>
				<textarea id="<?php echo esc_attr( $element_id . '-text-start' ); ?>" name="<?php echo esc_attr( $prefix . '[start]' ); ?>" rows="2" maxlength="<?php echo 'text' === $field['type'] ? '200' : '20000'; ?>"><?php echo esc_textarea( isset( $field['start'] ) ? $field['start'] : '' ); ?></textarea>
			</p>
		</div>
		<?php if ( $locked && $field['required'] ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $prefix . '[required]' ); ?>" value="1">
		<?php endif; ?>
		<p><label class="pspp-check"><input type="checkbox" name="<?php echo esc_attr( $prefix . '[required]' ); ?>" value="1" <?php checked( $field['required'] ); ?> <?php disabled( $locked ); ?>> <?php esc_html_e( 'Pflichtfeld', 'ps-pen-paper' ); ?></label></p>
		<?php if ( null !== $policy && '' !== $field['id'] ) :
			$setting = $policy['fields'][ $field['id'] ] ?? null;
			$policy_prefix = 'rules[campaign_policy][fields][' . $field['id'] . ']';
			?>
			<fieldset>
				<legend><?php esc_html_e( 'Vorgabe für Kampagnen', 'ps-pen-paper' ); ?></legend>
				<label class="pspp-check"><input type="checkbox" name="<?php echo esc_attr( $policy_prefix . '[enabled]' ); ?>" value="1" <?php checked( null !== $setting ); ?>> <?php esc_html_e( 'Dieses Feld darf kampagnenindividuell angepasst werden', 'ps-pen-paper' ); ?></label>
				<?php if ( in_array( $field['type'], array( 'number', 'attribute', 'resource' ), true ) ) : ?>
					<?php foreach ( array( 'min' => __( 'Untergrenze für Kampagnen', 'ps-pen-paper' ), 'max' => __( 'Obergrenze für Kampagnen', 'ps-pen-paper' ) ) as $key => $label ) : ?>
						<p><label><?php echo esc_html( $label ); ?> <input type="number" step="1" min="-1000000" max="1000000" name="<?php echo esc_attr( $policy_prefix . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $setting[ $key ] ?? '' ); ?>"></label></p>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Leer lässt die jeweilige Grenze offen. Die Universumswerte müssen innerhalb der angegebenen Grenzen liegen.', 'ps-pen-paper' ); ?></p>
				<?php endif; ?>
			</fieldset>
		<?php endif; ?>
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
	$policy = $rules['campaign_policy'] ?? ps_pen_paper_inheritance_policy_default();
	$locked = false;
	$used_sheets = ps_pen_paper_has_characters( $campaign->ID );
	$inheritance = 'pspp_campaign' === $campaign->post_type ? ps_pen_paper_inheritance_source( $campaign->ID ) : null;
	$legacy_preview = null;
	if ( $can_manage && 'pspp_campaign' === $campaign->post_type && null === $inheritance ) {
		$link = ps_pen_paper_get_universe_link( $campaign->ID );
		if ( ! is_wp_error( $link ) && $link['universe_id'] && ps_pen_paper_can_manage_universe( ps_pen_paper_get_universe( $link['universe_id'] ) ) &&
			$rules['revision'] && ! metadata_exists( 'post', $campaign->ID, '_pspp_play_round' ) ) {
			$legacy_preview = ps_pen_paper_preview_legacy_inheritance( $campaign->ID );
		}
	}
	?>
	<section class="pspp-rules" aria-labelledby="pspp-rules-heading">
		<h2 id="pspp-rules-heading"><?php esc_html_e( 'Regelwerk und Charakterbogen-Vorlage', 'ps-pen-paper' ); ?></h2>
		<?php if ( $can_manage && 'pspp_universe' === $campaign->post_type && ps_pen_paper_pending_inherited_campaigns( $campaign->ID ) ) : ?>
			<p role="alert"><?php esc_html_e( 'Mindestens eine verknüpfte Kampagne konnte die aktuellen Universumsregeln noch nicht übernehmen. Ihre Spielleitung kann die ausstehende Änderung im Kampagnenregelwerk prüfen.', 'ps-pen-paper' ); ?></p>
		<?php endif; ?>
		<?php if ( is_wp_error( $inheritance ) ) : ?>
			<p role="alert"><?php echo esc_html( $inheritance->get_error_message() ); ?></p>
		<?php elseif ( $inheritance && $inheritance['link']['rules_base'] !== $inheritance['rules'] ) : ?>
			<p role="alert"><?php esc_html_e( 'Das Universumsregelwerk wurde geändert. Bis zur Übernahme können keine neuen Spiele gestartet werden; laufende Runden bleiben unverändert.', 'ps-pen-paper' ); ?></p>
			<?php if ( $can_manage ) : ?>
				<p><?php esc_html_e( 'Prüfe die Übernahme. Bei widersprechenden Kampagnenanpassungen kannst du zunächst die bisherigen flexiblen Vorgaben zurücksetzen und erneut prüfen.', 'ps-pen-paper' ); ?></p>
				<?php ps_pen_paper_form_start( 'sync_inherited_rules', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
					<?php submit_button( __( 'Universumsänderungen prüfen', 'ps-pen-paper' ) ); ?>
				</form>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( $can_manage && is_array( $inheritance ) ) :
			$base_policy = $inheritance['link']['rules_base']['campaign_policy'] ?? ps_pen_paper_inheritance_policy_default();
			?>
			<details>
				<summary><?php esc_html_e( 'Universumsvorgaben für diese Kampagne', 'ps-pen-paper' ); ?></summary>
				<ul>
					<?php foreach ( ps_pen_paper_inheritance_globals() as $key => $label ) : ?>
						<li><?php echo esc_html( $label ); ?>: <?php echo esc_html( in_array( $key, $base_policy['globals'], true ) ? __( 'flexibel', 'ps-pen-paper' ) : __( 'fest', 'ps-pen-paper' ) ); ?></li>
					<?php endforeach; ?>
					<?php foreach ( $inheritance['link']['rules_base']['fields'] as $field ) : ?>
						<li><?php echo esc_html( $field['label'] ); ?>: <?php echo esc_html( isset( $base_policy['fields'][ $field['id'] ] ) ? __( 'flexibel', 'ps-pen-paper' ) : __( 'fest', 'ps-pen-paper' ) ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p><?php echo esc_html( $base_policy['extra_fields'] ? __( 'Zusätzliche Kampagnenfelder sind erlaubt.', 'ps-pen-paper' ) : __( 'Zusätzliche Kampagnenfelder sind nicht freigegeben.', 'ps-pen-paper' ) ); ?></p>
			</details>
		<?php endif; ?>
		<?php if ( $legacy_preview ) : ?>
			<details>
				<summary><?php esc_html_e( 'Bestehendes Regelwerk mit dem Universum verbinden', 'ps-pen-paper' ); ?></summary>
				<?php if ( is_wp_error( $legacy_preview ) ) : ?>
					<p role="alert"><?php echo esc_html( $legacy_preview->get_error_message() ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Die aktuellen Kampagnenregeln erfüllen die Universumsvorgaben. Charaktere und Feldwerte bleiben unverändert. Künftige Universumsänderungen werden unter Beachtung der freigegebenen Anpassungen übernommen oder bei Konflikten zur Prüfung vorgelegt.', 'ps-pen-paper' ); ?></p>
					<?php ps_pen_paper_form_start( 'activate_legacy_inheritance', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
						<input type="hidden" name="inheritance_preview_hash" value="<?php echo esc_attr( $legacy_preview['hash'] ); ?>">
						<p><label class="pspp-check"><input type="checkbox" name="inheritance_confirmation" value="confirmed" required> <?php esc_html_e( 'Ich bestätige die Verbindung mit dem aktuellen Universumsregelwerk und dessen festen und flexiblen Vorgaben.', 'ps-pen-paper' ); ?></label></p>
						<?php submit_button( __( 'Regelvererbung aktivieren', 'ps-pen-paper' ) ); ?>
					</form>
				<?php endif; ?>
			</details>
		<?php endif; ?>
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
			<p><?php echo esc_html( 'pspp_universe' === $campaign->post_type ? __( 'Diese Vorlage gilt für Basisbögen und wird von neu angelegten Kampagnen übernommen. Änderungen werden für verknüpfte Kampagnen geprüft; bei Konflikten bleibt die Übernahme bis zur Klärung ausstehend. Basisbögen bleiben ausschließlich durch die Universumsleitung bearbeitbar.', 'ps-pen-paper' ) : __( 'Diese Einstellungen gelten für diese Kampagne und ihre Charaktere und Würfe. Bei vererbten Regeln dürfen nur die vom Universum freigegebenen Vorgaben angepasst werden.', 'ps-pen-paper' ) ); ?></p>
			<?php ps_pen_paper_form_start( 'pspp_universe' === $campaign->post_type ? 'save_universe_rules' : 'save_rules', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
				<div data-rules-editor data-limit="30" data-structure-locked="<?php echo $locked ? 'true' : 'false'; ?>"
					data-limit-message="<?php esc_attr_e( 'Es sind höchstens 30 Felder möglich.', 'ps-pen-paper' ); ?>"
					data-range-message="<?php esc_attr_e( 'Der Startwert muss zwischen Minimum und Maximum liegen.', 'ps-pen-paper' ); ?>"
					data-remove-message="<?php esc_attr_e( 'Dieses Feld aus der Vorlage entfernen? Erst beim Speichern wird die Änderung übernommen.', 'ps-pen-paper' ); ?>"
					data-unsaved-message="<?php esc_attr_e( 'Es gibt ungespeicherte Änderungen.', 'ps-pen-paper' ); ?>"
					data-change-message="<?php esc_attr_e( 'Feldliste geändert. Bitte speichere das Regelwerk.', 'ps-pen-paper' ); ?>">
					<input type="hidden" name="rules_revision" value="<?php echo esc_attr( $rules['revision'] ); ?>">
					<?php if ( 'pspp_universe' === $campaign->post_type ) : ?>
						<fieldset>
							<legend><strong><?php esc_html_e( 'Feste und flexible Universumsregeln', 'ps-pen-paper' ); ?></strong></legend>
							<p><?php esc_html_e( 'Nicht ausgewählte Vorgaben sind fest. Ausgewählte Vorgaben dürfen Kampagnenleitungen anpassen. Zusätzliche Felder benötigen eine eigene Freigabe.', 'ps-pen-paper' ); ?></p>
							<input type="hidden" name="rules[campaign_policy_present]" value="1">
							<label class="pspp-check"><input type="checkbox" name="rules[campaign_policy][extra_fields]" value="1" <?php checked( $policy['extra_fields'] ); ?>> <?php esc_html_e( 'Zusätzliche kampagneneigene Charakterfelder erlauben', 'ps-pen-paper' ); ?></label>
							<?php foreach ( ps_pen_paper_inheritance_globals() as $key => $label ) : ?>
								<label class="pspp-check"><input type="checkbox" name="rules[campaign_policy][globals][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $policy['globals'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
							<?php endforeach; ?>
						</fieldset>
					<?php endif; ?>
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
					<p class="description"><?php esc_html_e( 'Diese Vorgabe gilt für den Namen und für Felder ohne eigenes Bearbeitungsrecht. Jedes Feld kann stattdessen direkt, mit Freigabe oder nur durch die Spielleitung bearbeitbar sein. Direkte Änderungen wirken sofort; beantragte Änderungen bleiben getrennt. Neue Bögen mit Freigabefeldern benötigen eine vollständige Erstfreigabe. Ein Moduswechsel genehmigt oder verwirft offene Anträge nicht automatisch. Gemeinsame Basisbögen bleiben leitungsverwaltet.', 'ps-pen-paper' ); ?></p>
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
						<legend><strong><?php esc_html_e( 'Erlaubte Würfelmechaniken', 'ps-pen-paper' ); ?></strong></legend>
						<input type="hidden" name="rules[dice_mechanics_present]" value="1">
						<?php foreach ( ps_pen_paper_dice_mechanic_labels() as $mechanic => $label ) : ?>
							<label class="pspp-check"><input type="checkbox" name="rules[dice_mechanics][]" value="<?php echo esc_attr( $mechanic ); ?>" <?php checked( in_array( $mechanic, ps_pen_paper_allowed_dice_mechanics( $rules ), true ) ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Ohne Auswahl sind keine Würfe möglich. Neue Mechaniken gelten nur für die Würfelbühne; bestehende Abenteuerproben und Beutetabellen behalten ihre Würfelsummen.', 'ps-pen-paper' ); ?></p>
					</fieldset>
					<fieldset>
						<legend><strong><?php esc_html_e( 'Erlaubte Wurfsichtbarkeiten', 'ps-pen-paper' ); ?></strong></legend>
						<input type="hidden" name="rules[roll_visibilities_present]" value="1">
						<?php foreach ( ps_pen_paper_roll_visibility_labels() as $visibility => $label ) : ?>
							<label class="pspp-check"><input type="checkbox" name="rules[roll_visibilities][]" value="<?php echo esc_attr( $visibility ); ?>" <?php checked( in_array( $visibility, ps_pen_paper_allowed_roll_visibilities( $rules ), true ) ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Ohne Auswahl ist Würfeln deaktiviert. „Gruppe“ meint nur Kampagnenmitglieder. Bei verdeckten Proben sehen Spieler auch ihr eigenes Ergebnis nicht. Änderungen gelten nur für neue Würfe; ältere Würfe behalten ihre Sichtbarkeit. Aktuelle Spielleiter und Administratoren dürfen alle Würfe lesen.', 'ps-pen-paper' ); ?></p>
					</fieldset>
					<fieldset>
						<legend><strong><?php esc_html_e( 'Optionale Erfahrung und Stufen', 'ps-pen-paper' ); ?></strong></legend>
						<p><label for="pspp-progression-mode"><?php esc_html_e( 'Stufenberechnung', 'ps-pen-paper' ); ?></label>
							<select id="pspp-progression-mode" name="rules[progression_mode]">
								<option value="none" <?php selected( empty( $rules['progression'] ) ); ?>><?php esc_html_e( 'Nicht verwenden', 'ps-pen-paper' ); ?></option>
								<option value="configured" <?php selected( ! empty( $rules['progression'] ) ); ?>><?php esc_html_e( 'Aus Erfahrung und Schwellen berechnen', 'ps-pen-paper' ); ?></option>
							</select></p>
						<?php foreach ( array( 'experience_field' => __( 'Erfahrungsfeld', 'ps-pen-paper' ), 'level_field' => __( 'Stufenfeld', 'ps-pen-paper' ) ) as $key => $label ) : ?>
							<p><label for="<?php echo esc_attr( 'pspp-progression-' . $key ); ?>"><?php echo esc_html( $label ); ?></label>
								<select id="<?php echo esc_attr( 'pspp-progression-' . $key ); ?>" name="<?php echo esc_attr( 'rules[progression][' . $key . ']' ); ?>">
									<option value=""><?php esc_html_e( 'Feld wählen', 'ps-pen-paper' ); ?></option>
									<?php foreach ( $rules['fields'] as $field ) : if ( ! in_array( $field['type'], array( 'number', 'attribute' ), true ) ) { continue; } ?>
										<option value="<?php echo esc_attr( $field['id'] ); ?>" <?php selected( $rules['progression'][ $key ] ?? '', $field['id'] ); ?>><?php echo esc_html( $field['label'] ); ?></option>
									<?php endforeach; ?>
								</select></p>
						<?php endforeach; ?>
						<p><label for="pspp-progression-thresholds"><?php esc_html_e( 'Schwellen: Erfahrung : Stufe, eine Zeile je Schwelle', 'ps-pen-paper' ); ?></label>
							<textarea id="pspp-progression-thresholds" name="rules[progression][thresholds]" rows="5" maxlength="20000"><?php
								$thresholds = array();
								foreach ( $rules['progression']['thresholds'] ?? array() as $row ) { $thresholds[] = $row['experience'] . ' : ' . $row['level']; }
								echo esc_textarea( implode( "\n", $thresholds ) );
							?></textarea></p>
						<p class="description"><?php esc_html_e( 'Beispiel bei Erfahrungsminimum 0: „0 : 1“, „100 : 2“, „300 : 3“. Speichere die beiden Felder zuerst. Erfahrung und Stufen müssen aufsteigend sein und innerhalb der Feldgrenzen liegen. Das Stufenfeld wird automatisch aus dem jeweiligen Erfahrungsstand berechnet, auch im Antrag; keine Klassen oder Attributboni werden vergeben. Änderungen an vorhandenen Bögen benötigen eine bestätigte Vorschau. Beim Ausschalten bleiben erreichte Werte erhalten.', 'ps-pen-paper' ); ?></p>
					</fieldset>
					<h3><?php esc_html_e( 'Charakterfelder', 'ps-pen-paper' ); ?></h3>
					<p><?php esc_html_e( 'Bis zu 30 frei benennbare Felder. Auswahlfelder enthalten je eine Option pro Zeile. Strukturänderungen an verwendeten Bögen benötigen vor dem Speichern eine Vorschau und eine ausdrückliche Lösung für jeden Konflikt.', 'ps-pen-paper' ); ?></p>
					<fieldset>
						<legend><strong><?php esc_html_e( 'Optionale Attribut-Punkteverteilung', 'ps-pen-paper' ); ?></strong></legend>
						<p><label for="pspp-allocation-mode"><?php esc_html_e( 'Punkteverteilung', 'ps-pen-paper' ); ?></label>
							<select id="pspp-allocation-mode" name="rules[allocation_mode]">
								<option value="none" <?php selected( empty( $rules['allocation'] ) ); ?>><?php esc_html_e( 'Nicht verwenden', 'ps-pen-paper' ); ?></option>
								<option value="configured" <?php selected( ! empty( $rules['allocation'] ) && ! isset( $rules['allocation']['per_level'] ) ); ?>><?php esc_html_e( 'Feste Gesamtpunktzahl', 'ps-pen-paper' ); ?></option>
								<option value="level" <?php selected( isset( $rules['allocation']['per_level'] ) ); ?>><?php esc_html_e( 'Startpunkte und Punkte je Level', 'ps-pen-paper' ); ?></option>
							</select></p>
						<p><label for="pspp-allocation-points"><?php esc_html_e( 'Startpunkte / feste Gesamtpunktzahl', 'ps-pen-paper' ); ?></label>
							<input id="pspp-allocation-points" name="rules[allocation][points]" type="number" step="1" min="-1000000" max="1000000" value="<?php echo esc_attr( ! empty( $rules['allocation'] ) ? $rules['allocation']['points'] : 0 ); ?>"></p>
						<p><label for="pspp-allocation-per-level"><?php esc_html_e( 'Zusätzliche Punkte je Levelaufstieg', 'ps-pen-paper' ); ?></label>
							<input id="pspp-allocation-per-level" name="rules[allocation][per_level]" type="number" step="1" min="0" max="1000000" value="<?php echo esc_attr( $rules['allocation']['per_level'] ?? 0 ); ?>"></p>
						<?php foreach ( $rules['fields'] as $field ) : if ( 'attribute' !== $field['type'] ) { continue; } ?>
							<label class="pspp-check"><input type="checkbox" name="rules[allocation][fields][]" value="<?php echo esc_attr( $field['id'] ); ?>" <?php checked( ! empty( $rules['allocation'] ) && in_array( $field['id'], $rules['allocation']['fields'], true ) ); ?>> <?php echo esc_html( $field['label'] ); ?></label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Bei „Startpunkte und Punkte je Level“ gilt: Startpunkte + (Level − 1) × Punkte je Aufstieg. Nicht verteilte Punkte bleiben verfügbar. Erfahrung vergibt die Leitung; Spieler verteilen nur ihr verfügbares Budget auf die ausgewählten Attribute. Ressourcen und andere Zahlenwerte werden bei der Erstellung vorgegeben. Die feste Gesamtpunktzahl bleibt eine exakte Summe.', 'ps-pen-paper' ); ?></p>
					</fieldset>
					<div data-fields>
						<?php foreach ( $rules['fields'] as $index => $field ) : ?>
							<?php ps_pen_paper_render_rule_field( $field, $index, $locked, 'pspp_universe' === $campaign->post_type ? $policy : null ); ?>
						<?php endforeach; ?>
					</div>
					<template data-field-template>
						<?php ps_pen_paper_render_rule_field( array( 'id' => '', 'label' => '', 'type' => 'text', 'description' => '', 'required' => false ), '__INDEX__' ); ?>
					</template>
					<button type="button" class="button" data-add-field hidden><?php esc_html_e( 'Feld hinzufügen', 'ps-pen-paper' ); ?></button>
					<p class="pspp-status" role="status" aria-live="polite" data-rules-status></p>
					<noscript><p><?php esc_html_e( 'Zum Hinzufügen, Entfernen und Umsortieren von Feldern wird JavaScript benötigt. Vorhandene Felder und die übrigen Einstellungen können ohne JavaScript bearbeitet werden.', 'ps-pen-paper' ); ?></p></noscript>
					<input type="hidden" name="rules_complete" value="complete">
					<?php if ( $used_sheets ) : ?>
						<button type="submit" class="button" name="pspp_schema_preview" value="1"><?php esc_html_e( 'Vorschau der Feldänderungen', 'ps-pen-paper' ); ?></button>
					<?php endif; ?>
					<?php submit_button( __( 'Regelwerk speichern', 'ps-pen-paper' ) ); ?>
				</div>
			</form>
		<?php endif; ?>
	</section>
	<?php
}

function ps_pen_paper_show_rules_migration_preview( $scope, $input, $revision, $page_id = 0, $inherited = false ) {
	if ( ! $scope ) { ps_pen_paper_fail( __( 'Die Regelwerksvorlage ist nicht mehr verfügbar.', 'ps-pen-paper' ) ); }
	$preview = ps_pen_paper_preview_rules_migration( $scope->ID, $input, $revision, $inherited );
	if ( is_wp_error( $preview ) ) { ps_pen_paper_fail( $preview->get_error_message() ); }
	ob_start();
	ps_pen_paper_render_rules_migration_preview( $scope, $preview, $page_id );
	$html = ob_get_clean();
	wp_die( $html, __( 'Vorschau der Charakterbogen-Änderungen', 'ps-pen-paper' ), array( 'response' => 200 ) );
	exit;
}

function ps_pen_paper_render_rules_migration_preview( $campaign, $preview, $page_id = 0 ) {
	if ( ! $campaign || ! is_array( $preview ) || (int) $preview['scope_id'] !== (int) $campaign->ID ) {
		echo '<p role="alert">' . esc_html__( 'Die Regelwerksvorschau ist nicht verfügbar. Bitte lade das Formular neu.', 'ps-pen-paper' ) . '</p>';
		return;
	}
	$rules = $preview['after'];
	?>
	<section class="pspp-card" aria-labelledby="pspp-migration-heading">
		<h3 id="pspp-migration-heading"><?php esc_html_e( 'Vorschau der Charakterbogen-Änderungen', 'ps-pen-paper' ); ?></h3>
		<p><?php esc_html_e( 'Aktive und offene Charakterstände werden getrennt migriert. Kompatible Werte bleiben erhalten. Für jeden Konflikt musst du ausdrücklich bestätigen, dass der angezeigte Vorgabewert verwendet oder ein entferntes Feld gelöscht wird. Die Migration ändert keine Ausrüstung; sie wird abgebrochen, wenn Ausrüstungsboni oder Bereiche danach nicht mehr passen.', 'ps-pen-paper' ); ?></p>
		<?php if ( ! $preview['characters'] ) : ?><p><?php esc_html_e( 'Es sind keine gespeicherten Charakterstände betroffen.', 'ps-pen-paper' ); ?></p><?php endif; ?>
		<?php foreach ( $preview['characters'] as $row ) : ?>
			<section>
				<h4><?php echo esc_html( sprintf( __( 'Charakter #%1$d · %2$s', 'ps-pen-paper' ), $row['character_id'], 'active' === $row['slot'] ? __( 'freigegeben', 'ps-pen-paper' ) : __( 'offener Antrag', 'ps-pen-paper' ) ) ); ?></h4>
				<?php foreach ( $row['conflicts'] as $conflict ) : ?>
					<p><?php echo esc_html( $conflict['field_label'] ); ?>:
						<?php if ( 'removed' === $conflict['reason'] ) : ?>
							<?php esc_html_e( 'Wert aus der alten Feldstruktur entfernen', 'ps-pen-paper' ); ?>
						<?php elseif ( 'allocation' === $conflict['reason'] ) : ?>
							<?php esc_html_e( 'Attributwerte auf die neue Punktverteilung zurücksetzen', 'ps-pen-paper' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Den bisherigen Wert durch den neuen Vorgabewert ersetzen', 'ps-pen-paper' ); ?>
						<?php endif; ?>
						<?php if ( null !== $conflict['old_value'] ) : ?>
							<br><?php esc_html_e( 'Bisher:', 'ps-pen-paper' ); ?> <?php echo esc_html( is_array( $conflict['old_value'] ) ? wp_json_encode( $conflict['old_value'], JSON_UNESCAPED_UNICODE ) : (string) $conflict['old_value'] ); ?>
						<?php endif; ?>
						<?php if ( null !== $conflict['default'] ) : ?>
							<br><?php esc_html_e( 'Neu:', 'ps-pen-paper' ); ?> <?php echo esc_html( is_array( $conflict['default'] ) ? wp_json_encode( $conflict['default'], JSON_UNESCAPED_UNICODE ) : (string) $conflict['default'] ); ?>
						<?php endif; ?>
					</p>
				<?php endforeach; ?>
				<?php if ( ! $row['conflicts'] ) : ?><p><?php esc_html_e( 'Alle vorhandenen Feldwerte sind kompatibel und bleiben erhalten.', 'ps-pen-paper' ); ?></p><?php endif; ?>
			</section>
		<?php endforeach; ?>
		<?php ps_pen_paper_form_start( 'pspp_universe' === $campaign->post_type ? 'save_universe_rules' : 'save_rules_migration', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<?php if ( 'pspp_universe' === $campaign->post_type ) : ?><input type="hidden" name="rules_migration" value="1"><?php endif; ?>
			<?php if ( isset( $preview['inheritance'] ) ) : ?><input type="hidden" name="rules_inheritance" value="1"><?php endif; ?>
			<input type="hidden" name="rules_revision" value="<?php echo esc_attr( $preview['before']['revision'] ); ?>">
			<input type="hidden" name="migration_preview_hash" value="<?php echo esc_attr( $preview['hash'] ); ?>">
			<input type="hidden" name="rules_complete" value="complete">
			<input type="hidden" name="rules[name]" value="<?php echo esc_attr( $rules['name'] ); ?>">
			<input type="hidden" name="rules[description]" value="<?php echo esc_attr( $rules['description'] ); ?>">
			<input type="hidden" name="rules[character_mode]" value="<?php echo esc_attr( ps_pen_paper_character_mode( $rules ) ); ?>">
			<?php if ( 'pspp_universe' === $campaign->post_type && array_key_exists( 'campaign_policy', $rules ) ) : ?>
				<input type="hidden" name="rules[campaign_policy_present]" value="1">
				<?php if ( $rules['campaign_policy']['extra_fields'] ) : ?><input type="hidden" name="rules[campaign_policy][extra_fields]" value="1"><?php endif; ?>
				<?php foreach ( $rules['campaign_policy']['globals'] as $key ) : ?><input type="hidden" name="rules[campaign_policy][globals][]" value="<?php echo esc_attr( $key ); ?>"><?php endforeach; ?>
				<?php foreach ( $rules['campaign_policy']['fields'] as $id => $bounds ) : ?>
					<input type="hidden" name="<?php echo esc_attr( 'rules[campaign_policy][fields][' . $id . '][enabled]' ); ?>" value="1">
					<?php foreach ( $bounds as $key => $value ) : ?><input type="hidden" name="<?php echo esc_attr( 'rules[campaign_policy][fields][' . $id . '][' . $key . ']' ); ?>" value="<?php echo esc_attr( null === $value ? '' : $value ); ?>"><?php endforeach; ?>
				<?php endforeach; ?>
			<?php endif; ?>
			<?php if ( array_key_exists( 'dice_mechanics', $rules ) ) : ?>
				<input type="hidden" name="rules[dice_mechanics_present]" value="1">
				<?php foreach ( ps_pen_paper_allowed_dice_mechanics( $rules ) as $mechanic ) : ?><input type="hidden" name="rules[dice_mechanics][]" value="<?php echo esc_attr( $mechanic ); ?>"><?php endforeach; ?>
			<?php endif; ?>
			<?php if ( array_key_exists( 'progression', $rules ) ) : ?>
				<input type="hidden" name="rules[progression_mode]" value="<?php echo esc_attr( empty( $rules['progression'] ) ? 'none' : 'configured' ); ?>">
			<?php endif; ?>
			<?php if ( ! empty( $rules['progression'] ) ) : ?>
				<input type="hidden" name="rules[progression][experience_field]" value="<?php echo esc_attr( $rules['progression']['experience_field'] ); ?>">
				<input type="hidden" name="rules[progression][level_field]" value="<?php echo esc_attr( $rules['progression']['level_field'] ); ?>">
				<?php foreach ( $rules['progression']['thresholds'] as $index => $row ) : ?>
					<?php foreach ( $row as $key => $number ) : ?><input type="hidden" name="<?php echo esc_attr( 'rules[progression][thresholds][' . $index . '][' . $key . ']' ); ?>" value="<?php echo esc_attr( $number ); ?>"><?php endforeach; ?>
				<?php endforeach; ?>
			<?php endif; ?>
			<input type="hidden" name="rules[roll_visibilities_present]" value="1">
			<?php foreach ( $rules['dice'] as $die ) : ?><input type="hidden" name="rules[dice][]" value="<?php echo esc_attr( $die ); ?>"><?php endforeach; ?>
			<?php foreach ( ps_pen_paper_allowed_roll_visibilities( $rules ) as $visibility ) : ?><input type="hidden" name="rules[roll_visibilities][]" value="<?php echo esc_attr( $visibility ); ?>"><?php endforeach; ?>
			<input type="hidden" name="rules[allocation_mode]" value="<?php echo esc_attr( empty( $rules['allocation'] ) ? 'none' : ( isset( $rules['allocation']['per_level'] ) ? 'level' : 'configured' ) ); ?>">
			<?php if ( ! empty( $rules['allocation'] ) ) : ?>
				<input type="hidden" name="rules[allocation][points]" value="<?php echo esc_attr( $rules['allocation']['points'] ); ?>">
				<?php if ( isset( $rules['allocation']['per_level'] ) ) : ?><input type="hidden" name="rules[allocation][per_level]" value="<?php echo esc_attr( $rules['allocation']['per_level'] ); ?>"><?php endif; ?>
				<?php foreach ( $rules['allocation']['fields'] as $field_id ) : ?><input type="hidden" name="rules[allocation][fields][]" value="<?php echo esc_attr( $field_id ); ?>"><?php endforeach; ?>
			<?php endif; ?>
			<?php foreach ( $rules['fields'] as $index => $field ) : ?>
				<input type="hidden" name="<?php echo esc_attr( 'rules[fields][' . $index . '][id]' ); ?>" value="<?php echo esc_attr( $field['id'] ); ?>">
				<input type="hidden" name="<?php echo esc_attr( 'rules[fields][' . $index . '][label]' ); ?>" value="<?php echo esc_attr( $field['label'] ); ?>">
				<input type="hidden" name="<?php echo esc_attr( 'rules[fields][' . $index . '][type]' ); ?>" value="<?php echo esc_attr( $field['type'] ); ?>">
				<input type="hidden" name="<?php echo esc_attr( 'rules[fields][' . $index . '][description]' ); ?>" value="<?php echo esc_attr( $field['description'] ); ?>">
				<input type="hidden" name="<?php echo esc_attr( 'rules[fields][' . $index . '][edit_mode]' ); ?>" value="<?php echo esc_attr( $field['edit_mode'] ?? 'inherit' ); ?>">
				<?php if ( $field['required'] ) : ?><input type="hidden" name="<?php echo esc_attr( 'rules[fields][' . $index . '][required]' ); ?>" value="1"><?php endif; ?>
				<?php if ( in_array( $field['type'], array( 'number', 'attribute', 'resource' ), true ) ) : ?>
					<?php foreach ( array( 'min', 'max', 'start' ) as $key ) : ?><input type="hidden" name="<?php echo esc_attr( 'rules[fields][' . $index . '][' . $key . ']' ); ?>" value="<?php echo esc_attr( $field[ $key ] ); ?>"><?php endforeach; ?>
				<?php elseif ( 'choice' === $field['type'] || ! empty( $field['choices'] ) ) : ?>
					<?php foreach ( $field['choices'] as $choice ) : ?><input type="hidden" name="<?php echo esc_attr( 'rules[fields][' . $index . '][choices][]' ); ?>" value="<?php echo esc_attr( $choice ); ?>"><?php endforeach; ?>
					<input type="hidden" name="<?php echo esc_attr( 'rules[fields][' . $index . '][start]' ); ?>" value="<?php echo esc_attr( $field['start'] ); ?>">
				<?php else : ?>
					<input type="hidden" name="<?php echo esc_attr( 'rules[fields][' . $index . '][start]' ); ?>" value="<?php echo esc_attr( $field['start'] ); ?>">
				<?php endif; ?>
			<?php endforeach; ?>
			<?php foreach ( $preview['characters'] as $row ) : ?>
				<?php foreach ( $row['conflicts'] as $conflict ) : ?>
					<input type="hidden" name="<?php echo esc_attr( 'resolutions[' . $conflict['token'] . ']' ); ?>" value="use_default">
				<?php endforeach; ?>
			<?php endforeach; ?>
			<p><label class="pspp-check"><input type="checkbox" name="migration_confirmation" value="confirmed" required><?php esc_html_e( 'Ich bestätige jede oben aufgeführte Konfliktlösung und die Migration beider Charakterstände.', 'ps-pen-paper' ); ?></label></p>
			<?php submit_button( __( 'Vorschau migrieren und Regelwerk speichern', 'ps-pen-paper' ) ); ?>
		</form>
	</section>
	<?php
}
