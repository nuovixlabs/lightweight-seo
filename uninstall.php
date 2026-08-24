<?php
/**
 * Uninstall cleanup for Lightweight SEO.
 *
 * @package Lightweight_SEO
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-lightweight-seo-data-registry.php';

/**
 * Delete plugin data for the current site.
 *
 * @return void
 */
function lightweight_seo_delete_plugin_data() {
	$settings   = get_option( 'lightweight_seo_settings', false );
	$delete_all = is_array( $settings ) && '1' === (string) ( $settings['delete_data_on_uninstall'] ?? '0' );

	foreach ( Lightweight_SEO_Data_Registry::get_cron_hooks() as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}

	foreach ( Lightweight_SEO_Data_Registry::get_ephemeral_options() as $option_name ) {
		delete_option( $option_name );
	}

	if ( ! $delete_all ) {
		if ( ! is_array( $settings ) ) {
			return;
		}

		foreach ( Lightweight_SEO_Data_Registry::get_ephemeral_setting_keys() as $setting_key ) {
			unset( $settings[ $setting_key ] );
		}

		update_option( 'lightweight_seo_settings', $settings );
		return;
	}

	foreach ( Lightweight_SEO_Data_Registry::get_persistent_options() as $option_name ) {
		delete_option( $option_name );
	}

	foreach ( Lightweight_SEO_Data_Registry::get_object_meta_keys() as $meta_key ) {
		delete_metadata( 'post', 0, $meta_key, '', true );
		delete_metadata( 'term', 0, $meta_key, '', true );
		delete_metadata( 'user', 0, $meta_key, '', true );
	}
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		lightweight_seo_delete_plugin_data();
		restore_current_blog();
	}
} else {
	lightweight_seo_delete_plugin_data();
}
