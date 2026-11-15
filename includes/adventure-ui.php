<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_handle_adventure_action( $operation ) {
	$campaign_id = ps_pen_paper_posted_id( 'campaign_id' ); $page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	check_admin_referer( 'pspp_campaign_' . $campaign_id );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! $campaign || ! ps_pen_paper_can_access_site() || ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) ) {
		ps_pen_paper_fail( ps_pen_paper_adventure_error()->get_error_message() );
	}
	$id = ps_pen_paper_posted_id( 'adventure_id' ); $revision = ps_pen_paper_posted_text( 'adventure_revision' );
	if ( 'start_adventure' === $operation ) {
		$result = ps_pen_paper_start_adventure_run( $campaign, $id, ps_pen_paper_posted_id( 'character_id' ), $revision, ps_pen_paper_posted_text( 'character_revision' ) );
		$character_id = ps_pen_paper_posted_id( 'character_id' );
		$url = ps_pen_paper_profile_return( $campaign_id, $character_id );
		if ( ! $url ) { $url = ps_pen_paper_character_url( $campaign_id, $character_id, $page_id ); }
		$url .= '#pspp-adventures';
	} elseif ( 'advance_adventure' === $operation ) {
		$run_id = ps_pen_paper_posted_id( 'run_id' ); $character_id = ps_pen_paper_posted_id( 'character_id' );
		$result = ps_pen_paper_advance_adventure_run( $campaign, $run_id, ps_pen_paper_posted_text( 'adventure_edge' ), ps_pen_paper_posted_text( 'run_revision' ) );
		$url = ps_pen_paper_profile_return( $campaign_id, $character_id );
		if ( ! $url ) { $url = ps_pen_paper_character_url( $campaign_id, $character_id, $page_id ); }
		$url .= '#pspp-adventure-run-' . $run_id;
	} else {
		if ( ! ps_pen_paper_can_manage_campaign( $campaign ) || 'complete' !== ps_pen_paper_posted_text( 'adventure_complete' ) ) { ps_pen_paper_fail( ps_pen_paper_adventure_error()->get_error_message() ); }
		if ( 'save_adventure' === $operation ) {
			$result = ps_pen_paper_save_adventure( $campaign, $id, ps_pen_paper_posted_text( 'adventure_title' ), ps_pen_paper_posted_text( 'adventure_start_scene' ), $revision );
		} elseif ( 'save_adventure_edge' === $operation ) {
			$input = isset( $_POST['adventure_edge'] ) ? wp_unslash( $_POST['adventure_edge'] ) : null;
			$result = ps_pen_paper_save_adventure_edge( $campaign, $id, $input, $revision );
		} elseif ( 'remove_adventure_edge' === $operation ) {
			$result = ps_pen_paper_save_adventure_edge( $campaign, $id, array(), $revision, ps_pen_paper_posted_text( 'edge_id' ) );
		} else {
			$result = ps_pen_paper_publish_adventure( $campaign, $id, $revision, ps_pen_paper_posted_text( 'adventure_confirmation' ), in_array( $operation, array( 'pause_adventure', 'resume_adventure' ), true ) && 'pause_adventure' === $operation );
		}
		$url = ps_pen_paper_game_url( $page_id, $campaign_id ) . '#pspp-story';
		if ( 'save_adventure' === $operation && ! is_wp_error( $result ) ) { $url = add_query_arg( 'pspp_adventure', $result, $url ); }
	}
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	wp_safe_redirect( $url ); exit;
}

function ps_pen_paper_adventure_scene_options( $campaign, $selected = 0, $allow_end = false ) {
	if ( $allow_end ) { echo '<option value="0"' . selected( $selected, 0, false ) . '>' . esc_html__( 'Abenteuer beenden', 'ps-pen-paper' ) . '</option>'; }
	foreach ( get_posts( array( 'post_type' => 'pspp_scene', 'post_status' => 'private', 'post_parent' => $campaign->ID,
		'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
		$scene = ps_pen_paper_get_scene( $campaign, $post->ID );
		if ( is_wp_error( $scene ) ) { echo '<option disabled>' . esc_html( $scene->get_error_message() ) . '</option>'; return; }
		if ( ! $scene['published'] ) { continue; }
		echo '<option value="' . esc_attr( $post->ID ) . '"' . selected( $selected, $post->ID, false ) . '>' . esc_html( $scene['published']['title'] ) . '</option>';
	}
}

function ps_pen_paper_render_adventure_editor( $campaign, $page_id ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return; }
	$records = ps_pen_paper_adventure_records( $campaign );
	if ( is_wp_error( $records ) ) { echo '<p role="alert">' . esc_html( $records->get_error_message() ) . '</p>'; return; }
	$scenes = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_scene', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
		$scene = ps_pen_paper_get_scene( $campaign, $post->ID );
		if ( is_wp_error( $scene ) ) { echo '<p role="alert">' . esc_html( $scene->get_error_message() ) . '</p>'; return; }
		if ( $scene['published'] ) { $scenes[ $post->ID ] = $scene['published']['title']; }
	}
	$id = ps_pen_paper_frontend_id( 'pspp_adventure' );
	$state = is_wp_error( $id ) ? $id : ( $id ? ps_pen_paper_get_adventure( $campaign, $id ) : ps_pen_paper_adventure_default() );
	if ( is_wp_error( $state ) ) { echo '<p role="alert">' . esc_html( $state->get_error_message() ) . '</p>'; return; }
	$draft = $state['draft']; $rules = ps_pen_paper_get_rules( $campaign->ID );
	?>
	<section class="pspp-adventure-editor"><h3><?php esc_html_e( 'Soloabenteuer vorbereiten', 'ps-pen-paper' ); ?></h3>
	<p><?php esc_html_e( 'Einzeldurchläufe zeigen nur freigegebene Szenentexte. Entscheide und Proben öffnen private Folgeszenen für genau diese Figur. Es werden weder Kampagnenszene noch Ressourcen oder Beute verändert.', 'ps-pen-paper' ); ?></p>
	<?php if ( ! $scenes ) : ?><p><?php esc_html_e( 'Veröffentliche zuerst mindestens zwei Szenen. Danach kannst du hier einen Pfad aus Entscheidung, optionaler Probe und Folgeszene vorbereiten.', 'ps-pen-paper' ); ?></p>
	<?php else : ?>
		<nav class="pspp-navigation"><a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-story' ); ?>"><?php esc_html_e( 'Neues Soloabenteuer', 'ps-pen-paper' ); ?></a>
			<?php foreach ( $records as $adventure_id => $record ) : ?><a href="<?php echo esc_url( add_query_arg( 'pspp_adventure', $adventure_id, ps_pen_paper_game_url( $page_id, $campaign->ID ) ) . '#pspp-story' ); ?>"><?php echo esc_html( $record['draft']['title'] . ' · ' . $record['status'] ); ?></a><?php endforeach; ?>
		</nav>
		<?php ps_pen_paper_form_start( 'save_adventure', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<input type="hidden" name="adventure_complete" value="complete"><input type="hidden" name="adventure_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="adventure_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
			<label><?php esc_html_e( 'Abenteuertitel', 'ps-pen-paper' ); ?><input type="text" name="adventure_title" value="<?php echo esc_attr( $draft['title'] ); ?>" maxlength="200" required></label>
			<label><?php esc_html_e( 'Eröffnungsszene', 'ps-pen-paper' ); ?><select name="adventure_start_scene" required><?php ps_pen_paper_adventure_scene_options( $campaign, $draft['start_scene'] ); ?></select></label>
			<button type="submit"><?php esc_html_e( 'Abenteuerentwurf speichern', 'ps-pen-paper' ); ?></button>
		</form>
		<?php if ( $id ) : ?>
			<h4><?php esc_html_e( 'Entscheidungen und Folgeszenen', 'ps-pen-paper' ); ?></h4>
			<?php if ( ! $draft['edges'] ) : ?><p><?php esc_html_e( 'Noch keine Entscheidung angelegt.', 'ps-pen-paper' ); ?></p><?php endif; ?>
			<ol><?php foreach ( $draft['edges'] as $edge ) : ?>
				<li><?php echo esc_html( ( $scenes[ $edge['from_scene'] ] ?? __( 'Ungültige Szene', 'ps-pen-paper' ) ) . ' · ' . $edge['choice'] ); ?>
					<?php if ( $edge['expression'] ) : ?> · <?php echo esc_html( $edge['expression'] . ' ≥ ' . $edge['threshold'] ); ?><?php endif; ?>
					<?php echo esc_html( ' → ' . ( $edge['success_scene'] ? ( $scenes[ $edge['success_scene'] ] ?? '' ) : __( 'Ende', 'ps-pen-paper' ) ) ); ?>
					<?php if ( $edge['expression'] ) { echo esc_html( ' / ' . ( $edge['failure_scene'] ? ( $scenes[ $edge['failure_scene'] ] ?? '' ) : __( 'Ende', 'ps-pen-paper' ) ) ); } ?>
					<?php ps_pen_paper_form_start( 'remove_adventure_edge', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
						<input type="hidden" name="adventure_complete" value="complete"><input type="hidden" name="adventure_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="adventure_revision" value="<?php echo esc_attr( $state['revision'] ); ?>"><input type="hidden" name="edge_id" value="<?php echo esc_attr( $edge['id'] ); ?>">
						<button type="submit"><?php esc_html_e( 'Entscheidung entfernen', 'ps-pen-paper' ); ?></button>
					</form>
				</li>
			<?php endforeach; ?></ol>
			<?php if ( $rules && ! is_wp_error( $rules ) ) : ?>
			<details class="pspp-fold"><summary><?php esc_html_e( 'Entscheidung oder Probe ergänzen', 'ps-pen-paper' ); ?></summary>
				<?php ps_pen_paper_form_start( 'save_adventure_edge', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
					<input type="hidden" name="adventure_complete" value="complete"><input type="hidden" name="adventure_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="adventure_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
					<label><?php esc_html_e( 'Ausgangsszene', 'ps-pen-paper' ); ?><select name="adventure_edge[from_scene]" required><?php ps_pen_paper_adventure_scene_options( $campaign ); ?></select></label>
					<label><?php esc_html_e( 'Entscheidungstext', 'ps-pen-paper' ); ?><input type="text" name="adventure_edge[choice]" maxlength="120" required></label>
					<label><?php esc_html_e( 'Optionaler Würfelausdruck (leer = Entscheidung ohne Probe)', 'ps-pen-paper' ); ?><input type="text" name="adventure_edge[expression]" placeholder="2W6"></label>
					<label><?php esc_html_e( 'Optionaler freigegebener Zahlenwert', 'ps-pen-paper' ); ?><select name="adventure_edge[attribute]"><option value=""><?php esc_html_e( 'Kein Attribut addieren', 'ps-pen-paper' ); ?></option>
						<?php foreach ( $rules['fields'] as $field ) : if ( ! in_array( $field['type'], array( 'attribute', 'number' ), true ) ) { continue; } ?><option value="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( $field['label'] ); ?></option><?php endforeach; ?>
					</select></label>
					<label><?php esc_html_e( 'Erfolg ab Gesamtwert', 'ps-pen-paper' ); ?><input type="number" name="adventure_edge[threshold]" min="-1000000" max="1000000" value="10" required></label>
					<label><?php esc_html_e( 'Folgeszene bei Erfolg (oder bei Entscheidung ohne Probe)', 'ps-pen-paper' ); ?><select name="adventure_edge[success_scene]" required><?php ps_pen_paper_adventure_scene_options( $campaign, 0, true ); ?></select></label>
					<label><?php esc_html_e( 'Folgeszene bei Misserfolg', 'ps-pen-paper' ); ?><select name="adventure_edge[failure_scene]"><option value="0"><?php esc_html_e( 'Abenteuer beenden', 'ps-pen-paper' ); ?></option><?php ps_pen_paper_adventure_scene_options( $campaign ); ?></select></label>
					<p><?php esc_html_e( 'Der Server würfelt einmal, addiert nur den gewählten freigegebenen Zahlenwert und vergleicht mit dem Zielwert. Es wird keine Ressource abgezogen.', 'ps-pen-paper' ); ?></p>
					<button type="submit"><?php esc_html_e( 'Entscheidung speichern', 'ps-pen-paper' ); ?></button>
				</form>
			</details>
			<?php endif; ?>
			<p><?php echo esc_html( __( 'Status:', 'ps-pen-paper' ) . ' ' . array( 'draft' => __( 'Entwurf', 'ps-pen-paper' ), 'published' => __( 'Für Einzelabenteuer freigegeben', 'ps-pen-paper' ), 'paused' => __( 'Pausiert – laufende Durchläufe können fortgesetzt werden', 'ps-pen-paper' ) )[ $state['status'] ] ); ?></p>
			<?php foreach ( array( 'publish_adventure' => __( 'Abenteuer ausdrücklich freigeben', 'ps-pen-paper' ), 'pause_adventure' => __( 'Neue Durchläufe pausieren', 'ps-pen-paper' ), 'resume_adventure' => __( 'Neue Durchläufe wieder erlauben', 'ps-pen-paper' ) ) as $operation => $label ) :
				if ( ( 'publish_adventure' === $operation && in_array( $state['status'], array( 'published', 'paused' ), true ) ) || ( 'pause_adventure' === $operation && 'published' !== $state['status'] ) ||
					( 'resume_adventure' === $operation && 'paused' !== $state['status'] ) ) { continue; }
				ps_pen_paper_form_start( $operation, 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
				<input type="hidden" name="adventure_complete" value="complete"><input type="hidden" name="adventure_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="adventure_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
				<label class="pspp-check"><input type="checkbox" name="adventure_confirmation" value="confirmed" required><?php echo esc_html( $label ); ?></label><button type="submit"><?php echo esc_html( $label ); ?></button>
			</form><?php endforeach; ?>
		<?php endif; ?>
	<?php endif; ?></section>
	<?php
}

function ps_pen_paper_render_character_adventures( $campaign, $character, $page_id ) {
	if ( ! $campaign || 'pspp_campaign' !== $campaign->post_type || ! ps_pen_paper_can_access_site() ||
		! ps_pen_paper_get_character( $campaign, $character->ID ) ) { return; }
	$adventures = ps_pen_paper_adventure_records( $campaign, false );
	$runs = ps_pen_paper_adventure_run_records( $campaign, $character->ID );
	$state = ps_pen_paper_character_state( $campaign, $character );
	if ( is_wp_error( $adventures ) || is_wp_error( $runs ) || is_wp_error( $state ) ) {
		echo '<p role="alert">' . esc_html( is_wp_error( $adventures ) ? $adventures->get_error_message() : ( is_wp_error( $runs ) ? $runs->get_error_message() : $state->get_error_message() ) ) . '</p>'; return;
	}
	$by_adventure = array(); foreach ( $runs as $run_id => $run ) { $by_adventure[ $run['adventure_id'] ] = array( 'id' => $run_id, 'state' => $run ); }
	?>
	<section id="pspp-adventures" class="pspp-solo-adventures"><h3><?php esc_html_e( 'Soloabenteuer', 'ps-pen-paper' ); ?></h3>
	<p><?php esc_html_e( 'Dein eigener, gespeicherter Durchlauf. Entscheidungen ändern weder die gemeinsame Szene noch deine Werte. Jede Probe wird mit ihrem tatsächlichen Ergebnis protokolliert.', 'ps-pen-paper' ); ?></p>
	<?php if ( ! $adventures ) : ?><p><?php esc_html_e( 'Derzeit sind keine freigegebenen Soloabenteuer vorhanden.', 'ps-pen-paper' ); ?></p><?php endif; ?>
	<?php foreach ( $adventures as $adventure_id => $adventure ) :
		$existing = isset( $by_adventure[ $adventure_id ] ) ? $by_adventure[ $adventure_id ] : null; ?>
		<article class="pspp-solo-adventure" data-adventure-id="<?php echo esc_attr( $adventure_id ); ?>">
			<h4><?php echo esc_html( $adventure['published']['title'] ); ?></h4>
			<?php if ( ! $existing && 'published' === $adventure['status'] && (int) $character->post_author === get_current_user_id() ) :
				ps_pen_paper_form_start( 'start_adventure', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
				<input type="hidden" name="character_id" value="<?php echo esc_attr( $character->ID ); ?>"><input type="hidden" name="character_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
				<input type="hidden" name="adventure_id" value="<?php echo esc_attr( $adventure_id ); ?>"><input type="hidden" name="adventure_revision" value="<?php echo esc_attr( $adventure['revision'] ); ?>">
				<button type="submit"><?php esc_html_e( 'Solo starten', 'ps-pen-paper' ); ?></button>
			</form>
			<?php elseif ( $existing ) : ps_pen_paper_render_adventure_run( $campaign, $character, $page_id, $existing['id'], $existing['state'] ); ?>
			<?php elseif ( 'published' !== $adventure['status'] ) : ?><p><?php esc_html_e( 'Neue Durchläufe sind pausiert. Laufende Durchläufe bleiben unabhängig fortsetzbar.', 'ps-pen-paper' ); ?></p>
			<?php elseif ( (int) $character->post_author !== get_current_user_id() ) : ?><p><?php esc_html_e( 'Nur der Besitzer startet den eigenen Durchlauf.', 'ps-pen-paper' ); ?></p><?php endif; ?>
		</article>
	<?php endforeach; ?></section>
	<?php
}

function ps_pen_paper_render_adventure_run( $campaign, $character, $page_id, $run_id, $run ) {
	$snapshot = $run['snapshot']; $scene = $run['scene_id'] ? $snapshot['scenes'][ $run['scene_id'] ] : null;
	echo '<div class="pspp-adventure-run" id="pspp-adventure-run-' . esc_attr( $run_id ) . '" data-run-id="' . esc_attr( $run_id ) . '">';
	if ( $scene ) {
		echo '<h5>' . esc_html( $scene['title'] ) . '</h5><p class="pspp-multiline">' . esc_html( $scene['text'] ) . '</p>';
	} elseif ( $run['history'] ) {
		$last = end( $run['history'] ); echo '<p>' . esc_html( $snapshot['title'] . ' · ' . __( 'Abenteuer beendet', 'ps-pen-paper' ) . ' · ' . $last['choice'] ) . '</p>';
	}
	if ( 'completed' === $run['status'] ) { echo '<p role="status">' . esc_html__( 'Durchlauf abgeschlossen. Ein zweiter Durchlauf mit dieser Figur ist derzeit nicht vorgesehen.', 'ps-pen-paper' ) . '</p>'; }
	elseif ( (int) $character->post_author === get_current_user_id() ) {
		$choices = array_values( array_filter( $snapshot['edges'], function ( $edge ) use ( $run ) { return $edge['from_scene'] === $run['scene_id']; } ) );
		foreach ( $choices as $edge ) {
			ps_pen_paper_form_start( 'advance_adventure', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<input type="hidden" name="character_id" value="<?php echo esc_attr( $character->ID ); ?>"><input type="hidden" name="run_id" value="<?php echo esc_attr( $run_id ); ?>">
			<input type="hidden" name="run_revision" value="<?php echo esc_attr( $run['revision'] ); ?>"><input type="hidden" name="adventure_edge" value="<?php echo esc_attr( $edge['id'] ); ?>">
			<button type="submit"><?php echo esc_html( $edge['choice'] ); ?></button>
			<?php if ( $edge['expression'] ) : ?><p><?php echo esc_html( __( 'Serverprobe:', 'ps-pen-paper' ) . ' ' . $edge['expression'] . ( $edge['attribute'] ? ' + freigegebener Wert' : '' ) . ' · ' . __( 'Erfolg ab', 'ps-pen-paper' ) . ' ' . $edge['threshold'] ); ?></p><?php endif; ?>
			</form><?php
		}
	} else { echo '<p>' . esc_html__( 'Der Durchlauf gehört dem Charakterbesitzer. Die Spielleitung kann ihn einsehen, aber nicht an seiner Stelle entscheiden.', 'ps-pen-paper' ) . '</p>'; }
	if ( $run['history'] ) {
		echo '<h6>' . esc_html__( 'Bisheriger Verlauf', 'ps-pen-paper' ) . '</h6><ol>';
		foreach ( $run['history'] as $entry ) {
			$detail = $entry['choice'];
			if ( $entry['roll'] ) { $detail .= ' · ' . $entry['roll']['total'] . ' / ' . $entry['roll']['threshold'] . ' · ' . ( $entry['roll']['success'] ? __( 'Erfolg', 'ps-pen-paper' ) : __( 'Misserfolg', 'ps-pen-paper' ) ); }
			echo '<li>' . esc_html( $detail ) . '</li>';
		}
		echo '</ol>';
	}
	echo '</div>';
}
