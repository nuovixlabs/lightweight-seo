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
			'title_format'                  => LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT,
			'home_title_format'             => '%sitename% %sep% %tagline%',
			'archive_title_format'          => '%title% %sep% %sitename%',
			'search_title_format'           => 'Search Results for "%search%" %sep% %sitename%',
			'meta_description'              => get_bloginfo( 'description' ),
			'meta_keywords'                 => '',
			'enable_meta_keywords'          => '1',
			'noindex_search_results'        => '1',
			'exclude_noindex_from_sitemaps' => '1',
			'enable_image_sitemaps'         => '1',
			'enable_schema_output'          => '1',
			'organization_same_as'          => '',
			'enable_404_monitor'            => '1',
			'enable_auto_redirects'         => '1',
			'redirect_rules'                => '',
			'default_max_image_preview'     => 'large',
			'social_image'                  => '',
			'social_image_id'               => 0,
			'ga4_measurement_id'            => '',
			'gtm_container_id'              => '',
			'facebook_pixel_id'             => '',
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
	 * Determine whether noindexed content should be excluded from XML sitemaps.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function exclude_noindex_from_sitemaps_enabled() {
		return '1' === (string) $this->get( 'exclude_noindex_from_sitemaps', '1' );
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
	 * Determine whether core schema output is enabled.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function schema_output_enabled() {
		return '1' === (string) $this->get( 'enable_schema_output', '1' );
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
