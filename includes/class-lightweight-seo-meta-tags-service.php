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
		$context = $this->page_context->get_context();
		$links   = array(
			array(
				'rel'  => 'canonical',
				'href' => $context['canonical_url'] ?? '',
			),
		);
		$tags    = array(
			array(
				'attribute' => 'name',
				'key'       => 'description',
				'value'     => $context['description'],
			),
			array(
				'attribute' => 'name',
				'key'       => 'keywords',
				'value'     => ! empty( $context['keywords_enabled'] ) ? $context['keywords'] : '',
			),
			array(
				'attribute' => 'name',
				'key'       => 'robots',
				'value'     => $context['robots'],
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
		);

		$tags = array_filter(
			$tags,
			function ( $tag ) {
				return ! empty( $tag['value'] );
			}
		);

		$tags = apply_filters( 'lightweight_seo_meta_tags', $tags, $context );

		$links = array_filter(
			$links,
			function ( $link ) {
				return ! empty( $link['href'] );
			}
		);

		$links = apply_filters( 'lightweight_seo_link_tags', $links, $context );

		do_action( 'lightweight_seo_before_meta_tags', $tags, $context );

		foreach ( $links as $link ) {
			if ( ! isset( $link['rel'], $link['href'] ) ) {
				continue;
			}

			echo '<link rel="' . esc_attr( $link['rel'] ) . '" href="' . esc_url( $link['href'] ) . '" />' . "\n";
		}

		foreach ( $tags as $tag ) {
			if ( ! isset( $tag['attribute'], $tag['key'], $tag['value'] ) ) {
				continue;
			}

			if ( ! in_array( $tag['attribute'], array( 'name', 'property' ), true ) ) {
				continue;
			}

			echo '<meta ' . esc_attr( $tag['attribute'] ) . '="' . esc_attr( $tag['key'] ) . '" content="' . esc_attr( $tag['value'] ) . '" />' . "\n";
		}

		do_action( 'lightweight_seo_after_meta_tags', $tags, $context );
	}
}
