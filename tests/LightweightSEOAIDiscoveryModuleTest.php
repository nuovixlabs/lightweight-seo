<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-ai-discovery-module.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOAIDiscoveryModuleTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_filters;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_filters = array();
		$lightweight_seo_test_options = array( 'blog_public' => 1 );
		$lightweight_seo_test_posts   = array();
	}

	public function test_virtual_robots_separates_search_and_training_policies(): void {
		$module = new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false ), null, 'admin' );
		$output = $module->filter_robots_txt( "User-agent: *\nDisallow: /wp-admin/\n", true );

		$this->assertStringContainsString( "User-agent: OAI-SearchBot\nAllow: /", $output );
		$this->assertStringContainsString( "User-agent: Claude-SearchBot\nAllow: /", $output );
		$this->assertStringContainsString( "User-agent: GPTBot\nDisallow: /", $output );
		$this->assertStringContainsString( "User-agent: Google-Extended\nDisallow: /", $output );
		$this->assertSame( 1, substr_count( $module->filter_robots_txt( $output, true ), 'User-agent: OAI-SearchBot' ) );
	}

	public function test_physical_robots_file_prevents_virtual_changes_and_is_reported(): void {
		global $lightweight_seo_test_filters;

		$path = tempnam( sys_get_temp_dir(), 'lightweight-seo-robots-' );
		$lightweight_seo_test_filters['lightweight_seo_physical_robots_path'] = static function () use ( $path ) {
			return $path;
		};
		$module = new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false ), null, 'admin' );
		$output = "User-agent: *\nDisallow:\n";

		$this->assertSame( $output, $module->filter_robots_txt( $output, true ) );
		$this->assertTrue( $module->get_conflicts( $output )['physical_robots'] );

		unlink( $path );
	}

	public function test_llms_txt_contains_only_valid_curated_page_summaries(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_posts = array(
			11 => $this->post( 11, 'publish', '', 'https://example.com/guide/' ),
			12 => $this->post( 12, 'draft', '', 'https://example.com/draft/' ),
			13 => $this->post( 13, 'publish', 'secret', 'https://example.com/private/' ),
			14 => $this->post( 14, 'publish', '', 'https://example.com/redirected/' ),
			15 => $this->post( 15, 'publish', '', 'https://external.example/page/' ),
			16 => $this->post( 16, 'publish', '', 'https://example.com/noindex/' ),
		);
		$lightweight_seo_test_options['lightweight_seo_generated_redirect_rules'] = array(
			array(
				'source' => '/redirected',
				'target' => '/guide',
				'status' => 301,
			),
		);
		$api    = new class() {
			public function get_object_context( $type, $id ) {
				$contexts = array(
					11 => array(
						'title'         => 'Public [Guide]',
						'description'   => '<b>A concise public summary.</b>',
						'canonical_url' => 'https://example.com/guide/',
						'indexable'     => true,
					),
					12 => array(
						'title'         => 'Draft',
						'description'   => 'Draft',
						'canonical_url' => 'https://example.com/draft/',
						'indexable'     => true,
					),
					13 => array(
						'title'         => 'Private',
						'description'   => 'Private',
						'canonical_url' => 'https://example.com/private/',
						'indexable'     => true,
					),
					14 => array(
						'title'         => 'Redirected',
						'description'   => 'Redirected',
						'canonical_url' => 'https://example.com/redirected/',
						'indexable'     => true,
					),
					15 => array(
						'title'         => 'External',
						'description'   => 'External',
						'canonical_url' => 'https://external.example/page/',
						'indexable'     => true,
					),
					16 => array(
						'title'         => 'Noindex',
						'description'   => 'Noindex',
						'canonical_url' => 'https://example.com/noindex/',
						'indexable'     => false,
					),
				);

				return $contexts[ $id ] ?? array();
			}
		};
		$module = new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false, array( 11, 12, 13, 14, 15, 16 ) ), $api, 'admin' );
		$output = $module->build_llms_txt();

		$this->assertStringContainsString( '[Public \\[Guide\\]](https://example.com/guide/): A concise public summary.', $output );
		$this->assertStringContainsString( 'Google Search ignores llms.txt', $output );
		$this->assertStringNotContainsString( 'Draft', $output );
		$this->assertStringNotContainsString( 'Private', $output );
		$this->assertStringNotContainsString( 'Redirected', $output );
		$this->assertStringNotContainsString( 'External', $output );
		$this->assertStringNotContainsString( 'Noindex', $output );
	}

	public function test_registry_rejects_invalid_extension_definitions(): void {
		global $lightweight_seo_test_filters;

		$lightweight_seo_test_filters['lightweight_seo_ai_crawler_registry'] = static function ( $registry ) {
			$registry['ValidBot']    = array(
				'vendor'  => 'Test',
				'purpose' => 'search',
			);
			$registry['Invalid Bot'] = array(
				'vendor'  => 'Test',
				'purpose' => 'search',
			);
			$registry['UnknownBot']  = array(
				'vendor'  => 'Test',
				'purpose' => 'unknown',
			);

			return $registry;
		};
		$registry = ( new Lightweight_SEO_AI_Discovery_Module( $this->settings(), null, 'admin' ) )->get_crawler_registry();

		$this->assertArrayHasKey( 'ValidBot', $registry );
		$this->assertArrayNotHasKey( 'Invalid Bot', $registry );
		$this->assertArrayNotHasKey( 'UnknownBot', $registry );
	}

	public function test_existing_llms_rewrite_rule_is_reported_as_an_endpoint_conflict(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options['rewrite_rules'] = array( '^llms\.txt/?$' => 'index.php?third_party_llms=1' );
		$module                                        = new Lightweight_SEO_AI_Discovery_Module( $this->settings(), null, 'admin' );

		$this->assertTrue( $module->get_conflicts( '' )['llms_endpoint'] );
	}

	private function settings( $search = true, $training = false, $ids = array() ) {
		return new class( $search, $training, $ids ) {
			private $search;
			private $training;
			private $ids;

			public function __construct( $search, $training, $ids ) {
				$this->search   = $search;
				$this->training = $training;
				$this->ids      = $ids;
			}

			public function ai_search_crawlers_enabled() {
				return $this->search;
			}

			public function ai_training_crawlers_enabled() {
				return $this->training;
			}

			public function llms_txt_enabled() {
				return true;
			}

			public function get_llms_post_ids() {
				return $this->ids;
			}

			public function get_manual_redirect_rules() {
				return array();
			}

			public function schema_output_enabled() {
				return true;
			}
		};
	}

	private function post( $id, $status, $password, $url ) {
		return (object) array(
			'ID'            => $id,
			'post_type'     => 'page',
			'post_status'   => $status,
			'post_password' => $password,
			'permalink'     => $url,
		);
	}
}
