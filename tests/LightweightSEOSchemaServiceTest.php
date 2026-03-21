<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-schema-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOSchemaServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_authors;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_home']           = false;
		$lightweight_seo_test_query_state['is_front_page']     = false;
		$lightweight_seo_test_query_state['is_singular']       = false;
		$lightweight_seo_test_query_state['is_single']         = false;
		$lightweight_seo_test_query_state['is_author']         = false;
		$lightweight_seo_test_query_state['queried_object_id'] = 0;
		$lightweight_seo_test_query_state['permalink']         = 'https://example.com/post';
		$lightweight_seo_test_query_state['title']             = 'Test Title';
		$lightweight_seo_test_query_state['thumbnail_url']     = '';
		$lightweight_seo_test_query_state['queried_object']    = null;
		$lightweight_seo_test_query_state['post_author']       = 17;
		$lightweight_seo_test_query_state['published_date']    = '2024-01-01T12:00:00+00:00';
		$lightweight_seo_test_query_state['modified_date']     = '2024-01-02T12:00:00+00:00';
		$lightweight_seo_test_authors                          = array(
			17 => array(
				'display_name' => 'Author Name',
				'description'  => 'Author biography',
			),
		);
	}

	public function test_homepage_schema_outputs_organization_and_website_graph(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_home'] = true;

		$page_context = new class() {
			public function get_context() {
				return array(
					'document_title' => 'Test Site – Test Tagline',
					'canonical_url'  => 'https://example.com/',
					'description'    => 'Default description',
				);
			}
		};

		$settings = new class() {
			public function schema_output_enabled() {
				return true;
			}

			public function get_social_image_url() {
				return 'https://example.com/logo.png';
			}

			public function get_organization_same_as() {
				return array( 'https://example.com/x' );
			}
		};

		$service = new Lightweight_SEO_Schema_Service( $page_context, $settings );

		ob_start();
		$service->add_schema();
		$output = ob_get_clean();

		$this->assertStringContainsString( '"@type":"Organization"', $output );
		$this->assertStringContainsString( '"@type":"WebSite"', $output );
		$this->assertStringContainsString( '"sameAs":["https://example.com/x"]', $output );
		$this->assertStringContainsString( '"logo":"https://example.com/logo.png"', $output );
	}

	public function test_single_post_schema_outputs_article_and_breadcrumbs(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular']       = true;
		$lightweight_seo_test_query_state['is_single']         = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 42;
		$lightweight_seo_test_query_state['thumbnail_url']     = 'https://example.com/post-image.jpg';

		$page_context = new class() {
			public function get_context() {
				return array(
					'document_title' => 'My Post Title',
					'canonical_url'  => 'https://example.com/my-post',
					'description'    => 'Post description',
					'og_image'       => 'https://example.com/post-image.jpg',
				);
			}
		};

		$settings = new class() {
			public function schema_output_enabled() {
				return true;
			}

			public function get_social_image_url() {
				return '';
			}

			public function get_organization_same_as() {
				return array();
			}
		};

		$service = new Lightweight_SEO_Schema_Service( $page_context, $settings );

		ob_start();
		$service->add_schema();
		$output = ob_get_clean();

		$this->assertStringContainsString( '"@type":"Article"', $output );
		$this->assertStringContainsString( '"headline":"My Post Title"', $output );
		$this->assertStringContainsString( '"datePublished":"2024-01-01T12:00:00+00:00"', $output );
		$this->assertStringContainsString( '"@type":"BreadcrumbList"', $output );
		$this->assertStringContainsString( '"@type":"Person"', $output );
	}

	public function test_author_archive_outputs_profile_page_schema(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_author']         = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 17;
		$lightweight_seo_test_query_state['queried_object']    = (object) array(
			'ID'           => 17,
			'display_name' => 'Author Name',
		);

		$page_context = new class() {
			public function get_context() {
				return array(
					'document_title' => 'Author Name – Test Site',
					'canonical_url'  => 'https://example.com/author/17',
					'description'    => 'Author biography',
				);
			}
		};

		$settings = new class() {
			public function schema_output_enabled() {
				return true;
			}

			public function get_social_image_url() {
				return '';
			}

			public function get_organization_same_as() {
				return array();
			}
		};

		$service = new Lightweight_SEO_Schema_Service( $page_context, $settings );

		ob_start();
		$service->add_schema();
		$output = ob_get_clean();

		$this->assertStringContainsString( '"@type":"ProfilePage"', $output );
		$this->assertStringContainsString( '"mainEntity":{"@type":"Person","name":"Author Name","url":"https://example.com/author/17","description":"Author biography"}', $output );
	}
}
