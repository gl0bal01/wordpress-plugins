<?php
/**
 * Plugin Name: WP Google News Sitemap
 * Plugin URI: https://github.com/gl0bal01/wordpress-plugins/wp-google-news-sitemap
 * Description: Generates a Google News compliant XML sitemap for WordPress sites with caching, validation, and performance optimizations.
 * Version: 1.0.0
 * Author: gl0bal01
 * Author URI: https://gl0bal01.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-google-news-sitemap
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * Update URI: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Define plugin constants
define('WP_GOOGLE_NEWS_SITEMAP_VERSION', '1.0.0');

/**
 * Main plugin class
 */
class WP_Google_News_Sitemap {
    
    private static $instance = null;
    private $cacheGroup = 'wp_google_news_sitemap';
    private $sitemapCacheKey = 'sitemap_xml';
    
    private $defaultSettings = [
        'post_types' => ['post'],
        'posts_per_sitemap' => 1000,
        'max_age_days' => 2,
        'publication_name' => '',
        'publication_language' => 'fr',
        'cache_enabled' => true,
        'cache_duration' => 3600,
        'exclude_categories' => [],
        'exclude_tags' => [],
        'include_images' => true,
        'validate_xml' => true
    ];
    
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
        
        // Register rewrite rules
        add_action('init', [$this, 'registerRewriteRules']);
        add_filter('query_vars', [$this, 'addQueryVars']);
        add_action('template_redirect', [$this, 'handleSitemapRequest']);
        
        // Admin interface
        if (is_admin()) {
            add_action('admin_menu', [$this, 'addAdminMenu']);
            add_action('admin_init', [$this, 'initSettings']);
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'addSettingsLink']);
            add_action('admin_notices', [$this, 'adminNotices']);
        }
        
        // Clear cache when posts change
        add_action('wp_insert_post', [$this, 'clearCache'], 10, 2);
        add_action('transition_post_status', [$this, 'clearCacheOnStatusChange'], 10, 3);
        
        // Add to robots.txt
        add_action('do_robotstxt', [$this, 'addSitemapToRobots']);
        
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        register_uninstall_hook(__FILE__, [__CLASS__, 'uninstall']);
        
        // Cleanup hook
        add_action('wp_google_news_sitemap_cleanup', [$this, 'cleanupCache']);
    }
    
    /**
     * Load text domain
     */
    public function loadTextDomain() {
        load_plugin_textdomain('wp-google-news-sitemap', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Register rewrite rules
     */
    public function registerRewriteRules() {
        add_rewrite_rule('^sitemap-news\.xml$', 'index.php?google_news_sitemap=1', 'top');
        add_rewrite_rule('^sitemap_news\.xml$', 'index.php?google_news_sitemap=1', 'top');
        add_rewrite_rule('^news-sitemap\.xml$', 'index.php?google_news_sitemap=1', 'top');
        add_rewrite_rule('^googlenews\.xml$', 'index.php?google_news_sitemap=1', 'top');
    }
    
    /**
     * Add query variables
     */
    public function addQueryVars($vars) {
        $vars[] = 'google_news_sitemap';
        $vars[] = 'sitemap_debug';
        return $vars;
    }
    
    /**
     * Handle sitemap requests
     */
    public function handleSitemapRequest() {
        if (!get_query_var('google_news_sitemap')) {
            return;
        }
        
        // Check if generation is allowed
        if (get_option('blog_public') == '0') {
            header('HTTP/1.1 403 Forbidden');
            exit('Sitemap generation not allowed');
        }
        
        // Debug mode
        if (get_query_var('sitemap_debug') === '1') {
            $this->handleDebugRequest();
            return;
        }
        
        try {
            $this->generateSitemap();
        } catch (Exception $e) {
            error_log('Google News Sitemap Error: ' . $e->getMessage());
            $this->outputErrorResponse($e->getMessage());
        }
    }
    
    /**
     * Generate and output sitemap
     */
    private function generateSitemap() {
        // Try cache first
        if ($this->isCacheEnabled()) {
            $cached = wp_cache_get($this->sitemapCacheKey, $this->cacheGroup);
            if ($cached !== false && $this->validateCachedSitemap($cached)) {
                $this->outputSitemap($cached['content']);
                return;
            }
        }
        
        // Generate new sitemap
        $sitemap = $this->buildSitemap();
        
        // Validate if enabled
        $settings = $this->getSettings();
        if ($settings['validate_xml'] && !$this->validateXml($sitemap)) {
            throw new Exception('Generated XML is not valid');
        }
        
        // Cache the sitemap
        if ($this->isCacheEnabled()) {
            $cacheData = [
                'content' => $sitemap,
                'generated' => time(),
                'hash' => md5($sitemap)
            ];
            wp_cache_set($this->sitemapCacheKey, $cacheData, $this->cacheGroup, $settings['cache_duration']);
            update_option('wp_google_news_sitemap_last_generated', time());
        }
        
        $this->outputSitemap($sitemap);
    }
    
    /**
     * Build XML sitemap
     */
    private function buildSitemap() {
        $settings = $this->getSettings();
        
        // Create DOM document
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        
        // Root element
        $urlset = $dom->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $urlset->setAttribute('xmlns:news', 'http://www.google.com/schemas/sitemap-news/0.9');
        $dom->appendChild($urlset);
        
        // Get eligible posts
        $posts = $this->getEligiblePosts();
        
        foreach ($posts as $post) {
            $this->addPostToSitemap($dom, $urlset, $post, $settings);
        }
        
        $xml = $dom->saveXML();
        if ($xml === false) {
            throw new Exception('Failed to generate XML sitemap');
        }
        
        return $xml;
    }
    
    /**
     * Get eligible posts for sitemap
     */
    private function getEligiblePosts() {
        $settings = $this->getSettings();
        
        $args = [
            'post_type' => $settings['post_types'],
            'post_status' => 'publish',
            'posts_per_page' => $settings['posts_per_sitemap'],
            'orderby' => 'date',
            'order' => 'DESC',
            'date_query' => [
                'after' => date('Y-m-d H:i:s', strtotime('-' . $settings['max_age_days'] . ' days'))
            ]
        ];
        
        // Exclude categories
        if (!empty($settings['exclude_categories'])) {
            $args['category__not_in'] = $settings['exclude_categories'];
        }
        
        // Exclude tags  
        if (!empty($settings['exclude_tags'])) {
            $args['tag__not_in'] = $settings['exclude_tags'];
        }
        
        $args = apply_filters('wp_google_news_sitemap_query_args', $args);
        
        $query = new WP_Query($args);
        return $query->posts;
    }
    
    /**
     * Add post to sitemap
     */
    private function addPostToSitemap($dom, $urlset, $post, $settings) {
        // URL element
        $url = $dom->createElement('url');
        
        // Location
        $loc = $dom->createElement('loc', esc_url(get_permalink($post->ID)));
        $url->appendChild($loc);
        
        // News element
        $news = $dom->createElement('news:news');
        
        // Publication
        $publication = $dom->createElement('news:publication');
        
        $pubName = $dom->createElement('news:name');
        $pubName->appendChild($dom->createTextNode($this->sanitizeXmlContent($settings['publication_name'])));
        $publication->appendChild($pubName);
        
        $pubLanguage = $dom->createElement('news:language', $settings['publication_language']);
        $publication->appendChild($pubLanguage);
        
        $news->appendChild($publication);
        
        // Publication date
        $pubDate = get_post_time('Y-m-d\TH:i:sP', false, $post->ID);
        $publicationDate = $dom->createElement('news:publication_date', $pubDate);
        $news->appendChild($publicationDate);
        
        // Title
        $title = $dom->createElement('news:title');
        $title->appendChild($dom->createTextNode($this->sanitizeXmlContent(get_the_title($post->ID))));
        $news->appendChild($title);
        
        // Keywords
        $keywords = $this->getPostKeywords($post->ID);
        if (!empty($keywords)) {
            $keywordsElement = $dom->createElement('news:keywords');
            $keywordsElement->appendChild($dom->createTextNode($this->sanitizeXmlContent($keywords)));
            $news->appendChild($keywordsElement);
        }
        
        // Stock tickers (if available)
        $stockTickers = get_post_meta($post->ID, '_stock_tickers', true);
        if (!empty($stockTickers) && is_array($stockTickers)) {
            $validTickers = array_filter($stockTickers, function($ticker) {
                return preg_match('/^[A-Z]{1,6}$/', $ticker);
            });
            if (!empty($validTickers)) {
                $stockElement = $dom->createElement('news:stock_tickers');
                $stockElement->appendChild($dom->createTextNode(implode(', ', $validTickers)));
                $news->appendChild($stockElement);
            }
        }
        
        $url->appendChild($news);
        
        // Add images if enabled
        if ($settings['include_images']) {
            $this->addPostImages($dom, $url, $post->ID);
        }
        
        $urlset->appendChild($url);
        
        do_action('wp_google_news_sitemap_url_element', $url, $post, $settings);
    }
    
    /**
     * Get post keywords
     */
    private function getPostKeywords($postId) {
        $keywords = [];
        
        // Categories
        $categories = get_the_category($postId);
        foreach ($categories as $category) {
            $keywords[] = $category->name;
        }
        
        // Tags
        $tags = get_the_tags($postId);
        if ($tags) {
            foreach ($tags as $tag) {
                $keywords[] = $tag->name;
            }
        }
        
        // Custom keywords
        $customKeywords = get_post_meta($postId, 'news_keywords', true);
        if ($customKeywords) {
            $keywords = array_merge($keywords, explode(',', $customKeywords));
        }
        
        // Limit and clean
        $keywords = array_slice(array_unique($keywords), 0, 10);
        $keywords = array_map(function($keyword) {
            return mb_substr(trim($keyword), 0, 50);
        }, $keywords);
        
        $keywords = apply_filters('wp_google_news_sitemap_post_keywords', $keywords, $postId);
        
        return implode(', ', $keywords);
    }
    
    /**
     * Add post images
     */
    private function addPostImages($dom, $url, $postId) {
        $thumbnailId = get_post_thumbnail_id($postId);
        if (!$thumbnailId) return;
        
        $imageUrl = wp_get_attachment_image_url($thumbnailId, 'full');
        if (!$imageUrl) return;
        
        $image = $dom->createElement('image:image');
        $image->setAttribute('xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
        
        $imageLoc = $dom->createElement('image:loc', esc_url($imageUrl));
        $image->appendChild($imageLoc);
        
        // Image title
        $imageTitle = get_the_title($thumbnailId);
        if (!empty($imageTitle)) {
            $imageTitleElement = $dom->createElement('image:title');
            $imageTitleElement->appendChild($dom->createTextNode($this->sanitizeXmlContent($imageTitle)));
            $image->appendChild($imageTitleElement);
        }
        
        // Image caption
        $imageCaption = wp_get_attachment_caption($thumbnailId);
        if (!empty($imageCaption)) {
            $imageCaptionElement = $dom->createElement('image:caption');
            $imageCaptionElement->appendChild($dom->createTextNode($this->sanitizeXmlContent($imageCaption)));
            $image->appendChild($imageCaptionElement);
        }
        
        $url->appendChild($image);
    }
    
    /**
     * Sanitize content for XML
     */
    private function sanitizeXmlContent($content) {
        $content = wp_strip_all_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
        return trim($content);
    }
    
    /**
     * Validate XML
     */
    private function validateXml($xml) {
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        $dom = new DOMDocument();
        $isValid = $dom->loadXML($xml);
        
        if (!$isValid) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                error_log("XML Validation Error: {$error->message}");
            }
            libxml_clear_errors();
            return false;
        }
        
        return true;
    }
    
    /**
     * Output sitemap
     */
    private function outputSitemap($sitemap) {
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');
        
        $settings = $this->getSettings();
        $cacheDuration = $settings['cache_duration'];
        header("Cache-Control: public, max-age={$cacheDuration}");
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + $cacheDuration));
        
        echo $sitemap;
        exit;
    }
    
    /**
     * Output error response
     */
    private function outputErrorResponse($message) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/xml; charset=UTF-8');
        
        $errorXml = '<?xml version="1.0" encoding="UTF-8"?>';
        $errorXml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">';
        $errorXml .= '<!-- Error: ' . esc_html($message) . ' -->';
        $errorXml .= '</urlset>';
        
        echo $errorXml;
        exit;
    }
    
    /**
     * Handle debug request
     */
    private function handleDebugRequest() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'wp-google-news-sitemap'));
        }
        
        header('Content-Type: text/html; charset=UTF-8');
        
        echo '<html><head><title>Google News Sitemap Debug</title></head><body>';
        echo '<h1>Google News Sitemap Debug Information</h1>';
        
        // Plugin status
        echo '<h2>Plugin Status</h2>';
        echo '<ul>';
        echo '<li>Plugin Version: ' . esc_html(WP_GOOGLE_NEWS_SITEMAP_VERSION) . '</li>';
        echo '<li>WordPress Version: ' . esc_html(get_bloginfo('version')) . '</li>';
        echo '<li>PHP Version: ' . esc_html(PHP_VERSION) . '</li>';
        echo '<li>Site URL: ' . esc_html(home_url()) . '</li>';
        echo '</ul>';
        
        // Settings
        $settings = $this->getSettings();
        echo '<h2>Plugin Settings</h2>';
        echo '<pre>' . esc_html(print_r($settings, true)) . '</pre>';
        
        // Recent eligible posts
        echo '<h2>Recent Eligible Posts</h2>';
        $posts = $this->getEligiblePosts();
        
        if (empty($posts)) {
            echo '<p>No eligible posts found.</p>';
        } else {
            echo '<ul>';
            foreach ($posts as $post) {
                echo '<li>' . esc_html($post->post_title) . ' (' . esc_html($post->post_date) . ')</li>';
            }
            echo '</ul>';
        }
        
        echo '</body></html>';
        exit;
    }
    
    /**
     * Cache management
     */
    private function isCacheEnabled() {
        $settings = $this->getSettings();
        return $settings['cache_enabled'] && !defined('WP_DEBUG') || !WP_DEBUG;
    }
    
    private function validateCachedSitemap($cached) {
        if (!isset($cached['content'], $cached['generated'], $cached['hash'])) {
            return false;
        }
        
        $settings = $this->getSettings();
        $duration = $settings['cache_duration'];
        
        if (time() - $cached['generated'] > $duration) {
            return false;
        }
        
        if (md5($cached['content']) !== $cached['hash']) {
            return false;
        }
        
        return true;
    }
    
    public function clearCache($postId = null, $post = null) {
        if ($post && !in_array($post->post_type, $this->getSettings()['post_types'])) {
            return;
        }
        
        wp_cache_delete($this->sitemapCacheKey, $this->cacheGroup);
        do_action('wp_google_news_sitemap_cache_cleared');
    }
    
    public function clearCacheOnStatusChange($newStatus, $oldStatus, $post) {
        $settings = $this->getSettings();
        if (in_array($post->post_type, $settings['post_types']) && 
            ($newStatus === 'publish' || $oldStatus === 'publish')) {
            $this->clearCache();
        }
    }
    
    public function cleanupCache() {
        // Cleanup is handled automatically by WordPress object cache
    }
    
    /**
     * Settings management
     */
    public function getSettings() {
        $settings = get_option('wp_google_news_sitemap_settings', []);
        $settings = array_merge($this->defaultSettings, $settings);
        
        if (empty($settings['publication_name'])) {
            $settings['publication_name'] = get_bloginfo('name');
        }
        
        return $settings;
    }
    
    /**
     * Admin interface
     */
    public function addAdminMenu() {
        add_options_page(
            __('Google News Sitemap', 'wp-google-news-sitemap'),
            __('Google News Sitemap', 'wp-google-news-sitemap'),
            'manage_options',
            'wp-google-news-sitemap',
            [$this, 'renderSettingsPage']
        );
    }
    
    public function initSettings() {
        register_setting(
            'wp-google-news-sitemap',
            'wp_google_news_sitemap_settings',
            [$this, 'sanitizeSettings']
        );
        
        add_settings_section(
            'general',
            __('General Settings', 'wp-google-news-sitemap'),
            null,
            'wp-google-news-sitemap'
        );
        
        $this->addSettingsFields();
    }
    
    private function addSettingsFields() {
        $fields = [
            'publication_name' => __('Publication Name', 'wp-google-news-sitemap'),
            'publication_language' => __('Publication Language', 'wp-google-news-sitemap'),
            'max_age_days' => __('Maximum Age (Days)', 'wp-google-news-sitemap'),
            'posts_per_sitemap' => __('Posts per Sitemap', 'wp-google-news-sitemap'),
            'cache_enabled' => __('Enable Caching', 'wp-google-news-sitemap'),
            'cache_duration' => __('Cache Duration (Seconds)', 'wp-google-news-sitemap'),
            'include_images' => __('Include Images', 'wp-google-news-sitemap'),
            'validate_xml' => __('Validate XML', 'wp-google-news-sitemap')
        ];
        
        foreach ($fields as $field => $label) {
            add_settings_field(
                $field,
                $label,
                [$this, 'renderField'],
                'wp-google-news-sitemap',
                'general',
                ['field' => $field]
            );
        }
    }
    
    public function renderField($args) {
        $settings = $this->getSettings();
        $field = $args['field'];
        $value = $settings[$field] ?? '';
        
        switch ($field) {
            case 'publication_language':
                $languages = [
                    'ar' => 'Arabic', 'zh' => 'Chinese', 'en' => 'English',
                    'fr' => 'French', 'de' => 'German', 'it' => 'Italian',
                    'ja' => 'Japanese', 'es' => 'Spanish'
                ];
                echo '<select name="wp_google_news_sitemap_settings[' . $field . ']">';
                foreach ($languages as $code => $name) {
                    $selected = selected($value, $code, false);
                    echo "<option value=\"{$code}\" {$selected}>{$name}</option>";
                }
                echo '</select>';
                break;
                
            case 'cache_enabled':
            case 'include_images':
            case 'validate_xml':
                $checked = checked($value, true, false);
                echo "<input type='checkbox' name='wp_google_news_sitemap_settings[{$field}]' value='1' {$checked} />";
                break;
                
            case 'max_age_days':
            case 'posts_per_sitemap':
            case 'cache_duration':
                echo "<input type='number' name='wp_google_news_sitemap_settings[{$field}]' value='{$value}' class='small-text' />";
                break;
                
            default:
                echo "<input type='text' name='wp_google_news_sitemap_settings[{$field}]' value='{$value}' class='regular-text' />";
                break;
        }
    }
    
    public function renderSettingsPage() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-google-news-sitemap'));
        }
        
        $sitemapUrl = home_url('/sitemap-news.xml');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-info">
                <p>
                    <?php printf(
                        __('Your Google News sitemap is available at: %s', 'wp-google-news-sitemap'),
                        '<a href="' . esc_url($sitemapUrl) . '" target="_blank">' . esc_html($sitemapUrl) . '</a>'
                    ); ?>
                </p>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wp-google-news-sitemap');
                do_settings_sections('wp-google-news-sitemap');
                submit_button();
                ?>
            </form>
            
            <div class="postbox" style="margin-top: 20px;">
                <h3 class="hndle"><span><?php _e('Quick Actions', 'wp-google-news-sitemap'); ?></span></h3>
                <div class="inside">
                    <p>
                        <a href="<?php echo $sitemapUrl; ?>" target="_blank" class="button button-primary">
                            <?php _e('View Sitemap', 'wp-google-news-sitemap'); ?>
                        </a>
                        <a href="<?php echo add_query_arg(['google_news_sitemap' => '1', 'sitemap_debug' => '1'], home_url('/')); ?>" target="_blank" class="button button-secondary">
                            <?php _e('Debug Information', 'wp-google-news-sitemap'); ?>
                        </a>
                        <a href="<?php echo add_query_arg(['clear_cache' => '1']); ?>" class="button button-secondary">
                            <?php _e('Clear Cache', 'wp-google-news-sitemap'); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function sanitizeSettings($input) {
        $sanitized = [];
        
        $sanitized['publication_name'] = sanitize_text_field($input['publication_name'] ?? '');
        if (empty($sanitized['publication_name'])) {
            $sanitized['publication_name'] = get_bloginfo('name');
        }
        
        $sanitized['publication_language'] = sanitize_text_field($input['publication_language'] ?? 'fr');
        $sanitized['max_age_days'] = absint($input['max_age_days'] ?? 2);
        $sanitized['posts_per_sitemap'] = absint($input['posts_per_sitemap'] ?? 1000);
        $sanitized['cache_duration'] = absint($input['cache_duration'] ?? 3600);
        
        $sanitized['cache_enabled'] = !empty($input['cache_enabled']);
        $sanitized['include_images'] = !empty($input['include_images']);
        $sanitized['validate_xml'] = !empty($input['validate_xml']);
        
        // Keep existing arrays
        $existing = $this->getSettings();
        $sanitized['post_types'] = $existing['post_types'];
        $sanitized['exclude_categories'] = $existing['exclude_categories'];
        $sanitized['exclude_tags'] = $existing['exclude_tags'];
        
        return $sanitized;
    }
    
    public function addSettingsLink($links) {
        $settingsLink = '<a href="' . admin_url('options-general.php?page=wp-google-news-sitemap') . '">' . __('Settings', 'wp-google-news-sitemap') . '</a>';
        array_unshift($links, $settingsLink);
        return $links;
    }
    
    public function adminNotices() {
        if (isset($_GET['clear_cache']) && $_GET['clear_cache'] === '1' && current_user_can('manage_options')) {
            $this->clearCache();
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . __('Cache cleared successfully.', 'wp-google-news-sitemap') . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Add sitemap to robots.txt
     */
    public function addSitemapToRobots() {
        $sitemapUrl = home_url('/sitemap-news.xml');
        echo "Sitemap: {$sitemapUrl}\n";
    }
    
    /**
     * Plugin lifecycle
     */
    public function activate() {
        // Set default settings
        add_option('wp_google_news_sitemap_settings', $this->defaultSettings);
        
        // Register rewrite rules
        $this->registerRewriteRules();
        flush_rewrite_rules();
        
        // Schedule cleanup
        if (!wp_next_scheduled('wp_google_news_sitemap_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_google_news_sitemap_cleanup');
        }
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('wp_google_news_sitemap_cleanup');
        $this->clearCache();
        flush_rewrite_rules();
    }
    
    public static function uninstall() {
        delete_option('wp_google_news_sitemap_settings');
        delete_option('wp_google_news_sitemap_last_generated');
        wp_cache_flush();
        flush_rewrite_rules();
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    WP_Google_News_Sitemap::getInstance();
});

// Helper functions
function wp_google_news_sitemap_url() {
    return home_url('/sitemap-news.xml');
}

function wp_google_news_clear_cache() {
    return WP_Google_News_Sitemap::getInstance()->clearCache();
}