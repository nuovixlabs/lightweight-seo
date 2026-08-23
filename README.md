# Lightweight SEO

Lightweight SEO provides correct, predictable on-page SEO without a crawler, analytics dashboard, or background synchronization layer. It supports WordPress 6.0+ and PHP 7.4+.

## What it does

Core provides:

- document titles and templates
- meta descriptions, canonical URLs, and WordPress-native robots directives
- Open Graph and X/Twitter metadata
- post, term, and author SEO controls
- Organization, WebSite, BreadcrumbList, ProfilePage, and scoped Article schema
- filtering of WordPress core XML sitemaps
- safe mode when Yoast SEO, Rank Math, or All in One SEO is active
- bounded migration tools and a versioned, read-only extension API

Five optional modules are lazy-loaded only in their declared request contexts:

- Redirects: exact manual redirects and optional published-slug redirects
- Hreflang: validated manual mappings and adapter hooks
- Tracking: GTM-first output with direct GA4 or Meta Pixel alternatives
- Local SEO: validated LocalBusiness schema fields
- AI Discovery (experimental): crawler policies, a curated `/llms.txt`, and local readiness checks

AI Discovery does not guarantee crawling, training, citation, inclusion, visibility, or ranking.

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

- an optional SEO title and meta description
- canonical and robots controls
- social title, description, and image overrides
- deterministic checks explaining missing or conflicting inputs

Legacy focus-keyword values are preserved for export but are not edited or emitted.

## Development

Install the development dependencies and run the local gates:

```bash
composer install
composer validate --strict
composer audit
composer test
composer phpcs
```

Real WordPress tests use `tests/bin/install-wp-tests.sh` and `composer test:integration`. CI covers the compatibility matrix documented in [QA Checklist](docs/qa-checklist.md).

The public extension contract and stable hooks are documented in [Developer API](docs/developer-api.md).

## Release

Tags must exactly match the `Version:` header, for example `v1.1.0-rc.1`. The release workflow creates and verifies the ZIP, checks required runtime files, and smoke-activates the packaged plugin in WordPress.

See [Release Notes](docs/release-notes-1.1.0.md) and [Release Baselines](docs/release-baselines.md) for the current candidate.

## License

MIT. See [LICENSE](LICENSE).
