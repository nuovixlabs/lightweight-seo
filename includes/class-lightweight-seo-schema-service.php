<?php
/**
 * Schema output service for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Schema output service.
 */
class Lightweight_SEO_Schema_Service {

	/**
	 * Shared page context service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Page_Context_Service
	 */
	private $page_context;

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings
	 */
	private $settings;

	/**
	 * Initialize the service.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Page_Context_Service    $page_context    Shared page context service.
	 * @param    Lightweight_SEO_Settings                $settings        Shared settings service.
	 */
	public function __construct( $page_context, $settings ) {
		$this->page_context = $page_context;
		$this->settings     = $settings;
	}

	/**
	 * Output JSON-LD schema markup.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function add_schema() {
		$graph = $this->get_graph();

		if ( empty( $graph ) ) {
			return;
		}

		// JSON_HEX_TAG keeps any "<" in graph values (including third-party filter data) from closing the script element.
		echo '<script type="application/ld+json">' . wp_json_encode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded with JSON_HEX_TAG for a script data block.
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
		) . '</script>' . "\n";
	}

	/**
	 * Get the schema graph of the current request exactly as it is printed.
	 *
	 * @since    1.1.0
	 * @return   array Graph nodes, or an empty array when nothing is printed.
	 */
	public function get_graph() {
		if ( ! $this->settings->schema_output_enabled() ) {
			return array();
		}

		$context = $this->page_context->get_context();
		$graph   = array();

		if ( ! empty( $context['is_404'] ) || is_404() ) {
			return array();
		}

		$graph[] = $this->build_site_entity_schema();
		$graph[] = $this->build_website_schema();

		$breadcrumb_schema = $this->build_breadcrumb_schema( $context );

		if ( ! empty( $breadcrumb_schema ) ) {
			$graph[] = $breadcrumb_schema;
		}

		$article_schema = $this->build_article_schema( $context );

		if ( ! empty( $article_schema ) ) {
			$graph[] = $article_schema;
		}

		$profile_page_schema = $this->build_profile_page_schema( $context );

		if ( ! empty( $profile_page_schema ) ) {
			$graph[] = $profile_page_schema;
		}

		return array_values(
			array_filter(
				apply_filters( 'lightweight_seo_schema_graph', $graph, $context )
			)
		);
	}

	/**
	 * Reduce a title or name to plain text for schema values.
	 *
	 * @since    1.1.0
	 * @param    mixed $value Raw title or name.
	 * @return   string
	 */
	private function plain_text( $value ) {
		$value = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
		$value = wp_strip_all_tags( $value, true );

		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}

	/**
	 * Get the visible name of the current object rather than its templated title tag.
	 *
	 * @since    1.1.0
	 * @param    array $context Resolved page context.
	 * @return   string
	 */
	private function get_object_title( $context ) {
		return ! empty( $context['object_title'] ) ? (string) $context['object_title'] : (string) ( $context['document_title'] ?? '' );
	}

	/**
	 * Get the identifier of the node that describes who the site belongs to.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	private function get_site_entity_id() {
		return home_url( 'person' === $this->settings->get_identity_type() ? '/#person' : '/#organization' );
	}

	/**
	 * Build the node for the organization or person the site represents.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function build_site_entity_schema() {
		$is_person = 'person' === $this->settings->get_identity_type();
		$image_url = $this->settings->get_identity_logo_url();
		$schema    = array(
			'@type' => $is_person ? 'Person' : 'Organization',
			'@id'   => $this->get_site_entity_id(),
			'name'  => $this->settings->get_identity_name(),
			'url'   => home_url( '/' ),
		);

		if ( $is_person ) {
			// A person has a photo, not a logo, and a share image is never used in its place.
			if ( ! empty( $image_url ) ) {
				$schema['image'] = $image_url;
			}
		} else {
			// An organization without a logo of its own keeps using the default social image.
			$logo_url = ! empty( $image_url ) ? $image_url : $this->settings->get_social_image_url();

			if ( ! empty( $logo_url ) ) {
				$schema['logo'] = $logo_url;
			}
		}

		$same_as = $this->settings->get_organization_same_as();

		if ( ! empty( $same_as ) ) {
			$schema['sameAs'] = $same_as;
		}

		return $schema;
	}

	/**
	 * Build the website schema for the site homepage.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function build_website_schema() {
		return array(
			'@type'       => 'WebSite',
			'@id'         => home_url( '/#website' ),
			'url'         => home_url( '/' ),
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'publisher'   => array(
				'@id' => $this->get_site_entity_id(),
			),
		);
	}

	/**
	 * Build an article schema node for single posts.
	 *
	 * @since    1.1.0
	 * @param    array    $context    Resolved page context.
	 * @return   array
	 */
	private function build_article_schema( $context ) {
		$post_id = get_queried_object_id();
		$post    = $post_id ? get_post( $post_id ) : null;
		$types   = (array) apply_filters( 'lightweight_seo_article_post_types', array( 'post' ) );

		if ( ! is_singular() || ! $post_id || empty( $post ) || ! in_array( (string) ( $post->post_type ?? '' ), $types, true ) ) {
			return array();
		}

		$schema = array(
			'@type'            => 'Article',
			'@id'              => $context['canonical_url'] . '#article',
			'headline'         => '' !== $this->get_object_title( $context ) ? $this->get_object_title( $context ) : $this->plain_text( get_the_title( $post_id ) ),
			'mainEntityOfPage' => array(
				'@id' => $context['canonical_url'],
			),
			'url'              => $context['canonical_url'],
			'isPartOf'         => array(
				'@id' => home_url( '/#website' ),
			),
			'publisher'        => array(
				'@id' => $this->get_site_entity_id(),
			),
		);

		if ( ! empty( $context['description'] ) ) {
			$schema['description'] = $context['description'];
		}

		$date_published = get_the_date( DATE_W3C, $post_id );
		$date_modified  = get_the_modified_date( DATE_W3C, $post_id );

		if ( ! empty( $date_published ) ) {
			$schema['datePublished'] = $date_published;
		}

		if ( ! empty( $date_modified ) ) {
			$schema['dateModified'] = $date_modified;
		}

		$author_id = (int) get_post_field( 'post_author', $post_id );

		if ( $author_id ) {
			$schema['author'] = array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', $author_id ),
				'url'   => get_author_posts_url( $author_id ),
			);
		}

		$image = $this->build_primary_image_schema( $context, $post_id );

		if ( ! empty( $image ) ) {
			$schema['image'] = $image;
		}

		return $schema;
	}

	/**
	 * Build a profile page schema node for author archives.
	 *
	 * @since    1.1.0
	 * @param    array    $context    Resolved page context.
	 * @return   array
	 */
	private function build_profile_page_schema( $context ) {
		if ( ! is_author() ) {
			return array();
		}

		$author    = get_queried_object();
		$author_id = isset( $author->ID ) ? (int) $author->ID : get_queried_object_id();

		if ( ! $author_id || empty( $context['canonical_url'] ) || empty( $context['document_title'] ) ) {
			return array();
		}

		$display_name = get_the_author_meta( 'display_name', $author_id );

		if ( empty( $display_name ) && ! empty( $author->display_name ) ) {
			$display_name = $author->display_name;
		}

		$person = array(
			'@type' => 'Person',
			'name'  => ! empty( $display_name ) ? $display_name : $context['document_title'],
			'url'   => $context['canonical_url'],
		);

		if ( ! empty( $context['description'] ) ) {
			$person['description'] = $context['description'];
		}

		return array(
			'@type'      => 'ProfilePage',
			'url'        => $context['canonical_url'],
			'name'       => $context['document_title'],
			'mainEntity' => $person,
		);
	}

	/**
	 * Build a hierarchical breadcrumb trail for the current request.
	 *
	 * @since    1.1.0
	 * @param    array    $context    Resolved page context.
	 * @return   array
	 */
	private function build_breadcrumb_schema( $context ) {
		if ( is_home() || is_front_page() ) {
			return array();
		}

		if ( empty( $context['canonical_url'] ) || empty( $context['document_title'] ) ) {
			return array();
		}

		$items = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => get_bloginfo( 'name' ),
				'item'     => home_url( '/' ),
			),
		);

		if ( is_singular() ) {
			$post_id   = get_queried_object_id();
			$ancestors = function_exists( 'get_post_ancestors' ) ? array_reverse( get_post_ancestors( $post_id ) ) : array();

			foreach ( $ancestors as $ancestor_id ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => count( $items ) + 1,
					'name'     => $this->plain_text( get_the_title( $ancestor_id ) ),
					'item'     => get_permalink( $ancestor_id ),
				);
			}
		} elseif ( is_category() || is_tax() ) {
			$term      = get_queried_object();
			$term_id   = (int) ( $term->term_id ?? 0 );
			$taxonomy  = (string) ( $term->taxonomy ?? '' );
			$ancestors = $term_id && function_exists( 'get_ancestors' ) ? array_reverse( get_ancestors( $term_id, $taxonomy, 'taxonomy' ) ) : array();

			foreach ( $ancestors as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, $taxonomy );
				$link     = get_term_link( $ancestor_id, $taxonomy );

				if ( is_wp_error( $ancestor ) || is_wp_error( $link ) ) {
					continue;
				}

				$items[] = array(
					'@type'    => 'ListItem',
					'position' => count( $items ) + 1,
					'name'     => $this->plain_text( $ancestor->name ),
					'item'     => $link,
				);
			}
		}

		$items[] = array(
			'@type'    => 'ListItem',
			'position' => count( $items ) + 1,
			'name'     => $this->get_object_title( $context ),
			'item'     => $context['canonical_url'],
		);

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $context['canonical_url'] . '#breadcrumb',
			'itemListElement' => $items,
		);
	}

	/**
	 * Build a primary image schema value when available.
	 *
	 * @since    1.1.0
	 * @param    array    $context    Resolved page context.
	 * @param    int      $post_id    Current post ID.
	 * @return   array
	 */
	private function build_primary_image_schema( $context, $post_id ) {
		$image_url = '';

		if ( ! empty( $context['og_image'] ) ) {
			$image_url = $context['og_image'];
		} elseif ( has_post_thumbnail( $post_id ) ) {
			$image_url = get_the_post_thumbnail_url( $post_id, 'full' );
		}

		if ( empty( $image_url ) ) {
			return array();
		}

		return array( $image_url );
	}
}
