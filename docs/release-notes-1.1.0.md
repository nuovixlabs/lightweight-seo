# Lightweight SEO 1.1 Release Notes

Release candidate: `1.1.0-rc.1`

## Highlights

- Correct WordPress-native canonical and robots output with hardened 404, privacy, social-image, and schema behavior.
- A small request-aware module registry with Redirects, Hreflang, Tracking, Local SEO, and experimental AI Discovery modules.
- A clearer setup overview, grouped settings, editor guidance, module status, and keyboard-friendly administration.
- A versioned read-only extension API with a tested companion fixture.
- Real WordPress single-site and multisite integration coverage plus a WordPress/PHP CI matrix.

## Experimental AI Discovery

AI Discovery can manage documented crawler policies and publish a curated `/llms.txt` containing selected public summaries. Model-training access defaults off. The feature makes no crawling, training, citation, inclusion, visibility, or ranking guarantee.

## Upgrade and removal notes

- Meta-keywords output and editing are removed; stored values remain exportable.
- Image, video, and news sitemap providers are removed. Remove their old URLs from webmaster tools.
- Generic Product schema is removed so WooCommerce or another authoritative owner can provide it.
- Search Console synchronization, credentials use, report caches, and scheduled jobs are removed from core. Administrators receive an explicit copy/delete transition for retained configuration.
- Site-wide internal-link and image-audit reports plus continuous 404 logging are removed from core.
- Imports now provide preview, batches of at most 50 posts, fill-empty-only behavior, and latest-batch rollback.

See [Migration and RC Testing](migration.md) before upgrading a production site.

## Compatibility

- WordPress 6.0 or newer
- PHP 7.4 or newer
- Multisite lifecycle and uninstall paths
- Safe mode for Yoast SEO, Rank Math, and All in One SEO
- WooCommerce Product-schema ownership

## Developer notes

The public API version is `1.0`. Extensions must wait for `lightweight_seo_loaded`, perform the documented version handshake, and use only the facade and documented hooks. See [Developer API](developer-api.md).
