<?php

/**
 * PhotoLibrary File Cache Manager
 *
 * Système de cache basé sur les fichiers pour les serveurs mutualisés
 * où les solutions de cache avancées ne sont pas disponibles.
 *
 * @package PhotoLibrary
 * @version 0.2.0
 * @author Alex Baron
 * @since 0.2.0
 */
class PL_File_Cache_Manager
{
    /**
     * Répertoire de cache
     */
    private const CACHE_DIR = WP_CONTENT_DIR . '/cache/photo-library/';

    /**
     * Extension des fichiers de cache
     */
    private const CACHE_EXT = '.cache';

    /**
     * Durées de cache par défaut (en secondes)
     */
    public const CACHE_DURATIONS = array(
        'keywords'     => 3600,    // 1 heure
        'pictures_all' => 1800,    // 30 minutes
        'picture_data' => 7200,    // 2 heures
        'search'       => 900,     // 15 minutes
        'hierarchy'    => 3600,    // 1 heure
        'random'       => 300,     // 5 minutes
    );

    /**
     * Initialise le système de cache
     */
    public static function init(): void
    {
        if (!file_exists(self::CACHE_DIR)) {
            wp_mkdir_p(self::CACHE_DIR);

            // Créer un fichier .htaccess pour sécuriser le répertoire
            $htaccess_content = "# Deny direct access to cache files\n";
            $htaccess_content .= "Order deny,allow\n";
            $htaccess_content .= "Deny from all\n";

            file_put_contents(self::CACHE_DIR . '.htaccess', $htaccess_content);

            // Créer un index.php vide pour plus de sécurité
            file_put_contents(self::CACHE_DIR . 'index.php', '<?php // Silence is golden');
        }
    }

    /**
     * Génère le chemin du fichier de cache
     *
     * @param string $key Clé de cache
     * @return string Chemin complet du fichier
     */
    private static function get_cache_file_path(string $key): string
    {
        return self::CACHE_DIR . md5($key) . self::CACHE_EXT;
    }

    /**
     * Stocke des données en cache
     *
     * @param string $key      Clé de cache
     * @param mixed  $data     Données à mettre en cache
     * @param int    $duration Durée en secondes
     * @return bool True si succès
     */
    public static function set(string $key, $data, int $duration = 3600): bool
    {
        self::init();

        $file_path = self::get_cache_file_path($key);
        $cache_data = array(
            'data'       => $data,
            'expires_at' => time() + $duration,
            'created_at' => time(),
        );

        $serialized_data = serialize($cache_data);

        // Utiliser file_put_contents avec LOCK_EX pour éviter les conflits
        $result = file_put_contents($file_path, $serialized_data, LOCK_EX);

        return $result !== false;
    }

    /**
     * Récupère des données du cache
     *
     * @param string $key Clé de cache
     * @return mixed|false Données ou false si pas trouvé/expiré
     */
    public static function get(string $key)
    {
        $file_path = self::get_cache_file_path($key);

        if (!file_exists($file_path)) {
            return false;
        }

        $content = file_get_contents($file_path);
        if ($content === false) {
            return false;
        }

        $cache_data = unserialize($content);
        if ($cache_data === false) {
            // Fichier corrompu, le supprimer
            unlink($file_path);
            return false;
        }

        // Vérifier l'expiration
        if (time() > $cache_data['expires_at']) {
            unlink($file_path);
            return false;
        }

        return $cache_data['data'];
    }

    /**
     * Supprime une entrée de cache
     *
     * @param string $key Clé de cache
     * @return bool True si supprimé
     */
    public static function delete(string $key): bool
    {
        $file_path = self::get_cache_file_path($key);

        if (file_exists($file_path)) {
            return unlink($file_path);
        }

        return true;
    }

    /**
     * Vide tout le cache
     *
     * @return bool True si succès
     */
    public static function flush_all(): bool
    {
        if (!file_exists(self::CACHE_DIR)) {
            return true;
        }

        $files = glob(self::CACHE_DIR . '*' . self::CACHE_EXT);
        $success = true;

        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Nettoie les fichiers expirés
     *
     * @return int Nombre de fichiers supprimés
     */
    public static function cleanup_expired(): int
    {
        if (!file_exists(self::CACHE_DIR)) {
            return 0;
        }

        $files = glob(self::CACHE_DIR . '*' . self::CACHE_EXT);
        $deleted_count = 0;

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $cache_data = unserialize($content);
            if ($cache_data === false || time() > $cache_data['expires_at']) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }

        return $deleted_count;
    }

    /**
     * Obtient des statistiques sur le cache
     *
     * @return array Statistiques
     */
    public static function get_stats(): array
    {
        if (!file_exists(self::CACHE_DIR)) {
            return array(
                'total_files' => 0,
                'total_size'  => 0,
                'expired'     => 0,
            );
        }

        $files = glob(self::CACHE_DIR . '*' . self::CACHE_EXT);
        $total_size = 0;
        $expired_count = 0;

        foreach ($files as $file) {
            $total_size += filesize($file);

            $content = file_get_contents($file);
            if ($content !== false) {
                $cache_data = unserialize($content);
                if ($cache_data !== false && time() > $cache_data['expires_at']) {
                    $expired_count++;
                }
            }
        }

        return array(
            'total_files' => count($files),
            'total_size'  => $total_size,
            'expired'     => $expired_count,
            'cache_dir'   => self::CACHE_DIR,
        );
    }
}
