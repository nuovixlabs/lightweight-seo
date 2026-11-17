<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-page-context-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOPageContextServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_authors;
		global $lightweight_seo_test_query_state;
		global $wp;

		$lightweight_seo_test_query_state = array(
			'is_singular'       => false,
			'is_single'         => false,
			'is_author'         => false,
			'is_home'           => false,
			'is_front_page'     => false,
			'is_category'       => false,
			'is_tag'            => false,
			'is_tax'            => false,
			'is_archive'        => false,
			'is_search'         => false,
			'queried_object_id' => 0,
			'search_query'      => '',
			'permalink'         => 'https://example.com/default-permalink',
			'title'             => 'Test Title',
			'thumbnail_url'     => '',
			'current_request'   => '',
			'queried_object'    => null,
		);
		$lightweight_seo_test_authors     = array(
			17 => array(
				'display_name' => 'Author Name',
				'description'  => 'Author biography',
			),
		);
		$lightweight_seo_test_posts       = array();

		$wp                     = (object) array(
			'request' => '',
		);
		$_SERVER['REQUEST_URI'] = '/';
	}

	public function test_singular_context_supports_canonical_and_granular_robots_directives(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular']       = true;
		$lightweight_seo_test_query_state['is_single']         = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 42;
		$lightweight_seo_test_query_state['permalink']         = 'https://example.com/original-post';
		$lightweight_seo_test_query_state['title']             = 'Original Post';

		$post_meta = new class() {
			public function get_all( $post_id ) {
				return array(
					'seo_title'             => '',
					'seo_description'       => '',
					'seo_keywords'          => '',
					'seo_canonical_url'     => 'https://example.com/canonical-post',
					'seo_noindex'           => '1',
					'seo_nofollow'          => '0',
					'seo_noarchive'         => '1',
					'seo_nosnippet'         => '0',
					'seo_max_image_preview' => 'standard',
					'social_title'          => '',
					'social_description'    => '',
					'social_image'          => '',
					'social_image_id'       => 0,
				);
			}

			public function get_social_image_url( $post_id ) {
				return '';
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

		$service = new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( true ), $post_meta, $archive_meta );
		$context = $service->get_context();

		$this->assertSame( 'Original Post – Test Site', $context['document_title'] );
		$this->assertSame( 'https://example.com/canonical-post', $context['canonical_url'] );
		$this->assertTrue( $context['canonical_custom'] );
		$this->assertSame( 'noindex, noarchive, max-image-preview:standard', $context['robots'] );
		$this->assertSame( 'https://example.com/canonical-post', $context['og_url'] );
	}

	public function test_search_results_can_default_to_noindex(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_search']    = true;
		$lightweight_seo_test_query_state['search_query'] = 'seo plugin';
		$_SERVER['REQUEST_URI']                           = '/?s=seo%20plugin';

		$post_meta = new class() {
			public function get_all( $post_id ) {
				return array();
			}

			public function get_social_image_url( $post_id ) {
				return '';
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

		$service = new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( false ), $post_meta, $archive_meta );
		$context = $service->get_context();

		$this->assertSame( 'Search Results for "seo plugin" – Test Site', $context['document_title'] );
		$this->assertSame( 'https://example.com/?s=seo%20plugin', $context['canonical_url'] );
		$this->assertSame( 'noindex, max-image-preview:large', $context['robots'] );
	}

	public function test_paginated_home_preserves_current_request_url(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_home'] = true;
		$_SERVER['REQUEST_URI']                      = '/page/2/';

		$post_meta = new class() {
			public function get_all( $post_id ) {
				return array();
			}

			public function get_social_image_url( $post_id ) {
				return '';
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

		$service = new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( true ), $post_meta, $archive_meta );
		$context = $service->get_context();

		$this->assertSame( 'https://example.com/page/2/', $context['canonical_url'] );
		$this->assertSame( 'https://example.com/page/2/', $context['og_url'] );
	}

	public function test_paginated_search_keeps_search_and_pagination_query_args_only(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_search']    = true;
		$lightweight_seo_test_query_state['search_query'] = 'seo plugin';
		$_SERVER['REQUEST_URI']                           = '/?s=seo%20plugin&paged=2&utm_source=newsletter&fbclid=abc123';

		$post_meta = new class() {
			public function get_all( $post_id ) {
				return array();
			}

			public function get_social_image_url( $post_id ) {
				return '';
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

		$service = new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( true ), $post_meta, $archive_meta );
		$context = $service->get_context();

		$this->assertSame( 'https://example.com/?s=seo%20plugin&paged=2', $context['canonical_url'] );
		$this->assertSame( 'https://example.com/?s=seo%20plugin&paged=2', $context['og_url'] );
	}

	public function test_home_canonical_strips_tracking_query_args(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_home'] = true;
		$_SERVER['REQUEST_URI']                      = '/?utm_source=newsletter&fbclid=abc123';

		$post_meta = new class() {
			public function get_all( $post_id ) {
				return array();
			}

			public function get_social_image_url( $post_id ) {
				return '';
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

		$service = new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( true ), $post_meta, $archive_meta );
		$context = $service->get_context();

		$this->assertSame( 'https://example.com/', $context['canonical_url'] );
		$this->assertSame( 'https://example.com/', $context['og_url'] );
	}

	public function test_term_archive_uses_object_level_overrides(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_category']    = true;
		$lightweight_seo_test_query_state['queried_object'] = (object) array(
			'term_id'     => 23,
			'taxonomy'    => 'category',
			'name'        => 'News',
			'description' => 'Default term description',
		);

		$post_meta = new class() {
			public function get_all( $post_id ) {
				return array();
			}

			public function get_social_image_url( $post_id ) {
				return '';
			}
		};

		$archive_meta = new class() {
			public function get_term_all( $term_id ) {
				return array(
					'seo_title'             => 'SEO News Archive',
					'seo_description'       => 'Custom archive description',
					'seo_canonical_url'     => 'https://example.com/news/',
					'seo_noindex'           => '1',
					'seo_nofollow'          => '0',
					'seo_noarchive'         => '0',
					'seo_nosnippet'         => '1',
					'seo_max_image_preview' => 'standard',
				);
			}

			public function get_user_all( $user_id ) {
				return array();
			}
		};

		$service = new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( true ), $post_meta, $archive_meta );
		$context = $service->get_context();

		$this->assertSame( 'SEO News Archive', $context['document_title'] );
		$this->assertSame( 'Custom archive description', $context['description'] );
		$this->assertSame( 'https://example.com/news/', $context['canonical_url'] );
		$this->assertSame( 'noindex, nosnippet, max-image-preview:standard', $context['robots'] );
		$this->assertSame( 'https://example.com/news/', $context['og_url'] );
	}

	public function test_author_archive_uses_author_overrides(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_author']         = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 17;
		$lightweight_seo_test_query_state['queried_object']    = (object) array(
			'ID'           => 17,
			'display_name' => 'Author Name',
			'description'  => 'Author biography',
		);

		$post_meta = new class() {
			public function get_all( $post_id ) {
				return array();
			}

			public function get_social_image_url( $post_id ) {
				return '';
			}
		};

		$archive_meta = new class() {
			public function get_term_all( $term_id ) {
				return array();
			}

			public function get_user_all( $user_id ) {
				return array(
					'seo_title'             => '',
					'seo_description'       => 'Custom author summary',
					'seo_canonical_url'     => '',
					'seo_noindex'           => '0',
					'seo_nofollow'          => '1',
					'seo_noarchive'         => '1',
					'seo_nosnippet'         => '0',
					'seo_max_image_preview' => '',
				);
			}
		};

		$service = new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( true ), $post_meta, $archive_meta );
		$context = $service->get_context();

		$this->assertSame( 'Author Name – Test Site', $context['document_title'] );
		$this->assertSame( 'Custom author summary', $context['description'] );
		$this->assertSame( 'https://example.com/author/17', $context['canonical_url'] );
		$this->assertSame( 'nofollow, noarchive, max-image-preview:large', $context['robots'] );
	}

	public function test_attachment_pages_default_to_noindex_when_enabled(): void {
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular']       = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 88;
		$lightweight_seo_test_query_state['permalink']         = 'https://example.com/media/attachment-page/';
		$lightweight_seo_test_query_state['title']             = 'Attachment Page';
		$lightweight_seo_test_posts[88]                        = (object) array(
			'ID'        => 88,
			'post_type' => 'attachment',
			'permalink' => 'https://example.com/media/attachment-page/',
		);

		$post_meta = new class() {
			public function get_all( $post_id ) {
				return array(
					'seo_title'             => '',
					'seo_description'       => '',
					'seo_keywords'          => '',
					'seo_canonical_url'     => '',
					'seo_noindex'           => '0',
					'seo_nofollow'          => '0',
					'seo_noarchive'         => '0',
					'seo_nosnippet'         => '0',
					'seo_max_image_preview' => '',
					'social_title'          => '',
					'social_description'    => '',
					'social_image'          => '',
					'social_image_id'       => 0,
				);
			}

			public function get_social_image_url( $post_id ) {
				return '';
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

		$service = new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( true ), $post_meta, $archive_meta );
		$context = $service->get_context();

		$this->assertSame( 'noindex, max-image-preview:large', $context['robots'] );
		$this->assertSame( 'https://example.com/media/attachment-page/', $context['canonical_url'] );
	}

	public function test_static_front_page_uses_home_title_format_with_singular_context(): void {
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_front_page']     = true;
		$lightweight_seo_test_query_state['is_singular']       = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 99;
		$lightweight_seo_test_query_state['permalink']         = 'https://example.com/';
		$lightweight_seo_test_query_state['title']             = 'Homepage';
		$lightweight_seo_test_posts[99]                        = (object) array(
			'ID'          => 99,
			'post_type'   => 'page',
			'post_status' => 'publish',
			'permalink'   => 'https://example.com/',
		);

		$post_meta = new class() {
			public function get_all( $post_id ) {
				return array(
					'seo_title'             => '',
					'seo_description'       => '',
					'seo_keywords'          => '',
					'seo_canonical_url'     => '',
					'seo_noindex'           => '0',
					'seo_nofollow'          => '0',
					'seo_noarchive'         => '0',
					'seo_nosnippet'         => '0',
					'seo_max_image_preview' => '',
					'social_title'          => '',
					'social_description'    => '',
					'social_image'          => '',
					'social_image_id'       => 0,
				);
			}

			public function get_social_image_url( $post_id ) {
				return '';
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

		$service = new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( true ), $post_meta, $archive_meta );
		$context = $service->get_context();

		$this->assertSame( 'Test Site – Test Tagline', $context['document_title'] );
		$this->assertSame( 'Test Site – Test Tagline', $context['og_title'] );
		$this->assertSame( 'https://example.com/', $context['canonical_url'] );
	}

	public function test_singular_context_uses_normalized_excerpt_and_image_metadata(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular']                = true;
		$lightweight_seo_test_query_state['is_single']                  = true;
		$lightweight_seo_test_query_state['queried_object_id']          = 42;
		$lightweight_seo_test_posts[42]                                 = (object) array(
			'ID'            => 42,
			'post_type'     => 'post',
			'post_excerpt'  => '<strong>Useful</strong>  excerpt',
			'post_content'  => 'Fallback content',
			'thumbnail_id'  => 10,
			'thumbnail_url' => 'https://example.com/hero.jpg',
		);
		$lightweight_seo_test_posts[10]                                 = (object) array(
			'metadata' => array(
				'width'  => 1600,
				'height' => 900,
			),
		);
		$lightweight_seo_test_post_meta[10]['_wp_attachment_image_alt'] = 'Hero image';

		$post_meta = new class() {
			public function get_all( $post_id ) {
				return array();
			}

			public function get_social_image_url( $post_id ) {
				return '';
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

		$context = ( new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( true ), $post_meta, $archive_meta ) )->get_context();

		$this->assertSame( 'Useful excerpt', $context['description'] );
		$this->assertSame( 'Hero image', $context['og_image_alt'] );
		$this->assertSame( 1600, $context['og_image_width'] );
		$this->assertSame( 900, $context['og_image_height'] );
		$this->assertSame( 'summary_large_image', $context['twitter_card'] );
	}

	public function test_404_context_suppresses_canonical_and_social_values(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_404'] = true;

		$post_meta = new class() {
			public function get_all( $post_id ) {
				return array();
			}

			public function get_social_image_url( $post_id ) {
				return '';
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

		$context = ( new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( true ), $post_meta, $archive_meta ) )->get_context();

		$this->assertSame( '', $context['canonical_url'] );
		$this->assertSame( '', $context['og_title'] );
		$this->assertSame( '', $context['og_image'] );
		$this->assertStringContainsString( 'noindex', $context['robots'] );
	}

	public function test_content_fallback_description_is_summarized_not_repeated_in_full(): void {
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular']       = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 42;
		$lightweight_seo_test_posts[42]                        = (object) array(
			'ID'           => 42,
			'post_type'    => 'post',
			'post_excerpt' => '',
			'post_content' => '<p>' . str_repeat( 'A long article sentence. ', 400 ) . '</p>',
		);

		$service = new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( false ), $this->get_post_meta_stub(), $this->get_archive_meta_stub() );
		$context = $service->get_context();

		$this->assertLessThanOrEqual( 161, Lightweight_SEO_Text::length( $context['description'] ) );
		$this->assertStringEndsWith( '…', $context['description'] );
		$this->assertSame( $context['description'], $context['og_description'] );
	}

	public function test_editor_written_description_is_never_shortened(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular']       = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 42;
		$description = trim( str_repeat( 'Deliberately long editor copy. ', 12 ) );

		$service = new Lightweight_SEO_Page_Context_Service(
			$this->get_settings_stub( false ),
			$this->get_post_meta_stub( array( 'seo_description' => $description ) ),
			$this->get_archive_meta_stub()
		);

		$this->assertSame( $description, $service->get_context()['description'] );
	}

	public function test_custom_seo_title_resolves_template_tokens(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular']       = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 42;
		$lightweight_seo_test_query_state['title']             = 'Original Post';

		$service = new Lightweight_SEO_Page_Context_Service(
			$this->get_settings_stub( false ),
			$this->get_post_meta_stub( array( 'seo_title' => '%title% %sep% Imported Suffix' ) ),
			$this->get_archive_meta_stub()
		);
		$context = $service->get_context();

		$this->assertSame( 'Original Post – Imported Suffix', $context['document_title'] );
		$this->assertSame( 'Original Post', $context['object_title'] );
	}

	public function test_paginated_term_archive_keeps_a_self_referencing_canonical(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_category']    = true;
		$lightweight_seo_test_query_state['is_archive']     = true;
		$lightweight_seo_test_query_state['query_vars']     = array( 'paged' => 3 );
		$lightweight_seo_test_query_state['queried_object'] = (object) array(
			'term_id'     => 5,
			'name'        => 'News',
			'taxonomy'    => 'category',
			'description' => '',
		);
		$_SERVER['REQUEST_URI']                             = '/category/news/page/3/?utm_source=newsletter';
		$GLOBALS['wp_rewrite']                              = new class() {
			public $pagination_base = 'page';

			public function using_permalinks() {
				return true;
			}
		};

		$service = new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( false ), $this->get_post_meta_stub(), $this->get_archive_meta_stub() );
		$context = $service->get_context();

		unset( $GLOBALS['wp_rewrite'] );

		$this->assertSame( 'https://example.com/term/5/page/3/', $context['canonical_url'] );
		$this->assertFalse( $context['canonical_custom'] );
	}

	public function test_static_posts_page_uses_its_own_title_not_the_homepage_title(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_options['page_for_posts']    = 77;
		$lightweight_seo_test_posts[77]                    = (object) array(
			'ID'        => 77,
			'permalink' => 'https://example.com/blog/',
		);
		$lightweight_seo_test_query_state['is_home']       = true;
		$lightweight_seo_test_query_state['is_front_page'] = false;
		$lightweight_seo_test_query_state['title']         = 'Blog';
		$_SERVER['REQUEST_URI']                            = '/blog/';

		$service = new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( false ), $this->get_post_meta_stub(), $this->get_archive_meta_stub() );
		$context = $service->get_context();

		unset( $lightweight_seo_test_options['page_for_posts'] );

		$this->assertSame( 'Blog – Test Site', $context['document_title'] );
		$this->assertSame( 'https://example.com/blog/', $context['canonical_url'] );
		$this->assertSame( '', $context['description'] );
	}

	public function test_archive_url_on_a_subdirectory_install_does_not_repeat_the_home_path(): void {
		global $lightweight_seo_test_home_url;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_home_url                  = 'https://example.com/blog';
		$lightweight_seo_test_query_state['is_archive'] = true;
		$lightweight_seo_test_query_state['title']      = 'May 2024';
		$_SERVER['REQUEST_URI']                         = '/blog/2024/05/';

		$service = new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( false ), $this->get_post_meta_stub(), $this->get_archive_meta_stub() );
		$context = $service->get_context();

		$lightweight_seo_test_home_url = 'https://example.com';

		$this->assertSame( 'https://example.com/blog/2024/05/', $context['canonical_url'] );
		$this->assertSame( 'https://example.com/blog/2024/05/', $context['og_url'] );
	}

	private function get_post_meta_stub( array $meta = array() ) {
		return new class( $meta ) {
			private $meta;

			public function __construct( array $meta ) {
				$this->meta = $meta;
			}

			public function get_all( $post_id ) {
				return $this->meta;
			}

			public function get_social_image_url( $post_id ) {
				return '';
			}
		};
	}

	private function get_archive_meta_stub() {
		return new class() {
			public function get_term_all( $term_id ) {
				return array();
			}

			public function get_user_all( $user_id ) {
				return array();
			}
		};
	}

	/**
	 * Build the real settings service over a fixed set of stored values.
	 *
	 * @param mixed $unused    Kept for the tests written against the retired keywords switch.
	 * @param array $overrides Stored settings that differ from the defaults.
	 */
	private function get_settings_stub( $unused = null, array $overrides = array() ) {
		return new class( $overrides ) extends Lightweight_SEO_Settings {
			private $overrides;

			public function __construct( array $overrides ) {
				$this->overrides = $overrides;
			}

			public function get_all() {
				return array_merge( $this->get_defaults(), array( 'meta_description' => 'Default description' ), $this->overrides );
			}
		};
	}

	/**
	 * Resolve the context of a singular request for one post.
	 *
	 * @param string $post_type Post type of the queried post.
	 * @param array  $meta      Stored SEO meta of the post.
	 * @param array  $settings  Stored settings that differ from the defaults.
	 */
	private function get_singular_context( $post_type, array $meta, array $settings ) {
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular']       = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 61;
		$lightweight_seo_test_query_state['title']             = 'Harbour at dawn';
		$lightweight_seo_test_posts[61]                        = (object) array(
			'ID'        => 61,
			'post_type' => $post_type,
			'permalink' => 'https://example.com/item/',
		);

		return ( new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( null, $settings ), $this->get_post_meta_stub( $meta ), $this->get_archive_meta_stub() ) )->get_context();
	}

	public function test_a_hidden_post_type_hides_its_items_unless_an_item_chooses_to_show(): void {
		$hidden = array( 'content_types' => array( 'post_types' => array( 'portfolio' => array( 'noindex' => '1' ) ) ) );

		$this->assertSame( 'noindex, max-image-preview:large', $this->get_singular_context( 'portfolio', array(), $hidden )['robots'] );
		$this->assertSame( 'noindex, max-image-preview:large', $this->get_singular_context( 'portfolio', array( 'seo_noindex' => '0' ), $hidden )['robots'] );
		$this->assertSame( 'max-image-preview:large', $this->get_singular_context( 'portfolio', array( 'seo_noindex' => 'index' ), $hidden )['robots'] );
		$this->assertSame( 'max-image-preview:large', $this->get_singular_context( 'post', array(), $hidden )['robots'] );
	}

	public function test_an_item_can_still_hide_itself_in_a_shown_post_type(): void {
		$this->assertSame( 'max-image-preview:large', $this->get_singular_context( 'post', array( 'seo_noindex' => '0' ), array() )['robots'] );
		$this->assertStringStartsWith( 'noindex', $this->get_singular_context( 'post', array( 'seo_noindex' => '1' ), array() )['robots'] );
	}

	public function test_one_attachment_can_be_shown_while_attachment_pages_stay_hidden(): void {
		$this->assertSame( 'noindex, max-image-preview:large', $this->get_singular_context( 'attachment', array(), array() )['robots'] );
		$this->assertSame( 'max-image-preview:large', $this->get_singular_context( 'attachment', array( 'seo_noindex' => 'index' ), array() )['robots'] );
		$this->assertSame( 'max-image-preview:large', $this->get_singular_context( 'attachment', array(), array( 'noindex_attachment_pages' => '0' ) )['robots'] );
	}

	public function test_a_post_type_title_format_applies_only_to_that_type_and_yields_to_an_seo_title(): void {
		$formats = array( 'content_types' => array( 'post_types' => array( 'portfolio' => array( 'title_format' => '%title% %sep% Portfolio' ) ) ) );

		$this->assertSame( 'Harbour at dawn – Portfolio', $this->get_singular_context( 'portfolio', array(), $formats )['document_title'] );
		$this->assertSame( 'Harbour at dawn – Test Site', $this->get_singular_context( 'post', array(), $formats )['document_title'] );
		$this->assertSame( 'Own title', $this->get_singular_context( 'portfolio', array( 'seo_title' => 'Own title' ), $formats )['document_title'] );
	}

	public function test_a_hidden_taxonomy_hides_its_archives_unless_a_term_chooses_to_show(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_tag']         = true;
		$lightweight_seo_test_query_state['is_archive']     = true;
		$lightweight_seo_test_query_state['queried_object'] = (object) array(
			'term_id'     => 9,
			'name'        => 'Monochrome',
			'taxonomy'    => 'post_tag',
			'description' => '',
		);

		$settings = array(
			'content_types' => array(
				'taxonomies' => array(
					'post_tag' => array(
						'noindex'      => '1',
						'title_format' => 'Tagged %title%',
					),
				),
			),
		);
		$hidden   = ( new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( null, $settings ), $this->get_post_meta_stub(), $this->get_archive_meta_stub() ) )->get_context();
		$shown    = ( new Lightweight_SEO_Page_Context_Service(
			$this->get_settings_stub( null, $settings ),
			$this->get_post_meta_stub(),
			new class() {
				public function get_term_all( $term_id ) {
					return array( 'seo_noindex' => 'index' );
				}

				public function get_user_all( $user_id ) {
					return array();
				}
			}
		) )->get_context();

		$this->assertSame( 'noindex, max-image-preview:large', $hidden['robots'] );
		$this->assertSame( 'Tagged Monochrome', $hidden['document_title'] );
		$this->assertSame( 'max-image-preview:large', $shown['robots'] );

		// Another taxonomy keeps the archive title format and stays indexable.
		$lightweight_seo_test_query_state['queried_object']->taxonomy = 'category';
		$category = ( new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( null, $settings ), $this->get_post_meta_stub(), $this->get_archive_meta_stub() ) )->get_context();

		$this->assertSame( 'max-image-preview:large', $category['robots'] );
		$this->assertSame( 'Monochrome – Test Site', $category['document_title'] );
	}

	public function test_author_archives_can_be_hidden_by_default(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_author']         = true;
		$lightweight_seo_test_query_state['is_archive']        = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 17;

		$hidden = ( new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( null, array( 'noindex_author_archives' => '1' ) ), $this->get_post_meta_stub(), $this->get_archive_meta_stub() ) )->get_context();
		$shown  = ( new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub(), $this->get_post_meta_stub(), $this->get_archive_meta_stub() ) )->get_context();

		$this->assertSame( 'noindex, max-image-preview:large', $hidden['robots'] );
		$this->assertSame( 'max-image-preview:large', $shown['robots'] );
	}

	public function test_date_archives_can_be_hidden(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_archive'] = true;
		$lightweight_seo_test_query_state['is_date']    = true;

		$hidden = ( new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( null, array( 'noindex_date_archives' => '1' ) ), $this->get_post_meta_stub(), $this->get_archive_meta_stub() ) )->get_context();
		$shown  = ( new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub(), $this->get_post_meta_stub(), $this->get_archive_meta_stub() ) )->get_context();

		$this->assertSame( 'noindex, max-image-preview:large', $hidden['robots'] );
		$this->assertSame( 'max-image-preview:large', $shown['robots'] );
	}

	public function test_a_hidden_post_type_hides_its_archive_page(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_archive']           = true;
		$lightweight_seo_test_query_state['is_post_type_archive'] = true;
		$lightweight_seo_test_query_state['title']                = 'Portfolio';
		$lightweight_seo_test_query_state['queried_object']       = (object) array( 'name' => 'portfolio' );

		$settings = array(
			'content_types' => array(
				'post_types' => array(
					'portfolio' => array(
						'noindex'      => '1',
						'title_format' => '%title% %sep% Work',
					),
				),
			),
		);
		$hidden   = ( new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( null, $settings ), $this->get_post_meta_stub(), $this->get_archive_meta_stub() ) )->get_context();
		$shown    = ( new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub(), $this->get_post_meta_stub(), $this->get_archive_meta_stub() ) )->get_context();

		$this->assertSame( 'noindex, max-image-preview:large', $hidden['robots'] );
		// The archive page keeps the archive title format; the post type format is for single items.
		$this->assertSame( 'Portfolio – Test Site', $hidden['document_title'] );
		$this->assertSame( 'max-image-preview:large', $shown['robots'] );
	}

	public function test_the_chosen_separator_replaces_the_sep_variable_everywhere(): void {
		global $lightweight_seo_test_query_state;

		$chosen = array( 'title_separator' => '|' );

		$this->assertSame( 'Harbour at dawn – Test Site', $this->get_singular_context( 'post', array(), array( 'title_format' => '%title% %sep% %sitename%' ) )['document_title'] );
		$this->assertSame( 'Harbour at dawn | Test Site', $this->get_singular_context( 'post', array(), $chosen + array( 'title_format' => '%title% %sep% %sitename%' ) )['document_title'] );
		// A format that spells out its own character is left as written.
		$this->assertSame( 'Harbour at dawn – Test Site', $this->get_singular_context( 'post', array(), $chosen )['document_title'] );
		// A page's own SEO and social titles resolve the variable too.
		$own = $this->get_singular_context(
			'post',
			array(
				'seo_title'    => '%title% %sep% Own',
				'social_title' => 'Share %sep% %title%',
			),
			$chosen
		);

		$this->assertSame( 'Harbour at dawn | Own', $own['document_title'] );
		$this->assertSame( 'Share | Harbour at dawn', $own['og_title'] );

		$lightweight_seo_test_query_state['is_singular'] = false;
		$lightweight_seo_test_query_state['is_home']     = true;

		$home = ( new Lightweight_SEO_Page_Context_Service( $this->get_settings_stub( null, $chosen ), $this->get_post_meta_stub(), $this->get_archive_meta_stub() ) )->get_context();

		$this->assertSame( 'Test Site | Test Tagline', $home['document_title'] );
	}

	public function test_the_x_handle_reaches_the_context_with_its_at_sign(): void {
		$this->assertSame( '', $this->get_singular_context( 'post', array(), array() )['twitter_site'] );
		$this->assertSame( '@example', $this->get_singular_context( 'post', array(), array( 'x_handle' => 'example' ) )['twitter_site'] );
		$this->assertSame( '', $this->get_singular_context( 'post', array(), array( 'x_handle' => 'not a handle' ) )['twitter_site'] );
	}
}
