<?php
/**
 * Plugin Name: WP Inline Related Posts
 * Plugin URI: https://github.com/gl0bal01/wordpress-plugins/wp-inline-related-posts
 * Description: Intelligently inserts unique related posts blocks inline within your content based on categories with DOM manipulation and advanced filtering.
 * Version: 1.2.1
 * Author: gl0bal01
 * Author URI: https://gl0bal01.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-inline-related-posts
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8.3
 * Requires PHP: 8.2+
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
define('WP_INLINE_RELATED_VERSION', '1.2.1');
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
    const CACHE_GROUP                = 'wp_inline_related_posts';

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
        add_action('plugins_loaded', [$this, 'loadTextDomain']);

        if (is_admin()) {
            add_action('admin_menu', [$this, 'addSettingsPage']);
            add_action('admin_init', [$this, 'registerSettings']);
            add_action('admin_notices', [$this, 'adminNotices']);
        }

        add_filter('the_content', [$this, 'insertRelatedPosts'], 20);
        add_shortcode('inline_related_posts', [$this, 'relatedPostsShortcode']);

        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('save_post', [$this, 'clearCacheOnPostSave']);
        add_action('wp_inline_related_cleanup', [$this, 'cleanupCache']);
    }

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
            'repeat_every'       => __('Repeat Every X Paragraphs', 'wp-inline-related-posts'),
            'number_of_posts'    => __('Number of Related Posts', 'wp-inline-related-posts'),
            'post_types'         => __('Enable on Post Types', 'wp-inline-related-posts'),
            'date_range'         => __('Post Age Limit', 'wp-inline-related-posts'),
            'cache_enabled'      => __('Enable Caching', 'wp-inline-related-posts'),
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

        $pos = absint($input['paragraph_position'] ?? 0);
        $output['paragraph_position'] = $pos >= 1 ? $pos : self::DEFAULT_PARAGRAPH_POSITION;

        $output['repeat_every'] = absint($input['repeat_every'] ?? self::DEFAULT_REPEAT_EVERY);

        $num = absint($input['number_of_posts'] ?? 0);
        $output['number_of_posts'] = ($num >= 1 && $num <= self::MAX_RELATED_POSTS)
            ? $num
            : self::DEFAULT_NUMBER_OF_POSTS;

        if (!empty($input['post_types']) && is_array($input['post_types'])) {
            $output['post_types'] = array_map('sanitize_text_field', $input['post_types']);
        } else {
            $output['post_types'] = ['post'];
        }

        $dateRange = sanitize_text_field($input['date_range'] ?? '1 year');
        $allowedRanges = ['1 month', '3 months', '6 months', '1 year', '2 years', 'all'];
        $output['date_range'] = in_array($dateRange, $allowedRanges, true) ? $dateRange : '1 year';

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
            if ($postType->name === 'attachment') {
                continue;
            }

            $checked = in_array($postType->name, $enabledTypes, true) ? 'checked="checked"' : '';
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
            '1 month'  => __('Last Month', 'wp-inline-related-posts'),
            '3 months' => __('Last 3 Months', 'wp-inline-related-posts'),
            '6 months' => __('Last 6 Months', 'wp-inline-related-posts'),
            '1 year'   => __('Last Year', 'wp-inline-related-posts'),
            '2 years'  => __('Last 2 Years', 'wp-inline-related-posts'),
            'all'      => __('All Time', 'wp-inline-related-posts'),
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

        $dateRange = $opts['date_range'] ?? '1 year';
        $dateQuery = ($dateRange !== 'all')
            ? [['after' => date('Y-m-d', strtotime('-' . $dateRange)), 'inclusive' => true]]
            : [];

        $queryArgs = [
            'category__in'            => $categories,
            'post__not_in'            => $exclude,
            'posts_per_page'          => $count,
            'orderby'                 => 'rand',
            'post_status'             => 'publish',
            'no_found_rows'           => true,
            'update_post_meta_cache'  => false,
            'update_post_term_cache'  => false,
        ];

        if (!empty($dateQuery)) {
            $queryArgs['date_query'] = $dateQuery;
        }

        $queryArgs = apply_filters('wp_inline_related_posts_query_args', $queryArgs, $postId);
        $query = new WP_Query($queryArgs);
        $posts = $query->posts;

        if ($this->isCacheEnabled()) {
            wp_cache_set($cacheKey, $posts, self::CACHE_GROUP, WP_INLINE_RELATED_CACHE_DURATION);
        }

        return $posts;
    }

    private function generateRelatedPostsHtml($posts) {
        if (empty($posts)) {
            return '';
        }

        $html  = '<div class="wp-inline-related-posts" style="background:#f8f9fa;border-radius:8px;border:2px solid #e9ecef;padding:1.5em 1.5em 1em 1.5em;margin:2em 0;box-shadow:0 2px 4px rgba(0,0,0,0.1);">';
        $html .= '<h4 style="margin:0 0 1em 0;color:#495057;font-size:1.1em;font-weight:600;">' . esc_html__('Related Articles', 'wp-inline-related-posts') . '</h4>';
        $html .= '<ul style="margin:0;padding:0;list-style:none;">';

        foreach ($posts as $post) {
            $url   = esc_url(get_permalink($post->ID));
            $title = esc_html(wp_trim_words(get_the_title($post->ID), 12, '…'));
            $date  = esc_html(get_the_date('', $post->ID));

            $html .= "<li style='margin:0 0 .5em 0;padding:0;'><a href='{$url}' style='color:#007cba;text-decoration:none;font-weight:500;' title='{$title}'>{$title}</a> <small style='color:#6c757d;'>({$date})</small></li>";
        }

        $html .= '</ul></div>';
        return apply_filters('wp_inline_related_posts_html', $html, $posts);
    }

    public function insertRelatedPosts($content) {
        if (WP_INLINE_RELATED_DISABLED) {
            return $content;
        }

        global $is_disable;
        if (isset($is_disable) && (int)$is_disable === 1) {
            return $content;
        }

        if (!is_singular() || !in_the_loop() || !is_main_query() || is_feed()) {
            return $content;
        }

        if (WP_INLINE_RELATED_TEST_MODE && get_the_ID() !== WP_INLINE_RELATED_TEST_POST_ID) {
            return $content;
        }

        $opts = get_option('wp_inline_related_posts_settings', []);
        $enabledTypes = $opts['post_types'] ?? ['post'];
        if (!in_array(get_post_type(), $enabledTypes, true)) {
            return $content;
        }

        if (!apply_filters('wp_inline_related_posts_enabled', true, get_the_ID())) {
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
        $count       = absint($opts['number_of_posts'] ?? self::DEFAULT_NUMBER_OF_POSTS);

        try {
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            $paragraphs = $xpath->query('//div/p[not(ancestor::blockquote[contains(@class,"instagram-media")]) and not(ancestor::*[contains(@class,"wp-block-embed")])]');

            $totalParagraphs = $paragraphs->length;
            if ($totalParagraphs < $paragraphPos) {
                return $content;
            }

            $positions = [$paragraphPos];
            if ($repeatEvery > 0) {
                $next = $paragraphPos + $repeatEvery;
                while ($next <= $totalParagraphs) {
                    $positions[] = $next;
                    $next += $repeatEvery;
                }
            }

            $usedIds = [];

            foreach ($positions as $position) {
                $node = $paragraphs->item($position - 1);
                if (!$node) continue;

                $relatedPosts = $this->getRelatedPosts(get_the_ID(), $count, $usedIds);
                if (empty($relatedPosts)) continue;

                foreach ($relatedPosts as $p) {
                    $usedIds[] = $p->ID;
                }

                $html = $this->generateRelatedPostsHtml($relatedPosts);
                if (empty($html)) continue;

                $fragment = $dom->createDocumentFragment();
                $ok = @$fragment->appendXML($html);

                if (!$ok || !$fragment->hasChildNodes()) {
                    // fallback to import if appendXML fails
                    $tmp = new DOMDocument();
                    libxml_use_internal_errors(true);
                    $tmp->loadHTML('<?xml encoding="UTF-8">' . $html);
                    libxml_clear_errors();

                    $body = $tmp->getElementsByTagName('body')->item(0);
                    if ($body && $body->firstChild) {
                        $import = $dom->importNode($body->firstChild, true);
                        if ($import) {
                            $node->parentNode->insertBefore($import, $node->nextSibling);
                        }
                    }
                } else {
                    $node->parentNode->insertBefore($fragment, $node->nextSibling);
                }
            }

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
            'count'   => null,
            'exclude' => '',
        ], $atts, 'inline_related_posts');

        $count   = !empty($atts['count']) ? absint($atts['count']) : null;
        $exclude = !empty($atts['exclude']) ? array_map('absint', explode(',', $atts['exclude'])) : [];
        $related = $this->getRelatedPosts(get_the_ID(), $count, $exclude);

        return $this->generateRelatedPostsHtml($related);
    }

    /* ------------------------------
     *  Cache & Admin
     * ------------------------------ */

    private function isCacheEnabled() {
        $opts = get_option('wp_inline_related_posts_settings', []);
        return WP_INLINE_RELATED_CACHE_ENABLED && ($opts['cache_enabled'] ?? true);
    }

    public function clearCacheOnPostSave($postId) {
        if (wp_is_post_revision($postId)) return;
        wp_cache_flush_group(self::CACHE_GROUP);
    }

    public function cleanupCache() {
        wp_cache_flush_group(self::CACHE_GROUP);
    }

    public function activate() {
        add_option('wp_inline_related_posts_settings', [
            'paragraph_position' => self::DEFAULT_PARAGRAPH_POSITION,
            'repeat_every'       => self::DEFAULT_REPEAT_EVERY,
            'number_of_posts'    => self::DEFAULT_NUMBER_OF_POSTS,
            'post_types'         => ['post'],
            'date_range'         => '1 year',
            'cache_enabled'      => true,
        ]);

        if (!wp_next_scheduled('wp_inline_related_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_inline_related_cleanup');
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook('wp_inline_related_cleanup');
        $this->cleanupCache();
    }

    public function adminNotices() {
        if (isset($_GET['clear_cache']) && $_GET['clear_cache'] === '1' && current_user_can('manage_options')) {
            $this->cleanupCache();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cache cleared successfully.', 'wp-inline-related-posts') . '</p></div>';
        }

        if (WP_INLINE_RELATED_TEST_MODE && current_user_can('manage_options')) {
            echo '<div class="notice notice-warning"><p>' .
                sprintf(esc_html__('Inline Related Posts: Test mode is active for post ID %d.', 'wp-inline-related-posts'), WP_INLINE_RELATED_TEST_POST_ID) .
                '</p></div>';
        }
    }

    public function getConfigStatus() {
        $opts = get_option('wp_inline_related_posts_settings', []);
        return [
            'configured'        => !empty($opts),
            'test_mode'         => WP_INLINE_RELATED_TEST_MODE,
            'cache_enabled'     => $this->isCacheEnabled(),
            'enabled_post_types'=> $opts['post_types'] ?? ['post'],
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

function wp_inline_related_posts_is_enabled() {
    return !WP_INLINE_RELATED_DISABLED;
}

function wp_inline_related_posts_get_config() {
    return WP_Inline_Related_Posts::getInstance()->getConfigStatus();
}
