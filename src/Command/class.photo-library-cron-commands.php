<?php

/**
 * Exemple de commandes WordPress via hooks
 * Alternative à WP-CLI pour les tâches programmées
 */

class PhotoLibrary_Cron_Commands
{
    public function __construct()
    {
        // Enregistrer les hooks pour les tâches programmées
        add_action('init', [$this, 'maybe_register_cron_jobs']);
        add_action('pl_sync_palettes_cron', [$this, 'sync_palettes_cron']);
        add_action('pl_cleanup_cache_cron', [$this, 'cleanup_cache_cron']);

        // Hooks pour les actions admin
        add_action('wp_ajax_pl_sync_palettes', [$this, 'ajax_sync_palettes']);
        add_action('wp_ajax_pl_clear_cache', [$this, 'ajax_clear_cache']);
    }

    /**
     * Enregistre les tâches cron si elles n'existent pas
     */
    public function maybe_register_cron_jobs()
    {
        // Synchronisation des palettes - quotidienne
        if (!wp_next_scheduled('pl_sync_palettes_cron')) {
            wp_schedule_event(time(), 'daily', 'pl_sync_palettes_cron');
        }

        // Nettoyage cache - hebdomadaire
        if (!wp_next_scheduled('pl_cleanup_cache_cron')) {
            wp_schedule_event(time(), 'weekly', 'pl_cleanup_cache_cron');
        }
    }

    /**
     * Tâche cron : Synchronisation des palettes
     */
    public function sync_palettes_cron()
    {
        error_log('PhotoLibrary: Début synchronisation palettes (cron)');

        try {
            global $wpdb;
            $db = new PL_REST_DB($wpdb);
            $schema = new PhotoLibrarySchema($db);

            // Traiter par batch de 20 images sans palette
            $pictures = $db->getPicturesForPaletteSync(20, 0, true);
            $processed = 0;

            foreach ($pictures as $picture) {
                $palette = $schema->getPalette($picture);
                if ($palette) {
                    $processed++;
                }
            }

            error_log("PhotoLibrary: Palettes synchronisées (cron) - {$processed} images");

        } catch (Exception $e) {
            error_log('PhotoLibrary: Erreur sync palettes (cron): ' . $e->getMessage());
        }
    }

    /**
     * Tâche cron : Nettoyage du cache
     */
    public function cleanup_cache_cron()
    {
        error_log('PhotoLibrary: Début nettoyage cache (cron)');

        try {
            PL_Cache_Manager::flush_all_cache();
            error_log('PhotoLibrary: Cache nettoyé (cron)');
        } catch (Exception $e) {
            error_log('PhotoLibrary: Erreur nettoyage cache (cron): ' . $e->getMessage());
        }
    }

    /**
     * Action AJAX : Synchronisation manuelle des palettes
     */
    public function ajax_sync_palettes()
    {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_ajax_referer('pl_sync_palettes_nonce');

        try {
            global $wpdb;
            $db = new PL_REST_DB($wpdb);
            $schema = new PhotoLibrarySchema($db);

            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
            $pictures = $db->getPictures($limit);
            $processed = 0;

            foreach ($pictures as $picture) {
                $palette = $schema->getPalette($picture);
                if ($palette) {
                    $processed++;
                }
            }

            wp_send_json_success([
                'message' => "Synchronisation terminée",
                'processed' => $processed,
                'total' => count($pictures)
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'Erreur: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Action AJAX : Vider le cache
     */
    public function ajax_clear_cache()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_ajax_referer('pl_clear_cache_nonce');

        try {
            PL_Cache_Manager::flush_all_cache();
            wp_send_json_success(['message' => 'Cache vidé avec succès']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Erreur: ' . $e->getMessage()]);
        }
    }
}

// Initialiser les commandes cron
if (is_admin() || wp_doing_cron()) {
    new PhotoLibrary_Cron_Commands();
}
