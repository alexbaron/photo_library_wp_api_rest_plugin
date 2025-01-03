<?php

/** @package  */
class PL_REST_DB
{
	private static $table_name;

	/**
	 * Get all pictures with their metadata.
	 *
	 * @return array An array of pictures and their metadata.
	 */
	public static function getPictures($offset = 0): array
	{
		try {
			global $wpdb;

			// SQL query to get pictures and their metadata.
			$req =
			"SELECT
					p.ID as id,
					metadata.post_title as title,
					p.guid as img_url,
					metadata.meta_value
				FROM
					{$wpdb->prefix}posts AS p
					LEFT JOIN {$wpdb->prefix}postmeta AS pm ON p.ID = pm.post_id
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
			WHERE
				p.post_type = 'attachment'
				AND p.post_mime_type LIKE 'image%'
			GROUP BY
				p.ID
			ORDER BY
    		metadata.post_title
			LIMIT 20
			OFFSET %d
			";

			// Prepare and execute the SQL query.
			$sql = $wpdb->prepare($req, $wpdb->prefix, $offset);
			$result['time'] = \DateTime::createFromFormat('U.u', microtime(true))->format('Y-m-d H:i:s');
			try {
				// $result['sql'] = $sql;
				$result['pictures'] = $wpdb->get_results($sql);
				$result['total'] = count($result['pictures']);
			} catch (\Exception $e) {
				$result['sql error'] = $e->getMessage();
			}

			$photoLibrarySchema = new PhotoLibrarySchema();
			$result['pictures'] = $photoLibrarySchema->prepareAllPicturesDataAsArray($result['pictures']);
			$result['nb_results']	= count($result['pictures']);
			// Return an empty array if no results are found.
			if (!$result) {
				$result['pictures'] = 'no results';
				return $result;
			}
			return $result;
		} catch (\Exception $e) {
			// Return an error message if an exception occurs.
			return ['error' => $e->getMessage()];
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
	public static function getPicturesByKeywords(array $request = [], $offset = 0): array
	{
		try {
			global $wpdb;

			// Initialize the condition string for the SQL query.
			$keywords = implode('|', isset($request['search']) ? [$request['search']] : []);

			$req = "SELECT
			TMP.id,
			TMP.title,
			TMP.img_url,
			TMP.keywords,
			TMP.meta_value
			FROM
			( SELECT
							p.ID as id,
							metadata.post_title as title,
							p.guid as img_url,
							CONCAT (metadata.post_title , '|' , IFNULL(c.name, '') ) AS keywords,
							metadata.meta_value,
							SUBSTRING(metadata.meta_value,locate('keywords',metadata.meta_value) + LENGTH('keywords'), LENGTH(metadata.meta_value) ) as meta_keywords
			FROM
					{$wpdb->prefix}posts AS p
					LEFT JOIN {$wpdb->prefix}postmeta AS pm ON p.ID = pm.post_id
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
					GROUP BY pm.post_id
			) AS TMP
			WHERE   TMP.keywords REGEXP '$keywords'
			OR TMP.meta_keywords REGEXP '$keywords'
			";

			/**
			 * @var  wp-includes/class-wpdb.php $wpdb
			 */
			$sql = $wpdb->prepare($req, $keywords);
			try {
				$sqlResult = $wpdb->get_results($sql);
			} catch (\Exception $e) {
				$result['sql error'] = $e->getMessage();
			}

			$result['debug log'] = 'api_debug.log';
			$result['pictures'] = $wpdb->get_results($sql);
			$result['total'] = count($result['pictures']);

			$photoLibrarySchema = new PhotoLibrarySchema();
			$result['pictures'] = $photoLibrarySchema->prepareAllPicturesDataAsArray($result['pictures']);

			// Return an empty array if no results are found.
			if (!$result) {
				return [];
			}
			return $result;
		} catch (\Exception $e) {
			// Return an error message if an exception occurs.
			return ['error' => $e->getMessage()];
		}
		return [];
	}

	/**
	 * Get pictures by id.
	 *
	 * @param array $request An array of $request parameters
	 *
	 * @return array An array of pictures that match the id.
	 */
	public static function getPicturesById(int $id): array
	{
		try {
			global $wpdb;

			// Initialize the condition string for the SQL query.
			if (!$id) {
				throw new \Exception('No id provided');
			}

			$req = "SELECT
			TMP.id,
			TMP.title,
			TMP.img_url,
			TMP.keywords,
			TMP.meta_value
			FROM
			( SELECT
							p.ID as id,
							metadata.post_title as title,
							p.guid as img_url,
							CONCAT (metadata.post_title , '|' , IFNULL(c.name, '') ) AS keywords,
							metadata.meta_value,
							SUBSTRING(metadata.meta_value,locate('keywords',metadata.meta_value) + LENGTH('keywords'), LENGTH(metadata.meta_value) ) as meta_keywords
			FROM
					{$wpdb->prefix}posts AS p
					LEFT JOIN {$wpdb->prefix}postmeta AS pm ON p.ID = pm.post_id
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
					WHERE   p.id = $id
					GROUP BY pm.post_id
			) AS TMP
			";

			/**
			 * @var  wp-includes/class-wpdb.php $wpdb
			 */
			$sql = $wpdb->prepare($req, $id);
			try {
				$sqlResult = $wpdb->get_results($sql);
			} catch (\Exception $e) {
				$result['sql error'] = $e->getMessage();
			}

			$result['debug log'] = 'api_debug.log';
			$result['sql'] = $sql;
			$result['picture'] = $wpdb->get_results($sql);
			$result['total'] = count($result['picture']);

			$photoLibrarySchema = new PhotoLibrarySchema();
			$result['picture'] = $photoLibrarySchema->preparePictureDataAsArray($result['picture'][0]);

			// Return an empty array if no results are found.
			if (!$result) {
				return [];
			}
			return $result;
		} catch (\Exception $e) {
			// Return an error message if an exception occurs.
			return ['error' => $e->getMessage()];
		}
		return [];
	}

	/**
	 * Summary of getKeywords
	 * @return array
	 */
	public static function getKeywords(): array
	{
		$keywords = [];
		try {
			global $wpdb;

			// SQL query to get pictures and their metadata.
			$req =
				"SELECT
					metadata.meta_value
			FROM
					{$wpdb->prefix}postmeta AS pm
					LEFT JOIN {$wpdb->prefix}posts AS p ON pm.meta_value = p.ID
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
			WHERE
					pm.meta_key = '_thumbnail_id'
					AND p.guid IS NOT NULL
			GROUP BY p.ID
			";

			// Prepare and execute the SQL query.
			$sql = $wpdb->prepare($req, $wpdb->prefix);
			$result['time'] = \DateTime::createFromFormat('U.u', microtime(true))->format('Y-m-d H:i:s');
			try {
				$result['meta_data'] = $wpdb->get_results($sql);
			} catch (\Exception $e) {
				$result['sql error'] = $e->getMessage();
			}
			foreach ($result['meta_data'] as $metadata) {
				$data = unserialize($metadata->meta_value);
				if (isset($data['image_meta']['keywords']) && !empty($data['image_meta']['keywords'])) {
					foreach ($data['image_meta']['keywords'] as $keyword) {
						if (!in_array($keyword, $keywords)) {
							$keywords[] = $keyword;
						}
					}
				}
			}
			// Return an empty array if no results are found.
			if (!$result) {
				$keywords = ['no results'];
				return $result;
			}

			sort($keywords);
			return $keywords;
		} catch (\Exception $e) {
			// Return an error message if an exception occurs.
			return ['error' => $e->getMessage()];
		}
		return [];
	}

	public static function updateKeywords($keywords = []): array
	{
		$test = 1;

		// $oldKeywords = self::getKeywords();

		// try {
		// 	global $wpdb;
		// 	$tableName = $wpdb->prefix . 'pl_keyword';

		// 	$keywords = isset($keywords) ? $keywords : [];
		// 	$data = [
		// 		'keyword' => 'test'
		// 	];
		// 	$wpdb->insert($this, $data);
		// } catch (\Exception $e) {
		// 	return ['error' => $e->getMessage()];
		// }
	}
}
