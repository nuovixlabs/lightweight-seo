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
			'object_title'     => '',
			'description'      => '',
			'canonical_url'    => $this->get_current_url(),
			'canonical_custom' => false,
			'robots'           => $this->build_default_robots_directives(),
			'og_title'         => get_bloginfo( 'name' ),
			'og_description'   => '',
			'og_image'         => $this->settings->get_social_image_url(),
			'og_image_alt'     => '',
			'og_image_width'   => 0,
			'og_image_height'  => 0,
			'og_type'          => 'website',
			'og_url'           => $this->get_current_url(),
			'og_locale'        => $this->get_open_graph_locale(),
			'published_time'   => '',
			'modified_time'    => '',
			'twitter_card'     => 'summary',
			'is_404'           => is_404(),
		);

		if ( $context['is_404'] ) {
			$context['canonical_url'] = '';
			$context['og_title']      = '';
			$context['og_url']        = '';
			$context['og_image']      = '';
			$context['robots']        = $this->ensure_robots_directive( $context['robots'], 'noindex' );

			return apply_filters( 'lightweight_seo_page_context', $this->finalize_context( $context ) );
		}

		if ( is_singular() ) {
			$post_id = get_queried_object_id();

			if ( $post_id ) {
				$post           = get_post( $post_id );
				$post_type      = (string) ( $post->post_type ?? '' );
				$post_meta      = $this->post_meta->get_all( $post_id );
				$title_template = is_front_page() ? $this->settings->get_home_title_format() : $this->settings->get_title_format_for_post_type( $post_type );
				$tokens         = array( 'title' => get_the_title( $post_id ) );

				$context['object_title']   = $tokens['title'];
				$context['document_title'] = $this->replace_title_template_vars(
					! empty( $post_meta['seo_title'] ) ? $post_meta['seo_title'] : $title_template,
					$tokens
				);

				$context['description'] = ! empty( $post_meta['seo_description'] )
					? $this->replace_title_template_vars( $post_meta['seo_description'], $tokens )
					: $this->get_post_description( $post );

				if ( is_front_page() && empty( $post_meta['seo_description'] ) && ! empty( $settings['meta_description'] ) ) {
					$context['description'] = $settings['meta_description'];
				}

				$context['canonical_custom'] = ! empty( $post_meta['seo_canonical_url'] );
				$context['canonical_url']    = $context['canonical_custom'] ? $post_meta['seo_canonical_url'] : get_permalink( $post_id );

				$context['robots'] = $this->build_robots_directives( $post_meta, true, $this->settings->is_post_type_noindex( $post_type ) );

				if ( empty( $context['canonical_url'] ) ) {
					$context['canonical_url'] = get_permalink( $post_id );
				}

				$context['og_title']       = ! empty( $post_meta['social_title'] ) ? $this->replace_title_template_vars( $post_meta['social_title'], $tokens ) : $context['document_title'];
				$context['og_description'] = ! empty( $post_meta['social_description'] ) ? $this->replace_title_template_vars( $post_meta['social_description'], $tokens ) : $context['description'];
				$context['og_url']         = $context['canonical_url'];
				$article_post_types        = (array) apply_filters( 'lightweight_seo_article_post_types', array( 'post' ) );
				$context['og_type']        = in_array( $post_type, $article_post_types, true ) ? 'article' : 'website';

				if ( 'article' === $context['og_type'] ) {
					$context['published_time'] = (string) get_the_date( DATE_W3C, $post_id );
					$context['modified_time']  = (string) get_the_modified_date( DATE_W3C, $post_id );
				}

				$post_social_image = $this->post_meta->get_social_image_url( $post_id );

				if ( ! empty( $post_social_image ) ) {
					$context['og_image'] = $post_social_image;
					$image_id            = absint( $post_meta['social_image_id'] ?? 0 );
				} elseif ( has_post_thumbnail( $post_id ) ) {
					$context['og_image'] = get_the_post_thumbnail_url( $post_id, 'full' );
					$image_id            = get_post_thumbnail_id( $post_id );
				} else {
					$image_id = method_exists( $this->settings, 'get' ) ? absint( $this->settings->get( 'social_image_id', 0 ) ) : 0;
				}

				if ( function_exists( 'post_password_required' ) && post_password_required( $post ) ) {
					$context['description']    = '';
					$context['og_description'] = '';
					$context['og_image']       = '';
					$context['published_time'] = '';
					$context['modified_time']  = '';
					$context['robots']         = $this->ensure_robots_directive( $context['robots'], 'noindex' );
					$image_id                  = 0;
				}

				$context = $this->add_social_image_data( $context, $image_id );
			}

			return apply_filters( 'lightweight_seo_page_context', $this->finalize_context( $context ) );
		}

		$posts_page_id = is_home() && ! is_front_page() ? absint( get_option( 'page_for_posts', 0 ) ) : 0;

		if ( $posts_page_id ) {
			// A static "Posts page" is its own document, not a second copy of the homepage.
			$post_meta = $this->post_meta->get_all( $posts_page_id );
			$tokens    = array( 'title' => get_the_title( $posts_page_id ) );

			$context['object_title']     = $tokens['title'];
			$context['document_title']   = $this->replace_title_template_vars(
				! empty( $post_meta['seo_title'] ) ? $post_meta['seo_title'] : $this->settings->get_title_format_for_post_type( 'page' ),
				$tokens
			);
			$context['description']      = ! empty( $post_meta['seo_description'] ) ? $this->replace_title_template_vars( $post_meta['seo_description'], $tokens ) : '';
			$context['canonical_custom'] = ! empty( $post_meta['seo_canonical_url'] );
			$context['canonical_url']    = $context['canonical_custom'] ? $post_meta['seo_canonical_url'] : $this->add_pagination_to_url( (string) get_permalink( $posts_page_id ) );
			$context['robots']           = $this->build_robots_directives( $post_meta, false, $this->settings->is_post_type_noindex( 'page' ) );
			$context['og_title']         = ! empty( $post_meta['social_title'] ) ? $this->replace_title_template_vars( $post_meta['social_title'], $tokens ) : $context['document_title'];
			$context['og_description']   = ! empty( $post_meta['social_description'] ) ? $this->replace_title_template_vars( $post_meta['social_description'], $tokens ) : $context['description'];
			$context['og_url']           = $context['canonical_url'];
		} elseif ( is_home() || is_front_page() ) {
			$context['object_title']   = get_bloginfo( 'name' );
			$context['description']    = $settings['meta_description'];
			$context['document_title'] = $this->replace_title_template_vars(
				$this->settings->get_home_title_format(),
				array(
					'title' => get_bloginfo( 'name' ),
				)
			);
			$context['og_title']       = $context['document_title'];
			$context['og_description'] = $context['description'];
		} elseif ( is_search() ) {
			$context['object_title']   = get_search_query();
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
			$term                        = get_queried_object();
			$taxonomy                    = (string) ( $term->taxonomy ?? '' );
			$term_meta                   = ! empty( $term->term_id ) ? $this->archive_meta->get_term_all( $term->term_id ) : array();
			$tokens                      = array( 'title' => isset( $term->name ) ? $term->name : '' );
			$context['object_title']     = $tokens['title'];
			$context['document_title']   = $this->replace_title_template_vars(
				! empty( $term_meta['seo_title'] ) ? $term_meta['seo_title'] : $this->settings->get_title_format_for_taxonomy( $taxonomy ),
				$tokens
			);
			$context['description']      = ! empty( $term_meta['seo_description'] ) ? $this->replace_title_template_vars( $term_meta['seo_description'], $tokens ) : $this->summarize_text( $term->description ?? '' );
			$context['canonical_custom'] = ! empty( $term_meta['seo_canonical_url'] );
			$context['canonical_url']    = $context['canonical_custom'] ? $term_meta['seo_canonical_url'] : $this->add_pagination_to_url( $this->get_term_archive_url( $term, $context['canonical_url'] ) );
			$context['robots']           = $this->build_robots_directives( $term_meta, false, $this->settings->is_taxonomy_noindex( $taxonomy ) );
			$context['og_title']         = $context['document_title'];
			$context['og_description']   = $context['description'];
			$context['og_url']           = $context['canonical_url'];
		} elseif ( is_author() ) {
			$author                      = get_queried_object();
			$author_id                   = isset( $author->ID ) ? (int) $author->ID : get_queried_object_id();
			$author_meta                 = $author_id ? $this->archive_meta->get_user_all( $author_id ) : array();
			$tokens                      = array( 'title' => $this->get_author_display_name( $author, $author_id ) );
			$context['object_title']     = $tokens['title'];
			$context['document_title']   = $this->replace_title_template_vars(
				! empty( $author_meta['seo_title'] ) ? $author_meta['seo_title'] : $this->settings->get_archive_title_format(),
				$tokens
			);
			$context['description']      = ! empty( $author_meta['seo_description'] ) ? $this->replace_title_template_vars( $author_meta['seo_description'], $tokens ) : $this->summarize_text( $this->get_author_description( $author, $author_id, '' ) );
			$context['canonical_custom'] = ! empty( $author_meta['seo_canonical_url'] );
			$context['canonical_url']    = $context['canonical_custom'] ? $author_meta['seo_canonical_url'] : $this->add_pagination_to_url( $this->get_author_archive_url( $author_id, $context['canonical_url'] ) );
			$context['robots']           = $this->build_robots_directives( $author_meta, false, $this->settings->author_archives_noindex_enabled() );
			$context['og_title']         = $context['document_title'];
			$context['og_description']   = $context['description'];
			$context['og_url']           = $context['canonical_url'];
		} elseif ( is_archive() ) {
			$archive_title             = get_the_archive_title();
			$archive_description       = get_the_archive_description();
			$context['object_title']   = $archive_title;
			$context['document_title'] = $this->replace_title_template_vars(
				$this->settings->get_archive_title_format(),
				array(
					'title' => $archive_title,
				)
			);
			$context['description']    = ! empty( $archive_description ) ? $this->summarize_text( $archive_description ) : '';
			$context['og_title']       = $context['document_title'];
			$context['og_description'] = $context['description'];
			$context['og_url']         = $context['canonical_url'];

			if ( $this->is_hidden_archive() ) {
				$context['robots'] = $this->ensure_robots_directive( $context['robots'], 'noindex' );
			}
		}

		$image_id = method_exists( $this->settings, 'get' ) ? absint( $this->settings->get( 'social_image_id', 0 ) ) : 0;
		$context  = $this->add_social_image_data( $context, $image_id );

		return apply_filters( 'lightweight_seo_page_context', $this->finalize_context( $context ) );
	}

	/**
	 * Resolve a useful singular description from the excerpt or visible content.
	 *
	 * @param WP_Post|object|null $post Current queried post.
	 * @return string
	 */
	private function get_post_description( $post ) {
		if ( empty( $post ) ) {
			return '';
		}

		$description = (string) ( $post->post_excerpt ?? '' );

		if ( '' === trim( $description ) ) {
			$description = (string) ( $post->post_content ?? '' );
		}

		if ( function_exists( 'strip_shortcodes' ) ) {
			$description = strip_shortcodes( $description );
		}

		return $this->summarize_text( $description );
	}

	/**
	 * Reduce derived text to a search-snippet sized summary.
	 *
	 * Editor-written descriptions are never shortened. Only content, term, and
	 * biography fallbacks pass through here, so a long article is not repeated
	 * in full across the description, Open Graph, X, and schema output.
	 *
	 * @since    1.1.0
	 * @param    mixed $value Raw fallback text.
	 * @return   string
	 */
	private function summarize_text( $value ) {
		return Lightweight_SEO_Text::summarize( $this->normalize_text( $value ) );
	}

	/**
	 * Get the Open Graph locale for the current site language.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	private function get_open_graph_locale() {
		$locale = function_exists( 'get_locale' ) ? (string) get_locale() : '';

		// Open Graph expects language_TERRITORY; drop variants such as de_DE_formal.
		return 1 === preg_match( '/^[a-z]{2,3}(?:_[A-Z]{2})?/', $locale, $matches ) ? $matches[0] : '';
	}

	/**
	 * Keep later archive pages self-canonical instead of pointing them at page one.
	 *
	 * @since    1.1.0
	 * @param    string $url First-page archive URL.
	 * @return   string
	 */
	private function add_pagination_to_url( $url ) {
		$paged = function_exists( 'get_query_var' ) ? absint( get_query_var( 'paged' ) ) : 0;

		if ( $paged < 2 || '' === (string) $url ) {
			return $url;
		}

		$rewrite = $GLOBALS['wp_rewrite'] ?? null;

		if ( is_object( $rewrite ) && method_exists( $rewrite, 'using_permalinks' ) && $rewrite->using_permalinks() && false === strpos( $url, '?' ) ) {
			$paged_url = trailingslashit( $url ) . ( ! empty( $rewrite->pagination_base ) ? $rewrite->pagination_base : 'page' ) . '/' . $paged;

			return function_exists( 'user_trailingslashit' ) ? user_trailingslashit( $paged_url, 'paged' ) : $paged_url;
		}

		return add_query_arg( 'paged', $paged, $url );
	}

	/**
	 * Add attachment metadata for the selected social image when available.
	 *
	 * @param array $context  Resolved page context.
	 * @param int   $image_id WordPress attachment ID.
	 * @return array
	 */
	private function add_social_image_data( $context, $image_id ) {
		if ( empty( $context['og_image'] ) ) {
			$context['twitter_card'] = 'summary';
			return $context;
		}

		$context['twitter_card'] = 'summary_large_image';
		$image_id                = absint( $image_id );

		if ( ! $image_id ) {
			return $context;
		}

		$metadata                   = wp_get_attachment_metadata( $image_id );
		$context['og_image_alt']    = $this->normalize_text( get_post_meta( $image_id, '_wp_attachment_image_alt', true ) );
		$context['og_image_width']  = absint( $metadata['width'] ?? 0 );
		$context['og_image_height'] = absint( $metadata['height'] ?? 0 );

		return $context;
	}

	/**
	 * Normalize human-readable metadata before it reaches HTML attributes.
	 *
	 * @param mixed $value Raw metadata value.
	 * @return string
	 */
	private function normalize_text( $value ) {
		$value = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
		$value = wp_strip_all_tags( $value, true );

		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}

	/**
	 * Normalize output-facing context values and social-card selection.
	 *
	 * @param array $context Resolved page context.
	 * @return array
	 */
	private function finalize_context( $context ) {
		foreach ( array( 'document_title', 'object_title', 'description', 'og_title', 'og_description', 'og_image_alt' ) as $field ) {
			$context[ $field ] = $this->normalize_text( $context[ $field ] ?? '' );
		}

		$context['twitter_card'] = ! empty( $context['og_image'] ) ? 'summary_large_image' : 'summary';

		return $context;
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
	 * Build the robots directives of a page, term, or author, preserving legacy noindex behavior.
	 *
	 * @since    1.1.0
	 * @param    array    $meta_values                 Resolved SEO meta of the object.
	 * @param    bool     $preserve_legacy_nofollow    Whether a 1.0 noindex still implies nofollow.
	 * @param    bool     $type_hidden                 Whether the object's content type is hidden from search results.
	 * @return   string
	 */
	private function build_robots_directives( $meta_values, $preserve_legacy_nofollow = false, $type_hidden = false ) {
		$directives = array();

		if ( $this->settings->resolve_noindex( $meta_values['seo_noindex'] ?? '', $type_hidden ) ) {
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
	 * Determine whether the current date or post type archive is hidden from search results.
	 *
	 * Neither archive has an object of its own to carry an exception, so the
	 * content type setting decides.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	private function is_hidden_archive() {
		if ( is_date() ) {
			return $this->settings->date_archives_noindex_enabled();
		}

		if ( is_post_type_archive() ) {
			$post_type = get_queried_object();

			return ! empty( $post_type->name ) && $this->settings->is_post_type_noindex( $post_type->name );
		}

		return false;
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
		$template = (string) $template;

		if ( false === strpos( $template, '%' ) ) {
			return $template;
		}

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

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only the parsed path and allowlisted query arguments are used, and the URL is escaped on output.

		if ( '' !== $request_uri ) {
			$request_path  = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
			$request_query = $this->get_canonical_query_string( (string) wp_parse_url( $request_uri, PHP_URL_QUERY ) );

			if ( '' !== $request_path || '' !== $request_query ) {
				// REQUEST_URI already contains a subdirectory install's path, so join it to the origin.
				$url = Lightweight_SEO_Url::from_path( '' !== $request_path ? $request_path : '/' );

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

	/**
	 * Normalize request query arguments for canonical URLs.
	 *
	 * @since    1.1.0
	 * @param    string $request_query Raw request query string.
	 * @return   string
	 */
	private function get_canonical_query_string( $request_query ) {
		if ( '' === $request_query ) {
			return '';
		}

		parse_str( $request_query, $query_args );

		if ( empty( $query_args ) || ! is_array( $query_args ) ) {
			return '';
		}

		$allowed_args = array();

		if ( is_search() && isset( $query_args['s'] ) && is_scalar( $query_args['s'] ) && '' !== (string) $query_args['s'] ) {
			$allowed_args['s'] = (string) $query_args['s'];
		}

		if ( isset( $query_args['paged'] ) && is_scalar( $query_args['paged'] ) && '' !== (string) $query_args['paged'] ) {
			$allowed_args['paged'] = (string) $query_args['paged'];
		}

		if ( empty( $allowed_args ) ) {
			return '';
		}

		return http_build_query( $allowed_args, '', '&', PHP_QUERY_RFC3986 );
	}
}
