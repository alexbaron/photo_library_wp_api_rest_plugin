<?php

/**
 * PhotoLibrary REST API Plugin
 *
 * @package Photo_library_rest_api
 *
 * Plugin Name:     Photo_library_rest_api
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     A WordPress plugin that provides a REST API for managing and searching a photo library with advanced features like color palette extraction, similarity search, and Pinecone vector database integration.
 * Author:          alex baron
 * Author URI:      YOUR SITE HERE
 * Text Domain:     photo_library_rest_api
 * Version:         2.0.0
 */

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Alex\PhotoLibraryRestApi\Command\PhotoLibrarySfCommand;
use Alex\PhotoLibraryRestApi\Command\ColorSearchTestCommand;
use Alex\PhotoLibraryRestApi\Command\CronTasksCommand;
use Alex\PhotoLibraryRestApi\Command\ParallelProcessingCommand;
use Alex\PhotoLibraryRestApi\Command\PhotoLibraryCommand;
use Alex\PhotoLibraryRestApi\Command\WPCLISymfonyBridge;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('add_action')) {
    exit;
}

// Register Symfony commands as WP-CLI commands
if (defined('WP_CLI') && WP_CLI) {
    // Register existing Pinecone test command
    $symfonyCommand = new PhotoLibrarySfCommand();
    $commandName = str_replace(':', ' ', $symfonyCommand->getName());

    \WP_CLI::add_command($commandName, new WPCLISymfonyBridge($symfonyCommand), [
        'shortdesc' => $symfonyCommand->getDescription(),
    ]);

    // Register new color search test command
    $colorSearchCommand = new ColorSearchTestCommand();
    $colorCommandName = str_replace(':', ' ', $colorSearchCommand->getName());

    \WP_CLI::add_command($colorCommandName, new WPCLISymfonyBridge($colorSearchCommand), [
        'shortdesc' => $colorSearchCommand->getDescription(),
    ]);

// Register cron tasks command
$cronTasksCommand = new CronTasksCommand();
$cronCommandName = str_replace(':', ' ', $cronTasksCommand->getName());

\WP_CLI::add_command($cronCommandName, new WPCLISymfonyBridge($cronTasksCommand), [
    'shortdesc' => $cronTasksCommand->getDescription(),
]);

// Register parallel processing command
$parallelCommand = new ParallelProcessingCommand();
$parallelCommandName = str_replace(':', ' ', $parallelCommand->getName());

\WP_CLI::add_command($parallelCommandName, new WPCLISymfonyBridge($parallelCommand), [
    'shortdesc' => $parallelCommand->getDescription(),
]);

// Register main PhotoLibrary command
$photoLibraryCommand = new PhotoLibraryCommand();
$photoLibraryCommandName = str_replace(':', ' ', $photoLibraryCommand->getName());

\WP_CLI::add_command($photoLibraryCommandName, new WPCLISymfonyBridge($photoLibraryCommand), [
    'shortdesc' => $photoLibraryCommand->getDescription(),
]);

}

define('PL__PLUGIN_DIR', plugin_dir_path(__FILE__) . 'src');

require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'class.photo-library.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'class.photo-library-cache.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'class.photo-library-file-cache.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'color' . DIRECTORY_SEPARATOR . 'class.photo-library-color.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'color' . DIRECTORY_SEPARATOR . 'class.photo-library-rgb-distance.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'color' . DIRECTORY_SEPARATOR . 'class.photo-library-async-palette.php';

require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'class.photo-library-db.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'class.photo-library-schema.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'handler' . DIRECTORY_SEPARATOR . 'class.photo-library-data-handler.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'class.photo-library-install.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'logger' . DIRECTORY_SEPARATOR . 'class.photo-library-palette-logger.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'pinecone' . DIRECTORY_SEPARATOR . 'class.photo-library-pinecone.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'routing' . DIRECTORY_SEPARATOR . 'class.photo-library-route.php';

require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'class.photo-library-frontend.php';

require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'react' . DIRECTORY_SEPARATOR . 'class.photo-library-react-app.php';
require_once PL__PLUGIN_DIR . DIRECTORY_SEPARATOR . 'react' . DIRECTORY_SEPARATOR . 'class.photo-library-wordpress-page.php';



// Initialize configuration.

add_action('init', [ 'PhotoLibrary', 'init' ]);

add_action('init', [ 'PhotoLibrary_Frontend', 'init' ]);


// Handle CORS headers for all REST API requests - multiple hooks for maximum coverage.
add_filter('rest_pre_serve_request', [ 'PhotoLibrary', 'rest_send_cors_headers' ]);
add_action('rest_api_init', [ 'PhotoLibrary', 'rest_send_cors_headers' ]);
add_action('wp_headers', [ 'PhotoLibrary', 'rest_send_cors_headers' ]);

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
    },
);

// Additional CORS handling for any request that might bypass the filters.
add_action(
    'send_headers',
    function () {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-WP-Nonce');
        header('Access-Control-Allow-Credentials: true');
    },
);


// Initialize React App integration EARLY
PL_React_App::init();
PL_WordPress_Page::init();

add_action('rest_api_init', [ 'PhotoLibrary', 'register_rest_routes' ]);

// Cache invalidation hooks pour serveur mutualisé
add_action('add_attachment', [ 'PL_Cache_Manager', 'mark_content_updated' ]);
add_action('edit_attachment', [ 'PL_Cache_Manager', 'mark_content_updated' ]);
add_action('delete_attachment', [ 'PL_Cache_Manager', 'mark_content_updated' ]);
add_action('set_object_terms', [ 'PL_Cache_Manager', 'mark_content_updated' ], 10, 4);

register_activation_hook(__FILE__, [ 'PL_INSTALL', 'create_table' ]);

$installation = new PL_INSTALL();
