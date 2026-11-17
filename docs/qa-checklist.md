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
- Confirm homepage, static Posts page, post, page, term, author, search, attachment, password-protected, and 404 behavior.
- Confirm page two of a term and author archive carries a self-referencing canonical.
- On a subdirectory install, confirm canonical, `og:url`, and redirect targets contain the install path exactly once.
- Confirm a long post without an excerpt emits a short description summary, not the full content.
- Confirm Open Graph and X/Twitter fallbacks and social image validation.
- Confirm schema JSON parses and uses Organization or Person, WebSite, BreadcrumbList, ProfilePage, and scoped Article nodes only.
- Confirm a WooCommerce Product page receives no Product graph from Lightweight SEO.
- Confirm an ordinary frontend request makes no remote request, recurring schedule, custom table, or plugin write.
- Site identity: on an upgraded site that has never saved the card, confirm the homepage schema still prints the same Organization node, with the default social image as its logo, and that no `twitter:site` tag is printed.
- Site identity: set a name and a logo and confirm the Organization node uses both; clear the logo and confirm it falls back to the default social image.
- Site identity: choose "A person" and confirm a `Person` node with the `#person` identifier and an `image`, no `logo`, and that the `publisher` of the WebSite node and of an Article points at it. With no photo chosen, confirm the Person has no `image` even when a default social image exists. With Local business on, confirm the LocalBusiness node is printed beside the Person.
- Site identity: set an X handle and confirm one `twitter:site` tag with a leading `@` on the homepage, a post, and an archive, and none on a 404.
- Title separator: choose each separator in turn and confirm a title built with `%sep%` uses it on a post, the homepage, an archive, and a search page, and that a format with a typed character is left as written.
- Content types: on an upgraded site that has never saved the table, confirm the robots result and title of a post, page, attachment, custom post type item, term, author, date, and search page are unchanged.
- Content types: hide a post type and confirm its items and its archive page carry `noindex`; set one item to "Show" and confirm only that item loses it. Hide one item of a shown post type and confirm only that item carries `noindex`.
- Content types: repeat the hide, show, and per-item exception for a taxonomy with one term and for author archives with one author. Hide date archives and confirm a year and a month archive carry `noindex`.
- Content types: give a post type and a taxonomy their own title format; confirm each is used, that a page with its own SEO title keeps it, and that the static front page keeps the homepage format.

## Sitemaps and headers

- Open `/wp-sitemap.xml` and confirm valid XML.
- Confirm noindexed and redirected content is excluded when configured.
- Content types: clear "In sitemap" for a post type and a taxonomy and confirm each sub-sitemap leaves the index while its pages stay indexable.
- Content types: hide a post type and confirm its sub-sitemap lists only the items set to "Show", and leaves the index when there are none. Repeat for a taxonomy and for author archives. With "Leave hidden pages out of the sitemap" off, confirm hidden content stays listed.
- Confirm no image, video, or news sitemap provider is registered.
- Confirm attachment responses receive configured WordPress-controlled X-Robots headers.
- Confirm static media served directly by the web server is not claimed as plugin-controlled.

## Modules

- Toggle every module repeatedly and confirm disabled implementation classes do not load.
- Redirects: test exact rules, malformed destinations, external-host rejection, loops, chains, automatic slug redirects, export, and the documented high-rule boundary.
- Redirects: change a published slug in the block editor, in Quick Edit, and in the classic editor; each must record a generated redirect.
- Hreflang: test reciprocal mappings, `x-default`, invalid locale/URL rejection, path mirroring, and WPML or Polylang ownership.
- Tracking: test GTM precedence, direct GA4/Meta alternatives, consent filters, role/environment exclusions, CSP nonce, and a missing `wp_body_open` diagnostic.
- Local SEO: test required fields, supported subtype, address, coordinates, hours, image, and disabled behavior.
- AI Discovery: test physical/virtual `robots.txt`, model-training default off, curated `/llms.txt`, conflict behavior, ETag `304`, privacy exclusions, invalid IDs, and readiness messages.

## Editor and accessibility

- Use the setup overview and each settings tab with keyboard only.
- Confirm visible focus, logical tab order, associated labels, and understandable validation errors.
- Check desktop and narrow/mobile layouts.
- Confirm the editor panel explains title, description, canonical, robots, and social-image issues without speculative scores.
- In the block editor, change the post title, excerpt, content, and featured image; the previews, length meters, and checks must follow without a save. Clear the last warning and confirm the check count becomes a tick. Repeat the title and excerpt changes in the classic editor.
- Save a post that has a featured image and no social image of its own; its social image field must stay empty and `og:image` must follow a later change of featured image.
- Change a field on a settings tab and try to leave; the browser must ask first, and "Unsaved changes" must show beside Save. Saving must leave without a prompt.
- On the overview, confirm the homepage preview matches the homepage's real title, description, and image, and that each setup step is green only when it is done. Dismiss the checklist, confirm it stays dismissed in another browser for the same user, and bring it back with "Show setup checklist".
- Tick and clear every excluded role and environment, saving each time; an empty list must save as empty.
- Open the Modules tab and change nothing; "Unsaved changes" must not appear.
- Site identity: enter an X handle as `handle`, as `@handle`, and as an `x.com` profile address; each must save as the same handle and show with its `@`. An invalid value must show an error and keep the saved handle.
- Site identity: choose a logo with the media picker, save, and confirm the thumbnail and the stored address; clear the address, save, and confirm both are gone.
- Title separator: change it and confirm the editor preview of a page without its own SEO title and the homepage preview on the Overview both follow, and that saving "Schema and identity" afterwards leaves it unchanged.
- Content types: open "Content and indexation" and change nothing; "Unsaved changes" must not appear. Confirm every public post type and taxonomy has a row, that Media and "Internal search pages" start hidden, and that an empty title format shows the site-wide format as its placeholder.
- Content types: clear a "Show in search results" box and confirm "Shown items only" appears beside that row's sitemap box without moving the columns. Insert a variable into a title format with the variable buttons. Save, reload, and confirm every value held.
- Content types: on a site whose homepage is a page, hide Pages and save; the card must warn that the homepage is hidden too, and setting the homepage to "Show" must keep it indexable.
- Content types: use the table at 320 px wide and with the keyboard only, then save it with JavaScript disabled.
- Content types: save another settings tab and confirm the table is unchanged. Deactivate a plugin that registers a post type, save the table, reactivate it, and confirm that post type's row kept its values.
- Search visibility: in the block editor and the classic editor, confirm the "Search results" choice names the content type and its current default, and that the Indexing check follows the choice without a save, with its own message for a page hidden only by its content type. Confirm the same choice on a term's add and edit screens and on a user profile.
- Search visibility: confirm a page saved before this change with "Prevent search engines from indexing this page" ticked opens as "Hide from search results", and one saved with it clear opens as "Default".
- Redirects: add, edit, and remove rows, then save and confirm a request to the old path redirects. Paste several rules through "Edit as text" and switch back to rows. Save a loop or a repeated path and confirm the notice says how many rules were left out.
- Opening hours: set weekday hours with "Copy Monday to Tuesday–Friday", add a second time range to one day, leave one day closed, and save; the LocalBusiness schema must list the same hours.
- `llms.txt`: search for pages, add and remove them, and save; `/llms.txt` must list the chosen pages.
- Hreflang: add a translation by searching for a page, save, and confirm the `<link rel="alternate">` on that page. A retained legacy line must show as "Every page (legacy)" and survive a save.
- With JavaScript disabled, each of those four settings must show its original text field and save from it.
- Open `?page=lightweight-seo&tab=developer`; it must land on Tools with the Developer API card open.
- Confirm no control promises ranking, crawling, citation, training, or inclusion.

## Migration

- Upgrade a representative 1.0.3 fixture and confirm supported settings plus post, term, user, canonical, robots, and social metadata survive.
- Confirm retired reports, tokens, and Search Console cron events are removed.
- Confirm the one-time upgrade summary lists retired behavior and specialized sitemap endpoints.
- Confirm Search Console credentials require explicit copy/delete and are never reused automatically.
- Export retained keyword values and confirm they are not emitted on the frontend.
- Preview a supported-source import, confirm it is read-only, then import in batches of at most 50.
- Save the Tools tab and confirm it runs no import, rollback, or deletion; delete retained 404 log entries with their own confirmation box and button.
- Confirm occupied Lightweight SEO fields are never overwritten and roll back the latest batch.
- Import a source title containing template variables and confirm the page title shows resolved text, with unsupported variables reported rather than imported.

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
