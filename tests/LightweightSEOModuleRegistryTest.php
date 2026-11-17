<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-module-state.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-module-registry.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOModuleRegistryTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options = array(
			LIGHTWEIGHT_SEO_MODULES_OPTION_NAME => array(),
		);
	}

	public function test_disabled_module_does_not_invoke_factory(): void {
		$factory_calls = 0;
		$registry      = new Lightweight_SEO_Module_Registry( new Lightweight_SEO_Module_State() );

		$registry->register(
			'fixture',
			array(
				'name'     => 'Fixture',
				'contexts' => array( 'frontend' ),
				'factory'  => function () use ( &$factory_calls ) {
					++$factory_calls;
				},
			)
		);
		$registry->finalize();
		$registry->load_context( 'frontend' );

		$this->assertSame( 0, $factory_calls );
		$this->assertFalse( $registry->get_public_modules()['fixture']['enabled'] );
		$this->assertArrayNotHasKey( 'factory', $registry->get_public_modules()['fixture'] );
	}

	public function test_enabled_module_loads_only_in_declared_context_once(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ] = array( 'tracking' => true );
		$factory_calls = 0;
		$registry      = new Lightweight_SEO_Module_Registry( new Lightweight_SEO_Module_State() );
		$registry->register(
			'tracking',
			array(
				'contexts' => array( 'frontend' ),
				'factory'  => function () use ( &$factory_calls ) {
					++$factory_calls;
				},
			)
		);
		$registry->finalize();

		$registry->load_context( 'admin' );
		$registry->load_context( 'frontend' );
		$registry->load_context( 'frontend' );

		$this->assertSame( 1, $factory_calls );
		$this->assertTrue( $registry->get_public_modules()['tracking']['loaded'] );
		$this->assertFalse( $registry->register( 'late-module', array() ) );
	}

	public function test_legacy_state_derivation_requires_explicit_or_valid_configuration(): void {
		$states = Lightweight_SEO_Module_State::derive_from_legacy_settings(
			array(
				'enable_hreflang_output'       => '1',
				'enable_local_business_schema' => '0',
				'ga4_measurement_id'           => 'not-an-id',
				'gtm_container_id'             => 'GTM-ABC123',
				'enable_404_monitor'           => '0',
				'enable_auto_redirects'        => '0',
				'redirect_rules'               => '',
			)
		);

		$this->assertTrue( $states['hreflang'] );
		$this->assertTrue( $states['tracking'] );
		$this->assertFalse( $states['local-seo'] );
		$this->assertFalse( $states['redirects'] );

		$legacy_404_only = Lightweight_SEO_Module_State::derive_from_legacy_settings( array( 'enable_404_monitor' => '1' ) );
		$this->assertFalse( $legacy_404_only['redirects'] );

		$ai_enabled = Lightweight_SEO_Module_State::derive_from_legacy_settings( array( 'enable_ai_discovery' => '1' ) );
		$this->assertTrue( $ai_enabled['ai'] );
	}

	public function test_redirects_and_tracking_follow_their_own_switch_once_it_is_stored(): void {
		$switched_off = Lightweight_SEO_Module_State::derive_from_legacy_settings(
			array(
				'enable_redirects'      => '0',
				'enable_auto_redirects' => '1',
				'redirect_rules'        => '/old /new 301',
				'enable_tracking'       => '0',
				'gtm_container_id'      => 'GTM-ABC123',
			)
		);

		$this->assertFalse( $switched_off['redirects'] );
		$this->assertFalse( $switched_off['tracking'] );

		$switched_on = Lightweight_SEO_Module_State::derive_from_legacy_settings(
			array(
				'enable_redirects' => '1',
				'enable_tracking'  => '1',
			)
		);

		$this->assertTrue( $switched_on['redirects'] );
		$this->assertTrue( $switched_on['tracking'] );
	}
}
