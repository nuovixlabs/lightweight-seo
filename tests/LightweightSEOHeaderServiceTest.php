<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-header-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOHeaderServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_posts                            = array();
		$lightweight_seo_test_query_state['is_singular']       = false;
		$lightweight_seo_test_query_state['queried_object_id'] = 0;
		$_SERVER['REQUEST_URI']                                = '/';
	}

	public function test_get_x_robots_tag_uses_attachment_context_directives(): void {
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular']       = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 77;
		$lightweight_seo_test_posts[77]                        = (object) array(
			'ID'        => 77,
			'post_type' => 'attachment',
		);

		$page_context = new class() {
			public function get_context() {
				return array(
					'robots' => 'noindex, noarchive',
				);
			}
		};

		$settings = new class() {
			public function media_x_robots_headers_enabled() {
				return true;
			}
		};

		$service = new Lightweight_SEO_Header_Service( $page_context, $settings );

		$this->assertSame( 'noindex, noarchive', $service->get_x_robots_tag() );
	}

	public function test_get_x_robots_tag_flags_direct_document_requests(): void {
		$_SERVER['REQUEST_URI'] = '/files/seo-guide.pdf';

		$page_context = new class() {
			public function get_context() {
				return array();
			}
		};

		$settings = new class() {
			public function media_x_robots_headers_enabled() {
				return true;
			}
		};

		$service = new Lightweight_SEO_Header_Service( $page_context, $settings );

		$this->assertSame( 'noindex, noarchive', $service->get_x_robots_tag() );
	}

	public function test_get_x_robots_tag_does_not_flag_direct_image_requests(): void {
		$_SERVER['REQUEST_URI'] = '/uploads/hero-image.jpg';

		$page_context = new class() {
			public function get_context() {
				return array();
			}
		};

		$settings = new class() {
			public function media_x_robots_headers_enabled() {
				return true;
			}
		};

		$service = new Lightweight_SEO_Header_Service( $page_context, $settings );

		$this->assertSame( '', $service->get_x_robots_tag() );
	}

	public function test_get_x_robots_tag_does_not_flag_direct_video_requests(): void {
		$_SERVER['REQUEST_URI'] = '/videos/demo.mp4';

		$page_context = new class() {
			public function get_context() {
				return array();
			}
		};

		$settings = new class() {
			public function media_x_robots_headers_enabled() {
				return true;
			}
		};

		$service = new Lightweight_SEO_Header_Service( $page_context, $settings );

		$this->assertSame( '', $service->get_x_robots_tag() );
	}
}
