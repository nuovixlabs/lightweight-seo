<?php
/**
 * Internal link analysis service for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Internal link analysis service.
 */
class Lightweight_SEO_Internal_Links_Service {

	/**
	 * Cached report option name.
	 *
	 * @since    1.1.0
	 * @var      string
	 */
	const REPORT_OPTION_NAME = 'lightweight_seo_internal_links_report';

	/**
	 * Cache lifetime in seconds.
	 *
	 * @since    1.1.0
	 * @var      int
	 */
	const REPORT_CACHE_TTL = 900;

	/**
	 * Shared post meta service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Post_Meta    $post_meta
	 */
	private $post_meta;

	/**
	 * Initialize the service.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Post_Meta    $post_meta          Shared post meta service.
	 * @param    bool                         $register_hooks     Whether to register invalidation hooks.
	 */
	public function __construct( $post_meta, $register_hooks = true ) {
		$this->post_meta = $post_meta;

		if ( $register_hooks ) {
			add_action( 'save_post', array( $this, 'invalidate_report_cache' ) );
			add_action( 'deleted_post', array( $this, 'invalidate_report_cache' ) );
			add_action( 'trashed_post', array( $this, 'invalidate_report_cache' ) );
			add_action( 'untrashed_post', array( $this, 'invalidate_report_cache' ) );
		}
	}

	/**
	 * Get the internal link report, computing it when stale.
	 *
	 * @since    1.1.0
	 * @param    bool    $force_refresh    Whether to bypass cache.
	 * @return   array
	 */
	public function get_report( $force_refresh = false ) {
		$cached_report = get_option( self::REPORT_OPTION_NAME, array() );

		if ( ! $force_refresh && $this->is_cached_report_fresh( $cached_report ) ) {
			return $cached_report;
		}

		$report = $this->build_report();

		update_option( self::REPORT_OPTION_NAME, $report );

		return $report;
	}

	/**
	 * Invalidate the cached internal link report.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function invalidate_report_cache() {
		update_option( self::REPORT_OPTION_NAME, array() );
	}

	/**
	 * Build a fresh internal link report.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function build_report() {
		$posts            = $this->get_indexable_posts();
		$page_map         = array();
		$broken_links     = array();
		$total_link_count = 0;

		foreach ( $posts as $post ) {
			$permalink = get_permalink( $post );
			$path      = $this->normalize_path( (string) wp_parse_url( (string) $permalink, PHP_URL_PATH ) );

			if ( empty( $permalink ) || empty( $path ) ) {
				continue;
			}

			$page_map[ $path ] = array(
				'id'       => (int) $post->ID,
				'title'    => ! empty( $post->post_title ) ? $post->post_title : get_the_title( $post->ID ),
				'url'      => $permalink,
				'path'     => $path,
				'inbound'  => 0,
				'outbound' => 0,
			);
		}

		foreach ( $posts as $post ) {
			$source_url   = get_permalink( $post );
			$source_path  = $this->normalize_path( (string) wp_parse_url( (string) $source_url, PHP_URL_PATH ) );
			$source_title = ! empty( $post->post_title ) ? $post->post_title : get_the_title( $post->ID );

			if ( empty( $source_path ) || ! isset( $page_map[ $source_path ] ) ) {
				continue;
			}

			$internal_links = array_values(
				array_unique(
					$this->extract_internal_paths( (string) ( $post->post_content ?? '' ) )
				)
			);

			foreach ( $internal_links as $target_path ) {
				if ( $target_path === $source_path ) {
					continue;
				}

				++$total_link_count;
				++$page_map[ $source_path ]['outbound'];

				if ( isset( $page_map[ $target_path ] ) ) {
					++$page_map[ $target_path ]['inbound'];

					continue;
				}

				if ( $this->should_ignore_target_path( $target_path ) ) {
					continue;
				}

				$broken_links[] = array(
					'source_title' => $source_title,
					'source_url'   => $source_url,
					'target_path'  => $target_path,
				);
			}
		}

		$orphan_pages = array_values(
			array_filter(
				$page_map,
				function ( $page ) {
					return 0 === (int) $page['inbound'];
				}
			)
		);

		$weak_pages = array_values(
			array_filter(
				$page_map,
				function ( $page ) {
					return 1 === (int) $page['inbound'];
				}
			)
		);

		usort( $orphan_pages, array( $this, 'sort_pages_by_title' ) );
		usort( $weak_pages, array( $this, 'sort_pages_by_title' ) );
		usort( $broken_links, array( $this, 'sort_broken_links' ) );

		return array(
			'generated_at'   => gmdate( 'c' ),
			'pages_scanned'  => count( $page_map ),
			'internal_links' => $total_link_count,
			'orphan_pages'   => $orphan_pages,
			'weak_pages'     => $weak_pages,
			'broken_links'   => $broken_links,
		);
	}

	/**
	 * Get published public posts that should be scanned for internal links.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function get_indexable_posts() {
		$posts = get_posts(
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

		return array_values(
			array_filter(
				$posts,
				function ( $post ) {
					$post_meta = $this->post_meta->get_all( (int) $post->ID );

					return '1' !== (string) ( $post_meta['seo_noindex'] ?? '' );
				}
			)
		);
	}

	/**
	 * Determine whether a cached report is still fresh enough to reuse.
	 *
	 * @since    1.1.0
	 * @param    array    $report    Cached report payload.
	 * @return   bool
	 */
	private function is_cached_report_fresh( $report ) {
		if ( empty( $report['generated_at'] ) ) {
			return false;
		}

		$generated_at = strtotime( (string) $report['generated_at'] );

		if ( ! $generated_at ) {
			return false;
		}

		return ( time() - $generated_at ) < self::REPORT_CACHE_TTL;
	}

	/**
	 * Extract normalized internal paths from raw post content.
	 *
	 * @since    1.1.0
	 * @param    string    $content    Raw post content.
	 * @return   array
	 */
	private function extract_internal_paths( $content ) {
		preg_match_all( '/href=(["\'])(.*?)\1/i', $content, $matches );

		if ( empty( $matches[2] ) ) {
			return array();
		}

		$internal_paths = array();
		$home_host      = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

		foreach ( $matches[2] as $href ) {
			$href = trim( (string) $href );

			if ( '' === $href || 0 === strpos( $href, '#' ) || 0 === strpos( $href, 'mailto:' ) || 0 === strpos( $href, 'tel:' ) ) {
				continue;
			}

			$path = '';
			$host = strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) );

			if ( 0 === strpos( $href, '/' ) ) {
				$path = (string) wp_parse_url( $href, PHP_URL_PATH );
			} elseif ( ! empty( $host ) && $host === $home_host ) {
				$path = (string) wp_parse_url( $href, PHP_URL_PATH );
			}

			$path = $this->normalize_path( $path );

			if ( ! empty( $path ) ) {
				$internal_paths[] = $path;
			}
		}

		return $internal_paths;
	}

	/**
	 * Normalize a site-relative path.
	 *
	 * @since    1.1.0
	 * @param    string    $path    Raw path.
	 * @return   string
	 */
	private function normalize_path( $path ) {
		$path = trim( (string) $path );

		if ( '' === $path ) {
			return '';
		}

		$path = '/' . ltrim( $path, '/' );

		if ( '/' !== $path ) {
			$path = rtrim( $path, '/' );
		}

		return $path;
	}

	/**
	 * Determine whether a missing path should be ignored in broken-link reporting.
	 *
	 * @since    1.1.0
	 * @param    string    $path    Normalized path.
	 * @return   bool
	 */
	private function should_ignore_target_path( $path ) {
		return 1 === preg_match( '/\.[a-z0-9]{2,5}$/i', $path );
	}

	/**
	 * Sort pages alphabetically by title.
	 *
	 * @since    1.1.0
	 * @param    array    $left     Left page data.
	 * @param    array    $right    Right page data.
	 * @return   int
	 */
	private function sort_pages_by_title( $left, $right ) {
		return strcmp( (string) $left['title'], (string) $right['title'] );
	}

	/**
	 * Sort broken links by source title and then target path.
	 *
	 * @since    1.1.0
	 * @param    array    $left     Left broken link data.
	 * @param    array    $right    Right broken link data.
	 * @return   int
	 */
	private function sort_broken_links( $left, $right ) {
		$title_comparison = strcmp( (string) $left['source_title'], (string) $right['source_title'] );

		if ( 0 !== $title_comparison ) {
			return $title_comparison;
		}

		return strcmp( (string) $left['target_path'], (string) $right['target_path'] );
	}
}
