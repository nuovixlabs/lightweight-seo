<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-importer-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOImporterServiceTest extends TestCase {

	public function test_import_yoast_updates_supported_fields(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_post_meta = array(
			81 => array(
				'_yoast_wpseo_title'               => 'Imported Title',
				'_yoast_wpseo_metadesc'            => 'Imported Description',
				'_yoast_wpseo_canonical'           => 'https://example.com/imported/',
				'_yoast_wpseo_meta-robots-noindex' => '1',
			),
		);
		$lightweight_seo_test_posts     = array(
			81 => (object) array(
				'ID'          => 81,
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Legacy Post',
				'permalink'   => 'https://example.com/legacy-post/',
			),
		);

		$imported_meta = array();
		$post_meta     = new class( $imported_meta ) {
			private $imported_meta;

			public function __construct( &$imported_meta ) {
				$this->imported_meta =& $imported_meta;
			}

			public function get_supported_post_types() {
				return array( 'post' );
			}

			public function get( $post_id, $field ) {
				return $this->imported_meta[ $post_id ][ $field ] ?? '';
			}

			public function update( $post_id, $field, $value ) {
				$this->imported_meta[ $post_id ][ $field ] = $value;

				return true;
			}
		};

		$service = new Lightweight_SEO_Importer_Service( $post_meta );
		$report  = $service->import( 'yoast' );

		$this->assertSame( 1, $report['scanned_posts'] );
		$this->assertSame( 1, $report['imported_posts'] );
		$this->assertSame( 'Imported Title', $imported_meta[81]['seo_title'] );
		$this->assertSame( 'Imported Description', $imported_meta[81]['seo_description'] );
		$this->assertSame( '1', $imported_meta[81]['seo_noindex'] );
	}
}
