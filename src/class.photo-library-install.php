<?php

class PL_Install
{
	private static $table_name;
	private static $version = '1.0';

	public function __construct()
	{
		global $wpdb;
		self::$table_name = $wpdb->prefix . 'pl_keyword';
		self::$version = '1.0';
		error_log('Test creation table keyword' . "\n", 3, ABSPATH . 'debug.log');
		error_log('FILE :' . __FILE__ . "\n", 3, ABSPATH . 'debug.log');

		// Hook pour insérer des données
		// add_action('pl_cron_hook', [$this, 'insert_data']);
	}

	public static function create_table()
	{
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE " . self::$table_name . " (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL UNIQUE,
            PRIMARY KEY  (id),
						CONSTRAINT U_keyword UNIQUE (keyword)
        ) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);

		// Mettre à jour la version de la base de données
		add_option('pl_db_version', self::$version);
	}

	public static function delete_table()
	{
		global $wpdb;

		$sql = "DROP TABLE IF EXISTS " . self::$table_name . ";";
		$wpdb->query($sql);
	}

	public function schedule_cron_job()
	{
		if (!wp_next_scheduled('pl_cron_hook')) {
			wp_schedule_event(time(), 'daily', 'mon_plugin_cron_hook');
		}
	}

	public function insert_data()
	{
		$test = 2;
		// global $wpdb;
		// $data = [
		// 	'keyword' => 'test'
		// ];
		// $wpdb->insert(self::$table_name, $data);
	}
}
