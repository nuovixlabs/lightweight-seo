<?php

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

	public function test_paginated_search_preserves_query_string_in_current_url(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_search']    = true;
		$lightweight_seo_test_query_state['search_query'] = 'seo plugin';
		$_SERVER['REQUEST_URI']                           = '/?s=seo%20plugin&paged=2';

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

	private function get_settings_stub( $keywords_enabled ) {
		return new class( $keywords_enabled ) {
			private $keywords_enabled;

			public function __construct( $keywords_enabled ) {
				$this->keywords_enabled = $keywords_enabled;
			}

			public function get_all() {
				return array(
					'home_title_format'         => '%sitename% %sep% %tagline%',
					'archive_title_format'      => '%title% %sep% %sitename%',
					'search_title_format'       => 'Search Results for "%search%" %sep% %sitename%',
					'meta_description'          => 'Default description',
					'meta_keywords'             => 'alpha,beta',
					'default_max_image_preview' => 'large',
				);
			}

			public function meta_keywords_enabled() {
				return $this->keywords_enabled;
			}

			public function get_social_image_url() {
				return '';
			}

			public function get_title_format() {
				return '%title% – %sitename%';
			}

			public function get_home_title_format() {
				return '%sitename% %sep% %tagline%';
			}

			public function get_archive_title_format() {
				return '%title% %sep% %sitename%';
			}

			public function get_search_title_format() {
				return 'Search Results for "%search%" %sep% %sitename%';
			}

			public function get_default_max_image_preview() {
				return 'large';
			}

			public function normalize_max_image_preview( $value, $fallback = '' ) {
				$allowed = array( 'none', 'standard', 'large' );

				return in_array( $value, $allowed, true ) ? $value : $fallback;
			}

			public function search_results_noindex_enabled() {
				return true;
			}

			public function attachment_pages_noindex_enabled() {
				return true;
			}
		};
	}
}
