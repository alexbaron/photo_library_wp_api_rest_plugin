<?php

class PhotoLibrary_Route extends WP_REST_Controller
{
	protected $namespace;
	protected $resourceName;

	// Here initialize our namespace and resource name.
	public function __construct()
	{
		$this->namespace    = '/photo-library/v1';
		$this->resourceName = 'pictures';
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes(): void
	{
		// test purpose
		register_rest_route($this->namespace, '/test', [
			'methods'  => WP_REST_Server::READABLE,
			'callback' => ['PhotoLibrary_Route', 'test_request'],
		]);

		// get all pictures
		$testRoute = $this->namespace . '/' . $this->resourceName . '/all';

		register_rest_route($this->namespace, '/' . $this->resourceName . '/all', [
			// Here we register the readable endpoint for collections.
			[
				'methods'   => WP_REST_Server::READABLE,
				'callback'  => [$this, 'get_pictures'],
				// @todo configure some permission rules
				// 'permission_callback' => [$this, 'get_items_permissions_check'],
			],
			// Register our schema callback.
			// 'schema' => ['PhotoLibrary_Route', 'get_item_schema'],
		]);

		// Get picture by keyword
		register_rest_route($this->namespace, '/' . $this->resourceName . '/by_keywords', [
			// Here we register the readable endpoint for collections.
			[
				'methods'   => WP_REST_Server::CREATABLE,
				'callback'  => [$this, 'get_pictures_by_keyword'],
				// @todo configure some permission rules
				// 'permission_callback' => [$this, 'get_items_permissions_check'],
			],
			// Register our schema callback.
			// 'schema' => ['PhotoLibrary_Route', 'get_item_schema'],
		]);

		//Get all keywords
		register_rest_route($this->namespace, '/' . $this->resourceName . '/keywords', [
			[
				'methods'   => WP_REST_Server::READABLE,
				'callback'  => [$this, 'get_keywords'],
			],
		]);
	}

	/**
	 * Handle the GET request.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response
	 */
	public static function test_request(WP_REST_Request $request)
	{
		$data = [
			'message' => 'Bienvenue sur notre photo library API ',
			'status'  => 'success',
		];

		return rest_ensure_response($data);
	}

	/**
	 * Get all keywords from the database
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_keywords($request)
	{
		$data = [];
		try {
			$data['keywords'] = PL_REST_DB::getKeywords();
		} catch (\Exception $e) {
			$data = ['error' => sprintf('An error occured : %s', $e->getMessage())];
		}
		return new WP_REST_Response($data, 200);
	}

	/**
	 * Get a collection of pictures
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_pictures($request)
	{
		$data = [];
		try {
			$data = PL_REST_DB::getPictures();
		} catch (\Exception $e) {
			$data = ['error' => sprintf('An error occured : %s', $e->getMessage())];
		}
		return new WP_REST_Response($data, 200);
	}

	/**
	 * Get one item from the collection
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_pictures_by_keyword($request)
	{
		//get parameters from request
		$params = $request->get_params();
		$data = []; //do a query, call another class, etc

		try {
			$data = PL_REST_DB::getPicturesByKeywords($params);
		} catch (\Exception $e) {
			$data = ['error' => sprintf('An error occured : %s', $e->getMessage())];
		}
		return new WP_REST_Response($data, 200);
	}

	/**
	 * Get our sample schema for a post.
	 *
	 * @return array The sample schema for a post
	 */
	public function get_item_schema()
	{
		if ($this->schema) {
			// Since WordPress 5.3, the schema can be cached in the $schema property.
			return $this->schema;
		}

		$this->schema = [
			// This tells the spec of JSON Schema we are using which is draft 4.
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			// The title property marks the identity of the resource.
			'title'                => 'picture',
			'type'                 => 'object',
			// In JSON Schema you can specify object properties in the properties attribute.
			'properties'           => [
				'id' => [
					'description'  => esc_html__('Unique identifier for the object.', 'my-textdomain'),
					'type'         => 'integer',
					'context'      => ['view', 'edit', 'embed'],
					'readonly'     => true,
				],
				'url' => [
					'description'  => esc_html__('The content for the object.', 'my-textdomain'),
					'type'         => 'string',
				],
				'categories' => [
					'description'  => esc_html__('The categories for the object.', 'my-textdomain'),
					'type'         => 'array',
				],
			],
		];

		return $this->schema;
	}

	// Sets up the proper HTTP status code for authorization.
	public function authorization_status_code()
	{
		$status = 401;

		if (is_user_logged_in()) {
			$status = 403;
		}

		return $status;
	}
}
