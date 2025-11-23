<?php

/**
 * Exemple d'utilisation de l'intégration Pinecone
 *
 * Ce fichier montre comment utiliser les classes Pinecone dans votre plugin WordPress
 */

// Ne pas exécuter ce fichier directement
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Exemple 1: Configuration initiale de Pinecone
 */
function example_setup_pinecone()
{
    // Charger les classes
    require_once plugin_dir_path(__FILE__) . 'class.pinecone-config.php';

    // Configurer Pinecone via code (ou utiliser l'interface admin)
    PineconeConfig::setApiKey('pc-your-api-key-here');
    PineconeConfig::setHost('your-index-abc123.svc.aped-4627-b74a.pinecone.io');
    PineconeConfig::setIndexName('photo-library-index');
    PineconeConfig::setEnvironment('us-east-1-aws');

    // Vérifier la configuration
    if (PineconeConfig::isConfigured()) {
        echo "Pinecone est configuré correctement!";
    } else {
        echo "Configuration Pinecone incomplète.";
    }
}

/**
 * Exemple 2: Test de connexion
 */
function example_test_connection()
{
    require_once plugin_dir_path(__FILE__) . 'class.photo-library-pinecone.php';

    $pinecone = new PhotoLibraryPinecone();

    if ($pinecone->testConnection()) {
        $stats = $pinecone->getIndexStats();
        echo "Connexion réussie! Index contient " . $stats['total_vectors'] . " vecteurs.";
    } else {
        echo "Échec de la connexion à Pinecone.";
    }
}

/**
 * Exemple 3: Indexer une photo avec ses embeddings
 */
function example_index_photo($photo_id)
{
    require_once plugin_dir_path(__FILE__) . 'class.photo-library-pinecone.php';

    // Générer ou récupérer les embeddings de l'image
    // Ceci est un exemple - vous devrez implémenter la génération d'embeddings
    $embedding = generate_image_embedding($photo_id);

    // Récupérer les métadonnées de l'image
    $metadata = [
        'title' => get_the_title($photo_id),
        'description' => get_post_field('post_content', $photo_id),
        'keywords' => get_post_meta($photo_id, 'keywords', true),
        'upload_date' => get_post_field('post_date', $photo_id),
        'file_size' => filesize(get_attached_file($photo_id)),
        'mime_type' => get_post_mime_type($photo_id)
    ];

    $pinecone = new PhotoLibraryPinecone();

    if ($pinecone->indexPhoto($photo_id, $embedding, $metadata)) {
        echo "Photo {$photo_id} indexée avec succès dans Pinecone!";
    } else {
        echo "Échec de l'indexation de la photo {$photo_id}.";
    }
}

/**
 * Exemple 4: Indexer plusieurs photos en lot
 */
function example_batch_index_photos($photo_ids)
{
    require_once plugin_dir_path(__FILE__) . 'class.photo-library-pinecone.php';

    $photos_data = [];

    foreach ($photo_ids as $photo_id) {
        // Générer les embeddings pour chaque photo
        $embedding = generate_image_embedding($photo_id);

        $photos_data[] = [
            'id' => $photo_id,
            'embedding' => $embedding,
            'metadata' => [
                'title' => get_the_title($photo_id),
                'description' => get_post_field('post_content', $photo_id),
                'upload_date' => get_post_field('post_date', $photo_id)
            ]
        ];
    }

    $pinecone = new PhotoLibraryPinecone();

    if ($pinecone->batchIndexPhotos($photos_data)) {
        echo "Lot de " . count($photos_data) . " photos indexé avec succès!";
    } else {
        echo "Échec de l'indexation du lot de photos.";
    }
}

/**
 * Exemple 5: Recherche par similarité avec un vecteur
 */
function example_search_similar_photos($query_vector)
{
    require_once plugin_dir_path(__FILE__) . 'class.photo-library-pinecone.php';

    $pinecone = new PhotoLibraryPinecone();

    // Recherche avec filtres optionnels
    $filter = [
        'upload_date' => ['$gte' => '2024-01-01'],
        'mime_type' => 'image/jpeg'
    ];

    $results = $pinecone->searchSimilarPhotos($query_vector, 10, $filter);

    if ($results !== false) {
        echo "Trouvé " . count($results) . " photos similaires:\n";
        foreach ($results as $result) {
            echo "- Photo ID: {$result['photo_id']}, Score: {$result['similarity_score']}\n";
        }
    } else {
        echo "Échec de la recherche.";
    }
}

/**
 * Exemple 6: Recherche par texte (nécessite des embeddings intégrés)
 */
function example_search_photos_by_text($search_text)
{
    require_once plugin_dir_path(__FILE__) . 'class.photo-library-pinecone.php';

    $pinecone = new PhotoLibraryPinecone();

    // Recherche avec reranking pour de meilleurs résultats
    $results = $pinecone->searchPhotosByText($search_text, 10, [], true);

    if ($results !== false) {
        echo "Résultats pour '{$search_text}':\n";
        foreach ($results as $result) {
            $title = $result['metadata']['title'] ?? 'Sans titre';
            echo "- {$title} (ID: {$result['photo_id']}, Score: {$result['similarity_score']})\n";
        }
    } else {
        echo "Échec de la recherche textuelle.";
    }
}

/**
 * Exemple 7: Supprimer une photo de l'index
 */
function example_remove_photo($photo_id)
{
    require_once plugin_dir_path(__FILE__) . 'class.photo-library-pinecone.php';

    $pinecone = new PhotoLibraryPinecone();

    if ($pinecone->removePhoto($photo_id)) {
        echo "Photo {$photo_id} supprimée de l'index Pinecone.";
    } else {
        echo "Échec de la suppression de la photo {$photo_id}.";
    }
}

/**
 * Exemple 8: Obtenir les statistiques de l'index
 */
function example_get_index_stats()
{
    require_once plugin_dir_path(__FILE__) . 'class.photo-library-pinecone.php';

    $pinecone = new PhotoLibraryPinecone();
    $stats = $pinecone->getIndexStats();

    if ($stats) {
        echo "Statistiques de l'index:\n";
        echo "- Nombre total de vecteurs: {$stats['total_vectors']}\n";
        echo "- Namespace: {$stats['namespace']}\n";
        echo "- Dernière mise à jour: {$stats['last_updated']}\n";
    } else {
        echo "Impossible d'obtenir les statistiques.";
    }
}

/**
 * Exemple 9: Trouver les photos qui ont besoin d'indexation
 */
function example_find_photos_needing_indexing()
{
    require_once plugin_dir_path(__FILE__) . 'class.photo-library-pinecone.php';

    $pinecone = new PhotoLibraryPinecone();
    $photo_ids = $pinecone->getPhotosNeedingIndexing(100);

    echo "Photos nécessitant une indexation: " . count($photo_ids) . "\n";
    foreach ($photo_ids as $photo_id) {
        echo "- Photo ID: {$photo_id}\n";
    }
}

/**
 * Exemple 10: Configurer les hooks WordPress pour l'indexation automatique
 */
function example_setup_automatic_indexing()
{
    require_once plugin_dir_path(__FILE__) . 'class.photo-library-pinecone.php';

    $pinecone = new PhotoLibraryPinecone();
    $pinecone->addHooks();

    // Ajouter le hook pour l'action cron d'indexation
    add_action('photo_library_index_photo', function ($photo_id) use ($pinecone) {
        // Vérifier si la photo est une image
        if (!wp_attachment_is_image($photo_id)) {
            return;
        }

        // Générer les embeddings et indexer
        $embedding = generate_image_embedding($photo_id);
        if ($embedding) {
            $metadata = [
                'title' => get_the_title($photo_id),
                'description' => get_post_field('post_content', $photo_id),
                'upload_date' => get_post_field('post_date', $photo_id)
            ];

            $pinecone->indexPhoto($photo_id, $embedding, $metadata);
        }
    });

    echo "Indexation automatique configurée!";
}

/**
 * Fonction d'exemple pour générer des embeddings d'image
 * ATTENTION: Cette fonction est un exemple et doit être remplacée
 * par une vraie implémentation utilisant un service d'embeddings
 */
function generate_image_embedding($photo_id)
{
    // EXEMPLE SEULEMENT - remplacer par une vraie génération d'embeddings
    // Vous pourriez utiliser :
    // - OpenAI CLIP API
    // - Google Vision AI
    // - Azure Computer Vision
    // - Un modèle local comme CLIP

    // Générer un vecteur factice de 1536 dimensions (taille OpenAI)
    $embedding = [];
    for ($i = 0; $i < 1536; $i++) {
        $embedding[] = (float) (rand(-100, 100) / 100);
    }

    // Normaliser le vecteur (important pour la similarité cosinus)
    $magnitude = sqrt(array_sum(array_map(function ($x) { return $x * $x; }, $embedding)));
    return array_map(function ($x) use ($magnitude) { return $x / $magnitude; }, $embedding);
}

/**
 * Commande WP-CLI pour indexer toutes les photos
 */
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('photo-library pinecone-index-all', function ($args, $assoc_args) {
        require_once plugin_dir_path(__FILE__) . 'class.photo-library-pinecone.php';

        $pinecone = new PhotoLibraryPinecone();
        $photo_ids = $pinecone->getPhotosNeedingIndexing(1000);

        WP_CLI::line("Indexation de " . count($photo_ids) . " photos...");

        $batch_size = 10;
        for ($i = 0; $i < count($photo_ids); $i += $batch_size) {
            $batch = array_slice($photo_ids, $i, $batch_size);
            $photos_data = [];

            foreach ($batch as $photo_id) {
                $embedding = generate_image_embedding($photo_id);
                $photos_data[] = [
                    'id' => $photo_id,
                    'embedding' => $embedding,
                    'metadata' => [
                        'title' => get_the_title($photo_id),
                        'upload_date' => get_post_field('post_date', $photo_id)
                    ]
                ];
            }

            if ($pinecone->batchIndexPhotos($photos_data)) {
                WP_CLI::line("Lot " . (intval($i / $batch_size) + 1) . " indexé avec succès.");
            } else {
                WP_CLI::error("Échec de l'indexation du lot " . (intval($i / $batch_size) + 1));
            }
        }

        WP_CLI::success("Indexation terminée!");
    });
}
