<?php

/**
 * PhotoLibrary Cache Manager
 *
 * Gère la mise en cache des données pour optimiser les performances des endpoints API.
 * Utilise le système de cache WordPress (wp_cache) qui fonctionne sur tous les types
 * d'hébergement, y compris les serveurs mutualisés.
 *
 * @package PhotoLibrary
 * @version 0.2.0
 * @author Alex Baron
 * @since 0.2.0
 */
class PL_Cache_Manager
{
    /**
     * Préfixe pour toutes les clés de cache
     */
    public const CACHE_PREFIX = 'pl_';

    /**
     * Groupe de cache pour organiser les données
     */
    public const CACHE_GROUP = 'pl';

    /**
     * Durées de cache par défaut (en secondes)
     */
    public const CACHE_DURATIONS = array(
        'keywords'     => 3600,    // 1 heure - les mots-clés changent rarement
        'pictures_all' => 1800,    // 30 minutes - liste complète des images
        'picture_data' => 7200,    // 2 heures - données d'une image spécifique
        'search'       => 900,     // 15 minutes - résultats de recherche
        'hierarchy'    => 3600,    // 1 heure - hiérarchie des mots-clés
        'random'       => 300,     // 5 minutes - images aléatoires (courte durée)
    );


    /**
     * Cache hybride : essaie wp_cache d'abord, puis file cache
     *
     * @param string $key      Clé de cache (sans préfixe)
     * @param mixed  $data     Données à mettre en cache
     * @param int    $duration Durée en secondes
     * @return bool True si au moins un cache a fonctionné
     */
    private static function set_hybrid_cache(string $key, $data, int $duration): bool
    {
        $cache_key = self::CACHE_PREFIX . $key;

        // Essayer le cache WordPress d'abord (plus rapide)
        $wp_cache_result = wp_cache_set($cache_key, $data, self::CACHE_GROUP, $duration);

        // Essayer le cache fichier comme fallback (persistant)
        $file_cache_result = false;
        if (class_exists('PL_File_Cache_Manager')) {
            $file_cache_result = PL_File_Cache_Manager::set($cache_key, $data, $duration);
        }

        // Succès si au moins un des deux a fonctionné
        return $wp_cache_result || $file_cache_result;
    }

    /**
     * Récupération hybride : essaie wp_cache d'abord, puis file cache
     *
     * @param string $key Clé de cache (sans préfixe)
     * @return mixed|false Données ou false si pas trouvé
     */
    private static function get_hybrid_cache(string $key)
    {
        $cache_key = self::CACHE_PREFIX . $key;

        // Essayer le cache WordPress d'abord (plus rapide)
        $data = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($data !== false) {
            return $data;
        }

        // Fallback vers le cache fichier
        if (class_exists('PL_File_Cache_Manager')) {
            $data = PL_File_Cache_Manager::get($cache_key);

            // Si trouvé dans le file cache, le remettre dans wp_cache pour la prochaine fois
            if ($data !== false) {
                wp_cache_set($cache_key, $data, self::CACHE_GROUP, 300); // 5 min
            }

            return $data;
        }

        return false;
    }

    /**
     * Suppression hybride
     *
     * @param string $key Clé de cache (sans préfixe)
     * @return bool True si supprimé
     */
    private static function delete_hybrid_cache(string $key): bool
    {
        $cache_key = self::CACHE_PREFIX . $key;

        $wp_result = wp_cache_delete($cache_key, self::CACHE_GROUP);

        $file_result = true;
        if (class_exists('PL_File_Cache_Manager')) {
            $file_result = PL_File_Cache_Manager::delete($cache_key);
        }

        return $wp_result && $file_result;
    }

    public static function set_test_cache(): bool
    {
        return self::set_hybrid_cache('test', ['test_data_pl'], 7200);
    }

    public static function get_test_cache()
    {
        return self::get_hybrid_cache('test');
    }

    /**
     * Récupère les mots-clés avec cache
     *
     * @return array|false Données en cache ou false si pas de cache
     */
    public static function get_keywords_cached()
    {
        return self::get_hybrid_cache('keywords');
    }

    /**
     * Met en cache les mots-clés
     *
     * @param array $keywords Données des mots-clés à mettre en cache
     * @return bool True si mis en cache avec succès
     */
    public static function set_keywords_cache($keywords): bool
    {
        return self::set_hybrid_cache('keywords', $keywords, self::CACHE_DURATIONS['keywords']);
    }

    /**
     * Récupère toutes les images avec cache
     *
     * @param int $offset Offset pour la pagination
     * @return array|false Données en cache ou false si pas de cache
     */
    public static function get_pictures_all_cached($offset = 0)
    {
        return self::get_hybrid_cache('pictures_all_' . $offset);
    }

    /**
     * Met en cache toutes les images
     *
     * @param array $pictures Données des images à mettre en cache
     * @param int   $offset   Offset pour la pagination
     * @return bool True si mis en cache avec succès
     */
    public static function set_pictures_all_cache($pictures, $offset = 0): bool
    {
        return self::set_hybrid_cache('pictures_all_' . $offset, $pictures, self::CACHE_DURATIONS['pictures_all']);
    }

    /**
     * Récupère une image spécifique avec cache
     *
     * @param int $id ID de l'image
     * @return array|false Données en cache ou false si pas de cache
     */
    public static function get_picture_by_id_cached($id)
    {
        return self::get_hybrid_cache('picture_' . $id);
    }

    /**
     * Met en cache une image spécifique
     *
     * @param int   $id      ID de l'image
     * @param array $picture Données de l'image à mettre en cache
     * @return bool True si mis en cache avec succès
     */
    public static function set_picture_by_id_cache($id, $picture): bool
    {
        return self::set_hybrid_cache('picture_' . $id, $picture, self::CACHE_DURATIONS['picture_data']);
    }

    /**
     * Récupère les résultats de recherche avec cache
     *
     * @param array $keywords Mots-clés de recherche
     * @return array|false Données en cache ou false si pas de cache
     */
    public static function get_search_results_cached($keywords)
    {
        return self::get_hybrid_cache('search_' . md5(serialize($keywords)));
    }

    /**
     * Met en cache les résultats de recherche
     *
     * @param array $keywords Mots-clés de recherche
     * @param array $results  Résultats de recherche à mettre en cache
     * @return bool True si mis en cache avec succès
     */
    public static function set_search_results_cache($keywords, $results): bool
    {
        return self::set_hybrid_cache('search_' . md5(serialize($keywords)), $results, self::CACHE_DURATIONS['search']);
    }

    /**
     * Récupère la hiérarchie avec cache
     *
     * @return array|false Données en cache ou false si pas de cache
     */
    public static function get_hierarchy_cached()
    {
        return self::get_hybrid_cache('hierarchy');
    }

    /**
     * Met en cache la hiérarchie
     *
     * @param array $hierarchy Données de hiérarchie à mettre en cache
     * @return bool True si mis en cache avec succès
     */
    public static function set_hierarchy_cache($hierarchy): bool
    {
        return self::set_hybrid_cache('hierarchy', $hierarchy, self::CACHE_DURATIONS['hierarchy']);
    }

    /**
     * Invalide le cache complet du plugin
     *
     * Utile après ajout/modification d'images ou de mots-clés
     *
     * @return bool True si invalidation réussie
     */
    public static function flush_all_cache(): bool
    {
        // Clés connues à supprimer
        $keys_to_delete = array(
            'keywords',
            'hierarchy',
            'pictures_all_0', // Première page
            'test', // Clé de test
        );

        $wp_success = true;
        $file_success = true;

        // Supprimer du cache WordPress
        foreach ($keys_to_delete as $key) {
            if (!self::delete_hybrid_cache($key)) {
                $wp_success = false;
            }
        }

        // Supprimer tout le cache fichier
        if (class_exists('PL_File_Cache_Manager')) {
            $file_success = PL_File_Cache_Manager::flush_all();
        }

        return $wp_success && $file_success;
    }

    /**
     * Invalide le cache pour une image spécifique
     *
     * @param int $id ID de l'image
     * @return bool True si invalidation réussie
     */
    public static function flush_picture_cache($id): bool
    {
        return self::delete_hybrid_cache('picture_' . $id);
    }

    /**
     * Invalide le cache de recherche
     *
     * Utile après modification des mots-clés ou des images
     *
     * @return bool True si invalidation réussie
     */
    public static function flush_search_cache(): bool
    {
        // Pour les recherches, on ne peut pas facilement identifier toutes les clés
        // On marque comme invalidé via une option WordPress
        return update_option('pl_search_cache_version', time());
    }

    /**
     * Vérifie si le cache de recherche est valide
     *
     * @return bool True si le cache est valide
     */
    public static function is_search_cache_valid(): bool
    {
        $last_flush = get_option('pl_search_cache_version', 0);
        $cache_time = get_option('pl_last_content_update', 0);

        return $last_flush >= $cache_time;
    }

    /**
     * Marque le contenu comme mis à jour
     *
     * Utilisé pour invalider les caches quand le contenu change
     */
    public static function mark_content_updated(): void
    {
        update_option('pl_last_content_update', time());
    }

    /**
     * Obtient des statistiques sur le cache
     *
     * @return array Statistiques du cache
     */
    public static function get_cache_stats(): array
    {
        $stats = array(
            'cache_enabled' => wp_using_ext_object_cache(),
            'cache_type'    => wp_using_ext_object_cache() ? 'external' : 'runtime',
            'timestamps'    => array(
                'last_content_update' => get_option('pl_last_content_update', 0),
                'search_cache_version' => get_option('pl_search_cache_version', 0),
            ),
        );

        return $stats;
    }
}
