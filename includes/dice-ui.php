<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_render_die( $sides, $value = '' ) {
	$shapes = array(
		4   => array( 'M50 6L94 88H6Z', 'M50 6V63L6 88M50 63L94 88' ),
		6   => array( 'M50 5L91 28V73L50 96L9 73V28Z', 'M9 28L50 50L91 28M50 50V96' ),
		8   => array( 'M50 4L95 50L50 96L5 50Z', 'M5 50H95M50 4L28 50L50 96L72 50Z' ),
		10  => array( 'M50 4L91 35L80 82L50 96L20 82L9 35Z', 'M50 4V65L20 82M50 65L80 82M9 35L50 65L91 35' ),
		12  => array( 'M38 5H62L82 18L95 38V62L82 82L62 95H38L18 82L5 62V38L18 18Z', 'M50 21L78 42L67 75H33L22 42ZM50 21V5M78 42L95 38M67 75L82 82M33 75L18 82M22 42L5 38' ),
		20  => array( 'M50 4L91 27V73L50 96L9 73V27Z', 'M50 4L28 65L91 27L9 27L72 65ZM9 73L28 65L50 96L72 65L91 73M28 65H72' ),
		100 => array( 'M50 4L91 35L80 82L50 96L20 82L9 35Z', 'M50 4V65L20 82M50 65L80 82M9 35L50 65L91 35' ),
	);
	?>
	<span class="pspp-die-visual" aria-hidden="true">
		<svg viewBox="0 0 100 100" focusable="false">
			<path class="pspp-die-shell" d="<?php echo esc_attr( $shapes[ $sides ][0] ); ?>"></path>
			<path class="pspp-die-facets" d="<?php echo esc_attr( $shapes[ $sides ][1] ); ?>"></path>
			<?php if ( '' !== $value ) : ?>
				<circle class="pspp-die-center" cx="50" cy="54" r="19"></circle>
			<?php endif; ?>
		</svg>
		<?php if ( '' !== $value ) : ?><span class="pspp-die-number"><?php echo esc_html( $value ); ?></span><?php endif; ?>
	</span>
	<?php
}

function ps_pen_paper_roll_description( $event ) {
	$description = $event['expression'] . ( $event['attribute'] ? ' + ' . $event['attribute']['label'] . ' (' . $event['attribute']['value'] . ')' : '' );
	if ( isset( $event['mechanic'] ) ) {
		$description .= ' · ' . ps_pen_paper_dice_mechanic_labels()[ $event['mechanic'] ];
		if ( 'pool' === $event['mechanic'] ) { $description .= ' · ' . __( 'Erfolg ab:', 'ps-pen-paper' ) . ' ' . $event['threshold']; }
	}
	return $description;
}

function ps_pen_paper_render_roll_details( $event ) {
	?>
	<ul>
		<?php if ( isset( $event['mechanic'] ) ) : ?><li><?php echo esc_html( ps_pen_paper_roll_description( $event ) ); ?></li><?php endif; ?>
		<?php foreach ( $event['groups'] as $group ) : ?>
			<li><?php echo esc_html( count( $group['rolls'] ) . 'W' . $group['sides'] . ': ' . implode( ', ', $group['rolls'] ) . ( isset( $event['mechanic'] ) ? '' : ' = ' . array_sum( $group['rolls'] ) ) ); ?></li>
		<?php endforeach; ?>
		<li><?php echo esc_html( __( 'Modifikator:', 'ps-pen-paper' ) . ' ' . $event['modifier'] ); ?></li>
		<?php if ( $event['character'] ) : ?>
			<li><?php echo esc_html( __( 'Charakter:', 'ps-pen-paper' ) . ' ' . $event['character']['name'] ); ?></li>
			<?php if ( isset( $event['character']['equipment'] ) ) : ?>
				<li><?php
				/* translators: 1: Inventory revision, 2: Slot schema revision. */
				echo esc_html( sprintf( __( 'Ausrüstungsrevision %1$d · Slotrevision %2$d', 'ps-pen-paper' ), $event['character']['equipment']['revision'], $event['character']['equipment']['schema_revision'] ) );
				if ( $event['attribute'] && isset( $event['character']['equipment']['bonuses'][ $event['attribute']['id'] ] ) ) {
					echo ' · ' . esc_html( __( 'Enthaltener Ausrüstungsbonus:', 'ps-pen-paper' ) . ' ' . $event['character']['equipment']['bonuses'][ $event['attribute']['id'] ] );
				}
				?></li>
			<?php endif; ?>
			<?php if ( isset( $event['character']['context'] ) ) :
				$context = $event['character']['context'];
				?>
				<li><?php echo esc_html( ps_pen_paper_character_context_modes()[ $context['mode'] ] ); ?></li>
				<li><?php
				/* translators: 1: Base character ID, 2: Source revision, 3: Campaign character revision. */
				echo esc_html( sprintf( __( 'Basisbogen #%1$d · Basisrevision %2$d · Kontextrevision %3$d', 'ps-pen-paper' ), $context['source_id'], $context['source_revision'], $context['revision'] ) );
				?></li>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( $event['attribute'] ) : ?>
			<li><?php echo esc_html( $event['attribute']['label'] . ': ' . $event['attribute']['value'] ); ?></li>
		<?php endif; ?>
		<li><strong><?php echo esc_html( __( 'Gesamtergebnis:', 'ps-pen-paper' ) . ' ' . $event['total'] ); ?></strong></li>
	</ul>
	<?php
}

function ps_pen_paper_render_dice( $campaign, $page_id = 0, $with_journal = true ) {
	$rules = ps_pen_paper_get_rules( $campaign->ID );
	$labels = ps_pen_paper_roll_visibility_labels();
	$allowed = ps_pen_paper_allowed_roll_visibilities( $rules );
	$mechanics = ps_pen_paper_allowed_dice_mechanics( $rules );
	$selected = ps_pen_paper_frontend_id( 'pspp_roll' );
	$log_page = ps_pen_paper_frontend_id( 'pspp_log_page' );
	$preferred_visibility = isset( $_GET['pspp_notice'] ) && 'roll_hidden_saved' === $_GET['pspp_notice'] ? 'gm' : ( $allowed ? $allowed[0] : '' );
	?>
	<section id="pspp-dice" class="pspp-rules" aria-labelledby="pspp-dice-heading" data-dice-controller>
		<h2 id="pspp-dice-heading"><?php esc_html_e( 'Würfelbühne', 'ps-pen-paper' ); ?></h2>
		<p><?php esc_html_e( 'Ergebnisse werden auf dem Server gewürfelt und vor der Anzeige gespeichert. Die Animation verändert kein Ergebnis.', 'ps-pen-paper' ); ?></p>
		<?php if ( is_wp_error( $selected ) || is_wp_error( $log_page ) ) : ?>
			<p role="alert"><?php esc_html_e( 'Ungültige Wurfauswahl oder Protokollseite.', 'ps-pen-paper' ); ?></p>
		<?php elseif ( $selected ) : ?>
			<?php $event = ps_pen_paper_get_roll( $campaign, $selected ); ?>
			<?php if ( is_wp_error( $event ) ) : ?>
				<p role="alert"><?php echo esc_html( $event->get_error_message() ); ?></p>
			<?php else : ?>
				<?php $preferred_visibility = $event['visibility']; ?>
				<div class="pspp-dice-result pspp-card">
					<h3 tabindex="-1" data-roll-heading><?php esc_html_e( 'Gespeichertes Ergebnis', 'ps-pen-paper' ); ?></h3>
					<p><?php echo esc_html( ps_pen_paper_roll_description( $event ) ); ?></p>
					<p><?php echo esc_html( $labels[ $event['visibility'] ] ); ?></p>
					<div class="pspp-dice-stage" data-dice-stage aria-hidden="true">
						<?php
						$shown = 0;
						$count = 0;
						foreach ( $event['groups'] as $group ) :
							$count += count( $group['rolls'] );
							foreach ( $group['rolls'] as $value ) :
								if ( $shown >= 12 ) { break; }
								?>
								<span class="pspp-die" style="<?php echo esc_attr( '--pspp-die-index:' . $shown ); ?>">
									<?php ps_pen_paper_render_die( $group['sides'], $value ); ?>
									<span><?php echo esc_html( 'W' . $group['sides'] ); ?></span>
								</span>
								<?php $shown++; ?>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</div>
					<?php if ( $count > 12 ) : ?>
						<p><?php echo esc_html( sprintf( __( '%d weitere Würfel stehen in den Einzelwerten.', 'ps-pen-paper' ), $count - 12 ) ); ?></p>
					<?php endif; ?>
					<p class="pspp-roll-total"><?php esc_html_e( 'Gesamt', 'ps-pen-paper' ); ?> <output aria-label="<?php esc_attr_e( 'Gesamtergebnis', 'ps-pen-paper' ); ?>" data-roll-total><?php echo esc_html( $event['total'] ); ?></output></p>
					<details><summary><?php esc_html_e( 'Einzelwürfe und Modifikatoren', 'ps-pen-paper' ); ?></summary>
						<?php ps_pen_paper_render_roll_details( $event ); ?>
					</details>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( $rules['dice'] && $allowed && $mechanics ) : ?>
			<div class="pspp-dice-quick" data-dice-quick hidden>
				<label for="pspp-dice-count"><?php esc_html_e( 'Anzahl für die Schnellwahl', 'ps-pen-paper' ); ?></label>
				<input id="pspp-dice-count" type="number" min="1" max="100" step="1" value="1" required data-dice-count>
				<div class="pspp-dice-choices">
					<?php foreach ( $rules['dice'] as $die ) : ?>
						<button type="button" class="button pspp-die-choice" data-choose-die="<?php echo esc_attr( $die ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'W%s wählen', 'ps-pen-paper' ), $die ) ); ?>">
							<?php ps_pen_paper_render_die( (int) $die ); ?>
							<span><?php echo esc_html( 'W' . $die ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
				<p class="description"><?php esc_html_e( 'Die Schnellwahl ersetzt den Ausdruck. Kombinationen kannst du darunter direkt eingeben.', 'ps-pen-paper' ); ?></p>
			</div>
			<?php ps_pen_paper_form_start( 'roll_dice', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
				<input type="hidden" name="roll_rules_revision" value="<?php echo esc_attr( $rules['revision'] ); ?>">
				<p><label for="pspp-roll-expression"><?php esc_html_e( 'Würfelausdruck', 'ps-pen-paper' ); ?></label>
					<input id="pspp-roll-expression" type="text" name="roll[expression]" value="<?php echo esc_attr( '1W' . $rules['dice'][0] ); ?>" maxlength="200" required aria-describedby="pspp-roll-help" data-dice-expression></p>
				<p id="pspp-roll-help" class="description"><?php esc_html_e( 'Zum Beispiel 3W6 + 2 oder W20 + 2W6 - 1. Höchstens 10 Gruppen und insgesamt 100 Würfel; Modifikator bis ±1000000. W und D sind erlaubt.', 'ps-pen-paper' ); ?></p>
				<p><label for="pspp-roll-mechanic"><?php esc_html_e( 'Würfelmechanik', 'ps-pen-paper' ); ?></label>
					<select id="pspp-roll-mechanic" name="roll[mechanic]">
						<?php foreach ( $mechanics as $mechanic ) : ?><option value="<?php echo esc_attr( $mechanic ); ?>"><?php echo esc_html( ps_pen_paper_dice_mechanic_labels()[ $mechanic ] ); ?></option><?php endforeach; ?>
					</select></p>
				<?php if ( in_array( 'pool', $mechanics, true ) ) : ?>
					<p><label for="pspp-roll-threshold"><?php esc_html_e( 'Erfolgsschwelle (nur Würfelpool)', 'ps-pen-paper' ); ?></label>
						<input id="pspp-roll-threshold" name="roll[threshold]" type="number" min="1" max="100" step="1" value="1"></p>
				<?php endif; ?>
				<?php if ( array( 'sum' ) !== $mechanics ) : ?><p class="description"><?php esc_html_e( 'Pool, bestes und schlechtestes Ergebnis verwenden genau eine Würfelgruppe. Beim Pool zählt jeder Würfel ab der Schwelle als ein Erfolg; Modifikatoren und Attributaddition sind dort nicht erlaubt. Bestes/schlechtestes Ergebnis meint den höchsten/niedrigsten Einzelwürfel, danach werden Modifikator und gewählter Attributwert addiert.', 'ps-pen-paper' ); ?></p><?php endif; ?>
				<div class="pspp-grid">
					<p><label for="pspp-roll-character"><?php esc_html_e( 'Für Charakter', 'ps-pen-paper' ); ?></label>
						<select id="pspp-roll-character" name="roll[character]">
							<option value=""><?php esc_html_e( 'Ohne Charakter', 'ps-pen-paper' ); ?></option>
							<?php foreach ( ps_pen_paper_get_characters( $campaign ) as $character ) : ?>
								<?php
								$state = ps_pen_paper_character_state( $campaign, $character );
								if ( is_wp_error( $state ) ) {
									echo '<option value="" disabled>' . esc_html( $state->get_error_message() ) . '</option>';
									continue;
								}
								?>
								<?php if ( ! $state['active'] || '' === ps_pen_paper_campaign_role( $campaign, (int) $character->post_author ) || ! is_user_member_of_blog( (int) $character->post_author ) ) { continue; } ?>
								<option value="<?php echo esc_attr( ps_pen_paper_character_token( $character, $state ) ); ?>"><?php echo esc_html( $state['active']['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p><label for="pspp-roll-attribute"><?php esc_html_e( 'Attributwert addieren', 'ps-pen-paper' ); ?></label>
						<select id="pspp-roll-attribute" name="roll[attribute]" data-attribute-error="<?php esc_attr_e( 'Wähle für den Attributwert zuerst einen Charakter.', 'ps-pen-paper' ); ?>">
							<option value=""><?php esc_html_e( 'Keinen Wert addieren', 'ps-pen-paper' ); ?></option>
							<?php foreach ( $rules['fields'] as $field ) : ?>
								<?php if ( in_array( $field['type'], array( 'number', 'attribute' ), true ) ) : ?>
									<option value="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( $field['label'] ); ?></option>
								<?php endif; ?>
							<?php endforeach; ?>
						</select>
					</p>
				</div>
				<p class="description"><?php esc_html_e( 'Verwendet wird der freigegebene Zahlenwert, kein automatisch berechneter Systembonus. Gruppenwürfe teilen auch gewählten Charakternamen und Attributwert mit der Gruppe.', 'ps-pen-paper' ); ?></p>
				<p><label for="pspp-roll-visibility"><?php esc_html_e( 'Sichtbarkeit', 'ps-pen-paper' ); ?></label>
					<select id="pspp-roll-visibility" name="roll[visibility]" required>
						<?php if ( ! in_array( $preferred_visibility, $allowed, true ) ) : ?>
							<option value="" selected><?php esc_html_e( 'Bisherige Sichtbarkeit nicht mehr erlaubt – bitte neu wählen', 'ps-pen-paper' ); ?></option>
						<?php endif; ?>
						<?php foreach ( $allowed as $visibility ) : ?>
							<option value="<?php echo esc_attr( $visibility ); ?>" <?php selected( $preferred_visibility, $visibility ); ?>><?php echo esc_html( $labels[ $visibility ] ); ?></option>
						<?php endforeach; ?>
					</select></p>
				<p class="description"><?php esc_html_e( 'Bei verdeckten Proben erhalten Spieler nur eine Speicherbestätigung, kein Ergebnis.', 'ps-pen-paper' ); ?></p>
				<p><label for="pspp-roll-label"><?php esc_html_e( 'Anlass (optional)', 'ps-pen-paper' ); ?></label>
					<input id="pspp-roll-label" type="text" name="roll[label]" maxlength="200"></p>
				<input type="hidden" name="roll_complete" value="complete">
				<?php submit_button( __( 'Jetzt würfeln', 'ps-pen-paper' ) ); ?>
				<p role="status" data-roll-progress data-message="<?php esc_attr_e( 'Der Wurf wird gewürfelt und gespeichert …', 'ps-pen-paper' ); ?>"></p>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'Wähle im Regelwerk mindestens einen erlaubten Würfel, eine Würfelmechanik und eine Sichtbarkeit, um zu würfeln.', 'ps-pen-paper' ); ?></p>
		<?php endif; ?>
		<?php if ( $with_journal ) { ps_pen_paper_render_journal( $campaign, $page_id ); } ?>
	</section>
	<?php
}
