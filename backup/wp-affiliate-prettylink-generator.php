<?php
/**
 * !!! BACKUP FILE – DO NOT USE IN PRODUCTION. This file is intended for backup purposes only. You must review and modify it to suit your specific requirements before use.
 * Plugin Name: Affiliate PrettyLink Generator
 * Plugin URI: https://github.com/gl0bal01/wordpress-plugins/wp-affiliate-prettylink-generator
 * Description: A powerful WordPress plugin that automatically detects affiliate platforms and generates Pretty Links with proper tracking parameters for various affiliate networks.
 * Version: 1.0.1
 * Author: gl0bal01
 * Author URI: https://gl0bal01.com
 * Text Domain: wp-affiliate-prettylink-generator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * Network: false
 * Update URI: false
 * Created: 2019-12-16
 * Updated: 2025-01-30
 * !!! BACKUP FILE – DO NOT USE IN PRODUCTION. This file is intended for backup purposes only. You must review and modify it to suit your specific requirements before use.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Main Affiliate Link Generator Plugin Class
 * 
 * @since 1.0.0
 */
class AffiliateeLinkGenerator
{
    /**
     * Plugin version
     * 
     * @var string
     */
    public const VERSION = '1.0.1';

    /**
     * Plugin text domain
     * 
     * @var string
     */
    public const TEXT_DOMAIN = 'affiliate-link-generator';

    /**
     * Minimum WordPress version
     * 
     * @var string
     */
    public const MIN_WP_VERSION = '5.0';

    /**
     * Minimum PHP version
     * 
     * @var string
     */
    public const MIN_PHP_VERSION = '7.4';

    /**
     * Maximum AJAX requests per hour per user
     * 
     * @var int
     */
    private const MAX_REQUESTS_PER_HOUR = 100;

    /**
     * Single instance of the plugin
     * 
     * @var AffiliateeLinkGenerator|null
     */
    private static $instance = null;

    /**
     * Affiliate platform configurations
     * 
     * @var array<string, array>
     */
    private $platforms = [];

    /**
     * Plugin constructor
     * 
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->definePlatforms();
        $this->initHooks();
    }

    /**
     * Get singleton instance
     * 
     * @since 1.0.0
     * @return AffiliateeLinkGenerator
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize WordPress hooks
     * 
     * @since 1.0.0
     * @return void
     */
    private function initHooks()
    {
        add_action('plugins_loaded', [$this, 'checkDependencies']);
        add_action('init', [$this, 'loadTextDomain']);
        
        // Only initialize if Pretty Link is active
        if ($this->isPrettyLinkActive()) {
            add_action('add_meta_boxes', [$this, 'addMetaBox']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
            add_action('wp_ajax_generate_affiliate_link', [$this, 'handleAjaxRequest']);
        }
    }

    /**
     * Check if Pretty Link plugin is active and functions are available
     * 
     * @since 1.0.1
     * @return bool
     */
    private function isPrettyLinkActive()
    {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        return is_plugin_active('pretty-link/pretty-link.php') && 
               function_exists('prli_create_pretty_link') && 
               function_exists('prli_get_pretty_link_url');
    }

    /**
     * Check plugin dependencies
     * 
     * @since 1.0.0
     * @return void
     */
    public function checkDependencies()
    {
        if (!$this->isPrettyLinkActive()) {
            add_action('admin_notices', [$this, 'showPrettyLinkNotice']);
            return;
        }

        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'showPhpVersionNotice']);
            return;
        }

        if (version_compare(get_bloginfo('version'), self::MIN_WP_VERSION, '<')) {
            add_action('admin_notices', [$this, 'showWpVersionNotice']);
            return;
        }
    }

    /**
     * Load plugin text domain for internationalization
     * 
     * @since 1.0.0
     * @return void
     */
    public function loadTextDomain()
    {
        load_plugin_textdomain(
            self::TEXT_DOMAIN,
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Define affiliate platform configurations
     * 
     * This method centralizes all platform configurations.
     * To add a new platform, simply add it to this array.
     * 
     * ⚠️ IMPORTANT: Replace example affiliate IDs with your actual IDs before use!
     * 
     * @since 1.0.0
     * @return void
     */
    private function definePlatforms()
    {
        $this->platforms = [
            'adtraction' => [
                'domains' => ['oma-and-me.com'],
                'url_template' => 'https://at.oma-and-me.com/t/t?a=YOUR_ADTRACTION_ID&as=YOUR_ADTRACTION_AS&t=2&tk=1&url=%s',
                'name' => __('Adtraction', self::TEXT_DOMAIN),
                'type' => 'prepend_url'
            ],
            'zanox' => [
                'domains' => ['zalando.be', 'kiabi.be', 'orcanta.'],
                'programs' => [
                    'zalando.be' => 0, // Replace with actual program ID
                    'kiabi.be' => 0,   // Replace with actual program ID
                    'orcanta.' => 0    // Replace with actual program ID
                ],
                'name' => __('Zanox', self::TEXT_DOMAIN),
                'type' => 'api_deeplink'
            ],
            'amazon' => [
                'domains' => ['amazon.com', 'amazon.co.uk', 'amazon.fr', 'amazon.de'],
                'parameter' => 'tag=YOUR-AMAZON-TAG-20', // Replace with your Amazon Associate tag
                'name' => __('Amazon Associates', self::TEXT_DOMAIN),
                'type' => 'append_parameter'
            ],
            'awin' => [
                'domains' => ['example-awin-merchant.com'],
                'url_template' => 'https://www.awin1.com/cread.php?awinmid=%s&awinaffid=YOUR_AWIN_ID&ued=%s',
                'merchant_id' => '0', // Replace with actual merchant ID
                'name' => __('Awin', self::TEXT_DOMAIN),
                'type' => 'template_with_merchant'
            ]
            // Add more platforms here following the same pattern
        ];

        /**
         * Allow other plugins/themes to modify platform configurations
         * 
         * @since 1.0.0
         * @param array $platforms The platform configurations array
         */
        $this->platforms = apply_filters('affiliate_link_generator_platforms', $this->platforms);
    }

    /**
     * Add meta box to post editor
     * 
     * @since 1.0.0
     * @return void
     */
    public function addMetaBox()
    {
        // Check user capability
        if (!current_user_can('edit_posts')) {
            return;
        }

        $post_types = ['post', 'page']; // Add more post types as needed
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'affiliate-link-generator',
                __('Affiliate Link Generator', self::TEXT_DOMAIN),
                [$this, 'renderMetaBox'],
                $post_type,
                'side',
                'default'
            );
        }
    }

    /**
     * Render meta box content
     * 
     * @since 1.0.0
     * @param WP_Post $post The current post object
     * @return void
     */
    public function renderMetaBox($post)
    {
        // Create nonce for security
        wp_nonce_field('affiliate_link_generator_meta_box', 'affiliate_link_generator_nonce');
        
        ?>
        <div class="affiliate-link-generator-metabox">
            <p>
                <label for="alg_link_name">
                    <strong><?php esc_html_e('Link Name', self::TEXT_DOMAIN); ?></strong>
                </label><br>
                <input 
                    id="alg_link_name" 
                    name="alg_link_name" 
                    type="text" 
                    class="widefat"
                    placeholder="<?php esc_attr_e('e.g., Kiabi - Jasmine dress', self::TEXT_DOMAIN); ?>"
                    maxlength="200"
                    autocomplete="off"
                >
                <span class="howto">
                    <?php esc_html_e('Enter a descriptive name for your affiliate link', self::TEXT_DOMAIN); ?>
                </span>
            </p>
            
            <p>
                <label for="alg_target_url">
                    <strong><?php esc_html_e('Target URL', self::TEXT_DOMAIN); ?></strong>
                </label><br>
                <input 
                    id="alg_target_url" 
                    name="alg_target_url" 
                    type="url" 
                    class="widefat"
                    autocomplete="off"
                    placeholder="<?php esc_attr_e('https://example.com/product', self::TEXT_DOMAIN); ?>"
                    maxlength="2048"
                >
                <span class="howto">
                    <?php esc_html_e('Enter the full URL you want to affiliate', self::TEXT_DOMAIN); ?>
                </span>
            </p>
            
            <p>
                <label for="alg_disable_affiliation">
                    <input 
                        id="alg_disable_affiliation" 
                        name="alg_disable_affiliation" 
                        type="checkbox"
                    >
                    <strong><?php esc_html_e('Disable Affiliation', self::TEXT_DOMAIN); ?></strong>
                </label>
                <span class="howto">
                    <p><?php esc_html_e('Check this box if you want to create a link without affiliate parameters (e.g., for client orders).', self::TEXT_DOMAIN); ?></p>
                    <p><u><?php esc_html_e('Client Order Specifics:', self::TEXT_DOMAIN); ?></u><br>
                    <?php esc_html_e('Sans Complexe → Check this box', self::TEXT_DOMAIN); ?><br>
                    <?php esc_html_e('Lelo → Do not check', self::TEXT_DOMAIN); ?><br>
                    <?php esc_html_e('MsMode → Add UTM parameters and check this box', self::TEXT_DOMAIN); ?></p>
                </span>
            </p>
            
            <p>
                <button 
                    id="alg_generate_link" 
                    type="button" 
                    class="button button-primary"
                >
                    <?php esc_html_e('Generate Affiliate Link', self::TEXT_DOMAIN); ?>
                </button>
                <span class="spinner" id="alg_spinner"></span>
            </p>
            
            <div id="alg_result_field" class="alg-result-field"></div>
        </div>
        
        <style>
        .alg-result-field {
            margin-top: 15px;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: none;
        }
        .alg-result-field.has-content {
            display: block;
        }
        .alg-platform-detected {
            color: #0073aa;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .alg-generated-link {
            word-break: break-all;
            background: white;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
            margin: 5px 0;
        }
        .alg-copy-link {
            font-size: 11px;
            height: auto;
            line-height: 1.5;
            padding: 3px 8px;
        }
        </style>
        <?php
    }

    /**
     * Enqueue admin scripts and styles
     * 
     * @since 1.0.0
     * @param string $hook_suffix The current admin page hook suffix
     * @return void
     */
    public function enqueueAdminScripts($hook_suffix)
    {
        // Only load on post edit screens
        $allowed_hooks = ['post.php', 'post-new.php'];
        if (!in_array($hook_suffix, $allowed_hooks, true)) {
            return;
        }

        // Enqueue jQuery if not already loaded
        wp_enqueue_script('jquery');

        // Create nonce for AJAX security
        $nonce = wp_create_nonce('affiliate_link_generator_ajax');
        
        // Localize script data
        $script_data = [
            'ajax_url' => esc_url(admin_url('admin-ajax.php')),
            'nonce' => $nonce,
            'strings' => [
                'generating' => esc_js(__('Generating link...', self::TEXT_DOMAIN)),
                'error' => esc_js(__('Error generating link. Please try again.', self::TEXT_DOMAIN)),
                'invalid_url' => esc_js(__('Please enter a valid URL.', self::TEXT_DOMAIN)),
                'invalid_name' => esc_js(__('Please enter a link name.', self::TEXT_DOMAIN)),
                'copy_success' => esc_js(__('Link copied to clipboard!', self::TEXT_DOMAIN)),
                'copy_error' => esc_js(__('Failed to copy link.', self::TEXT_DOMAIN)),
                'rate_limit' => esc_js(__('Too many requests. Please wait a moment and try again.', self::TEXT_DOMAIN))
            ]
        ];

        // Add inline script
        wp_add_inline_script('jquery', $this->getInlineScript($script_data));
    }

    /**
     * Get inline JavaScript for the admin interface
     * 
     * @since 1.0.0
     * @param array $data Localized script data
     * @return string
     */
    private function getInlineScript(array $data)
    {
        $json_data = wp_json_encode($data);
        
        return "
        jQuery(document).ready(function($) {
            const algData = {$json_data};
            
            // Debounce function to prevent double-clicks
            let isProcessing = false;
            
            $('#alg_generate_link').on('click', function(e) {
                e.preventDefault();
                
                if (isProcessing) {
                    return;
                }
                
                const linkName = $('#alg_link_name').val().trim();
                const targetUrl = $('#alg_target_url').val().trim();
                const disableAffiliation = $('#alg_disable_affiliation').is(':checked');
                const button = $(this);
                const spinner = $('#alg_spinner');
                const resultField = $('#alg_result_field');
                
                // Basic validation
                if (!linkName) {
                    alert(algData.strings.invalid_name);
                    $('#alg_link_name').focus();
                    return;
                }
                
                if (!targetUrl) {
                    alert(algData.strings.invalid_url);
                    $('#alg_target_url').focus();
                    return;
                }
                
                // Validate URL format
                try {
                    new URL(targetUrl);
                } catch (e) {
                    alert(algData.strings.invalid_url);
                    $('#alg_target_url').focus();
                    return;
                }
                
                // Show loading state
                isProcessing = true;
                button.prop('disabled', true).text(algData.strings.generating);
                spinner.addClass('is-active');
                resultField.removeClass('has-content').hide();
                
                // Make AJAX request
                $.post(algData.ajax_url, {
                    action: 'generate_affiliate_link',
                    nonce: algData.nonce,
                    link_name: linkName,
                    target_url: targetUrl,
                    disable_affiliation: disableAffiliation
                })
                .done(function(response) {
                    if (response.success) {
                        resultField.html(response.data.html).addClass('has-content').show();
                        
                        // Add copy functionality to generated links
                        resultField.find('.alg-copy-link').on('click', function() {
                            const linkText = $(this).data('link');
                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(linkText).then(function() {
                                    alert(algData.strings.copy_success);
                                }).catch(function() {
                                    alert(algData.strings.copy_error);
                                });
                            } else {
                                // Fallback for older browsers
                                const textArea = document.createElement('textarea');
                                textArea.value = linkText;
                                textArea.style.position = 'fixed';
                                textArea.style.left = '-999999px';
                                document.body.appendChild(textArea);
                                textArea.select();
                                try {
                                    document.execCommand('copy');
                                    alert(algData.strings.copy_success);
                                } catch (err) {
                                    alert(algData.strings.copy_error);
                                }
                                document.body.removeChild(textArea);
                            }
                        });
                    } else {
                        const errorMsg = response.data && response.data.message ? response.data.message : algData.strings.error;
                        resultField.html('<div class=\"notice notice-error\"><p>' + $('<div>').text(errorMsg).html() + '</p></div>')
                            .addClass('has-content').show();
                    }
                })
                .fail(function(jqXHR) {
                    let errorMsg = algData.strings.error;
                    if (jqXHR.status === 429) {
                        errorMsg = algData.strings.rate_limit;
                    }
                    resultField.html('<div class=\"notice notice-error\"><p>' + $('<div>').text(errorMsg).html() + '</p></div>')
                        .addClass('has-content').show();
                })
                .always(function() {
                    // Reset loading state
                    isProcessing = false;
                    button.prop('disabled', false).text('" . esc_js(__('Generate Affiliate Link', self::TEXT_DOMAIN)) . "');
                    spinner.removeClass('is-active');
                });
            });
        });
        ";
    }

    /**
     * Check rate limit for current user
     * 
     * @since 1.0.1
     * @return bool True if within rate limit, false otherwise
     */
    private function checkRateLimit()
    {
        $user_id = get_current_user_id();
        $transient_key = 'alg_rate_limit_' . $user_id;
        $request_count = get_transient($transient_key);
        
        if ($request_count === false) {
            set_transient($transient_key, 1, HOUR_IN_SECONDS);
            return true;
        }
        
        if ($request_count >= self::MAX_REQUESTS_PER_HOUR) {
            return false;
        }
        
        set_transient($transient_key, $request_count + 1, HOUR_IN_SECONDS);
        return true;
    }

    /**
     * Handle AJAX request for generating affiliate links
     * 
     * @since 1.0.0
     * @return void
     */
    public function handleAjaxRequest()
    {
        try {
            // Verify nonce for security
            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if (!wp_verify_nonce($nonce, 'affiliate_link_generator_ajax')) {
                wp_send_json_error([
                    'message' => __('Security check failed. Please refresh the page and try again.', self::TEXT_DOMAIN)
                ], 403);
                return;
            }

            // Check user capabilities
            if (!current_user_can('edit_posts')) {
                wp_send_json_error([
                    'message' => __('You do not have permission to perform this action.', self::TEXT_DOMAIN)
                ], 403);
                return;
            }

            // Check rate limit
            if (!$this->checkRateLimit()) {
                wp_send_json_error([
                    'message' => __('Too many requests. Please wait a moment and try again.', self::TEXT_DOMAIN)
                ], 429);
                return;
            }

            // Sanitize and validate input
            $link_name = isset($_POST['link_name']) ? sanitize_text_field(wp_unslash($_POST['link_name'])) : '';
            $target_url = isset($_POST['target_url']) ? esc_url_raw(wp_unslash($_POST['target_url'])) : '';
            $disable_affiliation = isset($_POST['disable_affiliation']) && $_POST['disable_affiliation'] === 'true';

            // Validate required fields
            if (empty($link_name) || empty($target_url)) {
                wp_send_json_error([
                    'message' => __('Both link name and target URL are required.', self::TEXT_DOMAIN)
                ], 400);
                return;
            }

            // Validate link name length
            if (strlen($link_name) > 200) {
                wp_send_json_error([
                    'message' => __('Link name is too long. Maximum 200 characters allowed.', self::TEXT_DOMAIN)
                ], 400);
                return;
            }

            // Validate URL format
            if (!filter_var($target_url, FILTER_VALIDATE_URL)) {
                wp_send_json_error([
                    'message' => __('Invalid URL format. Please enter a valid URL.', self::TEXT_DOMAIN)
                ], 400);
                return;
            }

            // Check URL scheme (only http/https allowed)
            $parsed_url = wp_parse_url($target_url);
            if (!isset($parsed_url['scheme']) || !in_array($parsed_url['scheme'], ['http', 'https'], true)) {
                wp_send_json_error([
                    'message' => __('Invalid URL scheme. Only HTTP and HTTPS are allowed.', self::TEXT_DOMAIN)
                ], 400);
                return;
            }

            // Generate the affiliate link
            $result = $this->generateAffiliateLink($target_url, $link_name, $disable_affiliation);

            if ($result['success']) {
                wp_send_json_success([
                    'html' => $result['html'],
                    'pretty_link' => $result['pretty_link'],
                    'platform' => $result['platform']
                ]);
            } else {
                wp_send_json_error([
                    'message' => $result['message']
                ], 500);
            }

        } catch (Exception $e) {
            // Log error for debugging (don't expose to user)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Affiliate Link Generator Error: ' . $e->getMessage());
            }
            
            wp_send_json_error([
                'message' => __('An unexpected error occurred. Please try again.', self::TEXT_DOMAIN)
            ], 500);
        }
    }

    /**
     * Generate affiliate link based on detected platform
     * 
     * @since 1.0.0
     * @param string $target_url The target URL to affiliate
     * @param string $link_name The name for the pretty link
     * @param bool $disable_affiliation Whether to disable affiliation
     * @return array Result array with success status and data
     */
    private function generateAffiliateLink($target_url, $link_name, $disable_affiliation)
    {
        // Generate slug for pretty link
        $slug = 'go/' . sanitize_title_with_dashes(remove_accents($link_name));
        
        // Ensure slug is not too long
        if (strlen($slug) > 200) {
            $slug = substr($slug, 0, 200);
        }

        // Detect platform if affiliation is enabled
        $platform_data = $disable_affiliation ? false : $this->detectPlatform($target_url);

        if ($platform_data === false) {
            // No platform detected or affiliation disabled - create regular UTM link
            $final_url = $this->addUtmParameters($target_url);
            $platform_name = __('Generic UTM', self::TEXT_DOMAIN);
        } else {
            // Platform detected - generate affiliate URL
            $affiliate_result = $this->generatePlatformAffiliateUrl($target_url, $platform_data);
            
            if (!$affiliate_result['success']) {
                return [
                    'success' => false,
                    'message' => $affiliate_result['message']
                ];
            }
            
            $final_url = $affiliate_result['url'];
            $platform_name = $platform_data['platform_config']['name'];
        }

        // Create or get existing pretty link
        if (!function_exists('prli_create_pretty_link') || !function_exists('prli_get_pretty_link_url')) {
            return [
                'success' => false,
                'message' => __('Pretty Link functions are not available. Please ensure Pretty Link plugin is active.', self::TEXT_DOMAIN)
            ];
        }

        // Check if link with same slug already exists
        global $wpdb;
        $prli_link_table = $wpdb->prefix . 'prli_links';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $prli_link_table)) === $prli_link_table;
        
        if (!$table_exists) {
            return [
                'success' => false,
                'message' => __('Pretty Link database tables not found. Please reinstall Pretty Link plugin.', self::TEXT_DOMAIN)
            ];
        }

        // Try to create the link
        $link_id = prli_create_pretty_link($final_url, $slug, $link_name);

        if (!$link_id || is_wp_error($link_id)) {
            return [
                'success' => false,
                'message' => __('Failed to create pretty link. The slug may already exist or there may be a configuration issue.', self::TEXT_DOMAIN)
            ];
        }

        $pretty_link_url = prli_get_pretty_link_url($link_id);
        
        if (!$pretty_link_url) {
            return [
                'success' => false,
                'message' => __('Failed to generate pretty link URL.', self::TEXT_DOMAIN)
            ];
        }

        // Generate HTML output
        $html = $this->generateResultHtml($pretty_link_url, $final_url, $platform_name, $platform_data !== false);

        return [
            'success' => true,
            'html' => $html,
            'pretty_link' => $pretty_link_url,
            'platform' => $platform_name
        ];
    }

    /**
     * Detect affiliate platform from URL
     * 
     * PHP 7.4 compatible - returns array or false
     * 
     * @since 1.0.0
     * @param string $url The URL to analyze
     * @return array|false Platform data if detected, false otherwise
     */
    private function detectPlatform($url)
    {
        // Validate URL
        if (empty($url) || !is_string($url)) {
            return false;
        }

        foreach ($this->platforms as $platform_key => $platform_config) {
            if (!isset($platform_config['domains']) || !is_array($platform_config['domains'])) {
                continue;
            }

            foreach ($platform_config['domains'] as $domain) {
                if (!is_string($domain) || empty($domain)) {
                    continue;
                }
                
                if (strpos($url, $domain) !== false) {
                    return [
                        'platform_key' => $platform_key,
                        'platform_config' => $platform_config,
                        'matched_domain' => $domain
                    ];
                }
            }
        }

        return false;
    }

    /**
     * Generate affiliate URL based on platform configuration
     * 
     * @since 1.0.0
     * @param string $target_url The original URL
     * @param array $platform_data Platform configuration data
     * @return array Result with success status and generated URL
     */
    private function generatePlatformAffiliateUrl($target_url, array $platform_data)
    {
        if (!isset($platform_data['platform_config']) || !isset($platform_data['platform_key'])) {
            return [
                'success' => false,
                'message' => __('Invalid platform configuration.', self::TEXT_DOMAIN)
            ];
        }

        $platform_config = $platform_data['platform_config'];
        $platform_key = $platform_data['platform_key'];

        if (!isset($platform_config['type'])) {
            return [
                'success' => false,
                'message' => __('Platform type not specified.', self::TEXT_DOMAIN)
            ];
        }

        switch ($platform_config['type']) {
            case 'prepend_url':
                if (!isset($platform_config['url_template'])) {
                    return [
                        'success' => false,
                        'message' => sprintf(__('URL template not configured for platform: %s', self::TEXT_DOMAIN), esc_html($platform_config['name']))
                    ];
                }
                return [
                    'success' => true,
                    'url' => sprintf($platform_config['url_template'], urlencode($target_url))
                ];

            case 'append_parameter':
                if (!isset($platform_config['parameter'])) {
                    return [
                        'success' => false,
                        'message' => sprintf(__('Parameter not configured for platform: %s', self::TEXT_DOMAIN), esc_html($platform_config['name']))
                    ];
                }
                return [
                    'success' => true,
                    'url' => $this->appendParameter($target_url, $platform_config['parameter'])
                ];

            case 'template_with_merchant':
                if (!isset($platform_config['merchant_id']) || !isset($platform_config['url_template'])) {
                    return [
                        'success' => false,
                        'message' => sprintf(__('Merchant ID or URL template not configured for platform: %s', self::TEXT_DOMAIN), esc_html($platform_config['name']))
                    ];
                }
                // Check if merchant ID is placeholder
                if ($platform_config['merchant_id'] === '0' || $platform_config['merchant_id'] === 0) {
                    return [
                        'success' => false,
                        'message' => sprintf(__('Please configure merchant ID for platform: %s', self::TEXT_DOMAIN), esc_html($platform_config['name']))
                    ];
                }
                return [
                    'success' => true,
                    'url' => sprintf($platform_config['url_template'], $platform_config['merchant_id'], urlencode($target_url))
                ];

            case 'api_deeplink':
                return $this->generateZanoxDeeplink($target_url, $platform_data);

            default:
                return [
                    'success' => false,
                    'message' => sprintf(__('Unknown platform type: %s', self::TEXT_DOMAIN), esc_html($platform_config['type']))
                ];
        }
    }

    /**
     * Generate Zanox deeplink using API
     * 
     * @since 1.0.0
     * @param string $target_url The target URL
     * @param array $platform_data Platform configuration
     * @return array Result with success status and URL
     */
    private function generateZanoxDeeplink($target_url, array $platform_data)
    {
        if (!isset($platform_data['platform_config']) || !isset($platform_data['matched_domain'])) {
            return [
                'success' => false,
                'message' => __('Invalid Zanox configuration.', self::TEXT_DOMAIN)
            ];
        }

        $platform_config = $platform_data['platform_config'];
        $matched_domain = $platform_data['matched_domain'];

        if (!isset($platform_config['programs'][$matched_domain])) {
            return [
                'success' => false,
                'message' => sprintf(__('No program ID configured for domain: %s', self::TEXT_DOMAIN), esc_html($matched_domain))
            ];
        }

        $program_id = $platform_config['programs'][$matched_domain];
        
        // Check if program ID is placeholder
        if ($program_id === 0 || $program_id === '0') {
            return [
                'success' => false,
                'message' => sprintf(__('Please configure program ID for domain: %s', self::TEXT_DOMAIN), esc_html($matched_domain))
            ];
        }
        
        /**
         * In a production implementation, you would make an API call to Zanox here.
         * Example API endpoint: https://api.zanox.com/json/2011-03-01/createdeeplink
         * 
         * For now, we'll return a basic deeplink format.
         * Replace this with actual Zanox API integration.
         */
        $deeplink_params = [
            'zanox_program' => $program_id,
            'zanox_url' => $target_url
        ];
        
        $deeplink_url = add_query_arg($deeplink_params, 'https://ad.zanox.com/ppc/');

        return [
            'success' => true,
            'url' => $deeplink_url
        ];
    }

    /**
     * Append parameter to URL
     * 
     * @since 1.0.0
     * @param string $url The base URL
     * @param string $parameter The parameter to append
     * @return string The modified URL
     */
    private function appendParameter($url, $parameter)
    {
        if (empty($url) || empty($parameter)) {
            return $url;
        }

        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        return $url . $separator . $parameter;
    }

    /**
     * Add UTM parameters to URL
     * 
     * @since 1.0.0
     * @param string $url The base URL
     * @return string URL with UTM parameters
     */
    private function addUtmParameters($url)
    {
        // Check if UTM parameters already exist
        if (strpos($url, 'utm_source') !== false) {
            return $url;
        }

        $utm_params = [
            'utm_source' => 'thebodyoptimist',
            'utm_medium' => 'article',
            'utm_campaign' => 'date_' . gmdate('m-d-y')
        ];

        $separator = (strpos($url, '?') !== false) ? '&' : '?';
        return $url . $separator . http_build_query($utm_params);
    }

    /**
     * Generate HTML output for the result
     * 
     * @since 1.0.0
     * @param string $pretty_link_url The generated pretty link URL
     * @param string $final_url The final affiliate URL
     * @param string $platform_name The detected platform name
     * @param bool $platform_detected Whether a platform was detected
     * @return string HTML output
     */
    private function generateResultHtml($pretty_link_url, $final_url, $platform_name, $platform_detected)
    {
        $html = '<div class="alg-result-content">';
        
        if ($platform_detected) {
            $html .= sprintf(
                '<div class="alg-platform-detected">%s: <strong>%s</strong></div>',
                esc_html__('Platform detected', self::TEXT_DOMAIN),
                esc_html($platform_name)
            );
        }

        $html .= sprintf(
            '<div class="alg-generated-link">
                <strong>%s:</strong><br>
                <a href="%s" target="_blank" rel="nofollow noopener">%s</a>
                <button type="button" class="button button-small alg-copy-link" data-link="%s" style="margin-left: 10px;">%s</button>
            </div>',
            esc_html__('Pretty Link', self::TEXT_DOMAIN),
            esc_url($pretty_link_url),
            esc_html($pretty_link_url),
            esc_attr($pretty_link_url),
            esc_html__('Copy', self::TEXT_DOMAIN)
        );

        $html .= sprintf(
            '<div class="alg-final-url" style="font-size: 11px; color: #666; margin-top: 5px;">
                <strong>%s:</strong><br>
                <span style="word-break: break-all;">%s</span>
            </div>',
            esc_html__('Final URL', self::TEXT_DOMAIN),
            esc_html($final_url)
        );

        $html .= '</div>';

        return $html;
    }

    /**
     * Show admin notice when Pretty Link is not active
     * 
     * @since 1.0.0
     * @return void
     */
    public function showPrettyLinkNotice()
    {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %s: Plugin name */
                    esc_html__('%s requires the Pretty Link plugin to be installed and activated.', self::TEXT_DOMAIN),
                    '<strong>' . esc_html__('Affiliate Link Generator', self::TEXT_DOMAIN) . '</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Show admin notice for PHP version requirement
     * 
     * @since 1.0.0
     * @return void
     */
    public function showPhpVersionNotice()
    {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %1$s: Plugin name, %2$s: Required PHP version, %3$s: Current PHP version */
                    esc_html__('%1$s requires PHP %2$s or higher. You are running PHP %3$s.', self::TEXT_DOMAIN),
                    '<strong>' . esc_html__('Affiliate Link Generator', self::TEXT_DOMAIN) . '</strong>',
                    esc_html(self::MIN_PHP_VERSION),
                    esc_html(PHP_VERSION)
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Show admin notice for WordPress version requirement
     * 
     * @since 1.0.0
     * @return void
     */
    public function showWpVersionNotice()
    {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %1$s: Plugin name, %2$s: Required WordPress version, %3$s: Current WordPress version */
                    esc_html__('%1$s requires WordPress %2$s or higher. You are running WordPress %3$s.', self::TEXT_DOMAIN),
                    '<strong>' . esc_html__('Affiliate Link Generator', self::TEXT_DOMAIN) . '</strong>',
                    esc_html(self::MIN_WP_VERSION),
                    esc_html(get_bloginfo('version'))
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Get example of how to add a new affiliate platform
     * 
     * @since 1.0.0
     * @return string Example code
     */
    public static function getNewPlatformExample()
    {
        return '
/**
 * Example: How to add a new affiliate platform
 * 
 * Add this code to your theme\'s functions.php or in a separate plugin
 */
add_filter("affiliate_link_generator_platforms", function($platforms) {
    // Example 1: Simple parameter append
    $platforms["my_affiliate_network"] = [
        "domains" => ["shop.example.com", "store.example.com"],
        "parameter" => "affiliate_id=YOUR_ID&ref=blog",
        "name" => __("My Affiliate Network", "your-text-domain"),
        "type" => "append_parameter"
    ];
    
    // Example 2: URL template with merchant ID
    $platforms["another_network"] = [
        "domains" => ["merchant.example.com"],
        "url_template" => "https://track.network.com/click?merchant=%s&url=%s",
        "merchant_id" => "12345",
        "name" => __("Another Network", "your-text-domain"),
        "type" => "template_with_merchant"
    ];
    
    // Example 3: Prepend tracking URL
    $platforms["tracking_service"] = [
        "domains" => ["brand.example.com"],
        "url_template" => "https://tracker.service.com/go?target=%s&id=YOUR_ID",
        "name" => __("Tracking Service", "your-text-domain"),
        "type" => "prepend_url"
    ];
    
    return $platforms;
});';
    }
}

// Initialize the plugin
AffiliateeLinkGenerator::getInstance();

// Display example for developers in admin (with proper sanitization)
if (is_admin() && current_user_can('manage_options')) {
    add_action('admin_footer', function() {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($page === 'affiliate-link-generator-help') {
            echo '<pre style="background: #f1f1f1; padding: 20px; margin: 20px; overflow-x: auto;">';
            echo esc_html(AffiliateeLinkGenerator::getNewPlatformExample());
            echo '</pre>';
        }
    });
}
