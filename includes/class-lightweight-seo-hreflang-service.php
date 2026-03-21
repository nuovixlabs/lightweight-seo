<?php
/**
 * Hreflang output service for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Hreflang output service.
 */
class Lightweight_SEO_Hreflang_Service {

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings
	 */
	private $settings;

	/**
	 * Shared page context service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Page_Context_Service
	 */
	private $page_context;

	/**
	 * Initialize the service.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings                $settings        Shared settings service.
	 * @param    Lightweight_SEO_Page_Context_Service    $page_context    Shared page context service.
	 */
	public function __construct( $settings, $page_context ) {
		$this->settings     = $settings;
		$this->page_context = $page_context;
	}

	/**
	 * Output hreflang alternate links.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function add_hreflang_links() {
		foreach ( $this->get_hreflang_links() as $link ) {
			if ( empty( $link['hreflang'] ) || empty( $link['href'] ) ) {
				continue;
			}

			echo '<link rel="alternate" hreflang="' . esc_attr( $link['hreflang'] ) . '" href="' . esc_url( $link['href'] ) . '" />' . "\n";
		}
	}

	/**
	 * Get the current hreflang link set.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	public function get_hreflang_links() {
		if ( ! $this->settings->hreflang_output_enabled() ) {
			return array();
		}

		$context       = $this->page_context->get_context();
		$canonical_url = esc_url_raw( $context['canonical_url'] ?? '' );

		if ( empty( $canonical_url ) ) {
			return array();
		}

		$request_path = (string) wp_parse_url( $canonical_url, PHP_URL_PATH );
		$request_path = '/' . ltrim( $request_path, '/' );

		if ( '/' !== $request_path ) {
			$request_path = rtrim( $request_path, '/' );
		}

		$links        = array();
		$self_locale  = str_replace( '_', '-', sanitize_text_field( function_exists( 'get_locale' ) ? get_locale() : 'en-US' ) );
		$seen_codes   = array();
		$links[]      = array(
			'hreflang' => $self_locale,
			'href'     => $canonical_url,
		);
		$seen_codes[] = strtolower( $self_locale );

		foreach ( $this->settings->get_hreflang_mappings() as $mapping ) {
			$language = sanitize_text_field( $mapping['language'] ?? '' );
			$base_url = esc_url_raw( $mapping['url'] ?? '' );
			$key      = strtolower( $language );

			if ( empty( $language ) || empty( $base_url ) || in_array( $key, $seen_codes, true ) ) {
				continue;
			}

			$links[]      = array(
				'hreflang' => $language,
				'href'     => $this->build_alternate_url( $base_url, $request_path ),
			);
			$seen_codes[] = $key;
		}

		return $links;
	}

	/**
	 * Build a page-level alternate URL from a configured base.
	 *
	 * @since    1.1.0
	 * @param    string    $base_url        Configured alternate URL.
	 * @param    string    $request_path    Current canonical path.
	 * @return   string
	 */
	private function build_alternate_url( $base_url, $request_path ) {
		if ( false !== strpos( $base_url, '%path%' ) ) {
			return str_replace( '%path%', ltrim( $request_path, '/' ), $base_url );
		}

		$base_path = (string) wp_parse_url( $base_url, PHP_URL_PATH );

		if ( '' === $base_path || '/' === $base_path ) {
			return rtrim( $base_url, '/' ) . $request_path;
		}

		return $base_url;
	}
}
