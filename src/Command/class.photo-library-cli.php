<?php

/**
 * Exemple de commande WP-CLI personnalisée pour PhotoLibrary
 * Fichier: src/command/class.photo-library-cli.php
 */

if (defined('WP_CLI') && WP_CLI) {

    /**
     * Commandes personnalisées pour PhotoLibrary
     */
    class PhotoLibrary_CLI_Commands
    {
        /**
         * Constructor - Initialise l'environnement CLI proprement
         */
        public function __construct() {
            // Supprimer les notices de dépréciation non critiques pendant l'exécution des commandes
            $this->setup_error_handling();
        }

        /**
         * Configure la gestion d'erreur pour les commandes CLI
         */
        private function setup_error_handling() {
            // Réduire le niveau de verbosité pour les deprecated notices non critiques
            add_filter('deprecated_function_trigger_error', array($this, 'filter_cli_deprecated_notices'), 10, 4);
            add_filter('deprecated_argument_trigger_error', array($this, 'filter_cli_deprecated_notices'), 10, 4);
            add_filter('deprecated_hook_trigger_error', array($this, 'filter_cli_deprecated_notices'), 10, 4);
        }

        /**
         * Filtre les notices de dépréciation pendant l'exécution CLI
         *
         * @param bool $trigger
         * @param string $function_name
         * @param string $replacement
         * @param string $version
         * @return bool
         */
        public function filter_cli_deprecated_notices($trigger, $function_name, $replacement, $version) {
            // Liste des fonctions dépréciées à ignorer pendant les commandes CLI
            $ignored_functions = array(
                'visual_composer',
                'vc_',
                'wpb_',
                // Ajoutez d'autres fonctions problématiques ici
            );

            foreach ($ignored_functions as $ignored) {
                if (strpos($function_name, $ignored) === 0) {
                    return false; // Supprimer l'affichage de cette notice
                }
            }

            return $trigger;
        }
        /**
         * Supprime toutes les données de palettes existantes
         *
         * ## OPTIONS
         *
         * [--confirm]
         * : Confirme la suppression sans demander
         *
         * ## EXAMPLES
         *
         *     wp photolibrary clear_palettes
         *     wp photolibrary clear_palettes --confirm
         *
         * @when after_wp_load
         */
        public function clear_palettes($args, $assoc_args)
        {
            if (!isset($assoc_args['confirm'])) {
                WP_CLI::confirm("Êtes-vous sûr de vouloir supprimer toutes les données de palettes ?");
            }

            try {
                global $wpdb;
                $db = new PL_REST_DB($wpdb);

                WP_CLI::line("🗑️  Suppression des palettes en cours...");

                // Supprimer les palettes de la table meta
                $deleted_meta = $wpdb->query(
                    "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'pl_palette'"
                );

                // Supprimer les palettes de la table cache si elle existe
                $cache_table = $wpdb->prefix . 'pl_color_cache';
                if ($wpdb->get_var("SHOW TABLES LIKE '{$cache_table}'") == $cache_table) {
                    $deleted_cache = $wpdb->query("TRUNCATE TABLE {$cache_table}");
                } else {
                    $deleted_cache = 0;
                }

                // Vider le cache
                if (class_exists('PL_Cache_Manager')) {
                    PL_Cache_Manager::flush_all_cache();
                }

                WP_CLI::success("✅ Suppression terminée !");
                WP_CLI::line("📊 Statistiques :");
                WP_CLI::line("  - Métadonnées supprimées: {$deleted_meta}");
                WP_CLI::line("  - Cache supprimé: {$deleted_cache} entrées");

            } catch (Exception $e) {
                WP_CLI::error("❌ Erreur lors de la suppression: " . $e->getMessage());
            }
        }

        /**
         * Synchronise les palettes de couleurs pour toutes les images
         *
         * ## OPTIONS
         *
         * [--batch-size=<number>]
         * : Taille des lots pour le traitement
         * ---
         * default: 20
         * ---
         *
         * [--max-images=<number>]
         * : Nombre maximum d'images à traiter (0 = toutes)
         * ---
         * default: 0
         * ---
         *
         * [--force]
         * : Force la recalculation même si une palette existe déjà
         *
         * [--dry-run]
         * : Simulation sans modification
         *
         * ## EXAMPLES
         *
         *     wp photolibrary sync_palettes
         *     wp photolibrary sync_palettes --batch-size=50 --max-images=100 --force
         *     wp photolibrary sync_palettes --dry-run
         *
         * @when after_wp_load
         */
        public function sync_palettes($args, $assoc_args)
        {
            $batch_size = isset($assoc_args['batch-size']) ? (int)$assoc_args['batch-size'] : 20;
            $max_images = isset($assoc_args['max-images']) ? (int)$assoc_args['max-images'] : 0;
            $force = isset($assoc_args['force']);
            $dry_run = isset($assoc_args['dry-run']);

            WP_CLI::line("🎨 Synchronisation des palettes de couleurs...");
            WP_CLI::line("Taille des lots: {$batch_size}");
            WP_CLI::line("Max images: " . ($max_images > 0 ? $max_images : 'Toutes'));
            WP_CLI::line("Force: " . ($force ? 'Oui' : 'Non'));
            WP_CLI::line("Simulation: " . ($dry_run ? 'Oui' : 'Non'));
            WP_CLI::line("");

            try {
                global $wpdb;
                $db = new PL_REST_DB($wpdb);
                $schema = new PhotoLibrarySchema($db);

                // Compter le total d'images disponibles
                $total_query = "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'";
                $total_available = $wpdb->get_var($total_query);

                $total_to_process = $max_images > 0 ? min($max_images, $total_available) : $total_available;

                WP_CLI::line("📊 Images disponibles: {$total_available}");
                WP_CLI::line("📊 Images à traiter: {$total_to_process}");
                WP_CLI::line("");

                // Progression globale
                $progress = WP_CLI\Utils\make_progress_bar('Traitement global', $total_to_process);

                $processed = 0;
                $errors = 0;
                $skipped = 0;
                $offset = 0;

                // Traitement par lot
                while ($processed + $skipped + $errors < $total_to_process) {
                    $current_batch_size = min($batch_size, $total_to_process - ($processed + $skipped + $errors));

                    // Récupérer le lot d'images
                    $pictures = $db->getPicturesForPaletteSync($current_batch_size, $offset, !$force);
                    if (empty($pictures)) {
                        break;
                    }

                    WP_CLI::debug("Traitement du lot {$offset}-" . ($offset + count($pictures)) . " ({" . count($pictures) . "} images)");

                    foreach ($pictures as $picture) {
                        try {
                            // Vérifier si palette existe déjà
                            if (!$force && !empty($picture->palette)) {
                                $skipped++;
                                WP_CLI::debug("⏭️  Palette existante pour l'image {$picture->id}");
                                $progress->tick();
                                continue;
                            }

                            if ($dry_run) {
                                WP_CLI::debug("🔍 [SIMULATION] Traiterait l'image {$picture->id}");
                                $processed++;
                            } else {
                                // Traiter l'image
                                $palette = $schema->getPalette($picture);

                                if ($palette && count($palette) > 0) {
                                    $processed++;
                                    WP_CLI::debug("✅ Palette créée pour l'image {$picture->id} (" . count($palette) . " couleurs)");
                                } else {
                                    $errors++;
                                    WP_CLI::debug("❌ Échec palette pour l'image {$picture->id}");
                                }
                            }

                        } catch (Exception $e) {
                            $errors++;
                            WP_CLI::warning("❌ Erreur pour l'image {$picture->id}: " . $e->getMessage());
                        }

                        $progress->tick();
                    }

                    $offset += count($pictures);

                    // Pause entre les lots pour éviter la surcharge
                    if (!$dry_run && count($pictures) == $batch_size) {
                        sleep(1);
                    }
                }

                $progress->finish();

                WP_CLI::line("");
                if ($dry_run) {
                    WP_CLI::success("✅ Simulation terminée !");
                } else {
                    WP_CLI::success("✅ Synchronisation terminée !");
                }
                WP_CLI::line("📊 Statistiques :");
                WP_CLI::line("  - Images traitées: {$processed}");
                WP_CLI::line("  - Images ignorées: {$skipped}");
                WP_CLI::line("  - Erreurs: {$errors}");
                WP_CLI::line("  - Total: " . ($processed + $skipped + $errors));

            } catch (Exception $e) {
                WP_CLI::error("❌ Erreur générale: " . $e->getMessage());
            }
        }

        /**
         * Nettoie le cache des couleurs
         *
         * ## OPTIONS
         *
         * [--confirm]
         * : Confirme la suppression
         *
         * ## EXAMPLES
         *
         *     wp photolibrary clear-cache --confirm
         */
        public function clear_cache($args, $assoc_args)
        {
            if (!isset($assoc_args['confirm'])) {
                WP_CLI::confirm("Êtes-vous sûr de vouloir vider le cache des couleurs ?");
            }

            try {
                PL_Cache_Manager::flush_all_cache();
                WP_CLI::success("✅ Cache vidé avec succès !");
            } catch (Exception $e) {
                WP_CLI::error("❌ Erreur lors du vidage du cache: " . $e->getMessage());
            }
        }

        /**
         * Affiche des statistiques sur la photothèque
         *
         * ## EXAMPLES
         *
         *     wp photolibrary stats
         */
        public function stats($args, $assoc_args)
        {
            try {
                global $wpdb;
                $db = new PL_REST_DB($wpdb);

                // Statistiques générales
                $total_pictures = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'");
                $pictures_with_palette = $wpdb->get_var("SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%' AND pm.meta_key = 'pl_palette' AND pm.meta_value != ''");

                WP_CLI::line("📊 Statistiques PhotoLibrary");
                WP_CLI::line("========================");
                WP_CLI::line("📷 Images totales: {$total_pictures}");
                WP_CLI::line("🎨 Images avec palette: {$pictures_with_palette}");

                if ($total_pictures > 0) {
                    $percentage = round(($pictures_with_palette / $total_pictures) * 100, 2);
                    WP_CLI::line("📈 Couverture palette: {$percentage}%");
                }

                // Cache stats
                if (class_exists('PL_Cache_Manager')) {
                    WP_CLI::line("💾 Système de cache: Actif");
                }

            } catch (Exception $e) {
                WP_CLI::error("❌ Erreur lors de la récupération des statistiques: " . $e->getMessage());
            }
        }

        /**
         * Recalcule toutes les palettes (supprime puis synchronise)
         *
         * ## OPTIONS
         *
         * [--batch-size=<number>]
         * : Taille des lots pour le traitement
         * ---
         * default: 20
         * ---
         *
         * [--max-images=<number>]
         * : Nombre maximum d'images à traiter (0 = toutes)
         * ---
         * default: 0
         * ---
         *
         * [--confirm]
         * : Confirme l'opération sans demander
         *
         * ## EXAMPLES
         *
         *     wp photolibrary rebuild_palettes
         *     wp photolibrary rebuild_palettes --batch-size=50 --max-images=100 --confirm
         *
         * @when after_wp_load
         */
        public function rebuild_palettes($args, $assoc_args)
        {
            if (!isset($assoc_args['confirm'])) {
                WP_CLI::confirm("Êtes-vous sûr de vouloir recalculer toutes les palettes ? (Cela supprimera d'abord toutes les palettes existantes)");
            }

            WP_CLI::line("🔄 Recalcul complet des palettes...");
            WP_CLI::line("");

            // Étape 1: Supprimer les palettes existantes
            WP_CLI::line("🗑️  Étape 1/2: Suppression des palettes existantes");
            $this->clear_palettes($args, ['confirm' => true]);

            WP_CLI::line("");

            // Étape 2: Synchroniser toutes les palettes
            WP_CLI::line("🎨 Étape 2/2: Recalcul des palettes");
            $sync_args = $assoc_args;
            $sync_args['force'] = true; // Force le recalcul
            unset($sync_args['confirm']); // Retire le confirm pour sync_palettes

            $this->sync_palettes($args, $sync_args);

            WP_CLI::line("");
            WP_CLI::success("✅ Recalcul complet terminé !");
        }

        /**
         * Reconstruit complètement l'index Pinecone avec toutes les palettes
         *
         * ## OPTIONS
         *
         * [--clear-first]
         * : Vide l'index Pinecone avant de le reconstruire
         *
         * [--batch-size=<size>]
         * : Taille des lots pour l'upload (défaut: 100)
         * ---
         * default: 100
         * ---
         *
         * [--dry-run]
         * : Simule l'opération sans modifier Pinecone
         *
         * ## EXAMPLES
         *
         *     wp photolibrary rebuild_pinecone_index
         *     wp photolibrary rebuild_pinecone_index --clear-first
         *     wp photolibrary rebuild_pinecone_index --batch-size=50 --dry-run
         *
         * @when after_wp_load
         */
        public function rebuild_pinecone_index($args, $assoc_args)
        {
            try {
                $clear_first = isset($assoc_args['clear-first']);
                $batch_size = isset($assoc_args['batch-size']) ? intval($assoc_args['batch-size']) : 100;
                $dry_run = isset($assoc_args['dry-run']);

                WP_CLI::line("🔄 Reconstruction de l'index Pinecone...");
                WP_CLI::line("");

                // Initialiser l'index Pinecone
                $color_index = new PL_Color_Search_Index();

                if ($dry_run) {
                    WP_CLI::line("🔬 Mode simulation (dry-run) - Aucune modification ne sera effectuée");
                    WP_CLI::line("");
                }

                // Tester la connexion
                WP_CLI::line("🔗 Test de la connexion Pinecone...");
                $connection_test = $color_index->test_connection();

                if ($connection_test['status'] === 'error') {
                    if (strpos($connection_test['message'], 'PINECONE_API_KEY') !== false) {
                        WP_CLI::error("❌ Clé API Pinecone non configurée. Vérifiez PINECONE_API_KEY dans .env ou wp-config.php");
                    } else {
                        WP_CLI::error("❌ Connexion échouée: " . $connection_test['message']);
                    }
                    return;
                }

                WP_CLI::success("✅ Connexion Pinecone OK");
                WP_CLI::line("");

                // Statistiques avant nettoyage
                $stats_before = $color_index->get_index_stats();
                WP_CLI::line("📊 Statistiques actuelles de l'index:");
                WP_CLI::line("   Vecteurs totaux: " . $stats_before['total_vectors']);
                WP_CLI::line("");

                // Vider l'index si demandé
                if ($clear_first) {
                    WP_CLI::line("🗑️  Vidage de l'index Pinecone...");
                    if (!$dry_run) {
                        $clear_success = $color_index->clear_index();
                        if ($clear_success) {
                            WP_CLI::success("✅ Index vidé avec succès");
                        } else {
                            WP_CLI::error("❌ Échec du vidage de l'index");
                        }
                    } else {
                        WP_CLI::line("   [SIMULATION] Index serait vidé");
                    }
                    WP_CLI::line("");
                }

                // Récupérer toutes les photos avec palettes
                global $wpdb;
                WP_CLI::line("🔍 Recherche des photos avec palettes...");

                $query = "
                    SELECT p.ID, pm.meta_value as palette_data, p.post_title
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE p.post_type = 'attachment'
                    AND p.post_mime_type LIKE 'image/%'
                    AND pm.meta_key = '_pl_palette'
                    AND pm.meta_value != ''
                    AND pm.meta_value IS NOT NULL
                    ORDER BY p.ID ASC
                ";

                $photos_with_palettes = $wpdb->get_results($query);

                if (empty($photos_with_palettes)) {
                    WP_CLI::error("❌ Aucune photo avec palette trouvée. Exécutez d'abord 'wp photolibrary sync_palettes'");
                    return;
                }

                WP_CLI::success("✅ " . count($photos_with_palettes) . " photos avec palettes trouvées");
                WP_CLI::line("");

                // Préparer les données pour Pinecone
                WP_CLI::line("🎨 Préparation des données couleur...");
                $photos_to_sync = array();
                $processed = 0;
                $skipped = 0;

                $progress = WP_CLI\Utils\make_progress_bar(
                    'Traitement des palettes',
                    count($photos_with_palettes)
                );

                foreach ($photos_with_palettes as $photo) {
                    $progress->tick();

                    $palette = unserialize($photo->palette_data);

                    if (!is_array($palette) || empty($palette)) {
                        $skipped++;
                        continue;
                    }

                    // Extraire la couleur dominante
                    $dominant_color = null;
                    if (isset($palette[0]) && is_array($palette[0]) && count($palette[0]) >= 3) {
                        $dominant_color = $palette[0];
                    } elseif (isset($palette['dominant']) && is_array($palette['dominant'])) {
                        $dominant_color = $palette['dominant'];
                    } elseif (is_array($palette) && count($palette) >= 3 && is_numeric($palette[0])) {
                        $dominant_color = $palette;
                    }

                    if ($dominant_color === null || count($dominant_color) < 3) {
                        $skipped++;
                        continue;
                    }

                    // Valider les valeurs RGB
                    $rgb = array_map('intval', array_slice($dominant_color, 0, 3));
                    if ($rgb[0] < 0 || $rgb[0] > 255 || $rgb[1] < 0 || $rgb[1] > 255 || $rgb[2] < 0 || $rgb[2] > 255) {
                        $skipped++;
                        continue;
                    }

                    $photos_to_sync[] = array(
                        'id' => (int) $photo->ID,
                        'rgb' => $rgb,
                        'metadata' => array(
                            'title' => $photo->post_title ?: '',
                            'uploaded_at' => current_time('mysql'),
                            'rebuild_batch' => date('Y-m-d H:i:s')
                        )
                    );

                    $processed++;
                }

                $progress->finish();

                WP_CLI::line("");
                WP_CLI::line("📈 Résultats du traitement:");
                WP_CLI::line("   Photos traitées: " . $processed);
                WP_CLI::line("   Photos ignorées: " . $skipped);
                WP_CLI::line("");

                if (empty($photos_to_sync)) {
                    WP_CLI::error("❌ Aucune photo valide à synchroniser");
                    return;
                }

                if ($dry_run) {
                    WP_CLI::line("🔬 Simulation - Voici ce qui serait synchronisé:");
                    WP_CLI::line("   Nombre de photos: " . count($photos_to_sync));
                    WP_CLI::line("   Taille des lots: " . $batch_size);
                    WP_CLI::line("   Nombre de lots: " . ceil(count($photos_to_sync) / $batch_size));

                    // Montrer quelques exemples
                    $examples = array_slice($photos_to_sync, 0, 3);
                    WP_CLI::line("");
                    WP_CLI::line("📋 Exemples de données (3 premiers):");
                    foreach ($examples as $example) {
                        WP_CLI::line("   ID " . $example['id'] . ": RGB(" . implode(', ', $example['rgb']) . ")");
                    }

                    WP_CLI::success("✅ Simulation terminée - utilisez sans --dry-run pour exécuter réellement");
                    return;
                }

                // Upload vers Pinecone par lots
                WP_CLI::line("☁️  Upload vers Pinecone...");
                WP_CLI::line("   Taille des lots: " . $batch_size);
                WP_CLI::line("");

                $results = $color_index->batch_upsert_photos($photos_to_sync);

                WP_CLI::line("");
                WP_CLI::line("📊 Résultats de l'upload:");
                WP_CLI::line("   Succès: " . $results['success_count']);
                WP_CLI::line("   Erreurs: " . $results['error_count']);
                WP_CLI::line("");

                // Statistiques finales
                $stats_after = $color_index->get_index_stats();
                WP_CLI::line("📈 Statistiques finales de l'index:");
                WP_CLI::line("   Vecteurs totaux: " . $stats_after['total_vectors']);
                WP_CLI::line("   Remplissage: " . round($stats_after['index_fullness'] * 100, 2) . "%");
                WP_CLI::line("");

                if ($results['error_count'] > 0) {
                    WP_CLI::warning("⚠️  Reconstruction terminée avec " . $results['error_count'] . " erreurs");
                } else {
                    WP_CLI::success("🎉 Index Pinecone reconstruit avec succès!");
                }

            } catch (Exception $e) {
                WP_CLI::error("❌ Erreur lors de la reconstruction: " . $e->getMessage());
            }
        }

        /**
         * Teste et affiche toutes les palettes disponibles pour Pinecone
         *
         * ## OPTIONS
         *
         * [--limit=<number>]
         * : Limite le nombre de résultats affichés
         * ---
         * default: 10
         * ---
         *
         * [--format=<format>]
         * : Format de sortie (table, json, csv)
         * ---
         * default: table
         * options:
         *   - table
         *   - json
         *   - csv
         * ---
         *
         * ## EXAMPLES
         *
         *     wp photolibrary list_palettes_for_pinecone
         *     wp photolibrary list_palettes_for_pinecone --limit=20 --format=json
         *
         * @when after_wp_load
         */
        public function list_palettes_for_pinecone($args, $assoc_args)
        {
            try {
                $limit = isset($assoc_args['limit']) ? intval($assoc_args['limit']) : 10;
                $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';

                WP_CLI::line("🎨 Analyse des palettes pour Pinecone...");
                WP_CLI::line("");

                // Récupérer les photos avec palettes
                global $wpdb;
                $query = "
                    SELECT p.ID, p.post_title, pm.meta_value as palette_data, p.post_date
                    FROM {$wpdb->posts} p
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                    WHERE p.post_type = 'attachment'
                    AND p.post_mime_type LIKE 'image/%'
                    AND pm.meta_key = '_pl_palette'
                    AND pm.meta_value != ''
                    AND pm.meta_value IS NOT NULL
                    ORDER BY p.ID ASC
                    LIMIT " . ($limit * 2); // Récupérer plus pour compenser les palettes invalides

                $photos_with_palettes = $wpdb->get_results($query);

                if (empty($photos_with_palettes)) {
                    WP_CLI::error("❌ Aucune photo avec palette trouvée");
                    return;
                }

                $processed_palettes = array();
                $valid_count = 0;
                $invalid_count = 0;

                foreach ($photos_with_palettes as $photo) {
                    if ($valid_count >= $limit) {
                        break;
                    }

                    $palette = unserialize($photo->palette_data);

                    if (!is_array($palette) || empty($palette)) {
                        $invalid_count++;
                        continue;
                    }

                    // Extraire la couleur dominante
                    $dominant_color = null;
                    if (isset($palette[0]) && is_array($palette[0]) && count($palette[0]) >= 3) {
                        $dominant_color = $palette[0];
                    } elseif (isset($palette['dominant']) && is_array($palette['dominant'])) {
                        $dominant_color = $palette['dominant'];
                    } elseif (is_array($palette) && count($palette) >= 3 && is_numeric($palette[0])) {
                        $dominant_color = $palette;
                    }

                    if ($dominant_color === null || count($dominant_color) < 3) {
                        $invalid_count++;
                        continue;
                    }

                    // Valider les valeurs RGB
                    $rgb = array_map('intval', array_slice($dominant_color, 0, 3));
                    if ($rgb[0] < 0 || $rgb[0] > 255 || $rgb[1] < 0 || $rgb[1] > 255 || $rgb[2] < 0 || $rgb[2] > 255) {
                        $invalid_count++;
                        continue;
                    }

                    $processed_palettes[] = array(
                        'ID' => $photo->ID,
                        'title' => $photo->post_title ?: '(Sans titre)',
                        'date' => $photo->post_date,
                        'rgb_r' => $rgb[0],
                        'rgb_g' => $rgb[1],
                        'rgb_b' => $rgb[2],
                        'rgb_hex' => sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]),
                        'palette_colors' => count($palette)
                    );

                    $valid_count++;
                }

                WP_CLI::line("📊 Résumé des palettes:");
                WP_CLI::line("   Photos valides: " . $valid_count);
                WP_CLI::line("   Photos invalides: " . $invalid_count);
                WP_CLI::line("   Total trouvées: " . count($photos_with_palettes));
                WP_CLI::line("");

                if (empty($processed_palettes)) {
                    WP_CLI::error("❌ Aucune palette valide trouvée");
                    return;
                }

                if ($format === 'json') {
                    WP_CLI::line(json_encode($processed_palettes, JSON_PRETTY_PRINT));
                } elseif ($format === 'csv') {
                    // En-têtes CSV
                    WP_CLI::line('ID,Title,Date,R,G,B,Hex,Palette_Colors');
                    foreach ($processed_palettes as $palette) {
                        WP_CLI::line(sprintf(
                            '%d,"%s",%s,%d,%d,%d,%s,%d',
                            $palette['ID'],
                            str_replace('"', '""', $palette['title']),
                            $palette['date'],
                            $palette['rgb_r'],
                            $palette['rgb_g'],
                            $palette['rgb_b'],
                            $palette['rgb_hex'],
                            $palette['palette_colors']
                        ));
                    }
                } else {
                    // Format table par défaut
                    WP_CLI\Utils\format_items('table', $processed_palettes, array(
                        'ID',
                        'title',
                        'rgb_r',
                        'rgb_g',
                        'rgb_b',
                        'rgb_hex',
                        'palette_colors'
                    ));
                }

                WP_CLI::success("✅ Analyse terminée - " . $valid_count . " palettes prêtes pour Pinecone");

            } catch (Exception $e) {
                WP_CLI::error("❌ Erreur lors de l'analyse: " . $e->getMessage());
            }
        }

        /**
         * Test de la connexion Pinecone
         *
         * ## EXAMPLES
         *
         *     wp photolibrary test_pinecone
         */
        public function test_pinecone($args, $assoc_args)
        {
            try {
                WP_CLI::line("🔍 Test de la connexion Pinecone...");

                // Test basique pour vérifier que les classes existent
                if (class_exists('PL_Color_Search_Index')) {
                    WP_CLI::success("✅ Classe PL_Color_Search_Index trouvée !");
                } else {
                    WP_CLI::error("❌ Classe PL_Color_Search_Index introuvable");
                }

            } catch (Exception $e) {
                WP_CLI::error("❌ Erreur Pinecone: " . $e->getMessage());
            }
        }
    }

    // Enregistrer les commandes
    WP_CLI::add_command('photolibrary', 'PhotoLibrary_CLI_Commands');
}
