<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', true );
}

require_once dirname( __DIR__, 2 ) . '/uninstall.php';

final class LightweightSEOUninstallTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		foreach ( Lightweight_SEO_Data_Registry::get_persistent_options() as $option_name ) {
			delete_option( $option_name );
		}

		foreach ( Lightweight_SEO_Data_Registry::get_ephemeral_options() as $option_name ) {
			delete_option( $option_name );
		}

		wp_clear_scheduled_hook( 'lightweight_seo_search_console_sync' );
	}

	public function test_uninstall_preserves_seo_data_by_default_and_removes_ephemeral_data(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_lightweight_seo_title', 'Preserve me' );
		update_option(
			LIGHTWEIGHT_SEO_OPTION_NAME,
			array(
				'title_format'                        => 'Preserve me',
				'search_console_service_account_json' => 'private',
				'last_import_report'                  => 'temporary',
			)
		);
		update_option( 'lightweight_seo_search_console_token', array( 'token' => 'temporary' ) );
		wp_schedule_event( time() + 60, 'daily', 'lightweight_seo_search_console_sync' );

		lightweight_seo_delete_plugin_data();

		$settings = get_option( LIGHTWEIGHT_SEO_OPTION_NAME );

		$this->assertSame( 'Preserve me', get_post_meta( $post_id, '_lightweight_seo_title', true ) );
		$this->assertSame( 'Preserve me', $settings['title_format'] );
		$this->assertArrayNotHasKey( 'search_console_service_account_json', $settings );
		$this->assertArrayNotHasKey( 'last_import_report', $settings );
		$this->assertFalse( get_option( 'lightweight_seo_search_console_token' ) );
		$this->assertFalse( wp_next_scheduled( 'lightweight_seo_search_console_sync' ) );
	}

	public function test_uninstall_does_not_create_missing_settings(): void {
		delete_option( LIGHTWEIGHT_SEO_OPTION_NAME );

		lightweight_seo_delete_plugin_data();

		$this->assertFalse( get_option( LIGHTWEIGHT_SEO_OPTION_NAME ) );
	}

	public function test_uninstall_delete_all_removes_options_and_all_object_meta(): void {
		$post_id = self::factory()->post->create();
		$term_id = self::factory()->term->create();
		$user_id = self::factory()->user->create();

		update_post_meta( $post_id, '_lightweight_seo_canonical_url', 'https://example.org/post' );
		update_term_meta( $term_id, '_lightweight_seo_canonical_url', 'https://example.org/term' );
		update_user_meta( $user_id, '_lightweight_seo_canonical_url', 'https://example.org/author' );
		update_option( LIGHTWEIGHT_SEO_OPTION_NAME, array( 'delete_data_on_uninstall' => '1' ) );
		update_option( LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION, 1 );
		update_option( 'lightweight_seo_generated_redirect_rules', array( '/old' => '/new' ) );

		lightweight_seo_delete_plugin_data();

		$this->assertFalse( get_option( LIGHTWEIGHT_SEO_OPTION_NAME ) );
		$this->assertFalse( get_option( LIGHTWEIGHT_SEO_SCHEMA_VERSION_OPTION ) );
		$this->assertFalse( get_option( 'lightweight_seo_generated_redirect_rules' ) );
		$this->assertSame( '', get_post_meta( $post_id, '_lightweight_seo_canonical_url', true ) );
		$this->assertSame( '', get_term_meta( $term_id, '_lightweight_seo_canonical_url', true ) );
		$this->assertSame( '', get_user_meta( $user_id, '_lightweight_seo_canonical_url', true ) );
	}
}
