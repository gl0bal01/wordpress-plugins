<?php
/**
 * Plugin Name: Blacklist Word Checker
 * Plugin URI: https://github.com/gl0bal01/wordpress-plugins/wp-blacklist-word-checker
 * Description: A WordPress plugin that scans post titles and content for blacklisted words in real-time, providing detailed reporting and easy blacklist management.
 * Version: 1.0.0
 * Author: gl0bal01
 * Author URI: https://gl0bal01.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: blacklist-word-checker
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * Update URI: false
 */

declare(strict_types=1);

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BLACKLIST_WORD_CHECKER_VERSION', '1.0.0');
define('BLACKLIST_WORD_CHECKER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BLACKLIST_WORD_CHECKER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BLACKLIST_WORD_CHECKER_PLUGIN_FILE', __FILE__);
define('BLACKLIST_WORD_CHECKER_TEXT_DOMAIN', 'blacklist-word-checker');

/**
 * Main plugin bootstrap class
 *
 * @since 1.0.0
 */
final class BlacklistWordCheckerBootstrap
{
    /**
     * Plugin instance
     *
     * @var BlacklistWordCheckerBootstrap|null
     */
    private static ?BlacklistWordCheckerBootstrap $instance = null;

    /**
     * Main plugin instance
     *
     * @var BlacklistWordChecker|null
     */
    private ?BlacklistWordChecker $plugin = null;

    /**
     * Admin instance
     *
     * @var BlacklistWordCheckerAdmin|null
     */
    private ?BlacklistWordCheckerAdmin $admin = null;

    /**
     * Get singleton instance
     *
     * @return BlacklistWordCheckerBootstrap
     */
    public static function getInstance(): BlacklistWordCheckerBootstrap
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
        $this->setupHooks();
    }

    /**
     * Setup WordPress hooks
     *
     * @return void
     */
    private function setupHooks(): void
    {
        add_action('plugins_loaded', [$this, 'initializePlugin']);
        
        register_activation_hook(BLACKLIST_WORD_CHECKER_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(BLACKLIST_WORD_CHECKER_PLUGIN_FILE, [$this, 'deactivate']);
        register_uninstall_hook(BLACKLIST_WORD_CHECKER_PLUGIN_FILE, [__CLASS__, 'uninstall']);
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    public function initializePlugin(): void
    {
        // Load text domain for internationalization
        $this->loadTextDomain();

        // Include required files
        $this->includeFiles();

        // Initialize main plugin class
        $this->plugin = new BlacklistWordChecker();
        $this->plugin->init();

        // Initialize admin if in admin area
        if (is_admin()) {
            $this->admin = new BlacklistWordCheckerAdmin();
            $this->admin->init();
        }
    }

    /**
     * Load plugin text domain
     *
     * @return void
     */
    private function loadTextDomain(): void
    {
        load_plugin_textdomain(
            BLACKLIST_WORD_CHECKER_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(BLACKLIST_WORD_CHECKER_PLUGIN_FILE)) . '/languages'
        );
    }

    /**
     * Include required files
     *
     * @return void
     */
    private function includeFiles(): void
    {
        require_once BLACKLIST_WORD_CHECKER_PLUGIN_DIR . 'includes/class-blacklist-word-checker.php';

        if (is_admin()) {
            require_once BLACKLIST_WORD_CHECKER_PLUGIN_DIR . 'admin/class-blacklist-word-checker-admin.php';
        }
    }

    /**
     * Plugin activation callback
     *
     * @return void
     */
    public function activate(): void
    {
        // Create default blacklist if it doesn't exist
        $defaultBlacklist = [
            'spam',
            'inappropriate',
            'offensive',
            'blocked'
        ];

        if (!get_option('blacklist_word_checker_list')) {
            update_option('blacklist_word_checker_list', $defaultBlacklist);
        }

        // Set version
        update_option('blacklist_word_checker_version', BLACKLIST_WORD_CHECKER_VERSION);

        // Clear permalinks
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation callback
     *
     * @return void
     */
    public function deactivate(): void
    {
        // Clear any cached data
        wp_cache_flush();
        
        // Clear permalinks
        flush_rewrite_rules();
    }

    /**
     * Plugin uninstall callback
     *
     * @return void
     */
    public static function uninstall(): void
    {
        // Remove all plugin data
        delete_option('blacklist_word_checker_list');
        delete_option('blacklist_word_checker_version');

        // Clear any cached data
        wp_cache_flush();
    }

    /**
     * Prevent cloning
     *
     * @return void
     */
    private function __clone()
    {
        // Prevent cloning
    }

    /**
     * Prevent unserialization
     *
     * @return void
     */
    public function __wakeup(): void
    {
        throw new Exception('Cannot unserialize singleton');
    }
}

// Initialize the plugin
BlacklistWordCheckerBootstrap::getInstance();
