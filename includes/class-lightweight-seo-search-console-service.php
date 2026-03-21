<?php
/**
 * Search Console sync service for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Search Console sync service.
 */
class Lightweight_SEO_Search_Console_Service {

	/**
	 * Scheduled sync hook name.
	 *
	 * @since    1.1.0
	 * @var      string
	 */
	const SYNC_HOOK = 'lightweight_seo_search_console_sync';

	/**
	 * Cached token option name.
	 *
	 * @since    1.1.0
	 * @var      string
	 */
	const TOKEN_OPTION_NAME = 'lightweight_seo_search_console_token';

	/**
	 * Cached snapshot option name.
	 *
	 * @since    1.1.0
	 * @var      string
	 */
	const SNAPSHOT_OPTION_NAME = 'lightweight_seo_search_console_snapshot';

	/**
	 * Snapshot TTL in seconds.
	 *
	 * @since    1.1.0
	 * @var      int
	 */
	const SNAPSHOT_TTL = 21600;

	/**
	 * Search Console API scope.
	 *
	 * @since    1.1.0
	 * @var      string
	 */
	const API_SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings    $settings
	 */
	private $settings;

	/**
	 * Initialize the service.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings    $settings    Shared settings service.
	 * @param    bool                        $register_hooks    Whether to register sync hooks.
	 */
	public function __construct( $settings, $register_hooks = true ) {
		$this->settings = $settings;

		if ( $register_hooks ) {
			add_action( 'init', array( $this, 'ensure_sync_schedule' ) );
			add_action( self::SYNC_HOOK, array( $this, 'refresh_snapshot' ) );
		}
	}

	/**
	 * Determine whether Search Console is configured.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function is_configured() {
		return ! empty( $this->settings->get_search_console_property() ) && ! empty( $this->get_service_account_credentials() );
	}

	/**
	 * Get the configured service account email when available.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	public function get_service_account_email() {
		$credentials = $this->get_service_account_credentials();

		return sanitize_text_field( $credentials['client_email'] ?? '' );
	}

	/**
	 * Get the cached Search Console snapshot, refreshing it when stale.
	 *
	 * @since    1.1.0
	 * @param    bool    $force_refresh    Whether to bypass the cache.
	 * @return   array
	 */
	public function get_snapshot( $force_refresh = false ) {
		if ( ! $this->is_configured() ) {
			return $this->get_empty_snapshot();
		}

		$snapshot = get_option( self::SNAPSHOT_OPTION_NAME, array() );

		if ( ! $force_refresh && $this->is_snapshot_fresh( $snapshot ) ) {
			return $snapshot;
		}

		return $this->refresh_snapshot();
	}

	/**
	 * Refresh the Search Console snapshot from the API.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	public function refresh_snapshot() {
		$snapshot = $this->get_empty_snapshot();

		if ( ! $this->is_configured() ) {
			update_option( self::SNAPSHOT_OPTION_NAME, $snapshot );

			return $snapshot;
		}

		$property              = $this->settings->get_search_console_property();
		$analytics_date_ranges = $this->get_analytics_date_ranges();
		$analytics_rows        = $this->fetch_search_analytics_rows_for_range(
			$property,
			$analytics_date_ranges['current']['start'],
			$analytics_date_ranges['current']['end']
		);
		$previous_rows         = $this->fetch_search_analytics_rows_for_range(
			$property,
			$analytics_date_ranges['previous']['start'],
			$analytics_date_ranges['previous']['end']
		);
		$sitemap_rows          = $this->fetch_sitemaps( $property );

		$snapshot['configured']            = true;
		$snapshot['property']              = $property;
		$snapshot['service_account_email'] = $this->get_service_account_email();
		$snapshot['last_synced']           = gmdate( 'c' );

		if ( is_wp_error( $analytics_rows ) ) {
			$snapshot['last_error'] = $analytics_rows->get_error_message();
			update_option( self::SNAPSHOT_OPTION_NAME, $snapshot );

			return $snapshot;
		}

		if ( is_wp_error( $previous_rows ) ) {
			$previous_rows = array();
		}

		$snapshot['totals']               = $this->build_analytics_totals( $analytics_rows );
		$snapshot['top_pages']            = array_slice( $analytics_rows, 0, 10 );
		$snapshot['low_ctr_pages']        = $this->extract_low_ctr_pages( $analytics_rows );
		$snapshot['declining_pages']      = $this->extract_declining_pages( $analytics_rows, $previous_rows );
		$snapshot['inspection_results']   = $this->fetch_url_inspection_results(
			$property,
			$this->get_inspection_candidates( $snapshot )
		);
		$snapshot['indexation_issues']    = $this->extract_indexation_issues( $snapshot['inspection_results'] );
		$snapshot['canonical_mismatches'] = $this->extract_canonical_mismatches( $snapshot['inspection_results'] );

		if ( is_wp_error( $sitemap_rows ) ) {
			$snapshot['last_error'] = $sitemap_rows->get_error_message();
		} else {
			$snapshot['sitemaps'] = $sitemap_rows;
		}

		update_option( self::SNAPSHOT_OPTION_NAME, $snapshot );

		return $snapshot;
	}

	/**
	 * Ensure the Search Console sync event is scheduled.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function ensure_sync_schedule() {
		if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}

		if ( ! wp_next_scheduled( self::SYNC_HOOK ) ) {
			wp_schedule_event( time() + 300, 'daily', self::SYNC_HOOK );
		}
	}

	/**
	 * Get an empty Search Console snapshot shape.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function get_empty_snapshot() {
		return array(
			'configured'            => false,
			'property'              => '',
			'service_account_email' => $this->get_service_account_email(),
			'last_synced'           => '',
			'last_error'            => '',
			'totals'                => array(
				'clicks'      => 0,
				'impressions' => 0,
				'ctr'         => 0,
				'position'    => 0,
			),
			'top_pages'             => array(),
			'low_ctr_pages'         => array(),
			'declining_pages'       => array(),
			'inspection_results'    => array(),
			'indexation_issues'     => array(),
			'canonical_mismatches'  => array(),
			'sitemaps'              => array(),
		);
	}

	/**
	 * Determine whether a stored snapshot can still be reused.
	 *
	 * @since    1.1.0
	 * @param    array    $snapshot    Snapshot payload.
	 * @return   bool
	 */
	private function is_snapshot_fresh( $snapshot ) {
		if ( empty( $snapshot['last_synced'] ) || empty( $snapshot['property'] ) ) {
			return false;
		}

		$last_synced = strtotime( (string) $snapshot['last_synced'] );

		if ( ! $last_synced ) {
			return false;
		}

		if ( $snapshot['property'] !== $this->settings->get_search_console_property() ) {
			return false;
		}

		return ( time() - $last_synced ) < self::SNAPSHOT_TTL;
	}

	/**
	 * Fetch Search Analytics rows for the configured property.
	 *
	 * @since    1.1.0
	 * @param    string    $property    Search Console property identifier.
	 * @return   array|WP_Error
	 */
	private function fetch_search_analytics_rows_for_range( $property, $start_date, $end_date ) {
		$endpoint = sprintf(
			'https://www.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query',
			rawurlencode( $property )
		);
		$response = $this->request_json(
			'POST',
			$endpoint,
			array(
				'startDate'  => $start_date,
				'endDate'    => $end_date,
				'dimensions' => array( 'page' ),
				'rowLimit'   => 100,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$rows            = $response['rows'] ?? array();
		$normalized_rows = array();

		foreach ( $rows as $row ) {
			$page = $row['keys'][0] ?? '';

			if ( empty( $page ) ) {
				continue;
			}

			$normalized_rows[] = array(
				'page'        => esc_url_raw( $page ),
				'clicks'      => (float) ( $row['clicks'] ?? 0 ),
				'impressions' => (float) ( $row['impressions'] ?? 0 ),
				'ctr'         => (float) ( $row['ctr'] ?? 0 ),
				'position'    => (float) ( $row['position'] ?? 0 ),
			);
		}

		return $normalized_rows;
	}

	/**
	 * Build the current and previous Search Analytics date ranges.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function get_analytics_date_ranges() {
		return array(
			'current'  => array(
				'start' => gmdate( 'Y-m-d', strtotime( '-27 days' ) ),
				'end'   => gmdate( 'Y-m-d', strtotime( '-1 day' ) ),
			),
			'previous' => array(
				'start' => gmdate( 'Y-m-d', strtotime( '-55 days' ) ),
				'end'   => gmdate( 'Y-m-d', strtotime( '-28 days' ) ),
			),
		);
	}

	/**
	 * Build a deduplicated list of URLs to inspect.
	 *
	 * @since    1.1.0
	 * @param    array    $snapshot    Partial snapshot payload.
	 * @return   array
	 */
	private function get_inspection_candidates( $snapshot ) {
		$candidates = array();
		$groups     = array(
			$snapshot['top_pages'] ?? array(),
			$snapshot['low_ctr_pages'] ?? array(),
			$snapshot['declining_pages'] ?? array(),
		);

		foreach ( $groups as $group ) {
			foreach ( $group as $row ) {
				$page = $row['page'] ?? '';

				if ( empty( $page ) || isset( $candidates[ $page ] ) ) {
					continue;
				}

				$candidates[ $page ] = $page;
			}
		}

		return array_slice( array_values( $candidates ), 0, 10 );
	}

	/**
	 * Fetch sitemap status rows for the configured property.
	 *
	 * @since    1.1.0
	 * @param    string    $property    Search Console property identifier.
	 * @return   array|WP_Error
	 */
	private function fetch_sitemaps( $property ) {
		$endpoint = sprintf(
			'https://www.googleapis.com/webmasters/v3/sites/%s/sitemaps',
			rawurlencode( $property )
		);
		$response = $this->request_json( 'GET', $endpoint );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$sitemaps            = $response['sitemap'] ?? array();
		$normalized_sitemaps = array();

		foreach ( $sitemaps as $sitemap ) {
			$normalized_sitemaps[] = array(
				'path'            => esc_url_raw( $sitemap['path'] ?? '' ),
				'type'            => sanitize_text_field( $sitemap['type'] ?? '' ),
				'last_submitted'  => sanitize_text_field( $sitemap['lastSubmitted'] ?? '' ),
				'last_downloaded' => sanitize_text_field( $sitemap['lastDownloaded'] ?? '' ),
				'is_pending'      => ! empty( $sitemap['isPending'] ),
				'warnings'        => absint( $sitemap['warnings'] ?? 0 ),
				'errors'          => absint( $sitemap['errors'] ?? 0 ),
			);
		}

		return $normalized_sitemaps;
	}

	/**
	 * Build snapshot totals from Search Analytics rows.
	 *
	 * @since    1.1.0
	 * @param    array    $rows    Search Analytics rows.
	 * @return   array
	 */
	private function build_analytics_totals( $rows ) {
		$clicks               = 0;
		$impressions          = 0;
		$weighted_position    = 0;
		$total_position_basis = 0;

		foreach ( $rows as $row ) {
			$clicks      += (float) ( $row['clicks'] ?? 0 );
			$impressions += (float) ( $row['impressions'] ?? 0 );

			if ( ! empty( $row['impressions'] ) ) {
				$weighted_position    += (float) $row['position'] * (float) $row['impressions'];
				$total_position_basis += (float) $row['impressions'];
			}
		}

		return array(
			'clicks'      => $clicks,
			'impressions' => $impressions,
			'ctr'         => $impressions > 0 ? $clicks / $impressions : 0,
			'position'    => $total_position_basis > 0 ? $weighted_position / $total_position_basis : 0,
		);
	}

	/**
	 * Extract pages that have meaningful impressions but weak CTR.
	 *
	 * @since    1.1.0
	 * @param    array    $rows    Search Analytics rows.
	 * @return   array
	 */
	private function extract_low_ctr_pages( $rows ) {
		$low_ctr_pages = array_values(
			array_filter(
				$rows,
				function ( $row ) {
					return (float) ( $row['impressions'] ?? 0 ) >= 100 && (float) ( $row['ctr'] ?? 0 ) < 0.03;
				}
			)
		);

		usort(
			$low_ctr_pages,
			function ( $left, $right ) {
				return ( (float) ( $right['impressions'] ?? 0 ) <=> (float) ( $left['impressions'] ?? 0 ) );
			}
		);

		return array_slice( $low_ctr_pages, 0, 10 );
	}

	/**
	 * Extract pages whose clicks are declining versus the previous period.
	 *
	 * @since    1.1.0
	 * @param    array    $current_rows     Current period rows.
	 * @param    array    $previous_rows    Previous period rows.
	 * @return   array
	 */
	private function extract_declining_pages( $current_rows, $previous_rows ) {
		$previous_map    = array();
		$declining_pages = array();

		foreach ( $previous_rows as $row ) {
			if ( empty( $row['page'] ) ) {
				continue;
			}

			$previous_map[ $row['page'] ] = $row;
		}

		foreach ( $current_rows as $row ) {
			$page = $row['page'] ?? '';

			if ( empty( $page ) || empty( $previous_map[ $page ] ) ) {
				continue;
			}

			$current_clicks  = (float) ( $row['clicks'] ?? 0 );
			$previous_clicks = (float) ( $previous_map[ $page ]['clicks'] ?? 0 );
			$click_delta     = $current_clicks - $previous_clicks;

			if ( $previous_clicks < 5 || $click_delta >= 0 ) {
				continue;
			}

			$declining_pages[] = array(
				'page'            => $page,
				'current_clicks'  => $current_clicks,
				'previous_clicks' => $previous_clicks,
				'click_delta'     => $click_delta,
				'impressions'     => (float) ( $row['impressions'] ?? 0 ),
				'ctr'             => (float) ( $row['ctr'] ?? 0 ),
				'position'        => (float) ( $row['position'] ?? 0 ),
			);
		}

		usort(
			$declining_pages,
			function ( $left, $right ) {
				return ( (float) ( $left['click_delta'] ?? 0 ) <=> (float) ( $right['click_delta'] ?? 0 ) );
			}
		);

		return array_slice( $declining_pages, 0, 10 );
	}

	/**
	 * Fetch URL Inspection results for important pages.
	 *
	 * @since    1.1.0
	 * @param    string    $property         Search Console property identifier.
	 * @param    array     $inspection_urls  Fully qualified URLs to inspect.
	 * @return   array
	 */
	private function fetch_url_inspection_results( $property, $inspection_urls ) {
		$results  = array();
		$endpoint = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

		foreach ( $inspection_urls as $inspection_url ) {
			$response = $this->request_json(
				'POST',
				$endpoint,
				array(
					'inspectionUrl' => $inspection_url,
					'siteUrl'       => $property,
					'languageCode'  => 'en-US',
				)
			);

			if ( is_wp_error( $response ) ) {
				$results[] = array(
					'page'  => esc_url_raw( $inspection_url ),
					'error' => $response->get_error_message(),
				);

				continue;
			}

			$index_status = $response['inspectionResult']['indexStatusResult'] ?? array();

			$results[] = array(
				'page'             => esc_url_raw( $inspection_url ),
				'verdict'          => sanitize_text_field( $index_status['verdict'] ?? '' ),
				'coverage_state'   => sanitize_text_field( $index_status['coverageState'] ?? '' ),
				'indexing_state'   => sanitize_text_field( $index_status['indexingState'] ?? '' ),
				'robots_txt_state' => sanitize_text_field( $index_status['robotsTxtState'] ?? '' ),
				'page_fetch_state' => sanitize_text_field( $index_status['pageFetchState'] ?? '' ),
				'google_canonical' => esc_url_raw( $index_status['googleCanonical'] ?? '' ),
				'user_canonical'   => esc_url_raw( $index_status['userCanonical'] ?? '' ),
				'last_crawl_time'  => sanitize_text_field( $index_status['lastCrawlTime'] ?? '' ),
				'sitemaps'         => array_values(
					array_filter(
						array_map( 'esc_url_raw', $index_status['sitemap'] ?? array() )
					)
				),
			);
		}

		return $results;
	}

	/**
	 * Extract indexation issues from URL Inspection results.
	 *
	 * @since    1.1.0
	 * @param    array    $inspection_results    URL Inspection results.
	 * @return   array
	 */
	private function extract_indexation_issues( $inspection_results ) {
		$issues = array();

		foreach ( $inspection_results as $result ) {
			if ( ! empty( $result['error'] ) ) {
				$issues[] = array(
					'page'    => $result['page'],
					'type'    => 'inspection',
					'details' => $result['error'],
				);

				continue;
			}

			if ( ! empty( $result['coverage_state'] ) && false !== stripos( $result['coverage_state'], 'not indexed' ) ) {
				$issues[] = array(
					'page'    => $result['page'],
					'type'    => 'coverage',
					'details' => $result['coverage_state'],
				);
			}

			if ( ! empty( $result['robots_txt_state'] ) && 'ALLOWED' !== $result['robots_txt_state'] ) {
				$issues[] = array(
					'page'    => $result['page'],
					'type'    => 'robots',
					'details' => $result['robots_txt_state'],
				);
			}

			if ( ! empty( $result['indexing_state'] ) && 'INDEXING_ALLOWED' !== $result['indexing_state'] ) {
				$issues[] = array(
					'page'    => $result['page'],
					'type'    => 'indexing',
					'details' => $result['indexing_state'],
				);
			}

			if ( ! empty( $result['page_fetch_state'] ) && 'SUCCESSFUL' !== $result['page_fetch_state'] ) {
				$issues[] = array(
					'page'    => $result['page'],
					'type'    => 'fetch',
					'details' => $result['page_fetch_state'],
				);
			}
		}

		return array_slice( $issues, 0, 20 );
	}

	/**
	 * Extract canonical mismatches from URL Inspection results.
	 *
	 * @since    1.1.0
	 * @param    array    $inspection_results    URL Inspection results.
	 * @return   array
	 */
	private function extract_canonical_mismatches( $inspection_results ) {
		$mismatches = array();

		foreach ( $inspection_results as $result ) {
			if ( empty( $result['user_canonical'] ) || empty( $result['google_canonical'] ) || $result['user_canonical'] === $result['google_canonical'] ) {
				continue;
			}

			$mismatches[] = array(
				'page'             => $result['page'],
				'user_canonical'   => $result['user_canonical'],
				'google_canonical' => $result['google_canonical'],
			);
		}

		return array_slice( $mismatches, 0, 10 );
	}

	/**
	 * Execute an authenticated JSON request against the Search Console API.
	 *
	 * @since    1.1.0
	 * @param    string       $method    HTTP method.
	 * @param    string       $url       API endpoint.
	 * @param    array|null   $body      Optional JSON payload.
	 * @return   array|WP_Error
	 */
	private function request_json( $method, $url, $body = null ) {
		$access_token = $this->get_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/json',
			),
			'timeout' => 20,
		);

		if ( 'POST' === strtoupper( $method ) ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
			$response                        = wp_remote_post( $url, $args );
		} else {
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		$response_body = (string) wp_remote_retrieve_body( $response );
		$decoded_body  = json_decode( $response_body, true );

		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_message = $decoded_body['error']['message'] ?? 'Search Console request failed.';

			return new WP_Error( 'lightweight_seo_search_console_request_failed', sanitize_text_field( $error_message ) );
		}

		return is_array( $decoded_body ) ? $decoded_body : array();
	}

	/**
	 * Get a bearer token using service-account credentials.
	 *
	 * @since    1.1.0
	 * @return   string|WP_Error
	 */
	private function get_access_token() {
		$cached_token = get_option( self::TOKEN_OPTION_NAME, array() );

		if ( ! empty( $cached_token['access_token'] ) && ! empty( $cached_token['expires_at'] ) && (int) $cached_token['expires_at'] > ( time() + 60 ) ) {
			return $cached_token['access_token'];
		}

		$credentials = $this->get_service_account_credentials();

		if ( empty( $credentials ) ) {
			return new WP_Error( 'lightweight_seo_search_console_missing_credentials', 'Search Console service-account credentials are missing.' );
		}

		if ( ! function_exists( 'openssl_sign' ) ) {
			return new WP_Error( 'lightweight_seo_search_console_openssl_missing', 'OpenSSL is required to sign Search Console service-account requests.' );
		}

		$assertion = $this->build_service_account_assertion( $credentials );

		if ( is_wp_error( $assertion ) ) {
			return $assertion;
		}

		$response = wp_remote_post(
			$credentials['token_uri'],
			array(
				'timeout' => 20,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $assertion,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = (int) wp_remote_retrieve_response_code( $response );
		$response_body = (string) wp_remote_retrieve_body( $response );
		$decoded_body  = json_decode( $response_body, true );

		if ( $response_code < 200 || $response_code >= 300 || empty( $decoded_body['access_token'] ) ) {
			$error_message = $decoded_body['error_description'] ?? $decoded_body['error'] ?? 'Failed to retrieve a Search Console access token.';

			return new WP_Error( 'lightweight_seo_search_console_token_failed', sanitize_text_field( (string) $error_message ) );
		}

		update_option(
			self::TOKEN_OPTION_NAME,
			array(
				'access_token' => sanitize_text_field( $decoded_body['access_token'] ),
				'expires_at'   => time() + max( 60, absint( $decoded_body['expires_in'] ?? 3600 ) - 60 ),
			)
		);

		return sanitize_text_field( $decoded_body['access_token'] );
	}

	/**
	 * Build a JWT bearer assertion for the Google token exchange.
	 *
	 * @since    1.1.0
	 * @param    array    $credentials    Normalized service-account credentials.
	 * @return   string|WP_Error
	 */
	private function build_service_account_assertion( $credentials ) {
		$header    = $this->base64url_encode(
			wp_json_encode(
				array(
					'alg' => 'RS256',
					'typ' => 'JWT',
				)
			)
		);
		$issued_at = time();
		$payload   = $this->base64url_encode(
			wp_json_encode(
				array(
					'iss'   => $credentials['client_email'],
					'scope' => self::API_SCOPE,
					'aud'   => $credentials['token_uri'],
					'iat'   => $issued_at,
					'exp'   => $issued_at + 3600,
				)
			)
		);
		$unsigned  = $header . '.' . $payload;
		$signature = '';

		if ( ! openssl_sign( $unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256 ) ) {
			return new WP_Error( 'lightweight_seo_search_console_signing_failed', 'Failed to sign the Search Console service-account request.' );
		}

		return $unsigned . '.' . $this->base64url_encode( $signature );
	}

	/**
	 * Normalize service-account credentials from saved settings.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function get_service_account_credentials() {
		$raw_json = $this->settings->get_search_console_service_account_json();

		if ( empty( $raw_json ) ) {
			return array();
		}

		$credentials = json_decode( $raw_json, true );

		if ( ! is_array( $credentials ) || empty( $credentials['client_email'] ) || empty( $credentials['private_key'] ) ) {
			return array();
		}

		$credentials['token_uri'] = ! empty( $credentials['token_uri'] ) ? esc_url_raw( $credentials['token_uri'] ) : 'https://oauth2.googleapis.com/token';

		return $credentials;
	}

	/**
	 * Encode a string using URL-safe base64.
	 *
	 * @since    1.1.0
	 * @param    string    $value    Raw string value.
	 * @return   string
	 */
	private function base64url_encode( $value ) {
		return rtrim( strtr( base64_encode( (string) $value ), '+/', '-_' ), '=' );
	}
}
