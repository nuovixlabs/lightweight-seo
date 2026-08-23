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
	}
}
