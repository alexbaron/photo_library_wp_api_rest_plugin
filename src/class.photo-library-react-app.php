<?php

/**
 * Class PL_React_App
 * 
 * Handles the integration of the React application into WordPress
 */
class PL_React_App {
    
    /**
     * Initialize the React app integration
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'add_rewrite_rules'));
        add_action('template_redirect', array(__CLASS__, 'handle_react_app'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_react_assets'));
        add_filter('query_vars', array(__CLASS__, 'add_query_vars'));
    }
    
    /**
     * Add rewrite rules for the React app
     */
    public static function add_rewrite_rules() {
        // Route pour l'application React
        add_rewrite_rule(
            '^phototheque/?$',
            'index.php?phototheque_app=1',
            'top'
        );
        
        // Route pour les assets de l'application React
        add_rewrite_rule(
            '^phototheque/assets/(.+)$',
            'index.php?phototheque_asset=$matches[1]',
            'top'
        );
    }
    
    /**
     * Add custom query variables
     */
    public static function add_query_vars($vars) {
        $vars[] = 'phototheque_app';
        $vars[] = 'phototheque_asset';
        return $vars;
    }
    
    /**
     * Handle React app routing
     */
    public static function handle_react_app() {
        global $wp_query;
        
        // Serve React app
        if (get_query_var('phototheque_app')) {
            self::serve_react_app();
            exit;
        }
        
        // Serve React assets
        if (get_query_var('phototheque_asset')) {
            self::serve_react_asset(get_query_var('phototheque_asset'));
            exit;
        }
    }
    
    /**
     * Serve the main React application
     */
    private static function serve_react_app() {
        $plugin_dir = plugin_dir_path(__FILE__);
        $index_file = $plugin_dir . '../public/index.html';
        
        if (file_exists($index_file)) {
            $content = file_get_contents($index_file);
            
            // Modifier les chemins des assets pour qu'ils pointent vers la bonne URL
            $site_url = site_url();
            $content = str_replace(
                '/assets/',
                $site_url . '/phototheque/assets/',
                $content
            );
            
            // Définir les headers appropriés
            header('Content-Type: text/html; charset=utf-8');
            echo $content;
        } else {
            wp_die('Application React non trouvée');
        }
    }
    
    /**
     * Serve React assets (JS, CSS, etc.)
     */
    private static function serve_react_asset($asset_path) {
        $plugin_dir = plugin_dir_path(__FILE__);
        $asset_file = $plugin_dir . '../public/assets/' . $asset_path;
        
        if (file_exists($asset_file)) {
            // Déterminer le type MIME
            $mime_type = self::get_mime_type($asset_path);
            
            header('Content-Type: ' . $mime_type);
            header('Cache-Control: public, max-age=31536000'); // Cache 1 an
            
            readfile($asset_file);
        } else {
            http_response_code(404);
            echo 'Asset non trouvé';
        }
    }
    
    /**
     * Get MIME type for asset files
     */
    private static function get_mime_type($filename) {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        $mime_types = array(
            'js' => 'application/javascript',
            'css' => 'text/css',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf'
        );
        
        return isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';
    }
    
    /**
     * Enqueue React assets if needed
     */
    public static function enqueue_react_assets() {
        // Cette méthode peut être utilisée pour enqueue des assets additionnels si nécessaire
        // Pour l'instant, on sert tout via les routes personnalisées
    }
    
    /**
     * Flush rewrite rules (à appeler lors de l'activation du plugin)
     */
    public static function flush_rewrite_rules() {
        self::add_rewrite_rules();
        flush_rewrite_rules();
    }
}