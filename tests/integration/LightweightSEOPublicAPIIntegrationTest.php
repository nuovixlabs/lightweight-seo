<?php

final class LightweightSEOPublicAPIIntegrationTest extends WP_UnitTestCase {

	public function test_disabled_modules_load_no_implementation_classes_or_hooks(): void {
		$this->assertSame(
			array(
				'redirects' => false,
				'hreflang'  => false,
				'tracking'  => false,
				'local-seo' => false,
				'ai'        => false,
			),
			$GLOBALS['lightweight_seo_disabled_module_classes_at_boot']
		);
		$this->assertFalse( has_action( 'template_redirect', array( 'Lightweight_SEO_Redirects_Service', 'maybe_redirect_request' ) ) );
	}

	public function test_insights_style_fixture_consumes_only_the_public_facade(): void {
		$api = lightweight_seo_get_api();

		$this->assertInstanceOf( Lightweight_SEO_API::class, $api );
		$this->assertTrue( $api->is_compatible( '1.1.0-rc.1', '1.0' ) );
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

	public function test_tracking_module_prefers_gtm_and_emits_its_body_fallback(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-lightweight-seo-tracking-service.php';

		update_option(
			LIGHTWEIGHT_SEO_OPTION_NAME,
			array(
				'gtm_container_id'               => 'GTM-INTEGRATION1',
				'ga4_measurement_id'             => 'G-DIRECT1',
				'facebook_pixel_id'              => '1234567',
				'tracking_excluded_roles'        => '',
				'tracking_excluded_environments' => '',
			)
		);
		$service = new Lightweight_SEO_Tracking_Service( new Lightweight_SEO_Settings() );

		ob_start();
		$service->add_tracking_codes();
		$service->add_gtm_noscript();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'GTM-INTEGRATION1', $output );
		$this->assertStringContainsString( 'googletagmanager.com/ns.html', $output );
		$this->assertStringNotContainsString( 'G-DIRECT1', $output );
		$this->assertStringNotContainsString( '1234567', $output );
	}

	public function test_local_seo_module_replaces_the_generic_organization_with_valid_business_data(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-lightweight-seo-local-seo-module.php';
		$this->go_to( home_url( '/' ) );

		update_option(
			LIGHTWEIGHT_SEO_OPTION_NAME,
			array(
				'enable_local_business_schema'       => '1',
				'local_business_type'                => 'Restaurant',
				'local_business_name'                => 'Integration Cafe',
				'local_business_phone'               => '+1 555 555 5555',
				'local_business_price_range'         => '$$',
				'local_business_address_street'      => '123 Main St',
				'local_business_address_locality'    => 'San Diego',
				'local_business_address_region'      => 'CA',
				'local_business_address_postal_code' => '92101',
				'local_business_address_country'     => 'US',
				'local_business_latitude'            => '32.7157',
				'local_business_longitude'           => '-117.1611',
				'local_business_opening_hours'       => 'Mo-Fr 09:00-17:00',
				'local_business_image'               => 'https://example.org/business.jpg',
			)
		);
		$settings = new Lightweight_SEO_Settings();
		$module   = new Lightweight_SEO_Local_SEO_Module( $settings );
		$graph    = $module->add_local_business(
			array(
				array(
					'@type' => 'Organization',
					'@id'   => home_url( '/#organization' ),
					'name'  => get_bloginfo( 'name' ),
				),
			),
			array()
		);

		$this->assertCount( 1, $graph );
		$this->assertSame( 'Restaurant', $graph[0]['@type'] );
		$this->assertSame( 'Integration Cafe', $graph[0]['name'] );
		$this->assertSame( 'US', $graph[0]['address']['addressCountry'] );
		$this->assertSame( 'https://example.org/business.jpg', $graph[0]['image'] );
	}

	public function test_ai_discovery_outputs_separate_robots_policies_and_curated_llms_txt(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/class-lightweight-seo-ai-discovery-module.php';

		$public_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Public Guide',
				'post_excerpt' => 'A concise public summary.',
			)
		);
		$draft_id  = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
				'post_title'  => 'Draft Guide',
			)
		);
		update_option(
			LIGHTWEIGHT_SEO_OPTION_NAME,
			array(
				'enable_ai_discovery'          => '1',
				'ai_search_crawlers_enabled'   => '1',
				'ai_training_crawlers_enabled' => '0',
				'enable_llms_txt'              => '1',
				'llms_txt_post_ids'            => $public_id . ',' . $draft_id,
			)
		);
		$module = new Lightweight_SEO_AI_Discovery_Module( new Lightweight_SEO_Settings(), lightweight_seo_get_api(), 'admin' );
		$robots = $module->filter_robots_txt( "User-agent: *\nDisallow: /wp-admin/\n", true );
		$llms   = $module->build_llms_txt();

		$this->assertStringContainsString( "User-agent: OAI-SearchBot\nAllow: /", $robots );
		$this->assertStringContainsString( "User-agent: GPTBot\nDisallow: /", $robots );
		$this->assertStringContainsString( 'Public Guide', $llms );
		$this->assertStringContainsString( 'A concise public summary.', $llms );
		$this->assertStringNotContainsString( 'Draft Guide', $llms );
		$this->assertStringContainsString( 'does not guarantee crawling, citation, training, or inclusion', $llms );
	}
}
