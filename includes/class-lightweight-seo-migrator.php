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
		$had_settings   = false !== get_option( LIGHTWEIGHT_SEO_OPTION_NAME, false );

		if ( $stored_version >= LIGHTWEIGHT_SEO_SCHEMA_VERSION ) {
			return;
		}

		if ( $stored_version < 1 ) {
			self::migrate_to_version_1();
		}

		if ( $stored_version < 2 ) {
			self::migrate_to_version_2( $had_settings );
		}

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

	/**
	 * Establish bounded module state. New installs start with every module off,
	 * while upgrades preserve explicit legacy configuration.
	 *
	 * @param bool $had_settings Whether settings existed before migration.
	 * @return void
	 */
	private static function migrate_to_version_2( $had_settings ) {
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-module-state.php';

		$states = array();

		if ( $had_settings ) {
			$states = Lightweight_SEO_Module_State::derive_from_legacy_settings(
				(array) get_option( LIGHTWEIGHT_SEO_OPTION_NAME, array() )
			);
		}

		update_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME, Lightweight_SEO_Module_State::normalize( $states ) );
	}
}
