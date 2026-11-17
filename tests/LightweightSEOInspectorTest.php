<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-module-state.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-module-registry.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-api.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-page-context-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-meta-tags-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-schema-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-ui.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-inspector.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOInspectorTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_authors;
		global $lightweight_seo_test_categories;
		global $lightweight_seo_test_filters;
		global $lightweight_seo_test_home_url;
		global $lightweight_seo_test_nonce_is_valid;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;
		global $lightweight_seo_test_user_can;
		global $wp;

		$lightweight_seo_test_query_state    = array(
			'is_singular'          => false,
			'is_single'            => false,
			'is_author'            => false,
			'is_home'              => false,
			'is_front_page'        => false,
			'is_category'          => false,
			'is_tag'               => false,
			'is_tax'               => false,
			'is_archive'           => false,
			'is_search'            => false,
			'is_date'              => false,
			'is_post_type_archive' => false,
			'is_404'               => false,
			'queried_object_id'    => 0,
			'search_query'         => '',
			'permalink'            => 'https://example.com/a-post/',
			'title'                => 'A post',
			'thumbnail_url'        => '',
			'current_request'      => '',
			'queried_object'       => null,
			'post_author'          => 17,
			'published_date'       => '2024-01-01T12:00:00+00:00',
			'modified_date'        => '2024-01-02T12:00:00+00:00',
			'document_title'       => 'A post &#8211; Test Site',
			'wp_canonical'         => 'https://example.com/a-post/',
		);
		$lightweight_seo_test_authors        = array( 17 => array( 'display_name' => 'Author Name' ) );
		$lightweight_seo_test_categories     = array();
		$lightweight_seo_test_filters        = array();
		$lightweight_seo_test_home_url       = 'https://example.com';
		$lightweight_seo_test_nonce_is_valid = true;
		$lightweight_seo_test_options        = array( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME => array() );
		$lightweight_seo_test_posts          = array();
		$lightweight_seo_test_user_can       = true;
		$wp                                  = (object) array( 'request' => '' );
		$_SERVER['REQUEST_URI']              = '/a-post/?lightweight_seo_inspect=fragment&_wpnonce=abc';
		$_GET                                = array(
			'lightweight_seo_inspect' => 'fragment',
			'_wpnonce'                => 'abc',
		);
	}

	protected function tearDown(): void {
		global $lightweight_seo_test_filters;

		$lightweight_seo_test_filters = array();
		$_GET                         = array();
		$_SERVER['REQUEST_URI']       = '/';
	}

	/**
	 * Build an inspector over the real services, for one set of stored values.
	 *
	 * @param array $options    Stored settings that differ from the defaults.
	 * @param array $meta       Stored SEO meta of the queried post, term, or author.
	 * @param array $suppressed Outputs held back by safe mode.
	 */
	private function get_inspector( array $options = array(), array $meta = array(), array $suppressed = array() ) {
		global $lightweight_seo_test_filters;
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = $options;

		$settings      = new Lightweight_SEO_Settings();
		$post_meta     = new class( $meta ) {
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

			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}
		};
		$archive_meta  = new class( $meta ) {
			private $meta;

			public function __construct( array $meta ) {
				$this->meta = $meta;
			}

			public function get_term_all( $term_id ) {
				return $this->meta;
			}

			public function get_user_all( $user_id ) {
				return $this->meta;
			}

			public function get_supported_taxonomies() {
				return array( 'category', 'post_tag' );
			}
		};
		$compatibility = new class( $suppressed ) {
			private $suppressed;

			public function __construct( array $suppressed ) {
				$this->suppressed = $suppressed;
			}

			public function get_conflicting_plugins() {
				return empty( $this->suppressed ) ? array() : array( 'Other SEO' );
			}

			public function get_suppressed_features() {
				return $this->suppressed;
			}

			public function feature_output_allowed( $feature ) {
				return ! in_array( $feature, $this->suppressed, true );
			}
		};
		$page_context  = new Lightweight_SEO_Page_Context_Service( $settings, $post_meta, $archive_meta );
		$meta_tags     = new Lightweight_SEO_Meta_Tags_Service( $page_context );
		$schema        = new Lightweight_SEO_Schema_Service( $page_context, $settings );
		$api           = new Lightweight_SEO_API( $page_context, $post_meta, $archive_meta, new Lightweight_SEO_Module_Registry( new Lightweight_SEO_Module_State() ), $settings );

		// WordPress builds its robots meta tag from this filter, which the plugin is hooked to unless safe mode holds it back.
		if ( ! in_array( 'robots', $suppressed, true ) ) {
			$lightweight_seo_test_filters['wp_robots'] = array( $meta_tags, 'filter_robots' );
		}

		return new class( $api, $meta_tags, $schema, $settings, $post_meta, $archive_meta, $compatibility ) extends Lightweight_SEO_Inspector {
			public $headers_sent = 0;
			public $finished     = 0;

			protected function send_headers() {
				++$this->headers_sent;
			}

			protected function finish() {
				++$this->finished;
			}
		};
	}

	/**
	 * Set the request up as a single post.
	 *
	 * @param string $post_type Post type of the queried post.
	 * @param array  $post      Extra post fields.
	 */
	private function view_post( $post_type = 'post', array $post = array() ) {
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular']       = true;
		$lightweight_seo_test_query_state['is_single']         = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 42;
		$lightweight_seo_test_posts[42]                        = (object) array_merge(
			array(
				'ID'           => 42,
				'post_type'    => $post_type,
				'post_status'  => 'publish',
				'post_excerpt' => 'A short excerpt.',
				'post_content' => '',
				'permalink'    => 'https://example.com/a-post/',
			),
			$post
		);
	}

	/**
	 * Flatten a report to "Row title: line | line" strings, for readable assertions.
	 *
	 * @param array $report Report from the inspector.
	 */
	private function lines( array $report ) {
		$lines = array();

		foreach ( $report['sections'] as $section ) {
			foreach ( $section['rows'] as $row ) {
				$lines[ $section['title'] . ' / ' . $row['title'] ] = $row['state'] . ': ' . implode( ' | ', $row['lines'] );
			}
		}

		return $lines;
	}

	public function test_a_post_with_nothing_set_reports_the_defaults_and_their_sources(): void {
		$this->view_post();

		$report = $this->get_inspector()->get_report();
		$lines  = $this->lines( $report );

		$this->assertSame( 'https://example.com/a-post/', $report['address'], 'The arguments that asked for the report are not part of the address.' );
		$this->assertSame( array(), $report['features'] );
		$this->assertSame( 'info: A single item of the content type “Posts”.', $lines['Page / What WordPress found'] );
		$this->assertSame( 'success: Search engines may show this page.', $lines['Search results / Search results'] );
		$this->assertSame( 'info: max-image-preview:large', $lines['Search results / Robots directives'] );
		$this->assertSame( 'success: A post – Test Site | From the default title format. | 18 characters.', $lines['Title / Title tag'] );
		$this->assertSame( 'success: A short excerpt. | A short summary of the excerpt or content, because the page has no description of its own. | 16 characters.', $lines['Description / Meta description'] );
		$this->assertSame( 'info: https://example.com/a-post/ | The page’s own address.', $lines['Canonical / Canonical address'] );
		$this->assertSame( 'info: No image is sent, so a share shows text only.', $lines['Sharing and other tags / Share image'] );
		$this->assertStringStartsWith( 'success: Lightweight SEO does not leave it out.', $lines['Sitemap / XML sitemap'] );
		$this->assertSame( 'success: Organization, WebSite, WebPage, BreadcrumbList, Article', $lines['Structured data / Schema'] );
	}

	public function test_the_report_lists_the_tags_and_graph_exactly_as_they_are_printed(): void {
		$this->view_post();

		$inspector = $this->get_inspector( array( 'x_handle' => 'example' ) );
		$report    = $inspector->get_report();
		$sharing   = $report['sections'][5];
		$schema    = $report['sections'][7];

		$this->assertSame( 'Sharing and other tags', $sharing['title'] );
		$this->assertSame( 'A post – Test Site', $sharing['tags']['og:title'] );
		$this->assertSame( 'article', $sharing['tags']['og:type'] );
		$this->assertSame( 'summary', $sharing['tags']['twitter:card'] );
		$this->assertSame( '@example', $sharing['tags']['twitter:site'] );
		$this->assertArrayNotHasKey( 'description', $sharing['tags'], 'The description has a section of its own.' );

		$graph = json_decode( $schema['code'], true );

		$this->assertSame( 'https://schema.org', $graph['@context'] );
		$this->assertSame( array( 'Organization', 'WebSite', 'WebPage', 'BreadcrumbList', 'Article' ), array_column( $graph['@graph'], '@type' ) );
	}

	public function test_own_fields_are_named_as_the_source(): void {
		$this->view_post();

		$lines = $this->lines(
			$this->get_inspector(
				array(),
				array(
					'seo_title'         => 'Own title',
					'seo_description'   => 'Own description.',
					'seo_canonical_url' => 'https://example.com/other/',
				)
			)->get_report()
		);

		$this->assertStringContainsString( 'From this page’s own SEO title.', $lines['Title / Title tag'] );
		$this->assertSame( 'success: Own description. | From this page’s own meta description. | 16 characters.', $lines['Description / Meta description'] );
		$this->assertStringContainsString( 'A custom address, set in the SEO settings of this page or archive.', $lines['Canonical / Canonical address'] );
	}

	public function test_every_reason_a_page_is_hidden_is_named(): void {
		global $lightweight_seo_test_options;

		$this->view_post( 'post', array( 'post_password' => 'secret' ) );

		$hidden_type = array( 'content_types' => array( 'post_types' => array( 'post' => array( 'noindex' => '1' ) ) ) );
		$inspector   = $this->get_inspector( $hidden_type );

		$lightweight_seo_test_options['blog_public'] = '0';

		$lines = $this->lines( $inspector->get_report() );

		$this->assertStringStartsWith( 'warning: Search engines are asked not to show this page.', $lines['Search results / Search results'] );
		$this->assertStringContainsString( 'WordPress is set to discourage search engines', $lines['Search results / Search results'] );
		$this->assertStringContainsString( 'The page is protected by a password.', $lines['Search results / Search results'] );
		$this->assertStringContainsString( '“Posts” is hidden on the “Content and indexation” tab, and this one follows that.', $lines['Search results / Search results'] );
		$this->assertStringContainsString( 'noindex', $lines['Search results / Robots directives'] );
		$this->assertSame( 'info: Left out. | It is hidden from search results, and hidden pages are left out of the sitemap.', $lines['Sitemap / XML sitemap'] );

		// Its own choice is a different reason from its content type.
		$this->view_post();
		$own = $this->lines( $this->get_inspector( array(), array( 'seo_noindex' => '1' ) )->get_report() );

		$this->assertStringContainsString( 'This page is set to “Hide from search results” in its SEO settings.', $own['Search results / Search results'] );
		$this->assertStringNotContainsString( 'follows that', $own['Search results / Search results'] );
	}

	public function test_a_page_set_to_show_inside_a_hidden_content_type_is_reported_as_an_exception(): void {
		$this->view_post();

		$lines = $this->lines(
			$this->get_inspector(
				array( 'content_types' => array( 'post_types' => array( 'post' => array( 'noindex' => '1' ) ) ) ),
				array( 'seo_noindex' => 'index' )
			)->get_report()
		);

		$this->assertSame( 'success: Search engines may show this page.', $lines['Search results / Search results'] );
		$this->assertSame( 'info: Its content type is hidden from search results, but this one is set to “Show in search results”.', $lines['Search results / Exception'] );
	}

	public function test_a_directive_from_elsewhere_is_not_blamed_on_the_plugin(): void {
		global $lightweight_seo_test_filters;

		$this->view_post();

		$inspector                                 = $this->get_inspector();
		$lightweight_seo_test_filters['wp_robots'] = static function () {
			return array(
				'noindex'           => true,
				'max-image-preview' => 'large',
			);
		};

		$lines = $this->lines( $inspector->get_report() );

		$this->assertStringContainsString( 'Lightweight SEO does not hide it', $lines['Search results / Search results'] );
		$this->assertSame( 'info: noindex, max-image-preview:large', $lines['Search results / Robots directives'] );
	}

	public function test_archives_search_and_missing_pages_are_described_for_what_they_are(): void {
		global $lightweight_seo_test_query_state;

		// A tag archive with its own title format.
		$lightweight_seo_test_query_state['is_tag']         = true;
		$lightweight_seo_test_query_state['is_archive']     = true;
		$lightweight_seo_test_query_state['document_title'] = 'Tagged Monochrome';
		$lightweight_seo_test_query_state['queried_object'] = (object) array(
			'term_id'     => 9,
			'name'        => 'Monochrome',
			'taxonomy'    => 'post_tag',
			'description' => 'Black and white work.',
		);

		$tag = $this->lines(
			$this->get_inspector(
				array(
					'content_types' => array(
						'taxonomies' => array(
							'post_tag' => array(
								'title_format' => 'Tagged %title%',
								'sitemap'      => '0',
							),
						),
					),
				)
			)->get_report()
		);

		$this->assertSame( 'info: An archive of the taxonomy “Tags”.', $tag['Page / What WordPress found'] );
		$this->assertStringContainsString( 'From the title format of “Tags” on the “Content and indexation” tab.', $tag['Title / Title tag'] );
		$this->assertStringContainsString( 'A short summary of the term’s description.', $tag['Description / Meta description'] );
		$this->assertSame( 'info: Left out. | “In sitemap” is off for “Tags” on the “Content and indexation” tab.', $tag['Sitemap / XML sitemap'] );

		// An author archive, hidden as a kind.
		$lightweight_seo_test_query_state['is_tag']            = false;
		$lightweight_seo_test_query_state['is_author']         = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 17;
		$lightweight_seo_test_query_state['queried_object']    = null;

		$author = $this->lines( $this->get_inspector( array( 'noindex_author_archives' => '1' ) )->get_report() );

		$this->assertSame( 'info: An author archive.', $author['Page / What WordPress found'] );
		$this->assertStringContainsString( 'Author archives are hidden on the “Content and indexation” tab, and this author follows that.', $author['Search results / Search results'] );

		// A date archive on its second page.
		$lightweight_seo_test_query_state['is_author']  = false;
		$lightweight_seo_test_query_state['is_date']    = true;
		$lightweight_seo_test_query_state['query_vars'] = array( 'paged' => 2 );

		$date = $this->lines( $this->get_inspector( array( 'noindex_date_archives' => '1' ) )->get_report() );

		$this->assertSame( 'info: A date archive. | This is page 2 of it.', $date['Page / What WordPress found'] );
		$this->assertStringContainsString( 'Date archives are hidden on the “Content and indexation” tab.', $date['Search results / Search results'] );
		$this->assertArrayNotHasKey( 'Sitemap / XML sitemap', $date, 'A date archive is not a sitemap entry of its own.' );

		// Internal search.
		$lightweight_seo_test_query_state['is_date']    = false;
		$lightweight_seo_test_query_state['is_archive'] = false;
		$lightweight_seo_test_query_state['is_search']  = true;
		$lightweight_seo_test_query_state['query_vars'] = array();

		$search = $this->lines( $this->get_inspector()->get_report() );

		$this->assertSame( 'info: A page of internal search results.', $search['Page / What WordPress found'] );
		$this->assertStringContainsString( 'Internal search pages are hidden on the “Content and indexation” tab.', $search['Search results / Search results'] );
		$this->assertStringContainsString( 'From the search title format.', $search['Title / Title tag'] );

		// A missing page.
		$lightweight_seo_test_query_state['is_search']      = false;
		$lightweight_seo_test_query_state['is_404']         = true;
		$lightweight_seo_test_query_state['document_title'] = 'Page not found';

		$missing = $this->lines( $this->get_inspector()->get_report() );

		$this->assertSame( 'warning: No page exists at this address (404).', $missing['Page / What WordPress found'] );
		$this->assertStringContainsString( 'A missing page is never shown.', $missing['Search results / Search results'] );
		$this->assertSame( 'info: None is sent for a missing page.', $missing['Canonical / Canonical address'] );
		$this->assertSame( 'info: None are sent for this page.', $missing['Sharing and other tags / Open Graph and X tags'] );
		$this->assertSame( 'info: None is sent for this page.', $missing['Structured data / Schema'] );
	}

	public function test_the_homepage_names_its_own_format_and_description(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_home']        = true;
		$lightweight_seo_test_query_state['is_front_page']  = true;
		$lightweight_seo_test_query_state['document_title'] = 'Test Site – Test Tagline';

		$lines = $this->lines( $this->get_inspector( array( 'meta_description' => 'Welcome.' ) )->get_report() );

		$this->assertSame( 'info: The homepage, which lists the latest posts.', $lines['Page / What WordPress found'] );
		$this->assertStringContainsString( 'From the homepage title format.', $lines['Title / Title tag'] );
		$this->assertSame( 'success: Welcome. | From the homepage meta description on the “Search appearance” tab. | 8 characters.', $lines['Description / Meta description'] );
	}

	public function test_safe_mode_is_reported_instead_of_what_would_have_been_printed(): void {
		$this->view_post();

		$report = $this->get_inspector( array(), array( 'seo_noindex' => '1' ), array( 'title', 'meta', 'robots', 'canonical', 'schema' ) )->get_report();
		$lines  = $this->lines( $report );

		$this->assertSame( array( 'Other SEO' ), $report['plugins'] );
		$this->assertStringContainsString( 'Safe mode: the title is written by WordPress or another plugin', $lines['Title / Title tag'] );
		$this->assertSame( 'info: Safe mode: Lightweight SEO prints no description.', $lines['Description / Meta description'] );
		$this->assertSame( 'info: Safe mode: Lightweight SEO prints none.', $lines['Sharing and other tags / Open Graph and X tags'] );
		$this->assertSame( 'info: Safe mode: Lightweight SEO prints none.', $lines['Structured data / Schema'] );
		$this->assertStringContainsString( 'Safe mode: Lightweight SEO adds no directive of its own.', $lines['Search results / Robots directives'] );
		// The page's own "Hide" is not in effect while robots output is held back, so it is not reported as hidden.
		$this->assertSame( 'success: Search engines may show this page.', $lines['Search results / Search results'] );
	}

	public function test_the_report_is_sent_only_to_an_administrator_with_a_valid_nonce(): void {
		global $lightweight_seo_test_nonce_is_valid;
		global $lightweight_seo_test_user_can;

		$this->view_post();

		$lightweight_seo_test_user_can = false;
		$inspector                     = $this->get_inspector();

		ob_start();
		$this->assertFalse( $inspector->respond() );
		$this->assertSame( '', ob_get_clean() );
		$this->assertSame( 0, $inspector->headers_sent + $inspector->finished );

		$lightweight_seo_test_user_can       = true;
		$lightweight_seo_test_nonce_is_valid = false;

		ob_start();
		$this->assertFalse( $inspector->respond() );
		$this->assertSame( '', ob_get_clean() );
		$this->assertSame( 0, $inspector->headers_sent + $inspector->finished );

		$lightweight_seo_test_nonce_is_valid = true;
		unset( $_GET['_wpnonce'] );

		ob_start();
		$this->assertTrue( $inspector->respond() );
		$fragment = (string) ob_get_clean();

		$this->assertSame( 1, $inspector->headers_sent );
		$this->assertSame( 1, $inspector->finished );
		$this->assertStringStartsWith( '<div class="lwseo-inspect__result">', $fragment );
		$this->assertStringNotContainsString( '<html', $fragment );

		// Asked for as a page of its own, the same report comes wrapped in a document.
		$_GET['lightweight_seo_inspect'] = '1';

		ob_start();
		$inspector->respond();
		$document = (string) ob_get_clean();

		$this->assertStringStartsWith( '<!DOCTYPE html><html lang="en-US">', $document );
		$this->assertStringContainsString( '<meta name="robots" content="noindex, nofollow">', $document );
		$this->assertStringContainsString( 'admin/css/lightweight-seo-admin.css', $document );
		$this->assertStringContainsString( '<div class="lwseo-inspect__result">', $document );
	}

	public function test_the_rendered_report_escapes_everything_it_shows(): void {
		global $lightweight_seo_test_query_state;

		$this->view_post();

		$lightweight_seo_test_query_state['document_title'] = 'A <script>alert(1)</script> title';
		$_SERVER['REQUEST_URI']                             = '/a-post/?x=%22%3E%3Cscript%3E&lightweight_seo_inspect=fragment';

		$inspector = $this->get_inspector( array(), array( 'seo_description' => 'Tom & "Jerry" <b>bold</b>' ) );

		ob_start();
		$inspector->render( $inspector->get_report() );
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsString( '<script', $output );
		$this->assertStringNotContainsString( '<b>', $output );
		$this->assertStringContainsString( 'Tom &amp; &quot;Jerry&quot; bold', $output );
		$this->assertStringContainsString( '<h3 class="lwseo-inspect__heading">Search results</h3>', $output );
		$this->assertStringContainsString( '<th scope="row"><code>og:title</code></th>', $output );
		$this->assertStringContainsString( '<summary>Show the JSON</summary>', $output );
		$this->assertStringContainsString( '<pre class="lwseo-log">', $output );
		$this->assertStringContainsString( 'lightweight_seo_inspect', $_SERVER['REQUEST_URI'] );
		$this->assertStringNotContainsString( 'lightweight_seo_inspect', $output );
	}

	public function test_what_the_services_expose_is_what_they_print(): void {
		$this->view_post();

		$inspector = $this->get_inspector( array( 'x_handle' => 'example' ) );
		$report    = $inspector->get_report();

		$settings     = new Lightweight_SEO_Settings();
		$post_meta    = new class() {
			public function get_all( $post_id ) {
				return array();
			}

			public function get_social_image_url( $post_id ) {
				return '';
			}
		};
		$archive_meta = new class() {
		};
		$page_context = new Lightweight_SEO_Page_Context_Service( $settings, $post_meta, $archive_meta );
		$meta_tags    = new Lightweight_SEO_Meta_Tags_Service( $page_context );
		$schema       = new Lightweight_SEO_Schema_Service( $page_context, $settings );

		ob_start();
		$meta_tags->add_meta_tags();
		$printed_tags = (string) ob_get_clean();

		foreach ( $meta_tags->get_meta_tags() as $tag ) {
			$this->assertStringContainsString( '<meta ' . $tag['attribute'] . '="' . $tag['key'] . '" content="' . esc_attr( $tag['value'] ) . '" />', $printed_tags );
		}

		$this->assertSame( substr_count( $printed_tags, '<meta ' ), count( $meta_tags->get_meta_tags() ) );

		ob_start();
		$schema->add_schema();
		$printed_schema = (string) ob_get_clean();

		$this->assertSame( wp_json_encode( $schema->get_graph(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ), wp_json_encode( json_decode( strip_tags( $printed_schema ), true )['@graph'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) );
		$this->assertCount( count( $schema->get_graph() ), json_decode( $report['sections'][7]['code'], true )['@graph'] );
	}
}
