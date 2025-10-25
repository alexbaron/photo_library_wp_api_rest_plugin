<?php

class PhotoLibrary_Route extends WP_REST_Controller
{
	protected $namespace;
	protected $resourceName;

	// Here initialize our namespace and resource name.
	public function __construct()
	{
		$this->namespace    = 'photo-library/v1';
		$this->resourceName = 'pictures';
		$this->singleResourceName = 'pictures';
	}

	/**
	 * Register the routes for the objects of the controller.
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
	 * Test request for debugging purposes
	 */
	public static function test_request(): WP_REST_Response
	{
		$message = ['message' => 'PhotoLibrary REST API is working!'];
		return new WP_REST_Response($message, 200);
	}

	/**
	 * Get pictures based on keyword filter
	 */
	public function get_pictures_by_keyword($request): WP_REST_Response
	{
		$keyword = $request->get_param('keyword') ?? '';
		$message = ['message' => 'get_pictures_by_keyword called with keyword: ' . $keyword, 'data' => []];
		return new WP_REST_Response($message, 200);
	}

	/**
	 * Get all keywords
	 */
	public function get_keywords(): WP_REST_Response
	{
		$message = ['message' => 'get_keywords called', 'data' => []];
		return new WP_REST_Response($message, 200);
	}

	/**
	 * Get picture by ID
	 */
	public function get_pictures_by_id($request): WP_REST_Response
	{
		$id = $request->get_param('id');
		$message = ['message' => 'get_pictures_by_id called with ID: ' . $id, 'data' => []];
		return new WP_REST_Response($message, 200);
	}

	/**
	 * Get all pictures
	 */
	public function get_pictures($request): WP_REST_Response
	{
		$message = ['message' => 'get_pictures called', 'data' => []];
		return new WP_REST_Response($message, 200);
	}

	/**
	 * Get random picture
	 */
	public function get_random_picture($request): WP_REST_Response
	{
		$id = $request->get_param('id');
		$message = ['message' => 'get_random_picture called with ID: ' . $id, 'data' => []];
		return new WP_REST_Response($message, 200);
	}
}
