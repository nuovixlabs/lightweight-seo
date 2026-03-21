<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-meta-tags-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOMetaTagsServiceTest extends TestCase {

	public function test_add_meta_tags_respects_keyword_toggle(): void {
		$page_context = new class() {
			public function get_context() {
				return array(
					'description'      => 'A description',
					'keywords'         => 'one,two',
					'keywords_enabled' => false,
					'robots'           => 'noindex, nofollow',
					'og_title'         => 'My OG Title',
					'og_description'   => 'My OG Description',
					'og_type'          => 'article',
					'og_url'           => 'https://example.com/post',
					'og_image'         => 'https://example.com/image.jpg',
					'twitter_card'     => 'summary_large_image',
				);
			}
		};

		$service = new Lightweight_SEO_Meta_Tags_Service( $page_context );

		ob_start();
		$service->add_meta_tags();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="description" content="A description"', $output );
		$this->assertStringContainsString( 'property="og:title" content="My OG Title"', $output );
		$this->assertStringContainsString( 'name="twitter:title" content="My OG Title"', $output );
		$this->assertStringNotContainsString( 'name="keywords"', $output );
	}
}
