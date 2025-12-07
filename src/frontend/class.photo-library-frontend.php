<?php

/**
 * Gestion de l'intégration frontend de l'application React
 */
class PhotoLibrary_Frontend {

    /**
     * Hook WordPress pour initialiser le frontend
     */
    public static function init() {
        add_action('template_redirect', array(__CLASS__, 'load_react_template'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_react_app'));
        add_filter('the_content', array(__CLASS__, 'inject_react_app'), 10, 1);
    }

    /**
     * Charge l'application React sur les bonnes pages
     */
    public static function enqueue_react_app() {
        global $post;

        // Vérifier si nous sommes sur une des pages de la photothèque
        if (is_page() && isset($post->post_name) &&
            (in_array($post->post_name, ['phototeque-react', 'phototeque-js']))) {

            $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
            $dist_path = $plugin_url . 'public/dist/';

            // Lire le fichier index.html pour extraire les assets
            $index_file = plugin_dir_path(dirname(dirname(__FILE__))) . 'public/dist/index.html';

            if (file_exists($index_file)) {
                $content = file_get_contents($index_file);

                // Extraire les fichiers CSS
                if (preg_match_all('/href="([^"]*\.css)"/', $content, $css_matches)) {
                    foreach ($css_matches[1] as $css_file) {
                        $css_url = str_replace('/phototheque/', $dist_path, $css_file);
                        wp_enqueue_style('photolibrary-react-css-' . md5($css_file), $css_url);
                    }
                }

                // Extraire les fichiers JS
                if (preg_match_all('/src="([^"]*\.js)"/', $content, $js_matches)) {
                    foreach ($js_matches[1] as $js_file) {
                        $js_url = str_replace('/phototheque/', $dist_path, $js_file);
                        wp_enqueue_script('photolibrary-react-js-' . md5($js_file), $js_url, array(), null, true);
                    }
                }

                // Ajouter la configuration pour l'API
                wp_localize_script('photolibrary-react-js-' . md5($js_matches[1][0]), 'wpApiSettings', array(
                    'root' => esc_url_raw(rest_url()),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'photolibraryApiUrl' => esc_url_raw(rest_url('photo-library/v1/')),
                ));
            }
        }
    }

    /**
     * Injecte le conteneur React dans le contenu de la page
     */
    public static function inject_react_app($content) {
        global $post;

        if (is_page() && isset($post->post_name) &&
            (in_array($post->post_name, ['phototeque-react', 'phototeque-js']))) {

            // Remplacer le contenu par le div React
            $react_content = '<div id="root"></div>';

            // Si le contenu contient déjà un div root ou phototheque-react-root, le remplacer
            if (strpos($content, 'phototheque-react-root') !== false) {
                $content = str_replace("div id='phototheque-react-root'", 'div id="root"', $content);
            } elseif (strpos($content, 'id="root"') === false) {
                $content .= $react_content;
            }
        }

        return $content;
    }

    /**
     * Charge un template React sans thème WordPress
     */
    public static function load_react_template() {
        global $post;

        if (is_page() && isset($post->post_name) &&
            (in_array($post->post_name, ['phototeque-react', 'phototeque-js']))) {

            // Empêcher WordPress de charger le thème
            remove_all_actions('wp_head');
            remove_all_actions('wp_footer');

            // Réajouter seulement les actions essentielles
            add_action('wp_head', 'wp_enqueue_scripts', 1);
            add_action('wp_head', 'wp_print_styles', 8);
            add_action('wp_head', 'wp_print_head_scripts', 9);
            add_action('wp_footer', 'wp_print_footer_scripts', 20);

            // Charger notre template personnalisé
            self::render_react_template();
            exit;
        }
    }

    /**
     * Rendu du template React personnalisé
     */
    public static function render_react_template() {
        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
        $dist_path = $plugin_url . 'public/dist/';

        // Extraire les assets du fichier index.html
        $index_file = plugin_dir_path(dirname(dirname(__FILE__))) . 'public/dist/index.html';
        $css_files = array();
        $js_files = array();

        if (file_exists($index_file)) {
            $content = file_get_contents($index_file);

            // Extraire les fichiers CSS
            if (preg_match_all('/href="([^"]*\.css)"/', $content, $css_matches)) {
                foreach ($css_matches[1] as $css_file) {
                    $css_files[] = str_replace('/phototheque/', $dist_path, $css_file);
                }
            }

            // Extraire les fichiers JS
            if (preg_match_all('/src="([^"]*\.js)"/', $content, $js_matches)) {
                foreach ($js_matches[1] as $js_file) {
                    $js_files[] = str_replace('/phototheque/', $dist_path, $js_file);
                }
            }
        }

        ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(get_the_title()); ?> - <?php bloginfo('name'); ?></title>

    <?php foreach ($css_files as $css_file): ?>
    <link rel="stylesheet" href="<?php echo esc_url($css_file); ?>">
    <?php endforeach; ?>

    <script>
        window.wpApiSettings = {
            root: '<?php echo esc_url_raw(rest_url()); ?>',
            nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
            photolibraryApiUrl: '<?php echo esc_url_raw(rest_url('photo-library/v1/')); ?>'
        };
    </script>
</head>
<body class="photolibrary-react-app">
    <div id="root"></div>

    <?php foreach ($js_files as $js_file): ?>
    <script type="module" crossorigin src="<?php echo esc_url($js_file); ?>"></script>
    <?php endforeach; ?>
</body>
</html><?php
    }

    /**
     * Obtient l'URL des assets du plugin
     */
    public static function get_plugin_assets_url() {
        return plugin_dir_url(dirname(dirname(__FILE__))) . 'public/dist/assets/';
    }
}
