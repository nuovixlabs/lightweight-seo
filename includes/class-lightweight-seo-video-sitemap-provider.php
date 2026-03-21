<?php
/**
 * Video sitemap provider for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Video sitemap provider backed by attachment URLs.
 */
class Lightweight_SEO_Video_Sitemap_Provider extends WP_Sitemaps_Provider {

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings
	 */
	private $settings;

	/**
	 * Initialize the provider.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings    $settings    Shared settings service.
	 */
	public function __construct( $settings ) {
		$this->settings    = $settings;
		$this->name        = 'lightweightseovideos';
		$this->object_type = 'video';
	}

	/**
	 * Get a URL list for a video sitemap page.
	 *
	 * @since    1.1.0
	 * @param    int       $page_num          Page of results.
	 * @param    string    $object_subtype    Optional subtype. Unused.
	 * @return   array
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		if ( ! $this->settings->video_sitemaps_enabled() ) {
			return array();
		}

		$attachments = get_posts( $this->get_query_args( (int) $page_num, $this->get_max_urls_per_sitemap() ) );
		$url_list    = array();

		foreach ( $attachments as $attachment ) {
			$attachment_url = wp_get_attachment_url( $attachment->ID );

			if ( empty( $attachment_url ) ) {
				continue;
			}

			$entry = array(
				'loc' => $attachment_url,
			);

			if ( ! empty( $attachment->post_modified_gmt ) ) {
				$entry['lastmod'] = gmdate( DATE_W3C, strtotime( (string) $attachment->post_modified_gmt ) );
			}

			$url_list[] = $entry;
		}

		return $url_list;
	}

	/**
	 * Get the number of sitemap pages needed.
	 *
	 * @since    1.1.0
	 * @param    string    $object_subtype    Optional subtype. Unused.
	 * @return   int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		if ( ! $this->settings->video_sitemaps_enabled() ) {
			return 0;
		}

		$total_videos = count(
			get_posts(
				$this->get_query_args( 1, -1, true )
			)
		);

		if ( 0 === $total_videos ) {
			return 0;
		}

		return (int) ceil( $total_videos / $this->get_max_urls_per_sitemap() );
	}

	/**
	 * Build query args for video attachments.
	 *
	 * @since    1.1.0
	 * @param    int     $page_num          Page number.
	 * @param    int     $posts_per_page    Number of items.
	 * @param    bool    $ids_only          Whether to request IDs only.
	 * @return   array
	 */
	private function get_query_args( $page_num, $posts_per_page, $ids_only = false ) {
		return array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'post_mime_type'         => 'video',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'posts_per_page'         => $posts_per_page,
			'paged'                  => max( 1, (int) $page_num ),
			'fields'                 => $ids_only ? 'ids' : '',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
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

		return 2000;
	}
}
