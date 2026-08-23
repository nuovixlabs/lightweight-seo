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

Reference environment: local WordPress 6.0, Twenty Twenty-Two, PHP 8.5, MySQL 8, loopback HTTP, no persistent object cache. Each response-time result is the median of 100 sequential warm homepage requests after 10 warm-ups. Query and exact peak-memory values are averages of 20 instrumented warm requests.

The all-modules case enables every built-in module. The local-environment tracking exclusion remains in force, so the benchmark measures plugin-side module overhead and does not fetch third-party scripts. Tracking is an explicit user-enabled exception to the no-frontend-script rule and its external network cost belongs to the selected provider and browser environment.

The exact staged archive passed `unzip -t`, contained all runtime files, excluded tests/development configuration, and smoke-activated in a clean latest-WordPress install with plugin version `1.1.0-rc.1` and API version `1.0`.
