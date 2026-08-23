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

	public function test_new_install_defaults_do_not_use_the_site_tagline_as_description(): void {
		$settings = new Lightweight_SEO_Settings();

		$this->assertSame( '', $settings->get_defaults()['meta_description'] );
		$this->assertSame( '0', $settings->get_defaults()['enable_product_schema'] );
		$this->assertSame( '0', $settings->get_defaults()['enable_image_sitemaps'] );
	}

	public function test_redirect_normalization_rejects_loops_and_caps_storage(): void {
		$settings = new Lightweight_SEO_Settings();
		$lines    = array(
			'/loop-a /loop-b 301',
			'/loop-b /loop-a 302',
			'/valid /destination 308',
			'/with-query?utm_source=test /clean-target?campaign=ignored 301',
			'/unsafe https://unapproved.example/destination 301',
		);

		for ( $index = 0; $index < 510; $index++ ) {
			$lines[] = '/source-' . $index . ' /target-' . $index . ' 301';
		}

		$normalized = $settings->normalize_redirect_rules_input( implode( "\n", $lines ) );
		$rules      = explode( "\n", $normalized );

		$this->assertCount( Lightweight_SEO_Settings::MAX_MANUAL_REDIRECTS, $rules );
		$this->assertStringNotContainsString( '/loop-a', $normalized );
		$this->assertStringNotContainsString( '/loop-b', $normalized );
		$this->assertStringNotContainsString( '/unsafe', $normalized );
		$this->assertSame( '/valid /destination 308', $rules[0] );
		$this->assertSame( '/with-query /clean-target 301', $rules[1] );
	}

	public function test_hreflang_normalization_validates_tags_and_duplicate_targets(): void {
		$settings   = new Lightweight_SEO_Settings();
		$normalized = $settings->normalize_hreflang_mappings_input(
			implode(
				"\n",
				array(
					'post:42 EN-gb https://example.co.uk/page/',
					'post:42 en-GB https://duplicate.example/page/',
					'post:42 fr-FR https://example.co.uk/page/',
					'post:42 invalid_tag https://invalid.example/page/',
					'post:42 x-default https://example.com/page/',
				)
			)
		);

		$this->assertSame( "post:42 en-GB https://example.co.uk/page/\npost:42 x-default https://example.com/page/", $normalized );
	}
}
