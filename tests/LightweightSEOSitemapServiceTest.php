<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-sitemap-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-image-sitemap-provider.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-video-sitemap-provider.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-news-sitemap-provider.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-redirects-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOSitemapServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_registered_sitemap_providers;

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

	public function test_register_image_sitemap_provider_registers_attachment_provider(): void {
		global $lightweight_seo_test_registered_sitemap_providers;

		$service = $this->get_service();
		$service->register_image_sitemap_provider();

		$this->assertArrayHasKey( 'lightweightseoimages', $lightweight_seo_test_registered_sitemap_providers );
		$this->assertInstanceOf( Lightweight_SEO_Image_Sitemap_Provider::class, $lightweight_seo_test_registered_sitemap_providers['lightweightseoimages'] );
	}

	public function test_register_video_and_news_sitemap_providers_register_custom_modules(): void {
		global $lightweight_seo_test_registered_sitemap_providers;

		$service = $this->get_service();
		$service->register_video_sitemap_provider();
		$service->register_news_sitemap_provider();

		$this->assertArrayHasKey( 'lightweightseovideos', $lightweight_seo_test_registered_sitemap_providers );
		$this->assertArrayHasKey( 'lightweightseonews', $lightweight_seo_test_registered_sitemap_providers );
		$this->assertInstanceOf( Lightweight_SEO_Video_Sitemap_Provider::class, $lightweight_seo_test_registered_sitemap_providers['lightweightseovideos'] );
		$this->assertInstanceOf( Lightweight_SEO_News_Sitemap_Provider::class, $lightweight_seo_test_registered_sitemap_providers['lightweightseonews'] );
	}

	public function test_filter_posts_query_args_excludes_redirected_posts(): void {
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
	}

	private function get_service() {
		$settings = new class() {
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
