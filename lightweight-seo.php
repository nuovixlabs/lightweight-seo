<?php
/**
 * Plugin Name: Lightweight SEO
 * Plugin URI: https://rakeshmandal.com
 * Description: A lightweight WordPress SEO plugin that adds essential SEO functionality without bloat.
 * Version: 1.1.0-rc.3
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Rakesh Mandal
 * Author URI: https://rakeshmandal.com
 * Text Domain: lightweight-seo
 * Domain Path: /languages
 * License: MIT
 * Update URI: https://github.com/nuovixlabs/lightweight-seo
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants. The version is read from the header above so the
// release tag check has a single source of truth; no global variable is leaked.
define( 'LIGHTWEIGHT_SEO_VERSION', get_file_data( __FILE__, array( 'Version' => 'Version' ), 'plugin' )['Version'] );
define( 'LIGHTWEIGHT_SEO_API_VERSION', '1.0' );
define( 'LIGHTWEIGHT_SEO_PLUGIN_FILE', __FILE__ );
define( 'LIGHTWEIGHT_SEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIGHTWEIGHT_SEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LIGHTWEIGHT_SEO_OPTION_NAME', 'lightweight_seo_settings' );
define( 'LIGHTWEIGHT_SEO_MODULES_OPTION_NAME', 'lightweight_seo_modules' );
define( 'LIGHTWEIGHT_SEO_SCHEMA_VERSION', 4 );
define( 'LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION', 'lightweight_seo_schema_version' );
define( 'LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT', '%title% – %sitename%' );
define( 'LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR', '–' );
// Query argument, and nonce action, of an administrator's request for the report of a page.
define( 'LIGHTWEIGHT_SEO_INSPECT_ARG', 'lightweight_seo_inspect' );

/**
 * The core plugin class.
 */
require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-url.php';
require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-text.php';
require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-data-registry.php';
require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-migrator.php';
require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-lifecycle.php';
require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo.php';

/**
 * Get the read-only public API after Lightweight SEO has loaded.
 *
 * Consumers should wait for the lightweight_seo_loaded action.
 *
 * @return Lightweight_SEO_API|null
 */
function lightweight_seo_get_api() {
	return isset( $GLOBALS['lightweight_seo_api'] ) && $GLOBALS['lightweight_seo_api'] instanceof Lightweight_SEO_API
		? $GLOBALS['lightweight_seo_api']
		: null;
}

register_activation_hook( __FILE__, array( 'Lightweight_SEO_Lifecycle', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Lightweight_SEO_Lifecycle', 'deactivate' ) );

/**
 * Begins execution of the plugin.
 */
function run_lightweight_seo() {
	Lightweight_SEO_Migrator::maybe_migrate();

	$plugin = new Lightweight_SEO();
	$plugin->run();

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-cli.php';
		Lightweight_SEO_CLI::register();
	}
}

add_action( 'init', 'run_lightweight_seo', 0 );
