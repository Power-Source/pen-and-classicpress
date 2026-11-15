<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_universe_url( $id, $page_id = 0, $character_id = 0 ) {
	$args = array( 'pspp_universe' => $id );
	if ( $character_id ) { $args['pspp_base_character'] = $character_id; }
	return add_query_arg( $args, ps_pen_paper_dashboard_url( 'universes', $page_id ) );
}

function ps_pen_paper_handle_character_context_action( $operation ) {
	$page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	if ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) {
		ps_pen_paper_fail( __( 'Die Spielbereich-Seite ist nicht mehr verfügbar.', 'ps-pen-paper' ) );
	}
	$id = ps_pen_paper_posted_id( 'campaign_id' );
	check_admin_referer( 'pspp_campaign_' . $id );
	$url = ps_pen_paper_universe_url( $id, $page_id );
	if ( 'save_universe_rules' === $operation ) {
		if ( 'complete' !== ps_pen_paper_posted_text( 'rules_complete' ) || ! ps_pen_paper_get_universe( $id ) ) {
			ps_pen_paper_fail( __( 'Die Universumsvorlage wurde nicht vollständig übertragen.', 'ps-pen-paper' ) );
		}
		$result = ps_pen_paper_save_rules( $id, isset( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : null, isset( $_POST['rules_revision'] ) ? $_POST['rules_revision'] : null );
	} elseif ( 'save_base_character' === $operation ) {
		if ( 'complete' !== ps_pen_paper_posted_text( 'character_complete' ) || ! ps_pen_paper_get_universe( $id ) ) {
			ps_pen_paper_fail( __( 'Der Basisbogen wurde nicht vollständig übertragen.', 'ps-pen-paper' ) );
		}
		$result = ps_pen_paper_save_character( $id, ps_pen_paper_posted_id( 'character_id' ), ps_pen_paper_posted_id( 'owner_id' ),
			isset( $_POST['character'] ) ? wp_unslash( $_POST['character'] ) : null,
			isset( $_POST['character_revision'] ) ? $_POST['character_revision'] : null,
			isset( $_POST['character_rules_revision'] ) ? $_POST['character_rules_revision'] : null );
		if ( ! is_wp_error( $result ) ) { $url = ps_pen_paper_universe_url( $id, $page_id, $result ); }
	} elseif ( 'copy_shared_character' === $operation ) {
		$character_id = ps_pen_paper_posted_id( 'character_id' );
		$result = ps_pen_paper_copy_shared_character( $id, $character_id,
			isset( $_POST['character_revision'] ) ? $_POST['character_revision'] : null,
			isset( $_POST['base_revision'] ) ? $_POST['base_revision'] : null,
			isset( $_POST['character_rules_revision'] ) ? $_POST['character_rules_revision'] : null,
			ps_pen_paper_posted_text( 'context_confirmation' ) );
		$url = ps_pen_paper_character_url( $id, $character_id, $page_id );
	} elseif ( 'save_character_context' === $operation ) {
		if ( 'complete' !== ps_pen_paper_posted_text( 'context_complete' ) ) {
			ps_pen_paper_fail( __( 'Die Kampagnenauflagen wurden nicht vollständig übertragen.', 'ps-pen-paper' ) );
		}
		$character_id = ps_pen_paper_posted_id( 'character_id' );
		$result = ps_pen_paper_save_character_context( $id, $character_id,
			isset( $_POST['character_revision'] ) ? $_POST['character_revision'] : null,
			isset( $_POST['base_revision'] ) ? $_POST['base_revision'] : null,
			isset( $_POST['character_rules_revision'] ) ? $_POST['character_rules_revision'] : null,
			isset( $_POST['overrides'] ) ? wp_unslash( $_POST['overrides'] ) : array(), isset( $_POST['context_note'] ) ? wp_unslash( $_POST['context_note'] ) : null );
		$url = ps_pen_paper_character_url( $id, $character_id, $page_id );
	} else {
		$confirmation = ps_pen_paper_posted_text( 'context_confirmation' );
		if ( 'adopt_universe_rules' === $operation ) {
			$result = ps_pen_paper_adopt_universe_rules( $id, isset( $_POST['source_rules_revision'] ) ? $_POST['source_rules_revision'] : null, $confirmation );
			$link = ps_pen_paper_get_universe_link( $id );
			if ( ! is_wp_error( $link ) ) { $url = ps_pen_paper_universe_url( $link['universe_id'], $page_id ); }
		} else {
			$token = ps_pen_paper_posted_text( 'base_character' );
			if ( ! preg_match( '/^([1-9]\d*):([1-9]\d*)$/D', $token, $parts ) || (string) (int) $parts[1] !== $parts[1] || (string) (int) $parts[2] !== $parts[2] ) {
				ps_pen_paper_fail( __( 'Bitte wähle einen gültigen Basisbogen.', 'ps-pen-paper' ) );
			}
			$result = ps_pen_paper_attach_base_character( $id, (int) $parts[1], $parts[2],
				isset( $_POST['target_rules_revision'] ) ? $_POST['target_rules_revision'] : null,
				ps_pen_paper_posted_text( 'context_mode' ), $confirmation );
			if ( ! is_wp_error( $result ) ) { $url = ps_pen_paper_character_url( $id, $result, $page_id ); }
		}
	}
	if ( is_wp_error( $result ) ) {
		ps_pen_paper_fail( $result->get_error_message() );
	}
	wp_safe_redirect( add_query_arg( 'pspp_notice', 'context_saved', $url ) );
	exit;
}

function ps_pen_paper_render_universe_characters( $universe, $page_id ) {
	if ( ! ps_pen_paper_can_manage_universe( $universe ) ) {
		echo '<p role="alert">' . esc_html__( 'Dieses Universum ist für dich nicht verfügbar.', 'ps-pen-paper' ) . '</p>';
		return;
	}
	$document = ps_pen_paper_universe_document( $universe->ID );
	$character_id = ps_pen_paper_frontend_id( 'pspp_base_character' );
	if ( is_wp_error( $document ) || is_wp_error( $character_id ) ) {
		echo '<p role="alert">' . esc_html__( 'Das Universum oder der gewählte Basisbogen ist nicht verfügbar.', 'ps-pen-paper' ) . '</p>';
		return;
	}
	?>
	<h2><?php echo esc_html( $document['name'] ); ?></h2>
	<p><a href="<?php echo esc_url( ps_pen_paper_dashboard_url( 'universes', $page_id ) ); ?>"><?php esc_html_e( 'Zur Universumsübersicht', 'ps-pen-paper' ); ?></a></p>
	<p><?php esc_html_e( 'Die Universumsleitung verwaltet hier Regelvorlage und Basisbögen. Besitzer sind Website-Mitglieder; eine Kampagnenteilnahme wird dadurch nicht vergeben. Gemeinsame Basisstände werden nur hier geändert, Kampagnenkopien dagegen in ihrer jeweiligen Runde.', 'ps-pen-paper' ); ?></p>
	<?php if ( $character_id ) : ?>
		<?php ps_pen_paper_render_characters( $universe, $character_id, $page_id ); ?>
	<?php else : ?>
		<details class="pspp-fold">
			<summary><?php esc_html_e( 'Universumsregelwerk bearbeiten', 'ps-pen-paper' ); ?></summary>
			<?php ps_pen_paper_render_rules( $universe, true, $page_id ); ?>
		</details>
		<?php ps_pen_paper_render_characters( $universe, 0, $page_id ); ?>
		<section id="pspp-settings"><?php ps_pen_paper_render_currency_settings( $universe, $page_id ); ?></section>
		<?php ps_pen_paper_render_trade_policy( $universe, $page_id ); ?>
		<section id="pspp-equipment"><?php ps_pen_paper_render_equipment_catalogue( $universe, $page_id ); ?></section>
		<?php ps_pen_paper_render_npcs( $universe, $page_id ); ?>
		<?php ps_pen_paper_render_wiki( $universe, $page_id ); ?>
		<h3><?php esc_html_e( 'In eine Kampagne übernehmen', 'ps-pen-paper' ); ?></h3>
		<p><?php esc_html_e( 'Nur bereits aufgenommene Besitzer können mitspielen. Zuerst übernimmt eine leere Kampagne ausdrücklich die Regelvorlage mit denselben Feldkennungen; bestehende Regelwerke werden nicht überschrieben.', 'ps-pen-paper' ); ?></p>
		<?php
		$rules = ps_pen_paper_get_rules( $universe->ID );
		$bases = ps_pen_paper_get_characters( $universe );
		foreach ( ps_pen_paper_get_campaigns() as $campaign ) :
			$link = ps_pen_paper_get_universe_link( $campaign->ID );
			if ( is_wp_error( $link ) || $link['universe_id'] !== $universe->ID || ! ps_pen_paper_can_manage_campaign( $campaign ) ) { continue; }
			$target = ps_pen_paper_get_rules( $campaign->ID );
			?>
			<section class="pspp-card">
				<h4><?php echo esc_html( $campaign->post_title ); ?></h4>
				<?php if ( ! $rules['revision'] ) : ?>
					<p><?php esc_html_e( 'Zuerst das Universumsregelwerk speichern.', 'ps-pen-paper' ); ?></p>
				<?php elseif ( ! $target['revision'] ) : ?>
					<?php ps_pen_paper_form_start( 'adopt_universe_rules', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
						<input type="hidden" name="source_rules_revision" value="<?php echo esc_attr( $rules['revision'] ); ?>">
						<p><label class="pspp-check"><input type="checkbox" name="context_confirmation" value="confirmed" required>
							<?php esc_html_e( 'Die aktuelle Universumsvorlage als eigenständiges Kampagnenregelwerk übernehmen. Spätere Änderungen werden nicht automatisch synchronisiert.', 'ps-pen-paper' ); ?></label></p>
						<?php submit_button( __( 'Regelvorlage übernehmen', 'ps-pen-paper' ) ); ?>
					</form>
				<?php elseif ( ps_pen_paper_character_schema( $rules ) !== ps_pen_paper_character_schema( $target ) ) : ?>
					<p><?php esc_html_e( 'Das bestehende Kampagnenregelwerk hat andere Feldkennungen oder Wertebereiche. Eine automatische Migration ist zum Schutz der Bögen gesperrt.', 'ps-pen-paper' ); ?></p>
				<?php else : ?>
					<?php ps_pen_paper_form_start( 'attach_base_character', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
						<input type="hidden" name="target_rules_revision" value="<?php echo esc_attr( $target['revision'] ); ?>">
						<p><label><?php esc_html_e( 'Freigegebener Basisbogen', 'ps-pen-paper' ); ?>
							<select name="base_character" required>
								<option value=""><?php esc_html_e( 'Basisbogen auswählen', 'ps-pen-paper' ); ?></option>
								<?php foreach ( $bases as $base ) :
									$state = ps_pen_paper_character_state( $universe, $base );
									if ( is_wp_error( $state ) ) {
										echo '<option value="" disabled>' . esc_html( $state->get_error_message() ) . '</option>';
										continue;
									}
									if ( ! $state['active'] || ! ps_pen_paper_campaign_role( $campaign, (int) $base->post_author ) || ! is_user_member_of_blog( (int) $base->post_author ) ) { continue; }
									?>
									<option value="<?php echo esc_attr( ps_pen_paper_character_token( $base, $state ) ); ?>"><?php echo esc_html( $state['active']['name'] ); ?></option>
								<?php endforeach; ?>
							</select></label></p>
						<p><label><?php esc_html_e( 'Fortschritt in dieser Kampagne', 'ps-pen-paper' ); ?>
							<select name="context_mode"><?php foreach ( ps_pen_paper_character_context_modes() as $mode => $label ) : ?><option value="<?php echo esc_attr( $mode ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label></p>
						<p><?php esc_html_e( 'Gemeinsam: Freigegebene Basisänderungen erscheinen auch hier; Bearbeitung nur durch die Universumsleitung. Kopie: Der aktuelle freigegebene Stand wird unabhängig weitergeführt. Offene Basisanträge werden nie übernommen. Die Kampagnenleitung kann einen gemeinsamen Bogen später ausdrücklich als unabhängige Kopie weiterführen, sofern kein gemeinsames Universumskonto daran gebunden ist.', 'ps-pen-paper' ); ?></p>
						<p><label class="pspp-check"><input type="checkbox" name="context_confirmation" value="confirmed" required>
							<?php esc_html_e( 'Diesen Bogen im gewählten Modus aufnehmen, ohne Mitgliedschaften zu verändern.', 'ps-pen-paper' ); ?></label></p>
						<?php submit_button( __( 'Basisbogen übernehmen', 'ps-pen-paper' ) ); ?>
					</form>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
	<?php
}

function ps_pen_paper_render_character_context( $campaign, $character, $state, $page_id ) {
	if ( ! isset( $state['context'] ) ) { return; }
	$context = $state['context'];
	$rules = ps_pen_paper_get_rules( $campaign->ID );
	?>
	<section class="pspp-card">
		<h3><?php esc_html_e( 'Kampagnenkontext', 'ps-pen-paper' ); ?></h3>
		<p><?php echo esc_html( ps_pen_paper_character_context_modes()[ $context['mode'] ] ); ?></p>
		<p><?php esc_html_e( 'Die angezeigten gültigen Werte enthalten die freigegebenen Kampagnenauflagen. Diese überschreiben weder den Basisbogen noch andere Runden. Leere Auflagenfelder übernehmen den Grundwert. Ressourcen bleiben unverändert; eine optionale Stufe kann als frei benanntes Zahlenfeld angepasst werden.', 'ps-pen-paper' ); ?></p>
		<p class="pspp-multiline"><?php echo esc_html( $context['note'] ); ?></p>
		<?php if ( ps_pen_paper_can_manage_campaign( $campaign ) ) : ?>
			<?php if ( 'shared' === $context['mode'] ) :
				$preview = ps_pen_paper_preview_character_copy( $campaign->ID, $character->ID );
				?>
				<details class="pspp-fold">
					<summary><?php esc_html_e( 'Als unabhängigen Kampagnenbogen weiterführen', 'ps-pen-paper' ); ?></summary>
					<?php if ( is_wp_error( $preview ) ) : ?>
						<p role="alert"><?php echo esc_html( $preview->get_error_message() ); ?></p>
					<?php else : ?>
						<p><?php esc_html_e( 'Dieser freigegebene Stand einschließlich Kampagnenauflagen wird zum eigenen Grundstand. Ausrüstung, Inventar und bestehende Kampagnenkonten bleiben erhalten; Ausrüstungsboni werden nicht als Grundwerte übernommen. Spätere Basisänderungen gelten hier nicht mehr. Der Basisbogen und andere Kampagnen bleiben unverändert. Eine Rückverbindung ist nicht automatisch möglich.', 'ps-pen-paper' ); ?></p>
						<?php ps_pen_paper_render_character_snapshot( $preview['effective']['active'], $preview['rules'] ); ?>
						<?php ps_pen_paper_form_start( 'copy_shared_character', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
							<input type="hidden" name="character_id" value="<?php echo esc_attr( $character->ID ); ?>">
							<input type="hidden" name="character_revision" value="<?php echo esc_attr( $preview['before']['revision'] ); ?>">
							<input type="hidden" name="base_revision" value="<?php echo esc_attr( $preview['effective']['base_revision'] ); ?>">
							<input type="hidden" name="character_rules_revision" value="<?php echo esc_attr( $preview['rules']['revision'] ); ?>">
							<p><label class="pspp-check"><input type="checkbox" name="context_confirmation" value="confirmed" required>
								<?php esc_html_e( 'Den angezeigten Stand unabhängig in dieser Kampagne weiterführen.', 'ps-pen-paper' ); ?></label></p>
							<?php submit_button( __( 'Basisverbindung lösen', 'ps-pen-paper' ) ); ?>
						</form>
					<?php endif; ?>
				</details>
			<?php endif; ?>
			<details class="pspp-fold">
				<summary><?php esc_html_e( 'Kampagnenauflagen bearbeiten', 'ps-pen-paper' ); ?></summary>
				<?php ps_pen_paper_form_start( 'save_character_context', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
					<input type="hidden" name="character_id" value="<?php echo esc_attr( $character->ID ); ?>">
					<input type="hidden" name="character_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
					<input type="hidden" name="base_revision" value="<?php echo esc_attr( isset( $state['base_revision'] ) ? $state['base_revision'] : '' ); ?>">
					<input type="hidden" name="character_rules_revision" value="<?php echo esc_attr( $rules['revision'] ); ?>">
					<div class="pspp-grid">
						<?php foreach ( $rules['fields'] as $field ) : ?>
							<?php if ( ! in_array( $field['type'], array( 'number', 'attribute' ), true ) ) { continue; } ?>
							<p><label><?php echo esc_html( $field['label'] ); ?><br>
								<input type="number" step="1" name="<?php echo esc_attr( 'overrides[' . $field['id'] . ']' ); ?>" min="<?php echo esc_attr( $field['min'] ); ?>" max="<?php echo esc_attr( $field['max'] ); ?>" value="<?php echo esc_attr( isset( $context['overrides'][ $field['id'] ] ) ? $context['overrides'][ $field['id'] ] : '' ); ?>"></label></p>
						<?php endforeach; ?>
					</div>
					<p><label><?php esc_html_e( 'Notiz für Besitzer und Spielleitung', 'ps-pen-paper' ); ?><br>
						<textarea name="context_note" rows="3"><?php echo esc_textarea( $context['note'] ); ?></textarea></label></p>
					<input type="hidden" name="context_complete" value="complete">
					<?php submit_button( __( 'Kampagnenauflagen freigeben', 'ps-pen-paper' ) ); ?>
				</form>
			</details>
		<?php endif; ?>
	</section>
	<?php
}
