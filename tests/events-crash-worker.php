<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 3 !== count( $args ) || ! in_array( $args[2], array( 'before_commit', 'after_commit' ), true ) ) {
	exit( "Run only through events-integration.php.\n" );
}
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
foreach ( array( 'campaign', 'character', 'event' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
wp_set_current_user( (int) $args[1] );
$character = get_post( (int) $args[0] );
$state = get_post_meta( (int) $args[0], '_pspp_character', true );
if ( ! $character || ! isset( $state['active']['name'] ) || 0 !== strpos( $state['active']['name'], 'PSPP transaction crash fixture ' ) ) {
	exit( "Invalid transaction fixture.\n" );
}
$input = $state['active'];
$input['name'] .= ' updated';
foreach ( $input['values'] as &$value ) {
	$value = is_array( $value ) ? array_map( 'strval', $value ) : (string) $value;
}
unset( $value );
if ( 'before_commit' === $args[2] ) {
	add_action( 'transition_post_status', function ( $status, $old_status, $post ) {
		if ( 'pspp_event' === $post->post_type && 'private' === $status ) { exit( 91 ); }
	}, 10, 3 );
} else {
	add_action( 'clean_post_cache', function ( $id ) use ( $character ) {
		if ( $id === $character->ID && ! wp_suspend_cache_addition() ) { exit( 92 ); }
	} );
}
ps_pen_paper_save_character( $character->post_parent, $character->ID, $character->post_author, $input, (string) $state['revision'], (string) ps_pen_paper_get_rules( $character->post_parent )['revision'] );
exit( "The transaction did not reach its expected exit point.\n" );
