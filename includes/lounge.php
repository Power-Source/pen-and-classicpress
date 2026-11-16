<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/play-rounds.php';
require_once __DIR__ . '/play-rounds-ui.php';

function ps_pen_paper_lounge_data() {
	$data = array( 'universes' => array(), 'campaigns' => array(), 'invitations' => ps_pen_paper_profile_invitations() );
	foreach ( ps_pen_paper_profile_universe_choices() as $choice ) {
		if ( $choice['error'] ) { return new WP_Error( 'invalid_universe', $choice['reason'] ); }
		$data['universes'][ $choice['id'] ] = $choice['name'];
	}
	$invited = array();
	foreach ( $data['invitations']['received'] as $invitation ) {
		if ( 'pspp_campaign' === $invitation['scope_type'] ) { $invited[ $invitation['scope_id'] ] = $invitation; }
		elseif ( 'offered' === $invitation['status'] ) { $data['universes'][ $invitation['scope_id'] ] = $invitation['name']; }
	}
	foreach ( get_posts( array( 'post_type' => 'pspp_campaign', 'post_status' => 'private', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC',
		'meta_query' => array( array( 'key' => '_pspp_play_round', 'compare' => 'NOT EXISTS' ) ) ) ) as $campaign ) {
		$accessible = ps_pen_paper_can_view_campaign( $campaign );
		if ( ! $accessible && ! isset( $invited[ $campaign->ID ] ) ) { continue; }
		$link = ps_pen_paper_get_universe_link( $campaign->ID );
		if ( is_wp_error( $link ) ) { return $link; }
		if ( $link['universe_id'] && ! isset( $data['universes'][ $link['universe_id'] ] ) ) {
			$data['universes'][ $link['universe_id'] ] = $link['name'];
		}
		$data['campaigns'][] = array( 'post' => $campaign, 'link' => $link, 'accessible' => $accessible,
			'role' => ps_pen_paper_campaign_role( $campaign, get_current_user_id() ),
			'invitation' => $invited[ $campaign->ID ] ?? null );
	}
	return $data;
}

function ps_pen_paper_lounge_universe_id( $data ) {
	$id = ps_pen_paper_frontend_id( 'pspp_universe' );
	if ( is_wp_error( $id ) ) { return $id; }
	if ( $id ) {
		return isset( $data['universes'][ $id ] ) ? $id :
			new WP_Error( 'forbidden', __( 'Dieses Universum ist für dich nicht verfügbar.', 'ps-pen-paper' ) );
	}
	foreach ( $data['campaigns'] as $row ) {
		if ( $row['role'] && $row['link']['universe_id'] ) { return $row['link']['universe_id']; }
	}
	return $data['universes'] ? (int) array_key_first( $data['universes'] ) : 0;
}

function ps_pen_paper_lounge_running_adventures( $campaign, $player_view = false ) {
	$result = array();
	$rounds = ps_pen_paper_play_rounds( $campaign );
	if ( is_wp_error( $rounds ) ) { return $rounds; }
	foreach ( $rounds as $row ) {
		$state = $row['state'];
		if ( 'completed' === $state['status'] || ( ! ps_pen_paper_play_participant( $state, get_current_user_id() ) &&
			$state['host_id'] !== get_current_user_id() && ( $player_view || 'gm' !== ps_pen_paper_campaign_role( $campaign, get_current_user_id() ) ) ) ) { continue; }
		$result[] = array( 'round_id' => (int) $row['post']->ID, 'title' => $row['post']->post_title,
			'mode' => ps_pen_paper_play_modes()[ $state['mode'] ], 'status' => ps_pen_paper_play_statuses()[ $state['status'] ] );
	}
	$groups = ps_pen_paper_adventure_group_records( $campaign, $player_view );
	if ( is_wp_error( $groups ) ) { return $groups; }
	foreach ( $groups as $id => $record ) {
		if ( 'completed' === $record['status'] || ! $record['state'] ) { continue; }
		$member = ps_pen_paper_adventure_group_member( $campaign, $record['state'], get_current_user_id() );
		$leading = ! $player_view && 'gm' === ps_pen_paper_campaign_role( $campaign, get_current_user_id() ) && ps_pen_paper_adventure_group_electorate( $record['state'] );
		if ( ! $member && ! $leading ) { continue; }
		$result[] = array( 'title' => $record['title'], 'character_id' => 0, 'anchor' => 'pspp-adventure-group-' . $id,
			'mode' => __( 'Automatische Gruppe', 'ps-pen-paper' ), 'paused' => 'paused' === $record['status'] );
	}
	foreach ( ps_pen_paper_get_characters( $campaign ) as $character ) {
		if ( (int) $character->post_author !== get_current_user_id() ) { continue; }
		$runs = ps_pen_paper_adventure_run_records( $campaign, (int) $character->ID );
		if ( is_wp_error( $runs ) ) { return $runs; }
		foreach ( $runs as $id => $run ) {
			if ( 'completed' === $run['status'] ) { continue; }
			$result[] = array( 'title' => $run['snapshot']['title'], 'character_id' => (int) $character->ID,
				'anchor' => 'pspp-adventure-run-' . $id, 'mode' => __( 'Solo-Abenteuer', 'ps-pen-paper' ),
				'paused' => 'paused' === $run['status'] );
		}
	}
	return $result;
}

function ps_pen_paper_render_lounge_modes( $campaign, $page_id ) {
	$options = ps_pen_paper_play_options( $campaign->ID );
	if ( is_wp_error( $options ) ) { echo '<p role="alert">' . esc_html( $options->get_error_message() ) . '</p>'; return; }
	echo '<nav class="pspp-actions" aria-label="' . esc_attr__( 'Spielmodus wählen', 'ps-pen-paper' ) . '">';
	foreach ( $options['modes'] as $mode ) {
		$adventures = 'guided' !== $mode ? ps_pen_paper_play_adventures( $campaign, $mode ) : array();
		if ( is_wp_error( $adventures ) ) { echo '<p role="alert">' . esc_html( $adventures->get_error_message() ) . '</p>'; continue; }
		if ( 'guided' !== $mode && ! $adventures ) { continue; }
		echo '<a class="button" href="' . esc_url( ps_pen_paper_play_url( $page_id, $campaign->ID, $mode ) ) . '">' . esc_html( ps_pen_paper_play_modes()[ $mode ] ) . '</a>';
	}
	echo '<a href="' . esc_url( ps_pen_paper_play_url( $page_id, $campaign->ID ) ) . '">' . esc_html__( 'Alle Runden', 'ps-pen-paper' ) . '</a></nav>';
}

function ps_pen_paper_render_lounge_campaign( $row, $page_id ) {
	$campaign = $row['post'];
	$style = ps_pen_paper_effective_style( (int) $campaign->ID );
	if ( is_wp_error( $style ) ) { echo '<p role="alert">' . esc_html( $style->get_error_message() ) . '</p>'; return; }
	?>
	<article class="pspp-card" style="<?php echo esc_attr( ps_pen_paper_style_variables( $style['colors'] ) ); ?>" data-lounge-campaign="<?php echo esc_attr( $campaign->ID ); ?>">
		<h3><?php echo esc_html( $campaign->post_title ); ?></h3>
		<?php if ( ! $row['accessible'] ) : ?>
			<p><?php esc_html_e( 'Du bist eingeladen. Nimm die Einladung an, um mitzuspielen.', 'ps-pen-paper' ); ?></p>
			<a href="#pspp-lounge-invitations"><?php esc_html_e( 'Zur Einladung', 'ps-pen-paper' ); ?></a>
		<?php else : ?>
			<p><?php echo esc_html( 'gm' === $row['role'] ? __( 'Du leitest diese Kampagne.', 'ps-pen-paper' ) :
				( $row['role'] ? __( 'Du gehörst zu dieser Kampagne.', 'ps-pen-paper' ) : __( 'Verwaltungszugriff', 'ps-pen-paper' ) ) ); ?></p>
			<p class="pspp-multiline"><?php echo esc_html( wp_html_excerpt( $campaign->post_content, 220, '…' ) ); ?></p>
			<?php if ( '1' === get_post_meta( $campaign->ID, '_pspp_archived', true ) ) : ?>
				<p><?php esc_html_e( 'Archiviert', 'ps-pen-paper' ); ?></p>
			<?php endif; ?>
			<nav class="pspp-actions" aria-label="<?php esc_attr_e( 'Kampagneninformationen', 'ps-pen-paper' ); ?>">
				<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-play' ); ?>"><?php esc_html_e( 'Story / Einleitung', 'ps-pen-paper' ); ?></a>
				<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-rulebook' ); ?>"><?php esc_html_e( 'Regelwerk', 'ps-pen-paper' ); ?></a>
				<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-handouts' ); ?>"><?php esc_html_e( 'Handouts', 'ps-pen-paper' ); ?></a>
				<a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-log' ); ?>"><?php esc_html_e( 'Spielverlauf', 'ps-pen-paper' ); ?></a>
			</nav>
			<?php ps_pen_paper_render_lounge_modes( $campaign, $page_id ); ?>
			<?php if ( $row['role'] && (int) $campaign->post_author !== get_current_user_id() ) : ?>
				<details class="pspp-fold"><summary><?php esc_html_e( 'Kampagne verlassen', 'ps-pen-paper' ); ?></summary>
					<?php $membership = ps_pen_paper_membership_revision( $campaign->ID );
					if ( is_wp_error( $membership ) ) : ?><p role="alert"><?php echo esc_html( $membership->get_error_message() ); ?></p>
					<?php else :
						ps_pen_paper_form_start( 'leave_campaign', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
						<input type="hidden" name="membership_revision" value="<?php echo esc_attr( $membership['revision'] ); ?>">
						<label><input type="checkbox" name="leave_confirmation" value="confirmed" required>
							<?php esc_html_e( 'Meine Teilnahme beenden. Meine Charaktere und der bisherige Spielverlauf bleiben erhalten.', 'ps-pen-paper' ); ?></label>
						<button type="submit"><?php esc_html_e( 'Kampagne verlassen', 'ps-pen-paper' ); ?></button></form>
					<?php endif; ?>
				</details>
			<?php endif; ?>
		<?php endif; ?>
	</article>
	<?php
}

function ps_pen_paper_render_lounge( $page_id, $data, $universe_id ) {
	?>
	<section class="pspp-lounge" id="pspp-lounge">
		<header class="pspp-lounge-header">
			<div><h2><?php esc_html_e( 'Game-Lounge', 'ps-pen-paper' ); ?></h2>
				<p><?php esc_html_e( 'Deine Welten, Kampagnen und laufenden Spiele.', 'ps-pen-paper' ); ?></p></div>
			<nav class="pspp-actions" aria-label="<?php esc_attr_e( 'Meine Spiele', 'ps-pen-paper' ); ?>">
				<a href="#pspp-lounge-running"><?php esc_html_e( 'Meine laufenden Spiele', 'ps-pen-paper' ); ?></a>
				<a href="#pspp-lounge-invitations"><?php esc_html_e( 'Einladungen', 'ps-pen-paper' ); ?></a>
				<?php if ( ps_pen_paper_community_url() ) : ?><a href="<?php echo esc_url( ps_pen_paper_community_url() ); ?>"><?php esc_html_e( 'Meine Charaktere', 'ps-pen-paper' ); ?></a><?php endif; ?>
			</nav>
		</header>
		<?php ps_pen_paper_render_booking_agenda( $page_id ); ?>
		<?php if ( count( $data['universes'] ) > 1 ) : ?>
			<form method="get" action="<?php echo esc_url( get_permalink( $page_id ) ); ?>" class="pspp-lounge-picker">
				<?php if ( ! get_option( 'permalink_structure' ) ) : ?><input type="hidden" name="page_id" value="<?php echo esc_attr( $page_id ); ?>"><?php endif; ?>
				<label for="pspp-lounge-universe"><?php esc_html_e( 'Universum auswählen', 'ps-pen-paper' ); ?></label>
				<select id="pspp-lounge-universe" name="pspp_universe">
					<?php foreach ( $data['universes'] as $id => $name ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( $universe_id, $id ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?>
				</select>
				<button type="submit"><?php esc_html_e( 'Universum anzeigen', 'ps-pen-paper' ); ?></button>
			</form>
		<?php endif; ?>
		<section class="pspp-lounge-world">
			<h2><?php echo esc_html( $universe_id ? $data['universes'][ $universe_id ] : __( 'Kampagnen ohne Universumszuordnung', 'ps-pen-paper' ) ); ?></h2>
			<?php $universe = $universe_id ? ps_pen_paper_get_universe( $universe_id ) : null;
			if ( $universe ) :
				$document = ps_pen_paper_universe_document( $universe_id );
				if ( is_wp_error( $document ) ) { echo '<p role="alert">' . esc_html( $document->get_error_message() ) . '</p>'; }
				elseif ( ! empty( $document['introduction'] ) ) { echo '<p class="pspp-multiline">' . esc_html( $document['introduction'] ) . '</p>'; }
				$wiki_url = ps_pen_paper_public_wiki_url( $universe ); ?>
				<nav class="pspp-actions" aria-label="<?php esc_attr_e( 'Weltinformationen', 'ps-pen-paper' ); ?>">
					<?php if ( is_wp_error( $wiki_url ) ) : ?><p role="alert"><?php echo esc_html( $wiki_url->get_error_message() ); ?></p>
					<?php elseif ( $wiki_url ) : ?><a class="button" href="<?php echo esc_url( $wiki_url ); ?>"><?php esc_html_e( 'Weltwiki öffnen', 'ps-pen-paper' ); ?></a><?php endif; ?>
					<?php if ( ps_pen_paper_community_url() ) : ?><a class="button" href="<?php echo esc_url( ps_pen_paper_community_url( 0, 0, $universe_id ) ); ?>"><?php esc_html_e( 'Charaktere dieser Welt', 'ps-pen-paper' ); ?></a><?php endif; ?>
				</nav>
			<?php endif; ?>
			<h3><?php esc_html_e( 'Kampagnen dieser Welt', 'ps-pen-paper' ); ?></h3>
			<div class="pspp-grid">
				<?php $found = false;
				foreach ( $data['campaigns'] as $row ) {
					if ( $row['link']['universe_id'] !== $universe_id ) { continue; }
					$found = true; ps_pen_paper_render_lounge_campaign( $row, $page_id );
				} ?>
			</div>
			<?php if ( ! $found ) : ?><p><?php esc_html_e( 'Hier hast du noch keinen Kampagnenzugang und keine Kampagneneinladung.', 'ps-pen-paper' ); ?></p><?php endif; ?>
		</section>
		<section id="pspp-lounge-running">
			<h2><?php esc_html_e( 'Meine Kampagnen und laufenden Spiele · alle Universen', 'ps-pen-paper' ); ?></h2>
			<ul class="pspp-lounge-running">
				<?php $found = false;
				foreach ( $data['campaigns'] as $row ) :
					if ( '1' === get_post_meta( $row['post']->ID, '_pspp_archived', true ) ) { continue; }
					$runs = ps_pen_paper_lounge_running_adventures( $row['post'], true );
					if ( is_wp_error( $runs ) ) { echo '<li role="alert">' . esc_html( $runs->get_error_message() ) . '</li>'; continue; }
					if ( ! $row['role'] && ! $runs ) { continue; }
					$found = true; $campaign = $row['post'];
					$style = ps_pen_paper_effective_style( (int) $campaign->ID );
					if ( is_wp_error( $style ) ) { echo '<li role="alert">' . esc_html( $style->get_error_message() ) . '</li>'; continue; } ?>
					<li style="<?php echo esc_attr( ps_pen_paper_style_variables( $style['colors'] ) ); ?>"><strong><?php echo esc_html( $campaign->post_title ); ?></strong>
						<span><?php echo esc_html( $row['link']['name'] ); ?></span>
						<a class="button" href="<?php echo esc_url( ps_pen_paper_play_url( $page_id, $campaign->ID ) ); ?>"><?php esc_html_e( 'Kampagne öffnen', 'ps-pen-paper' ); ?></a>
						<?php foreach ( $runs as $run ) : ?>
							<?php if ( isset( $run['round_id'] ) ) : ?>
								<p><a href="<?php echo esc_url( ps_pen_paper_play_url( $page_id, $campaign->ID, 'choose', $run['round_id'] ) ); ?>"><?php echo esc_html( $run['mode'] . ' · ' . $run['title'] . ' · ' . $run['status'] ); ?></a></p>
							<?php else : ?>
								<p><a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID, $run['character_id'] ) . '#' . $run['anchor'] ); ?>"><?php echo esc_html( $run['mode'] . ' · ' . $run['title'] . ' · ' .
									( $run['paused'] ? __( 'Pausiert', 'ps-pen-paper' ) : __( 'Fortsetzen', 'ps-pen-paper' ) ) ); ?></a></p>
							<?php endif; ?>
						<?php endforeach; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( ! $found ) : ?><p><?php esc_html_e( 'Du gehörst noch keiner Kampagne an. Deine Einladungen findest du darunter.', 'ps-pen-paper' ); ?></p><?php endif; ?>
			<?php $unassigned = array_filter( $data['campaigns'], static function ( $row ) use ( $universe_id ) { return 0 === $row['link']['universe_id'] && 0 !== $universe_id; } );
			if ( $unassigned ) : ?>
				<details class="pspp-fold"><summary><?php esc_html_e( 'Kampagnen ohne Universumszuordnung', 'ps-pen-paper' ); ?></summary><div class="pspp-grid">
					<?php foreach ( $unassigned as $row ) { ps_pen_paper_render_lounge_campaign( $row, $page_id ); } ?>
				</div></details>
			<?php endif; ?>
		</section>
		<section id="pspp-lounge-invitations"><?php ps_pen_paper_render_profile_invitations( $page_id ); ps_pen_paper_render_friend_round_offers( $data, $page_id ); ?></section>
	</section>
	<?php
}
