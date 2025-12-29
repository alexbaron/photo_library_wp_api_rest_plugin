<?php

/**
 * Class PhotoLibrary
 *
 * This class is responsible for initializing the plugin and registering REST API routes.
 */
class PhotoLibrary {

	/**
	 * Initialize the plugin.
	 *
	 * This method is intended to handle the plugin configuration with forms UI.
	 * Currently, it is a placeholder for future implementation.
	 *
	 * @return void
	 */
	public static function init(): void {
		// @todo plugin configuration with forms UI
	}

	/**
	 * Sends Cross-Origin Resource Sharing headers with API requests.
	 *
	 * @since 4.4.0
	 *
	 * @param mixed $value Response data.
	 * @return mixed Response data.
	 */
	public static function rest_send_cors_headers( $value ) {
		// Allow all origins - disable CORS security
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH' );
		header( 'Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-WP-Nonce' );
		header( 'Access-Control-Allow-Credentials: true' );
		header( 'Access-Control-Max-Age: 86400' ); // Cache preflight for 24 hours

		// Handle preflight OPTIONS requests
		if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
			status_header( 200 );
			exit();
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
	public static function register_rest_routes(): void {
		$photo_library_route = new PhotoLibrary_Route();
		$photo_library_route->register_routes();
		error_log( 'PhotoLibrary::register_rest_routes completed' );
	}
}
