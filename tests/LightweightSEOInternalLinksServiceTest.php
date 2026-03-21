<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-internal-links-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOInternalLinksServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_options = array();
		$lightweight_seo_test_posts   = array(
			21 => (object) array(
				'ID'           => 21,
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Alpha',
				'post_content' => '<a href="/beta/">Beta</a><a href="https://example.com/missing-page/">Missing</a><a href="https://external.example.com/">External</a>',
				'permalink'    => 'https://example.com/alpha/',
			),
			22 => (object) array(
				'ID'           => 22,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Beta',
				'post_content' => '<a href="/alpha/">Alpha</a>',
				'permalink'    => 'https://example.com/beta/',
			),
			23 => (object) array(
				'ID'           => 23,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Gamma',
				'post_content' => '',
				'permalink'    => 'https://example.com/gamma/',
			),
			24 => (object) array(
				'ID'           => 24,
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Noindex',
				'post_content' => '<a href="/gamma/">Gamma</a>',
				'permalink'    => 'https://example.com/noindex/',
			),
		);
	}

	public function test_get_report_detects_orphans_broken_links_and_noindex_exclusions(): void {
		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}

			public function get_all( $post_id ) {
				if ( 24 === (int) $post_id ) {
					return array(
						'seo_noindex' => '1',
					);
				}

				return array();
			}
		};

		$service = new Lightweight_SEO_Internal_Links_Service( $post_meta, false );
		$report  = $service->get_report( true );

		$this->assertSame( 3, $report['pages_scanned'] );
		$this->assertSame( 3, $report['internal_links'] );
		$this->assertSame( array( 'Gamma' ), array_column( $report['orphan_pages'], 'title' ) );
		$this->assertSame( array( 'Alpha', 'Beta' ), array_column( $report['weak_pages'], 'title' ) );
		$this->assertCount( 1, $report['broken_links'] );
		$this->assertSame( '/missing-page', $report['broken_links'][0]['target_path'] );
	}

	public function test_get_report_uses_cached_payload_until_invalidated(): void {
		global $lightweight_seo_test_posts;

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}

			public function get_all( $post_id ) {
				return array();
			}
		};

		$service      = new Lightweight_SEO_Internal_Links_Service( $post_meta, false );
		$first_report = $service->get_report( true );

		$lightweight_seo_test_posts[25] = (object) array(
			'ID'           => 25,
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Delta',
			'post_content' => '',
			'permalink'    => 'https://example.com/delta/',
		);

		$cached_report = $service->get_report();

		$this->assertSame( 4, $first_report['pages_scanned'] );
		$this->assertSame( $first_report['pages_scanned'], $cached_report['pages_scanned'] );

		$service->invalidate_report_cache();
		$refreshed_report = $service->get_report();

		$this->assertSame( $first_report['pages_scanned'] + 1, $refreshed_report['pages_scanned'] );
	}
}
