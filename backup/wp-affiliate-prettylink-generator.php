<?php
/**
 * !!! BACKUP FILE – DO NOT USE IN PRODUCTION. This file is intended for backup purposes only. You must review and modify it to suit your specific requirements before use.
 * Plugin Name: Affiliate PrettyLink Generator
 * Plugin URI: https://github.com/gl0bal01/wordpress-plugins/wp-affiliate-prettylink-generator
 * Description: A powerful WordPress plugin that automatically detects affiliate platforms and generates Pretty Links with proper tracking parameters for various affiliate networks.
 * Version: 1.0.0
 * Author: gl0bal01
 * Author URI: https://gl0bal01.com
 * Text Domain: wp-affiliate-prettylink-generator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * Update URI: false
 * Created: 2019-12-16
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
    public const VERSION = '1.0.0';

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
     * Single instance of the plugin
     * 
     * @var AffiliateeLinkGenerator|null
     */
    private static ?AffiliateeLinkGenerator $instance = null;

    /**
     * Affiliate platform configurations
     * 
     * @var array<string, array>
     */
    private array $platforms = [];

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
    public static function getInstance(): AffiliateeLinkGenerator
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
    private function initHooks(): void
    {
        add_action('plugins_loaded', [$this, 'checkDependencies']);
        add_action('init', [$this, 'loadTextDomain']);
        
        // Only initialize if Pretty Link is active
        if (is_plugin_active('pretty-link/pretty-link.php')) {
            add_action('add_meta_boxes', [$this, 'addMetaBox']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
            add_action('wp_ajax_generate_affiliate_link', [$this, 'handleAjaxRequest']);
        }
    }

    /**
     * Check plugin dependencies
     * 
     * @since 1.0.0
     * @return void
     */
    public function checkDependencies(): void
    {
        if (!is_plugin_active('pretty-link/pretty-link.php')) {
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
    public function loadTextDomain(): void
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
     * @since 1.0.0
     * @return void
     */
    private function definePlatforms(): void
    {
        $this->platforms = [
            'adtraction' => [
                'domains' => ['oma-and-me.com'],
                'url_template' => 'https://at.oma-and-me.com/t/t?a=12345678&as=12345678&t=2&tk=1&url=%s',
                'name' => __('Adtraction', self::TEXT_DOMAIN),
                'type' => 'prepend_url'
            ],
            'zanox' => [
                'domains' => ['zalando.be', 'kiabi.be', 'orcanta.'],
                'programs' => [
                    'zalando.be' => 12345678,
                    'kiabi.be' => 12345678,
                    'orcanta.' => 12345678
                ],
                'name' => __('Zanox', self::TEXT_DOMAIN),
                'type' => 'api_deeplink'
            ],
            'amazon' => [
                'domains' => ['amazon.com', 'amazon.co.uk', 'amazon.fr', 'amazon.de'],
                'parameter' => 'tag=your-amazon-tag',
                'name' => __('Amazon Associates', self::TEXT_DOMAIN),
                'type' => 'append_parameter'
            ],
            'awin' => [
                'domains' => ['example-awin-merchant.com'],
                'url_template' => 'https://www.awin1.com/cread.php?awinmid=%s&awinaffid=12345678&ued=%s',
                'merchant_id' => '12345', // Replace with actual merchant ID
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
    public function addMetaBox(): void
    {
        // Check user capability
        if (!current_user_can('edit_posts')) {
            return;
        }

        add_meta_box(
            'affiliate-link-generator',
            __('Affiliate Link Generator', self::TEXT_DOMAIN),
            [$this, 'renderMetaBox'],
            'post',
            'side',
            'default'
        );
    }

    /**
     * Render meta box content
     * 
     * @since 1.0.0
     * @param WP_Post $post The current post object
     * @return void
     */
    public function renderMetaBox(WP_Post $post): void
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
    public function enqueueAdminScripts(string $hook_suffix): void
    {
        // Only load on post edit screens
        if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        // Enqueue jQuery if not already loaded
        wp_enqueue_script('jquery');

        // Create nonce for AJAX security
        $nonce = wp_create_nonce('affiliate_link_generator_ajax');
        
        // Localize script data
        $script_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => $nonce,
            'strings' => [
                'generating' => __('Generating link...', self::TEXT_DOMAIN),
                'error' => __('Error generating link. Please try again.', self::TEXT_DOMAIN),
                'invalid_url' => __('Please enter a valid URL.', self::TEXT_DOMAIN),
                'invalid_name' => __('Please enter a link name.', self::TEXT_DOMAIN),
                'copy_success' => __('Link copied to clipboard!', self::TEXT_DOMAIN),
                'copy_error' => __('Failed to copy link.', self::TEXT_DOMAIN)
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
    private function getInlineScript(array $data): string
    {
        $json_data = wp_json_encode($data);
        
        return "
        jQuery(document).ready(function($) {
            const algData = {$json_data};
            
            $('#alg_generate_link').on('click', function(e) {
                e.preventDefault();
                
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
                
                // Show loading state
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
                            navigator.clipboard.writeText(linkText).then(function() {
                                alert(algData.strings.copy_success);
                            }).catch(function() {
                                alert(algData.strings.copy_error);
                            });
                        });
                    } else {
                        resultField.html('<div class=\"notice notice-error\"><p>' + 
                            (response.data.message || algData.strings.error) + '</p></div>')
                            .addClass('has-content').show();
                    }
                })
                .fail(function() {
                    resultField.html('<div class=\"notice notice-error\"><p>' + 
                        algData.strings.error + '</p></div>')
                        .addClass('has-content').show();
                })
                .always(function() {
                    // Reset loading state
                    button.prop('disabled', false).text('" . esc_js(__('Generate Affiliate Link', self::TEXT_DOMAIN)) . "');
                    spinner.removeClass('is-active');
                });
            });
        });
        ";
    }

    /**
     * Handle AJAX request for generating affiliate links
     * 
     * @since 1.0.0
     * @return void
     */
    public function handleAjaxRequest(): void
    {
        try {
            // Verify nonce for security
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'affiliate_link_generator_ajax')) {
                wp_send_json_error([
                    'message' => __('Security check failed. Please refresh the page and try again.', self::TEXT_DOMAIN)
                ]);
                return;
            }

            // Check user capabilities
            if (!current_user_can('edit_posts')) {
                wp_send_json_error([
                    'message' => __('You do not have permission to perform this action.', self::TEXT_DOMAIN)
                ]);
                return;
            }

            // Sanitize and validate input
            $link_name = isset($_POST['link_name']) ? sanitize_text_field(wp_unslash($_POST['link_name'])) : '';
            $target_url = isset($_POST['target_url']) ? esc_url_raw(wp_unslash($_POST['target_url'])) : '';
            $disable_affiliation = isset($_POST['disable_affiliation']) && $_POST['disable_affiliation'] === 'true';

            if (empty($link_name) || empty($target_url)) {
                wp_send_json_error([
                    'message' => __('Both link name and target URL are required.', self::TEXT_DOMAIN)
                ]);
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
                ]);
            }

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('An unexpected error occurred. Please try again.', self::TEXT_DOMAIN)
            ]);
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
    private function generateAffiliateLink(string $target_url, string $link_name, bool $disable_affiliation): array
    {
        // Generate slug for pretty link
        $slug = 'go/' . sanitize_title_with_dashes(remove_accents($link_name));

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
        global $prli_link;
        
        if (!$prli_link) {
            return [
                'success' => false,
                'message' => __('Pretty Link plugin is not properly initialized.', self::TEXT_DOMAIN)
            ];
        }

        // Check if link already exists or create new one
        $link_id = $prli_link->find_first_target_url($final_url);
        if (!$link_id) {
            $link_id = prli_create_pretty_link($final_url, $slug, $link_name);
        }

        if (!$link_id) {
            return [
                'success' => false,
                'message' => __('Failed to create pretty link. Please check Pretty Link plugin configuration.', self::TEXT_DOMAIN)
            ];
        }

        $pretty_link_url = prli_get_pretty_link_url($link_id);

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
     * @since 1.0.0
     * @param string $url The URL to analyze
     * @return array|false Platform data if detected, false otherwise
     */
    private function detectPlatform(string $url): array|false
    {
        foreach ($this->platforms as $platform_key => $platform_config) {
            if (!isset($platform_config['domains'])) {
                continue;
            }

            foreach ($platform_config['domains'] as $domain) {
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
    private function generatePlatformAffiliateUrl(string $target_url, array $platform_data): array
    {
        $platform_config = $platform_data['platform_config'];
        $platform_key = $platform_data['platform_key'];

        switch ($platform_config['type']) {
            case 'prepend_url':
                return [
                    'success' => true,
                    'url' => sprintf($platform_config['url_template'], urlencode($target_url))
                ];

            case 'append_parameter':
                return [
                    'success' => true,
                    'url' => $this->appendParameter($target_url, $platform_config['parameter'])
                ];

            case 'template_with_merchant':
                if (!isset($platform_config['merchant_id'])) {
                    return [
                        'success' => false,
                        'message' => sprintf(__('Merchant ID not configured for platform: %s', self::TEXT_DOMAIN), $platform_config['name'])
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
                    'message' => sprintf(__('Unknown platform type: %s', self::TEXT_DOMAIN), $platform_config['type'])
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
    private function generateZanoxDeeplink(string $target_url, array $platform_data): array
    {
        // This would require Zanox API credentials to be configured
        // For now, return a basic implementation
        $platform_config = $platform_data['platform_config'];
        $matched_domain = $platform_data['matched_domain'];

        if (!isset($platform_config['programs'][$matched_domain])) {
            return [
                'success' => false,
                'message' => sprintf(__('No program ID configured for domain: %s', self::TEXT_DOMAIN), $matched_domain)
            ];
        }

        $program_id = $platform_config['programs'][$matched_domain];
        
        // In a real implementation, you would make an API call to Zanox here
        // For this example, we'll return a basic deeplink format
        $deeplink_url = add_query_arg([
            'zanox_program' => $program_id,
            'zanox_url' => urlencode($target_url)
        ], 'https://example.zanox-deeplink.com/');

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
    private function appendParameter(string $url, string $parameter): string
    {
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
    private function addUtmParameters(string $url): string
    {
        // Check if UTM parameters already exist
        if (strpos($url, 'utm_source') !== false) {
            return $url;
        }

        $utm_params = [
            'utm_source' => 'thebodyoptimist',
            'utm_medium' => 'article',
            'utm_campaign' => 'date_' . date('m-d-y')
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
    private function generateResultHtml(string $pretty_link_url, string $final_url, string $platform_name, bool $platform_detected): string
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
                <a href="%s" target="_blank" rel="nofollow">%s</a>
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
    public function showPrettyLinkNotice(): void
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
    public function showPhpVersionNotice(): void
    {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %1$s: Plugin name, %2$s: Required PHP version, %3$s: Current PHP version */
                    esc_html__('%1$s requires PHP %2$s or higher. You are running PHP %3$s.', self::TEXT_DOMAIN),
                    '<strong>' . esc_html__('Affiliate Link Generator', self::TEXT_DOMAIN) . '</strong>',
                    self::MIN_PHP_VERSION,
                    PHP_VERSION
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
    public function showWpVersionNotice(): void
    {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %1$s: Plugin name, %2$s: Required WordPress version, %3$s: Current WordPress version */
                    esc_html__('%1$s requires WordPress %2$s or higher. You are running WordPress %3$s.', self::TEXT_DOMAIN),
                    '<strong>' . esc_html__('Affiliate Link Generator', self::TEXT_DOMAIN) . '</strong>',
                    self::MIN_WP_VERSION,
                    get_bloginfo('version')
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
    public static function getNewPlatformExample(): string
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

// Display example for developers in admin
if (is_admin() && current_user_can('manage_options')) {
    add_action('admin_footer', function() {
        if (isset($_GET['page']) && $_GET['page'] === 'affiliate-link-generator-help') {
            echo '<pre style="background: #f1f1f1; padding: 20px; margin: 20px; overflow-x: auto;">';
            echo esc_html(AffiliateeLinkGenerator::getNewPlatformExample());
            echo '</pre>';
        }
    });
}
