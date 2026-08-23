<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-importer-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOImporterServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_options   = array();
		$lightweight_seo_test_post_meta = array();
		$lightweight_seo_test_posts     = array();
	}

	public function test_preview_is_read_only_and_import_fills_empty_fields_only(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_post_meta = array(
			81 => array(
				'_yoast_wpseo_title'               => 'Imported Title',
				'_yoast_wpseo_metadesc'            => 'Imported Description',
				'_yoast_wpseo_meta-robots-noindex' => '1',
			),
		);
		$lightweight_seo_test_posts     = array(
			81 => (object) array(
				'ID'          => 81,
				'post_type'   => 'post',
				'post_status' => 'publish',
			),
		);
		$values                         = array( 81 => array( 'seo_title' => 'Keep Existing' ) );
		$service                        = new Lightweight_SEO_Importer_Service( $this->get_post_meta_stub( $values ) );

		$preview = $service->preview( 'yoast' );

		$this->assertSame( 1, $preview['scanned_posts'] );
		$this->assertSame( 1, $preview['eligible_posts'] );
		$this->assertSame( 2, $preview['updated_fields'] );
		$this->assertSame( 1, $preview['skipped_fields'] );
		$this->assertSame( array( 81 => array( 'seo_title' => 'Keep Existing' ) ), $values );

		$report = $service->import_batch( 'yoast' );

		$this->assertSame( 1, $report['imported_posts'] );
		$this->assertSame( 'Keep Existing', $values[81]['seo_title'] );
		$this->assertSame( 'Imported Description', $values[81]['seo_description'] );
		$this->assertSame( '1', $values[81]['seo_noindex'] );
		$this->assertTrue( $service->has_rollback() );
	}

	public function test_import_is_batched_and_exposes_a_stable_cursor(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		for ( $post_id = 1; $post_id <= 55; ++$post_id ) {
			$lightweight_seo_test_posts[ $post_id ]                           = (object) array(
				'ID'          => $post_id,
				'post_type'   => 'post',
				'post_status' => 'publish',
			);
			$lightweight_seo_test_post_meta[ $post_id ]['_yoast_wpseo_title'] = 'Title ' . $post_id;
		}

		$values  = array();
		$service = new Lightweight_SEO_Importer_Service( $this->get_post_meta_stub( $values ) );
		$first   = $service->import_batch( 'yoast', 0 );
		$second  = $service->import_batch( 'yoast', $first['next_offset'] );

		$this->assertSame( 50, $first['scanned_posts'] );
		$this->assertTrue( $first['has_more'] );
		$this->assertSame( 50, $first['next_offset'] );
		$this->assertSame( 5, $second['scanned_posts'] );
		$this->assertFalse( $second['has_more'] );
		$this->assertCount( 55, $values );
	}

	public function test_latest_batch_can_be_rolled_back(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_posts[9]                           = (object) array(
			'ID'          => 9,
			'post_type'   => 'post',
			'post_status' => 'publish',
		);
		$lightweight_seo_test_post_meta[9]['_yoast_wpseo_title'] = 'Imported';
		$values  = array();
		$service = new Lightweight_SEO_Importer_Service( $this->get_post_meta_stub( $values ) );

		$service->import_batch( 'yoast' );
		$report = $service->rollback_last_batch();

		$this->assertSame( 1, $report['restored_posts'] );
		$this->assertSame( 1, $report['restored_fields'] );
		$this->assertArrayNotHasKey( 'seo_title', $values[9] );
		$this->assertFalse( $service->has_rollback() );
	}

	private function get_post_meta_stub( &$values ) {
		return new class( $values ) {
			private $values;

			public function __construct( &$values ) {
				$this->values =& $values;
			}

			public function get_supported_post_types() {
				return array( 'post' );
			}

			public function get( $post_id, $field ) {
				return $this->values[ $post_id ][ $field ] ?? '';
			}

			public function get_meta_key( $field ) {
				return '_lightweight_seo_' . $field;
			}

			public function update( $post_id, $field, $value ) {
				$this->values[ $post_id ][ $field ] = $value;

				return true;
			}

			public function delete( $post_id, $field ) {
				unset( $this->values[ $post_id ][ $field ] );

				return true;
			}
		};
	}
}
