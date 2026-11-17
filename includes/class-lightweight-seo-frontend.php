<?php
/**
 * The frontend functionality of the plugin.
 *
 * @since      1.0.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The frontend functionality of the plugin.
 */
class Lightweight_SEO_Frontend {

	/**
	 * Title service.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      Lightweight_SEO_Title_Service    $title_service
	 */
	private $title_service;

	/**
	 * Meta tags service.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      Lightweight_SEO_Meta_Tags_Service    $meta_tags_service
	 */
	private $meta_tags_service;

	/**
	 * Schema service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Schema_Service    $schema_service
	 */
	private $schema_service;

	/**
	 * Header service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Header_Service
	 */
	private $header_service;

	/**
	 * Shared page context service.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      Lightweight_SEO_Page_Context_Service    $page_context
	 */
	private $page_context;

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings    $settings
	 */
	private $settings;

	/**
	 * Shared post meta service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Post_Meta    $post_meta
	 */
	private $post_meta;

	/**
	 * Shared archive meta service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Archive_Meta    $archive_meta
	 */
	private $archive_meta;

	/**
	 * Safe mode detection.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Compatibility_Service    $compatibility
	 */
	private $compatibility;

	/**
	 * Initialize the class.
	 *
	 * @since    1.0.2
	 * @param    Lightweight_SEO_Settings             $settings       Shared settings service.
	 * @param    Lightweight_SEO_Post_Meta            $post_meta      Shared post meta service.
	 * @param    Lightweight_SEO_Archive_Meta         $archive_meta   Shared archive meta service.
	 * @param    Lightweight_SEO_Page_Context_Service $page_context   Optional shared page context service.
	 */
	public function __construct( $settings, $post_meta, $archive_meta, $page_context = null ) {
		$compatibility_service = new Lightweight_SEO_Compatibility_Service();

		$this->settings      = $settings;
		$this->post_meta     = $post_meta;
		$this->archive_meta  = $archive_meta;
		$this->compatibility = $compatibility_service;

		$this->page_context      = $page_context ? $page_context : new Lightweight_SEO_Page_Context_Service( $settings, $post_meta, $archive_meta );
		$this->title_service     = new Lightweight_SEO_Title_Service( $this->page_context );
		$this->meta_tags_service = new Lightweight_SEO_Meta_Tags_Service( $this->page_context );
		$this->header_service    = new Lightweight_SEO_Header_Service( $this->page_context, $settings );
		$this->schema_service    = new Lightweight_SEO_Schema_Service( $this->page_context, $settings );

		if ( $compatibility_service->feature_output_allowed( 'title' ) ) {
			add_filter( 'pre_get_document_title', array( $this->title_service, 'filter_document_title' ), 15 );
		}

		if ( $compatibility_service->feature_output_allowed( 'meta' ) ) {
			add_action( 'wp_head', array( $this->meta_tags_service, 'add_meta_tags' ), 1 );
		}

		if ( $compatibility_service->feature_output_allowed( 'robots' ) ) {
			add_filter( 'wp_robots', array( $this->meta_tags_service, 'filter_robots' ) );
		}

		if ( $compatibility_service->feature_output_allowed( 'canonical' ) ) {
			add_filter( 'get_canonical_url', array( $this->meta_tags_service, 'filter_canonical_url' ), 10, 2 );
			add_action( 'wp_head', array( $this->meta_tags_service, 'add_non_singular_canonical' ), 10 );
		}

		if ( $compatibility_service->feature_output_allowed( 'schema' ) ) {
			add_action( 'wp_head', array( $this->schema_service, 'add_schema' ), 5 );
		}

		add_filter( 'wp_headers', array( $this->header_service, 'filter_headers' ) );

		// After every redirect has had its chance, so an address that sends visitors elsewhere is never reported as a page.
		add_action( 'template_redirect', array( $this, 'maybe_inspect' ), 99999 );

		// Late in the toolbar, after the items of WordPress itself.
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_status' ), 90 );
	}

	/**
	 * Tell an administrator, in the toolbar of the page they are viewing, whether search engines may show it.
	 *
	 * The item is plain text that links to the report of the page. It adds no
	 * style or script, and it is built from what the page has already resolved
	 * for its head.
	 *
	 * @since    1.1.0
	 * @param    WP_Admin_Bar    $admin_bar    Toolbar being built.
	 * @return   void
	 */
	public function add_admin_bar_status( $admin_bar ) {
		if ( is_admin() || ! is_object( $admin_bar ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Becomes one encoded query argument of a link inside the administration area.
		$robots      = (array) apply_filters( 'wp_robots', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The WordPress filter that builds the robots tag of this page.

		if ( is_404() ) {
			$title = __( 'SEO: page not found', 'lightweight-seo' );
		} elseif ( ! empty( $robots['noindex'] ) ) {
			$title = __( 'SEO: hidden from search', 'lightweight-seo' );
		} else {
			$title = __( 'SEO: shown in search', 'lightweight-seo' );
		}

		$admin_bar->add_node(
			array(
				'id'    => 'lightweight-seo',
				'title' => esc_html( $title ),
				'href'  => admin_url( 'admin.php?page=lightweight-seo&tab=tools&inspect=' . rawurlencode( Lightweight_SEO_Url::from_path( $request_uri ) ) ) . '#lwseo-inspect',
				'meta'  => array(
					'title' => __( 'See what Lightweight SEO sends for this page, and why', 'lightweight-seo' ),
				),
			)
		);
	}

	/**
	 * Answer an administrator's inspect request with a report instead of the page.
	 *
	 * An ordinary request leaves here after one check. The inspector and the
	 * interface helpers it prints with are loaded only for an administrator who asked.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function maybe_inspect() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checks that the argument is present; the inspector verifies the nonce before it answers.
		if ( ! isset( $_GET[ LIGHTWEIGHT_SEO_INSPECT_ARG ] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$api = function_exists( 'lightweight_seo_get_api' ) ? lightweight_seo_get_api() : null;

		if ( ! $api ) {
			return;
		}

		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-admin-ui.php';
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-inspector.php';

		( new Lightweight_SEO_Inspector( $api, $this->meta_tags_service, $this->schema_service, $this->settings, $this->post_meta, $this->archive_meta, $this->compatibility ) )->respond();
	}
}
