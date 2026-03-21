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
		if ( ! $this->settings->schema_output_enabled() ) {
			return;
		}

		$context = $this->page_context->get_context();
		$graph   = array();

		if ( is_home() || is_front_page() ) {
			$graph[] = $this->build_organization_schema();
			$graph[] = $this->build_website_schema();

			$local_business_schema = $this->build_local_business_schema();

			if ( ! empty( $local_business_schema ) ) {
				$graph[] = $local_business_schema;
			}
		}

		$breadcrumb_schema = $this->build_breadcrumb_schema( $context );

		if ( ! empty( $breadcrumb_schema ) ) {
			$graph[] = $breadcrumb_schema;
		}

		$product_schema = $this->build_product_schema( $context );

		if ( ! empty( $product_schema ) ) {
			$graph[] = $product_schema;
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
	 * Build a LocalBusiness schema node for the homepage.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function build_local_business_schema() {
		if ( ! $this->settings->local_business_schema_enabled() ) {
			return array();
		}

		$business = $this->settings->get_local_business_data();

		if ( empty( $business['name'] ) ) {
			return array();
		}

		$schema = array(
			'@type'              => $business['type'],
			'@id'                => home_url( '/#localbusiness' ),
			'name'               => $business['name'],
			'url'                => home_url( '/' ),
			'parentOrganization' => array(
				'@id' => home_url( '/#organization' ),
			),
		);

		$logo_url = $this->settings->get_social_image_url();

		if ( ! empty( $logo_url ) ) {
			$schema['image'] = $logo_url;
		}

		if ( ! empty( $business['telephone'] ) ) {
			$schema['telephone'] = $business['telephone'];
		}

		if ( ! empty( $business['price_range'] ) ) {
			$schema['priceRange'] = $business['price_range'];
		}

		$address = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $business['street'],
				'addressLocality' => $business['locality'],
				'addressRegion'   => $business['region'],
				'postalCode'      => $business['postal_code'],
				'addressCountry'  => $business['country'],
			)
		);

		if ( count( $address ) > 1 ) {
			$schema['address'] = $address;
		}

		if ( ! empty( $business['latitude'] ) && ! empty( $business['longitude'] ) ) {
			$schema['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => $business['latitude'],
				'longitude' => $business['longitude'],
			);
		}

		if ( ! empty( $business['opening_hours'] ) ) {
			$schema['openingHours'] = $business['opening_hours'];
		}

		return $schema;
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
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post_id || $this->is_product_post( $post ) ) {
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

		$image = $this->build_primary_image_schema( $context, $post_id );

		if ( ! empty( $image ) ) {
			$schema['image'] = $image;
		}

		return $schema;
	}

	/**
	 * Build a Product schema node for singular product content.
	 *
	 * @since    1.1.0
	 * @param    array    $context    Resolved page context.
	 * @return   array
	 */
	private function build_product_schema( $context ) {
		if ( ! $this->settings->product_schema_enabled() || ! is_singular() ) {
			return array();
		}

		$post_id = get_queried_object_id();
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $this->is_product_post( $post ) ) {
			return array();
		}

		$price         = get_post_meta( $post_id, '_price', true );
		$regular_price = get_post_meta( $post_id, '_regular_price', true );
		$sale_price    = get_post_meta( $post_id, '_sale_price', true );
		$stock_status  = sanitize_text_field( (string) get_post_meta( $post_id, '_stock_status', true ) );
		$sku           = sanitize_text_field( (string) get_post_meta( $post_id, '_sku', true ) );
		$description   = ! empty( $context['description'] ) ? $context['description'] : $this->get_post_plain_text( $post );
		$schema        = array(
			'@type'       => 'Product',
			'@id'         => $context['canonical_url'] . '#product',
			'name'        => ! empty( $context['document_title'] ) ? $context['document_title'] : get_the_title( $post_id ),
			'url'         => $context['canonical_url'],
			'description' => $description,
			'brand'       => array(
				'@id' => home_url( '/#organization' ),
			),
		);

		if ( ! empty( $sku ) ) {
			$schema['sku'] = $sku;
		}

		$image = $this->build_primary_image_schema( $context, $post_id );

		if ( ! empty( $image ) ) {
			$schema['image'] = $image;
		}

		if ( '' !== (string) $price || '' !== (string) $regular_price || '' !== (string) $sale_price ) {
			$offer_price      = '' !== (string) $sale_price ? $sale_price : ( '' !== (string) $price ? $price : $regular_price );
			$schema['offers'] = array(
				'@type'         => 'Offer',
				'price'         => (string) $offer_price,
				'priceCurrency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
				'availability'  => 'outofstock' === strtolower( $stock_status ) ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
				'url'           => $context['canonical_url'],
			);
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

	/**
	 * Determine whether the current post should be treated as a product.
	 *
	 * @since    1.1.0
	 * @param    WP_Post|object|null    $post    Current queried post object.
	 * @return   bool
	 */
	private function is_product_post( $post ) {
		return ! empty( $post ) && 'product' === (string) ( $post->post_type ?? '' );
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

	/**
	 * Get plain text content for a post object.
	 *
	 * @since    1.1.0
	 * @param    WP_Post|object|null    $post    Current post object.
	 * @return   string
	 */
	private function get_post_plain_text( $post ) {
		$content = (string) ( $post->post_content ?? '' );
		$content = html_entity_decode( preg_replace( '/<[^>]+>/', ' ', $content ), ENT_QUOTES, 'UTF-8' );

		return trim( preg_replace( '/\s+/', ' ', $content ) );
	}
}
