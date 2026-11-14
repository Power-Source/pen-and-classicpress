<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ps_pen_paper_register_campaign_type() {
	register_post_type(
		'pspp_campaign',
		array(
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => false,
			'show_in_rest'        => false,
			'capability_type'     => array( 'pspp_campaign', 'pspp_campaigns' ),
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'editor', 'author' ),
		)
	);
}

function ps_pen_paper_get_campaign( $campaign_id ) {
	$campaign = get_post( $campaign_id );
	if ( ! $campaign || 'pspp_campaign' !== $campaign->post_type || 'private' !== $campaign->post_status ) {
		return null;
	}
	return $campaign;
}

function ps_pen_paper_campaign_role( $campaign, $user_id ) {
	if ( (int) $campaign->post_author === (int) $user_id || in_array( (string) $user_id, get_post_meta( $campaign->ID, '_pspp_gm', false ), true ) ) {
		return 'gm';
	}
	if ( in_array( (string) $user_id, get_post_meta( $campaign->ID, '_pspp_player', false ), true ) ) {
		return 'player';
	}
	return '';
}

function ps_pen_paper_can_view_campaign( $campaign ) {
	return current_user_can( 'manage_options' ) || '' !== ps_pen_paper_campaign_role( $campaign, get_current_user_id() );
}

function ps_pen_paper_can_access_site() {
	return current_user_can( 'read' ) && ( ps_pen_paper_site_user( get_current_user_id() ) || ( is_multisite() && is_super_admin() ) );
}

function ps_pen_paper_can_manage_campaign( $campaign ) {
	return current_user_can( 'manage_options' ) || 'gm' === ps_pen_paper_campaign_role( $campaign, get_current_user_id() );
}

function ps_pen_paper_can_create_campaign() {
	return current_user_can( 'manage_options' ) || current_user_can( 'ps_pen_paper_create_campaigns' );
}

function ps_pen_paper_get_campaigns() {
	$args = array(
		'post_type'      => 'pspp_campaign',
		'post_status'    => 'private',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);
	if ( ! current_user_can( 'manage_options' ) ) {
		$args['meta_query'] = array(
			'relation' => 'OR',
			array(
				'key'   => '_pspp_gm',
				'value' => (string) get_current_user_id(),
			),
			array(
				'key'   => '_pspp_player',
				'value' => (string) get_current_user_id(),
			),
		);
	}
	return get_posts( $args );
}
