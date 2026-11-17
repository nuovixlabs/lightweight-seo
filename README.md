# Lightweight SEO

Lightweight SEO provides correct, predictable on-page SEO without a crawler, analytics dashboard, or background synchronization layer. It supports WordPress 6.0+ and PHP 7.4+.

## What it does

Core provides:

- document titles and templates
- meta descriptions, canonical URLs, and WordPress-native robots directives
- Open Graph and X/Twitter metadata
- post, term, and author SEO controls
- search visibility, sitemap listing, and a title format for each post type and taxonomy
- Organization or Person, WebSite, BreadcrumbList, ProfilePage, and scoped Article schema
- a site identity with its own name, logo or photo, X handle, and profile links, plus a choice of title separator
- verification meta tags for Google Search Console, Bing Webmaster Tools, Pinterest, and Yandex Webmaster
- filtering of WordPress core XML sitemaps
- safe mode when Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, or Slim SEO is active
- bounded migration tools and a versioned, read-only extension API

Five optional modules are lazy-loaded only in their declared request contexts:

- Redirects: manual redirects for single addresses or whole sections, "410 Gone" answers, CSV import, a test box, and optional published-slug redirects
- Hreflang: validated manual mappings and adapter hooks
- Tracking: GTM-first output with direct GA4 or Meta Pixel alternatives
- Local SEO: validated LocalBusiness schema fields
- AI Discovery (experimental): crawler policies, a curated `/llms.txt`, and local readiness checks

AI Discovery does not guarantee crawling, training, citation, inclusion, visibility, or ranking.

Settings can be exported to one file and imported on another site from **SEO → Tools**, with an undo for the last import. The same, module switches, and redirect tools are available as `wp lightweight-seo` commands.

## Deliberate boundaries

Core makes no remote requests, schedules no recurring jobs, creates no custom tables, and performs no site-wide frontend scans. Search Console/GA4 reporting, URL Inspection, rank tracking, internal-link crawling, and historical analytics belong outside core.

Meta-keywords output, Product schema, specialized image/video/news sitemap providers, continuous 404 logging, Search Console synchronization, internal-link reports, and site-wide image audits were retired in 1.1. Stored legacy keyword values remain exportable during migration.

## Installation

1. Copy the `lightweight-seo` directory to `wp-content/plugins/` or install the release ZIP.
2. Activate Lightweight SEO in WordPress.
3. Open **SEO** in the administrator and review the setup overview.
4. Enable only the optional modules the site needs.

For a 1.0.3 upgrade or release-candidate test, follow [Migration and RC Testing](docs/migration.md) and take a database backup first.

## Editing content

The SEO panel on supported posts and pages provides:

- an optional SEO title and meta description, with `%title%`, `%sitename%`, `%tagline%`, `%sep%`, `%category%`, `%author%`, `%year%`, and `%page%` resolved on the page
- canonical and robots controls, including a choice to show or hide the page whatever its content type is set to
- social title, description, and image overrides
- deterministic checks explaining missing or conflicting inputs

When no description is written, a 160-character summary of the excerpt or content is used. Legacy focus-keyword values are preserved for export but are not edited or emitted.

Redirect rule paths are matched against the full request path, so a subdirectory install writes rules as `/blog/old-path /blog/new-path`. A source ending in `/*` covers everything under it, a target ending in `/*` keeps the rest of the address, and `/old-path 410` answers that the page is gone.

The Tools tab can inspect any address on the site: it shows what is sent for that page and where each value comes from.

The Posts, Pages, and custom post type lists show a compact SEO column, and their Quick Edit and Bulk Edit can change the SEO title, the description, and whether the item is shown in search results.

## Development

Install the development dependencies and run the local gates:

```bash
composer install
composer validate --strict
composer audit
composer test
composer phpcs
node --test tests/js/*.test.js
```

`composer phpcs` enforces WordPress core style plus output escaping, nonce, input-sanitization, and text-domain checks on shipped code. Real WordPress tests use `tests/bin/install-wp-tests.sh` and `composer test:integration`. CI covers the compatibility matrix documented in [QA Checklist](docs/qa-checklist.md).

The public extension contract and stable hooks are documented in [Developer API](docs/developer-api.md).

## Release

The plugin is distributed through GitHub releases. Its `Update URI` header stops WordPress.org from offering a same-named directory plugin as an update; remove or change that header if the plugin is ever published to the directory.

Tags must exactly match the `Version:` header, for example `v1.1.0-rc.2`. The release workflow creates and verifies the ZIP, checks required runtime files, and smoke-activates the packaged plugin in WordPress. A tag with a hyphen, such as a release candidate, is published as a prerelease and does not replace the latest stable release.

See [Release Notes](docs/release-notes-1.1.0.md) and [Release Baselines](docs/release-baselines.md) for the current candidate.

## License

MIT. See [LICENSE](LICENSE).
