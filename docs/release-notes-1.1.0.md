# Lightweight SEO 1.1 Release Notes

Release candidate: `1.1.0-rc.5`

`1.1.0-rc.1` was never published. `1.1.0-rc.2` was the first 1.1 candidate available as a release package, and `1.1.0-rc.3` added the redesigned administration interface. `1.1.0-rc.4` added everything in the sections marked "since rc.3" below, and this candidate adds the fix under "Since rc.4". Since rc.3: the content types table, site identity, more title variables, the post list column, Inspect a page, a switch for every module, redirect upgrades, settings export and import, deeper structured data, three more importers, abilities, the IndexNow module, and live previews.

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
- One of these variables that has no value on a page is left out together with the separator beside it, so `%title% %sep% %page% %sep% %sitename%` has no gap on the first page. `%title%`, `%sitename%`, and `%sep%` behave exactly as before; for `%tagline%` see "Since rc.4".
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

## Inspect a page since rc.3

The Tools tab opens with an "Inspect a page" card: enter any address on the site and see what Lightweight SEO sends for it, and where each value comes from.

- The report covers what kind of page WordPress resolved, whether search engines may show it and every reason that hides it, the title and description with their sources, the canonical address, the sharing tags, the sitemap, and the structured data.
- The page produces its own report, so the report is exactly what a visitor's browser receives. It works for posts, archives, search results, later pages, and missing addresses. An address that redirects is reported as a redirect.
- An "Inspect SEO" link in each post list row and "Inspect what is sent for this page" in the editor's Checks tab open the report for that item.
- PHP requests nothing: the administrator's browser asks the site. The inspect argument does nothing without the `manage_options` capability and a valid nonce, and a visitor request pays for one check.
- The report is read-only and holds no score. It is built on the public API's `get_current_context()` together with the tags and schema graph the page prints.

One file is new and is loaded only for an inspect request; see [Release Baselines](release-baselines.md).

## Module switches since rc.3

- Every module card on "Optional modules" now starts with the same "Enable module" checkbox. Redirects and Tracking had none: they were on whenever a rule or an ID was saved, so the only way to turn one off was to delete what was configured.
- A module that is switched off keeps its rules, IDs, and other settings, and loads no code.
- On upgrade each of the two new switches is set to what its module is doing at that moment, so nothing turns on or off by itself.

Two settings are new in the existing settings option: `enable_redirects` and `enable_tracking`. The settings schema version is now 4; migration step 4 writes the two switches from the stored module state and changes nothing else.

## Redirects since rc.3

- **Whole sections.** A rule whose old path ends in `/*` covers that path and everything under it. End the new path with `/*` as well and the rest of each address is kept: `/old-blog/* /blog/*` sends `/old-blog/2024/post` to `/blog/2024/post`. A rule for one exact path always wins, then the longest matching section.
- **410 Gone.** A rule can answer that a page was removed on purpose: `/old-path 410`, or the "410 Gone" type in the row editor. The visitor sees the theme's "not found" page with the status 410, WordPress does not guess a similar address, and the page leaves the sitemap like any redirected page.
- **Test an address.** The Redirects card shows which saved rule applies to a path, every hop it takes, and where it ends. It reads the saved rules only, requests nothing, and works without JavaScript.
- **CSV import.** Upload a file with one rule per row (old path, new path or address, status code). Rows go through the same checks as typed rules; a rule for a path that already has one replaces it. The notice counts what was added, replaced, already there, and left out. The file is read and not kept.
- Rules that would loop between sections, cover the whole site, or grow an address on every hop are refused when they are saved.
- The redirect health report follows sections too, and a redirect that leads to a gone address is listed with `410` as its end.

Stored rules keep their format, so nothing is migrated. One file is new, `includes/class-lightweight-seo-redirect-import.php`, loaded only for an import; see [Release Baselines](release-baselines.md).

## Settings export, import, and WP-CLI since rc.3

- The Tools tab gains "Export and import settings". The download is one JSON file with the settings of every tab and which modules are on. It holds no titles or descriptions of pages, no credentials, and nothing kept from retired features.
- An import replaces every setting, only after its confirmation box is ticked. Each value goes through the same checks as on the settings tabs; a value the file does not hold, or one that fails a check, is set to its default and reported. A file from another site keeps image addresses and leaves out image IDs, the `llms.txt` page list, and translations tied to a page.
- The settings in place before an import are kept, and "Undo last import" puts them back exactly as they were stored.
- WP-CLI: `wp lightweight-seo settings export|import|undo-import`, `wp lightweight-seo module list|enable|disable`, and `wp lightweight-seo redirects import|test`. See [Developer API](developer-api.md#wp-cli).

One option is new, `lightweight_seo_settings_backup`. It is not autoloaded, exists only between an import and its undo or the next import, and is removed on uninstall. Two files are new and neither is loaded on a visitor request; see [Release Baselines](release-baselines.md).

## Structured data since rc.3

- **A page node on every page.** The graph now describes the page itself: a `WebPage`, a `CollectionPage` for lists of posts, a `SearchResultsPage`, or a `ProfilePage` for an author archive. It carries the name, description, language, dates, and featured image, and ties the site, the breadcrumb trail, and the article together. The separate `ProfilePage` node of author archives is now that page node.
- **A schema type per post type.** The content types table has a "Schema type" column: "Web page", "Article", "Blog post", or "News article". Posts stay "Article" and every other post type stays "Web page" until you choose otherwise. The `lightweight_seo_article_post_types` filter still sets the default.
- **Breadcrumbs that match the WordPress Breadcrumbs block.** The trail in the structured data follows the block's default rules: Home, the archive of the post type when it has one, parent pages or the first category with its parents, then the page. A post therefore lists its category, a day archive lists its year and month, and the posts page sits below Home. The block's own filters are applied, so a customized trail is the same in both places. The first item is named "Home", as in the block, not after the site.
- The article node now belongs to the page node (`isPartOf` and `mainEntityOfPage` point at `…#webpage`), and the page node belongs to the site.

These are changes to what is printed on every page. No setting is migrated: a schema type is stored in a content type's row only when it differs from the default.

## Importers since rc.3

- The import on the Tools tab reads three more plugins: SEOPress, The SEO Framework, and Slim SEO. Titles, descriptions, canonical addresses, robots choices, and sharing fields are imported in the same bounded way: a preview first, 50 posts at a time, empty fields only, and a rollback of the latest batch.
- Variables of SEOPress and Slim SEO that mean the same as a Lightweight SEO variable are converted. A field with any other variable is left for review and counted, as before.
- The SEO Framework's "always index" choice becomes "Show in search results" for that page.
- A title from The SEO Framework is imported with the site name that plugin added when it printed the title, so the page title stays the same after the switch.
- A page hidden by an import, from any of the six sources, now prints `noindex` only. Before, a page imported as hidden also printed `nofollow`, through the rule that keeps the behaviour of pages hidden before 1.1.

The field names of each plugin were taken from its published source code, and each import was compared with what the real plugin printed for the same page. See [Migration and RC Testing](migration.md) for the list.

## Abilities API since rc.3

On WordPress 6.9 and later, three abilities let agents and other clients of the Abilities API work with the SEO fields of a post, a term, or an author: `lightweight-seo/get-seo-fields`, `lightweight-seo/update-seo-fields`, and `lightweight-seo/get-seo-context`.

- Each one takes an `object_id` and an optional `object_type` (`post`, `term`, or `user`), requires the right to edit that item, and an update goes through the same sanitizers as the editing screens.
- Terms and authors have a title, a description, a canonical address, and a search visibility. The sharing fields exist for posts only.
- Site settings are not exposed.
- Nothing is loaded for them until WordPress collects abilities, and older WordPress versions never do.

See [Developer API](developer-api.md#abilities). One file is new; see [Release Baselines](release-baselines.md).

## Public API since rc.3

`get_object_context()` now returns the `title` and `description` of a post, term, or user with their variables filled in. Before, a custom SEO title such as `%title% %sep% %sitename%` was returned as it was typed.

## IndexNow module since rc.3

A sixth optional module, off by default, tells search engines that support IndexNow (such as Bing and Yandex; not Google) when a page is published, changed, or removed.

- It is the one part of the plugin, besides tracking codes you add yourself, that makes a request to another service: `api.indexnow.org`. Nothing is sent, and no code is loaded, while the module is off.
- Addresses are sent once at the end of the request that saved them, without waiting for the answer. There is no queue and no scheduled job.
- Only public pages that search engines may show are sent. Nothing is sent from a site that is not a production site, that discourages search engines, or whose address search engines cannot reach, such as `localhost`.
- The key is created when the module is first switched on and is answered at `/{key}.txt` by the plugin; no file is written. The key is never exported with the settings.
- The card shows whether the site sends, the key address, what was last sent, and a "Send a test" button.
- A post saved by a WP-CLI command is announced like one saved in the administration.

Settings `enable_indexnow` and `indexnow_key` and the non-autoloaded option `lightweight_seo_indexnow_last` are new. One file is new, loaded only while the module is on.

## Previews, toolbar, and command palette since rc.3

- **Title format previews.** Each title format on "Search appearance", and each title format in the content types table, shows what it becomes for the newest post of the site, and follows the format and the separator while you edit them.
- **Desktop and mobile search preview.** The search result preview in the editor panel and on the Overview has a Desktop/Mobile switch and cuts a long title or description off about where a search result would. The length count still uses the full text, and nothing here is a score.
- **Toolbar status.** On the front end, administrators see "SEO: shown in search", "SEO: hidden from search", or "SEO: page not found" in the toolbar, linked to the Inspect report of that page. It adds no style or script to the page.
- **Command palette.** On WordPress 6.9 and later the command palette lists every Lightweight SEO settings tab and "Inspect a page".
- The "Essential setup" card on the Overview gives its title and description the full width on a phone, with the badge and the dismiss link below.

The editor screens now load `lightweight-seo-formats.js` as well, because the editor preview and the settings previews share its variable and shortening rules. No setting and no file is new.

## Since rc.4

- An empty tagline no longer leaves a separator behind. WordPress installs with an empty tagline, and the default homepage title format is `%sitename% %sep% %tagline%`, so such a site printed "Site name –". `%tagline%` now behaves like the other optional variables: when the site has no tagline it is left out together with one separator beside it, and the homepage title is "Site name". A site with a tagline prints exactly what it printed before. The settings previews and the editor preview follow the same rule.

## Since rc.5

Not yet part of a release package.

- The setup checklist on the Overview no longer asks for a tagline. Its first step is "Set a site name" and counts as done once the site has a name, because a title now leaves an empty tagline out by itself.

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
