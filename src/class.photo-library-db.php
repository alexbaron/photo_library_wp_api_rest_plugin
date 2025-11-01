<?php

/** @package  */
class PL_REST_DB {

	protected $wpdb;

	/**
	 * Constructeur de la classe PhotoLibrarySchema.
	 *
	 * @param mixed $schema
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	public function getWpDb() {
		return $this->wpdb;
	}

	public static function getRandomPicture( ?int $id = 0 ) {
		global $wpdb;

		$result = [];
		$picture = $wpdb->get_row(
			"
			SELECT
				p.ID as id,
				p.post_title as title,
				p.post_content as description,
				p.guid as img_url,
				metadata.meta_value as metadata,
				palette.meta_value as palette
			FROM {$wpdb->prefix}posts AS p
			LEFT JOIN {$wpdb->prefix}postmeta AS metadata ON p.ID = metadata.post_id AND metadata.meta_key = '_wp_attachment_metadata'
			LEFT JOIN {$wpdb->prefix}postmeta AS palette ON p.ID = palette.post_id AND palette.meta_key = '_pl_palette'
			WHERE post_type = 'attachment'
				AND post_mime_type LIKE 'image%'
				AND p.ID <> 0
			ORDER BY RAND() LIMIT 1
			"
		);

		$photoLibrarySchema = new PhotoLibrarySchema();
		$result['picture']  = $photoLibrarySchema->preparePictureDataAsArray( (object)$picture );
		return $result;
	}

	/**
	 * Get all pictures with their metadata.
	 *
	 * @return array An array of pictures and their metadata.
	 */
	public static function getPictures( $offset = 0 ): array {
		try {
			global $wpdb;

			// SQL query to get pictures and their metadata.
			$req =
			"SELECT
					p.ID as id,
					metadata.post_title as title,
					p.guid as img_url,
					p.post_content as description,
					metadata.meta_value as metadata,
					metadata_palette.meta_value as palette
				FROM
					{$wpdb->prefix}posts AS p
					LEFT JOIN {$wpdb->prefix}postmeta AS pm ON p.ID = pm.post_id
					LEFT JOIN {$wpdb->prefix}postmeta AS pm_palette ON p.ID = pm.post_id AND pm.meta_key = '_pl_palette'
					LEFT JOIN {$wpdb->prefix}lrsync_relations r ON r.wp_id = p.ID
					LEFT JOIN {$wpdb->prefix}lrsync_collections c ON r.wp_col_id = c.wp_col_id
					LEFT JOIN (
							SELECT
									p_temp.ID,
									p_temp.post_title,
									p_temp.guid AS img_url,
									pm_temp.meta_key,
									pm_temp.meta_value
							FROM
									{$wpdb->prefix}posts AS p_temp
									LEFT JOIN {$wpdb->prefix}postmeta AS pm_temp ON p_temp.ID = pm_temp.post_id
							WHERE
									pm_temp.meta_key = '_wp_attachment_metadata'
					) AS metadata ON metadata.ID = p.ID
					LEFT JOIN (
							SELECT
									p_temp.ID,
									pm_temp.meta_value
							FROM
									{$wpdb->prefix}posts AS p_temp
									LEFT JOIN {$wpdb->prefix}postmeta AS pm_temp ON p_temp.ID = pm_temp.post_id
							WHERE
									pm_temp.meta_key = '_pl_palette'
					) AS metadata_palette ON metadata_palette.ID = p.ID
			WHERE
				p.post_type = 'attachment'
				AND p.post_mime_type LIKE 'image%'
			GROUP BY
				p.ID
			ORDER BY
    		metadata.post_title
			LIMIT 5
			OFFSET %d
			";

			// Prepare and execute the SQL query.
			$sql            = $wpdb->prepare( $req, $wpdb->prefix, $offset );
			$result['time'] = \DateTime::createFromFormat( 'U.u', microtime( true ) )->format( 'Y-m-d H:i:s' );
			try {
				$result['sql']      = $sql;
				$result['pictures'] = $wpdb->get_results( $sql );
				$result['total']    = count( $result['pictures'] );
			} catch ( \Exception $e ) {
				$result['sql error'] = $e->getMessage();
			}

			$photoLibrarySchema   = new PhotoLibrarySchema();
			$result['pictures']   = $photoLibrarySchema->prepareAllPicturesDataAsArray( $result['pictures'] );
			$result['nb_results'] = count( $result['pictures'] );
			// Return an empty array if no results are found.
			if ( ! $result ) {
				$result['pictures'] = 'no results';
				return $result;
			}
			return $result;
		} catch ( \Exception $e ) {
			// Return an error message if an exception occurs.
			return array( 'error' => $e->getMessage() );
		}
		return [];
	}

	/**
	 * Get pictures by keywords.
	 *
	 * @param array $request An array of $request parameters
	 *
	 * @return array An array of pictures that match the keywords.
	 */
	public static function getPicturesByKeywords( array $request = [], $offset = 0 ): array {
		try {
			global $wpdb;

			// Initialize the condition string for the SQL query.
			$keywords = implode( '|', isset( $request['search'] ) ? array( $request['search'] ) : [] );

			$req = "SELECT
			TMP.id,
			TMP.img_url,
			TMP.meta_value as metadata
			FROM
			( SELECT
					p.ID as id,
					metadata.post_title as title,
					p.guid as img_url,
					metadata.meta_value,
          wp_attachment_metadata.meta_value as attached_file,
					SUBSTRING(metadata.meta_value,locate('keywords',metadata.meta_value) + LENGTH('keywords'), LENGTH(metadata.meta_value) ) as meta_keywords,
					metadata_palette.meta_value as palette
			FROM
					{$wpdb->prefix}posts AS p
					LEFT JOIN {$wpdb->prefix}postmeta AS pm ON p.ID = pm.post_id
          LEFT JOIN {$wpdb->prefix}postmeta AS wp_attachment_metadata ON p.ID = wp_attachment_metadata.post_id AND wp_attachment_metadata.meta_key = '_wp_attachment_metadata'
					LEFT JOIN (
						SELECT
										p_temp.ID,
										p_temp.post_title,
										p_temp.guid AS img_url,
										pm_temp.meta_key,
										pm_temp.meta_value
						FROM
										{$wpdb->prefix}posts AS p_temp
										LEFT JOIN {$wpdb->prefix}postmeta AS pm_temp ON p_temp.ID = pm_temp.post_id
						WHERE
										pm_temp.meta_key = '_wp_attachment_metadata'
					) AS metadata ON metadata.ID = p.ID
					LEFT JOIN (
						SELECT
								p_temp.ID,
								pm_temp.meta_value
						FROM
								{$wpdb->prefix}posts AS p_temp
								LEFT JOIN {$wpdb->prefix}postmeta AS pm_temp ON p_temp.ID = pm_temp.post_id
						WHERE
								pm_temp.meta_key = '_pl_palette'
					) AS metadata_palette ON metadata_palette.ID = p.ID
					GROUP BY pm.post_id
			) AS TMP
			WHERE
			TMP.meta_keywords REGEXP '$keywords'
			";

			/**
			 * @var  wp-includes/class-wpdb.php $wpdb
			 */
			$sql = $wpdb->prepare( $req, $keywords );
			try {
				$result['pictures'] = $wpdb->get_results( $sql );
			} catch ( \Exception $e ) {
				$result['sql error'] = $e->getMessage();
			}

			$result['debug log'] = 'api_debug.log';
			$result['total']     = count( $result['pictures'] );

			$photoLibrarySchema = new PhotoLibrarySchema();
			$result['pictures'] = $photoLibrarySchema->prepareAllPicturesDataAsArray( $result['pictures'] );

			// Return an empty array if no results are found.
			if ( ! $result ) {
				return [];
			}
			return $result;
		} catch ( \Exception $e ) {
			// Return an error message if an exception occurs.
			return array( 'error' => $e->getMessage() );
		}
	}

	/**
	 * Get pictures by id.
	 *
	 * @param array $request An array of $request parameters
	 *
	 * @return array An array of pictures that match the id.
	 */
	public static function getPicturesById( int $id ): array {
		try {
			global $wpdb;

			// Initialize the condition string for the SQL query.
			if ( ! $id ) {
				throw new \Exception( 'No id provided' );
			}

			$req = "SELECT
			TMP.id,
			TMP.description,
			TMP.title,
			TMP.img_url,
			TMP.meta_value as metadata,
			TMP.palette
			FROM
			( SELECT
					p.ID as id,
					p.post_content as description,
					metadata.post_title as title,
					p.guid as img_url,
					CONCAT (metadata.post_title , '|' , IFNULL(c.name, '') ) AS keywords,
					metadata.meta_value,
					metadata_palette.meta_value as palette
			FROM
					{$wpdb->prefix}posts AS p
					LEFT JOIN {$wpdb->prefix}postmeta AS pm ON p.ID = pm.post_id
					LEFT JOIN {$wpdb->prefix}postmeta AS pm_palette ON p.ID = pm.post_id AND pm.meta_key = '_pl_palette'
					LEFT JOIN {$wpdb->prefix}lrsync_relations r ON r.wp_id = p.ID
					LEFT JOIN {$wpdb->prefix}lrsync_collections c ON r.wp_col_id = c.wp_col_id
					LEFT JOIN (
						SELECT
							p_temp.ID,
							p_temp.post_title,
							p_temp.guid AS img_url,
							pm_temp.meta_key,
							pm_temp.meta_value
						FROM
							{$wpdb->prefix}posts AS p_temp
							LEFT JOIN {$wpdb->prefix}postmeta AS pm_temp ON p_temp.ID = pm_temp.post_id
						WHERE pm_temp.meta_key = '_wp_attachment_metadata'
					) AS metadata ON metadata.ID = p.ID
					LEFT JOIN (
							SELECT
									p_temp.ID,
									pm_temp.meta_value
							FROM
									{$wpdb->prefix}posts AS p_temp
									LEFT JOIN {$wpdb->prefix}postmeta AS pm_temp ON p_temp.ID = pm_temp.post_id
							WHERE
									pm_temp.meta_key = '_pl_palette'
					) AS metadata_palette ON metadata_palette.ID = p.ID
					WHERE   p.id = $id
					GROUP BY pm.post_id
			) AS TMP
			";

			/**
			 * @var  wp-includes/class-wpdb.php $wpdb
			 */
			$sql = $wpdb->prepare( $req, $id );
			try {
				$sqlResult = $wpdb->get_results( $sql );
			} catch ( \Exception $e ) {
				$result['sql error'] = $e->getMessage();
			}

			$result['debug log'] = 'api_debug.log';
			// $result['sql'] = $sql;
			$result['picture'] = $wpdb->get_results( $sql );
			$result['total']   = count( $result['picture'] );

			$photoLibrarySchema = new PhotoLibrarySchema( new PL_REST_DB( $wpdb ) );
			$result['picture']  = $photoLibrarySchema->preparePictureDataAsArray( $result['picture'][0] );

			// Return an empty array if no results are found.
			if ( ! $result ) {
				return [];
			}
			return $result;
		} catch ( \Exception $e ) {
			// Return an error message if an exception occurs.
			return array( 'error' => $e->getMessage() );
		}
	}

	/**
	 * Summary of getKeywords
	 *
	 * @return array
	 */
	public static function getKeywords(): array {
		$keywords = [];
		try {
			global $wpdb;

			$req =
			"SELECT DISTINCT(CONCAT(UPPER(LEFT(value,1)), SUBSTR(value, 2))) as value
			FROM {$wpdb->prefix}lrsync_meta
			WHERE name = 'tag_name'
			ORDER BY value
			;";

			// Prepare and execute the SQL query.
			$sql            = $wpdb->prepare( $req, $wpdb->prefix );
			$result['time'] = \DateTime::createFromFormat( 'U.u', microtime( true ) )->format( 'Y-m-d H:i:s' );
			try {
				$result['meta_data'] = $wpdb->get_results( $sql );
			} catch ( \Exception $e ) {
				$result['sql error'] = $e->getMessage();
			}
			foreach ( $result['meta_data'] as $metadata ) {
				$keywords[] = $metadata->value;
			}
			// If no results are found, return an array with no results as info.
			if ( ! $result ) {
				return array( 'no results' );
			}

			// usort($keywords, 'strcasecmp');
			return $keywords;
		} catch ( \Exception $e ) {
			// Return an error message if an exception occurs.
			return array( 'error' => $e->getMessage() );
		}
	}

	/**
	 * Test if data about palette already exists in the database.
	 *
	 * @return bool
	 */
	public function ifPostMetaExists( int $pictureId, string $meta_key, string $meta_value ): bool {
		$sql = $this->getWpDb()->prepare(
			"SELECT * FROM {$this->getWpDb()->prefix}postmeta WHERE post_id = %d AND meta_key = %s AND meta_value = %s",
			$pictureId,
			$meta_key,
			$meta_value
		);

		$result = $this->getWpDb()->get_results( $sql );

		if ( count( $result ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Save the palette in the database.
	 *
	 * @param int   $pictureId
	 * @param array $palette
	 * @return null
	 */
	public function savePaletteMeta( int $pictureId, array $palette ): array|null {

		if ( 3 < count( $palette ) ) {
			$palette = array_slice( $palette, 0, 3 );
		}

		$meta_key   = '_pl_palette';
		$meta_value = serialize( $palette );

		if ( $this->ifPostMetaExists( $pictureId, $meta_key, $meta_value ) ) {
			return null;
		}

		if ( $this->getWpDb() ) {
			$this->getWpDb()->insert(
				$this->getWpDb()->prefix . 'postmeta',
				array(
					'post_id'    => $pictureId,
					'meta_key'   => $meta_key,
					'meta_value' => $meta_value,
				)
			);
		}
		return $palette;
	}

	public function getPalette( $pictureId ) {
		$sql = $this->getWpDb()->prepare(
			"SELECT meta_value FROM {$this->getWpDb()->prefix}postmeta WHERE post_id = %d AND meta_key = %s",
			$pictureId,
			'_pl_palette'
		);

		$result = $this->getWpDb()->get_results( $sql );

		if ( $result ) {
			return unserialize( $result[0]->meta_value );
		} else {
			return null;
		}
	}

	/**
	 * Retrieves all media associated with a list of keywords via WP/LR Sync
	 *
	 * @param array $keywords List of keyword names to search for
	 * @return array Array containing media IDs and their paths
	 */
	public static function getMediaByKeywords( array $keywords = [] ): array {
		if ( empty( $keywords ) ) {
			return array( 'error' => 'No keywords provided' );
		}

		try {
			global $wplr, $wpdb;

			// Check if WP/LR Sync is available
			if ( ! isset( $wplr ) || ! ( $wplr instanceof Meow_WPLR_Sync_Core ) ) {
				error_log( 'PhotoLibrary: WP/LR Sync Core not available for keyword search' );
				return array( 'error' => 'WP/LR Sync not available' );
			}

			$result = array(
				'keywords_searched' => $keywords,
				'pictures'          => [],
				'total_found'       => 0,
				'keywords_found'    => [],
			);

			// Step 1: Retrieve tag IDs corresponding to keywords
			$tag_ids  = [];
			$tbl_meta = $wpdb->prefix . 'lrsync_meta';

			// Prepare WHERE condition to search for keywords
			$keyword_placeholders = implode( ',', array_fill( 0, count( $keywords ), '%s' ) );
			$sql                  =
      "SELECT
        id,
        value as name
					FROM $tbl_meta
					WHERE name = 'tag_name'
					AND value IN ($keyword_placeholders)";

			$found_tags = $wpdb->get_results( $wpdb->prepare( $sql, ...$keywords ), ARRAY_A );

			foreach ( $found_tags as $tag ) {
				$tag_ids[]                  = $tag['id'];
				$result['keywords_found'][] = $tag['name'];
			}

			if ( empty( $tag_ids ) ) {
				$result['message'] = 'No matching keywords found in WP/LR Sync';
				return $result;
			}

			// Step 2: Retrieve all media associated with these tags
			$media_ids = [];
			foreach ( $tag_ids as $tag_id ) {
				$media_for_tag = $wplr->get_media_from_tag( $tag_id );
				$media_ids     = array_merge( $media_ids, $media_for_tag );
			}

			// Remove duplicates
			$media_ids = array_unique( $media_ids );

			if ( empty( $media_ids ) ) {
				$result['message'] = 'No media found for the specified keywords';
				return $result;
			}

			// Step 3: Retrieve detailed media information
			$media_placeholders = implode( ',', array_fill( 0, count( $media_ids ), '%d' ) );
			$media_sql          =
            "SELECT
							p.ID as id,
							p.post_title as title,
							pm.meta_value as attached_file,
              p.post_content as description,
              p.guid as img_url,
              metadata.meta_value as metadata,
              palette.meta_value as palette
						FROM {$wpdb->prefix}posts p
						LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
							AND pm.meta_key = '_wp_attached_file'
            LEFT JOIN {$wpdb->prefix}postmeta AS metadata ON p.ID = metadata.post_id AND metadata.meta_key = '_wp_attachment_metadata'
			      LEFT JOIN {$wpdb->prefix}postmeta AS palette ON p.ID = palette.post_id AND palette.meta_key = '_pl_palette'
						WHERE p.ID IN ($media_placeholders)
						AND p.post_type = 'attachment'
						AND p.post_mime_type LIKE 'image%'
						ORDER BY p.post_title";

			$media_data = $wpdb->get_results( $wpdb->prepare( $media_sql, ...$media_ids ), ARRAY_A );

			foreach ( $media_data as &$media ) {
        $photoLibrarySchema = new PhotoLibrarySchema();
		    $result['pictures'][]  = $photoLibrarySchema->preparePictureDataAsArray(  (object)$media );
			}

			// $result['media']       = $media_data;
			$result['total_found'] = count( $media_data );
			$result['message']     = "Found {$result['total_found']} media(s) for keywords: " . implode( ', ', $result['keywords_found'] );

			return $result;

		} catch ( \Exception $e ) {
			error_log( 'PhotoLibrary: Error in getMediaByKeywords: ' . $e->getMessage() );
			return array( 'error' => $e->getMessage() );
		}
	}

	/**
	 * Simplified version that returns only IDs and paths
	 *
	 * @param array $keywords Liste des noms de keywords à rechercher
	 * @return array Array simple avec id et path de chaque média
	 */
	public static function getMediaIdsByKeywords( array $keywords = [] ): array {
		$full_result = self::getMediaByKeywords( $keywords );

		if ( isset( $full_result['error'] ) ) {
			return $full_result;
		}

		$simple_result = [];
		foreach ( $full_result['media'] as $media ) {
			$simple_result[] = array(
				'id'   => $media['id'],
				'path' => $media['path'],
			);
		}

		return $simple_result;
	}
}
