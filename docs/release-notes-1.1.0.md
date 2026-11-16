# Lightweight SEO 1.1 Release Notes

Release candidate: `1.1.0-rc.2`

`1.1.0-rc.1` was never published. This is the first 1.1 candidate available as a release package.

## Highlights

- Correct WordPress-native canonical and robots output with hardened 404, privacy, social-image, and schema behavior.
- A small request-aware module registry with Redirects, Hreflang, Tracking, Local SEO, and experimental AI Discovery modules.
- A clearer setup overview, grouped settings, editor guidance, module status, and keyboard-friendly administration.
- A versioned read-only extension API with a tested companion fixture.
- Real WordPress single-site and multisite integration coverage plus a WordPress/PHP CI matrix.

## Hardening since rc.1

Correctness:

- Descriptions derived from an excerpt, post content, term description, or author biography are shortened to a 160-character summary. Editor-written descriptions are never shortened.
- A custom canonical applies only to the page it was set on. Canonicals that WordPress resolves for other posts, archives, or admin screens are no longer rewritten.
- Subdirectory installs produce correct canonical, `og:url`, and local redirect targets instead of repeating the install path.
- Page two and later of term and author archives keep a self-referencing canonical instead of pointing at page one.
- A static Posts page uses its own title and SEO fields rather than the homepage title and description.
- Automatic slug redirects are recorded for block-editor saves and Quick Edit, not only the classic editor.
- Imported titles and descriptions have their source template variables converted or are left for review; nothing is imported as literal `%%variable%%` text.
- `%title%`, `%sitename%`, `%tagline%`, and `%sep%` are resolved in per-object SEO and social titles and descriptions.
- Article `headline` and the final breadcrumb name use the visible title instead of the templated title tag.
- `og:locale` and, for article content, `article:published_time` and `article:modified_time` are emitted.
- Password-protected posts are left out of sitemaps together with noindexed content.
- The public API reports unpublished and password-protected posts as not indexable and no longer returns a full post body as a description.

Security and robustness:

- JSON-LD is encoded so that no graph value can close its script element.
- Term SEO fields require the `edit_term` capability in addition to the nonce.
- Retired focus-keyword values are no longer exposed through the REST API.
- The safe-mode notice is shown to administrators on the dashboard, Plugins, and SEO screens only.
- The settings screen, editor panel, and importer load only in the administration area.
- Import preview, import, rollback, and legacy 404-log deletion are separate administrator actions, each with its own nonce. Saving settings never runs an import or deletes data.
- `Update URI` prevents an unrelated WordPress.org plugin with the same slug from being offered as an update.
- The coding-standard gate now enforces output escaping, nonce verification, input sanitization, and the text domain.
- SEOPress, The SEO Framework, and Slim SEO also trigger safe mode.

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
- Safe mode for Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, and Slim SEO
- Root and subdirectory installs
- WooCommerce Product-schema ownership

## Developer notes

The public API version is `1.0`. Extensions must wait for `lightweight_seo_loaded`, perform the documented version handshake, and use only the facade and documented hooks. See [Developer API](developer-api.md).
