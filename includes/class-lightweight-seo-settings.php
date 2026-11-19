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

	/** Schema types a post type can be given. "WebPage" adds no node beyond the page itself. */
	const SCHEMA_TYPES = array( 'WebPage', 'Article', 'BlogPosting', 'NewsArticle' );

	/** Maximum number of explicit hreflang mappings stored in the bounded core option. */
	const MAX_HREFLANG_MAPPINGS = 500;

	/** Maximum number of manually curated pages exposed in llms.txt. */
	const MAX_LLMS_POSTS = 50;

	/** Maximum number of post type or taxonomy rows stored in the bounded core option. */
	const MAX_CONTENT_TYPE_ROWS = 100;

	/** Stored per-item choice that shows an item even when its content type is hidden. */
	const INDEX_OVERRIDE = 'index';

	/** Meta tag name each supported service reads its verification code from, keyed by service. */
	const VERIFICATION_TAGS = array(
		'google'    => 'google-site-verification',
		'bing'      => 'msvalidate.01',
		'pinterest' => 'p:domain_verify',
		'yandex'    => 'yandex-verification',
	);

	/** Characters the %sep% title variable may become. The first is the default. */
	const TITLE_SEPARATORS = array( '–', '—', '-', '|', '·', '•', '»', '/' );

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
			'title_separator'                    => LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR,
			'home_title_format'                  => '%sitename% %sep% %tagline%',
			'archive_title_format'               => '%title% %sep% %sitename%',
			'search_title_format'                => 'Search Results for "%search%" %sep% %sitename%',
			'meta_description'                   => '',
			'noindex_search_results'             => '1',
			'noindex_attachment_pages'           => '1',
			'noindex_author_archives'            => '0',
			'noindex_date_archives'              => '0',
			'content_types'                      => array(),
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
			'identity_type'                      => 'organization',
			'identity_name'                      => '',
			'identity_description'               => '',
			'identity_logo'                      => '',
			'identity_logo_id'                   => 0,
			'x_handle'                           => '',
			'organization_same_as'               => '',
			'verification_google'                => '',
			'verification_bing'                  => '',
			'verification_pinterest'             => '',
			'verification_yandex'                => '',
			'enable_hreflang_output'             => '0',
			'enable_hreflang_path_mirroring'     => '0',
			'hreflang_mappings'                  => '',
			'enable_redirects'                   => '0',
			'enable_auto_redirects'              => '0',
			'redirect_rules'                     => '',
			'import_source'                      => '',
			'default_max_image_preview'          => 'large',
			'social_image'                       => '',
			'social_image_id'                    => 0,
			'enable_tracking'                    => '0',
			'ga4_measurement_id'                 => '',
			'gtm_container_id'                   => '',
			'facebook_pixel_id'                  => '',
			'tracking_excluded_roles'            => 'administrator',
			'tracking_excluded_environments'     => "local\ndevelopment\nstaging",
			'enable_indexnow'                    => '0',
			'indexnow_key'                       => '',
			'enable_ai_discovery'                => '0',
			'ai_search_crawlers_enabled'         => '1',
			'ai_training_crawlers_enabled'       => '0',
			'enable_content_signals'             => '0',
			'enable_llms_txt'                    => '0',
			'llms_txt_post_ids'                  => '',
			'enable_markdown_on_request'         => '0',
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
	 * Get the character that the %sep% title variable becomes.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	public function get_title_separator() {
		return $this->normalize_title_separator( $this->get( 'title_separator', LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR ) );
	}

	/**
	 * Normalize a title separator to one of the supported characters.
	 *
	 * @since    1.1.0
	 * @param    mixed     $value       Submitted or stored separator.
	 * @param    string    $fallback    Separator used when the value is not supported.
	 * @return   string
	 */
	public function normalize_title_separator( $value, $fallback = LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR ) {
		return is_string( $value ) && in_array( $value, self::TITLE_SEPARATORS, true ) ? $value : $fallback;
	}

	/**
	 * Get what the site represents: an organization or a person.
	 *
	 * @since    1.1.0
	 * @return   string Either organization or person.
	 */
	public function get_identity_type() {
		return $this->normalize_identity_type( $this->get( 'identity_type', 'organization' ) );
	}

	/**
	 * Normalize the kind of entity the site represents.
	 *
	 * @since    1.1.0
	 * @param    mixed    $value    Submitted or stored type.
	 * @return   string Either organization or person.
	 */
	public function normalize_identity_type( $value ) {
		return 'person' === $value ? 'person' : 'organization';
	}

	/**
	 * Get the name of the organization or person, falling back to the site title.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	public function get_identity_name() {
		$name = trim( (string) $this->get( 'identity_name', '' ) );

		return '' !== $name ? $name : (string) get_bloginfo( 'name' );
	}

	/**
	 * Get the description of the organization or person.
	 *
	 * @since    1.2.0
	 * @return   string One line of text, or an empty string when none is written.
	 */
	public function get_identity_description() {
		return trim( (string) preg_replace( '/\s+/u', ' ', (string) $this->get( 'identity_description', '' ) ) );
	}

	/**
	 * Get the logo of the organization or the photo of the person.
	 *
	 * @since    1.1.0
	 * @return   string Image URL, or an empty string when none is chosen.
	 */
	public function get_identity_logo_url() {
		$image_id = absint( $this->get( 'identity_logo_id', 0 ) );

		if ( $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'full' );

			if ( ! empty( $image_url ) ) {
				return $image_url;
			}
		}

		return (string) $this->get( 'identity_logo', '' );
	}

	/**
	 * Get the site's X handle without its leading @.
	 *
	 * @since    1.1.0
	 * @return   string Handle, or an empty string when none is set.
	 */
	public function get_x_handle() {
		return (string) $this->normalize_x_handle( $this->get( 'x_handle', '' ) );
	}

	/**
	 * Reduce a typed X handle or profile address to the bare handle.
	 *
	 * @since    1.1.0
	 * @param    mixed    $value    A handle, with or without @, or an x.com or twitter.com profile address.
	 * @return   string|null Handle, an empty string for no handle, or null when the value is not a handle.
	 */
	public function normalize_x_handle( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( '' === $value ) {
			return '';
		}

		if ( 1 === preg_match( '#^(?:https?://)?(?:www\.|mobile\.)?(?:x|twitter)\.com/@?([A-Za-z0-9_]+)/?(?:[?\#].*)?$#i', $value, $matches ) ) {
			$value = $matches[1];
		}

		$value = ltrim( $value, '@' );

		return 1 === preg_match( '/^[A-Za-z0-9_]{1,15}$/', $value ) ? $value : null;
	}

	/**
	 * Get the verification codes that are set, keyed by the meta tag name each is printed under.
	 *
	 * @since    1.1.0
	 * @return   array<string,string>
	 */
	public function get_verification_codes() {
		$codes = array();

		foreach ( self::VERIFICATION_TAGS as $service => $tag ) {
			$code = (string) $this->normalize_verification_code( $this->get( 'verification_' . $service, '' ) );

			if ( '' !== $code ) {
				$codes[ $tag ] = $code;
			}
		}

		return $codes;
	}

	/**
	 * Reduce a pasted verification code, or the whole meta tag a service shows, to the code.
	 *
	 * @since    1.1.0
	 * @param    mixed    $value    A code, or a meta tag with the code in its content attribute.
	 * @return   string|null Code, an empty string for no code, or null when the value is not a code.
	 */
	public function normalize_verification_code( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( '' === $value ) {
			return '';
		}

		if ( 1 === preg_match( '/\bcontent\s*=\s*["\']([^"\']*)["\']/i', $value, $matches ) ) {
			$value = trim( $matches[1] );
		}

		// Nothing outside these characters is stored, so the code is safe to print as an attribute.
		return 1 === preg_match( '/^[A-Za-z0-9_\-]{1,100}$/', $value ) ? $value : null;
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
	 * Determine whether author archives should default to noindex.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function author_archives_noindex_enabled() {
		return '1' === (string) $this->get( 'noindex_author_archives', '0' );
	}

	/**
	 * Determine whether date archives should be noindexed.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function date_archives_noindex_enabled() {
		return '1' === (string) $this->get( 'noindex_date_archives', '0' );
	}

	/**
	 * Get the settings of one post type or taxonomy, with defaults for anything not stored.
	 *
	 * A row that was never saved shows the content type in search results, lists it in
	 * the sitemap, and inherits the site-wide title format. Attachment visibility lives
	 * in its own setting, which predates this table.
	 *
	 * @since    1.1.0
	 * @param    string    $group    Either post_types or taxonomies.
	 * @param    string    $name     Post type or taxonomy name.
	 * @return   array Row with noindex, sitemap, and title_format.
	 */
	public function get_content_type( $group, $name ) {
		$stored = $this->get( 'content_types', array() );
		$row    = is_array( $stored ) && isset( $stored[ $group ][ $name ] ) ? $this->normalize_content_type_row( $stored[ $group ][ $name ] ) : $this->normalize_content_type_row( array() );

		if ( 'post_types' === $group && 'attachment' === $name ) {
			$row['noindex'] = $this->attachment_pages_noindex_enabled() ? '1' : '0';
		}

		return $row;
	}

	/**
	 * Determine whether a post type is hidden from search results by default.
	 *
	 * @since    1.1.0
	 * @param    string    $post_type    Post type name.
	 * @return   bool
	 */
	public function is_post_type_noindex( $post_type ) {
		return '1' === $this->get_content_type( 'post_types', (string) $post_type )['noindex'];
	}

	/**
	 * Determine whether the archives of a taxonomy are hidden from search results by default.
	 *
	 * @since    1.1.0
	 * @param    string    $taxonomy    Taxonomy name.
	 * @return   bool
	 */
	public function is_taxonomy_noindex( $taxonomy ) {
		return '1' === $this->get_content_type( 'taxonomies', (string) $taxonomy )['noindex'];
	}

	/**
	 * Determine whether a post type may be listed in the XML sitemap.
	 *
	 * @since    1.1.0
	 * @param    string    $post_type    Post type name.
	 * @return   bool
	 */
	public function is_post_type_in_sitemap( $post_type ) {
		return '1' === $this->get_content_type( 'post_types', (string) $post_type )['sitemap'];
	}

	/**
	 * Determine whether a taxonomy may be listed in the XML sitemap.
	 *
	 * @since    1.1.0
	 * @param    string    $taxonomy    Taxonomy name.
	 * @return   bool
	 */
	public function is_taxonomy_in_sitemap( $taxonomy ) {
		return '1' === $this->get_content_type( 'taxonomies', (string) $taxonomy )['sitemap'];
	}

	/**
	 * Keep an IndexNow key only when it has the shape this plugin creates.
	 *
	 * @since    1.1.0
	 * @param    mixed    $value    Stored or submitted key.
	 * @return   string Key, or an empty string.
	 */
	public static function normalize_indexnow_key( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		return 1 === preg_match( '/^[a-f0-9]{32}$/', $value ) ? $value : '';
	}

	/**
	 * Create an IndexNow key for this site.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	public static function generate_indexnow_key() {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Get the schema type single items of a post type have when none is chosen.
	 *
	 * @since    1.1.0
	 * @param    string    $post_type    Post type name.
	 * @return   string "Article" for the post types of the lightweight_seo_article_post_types filter, otherwise "WebPage".
	 */
	public function get_default_schema_type( $post_type ) {
		$article_types = (array) apply_filters( 'lightweight_seo_article_post_types', array( 'post' ) );

		return in_array( (string) $post_type, $article_types, true ) ? 'Article' : 'WebPage';
	}

	/**
	 * Get the schema type of single items of a post type.
	 *
	 * @since    1.1.0
	 * @param    string    $post_type    Post type name.
	 * @return   string One of SCHEMA_TYPES.
	 */
	public function get_schema_type_for_post_type( $post_type ) {
		$chosen = 'attachment' === (string) $post_type ? '' : $this->get_content_type( 'post_types', (string) $post_type )['schema'];

		return '' !== $chosen ? $chosen : $this->get_default_schema_type( $post_type );
	}

	/**
	 * Get the title format of a post type, falling back to the default title format.
	 *
	 * @since    1.1.0
	 * @param    string    $post_type    Post type name.
	 * @return   string
	 */
	public function get_title_format_for_post_type( $post_type ) {
		$format = $this->get_content_type( 'post_types', (string) $post_type )['title_format'];

		return '' !== $format ? wp_specialchars_decode( $format, ENT_QUOTES ) : $this->get_title_format();
	}

	/**
	 * Get the title format of a taxonomy's archives, falling back to the archive title format.
	 *
	 * @since    1.1.0
	 * @param    string    $taxonomy    Taxonomy name.
	 * @return   string
	 */
	public function get_title_format_for_taxonomy( $taxonomy ) {
		$format = $this->get_content_type( 'taxonomies', (string) $taxonomy )['title_format'];

		return '' !== $format ? wp_specialchars_decode( $format, ENT_QUOTES ) : $this->get_archive_title_format();
	}

	/**
	 * Decide whether one page, term, or author is hidden from search results.
	 *
	 * The item's own choice wins in both directions. Without one, the item follows
	 * its content type.
	 *
	 * @since    1.1.0
	 * @param    mixed    $own_value      Stored per-item value: 1 hides, INDEX_OVERRIDE shows, anything else follows the type.
	 * @param    bool     $type_hidden    Whether the content type is hidden by default.
	 * @return   bool
	 */
	public function resolve_noindex( $own_value, $type_hidden ) {
		$own_value = (string) $own_value;

		if ( '1' === $own_value ) {
			return true;
		}

		if ( self::INDEX_OVERRIDE === $own_value ) {
			return false;
		}

		return (bool) $type_hidden;
	}

	/**
	 * Normalize a per-item search visibility choice for storage.
	 *
	 * @since    1.1.0
	 * @param    mixed    $value    Submitted value.
	 * @return   string 1 to hide, INDEX_OVERRIDE to show, or 0 to follow the content type.
	 */
	public function normalize_noindex_choice( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';

		return in_array( $value, array( '1', self::INDEX_OVERRIDE ), true ) ? $value : '0';
	}

	/**
	 * Normalize the content types table for storage.
	 *
	 * Only rows that differ from the defaults are kept, so an untouched table stores
	 * nothing. A stored row whose post type or taxonomy is not registered right now is
	 * carried over, so its settings survive a plugin being switched off and on.
	 *
	 * @since    1.1.0
	 * @param    mixed    $input         Submitted table.
	 * @param    mixed    $existing      Stored table.
	 * @param    array    $post_types    Post types that may be set.
	 * @param    array    $taxonomies    Taxonomies that may be set.
	 * @return   array
	 */
	public function normalize_content_types( $input, $existing, $post_types, $taxonomies ) {
		$input      = is_array( $input ) ? $input : array();
		$existing   = is_array( $existing ) ? $existing : array();
		$defaults   = $this->normalize_content_type_row( array() );
		$normalized = array();

		// A row is stored without a schema type unless one was chosen.
		unset( $defaults['schema'] );

		foreach ( array(
			'post_types' => $post_types,
			'taxonomies' => $taxonomies,
		) as $group => $registered ) {
			$registered = array_map( 'strval', (array) $registered );
			$submitted  = isset( $input[ $group ] ) && is_array( $input[ $group ] ) ? $input[ $group ] : array();
			$stored     = isset( $existing[ $group ] ) && is_array( $existing[ $group ] ) ? $existing[ $group ] : array();
			$rows       = array();

			foreach ( $registered as $name ) {
				if ( isset( $submitted[ $name ] ) ) {
					$rows[ $name ] = $this->normalize_content_type_row( $submitted[ $name ] );
				} elseif ( isset( $stored[ $name ] ) ) {
					$rows[ $name ] = $this->normalize_content_type_row( $stored[ $name ] );
				}
			}

			foreach ( $stored as $name => $row ) {
				$name = sanitize_key( $name );

				if ( '' !== $name && ! in_array( $name, $registered, true ) ) {
					$rows[ $name ] = $this->normalize_content_type_row( $row );
				}
			}

			foreach ( $rows as $name => $row ) {
				// A choice that equals the default is not stored, so the default can still change. Only post types have one.
				if ( 'post_types' !== $group || 'attachment' === $name || '' === $row['schema'] || $row['schema'] === $this->get_default_schema_type( $name ) ) {
					unset( $rows[ $name ]['schema'] );
				}
			}

			if ( isset( $rows['attachment'] ) && 'post_types' === $group ) {
				// Attachment visibility is stored in its own setting, and WordPress never lists attachments in a sitemap.
				$rows['attachment']['noindex'] = $defaults['noindex'];
				$rows['attachment']['sitemap'] = $defaults['sitemap'];
			}

			$rows = array_filter(
				$rows,
				static function ( $row ) use ( $defaults ) {
					return $row !== $defaults;
				}
			);

			if ( ! empty( $rows ) ) {
				$normalized[ $group ] = array_slice( $rows, 0, self::MAX_CONTENT_TYPE_ROWS, true );
			}
		}

		return $normalized;
	}

	/**
	 * Normalize one row of the content types table.
	 *
	 * @since    1.1.0
	 * @param    mixed    $row    Submitted or stored row.
	 * @return   array
	 */
	private function normalize_content_type_row( $row ) {
		$row   = is_array( $row ) ? $row : array();
		$value = static function ( $key, $fallback ) use ( $row ) {
			return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? (string) $row[ $key ] : $fallback;
		};

		return array(
			'noindex'      => '1' === $value( 'noindex', '0' ) ? '1' : '0',
			'sitemap'      => '0' === $value( 'sitemap', '1' ) ? '0' : '1',
			'title_format' => Lightweight_SEO_Text::sanitize_template( $value( 'title_format', '' ) ),
			// Empty means the default for the post type; see get_default_schema_type().
			'schema'       => in_array( $value( 'schema', '' ), self::SCHEMA_TYPES, true ) ? $value( 'schema', '' ) : '',
		);
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

	/** Return whether a Content-Signal line is added to the virtual robots.txt. */
	public function content_signals_enabled() {
		return '1' === (string) $this->get( 'enable_content_signals', '0' );
	}

	/** Return whether the optional curated llms.txt endpoint is enabled. */
	public function llms_txt_enabled() {
		return '1' === (string) $this->get( 'enable_llms_txt', '0' );
	}

	/**
	 * Return whether a request that prefers text/markdown is answered with Markdown.
	 *
	 * @since 1.3.0
	 */
	public function markdown_on_request_enabled() {
		return '1' === (string) $this->get( 'enable_markdown_on_request', '0' );
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

	/** Tell whether any stored mapping is a retained legacy line that names no page of its own. */
	public function has_legacy_hreflang_mirrors() {
		foreach ( preg_split( "/\r\n|\n|\r/", (string) $this->get( 'hreflang_mappings', '' ) ) as $line ) {
			$mapping = $this->parse_hreflang_mapping_line( $line );

			if ( ! empty( $mapping ) && 'mirror' === $mapping['object_type'] ) {
				return true;
			}
		}

		return false;
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
		return $this->parse_redirect_rules( $this->get( 'redirect_rules', '' ) );
	}

	/**
	 * Parse redirect rule text, one rule per line, leaving out lines that are not rules.
	 *
	 * @since    1.1.0
	 * @param    string    $text    Rule lines.
	 * @return   array
	 */
	public function parse_redirect_rules( $text ) {
		$lines = preg_split( "/\r\n|\n|\r/", (string) $text );
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
				array( __CLASS__, 'format_redirect_rule' ),
				$normalized_rules
			)
		);
	}

	/**
	 * Write one parsed rule as the line it is stored as.
	 *
	 * @since    1.1.0
	 * @param    array    $rule    Parsed rule.
	 * @return   string
	 */
	public static function format_redirect_rule( $rule ) {
		if ( 410 === (int) $rule['status'] ) {
			return $rule['source'] . ' 410';
		}

		return $rule['source'] . ' ' . $rule['target'] . ' ' . $rule['status'];
	}

	/**
	 * Parse a redirect rule line into a normalized rule array.
	 *
	 * A line is "source target status". A source ending in "/*" covers that path and
	 * everything under it, and a target ending in "*" then receives the rest of the
	 * requested path. "source 410" answers that the address is gone, with no target.
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

		$raw_source = (string) array_shift( $parts );
		$is_prefix  = '/*' === substr( $raw_source, -2 );
		$source     = $this->normalize_redirect_path( $is_prefix ? substr( $raw_source, 0, -2 ) : $raw_source );

		// A rule covering the whole site would also cover the address it sends visitors to.
		if ( empty( $source ) || ( $is_prefix && '/' === $source ) ) {
			return array();
		}

		$stored_source = $source . ( $is_prefix ? '/*' : '' );

		if ( '410' === (string) $parts[0] || '410' === (string) ( $parts[1] ?? '' ) ) {
			return array(
				'source' => $stored_source,
				'target' => '',
				'status' => 410,
			);
		}

		$target = (string) array_shift( $parts );
		$status = absint( $parts[0] ?? 301 );
		$carry  = $is_prefix && '*' === substr( $target, -1 );

		if ( $carry ) {
			$target = rtrim( substr( $target, 0, -1 ), '/' );
			$target = '' === $target ? '/' : $target;
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

		// Carrying the rest of the path into the covered area would grow the address on every hop.
		if ( $carry && 0 === strpos( $target . '/', $source . '/' ) ) {
			return array();
		}

		if ( $carry ) {
			$target = rtrim( $target, '/' ) . '/*';
		}

		return array(
			'source' => $stored_source,
			'target' => $target,
			'status' => $status,
		);
	}

	/**
	 * Sort rules into exact rules keyed by path and prefix rules, longest first.
	 *
	 * The first rule for a source wins, so callers list the rules that take precedence first.
	 *
	 * @since    1.1.0
	 * @param    array    $rules    Parsed rules.
	 * @return   array{exact:array,prefix:array}
	 */
	public static function index_redirect_rules( $rules ) {
		$index = array(
			'exact'  => array(),
			'prefix' => array(),
		);

		foreach ( $rules as $rule ) {
			$kind = '/*' === substr( $rule['source'], -2 ) ? 'prefix' : 'exact';

			if ( ! isset( $index[ $kind ][ $rule['source'] ] ) ) {
				$index[ $kind ][ $rule['source'] ] = $rule;
			}
		}

		uksort(
			$index['prefix'],
			static function ( $left, $right ) {
				return strlen( $right ) - strlen( $left );
			}
		);

		return $index;
	}

	/**
	 * Find the rule that applies to one path: an exact rule first, then the longest prefix.
	 *
	 * @since    1.1.0
	 * @param    string    $path     Normalized request path.
	 * @param    array     $index    Rules from index_redirect_rules().
	 * @return   array Rule with its target resolved for this path, plus "rule" (the source as stored), or an empty array.
	 */
	public static function resolve_redirect_hop( $path, $index ) {
		if ( isset( $index['exact'][ $path ] ) ) {
			return $index['exact'][ $path ] + array( 'rule' => $path );
		}

		foreach ( $index['prefix'] as $source => $rule ) {
			$prefix = substr( $source, 0, -2 );

			if ( $path !== $prefix && 0 !== strpos( $path, $prefix . '/' ) ) {
				continue;
			}

			if ( '*' === substr( $rule['target'], -1 ) ) {
				$target         = rtrim( substr( $rule['target'], 0, -1 ), '/' ) . substr( $path, strlen( $prefix ) );
				$rule['target'] = '' === $target ? '/' : $target;
			}

			return $rule + array( 'rule' => $source );
		}

		return array();
	}

	/**
	 * Follow a path through the rules, one hop at a time.
	 *
	 * @since    1.1.0
	 * @param    string    $path     Normalized request path.
	 * @param    array     $index    Rules from index_redirect_rules().
	 * @return   array{hops:array,loop:bool} Each hop is a resolved rule; "loop" is true when the path never settles.
	 */
	public static function trace_redirect_hops( $path, $index ) {
		$hops    = array();
		$visited = array( $path => true );

		while ( true ) {
			$hop = self::resolve_redirect_hop( $path, $index );

			// A prefix rule that points at an address it covers leaves that address where it is.
			if ( empty( $hop ) || ( 410 !== (int) $hop['status'] && $hop['target'] === $path ) ) {
				break;
			}

			$hops[] = $hop;
			$path   = $hop['target'];

			// A gone address and another site both end the trail.
			if ( 410 === (int) $hop['status'] || 0 !== strpos( $path, '/' ) ) {
				break;
			}

			if ( isset( $visited[ $path ] ) || count( $hops ) >= 10 ) {
				return array(
					'hops' => $hops,
					'loop' => true,
				);
			}

			$visited[ $path ] = true;
		}

		return array(
			'hops' => $hops,
			'loop' => false,
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
		$index = self::index_redirect_rules( $rules );

		return array_values(
			array_filter(
				$rules,
				function ( $rule ) use ( $index ) {
					// A prefix rule is followed from an address under it.
					$start = '/*' === substr( $rule['source'], -2 ) ? substr( $rule['source'], 0, -2 ) . '/lightweight-seo-probe' : $rule['source'];
					$trace = self::trace_redirect_hops( $start, $index );

					return ! $trace['loop'];
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
