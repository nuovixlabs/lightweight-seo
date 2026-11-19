<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-compatibility-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-meta-tags-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-schema-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-ai-discovery-module.php';

use PHPUnit\Framework\TestCase;

/**
 * Markdown on request: the switch, which requests are answered in Markdown, and the answer.
 *
 * The converter and the response builder are not required here on purpose: the module loads
 * them only when it builds an answer, which a test in a separate process checks.
 */
final class LightweightSEOMarkdownOnRequestTest extends TestCase {

	const QUERY_DEFAULTS = array(
		'is_singular'          => false,
		'is_single'            => false,
		'is_home'              => false,
		'is_front_page'        => false,
		'is_archive'           => false,
		'is_category'          => false,
		'is_search'            => false,
		'is_404'               => false,
		'is_feed'              => false,
		'is_embed'             => false,
		'is_robots'            => false,
		'is_trackback'         => false,
		'is_favicon'           => false,
		'is_preview'           => false,
		'is_customize_preview' => false,
		'queried_object_id'    => 0,
		'query_vars'           => array(),
		'title'                => 'Hello: “World”',
		'document_title'       => 'Hello: &#8220;World&#8221; &#8211; Test Site',
		'wp_canonical'         => 'https://example.com/hello/',
	);

	/** @var array */
	private $saved_query_state;

	protected function setUp(): void {
		global $lightweight_seo_test_query_state;

		$this->saved_query_state = $lightweight_seo_test_query_state;

		$GLOBALS['lightweight_seo_test_filters']        = array();
		$GLOBALS['lightweight_seo_test_options']        = array( 'blog_public' => 1 );
		$GLOBALS['lightweight_seo_test_posts']          = array(
			5  => $this->post( 5, 'post', 'publish', '', 'https://example.com/hello/' ),
			6  => $this->post( 6, 'page', 'publish', '', 'https://example.com/about/' ),
			7  => $this->post( 7, 'post', 'draft', '', 'https://example.com/?p=7' ),
			8  => $this->post( 8, 'post', 'publish', 'secret', 'https://example.com/locked/' ),
			9  => $this->post( 9, 'attachment', 'inherit', '', 'https://example.com/photo/' ),
			10 => $this->post( 10, 'post', 'private', '', 'https://example.com/private/' ),
			11 => $this->post( 11, 'product', 'publish', '', 'https://example.com/hidden-type/' ),
			12 => $this->post( 12, 'attachment', 'publish', '', 'https://example.com/published-photo/' ),
			20 => $this->post( 20, 'page', 'publish', '', 'https://example.com/blog/' ),
		);
		$GLOBALS['lightweight_seo_test_hooks']          = array();
		$GLOBALS['lightweight_seo_test_hook_callbacks'] = array();
		$GLOBALS['lightweight_seo_test_loop']           = array();
		$GLOBALS['lightweight_seo_test_loop_index']     = 0;
		$GLOBALS['lightweight_seo_test_is_admin']       = false;
		$lightweight_seo_test_query_state               = array_merge( $lightweight_seo_test_query_state, self::QUERY_DEFAULTS );
		$_SERVER['REQUEST_URI']                         = '/hello/';
		$_SERVER['REQUEST_METHOD']                      = 'GET';
		unset( $GLOBALS['lightweight_seo_test_loop_post'] );
	}

	protected function tearDown(): void {
		$GLOBALS['lightweight_seo_test_query_state'] = $this->saved_query_state;
		$GLOBALS['lightweight_seo_test_filters']     = array();
		$GLOBALS['lightweight_seo_test_options']     = array();
		$GLOBALS['lightweight_seo_test_posts']       = array();
		$GLOBALS['lightweight_seo_test_loop']        = array();
		unset( $GLOBALS['lightweight_seo_test_loop_post'] );
	}

	/* The switch and its hook --------------------------------------------- */

	public function test_the_switch_is_off_by_default(): void {
		$settings = new Lightweight_SEO_Settings();

		$this->assertSame( '0', $settings->get_defaults()['enable_markdown_on_request'] );
		$this->assertFalse( $settings->markdown_on_request_enabled() );

		$GLOBALS['lightweight_seo_test_options'][ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'enable_markdown_on_request' => '1' );

		$this->assertTrue( ( new Lightweight_SEO_Settings() )->markdown_on_request_enabled() );
	}

	public function test_with_the_switch_off_no_hook_is_added(): void {
		new Lightweight_SEO_AI_Discovery_Module( new Lightweight_SEO_Settings(), null, 'frontend' );

		$this->assertSame( array( 'robots_txt', 'template_redirect' ), $GLOBALS['lightweight_seo_test_hooks'] );
		$this->assertSame( array( 'maybe_render_llms_txt' ), array_column( $GLOBALS['lightweight_seo_test_hook_callbacks']['template_redirect'], 1 ) );
	}

	public function test_with_the_switch_on_one_late_template_redirect_callback_is_added_on_the_frontend_only(): void {
		$this->switch_on();

		new Lightweight_SEO_AI_Discovery_Module( new Lightweight_SEO_Settings(), null, 'admin' );

		$this->assertSame( array(), $GLOBALS['lightweight_seo_test_hooks'] );

		$module = new Lightweight_SEO_AI_Discovery_Module( new Lightweight_SEO_Settings(), null, 'frontend' );

		$this->assertSame( array( 'robots_txt', 'template_redirect', 'template_redirect' ), $GLOBALS['lightweight_seo_test_hooks'] );
		$this->assertSame( array( $module, 'maybe_answer_with_markdown' ), $GLOBALS['lightweight_seo_test_hook_callbacks']['template_redirect'][1] );
		// After canonical redirects (10) and the inspector (99999).
		$this->assertGreaterThan( 99999, Lightweight_SEO_AI_Discovery_Module::MARKDOWN_PRIORITY );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_the_converter_and_response_files_are_loaded_only_to_build_an_answer(): void {
		$this->switch_on();
		$this->view_post( 5 );

		$module = new Lightweight_SEO_AI_Discovery_Module( new Lightweight_SEO_Settings(), null, 'frontend' );

		// An ordinary page view: the view is checked, the header read, and nothing else loaded.
		$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,*/*;q=0.8';
		$module->maybe_answer_with_markdown();

		$this->assertFalse( class_exists( 'Lightweight_SEO_Markdown_Converter', false ) );
		$this->assertFalse( class_exists( 'Lightweight_SEO_Markdown_Response', false ) );

		$this->assertTrue( $module->markdown_requested( 'singular', 'text/markdown', 'GET' ) );
		$module->build_markdown_response( 'singular', 'HEAD' );

		$this->assertTrue( class_exists( 'Lightweight_SEO_Markdown_Converter', false ) );
		$this->assertTrue( class_exists( 'Lightweight_SEO_Markdown_Response', false ) );
	}

	/**
	 * Sending loads the files it needs: the class that sends is defined by building the answer.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_an_answer_is_sent_without_its_files_loaded_beforehand(): void {
		$this->switch_on();
		$this->view_post( 5, '<p>Sent body.</p>' );

		$module = new Lightweight_SEO_AI_Discovery_Module( new Lightweight_SEO_Settings(), null, 'frontend' );
		$send   = new ReflectionMethod( $module, 'send_markdown_response' );
		$send->setAccessible( true );

		$this->assertFalse( class_exists( 'Lightweight_SEO_Markdown_Response', false ) );

		ob_start();
		$send->invoke( $module, 'singular', 'GET' );
		$body = ob_get_clean();

		$this->assertStringContainsString( "\n\nSent body.\n", $body );
		$this->assertTrue( defined( 'DONOTCACHEPAGE' ) );
	}

	/* Which requests are answered in Markdown ------------------------------ */

	/**
	 * @dataProvider accept_headers
	 */
	public function test_accept_header_selects_markdown_only_when_it_prefers_it( string $accept, bool $expected ): void {
		$this->assertSame( $expected, Lightweight_SEO_AI_Discovery_Module::prefers_markdown( $accept ) );
	}

	public function accept_headers(): array {
		return array(
			'markdown alone'                      => array( 'text/markdown', true ),
			'markdown first, html lower'          => array( 'text/markdown, text/html;q=0.9, */*;q=0.8', true ),
			'equal quality'                       => array( 'text/html;q=0.5, text/markdown;q=0.5', true ),
			'html higher'                         => array( 'text/html, text/markdown;q=0.5', false ),
			'xhtml higher'                        => array( 'application/xhtml+xml, text/markdown;q=0.9', false ),
			'markdown refused'                    => array( 'text/markdown;q=0', false ),
			'markdown refused with decimals'      => array( 'text/markdown;q=0.000', false ),
			'wildcard alone'                      => array( '*/*', false ),
			'text wildcard alone'                 => array( 'text/*', false ),
			'missing header'                      => array( '', false ),
			'browser'                             => array( 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8', false ),
			'odd case and spacing'                => array( "  TEXT/Markdown ;  Q = 0.8 ,\ttext/HTML ; q=0.7", true ),
			'charset parameter before q'          => array( 'text/markdown; charset=utf-8; q=1.0, text/html;q=0.9', true ),
			'quoted q value'                      => array( 'text/markdown;q="0.9", text/html;q=0.8', true ),
			'unreadable q on markdown'            => array( 'text/markdown;q=high', false ),
			'unreadable q on html counts as 1'    => array( 'text/markdown, text/html;q=2', true ),
			'unreadable q on html beats lower md' => array( 'text/markdown;q=0.9, text/html;q=x', false ),
			'markdown named twice'                => array( 'text/markdown;q=0, text/markdown;q=0.6, text/html;q=0.5', true ),
			'another markdown-like type'          => array( 'text/x-markdown', false ),
			'empty entries'                       => array( ',,text/markdown,,', true ),
		);
	}

	public function test_a_vary_line_is_added_unless_one_already_names_accept(): void {
		$this->assertTrue( Lightweight_SEO_AI_Discovery_Module::needs_vary_accept( array() ) );
		$this->assertTrue( Lightweight_SEO_AI_Discovery_Module::needs_vary_accept( array( 'Content-Type: text/html', 'Vary: Accept-Encoding, Cookie' ) ) );
		$this->assertFalse( Lightweight_SEO_AI_Discovery_Module::needs_vary_accept( array( 'vary: Cookie,  accept ' ) ) );
		$this->assertFalse( Lightweight_SEO_AI_Discovery_Module::needs_vary_accept( array( 'Vary: *' ) ) );
	}

	/**
	 * @dataProvider views
	 */
	public function test_only_public_posts_pages_and_the_homepages_can_be_answered_in_markdown( array $state, string $expected, array $options = array() ): void {
		$GLOBALS['lightweight_seo_test_query_state'] = array_merge( $GLOBALS['lightweight_seo_test_query_state'], $state );
		$GLOBALS['lightweight_seo_test_options']     = array_merge( $GLOBALS['lightweight_seo_test_options'], $options );
		$GLOBALS['lightweight_seo_test_is_admin']    = ! empty( $state['is_admin'] );
		$_SERVER['REQUEST_URI']                      = $state['uri'] ?? '/';

		$this->assertSame( $expected, $this->module()->get_markdown_view() );
	}

	public function views(): array {
		$singular = function ( $id, $extra = array() ) {
			return array_merge(
				array(
					'is_singular'       => true,
					'queried_object_id' => $id,
				),
				$extra
			);
		};

		return array(
			'a published post'                => array( $singular( 5 ), 'singular' ),
			'a published page'                => array( $singular( 6 ), 'singular' ),
			'a static front page'             => array( $singular( 6, array( 'is_front_page' => true ) ), 'singular' ),
			'the latest-posts homepage'       => array(
				array(
					'is_home'       => true,
					'is_front_page' => true,
				),
				'home',
			),
			'the posts page'                  => array( array( 'is_home' => true ), 'posts', array( 'page_for_posts' => 20 ) ),
			'a posts page that is not public' => array( array( 'is_home' => true ), '', array( 'page_for_posts' => 7 ) ),
			'a draft'                         => array( $singular( 7 ), '' ),
			'a password-protected post'       => array( $singular( 8 ), '' ),
			'an attachment'                   => array( $singular( 9 ), '' ),
			'a private post'                  => array( $singular( 10 ), '' ),
			'a post type that is not public'  => array( $singular( 11 ), '' ),
			'a missing post'                  => array( $singular( 404 ), '' ),
			'a preview'                       => array( $singular( 5, array( 'is_preview' => true ) ), '' ),
			'the customizer'                  => array( $singular( 5, array( 'is_customize_preview' => true ) ), '' ),
			'an embed'                        => array( $singular( 5, array( 'is_embed' => true ) ), '' ),
			'a trackback'                     => array( $singular( 5, array( 'is_trackback' => true ) ), '' ),
			'a feed'                          => array( array( 'is_feed' => true ), '' ),
			'an archive'                      => array(
				array(
					'is_archive'  => true,
					'is_category' => true,
				),
				'',
			),
			'search results'                  => array(
				array(
					'is_search' => true,
					'is_home'   => true,
				),
				'',
			),
			'not found'                       => array( array( 'is_404' => true ), '' ),
			'robots.txt'                      => array( array( 'is_robots' => true ), '' ),
			'the favicon'                     => array( array( 'is_favicon' => true ), '' ),
			'a sitemap'                       => array(
				array( 'query_vars' => array( 'sitemap' => 'posts' ) ) + array(
					'is_home'       => true,
					'is_front_page' => true,
				),
				'',
			),
			'a sitemap stylesheet'            => array(
				array( 'query_vars' => array( 'sitemap-stylesheet' => 'sitemap' ) ) + array(
					'is_home'       => true,
					'is_front_page' => true,
				),
				'',
			),
			'llms.txt'                        => array(
				array( 'uri' => '/llms.txt' ) + array(
					'is_home'       => true,
					'is_front_page' => true,
				),
				'',
			),
			'the administration'              => array( $singular( 5, array( 'is_admin' => true ) ), '' ),
		);
	}

	/**
	 * Each exclusion on its own turns away a view that would otherwise be answered, so each guard is what is tested.
	 *
	 * @dataProvider exclusions
	 */
	public function test_each_exclusion_alone_keeps_an_eligible_post_in_html( array $state, string $uri = '/hello/' ): void {
		$GLOBALS['lightweight_seo_test_query_state']['is_singular']       = true;
		$GLOBALS['lightweight_seo_test_query_state']['queried_object_id'] = 5;

		$this->assertSame( 'singular', $this->module()->get_markdown_view(), 'Without the exclusion the post is answered.' );

		$GLOBALS['lightweight_seo_test_query_state'] = array_merge( $GLOBALS['lightweight_seo_test_query_state'], $state );
		$GLOBALS['lightweight_seo_test_is_admin']    = ! empty( $state['is_admin'] );
		$_SERVER['REQUEST_URI']                      = $uri;

		$this->assertSame( '', $this->module()->get_markdown_view() );
	}

	public function exclusions(): array {
		return array(
			'a post\'s comment feed'     => array( array( 'is_feed' => true ) ),
			'an embed'                   => array( array( 'is_embed' => true ) ),
			'robots.txt'                 => array( array( 'is_robots' => true ) ),
			'a trackback'                => array( array( 'is_trackback' => true ) ),
			'the favicon'                => array( array( 'is_favicon' => true ) ),
			'not found'                  => array( array( 'is_404' => true ) ),
			'search results'             => array( array( 'is_search' => true ) ),
			'an archive'                 => array( array( 'is_archive' => true ) ),
			'a preview'                  => array( array( 'is_preview' => true ) ),
			'the customizer'             => array( array( 'is_customize_preview' => true ) ),
			'a sitemap'                  => array( array( 'query_vars' => array( 'sitemap' => 'posts' ) ) ),
			'a sitemap stylesheet'       => array( array( 'query_vars' => array( 'sitemap-stylesheet' => 'sitemap' ) ) ),
			'llms.txt'                   => array( array(), '/llms.txt' ),
			'the administration'         => array( array( 'is_admin' => true ) ),
			'an attachment, even public' => array( array( 'queried_object_id' => 12 ) ),
		);
	}

	/**
	 * A REST request is never answered in Markdown. REST_REQUEST is a constant, so this runs alone.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_a_rest_request_keeps_an_eligible_post_in_html(): void {
		$GLOBALS['lightweight_seo_test_query_state']['is_singular']       = true;
		$GLOBALS['lightweight_seo_test_query_state']['queried_object_id'] = 5;

		$this->assertSame( 'singular', $this->module()->get_markdown_view() );

		define( 'REST_REQUEST', true );

		$this->assertSame( '', $this->module()->get_markdown_view() );
	}

	public function test_no_post_id_never_stands_for_the_global_post(): void {
		// WordPress's get_post( 0 ) returns the global post; this stub does the same with ID 0.
		$GLOBALS['lightweight_seo_test_posts'][0]                  = $GLOBALS['lightweight_seo_test_posts'][6];
		$GLOBALS['lightweight_seo_test_options']['page_for_posts'] = 0;
		$GLOBALS['lightweight_seo_test_query_state']['is_home']    = true;

		$this->assertSame( '', $this->module()->get_markdown_view() );

		$GLOBALS['lightweight_seo_test_query_state']['is_home']           = false;
		$GLOBALS['lightweight_seo_test_query_state']['is_singular']       = true;
		$GLOBALS['lightweight_seo_test_query_state']['queried_object_id'] = 0;

		$this->assertSame( '', $this->module()->get_markdown_view() );
	}

	public function test_only_get_and_head_requests_for_an_available_view_are_answered(): void {
		$module = $this->module();

		$this->assertTrue( $module->markdown_requested( 'singular', 'text/markdown', 'GET' ) );
		$this->assertTrue( $module->markdown_requested( 'home', 'text/markdown', 'HEAD' ) );
		$this->assertFalse( $module->markdown_requested( 'singular', 'text/markdown', 'POST' ) );
		$this->assertFalse( $module->markdown_requested( 'singular', 'text/html', 'GET' ) );
		$this->assertFalse( $module->markdown_requested( '', 'text/markdown', 'GET' ) );
	}

	public function test_a_plugin_can_refuse_markdown_for_a_post(): void {
		$seen = array();
		$GLOBALS['lightweight_seo_test_filters']['lightweight_seo_markdown_supported'] = static function ( $supported, $post ) use ( &$seen ) {
			$seen[] = $post ? $post->ID : null;

			return $supported && ( ! $post || 5 !== $post->ID );
		};
		$GLOBALS['lightweight_seo_test_options']['page_for_posts']                     = 20;

		$this->view_post( 5 );
		$this->assertFalse( $this->module()->markdown_requested( 'singular', 'text/markdown', 'GET' ) );

		$this->view_post( 6 );
		$this->assertTrue( $this->module()->markdown_requested( 'singular', 'text/markdown', 'GET' ) );
		$this->assertTrue( $this->module()->markdown_requested( 'posts', 'text/markdown', 'GET' ) );
		$this->assertTrue( $this->module()->markdown_requested( 'home', 'text/markdown', 'GET' ) );

		// The post shown, the posts page, and nothing for the latest-posts homepage.
		$this->assertSame( array( 5, 6, 20, null ), $seen );
	}

	public function test_without_the_dom_extension_html_is_sent(): void {
		$module = new class( new Lightweight_SEO_Settings(), null, 'admin' ) extends Lightweight_SEO_AI_Discovery_Module {
			protected function markdown_available() {
				return false;
			}
		};

		$this->assertFalse( $module->markdown_requested( 'singular', 'text/markdown', 'GET' ) );
	}

	/**
	 * Output already started: the HTML page is sent, because the Markdown would go out without its headers.
	 *
	 * Without the check the answer is sent and the request ends, which ends this test's process.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_when_headers_are_already_sent_the_html_page_is_sent(): void {
		$this->switch_on();
		$this->view_post( 5, '<p>Body.</p>' );
		$_SERVER['HTTP_ACCEPT'] = 'text/markdown';

		$module = new class( new Lightweight_SEO_Settings(), null, 'admin' ) extends Lightweight_SEO_AI_Discovery_Module {
			protected function headers_already_sent() {
				return true;
			}
		};

		$this->assertTrue( $module->markdown_requested( 'singular', 'text/markdown', 'GET' ) );

		ob_start();
		$module->maybe_answer_with_markdown();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
		$this->assertFalse( class_exists( 'Lightweight_SEO_Markdown_Response', false ) );
	}

	/* The answer ------------------------------------------------------------ */

	public function test_headers_of_a_markdown_answer(): void {
		$this->load_response();

		$this->assertSame(
			array(
				array( 'Content-Type', 'text/markdown; charset=UTF-8', true ),
				array( 'Vary', 'Accept', false ),
				array( 'X-Content-Type-Options', 'nosniff', true ),
				array( 'Cache-Control', 'private, no-store', true ),
				array( 'X-LiteSpeed-Cache-Control', 'no-cache', true ),
			),
			Lightweight_SEO_Markdown_Response::get_headers( '', '', '' )
		);

		$all = Lightweight_SEO_Markdown_Response::get_headers( "https://example.com/a/\r\n", 'noindex, nofollow', 'ai-train=no, search=yes, ai-input=yes' );

		$this->assertContains( array( 'Link', '<https://example.com/a/>; rel="canonical"', false ), $all );
		$this->assertContains( array( 'X-Robots-Tag', 'noindex, nofollow', false ), $all );
		$this->assertContains( array( 'Content-Signal', 'ai-train=no, search=yes, ai-input=yes', true ), $all );
		$this->assertNotContains( 'ETag', array_column( $all, 0 ) );
	}

	/**
	 * @dataProvider robots_cases
	 */
	public function test_robots_directives_are_repeated_only_when_they_restrict_something( array $robots, string $expected ): void {
		$this->load_response();

		$this->assertSame( $expected, Lightweight_SEO_Markdown_Response::restrictive_robots( $robots ) );
	}

	public function robots_cases(): array {
		return array(
			'none'                     => array( array(), '' ),
			'the WordPress default'    => array( array( 'max-image-preview' => 'large' ), '' ),
			'index and follow'         => array(
				array(
					'index'  => true,
					'follow' => true,
				),
				'',
			),
			'noindex'                  => array(
				array(
					'noindex'           => true,
					'max-image-preview' => 'large',
				),
				'noindex, max-image-preview:large',
			),
			'noindex drops index'      => array(
				array(
					'index'    => true,
					'noindex'  => true,
					'nofollow' => true,
					'follow'   => true,
				),
				'noindex, nofollow',
			),
			'a smaller image preview'  => array( array( 'max-image-preview' => 'standard' ), 'max-image-preview:standard' ),
			'nosnippet'                => array( array( 'nosnippet' => true ), 'nosnippet' ),
			'a directive switched off' => array(
				array(
					'noindex'   => false,
					'noarchive' => true,
				),
				'noarchive',
			),
		);
	}

	/**
	 * @dataProvider yaml_values
	 */
	public function test_front_matter_values_are_valid_yaml( string $value, string $expected ): void {
		$this->load_response();

		$this->assertSame( $expected, Lightweight_SEO_Markdown_Response::yaml_scalar( $value ) );
	}

	public function yaml_values(): array {
		return array(
			'plain title'          => array( 'Markdown for Agents', 'Markdown for Agents' ),
			'address'              => array( 'https://example.com/a.jpg?x=1', 'https://example.com/a.jpg?x=1' ),
			'quotes inside'        => array( 'It’s the "best" – really', 'It’s the "best" – really' ),
			'non-Latin and emoji'  => array( 'Привет, 世界 👋', 'Привет, 世界 👋' ),
			'colon and space'      => array( 'Part 1: The start', '"Part 1: The start"' ),
			'trailing colon'       => array( 'Note:', '"Note:"' ),
			'leading quote'        => array( '"Quoted" title', '"\\"Quoted\\" title"' ),
			'leading single quote' => array( "'Tis", "\"'Tis\"" ),
			'leading dash'         => array( '- list?', '"- list?"' ),
			'leading hash'         => array( '#1 pick', '"#1 pick"' ),
			'comment-like'         => array( 'A #hashtag', '"A #hashtag"' ),
			'number'               => array( '2024', '"2024"' ),
			'boolean-like'         => array( 'Yes', '"Yes"' ),
			'null-like'            => array( '~', '"~"' ),
			'indicator'            => array( '@home', '"@home"' ),
			'backslash and tab'    => array( "a\\b\tc", '"a\\\\b\\tc"' ),
			'line breaks'          => array( "a\nb\u{2028}c", '"a\\nb\\Lc"' ),
			'control character'    => array( "a\x01b", '"a\\x01b"' ),
		);
	}

	public function test_front_matter_holds_only_fields_with_a_value(): void {
		$this->load_response();

		$this->assertSame(
			"---\ntitle: A\nimage: https://example.com/i.png\n---",
			Lightweight_SEO_Markdown_Response::front_matter(
				array(
					'title'       => 'A',
					'description' => ' ',
					'image'       => 'https://example.com/i.png',
				)
			)
		);
		$this->assertSame( '', Lightweight_SEO_Markdown_Response::front_matter( array( 'title' => '' ) ) );
	}

	public function test_a_post_is_answered_with_front_matter_title_content_and_json_ld(): void {
		$this->load_response();
		$this->view_post( 5, '<p>First <strong>point</strong>.</p><!-- wp:x --><script>x</script>' );
		$GLOBALS['lightweight_seo_test_filters']['the_content'] = static function ( $content ) {
			return $content . '<p>Added by a filter.</p>';
		};
		$GLOBALS['lightweight_seo_test_filters']['wp_robots']   = static function () {
			return array(
				'noindex'           => true,
				'max-image-preview' => 'large',
			);
		};

		$context  = $this->page_context(
			array(
				'description' => 'A short summary: with a colon.',
				'og_image'    => 'https://example.com/cover.png',
			)
		);
		$settings = new Lightweight_SEO_Settings();
		$response = ( new Lightweight_SEO_Markdown_Response( $settings, $context, $this->compatibility( true ) ) )->build( 'singular', get_post( 5 ), 'GET', '' );
		$graph    = ( new Lightweight_SEO_Schema_Service( $context, $settings ) )->get_graph();

		$this->assertNotEmpty( $graph );
		$this->assertSame( 200, $response['status'] );
		$this->assertSame(
			"---\ntitle: \"Hello: “World” – Test Site\"\ndescription: \"A short summary: with a colon.\"\nimage: https://example.com/cover.png\n---\n\n"
			. "# Hello: “World”\n\nFirst **point**.\n\nAdded by a filter.\n\n"
			. "```json\n" . wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $graph,
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			) . "\n```\n",
			$response['body']
		);
		$this->assertContains( array( 'Link', '<https://example.com/hello/>; rel="canonical"', false ), $response['headers'] );
		$this->assertContains( array( 'X-Robots-Tag', 'noindex, max-image-preview:large', false ), $response['headers'] );
		$this->assertNotContains( 'Content-Signal', array_column( $response['headers'], 0 ) );
	}

	public function test_what_a_filter_prints_instead_of_returning_stays_out_of_the_answer(): void {
		$this->load_response();
		$this->view_post( 5, '<p>Body.</p>' );
		$GLOBALS['lightweight_seo_test_filters']['the_content'] = static function ( $content ) {
			echo '<div>printed by a shortcode</div>';

			return $content;
		};

		$this->expectOutputString( '' );

		$body = ( new Lightweight_SEO_Markdown_Response( new Lightweight_SEO_Settings(), null, $this->compatibility( true ) ) )->build( 'singular', get_post( 5 ) )['body'];

		$this->assertStringNotContainsString( 'printed', $body );
	}

	public function test_without_schema_output_the_answer_has_no_json_ld(): void {
		$this->load_response();
		$this->view_post( 5, '<p>Body.</p>' );
		$GLOBALS['lightweight_seo_test_options'][ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'enable_schema_output' => '0' );

		$body = ( new Lightweight_SEO_Markdown_Response( new Lightweight_SEO_Settings(), $this->page_context(), $this->compatibility( true ) ) )->build( 'singular', get_post( 5 ) )['body'];

		$this->assertStringNotContainsString( '```json', $body );
		$this->assertStringEndsWith( "# Hello: “World”\n\nBody.\n", $body );
	}

	public function test_in_safe_mode_only_what_wordpress_itself_prints_is_used(): void {
		$this->load_response();
		$this->view_post( 5, '<p>Body.</p>' );

		$response = ( new Lightweight_SEO_Markdown_Response( new Lightweight_SEO_Settings(), $this->page_context( array( 'description' => 'Ours' ) ), $this->compatibility( false ) ) )->build( 'singular', get_post( 5 ) );

		// The title is what WordPress prints, through the other plugin's filter; no description, image, schema, or canonical of ours.
		$this->assertSame( "---\ntitle: \"Hello: “World” – Test Site\"\n---\n\n# Hello: “World”\n\nBody.\n", $response['body'] );
		$this->assertNotContains( 'Link', array_column( $response['headers'], 0 ) );
	}

	public function test_a_head_request_gets_headers_and_no_body(): void {
		$this->load_response();
		$this->view_post( 5, '<p>Body.</p>' );
		$GLOBALS['lightweight_seo_test_filters']['lightweight_seo_markdown_document'] = function () {
			$this->fail( 'No document is built for a HEAD request.' );
		};

		$response = ( new Lightweight_SEO_Markdown_Response( new Lightweight_SEO_Settings(), $this->page_context(), $this->compatibility( true ) ) )->build( 'singular', get_post( 5 ), 'HEAD' );

		$this->assertSame( '', $response['body'] );
		$this->assertContains( array( 'Content-Type', 'text/markdown; charset=UTF-8', true ), $response['headers'] );
	}

	public function test_the_document_filter_receives_the_post(): void {
		$this->load_response();
		$this->view_post( 5, '<p>Body.</p>' );
		$GLOBALS['lightweight_seo_test_filters']['lightweight_seo_markdown_document'] = static function ( $markdown, $post ) {
			return $markdown . 'Post ' . $post->ID . "\n";
		};

		$body = ( new Lightweight_SEO_Markdown_Response( new Lightweight_SEO_Settings(), null, $this->compatibility( true ) ) )->build( 'singular', get_post( 5 ) )['body'];

		$this->assertStringEndsWith( "Body.\nPost 5\n", $body );
	}

	public function test_homepage_lists_the_posts_of_the_main_query_with_their_excerpts(): void {
		$this->load_response();
		$GLOBALS['lightweight_seo_test_options'][ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'enable_schema_output' => '0' );
		$GLOBALS['lightweight_seo_test_query_state']['is_home']                 = true;
		$GLOBALS['lightweight_seo_test_query_state']['is_front_page']           = true;
		$GLOBALS['lightweight_seo_test_query_state']['document_title']          = 'Test Site &#8211; Test Tagline';
		$GLOBALS['lightweight_seo_test_loop']                                   = array(
			(object) array(
				'post_title'    => 'First &amp; <em>best</em>',
				'post_excerpt'  => '<p>An excerpt with *stars* and a <a href="#">Continue reading</a> link.</p>',
				'post_password' => '',
				'permalink'     => 'https://example.com/first/',
			),
			(object) array(
				'post_title'    => '1. Locked',
				'post_excerpt'  => 'There is no excerpt because this is a protected post.',
				'post_password' => 'secret',
				'permalink'     => 'https://example.com/locked (1)/',
			),
		);

		$body = ( new Lightweight_SEO_Markdown_Response( new Lightweight_SEO_Settings(), $this->page_context(), $this->compatibility( true ) ) )->build( 'home', null )['body'];

		$this->assertSame(
			"---\ntitle: Test Site – Test Tagline\n---\n\n# Test Site\n\n"
			. "- [First & best](https://example.com/first/): An excerpt with \\*stars\\* and a Continue reading link.\n"
			. "- [1\\. Locked](https://example.com/locked%20%281%29/)\n",
			$body
		);
		// The loop is rewound for the theme.
		$this->assertSame( 0, $GLOBALS['lightweight_seo_test_loop_index'] );
	}

	public function test_posts_page_is_headed_by_its_own_title(): void {
		$this->load_response();
		$GLOBALS['lightweight_seo_test_query_state']['title'] = 'Blog';

		$body = ( new Lightweight_SEO_Markdown_Response( new Lightweight_SEO_Settings(), null, $this->compatibility( true ) ) )->build( 'posts', get_post( 20 ) )['body'];

		$this->assertStringEndsWith( "# Blog\n", $body );
	}

	public function test_content_signal_header_follows_its_switch(): void {
		$this->view_post( 5, '<p>Body.</p>' );
		$this->switch_on( array( 'enable_content_signals' => '1' ) );

		$headers = $this->module()->build_markdown_response( 'singular', 'HEAD' )['headers'];

		$this->assertContains( array( 'Content-Signal', 'ai-train=no, search=yes, ai-input=yes', true ), $headers );

		$this->switch_on( array( 'enable_content_signals' => '0' ) );

		$this->assertNotContains( 'Content-Signal', array_column( $this->module()->build_markdown_response( 'singular', 'HEAD' )['headers'], 0 ) );
	}

	/* Helpers --------------------------------------------------------------- */

	private function module() {
		return new Lightweight_SEO_AI_Discovery_Module( new Lightweight_SEO_Settings(), null, 'admin', null, $this->page_context() );
	}

	private function switch_on( $extra = array() ) {
		$GLOBALS['lightweight_seo_test_options'][ LIGHTWEIGHT_SEO_OPTION_NAME ] = array_merge(
			array(
				'enable_ai_discovery'        => '1',
				'enable_markdown_on_request' => '1',
			),
			$extra
		);
	}

	private function view_post( $id, $content = '' ) {
		$GLOBALS['lightweight_seo_test_query_state']['is_singular']       = true;
		$GLOBALS['lightweight_seo_test_query_state']['is_single']         = true;
		$GLOBALS['lightweight_seo_test_query_state']['queried_object_id'] = $id;
		$GLOBALS['lightweight_seo_test_posts'][ $id ]->post_content       = $content;
		$GLOBALS['lightweight_seo_test_loop']                             = array( $GLOBALS['lightweight_seo_test_posts'][ $id ] );
		$GLOBALS['lightweight_seo_test_loop_index']                       = 0;
	}

	private function load_response() {
		require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-markdown-converter.php';
		require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-markdown-response.php';
	}

	private function page_context( $context = array() ) {
		return new class( $context ) {
			private $context;

			public function __construct( $context ) {
				$this->context = array_merge(
					array(
						'document_title' => 'Hello',
						'object_title'   => 'Hello',
						'description'    => '',
						'canonical_url'  => 'https://example.com/hello/',
						'robots'         => '',
						'og_title'       => 'Hello',
						'og_image'       => '',
						'og_type'        => 'article',
						'is_404'         => false,
					),
					$context
				);
			}

			public function get_context() {
				return $this->context;
			}
		};
	}

	private function compatibility( $allowed ) {
		return new class( $allowed ) {
			private $allowed;

			public function __construct( $allowed ) {
				$this->allowed = $allowed;
			}

			public function feature_output_allowed( $feature ) {
				return $this->allowed;
			}
		};
	}

	private function post( $id, $type, $status, $password, $url ) {
		return (object) array(
			'ID'            => $id,
			'post_type'     => $type,
			'post_status'   => $status,
			'post_password' => $password,
			'post_title'    => 'Post ' . $id,
			'post_content'  => '',
			'post_excerpt'  => '',
			'permalink'     => $url,
		);
	}
}
