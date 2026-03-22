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
				'post_title'   => 'SEO Basics',
				'post_content' => '<a href="/beta/">read more</a><a href="https://example.com/missing-page/">Missing</a><a href="https://external.example.com/">External</a><p>This SEO audit guide covers crawl depth.</p>',
				'permalink'    => 'https://example.com/alpha/',
			),
			22 => (object) array(
				'ID'           => 22,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Metadata Tips',
				'post_content' => '<a href="/alpha/">click here</a>',
				'permalink'    => 'https://example.com/beta/',
			),
			23 => (object) array(
				'ID'           => 23,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'SEO Audit Guide',
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
		$this->assertSame( array( 'SEO Audit Guide' ), array_column( $report['orphan_pages'], 'title' ) );
		$this->assertSame( array( 'Metadata Tips', 'SEO Basics' ), array_column( $report['weak_pages'], 'title' ) );
		$this->assertCount( 1, $report['broken_links'] );
		$this->assertSame( '/missing-page', $report['broken_links'][0]['target_path'] );
		$this->assertSame( array( 'Metadata Tips', 'SEO Basics' ), array_column( $report['anchor_text_issues'], 'title' ) );
		$this->assertSame( 'metadata tips', $report['anchor_text_issues'][0]['recommended_anchor'] );
		$this->assertSame( 'SEO Audit Guide', $report['link_suggestions'][0]['target_title'] );
		$this->assertSame( 'seo audit guide', $report['link_suggestions'][0]['recommended_anchor'] );
		$this->assertSame( 'SEO Basics', $report['link_suggestions'][0]['suggestions'][0]['source_title'] );
		$this->assertSame( 'seo', $report['topic_clusters'][0]['topic'] );
		$this->assertSame( 2, $report['topic_clusters'][0]['member_count'] );
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

	public function test_get_report_excludes_default_noindexed_attachments_when_enabled(): void {
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_posts[26] = (object) array(
			'ID'           => 26,
			'post_type'    => 'attachment',
			'post_status'  => 'publish',
			'post_title'   => 'Upload Asset',
			'post_content' => '',
			'permalink'    => 'https://example.com/uploads/upload-asset/',
		);

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page', 'attachment' );
			}

			public function get_all( $post_id ) {
				return array();
			}
		};

		$settings = new class() {
			public function attachment_pages_noindex_enabled() {
				return true;
			}
		};

		$service = new Lightweight_SEO_Internal_Links_Service( $post_meta, false, $settings );
		$report  = $service->get_report( true );

		$this->assertSame( 4, $report['pages_scanned'] );
		$this->assertNotContains( 'Upload Asset', array_column( $report['orphan_pages'], 'title' ) );
		$this->assertNotContains( 'Upload Asset', array_column( $report['weak_pages'], 'title' ) );
	}
}
