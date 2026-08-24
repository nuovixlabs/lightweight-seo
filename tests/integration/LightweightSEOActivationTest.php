<?php

final class LightweightSEOActivationTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		delete_option( LIGHTWEIGHT_SEO_OPTION_NAME );
		delete_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME );
		delete_option( LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION );
		foreach ( Lightweight_SEO_Data_Registry::get_ephemeral_options() as $option_name ) {
			delete_option( $option_name );
		}
		wp_clear_scheduled_hook( 'lightweight_seo_search_console_sync' );
	}

	public function test_activation_initializes_schema_without_frontend_output(): void {
		Lightweight_SEO_Lifecycle::activate();

		$this->assertIsArray( get_option( LIGHTWEIGHT_SEO_OPTION_NAME ) );
		$this->assertSame( 3, get_option( LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION ) );
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
			'meta_keywords'                       => 'legacy,keywords',
			'enable_meta_keywords'                => '1',
			'enable_image_sitemaps'               => '1',
			'ga4_measurement_id'                  => 'G-EXISTING1',
			'enable_hreflang_output'              => '1',
			'enable_local_business_schema'        => '1',
			'search_console_service_account_json' => '{"private_key":"preserve-until-cleanup"}',
		);
		$post_id         = self::factory()->post->create();
		$term_id         = self::factory()->term->create();
		$user_id         = self::factory()->user->create();

		update_option( LIGHTWEIGHT_SEO_OPTION_NAME, $legacy_settings );
		update_option( 'lightweight_seo_internal_links_report', array( 'retired' ) );
		update_option( 'lightweight_seo_search_console_token', array( 'private' ) );
		update_post_meta( $post_id, '_lightweight_seo_title', 'Post title' );
		update_term_meta( $term_id, '_lightweight_seo_description', 'Term description' );
		update_user_meta( $user_id, '_lightweight_seo_social_title', 'Author social title' );
		wp_schedule_event( time() + 60, 'daily', 'lightweight_seo_search_console_sync' );

		Lightweight_SEO_Lifecycle::activate();

		$migrated = get_option( LIGHTWEIGHT_SEO_OPTION_NAME );

		$this->assertSame( 'Legacy %title%', $migrated['title_format'] );
		$this->assertSame( 'legacy,keywords', $migrated['meta_keywords'] );
		$this->assertArrayNotHasKey( 'enable_meta_keywords', $migrated );
		$this->assertArrayNotHasKey( 'enable_image_sitemaps', $migrated );
		$this->assertSame( '{"private_key":"preserve-until-cleanup"}', $migrated['search_console_service_account_json'] );
		$this->assertSame( 3, get_option( LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION ) );
		$this->assertTrue( get_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME )['tracking'] );
		$this->assertFalse( get_option( 'lightweight_seo_internal_links_report' ) );
		$this->assertFalse( get_option( 'lightweight_seo_search_console_token' ) );
		$this->assertFalse( wp_next_scheduled( 'lightweight_seo_search_console_sync' ) );
		$this->assertSame( 'Post title', get_post_meta( $post_id, '_lightweight_seo_title', true ) );
		$this->assertSame( 'Term description', get_term_meta( $term_id, '_lightweight_seo_description', true ) );
		$this->assertSame( 'Author social title', get_user_meta( $user_id, '_lightweight_seo_social_title', true ) );
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

		$this->assertSame( 3, get_blog_option( $second_site_id, LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION ) );
	}
}
