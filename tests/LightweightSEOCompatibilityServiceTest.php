<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-compatibility-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOCompatibilityServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_filters;
		global $lightweight_seo_test_options;

		$lightweight_seo_test_filters = array();
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

	public function test_safe_mode_suppresses_only_overlapping_features(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options['active_plugins'] = array( 'wordpress-seo/wp-seo.php' );
		$service                                        = new Lightweight_SEO_Compatibility_Service();

		$this->assertFalse( $service->feature_output_allowed( 'title' ) );
		$this->assertFalse( $service->feature_output_allowed( 'schema' ) );
		$this->assertTrue( $service->feature_output_allowed( 'hreflang' ) );
		$this->assertSame( array( 'title', 'meta', 'robots', 'canonical', 'schema' ), $service->get_suppressed_features() );
	}

	public function test_compatibility_providers_and_feature_matrix_are_filterable(): void {
		global $lightweight_seo_test_filters;
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options['active_plugins']                        = array( 'custom-seo/custom.php' );
		$lightweight_seo_test_filters['lightweight_seo_compatibility_plugins'] = static function ( $plugins ) {
			$plugins['custom-seo/custom.php'] = 'Custom SEO';

			return $plugins;
		};
		$lightweight_seo_test_filters['lightweight_seo_suppressed_features']   = static function ( $features ) {
			return array( 'canonical' );
		};

		$service = new Lightweight_SEO_Compatibility_Service();

		$this->assertSame( array( 'Custom SEO' ), $service->get_conflicting_plugins() );
		$this->assertFalse( $service->feature_output_allowed( 'canonical' ) );
		$this->assertTrue( $service->feature_output_allowed( 'schema' ) );
	}
}
