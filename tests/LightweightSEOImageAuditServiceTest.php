<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-image-audit-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOImageAuditServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_cache;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_cache       = array();
		$lightweight_seo_test_options     = array();
		$lightweight_seo_test_post_meta   = array();
		$lightweight_seo_test_posts       = array();
		$lightweight_seo_test_query_state = array(
			'thumbnail_url' => '',
		);
	}

	public function test_get_report_excludes_default_noindexed_attachments_when_enabled(): void {
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_posts = array(
			31 => (object) array(
				'ID'           => 31,
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Content Page',
				'post_content' => '',
				'permalink'    => 'https://example.com/content-page/',
			),
			32 => (object) array(
				'ID'           => 32,
				'post_type'    => 'attachment',
				'post_status'  => 'publish',
				'post_title'   => 'Media Attachment',
				'post_content' => '',
				'permalink'    => 'https://example.com/uploads/media-attachment/',
			),
		);

		$settings = new class() {
			public function get_discover_min_image_width() {
				return 1200;
			}

			public function get_discover_min_image_height() {
				return 900;
			}

			public function attachment_pages_noindex_enabled() {
				return true;
			}
		};

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'attachment' );
			}

			public function get_all( $post_id ) {
				return array(
					'seo_noindex' => '',
				);
			}
		};

		$service = new Lightweight_SEO_Image_Audit_Service( $settings, $post_meta, false );
		$report  = $service->get_report( true );

		$this->assertSame( array( 'Content Page' ), array_column( $report['missing_featured_images'], 'title' ) );
	}

	public function test_get_report_rebuilds_when_discover_thresholds_change(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_post_meta = array(
			501 => array(
				'_wp_attachment_image_alt' => 'Discover image',
			),
		);
		$lightweight_seo_test_posts     = array(
			31  => (object) array(
				'ID'            => 31,
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => 'Discover Candidate',
				'post_content'  => '',
				'permalink'     => 'https://example.com/discover-candidate/',
				'thumbnail_id'  => 501,
				'thumbnail_url' => 'https://example.com/uploads/discover.jpg',
			),
			501 => (object) array(
				'ID'             => 501,
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/jpeg',
				'attachment_url' => 'https://example.com/uploads/discover.jpg',
				'metadata'       => array(
					'width'  => 1000,
					'height' => 800,
				),
			),
		);

		$settings = new class() {
			private $minimum_width  = 1200;
			private $minimum_height = 900;

			public function get_discover_min_image_width() {
				return $this->minimum_width;
			}

			public function get_discover_min_image_height() {
				return $this->minimum_height;
			}

			public function attachment_pages_noindex_enabled() {
				return true;
			}

			public function set_thresholds( $minimum_width, $minimum_height ) {
				$this->minimum_width  = $minimum_width;
				$this->minimum_height = $minimum_height;
			}
		};

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post' );
			}

			public function get_all( $post_id ) {
				return array(
					'seo_noindex' => '',
				);
			}
		};

		$service      = new Lightweight_SEO_Image_Audit_Service( $settings, $post_meta, false );
		$first_report = $service->get_report( true );

		$this->assertSame( 1200, $first_report['minimum_width'] );
		$this->assertCount( 1, $first_report['undersized_images'] );

		$settings->set_thresholds( 800, 600 );

		$updated_report = $service->get_report();

		$this->assertSame( 800, $updated_report['minimum_width'] );
		$this->assertSame( 600, $updated_report['minimum_height'] );
		$this->assertCount( 0, $updated_report['undersized_images'] );
	}
}
