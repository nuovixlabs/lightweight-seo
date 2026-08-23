<?php

final class LightweightSEOActivationTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		delete_option( LIGHTWEIGHT_SEO_OPTION_NAME );
		delete_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME );
		delete_option( LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION );
		wp_clear_scheduled_hook( 'lightweight_seo_search_console_sync' );
	}

	public function test_activation_initializes_schema_without_frontend_output(): void {
		Lightweight_SEO_Lifecycle::activate();

		$this->assertIsArray( get_option( LIGHTWEIGHT_SEO_OPTION_NAME ) );
		$this->assertSame( 2, get_option( LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION ) );
		$this->assertSame(
			array(
				'redirects' => false,
				'hreflang'  => false,
				'tracking'  => false,
				'local-seo' => false,
				'ai'        => false,
			),
			get_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME )
		);
	}

	public function test_activation_preserves_representative_103_settings(): void {
		$legacy_settings = array(
			'title_format'                        => 'Legacy %title%',
			'ga4_measurement_id'                  => 'G-EXISTING1',
			'enable_hreflang_output'              => '1',
			'enable_local_business_schema'        => '1',
			'search_console_service_account_json' => '{"private_key":"preserve-until-cleanup"}',
		);

		update_option( LIGHTWEIGHT_SEO_OPTION_NAME, $legacy_settings );

		Lightweight_SEO_Lifecycle::activate();

		$this->assertSame( $legacy_settings, get_option( LIGHTWEIGHT_SEO_OPTION_NAME ) );
		$this->assertSame( 2, get_option( LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION ) );
		$this->assertTrue( get_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME )['tracking'] );
	}

	public function test_deactivation_unschedules_plugin_events(): void {
		wp_schedule_event( time() + 60, 'daily', 'lightweight_seo_search_console_sync' );

		Lightweight_SEO_Lifecycle::deactivate();

		$this->assertFalse( wp_next_scheduled( 'lightweight_seo_search_console_sync' ) );
	}

	public function test_network_activation_migrates_each_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite-only lifecycle scenario.' );
		}

		$second_site_id = self::factory()->blog->create();
		delete_blog_option( $second_site_id, LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION );

		Lightweight_SEO_Lifecycle::activate( true );

		$this->assertSame( 2, get_blog_option( $second_site_id, LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION ) );
	}
}
