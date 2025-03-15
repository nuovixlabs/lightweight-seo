<?php
/**
 * Plugin Name: Lightweight SEO
 * Plugin URI: https://rakeshmandal.com
 * Description: A lightweight WordPress SEO plugin that adds essential SEO functionality without bloat.
 * Version: 1.0.2
 * Author: Rakesh Mandal
 * Author URI: https://rakeshmandal.com
 * Text Domain: lightweight-seo
 * Domain Path: /languages
 * License: MIT
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get plugin metadata
$plugin_data = get_file_data(__FILE__, array(
    'Version' => 'Version',
), 'plugin');

// Define plugin constants
define('LIGHTWEIGHT_SEO_VERSION', $plugin_data['Version']);
define('LIGHTWEIGHT_SEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LIGHTWEIGHT_SEO_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The core plugin class.
 */
require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo.php';

/**
 * Begins execution of the plugin.
 */
function run_lightweight_seo() {
    $plugin = new Lightweight_SEO();
    $plugin->run();
}

// Start the plugin
run_lightweight_seo();
