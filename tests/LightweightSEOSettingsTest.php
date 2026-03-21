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

	public function test_get_social_image_url_returns_current_attachment_url_when_the_attachment_remains_valid(): void {
		global $lightweight_seo_test_attachment_urls;
		global $lightweight_seo_test_options;

		$lightweight_seo_test_attachment_urls[14]                    = 'https://example.com/uploads/current-image.jpg';
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'social_image'    => 'https://example.com/uploads/old-image.jpg',
			'social_image_id' => 14,
		);

		$settings = new Lightweight_SEO_Settings();

		$this->assertSame( 'https://example.com/uploads/current-image.jpg', $settings->get_social_image_url() );
	}
}
