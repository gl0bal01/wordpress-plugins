# WpPost2GitHubMarkdown WordPress Plugin

A lightweight, secure WordPress plugin that automatically syncs your blog posts to GitHub as markdown files with YAML frontmatter. Built with modern PHP standards (PSR-12) and WordPress best practices.

[![WordPress Plugin Version](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v3%2B-green.svg)](https://www.gnu.org/licenses/gpl-3.0.html)

## 🚀 Features

- **🔄 Automated Syncing**: Uses WordPress cron or system cron for reliable background processing
- **↔️ Bidirectional Sync**: WordPress ↔ GitHub - changes in either direction are automatically synced
- **📝 Clean Markdown**: Converts HTML posts to properly formatted markdown with YAML frontmatter
- **📁 Organized Files**: Creates files as `post-slug-DD-MM-YYYY.md` for easy navigation
- **🔒 Security First**: Built with WordPress security standards, input validation, and secure API handling
- **⚡ Performance Optimized**: Background processing that doesn't impact site performance
- **📊 Monitoring**: Comprehensive sync status dashboard with error tracking
- **🛠️ Flexible Configuration**: Customizable sync intervals, post filters, and repository settings
- **🎯 GitHub Webhooks**: Real-time updates when markdown files are modified on GitHub
- **🔄 Conflict Resolution**: Intelligent handling of simultaneous edits

## 📋 Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **GitHub**: Personal Access Token with repository permissions
- **Server**: cURL support (standard in most hosting environments)

## 🛠️ Installation

### Quick Installation

1. **Download** or clone this repository to your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/gl0bal01/worpress-plugins.git
   ```

2. **Activate** the plugin in WordPress Admin → Plugins

3. **Configure** in Settings → WpPost2GitHubMarkdown

### Manual Installation

1. Download the latest release ZIP file
2. Upload through WordPress Admin → Plugins → Add New → Upload Plugin
3. Activate and configure

## ⚙️ Configuration

### Step 1: GitHub Setup

1. **Create a Personal Access Token**:
   ```
   GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
   ```

2. **Required Scopes**:
   - `repo` (for private repositories)
   - `public_repo` (for public repositories only)

3. **Create or Choose Repository**:
   - Public or private repository
   - Ensure it has a `main` branch
   - The plugin will create a `/posts/` directory automatically **if not you must create it**

### Step 2: Plugin Configuration

Navigate to **Settings → WpPost2GitHubMarkdown** in your WordPress admin:

```
GitHub Personal Access Token: [your-token-here]
GitHub Repository: username/repository-name
Sync Interval: hourly (recommended)
✅ Enable automatic syncing
✅ Sync published posts only
```

### Step 3: Test Configuration

1. Click "**Sync Now**" to test the connection
2. Check the "**Sync Status**" table for any errors
3. Verify files appear in your GitHub repository under `/posts/`

### Step 4: Enable Bidirectional Sync (Optional)

1. **Enable bidirectional sync** in the plugin settings
2. **Generate a webhook secret** for security
3. **Set up GitHub webhook** using the provided instructions
4. **Test by editing** a markdown file directly on GitHub!

## 🕐 Cron Setup Options

### Option 1: WordPress Cron (Default)

The plugin works out-of-the-box with WordPress cron. Suitable for low-to-medium traffic sites.

**Pros**: No server configuration needed
**Cons**: Relies on site traffic to trigger cron jobs

### Option 2: System Cron (Recommended)

For better reliability, especially on high-traffic sites or when precise timing is important:

#### Disable WordPress Cron

Add to your `wp-config.php`:
```php
define('DISABLE_WP_CRON', true);
```

#### Set Up System Crontab

**Option A: Using wget**
```bash
# Edit crontab
crontab -e

# Hourly sync
0 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1

# Every 15 minutes
*/15 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1

# Every 30 minutes
*/30 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

**Option B: Using curl**
```bash
# Hourly sync with better error handling
0 * * * * curl -s -m 30 https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1

# With logging
0 * * * * curl -s -m 30 https://yoursite.com/wp-cron.php?doing_wp_cron >> /var/log/wp-cron.log 2>&1
```

**Option C: Using WP-CLI (Advanced)**
```bash
# If WP-CLI is available
0 * * * * /usr/local/bin/wp cron event run --due-now --path=/path/to/wordpress >/dev/null 2>&1
```

#### Verify System Cron

```bash
# Check if cron is running
sudo systemctl status cron

# View cron logs
sudo grep CRON /var/log/syslog | tail -10

# Test manual execution
wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron
```

### Option 3: External Cron Services

For shared hosting without cron access, use external services:

- [Cronitor](https://crontab.guru/)

**Configuration**:
- URL: `https://yoursite.com/wp-cron.php?doing_wp_cron`
- Interval: Every 15-60 minutes
- Method: GET

## 📄 Generated Markdown Format

The plugin converts WordPress posts to this format:

```markdown
---
title: "Your Post Title"
date: 2025-06-15 14:30:00
author: John Doe
status: publish
excerpt: "Brief description of the post content"
categories:
  - Technology
  - WordPress
tags:
  - github
  - automation
  - markdown
permalink: https://yoursite.com/your-post-title/
---

# Your Post Title

Your post content converted to clean markdown with proper formatting:

- **Bold text** and *italic text*
- [Links](https://example.com) and images
- Code blocks and `inline code`
- Lists and blockquotes

> This is a blockquote example

## Subheadings

More content here...
```

## 🏗️ Plugin Architecture

### File Structure
```
wppost2githubmarkdown/
├── wppost2githubmarkdown.php  # Main plugin file
├── readme.txt                 # WordPress plugin readme
└── README.md                  # This file
```

### Database Schema
```sql
CREATE TABLE wp_wppost2githubmarkdown_log (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    post_id bigint(20) unsigned NOT NULL,
    sync_status varchar(20) NOT NULL DEFAULT 'pending',
    github_path varchar(500) DEFAULT NULL,
    last_sync_time datetime DEFAULT NULL,
    error_message text DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY post_id (post_id),
    KEY sync_status (sync_status)
);
```

### Key Classes and Methods

```php
WpPost2GitHubMarkdown\WpPost2GitHubMarkdownPlugin
├── processSyncQueue()       # Main cron function
├── syncPost()              # Sync individual post
├── convertPostToMarkdown() # HTML to Markdown conversion
├── uploadToGitHub()        # GitHub API integration
└── updateSyncLog()         # Status tracking
```

## 🔧 Troubleshooting

### Common Issues

**1. "GitHub API request failed"**
```
Solution: Check your Personal Access Token permissions and repository name format
```

**2. "Plugin not properly configured"**
```
Solution: Ensure both GitHub token and repository are set correctly
```

**3. "Cron not running"**
```
Solution: Enable WordPress cron or set up system cron as described above
```

### Debug Mode

Enable WordPress debug logging in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs at `/wp-content/debug.log` for detailed error messages.

### Manual Testing

Test the sync process manually:
```php
// In WordPress admin or via WP-CLI
do_action('wppost2githubmarkdown_cron');
```

## 🔒 Security Considerations

- **Token Storage**: GitHub tokens are stored in WordPress options table (encrypted in transit)
- **Input Validation**: All user inputs are sanitized and validated
- **Nonce Protection**: All forms use WordPress nonces for CSRF protection
- **Capability Checks**: Only users with `manage_options` capability can configure
- **API Rate Limiting**: Built-in rate limiting and retry logic for GitHub API

## 🚦 Performance

- **Background Processing**: All syncing happens via cron jobs
- **Batch Processing**: Processes maximum 10 posts per cron run to avoid timeouts
- **Memory Efficient**: Minimal memory footprint during sync operations
- **No Frontend Impact**: Zero impact on site performance for visitors

## 🔄 Bidirectional Sync. (Not recommended)

The plugin now supports **bidirectional synchronization**! When you edit markdown files directly on GitHub, the changes will automatically sync back to your WordPress posts.

### How It Works

1. **GitHub → WordPress**: When you edit a markdown file in your GitHub repository, a webhook triggers WordPress to update the corresponding post
2. **WordPress → GitHub**: When you update a post in WordPress, the next cron run will sync the changes to GitHub
3. **Conflict Resolution**: The system intelligently handles simultaneous edits using timestamps

### Setup Bidirectional Sync

1. **Enable the feature** in Settings → WpPost2GitHubMarkdown
2. **Generate a webhook secret** for security
3. **Set up a GitHub webhook** using the provided URL and secret
4. **Edit files on GitHub** and watch them sync to WordPress!

### Webhook Configuration

When bidirectional sync is enabled, you'll see detailed instructions for setting up the GitHub webhook:

- **Payload URL**: `https://yoursite.com/wp-json/wppost2githubmarkdown/v1/webhook`
- **Content Type**: `application/json`
- **Secret**: Generated secure token
- **Events**: Push events only
- **Active**: ✅ Enabled

### Testing Bidirectional Sync

```bash
# Test webhook endpoint with WP-CLI
wp eval "do_action('rest_api_init'); echo 'Webhook endpoint registered.';"

# Check webhook logs
tail -f /path/to/wordpress/wp-content/debug.log | grep "WpPost2GitHubMarkdown"
```

### Development Setup

```bash
# Clone repository
git clone https://github.com/gl0bal01/wordpress-plugins/wppost2githubmarkdown.git

# Install development dependencies (if added in future)
composer install --dev

# Run coding standards check
./vendor/bin/phpcs --standard=PSR12 wppost2githubmarkdown.php
```

## 📝 License

This project is licensed under the GPL v3 or later - see the [LICENSE](LICENSE) file for details.

## 💖 Acknowledgments

- Built with WordPress coding standards and best practices
- Follows PSR-12 PHP coding standards
- Inspired by the need for simple, reliable content backup solutions
- Thanks to the WordPress and GitHub communities for excellent APIs and documentation

---

**Made with ❤️ for the WordPress community**
