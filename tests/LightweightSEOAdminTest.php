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
					'title_format'         => LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT,
					'meta_description'     => 'Existing description',
					'meta_keywords'        => 'existing,keywords',
					'enable_meta_keywords' => '1',
					'social_image'         => 'https://example.com/existing-image.jpg',
					'social_image_id'      => 14,
					'ga4_measurement_id'   => 'G-OLD123',
					'gtm_container_id'     => 'GTM-OLD123',
					'facebook_pixel_id'    => '12345',
				);
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
				'title_format'       => '%title% – %sitename%',
				'meta_description'   => 'New description',
				'meta_keywords'      => 'new,keywords',
				'social_image'       => 'https://example.com/new-image.jpg',
				'social_image_id'    => '27',
				'ga4_measurement_id' => 'invalid-ga4',
				'gtm_container_id'   => 'gtm-new123',
				'facebook_pixel_id'  => 'abc123',
			)
		);

		$this->assertSame( 'G-OLD123', $validated['ga4_measurement_id'] );
		$this->assertSame( 'GTM-NEW123', $validated['gtm_container_id'] );
		$this->assertSame( '12345', $validated['facebook_pixel_id'] );
		$this->assertSame( '0', $validated['enable_meta_keywords'] );
		$this->assertSame( 27, $validated['social_image_id'] );
		$this->assertCount( 2, $lightweight_seo_test_settings_errors );
	}
}
