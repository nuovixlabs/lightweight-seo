<?php
/**
 * Shared settings service for Lightweight SEO.
 *
 * @since      1.0.2
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Shared settings service.
 */
class Lightweight_SEO_Settings {

	/**
	 * Cached settings for the current request.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      array|null    $settings
	 */
	private $settings;

	/**
	 * Get default plugin settings.
	 *
	 * @since    1.0.2
	 * @return   array
	 */
	public function get_defaults() {
		return array(
			'title_format'                        => LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT,
			'home_title_format'                   => '%sitename% %sep% %tagline%',
			'archive_title_format'                => '%title% %sep% %sitename%',
			'search_title_format'                 => 'Search Results for "%search%" %sep% %sitename%',
			'meta_description'                    => get_bloginfo( 'description' ),
			'meta_keywords'                       => '',
			'enable_meta_keywords'                => '1',
			'noindex_search_results'              => '1',
			'noindex_attachment_pages'            => '1',
			'enable_media_x_robots_headers'       => '1',
			'exclude_noindex_from_sitemaps'       => '1',
			'exclude_redirected_from_sitemaps'    => '1',
			'enable_image_sitemaps'               => '1',
			'enable_video_sitemaps'               => '1',
			'enable_news_sitemaps'                => '0',
			'enable_schema_output'                => '1',
			'enable_product_schema'               => '1',
			'enable_local_business_schema'        => '0',
			'local_business_type'                 => 'LocalBusiness',
			'local_business_name'                 => '',
			'local_business_phone'                => '',
			'local_business_price_range'          => '',
			'local_business_address_street'       => '',
			'local_business_address_locality'     => '',
			'local_business_address_region'       => '',
			'local_business_address_postal_code'  => '',
			'local_business_address_country'      => '',
			'local_business_latitude'             => '',
			'local_business_longitude'            => '',
			'local_business_opening_hours'        => '',
			'organization_same_as'                => '',
			'enable_hreflang_output'              => '0',
			'hreflang_mappings'                   => '',
			'search_console_property'             => '',
			'search_console_service_account_json' => '',
			'submit_sitemaps_to_search_console'   => '1',
			'enable_404_monitor'                  => '1',
			'enable_auto_redirects'               => '1',
			'redirect_rules'                      => '',
			'discover_min_image_width'            => 1200,
			'discover_min_image_height'           => 900,
			'import_source'                       => '',
			'last_import_report'                  => '',
			'default_max_image_preview'           => 'large',
			'social_image'                        => '',
			'social_image_id'                     => 0,
			'ga4_measurement_id'                  => '',
			'gtm_container_id'                    => '',
			'facebook_pixel_id'                   => '',
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @since    1.0.2
	 * @return   array
	 */
	public function get_all() {
		if ( null === $this->settings ) {
			$this->settings = wp_parse_args( get_option( LIGHTWEIGHT_SEO_OPTION_NAME, array() ), $this->get_defaults() );
		}

		return $this->settings;
	}

	/**
	 * Get a single setting value.
	 *
	 * @since    1.0.2
	 * @param    string    $key         Setting key.
	 * @param    mixed     $fallback    Fallback value.
	 * @return   mixed
	 */
	public function get( $key, $fallback = null ) {
		$settings = $this->get_all();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $fallback;
	}

	/**
	 * Get the decoded title format.
	 *
	 * @since    1.0.2
	 * @return   string
	 */
	public function get_title_format() {
		return wp_specialchars_decode( $this->get( 'title_format', LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT ), ENT_QUOTES );
	}

	/**
	 * Get the decoded home title format.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	public function get_home_title_format() {
		return wp_specialchars_decode( $this->get( 'home_title_format', '%sitename% %sep% %tagline%' ), ENT_QUOTES );
	}

	/**
	 * Get the decoded archive title format.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	public function get_archive_title_format() {
		return wp_specialchars_decode( $this->get( 'archive_title_format', '%title% %sep% %sitename%' ), ENT_QUOTES );
	}

	/**
	 * Get the decoded search title format.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	public function get_search_title_format() {
		return wp_specialchars_decode( $this->get( 'search_title_format', 'Search Results for "%search%" %sep% %sitename%' ), ENT_QUOTES );
	}

	/**
	 * Determine whether meta keywords output is enabled.
	 *
	 * @since    1.0.2
	 * @return   bool
	 */
	public function meta_keywords_enabled() {
		return '1' === (string) $this->get( 'enable_meta_keywords', '1' );
	}

	/**
	 * Determine whether search result pages should be noindexed.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function search_results_noindex_enabled() {
		return '1' === (string) $this->get( 'noindex_search_results', '1' );
	}

	/**
	 * Determine whether attachment pages should default to noindex.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function attachment_pages_noindex_enabled() {
		return '1' === (string) $this->get( 'noindex_attachment_pages', '1' );
	}

	/**
	 * Determine whether media requests should receive X-Robots-Tag headers.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function media_x_robots_headers_enabled() {
		return '1' === (string) $this->get( 'enable_media_x_robots_headers', '1' );
	}

	/**
	 * Determine whether noindexed content should be excluded from XML sitemaps.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function exclude_noindex_from_sitemaps_enabled() {
		return '1' === (string) $this->get( 'exclude_noindex_from_sitemaps', '1' );
	}

	/**
	 * Determine whether redirected URLs should be excluded from XML sitemaps.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function exclude_redirected_from_sitemaps_enabled() {
		return '1' === (string) $this->get( 'exclude_redirected_from_sitemaps', '1' );
	}

	/**
	 * Determine whether the attachment image sitemap is enabled.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function image_sitemaps_enabled() {
		return '1' === (string) $this->get( 'enable_image_sitemaps', '1' );
	}

	/**
	 * Determine whether the video attachment sitemap is enabled.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function video_sitemaps_enabled() {
		return '1' === (string) $this->get( 'enable_video_sitemaps', '1' );
	}

	/**
	 * Determine whether the recent-post news sitemap is enabled.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function news_sitemaps_enabled() {
		return '1' === (string) $this->get( 'enable_news_sitemaps', '0' );
	}

	/**
	 * Determine whether core schema output is enabled.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function schema_output_enabled() {
		return '1' === (string) $this->get( 'enable_schema_output', '1' );
	}

	/**
	 * Determine whether WooCommerce-style product schema is enabled.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function product_schema_enabled() {
		return '1' === (string) $this->get( 'enable_product_schema', '1' );
	}

	/**
	 * Determine whether LocalBusiness schema is enabled.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function local_business_schema_enabled() {
		return '1' === (string) $this->get( 'enable_local_business_schema', '0' );
	}

	/**
	 * Determine whether 404 logging is enabled.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function not_found_monitor_enabled() {
		return '1' === (string) $this->get( 'enable_404_monitor', '1' );
	}

	/**
	 * Determine whether automatic slug redirects are enabled.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function auto_redirects_enabled() {
		return '1' === (string) $this->get( 'enable_auto_redirects', '1' );
	}

	/**
	 * Determine whether hreflang output is enabled.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function hreflang_output_enabled() {
		return '1' === (string) $this->get( 'enable_hreflang_output', '0' );
	}

	/**
	 * Get organization sameAs profile URLs.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	public function get_organization_same_as() {
		$raw_value = (string) $this->get( 'organization_same_as', '' );
		$lines     = preg_split( "/\r\n|\n|\r/", $raw_value );
		$urls      = array();

		foreach ( $lines as $line ) {
			$url = trim( $line );

			if ( ! empty( $url ) ) {
				$urls[] = $url;
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Get normalized LocalBusiness schema data.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	public function get_local_business_data() {
		$business_type = sanitize_text_field( (string) $this->get( 'local_business_type', 'LocalBusiness' ) );
		$allowed_types = array(
			'LocalBusiness',
			'Restaurant',
			'Store',
			'MedicalBusiness',
			'ProfessionalService',
		);

		if ( ! in_array( $business_type, $allowed_types, true ) ) {
			$business_type = 'LocalBusiness';
		}

		$opening_hours = preg_split( "/\r\n|\n|\r/", (string) $this->get( 'local_business_opening_hours', '' ) );
		$opening_hours = array_values(
			array_filter(
				array_map( 'sanitize_text_field', $opening_hours )
			)
		);

		return array(
			'type'          => $business_type,
			'name'          => sanitize_text_field( (string) $this->get( 'local_business_name', '' ) ),
			'telephone'     => sanitize_text_field( (string) $this->get( 'local_business_phone', '' ) ),
			'price_range'   => sanitize_text_field( (string) $this->get( 'local_business_price_range', '' ) ),
			'street'        => sanitize_text_field( (string) $this->get( 'local_business_address_street', '' ) ),
			'locality'      => sanitize_text_field( (string) $this->get( 'local_business_address_locality', '' ) ),
			'region'        => sanitize_text_field( (string) $this->get( 'local_business_address_region', '' ) ),
			'postal_code'   => sanitize_text_field( (string) $this->get( 'local_business_address_postal_code', '' ) ),
			'country'       => sanitize_text_field( (string) $this->get( 'local_business_address_country', '' ) ),
			'latitude'      => sanitize_text_field( (string) $this->get( 'local_business_latitude', '' ) ),
			'longitude'     => sanitize_text_field( (string) $this->get( 'local_business_longitude', '' ) ),
			'opening_hours' => $opening_hours,
		);
	}

	/**
	 * Get normalized hreflang mappings.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	public function get_hreflang_mappings() {
		$lines    = preg_split( "/\r\n|\n|\r/", (string) $this->get( 'hreflang_mappings', '' ) );
		$mappings = array();

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );

			if ( empty( $line ) ) {
				continue;
			}

			$parts = preg_split( '/\s+/', $line, 2 );

			if ( 2 !== count( $parts ) ) {
				continue;
			}

			$language = sanitize_text_field( $parts[0] );
			$url      = esc_url_raw( $parts[1] );

			if ( empty( $language ) || empty( $url ) ) {
				continue;
			}

			$mappings[] = array(
				'language' => $language,
				'url'      => $url,
			);
		}

		return $mappings;
	}

	/**
	 * Get the configured Search Console property identifier.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	public function get_search_console_property() {
		return trim( sanitize_text_field( (string) $this->get( 'search_console_property', '' ) ) );
	}

	/**
	 * Get the raw Search Console service-account JSON payload.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	public function get_search_console_service_account_json() {
		return trim( (string) $this->get( 'search_console_service_account_json', '' ) );
	}

	/**
	 * Determine whether sitemap submission should be attempted during Search Console sync.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function search_console_sitemap_submission_enabled() {
		return '1' === (string) $this->get( 'submit_sitemaps_to_search_console', '1' );
	}

	/**
	 * Get parsed manual redirect rules.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	public function get_manual_redirect_rules() {
		$lines = preg_split( "/\r\n|\n|\r/", (string) $this->get( 'redirect_rules', '' ) );
		$rules = array();

		foreach ( $lines as $line ) {
			$rule = $this->parse_redirect_rule_line( $line );

			if ( ! empty( $rule ) ) {
				$rules[] = $rule;
			}
		}

		return $rules;
	}

	/**
	 * Normalize manual redirect rules into a stored textarea value.
	 *
	 * @since    1.1.0
	 * @param    string    $value    Raw textarea value.
	 * @return   string
	 */
	public function normalize_redirect_rules_input( $value ) {
		$lines            = preg_split( "/\r\n|\n|\r/", (string) $value );
		$normalized_rules = array();

		foreach ( $lines as $line ) {
			$rule = $this->parse_redirect_rule_line( $line );

			if ( ! empty( $rule ) ) {
				$normalized_rules[] = $rule['source'] . ' ' . $rule['target'] . ' ' . $rule['status'];
			}
		}

		return implode( "\n", array_values( array_unique( $normalized_rules ) ) );
	}

	/**
	 * Parse a redirect rule line into a normalized rule array.
	 *
	 * @since    1.1.0
	 * @param    string    $line    Raw redirect rule line.
	 * @return   array
	 */
	private function parse_redirect_rule_line( $line ) {
		$parts = preg_split( '/\s+/', trim( (string) $line ) );

		if ( count( $parts ) < 2 ) {
			return array();
		}

		$source = $this->normalize_redirect_path( array_shift( $parts ) );
		$target = array_shift( $parts );
		$status = absint( $parts[0] ?? 301 );

		if ( empty( $source ) ) {
			return array();
		}

		if ( ! in_array( $status, array( 301, 302, 307, 308 ), true ) ) {
			$status = 301;
		}

		if ( 0 === strpos( $target, '/' ) ) {
			$target = $this->normalize_redirect_path( $target );
		} elseif ( false === filter_var( $target, FILTER_VALIDATE_URL ) ) {
			return array();
		} else {
			$scheme = strtolower( (string) wp_parse_url( $target, PHP_URL_SCHEME ) );

			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
				return array();
			}
		}

		return array(
			'source' => $source,
			'target' => $target,
			'status' => $status,
		);
	}

	/**
	 * Normalize a redirect source or local target path.
	 *
	 * @since    1.1.0
	 * @param    string    $path    Raw path or URL.
	 * @return   string
	 */
	private function normalize_redirect_path( $path ) {
		$normalized_path = trim( (string) $path );

		if ( false !== strpos( $normalized_path, '://' ) ) {
			$normalized_path = (string) wp_parse_url( $normalized_path, PHP_URL_PATH );
		}

		if ( '' === $normalized_path ) {
			return '';
		}

		$normalized_path = '/' . ltrim( $normalized_path, '/' );

		if ( '/' !== $normalized_path ) {
			$normalized_path = rtrim( $normalized_path, '/' );
		}

		return $normalized_path;
	}

	/**
	 * Get the default max-image-preview robots directive value.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	public function get_default_max_image_preview() {
		return $this->normalize_max_image_preview( $this->get( 'default_max_image_preview', 'large' ), 'large' );
	}

	/**
	 * Get the minimum image width for Discover-focused audits.
	 *
	 * @since    1.1.0
	 * @return   int
	 */
	public function get_discover_min_image_width() {
		return max( 1, absint( $this->get( 'discover_min_image_width', 1200 ) ) );
	}

	/**
	 * Get the minimum image height for Discover-focused audits.
	 *
	 * @since    1.1.0
	 * @return   int
	 */
	public function get_discover_min_image_height() {
		return max( 1, absint( $this->get( 'discover_min_image_height', 900 ) ) );
	}

	/**
	 * Normalize a max-image-preview value to a supported directive.
	 *
	 * @since    1.1.0
	 * @param    string    $value       Value to normalize.
	 * @param    string    $fallback    Fallback value.
	 * @return   string
	 */
	public function normalize_max_image_preview( $value, $fallback = '' ) {
		$normalized = strtolower( sanitize_text_field( (string) $value ) );
		$allowed    = array( 'none', 'standard', 'large' );

		if ( in_array( $normalized, $allowed, true ) ) {
			return $normalized;
		}

		return $fallback;
	}

	/**
	 * Get the global social image URL.
	 *
	 * @since    1.0.2
	 * @return   string
	 */
	public function get_social_image_url() {
		$image_id = absint( $this->get( 'social_image_id', 0 ) );

		if ( $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'full' );

			if ( ! empty( $image_url ) ) {
				return $image_url;
			}
		}

		return $this->get( 'social_image', '' );
	}
}
