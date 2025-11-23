<?php

/**
 * Pinecone Configuration Manager
 *
 * Gère la configuration et les paramètres de Pinecone pour le plugin Photo Library
 */
class PineconeConfig
{
    /**
     * Option keys dans WordPress
     */
    private const OPTION_API_KEY = 'pinecone_api_key';
    private const OPTION_HOST = 'pinecone_host';
    private const OPTION_INDEX_NAME = 'pinecone_index_name';
    private const OPTION_ENVIRONMENT = 'pinecone_environment';

    /**
     * Get Pinecone API key
     *
     * @return string|null
     */
    public static function getApiKey(): ?string
    {
        $api_key = get_option(self::OPTION_API_KEY);

        // Essayer aussi les variables d'environnement
        if (empty($api_key)) {
            $api_key = $_ENV['PINECONE_API_KEY'] ?? getenv('PINECONE_API_KEY');
        }

        return !empty($api_key) ? $api_key : null;
    }

    /**
     * Set Pinecone API key
     *
     * @param string $api_key
     * @return bool
     */
    public static function setApiKey(string $api_key): bool
    {
        return update_option(self::OPTION_API_KEY, sanitize_text_field($api_key));
    }

    /**
     * Get Pinecone host
     *
     * @return string|null
     */
    public static function getHost(): ?string
    {
        $host = get_option(self::OPTION_HOST);

        // Essayer aussi les variables d'environnement
        if (empty($host)) {
            $host = $_ENV['PINECONE_HOST'] ?? getenv('PINECONE_HOST');
        }

        return !empty($host) ? $host : null;
    }

    /**
     * Set Pinecone host
     *
     * @param string $host
     * @return bool
     */
    public static function setHost(string $host): bool
    {
        // Nettoyer l'URL (enlever protocole si présent)
        $host = preg_replace('/^https?:\/\//', '', $host);
        return update_option(self::OPTION_HOST, sanitize_text_field($host));
    }

    /**
     * Get index name
     *
     * @return string
     */
    public static function getIndexName(): string
    {
        $index_name = get_option(self::OPTION_INDEX_NAME);
        return !empty($index_name) ? $index_name : 'photo-library-index';
    }

    /**
     * Set index name
     *
     * @param string $index_name
     * @return bool
     */
    public static function setIndexName(string $index_name): bool
    {
        return update_option(self::OPTION_INDEX_NAME, sanitize_text_field($index_name));
    }

    /**
     * Get environment
     *
     * @return string
     */
    public static function getEnvironment(): string
    {
        $environment = get_option(self::OPTION_ENVIRONMENT);
        return !empty($environment) ? $environment : 'us-east-1-aws';
    }

    /**
     * Set environment
     *
     * @param string $environment
     * @return bool
     */
    public static function setEnvironment(string $environment): bool
    {
        return update_option(self::OPTION_ENVIRONMENT, sanitize_text_field($environment));
    }

    /**
     * Check if Pinecone is configured
     *
     * @return bool
     */
    public static function isConfigured(): bool
    {
        $api_key = self::getApiKey();
        $host = self::getHost();

        return !empty($api_key) && !empty($host);
    }

    /**
     * Get all configuration as array
     *
     * @return array
     */
    public static function getAllConfig(): array
    {
        return [
            'api_key' => self::getApiKey(),
            'host' => self::getHost(),
            'index_name' => self::getIndexName(),
            'environment' => self::getEnvironment(),
            'configured' => self::isConfigured()
        ];
    }

    /**
     * Validate API key format
     *
     * @param string $api_key
     * @return bool
     */
    public static function validateApiKey(string $api_key): bool
    {
        // Les clés API Pinecone commencent généralement par 'pc-'
        return preg_match('/^pc-[a-zA-Z0-9_-]+$/', $api_key) === 1;
    }

    /**
     * Validate host format
     *
     * @param string $host
     * @return bool
     */
    public static function validateHost(string $host): bool
    {
        // Vérifier le format de l'host Pinecone
        return preg_match('/^[a-zA-Z0-9-]+\.svc\.[a-zA-Z0-9-]+\.pinecone\.io$/', $host) === 1;
    }

    /**
     * Reset all configuration
     *
     * @return bool
     */
    public static function reset(): bool
    {
        $success = true;
        $success &= delete_option(self::OPTION_API_KEY);
        $success &= delete_option(self::OPTION_HOST);
        $success &= delete_option(self::OPTION_INDEX_NAME);
        $success &= delete_option(self::OPTION_ENVIRONMENT);

        return $success;
    }

    /**
     * Add admin page for Pinecone settings
     *
     * @return void
     */
    public static function addAdminPage(): void
    {
        add_action('admin_menu', function () {
            add_options_page(
                'Pinecone Configuration',
                'Pinecone Settings',
                'manage_options',
                'pinecone-settings',
                [self::class, 'renderAdminPage']
            );
        });

        // Enregistrer les paramètres
        add_action('admin_init', [self::class, 'registerSettings']);
    }

    /**
     * Register WordPress settings
     *
     * @return void
     */
    public static function registerSettings(): void
    {
        register_setting('pinecone_settings', self::OPTION_API_KEY, [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('pinecone_settings', self::OPTION_HOST, [
            'sanitize_callback' => function ($host) {
                return preg_replace('/^https?:\/\//', '', sanitize_text_field($host));
            }
        ]);
        register_setting('pinecone_settings', self::OPTION_INDEX_NAME, [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
        register_setting('pinecone_settings', self::OPTION_ENVIRONMENT, [
            'sanitize_callback' => 'sanitize_text_field'
        ]);
    }

    /**
     * Render admin page
     *
     * @return void
     */
    public static function renderAdminPage(): void
    {
        if (isset($_POST['test_connection'])) {
            self::testConnectionFromAdmin();
        }

        $config = self::getAllConfig();
        ?>
		<div class="wrap">
			<h1>Pinecone Configuration</h1>

			<form method="post" action="options.php">
				<?php settings_fields('pinecone_settings'); ?>
				<?php do_settings_sections('pinecone_settings'); ?>

				<table class="form-table">
					<tr>
						<th scope="row">API Key</th>
						<td>
							<input type="password" name="<?php echo self::OPTION_API_KEY; ?>"
								   value="<?php echo esc_attr($config['api_key']); ?>"
								   class="regular-text" placeholder="pc-xxxxxxxxxxxxxxxx" />
							<p class="description">Your Pinecone API key (starts with 'pc-')</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Index Host</th>
						<td>
							<input type="text" name="<?php echo self::OPTION_HOST; ?>"
								   value="<?php echo esc_attr($config['host']); ?>"
								   class="regular-text" placeholder="your-index-abc123.svc.aped-4627-b74a.pinecone.io" />
							<p class="description">Your Pinecone index host URL</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Index Name</th>
						<td>
							<input type="text" name="<?php echo self::OPTION_INDEX_NAME; ?>"
								   value="<?php echo esc_attr($config['index_name']); ?>"
								   class="regular-text" placeholder="photo-library-index" />
							<p class="description">Name of your Pinecone index</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Environment</th>
						<td>
							<select name="<?php echo self::OPTION_ENVIRONMENT; ?>">
								<?php
                                $environments = [
                                    'us-east-1-aws' => 'US East 1 (AWS)',
                                    'us-west-2-aws' => 'US West 2 (AWS)',
                                    'eu-west-1-aws' => 'EU West 1 (AWS)',
                                    'asia-northeast1-gcp' => 'Asia Northeast 1 (GCP)',
                                ];
        foreach ($environments as $value => $label) {
            $selected = selected($config['environment'], $value, false);
            echo "<option value='{$value}' {$selected}>{$label}</option>";
        }
        ?>
							</select>
							<p class="description">Pinecone environment/region</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr>

			<h2>Connection Test</h2>
			<form method="post">
				<?php wp_nonce_field('test_pinecone_connection'); ?>
				<p>
					<input type="submit" name="test_connection" class="button" value="Test Connection" />
				</p>
			</form>

			<?php if ($config['configured']): ?>
				<div class="notice notice-success">
					<p><strong>Pinecone is configured!</strong> You can now use semantic search for your photos.</p>
				</div>
			<?php else: ?>
				<div class="notice notice-warning">
					<p><strong>Configuration incomplete.</strong> Please fill in the API key and host above.</p>
				</div>
			<?php endif; ?>

			<h2>Usage Instructions</h2>
			<ol>
				<li>Create a Pinecone account at <a href="https://pinecone.io" target="_blank">pinecone.io</a></li>
				<li>Create a new index with integrated embeddings (recommended: llama-text-embed-v2)</li>
				<li>Get your API key from the Pinecone console</li>
				<li>Copy your index host URL (found in index details)</li>
				<li>Fill in the configuration above and save</li>
				<li>Test the connection to verify everything works</li>
			</ol>
		</div>
		<?php
    }

    /**
     * Test connection from admin interface
     *
     * @return void
     */
    private static function testConnectionFromAdmin(): void
    {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'test_pinecone_connection')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Charger les classes nécessaires
        require_once plugin_dir_path(__FILE__) . 'class.pinecone-client.php';
        require_once plugin_dir_path(__FILE__) . 'class.photo-library-pinecone.php';

        $pinecone = new PhotoLibraryPinecone();

        if ($pinecone->testConnection()) {
            $stats = $pinecone->getIndexStats();
            $message = 'Connection successful!';
            if ($stats) {
                $message .= ' Index contains ' . number_format($stats['total_vectors']) . ' vectors.';
            }
            add_settings_error('pinecone_settings', 'connection_success', $message, 'success');
        } else {
            add_settings_error(
                'pinecone_settings',
                'connection_failed',
                'Connection failed. Please check your API key and host.',
                'error'
            );
        }
    }
}

// Initialiser la configuration si nous sommes dans l'admin
if (is_admin()) {
    PineconeConfig::addAdminPage();
}
