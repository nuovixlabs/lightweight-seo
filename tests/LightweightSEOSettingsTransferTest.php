<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-module-state.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-data-registry.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings-transfer.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOSettingsTransferTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_home_url;
		global $lightweight_seo_test_option_autoload;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_settings_errors;

		$lightweight_seo_test_home_url        = 'https://example.com';
		$lightweight_seo_test_option_autoload = array();
		$lightweight_seo_test_options         = array();
		$lightweight_seo_test_settings_errors = array();
	}

	public function test_export_holds_the_settings_and_modules_but_nothing_that_must_stay_on_the_site(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]         = array(
			'title_format'                        => '%title% | %sitename%',
			'enable_redirects'                    => '1',
			'meta_keywords'                       => 'legacy,keywords',
			'search_console_property'             => 'sc-domain:example.com',
			'search_console_service_account_json' => '{"private_key":"secret"}',
			'import_source'                       => 'yoast',
		);
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ] = array( 'redirects' => true );

		$transfer = new Lightweight_SEO_Settings_Transfer( new Lightweight_SEO_Settings() );
		$file     = $transfer->export();

		$this->assertSame( 'lightweight-seo-settings', $file['format'] );
		$this->assertSame( LIGHTWEIGHT_SEO_SCHEMA_VERSION, $file['schema_version'] );
		$this->assertSame( 'https://example.com/', $file['site_url'] );
		$this->assertSame( '%title% | %sitename%', $file['settings']['title_format'] );
		// A setting that was never saved is exported with its default, so the file is complete.
		$this->assertSame( '1', $file['settings']['noindex_search_results'] );
		$this->assertTrue( $file['modules']['redirects'] );
		$this->assertFalse( $file['modules']['tracking'] );

		foreach ( array( 'meta_keywords', 'search_console_property', 'search_console_service_account_json', 'import_source', 'last_import_report' ) as $local ) {
			$this->assertArrayNotHasKey( $local, $file['settings'] );
		}

		$json = $transfer->export_json();

		$this->assertStringNotContainsString( 'secret', $json );
		$this->assertSame( $file['settings'], json_decode( $json, true )['settings'] );
	}

	public function test_import_refuses_anything_that_is_not_a_settings_file_of_this_or_an_older_version(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'title_format' => 'Kept' );

		$before   = $lightweight_seo_test_options;
		$transfer = new Lightweight_SEO_Settings_Transfer( new Lightweight_SEO_Settings() );
		$newer    = $transfer->export();

		$newer['schema_version'] = LIGHTWEIGHT_SEO_SCHEMA_VERSION + 1;

		foreach ( array( '', 'not json', '[]', '{"format":"something-else","settings":{}}', '{"format":"lightweight-seo-settings","settings":"text"}', wp_json_encode( $newer ) ) as $contents ) {
			$result = $transfer->import( $contents );

			$this->assertFalse( $result['ok'], $contents );
			$this->assertNotSame( '', $result['message'] );
			$this->assertSame( $before, $lightweight_seo_test_options, $contents );
		}
	}

	public function test_import_replaces_every_setting_through_the_settings_checks_and_keeps_what_is_local(): void {
		global $lightweight_seo_test_option_autoload;
		global $lightweight_seo_test_options;

		$current = array(
			'title_format'            => 'Current %title%',
			'home_title_format'       => 'Current home',
			'gtm_container_id'        => 'GTM-CURRENT',
			'x_handle'                => '@current',
			'enable_tracking'         => '1',
			'meta_keywords'           => 'legacy,keywords',
			'search_console_property' => 'sc-domain:example.com',
		);

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]         = $current;
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ] = array( 'tracking' => true );

		$file = array(
			'format'         => 'lightweight-seo-settings',
			'format_version' => 1,
			'schema_version' => LIGHTWEIGHT_SEO_SCHEMA_VERSION,
			'site_url'       => 'https://example.com',
			'settings'       => array(
				'title_format'            => '%title% %sep% %category% %sep% %sitename%',
				'title_separator'         => '|',
				'enable_redirects'        => '1',
				'redirect_rules'          => "/old /new 301\n/gone 410\n/loop-a /loop-b\n/loop-b /loop-a",
				'ga4_measurement_id'      => 'not-an-id',
				'x_handle'                => 'not a handle!',
				'social_image'            => 'https://example.com/share.jpg',
				'social_image_id'         => 31,
				'unknown_setting'         => 'ignored',
				'meta_keywords'           => 'from,the,file',
				'search_console_property' => 'sc-domain:other.example',
			),
		);

		$transfer = new Lightweight_SEO_Settings_Transfer( new Lightweight_SEO_Settings() );
		$result   = $transfer->import( wp_json_encode( $file ) );
		$stored   = $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ];

		$this->assertTrue( $result['ok'] );
		$this->assertSame( array(), $result['notes'] );
		$this->assertCount( 2, $result['rejected'] );

		// Values from the file, checked like a save on the settings screen.
		$this->assertSame( '%title% %sep% %category% %sep% %sitename%', $stored['title_format'] );
		$this->assertSame( '|', $stored['title_separator'] );
		$this->assertSame( "/old /new 301\n/gone 410", $stored['redirect_rules'] );
		$this->assertSame( 31, $stored['social_image_id'] );

		// What the file does not hold, or what fails a check, is the default and not this site's value.
		$this->assertSame( '%sitename% %sep% %tagline%', $stored['home_title_format'] );
		$this->assertSame( '', $stored['gtm_container_id'] );
		$this->assertSame( '', $stored['ga4_measurement_id'] );
		$this->assertSame( '', $stored['x_handle'] );
		$this->assertSame( '0', $stored['enable_tracking'] );
		$this->assertArrayNotHasKey( 'unknown_setting', $stored );

		// What must stay on a site is untouched by the file.
		$this->assertSame( 'legacy,keywords', $stored['meta_keywords'] );
		$this->assertSame( 'sc-domain:example.com', $stored['search_console_property'] );

		// Module switches follow the imported settings.
		$this->assertTrue( $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ]['redirects'] );
		$this->assertFalse( $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ]['tracking'] );

		// The replaced settings are kept, outside the options loaded on every request.
		$backup = $lightweight_seo_test_options[ Lightweight_SEO_Settings_Transfer::BACKUP_OPTION ];

		$this->assertSame( $current, $backup['settings'] );
		$this->assertSame( array( 'tracking' => true ), $backup['modules'] );
		$this->assertFalse( $lightweight_seo_test_option_autoload[ Lightweight_SEO_Settings_Transfer::BACKUP_OPTION ] );
		$this->assertGreaterThan( 0, $transfer->get_undo_time() );

		// Saving the imported settings again changes nothing.
		$validator = new Lightweight_SEO_Admin_Settings( 'lightweight-seo', new Lightweight_SEO_Settings(), null, null, null );
		$this->assertEquals( $stored, $validator->validate_settings( $stored ) );
	}

	public function test_a_file_from_another_site_leaves_out_what_points_at_that_sites_media_and_pages(): void {
		global $lightweight_seo_test_options;

		$file = array(
			'format'         => 'lightweight-seo-settings',
			'format_version' => 1,
			'schema_version' => LIGHTWEIGHT_SEO_SCHEMA_VERSION,
			'site_url'       => 'https://other.example/',
			'settings'       => array(
				'social_image'      => 'https://other.example/share.jpg',
				'social_image_id'   => 31,
				'identity_logo'     => 'https://other.example/logo.png',
				'identity_logo_id'  => 32,
				'llms_txt_post_ids' => '4,8',
				'hreflang_mappings' => "post:12 en-GB https://example.co.uk/page/\nde-DE https://example.de",
			),
		);

		$result = ( new Lightweight_SEO_Settings_Transfer( new Lightweight_SEO_Settings() ) )->import( wp_json_encode( $file ) );
		$stored = $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ];

		$this->assertTrue( $result['ok'] );
		$this->assertCount( 1, $result['notes'] );
		$this->assertSame( 'https://other.example/share.jpg', $stored['social_image'] );
		$this->assertSame( 0, $stored['social_image_id'] );
		$this->assertSame( 'https://other.example/logo.png', $stored['identity_logo'] );
		$this->assertSame( 0, $stored['identity_logo_id'] );
		$this->assertSame( '', $stored['llms_txt_post_ids'] );
		$this->assertStringNotContainsString( 'post:12', $stored['hreflang_mappings'] );
	}

	public function test_undo_puts_back_exactly_what_was_stored_and_can_be_used_once(): void {
		global $lightweight_seo_test_options;

		// Settings as an older release stored them: only some keys, one of them retired.
		$before_settings = array(
			'title_format'       => 'Before %title%',
			'retired_setting'    => 'still here',
			'ga4_measurement_id' => 'G-BEFORE1',
		);
		$before_modules  = array(
			'redirects' => false,
			'hreflang'  => false,
			'tracking'  => true,
			'local-seo' => false,
			'ai'        => false,
		);

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]         = $before_settings;
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ] = $before_modules;

		$transfer = new Lightweight_SEO_Settings_Transfer( new Lightweight_SEO_Settings() );

		$this->assertSame( 0, $transfer->get_undo_time() );
		$this->assertFalse( $transfer->undo()['ok'] );

		$file                                 = $transfer->export();
		$file['settings']['title_format']     = 'Imported %title%';
		$file['settings']['enable_redirects'] = '1';

		$this->assertTrue( $transfer->import( wp_json_encode( $file ) )['ok'] );
		$this->assertSame( 'Imported %title%', $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]['title_format'] );

		$this->assertTrue( $transfer->undo()['ok'] );
		$this->assertSame( $before_settings, $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] );
		$this->assertSame( $before_modules, $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ] );
		$this->assertArrayNotHasKey( Lightweight_SEO_Settings_Transfer::BACKUP_OPTION, $lightweight_seo_test_options );
		$this->assertFalse( $transfer->undo()['ok'] );
		$this->assertFalse( Lightweight_SEO_Settings_Transfer::is_restoring() );
	}

	public function test_the_settings_checks_step_aside_only_while_an_undo_writes_its_values(): void {
		$validator = new Lightweight_SEO_Admin_Settings( 'lightweight-seo', new Lightweight_SEO_Settings(), null, null, null );
		$validated = $validator->validate_settings( array( 'retired_setting' => 'dropped' ) );

		$this->assertArrayNotHasKey( 'retired_setting', $validated );
		$this->assertFalse( Lightweight_SEO_Settings_Transfer::is_restoring() );
	}
}
