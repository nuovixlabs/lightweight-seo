<?php

final class LightweightSEOFrontendOutputTest extends WP_UnitTestCase {

	public function test_real_wp_head_has_one_canonical_one_robots_result_and_valid_schema(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Integration Post',
				'post_excerpt' => '<strong>Useful</strong>   integration summary',
				'post_status'  => 'publish',
			)
		);

		update_post_meta( $post_id, '_lightweight_seo_canonical_url', home_url( '/preferred-integration-post/' ) );
		update_post_meta( $post_id, '_lightweight_seo_noindex', '1' );
		$this->go_to( get_permalink( $post_id ) );

		if ( false === has_action( 'wp_head', 'rel_canonical' ) ) {
			add_action( 'wp_head', 'rel_canonical' );
		}

		if ( false === has_action( 'wp_head', 'wp_robots' ) ) {
			add_action( 'wp_head', 'wp_robots' );
		}

		ob_start();
		do_action( 'wp_head' );
		$output = ob_get_clean();

		$this->assertSame( 1, substr_count( $output, 'rel="canonical"' ) );
		$this->assertStringContainsString( 'href="' . home_url( '/preferred-integration-post/' ) . '"', $output );
		$this->assertSame( 1, preg_match_all( '/name=[\'\"]robots[\'\"]/', $output ) );
		$this->assertMatchesRegularExpression( '/name=[\'\"]robots[\'\"] content=[\'\"][^\'\"]*noindex/', $output );
		$this->assertStringContainsString( 'name="description" content="Useful integration summary"', $output );
		$this->assertStringNotContainsString( 'name="keywords"', $output );
		$this->assertStringContainsString( '"@type":"Article"', $output );
		$this->assertStringContainsString( '"@type":"Organization"', $output );
	}

	public function test_real_404_context_suppresses_canonical_social_and_schema_output(): void {
		$this->go_to( home_url( '/definitely-missing-lightweight-seo-url/' ) );
		$GLOBALS['wp_query']->set_404();

		$settings     = new Lightweight_SEO_Settings();
		$post_meta    = new Lightweight_SEO_Post_Meta();
		$archive_meta = new Lightweight_SEO_Archive_Meta( $settings );
		$page_context = new Lightweight_SEO_Page_Context_Service( $settings, $post_meta, $archive_meta );
		$meta_tags    = new Lightweight_SEO_Meta_Tags_Service( $page_context );
		$schema       = new Lightweight_SEO_Schema_Service( $page_context, $settings );

		ob_start();
		$meta_tags->add_meta_tags();
		$meta_tags->add_non_singular_canonical();
		$schema->add_schema();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'rel="canonical"', $output );
		$this->assertStringNotContainsString( 'property="og:', $output );
		$this->assertStringNotContainsString( 'application/ld+json', $output );
		$robots = $meta_tags->filter_robots( array() );

		$this->assertTrue( $robots['noindex'] );
	}

	public function test_password_protected_content_does_not_expose_description_or_social_image(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_content'  => 'Private content must not appear in metadata.',
				'post_password' => 'secret',
				'post_status'   => 'publish',
			)
		);
		$this->go_to( get_permalink( $post_id ) );

		$settings     = new Lightweight_SEO_Settings();
		$post_meta    = new Lightweight_SEO_Post_Meta();
		$archive_meta = new Lightweight_SEO_Archive_Meta( $settings );
		$context      = ( new Lightweight_SEO_Page_Context_Service( $settings, $post_meta, $archive_meta ) )->get_context();

		$this->assertSame( '', $context['description'] );
		$this->assertSame( '', $context['og_image'] );
		$this->assertStringContainsString( 'noindex', $context['robots'] );
	}
}
