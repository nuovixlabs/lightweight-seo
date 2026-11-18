# Recipes

Step-by-step procedures for the changes that come up most. Each list names every place a change of that kind touches. The rules behind them are in [Architecture](architecture.md).

## Commands

| Command | What it does |
|---|---|
| `composer check` | Every gate that needs no network: Composer validation, PHP syntax, PHPCS, unit tests, JavaScript tests, translation template freshness, whitespace. About 20 seconds. |
| `composer test` | Unit tests only. About a second. |
| `composer phpcs` | WordPress coding standards, security sniffs, and PHP 7.4 compatibility. |
| `composer make-pot` | Regenerates `languages/lightweight-seo.pot`. Leaves the file alone when no string changed. Needs WP-CLI. |
| `composer audit` | Checks dependencies for known vulnerabilities. Needs network. |
| `composer test:integration` | Real WordPress tests. Needs MySQL and `tests/bin/install-wp-tests.sh`; CI runs them on every push. |
| `bin/build-zip` | Builds the release ZIP the way the release workflow does and prints its file count and size. |
| `bin/release-prep <version>` | Bumps the version, regenerates the template, dates the changelog, and builds the ZIP. |
| `bin/dev-site` | A throwaway WordPress for this checkout. See [Test by hand](#test-by-hand). |

Run `composer check` before every commit. Run `composer make-pot` whenever a translatable string, or a line above one, changed; `composer check` fails when the template is stale.

CI repeats the syntax check and the unit tests on PHP 7.4. To run them on 7.4 before pushing, with Docker:

```bash
docker run --rm -v "$PWD":/app -w /app php:7.4-cli sh -c 'bin/lint-php && vendor/bin/phpunit --configuration phpunit.xml.dist'
```

## Add a setting

1. Add the key and its default to `Lightweight_SEO_Settings::get_defaults()`. Choose a default that keeps existing sites behaving as before, so no migration is needed.
2. Sanitize it in the settings callback in `class-lightweight-seo-admin-settings.php`.
3. Register, render, and place the field as described under [Adding to a Settings Tab](design-system.md#adding-to-a-settings-tab).
4. A value that must stay on its own site (a key, a credential, an ID of a local object) goes into `Lightweight_SEO_Settings_Transfer::get_local_keys()`, so it is neither exported nor imported.
5. Test the default, the sanitizer, and the output it changes.
6. Add the setting to `CHANGELOG.md`, and to the release notes when a user will notice it.

## Add a module

Use the IndexNow module as the model: `git show c7e58ff --stat`.

Code:

1. Create `includes/class-lightweight-seo-<id>-module.php`. The class registers its own hooks in its constructor.
2. In `includes/class-lightweight-seo.php`, add the metadata to `register_builtin_modules()` and a case to `load_builtin_module()`. List only the contexts the module must run in, with a comment that says why each is needed.
3. In `Lightweight_SEO_Settings::get_defaults()`, add `enable_<id>` with the default `'0'`, and the module's own settings.
4. In `Lightweight_SEO_Module_State`, add the ID to `derive_from_legacy_settings()` and to `normalize()`.
5. In `Lightweight_SEO_CLI`, add the ID to `MODULE_SWITCHES` and to the two `<module>` help texts.
6. Settings screen: a section and sanitizing in `class-lightweight-seo-admin-settings.php`, the field renderers in `class-lightweight-seo-admin-module-fields.php`, and a card with `'module' => '<id>'` plus a label in `get_module_labels()` in `class-lightweight-seo-admin-page.php`.
7. A new option goes into `Lightweight_SEO_Data_Registry`. A setting that must not leave the site goes into `Lightweight_SEO_Settings_Transfer::get_local_keys()`.

Tests:

8. Add `tests/LightweightSEO<Name>ModuleTest.php`.
9. Add the ID to the module lists in `LightweightSEOLifecycleTest`, `LightweightSEOSettingsTransferTest`, `LightweightSEOCLITest`, `tests/integration/LightweightSEOActivationTest.php`, `tests/integration/LightweightSEOPublicAPIIntegrationTest.php`, and the class list at the end of `tests/integration/bootstrap.php`.
10. A module that makes a remote request, schedules an event, or creates a table breaks a product rule. Only add it after that exception is agreed, then extend the allow-list in `tests/LightweightSEOBoundariesTest.php`.

Docs:

11. `README.md`: the module list and its count, and "Deliberate boundaries" if the module is an exception to one.
12. `docs/architecture.md`: the module table.
13. `docs/developer-api.md`: any hook the module adds.
14. `docs/qa-checklist.md`: checks under "Modules", and the module count under "Installation and lifecycle".
15. `CHANGELOG.md`, the release notes, and the new file in `docs/release-baselines.md` with when it is loaded.

`tests/LightweightSEOBoundariesTest.php` fails while the module lists in steps 2, 4, and 5 disagree, while a switch from step 3 is missing or not off by default, and while an option from step 7 is missing from the registry.

## Add an import source

Use the SEOPress, The SEO Framework, and Slim SEO commit as the model: `git show 78d3650 --stat`.

1. Take the field names from the source plugin's published code, not from memory.
2. In `Lightweight_SEO_Importer_Service`, add the ID to `SOURCES`, a case to `map_source_meta()`, and a case to `convert_template_variables()`. A variable with no Lightweight SEO equivalent leaves its field for review; it is never imported as literal text.
3. Add the `<option>` to the source select in `class-lightweight-seo-admin-tools-fields.php`.
4. Add unit tests for every mapped field and every converted variable in `tests/LightweightSEOImporterServiceTest.php`.
5. Compare with the real plugin on a [throwaway site](#test-by-hand): let it print a page from its own fields, import, deactivate it, and compare the page head.
6. Add a row to the table in `docs/migration.md`, a check to `docs/qa-checklist.md`, and the source to the safe-mode list if the plugin prints SEO output.

## Change stored data

A new setting with a safe default needs none of this. Use a migration only when stored values must be rewritten.

1. Raise `LIGHTWEIGHT_SEO_SCHEMA_VERSION` in `lightweight-seo.php`. The unit tests read it from there.
2. Add `migrate_to_version_<n>()` to `Lightweight_SEO_Migrator` and call it from `maybe_migrate()` behind `$stored_version < <n>`.
3. The step must be idempotent, must leave a new install alone, and must not delete user metadata.
4. A new option or meta key goes into `Lightweight_SEO_Data_Registry`: persistent when the user would miss it, ephemeral otherwise.
5. Test the step from an old fixture, from a new install, and run twice.
6. Describe the change in `docs/migration.md`, `CHANGELOG.md`, and the release notes.

## Add a file to the plugin

- Load it only where it is needed. A file a visitor request does not need must not be loaded on one.
- A file under `admin/` or `includes/` ships automatically. A new top-level file or folder that is not part of the plugin needs an `export-ignore` line in `.gitattributes`; `bin/build-zip` fails when anything unexpected reaches the ZIP.
- List a new shipped file in `docs/release-baselines.md` with when it is loaded.

## Call a WordPress function the plugin has not used before

1. Check the WordPress version that introduced it. Anything newer than 6.0 needs a `function_exists()` guard and a sensible result without it.
2. Add a stub to `tests/bootstrap.php`, or the unit suite fails with an undefined function.
3. When the behavior depends on what real WordPress does, add an integration test under `tests/integration/`.

## Cut a release

A tag with a hyphen, such as `v1.1.0-rc.7`, is published as a prerelease.

1. Start from an up-to-date `main` in a worktree. `CHANGELOG.md` must list every change under "Unreleased".
2. Run `bin/release-prep <version>`, for example `bin/release-prep 1.1.0-rc.7`. It:
   - sets `Version:` in `lightweight-seo.php`, the only place the version is written
   - regenerates the translation template
   - moves "Unreleased" in `CHANGELOG.md` under the new version and today's date
   - builds the ZIP from the working tree and prints its file count and size
3. Add a section for the version to `docs/release-baselines.md` with the printed numbers. Measure timing, memory, and queries again when the release can change a visitor request; otherwise say why they were not measured again.
4. Run `composer check` and `composer audit`.
5. Work through `docs/qa-checklist.md` for everything the release touches, and record what was checked by hand in the baselines.
6. Commit as `🚀 RELEASE: Prepare <version> release candidate` (or `Prepare <version>` for a final release), open a pull request, and merge it once CI passes.
7. Tag the merge commit `v<version>` and push the tag. The release workflow runs the quality checks, builds and verifies the ZIP, smoke-activates it, and publishes it.

## Test by hand

### A throwaway site

`bin/dev-site` builds a WordPress in `.cache/dev-site/` of the current checkout, on SQLite, with the plugin linked in and active. It needs WP-CLI and downloads WordPress and the SQLite Database Integration plugin.

```bash
bin/dev-site up                      # latest WordPress
bin/dev-site up --wp=6.8.3           # an older WordPress
bin/dev-site up --multisite          # a subdirectory network
bin/dev-site up --copy               # copy the plugin in instead of linking it
bin/dev-site serve                   # http://127.0.0.1:8881, runs until stopped
bin/dev-site wp plugin list          # any WP-CLI command against the site
bin/dev-site info                    # address, folder, and where the admin password is
bin/dev-site down                    # removes the site
```

- **Never uninstall or delete the linked plugin** on a dev site. WordPress follows the link and deletes the repository's files. To test uninstall, use `bin/dev-site up --copy`. `bin/dev-site wp` refuses those two commands while the plugin is linked; the Plugins screen does not.
- The site reports its environment as `local` and writes notices to `wp-content/debug.log` instead of the page.
- The oldest WordPress it can build is the oldest one the SQLite plugin supports. WordPress 6.0 is covered by the integration suite in CI.
- The built-in server does not route the sub-sites of a subdirectory network, so their pages cannot be requested. Network activation and per-site settings can still be checked with WP-CLI.
- Each worktree gets its own site, so two sessions do not share one. Give a second site its own port when building it: `bin/dev-site up --port=8882`.

### Things that mislead

- **WP-CLI is not an administration request.** The importer and the settings classes are not loaded under it; `require_once` them in `wp eval` when a check needs them.
- **The browser caches the plugin's CSS and JavaScript**, because the `?ver=` query does not change while developing. Reload those files without the cache before judging a visual change.
- **`wp_get_environment_type()` can say `production` on `localhost`.** Before switching on anything that sends a request, such as IndexNow, confirm nothing leaves the machine with a `pre_http_request` filter that records and blocks.
- **Saving a post changes it.** Editor saves, Quick Edit, and Bulk Edit set `post_modified` and may add a revision. On a site whose content matters, record the SEO meta, both modified dates, and the revision count first, and put them back afterwards.
- **WP-CLI does not run the settings sanitizer.** `wp option get <name> --format=json > file` and `wp option update <name> --format=json < file` back up and restore an option byte for byte. To test sanitizing, save through the settings screen.
- **Hidden list columns are per user.** They live in the user meta `manageedit-<post type>columnshidden`.
- **To see a settings screen without JavaScript**, load it in an `<iframe sandbox="allow-same-origin allow-forms">` inside the administration. The frame runs no script, and its forms still submit.
