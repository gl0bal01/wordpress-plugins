<?php
/**
 * Plugin Name: WP Article Timezones
 * Plugin URI: https://github.com/gl0bal01/wordpress-plugins/wp-article-timezones
 * Description: Displays and converts times for multiple countries with WordPress timezone format in post editor meta boxes.
 * Version: 1.0.0
 * Author: gl0bal01
 * Author URI: https://gl0bal01.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-article-timezones
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
define('WP_ARTICLE_TIMEZONES_VERSION', '1.0.0');

/**
 * Main plugin class
 */
class WP_Article_Timezones {
    
    private static $instance = null;
    private $timezones = [
        'Canada' => 'America/Toronto',
        'India' => 'Asia/Kolkata',
        'Thailand' => 'Asia/Bangkok',
        'China' => 'Asia/Shanghai',
        'Japan' => 'Asia/Tokyo'
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
        
        // Add meta box
        add_action('add_meta_boxes', [$this, 'addTimezoneMetabox']);
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // AJAX handlers
        add_action('wp_ajax_convert_timezones', [$this, 'ajaxConvertTimezones']);
        add_action('wp_ajax_get_current_timezones', [$this, 'ajaxGetCurrentTimezones']);
        
        // Admin styles
        add_action('admin_head', [$this, 'addInlineStyles']);
        add_action('admin_footer', [$this, 'addInlineScripts']);
        
        // Activation hook
        register_activation_hook(__FILE__, [$this, 'activate']);
    }
    
    /**
     * Load text domain
     */
    public function loadTextDomain() {
        load_plugin_textdomain('wp-article-timezones', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Add timezone metabox
     */
    public function addTimezoneMetabox() {
        $postTypes = apply_filters('wp_article_timezones_post_types', ['post']);
        
        foreach ($postTypes as $postType) {
            add_meta_box(
                'world_timezones_metabox',
                __('World Timezones Converter', 'wp-article-timezones'),
                [$this, 'renderTimezoneMetabox'],
                $postType,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render timezone metabox
     */
    public function renderTimezoneMetabox($post) {
        wp_nonce_field('wp_article_timezones_nonce', 'wp_article_timezones_nonce_field');
        
        $currentDate = current_time('Y-m-d');
        $currentTime = current_time('H:i');
        
        ?>
        <div class="timezone-metabox-wrapper">
            <div class="timezone-input-group">
                <label for="timezone-date"><?php _e('Date:', 'wp-article-timezones'); ?></label>
                <input type="date" id="timezone-date" value="<?php echo esc_attr($currentDate); ?>" />
                
                <label for="timezone-time"><?php _e('Time:', 'wp-article-timezones'); ?></label>
                <input type="time" id="timezone-time" value="<?php echo esc_attr($currentTime); ?>" />
            </div>

            <div id="timezone-loading" class="timezone-loading" style="display: none;">
                <span class="spinner is-active"></span>
                <span><?php _e('Converting timezones...', 'wp-article-timezones'); ?></span>
            </div>

            <div class="timezone-container" id="timezone-results">
                <?php $this->renderTimezoneResults(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render timezone results
     */
    private function renderTimezoneResults() {
        $timezones = apply_filters('wp_article_timezones_configured', $this->timezones);
        
        foreach ($timezones as $country => $timezone) {
            try {
                $datetime = new DateTime('now', new DateTimeZone($timezone));
                $formattedTime = $this->formatDateTime($datetime);
                
                echo '<div class="timezone-item" data-country="' . esc_attr($country) . '">';
                echo '<div class="timezone-country">' . esc_html($country) . '</div>';
                echo '<div class="timezone-time">' . esc_html($formattedTime) . '</div>';
                echo '<div class="timezone-identifier">' . esc_html($timezone) . '</div>';
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div class="timezone-item timezone-error" data-country="' . esc_attr($country) . '">';
                echo '<div class="timezone-country">' . esc_html($country) . '</div>';
                echo '<div class="timezone-time">Error</div>';
                echo '</div>';
                
                error_log('WP Article Timezones: Error for ' . $country . ' - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Format datetime for display
     */
    private function formatDateTime($datetime) {
        $format = apply_filters('wp_article_timezones_format', 'Y-m-d H:i:s');
        return $datetime->format($format);
    }
    
    /**
     * AJAX: Convert timezones
     */
    public function ajaxConvertTimezones() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wp_article_timezones_ajax')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }
        
        $date = sanitize_text_field($_POST['date'] ?? '');
        $time = sanitize_text_field($_POST['time'] ?? '');
        
        if (!$this->validateDate($date) || !$this->validateTime($time)) {
            wp_send_json_error(['message' => 'Invalid date or time format']);
            return;
        }
        
        try {
            $results = $this->convertToMultipleTimezones($date, $time);
            wp_send_json_success($results);
        } catch (Exception $e) {
            error_log('WP Article Timezones AJAX Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Conversion failed']);
        }
    }
    
    /**
     * AJAX: Get current timezones
     */
    public function ajaxGetCurrentTimezones() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wp_article_timezones_ajax')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }
        
        try {
            $results = $this->getCurrentTimesInAllTimezones();
            wp_send_json_success($results);
        } catch (Exception $e) {
            error_log('WP Article Timezones AJAX Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Failed to get current times']);
        }
    }
    
    /**
     * Convert datetime to multiple timezones
     */
    private function convertToMultipleTimezones($inputDate, $inputTime, $sourceTimezone = 'UTC') {
        $results = [];
        $timezones = apply_filters('wp_article_timezones_configured', $this->timezones);
        
        // Create source datetime
        $dateTimeString = $inputDate . ' ' . $inputTime;
        $sourceDateTime = new DateTime($dateTimeString, new DateTimeZone($sourceTimezone));
        
        foreach ($timezones as $country => $timezone) {
            try {
                $convertedTime = clone $sourceDateTime;
                $convertedTime->setTimezone(new DateTimeZone($timezone));
                $results[$country] = $this->formatDateTime($convertedTime);
            } catch (Exception $e) {
                $results[$country] = 'Error';
                error_log('Conversion error for ' . $country . ': ' . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Get current time in all timezones
     */
    private function getCurrentTimesInAllTimezones() {
        $results = [];
        $timezones = apply_filters('wp_article_timezones_configured', $this->timezones);
        
        foreach ($timezones as $country => $timezone) {
            try {
                $datetime = new DateTime('now', new DateTimeZone($timezone));
                $results[$country] = $this->formatDateTime($datetime);
            } catch (Exception $e) {
                $results[$country] = 'Error';
                error_log('Current time error for ' . $country . ': ' . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Validate date format
     */
    private function validateDate($date) {
        if (empty($date)) return false;
        $dateTime = DateTime::createFromFormat('Y-m-d', $date);
        return $dateTime && $dateTime->format('Y-m-d') === $date;
    }
    
    /**
     * Validate time format
     */
    private function validateTime($time) {
        if (empty($time)) return false;
        $dateTime = DateTime::createFromFormat('H:i', $time);
        return $dateTime && $dateTime->format('H:i') === $time;
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }
        
        $screen = get_current_screen();
        $postTypes = apply_filters('wp_article_timezones_post_types', ['post']);
        
        if (!$screen || !in_array($screen->post_type, $postTypes)) {
            return;
        }
        
        wp_enqueue_script('jquery');
    }
    
    /**
     * Add inline styles
     */
    public function addInlineStyles() {
        if (!$this->shouldLoadAssets()) return;
        
        ?>
        <style>
            .timezone-metabox-wrapper {
                padding: 10px 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .timezone-input-group {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-bottom: 15px;
                padding: 12px;
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 6px;
            }
            .timezone-input-group label {
                font-weight: 600;
                font-size: 12px;
                color: #495057;
                margin-bottom: 3px;
            }
            .timezone-input-group input {
                padding: 8px 10px;
                border: 1px solid #ced4da;
                border-radius: 4px;
                font-size: 14px;
                background: #fff;
            }
            .timezone-input-group input:focus {
                border-color: #0073aa;
                box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
                outline: none;
            }
            .timezone-loading {
                text-align: center;
                padding: 20px 10px;
                color: #6c757d;
                background: #f8f9fa;
                border-radius: 4px;
                margin-bottom: 15px;
            }
            .timezone-loading .spinner {
                float: none;
                margin: 0 8px 0 0;
                vertical-align: middle;
            }
            .timezone-container {
                display: grid;
                gap: 10px;
            }
            .timezone-item {
                background: linear-gradient(135deg, #f8f9fb 0%, #ffffff 100%);
                padding: 12px;
                border-radius: 6px;
                border-left: 4px solid #0073aa;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
                transition: transform 0.2s ease;
            }
            .timezone-item:hover {
                transform: translateY(-1px);
            }
            .timezone-country {
                font-weight: 600;
                font-size: 14px;
                color: #2c3e50;
                margin-bottom: 6px;
            }
            .timezone-time {
                font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                font-size: 13px;
                color: #495057;
                background: rgba(0, 115, 170, 0.05);
                padding: 4px 6px;
                border-radius: 3px;
                margin-bottom: 4px;
                font-weight: 500;
            }
            .timezone-identifier {
                font-size: 11px;
                color: #868e96;
                font-style: italic;
                opacity: 0.8;
            }
            .timezone-error {
                background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
                border-left-color: #fc8181;
            }
            .timezone-item.updating {
                opacity: 0.6;
                transition: opacity 0.3s ease;
            }
            .timezone-item.updated {
                animation: highlight 0.6s ease;
            }
            @keyframes highlight {
                0% { background: #e3f2fd; }
                100% { background: inherit; }
            }
        </style>
        <?php
    }
    
    /**
     * Add inline scripts
     */
    public function addInlineScripts() {
        if (!$this->shouldLoadAssets()) return;
        
        ?>
        <script>
        jQuery(document).ready(function($) {
            var timeoutId;
            
            function convertTimezones() {
                var inputDate = $('#timezone-date').val();
                var inputTime = $('#timezone-time').val();
                
                if (!inputDate || !inputTime) return;
                
                $('#timezone-loading').show();
                $('.timezone-item').addClass('updating');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'convert_timezones',
                        nonce: '<?php echo wp_create_nonce('wp_article_timezones_ajax'); ?>',
                        date: inputDate,
                        time: inputTime
                    },
                    success: function(response) {
                        $('#timezone-loading').hide();
                        $('.timezone-item').removeClass('updating');
                        
                        if (response.success && response.data) {
                            updateTimezoneDisplay(response.data);
                        } else {
                            console.error('Timezone conversion failed:', response.data?.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#timezone-loading').hide();
                        $('.timezone-item').removeClass('updating');
                        console.error('AJAX error:', error);
                    }
                });
            }
            
            function updateTimezoneDisplay(timezoneData) {
                Object.entries(timezoneData).forEach(function([country, time]) {
                    var $item = $('.timezone-item[data-country="' + country + '"]');
                    if ($item.length) {
                        var $timeElement = $item.find('.timezone-time');
                        $timeElement.fadeOut(200, function() {
                            $(this).text(time).fadeIn(200);
                        });
                        
                        setTimeout(function() {
                            $item.addClass('updated');
                            setTimeout(function() {
                                $item.removeClass('updated');
                            }, 600);
                        }, 400);
                    }
                });
            }
            
            function loadCurrentTimezones() {
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'get_current_timezones',
                        nonce: '<?php echo wp_create_nonce('wp_article_timezones_ajax'); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            updateTimezoneDisplay(response.data);
                        }
                    }
                });
            }
            
            // Event listeners with debouncing
            $('#timezone-date, #timezone-time').on('change input', function() {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(convertTimezones, 500);
            });
            
            // Load current times on page load
            loadCurrentTimezones();
            
            // Accessibility enhancements
            $('#timezone-date').attr('aria-label', 'Select date for timezone conversion');
            $('#timezone-time').attr('aria-label', 'Select time for timezone conversion');
            
            $('.timezone-item').attr('tabindex', '0').on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    var country = $(this).find('.timezone-country').text();
                    var time = $(this).find('.timezone-time').text();
                    console.log('Timezone info:', country, time);
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Check if assets should be loaded
     */
    private function shouldLoadAssets() {
        $screen = get_current_screen();
        if (!$screen) return false;
        
        $postTypes = apply_filters('wp_article_timezones_post_types', ['post']);
        return in_array($screen->post_type ?? '', $postTypes) && 
               in_array($screen->base ?? '', ['post', 'edit']);
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        add_option('wp_article_timezones_settings', $this->timezones);
    }
}

// Initialize plugin
add_action('plugins_loaded', function() {
    WP_Article_Timezones::getInstance();
});
