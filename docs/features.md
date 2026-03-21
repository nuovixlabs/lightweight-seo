# Lightweight SEO Feature Guide

This document describes the feature set currently implemented in Lightweight SEO after the SEO expansion work. It is written as a product and engineering reference, not as a changelog.

## Product Position

Lightweight SEO now covers the core layers that usually require multiple plugins or a much heavier SEO suite:

- on-page SEO controls
- crawl and indexation controls
- XML sitemaps
- schema output
- redirects and 404 recovery
- Search Console insights
- internal-link analysis
- image and Discover audits
- migration tooling
- safe-mode compatibility with larger SEO plugins

The plugin still aims to stay operationally simple. Most features are either automatic or driven by a small number of global settings, with per-object overrides where they matter.

## Feature Areas

### 1. Foundation and Safe Mode

What it is:
- A shared SEO context powers titles, meta tags, schema, and frontend decisions.
- Safe mode prevents duplicate SEO output when a major SEO plugin is already active.

How it works:
- Frontend rendering is centralized through shared services instead of separate ad hoc code paths.
- When Yoast SEO, Rank Math, or All in One SEO is detected, Lightweight SEO suppresses its own title/meta/schema head output.
- Redirects, sitemaps, Search Console reporting, and content audits remain available in safe mode.

Why it matters:
- Prevents duplicate canonical tags, duplicate schema, and conflicting meta output.
- Makes the plugin safer to install on production sites that already have SEO tooling.

### 2. On-Page SEO and Indexation Controls

What it is:
- Global templates plus per-post, per-term, and per-author overrides for core SEO fields.
- Granular robots controls beyond simple noindex.

What is implemented:
- title templates for default, home, archive, and search pages
- per-object custom title, description, keywords, canonical URL
- `noindex`, `nofollow`, `noarchive`, `nosnippet`
- `max-image-preview`
- default noindex for search results
- default noindex for attachment pages
- `X-Robots-Tag` headers for attachments and direct media/document requests

How it works:
- The page context service resolves the current request into a normalized SEO payload.
- Frontend output uses that payload for meta tags and related logic.
- Attachment and media requests can be controlled with headers even when HTML meta tags are not available.

Why it matters:
- Keeps thin or low-value URLs out of the index.
- Reduces crawl waste.
- Gives stronger control over preview behavior in search results.

### 3. XML Sitemap System

What it is:
- An extension layer on top of WordPress core sitemaps, not a parallel sitemap implementation.

What is implemented:
- exclusion of noindexed posts, terms, and authors
- exclusion of redirected posts from post sitemaps
- dedicated image attachment sitemap
- dedicated video attachment sitemap
- recent-post news sitemap
- optional submission of enabled sitemap endpoints during Search Console sync

Key endpoints:
- `/wp-sitemap.xml`
- `/wp-sitemap-lightweightseoimages-1.xml`
- `/wp-sitemap-lightweightseovideos-1.xml`
- `/wp-sitemap-lightweightseonews-1.xml`

How it works:
- WordPress core continues to own the sitemap index.
- Lightweight SEO filters sitemap queries and registers custom sitemap providers for additional modules.
- Search Console sync can submit the core sitemap index and enabled module sitemaps before reading sitemap status.

Why it matters:
- Keeps sitemap coverage aligned with indexable URLs.
- Gives better discovery for image, video, and fresh content workflows.

### 4. Structured Data

What it is:
- JSON-LD schema output driven by the shared page context and global settings.

Core schema implemented:
- `Organization`
- `WebSite`
- `BreadcrumbList`
- `Article`
- `ProfilePage`

Extended schema implemented:
- `LocalBusiness`
- `Product`

How it works:
- Homepage schema can include organization identity plus an optional LocalBusiness node.
- Single posts output Article schema.
- Product pages output Product schema with offer and stock data when the current post type is `product`.
- Author archives output ProfilePage schema.

Why it matters:
- Improves search engine understanding of page types and entity identity.
- Supports richer eligibility for product and local-business search features.

### 5. International SEO

What it is:
- Global hreflang mapping for mirrored domain or locale structures.

What is implemented:
- self-referencing hreflang using the site locale
- alternate hreflang links from configured mappings
- `x-default` support
- optional path reuse so alternate domains can mirror the current page structure

How it works:
- Admin mappings are entered in the format `language-code https://domain.example`.
- For root domain mappings, the current canonical path is appended automatically.
- For advanced cases, `%path%` can be used in a mapping URL template.

Why it matters:
- Helps search engines send the right language or market URL to the right audience.
- Reduces cross-market cannibalization when the site structure is mirrored.

### 6. Redirects and 404 Recovery

What it is:
- A lightweight redirect manager plus broken URL visibility.

What is implemented:
- manual redirect rules
- automatic 301 generation on slug changes
- 404 logging
- redirect export
- redirect chain detection
- redirect loop detection

How it works:
- Manual rules are stored in plugin settings.
- Generated redirects are stored separately so manual rules can take precedence.
- Health reports analyze the full redirect graph and call out chains or loops.

Why it matters:
- Preserves link equity after URL changes.
- Exposes broken inbound paths before they become a ranking or UX problem.

### 7. Search Console Insights

What it is:
- A service-account-based Search Console integration with cached reporting.

What is implemented:
- Search Analytics snapshot
- low-CTR page reporting
- declining-page reporting
- sitemap status reporting
- URL Inspection checks for key pages
- indexation issue surfacing
- canonical mismatch reporting
- scheduled daily sync
- optional sitemap submission during sync

How it works:
- Admin stores a property identifier and service-account JSON.
- The plugin requests Search Analytics and sitemap data.
- High-value pages from the snapshot are inspected through the URL Inspection API.
- Results are cached and rendered as actionable admin tables.

Why it matters:
- Turns SEO work from guesswork into issue-driven prioritization.
- Connects crawl/indexation settings with live search performance.

### 8. Internal Linking and Content Architecture

What it is:
- A site-level internal-link audit focused on discoverability and information architecture.

What is implemented:
- orphan page detection
- weak-page detection
- broken internal-link detection
- generic anchor-text detection
- recommended replacement anchors
- semantic internal-link source suggestions
- topic-cluster and hub-page reporting

How it works:
- Published indexable content is scanned and internal links are normalized into a local graph.
- Page titles, slugs, content tokens, and phrase overlap are used to suggest likely source pages for weak targets.
- Repeated topic terms are grouped into clusters to surface likely hubs and supporting pages.

Why it matters:
- Improves crawl depth and internal authority flow.
- Makes content architecture problems visible without a separate crawler.

### 9. Image SEO and Discover Readiness

What it is:
- Audit tooling for content that depends on strong featured images.

What is implemented:
- missing featured image report
- missing alt text report for featured images
- undersized featured image report against configurable minimum dimensions

How it works:
- Published indexable posts are scanned for thumbnails.
- Thumbnail metadata is inspected for width, height, and alt text.
- Admin tables highlight pages that need stronger image coverage.

Why it matters:
- Improves visual readiness for social sharing, schema, and Discover-style surfaces.
- Gives editors a direct checklist instead of relying on manual review.

### 10. Import and Migration

What it is:
- A metadata importer for moving off common SEO plugins.

Supported sources:
- Yoast SEO
- Rank Math
- All in One SEO

What is imported:
- title
- description
- canonical URL
- basic robots flags
- social title and description
- selected keyword fields when available

How it works:
- Choose a source in admin.
- Save settings with the import checkbox enabled.
- Lightweight SEO scans supported post types and copies supported source fields into its own meta model.
- The last import result is stored in settings for visibility.

Why it matters:
- Reduces migration friction and lowers the cost of adopting the plugin.

### 11. Performance and Multisite-Safe Behavior

What it is:
- Report-level hardening so analysis features remain lightweight in production.

What is implemented:
- object-cache support for internal-link and Search Console snapshots
- site-scoped cache keys using the current blog ID
- image audit caching
- network-admin guard for the plugin settings menu

Why it matters:
- Keeps repeated admin report loads from recomputing everything on every request.
- Avoids cross-site cache collisions in multisite environments.

## Automation vs Manual Control

Automatic by default:
- sitemap generation through WordPress core
- noindex exclusion from sitemaps
- automatic slug redirects
- Search Console scheduled sync
- redirect health analysis
- internal-link report caching and refresh on content changes

Opt-in or configuration-driven:
- LocalBusiness schema
- hreflang output
- Search Console connection
- sitemap submission to Search Console
- news sitemap
- metadata importer

Per-object override driven:
- titles
- descriptions
- canonicals
- robots directives
- social metadata

## Admin Surface Summary

The settings page now covers these operating areas:

- Global SEO Settings
- Indexation Controls
- XML Sitemaps
- Structured Data
- Redirects and 404 Monitoring
- Internal Linking
- Image SEO and Discover
- Search Console
- Migration and Imports
- Tracking Codes

## Recommended Operator Workflow

1. Configure titles, descriptions, and default social image.
2. Keep search and attachment pages noindexed unless there is a specific business reason not to.
3. Enable Search Console and confirm sitemap submission.
4. Review redirects and 404 logs after migrations or URL changes.
5. Review orphan pages, weak pages, and topic clusters regularly.
6. Review image audits before pushing new content sections live.
7. Use safe mode when another SEO plugin is present during migrations.

## Release Notes Worth Calling Out

These additions materially changed the plugin scope:

- Lightweight SEO is no longer just a meta-tags plugin.
- The plugin now includes technical SEO, reporting, migration, and recovery workflows.
- Search Console and content-architecture tooling are now first-class features.
- The plugin can coexist more safely with other SEO plugins through safe mode.
