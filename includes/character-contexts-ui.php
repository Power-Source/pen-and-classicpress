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
	$profile_url = ps_pen_paper_profile_return( $id, ps_pen_paper_posted_id( 'character_id' ) );
	$url = ps_pen_paper_universe_url( $id, $page_id );
	if ( 'save_universe_rules' === $operation ) {
		if ( 'complete' !== ps_pen_paper_posted_text( 'rules_complete' ) || ! ps_pen_paper_get_universe( $id ) ) {
			ps_pen_paper_fail( __( 'Die Universumsvorlage wurde nicht vollständig übertragen.', 'ps-pen-paper' ) );
		}
		$input = isset( $_POST['rules'] ) ? wp_unslash( $_POST['rules'] ) : null;
		$revision = isset( $_POST['rules_revision'] ) ? $_POST['rules_revision'] : null;
		if ( '1' === ps_pen_paper_posted_text( 'pspp_schema_preview' ) ) {
			ps_pen_paper_show_rules_migration_preview( ps_pen_paper_get_universe( $id ), $input, $revision, $page_id );
		}
		if ( isset( $_POST['rules_migration'] ) && '1' !== ps_pen_paper_posted_text( 'rules_migration' ) ) {
			ps_pen_paper_fail( __( 'Die Migrationsbestätigung ist ungültig. Bitte öffne eine neue Vorschau.', 'ps-pen-paper' ) );
		}
		$result = '1' === ps_pen_paper_posted_text( 'rules_migration' )
			? ps_pen_paper_save_rules_migration( $id, $input, $revision,
				isset( $_POST['resolutions'] ) ? wp_unslash( $_POST['resolutions'] ) : array(),
				ps_pen_paper_posted_text( 'migration_confirmation' ), ps_pen_paper_posted_text( 'migration_preview_hash' ) )
			: ps_pen_paper_save_rules( $id, $input, $revision );
	} elseif ( 'request_universe_character' === $operation ) {
		if ( 'complete' !== ps_pen_paper_posted_text( 'character_complete' ) ) {
			ps_pen_paper_fail( __( 'Der Universumsantrag wurde nicht vollständig übertragen.', 'ps-pen-paper' ) );
		}
		$universe_id = ps_pen_paper_posted_id( 'universe_id' );
		$universe_epoch = ps_pen_paper_scope_epoch( $universe_id );
		if ( is_wp_error( $universe_epoch ) || $universe_epoch !== ps_pen_paper_posted_text( 'universe_epoch' ) ) {
			ps_pen_paper_fail( __( 'Das Universum wurde inzwischen wiederhergestellt. Bitte lade das Antragsformular neu.', 'ps-pen-paper' ) );
		}
		$result = ps_pen_paper_request_universe_character( $id, $universe_id,
			isset( $_POST['character'] ) ? wp_unslash( $_POST['character'] ) : null,
			isset( $_POST['character_rules_revision'] ) ? $_POST['character_rules_revision'] : null );
		$url = $page_id ? ps_pen_paper_game_url( $page_id, $id ) : ps_pen_paper_admin_url( $id );
	} elseif ( 'review_base_character' === $operation ) {
		$universe = ps_pen_paper_get_universe( $id );
		if ( ! $universe || ! ps_pen_paper_can_manage_universe( $universe ) ) {
			ps_pen_paper_fail( __( 'Nur die Universumsleitung darf diesen Basisbogen freigeben oder ablehnen.', 'ps-pen-paper' ) );
		}
		$character_id = ps_pen_paper_posted_id( 'character_id' );
		$result = ps_pen_paper_review_character( $id, $character_id, ps_pen_paper_posted_text( 'decision' ),
			isset( $_POST['character_revision'] ) ? $_POST['character_revision'] : null );
		$url = ps_pen_paper_universe_url( $id, $page_id, $character_id );
	} elseif ( 'save_base_character' === $operation ) {
		if ( 'complete' !== ps_pen_paper_posted_text( 'character_complete' ) || ! ps_pen_paper_get_universe( $id ) ) {
			ps_pen_paper_fail( __( 'Der Basisbogen wurde nicht vollständig übertragen.', 'ps-pen-paper' ) );
		}
		$result = ps_pen_paper_save_character( $id, ps_pen_paper_posted_id( 'character_id' ), ps_pen_paper_posted_id( 'owner_id' ),
			isset( $_POST['character'] ) ? wp_unslash( $_POST['character'] ) : null,
			isset( $_POST['character_revision'] ) ? $_POST['character_revision'] : null,
			isset( $_POST['character_rules_revision'] ) ? $_POST['character_rules_revision'] : null,
			'' !== ps_pen_paper_profile_return( $id, ps_pen_paper_posted_id( 'character_id' ) ) );
		if ( ! is_wp_error( $result ) ) { $url = ps_pen_paper_universe_url( $id, $page_id, $result ); }
	} elseif ( 'copy_shared_character' === $operation ) {
		$character_id = ps_pen_paper_posted_id( 'character_id' );
		$result = ps_pen_paper_copy_shared_character( $id, $character_id,
			isset( $_POST['character_revision'] ) ? $_POST['character_revision'] : null,
			isset( $_POST['base_revision'] ) ? $_POST['base_revision'] : null,
			isset( $_POST['character_rules_revision'] ) ? $_POST['character_rules_revision'] : null,
			ps_pen_paper_posted_text( 'context_confirmation' ) );
		$url = ps_pen_paper_character_url( $id, $character_id, $page_id );
	} elseif ( 'request_base_character' === $operation ) {
		$token = ps_pen_paper_posted_text( 'base_character' );
		if ( ! preg_match( '/^([1-9]\d*):([1-9]\d*)$/D', $token, $parts ) ) {
			ps_pen_paper_fail( __( 'Bitte wähle einen gültigen eigenen Basisbogen.', 'ps-pen-paper' ) );
		}
		$result = ps_pen_paper_request_base_character( $id, (int) $parts[1], $parts[2],
			isset( $_POST['character_rules_revision'] ) ? $_POST['character_rules_revision'] : null );
		if ( ! is_wp_error( $result ) ) { $url = ps_pen_paper_character_url( $id, $result, $page_id ); }
	} elseif ( 'reattach_character' === $operation ) {
		$character_id = ps_pen_paper_posted_id( 'character_id' );
		$result = ps_pen_paper_reattach_character( $id, $character_id,
			isset( $_POST['character_revision'] ) ? $_POST['character_revision'] : null,
			isset( $_POST['base_revision'] ) ? $_POST['base_revision'] : null,
			isset( $_POST['character_rules_revision'] ) ? $_POST['character_rules_revision'] : null,
			isset( $_POST['resolutions'] ) ? wp_unslash( $_POST['resolutions'] ) : array(),
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
			if ( $page_id && '1' === ps_pen_paper_posted_text( 'pspp_table_adoption' ) ) { $url = ps_pen_paper_game_url( $page_id, $id ) . '#pspp-sheet'; }
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
	wp_safe_redirect( add_query_arg( 'pspp_notice', 'context_saved', $profile_url ? $profile_url : $url ) );
	exit;
}

function ps_pen_paper_render_campaign_base_requests( $campaign, $page_id ) {
	$link = ps_pen_paper_get_universe_link( $campaign->ID );
	$rules = ps_pen_paper_get_rules( $campaign->ID );
	if ( is_wp_error( $link ) ) { echo '<p role="alert">' . esc_html( $link->get_error_message() ) . '</p>'; return; }
	if ( ! $link['universe_id'] || ! ps_pen_paper_can_access_site() ||
		! ps_pen_paper_campaign_role( $campaign, get_current_user_id() ) ) { return; }
	$universe = ps_pen_paper_get_universe( $link['universe_id'] );
	if ( ! $universe ) { echo '<p role="alert">' . esc_html__( 'Das zugeordnete Universum ist nicht mehr verfügbar. Bitte informiere die Spielleitung.', 'ps-pen-paper' ) . '</p>'; return; }
	$base_rules = ps_pen_paper_get_rules( $universe->ID );
	if ( $base_rules['revision'] > 0 && ps_pen_paper_community_available() ) {
		echo '<p><a class="button" href="' . esc_url( ps_pen_paper_community_url( 0, 0, $universe->ID ) ) . '">' . esc_html__( 'Charakter im Profil erstellen', 'ps-pen-paper' ) . '</a></p>';
	} elseif ( $base_rules['revision'] > 0 ) {
		echo '<details class="pspp-fold"><summary>' . esc_html__( 'Neuen eigenen Universumsbogen beantragen', 'ps-pen-paper' ) . '</summary>';
		ps_pen_paper_render_character_form( $universe, $base_rules, null, $page_id, $campaign );
		echo '</details>';
	}
	$bases = get_posts( array(
		'post_type' => 'pspp_character', 'post_status' => 'private', 'post_parent' => $link['universe_id'],
		'author' => get_current_user_id(), 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC',
	) );
	$options = array();
	foreach ( $bases as $base ) {
		$state = ps_pen_paper_raw_character_state( $base->ID );
		if ( is_wp_error( $state ) ) { echo '<p role="alert">' . esc_html( $state->get_error_message() ) . '</p>'; continue; }
		if ( ! is_wp_error( $state ) && ! array_key_exists( 'context', $state ) && $state['pending'] ) {
			echo '<section class="pspp-card"><h3>' . esc_html__( 'Dein offener Universumsantrag', 'ps-pen-paper' ) . '</h3>';
			ps_pen_paper_render_character_snapshot( $state['pending'], $base_rules );
			echo '<p>' . esc_html__( 'Die Universumsleitung muss diesen Basisbogen freigeben. Noch wird kein Kampagnenbogen aufgenommen.', 'ps-pen-paper' ) . '</p></section>';
		}
		if ( is_wp_error( $state ) || ! $state['active'] || array_key_exists( 'context', $state ) ||
			0 === $rules['revision'] ||
			ps_pen_paper_character_schema( $rules ) !== ps_pen_paper_character_schema( ps_pen_paper_get_rules( $link['universe_id'] ) ) ) { continue; }
		$options[] = array( 'token' => $base->ID . ':' . $state['revision'], 'label' => $state['active']['name'] );
	}
	if ( ! $options ) { return; }
	?>
	<section class="pspp-card">
		<h3><?php esc_html_e( 'Mit vorhandener Figur mitspielen', 'ps-pen-paper' ); ?></h3>
		<p><?php esc_html_e( 'Wähle deinen freigegebenen Charakter. Die Spielleitung bestätigt seine Aufnahme in diese Kampagne.', 'ps-pen-paper' ); ?></p>
		<?php ps_pen_paper_form_start( 'request_base_character', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<input type="hidden" name="character_rules_revision" value="<?php echo esc_attr( $rules['revision'] ); ?>">
			<p><label><?php esc_html_e( 'Charakter auswählen', 'ps-pen-paper' ); ?>
				<select name="base_character" required>
					<option value=""><?php esc_html_e( 'Eigenen Basisbogen auswählen', 'ps-pen-paper' ); ?></option>
					<?php foreach ( $options as $option ) : ?><option value="<?php echo esc_attr( $option['token'] ); ?>"><?php echo esc_html( $option['label'] ); ?></option><?php endforeach; ?>
				</select></label></p>
			<?php submit_button( __( 'Mit diesem Charakter mitspielen', 'ps-pen-paper' ) ); ?>
		</form>
	</section>
	<?php
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
			<summary><?php esc_html_e( 'Charaktervorgaben, Einladungen und Porträts', 'ps-pen-paper' ); ?></summary>
			<?php ps_pen_paper_render_character_policy( $universe, $page_id ); ?>
		</details>
		<details class="pspp-fold">
			<summary><?php esc_html_e( 'Universumsregelwerk bearbeiten', 'ps-pen-paper' ); ?></summary>
			<?php ps_pen_paper_render_rules( $universe, true, $page_id ); ?>
		</details>
		<?php ps_pen_paper_render_characters( $universe, 0, $page_id ); ?>
		<section id="pspp-settings"><?php ps_pen_paper_render_currency_settings( $universe, $page_id ); ?></section>
		<?php ps_pen_paper_render_trade_policy( $universe, $page_id ); ?>
		<section id="pspp-equipment"><?php ps_pen_paper_render_equipment_catalogue( $universe, $page_id ); ?></section>
		<?php ps_pen_paper_render_npcs( $universe, $page_id ); ?>
		<?php ps_pen_paper_render_loot( $universe, $page_id ); ?>
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
		<p><?php esc_html_e( 'Die angezeigten gültigen Werte enthalten die freigegebenen Kampagnenauflagen. Diese überschreiben weder den Basisbogen noch andere Runden. Leere Auflagenfelder übernehmen den Grundwert. Auflagen ändern keine Ressourcen; ausdrücklich konfigurierte Abenteuerkosten gemeinsamer Bögen werden einmalig am Basisbogen verbucht.', 'ps-pen-paper' ); ?></p>
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
						<?php ps_pen_paper_render_character_snapshot( $preview['effective']['active'], $preview['rules'], $campaign ); ?>
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
			<?php if ( 'copy' === $context['mode'] ) :
						$preview = ps_pen_paper_preview_character_reattachment( $campaign->ID, $character->ID );
						?>
						<details class="pspp-fold">
							<summary><?php esc_html_e( 'Mit dem aktuellen Basisbogen wieder verbinden', 'ps-pen-paper' ); ?></summary>
							<?php if ( is_wp_error( $preview ) ) : ?>
								<p role="alert"><?php echo esc_html( $preview->get_error_message() ); ?></p>
							<?php else : ?>
								<p><?php esc_html_e( 'Vergleiche jeden abweichenden Wert. Kopiewerte lassen sich nur bei Zahlen und Attributen als Kampagnenauflage behalten; andere Änderungen müssen ausdrücklich durch den Basiswert ersetzt werden. Ausrüstung und Inventar werden nicht kopiert oder entfernt.', 'ps-pen-paper' ); ?></p>
								<?php ps_pen_paper_render_character_snapshot( $preview['base'], $preview['rules'], $campaign ); ?>
								<?php if ( $preview['conflicts'] ) : ?>
									<ul>
										<?php foreach ( $preview['conflicts'] as $conflict ) : ?>
											<li><strong><?php echo esc_html( $conflict['label'] ); ?></strong>:
												<?php echo esc_html( is_array( $conflict['copy_value'] ) ? $conflict['copy_value']['current'] . ' / ' . $conflict['copy_value']['maximum'] : $conflict['copy_value'] ); ?>
												→
												<?php echo esc_html( is_array( $conflict['base_value'] ) ? $conflict['base_value']['current'] . ' / ' . $conflict['base_value']['maximum'] : $conflict['base_value'] ); ?>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?><p><?php esc_html_e( 'Die Werte stimmen überein.', 'ps-pen-paper' ); ?></p><?php endif; ?>
								<?php ps_pen_paper_form_start( 'reattach_character', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
									<input type="hidden" name="character_id" value="<?php echo esc_attr( $character->ID ); ?>">
									<input type="hidden" name="character_revision" value="<?php echo esc_attr( $preview['before']['revision'] ); ?>">
									<input type="hidden" name="base_revision" value="<?php echo esc_attr( $preview['base_revision'] ); ?>">
									<input type="hidden" name="character_rules_revision" value="<?php echo esc_attr( $preview['rules']['revision'] ); ?>">
									<?php foreach ( $preview['conflicts'] as $conflict ) : ?>
										<p><label><?php echo esc_html( $conflict['label'] ); ?>
											<select name="<?php echo esc_attr( 'resolutions[' . $conflict['field_id'] . ']' ); ?>">
												<option value="base"><?php esc_html_e( 'Basiswert verwenden', 'ps-pen-paper' ); ?></option>
												<?php if ( $conflict['copy_override_allowed'] ) : ?><option value="copy"><?php esc_html_e( 'Kopiewert als Kampagnenauflage behalten', 'ps-pen-paper' ); ?></option><?php endif; ?>
											</select></label></p>
									<?php endforeach; ?>
									<p><label class="pspp-check"><input type="checkbox" name="context_confirmation" value="confirmed" required><?php esc_html_e( 'Die oben gezeigten Werte und Konfliktlösungen übernehmen.', 'ps-pen-paper' ); ?></label></p>
									<?php submit_button( __( 'Basisverbindung wiederherstellen', 'ps-pen-paper' ) ); ?>
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
