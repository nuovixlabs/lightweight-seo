<?php
/**
 * Frontend page context service for Lightweight SEO.
 *
 * @since      1.0.2
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Frontend page context service.
 */
class Lightweight_SEO_Page_Context_Service {

	/**
	 * Shared settings service.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      Lightweight_SEO_Settings    $settings
	 */
	private $settings;

	/**
	 * Shared post meta service.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      Lightweight_SEO_Post_Meta    $post_meta
	 */
	private $post_meta;

	/**
	 * Cached page context for the current request.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      array|null    $context
	 */
	private $context;

	/**
	 * Initialize the service.
	 *
	 * @since    1.0.2
	 * @param    Lightweight_SEO_Settings     $settings     Shared settings service.
	 * @param    Lightweight_SEO_Post_Meta    $post_meta    Shared post meta service.
	 */
	public function __construct( $settings, $post_meta ) {
		$this->settings  = $settings;
		$this->post_meta = $post_meta;
	}

	/**
	 * Get the resolved SEO context for the current request.
	 *
	 * @since    1.0.2
	 * @return   array
	 */
	public function get_context() {
		if ( null === $this->context ) {
			$this->context = $this->build_context();
		}

		return $this->context;
	}

	/**
	 * Build the current request context.
	 *
	 * @since    1.0.2
	 * @return   array
	 */
	private function build_context() {
		$settings = $this->settings->get_all();
		$context  = array(
			'document_title'   => '',
			'description'      => $settings['meta_description'],
			'keywords'         => $settings['meta_keywords'],
			'keywords_enabled' => $this->settings->meta_keywords_enabled(),
			'robots'           => '',
			'og_title'         => get_bloginfo( 'name' ),
			'og_description'   => $settings['meta_description'],
			'og_image'         => $this->settings->get_social_image_url(),
			'og_type'          => 'website',
			'og_url'           => $this->get_current_url(),
			'twitter_card'     => 'summary_large_image',
		);

		if ( is_singular() ) {
			$post_id = get_queried_object_id();

			if ( $post_id ) {
				$post_meta = $this->post_meta->get_all( $post_id );

				$context['document_title'] = ! empty( $post_meta['seo_title'] ) ? $post_meta['seo_title'] : str_replace(
					array( '%title%', '%sitename%', '%tagline%', '%sep%' ),
					array(
						get_the_title( $post_id ),
						get_bloginfo( 'name' ),
						get_bloginfo( 'description' ),
						LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR,
					),
					$this->settings->get_title_format()
				);

				if ( ! empty( $post_meta['seo_description'] ) ) {
					$context['description'] = $post_meta['seo_description'];
				}

				if ( ! empty( $post_meta['seo_keywords'] ) ) {
					$context['keywords'] = $post_meta['seo_keywords'];
				}

				if ( '1' === $post_meta['seo_noindex'] ) {
					$context['robots'] = 'noindex, nofollow';
				}

				$context['og_title']       = ! empty( $post_meta['social_title'] ) ? $post_meta['social_title'] : $context['document_title'];
				$context['og_description'] = ! empty( $post_meta['social_description'] ) ? $post_meta['social_description'] : $context['description'];
				$context['og_url']         = get_permalink( $post_id );
				$context['og_type']        = is_single() ? 'article' : 'website';

				$post_social_image = $this->post_meta->get_social_image_url( $post_id );

				if ( ! empty( $post_social_image ) ) {
					$context['og_image'] = $post_social_image;
				} elseif ( has_post_thumbnail( $post_id ) ) {
					$context['og_image'] = get_the_post_thumbnail_url( $post_id, 'large' );
				}
			}

			return apply_filters( 'lightweight_seo_page_context', $context );
		}

		if ( is_home() || is_front_page() ) {
			$context['og_title']       = get_bloginfo( 'name' );
			$context['og_description'] = $context['description'];
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term                      = get_queried_object();
			$context['og_title']       = $term->name;
			$context['og_description'] = ! empty( $term->description ) ? $term->description : $context['description'];
		} elseif ( is_archive() ) {
			$context['og_title']       = get_the_archive_title();
			$context['og_description'] = get_the_archive_description() ? get_the_archive_description() : $context['description'];
		} elseif ( is_search() ) {
			$context['og_title']       = __( 'Search Results for', 'lightweight-seo' ) . ' "' . get_search_query() . '"';
			$context['og_description'] = $context['description'];
		}

		return apply_filters( 'lightweight_seo_page_context', $context );
	}

	/**
	 * Get the current page URL.
	 *
	 * @since    1.0.2
	 * @return   string
	 */
	private function get_current_url() {
		if ( is_singular() ) {
			$post_id = get_queried_object_id();

			if ( $post_id ) {
				return get_permalink( $post_id );
			}
		}

		return home_url( add_query_arg( array(), $GLOBALS['wp']->request ) );
	}
}
