<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_handle_adventure_group_action() {
	$campaign_id = ps_pen_paper_posted_id( 'campaign_id' ); $page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	check_admin_referer( 'pspp_campaign_' . $campaign_id );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! $campaign || ! ps_pen_paper_can_access_site() || ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) ) {
		ps_pen_paper_fail( ps_pen_paper_adventure_group_error()->get_error_message() );
	}
	$result = ps_pen_paper_write_adventure_group( $campaign, ps_pen_paper_posted_id( 'group_id' ), ps_pen_paper_posted_text( 'group_revision' ),
		ps_pen_paper_posted_text( 'group_operation' ), array( 'title' => ps_pen_paper_posted_text( 'group_title' ),
			'rule' => ps_pen_paper_posted_text( 'group_rule' ), 'adventure_id' => ps_pen_paper_posted_id( 'adventure_id' ),
			'adventure_revision' => ps_pen_paper_posted_text( 'adventure_revision' ), 'character_id' => ps_pen_paper_posted_id( 'character_id' ),
			'edge_id' => ps_pen_paper_posted_text( 'adventure_edge' ) ) );
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	wp_safe_redirect( ps_pen_paper_game_url( $page_id, $campaign_id ) . '#pspp-group-adventures' ); exit;
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

function ps_pen_paper_render_adventure_groups( $campaign, $page_id ) {
	$records = ps_pen_paper_adventure_group_records( $campaign );
	if ( is_wp_error( $records ) ) { echo '<p role="alert">' . esc_html( $records->get_error_message() ) . '</p>'; return; }
	$gm = ps_pen_paper_can_manage_campaign( $campaign ); $own_characters = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_character', 'post_status' => 'private', 'post_parent' => $campaign->ID,
		'author' => get_current_user_id(), 'posts_per_page' => -1 ) ) as $post ) {
		$state = ps_pen_paper_character_state( $campaign, $post );
		if ( is_wp_error( $state ) ) { echo '<p role="alert">' . esc_html( $state->get_error_message() ) . '</p>'; continue; }
		if ( $state['active'] ) { $own_characters[ $post->ID ] = $state['active']['name']; }
	}
	$statuses = array( 'active' => __( 'Aktiv', 'ps-pen-paper' ), 'paused' => __( 'Pausiert', 'ps-pen-paper' ), 'completed' => __( 'Abgeschlossen', 'ps-pen-paper' ) );
	?>
	<section class="pspp-adventure-groups"><h3><?php esc_html_e( 'Gemeinsame Abenteuer', 'ps-pen-paper' ); ?></h3>
	<p><?php esc_html_e( 'Ein gemeinsamer Szenenstand für alle Teilnehmer. Der Vorschlag legt fest, welcher Besitzer die Entscheidung ausführt. Nur dessen Charakter würfelt, bezahlt persönliche Kosten und erhält persönliche Belohnungen. Fremde Charakterwerte bleiben privat.', 'ps-pen-paper' ); ?></p>
	<p><?php esc_html_e( 'Teilnahmewechsel, Pausieren und Änderungen der Entscheidungsregel verwerfen offene Vorschläge und Freigaben, aber niemals den bisherigen Fortschritt. Pro Benutzer kann eine Figur gleichzeitig aktiv teilnehmen. Bis zu 100 Figuren einschließlich früherer Teilnehmer bleiben im Verlauf zugeordnet.', 'ps-pen-paper' ); ?></p>
	<?php if ( $gm ) :
		$adventures = ps_pen_paper_adventure_records( $campaign, false );
		if ( is_wp_error( $adventures ) ) : echo '<p role="alert">' . esc_html( $adventures->get_error_message() ) . '</p>';
		else : foreach ( $adventures as $adventure_id => $adventure ) :
			if ( 'published' !== $adventure['status'] ) { continue; } ?>
			<details><summary><?php echo esc_html( __( 'Gruppendurchlauf anlegen:', 'ps-pen-paper' ) . ' ' . $adventure['published']['title'] ); ?></summary>
				<?php ps_pen_paper_adventure_group_form( $campaign, $page_id, 0, 0, 'create' ); ?>
				<input type="hidden" name="adventure_id" value="<?php echo esc_attr( $adventure_id ); ?>"><input type="hidden" name="adventure_revision" value="<?php echo esc_attr( $adventure['revision'] ); ?>">
				<label><?php esc_html_e( 'Gruppenname', 'ps-pen-paper' ); ?><input type="text" name="group_title" maxlength="200" required></label>
				<label><?php esc_html_e( 'Wie entscheidet diese Gruppe?', 'ps-pen-paper' ); ?><select name="group_rule">
					<?php foreach ( ps_pen_paper_adventure_group_rules() as $rule => $label ) : ?><option value="<?php echo esc_attr( $rule ); ?>"<?php selected( 'unanimous', $rule ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
				</select></label>
				<button type="submit"><?php esc_html_e( 'Gemeinsamen Durchlauf anlegen', 'ps-pen-paper' ); ?></button></form>
			</details>
		<?php endforeach; endif; endif; ?>
	<?php if ( ! $records ) : ?><p><?php esc_html_e( 'Noch keine gemeinsamen Durchläufe vorhanden.', 'ps-pen-paper' ); ?></p><?php endif; ?>
	<?php foreach ( $records as $id => $record ) : $group = $record['state']; ?>
		<article class="pspp-adventure-group" data-group-id="<?php echo esc_attr( $id ); ?>">
			<h4><?php echo esc_html( $record['title'] . ' · ' . $statuses[ $record['status'] ] ); ?></h4>
			<?php $member = $group ? ps_pen_paper_adventure_group_member( $campaign, $group, get_current_user_id() ) : null;
			if ( ! $member && 'completed' !== $record['status'] ) :
				if ( $own_characters ) :
					ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $record['revision'], 'join' ); ?>
					<label><?php esc_html_e( 'Mit eigenem freigegebenem Charakter teilnehmen', 'ps-pen-paper' ); ?><select name="character_id">
						<?php foreach ( $own_characters as $character_id => $name ) : ?><option value="<?php echo esc_attr( $character_id ); ?>"><?php echo esc_html( $name ); ?></option><?php endforeach; ?>
					</select></label><button type="submit"><?php esc_html_e( 'Teilnehmen / wieder einsteigen', 'ps-pen-paper' ); ?></button></form>
				<?php else : ?><p><?php esc_html_e( 'Erstelle oder lasse zuerst einen eigenen Charakter freigeben.', 'ps-pen-paper' ); ?></p><?php endif;
			endif; ?>
			<?php if ( ! $group ) : ?><p><?php esc_html_e( 'Szenen und Verlauf sind erst nach eigener Teilnahme sichtbar.', 'ps-pen-paper' ); ?></p></article><?php continue; endif; ?>
			<p><?php echo esc_html( ps_pen_paper_adventure_group_rules()[ $group['rule'] ] ); ?></p>
			<ul><?php foreach ( $group['participants'] as $participant ) : ?>
				<li><?php echo esc_html( ps_pen_paper_adventure_group_user_label( $participant['owner_id'] ) . ' · ' . ( $participant['active'] ? __( 'Nimmt teil', 'ps-pen-paper' ) : __( 'Abwesend', 'ps-pen-paper' ) ) ); ?>
					<?php if ( $participant['active'] && ( $gm || $participant['owner_id'] === get_current_user_id() ) ) :
						ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $record['revision'], 'leave' ); ?>
						<input type="hidden" name="character_id" value="<?php echo esc_attr( $participant['character_id'] ); ?>">
						<button type="submit"><?php esc_html_e( 'Teilnahme beenden', 'ps-pen-paper' ); ?></button></form>
					<?php endif; ?>
				</li>
			<?php endforeach; ?></ul>
			<?php if ( $group['scene_id'] ) : $scene = $group['snapshot']['scenes'][ $group['scene_id'] ]; ?>
				<h5><?php echo esc_html( $scene['title'] ); ?></h5><p class="pspp-multiline"><?php echo esc_html( $scene['text'] ); ?></p>
			<?php endif; ?>
			<?php if ( $gm && 'completed' !== $group['status'] ) :
				ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $record['revision'], 'active' === $group['status'] ? 'pause' : 'resume' ); ?>
				<button type="submit"><?php echo esc_html( 'active' === $group['status'] ? __( 'Gruppendurchlauf pausieren', 'ps-pen-paper' ) : __( 'Gruppendurchlauf fortsetzen', 'ps-pen-paper' ) ); ?></button></form>
				<?php ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $record['revision'], 'configure' ); ?>
				<label><?php esc_html_e( 'Entscheidungsregel ändern', 'ps-pen-paper' ); ?><select name="group_rule">
					<?php foreach ( ps_pen_paper_adventure_group_rules() as $rule => $label ) : ?><option value="<?php echo esc_attr( $rule ); ?>"<?php selected( $group['rule'], $rule ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
				</select></label><button type="submit"><?php esc_html_e( 'Entscheidungsregel speichern', 'ps-pen-paper' ); ?></button></form>
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
							$conditions = ps_pen_paper_evaluate_adventure_conditions( $edge, $rules, $character['state'], array_column( $group['history'], 'decision' ), $group['snapshot']['edges'] );
							if ( $conditions ) { echo '<p>' . esc_html( is_wp_error( $conditions ) ? $conditions->get_error_message() : ps_pen_paper_adventure_conditions_label( $conditions ) ) . '</p>'; }
							$cost = isset( $edge['resource_cost'] ) ? ps_pen_paper_character_resource_cost( $campaign, $character['post'], $edge['resource_cost']['resource'], $edge['resource_cost']['amount'] ) : null;
							if ( $cost ) { echo '<p>' . esc_html( is_wp_error( $cost ) ? $cost->get_error_message() : sprintf(
								/* translators: 1: resource name, 2: cost, 3: available points, 4: cost timing. */
								__( 'Kosten: %1$s %2$d; verfügbar %3$d. %4$s.', 'ps-pen-paper' ), $cost['label'], $cost['amount'], $cost['before'],
								ps_pen_paper_adventure_cost_timings()[ $edge['resource_cost']['timing'] ] ) ) . '</p>'; }
							$reward = isset( $edge['reward'] ) ? ps_pen_paper_adventure_reward_context( $campaign, $character['post'], $edge['reward'] ) : null;
							if ( $reward ) { echo '<p>' . esc_html( is_wp_error( $reward ) ? $reward->get_error_message() : ps_pen_paper_adventure_reward_label( $edge['reward'] ) ) . '</p>'; }
							$ready = $ready && ! is_wp_error( $requirement ) && ( ! $requirement || $requirement['value'] >= $requirement['minimum'] ) &&
								! is_wp_error( $conditions ) && ( ! $conditions || $conditions['met'] ) && ! is_wp_error( $cost ) && ( ! $cost || $cost['after'] >= $cost['minimum'] ) && ! is_wp_error( $reward );
							ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $record['revision'], 'execute' ); ?>
							<button type="submit"<?php disabled( ! $ready ); ?>><?php esc_html_e( 'Vereinbarte Gruppenentscheidung ausführen', 'ps-pen-paper' ); ?></button></form>
						<?php }
					endif; ?>
				<?php elseif ( $member ) : foreach ( $group['snapshot']['edges'] as $edge ) :
					if ( $edge['from_scene'] !== $group['scene_id'] ) { continue; }
					ps_pen_paper_adventure_group_form( $campaign, $page_id, $id, $record['revision'], 'propose' ); ?>
					<input type="hidden" name="character_id" value="<?php echo esc_attr( $member['character_id'] ); ?>"><input type="hidden" name="adventure_edge" value="<?php echo esc_attr( $edge['id'] ); ?>">
					<button type="submit"><?php echo esc_html( __( 'Vorschlagen:', 'ps-pen-paper' ) . ' ' . $edge['choice'] ); ?></button>
					<?php if ( $edge['expression'] ) : ?><p><?php echo esc_html( __( 'Serverprobe:', 'ps-pen-paper' ) . ' ' . $edge['expression'] . ' · ' . __( 'Erfolg ab', 'ps-pen-paper' ) . ' ' . $edge['threshold'] ); ?></p><?php endif; ?>
					<?php if ( isset( $edge['resource_cost'] ) ) : ?><p><?php echo esc_html( __( 'Persönliche Ressourcenkosten:', 'ps-pen-paper' ) . ' ' . $edge['resource_cost']['amount'] . ' · ' . ps_pen_paper_adventure_cost_timings()[ $edge['resource_cost']['timing'] ] ); ?></p><?php endif; ?>
					<?php if ( isset( $edge['reward'] ) ) : ?><p><?php echo esc_html( ps_pen_paper_adventure_reward_label( $edge['reward'] ) . ' · ' . ps_pen_paper_adventure_reward_timings()[ $edge['reward']['timing'] ] ); ?></p><?php endif; ?>
					</form>
				<?php endforeach; endif;
			endif; ?>
			<?php if ( $group['history'] ) : ?><h5><?php esc_html_e( 'Gemeinsamer Verlauf', 'ps-pen-paper' ); ?></h5><ol>
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
						echo esc_html( $detail );
					?></li>
				<?php endforeach; ?></ol><?php endif; ?>
		</article>
	<?php endforeach; ?></section>
	<?php
}
