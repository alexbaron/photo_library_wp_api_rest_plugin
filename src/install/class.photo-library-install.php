<?php

class PL_Install
{
    private static $table_name;
    private static $rgb_distance_table_name;
    private static $version = '1.0';

    public function __construct()
    {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'pl_keyword';
        self::$rgb_distance_table_name = $wpdb->prefix . 'pl_rgb_distance';
        self::$version    = '1.0';
        // error_log('Test creation table keyword' . "\n", 3, ABSPATH . 'debug.log');
        // error_log('FILE :' . __FILE__ . "\n", 3, ABSPATH . 'debug.log');

        // Hook pour insérer des données
        // add_action('pl_cron_hook', [$this, 'insert_data']);
    }

    public static function create_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create pl_keyword table
        $sql = 'CREATE TABLE IF NOT EXISTS ' . self::$table_name . " (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL UNIQUE,
            PRIMARY KEY  (id),
						CONSTRAINT U_keyword UNIQUE (keyword)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Create pl_rgb_distance table
        self::create_rgb_distance_table();

        // Mettre à jour la version de la base de données
        add_option('pl_db_version', self::$version);
    }

    public static function create_rgb_distance_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = 'CREATE TABLE IF NOT EXISTS ' . self::$rgb_distance_table_name . " (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            color1_r tinyint(3) UNSIGNED NOT NULL,
            color1_g tinyint(3) UNSIGNED NOT NULL,
            color1_b tinyint(3) UNSIGNED NOT NULL,
            color2_r tinyint(3) UNSIGNED NOT NULL,
            color2_g tinyint(3) UNSIGNED NOT NULL,
            color2_b tinyint(3) UNSIGNED NOT NULL,
            distance decimal(10,4) NOT NULL,
            algorithm varchar(50) DEFAULT 'euclidean',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_color1 (color1_r, color1_g, color1_b),
            INDEX idx_color2 (color2_r, color2_g, color2_b),
            INDEX idx_distance (distance),
            UNIQUE KEY unique_color_pair (color1_r, color1_g, color1_b, color2_r, color2_g, color2_b, algorithm)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function delete_table()
    {
        global $wpdb;

        // Delete both tables
        $sql1 = 'DROP TABLE IF EXISTS ' . self::$table_name . ';';
        $sql2 = 'DROP TABLE IF EXISTS ' . self::$rgb_distance_table_name . ';';

        $wpdb->query($sql1);
        $wpdb->query($sql2);
    }

    public function schedule_cron_job()
    {
        if (! wp_next_scheduled('pl_cron_hook')) {
            wp_schedule_event(time(), 'daily', 'mon_plugin_cron_hook');
        }
    }

    public function insert_data()
    {
        // global $wpdb;
        // $data = [
        // 'keyword' => 'test'
        // ];
        // $wpdb->insert(self::$table_name, $data);
    }
}
