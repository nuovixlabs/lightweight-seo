<?php

/**
 * Markdown on request against real WordPress: real queries, the_content filters, and the head the plugin prints.
 *
 * PHP on the command line records no header, so the headers are read from the answer the module
 * builds, and the HTML path from the decision it makes.
 */
final class LightweightSEOMarkdownOnRequestIntegrationTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		require_once dirname( __DIR__, 2 ) . '/includes/class-lightweight-seo-ai-discovery-module.php';

		update_option(
			LIGHTWEIGHT_SEO_OPTION_NAME,
			array(
				'enable_ai_discovery'        => '1',
				'enable_markdown_on_request' => '1',
			)
		);
		update_option( 'blog_public', 1 );
		update_option( 'show_on_front', 'posts' );
		update_option( 'blogname', 'Integration Site' );
	}

	public function test_a_post_is_answered_with_its_content_and_the_head_values(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Integration Markdown Post',
				'post_excerpt' => 'A summary for the head.',
				'post_content' => "<!-- wp:paragraph -->\n<p>Hello <strong>bold</strong> world.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul class=\"wp-block-list\"><li>One</li><li>Two</li></ul>\n<!-- /wp:list -->",
			)
		);

		$answer = $this->answer( get_permalink( $post_id ) );
		$body   = $answer['response']['body'];

		$this->assertSame( 'singular', $answer['view'] );
		$this->assertSame( 200, $answer['response']['status'] );
		$this->assertStringStartsWith( "---\ntitle: ", $body );
		$this->assertStringContainsString( "\ndescription: A summary for the head.\n---\n\n# Integration Markdown Post\n\nHello **bold** world.\n\n- One\n- Two\n\n```json\n{", $body );
		$this->assertStringContainsString( '"@type":"Article"', $body );
		$this->assertStringEndsWith( "}\n```\n", $body );
		$this->assertStringNotContainsString( 'wp:paragraph', $body );

		$headers = $this->headers( $answer );

		$this->assertSame( 'text/markdown; charset=UTF-8', $headers['Content-Type'] );
		$this->assertSame( 'Accept', $headers['Vary'] );
		$this->assertSame( 'private, no-store', $headers['Cache-Control'] );
		$this->assertSame( '<' . get_permalink( $post_id ) . '>; rel="canonical"', $headers['Link'] );
		$this->assertArrayNotHasKey( 'X-Robots-Tag', $headers );
		$this->assertArrayNotHasKey( 'Content-Signal', $headers );
	}

	public function test_a_page_split_into_pages_gives_the_page_requested(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Integration Split Page',
				'post_content' => '<p>Part one.</p><!--nextpage--><p>Part two.</p>',
			)
		);

		$first  = $this->answer( get_permalink( $page_id ) );
		$second = $this->answer( add_query_arg( 'page', 2, get_permalink( $page_id ) ) );

		$this->assertSame( 'singular', $first['view'] );
		$this->assertStringContainsString( "# Integration Split Page\n\nPart one.\n", $first['response']['body'] );
		$this->assertStringNotContainsString( 'Part two.', $first['response']['body'] );
		$this->assertStringContainsString( "# Integration Split Page\n\nPart two.\n", $second['response']['body'] );
		$this->assertStringNotContainsString( 'Part one.', $second['response']['body'] );
	}

	public function test_a_static_front_page_is_answered_as_its_page(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Integration Front Page',
				'post_content' => '<p>Welcome.</p>',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );

		$answer = $this->answer( home_url( '/' ) );

		$this->assertTrue( is_front_page() );
		$this->assertSame( 'singular', $answer['view'] );
		$this->assertStringContainsString( "# Integration Front Page\n\nWelcome.\n", $answer['response']['body'] );
	}

	public function test_the_latest_posts_homepage_lists_its_posts(): void {
		$older = self::factory()->post->create(
			array(
				'post_title'   => 'Integration Older Post',
				'post_excerpt' => 'Older excerpt.',
				'post_date'    => '2024-01-01 10:00:00',
			)
		);
		$newer = self::factory()->post->create(
			array(
				'post_title'   => 'Integration Newer Post',
				'post_excerpt' => 'Newer excerpt.',
				'post_date'    => '2024-02-01 10:00:00',
			)
		);

		$answer = $this->answer( home_url( '/' ) );
		$body   = $answer['response']['body'];

		$this->assertSame( 'home', $answer['view'] );
		$this->assertStringContainsString( "# Integration Site\n\n", $body );

		$newer_line = '- [Integration Newer Post](' . get_permalink( $newer ) . '): Newer excerpt.';
		$older_line = '- [Integration Older Post](' . get_permalink( $older ) . '): Older excerpt.';

		$this->assertStringContainsString( $newer_line, $body );
		$this->assertStringContainsString( $older_line, $body );
		$this->assertLessThan( strpos( $body, $older_line ), strpos( $body, $newer_line ), 'The order of the main query is kept.' );
	}

	public function test_password_protected_and_draft_posts_are_never_answered_in_markdown(): void {
		$protected = self::factory()->post->create(
			array(
				'post_title'    => 'Integration Locked Post',
				'post_content'  => 'Secret words.',
				'post_password' => 'secret',
			)
		);
		$draft     = self::factory()->post->create(
			array(
				'post_title'   => 'Integration Draft Post',
				'post_content' => 'Unpublished words.',
				'post_status'  => 'draft',
			)
		);

		$this->assertSame( '', $this->answer( get_permalink( $protected ) )['view'] );
		$this->assertNull( $this->answer( get_permalink( $protected ) )['response'] );
		$this->assertSame( '', $this->answer( get_permalink( $draft ) )['view'] );
	}

	public function test_a_post_hidden_from_search_keeps_noindex_in_its_markdown_answer(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Integration Hidden Post',
				'post_content' => '<p>Hidden.</p>',
			)
		);
		update_post_meta( $post_id, '_lightweight_seo_noindex', '1' );

		$headers = $this->headers( $this->answer( get_permalink( $post_id ) ) );

		$this->assertMatchesRegularExpression( '/(?:^|, )noindex(?:,|$)/', $headers['X-Robots-Tag'] );
	}

	public function test_content_signals_are_sent_when_their_switch_is_on(): void {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Integration Signal Post' ) );

		update_option(
			LIGHTWEIGHT_SEO_OPTION_NAME,
			array(
				'enable_ai_discovery'        => '1',
				'enable_markdown_on_request' => '1',
				'enable_content_signals'     => '1',
			)
		);

		$this->assertSame( 'ai-train=no, search=yes, ai-input=yes', $this->headers( $this->answer( get_permalink( $post_id ) ) )['Content-Signal'] );
	}

	public function test_a_browser_gets_html_and_a_vary_line_is_due(): void {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Integration Browser Post' ) );
		$browser = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
		$answer  = $this->answer( get_permalink( $post_id ), $browser );

		$this->assertSame( 'singular', $answer['view'] );
		$this->assertNull( $answer['response'] );
		$this->assertTrue( Lightweight_SEO_AI_Discovery_Module::needs_vary_accept( array( 'Content-Type: text/html; charset=UTF-8' ) ) );
	}

	public function test_with_the_switch_off_the_frontend_hook_is_not_added(): void {
		update_option( LIGHTWEIGHT_SEO_OPTION_NAME, array( 'enable_ai_discovery' => '1' ) );

		$module = new Lightweight_SEO_AI_Discovery_Module( new Lightweight_SEO_Settings(), lightweight_seo_get_api(), 'frontend' );

		$this->assertFalse( has_action( 'template_redirect', array( $module, 'maybe_answer_with_markdown' ) ) );

		remove_filter( 'robots_txt', array( $module, 'filter_robots_txt' ), 20 );
		remove_action( 'template_redirect', array( $module, 'maybe_render_llms_txt' ), 0 );
	}

	/**
	 * Visit an address and build the answer the module would send, without sending it.
	 *
	 * @return array { view, response: the answer, or null when HTML is sent }
	 */
	private function answer( $url, $accept = 'text/markdown', $method = 'GET' ) {
		$this->go_to( $url );

		$page_context = $this->shared_page_context();
		$module       = new Lightweight_SEO_AI_Discovery_Module( new Lightweight_SEO_Settings(), lightweight_seo_get_api(), 'admin', new Lightweight_SEO_Post_Meta(), $page_context );
		$view         = $module->get_markdown_view();

		return array(
			'view'     => $view,
			'response' => '' !== $view && $module->markdown_requested( $view, $accept, $method ) ? $module->build_markdown_response( $view, $method ) : null,
		);
	}

	/** Headers of an answer by name. A name sent more than once keeps its last value. */
	private function headers( $answer ) {
		$this->assertNotNull( $answer['response'] );

		return array_column( $answer['response']['headers'], 1, 0 );
	}

	/**
	 * The page context the plugin's head output reads, emptied for the request just visited.
	 *
	 * It is built once per request; the test visits several addresses in one process.
	 */
	private function shared_page_context() {
		foreach ( $GLOBALS['wp_filter']['wp_robots']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Lightweight_SEO_Meta_Tags_Service ) {
					$service = new ReflectionProperty( Lightweight_SEO_Meta_Tags_Service::class, 'page_context' );
					$service->setAccessible( true );
					$page_context = $service->getValue( $callback['function'][0] );

					$cache = new ReflectionProperty( Lightweight_SEO_Page_Context_Service::class, 'context' );
					$cache->setAccessible( true );
					$cache->setValue( $page_context, null );

					return $page_context;
				}
			}
		}

		$this->fail( 'The plugin adds its robots directives through wp_robots.' );
	}
}
