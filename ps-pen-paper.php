<?php
/**
 * Plugin Name: PS Pen & Paper
 * Description: Ein universeller Baukasten für Pen-and-Paper-Kampagnen.
 * Version: 0.29.0
 * Author: PSOURCE
 * Text Domain: ps-pen-paper
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/scope-state.php';
require_once __DIR__ . '/includes/campaigns.php';
require_once __DIR__ . '/includes/rules.php';
require_once __DIR__ . '/includes/rules-admin.php';
require_once __DIR__ . '/includes/characters.php';
require_once __DIR__ . '/includes/characters-admin.php';
require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/events-ui.php';
require_once __DIR__ . '/includes/dice.php';
require_once __DIR__ . '/includes/dice-ui.php';
require_once __DIR__ . '/includes/styles.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/includes/access-ui.php';
require_once __DIR__ . '/includes/backdrop.php';
require_once __DIR__ . '/includes/maps.php';
require_once __DIR__ . '/includes/maps-ui.php';
require_once __DIR__ . '/includes/folio.php';
require_once __DIR__ . '/includes/folio-ui.php';
require_once __DIR__ . '/includes/npcs.php';
require_once __DIR__ . '/includes/npcs-ui.php';
require_once __DIR__ . '/includes/wiki.php';
require_once __DIR__ . '/includes/wiki-ui.php';
require_once __DIR__ . '/includes/bookings.php';
require_once __DIR__ . '/includes/bookings-ui.php';
require_once __DIR__ . '/includes/community.php';
require_once __DIR__ . '/includes/equipment.php';
require_once __DIR__ . '/includes/item-use.php';
require_once __DIR__ . '/includes/economy.php';
require_once __DIR__ . '/includes/economy-ui.php';
require_once __DIR__ . '/includes/loot.php';
require_once __DIR__ . '/includes/loot-ui.php';
require_once __DIR__ . '/includes/trade.php';
require_once __DIR__ . '/includes/trade-ui.php';
require_once __DIR__ . '/includes/transfers.php';
require_once __DIR__ . '/includes/transfers-ui.php';
require_once __DIR__ . '/includes/adventure.php';
require_once __DIR__ . '/includes/adventure-world.php';
require_once __DIR__ . '/includes/adventure-scenarios.php';
require_once __DIR__ . '/includes/adventure-scenarios-ui.php';
require_once __DIR__ . '/includes/adventure-world-ui.php';
require_once __DIR__ . '/includes/adventure-actions.php';
require_once __DIR__ . '/includes/adventure-reactions.php';
require_once __DIR__ . '/includes/adventure-review.php';
require_once __DIR__ . '/includes/adventure-review-ui.php';
require_once __DIR__ . '/includes/adventure-groups.php';
require_once __DIR__ . '/includes/adventure-groups-ui.php';
require_once __DIR__ . '/includes/adventure-rewards.php';
require_once __DIR__ . '/includes/adventure-ui.php';
require_once __DIR__ . '/includes/appearance.php';
require_once __DIR__ . '/includes/equipment-ui.php';
require_once __DIR__ . '/includes/item-use-ui.php';
require_once __DIR__ . '/includes/universes.php';
require_once __DIR__ . '/includes/character-contexts.php';
require_once __DIR__ . '/includes/character-contexts-ui.php';
require_once __DIR__ . '/includes/universes-ui.php';
require_once __DIR__ . '/includes/dashboard.php';
require_once __DIR__ . '/includes/export.php';
require_once __DIR__ . '/includes/import.php';
require_once __DIR__ . '/includes/import-documents.php';
require_once __DIR__ . '/includes/import-prepare.php';
require_once __DIR__ . '/includes/import-write.php';
require_once __DIR__ . '/includes/import-ui.php';
require_once __DIR__ . '/includes/chat.php';
require_once __DIR__ . '/includes/table.php';
require_once __DIR__ . '/includes/table-ui.php';
require_once __DIR__ . '/includes/frontend.php';
require_once __DIR__ . '/includes/admin.php';

add_action( 'plugins_loaded', 'ps_pen_paper_load_textdomain' );
add_action( 'admin_post_pspp_handout_download', 'ps_pen_paper_download_handout' );
add_action( 'pre_get_posts', 'ps_pen_paper_scope_queries', PHP_INT_MAX );
add_action( 'init', 'ps_pen_paper_register_campaign_type' );
add_action( 'init', 'ps_pen_paper_register_character_type' );
add_action( 'init', 'ps_pen_paper_register_roll_type' );
add_action( 'init', 'ps_pen_paper_register_event_type' );
add_action( 'init', 'ps_pen_paper_register_asset_type' );
add_action( 'init', 'ps_pen_paper_register_scene_type' );
add_action( 'init', 'ps_pen_paper_register_folio_type' );
add_action( 'init', 'ps_pen_paper_register_npc_type' );
add_action( 'init', 'ps_pen_paper_register_wiki_type' );
add_action( 'init', 'ps_pen_paper_register_loot_types' );
add_action( 'init', 'ps_pen_paper_register_trade_type' );
add_action( 'init', 'ps_pen_paper_register_adventure_types' );
add_action( 'init', 'ps_pen_paper_register_item_type' );
add_filter( 'cpc_profile_tabs', 'ps_pen_paper_community_tabs', 10, 3 );
add_filter( 'cpc_profile_tab_content', 'ps_pen_paper_community_content', 10, 4 );
add_action( 'template_redirect', 'ps_pen_paper_community_prepare' );
add_action( 'wp_enqueue_scripts', 'ps_pen_paper_community_assets' );
add_action( 'init', 'ps_pen_paper_register_universe_type' );
add_action( 'before_delete_post', 'ps_pen_paper_delete_campaign_assets', 10, 2 );
add_action( 'admin_menu', 'ps_pen_paper_register_admin_menu' );
add_action( 'admin_enqueue_scripts', 'ps_pen_paper_enqueue_admin_assets' );
add_action( 'admin_post_ps_pen_paper', 'ps_pen_paper_handle_admin_action' );
add_action( 'template_redirect', 'ps_pen_paper_prepare_frontend', 0 );
add_action( 'template_redirect', 'ps_pen_paper_handle_frontend_action', 5 );
add_action( 'wp_enqueue_scripts', 'ps_pen_paper_enqueue_frontend' );
add_action( 'rest_api_init', 'ps_pen_paper_register_table_routes' );
add_action( 'rest_api_init', 'ps_pen_paper_register_map_routes' );
add_shortcode( 'ps_pen_paper', 'ps_pen_paper_frontend_shortcode' );
add_filter( 'theme_page_templates', 'ps_pen_paper_page_templates' );
add_filter( 'template_include', 'ps_pen_paper_frontend_template' );
add_filter( 'psource_chat_session_access', 'ps_pen_paper_chat_access', 10, 2 );
add_filter( 'psource_chat_session_moderator', 'ps_pen_paper_chat_moderator', 10, 2 );

function ps_pen_paper_load_textdomain() {
	load_plugin_textdomain(
		'ps-pen-paper',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}

