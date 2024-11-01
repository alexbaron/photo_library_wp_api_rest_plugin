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
		$photo_library_route = new PhotoLibrary_Route();
		$photo_library_route->register_routes();
	}
}
