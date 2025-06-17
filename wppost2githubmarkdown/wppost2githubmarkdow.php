<?php
/**
 * Plugin Name: WpPost2GitHubMarkdown
 * Plugin URI: https://github.com/gl0bal01/wordpress-plugins/wppost2githubmarkdown
 * Description: Automatically sync WordPress posts to GitHub as markdown files using cronjobs with bidirectional sync support.
 * Version: 1.0.0
 * Author: gl0bal01
 * Author URI: https://gl0bal01.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wppost2githubmarkdown
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * Update URI: false
 *
 * @package WpPost2GitHubMarkdown
 */

declare(strict_types=1);

namespace WpPost2GitHubMarkdown;

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPPOST2GITHUBMARKDOWN_VERSION', '1.0.0');
define('WPPOST2GITHUBMARKDOWN_PLUGIN_FILE', __FILE__);
define('WPPOST2GITHUBMARKDOWN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPPOST2GITHUBMARKDOWN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPPOST2GITHUBMARKDOWN_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class following PSR-4 autoloading standards
 */
class WpPost2GitHubMarkdownPlugin
{
    /**
     * Plugin instance
     */
    private static ?self $instance = null;

    /**
     * Database option prefix
     */
    private const OPTION_PREFIX = 'wppost2githubmarkdown_';

    /**
     * Cron hook name
     */
    private const CRON_HOOK = 'wppost2githubmarkdown_cron';

    /**
     * Webhook endpoint namespace
     */
    private const WEBHOOK_NAMESPACE = 'wppost2githubmarkdown/v1';

    /**
     * Webhook endpoint
     */
    private const WEBHOOK_ENDPOINT = 'webhook';

    /**
     * Get plugin instance (Singleton pattern)
     */
    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->init();
    }

    /**
     * Initialize the plugin
     */
    private function init(): void
    {
        // Load text domain for internationalization
        add_action('plugins_loaded', [$this, 'loadTextDomain']);
        
        // Register activation and deactivation hooks
        register_activation_hook(WPPOST2GITHUBMARKDOWN_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(WPPOST2GITHUBMARKDOWN_PLUGIN_FILE, [$this, 'deactivate']);
        
        // Initialize admin interface
        if (is_admin()) {
            add_action('admin_menu', [$this, 'addAdminMenu']);
            add_action('admin_init', [$this, 'registerSettings']);
        }
        
        // Register cron action
        add_action(self::CRON_HOOK, [$this, 'processSyncQueue']);
        
        // Add custom cron interval
        add_filter('cron_schedules', [$this, 'addCustomCronInterval']);
        
        // Initialize REST API for webhooks
        add_action('rest_api_init', [$this, 'registerWebhookEndpoint']);
        
        // Add settings for bidirectional sync
        add_action('admin_init', [$this, 'registerBidirectionalSettings']);
    }

    /**
     * Load plugin text domain
     */
    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'wppost2githubmarkdown',
            false,
            dirname(WPPOST2GITHUBMARKDOWN_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Plugin activation hook
     */
    public function activate(): void
    {
        // Create sync log table
        $this->createSyncLogTable();
        
        // Schedule cron if not already scheduled
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            $interval = $this->getOption('sync_interval', 'hourly');
            wp_schedule_event(time(), $interval, self::CRON_HOOK);
        }
        
        // Set default options
        $this->setDefaultOptions();
    }

    /**
     * Plugin deactivation hook
     */
    public function deactivate(): void
    {
        // Unschedule cron
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Add custom cron interval
     */
    public function addCustomCronInterval(array $schedules): array
    {
        $schedules['every_15_minutes'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => __('Every 15 minutes', 'wppost2githubmarkdown'),
        ];
        
        return $schedules;
    }

    /**
     * Create sync log table
     */
    private function createSyncLogTable(): void
    {
        global $wpdb;
        
        $tableName = $wpdb->prefix . 'wppost2githubmarkdown_log';
        
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            sync_status varchar(20) NOT NULL DEFAULT 'pending',
            github_path varchar(500) DEFAULT NULL,
            last_sync_time datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY sync_status (sync_status),
            KEY last_sync_time (last_sync_time)
        ) {$charset};";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Set default plugin options
     */
    private function setDefaultOptions(): void
    {
        $defaults = [
            'github_token' => '',
            'github_repo' => '',
            'sync_interval' => 'hourly',
            'sync_enabled' => false,
            'sync_published_only' => true,
            'exclude_categories' => [],
            'bidirectional_sync' => false,
            'webhook_secret' => '',
            'webhook_url' => '',
        ];

        foreach ($defaults as $key => $value) {
            if (false === get_option(self::OPTION_PREFIX . $key)) {
                add_option(self::OPTION_PREFIX . $key, $value);
            }
        }
    }

    /**
     * Get plugin option with prefix
     */
    private function getOption(string $key, $default = false)
    {
        return get_option(self::OPTION_PREFIX . $key, $default);
    }

    /**
     * Update plugin option with prefix
     */
    private function updateOption(string $key, $value): bool
    {
        return update_option(self::OPTION_PREFIX . $key, $value);
    }

    /**
     * Add admin menu
     */
    public function addAdminMenu(): void
    {
        add_options_page(
            __('WpPost2GitHubMarkdown Settings', 'wppost2githubmarkdown'),
            __('WpPost2GitHubMarkdown', 'wppost2githubmarkdown'),
            'manage_options',
            'wppost2githubmarkdown',
            [$this, 'renderAdminPage']
        );
    }

    /**
     * Register plugin settings
     */
    public function registerSettings(): void
    {
        // Register settings
        register_setting('wppost2githubmarkdown_settings', self::OPTION_PREFIX . 'github_token', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        register_setting('wppost2githubmarkdown_settings', self::OPTION_PREFIX . 'github_repo', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        register_setting('wppost2githubmarkdown_settings', self::OPTION_PREFIX . 'sync_interval', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        
        register_setting('wppost2githubmarkdown_settings', self::OPTION_PREFIX . 'sync_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        
        register_setting('wppost2githubmarkdown_settings', self::OPTION_PREFIX . 'sync_published_only', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
    }

    /**
     * Register bidirectional sync settings
     */
    public function registerBidirectionalSettings(): void
    {
        register_setting('wppost2githubmarkdown_settings', self::OPTION_PREFIX . 'bidirectional_sync', [
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        
        register_setting('wppost2githubmarkdown_settings', self::OPTION_PREFIX . 'webhook_secret', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }

    /**
     * Render admin page
     */
    public function renderAdminPage(): void
    {
        if (isset($_POST['manual_sync']) && wp_verify_nonce($_POST['_wpnonce'], 'wppost2githubmarkdown_manual')) {
            $this->triggerManualSync();
            echo '<div class="notice notice-success"><p>' . 
                 esc_html__('Manual sync triggered successfully!', 'wppost2githubmarkdown') . '</p></div>';
        }

        $githubToken = $this->getOption('github_token');
        $githubRepo = $this->getOption('github_repo');
        $syncInterval = $this->getOption('sync_interval', 'hourly');
        $syncEnabled = $this->getOption('sync_enabled', false);
        $syncPublishedOnly = $this->getOption('sync_published_only', true);
        $bidirectionalSync = $this->getOption('bidirectional_sync', false);
        $webhookSecret = $this->getOption('webhook_secret');
        $webhookUrl = home_url('/wp-json/' . self::WEBHOOK_NAMESPACE . '/' . self::WEBHOOK_ENDPOINT);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WpPost2GitHubMarkdown Settings', 'wppost2githubmarkdown'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('wppost2githubmarkdown_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="github_token"><?php esc_html_e('GitHub Personal Access Token', 'wppost2githubmarkdown'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="github_token" 
                                   name="<?php echo esc_attr(self::OPTION_PREFIX . 'github_token'); ?>" 
                                   value="<?php echo esc_attr($githubToken); ?>" 
                                   class="regular-text" />
                            <p class="description">
                                <?php esc_html_e('Generate a token at GitHub Settings > Developer settings > Personal access tokens', 'wppost2githubmarkdown'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="github_repo"><?php esc_html_e('GitHub Repository', 'wppost2githubmarkdown'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="github_repo" 
                                   name="<?php echo esc_attr(self::OPTION_PREFIX . 'github_repo'); ?>" 
                                   value="<?php echo esc_attr($githubRepo); ?>" 
                                   class="regular-text" 
                                   placeholder="username/repository-name" />
                            <p class="description">
                                <?php esc_html_e('Format: username/repository-name', 'wppost2githubmarkdown'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="sync_interval"><?php esc_html_e('Sync Interval', 'wppost2githubmarkdown'); ?></label>
                        </th>
                        <td>
                            <select id="sync_interval" name="<?php echo esc_attr(self::OPTION_PREFIX . 'sync_interval'); ?>">
                                <option value="every_15_minutes" <?php selected($syncInterval, 'every_15_minutes'); ?>>
                                    <?php esc_html_e('Every 15 minutes', 'wppost2githubmarkdown'); ?>
                                </option>
                                <option value="hourly" <?php selected($syncInterval, 'hourly'); ?>>
                                    <?php esc_html_e('Hourly', 'wppost2githubmarkdown'); ?>
                                </option>
                                <option value="twicedaily" <?php selected($syncInterval, 'twicedaily'); ?>>
                                    <?php esc_html_e('Twice daily', 'wppost2githubmarkdown'); ?>
                                </option>
                                <option value="daily" <?php selected($syncInterval, 'daily'); ?>>
                                    <?php esc_html_e('Daily', 'wppost2githubmarkdown'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Sync Options', 'wppost2githubmarkdown'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label for="sync_enabled">
                                    <input type="checkbox" 
                                           id="sync_enabled" 
                                           name="<?php echo esc_attr(self::OPTION_PREFIX . 'sync_enabled'); ?>" 
                                           value="1" 
                                           <?php checked($syncEnabled); ?> />
                                    <?php esc_html_e('Enable automatic syncing', 'wppost2githubmarkdown'); ?>
                                </label><br />
                                
                                <label for="sync_published_only">
                                    <input type="checkbox" 
                                           id="sync_published_only" 
                                           name="<?php echo esc_attr(self::OPTION_PREFIX . 'sync_published_only'); ?>" 
                                           value="1" 
                                           <?php checked($syncPublishedOnly); ?> />
                                    <?php esc_html_e('Sync published posts only', 'wppost2githubmarkdown'); ?>
                                </label><br />
                                
                                <label for="bidirectional_sync">
                                    <input type="checkbox" 
                                           id="bidirectional_sync" 
                                           name="<?php echo esc_attr(self::OPTION_PREFIX . 'bidirectional_sync'); ?>" 
                                           value="1" 
                                           <?php checked($bidirectionalSync); ?> />
                                    <?php esc_html_e('Enable bidirectional sync (GitHub → WordPress)', 'wppost2githubmarkdown'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <?php if ($bidirectionalSync): ?>
                    <tr>
                        <th scope="row">
                            <label for="webhook_secret"><?php esc_html_e('Webhook Secret', 'wppost2githubmarkdown'); ?></label>
                        </th>
                        <td>
                            <input type="password" 
                                   id="webhook_secret" 
                                   name="<?php echo esc_attr(self::OPTION_PREFIX . 'webhook_secret'); ?>" 
                                   value="<?php echo esc_attr($webhookSecret); ?>" 
                                   class="regular-text" 
                                   placeholder="Generate a secure secret" />
                            <p class="description">
                                <?php esc_html_e('Secret key for webhook security. Generate a random string.', 'wppost2githubmarkdown'); ?>
                            </p>
                            <button type="button" onclick="document.getElementById('webhook_secret').value = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);" class="button button-secondary">
                                <?php esc_html_e('Generate Secret', 'wppost2githubmarkdown'); ?>
                            </button>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Webhook URL', 'wppost2githubmarkdown'); ?>
                        </th>
                        <td>
                            <code><?php echo esc_html($webhookUrl); ?></code>
                            <p class="description">
                                <?php esc_html_e('Use this URL when setting up the webhook in your GitHub repository.', 'wppost2githubmarkdown'); ?>
                            </p>
                            <button type="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($webhookUrl); ?>');" class="button button-secondary">
                                <?php esc_html_e('Copy URL', 'wppost2githubmarkdown'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr />
            
            <h2><?php esc_html_e('Manual Sync', 'wppost2githubmarkdown'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('wppost2githubmarkdown_manual'); ?>
                <p>
                    <?php esc_html_e('Trigger a manual sync of all eligible posts.', 'wppost2githubmarkdown'); ?>
                </p>
                <input type="submit" 
                       name="manual_sync" 
                       class="button button-secondary" 
                       value="<?php esc_attr_e('Sync Now', 'wppost2githubmarkdown'); ?>" />
            </form>
            
            <hr />
            
            <h2><?php esc_html_e('Sync Status', 'wppost2githubmarkdown'); ?></h2>
            <?php $this->renderSyncStatus(); ?>
            
            <?php if ($bidirectionalSync): ?>
            <hr />
            <h2><?php esc_html_e('GitHub Webhook Setup', 'wppost2githubmarkdown'); ?></h2>
            <?php $this->renderWebhookInstructions($webhookUrl, $webhookSecret); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render sync status table
     */
    private function renderSyncStatus(): void
    {
        global $wpdb;
        
        $tableName = $wpdb->prefix . 'wppost2githubmarkdown_log';
        $logs = $wpdb->get_results(
            "SELECT * FROM {$tableName} ORDER BY updated_at DESC LIMIT 20",
            ARRAY_A
        );
        
        if (empty($logs)) {
            echo '<p>' . esc_html__('No sync logs found.', 'wppost2githubmarkdown') . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Post ID', 'wppost2githubmarkdown'); ?></th>
                    <th><?php esc_html_e('Post Title', 'wppost2githubmarkdown'); ?></th>
                    <th><?php esc_html_e('Status', 'wppost2githubmarkdown'); ?></th>
                    <th><?php esc_html_e('GitHub Path', 'wppost2githubmarkdown'); ?></th>
                    <th><?php esc_html_e('Last Sync', 'wppost2githubmarkdown'); ?></th>
                    <th><?php esc_html_e('Error', 'wppost2githubmarkdown'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php $post = get_post($log['post_id']); ?>
                    <tr>
                        <td><?php echo esc_html($log['post_id']); ?></td>
                        <td>
                            <?php if ($post): ?>
                                <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">
                                    <?php echo esc_html($post->post_title); ?>
                                </a>
                            <?php else: ?>
                                <em><?php esc_html_e('Post not found', 'wppost2githubmarkdown'); ?></em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="sync-status sync-status-<?php echo esc_attr($log['sync_status']); ?>">
                                <?php echo esc_html($log['sync_status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['github_path']): ?>
                                <code><?php echo esc_html($log['github_path']); ?></code>
                            <?php else: ?>
                                <em><?php esc_html_e('Not synced', 'wppost2githubmarkdown'); ?></em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if ($log['last_sync_time']) {
                                echo esc_html(
                                    wp_date(get_option('date_format') . ' ' . get_option('time_format'), 
                                           strtotime($log['last_sync_time']))
                                );
                            } else {
                                echo '<em>' . esc_html__('Never', 'wppost2githubmarkdown') . '</em>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($log['error_message']): ?>
                                <details>
                                    <summary><?php esc_html_e('View Error', 'wppost2githubmarkdown'); ?></summary>
                                    <code><?php echo esc_html($log['error_message']); ?></code>
                                </details>
                            <?php else: ?>
                                <span style="color: green;">✓</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <style>
        .sync-status-success { color: green; font-weight: bold; }
        .sync-status-error { color: red; font-weight: bold; }
        .sync-status-pending { color: orange; font-weight: bold; }
        .sync-status-updated_from_github { color: blue; font-weight: bold; }
        </style>
        <?php
    }

    /**
     * Render webhook setup instructions
     */
    private function renderWebhookInstructions(string $webhookUrl, string $webhookSecret): void
    {
        $repo = $this->getOption('github_repo');
        if (empty($repo)) {
            echo '<p><em>' . esc_html__('Configure your GitHub repository first.', 'wppost2githubmarkdown') . '</em></p>';
            return;
        }
        
        if (empty($webhookSecret)) {
            echo '<div class="notice notice-warning"><p>' . 
                 esc_html__('Please generate and save a webhook secret first.', 'wppost2githubmarkdown') . '</p></div>';
            return;
        }
        
        $githubWebhookUrl = "https://github.com/{$repo}/settings/hooks";
        ?>
        <div class="webhook-instructions">
            <p><?php esc_html_e('To enable bidirectional sync, set up a webhook in your GitHub repository:', 'wppost2githubmarkdown'); ?></p>
            
            <ol>
                <li>
                    <strong><?php esc_html_e('Go to your GitHub repository:', 'wppost2githubmarkdown'); ?></strong>
                    <br><a href="<?php echo esc_url($githubWebhookUrl); ?>" target="_blank" rel="noopener">
                        <?php echo esc_html($githubWebhookUrl); ?>
                    </a>
                </li>
                
                <li>
                    <strong><?php esc_html_e('Click "Add webhook"', 'wppost2githubmarkdown'); ?></strong>
                </li>
                
                <li>
                    <strong><?php esc_html_e('Configure webhook settings:', 'wppost2githubmarkdown'); ?></strong>
                    <ul style="margin-top: 10px;">
                        <li><strong>Payload URL:</strong> <code><?php echo esc_html($webhookUrl); ?></code></li>
                        <li><strong>Content type:</strong> application/json</li>
                        <li><strong>Secret:</strong> <em><?php esc_html_e('Use the webhook secret from above', 'wppost2githubmarkdown'); ?></em></li>
                        <li><strong>Events:</strong> <?php esc_html_e('Just the push event', 'wppost2githubmarkdown'); ?></li>
                        <li><strong>Active:</strong> ✅ <?php esc_html_e('Checked', 'wppost2githubmarkdown'); ?></li>
                    </ul>
                </li>
                
                <li>
                    <strong><?php esc_html_e('Click "Add webhook"', 'wppost2githubmarkdown'); ?></strong>
                </li>
            </ol>
            
            <div class="notice notice-info">
                <p>
                    <strong><?php esc_html_e('Important:', 'wppost2githubmarkdown'); ?></strong>
                    <?php esc_html_e('Only markdown files in the /posts/ directory will trigger WordPress updates. Changes to other files will be ignored.', 'wppost2githubmarkdown'); ?>
                </p>
            </div>
        </div>
        
        <style>
        .webhook-instructions {
            background: #f9f9f9;
            padding: 20px;
            border-left: 4px solid #0073aa;
            margin: 15px 0;
        }
        .webhook-instructions ul {
            margin-left: 20px;
        }
        .webhook-instructions code {
            background: #fff;
            padding: 2px 6px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        </style>
        <?php
    }

    /**
     * Register webhook REST API endpoint
     */
    public function registerWebhookEndpoint(): void
    {
        register_rest_route(self::WEBHOOK_NAMESPACE, '/' . self::WEBHOOK_ENDPOINT, [
            'methods' => 'POST',
            'callback' => [$this, 'handleWebhook'],
            'permission_callback' => '__return_true', // We'll handle authentication in the callback
            'args' => [],
        ]);
    }

    /**
     * Handle incoming GitHub webhook
     */
    public function handleWebhook(\WP_REST_Request $request): \WP_REST_Response
    {
        // Check if bidirectional sync is enabled
        if (!$this->getOption('bidirectional_sync', false)) {
            return new \WP_REST_Response([
                'error' => 'Bidirectional sync is disabled'
            ], 403);
        }

        // Verify webhook signature
        $signature = $request->get_header('X-Hub-Signature-256');
        $payload = $request->get_body();
        
        if (!$this->verifyWebhookSignature($payload, $signature)) {
            error_log('WpPost2GitHubMarkdown: Invalid webhook signature');
            return new \WP_REST_Response([
                'error' => 'Invalid signature'
            ], 401);
        }

        $data = $request->get_json_params();
        
        // Only handle push events
        if ($request->get_header('X-GitHub-Event') !== 'push') {
            return new \WP_REST_Response([
                'message' => 'Event ignored (not a push event)'
            ], 200);
        }

        // Process the webhook data
        $this->processGitHubPush($data);

        return new \WP_REST_Response([
            'message' => 'Webhook processed successfully'
        ], 200);
    }

    /**
     * Verify GitHub webhook signature
     */
    private function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = $this->getOption('webhook_secret');
        
        if (empty($secret) || empty($signature)) {
            return false;
        }

        // Remove 'sha256=' prefix if present
        $signature = str_replace('sha256=', '', $signature);
        
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process GitHub push event
     */
    private function processGitHubPush(array $data): void
    {
        if (!isset($data['commits']) || !is_array($data['commits'])) {
            return;
        }

        foreach ($data['commits'] as $commit) {
            if (!isset($commit['modified']) || !is_array($commit['modified'])) {
                continue;
            }

            foreach ($commit['modified'] as $filePath) {
                // Only process markdown files in the posts directory
                if (strpos($filePath, 'posts/') === 0 && pathinfo($filePath, PATHINFO_EXTENSION) === 'md') {
                    $this->updatePostFromGitHub($filePath);
                }
            }
        }
    }

    /**
     * Update WordPress post from GitHub file
     */
    private function updatePostFromGitHub(string $filePath): void
    {
        try {
            // Get file content from GitHub
            $content = $this->getGitHubFileContent($filePath);
            
            if (!$content) {
                error_log("WpPost2GitHubMarkdown: Could not fetch content for {$filePath}");
                return;
            }

            // Parse markdown content
            $parsedContent = $this->parseMarkdownContent($content);
            
            if (!$parsedContent) {
                error_log("WpPost2GitHubMarkdown: Could not parse markdown for {$filePath}");
                return;
            }

            // Find corresponding WordPress post
            $postId = $this->findPostByGitHubPath($filePath);
            
            if (!$postId) {
                error_log("WpPost2GitHubMarkdown: No corresponding WordPress post found for {$filePath}");
                return;
            }

            // Update the WordPress post
            $updateResult = wp_update_post([
                'ID' => $postId,
                'post_title' => $parsedContent['title'],
                'post_content' => $parsedContent['content'],
                'post_excerpt' => $parsedContent['excerpt'] ?? '',
            ]);

            if ($updateResult && !is_wp_error($updateResult)) {
                // Update categories and tags if provided
                if (!empty($parsedContent['categories'])) {
                    wp_set_post_categories($postId, $parsedContent['categories']);
                }
                
                if (!empty($parsedContent['tags'])) {
                    wp_set_post_tags($postId, $parsedContent['tags']);
                }

                // Log successful update
                error_log("WpPost2GitHubMarkdown: Successfully updated post {$postId} from GitHub");
                
                // Update sync log
                $this->updateSyncLog($postId, 'updated_from_github', $filePath, null);
            } else {
                error_log("WpPost2GitHubMarkdown: Failed to update post {$postId}");
            }

        } catch (\Exception $e) {
            error_log("WpPost2GitHubMarkdown: Error updating post from GitHub: " . $e->getMessage());
        }
    }

    /**
     * Get file content from GitHub
     */
    private function getGitHubFileContent(string $filePath): ?string
    {
        $token = $this->getOption('github_token');
        $repo = $this->getOption('github_repo');
        
        if (empty($token) || empty($repo)) {
            return null;
        }

        $url = "https://api.github.com/repos/{$repo}/contents/{$filePath}";
        
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'token ' . $token,
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WpPost2GitHubMarkdown/1.0',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $statusCode = wp_remote_retrieve_response_code($response);
        
        if ($statusCode !== 200) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['content'])) {
            return null;
        }
        
        return base64_decode($data['content']);
    }

    /**
     * Parse markdown content with frontmatter
     */
    private function parseMarkdownContent(string $content): ?array
    {
        // Check if content starts with frontmatter
        if (strpos($content, '---') !== 0) {
            return null;
        }

        // Split frontmatter and content
        $parts = explode('---', $content, 3);
        
        if (count($parts) < 3) {
            return null;
        }

        $frontmatter = trim($parts[1]);
        $markdownContent = trim($parts[2]);

        // Parse YAML frontmatter (basic parsing)
        $metadata = $this->parseYamlFrontmatter($frontmatter);
        
        // Convert markdown to HTML
        $htmlContent = $this->markdownToHtml($markdownContent);

        return [
            'title' => $metadata['title'] ?? 'Untitled',
            'content' => $htmlContent,
            'excerpt' => $metadata['excerpt'] ?? '',
            'categories' => $metadata['categories'] ?? [],
            'tags' => $metadata['tags'] ?? [],
        ];
    }

    /**
     * Basic YAML frontmatter parser
     */
    private function parseYamlFrontmatter(string $yaml): array
    {
        $data = [];
        $lines = explode("\n", $yaml);
        $currentKey = null;
        $currentArray = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            // Handle array items
            if (strpos($line, '- ') === 0) {
                $currentArray[] = trim(substr($line, 2));
                continue;
            }
            
            // If we were building an array, save it
            if ($currentKey && !empty($currentArray)) {
                $data[$currentKey] = $currentArray;
                $currentArray = [];
                $currentKey = null;
            }
            
            // Handle key-value pairs
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes from value
                $value = trim($value, '"\'');
                
                if (empty($value)) {
                    // This might be an array
                    $currentKey = $key;
                } else {
                    $data[$key] = $value;
                }
            }
        }
        
        // Save any remaining array
        if ($currentKey && !empty($currentArray)) {
            $data[$currentKey] = $currentArray;
        }
        
        return $data;
    }

    /**
     * Convert markdown to HTML
     */
    private function markdownToHtml(string $markdown): string
    {
        // Basic markdown to HTML conversion
        $replacements = [
            '/^# (.*$)/m' => '<h1>$1</h1>',
            '/^## (.*$)/m' => '<h2>$1</h2>',
            '/^### (.*$)/m' => '<h3>$1</h3>',
            '/^#### (.*$)/m' => '<h4>$1</h4>',
            '/^##### (.*$)/m' => '<h5>$1</h5>',
            '/^###### (.*$)/m' => '<h6>$1</h6>',
            '/\*\*(.*?)\*\*/s' => '<strong>$1</strong>',
            '/\*(.*?)\*/s' => '<em>$1</em>',
            '/`(.*?)`/s' => '<code>$1</code>',
            '/^> (.*)$/m' => '<blockquote>$1</blockquote>',
            '/^- (.*)$/m' => '<ul><li>$1</li></ul>',
            '/^\d+\. (.*)$/m' => '<ol><li>$1</li></ol>',
            '/\[([^\]]+)\]\(([^)]+)\)/' => '<a href="$2">$1</a>',
            '/!\[([^\]]*)\]\(([^)]+)\)/' => '<img src="$2" alt="$1" />',
            '/^---$/m' => '<hr />',
        ];
        
        foreach ($replacements as $pattern => $replacement) {
            $markdown = preg_replace($pattern, $replacement, $markdown);
        }
        
        // Handle code blocks
        $markdown = preg_replace('/```([\s\S]*?)```/', '<pre><code>$1</code></pre>', $markdown);
        
        // Convert line breaks to paragraphs
        $paragraphs = explode("\n\n", $markdown);
        $html = '';
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!empty($paragraph)) {
                // Don't wrap existing HTML tags in paragraphs
                if (!preg_match('/^<(h[1-6]|ul|ol|blockquote|hr|pre|div)/', $paragraph)) {
                    $paragraph = '<p>' . $paragraph . '</p>';
                }
                $html .= $paragraph . "\n";
            }
        }
        
        // Clean up consecutive lists
        $html = preg_replace('/<\/ul>\s*<ul>/', '', $html);
        $html = preg_replace('/<\/ol>\s*<ol>/', '', $html);
        
        return trim($html);
    }

    /**
     * Find WordPress post by GitHub file path
     */
    private function findPostByGitHubPath(string $filePath): ?int
    {
        global $wpdb;
        
        $tableName = $wpdb->prefix . 'wppost2githubmarkdown_log';
        
        $postId = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$tableName} WHERE github_path = %s ORDER BY last_sync_time DESC LIMIT 1",
            $filePath
        ));
        
        return $postId ? (int) $postId : null;
    }

    /**
     * Trigger manual sync
     */
    private function triggerManualSync(): void
    {
        // Queue all eligible posts for sync
        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => $this->getOption('sync_published_only', true) ? 'publish' : 'any',
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        foreach ($posts as $postId) {
            $this->queuePostForSync($postId);
        }

        // Process the queue immediately
        $this->processSyncQueue();
    }

    /**
     * Queue a post for sync
     */
    private function queuePostForSync(int $postId): void
    {
        global $wpdb;
        
        $tableName = $wpdb->prefix . 'wppost2githubmarkdown_log';
        
        // Check if post is already queued
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tableName} WHERE post_id = %d",
            $postId
        ));
        
        if (!$existing) {
            $wpdb->insert(
                $tableName,
                [
                    'post_id' => $postId,
                    'sync_status' => 'pending',
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%s']
            );
        } else {
            // Update existing record to pending if it failed before
            $wpdb->update(
                $tableName,
                [
                    'sync_status' => 'pending',
                    'updated_at' => current_time('mysql'),
                ],
                ['post_id' => $postId],
                ['%s', '%s'],
                ['%d']
            );
        }
    }

    /**
     * Process sync queue (main cron function)
     */
    public function processSyncQueue(): void
    {
        // Check if sync is enabled
        if (!$this->getOption('sync_enabled', false)) {
            return;
        }

        // Validate configuration
        if (!$this->isConfigured()) {
            error_log('WpPost2GitHubMarkdown: Plugin not properly configured');
            return;
        }

        global $wpdb;
        $tableName = $wpdb->prefix . 'wppost2githubmarkdown_log';
        
        // Get pending sync items (limit to 10 per run to avoid timeouts)
        $pendingItems = $wpdb->get_results(
            "SELECT * FROM {$tableName} WHERE sync_status = 'pending' ORDER BY created_at ASC LIMIT 10",
            ARRAY_A
        );

        foreach ($pendingItems as $item) {
            $this->syncPost((int) $item['post_id']);
        }
    }

    /**
     * Check if plugin is properly configured
     */
    private function isConfigured(): bool
    {
        $token = $this->getOption('github_token');
        $repo = $this->getOption('github_repo');
        
        return !empty($token) && !empty($repo) && $this->isValidRepo($repo);
    }

    /**
     * Validate repository format
     */
    private function isValidRepo(string $repo): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_.-]+\/[a-zA-Z0-9_.-]+$/', $repo);
    }

    /**
     * Sync individual post to GitHub
     */
    private function syncPost(int $postId): void
    {
        $post = get_post($postId);
        
        if (!$post || $post->post_type !== 'post') {
            $this->updateSyncLog($postId, 'error', null, 'Post not found or invalid type');
            return;
        }

        // Skip if sync published only is enabled and post is not published
        if ($this->getOption('sync_published_only', true) && $post->post_status !== 'publish') {
            $this->updateSyncLog($postId, 'error', null, 'Post is not published');
            return;
        }

        try {
            // Convert post to markdown
            $markdown = $this->convertPostToMarkdown($post);
            
            // Generate filename
            $filename = $this->generateFilename($post);
            
            // Upload to GitHub
            $githubPath = $this->uploadToGitHub($filename, $markdown, $post);
            
            // Update sync log
            $this->updateSyncLog($postId, 'success', $githubPath, null);
            
        } catch (\Exception $e) {
            $this->updateSyncLog($postId, 'error', null, $e->getMessage());
            error_log('WpPost2GitHubMarkdown Error for post ' . $postId . ': ' . $e->getMessage());
        }
    }

    /**
     * Convert WordPress post to markdown
     */
    private function convertPostToMarkdown(\WP_Post $post): string
    {
        $content = $post->post_content;
        
        // Apply WordPress content filters
        $content = apply_filters('the_content', $content);
        
        // Convert HTML to markdown (basic conversion)
        $content = $this->htmlToMarkdown($content);
        
        // Get post metadata
        $categories = wp_get_post_categories($post->ID, ['fields' => 'names']);
        $tags = wp_get_post_tags($post->ID, ['fields' => 'names']);
        $excerpt = get_the_excerpt($post);
        
        // Build frontmatter
        $frontmatter = "---\n";
        $frontmatter .= "title: \"" . addslashes($post->post_title) . "\"\n";
        $frontmatter .= "date: " . get_the_date('Y-m-d H:i:s', $post) . "\n";
        $frontmatter .= "author: " . get_the_author_meta('display_name', $post->post_author) . "\n";
        $frontmatter .= "status: " . $post->post_status . "\n";
        
        if (!empty($excerpt)) {
            $frontmatter .= "excerpt: \"" . addslashes($excerpt) . "\"\n";
        }
        
        if (!empty($categories)) {
            $frontmatter .= "categories:\n";
            foreach ($categories as $category) {
                $frontmatter .= "  - " . $category . "\n";
            }
        }
        
        if (!empty($tags)) {
            $frontmatter .= "tags:\n";
            foreach ($tags as $tag) {
                $frontmatter .= "  - " . $tag . "\n";
            }
        }
        
        $frontmatter .= "permalink: " . get_permalink($post) . "\n";
        $frontmatter .= "---\n\n";
        
        // Add the post title as first heading and return with content
        return $frontmatter . "# " . $post->post_title . "\n\n" . $content;
    }

    /**
     * Basic HTML to Markdown conversion
     */
    private function htmlToMarkdown(string $html): string
    {
        // Remove WordPress-specific shortcodes and blocks
        $html = strip_shortcodes($html);
        
        // Basic HTML to Markdown conversions
        $replacements = [
            '/<h1[^>]*>(.*?)<\/h1>/i' => '# $1',
            '/<h2[^>]*>(.*?)<\/h2>/i' => '## $1',
            '/<h3[^>]*>(.*?)<\/h3>/i' => '### $1',
            '/<h4[^>]*>(.*?)<\/h4>/i' => '#### $1',
            '/<h5[^>]*>(.*?)<\/h5>/i' => '##### $1',
            '/<h6[^>]*>(.*?)<\/h6>/i' => '###### $1',
            '/<strong[^>]*>(.*?)<\/strong>/i' => '**$1**',
            '/<b[^>]*>(.*?)<\/b>/i' => '**$1**',
            '/<em[^>]*>(.*?)<\/em>/i' => '*$1*',
            '/<i[^>]*>(.*?)<\/i>/i' => '*$1*',
            '/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/i' => '[$2]($1)',
            '/<img[^>]*src="([^"]*)"[^>]*alt="([^"]*)"[^>]*>/i' => '![$2]($1)',
            '/<img[^>]*src="([^"]*)"[^>]*>/i' => '![]($1)',
            '/<code[^>]*>(.*?)<\/code>/i' => '`$1`',
            '/<pre[^>]*>(.*?)<\/pre>/is' => "```\n$1\n```",
            '/<blockquote[^>]*>(.*?)<\/blockquote>/is' => '> $1',
            '/<ul[^>]*>(.*?)<\/ul>/is' => '$1',
            '/<ol[^>]*>(.*?)<\/ol>/is' => '$1',
            '/<li[^>]*>(.*?)<\/li>/i' => '- $1',
            '/<br\s*\/?>/i' => "\n",
            '/<hr\s*\/?>/i' => "\n---\n",
            '/<p[^>]*>(.*?)<\/p>/is' => "$1\n\n",
        ];
        
        foreach ($replacements as $pattern => $replacement) {
            $html = preg_replace($pattern, $replacement, $html);
        }
        
        // Remove remaining HTML tags
        $html = strip_tags($html);
        
        // Clean up extra whitespace
        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        $html = trim($html);
        
        return $html;
    }

    /**
     * Generate filename for the post: post-slug-DD-MM-YYYY.md
     */
    private function generateFilename(\WP_Post $post): string
    {
        $slug = $post->post_name;
        $date = get_the_date('d-m-Y', $post);
        
        return sprintf('%s-%s.md', $slug, $date);
    }

    /**
     * Upload content to GitHub
     */
    private function uploadToGitHub(string $filename, string $content, \WP_Post $post): string
    {
        $token = $this->getOption('github_token');
        $repo = $this->getOption('github_repo');
        
        if (empty($token) || empty($repo)) {
            throw new \Exception('GitHub token or repository not configured');
        }

        $path = 'posts/' . $filename;
        $url = "https://api.github.com/repos/{$repo}/contents/{$path}";
        
        // Check if file already exists
        $existingFile = $this->getGitHubFile($url, $token);
        
        $requestBody = [
            'message' => sprintf('Sync WordPress post: %s', $post->post_title),
            'content' => base64_encode($content),
            'branch' => 'main',
        ];
        
        // If file exists, include SHA for update
        if ($existingFile && isset($existingFile['sha'])) {
            $requestBody['sha'] = $existingFile['sha'];
        }
        
        $response = wp_remote_request($url, [
            'method' => 'PUT',
            'headers' => [
                'Authorization' => 'token ' . $token,
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WpPost2GitHubMarkdown/1.0',
            ],
            'body' => wp_json_encode($requestBody),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            throw new \Exception('GitHub API request failed: ' . $response->get_error_message());
        }
        
        $statusCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        
        if ($statusCode < 200 || $statusCode >= 300) {
            $errorData = json_decode($responseBody, true);
            $errorMessage = $errorData['message'] ?? 'Unknown GitHub API error';
            throw new \Exception("GitHub API error ({$statusCode}): {$errorMessage}");
        }
        
        return $path;
    }

    /**
     * Get existing file from GitHub
     */
    private function getGitHubFile(string $url, string $token): ?array
    {
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'token ' . $token,
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WpPost2GitHubMarkdown/1.0',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $statusCode = wp_remote_retrieve_response_code($response);
        
        if ($statusCode === 404) {
            return null; // File doesn't exist
        }
        
        if ($statusCode !== 200) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * Update sync log
     */
    private function updateSyncLog(int $postId, string $status, ?string $githubPath, ?string $errorMessage): void
    {
        global $wpdb;
        
        $tableName = $wpdb->prefix . 'wppost2githubmarkdown_log';
        
        $data = [
            'sync_status' => $status,
            'updated_at' => current_time('mysql'),
        ];
        
        if ($status === 'success') {
            $data['last_sync_time'] = current_time('mysql');
            $data['github_path'] = $githubPath;
            $data['error_message'] = null;
        } else {
            $data['error_message'] = $errorMessage;
        }
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tableName} WHERE post_id = %d",
            $postId
        ));
        
        if ($existing) {
            $wpdb->update(
                $tableName,
                $data,
                ['post_id' => $postId],
                array_fill(0, count($data), '%s'),
                ['%d']
            );
        } else {
            $data['post_id'] = $postId;
            $data['created_at'] = current_time('mysql');
            $wpdb->insert(
                $tableName,
                $data,
                array_merge(['%d'], array_fill(0, count($data) - 1, '%s'))
            );
        }
    }
}

// Initialize the plugin
WpPost2GitHubMarkdownPlugin::getInstance();
