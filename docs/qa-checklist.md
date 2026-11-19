# QA Checklist

Use this checklist for a release candidate. Test destructive migration and uninstall cases only on a disposable or backed-up site.

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

- Activate a clean install and confirm every optional module defaults off.
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
- Confirm schema JSON parses and uses Organization or Person, WebSite, one page node (WebPage, CollectionPage, SearchResultsPage, or ProfilePage), BreadcrumbList, and one Article, BlogPosting, or NewsArticle node only.
- Schema: on a post, a page with a parent, a custom post type item, a category with a parent, an author archive, a day archive, a search, the posts page, and the front page, confirm the page node type, that the article node and the breadcrumb point at `…#webpage` and `…#breadcrumb`, and that the front page has no breadcrumb.
- Schema: place the WordPress Breadcrumbs block (WordPress 7.0 or later) on a post, a child page, and a category archive, and confirm the trail in the structured data names the same items in the same order.
- Schema: in the content types table set a post type to "Blog post", "News article", and "Web page", and confirm the article node changes type or goes away. Set it back to its default and confirm nothing is stored for that row.
- Confirm a WooCommerce Product page receives no Product graph from Lightweight SEO.
- Confirm an ordinary frontend request makes no remote request, recurring schedule, custom table, or plugin write.
- Confirm a post list with the SEO column runs no more database queries than the same list with the column switched off.
- Site identity: on an upgraded site that has never saved the card, confirm the homepage schema still prints the same Organization node, with the default social image as its logo, and that no `twitter:site` tag is printed.
- Site identity: set a name and a logo and confirm the Organization node uses both; clear the logo and confirm it falls back to the default social image.
- Site identity: choose "A person" and confirm a `Person` node with the `#person` identifier and an `image`, no `logo`, and that the `publisher` of the WebSite node and of an Article points at it. With no photo chosen, confirm the Person has no `image` even when a default social image exists. With Local business on, confirm the LocalBusiness node is printed beside the Person.
- Site identity: set an X handle and confirm one `twitter:site` tag with a leading `@` on the homepage, a post, and an archive, and none on a 404.
- Verification: enter a code for each service and confirm its meta tag is printed once on the first page of the homepage, and on no post, archive, search, later homepage page, or 404.
- Title variables: use `%category%`, `%author%`, `%year%`, and `%page%` in the default, archive, and search title formats and in one content type's format. Confirm a post with and without a category, a category archive, an author archive, a date archive, and page two of an archive, with no stray separator where a variable has no value.
- Title variables: confirm a format that uses none of the optional variables prints exactly what it printed before.
- Title variables: on a site with an empty tagline and the default homepage format, confirm the homepage title is the site name alone with no separator after it, on the page, in the homepage preview on the Overview, and in the preview under "Homepage title format". Set a tagline and confirm it returns with its separator. On the same site without a tagline, confirm the setup checklist on the Overview shows "Set a site name" as done and does not ask for a tagline.
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
- Module switches: confirm every module card starts with "Enable module". Save redirect rules and a tracking ID with both switches clear; neither module may load, and the rules and the ID must still be there. Save another tab and confirm the switches did not change.
- Module switches: upgrade a site that has redirect rules and one that has a valid tracking ID; each module must stay on with its switch ticked. Upgrade a site that has neither; both must stay off.
- Redirects: test exact rules, malformed destinations, external-host rejection, loops, chains, automatic slug redirects, export, and the documented high-rule boundary.
- Redirects: change a published slug in the block editor, in Quick Edit, and in the classic editor; each must record a generated redirect.
- Redirects: save `/old/* /new/*`, `/old/special /landing`, and `/old/archive/* /archive`. Confirm the exact rule wins, the longest section wins next, the rest of the path is carried over only where the target ends in `/*`, and `/older` is not affected.
- Redirects: save a "410 Gone" rule for a missing address, for a published post, and for a section. Each must answer 410 with the theme's "not found" page and no redirect, and the post must leave the sitemap. In the row editor the destination of a 410 row must be switched off.
- Redirects: confirm `/* /anywhere`, `/a/* /a/b/*`, and a pair of sections that point at each other are left out with the usual notice.
- Redirects: use "Test an address" with a path, a full address, an address no rule covers, and one that passes through two rules; then repeat with JavaScript disabled. Testing must not mark the form as unsaved.
- Redirects: import a CSV file with a header row, a new rule, a rule for a path that already has one, a 410 row, and a row pointing to an unapproved site. The notice must count each kind, and a request without a file must change nothing.
- Hreflang: test reciprocal mappings, `x-default`, invalid locale/URL rejection, path mirroring, and WPML or Polylang ownership.
- Tracking: test GTM precedence, direct GA4/Meta alternatives, consent filters, role/environment exclusions, CSP nonce, and a missing `wp_body_open` diagnostic.
- Local SEO: test required fields, supported subtype, address, coordinates, hours, image, and disabled behavior.
- IndexNow: with the module off, confirm no key exists, the key address answers 404, and the module file is not loaded. Switch it on and save; a key must be created once, the key address must answer 200 as plain text with the key, and no file may be written.
- IndexNow: on a local, development, or staging site, on a site that discourages search engines, and on `localhost`, confirm the card names the reason, "Send a test" sends nothing, and saving a post attempts no request.
- IndexNow: on a public production site, publish a post, update it, change its slug, and move it to the trash. Each save must send one request with the right addresses and must not wait for the answer. A draft, a password-protected post, and a post or content type hidden from search results must send nothing. "Send a test" must report the answer.
- IndexNow: publish a post with `wp post create --post_status=publish` on a public production site and confirm it is announced; with the module off, confirm a command loads no module file.
- IndexNow: export the settings and confirm the key is not in the file; import a file and confirm the key of the site is unchanged.
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
- Verification: paste a bare code, then the whole meta tag a service shows; both must save as the code. A value that is not a code must show an error that names the service and keep the saved code.
- Title variables: save each of the four new variables in a title format, in a content type's format, and in a page's own SEO title, meta description, social title, and social description, and in a term's and an author's SEO title. Reopen each and confirm it still reads exactly as typed; `%category%` in particular must not come back as `tegory%`.
- Title variables: open "What the variables mean" on "Search appearance" and confirm every variable button has a line there. In the editor, type a title that uses `%page%` and `%category%` on a post without a category and confirm the preview has no stray separator.
- Inspect: on the Tools tab, inspect a post, a page, a custom post type item, a term archive, an author archive, a date archive, a search, page two of an archive, the homepage, and a missing address. For each, the title, robots directives, canonical, description, sharing tags, and schema types in the report must equal the page source fetched separately.
- Inspect: hide a content type, hide one item, set one item to "Show", hide author and date archives, and set WordPress to discourage search engines; each reason must appear in the report in the settings screen's own words, and disappear when the cause is removed.
- Inspect: confirm the title and description sources for an item with its own values, an item using its content type's format, the homepage, and a search page.
- Inspect: enter an address that a redirect rule sends elsewhere; the card must say where it goes and put that address in the field. Enter an address on another site; the card must refuse it without making a request.
- Inspect: follow "Inspect SEO" from a post list row and "Inspect what is sent for this page" from the editor's Checks tab; each must open the Tools tab with the report already shown. Neither link may appear for a draft or for a user who cannot manage options.
- Inspect: press Enter in the address field and confirm the report runs, the settings are not saved, and "Unsaved changes" does not appear.
- Inspect: with JavaScript disabled, arrive from one of those links and open "Open the report in a new tab"; the same report must show as a page of its own.
- Inspect: with another SEO plugin active, confirm the report says which outputs are held back instead of listing values that are not printed.
- Post list: confirm the "SEO" column on the Posts, Pages, and each custom post type list, and not in the Media Library. For an item with nothing set it must read "Title: default" and "Description: from content" or "Description: none"; with its own values, "own"; and "Hidden from search" only when the item is hidden, by its own choice or through its content type.
- Post list: open Quick Edit; the SEO title, description, and search choice must be filled with the saved values, variables included. Change all three and save; the row must update in place, the page source must follow, and the editor panel must show the same values. Confirm the canonical, social fields, and other robots directives of that item are unchanged.
- Post list: hide a page for the first time from Quick Edit or Bulk Edit and confirm the page prints `noindex` without `nofollow`.
- Post list: hide the SEO column in Screen Options; Quick Edit must no longer show the SEO group, and saving Quick Edit must change no SEO field. Show the column again and confirm the group returns.
- Post list: select several items and use Bulk Edit; "— No change —" must change no SEO field, and "Hide", "Show", and "Default" must each apply to every selected item and to no other.
- Post list: as a user who can edit only their own posts, confirm Quick Edit and Bulk Edit change only items that user may edit.
- Post list: use the list at phone width and Quick Edit with the keyboard only.
- Site identity: enter an X handle as `handle`, as `@handle`, and as an `x.com` profile address; each must save as the same handle and show with its `@`. An invalid value must show an error and keep the saved handle.
- Site identity: choose a logo with the media picker, save, and confirm the thumbnail and the stored address; clear the address, save, and confirm both are gone.
- Title formats: confirm each of the four formats, and each title format in the content types table, shows a preview built from the newest post, that editing a format or changing the separator updates it without a save, that an emptied format previews its default, and that the previews are complete with JavaScript disabled.
- Search preview: in the block editor, the classic editor, and on the Overview, switch between Desktop and Mobile with the mouse and the keyboard. A long title and description must end in an ellipsis at a word, the character count must stay on the full text, and with JavaScript disabled the switch must be absent and the full text shown.
- Toolbar: signed in as an administrator, open a normal page, a page hidden from search, a search, and a missing address on the front end. The toolbar item must name the state and open the Inspect report of that address. Confirm an editor and a signed-out visitor get no item, and that the page loads no Lightweight SEO style or script.
- Command palette (WordPress 6.9 or later): open it with Ctrl/Cmd+K, type "Lightweight", and run "Inspect a page" and one tab command. Confirm a user who cannot manage options has none of them.
- Overview: at 320 px wide, confirm the "Essential setup" title and description use the full width with the badge and "Dismiss checklist" below.
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
- Import titles that use the source plugin's current year, author name, and page variables, and Rank Math's `%category%`; each must arrive as `%year%`, `%author%`, `%page%`, or `%category%`. A Yoast or All in One SEO variable that lists every category must still be reported, not imported.
- Import from SEOPress, The SEO Framework, and Slim SEO on a site that holds their metadata: preview, import, compare each imported field with the source, and roll back. Confirm a page imported as hidden prints `noindex` without `nofollow`, a page hidden before the import is unchanged, and The SEO Framework's "always index" arrives as "Show".
- Settings file: download it from Tools and confirm it holds no credentials, no retained legacy values, and no page titles or descriptions.
- Settings file: change several settings, import the file, and confirm every setting equals the file. Confirm the import does nothing without its confirmation box or without a file, and that a file that is not a settings file, or one from a newer version, is refused with nothing changed.
- Settings file: import a file whose site address differs and confirm image addresses are kept while image IDs, the `llms.txt` list, and page-bound hreflang lines are left out, with a notice saying so.
- Settings file: use "Undo last import" and confirm the settings option is byte-for-byte what it was before the import, then confirm the button is gone.
- WP-CLI: run each `wp lightweight-seo` command once, including an import of a damaged file and `module enable` with an unknown module, which must end with an error and change nothing.

## Security and privacy

- Confirm every mutation requires both the documented capability and a nonce.
- Request any address with the inspect argument while signed out, as a user who cannot manage options, and with a wrong nonce; each must return the normal page with no report and no `X-Lightweight-SEO-Inspect` header.
- Confirm object saves use object-specific edit capabilities.
- Test stored and reflected output escaping with HTML and CSV-formula payloads.
- Test redirect scheme, host, chain, loop, and malformed-rule validation.
- Confirm credentials/tokens never appear in public APIs, frontend markup, diagnostics, JavaScript data, or logs.
- Confirm `/llms.txt` includes only selected public, indexable, local, canonical, non-redirected posts/pages and never full content.
- `/llms.txt` opens with the site name and, under it, the homepage meta description, or the tagline when there is none, or no summary line when both are empty. List the static front page and confirm its line shows the homepage's `<title>` and meta description, as on the homepage itself.
- Confirm core makes no external request and stores no new 404 or analytics history.
- Abilities (WordPress 6.9 or later): run each `lightweight-seo/…` ability for a post, a term, and an author as an administrator, as a user who cannot edit that item, and signed out. Only the first may read or write. Confirm an unknown field, an unknown visibility value, and an address that is not `http` or `https` save nothing, and that the REST routes answer 401 without a sign-in.
- Abilities: on WordPress older than 6.9, confirm the plugin loads without errors and `includes/class-lightweight-seo-abilities.php` is never loaded.

## Performance and packaging

- Compare 100 warm frontend requests with the plugin active and inactive; median regression must not exceed 5% in the reference environment.
- Record warm peak-memory delta, query delta, option sizes, runtime file count, and ZIP size in `docs/release-baselines.md`.
- Confirm disabled-module and all-module states remain within the recorded baseline.
- Build the release ZIP with `bin/build-zip`, which checks its integrity and that it holds every plugin file and no development file, and smoke-activate that exact package in WordPress.

## Final gates

- `composer check`: Composer validation, PHP syntax, PHPCS, unit tests, JavaScript tests, translation template freshness, and whitespace
- `composer audit`
- real WordPress single-site and multisite integration suites, which CI runs on every push
- `bin/build-zip`: the package holds only plugin files and every plugin file
