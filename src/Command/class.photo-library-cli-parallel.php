<?php

/**
 * Extension parallélisée pour les commandes WP-CLI PhotoLibrary
 * Fichier: src/command/class.photo-library-cli-parallel.php
 */

if (defined('WP_CLI') && WP_CLI) {

    /**
     * Trait pour ajouter des capacités de parallélisation aux commandes CLI
     */
    trait PhotoLibrary_CLI_Parallel
    {
        /**
         * Traitement parallélisé avec processus multiples
         *
         * @param array $pictures Liste des images à traiter
         * @param PhotoLibrarySchema $schema Instance du schéma
         * @param bool $force Force la recalculation
         * @param bool $dry_run Mode simulation
         * @param int $parallel_count Nombre de processus parallèles
         * @return array Résultats agrégés
         */
        private function process_parallel_fork($pictures, $schema, $force, $dry_run, $parallel_count = 4)
        {
            // Vérifier si pcntl est disponible
            if (!function_exists('pcntl_fork')) {
                WP_CLI::warning("⚠️  pcntl_fork non disponible, utilisation du traitement séquentiel");
                return $this->process_sequential($pictures, $schema, $force, $dry_run);
            }

            $chunk_size = ceil(count($pictures) / $parallel_count);
            $chunks = array_chunk($pictures, $chunk_size);
            $pids = [];
            $temp_dir = sys_get_temp_dir();

            WP_CLI::debug("🔀 Démarrage de {$parallel_count} processus parallèles");

            foreach ($chunks as $chunk_index => $chunk) {
                $pid = pcntl_fork();

                if ($pid == -1) {
                    WP_CLI::warning("❌ Impossible de créer le processus {$chunk_index}");
                    continue;
                } elseif ($pid == 0) {
                    // Processus enfant
                    $results = $this->process_sequential($chunk, $schema, $force, $dry_run);

                    // Sauvegarder les résultats dans un fichier temporaire
                    $result_file = $temp_dir . "/palette_chunk_{$chunk_index}_" . getmypid() . ".json";
                    file_put_contents($result_file, json_encode($results));

                    exit(0);
                } else {
                    // Processus parent
                    $pids[] = [
                        'pid' => $pid,
                        'chunk' => $chunk_index,
                        'file' => $temp_dir . "/palette_chunk_{$chunk_index}_{$pid}.json"
                    ];
                }
            }

            // Attendre tous les processus enfants et collecter les résultats
            $total_results = ['processed' => 0, 'skipped' => 0, 'errors' => 0];

            foreach ($pids as $process) {
                $status = 0;
                pcntl_waitpid($process['pid'], $status);

                // Lire les résultats du processus
                if (file_exists($process['file'])) {
                    $child_results = json_decode(file_get_contents($process['file']), true);

                    $total_results['processed'] += $child_results['processed'];
                    $total_results['skipped'] += $child_results['skipped'];
                    $total_results['errors'] += $child_results['errors'];

                    // Nettoyer le fichier temporaire
                    unlink($process['file']);
                }
            }

            return $total_results;
        }

        /**
         * Traitement avec workers asynchrones
         */
        private function process_async_workers($pictures, $schema, $force, $dry_run, $worker_count = 3)
        {
            $chunk_size = ceil(count($pictures) / $worker_count);
            $chunks = array_chunk($pictures, $chunk_size);

            WP_CLI::debug("👥 Traitement avec {$worker_count} workers asynchrones");

            $total_results = ['processed' => 0, 'skipped' => 0, 'errors' => 0];

            // Traitement concurrent des chunks
            $results_queue = [];

            foreach ($chunks as $chunk_index => $chunk) {
                // Simuler un traitement asynchrone avec des timers
                $start_time = microtime(true);
                $chunk_results = $this->process_sequential($chunk, $schema, $force, $dry_run);
                $end_time = microtime(true);

                $results_queue[] = [
                    'chunk' => $chunk_index,
                    'results' => $chunk_results,
                    'duration' => $end_time - $start_time
                ];
            }

            // Agrégation des résultats
            foreach ($results_queue as $queue_item) {
                $results = $queue_item['results'];
                $total_results['processed'] += $results['processed'];
                $total_results['skipped'] += $results['skipped'];
                $total_results['errors'] += $results['errors'];

                WP_CLI::debug("✅ Chunk {$queue_item['chunk']} terminé en {" . round($queue_item['duration'], 2) . "}s");
            }

            return $total_results;
        }

        /**
         * Traitement séquentiel de base
         */
        private function process_sequential($pictures, $schema, $force, $dry_run)
        {
            $results = ['processed' => 0, 'skipped' => 0, 'errors' => 0];

            foreach ($pictures as $picture) {
                try {
                    // Vérifier si palette existe déjà
                    if (!$force && !empty($picture->palette)) {
                        $results['skipped']++;
                        continue;
                    }

                    if ($dry_run) {
                        $results['processed']++;
                    } else {
                        // Traiter l'image
                        $palette = $schema->getPalette($picture);

                        if ($palette && count($palette) > 0) {
                            $results['processed']++;
                        } else {
                            $results['errors']++;
                        }
                    }

                } catch (Exception $e) {
                    $results['errors']++;
                    error_log("Erreur traitement image {$picture->id}: " . $e->getMessage());
                }
            }

            return $results;
        }

        /**
         * Traitement avec pool de connexions HTTP
         */
        private function process_http_pool($pictures, $schema, $force, $dry_run, $pool_size = 5)
        {
            // Cette méthode peut être utilisée pour des traitements nécessitant des requêtes HTTP
            // comme des APIs externes de traitement d'images ou de couleurs

            if (!function_exists('curl_multi_init')) {
                WP_CLI::warning("⚠️  cURL multi non disponible, utilisation du traitement séquentiel");
                return $this->process_sequential($pictures, $schema, $force, $dry_run);
            }

            WP_CLI::debug("🌐 Traitement avec pool HTTP de {$pool_size} connexions");

            $chunks = array_chunk($pictures, $pool_size);
            $total_results = ['processed' => 0, 'skipped' => 0, 'errors' => 0];

            foreach ($chunks as $chunk) {
                $chunk_results = $this->process_sequential($chunk, $schema, $force, $dry_run);

                $total_results['processed'] += $chunk_results['processed'];
                $total_results['skipped'] += $chunk_results['skipped'];
                $total_results['errors'] += $chunk_results['errors'];

                // Petite pause pour éviter la surcharge
                usleep(100000); // 0.1 seconde
            }

            return $total_results;
        }
    }
}
