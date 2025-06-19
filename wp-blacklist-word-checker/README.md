# Blacklist Word Checker

A comprehensive WordPress plugin that scans post titles and content for blacklisted words in real-time, providing detailed reporting and easy blacklist management.

## Features

- **Real-time Word Detection**: Automatically scans post titles and content as you type
- **Dual Editor Support**: Works with both Gutenberg and Classic Editor
- **Detailed Reporting**: Shows exact word counts and locations (title vs content)
- **Easy Management**: Simple admin interface for adding/removing blacklisted words
- **Import/Export**: Bulk import and export blacklist functionality
- **Security First**: Built with WordPress security best practices
- **Accessibility**: WCAG compliant interface with keyboard navigation support
- **Performance Optimized**: Debounced checking to prevent excessive server requests

## Installation

1. Download the plugin files
2. Upload to your WordPress `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **Settings > Blacklist Words** to configure your blacklist

## Usage

### Adding Blacklist Words

1. Go to **Settings > Blacklist Words** in your WordPress admin
2. Enter a word in the "Add New Blacklist Word" field
3. Click "Add Word"

### Managing the Blacklist

- **View Current Words**: All blacklisted words are displayed in a table
- **Delete Words**: Click the "Delete" button next to any word to remove it
- **Import Words**: Paste a list of words (one per line) to bulk import
- **Export Words**: Copy the export textarea content to backup your list

### Checking Posts

1. Create or edit a post/page
2. The "Blacklist Word Checker" meta box appears in the sidebar
3. As you type in the title or content, the checker automatically scans for blacklisted words
4. Results show:
   - Total number of blacklisted words found
   - Breakdown by title and content
   - Individual word counts

## Technical Details

### Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher

### File Structure

```
wp-blacklist-word-checker/
├── wp-blacklist-word-checker.php (Main plugin file)
├── admin/
│   ├── class-blacklist-word-checker-admin.php (Admin functionality)
│   └── index.php (Security file)
├── includes/
│   ├── class-blacklist-word-checker.php (Core functionality)
│   └── index.php (Security file)
├── assets/
│   ├── css/
│   │   ├── blacklist-checker.css (Styles)
│   │   └── index.php (Security file)
│   ├── js/
│   │   ├── blacklist-checker.js (Frontend JavaScript)
│   │   ├── blacklist-admin.js (Admin JavaScript)
│   │   └── index.php (Security file)
│   └── index.php (Security file)
├── languages/
│   └── index.php (Security file)
├── README.md (This file)
└── index.php (Security file)
```

### Security Features

- Nonce verification for all forms
- Data sanitization and validation
- Capability checks for admin functions
- Secure AJAX handling
- SQL injection prevention
- XSS protection
- Directory browsing prevention

### Performance Features

- Debounced checking (1-second delay)
- Efficient regex patterns with word boundaries
- Minimal server requests
- Optimized HTML stripping
- Smart content change detection

## Customization

### Hooks and Filters

The plugin provides several hooks for customization:

```php
// Modify default blacklist on activation
add_filter('blacklist_word_checker_default_words', function($words) {
    return array_merge($words, ['custom', 'words']);
});

// Modify supported post types
add_filter('blacklist_word_checker_post_types', function($types) {
    return array_merge($types, ['custom_post_type']);
});
```

### CSS Customization

Add custom styles by targeting these classes:

```css
.blacklist-warning { /* Red warning box */ }
.blacklist-success { /* Green success box */ }
.blacklist-section-title { /* Section headers */ }
#blacklist-checker-results ul { /* Results lists */ }
```

## Troubleshooting

### Common Issues

**1. Checker not working in Gutenberg**
- Ensure your WordPress version is 5.0+
- Check browser console for JavaScript errors
- Verify the plugin is properly activated

**2. Words not being detected**
- Check that words are properly added to the blacklist
- Ensure words match exactly (case-insensitive)
- Verify word boundaries (partial matches won't trigger)

**3. Admin page not loading**
- Check user permissions (requires `manage_options` capability)
- Verify plugin files are properly uploaded
- Check for PHP errors in debug log

### Debug Mode

To enable debug logging, add to your `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Changelog

### Version 1.0.0
- Initial release
- Real-time word detection
- Admin management interface
- Import/export functionality
- Security and performance optimizations

## 📝 License

This plugin is licensed under the GPL v3 or later.
