<?php
/**
 * Plugin Name: WP PopCash Slack Integration
 * Plugin URI: https://github.com/gl0bal01/wordpress-plugins/wp-popcash-slack-integration
 * Description: Automatically sends published articles tagged to Slack channels and creates Popcash campaigns with enhanced security and error handling.
 * Version: 1.0.0
 * Author: gl0bal01
 * Author URI: https://gl0bal01.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-popcash-slack-integration
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Created: 2024-02-01
 * Network: false
 * Update URI: false
 * Doc: https://docs-api.popcash.net/
 * 
 * SETUP INSTRUCTIONS:
 * ===================
 * 
 * 1. Add these constants to your wp-config.php file:
 * 
 *    // Required - Slack webhook URL for notifications
 *    define('SLACK_WEBHOOK_NEWS', 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL');
 * 
 *    // Optional - Popcash API settings
 *    define('POPCASH_API_URL', 'https://api.popcash.net/campaigns');
 *    define('POPCASH_API_KEY', 'your-popcash-api-key');
 * 
 *    // Optional - Configuration constants
 *    define('WP_POP_NEWS_TAG', 'News');        // Tag name to filter posts (default: 'News')
 *    define('WP_POP_NEWS_BUDGET', 2);          // Default campaign budget (default: 1)
 *    define('WP_POP_COUNTRY', 'FR');           // Target country (default: 'FR')
 * 
 * 2. Create a tag called "News" (or your custom tag name) in WordPress
 * 
 * 3. Apply this tag to posts you want to send to Slack and create campaigns for
 * 
 * 4. Activate the plugin and publish a post with the News tag to test
 * 
 * WEBHOOK SETUP:
 * ==============
 * 
 * 1. Go to your Slack workspace settings
 * 2. Navigate to Apps > Incoming Webhooks
 * 3. Create a new webhook for your desired channel
 * 4. Copy the webhook URL and add it to wp-config.php as SLACK_WEBHOOK_NEWS
 * 
 * POPCASH SETUP (Optional):
 * =========================
 * 
 * 1. Sign up for a Popcash account
 * 2. Get your API key from account settings
 * 3. Add the API URL and key to wp-config.php
 * 4. Campaigns will be automatically created for published posts
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Define plugin constants
define('WP_SLACK_NEWS_VERSION', '1.0.0');
define('WP_SLACK_NEWS_FILE', __FILE__);

// Configuration constants with defaults
if (!defined('WP_POP_NEWS_TAG')) {
    define('WP_POP_NEWS_TAG', 'News');
}

if (!defined('WP_POP_NEWS_BUDGET')) {
    define('WP_POP_NEWS_BUDGET', 1);
}

if (!defined('WP_POP_COUNTRY')) {
    define('WP_POP_COUNTRY', 'FR');
}

/**
 * Main plugin class
 */
class WP_Pop_News_Integration {
    
    private static $instance = null;
    
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
        
        // Hook into post status transitions
        add_action('transition_post_status', [$this, 'handlePostStatusTransition'], 10, 3);
        
        // Admin notices
        add_action('admin_notices', [$this, 'adminNotices']);
        
        // Activation/deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    /**
     * Load text domain for translations
     */
    public function loadTextDomain() {
        load_plugin_textdomain('wp-popcash-slack-integration', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Handle post status transitions
     */
    public function handlePostStatusTransition($newStatus, $oldStatus, $post) {
        // Only process posts
        if ($post->post_type !== 'post') {
            return;
        }
        
        // Only trigger on publish transition
        if ($newStatus !== 'publish' || $oldStatus === 'publish') {
            return;
        }
        
        // Filter by News tag (using constant)
        if (!has_tag(WP_POP_NEWS_TAG, $post->ID)) {
            return;
        }
        
        $this->processPublishedPost($post);
    }
    
    /**
     * Process newly published post
     */
    private function processPublishedPost($post) {
        try {
            // Prepare post data
            $postData = $this->preparePostData($post);
            
            // Send Slack notification
            $slackResult = $this->sendSlackNotification($postData);
            
            // Create Popcash campaign if enabled
            if (defined('POPCASH_API_URL') && !empty(constant('POPCASH_API_URL'))) {
                $campaignResult = $this->createPopcashCampaign($postData);
            }
            
            // Log activity
            $this->logActivity($post->ID, $slackResult, $campaignResult ?? false);
            
        } catch (Exception $e) {
            error_log('WP Pop News Integration Error: ' . $e->getMessage());
            $this->sendErrorNotification($e->getMessage());
        }
    }
    
    /**
     * Prepare post data for notifications
     */
    private function preparePostData($post) {
        return [
            'id' => $post->ID,
            'title' => wp_strip_all_tags($post->post_title),
            'url' => get_permalink($post->ID),
            'author' => get_the_author_meta('display_name', $post->post_author),
            'date' => get_post_time('Y-m-d H:i:s', false, $post),
            'status' => $post->post_status
        ];
    }
    
    /**
     * Send Slack notification
     */
    private function sendSlackNotification($postData) {
        if (!defined('SLACK_WEBHOOK_NEWS') || empty(constant('SLACK_WEBHOOK_NEWS'))) {
            error_log('WP Pop News: SLACK_WEBHOOK_NEWS not defined');
            return false;
        }
        
        $message = $this->formatSlackMessage($postData);
        
        $payload = wp_json_encode([
            'text' => $message,
            'username' => 'WordPress News Bot',
            'icon_emoji' => ':newspaper:'
        ]);
        
        $response = wp_remote_post(constant('SLACK_WEBHOOK_NEWS'), [
            'body' => $payload,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'timeout' => 15,
            'user-agent' => 'WP Pop News Integration/' . WP_SLACK_NEWS_VERSION
        ]);
        
        if (is_wp_error($response)) {
            error_log('Slack notification failed: ' . $response->get_error_message());
            return false;
        }
        
        $responseCode = wp_remote_retrieve_response_code($response);
        return $responseCode === 200;
    }
    
    /**
     * Format message for Slack
     */
    private function formatSlackMessage($postData) {
        return sprintf(
            '<%s|%s> - *%s* - _%s - %s_',
            esc_url($postData['url']),
            sanitize_text_field($postData['title']),
            sanitize_text_field($postData['status']),
            sanitize_text_field($postData['author']),
            sanitize_text_field($postData['date'])
        );
    }
    
    /**
     * Create Popcash campaign
     */
    private function createPopcashCampaign($postData) {
        if (!defined('POPCASH_API_URL') || empty(constant('POPCASH_API_URL'))) {
            return false;
        }
        
        $campaignData = [
            'name' => mb_substr($postData['title'], 0, 60, 'UTF-8'),
            'url' => $postData['url'],
            'budget' => apply_filters('wp_pop_news_popcash_budget', WP_POP_NEWS_BUDGET),
            'frequencyCap' => apply_filters('wp_pop_news_popcash_frequency_cap', 1),
            'bid' => apply_filters('wp_pop_news_popcash_bid', 0.0002),
            'pauseAfterApproval' => true,
            'networkConnection' => '0',
            'countries' => apply_filters('wp_pop_news_popcash_countries', [WP_POP_COUNTRY])
        ];
        
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
        
        // Add API key if defined
        if (defined('POPCASH_API_KEY') && !empty(constant('POPCASH_API_KEY'))) {
            $headers['X-Api-Key'] = constant('POPCASH_API_KEY');
        }
        
        $response = wp_remote_post(constant('POPCASH_API_URL'), [
            'body' => wp_json_encode($campaignData),
            'headers' => $headers,
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            $this->sendSlackNotification([
                'title' => 'Popcash API Error',
                'url' => '#',
                'author' => 'System',
                'date' => current_time('mysql'),
                'status' => 'error: ' . $response->get_error_message()
            ]);
            return false;
        }
        
        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        
        if ($responseCode === 200 || $responseCode === 201) {
            $this->sendSlackNotification([
                'title' => 'Popcash Campaign Created',
                'url' => '#',
                'author' => 'System',
                'date' => current_time('mysql'),
                'status' => 'success: ' . $responseBody
            ]);
            return true;
        } else {
            $this->sendSlackNotification([
                'title' => 'Popcash Campaign Failed',
                'url' => '#',
                'author' => 'System',
                'date' => current_time('mysql'),
                'status' => 'error: ' . $responseBody
            ]);
            return false;
        }
    }
    
    /**
     * Send error notification to Slack
     */
    private function sendErrorNotification($errorMessage) {
        if (!defined('SLACK_WEBHOOK_NEWS') || empty(constant('SLACK_WEBHOOK_NEWS'))) {
            return;
        }
        
        $payload = wp_json_encode([
            'text' => ':warning: WP Pop News Error: ' . $errorMessage,
            'username' => 'WordPress Error Bot',
            'icon_emoji' => ':warning:'
        ]);
        
        wp_remote_post(constant('SLACK_WEBHOOK_NEWS'), [
            'body' => $payload,
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 10
        ]);
    }
    
    /**
     * Log activity for monitoring
     */
    private function logActivity($postId, $slackResult, $campaignResult) {
        $logs = get_option('wp_pop_news_logs', []);
        
        $logs[] = [
            'post_id' => $postId,
            'slack_sent' => $slackResult,
            'campaign_created' => $campaignResult,
            'timestamp' => current_time('mysql')
        ];
        
        // Keep only last 100 entries
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('wp_pop_news_logs', $logs);
    }
    
    /**
     * Display admin notices
     */
    public function adminNotices() {
        $notices = [];
        
        // Check for required constants
        if (!defined('SLACK_WEBHOOK_NEWS') || empty(constant('SLACK_WEBHOOK_NEWS'))) {
            $notices[] = [
                'type' => 'error',
                'message' => __('SLACK_WEBHOOK_NEWS constant is not defined. Please add it to wp-config.php', 'wp-popcash-slack-integration')
            ];
        }
        
        // Check if News tag exists
        if (!term_exists(WP_POP_NEWS_TAG, 'post_tag')) {
            $notices[] = [
                'type' => 'warning', 
                'message' => sprintf(__('Tag "%s" does not exist. Please create it or posts will not be processed.', 'wp-popcash-slack-integration'), WP_POP_NEWS_TAG)
            ];
        }
        
        // Display configuration info
        if (current_user_can('manage_options')) {
            $notices[] = [
                'type' => 'info',
                'message' => sprintf(__('Plugin configured with tag "%s", budget %d, and country %s. See plugin file for full setup instructions.', 'wp-popcash-slack-integration'), WP_POP_NEWS_TAG, WP_POP_NEWS_BUDGET, WP_POP_COUNTRY)
            ];
        }
        
        foreach ($notices as $notice) {
            echo '<div class="notice notice-' . esc_attr($notice['type']) . '"><p>';
            echo esc_html('WP Pop News Integration: ' . $notice['message']);
            echo '</p></div>';
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Schedule cleanup event
        if (!wp_next_scheduled('wp_pop_news_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_pop_news_cleanup');
        }
        
        // Create News tag if it doesn't exist
        if (!term_exists(WP_POP_NEWS_TAG, 'post_tag')) {
            wp_insert_term(WP_POP_NEWS_TAG, 'post_tag', [
                'description' => 'Posts tagged with this will be sent to Slack and create Popcash campaigns'
            ]);
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        wp_clear_scheduled_hook('wp_pop_news_cleanup');
    }
    
    /**
     * Get plugin configuration status
     */
    public function getConfigStatus() {
        return [
            'slack_configured' => defined('SLACK_WEBHOOK_NEWS') && !empty(constant('SLACK_WEBHOOK_NEWS')),
            'popcash_configured' => defined('POPCASH_API_URL') && !empty(constant('POPCASH_API_URL')),
            'tag_name' => WP_POP_NEWS_TAG,
            'tag_exists' => term_exists(WP_POP_NEWS_TAG, 'post_tag'),
            'budget' => WP_POP_NEWS_BUDGET,
            'country' => WP_POP_COUNTRY
        ];
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    WP_Pop_News_Integration::getInstance();
});

// Cleanup old logs
add_action('wp_pop_news_cleanup', function() {
    $logs = get_option('wp_pop_news_logs', []);
    $cutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));
    
    $filteredLogs = array_filter($logs, function($log) use ($cutoffDate) {
        return isset($log['timestamp']) && $log['timestamp'] > $cutoffDate;
    });
    
    update_option('wp_pop_news_logs', $filteredLogs);
});

// Helper function to check plugin configuration
function wp_pop_news_is_configured() {
    $plugin = WP_Pop_News_Integration::getInstance();
    $status = $plugin->getConfigStatus();
    return $status['slack_configured'] && $status['tag_exists'];
}