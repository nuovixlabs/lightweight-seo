<?php

final class LightweightSEOImporterIntegrationTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		delete_option( Lightweight_SEO_Importer_Service::ROLLBACK_OPTION );
	}

	public function test_preview_import_and_rollback_preserve_existing_seo_values(): void {
		$fillable_post = self::factory()->post->create();
		$existing_post = self::factory()->post->create();
		update_post_meta( $fillable_post, '_yoast_wpseo_title', 'Imported title' );
		update_post_meta( $existing_post, '_yoast_wpseo_title', 'Do not overwrite' );
		update_post_meta( $existing_post, '_lightweight_seo_title', 'Existing title' );

		$service = new Lightweight_SEO_Importer_Service( new Lightweight_SEO_Post_Meta() );
		$preview = $service->preview( 'yoast' );

		$this->assertSame( '', get_post_meta( $fillable_post, '_lightweight_seo_title', true ) );
		$this->assertSame( 1, $preview['eligible_posts'] );
		$this->assertSame( 1, $preview['skipped_fields'] );

		$report = $service->import_batch( 'yoast' );

		$this->assertSame( 1, $report['imported_posts'] );
		$this->assertSame( 'Imported title', get_post_meta( $fillable_post, '_lightweight_seo_title', true ) );
		$this->assertSame( 'Existing title', get_post_meta( $existing_post, '_lightweight_seo_title', true ) );

		$rollback = $service->rollback_last_batch();

		$this->assertSame( 1, $rollback['restored_fields'] );
		$this->assertSame( '', get_post_meta( $fillable_post, '_lightweight_seo_title', true ) );
		$this->assertSame( 'Existing title', get_post_meta( $existing_post, '_lightweight_seo_title', true ) );
	}
}
