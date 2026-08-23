<?php

final class LightweightSEOWooCommerceCompatibilityTest extends WP_UnitTestCase {

	public function test_product_page_does_not_receive_lightweight_seo_product_or_article_schema(): void {
		if ( ! defined( 'WC_VERSION' ) ) {
			$this->markTestSkipped( 'WooCommerce compatibility job only.' );
		}

		$product_id = self::factory()->post->create(
			array(
				'post_type'   => 'product',
				'post_status' => 'publish',
				'post_title'  => 'Compatibility Product',
			)
		);
		$this->go_to( get_permalink( $product_id ) );

		$settings     = new Lightweight_SEO_Settings();
		$post_meta    = new Lightweight_SEO_Post_Meta();
		$archive_meta = new Lightweight_SEO_Archive_Meta( $settings );
		$page_context = new Lightweight_SEO_Page_Context_Service( $settings, $post_meta, $archive_meta );
		$schema       = new Lightweight_SEO_Schema_Service( $page_context, $settings );

		ob_start();
		$schema->add_schema();
		$output = ob_get_clean();

		$this->assertNotSame( '', (string) WC_VERSION );
		$this->assertStringNotContainsString( '"@type":"Product"', $output );
		$this->assertStringNotContainsString( '"@type":"Article"', $output );
	}
}
