<?php
/**
 * Run with WP-CLI --skip-plugins --skip-themes on a development site.
 * PSPP_TEST_HTTP=1 additionally checks real multipart uploads and preview forms.
 * PSPP_TEST_HTTP_ADMIN_REDIRECT=1 expects an external admin start-page policy.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run this test with WP-CLI.\n" ); }
require_once dirname( __DIR__ ) . '/ps-pen-paper.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
if ( is_multisite() ) { require_once ABSPATH . 'wp-admin/includes/ms.php'; }
foreach ( array( 'campaign', 'universe', 'character', 'event', 'asset' ) as $type ) { call_user_func( 'ps_pen_paper_register_' . $type . '_type' ); }
global $wpdb;
$original_user = get_current_user_id(); $original_post = $_POST;
$original_cookie = isset( $_COOKIE[LOGGED_IN_COOKIE] ) ? $_COOKIE[LOGGED_IN_COOKIE] : null;
$users = array(); $parents = array(); $tokens = array(); $pages = array();
$check = function ( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } };
$encode = function ( $archive ) {
	$archive['payload_sha256'] = hash( 'sha256', json_encode( $archive['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	return json_encode( $archive, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
};
$post = function ( $type, $parent, $author, $title ) use ( $check ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'private', 'post_parent' => $parent,
		'post_author' => $author, 'post_title' => $title ), true );
	$check( is_int( $id ) && $id > 0, 'Fixture post failed.' );
	return $id;
};
class PSPP_Import_Preview_Object {
	public static $executed = 0;
	public function __wakeup() { self::$executed++; }
}
try {
	foreach ( array( 'gm', 'player', 'other_gm' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'pspp-import-preview-' . wp_generate_password( 12, false ),
			'user_pass' => wp_generate_password( 40 ), 'role' => 'subscriber' ) );
		$check( is_int( $id ), 'Fixture account failed.' ); $users[ $role ] = $id;
	}
	foreach ( array( 'gm', 'other_gm' ) as $role ) { get_userdata( $users[ $role ] )->add_cap( 'ps_pen_paper_create_campaigns' ); }
	wp_set_current_user( $users['gm'] );
	$world = ps_pen_paper_save_universe( 0, array( 'name' => 'Import preview world', 'description' => '' ), '0' );
	$check( is_int( $world ), 'World fixture failed.' ); $parents[] = $world;
	$rules = ps_pen_paper_save_rules( $world, array( 'name' => 'Preview rules', 'description' => '', 'dice' => array( '6' ), 'fields' => array(
		array( 'id' => '', 'label' => 'Mut', 'type' => 'attribute', 'description' => '', 'min' => '0', 'max' => '10', 'start' => '2' ),
	) ), '0' );
	$check( ! is_wp_error( $rules ), 'Rules fixture failed.' );
	$base = ps_pen_paper_save_character( $world, 0, $users['player'],
		array( 'name' => 'Preview hero', 'values' => array( $rules['fields'][0]['id'] => '2' ) ), '0', '1' );
	$check( is_int( $base ), 'Base fixture failed.' );
	$campaign = $post( 'pspp_campaign', 0, $users['gm'], 'Import preview source' ); $parents[] = $campaign;
	add_post_meta( $campaign, '_pspp_player', (string) $users['player'] );
	$check( ! is_wp_error( ps_pen_paper_save_universe_link( $campaign, $world, '0', '1', 'confirmed' ) ) &&
		! is_wp_error( ps_pen_paper_adopt_universe_rules( $campaign, '1', 'confirmed' ) ), 'Link fixture failed.' );
	$alias = ps_pen_paper_attach_base_character( $campaign, $base, '1', '1', 'shared', 'confirmed' );
	$check( is_int( $alias ), 'Alias fixture failed.' );
	$copy_base = ps_pen_paper_save_character( $world, 0, $users['gm'],
		array( 'name' => 'Independent hero', 'values' => array( $rules['fields'][0]['id'] => '2' ) ), '0', '1' );
	$check( is_int( $copy_base ), 'Independent base fixture failed.' );
	$copy_character = ps_pen_paper_attach_base_character( $campaign, $copy_base, '1', '1', 'copy', 'confirmed' );
	$check( is_int( $copy_character ), 'Independent campaign copy fixture failed.' );
	$history = $post( 'pspp_event', $campaign, $users['other_gm'], 'Historical ownership' );
	add_post_meta( $history, '_pspp_event', array( 'object_id' => $alias,
		'before' => array( 'owner_id' => $users['other_gm'], 'asset_id' => 123456789 ) ) );
	$target = $post( 'pspp_campaign', 0, $users['gm'], 'Import preview target' ); $parents[] = $target;
	$foreign = $post( 'pspp_campaign', 0, $users['other_gm'], 'Foreign target' ); $parents[] = $foreign;
	$asset = $post( 'pspp_asset', $campaign, $users['gm'], 'Preview image' );
	$image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=';
	wp_update_post( array( 'ID' => $asset, 'post_content' => $image, 'post_mime_type' => 'image/png' ) );
	add_post_meta( $campaign, '_pspp_backdrop', array( 'revision' => 1, 'asset_id' => $asset ) );
	$opaque = array( 'name' => 'Größe und Prüfung', 'literal' => 'O:1:"X":0:{}', 'values' => array( 0, '0', null, false ) );
	add_post_meta( $campaign, '_pspp_import_fixture', wp_slash( $opaque ) );
	$archive = ps_pen_paper_export_campaign( $campaign, 'complete' );
	$check( is_array( $archive ), 'Export fixture failed.' );
	$json = $encode( $archive );
	$parsed = ps_pen_paper_parse_import( $json );
	$check( is_array( $parsed ) && $parsed['universe_id'] === $world && isset( $parsed['bases'][ $base ] ),
		'Valid exported scope was not accepted.' );
	$check( $opaque === ps_pen_paper_import_meta( $parsed, $campaign, '_pspp_import_fixture' ), 'Literal serialization syntax, types or umlauts changed.' );
	$check( $parsed['users'][ $users['player'] ]['required'], 'Later history records overwrote mandatory character ownership.' );
	$check( ! $parsed['users'][ $users['other_gm'] ]['required'] && ! isset( $parsed['records'][ $copy_base ] ) &&
		isset( $parsed['records'][ $copy_character ] ), 'Historical ownership required an active account or independent copies required their original bases.' );
	$options = array( 'operation' => 'copy', 'target_id' => 0, 'universe_mode' => 'copy', 'universe_id' => 0, 'users' => array(), 'bases' => array() );
	foreach ( $parsed['users'] as $id => $required ) { $options['users'][ $id ] = $id; }
	$options['users'][ $users['other_gm'] ] = -1;
	$before = ps_pen_paper_export_campaign( $campaign, 'complete' )['payload_sha256'];
	$before_target = ps_pen_paper_export_campaign( $target, 'local' )['payload_sha256'];
	$copy = ps_pen_paper_import_configuration( $parsed, $options );
	$check( is_array( $copy ) && ! $copy['conflicts'], 'New campaign/new universe option failed.' );
	$check( $copy['documents']['checked'] > 0 && in_array( '_pspp_event', $copy['documents']['deferred'], true ),
		'Document checks or explicitly deferred journal checks are missing.' );
	foreach ( array( 'rules', 'character' ) as $invalid_document ) {
		$bad = $archive;
		foreach ( $bad['payload']['records'] as &$entry ) {
			foreach ( $entry['meta'] as &$metadata ) {
				if ( 'rules' === $invalid_document && $entry['id'] === $campaign && '_pspp_rules' === $metadata['key'] ) {
					$state = unserialize( $metadata['value'], array( 'allowed_classes' => false ) );
					$state['fields'][0]['start'] = $state['fields'][0]['max'] + 1;
					$metadata['value'] = serialize( $state );
				}
				if ( 'character' === $invalid_document && $entry['id'] === $alias && '_pspp_character' === $metadata['key'] ) {
					$state = unserialize( $metadata['value'], array( 'allowed_classes' => false ) );
					$state['active']['values'][ $rules['fields'][0]['id'] ] = 'not a number';
					$metadata['value'] = serialize( $state );
				}
			}
			unset( $metadata );
		}
		unset( $entry );
		$invalid = ps_pen_paper_parse_import( $encode( $bad ) );
		$check( is_array( $invalid ) && ps_pen_paper_import_configuration( $invalid, $options )['conflicts'],
			'Recomputed checksum bypassed ' . $invalid_document . ' document validation.' );
	}
	$scene = ps_pen_paper_scene_default(); $scene['revision'] = 1; $scene['draft']['title'] = 'Import scene';
	$scene_record = $archive['payload']['records'][0];
	$scene_record['id'] = max( array_keys( $parsed['records'] ) ) + 1000; $scene_record['type'] = 'pspp_scene';
	$scene_record['parent_id'] = $campaign; $scene_record['meta'] = array( array( 'key' => '_pspp_scene', 'value' => serialize( $scene ) ) );
	$bad = $archive; $bad['payload']['records'][] = $scene_record;
	$valid_scene = ps_pen_paper_parse_import( $encode( $bad ) );
	$check( is_array( $valid_scene ) && ! ps_pen_paper_import_configuration( $valid_scene, $options )['conflicts'], 'Valid story document was rejected.' );
	$scene['published'] = $scene['draft']; $scene['publication'] = 1;
	$bad['payload']['records'][ count( $bad['payload']['records'] ) - 1 ]['meta'][0]['value'] = serialize( $scene );
	$invalid_scene = ps_pen_paper_parse_import( $encode( $bad ) );
	$check( is_array( $invalid_scene ) && ps_pen_paper_import_configuration( $invalid_scene, $options )['conflicts'],
		'Published scene containing GM secrets passed document validation.' );
	$wallet = array( 'revision' => 1, 'currency_id' => wp_generate_uuid4(), 'currency_scope_id' => $world,
		'name' => 'Coins', 'symbol' => 'C', 'balance' => 100 );
	$bad = $archive;
	foreach ( $bad['payload']['records'] as &$entry ) {
		if ( $entry['id'] === $alias ) { $entry['meta'][] = array( 'key' => '_pspp_wallet', 'value' => serialize( $wallet ) ); }
	}
	unset( $entry );
	$valid_wallet = ps_pen_paper_parse_import( $encode( $bad ) );
	$original_parsed = serialize( $valid_wallet );
	$check( ! ps_pen_paper_import_documents( $valid_wallet )['conflicts'] && $original_parsed === serialize( $valid_wallet ),
		'Document validation changed source values or rejected a valid wallet.' );
	foreach ( $bad['payload']['records'] as &$entry ) {
		if ( $entry['id'] === $alias ) {
			$wallet['balance'] = 1000000000;
			$entry['meta'][ count( $entry['meta'] ) - 1 ]['value'] = serialize( $wallet );
		}
	}
	unset( $entry );
	$invalid_wallet = ps_pen_paper_parse_import( $encode( $bad ) );
	$check( is_array( $invalid_wallet ) && ps_pen_paper_import_documents( $invalid_wallet )['conflicts'],
		'Wallet above the supported balance limit passed document validation.' );
	$replace_options = $options;
	$replace_options['operation'] = 'replace'; $replace_options['target_id'] = $target;
	$replace_options['universe_mode'] = 'bind'; $replace_options['universe_id'] = $world; $replace_options['bases'][ $base ] = $base;
	$replace = ps_pen_paper_import_configuration( $parsed, $replace_options );
	$check( is_array( $replace ) && ! $replace['conflicts'] && $replace['target_hash'] === $before_target && '' !== $replace['universe_hash'],
		'Existing campaign and unchanged universe binding option failed.' );
	$check( $replace['documents'] === $copy['documents'], 'Universe binding overwrote the source document-check report.' );
	$bad_options = $replace_options; $bad_options['target_id'] = $foreign;
	$check( ps_pen_paper_import_configuration( $parsed, $bad_options )['conflicts'], 'Unmanaged target was accepted.' );
	$bad_options = $replace_options; $bad_options['users'][ $users['player'] ] = $users['gm'];
	$check( ps_pen_paper_import_configuration( $parsed, $bad_options )['conflicts'], 'Mismatched base ownership was accepted.' );
	$bad_options = $options; $bad_options['users'][ $users['player'] ] = -1;
	$check( ps_pen_paper_import_configuration( $parsed, $bad_options )['conflicts'], 'Active character owner could be omitted.' );
	$local = ps_pen_paper_parse_import( $encode( ps_pen_paper_export_campaign( $campaign, 'local' ) ) );
	$check( is_array( $local ) && ps_pen_paper_import_configuration( $local, $options )['conflicts'] &&
		! ps_pen_paper_import_configuration( $local, $replace_options )['conflicts'], 'Local archive dependency handling is not explicit.' );
	$bad = $archive; $bad['payload']['records'][0]['title'] .= ' altered';
	$check( is_wp_error( ps_pen_paper_parse_import( json_encode( $bad ) ) ), 'Changed data passed checksum.' );
	$bad = $archive; $bad['format_version'] = 99;
	$check( is_wp_error( ps_pen_paper_parse_import( $encode( $bad ) ) ), 'Unknown version accepted.' );
	$check( is_wp_error( ps_pen_paper_parse_import( substr( $json, 0, -1 ) ) ), 'Truncated JSON accepted.' );
	$bad = $archive; $bad['payload']['records'][] = $bad['payload']['records'][0];
	$check( is_wp_error( ps_pen_paper_parse_import( $encode( $bad ) ) ), 'Duplicate record IDs accepted.' );
	$bad = $archive;
	foreach ( $bad['payload']['records'] as &$entry ) { if ( $entry['id'] === $alias ) { $entry['parent_id'] = $foreign; } }
	unset( $entry );
	$check( is_wp_error( ps_pen_paper_parse_import( $encode( $bad ) ) ), 'Foreign parent scope accepted.' );
	$bad = $archive;
	foreach ( $bad['payload']['records'] as &$entry ) {
		if ( $entry['id'] === $campaign ) { $entry['meta'][] = array( 'key' => '_pspp_object', 'value' => serialize( new PSPP_Import_Preview_Object() ) ); }
	}
	unset( $entry );
	$check( is_wp_error( ps_pen_paper_parse_import( $encode( $bad ) ) ) && 0 === PSPP_Import_Preview_Object::$executed, 'Serialized object executed or was accepted.' );
	foreach ( array( 'C:1:"X":0:{}', 'E:7:"X:Value";', 'a:1:{i:0;R:1;}', 'a:1:{i:0;i:999999999999999999999999999;}' ) as $unsafe ) {
		$bad = $archive;
		foreach ( $bad['payload']['records'] as &$entry ) {
			if ( $entry['id'] === $campaign ) { $entry['meta'][] = array( 'key' => '_pspp_unsafe', 'value' => $unsafe ); }
		}
		unset( $entry );
		$check( is_wp_error( ps_pen_paper_parse_import( $encode( $bad ) ) ), 'Unsupported serialization accepted.' );
	}
	$bad = $archive;
	foreach ( $bad['payload']['records'] as &$entry ) { if ( $entry['id'] === $asset ) { $entry['content'] = base64_encode( '<script>not an image</script>' ); } }
	unset( $entry );
	$check( is_wp_error( ps_pen_paper_parse_import( $encode( $bad ) ) ), 'Non-image bytes accepted as a private image.' );
	foreach ( array( 0, 3001 ) as $width ) {
		$bad = $archive;
		foreach ( $bad['payload']['records'] as &$entry ) {
			if ( $entry['id'] === $asset ) { $entry['content'] = base64_encode( substr_replace( base64_decode( $image ), pack( 'N', $width ), 16, 4 ) ); }
		}
		unset( $entry );
		$check( is_wp_error( ps_pen_paper_parse_import( $encode( $bad ) ) ), 'Invalid image dimension accepted.' );
	}
	$bad = $archive;
	$bad['payload']['records'] = array_values( array_filter( $bad['payload']['records'], function ( $entry ) use ( $asset ) { return $entry['id'] !== $asset; } ) );
	$bad['payload']['dependencies'] = array_values( array_filter( $bad['payload']['dependencies'], function ( $dependency ) use ( $asset ) { return $dependency['id'] !== $asset; } ) );
	$missing = ps_pen_paper_parse_import( $encode( $bad ) );
	$check( is_array( $missing ) && ps_pen_paper_import_configuration( $missing, $options )['conflicts'], 'Removing the manifest hid a missing current image.' );
	$bad = $archive;
	foreach ( $bad['payload']['records'] as &$entry ) {
		if ( $entry['id'] === $campaign ) {
			foreach ( $entry['meta'] as $metadata ) {
				if ( '_pspp_backdrop' === $metadata['key'] ) { $entry['meta'][] = $metadata; break; }
			}
		}
	}
	unset( $entry );
	$check( is_wp_error( ps_pen_paper_parse_import( $encode( $bad ) ) ), 'Duplicate singleton metadata accepted.' );
	$limit = function () use ( $json ) { return strlen( $json ); };
	add_filter( 'upload_size_limit', $limit );
	try { $check( is_array( ps_pen_paper_parse_import( $json ) ), 'Exact upload-size boundary rejected.' ); }
	finally { remove_filter( 'upload_size_limit', $limit ); }
	$limit = function () use ( $json ) { return strlen( $json ) - 1; };
	add_filter( 'upload_size_limit', $limit );
	try { $check( is_wp_error( ps_pen_paper_parse_import( $json ) ), 'Oversized input accepted.' ); }
	finally { remove_filter( 'upload_size_limit', $limit ); }
	$token = ps_pen_paper_create_import_preview( $json );
	$check( is_string( $token ), 'Private preview creation failed.' ); $tokens[ $token ] = $users['gm'];
	$stored = ps_pen_paper_get_import_preview( $token );
	$check( is_array( $stored ) && $stored['json'] === $json && $stored['options']['operation'] === '', 'Preview imposed a product option.' );
	$stored['options'] = $replace_options;
	$check( set_transient( ps_pen_paper_import_preview_key( $token ), $stored, HOUR_IN_SECONDS ), 'Preview configuration fixture failed.' );
	ob_start(); ps_pen_paper_render_import_preview( $token, 42 ); $html = ob_get_clean();
	$check( false !== strpos( $html, 'value="copy"' ) && false !== strpos( $html, 'value="replace"' ) &&
		false !== strpos( $html, 'value="bind"' ) && false !== strpos( $html, 'name="import[users][' ) &&
		false !== strpos( $html, 'import[bases][' ) && false !== strpos( $html, 'export_campaign' ) &&
		false === strpos( $html, 'value="restore_import"' ), 'Runtime choices missing or unimplemented write action offered.' );
	wp_set_current_user( $users['other_gm'] );
	$check( is_wp_error( ps_pen_paper_get_import_preview( $token ) ), 'Another GM opened the private preview.' );
	wp_set_current_user( $users['player'] );
	$check( is_wp_error( ps_pen_paper_create_import_preview( $json ) ), 'Player created an import preview.' );
	wp_get_current_user()->add_cap( 'ps_pen_paper_create_campaigns' );
	$player_token = ps_pen_paper_create_import_preview( $json );
	$check( is_string( $player_token ), 'Authorized preview fixture failed.' ); $tokens[ $player_token ] = $users['player'];
	wp_get_current_user()->remove_cap( 'ps_pen_paper_create_campaigns' );
	$check( is_wp_error( ps_pen_paper_get_import_preview( $player_token ) ), 'Revoked permission left a private preview accessible.' );
	wp_set_current_user( $users['gm'] );
	$_POST = array( 'import' => array( 'operation' => 'copy', 'target_id' => '0', 'universe_mode' => 'copy', 'universe_id' => '0', 'users' => '', 'bases' => '' ) );
	$check( array() === ps_pen_paper_import_options_from_post()['users'], 'Empty form sentinel invented assignments.' );
	$_POST['import']['users'] = array( $users['player'] => array() );
	$check( is_wp_error( ps_pen_paper_import_options_from_post() ), 'Malformed assignment input accepted.' );
	if ( '1' === getenv( 'PSPP_TEST_HTTP' ) ) {
		$session = WP_Session_Tokens::get_instance( $users['gm'] )->create( time() + 300 );
		$_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie( $users['gm'], time() + 300, 'logged_in', $session );
		$cookie = LOGGED_IN_COOKIE . '=' . $_COOKIE[LOGGED_IN_COOKIE] . '; ' . AUTH_COOKIE . '=' .
			wp_generate_auth_cookie( $users['gm'], time() + 300, 'auth', $session ) . '; ' .
			( is_multisite() ? 'wdeb_on_site_' . get_current_blog_id() : 'wdeb_on' ) . '=1';
		$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_author' => $users['gm'],
			'post_title' => 'Import HTTP ' . wp_generate_password( 8, false ), 'post_content' => '[ps_pen_paper]' ), true );
		$check( is_int( $page ), 'HTTP page fixture failed.' ); $pages[] = $page;
		foreach ( array( admin_url( 'admin-post.php' ), get_permalink( $page ) ) as $index => $url ) {
			$boundary = 'pspp' . wp_generate_password( 20, false );
			$fields = array( 'action' => 'ps_pen_paper', 'pspp_operation' => 'preview_import', '_wpnonce' => wp_create_nonce( 'pspp_import_upload' ) );
			if ( $index ) { $fields['pspp_frontend_page'] = (string) $page; }
			$body = '';
			foreach ( $fields as $name => $value ) { $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n"; }
			$body .= "--$boundary\r\nContent-Disposition: form-data; name=\"import_file\"; filename=\"backup.json\"\r\nContent-Type: application/json\r\n\r\n$json\r\n--$boundary--\r\n";
			$response = wp_remote_post( $url, array( 'timeout' => 30, 'redirection' => 0, 'headers' =>
				array( 'Cookie' => $cookie, 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ), 'body' => $body ) );
			$check( ! is_wp_error( $response ) && 302 === wp_remote_retrieve_response_code( $response ), 'Multipart preview upload failed.' );
			$location = wp_remote_retrieve_header( $response, 'location' );
			parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );
			$check( isset( $query['pspp_import_preview'] ), 'Upload did not redirect to a private preview.' );
			$http_token = $query['pspp_import_preview']; $tokens[ $http_token ] = $users['gm'];
			$initial_http = ps_pen_paper_get_import_preview( $http_token );
			$check( is_array( $initial_http ), 'HTTP preview not accessible to its owner.' );
			$input = array( 'action' => 'ps_pen_paper', 'pspp_operation' => 'configure_import_preview', 'import_token' => $http_token,
				'_wpnonce' => wp_create_nonce( 'pspp_import_preview_' . $http_token ), 'import' => $replace_options );
			if ( $index ) { $input['pspp_frontend_page'] = $page; }
			foreach ( $input['import']['users'] as &$value ) { $value = (string) $value; } unset( $value );
			$input['import']['target_id'] = (string) $target; $input['import']['universe_id'] = (string) $world;
			$input['import']['bases'][ $base ] = (string) $base;
			for ( $repeat = 0; $repeat < 2; $repeat++ ) {
				$response = wp_remote_post( $url, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Cookie' => $cookie ), 'body' => $input ) );
				$check( ! is_wp_error( $response ) && 302 === wp_remote_retrieve_response_code( $response ), 'Repeated preview configuration failed.' );
			}
			$response = wp_remote_get( $location, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Cookie' => $cookie ) ) );
			if ( ! $index && '1' === getenv( 'PSPP_TEST_HTTP_ADMIN_REDIRECT' ) ) {
				$check( ! is_wp_error( $response ) && 302 === wp_remote_retrieve_response_code( $response ) &&
					wp_parse_url( wp_remote_retrieve_header( $response, 'location' ), PHP_URL_PATH ) === wp_parse_url( admin_url( 'index.php' ), PHP_URL_PATH ),
					'Expected external admin start-page policy changed.' );
				WP_CLI::warning( 'External admin start-page policy prevents HTTP preview GET; backend actions and direct rendering are checked, frontend GET remains mandatory.' );
			} else {
				$check( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) &&
					false !== strpos( wp_remote_retrieve_body( $response ), 'name="import[operation]"' ), 'Private preview page failed.' );
			}
			$key = ps_pen_paper_import_preview_key( $http_token );
			foreach ( array( '_transient_' . $key, '_transient_timeout_' . $key ) as $cache_key ) { wp_cache_delete( $cache_key, 'options' ); }
			wp_cache_delete( $key, 'transient' );
			$updated_http = ps_pen_paper_get_import_preview( $http_token );
			$check( is_array( $updated_http ) && $updated_http['options'] === $replace_options &&
				$updated_http['expires'] === $initial_http['expires'], 'HTTP configuration was not saved or extended the preview lifetime.' );
			$input['_wpnonce'] = 'invalid';
			$response = wp_remote_post( $url, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Cookie' => $cookie ), 'body' => $input ) );
			$check( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) >= 400, 'Invalid preview nonce accepted.' );
			$input['_wpnonce'] = wp_create_nonce( 'pspp_import_preview_' . $http_token );
			$input['pspp_operation'] = 'discard_import_preview';
			$response = wp_remote_post( $url, array( 'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Cookie' => $cookie ), 'body' => $input ) );
			$check( ! is_wp_error( $response ) && 302 === wp_remote_retrieve_response_code( $response ), 'HTTP discard failed.' );
			foreach ( array( '_transient_' . $key, '_transient_timeout_' . $key ) as $cache_key ) { wp_cache_delete( $cache_key, 'options' ); }
			wp_cache_delete( $key, 'transient' );
			$check( is_wp_error( ps_pen_paper_get_import_preview( $http_token ) ), 'Discarded preview remained accessible.' );
		}
	}
	$check( $before === ps_pen_paper_export_campaign( $campaign, 'complete' )['payload_sha256'] &&
		$before_target === ps_pen_paper_export_campaign( $target, 'local' )['payload_sha256'], 'Preview changed source or target game data.' );
	$stored['blog_id']++;
	set_transient( ps_pen_paper_import_preview_key( $token ), $stored, HOUR_IN_SECONDS );
	$check( is_wp_error( ps_pen_paper_get_import_preview( $token ) ), 'Preview with another blog scope was accepted.' );
	$stored['blog_id'] = get_current_blog_id();
	$stored['expires'] = time() - 1;
	set_transient( ps_pen_paper_import_preview_key( $token ), $stored, HOUR_IN_SECONDS );
	$check( is_wp_error( ps_pen_paper_get_import_preview( $token ) ), 'Expired preview remained accessible.' );
	WP_CLI::success( 'Import preview validation, runtime choices, ownership, limits and unchanged game data passed' .
		( '1' === getenv( 'PSPP_TEST_HTTP' ) ? ' including real uploads and repeated backend/frontend forms.' : '.' ) );
} finally {
	$_POST = $original_post; wp_set_current_user( $users ? $users['gm'] : $original_user );
	foreach ( $tokens as $token => $owner ) { wp_set_current_user( $owner ); delete_transient( ps_pen_paper_import_preview_key( $token ) ); }
	if ( $users ) { wp_set_current_user( $users['gm'] ); }
	if ( null === $original_cookie ) { unset( $_COOKIE[LOGGED_IN_COOKIE] ); } else { $_COOKIE[LOGGED_IN_COOKIE] = $original_cookie; }
	foreach ( $pages as $id ) { if ( ! wp_delete_post( $id, true ) ) { WP_CLI::warning( 'Preview page cleanup failed: ' . $id ); } }
	foreach ( $parents as $id ) {
		foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent=%d", $id ) ) as $child ) {
			if ( ! wp_delete_post( (int) $child, true ) ) { WP_CLI::warning( 'Preview child cleanup failed: ' . $child ); }
		}
		if ( ! wp_delete_post( $id, true ) ) { WP_CLI::warning( 'Preview scope cleanup failed: ' . $id ); }
	}
	wp_set_current_user( $original_user );
	foreach ( $users as $id ) { if ( ! ( is_multisite() ? wpmu_delete_user( $id ) : wp_delete_user( $id ) ) ) { WP_CLI::warning( 'Preview user cleanup failed: ' . $id ); } }
}
