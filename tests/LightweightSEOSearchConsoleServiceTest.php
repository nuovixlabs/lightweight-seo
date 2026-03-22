<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-search-console-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOSearchConsoleServiceTest extends TestCase {

	public const PRIVATE_KEY = <<<'KEY'
-----BEGIN PRIVATE KEY-----
MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAJSnnfYDvm0B1Hqj
RDAU337jVuMFYxqHRDZVKGqzTju52BLCe1pTCbXUyKMd7WrfGEC4sPVX0DpDUhMX
YaScZWMFHqfKB4ZM8MYrj4xLPWpX/UNvkN3ZfuSNVczjn5XlnQhaNtHFoVy+qPnI
C2A/n9vwpAOFaml7ld+WySPBHHmfAgMBAAECgYBamBxIRdfTjv3zD3UK6G2cYugc
yreu/yivBA7xl/zhoUzxgdyzG1AbpGXyItcB/pxFNUmC+9VG7KgkQmebbkTKuhTm
l+guObmKKw1IhTWbiSe7Re+Tr64W4rpVtXPUH/rUIZncovyIaN5DxIYluafwHvy9
dmwvFuABv87YXA7gYQJBAMV6Uox5XcqYOzNScTWE/dFcn6bYC8RMGHPgELJSHjOT
AKoUdFhd708O6S1InxzRRYRxQ/LB8uhNpgxWsfCmr6sCQQDAtVOybFpjrww1mFSG
T5VgOc0aQnaO6x8PbWzreq8GRKoa2rd9QRknQjKicKVLOmlbbNeZ+ugiUysbiAFl
jXndAkAHZoYhbYruRLYzPiuv7cP1TJtPDVmjiZaBASyfAiTPmfq0ZP/XL+3/8Hcc
k1QjKFSKmhQJzOrlecN3Quh4NEbxAkEAwFi878xi9DiWkTA4vc7VpDRNWjaYq9JX
MEjifK/53uHOf/trRmQhvSO/8o9JDSuCWbTsBk+AQDKPRm2cJ0btKQJAWgnjh5my
r24S5IduHnc0OCOAZJmlPjweZ3tTeTGGC4JbmLazWdNd7MLeWdr6mTk8Zqqfw2fn
VXrFZpFjBva3+Q==
-----END PRIVATE KEY-----
KEY;

	protected function setUp(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_remote_get_responses;
		global $lightweight_seo_test_remote_post_responses;
		global $lightweight_seo_test_scheduled_events;

		$lightweight_seo_test_options               = array();
		$lightweight_seo_test_remote_post_responses = array();
		$lightweight_seo_test_remote_get_responses  = array();
		$lightweight_seo_test_scheduled_events      = array();
	}

	public function test_get_snapshot_fetches_search_analytics_and_sitemaps(): void {
		global $lightweight_seo_test_remote_get_responses;
		global $lightweight_seo_test_remote_post_responses;

		$property                                   = 'https://example.com/';
		$encoded_property                           = rawurlencode( $property );
		$token_endpoint                             = 'https://oauth2.googleapis.com/token';
		$analytics_endpoint                         = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/searchAnalytics/query';
		$inspection_endpoint                        = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
		$sitemaps_endpoint                          = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/sitemaps';
		$submit_sitemap_endpoint                    = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/sitemaps/' . rawurlencode( 'https://example.com/wp-sitemap.xml' );
		$submit_image_sitemap_endpoint              = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/sitemaps/' . rawurlencode( 'https://example.com/wp-sitemap-lightweightseoimages-1.xml' );
		$submit_video_sitemap_endpoint              = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/sitemaps/' . rawurlencode( 'https://example.com/wp-sitemap-lightweightseovideos-1.xml' );
		$submit_news_sitemap_endpoint               = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/sitemaps/' . rawurlencode( 'https://example.com/wp-sitemap-lightweightseonews-1.xml' );
		$lightweight_seo_test_remote_post_responses = array(
			$token_endpoint                => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'access_token' => 'search-console-token',
						'expires_in'   => 3600,
					)
				),
			),
			$analytics_endpoint            => function ( $url, $args ) {
				$body = json_decode( (string) ( $args['body'] ?? '{}' ), true );

				if ( ( $body['startDate'] ?? '' ) === gmdate( 'Y-m-d', strtotime( '-55 days' ) ) ) {
					return array(
						'response' => array(
							'code' => 200,
						),
						'body'     => wp_json_encode(
							array(
								'rows' => array(
									array(
										'keys'        => array( 'https://example.com/alpha/' ),
										'clicks'      => 25,
										'impressions' => 600,
										'ctr'         => 0.041,
										'position'    => 4.1,
									),
									array(
										'keys'        => array( 'https://example.com/beta/' ),
										'clicks'      => 18,
										'impressions' => 180,
										'ctr'         => 0.10,
										'position'    => 2.7,
									),
								),
							)
						),
					);
				}

				return array(
					'response' => array(
						'code' => 200,
					),
					'body'     => wp_json_encode(
						array(
							'rows' => array(
								array(
									'keys'        => array( 'https://example.com/alpha/' ),
									'clicks'      => 10,
									'impressions' => 500,
									'ctr'         => 0.02,
									'position'    => 5.2,
								),
								array(
									'keys'        => array( 'https://example.com/beta/' ),
									'clicks'      => 20,
									'impressions' => 200,
									'ctr'         => 0.10,
									'position'    => 2.5,
								),
							),
						)
					),
				);
			},
			$inspection_endpoint           => function ( $url, $args ) {
				$body           = json_decode( (string) ( $args['body'] ?? '{}' ), true );
				$inspection_url = $body['inspectionUrl'] ?? '';

				if ( 'https://example.com/alpha/' === $inspection_url ) {
					return array(
						'response' => array(
							'code' => 200,
						),
						'body'     => wp_json_encode(
							array(
								'inspectionResult' => array(
									'indexStatusResult' => array(
										'verdict'         => 'FAIL',
										'coverageState'   => 'Submitted URL not indexed',
										'indexingState'   => 'BLOCKED_BY_NOINDEX',
										'robotsTxtState'  => 'ALLOWED',
										'pageFetchState'  => 'SUCCESSFUL',
										'googleCanonical' => 'https://example.com/alpha-canonical/',
										'userCanonical'   => 'https://example.com/alpha/',
										'sitemap'         => array( 'https://example.com/wp-sitemap.xml' ),
									),
								),
							)
						),
					);
				}

				return array(
					'response' => array(
						'code' => 200,
					),
					'body'     => wp_json_encode(
						array(
							'inspectionResult' => array(
								'indexStatusResult' => array(
									'verdict'         => 'PASS',
									'coverageState'   => 'Indexed, not submitted in sitemap',
									'indexingState'   => 'INDEXING_ALLOWED',
									'robotsTxtState'  => 'ALLOWED',
									'pageFetchState'  => 'SUCCESSFUL',
									'googleCanonical' => 'https://example.com/beta/',
									'userCanonical'   => 'https://example.com/beta/',
								),
							),
						)
					),
				);
			},
			$submit_sitemap_endpoint       => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => '{}',
			),
			$submit_image_sitemap_endpoint => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => '{}',
			),
			$submit_video_sitemap_endpoint => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => '{}',
			),
			$submit_news_sitemap_endpoint  => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => '{}',
			),
		);
		$lightweight_seo_test_remote_get_responses  = array(
			$sitemaps_endpoint => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'sitemap' => array(
							array(
								'path'          => 'https://example.com/wp-sitemap.xml',
								'type'          => 'sitemap',
								'lastSubmitted' => '2026-03-20T12:00:00+00:00',
								'warnings'      => 0,
								'errors'        => 1,
							),
						),
					)
				),
			),
		);

		$settings = $this->get_settings_stub( $property );
		$service  = new Lightweight_SEO_Search_Console_Service( $settings, false );
		$snapshot = $service->get_snapshot( true );

		$this->assertTrue( $snapshot['configured'] );
		$this->assertSame( $property, $snapshot['property'] );
		$this->assertSame( 'search-console@example.com', $snapshot['service_account_email'] );
		$this->assertSame( 30.0, $snapshot['totals']['clicks'] );
		$this->assertSame( 700.0, $snapshot['totals']['impressions'] );
		$this->assertCount( 1, $snapshot['low_ctr_pages'] );
		$this->assertSame( 'https://example.com/alpha/', $snapshot['low_ctr_pages'][0]['page'] );
		$this->assertCount( 1, $snapshot['declining_pages'] );
		$this->assertSame( -15.0, $snapshot['declining_pages'][0]['click_delta'] );
		$this->assertCount( 2, $snapshot['inspection_results'] );
		$this->assertCount( 2, $snapshot['indexation_issues'] );
		$this->assertCount( 1, $snapshot['canonical_mismatches'] );
		$this->assertSame( 'https://example.com/alpha-canonical/', $snapshot['canonical_mismatches'][0]['google_canonical'] );
		$this->assertCount( 4, $snapshot['submitted_sitemaps'] );
		$this->assertCount( 1, $snapshot['sitemaps'] );
		$this->assertSame( 1, $snapshot['sitemaps'][0]['errors'] );
	}

	public function test_get_snapshot_uses_cached_snapshot_until_forced(): void {
		global $lightweight_seo_test_remote_get_responses;
		global $lightweight_seo_test_remote_post_responses;

		$property                                   = 'https://example.com/';
		$encoded_property                           = rawurlencode( $property );
		$token_endpoint                             = 'https://oauth2.googleapis.com/token';
		$analytics_endpoint                         = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/searchAnalytics/query';
		$inspection_endpoint                        = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
		$sitemaps_endpoint                          = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/sitemaps';
		$submit_sitemap_endpoint                    = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/sitemaps/' . rawurlencode( 'https://example.com/wp-sitemap.xml' );
		$submit_image_sitemap_endpoint              = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/sitemaps/' . rawurlencode( 'https://example.com/wp-sitemap-lightweightseoimages-1.xml' );
		$submit_video_sitemap_endpoint              = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/sitemaps/' . rawurlencode( 'https://example.com/wp-sitemap-lightweightseovideos-1.xml' );
		$submit_news_sitemap_endpoint               = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/sitemaps/' . rawurlencode( 'https://example.com/wp-sitemap-lightweightseonews-1.xml' );
		$lightweight_seo_test_remote_post_responses = array(
			$token_endpoint                => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'access_token' => 'search-console-token',
						'expires_in'   => 3600,
					)
				),
			),
			$analytics_endpoint            => function ( $url, $args ) {
				return array(
					'response' => array(
						'code' => 200,
					),
					'body'     => wp_json_encode(
						array(
							'rows' => array(
								array(
									'keys'        => array( 'https://example.com/alpha/' ),
									'clicks'      => 3,
									'impressions' => 100,
									'ctr'         => 0.03,
									'position'    => 4.2,
								),
							),
						)
					),
				);
			},
			$inspection_endpoint           => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'inspectionResult' => array(
							'indexStatusResult' => array(
								'verdict'         => 'PASS',
								'coverageState'   => 'Indexed',
								'indexingState'   => 'INDEXING_ALLOWED',
								'robotsTxtState'  => 'ALLOWED',
								'pageFetchState'  => 'SUCCESSFUL',
								'googleCanonical' => 'https://example.com/alpha/',
								'userCanonical'   => 'https://example.com/alpha/',
							),
						),
					)
				),
			),
			$submit_sitemap_endpoint       => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => '{}',
			),
			$submit_image_sitemap_endpoint => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => '{}',
			),
			$submit_video_sitemap_endpoint => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => '{}',
			),
			$submit_news_sitemap_endpoint  => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => '{}',
			),
		);
		$lightweight_seo_test_remote_get_responses  = array(
			$sitemaps_endpoint => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'sitemap' => array(),
					)
				),
			),
		);

		$settings       = $this->get_settings_stub( $property );
		$service        = new Lightweight_SEO_Search_Console_Service( $settings, false );
		$first_snapshot = $service->get_snapshot( true );

		$lightweight_seo_test_remote_post_responses[ $analytics_endpoint ] = function ( $url, $args ) {
			return array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'rows' => array(
							array(
								'keys'        => array( 'https://example.com/alpha/' ),
								'clicks'      => 50,
								'impressions' => 1000,
								'ctr'         => 0.05,
								'position'    => 1.1,
							),
						),
					)
				),
			);
		};

		$cached_snapshot = $service->get_snapshot();

		$this->assertSame( $first_snapshot['totals'], $cached_snapshot['totals'] );

		$refreshed_snapshot = $service->get_snapshot( true );

		$this->assertSame( 50.0, $refreshed_snapshot['totals']['clicks'] );
	}

	public function test_ensure_sync_schedule_registers_daily_event(): void {
		global $lightweight_seo_test_scheduled_events;

		$settings = $this->get_settings_stub( 'https://example.com/' );
		$service  = new Lightweight_SEO_Search_Console_Service( $settings, false );

		$service->ensure_sync_schedule();

		$this->assertArrayHasKey( Lightweight_SEO_Search_Console_Service::SYNC_HOOK, $lightweight_seo_test_scheduled_events );
	}

	public function test_get_snapshot_paginates_search_analytics_rows_before_building_totals(): void {
		global $lightweight_seo_test_remote_get_responses;
		global $lightweight_seo_test_remote_post_responses;

		$property            = 'https://example.com/';
		$encoded_property    = rawurlencode( $property );
		$token_endpoint      = 'https://oauth2.googleapis.com/token';
		$analytics_endpoint  = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/searchAnalytics/query';
		$inspection_endpoint = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
		$sitemaps_endpoint   = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/sitemaps';

		$lightweight_seo_test_remote_post_responses = array(
			$token_endpoint      => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'access_token' => 'search-console-token',
						'expires_in'   => 3600,
					)
				),
			),
			$analytics_endpoint  => function ( $url, $args ) {
				$body       = json_decode( (string) ( $args['body'] ?? '{}' ), true );
				$start_row  = (int) ( $body['startRow'] ?? 0 );
				$start_date = (string) ( $body['startDate'] ?? '' );

				if ( gmdate( 'Y-m-d', strtotime( '-55 days' ) ) === $start_date ) {
					return array(
						'response' => array(
							'code' => 200,
						),
						'body'     => wp_json_encode(
							array(
								'rows' => array(),
							)
						),
					);
				}

				$page_map = array(
					0 => array(
						array(
							'keys'        => array( 'https://example.com/alpha/' ),
							'clicks'      => 10,
							'impressions' => 100,
							'ctr'         => 0.10,
							'position'    => 2.0,
						),
						array(
							'keys'        => array( 'https://example.com/beta/' ),
							'clicks'      => 5,
							'impressions' => 80,
							'ctr'         => 0.0625,
							'position'    => 3.1,
						),
					),
					2 => array(
						array(
							'keys'        => array( 'https://example.com/gamma/' ),
							'clicks'      => 2,
							'impressions' => 40,
							'ctr'         => 0.05,
							'position'    => 4.7,
						),
					),
				);

				return array(
					'response' => array(
						'code' => 200,
					),
					'body'     => wp_json_encode(
						array(
							'rows' => $page_map[ $start_row ] ?? array(),
						)
					),
				);
			},
			$inspection_endpoint => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'inspectionResult' => array(
							'indexStatusResult' => array(
								'verdict'         => 'PASS',
								'coverageState'   => 'Indexed',
								'indexingState'   => 'INDEXING_ALLOWED',
								'robotsTxtState'  => 'ALLOWED',
								'pageFetchState'  => 'SUCCESSFUL',
								'googleCanonical' => 'https://example.com/alpha/',
								'userCanonical'   => 'https://example.com/alpha/',
							),
						),
					)
				),
			),
		);
		$lightweight_seo_test_remote_get_responses  = array(
			$sitemaps_endpoint => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'sitemap' => array(),
					)
				),
			),
		);

		$settings = new class( $property ) {
			private $property;

			public function __construct( $property ) {
				$this->property = $property;
			}

			public function get_search_console_property() {
				return $this->property;
			}

			public function get_search_console_service_account_json() {
				return wp_json_encode(
					array(
						'client_email' => 'search-console@example.com',
						'private_key'  => LightweightSEOSearchConsoleServiceTest::PRIVATE_KEY,
						'token_uri'    => 'https://oauth2.googleapis.com/token',
					)
				);
			}

			public function search_console_sitemap_submission_enabled() {
				return false;
			}

			public function image_sitemaps_enabled() {
				return false;
			}

			public function video_sitemaps_enabled() {
				return false;
			}

			public function news_sitemaps_enabled() {
				return false;
			}
		};

		$service = new class( $settings, false ) extends Lightweight_SEO_Search_Console_Service {
			protected function get_search_analytics_row_limit() {
				return 2;
			}
		};

		$snapshot = $service->get_snapshot( true );

		$this->assertSame( 17.0, $snapshot['totals']['clicks'] );
		$this->assertSame( 220.0, $snapshot['totals']['impressions'] );
		$this->assertCount( 3, $snapshot['top_pages'] );
	}

	public function test_submit_sitemaps_put_requests_do_not_send_json_null_bodies(): void {
		global $lightweight_seo_test_remote_get_responses;
		global $lightweight_seo_test_remote_post_responses;

		$property                                   = 'https://example.com/';
		$encoded_property                           = rawurlencode( $property );
		$token_endpoint                             = 'https://oauth2.googleapis.com/token';
		$analytics_endpoint                         = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/searchAnalytics/query';
		$inspection_endpoint                        = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
		$sitemaps_endpoint                          = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/sitemaps';
		$submit_sitemap_endpoint                    = 'https://www.googleapis.com/webmasters/v3/sites/' . $encoded_property . '/sitemaps/' . rawurlencode( 'https://example.com/wp-sitemap.xml' );
		$submit_sitemap_request_args                = array();
		$lightweight_seo_test_remote_post_responses = array(
			$token_endpoint          => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'access_token' => 'search-console-token',
						'expires_in'   => 3600,
					)
				),
			),
			$analytics_endpoint      => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'rows' => array(),
					)
				),
			),
			$submit_sitemap_endpoint => function ( $url, $args ) use ( &$submit_sitemap_request_args ) {
				$submit_sitemap_request_args = $args;

				return array(
					'response' => array(
						'code' => 200,
					),
					'body'     => '{}',
				);
			},
		);
		$lightweight_seo_test_remote_get_responses  = array(
			$sitemaps_endpoint => array(
				'response' => array(
					'code' => 200,
				),
				'body'     => wp_json_encode(
					array(
						'sitemap' => array(),
					)
				),
			),
		);

		$settings = new class( $property ) {
			private $property;

			public function __construct( $property ) {
				$this->property = $property;
			}

			public function get_search_console_property() {
				return $this->property;
			}

			public function get_search_console_service_account_json() {
				return wp_json_encode(
					array(
						'client_email' => 'search-console@example.com',
						'private_key'  => LightweightSEOSearchConsoleServiceTest::PRIVATE_KEY,
						'token_uri'    => 'https://oauth2.googleapis.com/token',
					)
				);
			}

			public function search_console_sitemap_submission_enabled() {
				return true;
			}

			public function image_sitemaps_enabled() {
				return false;
			}

			public function video_sitemaps_enabled() {
				return false;
			}

			public function news_sitemaps_enabled() {
				return false;
			}
		};

		$service = new Lightweight_SEO_Search_Console_Service( $settings, false );
		$service->get_snapshot( true );

		$this->assertSame( 'PUT', $submit_sitemap_request_args['method'] ?? '' );
		$this->assertArrayNotHasKey( 'body', $submit_sitemap_request_args );
		$this->assertArrayNotHasKey( 'Content-Type', $submit_sitemap_request_args['headers'] ?? array() );
	}

	private function get_settings_stub( $property ) {
		return new class( $property ) {
			private $property;

			public function __construct( $property ) {
				$this->property = $property;
			}

			public function get_search_console_property() {
				return $this->property;
			}

			public function get_search_console_service_account_json() {
				return wp_json_encode(
					array(
						'client_email' => 'search-console@example.com',
						'private_key'  => LightweightSEOSearchConsoleServiceTest::PRIVATE_KEY,
						'token_uri'    => 'https://oauth2.googleapis.com/token',
					)
				);
			}

			public function search_console_sitemap_submission_enabled() {
				return true;
			}

			public function image_sitemaps_enabled() {
				return true;
			}

			public function video_sitemaps_enabled() {
				return true;
			}

			public function news_sitemaps_enabled() {
				return true;
			}
		};
	}
}
