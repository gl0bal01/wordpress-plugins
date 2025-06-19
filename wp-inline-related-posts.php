<?php
/**
 * Plugin Name: WP Inline Related Posts
 * Plugin URI: https://github.com/gl0bal01/wordpress-plugins/wp-inline-related-posts
 * Description: Intelligently inserts unique related posts blocks inline within your content based on categories with DOM manipulation and advanced filtering.
 * Version: 1.2.0
 * Author: gl0bal01
 * Author URI: https://gl0bal01.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-inline-related-posts
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * Update URI: false
 * 
 * SETUP INSTRUCTIONS:
 * ===================
 * 
 * 1. Activate the plugin through the WordPress admin
 * 
 * 2. Go to Settings > Inline Related Posts to configure:
 *    - Paragraph position for first insertion
 *    - Repeat frequency for additional insertions
 *    - Number of related posts to show
 *    - Post types where the plugin should work
 * 
 * 3. Optional - Add these constants to wp-config.php for advanced configuration:
 * 
 *    // Optional - Test mode (only show on specific post)
 *    define('WP_INLINE_RELATED_TEST_MODE', false);
 *    define('WP_INLINE_RELATED_TEST_POST_ID', 12345);
 * 
 *    // Optional - Global disable flag
 *    define('WP_INLINE_RELATED_DISABLED', false);
 * 
 *    // Optional - Performance settings
 *    define('WP_INLINE_RELATED_CACHE_ENABLED', true);
 *    define('WP_INLINE_RELATED_CACHE_DURATION', 3600);
 * 
 * 4. The plugin will automatically insert related posts based on your settings
 * 
 * 5. Use shortcode [inline_related_posts count="2"] for manual insertion
 * 
 * FEATURES:
 * =========
 * 
 * - Smart content insertion using DOM manipulation
 * - Category-based related posts matching
 * - Configurable insertion positions and frequency
 * - Excludes Instagram embeds and other special content
 * - Caching for improved performance
 * - Admin interface for easy configuration
 * - Shortcode support for manual placement
 * - Test mode for development
 * - Global disable functionality
 * 
 * FILTERS & ACTIONS:
 * ==================
 * 
 * // Customize related posts query
 * add_filter('wp_inline_related_posts_query_args', function($args, $post_id) {
 *     $args['meta_key'] = 'featured_post';
 *     $args['meta_value'] = '1';
 *     return $args;
 * }, 10, 2);
 * 
 * // Customize HTML output
 * add_filter('wp_inline_related_posts_html', function($html, $posts) {
 *     // Custom HTML styling
 *     return $html;
 * }, 10, 2);
 * 
 * // Disable on specific posts
 * add_filter('wp_inline_related_posts_enabled', function($enabled, $post_id) {
 *     if ($post_id === 123) return false;
 *     return $enabled;
 * }, 10, 2);
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Define plugin constants
define('WP_INLINE_RELATED_VERSION', '1.2.0');
define('WP_INLINE_RELATED_FILE', __FILE__);

// Configuration constants with defaults
if (!defined('WP_INLINE_RELATED_TEST_MODE')) {
    define('WP_INLINE_RELATED_TEST_MODE', false);
}

if (!defined('WP_INLINE_RELATED_TEST_POST_ID')) {
    define('WP_INLINE_RELATED_TEST_POST_ID', 0);
}

if (!defined('WP_INLINE_RELATED_DISABLED')) {
    define('WP_INLINE_RELATED_DISABLED', false);
}

if (!defined('WP_INLINE_RELATED_CACHE_ENABLED')) {
    define('WP_INLINE_RELATED_CACHE_ENABLED', true);
}

if (!defined('WP_INLINE_RELATED_CACHE_DURATION')) {
    define('WP_INLINE_RELATED_CACHE_DURATION', 3600);
}

/**
 * Main plugin class
 */
class WP_Inline_Related_Posts {

    private static $instance = null;
    
    // Plugin constants
    const DEFAULT_PARAGRAPH_POSITION = 3;
    const DEFAULT_NUMBER_OF_POSTS    = 2;
    const MAX_RELATED_POSTS          = 5;
    const DEFAULT_REPEAT_EVERY       = 4;
    const CACHE_GROUP               = 'wp_inline_related_posts';
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init() {
        // Load text domain
        add_action('plugins_loaded', [$this, 'loadTextDomain']);
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'addSettingsPage']);
            add_action('admin_init', [$this, 'registerSettings']);
            add_action('admin_notices', [$this, 'adminNotices']);
        }
        
        // Frontend hooks
        add_filter('the_content', [$this, 'insertRelatedPosts'], 20);
        add_shortcode('inline_related_posts', [$this, 'relatedPostsShortcode']);
        
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Cache management
        add_action('save_post', [$this, 'clearCacheOnPostSave']);
        add_action('wp_inline_related_cleanup', [$this, 'cleanupCache']);
    }

    /**
     * Load text domain for translations
     */
    public function loadTextDomain() {
        load_plugin_textdomain('wp-inline-related-posts', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /* ------------------------------
     *  Admin Settings
     * ------------------------------ */

    public function addSettingsPage() {
        add_options_page(
            __('Inline Related Posts', 'wp-inline-related-posts'),
            __('Inline Related Posts', 'wp-inline-related-posts'),
            'manage_options',
            'wp-inline-related-posts',
            [$this, 'settingsPageContent']
        );
    }

    public function registerSettings() {
        register_setting(
            'wp_inline_related_posts_options',
            'wp_inline_related_posts_settings',
            [$this, 'sanitizeSettings']
        );

        add_settings_section(
            'wp_inline_related_posts_section',
            __('Plugin Configuration', 'wp-inline-related-posts'),
            [$this, 'settingsSectionCallback'],
            'wp-inline-related-posts'
        );

        $this->addSettingsFields();
    }

    private function addSettingsFields() {
        $fields = [
            'paragraph_position' => __('First Insertion Position (Paragraph #)', 'wp-inline-related-posts'),
            'repeat_every' => __('Repeat Every X Paragraphs', 'wp-inline-related-posts'),
            'number_of_posts' => __('Number of Related Posts', 'wp-inline-related-posts'),
            'post_types' => __('Enable on Post Types', 'wp-inline-related-posts'),
            'date_range' => __('Post Age Limit', 'wp-inline-related-posts'),
            'cache_enabled' => __('Enable Caching', 'wp-inline-related-posts')
        ];

        foreach ($fields as $field => $label) {
            add_settings_field(
                $field,
                $label,
                [$this, $field . 'Callback'],
                'wp-inline-related-posts',
                'wp_inline_related_posts_section'
            );
        }
    }

    public function sanitizeSettings($input) {
        $output = [];

        // Paragraph position
        $pos = absint($input['paragraph_position'] ?? 0);
        $output['paragraph_position'] = $pos >= 1 ? $pos : self::DEFAULT_PARAGRAPH_POSITION;

        // Repeat frequency
        $output['repeat_every'] = absint($input['repeat_every'] ?? self::DEFAULT_REPEAT_EVERY);

        // Number of posts
        $num = absint($input['number_of_posts'] ?? 0);
        $output['number_of_posts'] = ($num >= 1 && $num <= self::MAX_RELATED_POSTS) 
            ? $num : self::DEFAULT_NUMBER_OF_POSTS;

        // Post types
        if (!empty($input['post_types']) && is_array($input['post_types'])) {
            $output['post_types'] = array_map('sanitize_text_field', $input['post_types']);
        } else {
            $output['post_types'] = ['post'];
        }

        // Date range
        $dateRange = sanitize_text_field($input['date_range'] ?? '1 year');
        $output['date_range'] = in_array($dateRange, ['1 month', '3 months', '6 months', '1 year', '2 years', 'all']) 
            ? $dateRange : '1 year';

        // Cache enabled
        $output['cache_enabled'] = !empty($input['cache_enabled']);

        return $output;
    }

    public function settingsSectionCallback() {
        echo '<p>' . esc_html__('Configure how and where related posts are inserted into your content.', 'wp-inline-related-posts') . '</p>';
    }

    public function paragraph_positionCallback() {
        $opts = get_option('wp_inline_related_posts_settings', []);
        $val = $opts['paragraph_position'] ?? self::DEFAULT_PARAGRAPH_POSITION;
        printf(
            '<input type="number" min="1" max="20" name="wp_inline_related_posts_settings[paragraph_position]" value="%d" class="small-text" />',
            esc_attr($val)
        );
        echo '<p class="description">' . esc_html__('Insert first related posts block after this paragraph number.', 'wp-inline-related-posts') . '</p>';
    }

    public function repeat_everyCallback() {
        $opts = get_option('wp_inline_related_posts_settings', []);
        $val = $opts['repeat_every'] ?? self::DEFAULT_REPEAT_EVERY;
        printf(
            '<input type="number" min="0" max="20" name="wp_inline_related_posts_settings[repeat_every]" value="%d" class="small-text" />',
            esc_attr($val)
        );
        echo '<p class="description">' . esc_html__('Insert additional blocks every X paragraphs. Set to 0 to disable repeating.', 'wp-inline-related-posts') . '</p>';
    }

    public function number_of_postsCallback() {
        $opts = get_option('wp_inline_related_posts_settings', []);
        $val = $opts['number_of_posts'] ?? self::DEFAULT_NUMBER_OF_POSTS;
        printf(
            '<input type="number" min="1" max="%d" name="wp_inline_related_posts_settings[number_of_posts]" value="%d" class="small-text" />',
            self::MAX_RELATED_POSTS,
            esc_attr($val)
        );
        echo '<p class="description">' . sprintf(esc_html__('Number of related posts to show (max %d).', 'wp-inline-related-posts'), self::MAX_RELATED_POSTS) . '</p>';
    }

    public function post_typesCallback() {
        $opts = get_option('wp_inline_related_posts_settings', []);
        $enabledTypes = $opts['post_types'] ?? ['post'];
        $postTypes = get_post_types(['public' => true], 'objects');
        
        foreach ($postTypes as $postType) {
            if ($postType->name === 'attachment') continue;
            
            $checked = in_array($postType->name, $enabledTypes) ? 'checked="checked"' : '';
            printf(
                '<label><input type="checkbox" name="wp_inline_related_posts_settings[post_types][]" value="%s" %s /> %s</label><br>',
                esc_attr($postType->name),
                $checked,
                esc_html($postType->label)
            );
        }
        echo '<p class="description">' . esc_html__('Select post types where related posts should be inserted.', 'wp-inline-related-posts') . '</p>';
    }

    public function date_rangeCallback() {
        $opts = get_option('wp_inline_related_posts_settings', []);
        $val = $opts['date_range'] ?? '1 year';
        
        $options = [
            '1 month' => __('Last Month', 'wp-inline-related-posts'),
            '3 months' => __('Last 3 Months', 'wp-inline-related-posts'),
            '6 months' => __('Last 6 Months', 'wp-inline-related-posts'),
            '1 year' => __('Last Year', 'wp-inline-related-posts'),
            '2 years' => __('Last 2 Years', 'wp-inline-related-posts'),
            'all' => __('All Time', 'wp-inline-related-posts')
        ];
        
        echo '<select name="wp_inline_related_posts_settings[date_range]">';
        foreach ($options as $value => $label) {
            $selected = selected($val, $value, false);
            printf('<option value="%s" %s>%s</option>', esc_attr($value), $selected, esc_html($label));
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Only show related posts from this time period.', 'wp-inline-related-posts') . '</p>';
    }

    public function cache_enabledCallback() {
        $opts = get_option('wp_inline_related_posts_settings', []);
        $val = $opts['cache_enabled'] ?? true;
        printf(
            '<input type="checkbox" name="wp_inline_related_posts_settings[cache_enabled]" value="1" %s />',
            checked($val, true, false)
        );
        echo ' <label>' . esc_html__('Enable caching for better performance', 'wp-inline-related-posts') . '</label>';
    }

    public function settingsPageContent() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-inline-related-posts'));
        }
        
        $stats = $this->getPluginStats();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (WP_INLINE_RELATED_TEST_MODE): ?>
                <div class="notice notice-warning">
                    <p><?php printf(esc_html__('Test mode is active for post ID %d. Disable in production!', 'wp-inline-related-posts'), WP_INLINE_RELATED_TEST_POST_ID); ?></p>
                </div>
            <?php endif; ?>
            
            <div style="display: flex; gap: 20px;">
                <div style="flex: 2;">
                    <form action="options.php" method="post">
                        <?php
                        settings_fields('wp_inline_related_posts_options');
                        do_settings_sections('wp-inline-related-posts');
                        submit_button();
                        ?>
                    </form>
                </div>
                
                <div style="flex: 1;">
                    <div class="postbox">
                        <h3 class="hndle"><span><?php _e('Plugin Statistics', 'wp-inline-related-posts'); ?></span></h3>
                        <div class="inside">
                            <p><strong><?php _e('Cache Status:', 'wp-inline-related-posts'); ?></strong> 
                               <?php echo $stats['cache_enabled'] ? __('Enabled', 'wp-inline-related-posts') : __('Disabled', 'wp-inline-related-posts'); ?>
                            </p>
                            <p><strong><?php _e('Cached Entries:', 'wp-inline-related-posts'); ?></strong> <?php echo esc_html($stats['cache_count']); ?></p>
                            <p><strong><?php _e('Test Mode:', 'wp-inline-related-posts'); ?></strong> 
                               <?php echo WP_INLINE_RELATED_TEST_MODE ? __('Active', 'wp-inline-related-posts') : __('Inactive', 'wp-inline-related-posts'); ?>
                            </p>
                            <p>
                                <a href="<?php echo esc_url(add_query_arg(['clear_cache' => '1'])); ?>" class="button button-secondary">
                                    <?php _e('Clear Cache', 'wp-inline-related-posts'); ?>
                                </a>
                            </p>
                        </div>
                    </div>
                    
                    <div class="postbox">
                        <h3 class="hndle"><span><?php _e('Shortcode Usage', 'wp-inline-related-posts'); ?></span></h3>
                        <div class="inside">
                            <p><?php _e('Use this shortcode to manually insert related posts:', 'wp-inline-related-posts'); ?></p>
                            <code>[inline_related_posts count="2"]</code>
                            <p class="description"><?php _e('The count parameter is optional and defaults to your plugin settings.', 'wp-inline-related-posts'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /* ------------------------------
     *  Core Functionality
     * ------------------------------ */

    private function getRelatedPosts($postId, $count = null, $excludeIds = []) {
        if (!$postId) {
            return [];
        }

        $opts = get_option('wp_inline_related_posts_settings', []);
        $count = is_null($count) ? ($opts['number_of_posts'] ?? self::DEFAULT_NUMBER_OF_POSTS) : absint($count);
        $count = min(max(1, $count), self::MAX_RELATED_POSTS);

        // Check cache first
        if ($this->isCacheEnabled()) {
            $cacheKey = "related_posts_{$postId}_{$count}_" . md5(serialize($excludeIds));
            $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);
            if ($cached !== false) {
                return $cached;
            }
        }

        $exclude = array_merge([$postId], $excludeIds);
        $categories = wp_get_post_categories($postId);
        
        if (empty($categories)) {
            return [];
        }

        // Build date query
        $dateQuery = null;
        $dateRange = $opts['date_range'] ?? '1 year';
        if ($dateRange !== 'all') {
            $dateQuery = [
                'after' => date('Y-m-d', strtotime('-' . $dateRange)),
                'inclusive' => true
            ];
        }

        $queryArgs = [
            'category__in' => $categories,
            'post__not_in' => $exclude,
            'posts_per_page' => $count,
            'orderby' => 'rand',
            'post_status' => 'publish',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        if ($dateQuery) {
            $queryArgs['date_query'] = [$dateQuery];
        }

        /**
         * Filter related posts query arguments
         *
         * @param array $queryArgs WP_Query arguments
         * @param int $postId Current post ID
         */
        $queryArgs = apply_filters('wp_inline_related_posts_query_args', $queryArgs, $postId);

        $query = new WP_Query($queryArgs);
        $posts = $query->posts;

        // Cache the result
        if ($this->isCacheEnabled()) {
            wp_cache_set($cacheKey, $posts, self::CACHE_GROUP, WP_INLINE_RELATED_CACHE_DURATION);
        }

        return $posts;
    }

    private function generateRelatedPostsHtml($posts) {
        if (empty($posts)) {
            return '';
        }

        $html = '<div class="wp-inline-related-posts" style="background:#f8f9fa;border-radius:8px;border:2px solid #e9ecef;padding:1.5em 1.5em 1em 1.5em;margin:2em 0;box-shadow:0 2px 4px rgba(0,0,0,0.1);">';
        $html .= '<h4 style="margin:0 0 1em 0;color:#495057;font-size:1.1em;font-weight:600;">' . esc_html__('Related Articles', 'wp-inline-related-posts') . '</h4>';
        $html .= '<ul style="margin:0;padding:0;list-style:none;">';
        
        foreach ($posts as $post) {
            $url = esc_url(get_permalink($post->ID));
            $title = esc_html(wp_trim_words(get_the_title($post->ID), 12, '…'));
            $date = get_the_date('', $post->ID);
            
            $html .= '<li style="margin:0 0 0.5em 0;padding:0;">';
            $html .= '<a href="' . $url . '" style="color:#007cba;text-decoration:none;font-weight:500;" title="' . esc_attr(get_the_title($post->ID)) . '">' . $title . '</a>';
            $html .= ' <small style="color:#6c757d;">(' . esc_html($date) . ')</small>';
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';

        /**
         * Filter the generated HTML for related posts
         *
         * @param string $html Generated HTML
         * @param array $posts Array of post objects
         */
        return apply_filters('wp_inline_related_posts_html', $html, $posts);
    }

    public function insertRelatedPosts($content) {
        // Check global disable flag
        if (WP_INLINE_RELATED_DISABLED) {
            return $content;
        }

        // Check legacy global variable
        global $is_disable;
        if (isset($is_disable) && (int)$is_disable === 1) {
            return $content;
        }

        // Basic context checks
        if (!is_singular() || !in_the_loop() || !is_main_query() || is_feed()) {
            return $content;
        }

        // Test mode check
        if (WP_INLINE_RELATED_TEST_MODE && get_the_ID() !== WP_INLINE_RELATED_TEST_POST_ID) {
            return $content;
        }

        // Post type check
        $opts = get_option('wp_inline_related_posts_settings', []);
        $enabledTypes = $opts['post_types'] ?? ['post'];
        if (!in_array(get_post_type(), $enabledTypes, true)) {
            return $content;
        }

        // Allow filtering of enabled status
        $enabled = apply_filters('wp_inline_related_posts_enabled', true, get_the_ID());
        if (!$enabled) {
            return $content;
        }

        return $this->processContentWithDom($content, $opts);
    }

    private function processContentWithDom($content, $opts) {
        $paragraphPos = $opts['paragraph_position'] ?? self::DEFAULT_PARAGRAPH_POSITION;
        if ($paragraphPos < 1) {
            return $content;
        }

        $repeatEvery = absint($opts['repeat_every'] ?? self::DEFAULT_REPEAT_EVERY);
        $count = absint($opts['number_of_posts'] ?? self::DEFAULT_NUMBER_OF_POSTS);

        try {
            // Load content into DOM
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            // Exclude paragraphs inside Instagram embeds and other special content
            $paragraphs = $xpath->query('//div/p[not(ancestor::blockquote[contains(@class,"instagram-media")]) and not(ancestor::*[contains(@class,"wp-block-embed")])]');
            $totalParagraphs = $paragraphs->length;

            if ($totalParagraphs < $paragraphPos) {
                return $content;
            }

            // Calculate insertion positions
            $positions = [$paragraphPos];
            if ($repeatEvery > 0) {
                $nextPos = $paragraphPos + $repeatEvery;
                while ($nextPos <= $totalParagraphs) {
                    $positions[] = $nextPos;
                    $nextPos += $repeatEvery;
                }
            }

            $usedIds = [];

            // Insert related posts at each position
            foreach ($positions as $position) {
                $node = $paragraphs->item($position - 1);
                if (!$node) continue;

                $relatedPosts = $this->getRelatedPosts(get_the_ID(), $count, $usedIds);
                if (empty($relatedPosts)) continue;

                // Track used IDs to avoid duplicates
                foreach ($relatedPosts as $relatedPost) {
                    $usedIds[] = $relatedPost->ID;
                }

                $fragment = $dom->createDocumentFragment();
                $fragment->appendXML($this->generateRelatedPostsHtml($relatedPosts));
                $node->parentNode->insertBefore($fragment, $node->nextSibling);
            }

            // Extract modified content
            $wrapper = $dom->getElementsByTagName('div')->item(0);
            $newContent = '';
            foreach ($wrapper->childNodes as $child) {
                $newContent .= $dom->saveHTML($child);
            }

            return $newContent;

        } catch (Exception $e) {
            error_log('WP Inline Related Posts DOM Error: ' . $e->getMessage());
            return $content;
        }
    }

    public function relatedPostsShortcode($atts) {
        $atts = shortcode_atts([
            'count' => null,
            'exclude' => ''
        ], $atts, 'inline_related_posts');

        $count = !empty($atts['count']) ? absint($atts['count']) : null;
        $exclude = !empty($atts['exclude']) ? array_map('absint', explode(',', $atts['exclude'])) : [];
        
        $relatedPosts = $this->getRelatedPosts(get_the_ID(), $count, $exclude);
        return $this->generateRelatedPostsHtml($relatedPosts);
    }

    /* ------------------------------
     *  Cache & Performance
     * ------------------------------ */

    private function isCacheEnabled() {
        $opts = get_option('wp_inline_related_posts_settings', []);
        return WP_INLINE_RELATED_CACHE_ENABLED && ($opts['cache_enabled'] ?? true);
    }

    public function clearCacheOnPostSave($postId) {
        if (wp_is_post_revision($postId)) return;
        
        $post = get_post($postId);
        if (!$post || $post->post_status !== 'publish') return;

        // Clear cache for this post and related posts
        wp_cache_delete_multiple([
            "related_posts_{$postId}_" . '*',
        ], self::CACHE_GROUP);
        
        wp_cache_flush_group(self::CACHE_GROUP);
    }

    public function cleanupCache() {
        wp_cache_flush_group(self::CACHE_GROUP);
    }

    private function getPluginStats() {
        return [
            'cache_enabled' => $this->isCacheEnabled(),
            'cache_count' => $this->getCacheCount(),
            'version' => WP_INLINE_RELATED_VERSION
        ];
    }

    private function getCacheCount() {
        // This is a simplified count - WordPress object cache doesn't provide easy counting
        return 'N/A';
    }

    /* ------------------------------
     *  Admin & Lifecycle
     * ------------------------------ */

    public function adminNotices() {
        if (isset($_GET['clear_cache']) && $_GET['clear_cache'] === '1' && current_user_can('manage_options')) {
            $this->cleanupCache();
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__('Cache cleared successfully.', 'wp-inline-related-posts') . '</p>';
            echo '</div>';
        }

        if (WP_INLINE_RELATED_TEST_MODE && current_user_can('manage_options')) {
            echo '<div class="notice notice-warning">';
            echo '<p>' . sprintf(esc_html__('Inline Related Posts: Test mode is active for post ID %d.', 'wp-inline-related-posts'), WP_INLINE_RELATED_TEST_POST_ID) . '</p>';
            echo '</div>';
        }
    }

    public function activate() {
        // Set default options
        add_option('wp_inline_related_posts_settings', [
            'paragraph_position' => self::DEFAULT_PARAGRAPH_POSITION,
            'repeat_every' => self::DEFAULT_REPEAT_EVERY,
            'number_of_posts' => self::DEFAULT_NUMBER_OF_POSTS,
            'post_types' => ['post'],
            'date_range' => '1 year',
            'cache_enabled' => true
        ]);

        // Schedule cleanup
        if (!wp_next_scheduled('wp_inline_related_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_inline_related_cleanup');
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook('wp_inline_related_cleanup');
        $this->cleanupCache();
    }

    /**
     * Get plugin configuration status
     */
    public function getConfigStatus() {
        $opts = get_option('wp_inline_related_posts_settings', []);
        return [
            'configured' => !empty($opts),
            'test_mode' => WP_INLINE_RELATED_TEST_MODE,
            'cache_enabled' => $this->isCacheEnabled(),
            'enabled_post_types' => $opts['post_types'] ?? ['post']
        ];
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    WP_Inline_Related_Posts::getInstance();
});

// Cleanup hook
add_action('wp_inline_related_cleanup', function() {
    $plugin = WP_Inline_Related_Posts::getInstance();
    $plugin->cleanupCache();
});

// Helper functions
function wp_inline_related_posts_is_enabled() {
    return !WP_INLINE_RELATED_DISABLED;
}

function wp_inline_related_posts_get_config() {
    $plugin = WP_Inline_Related_Posts::getInstance();
    return $plugin->getConfigStatus();
}