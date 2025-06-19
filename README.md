# WordPress Plugins

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://www.php.net/)
[![Code Standards](https://img.shields.io/badge/Code_Standards-PSR--12-orange.svg)](https://www.php-fig.org/psr/psr-12/)


## 🚀 Philosophy

These plugins are built with a "no-bloat" philosophy, focusing on:
- **Performance**: Lightweight, optimized code
- **Security**: Following WordPress security best practices
- **Standards**: PSR-12 compliant, modern PHP practices
- **Functionality**: Tools that solve real problems efficiently

## 📁 Plugin Collection

### Available Plugins

### 📚 [WP Table of Contents](./wp-table-of-contents.php)

**Description**: Automatically generates a table of contents for posts and pages based on heading tags (H1-H2)

**Features**:
- Automatic TOC generation from heading structure
- Admin controls to disable on specific content
- Clean, accessible markup
- Lightweight and fast

### 📄 [Wp Post 2 GitHub Markdown](./wppost2githubmarkdown)

**Description**: Automatically sync WordPress posts to GitHub as markdown files

**Features**:
- Automated WordPress to GitHub sync
- Bidirectional synchronization support
- Cronjob-based automation
- Markdown conversion with metadata preservation

### 🔗 [WP PopCash Slack Integration](./wp-popcash-slack-integration.php)

**Description**: Automatically sends published articles to Slack channels and creates Popcash campaigns

**Features**: 
- Automatic Slack notifications for tagged articles
- Popcash campaign creation integration
- Enhanced security and error handling
- API integration with robust validation

### 📝 [WP Inline Related Posts](./wp-inline-related-posts.php)

**Description**: Intelligently inserts unique related posts blocks inline within your content

**Features**: 
- Smart category-based post matching
- DOM manipulation for seamless integration
- Advanced filtering to prevent duplicates
- Intelligent content placement

### 🗺️ [WP Google News Sitemap](./wp-google-news-sitemap.php)

**Description**: Generates a Google News compliant XML sitemap for WordPress sites

**Features**: 
- Google News XML compliance
- Built-in caching for performance
- Sitemap validation
- Performance optimizations

### 🌍 [WP Article Timezones](./wp-article-timezones.php)

**Description**: Displays and converts times for multiple countries in post editor meta boxes

**Features**: 
- Multi-timezone time display
- WordPress timezone format integration
- Post editor meta box interface
- Real-time conversion

### 🚫 [WP Blacklist Word Checker](./wp-blacklist-word-checker)

**Description**: Scans post titles and content for blacklisted words in real-time with detailed reporting

**Features**: 
- Real-time content scanning
- Comprehensive blacklist management
- Detailed violation reporting
- Easy administration interface

## 📋 Development Standards

### Code Quality

- **PSR-12**: PHP coding standard compliance
- **WordPress Coding Standards**: Following official WP guidelines
- **Type Hints**: Modern PHP type declarations
- **Documentation**: Comprehensive PHPDoc blocks

### Security First

- ✅ Input sanitization and validation
- ✅ Output escaping
- ✅ Nonce verification
- ✅ Capability checks
- ✅ SQL injection prevention
- ✅ XSS protection

### Performance Optimized

- Minimal database queries
- Proper WordPress hooks usage
- Conditional loading
- Asset optimization
- Caching where appropriate

## 🛠️ Installation

### Individual Plugin Installation

1. **Download** the plugin folder from this repository
2. **Upload** to your WordPress `/wp-content/plugins/` directory
3. **Activate** the plugin through the WordPress admin panel
4. **Configure** via the plugin's settings page (if applicable)

### Development Installation

```bash
# Clone the repository
git clone https://github.com/gl0bal01/wordpress-plugins.git

# Navigate to your WordPress plugins directory
cd /path/to/your/wordpress/wp-content/plugins/

# Copy the desired plugin
cp -r /path/to/wordpress-plugins/plugin-name ./
```

## 📖 Documentation

Each plugin includes:
- **README.md**: Installation and usage instructions
- **Inline Documentation**: PHPDoc comments throughout the code

## 🔧 Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher

## 🤝 Contributing

While these are personal plugins, contributions are welcome:

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/awesome-feature`)
3. **Commit** your changes (`git commit -m 'Add awesome feature'`)
4. **Push** to the branch (`git push origin feature/awesome-feature`)
5. **Open** a Pull Request

### Contribution Guidelines

- Follow PSR-12 coding standards
- Include proper documentation
- Add/update tests where applicable
- Ensure security best practices

## 📄 License

This project is licensed under the **GNU General Public License v3.0**.

```
Copyright (C) 2025 gl0bal01

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.
```


**Built with ❤️ and ☕**

*"No bloat – just tools that get the job done."*
