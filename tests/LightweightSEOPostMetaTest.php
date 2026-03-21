<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-post-meta.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOPostMetaTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_attachment_urls;
		global $lightweight_seo_test_post_meta;

		$lightweight_seo_test_attachment_urls = array();
		$lightweight_seo_test_post_meta       = array();
	}

	public function test_get_social_image_url_prefers_manual_url_when_attachment_id_is_stale(): void {
		global $lightweight_seo_test_attachment_urls;

		$lightweight_seo_test_attachment_urls[42] = 'https://example.com/uploads/old-image.jpg';

		$post_meta = new class() extends Lightweight_SEO_Post_Meta {
			public function __construct() {}

			public function get_all( $post_id ) {
				return array(
					'social_image'    => 'https://cdn.example.com/manual-image.jpg',
					'social_image_id' => 42,
				);
			}
		};

		$this->assertSame( 'https://cdn.example.com/manual-image.jpg', $post_meta->get_social_image_url( 99 ) );
	}

	public function test_maybe_clear_stale_social_image_id_when_url_changes_independently(): void {
		global $lightweight_seo_test_attachment_urls;
		global $lightweight_seo_test_post_meta;

		$lightweight_seo_test_attachment_urls[42] = 'https://example.com/uploads/old-image.jpg';
		$lightweight_seo_test_post_meta[99]       = array(
			'_lightweight_seo_social_image_id' => 42,
		);

		$post_meta = new Lightweight_SEO_Post_Meta();
		$post_meta->maybe_clear_stale_social_image_id( 1, 99, '_lightweight_seo_social_image', 'https://cdn.example.com/manual-image.jpg' );

		$this->assertSame( 0, $lightweight_seo_test_post_meta[99]['_lightweight_seo_social_image_id'] );
	}
}
