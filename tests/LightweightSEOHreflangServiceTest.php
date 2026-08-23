<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-hreflang-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOHreflangServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_filters;

		$lightweight_seo_test_filters = array();
	}

	public function test_get_hreflang_links_builds_self_and_explicit_object_alternates(): void {
		$settings = new class() {
			public function hreflang_output_enabled() {
				return true;
			}

			public function get_hreflang_mappings() {
				return array(
					array(
						'object_type' => 'post',
						'object_id'   => 42,
						'language'    => 'en-GB',
						'url'         => 'https://uk.example.com/services/seo-audit/',
					),
					array(
						'object_type' => 'post',
						'object_id'   => 42,
						'language'    => 'x-default',
						'url'         => 'https://www.example.com/services/seo-audit/',
					),
				);
			}

			public function hreflang_path_mirroring_enabled() {
				return false;
			}
		};

		$page_context = new class() {
			public function get_context() {
				return array(
					'object_type'   => 'post',
					'object_id'     => 42,
					'canonical_url' => 'https://example.com/services/seo-audit/',
				);
			}
		};

		$service = new Lightweight_SEO_Hreflang_Service( $settings, $page_context );
		$links   = $service->get_hreflang_links();

		$this->assertCount( 3, $links );
		$this->assertSame( 'en-US', $links[0]['hreflang'] );
		$this->assertSame( 'https://example.com/services/seo-audit/', $links[0]['href'] );
		$this->assertSame( 'https://uk.example.com/services/seo-audit/', $links[1]['href'] );
		$this->assertSame( 'x-default', $links[2]['hreflang'] );
	}

	public function test_legacy_path_mirroring_requires_explicit_opt_in(): void {
		$settings = new class() {
			public $enabled = false;
			public function hreflang_output_enabled() {
				return true;
			}
			public function hreflang_path_mirroring_enabled() {
				return $this->enabled;
			}
			public function get_hreflang_mappings() {
				return array(
					array(
						'object_type' => 'mirror',
						'object_id'   => 0,
						'language'    => 'fr-FR',
						'url'         => 'https://fr.example.com',
					),
				);
			}
		};
		$context  = new class() {
			public function get_context() {
				return array( 'canonical_url' => 'https://example.com/about/' );
			}
		};
		$service  = new Lightweight_SEO_Hreflang_Service( $settings, $context );

		$this->assertSame( array(), $service->get_hreflang_links() );
		$settings->enabled = true;
		$links             = $service->get_hreflang_links();
		$this->assertSame( 'https://fr.example.com/about', $links[1]['href'] );
	}

	public function test_local_post_target_requires_reciprocal_mapping(): void {
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_posts[84] = (object) array(
			'ID'        => 84,
			'permalink' => 'https://example.com/fr/page/',
		);
		$settings                       = new class() {
			public $reciprocal = false;
			public function hreflang_output_enabled() {
				return true;
			}
			public function hreflang_path_mirroring_enabled() {
				return false;
			}
			public function get_manual_redirect_rules() {
				return array();
			}
			public function get_hreflang_mappings() {
				$mappings = array(
					array(
						'object_type' => 'post',
						'object_id'   => 42,
						'language'    => 'fr-FR',
						'url'         => 'https://example.com/fr/page/',
					),
				);

				if ( $this->reciprocal ) {
					$mappings[] = array(
						'object_type' => 'post',
						'object_id'   => 84,
						'language'    => 'en-US',
						'url'         => 'https://example.com/page/',
					);
				}

				return $mappings;
			}
		};
		$context                        = new class() {
			public function get_context() {
				return array(
					'object_type'   => 'post',
					'object_id'     => 42,
					'canonical_url' => 'https://example.com/page/',
				);
			}
		};
		$service                        = new Lightweight_SEO_Hreflang_Service( $settings, $context );

		$this->assertSame( array(), $service->get_hreflang_links() );
		$settings->reciprocal = true;
		$this->assertCount( 2, $service->get_hreflang_links() );
	}

	public function test_additional_multilingual_provider_can_claim_output_ownership(): void {
		global $lightweight_seo_test_filters;

		$lightweight_seo_test_filters['lightweight_seo_multilingual_provider_active'] = static function () {
			return true;
		};

		$service = new Lightweight_SEO_Hreflang_Service( new stdClass(), new stdClass() );

		$this->assertTrue( $service->multilingual_provider_active() );
	}
}
