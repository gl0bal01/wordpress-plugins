/**
 * Blacklist Word Checker JavaScript - Refactored Version
 * 
 * Enhanced version with improved error handling, debouncing,
 * and better integration with both Gutenberg and Classic editors.
 * 
 * @package BlacklistWordChecker
 * @since   1.0.0
 */
(function($) {
    'use strict';

    /**
     * Main BlacklistChecker class
     */
    class BlacklistChecker {
        constructor() {
            this.config = blacklistCheckerData || {};
            this.debounceTimer = null;
            this.debounceDelay = 1000;
            this.isChecking = false;
            this.lastCheckedTitle = '';
            this.lastCheckedContent = '';
            
            this.init();
        }

        /**
         * Initialize the checker
         */
        init() {
            if (!this.config.blacklist || !Array.isArray(this.config.blacklist)) {
                console.warn('Blacklist Word Checker: Invalid configuration');
                return;
            }

            this.setupEventListeners();
            
            // Initial check with delay to ensure editor is ready
            setTimeout(() => {
                this.checkContent();
            }, 1500);
        }

        /**
         * Setup event listeners for different editors
         */
        setupEventListeners() {
            if (this.isGutenbergEditor()) {
                this.setupGutenbergListeners();
            } else {
                this.setupClassicListeners();
            }
        }

        /**
         * Check if Gutenberg editor is active
         * @returns {boolean}
         */
        isGutenbergEditor() {
            return typeof wp !== 'undefined' && 
                   wp.data && 
                   wp.data.select && 
                   wp.data.select('core/editor');
        }

        /**
         * Setup listeners for Gutenberg editor
         */
        setupGutenbergListeners() {
            if (!wp.data.subscribe) {
                console.warn('Blacklist Word Checker: wp.data.subscribe not available');
                return;
            }

            wp.data.subscribe(() => {
                this.debouncedCheck();
            });
        }

        /**
         * Setup listeners for Classic editor
         */
        setupClassicListeners() {
            // Title input listener
            $('#title').on('input keyup paste', () => {
                this.debouncedCheck();
            });

            // TinyMCE editor listeners
            if (typeof tinyMCE !== 'undefined') {
                const checkTinyMCE = () => {
                    if (tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
                        tinyMCE.activeEditor.on('keyup change paste', () => {
                            this.debouncedCheck();
                        });
                    }
                };

                // Check immediately if editor is ready
                if (tinyMCE.activeEditor) {
                    checkTinyMCE();
                } else {
                    // Wait for editor to be ready
                    $(document).on('tinymce-editor-init', checkTinyMCE);
                }
            }

            // Textarea fallback for HTML mode
            $('#content').on('input keyup paste', () => {
                this.debouncedCheck();
            });
        }

        /**
         * Debounced check to avoid too many requests
         */
        debouncedCheck() {
            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }

            this.debounceTimer = setTimeout(() => {
                this.checkContent();
            }, this.debounceDelay);
        }

        /**
         * Get current title from editor
         * @returns {string}
         */
        getCurrentTitle() {
            let title = '';

            if (this.isGutenbergEditor()) {
                try {
                    title = wp.data.select('core/editor').getEditedPostAttribute('title') || '';
                } catch (error) {
                    console.warn('Blacklist Word Checker: Error getting Gutenberg title', error);
                }
            } else {
                title = $('#title').val() || '';
            }

            return String(title).trim();
        }

        /**
         * Get current content from editor
         * @returns {string}
         */
        getCurrentContent() {
            let content = '';

            if (this.isGutenbergEditor()) {
                try {
                    content = wp.data.select('core/editor').getEditedPostContent() || '';
                } catch (error) {
                    console.warn('Blacklist Word Checker: Error getting Gutenberg content', error);
                }
            } else if (typeof tinyMCE !== 'undefined' && 
                       tinyMCE.activeEditor && 
                       !tinyMCE.activeEditor.isHidden()) {
                content = tinyMCE.activeEditor.getContent() || '';
            } else {
                content = $('#content').val() || '';
            }

            return String(content).trim();
        }

        /**
         * Check content for blacklisted words
         */
        checkContent() {
            if (this.isChecking) {
                return;
            }

            const currentTitle = this.getCurrentTitle();
            const currentContent = this.getCurrentContent();

            // Skip check if content hasn't changed
            if (currentTitle === this.lastCheckedTitle && 
                currentContent === this.lastCheckedContent) {
                return;
            }

            this.lastCheckedTitle = currentTitle;
            this.lastCheckedContent = currentContent;

            this.isChecking = true;
            this.showLoadingMessage();

            $.ajax({
                url: this.config.ajaxurl,
                type: 'POST',
                data: {
                    action: 'check_blacklist_words',
                    nonce: this.config.nonce,
                    title: currentTitle,
                    content: currentContent
                },
                timeout: 10000, // 10 second timeout
                success: (response) => {
                    this.handleResponse(response);
                },
                error: (xhr, status, error) => {
                    this.handleError(xhr, status, error);
                },
                complete: () => {
                    this.isChecking = false;
                }
            });
        }

        /**
         * Show loading message
         */
        showLoadingMessage() {
            const $results = $('#blacklist-checker-results');
            $results.html(`<p class="blacklist-checking">${this.config.checkingMessage}</p>`);
        }

        /**
         * Handle successful AJAX response
         * @param {Object} response
         */
        handleResponse(response) {
            if (!response || typeof response !== 'object') {
                this.displayError('Invalid response format');
                return;
            }

            if (response.success && response.data) {
                this.displayResults(response.data);
            } else {
                const message = response.data && response.data.message 
                    ? response.data.message 
                    : 'Unknown error occurred';
                this.displayError(message);
            }
        }

        /**
         * Handle AJAX error
         * @param {Object} xhr
         * @param {string} status
         * @param {string} error
         */
        handleError(xhr, status, error) {
            console.error('Blacklist Word Checker AJAX Error:', { xhr, status, error });
            
            let errorMessage = 'Error checking content.';
            
            if (status === 'timeout') {
                errorMessage = 'Request timed out. Please try again.';
            } else if (xhr.status === 403) {
                errorMessage = 'Permission denied. Please refresh the page.';
            } else if (xhr.status >= 500) {
                errorMessage = 'Server error. Please try again later.';
            }

            this.displayError(errorMessage);
        }

        /**
         * Display error message
         * @param {string} message
         */
        displayError(message) {
            const $results = $('#blacklist-checker-results');
            $results.html(`<p class="blacklist-error" style="color: #d63638;">${this.escapeHtml(message)}</p>`);
        }

        /**
         * Display results
         * @param {Object} data
         */
        displayResults(data) {
            if (!data || typeof data !== 'object') {
                this.displayError('Invalid data received');
                return;
            }

            const $results = $('#blacklist-checker-results');
            let html = '';

            if (data.totalFound > 0) {
                html += '<div class="blacklist-warning">';
                html += `<p><strong>${data.totalFound} ${this.config.wordsFoundText}</strong></p>`;

                // Display title results
                if (data.title && data.title.totalFound > 0) {
                    html += this.renderSection(this.config.titleText, data.title.results);
                }

                // Display content results
                if (data.content && data.content.totalFound > 0) {
                    html += this.renderSection(this.config.contentText, data.content.results);
                }

                html += '</div>';
            } else {
                html = `<p class="blacklist-success">${this.config.noWordsFound}</p>`;
            }

            $results.html(html);
        }

        /**
         * Render a results section
         * @param {string} title
         * @param {Object} results
         * @returns {string}
         */
        renderSection(title, results) {
            if (!results || typeof results !== 'object') {
                return '';
            }

            let html = `<p class="blacklist-section-title">${this.escapeHtml(title)}</p>`;
            html += '<ul>';

            for (const [word, count] of Object.entries(results)) {
                html += `<li>${this.escapeHtml(word)}: <strong>${parseInt(count, 10)}</strong></li>`;
            }

            html += '</ul>';
            return html;
        }

        /**
         * Escape HTML characters
         * @param {string} text
         * @returns {string}
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new BlacklistChecker();
    });

})(jQuery);
