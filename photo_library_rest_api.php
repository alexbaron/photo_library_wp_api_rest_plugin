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
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-data-handler.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-db.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-install.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-route.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-schema.php';

// Initialize configuration.

add_action('init', array( 'PhotoLibrary', 'init' ));

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

register_activation_hook(__FILE__, array( 'PL_INSTALL', 'create_table' ));

$installation = new PL_INSTALL();
