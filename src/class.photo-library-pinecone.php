<?php

/**
 * Pinecone Color Search Integration for PhotoLibrary
 * Search photos by RGB color similarity using Pinecone vector database
 *
 * @package PhotoLibrary
 */

class PL_Color_Search_Index
{
    private $api_key;
    private $index_host;
    private $index_name = 'phototheque-color-search';

    public function __construct()
    {
        // Charger les variables d'environnement
        if (!class_exists('PL_Env_Manager')) {
            require_once __DIR__ . '/class.photo-library-env-manager.php';
        }

        PL_Env_Manager::load();

        // Récupérer la clé API avec debug
        $this->api_key = PL_Env_Manager::get('PINECONE_API_KEY');

        if (!$this->api_key) {
            $debug_info = PL_Env_Manager::get_debug_info();
            $error_msg = 'PINECONE_API_KEY not found. Debug info: ' . json_encode($debug_info);
            error_log($error_msg);
            throw new Exception($error_msg);
        }

        // Get index host from Pinecone
        $this->index_host = $this->get_index_host();
    }

    /**
     * Get index host URL from Pinecone API
     */
    private function get_index_host()
    {
        $response = wp_remote_get(
            "https://api.pinecone.io/indexes/{$this->index_name}",
            array(
                'headers' => array(
                    'Api-Key' => $this->api_key,
                    'Content-Type' => 'application/json'
                )
            )
        );

        if (is_wp_error($response)) {
            throw new Exception('Failed to get index info: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['host'] ?? null;
    }

    /**
     * Store photo's dominant RGB color in Pinecone
     *
     * @param string $photo_id Unique photo identifier (e.g., "photo_123")
     * @param array $rgb_values [R, G, B] in 0-255 range
     * @return bool Success status
     */
    public function upsert_photo_color($photo_id, $rgb_values)
    {
        if (count($rgb_values) !== 3) {
            throw new Exception('RGB values must be an array of 3 numbers [R, G, B]');
        }

        // Normalize RGB to 0-1 range for better distance calculation
        $normalized_rgb = array_map(function ($v) {
            return $v / 255.0;
        }, $rgb_values);

        $vectors = array(
            array(
                'id' => (string) $photo_id,
                'values' => $normalized_rgb
            )
        );

        return $this->upsert_vectors($vectors, 'photos');
    }

    /**
     * Batch insert multiple photos
     *
     * @param array $photos Array of arrays with keys: id, rgb
     *                      Example: [["id" => "1", "rgb" => [120, 80, 200]]]
     * @return bool Success status
     */
    public function batch_upsert_photos($photos)
    {
        $vectors = array();

        foreach ($photos as $photo) {
            $normalized_rgb = array_map(function ($v) {
                return $v / 255.0;
            }, $photo['rgb']);

            $vectors[] = array(
                'id' => (string) $photo['id'],
                'values' => $normalized_rgb
            );
        }

        // Pinecone limit: 1000 vectors per batch
        $batch_size = 1000;
        $batches = array_chunk($vectors, $batch_size);

        foreach ($batches as $batch) {
            $success = $this->upsert_vectors($batch, 'photos');
            if (!$success) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find photos with similar colors
     *
     * @param array $rgb_values Target color [R, G, B] in 0-255 range
     * @param int $top_k Number of results to return
     * @return array List of matches with photo IDs and scores
     */
    public function search_by_color($rgb_values, $top_k = 10)
    {
        $normalized_rgb = array_map(function ($v) {
            return $v / 255.0;
        }, $rgb_values);

        $query_data = array(
            'vector' => $normalized_rgb,
            'topK' => $top_k,
            'namespace' => 'photos',
            'includeMetadata' => false,
            'includeValues' => true
        );

        $response = wp_remote_post(
            "https://{$this->index_host}/query",
            array(
                'headers' => array(
                    'Api-Key' => $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($query_data),
                'timeout' => 30
            )
        );

        if (is_wp_error($response)) {
            throw new Exception('Search failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $matches = $body['matches'] ?? array();

        return array_map(function ($match) {
            return array(
                'photo_id' => $match['id'],
                'score' => $match['score'],
                'rgb_stored' => isset($match['values'])
                    ? array_map(function ($v) { return (int)($v * 255); }, $match['values'])
                    : null
            );
        }, $matches);
    }

    /**
     * Get statistics about the index
     *
     * @return array Index statistics
     */
    public function get_index_stats()
    {
        $response = wp_remote_post(
            "https://{$this->index_host}/describe_index_stats",
            array(
                'headers' => array(
                    'Api-Key' => $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(new stdClass()),
                'timeout' => 30
            )
        );

        if (is_wp_error($response)) {
            throw new Exception('Failed to get stats: ' . $response->get_error_message());
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Internal method to upsert vectors to Pinecone
     *
     * @param array $vectors Array of vector objects
     * @param string $namespace Namespace name
     * @return bool Success status
     */
    private function upsert_vectors($vectors, $namespace)
    {
        $response = wp_remote_post(
            "https://{$this->index_host}/vectors/upsert",
            array(
                'headers' => array(
                    'Api-Key' => $this->api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array(
                    'vectors' => $vectors,
                    'namespace' => $namespace
                )),
                'timeout' => 30
            )
        );

        if (is_wp_error($response)) {
            error_log('Pinecone upsert error: ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        return $status_code === 200;
    }
}

// Example usage (WordPress context)
if (defined('WP_CLI') && WP_CLI) {
    /**
     * Example CLI command to test color search
     * Usage: wp eval-file pinecone_color_search.php
     */

    try {
        $color_search = new PL_Color_Search_Index();

        // Example: Upsert photos with their dominant colors
        $example_photos = array(
            array(
                'id' => 'photo_1',
                'rgb' => array(120, 150, 200) // Light blue
            ),
            array(
                'id' => 'photo_2',
                'rgb' => array(220, 100, 80) // Warm red/orange
            )
        );

        $color_search->batch_upsert_photos($example_photos);
        WP_CLI::success('Photos uploaded to Pinecone');

        // Search for similar colors
        $target_color = array(130, 160, 210); // Similar to light blue
        $results = $color_search->search_by_color($target_color, 5);

        WP_CLI::line("\nSearching for colors similar to RGB(" . implode(', ', $target_color) . "):");
        foreach ($results as $result) {
            WP_CLI::line("  Photo: {$result['photo_id']}");
            WP_CLI::line("  Score: " . round($result['score'], 4));
            WP_CLI::line("  Stored RGB: [" . implode(', ', $result['rgb_stored']) . "]");
            WP_CLI::line('');
        }

        // Stats
        $stats = $color_search->get_index_stats();
        WP_CLI::line("Index stats: {$stats['totalVectorCount']} photos indexed");

    } catch (Exception $e) {
        WP_CLI::error($e->getMessage());
    }
}
