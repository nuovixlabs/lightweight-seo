<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOAdminTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_nonce_is_valid;
		global $lightweight_seo_test_settings_errors;
		global $lightweight_seo_test_user_can;

		$lightweight_seo_test_nonce_is_valid  = true;
		$lightweight_seo_test_settings_errors = array();
		$lightweight_seo_test_user_can        = true;
	}

	public function test_validate_settings_preserves_existing_tracking_ids_when_invalid(): void {
		global $lightweight_seo_test_settings_errors;

		$settings = new class() {
			public function get_all() {
				return array(
					'title_format'                  => LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT,
					'home_title_format'             => '%sitename% %sep% %tagline%',
					'archive_title_format'          => '%title% %sep% %sitename%',
					'search_title_format'           => 'Search Results for "%search%" %sep% %sitename%',
					'meta_description'              => 'Existing description',
					'meta_keywords'                 => 'existing,keywords',
					'enable_meta_keywords'          => '1',
					'noindex_search_results'        => '1',
					'exclude_noindex_from_sitemaps' => '1',
					'enable_image_sitemaps'         => '1',
					'enable_schema_output'          => '1',
					'organization_same_as'          => "https://example.com/about\nhttps://example.com/x",
					'enable_404_monitor'            => '1',
					'enable_auto_redirects'         => '1',
					'redirect_rules'                => '/old-page /new-page 301',
					'default_max_image_preview'     => 'large',
					'social_image'                  => 'https://example.com/existing-image.jpg',
					'social_image_id'               => 14,
					'ga4_measurement_id'            => 'G-OLD123',
					'gtm_container_id'              => 'GTM-OLD123',
					'facebook_pixel_id'             => '12345',
				);
			}

			public function normalize_max_image_preview( $value, $fallback = '' ) {
				$allowed = array( 'none', 'standard', 'large' );

				return in_array( $value, $allowed, true ) ? $value : $fallback;
			}

			public function get_default_max_image_preview() {
				return 'large';
			}

			public function normalize_redirect_rules_input( $value ) {
				return "/old-path /new-path 301\n/legacy https://example.com/destination 302";
			}
		};

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}
		};

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.0.2', $settings, $post_meta );

		$validated = $admin->validate_settings(
			array(
				'title_format'              => '%title% – %sitename%',
				'home_title_format'         => '%sitename%',
				'archive_title_format'      => '%title% | %sitename%',
				'search_title_format'       => 'Find %search%',
				'meta_description'          => 'New description',
				'meta_keywords'             => 'new,keywords',
				'default_max_image_preview' => 'invalid',
				'organization_same_as'      => "https://example.com/linkedin\nnot-a-url\nhttps://example.com/linkedin",
				'redirect_rules'            => "/old-path /new-path 301\ninvalid rule",
				'social_image'              => 'https://example.com/new-image.jpg',
				'social_image_id'           => '27',
				'ga4_measurement_id'        => 'invalid-ga4',
				'gtm_container_id'          => 'gtm-new123',
				'facebook_pixel_id'         => 'abc123',
			)
		);

		$this->assertSame( 'G-OLD123', $validated['ga4_measurement_id'] );
		$this->assertSame( 'GTM-NEW123', $validated['gtm_container_id'] );
		$this->assertSame( '12345', $validated['facebook_pixel_id'] );
		$this->assertSame( '%sitename%', $validated['home_title_format'] );
		$this->assertSame( '%title% | %sitename%', $validated['archive_title_format'] );
		$this->assertSame( 'Find %search%', $validated['search_title_format'] );
		$this->assertSame( '0', $validated['enable_meta_keywords'] );
		$this->assertSame( '0', $validated['noindex_search_results'] );
		$this->assertSame( '0', $validated['exclude_noindex_from_sitemaps'] );
		$this->assertSame( '0', $validated['enable_image_sitemaps'] );
		$this->assertSame( '0', $validated['enable_schema_output'] );
		$this->assertSame( '0', $validated['enable_404_monitor'] );
		$this->assertSame( '0', $validated['enable_auto_redirects'] );
		$this->assertSame( 'https://example.com/linkedin', $validated['organization_same_as'] );
		$this->assertSame( "/old-path /new-path 301\n/legacy https://example.com/destination 302", $validated['redirect_rules'] );
		$this->assertSame( 'large', $validated['default_max_image_preview'] );
		$this->assertSame( 27, $validated['social_image_id'] );
		$this->assertCount( 2, $lightweight_seo_test_settings_errors );
	}
}
