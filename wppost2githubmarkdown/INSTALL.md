# Quick Installation Guide - WpPost2GitHubMarkdown

## 📦 Installation Steps

1. **Copy Plugin Files**:
   - Copy the `wppost2githubmarkdown` folder to your WordPress plugins directory:
   - `wp-content/plugins/wppost2githubmarkdown/`

2. **Activate Plugin**:
   - Go to WordPress Admin → Plugins
   - Find "WpPost2GitHubMarkdown" and click "Activate"

3. **Configure Settings**:
   - Go to Settings → WpPost2GitHubMarkdown
   - Add your GitHub Personal Access Token
   - Set your repository (format: username/repo-name)
   - Enable automatic syncing
   - Choose sync interval

## 🔑 GitHub Token Setup

1. Go to GitHub.com → Settings → Developer settings → Personal access tokens
2. Click "Generate new token (classic)"
3. Name it "WordPress Sync"
4. Select scopes:
   - `repo` (for private repos)
   - `public_repo` (for public repos only)
5. Copy the token and paste in plugin settings

## ⚙️ Cron Setup (Optional but Recommended)

### For System Cron (Better Reliability):

1. **Disable WordPress Cron** in `wp-config.php`:
   ```php
   define('DISABLE_WP_CRON', true);
   ```

2. **Add to system crontab**:
   ```bash
   crontab -e
   # Add this line for hourly sync:
   0 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
   ```

## 🧪 Test Your Setup

### Test WordPress → GitHub Sync
1. Click "Sync Now" in the plugin settings
2. Check the "Sync Status" table for any errors
3. Verify files appear in your GitHub repository under `/posts/` 

### Test GitHub → WordPress Sync (Bidirectional) !NOT RECOMMENDED. KNOW WHAT YOU DO.!
1. Enable "Bidirectional Sync" in plugin settings
2. Generate and save a webhook secret
3. Set up GitHub webhook using the provided instructions
4. Edit a markdown file directly on GitHub
5. Watch the corresponding WordPress post update automatically!
6. Check sync logs to verify the bidirectional update

## 📁 Expected File Structure

Your posts will be saved as:
```
your-repo/
└── posts/
    ├── my-first-post-15-06-2025.md
    ├── wordpress-tips-20-06-2025.md
    └── latest-update-01-07-2025.md
```

## 🔄 Bidirectional Sync Setup

### Enable Bidirectional Sync
1. Go to Settings → WpPost2GitHubMarkdown
2. Check "Enable bidirectional sync (GitHub → WordPress)"
3. Click "Generate Secret" to create a webhook secret
4. Save settings

### Set Up GitHub Webhook
1. Copy the webhook URL from the plugin settings
2. Go to your GitHub repository
3. Navigate to Settings → Webhooks
4. Click "Add webhook"
5. Configure:
   - **Payload URL**: Paste the webhook URL from plugin
   - **Content type**: application/json
   - **Secret**: Paste the webhook secret from plugin
   - **Events**: Select "Just the push event"
   - **Active**: Check the box
6. Click "Add webhook"

### Test Bidirectional Sync
1. Edit any markdown file in `/posts/` directory on GitHub
2. Commit the changes
3. Check your WordPress admin → Posts
4. The corresponding post should be updated!

## 🆘 Troubleshooting

- **"GitHub API request failed"**: Check token permissions
- **"Plugin not configured"**: Ensure token and repository are set
- **No files syncing**: Check if sync is enabled and posts are published. You might want to create the `/posts/`folder in your github repo
- **Webhook not working**: Verify webhook URL and secret are correctly configured
- **Bidirectional sync failing**: Check webhook delivery in GitHub repository settings

For detailed documentation, see README.md or readme.txt files.

---
**Ready to sync? Activate the plugin and configure your GitHub settings!**
