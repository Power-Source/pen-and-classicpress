<?php
/**
 * Run with WP-CLI --skip-plugins --skip-themes.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once dirname( __DIR__ ) . '/includes/import-prepare.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( 'campaign', 'universe', 'character', 'event', 'asset', 'scene', 'folio', 'wiki' ) as $type ) {
	call_user_func( 'ps_pen_paper_register_' . $type . '_type' );
}
global $wpdb;
$original_user = get_current_user_id();
$users = array(); $roots = array();
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$post = function ( $type, $parent, $author, $title ) use ( $check ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'private', 'post_parent' => $parent,
		'post_author' => $author, 'post_title' => $title ), true );
	$check( is_int( $id ) && $id > 0, 'Fixture post failed: ' . $type );
	return $id;
};
$archive_json = function ( $archive ) {
	$archive['payload_sha256'] = hash( 'sha256', json_encode( $archive['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	return json_encode( $archive, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
};
try {
	foreach ( array( 'gm', 'player', 'historian' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-import-prepare-' . wp_generate_password( 12, false ),
			'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( is_int( $id ), 'Fixture account failed.' ); $users[ $role ] = $id;
	}
	get_userdata( $users['gm'] )->add_cap( 'ps_pen_paper_create_campaigns' );
	wp_set_current_user( $users['gm'] );
	$universe = ps_pen_paper_save_universe( 0, array( 'name' => 'Prepare World', 'description' => 'World fixture' ), '0' );
	$check( is_int( $universe ), 'Universe fixture failed.' ); $roots[] = $universe;
	$rules = ps_pen_paper_save_rules( $universe, array( 'name' => 'Prepare Rules', 'description' => '',
		'dice' => array( '6', '20' ), 'fields' => array(
			array( 'id' => '', 'label' => 'Might', 'type' => 'attribute', 'description' => '',
				'min' => '0', 'max' => '10', 'start' => '2' ),
		) ), '0' );
	$check( ! is_wp_error( $rules ), 'Rules fixture failed.' );
	$base = ps_pen_paper_save_character( $universe, 0, $users['player'],
		array( 'name' => 'Base Hero', 'values' => array( $rules['fields'][0]['id'] => '2' ) ), '0', '1' );
	$check( is_int( $base ), 'Base character fixture failed.' );
	$universe_asset = $post( 'pspp_asset', $universe, $users['gm'], 'Bound Universe Image' );
	wp_update_post( array( 'ID' => $universe_asset, 'post_content' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=',
		'post_mime_type' => 'image/png' ) );
	$campaign = $post( 'pspp_campaign', 0, $users['gm'], 'Prepare Source' ); $roots[] = $campaign;
	add_post_meta( $campaign, '_pspp_gm', (string) $users['gm'] );
	add_post_meta( $campaign, '_pspp_player', (string) $users['player'] );
	$check( ! is_wp_error( ps_pen_paper_save_universe_link( $campaign, $universe, '0', '1', 'confirmed', 'local' ) ) &&
		! is_wp_error( ps_pen_paper_adopt_universe_rules( $campaign, '1', 'confirmed' ) ), 'Campaign context fixture failed.' );
	$local_currency = array( 'revision' => 1, 'currency_id' => wp_generate_uuid4(), 'name' => 'Local Coin',
		'symbol' => 'LC', 'wallet_mode' => 'campaign', 'in_use' => true );
	$universe_currency = array( 'revision' => 1, 'currency_id' => wp_generate_uuid4(), 'name' => 'World Coin',
		'symbol' => 'WC', 'wallet_mode' => 'universe', 'in_use' => true );
	$local_trade_policy = array( 'revision' => 1, 'kinds' => array( 'gift', 'sale' ), 'approval' => false );
	$universe_trade_policy = array( 'revision' => 1, 'kinds' => array( 'gift' ), 'approval' => true );
	add_post_meta( $campaign, '_pspp_currency', $local_currency );
	add_post_meta( $campaign, '_pspp_trade_policy', $local_trade_policy );
	add_post_meta( $universe, '_pspp_currency', $universe_currency );
	add_post_meta( $universe, '_pspp_trade_policy', $universe_trade_policy );
	$alias = ps_pen_paper_attach_base_character( $campaign, $base, '1', '1', 'shared', 'confirmed' );
	$check( is_int( $alias ), 'Shared character fixture failed.' );
	$local_wallet = array( 'revision' => 1, 'currency_id' => $local_currency['currency_id'],
		'currency_scope_id' => $campaign, 'name' => $local_currency['name'], 'symbol' => $local_currency['symbol'],
		'balance' => 425 );
	add_post_meta( $alias, '_pspp_wallet', $local_wallet );
	add_post_meta( $campaign, '_pspp_access', array( 'revision' => 1, 'visibility' => 'teaser',
		'admission' => 'approval', 'teaser' => 'Source access notice' ) );
	$alias_state = get_post_meta( $alias, '_pspp_character', true );
	$roll = array( 'version' => 1, 'expression' => '1W6 + 2', 'groups' => array( array( 'sides' => 6, 'rolls' => array( 3 ) ) ),
		'modifier' => 2, 'total' => 5, 'visibility' => 'private', 'recipient_id' => $users['player'],
		'actor_name' => 'Imported Player', 'label' => 'Fixture roll',
		'character' => array( 'id' => $alias, 'name' => $alias_state['active']['name'], 'owner_id' => $users['player'],
			'revision' => $alias_state['revision'], 'context' => array( 'mode' => 'shared', 'source_id' => $base,
				'source_revision' => $alias_state['context']['source_revision'], 'revision' => $alias_state['revision'] ) ),
		'attribute' => null, 'rules_revision' => 1 );
	$roll_id = $post( 'pspp_roll', $campaign, $users['historian'], 'Prepare Roll' );
	add_post_meta( $roll_id, '_pspp_roll', wp_slash( $roll ) );
	add_post_meta( $roll_id, '_pspp_visibility', 'private' );
	add_post_meta( $roll_id, '_pspp_recipient', (string) $users['player'] );
	add_post_meta( $roll_id, '_pspp_actor', (string) $users['historian'] );
	$anonymous_roll = $roll;
	$anonymous_roll['recipient_id'] = $users['historian']; $anonymous_roll['character'] = null;
	$anonymous_roll['visibility'] = 'private';
	$anonymous_roll_id = $post( 'pspp_roll', $campaign, $users['historian'], 'Unmapped Historical Roll' );
	add_post_meta( $anonymous_roll_id, '_pspp_roll', wp_slash( $anonymous_roll ) );
	add_post_meta( $anonymous_roll_id, '_pspp_visibility', 'private' );
	add_post_meta( $anonymous_roll_id, '_pspp_recipient', (string) $users['historian'] );
	add_post_meta( $anonymous_roll_id, '_pspp_actor', (string) $users['historian'] );
	$image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=';
	$asset = $post( 'pspp_asset', $campaign, $users['gm'], 'Prepare Image' );
	wp_update_post( array( 'ID' => $asset, 'post_content' => $image, 'post_mime_type' => 'image/png' ) );
	add_post_meta( $campaign, '_pspp_backdrop', array( 'revision' => 1, 'asset_id' => $asset ) );
	$scene = $post( 'pspp_scene', $campaign, $users['gm'], 'Prepare Scene' );
	$scene_state = ps_pen_paper_scene_default();
	$scene_state['revision'] = 1; $scene_state['publication'] = 1;
	$scene_state['draft']['title'] = 'Published scene'; $scene_state['draft']['asset_id'] = $asset;
	$scene_state['published'] = $scene_state['draft']; unset( $scene_state['published']['secret'] );
	add_post_meta( $scene, '_pspp_scene', wp_slash( $scene_state ) );
	add_post_meta( $campaign, '_pspp_scene_selection', array( 'revision' => 1, 'scene_id' => $scene, 'name' => 'Published scene' ) );
	$folio = $post( 'pspp_folio', $campaign, $users['gm'], 'Prepare Folio' );
	add_post_meta( $folio, '_pspp_folio', wp_slash( array( 'revision' => 1, 'draft' => array(
		'type' => 'chapter', 'title' => 'Chapter', 'text' => '', 'secret' => 'hidden', 'order' => 0,
		'chapter_id' => 0, 'scene_id' => $scene, 'scene_ids' => array( $scene ), 'asset_id' => $asset,
	), 'published' => null ) ) );
	$wiki_config = ps_pen_paper_wiki_default(); $wiki_config['revision'] = 1; $wiki_config['name'] = 'External Wiki';
	$wiki_config['root_id'] = 8001;
	foreach ( ps_pen_paper_wiki_categories() as $key => $label ) { $wiki_config['categories'][ $key ] = array( 'id' => 8100 + count( $wiki_config['categories'] ), 'name' => $label ); }
	add_post_meta( $universe, '_pspp_wiki', wp_slash( $wiki_config ) );
	$wiki_entry_id = $post( 'pspp_wiki_entry', $universe, $users['gm'], 'External Wiki Snapshot' );
	$wiki_entry = array( 'revision' => 1, 'source_id' => 0, 'source_revision' => 0, 'wiki_id' => 9001,
		'title' => 'External article', 'content' => 'Frozen article copy', 'category' => 'people',
		'fingerprint' => str_repeat( 'a', 64 ), 'published' => false );
	add_post_meta( $wiki_entry_id, '_pspp_wiki_entry', wp_slash( $wiki_entry ) );
	$unknown = array( 'literal' => 'O:1:"Nope":0:{}', 'types' => array( 0, '0', null, false ), 'text' => 'Größe' );
	add_post_meta( $campaign, '_pspp_future_feature', wp_slash( $unknown ) );
	$export = ps_pen_paper_export_campaign( $campaign, 'complete' );
	$check( is_array( $export ), 'Campaign fixture export failed: ' . ( is_wp_error( $export ) ? $export->get_error_message() : var_export( $export, true ) ) );
	foreach ( $export['payload']['records'] as &$export_record ) {
		if ( $export_record['id'] !== $campaign ) { continue; }
		$export_record['meta'][] = array( 'key' => '_pspp_import_epoch', 'value' => wp_generate_uuid4() );
		$export_record['meta'][] = array( 'key' => '_pspp_import_receipt', 'value' => json_encode( array(
			'owner_id' => $users['gm'], 'blog_id' => get_current_blog_id(), 'token_hash' => str_repeat( 'a', 64 ),
			'confirmation_hash' => str_repeat( 'b', 64 ), 'backup_id' => $campaign + 1,
		) ) );
		$export_record['meta'][] = array( 'key' => '_pspp_import_backup', 'value' => '{"private":"previous export"}' );
	}
	unset( $export_record );
	$restore_event = array( 'version' => 1, 'kind' => 'campaign_restored', 'visibility' => 'gm', 'recipient_id' => 0,
		'actor_name' => 'Imported GM', 'object_id' => $campaign, 'before' => array( 'revision' => 0 ),
		'after' => array( 'revision' => 1, 'source_hash' => str_repeat( 'c', 64 ), 'backup_id' => $campaign + 1 ),
		'fields' => array(), 'rules_revision' => 1 );
	$event_record = $export['payload']['records'][0];
	$event_record['id'] = max( array_column( $export['payload']['records'], 'id' ) ) + 1;
	$event_record['type'] = 'pspp_event'; $event_record['parent_id'] = $campaign; $event_record['author_id'] = $users['gm'];
	$event_record['status'] = 'private'; $event_record['meta'] = array(
		array( 'key' => '_pspp_event', 'value' => serialize( $restore_event ) ),
		array( 'key' => '_pspp_visibility', 'value' => 'gm' ),
		array( 'key' => '_pspp_recipient', 'value' => '0' ),
		array( 'key' => '_pspp_actor', 'value' => (string) $users['historian'] ),
	);
	$export['payload']['records'][] = $event_record;
	$membership_event = array( 'version' => 1, 'kind' => 'membership_saved', 'visibility' => 'gm', 'recipient_id' => 0,
		'actor_name' => 'Unmapped Historian', 'object_id' => $campaign, 'before' => array( 'revision' => 0 ),
		'after' => array( 'revision' => 1 ), 'fields' => array(), 'rules_revision' => 0,
		'member_id' => $users['historian'], 'member_name' => 'Unmapped Historian',
		'member_before' => array( 'role' => '', 'request' => 'pending' ),
		'member_after' => array( 'role' => 'player', 'request' => 'approved' ) );
	$membership_record = $event_record; $membership_record['id']++;
	$membership_record['author_id'] = $users['historian'];
	$membership_record['meta'][0]['value'] = serialize( $membership_event );
	$membership_record['meta'][3]['value'] = (string) $users['historian'];
	$export['payload']['records'][] = $membership_record;
	$migration_before_rules = $rules;
	$migration_before_rules['allocation'] = array( 'fields' => array( $rules['fields'][0]['id'] ), 'points' => 2 );
	$migration_after_rules = $migration_before_rules;
	$migration_after_rules['revision']++;
	$migration_after_rules['allocation']['points'] = 3;
	$migration_before = get_post_meta( $base, '_pspp_character', true );
	$migration_after = $migration_before;
	$migration_after['revision']++;
	$migration_after['active']['values'][ $rules['fields'][0]['id'] ] = 3;
	$migration_event = array( 'version' => 1, 'kind' => 'character_saved', 'visibility' => 'owner',
		'recipient_id' => $users['player'], 'actor_name' => 'Rules Migration', 'object_id' => $base,
		'before' => $migration_before, 'after' => $migration_after,
		'fields' => $migration_after_rules['fields'], 'rules_revision' => $migration_after_rules['revision'],
		'migration_rules_before' => $migration_before_rules, 'migration_rules_after' => $migration_after_rules );
	$migration_record = $event_record;
	$migration_record['id'] = $membership_record['id'] + 1;
	$migration_record['parent_id'] = $universe;
	$migration_record['meta'][0]['value'] = serialize( $migration_event );
	$migration_record['meta'][1]['value'] = 'owner';
	$migration_record['meta'][2]['value'] = (string) $users['player'];
	$export['payload']['records'][] = $migration_record;
	$resource_field = $rules['fields'][0];
	$resource_field['id'] = wp_generate_uuid4();
	$resource_field['label'] = 'Mana'; $resource_field['type'] = 'resource'; $resource_field['start'] = 5;
	$resource_before = array( 'revision' => 7, 'active' => array( 'name' => 'Historical Base',
		'values' => array( $resource_field['id'] => array( 'current' => 5, 'maximum' => 10 ) ) ), 'pending' => null );
	$resource_after = $resource_before;
	$resource_after['revision']++;
	$resource_after['active']['values'][ $resource_field['id'] ]['current'] = 3;
	$resource_event = array( 'version' => 1, 'kind' => 'character_resource_spent', 'visibility' => 'owner',
		'recipient_id' => $users['player'], 'actor_name' => 'Shared Base Cost', 'object_id' => $base,
		'before' => $resource_before, 'after' => $resource_after,
		'fields' => array( $resource_field ), 'rules_revision' => $rules['revision'],
		'resource_cost' => array( 'id' => $resource_field['id'], 'label' => 'Mana', 'amount' => 2,
			'before' => 5, 'after' => 3, 'minimum' => 0, 'maximum' => 10, 'character_revision' => 7,
			'shared_base' => array( 'universe_id' => $universe, 'character_id' => $base,
				'alias_revision' => 4, 'source_revision' => 7 ) ) );
	$resource_record = $migration_record;
	$resource_record['id']++;
	$resource_record['meta'][0]['value'] = serialize( $resource_event );
	$export['payload']['records'][] = $resource_record;
	$parsed = ps_pen_paper_parse_import( $archive_json( $export ) );
	$check( is_array( $parsed ), 'Campaign fixture archive did not parse.' );
	$document_report = ps_pen_paper_import_documents( $parsed );
	$check( ! $document_report['conflicts'], 'Fixture documents invalid: ' . implode( ' | ', $document_report['conflicts'] ) );
	$receipt_variant = array( 'owner_id' => $users['gm'], 'blog_id' => get_current_blog_id(),
		'token_hash' => str_repeat( 'a', 64 ), 'request_hash' => str_repeat( 'b', 64 ),
		'new_target_id' => $campaign, 'backup_id' => $campaign + 1 );
	$check( ps_pen_paper_prepare_import_validate_known( '_pspp_import_receipt', $receipt_variant,
		$parsed['records'][ $campaign ], $parsed ), 'The structured request receipt variant failed validation.' );
	$legacy_link = array( 'revision' => 1, 'universe_id' => $universe, 'name' => 'Prepare World' );
	$check( ps_pen_paper_valid_universe_link( $legacy_link ), 'A legacy universe link without economy_mode was rejected.' );
	$options = array( 'operation' => 'copy', 'target_id' => 0, 'universe_mode' => 'copy',
		'universe_id' => 0, 'users' => array(), 'bases' => array() );
	foreach ( $parsed['users'] as $user_id => $requirement ) { $options['users'][ $user_id ] = $users['gm']; }
	$options['users'][ $users['historian'] ] = -1;
	$map = array(); $next = 100000;
	foreach ( array_keys( $parsed['records'] ) as $source_id ) { $map[ $source_id ] = $next++; }
	$plain = ps_pen_paper_prepare_import( $parsed, $options, $map );
	$check( is_wp_error( $plain ), 'Unknown _pspp_ metadata was accepted without explicit archival consent.' );
	$prepared = ps_pen_paper_prepare_import( $parsed, $options, $map, true );
	$check( is_array( $prepared ) && isset( $prepared[ $campaign ], $prepared[ $alias ], $prepared[ $roll_id ],
		$prepared[ $anonymous_roll_id ], $prepared[ $scene ], $prepared[ $folio ], $event_record['id'], $membership_record['id'] ),
		'Supported campaign records were not prepared: ' . ( is_wp_error( $prepared ) ? $prepared->get_error_message() : implode( ',', array_keys( $prepared ) ) ) );
	$check( $prepared[ $alias ]['parent_id'] === $map[ $campaign ] &&
		$prepared[ $alias ]['author_id'] === $users['gm'], 'Parent or owner remapping failed.' );
	$character = $prepared[ $alias ]['meta'][0]['value'];
	$character = ps_pen_paper_import_meta( array( 'meta' => array( $alias => array( '_pspp_character' => array( $character ) ) ) ), $alias, '_pspp_character' );
	$check( $character['context']['universe_id'] === $map[ $universe ] &&
		$character['context']['source_id'] === $map[ $base ], 'Shared character context was not remapped.' );
	$migration_meta = array_column( $prepared[ $migration_record['id'] ]['meta'], 'value', 'key' );
	$check( $migration_meta['_pspp_event']['migration_rules_before'] === $migration_before_rules &&
		$migration_meta['_pspp_event']['migration_rules_after'] === $migration_after_rules,
		'Migration rule snapshots or allocation/field UUIDs were altered during preparation.' );
	$bad_migration = $parsed;
	$bad_migration['meta'][ $migration_record['id'] ]['_pspp_event'][0]['before']['active']['values'][ $rules['fields'][0]['id'] ] = 3;
	$check( is_wp_error( ps_pen_paper_prepare_import( $bad_migration, $options, $map, true ) ),
		'Migration before state was validated with after/latest allocation instead of its embedded rules.' );
	$bad_migration = $parsed;
	unset( $bad_migration['meta'][ $migration_record['id'] ]['_pspp_event'][0]['migration_rules_before'] );
	$check( is_wp_error( ps_pen_paper_prepare_import( $bad_migration, $options, $map, true ) ),
		'An incomplete embedded migration-rule pair was accepted.' );
	$resource_meta = array_column( $prepared[ $resource_record['id'] ]['meta'], 'value', 'key' );
	$prepared_cost = $resource_meta['_pspp_event']['resource_cost'];
	$check( $resource_meta['_pspp_event']['object_id'] === $map[ $base ] &&
		$prepared_cost['shared_base']['character_id'] === $map[ $base ] &&
		$prepared_cost['shared_base']['universe_id'] === $map[ $universe ] &&
		$prepared_cost['character_revision'] === 7 && $prepared_cost['shared_base']['source_revision'] === 7 &&
		$prepared_cost['shared_base']['alias_revision'] === 4,
		'Shared resource cost confused alias/base identities or changed historical revisions.' );
	$bad_resource = $parsed;
	$bad_resource['meta'][ $resource_record['id'] ]['_pspp_event'][0]['resource_cost']['shared_base']['character_id'] = $alias;
	$check( is_wp_error( ps_pen_paper_prepare_import( $bad_resource, $options, $map, true ) ),
		'A shared base resource event accepted a foreign campaign alias as its base character.' );
	$meta = array();
	foreach ( $prepared[ $campaign ]['meta'] as $entry ) { $meta[ $entry['key'] ][] = $entry['value']; }
	$check( $meta['_pspp_backdrop'][0]['asset_id'] === $map[ $asset ] &&
		$meta['_pspp_scene_selection'][0]['scene_id'] === $map[ $scene ], 'Live asset or scene references were not remapped.' );
	$check( $meta['_pspp_currency'][0] === $local_currency &&
		$meta['_pspp_trade_policy'][0] === $local_trade_policy &&
		$meta['_pspp_universe_link'][0]['economy_mode'] === 'local',
		'Copy preparation lost the local economy setting or its campaign currency/trade policy.' );
	$prepared_wallet = null;
	foreach ( $prepared[ $alias ]['meta'] as $entry ) {
		if ( '_pspp_wallet' === $entry['key'] ) { $prepared_wallet = $entry['value']; }
	}
	$check( $prepared_wallet === array_merge( $local_wallet, array( 'currency_scope_id' => $map[ $campaign ] ) ),
		'Copy preparation did not preserve the local wallet and remap its campaign scope.' );
	$bad_local_wallet_scope = $parsed;
	$bad_local_wallet_scope['meta'][ $alias ]['_pspp_wallet'][0]['currency_scope_id'] = $universe;
	$check( is_wp_error( ps_pen_paper_prepare_import( $bad_local_wallet_scope, $options, $map, true ) ),
		'Local wallet metadata with a universe currency scope was accepted.' );
	$link_event_id = 0;
	foreach ( $parsed['records'] as $source_id => $source_record ) {
		if ( 'pspp_event' === $source_record['type'] &&
			'universe_linked' === $parsed['meta'][ $source_id ]['_pspp_event'][0]['kind'] ) {
			$link_event_id = $source_id;
			break;
		}
	}
	$check( $link_event_id > 0, 'The fixture universe-link journal entry is missing.' );
	$prepared_link_event = null;
	foreach ( $prepared[ $link_event_id ]['meta'] as $entry ) {
		if ( '_pspp_event' === $entry['key'] ) { $prepared_link_event = $entry['value']; }
	}
	$check( ! array_key_exists( 'economy_mode', $prepared_link_event['before'] ) &&
		$prepared_link_event['after']['economy_mode'] === 'local',
		'Historical link snapshots did not preserve the legacy default and local economy flag.' );
	$folio_state = null;
	foreach ( $prepared[ $folio ]['meta'] as $entry ) { if ( '_pspp_folio' === $entry['key'] ) { $folio_state = $entry['value']; } }
	$check( $folio_state['draft']['scene_id'] === $map[ $scene ] && $folio_state['draft']['scene_ids'] === array( $map[ $scene ] ) &&
		$folio_state['draft']['asset_id'] === $map[ $asset ], 'Folio scene and image references were not remapped.' );
	$roll_state = null;
	foreach ( $prepared[ $roll_id ]['meta'] as $entry ) { if ( '_pspp_roll' === $entry['key'] ) { $roll_state = $entry['value']; } }
	$check( $roll_state['recipient_id'] === $users['gm'] && $roll_state['character']['owner_id'] === $users['gm'] &&
		$roll_state['character']['id'] === $map[ $alias ] && $roll_state['character']['context']['source_id'] === $map[ $base ] &&
		$prepared[ $roll_id ]['author_id'] === 0,
		'Roll snapshot character/owner references were not safely remapped.' );
	$anonymous_state = null; $anonymous_headers = array();
	foreach ( $prepared[ $anonymous_roll_id ]['meta'] as $entry ) {
		if ( '_pspp_roll' === $entry['key'] ) { $anonymous_state = $entry['value']; }
		if ( in_array( $entry['key'], array( '_pspp_visibility', '_pspp_recipient', '_pspp_actor' ), true ) ) { $anonymous_headers[ $entry['key'] ] = $entry['value']; }
	}
	$check( $anonymous_state['recipient_id'] === 0 && $anonymous_state['visibility'] === 'gm' &&
		$anonymous_headers['_pspp_visibility'] === 'gm' && $anonymous_headers['_pspp_recipient'] === '0' &&
		$anonymous_headers['_pspp_actor'] === '0' && $prepared[ $anonymous_roll_id ]['author_id'] === 0,
		'-1 historical accounts were attributed to the importer or left as private/unreadable records.' );
	$restored_event = null;
	foreach ( $prepared[ $event_record['id'] ]['meta'] as $entry ) {
		if ( '_pspp_event' === $entry['key'] ) { $restored_event = $entry['value']; }
	}
	$check( is_array( $restored_event ) && $restored_event['object_id'] === $map[ $campaign ] &&
		$restored_event['after']['backup_id'] === $campaign + 1, 'Restore history remapped a provenance backup ID as a live foreign key.' );
	$membership_state = null; $membership_archive = null;
	foreach ( $prepared[ $membership_record['id'] ]['meta'] as $entry ) {
		if ( '_pspp_event' === $entry['key'] ) { $membership_state = $entry['value']; }
		if ( '_pspp_import_archive' === $entry['key'] ) { $membership_archive = $entry['value']; }
	}
	$member_provenance = false;
	foreach ( $membership_archive['references'] as $reference ) {
		if ( 'historical_user' === $reference['kind'] && $reference['path'] === '_pspp_event.member_id' &&
			$reference['source_id'] === $users['historian'] ) { $member_provenance = true; }
	}
	$check( $membership_state['member_id'] === $users['historian'] && $membership_state['recipient_id'] === 0 &&
		$member_provenance,
		'Positive historical member IDs were rebound as destination accounts or lost their private provenance.' );
	$campaign_meta = array_column( $prepared[ $campaign ]['meta'], null, 'key' );
	$check( ! isset( $campaign_meta['_pspp_import_epoch'], $campaign_meta['_pspp_import_receipt'], $campaign_meta['_pspp_import_backup'] ),
		'Source import authority markers leaked into destination metadata.' );
	$archive_meta = $campaign_meta['_pspp_import_archive']['value'];
	$source_access_raw = null;
	foreach ( $parsed['records'][ $campaign ]['meta'] as $entry ) {
		if ( '_pspp_access' === $entry['key'] ) { $source_access_raw = $entry['value']; }
	}
	$check( isset( $archive_meta['source_access']['key'], $archive_meta['source_access']['value'] ) &&
		$archive_meta['source_access']['key'] === '_pspp_access' && $archive_meta['source_access']['value'] === $source_access_raw,
		'Original campaign access settings were not retained as private import provenance.' );
	$raw_unknown = null;
	foreach ( $parsed['records'][ $campaign ]['meta'] as $entry ) {
		if ( '_pspp_future_feature' === $entry['key'] ) { $raw_unknown = $entry['value']; }
	}
	$archived_unknown = false;
	foreach ( $archive_meta['metadata'] as $entry ) {
		if ( '_pspp_future_feature' === $entry['key'] && $raw_unknown === $entry['value'] ) { $archived_unknown = true; }
	}
	$check( $archived_unknown && ! isset( $campaign_meta['_pspp_future_feature'] ), 'Unknown metadata was not preserved as raw private provenance.' );
	$wiki_prepared = $prepared[ $wiki_entry_id ]['meta'];
	$check( ! array_filter( $wiki_prepared, function ( $entry ) { return in_array( $entry['key'], array( '_pspp_wiki_entry', '_pspp_wiki' ), true ); } ),
		'External Wiki snapshots were activated.' );
	$wiki_archive = null;
	foreach ( $wiki_prepared as $entry ) { if ( '_pspp_import_archive' === $entry['key'] ) { $wiki_archive = $entry['value']; } }
	$check( is_array( $wiki_archive ) && ! empty( $wiki_archive['warnings'] ), 'Wiki provenance lacks an explicit integration warning.' );

	$bound_options = $options; $bound_options['universe_mode'] = 'bind'; $bound_options['universe_id'] = $universe;
	$bound_options['bases'][ $base ] = $base;
	$bound_map = $map; $bound_map[ $universe ] = $universe; $bound_map[ $base ] = $base;
	$bound = ps_pen_paper_prepare_import( $parsed, $bound_options, $bound_map, true );
	$check( is_array( $bound ) && ! isset( $bound[ $universe ], $bound[ $base ], $bound[ $wiki_entry_id ] ),
		'Binding did not omit existing universe-owned records.' );
	$bound_character = null;
	foreach ( $bound[ $alias ]['meta'] as $entry ) { if ( '_pspp_character' === $entry['key'] ) { $bound_character = $entry['value']; } }
	$check( $bound_character['context']['universe_id'] === $universe && $bound_character['context']['source_id'] === $base,
		'Bound character context did not target the selected universe/base.' );
	$check( $bound[ $campaign ]['parent_id'] === 0, 'Campaign root acquired a foreign parent.' );
	$bound_meta = array_column( $bound[ $campaign ]['meta'], null, 'key' );
	$bound_wallet = null;
	foreach ( $bound[ $alias ]['meta'] as $entry ) {
		if ( '_pspp_wallet' === $entry['key'] ) { $bound_wallet = $entry['value']; }
	}
	$check( $bound_meta['_pspp_currency']['value'] === $local_currency &&
		$bound_meta['_pspp_trade_policy']['value'] === $local_trade_policy &&
		$bound_meta['_pspp_universe_link']['value']['economy_mode'] === 'local' &&
		$bound_wallet === array_merge( $local_wallet, array( 'currency_scope_id' => $bound_map[ $campaign ] ) ) &&
		$bound_wallet['currency_scope_id'] !== $universe,
		'Bind preparation substituted universe economy data for local campaign wallets/policy.' );
	$bad_local_link = $parsed;
	$bad_local_link['meta'][ $campaign ]['_pspp_universe_link'][0]['economy_mode'] = 'global';
	$check( is_wp_error( ps_pen_paper_prepare_import( $bad_local_link, $options, $map, true ) ),
		'Malformed economy_mode on the active universe link was accepted.' );
	$bad_historical_link = $parsed;
	$bad_historical_link['meta'][ $link_event_id ]['_pspp_event'][0]['after']['economy_mode'] = 'global';
	$check( is_wp_error( ps_pen_paper_prepare_import( $bad_historical_link, $options, $map, true ) ),
		'Malformed economy_mode in a historical universe-link snapshot was accepted.' );
	$bound_with_live_universe_image = $parsed;
	$bound_gear = array(
		'revision' => 1,
		'active' => array( 'items' => array( wp_generate_uuid4() => array(
			'source_id' => $universe_asset, 'scope_id' => $universe, 'revision' => 1,
			'item' => array( 'name' => 'Bound image item', 'description' => '', 'kind' => 'weapon', 'rarity' => 'common',
				'rarity_label' => 'Common', 'color' => '#ffffff', 'binding' => 'bound', 'asset_id' => $universe_asset,
				'properties' => array(), 'bonuses' => array() ),
		) ), 'slots' => array() ),
		'pending' => null,
	);
	$bound_with_live_universe_image['meta'][ $alias ]['_pspp_gear'] = array( $bound_gear );
	$bound_with_live_universe_image['records'][ $alias ]['meta'][] = array( 'key' => '_pspp_gear', 'value' => serialize( $bound_gear ) );
	$unresolved_bound_asset = ps_pen_paper_prepare_import( $bound_with_live_universe_image, $bound_options, $bound_map, true );
	$check( is_wp_error( $unresolved_bound_asset ) && 'unresolved_bound_asset' === $unresolved_bound_asset->get_error_code(),
		'An active image from the bound universe was silently detached instead of producing an actionable error.' );

	$bad = $parsed;
	$bad['meta'][ $scene ]['_pspp_scene'][0]['draft']['asset_id'] = 987654321;
	$check( is_wp_error( ps_pen_paper_prepare_import( $bad, $options, $map, true ) ),
		'An invalid known live asset reference was silently repaired.' );
	WP_CLI::success( 'Campaign import preparation, references, private provenance, external Wiki isolation and bind behavior passed.' );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $roots as $id ) {
		foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent=%d", $id ) ) as $child ) {
			wp_delete_post( (int) $child, true );
		}
		wp_delete_post( $id, true );
	}
	foreach ( $users as $id ) { if ( ! ( is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ) ) ) { WP_CLI::warning( 'Fixture user cleanup failed: ' . $id ); } }
}
