<?php

final class LightweightSEOPublicAPIIntegrationTest extends WP_UnitTestCase {

	public function test_disabled_modules_load_no_implementation_classes_or_hooks(): void {
		$this->assertFalse( class_exists( 'Lightweight_SEO_Redirects_Service', false ) );
		$this->assertFalse( class_exists( 'Lightweight_SEO_Hreflang_Service', false ) );
		$this->assertFalse( class_exists( 'Lightweight_SEO_Tracking_Service', false ) );
		$this->assertFalse( class_exists( 'Lightweight_SEO_Local_SEO_Module', false ) );
		$this->assertFalse( has_action( 'template_redirect', array( 'Lightweight_SEO_Redirects_Service', 'maybe_redirect_request' ) ) );
	}

	public function test_insights_style_fixture_consumes_only_the_public_facade(): void {
		$api = lightweight_seo_get_api();

		$this->assertInstanceOf( Lightweight_SEO_API::class, $api );
		$this->assertTrue( $api->is_compatible( '1.0.3', '1.0' ) );
		$this->assertArrayHasKey( 'post', $api->get_supported_object_types() );
		$this->assertArrayHasKey( 'redirects', $api->get_modules() );
		$this->assertArrayNotHasKey( 'factory', $api->get_modules()['redirects'] );
		$this->assertSame( array( home_url( '/wp-sitemap.xml' ) ), $api->get_sitemap_urls() );
	}

	public function test_redirect_module_resolves_chains_and_has_no_404_writer(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-lightweight-seo-redirects-service.php';

		$settings = new Lightweight_SEO_Settings();
		$rules    = $settings->normalize_redirect_rules_input(
			"/old /middle 301\n/middle /final 302\n/external https://unapproved.example/page/ 301"
		);
		update_option( LIGHTWEIGHT_SEO_OPTION_NAME, array( 'redirect_rules' => $rules ) );
		$service = new Lightweight_SEO_Redirects_Service( new Lightweight_SEO_Settings(), false );

		$this->assertSame( '/final', $service->find_matching_redirect( '/old/' )['target'] );
		$this->assertSame( array(), $service->find_matching_redirect( '/external' ) );
		$this->assertFalse( method_exists( $service, 'log_404_request' ) );
	}

	public function test_hreflang_module_uses_valid_explicit_object_mappings(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-lightweight-seo-hreflang-service.php';

		$settings = new Lightweight_SEO_Settings();
		$mappings = $settings->normalize_hreflang_mappings_input(
			"post:42 en-GB https://example.co.uk/page/\npost:42 invalid_tag https://invalid.example/page/\npost:42 x-default https://example.com/page/"
		);
		update_option(
			LIGHTWEIGHT_SEO_OPTION_NAME,
			array(
				'enable_hreflang_output' => '1',
				'hreflang_mappings'      => $mappings,
			)
		);
		$context = new class() {
			public function get_context() {
				return array(
					'object_type'   => 'post',
					'object_id'     => 42,
					'canonical_url' => 'https://example.org/page/',
				);
			}
		};
		$links   = ( new Lightweight_SEO_Hreflang_Service( new Lightweight_SEO_Settings(), $context ) )->get_hreflang_links();

		$this->assertCount( 3, $links );
		$this->assertSame( 'en-GB', $links[1]['hreflang'] );
		$this->assertSame( 'x-default', $links[2]['hreflang'] );
	}
}
