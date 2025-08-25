/**
 * Blacklist Word Checker Admin JavaScript - Refactored Version
 * 
 * Enhanced admin interface interactions with better UX,
 * error handling, and accessibility features.
 * 
 * @package BlacklistWordChecker
 * @since   1.0.0
 */
(function($) {
    'use strict';

    /**
     * Admin interface handler
     */
    class BlacklistAdmin {
        constructor() {
            this.config = blacklistAdminData || {};
            this.init();
        }

        /**
         * Initialize admin functionality
         */
        init() {
            this.setupDeleteConfirmation();
            this.setupFormValidation();
            this.setupTextareaHelpers();
            this.setupAccessibilityFeatures();
        }

        /**
         * Setup delete confirmation with improved UX
         */
        setupDeleteConfirmation() {
            $(document).on('click', '.delete-word', (e) => {
                const $link = $(e.currentTarget);
                const word = $link.data('word') || $link.attr('data-word');
                const confirmMessage = this.config.confirmDelete || 'Are you sure you want to delete this word?';
                
                // Create custom confirmation message
                const customMessage = confirmMessage.replace('%s', word || 'this word');
                
                if (!confirm(customMessage)) {
                    e.preventDefault();
                    return false;
                }
                
                // Add loading state
                $link.text('Deleting...').addClass('disabled');
                
                // Prevent double-clicking
                $link.prop('disabled', true);
            });
        }

        /**
         * Setup form validation
         */
        setupFormValidation() {
            // Add word form validation
            $('form').on('submit', (e) => {
                const $form = $(e.currentTarget);
                const $wordInput = $form.find('input[name="new_blacklist_word"]');
                
                if ($wordInput.length > 0) {
                    const word = $wordInput.val().trim();
                    
                    if (!word) {
                        e.preventDefault();
                        this.showError($wordInput, 'Please enter a word.');
                        return false;
                    }
                    
                    if (word.length > 100) {
                        e.preventDefault();
                        this.showError($wordInput, 'Word is too long (maximum 100 characters).');
                        return false;
                    }
                    
                    // DEBUG: Log the word being validated
                    console.log('Validating word:', word);
                    console.log('Pattern test result (Unicode):', /^[\p{L}\p{N}\s\-']+$/u.test(word));
                    
                    // Fallback pattern for broader browser compatibility
                    const compatiblePattern = /^[a-zA-Z0-9\u00C0-\u017F\u1E00-\u1EFF\s\-']+$/;
                    console.log('Pattern test result (Compatible):', compatiblePattern.test(word));
                    
                    // Check for invalid characters - Use compatible pattern for accented characters
                    if (!compatiblePattern.test(word)) {
                        e.preventDefault();
                        this.showError($wordInput, 'Word contains invalid characters.');
                        return false;
                    }
                    
                    // Clear any previous errors
                    this.clearError($wordInput);
                }
                
                // Import form validation
                const $importTextarea = $form.find('textarea[name="import_blacklist"]');
                if ($importTextarea.length > 0) {
                    const content = $importTextarea.val().trim();
                    
                    if (!content) {
                        e.preventDefault();
                        this.showError($importTextarea, 'Please enter words to import.');
                        return false;
                    }
                    
                    // Count lines
                    const lines = content.split('\n').filter(line => line.trim().length > 0);
                    if (lines.length > 1000) {
                        e.preventDefault();
                        this.showError($importTextarea, 'Too many words (maximum 1000 allowed).');
                        return false;
                    }
                    
                    this.clearError($importTextarea);
                }
            });
            
            // Real-time validation for word input
            $('input[name="new_blacklist_word"]').on('input', (e) => {
                const $input = $(e.currentTarget);
                const word = $input.val().trim();
                
                // DEBUG: Log real-time validation
                console.log('Real-time validating word:', word);
                console.log('Real-time pattern test result (Unicode):', /^[\p{L}\p{N}\s\-']+$/u.test(word));
                
                // Fallback pattern for broader browser compatibility
                const compatiblePattern = /^[a-zA-Z0-9\u00C0-\u017F\u1E00-\u1EFF\s\-']+$/;
                console.log('Real-time pattern test result (Compatible):', compatiblePattern.test(word));
                
                if (word && word.length > 100) {
                    this.showError($input, 'Word is too long (maximum 100 characters).');
                } else if (word && !compatiblePattern.test(word)) {
                    this.showError($input, 'Invalid characters in word.');
                } else {
                    this.clearError($input);
                }
            });
        }

        /**
         * Setup textarea helpers
         */
        setupTextareaHelpers() {
            // Auto-select export textarea content on click
            $('.blacklist-export textarea').on('click focus', function() {
                $(this).select();
            });
            
            // Add word counter for import textarea
            const $importTextarea = $('textarea[name="import_blacklist"]');
            if ($importTextarea.length > 0) {
                this.addWordCounter($importTextarea);
            }
            
            // Add copy button for export
            this.addCopyButton();
        }

        /**
         * Add word counter to textarea
         * @param {jQuery} $textarea
         */
        addWordCounter($textarea) {
            const $counter = $('<div class="word-counter" style="margin-top: 5px; font-size: 12px; color: #666;"></div>');
            $textarea.after($counter);
            
            const updateCounter = () => {
                const content = $textarea.val().trim();
                const lines = content ? content.split('\n').filter(line => line.trim().length > 0) : [];
                const wordCount = lines.length;
                
                $counter.text(`Words: ${wordCount} / 1000`);
                
                if (wordCount > 1000) {
                    $counter.css('color', '#d63638');
                } else if (wordCount > 800) {
                    $counter.css('color', '#dba617');
                } else {
                    $counter.css('color', '#666');
                }
            };
            
            $textarea.on('input keyup paste', updateCounter);
            updateCounter();
        }

        /**
         * Add copy button for export textarea
         */
        addCopyButton() {
            const $exportTextarea = $('.blacklist-export textarea');
            if ($exportTextarea.length === 0) return;
            
            const $copyButton = $('<button type="button" class="button button-small" style="margin-left: 10px;">Copy to Clipboard</button>');
            
            $exportTextarea.after($copyButton);
            
            $copyButton.on('click', () => {
                $exportTextarea.select();
                
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText($exportTextarea.val()).then(() => {
                            this.showSuccessMessage($copyButton, 'Copied!');
                        }).catch(() => {
                            this.fallbackCopy($exportTextarea, $copyButton);
                        });
                    } else {
                        this.fallbackCopy($exportTextarea, $copyButton);
                    }
                } catch (err) {
                    this.fallbackCopy($exportTextarea, $copyButton);
                }
            });
        }

        /**
         * Fallback copy method
         * @param {jQuery} $textarea
         * @param {jQuery} $button
         */
        fallbackCopy($textarea, $button) {
            try {
                $textarea.select();
                document.execCommand('copy');
                this.showSuccessMessage($button, 'Copied!');
            } catch (err) {
                this.showSuccessMessage($button, 'Copy failed - please select and copy manually');
            }
        }

        /**
         * Setup accessibility features
         */
        setupAccessibilityFeatures() {
            // Add ARIA labels where needed
            $('input[name="new_blacklist_word"]').attr('aria-label', 'Enter new blacklist word');
            $('textarea[name="import_blacklist"]').attr('aria-label', 'Paste blacklist words to import');
            $('.blacklist-export textarea').attr('aria-label', 'Exported blacklist words - read only');
            
            // Improve delete button accessibility
            $('.delete-word').each(function() {
                const $link = $(this);
                const word = $link.data('word') || $link.closest('tr').find('td:first').text().trim();
                $link.attr('aria-label', `Delete word: ${word}`);
            });
            
            // Add skip links for keyboard navigation
            this.addSkipLinks();
        }

        /**
         * Add skip links for better keyboard navigation
         */
        addSkipLinks() {
            const $container = $('.blacklist-manager-container');
            if ($container.length === 0) return;
            
            const skipLinks = [
                { href: '#add-word-form', text: 'Skip to add word form' },
                { href: '#current-words', text: 'Skip to current words list' },
                { href: '#import-export', text: 'Skip to import/export section' }
            ];
            
            const $skipNav = $('<nav class="skip-links" style="margin-bottom: 20px;"></nav>');
            
            skipLinks.forEach(link => {
                const $skipLink = $(`<a href="${link.href}" class="screen-reader-shortcut" style="position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden;">${link.text}</a>`);
                $skipLink.on('focus', function() {
                    $(this).css({
                        position: 'static',
                        width: 'auto',
                        height: 'auto',
                        overflow: 'visible',
                        background: '#f1f1f1',
                        padding: '8px 16px',
                        textDecoration: 'none',
                        zIndex: 100000
                    });
                }).on('blur', function() {
                    $(this).css({
                        position: 'absolute',
                        left: '-9999px',
                        width: '1px',
                        height: '1px',
                        overflow: 'hidden'
                    });
                });
                $skipNav.append($skipLink);
            });
            
            $container.before($skipNav);
            
            // Add IDs to target sections
            $('.blacklist-add-word-form').attr('id', 'add-word-form');
            $('.blacklist-current-words').attr('id', 'current-words');
            $('.blacklist-import-export').attr('id', 'import-export');
        }

        /**
         * Show error message for input
         * @param {jQuery} $input
         * @param {string} message
         */
        showError($input, message) {
            this.clearError($input);
            
            const $error = $(`<div class="error-message" style="color: #d63638; font-size: 12px; margin-top: 5px;">${this.escapeHtml(message)}</div>`);
            $input.after($error);
            $input.addClass('error').focus();
            
            // Add ARIA attributes
            const errorId = 'error-' + Date.now();
            $error.attr('id', errorId);
            $input.attr('aria-describedby', errorId);
        }

        /**
         * Clear error message for input
         * @param {jQuery} $input
         */
        clearError($input) {
            const $error = $input.next('.error-message');
            if ($error.length > 0) {
                $error.remove();
                $input.removeClass('error').removeAttr('aria-describedby');
            }
        }

        /**
         * Show temporary success message
         * @param {jQuery} $element
         * @param {string} message
         */
        showSuccessMessage($element, message) {
            const originalText = $element.text();
            $element.text(message).addClass('success');
            
            setTimeout(() => {
                $element.text(originalText).removeClass('success');
            }, 2000);
        }

        /**
         * Escape HTML to prevent XSS
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
        new BlacklistAdmin();
    });

})(jQuery);
