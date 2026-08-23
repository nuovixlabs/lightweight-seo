<?php
/**
 * Registry of data owned by Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Provides one authoritative inventory for lifecycle and uninstall cleanup.
 */
class Lightweight_SEO_Data_Registry {

	/**
	 * Get persistent site options.
	 *
	 * @return array
	 */
	public static function get_persistent_options() {
		return array(
			'lightweight_seo_settings',
			'lightweight_seo_schema_version',
			'lightweight_seo_generated_redirect_rules',
		);
	}

	/**
	 * Get ephemeral site options that are always removed during uninstall.
	 *
	 * @return array
	 */
	public static function get_ephemeral_options() {
		return array(
			'lightweight_seo_404_logs',
			'lightweight_seo_image_audit_report',
			'lightweight_seo_internal_links_report',
			'lightweight_seo_search_console_snapshot',
			'lightweight_seo_search_console_token',
		);
	}

	/**
	 * Get persistent object meta keys.
	 *
	 * @return array
	 */
	public static function get_object_meta_keys() {
		return array(
			'_lightweight_seo_title',
			'_lightweight_seo_description',
			'_lightweight_seo_keywords',
			'_lightweight_seo_canonical_url',
			'_lightweight_seo_noindex',
			'_lightweight_seo_nofollow',
			'_lightweight_seo_noarchive',
			'_lightweight_seo_nosnippet',
			'_lightweight_seo_max_image_preview',
			'_lightweight_seo_social_title',
			'_lightweight_seo_social_description',
			'_lightweight_seo_social_image',
			'_lightweight_seo_social_image_id',
		);
	}

	/**
	 * Get plugin-owned cron hooks.
	 *
	 * @return array
	 */
	public static function get_cron_hooks() {
		return array( 'lightweight_seo_search_console_sync' );
	}

	/**
	 * Get credential or report fields removed even when persistent data is kept.
	 *
	 * @return array
	 */
	public static function get_ephemeral_setting_keys() {
		return array(
			'last_import_report',
			'search_console_service_account_json',
		);
	}
}
