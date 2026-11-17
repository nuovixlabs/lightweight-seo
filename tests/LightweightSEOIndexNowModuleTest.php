<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-indexnow-module.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOIndexNowModuleTest extends TestCase {

	const KEY = '0123456789abcdef0123456789abcdef';

	/** @var array[] Requests the module made. */
	private $requests = array();

	protected function setUp(): void {
		global $lightweight_seo_test_environment_type;
		global $lightweight_seo_test_home_url;
		global $lightweight_seo_test_hooks;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_remote_post_responses;

		$this->requests                        = array();
		$lightweight_seo_test_environment_type = 'production';
		$lightweight_seo_test_home_url         = 'https://www.example.org';
		$lightweight_seo_test_hooks            = array();
		$lightweight_seo_test_post_meta        = array();
		$lightweight_seo_test_posts            = array();
		$lightweight_seo_test_options          = array(
			LIGHTWEIGHT_SEO_OPTION_NAME => array(
				'enable_indexnow' => '1',
				'indexnow_key'    => self::KEY,
			),
		);

		$lightweight_seo_test_remote_post_responses = array(
			Lightweight_SEO_IndexNow_Module::ENDPOINT => function ( $url, $args ) {
				$this->requests[] = $args;

				return array( 'response' => array( 'code' => 200 ) );
			},
		);
	}

	protected function tearDown(): void {
		global $lightweight_seo_test_environment_type;
		global $lightweight_seo_test_home_url;

		$lightweight_seo_test_environment_type = 'production';
		$lightweight_seo_test_home_url         = 'https://example.com';
	}

	public function test_visitors_only_get_the_key_file_and_saves_only_collect_changes(): void {
		global $lightweight_seo_test_hooks;

		$this->get_module( 'frontend' );
		$this->assertSame( array( 'template_redirect' ), $lightweight_seo_test_hooks );

		foreach ( array( 'admin', 'editor', 'rest', 'cron', 'cli' ) as $context ) {
			$lightweight_seo_test_hooks = array();
			$this->get_module( $context );
			$this->assertSame( array( 'pre_post_update', 'transition_post_status', 'shutdown' ), $lightweight_seo_test_hooks, $context );
		}

		$lightweight_seo_test_hooks = array();
		$this->get_module( false );
		$this->assertSame( array(), $lightweight_seo_test_hooks );
	}

	public function test_the_key_file_is_answered_only_at_its_own_address(): void {
		global $lightweight_seo_test_options;

		$module = $this->get_module( 'frontend' );

		$this->assertSame( 'https://www.example.org/' . self::KEY . '.txt', $module->get_key_url() );

		foreach ( array( '/', '/' . self::KEY, '/other/' . self::KEY . '.txt', '/' . strrev( self::KEY ) . '.txt' ) as $path ) {
			$_SERVER['REQUEST_URI'] = $path;
			$module->maybe_render_key_file();
		}

		$this->assertSame( array(), $module->key_files );

		$_SERVER['REQUEST_URI'] = '/' . self::KEY . '.txt?cache=1';
		$module->maybe_render_key_file();

		$this->assertSame( array( self::KEY ), $module->key_files );

		// A stored value that is not a key of this plugin is never served.
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]['indexnow_key'] = '../wp-config';

		$broken                 = $this->get_module( 'frontend' );
		$_SERVER['REQUEST_URI'] = '/../wp-config.txt';
		$broken->maybe_render_key_file();

		$this->assertSame( '', $broken->get_key() );
		$this->assertSame( array(), $broken->key_files );

		$_SERVER['REQUEST_URI'] = '/';
	}

	public function test_a_published_page_is_sent_once_at_the_end_of_the_request_without_waiting(): void {
		global $lightweight_seo_test_options;

		$post   = $this->add_post( 42, 'publish', 'https://www.example.org/hello/' );
		$module = $this->get_module( 'editor' );

		// Published, then saved again by the same request.
		$module->collect_changed_post( 'publish', 'draft', $post );
		$module->collect_changed_post( 'publish', 'publish', $post );

		$this->assertSame( array(), $this->requests, 'Nothing is sent while the request is still saving.' );

		$module->submit_changes();
		$module->submit_changes();

		$this->assertCount( 1, $this->requests );
		$this->assertFalse( $this->requests[0]['blocking'] );
		$this->assertSame( 0, $this->requests[0]['redirection'] );
		$this->assertSame(
			array(
				'host'        => 'www.example.org',
				'key'         => self::KEY,
				'keyLocation' => 'https://www.example.org/' . self::KEY . '.txt',
				'urlList'     => array( 'https://www.example.org/hello/' ),
			),
			json_decode( $this->requests[0]['body'], true )
		);

		$last = $lightweight_seo_test_options[ Lightweight_SEO_IndexNow_Module::LAST_OPTION ];

		$this->assertSame( 1, $last['count'] );
		$this->assertSame( 'https://www.example.org/hello/', $last['url'] );
	}

	public function test_pages_that_search_engines_may_not_show_are_never_sent(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_post_meta;

		$module = $this->get_module( 'admin' );

		$draft     = $this->add_post( 1, 'draft', 'https://www.example.org/?p=1' );
		$protected = $this->add_post( 2, 'publish', 'https://www.example.org/protected/', array( 'post_password' => 'secret' ) );
		$hidden    = $this->add_post( 3, 'publish', 'https://www.example.org/hidden/' );
		$revision  = $this->add_post(
			4,
			'inherit',
			'https://www.example.org/?p=4',
			array(
				'post_type'   => 'revision',
				'post_parent' => 3,
			)
		);
		$internal  = $this->add_post( 5, 'publish', 'https://www.example.org/?nav=5', array( 'post_type' => 'nav_menu_item' ) );
		$by_type   = $this->add_post( 6, 'publish', 'https://www.example.org/a-page/', array( 'post_type' => 'page' ) );

		$lightweight_seo_test_post_meta[3] = array( '_lightweight_seo_noindex' => '1' );
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]['content_types'] = array( 'post_types' => array( 'page' => array( 'noindex' => '1' ) ) );

		$module = $this->get_module( 'admin' );

		$module->collect_changed_post( 'draft', 'auto-draft', $draft );
		$module->collect_changed_post( 'publish', 'publish', $protected );
		$module->collect_changed_post( 'publish', 'publish', $hidden );
		$module->collect_changed_post( 'publish', 'publish', $revision );
		$module->collect_changed_post( 'publish', 'publish', $internal );
		$module->collect_changed_post( 'publish', 'publish', $by_type );
		$module->submit_changes();

		$this->assertSame( array(), $this->requests );
		$this->assertArrayNotHasKey( Lightweight_SEO_IndexNow_Module::LAST_OPTION, $lightweight_seo_test_options );
	}

	public function test_an_address_that_stopped_working_is_sent_unless_the_page_was_hidden_by_choice(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$module  = $this->get_module( 'rest' );
		$renamed = $this->add_post( 10, 'publish', 'https://www.example.org/old-name/' );
		$removed = $this->add_post( 11, 'publish', 'https://www.example.org/removed/' );
		$hidden  = $this->add_post( 12, 'publish', 'https://www.example.org/hidden/' );

		$lightweight_seo_test_post_meta[12] = array( '_lightweight_seo_noindex' => '1' );

		foreach ( array( 10, 11, 12 ) as $post_id ) {
			$module->remember_previous_address( $post_id );
		}

		// The update itself: a new slug, and two pages taken offline.
		$lightweight_seo_test_posts[10]->permalink   = 'https://www.example.org/new-name/';
		$lightweight_seo_test_posts[11]->post_status = 'trash';
		$lightweight_seo_test_posts[11]->permalink   = 'https://www.example.org/?p=11';
		$lightweight_seo_test_posts[12]->post_status = 'draft';
		$lightweight_seo_test_posts[12]->permalink   = 'https://www.example.org/?p=12';

		$module->collect_changed_post( 'publish', 'publish', $renamed );
		$module->collect_changed_post( 'trash', 'publish', $removed );
		$module->collect_changed_post( 'draft', 'publish', $hidden );
		$module->submit_changes();

		$this->assertCount( 1, $this->requests );
		$this->assertSame(
			array( 'https://www.example.org/new-name/', 'https://www.example.org/old-name/', 'https://www.example.org/removed/' ),
			json_decode( $this->requests[0]['body'], true )['urlList']
		);
	}

	public function test_nothing_is_sent_from_a_site_that_is_not_public_and_the_reason_is_named(): void {
		global $lightweight_seo_test_environment_type;
		global $lightweight_seo_test_home_url;
		global $lightweight_seo_test_options;

		$post = $this->add_post( 42, 'publish', 'https://www.example.org/hello/' );
		$send = function () use ( $post ) {
			$module = $this->get_module( 'admin' );

			$module->collect_changed_post( 'publish', 'draft', $post );
			$module->submit_changes();

			// The test button uses the same gate.
			$this->assertNull( $module->submit( array( home_url( '/' ) ), true ) );

			return $module->get_blocking_reason();
		};

		$this->assertSame( '', $this->get_module( false )->get_blocking_reason() );

		foreach ( array( 'local', 'development', 'staging' ) as $environment ) {
			$lightweight_seo_test_environment_type = $environment;
			$this->assertStringContainsString( $environment, $send() );
		}

		$lightweight_seo_test_environment_type = 'production';

		foreach ( array( 'http://localhost', 'http://localhost:8080/site', 'https://192.168.1.20', 'https://[::1]', 'https://mysite.local', 'https://shop.test', 'https://intranet', 'https://dev.example.internal' ) as $home ) {
			$lightweight_seo_test_home_url = $home;
			$this->assertStringContainsString( 'cannot reach', $send(), $home );
		}

		$lightweight_seo_test_home_url               = 'https://www.example.org';
		$lightweight_seo_test_options['blog_public'] = '0';
		$this->assertStringContainsString( 'discourage', $send() );

		unset( $lightweight_seo_test_options['blog_public'] );
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]['indexnow_key'] = '';
		$this->assertStringContainsString( 'key', $send() );

		$this->assertSame( array(), $this->requests );
	}

	public function test_only_addresses_of_this_site_are_ever_submitted(): void {
		$module   = $this->get_module( false );
		$response = $module->submit(
			array( 'https://www.example.org/a/', 'https://www.example.org/a/', 'https://other.example.org/b/', 'not an address', array( 'nested' ) ),
			true
		);

		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
		$this->assertTrue( $this->requests[0]['blocking'] );
		$this->assertSame( array( 'https://www.example.org/a/' ), json_decode( $this->requests[0]['body'], true )['urlList'] );
		$this->assertNull( $module->submit( array( 'https://other.example.org/b/' ), true ) );
	}

	public function test_the_key_has_one_shape_and_is_made_from_random_bytes(): void {
		$key = Lightweight_SEO_Settings::generate_indexnow_key();

		$this->assertSame( $key, Lightweight_SEO_Settings::normalize_indexnow_key( $key ) );
		$this->assertNotSame( $key, Lightweight_SEO_Settings::generate_indexnow_key() );

		foreach ( array( '', 'short', strtoupper( self::KEY ), self::KEY . '0', '../' . self::KEY, array( self::KEY ) ) as $value ) {
			$this->assertSame( '', Lightweight_SEO_Settings::normalize_indexnow_key( $value ) );
		}
	}

	private function add_post( $post_id, $status, $permalink, array $extra = array() ) {
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_posts[ $post_id ] = (object) array_merge(
			array(
				'ID'            => $post_id,
				'post_type'     => 'post',
				'post_status'   => $status,
				'post_password' => '',
				'permalink'     => $permalink,
			),
			$extra
		);

		return $lightweight_seo_test_posts[ $post_id ];
	}

	/** The module with the key file response observed instead of ending the process. */
	private function get_module( $context ) {
		return new class( new Lightweight_SEO_Settings(), null, $context ) extends Lightweight_SEO_IndexNow_Module {
			public $key_files = array();

			protected function send_key_file( $key ) {
				$this->key_files[] = $key;

				return false;
			}
		};
	}
}
