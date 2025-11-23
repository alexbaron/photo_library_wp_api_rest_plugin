<?php

/**
 * Photo Library Pinecone Integration
 *
 * Cette classe gère l'intégration entre le plugin Photo Library et Pinecone
 * pour la recherche sémantique d'images basée sur les vecteurs.
 */
class PhotoLibraryPinecone
{
    /**
     * @var PineconeClient
     */
    private ?PineconeClient $client = null;

    /**
     * @var string Default namespace for photo embeddings
     */
    private const DEFAULT_NAMESPACE = 'photo_library';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->initializeClient();
    }

    /**
     * Initialize Pinecone client
     *
     * @return void
     */
    private function initializeClient(): void
    {
        // Charger la classe PineconeClient si pas encore chargée
        if (!class_exists('PineconeClient')) {
            require_once plugin_dir_path(__FILE__) . 'class.pinecone-client.php';
        }

        $this->client = PineconeClient::fromWordPressOptions();

        if ($this->client === null) {
            add_action('admin_notices', [$this, 'showConfigurationNotice']);
        }
    }

    /**
     * Show admin notice when Pinecone is not configured
     *
     * @return void
     */
    public function showConfigurationNotice(): void
    {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>Photo Library:</strong> Pinecone configuration required for semantic search. ';
        echo '<a href="' . admin_url('admin.php?page=photo-library-settings') . '">Configure now</a></p>';
        echo '</div>';
    }

    /**
     * Test Pinecone connection
     *
     * @return bool
     */
    public function testConnection(): bool
    {
        if (!$this->client) {
            return false;
        }

        return $this->client->testConnection();
    }

    /**
     * Index a photo with its embeddings
     *
     * @param int $photo_id Photo ID
     * @param array $embedding Vector embedding
     * @param array $metadata Photo metadata
     * @return bool Success status
     */
    public function indexPhoto(int $photo_id, array $embedding, array $metadata = []): bool
    {
        if (!$this->client) {
            return false;
        }

        // Préparer le vecteur pour Pinecone
        $vector = [
            'id' => (string) $photo_id,
            'values' => $embedding,
            'metadata' => array_merge($metadata, [
                'photo_id' => $photo_id,
                'indexed_at' => current_time('mysql'),
                'source' => 'photo_library'
            ])
        ];

        $result = $this->client->upsert([$vector], self::DEFAULT_NAMESPACE);

        if ($result !== false) {
            // Sauvegarder le statut d'indexation dans WordPress
            update_post_meta($photo_id, '_pinecone_indexed', true);
            update_post_meta($photo_id, '_pinecone_indexed_at', current_time('mysql'));
            return true;
        }

        return false;
    }

    /**
     * Index multiple photos in batch
     *
     * @param array $photos Array of photo data with embeddings
     * @return bool Success status
     */
    public function batchIndexPhotos(array $photos): bool
    {
        if (!$this->client) {
            return false;
        }

        $vectors = [];
        foreach ($photos as $photo) {
            $vectors[] = [
                'id' => (string) $photo['id'],
                'values' => $photo['embedding'],
                'metadata' => array_merge($photo['metadata'] ?? [], [
                    'photo_id' => $photo['id'],
                    'indexed_at' => current_time('mysql'),
                    'source' => 'photo_library'
                ])
            ];
        }

        $success = $this->client->batchUpsert($vectors, self::DEFAULT_NAMESPACE);

        if ($success) {
            // Marquer toutes les photos comme indexées
            foreach ($photos as $photo) {
                update_post_meta($photo['id'], '_pinecone_indexed', true);
                update_post_meta($photo['id'], '_pinecone_indexed_at', current_time('mysql'));
            }
        }

        return $success;
    }

    /**
     * Search for similar photos using vector similarity
     *
     * @param array $query_vector Query embedding vector
     * @param int $limit Number of results to return
     * @param array $filter Optional metadata filter
     * @return array|false Search results or false on error
     */
    public function searchSimilarPhotos(array $query_vector, int $limit = 10, array $filter = []): array|false
    {
        if (!$this->client) {
            return false;
        }

        // Ajouter le filtre source par défaut
        if (!isset($filter['source'])) {
            $filter['source'] = 'photo_library';
        }

        $result = $this->client->query(
            $query_vector,
            $limit,
            self::DEFAULT_NAMESPACE,
            $filter,
            false, // includeValues
            true   // includeMetadata
        );

        if ($result === false) {
            return false;
        }

        // Transformer les résultats pour le plugin
        $photos = [];
        foreach ($result['matches'] ?? [] as $match) {
            $photos[] = [
                'photo_id' => (int) $match['metadata']['photo_id'],
                'similarity_score' => $match['score'],
                'metadata' => $match['metadata']
            ];
        }

        return $photos;
    }

    /**
     * Search photos using text query (requires integrated embeddings)
     *
     * @param string $text_query Search text
     * @param int $limit Number of results to return
     * @param array $filter Optional metadata filter
     * @param bool $use_rerank Use reranking for better results
     * @return array|false Search results or false on error
     */
    public function searchPhotosByText(
        string $text_query,
        int $limit = 10,
        array $filter = [],
        bool $use_rerank = true
    ): array|false {
        if (!$this->client) {
            return false;
        }

        // Ajouter le filtre source par défaut
        if (!isset($filter['source'])) {
            $filter['source'] = 'photo_library';
        }

        if ($use_rerank) {
            $result = $this->client->searchWithRerank(
                $text_query,
                $limit,
                self::DEFAULT_NAMESPACE,
                $filter,
                'bge-reranker-v2-m3',
                ['title', 'description', 'keywords']
            );
        } else {
            $result = $this->client->searchText(
                $text_query,
                $limit,
                self::DEFAULT_NAMESPACE,
                $filter
            );
        }

        if ($result === false) {
            return false;
        }

        // Transformer les résultats
        $photos = [];
        $hits = $result['result']['hits'] ?? $result['matches'] ?? [];

        foreach ($hits as $hit) {
            $photos[] = [
                'photo_id' => (int) ($hit['metadata']['photo_id'] ?? $hit['fields']['photo_id']),
                'similarity_score' => $hit['score'] ?? $hit['_score'],
                'metadata' => $hit['metadata'] ?? $hit['fields']
            ];
        }

        return $photos;
    }

    /**
     * Remove photo from index
     *
     * @param int $photo_id Photo ID to remove
     * @return bool Success status
     */
    public function removePhoto(int $photo_id): bool
    {
        if (!$this->client) {
            return false;
        }

        $result = $this->client->delete([(string) $photo_id], self::DEFAULT_NAMESPACE);

        if ($result !== false) {
            // Supprimer les métadonnées d'indexation
            delete_post_meta($photo_id, '_pinecone_indexed');
            delete_post_meta($photo_id, '_pinecone_indexed_at');
            return true;
        }

        return false;
    }

    /**
     * Get index statistics
     *
     * @return array|null
     */
    public function getIndexStats(): ?array
    {
        if (!$this->client) {
            return null;
        }

        $stats = $this->client->getNamespaceStats(self::DEFAULT_NAMESPACE);

        if ($stats) {
            return [
                'total_vectors' => $stats['vectorCount'] ?? 0,
                'namespace' => self::DEFAULT_NAMESPACE,
                'last_updated' => current_time('mysql')
            ];
        }

        return null;
    }

    /**
     * Check if photo is indexed in Pinecone
     *
     * @param int $photo_id Photo ID
     * @return bool
     */
    public function isPhotoIndexed(int $photo_id): bool
    {
        $indexed = get_post_meta($photo_id, '_pinecone_indexed', true);
        return !empty($indexed);
    }

    /**
     * Get photos that need indexing
     *
     * @param int $limit Maximum number of photos to return
     * @return array Photo IDs that need indexing
     */
    public function getPhotosNeedingIndexing(int $limit = 50): array
    {
        global $wpdb;

        $query = $wpdb->prepare("
			SELECT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_pinecone_indexed')
			WHERE p.post_type = 'attachment'
			AND p.post_mime_type LIKE 'image/%'
			AND (pm.meta_value IS NULL OR pm.meta_value = '')
			ORDER BY p.post_date DESC
			LIMIT %d
		", $limit);

        $results = $wpdb->get_col($query);
        return array_map('intval', $results);
    }

    /**
     * Reindex all photos (use with caution)
     *
     * @return bool Success status
     */
    public function reindexAllPhotos(): bool
    {
        if (!$this->client) {
            return false;
        }

        // Supprimer toutes les données de l'index
        $result = $this->client->deleteAll(self::DEFAULT_NAMESPACE);

        if ($result !== false) {
            // Réinitialiser les métadonnées d'indexation
            global $wpdb;
            $wpdb->delete($wpdb->postmeta, [
                'meta_key' => '_pinecone_indexed'
            ]);
            $wpdb->delete($wpdb->postmeta, [
                'meta_key' => '_pinecone_indexed_at'
            ]);

            return true;
        }

        return false;
    }

    /**
     * Add WordPress hooks for automatic indexing
     *
     * @return void
     */
    public function addHooks(): void
    {
        // Indexer automatiquement les nouvelles images
        add_action('add_attachment', [$this, 'onAttachmentAdded']);

        // Supprimer de l'index quand une image est supprimée
        add_action('delete_attachment', [$this, 'onAttachmentDeleted']);

        // Réindexer quand les métadonnées changent
        add_action('updated_postmeta', [$this, 'onMetadataUpdated'], 10, 4);
    }

    /**
     * Hook: When attachment is added
     *
     * @param int $attachment_id
     * @return void
     */
    public function onAttachmentAdded(int $attachment_id): void
    {
        // Vérifier si c'est une image
        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }

        // Marquer pour indexation différée (via cron job)
        wp_schedule_single_event(time() + 60, 'photo_library_index_photo', [$attachment_id]);
    }

    /**
     * Hook: When attachment is deleted
     *
     * @param int $attachment_id
     * @return void
     */
    public function onAttachmentDeleted(int $attachment_id): void
    {
        $this->removePhoto($attachment_id);
    }

    /**
     * Hook: When post metadata is updated
     *
     * @param int $meta_id
     * @param int $post_id
     * @param string $meta_key
     * @param mixed $meta_value
     * @return void
     */
    public function onMetadataUpdated(int $meta_id, int $post_id, string $meta_key, $meta_value): void
    {
        // Réindexer si les métadonnées importantes changent
        $important_keys = ['_wp_attachment_metadata', '_wp_attached_file'];

        if (in_array($meta_key, $important_keys) && wp_attachment_is_image($post_id)) {
            // Marquer pour réindexation
            delete_post_meta($post_id, '_pinecone_indexed');
            wp_schedule_single_event(time() + 30, 'photo_library_index_photo', [$post_id]);
        }
    }
}
