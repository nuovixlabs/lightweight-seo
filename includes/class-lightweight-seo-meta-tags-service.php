<?php
/**
 * Frontend meta tags service for Lightweight SEO.
 *
 * @since      1.0.2
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Frontend meta tags service.
 */
class Lightweight_SEO_Meta_Tags_Service {

	/**
	 * Shared page context service.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      Lightweight_SEO_Page_Context_Service    $page_context
	 */
	private $page_context;

	/**
	 * Initialize the service.
	 *
	 * @since    1.0.2
	 * @param    Lightweight_SEO_Page_Context_Service    $page_context    Shared page context service.
	 */
	public function __construct( $page_context ) {
		$this->page_context = $page_context;
	}

	/**
	 * Add meta tags to head.
	 *
	 * @since    1.0.2
	 * @return   void
	 */
	public function add_meta_tags() {
		$context = array_merge(
			array(
				'description'     => '',
				'og_title'        => '',
				'og_description'  => '',
				'og_type'         => '',
				'og_url'          => '',
				'og_image'        => '',
				'og_image_alt'    => '',
				'og_image_width'  => 0,
				'og_image_height' => 0,
				'twitter_card'    => 'summary',
				'is_404'          => false,
			),
			$this->page_context->get_context()
		);
		$tags    = array(
			array(
				'attribute' => 'name',
				'key'       => 'description',
				'value'     => $context['description'],
			),
			array(
				'attribute' => 'property',
				'key'       => 'og:title',
				'value'     => $context['og_title'],
			),
			array(
				'attribute' => 'property',
				'key'       => 'og:description',
				'value'     => $context['og_description'],
			),
			array(
				'attribute' => 'property',
				'key'       => 'og:type',
				'value'     => $context['og_type'],
			),
			array(
				'attribute' => 'property',
				'key'       => 'og:url',
				'value'     => $context['og_url'],
			),
			array(
				'attribute' => 'property',
				'key'       => 'og:image',
				'value'     => $context['og_image'],
			),
			array(
				'attribute' => 'property',
				'key'       => 'og:image:alt',
				'value'     => $context['og_image_alt'] ?? '',
			),
			array(
				'attribute' => 'property',
				'key'       => 'og:image:width',
				'value'     => $context['og_image_width'] ?? 0,
			),
			array(
				'attribute' => 'property',
				'key'       => 'og:image:height',
				'value'     => $context['og_image_height'] ?? 0,
			),
			array(
				'attribute' => 'property',
				'key'       => 'og:site_name',
				'value'     => get_bloginfo( 'name' ),
			),
			array(
				'attribute' => 'name',
				'key'       => 'twitter:card',
				'value'     => $context['twitter_card'],
			),
			array(
				'attribute' => 'name',
				'key'       => 'twitter:title',
				'value'     => $context['og_title'],
			),
			array(
				'attribute' => 'name',
				'key'       => 'twitter:description',
				'value'     => $context['og_description'],
			),
			array(
				'attribute' => 'name',
				'key'       => 'twitter:image',
				'value'     => $context['og_image'],
			),
			array(
				'attribute' => 'name',
				'key'       => 'twitter:image:alt',
				'value'     => $context['og_image_alt'] ?? '',
			),
		);

		if ( ! empty( $context['is_404'] ) ) {
			$tags = array();
		}

		$tags = array_filter(
			$tags,
			function ( $tag ) {
				return ! empty( $tag['value'] );
			}
		);

		$tags = apply_filters( 'lightweight_seo_meta_tags', $tags, $context );

		do_action( 'lightweight_seo_before_meta_tags', $tags, $context );

		foreach ( $tags as $tag ) {
			if ( ! isset( $tag['attribute'], $tag['key'], $tag['value'] ) ) {
				continue;
			}

			if ( ! in_array( $tag['attribute'], array( 'name', 'property' ), true ) ) {
				continue;
			}

			echo '<meta ' . esc_attr( $tag['attribute'] ) . '="' . esc_attr( $tag['key'] ) . '" content="' . esc_attr( $this->normalize_attribute_value( $tag['value'] ) ) . '" />' . "\n";
		}

		do_action( 'lightweight_seo_after_meta_tags', $tags, $context );
	}

	/**
	 * Merge plugin directives into the WordPress robots API result.
	 *
	 * @param array $robots Existing WordPress robots directives.
	 * @return array
	 */
	public function filter_robots( $robots ) {
		$context = $this->page_context->get_context();

		foreach ( explode( ',', (string) ( $context['robots'] ?? '' ) ) as $directive ) {
			$directive = trim( $directive );

			if ( '' === $directive ) {
				continue;
			}

			$parts = array_map( 'trim', explode( ':', $directive, 2 ) );
			$key   = $parts[0];
			$value = isset( $parts[1] ) ? $parts[1] : true;

			$robots[ $key ] = $value;
		}

		return $robots;
	}

	/**
	 * Resolve singular canonicals through the WordPress canonical API.
	 *
	 * @param string  $canonical_url WordPress canonical URL.
	 * @param WP_Post $post          Current post object.
	 * @return string
	 */
	public function filter_canonical_url( $canonical_url, $post ) {
		$context = $this->page_context->get_context();

		if ( ! empty( $context['is_404'] ) ) {
			return '';
		}

		if ( is_singular() && empty( $context['canonical_custom'] ) ) {
			return $canonical_url;
		}

		return ! empty( $context['canonical_url'] ) ? (string) $context['canonical_url'] : $canonical_url;
	}

	/**
	 * Output canonical and extension link tags for non-singular requests.
	 *
	 * Singular canonicals remain owned by WordPress core's rel_canonical().
	 *
	 * @return void
	 */
	public function add_non_singular_canonical() {
		$context = $this->page_context->get_context();
		$links   = array();

		if ( ! is_singular() && empty( $context['is_404'] ) && ! empty( $context['canonical_url'] ) ) {
			$links[] = array(
				'rel'  => 'canonical',
				'href' => $context['canonical_url'],
			);
		}

		$links = apply_filters( 'lightweight_seo_link_tags', $links, $context );

		$canonical_output = false;

		foreach ( $links as $link ) {
			if ( ! isset( $link['rel'], $link['href'] ) || empty( $link['href'] ) ) {
				continue;
			}

			if ( 'canonical' === strtolower( (string) $link['rel'] ) ) {
				if ( ! empty( $context['is_404'] ) || is_singular() || $canonical_output ) {
					continue;
				}

				$canonical_output = true;
			}

			echo '<link rel="' . esc_attr( $link['rel'] ) . '" href="' . esc_url( $link['href'] ) . '" />' . "\n";
		}
	}

	/**
	 * Normalize whitespace and markup before HTML attribute escaping.
	 *
	 * @param mixed $value Raw tag value.
	 * @return string
	 */
	private function normalize_attribute_value( $value ) {
		$value = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
		$value = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $value, true ) : strip_tags( $value );

		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}
}
