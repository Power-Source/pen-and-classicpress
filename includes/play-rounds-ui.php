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
	if ( 'save_play_options' === $operation ) {
		$modes = isset( $_POST['play_modes'] ) ? wp_unslash( $_POST['play_modes'] ) : array();
		$result = ps_pen_paper_save_play_options( $campaign, $modes, 'yes' === ps_pen_paper_posted_text( 'player_starts' ), ps_pen_paper_posted_text( 'play_revision' ) );
		$url = ps_pen_paper_play_url( $page_id, $id );
	} elseif ( 'create_play_round' === $operation ) {
		$result = ps_pen_paper_create_play_round( $campaign, ps_pen_paper_posted_text( 'play_mode' ), ps_pen_paper_posted_id( 'play_adventure' ),
			ps_pen_paper_posted_text( 'play_token' ), ps_pen_paper_posted_text( 'play_revision' ) );
		$url = ! is_wp_error( $result ) ? ps_pen_paper_play_url( $page_id, $id, 'choose', $result ) : '';
	} else {
		$state = ps_pen_paper_play_round( $id );
		if ( ! $state || is_wp_error( $state ) ) { ps_pen_paper_fail( ps_pen_paper_play_error()->get_error_message() ); }
		$action = ps_pen_paper_posted_text( 'play_action' );
		$character_id = ps_pen_paper_posted_id( 'play_character' );
		$source_revision = '';
		if ( 'join' === $action ) {
			$revisions = isset( $_POST['play_character_revisions'] ) ? wp_unslash( $_POST['play_character_revisions'] ) : array();
			if ( ! is_array( $revisions ) || ! isset( $revisions[ $character_id ] ) || ! is_string( $revisions[ $character_id ] ) ) { ps_pen_paper_fail( ps_pen_paper_play_error()->get_error_message() ); }
			$source_revision = $revisions[ $character_id ];
		}
		$result = ps_pen_paper_change_play_round( $campaign, $action, ps_pen_paper_posted_text( 'play_revision' ), $character_id, $source_revision );
		$url = ps_pen_paper_play_url( $page_id, $state['source_id'], 'choose', $id );
	}
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	wp_safe_redirect( $url ); exit;
}

function ps_pen_paper_render_play_options( $source, $page_id ) {
	if ( ! ps_pen_paper_can_manage_campaign( $source ) ) { return; }
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

function ps_pen_paper_render_play_directory( $source, $page_id, $mode = 'choose' ) {
	$options = ps_pen_paper_play_options( $source->ID ); $rounds = ps_pen_paper_play_rounds( $source );
	if ( is_wp_error( $options ) || is_wp_error( $rounds ) ) { echo '<p role="alert">' . esc_html( is_wp_error( $options ) ? $options->get_error_message() : $rounds->get_error_message() ) . '</p>'; return; }
	?>
	<section class="pspp-play-directory"><h2><?php echo esc_html( $source->post_title ); ?></h2>
		<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id ) ); ?>"><?php esc_html_e( 'Zur Game-Lounge', 'ps-pen-paper' ); ?></a>
		<?php ps_pen_paper_render_notice(); ps_pen_paper_render_lounge_modes( $source, $page_id ); ?>
		<h3><?php esc_html_e( 'Runden', 'ps-pen-paper' ); ?></h3>
		<div class="pspp-grid">
			<?php $found = false;
			foreach ( $rounds as $row ) :
				$state = $row['state']; $round = $row['post'];
				if ( ( 'choose' !== $mode && $state['mode'] !== $mode ) || 'completed' === $state['status'] ||
					( 'solo' === $state['mode'] && $state['host_id'] !== get_current_user_id() && ! ps_pen_paper_can_manage_campaign( $source ) ) ) { continue; }
				$names = array();
				foreach ( $state['participants'] as $participant ) {
					if ( $participant['active'] ) { $names[] = ps_pen_paper_adventure_group_user_label( $participant['user_id'] ); }
				}
				$found = true; ?>
				<article class="pspp-card"><h4><?php echo esc_html( $round->post_title . ' · ' . ps_pen_paper_play_modes()[ $state['mode'] ] ); ?></h4>
					<p><?php echo esc_html( ps_pen_paper_play_statuses()[ $state['status'] ] ); ?></p>
					<p><?php echo esc_html( implode( ', ', $names ) ); ?></p>
					<a class="button" href="<?php echo esc_url( ps_pen_paper_play_url( $page_id, $source->ID, 'choose', $round->ID ) ); ?>"><?php
						echo esc_html( 'lobby' === $state['status'] ? __( 'Wartebereich öffnen', 'ps-pen-paper' ) : __( 'Runde öffnen', 'ps-pen-paper' ) ); ?></a>
				</article>
			<?php endforeach; ?>
		</div>
		<?php if ( ! $found ) : ?><p><?php esc_html_e( 'Hier wartet noch keine Runde.', 'ps-pen-paper' ); ?></p><?php endif; ?>
		<?php if ( 'choose' !== $mode && in_array( $mode, $options['modes'], true ) &&
			( ps_pen_paper_can_manage_campaign( $source ) || ( 'guided' !== $mode && $options['player_starts'] ) ) ) :
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

function ps_pen_paper_render_play_round( $source, $round, $page_id, $state, $character_id = 0 ) {
	$member = ps_pen_paper_play_participant( $state, get_current_user_id() );
	$gm = ps_pen_paper_can_manage_campaign( $source );
	$controller = $gm || ( 'guided' !== $state['mode'] && $state['host_id'] === get_current_user_id() );
	$active = array_filter( $state['participants'], static function ( $participant ) { return $participant['active']; } );
	if ( 'solo' === $state['mode'] && ! $controller ) { echo '<p role="alert">' . esc_html__( 'Diese Solo-Runde ist privat.', 'ps-pen-paper' ) . '</p>'; return; }
	?>
	<section class="pspp-play-round"><header>
		<h2><?php echo esc_html( $source->post_title . ' · ' . $round->post_title . ' · ' . ps_pen_paper_play_modes()[ $state['mode'] ] ); ?></h2>
		<p><?php echo esc_html( ps_pen_paper_play_statuses()[ $state['status'] ] ); ?></p>
		<a href="<?php echo esc_url( ps_pen_paper_play_url( $page_id, $source->ID, $state['mode'] ) ); ?>"><?php esc_html_e( 'Zu den Runden dieser Kampagne', 'ps-pen-paper' ); ?></a>
		<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id ) ); ?>"><?php esc_html_e( 'Zur Game-Lounge', 'ps-pen-paper' ); ?></a>
	</header>
	<?php ps_pen_paper_render_notice(); ?>
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
				<?php foreach ( $choices as $id => $choice ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( $member['source_id'] ?? 0, $id ); ?>><?php echo esc_html( $choice['state']['active']['name'] ); ?></option><?php endforeach; ?>
			</select></label>
			<?php foreach ( $choices as $id => $choice ) : ?><input type="hidden" name="play_character_revisions[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $choice['state']['revision'] ); ?>"><?php endforeach; ?>
			<button type="submit"><?php echo esc_html( $member ? __( 'Charakter wechseln', 'ps-pen-paper' ) : __( 'Mit diesem Charakter teilnehmen', 'ps-pen-paper' ) ); ?></button></form>
		<?php elseif ( ! $gm || 'guided' !== $state['mode'] ) : ?>
			<p><?php esc_html_e( 'Du brauchst einen eigenen freigegebenen Charakter mit passenden Regeln.', 'ps-pen-paper' ); ?></p>
			<?php if ( ps_pen_paper_community_url() ) : ?><a href="<?php echo esc_url( ps_pen_paper_community_url() ); ?>"><?php esc_html_e( 'Meine Charaktere öffnen', 'ps-pen-paper' ); ?></a><?php endif; ?>
		<?php endif;
	endif;
	if ( $controller && 'completed' !== $state['status'] ) {
		if ( 'active' === $state['status'] ) { ps_pen_paper_play_button( $round, $page_id, $state, 'pause', __( 'Pause · Teilnehmer ändern', 'ps-pen-paper' ) ); }
		elseif ( $active && ( 'solo' !== $state['mode'] || $state['host_id'] === get_current_user_id() ) ) {
			ps_pen_paper_play_button( $round, $page_id, $state, 'start', 'lobby' === $state['status'] ? __( 'Spiel starten', 'ps-pen-paper' ) : __( 'Spiel fortsetzen', 'ps-pen-paper' ) );
		}
		ps_pen_paper_play_button( $round, $page_id, $state, 'complete', __( 'Runde abschließen', 'ps-pen-paper' ) );
	}
	if ( $character_id && ( $member || $gm ) ) {
		ps_pen_paper_render_table( $round, $page_id, $character_id );
	} elseif ( 'lobby' === $state['status'] || 'paused' === $state['status'] ) {
		$message = 'lobby' === $state['status']
			? ( $gm && 'guided' === $state['mode'] ? __( 'Die Spieler wählen ihre Charaktere. Danach kannst du das Spiel starten.', 'ps-pen-paper' ) : __( 'Wähle deinen Charakter. Die Runde beginnt erst mit „Spiel starten“.', 'ps-pen-paper' ) )
			: __( 'Während der Pause können Spieler beitreten oder ihre Teilnahme beenden.', 'ps-pen-paper' );
		echo '<p>' . esc_html( $message ) . '</p>';
	} elseif ( $member || $gm ) {
		if ( 'guided' === $state['mode'] ) { ps_pen_paper_render_table( $round, $page_id, 0 ); }
		elseif ( 'group' === $state['mode'] ) { ps_pen_paper_render_adventure_groups( $round, $page_id ); }
		elseif ( $member ) {
			$character = ps_pen_paper_get_character( $round, $member['character_id'] );
			if ( $character ) { ps_pen_paper_render_character_adventures( $round, $character, $page_id ); }
		}
	} elseif ( 'completed' === $state['status'] ) {
		echo '<p>' . esc_html__( 'Diese Runde ist abgeschlossen.', 'ps-pen-paper' ) . '</p>';
	} else { echo '<p>' . esc_html__( 'Das Spiel läuft. Eine neue Teilnahme ist möglich, sobald die Runde pausiert.', 'ps-pen-paper' ) . '</p>'; }
	echo '</section>';
}
