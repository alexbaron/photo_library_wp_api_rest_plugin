<?php

/**
 * @package Photo_library_rest_api
 */
/**
 * Plugin Name:     Photo_library_rest_api
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     PLUGIN DESCRIPTION HERE
 * Author:          alex baron
 * Author URI:      YOUR SITE HERE
 * Text Domain:     photo_library_rest_api
 * Version:         0.2.0
 *
 * @package         Photo_library_rest_api
 */


require_once __DIR__ . '/vendor/autoload.php';

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

if (!function_exists('add_action')) {
	error_log('add_action error');
	exit;
}

define('PL__PLUGIN_DIR', plugin_dir_path(__FILE__) . 'src');

require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-data-handler.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-db.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-install.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-route.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-schema.php';

// Add a new prefix for REST API endpoints.
// add_filter('rest_url_prefix', function () {
// 	return 'api';
// });

add_action('init', ['PhotoLibrary', 'init']);

add_filter('rest_pre_serve_request', ['PhotoLibrary', 'rest_send_cors_headers']);

add_action('rest_api_init', ['PhotoLibrary',  'register_rest_routes']);

register_activation_hook(__FILE__, ['PL_INSTALL', 'create_table']);

$installation = new PL_INSTALL();
// $installation->insert_data();
