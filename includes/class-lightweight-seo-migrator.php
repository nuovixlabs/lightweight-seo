<?php
/**
 * Option schema migrations for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Runs idempotent, site-scoped schema migrations.
 */
class Lightweight_SEO_Migrator {

	/**
	 * Run pending migrations when the stored schema is behind.
	 *
	 * @return void
	 */
	public static function maybe_migrate() {
		$stored_version = (int) get_option( LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION, 0 );

		if ( $stored_version >= LIGHTWEIGHT_SEO_SCHEMA_VERSION ) {
			return;
		}

		self::migrate_to_version_1();
		update_option( LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION, LIGHTWEIGHT_SEO_SCHEMA_VERSION, false );
	}

	/**
	 * Establish the versioned settings schema without rewriting existing values.
	 *
	 * @return void
	 */
	private static function migrate_to_version_1() {
		$stored_settings = get_option( LIGHTWEIGHT_SEO_OPTION_NAME, false );

		if ( false !== $stored_settings ) {
			return;
		}

		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-settings.php';

		$settings = new Lightweight_SEO_Settings();
		update_option( LIGHTWEIGHT_SEO_OPTION_NAME, $settings->get_defaults() );
	}
}
