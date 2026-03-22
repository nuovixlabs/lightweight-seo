<?php
/**
 * Image SEO audit service for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Image SEO audit service.
 */
class Lightweight_SEO_Image_Audit_Service {

	/**
	 * Stored audit option name.
	 *
	 * @since    1.1.0
	 * @var      string
	 */
	const REPORT_OPTION_NAME = 'lightweight_seo_image_audit_report';

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
	 * Initialize the service.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings     $settings        Shared settings service.
	 * @param    Lightweight_SEO_Post_Meta    $post_meta       Shared post meta service.
	 * @param    bool                         $register_hooks  Whether to register invalidation hooks.
	 */
	public function __construct( $settings, $post_meta, $register_hooks = true ) {
		$this->settings  = $settings;
		$this->post_meta = $post_meta;

		if ( $register_hooks ) {
			add_action( 'save_post', array( $this, 'invalidate_report_cache' ) );
			add_action( 'deleted_post', array( $this, 'invalidate_report_cache' ) );
		}
	}

	/**
	 * Get the cached image audit report.
	 *
	 * @since    1.1.0
	 * @param    bool    $force_refresh    Whether to rebuild the report.
	 * @return   array
	 */
	public function get_report( $force_refresh = false ) {
		$cache_key = $this->get_cache_key();
		$cached    = function_exists( 'wp_cache_get' ) ? wp_cache_get( $cache_key, 'lightweight_seo' ) : false;

		if ( ! $force_refresh && is_array( $cached ) && $this->is_cached_report_fresh( $cached ) ) {
			return $cached;
		}

		$report = get_option( self::REPORT_OPTION_NAME, array() );

		if ( ! $force_refresh && $this->is_cached_report_fresh( $report ) ) {
			if ( function_exists( 'wp_cache_set' ) ) {
				wp_cache_set( $cache_key, $report, 'lightweight_seo', 900 );
			}

			return $report;
		}

		$report = $this->build_report();
		update_option( self::REPORT_OPTION_NAME, $report );

		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( $cache_key, $report, 'lightweight_seo', 900 );
		}

		return $report;
	}

	/**
	 * Invalidate the cached image audit report.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function invalidate_report_cache() {
		update_option( self::REPORT_OPTION_NAME, array() );

		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $this->get_cache_key(), 'lightweight_seo' );
		}
	}

	/**
	 * Build a fresh image SEO audit report.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function build_report() {
		$posts                  = get_posts(
			array(
				'post_type'              => $this->post_meta->get_supported_post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$minimum_width          = $this->settings->get_discover_min_image_width();
		$minimum_height         = $this->settings->get_discover_min_image_height();
		$missing_featured_image = array();
		$missing_alt_text       = array();
		$undersized_images      = array();

		foreach ( $posts as $post ) {
			$post_meta = $this->post_meta->get_all( (int) $post->ID );

			if ( '1' === (string) ( $post_meta['seo_noindex'] ?? '' ) ) {
				continue;
			}

			if ( $this->should_exclude_attachment( $post ) ) {
				continue;
			}

			$post_title = ! empty( $post->post_title ) ? $post->post_title : get_the_title( $post->ID );
			$post_url   = get_permalink( $post );

			if ( ! has_post_thumbnail( $post->ID ) ) {
				$missing_featured_image[] = array(
					'id'    => (int) $post->ID,
					'title' => $post_title,
					'url'   => $post_url,
				);

				continue;
			}

			$thumbnail_id = function_exists( 'get_post_thumbnail_id' ) ? absint( get_post_thumbnail_id( $post->ID ) ) : 0;
			$metadata     = $thumbnail_id && function_exists( 'wp_get_attachment_metadata' ) ? wp_get_attachment_metadata( $thumbnail_id ) : array();
			$width        = absint( $metadata['width'] ?? 0 );
			$height       = absint( $metadata['height'] ?? 0 );
			$alt_text     = $thumbnail_id ? trim( (string) get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ) : '';

			if ( $thumbnail_id && empty( $alt_text ) ) {
				$missing_alt_text[] = array(
					'id'            => (int) $post->ID,
					'title'         => $post_title,
					'url'           => $post_url,
					'attachment_id' => $thumbnail_id,
				);
			}

			if ( $thumbnail_id && ( $width < $minimum_width || $height < $minimum_height ) ) {
				$undersized_images[] = array(
					'id'            => (int) $post->ID,
					'title'         => $post_title,
					'url'           => $post_url,
					'attachment_id' => $thumbnail_id,
					'width'         => $width,
					'height'        => $height,
				);
			}
		}

		return array(
			'generated_at'            => gmdate( 'c' ),
			'minimum_width'           => $minimum_width,
			'minimum_height'          => $minimum_height,
			'missing_featured_images' => array_slice( $missing_featured_image, 0, 20 ),
			'missing_alt_text'        => array_slice( $missing_alt_text, 0, 20 ),
			'undersized_images'       => array_slice( $undersized_images, 0, 20 ),
		);
	}

	/**
	 * Determine whether a cached report still reflects the current settings.
	 *
	 * @since    1.1.0
	 * @param    array $report Cached report payload.
	 * @return   bool
	 */
	private function is_cached_report_fresh( $report ) {
		if ( empty( $report['generated_at'] ) ) {
			return false;
		}

		return (int) ( $report['minimum_width'] ?? 0 ) === $this->settings->get_discover_min_image_width()
			&& (int) ( $report['minimum_height'] ?? 0 ) === $this->settings->get_discover_min_image_height();
	}

	/**
	 * Determine whether an attachment should be excluded by default.
	 *
	 * @since    1.1.0
	 * @param    object $post Candidate post object.
	 * @return   bool
	 */
	private function should_exclude_attachment( $post ) {
		if ( empty( $post ) || 'attachment' !== (string) ( $post->post_type ?? '' ) ) {
			return false;
		}

		return method_exists( $this->settings, 'attachment_pages_noindex_enabled' ) && $this->settings->attachment_pages_noindex_enabled();
	}

	/**
	 * Get the site-scoped cache key.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	private function get_cache_key() {
		$blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;

		return 'image_audit_report_' . $blog_id;
	}
}
