<?php
/**
 * Header output service for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Header output service.
 */
class Lightweight_SEO_Header_Service {

	/**
	 * Shared page context service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Page_Context_Service
	 */
	private $page_context;

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings
	 */
	private $settings;

	/**
	 * Initialize the service.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Page_Context_Service    $page_context    Shared page context service.
	 * @param    Lightweight_SEO_Settings                $settings        Shared settings service.
	 */
	public function __construct( $page_context, $settings ) {
		$this->page_context = $page_context;
		$this->settings     = $settings;
	}

	/**
	 * Add X-Robots-Tag headers for media and attachment requests.
	 *
	 * @since    1.1.0
	 * @param    array    $headers    Existing response headers.
	 * @return   array
	 */
	public function filter_headers( $headers ) {
		$x_robots_tag = $this->get_x_robots_tag();

		if ( empty( $x_robots_tag ) ) {
			return $headers;
		}

		$headers['X-Robots-Tag'] = $x_robots_tag;

		return $headers;
	}

	/**
	 * Get the X-Robots-Tag value for the current request when needed.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	public function get_x_robots_tag() {
		if ( ! $this->settings->media_x_robots_headers_enabled() ) {
			return '';
		}

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			$post    = $post_id ? get_post( $post_id ) : null;

			if ( ! empty( $post ) && 'attachment' === (string) ( $post->post_type ?? '' ) ) {
				$context = $this->page_context->get_context();

				return ! empty( $context['robots'] ) ? (string) $context['robots'] : 'noindex, noarchive';
			}
		}

		$request_uri  = sanitize_text_field( $_SERVER['REQUEST_URI'] ?? '' );
		$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$extension    = strtolower( pathinfo( $request_path, PATHINFO_EXTENSION ) );
		$extensions   = array(
			'pdf',
			'doc',
			'docx',
			'xls',
			'xlsx',
			'ppt',
			'pptx',
			'zip',
		);

		if ( in_array( $extension, $extensions, true ) ) {
			return 'noindex, noarchive';
		}

		return '';
	}
}
