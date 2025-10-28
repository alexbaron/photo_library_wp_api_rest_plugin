<?php

/**
 * PhotoLibrary Data Handler
 *
 * This file contains the PL_DATA_HANDLER class for handling data operations
 * and transformations in the PhotoLibrary plugin.
 *
 * @package PhotoLibrary
 * @since 0.2.0
 */

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
    public const ATTACHMENT = 'attachment';

    /**
     * Convert hierarchical keyword structure to flat array
     *
     * Recursively traverses a hierarchical keyword structure (from WP/LR Sync)
     * and converts it to a flat associative array where keys are keyword IDs
     * and values are keyword names. Results are sorted alphabetically.
     *
     * @since 0.2.0
     *
     * @param array $keywords_hierarchy Hierarchical array of keywords from WP/LR Sync.
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
        $flat_keywords = array();

        $traverse = function ($nodes) use (&$traverse, &$flat_keywords) {
            foreach ($nodes as $node) {
                $flat_keywords[ $node['id'] ] = $node['name'];
                if (! empty($node['children'])) {
                    $traverse($node['children']);
                }
            }
        };

        $traverse($keywords_hierarchy);
        rsort($flat_keywords);

        return $flat_keywords;
    }

    /**
     * Get data from media attachment
     *
     * @param string $media_id The media ID.
     * @return array Media data or error array.
     */
    public static function get_data_from_media(string $media_id): array
    {
        $data = [];

        $post = get_post($media_id);
        if (! $post || self::ATTACHMENT !== $post->post_type) {
            $message = sprintf('Picture with ID %d not found', $media_id);
            return array(
                'error' => $message,
                'code'  => 404,
            );
        }

        // Get file path - use configured path or WordPress default.
        $file_path = get_attached_file($media_id);
        if (! file_exists($file_path)) {
            return [
                'error' => 'File not found: ' . $file_path,
                'code'  => 404,
            ];
        }

        $data['id']          = $post->ID;
        $data['name']        = $post->post_name;
        $data['description'] = $post->post_description;
        $data['file_path']   = $file_path;

        // Get IPTC keywords.
        $image_info       = getimagesize($file_path, $info);
        $data['keywords'] = self::get_keywords_from_iptc($image_info, $info);

        return $data;
    }

    /**
     * Get keywords from IPTC data
     *
     * @param array|false $image_info Image info from getimagesize.
     * @param array       $info Additional info from getimagesize.
     * @return array Keywords extracted from IPTC data.
     */
    public static function get_keywords_from_iptc($image_info, array $info): array
    {
        $keywords = array();

        if (! $image_info || ! isset($info['APP13'])) {
            return $keywords;
        }

        $iptc = iptcparse($info['APP13']);

        if (! $iptc) {
            return $keywords;
        }

        // Keywords are in 2#025.
        if (isset($iptc['2#025'])) {
            foreach ($iptc['2#025'] as $keyword) {
                // Clean and convert encoding.
                $keyword = trim($keyword);

                // Detect and convert encoding.
                if (false === mb_detect_encoding($keyword, 'UTF-8', true)) {
                    // Not UTF-8, try ISO-8859-1.
                    $keyword = mb_convert_encoding($keyword, 'UTF-8', 'ISO-8859-1');
                }

                if (! empty($keyword)) {
                    $keywords[] = $keyword;
                }
            }
        }

        return $keywords;
    }
}
