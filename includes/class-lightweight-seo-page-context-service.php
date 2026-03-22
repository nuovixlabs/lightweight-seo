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
	 * Shared archive meta service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Archive_Meta    $archive_meta
	 */
	private $archive_meta;

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
	 * @param    Lightweight_SEO_Settings        $settings        Shared settings service.
	 * @param    Lightweight_SEO_Post_Meta       $post_meta       Shared post meta service.
	 * @param    Lightweight_SEO_Archive_Meta    $archive_meta    Shared archive meta service.
	 */
	public function __construct( $settings, $post_meta, $archive_meta ) {
		$this->settings     = $settings;
		$this->post_meta    = $post_meta;
		$this->archive_meta = $archive_meta;
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
			'canonical_url'    => $this->get_current_url(),
			'robots'           => $this->build_default_robots_directives(),
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
				$post           = get_post( $post_id );
				$post_meta      = $this->post_meta->get_all( $post_id );
				$title_template = is_front_page() ? $this->settings->get_home_title_format() : $this->settings->get_title_format();

				$context['document_title'] = ! empty( $post_meta['seo_title'] ) ? $post_meta['seo_title'] : $this->replace_title_template_vars(
					$title_template,
					array(
						'title' => get_the_title( $post_id ),
					)
				);

				if ( ! empty( $post_meta['seo_description'] ) ) {
					$context['description'] = $post_meta['seo_description'];
				}

				if ( ! empty( $post_meta['seo_keywords'] ) ) {
					$context['keywords'] = $post_meta['seo_keywords'];
				}

				$context['canonical_url'] = ! empty( $post_meta['seo_canonical_url'] ) ? $post_meta['seo_canonical_url'] : get_permalink( $post_id );

				$context['robots'] = $this->build_robots_directives( $post_meta, true );

				if ( $this->should_noindex_attachment_page( $post ) ) {
					$context['robots'] = $this->ensure_robots_directive( $context['robots'], 'noindex' );
				}

				if ( empty( $context['canonical_url'] ) ) {
					$context['canonical_url'] = get_permalink( $post_id );
				}

				$context['og_title']       = ! empty( $post_meta['social_title'] ) ? $post_meta['social_title'] : $context['document_title'];
				$context['og_description'] = ! empty( $post_meta['social_description'] ) ? $post_meta['social_description'] : $context['description'];
				$context['og_url']         = $context['canonical_url'];
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
			$context['document_title'] = $this->replace_title_template_vars(
				$this->settings->get_home_title_format(),
				array(
					'title' => get_bloginfo( 'name' ),
				)
			);
			$context['og_title']       = $context['document_title'];
			$context['og_description'] = $context['description'];
		} elseif ( is_search() ) {
			$context['document_title'] = $this->replace_title_template_vars(
				$this->settings->get_search_title_format(),
				array(
					'title'  => get_search_query(),
					'search' => get_search_query(),
				)
			);
			$context['og_title']       = $context['document_title'];
			$context['og_description'] = $context['description'];
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term                      = get_queried_object();
			$term_meta                 = ! empty( $term->term_id ) ? $this->archive_meta->get_term_all( $term->term_id ) : array();
			$term_title                = isset( $term->name ) ? $term->name : '';
			$context['document_title'] = ! empty( $term_meta['seo_title'] ) ? $term_meta['seo_title'] : $this->replace_title_template_vars(
				$this->settings->get_archive_title_format(),
				array(
					'title' => $term_title,
				)
			);
			$context['description']    = ! empty( $term_meta['seo_description'] ) ? $term_meta['seo_description'] : ( ! empty( $term->description ) ? $term->description : $context['description'] );
			$context['canonical_url']  = ! empty( $term_meta['seo_canonical_url'] ) ? $term_meta['seo_canonical_url'] : $this->get_term_archive_url( $term, $context['canonical_url'] );
			$context['robots']         = $this->build_robots_directives( $term_meta );
			$context['og_title']       = $context['document_title'];
			$context['og_description'] = $context['description'];
			$context['og_url']         = $context['canonical_url'];
		} elseif ( is_author() ) {
			$author                    = get_queried_object();
			$author_id                 = isset( $author->ID ) ? (int) $author->ID : get_queried_object_id();
			$author_meta               = $author_id ? $this->archive_meta->get_user_all( $author_id ) : array();
			$author_name               = $this->get_author_display_name( $author, $author_id );
			$context['document_title'] = ! empty( $author_meta['seo_title'] ) ? $author_meta['seo_title'] : $this->replace_title_template_vars(
				$this->settings->get_archive_title_format(),
				array(
					'title' => $author_name,
				)
			);
			$context['description']    = ! empty( $author_meta['seo_description'] ) ? $author_meta['seo_description'] : $this->get_author_description( $author, $author_id, $context['description'] );
			$context['canonical_url']  = ! empty( $author_meta['seo_canonical_url'] ) ? $author_meta['seo_canonical_url'] : $this->get_author_archive_url( $author_id, $context['canonical_url'] );
			$context['robots']         = $this->build_robots_directives( $author_meta );
			$context['og_title']       = $context['document_title'];
			$context['og_description'] = $context['description'];
			$context['og_url']         = $context['canonical_url'];
		} elseif ( is_archive() ) {
			$archive_title             = get_the_archive_title();
			$archive_description       = get_the_archive_description();
			$context['document_title'] = $this->replace_title_template_vars(
				$this->settings->get_archive_title_format(),
				array(
					'title' => $archive_title,
				)
			);
			$context['description']    = ! empty( $archive_description ) ? $archive_description : $context['description'];
			$context['og_title']       = $context['document_title'];
			$context['og_description'] = $context['description'];
			$context['og_url']         = $context['canonical_url'];
		}

		return apply_filters( 'lightweight_seo_page_context', $context );
	}

	/**
	 * Build the default robots directives for the current request.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	private function build_default_robots_directives() {
		$directives = array();

		if ( is_search() && $this->settings->search_results_noindex_enabled() ) {
			$directives[] = 'noindex';
		}

		$max_image_preview = $this->settings->get_default_max_image_preview();

		if ( ! empty( $max_image_preview ) ) {
			$directives[] = 'max-image-preview:' . $max_image_preview;
		}

		return implode( ', ', $directives );
	}

	/**
	 * Build singular robots directives, preserving legacy noindex behavior.
	 *
	 * @since    1.1.0
	 * @param    array    $post_meta    Resolved SEO post meta.
	 * @return   string
	 */
	private function build_robots_directives( $meta_values, $preserve_legacy_nofollow = false ) {
		$directives = array();

		if ( '1' === (string) ( $meta_values['seo_noindex'] ?? '' ) ) {
			$directives[] = 'noindex';
		}

		if ( '1' === (string) ( $meta_values['seo_nofollow'] ?? '' ) || ( $preserve_legacy_nofollow && $this->should_apply_legacy_nofollow( $meta_values ) ) ) {
			$directives[] = 'nofollow';
		}

		if ( '1' === (string) ( $meta_values['seo_noarchive'] ?? '' ) ) {
			$directives[] = 'noarchive';
		}

		if ( '1' === (string) ( $meta_values['seo_nosnippet'] ?? '' ) ) {
			$directives[] = 'nosnippet';
		}

		$max_image_preview = $this->settings->get_default_max_image_preview();

		if ( ! empty( $meta_values['seo_max_image_preview'] ) ) {
			$max_image_preview = $this->settings->normalize_max_image_preview( $meta_values['seo_max_image_preview'], $max_image_preview );
		}

		if ( ! empty( $max_image_preview ) ) {
			$directives[] = 'max-image-preview:' . $max_image_preview;
		}

		return implode( ', ', $directives );
	}

	/**
	 * Determine whether the current singular object should default to attachment noindex.
	 *
	 * @since    1.1.0
	 * @param    object|null    $post    Current queried post object.
	 * @return   bool
	 */
	private function should_noindex_attachment_page( $post ) {
		return ! empty( $post ) && 'attachment' === (string) ( $post->post_type ?? '' ) && $this->settings->attachment_pages_noindex_enabled();
	}

	/**
	 * Ensure a robots directive is present without duplicating it.
	 *
	 * @since    1.1.0
	 * @param    string    $robots       Existing robots string.
	 * @param    string    $directive    Directive to ensure.
	 * @return   string
	 */
	private function ensure_robots_directive( $robots, $directive ) {
		$directives = array_filter(
			array_map(
				'trim',
				explode( ',', (string) $robots )
			)
		);

		if ( ! in_array( $directive, $directives, true ) ) {
			array_unshift( $directives, $directive );
		}

		return implode( ', ', $directives );
	}

	/**
	 * Preserve the previous noindex=>nofollow behavior until content is resaved.
	 *
	 * @since    1.1.0
	 * @param    array    $post_meta    Resolved SEO post meta.
	 * @return   bool
	 */
	private function should_apply_legacy_nofollow( $post_meta ) {
		if ( '1' !== (string) ( $post_meta['seo_noindex'] ?? '' ) ) {
			return false;
		}

		if ( '' !== (string) ( $post_meta['seo_nofollow'] ?? '' ) ) {
			return false;
		}

		$advanced_fields = array(
			$post_meta['seo_noarchive'] ?? '',
			$post_meta['seo_nosnippet'] ?? '',
			$post_meta['seo_max_image_preview'] ?? '',
		);

		foreach ( $advanced_fields as $field_value ) {
			if ( '' !== (string) $field_value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Replace supported title template variables.
	 *
	 * @since    1.1.0
	 * @param    string    $template    Raw template string.
	 * @param    array     $variables   Template values.
	 * @return   string
	 */
	private function replace_title_template_vars( $template, $variables = array() ) {
		$replacements = array(
			'%title%'    => $variables['title'] ?? '',
			'%search%'   => $variables['search'] ?? '',
			'%sitename%' => get_bloginfo( 'name' ),
			'%tagline%'  => get_bloginfo( 'description' ),
			'%sep%'      => LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR,
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Resolve a term archive URL when available.
	 *
	 * @since    1.1.0
	 * @param    object    $term        Current queried term object.
	 * @param    string    $fallback    Fallback URL.
	 * @return   string
	 */
	private function get_term_archive_url( $term, $fallback ) {
		if ( empty( $term ) || empty( $term->taxonomy ) || empty( $term->term_id ) || ! function_exists( 'get_term_link' ) ) {
			return $fallback;
		}

		$term_link = get_term_link( $term );

		if ( ! is_wp_error( $term_link ) ) {
			return $term_link;
		}

		return $fallback;
	}

	/**
	 * Resolve an author archive URL when available.
	 *
	 * @since    1.1.0
	 * @param    int       $author_id    Current author ID.
	 * @param    string    $fallback     Fallback URL.
	 * @return   string
	 */
	private function get_author_archive_url( $author_id, $fallback ) {
		if ( empty( $author_id ) || ! function_exists( 'get_author_posts_url' ) ) {
			return $fallback;
		}

		return get_author_posts_url( $author_id );
	}

	/**
	 * Resolve the current author display name.
	 *
	 * @since    1.1.0
	 * @param    object    $author       Current queried author object.
	 * @param    int       $author_id    Current author ID.
	 * @return   string
	 */
	private function get_author_display_name( $author, $author_id ) {
		$display_name = '';

		if ( $author_id && function_exists( 'get_the_author_meta' ) ) {
			$display_name = get_the_author_meta( 'display_name', $author_id );
		}

		if ( empty( $display_name ) && ! empty( $author->display_name ) ) {
			$display_name = $author->display_name;
		}

		if ( empty( $display_name ) ) {
			$display_name = get_the_archive_title();
		}

		return $display_name;
	}

	/**
	 * Resolve the current author description.
	 *
	 * @since    1.1.0
	 * @param    object    $author       Current queried author object.
	 * @param    int       $author_id    Current author ID.
	 * @param    string    $fallback     Fallback description.
	 * @return   string
	 */
	private function get_author_description( $author, $author_id, $fallback ) {
		$description = '';

		if ( $author_id && function_exists( 'get_the_author_meta' ) ) {
			$description = get_the_author_meta( 'description', $author_id );
		}

		if ( empty( $description ) && ! empty( $author->description ) ) {
			$description = $author->description;
		}

		return ! empty( $description ) ? $description : $fallback;
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

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';

		if ( '' !== $request_uri ) {
			$request_path  = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
			$request_query = (string) wp_parse_url( $request_uri, PHP_URL_QUERY );

			if ( '' !== $request_path || '' !== $request_query ) {
				$url = home_url( '' !== $request_path ? $request_path : '/' );

				if ( '' !== $request_query ) {
					$url .= '?' . $request_query;
				}

				return $url;
			}
		}

		if ( is_search() && function_exists( 'get_search_link' ) ) {
			return get_search_link( get_search_query() );
		}

		if ( is_home() || is_front_page() ) {
			return home_url( '/' );
		}

		return home_url( add_query_arg( array(), $GLOBALS['wp']->request ) );
	}
}
