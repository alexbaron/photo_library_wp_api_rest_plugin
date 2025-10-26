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
    public function __construct(
    ) {
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
        error_log("PhotoLibrary_Route::register_routes called");

        // test purpose
        register_rest_route($this->namespace, '/test', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => ['PhotoLibrary_Route', 'test_request'],
            'permission_callback' => '__return_true',
        ]);
        error_log("Test route registered: " . $this->namespace . '/test');

        // get all pictures
        register_rest_route($this->namespace, '/' . $this->resourceName . '/all', [
            [
                'methods'   => WP_REST_Server::READABLE,
                'callback'  => [$this, 'get_pictures'],
                'permission_callback' => '__return_true',
            ],
        ]);

        // pass id = 0 to get a random picture
        register_rest_route($this->namespace, '/' . $this->resourceName . '/random' . '/(?P<id>[\d]+)', [
            [
                'methods'   => WP_REST_Server::READABLE,
                'callback'  => [$this, 'get_random_picture'],
                'permission_callback' => '__return_true',
            ],
        ]);

        // Get pictures by keyword
        register_rest_route($this->namespace, '/' . $this->resourceName . '/by_keywords', [
            [
                'methods'   => WP_REST_Server::CREATABLE,
                'callback'  => [$this, 'get_pictures_by_keyword'],
                'permission_callback' => '__return_true',
            ],
        ]);

        //Get all keywords
        register_rest_route($this->namespace, '/' . $this->resourceName . '/keywords', [
            [
                'methods'   => WP_REST_Server::READABLE,
                'callback'  => [$this, 'get_keywords'],
                'permission_callback' => '__return_true',
            ],
        ]);

        //Get hierarchy
        register_rest_route($this->namespace, '/' . $this->resourceName . '/hierarchy', [
            [
                'methods'   => WP_REST_Server::READABLE,
                'callback'  => [$this, 'get_hierarchy'],
                'permission_callback' => '__return_true',
            ],
        ]);

        // Get picture by id
        register_rest_route($this->namespace, '/' . $this->resourceName . '/(?P<id>[\d]+)', [
            [
                'methods'   => WP_REST_Server::READABLE,
                'callback'  => [$this, 'get_pictures_by_id'],
                'permission_callback' => '__return_true',
            ],
        ]);
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
        $message = ['message' => 'PhotoLibrary REST API is working!'];
        return new WP_REST_Response($message, 200);
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
    public function get_pictures_by_keyword($request): WP_REST_Response
    {
        $keywords = $request->get_param('search') ?? [];
        $data = PL_REST_DB::getMediaByKeywords($keywords);
        return new WP_REST_Response($data, 200);
    }

    /**
     * Get all available keywords (flat list)
     *
     * Retrieves all keywords/tags available in the system as a flat array.
     * Prioritizes WP/LR Sync data when available, falls back to direct
     * database queries otherwise.
     *
     * @since 0.1.0
     * @since 0.2.0 Added WP/LR Sync integration and hierarchy flattening
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
     *   "source": "wplr_sync" // or "fallback_db"
     * }
     */
    public function get_keywords(): WP_REST_Response
    {
        $data = ['message' => 'get_keywords called', 'data' => []];

        if ($this->is_wplr_available()) {
            try {
                // Use WP/LR Sync keyword hierarchy
                $keywords_hierarchy = $this->wplrSync->get_keywords_hierarchy();
                $data['data'] = PL_DATA_HANDLER::filter_keywords_to_flat_array($keywords_hierarchy);
                // $data['source'] = 'wplr_sync';
            } catch (Exception $e) {
                error_log('PhotoLibrary: Error getting keywords from WP/LR Sync: ' . $e->getMessage());
                $data['error'] = 'WP/LR Sync keywords unavailable';
            }
        } else {
            // Fallback to existing method
            $data['data'] = PL_REST_DB::getKeywords();
            $data['source'] = 'fallback_db';
        }

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
        $data = ['message' => 'get_hierarchy called', 'data' => []];

        if ($this->is_wplr_available()) {
            try {
                // Use WP/LR Sync keyword hierarchy
                $keywords_hierarchy = $this->wplrSync->get_hierarchy();
                $data['data'] = $keywords_hierarchy;
                // $data['source'] = 'wplr_sync';
            } catch (Exception $e) {
                error_log('PhotoLibrary: Error getting hierarchy from WP/LR Sync: ' . $e->getMessage());
                $data['error'] = 'WP/LR Sync hierarchy unavailable';
            }
        } else {
            // Fallback to existing method
            $data['data'] = PL_REST_DB::getKeywords();
            $data['source'] = 'fallback_db';
        }

        return new WP_REST_Response($data, 200);
    }

    /**
     * Get specific picture by ID
     *
     * Retrieves detailed information about a specific picture using its
     * WordPress media ID.
     *
     * @since 0.1.0
     *
     * @param WP_REST_Request $request REST request containing the picture ID
     *
     * @return WP_REST_Response Picture details with metadata
     *
     * @example GET /wp-json/photo-library/v1/pictures/123
     *
     * @todo Implement actual picture retrieval logic
     */
    public function get_pictures_by_id($request): WP_REST_Response
    {
        $id = $request->get_param('id');
        $message = ['message' => 'get_pictures_by_id called with ID: ' . $id, 'data' => []];
        return new WP_REST_Response($message, 200);
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
        $message = ['message' => 'get_pictures called', 'data' => []];
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
        $id = $request->get_param('id');
        $message = ['message' => 'get_random_picture called with ID: ' . $id, 'data' => []];
        return new WP_REST_Response($message, 200);
    }
}
