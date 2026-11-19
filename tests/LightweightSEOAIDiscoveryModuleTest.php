<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-ai-discovery-module.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-post-meta.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOAIDiscoveryModuleTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_filters;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_filters = array();
		$lightweight_seo_test_options = array( 'blog_public' => 1 );
		$lightweight_seo_test_posts   = array();
	}

	protected function tearDown(): void {
		global $lightweight_seo_test_post_meta;

		$lightweight_seo_test_post_meta = array();
		unset( $GLOBALS['lightweight_seo_test_tagline'] );
	}

	public function test_virtual_robots_disallows_switched_off_crawlers_and_leaves_allowed_ones_to_the_general_rules(): void {
		$module = new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false ), null, 'admin' );
		$output = $module->filter_robots_txt( "User-agent: *\nDisallow: /wp-admin/\n", true );

		// A crawler with a group of its own ignores the group for every crawler, so an allowed one gets none.
		$this->assertStringNotContainsString( 'User-agent: OAI-SearchBot', $output );
		$this->assertStringNotContainsString( "\nAllow: /\n", $output );
		$this->assertStringContainsString( "# Lightweight SEO AI Discovery (experimental)\n# Not blocked here, so they follow the rules for all crawlers: OAI-SearchBot, ChatGPT-User, Claude-SearchBot,", $output );
		$this->assertStringContainsString( "User-agent: GPTBot\nDisallow: /", $output );
		$this->assertStringContainsString( "User-agent: Google-Extended\nDisallow: /", $output );

		// Running the filter again adds nothing.
		$this->assertSame( $output, $module->filter_robots_txt( $output, true ) );
	}

	/**
	 * @dataProvider switch_combinations
	 */
	public function test_every_switch_combination_keeps_the_general_rules_for_allowed_crawlers( $search, $training ): void {
		$general = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\nDisallow: /cart/\nDisallow: /checkout/\n\nSitemap: https://example.com/wp-sitemap.xml\n";
		$module  = new Lightweight_SEO_AI_Discovery_Module( $this->settings( $search, $training ), null, 'admin' );
		$output  = $module->filter_robots_txt( $general, true );
		$policy  = $module->get_crawler_policy();
		$allowed = array_keys( array_filter( $policy ) );
		$blocked = array_keys( array_diff_key( $policy, array_flip( $allowed ) ) );

		// The general rules are untouched and come first.
		$this->assertStringStartsWith( rtrim( $general ) . "\n\n# Lightweight SEO AI Discovery (experimental)\n", $output );

		// Only a crawler that is switched off has a group, and it disallows the whole site.
		preg_match_all( '/^User-agent: (.+)$/m', $output, $agents );
		$this->assertSame( array_merge( array( '*' ), $blocked ), $agents[1] );

		foreach ( $blocked as $token ) {
			$this->assertStringContainsString( "User-agent: {$token}\nDisallow: /\n", $output );
		}

		$this->assertSame( 1, substr_count( $output, "\nAllow: " ), 'The only Allow line is the general one.' );

		// No directive appears inside a comment the plugin writes, so a loose reader cannot mistake one for a rule.
		preg_match_all( '/^# .*$/m', substr( $output, strlen( rtrim( $general ) ) ), $comments );
		$this->assertNotEmpty( $comments[0] );
		$this->assertDoesNotMatchRegularExpression( '/(User-agent|Allow|Disallow|Sitemap)\s*:/i', implode( "\n", $comments[0] ) );

		// The allowed crawlers are named in a comment, which is printed only when there are any.
		if ( $allowed ) {
			$this->assertStringContainsString( "\n# Not blocked here, so they follow the rules for all crawlers: " . implode( ', ', $allowed ) . "\n", $output );
		} else {
			$this->assertStringNotContainsString( 'Not blocked here', $output );
		}

		// With both switches on, the plugin's part is the marker and the comment alone.
		if ( $search && $training ) {
			$this->assertStringEndsWith( "\n\n# Lightweight SEO AI Discovery (experimental)\n# Not blocked here, so they follow the rules for all crawlers: " . implode( ', ', array_keys( $policy ) ) . "\n", $output );
		}
	}

	public function switch_combinations(): array {
		return array(
			'search on, training off (defaults)' => array( true, false ),
			'both on'                            => array( true, true ),
			'search off, training on'            => array( false, true ),
			'both off'                           => array( false, false ),
		);
	}

	public function test_a_token_another_filter_names_is_left_out_of_the_group_and_the_comment(): void {
		$module = new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false ), null, 'admin' );
		$output = $module->filter_robots_txt( "User-agent: *\nDisallow: /wp-admin/\n\nUser-agent: OAI-SearchBot\nDisallow: /private/\n\nUser-agent: GPTBot\nAllow: /\n", true );

		$this->assertSame( 1, substr_count( $output, 'User-agent: OAI-SearchBot' ) );
		$this->assertSame( 1, substr_count( $output, 'User-agent: GPTBot' ) );
		$this->assertStringNotContainsString( ': OAI-SearchBot,', $output );
		$this->assertStringContainsString( 'the rules for all crawlers: ChatGPT-User, Claude-SearchBot', $output );
	}

	public function test_physical_robots_file_prevents_virtual_changes_and_is_reported(): void {
		global $lightweight_seo_test_filters;

		$path = tempnam( sys_get_temp_dir(), 'lightweight-seo-robots-' );
		$lightweight_seo_test_filters['lightweight_seo_physical_robots_path'] = static function () use ( $path ) {
			return $path;
		};
		$module = new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false ), null, 'admin' );
		$output = "User-agent: *\nDisallow:\n";

		$this->assertSame( $output, $module->filter_robots_txt( $output, true ) );
		$this->assertTrue( $module->get_conflicts( $output )['physical_robots'] );

		unlink( $path );
	}

	public function test_llms_txt_contains_only_valid_curated_page_summaries(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_posts = array(
			11 => $this->post( 11, 'publish', '', 'https://example.com/guide/' ),
			12 => $this->post( 12, 'draft', '', 'https://example.com/draft/' ),
			13 => $this->post( 13, 'publish', 'secret', 'https://example.com/private/' ),
			14 => $this->post( 14, 'publish', '', 'https://example.com/redirected/' ),
			15 => $this->post( 15, 'publish', '', 'https://external.example/page/' ),
			16 => $this->post( 16, 'publish', '', 'https://example.com/noindex/' ),
		);
		$lightweight_seo_test_options['lightweight_seo_generated_redirect_rules'] = array(
			array(
				'source' => '/redirected',
				'target' => '/guide',
				'status' => 301,
			),
		);
		$api    = new class() {
			public function get_object_context( $type, $id ) {
				$contexts = array(
					11 => array(
						'title'         => 'Public [Guide]',
						'description'   => '<b>A concise public summary.</b>',
						'canonical_url' => 'https://example.com/guide/',
						'indexable'     => true,
					),
					12 => array(
						'title'         => 'Draft',
						'description'   => 'Draft',
						'canonical_url' => 'https://example.com/draft/',
						'indexable'     => true,
					),
					13 => array(
						'title'         => 'Private',
						'description'   => 'Private',
						'canonical_url' => 'https://example.com/private/',
						'indexable'     => true,
					),
					14 => array(
						'title'         => 'Redirected',
						'description'   => 'Redirected',
						'canonical_url' => 'https://example.com/redirected/',
						'indexable'     => true,
					),
					15 => array(
						'title'         => 'External',
						'description'   => 'External',
						'canonical_url' => 'https://external.example/page/',
						'indexable'     => true,
					),
					16 => array(
						'title'         => 'Noindex',
						'description'   => 'Noindex',
						'canonical_url' => 'https://example.com/noindex/',
						'indexable'     => false,
					),
				);

				return $contexts[ $id ] ?? array();
			}
		};
		$module = new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false, array( 11, 12, 13, 14, 15, 16 ) ), $api, 'admin' );
		$output = $module->build_llms_txt();

		$this->assertStringContainsString( '[Public \\[Guide\\]](https://example.com/guide/): A concise public summary.', $output );
		$this->assertStringNotContainsString( 'Draft', $output );
		$this->assertStringNotContainsString( 'Private', $output );
		$this->assertStringNotContainsString( 'Redirected', $output );
		$this->assertStringNotContainsString( 'External', $output );
		$this->assertStringNotContainsString( 'Noindex', $output );
	}

	public function test_registry_holds_the_documented_tokens_and_their_purposes(): void {
		$registry = ( new Lightweight_SEO_AI_Discovery_Module( $this->settings(), null, 'admin' ) )->get_crawler_registry();

		$this->assertSame(
			array(
				'OAI-SearchBot'        => 'search',
				'ChatGPT-User'         => 'user',
				'GPTBot'               => 'training',
				'Claude-SearchBot'     => 'search',
				'Claude-User'          => 'user',
				'ClaudeBot'            => 'training',
				'PerplexityBot'        => 'search',
				'Perplexity-User'      => 'user',
				'Google-Extended'      => 'training',
				'Applebot-Extended'    => 'training',
				'Meta-ExternalAgent'   => 'training',
				'Meta-WebIndexer'      => 'search',
				'Meta-ExternalFetcher' => 'user',
				'Amazonbot'            => 'training',
				'Amzn-SearchBot'       => 'search',
				'Amzn-User'            => 'user',
				'DuckAssistBot'        => 'search',
				'MistralAI-User'       => 'user',
				'MistralAI-Index'      => 'search',
				'MistralAI-Training'   => 'training',
			),
			array_map(
				static function ( $definition ) {
					return $definition['purpose'];
				},
				$registry
			)
		);

		// docs/ai-crawlers.md records the source of every token, so a refresh starts from there.
		$doc = (string) file_get_contents( dirname( __DIR__ ) . '/docs/ai-crawlers.md' );

		preg_match( '/## Tokens\n(.*?)\n## /s', $doc, $section );
		preg_match_all( '/^\| `([^`]+)` \| ([^|]+) \| (\w+) \|.*\| (https:\/\/\S+) \|$/m', $section[1] ?? '', $rows, PREG_SET_ORDER );

		$documented = array();

		foreach ( $rows as $row ) {
			$documented[ $row[1] ] = array(
				'vendor'  => trim( $row[2] ),
				'purpose' => $row[3],
			);
		}

		$this->assertSame( $registry, $documented );
	}

	public function test_new_tokens_follow_the_switch_of_their_purpose(): void {
		$closed = ( new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false ), null, 'admin' ) )->filter_robots_txt( '', true );
		$open   = ( new Lightweight_SEO_AI_Discovery_Module( $this->settings( false, true ), null, 'admin' ) )->filter_robots_txt( '', true );

		foreach ( array( 'Applebot-Extended', 'Meta-ExternalAgent', 'Amazonbot', 'MistralAI-Training' ) as $training ) {
			$this->assertStringContainsString( "User-agent: {$training}\nDisallow: /", $closed );
			$this->assertStringNotContainsString( "User-agent: {$training}\n", $open );
			$this->assertMatchesRegularExpression( '/^# Not blocked here.*\b' . preg_quote( $training, '/' ) . '\b/m', $open );
		}

		foreach ( array( 'DuckAssistBot', 'MistralAI-User', 'MistralAI-Index', 'Meta-WebIndexer', 'Meta-ExternalFetcher', 'Amzn-SearchBot', 'Amzn-User' ) as $search ) {
			$this->assertStringNotContainsString( "User-agent: {$search}\n", $closed );
			$this->assertMatchesRegularExpression( '/^# Not blocked here.*\b' . preg_quote( $search, '/' ) . '\b/m', $closed );
			$this->assertStringContainsString( "User-agent: {$search}\nDisallow: /", $open );
		}

		$this->assertStringNotContainsString( 'CCBot', $closed );
		$this->assertStringNotContainsString( 'OAI-AdsBot', $closed );
		$this->assertStringNotContainsString( 'Claude-Agent', $closed );
	}

	public function test_registry_rejects_invalid_extension_definitions(): void {
		global $lightweight_seo_test_filters;

		$lightweight_seo_test_filters['lightweight_seo_ai_crawler_registry'] = static function ( $registry ) {
			$registry['ValidBot']    = array(
				'vendor'  => 'Test',
				'purpose' => 'search',
			);
			$registry['Invalid Bot'] = array(
				'vendor'  => 'Test',
				'purpose' => 'search',
			);
			$registry['UnknownBot']  = array(
				'vendor'  => 'Test',
				'purpose' => 'unknown',
			);

			return $registry;
		};
		$registry = ( new Lightweight_SEO_AI_Discovery_Module( $this->settings(), null, 'admin' ) )->get_crawler_registry();

		$this->assertArrayHasKey( 'ValidBot', $registry );
		$this->assertArrayNotHasKey( 'Invalid Bot', $registry );
		$this->assertArrayNotHasKey( 'UnknownBot', $registry );
	}

	public function test_existing_llms_rewrite_rule_is_reported_as_an_endpoint_conflict(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options['rewrite_rules'] = array( '^llms\.txt/?$' => 'index.php?third_party_llms=1' );
		$module                                        = new Lightweight_SEO_AI_Discovery_Module( $this->settings(), null, 'admin' );

		$this->assertTrue( $module->get_conflicts( '' )['llms_endpoint'] );
	}

	public function test_llms_txt_summary_is_the_homepage_description_then_the_tagline(): void {
		$with_description = new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false, array(), array( 'meta_description' => 'Handmade [oak] furniture, made to order.' ) ), null, 'admin' );
		$output           = $with_description->build_llms_txt();

		$this->assertStringStartsWith( "# Test Site\n\n> Handmade \\[oak\\] furniture, made to order.\n", $output );
		$this->assertStringNotContainsString( 'Google Search ignores', $output );

		$with_tagline = new Lightweight_SEO_AI_Discovery_Module( $this->settings(), null, 'admin' );

		$this->assertSame( "# Test Site\n\n> Test Tagline\n", $with_tagline->build_llms_txt() );

		$GLOBALS['lightweight_seo_test_tagline'] = '';

		$this->assertSame( "# Test Site\n", $with_tagline->build_llms_txt() );
	}

	public function test_static_front_page_is_listed_as_the_homepage_prints_it(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$previous_title                            = $lightweight_seo_test_query_state['title'];
		$lightweight_seo_test_query_state['title'] = 'Home Page';
		$lightweight_seo_test_options             += array(
			'show_on_front' => 'page',
			'page_on_front' => 21,
		);
		$lightweight_seo_test_posts                = array(
			21 => $this->post( 21, 'publish', '', 'https://example.com/' ),
			22 => $this->post( 22, 'publish', '', 'https://example.com/about/' ),
		);
		$api                                       = new class() {
			public function get_object_context( $type, $id ) {
				// The page text lives in theme templates, so nothing can be summarized from the content.
				return array(
					'title'         => 21 === $id ? 'Home Page' : 'About',
					'description'   => '',
					'canonical_url' => 21 === $id ? 'https://example.com/' : 'https://example.com/about/',
					'indexable'     => true,
				);
			}
		};
		$module                                    = new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false, array( 21, 22 ), array( 'meta_description' => 'Handmade oak furniture.' ) ), $api, 'admin', new Lightweight_SEO_Post_Meta() );
		$output                                    = $module->build_llms_txt();

		$lightweight_seo_test_query_state['title'] = $previous_title;

		$this->assertStringContainsString( "- [Test Site – Test Tagline](https://example.com/): Handmade oak furniture.\n", $output );
		$this->assertStringContainsString( "- [About](https://example.com/about/)\n", $output );
		$this->assertStringNotContainsString( 'Home Page', $output );
	}

	public function test_static_front_page_keeps_its_own_seo_title_and_description(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_post_meta;

		$lightweight_seo_test_options  += array(
			'show_on_front' => 'page',
			'page_on_front' => 21,
		);
		$lightweight_seo_test_posts     = array( 21 => $this->post( 21, 'publish', '', 'https://example.com/' ) );
		$lightweight_seo_test_post_meta = array(
			21 => array(
				'_lightweight_seo_title'       => 'Oak furniture %sep% %sitename%',
				'_lightweight_seo_description' => 'Our own words.',
			),
		);
		$api                            = new class() {
			public function get_object_context( $type, $id ) {
				return array(
					'title'         => 'Oak furniture – Test Site',
					'description'   => 'Our own words.',
					'canonical_url' => 'https://example.com/',
					'indexable'     => true,
				);
			}
		};
		$module                         = new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false, array( 21 ), array( 'meta_description' => 'Handmade oak furniture.' ) ), $api, 'admin', new Lightweight_SEO_Post_Meta() );

		$this->assertStringContainsString( "- [Oak furniture – Test Site](https://example.com/): Our own words.\n", $module->build_llms_txt() );
	}

	public function test_listing_a_static_front_page_registers_no_hook(): void {
		global $lightweight_seo_test_hooks;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_options += array(
			'show_on_front' => 'page',
			'page_on_front' => 21,
		);
		$lightweight_seo_test_posts    = array( 21 => $this->post( 21, 'publish', '', 'https://example.com/' ) );
		$api                           = new class() {
			public function get_object_context( $type, $id ) {
				return array(
					'title'         => 'Home Page',
					'description'   => '',
					'canonical_url' => 'https://example.com/',
					'indexable'     => true,
				);
			}
		};
		$module                        = new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false, array( 21 ) ), $api, 'admin', new Lightweight_SEO_Post_Meta() );
		$hooks_before                  = count( $lightweight_seo_test_hooks );

		// The readiness checks build the entries on every render of the Modules tab.
		$module->get_curated_entries();
		$module->get_curated_entries();
		$module->get_readiness_report();

		$this->assertSame( 'Test Site – Test Tagline', $module->get_curated_entries()[0]['title'] );
		$this->assertSame( $hooks_before, count( $lightweight_seo_test_hooks ) );
	}

	public function test_served_robots_expectations_follow_the_switches_and_the_install(): void {
		global $lightweight_seo_test_filters;
		global $lightweight_seo_test_home_url;
		global $lightweight_seo_test_options;

		$api      = new class() {
			public function get_sitemap_urls() {
				return array( 'https://example.com/wp-sitemap.xml' );
			}
		};
		$module   = new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false ), $api, 'admin' );
		$expected = $module->get_served_robots_expectations();

		$this->assertSame( 'https://example.com/robots.txt', $expected['url'] );
		$this->assertSame( '', $expected['subdirectory'] );
		$this->assertSame( array( 'Googlebot', 'Bingbot', '*' ), $expected['crawlers'] );
		$this->assertSame( 'Sitemap: https://example.com/wp-sitemap.xml', $expected['sitemapLine'] );
		$this->assertSame( array_keys( $module->get_crawler_registry() ), array_keys( $expected['rules'] ) );
		$this->assertTrue( $expected['rules']['OAI-SearchBot'] );
		$this->assertTrue( $expected['rules']['Claude-User'] );
		$this->assertFalse( $expected['rules']['GPTBot'] );
		$this->assertFalse( $expected['rules']['Google-Extended'] );
		// The served-file check recognises the plugin's part by its opening comment.
		$this->assertSame( '# Lightweight SEO AI Discovery (experimental)', $expected['marker'] );
		$this->assertStringContainsString( $expected['marker'], $module->filter_robots_txt( '', true ) );

		// A token another filter already writes is that filter's rule, not the plugin's.
		$lightweight_seo_test_filters['robots_txt'] = static function () {
			return "User-agent: GPTBot\nAllow: /\n";
		};
		$this->assertArrayNotHasKey( 'GPTBot', $module->get_served_robots_expectations()['rules'] );
		unset( $lightweight_seo_test_filters['robots_txt'] );

		// A physical file is served instead of the plugin's rules.
		$path = tempnam( sys_get_temp_dir(), 'lightweight-seo-robots-' );
		$lightweight_seo_test_filters['lightweight_seo_physical_robots_path'] = static function () use ( $path ) {
			return $path;
		};
		$this->assertSame( array(), $module->get_served_robots_expectations()['rules'] );
		$this->assertSame( '', $module->get_served_robots_expectations()['marker'] );
		unset( $lightweight_seo_test_filters['lightweight_seo_physical_robots_path'] );
		unlink( $path );

		// A site that asks not to be indexed gets neither rules nor a Sitemap line from WordPress.
		$lightweight_seo_test_options['blog_public'] = 0;
		$this->assertSame( array(), $module->get_served_robots_expectations()['rules'] );
		$this->assertSame( '', $module->get_served_robots_expectations()['sitemapLine'] );
		$lightweight_seo_test_options['blog_public'] = 1;

		// Crawlers read robots.txt at the root of the host, which a subdirectory install does not answer.
		$previous_home                 = $lightweight_seo_test_home_url;
		$lightweight_seo_test_home_url = 'http://example.com:8080/blog';
		$subdirectory                  = $module->get_served_robots_expectations();
		$lightweight_seo_test_home_url = $previous_home;

		$this->assertSame( 'http://example.com:8080/robots.txt', $subdirectory['url'] );
		$this->assertSame( '/blog', $subdirectory['subdirectory'] );
		$this->assertSame( array(), $subdirectory['rules'] );
		$this->assertSame( 'Sitemap: https://example.com/wp-sitemap.xml', $subdirectory['sitemapLine'] );
	}

	public function test_content_signal_goes_inside_the_group_for_every_crawler_when_switched_on(): void {
		$wordpress = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n\nSitemap: https://example.com/wp-sitemap.xml\n";
		$on        = array( 'enable_content_signals' => '1' );

		// Off by default: the file is as before.
		$this->assertStringNotContainsString( 'Content-Signal', ( new Lightweight_SEO_AI_Discovery_Module( $this->settings(), null, 'admin' ) )->filter_robots_txt( $wordpress, true ) );

		$output = ( new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false, array(), $on ), null, 'admin' ) )->filter_robots_txt( $wordpress, true );

		$this->assertStringStartsWith( "User-agent: *\n# Content signals, as defined at https://contentsignals.org/\nContent-Signal: ai-train=no, search=yes, ai-input=yes\nDisallow: /wp-admin/\n", $output );
		$this->assertSame( 1, substr_count( $output, 'Content-Signal:' ) );
		$this->assertStringContainsString( "User-agent: GPTBot\nDisallow: /", $output );

		// The values follow the two crawler switches.
		$this->assertStringContainsString( 'Content-Signal: ai-train=yes, search=yes, ai-input=no', ( new Lightweight_SEO_AI_Discovery_Module( $this->settings( false, true, array(), $on ), null, 'admin' ) )->filter_robots_txt( $wordpress, true ) );

		// User-agent lines that share the group stay together, so no rule changes group.
		$shared = ( new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false, array(), $on ), null, 'admin' ) )->filter_robots_txt( "User-agent: *\nUser-agent: ExampleBot\nDisallow: /private/\n", true );

		$this->assertStringStartsWith( "User-agent: *\nUser-agent: ExampleBot\n# Content signals", $shared );

		// Without a group for every crawler, one is added that allows what was allowed anyway.
		$added = ( new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false, array(), $on ), null, 'admin' ) )->filter_robots_txt( "User-agent: ExampleBot\nDisallow: /\n", true );

		$this->assertStringStartsWith( "User-agent: ExampleBot\nDisallow: /\n\nUser-agent: *\n# Content signals, as defined at https://contentsignals.org/\nContent-Signal: ai-train=no, search=yes, ai-input=yes\nAllow: /\n", $added );

		// A file that already states a signal is left alone, and a site that asks not to be indexed gets none.
		$existing = "User-agent: *\ncontent-signal: search=yes\nAllow: /\n";
		$module   = new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false, array(), $on ), null, 'admin' );

		$this->assertSame( 1, substr_count( strtolower( $module->filter_robots_txt( $existing, true ) ), 'content-signal:' ) );
		$this->assertSame( $wordpress, $module->filter_robots_txt( $wordpress, false ) );
		$this->assertSame( 'ai-train=no, search=yes, ai-input=yes', $module->get_served_robots_expectations()['contentSignal'] );
		$this->assertSame( '', ( new Lightweight_SEO_AI_Discovery_Module( $this->settings(), null, 'admin' ) )->get_served_robots_expectations()['contentSignal'] );
	}

	public function test_readiness_report_states_facts_in_four_statuses_and_names_what_is_missing(): void {
		$rows = function ( $module ) {
			$found = array();

			foreach ( $module->get_readiness_report() as $area ) {
				foreach ( $area['rows'] as $row ) {
					$found[ $row['id'] ] = $row;
				}
			}

			return $found;
		};

		$plain = $rows( new Lightweight_SEO_AI_Discovery_Module( $this->settings(), null, 'admin' ) );

		$this->assertSame( array( 'off', 'Disallowed, as chosen on this card.' ), array( $plain['training']['state'], $plain['training']['text'] ) );
		$this->assertSame( array( 'warning', array( '0 of 0 selected pages are listed: published, public, indexable, canonical, on this site, and not redirected.', '0 of 0 listed pages have a description.' ) ), array( $plain['llms_pages']['state'], $plain['llms_pages']['text'] ) );
		$this->assertSame( 'off', $rows( new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false, array(), array( 'llms' => false ) ), null, 'admin' ) )['llms_pages']['state'] );
		$this->assertSame( array( 'warning', 'The Organization node is missing: logo, description, profile links. Add them on the “Site identity” card.' ), array( $plain['identity']['state'], $plain['identity']['text'] ) );
		$this->assertSame( 'browser', $plain['apiCatalog']['state'] );
		$this->assertSame( 'https://example.com/.well-known/api-catalog', $plain['apiCatalog']['url'] );

		foreach ( array( 'dnsAid', 'webBotAuth', 'openApi', 'webMcp', 'oauth', 'a2a', 'commerce' ) as $id ) {
			$this->assertSame( 'na', $plain[ $id ]['state'], $id );
			$this->assertMatchesRegularExpression( '/^[^.]+\.$/', $plain[ $id ]['text'], $id . ' gives one sentence.' );
			$this->assertStringContainsString( 'Lightweight SEO', $plain[ $id ]['text'], $id . ' says what the plugin does about it.' );
			$this->assertStringNotContainsString( 'publishes content', $plain[ $id ]['text'], $id . ' claims nothing about what the site runs.' );
		}

		// A complete identity passes; a person without a photo names the photo, not a logo.
		$complete = $rows(
			new Lightweight_SEO_AI_Discovery_Module(
				$this->settings(
					true,
					true,
					array(),
					array(
						'identity_logo'        => 'https://example.com/logo.png',
						'identity_description' => 'Makes oak furniture.',
						'same_as'              => array( 'https://example.org/oak' ),
					)
				),
				null,
				'admin'
			)
		);

		$this->assertSame( 'success', $complete['identity']['state'] );
		$this->assertSame( 'success', $complete['training']['state'] );

		$person = $rows(
			new Lightweight_SEO_AI_Discovery_Module(
				$this->settings(
					true,
					false,
					array(),
					array(
						'identity_type' => 'person',
						'social_image'  => 'https://example.com/share.jpg',
					)
				),
				null,
				'admin'
			)
		);

		$this->assertStringStartsWith( 'The Person node is missing: photo, description', $person['identity']['text'] );

		// Switching schema output off is a choice, not a problem.
		$this->assertSame( 'off', $rows( new Lightweight_SEO_AI_Discovery_Module( $this->settings( true, false, array(), array( 'schema' => false ) ), null, 'admin' ) )['identity']['state'] );
	}

	public function test_who_writes_robots_txt_states_only_what_the_server_can_know(): void {
		global $lightweight_seo_test_filters;
		global $lightweight_seo_test_options;

		$owner = function () {
			foreach ( ( new Lightweight_SEO_AI_Discovery_Module( $this->settings(), null, 'admin' ) )->get_readiness_report() as $area ) {
				foreach ( $area['rows'] as $row ) {
					if ( 'robots_owner' === $row['id'] ) {
						return $row;
					}
				}
			}

			return array();
		};

		// Nothing in the way: a neutral fact, never "passed", and never that WordPress writes the file.
		$row = $owner();

		$this->assertSame( 'info', $row['state'] );
		$this->assertStringStartsWith( 'No robots.txt file is on disk, and no other plugin the server can see adds rules for these crawlers.', $row['text'] );
		$this->assertStringNotContainsString( 'WordPress writes', $row['text'] );
		// The browser settles it from the served file.
		$this->assertSame( 'rules', $row['attributes']['data-settled-by'] );
		$this->assertStringContainsString( 'the served file has the part Lightweight SEO adds', $row['attributes']['data-msg-present'] );
		$this->assertStringContainsString( 'but the served file does not have the part Lightweight SEO adds', $row['attributes']['data-msg-absent'] );

		// With no rules to expect, there is nothing for the browser to settle.
		$lightweight_seo_test_options['blog_public'] = 0;
		$this->assertArrayNotHasKey( 'attributes', $owner() );
		$this->assertSame( 'info', $owner()['state'] );
		$lightweight_seo_test_options['blog_public'] = 1;

		// What the server can see in the way stays a warning.
		$lightweight_seo_test_filters['robots_txt'] = static function () {
			return "User-agent: GPTBot\nAllow: /\n";
		};
		$this->assertSame( array( 'warning', 'Another robots.txt filter already writes rules for GPTBot, so Lightweight SEO leaves them alone.' ), array( $owner()['state'], $owner()['text'] ) );
		unset( $lightweight_seo_test_filters['robots_txt'] );
	}

	private function settings( $search = true, $training = false, $ids = array(), $values = array() ) {
		return new class( $search, $training, $ids, $values ) {
			private $search;
			private $training;
			private $ids;
			private $values;

			public function __construct( $search, $training, $ids, $values ) {
				$this->search   = $search;
				$this->training = $training;
				$this->ids      = $ids;
				$this->values   = $values;
			}

			public function get( $key, $fallback = null ) {
				return $this->values[ $key ] ?? $fallback;
			}

			public function get_identity_type() {
				return $this->values['identity_type'] ?? 'organization';
			}

			public function get_identity_name() {
				return $this->values['identity_name'] ?? 'Test Site';
			}

			public function get_identity_logo_url() {
				return $this->values['identity_logo'] ?? '';
			}

			public function get_identity_description() {
				return $this->values['identity_description'] ?? '';
			}

			public function get_organization_same_as() {
				return $this->values['same_as'] ?? array();
			}

			public function get_social_image_url() {
				return $this->values['social_image'] ?? '';
			}

			public function content_signals_enabled() {
				return '1' === (string) ( $this->values['enable_content_signals'] ?? '0' );
			}

			public function get_home_title_format() {
				return '%sitename% %sep% %tagline%';
			}

			public function get_title_separator() {
				return '–';
			}

			public function ai_search_crawlers_enabled() {
				return $this->search;
			}

			public function ai_training_crawlers_enabled() {
				return $this->training;
			}

			public function llms_txt_enabled() {
				return $this->values['llms'] ?? true;
			}

			public function get_llms_post_ids() {
				return $this->ids;
			}

			public function get_manual_redirect_rules() {
				return array();
			}

			public function schema_output_enabled() {
				return $this->values['schema'] ?? true;
			}
		};
	}

	private function post( $id, $status, $password, $url ) {
		return (object) array(
			'ID'            => $id,
			'post_type'     => 'page',
			'post_status'   => $status,
			'post_password' => $password,
			'permalink'     => $url,
		);
	}
}
