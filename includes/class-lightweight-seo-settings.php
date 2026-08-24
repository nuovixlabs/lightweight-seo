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
	/** Maximum number of manual redirect rules stored in the bounded core option. */
	const MAX_MANUAL_REDIRECTS = 500;

	/** Maximum number of explicit hreflang mappings stored in the bounded core option. */
	const MAX_HREFLANG_MAPPINGS = 500;

	/** Maximum number of manually curated pages exposed in llms.txt. */
	const MAX_LLMS_POSTS = 50;


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
			'title_format'                       => LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT,
			'home_title_format'                  => '%sitename% %sep% %tagline%',
			'archive_title_format'               => '%title% %sep% %sitename%',
			'search_title_format'                => 'Search Results for "%search%" %sep% %sitename%',
			'meta_description'                   => '',
			'noindex_search_results'             => '1',
			'noindex_attachment_pages'           => '1',
			'enable_media_x_robots_headers'      => '1',
			'exclude_noindex_from_sitemaps'      => '1',
			'exclude_redirected_from_sitemaps'   => '1',
			'enable_schema_output'               => '1',
			'enable_local_business_schema'       => '0',
			'local_business_type'                => 'LocalBusiness',
			'local_business_name'                => '',
			'local_business_phone'               => '',
			'local_business_price_range'         => '',
			'local_business_address_street'      => '',
			'local_business_address_locality'    => '',
			'local_business_address_region'      => '',
			'local_business_address_postal_code' => '',
			'local_business_address_country'     => '',
			'local_business_latitude'            => '',
			'local_business_longitude'           => '',
			'local_business_opening_hours'       => '',
			'local_business_image'               => '',
			'organization_same_as'               => '',
			'enable_hreflang_output'             => '0',
			'enable_hreflang_path_mirroring'     => '0',
			'hreflang_mappings'                  => '',
			'enable_auto_redirects'              => '0',
			'redirect_rules'                     => '',
			'import_source'                      => '',
			'import_cursor'                      => 0,
			'last_import_report'                 => '',
			'default_max_image_preview'          => 'large',
			'social_image'                       => '',
			'social_image_id'                    => 0,
			'ga4_measurement_id'                 => '',
			'gtm_container_id'                   => '',
			'facebook_pixel_id'                  => '',
			'tracking_excluded_roles'            => 'administrator',
			'tracking_excluded_environments'     => "local\ndevelopment\nstaging",
			'enable_ai_discovery'                => '0',
			'ai_search_crawlers_enabled'         => '1',
			'ai_training_crawlers_enabled'       => '0',
			'enable_llms_txt'                    => '0',
			'llms_txt_post_ids'                  => '',
			'delete_data_on_uninstall'           => '0',
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
	 * Determine whether core schema output is enabled.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function schema_output_enabled() {
		return '1' === (string) $this->get( 'enable_schema_output', '1' );
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
	 * Determine whether automatic slug redirects are enabled.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function auto_redirects_enabled() {
		return '1' === (string) $this->get( 'enable_auto_redirects', '0' );
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
		$normalized = $this->normalize_local_business_input( $this->get_all() );

		return $normalized['data'];
	}

	/**
	 * Validate and normalize the supported single-location business fields.
	 *
	 * @param array $input Submitted or stored settings.
	 * @return array Normalized data, storage values, and validation errors.
	 */
	public function normalize_local_business_input( $input ) {
		$input         = is_array( $input ) ? $input : array();
		$allowed_types = array( 'LocalBusiness', 'Restaurant', 'Store', 'MedicalBusiness', 'ProfessionalService' );
		$type          = sanitize_text_field( (string) ( $input['local_business_type'] ?? 'LocalBusiness' ) );
		$errors        = array();

		if ( ! in_array( $type, $allowed_types, true ) ) {
			$type     = 'LocalBusiness';
			$errors[] = __( 'Choose a supported LocalBusiness type.', 'lightweight-seo' );
		}

		$raw_image = trim( (string) ( $input['local_business_image'] ?? '' ) );
		$data      = array(
			'type'          => $type,
			'name'          => sanitize_text_field( (string) ( $input['local_business_name'] ?? '' ) ),
			'telephone'     => sanitize_text_field( (string) ( $input['local_business_phone'] ?? '' ) ),
			'price_range'   => sanitize_text_field( (string) ( $input['local_business_price_range'] ?? '' ) ),
			'street'        => sanitize_text_field( (string) ( $input['local_business_address_street'] ?? '' ) ),
			'locality'      => sanitize_text_field( (string) ( $input['local_business_address_locality'] ?? '' ) ),
			'region'        => sanitize_text_field( (string) ( $input['local_business_address_region'] ?? '' ) ),
			'postal_code'   => sanitize_text_field( (string) ( $input['local_business_address_postal_code'] ?? '' ) ),
			'country'       => strtoupper( sanitize_text_field( (string) ( $input['local_business_address_country'] ?? '' ) ) ),
			'latitude'      => trim( sanitize_text_field( (string) ( $input['local_business_latitude'] ?? '' ) ) ),
			'longitude'     => trim( sanitize_text_field( (string) ( $input['local_business_longitude'] ?? '' ) ) ),
			'opening_hours' => array(),
			'image'         => esc_url_raw( $raw_image ),
			'valid'         => true,
		);

		if ( '' !== $data['telephone'] ) {
			$digit_count = strlen( preg_replace( '/\D+/', '', $data['telephone'] ) );

			if ( $digit_count < 7 || $digit_count > 15 || 1 !== preg_match( '/^\+?[0-9().\-\s]+$/', $data['telephone'] ) ) {
				$data['telephone'] = '';
				$errors[]          = __( 'Enter a valid phone number containing 7 to 15 digits.', 'lightweight-seo' );
			}
		}

		if ( '' !== $data['price_range'] && ( strlen( $data['price_range'] ) > 32 || 1 !== preg_match( '/^[\p{Sc}0-9.,\-–\s]+$/u', $data['price_range'] ) ) ) {
			$data['price_range'] = '';
			$errors[]            = __( 'Enter a short numeric or currency-symbol price range.', 'lightweight-seo' );
		}

		if ( '' !== $data['country'] && 1 !== preg_match( '/^[A-Z]{2}$/', $data['country'] ) ) {
			$data['country'] = '';
			$errors[]        = __( 'Use a two-letter ISO country code.', 'lightweight-seo' );
		}

		$has_latitude  = '' !== $data['latitude'];
		$has_longitude = '' !== $data['longitude'];

		if ( $has_latitude !== $has_longitude || ( $has_latitude && ( ! is_numeric( $data['latitude'] ) || ! is_numeric( $data['longitude'] ) || (float) $data['latitude'] < -90 || (float) $data['latitude'] > 90 || (float) $data['longitude'] < -180 || (float) $data['longitude'] > 180 ) ) ) {
			$data['latitude']  = '';
			$data['longitude'] = '';
			$errors[]          = __( 'Enter both coordinates using latitude -90 to 90 and longitude -180 to 180.', 'lightweight-seo' );
		}

		foreach ( preg_split( "/\r\n|\n|\r/", (string) ( $input['local_business_opening_hours'] ?? '' ) ) as $opening_hours ) {
			$opening_hours = trim( sanitize_text_field( $opening_hours ) );

			if ( '' === $opening_hours ) {
				continue;
			}

			if ( 1 !== preg_match( '/^(Mo|Tu|We|Th|Fr|Sa|Su)(-(Mo|Tu|We|Th|Fr|Sa|Su))? (?:[01][0-9]|2[0-3]):[0-5][0-9]-(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $opening_hours ) ) {
				$errors[] = __( 'Use opening hours like Mo-Fr 09:00-17:00.', 'lightweight-seo' );
				continue;
			}

			$data['opening_hours'][] = $opening_hours;
		}

		if ( '' !== $raw_image ) {
			$scheme = strtolower( (string) wp_parse_url( $data['image'], PHP_URL_SCHEME ) );

			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || false === filter_var( $data['image'], FILTER_VALIDATE_URL ) ) {
				$data['image'] = '';
				$errors[]      = __( 'Enter a valid HTTP or HTTPS business image URL.', 'lightweight-seo' );
			}
		}

		$address_values = array( $data['street'], $data['locality'], $data['postal_code'], $data['country'] );
		$has_address    = (bool) array_filter( $address_values );

		$enabled = '1' === (string) ( $input['enable_local_business_schema'] ?? '0' );

		if ( $enabled && $has_address && 4 !== count( array_filter( $address_values ) ) ) {
			$errors[] = __( 'Complete the street, locality, postal code, and country together.', 'lightweight-seo' );
		}

		if ( $enabled && ( '' === $data['name'] || ! $has_address || 4 !== count( array_filter( $address_values ) ) ) ) {
			$errors[] = __( 'A business name and complete address are required before Local SEO can be enabled.', 'lightweight-seo' );
		}

		$data['valid'] = empty( $errors );

		return array(
			'data'   => $data,
			'errors' => array_values( array_unique( $errors ) ),
			'values' => array(
				'local_business_type'                => $data['type'],
				'local_business_name'                => $data['name'],
				'local_business_phone'               => $data['telephone'],
				'local_business_price_range'         => $data['price_range'],
				'local_business_address_street'      => $data['street'],
				'local_business_address_locality'    => $data['locality'],
				'local_business_address_region'      => $data['region'],
				'local_business_address_postal_code' => $data['postal_code'],
				'local_business_address_country'     => $data['country'],
				'local_business_latitude'            => $data['latitude'],
				'local_business_longitude'           => $data['longitude'],
				'local_business_opening_hours'       => implode( "\n", $data['opening_hours'] ),
				'local_business_image'               => $data['image'],
			),
		);
	}

	/** Return sanitized role slugs excluded from tracking output. */
	public function get_tracking_excluded_roles() {
		return $this->normalize_key_list( $this->get( 'tracking_excluded_roles', 'administrator' ) );
	}

	/** Return recognized WordPress environment types excluded from tracking. */
	public function get_tracking_excluded_environments() {
		return array_values( array_intersect( $this->normalize_key_list( $this->get( 'tracking_excluded_environments', "local\ndevelopment\nstaging" ) ), array( 'local', 'development', 'staging', 'production' ) ) );
	}

	/** Normalize a comma or line separated list of keys. */
	public function normalize_key_list( $value ) {
		$keys = preg_split( '/[\s,]+/', strtolower( (string) $value ) );
		$keys = array_filter( array_map( 'sanitize_key', $keys ) );

		return array_values( array_unique( $keys ) );
	}

	/** Return whether the experimental AI Discovery module is configured on. */
	public function ai_discovery_enabled() {
		return '1' === (string) $this->get( 'enable_ai_discovery', '0' );
	}

	/** Return whether search and user-directed AI crawlers are allowed. */
	public function ai_search_crawlers_enabled() {
		return '1' === (string) $this->get( 'ai_search_crawlers_enabled', '1' );
	}

	/** Return whether model-training crawler tokens are allowed. */
	public function ai_training_crawlers_enabled() {
		return '1' === (string) $this->get( 'ai_training_crawlers_enabled', '0' );
	}

	/** Return whether the optional curated llms.txt endpoint is enabled. */
	public function llms_txt_enabled() {
		return '1' === (string) $this->get( 'enable_llms_txt', '0' );
	}

	/** Normalize the bounded list of explicitly curated post IDs. */
	public function normalize_llms_post_ids( $value ) {
		$ids = preg_split( '/[^0-9]+/', (string) $value );
		$ids = array_filter( array_map( 'absint', $ids ) );

		return implode( ',', array_slice( array_values( array_unique( $ids ) ), 0, self::MAX_LLMS_POSTS ) );
	}

	/** Return the bounded list of explicitly curated post IDs. */
	public function get_llms_post_ids() {
		$normalized = $this->normalize_llms_post_ids( $this->get( 'llms_txt_post_ids', '' ) );

		return '' === $normalized ? array() : array_map( 'absint', explode( ',', $normalized ) );
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

			$mapping = $this->parse_hreflang_mapping_line( $line );

			if ( ! empty( $mapping ) ) {
				$mappings[] = $mapping;
			}
		}

		return $mappings;
	}

	/** Normalize explicit object mappings and retained legacy mirror mappings. */
	public function normalize_hreflang_mappings_input( $value ) {
		$lines         = preg_split( "/\r\n|\n|\r/", (string) $value );
		$normalized    = array();
		$seen_language = array();
		$seen_target   = array();

		foreach ( $lines as $line ) {
			$mapping = $this->parse_hreflang_mapping_line( $line );

			if ( empty( $mapping ) ) {
				continue;
			}

			$reference    = $mapping['object_type'] . ':' . $mapping['object_id'];
			$language_key = $reference . '|' . strtolower( $mapping['language'] );
			$target_key   = $reference . '|' . strtolower( untrailingslashit( $mapping['url'] ) );

			if ( isset( $seen_language[ $language_key ] ) || isset( $seen_target[ $target_key ] ) ) {
				continue;
			}

			$seen_language[ $language_key ] = true;
			$seen_target[ $target_key ]     = true;
			$normalized[]                   = ( 'mirror' === $mapping['object_type'] ? '' : $reference . ' ' ) . $mapping['language'] . ' ' . $mapping['url'];
		}

		return implode( "\n", array_slice( $normalized, 0, self::MAX_HREFLANG_MAPPINGS ) );
	}

	/** Determine whether retained legacy base URLs may mirror the current path. */
	public function hreflang_path_mirroring_enabled() {
		return '1' === (string) $this->get( 'enable_hreflang_path_mirroring', '0' );
	}

	/** Parse one explicit `type:id language URL` line or a retained legacy mirror line. */
	private function parse_hreflang_mapping_line( $line ) {
		$parts = preg_split( '/\s+/', trim( (string) $line ) );

		if ( 2 === count( $parts ) ) {
			$object_type = 'mirror';
			$object_id   = 0;
			$language    = $parts[0];
			$url         = $parts[1];
		} elseif ( 3 === count( $parts ) && preg_match( '/^(post|term|user):([1-9][0-9]*)$/', $parts[0], $matches ) ) {
			$object_type = $matches[1];
			$object_id   = absint( $matches[2] );
			$language    = $parts[1];
			$url         = $parts[2];
		} else {
			return array();
		}

		$language = $this->normalize_hreflang_language( $language );
		$url      = esc_url_raw( $url );

		if ( empty( $language ) || empty( $url ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return array();
		}

		return array(
			'object_type' => $object_type,
			'object_id'   => $object_id,
			'language'    => $language,
			'url'         => $url,
		);
	}

	/** Normalize the common BCP 47 language, script, region, and variant structure. */
	private function normalize_hreflang_language( $language ) {
		$language = str_replace( '_', '-', trim( sanitize_text_field( (string) $language ) ) );

		if ( 'x-default' === strtolower( $language ) ) {
			return 'x-default';
		}

		if ( 1 !== preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z]{4})?(?:-(?:[A-Za-z]{2}|[0-9]{3}))?(?:-[A-Za-z0-9]{5,8})*$/', $language ) ) {
			return '';
		}

		$parts = explode( '-', $language );

		foreach ( $parts as $index => $part ) {
			if ( 0 === $index ) {
				$parts[ $index ] = strtolower( $part );
			} elseif ( 4 === strlen( $part ) ) {
				$parts[ $index ] = ucfirst( strtolower( $part ) );
			} elseif ( 2 === strlen( $part ) && ctype_alpha( $part ) ) {
				$parts[ $index ] = strtoupper( $part );
			} else {
				$parts[ $index ] = strtolower( $part );
			}
		}

		return implode( '-', $parts );
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
				$normalized_rules[ $rule['source'] ] = $rule;
			}
		}

		$normalized_rules = $this->remove_redirect_loops( array_values( $normalized_rules ) );
		$normalized_rules = array_slice( $normalized_rules, 0, self::MAX_MANUAL_REDIRECTS );

		return implode(
			"\n",
			array_map(
				function ( $rule ) {
					return $rule['source'] . ' ' . $rule['target'] . ' ' . $rule['status'];
				},
				$normalized_rules
			)
		);
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

			if ( ! $this->is_redirect_target_allowed( $target ) ) {
				return array();
			}
		}

		if ( $source === $target ) {
			return array();
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
		$normalized_path = (string) wp_parse_url( $normalized_path, PHP_URL_PATH );

		if ( '' === $normalized_path ) {
			return '';
		}

		$normalized_path = '/' . ltrim( $normalized_path, '/' );

		if ( '/' !== $normalized_path ) {
			$normalized_path = rtrim( $normalized_path, '/' );
		}

		return $normalized_path;
	}

	/** Reject any local rule that participates in a redirect loop. */
	private function remove_redirect_loops( $rules ) {
		$rule_map     = array_column( $rules, 'target', 'source' );
		$loop_sources = array();

		foreach ( array_keys( $rule_map ) as $source ) {
			$visited = array();
			$current = $source;

			while ( isset( $rule_map[ $current ] ) && 0 === strpos( $rule_map[ $current ], '/' ) ) {
				if ( isset( $visited[ $current ] ) ) {
					$loop_sources = array_merge( $loop_sources, array_keys( $visited ) );
					break;
				}

				$visited[ $current ] = true;
				$current             = $rule_map[ $current ];
			}
		}

		return array_values(
			array_filter(
				$rules,
				function ( $rule ) use ( $loop_sources ) {
					return ! in_array( $rule['source'], $loop_sources, true );
				}
			)
		);
	}

	/** Allow same-host redirects and external hosts explicitly approved by WordPress. */
	private function is_redirect_target_allowed( $target ) {
		$target_host = strtolower( (string) wp_parse_url( $target, PHP_URL_HOST ) );
		$home_host   = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

		if ( empty( $target_host ) || $target_host === $home_host ) {
			return ! empty( $target_host );
		}

		$allowed_hosts = (array) apply_filters( 'allowed_redirect_hosts', array(), $target_host );

		return in_array( $target_host, array_map( 'strtolower', $allowed_hosts ), true );
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
