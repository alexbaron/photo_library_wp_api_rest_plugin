<?php

/**
 * Class PL_React_Shortcode
 *
 * Handles the shortcode to display React app in WordPress pages
 */
class PL_React_Shortcode
{
    /**
     * Render the React app via shortcode
     */
    public static function render($atts = [], $content = null, $tag = '')
    {
        $plugin_root = dirname(dirname(__DIR__));
        $index_file = $plugin_root . '/public/index.html';
        $site_url = site_url();

        if (!file_exists($index_file)) {
            return '<div style="padding: 20px; background: #fee; border: 1px solid #f00;">Erreur: Fichier index.html non trouvé à ' . $index_file . '</div>';
        }

        $content = file_get_contents($index_file);

        if ($content === false) {
            return '<div style="padding: 20px; background: #fee; border: 1px solid #f00;">Erreur: Impossible de lire le fichier index.html</div>';
        }

        // Remplacer les chemins des assets
        // Pattern 1: Chemins absolus avec le chemin complet du plugin
        $content = preg_replace_callback(
            '/href="\/wp-content\/plugins\/photo_library_wp_api_rest_plugin\/public\/assets\/([^"]*\.css)"/',
            function($matches) use ($site_url) {
                $filename = $matches[1];
                return 'href="' . $site_url . '?pl_css=' . urlencode($filename) . '"';
            },
            $content
        );

        // Pattern 2: Chemins relatifs simples /assets/ (générés par React)
        $content = preg_replace_callback(
            '/href="\/assets\/([^"]*\.css)"/',
            function($matches) use ($site_url) {
                $filename = $matches[1];
                return 'href="' . $site_url . '?pl_css=' . urlencode($filename) . '"';
            },
            $content
        );

        // Pattern 1: Chemins absolus avec le chemin complet du plugin
        $content = preg_replace_callback(
            '/src="\/wp-content\/plugins\/photo_library_wp_api_rest_plugin\/public\/assets\/([^"]*\.js)"/',
            function($matches) use ($site_url) {
                $filename = $matches[1];
                return 'src="' . $site_url . '?pl_js=' . urlencode($filename) . '"';
            },
            $content
        );

        // Pattern 2: Chemins relatifs simples /assets/ (générés par React)
        $content = preg_replace_callback(
            '/src="\/assets\/([^"]*\.js)"/',
            function($matches) use ($site_url) {
                $filename = $matches[1];
                error_log('Photo Library Shortcode: Converting JS asset: /assets/' . $matches[1] . ' -> ?pl_js=' . $matches[1]);
                return 'src="' . $site_url . '?pl_js=' . urlencode($filename) . '"';
            },
            $content
        );

        // Debug: log du contenu HTML pour voir les patterns
        error_log('Photo Library Shortcode: HTML content preview: ' . substr($content, 0, 500));

        // Extraire uniquement le contenu du body et les scripts
        preg_match('/<body[^>]*>(.*?)<\/body>/is', $content, $body_matches);
        $body_content = $body_matches[1] ?? '';

        // Extraire les liens CSS et JS du head
        preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]*>/i', $content, $css_matches);
        preg_match_all('/<script[^>]*src=[^>]*><\/script>/i', $content, $js_matches);

        $output = '';

        // Ajouter les CSS
        foreach ($css_matches[0] as $css) {
            $output .= $css . "\n";
        }

        // Ajouter le contenu du body
        $output .= $body_content;
				// Ajouter les JS
        foreach ($js_matches[0] as $js) {
            $output .= $js . "\n";
        }

        return $output;
    }
}
