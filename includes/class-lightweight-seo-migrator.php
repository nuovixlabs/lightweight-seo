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

		if ( $stored_version < 3 ) {
			self::migrate_to_version_3( $had_settings );
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

	/**
	 * Retire core reports and remote synchronization while preserving supported
	 * SEO metadata and explicitly retained legacy values for export.
	 *
	 * @param bool $had_settings Whether settings existed before migration.
	 * @return void
	 */
	private static function migrate_to_version_3( $had_settings ) {
		$settings = (array) get_option( LIGHTWEIGHT_SEO_OPTION_NAME, array() );
		$summary  = array(
			'removed_sitemaps'           => array(),
			'has_search_console_private' => ! empty( $settings['search_console_service_account_json'] ),
			'legacy_404_entries'         => count( (array) get_option( 'lightweight_seo_404_logs', array() ) ),
			'has_meta_keywords'          => ! empty( $settings['meta_keywords'] ),
		);

		foreach ( array( 'image', 'video', 'news' ) as $sitemap_type ) {
			if ( ! empty( $settings[ 'enable_' . $sitemap_type . '_sitemaps' ] ) ) {
				$summary['removed_sitemaps'][] = $sitemap_type;
			}
		}

		foreach ( array( 'enable_meta_keywords', 'enable_image_sitemaps', 'enable_video_sitemaps', 'enable_news_sitemaps', 'enable_product_schema', 'submit_sitemaps_to_search_console', 'enable_404_monitor', 'discover_min_image_width', 'discover_min_image_height', 'last_import_report' ) as $retired_key ) {
			unset( $settings[ $retired_key ] );
		}

		update_option( LIGHTWEIGHT_SEO_OPTION_NAME, $settings );

		foreach ( array( 'lightweight_seo_image_audit_report', 'lightweight_seo_internal_links_report', 'lightweight_seo_search_console_snapshot', 'lightweight_seo_search_console_token' ) as $option_name ) {
			delete_option( $option_name );
		}

		wp_clear_scheduled_hook( 'lightweight_seo_search_console_sync' );

		if ( $had_settings ) {
			update_option( 'lightweight_seo_upgrade_summary', $summary, false );
		}
	}
}
