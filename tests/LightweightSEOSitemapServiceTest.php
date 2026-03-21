<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-sitemap-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-image-sitemap-provider.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOSitemapServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_registered_sitemap_providers;

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

	private function get_service() {
		$settings = new class() {
			public function exclude_noindex_from_sitemaps_enabled() {
				return true;
			}

			public function image_sitemaps_enabled() {
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
