<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Coordinates the administration area.
 *
 * Builds the settings registrar, field renderers, settings page, notices, and
 * tool handlers, and connects each of them to its WordPress hooks.
 */
class Lightweight_SEO_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Shared post meta service.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      Lightweight_SEO_Post_Meta    $post_meta
	 */
	private $post_meta;

	/**
	 * Settings page renderer.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Admin_Page    $page
	 */
	private $page;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string                       $plugin_name    The name of this plugin.
	 * @param    string                       $version        The version of this plugin.
	 * @param    Lightweight_SEO_Settings     $settings       Shared settings service.
	 * @param    Lightweight_SEO_Post_Meta    $post_meta      Shared post meta service.
	 */
	public function __construct( $plugin_name, $version, $settings, $post_meta ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->post_meta   = $post_meta;

		$importer       = new Lightweight_SEO_Importer_Service( $post_meta );
		$import_state   = new Lightweight_SEO_Import_State();
		$tools_fields   = new Lightweight_SEO_Admin_Tools_Fields( $settings, $importer, $import_state );
		$admin_settings = new Lightweight_SEO_Admin_Settings(
			$plugin_name,
			$settings,
			new Lightweight_SEO_Admin_Core_Fields( $settings ),
			new Lightweight_SEO_Admin_Module_Fields( $settings ),
			$tools_fields
		);
		$notices        = new Lightweight_SEO_Admin_Notices( $plugin_name );
		$tools          = new Lightweight_SEO_Admin_Tools( $plugin_name, $importer, $import_state );
		$this->page     = new Lightweight_SEO_Admin_Page( $plugin_name, $settings, $tools_fields );

		// Add menu item
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add settings link on plugin page
		add_filter( 'plugin_action_links_' . plugin_basename( LIGHTWEIGHT_SEO_PLUGIN_FILE ), array( $this, 'add_action_links' ) );

		// Register settings
		add_action( 'admin_init', array( $admin_settings, 'register_settings' ) );

		// Render compatibility notices
		add_action( 'admin_notices', array( $notices, 'maybe_render_safe_mode_notice' ) );
		add_action( 'admin_notices', array( $notices, 'maybe_render_upgrade_summary' ) );
		add_action( 'admin_post_lightweight_seo_dismiss_upgrade_summary', array( $notices, 'dismiss_upgrade_summary' ) );

		// Handle Tools tab actions, each behind its own nonce
		add_action( 'admin_post_lightweight_seo_export_legacy_keywords', array( $tools, 'export_legacy_keywords' ) );
		add_action( 'admin_post_' . Lightweight_SEO_Admin_Tools::ACTION_IMPORT_PREVIEW, array( $tools, 'handle_import_preview' ) );
		add_action( 'admin_post_' . Lightweight_SEO_Admin_Tools::ACTION_IMPORT_BATCH, array( $tools, 'handle_import_batch' ) );
		add_action( 'admin_post_' . Lightweight_SEO_Admin_Tools::ACTION_IMPORT_ROLLBACK, array( $tools, 'handle_import_rollback' ) );
		add_action( 'admin_post_' . Lightweight_SEO_Admin_Tools::ACTION_DELETE_404_LOGS, array( $tools, 'handle_delete_legacy_404_logs' ) );

		// Enqueue admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Register the administration menu for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {
		if ( function_exists( 'is_network_admin' ) && is_network_admin() ) {
			return;
		}

		add_menu_page(
			__( 'Lightweight SEO Settings', 'lightweight-seo' ),
			__( 'SEO', 'lightweight-seo' ),
			'manage_options',
			$this->plugin_name,
			array( $this->page, 'display_plugin_admin_page' ),
			'dashicons-search',
			100
		);
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 * @param    array    $links    Plugin Action links.
	 * @return   array
	 */
	public function add_action_links( $links ) {
		$settings_link = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . $this->plugin_name ) ) . '">' . esc_html__( 'Settings', 'lightweight-seo' ) . '</a>',
		);
		return array_merge( $settings_link, $links );
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @since    1.0.0
	 */
	public function enqueue_admin_scripts( $hook ) {
		$screen                     = get_current_screen();
		$is_plugin_page             = 'toplevel_page_' . $this->plugin_name === $hook;
		$is_public_post_type_screen = $screen && 'post' === $screen->base && in_array( $screen->post_type, $this->post_meta->get_supported_post_types(), true );

		// Only load scripts on our plugin page or supported post edit screens
		if ( ! $is_plugin_page && ! $is_public_post_type_screen ) {
			return;
		}

		// Enqueue the WordPress media uploader
		wp_enqueue_media();

		// Enqueue our admin script
		wp_enqueue_script(
			$this->plugin_name . '-admin-script',
			LIGHTWEIGHT_SEO_PLUGIN_URL . 'admin/js/lightweight-seo-admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			$this->plugin_name . '-admin-script',
			'lightweightSeoAdmin',
			array(
				'mediaTitle'  => __( 'Select or Upload Image', 'lightweight-seo' ),
				'mediaButton' => __( 'Use this image', 'lightweight-seo' ),
			)
		);

		// Enqueue our admin styles
		wp_enqueue_style(
			$this->plugin_name . '-admin-style',
			LIGHTWEIGHT_SEO_PLUGIN_URL . 'admin/css/lightweight-seo-admin.css',
			array(),
			$this->version
		);
	}
}
