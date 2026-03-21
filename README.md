# Lightweight SEO

A lightweight WordPress SEO plugin that adds essential SEO functionality without bloat.

## 🎯 Introduction

Lightweight SEO is a simple yet powerful WordPress plugin designed to help you optimize your website for search engines without overwhelming you with complex features. It focuses on the essential SEO elements that matter most for your website's visibility.

## ✨ Features

- **Meta Information Management**

  - Custom title formats with variables support (%title%, %sitename%, %tagline%)
  - Meta description control
  - Meta keywords support
  - Individual page/post SEO settings

- **Social Media Optimization**

  - Open Graph support for better social sharing
  - Custom social media images
  - Twitter Card integration
  - Customizable social titles and descriptions

- **Search Engine Controls**

  - Noindex/nofollow controls
  - Global SEO settings
  - Per-page SEO overrides
  - Clean, valid HTML output

- **Analytics & Tracking Integration**

  - Google Analytics 4 support
  - Google Tag Manager integration
  - Facebook Pixel implementation
  - Easy-to-use tracking ID management

- **User Experience**
  - Simple, intuitive interface
  - Minimal performance impact
  - WordPress standard design patterns
  - No bloat or unnecessary features

## 🏗️ Architecture

- `lightweight-seo.php` bootstraps the plugin and loads the core class
- `includes/class-lightweight-seo.php` wires shared services, admin UI, meta boxes, and frontend handlers
- `includes/class-lightweight-seo-settings.php` centralizes option defaults and resolved settings
- `includes/class-lightweight-seo-post-meta.php` centralizes SEO post meta, supported post types, and REST registration
- Frontend behavior is split across:
  - `includes/class-lightweight-seo-page-context-service.php`
  - `includes/class-lightweight-seo-title-service.php`
  - `includes/class-lightweight-seo-meta-tags-service.php`
  - `includes/class-lightweight-seo-tracking-service.php`

## 📚 Documentation

- [Documentation Index](docs/README.md)
- [Feature Guide](docs/features.md)
- [QA Checklist](docs/qa-checklist.md)

## 🧪 Local Development

For live testing during development, symlink this workspace into your local WordPress install:

```bash
ln -s "/Users/rakeshm/conductor/workspaces/lightweight-seo/bucharest" "/path/to/wp-content/plugins/lightweight-seo"
```

That lets WordPress load the latest files from this workspace directly.

## ✅ Quality Checks

Install development tools:

```bash
composer install
```

Run coding standards:

```bash
composer run phpcs
```

Run tests:

```bash
composer run test
```

## 🔁 Upgrade Notes

- Social images now support attachment IDs with URL fallback for backward compatibility
- Meta keywords output can now be disabled from plugin settings
- Supported SEO post types are now filterable through `lightweight_seo_supported_post_types`
- Frontend title, meta tags, page context, and tracking settings now expose extension hooks

## 📖 How to Use

### 1. Global Settings

1. Navigate to "SEO" in your WordPress dashboard
2. Configure your default title format (e.g., "%title% – %sitename%")
3. Set up your default meta description
4. Add your default social media image

### 2. Page/Post SEO

1. Edit any post or page
2. Scroll to the "SEO Settings" meta box
3. Customize:
   - SEO Title
   - Meta Description
   - Meta Keywords
   - Social Media Settings
   - Search Engine Indexing

### 3. Title Variables

You can use these variables in your title formats:

- `%title%` - Page/post title
- `%sitename%` - Your site's name
- `%tagline%` - Your site's tagline
- `%sep%` - Separator (displays as "–")

## 🔄 Release

[Latest Release (v1.0.2)](https://github.com/therakeshm/lightweight-seo/releases/latest)

## 👨‍💻 Author

**Rakesh Mandal**

- Website: [https://rakeshmandal.com](https://rakeshmandal.com)
- GitHub: [@therakeshm](https://github.com/therakeshm)

## 📜 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

Contributions are welcome! Feel free to:

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## 🐛 Bug Reports

If you find a bug, please create an issue with:

1. A clear description of the problem
2. Steps to reproduce
3. Expected behavior
4. Screenshots (if applicable)
5. Your WordPress version and environment details
