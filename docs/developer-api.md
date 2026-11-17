# Lightweight SEO Developer API

Lightweight SEO exposes a read-only facade for extensions. Consumers must not read the `lightweight_seo_settings` option, instantiate classes from `includes/`, or rely on plugin file paths.

## Version handshake

The core plugin defines `LIGHTWEIGHT_SEO_API_VERSION` separately from its release version. An extension supporting WordPress 6.0 should guard its bootstrap at runtime:

```php
add_action(
	'lightweight_seo_loaded',
	static function ( $api ) {
		if ( ! $api->is_compatible( '1.1.0-rc.2', '1.0' ) ) {
			return;
		}

		// Register the extension. Keep jobs and reports disabled before this point.
	}
);
```

If `lightweight_seo_get_api()` does not exist or returns `null`, core is missing, inactive, or has not loaded. Core finishes loading on `init` at priority 0; consumers should rely on `lightweight_seo_loaded` rather than a WordPress hook priority. Extensions should register no jobs in that state and may show an administrator notice. A failed check must not delete extension data.

## Facade methods

`lightweight_seo_get_api()` returns `Lightweight_SEO_API|null`. The version 1.0 facade supports:

- `get_api_version()` and `get_plugin_version()`
- `is_compatible( $minimum_plugin_version, $minimum_api_version )`
- `get_current_context()`
- `get_object_context( $object_type, $object_id )`, where type is `post`, `term`, or `user`
- `is_indexable( $object_type, $object_id )`
- `get_canonical_url( $object_type, $object_id )`
- `get_supported_object_types()`
- `get_sitemap_urls()`
- `get_modules()`

Returned values are copies of normalized public data. They never contain settings arrays, credentials, tokens, nonces, module factories, or mutable internal services.

`is_indexable()` and the `robots` and `indexable` values of `get_object_context()` follow the same rule as the page output: the object's own choice to show or hide wins, and without one the object follows the search visibility of its post type, taxonomy, or, for a user, author archives.

`get_object_context()` returns the `title` and `description` with their variables filled in: `%title%` is the object's own title or name, `%category%` and `%author%` follow the object, and `%page%` is empty because an object is described by its first page. A title without a custom SEO title is still the object's own title, not the title format of its content type.

For posts, `get_object_context()` reports unpublished and password-protected content as not indexable and returns no derived description for it. A description derived from an excerpt or content is a summary of at most 160 characters, never the full body.

## Stable lifecycle hooks

| Hook | Arguments | Contract |
|---|---|---|
| `lightweight_seo_loaded` | `Lightweight_SEO_API $api` | Fires after the facade is available and module registration is finalized. The facade already exists when module factories run. |
| `lightweight_seo_register_modules` | `Lightweight_SEO_Module_Registry $registry` | Registration window for module metadata and a lazy factory. |
| `lightweight_seo_modules_registered` | `Lightweight_SEO_Module_Registry $registry` | Fires after registration is finalized. |
| `lightweight_seo_object_meta_updated` | `string $type, int $id, string[] $fields` | Announces normalized post, term, or user SEO field changes; no request data is passed. |
| `lightweight_seo_is_indexable` | `bool $indexable, string $type, int $id, array $context` | Filters the facade's normalized indexability decision. |
| `lightweight_seo_canonical_url` | `string $url, string $type, int $id` | Filters the facade's resolved canonical URL. |
| `lightweight_seo_sitemap_urls` | `string[] $urls` | Filters enabled, valid sitemap locations. |

Module factories receive the current context (`frontend`, `admin`, `editor`, `rest`, or `cron`) and module ID. A factory runs at most once per request and only when the module is enabled for that context. Extensions that own their enablement option may pass an explicit boolean `enabled` value during registration. Module metadata returned by the facade never includes the factory callable.

## Tracking extension points

The optional Tracking module emits nothing until it is enabled by a valid provider ID. When GTM is configured, direct GA4 and Meta Pixel output is suppressed to avoid duplicate event paths.

| Hook | Arguments | Contract |
|---|---|---|
| `lightweight_seo_tracking_consent_granted` | `bool $granted, string $provider` | Return `false` to block `gtm`, `ga4`, or `meta` until the site's consent solution allows it. |
| `lightweight_seo_tracking_should_output` | `bool $should_output, string $provider` | Final provider-level output decision after role, environment, and consent checks. |
| `lightweight_seo_tracking_script_nonce` | `string $nonce, string $provider` | Supplies a CSP nonce for the provider's script tags. Return the raw nonce value, not an HTML attribute. |
| `lightweight_seo_tracking_settings` | `array $tracking_settings` | Retained compatibility filter containing only the three tracking identifier keys. The complete plugin settings array is no longer exposed. |
| `lightweight_seo_before_tracking_codes` | `array $tracking_settings` | Fires before configured head providers are considered with the three filtered tracking identifier keys. |
| `lightweight_seo_after_tracking_codes` | `array $tracking_settings` | Fires after configured head providers are considered with the three filtered tracking identifier keys. |

The consent filter supports a basic blocking integration; Lightweight SEO does not create a consent banner or persist consent choices. GTM's noscript fallback uses `wp_body_open`. A theme that omits that hook receives a diagnostic comment in page source while the head container continues to operate.

## IndexNow extension point

The optional IndexNow module is the only place where the plugin's PHP sends a request to another service. It sends nothing unless the module is on, a key exists, the site is public, the environment type is `production`, and the site's host is one search engines can reach.

| Hook | Arguments | Contract |
|---|---|---|
| `lightweight_seo_indexnow_urls` | `string[] $urls` | Filters the addresses about to be submitted. Return fewer addresses, or an empty array to send nothing. Addresses on another host are dropped afterwards, and at most 100 are sent by one request. |

The module collects changes wherever a post can be saved: the administration, the editors, REST, cron for scheduled posts, and WP-CLI commands.

## Core output filters and actions

| Hook | Arguments | Contract |
|---|---|---|
| `lightweight_seo_page_context` | `array $context` | Filters the normalized request context. Do not add secrets or mutable services. Besides the title, description, canonical, robots, and Open Graph keys, the context carries `object_title` (the visible name without the title template), `og_locale`, `twitter_site` (the site's X handle with its `@`, or an empty string), `verification` (verification codes keyed by meta tag name, on the first page of the homepage only), and `published_time`/`modified_time` for article content. |
| `lightweight_seo_document_title` | `string $title, array $context` | Filters the final plugin document title. |
| `lightweight_seo_meta_tags` | `array $tags, array $context` | Filters normalized name/property meta-tag definitions before escaping. |
| `lightweight_seo_before_meta_tags` | `array $tags, array $context` | Fires before meta tags are printed. |
| `lightweight_seo_after_meta_tags` | `array $tags, array $context` | Fires after meta tags are printed. |
| `lightweight_seo_link_tags` | `array $links, array $context` | Filters normalized link-tag definitions such as canonical output. |
| `lightweight_seo_schema_graph` | `array $graph, array $context` | Filters schema graph nodes before JSON encoding. Return arrays only. The node for the site's publisher is an `Organization` with the identifier `{home}/#organization`, or a `Person` with `{home}/#person` when the site is set to represent a person. |
| `lightweight_seo_article_post_types` | `string[] $post_types` | Selects the post types whose default schema type is Article. The site owner can choose another type for each post type in the content types table, and that choice wins. Product is excluded by default. |
| `lightweight_seo_supported_post_types` | `string[] $post_types` | Filters public post types that receive SEO metadata controls. |
| `lightweight_seo_supported_taxonomies` | `string[] $taxonomies` | Filters public taxonomies that receive archive SEO controls. |
| `lightweight_seo_compatibility_plugins` | `array $plugins` | Filters plugin-basename to label mappings used for safe-mode detection. |
| `lightweight_seo_suppressed_features` | `string[] $features, string[] $plugins` | Filters the safe-mode feature matrix. Known full SEO plugins suppress title, meta, robots, canonical, and schema output by default. |
| `lightweight_seo_multilingual_provider_active` | `bool $active` | Lets an additional multilingual provider claim hreflang ownership. |

### Schema graph

Every page with a canonical address has a page node with the identifier `{canonical}#webpage`: a `WebPage`, a `CollectionPage` for lists of posts, a `SearchResultsPage` for a search, or a `ProfilePage` for an author archive. It belongs to the `WebSite` node, points at the breadcrumb trail (`{canonical}#breadcrumb`) when there is one, and on the front page is `about` the publisher. An `Article`, `BlogPosting`, or `NewsArticle` node (`{canonical}#article`) is `isPartOf` and the `mainEntityOfPage` of that page node.

The breadcrumb trail follows the default rules of the WordPress Breadcrumbs block, so the two agree: Home, the archive of the post type when it has one, parent pages or the first term with its parents, then the page. Two filters of that block are applied to the structured data as well: `block_core_breadcrumbs_post_type_settings` (preferred taxonomy and term) and `block_core_breadcrumbs_items` (the finished trail). An item without a link is left out, except the last one, which takes the canonical address.

## Module hooks

| Hook | Arguments | Contract |
|---|---|---|
| `lightweight_seo_ai_crawler_registry` | `array $registry` | Filters documented crawler-token metadata. Keep search/user-fetch and model-training purposes distinct. |
| `lightweight_seo_multilingual_links` | `array $links` | Filters normalized provider language/URL pairs used by direct service consumers. |
| `lightweight_seo_llms_txt_conflict` | `bool $conflict` | Return `true` when another owner serves `/llms.txt`. |
| `lightweight_seo_physical_robots_path` | `string $path` | Changes the physical `robots.txt` conflict-check path. |
| `lightweight_seo_physical_llms_path` | `string $path` | Changes the physical `llms.txt` conflict-check path. |

The built-in Redirects module loads in the `frontend`, `admin`, `editor`, and `rest` contexts so slug changes are tracked wherever a post can be saved; only the `frontend` context matches requests.

Redirect, Hreflang, Tracking, Local SEO, and AI Discovery implementation classes are private runtime details. Integrations should use the registry and documented hooks, not instantiate those classes.

## Abilities

On WordPress 6.9 and later the plugin registers three abilities in the `lightweight-seo` category, so agents and other clients of the Abilities API can read and fill the SEO fields of a post, a term, or an author. They are public and available over REST. The class behind them loads only when WordPress collects abilities.

Every ability takes `object_id` and an optional `object_type`: `post` (the default) for a post, page, or other content item, `term` for a category, tag, or other term, and `user` for an author.

| Ability | Further input | Returns |
|---|---|---|
| `lightweight-seo/get-seo-fields` | none | The fields as stored: `title`, `description`, `canonical_url`, `search_visibility` (`default`, `show`, or `hide`), and for posts `social_title`, `social_description`, `social_image`. An empty field means the default is used. |
| `lightweight-seo/update-seo-fields` | any of the fields above | The fields as stored afterwards. Only the fields that are sent change; an empty string clears a field. |
| `lightweight-seo/get-seo-context` | none | `title`, `description`, `canonical_url`, `robots`, and `indexable`, from `get_object_context()`, with the variables of the title and description filled in. |

- **Permission.** An ability requires the right to edit that item: `edit_post`, `edit_term`, or `edit_user`. A signed-out request, or an item that does not exist, is refused.
- **Validation.** WordPress checks the input against the schema first, so an unknown field or an unknown `search_visibility` value is refused. Text is sanitized like the editing screens and keeps variables such as `%title%`. `canonical_url` and `social_image` must be `http` or `https` addresses. A sharing field sent for a term or an author is refused. When anything is refused, nothing is saved.
- **Search visibility.** A post hidden for the first time is stored with "follow links", so it prints `noindex` only.
- **Scope.** Items with SEO fields: supported post types, public taxonomies, and users. Site settings are never changed through an ability.

## WP-CLI

The commands are registered only when WordPress runs under WP-CLI.

| Command | What it does |
|---|---|
| `wp lightweight-seo settings export [--file=<path>]` | Prints the settings file, or writes it to a path. |
| `wp lightweight-seo settings import <file> [--yes]` | Replaces every setting with the file, after the checks of the settings screen. |
| `wp lightweight-seo settings undo-import [--yes]` | Puts back the settings the last import replaced. |
| `wp lightweight-seo module list [--format=<format>]` | Lists the modules and whether each is on. |
| `wp lightweight-seo module enable <module>` | Switches `redirects`, `hreflang`, `tracking`, `local-seo`, or `ai` on. |
| `wp lightweight-seo module disable <module>` | Switches a module off; its settings stay saved. |
| `wp lightweight-seo redirects import <file>` | Merges a CSV file of redirect rules into the saved rules. |
| `wp lightweight-seo redirects test <path>` | Shows which saved rule applies to a path, without requesting it. |

### Settings file

A settings file is JSON with `format` (`lightweight-seo-settings`), `format_version`, `plugin_version`, `schema_version`, `site_url`, `exported_at`, `settings`, and `modules`. It holds the settings option without credentials, data kept from retired features, and the import source. It never holds titles, descriptions, or other fields saved on posts, terms, or users.

An import refuses a file with a newer `schema_version`. It validates every value like a save of the settings screen, measured against the defaults: a value the file does not hold, or one that fails a check, becomes the default. A file whose `site_url` is another site loses its attachment IDs, its `llms.txt` page list, and hreflang lines tied to an object ID. Module state follows the imported switches; the `modules` entry of the file is informational. The replaced settings are kept in the non-autoloaded `lightweight_seo_settings_backup` option until the next import or an undo.

## Compatibility policy

API `1.0` remains backward compatible throughout the 1.1 release line. New fields may be added to returned associative arrays, so consumers must ignore unknown keys. Existing methods, hook names, argument order, and documented value types are not removed without an API version change. Experimental AI crawler metadata may add tokens, but the purpose categories and opt-in training boundary remain stable.
