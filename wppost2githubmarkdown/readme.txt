=== WpPost2GitHubMarkdown ===
Contributors: gl0bal01
Donate link: https://github.com/gl0bal01/wordpress-plugins/wppost2githubmarkdown
Tags: github, markdown, sync, backup, version-control, git, automation, posts
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Automatically sync WordPress posts to GitHub as markdown files with YAML frontmatter using reliable cronjobs.

== Description ==

WpPost2GitHubMarkdown is a lightweight WordPress plugin that automatically converts your blog posts to markdown format and syncs them to a GitHub repository. Perfect for developers who want to maintain their content in version control or create backups of their posts.

= Key Features =

* **Simple Cronjob Approach**: Uses WordPress cron (or system cron) for reliable background syncing
* **Bidirectional Sync**: WordPress ↔ GitHub - changes in either direction are automatically synced
* **Clean Markdown Conversion**: Converts HTML posts to properly formatted markdown with YAML frontmatter
* **Custom Filename Format**: Creates files as `post-slug-DD-MM-YYYY.md` for easy organization
* **GitHub Webhooks**: Real-time updates when markdown files are modified on GitHub
* **Security First**: Built with WordPress security best practices and input validation
* **Flexible Configuration**: Choose sync intervals, post status filters, and category exclusions
* **Error Handling**: Comprehensive logging and retry mechanisms for failed syncs
* **Manual Sync**: Trigger immediate syncs from the admin panel

= How It Works =

**WordPress → GitHub:**
1. Plugin runs on scheduled intervals (15 minutes to daily)
2. Finds new or modified posts since last sync
3. Converts posts to markdown with metadata frontmatter
4. Uploads files to your GitHub repository in `/posts/` directory
5. Tracks sync status and handles errors gracefully

**GitHub → WordPress (Bidirectional):**
1. GitHub webhook triggers when markdown files are modified
2. Plugin fetches the updated file content
3. Converts markdown back to HTML
4. Updates the corresponding WordPress post
5. Logs the bidirectional sync for monitoring

= Markdown Features =

The plugin converts WordPress posts to clean markdown including:

* YAML frontmatter with title, date, author, categories, tags, excerpt
* Proper heading structures (H1-H6)
* Bold and italic formatting
* Links and images
* Code blocks and inline code
* Lists (ordered and unordered)
* Blockquotes and horizontal rules

= GitHub Integration =

* Uses GitHub REST API v4 for reliable file operations
* Handles both file creation and updates
* Supports custom commit messages
* Rate limiting and retry logic built-in
* Works with public and private repositories

== Installation ==

= Automatic Installation =

1. In your WordPress admin, go to Plugins → Add New
2. Search for "WpPost2GitHubMarkdown"
3. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin zip file
2. Upload and extract to `/wp-content/plugins/wppost2githubmarkdown/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to Settings → WpPost2GitHubMarkdown to configure

= GitHub Setup =

1. Create a GitHub Personal Access Token:
   - Go to GitHub → Settings → Developer settings → Personal access tokens
   - Click "Generate new token (classic)"
   - Select scopes: `repo` (for private repos) or `public_repo` (for public repos)
   - Copy the generated token

2. Create or choose a repository for your posts
3. Ensure the repository has a `main` branch (or update plugin settings)

== Configuration ==

1. **GitHub Token**: Enter your Personal Access Token
2. **Repository**: Format as `username/repository-name`
3. **Sync Interval**: Choose how often to sync (15 minutes to daily)
4. **Sync Options**: 
   - Enable/disable automatic syncing
   - Sync published posts only (recommended)
5. **Save Settings** and optionally trigger a manual sync

= Using System Cron (Recommended for High-Traffic Sites) =

For better reliability, especially on high-traffic sites, use system cron instead of WordPress cron:

1. **Disable WordPress Cron** by adding to `wp-config.php`:
   ```php
   define('DISABLE_WP_CRON', true);
   ```

2. **Set up system crontab**:
   ```bash
   # Edit crontab
   crontab -e
   
   # Add line for hourly sync (adjust URL and timing as needed)
   0 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
   
   # Or for every 15 minutes
   */15 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
   ```

3. **Alternative with curl**:
   ```bash
   0 * * * * curl -s https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
   ```

= Bidirectional Sync Setup =

For GitHub → WordPress synchronization:

1. **Enable bidirectional sync** in plugin settings
2. **Generate webhook secret** for security
3. **Copy webhook URL** provided in settings
4. **Set up GitHub webhook**:
   - Go to your repository → Settings → Webhooks
   - Add webhook with the provided URL and secret
   - Select "Push events" only
   - Content type: application/json
5. **Test by editing** a markdown file on GitHub!

== Screenshots ==

1. **Settings Page** - Configure GitHub integration and sync options
2. **Sync Status** - Monitor sync progress and view error logs
3. **Manual Sync** - Trigger immediate synchronization
4. **Generated Markdown** - Example of converted post with frontmatter

== Frequently Asked Questions ==

= How do I get a GitHub Personal Access Token? =

1. Go to GitHub.com → Settings → Developer settings → Personal access tokens
2. Click "Generate new token (classic)"
3. Give it a descriptive name like "WordPress Sync"
4. Select the `repo` scope for full repository access
5. Copy the generated token and paste it in the plugin settings

= What happens if my GitHub repository doesn't exist? =

The plugin will return an error. Make sure to create the repository first and ensure your token has the correct permissions.

= Can I sync custom post types? =

Currently, the plugin only syncs regular WordPress posts. Custom post type support may be added in future versions.

= Will this slow down my website? =

No, the plugin uses background cron jobs that don't affect your site's frontend performance. For high-traffic sites, we recommend using system cron instead of WordPress cron.

= What if a sync fails? =

Failed syncs are logged with error messages in the admin panel. The plugin will retry failed syncs on the next cron run.

= Can I exclude certain posts or categories? =

Yes, you can configure the plugin to only sync published posts and exclude specific categories (feature coming in future update).

= What markdown format is used? =

The plugin generates GitHub Flavored Markdown with YAML frontmatter containing post metadata.

== Changelog ==

= 1.0.0 =
* Initial release
* WordPress cron integration
* GitHub API integration
* Markdown conversion with frontmatter
* Admin interface with sync status
* Manual sync capability
* Error logging and retry mechanisms
* Security hardening and input validation
* **NEW: Bidirectional sync support**
* **NEW: GitHub webhook integration**
* **NEW: Real-time markdown-to-WordPress updates**
* **NEW: Comprehensive webhook setup instructions**

== Upgrade Notice ==

= 1.0.0 =
Initial release of WpPost2GitHubMarkdown plugin. Start syncing your WordPress posts to GitHub today!

== Privacy Policy ==

This plugin connects to the GitHub API to sync your posts. The following data is transmitted:

* Post content (title, body, metadata)
* Your GitHub Personal Access Token (stored locally, transmitted to GitHub API)
* Basic site information in User-Agent headers

No data is shared with third parties other than GitHub for the core functionality.

== Credits ==

Developed with ❤️ for the WordPress community. Built following WordPress coding standards and security best practices.
