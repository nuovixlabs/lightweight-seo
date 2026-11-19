# Release Baselines

## 1.2.0-rc.1

Reference measurements for `1.2.0-rc.1` were recorded on 2026-11-19. Every row was measured again.

| Metric | Baseline |
|---|---:|
| Runtime package files | 53 |
| Release ZIP size | 243,439 bytes (237.7 KiB) |
| Warm peak-memory delta, all modules disabled | +375,720 bytes |
| Warm query delta, all modules disabled | +1 (25 vs 24) |
| 100-request median response-time regression, all modules disabled | +3.21% (81.899 ms vs 79.351 ms) |
| All-modules median response-time regression | +3.25% (81.930 ms vs 79.351 ms) |
| All-modules peak-memory delta | +387,884 bytes |
| All-modules query delta | +2 (26 vs 24) |
| Autoloaded core settings size | 2,522 bytes |
| Autoloaded module-state size | 116 bytes |

Reference environment: a clean local WordPress 7.1.5 with Twenty Twenty-Five and no other active plugin, PHP 8.3.31 with the built-in web server that `bin/dev-site serve` starts, SQLite through the SQLite Database Integration plugin 3.1.3, loopback HTTP, no persistent object cache. `1.1.0-rc.4` was measured on WordPress 7.1.4 with Twenty Twenty-Two, which this WordPress download does not bundle, so compare the deltas and percentages between the two tables, not the absolute times or query counts.

Each response-time result is the median of 100 sequential warm homepage requests after 10 warm-ups. It was taken four times, twice in each order of the three cases, and averaged; `1.1.0-rc.4` was taken twice. The two extra passes were added because the first pass gave 84.691 ms for all modules disabled, while the other three gave 80.942, 80.966, and 80.997 ms. With the first two passes alone, as before, the regressions are +4.19% with all modules disabled and +3.48% with all modules on. Query counts are from 20 instrumented warm requests in each of the first two passes and were identical. Peak memory is the average of those two passes; it differed by about 1,200 bytes between them with what the settings option held.

The all-modules case enables all six built-in modules, each with valid settings: three redirect rules, one hreflang mapping, a GTM container with no excluded environment so its tag is printed, a complete local business, AI Discovery with an `llms.txt` page, and an IndexNow key. The site runs on `127.0.0.1`, so IndexNow sends nothing, and the benchmark fetches no third-party script. The settings size is the stored option after one save through the settings screen of a new site; two settings are new since `1.1.0`.

The package grew from 52 to 53 files since `1.1.0`. The file added is not loaded on a visitor request:

- `admin/js/lightweight-seo-robots.js`: the Modules tab of the settings screen, while AI Discovery is on.

### Checked by hand for this candidate

On 2026-11-19, in a browser on local WordPress 7.1.5, on the code that is now in this candidate:

- The AI readiness report: every row fills in from 13 requests the browser sends, and with the module off the page holds neither the report nor `lightweight-seo-robots.js`. With `robots.txt` answered the way a host answered it on a real site (only `User-agent: Googlebot` / `Disallow: /` and `User-agent: *` / `Allow: /`), the report says the part Lightweight SEO adds is absent, names Googlebot as blocked, gives the `Sitemap:` line to add, and marks "Who writes robots.txt" as needing attention. With a physical `robots.txt`, it says the file takes precedence. With general rules that disallow everything, it names the 13 allowed AI crawlers as shut out of the whole site.
- `/robots.txt` in all four combinations of the two crawler switches: a group with `Disallow: /` for each crawler switched off, no group and no `Allow: /` line for an allowed one, WordPress's rules for all crawlers unchanged, and the marker comment in every case.
- The content signals switch, saved through the settings screen: one `Content-Signal` line under `User-agent: *`, above its rules.
- `/llms.txt`: the tagline as its summary, and the static front page listed with exactly the homepage's `<title>`.
- The description on the "Site identity" card, saved through the settings screen and printed on the Organization node.
- The editor checks in the block editor and, through a post type without REST support, in the classic editor: the counts of images without alternative text and of H1 headings, both following an edit, and "No description is printed for this page" on an empty post.
- The Content section of the Inspect report, on a post with three H1 headings on the page and one of two images without alternative text.
- With scripts switched off, in a sandboxed frame: the report prints every row with its link, and the rows the browser decides stay unresolved.
- A request sent the way the report sends its requests, without credentials, receives the signed-out page.
- No notice from the plugin in `debug.log`, no error from the plugin's scripts in the console, and no plugin asset on a visitor page.

Not checked: a subdirectory install, because the test web server pins the site address to the root of the host; multisite; an administration on another address than the site; a collapsed AI Discovery card waiting to send its requests; the editor checks on a very long post; a real IndexNow submission; and anything in `docs/qa-checklist.md` that 1.2 does not touch.

## 1.1.0-rc.4

Reference measurements for `1.1.0-rc.4` were recorded on 2026-11-17. Every row was measured again.

| Metric | Baseline |
|---|---:|
| Runtime package files | 52 |
| Release ZIP size | 218,974 bytes (213.8 KiB) |
| Warm peak-memory delta, all modules disabled | +376,840 bytes |
| Warm query delta, all modules disabled | +1 (22 vs 21) |
| 100-request median response-time regression, all modules disabled | +2.39% (79.877 ms vs 78.016 ms) |
| All-modules median response-time regression | +3.23% (80.534 ms vs 78.016 ms) |
| All-modules peak-memory delta | +387,648 bytes |
| All-modules query delta | +2 (23 vs 21) |
| Autoloaded core settings size | 2,453 bytes |
| Autoloaded module-state size | 116 bytes |

Reference environment: a clean local WordPress 7.1.4 with Twenty Twenty-Two and no other active plugin, PHP 8.3.31 with its built-in web server, SQLite through the SQLite Database Integration plugin 3.1.3, loopback HTTP, no persistent object cache. Each response-time result is the median of 100 sequential warm homepage requests after 10 warm-ups, taken twice (once in each order of the three cases) and averaged. Query and peak-memory values are averages of 20 instrumented warm requests and were identical in both runs.

This environment differs from the one used for `1.1.0-rc.1` (database, web server, WordPress and PHP versions), so compare the deltas and percentages between the two tables, not the absolute times or query counts.

The all-modules case enables all six built-in modules, IndexNow included, each with valid settings: three redirect rules, one hreflang mapping, a GTM container, a complete local business, AI Discovery with an `llms.txt` page, and an IndexNow key. The site runs on `localhost`, so IndexNow sends nothing, and the benchmark fetches no third-party script; it measures plugin-side overhead only. Tracking is an explicit user-enabled exception to the no-frontend-script rule and its external network cost belongs to the selected provider and browser environment.

The ZIP was built the way the release workflow builds it (`git archive` with the export rules, then `zip`). The package grew from 33 to 52 files since `1.1.0-rc.1`. Of the files added since `1.1.0-rc.3`, none is loaded on a visitor request, except the IndexNow module while it is switched on:

- `admin/js/lightweight-seo-formats.js`: the settings screen and the editor screens.
- `includes/class-lightweight-seo-post-list.php`: the administration area, for the post list column.
- `includes/class-lightweight-seo-inspector.php`: only when an administrator asks for the report of a page.
- `includes/class-lightweight-seo-redirect-import.php`: only while redirect rules are imported from a CSV file.
- `includes/class-lightweight-seo-settings-transfer.php`: only for a settings export, import, or undo.
- `includes/class-lightweight-seo-cli.php`: only under WP-CLI.
- `includes/class-lightweight-seo-abilities.php`: only when WordPress collects abilities.
- `includes/class-lightweight-seo-indexnow-module.php`: only while the IndexNow module is switched on.

### Checked by hand for this candidate

On 2026-11-17, on local WordPress 7.1.4 unless noted:

- The classic editor panel, keyboard use of the settings tabs, module cards, Quick Edit and the preview switch, the four structured editors and the previews with scripts switched off, and LocalBusiness opening hours saved through the day rows.
- Imports from the real SEOPress 10.4, The SEO Framework 5.2.1, and Slim SEO 4.11.6: each plugin printed a page from its own stored fields, the fields were imported, the plugin was deactivated, and the page head was compared.
- Safe mode beside the real SEOPress and Yoast SEO 28.8: one title, description, robots tag, canonical, and schema block per page, none of them from Lightweight SEO.
- The abilities as an administrator, an author, a subscriber, and signed out, through WordPress and through its REST routes.
- A multisite network: network activation, separate settings and module state per site, a site created after activation, and a settings file moved between two sites. Page output of sub-sites was not checked by hand, because the test web server does not route them.
- WordPress 6.8.3: page output, all six settings tabs, and the commands, with no abilities and no command palette entries.

Not checked: a real IndexNow submission, which needs a public site.

## 1.1.0-rc.5

`1.1.0-rc.5` changes one rule of the title variables (an empty tagline leaves with its separator) and adds no file. Only the package was rechecked: 52 files, 219,127 bytes (214.0 KiB). The timing, memory, and query rows of `1.1.0-rc.4` were not measured again; the change adds no query and no file to a request.

## 1.1.0-rc.6

`1.1.0-rc.6` changes what the setup checklist on the Overview asks for (a site name, no longer a tagline) and adds no file. Only the package was rechecked: 52 files, 219,068 bytes (213.9 KiB). The timing, memory, and query rows of `1.1.0-rc.4` were not measured again; the change touches the administration area only.

## 1.1.0

`1.1.0` is the first stable release of the 1.1 line. Since `1.1.0-rc.6` it changes two shipped files and adds none: the legacy keyword CSV export passes the CSV escape character explicitly, and one trailing space left the Meta Pixel `noscript` markup. Only the package was rechecked: 52 files, 219,068 bytes (213.9 KiB), the same size as `1.1.0-rc.6` by coincidence; the package was opened to confirm it carries the `1.1.0` header. The timing, memory, and query rows of `1.1.0-rc.4` were not measured again; the export runs in the administration area only, and the removed space adds no query and no file to a request.

### Checked by hand for this release

On 2026-11-19:

- The three `fputcsv()` calls of the export, with the arguments the plugin passes, on PHP 8.3.31 and PHP 8.5.7: no notice on either, and the same bytes as the call without the escape argument, which raises three deprecation notices on PHP 8.5.7.
- No other CSV call in the shipped code leaves the escape argument out.
- The unit suite on PHP 8.2, 8.3, and 8.5, and a syntax check of the shipped files on PHP 8.5.

Not checked: the export downloaded from a real site in a browser, and anything in `docs/qa-checklist.md` that the two changes do not touch. That list was last worked through for `1.1.0-rc.4`.

## 1.1.0-rc.1

Reference measurements for `1.1.0-rc.1` were recorded on 2026-08-24.

| Metric | Baseline |
|---|---:|
| Runtime package files | 33 |
| Release ZIP size | 95,391 bytes (93.2 KiB) |
| Warm peak-memory delta, all modules disabled | +352,928 bytes |
| Warm query delta, all modules disabled | +1 (42 vs 41) |
| 100-request median response-time regression, all modules disabled | +2.84% (81.334 ms vs 79.092 ms) |
| All-modules median response-time regression | +1.58% (80.339 ms vs 79.092 ms) |
| All-modules peak-memory delta | +361,736 bytes |
| All-modules query delta | +2 (43 vs 41) |
| Autoloaded core settings size | 2,333 bytes |
| Autoloaded module-state size | 97 bytes |

Reference environment: local WordPress 6.0, Twenty Twenty-Two, PHP 8.5, MySQL 8, loopback HTTP, no persistent object cache. Each response-time result is the median of 100 sequential warm homepage requests after 10 warm-ups. Query and exact peak-memory values are averages of 20 instrumented warm requests. The all-modules case enabled the five modules that existed then.

The exact staged archive passed `unzip -t`, contained all runtime files, excluded tests/development configuration, and smoke-activated in a clean latest-WordPress install with plugin version `1.1.0-rc.1` and API version `1.0`.

`1.1.0-rc.2` (43 files) and `1.1.0-rc.3` (44 files) were tagged without repeating these measurements; only the package file count was rechecked. After `1.1.0-rc.1` the settings screen was split into administration-only files, the settings screen, editor panel, and importer stopped loading on visitor requests, and import progress moved from the autoloaded settings option to the non-autoloaded `lightweight_seo_import_state` option.
