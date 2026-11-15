<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_render_adventure_reviews( $campaign, $page_id ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return; }
	?>
	<section class="pspp-adventure-reviews"><h4><?php esc_html_e( 'Ausstehende Abenteuerentscheidungen', 'ps-pen-paper' ); ?></h4>
	<?php
	$pending = 0;
	foreach ( get_posts( array( 'post_type' => 'pspp_adventure_run', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1 ) ) as $post ) {
		$run = get_post_meta( $post->ID, '_pspp_adventure_run', true );
		if ( ! ps_pen_paper_valid_adventure_run( $run ) ) { echo '<p role="alert">' . esc_html( ps_pen_paper_adventure_error()->get_error_message() ) . '</p>'; continue; }
		if ( ! isset( $run['approval'] ) || 'pending' !== $run['approval']['status'] ) { continue; }
		$pending++;
		$character = ps_pen_paper_adventure_character( $campaign, $run['character_id'] );
		if ( is_wp_error( $character ) ) { echo '<p role="alert">' . esc_html( $character->get_error_message() ) . '</p>'; continue; }
		$choice = '';
		foreach ( $run['snapshot']['edges'] as $edge ) { if ( $edge['id'] === $run['approval']['edge_id'] ) { $choice = $edge['choice']; break; } }
		echo '<p><strong>' . esc_html( $character['state']['active']['name'] ) . '</strong> · ' . esc_html( $run['snapshot']['title'] . ' · ' . $choice ) . '</p>';
		ps_pen_paper_form_start( 'review_adventure_run', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<input type="hidden" name="run_id" value="<?php echo esc_attr( $post->ID ); ?>"><input type="hidden" name="run_revision" value="<?php echo esc_attr( $run['revision'] ); ?>">
			<input type="hidden" name="character_id" value="<?php echo esc_attr( $run['character_id'] ); ?>"><input type="hidden" name="adventure_edge" value="<?php echo esc_attr( $run['approval']['edge_id'] ); ?>">
			<button type="submit" name="decision" value="approved"><?php esc_html_e( 'Entscheidung freigeben', 'ps-pen-paper' ); ?></button>
			<button type="submit" name="decision" value="rejected"><?php esc_html_e( 'Entscheidung ablehnen', 'ps-pen-paper' ); ?></button>
		</form><?php
	}
	if ( ! $pending ) { echo '<p>' . esc_html__( 'Keine offenen Abenteuerentscheidungen.', 'ps-pen-paper' ) . '</p>'; }
	?></section><?php
}
