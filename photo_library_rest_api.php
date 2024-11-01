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
 * Version:         0.1.0
 *
 * @package         Photo_library_rest_api
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
	exit;
}

if (!function_exists('add_action')) {
	error_log('add_action error');
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define('PL__PLUGIN_DIR', plugin_dir_path(__FILE__) . 'src');

require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-db.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library-route.php';

// Add a new prefix for REST API endpoints.
add_filter('rest_url_prefix', function () {
	return 'api';
});

add_action('init', ['PhotoLibrary', 'init']);

add_action('rest_api_init', ['PhotoLibrary',  'register_rest_routes']);
