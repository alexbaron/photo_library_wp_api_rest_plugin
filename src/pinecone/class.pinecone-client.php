<?php

/**
 * Pinecone Client for WordPress
 *
 * Cette classe permet de se connecter et d'interagir avec le service Pinecone
 * via leur API REST depuis un plugin WordPress.
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\GuzzleException;

class PineconeClient
{
    /**
     * @var string API key for Pinecone
     */
    private string $api_key;

    /**
     * @var string Pinecone environment/host
     */
    private string $host;

    /**
     * @var Client Client HTTP Guzzle
     */
    private Client $http_client;

    /**
     * Constructor
     *
     * @param string $api_key Pinecone API key
     * @param string $host Pinecone host (e.g., 'your-index-abc123.svc.aped-4627-b74a.pinecone.io')
     */
    public function __construct(string $api_key, string $host)
    {
        $this->api_key = $api_key;
        $this->host = $host;

        // Initialiser le client Guzzle
        $this->http_client = new Client([
            'base_uri' => "https://{$host}",
            'timeout' => 30,
            'headers' => [
                'Api-Key' => $api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
    }

    /**
     * Initialize from WordPress options
     *
     * @return self|null
     */
    public static function fromWordPressOptions(): ?self
    {
        $api_key = get_option('pinecone_api_key');
        $host = get_option('pinecone_host');

        if (empty($api_key) || empty($host)) {
            error_log('Pinecone configuration missing: API key or host not set');
            return null;
        }

        return new self($api_key, $host);
    }

    /**
     * Make HTTP request to Pinecone API
     *
     * @param string $method HTTP method (GET, POST, DELETE, etc.)
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|false Response data or false on error
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): array|false
    {
        try {
            $options = [];

            if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $options['json'] = $data;
            }

            $response = $this->http_client->request($method, $endpoint, $options);
            $body = $response->getBody()->getContents();

            $decoded = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Pinecone API response JSON decode error: " . json_last_error_msg());
                return false;
            }

            return $decoded;

        } catch (RequestException $e) {
            $error_message = $e->getMessage();
            if ($e->hasResponse()) {
                $status_code = $e->getResponse()->getStatusCode();
                $response_body = $e->getResponse()->getBody()->getContents();
                error_log("Pinecone API error {$status_code}: {$response_body}");
            } else {
                error_log("Pinecone API request failed: {$error_message}");
            }
            return false;
        } catch (GuzzleException $e) {
            error_log("Pinecone API request failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get index statistics
     *
     * @return array|false
     */
    public function describeIndexStats(): array|false
    {
        return $this->makeRequest('POST', '/describe_index_stats', []);
    }

    /**
     * Upsert vectors into the index
     *
     * @param array $vectors Array of vector objects
     * @param string $namespace Optional namespace
     * @return array|false
     */
    public function upsert(array $vectors, string $namespace = ''): array|false
    {
        $data = ['vectors' => $vectors];

        if (!empty($namespace)) {
            $data['namespace'] = $namespace;
        }

        return $this->makeRequest('POST', '/vectors/upsert', $data);
    }

    /**
     * Query vectors by similarity
     *
     * @param array $vector Query vector
     * @param int $top_k Number of results to return
     * @param string $namespace Optional namespace
     * @param array $filter Optional metadata filter
     * @param bool $include_values Include vector values in response
     * @param bool $include_metadata Include metadata in response
     * @return array|false
     */
    public function query(
        array $vector,
        int $top_k = 10,
        string $namespace = '',
        array $filter = [],
        bool $include_values = false,
        bool $include_metadata = true
    ): array|false {
        $data = [
            'vector' => $vector,
            'topK' => $top_k,
            'includeValues' => $include_values,
            'includeMetadata' => $include_metadata
        ];

        if (!empty($namespace)) {
            $data['namespace'] = $namespace;
        }

        if (!empty($filter)) {
            $data['filter'] = $filter;
        }

        return $this->makeRequest('POST', '/query', $data);
    }

    /**
     * Search using text query (requires index with integrated embeddings)
     *
     * @param string $text Query text
     * @param int $top_k Number of results to return
     * @param string $namespace Optional namespace
     * @param array $filter Optional metadata filter
     * @return array|false
     */
    public function searchText(
        string $text,
        int $top_k = 10,
        string $namespace = '',
        array $filter = []
    ): array|false {
        $data = [
            'query' => [
                'inputs' => ['text' => $text],
                'top_k' => $top_k
            ]
        ];

        if (!empty($namespace)) {
            $data['namespace'] = $namespace;
        }

        if (!empty($filter)) {
            $data['query']['filter'] = $filter;
        }

        return $this->makeRequest('POST', '/search', $data);
    }

    /**
     * Search with reranking for better results
     *
     * @param string $text Query text
     * @param int $top_k Number of results to return
     * @param string $namespace Optional namespace
     * @param array $filter Optional metadata filter
     * @param string $rerank_model Reranking model to use
     * @param array $rank_fields Fields to use for reranking
     * @return array|false
     */
    public function searchWithRerank(
        string $text,
        int $top_k = 10,
        string $namespace = '',
        array $filter = [],
        string $rerank_model = 'bge-reranker-v2-m3',
        array $rank_fields = ['content']
    ): array|false {
        $data = [
            'query' => [
                'inputs' => ['text' => $text],
                'top_k' => $top_k * 2 // Get more candidates for reranking
            ],
            'rerank' => [
                'model' => $rerank_model,
                'top_n' => $top_k,
                'rank_fields' => $rank_fields
            ]
        ];

        if (!empty($namespace)) {
            $data['namespace'] = $namespace;
        }

        if (!empty($filter)) {
            $data['query']['filter'] = $filter;
        }

        return $this->makeRequest('POST', '/search', $data);
    }

    /**
     * Fetch vectors by IDs
     *
     * @param array $ids Vector IDs to fetch
     * @param string $namespace Optional namespace
     * @return array|false
     */
    public function fetch(array $ids, string $namespace = ''): array|false
    {
        $data = ['ids' => $ids];

        if (!empty($namespace)) {
            $data['namespace'] = $namespace;
        }

        return $this->makeRequest('POST', '/vectors/fetch', $data);
    }

    /**
     * Delete vectors by IDs
     *
     * @param array $ids Vector IDs to delete
     * @param string $namespace Optional namespace
     * @return array|false
     */
    public function delete(array $ids, string $namespace = ''): array|false
    {
        $data = ['ids' => $ids];

        if (!empty($namespace)) {
            $data['namespace'] = $namespace;
        }

        return $this->makeRequest('POST', '/vectors/delete', $data);
    }

    /**
     * Delete all vectors in namespace
     *
     * @param string $namespace Namespace to clear
     * @return array|false
     */
    public function deleteAll(string $namespace = ''): array|false
    {
        $data = ['deleteAll' => true];

        if (!empty($namespace)) {
            $data['namespace'] = $namespace;
        }

        return $this->makeRequest('POST', '/vectors/delete', $data);
    }

    /**
     * List vector IDs (paginated)
     *
     * @param string $namespace Optional namespace
     * @param string $prefix Optional ID prefix filter
     * @param int $limit Results per page (max 1000)
     * @param string $pagination_token Token for next page
     * @return array|false
     */
    public function listIds(
        string $namespace = '',
        string $prefix = '',
        int $limit = 100,
        string $pagination_token = ''
    ): array|false {
        $data = ['limit' => min($limit, 1000)];

        if (!empty($namespace)) {
            $data['namespace'] = $namespace;
        }

        if (!empty($prefix)) {
            $data['prefix'] = $prefix;
        }

        if (!empty($pagination_token)) {
            $data['paginationToken'] = $pagination_token;
        }

        return $this->makeRequest('POST', '/vectors/list', $data);
    }

    /**
     * Test connection to Pinecone
     *
     * @return bool
     */
    public function testConnection(): bool
    {
        $result = $this->describeIndexStats();
        return $result !== false;
    }

    /**
     * Get namespace statistics
     *
     * @param string $namespace Namespace to check
     * @return array|null
     */
    public function getNamespaceStats(string $namespace = ''): ?array
    {
        $stats = $this->describeIndexStats();

        if ($stats === false) {
            return null;
        }

        if (empty($namespace)) {
            return $stats;
        }

        return $stats['namespaces'][$namespace] ?? null;
    }

    /**
     * Batch upsert with automatic chunking
     *
     * @param array $vectors Large array of vectors to upsert
     * @param string $namespace Optional namespace
     * @param int $batch_size Vectors per batch (max 1000)
     * @return bool Success status
     */
    public function batchUpsert(array $vectors, string $namespace = '', int $batch_size = 100): bool
    {
        $batch_size = min($batch_size, 1000);
        $total_batches = ceil(count($vectors) / $batch_size);

        for ($i = 0; $i < $total_batches; $i++) {
            $batch = array_slice($vectors, $i * $batch_size, $batch_size);

            $result = $this->upsert($batch, $namespace);
            if ($result === false) {
                error_log("Pinecone batch upsert failed on batch " . ($i + 1) . " of {$total_batches}");
                return false;
            }

            // Rate limiting - small delay between batches
            if ($i < $total_batches - 1) {
                usleep(100000); // 100ms delay
            }
        }

        return true;
    }
}
