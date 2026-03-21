<?php
/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class.
 */
class Lightweight_SEO {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Shared settings service.
	 *
	 * @since    1.0.2
	 * @access   protected
	 * @var      Lightweight_SEO_Settings    $settings
	 */
	protected $settings;

	/**
	 * Shared post meta service.
	 *
	 * @since    1.0.2
	 * @access   protected
	 * @var      Lightweight_SEO_Post_Meta    $post_meta
	 */
	protected $post_meta;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->plugin_name = 'lightweight-seo';
		$this->version     = LIGHTWEIGHT_SEO_VERSION;
		$this->load_dependencies();
		$this->settings  = new Lightweight_SEO_Settings();
		$this->post_meta = new Lightweight_SEO_Post_Meta();
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		// Shared settings service
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-settings.php';

		// Shared post meta service
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-post-meta.php';

		// Admin class for backend functionality
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-admin.php';

		// Meta boxes class for per-page SEO controls
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-meta-boxes.php';

		// Shared frontend page context service
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-page-context-service.php';

		// Frontend title service
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-title-service.php';

		// Frontend meta tags service
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-meta-tags-service.php';

		// Frontend tracking service
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-tracking-service.php';

		// Frontend class for displaying SEO data
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-frontend.php';
	}

	/**
	 * Run the plugin.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		// Initialize admin functionality
		$plugin_admin = new Lightweight_SEO_Admin( $this->get_plugin_name(), $this->get_version(), $this->settings, $this->post_meta );

		// Initialize meta boxes
		$plugin_meta_boxes = new Lightweight_SEO_Meta_Boxes( $this->settings, $this->post_meta );

		// Initialize frontend functionality
		$plugin_frontend = new Lightweight_SEO_Frontend( $this->settings, $this->post_meta );

		// Register activation hook
		register_activation_hook( LIGHTWEIGHT_SEO_PLUGIN_FILE, array( $this, 'activate' ) );
	}

	/**
	 * Load the plugin text domain.
	 *
	 * @since    1.0.2
	 * @return   void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'lightweight-seo',
			false,
			dirname( plugin_basename( LIGHTWEIGHT_SEO_PLUGIN_FILE ) ) . '/languages/'
		);
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Plugin activation
	 *
	 * @since     1.0.0
	 */
	public function activate() {
		// Set default options if they don't exist
		if ( ! get_option( LIGHTWEIGHT_SEO_OPTION_NAME ) ) {
			update_option( LIGHTWEIGHT_SEO_OPTION_NAME, $this->settings->get_defaults() );
		}
	}
}
