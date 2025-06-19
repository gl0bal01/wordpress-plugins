<?php
/**
 * Main plugin class for Blacklist Word Checker
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
 * Main plugin functionality class
 *
 * Handles the core blacklist word checking functionality,
 * meta box display, and AJAX handling.
 *
 * @since 1.0.0
 */
class BlacklistWordChecker
{
    /**
     * Option name for storing blacklist
     *
     * @var string
     */
    private string $optionName = 'blacklist_word_checker_list';

    /**
     * Nonce name for security
     *
     * @var string
     */
    private string $nonceName = 'blacklist_word_checker_nonce';

    /**
     * Script handle for main JavaScript
     *
     * @var string
     */
    private string $scriptHandle = 'blacklist-word-checker-js';

    /**
     * Style handle for CSS
     *
     * @var string
     */
    private string $styleHandle = 'blacklist-word-checker-css';

    /**
     * Initialize the plugin functionality
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
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('wp_ajax_check_blacklist_words', [$this, 'ajaxCheckBlacklistWords']);
    }

    /**
     * Get the current blacklist
     *
     * @return array<string> The blacklist of words
     */
    public function getBlacklist(): array
    {
        $blacklist = get_option($this->optionName, []);
        
        // Ensure we always return an array
        if (!is_array($blacklist)) {
            return [];
        }

        // Filter out empty values and ensure all items are strings
        return array_filter(array_map('strval', $blacklist), function (string $word): bool {
            return !empty(trim($word));
        });
    }

    /**
     * Add meta box to post editor
     *
     * @return void
     */
    public function addMetaBox(): void
    {
        $postTypes = ['post', 'page'];
        
        foreach ($postTypes as $postType) {
            add_meta_box(
                'blacklist_word_checker',
                esc_html__('Blacklist Word Checker', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN),
                [$this, 'renderMetaBox'],
                $postType,
                'side',
                'high'
            );
        }
    }

    /**
     * Enqueue scripts and styles for post editor
     *
     * @param string $hook The current admin page hook
     * @return void
     */
    public function enqueueScripts(string $hook): void
    {
        // Only load on post edit screens
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        // Register and enqueue the CSS
        wp_enqueue_style(
            $this->styleHandle,
            BLACKLIST_WORD_CHECKER_PLUGIN_URL . 'assets/css/blacklist-checker.css',
            [],
            BLACKLIST_WORD_CHECKER_VERSION
        );

        // Register and enqueue the JS
        wp_enqueue_script(
            $this->scriptHandle,
            BLACKLIST_WORD_CHECKER_PLUGIN_URL . 'assets/js/blacklist-checker.js',
            ['jquery'],
            BLACKLIST_WORD_CHECKER_VERSION,
            true
        );

        // Pass data to script securely
        wp_localize_script(
            $this->scriptHandle,
            'blacklistCheckerData',
            [
                'blacklist' => $this->getBlacklist(),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce($this->nonceName),
                'checkingMessage' => esc_html__('Checking for blacklisted words...', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN),
                'noWordsFound' => esc_html__('No blacklisted words found.', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN),
                'wordsFoundText' => esc_html__('blacklisted words found:', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN),
                'titleText' => esc_html__('Title:', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN),
                'contentText' => esc_html__('Content:', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN)
            ]
        );
    }

    /**
     * Render meta box content
     *
     * @param WP_Post $post The post object
     * @return void
     */
    public function renderMetaBox(WP_Post $post): void
    {
        // Create a nonce for security
        wp_nonce_field($this->nonceName, $this->nonceName);
        ?>
        <div id="blacklist-checker-results">
            <p><?php esc_html_e('Checking for blacklisted words...', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN); ?></p>
        </div>
        <?php
    }

    /**
     * Strip HTML tags and attributes more effectively for word checking
     *
     * This improved method better handles various HTML structures and entities
     * to provide more accurate word counting.
     *
     * @param string $content The HTML content
     * @return string The cleaned text content
     */
    private function stripHtmlForChecking(string $content): string
    {
        // Remove script and style tags completely with their content
        $content = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/si', ' ', $content);
        
        // Remove HTML comments
        $content = preg_replace('/<!--.*?-->/s', ' ', $content);
        
        // Replace common block-level elements with spaces to avoid word concatenation
        $content = preg_replace('/<\/(div|p|h[1-6]|li|td|th|br)>/i', ' ', $content);
        
        // Replace other HTML tags with spaces
        $content = preg_replace('/<[^>]+>/', ' ', $content);
        
        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Normalize whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Trim and return
        return trim($content);
    }

    /**
     * Check text for blacklisted words with improved accuracy
     *
     * @param string        $text      Text to check
     * @param array<string> $blacklist List of words to check for
     * @return array{results: array<string, int>, totalFound: int} Results with word counts
     */
    private function checkForBlacklistedWords(string $text, array $blacklist): array
    {
        $results = [];
        $totalFound = 0;

        if (empty($text) || empty($blacklist)) {
            return [
                'results' => $results,
                'totalFound' => $totalFound
            ];
        }

        // Convert text to lowercase for case-insensitive matching
        $textLower = strtolower($text);

        foreach ($blacklist as $word) {
            $word = trim($word);
            
            if (empty($word)) {
                continue;
            }

            // Escape the word for regex and convert to lowercase
            $escapedWord = preg_quote(strtolower($word), '/');
            
            // Use word boundaries for more accurate matching
            // This prevents matching parts of larger words
            $pattern = '/\b' . $escapedWord . '\b/u';
            
            $matches = [];
            $count = preg_match_all($pattern, $textLower, $matches);
            
            if ($count > 0) {
                $results[$word] = $count;
                $totalFound += $count;
            }
        }

        return [
            'results' => $results,
            'totalFound' => $totalFound
        ];
    }

    /**
     * AJAX handler for checking blacklisted words
     *
     * @return void
     */
    public function ajaxCheckBlacklistWords(): void
    {
        // Verify the nonce for security
        if (!check_ajax_referer($this->nonceName, 'nonce', false)) {
            wp_send_json_error([
                'message' => esc_html__('Security check failed.', BLACKLIST_WORD_CHECKER_TEXT_DOMAIN)
            ]);
            return;
        }

        $blacklist = $this->getBlacklist();
        $titleResults = ['results' => [], 'totalFound' => 0];
        $contentResults = ['results' => [], 'totalFound' => 0];

        // Check title if provided
        if (isset($_POST['title']) && is_string($_POST['title'])) {
            $titleText = sanitize_text_field(wp_unslash($_POST['title']));
            
            if (!empty($titleText)) {
                $titleResults = $this->checkForBlacklistedWords($titleText, $blacklist);
            }
        }

        // Check content if provided
        if (isset($_POST['content']) && is_string($_POST['content'])) {
            $rawContent = wp_kses_post(wp_unslash($_POST['content']));
            
            if (!empty($rawContent)) {
                // Strip HTML and attributes to check only the actual text content
                $contentText = $this->stripHtmlForChecking($rawContent);
                $contentResults = $this->checkForBlacklistedWords($contentText, $blacklist);
            }
        }

        // Calculate the overall total
        $totalFound = $titleResults['totalFound'] + $contentResults['totalFound'];

        // Send a JSON response
        wp_send_json_success([
            'title' => $titleResults,
            'content' => $contentResults,
            'totalFound' => $totalFound
        ]);
    }
}
