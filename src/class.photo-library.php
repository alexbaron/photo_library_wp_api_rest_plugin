<?php

/**
 * Class PhotoLibrary
 *
 * This class is responsible for initializing the plugin and registering REST API routes.
 */
class PhotoLibrary
{
	/**
	 * Initialize the plugin.
	 *
	 * This method is intended to handle the plugin configuration with forms UI.
	 * Currently, it is a placeholder for future implementation.
	 *
	 * @return void
	 */
	public static function init(): void
	{
		// @todo plugin configuration with forms UI
		error_log("PhotoLibrary init");
	}

	/**
	 * Sends Cross-Origin Resource Sharing headers with API requests.
	 *
	 * @since 4.4.0
	 *
	 * @param mixed $value Response data.
	 * @return mixed Response data.
	 */
	public static function rest_send_cors_headers($value)
	{
		$origin = get_http_origin();
		$allowed_origins = ['phototheque.stephanewagner.com', 'localhost:3000', '*'];

		if ($origin && in_array($origin, $allowed_origins)) {
			header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
			header('Access-Control-Allow-Methods: *');
			header('Access-Control-Allow-Credentials: true');
		}

		return $value;
	}

	/**
	 * Register REST API routes.
	 *
	 * This method creates an instance of the PhotoLibrary_Route class
	 * and calls its register_routes method to register the REST API routes.
	 *
	 * @return void
	 */
	public static function register_rest_routes(): void
	{
		error_log("register_rest_routes init");
		$photo_library_route = new PhotoLibrary_Route();
		$photo_library_route->register_routes();
	}
}
