<?php

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

	public function test_log_404_request_stores_recent_log_entry(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_404'] = true;
		$_SERVER['REQUEST_URI']                     = '/missing-page/';
		$_SERVER['HTTP_REFERER']                    = 'https://example.com/referrer';

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
		$service->log_404_request();

		$logs = $lightweight_seo_test_options[ Lightweight_SEO_Redirects_Service::LOG_OPTION_NAME ] ?? array();

		$this->assertArrayHasKey( '/missing-page', $logs );
		$this->assertSame( 1, $logs['/missing-page']['hits'] );
		$this->assertSame( 'https://example.com/referrer', $logs['/missing-page']['referer'] );
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
	}

	public function test_perform_redirect_uses_wp_redirect_for_external_targets(): void {
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

		$this->assertTrue( $service->dispatch_redirect( 'https://new.example/landing', 301 ) );
		$this->assertCount( 1, $lightweight_seo_test_wp_redirect_calls );
		$this->assertSame( 'https://new.example/landing', $lightweight_seo_test_wp_redirect_calls[0]['location'] );
		$this->assertSame( 301, $lightweight_seo_test_wp_redirect_calls[0]['status'] );
		$this->assertSame( array(), $lightweight_seo_test_wp_safe_redirect_calls );
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
}
