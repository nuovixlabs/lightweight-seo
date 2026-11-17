<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-ui.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-redirects-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-compatibility-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-importer-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-import-state.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-core-fields.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-module-fields.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-tools-fields.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-page.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-notices.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-tools.php';
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
		global $lightweight_seo_test_hook_callbacks;
		global $lightweight_seo_test_menu_pages;
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_registered_settings;
		global $lightweight_seo_test_screen;
		global $wp_settings_fields;
		global $wp_settings_sections;

		$lightweight_seo_test_hook_callbacks      = array();
		$lightweight_seo_test_menu_pages          = array();
		$lightweight_seo_test_post_meta           = array();
		$lightweight_seo_test_registered_settings = array();
		$lightweight_seo_test_screen              = null;
		$wp_settings_fields                       = array();
		$wp_settings_sections                     = array();
		$_GET                                     = array();
		$_POST                                    = array();

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

	protected function tearDown(): void {
		$_GET  = array();
		$_POST = array();
	}

	private function get_post_meta() {
		return new class() {
			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}
		};
	}

	private function get_tools_fields( $settings ) {
		return new Lightweight_SEO_Admin_Tools_Fields(
			$settings,
			new Lightweight_SEO_Importer_Service( $this->get_post_meta() ),
			new Lightweight_SEO_Import_State()
		);
	}

	private function get_admin_settings( $settings ) {
		return new Lightweight_SEO_Admin_Settings(
			'lightweight-seo',
			$settings,
			new Lightweight_SEO_Admin_Core_Fields( $settings ),
			new Lightweight_SEO_Admin_Module_Fields( $settings ),
			$this->get_tools_fields( $settings )
		);
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

		$validated = $this->get_admin_settings( $settings )->validate_settings(
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
		$this->assertSame( 'existing,keywords', $validated['meta_keywords'] );
		$this->assertArrayNotHasKey( 'enable_meta_keywords', $validated );
		$this->assertSame( '0', $validated['noindex_search_results'] );
		$this->assertSame( '0', $validated['noindex_attachment_pages'] );
		$this->assertSame( '0', $validated['exclude_noindex_from_sitemaps'] );
		$this->assertArrayNotHasKey( 'enable_image_sitemaps', $validated );
		$this->assertSame( '0', $validated['enable_schema_output'] );
		$this->assertArrayNotHasKey( 'enable_404_monitor', $validated );
		$this->assertSame( '0', $validated['enable_auto_redirects'] );
		$this->assertSame( 'https://example.com/linkedin', $validated['organization_same_as'] );
		$this->assertSame( 'https://example.com/', $validated['search_console_property'] );
		$this->assertStringContainsString( 'existing@example.com', $validated['search_console_service_account_json'] );
		$this->assertSame( "/old-path /new-path 301\n/legacy https://example.com/destination 302", $validated['redirect_rules'] );
		$this->assertSame( 'large', $validated['default_max_image_preview'] );
		$this->assertSame( 27, $validated['social_image_id'] );
		$this->assertCount( 2, $lightweight_seo_test_settings_errors );
	}

	public function test_validate_settings_disables_invalid_local_seo_and_normalizes_tracking_exclusions(): void {
		global $lightweight_seo_test_settings_errors;

		$validated = $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings(
			array(
				'settings_tab'                   => 'modules',
				'enable_local_business_schema'   => '1',
				'local_business_name'            => 'Example Cafe',
				'local_business_address_street'  => '123 Main St',
				'local_business_address_country' => 'USA',
				'local_business_latitude'        => '91',
				'local_business_longitude'       => '-181',
				'local_business_opening_hours'   => 'weekdays',
				'tracking_excluded_roles'        => 'Administrator, EDITOR, invalid role',
				'tracking_excluded_environments' => 'local, staging, unknown',
			)
		);

		$this->assertSame( '0', $validated['enable_local_business_schema'] );
		$this->assertSame( '', $validated['local_business_address_country'] );
		$this->assertSame( '', $validated['local_business_latitude'] );
		$this->assertSame( "administrator\neditor\ninvalid\nrole", $validated['tracking_excluded_roles'] );
		$this->assertSame( "local\nstaging", $validated['tracking_excluded_environments'] );
		$this->assertNotEmpty( $lightweight_seo_test_settings_errors );
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

		$validated = $this->get_admin_settings( $settings )->validate_settings(
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

		$validated = $this->get_admin_settings( $settings )->validate_settings(
			array(
				'social_image'    => 'https://example.com/uploads/current-image.jpg',
				'social_image_id' => '14',
			)
		);

		$this->assertSame( 'https://example.com/uploads/current-image.jpg', $validated['social_image'] );
		$this->assertSame( 14, $validated['social_image_id'] );
	}

	public function test_redirect_health_render_outputs_detected_issues(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ] = array( 'redirects' => true );

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

		ob_start();
		( new Lightweight_SEO_Admin_Module_Fields( $settings ) )->redirect_health_render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Chain', $output );
		$this->assertStringContainsString( '/old-page', $output );
		$this->assertStringContainsString( '/old-page -&gt; /mid-page -&gt; /final-page', $output );
	}

	public function test_redirect_export_render_outputs_normalized_rules(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ]                            = array( 'redirects' => true );
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

		ob_start();
		( new Lightweight_SEO_Admin_Module_Fields( $settings ) )->redirect_export_render();
		$output = ob_get_clean();

		$this->assertStringContainsString( '/old-page /new-page 302', $output );
		$this->assertStringContainsString( '/legacy-page /fresh-page 301', $output );
	}

	public function test_safe_mode_notice_renders_when_conflicting_plugin_is_active(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options['active_plugins'] = array(
			'wordpress-seo/wp-seo.php',
		);

		ob_start();
		( new Lightweight_SEO_Admin_Notices( 'lightweight-seo' ) )->maybe_render_safe_mode_notice();
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
		$page     = new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ) );

		ob_start();
		$page->display_plugin_admin_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'settings-errors', $output );
		$this->assertStringContainsString( 'Essential setup', $output );
		$this->assertStringContainsString( 'Search appearance', $output );
		$this->assertStringContainsString( 'Developer API', $output );
		$this->assertSame( array( LIGHTWEIGHT_SEO_OPTION_NAME ), $lightweight_seo_test_rendered_settings_errors );
	}

	public function test_tab_save_preserves_checkbox_values_from_other_tabs(): void {
		global $lightweight_seo_test_options;

		$existing                                 = ( new Lightweight_SEO_Settings() )->get_defaults();
		$existing['noindex_search_results']       = '1';
		$existing['enable_schema_output']         = '1';
		$existing['enable_local_business_schema'] = '1';
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = $existing;

		$validated = $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings(
			array(
				'settings_tab' => 'appearance',
				'title_format' => '%title% | %sitename%',
			)
		);

		$this->assertSame( '%title% | %sitename%', $validated['title_format'] );
		$this->assertSame( '1', $validated['noindex_search_results'] );
		$this->assertSame( '1', $validated['enable_schema_output'] );
		$this->assertSame( '1', $validated['enable_local_business_schema'] );
	}

	public function test_tools_save_explicitly_deletes_retained_search_console_data(): void {
		global $lightweight_seo_test_options;

		$settings_data                                        = ( new Lightweight_SEO_Settings() )->get_defaults();
		$settings_data['search_console_property']             = 'sc-domain:example.com';
		$settings_data['search_console_service_account_json'] = '{"private_key":"legacy"}';
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = $settings_data;

		$validated = $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings(
			array(
				'settings_tab'                      => 'tools',
				'delete_legacy_search_console_data' => '1',
			)
		);

		$this->assertArrayNotHasKey( 'search_console_property', $validated );
		$this->assertArrayNotHasKey( 'search_console_service_account_json', $validated );
	}

	public function test_validate_settings_never_imports_rolls_back_or_deletes(): void {
		global $lightweight_seo_test_get_posts_calls;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$settings_data                  = ( new Lightweight_SEO_Settings() )->get_defaults();
		$settings_data['import_source'] = 'yoast';
		$lightweight_seo_test_options   = array(
			LIGHTWEIGHT_SEO_OPTION_NAME               => $settings_data,
			'lightweight_seo_404_logs'                => array( '/missing' ),
			Lightweight_SEO_Importer_Service::ROLLBACK_OPTION => array(
				'source'  => 'yoast',
				'offset'  => 0,
				'changes' => array(
					array(
						'post_id'   => 81,
						'field'     => 'seo_title',
						'existed'   => false,
						'old_value' => '',
					),
				),
			),
			Lightweight_SEO_Import_State::OPTION_NAME => array(
				'source' => 'yoast',
				'cursor' => 50,
				'report' => 'Imported yoast batch.',
			),
		);
		$lightweight_seo_test_posts     = array(
			81 => (object) array(
				'ID'          => 81,
				'post_type'   => 'post',
				'post_status' => 'publish',
			),
		);
		$lightweight_seo_test_post_meta = array(
			81 => array( 'rank_math_title' => 'Imported Title' ),
		);
		$options_before                 = $lightweight_seo_test_options;
		$post_meta_before               = $lightweight_seo_test_post_meta;
		$queries_before                 = $lightweight_seo_test_get_posts_calls;
		$admin_settings                 = $this->get_admin_settings( new Lightweight_SEO_Settings() );
		// Every request field that used to start an import, a rollback, or a deletion.
		$input = array(
			'settings_tab'           => 'tools',
			'import_source'          => 'rank_math',
			'import_action'          => 'import',
			'delete_legacy_404_logs' => '1',
		);

		$first  = $admin_settings->validate_settings( $input );
		$second = $admin_settings->validate_settings( array_merge( $input, array( 'import_action' => 'rollback' ) ) );

		$this->assertSame( $options_before, $lightweight_seo_test_options );
		$this->assertSame( $post_meta_before, $lightweight_seo_test_post_meta );
		$this->assertSame( $queries_before, $lightweight_seo_test_get_posts_calls );
		$this->assertSame( $first, $second );
		$this->assertSame( 'rank_math', $first['import_source'] );
		$this->assertArrayNotHasKey( 'import_action', $first );
		$this->assertArrayNotHasKey( 'import_cursor', $first );
		$this->assertArrayNotHasKey( 'last_import_report', $first );
	}

	public function test_validate_settings_returns_its_own_output_unchanged(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = ( new Lightweight_SEO_Settings() )->get_defaults();
		$admin_settings = $this->get_admin_settings( new Lightweight_SEO_Settings() );

		// A form post from the indexation tab with one box ticked and the rest cleared.
		$first = $admin_settings->validate_settings(
			array(
				'settings_tab'           => 'indexation',
				'noindex_search_results' => '1',
			)
		);
		// WordPress sanitizes a second time when it has to create the option row.
		$second = $admin_settings->validate_settings( $first );

		$this->assertSame( '1', $first['noindex_search_results'] );
		$this->assertSame( '0', $first['noindex_attachment_pages'] );
		$this->assertSame( '0', $first['delete_data_on_uninstall'] );
		$this->assertSame( $first, $second );
	}

	public function test_register_settings_keeps_every_field_in_its_section_and_order(): void {
		global $lightweight_seo_test_registered_settings;
		global $wp_settings_fields;
		global $wp_settings_sections;

		$admin_settings = $this->get_admin_settings( new Lightweight_SEO_Settings() );
		$admin_settings->register_settings();

		$this->assertSame(
			array(
				'lightweight_seo_general_section'      => array( 'title_format', 'home_title_format', 'archive_title_format', 'search_title_format', 'meta_description', 'social_image' ),
				'lightweight_seo_indexation_section'   => array( 'noindex_search_results', 'noindex_attachment_pages', 'default_max_image_preview', 'enable_media_x_robots_headers' ),
				'lightweight_seo_sitemap_section'      => array( 'exclude_noindex_from_sitemaps', 'exclude_redirected_from_sitemaps' ),
				'lightweight_seo_schema_section'       => array( 'enable_schema_output', 'organization_same_as', 'enable_local_business_schema', 'local_business_details', 'enable_hreflang_output', 'hreflang_mappings', 'enable_hreflang_path_mirroring' ),
				'lightweight_seo_redirects_section'    => array( 'enable_auto_redirects', 'redirect_rules', 'generated_redirect_rules', 'redirect_export', 'redirect_health' ),
				'lightweight_seo_migration_section'    => array( 'import_source', 'import_actions', 'import_report', 'legacy_data_transition', 'delete_data_on_uninstall' ),
				'lightweight_seo_tracking_section'     => array( 'gtm_container_id', 'ga4_measurement_id', 'facebook_pixel_id', 'tracking_excluded_roles', 'tracking_excluded_environments' ),
				'lightweight_seo_ai_discovery_section' => array( 'enable_ai_discovery', 'ai_search_crawlers_enabled', 'ai_training_crawlers_enabled', 'enable_llms_txt', 'llms_txt_post_ids', 'ai_readiness' ),
			),
			array_map( 'array_keys', $wp_settings_fields['lightweight-seo'] )
		);
		$this->assertSame( array_keys( $wp_settings_fields['lightweight-seo'] ), array_keys( $wp_settings_sections['lightweight-seo'] ) );

		foreach ( $wp_settings_sections['lightweight-seo'] as $section_id => $section ) {
			$this->assertTrue( is_callable( $section['callback'] ), $section_id );

			foreach ( $wp_settings_fields['lightweight-seo'][ $section_id ] as $field_id => $field ) {
				$this->assertTrue( is_callable( $field['callback'] ), $field_id );
			}
		}

		$this->assertSame(
			array( $admin_settings, 'validate_settings' ),
			$lightweight_seo_test_registered_settings[ LIGHTWEIGHT_SEO_OPTION_NAME ]['args']
		);
	}

	public function test_tools_tab_submits_each_tool_action_through_its_own_form_and_nonce(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options['lightweight_seo_404_logs']                          = array( '/missing', '/gone' );
		$lightweight_seo_test_options[ Lightweight_SEO_Importer_Service::ROLLBACK_OPTION ] = array(
			'changes' => array(
				array(
					'post_id' => 81,
					'field'   => 'seo_title',
				),
			),
		);
		$lightweight_seo_test_options[ Lightweight_SEO_Import_State::OPTION_NAME ]         = array(
			'source' => 'yoast',
			'cursor' => 50,
			'report' => 'Imported yoast batch.',
		);

		$settings = new Lightweight_SEO_Settings();
		$this->get_admin_settings( $settings )->register_settings();
		$_GET['tab'] = 'tools';

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ) ) )->display_plugin_admin_page();
		$output = ob_get_clean();

		// The tool forms follow the settings form; they are never nested inside it.
		$import_form = strpos( $output, '<form id="lightweight-seo-import-form" method="post" action="https://example.com/wp-admin/admin-post.php">' );
		$this->assertNotFalse( $import_form );
		$this->assertGreaterThan( strpos( $output, '</form>' ), $import_form );

		foreach ( array( 'lightweight_seo_import_preview', 'lightweight_seo_import_batch', 'lightweight_seo_import_rollback' ) as $action ) {
			$this->assertStringContainsString( 'form="lightweight-seo-import-form" name="action" value="' . $action . '"', $output );
			$this->assertStringContainsString( 'name="' . $action . '_nonce" value="nonce-' . $action . '"', $output );
		}

		$this->assertStringContainsString( '<select id="lightweight_seo_import_source" name="import_source" form="lightweight-seo-import-form">', $output );
		$this->assertStringContainsString( 'The next batch starts after 50 scanned posts.', $output );
		$this->assertStringContainsString( 'Imported yoast batch.', $output );

		$this->assertStringContainsString( '<form id="lightweight-seo-delete-404-logs-form" method="post" action="https://example.com/wp-admin/admin-post.php">', $output );
		$this->assertStringContainsString( '<input type="hidden" name="action" value="lightweight_seo_delete_legacy_404_logs">', $output );
		$this->assertStringContainsString( 'name="lightweight_seo_delete_legacy_404_logs_nonce" value="nonce-lightweight_seo_delete_legacy_404_logs"', $output );
		$this->assertStringContainsString( 'name="confirm_delete_404_logs" value="1" required form="lightweight-seo-delete-404-logs-form"', $output );
		$this->assertStringContainsString( 'Permanently delete 2 retained legacy 404 log entries', $output );

		// Saving the tab still goes to options.php, and no settings field asks the sanitizer to run a tool.
		$this->assertStringContainsString( '<form method="post" action="options.php"', $output );
		$this->assertStringContainsString( 'lightweight_seo_settings[delete_data_on_uninstall]', $output );
		$this->assertStringNotContainsString( 'lightweight_seo_settings[import_', $output );
		$this->assertStringNotContainsString( 'lightweight_seo_settings[delete_legacy_404_logs]', $output );
	}

	public function test_other_tabs_do_not_render_tool_forms(): void {
		$settings = new Lightweight_SEO_Settings();
		$this->get_admin_settings( $settings )->register_settings();
		$_GET['tab'] = 'appearance';

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ) ) )->display_plugin_admin_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'lightweight_seo_settings[title_format]', $output );
		$this->assertStringNotContainsString( 'admin-post.php', $output );
	}

	public function test_module_cards_collapse_until_the_module_is_enabled(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ] = array( 'redirects' => true );

		$settings = new Lightweight_SEO_Settings();
		$this->get_admin_settings( $settings )->register_settings();
		$_GET['tab'] = 'modules';

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ) ) )->display_plugin_admin_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<details class="lwseo-card" id="lwseo-module-redirects" open>', $output );

		foreach ( array( 'local-seo', 'hreflang', 'tracking', 'ai' ) as $module ) {
			$this->assertStringContainsString( '<details class="lwseo-card" id="lwseo-module-' . $module . '">', $output );
		}

		// Collapsed cards still submit their fields, so saving never drops a hidden setting.
		$this->assertStringContainsString( 'lightweight_seo_settings[gtm_container_id]', $output );
	}

	public function test_single_control_fields_are_labelled_and_groups_are_named(): void {
		$settings = new Lightweight_SEO_Settings();
		$this->get_admin_settings( $settings )->register_settings();
		$_GET['tab'] = 'appearance';

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ) ) )->display_plugin_admin_page();
		$appearance = ob_get_clean();

		$_GET['tab'] = 'indexation';

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ) ) )->display_plugin_admin_page();
		$indexation = ob_get_clean();

		$this->assertStringContainsString( '<label class="lwseo-field__label" for="lightweight_seo_title_format">', $appearance );
		$this->assertStringContainsString( 'id="lightweight_seo_title_format"', $appearance );
		$this->assertStringContainsString( 'role="group" aria-labelledby="lwseo-label-noindex_search_results"', $indexation );
		$this->assertStringContainsString( 'id="lwseo-label-noindex_search_results"', $indexation );
	}

	public function test_saving_is_confirmed_unless_the_plugin_already_reported_something(): void {
		global $lightweight_seo_test_settings_errors;

		$settings = new Lightweight_SEO_Settings();
		$page     = new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ) );

		$_GET['settings-updated'] = 'true';

		ob_start();
		$page->display_plugin_admin_page();
		ob_end_clean();

		$this->assertSame( array( 'settings_updated' ), array_column( $lightweight_seo_test_settings_errors, 'code' ) );

		ob_start();
		$page->display_plugin_admin_page();
		ob_end_clean();

		$this->assertCount( 1, $lightweight_seo_test_settings_errors );
	}

	public function test_admin_connects_each_hook_to_the_class_that_owns_it(): void {
		global $lightweight_seo_test_hook_callbacks;
		global $lightweight_seo_test_menu_pages;

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.1.0', new Lightweight_SEO_Settings(), $this->get_post_meta() );
		$admin->add_plugin_admin_menu();

		$expected = array(
			'admin_init'                                 => array( array( 'Lightweight_SEO_Admin_Settings', 'register_settings' ) ),
			'admin_notices'                              => array(
				array( 'Lightweight_SEO_Admin_Notices', 'maybe_render_safe_mode_notice' ),
				array( 'Lightweight_SEO_Admin_Notices', 'maybe_render_upgrade_summary' ),
			),
			'admin_post_lightweight_seo_dismiss_upgrade_summary' => array( array( 'Lightweight_SEO_Admin_Notices', 'dismiss_upgrade_summary' ) ),
			'admin_post_lightweight_seo_export_legacy_keywords' => array( array( 'Lightweight_SEO_Admin_Tools', 'export_legacy_keywords' ) ),
			'admin_post_lightweight_seo_import_preview'  => array( array( 'Lightweight_SEO_Admin_Tools', 'handle_import_preview' ) ),
			'admin_post_lightweight_seo_import_batch'    => array( array( 'Lightweight_SEO_Admin_Tools', 'handle_import_batch' ) ),
			'admin_post_lightweight_seo_import_rollback' => array( array( 'Lightweight_SEO_Admin_Tools', 'handle_import_rollback' ) ),
			'admin_post_lightweight_seo_delete_legacy_404_logs' => array( array( 'Lightweight_SEO_Admin_Tools', 'handle_delete_legacy_404_logs' ) ),
		);

		foreach ( $expected as $hook => $callbacks ) {
			$registered = array_map(
				static function ( $callback ) {
					return array( get_class( $callback[0] ), $callback[1] );
				},
				$lightweight_seo_test_hook_callbacks[ $hook ] ?? array()
			);

			$this->assertSame( $callbacks, $registered, $hook );

			foreach ( $lightweight_seo_test_hook_callbacks[ $hook ] as $callback ) {
				$this->assertTrue( is_callable( $callback ), $hook );
			}
		}

		$this->assertSame( 'manage_options', $lightweight_seo_test_menu_pages['lightweight-seo']['capability'] );
		$this->assertInstanceOf( Lightweight_SEO_Admin_Page::class, $lightweight_seo_test_menu_pages['lightweight-seo']['callback'][0] );
		$this->assertSame( 'display_plugin_admin_page', $lightweight_seo_test_menu_pages['lightweight-seo']['callback'][1] );
	}

	public function test_safe_mode_notice_is_limited_to_administrators_on_relevant_screens(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_screen;
		global $lightweight_seo_test_user_can;

		$lightweight_seo_test_options['active_plugins'] = array( 'wordpress-seo/wp-seo.php' );

		$notices = new Lightweight_SEO_Admin_Notices( 'lightweight-seo' );

		$lightweight_seo_test_user_can = false;
		ob_start();
		$notices->maybe_render_safe_mode_notice();
		$without_capability = ob_get_clean();

		$lightweight_seo_test_user_can = true;
		$lightweight_seo_test_screen   = (object) array( 'id' => 'edit-post' );
		ob_start();
		$notices->maybe_render_safe_mode_notice();
		$unrelated_screen = ob_get_clean();

		$lightweight_seo_test_screen = (object) array( 'id' => 'plugins' );
		ob_start();
		$notices->maybe_render_safe_mode_notice();
		$plugins_screen = ob_get_clean();

		$lightweight_seo_test_screen = null;

		$this->assertSame( '', $without_capability );
		$this->assertSame( '', $unrelated_screen );
		$this->assertStringContainsString( 'safe mode is active', $plugins_screen );
	}
}
