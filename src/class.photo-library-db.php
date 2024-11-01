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
			$req = "SELECT
                pm.meta_id as pm_id,
                p.guid as img_url,
                concat(p.post_title,'|', c.name) as metadata
                FROM {$wpdb->prefix}postmeta AS pm
                LEFT JOIN {$wpdb->prefix}posts AS p ON pm.meta_value = p.ID
                LEFT JOIN {$wpdb->prefix}lrsync_relations r ON r.wp_id = p.ID
                LEFT JOIN {$wpdb->prefix}lrsync_collections c ON r.wp_col_id = c.wp_col_id
                WHERE pm.meta_key = '_thumbnail_id'
                AND p.guid IS NOT NULL
            ;";

			// Prepare and execute the SQL query.
			$sql = $wpdb->prepare($req, $wpdb->prefix);
			$result = $wpdb->get_results($sql);

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

			$keywords = isset($request['search']) ?? [];

			// Add a condition for each keyword.
			foreach ($keywords as $word) {
				$conditions[] = " TMP.tag_words LIKE '%" . $word . "%' ";
			}

			$req = "SELECT TMP.* FROM
				( SELECT p.guid,
					concat(p.post_title,' ', c.name) as tag_words
					FROM {$wpdb->prefix}postmeta AS pm
					INNER JOIN {$wpdb->prefix}posts AS p ON pm.meta_value = p.ID
					LEFT JOIN {$wpdb->prefix}lrsync_relations r ON r.wp_id = p.ID
					LEFT JOIN {$wpdb->prefix}lrsync_collections c ON r.wp_col_id = c.wp_col_id
					WHERE  pm.meta_key = '_thumbnail_id'
				) AS TMP";

			$conds = '';
			if ($conditions) {
				// SQL query to get pictures that match the keywords.
				$conds = " " . implode(" OR ", $conditions) . ";";
				$req .= "WHERE $conds ";
			}

			// Prepare and execute the SQL query.
			$sql = $wpdb->prepare($req, $wpdb->prefix);
			$result = $wpdb->get_results($sql);

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
