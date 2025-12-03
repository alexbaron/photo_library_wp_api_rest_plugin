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
    public const CACHE_PREFIX = 'photo_library_';

    /**
     * Groupe de cache pour organiser les données
     */
    public const CACHE_GROUP = 'photo_library';

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
     * Récupère les mots-clés avec cache
     *
     * @return array|false Données en cache ou false si pas de cache
     */
    public static function get_keywords_cached()
    {
        $cache_key = self::CACHE_PREFIX . 'keywords';
        return wp_cache_get($cache_key, self::CACHE_GROUP);
    }

    /**
     * Met en cache les mots-clés
     *
     * @param array $keywords Données des mots-clés à mettre en cache
     * @return bool True si mis en cache avec succès
     */
    public static function set_keywords_cache($keywords): bool
    {
        $cache_key = self::CACHE_PREFIX . 'keywords';
        return wp_cache_set(
            $cache_key,
            $keywords,
            self::CACHE_GROUP,
            self::CACHE_DURATIONS['keywords']
        );
    }

    /**
     * Récupère toutes les images avec cache
     *
     * @param int $offset Offset pour la pagination
     * @return array|false Données en cache ou false si pas de cache
     */
    public static function get_pictures_all_cached($offset = 0)
    {
        $cache_key = self::CACHE_PREFIX . 'pictures_all_' . $offset;
        return wp_cache_get($cache_key, self::CACHE_GROUP);
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
        $cache_key = self::CACHE_PREFIX . 'pictures_all_' . $offset;
        return wp_cache_set(
            $cache_key,
            $pictures,
            self::CACHE_GROUP,
            self::CACHE_DURATIONS['pictures_all']
        );
    }

    /**
     * Récupère une image spécifique avec cache
     *
     * @param int $id ID de l'image
     * @return array|false Données en cache ou false si pas de cache
     */
    public static function get_picture_by_id_cached($id)
    {
        $cache_key = self::CACHE_PREFIX . 'picture_' . $id;
        return wp_cache_get($cache_key, self::CACHE_GROUP);
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
        $cache_key = self::CACHE_PREFIX . 'picture_' . $id;
        return wp_cache_set(
            $cache_key,
            $picture,
            self::CACHE_GROUP,
            self::CACHE_DURATIONS['picture_data']
        );
    }

    /**
     * Récupère les résultats de recherche avec cache
     *
     * @param array $keywords Mots-clés de recherche
     * @return array|false Données en cache ou false si pas de cache
     */
    public static function get_search_results_cached($keywords)
    {
        $cache_key = self::CACHE_PREFIX . 'search_' . md5(serialize($keywords));
        return wp_cache_get($cache_key, self::CACHE_GROUP);
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
        $cache_key = self::CACHE_PREFIX . 'search_' . md5(serialize($keywords));
        return wp_cache_set(
            $cache_key,
            $results,
            self::CACHE_GROUP,
            self::CACHE_DURATIONS['search']
        );
    }

    /**
     * Récupère la hiérarchie avec cache
     *
     * @return array|false Données en cache ou false si pas de cache
     */
    public static function get_hierarchy_cached()
    {
        $cache_key = self::CACHE_PREFIX . 'hierarchy';
        return wp_cache_get($cache_key, self::CACHE_GROUP);
    }

    /**
     * Met en cache la hiérarchie
     *
     * @param array $hierarchy Données de hiérarchie à mettre en cache
     * @return bool True si mis en cache avec succès
     */
    public static function set_hierarchy_cache($hierarchy): bool
    {
        $cache_key = self::CACHE_PREFIX . 'hierarchy';
        return wp_cache_set(
            $cache_key,
            $hierarchy,
            self::CACHE_GROUP,
            self::CACHE_DURATIONS['hierarchy']
        );
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
        // WordPress ne permet pas de vider un groupe spécifique facilement
        // On utilise donc une approche alternative avec les clés connues
        $keys_to_delete = array(
            'keywords',
            'hierarchy',
            'pictures_all_0', // Première page
            // Note: on ne peut pas facilement supprimer toutes les clés dynamiques
        );

        $success = true;
        foreach ($keys_to_delete as $key) {
            $cache_key = self::CACHE_PREFIX . $key;
            if (!wp_cache_delete($cache_key, self::CACHE_GROUP)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Invalide le cache pour une image spécifique
     *
     * @param int $id ID de l'image
     * @return bool True si invalidation réussie
     */
    public static function flush_picture_cache($id): bool
    {
        $cache_key = self::CACHE_PREFIX . 'picture_' . $id;
        return wp_cache_delete($cache_key, self::CACHE_GROUP);
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
