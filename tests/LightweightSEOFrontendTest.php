<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-post-meta.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-archive-meta.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-compatibility-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-page-context-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-title-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-meta-tags-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-header-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-schema-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-frontend.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOFrontendTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_filters;
		global $lightweight_seo_test_home_url;
		global $lightweight_seo_test_is_admin;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_query_state;
		global $lightweight_seo_test_user_can;

		$lightweight_seo_test_filters               = array();
		$lightweight_seo_test_home_url              = 'https://example.com/blog';
		$lightweight_seo_test_is_admin              = false;
		$lightweight_seo_test_options               = array();
		$lightweight_seo_test_query_state['is_404'] = false;
		$lightweight_seo_test_user_can              = true;
		$_SERVER['REQUEST_URI']                     = '/blog/hello-world/?preview=1&b=two words';
	}

	protected function tearDown(): void {
		global $lightweight_seo_test_filters;
		global $lightweight_seo_test_home_url;
		global $lightweight_seo_test_query_state;
		global $lightweight_seo_test_user_can;

		$lightweight_seo_test_filters               = array();
		$lightweight_seo_test_home_url              = 'https://example.com';
		$lightweight_seo_test_query_state['is_404'] = false;
		$lightweight_seo_test_user_can              = true;
		$_SERVER['REQUEST_URI']                     = '/';
	}

	public function test_the_toolbar_tells_an_administrator_whether_the_page_may_be_shown_and_links_to_its_report(): void {
		global $lightweight_seo_test_filters;
		global $lightweight_seo_test_query_state;

		$node = $this->get_toolbar_nodes()[0];

		$this->assertSame( 'lightweight-seo', $node['id'] );
		$this->assertSame( 'SEO: shown in search', $node['title'] );
		// The address of the page is one encoded argument, joined to the origin so the subdirectory is not repeated.
		$this->assertSame(
			'https://example.com/wp-admin/admin.php?page=lightweight-seo&tab=tools&inspect=' . rawurlencode( 'https://example.com/blog/hello-world/?preview=1&b=two words' ) . '#lwseo-inspect',
			$node['href']
		);

		// Whatever puts "noindex" into the robots tag of the page, the toolbar says the same.
		$lightweight_seo_test_filters['wp_robots'] = static function ( $robots ) {
			return array( 'noindex' => true ) + $robots;
		};

		$this->assertSame( 'SEO: hidden from search', $this->get_toolbar_nodes()[0]['title'] );

		$lightweight_seo_test_filters               = array();
		$lightweight_seo_test_query_state['is_404'] = true;

		$this->assertSame( 'SEO: page not found', $this->get_toolbar_nodes()[0]['title'] );
	}

	public function test_the_toolbar_item_is_for_administrators_on_the_front_end_only(): void {
		global $lightweight_seo_test_is_admin;
		global $lightweight_seo_test_user_can;

		$lightweight_seo_test_user_can = false;
		$this->assertSame( array(), $this->get_toolbar_nodes() );

		$lightweight_seo_test_user_can = true;
		$lightweight_seo_test_is_admin = true;
		$this->assertSame( array(), $this->get_toolbar_nodes() );

		$lightweight_seo_test_is_admin = false;
	}

	/** Build the toolbar and return the items the plugin added. */
	private function get_toolbar_nodes() {
		$settings = new Lightweight_SEO_Settings();
		$frontend = new Lightweight_SEO_Frontend( $settings, new Lightweight_SEO_Post_Meta(), new Lightweight_SEO_Archive_Meta( $settings ) );
		$toolbar  = new class() {
			public $nodes = array();

			public function add_node( $node ) {
				$this->nodes[] = $node;
			}
		};

		$frontend->add_admin_bar_status( $toolbar );

		return $toolbar->nodes;
	}
}
