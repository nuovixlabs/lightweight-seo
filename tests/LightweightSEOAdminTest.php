<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOAdminTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_attachment_urls;
		global $lightweight_seo_test_nonce_is_valid;
		global $lightweight_seo_test_rendered_settings_errors;
		global $lightweight_seo_test_settings_errors;
		global $lightweight_seo_test_user_can;

		$lightweight_seo_test_attachment_urls          = array();
		$lightweight_seo_test_nonce_is_valid           = true;
		$lightweight_seo_test_rendered_settings_errors = array();
		$lightweight_seo_test_settings_errors          = array();
		$lightweight_seo_test_user_can                 = true;
	}

	public function test_validate_settings_preserves_existing_tracking_ids_when_invalid(): void {
		global $lightweight_seo_test_attachment_urls;
		global $lightweight_seo_test_settings_errors;

		$lightweight_seo_test_attachment_urls[27] = 'https://example.com/new-image.jpg';

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

	public function test_validate_settings_clears_stale_social_image_id_for_manual_urls(): void {
		global $lightweight_seo_test_attachment_urls;

		$lightweight_seo_test_attachment_urls[14] = 'https://example.com/existing-image.jpg';

		$settings = new class() {
			public function get_all() {
				return array(
					'title_format'         => LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT,
					'meta_description'     => 'Existing description',
					'meta_keywords'        => 'existing,keywords',
					'enable_meta_keywords' => '1',
					'social_image'         => 'https://example.com/existing-image.jpg',
					'social_image_id'      => 14,
					'ga4_measurement_id'   => '',
					'gtm_container_id'     => '',
					'facebook_pixel_id'    => '',
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
				'social_image'    => 'https://cdn.example.com/manual-image.jpg',
				'social_image_id' => '14',
			)
		);

		$this->assertSame( 'https://cdn.example.com/manual-image.jpg', $validated['social_image'] );
		$this->assertSame( 0, $validated['social_image_id'] );
	}

	public function test_validate_settings_preserves_attachment_id_when_only_the_resolved_url_changes(): void {
		global $lightweight_seo_test_attachment_urls;

		$lightweight_seo_test_attachment_urls[14] = 'https://example.com/uploads/current-image.jpg';

		$settings = new class() {
			public function get_all() {
				return array(
					'title_format'         => LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT,
					'meta_description'     => 'Existing description',
					'meta_keywords'        => 'existing,keywords',
					'enable_meta_keywords' => '1',
					'social_image'         => 'https://example.com/uploads/old-image.jpg',
					'social_image_id'      => 14,
					'ga4_measurement_id'   => '',
					'gtm_container_id'     => '',
					'facebook_pixel_id'    => '',
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
				'social_image'    => 'https://example.com/uploads/current-image.jpg',
				'social_image_id' => '14',
			)
		);

		$this->assertSame( 'https://example.com/uploads/current-image.jpg', $validated['social_image'] );
		$this->assertSame( 14, $validated['social_image_id'] );
	}

	public function test_display_plugin_admin_page_renders_settings_errors(): void {
		global $lightweight_seo_test_rendered_settings_errors;

		$settings = new class() {
			public function get_all() {
				return array();
			}
		};

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}
		};

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.0.2', $settings, $post_meta );

		ob_start();
		$admin->display_plugin_admin_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'settings-errors', $output );
		$this->assertSame( array( LIGHTWEIGHT_SEO_OPTION_NAME ), $lightweight_seo_test_rendered_settings_errors );
	}
}
