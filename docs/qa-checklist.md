# QA Checklist

Use this checklist for the 1.1 release candidate. Test destructive migration and uninstall cases only on a disposable or backed-up site.

## Compatibility matrix

- WordPress 6.0 / PHP 7.4, single site
- latest supported WordPress / PHP 7.4
- latest supported WordPress / PHP 8.2
- latest supported WordPress / PHP 8.3 or the current project maximum
- latest supported WordPress multisite
- WooCommerce compatibility job
- Yoast SEO, Rank Math, and All in One SEO safe-mode smoke tests
- one multilingual adapter smoke test

## Installation and lifecycle

- Activate a clean install and confirm all five optional modules default off.
- Activate, deactivate, and reactivate repeatedly; confirm no warnings or duplicate hooks.
- On multisite, test site activation, network activation, and deactivation.
- Confirm deactivation clears plugin-owned scheduled events without deleting persistent SEO data.
- Test uninstall with data preservation off and on, including post, term, and user metadata on multisite.

## Core frontend output

- Confirm one document title, one canonical, and one coherent robots result.
- Confirm homepage, post, page, term, author, search, attachment, password-protected, and 404 behavior.
- Confirm Open Graph and X/Twitter fallbacks and social image validation.
- Confirm schema JSON parses and uses Organization, WebSite, BreadcrumbList, ProfilePage, and scoped Article nodes only.
- Confirm a WooCommerce Product page receives no Product graph from Lightweight SEO.
- Confirm an ordinary frontend request makes no remote request, recurring schedule, custom table, or plugin write.

## Sitemaps and headers

- Open `/wp-sitemap.xml` and confirm valid XML.
- Confirm noindexed and redirected content is excluded when configured.
- Confirm no image, video, or news sitemap provider is registered.
- Confirm attachment responses receive configured WordPress-controlled X-Robots headers.
- Confirm static media served directly by the web server is not claimed as plugin-controlled.

## Modules

- Toggle every module repeatedly and confirm disabled implementation classes do not load.
- Redirects: test exact rules, malformed destinations, external-host rejection, loops, chains, automatic slug redirects, export, and the documented high-rule boundary.
- Hreflang: test reciprocal mappings, `x-default`, invalid locale/URL rejection, path mirroring, and WPML or Polylang ownership.
- Tracking: test GTM precedence, direct GA4/Meta alternatives, consent filters, role/environment exclusions, CSP nonce, and a missing `wp_body_open` diagnostic.
- Local SEO: test required fields, supported subtype, address, coordinates, hours, image, and disabled behavior.
- AI Discovery: test physical/virtual `robots.txt`, model-training default off, curated `/llms.txt`, conflict behavior, ETag `304`, privacy exclusions, invalid IDs, and readiness messages.

## Editor and accessibility

- Use the setup overview and each settings tab with keyboard only.
- Confirm visible focus, logical tab order, associated labels, and understandable validation errors.
- Check desktop and narrow/mobile layouts.
- Confirm the editor panel explains title, description, canonical, robots, and social-image issues without speculative scores.
- Confirm no control promises ranking, crawling, citation, training, or inclusion.

## Migration

- Upgrade a representative 1.0.3 fixture and confirm supported settings plus post, term, user, canonical, robots, and social metadata survive.
- Confirm retired reports, tokens, and Search Console cron events are removed.
- Confirm the one-time upgrade summary lists retired behavior and specialized sitemap endpoints.
- Confirm Search Console credentials require explicit copy/delete and are never reused automatically.
- Export retained keyword values and confirm they are not emitted on the frontend.
- Preview a supported-source import, confirm it is read-only, then import in batches of at most 50.
- Confirm occupied Lightweight SEO fields are never overwritten and roll back the latest batch.

## Security and privacy

- Confirm every mutation requires both the documented capability and a nonce.
- Confirm object saves use object-specific edit capabilities.
- Test stored and reflected output escaping with HTML and CSV-formula payloads.
- Test redirect scheme, host, chain, loop, and malformed-rule validation.
- Confirm credentials/tokens never appear in public APIs, frontend markup, diagnostics, JavaScript data, or logs.
- Confirm `/llms.txt` includes only selected public, indexable, local, canonical, non-redirected posts/pages and never full content.
- Confirm core makes no external request and stores no new 404 or analytics history.

## Performance and packaging

- Compare 100 warm frontend requests with the plugin active and inactive; median regression must not exceed 5% in the reference environment.
- Record warm peak-memory delta, query delta, option sizes, runtime file count, and ZIP size in `docs/release-baselines.md`.
- Confirm disabled-module and all-module states remain within the recorded baseline.
- Build the release ZIP, inspect its manifest, run `unzip -t`, and smoke-activate that exact package in WordPress.
- Confirm tests, development configuration, and CI files are excluded from the ZIP.

## Final gates

- `composer validate --strict`
- `composer audit`
- `composer test`
- `composer phpcs`
- PHP syntax checks
- real WordPress single-site and multisite integration suites
- translation template generation without warnings
- whitespace check
