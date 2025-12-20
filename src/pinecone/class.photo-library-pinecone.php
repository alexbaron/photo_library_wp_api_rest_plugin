<?php

/**
 * PhotoLibrary Color Search Index for Pinecone
 *
 * Classe pour gérer l'index de recherche de couleurs avec Pinecone.
 * Permet de rechercher des images par similarité de couleur RGB.
 *
 * @package PhotoLibrary
 * @version 1.0.0
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\GuzzleException;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class PL_Color_Search_Index
{
    /**
     * @var string Nom de l'index Pinecone
     */
    private const INDEX_NAME = 'phototheque-color-search';

    /**
     * @var string Namespace par défaut
     */
    private const NAMESPACE = 'photos';

    /**
     * @var int Dimensions du vecteur (RGB = 3 dimensions)
     */
    private const DIMENSIONS = 3;

    /**
     * @var string Clé API Pinecone
     */
    private string $api_key;

    /**
     * @var string URL de base de l'API Pinecone
     */
    private string $base_url;

    /**
     * @var Client Client HTTP Guzzle
     */
    private Client $http_client;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->api_key = $this->get_api_key();
        // $this->base_url = 'https://phototheque-color-search-123abc.svc.us-east-1.pinecone.io';
        $this->base_url = 'https://phototheque-color-search-c2u1l8z.svc.aped-4627-b74a.pinecone.io';

        if (empty($this->api_key)) {
            throw new Exception('PINECONE_API_KEY environment variable is required');
        }

        // Initialiser le client Guzzle
        $this->http_client = new Client([
            'base_uri' => $this->base_url,
            'timeout' => 30,
            'headers' => [
                'Api-Key' => $this->api_key,
                'Content-Type' => 'application/json',
                'User-Agent' => 'PhotoLibrary-WordPress-Plugin/1.0'
            ]
        ]);
    }

    /**
     * Récupère la clé API depuis les variables d'environnement
     */
    private function get_api_key(): string
    {
        // Charger le fichier .env s'il existe
        $env_file = dirname(__FILE__) . '/../../.env';
        if (file_exists($env_file)) {
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                if (strpos($line, 'PINECONE_API_KEY=') === 0) {
                    $api_key = trim(substr($line, strlen('PINECONE_API_KEY=')));
                    $api_key = trim($api_key, '"\'');
                    if (!empty($api_key)) {
                        putenv("PINECONE_API_KEY=$api_key");
                        $_ENV['PINECONE_API_KEY'] = $api_key;
                        return $api_key;
                    }
                }
            }
        }

        // Essayer différentes sources de configuration
        $api_key = getenv('PINECONE_API_KEY');

        if (empty($api_key) && defined('PINECONE_API_KEY')) {
            $api_key = PINECONE_API_KEY;
        }

        if (empty($api_key)) {
            $api_key = get_option('photolibrary_pinecone_api_key', '');
        }

        return $api_key;
    }

    /**
     * Ajouter ou mettre à jour une photo dans l'index
     *
     * @param int $photo_id ID de la photo WordPress
     * @param array $rgb_color Couleur RGB [r, g, b]
     * @param array $metadata Métadonnées additionnelles
     * @return bool Success
     */
    public function upsert_photo_color(int $photo_id, array $rgb_color, array $metadata = []): bool
    {
        try {
            // Normaliser les valeurs RGB (0-1)
            $normalized_rgb = [
                $rgb_color[0] / 255.0,
                $rgb_color[1] / 255.0,
                $rgb_color[2] / 255.0
            ];

            $vector_data = [
                'vectors' => [
                    [
                        'id' => (string) $photo_id,
                        'values' => $normalized_rgb,
                        'metadata' => array_merge([
                            'photo_id' => $photo_id,
                            'rgb' => $rgb_color,
                            'uploaded_at' => current_time('mysql')
                        ], $metadata)
                    ]
                ],
                'namespace' => self::NAMESPACE
            ];

            $response = $this->make_request('POST', '/vectors/upsert', $vector_data);

            return isset($response['upsertedCount']) && $response['upsertedCount'] > 0;

        } catch (Exception $e) {
            error_log('PL_Color_Search_Index::upsert_photo_color error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Batch insert de plusieurs photos
     *
     * @param array $photos Array de photos avec ['id' => int, 'rgb' => [r,g,b], 'metadata' => []]
     * @return array Résultats avec ['success_count' => int, 'error_count' => int]
     */
    public function batch_upsert_photos(array $photos): array
    {
        $results = ['success_count' => 0, 'error_count' => 0];

        // Traiter par chunks de 100 (limite Pinecone)
        $chunks = array_chunk($photos, 100);

        foreach ($chunks as $chunk) {
            try {
                $vectors = [];

                foreach ($chunk as $photo) {
                    $normalized_rgb = [
                        $photo['rgb'][0] / 255.0,
                        $photo['rgb'][1] / 255.0,
                        $photo['rgb'][2] / 255.0
                    ];

                    $vectors[] = [
                        'id' => (string) $photo['id'],
                        'values' => $normalized_rgb,
                        'metadata' => array_merge([
                            'photo_id' => $photo['id'],
                            'rgb' => $photo['rgb'],
                            'uploaded_at' => current_time('mysql')
                        ], $photo['metadata'] ?? [])
                    ];
                }

                $vector_data = [
                    'vectors' => $vectors,
                    'namespace' => self::NAMESPACE
                ];

                $response = $this->make_request('POST', '/vectors/upsert', $vector_data);

                if (isset($response['upsertedCount'])) {
                    $results['success_count'] += $response['upsertedCount'];
                } else {
                    $results['error_count'] += count($chunk);
                }

            } catch (Exception $e) {
                error_log('PL_Color_Search_Index::batch_upsert_photos chunk error: ' . $e->getMessage());
                $results['error_count'] += count($chunk);
            }
        }

        return $results;
    }

    /**
     * Rechercher des photos par couleur similaire
     *
     * @param array $rgb_color Couleur de recherche [r, g, b]
     * @param int $top_k Nombre de résultats à retourner
     * @param array $filter Filtres de métadonnées
     * @return array Résultats avec photos et scores
     */
    public function search_by_color(array $rgb_color, int $top_k = 10, array $filter = []): array
    {
        try {
            // Normaliser la couleur de recherche
            $normalized_rgb = [
                $rgb_color[0] / 255.0,
                $rgb_color[1] / 255.0,
                $rgb_color[2] / 255.0
            ];

            $query_data = [
                'vector' => $normalized_rgb,
                'topK' => $top_k,
                'includeMetadata' => true,
                'namespace' => self::NAMESPACE
            ];

            // Ajouter les filtres si spécifiés
            if (!empty($filter)) {
                $query_data['filter'] = $filter;
            }

            $response = $this->make_request('POST', '/query', $query_data);

            if (!isset($response['matches'])) {
                return [];
            }

            $results = [];
            foreach ($response['matches'] as $match) {
                $photo_id = intval($match['id']);
                $metadata = $match['metadata'] ?? [];

                $results[] = [
                    'photo_id' => $photo_id,
                    'color_score' => $match['score'],
                    'color_match' => $metadata['rgb'] ?? null,
                    'metadata' => $metadata
                ];
            }

            return $results;

        } catch (Exception $e) {
            error_log('PL_Color_Search_Index::search_by_color error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtenir les statistiques de l'index
     *
     * @return array Statistiques de l'index
     */
    public function get_index_stats(): array
    {
        try {
            $response = $this->make_request('GET', '/describe_index_stats');

            return [
                'total_vectors' => $response['totalVectorCount'] ?? 0,
                'namespaces' => $response['namespaces'] ?? [],
                'dimension' => $response['dimension'] ?? self::DIMENSIONS,
                'index_fullness' => $response['indexFullness'] ?? 0
            ];

        } catch (Exception $e) {
            error_log('PL_Color_Search_Index::get_index_stats error: ' . $e->getMessage());
            return [
                'total_vectors' => 0,
                'namespaces' => [],
                'dimension' => self::DIMENSIONS,
                'index_fullness' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Supprimer une photo de l'index
     *
     * @param int $photo_id ID de la photo
     * @return bool Success
     */
    public function delete_photo(int $photo_id): bool
    {
        try {
            $delete_data = [
                'ids' => [(string) $photo_id],
                'namespace' => self::NAMESPACE
            ];

            $response = $this->make_request('DELETE', '/vectors/delete', $delete_data);

            return true; // Pinecone delete renvoie toujours un succès même si l'ID n'existe pas

        } catch (Exception $e) {
            error_log('PL_Color_Search_Index::delete_photo error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Vider complètement l'index (attention!)
     *
     * @return bool Success
     */
    public function clear_index(): bool
    {
        try {
            $delete_data = [
                'deleteAll' => true,
                'namespace' => self::NAMESPACE
            ];

            $response = $this->make_request('DELETE', '/vectors/delete', $delete_data);

            return true;

        } catch (Exception $e) {
            error_log('PL_Color_Search_Index::clear_index error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Effectuer une requête HTTP vers l'API Pinecone
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     * @throws Exception Si la requête échoue
     */
    private function make_request(string $method, string $endpoint, array $data = []): array
    {
        try {
            $options = [];

            if (!empty($data)) {
                $options['json'] = $data;
            }

            $response = $this->http_client->request($method, $endpoint, $options);
            $body = $response->getBody()->getContents();

            $decoded = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from Pinecone API: ' . json_last_error_msg());
            }

            return $decoded;

        } catch (RequestException $e) {
            $error_message = $e->getMessage();
            if ($e->hasResponse()) {
                $response_body = $e->getResponse()->getBody()->getContents();
                $error_data = json_decode($response_body, true);
                if ($error_data && isset($error_data['message'])) {
                    $error_message = $error_data['message'];
                }
            }
            throw new Exception("Pinecone API error: $error_message");
        } catch (GuzzleException $e) {
            throw new Exception('HTTP request failed: ' . $e->getMessage());
        }
    }

    /**
     * Recherche locale par distance RGB dans WordPress
     *
     * Cette méthode recherche directement dans la base de données WordPress
     * en calculant la distance euclidienne entre couleurs RGB. Elle peut servir
     * de fallback ou d'alternative à Pinecone.
     *
     * @param array $rgb_color Couleur de recherche [r, g, b]
     * @param int $top_k Nombre de résultats à retourner
     * @param array $filter Filtres de métadonnées (non utilisés dans cette version)
     * @return array Résultats avec photos et scores de distance
     */
    public function search_by_color_local(array $rgb_color, int $top_k = 10, array $filter = []): array
    {
        global $wpdb;

        try {
            // Récupérer toutes les photos avec palette de couleurs
            $query = "
                SELECT p.ID, pm.meta_value as palette_data
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image/%'
                AND pm.meta_key = '_pl_palette'
                AND pm.meta_value != ''
                AND pm.meta_value IS NOT NULL
            ";

            $photos_with_palettes = $wpdb->get_results($query);

            if (empty($photos_with_palettes)) {
                return [];
            }

            $distances = [];
            $target_r = (float) $rgb_color[0];
            $target_g = (float) $rgb_color[1];
            $target_b = (float) $rgb_color[2];

            foreach ($photos_with_palettes as $photo) {
                $palette = unserialize($photo->palette_data);

                if (!is_array($palette) || empty($palette)) {
                    continue;
                }

                // Prendre la couleur dominante (première couleur de la palette)
                $dominant_color = null;
                if (isset($palette[0]) && is_array($palette[0]) && count($palette[0]) >= 3) {
                    $dominant_color = $palette[0];
                } elseif (isset($palette['dominant']) && is_array($palette['dominant']) && count($palette['dominant']) >= 3) {
                    $dominant_color = $palette['dominant'];
                } elseif (is_array($palette) && count($palette) >= 3 && is_numeric($palette[0])) {
                    // Cas où la palette est directement [r, g, b]
                    $dominant_color = $palette;
                }

                if ($dominant_color === null) {
                    continue;
                }

                // Calculer la distance euclidienne dans l'espace RGB
                $photo_r = (float) $dominant_color[0];
                $photo_g = (float) $dominant_color[1];
                $photo_b = (float) $dominant_color[2];

                $distance = sqrt(
                    pow($target_r - $photo_r, 2) +
                    pow($target_g - $photo_g, 2) +
                    pow($target_b - $photo_b, 2)
                );

                // Convertir la distance en score de similarité (0 = identique, 1 = très différent)
                $max_distance = sqrt(3 * pow(255, 2)); // Distance max possible en RGB
                $similarity_score = 1.0 - ($distance / $max_distance);

                $distances[] = [
                    'photo_id' => (int) $photo->ID,
                    'distance' => $distance,
                    'color_score' => $similarity_score,
                    'color_match' => [(int) $photo_r, (int) $photo_g, (int) $photo_b],
                    'metadata' => [
                        'photo_id' => (int) $photo->ID,
                        'rgb' => [(int) $photo_r, (int) $photo_g, (int) $photo_b],
                        'search_method' => 'local_rgb_distance'
                    ]
                ];
            }

            // Trier par distance croissante (couleurs les plus proches en premier)
            usort($distances, function ($a, $b) {
                return $a['distance'] <=> $b['distance'];
            });

            // Retourner les top_k résultats
            return array_slice($distances, 0, $top_k);

        } catch (Exception $e) {
            error_log('PL_Color_Search_Index::search_by_color_local error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Recherche hybride : essaie Pinecone d'abord, puis fallback local
     *
     * @param array $rgb_color Couleur de recherche [r, g, b]
     * @param int $top_k Nombre de résultats à retourner
     * @param array $filter Filtres de métadonnées
     * @param bool $force_local Forcer l'utilisation de la recherche locale
     * @return array Résultats avec photos et scores
     */
    public function search_by_color_hybrid(array $rgb_color, int $top_k = 10, array $filter = [], bool $force_local = false): array
    {
        // Si on force la recherche locale ou si Pinecone n'est pas disponible
        if ($force_local || empty($this->api_key)) {
            return $this->search_by_color_local($rgb_color, $top_k, $filter);
        }

        try {
            // Essayer Pinecone d'abord
            $pinecone_results = $this->search_by_color($rgb_color, $top_k, $filter);

            if (!empty($pinecone_results)) {
                return $pinecone_results;
            }

            // Fallback vers recherche locale si Pinecone ne retourne rien
            return $this->search_by_color_local($rgb_color, $top_k, $filter);

        } catch (Exception $e) {
            error_log('PL_Color_Search_Index Pinecone fallback: ' . $e->getMessage());
            // Fallback vers recherche locale en cas d'erreur Pinecone
            return $this->search_by_color_local($rgb_color, $top_k, $filter);
        }
    }

    /**
     * Tester la connexion à Pinecone
     *
     * @return array Résultat du test avec status et message
     */
    public function test_connection(): array
    {
        try {
            $stats = $this->get_index_stats();

            if (isset($stats['error'])) {
                return [
                    'status' => 'error',
                    'message' => 'Connection failed: ' . $stats['error']
                ];
            }

            return [
                'status' => 'success',
                'message' => 'Connection successful',
                'stats' => $stats
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Synchroniser toutes les photos avec palettes vers Pinecone
     *
     * @param bool $extract_missing Extraire les palettes manquantes avant sync
     * @return array Résultat de la synchronisation
     */
    public function sync_all_photos_to_pinecone(bool $extract_missing = false): array
    {
        global $wpdb;

        try {
            $results = [
                'extracted' => 0,
                'synced' => 0,
                'skipped' => 0,
                'errors' => [],
                'total_vectors' => 0
            ];

            // Extraire les palettes manquantes si demandé
            if ($extract_missing) {
                $extraction_result = $this->extract_missing_palettes();
                $results['extracted'] = $extraction_result['extracted'];
                $results['errors'] = array_merge($results['errors'], $extraction_result['errors']);
            }

            // Récupérer toutes les photos avec palette
            $photos = $wpdb->get_results(
                "SELECT
                    p.ID as id,
                    p.post_title as title,
                    palette.meta_value as palette
                FROM {$wpdb->prefix}posts AS p
                LEFT JOIN {$wpdb->prefix}postmeta AS palette
                    ON p.ID = palette.post_id
                    AND palette.meta_key = '_pl_palette'
                WHERE p.post_type = 'attachment'
                    AND p.post_mime_type LIKE 'image%'
                    AND palette.meta_value IS NOT NULL
                ORDER BY p.ID"
            );

            if (empty($photos)) {
                return array_merge($results, [
                    'status' => 'error',
                    'message' => 'No photos with color palette found. Run color extraction first.'
                ]);
            }

            // Préparer les données pour Pinecone
            $photos_to_sync = [];

            foreach ($photos as $photo) {
                $palette = maybe_unserialize($photo->palette);

                if (!is_array($palette) || empty($palette)) {
                    $results['skipped']++;
                    $results['errors'][] = "Photo ID {$photo->id}: Invalid palette data";
                    continue;
                }

                // Prendre la couleur dominante (première couleur de la palette)
                $dominant_color = $palette[0];

                if (!is_array($dominant_color) || count($dominant_color) !== 3) {
                    $results['skipped']++;
                    $results['errors'][] = "Photo ID {$photo->id}: Invalid RGB values";
                    continue;
                }

                $photos_to_sync[] = [
                    'id' => $photo->id,
                    'rgb' => [
                        (int) $dominant_color[0],
                        (int) $dominant_color[1],
                        (int) $dominant_color[2]
                    ]
                ];
            }

            if (empty($photos_to_sync)) {
                return array_merge($results, [
                    'status' => 'error',
                    'message' => 'No valid photos to sync'
                ]);
            }

            // Synchroniser vers Pinecone par batch
            $success = $this->batch_upsert_photos($photos_to_sync);

            if ($success) {
                $results['synced'] = count($photos_to_sync);

                // Obtenir les stats de l'index
                $stats = $this->get_index_stats();
                $results['total_vectors'] = $stats['totalVectorCount'] ?? 0;

                return array_merge($results, [
                    'status' => 'success',
                    'message' => "Successfully synced {$results['synced']} photos to Pinecone"
                ]);
            } else {
                return array_merge($results, [
                    'status' => 'error',
                    'message' => 'Failed to sync photos to Pinecone'
                ]);
            }

        } catch (Exception $e) {
            return array_merge($results ?? [], [
                'status' => 'error',
                'message' => 'Sync failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Extraire les palettes manquantes pour les photos
     *
     * @param int $limit Limite de photos à traiter
     * @return array Résultat de l'extraction
     */
    public function extract_missing_palettes(int $limit = 100): array
    {
        global $wpdb;

        try {
            $results = [
                'extracted' => 0,
                'errors' => [],
                'processed' => 0
            ];

            // Récupérer les photos sans palette
            $photos = $wpdb->get_results(
                "SELECT
                    p.ID as id,
                    p.post_title as title,
                    p.guid as img_url,
                    metadata.meta_value as metadata
                FROM {$wpdb->prefix}posts AS p
                LEFT JOIN {$wpdb->prefix}postmeta AS metadata
                    ON p.ID = metadata.post_id
                    AND metadata.meta_key = '_wp_attachment_metadata'
                LEFT JOIN {$wpdb->prefix}postmeta AS palette
                    ON p.ID = palette.post_id
                    AND palette.meta_key = '_pl_palette'
                WHERE p.post_type = 'attachment'
                    AND p.post_mime_type LIKE 'image%'
                    AND palette.meta_value IS NULL
                ORDER BY p.ID
                LIMIT " . intval($limit)
            );

            if (empty($photos)) {
                return array_merge($results, [
                    'status' => 'success',
                    'message' => 'All photos already have color palettes'
                ]);
            }

            // Charger les classes nécessaires
            if (!class_exists('PL_COLOR_HANDLER')) {
                require_once __DIR__ . '/../color/class.photo-library-color.php';
            }
            if (!class_exists('PL_REST_DB')) {
                require_once __DIR__ . '/../class.photo-library-db.php';
            }

            $color_handler = new PL_COLOR_HANDLER();
            $db = new PL_REST_DB($wpdb);

            foreach ($photos as $photo) {
                try {
                    $results['processed']++;

                    // Extraire la palette
                    $palette = $color_handler->extractPalette($photo);

                    if (!empty($palette)) {
                        // Sauvegarder en base
                        $db->savePaletteMeta($photo->id, $palette);
                        $results['extracted']++;
                    } else {
                        $results['errors'][] = "Photo ID {$photo->id}: Failed to extract palette";
                    }

                } catch (Exception $e) {
                    $results['errors'][] = "Photo ID {$photo->id}: " . $e->getMessage();
                }
            }

            return array_merge($results, [
                'status' => 'success',
                'message' => "Extracted palettes for {$results['extracted']} out of {$results['processed']} photos"
            ]);

        } catch (Exception $e) {
            return array_merge($results ?? [], [
                'status' => 'error',
                'message' => 'Extraction failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Obtenir la liste des photos avec leurs palettes pour analyse
     *
     * @param int $limit Nombre de photos à récupérer
     * @return array Liste des photos avec palettes
     */
    public function get_photos_for_pinecone_analysis(int $limit = 10): array
    {
        global $wpdb;

        try {
            $photos = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT
                        p.ID as id,
                        p.post_title as title,
                        palette.meta_value as palette,
                        p.post_date as created_at
                    FROM {$wpdb->prefix}posts AS p
                    LEFT JOIN {$wpdb->prefix}postmeta AS palette
                        ON p.ID = palette.post_id
                        AND palette.meta_key = '_pl_palette'
                    WHERE p.post_type = 'attachment'
                        AND p.post_mime_type LIKE 'image%%'
                        AND palette.meta_value IS NOT NULL
                    ORDER BY p.ID DESC
                    LIMIT %d",
                    $limit
                )
            );

            $result = [];
            foreach ($photos as $photo) {
                $palette = maybe_unserialize($photo->palette);

                if (is_array($palette) && !empty($palette)) {
                    $dominant_color = $palette[0];

                    if (is_array($dominant_color) && count($dominant_color) === 3) {
                        $result[] = [
                            'id' => $photo->id,
                            'title' => $photo->title,
                            'rgb' => [
                                (int) $dominant_color[0],
                                (int) $dominant_color[1],
                                (int) $dominant_color[2]
                            ],
                            'hex' => sprintf(
                                '#%02x%02x%02x',
                                (int) $dominant_color[0],
                                (int) $dominant_color[1],
                                (int) $dominant_color[2]
                            ),
                            'palette_size' => count($palette),
                            'created_at' => $photo->created_at
                        ];
                    }
                }
            }

            return $result;

        } catch (Exception $e) {
            return [
                'error' => 'Failed to get photos for analysis: ' . $e->getMessage()
            ];
        }
    }
}
