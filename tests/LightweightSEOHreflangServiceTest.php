<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-hreflang-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOHreflangServiceTest extends TestCase {

	public function test_get_hreflang_links_builds_self_and_alternates_from_current_path(): void {
		$settings = new class() {
			public function hreflang_output_enabled() {
				return true;
			}

			public function get_hreflang_mappings() {
				return array(
					array(
						'language' => 'en-GB',
						'url'      => 'https://uk.example.com',
					),
					array(
						'language' => 'x-default',
						'url'      => 'https://www.example.com',
					),
				);
			}
		};

		$page_context = new class() {
			public function get_context() {
				return array(
					'canonical_url' => 'https://example.com/services/seo-audit/',
				);
			}
		};

		$service = new Lightweight_SEO_Hreflang_Service( $settings, $page_context );
		$links   = $service->get_hreflang_links();

		$this->assertCount( 3, $links );
		$this->assertSame( 'en-US', $links[0]['hreflang'] );
		$this->assertSame( 'https://example.com/services/seo-audit/', $links[0]['href'] );
		$this->assertSame( 'https://uk.example.com/services/seo-audit', $links[1]['href'] );
		$this->assertSame( 'x-default', $links[2]['hreflang'] );
	}
}
