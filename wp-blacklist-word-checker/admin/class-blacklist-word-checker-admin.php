<?php
/**
 * Admin class for Blacklist Word Checker
 *
 * @package BlacklistWordChecker
 * @since   1.0.0
 */

declare(strict_types=1);

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin functionality class
 *
 * Handles admin interface, settings management, and blacklist administration.
 *
 * @since 1.0.0
 */
class BlacklistWordCheckerAdmin
{
    /**
     * Option name for storing blacklist
     *
     * @var string
     */
    private string $optionName = 'blacklist_word_checker_list';

    /**
     * Settings page slug
     *
     * @var string
     */
    private string $pageSlug = 'blacklist-word-checker';

    /**
     * Capability required to manage settings
     *
     * @var string
     */
    private string $capability = 'manage_options';

    /**
     * Admin script handle
     *
     * @var string
     */
    private string $adminScriptHandle = 'blacklist-word-checker-admin-js';

    /**
     * Admin style handle
     *
     * @var string
     */
    private string $adminStyleHandle = 'blacklist-word-checker-admin-css';

    /**
     * Initialize the admin functionality
     *
     * @return void
     */
    public function init(): void
    {
        $this->setupHooks();
    }

    /**
     * Setup WordPress hooks
     *
     * @return void
     */
    private function setupHooks(): void
    {
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        add_action('admin_notices', [$this, 'displayAdminNotices']);
    }

    /**
     * Enqueue admin-specific scripts and styles
     *
     * @param string $hook The current admin page hook
     * @return void
     */
    public function enqueueAdminScripts(string $hook): void
    {
        $settingsPage = 'settings_page_' . $this->pageSlug;

        if ($hook !== $settingsPage) {
            return;
        }

        wp_enqueue_style(
            $this->adminStyleHandle,
            BLACKLIST_WORD_CHECKER_PLUGIN_URL . 'assets/css/blacklist-checker.css',
            [],
            BLACKLIST_WORD_CHECKER_VERSION
        );

        wp_enqueue_script(
            $this->adminScriptHandle,
            BLACKLIST_WORD_CHECKER_PLUGIN_URL . 'assets/js/blacklist-admin.js',
            ['jquery'],
            BLACKLIST_WORD_CHECKER_VERSION,
            true
        );

        wp_localize_script(
            $this->adminScriptHandle,
            'blacklistAdminData',
            [
                'nonce' => wp_create_nonce('blacklist_word_checker_admin_nonce'),
                'confirmDelete' => esc_html__('Are you sure you want to delete this word?', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN)
            ]
        );
    }

    /**
     * Add settings page to WordPress admin
     *
     * @return void
     */
    public function addSettingsPage(): void
    {
        add_options_page(
            esc_html__('Blacklist Word Checker', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN),
            esc_html__('Blacklist Words', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN),
            $this->capability,
            $this->pageSlug,
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Register settings for the blacklist
     *
     * @return void
     */
    public function registerSettings(): void
    {
        register_setting(
            'blacklist_word_checker_settings',
            $this->optionName,
            [
                'sanitize_callback' => [$this, 'sanitizeBlacklist'],
                'default' => []
            ]
        );
    }

    /**
     * Sanitize the blacklist array
     *
     * @param mixed $input The input to sanitize
     * @return array<string> The sanitized blacklist
     */
    public function sanitizeBlacklist($input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $sanitizedInput = [];

        foreach ($input as $word) {
            $sanitizedWord = sanitize_text_field((string) $word);
            $sanitizedWord = trim($sanitizedWord);
            
            if (!empty($sanitizedWord)) {
                $sanitizedInput[] = $sanitizedWord;
            }
        }

        // Remove duplicates and return
        return array_unique($sanitizedInput);
    }

    /**
     * Get current blacklist
     *
     * @return array<string> Current blacklist words
     */
    private function getCurrentBlacklist(): array
    {
        $blacklist = get_option($this->optionName, []);
        
        if (!is_array($blacklist)) {
            return [];
        }

        return array_filter(array_map('strval', $blacklist), function (string $word): bool {
            return !empty(trim($word));
        });
    }

    /**
     * Handle form submissions and return status messages
     *
     * @return array{type: string, message: string}|null
     */
    private function handleFormSubmissions(): ?array
    {
        // Handle adding new word
        if (isset($_POST['blacklist_word_checker_add_word'], $_POST['blacklist_word_checker_admin_nonce'])) {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['blacklist_word_checker_admin_nonce'])), 'blacklist_word_checker_admin_nonce')) {
                return [
                    'type' => 'error',
                    'message' => esc_html__('Security check failed.', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN)
                ];
            }

            if (isset($_POST['new_blacklist_word'])) {
                $newWord = sanitize_text_field(wp_unslash($_POST['new_blacklist_word']));
                $newWord = trim($newWord);

                if (!empty($newWord)) {
                    $blacklist = $this->getCurrentBlacklist();
                    
                    if (!in_array($newWord, $blacklist, true)) {
                        $blacklist[] = $newWord;
                        update_option($this->optionName, $blacklist);

                        return [
                            'type' => 'success',
                            'message' => sprintf(
                                /* translators: %s: The word that was added */
                                esc_html__('Word "%s" added to blacklist.', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN),
                                esc_html($newWord)
                            )
                        ];
                    } else {
                        return [
                            'type' => 'error',
                            'message' => sprintf(
                                /* translators: %s: The word that already exists */
                                esc_html__('Word "%s" already exists in blacklist.', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN),
                                esc_html($newWord)
                            )
                        ];
                    }
                }
            }
        }

        // Handle word deletion
        if (isset($_GET['action'], $_GET['word'], $_GET['_wpnonce']) && $_GET['action'] === 'delete') {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'delete_blacklist_word')) {
                return [
                    'type' => 'error',
                    'message' => esc_html__('Security check failed.', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN)
                ];
            }

            $wordToDelete = sanitize_text_field(wp_unslash($_GET['word']));
            $blacklist = $this->getCurrentBlacklist();
            $key = array_search($wordToDelete, $blacklist, true);

            if ($key !== false) {
                unset($blacklist[$key]);
                $blacklist = array_values($blacklist); // Re-index array
                update_option($this->optionName, $blacklist);

                return [
                    'type' => 'success',
                    'message' => sprintf(
                        /* translators: %s: The word that was removed */
                        esc_html__('Word "%s" removed from blacklist.', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN),
                        esc_html($wordToDelete)
                    )
                ];
            }
        }

        // Handle blacklist import
        if (isset($_POST['blacklist_word_checker_import'], $_POST['blacklist_word_checker_import_nonce'])) {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['blacklist_word_checker_import_nonce'])), 'blacklist_word_checker_admin_nonce')) {
                return [
                    'type' => 'error',
                    'message' => esc_html__('Security check failed.', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN)
                ];
            }

            if (isset($_POST['import_blacklist'])) {
                $importText = sanitize_textarea_field(wp_unslash($_POST['import_blacklist']));

                if (!empty($importText)) {
                    // Split by newlines and sanitize each word
                    $words = explode("\n", $importText);
                    $importedBlacklist = [];

                    foreach ($words as $word) {
                        $word = trim(sanitize_text_field($word));
                        if (!empty($word)) {
                            $importedBlacklist[] = $word;
                        }
                    }

                    // Remove duplicates
                    $importedBlacklist = array_unique($importedBlacklist);

                    if (!empty($importedBlacklist)) {
                        update_option($this->optionName, $importedBlacklist);

                        return [
                            'type' => 'success',
                            'message' => sprintf(
                                /* translators: %d: Number of words imported */
                                esc_html__('Blacklist imported successfully. %d words added.', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN),
                                count($importedBlacklist)
                            )
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Display admin notices
     *
     * @return void
     */
    public function displayAdminNotices(): void
    {
        $screen = get_current_screen();
        
        if (!$screen || $screen->id !== 'settings_page_' . $this->pageSlug) {
            return;
        }

        $notice = $this->handleFormSubmissions();
        
        if ($notice) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($notice['type']),
                $notice['message']
            );
        }
    }

    /**
     * Render the settings page
     *
     * @return void
     */
    public function renderSettingsPage(): void
    {
        // Check user capabilities
        if (!current_user_can($this->capability)) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN));
        }

        // Get current blacklist (refreshed after potential updates)
        $blacklist = $this->getCurrentBlacklist();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="blacklist-manager-container">
                <!-- Add new word form -->
                <div class="blacklist-add-word-form">
                    <h2><?php esc_html_e('Add New Blacklist Word', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('blacklist_word_checker_admin_nonce', 'blacklist_word_checker_admin_nonce'); ?>
                        <input 
                            type="text" 
                            name="new_blacklist_word" 
                            placeholder="<?php esc_attr_e('Enter new word', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?>" 
                            required
                            maxlength="100"
                        >
                        <input 
                            type="submit" 
                            name="blacklist_word_checker_add_word" 
                            class="button button-primary" 
                            value="<?php esc_attr_e('Add Word', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?>"
                        >
                    </form>
                </div>
                
                <!-- Current blacklist table -->
                <div class="blacklist-current-words">
                    <h2>
                        <?php 
                        printf(
                            /* translators: %d: Number of words in blacklist */
                            esc_html__('Current Blacklist Words (%d)', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN),
                            count($blacklist)
                        ); 
                        ?>
                    </h2>
                    
                    <?php if (empty($blacklist)) : ?>
                        <p><?php esc_html_e('No words in blacklist yet.', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?></p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 70%;"><?php esc_html_e('Word', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?></th>
                                    <th style="width: 30%;"><?php esc_html_e('Actions', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blacklist as $word) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($word); ?></strong></td>
                                        <td>
                                            <a 
                                                href="<?php echo esc_url(wp_nonce_url(
                                                    add_query_arg([
                                                        'action' => 'delete',
                                                        'word' => urlencode($word)
                                                    ]),
                                                    'delete_blacklist_word',
                                                    '_wpnonce'
                                                )); ?>" 
                                                class="delete-word button button-small" 
                                                data-word="<?php echo esc_attr($word); ?>"
                                                style="color: #b32d2e;"
                                            >
                                                <?php esc_html_e('Delete', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <!-- Import/Export functionality -->
                <div class="blacklist-import-export">
                    <h2><?php esc_html_e('Import/Export Blacklist', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?></h2>
                    
                    <!-- Export section -->
                    <div class="blacklist-export">
                        <h3><?php esc_html_e('Export Blacklist', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?></h3>
                        <p><?php esc_html_e('Copy the text below to save your blacklist:', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?></p>
                        <textarea 
                            readonly 
                            class="large-text code" 
                            rows="5"
                            onclick="this.select();"
                        ><?php echo esc_textarea(implode("\n", $blacklist)); ?></textarea>
                    </div>
                    
                    <!-- Import section -->
                    <div class="blacklist-import">
                        <h3><?php esc_html_e('Import Blacklist', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?></h3>
                        <p><?php esc_html_e('Paste your blacklist below (one word per line):', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?></p>
                        <form method="post" action="">
                            <?php wp_nonce_field('blacklist_word_checker_admin_nonce', 'blacklist_word_checker_import_nonce'); ?>
                            <textarea 
                                name="import_blacklist" 
                                class="large-text code" 
                                rows="5"
                                placeholder="<?php esc_attr_e('word1&#10;word2&#10;word3', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?>"
                            ></textarea>
                            <p class="submit">
                                <input 
                                    type="submit" 
                                    name="blacklist_word_checker_import" 
                                    class="button button-primary" 
                                    value="<?php esc_attr_e('Import Blacklist', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?>"
                                >
                                <span class="description">
                                    <?php esc_html_e('This will replace your current blacklist.', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?>
                                </span>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
