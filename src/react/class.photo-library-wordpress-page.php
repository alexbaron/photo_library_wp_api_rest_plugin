<?php

/**
 * Class PL_WordPress_Page
 *
 * Handles WordPress page integration for React app
 */
class PL_WordPress_Page
{
    /**
     * Initialize the WordPress page integration
     */
    public static function init()
    {
        add_action('template_redirect', array(__CLASS__, 'handle_page_routing'));
        // Intercepter plus tôt dans le cycle WordPress
        add_action('parse_request', array(__CLASS__, 'handle_asset_requests'));
    }

    /**
     * Handle page routing for WordPress integration
     */
    public static function handle_page_routing()
    {
        if (get_query_var('phototeque_react_page')) {
            self::render_react_page();
            exit;
        }
    }

    /**
     * Handle asset requests via GET parameters
     */
    public static function handle_asset_requests($wp)
    {
        // Vérifier les paramètres dans $_REQUEST aussi pour être sûr
        if ((isset($_GET['pl_css']) && !empty($_GET['pl_css'])) ||
            (isset($_REQUEST['pl_css']) && !empty($_REQUEST['pl_css']))) {
            $filename = $_GET['pl_css'] ?? $_REQUEST['pl_css'];
            self::serve_css_asset($filename);
            exit;
        }

        if ((isset($_GET['pl_js']) && !empty($_GET['pl_js'])) ||
            (isset($_REQUEST['pl_js']) && !empty($_REQUEST['pl_js']))) {
            $filename = $_GET['pl_js'] ?? $_REQUEST['pl_js'];
            self::serve_js_asset($filename);
            exit;
        }
    }

    /**
     * Serve CSS asset
     */
    private static function serve_css_asset($filename)
    {
        // Nettoyage des headers précédents
        if (!headers_sent()) {
            header_remove();
        }

        $plugin_dir = plugin_dir_path(dirname(__FILE__, 2)); // Go up two levels to get to plugin root
        $css_file = $plugin_dir . 'public/assets/' . basename($filename);

        if (file_exists($css_file) && pathinfo($filename, PATHINFO_EXTENSION) === 'css') {
            header('Content-Type: text/css; charset=utf-8', true);
            header('Cache-Control: public, max-age=31536000', true);
            header('Access-Control-Allow-Origin: *', true);
            readfile($css_file);
        } else {
            http_response_code(404);
            header('Content-Type: text/css; charset=utf-8', true);
            echo '/* CSS file not found: ' . esc_html($filename) . ' */';
        }
    }

    /**
     * Serve JS asset
     */
    private static function serve_js_asset($filename)
    {
        // Nettoyage des headers précédents
        if (!headers_sent()) {
            header_remove();
        }

        $plugin_dir = plugin_dir_path(dirname(__FILE__, 2)); // Go up two levels to get to plugin root
        $js_file = $plugin_dir . 'public/assets/' . basename($filename);

        if (file_exists($js_file) && pathinfo($filename, PATHINFO_EXTENSION) === 'js') {
            header('Content-Type: application/javascript; charset=utf-8', true);
            header('Cache-Control: public, max-age=31536000', true);
            header('Access-Control-Allow-Origin: *', true);
            readfile($js_file);
        } else {
            http_response_code(404);
            header('Content-Type: application/javascript; charset=utf-8', true);
            echo '// JS file not found: ' . esc_js($filename);
        }
    }

    /**
     * Render simple page without any WordPress theme
     */
    public static function render_react_page()
    {
        $plugin_dir = plugin_dir_path(dirname(__FILE__, 2)); // Go up two levels to get to plugin root
        $index_file = $plugin_dir . 'public/index.html';
        $site_url = site_url();

        // Vérifier si le fichier index.html existe
        if (!file_exists($index_file)) {
            echo '<h1>Erreur: Fichier index.html non trouvé</h1>';
            echo '<p>Le fichier ' . $index_file . ' n\'existe pas.</p>';
            echo '<p>Plugin dir: ' . $plugin_dir . '</p>';
            return;
        }

        // Lire le contenu du fichier index.html
        $content = file_get_contents($index_file);

        if ($content === false) {
            echo '<h1>Erreur: Impossible de lire le fichier index.html</h1>';
            return;
        }

        // Remplacer directement les liens vers les assets par nos URLs personnalisées
        $content = preg_replace_callback(
            '/href="\/assets\/([^"]*\.css)"/',
            function($matches) use ($site_url) {
                $filename = $matches[1];
                return 'href="' . $site_url . '?pl_css=' . urlencode($filename) . '"';
            },
            $content
        );

        $content = preg_replace_callback(
            '/src="\/assets\/([^"]*\.js)"/',
            function($matches) use ($site_url) {
                $filename = $matches[1];
                return 'src="' . $site_url . '?pl_js=' . urlencode($filename) . '"';
            },
            $content
        );

        // Remplacer le favicon
        $content = str_replace(
            'href="/vite.svg"',
            'href="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\'%3E%3C/svg%3E"',
            $content
        );

        // Supprimer le texte "test" du body
        $content = str_replace('test', '', $content);

        // Ajouter une vérification du chargement React
        $debug_script = '
        <script>
            console.log("Photothèque React - Initialisation");

            // Configuration pour l\'app React
            window.REACT_APP_CONFIG = {
                apiUrl: "' . $site_url . '/wp-json/photo-library/v1",
                baseUrl: "' . $site_url . '"
            };

            // Debug du chargement
            let loadAttempts = 0;
            const maxAttempts = 10;

            function checkReactApp() {
                loadAttempts++;
                const root = document.getElementById("root");

                if (root) {
                    if (root.innerHTML.trim() !== "") {
                        console.log("✅ Application React chargée avec succès");
                        return;
                    }

                    if (loadAttempts >= maxAttempts) {
                        console.error("❌ Timeout: React app ne s\'est pas chargée après " + (maxAttempts * 500) + "ms");
                        root.innerHTML = `
                            <div style="padding: 20px; font-family: Arial; background: #fee; border: 1px solid #fcc; border-radius: 4px; margin: 20px;">
                                <h3 style="color: #d00; margin-top: 0;">❌ Erreur de chargement</h3>
                                <p>L\'application React ne s\'est pas chargée.</p>
                                <p><strong>Vérifications à effectuer :</strong></p>
                                <ul>
                                    <li>Ouvrir la console du navigateur (F12)</li>
                                    <li>Vérifier les erreurs de chargement des assets</li>
                                    <li>Vérifier que les fichiers CSS/JS sont accessibles</li>
                                </ul>
                                <p><small>Tentatives: ${loadAttempts}/${maxAttempts}</small></p>
                            </div>
                        `;
                        return;
                    }

                    setTimeout(checkReactApp, 500);
                }
            }

            // Démarrer la vérification après le chargement du DOM
            document.addEventListener("DOMContentLoaded", function() {
                console.log("DOM chargé, vérification React dans 1s...");
                setTimeout(checkReactApp, 1000);
            });

            // Capturer les erreurs de chargement
            window.addEventListener("error", function(e) {
                console.error("Erreur de chargement:", e);
                if (e.target) {
                    console.error("Element en erreur:", e.target.tagName, e.target.src || e.target.href);
                }
            });
        </script>
        </body>';

        $content = str_replace('</body>', $debug_script, $content);

        // Définir les headers appropriés
        header('Content-Type: text/html; charset=utf-8');

        // Afficher le contenu
        echo $content;
    }

}
