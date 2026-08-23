<?php

final class LightweightSEOCompatibilityIntegrationTest extends WP_UnitTestCase {

	private $active_plugins;
	private $network_plugins;

	protected function setUp(): void {
		parent::setUp();
		$this->active_plugins  = get_option( 'active_plugins', array() );
		$this->network_plugins = is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();
	}

	protected function tearDown(): void {
		update_option( 'active_plugins', $this->active_plugins );

		if ( is_multisite() ) {
			update_site_option( 'active_sitewide_plugins', $this->network_plugins );
		}

		parent::tearDown();
	}

	public function test_known_locally_active_seo_plugins_enable_feature_safe_mode(): void {
		update_option(
			'active_plugins',
			array(
				'wordpress-seo/wp-seo.php',
				'seo-by-rank-math/rank-math.php',
				'all-in-one-seo-pack/all_in_one_seo_pack.php',
			)
		);
		$service = new Lightweight_SEO_Compatibility_Service();

		$this->assertSame( array( 'Yoast SEO', 'Rank Math SEO', 'All in One SEO' ), $service->get_conflicting_plugins() );
		$this->assertFalse( $service->feature_output_allowed( 'canonical' ) );
		$this->assertTrue( $service->feature_output_allowed( 'hreflang' ) );
	}

	public function test_network_active_seo_plugin_enables_feature_safe_mode(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite-only compatibility case.' );
		}

		update_option( 'active_plugins', array() );
		update_site_option( 'active_sitewide_plugins', array( 'wordpress-seo/wp-seo.php' => time() ) );
		$service = new Lightweight_SEO_Compatibility_Service();

		$this->assertSame( array( 'Yoast SEO' ), $service->get_conflicting_plugins() );
		$this->assertFalse( $service->frontend_head_output_allowed() );
	}
}
