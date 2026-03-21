<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-image-sitemap-provider.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOImageSitemapProviderTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_posts = array(
			101 => (object) array(
				'ID'                => 101,
				'post_type'         => 'attachment',
				'post_status'       => 'inherit',
				'post_mime_type'    => 'image/jpeg',
				'post_modified_gmt' => '2024-01-05 12:00:00',
				'attachment_url'    => 'https://example.com/uploads/image-one.jpg',
			),
			102 => (object) array(
				'ID'                => 102,
				'post_type'         => 'attachment',
				'post_status'       => 'inherit',
				'post_mime_type'    => 'image/png',
				'post_modified_gmt' => '2024-01-06 12:00:00',
				'attachment_url'    => 'https://example.com/uploads/image-two.png',
			),
			103 => (object) array(
				'ID'                => 103,
				'post_type'         => 'attachment',
				'post_status'       => 'inherit',
				'post_mime_type'    => 'application/pdf',
				'post_modified_gmt' => '2024-01-07 12:00:00',
				'attachment_url'    => 'https://example.com/uploads/file.pdf',
			),
		);
	}

	public function test_provider_returns_only_image_attachment_urls(): void {
		$settings = new class() {
			public function image_sitemaps_enabled() {
				return true;
			}
		};

		$provider = new Lightweight_SEO_Image_Sitemap_Provider( $settings );
		$urls     = $provider->get_url_list( 1 );

		$this->assertCount( 2, $urls );
		$this->assertSame( 'https://example.com/uploads/image-one.jpg', $urls[0]['loc'] );
		$this->assertSame( 'https://example.com/uploads/image-two.png', $urls[1]['loc'] );
		$this->assertArrayHasKey( 'lastmod', $urls[0] );
		$this->assertSame( 1, $provider->get_max_num_pages() );
	}
}
