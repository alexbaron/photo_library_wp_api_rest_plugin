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
 * - POST /wp-json/photo-library/v1/pictures/by_color      - Search by color similarity (Pinecone)
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

        // Swagger/OpenAPI documentation
        register_rest_route(
            $this->namespace,
            '/docs',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_swagger_docs' ),
                'permission_callback' => '__return_true',
            )
        );

        // Swagger UI (interface interactive)
        register_rest_route(
            $this->namespace,
            '/docs/ui',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_swagger_ui' ),
                'permission_callback' => '__return_true',
            )
        );

        // Search pictures by color similarity
        register_rest_route(
            $this->namespace,
            '/pictures/by_color',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE, // POST
                    'callback'            => array( $this, 'search_pictures_by_color' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'rgb' => array(
                            'required'    => true,
                            'type'        => 'array',
                            'description' => 'RGB color array [r, g, b]',
                            'validate_callback' => function ($param) {
                                return is_array($param) && count($param) === 3 &&
                                       is_numeric($param[0]) && is_numeric($param[1]) && is_numeric($param[2]) &&
                                       $param[0] >= 0 && $param[0] <= 255 &&
                                       $param[1] >= 0 && $param[1] <= 255 &&
                                       $param[2] >= 0 && $param[2] <= 255;
                            }
                        ),
                        'top_k' => array(
                            'required'    => false,
                            'type'        => 'integer',
                            'default'     => 10,
                            'description' => 'Number of results to return',
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && $param > 0 && $param <= 100;
                            }
                        ),
                        'filter' => array(
                            'required'    => false,
                            'type'        => 'object',
                            'default'     => array(),
                            'description' => 'Metadata filters'
                        )
                    ),
                ),
            )
        );

        // Search pictures by dominant color (alternative to by_color)
        register_rest_route(
            $this->namespace,
            '/pictures/by_dominant_color',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE, // POST
                    'callback'            => array( $this, 'search_pictures_by_dominant_color' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'rgb' => array(
                            'required'    => true,
                            'type'        => 'array',
                            'description' => 'RGB color array [r, g, b]',
                            'validate_callback' => function ($param) {
                                return is_array($param) && count($param) === 3 &&
                                       is_numeric($param[0]) && is_numeric($param[1]) && is_numeric($param[2]) &&
                                       $param[0] >= 0 && $param[0] <= 255 &&
                                       $param[1] >= 0 && $param[1] <= 255 &&
                                       $param[2] >= 0 && $param[2] <= 255;
                            }
                        ),
                        'tolerance' => array(
                            'required'    => false,
                            'type'        => 'integer',
                            'default'     => 30,
                            'description' => 'Color tolerance (0-255)',
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && $param >= 0 && $param <= 255;
                            }
                        ),
                        'limit' => array(
                            'required'    => false,
                            'type'        => 'integer',
                            'default'     => 20,
                            'description' => 'Maximum number of results',
                            'validate_callback' => function ($param) {
                                return is_numeric($param) && $param > 0 && $param <= 100;
                            }
                        ),
                        'method' => array(
                            'required'    => false,
                            'type'        => 'string',
                            'default'     => 'euclidean',
                            'description' => 'Distance calculation method: euclidean, manhattan, or weighted',
                            'validate_callback' => function ($param) {
                                return in_array($param, ['euclidean', 'manhattan', 'weighted']);
                            }
                        )
                    ),
                ),
            )
        );

        // Test Pinecone connection
        register_rest_route(
            $this->namespace,
            '/pinecone/test',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'test_pinecone_connection' ),
                'permission_callback' => '__return_true',
            )
        );

        // Pinecone diagnostics for production debugging
        register_rest_route(
            $this->namespace,
            '/pinecone/diagnostics',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_pinecone_diagnostics' ),
                'permission_callback' => '__return_true',
            )
        );

        // Symfony Commands Documentation
        register_rest_route(
            $this->namespace,
            '/commands/reference',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_commands_reference' ),
                'permission_callback' => '__return_true',
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
        $data['cached'] = false;

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

    /**
     * Search Pictures by Color Similarity
     *
     * Recherche des photos par similarité de couleur RGB en utilisant Pinecone.
     * Cette endpoint utilise l'index vectoriel pour trouver les photos avec des
     * couleurs similaires à celle spécifiée.
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request Request object containing RGB color and parameters
     * @return WP_REST_Response Pictures matching the color query with similarity scores
     *
     * @example POST /wp-json/photo-library/v1/pictures/by_color
     * Body: {"rgb": [120, 150, 200], "top_k": 10}
     */
    public function search_pictures_by_color(WP_REST_Request $request): WP_REST_Response
    {
        try {
            // Récupérer les paramètres
            $rgb_color = $request->get_param('rgb');
            $top_k = $request->get_param('top_k') ?: 10;
            $filter = $request->get_param('filter') ?: array();

            // Valider les paramètres RGB
            if (!is_array($rgb_color) || count($rgb_color) !== 3) {
                return new WP_REST_Response(
                    array('error' => 'Invalid RGB format. Expected array [r, g, b]'),
                    400
                );
            }

            // Convertir en entiers
            $rgb_color = array_map('intval', $rgb_color);

            // Initialiser l'index Pinecone
            $color_index = new PL_Color_Search_Index();

            // Effectuer la recherche directe dans Pinecone
            $results = $color_index->search_by_color($rgb_color, $top_k, $filter);

            if (empty($results)) {
                return new WP_REST_Response(
                    array(
                        'query_color' => $rgb_color,
                        'results_count' => 0,
                        'pictures' => array(),
                        'message' => 'No similar colors found'
                    ),
                    200
                );
            }

            // Récupérer les données complètes des photos
            global $wpdb;
            $db = new PL_REST_DB($wpdb);
            $pictures_data = array();

            foreach ($results as $result) {
                $photo_id = $result['photo_id'];

                // Récupérer les données de la photo depuis WordPress
                $attachment = get_post($photo_id);
                if (!$attachment || $attachment->post_type !== 'attachment') {
                    continue;
                }

                // Récupérer les métadonnées
                $metadata = wp_get_attachment_metadata($photo_id);
                $keywords = wp_get_object_terms($photo_id, 'attachment_tag', array('fields' => 'names'));

                // Construire l'objet photo avec score de similarité
                $picture = array(
                    'id' => $photo_id,
                    'title' => $attachment->post_title,
                    'description' => $attachment->post_content,
                    'date' => $attachment->post_date,
                    'src' => array(
                        'thumbnail' => wp_get_attachment_image_src($photo_id, 'thumbnail'),
                        'medium' => wp_get_attachment_image_src($photo_id, 'medium'),
                        'large' => wp_get_attachment_image_src($photo_id, 'large'),
                        'full' => wp_get_attachment_image_src($photo_id, 'full'),
                    ),
                    'keywords' => $keywords,
                    'color_score' => round($result['color_score'], 3),
                    'color_match' => $result['color_match'],
                    'metadata' => $result['metadata']
                );

                $pictures_data[] = $picture;
            }

            $response_data = array(
                'query_color' => $rgb_color,
                'results_count' => count($pictures_data),
                'pictures' => $pictures_data,
                'search_method' => 'pinecone',
                'total_matches' => count($results),
                'timestamp' => current_time('mysql')
            );

            return new WP_REST_Response($response_data, 200);

        } catch (Exception $e) {
            error_log('PhotoLibrary Color Search Error: ' . $e->getMessage());

            return new WP_REST_Response(
                array(
                    'error' => 'Color search failed',
                    'message' => $e->getMessage(),
                    'query_color' => $rgb_color ?? null
                ),
                500
            );
        }
    }

    /**
     * Get Swagger/OpenAPI documentation
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response with OpenAPI specification
     */
    public function get_swagger_docs($request)
    {
        try {
            $swagger_file = plugin_dir_path(__FILE__) . '../../swagger.yaml';

            if (!file_exists($swagger_file)) {
                error_log('PhotoLibrary: Swagger file not found at: ' . $swagger_file);
                return new WP_REST_Response(
                    array(
                        'error' => 'Swagger documentation not found',
                        'path' => $swagger_file
                    ),
                    404
                );
            }

            $swagger_content = file_get_contents($swagger_file);

            if ($swagger_content === false) {
                error_log('PhotoLibrary: Failed to read swagger file');
                return new WP_REST_Response(
                    array('error' => 'Failed to read documentation file'),
                    500
                );
            }

            // Parse YAML and return as JSON
            if (function_exists('yaml_parse')) {
                $swagger_data = yaml_parse($swagger_content);

                if ($swagger_data === false) {
                    error_log('PhotoLibrary: Failed to parse YAML content');
                    return new WP_REST_Response(
                        array('error' => 'Invalid YAML format in documentation'),
                        500
                    );
                }
            } else {
                error_log('PhotoLibrary: YAML extension not available, returning raw YAML');
                $response = new WP_REST_Response($swagger_content, 200);
                $response->header('Content-Type', 'application/x-yaml; charset=UTF-8');
                $response->header('Access-Control-Allow-Origin', '*');
                return $response;
            }

            // Validate essential structure
            if (!isset($swagger_data['openapi']) || !isset($swagger_data['paths'])) {
                error_log('PhotoLibrary: Invalid OpenAPI structure');
                return new WP_REST_Response(
                    array('error' => 'Invalid OpenAPI specification structure'),
                    500
                );
            }

            // Update server URLs with current site URL
            $current_url = home_url('/wp-json/photo-library/v1');
            $swagger_data['servers'] = array(
                array(
                    'url' => $current_url,
                    'description' => 'Current WordPress site'
                ),
                array(
                    'url' => 'https://your-wordpress-site.com/wp-json/photo-library/v1',
                    'description' => 'Production server example'
                )
            );

            $response = new WP_REST_Response($swagger_data, 200);
            $response->header('Content-Type', 'application/json; charset=UTF-8');
            $response->header('Access-Control-Allow-Origin', '*');
            $response->header('Cache-Control', 'public, max-age=300'); // Cache 5 minutes

            return $response;

        } catch (Exception $e) {
            error_log('PhotoLibrary: Exception in get_swagger_docs: ' . $e->getMessage());
            return new WP_REST_Response(
                array(
                    'error' => 'Failed to load documentation',
                    'message' => $e->getMessage()
                ),
                500
            );
        }
    }

    /**
     * Get Swagger UI (interactive documentation interface)
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response HTML page with Swagger UI
     */
    public function get_swagger_ui($request)
    {
        try {
            $docs_url = home_url('/wp-json/photo-library/v1/docs');

            // Générer le HTML sans échappement WordPress
            ob_start();
            ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PhotoLibrary REST API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui.css" />
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .swagger-ui .topbar { display: none; }
        .swagger-ui .info { margin: 20px 0; }
        .swagger-ui .info h1 { color: #3c4043; font-size: 28px; }
        .custom-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            margin-bottom: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .custom-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .custom-header p {
            margin: 15px 0 0 0;
            opacity: 0.9;
            font-size: 16px;
        }
        .custom-header .version {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            margin-top: 10px;
        }
        #swagger-ui {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .swagger-ui .scheme-container {
            background: #fafafa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="custom-header">
        <h1>📸 PhotoLibrary REST API</h1>
        <p>Documentation interactive - Gestion et recherche d'images WordPress</p>
        <div class="version">v1.0.0</div>
    </div>

    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@4.15.5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            // Ajouter un indicateur de chargement
            document.getElementById('swagger-ui').innerHTML =
                '<div id="loading" style="text-align: center; padding: 50px; color: #666;">' +
                '<div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-radius: 50%; border-top: 4px solid #667eea; animation: spin 1s linear infinite;"></div>' +
                '<p style="margin-top: 20px;">Chargement de la documentation...</p>' +
                '</div>';

            // Tester d'abord la connectivité à l'API
            const docsUrl = "<?php echo esc_url($docs_url); ?>";
            console.log("Testing API docs endpoint:", docsUrl);

            fetch(docsUrl)
                .then(response => {
                    console.log("API response status:", response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("API specification loaded successfully", data);

                    // Initialiser Swagger UI avec les données chargées
                    const ui = SwaggerUIBundle({
                        spec: data, // Utiliser les données directement au lieu de l'URL
                        dom_id: "#swagger-ui",
                        deepLinking: true,
                        presets: [
                            SwaggerUIBundle.presets.apis,
                            SwaggerUIStandalonePreset
                        ],
                        plugins: [
                            SwaggerUIBundle.plugins.DownloadUrl
                        ],
                        layout: "StandaloneLayout",
                        tryItOutEnabled: true,
                        supportedSubmitMethods: ["get", "post", "put", "delete", "patch"],
                        onComplete: function() {
                            console.log("PhotoLibrary API Documentation rendered successfully");
                        },
                        docExpansion: "list",
                        defaultModelsExpandDepth: 1,
                        defaultModelExpandDepth: 1,
                        displayRequestDuration: true,
                        filter: true,
                        showExtensions: true,
                        showCommonExtensions: true,
                        validatorUrl: null
                    });
                })
                .catch(error => {
                    console.error("Failed to load API documentation:", error);
                    document.getElementById('swagger-ui').innerHTML =
                        '<div style="padding: 20px; background: #ffebee; border-left: 4px solid #f44336; margin: 20px;">' +
                        '<h3 style="color: #d32f2f; margin-top: 0;">❌ Erreur de chargement</h3>' +
                        '<p><strong>Impossible de charger la spécification OpenAPI.</strong></p>' +
                        '<p><strong>URL testée:</strong> <code>' + docsUrl + '</code></p>' +
                        '<p><strong>Erreur:</strong> ' + error.message + '</p>' +
                        '<hr style="margin: 20px 0;">' +
                        '<p><strong>Solutions à essayer:</strong></p>' +
                        '<ul>' +
                        '<li>Vérifiez que le plugin PhotoLibrary est activé</li>' +
                        '<li>Testez l\'endpoint directement: <a href="' + docsUrl + '" target="_blank">' + docsUrl + '</a></li>' +
                        '<li>Vérifiez les logs d\'erreur WordPress</li>' +
                        '<li>Rechargez la page</li>' +
                        '</ul>' +
                        '</div>';
                });
        };
    </script>
    <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</body>
</html>
            <?php
            $html = ob_get_clean();

            // Sortir directement le HTML sans passer par WP_REST_Response pour éviter l'échappement
            status_header(200);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

            echo $html;
            exit;

        } catch (Exception $e) {
            return new WP_REST_Response(
                array('error' => 'Failed to load Swagger UI: ' . $e->getMessage()),
                500
            );
        }
    }

    /**
     * Search pictures by dominant color with different distance algorithms
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response with matching pictures
     */
    public function search_pictures_by_dominant_color($request)
    {
        global $wpdb;

        try {
            // Récupérer les paramètres
            $rgb_color = $request->get_param('rgb');
            $tolerance = intval($request->get_param('tolerance') ?? 30);
            $limit = intval($request->get_param('limit') ?? 5);
            $method = $request->get_param('method') ?? 'euclidean';
            $use_pinecone = $request->get_param('use_pinecone') ?? true;

            if (!is_array($rgb_color) || count($rgb_color) !== 3) {
                return new WP_REST_Response(
                    array('error' => 'RGB color must be an array of 3 integers'),
                    400
                );
            }

            $target_r = (int) $rgb_color[0];
            $target_g = (int) $rgb_color[1];
            $target_b = (int) $rgb_color[2];

            // Tenter d'abord la recherche avec Pinecone via Guzzle
            $pinecone_results = [];
            $search_source = 'local';

            if ($use_pinecone) {
                try {
                    error_log('Attempting Pinecone search for color: ' . json_encode($rgb_color) . ' with limit: ' . $limit);
                    $pinecone_results = $this->search_pinecone_with_guzzle($rgb_color, $limit);
                    error_log('Pinecone returned ' . count($pinecone_results) . ' results');
                    if (!empty($pinecone_results)) {
                        $search_source = 'pinecone';
                        error_log('Using Pinecone search results: ' . count($pinecone_results) . ' photos');
                    } else {
                        error_log('Pinecone returned 0 results, falling back to local search');
                    }
                } catch (Exception $e) {
                    error_log('Pinecone search failed, falling back to local: ' . $e->getMessage());
                }
            }

            // Si Pinecone a des résultats, les utiliser
            if (!empty($pinecone_results)) {
                $pictures_data = [];

                foreach ($pinecone_results as $match) {
                    $photo_id = $match['photo_id'];
                    $photo_info = get_post($photo_id);

                    if ($photo_info && $photo_info->post_type === 'attachment') {
                        $attachment_url = wp_get_attachment_url($photo_id);
                        $attachment_metadata = wp_get_attachment_metadata($photo_id);
                        
                        // Get different image sizes
                        $thumbnail_url = wp_get_attachment_image_url($photo_id, 'thumbnail');
                        $medium_url = wp_get_attachment_image_url($photo_id, 'medium');
                        $large_url = wp_get_attachment_image_url($photo_id, 'large');
                        
                        // Get color palette
                        $palette = get_post_meta($photo_id, '_pl_palette', true);
                        if ($palette && is_string($palette)) {
                            $palette = maybe_unserialize($palette);
                        }

                        $pictures_data[] = [
                            'id' => $photo_id,
                            'title' => $photo_info->post_title,
                            'url' => $attachment_url,
                            'src' => [
                                'thumbnail' => $thumbnail_url ?: $attachment_url,
                                'medium' => $medium_url ?: $attachment_url,
                                'large' => $large_url ?: $attachment_url,
                                'full' => $attachment_url
                            ],
                            'palette' => $palette ?: null,
                            'similarity_score' => $match['score'],
                            'dominant_color' => $match['color'],
                            'dominant_color_hex' => sprintf('#%02x%02x%02x', ...$match['color']),
                            'search_method' => 'pinecone',
                            'width' => $attachment_metadata['width'] ?? null,
                            'height' => $attachment_metadata['height'] ?? null
                        ];
                    }
                }

                // Only return Pinecone results if we actually have pictures
                if (!empty($pictures_data)) {
                    return new WP_REST_Response(
                        array(
                            'query_color' => $rgb_color,
                            'query_color_hex' => sprintf('#%02x%02x%02x', $target_r, $target_g, $target_b),
                            'search_source' => $search_source,
                            'results_count' => count($pictures_data),
                            'background_photo' => !empty($pictures_data) ? $pictures_data[0] : null,
                            'thumbnail_photos' => $pictures_data,
                            'pictures' => $pictures_data // Keep for backward compatibility
                        ),
                        200
                    );
                }
                // If Pinecone returned results but no valid pictures, fall through to local search
                error_log('Pinecone results contained no valid pictures, falling back to local search');
            }

            // Fallback: Recherche locale dans WordPress
            $photos_query = "
                SELECT p.ID, p.post_title, pm.meta_value as palette_data
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image/%'
                AND pm.meta_key = '_pl_palette'
                AND pm.meta_value IS NOT NULL
                AND pm.meta_value != ''
                ORDER BY p.post_date DESC
            ";

            $photos_with_palettes = $wpdb->get_results($photos_query);

            if (empty($photos_with_palettes)) {
                return new WP_REST_Response(
                    array(
                        'query_color' => $rgb_color,
                        'method' => $method,
                        'tolerance' => $tolerance,
                        'search_source' => $search_source,
                        'results_count' => 0,
                        'background_photo' => null,
                        'thumbnail_photos' => [],
                        'pictures' => [], // Keep for backward compatibility
                        'message' => 'No photos with color palettes found'
                    ),
                    200
                );
            }

            $matching_photos = [];

            foreach ($photos_with_palettes as $photo) {
                $palette = maybe_unserialize($photo->palette_data);

                if (!is_array($palette) || empty($palette)) {
                    continue;
                }

                // Extraire la couleur dominante
                $dominant_color = null;
                if (isset($palette[0]) && is_array($palette[0]) && count($palette[0]) >= 3) {
                    $dominant_color = $palette[0];
                } elseif (isset($palette['dominant']) && is_array($palette['dominant'])) {
                    $dominant_color = $palette['dominant'];
                } elseif (is_array($palette) && count($palette) >= 3 && is_numeric($palette[0])) {
                    $dominant_color = $palette;
                }

                if ($dominant_color === null) {
                    continue;
                }

                $photo_r = (int) $dominant_color[0];
                $photo_g = (int) $dominant_color[1];
                $photo_b = (int) $dominant_color[2];

                // Calculer la distance selon la méthode choisie
                $distance = $this->calculate_color_distance(
                    [$target_r, $target_g, $target_b],
                    [$photo_r, $photo_g, $photo_b],
                    $method
                );

                // Vérifier si la couleur est dans la tolérance
                $max_distance = $this->get_max_distance($method);
                $color_tolerance = ($tolerance / 255.0) * $max_distance;

                if ($distance <= $color_tolerance) {
                    // Calculer le score de similarité (0-1, 1 = identique)
                    $similarity_score = 1.0 - ($distance / $max_distance);

                    $matching_photos[] = [
                        'photo_id' => (int) $photo->ID,
                        'distance' => round($distance, 2),
                        'similarity_score' => round($similarity_score, 4),
                        'dominant_color' => [$photo_r, $photo_g, $photo_b],
                        'dominant_color_hex' => sprintf('#%02x%02x%02x', $photo_r, $photo_g, $photo_b),
                        'method' => $method
                    ];
                }
            }

            // Trier par distance croissante (plus proche en premier)
            usort($matching_photos, function ($a, $b) {
                return $a['distance'] <=> $b['distance'];
            });

            // Limiter les résultats
            $matching_photos = array_slice($matching_photos, 0, $limit);

            // Préparer les données de réponse avec informations de base
            if (!empty($matching_photos)) {
                $pictures_data = [];

                foreach ($matching_photos as $match) {
                    // Récupérer les informations de base de la photo
                    $photo_info = get_post($match['photo_id']);

                    if ($photo_info && $photo_info->post_type === 'attachment') {
                        $attachment_url = wp_get_attachment_url($match['photo_id']);
                        $attachment_metadata = wp_get_attachment_metadata($match['photo_id']);
                        
                        // Get different image sizes
                        $thumbnail_url = wp_get_attachment_image_url($match['photo_id'], 'thumbnail');
                        $medium_url = wp_get_attachment_image_url($match['photo_id'], 'medium');
                        $large_url = wp_get_attachment_image_url($match['photo_id'], 'large');
                        
                        // Get color palette
                        $palette = get_post_meta($match['photo_id'], '_pl_palette', true);
                        if ($palette && is_string($palette)) {
                            $palette = maybe_unserialize($palette);
                        }

                        $pictures_data[] = [
                            'id' => $match['photo_id'],
                            'title' => $photo_info->post_title,
                            'url' => $attachment_url,
                            'src' => [
                                'thumbnail' => $thumbnail_url ?: $attachment_url,
                                'medium' => $medium_url ?: $attachment_url,
                                'large' => $large_url ?: $attachment_url,
                                'full' => $attachment_url
                            ],
                            'palette' => $palette ?: null,
                            'distance' => $match['distance'],
                            'similarity_score' => $match['similarity_score'],
                            'dominant_color' => $match['dominant_color'],
                            'dominant_color_hex' => $match['dominant_color_hex'],
                            'search_method' => $method,
                            'width' => $attachment_metadata['width'] ?? null,
                            'height' => $attachment_metadata['height'] ?? null
                        ];
                    }
                }

                return new WP_REST_Response(
                    array(
                        'query_color' => $rgb_color,
                        'query_color_hex' => sprintf('#%02x%02x%02x', $target_r, $target_g, $target_b),
                        'method' => $method,
                        'tolerance' => $tolerance,
                        'results_count' => count($pictures_data),
                        'background_photo' => !empty($pictures_data) ? $pictures_data[0] : null,
                        'thumbnail_photos' => $pictures_data,
                        'pictures' => $pictures_data, // Keep for backward compatibility
                        'total_photos_scanned' => count($photos_with_palettes)
                    ),
                    200
                );
            }

            return new WP_REST_Response(
                array(
                    'query_color' => $rgb_color,
                    'query_color_hex' => sprintf('#%02x%02x%02x', $target_r, $target_g, $target_b),
                    'method' => $method,
                    'tolerance' => $tolerance,
                    'results_count' => 0,
                    'background_photo' => null,
                    'thumbnail_photos' => [],
                    'pictures' => [], // Keep for backward compatibility
                    'total_photos_scanned' => count($photos_with_palettes),
                    'message' => 'No photos found within the specified color tolerance'
                ),
                200
            );

        } catch (Exception $e) {
            error_log('PhotoLibrary Dominant Color Search Error: ' . $e->getMessage());

            return new WP_REST_Response(
                array(
                    'error' => 'Dominant color search failed',
                    'message' => $e->getMessage(),
                    'query_color' => $rgb_color ?? null
                ),
                500
            );
        }
    }

    /**
     * Search photos in Pinecone using Guzzle HTTP client
     *
     * @param array $rgb_color RGB color [r, g, b]
     * @param int $limit Number of results to return
     * @return array Array of matching photos with scores
     * @throws Exception If Pinecone API fails
     */
    private function search_pinecone_with_guzzle(array $rgb_color, int $limit = 5): array
    {
        // Load .env file
        $env_file = dirname(__FILE__) . '/../../.env';
        if (file_exists($env_file)) {
            $this->load_env($env_file);
        }

        $api_key = $_ENV['PINECONE_API_KEY'] ?? (getenv('PINECONE_API_KEY') ?: null);
        $index_name = $_ENV['PINECONE_INDEX_NAME'] ?? (getenv('PINECONE_INDEX_NAME') ?: 'phototheque-color-search');

        if (empty($api_key)) {
            throw new Exception('PINECONE_API_KEY not configured');
        }

        // Clean API key
        $api_key = trim($api_key, "'\"");

        // Get index host from Pinecone API
        $client = new \GuzzleHttp\Client([
            'base_uri' => 'https://api.pinecone.io',
            'timeout' => 10.0,
            'headers' => [
                'Api-Key' => $api_key,
                'Accept' => 'application/json',
            ]
        ]);

        $response = $client->get("/indexes/{$index_name}");
        $index_data = json_decode($response->getBody()->getContents(), true);

        if (!isset($index_data['host'])) {
            throw new Exception('Could not find Pinecone index host');
        }

        $host = $index_data['host'];

        // Normalize RGB to 0-1 range for Pinecone
        $normalized_vector = [
            $rgb_color[0] / 255.0,
            $rgb_color[1] / 255.0,
            $rgb_color[2] / 255.0
        ];

        // Query Pinecone index
        $index_client = new \GuzzleHttp\Client([
            'base_uri' => "https://{$host}",
            'timeout' => 10.0,
            'headers' => [
                'Api-Key' => $api_key,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]
        ]);

        $query_response = $index_client->post('/query', [
            'json' => [
                'vector' => $normalized_vector,
                'topK' => $limit,
                'includeMetadata' => true,
                'namespace' => 'photos'
            ]
        ]);

        $results = json_decode($query_response->getBody()->getContents(), true);

        // Debug logging
        error_log('Pinecone query request: ' . json_encode([
            'vector' => $normalized_vector,
            'topK' => $limit,
            'includeMetadata' => true,
            'namespace' => 'photos'
        ]));
        error_log('Pinecone raw response: ' . json_encode($results));

        if (!isset($results['matches']) || empty($results['matches'])) {
            error_log('No matches found in Pinecone response');
            return [];
        }

        $matches = [];
        foreach ($results['matches'] as $match) {
            $metadata = $match['metadata'] ?? [];
            
            // Get photo_id from metadata instead of match id (which is prefixed with 'img_')
            $photo_id = isset($metadata['photo_id']) ? (int) $metadata['photo_id'] : 0;
            
            // Skip if no valid photo_id
            if ($photo_id === 0) {
                error_log('Pinecone match missing photo_id in metadata: ' . json_encode($match));
                continue;
            }
            
            $score = $match['score'];

            $color = isset($metadata['rgb']) ? $metadata['rgb'] : $rgb_color;
            
            // Parse RGB string if it's in "r,g,b" format
            if (is_string($color) && strpos($color, ',') !== false) {
                $color = array_map('intval', explode(',', $color));
            }

            // Convert normalized color back to 0-255 if needed
            if (is_array($color) && max($color) <= 1.0) {
                $color = [
                    (int) ($color[0] * 255),
                    (int) ($color[1] * 255),
                    (int) ($color[2] * 255)
                ];
            }

            $matches[] = [
                'photo_id' => $photo_id,
                'score' => round($score, 4),
                'color' => $color
            ];
        }

        return $matches;
    }

    /**
     * Simple .env file loader
     */
    private function load_env(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // Remove quotes
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    /**
     * Calculate color distance using different algorithms
     *
     * @param array $color1 First RGB color [r, g, b]
     * @param array $color2 Second RGB color [r, g, b]
     * @param string $method Distance calculation method
     * @return float Distance value
     */
    private function calculate_color_distance(array $color1, array $color2, string $method = 'euclidean'): float
    {
        $r1 = (float) $color1[0];
        $g1 = (float) $color1[1];
        $b1 = (float) $color1[2];

        $r2 = (float) $color2[0];
        $g2 = (float) $color2[1];
        $b2 = (float) $color2[2];

        switch ($method) {
            case 'manhattan':
                // Distance de Manhattan (L1)
                return abs($r1 - $r2) + abs($g1 - $g2) + abs($b1 - $b2);

            case 'weighted':
                // Distance pondérée selon la perception humaine
                // Les coefficients reflètent la sensibilité de l'œil humain
                $dr = $r1 - $r2;
                $dg = $g1 - $g2;
                $db = $b1 - $b2;
                return sqrt(0.3 * $dr * $dr + 0.59 * $dg * $dg + 0.11 * $db * $db);

            case 'euclidean':
            default:
                // Distance euclidienne standard (L2)
                return sqrt(
                    pow($r1 - $r2, 2) +
                    pow($g1 - $g2, 2) +
                    pow($b1 - $b2, 2)
                );
        }
    }

    /**
     * Get maximum possible distance for normalization
     *
     * @param string $method Distance calculation method
     * @return float Maximum distance
     */
    private function get_max_distance(string $method): float
    {
        switch ($method) {
            case 'manhattan':
                return 3 * 255; // Maximum Manhattan distance in RGB space

            case 'weighted':
                return sqrt(0.3 + 0.59 + 0.11) * 255; // Maximum weighted distance

            case 'euclidean':
            default:
                return sqrt(3 * pow(255, 2)); // Maximum Euclidean distance in RGB space
        }
    }

    /**
     * Test Pinecone Connection
     *
     * Tests the connection to Pinecone by attempting to retrieve index statistics.
     * This endpoint verifies that the API key and host are configured correctly
     * and that the Pinecone service is accessible.
     *
     * @since 1.0.0
     *
     * @return WP_REST_Response Connection test results
     *
     * @example GET /wp-json/photo-library/v1/pinecone/test
     */
    public function test_pinecone_connection(): WP_REST_Response
    {
        try {
            $color_index = new PL_Color_Search_Index();
            $stats = $color_index->get_index_stats();

            if (isset($stats['error'])) {
                return new WP_REST_Response(
                    array(
                        'success' => false,
                        'message' => 'Connection failed',
                        'error' => $stats['error'],
                        'timestamp' => current_time('mysql')
                    ),
                    500
                );
            }

            return new WP_REST_Response(
                array(
                    'success' => true,
                    'message' => 'Connexion réussie !',
                    'stats' => $stats,
                    'timestamp' => current_time('mysql')
                ),
                200
            );

        } catch (Exception $e) {
            error_log('PhotoLibrary Pinecone Test Error: ' . $e->getMessage());

            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Connection test failed',
                    'error' => $e->getMessage(),
                    'timestamp' => current_time('mysql')
                ),
                500
            );
        }
    }

    /**
     * Get Pinecone Diagnostics for Production Debugging
     *
     * Comprehensive diagnostic endpoint to troubleshoot Pinecone issues
     * in production environment. Checks configuration, connectivity,
     * and environment-specific issues.
     *
     * @since 1.0.0
     *
     * @return WP_REST_Response Comprehensive diagnostic information
     *
     * @example GET /wp-json/photo-library/v1/pinecone/diagnostics
     */
    public function get_pinecone_diagnostics(): WP_REST_Response
    {
        $diagnostics = array(
            'timestamp' => current_time('mysql'),
            'environment' => array(),
            'configuration' => array(),
            'connectivity' => array(),
            'errors' => array()
        );

        // Environment checks
        $diagnostics['environment']['php_version'] = PHP_VERSION;
        $diagnostics['environment']['wp_version'] = get_bloginfo('version');
        $diagnostics['environment']['memory_limit'] = ini_get('memory_limit');
        $diagnostics['environment']['max_execution_time'] = ini_get('max_execution_time');
        $diagnostics['environment']['guzzle_available'] = class_exists('GuzzleHttp\\Client');

        // Configuration checks
        try {
            $env_file = dirname(__FILE__) . '/../../.env';
            $diagnostics['configuration']['env_file_exists'] = file_exists($env_file);
            $diagnostics['configuration']['env_file_readable'] = is_readable($env_file);

            if (file_exists($env_file)) {
                $this->load_env($env_file);
            }

            $api_key = $_ENV['PINECONE_API_KEY'] ?? (getenv('PINECONE_API_KEY') ?: null);
            $index_name = $_ENV['PINECONE_INDEX_NAME'] ?? (getenv('PINECONE_INDEX_NAME') ?: 'phototheque-color-search');

            $diagnostics['configuration']['api_key_configured'] = !empty($api_key);
            $diagnostics['configuration']['api_key_length'] = $api_key ? strlen($api_key) : 0;
            $diagnostics['configuration']['index_name'] = $index_name;

            // Test basic connectivity
            if (!empty($api_key)) {
                try {
                    $client = new \GuzzleHttp\Client([
                        'base_uri' => 'https://api.pinecone.io',
                        'timeout' => 5.0,
                        'headers' => [
                            'Api-Key' => trim($api_key, "'\""),
                            'Accept' => 'application/json',
                        ]
                    ]);

                    $response = $client->get("/indexes/{$index_name}");
                    $index_data = json_decode($response->getBody()->getContents(), true);

                    $diagnostics['connectivity']['api_reachable'] = true;
                    $diagnostics['connectivity']['index_exists'] = isset($index_data['host']);
                    $diagnostics['connectivity']['index_host'] = $index_data['host'] ?? 'Not found';

                    if (isset($index_data['host'])) {
                        // Test index connectivity
                        $index_client = new \GuzzleHttp\Client([
                            'base_uri' => "https://{$index_data['host']}",
                            'timeout' => 5.0,
                            'headers' => [
                                'Api-Key' => trim($api_key, "'\""),
                                'Accept' => 'application/json',
                                'Content-Type' => 'application/json',
                            ]
                        ]);

                        $stats_response = $index_client->post('/describe_index_stats');
                        $stats = json_decode($stats_response->getBody()->getContents(), true);
                        $diagnostics['connectivity']['index_stats'] = $stats;
                        $diagnostics['connectivity']['index_reachable'] = true;
                    }

                } catch (Exception $e) {
                    $diagnostics['connectivity']['api_reachable'] = false;
                    $diagnostics['errors'][] = array(
                        'type' => 'connectivity',
                        'message' => $e->getMessage(),
                        'code' => $e->getCode()
                    );
                }
            }

        } catch (Exception $e) {
            $diagnostics['errors'][] = array(
                'type' => 'configuration',
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            );
        }

        // WordPress database checks
        try {
            global $wpdb;
            $photos_count = $wpdb->get_var("
                SELECT COUNT(*)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image/%'
                AND pm.meta_key = '_pl_palette'
                AND pm.meta_value IS NOT NULL
                AND pm.meta_value != ''
            ");

            $diagnostics['database']['photos_with_palettes'] = (int) $photos_count;

        } catch (Exception $e) {
            $diagnostics['errors'][] = array(
                'type' => 'database',
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            );
        }

        // Determine overall status
        $has_errors = !empty($diagnostics['errors']);
        $pinecone_ready = $diagnostics['configuration']['api_key_configured'] &&
                         ($diagnostics['connectivity']['api_reachable'] ?? false);

        $status_code = $has_errors ? 500 : 200;
        $diagnostics['overall_status'] = $has_errors ? 'error' : ($pinecone_ready ? 'ready' : 'warning');

        return new WP_REST_Response($diagnostics, $status_code);
    }

    /**
     * Get Symfony Commands Reference Documentation
     *
     * Returns the complete JSON reference for all PhotoLibrary Symfony commands
     * including arguments, options, examples, and usage patterns.
     *
     * @since 1.0.0
     *
     * @return WP_REST_Response Commands reference JSON data
     *
     * @example GET /wp-json/photo-library/v1/commands/reference
     */
    public function get_commands_reference(): WP_REST_Response
    {
        try {
            $reference_file = dirname(__FILE__, 2) . '/documentation/symfony-commands-reference.json';

            if (!file_exists($reference_file)) {
                return new WP_REST_Response(
                    array(
                        'error' => 'Commands reference file not found',
                        'file_path' => $reference_file,
                        'timestamp' => current_time('mysql')
                    ),
                    404
                );
            }

            $json_content = file_get_contents($reference_file);
            $reference_data = json_decode($json_content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_REST_Response(
                    array(
                        'error' => 'Invalid JSON in reference file',
                        'json_error' => json_last_error_msg(),
                        'timestamp' => current_time('mysql')
                    ),
                    500
                );
            }

            // Add metadata
            $reference_data['meta'] = array(
                'file_size' => filesize($reference_file),
                'last_modified' => date('Y-m-d H:i:s', filemtime($reference_file)),
                'file_path' => str_replace(dirname(__FILE__, 3), '', $reference_file),
                'endpoint_accessed' => current_time('mysql'),
                'total_commands' => count($reference_data['commands'] ?? [])
            );

            return new WP_REST_Response($reference_data, 200);

        } catch (Exception $e) {
            error_log('PhotoLibrary Commands Reference Error: ' . $e->getMessage());

            return new WP_REST_Response(
                array(
                    'error' => 'Failed to load commands reference',
                    'message' => $e->getMessage(),
                    'timestamp' => current_time('mysql')
                ),
                500
            );
        }
    }
}
