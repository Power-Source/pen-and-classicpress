<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_character_url( $campaign_id, $character_id, $page_id = 0 ) {
	if ( ps_pen_paper_get_universe( $campaign_id ) ) {
		return ps_pen_paper_universe_url( $campaign_id, $page_id, $character_id );
	}
	if ( $page_id ) {
		return ps_pen_paper_game_url( $page_id, $campaign_id, $character_id );
	}
	return add_query_arg( 'character_id', $character_id, ps_pen_paper_admin_url( $campaign_id ) );
}

function ps_pen_paper_render_character_snapshot( $snapshot, $rules, $campaign = null ) {
	if ( null === $snapshot ) {
		echo '<p>' . esc_html__( 'Noch kein freigegebener Charakterstand.', 'ps-pen-paper' ) . '</p>';
		return;
	}
	?>
	<h4><?php echo esc_html( $snapshot['name'] ); ?></h4>
	<dl class="pspp-character-values">
		<?php foreach ( $rules['fields'] as $field ) : ?>
			<dt><?php echo esc_html( $field['label'] ); ?><?php if ( $campaign && 'pspp_campaign' === $campaign->post_type ) { echo ' '; ps_pen_paper_render_wiki_target_link( $campaign, 'field:' . $field['id'] ); } ?></dt>
			<dd class="pspp-multiline"><?php
				$value = $snapshot['values'][ $field['id'] ];
				echo esc_html( is_array( $value ) ? $value['current'] . ' / ' . $value['maximum'] : $value );
			?></dd>
		<?php endforeach; ?>
	</dl>
	<?php
}

function ps_pen_paper_render_character_form( $campaign, $rules, $character = null, $page_id = 0, $request_campaign = null, $profile_request = false, $player_context = false ) {
	$state = $character ? get_post_meta( $character->ID, '_pspp_character', true ) : array( 'revision' => 0, 'active' => null, 'pending' => null );
	$universe = 'pspp_universe' === $campaign->post_type;
	$requesting = $universe && ( $request_campaign || $profile_request );
	$is_gm = ! $player_context && ( ! $requesting || $profile_request ) && ps_pen_paper_can_manage_character_scope( $campaign );
	$player_creation = ! $character && ( $requesting || ! $is_gm );
	$form_scope = $request_campaign ? $request_campaign : $campaign;
	$element_prefix = $requesting ? 'pspp-universe-request-' . $campaign->ID : 'pspp-character';
	$snapshot = $state['pending'] ? $state['pending'] : $state['active'];
	if ( null === $snapshot ) {
		$snapshot = array( 'name' => '', 'values' => ps_pen_paper_character_values( $rules ) );
	}
	?>
	<h3><?php echo esc_html( $character ? __( 'Charakter bearbeiten', 'ps-pen-paper' ) : __( 'Charakter erstellen', 'ps-pen-paper' ) ); ?></h3>
	<?php if ( $requesting && ! $is_gm ) : ?>
		<p><?php esc_html_e( 'Dieser neue Universumsbogen wird ausschließlich als Antrag gespeichert. Erst die Universumsleitung gibt ihn frei; eine Aufnahme in die Kampagne erfolgt danach gesondert.', 'ps-pen-paper' ); ?></p>
	<?php elseif ( ! $is_gm ) : ?>
		<p><?php esc_html_e( 'Die Rechte stehen an jedem Feld. Direkte Änderungen gelten sofort, Freigabefelder erst nach Bestätigung. Vorhandene Anträge werden durch direkte Änderungen nicht automatisch genehmigt. Ein neuer Bogen mit Freigabefeldern bleibt bis zur Erstfreigabe ein Antrag.', 'ps-pen-paper' ); ?></p>
	<?php elseif ( $state['pending'] ) : ?>
		<p><?php esc_html_e( 'Speichern übernimmt die unten stehenden Werte sofort und ersetzt den offenen Antrag.', 'ps-pen-paper' ); ?></p>
	<?php endif; ?>
	<?php ps_pen_paper_form_start( $profile_request ? 'profile_create_character' : ( $requesting ? 'request_universe_character' : ( $universe ? 'save_base_character' : 'save_character' ) ), 'pspp_campaign_' . $form_scope->ID, $form_scope->ID, $page_id ); ?>
		<?php if ( $requesting ) : ?>
			<input type="hidden" name="universe_id" value="<?php echo esc_attr( $campaign->ID ); ?>">
			<?php $universe_epoch = ps_pen_paper_scope_epoch( $campaign->ID ); ?>
			<?php if ( is_wp_error( $universe_epoch ) ) { ps_pen_paper_fail( $universe_epoch->get_error_message() ); } ?>
			<input type="hidden" name="universe_epoch" value="<?php echo esc_attr( $universe_epoch ); ?>">
		<?php endif; ?>
		<input type="hidden" name="character_id" value="<?php echo esc_attr( $character ? $character->ID : 0 ); ?>">
		<input type="hidden" name="character_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
		<input type="hidden" name="character_rules_revision" value="<?php echo esc_attr( $rules['revision'] ); ?>">
		<?php if ( ! $character && $is_gm && ! $profile_request ) : ?>
			<p><label for="pspp-character-owner"><?php esc_html_e( 'Besitzer', 'ps-pen-paper' ); ?></label>
				<select id="pspp-character-owner" name="owner_id" required>
					<?php
					$members = array_unique( array_merge( array( (string) $campaign->post_author ), get_post_meta( $campaign->ID, '_pspp_gm', false ), get_post_meta( $campaign->ID, '_pspp_player', false ) ) );
					if ( $universe ) { $members = get_users( array( 'blog_id' => get_current_blog_id(), 'fields' => 'ID', 'orderby' => 'display_name' ) ); }
					foreach ( $members as $user_id ) :
						$user = get_userdata( (int) $user_id );
						if ( ! $user || ! is_user_member_of_blog( $user->ID ) || ( $universe && ! user_can( $user, 'read' ) ) ) {
							continue;
						}
						?>
						<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( get_current_user_id(), $user->ID ); ?>><?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php else : ?>
			<input type="hidden" name="owner_id" value="<?php echo esc_attr( $character ? $character->post_author : get_current_user_id() ); ?>">
		<?php endif; ?>
		<p><label for="<?php echo esc_attr( $element_prefix . '-name' ); ?>"><?php esc_html_e( 'Charaktername', 'ps-pen-paper' ); ?></label>
			<input id="<?php echo esc_attr( $element_prefix . '-name' ); ?>" name="character[name]" type="text" maxlength="200" value="<?php echo esc_attr( ! $is_gm && ! $requesting && 'gm_only' === ps_pen_paper_character_mode( $rules ) ? ( $state['active']['name'] ?? '' ) : $snapshot['name'] ); ?>" <?php echo ! $is_gm && ! $requesting && 'gm_only' === ps_pen_paper_character_mode( $rules ) ? 'readonly' : ''; ?> required></p>
		<?php if ( ! empty( $rules['allocation'] ) ) : ?>
			<?php if ( isset( $rules['allocation']['per_level'] ) ) : ?>
				<?php $budget = ps_pen_paper_character_point_budget( $snapshot, $rules ); $spent = array_sum( array_intersect_key( $snapshot['values'], array_flip( $rules['allocation']['fields'] ) ) ); ?>
				<?php /* translators: 1: available points, 2: remaining points. */ $point_message = __( 'Attributpunkte: %1$d insgesamt, %2$d noch zu verteilen. Nicht verteilte Punkte bleiben verfügbar.', 'ps-pen-paper' ); ?>
				<p data-character-allocation data-point-budget="<?php echo esc_attr( $budget ); ?>" data-point-message="<?php echo esc_attr( $point_message ); ?>" data-point-error="<?php esc_attr_e( 'Du hast mehr Attributpunkte verteilt, als dein Level erlaubt.', 'ps-pen-paper' ); ?>"><?php echo esc_html( sprintf( $point_message, $budget, $budget - $spent ) ); ?></p>
			<?php else : ?>
				<p><?php echo esc_html( sprintf( __( 'Die für die Punkteverteilung ausgewählten Attribute müssen zusammen genau %d Punkte ergeben.', 'ps-pen-paper' ), $rules['allocation']['points'] ) ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
		<div class="pspp-grid">
			<?php foreach ( $rules['fields'] as $field ) : ?>
				<?php
				$value = $snapshot['values'][ $field['id'] ];
				$id = $element_prefix . '-' . $field['id'];
				$name = 'character[values][' . $field['id'] . ']';
				$derived = ! empty( $rules['progression'] ) && $field['id'] === $rules['progression']['level_field'];
				$mode = ps_pen_paper_field_edit_mode( $field, $rules );
				$protected = $derived || ( $player_creation && ! ps_pen_paper_character_creation_field_editable( $field, $rules ) ) || ( ! $is_gm && 'gm_only' === $mode );
				if ( $protected && ! $derived ) { $value = $state['active']['values'][ $field['id'] ] ?? ps_pen_paper_character_values( $rules )[ $field['id'] ]; }
				?>
				<fieldset class="pspp-field" <?php echo 'resource' === $field['type'] && ! $protected ? 'data-character-resource' : ''; ?> <?php echo isset( $rules['allocation']['per_level'] ) && in_array( $field['id'], $rules['allocation']['fields'], true ) ? 'data-character-allocation-field' : ''; ?> data-range-message="<?php esc_attr_e( 'Der aktuelle Wert darf das persönliche Maximum nicht überschreiten.', 'ps-pen-paper' ); ?>">
					<legend><?php echo esc_html( $field['label'] ); ?></legend>
					<?php if ( $derived ) : ?><p><?php esc_html_e( 'Wird automatisch aus dem Erfahrungsstand berechnet.', 'ps-pen-paper' ); ?></p>
					<?php elseif ( ! $is_gm ) : ?><p><?php echo esc_html( ps_pen_paper_field_edit_modes()[ $mode ] ); ?></p><?php endif; ?>
					<?php if ( $protected ) : ?>
						<p class="pspp-multiline"><?php echo esc_html( is_array( $value ) ? $value['current'] . ' / ' . $value['maximum'] : $value ); ?></p>
						<?php if ( is_array( $value ) ) : ?>
							<?php foreach ( $value as $key => $number ) : ?><input type="hidden" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $number ); ?>"><?php endforeach; ?>
						<?php else : ?><input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>"><?php endif; ?>
					<?php elseif ( 'choice' === $field['type'] || ! empty( $field['choices'] ) ) : ?>
						<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
						<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" <?php echo $field['required'] ? 'required' : ''; ?>>
							<?php if ( ! $field['required'] ) : ?><option value=""><?php esc_html_e( 'Keine Auswahl', 'ps-pen-paper' ); ?></option><?php endif; ?>
							<?php foreach ( $field['choices'] as $choice ) : ?>
								<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $value, $choice ); ?>><?php echo esc_html( $choice ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php elseif ( in_array( $field['type'], array( 'text', 'textarea' ), true ) ) : ?>
						<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
						<?php if ( 'textarea' === $field['type'] ) : ?>
							<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="4" maxlength="20000" <?php echo $field['required'] ? 'required' : ''; ?>><?php echo esc_textarea( $value ); ?></textarea>
						<?php else : ?>
							<input id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" type="text" value="<?php echo esc_attr( $value ); ?>" maxlength="200" <?php echo $field['required'] ? 'required' : ''; ?>>
						<?php endif; ?>
					<?php else : ?>
						<?php
						$numbers = 'resource' === $field['type']
							? array( 'current' => __( 'Aktueller Wert', 'ps-pen-paper' ), 'maximum' => __( 'Persönliches Maximum', 'ps-pen-paper' ) )
							: array( 'value' => $field['label'] );
						foreach ( $numbers as $key => $label ) :
							$input_name = 'resource' === $field['type'] ? $name . '[' . $key . ']' : $name;
							$number = 'resource' === $field['type'] ? $value[ $key ] : $value;
							?>
							<p><label for="<?php echo esc_attr( $id . '-' . $key ); ?>"><?php echo esc_html( $label ); ?></label>
								<input id="<?php echo esc_attr( $id . '-' . $key ); ?>" name="<?php echo esc_attr( $input_name ); ?>" type="number" step="1" min="<?php echo esc_attr( $field['min'] ); ?>" max="<?php echo esc_attr( $field['max'] ); ?>" value="<?php echo esc_attr( $number ); ?>" data-character-number="<?php echo esc_attr( $key ); ?>" required></p>
						<?php endforeach; ?>
					<?php endif; ?>
					<?php if ( $field['description'] ) : ?>
						<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
					<?php endif; ?>
				</fieldset>
			<?php endforeach; ?>
		</div>
		<input type="hidden" name="character_complete" value="complete">
		<?php submit_button( $requesting && ! $is_gm ? __( 'Zur Freigabe einreichen', 'ps-pen-paper' ) : __( 'Charakter speichern', 'ps-pen-paper' ) ); ?>
	</form>
	<?php
}

function ps_pen_paper_render_characters( $campaign, $character_id = 0, $page_id = 0, $overview_url = '', $item_context = 'game' ) {
	$rules = ps_pen_paper_get_rules( $campaign->ID );
	$profile = 'profile' === $item_context;
	$management = ! $profile && ps_pen_paper_management_view( $page_id );
	?>
	<section id="pspp-characters" class="pspp-rules" aria-labelledby="pspp-characters-heading">
		<h2 id="pspp-characters-heading"><?php echo esc_html( $profile ? __( 'Dein Charakter', 'ps-pen-paper' ) : ( 'pspp_universe' === $campaign->post_type ? __( 'Basischaraktere', 'ps-pen-paper' ) : __( 'Charaktere', 'ps-pen-paper' ) ) ); ?></h2>
		<?php if ( ! $character_id && 'pspp_campaign' === $campaign->post_type ) { ps_pen_paper_render_campaign_base_requests( $campaign, $page_id ); } ?>
		<?php if ( ! $profile && ( ! $character_id || 'pspp_universe' === $campaign->post_type ) ) : ?>
			<p><?php echo esc_html( 'pspp_universe' === $campaign->post_type ? __( 'Eigene Basisbögen können im Profil erstellt werden. Neue Spielerbögen benötigen die Universumsfreigabe; bestehende Änderungen richten sich nach den Feldrechten. Eine Kampagnenaufnahme erfolgt gesondert und ausdrücklich.', 'ps-pen-paper' ) : ps_pen_paper_character_modes()[ ps_pen_paper_character_mode( $rules ) ] ); ?></p>
		<?php endif; ?>
		<?php if ( ! $profile ) : ?><p class="description"><?php esc_html_e( 'Kampagnenbögen sind nur für ihren Besitzer und die Spielleiter sichtbar.', 'ps-pen-paper' ); ?></p><?php endif; ?>
		<?php if ( $character_id ) : ?>
			<?php
			$character = ps_pen_paper_get_character( $campaign, $character_id );
			if ( ! $character ) {
				ps_pen_paper_fail( __( 'Du darfst diesen Charakter nicht ansehen.', 'ps-pen-paper' ) );
			}
			if ( ! $management && (int) $character->post_author !== get_current_user_id() ) {
				echo '<p role="alert">' . esc_html__( 'Fremde Charaktere öffnest du im Bereich Spielleitung.', 'ps-pen-paper' ) . '</p></section>';
				return;
			}
			$state = ps_pen_paper_character_state( $campaign, $character );
			if ( is_wp_error( $state ) ) {
				echo '<p role="alert">' . esc_html( $state->get_error_message() ) . '</p>';
				if ( $management && 'resource_limit_conflict' === $state->get_error_code() && ps_pen_paper_can_manage_campaign( $campaign ) ) {
					$raw = ps_pen_paper_raw_character_state( $character->ID );
					if ( ! is_wp_error( $raw ) && 'shared' === $raw['context']['mode'] ) {
						$base = ps_pen_paper_raw_character_state( $raw['context']['source_id'] );
						if ( is_wp_error( $base ) ) { echo '<p role="alert">' . esc_html( $base->get_error_message() ) . '</p>'; }
						else { $raw['base_revision'] = $base['revision']; }
					}
					if ( ! is_wp_error( $raw ) ) { ps_pen_paper_render_character_context( $campaign, $character, $raw, $page_id ); }
				}
				ps_pen_paper_render_character_equipment( $campaign, $character, $page_id, ! $profile, $item_context );
				echo '</section>';
				return;
			}
			if ( ! $profile && 'pspp_campaign' === $campaign->post_type ) {
				echo '<p>' . esc_html( isset( $state['context'] ) && 'shared' === $state['context']['mode'] ? ps_pen_paper_character_context_modes()['shared'] : ps_pen_paper_character_modes()[ ps_pen_paper_character_mode( $rules ) ] ) . '</p>';
			}
			$owner = get_userdata( (int) $character->post_author );
			?>
			<p class="pspp-navigation"><a href="<?php echo esc_url( $overview_url ? $overview_url : ( 'pspp_universe' === $campaign->post_type ? ps_pen_paper_universe_url( $campaign->ID, $page_id ) : ( $page_id ? ps_pen_paper_game_url( $page_id, $campaign->ID ) : ps_pen_paper_admin_url( $campaign->ID ) ) ) ); ?>"><?php esc_html_e( 'Zur Bogenübersicht', 'ps-pen-paper' ); ?></a></p>
			<p><strong><?php esc_html_e( 'Besitzer:', 'ps-pen-paper' ); ?></strong> <?php echo esc_html( $owner ? $owner->display_name : __( 'Gelöschter Benutzer', 'ps-pen-paper' ) ); ?></p>
			<?php $tab_id = 'pspp-character-' . $character->ID; ?>
			<div class="pspp-character-tabs" data-character-tabs>
			<nav class="pspp-character-tab-nav" aria-label="<?php esc_attr_e( 'Charakterbereiche', 'ps-pen-paper' ); ?>">
				<?php foreach ( array(
					'overview' => __( 'Charakter', 'ps-pen-paper' ), 'stats' => __( 'Werte', 'ps-pen-paper' ),
					'inventory' => __( 'Inventar', 'ps-pen-paper' ), 'adventures' => __( 'Abenteuer', 'ps-pen-paper' ),
					'edit' => __( 'Bearbeiten', 'ps-pen-paper' ),
				) as $tab => $label ) : if ( $profile && 'adventures' === $tab ) { continue; } ?>
					<a id="<?php echo esc_attr( $tab_id . '-tab-' . $tab ); ?>" href="#<?php echo esc_attr( $tab_id . '-' . $tab ); ?>" data-character-tab="<?php echo esc_attr( $tab ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<section id="<?php echo esc_attr( $tab_id . '-overview' ); ?>" data-character-panel="overview">
				<h3><?php echo esc_html( $state['active'] ? $state['active']['name'] : __( 'Noch nicht freigegeben', 'ps-pen-paper' ) ); ?></h3>
				<?php if ( $state['active'] && ! empty( $rules['progression'] ) ) :
					$level_id = $rules['progression']['level_field'];
					$fields = array_column( $rules['fields'], null, 'id' ); ?>
					<p class="pspp-character-level"><strong><?php echo esc_html( $fields[ $level_id ]['label'] . ': ' . $state['active']['values'][ $level_id ] ); ?></strong></p>
				<?php endif; ?>
				<?php if ( $state['pending'] ) : ?><p><?php esc_html_e( 'Freigabe ausstehend – die gültigen Werte bleiben unverändert.', 'ps-pen-paper' ); ?></p><?php endif; ?>
				<?php ps_pen_paper_render_character_loadout( $campaign, $character, $state['active'] ? $state['active']['name'] : __( 'Neu', 'ps-pen-paper' ) ); ?>
				<?php ps_pen_paper_render_portrait_presets( $campaign, $character, $page_id, $profile ); ?>
				<?php ps_pen_paper_render_portrait_presentation( $campaign, $character, $page_id ); ?>
			</section>
			<div id="<?php echo esc_attr( $tab_id . '-stats' ); ?>" class="pspp-grid" data-character-panel="stats">
				<section class="pspp-snapshot pspp-sheet"<?php $sheet_style_result = 'pspp_campaign' === $campaign->post_type ? ps_pen_paper_sheet_attributes( $campaign ) : true; ?>>
					<?php if ( is_wp_error( $sheet_style_result ) ) { echo '<p role="alert">' . esc_html( $sheet_style_result->get_error_message() ) . '</p>'; } ?>
					<h3><?php esc_html_e( 'Gültiger Stand', 'ps-pen-paper' ); ?></h3>
					<?php ps_pen_paper_render_character_snapshot( $state['active'], $rules, $campaign ); ?>
					<?php if ( isset( $state['equipment'] ) && $state['equipment']['bonuses'] ) : ?><p><?php esc_html_e( 'Angezeigte Werte enthalten freigegebene Ausrüstungsboni.', 'ps-pen-paper' ); ?></p><?php endif; ?>
				</section>
				<?php if ( $state['pending'] ) : ?>
					<section class="pspp-snapshot">
						<h3><?php esc_html_e( 'Offener Antrag', 'ps-pen-paper' ); ?></h3>
						<?php ps_pen_paper_render_character_snapshot( $state['pending'], $rules, $campaign ); ?>
						<?php if ( $management && ps_pen_paper_can_manage_character_scope( $campaign ) ) : ?>
							<?php ps_pen_paper_form_start( 'pspp_universe' === $campaign->post_type ? 'review_base_character' : 'review_character', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
								<input type="hidden" name="character_id" value="<?php echo esc_attr( $character_id ); ?>">
								<input type="hidden" name="character_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
								<div class="pspp-actions">
									<?php if ( empty( $state['base_request_stale'] ) ) : ?>
										<button class="button button-primary" name="decision" value="approve"><?php esc_html_e( 'Freigeben', 'ps-pen-paper' ); ?></button>
									<?php else : ?>
										<p role="alert"><?php esc_html_e( 'Der Basisbogen wurde seit der Anfrage geändert. Bitte lehne den Antrag ab; das Mitglied kann die Anfrage erneut stellen.', 'ps-pen-paper' ); ?></p>
									<?php endif; ?>
									<button class="button" name="decision" value="reject"><?php esc_html_e( 'Antrag verwerfen', 'ps-pen-paper' ); ?></button>
								</div>
							</form>
						<?php endif; ?>
					</section>
				<?php endif; ?>
			</div>
			<section id="<?php echo esc_attr( $tab_id . '-inventory' ); ?>" data-character-panel="inventory">
				<?php ps_pen_paper_render_character_equipment( $campaign, $character, $page_id, false, $item_context ); ?>
			</section>
			<?php if ( ! $profile ) : ?><section id="<?php echo esc_attr( $tab_id . '-adventures' ); ?>" data-character-panel="adventures">
				<?php ps_pen_paper_render_character_adventures( $campaign, $character, $page_id ); ?>
			</section><?php endif; ?>
			<section id="<?php echo esc_attr( $tab_id . '-edit' ); ?>" data-character-panel="edit">
			<?php ps_pen_paper_render_portrait_form( $campaign, $character, $page_id, $profile ); ?>
			<?php if ( ! $profile && 'pspp_campaign' === $campaign->post_type ) { ps_pen_paper_render_character_context( $campaign, $character, $state, $page_id ); } ?>
			<?php if ( isset( $state['context'] ) && 'shared' === $state['context']['mode'] ) : ?>
				<p><?php esc_html_e( 'Dieser Bogen folgt dem freigegebenen Basisstand. Grundwerte ändert nur die Universumsleitung; die Kampagnenleitung kann getrennte Auflagen setzen.', 'ps-pen-paper' ); ?></p>
			<?php elseif ( isset( $state['context'] ) ) : ?>
				<p><?php esc_html_e( 'Das Bearbeitungsformular ändert die unabhängigen Grundwerte dieser Kampagnenkopie. Getrennte Auflagen bleiben dabei bestehen.', 'ps-pen-paper' ); ?></p>
			<?php endif; ?>
			<?php if ( ps_pen_paper_can_edit_character( $campaign, $character ) ) : ?>
				<?php if ( $page_id || $overview_url ) : ?><details class="pspp-fold"><summary><?php esc_html_e( 'Bogen bearbeiten', 'ps-pen-paper' ); ?></summary><?php endif; ?>
				<?php ps_pen_paper_render_character_form( $campaign, $rules, $character, $page_id, null, false, ! $management ); ?>
				<?php if ( $page_id || $overview_url ) : ?></details><?php endif; ?>
			<?php endif; ?>
			</section></div>
		<?php else : ?>
			<?php $characters = ps_pen_paper_get_characters( $campaign ); ?>
			<?php if ( ! $characters ) : ?>
				<p><?php esc_html_e( 'Noch keine sichtbaren Charaktere vorhanden.', 'ps-pen-paper' ); ?></p>
			<?php else : ?>
				<ul class="pspp-character-list">
					<?php foreach ( $characters as $character ) : ?>
						<?php
						$state = ps_pen_paper_character_state( $campaign, $character );
						if ( is_wp_error( $state ) ) {
							echo '<li role="alert"><a href="' . esc_url( ps_pen_paper_character_url( $campaign->ID, $character->ID, $page_id ) ) . '">' .
								esc_html__( 'Charakter öffnen', 'ps-pen-paper' ) . '</a>: ' . esc_html( $state->get_error_message() ) . '</li>';
							continue;
						}
						$snapshot = $state['active'] ? $state['active'] : $state['pending'];
						?>
						<li><a href="<?php echo esc_url( ps_pen_paper_character_url( $campaign->ID, $character->ID, $page_id ) ); ?>"><?php echo esc_html( $snapshot ? $snapshot['name'] : __( 'Charakter ohne freigegebenen Stand', 'ps-pen-paper' ) ); ?></a>
							<?php if ( $state['pending'] ) : ?>
								<strong><?php esc_html_e( 'Freigabe ausstehend', 'ps-pen-paper' ); ?></strong>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( 0 === $rules['revision'] ) : ?>
				<p><?php esc_html_e( 'Vor dem ersten Charakter muss ein Spielleiter das Regelwerk speichern.', 'ps-pen-paper' ); ?></p>
			<?php elseif ( ps_pen_paper_can_edit_character( $campaign ) ) : ?>
				<?php ps_pen_paper_render_character_form( $campaign, $rules, null, $page_id ); ?>
			<?php endif; ?>
		<?php endif; ?>
	</section>
	<?php
}
