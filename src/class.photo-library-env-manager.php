<?php

/**
 * Gestionnaire .env robuste pour production
 */
class PL_Env_Manager
{
    private static $loaded = false;
    private static $vars = [];
    private static $debug_info = [];

    /**
     * Charge le fichier .env avec debug détaillé
     *
     * @param string $env_file_path Chemin vers le fichier .env
     * @return bool Success
     */
    public static function load($env_file_path = null)
    {
        if (self::$loaded) {
            return true;
        }

        // Déterminer le chemin du fichier .env
        if ($env_file_path === null) {
            $env_file_path = __DIR__ . '/.env';
        }

        self::$debug_info['env_file_path'] = $env_file_path;
        self::$debug_info['file_exists'] = file_exists($env_file_path);
        self::$debug_info['is_readable'] = is_readable($env_file_path);
        self::$debug_info['load_time'] = microtime(true);

        if (!file_exists($env_file_path)) {
            self::log_error("Fichier .env introuvable: $env_file_path");
            return false;
        }

        if (!is_readable($env_file_path)) {
            self::log_error("Fichier .env non lisible: $env_file_path");
            return false;
        }

        try {
            $content = file_get_contents($env_file_path);
            if ($content === false) {
                self::log_error("Impossible de lire le contenu du fichier .env");
                return false;
            }

            self::$debug_info['file_size'] = strlen($content);

            // Parser le contenu
            $lines = explode("\n", $content);
            $loaded_count = 0;

            foreach ($lines as $line_num => $line) {
                $line = trim($line);

                // Ignorer lignes vides et commentaires
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                // Vérifier format KEY=VALUE
                if (strpos($line, '=') === false) {
                    continue;
                }

                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Supprimer les guillemets si présents
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                // Stocker la variable
                self::$vars[$key] = $value;

                // Définir dans l'environnement si pas déjà défini
                if (!getenv($key) && !isset($_SERVER[$key])) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }

                $loaded_count++;
            }

            self::$debug_info['variables_loaded'] = $loaded_count;
            self::$loaded = true;

            self::log_info("Fichier .env chargé avec succès: $loaded_count variables");
            return true;

        } catch (Exception $e) {
            self::log_error("Erreur lors du chargement .env: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère une variable d'environnement avec fallback
     *
     * @param string $key Nom de la variable
     * @param mixed $default Valeur par défaut
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        // Ordre de priorité: variables système > .env > défaut

        // 1. Variable système (getenv, $_SERVER, $_ENV)
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        // 2. Variables chargées depuis .env
        if (isset(self::$vars[$key])) {
            return self::$vars[$key];
        }

        // 3. Valeur par défaut
        return $default;
    }

    /**
     * Vérifie si une variable est définie
     */
    public static function has($key)
    {
        return self::get($key) !== null;
    }

    /**
     * Retourne les informations de debug
     */
    public static function get_debug_info()
    {
        return self::$debug_info;
    }

    /**
     * Log des erreurs
     */
    private static function log_error($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("PL_Env_Manager ERROR: $message");
        }
        self::$debug_info['errors'][] = $message;
    }

    /**
     * Log des informations
     */
    private static function log_info($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("PL_Env_Manager INFO: $message");
        }
        self::$debug_info['info'][] = $message;
    }

    /**
     * Force le rechargement du fichier .env
     */
    public static function reload($env_file_path = null)
    {
        self::$loaded = false;
        self::$vars = [];
        self::$debug_info = [];
        return self::load($env_file_path);
    }
}

// Auto-chargement au premier appel
if (!PL_Env_Manager::get_debug_info()) {
    PL_Env_Manager::load();
}
