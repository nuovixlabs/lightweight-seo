<?php
/**
 * Frontend title service for Lightweight SEO.
 *
 * @since      1.0.2
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Frontend title service.
 */
class Lightweight_SEO_Title_Service {

	/**
	 * Shared page context service.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      Lightweight_SEO_Page_Context_Service    $page_context
	 */
	private $page_context;

	/**
	 * Initialize the service.
	 *
	 * @since    1.0.2
	 * @param    Lightweight_SEO_Page_Context_Service    $page_context    Shared page context service.
	 */
	public function __construct( $page_context ) {
		$this->page_context = $page_context;
	}

	/**
	 * Filter the document title.
	 *
	 * @since    1.0.2
	 * @param    string    $title    The document title.
	 * @return   string
	 */
	public function filter_document_title( $title ) {
		$context = $this->page_context->get_context();

		if ( ! empty( $context['document_title'] ) ) {
			return apply_filters( 'lightweight_seo_document_title', $context['document_title'], $context );
		}

		return $title;
	}
}
