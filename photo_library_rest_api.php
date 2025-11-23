<?php

/**
 * PhotoLibrary REST API Plugin
 *
 * @package Photo_library_rest_api
 *
 * Plugin Name:     Photo_library_rest_api
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     PLUGIN DESCRIPTION HERE
 * Author:          alex baron
 * Author URI:      YOUR SITE HERE
 * Text Domain:     photo_library_rest_api
 * Version:         0.2.0
 */

require_once __DIR__ . '/vendor/autoload.php';

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('add_action')) {
    exit;
}

define('PL__PLUGIN_DIR', plugin_dir_path(__FILE__) . 'src');

require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-cache.php';
// Chargement des classes nécessaires
require_once plugin_dir_path(__FILE__) . 'src/class.photo-library-cache.php';
require_once plugin_dir_path(__FILE__) . 'src/class.photo-library-file-cache.php';
require_once plugin_dir_path(__FILE__) . 'src/class.photo-library-db.php';
require_once plugin_dir_path(__FILE__) . 'src/class.photo-library-data-handler.php';
require_once plugin_dir_path(__FILE__) . 'src/color/class.photo-library-color.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-pinecone.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-install.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-route.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-schema.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-react-app.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-wordpress-page.php';

// Initialize configuration.

add_action('init', array( 'PhotoLibrary', 'init' ));
add_action('init', array( 'PL_React_App', 'init' ));
add_action('init', array( 'PL_WordPress_Page', 'init' ));

// Hooks d'activation et désactivation pour gérer les règles de réécriture
register_activation_hook(__FILE__, 'photo_library_plugin_activate');
register_deactivation_hook(__FILE__, 'photo_library_plugin_deactivate');

function photo_library_plugin_activate()
{
    // Ajouter les règles de réécriture
    PL_React_App::add_rewrite_rules();
    // Vider le cache des règles
    flush_rewrite_rules();
}

function photo_library_plugin_deactivate()
{
    // Vider le cache des règles
    flush_rewrite_rules();
}

// Handle CORS headers for all REST API requests - multiple hooks for maximum coverage.
add_filter('rest_pre_serve_request', array( 'PhotoLibrary', 'rest_send_cors_headers' ));
add_action('rest_api_init', array( 'PhotoLibrary', 'rest_send_cors_headers' ));
add_action('wp_headers', array( 'PhotoLibrary', 'rest_send_cors_headers' ));

// Handle OPTIONS requests early.
add_action(
    'init',
    function () {
        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-WP-Nonce');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
            status_header(200);
            exit();
        }
    }
);

// Additional CORS handling for any request that might bypass the filters.
add_action(
    'send_headers',
    function () {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-WP-Nonce');
        header('Access-Control-Allow-Credentials: true');
    }
);

add_action('rest_api_init', array( 'PhotoLibrary', 'register_rest_routes' ));

// Cache invalidation hooks pour serveur mutualisé
add_action('add_attachment', array( 'PL_Cache_Manager', 'mark_content_updated' ));
add_action('edit_attachment', array( 'PL_Cache_Manager', 'mark_content_updated' ));
add_action('delete_attachment', array( 'PL_Cache_Manager', 'mark_content_updated' ));
add_action('set_object_terms', array( 'PL_Cache_Manager', 'mark_content_updated' ), 10, 4);

register_activation_hook(__FILE__, array( 'PL_INSTALL', 'create_table' ));
register_activation_hook(__FILE__, array( 'PL_React_App', 'flush_rewrite_rules' ));

$installation = new PL_INSTALL();
