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
	 * Shared term and author meta service.
	 *
	 * @since    1.1.0
	 * @access   protected
	 * @var      Lightweight_SEO_Archive_Meta    $archive_meta
	 */
	protected $archive_meta;

	/** @var Lightweight_SEO_Page_Context_Service */
	protected $page_context;

	/** @var Lightweight_SEO_Module_State */
	protected $module_state;

	/** @var Lightweight_SEO_Module_Registry */
	protected $module_registry;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->plugin_name = 'lightweight-seo';
		$this->version     = LIGHTWEIGHT_SEO_VERSION;
		$this->load_dependencies();
		$this->settings        = new Lightweight_SEO_Settings();
		$this->post_meta       = new Lightweight_SEO_Post_Meta();
		$this->archive_meta    = new Lightweight_SEO_Archive_Meta( $this->settings );
		$this->page_context    = new Lightweight_SEO_Page_Context_Service( $this->settings, $this->post_meta, $this->archive_meta );
		$this->module_state    = new Lightweight_SEO_Module_State();
		$this->module_registry = new Lightweight_SEO_Module_Registry( $this->module_state );
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

		// Shared term and author meta service
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-archive-meta.php';

		// Versioned public API and the lightweight module boundary.
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-module-state.php';
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-module-registry.php';
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-api.php';

		// Compatibility and safe-mode service
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-compatibility-service.php';

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

		// Frontend header service
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-header-service.php';

		// Sitemap integration service
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-sitemap-service.php';

		// Structured data service
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-schema-service.php';

		// SEO metadata importer service
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-importer-service.php';

		// Frontend class for displaying SEO data
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-frontend.php';
	}

	/**
	 * Run the plugin.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->load_textdomain();
		$this->initialize_module_registry();

		// Initialize admin functionality
		$plugin_admin = new Lightweight_SEO_Admin( $this->get_plugin_name(), $this->get_version(), $this->settings, $this->post_meta );

		// Initialize meta boxes
		$plugin_meta_boxes = new Lightweight_SEO_Meta_Boxes( $this->settings, $this->post_meta );

		// Initialize frontend functionality
		$plugin_frontend = new Lightweight_SEO_Frontend( $this->settings, $this->post_meta, $this->archive_meta, $this->page_context );

		// Initialize sitemap integration
		$plugin_sitemaps = new Lightweight_SEO_Sitemap_Service( $this->settings, $this->post_meta, $this->archive_meta );

		$GLOBALS['lightweight_seo_api'] = new Lightweight_SEO_API( $this->page_context, $this->post_meta, $this->archive_meta, $this->module_registry );
		do_action( 'lightweight_seo_loaded', $GLOBALS['lightweight_seo_api'] );
	}

	/**
	 * Register built-in modules, finalize the registry, and load this request context.
	 *
	 * @return void
	 */
	private function initialize_module_registry() {
		$this->register_builtin_modules( $this->module_registry );
		do_action( 'lightweight_seo_register_modules', $this->module_registry );
		$this->module_registry->finalize();
		do_action( 'lightweight_seo_modules_registered', $this->module_registry );

		$context = $this->get_request_context();

		if ( in_array( $context, array( 'admin', 'editor', 'cron' ), true ) ) {
			$this->module_registry->load_context( $context );
			return;
		}

		add_action( 'rest_api_init', array( $this, 'load_rest_modules' ), 1, 0 );
		add_action( 'wp', array( $this, 'load_frontend_modules' ), 1, 0 );
	}

	/** Load REST modules only after WordPress identifies a REST request. */
	public function load_rest_modules() {
		$this->module_registry->load_context( 'rest' );
	}

	/** Load frontend modules after the main query is available. */
	public function load_frontend_modules() {
		$this->module_registry->load_context( 'frontend' );
	}

	/**
	 * Register metadata only; implementation files remain unloaded until enabled.
	 *
	 * @param Lightweight_SEO_Module_Registry $registry Module registry.
	 * @return void
	 */
	private function register_builtin_modules( $registry ) {
		$definitions = array(
			'redirects' => array(
				'name'        => __( 'Redirects', 'lightweight-seo' ),
				'description' => __( 'Manage redirects and optional slug-change handling.', 'lightweight-seo' ),
				'contexts'    => array( 'frontend', 'editor' ),
			),
			'hreflang'  => array(
				'name'        => __( 'Hreflang', 'lightweight-seo' ),
				'description' => __( 'Output alternate-language links.', 'lightweight-seo' ),
				'contexts'    => array( 'frontend' ),
			),
			'tracking'  => array(
				'name'        => __( 'Tracking', 'lightweight-seo' ),
				'description' => __( 'Output configured analytics containers.', 'lightweight-seo' ),
				'contexts'    => array( 'frontend' ),
			),
			'local-seo' => array(
				'name'        => __( 'Local SEO', 'lightweight-seo' ),
				'description' => __( 'Add single-location LocalBusiness data.', 'lightweight-seo' ),
				'contexts'    => array( 'frontend' ),
			),
			'ai'        => array(
				'name'         => __( 'AI Discovery', 'lightweight-seo' ),
				'description'  => __( 'Experimental crawler policy controls.', 'lightweight-seo' ),
				'contexts'     => array( 'frontend', 'admin' ),
				'experimental' => true,
			),
		);

		foreach ( $definitions as $module_id => $definition ) {
			$definition['factory'] = array( $this, 'load_builtin_module' );
			$registry->register( $module_id, $definition );
		}
	}

	/**
	 * Load one enabled built-in module implementation.
	 *
	 * @param string $context   Request context.
	 * @param string $module_id Module identifier.
	 * @return void
	 */
	public function load_builtin_module( $context, $module_id ) {
		switch ( $module_id ) {
			case 'redirects':
				require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-redirects-service.php';
				new Lightweight_SEO_Redirects_Service( $this->settings, $context );
				break;
			case 'hreflang':
				require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-hreflang-service.php';
				$service = new Lightweight_SEO_Hreflang_Service( $this->settings, $this->page_context );

				if ( ! $service->multilingual_provider_active() && ( new Lightweight_SEO_Compatibility_Service() )->feature_output_allowed( 'hreflang' ) ) {
					add_action( 'wp_head', array( $service, 'add_hreflang_links' ), 2 );
				}
				break;
			case 'tracking':
				require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-tracking-service.php';
				$service = new Lightweight_SEO_Tracking_Service( $this->settings );
				add_action( 'wp_head', array( $service, 'add_tracking_codes' ), 1 );
				add_action( 'wp_body_open', array( $service, 'add_gtm_noscript' ), 1 );
				add_action( 'wp_footer', array( $service, 'add_gtm_theme_diagnostic' ), 100 );
				break;
			case 'local-seo':
				require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-local-seo-module.php';
				new Lightweight_SEO_Local_SEO_Module( $this->settings );
				break;
			case 'ai':
				require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-ai-discovery-module.php';
				new Lightweight_SEO_AI_Discovery_Module( $this->settings, lightweight_seo_get_api(), $context );
				break;
		}
	}

	/**
	 * Resolve the broad execution context without loading module code.
	 *
	 * @return string
	 */
	private function get_request_context() {
		if ( ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return 'cron';
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}

		if ( is_admin() ) {
			global $pagenow;

			return in_array( (string) $pagenow, array( 'post.php', 'post-new.php' ), true ) ? 'editor' : 'admin';
		}

		return 'frontend';
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
}
