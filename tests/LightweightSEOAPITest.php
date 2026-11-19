<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-module-state.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-module-registry.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
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

		return new Lightweight_SEO_API( $page_context, $post_meta, $archive_meta, $registry, new Lightweight_SEO_Settings() );
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

	public function test_unpublished_and_protected_posts_are_never_reported_indexable(): void {
		global $lightweight_seo_test_posts;

		$post_meta    = new class() {
			public function get_all( $post_id ) {
				return array();
			}
		};
		$archive_meta = new class() {
		};
		$api          = new Lightweight_SEO_API(
			new class() {
			},
			$post_meta,
			$archive_meta,
			new Lightweight_SEO_Module_Registry( new Lightweight_SEO_Module_State() ),
			new Lightweight_SEO_Settings()
		);

		$lightweight_seo_test_posts = array(
			7  => (object) array(
				'post_status'  => 'draft',
				'post_content' => 'Unpublished body.',
				'permalink'    => 'https://example.com/?p=7',
			),
			8  => (object) array(
				'post_status'   => 'publish',
				'post_password' => 'secret',
				'post_content'  => 'Protected body.',
				'permalink'     => 'https://example.com/protected/',
			),
			9  => (object) array(
				'post_status'  => 'publish',
				'post_excerpt' => '',
				'post_content' => str_repeat( 'Public body text. ', 60 ),
				'permalink'    => 'https://example.com/public/',
			),
			10 => (object) array(
				'post_status'  => 'publish',
				'post_excerpt' => '',
				'post_content' => '<p>Part one.</p><!--nextpage--><p>Part two.</p>',
				'permalink'    => 'https://example.com/paged/',
			),
		);

		$draft     = $api->get_object_context( 'post', 7 );
		$protected = $api->get_object_context( 'post', 8 );
		$public    = $api->get_object_context( 'post', 9 );

		$this->assertFalse( $draft['indexable'] );
		$this->assertSame( '', $draft['description'], 'Unpublished content must not leak through the facade.' );
		$this->assertFalse( $protected['indexable'] );
		$this->assertSame( '', $protected['description'] );
		$this->assertTrue( $public['indexable'] );
		$this->assertStringEndsWith( '…', $public['description'] );
		$this->assertLessThanOrEqual( 161, Lightweight_SEO_Text::length( $public['description'] ) );
		$this->assertSame( 'Part one. Part two.', $api->get_object_context( 'post', 10 )['description'] );
	}

	public function test_object_context_fills_the_variables_of_a_title_and_description(): void {
		global $lightweight_seo_test_authors;
		global $lightweight_seo_test_categories;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;
		global $lightweight_seo_test_terms;
		global $lightweight_seo_test_users;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'title_separator' => '|' );
		$lightweight_seo_test_query_state['title']                   = 'Harbour at dawn';
		$lightweight_seo_test_query_state['post_author']             = 17;
		$lightweight_seo_test_authors                                = array( 17 => array( 'display_name' => 'Ada Lovelace' ) );
		$lightweight_seo_test_categories                             = array( 42 => array( (object) array( 'name' => 'Travel' ) ) );
		$lightweight_seo_test_posts[42]                              = (object) array(
			'ID'          => 42,
			'post_type'   => 'post',
			'post_status' => 'publish',
			'permalink'   => 'https://example.com/post/42',
		);
		$lightweight_seo_test_terms                                  = array(
			7 => (object) array(
				'term_id'  => 7,
				'name'     => 'Travel',
				'taxonomy' => 'category',
			),
		);
		$lightweight_seo_test_users                                  = array( 17 => (object) array( 'display_name' => 'Ada Lovelace' ) );

		$meta = array(
			'post' => array(
				'seo_title'       => '%title% %sep% %page% %sep% %category% %sep% %sitename%',
				'seo_description' => 'By %author% in %year%.',
			),
			'term' => array( 'seo_title' => '%category% stories %sep% %sitename%' ),
			'user' => array( 'seo_title' => 'Posts by %author% %sep% %page%' ),
		);

		$post_meta    = new class( $meta['post'] ) {
			private $meta;

			public function __construct( $meta ) {
				$this->meta = $meta;
			}

			public function get_all( $post_id ) {
				return $this->meta;
			}

			public function get_supported_post_types() {
				return array( 'post' );
			}
		};
		$archive_meta = new class( $meta ) {
			private $meta;

			public function __construct( $meta ) {
				$this->meta = $meta;
			}

			public function get_term_all( $term_id ) {
				return $this->meta['term'];
			}

			public function get_user_all( $user_id ) {
				return $this->meta['user'];
			}

			public function get_supported_taxonomies() {
				return array( 'category' );
			}
		};
		$registry     = new Lightweight_SEO_Module_Registry( new Lightweight_SEO_Module_State() );
		$registry->finalize();

		$api  = new Lightweight_SEO_API( null, $post_meta, $archive_meta, $registry, new Lightweight_SEO_Settings() );
		$post = $api->get_object_context( 'post', 42 );

		// The page variable has no value for an object, and leaves with one separator.
		$this->assertSame( 'Harbour at dawn | Travel | Test Site', $post['title'] );
		$this->assertSame( 'By Ada Lovelace in ' . gmdate( 'Y' ) . '.', $post['description'] );
		$this->assertSame( 'Travel stories | Test Site', $api->get_object_context( 'term', 7 )['title'] );
		$this->assertSame( 'Posts by Ada Lovelace', $api->get_object_context( 'user', 17 )['title'] );

		$lightweight_seo_test_authors    = array();
		$lightweight_seo_test_categories = array();
		$lightweight_seo_test_terms      = array();
		$lightweight_seo_test_users      = array();
	}

	public function test_object_context_follows_the_content_type_unless_the_object_chooses(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_terms;
		global $lightweight_seo_test_users;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'noindex_author_archives' => '1',
			'content_types'           => array(
				'post_types' => array( 'portfolio' => array( 'noindex' => '1' ) ),
				'taxonomies' => array( 'post_tag' => array( 'noindex' => '1' ) ),
			),
		);
		$lightweight_seo_test_posts                                  = array(
			11 => (object) array(
				'post_type' => 'portfolio',
				'permalink' => 'https://example.com/portfolio/hidden/',
			),
			12 => (object) array(
				'post_type' => 'portfolio',
				'permalink' => 'https://example.com/portfolio/shown/',
			),
			13 => (object) array(
				'post_type' => 'post',
				'permalink' => 'https://example.com/post/',
			),
		);
		$lightweight_seo_test_terms                                  = array(
			21 => (object) array(
				'name'     => 'Monochrome',
				'taxonomy' => 'post_tag',
			),
			22 => (object) array(
				'name'     => 'News',
				'taxonomy' => 'category',
			),
		);
		$lightweight_seo_test_users                                  = array( 31 => (object) array( 'display_name' => 'Author' ) );

		$post_meta    = new class() {
			public function get_all( $post_id ) {
				return 12 === $post_id ? array( 'seo_noindex' => 'index' ) : array( 'seo_noindex' => '0' );
			}
		};
		$archive_meta = new class() {
			public function get_term_all( $term_id ) {
				return array();
			}
			public function get_user_all( $user_id ) {
				return array();
			}
		};
		$api          = new Lightweight_SEO_API(
			new class() {
			},
			$post_meta,
			$archive_meta,
			new Lightweight_SEO_Module_Registry( new Lightweight_SEO_Module_State() ),
			new Lightweight_SEO_Settings()
		);

		$this->assertFalse( $api->is_indexable( 'post', 11 ) );
		$this->assertSame( 'noindex', $api->get_object_context( 'post', 11 )['robots'] );
		$this->assertTrue( $api->is_indexable( 'post', 12 ) );
		$this->assertTrue( $api->is_indexable( 'post', 13 ) );
		$this->assertFalse( $api->is_indexable( 'term', 21 ) );
		$this->assertTrue( $api->is_indexable( 'term', 22 ) );
		$this->assertFalse( $api->is_indexable( 'user', 31 ) );

		$lightweight_seo_test_terms = array();
		$lightweight_seo_test_users = array();
	}
}
