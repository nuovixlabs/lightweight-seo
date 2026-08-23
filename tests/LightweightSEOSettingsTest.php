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

	public function test_local_business_normalization_accepts_a_complete_valid_location(): void {
		$settings   = new Lightweight_SEO_Settings();
		$normalized = $settings->normalize_local_business_input(
			array(
				'enable_local_business_schema'       => '1',
				'local_business_type'                => 'Restaurant',
				'local_business_name'                => 'Example Cafe',
				'local_business_phone'               => '+1 (555) 555-5555',
				'local_business_price_range'         => '$$',
				'local_business_address_street'      => '123 Main St',
				'local_business_address_locality'    => 'San Diego',
				'local_business_address_region'      => 'CA',
				'local_business_address_postal_code' => '92101',
				'local_business_address_country'     => 'us',
				'local_business_latitude'            => '32.7157',
				'local_business_longitude'           => '-117.1611',
				'local_business_opening_hours'       => "Mo-Fr 09:00-17:00\nSa 10:00-14:00",
				'local_business_image'               => 'https://example.com/business.jpg',
			)
		);

		$this->assertSame( array(), $normalized['errors'] );
		$this->assertTrue( $normalized['data']['valid'] );
		$this->assertSame( 'US', $normalized['data']['country'] );
		$this->assertSame( array( 'Mo-Fr 09:00-17:00', 'Sa 10:00-14:00' ), $normalized['data']['opening_hours'] );
	}

	public function test_local_business_normalization_rejects_invalid_or_incomplete_data(): void {
		$settings   = new Lightweight_SEO_Settings();
		$normalized = $settings->normalize_local_business_input(
			array(
				'enable_local_business_schema'   => '1',
				'local_business_name'            => 'Example Cafe',
				'local_business_phone'           => 'call-us',
				'local_business_address_street'  => '123 Main St',
				'local_business_address_country' => 'United States',
				'local_business_latitude'        => '91',
				'local_business_longitude'       => '-181',
				'local_business_opening_hours'   => 'weekdays',
			)
		);

		$this->assertFalse( $normalized['data']['valid'] );
		$this->assertNotEmpty( $normalized['errors'] );
		$this->assertSame( '', $normalized['data']['telephone'] );
		$this->assertSame( '', $normalized['data']['country'] );
		$this->assertSame( '', $normalized['data']['latitude'] );
		$this->assertSame( array(), $normalized['data']['opening_hours'] );
	}

	public function test_llms_selection_is_deduplicated_and_bounded(): void {
		$settings = new Lightweight_SEO_Settings();
		$ids      = range( 1, 60 );
		$ids[]    = 1;
		$ids[]    = 2;

		$normalized = $settings->normalize_llms_post_ids( implode( ', ', $ids ) );

		$this->assertCount( Lightweight_SEO_Settings::MAX_LLMS_POSTS, explode( ',', $normalized ) );
		$this->assertSame( '1', explode( ',', $normalized )[0] );
		$this->assertSame( '50', explode( ',', $normalized )[49] );
	}
}
