<?php

/**
 * Class PL_React_App
 *
 * Handles the integration of the React application into WordPress
 */
class PL_React_App
{
    /**
     * Initialize the React app integration
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'add_rewrite_rules'));
        add_action('template_redirect', array(__CLASS__, 'handle_react_app'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_react_assets'));
        add_filter('query_vars', array(__CLASS__, 'add_query_vars'));
    }

    /**
     * Add rewrite rules for the React app
     */
    public static function add_rewrite_rules()
    {
        // Route pour l'application React
        add_rewrite_rule(
            '^phototheque/?$',
            'index.php?phototheque_app=1',
            'top'
        );

        // Route pour la page WordPress avec React
        add_rewrite_rule(
            '^phototeque-react/?$',
            'index.php?phototeque_react_page=1',
            'top'
        );

        // Route pour les assets de l'application React
        add_rewrite_rule(
            '^phototheque/assets/(.+)$',
            'index.php?phototheque_asset=$matches[1]',
            'top'
        );

        // Debug: log que les règles ont été ajoutées
        error_log('Photo Library Plugin: Rewrite rules added');
    }    /**
     * Add custom query variables
     */
    public static function add_query_vars($vars)
    {
        $vars[] = 'phototheque_app';
        $vars[] = 'phototheque_asset';
        $vars[] = 'phototeque_react_page';
        return $vars;
    }

    /**
     * Handle React app routing
     */
    public static function handle_react_app()
    {
        global $wp_query;

        // Debug: log les variables de requête
        $asset_var = get_query_var('phototheque_asset');
        if ($asset_var) {
            error_log('Photo Library Plugin: Asset requested: ' . $asset_var);
        }

        // Serve React app
        if (get_query_var('phototheque_app')) {
            self::serve_react_app();
            exit;
        }

        // Serve WordPress page with React
        if (get_query_var('phototeque_react_page')) {
            self::serve_wordpress_react_page();
            exit;
        }

        // Serve React assets
        if (get_query_var('phototheque_asset')) {
            self::serve_react_asset(get_query_var('phototheque_asset'));
            exit;
        }
    }    /**
     * Serve the main React application
     */
    private static function serve_react_app()
    {
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
    private static function serve_react_asset($asset_path)
    {
        $plugin_dir = plugin_dir_path(__FILE__);
        $asset_file = $plugin_dir . '../public/dist/assets/' . $asset_path;

        // Debug
        error_log('Photo Library Plugin: Serving asset - Path: ' . $asset_path . ', File: ' . $asset_file);

        if (file_exists($asset_file)) {
            // Déterminer le type MIME
            $mime_type = self::get_mime_type($asset_path);

            header('Content-Type: ' . $mime_type);
            header('Cache-Control: public, max-age=31536000'); // Cache 1 an

            readfile($asset_file);
        } else {
            http_response_code(404);
            error_log('Photo Library Plugin: Asset not found - ' . $asset_file);
            echo 'Asset non trouvé - Chemin: ' . $asset_file;
        }
    }

    /**
     * Get MIME type for asset files
     */
    private static function get_mime_type($filename)
    {
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
    public static function enqueue_react_assets()
    {
        // Cette méthode peut être utilisée pour enqueue des assets additionnels si nécessaire
        // Pour l'instant, on sert tout via les routes personnalisées
    }

    /**
     * Serve WordPress page with React app
     */
    private static function serve_wordpress_react_page()
    {
        // Déléguer à la classe WordPress Page
        PL_WordPress_Page::render_react_page();
    }

    /**
     * Enqueue assets for WordPress page
     */
    public static function enqueue_page_assets()
    {
        // Styles minimalistes pour la page
        wp_add_inline_style('wp-block-library', self::get_minimal_css());
    }

    /**
     * Output React assets in WordPress page
     */
    private static function output_react_assets()
    {
        $site_url = site_url();
        $plugin_dir = plugin_dir_path(__FILE__);
        $assets_dir = $plugin_dir . '../public/assets/';

        // Trouver les fichiers CSS et JS
        $css_files = glob($assets_dir . 'index-*.css');
        $js_files = glob($assets_dir . 'index-*.js');

        // Injecter le CSS
        if (!empty($css_files)) {
            $css_file = basename($css_files[0]);
            echo '<link rel="stylesheet" href="' . $site_url . '/phototheque/assets/' . $css_file . '">';
        }

        // Injecter le JavaScript
        if (!empty($js_files)) {
            $js_file = basename($js_files[0]);
            echo '<script type="module" src="' . $site_url . '/phototheque/assets/' . $js_file . '"></script>';
        }
    }

    /**
     * Get minimal CSS for the WordPress page
     */
    private static function get_minimal_css()
    {
        return '
        .phototeque-react-main {
            max-width: 100%;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            min-height: 100vh;
        }

        .phototeque-react-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            min-height: calc(100vh - 40px);
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .phototeque-react-title {
            text-align: center;
            color: #333;
            font-family: "Helvetica Neue", Arial, sans-serif;
            font-size: 2.5em;
            font-weight: 300;
            margin: 0 0 30px 0;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }

        .phototeque-react-app {
            width: 100%;
            min-height: 500px;
        }

        /* Masquer les éléments WordPress non nécessaires */
        .site-header,
        .site-navigation,
        .site-footer {
            display: none !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .phototeque-react-container {
                padding: 15px;
                margin: 10px;
            }

            .phototeque-react-title {
                font-size: 2em;
            }
        }
        ';
    }

    /**
     * Flush rewrite rules (à appeler lors de l'activation du plugin)
     */
    public static function flush_rewrite_rules()
    {
        self::add_rewrite_rules();
        flush_rewrite_rules();
    }
}
