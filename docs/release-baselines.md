# Release Baselines

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

Changes made after these measurements add two shared helper files, split the settings screen into eight more administration-only files (43 runtime package files), and stop loading the settings screen, editor panel, and importer on visitor requests. Import progress moved from the autoloaded settings option to the non-autoloaded `lightweight_seo_import_state` option.

`1.1.0-rc.2` was tagged without repeating these measurements. Only the package file count (43) was rechecked.

`1.1.0-rc.3` rebuilds the administration interface and adds one administration-only file (44 runtime package files). It was also tagged without repeating these measurements; only the package file count was rechecked. Re-measure every row before tagging the stable `1.1.0` release.

Work after `1.1.0-rc.3` adds eight files. `admin/js/lightweight-seo-formats.js` is loaded on the settings screen, `includes/class-lightweight-seo-post-list.php` in the administration area for the post list column, and `includes/class-lightweight-seo-inspector.php` only when an administrator asks for the report of a page. None is loaded on a visitor request. A fourth, `includes/class-lightweight-seo-redirect-import.php`, is loaded only while redirect rules are imported from a CSV file. Two more serve the settings file: `includes/class-lightweight-seo-settings-transfer.php`, loaded only for an export, import, or undo, and `includes/class-lightweight-seo-cli.php`, loaded only under WP-CLI. `includes/class-lightweight-seo-abilities.php` is loaded only when WordPress collects abilities. `includes/class-lightweight-seo-indexnow-module.php` is loaded only while the IndexNow module is switched on. The exported package now holds 52 files, counted with `git archive`. The other rows have not been measured again.

Reference environment: local WordPress 6.0, Twenty Twenty-Two, PHP 8.5, MySQL 8, loopback HTTP, no persistent object cache. Each response-time result is the median of 100 sequential warm homepage requests after 10 warm-ups. Query and exact peak-memory values are averages of 20 instrumented warm requests.

The all-modules case enables every built-in module that existed for `1.1.0-rc.1`; IndexNow was added later and has not been measured. The local-environment tracking exclusion remains in force, so the benchmark measures plugin-side module overhead and does not fetch third-party scripts. Tracking is an explicit user-enabled exception to the no-frontend-script rule and its external network cost belongs to the selected provider and browser environment.

The exact staged archive passed `unzip -t`, contained all runtime files, excluded tests/development configuration, and smoke-activated in a clean latest-WordPress install with plugin version `1.1.0-rc.1` and API version `1.0`.
