<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-sitemap-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-redirects-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOSitemapServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_get_posts_calls;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_registered_sitemap_providers;

		$lightweight_seo_test_get_posts_calls              = 0;
		$lightweight_seo_test_options                      = array();
		$lightweight_seo_test_posts                        = array();
		$lightweight_seo_test_registered_sitemap_providers = array();
	}

	public function test_filter_posts_query_args_excludes_noindexed_posts(): void {
		$service = $this->get_service();
		$args    = $service->filter_posts_query_args(
			array(
				'post_type' => 'post',
			),
			'post'
		);

		$this->assertArrayHasKey( 'meta_query', $args );
		$this->assertSame( '_lightweight_seo_noindex', $args['meta_query'][0][0]['key'] );
		$this->assertSame( 'NOT EXISTS', $args['meta_query'][0][0]['compare'] );
		$this->assertSame( '!=', $args['meta_query'][0][1]['compare'] );
		$this->assertSame( '1', $args['meta_query'][0][1]['value'] );
	}

	public function test_filter_taxonomies_query_args_excludes_noindexed_terms(): void {
		$service = $this->get_service();
		$args    = $service->filter_taxonomies_query_args(
			array(
				'taxonomy' => 'category',
			),
			'category'
		);

		$this->assertSame( '_lightweight_seo_noindex', $args['meta_query'][0][0]['key'] );
		$this->assertSame( 'NOT EXISTS', $args['meta_query'][0][0]['compare'] );
	}

	public function test_filter_users_query_args_excludes_noindexed_authors(): void {
		$service = $this->get_service();
		$args    = $service->filter_users_query_args(
			array(
				'who' => 'authors',
			)
		);

		$this->assertSame( '_lightweight_seo_noindex', $args['meta_query'][0][0]['key'] );
		$this->assertSame( '!=', $args['meta_query'][0][1]['compare'] );
	}

	public function test_specialized_sitemap_providers_are_not_registered_by_core(): void {
		global $lightweight_seo_test_registered_sitemap_providers;

		$this->get_service();

		$this->assertSame( array(), $lightweight_seo_test_registered_sitemap_providers );
	}

	public function test_filter_posts_query_args_excludes_redirected_posts(): void {
		global $lightweight_seo_test_get_posts_calls;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_posts = array(
			21 => (object) array(
				'ID'          => 21,
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Old URL',
				'permalink'   => 'https://example.com/old-url/',
			),
			22 => (object) array(
				'ID'          => 22,
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Fresh URL',
				'permalink'   => 'https://example.com/fresh-url/',
			),
		);

		$service = $this->get_service();
		$args    = $service->filter_posts_query_args(
			array(
				'post_type' => 'post',
			),
			'post'
		);

		$this->assertSame( array( 21 ), $args['post__not_in'] );
		$this->assertSame( 0, $lightweight_seo_test_get_posts_calls );
	}

	public function test_password_protected_posts_are_excluded_with_noindexed_content(): void {
		$args = $this->get_service()->filter_posts_query_args( array( 'post_type' => 'post' ), 'post' );

		$this->assertFalse( $args['has_password'] );
	}

	public function test_redirected_posts_are_resolved_once_and_scoped_to_their_post_type(): void {
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_posts[21] = (object) array(
			'ID'          => 21,
			'post_type'   => 'post',
			'post_status' => 'publish',
			'permalink'   => 'https://example.com/old-url/',
		);

		$service                    = $this->get_service();
		$post_args                  = $service->filter_posts_query_args( array(), 'post' );
		$lightweight_seo_test_posts = array();
		$page_args                  = $service->filter_posts_query_args( array(), 'page' );
		$post_again                 = $service->filter_posts_query_args( array(), 'post' );

		$this->assertSame( array( 21 ), $post_args['post__not_in'] );
		$this->assertArrayNotHasKey( 'post__not_in', $page_args );
		$this->assertSame( array( 21 ), $post_again['post__not_in'], 'The second lookup must reuse the per-request result.' );
	}

	public function test_content_types_left_out_of_the_sitemap_are_removed_from_the_index(): void {
		$service = $this->get_service(
			array(
				'content_types' => array(
					'post_types' => array( 'portfolio' => array( 'sitemap' => '0' ) ),
					'taxonomies' => array( 'post_tag' => array( 'sitemap' => '0' ) ),
				),
			)
		);

		$post_types = $service->filter_post_types(
			array(
				'post'      => (object) array( 'name' => 'post' ),
				'portfolio' => (object) array( 'name' => 'portfolio' ),
			)
		);
		$taxonomies = $service->filter_taxonomies(
			array(
				'category' => (object) array( 'name' => 'category' ),
				'post_tag' => (object) array( 'name' => 'post_tag' ),
			)
		);

		$this->assertSame( array( 'post' ), array_keys( $post_types ) );
		$this->assertSame( array( 'category' ), array_keys( $taxonomies ) );

		$untouched = $this->get_service();

		$this->assertCount( 2, $untouched->filter_post_types( array_fill_keys( array( 'post', 'portfolio' ), (object) array() ) ) );
		$this->assertCount( 2, $untouched->filter_taxonomies( array_fill_keys( array( 'category', 'post_tag' ), (object) array() ) ) );
	}

	public function test_a_hidden_content_type_lists_only_the_items_set_to_show(): void {
		$service    = $this->get_service(
			array(
				'noindex_author_archives' => '1',
				'content_types'           => array(
					'post_types' => array( 'portfolio' => array( 'noindex' => '1' ) ),
					'taxonomies' => array( 'post_tag' => array( 'noindex' => '1' ) ),
				),
			)
		);
		$only_shown = array(
			array(
				'key'   => '_lightweight_seo_noindex',
				'value' => 'index',
			),
		);

		$this->assertSame( array( $only_shown ), $service->filter_posts_query_args( array(), 'portfolio' )['meta_query'] );
		$this->assertSame( array( $only_shown ), $service->filter_taxonomies_query_args( array(), 'post_tag' )['meta_query'] );
		$this->assertSame( array( $only_shown ), $service->filter_users_query_args( array() )['meta_query'] );

		// A content type that is shown keeps the original "everything except hidden items" query.
		$this->assertSame( 'NOT EXISTS', $service->filter_posts_query_args( array(), 'post' )['meta_query'][0][0]['compare'] );
		$this->assertSame( 'NOT EXISTS', $service->filter_taxonomies_query_args( array(), 'category' )['meta_query'][0][0]['compare'] );
	}

	public function test_a_hidden_content_type_keeps_an_existing_meta_query(): void {
		$service = $this->get_service( array( 'content_types' => array( 'post_types' => array( 'portfolio' => array( 'noindex' => '1' ) ) ) ) );
		$args    = $service->filter_posts_query_args( array( 'meta_query' => array( array( 'key' => 'featured' ) ) ), 'portfolio' );

		$this->assertSame( 'AND', $args['meta_query']['relation'] );
		$this->assertSame( 'featured', $args['meta_query'][0][0]['key'] );
		$this->assertSame( 'index', $args['meta_query'][1][0]['value'] );
	}

	private function get_service( array $overrides = array() ) {
		$settings = new class( $overrides ) extends Lightweight_SEO_Settings {
			private $overrides;

			public function __construct( array $overrides ) {
				$this->overrides = $overrides;
			}

			public function get_all() {
				return array_merge( $this->get_defaults(), $this->overrides );
			}

			public function exclude_noindex_from_sitemaps_enabled() {
				return true;
			}

			public function exclude_redirected_from_sitemaps_enabled() {
				return true;
			}

			public function image_sitemaps_enabled() {
				return true;
			}

			public function video_sitemaps_enabled() {
				return true;
			}

			public function news_sitemaps_enabled() {
				return true;
			}

			public function get_manual_redirect_rules() {
				return array(
					array(
						'source' => '/old-url',
						'target' => '/fresh-url',
						'status' => 301,
					),
				);
			}

			public function not_found_monitor_enabled() {
				return true;
			}

			public function auto_redirects_enabled() {
				return true;
			}
		};

		$post_meta = new class() {
			public function get_meta_key( $field ) {
				return '_lightweight_seo_noindex';
			}
		};

		$archive_meta = new class() {
			public function get_meta_key( $field ) {
				return '_lightweight_seo_noindex';
			}
		};

		return new Lightweight_SEO_Sitemap_Service( $settings, $post_meta, $archive_meta );
	}
}
