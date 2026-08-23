<?php

final class LightweightSEOSitemapIntegrationTest extends WP_UnitTestCase {

	public function test_core_post_sitemap_excludes_noindexed_content_and_renders_valid_xml(): void {
		update_option( 'blog_public', '1' );

		$included_post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$excluded_post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		update_post_meta( $excluded_post_id, '_lightweight_seo_noindex', '1' );

		$server   = wp_sitemaps_get_server();
		$provider = $server->registry->get_provider( 'posts' );
		$urls     = $provider->get_url_list( 1, 'post' );
		$locs     = wp_list_pluck( $urls, 'loc' );
		$xml      = $server->renderer->get_sitemap_xml( $urls );

		$this->assertContains( get_permalink( $included_post_id ), $locs );
		$this->assertNotContains( get_permalink( $excluded_post_id ), $locs );
		$this->assertNotFalse( simplexml_load_string( $xml ) );
	}

	public function test_specialized_sitemap_providers_are_absent_from_core_registry(): void {
		$registry = wp_sitemaps_get_server()->registry;

		$this->assertNull( $registry->get_provider( 'lightweightseoimages' ) );
		$this->assertNull( $registry->get_provider( 'lightweightseovideos' ) );
		$this->assertNull( $registry->get_provider( 'lightweightseonews' ) );
	}
}
