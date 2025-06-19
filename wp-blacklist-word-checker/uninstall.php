<?php
/**
 * Uninstall script for Blacklist Word Checker
 *
 * This file is executed when the plugin is uninstalled via the WordPress admin.
 * It removes all plugin data from the database.
 *
 * @package BlacklistWordChecker
 * @since   1.0.0
 */

declare(strict_types=1);

// Exit if uninstall not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove all plugin data from the database
 *
 * This function ensures complete cleanup when the plugin is uninstalled,
 * removing all options and any cached data.
 *
 * @return void
 */
function blacklist_word_checker_uninstall(): void
{
    // Remove plugin options
    delete_option('blacklist_word_checker_list');
    delete_option('blacklist_word_checker_version');
    
    // Remove any site options (for multisite)
    delete_site_option('blacklist_word_checker_list');
    delete_site_option('blacklist_word_checker_version');
    
    // Clear any cached data
    wp_cache_flush();
    
    // Remove any transients (if we add them in future versions)
    delete_transient('blacklist_word_checker_cache');
    delete_site_transient('blacklist_word_checker_cache');
    
    // Clean up any user meta (if we add user-specific settings in future)
    global $wpdb;
    
    // Use WordPress database methods for security
    $wpdb->delete(
        $wpdb->usermeta,
        [
            'meta_key' => 'blacklist_word_checker_preferences'
        ]
    );
    
    // Log uninstall for debugging purposes (only if WP_DEBUG is enabled)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Blacklist Word Checker: Plugin uninstalled and data removed');
    }
}

// Execute the uninstall function
blacklist_word_checker_uninstall();
