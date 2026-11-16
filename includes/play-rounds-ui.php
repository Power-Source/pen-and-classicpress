<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_play_url( $page_id, $source_id, $mode = 'choose', $round_id = 0 ) {
	return add_query_arg( $round_id ? array( 'pspp_round' => $round_id ) : array( 'pspp_mode' => $mode ), ps_pen_paper_game_url( $page_id, $source_id ) );
}

function ps_pen_paper_play_adventures( $source, $mode ) {
	$records = ps_pen_paper_adventure_records( $source, false );
	if ( is_wp_error( $records ) ) { return $records; }
	return array_filter( $records, static function ( $adventure ) use ( $mode ) {
		return 'published' === $adventure['status'] &&
			in_array( $adventure['published']['scenario']['mode'] ?? 'both', array( 'both', $mode ), true ) &&
			! array_filter( $adventure['published']['edges'], static function ( $edge ) { return ! empty( $edge['gm_review'] ); } );
	} );
}

function ps_pen_paper_handle_play_action( $operation, $page_id, $id ) {
	check_admin_referer( 'pspp_campaign_' . $id );
	$campaign = ps_pen_paper_get_campaign( $id );
	if ( ! $campaign || ! ps_pen_paper_can_access_site() || ! ps_pen_paper_frontend_page( $page_id ) ) { ps_pen_paper_fail( ps_pen_paper_play_error()->get_error_message() ); }
	$character_id = ps_pen_paper_posted_id( 'play_character' );
	$source_revision = '';
	if ( in_array( $operation, array( 'begin_solo_play', 'begin_group_play' ), true ) || ( 'change_play_round' === $operation && 'join' === ps_pen_paper_posted_text( 'play_action' ) ) ) {
		$revisions = isset( $_POST['play_character_revisions'] ) ? wp_unslash( $_POST['play_character_revisions'] ) : array();
		if ( ! $character_id || ! is_array( $revisions ) || ! isset( $revisions[ $character_id ] ) || ! is_string( $revisions[ $character_id ] ) ) { ps_pen_paper_fail( ps_pen_paper_play_error()->get_error_message() ); }
		$source_revision = $revisions[ $character_id ];
	}
	if ( 'save_play_options' === $operation ) {
		$modes = isset( $_POST['play_modes'] ) ? wp_unslash( $_POST['play_modes'] ) : array();
		$result = ps_pen_paper_save_play_options( $campaign, $modes, 'yes' === ps_pen_paper_posted_text( 'player_starts' ), ps_pen_paper_posted_text( 'play_revision' ) );
		$url = ps_pen_paper_play_url( $page_id, $id );
	} elseif ( in_array( $operation, array( 'create_play_round', 'begin_solo_play', 'begin_group_play' ), true ) ) {
		$direct = 'create_play_round' !== $operation; $friends = null;
		if ( 'begin_group_play' === $operation ) {
			$input = isset( $_POST['play_friends'] ) ? wp_unslash( $_POST['play_friends'] ) : null;
			if ( ! is_array( $input ) || count( $input ) > 99 ) { ps_pen_paper_fail( ps_pen_paper_play_error()->get_error_message() ); }
			$friends = array();
			foreach ( $input as $friend ) {
				if ( ! is_string( $friend ) || ! preg_match( '/^[1-9]\d{0,18}$/D', $friend ) || (string) (int) $friend !== $friend ) { ps_pen_paper_fail( ps_pen_paper_play_error()->get_error_message() ); }
				$friends[] = (int) $friend;
			}
		}
		$result = ps_pen_paper_create_play_round( $campaign, $direct ? ( 'begin_group_play' === $operation ? 'group' : 'solo' ) : ps_pen_paper_posted_text( 'play_mode' ), ps_pen_paper_posted_id( 'play_adventure' ),
			ps_pen_paper_posted_text( 'play_token' ), ps_pen_paper_posted_text( 'play_revision' ), $direct ? $character_id : 0, $source_revision, $friends );
		$url = ! is_wp_error( $result ) ? ps_pen_paper_play_url( $page_id, $id, 'choose', $result ) : '';
	} else {
		$state = ps_pen_paper_play_round( $id );
		if ( ! $state || is_wp_error( $state ) ) { ps_pen_paper_fail( ps_pen_paper_play_error()->get_error_message() ); }
		$action = ps_pen_paper_posted_text( 'play_action' );
		$result = ps_pen_paper_change_play_round( $campaign, $action, ps_pen_paper_posted_text( 'play_revision' ),
			'revoke_invite' === $action ? ps_pen_paper_posted_id( 'play_invitee' ) : $character_id, $source_revision );
		$url = 'decline_invite' === $action ? ps_pen_paper_game_url( $page_id ) . '#pspp-lounge-invitations' :
			ps_pen_paper_play_url( $page_id, $state['source_id'], 'choose', $id );
	}
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	wp_safe_redirect( $url ); exit;
}

function ps_pen_paper_render_play_options( $source, $page_id ) {
	if ( ! ps_pen_paper_can_manage_campaign( $source ) || ! ps_pen_paper_management_view( $page_id ) ) { return; }
	$options = ps_pen_paper_play_options( $source->ID );
	if ( is_wp_error( $options ) ) { echo '<p role="alert">' . esc_html( $options->get_error_message() ) . '</p>'; return; }
	?>
	<details class="pspp-fold"><summary><?php esc_html_e( 'Spielarten anbieten', 'ps-pen-paper' ); ?></summary>
		<?php ps_pen_paper_form_start( 'save_play_options', 'pspp_campaign_' . $source->ID, $source->ID, $page_id ); ?>
		<input type="hidden" name="play_revision" value="<?php echo esc_attr( $options['revision'] ); ?>">
		<?php foreach ( ps_pen_paper_play_modes() as $mode => $label ) : ?>
			<label><input type="checkbox" name="play_modes[]" value="<?php echo esc_attr( $mode ); ?>" <?php checked( in_array( $mode, $options['modes'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
		<?php endforeach; ?>
		<label><input type="checkbox" name="player_starts" value="yes" <?php checked( $options['player_starts'] ); ?>> <?php esc_html_e( 'Spieler dürfen eigene Solo- und automatische Gruppenrunden anlegen.', 'ps-pen-paper' ); ?></label>
		<p><?php esc_html_e( 'Solo und automatische Gruppen brauchen ein freigegebenes Abenteuer ohne notwendige Spielleiterentscheidungen. Laufende Runden bleiben beim Abschalten eines Angebots erhalten.', 'ps-pen-paper' ); ?></p>
		<button type="submit"><?php esc_html_e( 'Angebot speichern', 'ps-pen-paper' ); ?></button></form>
	</details>
	<?php
}

function ps_pen_paper_render_automatic_entry( $source, $page_id, $options, $mode ) {
	$adventures = ps_pen_paper_play_adventures( $source, $mode );
	$choices = ps_pen_paper_play_characters( $source );
	if ( is_wp_error( $adventures ) || is_wp_error( $choices ) ) {
		echo '<p role="alert">' . esc_html( is_wp_error( $adventures ) ? $adventures->get_error_message() : $choices->get_error_message() ) . '</p>'; return;
	}
	if ( ! $adventures ) { echo '<p>' . esc_html__( 'Die Spielleitung hat noch kein automatisch spielbares Abenteuer dieser Spielart freigegeben.', 'ps-pen-paper' ) . '</p>'; return; }
	$eligible = array_filter( $choices, static function ( $choice ) { return empty( $choice['unavailable'] ); } );
	foreach ( $choices as $choice ) {
		if ( ! empty( $choice['unavailable'] ) ) { echo '<p role="status">' . esc_html( $choice['state']['active']['name'] . ': ' . $choice['unavailable'] ) . '</p>'; }
	}
	if ( $choices && ! $eligible ) { return; }
	$friends = 'group' === $mode ? ps_pen_paper_play_friend_choices() : array();
	if ( is_wp_error( $friends ) ) { echo '<p role="alert">' . esc_html( $friends->get_error_message() ) . '</p>'; return; }
	if ( 'group' === $mode && ! $friends ) { echo '<p>' . esc_html__( 'Du hast noch keine verfügbaren PS-Community-Freunde auf dieser Website. Verwalte zuerst deine Freundschaften in PS Community.', 'ps-pen-paper' ) . '</p>'; return; }
	if ( ! $choices ) {
		echo '<p>' . esc_html__( 'Zum Spielen brauchst du einen eigenen freigegebenen Charakter dieses Universums mit passenden Regeln. Erstelle ihn im Charakterprofil oder warte auf seine Freigabe und kehre anschließend hierher zurück.', 'ps-pen-paper' ) . '</p>';
		$link = ps_pen_paper_get_universe_link( $source->ID );
		if ( is_wp_error( $link ) ) { echo '<p role="alert">' . esc_html( $link->get_error_message() ) . '</p>'; return; }
		$url = ps_pen_paper_community_url( 0, 0, $link['universe_id'] );
		if ( $url ) { echo '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Charakter erstellen / Freigabe prüfen', 'ps-pen-paper' ) . '</a>'; }
		else { echo '<p role="alert">' . esc_html__( 'Das Charakterprofil ist nicht verfügbar. Bitte wende dich an die Website-Administration.', 'ps-pen-paper' ) . '</p>'; }
		return;
	}
	?>
	<section class="pspp-solo-entry"><h3><?php echo esc_html( 'group' === $mode ? __( 'Mit Freunden spielen', 'ps-pen-paper' ) : __( 'Solo spielen', 'ps-pen-paper' ) ); ?></h3>
		<p><?php echo esc_html( 'group' === $mode ? __( 'Wähle Abenteuer, eigenen Charakter und Freunde. Diese nehmen ihre Einladung in der Game-Lounge an und wählen jeweils eine eigene freigegebene Figur desselben Universums. Fehlender Kampagnenzugang benötigt weiterhin die vorgesehene Freigabe und Annahme.', 'ps-pen-paper' ) :
			__( 'Wähle ein freigegebenes Abenteuer und deinen Charakter. Danach beginnt das vorbereitete Spiel direkt, ohne Wartebereich. Laufende Spiele kannst du unten fortsetzen.', 'ps-pen-paper' ) ); ?></p>
		<?php ps_pen_paper_form_start( 'group' === $mode ? 'begin_group_play' : 'begin_solo_play', 'pspp_campaign_' . $source->ID, $source->ID, $page_id ); ?>
		<input type="hidden" name="play_revision" value="<?php echo esc_attr( $options['revision'] ); ?>">
		<input type="hidden" name="play_token" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
		<label><?php esc_html_e( 'Abenteuer', 'ps-pen-paper' ); ?><select name="play_adventure" required>
			<?php foreach ( $adventures as $id => $adventure ) : ?><option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $adventure['published']['title'] ); ?></option><?php endforeach; ?>
		</select></label>
		<?php if ( 'group' === $mode ) : ?>
			<label><?php esc_html_e( 'PS-Community-Freunde einladen', 'ps-pen-paper' ); ?><select name="play_friends[]" multiple required>
				<?php foreach ( $friends as $id => $name ) : ?><option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?>
			</select></label>
		<?php endif; ?>
		<label><?php esc_html_e( 'Mein Charakter', 'ps-pen-paper' ); ?><select name="play_character" required>
			<?php foreach ( $choices as $id => $choice ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php disabled( ! empty( $choice['unavailable'] ) ); ?>><?php echo esc_html( $choice['state']['active']['name'] ); ?></option><?php endforeach; ?>
		</select></label>
		<?php foreach ( $choices as $id => $choice ) : ?><input type="hidden" name="play_character_revisions[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $choice['state']['revision'] ); ?>"><?php endforeach; ?>
		<button type="submit"><?php echo esc_html( 'group' === $mode ? __( 'Gruppe vorbereiten und Freunde einladen', 'ps-pen-paper' ) : __( 'Solo spielen', 'ps-pen-paper' ) ); ?></button></form>
	</section>
	<?php
}

function ps_pen_paper_render_play_directory( $source, $page_id, $mode = 'choose' ) {
	$options = ps_pen_paper_play_options( $source->ID ); $rounds = ps_pen_paper_play_rounds( $source );
	if ( is_wp_error( $options ) || is_wp_error( $rounds ) ) { echo '<p role="alert">' . esc_html( is_wp_error( $options ) ? $options->get_error_message() : $rounds->get_error_message() ) . '</p>'; return; }
	?>
	<section class="pspp-play-directory" id="pspp-play"><h2><?php echo esc_html( $source->post_title ); ?></h2>
		<a href="<?php echo esc_url( remove_query_arg( 'pspp_view', ps_pen_paper_game_url( $page_id ) ) ); ?>"><?php esc_html_e( 'Zur Game-Lounge', 'ps-pen-paper' ); ?></a>
		<p class="pspp-multiline"><?php echo esc_html( $source->post_content ); ?></p>
		<?php ps_pen_paper_render_notice(); ps_pen_paper_render_lounge_modes( $source, $page_id ); ?>
		<?php ps_pen_paper_render_bookings( $source ); ?>
		<?php if ( in_array( $mode, array( 'solo', 'group' ), true ) && ! ps_pen_paper_management_view( $page_id ) && in_array( $mode, $options['modes'], true ) ) {
			if ( $options['player_starts'] ) { ps_pen_paper_render_automatic_entry( $source, $page_id, $options, $mode ); }
			else { echo '<p>' . esc_html__( 'Neue automatische Spiele sind derzeit nicht freigegeben. Deine bestehenden Spiele kannst du weiterhin öffnen.', 'ps-pen-paper' ) . '</p>'; }
		} ?>
		<h3><?php esc_html_e( 'Runden', 'ps-pen-paper' ); ?></h3>
		<div class="pspp-grid">
			<?php $found = false;
			foreach ( $rounds as $row ) :
				$state = $row['state']; $round = $row['post'];
				if ( ! ps_pen_paper_can_open_friend_round( $state ) && ! ( ps_pen_paper_management_view( $page_id ) && ps_pen_paper_can_manage_campaign( $source ) ) ) { continue; }
				if ( ( 'choose' !== $mode && $state['mode'] !== $mode ) ||
					( 'solo' === $state['mode'] && $state['host_id'] !== get_current_user_id() &&
						( ! ps_pen_paper_management_view( $page_id ) || ! ps_pen_paper_can_manage_campaign( $source ) ) ) ) { continue; }
				$names = array();
				foreach ( $state['participants'] as $participant ) {
					if ( $participant['active'] ) { $names[] = ps_pen_paper_adventure_group_user_label( $participant['user_id'] ); }
				}
				$found = true; ?>
				<article class="pspp-card"><h4><?php echo esc_html( $round->post_title . ' · ' . ps_pen_paper_play_modes()[ $state['mode'] ] ); ?></h4>
					<p><?php echo esc_html( ps_pen_paper_play_statuses()[ $state['status'] ] ); ?></p>
					<p><?php echo esc_html( implode( ', ', $names ) ); ?></p>
					<a class="button" href="<?php echo esc_url( ps_pen_paper_play_url( $page_id, $source->ID, 'choose', $round->ID ) ); ?>"><?php
						echo esc_html( 'completed' === $state['status'] ? __( 'Ergebnis ansehen', 'ps-pen-paper' ) :
							( 'lobby' === $state['status'] ? __( 'Wartebereich öffnen', 'ps-pen-paper' ) : __( 'Runde öffnen', 'ps-pen-paper' ) ) ); ?></a>
				</article>
			<?php endforeach; ?>
		</div>
		<?php if ( ! $found ) : ?><p><?php esc_html_e( 'Hier wartet noch keine Runde.', 'ps-pen-paper' ); ?></p><?php endif; ?>
		<?php if ( 'choose' !== $mode && ( 'guided' === $mode || ps_pen_paper_management_view( $page_id ) ) && in_array( $mode, $options['modes'], true ) &&
			( ( ps_pen_paper_management_view( $page_id ) && ps_pen_paper_can_manage_campaign( $source ) ) || ( 'guided' !== $mode && $options['player_starts'] ) ) ) :
			$adventures = 'guided' === $mode ? array() : ps_pen_paper_play_adventures( $source, $mode );
			if ( is_wp_error( $adventures ) ) : ?><p role="alert"><?php echo esc_html( $adventures->get_error_message() ); ?></p>
			<?php elseif ( 'guided' === $mode || $adventures ) : ?>
				<h3><?php esc_html_e( 'Neue Runde', 'ps-pen-paper' ); ?></h3>
				<?php ps_pen_paper_form_start( 'create_play_round', 'pspp_campaign_' . $source->ID, $source->ID, $page_id ); ?>
				<input type="hidden" name="play_mode" value="<?php echo esc_attr( $mode ); ?>"><input type="hidden" name="play_revision" value="<?php echo esc_attr( $options['revision'] ); ?>">
				<input type="hidden" name="play_token" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
				<?php if ( $adventures ) : ?>
					<label><?php esc_html_e( 'Abenteuer', 'ps-pen-paper' ); ?><select name="play_adventure">
						<?php foreach ( $adventures as $id => $adventure ) : ?><option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $adventure['published']['title'] ); ?></option><?php endforeach; ?>
					</select></label>
				<?php endif; ?>
				<button type="submit"><?php esc_html_e( 'Wartebereich anlegen', 'ps-pen-paper' ); ?></button></form>
			<?php endif;
		endif; ?>
		<?php ps_pen_paper_render_play_options( $source, $page_id ); ?>
		<?php ps_pen_paper_render_booking_settings( $source, $page_id ); ?>
	</section>
	<?php
}

function ps_pen_paper_play_statuses() {
	return array( 'lobby' => __( 'Wartet auf den Spielbeginn', 'ps-pen-paper' ), 'active' => __( 'Spiel läuft', 'ps-pen-paper' ),
		'paused' => __( 'Pausiert', 'ps-pen-paper' ), 'completed' => __( 'Abgeschlossen', 'ps-pen-paper' ) );
}

function ps_pen_paper_play_button( $round, $page_id, $state, $action, $label ) {
	ps_pen_paper_form_start( 'change_play_round', 'pspp_campaign_' . $round->ID, $round->ID, $page_id );
	echo '<input type="hidden" name="play_revision" value="' . esc_attr( $state['revision'] ) . '"><input type="hidden" name="play_action" value="' . esc_attr( $action ) . '">';
	echo '<button type="submit">' . esc_html( $label ) . '</button></form>';
}

function ps_pen_paper_render_friend_round_offers( $data, $page_id ) {
	$found = false;
	foreach ( $data['campaigns'] as $row ) {
		$rounds = ps_pen_paper_play_rounds( $row['post'] );
		if ( is_wp_error( $rounds ) ) { echo '<p role="alert">' . esc_html( $rounds->get_error_message() ) . '</p>'; continue; }
		foreach ( $rounds as $round ) {
			$state = $round['state'];
			if ( ! ps_pen_paper_can_decline_play_invitation( $state ) || ! in_array( $state['status'], array( 'lobby', 'paused' ), true ) ) { continue; }
			if ( ! $found ) { echo '<h2>' . esc_html__( 'Einladungen zu Freundesgruppen', 'ps-pen-paper' ) . '</h2>'; $found = true; }
			echo '<article><h3>' . esc_html( $row['post']->post_title . ' · ' . $round['post']->post_title ) . '</h3>';
			echo '<p>' . esc_html( $row['link']['name'] ) . '</p>';
			if ( $row['accessible'] && ps_pen_paper_can_open_friend_round( $state ) ) {
				echo '<a class="button" href="' . esc_url( ps_pen_paper_play_url( $page_id, $row['post']->ID, 'choose', $round['post']->ID ) ) . '">' .
					esc_html__( 'Einladung öffnen und Charakter wählen', 'ps-pen-paper' ) . '</a>';
			} elseif ( ! $row['accessible'] ) {
				echo '<p>' . esc_html__( 'Nimm zuerst die Kampagneneinladung an. Eine eventuell erforderliche Freigabe der Spielleitung bleibt notwendig.', 'ps-pen-paper' ) . '</p>';
			} else {
				echo '<p role="alert">' . esc_html__( 'Die PS-Community-Freundschaft ist nicht mehr verfügbar oder wurde blockiert. Eine Annahme ist deshalb nicht möglich.', 'ps-pen-paper' ) . '</p>';
			}
			ps_pen_paper_play_button( $round['post'], $page_id, $state, 'decline_invite', __( 'Gruppeneinladung ablehnen', 'ps-pen-paper' ) );
			echo '</article>';
		}
	}
}

function ps_pen_paper_render_friend_round_invitations( $source, $round, $page_id, $state, $controller ) {
	if ( ! isset( $state['invitations'] ) ) { return; }
	if ( ps_pen_paper_can_decline_play_invitation( $state ) && in_array( $state['status'], array( 'lobby', 'paused' ), true ) ) {
		ps_pen_paper_play_button( $round, $page_id, $state, 'decline_invite', __( 'Gruppeneinladung ablehnen', 'ps-pen-paper' ) );
	}
	if ( ! $controller ) { return; }
	$labels = array( 'offered' => __( 'Wartet auf Annahme und Charakterwahl', 'ps-pen-paper' ),
		'accepted' => __( 'Angenommen', 'ps-pen-paper' ), 'declined' => __( 'Abgelehnt', 'ps-pen-paper' ), 'revoked' => __( 'Zurückgezogen', 'ps-pen-paper' ) );
	echo '<h3>' . esc_html__( 'Eingeladene Freunde', 'ps-pen-paper' ) . '</h3><ul>';
	foreach ( $state['invitations'] as $entry ) {
		echo '<li>' . esc_html( ps_pen_paper_adventure_group_user_label( $entry['user_id'] ) . ' · ' . $labels[ $entry['status'] ] );
		if ( 'offered' === $entry['status'] && in_array( $state['status'], array( 'lobby', 'paused' ), true ) ) {
			if ( ! ps_pen_paper_campaign_role( $source, $entry['user_id'] ) ) {
				echo '<p>' . esc_html__( 'Kampagnenzugang noch offen; die vorhandene Kampagneneinladung muss zuerst freigegeben und angenommen werden.', 'ps-pen-paper' ) . '</p>';
			}
			ps_pen_paper_form_start( 'change_play_round', 'pspp_campaign_' . $round->ID, $round->ID, $page_id );
			echo '<input type="hidden" name="play_revision" value="' . esc_attr( $state['revision'] ) . '"><input type="hidden" name="play_action" value="revoke_invite">' .
				'<input type="hidden" name="play_invitee" value="' . esc_attr( $entry['user_id'] ) . '"><button type="submit">' . esc_html__( 'Gruppeneinladung zurückziehen', 'ps-pen-paper' ) . '</button></form>';
		}
		echo '</li>';
	}
	echo '</ul>';
	if ( 'lobby' === $state['status'] && in_array( 'offered', array_column( $state['invitations'], 'status' ), true ) ) {
		echo '<p>' . esc_html__( 'Der Gastgeber startet, sobald die offenen Einladungen beantwortet oder ausdrücklich zurückgezogen wurden.', 'ps-pen-paper' ) . '</p>';
	}
}

function ps_pen_paper_render_play_round( $source, $round, $page_id, $state, $character_id = 0 ) {
	$member = ps_pen_paper_play_participant( $state, get_current_user_id() );
	$gm = ps_pen_paper_can_manage_campaign( $source ) && ps_pen_paper_management_view( $page_id );
	$controller = $gm || ( 'guided' !== $state['mode'] && $state['host_id'] === get_current_user_id() );
	if ( ! $gm && ! ps_pen_paper_can_open_friend_round( $state ) ) { echo '<p role="alert">' . esc_html__( 'Diese Freundesgruppe ist nur für eingeladene Personen verfügbar.', 'ps-pen-paper' ) . '</p>'; return; }
	if ( 'solo' === $state['mode'] && ! $controller ) { echo '<p role="alert">' . esc_html__( 'Diese Solo-Runde ist privat.', 'ps-pen-paper' ) . '</p>'; return; }
	if ( ( $member || $gm ) && ( $character_id || 'lobby' !== $state['status'] ) ) {
		ps_pen_paper_render_table( $round, $page_id, $character_id );
		return;
	}
	ps_pen_paper_render_play_round_controls( $source, $round, $page_id, $state );
}

function ps_pen_paper_render_play_round_controls( $source, $round, $page_id, $state ) {
	$member = ps_pen_paper_play_participant( $state, get_current_user_id() );
	$gm = ps_pen_paper_can_manage_campaign( $source ) && ps_pen_paper_management_view( $page_id );
	$controller = $gm || ( 'guided' !== $state['mode'] && $state['host_id'] === get_current_user_id() );
	$active = array_filter( $state['participants'], static function ( $participant ) { return $participant['active']; } );
	if ( ! $gm && ! ps_pen_paper_can_open_friend_round( $state ) ) { echo '<p role="alert">' . esc_html__( 'Diese Freundesgruppe ist nur für eingeladene Personen verfügbar.', 'ps-pen-paper' ) . '</p>'; return; }
	if ( 'solo' === $state['mode'] && ! $controller ) { echo '<p role="alert">' . esc_html__( 'Diese Solo-Runde ist privat.', 'ps-pen-paper' ) . '</p>'; return; }
	?>
	<section class="pspp-play-round"><header>
		<h2><?php echo esc_html( $source->post_title . ' · ' . $round->post_title . ' · ' . ps_pen_paper_play_modes()[ $state['mode'] ] ); ?></h2>
		<p><?php echo esc_html( ps_pen_paper_play_statuses()[ $state['status'] ] ); ?></p>
		<a href="<?php echo esc_url( ps_pen_paper_play_url( $page_id, $source->ID, $state['mode'] ) ); ?>"><?php esc_html_e( 'Zu den Runden dieser Kampagne', 'ps-pen-paper' ); ?></a>
		<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id ) ); ?>"><?php esc_html_e( 'Zur Game-Lounge', 'ps-pen-paper' ); ?></a>
	</header>
	<?php ps_pen_paper_render_notice(); ?>
	<?php ps_pen_paper_render_bookings( $source ); ?>
	<?php ps_pen_paper_render_bookings( $round ); ?>
	<?php ps_pen_paper_render_booking_settings( $round, $page_id ); ?>
	<?php ps_pen_paper_render_friend_round_invitations( $source, $round, $page_id, $state, $controller ); ?>
	<h3><?php esc_html_e( 'Teilnehmer', 'ps-pen-paper' ); ?></h3>
	<?php if ( ! $active ) : ?><p><?php esc_html_e( 'Noch kein Charakter ausgewählt.', 'ps-pen-paper' ); ?></p><?php endif; ?>
	<ul>
		<?php foreach ( $state['participants'] as $participant ) :
			if ( ! $participant['active'] ) { continue; }
			$identity = ps_pen_paper_can_view_campaign( $round ) ? ps_pen_paper_table_identity( $round, $participant['user_id'] ) : array(); ?>
			<li><?php echo esc_html( ps_pen_paper_adventure_group_user_label( $participant['user_id'] ) ); ?><?php
				if ( is_wp_error( $identity ) ) { echo '<span role="alert">' . esc_html( $identity->get_error_message() ) . '</span>'; }
				elseif ( isset( $identity['name'] ) ) { echo ' · ' . esc_html( $identity['name'] ); } ?></li>
		<?php endforeach; ?>
	</ul>
	<?php if ( $member ) :
		$link = ps_pen_paper_get_universe_link( $source->ID );
		if ( ! is_wp_error( $link ) && ps_pen_paper_community_url() ) : ?>
			<a href="<?php echo esc_url( ps_pen_paper_community_url( 0, $member['source_id'], $link['universe_id'] ) ); ?>"><?php esc_html_e( 'Mein Charakter · Werte und Inventar', 'ps-pen-paper' ); ?></a>
		<?php endif;
		ps_pen_paper_play_button( $round, $page_id, $state, 'leave', __( 'Runde verlassen', 'ps-pen-paper' ) );
	endif;
	if ( in_array( $state['status'], array( 'lobby', 'paused' ), true ) && ( 'solo' !== $state['mode'] || $state['host_id'] === get_current_user_id() ) ) :
		$choices = ps_pen_paper_play_characters( $source, $round->ID );
		if ( is_wp_error( $choices ) ) : ?><p role="alert"><?php echo esc_html( $choices->get_error_message() ); ?></p>
		<?php elseif ( $member && 1 === count( $choices ) && isset( $choices[ $member['source_id'] ] ) ) : ?>
		<?php elseif ( $choices ) :
			ps_pen_paper_form_start( 'change_play_round', 'pspp_campaign_' . $round->ID, $round->ID, $page_id ); ?>
			<input type="hidden" name="play_revision" value="<?php echo esc_attr( $state['revision'] ); ?>"><input type="hidden" name="play_action" value="join">
			<label><?php esc_html_e( 'Mein Charakter', 'ps-pen-paper' ); ?><select name="play_character" required>
				<?php foreach ( $choices as $id => $choice ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( $member['source_id'] ?? 0, $id ); ?> <?php disabled( ! empty( $choice['unavailable'] ) ); ?>><?php echo esc_html( $choice['state']['active']['name'] . ( empty( $choice['unavailable'] ) ? '' : ' · ' . $choice['unavailable'] ) ); ?></option><?php endforeach; ?>
			</select></label>
			<?php foreach ( $choices as $id => $choice ) : ?><input type="hidden" name="play_character_revisions[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $choice['state']['revision'] ); ?>"><?php endforeach; ?>
			<button type="submit"><?php echo esc_html( $member ? __( 'Charakter wechseln', 'ps-pen-paper' ) : ( isset( $state['invitations'] ) ? __( 'Einladung annehmen und mit diesem Charakter teilnehmen', 'ps-pen-paper' ) : __( 'Mit diesem Charakter teilnehmen', 'ps-pen-paper' ) ) ); ?></button></form>
		<?php elseif ( ! $gm || 'guided' !== $state['mode'] ) : ?>
			<p><?php esc_html_e( 'Du brauchst einen eigenen freigegebenen Charakter mit passenden Regeln.', 'ps-pen-paper' ); ?></p>
			<?php $link = ps_pen_paper_get_universe_link( $source->ID );
			if ( is_wp_error( $link ) ) { echo '<p role="alert">' . esc_html( $link->get_error_message() ) . '</p>'; }
			elseif ( ps_pen_paper_community_url() ) { ?><a href="<?php echo esc_url( ps_pen_paper_community_url( 0, 0, $link['universe_id'] ) ); ?>"><?php esc_html_e( 'Meine Charaktere dieses Universums öffnen', 'ps-pen-paper' ); ?></a><?php } ?>
		<?php endif;
	endif;
	if ( $controller && 'completed' !== $state['status'] ) {
		if ( 'active' === $state['status'] ) { ps_pen_paper_play_button( $round, $page_id, $state, 'pause', __( 'Pause · Teilnehmer ändern', 'ps-pen-paper' ) ); }
		elseif ( $active && ( 'solo' !== $state['mode'] || $state['host_id'] === get_current_user_id() ) &&
			( ! isset( $state['invitations'] ) || 'paused' === $state['status'] ||
				! in_array( 'offered', array_column( $state['invitations'], 'status' ), true ) ) ) {
			ps_pen_paper_play_button( $round, $page_id, $state, 'start', 'lobby' === $state['status'] ? __( 'Spiel starten', 'ps-pen-paper' ) : __( 'Spiel fortsetzen', 'ps-pen-paper' ) );
		}
		ps_pen_paper_play_button( $round, $page_id, $state, 'complete', __( 'Runde abschließen', 'ps-pen-paper' ) );
	}
	if ( 'lobby' === $state['status'] || 'paused' === $state['status'] ) {
		$message = 'lobby' === $state['status']
			? ( $member ? ( $controller ? __( 'Dein Charakter ist ausgewählt. Sobald alle bereit sind, kannst du das Spiel starten.', 'ps-pen-paper' ) :
				__( 'Dein Charakter ist ausgewählt. Der Gastgeber startet das gemeinsame Spiel.', 'ps-pen-paper' ) ) :
				( $gm && 'guided' === $state['mode'] ? __( 'Die Spieler wählen ihre Charaktere. Danach kannst du das Spiel starten.', 'ps-pen-paper' ) : __( 'Wähle deinen Charakter. Die Runde beginnt erst mit „Spiel starten“.', 'ps-pen-paper' ) ) )
			: __( 'Während der Pause können Spieler beitreten oder ihre Teilnahme beenden.', 'ps-pen-paper' );
		echo '<p>' . esc_html( $message ) . '</p>';
	} elseif ( 'completed' === $state['status'] ) {
		echo '<p>' . esc_html__( 'Diese Runde ist abgeschlossen.', 'ps-pen-paper' ) . '</p>';
	} elseif ( ! $member && ! $gm ) { echo '<p>' . esc_html__( 'Das Spiel läuft. Eine neue Teilnahme ist möglich, sobald die Runde pausiert.', 'ps-pen-paper' ) . '</p>'; }
	echo '</section>';
}
