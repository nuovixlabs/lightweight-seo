# Lightweight SEO

Lightweight SEO provides correct, predictable on-page SEO without a crawler, analytics dashboard, or background synchronization layer. It supports WordPress 6.0+ and PHP 7.4+.

## What it does

Core provides:

- document titles and templates
- meta descriptions, canonical URLs, and WordPress-native robots directives
- Open Graph and X/Twitter metadata
- post, term, and author SEO controls
- search visibility, sitemap listing, and a title format for each post type and taxonomy
- Organization or Person, WebSite, a page node for every page, BreadcrumbList, and Article, Blog post, or News article schema chosen per post type
- a site identity with its own name, description, logo or photo, X handle, and profile links, plus a choice of title separator
- verification meta tags for Google Search Console, Bing Webmaster Tools, Pinterest, and Yandex Webmaster
- filtering of WordPress core XML sitemaps
- safe mode when Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, or Slim SEO is active
- bounded migration tools and a versioned, read-only extension API
- abilities for the WordPress Abilities API, so agents can read and fill the SEO fields of a post, term, or author with the editing rights of the signed-in user

Six optional modules are lazy-loaded only in their declared request contexts:

- Redirects: manual redirects for single addresses or whole sections, "410 Gone" answers, CSV import, a test box, and optional published-slug redirects
- Hreflang: validated manual mappings and adapter hooks
- Tracking: GTM-first output with direct GA4 or Meta Pixel alternatives
- Local SEO: validated LocalBusiness schema fields
- IndexNow: tells participating search engines when a page is published, changed, or removed. This is the one module that sends a request to another service (`api.indexnow.org`), and only from a public production site
- AI Discovery (experimental): a `Disallow` group for each AI crawler switched off, while allowed ones follow the site's rules for every crawler, a curated `/llms.txt`, an optional `Content-Signal` line, an optional Markdown version of posts and pages for clients that ask for `text/markdown`, and an AI readiness report grouped by area, whose checks of served addresses such as `/robots.txt` run in the administrator's browser

AI Discovery does not guarantee crawling, training, citation, inclusion, visibility, or ranking. Markdown on request converts only a page's content: what a theme or another plugin prints around it, such as a price, a form, or a sidebar, is not included.

Settings can be exported to one file and imported on another site from **SEO → Tools**, with an undo for the last import. The same, module switches, and redirect tools are available as `wp lightweight-seo` commands.

## Deliberate boundaries

Core makes no remote requests, schedules no recurring jobs, creates no custom tables, and performs no site-wide frontend scans. The optional IndexNow module is the one exception to the first: while it is switched on, it sends the addresses of changed pages to `api.indexnow.org`. Search Console/GA4 reporting, URL Inspection, rank tracking, internal-link crawling, and historical analytics belong outside core.

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
- deterministic checks explaining missing or conflicting inputs, including which description is printed, images in the content without alternative text, and more than one H1 heading

When no description is written, a 160-character summary of the excerpt or content is used, with a space between its paragraphs, headings, list items, and pages. Legacy focus-keyword values are preserved for export but are not edited or emitted.

Redirect rule paths are matched against the full request path, so a subdirectory install writes rules as `/blog/old-path /blog/new-path`. A source ending in `/*` covers everything under it, a target ending in `/*` keeps the rest of the address, and `/old-path 410` answers that the page is gone.

The Tools tab can inspect any address on the site: it shows what is sent for that page and where each value comes from, and counts the main content's text, the H1 headings, and the images without alternative text on the page as a visitor receives it.

The Posts, Pages, and custom post type lists show a compact SEO column, and their Quick Edit and Bulk Edit can change the SEO title, the description, and whether the item is shown in search results.

## Development

Install the development dependencies and run the local gates:

```bash
composer install
composer check
composer audit
```

`composer check` runs Composer validation, a PHP syntax check, PHPCS, the unit tests, the JavaScript tests, a translation template freshness check, and a whitespace check. It needs PHP, Node, and WP-CLI. PHPCS enforces WordPress core style, PHP 7.4 compatibility, and output escaping, nonce, input-sanitization, and text-domain checks on shipped code. Real WordPress tests use `tests/bin/install-wp-tests.sh` and `composer test:integration`. CI covers the compatibility matrix documented in [QA Checklist](docs/qa-checklist.md).

`bin/dev-site` builds a throwaway WordPress with the plugin active for testing by hand.

Start with [Architecture](docs/architecture.md) for the rules every change keeps and [Recipes](docs/recipes.md) for step-by-step procedures. The public extension contract and stable hooks are documented in [Developer API](docs/developer-api.md). Coding agents read [AGENTS.md](AGENTS.md).

## Release

The plugin is distributed through GitHub releases. Its `Update URI` header stops WordPress.org from offering a same-named directory plugin as an update; remove or change that header if the plugin is ever published to the directory.

Tags must exactly match the `Version:` header, for example `v1.1.0-rc.2`. `bin/release-prep <version>` prepares a release; see [Cut a release](docs/recipes.md#cut-a-release). The release workflow runs the quality checks, builds the ZIP with `bin/build-zip`, which fails when a plugin file is missing or a development file is included, and smoke-activates the packaged plugin in WordPress. A tag with a hyphen, such as a release candidate, is published as a prerelease and does not replace the latest stable release.

See the [Changelog](CHANGELOG.md), the release notes for [1.3](docs/release-notes-1.3.0.md), [1.2](docs/release-notes-1.2.0.md), and [1.1](docs/release-notes-1.1.0.md), and [Release Baselines](docs/release-baselines.md).

## License

MIT. See [LICENSE](LICENSE).
