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
}
