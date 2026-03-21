<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-compatibility-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOCompatibilityServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options = array();
	}

	public function test_get_conflicting_plugins_detects_known_active_seo_plugins(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options['active_plugins'] = array(
			'wordpress-seo/wp-seo.php',
			'seo-by-rank-math/rank-math.php',
		);

		$service   = new Lightweight_SEO_Compatibility_Service();
		$conflicts = $service->get_conflicting_plugins();

		$this->assertSame( array( 'Yoast SEO', 'Rank Math SEO' ), $conflicts );
		$this->assertFalse( $service->frontend_head_output_allowed() );
	}

	public function test_frontend_head_output_allowed_when_no_known_conflicts_exist(): void {
		$service = new Lightweight_SEO_Compatibility_Service();

		$this->assertSame( array(), $service->get_conflicting_plugins() );
		$this->assertTrue( $service->frontend_head_output_allowed() );
	}
}
