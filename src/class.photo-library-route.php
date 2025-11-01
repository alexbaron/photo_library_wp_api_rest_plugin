<?php

/**
 * PhotoLibrary REST API Route Controller
 *
 * This class handles all REST API endpoints for the PhotoLibrary plugin.
 * It extends WordPress's WP_REST_Controller to provide standardized REST API functionality
 * with integration support for WP/LR Sync plugin for advanced photo management.
 *
 * @package PhotoLibrary
 * @version 0.2.0
 * @author Alex Baron
 *
 * @since 0.1.0
 *
 * Features:
 * - Photo retrieval with metadata
 * - Keyword/tag-based search
 * - WP/LR Sync integration for Lightroom synchronization
 * - Hierarchical keyword structure support
 * - Fallback mechanisms when WP/LR Sync is unavailable
 *
 * REST API Endpoints:
 * - GET /wp-json/photo-library/v1/test                    - API health check
 * - GET /wp-json/photo-library/v1/pictures/all            - Get all pictures
 * - GET /wp-json/photo-library/v1/pictures/{id}           - Get picture by ID
 * - GET /wp-json/photo-library/v1/pictures/random/{id}    - Get random picture
 * - POST /wp-json/photo-library/v1/pictures/by_keywords   - Search by keywords
 * - GET /wp-json/photo-library/v1/pictures/keywords       - Get all keywords (flat)
 * - GET /wp-json/photo-library/v1/pictures/hierarchy      - Get keyword hierarchy
 *
 * Dependencies:
 * - WordPress REST API
 * - PL_REST_DB class for database operations
 * - WP/LR Sync plugin (optional, with graceful fallback)
 *
 * @link https://developer.wordpress.org/rest-api/
 * @link https://meowapps.com/plugin/wplr-sync/
 */
class PhotoLibrary_Route extends WP_REST_Controller
{
    /**
     * WP/LR Sync integration instance
     *
     * @var Meow_WPLR_Sync_Core|null Instance of WP/LR Sync core class for Lightroom integration
     * @since 0.2.0
     */
    protected $wplrSync;

    /**
     * REST API namespace for all endpoints
     *
     * @var string The namespace used for all PhotoLibrary REST endpoints
     * @since 0.1.0
     */
    protected $namespace;

    /**
     * Primary resource name for REST routes
     *
     * @var string The main resource name used in REST URLs
     * @since 0.1.0
     */
    protected $resourceName;

    /**
     * Constructor - Initialize PhotoLibrary REST API Controller
     *
     * Sets up the REST API namespace, resource names, and initializes
     * WP/LR Sync integration if available.
     *
     * @since 0.1.0
     *
     * @return void
     */
    public function __construct()
    {
        $this->namespace    = 'photo-library/v1';
        $this->resourceName = 'pictures';
        $this->init_wplr_sync();
    }

    /**
     * Initialize WP/LR Sync integration
     *
     * Attempts to connect with the WP/LR Sync plugin for advanced
     * Lightroom synchronization features. Gracefully handles cases
     * where the plugin is not installed or activated.
     *
     * @since 0.2.0
     *
     * @global Meow_WPLR_Sync_Core $wplr Global instance of WP/LR Sync
     *
     * @return void
     */
    private function init_wplr_sync()
    {
        global $wplr;

        if (isset($wplr) && ($wplr instanceof Meow_WPLR_Sync_Core)) {
            $this->wplrSync = $wplr;
            error_log('PhotoLibrary: WP/LR Sync Core integration enabled');
        } else {
            error_log('PhotoLibrary: WP/LR Sync Core not available');
        }
    }

    /**
     * Check if WP/LR Sync is available and functional
     *
     * @since 0.2.0
     *
     * @return bool True if WP/LR Sync is available, false otherwise
     */
    private function is_wplr_available(): bool
    {
        return $this->wplrSync !== null;
    }

    /**
     * Register all REST API routes for PhotoLibrary
     *
     * Registers all available REST API endpoints with their corresponding
     * HTTP methods, callbacks, and permission settings. All endpoints
     * are currently public (permission_callback => '__return_true').
     *
     * @since 0.1.0
     *
     * Registered Routes:
     * - GET /test                     - API health check
     * - GET /pictures/all             - Retrieve all pictures
     * - GET /pictures/{id}            - Get specific picture by ID
     * - GET /pictures/random/{id}     - Get random picture
     * - POST /pictures/by_keywords    - Search pictures by keywords
     * - GET /pictures/keywords        - Get all available keywords (flat list)
     * - GET /pictures/hierarchy       - Get keyword/collection hierarchy
     *
     * @return void
     */
    public function register_routes(): void
    {
        error_log('PhotoLibrary_Route::register_routes called');

        // test purpose
        register_rest_route(
            $this->namespace,
            '/test',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( 'PhotoLibrary_Route', 'test_request' ),
                'permission_callback' => '__return_true',
            )
        );
        error_log('Test route registered: ' . $this->namespace . '/test');

        // Configuration debug endpoint
        register_rest_route(
            $this->namespace,
            '/config',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_config_debug' ),
                'permission_callback' => '__return_true',
            )
        );

        // Cache statistics endpoint pour serveur mutualisé
        register_rest_route(
            $this->namespace,
            '/cache/stats',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_cache_stats' ),
                'permission_callback' => '__return_true',
            )
        );

        // Cache flush endpoint (utile pour les serveurs mutualisés)
        register_rest_route(
            $this->namespace,
            '/cache/flush',
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'flush_cache' ),
                'permission_callback' => '__return_true',
            )
        );

        // Test data handler endpoint.
        register_rest_route(
            $this->namespace,
            '/test-data/' . '(?P<id>[\d]+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'test_data_handler' ),
                'permission_callback' => '__return_true',
            )
        );

        // get all pictures
        register_rest_route(
            $this->namespace,
            '/' . $this->resourceName . '/all',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_pictures' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );

        // pass id = 0 to get a random picture
        register_rest_route(
            $this->namespace,
            '/' . $this->resourceName . '/random' . '(?:/(?P<id>[\d]+))?',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_random_picture' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );

        // Get pictures by keyword
        register_rest_route(
            $this->namespace,
            '/' . $this->resourceName . '/by_keywords',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'get_pictures_by_keyword' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );

        // Get all keywords
        register_rest_route(
            $this->namespace,
            '/' . $this->resourceName . '/keywords',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_keywords' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );

        // Get hierarchy
        register_rest_route(
            $this->namespace,
            '/' . $this->resourceName . '/hierarchy',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_hierarchy' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );

        // Get picture by id
        register_rest_route(
            $this->namespace,
            '/picture/(?P<id>[\d]+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_picture_by_id' ),
                    'permission_callback' => '__return_true',
                ),
            )
        );
    }

    /**
     * API Health Check Endpoint
     *
     * Simple endpoint to verify that the PhotoLibrary REST API is
     * functioning correctly. Useful for debugging and monitoring.
     *
     * @since 0.1.0
     *
     * @return WP_REST_Response Success message with HTTP 200 status
     *
     * @example GET /wp-json/photo-library/v1/test
     * Response: {"message": "PhotoLibrary REST API is working!"}
     */
    public static function test_request(): WP_REST_Response
    {
        $message = array( 'message' => 'PhotoLibrary REST API is working!' );
        PL_Cache_Manager::set_test_cache();

        $cacheTest = PL_Cache_Manager::get_test_cache( 'pl_test');
        $message['cache_test'] = $cacheTest;
        return new WP_REST_Response($message, 200);
    }

    /**
     * Configuration Debug Endpoint
     *
     * Shows environment settings, paths, and validation status.
     *
     * @since 0.2.0
     *
     * @return WP_REST_Response Configuration debug information
     *
     * @example GET /wp-json/photo-library/v1/config
     */
    public static function get_config_debug(): WP_REST_Response
    {
        $upload_dir = wp_upload_dir();
        $debug_info = array(
            'plugin_version' => '0.2.0',
            'wordpress_version' => get_bloginfo('version'),
            'upload_dir' => $upload_dir,
            'php_version' => PHP_VERSION,
        );
        $debug_info['message'] = 'PhotoLibrary Configuration Debug Info';
        $debug_info['timestamp'] = current_time('mysql');

        return new WP_REST_Response($debug_info, 200);
    }    /**
     * Test Data Handler Endpoint
     *
     * Tests the data handler functionality with a specific media ID.
     *
     * @since 0.2.0
     *
     * @param WP_REST_Request $request REST request containing media ID.
     * @return WP_REST_Response Media data with configuration info.
     */
    public function test_data_handler($request): WP_REST_Response
    {
        $media_id = $request->get_param('id');
        $upload_dir = wp_upload_dir();

        $data = array(
            'message'          => 'Data Handler Test',
            'media_id'         => $media_id,
            'upload_info'      => array(
                'basedir' => $upload_dir['basedir'],
                'baseurl' => $upload_dir['baseurl'],
            ),
            'media_data'       => PL_DATA_HANDLER::get_data_from_media($media_id),
            'timestamp'        => current_time('mysql'),
        );

        return new WP_REST_Response($data, 200);
    }

    /**
     * Search pictures by keywords
     *
     * Searches for pictures that are tagged with any of the provided keywords.
     * Uses WP/LR Sync integration when available for enhanced search capabilities.
     *
     * @since 0.2.0
     *
     * @param WP_REST_Request $request REST request object containing search parameters
     *
     * @return WP_REST_Response Array of matching pictures with metadata
     *
     * @example POST /wp-json/photo-library/v1/pictures/by_keywords
     * Body: {"search": ["beach", "sunset", "nature"]}
     *
     * Response Structure:
     * {
     *   "keywords_searched": ["beach", "sunset"],
     *   "keywords_found": ["beach", "sunset"],
     *   "total_found": 15,
     *   "media": [
     *     {
     *       "id": 123,
     *       "title": "Beach Sunset",
     *       "url": "https://site.com/wp-content/uploads/beach.jpg",
     *       "path": "2024/01/beach.jpg",
     *       "tags": ["beach", "sunset", "ocean"]
     *     }
     *   ]
     * }
     */
    /**
     * Search pictures by keywords
     *
     * Searches for pictures that are tagged with any of the provided keywords.
     * Uses cached results when available for improved performance.
     *
     * @since 0.1.0
     * @since 0.2.0 Added caching for search results
     *
     * @param WP_REST_Request $request REST request containing search parameters
     *
     * @return WP_REST_Response Array of matching pictures with metadata
     */
    public function get_pictures_by_keyword($request): WP_REST_Response
    {
        $keywords = $request->get_param('search') ?? array();

        // Vérification du cache pour cette recherche
        $cached_results = PL_Cache_Manager::get_search_results_cached($keywords);
        if ($cached_results !== false && PL_Cache_Manager::is_search_cache_valid()) {
            $cached_results['cached'] = true;
            $cached_results['cache_time'] = current_time('mysql');
            return new WP_REST_Response($cached_results, 200);
        }

        // Exécution de la recherche
        $data = PL_REST_DB::getMediaByKeywords($keywords);
        // $data['cached'] = false;
        $data['cached'] = 'not cached';

        // Mise en cache des résultats
        PL_Cache_Manager::set_search_results_cache($keywords, $data);

        return new WP_REST_Response($data, 200);
    }

    /**
     * Get all available keywords (flat list)
     *
     * Retrieves all keywords/tags available in the system as a flat array.
     * Prioritizes WP/LR Sync data when available, falls back to direct
     * database queries otherwise. Utilise le cache pour optimiser les performances.
     *
     * @since 0.1.0
     * @since 0.2.0 Added WP/LR Sync integration and hierarchy flattening
     * @since 0.2.0 Added caching for better performance
     *
     * @return WP_REST_Response Flat array of all keywords with their IDs
     *
     * @example GET /wp-json/photo-library/v1/pictures/keywords
     *
     * Response Structure:
     * {
     *   "message": "get_keywords called",
     *   "data": {
     *     "123": "beach",
     *     "124": "sunset",
     *     "125": "nature",
     *     "126": "portrait"
     *   },
     *   "source": "wplr_sync", // or "fallback_db"
     *   "cached": true // indique si les données viennent du cache
     * }
     */
    public function get_keywords(): WP_REST_Response
    {
        // Tentative de récupération depuis le cache
        $cached_data = PL_Cache_Manager::get_keywords_cached();
        if ($cached_data !== false) {
            $cached_data['cached'] = true;
            $cached_data['cache_time'] = current_time('mysql');
            return new WP_REST_Response($cached_data, 200);
        }

        $data = array(
            'message' => 'get_keywords called',
            'data'    => array(),
            'cached'  => false,
        );

        if ($this->is_wplr_available()) {
            try {
                // Use WP/LR Sync keyword hierarchy
                $keywords_hierarchy = $this->wplrSync->get_keywords_hierarchy();
                $data['data']       = PL_DATA_HANDLER::filter_keywords_to_flat_array($keywords_hierarchy);
                $data['source']     = 'wplr_sync';
            } catch (Exception $e) {
                error_log('PhotoLibrary: Error getting keywords from WP/LR Sync: ' . $e->getMessage());
                $data['error'] = 'WP/LR Sync keywords unavailable';
                // Fallback to database
                $data['data']   = PL_REST_DB::getKeywords();
                $data['source'] = 'fallback_db';
            }
        } else {
            // Fallback to existing method
            $data['data']   = PL_REST_DB::getKeywords();
            $data['source'] = 'fallback_db';
        }

        // Mise en cache des données pour les prochaines requêtes
        PL_Cache_Manager::set_keywords_cache($data);

        return new WP_REST_Response($data, 200);
    }

    /**
     * Get hierarchical structure of keywords and collections
     *
     * Retrieves the complete hierarchical structure of keywords, collections,
     * and folders as organized in Lightroom via WP/LR Sync. Falls back to
     * flat keyword list when WP/LR Sync is unavailable.
     *
     * @since 0.2.0
     *
     * @return WP_REST_Response Hierarchical tree structure of keywords/collections
     *
     * @example GET /wp-json/photo-library/v1/pictures/hierarchy
     *
     * Response Structure (WP/LR Sync available):
     * {
     *   "message": "get_hierarchy called",
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Travel",
     *       "type": "folder",
     *       "children": [
     *         {
     *           "id": 2,
     *           "name": "Europe",
     *           "type": "collection",
     *           "children": []
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    public function get_hierarchy(): WP_REST_Response
    {
        $data = array(
            'message' => 'get_hierarchy called',
            'data'    => array(),
        );

        if ($this->is_wplr_available()) {
            try {
                // Use WP/LR Sync keyword hierarchy
                $keywords_hierarchy = $this->wplrSync->get_hierarchy();
                $data['data']       = $keywords_hierarchy;
                // $data['source'] = 'wplr_sync';
            } catch (Exception $e) {
                error_log('PhotoLibrary: Error getting hierarchy from WP/LR Sync: ' . $e->getMessage());
                $data['error'] = 'WP/LR Sync hierarchy unavailable';
            }
        } else {
            // Fallback to existing method
            $data['data']   = PL_REST_DB::getKeywords();
            $data['source'] = 'fallback_db';
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * Get specific picture by ID
     *
     * Retrieves detailed information about a specific picture using its
     * WordPress media ID. Uses caching for improved performance.
     *
     * @since 0.1.0
     * @since 0.2.0 Added caching for picture data
     *
     * @param WP_REST_Request $request REST request containing the picture ID
     *
     * @return WP_REST_Response Picture details with metadata
     *
     * @example GET /wp-json/photo-library/v1/picture/92928
     */
    public function get_picture_by_id($request): WP_REST_Response
    {
        $id = $request->get_param('id');

        // Vérification du cache pour cette image
        $cached_data = PL_Cache_Manager::get_picture_by_id_cached($id);
        if ($cached_data !== false) {
            $cached_data['cached'] = true;
            $cached_data['cache_time'] = current_time('mysql');
            return new WP_REST_Response($cached_data, 200);
        }

        $data = array(
            'message' => 'get_picture_by_id called with ID: ' . $id,
            'data'    => array(),
            'cached'  => false,
        );

        $data['data'] = PL_DATA_HANDLER::get_data_from_media($id);

        // Mise en cache des données
        PL_Cache_Manager::set_picture_by_id_cache($id, $data);

        return new WP_REST_Response($data, 200);
    }

    /**
     * Get all pictures
     *
     * Retrieves all pictures in the library with their associated metadata,
     * tags, and file information.
     *
     * @since 0.1.0
     *
     * @param WP_REST_Request $request REST request object (may contain pagination params)
     *
     * @return WP_REST_Response Array of all pictures with metadata
     *
     * @example GET /wp-json/photo-library/v1/pictures/all
     *
     * @todo Implement actual picture retrieval with pagination
     * @todo Add filtering and sorting options
     */
    public function get_pictures($request): WP_REST_Response
    {
        $message = array(
            'message' => 'get_pictures called',
            'data'    => array(),
        );
        return new WP_REST_Response($message, 200);
    }

    /**
     * Get random picture(s)
     *
     * Retrieves one or more random pictures from the library. The ID parameter
     * can be used to specify the number of random pictures to return.
     *
     * @since 0.1.0
     *
     * @param WP_REST_Request $request REST request containing count parameter
     *
     * @return WP_REST_Response Random picture(s) with metadata
     *
     * @example GET /wp-json/photo-library/v1/pictures/random/3
     *
     * @todo Implement actual random picture selection logic
     * @todo Consider performance implications for large libraries
     */
    public function get_random_picture($request): WP_REST_Response
    {
        $id   = $request->get_param('id');
        $data = array(
            'message' => 'get_random_picture called with ID: ' . $id,
            'data'    => array(),
        );
        $data = PL_REST_DB::getRandomPicture($id);
        return new WP_REST_Response($data, 200);
    }

    /**
     * Get Cache Statistics
     *
     * Récupère les statistiques du cache WordPress pour le monitoring
     * des performances, particulièrement utile sur serveur mutualisé.
     *
     * @since 0.2.0
     *
     * @return WP_REST_Response Cache statistics and configuration
     *
     * @example GET /wp-json/photo-library/v1/cache/stats
     */
    public function get_cache_stats(): WP_REST_Response
    {
        $stats = PL_Cache_Manager::get_cache_stats();
        $stats['message'] = 'PhotoLibrary Cache Statistics';
        $stats['timestamp'] = current_time('mysql');
        $stats['server_info'] = array(
            'memory_limit'    => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status() !== false,
        );

        return new WP_REST_Response($stats, 200);
    }

    /**
     * Flush Cache
     *
     * Vide le cache du plugin. Utile pour forcer le rafraîchissement
     * des données sur serveur mutualisé.
     *
     * @since 0.2.0
     *
     * @return WP_REST_Response Confirmation of cache flush
     *
     * @example DELETE /wp-json/photo-library/v1/cache/flush
     */
    public function flush_cache(): WP_REST_Response
    {
        $success = PL_Cache_Manager::flush_all_cache();
        PL_Cache_Manager::mark_content_updated();

        $data = array(
            'message'   => 'Cache flush completed',
            'success'   => $success,
            'timestamp' => current_time('mysql'),
        );

        return new WP_REST_Response($data, $success ? 200 : 500);
    }
}
