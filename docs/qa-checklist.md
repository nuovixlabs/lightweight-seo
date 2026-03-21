# QA Checklist

Use this checklist before releasing or deploying a build of Lightweight SEO.

## Core Frontend Checks

- Confirm homepage title, description, canonical, OG, Twitter, and schema output.
- Confirm a standard post outputs the expected title, meta tags, breadcrumbs, and Article schema.
- Confirm a product page outputs Product schema and does not also output Article schema.
- Confirm author archives output ProfilePage schema.
- Confirm term and author overrides are reflected in frontend output.

## Indexation and Headers

- Confirm search results pages are `noindex` when the setting is enabled.
- Confirm attachment pages are `noindex` when the setting is enabled.
- Confirm direct PDF or media requests return the expected `X-Robots-Tag` header.
- Confirm per-object canonical overrides work.

## Sitemap Checks

- Open `/wp-sitemap.xml` and verify it loads.
- Open the image sitemap and confirm image attachment URLs appear.
- Open the video sitemap and confirm video attachment URLs appear.
- Open the news sitemap and confirm recent posts appear when enabled.
- Confirm noindexed content is excluded.
- Confirm redirected content is excluded from post sitemaps.

## Redirect and 404 Checks

- Change a published slug and confirm a generated 301 is created.
- Add a manual redirect and confirm manual redirects still take precedence.
- Create a chain and a loop in staging and confirm the redirect health report flags both.
- Hit a missing URL and confirm it appears in recent 404 logs.

## Search Console Checks

- Save a valid property and service-account JSON.
- Confirm the service-account email has access in Search Console.
- Trigger a sync and confirm totals, low-CTR pages, and sitemap status populate.
- Confirm submitted sitemap entries appear in the Search Console section.
- Confirm canonical mismatch and indexation issue tables populate when the API returns issues.

## Internal Linking Checks

- Confirm orphan pages appear in the report.
- Confirm broken internal links are detected.
- Confirm generic anchors like `read more` or `click here` are flagged.
- Confirm suggested internal links include source pages and recommended anchor text.
- Confirm topic clusters show a hub page and supporting pages.

## Image and Discover Checks

- Confirm missing featured images appear in the audit.
- Confirm missing featured-image alt text appears in the audit.
- Confirm undersized featured images appear when below the configured thresholds.

## Migration and Safe Mode Checks

- Run an import from a supported source in staging and confirm key fields are copied.
- Activate Yoast, Rank Math, or AIOSEO and confirm safe mode disables duplicate title/meta/schema output.
- Confirm redirects, sitemaps, Search Console, and audits remain usable in safe mode.

## Performance Checks

- Open internal-link and Search Console reports twice and confirm repeated loads are fast.
- In multisite, confirm one site does not leak reports or settings into another site.
