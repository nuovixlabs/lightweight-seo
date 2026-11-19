# Architecture

How Lightweight SEO is put together, and the rules every change has to keep. For what the plugin does, see the [README](../README.md). For the extension contract, see [Developer API](developer-api.md). For step-by-step procedures, see [Recipes](recipes.md).

## Product boundary

Lightweight SEO prints correct titles, descriptions, canonical URLs, robots directives, social tags, schema, and sitemap signals. It also checks whether AI crawlers and agents can reach and read the site, and publishes agent-discovery output, such as `llms.txt` or crawler rules, only for things the site really offers. It never publishes a document that describes a service the site does not run. It is not an SEO platform.

Principles, in priority order:

1. Correct output before feature count.
2. No duplicate WordPress, theme, WooCommerce, or third-party SEO output.
3. Disabled means unloaded.
4. Core makes no remote request, schedules no recurring job, runs no site-wide scan, and creates no custom table.
5. An ordinary page view writes nothing to the database.
6. Every warning explains the problem the user can see and what to do about it.
7. Existing user metadata survives upgrades.
8. Experimental features are labelled as such and never described as a ranking guarantee.

Guidance is deterministic: a check states a fact the plugin can verify. There is no percentage score, keyword-density score, readability grade, or ranking prediction, and no AI readiness score or level. An AI readiness check has one of four statuses: passed, needs attention, off by choice, or not applicable.

A check that tests an address as it is served, such as `/robots.txt`, runs in the administrator's browser, the way "Inspect a page" does. PHP requests nothing.

These stay out of this plugin. Most belong to a possible future Insights companion (see [below](#insights-companion)):

- Search Console or GA4 dashboards, URL Inspection jobs, rank tracking
- internal-link crawling, site-wide audits, historical analytics, continuous 404 logging
- image, video, and news sitemap providers
- Product schema (WooCommerce or another owner provides it)
- arbitrary script or header/footer code input
- AI-generated content, and any AI citation or ranking prediction
- an AI readiness score, level, or percentage

The one exception to "no remote request" is the IndexNow module, which is off by default and sends changed addresses to `api.indexnow.org`. Tracking prints a provider's script only after the user enables it and enters an ID.

Rule 4 is enforced by `tests/LightweightSEOBoundariesTest.php`. A change that needs a new exception must change that test, and say why in the pull request.

## Request flow

```text
lightweight-seo.php (file scope)
  constants, six always-loaded classes, activation and deactivation hooks
        |
        v
init, priority 0: run_lightweight_seo()
  Lightweight_SEO_Migrator::maybe_migrate()
  Lightweight_SEO::run()
        |
        +--> is_admin(): settings screen, editor panel, post list, importer
        +--> frontend and sitemap services
        +--> public API facade ($GLOBALS['lightweight_seo_api'])
        +--> module registry: register metadata, then load this context
        +--> abilities, only when WordPress collects them (6.9+)
        +--> do_action( 'lightweight_seo_loaded' )
  under WP-CLI: Lightweight_SEO_CLI::register()
```

The version is read from the plugin header, so `Version:` in `lightweight-seo.php` is the only place it is written.

Administration classes are loaded only when `is_admin()` is true. WP-CLI is not an administration request, so a command or `wp eval` that needs the importer or the settings classes must `require_once` them.

## Modules

A module is registered as metadata only. Its class file is loaded when the module is enabled and the request is in one of its contexts.

| ID | Switch setting | Contexts | Class file |
|---|---|---|---|
| `redirects` | `enable_redirects` | frontend, admin, editor, rest | `class-lightweight-seo-redirects-service.php` |
| `hreflang` | `enable_hreflang_output` | frontend | `class-lightweight-seo-hreflang-service.php` |
| `tracking` | `enable_tracking` | frontend | `class-lightweight-seo-tracking-service.php` |
| `local-seo` | `enable_local_business_schema` | frontend | `class-lightweight-seo-local-seo-module.php` |
| `ai` | `enable_ai_discovery` | frontend, admin | `class-lightweight-seo-ai-discovery-module.php` |
| `indexnow` | `enable_indexnow` | frontend, admin, editor, rest, cron, cli | `class-lightweight-seo-indexnow-module.php` |

Every module is off on a new install.

The context is resolved once per request in `Lightweight_SEO::get_request_context()`: `cron`, `rest`, `editor` (`post.php` and `post-new.php`), `admin`, or `frontend`. Admin, editor, and cron modules load at once. REST modules load on `rest_api_init`, frontend modules on `wp`, and `cli` modules under WP-CLI.

Module state is the option `lightweight_seo_modules`, a map of module ID to boolean. It follows the switch settings: `Lightweight_SEO_Module_State::derive_from_legacy_settings()` recomputes it whenever the settings option is saved. Extensions register their own modules on `lightweight_seo_register_modules`.

A disabled module:

- loads no implementation class and registers no hook
- runs no query beyond reading the module-state option
- schedules no event and enqueues no asset
- prints no markup or header
- makes no external request and no frontend write

Rendering the Modules tab must not load a module's implementation either.

A switch inside a module follows the same rule. The AI Discovery module's "Markdown on request" switch adds one late `template_redirect` callback only while it is on; that callback reads the `Accept` header, and the converter (`class-lightweight-seo-markdown-converter.php`) and the document builder (`class-lightweight-seo-markdown-response.php`) are loaded only when a request is answered in Markdown. The answer is built for that request and stored nowhere.

## Data

The plugin stores:

- post, term, and user meta for per-object SEO fields
- one settings option, `lightweight_seo_settings`
- one module-state option, `lightweight_seo_modules`
- the schema version, `lightweight_seo_schema_version`
- a few non-autoloaded options for bounded working state, such as the import position or the settings backup

It never stores unbounded logs, analytics history, link graphs, or API responses.

`Lightweight_SEO_Data_Registry` lists every option, meta key, and cron hook the plugin owns. `uninstall.php` reads it, so anything missing from the registry is left behind on uninstall. The boundaries test fails when the plugin writes an option the registry does not list. Uninstall always removes ephemeral data, and removes settings and object meta only when the administrator chose "delete data on uninstall".

Settings changes that need old values rewritten go through `Lightweight_SEO_Migrator`: one idempotent step per schema version, and `LIGHTWEIGHT_SEO_SCHEMA_VERSION` names the latest. A new setting with a safe default needs no migration.

## Performance budget

- no frontend CSS or JavaScript from core
- no site-wide content query on a frontend or settings-screen request
- settings are read once per request
- 100 warm frontend requests show at most a 5% median response-time regression against WordPress with the plugin inactive
- every release records package file count, ZIP size, memory delta, and query delta in [Release Baselines](release-baselines.md)

A file that is new in a release should not be loaded on a visitor request unless a visitor needs it. The baselines list, for each added file, when it is loaded.

## Safe mode

When Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, or Slim SEO is active, `Lightweight_SEO_Compatibility_Service` suppresses the overlapping output: title, meta, robots, canonical, and schema. Modules that do not overlap stay available, and administrators see a notice on the dashboard, Plugins, and SEO screens. Hreflang output also steps aside when a multilingual provider declares ownership.

## Security and privacy

- Every mutation requires a capability and a nonce.
- Post, term, and user metadata saves use the object-specific edit capability.
- Input that must be rejected is validated before it is sanitized. Output is escaped where it is printed.
- Raw settings and secrets never reach hooks, REST, localized JavaScript, logs, or diagnostics. The public API returns normalized facts only.
- Redirect targets cannot use unsafe schemes or form loops.
- Core stores no 404 referrer and no analytics history.
- AI training access is an explicit choice and defaults to off.

`composer phpcs` enforces escaping, nonce, input-sanitization, prepared-SQL, and text-domain rules on shipped code.

## Compatibility

- WordPress 6.0 or newer, PHP 7.4 or newer. Shipped code must parse on PHP 7.4 and must not call a WordPress function newer than 6.0 without a guard.
- Features of newer WordPress versions are added behind a check and are never required. Abilities and command palette entries need 6.9.
- Single site and multisite, root and subdirectory installs.

## Insights companion

Insights is planned, not built. It would own Search Console and GA4 connections, URL Inspection jobs, background synchronization, and report storage, together with its own options, secrets, cron hooks, and any custom table.

It reads normalized SEO facts from core through `lightweight_seo_get_api()` and the documented hooks. It never reads raw settings, private classes, or file paths. Core must work without it, and deactivating core while Insights is active must not cause a fatal error. `tests/fixtures/class-lightweight-seo-insights-fixture.php` proves the version handshake.

## Tests

| Layer | Where | Covers |
|---|---|---|
| Unit | `tests/*.php` | Sanitizers, templates, module decisions, graph construction, admin rendering, and HTML to Markdown conversion against a table of fixtures. Runs against the WordPress stubs in `tests/bootstrap.php`, in about a second. |
| JavaScript | `tests/js/` | Title variable and shortening rules shared by the previews, how a served `robots.txt` is read, and how content is counted. `tests/fixtures/content-facts.json` holds cases the PHP twin counts the same way. |
| Integration | `tests/integration/` | Real WordPress: hooks, head output, sitemaps, activation, uninstall, importers, WooCommerce, and Markdown answers to real queries. |
| By hand | [QA Checklist](qa-checklist.md) | Browser behavior, accessibility, other SEO plugins, performance. |

The unit suite is fast because WordPress is stubbed. A function the code newly calls needs a stub in `tests/bootstrap.php`, and behavior that depends on real WordPress needs an integration test as well.

CI runs the unit suite on PHP 7.4 and 8.3, and the integration suite on WordPress 6.0 and latest across PHP 7.4, 8.2, and 8.3, plus multisite and WooCommerce.
