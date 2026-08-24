<?php
/**
 * Activation and deactivation lifecycle for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Handles lifecycle operations without booting the plugin runtime.
 */
class Lightweight_SEO_Lifecycle {

	/**
	 * Activate the plugin and migrate each affected site.
	 *
	 * @param bool $network_wide Whether the plugin is network-activated.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		self::for_each_site( array( 'Lightweight_SEO_Migrator', 'maybe_migrate' ), $network_wide );
	}

	/**
	 * Deactivate the plugin and remove all owned schedules.
	 *
	 * @param bool $network_wide Whether the plugin is network-deactivated.
	 * @return void
	 */
	public static function deactivate( $network_wide = false ) {
		self::for_each_site( array( __CLASS__, 'clear_scheduled_events' ), $network_wide );
	}

	/**
	 * Clear all plugin-owned cron events on the current site.
	 *
	 * @return void
	 */
	public static function clear_scheduled_events() {
		foreach ( Lightweight_SEO_Data_Registry::get_cron_hooks() as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Run a site-scoped callback safely for single-site or network activation.
	 *
	 * @param callable $callback     Callback to run for each site.
	 * @param bool     $network_wide Whether all network sites are affected.
	 * @return void
	 */
	private static function for_each_site( $callback, $network_wide ) {
		if ( ! $network_wide || ! is_multisite() ) {
			call_user_func( $callback );
			return;
		}

		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			call_user_func( $callback );
			restore_current_blog();
		}
	}
}
