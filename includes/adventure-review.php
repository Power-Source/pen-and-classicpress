<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_valid_adventure_review( $review, $owner_id ) {
	return is_array( $review ) && array( 'edge_id', 'status', 'requester_id', 'reviewer_id' ) === array_keys( $review ) &&
		is_string( $review['edge_id'] ) && wp_is_uuid( $review['edge_id'] ) &&
		in_array( $review['status'], array( 'pending', 'approved' ), true ) &&
		is_int( $review['requester_id'] ) && $review['requester_id'] === $owner_id &&
		is_int( $review['reviewer_id'] ) && ( 'pending' === $review['status'] ? 0 === $review['reviewer_id'] : $review['reviewer_id'] > 0 );
}

function ps_pen_paper_adventure_reviewer_authority( $campaign, $user_id ) {
	return user_can( $user_id, 'read' ) &&
		( ps_pen_paper_site_user( $user_id ) || ( is_multisite() && is_super_admin( $user_id ) ) ) &&
		( user_can( $user_id, 'manage_options' ) || 'gm' === ps_pen_paper_campaign_role( $campaign, $user_id ) );
}

function ps_pen_paper_review_adventure_run( $campaign, $run_id, $edge_id, $revision, $decision = 'request' ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_view_campaign( $campaign ) ||
		! in_array( $decision, array( 'request', 'approved', 'rejected', 'cancel' ), true ) ) { return ps_pen_paper_adventure_error(); }
	return ps_pen_paper_scope_write( $campaign, function () use ( $campaign, $run_id, $edge_id, $revision, $decision ) {
		$locks = array( (int) $campaign->ID, (int) $run_id ); sort( $locks, SORT_NUMERIC );
		return ps_pen_paper_adventure_locks( $locks, function () use ( $campaign, $run_id, $edge_id, $revision, $decision ) {
			return ps_pen_paper_atomic_event( (int) $run_id, function () use ( $campaign, $run_id, $edge_id, $revision, $decision ) {
				global $wpdb;
				if ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID=%d FOR UPDATE", $campaign->ID ) ) !== (int) $campaign->ID ) { return ps_pen_paper_adventure_error(); }
				clean_post_cache( $campaign->ID ); clean_post_cache( $run_id );
				$post = ps_pen_paper_record_post( $run_id ); $before = get_post_meta( $run_id, '_pspp_adventure_run', true );
				if ( ! $post || 'pspp_adventure_run' !== $post->post_type || 'private' !== $post->post_status ||
					(int) $post->post_parent !== (int) $campaign->ID || ! ps_pen_paper_valid_adventure_run( $before ) ||
					'active' !== $before['status'] || ! is_string( $revision ) || (string) $before['revision'] !== $revision ) { return ps_pen_paper_adventure_error(); }
				$character = ps_pen_paper_adventure_character( $campaign, $before['character_id'] );
				if ( is_wp_error( $character ) || (int) $character['post']->post_author !== $before['owner_id'] ) { return ps_pen_paper_adventure_error(); }
				$edge = null;
				foreach ( $before['snapshot']['edges'] as $candidate ) {
					if ( $candidate['id'] === $edge_id && $candidate['from_scene'] === $before['scene_id'] && ! empty( $candidate['gm_review'] ) ) { $edge = $candidate; break; }
				}
				if ( ! $edge ) { return ps_pen_paper_adventure_error(); }
				$after = $before;
				if ( 'cancel' === $decision ) {
					if ( $before['owner_id'] !== get_current_user_id() || ! isset( $before['approval'] ) || $before['approval']['edge_id'] !== $edge_id ) { return ps_pen_paper_adventure_error(); }
					$after['approval'] = null;
				} elseif ( 'request' === $decision ) {
					if ( $before['owner_id'] !== get_current_user_id() || isset( $before['approval'] ) ) { return ps_pen_paper_adventure_error(); }
					$after['approval'] = array( 'edge_id' => $edge_id, 'status' => 'pending', 'requester_id' => $before['owner_id'], 'reviewer_id' => 0 );
				} else {
					if ( ! ps_pen_paper_can_manage_campaign( $campaign ) || ! isset( $before['approval'] ) ||
						$before['approval']['edge_id'] !== $edge_id || 'pending' !== $before['approval']['status'] ) { return ps_pen_paper_adventure_error(); }
					$after['approval'] = 'approved' === $decision ? array( 'edge_id' => $edge_id, 'status' => 'approved',
						'requester_id' => $before['owner_id'], 'reviewer_id' => get_current_user_id() ) : null;
				}
				$after['reviews'] = ( $before['reviews'] ?? 0 ) + 1;
				// Keep optional document keys in their canonical order.
				$approval = $after['approval']; unset( $after['approval'] ); $after['approval'] = $approval;
				return ps_pen_paper_write_adventure_event( $campaign, (int) $run_id, $before, $after, 'adventure_run', 'owner', $before['owner_id'] );
			} );
		} );
	} );
}
