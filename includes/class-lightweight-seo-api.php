<?php
/**
 * Read-only public API for Lightweight SEO extensions.
 *
 * @since 1.1.0
 * @package Lightweight_SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Exposes normalized SEO facts without exposing mutable internal services.
 */
final class Lightweight_SEO_API {

	private $page_context;
	private $post_meta;
	private $archive_meta;
	private $module_registry;

	public function __construct( $page_context, $post_meta, $archive_meta, $module_registry ) {
		$this->page_context    = $page_context;
		$this->post_meta       = $post_meta;
		$this->archive_meta    = $archive_meta;
		$this->module_registry = $module_registry;
	}

	public function get_api_version() {
		return LIGHTWEIGHT_SEO_API_VERSION;
	}

	public function get_plugin_version() {
		return LIGHTWEIGHT_SEO_VERSION;
	}

	public function is_compatible( $minimum_plugin_version, $minimum_api_version ) {
		return version_compare( LIGHTWEIGHT_SEO_VERSION, (string) $minimum_plugin_version, '>=' )
			&& version_compare( LIGHTWEIGHT_SEO_API_VERSION, (string) $minimum_api_version, '>=' );
	}

	public function get_current_context() {
		$context     = $this->normalize_context( $this->page_context->get_context() );
		$object_type = 'request';
		$object_id   = get_queried_object_id();

		if ( is_singular() ) {
			$object_type = 'post';
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$object_type = 'term';
		} elseif ( is_author() ) {
			$object_type = 'user';
		}

		$context['canonical_url'] = $this->get_canonical_url( $object_type, $object_id, $context['canonical_url'] ?? '' );
		$context['indexable']     = (bool) apply_filters( 'lightweight_seo_is_indexable', $context['indexable'], $object_type, $object_id, $context );

		return $context;
	}

	public function get_object_context( $object_type, $object_id ) {
		$object_type = sanitize_key( $object_type );
		$object_id   = absint( $object_id );

		if ( ! $object_id || ! in_array( $object_type, array( 'post', 'term', 'user' ), true ) ) {
			return array();
		}

		if ( 'post' === $object_type ) {
			$meta        = $this->post_meta->get_all( $object_id );
			$canonical   = ! empty( $meta['seo_canonical_url'] ) ? $meta['seo_canonical_url'] : get_permalink( $object_id );
			$post        = get_post( $object_id );
			$title       = ! empty( $meta['seo_title'] ) ? $meta['seo_title'] : get_the_title( $object_id );
			$description = ! empty( $meta['seo_description'] ) ? $meta['seo_description'] : ( $post->post_excerpt ?? ( $post->post_content ?? '' ) );
		} elseif ( 'term' === $object_type ) {
			$meta        = $this->archive_meta->get_term_all( $object_id );
			$term_link   = get_term_link( $object_id );
			$canonical   = ! empty( $meta['seo_canonical_url'] ) ? $meta['seo_canonical_url'] : ( is_wp_error( $term_link ) ? '' : $term_link );
			$term        = get_term( $object_id );
			$title       = ! empty( $meta['seo_title'] ) ? $meta['seo_title'] : ( $term->name ?? '' );
			$description = ! empty( $meta['seo_description'] ) ? $meta['seo_description'] : ( $term->description ?? '' );
		} else {
			$meta        = $this->archive_meta->get_user_all( $object_id );
			$canonical   = ! empty( $meta['seo_canonical_url'] ) ? $meta['seo_canonical_url'] : get_author_posts_url( $object_id );
			$user        = get_userdata( $object_id );
			$title       = ! empty( $meta['seo_title'] ) ? $meta['seo_title'] : ( $user->display_name ?? '' );
			$description = ! empty( $meta['seo_description'] ) ? $meta['seo_description'] : ( $user->description ?? '' );
		}

		$context                  = array(
			'object_type'   => $object_type,
			'object_id'     => $object_id,
			'title'         => $this->normalize_text( $title ),
			'description'   => $this->normalize_text( $description ),
			'canonical_url' => esc_url_raw( $canonical ),
			'robots'        => $this->build_robots( $meta ),
		);
		$context['indexable']     = $this->is_indexable( $object_type, $object_id, $context );
		$context['canonical_url'] = $this->get_canonical_url( $object_type, $object_id, $context['canonical_url'] );

		return $context;
	}

	public function is_indexable( $object_type, $object_id, $context = null ) {
		if ( null === $context ) {
			$context = $this->get_object_context( $object_type, $object_id );

			return ! empty( $context['indexable'] );
		}

		$indexable = false === strpos( (string) ( $context['robots'] ?? '' ), 'noindex' );

		return (bool) apply_filters( 'lightweight_seo_is_indexable', $indexable, sanitize_key( $object_type ), absint( $object_id ), $context );
	}

	public function get_canonical_url( $object_type, $object_id, $canonical_url = null ) {
		if ( null === $canonical_url ) {
			$context = $this->get_object_context( $object_type, $object_id );

			return $context['canonical_url'] ?? '';
		}

		return esc_url_raw( apply_filters( 'lightweight_seo_canonical_url', $canonical_url, sanitize_key( $object_type ), absint( $object_id ) ) );
	}

	public function get_supported_object_types() {
		return array(
			'post' => $this->post_meta->get_supported_post_types(),
			'term' => $this->archive_meta->get_supported_taxonomies(),
			'user' => array( 'author' ),
		);
	}

	public function get_sitemap_urls() {
		$urls = apply_filters( 'wp_sitemaps_enabled', true ) ? array( home_url( '/wp-sitemap.xml' ) ) : array();

		return array_values( array_filter( array_map( 'esc_url_raw', (array) apply_filters( 'lightweight_seo_sitemap_urls', $urls ) ) ) );
	}

	public function get_modules() {
		return $this->module_registry->get_public_modules();
	}

	private function normalize_context( $context ) {
		$normalized              = array_intersect_key(
			(array) $context,
			array_flip( array( 'document_title', 'description', 'canonical_url', 'robots', 'og_title', 'og_description', 'og_image', 'og_type', 'twitter_card', 'is_404' ) )
		);
		$normalized['indexable'] = empty( $normalized['is_404'] ) && false === strpos( (string) ( $normalized['robots'] ?? '' ), 'noindex' );

		return $normalized;
	}

	private function build_robots( $meta ) {
		$robots = array();

		foreach ( array( 'noindex', 'nofollow', 'noarchive', 'nosnippet' ) as $directive ) {
			if ( '1' === (string) ( $meta[ 'seo_' . $directive ] ?? '' ) ) {
				$robots[] = $directive;
			}
		}

		return implode( ', ', $robots );
	}

	/**
	 * Normalize public human-readable values.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function normalize_text( $value ) {
		$value = function_exists( 'strip_shortcodes' ) ? strip_shortcodes( (string) $value ) : (string) $value;
		$value = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $value, true ) : strip_tags( $value );

		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}
}
