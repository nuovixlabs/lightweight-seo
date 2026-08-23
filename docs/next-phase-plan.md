# Lightweight SEO Next-Phase Plan

Status: Implementation in progress; PR 1 lifecycle foundation complete locally

Planning date: 2026-08-24

Current release baseline: 1.0.3

Target compatibility: WordPress 6.0 or newer, PHP 7.4 or newer

## 1. Executive Decision

The next phase will turn Lightweight SEO into a stable, modular on-page SEO suite with a small default runtime and clear guidance for nontechnical site owners, developers, agencies, and publishers.

The product will use a hybrid architecture:

- One primary Lightweight SEO plugin contains the essential SEO engine and small optional modules.
- A maximum of one future Lightweight SEO Insights companion contains API-heavy dashboards, scheduled synchronization, and large report datasets.
- The core plugin remains functional without Insights.
- Insights integrates through a small, documented, versioned public API. It must never depend on private core classes or raw settings arrays.

The next phase includes:

- core correctness and lifecycle stabilization
- a true lazy-loaded module system
- improved on-page SEO guidance
- Redirects, Hreflang, Tracking, Local SEO, and experimental AI Discovery modules
- a safe migration path from 1.0.3
- real WordPress integration, compatibility, browser, and performance testing

Search Console, analytics dashboards, URL Inspection reporting, internal-link crawling, and other data-heavy analysis are deferred to the future Insights companion.

## 2. Product Promise

Within ten seconds, a user should understand:

> Lightweight SEO helps you publish correct titles, descriptions, canonical URLs, robots directives, social previews, schema, and sitemap signals without running a heavy SEO platform.

The plugin should help users improve on-page SEO through clear, deterministic guidance. It must not rely on speculative scores, fake ranking predictions, or busywork.

### Primary users

- Site owners who want safe defaults and a short setup path.
- Editors who need clear page-level recommendations.
- Developers and agencies who need predictable hooks and compatibility.
- Publishers who need dependable metadata without analytics or crawler overhead in the core.

### Product principles

1. Correct output before feature count.
2. No duplicate WordPress, theme, WooCommerce, or third-party SEO output.
3. Disabled means unloaded.
4. No remote API calls, recurring jobs, site-wide scans, or custom tables in the core.
5. No frontend database writes during ordinary page views.
6. Every warning must explain the user-visible problem and the corrective action.
7. Existing user metadata must survive upgrades.
8. Experimental features must be labeled honestly and never marketed as ranking guarantees.

## 3. Scope and Feature Decisions

### 3.1 Essential core, always available

The following capabilities remain part of the core SEO engine:

- document titles and title templates
- meta descriptions
- canonical URLs
- robots directives through WordPress core APIs
- Open Graph and X/Twitter metadata
- social image selection and validation
- per-post, term, author, and archive controls
- WordPress core sitemap filtering for indexable content
- Organization, WebSite, BreadcrumbList, ProfilePage, and correctly scoped Article schema
- safe-mode and compatibility detection
- migration tools that load only in the administrator
- public extension API and developer hooks

### 3.2 Built-in optional modules

These modules ship inside the primary plugin and are disabled or unloaded when not needed:

| Module | New-install default | Responsibility |
|---|---:|---|
| Redirects | Off | Manual exact redirects, safe automatic slug redirects, validation, and loop prevention |
| Hreflang | Off | Validated alternate-language relationships and multilingual integrations |
| Tracking | Off | GTM-first installation with optional direct GA4 or Meta Pixel output |
| Local SEO | Off | Validated LocalBusiness data and schema |
| AI Discovery | Off, Experimental | AI crawler policy controls, curated `llms.txt`, and deterministic readiness checks |

Migration behavior for existing users is defined in Section 10. New-install defaults do not silently override explicit 1.0.3 settings during an upgrade.

### 3.3 Removed or deferred from core

| Current area | Decision | Reason |
|---|---|---|
| Meta keywords output | Remove output and UI; preserve stored values for migration/export | No meaningful Google SEO value |
| Focus keyword field | Remove unless it powers a deterministic, useful editor check | A stored phrase alone does not improve SEO |
| Image, video, and news sitemap providers | Remove from core | Current providers do not emit the required specialized XML structures |
| Direct static-file X-Robots promise | Remove | WordPress cannot control files served directly by the web server or CDN |
| Product schema generation | Do not compete with WooCommerce; defer generic Product schema | Duplicate or stale offer data damages trust |
| Search Console integration | Move to future Insights | Credentials, remote APIs, cron, quotas, and dashboards do not belong in core |
| Internal-link and topic-cluster reports | Move to future Insights or Audit capability | Reliable analysis requires rendered-content crawling and indexed data |
| Site-wide Discover/image report | Replace with cheap editor-level checks | The existing report is synchronous and its thresholds are inaccurate |
| Continuous 404 logging | Off by default; exclude from initial Redirects scope | Frontend writes, bot noise, privacy, and retention require a separate data design |
| Generic arbitrary script insertion | Do not add | Security, consent, and CSP risks are outside the SEO core contract |

### 3.4 Explicitly not in this phase

- Search Console or GA4 dashboards
- URL Inspection jobs
- rank tracking
- AI citation or ranking prediction
- full-site internal-link crawling
- specialized publisher sitemaps
- raw analytics event storage
- a generic header/footer code editor
- custom database tables in the primary plugin
- automatic AI-generated content or mass FAQ generation

## 4. Target Architecture

### 4.1 Runtime shape

```text
WordPress request
       |
       v
Minimal plugin bootstrap
       |
       +--> register activation/deactivation hooks at file scope
       |
       v
Core boot on plugins_loaded
       |
       +--> core settings and API
       +--> module registry metadata
       +--> request-context loader
       |
       v
Load only what this request needs
       |
       +--> Frontend: title/meta/schema/sitemap filters
       +--> Editor: SEO fields, previews, deterministic checks
       +--> Admin SEO screen: settings and enabled module UI
       +--> Cron: no core job; only explicitly enabled extension jobs
       +--> REST: only the requested authenticated controller
       |
       v
Enabled module boot callbacks only
```

### 4.2 Module registry

The registry stores lightweight metadata only:

- stable module ID
- name and description
- default state
- experimental flag
- supported contexts
- dependency checks
- settings capability
- boot callback or service factory

Rendering the Modules screen must not require loading every module implementation.

Each module must declare its contexts, for example `frontend`, `admin`, `editor`, `rest`, or `cron`. Enabling a module does not permit it to boot in every context.

### 4.3 Definition of disabled

A disabled module:

- registers no runtime hooks
- loads no implementation classes
- performs no queries beyond reading the bounded module-state option
- schedules no events
- enqueues no assets
- emits no markup or headers
- makes no external requests
- performs no frontend writes

## 5. Core and Insights Extension Contract

Yes, the future Insights plugin will work on top of Lightweight SEO through documented hooks and a read-only public API.

### 5.1 Dependency boundary

```text
                         Public, versioned contract
                                    |
                                    v
+-----------------------+     +-------------------------+
| Lightweight SEO Core  |<----| Lightweight SEO Insights|
|                       |     |                         |
| Metadata resolution   |     | Search Console         |
| Indexability          |     | Analytics reporting     |
| Canonical resolution  |     | URL Inspection          |
| Sitemap locations     |     | Background sync         |
| Module registry       |     | Report storage          |
+-----------------------+     +-------------------------+
         ^                              |
         |                              v
         +------ no private access, no raw settings -----+
```

Insights may read normalized SEO facts from core. It may not modify core internals, read credential-bearing options, instantiate private services, or assume file paths.

### 5.2 Existing hooks to preserve and document

The current code already exposes a useful starting surface:

- `lightweight_seo_page_context`
- `lightweight_seo_document_title`
- `lightweight_seo_meta_tags`
- `lightweight_seo_link_tags`
- `lightweight_seo_before_meta_tags`
- `lightweight_seo_after_meta_tags`
- `lightweight_seo_schema_graph`
- `lightweight_seo_supported_post_types`
- `lightweight_seo_supported_taxonomies`

These hooks must receive documented argument shapes, return types, examples, and compatibility guarantees before being declared stable.

### 5.3 New stable lifecycle hooks

The implementation phase should add and document:

| Hook | Type | Purpose |
|---|---|---|
| `lightweight_seo_loaded` | Action | Announces that the public API is available |
| `lightweight_seo_register_modules` | Action | Allows official or third-party modules to register metadata and factories |
| `lightweight_seo_modules_registered` | Action | Announces the finalized module registry |
| `lightweight_seo_object_meta_updated` | Action | Announces a post, term, or user SEO metadata change without exposing raw requests |
| `lightweight_seo_is_indexable` | Filter | Adjusts the normalized indexability decision |
| `lightweight_seo_canonical_url` | Filter | Adjusts the resolved canonical URL |
| `lightweight_seo_sitemap_urls` | Filter | Exposes enabled, valid sitemap locations |

Hook names are part of the public contract once released. They require unit and integration tests and may not be renamed without a deprecation cycle.

### 5.4 Public API facade

Expose one stable accessor, such as `lightweight_seo_get_api()`, backed by a documented facade rather than the internal service container.

The first API version should support read-only methods equivalent to:

- get API and plugin versions
- get the normalized current-request context
- get a normalized context for a post, term, or user by ID
- determine whether an object is indexable
- resolve an object's canonical URL
- list supported object types
- list enabled, valid sitemap URLs
- inspect public module status

The facade must never return private keys, tracking configuration, raw settings arrays, access tokens, nonces, or mutable internal objects.

### 5.5 Version handshake and failure behavior

- Add a separate public API version constant.
- Insights declares its minimum compatible core and API versions.
- Because the target includes WordPress 6.0, Insights cannot rely only on the newer `Requires Plugins` header. It also needs a runtime dependency guard.
- If core is missing, inactive, or incompatible, Insights registers no jobs or reports and shows one administrator notice.
- Deactivating core while Insights remains active must produce no fatal error.
- A failed version check must not discard Insights data.
- Compatibility combinations require automated tests.

## 6. On-Page SEO Experience

The primary user outcome is understanding what to improve on the current page without learning SEO terminology first.

### 6.1 Overview and setup

Replace the single dense settings experience with a clear information hierarchy:

```text
SEO
|- Overview and setup checklist
|- Search appearance
|- Social appearance
|- Content types and indexation
|- Schema and identity
|- Modules
|- Tools and migration
`- Developer/API information
```

The overview should show a short, dismissible setup checklist, not a forced wizard:

- confirm site identity
- choose title and description defaults
- choose indexable content types
- select a social image
- review optional modules
- verify generated output on one page

### 6.2 Editor experience

The post editor should provide:

- search-result preview
- social preview
- title and description guidance
- canonical and robots controls behind an Advanced section
- social title, description, and image controls
- deterministic on-page checks with direct edit actions

Initial checks should be limited to facts the plugin can verify reliably:

- missing or empty SEO title
- missing description
- title/description length guidance, presented as guidance rather than a ranking rule
- no social image
- social image below the documented width recommendation
- page intentionally or accidentally noindexed
- custom canonical pointing to a different host or invalid URL
- conflicting schema or SEO plugin detected

Do not add a percentage SEO score, keyword-density score, readability grade, or fabricated ranking promise.

## 7. Core Correctness Workstream

This work must land before expanding modules.

### 7.1 Canonical and robots integration

- Replace standalone duplicate output with WordPress-native integration.
- Ensure one canonical URL is emitted.
- Merge directives through `wp_robots`.
- Define explicit behavior for singular, home, search, archive, paginated, attachment, password-protected, and 404 requests.
- Suppress canonical and generic social metadata on 404 responses.
- Add integration tests against real `wp_head` output.

### 7.2 Metadata and social output

- Stop using the site tagline as a universal page description fallback.
- Prefer a normalized excerpt/content fallback where appropriate, otherwise omit the description.
- Strip markup and normalize whitespace before emitting attribute values.
- Emit an appropriate Twitter card based on whether an image exists.
- Add social image alt, width, and height when available.
- Scope article metadata to actual article-like post types.
- Remove meta-keywords output.

### 7.3 Sitemap behavior

- Retain WordPress core sitemaps.
- Exclude noindexed objects and valid redirected content efficiently.
- Remove image, video, and news providers from core.
- Add endpoint and XML integration tests.
- Document any removed endpoint migration notice for users who submitted those URLs externally.

### 7.4 Schema behavior

- Emit a connected, internally consistent graph.
- Scope Article to posts configured as article content.
- Detect WooCommerce or other authoritative Product schema and avoid duplicates.
- Build full hierarchical breadcrumbs.
- Validate LocalBusiness only inside its enabled module.
- Test visible content against schema values.

### 7.5 Safe mode and compatibility

- Detect normal and network-active SEO plugins.
- Run detection after plugin loading is stable.
- Replace the current all-or-nothing decision with a feature conflict matrix.
- Show which outputs are suppressed and why.
- Support filters for additional compatibility providers.
- Test Yoast, Rank Math, AIOSEO, WooCommerce, multisite, and one multilingual integration in a real WordPress environment.

## 8. Optional Module Workstreams

### 8.1 Redirects

Initial scope:

- exact normalized source paths
- 301, 302, 307, and 308 status codes
- local and explicitly allowed external destinations
- automatic published-slug redirects
- validation before saving
- loop rejection
- chain detection with safe terminal resolution
- searchable management interface
- import/export with validation

Constraints:

- no regex in the first stable module
- no continuous 404 logging in the first release
- no unbounded frontend writes
- bounded option storage for the initial rule limit
- revisit a custom table only if the validated product requirement reaches thousands of rules

### 8.2 Hreflang

- validate BCP 47 language/region values and `x-default`
- avoid duplicate language targets
- support explicit object-to-object mappings
- integrate with WPML or Polylang through adapters when present
- verify reciprocity, canonical status, indexability, redirects, and HTTP success
- never create a mirrored URL only because two domains share a path pattern unless the administrator selects that mode

### 8.3 Tracking

- make Google Tag Manager the recommended primary strategy
- support direct GA4 and Meta Pixel only as explicit alternatives
- warn about combinations that can duplicate events
- validate provider IDs
- support logged-in-role and environment exclusions
- expose consent and CSP nonce filters
- verify `wp_body_open` support for GTM and show a diagnostic when the theme is incompatible
- do not provide arbitrary JavaScript input
- load no tracking code until the module is enabled and configured

### 8.4 Local SEO

- validate business type, name, address, country, phone, price range, coordinates, and opening hours
- reject invalid latitude/longitude ranges
- keep Organization and LocalBusiness identities consistent
- use a dedicated business image/logo rather than silently reusing unrelated social media imagery
- support one location in the first release
- defer multi-location pages to a later product decision

### 8.5 AI Discovery, experimental

Initial scope:

- separate AI search visibility controls from model-training controls
- support current documented crawler tokens through a maintainable registry
- integrate with WordPress virtual `robots.txt` without overwriting a physical file
- detect and report physical-file or third-party conflicts
- generate an optional curated `/llms.txt` endpoint
- allow manual selection of authoritative public pages
- exclude drafts, private, password-protected, noindexed, redirected, or invalid URLs
- emit the correct plain-text/Markdown content type and cache headers
- provide deterministic readiness checks based on crawlability, indexability, snippets, canonicals, identity, and visible structured data

Product language:

- label `llms.txt` as an experimental community proposal
- state that Google Search ignores it for ranking
- make no AI ranking, citation, or inclusion guarantees
- do not generate `llms-full.txt` or duplicate the entire site as Markdown in this phase

## 9. Data and Performance Contract

### 9.1 Core storage

The primary plugin creates no custom database tables.

Use:

- post meta for post/page SEO fields
- term meta for taxonomy SEO fields
- user meta for author SEO fields
- one bounded core settings option
- one bounded module-state option
- separate non-autoloaded module configuration where necessary
- transients or object cache only for bounded, reproducible output

Do not store unbounded logs, analytics history, link graphs, or API response histories in serialized options.

### 9.2 Performance budgets

The stable release must meet these observable constraints:

- no core remote requests
- no recurring core cron event
- no frontend write on an ordinary 200, redirect-free page view
- no frontend CSS or JavaScript from core
- no site-wide content query on normal frontend or settings-page loads
- disabled modules load no implementation files or hooks
- settings are read once per request and cached in memory
- 100 warm frontend requests show no more than a 5% median response-time regression versus WordPress with the plugin inactive on the reference environment
- the release package records file count, ZIP size, warm memory delta, query delta, and response-time benchmark as regression baselines

Third-party tracking scripts are an explicit user-enabled exception to the no-frontend-script rule and must be measured separately from core.

## 10. Upgrade, Deactivation, and Uninstall Plan

### 10.1 Upgrade from 1.0.3

Implement idempotent option-schema migrations with a stored schema version.

Migration rules:

- preserve all existing post, term, user, canonical, robots, and social metadata
- map old settings into core and module-specific configuration
- enable Tracking for an upgrade only when at least one valid tracking ID exists
- preserve explicit Hreflang and LocalBusiness enablement
- preserve existing redirect behavior, while separating 404 logging and showing its retention implications
- keep stored focus-keyword and meta-keyword values available for export, but stop frontend output
- remove cached internal-link and image-audit reports after their screens are retired
- unschedule Search Console synchronization and delete access-token and report caches
- do not silently move or reuse Search Console private keys; provide an explicit export/delete transition and require reconnecting through the future Insights security model
- display a one-time upgrade summary listing behavior changes and removed specialized sitemap endpoints

### 10.2 Deactivation

- unschedule all plugin-owned events
- flush rewrite rules only when module endpoints or redirect rewrites require it
- retain persistent settings and SEO metadata
- make deactivation safe on single-site, multisite, and network activation

### 10.3 Uninstall

- always remove ephemeral caches, tokens, generated reports, cron events, and temporary state
- add an explicit administrator setting controlling whether persistent SEO configuration and object metadata are deleted on uninstall
- enumerate every owned option and meta key in one data registry used by migrations and uninstall
- test both preserve-data and delete-all modes on multisite

## 11. Testing Strategy

The current stub-based unit suite remains useful for fast logic tests, but it is not a release gate by itself.

### 11.1 Test layers

| Layer | Purpose |
|---|---|
| Unit | Sanitizers, normalizers, templates, module decisions, graph construction |
| WordPress integration | Real hooks, Settings API, metadata, head output, sitemaps, rewrites, cron, activation, and uninstall |
| Compatibility integration | WooCommerce, multisite, major SEO plugins, and multilingual adapters |
| Browser smoke | Setup, module toggles, editor fields, previews, notices, keyboard navigation, and empty/error states |
| Performance | Warm request time, memory, queries, option sizes, module-disabled comparison |
| Security | Capabilities, nonces, stored/output escaping, redirect validation, credential handling, and endpoint access |

### 11.2 CI matrix

At minimum:

- WordPress 6.0 with PHP 7.4
- latest supported WordPress with PHP 7.4
- latest supported WordPress with PHP 8.2
- latest supported WordPress with PHP 8.3 or the current project maximum
- latest WordPress multisite
- WooCommerce compatibility job

Continue PHP lint, PHPCS, and PHPUnit. Add real WordPress tests and a release-package smoke activation test.

### 11.3 Required release scenarios

- clean installation with recommended defaults
- upgrade from a representative 1.0.3 database fixture
- activate and deactivate every module repeatedly
- core missing/incompatible while Insights is active
- another SEO plugin active locally and network-wide
- WooCommerce Product page with no duplicate Product graph
- physical and virtual robots.txt behavior
- `llms.txt` conflict, cache, privacy, and invalid-link cases
- redirect loop, chain, malformed destination, and high-rule boundary cases
- complete uninstall in preserve-data and delete-all modes

## 12. Delivery Sequence

Each pull request should be independently testable and reversible.

### PR 1: Compatibility baseline and lifecycle

Implementation status (2026-08-24): Complete locally; pending pull-request review and CI.

- add WordPress/PHP requirement headers
- move lifecycle hook registration to safe file scope
- add deactivation and complete data ownership registry
- add option schema version and idempotent migration harness
- add real WordPress test bootstrap and compatibility matrix foundation

Exit gate: activation, deactivation, upgrade fixture, and uninstall behavior pass in real WordPress.

Verification evidence:

- Fast suite: 68 tests and 259 assertions pass.
- Real WordPress 6.0 single-site suite: 7 tests and 18 assertions pass, with the multisite-only case skipped as intended.
- Real WordPress 6.0 multisite suite: all 7 tests and 19 assertions pass, including network activation.
- PHPCS, PHP syntax checks, Composer validation, and dependency security audit pass.
- CI now covers WordPress 6.0/PHP 7.4, latest WordPress on PHP 7.4/8.2/8.3, and latest WordPress multisite.

### PR 2: Core output correctness

Implementation status (2026-08-24): Complete locally; pending pull-request review and CI.

- canonical and `wp_robots` integration
- metadata fallback corrections
- 404 behavior
- social image/card corrections
- schema scoping and WooCommerce conflict prevention
- standard sitemap filtering only

Exit gate: exactly one canonical and robots output, valid schema graph, valid core sitemap XML, and compatibility smoke tests.

Verification evidence:

- Fast suite: 72 tests and 276 assertions pass.
- Real WordPress 6.0 single-site suite: 12 tests and 39 assertions pass, with the multisite-only lifecycle case skipped as intended.
- Real WordPress 6.0 multisite suite: all 12 tests and 40 assertions pass.
- Real `wp_head` output contains one WordPress-native canonical and one coherent robots tag; 404 output suppresses canonical, social, and schema markup.
- WordPress core sitemap integration excludes noindexed posts, renders parseable XML, and registers no image, video, or news providers.

### PR 3: Public API and lazy module registry

- public facade and API version
- stable lifecycle hooks
- module registry and context-aware loader
- split bounded core/module settings
- dependency and version handshake prototype for Insights

Exit gate: disabled modules register no implementation hooks/classes, and a fixture extension can consume the API without private access.

### PR 4: On-page SEO and admin information architecture

- overview/setup checklist
- reorganized settings
- editor search/social previews
- deterministic checks
- accessible navigation, empty, error, and success states

Exit gate: a new user can complete essential setup and correct a page-level issue without documentation.

### PR 5: Redirects and Hreflang modules

- rebuild both modules on the registry
- validation, conflict handling, and module-specific tests
- retire continuous 404 logging from the initial path

Exit gate: modules have zero disabled runtime and pass redirect/hreflang integration cases.

### PR 6: Tracking and Local SEO modules

- GTM-first tracking design
- consent, exclusion, duplicate, and CSP extension points
- validated single-location LocalBusiness output

Exit gate: no scripts when disabled, no duplicate configured provider path, and schema validation passes.

### PR 7: AI Discovery module

- crawler-policy registry
- virtual robots integration and physical-file diagnostics
- curated `llms.txt`
- deterministic readiness checks and experimental labeling

Exit gate: no ranking claims, no private URLs, and correct conflict/cache behavior.

### PR 8: Migration and removal cleanup

- remove retired services and settings UI
- preserve/export legacy metadata
- stop Search Console jobs and clean ephemeral data
- upgrade summary and deprecated-hook policy
- complete importer preview, batching, and rollback design

Exit gate: the 1.0.3 fixture upgrades without losing supported SEO metadata and without leaving scheduled jobs.

### PR 9: Release hardening

- complete compatibility, browser, performance, security, and packaging checks
- update README, QA checklist, developer hook documentation, and release notes
- verify translation readiness
- publish beta/RC upgrade instructions

Exit gate: all Definition of Done items pass.

## 13. Future Insights Companion Boundary

Insights is planned, not implemented in this phase.

Its intended responsibilities are:

- Search Console authentication and synchronization
- GA4 reporting authentication and synchronization
- URL Inspection jobs
- Google and Bing AI-visibility reporting when stable APIs permit it
- background jobs, retries, locks, and synchronization status
- large or historical report storage
- optional site-audit and internal-link datasets

Insights owns its options, secrets, cron hooks, schema version, retention, and any justified custom tables. Core owns none of those concerns.

Most users install only Lightweight SEO. Advanced users may install Insights later. No additional family of small companion plugins is planned.

The extension contract must remain licensing-neutral. A future free/pro decision must not require changing core hooks or data ownership.

## 14. Security and Privacy Requirements

- require capabilities as well as nonces for every mutation
- use object-specific edit capabilities for post, term, and user metadata
- validate before sanitizing where invalid input must be rejected
- escape at output
- never expose raw settings or secrets through hooks, REST, JavaScript localization, logs, or diagnostics
- do not accept arbitrary executable tracking code
- treat AI training access as an explicit publisher decision
- store no 404 referrer or analytics history in core
- ensure redirect destinations cannot create unsafe schemes or open-loop behavior
- document every external request in Insights before implementation

## 15. Documentation Plan

This file is the product and engineering source of truth for the next phase.

- Root `README.md` continues to describe the currently shipped release until code lands.
- `docs/qa-checklist.md` remains the current-release manual checklist and must be rewritten alongside implementation.
- Developer API and hook documentation will be added when PR 3 stabilizes argument and return types.
- Each implementation PR updates this plan's status and links its verification evidence.
- Removed behavior must be listed in upgrade and release notes before release.

The former `docs/features.md` mixed shipped behavior, aspirations, and several capabilities that did not meet their external contracts. Its unique feature inventory has been consolidated into Sections 3, 7, and 8 of this plan, and the superseded file is removed to avoid two competing sources of truth.

## 16. Definition of Done

The next phase is complete only when:

- core output is correct in a real WordPress environment
- one canonical and one coherent robots result are produced
- specialized sitemap claims are removed from core
- essential schema is valid and does not compete with WooCommerce
- all five optional modules are lazy-loaded and default appropriately
- a fixture extension proves the public API and version handshake
- current 1.0.3 metadata survives the tested upgrade path
- core creates no custom tables, recurring jobs, remote requests, site-wide scans, or ordinary frontend writes
- the compatibility and performance matrices pass
- deactivation and uninstall cleanup are complete
- onboarding and editor guidance are usable by keyboard and understandable without SEO expertise
- README, QA, API, migration, and release documentation match shipped behavior
- no experimental AI feature is described as a ranking guarantee

## 17. Approved Decisions

- Primary users are a combination of site owners, editors, developers, agencies, and publishers.
- The product must teach users how to improve on-page SEO through clear, actionable guidance.
- The next phase includes core stabilization, module architecture, Tracking, Redirects, Hreflang, Local SEO, and AI Discovery.
- The product uses one primary plugin and at most one future Insights companion.
- WordPress 6.0 and PHP 7.4 are the minimum compatibility targets.
- Existing 1.0.3 SEO metadata is preserved during migration.
- Overlapping and outdated documentation may be consolidated, while historical release notes and changelogs are preserved.
