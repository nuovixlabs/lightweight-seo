# Release Baselines

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
