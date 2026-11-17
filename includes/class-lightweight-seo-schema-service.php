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
		$graph[]           = $this->build_webpage_schema( $context, ! empty( $breadcrumb_schema ) );
		$graph[]           = $breadcrumb_schema;
		$graph[]           = $this->build_article_schema( $context );
		$graph             = array_values( array_filter( $graph ) );

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
	 * Get the identifier of the node that describes the current page.
	 *
	 * @since    1.1.0
	 * @param    array    $context    Resolved page context.
	 * @return   string
	 */
	private function get_webpage_id( $context ) {
		return $context['canonical_url'] . '#webpage';
	}

	/**
	 * Build the node that describes the current page.
	 *
	 * Every page with a canonical address has one. Lists of posts are a
	 * CollectionPage, search results a SearchResultsPage, and an author archive a
	 * ProfilePage that names the author.
	 *
	 * @since    1.1.0
	 * @param    array    $context           Resolved page context.
	 * @param    bool     $has_breadcrumb    Whether the graph holds a breadcrumb trail for this page.
	 * @return   array
	 */
	private function build_webpage_schema( $context, $has_breadcrumb ) {
		if ( empty( $context['canonical_url'] ) ) {
			return array();
		}

		$type = 'WebPage';

		if ( is_author() ) {
			$type = 'ProfilePage';
		} elseif ( is_search() ) {
			$type = 'SearchResultsPage';
		} elseif ( is_home() || is_archive() ) {
			$type = 'CollectionPage';
		}

		$schema = array(
			'@type'    => $type,
			'@id'      => $this->get_webpage_id( $context ),
			'url'      => $context['canonical_url'],
			'name'     => is_author() ? (string) ( $context['document_title'] ?? '' ) : $this->get_object_title( $context ),
			'isPartOf' => array(
				'@id' => home_url( '/#website' ),
			),
		);

		if ( '' === $schema['name'] ) {
			unset( $schema['name'] );
		}

		if ( ! empty( $context['description'] ) ) {
			$schema['description'] = $context['description'];
		}

		$language = (string) get_bloginfo( 'language' );

		if ( '' !== $language ) {
			$schema['inLanguage'] = $language;
		}

		if ( $has_breadcrumb ) {
			$schema['breadcrumb'] = array(
				'@id' => $context['canonical_url'] . '#breadcrumb',
			);
		}

		if ( is_front_page() ) {
			$schema['about'] = array(
				'@id' => $this->get_site_entity_id(),
			);
		}

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			$schema  = array_merge( $schema, $this->get_post_dates( $post_id ) );
			// Only the page's own featured image is its primary image; the site-wide share image is not.
			$image = $post_id && has_post_thumbnail( $post_id ) ? (string) get_the_post_thumbnail_url( $post_id, 'full' ) : '';

			if ( '' !== $image ) {
				$schema['primaryImageOfPage'] = array(
					'@type' => 'ImageObject',
					'url'   => $image,
				);
			}
		}

		if ( is_author() ) {
			$person = $this->build_author_person( $context );

			if ( empty( $person ) ) {
				return array();
			}

			$schema['mainEntity'] = $person;
		}

		return $schema;
	}

	/**
	 * Get the publication and modification dates of a post, where it has them.
	 *
	 * @since    1.1.0
	 * @param    int    $post_id    Post ID.
	 * @return   array
	 */
	private function get_post_dates( $post_id ) {
		$dates          = array();
		$date_published = $post_id ? get_the_date( DATE_W3C, $post_id ) : '';
		$date_modified  = $post_id ? get_the_modified_date( DATE_W3C, $post_id ) : '';

		if ( ! empty( $date_published ) ) {
			$dates['datePublished'] = $date_published;
		}

		if ( ! empty( $date_modified ) ) {
			$dates['dateModified'] = $date_modified;
		}

		return $dates;
	}

	/**
	 * Build an article node for single items of a post type that has an article schema type.
	 *
	 * @since    1.1.0
	 * @param    array    $context    Resolved page context.
	 * @return   array
	 */
	private function build_article_schema( $context ) {
		$post_id = get_queried_object_id();
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! is_singular() || ! $post_id || empty( $post ) || empty( $context['canonical_url'] ) ) {
			return array();
		}

		$type = $this->settings->get_schema_type_for_post_type( (string) ( $post->post_type ?? '' ) );

		if ( 'WebPage' === $type ) {
			return array();
		}

		$schema = array(
			'@type'            => $type,
			'@id'              => $context['canonical_url'] . '#article',
			'headline'         => '' !== $this->get_object_title( $context ) ? $this->get_object_title( $context ) : $this->plain_text( get_the_title( $post_id ) ),
			'mainEntityOfPage' => array(
				'@id' => $this->get_webpage_id( $context ),
			),
			'url'              => $context['canonical_url'],
			'isPartOf'         => array(
				'@id' => $this->get_webpage_id( $context ),
			),
			'publisher'        => array(
				'@id' => $this->get_site_entity_id(),
			),
		);

		if ( ! empty( $context['description'] ) ) {
			$schema['description'] = $context['description'];
		}

		$schema    = array_merge( $schema, $this->get_post_dates( $post_id ) );
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
	 * Build the person an author archive is about.
	 *
	 * @since    1.1.0
	 * @param    array    $context    Resolved page context.
	 * @return   array
	 */
	private function build_author_person( $context ) {
		$author    = get_queried_object();
		$author_id = isset( $author->ID ) ? (int) $author->ID : get_queried_object_id();

		if ( ! $author_id || empty( $context['document_title'] ) ) {
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

		return $person;
	}

	/**
	 * Build the breadcrumb trail of the current request.
	 *
	 * The trail follows the rules the WordPress Breadcrumbs block uses by default,
	 * so the structured data and a visible trail agree: Home, the archive of the
	 * post type when it has one, parent pages or the first term with its parents,
	 * then the page itself.
	 *
	 * @since    1.1.0
	 * @param    array    $context    Resolved page context.
	 * @return   array
	 */
	private function build_breadcrumb_schema( $context ) {
		if ( is_front_page() || empty( $context['canonical_url'] ) || empty( $context['document_title'] ) ) {
			return array();
		}

		$trail = array(
			array(
				'label' => __( 'Home', 'lightweight-seo' ),
				'url'   => home_url( '/' ),
			),
		);

		if ( is_singular() ) {
			$trail = array_merge( $trail, $this->get_post_trail( get_queried_object_id() ) );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( ! empty( $term->term_id ) ) {
				$trail = array_merge( $trail, $this->get_term_ancestor_trail( (int) $term->term_id, (string) ( $term->taxonomy ?? '' ) ) );
			}
		} elseif ( is_date() ) {
			$trail = array_merge( $trail, $this->get_date_trail() );
		}

		$trail[] = array(
			'label' => $this->get_object_title( $context ),
			'url'   => $context['canonical_url'],
		);

		/** This filter is documented in wp-includes/blocks/breadcrumbs.php */
		$trail = (array) apply_filters( 'block_core_breadcrumbs_items', $trail ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The WordPress Breadcrumbs block's own filter, applied so a customized trail is the same in both places.
		$items = array();
		$last  = count( $trail ) - 1;

		foreach ( array_values( $trail ) as $index => $crumb ) {
			$name = is_array( $crumb ) ? $this->plain_text( $crumb['label'] ?? '' ) : '';
			$url  = is_array( $crumb ) ? (string) ( $crumb['url'] ?? '' ) : '';

			// The block prints the current page without a link; in the structured data it is the page's own address.
			if ( '' === $url && $index === $last ) {
				$url = $context['canonical_url'];
			}

			if ( '' === $name || '' === $url ) {
				continue;
			}

			$items[] = array(
				'@type'    => 'ListItem',
				'position' => count( $items ) + 1,
				'name'     => $name,
				'item'     => $url,
			);
		}

		if ( count( $items ) < 2 ) {
			return array();
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $context['canonical_url'] . '#breadcrumb',
			'itemListElement' => $items,
		);
	}

	/**
	 * Get the trail between Home and a single post, page, or attachment.
	 *
	 * @since    1.1.0
	 * @param    int    $post_id    Post ID.
	 * @return   array[] Items with a label and a url.
	 */
	private function get_post_trail( $post_id ) {
		$post = $post_id ? get_post( $post_id ) : null;

		if ( empty( $post ) ) {
			return array();
		}

		$post_type = (string) ( $post->post_type ?? '' );
		$parent_id = (int) ( $post->post_parent ?? 0 );

		// An item of a flat post type that hangs under another item, such as an attachment, follows that item's trail.
		if ( $parent_id && ! is_post_type_hierarchical( $post_type ) ) {
			$parent = get_post( $parent_id );

			if ( ! empty( $parent ) ) {
				$post_id   = (int) $parent->ID;
				$post_type = (string) ( $parent->post_type ?? '' );
				$parent_id = (int) ( $parent->post_parent ?? 0 );
			}
		}

		$trail        = array();
		$archive_link = get_post_type_archive_link( $post_type );

		if ( $archive_link && untrailingslashit( home_url() ) !== untrailingslashit( $archive_link ) ) {
			$type_object    = get_post_type_object( $post_type );
			$label          = (string) ( $type_object->labels->archives ?? ( $type_object->labels->name ?? '' ) );
			$page_for_posts = (int) get_option( 'page_for_posts' );

			if ( 'post' === $post_type && $page_for_posts ) {
				$label = get_the_title( $page_for_posts );
			}

			$trail[] = array(
				'label' => $label,
				'url'   => $archive_link,
			);
		}

		if ( is_post_type_hierarchical( $post_type ) || $parent_id ) {
			foreach ( array_reverse( (array) get_post_ancestors( $post_id ) ) as $ancestor_id ) {
				$trail[] = array(
					'label' => get_the_title( $ancestor_id ),
					'url'   => get_permalink( $ancestor_id ),
				);
			}

			return $trail;
		}

		return array_merge( $trail, $this->get_post_term_trail( $post_id, $post_type ) );
	}

	/**
	 * Get the term a flat post is filed under, with that term's parents.
	 *
	 * @since    1.1.0
	 * @param    int       $post_id      Post ID.
	 * @param    string    $post_type    Post type name.
	 * @return   array[] Items with a label and a url.
	 */
	private function get_post_term_trail( $post_id, $post_type ) {
		$taxonomies = wp_filter_object_list(
			get_object_taxonomies( $post_type, 'objects' ),
			array(
				'publicly_queryable' => true,
				'show_in_rest'       => true,
			)
		);

		if ( empty( $taxonomies ) ) {
			return array();
		}

		/** This filter is documented in wp-includes/blocks/breadcrumbs.php */
		$preferred = (array) apply_filters( 'block_core_breadcrumbs_post_type_settings', array(), $post_type, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The WordPress Breadcrumbs block's own filter, so one choice of taxonomy and term serves both trails.
		$names     = array_values( wp_list_pluck( $taxonomies, 'name' ) );

		// The preferred taxonomy is tried first, then the others in their registered order.
		if ( ! empty( $preferred['taxonomy'] ) && in_array( $preferred['taxonomy'], $names, true ) ) {
			$names = array_values( array_unique( array_merge( array( $preferred['taxonomy'] ), $names ) ) );
		}

		foreach ( $names as $taxonomy ) {
			$terms = get_the_terms( $post_id, $taxonomy );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}

			$term = reset( $terms );

			if ( ! empty( $preferred['term'] ) && count( $terms ) > 1 ) {
				foreach ( $terms as $candidate ) {
					if ( $candidate->slug === $preferred['term'] ) {
						$term = $candidate;
						break;
					}
				}
			}

			$link = get_term_link( $term );

			if ( is_wp_error( $link ) ) {
				return array();
			}

			return array_merge(
				$this->get_term_ancestor_trail( (int) $term->term_id, $taxonomy ),
				array(
					array(
						'label' => $term->name,
						'url'   => $link,
					),
				)
			);
		}

		return array();
	}

	/**
	 * Get the parents of a term, outermost first.
	 *
	 * @since    1.1.0
	 * @param    int       $term_id     Term ID.
	 * @param    string    $taxonomy    Taxonomy name.
	 * @return   array[] Items with a label and a url.
	 */
	private function get_term_ancestor_trail( $term_id, $taxonomy ) {
		$trail = array();

		if ( ! $term_id || ! is_taxonomy_hierarchical( $taxonomy ) ) {
			return $trail;
		}

		foreach ( array_reverse( (array) get_ancestors( $term_id, $taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $taxonomy );
			$link     = empty( $ancestor ) || is_wp_error( $ancestor ) ? '' : get_term_link( $ancestor );

			if ( empty( $link ) || is_wp_error( $link ) ) {
				continue;
			}

			$trail[] = array(
				'label' => $ancestor->name,
				'url'   => $link,
			);
		}

		return $trail;
	}

	/**
	 * Get the year, and the month for a day archive, above the current date archive.
	 *
	 * @since    1.1.0
	 * @return   array[] Items with a label and a url.
	 */
	private function get_date_trail() {
		$year  = (int) get_query_var( 'year' );
		$month = (int) get_query_var( 'monthnum' );
		$day   = (int) get_query_var( 'day' );
		$trail = array();

		if ( ! $year || ! $month ) {
			return $trail;
		}

		$trail[] = array(
			'label' => (string) $year,
			'url'   => get_year_link( $year ),
		);

		if ( $day ) {
			$trail[] = array(
				'label' => date_i18n( 'F', mktime( 0, 0, 0, $month, 1, $year ) ),
				'url'   => get_month_link( $year, $month ),
			);
		}

		return $trail;
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
