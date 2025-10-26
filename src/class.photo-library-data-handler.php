<?php

/**
 * PhotoLibrary Data Handler
 *
 * This class handles data operations and transformations for the PhotoLibrary plugin.
 * It provides methods for array processing, data transformation, and formatting
 * of data retrieved from the database or external APIs.
 *
 * @package PhotoLibrary
 * @version 0.2.0
 * @author Alex Baron
 *
 * @since 0.2.0
 *
 * Features:
 * - Array operations and transformations
 * - Data structure conversions
 * - Keyword hierarchy processing
 * - Data formatting and normalization
 *
 * Dependencies:
 * - WordPress core functions
 * - PHP array functions
 */
class PL_DATA_HANDLER
{
    /**
     * Convert hierarchical keyword structure to flat array
     *
     * Recursively traverses a hierarchical keyword structure (from WP/LR Sync)
     * and converts it to a flat associative array where keys are keyword IDs
     * and values are keyword names. Results are sorted alphabetically.
     *
     * @since 0.2.0
     *
     * @param array $keywords_hierarchy Hierarchical array of keywords from WP/LR Sync
     *
     * @return array Flat associative array [id => name] sorted alphabetically
     *
     * Input Structure:
     * [
     *   [
     *     'id' => 123,
     *     'name' => 'Travel',
     *     'children' => [
     *       ['id' => 124, 'name' => 'Europe', 'children' => []],
     *       ['id' => 125, 'name' => 'Asia', 'children' => []]
     *     ]
     *   ]
     * ]
     *
     * Output Structure:
     * [
     *   '125' => 'Asia',
     *   '124' => 'Europe',
     *   '123' => 'Travel'
     * ]
     */
    public static function filter_keywords_to_flat_array($keywords_hierarchy)
    {
        $flat_keywords = [];

        $traverse = function ($nodes) use (&$traverse, &$flat_keywords) {
            foreach ($nodes as $node) {
                $flat_keywords[$node['id']] = $node['name'];
                if (!empty($node['children'])) {
                    $traverse($node['children']);
                }
            }
        };

        $traverse($keywords_hierarchy);
        rsort($flat_keywords);

        return $flat_keywords;
    }
}
