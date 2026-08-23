# Lightweight SEO Developer API

Lightweight SEO exposes a read-only facade for extensions. Consumers must not read the `lightweight_seo_settings` option, instantiate classes from `includes/`, or rely on plugin file paths.

## Version handshake

The core plugin defines `LIGHTWEIGHT_SEO_API_VERSION` separately from its release version. An extension supporting WordPress 6.0 should guard its bootstrap at runtime:

```php
add_action(
	'lightweight_seo_loaded',
	static function ( $api ) {
		if ( ! $api->is_compatible( '1.0.3', '1.0' ) ) {
			return;
		}

		// Register the extension. Keep jobs and reports disabled before this point.
	}
);
```

If `lightweight_seo_get_api()` does not exist or returns `null`, core is missing, inactive, or has not loaded. Extensions should register no jobs in that state and may show an administrator notice. A failed check must not delete extension data.

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

## Stable lifecycle hooks

| Hook | Arguments | Contract |
|---|---|---|
| `lightweight_seo_loaded` | `Lightweight_SEO_API $api` | Fires after the facade is available. |
| `lightweight_seo_register_modules` | `Lightweight_SEO_Module_Registry $registry` | Registration window for module metadata and a lazy factory. |
| `lightweight_seo_modules_registered` | `Lightweight_SEO_Module_Registry $registry` | Fires after registration is finalized. |
| `lightweight_seo_object_meta_updated` | `string $type, int $id, string[] $fields` | Announces normalized post, term, or user SEO field changes; no request data is passed. |
| `lightweight_seo_is_indexable` | `bool $indexable, string $type, int $id, array $context` | Filters the facade's normalized indexability decision. |
| `lightweight_seo_canonical_url` | `string $url, string $type, int $id` | Filters the facade's resolved canonical URL. |
| `lightweight_seo_sitemap_urls` | `string[] $urls` | Filters enabled, valid sitemap locations. |

Module factories receive the current context (`frontend`, `admin`, `editor`, `rest`, or `cron`) and module ID. A factory runs at most once per request and only when the module is enabled for that context. Extensions that own their enablement option may pass an explicit boolean `enabled` value during registration. Module metadata returned by the facade never includes the factory callable.
