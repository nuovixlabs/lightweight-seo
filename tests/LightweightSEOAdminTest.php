<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-internal-links-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-redirects-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-search-console-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-compatibility-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-image-audit-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-importer-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOAdminTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_attachment_urls;
		global $lightweight_seo_test_nonce_is_valid;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_rendered_settings_errors;
		global $lightweight_seo_test_settings_errors;
		global $lightweight_seo_test_user_can;
		global $lightweight_seo_test_cache;
		global $lightweight_seo_test_network_admin;

		$lightweight_seo_test_attachment_urls          = array();
		$lightweight_seo_test_nonce_is_valid           = true;
		$lightweight_seo_test_options                  = array();
		$lightweight_seo_test_posts                    = array();
		$lightweight_seo_test_rendered_settings_errors = array();
		$lightweight_seo_test_settings_errors          = array();
		$lightweight_seo_test_user_can                 = true;
		$lightweight_seo_test_cache                    = array();
		$lightweight_seo_test_network_admin            = false;
	}

	public function test_validate_settings_preserves_existing_tracking_ids_when_invalid(): void {
		global $lightweight_seo_test_attachment_urls;
		global $lightweight_seo_test_settings_errors;

		$lightweight_seo_test_attachment_urls[27] = 'https://example.com/new-image.jpg';

		$settings = new class() {
			public function get_all() {
				return array(
					'title_format'                        => LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT,
					'home_title_format'                   => '%sitename% %sep% %tagline%',
					'archive_title_format'                => '%title% %sep% %sitename%',
					'search_title_format'                 => 'Search Results for "%search%" %sep% %sitename%',
					'meta_description'                    => 'Existing description',
					'meta_keywords'                       => 'existing,keywords',
					'enable_meta_keywords'                => '1',
					'noindex_search_results'              => '1',
					'noindex_attachment_pages'            => '1',
					'exclude_noindex_from_sitemaps'       => '1',
					'enable_image_sitemaps'               => '1',
					'enable_schema_output'                => '1',
					'organization_same_as'                => "https://example.com/about\nhttps://example.com/x",
					'search_console_property'             => 'https://example.com/',
					'search_console_service_account_json' => '{"client_email":"existing@example.com","private_key":"EXISTING","token_uri":"https://oauth2.googleapis.com/token"}',
					'enable_404_monitor'                  => '1',
					'enable_auto_redirects'               => '1',
					'redirect_rules'                      => '/old-page /new-page 301',
					'default_max_image_preview'           => 'large',
					'social_image'                        => 'https://example.com/existing-image.jpg',
					'social_image_id'                     => 14,
					'ga4_measurement_id'                  => 'G-OLD123',
					'gtm_container_id'                    => 'GTM-OLD123',
					'facebook_pixel_id'                   => '12345',
				);
			}

			public function normalize_max_image_preview( $value, $fallback = '' ) {
				$allowed = array( 'none', 'standard', 'large' );

				return in_array( $value, $allowed, true ) ? $value : $fallback;
			}

			public function get_default_max_image_preview() {
				return 'large';
			}

			public function normalize_redirect_rules_input( $value ) {
				return "/old-path /new-path 301\n/legacy https://example.com/destination 302";
			}
		};

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}
		};

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.1.0', $settings, $post_meta );

		$validated = $admin->validate_settings(
			array(
				'title_format'                        => '%title% – %sitename%',
				'home_title_format'                   => '%sitename%',
				'archive_title_format'                => '%title% | %sitename%',
				'search_title_format'                 => 'Find %search%',
				'meta_description'                    => 'New description',
				'meta_keywords'                       => 'new,keywords',
				'default_max_image_preview'           => 'invalid',
				'organization_same_as'                => "https://example.com/linkedin\nnot-a-url\nhttps://example.com/linkedin",
				'search_console_property'             => 'sc-domain:example.com',
				'search_console_service_account_json' => '{"client_email":"search-console@example.com","private_key":"-----BEGIN PRIVATE KEY-----\nABC123\n-----END PRIVATE KEY-----\n"}',
				'redirect_rules'                      => "/old-path /new-path 301\ninvalid rule",
				'social_image'                        => 'https://example.com/new-image.jpg',
				'social_image_id'                     => '27',
				'ga4_measurement_id'                  => 'invalid-ga4',
				'gtm_container_id'                    => 'gtm-new123',
				'facebook_pixel_id'                   => 'abc123',
			)
		);

		$this->assertSame( 'G-OLD123', $validated['ga4_measurement_id'] );
		$this->assertSame( 'GTM-NEW123', $validated['gtm_container_id'] );
		$this->assertSame( '12345', $validated['facebook_pixel_id'] );
		$this->assertSame( '%sitename%', $validated['home_title_format'] );
		$this->assertSame( '%title% | %sitename%', $validated['archive_title_format'] );
		$this->assertSame( 'Find %search%', $validated['search_title_format'] );
		$this->assertSame( '0', $validated['enable_meta_keywords'] );
		$this->assertSame( '0', $validated['noindex_search_results'] );
		$this->assertSame( '0', $validated['noindex_attachment_pages'] );
		$this->assertSame( '0', $validated['exclude_noindex_from_sitemaps'] );
		$this->assertSame( '0', $validated['enable_image_sitemaps'] );
		$this->assertSame( '0', $validated['enable_schema_output'] );
		$this->assertSame( '0', $validated['enable_404_monitor'] );
		$this->assertSame( '0', $validated['enable_auto_redirects'] );
		$this->assertSame( 'https://example.com/linkedin', $validated['organization_same_as'] );
		$this->assertSame( 'sc-domain:example.com', $validated['search_console_property'] );
		$this->assertStringContainsString( 'search-console@example.com', $validated['search_console_service_account_json'] );
		$this->assertSame( "/old-path /new-path 301\n/legacy https://example.com/destination 302", $validated['redirect_rules'] );
		$this->assertSame( 'large', $validated['default_max_image_preview'] );
		$this->assertSame( 27, $validated['social_image_id'] );
		$this->assertCount( 2, $lightweight_seo_test_settings_errors );
	}

	public function test_validate_settings_clears_stale_social_image_id_for_manual_urls(): void {
		global $lightweight_seo_test_attachment_urls;

		$lightweight_seo_test_attachment_urls[14] = 'https://example.com/existing-image.jpg';

		$settings = new class() {
			public function get_all() {
				return array(
					'title_format'         => LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT,
					'meta_description'     => 'Existing description',
					'meta_keywords'        => 'existing,keywords',
					'enable_meta_keywords' => '1',
					'social_image'         => 'https://example.com/existing-image.jpg',
					'social_image_id'      => 14,
					'ga4_measurement_id'   => '',
					'gtm_container_id'     => '',
					'facebook_pixel_id'    => '',
				);
			}

			public function normalize_max_image_preview( $value, $fallback = '' ) {
				return 'large';
			}

			public function normalize_redirect_rules_input( $value ) {
				return '';
			}
		};

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}
		};

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.1.0', $settings, $post_meta );

		$validated = $admin->validate_settings(
			array(
				'social_image'    => 'https://cdn.example.com/manual-image.jpg',
				'social_image_id' => '14',
			)
		);

		$this->assertSame( 'https://cdn.example.com/manual-image.jpg', $validated['social_image'] );
		$this->assertSame( 0, $validated['social_image_id'] );
	}

	public function test_validate_settings_preserves_attachment_id_when_only_the_resolved_url_changes(): void {
		global $lightweight_seo_test_attachment_urls;

		$lightweight_seo_test_attachment_urls[14] = 'https://example.com/uploads/current-image.jpg';

		$settings = new class() {
			public function get_all() {
				return array(
					'title_format'         => LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT,
					'meta_description'     => 'Existing description',
					'meta_keywords'        => 'existing,keywords',
					'enable_meta_keywords' => '1',
					'social_image'         => 'https://example.com/uploads/old-image.jpg',
					'social_image_id'      => 14,
					'ga4_measurement_id'   => '',
					'gtm_container_id'     => '',
					'facebook_pixel_id'    => '',
				);
			}

			public function normalize_max_image_preview( $value, $fallback = '' ) {
				return 'large';
			}

			public function normalize_redirect_rules_input( $value ) {
				return '';
			}
		};

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}
		};

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.1.0', $settings, $post_meta );

		$validated = $admin->validate_settings(
			array(
				'social_image'    => 'https://example.com/uploads/current-image.jpg',
				'social_image_id' => '14',
			)
		);

		$this->assertSame( 'https://example.com/uploads/current-image.jpg', $validated['social_image'] );
		$this->assertSame( 14, $validated['social_image_id'] );
	}

	public function test_validate_settings_normalizes_url_prefix_search_console_properties(): void {
		$settings = new class() {
			public function get_all() {
				return array(
					'title_format'                        => LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT,
					'home_title_format'                   => '%sitename% %sep% %tagline%',
					'archive_title_format'                => '%title% %sep% %sitename%',
					'search_title_format'                 => 'Search Results for "%search%" %sep% %sitename%',
					'meta_description'                    => '',
					'meta_keywords'                       => '',
					'enable_meta_keywords'                => '1',
					'noindex_search_results'              => '1',
					'noindex_attachment_pages'            => '1',
					'exclude_noindex_from_sitemaps'       => '1',
					'enable_image_sitemaps'               => '1',
					'enable_schema_output'                => '1',
					'organization_same_as'                => '',
					'search_console_property'             => '',
					'search_console_service_account_json' => '',
					'enable_404_monitor'                  => '1',
					'enable_auto_redirects'               => '1',
					'redirect_rules'                      => '',
					'default_max_image_preview'           => 'large',
					'social_image'                        => '',
					'social_image_id'                     => 0,
					'ga4_measurement_id'                  => '',
					'gtm_container_id'                    => '',
					'facebook_pixel_id'                   => '',
				);
			}

			public function normalize_max_image_preview( $value, $fallback = '' ) {
				return in_array( $value, array( 'none', 'standard', 'large' ), true ) ? $value : $fallback;
			}

			public function get_default_max_image_preview() {
				return 'large';
			}

			public function normalize_redirect_rules_input( $value ) {
				return '';
			}
		};

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}
		};

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.1.0', $settings, $post_meta );

		$validated = $admin->validate_settings(
			array(
				'search_console_property' => 'https://Example.com/blog',
			)
		);

		$this->assertSame( 'https://example.com/blog/', $validated['search_console_property'] );
	}

	public function test_internal_link_report_render_outputs_orphans_and_broken_links(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_options = array();
		$lightweight_seo_test_posts   = array(
			11 => (object) array(
				'ID'           => 11,
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'SEO Basics',
				'post_content' => '<a href="/beta/">read more</a><a href="/missing-page/">Missing</a><p>This SEO audit guide covers crawl depth.</p>',
				'permalink'    => 'https://example.com/alpha/',
			),
			12 => (object) array(
				'ID'           => 12,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Metadata Tips',
				'post_content' => '<a href="/alpha/">click here</a>',
				'permalink'    => 'https://example.com/beta/',
			),
			13 => (object) array(
				'ID'           => 13,
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'SEO Audit Guide',
				'post_content' => '',
				'permalink'    => 'https://example.com/gamma/',
			),
		);

		$settings = new class() {
			public function get_all() {
				return array();
			}
		};

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}

			public function get_all( $post_id ) {
				return array();
			}
		};

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.1.0', $settings, $post_meta );

		ob_start();
		$admin->internal_link_report_render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Scanned 3 pages and found 3 internal links.', $output );
		$this->assertStringContainsString( 'Orphan Pages', $output );
		$this->assertStringContainsString( 'SEO Audit Guide', $output );
		$this->assertStringContainsString( 'Broken Internal Links', $output );
		$this->assertStringContainsString( 'Anchor Text Issues', $output );
		$this->assertStringContainsString( 'Suggested Internal Links', $output );
		$this->assertStringContainsString( 'Topic Clusters', $output );
		$this->assertStringContainsString( 'Recommended Anchor', $output );
		$this->assertStringContainsString( '/missing-page', $output );
	}

	public function test_image_discover_report_render_outputs_image_audit_segments(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['thumbnail_url'] = '';
		$lightweight_seo_test_post_meta                    = array(
			31  => array(
				'_lightweight_seo_noindex' => '',
			),
			32  => array(
				'_lightweight_seo_noindex' => '',
			),
			501 => array(
				'_wp_attachment_image_alt' => '',
			),
		);
		$lightweight_seo_test_posts                        = array(
			31  => (object) array(
				'ID'            => 31,
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => 'Discover Candidate',
				'post_content'  => '',
				'permalink'     => 'https://example.com/discover-candidate/',
				'thumbnail_id'  => 501,
				'thumbnail_url' => 'https://example.com/uploads/discover.jpg',
			),
			32  => (object) array(
				'ID'           => 32,
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'No Thumbnail',
				'post_content' => '',
				'permalink'    => 'https://example.com/no-thumbnail/',
			),
			501 => (object) array(
				'ID'             => 501,
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/jpeg',
				'attachment_url' => 'https://example.com/uploads/discover.jpg',
				'metadata'       => array(
					'width'  => 800,
					'height' => 600,
				),
			),
		);

		$settings = new class() {
			public function get_all() {
				return array(
					'discover_min_image_width'  => 1200,
					'discover_min_image_height' => 900,
				);
			}

			public function get_discover_min_image_width() {
				return 1200;
			}

			public function get_discover_min_image_height() {
				return 900;
			}
		};

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}

			public function get_all( $post_id ) {
				return array(
					'seo_noindex' => '',
				);
			}
		};

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.1.0', $settings, $post_meta );

		ob_start();
		$admin->image_discover_report_render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Missing Featured Images', $output );
		$this->assertStringContainsString( 'Missing Alt Text', $output );
		$this->assertStringContainsString( 'Undersized Featured Images', $output );
		$this->assertStringContainsString( 'No Thumbnail', $output );
		$this->assertStringContainsString( 'Discover Candidate', $output );
	}

	public function test_redirect_health_render_outputs_detected_issues(): void {
		$settings = new class() {
			public function get_all() {
				return array();
			}

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
				);
			}

			public function not_found_monitor_enabled() {
				return true;
			}

			public function auto_redirects_enabled() {
				return true;
			}
		};

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}
		};

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.1.0', $settings, $post_meta );

		ob_start();
		$admin->redirect_health_render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Chain', $output );
		$this->assertStringContainsString( '/old-page', $output );
		$this->assertStringContainsString( '/old-page -&gt; /mid-page -&gt; /final-page', $output );
	}

	public function test_redirect_export_render_outputs_normalized_rules(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ Lightweight_SEO_Redirects_Service::GENERATED_RULES_OPTION_NAME ] = array(
			array(
				'source'     => '/legacy-page',
				'target'     => '/fresh-page',
				'status'     => 301,
				'object_id'  => 9,
				'updated_at' => '2026-03-21T00:00:00+00:00',
			),
		);

		$settings = new class() {
			public function get_all() {
				return array();
			}

			public function get_manual_redirect_rules() {
				return array(
					array(
						'source' => '/old-page',
						'target' => '/new-page',
						'status' => 302,
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

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}
		};

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.1.0', $settings, $post_meta );

		ob_start();
		$admin->redirect_export_render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '/old-page /new-page 302', $output );
		$this->assertStringContainsString( '/legacy-page /fresh-page 301', $output );
	}

	public function test_safe_mode_notice_renders_when_conflicting_plugin_is_active(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options['active_plugins'] = array(
			'wordpress-seo/wp-seo.php',
		);

		$settings = new class() {
			public function get_all() {
				return array();
			}
		};

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}
		};

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.1.0', $settings, $post_meta );

		ob_start();
		$admin->maybe_render_safe_mode_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'safe mode is active', $output );
		$this->assertStringContainsString( 'Yoast SEO', $output );
	}

	public function test_display_plugin_admin_page_renders_settings_errors(): void {
		global $lightweight_seo_test_rendered_settings_errors;

		$settings = new class() {
			public function get_all() {
				return array();
			}
		};

		$post_meta = new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}
		};

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.1.0', $settings, $post_meta );

		ob_start();
		$admin->display_plugin_admin_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'settings-errors', $output );
		$this->assertSame( array( LIGHTWEIGHT_SEO_OPTION_NAME ), $lightweight_seo_test_rendered_settings_errors );
	}
}
