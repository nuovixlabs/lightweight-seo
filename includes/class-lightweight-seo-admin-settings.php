<?php
/**
 * Settings registration and sanitization for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Registers the plugin setting with its sections and fields, and sanitizes submitted values.
 */
class Lightweight_SEO_Admin_Settings {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings    $settings
	 */
	private $settings;

	/**
	 * Renderer for the appearance, indexation, and identity fields.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Admin_Core_Fields    $core_fields
	 */
	private $core_fields;

	/**
	 * Renderer for the optional module fields.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Admin_Module_Fields    $module_fields
	 */
	private $module_fields;

	/**
	 * Renderer for the Tools tab fields.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Admin_Tools_Fields    $tools_fields
	 */
	private $tools_fields;

	/**
	 * Initialize the registrar.
	 *
	 * @since    1.1.0
	 * @param    string                                 $plugin_name      The name of this plugin.
	 * @param    Lightweight_SEO_Settings               $settings         Shared settings service.
	 * @param    Lightweight_SEO_Admin_Core_Fields      $core_fields      Core field renderer.
	 * @param    Lightweight_SEO_Admin_Module_Fields    $module_fields    Module field renderer.
	 * @param    Lightweight_SEO_Admin_Tools_Fields     $tools_fields     Tools field renderer.
	 */
	public function __construct( $plugin_name, $settings, $core_fields, $module_fields, $tools_fields ) {
		$this->plugin_name   = $plugin_name;
		$this->settings      = $settings;
		$this->core_fields   = $core_fields;
		$this->module_fields = $module_fields;
		$this->tools_fields  = $tools_fields;
	}

	/**
	 * Register plugin settings
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {
		register_setting(
			LIGHTWEIGHT_SEO_OPTION_NAME,
			LIGHTWEIGHT_SEO_OPTION_NAME,
			array( $this, 'validate_settings' )
		);

		foreach ( $this->get_sections() as $section_id => $section ) {
			add_settings_section( $section_id, $section['title'], $section['callback'], $this->plugin_name );

			foreach ( $section['fields'] as $field_id => $field ) {
				// A field with one control names it, so the settings page can print a real label for it.
				$args = empty( $field[2] ) ? array() : array( 'label_for' => 'lightweight_seo_' . $field_id );

				// Every field is rendered by the "{field_id}_render" method of its renderer.
				add_settings_field( $field_id, $field[0], array( $field[1], $field_id . '_render' ), $this->plugin_name, $section_id, $args );
			}
		}
	}

	/**
	 * Describe every settings section and its fields in display order.
	 *
	 * Each field maps to its label and the renderer that owns it. A third value of
	 * true marks a field with a single control whose ID is "lightweight_seo_{field_id}".
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function get_sections() {
		$core    = $this->core_fields;
		$modules = $this->module_fields;
		$tools   = $this->tools_fields;

		return array(
			'lightweight_seo_general_section'      => array(
				'title'    => __( 'Global SEO settings', 'lightweight-seo' ),
				'callback' => array( $core, 'general_section_callback' ),
				'fields'   => array(
					'title_format'         => array( __( 'Default title format', 'lightweight-seo' ), $core, true ),
					'home_title_format'    => array( __( 'Homepage title format', 'lightweight-seo' ), $core, true ),
					'archive_title_format' => array( __( 'Archive title format', 'lightweight-seo' ), $core, true ),
					'search_title_format'  => array( __( 'Search title format', 'lightweight-seo' ), $core, true ),
					'meta_description'     => array( __( 'Homepage meta description', 'lightweight-seo' ), $core, true ),
					'social_image'         => array( __( 'Default social image', 'lightweight-seo' ), $core, true ),
				),
			),
			'lightweight_seo_indexation_section'   => array(
				'title'    => __( 'Indexation controls', 'lightweight-seo' ),
				'callback' => array( $core, 'indexation_section_callback' ),
				'fields'   => array(
					'content_types'                 => array( __( 'Content types', 'lightweight-seo' ), $core ),
					'default_max_image_preview'     => array( __( 'Image preview size', 'lightweight-seo' ), $core, true ),
					'enable_media_x_robots_headers' => array( __( 'Attachment page headers', 'lightweight-seo' ), $core ),
				),
			),
			'lightweight_seo_sitemap_section'      => array(
				'title'    => __( 'XML sitemaps', 'lightweight-seo' ),
				'callback' => array( $core, 'sitemap_section_callback' ),
				'fields'   => array(
					'exclude_noindex_from_sitemaps'    => array( __( 'Hidden pages', 'lightweight-seo' ), $core ),
					'exclude_redirected_from_sitemaps' => array( __( 'Redirected pages', 'lightweight-seo' ), $core ),
				),
			),
			'lightweight_seo_schema_section'       => array(
				'title'    => __( 'Structured data', 'lightweight-seo' ),
				'callback' => array( $core, 'schema_section_callback' ),
				'fields'   => array(
					'enable_schema_output'           => array( __( 'Core schema output', 'lightweight-seo' ), $core ),
					'organization_same_as'           => array( __( 'Organization profiles', 'lightweight-seo' ), $core, true ),
					'enable_local_business_schema'   => array( __( 'Local business schema', 'lightweight-seo' ), $modules ),
					'local_business_details'         => array( __( 'Business details', 'lightweight-seo' ), $modules ),
					'enable_hreflang_output'         => array( __( 'Hreflang output', 'lightweight-seo' ), $modules ),
					'hreflang_mappings'              => array( __( 'Hreflang mappings', 'lightweight-seo' ), $modules ),
					'enable_hreflang_path_mirroring' => array( __( 'Legacy path mirroring', 'lightweight-seo' ), $modules ),
				),
			),
			'lightweight_seo_redirects_section'    => array(
				'title'    => __( 'Redirects', 'lightweight-seo' ),
				'callback' => array( $modules, 'redirects_section_callback' ),
				'fields'   => array(
					'enable_auto_redirects'    => array( __( 'Automatic slug redirects', 'lightweight-seo' ), $modules ),
					'redirect_rules'           => array( __( 'Manual redirect rules', 'lightweight-seo' ), $modules ),
					'generated_redirect_rules' => array( __( 'Generated redirects', 'lightweight-seo' ), $modules ),
					'redirect_export'          => array( __( 'Redirect export', 'lightweight-seo' ), $modules ),
					'redirect_health'          => array( __( 'Redirect health', 'lightweight-seo' ), $modules ),
				),
			),
			'lightweight_seo_migration_section'    => array(
				'title'    => __( 'Migration and imports', 'lightweight-seo' ),
				'callback' => array( $tools, 'migration_section_callback' ),
				'fields'   => array(
					'import_source'            => array( __( 'Import source', 'lightweight-seo' ), $tools, true ),
					'import_actions'           => array( __( 'Preview or import', 'lightweight-seo' ), $tools ),
					'import_report'            => array( __( 'Import status', 'lightweight-seo' ), $tools ),
					'legacy_data_transition'   => array( __( 'Legacy data', 'lightweight-seo' ), $tools ),
					'delete_data_on_uninstall' => array( __( 'Uninstall data', 'lightweight-seo' ), $tools ),
				),
			),
			'lightweight_seo_tracking_section'     => array(
				'title'    => __( 'Tracking codes', 'lightweight-seo' ),
				'callback' => array( $modules, 'tracking_section_callback' ),
				'fields'   => array(
					// Google Tag Manager is the recommended primary strategy; the direct IDs are alternatives.
					'gtm_container_id'               => array( __( 'Google Tag Manager container ID', 'lightweight-seo' ), $modules, true ),
					'ga4_measurement_id'             => array( __( 'Direct Google Analytics 4 measurement ID', 'lightweight-seo' ), $modules, true ),
					'facebook_pixel_id'              => array( __( 'Direct Meta Pixel ID', 'lightweight-seo' ), $modules, true ),
					'tracking_excluded_roles'        => array( __( 'Excluded roles', 'lightweight-seo' ), $modules ),
					'tracking_excluded_environments' => array( __( 'Excluded environments', 'lightweight-seo' ), $modules ),
				),
			),
			'lightweight_seo_ai_discovery_section' => array(
				'title'    => __( 'AI Discovery', 'lightweight-seo' ),
				'callback' => array( $modules, 'ai_discovery_section_callback' ),
				'fields'   => array(
					'enable_ai_discovery'          => array( __( 'Enable module', 'lightweight-seo' ), $modules ),
					'ai_search_crawlers_enabled'   => array( __( 'AI search visibility', 'lightweight-seo' ), $modules ),
					'ai_training_crawlers_enabled' => array( __( 'Model training access', 'lightweight-seo' ), $modules ),
					'enable_llms_txt'              => array( __( 'Curated llms.txt', 'lightweight-seo' ), $modules ),
					'llms_txt_post_ids'            => array( __( 'Pages to list', 'lightweight-seo' ), $modules ),
					'ai_readiness'                 => array( __( 'Readiness checks', 'lightweight-seo' ), $modules ),
				),
			),
		);
	}

	/**
	 * Validate a tracking ID against a strict format.
	 *
	 * @since    1.0.2
	 * @param    string     $value             The submitted value.
	 * @param    string     $pattern           The validation pattern.
	 * @param    string     $settings_error    The settings error code.
	 * @param    string     $message           The error message.
	 * @param    string     $existing_value    The existing saved value.
	 * @param    bool       $uppercase         Whether to normalize to uppercase.
	 * @return   string
	 */
	private function validate_tracking_id( $value, $pattern, $settings_error, $message, $existing_value = '', $uppercase = false ) {
		$sanitized_value = trim( sanitize_text_field( $value ) );

		if ( $uppercase ) {
			$sanitized_value = strtoupper( $sanitized_value );
		}

		if ( '' === $sanitized_value ) {
			return '';
		}

		if ( 1 === preg_match( $pattern, $sanitized_value ) ) {
			return $sanitized_value;
		}

		add_settings_error( LIGHTWEIGHT_SEO_OPTION_NAME, $settings_error, $message, 'error' );

		return $existing_value;
	}

	/**
	 * Count the lines of a submitted or stored value that hold something.
	 *
	 * @since    1.1.0
	 * @param    mixed    $value    Text with one item per line.
	 * @return   int
	 */
	private function count_lines( $value ) {
		return count( array_filter( array_map( 'trim', preg_split( "/\r\n|\n|\r/", (string) $value ) ), 'strlen' ) );
	}

	/**
	 * Keep the stored social image URL and attachment ID in sync.
	 *
	 * @since    1.0.2
	 * @param    string    $image_url             Submitted image URL.
	 * @param    int       $image_id              Submitted attachment ID.
	 * @param    string    $previous_image_url    Previously saved image URL.
	 * @param    int       $previous_image_id     Previously saved attachment ID.
	 * @return   array
	 */
	private function normalize_social_image( $image_url, $image_id, $previous_image_url = '', $previous_image_id = 0 ) {
		$image_url          = esc_url_raw( $image_url );
		$image_id           = absint( $image_id );
		$previous_image_url = esc_url_raw( $previous_image_url );
		$previous_image_id  = absint( $previous_image_id );

		if ( '' === $image_url ) {
			return array( $image_url, 0 );
		}

		if ( $image_id && $image_url !== $previous_image_url && $image_id === $previous_image_id ) {
			$attachment_url = wp_get_attachment_image_url( $image_id, 'full' );

			if ( empty( $attachment_url ) || $image_url !== $attachment_url ) {
				$image_id = 0;
			}
		}

		return array( $image_url, $image_id );
	}

	/**
	 * Sanitize and validate settings
	 *
	 * WordPress runs this callback for every update of the settings option, and
	 * twice when the option row does not exist yet. It therefore only derives the
	 * value to store and reports invalid input; imports, rollbacks, and deletions
	 * are separate requests handled by Lightweight_SEO_Admin_Tools.
	 *
	 * @since    1.0.0
	 * @param    array    $input    The settings array.
	 * @return   array
	 */
	public function validate_settings( $input ) {
		$existing_settings = $this->settings->get_all();
		$sanitized_input   = array();
		$active_tab        = sanitize_key( $input['settings_tab'] ?? 'all' );
		$checkbox_value    = function ( $key, $tab ) use ( $input, $existing_settings, $active_tab ) {
			if ( 'all' === $active_tab || $tab === $active_tab ) {
				// An unchecked box is absent from a form post; a stored '0' passed back in must stay off.
				return ! empty( $input[ $key ] ) ? '1' : '0';
			}

			return $existing_settings[ $key ] ?? '0';
		};

		if ( isset( $input['title_format'] ) ) {
			$sanitized_input['title_format'] = sanitize_text_field( $input['title_format'] );
		} else {
			$sanitized_input['title_format'] = $existing_settings['title_format'] ?? LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT;
		}

		if ( isset( $input['home_title_format'] ) ) {
			$sanitized_input['home_title_format'] = sanitize_text_field( $input['home_title_format'] );
		} else {
			$sanitized_input['home_title_format'] = $existing_settings['home_title_format'] ?? '%sitename% %sep% %tagline%';
		}

		if ( isset( $input['archive_title_format'] ) ) {
			$sanitized_input['archive_title_format'] = sanitize_text_field( $input['archive_title_format'] );
		} else {
			$sanitized_input['archive_title_format'] = $existing_settings['archive_title_format'] ?? '%title% %sep% %sitename%';
		}

		if ( isset( $input['search_title_format'] ) ) {
			$sanitized_input['search_title_format'] = sanitize_text_field( $input['search_title_format'] );
		} else {
			$sanitized_input['search_title_format'] = $existing_settings['search_title_format'] ?? 'Search Results for "%search%" %sep% %sitename%';
		}

		if ( isset( $input['meta_description'] ) ) {
			$sanitized_input['meta_description'] = sanitize_textarea_field( $input['meta_description'] );
		} else {
			$sanitized_input['meta_description'] = $existing_settings['meta_description'] ?? '';
		}

		if ( ! empty( $existing_settings['meta_keywords'] ) ) {
			$sanitized_input['meta_keywords'] = $existing_settings['meta_keywords'] ?? '';
		}

		$sanitized_input['noindex_search_results']           = $checkbox_value( 'noindex_search_results', 'indexation' );
		$sanitized_input['noindex_attachment_pages']         = $checkbox_value( 'noindex_attachment_pages', 'indexation' );
		$sanitized_input['noindex_author_archives']          = $checkbox_value( 'noindex_author_archives', 'indexation' );
		$sanitized_input['noindex_date_archives']            = $checkbox_value( 'noindex_date_archives', 'indexation' );
		$sanitized_input['enable_media_x_robots_headers']    = $checkbox_value( 'enable_media_x_robots_headers', 'indexation' );
		$sanitized_input['exclude_noindex_from_sitemaps']    = $checkbox_value( 'exclude_noindex_from_sitemaps', 'indexation' );
		$sanitized_input['exclude_redirected_from_sitemaps'] = $checkbox_value( 'exclude_redirected_from_sitemaps', 'indexation' );
		$sanitized_input['enable_schema_output']             = $checkbox_value( 'enable_schema_output', 'identity' );
		$sanitized_input['enable_local_business_schema']     = $checkbox_value( 'enable_local_business_schema', 'modules' );
		$sanitized_input['enable_hreflang_output']           = $checkbox_value( 'enable_hreflang_output', 'modules' );
		$sanitized_input['enable_hreflang_path_mirroring']   = $checkbox_value( 'enable_hreflang_path_mirroring', 'modules' );
		$sanitized_input['enable_auto_redirects']            = $checkbox_value( 'enable_auto_redirects', 'modules' );
		$sanitized_input['enable_ai_discovery']              = $checkbox_value( 'enable_ai_discovery', 'modules' );
		$sanitized_input['ai_search_crawlers_enabled']       = $checkbox_value( 'ai_search_crawlers_enabled', 'modules' );
		$sanitized_input['ai_training_crawlers_enabled']     = $checkbox_value( 'ai_training_crawlers_enabled', 'modules' );
		$sanitized_input['enable_llms_txt']                  = $checkbox_value( 'enable_llms_txt', 'modules' );
		$sanitized_input['delete_data_on_uninstall']         = $checkbox_value( 'delete_data_on_uninstall', 'tools' );
		$sanitized_input['default_max_image_preview']        = $this->settings->normalize_max_image_preview(
			$input['default_max_image_preview'] ?? ( $existing_settings['default_max_image_preview'] ?? 'large' ),
			'large'
		);

		$content_types = isset( $input['content_types'] ) && is_array( $input['content_types'] ) ? $input['content_types'] : array();

		// A row is accepted for a post type or taxonomy that exists; stored rows of the others are carried over.
		$sanitized_input['content_types'] = $this->settings->normalize_content_types(
			$content_types,
			$existing_settings['content_types'] ?? array(),
			array_filter( array_keys( (array) ( $content_types['post_types'] ?? array() ) ), 'post_type_exists' ),
			array_filter( array_keys( (array) ( $content_types['taxonomies'] ?? array() ) ), 'taxonomy_exists' )
		);

		if ( isset( $input['redirect_rules'] ) ) {
			$sanitized_input['redirect_rules'] = $this->settings->normalize_redirect_rules_input( $input['redirect_rules'] );
			$dropped                           = 'modules' === $active_tab ? $this->count_lines( $input['redirect_rules'] ) - $this->count_lines( $sanitized_input['redirect_rules'] ) : 0;

			if ( $dropped > 0 ) {
				/* translators: %d: number of redirect rules that were not saved. */
				add_settings_error( LIGHTWEIGHT_SEO_OPTION_NAME, 'redirect_rules_dropped', sprintf( _n( 'Settings saved. %d redirect rule was left out because it was incomplete, repeated another rule, looped, or pointed to a site that is not approved.', 'Settings saved. %d redirect rules were left out because they were incomplete, repeated another rule, looped, or pointed to a site that is not approved.', $dropped, 'lightweight-seo' ), $dropped ), 'warning' );
			}
		} else {
			$sanitized_input['redirect_rules'] = $existing_settings['redirect_rules'] ?? '';
		}

		if ( isset( $input['organization_same_as'] ) ) {
			$raw_lines       = preg_split( "/\r\n|\n|\r/", (string) $input['organization_same_as'] );
			$sanitized_lines = array();

			foreach ( $raw_lines as $line ) {
				$url = esc_url_raw( trim( $line ) );

				if ( empty( $url ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
					continue;
				}

				$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );

				if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
					continue;
				}

				if ( ! empty( $url ) ) {
					$sanitized_lines[] = $url;
				}
			}

			$sanitized_input['organization_same_as'] = implode( "\n", array_values( array_unique( $sanitized_lines ) ) );
		} else {
			$sanitized_input['organization_same_as'] = $existing_settings['organization_same_as'] ?? '';
		}

		$local_business_input                                 = array_merge( $existing_settings, $input );
		$local_business_input['enable_local_business_schema'] = $sanitized_input['enable_local_business_schema'];

		if ( method_exists( $this->settings, 'normalize_local_business_input' ) ) {
			$local_business  = $this->settings->normalize_local_business_input( $local_business_input );
			$sanitized_input = array_merge( $sanitized_input, $local_business['values'] );

			if ( ! empty( $local_business['errors'] ) && in_array( $active_tab, array( 'all', 'modules' ), true ) ) {
				foreach ( $local_business['errors'] as $index => $message ) {
					add_settings_error( LIGHTWEIGHT_SEO_OPTION_NAME, 'invalid_local_business_' . $index, $message, 'error' );
				}

				$sanitized_input['enable_local_business_schema'] = '0';
			}
		} else {
			foreach ( array( 'type', 'name', 'phone', 'price_range', 'address_street', 'address_locality', 'address_region', 'address_postal_code', 'address_country', 'latitude', 'longitude', 'opening_hours', 'image' ) as $field ) {
				$key                     = 'local_business_' . $field;
				$sanitized_input[ $key ] = sanitize_text_field( $local_business_input[ $key ] ?? '' );
			}
		}

		if ( isset( $input['hreflang_mappings'] ) ) {
			$sanitized_input['hreflang_mappings'] = $this->settings->normalize_hreflang_mappings_input( $input['hreflang_mappings'] );
			$dropped                              = 'modules' === $active_tab ? $this->count_lines( $input['hreflang_mappings'] ) - $this->count_lines( $sanitized_input['hreflang_mappings'] ) : 0;

			if ( $dropped > 0 ) {
				/* translators: %d: number of hreflang mappings that were not saved. */
				add_settings_error( LIGHTWEIGHT_SEO_OPTION_NAME, 'hreflang_mappings_dropped', sprintf( _n( 'Settings saved. %d translation was left out because its page, language tag, or address was not valid, or it repeated another line.', 'Settings saved. %d translations were left out because their page, language tag, or address was not valid, or they repeated another line.', $dropped, 'lightweight-seo' ), $dropped ), 'warning' );
			}
		} else {
			$sanitized_input['hreflang_mappings'] = $existing_settings['hreflang_mappings'] ?? '';
		}

		if ( isset( $input['llms_txt_post_ids'] ) ) {
			$sanitized_input['llms_txt_post_ids'] = $this->settings->normalize_llms_post_ids( $input['llms_txt_post_ids'] );
		} else {
			$sanitized_input['llms_txt_post_ids'] = $existing_settings['llms_txt_post_ids'] ?? '';
		}

		if ( empty( $input['delete_legacy_search_console_data'] ) && ! empty( $existing_settings['search_console_property'] ) ) {
			$sanitized_input['search_console_property'] = $existing_settings['search_console_property'] ?? '';
		}

		if ( empty( $input['delete_legacy_search_console_data'] ) && ! empty( $existing_settings['search_console_service_account_json'] ) ) {
			$sanitized_input['search_console_service_account_json'] = $existing_settings['search_console_service_account_json'] ?? '';
		}

		// Kept so the stored settings shape is unchanged; the Tools tab tracks the live source in the import state.
		$sanitized_input['import_source'] = sanitize_key( $input['import_source'] ?? ( $existing_settings['import_source'] ?? '' ) );

		if ( isset( $input['social_image'] ) ) {
			$sanitized_input['social_image'] = esc_url_raw( $input['social_image'] );
		} else {
			$sanitized_input['social_image'] = $existing_settings['social_image'] ?? '';
		}

		if ( isset( $input['social_image_id'] ) ) {
			$sanitized_input['social_image_id'] = absint( $input['social_image_id'] );
		} else {
			$sanitized_input['social_image_id'] = absint( $existing_settings['social_image_id'] ?? 0 );
		}

		list( $sanitized_input['social_image'], $sanitized_input['social_image_id'] ) = $this->normalize_social_image(
			$sanitized_input['social_image'],
			$sanitized_input['social_image_id'],
			$existing_settings['social_image'] ?? '',
			$existing_settings['social_image_id'] ?? 0
		);

		if ( isset( $input['ga4_measurement_id'] ) ) {
			$sanitized_input['ga4_measurement_id'] = $this->validate_tracking_id(
				$input['ga4_measurement_id'],
				'/^G-[A-Z0-9]+$/',
				'invalid_ga4_measurement_id',
				__( 'Invalid Google Analytics 4 Measurement ID. Use a value like G-XXXXXXXXXX.', 'lightweight-seo' ),
				$existing_settings['ga4_measurement_id'] ?? '',
				true
			);
		} else {
			$sanitized_input['ga4_measurement_id'] = $existing_settings['ga4_measurement_id'] ?? '';
		}

		if ( isset( $input['gtm_container_id'] ) ) {
			$sanitized_input['gtm_container_id'] = $this->validate_tracking_id(
				$input['gtm_container_id'],
				'/^GTM-[A-Z0-9]+$/',
				'invalid_gtm_container_id',
				__( 'Invalid Google Tag Manager Container ID. Use a value like GTM-XXXXXX.', 'lightweight-seo' ),
				$existing_settings['gtm_container_id'] ?? '',
				true
			);
		} else {
			$sanitized_input['gtm_container_id'] = $existing_settings['gtm_container_id'] ?? '';
		}

		if ( isset( $input['facebook_pixel_id'] ) ) {
			$sanitized_input['facebook_pixel_id'] = $this->validate_tracking_id(
				$input['facebook_pixel_id'],
				'/^\d+$/',
				'invalid_facebook_pixel_id',
				__( 'Invalid Facebook Pixel ID. Use a numeric value.', 'lightweight-seo' ),
				$existing_settings['facebook_pixel_id'] ?? ''
			);
		} else {
			$sanitized_input['facebook_pixel_id'] = $existing_settings['facebook_pixel_id'] ?? '';
		}

		foreach ( array( 'tracking_excluded_roles', 'tracking_excluded_environments' ) as $key ) {
			$value = $input[ $key ] ?? ( $existing_settings[ $key ] ?? '' );

			// The settings screen submits a list of ticked boxes; other callers may still pass one string.
			if ( is_array( $value ) ) {
				$value = implode( ' ', array_filter( $value, 'is_scalar' ) );
			}
			$keys = method_exists( $this->settings, 'normalize_key_list' ) ? $this->settings->normalize_key_list( $value ) : array_filter( array_map( 'sanitize_key', preg_split( '/[\s,]+/', strtolower( (string) $value ) ) ) );

			if ( 'tracking_excluded_environments' === $key ) {
				$keys = array_intersect( $keys, array( 'local', 'development', 'staging', 'production' ) );
			}

			$sanitized_input[ $key ] = implode( "\n", array_values( array_unique( $keys ) ) );
		}

		return $sanitized_input;
	}
}
