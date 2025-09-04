<?php
/**
 * Plugin Name: Simple Daily Post Count
 * Plugin URI: https://github.com/gl0bal01/wordpress-plugins/wp-simple-daily-post-count
 * Description: Displays the number of published posts per day (last 14 days) on the Dashboard. Update counts via WP-CLI (or your external cron).
 * Version: 1.0.0
 * Author: gl0bal01
 * Author URI: https://gl0bal01.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: simple-daily-post-count
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SimpleDailyPostCount
 *
 * Calculates daily post counts and registers a dashboard widget.
 */
final class SimpleDailyPostCount {

    /**
     * The option name where the counts are stored.
     */
    private const OPTION_NAME = 'sdp_daily_post_counts';

    /**
     * Initializes the dashboard widget.
     *
     * @return void
     */
    public function init(): void {
        add_action('wp_dashboard_setup', [$this, 'registerDashboardWidget']);
    }

    /**
     * Registers the dashboard widget.
     *
     * @return void
     */
    public function registerDashboardWidget(): void {
        wp_add_dashboard_widget(
            'sdp_widget',
            __('Daily Post Count (Last 14 Days)', 'simple-daily-post-count'),
            [$this, 'renderDashboardWidget']
        );
    }

    /**
     * Renders the widget on the Dashboard.
     *
     * @return void
     */
    public function renderDashboardWidget(): void {
        // Check user capabilities
        if (!current_user_can('read')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'simple-daily-post-count'));
        }

        $dailyCounts = get_option(self::OPTION_NAME, []);
        
        // Validate data type and check if empty
        if (!is_array($dailyCounts) || empty($dailyCounts)) {
            echo '<p>' . esc_html__('No data available. Run the daily calculation (via WP-CLI or your crontab).', 'simple-daily-post-count') . '</p>';
            return;
        }

        // Sort dates in descending order.
        krsort($dailyCounts);

        echo '<table style="width:100%; border-collapse:collapse;">';
        echo '<thead><tr>';
        echo '<th style="border:1px solid #ccc; padding:4px;">' . esc_html__('Date', 'simple-daily-post-count') . '</th>';
        echo '<th style="border:1px solid #ccc; padding:4px;">' . esc_html__('Published Posts', 'simple-daily-post-count') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        foreach ($dailyCounts as $date => $count) {
            // Additional validation for date format and count
            if (!is_string($date) || !is_numeric($count)) {
                continue;
            }
            echo '<tr>';
            echo '<td style="border:1px solid #ccc; padding:4px;">' . esc_html($date) . '</td>';
            echo '<td style="border:1px solid #ccc; padding:4px;">' . esc_html($count) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        
        // Add last updated info
        $lastUpdated = get_option(self::OPTION_NAME . '_last_updated', false);
        if ($lastUpdated) {
            echo '<p style="font-size:11px; color:#666; margin-top:10px;">';
            echo esc_html__('Last updated: ', 'simple-daily-post-count') . esc_html(date('Y-m-d H:i:s', $lastUpdated));
            echo '</p>';
        }
    }

    /**
     * Calculates the published post counts per day for the last 14 days.
     *
     * You can trigger this method via WP-CLI.
     *
     * @return void
     */
    public static function calculateDailyPostCounts(): void {
        global $wpdb;

        // Determine the start date (last 14 days: today plus the previous 13 days).
        $startDate = date('Y-m-d', strtotime('-13 days'));

        // SQL query to count published posts per day.
        $sql = "
            SELECT DATE(post_date) AS post_date, COUNT(ID) AS count
            FROM {$wpdb->posts}
            WHERE post_status = %s
              AND post_type = %s
              AND post_date >= %s
            GROUP BY DATE(post_date)
            ORDER BY post_date DESC
            LIMIT 14
        ";
        
        $preparedSQL = $wpdb->prepare($sql, 'publish', 'post', $startDate . ' 00:00:00');
        $results = $wpdb->get_results($preparedSQL, ARRAY_A);

        // Check for database errors
        if ($wpdb->last_error) {
            error_log('Simple Daily Post Count DB Error: ' . $wpdb->last_error);
            return;
        }

        // Create an array with a key for each of the last 14 days.
        $dailyCounts = [];
        for ($i = 0; $i < 14; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dailyCounts[$date] = 0;
        }
        
        // Populate with actual counts
        if ($results && is_array($results)) {
            foreach ($results as $row) {
                if (isset($row['post_date'], $row['count']) && isset($dailyCounts[$row['post_date']])) {
                    $dailyCounts[$row['post_date']] = (int)$row['count'];
                }
            }
        }

        // Update the option and store last updated timestamp
        update_option(self::OPTION_NAME, $dailyCounts);
        update_option(self::OPTION_NAME . '_last_updated', time());
    }

    /**
     * Clean up plugin data on deactivation.
     *
     * @return void
     */
    public static function cleanup(): void {
        delete_option(self::OPTION_NAME);
        delete_option(self::OPTION_NAME . '_last_updated');
    }
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function sdpc_init_plugin(): void {
    $plugin = new SimpleDailyPostCount();
    $plugin->init();
}
add_action('plugins_loaded', 'sdpc_init_plugin');

/**
 * Register WP-CLI command if WP_CLI is defined.
 *
 * Usage: wp sdpc calculate
 */
if ( defined('WP_CLI') && WP_CLI ) {
    WP_CLI::add_command('sdpc calculate', function() {
        SimpleDailyPostCount::calculateDailyPostCounts();
        WP_CLI::success('Daily post counts calculated and cached.');
    });
}

/**
 * Plugin deactivation hook - cleanup data
 */
register_deactivation_hook(__FILE__, array('SimpleDailyPostCount', 'cleanup'));
