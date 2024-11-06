<?php

/** @package  */
class PL_REST_DB
{
	/**
	 * Get all pictures with their metadata.
	 *
	 * @return array An array of pictures and their metadata.
	 */
	public static function getPictures(): array
	{
		try {
			global $wpdb;

			// SQL query to get pictures and their metadata.
			$req =
			"SELECT
					p.ID as id,
					metadata.post_title as title,
					p.guid as img_url,
					CONCAT (metadata.post_title , '|' , IFNULL(c.name, '') ) AS keywords,
					metadata.meta_value
			FROM
					{$wpdb->prefix}postmeta AS pm
					LEFT JOIN {$wpdb->prefix}posts AS p ON pm.meta_value = p.ID
					LEFT JOIN {$wpdb->prefix}lrsync_relations r ON r.{$wpdb->prefix}id = p.ID
					LEFT JOIN {$wpdb->prefix}lrsync_collections c ON r.{$wpdb->prefix}col_id = c.{$wpdb->prefix}col_id
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
									pm_temp.meta_key = '_{$wpdb->prefix}attachment_metadata'
					) AS metadata ON metadata.ID = p.ID
			WHERE
					pm.meta_key = '_thumbnail_id'
					AND p.guid IS NOT NULL
			GROUP BY p.ID
			";

			// Prepare and execute the SQL query.
			$sql = $wpdb->prepare($req, $wpdb->prefix);

			$result['query'] = $sql;
			$result['p_table'] = $wpdb->prefix;
			$result['pictures'] = $wpdb->get_results($sql);

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
	 * Get pictures by keywords.
	 *
	 * @param array $request An array of $request parameters
	 *
	 * @return array An array of pictures that match the keywords.
	 */
	public static function getPicturesByKeywords(array $request = []): array
	{
		try {
			global $wpdb;

			// Initialize the condition string for the SQL query.
			$conditions = [];

			$keywords = isset($request['search']) ? $request['search'] : [];

			// Add a condition for each keyword.
			foreach ($keywords as $word) {
				$conditions[] = " TMP.keywords LIKE '%" . $word . "%' OR TMP.meta_keywords LIKE '%" . $word . "%'";
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
						{$wpdb->prefix}postmeta AS pm
						INNER JOIN {$wpdb->prefix}posts AS p ON pm.meta_value = p.ID
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
										pm_temp.meta_key = '_{$wpdb->prefix}attachment_metadata'
						) AS metadata ON metadata.ID = p.ID
						WHERE  pm.meta_key = '_thumbnail_id'
						AND p.guid IS NOT NULL
						GROUP BY p.ID
				) AS TMP ";


			$conds = '';
			if ($conditions) {
				// SQL query to get pictures that match the keywords.
				$conds = " " . implode(" OR ", $conditions) . ";";
				$req .= "WHERE $conds ";
			}

			// Prepare and execute the SQL query.
			$sql = $wpdb->prepare($req, $wpdb->prefix);
			$result['pictures'] = $wpdb->get_results($sql);

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
	 * Get keywords (placeholder function).
	 *
	 * @param array $keywords An array of keywords.
	 * @return array An array of keywords.
	 */
	public static function getkeyWords(array $keywords = []): array
	{
		// Placeholder function to get keywords.
		return [];
	}
}
