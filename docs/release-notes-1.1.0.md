# Lightweight SEO 1.1 Release Notes

Release candidate: `1.1.0-rc.3`

`1.1.0-rc.1` was never published. `1.1.0-rc.2` was the first 1.1 candidate available as a release package. This candidate adds the redesigned administration interface.

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

## Administration interface since rc.2

The settings screen and the editor panel are rebuilt on one small design system. No setting, option key, or saved value changes. See [Design System](design-system.md).

- Settings are grouped into cards under a simpler header and tab bar, and the Save button stays in reach on long screens.
- Each module is a collapsible card with an Active or Off badge. A card starts open only when its module is in use.
- The overview gains an "At a glance" card with search visibility, SEO output, the sitemap link, and module status. Optional setup steps no longer count against setup progress.
- Saving settings is confirmed with "Settings saved." The settings screen showed no confirmation before.
- Local business details are labelled inputs instead of placeholder-only inputs.
- AI readiness, redirect health, legacy data, and editor checks use one status-list style.
- The editor panel adds length meters, click-to-insert title variables, and a count of checks that need attention.
- Every control has a label or belongs to a named group, and titles and labels use sentence case.
- The accent color follows the administration color scheme chosen in the user's profile.

Administration CSS class names changed from `lightweight-seo-*` to `lwseo-*`. Custom administration styles that target the old names need updating.

## Administration interface since rc.3

No setting, option key, or saved format changes.

- The editor panel follows the page as it is edited. The previews, length meters, and checks update with the post title, excerpt, content, and featured image, and the check count becomes a tick when nothing is left to review.
- The overview opens with the homepage as it appears in search results and when shared.
- The setup checklist counts a step only when it is done. It is dismissed per user instead of per browser, and can be shown again.
- A settings tab warns before it is left with unsaved changes.
- Tracking exclusions are tick boxes for roles and environments, and the business image uses the media picker.
- Redirect rules and hreflang mappings are edited as rows, opening hours as one row per day, and the `llms.txt` pages are chosen by searching. Each is still stored in the same text format, and "Edit as text" shows it for pasting many lines at once.
- Saving the Modules tab says how many redirect rules or translations were left out, instead of dropping them silently.
- A module that is off shows only the controls that turn it on. The Legacy data card and the legacy hreflang path-mirroring option appear only on sites that still hold that data.
- The Developer API reference moved onto the Tools tab. Links to the old tab still work.
- Labels describe what a setting does before naming the directive, and use sentence case throughout.

Fixed: the editor filled the social image field with the featured image or the site default, so saving a post stored that image as the post's own. A later change of featured image or site default then left `og:image` on the old image. The field now holds only an image chosen for that post. Posts saved before this fix keep the image that was stored; clear their social image field to follow the featured image again.

## Content types since rc.3

The "Content and indexation" tab opens with one table for every public post type and taxonomy, plus author archives, date archives, and internal search pages.

- Each post type and taxonomy can be shown in or hidden from search results, listed in or left out of the sitemap, and given its own title format. An empty title format keeps the site-wide format.
- Author archives and date archives can be hidden from search results.
- A single page, term, or author can still decide for itself, in both directions. The "Prevent search engines from indexing this page" tick box in the editor, and the "Noindex" tick box on term and author screens, became a "Search results" choice: follow the content type, show, or hide.
- Hiding a post type also hides its archive page. A hidden content type lists only the items set to show in the sitemap, while "Leave hidden pages out of the sitemap" is on.
- The editor's Indexing check says when a page is hidden only because its content type is hidden, and how to make an exception.
- A homepage that is a page follows the Pages row like any other page. The table warns when Pages are hidden on such a site.
- The two tick boxes for internal search pages and attachment pages moved into the table as its "Internal search pages" and "Media" rows.

Nothing changes until a row is changed: every default matches the previous behavior, and no stored value is rewritten. Three settings are new in the existing settings option: `content_types`, `noindex_author_archives`, and `noindex_date_archives`. The per-item noindex value keeps `1` for hide, treats `0` or nothing as the content type default, and stores `index` for show. Earlier versions read `index` as not hidden.

One existing rule changes once the new choice is used: an attachment page set to "Show" is indexable even while attachment pages are hidden as a type. Before, the attachment setting always won.

For extensions, `is_indexable()` and the `indexable` and `robots` values of `get_object_context()` now include the content type default.

## Site identity since rc.3

"Schema and identity" opens with a "Site identity" card, and "Search appearance" gains a title separator.

- The site can represent an organization or a person. A person is printed as a `Person` node with the identifier `#person`, and becomes the publisher of the WebSite and Article nodes.
- The organization or person has its own name, which defaults to the site title, and its own logo or photo.
- An organization without a logo keeps using the default social image, as before. A person never borrows it: a photo is printed only when one is chosen.
- An X handle is printed as `twitter:site`. It can be typed as a handle or as a profile address.
- The title separator sets what `%sep%` becomes: an en dash by default, or one of seven other characters.
- "Organization profiles" is now "Profiles". Its stored key and format are unchanged.

Nothing changes until a field is changed. Six settings are new in the existing settings option: `identity_type`, `identity_name`, `identity_logo`, `identity_logo_id`, `x_handle`, and `title_separator`. There is no migration.

For extensions, the page context gains `twitter_site`, and the first node of the schema graph may be a `Person` with the identifier `#person` instead of an `Organization` with `#organization`.

## Verification codes and title variables since rc.3

- "Schema and identity" gains a "Site verification" card for Google Search Console, Bing Webmaster Tools, Pinterest, and Yandex Webmaster. Each code is printed as a meta tag on the homepage only. A field accepts the code or the whole meta tag the service shows. Nothing is requested from any service.
- Titles and descriptions understand four more variables: `%category%` (a post's first category, or the category of a category archive), `%author%` (a post's author, or the author of an author archive), `%year%` (the current year), and `%page%` ("Page 2" and so on, from the second page of an archive or of a post split into pages).
- One of these variables that has no value on a page is left out together with the separator beside it, so `%title% %sep% %page% %sep% %sitename%` has no gap on the first page. The four earlier variables behave exactly as before, including an empty tagline.
- "Search appearance" explains every variable under "What the variables mean", and the variable buttons in the settings and in the editor offer the new ones.
- Import converts more source variables instead of leaving them for review: the current year and the author name from Yoast SEO, Rank Math, and All in One SEO; `%category%` from Rank Math; and the page variable of Yoast SEO and Rank Math, which was dropped before. Theirs reads "Page 2 of 5"; `%page%` reads "Page 2".

Four settings are new in the existing settings option: `verification_google`, `verification_bing`, `verification_pinterest`, and `verification_yandex`. There is no migration.

Titles, descriptions, and title formats are now sanitized in a way that keeps variables whole. The WordPress text sanitizer removes anything shaped like a percent-encoded byte, which would have cut `%ca` out of `%category%`.

## Post list since rc.3

- The Posts, Pages, and custom post type lists gain a compact "SEO" column: whether the title and the description are the item's own, and "Hidden from search" when that applies. It reads only what WordPress has already loaded for the rows, adds no database query, and can be switched off in Screen Options.
- Quick Edit gains an SEO title, a description, and the search visibility choice, filled with the saved values.
- Bulk Edit gains the search visibility choice, with "— No change —" first. Titles and descriptions are not offered in bulk, because giving many pages the same one creates duplicates.
- The list saves only the fields it shows, only when they were filled from the row, and only with its own nonce and the right to edit that item. Hiding the SEO column also hides its Quick Edit fields.
- A page hidden from the list for the first time prints `noindex` only. A page hidden before 1.1 and not saved since keeps the `nofollow` it inherited.

The Media Library and taxonomy term lists are unchanged. One administration-only file is new; see [Release Baselines](release-baselines.md).

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
