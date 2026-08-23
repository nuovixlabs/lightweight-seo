<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-data-registry.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-migrator.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-lifecycle.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOLifecycleTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_cleared_scheduled_events;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_scheduled_events;

		$lightweight_seo_test_cleared_scheduled_events = array();
		$lightweight_seo_test_options                  = array();
		$lightweight_seo_test_scheduled_events         = array();
	}

	public function test_migration_initializes_new_install_once(): void {
		global $lightweight_seo_test_options;

		Lightweight_SEO_Migrator::maybe_migrate();

		$this->assertSame( 3, $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION ] );
		$this->assertSame( '0', $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]['delete_data_on_uninstall'] );
		$this->assertSame(
			array(
				'redirects' => false,
				'hreflang'  => false,
				'tracking'  => false,
				'local-seo' => false,
				'ai'        => false,
			),
			$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ]
		);

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]['title_format'] = 'Custom';
		Lightweight_SEO_Migrator::maybe_migrate();

		$this->assertSame( 'Custom', $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]['title_format'] );
	}

	public function test_migration_preserves_existing_103_settings(): void {
		global $lightweight_seo_test_options;

		$existing = array(
			'title_format'       => 'Legacy title',
			'ga4_measurement_id' => 'G-EXISTING1',
		);

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = $existing;
		Lightweight_SEO_Migrator::maybe_migrate();

		$this->assertSame( $existing, $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] );
		$this->assertSame( 3, $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION ] );
		$this->assertTrue( $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ]['tracking'] );
		$this->assertFalse( $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ]['redirects'] );
	}

	public function test_version_3_cleanup_preserves_metadata_and_credentials_but_retires_runtime_data(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_scheduled_events;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]                  = array(
			'title_format'                        => 'Legacy title',
			'meta_keywords'                       => 'preserve,export',
			'enable_meta_keywords'                => '1',
			'enable_image_sitemaps'               => '1',
			'enable_404_monitor'                  => '1',
			'search_console_property'             => 'sc-domain:example.com',
			'search_console_service_account_json' => '{"private_key":"retain-for-decision"}',
		);
		$lightweight_seo_test_options['lightweight_seo_internal_links_report']        = array( 'retired' );
		$lightweight_seo_test_options['lightweight_seo_search_console_token']         = array( 'private' );
		$lightweight_seo_test_options['lightweight_seo_404_logs']                     = array( '/one', '/two' );
		$lightweight_seo_test_scheduled_events['lightweight_seo_search_console_sync'] = 123;

		Lightweight_SEO_Migrator::maybe_migrate();

		$settings = $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ];
		$summary  = $lightweight_seo_test_options['lightweight_seo_upgrade_summary'];

		$this->assertSame( 'Legacy title', $settings['title_format'] );
		$this->assertSame( 'preserve,export', $settings['meta_keywords'] );
		$this->assertArrayNotHasKey( 'enable_meta_keywords', $settings );
		$this->assertArrayNotHasKey( 'enable_image_sitemaps', $settings );
		$this->assertSame( 'sc-domain:example.com', $settings['search_console_property'] );
		$this->assertArrayNotHasKey( 'lightweight_seo_internal_links_report', $lightweight_seo_test_options );
		$this->assertArrayNotHasKey( 'lightweight_seo_search_console_token', $lightweight_seo_test_options );
		$this->assertSame( array( 'image' ), $summary['removed_sitemaps'] );
		$this->assertSame( 2, $summary['legacy_404_entries'] );
		$this->assertArrayNotHasKey( 'lightweight_seo_search_console_sync', $lightweight_seo_test_scheduled_events );
	}

	public function test_deactivation_clears_every_owned_schedule(): void {
		global $lightweight_seo_test_cleared_scheduled_events;
		global $lightweight_seo_test_scheduled_events;

		$lightweight_seo_test_scheduled_events['lightweight_seo_search_console_sync'] = 123;

		Lightweight_SEO_Lifecycle::deactivate();

		$this->assertSame( Lightweight_SEO_Data_Registry::get_cron_hooks(), $lightweight_seo_test_cleared_scheduled_events );
		$this->assertArrayNotHasKey( 'lightweight_seo_search_console_sync', $lightweight_seo_test_scheduled_events );
	}

	public function test_registry_includes_all_current_object_meta_types(): void {
		$meta_keys = Lightweight_SEO_Data_Registry::get_object_meta_keys();

		$this->assertContains( '_lightweight_seo_canonical_url', $meta_keys );
		$this->assertContains( '_lightweight_seo_social_image_id', $meta_keys );
		$this->assertContains( '_lightweight_seo_keywords', $meta_keys );
	}
}
