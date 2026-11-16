<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_handle_adventure_group_action() {
	$campaign_id = ps_pen_paper_posted_id( 'campaign_id' ); $page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	check_admin_referer( 'pspp_campaign_' . $campaign_id );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! $campaign || ! ps_pen_paper_can_access_site() || ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) ) {
		ps_pen_paper_fail( ps_pen_paper_adventure_group_error()->get_error_message() );
	}
	if ( 'create' === ps_pen_paper_posted_text( 'group_operation' ) ) {
		ps_pen_paper_fail( __( 'Lege die Runde über die Game-Lounge an und wähle dort die Teilnehmer und Charaktere.', 'ps-pen-paper' ) );
	}
	$result = ps_pen_paper_write_adventure_group( $campaign, ps_pen_paper_posted_id( 'group_id' ), ps_pen_paper_posted_text( 'group_revision' ),
		ps_pen_paper_posted_text( 'group_operation' ), array( 'title' => ps_pen_paper_posted_text( 'group_title' ),
			'rule' => ps_pen_paper_posted_text( 'group_rule' ), 'adventure_id' => ps_pen_paper_posted_id( 'adventure_id' ),
			'adventure_revision' => ps_pen_paper_posted_text( 'adventure_revision' ), 'character_id' => ps_pen_paper_posted_id( 'character_id' ),
			'edge_id' => ps_pen_paper_posted_text( 'adventure_edge' ) ) );
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	$group_id = ps_pen_paper_posted_id( 'group_id' ) ?: $result;
	$url = $page_id ? ps_pen_paper_game_url( $page_id, $campaign_id ) : ps_pen_paper_admin_url( $campaign_id );
	wp_safe_redirect( $url . '#pspp-adventure-group-' . $group_id ); exit;
}

function ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $revision, $operation ) {
	ps_pen_paper_form_start( 'group_adventure', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id );
	?>
	<input type="hidden" name="group_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="group_revision" value="<?php echo esc_attr( $revision ); ?>">
	<input type="hidden" name="group_operation" value="<?php echo esc_attr( $operation ); ?>">
	<?php
}

function ps_pen_paper_adventure_group_user_label( $user_id ) {
	$user = get_userdata( $user_id );
	return $user ? $user->display_name : __( 'Gelöschter Benutzer', 'ps-pen-paper' );
}

function ps_pen_paper_render_adventure_groups( $campaign, $page_id, $on_table = false ) {
	$round = ps_pen_paper_play_round( $campaign->ID );
	if ( is_wp_error( $round ) || ( $round && 'group' !== $round['mode'] ) ) { return; }
	$records = ps_pen_paper_adventure_group_records( $campaign, ! ps_pen_paper_management_view( $page_id ) );
	if ( is_wp_error( $records ) ) { echo '<p role="alert">' . esc_html( $records->get_error_message() ) . '</p>'; return; }
	$gm = ps_pen_paper_management_view( $page_id ) && ps_pen_paper_can_manage_campaign( $campaign ); $own_characters = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_character', 'post_status' => 'private', 'post_parent' => $campaign->ID,
		'author' => get_current_user_id(), 'posts_per_page' => -1 ) ) as $post ) {
		$state = ps_pen_paper_character_state( $campaign, $post );
		if ( is_wp_error( $state ) ) { echo '<p role="alert">' . esc_html( $state->get_error_message() ) . '</p>'; continue; }
		if ( $state['active'] ) { $own_characters[ $post->ID ] = $state['active']['name']; }
	}
	$statuses = array( 'active' => __( 'Aktiv', 'ps-pen-paper' ), 'paused' => __( 'Pausiert', 'ps-pen-paper' ), 'completed' => __( 'Abgeschlossen', 'ps-pen-paper' ) );
	?>
	<section class="pspp-adventure-groups"><?php if ( ! $on_table ) : ?><h3><?php esc_html_e( 'Gemeinsam spielen', 'ps-pen-paper' ); ?></h3><?php endif; ?>
	<?php if ( ! $round ) : ?>
	<p><a href="<?php echo esc_url( ps_pen_paper_play_url( $page_id, $campaign->ID, 'group' ) ); ?>"><?php esc_html_e( 'Gruppenrunden in der Game-Lounge öffnen', 'ps-pen-paper' ); ?></a></p>
	<details><summary><?php esc_html_e( 'Spielregeln und Freigaben', 'ps-pen-paper' ); ?></summary>
	<p><?php esc_html_e( 'Ein gemeinsamer Szenenstand für alle Teilnehmer. Der Vorschlag legt fest, welcher Besitzer die Entscheidung ausführt. Nur dessen Charakter würfelt, bezahlt persönliche Kosten und erhält persönliche Belohnungen. Fremde Charakterwerte bleiben privat.', 'ps-pen-paper' ); ?></p>
	<p><?php esc_html_e( 'Teilnahmewechsel, Pausieren und Änderungen der Entscheidungsregel verwerfen offene Vorschläge und Freigaben, aber niemals den bisherigen Fortschritt. Pro Benutzer kann eine Figur gleichzeitig aktiv teilnehmen. Bis zu 100 Figuren einschließlich früherer Teilnehmer bleiben im Verlauf zugeordnet.', 'ps-pen-paper' ); ?></p>
	</details>
	<?php endif; ?>
	<?php if ( ! $records ) : ?><p><?php echo esc_html( $gm ? __( 'Noch kein gemeinsames Abenteuer gestartet.', 'ps-pen-paper' ) : __( 'Die Spielleitung hat noch kein gemeinsames Abenteuer gestartet.', 'ps-pen-paper' ) ); ?></p><?php endif; ?>
	<?php foreach ( $records as $id => $record ) : $group = $record['state']; ?>
		<article class="pspp-adventure-group" id="pspp-adventure-group-<?php echo esc_attr( $id ); ?>" data-group-id="<?php echo esc_attr( $id ); ?>">
			<h4><?php echo esc_html( $record['title'] . ' · ' . $statuses[ $record['status'] ] ); ?></h4>
			<?php $member = $group ? ps_pen_paper_adventure_group_member( $campaign, $group, get_current_user_id() ) : null;
			if ( ! $round && ! $member && 'completed' !== $record['status'] ) :
				if ( $own_characters ) :
					$raw_group = $group ?? get_post_meta( $id, '_pspp_adventure_group', true );
					$available = array();
					foreach ( $own_characters as $character_id => $name ) {
						$context = ps_pen_paper_adventure_character( $campaign, $character_id, true );
						$enrolled = in_array( get_current_user_id(), array_column( $raw_group['participants'], 'owner_id' ), true );
						$access = is_wp_error( $context ) ? $context : ps_pen_paper_scenario_access( $campaign, $raw_group['adventure_id'], $raw_group['snapshot'], $context, 'group', $enrolled ? $id : 0 );
						ps_pen_paper_render_scenario_access( $raw_group['snapshot'], $access );
						if ( ! is_wp_error( $access ) && $access['met'] ) { $available[ $character_id ] = $name; }
					}
					if ( $available ) :
					ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $record['revision'], 'join' ); ?>
					<label><?php esc_html_e( 'Mit eigenem freigegebenem Charakter teilnehmen', 'ps-pen-paper' ); ?><select name="character_id">
						<?php foreach ( $available as $character_id => $name ) : ?><option value="<?php echo esc_attr( $character_id ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?>
					</select></label><button type="submit"><?php esc_html_e( 'Mitspielen', 'ps-pen-paper' ); ?></button></form>
				<?php endif; else : ?>
					<?php if ( $gm ) : ?><p><?php esc_html_e( 'Du leitest diese Runde. Deine Spieler können mit ihrem freigegebenen Charakter mitspielen.', 'ps-pen-paper' ); ?></p>
					<?php else : ?><p><?php esc_html_e( 'Zum Mitspielen brauchst du einen eigenen freigegebenen Charakter.', 'ps-pen-paper' ); ?></p>
						<p><a class="button" href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-sheet' ); ?>"><?php esc_html_e( 'Meinen Charakter öffnen / erstellen', 'ps-pen-paper' ); ?></a></p>
					<?php endif; ?>
				<?php endif;
			endif; ?>
			<?php if ( ! $group ) : ?><p><?php esc_html_e( 'Szenen und Verlauf sind erst nach eigener Teilnahme sichtbar.', 'ps-pen-paper' ); ?></p></article><?php continue; endif; ?>
			<?php if ( $on_table ) : ?><details class="pspp-table-run-info"><summary><?php esc_html_e( 'Entscheidungsregel', 'ps-pen-paper' ); ?></summary><?php endif; ?>
			<p><?php echo esc_html( $round && 'any' === $group['rule'] ? __( 'Jeder Teilnehmer kann mit seinem Charakter eine Aktion ausführen.', 'ps-pen-paper' ) : ps_pen_paper_adventure_group_rules()[ $group['rule'] ] ); ?></p>
			<?php if ( $on_table ) : ?></details><?php endif; ?>
			<?php if ( ! $round ) : ?>
			<ul><?php foreach ( $group['participants'] as $participant ) : ?>
				<li><?php echo esc_html( ps_pen_paper_adventure_group_user_label( $participant['owner_id'] ) . ' · ' . ( $participant['active'] ? __( 'Nimmt teil', 'ps-pen-paper' ) : __( 'Abwesend', 'ps-pen-paper' ) ) ); ?>
					<?php if ( ! $round && $participant['active'] && ( $gm || $participant['owner_id'] === get_current_user_id() ) ) :
						ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $record['revision'], 'leave' ); ?>
						<input type="hidden" name="character_id" value="<?php echo esc_attr( $participant['character_id'] ); ?>">
						<button type="submit"><?php esc_html_e( 'Teilnahme beenden', 'ps-pen-paper' ); ?></button></form>
					<?php endif; ?>
				</li>
			<?php endforeach; ?></ul>
			<?php endif; ?>
			<?php if ( $on_table ) : ?><div class="pspp-table-adventure-board"><?php endif; ?>
			<?php if ( $on_table && $group['history'] ) {
				$last = end( $group['history'] );
				ps_pen_paper_render_adventure_table_result( $last['decision'], $gm || $last['owner_id'] === get_current_user_id() );
			} ?>
			<?php if ( $on_table ) : ?><div class="pspp-table-story-card" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Aktuelle Szene', 'ps-pen-paper' ); ?>"><?php endif; ?>
			<?php if ( $group['scene_id'] ) : $scene = $group['snapshot']['scenes'][ $group['scene_id'] ];
				if ( $on_table ) { ps_pen_paper_render_adventure_scene_image( $campaign, $scene ); } ?>
				<h5><?php echo esc_html( $scene['title'] ); ?></h5><p class="pspp-multiline"><?php echo esc_html( $scene['text'] ); ?></p>
			<?php endif; ?>
			<?php ps_pen_paper_render_adventure_world_progress( $group['snapshot'], $group['scene_id'], array_column( $group['history'], 'decision' ) ); ?>
			<?php if ( 'completed' === $group['status'] ) : ?><p role="status"><?php echo esc_html( ps_pen_paper_scenario_outcomes()[ ps_pen_paper_scenario_outcome( $group['snapshot'], array_column( $group['history'], 'decision' ) ) ] ); ?></p><?php endif; ?>
			<?php if ( $group['scene_id'] ) { ps_pen_paper_render_wiki_target_link( $campaign, 'scene:' . $group['scene_id'] ); } ?>
			<?php if ( $on_table ) : ?></div><div class="pspp-table-actions"><h6><?php echo esc_html( 'completed' === $group['status'] ? __( 'Ergebnis', 'ps-pen-paper' ) : __( 'Deine Aktionen', 'ps-pen-paper' ) ); ?></h6><?php endif; ?>
			<?php if ( ! $round && $gm && 'completed' !== $group['status'] ) :
				ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $record['revision'], 'active' === $group['status'] ? 'pause' : 'resume' ); ?>
				<button type="submit"><?php echo esc_html( 'active' === $group['status'] ? __( 'Gruppendurchlauf pausieren', 'ps-pen-paper' ) : __( 'Gruppendurchlauf fortsetzen', 'ps-pen-paper' ) ); ?></button></form>
				<details><summary><?php esc_html_e( 'Entscheidungsregel anpassen', 'ps-pen-paper' ); ?></summary>
				<?php ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $record['revision'], 'configure' ); ?>
				<label><?php esc_html_e( 'Entscheidungsregel ändern', 'ps-pen-paper' ); ?><select name="group_rule">
					<?php foreach ( ps_pen_paper_adventure_group_rules() as $rule => $label ) : ?><option value="<?php echo esc_attr( $rule ); ?>"<?php selected( $group['rule'], $rule ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
				</select></label><button type="submit"><?php esc_html_e( 'Entscheidungsregel speichern', 'ps-pen-paper' ); ?></button></form></details>
			<?php endif; ?>
			<?php if ( 'active' === $group['status'] ) :
				$proposal = $group['proposal'];
				if ( $proposal ) :
					$edge = ps_pen_paper_adventure_group_edge( $group, $proposal['edge_id'] );
					$needs_gm = 'gm' === $group['rule'] || ! empty( $edge['gm_review'] );
					$approved = $proposal['reviewer_id'] && ps_pen_paper_adventure_reviewer_authority( $campaign, $proposal['reviewer_id'] );
					$ready = ps_pen_paper_adventure_group_quorum( $group['rule'], ps_pen_paper_adventure_group_electorate( $group ), $proposal['votes'], $proposal['reviewer_id'] ) && ( ! $needs_gm || $approved ); ?>
					<p><strong><?php echo esc_html( $edge['choice'] ); ?></strong> · <?php echo esc_html( ps_pen_paper_adventure_group_user_label( $proposal['owner_id'] ) ); ?></p>
					<p role="status"><?php
						/* translators: 1: votes, 2: active participants. */
						echo esc_html( sprintf( __( 'Zustimmungen: %1$d von %2$d aktiven Teilnehmern.', 'ps-pen-paper' ), count( $proposal['votes'] ), count( ps_pen_paper_adventure_group_electorate( $group ) ) ) );
						if ( $needs_gm ) { echo ' ' . esc_html( $approved ? __( 'Spielleiterfreigabe vorhanden.', 'ps-pen-paper' ) : __( 'Aktuelle Spielleiterfreigabe benötigt.', 'ps-pen-paper' ) ); }
					?></p>
					<?php if ( $member ) :
						$voted = in_array( get_current_user_id(), $proposal['votes'], true );
						ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $record['revision'], $voted ? 'unvote' : 'vote' ); ?>
						<button type="submit"><?php echo esc_html( $voted ? __( 'Zustimmung zurückziehen', 'ps-pen-paper' ) : __( 'Vorschlag zustimmen', 'ps-pen-paper' ) ); ?></button></form>
					<?php endif; ?>
					<?php if ( $gm && $needs_gm ) :
						ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $record['revision'], 'approve' ); ?>
						<button type="submit"<?php disabled( $proposal['reviewer_id'] > 0 ); ?>><?php esc_html_e( 'Gruppenentscheidung freigeben', 'ps-pen-paper' ); ?></button></form>
					<?php endif; ?>
					<?php if ( $gm || ( $member && $proposal['owner_id'] === get_current_user_id() ) ) :
						ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $record['revision'], 'cancel' ); ?>
						<button type="submit"><?php esc_html_e( 'Gruppenvorschlag verwerfen', 'ps-pen-paper' ); ?></button></form>
					<?php endif; ?>
					<?php if ( $member && $proposal['owner_id'] === get_current_user_id() ) :
						$character = ps_pen_paper_adventure_character( $campaign, $proposal['character_id'], true );
						if ( is_wp_error( $character ) ) { echo '<p role="alert">' . esc_html( $character->get_error_message() ) . '</p>'; }
						else {
							$rules = ps_pen_paper_get_rules( $campaign->ID );
							$requirement = ps_pen_paper_adventure_requirement_value( $edge, $rules, $character['state'] );
							if ( $requirement ) { echo '<p>' . esc_html( is_wp_error( $requirement ) ? $requirement->get_error_message() : ps_pen_paper_adventure_requirement_label( $requirement ) ) . '</p>'; }
							$conditions = ps_pen_paper_evaluate_adventure_conditions( $edge, $rules, $character['state'], array_column( $group['history'], 'decision' ), $group['snapshot']['edges'], $group['snapshot']['states'] ?? array() );
							if ( $conditions ) { echo '<p>' . esc_html( is_wp_error( $conditions ) ? $conditions->get_error_message() : ps_pen_paper_adventure_conditions_label( $conditions ) ) . '</p>'; }
							$cost = isset( $edge['resource_cost'] ) ? ps_pen_paper_character_resource_cost( $campaign, $character['post'], $edge['resource_cost']['resource'], $edge['resource_cost']['amount'] ) : null;
							if ( $cost ) { echo '<p>' . esc_html( is_wp_error( $cost ) ? $cost->get_error_message() : sprintf(
								/* translators: 1: resource name, 2: cost, 3: available points, 4: cost timing. */
								__( 'Kosten: %1$s %2$d; verfügbar %3$d. %4$s.', 'ps-pen-paper' ), $cost['label'], $cost['amount'], $cost['before'],
								ps_pen_paper_adventure_cost_timings()[ $edge['resource_cost']['timing'] ] ) ) . '</p>'; }
							$effective_reward = ps_pen_paper_scenario_reward( $group['snapshot'], $edge, $member['scenario_attempt'] ?? 0 );
							$reward = $effective_reward ? ps_pen_paper_adventure_reward_context( $campaign, $character['post'], $effective_reward ) : null;
							$items = isset( $edge['items'] ) ? ps_pen_paper_adventure_items_result( $edge['items'], (int) $character['post']->ID ) : null;
							if ( $items ) { echo '<p>' . esc_html( is_wp_error( $items ) ? $items->get_error_message() : ps_pen_paper_adventure_items_label( $items ) ) . '</p>'; }
							if ( $reward ) { echo '<p>' . esc_html( is_wp_error( $reward ) ? $reward->get_error_message() : ps_pen_paper_adventure_reward_label( $effective_reward ) ) . '</p>'; }
							$character_access = ps_pen_paper_require_character_game_action( $campaign, (int) $character['post']->ID, $edge['character_conditions'] ?? array() );
							if ( is_wp_error( $character_access ) ) { echo '<p role="status">' . esc_html( $character_access->get_error_message() ) . '</p>'; }
							$ready = $ready && ! is_wp_error( $character_access ) && ! is_wp_error( $requirement ) && ( ! $requirement || $requirement['value'] >= $requirement['minimum'] ) &&
								! is_wp_error( $conditions ) && ( ! $conditions || $conditions['met'] ) && ! is_wp_error( $cost ) && ( ! $cost || $cost['after'] >= $cost['minimum'] ) && ! is_wp_error( $reward ) &&
								! is_wp_error( $items ) && ( ! $items || $items['met'] );
							ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $record['revision'], 'execute' ); ?>
							<button type="submit"<?php disabled( ! $ready ); ?>><?php esc_html_e( 'Vereinbarte Gruppenentscheidung ausführen', 'ps-pen-paper' ); ?></button></form>
						<?php }
					endif; ?>
				<?php elseif ( $member ) : foreach ( $group['snapshot']['edges'] as $edge ) :
					if ( $edge['from_scene'] !== $group['scene_id'] ) { continue; }
					$effective_reward = ps_pen_paper_scenario_reward( $group['snapshot'], $edge, $member['scenario_attempt'] ?? 0 );
					$character_access = ps_pen_paper_require_character_game_action( $campaign, $member['character_id'], $edge['character_conditions'] ?? array() );
					ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $record['revision'], 'propose' ); ?>
					<input type="hidden" name="character_id" value="<?php echo esc_attr( $member['character_id'] ); ?>"><input type="hidden" name="adventure_edge" value="<?php echo esc_attr( $edge['id'] ); ?>">
					<button type="submit" <?php disabled( is_wp_error( $character_access ) ); ?>><?php echo esc_html( ( $round && 'any' === $group['rule'] ? __( 'Ausführen:', 'ps-pen-paper' ) : __( 'Vorschlagen:', 'ps-pen-paper' ) ) . ' ' . $edge['choice'] ); ?></button>
					<?php if ( is_wp_error( $character_access ) ) : ?><p role="status"><?php echo esc_html( $character_access->get_error_message() ); ?></p><?php endif; ?>
					<?php if ( $edge['expression'] ) : ?><p><?php echo esc_html( __( 'Serverprobe:', 'ps-pen-paper' ) . ' ' . $edge['expression'] . ' · ' . __( 'Erfolg ab', 'ps-pen-paper' ) . ' ' . $edge['threshold'] ); ?></p><?php endif; ?>
					<?php if ( isset( $edge['resource_cost'] ) ) : ?><p><?php echo esc_html( __( 'Persönliche Ressourcenkosten:', 'ps-pen-paper' ) . ' ' . $edge['resource_cost']['amount'] . ' · ' . ps_pen_paper_adventure_cost_timings()[ $edge['resource_cost']['timing'] ] ); ?></p><?php endif; ?>
					<?php if ( $effective_reward ) : ?><p><?php echo esc_html( ps_pen_paper_adventure_reward_label( $effective_reward ) . ' · ' . ps_pen_paper_adventure_reward_timings()[ $effective_reward['timing'] ] ); ?></p><?php endif; ?>
					</form>
				<?php endforeach; endif;
			endif; ?>
			<?php if ( $on_table ) : ?></div></div><?php endif; ?>
			<?php if ( $group['history'] ) : ?>
				<?php if ( $on_table ) : ?><details class="pspp-table-history"><summary><?php esc_html_e( 'Gemeinsamer Verlauf', 'ps-pen-paper' ); ?></summary><?php endif; ?>
				<h5><?php esc_html_e( 'Gemeinsamer Verlauf', 'ps-pen-paper' ); ?></h5><ol>
				<?php foreach ( $group['history'] as $entry ) : $decision = $entry['decision']; $detail = $decision['choice']; ?>
					<li><?php
						if ( $decision['roll'] ) {
							$detail .= ' · ';
							if ( $gm || $entry['owner_id'] === get_current_user_id() ) { $detail .= $decision['roll']['total'] . ' / ' . $decision['roll']['threshold'] . ' · '; }
							$detail .= $decision['roll']['success'] ? __( 'Erfolg', 'ps-pen-paper' ) : __( 'Misserfolg', 'ps-pen-paper' );
						}
						if ( $gm || $entry['owner_id'] === get_current_user_id() ) {
							if ( isset( $decision['requirement'] ) ) { $detail .= ' · ' . ps_pen_paper_adventure_requirement_label( $decision['requirement'] ); }
							if ( isset( $decision['conditions'] ) ) { $detail .= ' · ' . ps_pen_paper_adventure_conditions_label( $decision['conditions'] ); }
							if ( array_key_exists( 'resource_cost', $decision ) ) {
								$detail .= ' · ' . ( $decision['resource_cost'] ? ps_pen_paper_adventure_cost_label( $decision['resource_cost'] ) : __( 'Bei Misserfolg keine Kosten', 'ps-pen-paper' ) );
							}
							if ( array_key_exists( 'reward', $decision ) ) { $detail .= ' · ' . ps_pen_paper_adventure_reward_history_label( $group['snapshot'], $decision ); }
						}
						if ( isset( $decision['reaction'] ) ) { $detail .= ' · ' . ps_pen_paper_adventure_reaction_label( $decision['reaction'] ); }
						echo esc_html( $detail );
					?></li>
				<?php endforeach; ?></ol><?php if ( $on_table ) : ?></details><?php endif; ?><?php endif; ?>
		</article>
	<?php endforeach; ?></section>
	<?php
}
