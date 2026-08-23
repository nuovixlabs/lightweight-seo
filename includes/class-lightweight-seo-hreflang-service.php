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
		$robots        = $context['robots'] ?? array();
		$noindex       = is_array( $robots ) ? in_array( 'noindex', $robots, true ) : false !== strpos( (string) $robots, 'noindex' );

		if ( empty( $canonical_url ) || $noindex ) {
			return array();
		}

		$provider_links = $this->get_multilingual_provider_links();

		if ( ! empty( $provider_links ) ) {
			return $this->normalize_links( $provider_links );
		}

		$request_path = (string) wp_parse_url( $canonical_url, PHP_URL_PATH );
		$request_path = '/' . ltrim( $request_path, '/' );

		if ( '/' !== $request_path ) {
			$request_path = rtrim( $request_path, '/' );
		}

		$reference   = $this->get_current_object_reference( $context );
		$links       = array();
		$self_locale = $this->normalize_language( str_replace( '_', '-', sanitize_text_field( function_exists( 'get_locale' ) ? get_locale() : 'en-US' ) ) );
		$links[]     = array(
			'hreflang' => $self_locale,
			'href'     => $canonical_url,
		);

		foreach ( $this->settings->get_hreflang_mappings() as $mapping ) {
			$mapping_type = sanitize_key( $mapping['object_type'] ?? 'mirror' );
			$mapping_id   = absint( $mapping['object_id'] ?? 0 );

			if ( 'mirror' === $mapping_type ) {
				if ( ! method_exists( $this->settings, 'hreflang_path_mirroring_enabled' ) || ! $this->settings->hreflang_path_mirroring_enabled() ) {
					continue;
				}
			} elseif ( $mapping_type !== $reference['type'] || $mapping_id !== $reference['id'] ) {
				continue;
			}

			$language = $this->normalize_language( $mapping['language'] ?? '' );
			$base_url = esc_url_raw( $mapping['url'] ?? '' );

			if ( empty( $language ) || empty( $base_url ) ) {
				continue;
			}

			if ( 'mirror' !== $mapping_type && ! $this->explicit_target_is_valid( $base_url, $canonical_url, $self_locale ) ) {
				continue;
			}

			$links[] = array(
				'hreflang' => $language,
				'href'     => 'mirror' === $mapping_type ? $this->build_mirrored_url( $base_url, $request_path ) : $base_url,
			);
		}

		$links = $this->normalize_links( $links );

		return count( $links ) > 1 ? $links : array();
	}

	/** Whether an authoritative multilingual plugin should own hreflang output. */
	public function multilingual_provider_active() {
		return defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'pll_the_languages' );
	}

	/**
	 * Build a page-level alternate URL from a configured base.
	 *
	 * @since    1.1.0
	 * @param    string    $base_url        Configured alternate URL.
	 * @param    string    $request_path    Current canonical path.
	 * @return   string
	 */
	private function build_mirrored_url( $base_url, $request_path ) {
		if ( false !== strpos( $base_url, '%path%' ) ) {
			return str_replace( '%path%', ltrim( $request_path, '/' ), $base_url );
		}

		$base_path = (string) wp_parse_url( $base_url, PHP_URL_PATH );

		if ( '' === $base_path || '/' === $base_path ) {
			return rtrim( $base_url, '/' ) . $request_path;
		}

		return $base_url;
	}

	/** Read current-page translations from WPML or Polylang without duplicating their output. */
	private function get_multilingual_provider_links() {
		$links = array();

		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			$languages = (array) apply_filters( 'wpml_active_languages', array(), array( 'skip_missing' => 1 ) );

			foreach ( $languages as $language ) {
				$links[] = array(
					'hreflang' => $language['default_locale'] ?? ( $language['language_code'] ?? '' ),
					'href'     => $language['url'] ?? '',
				);
			}
		} elseif ( function_exists( 'pll_the_languages' ) ) {
			$languages = (array) pll_the_languages(
				array(
					'raw'                    => 1,
					'hide_if_no_translation' => 1,
				)
			);

			foreach ( $languages as $language ) {
				if ( ! empty( $language['no_translation'] ) ) {
					continue;
				}

				$links[] = array(
					'hreflang' => $language['locale'] ?? ( $language['slug'] ?? '' ),
					'href'     => $language['url'] ?? '',
				);
			}
		}

		return $links;
	}

	/** Normalize language/URL pairs and reject duplicate codes or destinations. */
	private function normalize_links( $links ) {
		$normalized = array();
		$seen_codes = array();
		$seen_urls  = array();

		foreach ( $links as $link ) {
			$language = $this->normalize_language( $link['hreflang'] ?? '' );
			$url      = esc_url_raw( $link['href'] ?? '' );
			$code_key = strtolower( $language );
			$url_key  = strtolower( untrailingslashit( $url ) );

			if ( empty( $language ) || empty( $url ) || isset( $seen_codes[ $code_key ] ) || isset( $seen_urls[ $url_key ] ) ) {
				continue;
			}

			$seen_codes[ $code_key ] = true;
			$seen_urls[ $url_key ]   = true;
			$normalized[]            = array(
				'hreflang' => $language,
				'href'     => $url,
			);
		}

		return $normalized;
	}

	/** Normalize the supported BCP 47 language/script/region/variant structure. */
	private function normalize_language( $language ) {
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

	/** Resolve the current post, term, or author reference without path inference. */
	private function get_current_object_reference( $context ) {
		if ( ! empty( $context['object_type'] ) && ! empty( $context['object_id'] ) ) {
			return array(
				'type' => sanitize_key( $context['object_type'] ),
				'id'   => absint( $context['object_id'] ),
			);
		}

		if ( function_exists( 'is_singular' ) && is_singular() ) {
			return array(
				'type' => 'post',
				'id'   => absint( get_queried_object_id() ),
			);
		}

		if ( ( function_exists( 'is_category' ) && is_category() ) || ( function_exists( 'is_tag' ) && is_tag() ) || ( function_exists( 'is_tax' ) && is_tax() ) ) {
			return array(
				'type' => 'term',
				'id'   => absint( get_queried_object_id() ),
			);
		}

		if ( function_exists( 'is_author' ) && is_author() ) {
			return array(
				'type' => 'user',
				'id'   => absint( get_queried_object_id() ),
			);
		}

		return array(
			'type' => '',
			'id'   => 0,
		);
	}

	/**
	 * Verify local post targets without remote requests.
	 *
	 * External targets remain explicit administrator choices. Local post targets
	 * must be published, canonical, indexable, not redirected, and reciprocal.
	 */
	private function explicit_target_is_valid( $target_url, $canonical_url, $self_locale ) {
		$target_host = strtolower( (string) wp_parse_url( $target_url, PHP_URL_HOST ) );
		$home_host   = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

		if ( empty( $target_host ) || $target_host !== $home_host || ! function_exists( 'url_to_postid' ) ) {
			return true;
		}

		$target_id = absint( url_to_postid( $target_url ) );

		if ( ! $target_id || ( function_exists( 'get_post_status' ) && 'publish' !== get_post_status( $target_id ) ) ) {
			return false;
		}

		if ( function_exists( 'lightweight_seo_get_api' ) ) {
			$api     = lightweight_seo_get_api();
			$context = $api ? $api->get_object_context( 'post', $target_id ) : array();

			if ( empty( $context['indexable'] ) || untrailingslashit( (string) ( $context['canonical_url'] ?? '' ) ) !== untrailingslashit( $target_url ) ) {
				return false;
			}
		}

		if ( class_exists( 'Lightweight_SEO_Redirects_Service', false ) && method_exists( $this->settings, 'get_manual_redirect_rules' ) ) {
			$redirects = new Lightweight_SEO_Redirects_Service( $this->settings, false );
			$path      = (string) wp_parse_url( $target_url, PHP_URL_PATH );

			if ( ! empty( $redirects->find_matching_redirect( $path ) ) ) {
				return false;
			}
		}

		foreach ( $this->settings->get_hreflang_mappings() as $candidate ) {
			if ( 'post' === ( $candidate['object_type'] ?? '' )
				&& absint( $candidate['object_id'] ?? 0 ) === $target_id
				&& strtolower( $self_locale ) === strtolower( (string) ( $candidate['language'] ?? '' ) )
				&& untrailingslashit( $canonical_url ) === untrailingslashit( (string) ( $candidate['url'] ?? '' ) ) ) {
				return true;
			}
		}

		return false;
	}
}
