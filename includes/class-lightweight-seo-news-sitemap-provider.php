<?php
/**
 * News sitemap provider for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * News sitemap provider backed by recent posts.
 */
class Lightweight_SEO_News_Sitemap_Provider extends WP_Sitemaps_Provider {

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
	 * Initialize the provider.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings     $settings     Shared settings service.
	 * @param    Lightweight_SEO_Post_Meta    $post_meta    Shared post meta service.
	 */
	public function __construct( $settings, $post_meta ) {
		$this->settings    = $settings;
		$this->post_meta   = $post_meta;
		$this->name        = 'lightweightseonews';
		$this->object_type = 'news';
	}

	/**
	 * Get a URL list for a news sitemap page.
	 *
	 * @since    1.1.0
	 * @param    int       $page_num          Page of results.
	 * @param    string    $object_subtype    Optional subtype. Unused.
	 * @return   array
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		if ( ! $this->settings->news_sitemaps_enabled() ) {
			return array();
		}

		$posts    = $this->get_recent_posts();
		$entries  = array();
		$per_page = $this->get_max_urls_per_sitemap();
		$posts    = array_slice( $posts, max( 0, ( (int) $page_num - 1 ) * $per_page ), $per_page );

		foreach ( $posts as $post ) {
			$post_meta = $this->post_meta->get_all( (int) $post->ID );

			if ( '1' === (string) ( $post_meta['seo_noindex'] ?? '' ) ) {
				continue;
			}

			$permalink = get_permalink( $post );

			if ( empty( $permalink ) ) {
				continue;
			}

			$entry = array(
				'loc' => $permalink,
			);

			if ( ! empty( $post->post_modified_gmt ) ) {
				$entry['lastmod'] = gmdate( DATE_W3C, strtotime( (string) $post->post_modified_gmt ) );
			}

			$entries[] = $entry;
		}

		return $entries;
	}

	/**
	 * Get the number of sitemap pages needed.
	 *
	 * @since    1.1.0
	 * @param    string    $object_subtype    Optional subtype. Unused.
	 * @return   int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		if ( ! $this->settings->news_sitemaps_enabled() ) {
			return 0;
		}

		$total_posts = count( $this->get_recent_posts() );

		if ( 0 === $total_posts ) {
			return 0;
		}

		return (int) ceil( $total_posts / $this->get_max_urls_per_sitemap() );
	}

	/**
	 * Get recent posts eligible for the news sitemap.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function get_recent_posts() {
		$posts        = get_posts(
			array(
				'post_type'              => array( 'post' ),
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$window_start = time() - 172800;
		$recent_posts = array();

		foreach ( $posts as $post ) {
			$post_date = $post->post_date_gmt ?? $post->post_modified_gmt ?? '';
			$timestamp = strtotime( (string) $post_date );

			if ( $timestamp && $timestamp < $window_start ) {
				continue;
			}

			$recent_posts[] = $post;
		}

		return $recent_posts;
	}

	/**
	 * Get the maximum number of URLs per sitemap page.
	 *
	 * @since    1.1.0
	 * @return   int
	 */
	private function get_max_urls_per_sitemap() {
		if ( function_exists( 'wp_sitemaps_get_max_urls' ) ) {
			return (int) wp_sitemaps_get_max_urls( $this->object_type );
		}

		return 1000;
	}
}
