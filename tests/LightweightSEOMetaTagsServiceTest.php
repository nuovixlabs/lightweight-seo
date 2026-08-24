<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-meta-tags-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOMetaTagsServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular'] = false;
	}

	public function test_add_meta_tags_respects_keyword_toggle(): void {
		$page_context = new class() {
			public function get_context() {
				return array(
					'description'      => 'A description',
					'keywords'         => 'one,two',
					'keywords_enabled' => false,
					'canonical_url'    => 'https://example.com/canonical-url',
					'canonical_custom' => true,
					'robots'           => 'noindex, nofollow',
					'og_title'         => 'My OG Title',
					'og_description'   => 'My OG Description',
					'og_type'          => 'article',
					'og_url'           => 'https://example.com/post',
					'og_image'         => 'https://example.com/image.jpg',
					'og_image_alt'     => 'A useful image',
					'og_image_width'   => 1600,
					'og_image_height'  => 900,
					'twitter_card'     => 'summary_large_image',
				);
			}
		};

		$service = new Lightweight_SEO_Meta_Tags_Service( $page_context );

		ob_start();
		$service->add_meta_tags();
		$service->add_non_singular_canonical();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="description" content="A description"', $output );
		$this->assertStringContainsString( 'rel="canonical" href="https://example.com/canonical-url"', $output );
		$this->assertStringContainsString( 'property="og:title" content="My OG Title"', $output );
		$this->assertStringContainsString( 'name="twitter:title" content="My OG Title"', $output );
		$this->assertStringContainsString( 'property="og:image:alt" content="A useful image"', $output );
		$this->assertStringContainsString( 'property="og:image:width" content="1600"', $output );
		$this->assertStringContainsString( 'property="og:image:height" content="900"', $output );
		$this->assertStringContainsString( 'name="twitter:image:alt" content="A useful image"', $output );
		$this->assertStringNotContainsString( 'name="keywords"', $output );
		$this->assertStringNotContainsString( 'name="robots"', $output );
		$this->assertSame(
			array(
				'noindex'  => true,
				'nofollow' => true,
			),
			$service->filter_robots( array() )
		);

		global $lightweight_seo_test_query_state;
		$lightweight_seo_test_query_state['is_singular'] = true;

		$this->assertSame( 'https://example.com/canonical-url', $service->filter_canonical_url( 'https://example.com/original', null ) );
	}

	public function test_wordpress_canonical_is_preserved_for_non_custom_singular_pagination(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular'] = true;

		$page_context = new class() {
			public function get_context() {
				return array(
					'canonical_url'    => 'https://example.com/post/',
					'canonical_custom' => false,
				);
			}
		};

		$service = new Lightweight_SEO_Meta_Tags_Service( $page_context );

		$this->assertSame(
			'https://example.com/post/2/',
			$service->filter_canonical_url( 'https://example.com/post/2/', null )
		);
	}

	public function test_404_suppresses_canonical_and_social_metadata(): void {
		$page_context = new class() {
			public function get_context() {
				return array(
					'is_404'        => true,
					'canonical_url' => '',
					'robots'        => 'noindex',
				);
			}
		};

		$service = new Lightweight_SEO_Meta_Tags_Service( $page_context );

		ob_start();
		$service->add_meta_tags();
		$service->add_non_singular_canonical();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
		$this->assertSame( array( 'noindex' => true ), $service->filter_robots( array() ) );
	}
}
