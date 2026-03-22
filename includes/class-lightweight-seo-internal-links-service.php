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
	 * @var      Lightweight_SEO_Post_Meta
	 */
	private $post_meta;

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings|null
	 */
	private $settings;

	/**
	 * Initialize the service.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Post_Meta         $post_meta          Shared post meta service.
	 * @param    bool                              $register_hooks     Whether to register invalidation hooks.
	 * @param    Lightweight_SEO_Settings|null    $settings           Shared settings service.
	 */
	public function __construct( $post_meta, $register_hooks = true, $settings = null ) {
		$this->post_meta = $post_meta;
		$this->settings  = $settings;

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
		$cache_key = $this->get_cache_key();
		$cached    = function_exists( 'wp_cache_get' ) ? wp_cache_get( $cache_key, 'lightweight_seo' ) : false;

		if ( ! $force_refresh && is_array( $cached ) && $this->is_cached_report_fresh( $cached ) ) {
			return $cached;
		}

		$stored_report = get_option( self::REPORT_OPTION_NAME, array() );

		if ( ! $force_refresh && $this->is_cached_report_fresh( $stored_report ) ) {
			if ( function_exists( 'wp_cache_set' ) ) {
				wp_cache_set( $cache_key, $stored_report, 'lightweight_seo', self::REPORT_CACHE_TTL );
			}

			return $stored_report;
		}

		$report = $this->build_report();

		update_option( self::REPORT_OPTION_NAME, $report );

		if ( function_exists( 'wp_cache_set' ) ) {
			wp_cache_set( $cache_key, $report, 'lightweight_seo', self::REPORT_CACHE_TTL );
		}

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

		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $this->get_cache_key(), 'lightweight_seo' );
		}
	}

	/**
	 * Build a fresh internal link report.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function build_report() {
		$posts               = $this->get_indexable_posts();
		$page_map            = array();
		$broken_links        = array();
		$existing_links      = array();
		$anchor_map          = array();
		$content_tokens_map  = array();
		$title_tokens_map    = array();
		$content_phrases_map = array();
		$page_topics         = array();
		$total_link_count    = 0;

		foreach ( $posts as $post ) {
			$permalink = get_permalink( $post );
			$path      = $this->normalize_path( (string) wp_parse_url( (string) $permalink, PHP_URL_PATH ) );

			if ( empty( $permalink ) || empty( $path ) ) {
				continue;
			}

			$post_title        = ! empty( $post->post_title ) ? $post->post_title : get_the_title( $post->ID );
			$page_map[ $path ] = array(
				'id'       => (int) $post->ID,
				'title'    => $post_title,
				'url'      => $permalink,
				'path'     => $path,
				'inbound'  => 0,
				'outbound' => 0,
			);

			$title_tokens_map[ $path ]    = $this->tokenize_text( $post_title . ' ' . trim( str_replace( '/', ' ', $path ) ) );
			$content_tokens_map[ $path ]  = $this->tokenize_text(
				implode(
					' ',
					array(
						(string) $post_title,
						(string) ( $post->post_content ?? '' ),
						trim( str_replace( '/', ' ', $path ) ),
					)
				)
			);
			$content_phrases_map[ $path ] = $this->extract_phrases(
				implode(
					' ',
					array(
						(string) $post_title,
						(string) ( $post->post_content ?? '' ),
					)
				)
			);
		}

		foreach ( $posts as $post ) {
			$source_url   = get_permalink( $post );
			$source_path  = $this->normalize_path( (string) wp_parse_url( (string) $source_url, PHP_URL_PATH ) );
			$source_title = ! empty( $post->post_title ) ? $post->post_title : get_the_title( $post->ID );

			if ( empty( $source_path ) || ! isset( $page_map[ $source_path ] ) ) {
				continue;
			}

			$internal_links = $this->group_internal_links_by_target( $this->extract_internal_links( (string) ( $post->post_content ?? '' ) ) );

			foreach ( $internal_links as $target_path => $link_data ) {
				if ( $target_path === $source_path ) {
					continue;
				}

				$existing_links[ $source_path ][ $target_path ] = true;

				++$total_link_count;
				++$page_map[ $source_path ]['outbound'];

				if ( isset( $page_map[ $target_path ] ) ) {
					++$page_map[ $target_path ]['inbound'];
					$anchor_map[ $target_path ] = array_merge(
						$anchor_map[ $target_path ] ?? array(),
						$link_data['anchors']
					);

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

		foreach ( $page_map as $path => $page ) {
			$page_topics[ $path ] = $this->extract_topic_terms( $title_tokens_map[ $path ] ?? array(), $content_tokens_map[ $path ] ?? array() );
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

		$anchor_text_issues = $this->extract_anchor_text_issues( $page_map, $anchor_map );
		$link_suggestions   = $this->extract_link_suggestions( $page_map, $existing_links, $content_tokens_map, $title_tokens_map, $content_phrases_map );
		$topic_clusters     = $this->extract_topic_clusters( $page_map, $page_topics );

		return array(
			'generated_at'       => gmdate( 'c' ),
			'pages_scanned'      => count( $page_map ),
			'internal_links'     => $total_link_count,
			'orphan_pages'       => $orphan_pages,
			'weak_pages'         => $weak_pages,
			'broken_links'       => $broken_links,
			'anchor_text_issues' => $anchor_text_issues,
			'link_suggestions'   => $link_suggestions,
			'topic_clusters'     => $topic_clusters,
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

					if ( '1' === (string) ( $post_meta['seo_noindex'] ?? '' ) ) {
						return false;
					}

					if ( $this->should_exclude_attachment( $post ) ) {
						return false;
					}

					return true;
				}
			)
		);
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

		return ! empty( $this->settings ) && method_exists( $this->settings, 'attachment_pages_noindex_enabled' ) && $this->settings->attachment_pages_noindex_enabled();
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
	private function extract_internal_links( $content ) {
		preg_match_all( '/<a\b[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER );

		if ( empty( $matches ) ) {
			return array();
		}

		$internal_links = array();
		$home_host      = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );

		foreach ( $matches as $match ) {
			$href = trim( (string) ( $match[2] ?? '' ) );

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
				$internal_links[] = array(
					'path'   => $path,
					'anchor' => $this->normalize_anchor_text( (string) ( $match[3] ?? '' ) ),
				);
			}
		}

		return $internal_links;
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
	 * Group extracted internal links by target path.
	 *
	 * @since    1.1.0
	 * @param    array    $links    Extracted internal links.
	 * @return   array
	 */
	private function group_internal_links_by_target( $links ) {
		$grouped_links = array();

		foreach ( $links as $link ) {
			$target_path = $link['path'] ?? '';

			if ( empty( $target_path ) ) {
				continue;
			}

			if ( ! isset( $grouped_links[ $target_path ] ) ) {
				$grouped_links[ $target_path ] = array(
					'anchors' => array(),
				);
			}

			if ( ! empty( $link['anchor'] ) ) {
				$grouped_links[ $target_path ]['anchors'][] = $link['anchor'];
				$grouped_links[ $target_path ]['anchors']   = array_values( array_unique( $grouped_links[ $target_path ]['anchors'] ) );
			}
		}

		return $grouped_links;
	}

	/**
	 * Normalize anchor text into a plain lowercase phrase.
	 *
	 * @since    1.1.0
	 * @param    string    $anchor_html    Raw anchor HTML.
	 * @return   string
	 */
	private function normalize_anchor_text( $anchor_html ) {
		$anchor_text = html_entity_decode( preg_replace( '/<[^>]+>/', ' ', $anchor_html ), ENT_QUOTES, 'UTF-8' );
		$anchor_text = strtolower( trim( preg_replace( '/\s+/', ' ', $anchor_text ) ) );

		return $anchor_text;
	}

	/**
	 * Extract anchor-text quality issues from the collected anchor map.
	 *
	 * @since    1.1.0
	 * @param    array    $page_map      Indexed page map.
	 * @param    array    $anchor_map    Anchors keyed by target path.
	 * @return   array
	 */
	private function extract_anchor_text_issues( $page_map, $anchor_map ) {
		$generic_anchors = array(
			'click here',
			'read more',
			'learn more',
			'more',
			'here',
			'this page',
			'continue',
		);
		$issues          = array();

		foreach ( $anchor_map as $target_path => $anchors ) {
			$anchors = array_values( array_filter( array_unique( $anchors ) ) );

			if ( empty( $anchors ) || empty( $page_map[ $target_path ] ) ) {
				continue;
			}

			$generic_count = 0;

			foreach ( $anchors as $anchor ) {
				if ( strlen( $anchor ) < 4 || in_array( $anchor, $generic_anchors, true ) ) {
					++$generic_count;
				}
			}

			if ( count( $anchors ) !== $generic_count ) {
				continue;
			}

			$issues[] = array(
				'title'              => $page_map[ $target_path ]['title'],
				'url'                => $page_map[ $target_path ]['url'],
				'path'               => $target_path,
				'anchors'            => $anchors,
				'recommended_anchor' => $this->build_recommended_anchor( $page_map[ $target_path ]['title'] ),
			);
		}

		usort( $issues, array( $this, 'sort_pages_by_title' ) );

		return array_slice( $issues, 0, 10 );
	}

	/**
	 * Build semantic link suggestions for weakly linked targets.
	 *
	 * @since    1.1.0
	 * @param    array    $page_map             Indexed page map.
	 * @param    array    $existing_links       Existing links keyed by source and target path.
	 * @param    array    $content_tokens_map   Source content tokens keyed by path.
	 * @param    array    $title_tokens_map     Page title tokens keyed by path.
	 * @param    array    $content_phrases_map  Source phrase map keyed by path.
	 * @return   array
	 */
	private function extract_link_suggestions( $page_map, $existing_links, $content_tokens_map, $title_tokens_map, $content_phrases_map ) {
		$link_suggestions = array();

		foreach ( $page_map as $target_path => $page ) {
			if ( (int) $page['inbound'] > 1 ) {
				continue;
			}

			$target_tokens  = $title_tokens_map[ $target_path ] ?? array();
			$target_phrases = $this->extract_phrases( $page['title'] );

			if ( empty( $target_tokens ) ) {
				continue;
			}

			$suggestions = array();

			foreach ( $page_map as $source_path => $source_page ) {
				if ( $source_path === $target_path || ! empty( $existing_links[ $source_path ][ $target_path ] ) ) {
					continue;
				}

				$shared_terms   = array_values( array_intersect( $target_tokens, $content_tokens_map[ $source_path ] ?? array() ) );
				$shared_phrases = array_values( array_intersect( $target_phrases, $content_phrases_map[ $source_path ] ?? array() ) );
				$score          = ( count( $shared_terms ) * 2 ) + ( count( $shared_phrases ) * 3 );

				if ( 0 === $score ) {
					continue;
				}

				$suggestions[] = array(
					'source_title'       => $source_page['title'],
					'source_url'         => $source_page['url'],
					'source_path'        => $source_path,
					'score'              => $score,
					'matched_terms'      => array_slice( $shared_terms, 0, 3 ),
					'matched_phrases'    => array_slice( $shared_phrases, 0, 2 ),
					'recommended_anchor' => $this->build_recommended_anchor( $page['title'] ),
				);
			}

			if ( empty( $suggestions ) ) {
				continue;
			}

			usort(
				$suggestions,
				function ( $left, $right ) {
					if ( (int) $left['score'] === (int) $right['score'] ) {
						return strcmp( (string) $left['source_title'], (string) $right['source_title'] );
					}

					return (int) $right['score'] <=> (int) $left['score'];
				}
			);

			$link_suggestions[] = array(
				'target_title'       => $page['title'],
				'target_url'         => $page['url'],
				'target_path'        => $target_path,
				'recommended_anchor' => $this->build_recommended_anchor( $page['title'] ),
				'suggestions'        => array_slice( $suggestions, 0, 3 ),
			);
		}

		usort(
			$link_suggestions,
			function ( $left, $right ) {
				return strcmp( (string) $left['target_title'], (string) $right['target_title'] );
			}
		);

		return array_slice( $link_suggestions, 0, 10 );
	}

	/**
	 * Build simple hub-page topic clusters from page tokens.
	 *
	 * @since    1.1.0
	 * @param    array    $page_map       Indexed page map.
	 * @param    array    $page_topics    Topic tokens keyed by path.
	 * @return   array
	 */
	private function extract_topic_clusters( $page_map, $page_topics ) {
		$topic_map = array();

		foreach ( $page_topics as $path => $topics ) {
			foreach ( $topics as $topic ) {
				$topic_map[ $topic ][] = $path;
			}
		}

		$clusters = array();

		foreach ( $topic_map as $topic => $paths ) {
			$paths = array_values( array_unique( $paths ) );

			if ( count( $paths ) < 2 ) {
				continue;
			}

			$hub_path  = '';
			$hub_score = -1;

			foreach ( $paths as $path ) {
				$page  = $page_map[ $path ] ?? array();
				$score = (int) ( $page['inbound'] ?? 0 ) + (int) ( $page['outbound'] ?? 0 );

				if ( $score > $hub_score ) {
					$hub_score = $score;
					$hub_path  = $path;
				}
			}

			if ( empty( $hub_path ) || empty( $page_map[ $hub_path ] ) ) {
				continue;
			}

			$member_titles = array();

			foreach ( $paths as $path ) {
				$member_titles[] = $page_map[ $path ]['title'] ?? $path;
			}

			sort( $member_titles );

			$clusters[] = array(
				'topic'        => $topic,
				'hub_title'    => $page_map[ $hub_path ]['title'],
				'hub_url'      => $page_map[ $hub_path ]['url'],
				'member_count' => count( $paths ),
				'members'      => array_slice( $member_titles, 0, 5 ),
			);
		}

		usort(
			$clusters,
			function ( $left, $right ) {
				if ( (int) $left['member_count'] === (int) $right['member_count'] ) {
					return strcmp( (string) $left['topic'], (string) $right['topic'] );
				}

				return (int) $right['member_count'] <=> (int) $left['member_count'];
			}
		);

		return array_slice( $clusters, 0, 10 );
	}

	/**
	 * Tokenize text for simple content matching.
	 *
	 * @since    1.1.0
	 * @param    string    $text    Raw text.
	 * @return   array
	 */
	private function tokenize_text( $text ) {
		$normalized_text = strtolower( html_entity_decode( preg_replace( '/<[^>]+>/', ' ', $text ), ENT_QUOTES, 'UTF-8' ) );
		$tokens          = preg_split( '/[^a-z0-9]+/', $normalized_text );
		$stop_words      = array(
			'a',
			'an',
			'and',
			'the',
			'for',
			'with',
			'from',
			'that',
			'this',
			'into',
			'your',
			'about',
			'guide',
			'page',
			'post',
			'blog',
		);

		return array_values(
			array_filter(
				array_unique( $tokens ),
				function ( $token ) use ( $stop_words ) {
					return strlen( $token ) > 2 && ! in_array( $token, $stop_words, true );
				}
			)
		);
	}

	/**
	 * Extract reusable two-word phrases from text.
	 *
	 * @since    1.1.0
	 * @param    string    $text    Raw text.
	 * @return   array
	 */
	private function extract_phrases( $text ) {
		$tokens  = $this->tokenize_text( $text );
		$phrases = array();

		for ( $index = 0; $index < count( $tokens ) - 1; ++$index ) {
			$phrases[] = $tokens[ $index ] . ' ' . $tokens[ $index + 1 ];
		}

		return array_values( array_unique( $phrases ) );
	}

	/**
	 * Extract the strongest page topic terms.
	 *
	 * @since    1.1.0
	 * @param    array    $title_tokens      Title and slug tokens.
	 * @param    array    $content_tokens    Content tokens.
	 * @return   array
	 */
	private function extract_topic_terms( $title_tokens, $content_tokens ) {
		$topics = array_slice( $title_tokens, 0, 2 );

		if ( count( $topics ) < 2 ) {
			$topics = array_slice( array_values( array_unique( array_merge( $title_tokens, $content_tokens ) ) ), 0, 2 );
		}

		return array_values( array_unique( $topics ) );
	}

	/**
	 * Build a recommendation for better anchor text.
	 *
	 * @since    1.1.0
	 * @param    string    $title    Target page title.
	 * @return   string
	 */
	private function build_recommended_anchor( $title ) {
		return strtolower( trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $title ) ) ) );
	}

	/**
	 * Sort page rows alphabetically by title.
	 *
	 * @since    1.1.0
	 * @param    array    $left     Left page row.
	 * @param    array    $right    Right page row.
	 * @return   int
	 */
	private function sort_pages_by_title( $left, $right ) {
		return strcmp( (string) ( $left['title'] ?? '' ), (string) ( $right['title'] ?? '' ) );
	}

	/**
	 * Sort broken-link rows by source title then target path.
	 *
	 * @since    1.1.0
	 * @param    array    $left     Left broken-link row.
	 * @param    array    $right    Right broken-link row.
	 * @return   int
	 */
	private function sort_broken_links( $left, $right ) {
		$title_comparison = strcmp( (string) ( $left['source_title'] ?? '' ), (string) ( $right['source_title'] ?? '' ) );

		if ( 0 !== $title_comparison ) {
			return $title_comparison;
		}

		return strcmp( (string) ( $left['target_path'] ?? '' ), (string) ( $right['target_path'] ?? '' ) );
	}

	/**
	 * Get the site-scoped cache key.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	private function get_cache_key() {
		$blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;

		return 'internal_links_report_' . $blog_id;
	}
}
