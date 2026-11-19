# Changelog

What changed in each release. The release notes for [1.2](docs/release-notes-1.2.0.md) and [1.1](docs/release-notes-1.1.0.md) describe each line in full, by feature.

Add every change under "Unreleased" in the commit that makes it. `bin/release-prep` moves that section under the new version.

## Unreleased

### Added

- Content checks in the editor panel: images in the content without alternative text, as a count, and more than one H1 heading. Both follow the content as it is edited. An image that is only decorative may keep an empty `alt`, so it is a warning, not a failure.
- A "Content" section in the Inspect report: characters of text in the page's main content, the number of H1 headings, and images with an empty or missing `alt` out of the total. The Tools tab requests the page again, signed out, and counts what it receives, so a visitor's request does no extra work.
- Content signals: a switch on the AI Discovery card, off by default, stored as `enable_content_signals`. While it is on and WordPress answers `robots.txt`, one `Content-Signal:` line is added inside the `User-agent: *` group: `search=yes`, `ai-input` following the AI search switch, and `ai-train` following the model-training switch. A file that already holds a `Content-Signal` line is left alone. The served-file check reports whether a `Content-Signal` line is served.
- A description for the organization or person on the "Site identity" card. When it is written, the Organization or Person node of the schema prints it as `description`; empty by default, which prints nothing, as before. Stored as `identity_description` in the settings option.

### Changed

- The editor's description check says which description the page prints: the one written there, the homepage meta description on a static front page, or a summary of the excerpt or content. When none of these exists, it says "No description is printed for this page", and a password-protected page says that it prints none.
- AI Discovery writes rules for eleven more crawlers, each checked on its vendor's own page. `Applebot-Extended`, `Meta-ExternalAgent`, `Amazonbot`, and `MistralAI-Training` follow "Allow bundled model-training crawler tokens" (off by default); `Meta-ExternalAgent` and `Amazonbot` serve other uses too, and a `Disallow: /` stops them for all of them. `Meta-WebIndexer`, `Amzn-SearchBot`, `DuckAssistBot`, `MistralAI-Index`, `Meta-ExternalFetcher`, `Amzn-User`, and `MistralAI-User` follow "Allow bundled AI search and user-directed crawler tokens" (on by default). A site that already has AI Discovery on serves the new groups in its virtual `robots.txt` after updating, following the switches as they are set. `CCBot` and `Claude-Agent` were considered and left out: Common Crawl documents no AI purpose for its crawler, and Anthropic describes no `robots.txt` control for its hosted browser. `docs/ai-crawlers.md` records each token's source and the date it was checked.
- The AI Discovery card checks the `robots.txt` that is actually served. The administrator's browser fetches it, signed out, and reports whether the card's crawler rules are in it, any group for Googlebot, Bingbot, or every crawler (`*`) that disallows the whole site (or everything but the paths its `Allow` lines list), whether a `Sitemap:` line is present (with the exact line to add when WordPress sitemaps are on), and a failed fetch, a status other than 200, or a type other than `text/plain`. The server requests nothing. Without JavaScript, a link opens the file. On a subdirectory install, the card says that WordPress does not answer the `robots.txt` crawlers read. The reader, `admin/js/lightweight-seo-robots.js`, loads only on the Modules tab while AI Discovery is on.

### Fixed

- `/llms.txt` opens with a summary of the site, as the format expects: the homepage meta description, else the tagline, and no summary line when neither is set. The notice that Google Search ignores `llms.txt` stays on the AI Discovery card and is no longer printed in the file.
- A static front page listed in `/llms.txt` uses the title and description the homepage prints: its own SEO title or the homepage title format, and its own description or the homepage meta description. It was listed by the page's name, often with no description.

### Documentation

- The product boundary in `docs/architecture.md` now covers AI readiness: the plugin checks whether AI crawlers and agents can reach and read the site, and publishes agent-discovery output only for things the site really offers. There is no AI readiness score or level.
- `docs/release-notes-1.2.0.md` describes the 1.2 line as it is built, and the agent rules point at it.

## 1.1.0 - 2026-11-19

The first stable release of the 1.1 line. It holds everything listed under the release candidates below, and this fix.

### Fixed

- The legacy keyword CSV export passes the CSV escape character explicitly. On PHP 8.4 and later it raised a deprecation notice, which a site that displays errors would write into the downloaded file.

## 1.1.0-rc.6 - 2026-11-18

### Fixed

- The setup checklist on the Overview no longer asks for a tagline. Its first step is "Set a site name".

## 1.1.0-rc.5 - 2026-11-18

### Fixed

- An empty tagline no longer leaves a separator behind in a title. `%tagline%` is left out together with one separator beside it, like the other optional variables.

## 1.1.0-rc.4 - 2026-11-17

### Added

- A content types table for search visibility, sitemap listing, title format, and schema type of every post type and taxonomy.
- A site identity card for an organization or a person, an X handle, and a choice of title separator.
- Site verification tags for Google Search Console, Bing Webmaster Tools, Pinterest, and Yandex Webmaster.
- Four title variables: `%category%`, `%author%`, `%year%`, and `%page%`.
- An SEO column in the post lists, with Quick Edit and Bulk Edit.
- "Inspect a page" on the Tools tab, with links from the post lists, the editor, and the toolbar.
- An "Enable module" switch for Redirects and Tracking. The settings schema version is now 4.
- Redirects: whole sections, "410 Gone", a test box, and CSV import.
- Settings export and import with undo, and `wp lightweight-seo` commands.
- Structured data: a page node on every page, a schema type per post type, and breadcrumbs that match the WordPress Breadcrumbs block.
- Importers for SEOPress, The SEO Framework, and Slim SEO.
- Abilities to read and fill the SEO fields of posts, terms, and authors on WordPress 6.9 and later.
- An IndexNow module, off by default.
- Live title format previews, a desktop and mobile search preview, and command palette entries.

### Changed

- The editor panel follows the page as it is edited, and the setup checklist is dismissed per user.
- Redirect rules, hreflang mappings, opening hours, and `llms.txt` pages are edited as rows, with "Edit as text" for pasting.
- A module that is off shows only the controls that turn it on.
- `get_object_context()` returns titles and descriptions with their variables filled in.
- A page hidden by an import prints `noindex` only.

### Fixed

- The editor no longer stores the featured image or the site default as a post's own social image.
- A title imported from The SEO Framework keeps the site name that plugin added.

## 1.1.0-rc.3 - 2026-11-17

### Changed

- The settings screen and the editor panel are rebuilt on one design system. No setting, option key, or saved value changes.
- Administration CSS class names changed from `lightweight-seo-*` to `lwseo-*`.

## 1.1.0-rc.2 - 2026-11-17

The first 1.1 candidate published as a release package. `1.1.0-rc.1` was never published.

### Added

- A request-aware module registry with Redirects, Hreflang, Tracking, Local SEO, and experimental AI Discovery modules.
- A versioned, read-only extension API.
- A setup overview, grouped settings, and deterministic editor checks.
- Bounded imports from Yoast SEO, Rank Math, and All in One SEO, with preview and rollback.
- Real WordPress integration tests and a WordPress and PHP matrix in CI.

### Changed

- Canonical and robots output go through WordPress core, with defined behavior for 404, paginated, password-protected, and subdirectory cases.
- Descriptions derived from content are shortened to a 160-character summary.
- Safe mode also covers SEOPress, The SEO Framework, and Slim SEO.

### Removed

- Meta-keywords output and editing. Stored values remain exportable.
- Image, video, and news sitemap providers.
- Generic Product schema.
- Search Console synchronization, site-wide internal-link and image-audit reports, and continuous 404 logging.

### Security

- JSON-LD is encoded so that no value can close its script element.
- Term SEO fields require the `edit_term` capability in addition to the nonce.
- Import, rollback, and legacy data deletion are separate administrator actions, each with its own nonce.
- The coding-standard gate enforces output escaping, nonce verification, input sanitization, and the text domain.

## 1.0.3 and earlier

See the [GitHub releases](https://github.com/nuovixlabs/lightweight-seo/releases).
