# Testing Bidirectional Sync - WpPost2GitHubMarkdown

This guide helps you test the bidirectional sync functionality to ensure it's working correctly.

## 🔄 Prerequisites

1. ✅ Plugin installed and activated
2. ✅ GitHub token configured
3. ✅ Repository configured
4. ✅ WordPress → GitHub sync working
5. ✅ Bidirectional sync enabled
6. ✅ Webhook secret generated
7. ✅ GitHub webhook configured

## 🧪 Test Workflow

### Step 1: Verify Webhook Endpoint

```bash
# Test webhook endpoint exists
wp eval "
if (function_exists('rest_get_server')) {
    echo 'REST API is available' . PHP_EOL;
    do_action('rest_api_init');
    echo 'Webhook endpoint should be registered' . PHP_EOL;
} else {
    echo 'REST API not available' . PHP_EOL;
}
"

# Check if endpoint is registered
curl -X POST https://yoursite.com/wp-json/wppost2githubmarkdown/v1/webhook \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'
```

### Step 2: Test WordPress → GitHub Sync

1. **Create a test post** in WordPress:
   - Title: "Test Bidirectional Sync"
   - Content: "This is a test post for bidirectional sync."
   - Status: Published

2. **Trigger sync**:
   ```bash
   wp eval "do_action('wppost2githubmarkdown_cron');"
   ```

3. **Verify on GitHub**:
   - Check if file `test-bidirectional-sync-DD-MM-YYYY.md` appears in `/posts/`
   - Verify the markdown content is correct

### Step 3: Test GitHub → WordPress Sync

1. **Edit the markdown file on GitHub**:
   ```markdown
   ---
   title: "Test Bidirectional Sync - UPDATED"
   date: 2025-06-15 14:30:00
   author: Your Name
   status: publish
   excerpt: "Updated via GitHub"
   categories:
     - Testing
   tags:
     - bidirectional
     - sync
   permalink: https://yoursite.com/test-bidirectional-sync/
   ---

   # Test Bidirectional Sync - UPDATED

   This content was **updated directly on GitHub** to test bidirectional sync!

   ## New Section

   - Added via GitHub edit
   - Should appear in WordPress
   - Testing markdown conversion

   > This is a quote added on GitHub
   ```

2. **Commit the changes** on GitHub

3. **Check WordPress**:
   - Go to WordPress Admin → Posts
   - Find the "Test Bidirectional Sync" post
   - Verify the title shows "Test Bidirectional Sync - UPDATED"
   - Check that the content was updated
   - Verify categories and tags were updated

### Step 4: Check Logs

```bash
# Check WordPress debug logs
tail -f /path/to/wordpress/wp-content/debug.log | grep "WpPost2GitHubMarkdown"

# Check sync log table
wp db query "
SELECT post_id, sync_status, github_path, last_sync_time, error_message 
FROM wp_wppost2githubmarkdown_log 
WHERE sync_status = 'updated_from_github' 
ORDER BY updated_at DESC LIMIT 5;
"

# Check specific post sync history
wp eval "
\$logs = \$wpdb->get_results(
    \"SELECT * FROM {\$wpdb->prefix}wppost2githubmarkdown_log 
     WHERE github_path LIKE '%test-bidirectional-sync%' 
     ORDER BY updated_at DESC\", 
    ARRAY_A
);
foreach (\$logs as \$log) {
    echo \"{\$log['sync_status']} - {\$log['updated_at']} - {\$log['error_message']}\" . PHP_EOL;
}
"
```

## 🔍 Troubleshooting Tests

### Test Webhook Signature Verification

```bash
# Test with correct signature
WEBHOOK_SECRET="your-webhook-secret"
PAYLOAD='{"test": "data"}'
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$WEBHOOK_SECRET" | cut -d' ' -f2)

curl -X POST https://yoursite.com/wp-json/wppost2githubmarkdown/v1/webhook \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: push" \
  -H "X-Hub-Signature-256: sha256=$SIGNATURE" \
  -d "$PAYLOAD"
```

### Test Invalid Webhook

```bash
# Test with invalid signature (should fail)
curl -X POST https://yoursite.com/wp-json/wppost2githubmarkdown/v1/webhook \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: push" \
  -H "X-Hub-Signature-256: sha256=invalid" \
  -d '{"test": "data"}'
```

### Test Non-Push Event

```bash
# Test with different event type (should be ignored)
curl -X POST https://yoursite.com/wp-json/wppost2githubmarkdown/v1/webhook \
  -H "Content-Type: application/json" \
  -H "X-GitHub-Event: pull_request" \
  -d '{"test": "data"}'
```

## 📊 Expected Results

### Successful Bidirectional Sync:
- ✅ WordPress post updated with new content from GitHub
- ✅ Post title, content, categories, and tags updated
- ✅ Sync log entry with status `updated_from_github`
- ✅ No error messages in debug log
- ✅ Webhook responds with 200 status

### Common Issues:
- ❌ Webhook signature verification fails → Check secret configuration
- ❌ Post not found → Verify the file path matches sync log
- ❌ Content not parsing → Check markdown frontmatter format
- ❌ Webhook not triggered → Verify GitHub webhook configuration

## 🏁 Success Criteria

Your bidirectional sync is working correctly when:

1. **WordPress → GitHub**: New/updated posts sync to markdown files
2. **GitHub → WordPress**: Edited markdown files update WordPress posts  
3. **Webhook Security**: Invalid signatures are rejected
4. **Error Handling**: Failed syncs are logged with helpful error messages
5. **Content Integrity**: Markdown ↔ HTML conversion preserves formatting

## 🛠️ Debug Commands

```bash
# Enable debug mode
wp config set WP_DEBUG true --type=constant
wp config set WP_DEBUG_LOG true --type=constant

# Test markdown parsing
wp eval "
\$plugin = WpPost2GitHubMarkdown\WpPost2GitHubMarkdownPlugin::getInstance();
\$markdown = '---
title: \"Test\"
---
# Test Content';
echo 'Testing markdown parsing...' . PHP_EOL;
"

# Check REST API routes
wp eval "
\$routes = rest_get_server()->get_routes();
foreach (\$routes as \$route => \$handlers) {
    if (strpos(\$route, 'wppost2githubmarkdown') !== false) {
        echo \"Route: \$route\" . PHP_EOL;
    }
}
"
```

---
**Happy syncing! 🚀**
