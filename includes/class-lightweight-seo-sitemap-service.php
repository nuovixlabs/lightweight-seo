<?php
/**
 * Sitemap integration service for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Sitemap integration service.
 */
class Lightweight_SEO_Sitemap_Service {

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings
	 */
	private $settings;

	/**
	 * Shared post meta service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Post_Meta
	 */
	private $post_meta;

	/**
	 * Shared archive meta service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Archive_Meta
	 */
	private $archive_meta;

	/**
	 * Redirect source paths resolved to posts during this request.
	 *
	 * Core asks for the same exclusions once per post type and again for the
	 * sitemap index, so each rule is resolved a single time.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      array<string,array{id:int,type:string}>|null
	 */
	private $redirected_posts;

	/**
	 * Initialize the service.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings        $settings        Shared settings service.
	 * @param    Lightweight_SEO_Post_Meta       $post_meta       Shared post meta service.
	 * @param    Lightweight_SEO_Archive_Meta    $archive_meta    Shared archive meta service.
	 */
	public function __construct( $settings, $post_meta, $archive_meta ) {
		$this->settings     = $settings;
		$this->post_meta    = $post_meta;
		$this->archive_meta = $archive_meta;

		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'filter_posts_query_args' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies_query_args', array( $this, 'filter_taxonomies_query_args' ), 10, 2 );
		add_filter( 'wp_sitemaps_users_query_args', array( $this, 'filter_users_query_args' ) );
	}

	/**
	 * Exclude noindexed and redirected content from WordPress core sitemap post queries.
	 *
	 * @since    1.1.0
	 * @param    array     $args         Current query arguments.
	 * @param    string    $post_type    Post type being queried.
	 * @return   array
	 */
	public function filter_posts_query_args( $args, $post_type ) {
		if ( $this->settings->exclude_noindex_from_sitemaps_enabled() ) {
			$args = $this->append_noindex_meta_query(
				$args,
				$this->post_meta->get_meta_key( 'seo_noindex' )
			);

			// Password-protected content is always served noindex, so it does not belong in a sitemap.
			$args['has_password'] = false;
		}

		if ( $this->settings->exclude_redirected_from_sitemaps_enabled() ) {
			$args = $this->append_redirected_post_exclusions( $args, $post_type );
		}

		return $args;
	}

	/**
	 * Exclude noindexed terms from taxonomy sitemap queries.
	 *
	 * @since    1.1.0
	 * @param    array     $args        Current query arguments.
	 * @param    string    $taxonomy    Taxonomy being queried.
	 * @return   array
	 */
	public function filter_taxonomies_query_args( $args, $taxonomy ) {
		if ( ! $this->settings->exclude_noindex_from_sitemaps_enabled() ) {
			return $args;
		}

		return $this->append_noindex_meta_query(
			$args,
			$this->archive_meta->get_meta_key( 'seo_noindex' )
		);
	}

	/**
	 * Exclude noindexed author archives from user sitemap queries.
	 *
	 * @since    1.1.0
	 * @param    array    $args    Current user query arguments.
	 * @return   array
	 */
	public function filter_users_query_args( $args ) {
		if ( ! $this->settings->exclude_noindex_from_sitemaps_enabled() ) {
			return $args;
		}

		return $this->append_noindex_meta_query(
			$args,
			$this->archive_meta->get_meta_key( 'seo_noindex' )
		);
	}

	/**
	 * Append a "not noindexed" constraint to a sitemap meta query.
	 *
	 * @since    1.1.0
	 * @param    array     $args        Current query arguments.
	 * @param    string    $meta_key    Registered noindex meta key.
	 * @return   array
	 */
	private function append_noindex_meta_query( $args, $meta_key ) {
		$noindex_query = array(
			'relation' => 'OR',
			array(
				'key'     => $meta_key,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => $meta_key,
				'value'   => '1',
				'compare' => '!=',
			),
		);

		if ( empty( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
			$args['meta_query'] = array( $noindex_query );

			return $args;
		}

		$args['meta_query'] = array(
			'relation' => 'AND',
			$args['meta_query'],
			$noindex_query,
		);

		return $args;
	}

	/**
	 * Append exclusions for posts whose current paths are redirected elsewhere.
	 *
	 * @since    1.1.0
	 * @param    array     $args         Current query arguments.
	 * @param    string    $post_type    Post type being queried.
	 * @return   array
	 */
	private function append_redirected_post_exclusions( $args, $post_type ) {
		if ( ! class_exists( 'Lightweight_SEO_Redirects_Service' ) || ! function_exists( 'url_to_postid' ) ) {
			return $args;
		}

		$excluded_ids = array();

		foreach ( $this->get_redirected_posts() as $redirected_post ) {
			if ( $redirected_post['type'] === $post_type ) {
				$excluded_ids[] = $redirected_post['id'];
			}
		}

		if ( empty( $excluded_ids ) ) {
			return $args;
		}

		$args['post__not_in'] = array_values(
			array_unique(
				array_merge(
					array_map( 'absint', (array) ( $args['post__not_in'] ?? array() ) ),
					$excluded_ids
				)
			)
		);

		return $args;
	}

	/**
	 * Resolve every redirect source that is the live path of a published post.
	 *
	 * @since    1.1.0
	 * @return   array<string,array{id:int,type:string}>
	 */
	private function get_redirected_posts() {
		if ( null !== $this->redirected_posts ) {
			return $this->redirected_posts;
		}

		$this->redirected_posts = array();
		$redirects_service      = new Lightweight_SEO_Redirects_Service( $this->settings, false );

		foreach ( $redirects_service->get_all_redirect_rules() as $rule ) {
			$source = $this->normalize_path( $rule['source'] ?? '' );

			if ( empty( $source ) || isset( $this->redirected_posts[ $source ] ) ) {
				continue;
			}

			$post = $this->find_redirected_post( $source );

			if ( $post ) {
				$this->redirected_posts[ $source ] = array(
					'id'   => (int) $post->ID,
					'type' => (string) $post->post_type,
				);
			}
		}

		return $this->redirected_posts;
	}

	/**
	 * Resolve a redirect source path to the published post currently served there.
	 *
	 * @since    1.1.0
	 * @param    string    $source    Normalized, host-absolute redirect source path.
	 * @return   WP_Post|object|null
	 */
	private function find_redirected_post( $source ) {
		$candidate_urls = array();

		if ( '/' === $source ) {
			$candidate_urls[] = Lightweight_SEO_Url::from_path( '/' );
		} else {
			$candidate_urls[] = Lightweight_SEO_Url::from_path( trailingslashit( $source ) );
			$candidate_urls[] = Lightweight_SEO_Url::from_path( $source );
		}

		foreach ( array_unique( $candidate_urls ) as $candidate_url ) {
			$post_id = absint( url_to_postid( $candidate_url ) );

			if ( 0 === $post_id ) {
				continue;
			}

			$post = get_post( $post_id );

			if ( empty( $post ) || empty( $post->post_type ) || 'publish' !== (string) ( $post->post_status ?? '' ) ) {
				continue;
			}

			$current_path = $this->normalize_path( (string) wp_parse_url( (string) get_permalink( $post ), PHP_URL_PATH ) );

			if ( $current_path === $source ) {
				return $post;
			}
		}

		return null;
	}

	/**
	 * Normalize a request or permalink path to a sitemap-comparable form.
	 *
	 * @since    1.1.0
	 * @param    string    $path    Path to normalize.
	 * @return   string
	 */
	private function normalize_path( $path ) {
		$normalized = '/' . ltrim( trim( (string) $path ), '/' );

		if ( '/' !== $normalized ) {
			$normalized = rtrim( $normalized, '/' );
		}

		return $normalized;
	}
}
