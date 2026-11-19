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
		global $lightweight_seo_test_user_meta;
		global $wp_settings_fields;
		global $wp_settings_sections;

		$lightweight_seo_test_user_meta           = array();
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

			public function get_all( $post_id ) {
				global $lightweight_seo_test_post_meta;

				$values = array();

				foreach ( (array) ( $lightweight_seo_test_post_meta[ $post_id ] ?? array() ) as $meta_key => $value ) {
					$values[ str_replace( array( '_lightweight_seo_social_', '_lightweight_seo_' ), array( 'social_', 'seo_' ), $meta_key ) ] = $value;
				}

				return $values;
			}

			public function get_social_image_url( $post_id ) {
				return (string) ( $this->get_all( $post_id )['social_image'] ?? '' );
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

		$settings = new class() extends Lightweight_SEO_Settings {
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

	public function test_content_signals_switch_is_saved_from_the_modules_tab_only(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = ( new Lightweight_SEO_Settings() )->get_defaults();

		$on = $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings(
			array(
				'settings_tab'           => 'modules',
				'enable_content_signals' => '1',
			)
		);

		$this->assertSame( '1', $on['enable_content_signals'] );

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = $on;

		// Saving another tab keeps it; saving the Modules tab without the box clears it.
		$this->assertSame( '1', $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings( array( 'settings_tab' => 'appearance' ) )['enable_content_signals'] );
		$this->assertSame( '0', $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings( array( 'settings_tab' => 'modules' ) )['enable_content_signals'] );
	}

	public function test_validate_settings_clears_stale_social_image_id_for_manual_urls(): void {
		global $lightweight_seo_test_attachment_urls;

		$lightweight_seo_test_attachment_urls[14] = 'https://example.com/existing-image.jpg';

		$settings = new class() extends Lightweight_SEO_Settings {
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

		$settings = new class() extends Lightweight_SEO_Settings {
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

		$settings = new Lightweight_SEO_Settings();
		$page     = new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() );

		ob_start();
		$page->display_plugin_admin_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'settings-errors', $output );
		$this->assertStringContainsString( 'Essential setup', $output );
		$this->assertStringContainsString( 'Search appearance', $output );
		$this->assertStringContainsString( 'tab=tools">Tools</a>', $output );
		// The developer reference lives on the Tools tab, not in the tab bar.
		$this->assertStringNotContainsString( 'Developer API', $output );
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

	public function test_content_types_table_saves_from_its_tab_in_the_shape_it_is_stored(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = ( new Lightweight_SEO_Settings() )->get_defaults();
		$admin_settings = $this->get_admin_settings( new Lightweight_SEO_Settings() );

		// What the browser posts: a ticked "Show" box sends 0 after its hidden 1, and an unticked box sends only the hidden value.
		$first = $admin_settings->validate_settings(
			array(
				'settings_tab'             => 'indexation',
				'noindex_search_results'   => '1',
				'noindex_attachment_pages' => '0',
				'noindex_author_archives'  => '1',
				'noindex_date_archives'    => '0',
				'content_types'            => array(
					'post_types' => array(
						'post'    => array(
							'noindex'      => '0',
							'sitemap'      => '1',
							'title_format' => '%title% %sep% Journal',
						),
						'page'    => array(
							'noindex'      => '1',
							'sitemap'      => '0',
							'title_format' => '',
						),
						'retired' => array( 'noindex' => '1' ),
					),
					'taxonomies' => array(
						'category' => array(
							'noindex'      => '0',
							'sitemap'      => '1',
							'title_format' => '',
						),
						'post_tag' => array(
							'noindex'      => '1',
							'sitemap'      => '1',
							'title_format' => '',
						),
					),
				),
			)
		);
		// WordPress sanitizes a second time when it has to create the option row.
		$second = $admin_settings->validate_settings( $first );

		$this->assertSame( '1', $first['noindex_search_results'] );
		$this->assertSame( '0', $first['noindex_attachment_pages'] );
		$this->assertSame( '1', $first['noindex_author_archives'] );
		$this->assertSame( '0', $first['noindex_date_archives'] );
		$this->assertSame(
			array(
				'post_types' => array(
					'post' => array(
						'noindex'      => '0',
						'sitemap'      => '1',
						'title_format' => '%title% %sep% Journal',
					),
					'page' => array(
						'noindex'      => '1',
						'sitemap'      => '0',
						'title_format' => '',
					),
				),
				'taxonomies' => array(
					'post_tag' => array(
						'noindex'      => '1',
						'sitemap'      => '1',
						'title_format' => '',
					),
				),
			),
			$first['content_types']
		);
		$this->assertSame( $first, $second );
	}

	public function test_saving_another_tab_leaves_the_content_types_table_alone(): void {
		global $lightweight_seo_test_options;

		$existing                            = ( new Lightweight_SEO_Settings() )->get_defaults();
		$existing['noindex_author_archives'] = '1';
		$existing['noindex_date_archives']   = '1';
		$existing['content_types']           = array(
			'post_types' => array(
				'portfolio' => array(
					'noindex'      => '1',
					'sitemap'      => '0',
					'title_format' => '%title% %sep% Work',
				),
			),
		);
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = $existing;

		$validated = $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings(
			array(
				'settings_tab' => 'appearance',
				'title_format' => '%title% | %sitename%',
			)
		);

		$this->assertSame( $existing['content_types'], $validated['content_types'] );
		$this->assertSame( '1', $validated['noindex_author_archives'] );
		$this->assertSame( '1', $validated['noindex_date_archives'] );

		// Saving the table itself keeps the row of a post type that is not registered right now.
		$saved = $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings(
			array(
				'settings_tab'  => 'indexation',
				'content_types' => array( 'post_types' => array( 'post' => array( 'noindex' => '1' ) ) ),
			)
		);

		$this->assertSame( array( 'post', 'portfolio' ), array_keys( $saved['content_types']['post_types'] ) );
		$this->assertSame( '0', $saved['noindex_author_archives'], 'An unticked box on the saved tab is stored as off.' );
	}

	public function test_content_types_table_lists_every_public_content_type_with_its_stored_values(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'content_types' => array(
				'post_types' => array(
					'page' => array(
						'noindex'      => '1',
						'title_format' => '%title% &quot;page&quot;',
					),
				),
				'taxonomies' => array( 'post_tag' => array( 'sitemap' => '0' ) ),
			),
		);

		$settings = new Lightweight_SEO_Settings();
		$this->get_admin_settings( $settings )->register_settings();
		$_GET['tab'] = 'indexation';

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) )->display_plugin_admin_page();
		$output = ob_get_clean();

		$name = 'name="lightweight_seo_settings[content_types]';

		// A shown post type: the hidden input carries "hidden", and the ticked box overrides it with "shown".
		$this->assertStringContainsString( '<th scope="row">Posts</th>', $output );
		$this->assertStringContainsString( '<input type="hidden" ' . $name . '[post_types][post][noindex]" value="1"><label class="lwseo-matrix__check"><input type="checkbox" ' . $name . '[post_types][post][noindex]" value="0" checked="checked" data-lwseo-matrix-notes><span class="screen-reader-text">Posts: Show in search results</span>', $output );
		$this->assertStringContainsString( '<input type="checkbox" ' . $name . '[post_types][post][sitemap]" value="1" checked="checked">', $output );
		$this->assertStringContainsString( $name . '[post_types][post][title_format]" value="" placeholder="%title% – %sitename%" aria-label="Posts: Title format"', $output );

		// A hidden post type: the box is clear, its sitemap remark shows, and its own format is printed decoded.
		$this->assertStringContainsString( '<input type="checkbox" ' . $name . '[post_types][page][noindex]" value="0" data-lwseo-matrix-notes>', $output );
		$this->assertStringContainsString( '<span class="screen-reader-text">Pages: In sitemap</span></label><span class="lwseo-matrix__note">Shown items only</span>', $output );
		$this->assertStringContainsString( $name . '[post_types][page][title_format]" value="%title% &quot;page&quot;"', $output );

		// Taxonomies inherit the archive title format.
		$this->assertStringContainsString( '<input type="checkbox" ' . $name . '[taxonomies][post_tag][sitemap]" value="1">', $output );
		$this->assertStringContainsString( $name . '[taxonomies][category][title_format]" value="" placeholder="%title% %sep% %sitename%" aria-label="Categories: Title format"', $output );

		// Archives and search keep their own settings; internal search pages start hidden.
		$this->assertStringContainsString( '<input type="checkbox" name="lightweight_seo_settings[noindex_author_archives]" value="0" checked="checked"', $output );
		$this->assertStringContainsString( '<input type="checkbox" name="lightweight_seo_settings[noindex_date_archives]" value="0" checked="checked"', $output );
		$this->assertStringContainsString( '<input type="checkbox" name="lightweight_seo_settings[noindex_search_results]" value="0" data-lwseo-matrix-notes>', $output );

		// Pages are hidden here, but the homepage lists posts, so there is nothing to warn about yet.
		$this->assertStringNotContainsString( 'your homepage is a page', $output );

		$lightweight_seo_test_options['show_on_front'] = 'page';
		$lightweight_seo_test_options['page_on_front'] = 12;

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) )->display_plugin_admin_page();
		$with_static_front_page = ob_get_clean();

		$this->assertStringContainsString( 'Pages are hidden from search results, and your homepage is a page.', $with_static_front_page );

		// The card names the field for assistive technology, and the two moved tick boxes are gone.
		$this->assertStringContainsString( 'role="group" aria-labelledby="lwseo-label-content_types"', $output );
		$this->assertStringNotContainsString( 'Hide internal search result pages from search engines', $output );
		$this->assertStringNotContainsString( 'Hide attachment pages from search engines', $output );
		$this->assertStringContainsString( 'Previews and headers', $output );
	}

	public function test_content_types_table_offers_a_schema_type_for_each_post_type(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'content_types' => array( 'post_types' => array( 'page' => array( 'schema' => 'BlogPosting' ) ) ),
		);

		$table = $this->render_tab( 'indexation' );

		// Posts show their default, pages the stored choice, and taxonomies have no schema type.
		$this->assertMatchesRegularExpression( '#<select name="lightweight_seo_settings\[content_types\]\[post_types\]\[post\]\[schema\]" aria-label="Posts: Schema type">.*?<option value="Article" selected=\'selected\'>Article</option>#s', $table );
		$this->assertMatchesRegularExpression( '#<select name="lightweight_seo_settings\[content_types\]\[post_types\]\[page\]\[schema\]" aria-label="Pages: Schema type">.*?<option value="BlogPosting" selected=\'selected\'>Blog post</option>#s', $table );
		$this->assertStringNotContainsString( '[taxonomies][category][schema]', $table );
		$this->assertStringNotContainsString( '[post_types][attachment][schema]', $table );

		// Saving the table as it is shown stores nothing new for posts and keeps the choice for pages.
		$validated = $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings(
			array(
				'settings_tab'  => 'indexation',
				'content_types' => array(
					'post_types' => array(
						'post' => array(
							'noindex' => '0',
							'sitemap' => '1',
							'schema'  => 'Article',
						),
						'page' => array(
							'noindex' => '0',
							'sitemap' => '1',
							'schema'  => 'BlogPosting',
						),
					),
				),
			)
		);

		$this->assertArrayNotHasKey( 'post', $validated['content_types']['post_types'] );
		$this->assertSame( 'BlogPosting', $validated['content_types']['post_types']['page']['schema'] );
	}

	public function test_each_title_format_shows_what_it_becomes_for_a_page_of_this_site(): void {
		global $lightweight_seo_test_authors;
		global $lightweight_seo_test_get_posts_calls;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['title']                   = 'Harbour <em>at dawn</em>';
		$lightweight_seo_test_authors                                = array( 17 => array( 'display_name' => 'Ada Lovelace' ) );
		$lightweight_seo_test_posts                                  = array(
			42 => (object) array(
				'ID'          => 42,
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_author' => 17,
			),
		);
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'title_separator'      => '|',
			'title_format'         => '%title% %sep% %page% %sep% %sitename%',
			'archive_title_format' => '',
			'search_title_format'  => 'Results for "%search%" %sep% %year%',
		);

		$queries = (int) $lightweight_seo_test_get_posts_calls;
		$output  = $this->render_tab( 'appearance' );

		// One lookup of the newest post serves all four previews.
		$this->assertSame( $queries + 1, (int) $lightweight_seo_test_get_posts_calls );

		preg_match_all( '#<p class="lwseo-format-preview" data-for="([a-z_]+)" data-fallback="([^"]*)" data-tokens="([^"]*)">.*?<span class="lwseo-format-preview__text">(.*?)</span>#s', $output, $matches, PREG_SET_ORDER );

		$previews = array();

		foreach ( $matches as $match ) {
			$previews[ $match[1] ] = html_entity_decode( $match[4], ENT_QUOTES );
		}

		$this->assertSame(
			array(
				// The page variable has no value on a first page, and leaves with one separator.
				'lightweight_seo_title_format'         => 'Harbour at dawn | Test Site',
				'lightweight_seo_home_title_format'    => 'Test Site | Test Tagline',
				// An empty format is previewed as the default it falls back to.
				'lightweight_seo_archive_title_format' => 'A category | Test Site',
				'lightweight_seo_search_title_format'  => 'Results for "coffee" | ' . gmdate( 'Y' ),
			),
			$previews
		);

		// The script gets the same values, and the format the field falls back to.
		$tokens = json_decode( html_entity_decode( $matches[0][3], ENT_QUOTES ), true );

		$this->assertSame( 'Harbour at dawn', $tokens['%title%'] );
		$this->assertSame( '|', $tokens['%sep%'] );
		$this->assertSame( 'Ada Lovelace', $tokens['%author%'] );
		$this->assertSame( '', $tokens['%page%'] );
		$this->assertSame( '%title% – %sitename%', html_entity_decode( $matches[0][2], ENT_QUOTES ) );
	}

	public function test_content_types_table_previews_the_title_format_of_each_row(): void {
		global $lightweight_seo_test_get_posts_calls;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['title']                   = 'Harbour at dawn';
		$lightweight_seo_test_posts                                  = array(
			42 => (object) array(
				'ID'          => 42,
				'post_type'   => 'post',
				'post_status' => 'publish',
			),
		);
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'content_types' => array(
				'post_types' => array( 'page' => array( 'title_format' => '%title% %sep% Pages %sep% %page%' ) ),
			),
		);

		$queries = (int) $lightweight_seo_test_get_posts_calls;
		$table   = $this->render_tab( 'indexation' );

		// One lookup of the newest post serves every row.
		$this->assertSame( $queries + 1, (int) $lightweight_seo_test_get_posts_calls );

		preg_match_all( '#<input type="text" id="([a-z0-9-]+)" name="lightweight_seo_settings\[content_types\]\[(post_types|taxonomies)\]\[([a-z_]+)\]\[title_format\]".*?<p class="lwseo-format-preview" data-for="([a-z0-9-]+)" data-fallback="([^"]*)".*?<span class="lwseo-format-preview__text">(.*?)</span>#s', $table, $matches, PREG_SET_ORDER );

		$previews = array();

		foreach ( $matches as $match ) {
			// Each preview names the field right above it.
			$this->assertSame( $match[1], $match[4] );

			$previews[ $match[3] ] = html_entity_decode( $match[6], ENT_QUOTES );
		}

		// An empty format is previewed as the site-wide format it falls back to.
		$this->assertSame( 'Harbour at dawn – Test Site', $previews['post'] );
		$this->assertSame( 'Harbour at dawn – Pages', $previews['page'] );
		// A taxonomy row is previewed as an archive, with a category in place of the title.
		$this->assertSame( 'A category – Test Site', $previews['category'] );
		$this->assertCount( count( $previews ), array_unique( array_column( $matches, 1 ) ) );
	}

	public function test_identity_and_separator_fields_are_validated_and_kept_across_tabs(): void {
		global $lightweight_seo_test_attachment_urls;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_settings_errors;

		$lightweight_seo_test_attachment_urls[31]                    = 'https://example.com/uploads/logo.png';
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = ( new Lightweight_SEO_Settings() )->get_defaults();

		$saved = $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings(
			array(
				'settings_tab'         => 'identity',
				'identity_type'        => 'person',
				'identity_name'        => '  <b>Ada</b> Lovelace ',
				'identity_description' => " <script>x</script>Writes about\nthe Analytical Engine. ",
				'identity_logo'        => 'https://example.com/uploads/logo.png',
				'identity_logo_id'     => '31',
				'x_handle'             => 'https://x.com/@Ada_L',
			)
		);

		$this->assertSame( 'person', $saved['identity_type'] );
		$this->assertSame( 'Ada Lovelace', $saved['identity_name'] );
		$this->assertSame( "xWrites about\nthe Analytical Engine.", $saved['identity_description'] );
		$this->assertSame( 'https://example.com/uploads/logo.png', $saved['identity_logo'] );
		$this->assertSame( 31, $saved['identity_logo_id'] );
		$this->assertSame( 'Ada_L', $saved['x_handle'] );
		$this->assertSame( LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR, $saved['title_separator'] );
		$this->assertSame( array(), $lightweight_seo_test_settings_errors );

		// WordPress sanitizes a second time when it has to create the option row.
		$this->assertSame( $saved, $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings( $saved ) );

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = $saved;

		// Saving another tab changes the separator and leaves the identity alone.
		$appearance = $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings(
			array(
				'settings_tab'    => 'appearance',
				'title_separator' => '|',
			)
		);

		$this->assertSame( '|', $appearance['title_separator'] );
		$this->assertSame( 'person', $appearance['identity_type'] );
		$this->assertSame( 'Ada Lovelace', $appearance['identity_name'] );
		$this->assertSame( $saved['identity_description'], $appearance['identity_description'] );
		$this->assertSame( 31, $appearance['identity_logo_id'] );
		$this->assertSame( 'Ada_L', $appearance['x_handle'] );

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = $appearance;

		// Values outside what is supported keep what is stored, and a bad handle says so.
		$rejected = $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings(
			array(
				'settings_tab'    => 'all',
				'title_separator' => '<script>',
				'identity_type'   => 'robot',
				'x_handle'        => 'not a handle',
			)
		);

		$this->assertSame( '|', $rejected['title_separator'] );
		$this->assertSame( 'organization', $rejected['identity_type'] );
		$this->assertSame( 'Ada_L', $rejected['x_handle'] );
		$this->assertSame( 'invalid_x_handle', $lightweight_seo_test_settings_errors[0]['code'] ?? '' );

		// Clearing the fields is a valid save.
		$cleared = $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings(
			array(
				'settings_tab'         => 'identity',
				'identity_type'        => 'organization',
				'identity_name'        => '',
				'identity_description' => '',
				'identity_logo'        => '',
				'identity_logo_id'     => '31',
				'x_handle'             => '',
			)
		);

		$this->assertSame( '', $cleared['identity_name'] );
		$this->assertSame( '', $cleared['identity_description'] );
		$this->assertSame( '', $cleared['identity_logo'] );
		$this->assertSame( 0, $cleared['identity_logo_id'] );
		$this->assertSame( '', $cleared['x_handle'] );
	}

	public function test_identity_and_separator_fields_render_with_their_stored_values(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'title_separator'      => '|',
			'identity_type'        => 'person',
			'identity_name'        => 'Ada "Countess" Lovelace',
			'identity_description' => 'Writes <about> the "Engine".',
			'identity_logo'        => 'https://example.com/uploads/ada.jpg',
			'x_handle'             => 'ada_l',
		);

		$settings = new Lightweight_SEO_Settings();
		$this->get_admin_settings( $settings )->register_settings();

		$_GET['tab'] = 'identity';

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) )->display_plugin_admin_page();
		$identity = ob_get_clean();

		$_GET['tab'] = 'appearance';

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) )->display_plugin_admin_page();
		$appearance = ob_get_clean();

		// Site identity comes first and holds every identity field; structured data keeps its switch.
		$this->assertLessThan( strpos( $identity, 'Structured data' ), strpos( $identity, 'Site identity' ) );
		$this->assertStringContainsString( '<label class="lwseo-field__label" for="lightweight_seo_identity_type">This site represents</label>', $identity );
		$this->assertMatchesRegularExpression( '/<option value="person"\s+selected=\'selected\'>A person<\/option>/', $identity );
		$this->assertStringContainsString( 'name="lightweight_seo_settings[identity_name]" value="Ada &quot;Countess&quot; Lovelace" placeholder="Test Site"', $identity );
		$this->assertStringContainsString( '<label class="lwseo-field__label" for="lightweight_seo_identity_description">Description</label>', $identity );
		$this->assertStringContainsString( 'name="lightweight_seo_settings[identity_description]" rows="2" class="large-text">Writes &lt;about&gt; the &quot;Engine&quot;.</textarea>', $identity );
		$this->assertLessThan( strpos( $identity, 'lightweight_seo_identity_logo"' ), strpos( $identity, 'lightweight_seo_identity_description"' ) );
		$this->assertStringContainsString( 'id="lightweight_seo_identity_logo" name="lightweight_seo_settings[identity_logo]" value="https://example.com/uploads/ada.jpg"', $identity );
		$this->assertStringContainsString( 'name="lightweight_seo_settings[identity_logo_id]"', $identity );
		$this->assertStringContainsString( 'name="lightweight_seo_settings[x_handle]" value="@ada_l"', $identity );
		$this->assertStringContainsString( '<label class="lwseo-field__label" for="lightweight_seo_organization_same_as">Profiles</label>', $identity );
		$this->assertStringContainsString( 'lightweight_seo_settings[enable_schema_output]', $identity );
		$this->assertStringNotContainsString( 'used as the organization logo when available', $identity );

		// The separator leads the title formats, lists every supported character, and marks the stored one.
		$this->assertLessThan( strpos( $appearance, 'for="lightweight_seo_title_format"' ), strpos( $appearance, 'for="lightweight_seo_title_separator"' ) );
		$this->assertSame( count( Lightweight_SEO_Settings::TITLE_SEPARATORS ), substr_count( $appearance, '<option value=' ) );
		$this->assertMatchesRegularExpression( '/<option value="\|"\s+selected=\'selected\'>\|  Vertical bar<\/option>/', $appearance );
		$this->assertMatchesRegularExpression( '/<option value="–" >–  En dash<\/option>/', $appearance );
	}

	public function test_verification_codes_are_extracted_validated_and_kept_across_tabs(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_settings_errors;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = ( new Lightweight_SEO_Settings() )->get_defaults();

		$saved = $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings(
			array(
				'settings_tab'           => 'identity',
				'verification_google'    => '<meta name="google-site-verification" content="google-code_1" />',
				'verification_bing'      => ' 0123456789ABCDEF ',
				'verification_pinterest' => '',
				'verification_yandex'    => 'f00ba4',
			)
		);

		$this->assertSame( 'google-code_1', $saved['verification_google'] );
		$this->assertSame( '0123456789ABCDEF', $saved['verification_bing'] );
		$this->assertSame( '', $saved['verification_pinterest'] );
		$this->assertSame( 'f00ba4', $saved['verification_yandex'] );
		$this->assertSame( array(), $lightweight_seo_test_settings_errors );
		$this->assertSame( $saved, $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings( $saved ) );

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = $saved;

		// Another tab does not submit the codes, so they stay; a value that is not a code is refused.
		$other = $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings(
			array(
				'settings_tab'        => 'all',
				'title_format'        => '%title% %sep% %year%',
				'verification_yandex' => '"><script>alert(1)</script>',
			)
		);

		$this->assertSame( 'google-code_1', $other['verification_google'] );
		$this->assertSame( 'f00ba4', $other['verification_yandex'] );
		$this->assertSame( '%title% %sep% %year%', $other['title_format'] );
		$this->assertSame( 'invalid_verification_yandex', $lightweight_seo_test_settings_errors[0]['code'] ?? '' );
		$this->assertStringStartsWith( 'The Yandex Webmaster verification code was not saved.', $lightweight_seo_test_settings_errors[0]['message'] ?? '' );
	}

	public function test_verification_fields_and_the_variable_guide_render(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'verification_google' => 'google-code_1' );

		$settings = new Lightweight_SEO_Settings();
		$this->get_admin_settings( $settings )->register_settings();

		$_GET['tab'] = 'identity';

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) )->display_plugin_admin_page();
		$identity = ob_get_clean();

		$_GET['tab'] = 'appearance';

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) )->display_plugin_admin_page();
		$appearance = ob_get_clean();

		$_GET['tab'] = 'indexation';

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) )->display_plugin_admin_page();
		$indexation = ob_get_clean();

		// The verification card sits between the identity and the structured data cards.
		$this->assertLessThan( strpos( $identity, 'Site verification' ), strpos( $identity, 'Site identity' ) );
		$this->assertLessThan( strpos( $identity, 'Structured data' ), strpos( $identity, 'Site verification' ) );
		$this->assertStringContainsString( '<label class="lwseo-field__label" for="lightweight_seo_verification_google">Google Search Console</label>', $identity );
		$this->assertStringContainsString( 'id="lightweight_seo_verification_google" name="lightweight_seo_settings[verification_google]" value="google-code_1"', $identity );
		$this->assertStringContainsString( 'name="lightweight_seo_settings[verification_bing]" value=""', $identity );
		$this->assertStringContainsString( 'for="lightweight_seo_verification_pinterest">Pinterest</label>', $identity );
		$this->assertStringContainsString( 'for="lightweight_seo_verification_yandex">Yandex Webmaster</label>', $identity );

		// Every variable has a button and a line that says what it becomes.
		foreach ( array( '%title%', '%sitename%', '%tagline%', '%sep%', '%category%', '%author%', '%year%', '%page%' ) as $variable ) {
			$this->assertStringContainsString( 'data-token="' . $variable . '"', $appearance );
			$this->assertStringContainsString( '<th scope="row"><code>' . $variable . '</code></th>', $appearance );
			$this->assertStringContainsString( 'data-token="' . $variable . '"', $indexation );
		}

		$this->assertStringContainsString( '<summary>What the variables mean</summary>', $appearance );
	}

	public function test_inspect_card_opens_the_tools_tab_and_never_submits_its_address(): void {
		$output = $this->render_tab( 'tools' );

		// It is the first card, and it tells the script how to ask a page for its report.
		$this->assertLessThan( strpos( $output, 'Import from another SEO plugin' ), strpos( $output, 'Inspect a page' ) );
		$this->assertStringContainsString( '<label class="lwseo-field__label" for="lightweight_seo_inspect_page">Address</label>', $output );
		$this->assertStringContainsString( 'id="lwseo-inspect"', $output );
		$this->assertStringContainsString( 'data-arg="lightweight_seo_inspect"', $output );
		$this->assertStringContainsString( 'data-nonce="nonce-lightweight_seo_inspect"', $output );
		$this->assertStringContainsString( 'data-home-host="example.com"', $output );
		// The address is a tool input, not a setting: it has no name, so saving the tab cannot store it.
		$this->assertStringContainsString( '<input type="url" id="lightweight_seo_inspect_page" value="" placeholder="https://example.com/"', $output );
		$this->assertStringNotContainsString( '[inspect_page]', $output );
		$this->assertStringContainsString( '<button type="button" class="button lwseo-inspect__run">Inspect</button>', $output );
		$this->assertStringContainsString( '<div class="lwseo-inspect__report" aria-live="polite"></div>', $output );
		// With no address yet there is no report to link to.
		$this->assertStringContainsString( '<p class="lwseo-inspect__fallback" hidden>', $output );
	}

	public function test_inspect_card_starts_from_an_address_on_this_site_only(): void {
		$settings = new Lightweight_SEO_Settings();
		$this->get_admin_settings( $settings )->register_settings();

		$render = function ( $address ) use ( $settings ) {
			$_GET = array(
				'tab'     => 'tools',
				'inspect' => $address,
			);

			ob_start();
			( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) )->display_plugin_admin_page();

			return (string) ob_get_clean();
		};

		$own     = $render( 'https://example.com/a-post/?paged=2' );
		$foreign = $render( 'https://other.example/a-post/' );
		$script  = $render( 'javascript:alert(1)' );

		$this->assertStringContainsString( 'id="lightweight_seo_inspect_page" value="https://example.com/a-post/?paged=2"', $own );
		// Without the script, the same report opens as a page of its own.
		$this->assertStringContainsString( '<p class="lwseo-inspect__fallback">', $own );
		$this->assertStringContainsString( 'href="https://example.com/a-post/?paged=2&amp;lightweight_seo_inspect=1&amp;_wpnonce=nonce-lightweight_seo_inspect"', $own );

		foreach ( array( $foreign, $script ) as $output ) {
			$this->assertStringContainsString( 'id="lightweight_seo_inspect_page" value=""', $output );
			$this->assertStringContainsString( '<p class="lwseo-inspect__fallback" hidden>', $output );
			$this->assertStringNotContainsString( 'other.example', $output );
			$this->assertStringNotContainsString( 'alert(1)', $output );
		}
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
				'lightweight_seo_general_section'      => array( 'title_separator', 'title_format', 'home_title_format', 'archive_title_format', 'search_title_format', 'meta_description', 'social_image' ),
				'lightweight_seo_indexation_section'   => array( 'content_types', 'default_max_image_preview', 'enable_media_x_robots_headers' ),
				'lightweight_seo_sitemap_section'      => array( 'exclude_noindex_from_sitemaps', 'exclude_redirected_from_sitemaps' ),
				'lightweight_seo_schema_section'       => array( 'identity_type', 'identity_name', 'identity_description', 'identity_logo', 'x_handle', 'organization_same_as', 'enable_schema_output', 'enable_local_business_schema', 'local_business_details', 'enable_hreflang_output', 'hreflang_mappings', 'enable_hreflang_path_mirroring' ),
				'lightweight_seo_verification_section' => array( 'verification_google', 'verification_bing', 'verification_pinterest', 'verification_yandex' ),
				'lightweight_seo_redirects_section'    => array( 'enable_redirects', 'enable_auto_redirects', 'redirect_rules', 'redirect_test', 'generated_redirect_rules', 'redirect_export', 'redirect_import', 'redirect_health' ),
				'lightweight_seo_inspect_section'      => array( 'inspect_page' ),
				'lightweight_seo_migration_section'    => array( 'settings_export', 'settings_import', 'import_source', 'import_actions', 'import_report', 'legacy_data_transition', 'delete_data_on_uninstall' ),
				'lightweight_seo_tracking_section'     => array( 'enable_tracking', 'gtm_container_id', 'ga4_measurement_id', 'facebook_pixel_id', 'tracking_excluded_roles', 'tracking_excluded_environments' ),
				'lightweight_seo_indexnow_section'     => array( 'enable_indexnow', 'indexnow_status' ),
				'lightweight_seo_ai_discovery_section' => array( 'enable_ai_discovery', 'ai_search_crawlers_enabled', 'ai_training_crawlers_enabled', 'enable_content_signals', 'enable_llms_txt', 'llms_txt_post_ids', 'ai_readiness' ),
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
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) )->display_plugin_admin_page();
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

	public function test_tools_tab_offers_the_settings_file_and_the_undo_only_after_an_import(): void {
		global $lightweight_seo_test_options;

		$tools = $this->render_tab( 'tools' );

		$this->assertStringContainsString( 'admin-post.php?action=lightweight_seo_settings_export', $tools );
		$this->assertStringContainsString( 'name="lightweight_seo_settings_file" accept=".json,application/json" form="lightweight-seo-settings-import-form"', $tools );
		$this->assertStringContainsString( 'name="confirm_settings_import" value="1" required form="lightweight-seo-settings-import-form"', $tools );
		$this->assertStringContainsString( '<form id="lightweight-seo-settings-import-form" method="post" enctype="multipart/form-data"', $tools );
		$this->assertStringNotContainsString( 'Undo last import', $tools );

		$lightweight_seo_test_options['lightweight_seo_settings_backup'] = array(
			'settings' => array(),
			'modules'  => array(),
			'saved_at' => 1794900000,
		);

		$this->assertStringContainsString( 'form="lightweight-seo-settings-undo-form">Undo last import</button>', $this->render_tab( 'tools' ) );
	}

	public function test_other_tabs_do_not_render_tool_forms(): void {
		$settings = new Lightweight_SEO_Settings();
		$this->get_admin_settings( $settings )->register_settings();
		$_GET['tab'] = 'appearance';

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) )->display_plugin_admin_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'lightweight_seo_settings[title_format]', $output );
		$this->assertStringNotContainsString( 'admin-post.php', $output );
	}

	public function test_every_module_has_its_own_switch_that_only_the_modules_tab_changes(): void {
		global $lightweight_seo_test_options;

		$modules = $this->render_tab( 'modules' );

		foreach ( array( 'enable_local_business_schema', 'enable_hreflang_output', 'enable_redirects', 'enable_tracking', 'enable_ai_discovery', 'enable_indexnow' ) as $switch ) {
			$this->assertStringContainsString( 'type="checkbox" name="lightweight_seo_settings[' . $switch . ']"', $modules );
		}

		$this->assertSame( 6, substr_count( $modules, '>Enable module<' ) );

		$existing                     = ( new Lightweight_SEO_Settings() )->get_defaults();
		$existing['enable_redirects'] = '1';
		$existing['enable_tracking']  = '1';
		$existing['redirect_rules']   = '/old /new 301';
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = $existing;

		$admin_settings = $this->get_admin_settings( new Lightweight_SEO_Settings() );

		// Another tab does not carry the switches, so they stay as stored.
		$other_tab = $admin_settings->validate_settings( array( 'settings_tab' => 'appearance' ) );

		$this->assertSame( '1', $other_tab['enable_redirects'] );
		$this->assertSame( '1', $other_tab['enable_tracking'] );

		// The modules tab with both boxes cleared turns the modules off and keeps what was configured.
		$switched_off = $admin_settings->validate_settings(
			array(
				'settings_tab'   => 'modules',
				'redirect_rules' => '/old /new 301',
			)
		);

		$this->assertSame( '0', $switched_off['enable_redirects'] );
		$this->assertSame( '0', $switched_off['enable_tracking'] );
		$this->assertSame( '/old /new 301', $switched_off['redirect_rules'] );
		$this->assertFalse( Lightweight_SEO_Module_State::derive_from_legacy_settings( $switched_off )['redirects'] );
	}

	public function test_redirects_card_tests_an_address_against_the_saved_rules_without_javascript(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'enable_redirects' => '1',
			'redirect_rules'   => "/old-blog/* /blog/* 301\n/blog/2019/* /archive 302\n/retired 410\n/loop-a /loop-b 301",
		);

		// With the module off there is no test box, no import control, and neither of their forms.
		$off = $this->render_tab( 'modules' );

		foreach ( array( 'lightweight_seo_test_path', 'lightweight_seo_redirect_csv', 'lightweight-seo-redirect-test-form', 'lightweight-seo-redirect-import-form' ) as $absent ) {
			$this->assertStringNotContainsString( $absent, $off );
		}

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ] = array( 'redirects' => true );

		$on = $this->render_tab( 'modules' );

		// Both controls sit in the card and submit through forms printed outside the settings form.
		$this->assertStringContainsString( 'name="lightweight_seo_test_path" value="" placeholder="/old-path" class="regular-text" autocomplete="off" form="lightweight-seo-redirect-test-form"', $on );
		$this->assertStringContainsString( 'name="lightweight_seo_redirect_csv" accept=".csv,text/csv" form="lightweight-seo-redirect-import-form"', $on );
		$this->assertStringContainsString( '<form id="lightweight-seo-redirect-test-form" method="get"', $on );
		$this->assertStringContainsString( '<form id="lightweight-seo-redirect-import-form" method="post" enctype="multipart/form-data"', $on );
		$this->assertGreaterThan( strpos( $on, '</form>' ), strpos( $on, '<form id="lightweight-seo-redirect-test-form"' ) );
		$this->assertStringContainsString( '<option value="410">410 Gone</option>', $on );
		$this->assertStringNotContainsString( 'lwseo-status-list" role="status"', $on );

		$cases = array(
			'https://example.com/old-blog/2019/first-post/?utm=x' => array( '/old-blog/2019/first-post/ redirects to /archive', 'Rule /old-blog/*: to /blog/2019/first-post (301)', 'Rule /blog/2019/*: to /archive (302)', 'Visitors are sent straight to the last address.' ),
			'/retired'                  => array( '/retired answers &quot;410 Gone&quot;', 'Rule /retired: gone (410)' ),
			'/nothing-here'             => array( 'No rule applies to /nothing-here' ),
			'<script>alert(1)</script>' => array( 'No rule applies to /alert(1)' ),
		);

		foreach ( $cases as $address => $expected ) {
			$this->get_admin_settings( new Lightweight_SEO_Settings() )->register_settings();
			$_GET = array(
				'tab'                       => 'modules',
				'lightweight_seo_test_path' => $address,
			);

			ob_start();
			( new Lightweight_SEO_Admin_Page( 'lightweight-seo', new Lightweight_SEO_Settings(), $this->get_tools_fields( new Lightweight_SEO_Settings() ), $this->get_post_meta() ) )->display_plugin_admin_page();
			$output = ob_get_clean();

			foreach ( $expected as $text ) {
				$this->assertStringContainsString( $text, $output, $address );
			}

			$this->assertStringNotContainsString( '<script>alert', $output );
		}

		$_GET = array();
	}

	public function test_indexnow_key_is_created_once_when_the_module_is_first_switched_on(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = ( new Lightweight_SEO_Settings() )->get_defaults();

		$admin_settings = $this->get_admin_settings( new Lightweight_SEO_Settings() );

		// Off: no key is made.
		$off = $admin_settings->validate_settings( array( 'settings_tab' => 'modules' ) );

		$this->assertSame( '0', $off['enable_indexnow'] );
		$this->assertSame( '', $off['indexnow_key'] );

		$first = $admin_settings->validate_settings(
			array(
				'settings_tab'    => 'modules',
				'enable_indexnow' => '1',
			)
		);

		$this->assertSame( '1', $first['enable_indexnow'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $first['indexnow_key'] );
		// WordPress validates a second time when it creates the option row; the key must not change.
		$this->assertSame( $first, $admin_settings->validate_settings( $first ) );

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = $first;

		$admin_settings = $this->get_admin_settings( new Lightweight_SEO_Settings() );

		// Once stored, the key survives every later save, a forged field, and switching the module off.
		$later = $admin_settings->validate_settings(
			array(
				'settings_tab' => 'modules',
				'indexnow_key' => str_repeat( 'a', 32 ),
			)
		);

		$this->assertSame( '0', $later['enable_indexnow'] );
		$this->assertSame( $first['indexnow_key'], $later['indexnow_key'] );
		$this->assertSame( $first['indexnow_key'], $admin_settings->validate_settings( array( 'settings_tab' => 'appearance' ) )['indexnow_key'] );
		$this->assertTrue( Lightweight_SEO_Module_State::derive_from_legacy_settings( $first )['indexnow'] );
	}

	public function test_indexnow_card_names_the_outside_service_and_shows_its_state_only_when_on(): void {
		global $lightweight_seo_test_environment_type;
		global $lightweight_seo_test_options;

		$off = $this->render_tab( 'modules' );

		$this->assertStringContainsString( 'api.indexnow.org', $off );
		$this->assertStringContainsString( 'lightweight_seo_settings[enable_indexnow]', $off );
		$this->assertStringNotContainsString( 'lightweight-seo-indexnow-test-form', $off );
		$this->assertStringNotContainsString( 'lightweight_seo_settings[indexnow_key]', $off );

		$lightweight_seo_test_environment_type                               = 'staging';
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ] = array( 'indexnow' => true );
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]         = array(
			'enable_indexnow' => '1',
			'indexnow_key'    => '0123456789abcdef0123456789abcdef',
		);

		$on = $this->render_tab( 'modules' );

		$lightweight_seo_test_environment_type = 'production';

		$this->assertStringContainsString( 'Nothing is sent from this site', $on );
		$this->assertStringContainsString( 'This is a staging site, not a production site.', $on );
		$this->assertStringContainsString( '/0123456789abcdef0123456789abcdef.txt', $on );
		$this->assertStringContainsString( 'Nothing has been sent yet', $on );
		$this->assertStringContainsString( 'form="lightweight-seo-indexnow-test-form">Send a test</button>', $on );
		$this->assertStringContainsString( '<form id="lightweight-seo-indexnow-test-form" method="post"', $on );
		// The key is shown as an address, never as a field that could be posted back.
		$this->assertStringNotContainsString( 'lightweight_seo_settings[indexnow_key]', $on );
	}

	public function test_module_cards_collapse_until_the_module_is_enabled(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ] = array( 'redirects' => true );

		$settings = new Lightweight_SEO_Settings();
		$this->get_admin_settings( $settings )->register_settings();
		$_GET['tab'] = 'modules';

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) )->display_plugin_admin_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<details class="lwseo-card" id="lwseo-module-redirects" open>', $output );

		foreach ( array( 'local-seo', 'hreflang', 'tracking', 'ai', 'indexnow' ) as $module ) {
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
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) )->display_plugin_admin_page();
		$appearance = ob_get_clean();

		$_GET['tab'] = 'indexation';

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) )->display_plugin_admin_page();
		$indexation = ob_get_clean();

		$this->assertStringContainsString( '<label class="lwseo-field__label" for="lightweight_seo_title_format">', $appearance );
		$this->assertStringContainsString( 'id="lightweight_seo_title_format"', $appearance );
		$this->assertStringContainsString( 'role="group" aria-labelledby="lwseo-label-enable_media_x_robots_headers"', $indexation );
		$this->assertStringContainsString( 'id="lwseo-label-enable_media_x_robots_headers"', $indexation );
	}

	public function test_saving_is_confirmed_unless_the_plugin_already_reported_something(): void {
		global $lightweight_seo_test_settings_errors;

		$settings = new Lightweight_SEO_Settings();
		$page     = new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() );

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
			'admin_post_lightweight_seo_setup_checklist' => array( array( 'Lightweight_SEO_Admin_Page', 'handle_setup_checklist' ) ),
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

	private function render_tab( $tab, $settings = null ) {
		$settings = $settings ? $settings : new Lightweight_SEO_Settings();

		$this->get_admin_settings( $settings )->register_settings();
		$_GET = array( 'tab' => $tab );

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) )->display_plugin_admin_page();

		return ob_get_clean();
	}

	public function test_tracking_exclusions_are_tick_boxes_that_keep_the_stored_format(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'tracking_excluded_roles'        => "administrator\neditor",
			'tracking_excluded_environments' => "local\nstaging",
		);

		$output = $this->render_tab( 'modules' );

		$this->assertStringContainsString( 'name="lightweight_seo_settings[tracking_excluded_roles][]" value="editor" checked="checked">', $output );
		$this->assertStringContainsString( 'name="lightweight_seo_settings[tracking_excluded_roles][]" value="subscriber">', $output );
		$this->assertStringContainsString( 'name="lightweight_seo_settings[tracking_excluded_environments][]" value="staging" checked="checked">', $output );
		$this->assertStringContainsString( 'name="lightweight_seo_settings[tracking_excluded_environments][]" value="production">', $output );
		// Several boxes form a named group instead of pretending to be one labelled control.
		$this->assertStringContainsString( 'role="group" aria-labelledby="lwseo-label-tracking_excluded_roles"', $output );

		$admin_settings = $this->get_admin_settings( new Lightweight_SEO_Settings() );
		$validated      = $admin_settings->validate_settings(
			array(
				'settings_tab'                   => 'modules',
				'tracking_excluded_roles'        => array( '', 'editor', 'subscriber' ),
				'tracking_excluded_environments' => array( '', 'production', 'unknown' ),
			)
		);

		$this->assertSame( "editor\nsubscriber", $validated['tracking_excluded_roles'] );
		$this->assertSame( 'production', $validated['tracking_excluded_environments'] );

		// The empty first entry is all that arrives when every box is cleared, and it clears the list.
		$cleared = $admin_settings->validate_settings(
			array(
				'settings_tab'                   => 'modules',
				'tracking_excluded_roles'        => array( '' ),
				'tracking_excluded_environments' => array( '' ),
			)
		);

		$this->assertSame( '', $cleared['tracking_excluded_roles'] );
		$this->assertSame( '', $cleared['tracking_excluded_environments'] );

		// Saving another tab leaves both lists alone.
		$other_tab = $admin_settings->validate_settings( array( 'settings_tab' => 'appearance' ) );

		$this->assertSame( "administrator\neditor", $other_tab['tracking_excluded_roles'] );
		$this->assertSame( "local\nstaging", $other_tab['tracking_excluded_environments'] );
	}

	public function test_business_image_uses_the_media_picker_without_an_attachment_id(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'local_business_image' => 'https://example.com/shop.jpg' );

		$output = $this->render_tab( 'modules' );

		$this->assertStringContainsString( '<label class="lwseo-subfield__label" for="lightweight_seo_local_business_image">', $output );
		$this->assertStringContainsString( 'id="lightweight_seo_local_business_image" name="lightweight_seo_settings[local_business_image]" value="https://example.com/shop.jpg" class="lwseo-image__url">', $output );
		$this->assertStringContainsString( 'lwseo-image__choose', $output );
		$this->assertStringNotContainsString( 'lwseo-image__id', $output );
	}

	public function test_a_module_that_is_off_shows_only_what_turns_it_on(): void {
		global $lightweight_seo_test_options;

		$off = $this->render_tab( 'modules' );

		$this->assertStringContainsString( 'lightweight_seo_settings[redirect_rules]', $off );
		$this->assertStringContainsString( 'lightweight_seo_settings[enable_ai_discovery]', $off );

		foreach ( array( 'lwseo-label-generated_redirect_rules', 'lwseo-label-redirect_export', 'lwseo-label-redirect_health', 'lwseo-label-ai_readiness', 'The Redirects module is disabled.', 'Enable and save the module to run readiness checks.' ) as $absent ) {
			$this->assertStringNotContainsString( $absent, $off );
		}

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ] = array( 'redirects' => true );

		$on = $this->render_tab( 'modules' );

		foreach ( array( 'lwseo-label-generated_redirect_rules', 'lwseo-label-redirect_export', 'lwseo-label-redirect_health' ) as $present ) {
			$this->assertStringContainsString( $present, $on );
		}
	}

	public function test_legacy_path_mirroring_is_offered_only_to_sites_that_still_use_it(): void {
		global $lightweight_seo_test_options;

		$this->assertStringNotContainsString( 'enable_hreflang_path_mirroring', $this->render_tab( 'modules' ) );

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'hreflang_mappings' => "post:12 en-GB https://example.co.uk/page/\nde-DE https://example.de" );

		$this->assertStringContainsString( 'lightweight_seo_settings[enable_hreflang_path_mirroring]', $this->render_tab( 'modules' ) );

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'enable_hreflang_path_mirroring' => '1' );

		$this->assertStringContainsString( 'lightweight_seo_settings[enable_hreflang_path_mirroring]', $this->render_tab( 'modules' ) );
	}

	public function test_tools_tab_hides_legacy_data_until_there_is_some_and_holds_the_developer_reference(): void {
		global $lightweight_seo_test_options;

		$clean = $this->render_tab( 'tools' );

		$this->assertStringNotContainsString( 'Legacy data', $clean );
		$this->assertStringNotContainsString( 'No legacy', $clean );
		$this->assertStringContainsString( 'lightweight_seo_settings[delete_data_on_uninstall]', $clean );
		$this->assertStringContainsString( '<details class="lwseo-card" id="lwseo-developer-api">', $clean );
		$this->assertStringContainsString( 'lightweight_seo_get_api()', $clean );
		// The reference sits inside the form, above the save bar, and adds no field to it.
		$this->assertLessThan( strpos( $clean, 'lwseo-actions' ), strpos( $clean, 'lwseo-developer-api' ) );

		$lightweight_seo_test_options['lightweight_seo_404_logs'] = array( '/missing' );

		$this->assertStringContainsString( 'Legacy data', $this->render_tab( 'tools' ) );

		// A link to the old Developer API tab lands on Tools with the card open.
		$alias = $this->render_tab( 'developer' );

		$this->assertStringContainsString( '<details class="lwseo-card" id="lwseo-developer-api" open>', $alias );
		$this->assertMatchesRegularExpression( '/class="lwseo-tabs__tab is-active" aria-current="page" href="[^"]*tab=tools"/', $alias );
	}

	public function test_structured_editors_sit_beside_the_fields_that_are_still_saved(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_posts[12]            = (object) array(
			'ID'            => 12,
			'post_status'   => 'publish',
			'post_password' => '',
		);
		$lightweight_seo_test_posts[13]            = (object) array(
			'ID'            => 13,
			'post_status'   => 'draft',
			'post_password' => '',
		);
		$previous_title                            = $lightweight_seo_test_query_state['title'];
		$lightweight_seo_test_query_state['title'] = 'A "quoted" <b>title</b>';

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ] = array( 'redirects' => true );
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]         = array(
			'redirect_rules'               => "/old /new 301\n/moved /here 302",
			'hreflang_mappings'            => "post:12 en-GB https://example.co.uk/page/\nfr-FR https://example.fr",
			'local_business_opening_hours' => "Mo-Fr 09:00-17:00\nSa 10:00-14:00",
			'llms_txt_post_ids'            => '12,13,99',
		);

		$output = $this->render_tab( 'modules' );

		$lightweight_seo_test_query_state['title'] = $previous_title;

		// Every stored field is still printed, with the name that saves it, so nothing depends on the script.
		$this->assertStringContainsString( '<textarea id="lightweight_seo_redirect_rules" name="lightweight_seo_settings[redirect_rules]"', $output );
		$this->assertStringContainsString( "/old /new 301\n/moved /here 302</textarea>", $output );
		$this->assertStringContainsString( '<textarea id="lightweight_seo_hreflang_mappings" name="lightweight_seo_settings[hreflang_mappings]"', $output );
		$this->assertStringContainsString( '<textarea id="lightweight_seo_local_business_opening_hours" name="lightweight_seo_settings[local_business_opening_hours]"', $output );
		$this->assertStringContainsString( '<input type="text" id="lightweight_seo_llms_txt_post_ids" name="lightweight_seo_settings[llms_txt_post_ids]" value="12,13,99"', $output );

		// The editors start hidden and point at those fields.
		$this->assertStringContainsString( '<div class="lwseo-rows lwseo-rows--redirects" data-format="redirects" data-field="lightweight_seo_redirect_rules" data-limit="500"', $output );
		$this->assertStringContainsString( '<div class="lwseo-rows lwseo-rows--hreflang" data-format="hreflang" data-field="lightweight_seo_hreflang_mappings" data-limit="500"', $output );
		$this->assertStringContainsString( '<div class="lwseo-hours" data-field="lightweight_seo_local_business_opening_hours"', $output );
		$this->assertStringContainsString( '<div class="lwseo-picker" data-field="lightweight_seo_llms_txt_post_ids" data-limit="50"', $output );
		$this->assertSame( 4, preg_match_all( '/<div class="lwseo-(?:rows[^"]*|hours|picker)" [^>]* hidden>/', $output ) );

		// Each field keeps an accessible name now that its row is a group.
		foreach ( array( 'redirect_rules', 'hreflang_mappings', 'llms_txt_post_ids' ) as $field ) {
			$this->assertStringContainsString( 'role="group" aria-labelledby="lwseo-label-' . $field . '"', $output );
			$this->assertMatchesRegularExpression( '/id="lightweight_seo_' . $field . '"[^>]* aria-labelledby="lwseo-label-' . $field . '"/', $output );
		}

		// Row controls carry no name, so only the stored field is ever submitted.
		preg_match_all( '/<template>(.*?)<\/template>/s', $output, $templates );

		$this->assertCount( 3, $templates[1] );

		foreach ( $templates[1] as $template ) {
			$this->assertStringNotContainsString( ' name="', $template );
		}

		$this->assertStringContainsString( '<input type="text" data-field="source" aria-label="From"', $output );
		$this->assertStringContainsString( '<select data-field="status" aria-label="Type">', $output );
		$this->assertStringContainsString( '<input type="hidden" data-field="type" value="post">', $output );
		$this->assertStringContainsString( 'data-field="language" aria-label="Language" placeholder="en-GB" list="lwseo-hreflang-languages"', $output );
		$this->assertStringContainsString( '<datalist id="lwseo-hreflang-languages">', $output );
		$this->assertSame( 7, substr_count( $output, 'class="lwseo-hours__day"' ) );
		$this->assertMatchesRegularExpression( '/data-day="Mo" role="group" aria-label="Monday">.*data-day="Su" role="group" aria-label="Sunday">/s', $output );

		// Stored pages are named for the script, as escaped text. A legacy line has no page to name.
		$this->assertStringContainsString( 'data-labels="{&quot;post:12&quot;:&quot;A \\&quot;quoted\\&quot; title&quot;}"', $output );
		$this->assertStringContainsString( '&quot;12&quot;:{&quot;title&quot;:&quot;A \\&quot;quoted\\&quot; title&quot;,&quot;note&quot;:&quot;&quot;}', $output );
		$this->assertStringContainsString( '&quot;13&quot;:{&quot;title&quot;:&quot;A \\&quot;quoted\\&quot; title&quot;,&quot;note&quot;:&quot;Not public, so it is left out&quot;}', $output );
		$this->assertStringContainsString( '&quot;99&quot;:{&quot;title&quot;:&quot;&quot;,&quot;note&quot;:&quot;No longer exists&quot;}', $output );
		$this->assertStringNotContainsString( '<b>title</b>', $output );
	}

	public function test_a_modules_save_says_when_lines_were_left_out(): void {
		global $lightweight_seo_test_settings_errors;

		$admin_settings = $this->get_admin_settings( new Lightweight_SEO_Settings() );
		$validated      = $admin_settings->validate_settings(
			array(
				'settings_tab'      => 'modules',
				'redirect_rules'    => "/a /b 301\n/only-a-source\n/a /c 302\n\n/d /e",
				'hreflang_mappings' => "post:12 en-GB https://example.co.uk/a/\nthis is not a mapping",
			)
		);

		// The later rule for /a wins, and the line without a destination is dropped.
		$this->assertSame( "/a /c 302\n/d /e 301", $validated['redirect_rules'] );
		$this->assertSame( 'post:12 en-GB https://example.co.uk/a/', $validated['hreflang_mappings'] );

		$messages = array_column( $lightweight_seo_test_settings_errors, 'message', 'code' );

		$this->assertStringContainsString( 'Settings saved. 2 redirect rules were left out', $messages['redirect_rules_dropped'] );
		$this->assertStringContainsString( 'Settings saved. 1 translation was left out', $messages['hreflang_mappings_dropped'] );
		$this->assertSame( array( 'warning', 'warning' ), array_column( $lightweight_seo_test_settings_errors, 'type' ) );

		// Nothing is said when every line was kept, or when another screen saved.
		$lightweight_seo_test_settings_errors = array();

		$admin_settings->validate_settings(
			array(
				'settings_tab'      => 'modules',
				'redirect_rules'    => "/a /b 301\n/d /e 302",
				'hreflang_mappings' => 'post:12 en-GB https://example.co.uk/a/',
			)
		);
		$admin_settings->validate_settings(
			array(
				'settings_tab'   => 'appearance',
				'redirect_rules' => '/only-a-source',
			)
		);

		$this->assertSame( array(), $lightweight_seo_test_settings_errors );
	}

	public function test_ai_card_prints_the_readiness_report_grouped_by_area(): void {
		global $lightweight_seo_test_home_url;
		global $lightweight_seo_test_options;

		require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-ai-discovery-module.php';

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ] = array( 'ai' => true );
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]         = array( 'enable_ai_discovery' => '1' );

		ob_start();
		( new Lightweight_SEO_Admin_Module_Fields( new Lightweight_SEO_Settings() ) )->ai_readiness_render();
		$output = ob_get_clean();

		// Seven areas, in the order of the reference, and no score anywhere.
		preg_match_all( '/<h4 class="lwseo-readiness__area">([^<]+)<\/h4>/', $output, $areas );
		$this->assertSame( array( 'Discoverability', 'Content', 'Bot governance', 'Structured data', 'Capabilities', 'Authentication', 'Agent protocols' ), $areas[1] );
		$this->assertDoesNotMatchRegularExpression( '/\d+\s*%|\bscore\b(?! there)|\blevel\b/i', wp_strip_all_tags( str_replace( 'There is no score', '', $output ) ) );

		$this->assertStringContainsString( '<div class="lwseo-readiness" data-lwseo-served data-url="https://example.com/robots.txt"', $output );
		$this->assertStringContainsString( '&quot;OAI-SearchBot&quot;:true', $output );
		$this->assertStringContainsString( '&quot;GPTBot&quot;:false', $output );
		$this->assertStringContainsString( '&quot;mcp&quot;:[&quot;https:\/\/example.com\/.well-known\/mcp\/server-card.json&quot;', $output );
		$this->assertStringContainsString( 'data-prefix-off="Off by choice:"', $output );
		$this->assertStringContainsString( 'data-prefix-na="Not applicable:"', $output );

		// Rows about a served address are pending until the browser decides them, and link to the address.
		foreach ( array( 'fetch', 'sitemapXml', 'sitemap', 'linkHeaders', 'markdown', 'llms', 'rules', 'blocked', 'signals', 'apiCatalog', 'skills', 'mcp', 'ard' ) as $row ) {
			$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-pending" data-readiness="' . $row . '" data-served-check="' . $row . '"/', $output, $row );
		}

		$this->assertStringNotContainsString( ' hidden ', $output );
		$this->assertStringContainsString( '<a class="lwseo-status__action" href="https://example.com/.well-known/api-catalog" target="_blank" rel="noopener noreferrer">Open', $output );
		$this->assertStringContainsString( 'data-msg-none="Not offered. This version of Lightweight SEO does not publish it, and nothing else answers the address with one."', $output );
		$this->assertStringContainsString( 'data-msg-partly="These groups disallow the homepage and every page their Allow lines do not list: %s."', $output );
		$this->assertStringContainsString( 'data-msg-missing="No Sitemap line is served. Add this line to the file: %s"', $output );
		$this->assertStringContainsString( 'data-msg-off="The content signals switch on this card is off, and no Content-Signal line is served."', $output );
		// Every row about an address can say it was not checked, which is not a finding about the site.
		$this->assertSame( 13, substr_count( $output, 'data-msg-unchecked="Not checked: this browser could not fetch the address.' ) );
		$this->assertStringContainsString( 'WordPress has none, its REST API signs in with application passwords', $output );
		$this->assertStringContainsString( 'they are set at the DNS provider, where Lightweight SEO can neither publish nor check them.', $output );
		$this->assertStringNotContainsString( 'a site that publishes content', $output );

		// Settings-based rows and their states.
		$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-success" data-readiness="search_visibility"/', $output );
		// Who writes robots.txt is not passed by the server; the browser settles it from the served file.
		$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-info" data-readiness="robots_owner" data-settled-by="rules" data-msg-present="[^"]+" data-msg-absent="[^"]+but the served file does not have the part Lightweight SEO adds/', $output );
		$this->assertStringNotContainsString( 'WordPress writes robots.txt', $output );
		$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-off" data-readiness="training">.*?Off by choice:.*?Disallowed, as chosen on this card\./s', $output );
		$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-off" data-readiness="llms_pages"/', $output );
		$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-warning" data-readiness="identity">.*?The Organization node is missing: logo, description, profile links\./s', $output );

		// Every check that does not apply to a content site says why, in one sentence.
		foreach ( array( 'dnsAid', 'webBotAuth', 'openApi', 'webMcp', 'oauth', 'a2a', 'commerce' ) as $row ) {
			$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-na" data-readiness="' . $row . '">.*?<p class="lwseo-status__text">[^<]+\.<\/p>/s', $output, $row );
		}

		$previous_home                 = $lightweight_seo_test_home_url;
		$lightweight_seo_test_home_url = 'https://example.com/blog';

		ob_start();
		( new Lightweight_SEO_Admin_Module_Fields( new Lightweight_SEO_Settings() ) )->ai_readiness_render();
		$subdirectory = ob_get_clean();

		$lightweight_seo_test_home_url = $previous_home;

		$this->assertStringContainsString( 'WordPress is installed under /blog, and crawlers read robots.txt only at https://example.com/robots.txt, which WordPress does not answer.', $subdirectory );
		$this->assertStringContainsString( '&quot;rules&quot;:[]', $subdirectory );
		// No rules are expected there, so no row waits for them.
		$this->assertStringNotContainsString( 'data-served-check="rules"', $subdirectory );
	}

	public function test_robots_reader_loads_on_the_modules_tab_while_ai_discovery_is_on(): void {
		global $lightweight_seo_test_enqueued_scripts;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_screen;

		$admin                       = new Lightweight_SEO_Admin( 'lightweight-seo', '1.1.0', new Lightweight_SEO_Settings(), $this->get_post_meta() );
		$lightweight_seo_test_screen = (object) array(
			'base'      => 'toplevel_page_lightweight-seo',
			'post_type' => '',
		);
		$loads                       = function ( $tab, $ai ) use ( $admin ) {
			global $lightweight_seo_test_enqueued_scripts;
			global $lightweight_seo_test_options;

			$lightweight_seo_test_enqueued_scripts                               = array();
			$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ] = array( 'ai' => $ai );
			$_GET['tab'] = $tab;

			$admin->enqueue_admin_scripts( 'toplevel_page_lightweight-seo' );
			unset( $_GET['tab'] );

			return isset( $lightweight_seo_test_enqueued_scripts['lightweight-seo-robots'] );
		};

		$this->assertTrue( $loads( 'modules', true ) );
		$this->assertSame( array( 'jquery', 'lightweight-seo-formats', 'lightweight-seo-robots' ), $lightweight_seo_test_enqueued_scripts['lightweight-seo-admin-script'] );
		$this->assertFalse( $loads( 'modules', false ) );
		$this->assertFalse( $loads( 'appearance', true ) );
		$this->assertSame( array( 'jquery', 'lightweight-seo-formats' ), $lightweight_seo_test_enqueued_scripts['lightweight-seo-admin-script'] );

		$lightweight_seo_test_screen = null;
	}

	public function test_format_script_loads_on_the_settings_screen_only(): void {
		global $lightweight_seo_test_enqueued_scripts;
		global $lightweight_seo_test_localized_scripts;
		global $lightweight_seo_test_screen;

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.1.0', new Lightweight_SEO_Settings(), $this->get_post_meta() );

		$lightweight_seo_test_enqueued_scripts  = array();
		$lightweight_seo_test_localized_scripts = array();
		$lightweight_seo_test_screen            = (object) array(
			'base'      => 'toplevel_page_lightweight-seo',
			'post_type' => '',
		);

		$admin->enqueue_admin_scripts( 'toplevel_page_lightweight-seo' );

		$this->assertSame( array(), $lightweight_seo_test_enqueued_scripts['lightweight-seo-formats'] );
		$this->assertSame( array( 'jquery', 'lightweight-seo-formats' ), $lightweight_seo_test_enqueued_scripts['lightweight-seo-admin-script'] );
		$this->assertSame( 'https://example.com/wp-json/', $lightweight_seo_test_localized_scripts['lightweight-seo-admin-script']['restUrl'] );
		$this->assertSame( 'nonce-wp_rest', $lightweight_seo_test_localized_scripts['lightweight-seo-admin-script']['restNonce'] );

		// The editor panel fills variables and shortens its preview with the same file.
		$lightweight_seo_test_enqueued_scripts = array();
		$lightweight_seo_test_screen           = (object) array(
			'base'      => 'post',
			'post_type' => 'post',
		);

		$admin->enqueue_admin_scripts( 'post.php' );

		$lightweight_seo_test_screen = null;

		$this->assertSame( array(), $lightweight_seo_test_enqueued_scripts['lightweight-seo-formats'] );
		$this->assertSame( array( 'jquery', 'lightweight-seo-formats' ), $lightweight_seo_test_enqueued_scripts['lightweight-seo-admin-script'] );
	}

	public function test_the_command_palette_gets_one_entry_per_tab_and_one_for_inspect_where_wordpress_loads_it(): void {
		global $lightweight_seo_test_enqueued_scripts;
		global $lightweight_seo_test_inline_scripts;
		global $lightweight_seo_test_user_can;

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.1.0', new Lightweight_SEO_Settings(), $this->get_post_meta() );

		// WordPress before 6.9, or a screen without the palette: nothing is printed.
		$lightweight_seo_test_enqueued_scripts = array();
		$lightweight_seo_test_inline_scripts   = array();
		$admin->add_palette_commands();

		$this->assertSame( array(), $lightweight_seo_test_inline_scripts );

		$lightweight_seo_test_enqueued_scripts = array( 'wp-core-commands' => array() );

		// Someone who cannot open the settings screen gets no entries for it.
		$lightweight_seo_test_user_can = false;
		$admin->add_palette_commands();
		$lightweight_seo_test_user_can = true;

		$this->assertSame( array(), $lightweight_seo_test_inline_scripts );

		$admin->add_palette_commands();

		$script = $lightweight_seo_test_inline_scripts['wp-core-commands'][0];

		$lightweight_seo_test_enqueued_scripts = array();
		$lightweight_seo_test_inline_scripts   = array();

		$this->assertStringContainsString( 'wp.data.dispatch( "core/commands" )', $script );
		$this->assertSame( 1, preg_match( '/\)\( (\[.*\]) \);$/s', $script, $matches ) );

		$commands = json_decode( $matches[1], true );

		$this->assertSame(
			array( 'lightweight-seo/overview', 'lightweight-seo/appearance', 'lightweight-seo/indexation', 'lightweight-seo/identity', 'lightweight-seo/modules', 'lightweight-seo/tools', 'lightweight-seo/inspect' ),
			array_column( $commands, 'name' )
		);
		$this->assertSame( 'Lightweight SEO: Search appearance', $commands[1]['label'] );
		$this->assertSame( 'https://example.com/wp-admin/admin.php?page=lightweight-seo&tab=appearance', $commands[1]['url'] );
		$this->assertSame( 'https://example.com/wp-admin/admin.php?page=lightweight-seo&tab=tools#lwseo-inspect', $commands[6]['url'] );
		$this->assertStringNotContainsString( '</script', $script );
	}

	public function test_search_previews_carry_a_size_switch_that_only_the_script_reveals(): void {
		ob_start();
		Lightweight_SEO_Admin_UI::search_preview(
			array(
				'url'         => 'https://example.com/a-page/',
				'title'       => 'A title',
				'description' => 'A description',
			)
		);
		$preview = ob_get_clean();

		$this->assertStringContainsString( 'class="lwseo-preview lwseo-preview--search" data-device="desktop"', $preview );
		$this->assertStringContainsString( '<div class="lwseo-preview__devices" role="group" aria-label="Preview size" hidden>', $preview );
		$this->assertStringContainsString( 'data-device="desktop" aria-pressed="true">Desktop</button>', $preview );
		$this->assertStringContainsString( 'data-device="mobile" aria-pressed="false">Mobile</button>', $preview );
		// Without the script the whole text is shown, never a shortened one.
		$this->assertStringContainsString( 'data-preview="title">A title</p>', $preview );

		$this->assertSame( 1, preg_match( '/data-limits="([^"]+)"/', $preview, $matches ) );

		$limits = json_decode( html_entity_decode( $matches[1], ENT_QUOTES ), true );

		// The desktop limits are the upper ends of the length guidance in the editor panel.
		$this->assertSame( 60, $limits['desktop']['title'] );
		$this->assertSame( 160, $limits['desktop']['description'] );
		$this->assertGreaterThan( $limits['desktop']['title'], $limits['mobile']['title'] );
		$this->assertLessThan( $limits['desktop']['description'], $limits['mobile']['description'] );
	}

	public function test_post_lists_load_only_the_script_that_fills_quick_edit(): void {
		global $lightweight_seo_test_enqueued_scripts;
		global $lightweight_seo_test_enqueued_styles;
		global $lightweight_seo_test_localized_scripts;
		global $lightweight_seo_test_screen;

		$admin = new Lightweight_SEO_Admin( 'lightweight-seo', '1.1.0', new Lightweight_SEO_Settings(), $this->get_post_meta() );

		$lightweight_seo_test_enqueued_scripts  = array();
		$lightweight_seo_test_enqueued_styles   = array();
		$lightweight_seo_test_localized_scripts = array();
		$lightweight_seo_test_screen            = (object) array(
			'base'      => 'edit',
			'post_type' => 'page',
		);

		$admin->enqueue_admin_scripts( 'edit.php' );

		// The script waits for the WordPress Quick Edit script it extends.
		$this->assertSame( array( 'lightweight-seo-admin-script' => array( 'jquery', 'inline-edit-post' ) ), $lightweight_seo_test_enqueued_scripts );
		$this->assertSame( array(), $lightweight_seo_test_enqueued_styles, 'A post list is a WordPress screen and gets no plugin stylesheet.' );
		$this->assertSame( array(), $lightweight_seo_test_localized_scripts );

		// A list the column is not on loads nothing.
		foreach ( array( 'attachment', 'product' ) as $post_type ) {
			$lightweight_seo_test_enqueued_scripts = array();
			$lightweight_seo_test_screen           = (object) array(
				'base'      => 'edit',
				'post_type' => $post_type,
			);

			$admin->enqueue_admin_scripts( 'edit.php' );

			$this->assertSame( array(), $lightweight_seo_test_enqueued_scripts, $post_type );
		}

		$lightweight_seo_test_screen = null;
	}

	public function test_settings_forms_carry_an_unsaved_changes_status(): void {
		$this->assertStringContainsString( '<span class="lwseo-actions__status" role="status" data-unsaved="Unsaved changes"></span>', $this->render_tab( 'appearance' ) );
		$this->assertStringNotContainsString( 'lwseo-actions__status', $this->render_overview( new Lightweight_SEO_Settings() ) );
	}

	private function render_overview( $settings ) {
		$_GET = array();

		ob_start();
		( new Lightweight_SEO_Admin_Page( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) )->display_plugin_admin_page();

		return ob_get_clean();
	}

	public function test_setup_checklist_counts_only_steps_that_are_really_done(): void {
		global $lightweight_seo_test_options;

		$fresh = $this->render_overview( new Lightweight_SEO_Settings() );

		// The test site has a name; nothing else is set on a fresh install.
		$this->assertStringContainsString( '1 of 3 ready', $fresh );
		$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-success">(?:(?!<\/li>).)*Set a site name/s', $fresh );
		$this->assertStringContainsString( 'Write a homepage description', $fresh );
		$this->assertStringContainsString( 'Select a default social image', $fresh );
		$this->assertStringNotContainsString( 'Verify generated output', $fresh );
		// Optional steps are offered as neutral facts and never counted.
		$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-info">.*?Review optional modules.*?Optional/s', $fresh );

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'meta_description' => 'A short description of the homepage.',
			'social_image'     => 'https://example.com/share.jpg',
		);

		$configured = $this->render_overview( new Lightweight_SEO_Settings() );

		$this->assertStringContainsString( '3 of 3 ready', $configured );
		$this->assertStringContainsString( 'The essentials are set', $configured );
		$this->assertStringNotContainsString( 'Write a homepage description', $configured );
		$this->assertStringContainsString( 'Review optional modules', $configured );
	}

	public function test_setup_checklist_does_not_ask_for_a_tagline(): void {
		// WordPress installs with an empty tagline, and the default homepage title format ends in it.
		$GLOBALS['lightweight_seo_test_tagline'] = '';

		try {
			$without_tagline = $this->render_overview( new Lightweight_SEO_Settings() );
		} finally {
			unset( $GLOBALS['lightweight_seo_test_tagline'] );
		}

		$this->assertStringContainsString( '1 of 3 ready', $without_tagline );
		$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-success">(?:(?!<\/li>).)*Set a site name/s', $without_tagline );
		$this->assertStringNotContainsString( 'tagline', $without_tagline );
	}

	public function test_setup_checklist_asks_for_a_site_name_when_the_site_has_none(): void {
		$GLOBALS['lightweight_seo_test_site_name'] = '  ';

		try {
			$without_name = $this->render_overview( new Lightweight_SEO_Settings() );
		} finally {
			unset( $GLOBALS['lightweight_seo_test_site_name'] );
		}

		$this->assertStringContainsString( '0 of 3 ready', $without_name );
		$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-pending">(?:(?!<\/li>).)*Set a site name(?:(?!<\/li>).)*Page titles use it\.(?:(?!<\/li>).)*General settings/s', $without_name );
	}

	public function test_setup_checklist_accepts_a_description_written_on_the_static_front_page(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_posts[12]                                     = (object) array(
			'ID'           => 12,
			'post_excerpt' => '',
			'post_content' => '',
			'permalink'    => 'https://example.com/',
		);
		$lightweight_seo_test_options['show_on_front']                      = 'page';
		$lightweight_seo_test_options['page_on_front']                      = 12;
		$lightweight_seo_test_post_meta[12]['_lightweight_seo_description'] = 'Written on the front page itself.';

		$this->assertStringContainsString( '2 of 3 ready', $this->render_overview( new Lightweight_SEO_Settings() ) );
	}

	public function test_overview_previews_a_posts_homepage_from_the_homepage_defaults(): void {
		global $lightweight_seo_test_options;

		$empty = $this->render_overview( new Lightweight_SEO_Settings() );

		$this->assertStringContainsString( 'Your homepage', $empty );
		$this->assertStringContainsString( '<p class="lwseo-preview__title" data-preview="title">Test Site – Test Tagline</p>', $empty );
		$this->assertStringContainsString( 'No description is set, so search engines will pick text from the page.', $empty );
		$this->assertMatchesRegularExpression( '/<div class="lwseo-preview__media">\s*<\/div>/', $empty );
		$this->assertStringContainsString( 'tab=appearance">Edit homepage defaults</a>', $empty );
		// The homepage preview comes first, so the screen opens with the site rather than a status list.
		$this->assertLessThan( strpos( $empty, 'Essential setup' ), strpos( $empty, 'Your homepage' ) );

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'home_title_format' => '%sitename% | Photography',
			'meta_description'  => 'Portraits and weddings in <em>Leeds</em>.',
			'social_image'      => 'https://example.com/share.jpg',
		);

		$configured = $this->render_overview( new Lightweight_SEO_Settings() );

		$this->assertStringContainsString( 'data-preview="title">Test Site | Photography</p>', $configured );
		$this->assertStringContainsString( 'data-preview="description">Portraits and weddings in Leeds.</p>', $configured );
		$this->assertStringContainsString( 'data-preview="social-title">Test Site | Photography</p>', $configured );
		$this->assertStringContainsString( '<img src="https://example.com/share.jpg" alt="">', $configured );
		// A static preview is not a live region.
		$this->assertStringNotContainsString( 'aria-live', $configured );
	}

	public function test_overview_preview_uses_the_chosen_title_separator(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'title_separator' => '·' );

		$this->assertStringContainsString( 'data-preview="title">Test Site · Test Tagline</p>', $this->render_overview( new Lightweight_SEO_Settings() ) );
	}

	public function test_overview_previews_a_static_front_page_from_its_own_seo_fields(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_posts[12]                = (object) array(
			'ID'            => 12,
			'post_excerpt'  => '',
			'post_content'  => '<p>Welcome to the studio.</p>',
			'permalink'     => 'https://example.com/',
			'thumbnail_id'  => 40,
			'thumbnail_url' => 'https://example.com/featured.jpg',
		);
		$previous_title                                = $lightweight_seo_test_query_state['title'];
		$lightweight_seo_test_query_state['title']     = 'Home';
		$lightweight_seo_test_options['show_on_front'] = 'page';
		$lightweight_seo_test_options['page_on_front'] = 12;
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'meta_description' => 'The homepage default.',
			'social_image'     => 'https://example.com/default.jpg',
		);

		$defaults = $this->render_overview( new Lightweight_SEO_Settings() );

		$lightweight_seo_test_post_meta[12] = array(
			'_lightweight_seo_title'        => '%title% %sep% Studio',
			'_lightweight_seo_description'  => 'Written on the front page.',
			'_lightweight_seo_social_title' => 'Share me',
			'_lightweight_seo_social_image' => 'https://example.com/own.jpg',
		);

		$own = $this->render_overview( new Lightweight_SEO_Settings() );

		$lightweight_seo_test_query_state['title'] = $previous_title;

		// Without its own fields the page follows the homepage format, the homepage default, then its featured image.
		$this->assertStringContainsString( 'data-preview="title">Test Site – Test Tagline</p>', $defaults );
		$this->assertStringContainsString( 'data-preview="description">The homepage default.</p>', $defaults );
		$this->assertStringContainsString( '<img src="https://example.com/featured.jpg" alt="">', $defaults );
		$this->assertStringContainsString( 'post=12&amp;action=edit">Edit front page</a>', $defaults );

		$this->assertStringContainsString( 'data-preview="title">Home – Studio</p>', $own );
		$this->assertStringContainsString( 'data-preview="description">Written on the front page.</p>', $own );
		$this->assertStringContainsString( 'data-preview="social-title">Share me</p>', $own );
		$this->assertStringContainsString( 'data-preview="social-description">Written on the front page.</p>', $own );
		$this->assertStringContainsString( '<img src="https://example.com/own.jpg" alt="">', $own );
	}

	public function test_setup_checklist_is_dismissed_and_restored_per_user(): void {
		global $lightweight_seo_test_nonce_is_valid;
		global $lightweight_seo_test_user_meta;

		$settings = new Lightweight_SEO_Settings();
		$page     = new class( 'lightweight-seo', $settings, $this->get_tools_fields( $settings ), $this->get_post_meta() ) extends Lightweight_SEO_Admin_Page {
			public $redirects = array();

			protected function redirect( $url ) {
				$this->redirects[] = $url;

				return false;
			}
		};

		$this->assertStringContainsString( 'admin-post.php?action=lightweight_seo_setup_checklist&amp;_wpnonce=nonce-lightweight_seo_setup_checklist', $this->render_overview( $settings ) );

		$_GET = array( '_wpnonce' => 'nonce-lightweight_seo_setup_checklist' );
		$page->handle_setup_checklist();

		$this->assertSame( '1', $lightweight_seo_test_user_meta[1]['lightweight_seo_checklist_dismissed'] );
		$this->assertSame( array( 'https://example.com/wp-admin/admin.php?page=lightweight-seo' ), $page->redirects );

		$dismissed = $this->render_overview( $settings );

		$this->assertStringNotContainsString( 'Essential setup', $dismissed );
		$this->assertStringContainsString( 'Show setup checklist', $dismissed );
		$this->assertStringContainsString( 'show=1', $dismissed );

		$_GET = array(
			'_wpnonce' => 'nonce-lightweight_seo_setup_checklist',
			'show'     => '1',
		);
		$page->handle_setup_checklist();

		$this->assertArrayNotHasKey( 'lightweight_seo_checklist_dismissed', $lightweight_seo_test_user_meta[1] );
		$this->assertStringContainsString( 'Essential setup', $this->render_overview( $settings ) );

		// A request without the action's own nonce changes nothing.
		$lightweight_seo_test_nonce_is_valid = false;
		$_GET                                = array( '_wpnonce' => 'nonce-lightweight_seo_setup_checklist' );

		try {
			$page->handle_setup_checklist();
			$this->fail( 'A bad nonce should stop the request.' );
		} catch ( Lightweight_SEO_Test_WP_Die_Exception $exception ) {
			$this->assertArrayNotHasKey( 'lightweight_seo_checklist_dismissed', $lightweight_seo_test_user_meta[1] );
		}
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

	public function test_every_title_variable_survives_saving_a_format(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = ( new Lightweight_SEO_Settings() )->get_defaults();

		$format = '%title% %sep% %category% %sep% %author% %sep% %year% %sep% %page%';
		$saved  = $this->get_admin_settings( new Lightweight_SEO_Settings() )->validate_settings(
			array(
				'settings_tab'         => 'all',
				'title_format'         => $format,
				'home_title_format'    => '%sitename% %sep% %category%',
				'archive_title_format' => '%category% %sep% %page%',
				'search_title_format'  => '%search% %sep% %category%',
				'content_types'        => array(
					'post_types' => array( 'post' => array( 'title_format' => '%category%: %title%' ) ),
					'taxonomies' => array( 'category' => array( 'title_format' => '%category% %sep% %page%' ) ),
				),
			)
		);

		$this->assertSame( $format, $saved['title_format'] );
		$this->assertSame( '%sitename% %sep% %category%', $saved['home_title_format'] );
		$this->assertSame( '%category% %sep% %page%', $saved['archive_title_format'] );
		$this->assertSame( '%search% %sep% %category%', $saved['search_title_format'] );
		$this->assertSame( '%category%: %title%', $saved['content_types']['post_types']['post']['title_format'] );
		$this->assertSame( '%category% %sep% %page%', $saved['content_types']['taxonomies']['category']['title_format'] );
	}
}
