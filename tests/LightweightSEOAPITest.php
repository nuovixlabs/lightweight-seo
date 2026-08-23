<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-module-state.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-module-registry.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-api.php';
require_once __DIR__ . '/fixtures/class-lightweight-seo-insights-fixture.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOAPITest extends TestCase {

	private function create_api() {
		$page_context = new class() {
			public function get_context() {
				return array(
					'document_title' => 'Public title',
					'description'    => 'Public description',
					'canonical_url'  => 'https://example.com/current',
					'robots'         => '',
					'is_404'         => false,
					'keywords'       => 'must not leak',
				);
			}
		};
		$post_meta    = new class() {
			public function get_all( $post_id ) {
				return array(
					'seo_title'         => 'Object title',
					'seo_description'   => 'Object description',
					'seo_canonical_url' => '',
					'seo_noindex'       => '1',
					'social_image'      => 'private implementation detail',
				);
			}
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}
		};
		$archive_meta = new class() {
			public function get_term_all( $term_id ) {
				return array();
			}
			public function get_user_all( $user_id ) {
				return array();
			}
			public function get_supported_taxonomies() {
				return array( 'category' );
			}
		};
		$registry     = new Lightweight_SEO_Module_Registry( new Lightweight_SEO_Module_State() );
		$registry->register(
			'fixture',
			array(
				'name'     => 'Fixture',
				'contexts' => array( 'frontend' ),
			)
		);
		$registry->finalize();

		return new Lightweight_SEO_API( $page_context, $post_meta, $archive_meta, $registry );
	}

	protected function setUp(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_options = array( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME => array() );
		$lightweight_seo_test_posts   = array( 42 => (object) array( 'permalink' => 'https://example.com/post/42' ) );
	}

	public function test_facade_returns_normalized_read_only_facts(): void {
		$api     = $this->create_api();
		$current = $api->get_current_context();
		$object  = $api->get_object_context( 'post', 42 );

		$this->assertSame( '1.0', $api->get_api_version() );
		$this->assertArrayNotHasKey( 'keywords', $current );
		$this->assertSame( 'https://example.com/post/42', $object['canonical_url'] );
		$this->assertFalse( $object['indexable'] );
		$this->assertArrayNotHasKey( 'social_image', $object );
		$this->assertArrayNotHasKey( 'factory', $api->get_modules()['fixture'] );
	}

	public function test_insights_fixture_uses_only_versioned_api(): void {
		$fixture = new Lightweight_SEO_Insights_Fixture();

		$this->assertTrue( $fixture->boot( $this->create_api() ) );
		$this->assertTrue( $fixture->is_ready() );
		$this->assertFalse( ( new Lightweight_SEO_Insights_Fixture() )->boot( null ) );
	}
}
