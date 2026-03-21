<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOSettingsTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_attachment_urls;
		global $lightweight_seo_test_options;

		$lightweight_seo_test_attachment_urls = array();
		$lightweight_seo_test_options         = array();
	}

	public function test_get_social_image_url_prefers_manual_url_when_attachment_id_is_stale(): void {
		global $lightweight_seo_test_attachment_urls;
		global $lightweight_seo_test_options;

		$lightweight_seo_test_attachment_urls[14]                    = 'https://example.com/uploads/old-image.jpg';
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'social_image'    => 'https://cdn.example.com/manual-image.jpg',
			'social_image_id' => 14,
		);

		$settings = new Lightweight_SEO_Settings();

		$this->assertSame( 'https://cdn.example.com/manual-image.jpg', $settings->get_social_image_url() );
	}
}
