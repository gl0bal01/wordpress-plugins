<?php
/**
 * Plugin Name: WP Table of Contents
 * Plugin URI: https://github.com/gl0bal01/wordpress-plugins/wp-table-of-contents
 * Description: Automatically generates a table of contents for posts and pages based on heading tags (H1-H2). Includes admin controls to disable on specific content.
 * Version: 1.0.0
 * Author: gl0bal01
 * Author URI: https://gl0bal01.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-table-of-contents
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * Update URI: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main WP Table of Contents Plugin Class
 * 
 * This plugin automatically generates table of contents for posts and pages
 * based on heading tags (H1-H2) and provides admin controls for customization.
 * 
 * == FILTERS FOR CUSTOMIZATION ==
 * 
 * // Disable TOC programmatically for specific content
 * add_filter('wp_toc_is_disabled', function($disabled, $post) {
 *     if ($post->post_name === 'special-page') {
 *         return true;
 *     }
 *     return $disabled;
 * }, 10, 2);
 * 
 * // Customize which post types show TOC metabox
 * add_filter('wp_toc_post_types', function($post_types) {
 *     $post_types[] = 'custom_post_type';
 *     $post_types[] = 'portfolio';
 *     return $post_types;
 * });
 * 
 * // Modify generated TOC HTML
 * add_filter('wp_toc_html', function($html, $headings) {
 *     // Add custom wrapper div
 *     return '<div class="custom-toc-wrapper">' . $html . '</div>';
 * }, 10, 2);
 * 
 * // Customize plugin settings
 * add_filter('wp_toc_settings', function($settings) {
 *     $settings['min_headings'] = 3; // Require 3+ headings
 *     $settings['title'] = 'Contents'; // Custom title
 *     return $settings;
 * });
 * 
 * == ACTIONS FOR EXTENSIONS ==
 * 
 * // Hook into plugin initialization
 * add_action('wp_toc_init', function() {
 *     // Your custom initialization code
 *     if (class_exists('My_Custom_Plugin')) {
 *         My_Custom_Plugin::integrate_with_toc();
 *     }
 * });
 * 
 * // React to TOC settings being saved
 * add_action('wp_toc_settings_saved', function($post_id, $disabled, $post) {
 *     // Custom logic after TOC settings are saved
 *     if ($disabled) {
 *         // Log when TOC is disabled
 *         error_log("TOC disabled for post: {$post->post_title}");
 *     }
 * }, 10, 3);
 * 
 * // Plugin activation custom logic
 * add_action('wp_toc_activated', function() {
 *     // Run custom code when plugin is activated
 *     update_option('my_toc_integration_active', true);
 * });
 * 
 * // Plugin deactivation cleanup
 * add_action('wp_toc_deactivated', function() {
 *     // Clean up integration when plugin is deactivated
 *     delete_option('my_toc_integration_active');
 * });
 * 
 * @since 1.0.0
 */
final class WP_Table_Of_Contents {
    
    /**
     * Plugin version
     * 
     * @var string
     */
    public const VERSION = '1.0.0';
    
    /**
     * Text domain for internationalization
     * 
     * @var string
     */
    public const TEXT_DOMAIN = 'wp-table-of-contents';
    
    /**
     * Meta key for disabling TOC
     * 
     * @var string
     */
    private const META_KEY_DISABLE = 'wp_toc_disable';
    
    /**
     * Nonce action for metabox
     * 
     * @var string
     */
    private const NONCE_ACTION = 'wp_toc_metabox_save';
    
    /**
     * Minimum number of headings required to show TOC
     * 
     * @var int
     */
    private const MIN_HEADINGS = 2;
    
    /**
     * Plugin instance
     * 
     * @var WP_Table_Of_Contents|null
     */
    private static ?WP_Table_Of_Contents $instance = null;
    
    /**
     * Get plugin instance (Singleton pattern)
     * 
     * @return WP_Table_Of_Contents
     */
    public static function get_instance(): WP_Table_Of_Contents {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Constructor - Initialize the plugin
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    private function __wakeup() {}
    
    /**
     * Initialize WordPress hooks
     * 
     * @return void
     */
    private function init_hooks(): void {
        // Plugin initialization
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'init']);
        
        // Content filtering
        add_filter('the_content', [$this, 'maybe_add_toc'], 10);
        
        // Admin functionality
        add_action('add_meta_boxes', [$this, 'add_toc_metabox']);
        add_action('save_post', [$this, 'save_toc_metabox'], 10, 2);
        
        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }
    
    /**
     * Load plugin text domain for internationalization
     * 
     * @return void
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            self::TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
    
    /**
     * Initialize plugin
     * 
     * @return void
     */
    public function init(): void {
        // Any initialization code can go here
        do_action('wp_toc_init');
    }
    
    /**
     * Enqueue frontend assets
     * 
     * @return void
     */
    public function enqueue_frontend_assets(): void {
        if (!is_singular()) {
            return;
        }
        
        // Add inline CSS for TOC styling
        $css = $this->get_toc_styles();
        wp_add_inline_style('wp-block-library', $css);
    }
    
    /**
     * Get TOC CSS styles
     * 
     * @return string
     */
    private function get_toc_styles(): string {
        return '
        .wp-table-of-contents {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 1.5em 0;
            padding: 1em;
            font-size: 0.95em;
        }
        .wp-toc-title {
            font-weight: bold;
            font-size: 1.2em;
            margin: 0 0 0.5em 0;
            padding: 0;
            color: #333;
        }
        .wp-table-of-contents ul {
            margin: 0;
            padding-left: 1.5em;
            list-style: none;
        }
        .wp-table-of-contents li {
            margin: 0.25em 0;
        }
        .wp-table-of-contents a {
            text-decoration: none;
            color: #0073aa;
            transition: color 0.2s ease;
        }
        .wp-table-of-contents a:hover {
            color: #005177;
            text-decoration: underline;
        }
        .wp-table-of-contents .wp-toc-h1 {
            font-weight: 600;
        }
        .wp-table-of-contents .wp-toc-h2 {
            font-weight: normal;
        }
        ';
    }
    
    /**
     * Maybe add table of contents to content
     * 
     * @param string $content Post content
     * @return string Modified content
     */
    public function maybe_add_toc(string $content): string {
        // Skip if not singular content
        if (!is_singular()) {
            return $content;
        }
        
        // Skip for REST API requests
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return $content;
        }
        
        // Check if TOC is disabled for this post
        if ($this->is_toc_disabled()) {
            return $content;
        }
        
        // Generate and prepend TOC
        $toc = $this->generate_toc($content);
        if (!empty($toc)) {
            $content = $toc . $content;
        }
        
        return $content;
    }
    
    /**
     * Check if TOC is disabled for current post
     * 
     * @return bool
     */
    private function is_toc_disabled(): bool {
        global $post;
        
        if (!$post instanceof WP_Post) {
            return true;
        }
        
        // Check post meta
        $disabled = get_post_meta($post->ID, self::META_KEY_DISABLE, true);
        
        /**
         * Filter to allow disabling TOC programmatically
         * 
         * @param bool $disabled Whether TOC is disabled
         * @param WP_Post $post Current post object
         */
        return (bool) apply_filters('wp_toc_is_disabled', $disabled, $post);
    }
    
    /**
     * Generate table of contents from content
     * 
     * @param string $content Post content
     * @return string TOC HTML or empty string
     */
    private function generate_toc(string &$content): string {
        // Find all headings (H1-H2)
        if (!preg_match_all('/<h([1-2])([^>]*)>(.*?)<\/h[1-2]>/i', $content, $matches, PREG_SET_ORDER)) {
            return '';
        }
        
        // Check minimum heading count
        if (count($matches) < self::MIN_HEADINGS) {
            return '';
        }
        
        $headings = [];
        $used_ids = [];
        
        // Process each heading
        foreach ($matches as $match) {
            $level = (int) $match[1];
            $attributes = $match[2];
            $title = wp_strip_all_tags($match[3]);
            
            // Skip empty titles
            if (empty(trim($title))) {
                continue;
            }
            
            // Generate unique ID
            $id = $this->generate_heading_id($title, $used_ids);
            $used_ids[] = $id;
            
            $headings[] = [
                'level' => $level,
                'title' => $title,
                'id' => $id,
                'original' => $match[0]
            ];
            
            // Add ID to heading in content
            $new_heading = sprintf(
                '<h%d%s id="%s">%s</h%d>',
                $level,
                $attributes,
                esc_attr($id),
                $match[3],
                $level
            );
            
            $content = str_replace($match[0], $new_heading, $content);
        }
        
        if (empty($headings)) {
            return '';
        }
        
        return $this->build_toc_html($headings);
    }
    
    /**
     * Generate unique heading ID
     * 
     * @param string $title Heading title
     * @param array $used_ids Already used IDs
     * @return string Unique ID
     */
    private function generate_heading_id(string $title, array $used_ids): string {
        $id = sanitize_title($title);
        
        // Ensure ID is not empty
        if (empty($id)) {
            $id = 'heading';
        }
        
        // Make ID unique
        $original_id = $id;
        $counter = 1;
        
        while (in_array($id, $used_ids, true)) {
            $id = $original_id . '-' . $counter;
            $counter++;
        }
        
        return $id;
    }
    
    /**
     * Build TOC HTML structure
     * 
     * @param array $headings Array of heading data
     * @return string TOC HTML
     */
    private function build_toc_html(array $headings): string {
        $toc_title = __('Table of Contents', self::TEXT_DOMAIN);
        
        $html = sprintf(
            '<nav class="wp-table-of-contents" aria-label="%s">',
            esc_attr($toc_title)
        );
        
        $html .= sprintf(
            '<div class="wp-toc-title">%s</div>',
            esc_html($toc_title)
        );
        
        $html .= $this->build_toc_list($headings);
        $html .= '</nav>';
        
        /**
         * Filter the generated TOC HTML
         * 
         * @param string $html TOC HTML
         * @param array $headings Array of heading data
         */
        return apply_filters('wp_toc_html', $html, $headings);
    }
    
    /**
     * Build nested list structure for TOC
     * 
     * @param array $headings Array of heading data
     * @return string List HTML
     */
    private function build_toc_list(array $headings): string {
        $html = '<ul>';
        $last_level = 1;
        
        foreach ($headings as $index => $heading) {
            $level = $heading['level'];
            $title = $heading['title'];
            $id = $heading['id'];
            
            // Handle level changes
            if ($level > $last_level) {
                // Open new nested list(s)
                $html .= str_repeat('<ul>', $level - $last_level);
            } elseif ($level < $last_level) {
                // Close nested list(s)
                $html .= str_repeat('</li></ul>', $last_level - $level);
                $html .= '</li>';
            } elseif ($index > 0) {
                // Same level, close previous item
                $html .= '</li>';
            }
            
            // Add current item
            $html .= sprintf(
                '<li class="wp-toc-h%d"><a href="#%s">%s</a>',
                $level,
                esc_attr($id),
                esc_html($title)
            );
            
            $last_level = $level;
        }
        
        // Close remaining lists
        $html .= str_repeat('</li></ul>', $last_level);
        
        return $html;
    }
    
    /**
     * Add metabox to post/page edit screens
     * 
     * @return void
     */
    public function add_toc_metabox(): void {
        $post_types = ['post', 'page'];
        
        /**
         * Filter post types that should have TOC metabox
         * 
         * @param array $post_types Array of post type names
         */
        $post_types = apply_filters('wp_toc_post_types', $post_types);
        
        add_meta_box(
            'wp-toc-settings',
            __('Table of Contents', self::TEXT_DOMAIN),
            [$this, 'render_toc_metabox'],
            $post_types,
            'side',
            'default'
        );
    }
    
    /**
     * Render TOC metabox content
     * 
     * @param WP_Post $post Current post object
     * @return void
     */
    public function render_toc_metabox(WP_Post $post): void {
        // Add nonce for security
        wp_nonce_field(self::NONCE_ACTION, 'wp_toc_nonce');
        
        // Get current setting
        $disabled = get_post_meta($post->ID, self::META_KEY_DISABLE, true);
        
        ?>
        <p>
            <label for="wp-toc-disable">
                <input 
                    type="checkbox" 
                    name="wp_toc_disable" 
                    id="wp-toc-disable" 
                    value="1"
                    <?php checked($disabled, 1); ?>
                >
                <?php esc_html_e('Disable table of contents for this content', self::TEXT_DOMAIN); ?>
            </label>
        </p>
        <p class="description">
            <?php esc_html_e('Check this box to prevent the automatic table of contents from appearing on this post or page.', self::TEXT_DOMAIN); ?>
        </p>
        <?php
    }
    
    /**
     * Save metabox data
     * 
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @return void
     */
    public function save_toc_metabox(int $post_id, WP_Post $post): void {
        // Security checks
        if (!$this->verify_metabox_security($post_id)) {
            return;
        }
        
        // Get and sanitize input
        $disabled = isset($_POST['wp_toc_disable']) ? 1 : 0;
        
        // Update meta value
        update_post_meta($post_id, self::META_KEY_DISABLE, $disabled);
        
        /**
         * Action fired after TOC settings are saved
         * 
         * @param int $post_id Post ID
         * @param bool $disabled Whether TOC is disabled
         * @param WP_Post $post Post object
         */
        do_action('wp_toc_settings_saved', $post_id, (bool) $disabled, $post);
    }
    
    /**
     * Verify metabox security
     * 
     * @param int $post_id Post ID
     * @return bool
     */
    private function verify_metabox_security(int $post_id): bool {
        // Verify nonce
        if (!isset($_POST['wp_toc_nonce']) || 
            !wp_verify_nonce($_POST['wp_toc_nonce'], self::NONCE_ACTION)) {
            return false;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }
        
        // Check user capabilities
        $post_type = get_post_type($post_id);
        $post_type_object = get_post_type_object($post_type);
        
        if (!$post_type_object || !current_user_can($post_type_object->cap->edit_post, $post_id)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get plugin settings with defaults
     * 
     * @return array
     */
    public function get_settings(): array {
        $defaults = [
            'min_headings' => self::MIN_HEADINGS,
            'heading_levels' => [1, 2],
            'title' => __('Table of Contents', self::TEXT_DOMAIN),
        ];
        
        /**
         * Filter plugin settings
         * 
         * @param array $settings Plugin settings
         */
        return apply_filters('wp_toc_settings', $defaults);
    }
    
    /**
     * Plugin activation hook
     * 
     * @return void
     */
    public static function activate(): void {
        // Flush rewrite rules if needed
        flush_rewrite_rules();
        
        // Set default options
        add_option('wp_toc_version', self::VERSION);
        
        do_action('wp_toc_activated');
    }
    
    /**
     * Plugin deactivation hook
     * 
     * @return void
     */
    public static function deactivate(): void {
        // Clean up if needed
        flush_rewrite_rules();
        
        do_action('wp_toc_deactivated');
    }
    
    /**
     * Plugin uninstall hook
     * 
     * @return void
     */
    public static function uninstall(): void {
        // Remove plugin data
        delete_option('wp_toc_version');
        
        // Remove post meta
        delete_post_meta_by_key(self::META_KEY_DISABLE);
        
        do_action('wp_toc_uninstalled');
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    WP_Table_Of_Contents::get_instance();
});

// Activation/Deactivation hooks
register_activation_hook(__FILE__, [WP_Table_Of_Contents::class, 'activate']);
register_deactivation_hook(__FILE__, [WP_Table_Of_Contents::class, 'deactivate']);

// Uninstall hook
if (function_exists('register_uninstall_hook')) {
    register_uninstall_hook(__FILE__, [WP_Table_Of_Contents::class, 'uninstall']);
}