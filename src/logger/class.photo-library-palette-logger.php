<?php

/**
 * PhotoLibrary Logger - Système de logs pour les palettes de couleurs
 */
class PL_Palette_Logger
{
    private static $log_file = null;
    private static $instance = null;

    public function __construct()
    {
        self::$log_file = WP_CONTENT_DIR . '/photo-library-palettes.log';
    }

    /**
     * Singleton pattern
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log la création d'une palette
     *
     * @param int $image_id ID de l'image
     * @param array $palette Palette de couleurs extraite
     * @param array $image_dimensions Dimensions de l'image [width, height]
     * @param array $area_dimensions Dimensions de la zone analysée [x, y, width, height]
     * @param string $method Méthode d'extraction utilisée
     * @param float $processing_time Temps de traitement en secondes
     */
    public function log_palette_creation($image_id, $palette, $image_dimensions, $area_dimensions = null, $method = 'default', $processing_time = 0)
    {
        $log_entry = [
            'timestamp' => current_time('Y-m-d H:i:s'),
            'image_id' => $image_id,
            'image_width' => $image_dimensions['width'] ?? 0,
            'image_height' => $image_dimensions['height'] ?? 0,
            'area_x' => $area_dimensions['x'] ?? 0,
            'area_y' => $area_dimensions['y'] ?? 0,
            'area_width' => $area_dimensions['width'] ?? $image_dimensions['width'] ?? 0,
            'area_height' => $area_dimensions['height'] ?? $image_dimensions['height'] ?? 0,
            'palette_colors_count' => is_array($palette) ? count($palette) : 0,
            'palette' => $palette,
            'method' => $method,
            'processing_time' => round($processing_time, 4),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];

        $this->write_log_entry($log_entry);
    }

    /**
     * Log une erreur lors de l'extraction de palette
     */
    public function log_palette_error($image_id, $error_message, $image_dimensions = null)
    {
        $log_entry = [
            'timestamp' => current_time('Y-m-d H:i:s'),
            'type' => 'ERROR',
            'image_id' => $image_id,
            'image_width' => $image_dimensions['width'] ?? 0,
            'image_height' => $image_dimensions['height'] ?? 0,
            'error' => $error_message,
            'memory_usage' => memory_get_usage(true)
        ];

        $this->write_log_entry($log_entry);
    }

    /**
     * Écrit une entrée dans le fichier de log
     */
    private function write_log_entry($log_entry)
    {
        $log_line = json_encode($log_entry) . PHP_EOL;

        // Écriture thread-safe
        file_put_contents(self::$log_file, $log_line, FILE_APPEND | LOCK_EX);

        // Rotation des logs si le fichier devient trop volumineux (> 50MB)
        $this->rotate_log_if_needed();
    }

    /**
     * Rotation des logs si nécessaire
     */
    private function rotate_log_if_needed()
    {
        if (!file_exists(self::$log_file)) {
            return;
        }

        $max_size = 50 * 1024 * 1024; // 50MB

        if (filesize(self::$log_file) > $max_size) {
            $backup_file = self::$log_file . '.' . date('Y-m-d-H-i-s');
            rename(self::$log_file, $backup_file);

            // Garder seulement les 5 derniers fichiers de backup
            $this->cleanup_old_backups();
        }
    }

    /**
     * Nettoie les anciens backups
     */
    private function cleanup_old_backups()
    {
        $log_dir = dirname(self::$log_file);
        $log_basename = basename(self::$log_file);

        $backup_files = glob($log_dir . '/' . $log_basename . '.*');

        if (count($backup_files) > 5) {
            // Trier par date de modification
            usort($backup_files, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Supprimer les plus anciens
            $files_to_delete = array_slice($backup_files, 0, count($backup_files) - 5);
            foreach ($files_to_delete as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Lecture des logs avec filtres
     */
    public function read_logs($limit = 100, $image_id = null, $date_from = null, $date_to = null)
    {
        if (!file_exists(self::$log_file)) {
            return [];
        }

        $lines = file(self::$log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = [];

        // Lire en ordre inverse (plus récent en premier)
        $lines = array_reverse($lines);

        foreach ($lines as $line) {
            $log_entry = json_decode($line, true);

            if (!$log_entry) {
                continue;
            }

            // Filtres
            if ($image_id && $log_entry['image_id'] != $image_id) {
                continue;
            }

            if ($date_from && $log_entry['timestamp'] < $date_from) {
                continue;
            }

            if ($date_to && $log_entry['timestamp'] > $date_to) {
                continue;
            }

            $logs[] = $log_entry;

            if (count($logs) >= $limit) {
                break;
            }
        }

        return $logs;
    }

    /**
     * Statistiques des logs
     */
    public function get_log_stats()
    {
        $logs = $this->read_logs(1000); // Analyser les 1000 derniers logs

        $stats = [
            'total_entries' => count($logs),
            'success_count' => 0,
            'error_count' => 0,
            'avg_processing_time' => 0,
            'avg_palette_size' => 0,
            'most_common_image_sizes' => [],
            'processing_times' => []
        ];

        $processing_times = [];
        $palette_sizes = [];
        $image_sizes = [];

        foreach ($logs as $log) {
            if (isset($log['type']) && $log['type'] === 'ERROR') {
                $stats['error_count']++;
            } else {
                $stats['success_count']++;

                if (isset($log['processing_time'])) {
                    $processing_times[] = $log['processing_time'];
                }

                if (isset($log['palette_colors_count'])) {
                    $palette_sizes[] = $log['palette_colors_count'];
                }

                if (isset($log['image_width']) && isset($log['image_height'])) {
                    $size_key = $log['image_width'] . 'x' . $log['image_height'];
                    $image_sizes[$size_key] = ($image_sizes[$size_key] ?? 0) + 1;
                }
            }
        }

        if (!empty($processing_times)) {
            $stats['avg_processing_time'] = array_sum($processing_times) / count($processing_times);
        }

        if (!empty($palette_sizes)) {
            $stats['avg_palette_size'] = array_sum($palette_sizes) / count($palette_sizes);
        }

        // Trier les tailles d'images par fréquence
        arsort($image_sizes);
        $stats['most_common_image_sizes'] = array_slice($image_sizes, 0, 10, true);

        return $stats;
    }

    /**
     * Efface les logs
     */
    public function clear_logs($confirm = false)
    {
        if (!$confirm) {
            return false;
        }

        if (file_exists(self::$log_file)) {
            return unlink(self::$log_file);
        }

        return true;
    }
}
