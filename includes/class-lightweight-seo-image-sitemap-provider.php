<?php
/**
 * Image sitemap provider for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Image sitemap provider backed by attachment URLs.
 */
class Lightweight_SEO_Image_Sitemap_Provider extends WP_Sitemaps_Provider {

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings    $settings
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
		$this->name        = 'lightweightseoimages';
		$this->object_type = 'image';
	}

	/**
	 * Get a URL list for an image sitemap page.
	 *
	 * @since    1.1.0
	 * @param    int       $page_num          Page of results.
	 * @param    string    $object_subtype    Optional subtype. Unused.
	 * @return   array
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		if ( ! $this->settings->image_sitemaps_enabled() ) {
			return array();
		}

		$attachments = get_posts( $this->get_query_args( (int) $page_num, $this->get_max_urls_per_sitemap() ) );
		$url_list    = array();

		foreach ( $attachments as $attachment ) {
			$attachment_url = wp_get_attachment_url( $attachment->ID );

			if ( empty( $attachment_url ) ) {
				continue;
			}

			$sitemap_entry = array(
				'loc' => $attachment_url,
			);

			$lastmod = $this->format_last_modified( $attachment );

			if ( ! empty( $lastmod ) ) {
				$sitemap_entry['lastmod'] = $lastmod;
			}

			$sitemap_entry = apply_filters( 'lightweight_seo_image_sitemap_entry', $sitemap_entry, $attachment );

			if ( ! empty( $sitemap_entry ) ) {
				$url_list[] = $sitemap_entry;
			}
		}

		return $url_list;
	}

	/**
	 * Get the number of sitemap pages needed for image attachments.
	 *
	 * @since    1.1.0
	 * @param    string    $object_subtype    Optional subtype. Unused.
	 * @return   int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		if ( ! $this->settings->image_sitemaps_enabled() ) {
			return 0;
		}

		$total_images = count(
			get_posts(
				$this->get_query_args( 1, -1, true )
			)
		);

		if ( 0 === $total_images ) {
			return 0;
		}

		return (int) ceil( $total_images / $this->get_max_urls_per_sitemap() );
	}

	/**
	 * Build query args for image attachment lookups.
	 *
	 * @since    1.1.0
	 * @param    int     $page_num          Page of results.
	 * @param    int     $posts_per_page    Number of attachments to return.
	 * @param    bool    $ids_only          Whether to request IDs only.
	 * @return   array
	 */
	private function get_query_args( $page_num, $posts_per_page, $ids_only = false ) {
		$args = array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'post_mime_type'         => 'image',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'posts_per_page'         => $posts_per_page,
			'paged'                  => max( 1, (int) $page_num ),
			'fields'                 => $ids_only ? 'ids' : '',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		return apply_filters( 'lightweight_seo_image_sitemap_query_args', $args, $page_num, $posts_per_page, $ids_only );
	}

	/**
	 * Get the maximum number of URLs per image sitemap page.
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

	/**
	 * Format a sitemap-compatible last modified date for an attachment.
	 *
	 * @since    1.1.0
	 * @param    WP_Post|object    $attachment    Attachment object.
	 * @return   string
	 */
	private function format_last_modified( $attachment ) {
		$modified_gmt = $attachment->post_modified_gmt ?? $attachment->post_date_gmt ?? '';
		$timestamp    = strtotime( (string) $modified_gmt );

		if ( ! $timestamp ) {
			return '';
		}

		return gmdate( DATE_W3C, $timestamp );
	}
}
