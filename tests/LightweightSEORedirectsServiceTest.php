<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-redirects-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEORedirectsServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;
		global $lightweight_seo_test_wp_redirect_calls;
		global $lightweight_seo_test_wp_safe_redirect_calls;

		$lightweight_seo_test_options                = array();
		$lightweight_seo_test_posts                  = array();
		$lightweight_seo_test_query_state['is_404']  = false;
		$lightweight_seo_test_wp_redirect_calls      = array();
		$lightweight_seo_test_wp_safe_redirect_calls = array();
		$_SERVER['REQUEST_URI']                      = '/';
		unset( $_SERVER['HTTP_REFERER'] );
	}

	public function test_find_matching_redirect_returns_manual_rule(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ Lightweight_SEO_Redirects_Service::GENERATED_RULES_OPTION_NAME ] = array(
			array(
				'source' => '/old-page',
				'target' => '/generated-target',
				'status' => 302,
			),
		);

		$settings = new class() {
			public function get_manual_redirect_rules() {
				return array(
					array(
						'source' => '/old-page',
						'target' => '/new-page',
						'status' => 301,
					),
				);
			}

			public function not_found_monitor_enabled() {
				return true;
			}

			public function auto_redirects_enabled() {
				return true;
			}
		};

		$service = new Lightweight_SEO_Redirects_Service( $settings );
		$rule    = $service->find_matching_redirect( '/old-page/' );

		$this->assertSame( '/new-page', $rule['target'] );
		$this->assertSame( 301, $rule['status'] );
	}

	public function test_slug_change_generates_redirect_rule(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_posts[42] = (object) array(
			'ID'          => 42,
			'post_name'   => 'old-page',
			'post_status' => 'publish',
			'permalink'   => 'https://example.com/old-page/',
		);

		$post_before = $lightweight_seo_test_posts[42];
		$post_after  = (object) array(
			'ID'          => 42,
			'post_name'   => 'new-page',
			'post_status' => 'publish',
			'permalink'   => 'https://example.com/new-page/',
		);

		$settings = new class() {
			public function get_manual_redirect_rules() {
				return array();
			}

			public function not_found_monitor_enabled() {
				return true;
			}

			public function auto_redirects_enabled() {
				return true;
			}
		};

		$service = new Lightweight_SEO_Redirects_Service( $settings );
		$service->capture_previous_post_path(
			42,
			array(
				'post_name' => 'new-page',
			)
		);
		$service->maybe_store_slug_redirect( 42, $post_after, $post_before );

		$generated_rules = $lightweight_seo_test_options[ Lightweight_SEO_Redirects_Service::GENERATED_RULES_OPTION_NAME ] ?? array();
		$rule            = $service->find_matching_redirect( '/old-page/' );

		$this->assertCount( 1, $generated_rules );
		$this->assertSame( '/old-page', $generated_rules[0]['source'] );
		$this->assertSame( '/new-page', $generated_rules[0]['target'] );
		$this->assertSame( '/new-page', $rule['target'] );
		$this->assertSame( 301, $rule['status'] );
	}

	public function test_redirect_health_report_detects_chains_and_loops(): void {
		$settings = new class() {
			public function get_manual_redirect_rules() {
				return array(
					array(
						'source' => '/old-page',
						'target' => '/mid-page',
						'status' => 301,
					),
					array(
						'source' => '/mid-page',
						'target' => '/final-page',
						'status' => 301,
					),
					array(
						'source' => '/loop-a',
						'target' => '/loop-b',
						'status' => 301,
					),
					array(
						'source' => '/loop-b',
						'target' => '/loop-a',
						'status' => 301,
					),
				);
			}

			public function not_found_monitor_enabled() {
				return true;
			}

			public function auto_redirects_enabled() {
				return true;
			}
		};

		$service = new Lightweight_SEO_Redirects_Service( $settings, false );
		$report  = $service->get_redirect_health_report();
		$sources = array_column( $report['loops'], 'source' );

		$this->assertCount( 1, $report['chains'] );
		$this->assertCount( 2, $report['loops'] );
		$this->assertSame( array( '/old-page', '/mid-page', '/final-page' ), $report['chains'][0]['sequence'] );
		$this->assertContains( '/loop-a', $sources );
		$this->assertContains( '/loop-b', $sources );
		$this->assertSame( '/final-page', $service->find_matching_redirect( '/old-page' )['target'] );
		$this->assertSame( array(), $service->find_matching_redirect( '/loop-a' ) );
	}

	public function test_a_gone_rule_answers_410_with_the_not_found_template_and_no_redirect(): void {
		global $lightweight_seo_test_nocache_headers_calls;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_removed_actions;
		global $lightweight_seo_test_status_headers;
		global $lightweight_seo_test_wp_safe_redirect_calls;
		global $wp_query;

		$lightweight_seo_test_status_headers                         = array();
		$lightweight_seo_test_removed_actions                        = array();
		$lightweight_seo_test_nocache_headers_calls                  = 0;
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'redirect_rules' => "/retired 410\n/moved /retired 301\n/old-shop/* 410\n/old-shop/keep /shop 301",
		);

		$wp_query = new class() {
			public $is_404 = false;

			public function set_404() {
				$this->is_404 = true;
			}
		};

		$service = new Lightweight_SEO_Redirects_Service( new Lightweight_SEO_Settings() );

		$_SERVER['REQUEST_URI'] = '/retired/?utm_source=newsletter';
		$service->maybe_redirect_request();

		$this->assertSame( array( 410 ), $lightweight_seo_test_status_headers );
		$this->assertTrue( $wp_query->is_404 );
		$this->assertSame( 1, $lightweight_seo_test_nocache_headers_calls );
		$this->assertSame( array( array( 'template_redirect', 'redirect_canonical' ) ), $lightweight_seo_test_removed_actions );
		$this->assertSame( array(), $lightweight_seo_test_wp_safe_redirect_calls );

		// The health report names the end of such a trail.
		$this->assertSame( array( '/moved', '/retired', '410' ), $service->get_redirect_health_report()['chains'][0]['sequence'] );

		// A redirect that ends at a gone address answers gone straight away.
		$this->assertSame( 410, $service->find_matching_redirect( '/moved' )['status'] );
		// A whole section can be gone, and one address in it can still be redirected.
		$this->assertSame( 410, $service->find_matching_redirect( '/old-shop/product/blue-hat' )['status'] );
		$this->assertSame( '/shop', $service->find_matching_redirect( '/old-shop/keep' )['target'] );
		$this->assertSame( array(), $service->find_matching_redirect( '/shop' ) );

		$wp_query = null;
	}

	public function test_a_section_rule_redirects_everything_under_it_and_carries_the_rest_over(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_wp_safe_redirect_calls;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'redirect_rules' => "/old-blog/* /blog/* 301\n/blog/2019/* /archive 302\n/hub/* /hub/start 301",
		);

		$service = new class( new Lightweight_SEO_Settings() ) extends Lightweight_SEO_Redirects_Service {
			protected function perform_redirect( $target_url, $status ) {
				parent::perform_redirect( $target_url, $status );

				// Report "not sent" so the request is not ended.
				return false;
			}
		};

		$_SERVER['REQUEST_URI'] = '/old-blog/2024/hello-world/';
		$service->maybe_redirect_request();

		$this->assertSame( 'https://example.com/blog/2024/hello-world', $lightweight_seo_test_wp_safe_redirect_calls[0]['location'] );
		$this->assertSame( 301, $lightweight_seo_test_wp_safe_redirect_calls[0]['status'] );

		// Two rules in a row are followed, and the visitor is sent straight to the end.
		$trace = $service->trace_request( '/old-blog/2019/first' );

		$this->assertSame( array( '/old-blog/*', '/blog/2019/*' ), array_column( $trace['hops'], 'rule' ) );
		$this->assertSame( '/archive', $service->find_matching_redirect( '/old-blog/2019/first' )['target'] );
		$this->assertSame( 301, $service->find_matching_redirect( '/old-blog/2019/first' )['status'] );

		// The address a section points at is inside that section, and is served as it is.
		$lightweight_seo_test_wp_safe_redirect_calls = array();
		$_SERVER['REQUEST_URI']                      = '/hub/start/';
		$service->maybe_redirect_request();

		$this->assertSame( array(), $lightweight_seo_test_wp_safe_redirect_calls );

		$_SERVER['REQUEST_URI'] = '/hub/other/';
		$service->maybe_redirect_request();

		$this->assertSame( 'https://example.com/hub/start', $lightweight_seo_test_wp_safe_redirect_calls[0]['location'] );

		$report = $service->get_redirect_health_report();

		$this->assertSame( array(), $report['loops'] );
		$this->assertSame( array(), $report['chains'] );

		// The health report follows a section from an address under it.
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'redirect_rules' => "/first/* /second/* 301\n/second/* /third/* 301",
		);

		$report = ( new Lightweight_SEO_Redirects_Service( new Lightweight_SEO_Settings(), false ) )->get_redirect_health_report();

		$this->assertSame( array( '/first/*' ), array_column( $report['chains'], 'source' ) );
		$this->assertSame( array( '/first/*', '/second/…', '/third/…' ), $report['chains'][0]['sequence'] );
	}

	public function test_perform_redirect_uses_safe_redirect_for_allowed_external_targets(): void {
		global $lightweight_seo_test_wp_redirect_calls;
		global $lightweight_seo_test_wp_safe_redirect_calls;

		$settings = new class() {
			public function get_manual_redirect_rules() {
				return array();
			}

			public function not_found_monitor_enabled() {
				return true;
			}

			public function auto_redirects_enabled() {
				return true;
			}
		};

		$service = new class( $settings ) extends Lightweight_SEO_Redirects_Service {
			public function dispatch_redirect( $target_url, $status ) {
				return $this->perform_redirect( $target_url, $status );
			}

			protected function is_redirect_target_allowed( $target_url ) {
				return true;
			}
		};

		$this->assertTrue( $service->dispatch_redirect( 'https://new.example/landing', 301 ) );
		$this->assertCount( 1, $lightweight_seo_test_wp_safe_redirect_calls );
		$this->assertSame( 'https://new.example/landing', $lightweight_seo_test_wp_safe_redirect_calls[0]['location'] );
		$this->assertSame( 301, $lightweight_seo_test_wp_safe_redirect_calls[0]['status'] );
		$this->assertSame( array(), $lightweight_seo_test_wp_redirect_calls );
	}

	public function test_perform_redirect_rejects_unapproved_external_targets(): void {
		global $lightweight_seo_test_wp_redirect_calls;
		global $lightweight_seo_test_wp_safe_redirect_calls;

		$settings = new class() {
			public function get_manual_redirect_rules() {
				return array();
			}
		};
		$service  = new class( $settings ) extends Lightweight_SEO_Redirects_Service {
			public function dispatch_redirect( $target_url, $status ) {
				return $this->perform_redirect( $target_url, $status );
			}
		};

		$this->assertFalse( $service->dispatch_redirect( 'https://unapproved.example/landing', 302 ) );
		$this->assertSame( array(), $lightweight_seo_test_wp_safe_redirect_calls );
		$this->assertSame( array(), $lightweight_seo_test_wp_redirect_calls );
	}

	public function test_perform_redirect_uses_wp_safe_redirect_for_local_targets(): void {
		global $lightweight_seo_test_wp_redirect_calls;
		global $lightweight_seo_test_wp_safe_redirect_calls;

		$settings = new class() {
			public function get_manual_redirect_rules() {
				return array();
			}

			public function not_found_monitor_enabled() {
				return true;
			}

			public function auto_redirects_enabled() {
				return true;
			}
		};

		$service = new class( $settings ) extends Lightweight_SEO_Redirects_Service {
			public function dispatch_redirect( $target_url, $status ) {
				return $this->perform_redirect( $target_url, $status );
			}
		};

		$this->assertTrue( $service->dispatch_redirect( 'https://example.com/local-target/', 302 ) );
		$this->assertCount( 1, $lightweight_seo_test_wp_safe_redirect_calls );
		$this->assertSame( 'https://example.com/local-target/', $lightweight_seo_test_wp_safe_redirect_calls[0]['location'] );
		$this->assertSame( 302, $lightweight_seo_test_wp_safe_redirect_calls[0]['status'] );
		$this->assertSame( array(), $lightweight_seo_test_wp_redirect_calls );
	}

	public function test_slug_tracking_is_registered_for_every_writing_context(): void {
		global $lightweight_seo_test_hooks;

		$settings = new class() {
			public function get_manual_redirect_rules() {
				return array();
			}
		};

		foreach ( array( 'admin', 'editor', 'rest' ) as $context ) {
			$lightweight_seo_test_hooks = array();
			new Lightweight_SEO_Redirects_Service( $settings, $context );

			$this->assertSame( array( 'pre_post_update', 'post_updated' ), $lightweight_seo_test_hooks, $context . ' must track slug changes.' );
		}

		$lightweight_seo_test_hooks = array();
		new Lightweight_SEO_Redirects_Service( $settings, 'frontend' );
		$this->assertSame( array( 'template_redirect' ), $lightweight_seo_test_hooks );

		$lightweight_seo_test_hooks = array();
		new Lightweight_SEO_Redirects_Service( $settings, false );
		$this->assertSame( array(), $lightweight_seo_test_hooks, 'Read-only reporting must not register hooks.' );
	}

	public function test_local_target_on_a_subdirectory_install_does_not_repeat_the_home_path(): void {
		global $lightweight_seo_test_home_url;

		$lightweight_seo_test_home_url = 'https://example.com/blog';
		$_SERVER['REQUEST_URI']        = '/blog/old-page/?utm_source=test';

		$settings = new class() {
			public function get_manual_redirect_rules() {
				return array(
					array(
						'source' => '/blog/old-page',
						'target' => '/blog/new-page',
						'status' => 301,
					),
				);
			}
		};

		$service = new class( $settings, false ) extends Lightweight_SEO_Redirects_Service {
			public $redirects = array();

			protected function perform_redirect( $target_url, $status ) {
				$this->redirects[] = array( $target_url, $status );

				return false;
			}
		};

		$service->maybe_redirect_request();

		$lightweight_seo_test_home_url = 'https://example.com';

		$this->assertSame( array( array( 'https://example.com/blog/new-page', 301 ) ), $service->redirects );
	}
}
