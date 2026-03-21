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
	 * Tracking service.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      Lightweight_SEO_Tracking_Service    $tracking_service
	 */
	private $tracking_service;

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
	 * @param    Lightweight_SEO_Settings     $settings     Shared settings service.
	 * @param    Lightweight_SEO_Post_Meta    $post_meta    Shared post meta service.
	 */
	public function __construct( $settings, $post_meta ) {
		$this->page_context      = new Lightweight_SEO_Page_Context_Service( $settings, $post_meta );
		$this->title_service     = new Lightweight_SEO_Title_Service( $this->page_context );
		$this->meta_tags_service = new Lightweight_SEO_Meta_Tags_Service( $this->page_context );
		$this->tracking_service  = new Lightweight_SEO_Tracking_Service( $settings );

		// Filter document title
		add_filter( 'pre_get_document_title', array( $this->title_service, 'filter_document_title' ), 15 );

		// Add meta tags to head
		add_action( 'wp_head', array( $this->meta_tags_service, 'add_meta_tags' ), 1 );

		// Add tracking codes
		add_action( 'wp_head', array( $this->tracking_service, 'add_tracking_codes' ), 1 );
		add_action( 'wp_body_open', array( $this->tracking_service, 'add_gtm_noscript' ), 1 );
	}
}
