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

	public function test_custom_canonical_is_not_applied_to_other_posts_or_archives(): void {
		global $lightweight_seo_test_query_state;

		$page_context = new class() {
			public function get_context() {
				return array(
					'canonical_url'    => 'https://example.com/custom-canonical',
					'canonical_custom' => true,
				);
			}
		};
		$service      = new Lightweight_SEO_Meta_Tags_Service( $page_context );

		$lightweight_seo_test_query_state['is_singular']       = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 42;

		$this->assertSame(
			'https://example.com/custom-canonical',
			$service->filter_canonical_url( 'https://example.com/current/', (object) array( 'ID' => 42 ) )
		);
		$this->assertSame(
			'https://example.com/another-post/',
			$service->filter_canonical_url( 'https://example.com/another-post/', (object) array( 'ID' => 99 ) ),
			'A canonical requested for a different post must keep the value WordPress calculated.'
		);

		$lightweight_seo_test_query_state['is_singular'] = false;

		$this->assertSame(
			'https://example.com/looped-post/',
			$service->filter_canonical_url( 'https://example.com/looped-post/', (object) array( 'ID' => 42 ) ),
			'Archives and admin screens must not rewrite post canonicals.'
		);
	}

	public function test_open_graph_locale_and_article_times_are_emitted_when_known(): void {
		$page_context = new class() {
			public function get_context() {
				return array(
					'og_title'       => 'Article',
					'og_type'        => 'article',
					'og_locale'      => 'en_US',
					'published_time' => '2024-01-01T12:00:00+00:00',
					'modified_time'  => '2024-01-02T12:00:00+00:00',
				);
			}
		};
		$service      = new Lightweight_SEO_Meta_Tags_Service( $page_context );

		ob_start();
		$service->add_meta_tags();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'property="og:locale" content="en_US"', $output );
		$this->assertStringContainsString( 'property="article:published_time" content="2024-01-01T12:00:00+00:00"', $output );
		$this->assertStringContainsString( 'property="article:modified_time" content="2024-01-02T12:00:00+00:00"', $output );
	}

	public function test_x_handle_is_emitted_as_twitter_site_only_when_set(): void {
		$with_handle    = new class() {
			public function get_context() {
				return array(
					'og_title'     => 'A page',
					'twitter_site' => '@example',
				);
			}
		};
		$without_handle = new class() {
			public function get_context() {
				return array( 'og_title' => 'A page' );
			}
		};
		$not_found      = new class() {
			public function get_context() {
				return array(
					'is_404'       => true,
					'twitter_site' => '@example',
				);
			}
		};

		ob_start();
		( new Lightweight_SEO_Meta_Tags_Service( $with_handle ) )->add_meta_tags();
		$output = ob_get_clean();

		$this->assertSame( 1, substr_count( $output, 'name="twitter:site" content="@example"' ) );
		// The site handle sits with the other X tags, after the card type.
		$this->assertLessThan( strpos( $output, 'twitter:site' ), strpos( $output, 'twitter:card' ) );
		$this->assertLessThan( strpos( $output, 'twitter:title' ), strpos( $output, 'twitter:site' ) );

		ob_start();
		( new Lightweight_SEO_Meta_Tags_Service( $without_handle ) )->add_meta_tags();
		( new Lightweight_SEO_Meta_Tags_Service( $not_found ) )->add_meta_tags();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'twitter:site', $output );
	}

	public function test_verification_codes_are_printed_as_meta_tags(): void {
		$page_context = new class() {
			public function get_context() {
				return array(
					'og_title'     => 'Home',
					'verification' => array(
						'google-site-verification' => 'google-code_1',
						'msvalidate.01'            => '0123456789ABCDEF',
						'p:domain_verify'          => 'f00ba4',
					),
				);
			}
		};
		$without      = new class() {
			public function get_context() {
				return array( 'og_title' => 'A post' );
			}
		};

		ob_start();
		( new Lightweight_SEO_Meta_Tags_Service( $page_context ) )->add_meta_tags();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<meta name="google-site-verification" content="google-code_1" />', $output );
		$this->assertStringContainsString( '<meta name="msvalidate.01" content="0123456789ABCDEF" />', $output );
		$this->assertStringContainsString( '<meta name="p:domain_verify" content="f00ba4" />', $output );

		ob_start();
		( new Lightweight_SEO_Meta_Tags_Service( $without ) )->add_meta_tags();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'verification', $output );
		$this->assertStringNotContainsString( 'msvalidate', $output );
	}
}
