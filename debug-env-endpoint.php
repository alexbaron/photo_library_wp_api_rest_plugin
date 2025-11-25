<?php
/**
 * Endpoint de debug pour les variables d'environnement
 * À ajouter dans class.photo-library-route.php
 */

/**
 * Debug endpoint pour les variables d'environnement
 * Usage: GET /wp-json/photo-library/v1/debug/env
 */
public function debug_env_endpoint(): WP_REST_Response {
    if (!current_user_can('manage_options')) {
        return new WP_REST_Response(['error' => 'Unauthorized'], 403);
    }

    $debug_data = [
        'timestamp' => current_time('mysql'),
        'env_manager_info' => class_exists('PL_Env_Manager') ? PL_Env_Manager::get_debug_info() : 'Not loaded',
        'pinecone_key_status' => [
            'getenv' => getenv('PINECONE_API_KEY') ? 'SET (' . strlen(getenv('PINECONE_API_KEY')) . ' chars)' : 'NOT SET',
            'env_manager' => class_exists('PL_Env_Manager') && PL_Env_Manager::has('PINECONE_API_KEY') ? 'SET' : 'NOT SET',
            'server' => isset($_SERVER['PINECONE_API_KEY']) ? 'SET' : 'NOT SET',
            'env' => isset($_ENV['PINECONE_API_KEY']) ? 'SET' : 'NOT SET'
        ],
        'file_paths' => [
            'plugin_dir' => PL__PLUGIN_DIR ?? 'undefined',
            'env_file' => plugin_dir_path(__FILE__) . '.env',
            'env_exists' => file_exists(plugin_dir_path(__FILE__) . '.env')
        ],
        'php_info' => [
            'version' => PHP_VERSION,
            'sapi' => php_sapi_name(),
            'user' => get_current_user(),
            'cwd' => getcwd()
        ]
    ];

    return new WP_REST_Response($debug_data, 200);
}

// À ajouter dans register_routes()
register_rest_route(
    $this->namespace,
    '/debug/env',
    array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => array($this, 'debug_env_endpoint'),
        'permission_callback' => '__return_true',
    )
);
