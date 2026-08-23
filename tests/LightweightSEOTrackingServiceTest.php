<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-tracking-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOTrackingServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_environment_type;
		global $lightweight_seo_test_filters;
		global $lightweight_seo_test_user_roles;

		$lightweight_seo_test_environment_type = 'production';
		$lightweight_seo_test_filters          = array();
		$lightweight_seo_test_user_roles       = array();
	}

	public function test_gtm_is_the_primary_provider_when_direct_ids_are_also_saved(): void {
		$service = new Lightweight_SEO_Tracking_Service(
			$this->settings(
				array(
					'gtm_container_id'   => 'GTM-PRIMARY1',
					'ga4_measurement_id' => 'G-DIRECT1',
					'facebook_pixel_id'  => '1234567',
				)
			)
		);

		ob_start();
		$service->add_tracking_codes();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'GTM-PRIMARY1', $output );
		$this->assertStringNotContainsString( 'G-DIRECT1', $output );
		$this->assertStringNotContainsString( '1234567', $output );
	}

	public function test_direct_providers_support_a_csp_nonce_when_gtm_is_empty(): void {
		global $lightweight_seo_test_filters;

		$lightweight_seo_test_filters['lightweight_seo_tracking_script_nonce'] = static function () {
			return 'test-nonce';
		};

		$service = new Lightweight_SEO_Tracking_Service(
			$this->settings(
				array(
					'ga4_measurement_id' => 'G-DIRECT1',
					'facebook_pixel_id'  => '1234567',
				)
			)
		);

		ob_start();
		$service->add_tracking_codes();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'G-DIRECT1', $output );
		$this->assertStringContainsString( '1234567', $output );
		$this->assertStringContainsString( 'nonce="test-nonce"', $output );
	}

	public function test_legacy_settings_filter_receives_only_tracking_identifiers(): void {
		global $lightweight_seo_test_filters;

		$received = array();
		$lightweight_seo_test_filters['lightweight_seo_tracking_settings'] = static function ( $settings ) use ( &$received ) {
			$received                       = $settings;
			$settings['ga4_measurement_id'] = 'G-FILTERED1';

			return $settings;
		};
		$service = new Lightweight_SEO_Tracking_Service(
			$this->settings(
				array(
					'ga4_measurement_id'                  => 'G-DIRECT1',
					'search_console_service_account_json' => 'private',
				)
			)
		);

		ob_start();
		$service->add_tracking_codes();
		$output = ob_get_clean();

		$this->assertSame( array( 'gtm_container_id', 'ga4_measurement_id', 'facebook_pixel_id' ), array_keys( $received ) );
		$this->assertStringContainsString( 'G-FILTERED1', $output );
		$this->assertStringNotContainsString( 'private', $output );
	}

	public function test_environment_role_and_consent_exclusions_prevent_output(): void {
		global $lightweight_seo_test_environment_type;
		global $lightweight_seo_test_filters;
		global $lightweight_seo_test_user_roles;

		$settings = $this->settings(
			array( 'gtm_container_id' => 'GTM-PRIMARY1' ),
			array( 'administrator' ),
			array( 'staging' )
		);
		$service  = new Lightweight_SEO_Tracking_Service( $settings );

		$lightweight_seo_test_environment_type = 'staging';
		$this->assertFalse( $service->tracking_is_allowed( 'gtm' ) );

		$lightweight_seo_test_environment_type = 'production';
		$lightweight_seo_test_user_roles       = array( 'administrator' );
		$this->assertFalse( $service->tracking_is_allowed( 'gtm' ) );

		$lightweight_seo_test_user_roles = array();
		$lightweight_seo_test_filters['lightweight_seo_tracking_consent_granted'] = static function () {
			return false;
		};
		$this->assertFalse( $service->tracking_is_allowed( 'gtm' ) );
	}

	public function test_gtm_reports_a_missing_body_hook_without_a_frontend_write(): void {
		$service = new Lightweight_SEO_Tracking_Service( $this->settings( array( 'gtm_container_id' => 'GTM-PRIMARY1' ) ) );

		ob_start();
		$service->add_tracking_codes();
		$service->add_gtm_theme_diagnostic();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'did not call wp_body_open()', $output );

		$service = new Lightweight_SEO_Tracking_Service( $this->settings( array( 'gtm_container_id' => 'GTM-PRIMARY1' ) ) );
		ob_start();
		$service->add_tracking_codes();
		$service->add_gtm_noscript();
		$service->add_gtm_theme_diagnostic();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'did not call wp_body_open()', $output );
	}

	private function settings( $all, $roles = array(), $environments = array() ) {
		return new class( $all, $roles, $environments ) {
			private $all;
			private $roles;
			private $environments;

			public function __construct( $all, $roles, $environments ) {
				$this->all          = $all;
				$this->roles        = $roles;
				$this->environments = $environments;
			}

			public function get_all() {
				return $this->all;
			}

			public function get_tracking_excluded_roles() {
				return $this->roles;
			}

			public function get_tracking_excluded_environments() {
				return $this->environments;
			}
		};
	}
}
