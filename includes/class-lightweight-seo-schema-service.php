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
	 * @var      Lightweight_SEO_Page_Context_Service    $page_context
	 */
	private $page_context;

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings    $settings
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
		if ( ! $this->settings->schema_output_enabled() ) {
			return;
		}

		$context = $this->page_context->get_context();
		$graph   = array();

		if ( is_home() || is_front_page() ) {
			$graph[] = $this->build_organization_schema();
			$graph[] = $this->build_website_schema();
		}

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

		$graph = array_values(
			array_filter(
				apply_filters( 'lightweight_seo_schema_graph', $graph, $context )
			)
		);

		if ( empty( $graph ) ) {
			return;
		}

		echo '<script type="application/ld+json">' . wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		) . '</script>' . "\n";
	}

	/**
	 * Build the organization schema for the site homepage.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function build_organization_schema() {
		$schema = array(
			'@type' => 'Organization',
			'@id'   => home_url( '/#organization' ),
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);

		$logo_url = $this->settings->get_social_image_url();

		if ( ! empty( $logo_url ) ) {
			$schema['logo'] = $logo_url;
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
				'@id' => home_url( '/#organization' ),
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
		if ( ! is_single() ) {
			return array();
		}

		$post_id = get_queried_object_id();

		if ( ! $post_id ) {
			return array();
		}

		$schema = array(
			'@type'            => 'Article',
			'headline'         => ! empty( $context['document_title'] ) ? $context['document_title'] : get_the_title( $post_id ),
			'mainEntityOfPage' => $context['canonical_url'],
			'url'              => $context['canonical_url'],
			'publisher'        => array(
				'@id' => home_url( '/#organization' ),
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

		$image_url = '';

		if ( ! empty( $context['og_image'] ) ) {
			$image_url = $context['og_image'];
		} elseif ( has_post_thumbnail( $post_id ) ) {
			$image_url = get_the_post_thumbnail_url( $post_id, 'full' );
		}

		if ( ! empty( $image_url ) ) {
			$schema['image'] = array( $image_url );
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
	 * Build a simple breadcrumb trail for the current request.
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

		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => array(
				array(
					'@type'    => 'ListItem',
					'position' => 1,
					'name'     => get_bloginfo( 'name' ),
					'item'     => home_url( '/' ),
				),
				array(
					'@type'    => 'ListItem',
					'position' => 2,
					'name'     => $context['document_title'],
					'item'     => $context['canonical_url'],
				),
			),
		);
	}
}
