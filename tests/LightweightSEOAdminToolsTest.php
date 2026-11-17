<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-importer-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-import-state.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-tools.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOAdminToolsTest extends TestCase {

	const TOOLS_URL = 'https://example.com/wp-admin/admin.php?page=lightweight-seo&tab=tools&settings-updated=true';

	protected function setUp(): void {
		global $lightweight_seo_test_nonce_is_valid;
		global $lightweight_seo_test_option_autoload;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_settings_errors;
		global $lightweight_seo_test_transients;
		global $lightweight_seo_test_user_can;

		$lightweight_seo_test_nonce_is_valid  = true;
		$lightweight_seo_test_option_autoload = array();
		$lightweight_seo_test_options         = array();
		$lightweight_seo_test_post_meta       = array();
		$lightweight_seo_test_posts           = array();
		$lightweight_seo_test_settings_errors = array();
		$lightweight_seo_test_transients      = array();
		$lightweight_seo_test_user_can        = true;
		$_GET                                 = array();
		$_POST                                = array();
	}

	protected function tearDown(): void {
		global $lightweight_seo_test_user_can;

		$lightweight_seo_test_user_can = true;
		$_GET                          = array();
		$_POST                         = array();
	}

	public function test_every_handler_requires_the_manage_options_capability(): void {
		global $lightweight_seo_test_user_can;

		$lightweight_seo_test_user_can = false;

		foreach ( $this->get_handlers() as $method => $action ) {
			$this->seed_tool_data();
			$this->submit(
				$action,
				array(
					'import_source'           => 'yoast',
					'confirm_delete_404_logs' => '1',
				)
			);

			$this->assert_request_is_stopped_before_any_work( $method );
		}
	}

	public function test_every_handler_requires_its_nonce(): void {
		foreach ( $this->get_handlers() as $method => $action ) {
			$this->seed_tool_data();
			$_POST = array(
				'import_source'           => 'yoast',
				'confirm_delete_404_logs' => '1',
			);

			$this->assert_request_is_stopped_before_any_work( $method );
		}
	}

	public function test_a_nonce_issued_for_one_tool_action_is_rejected_by_the_others(): void {
		$handlers = $this->get_handlers();

		foreach ( $handlers as $method => $action ) {
			foreach ( $handlers as $other_action ) {
				if ( $other_action === $action ) {
					continue;
				}

				$this->seed_tool_data();
				// The right field for this handler, carrying a nonce that was issued for another action.
				$_POST = array(
					Lightweight_SEO_Admin_Tools::get_nonce_name( $action ) => wp_create_nonce( $other_action ),
					'import_source'           => 'yoast',
					'confirm_delete_404_logs' => '1',
				);

				$this->assert_request_is_stopped_before_any_work( $method );

				$this->seed_tool_data();
				// Another action's complete, valid nonce field.
				$this->submit(
					$other_action,
					array(
						'import_source'           => 'yoast',
						'confirm_delete_404_logs' => '1',
					)
				);

				$this->assert_request_is_stopped_before_any_work( $method );
			}
		}
	}

	public function test_preview_reports_the_next_batch_without_moving_the_cursor(): void {
		global $lightweight_seo_test_option_autoload;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_transients;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]               = array( 'import_source' => 'yoast' );
		$lightweight_seo_test_options[ Lightweight_SEO_Import_State::OPTION_NAME ] = array(
			'source' => 'yoast',
			'cursor' => 50,
			'report' => '',
		);
		$importer = $this->get_importer(
			array(
				'preview' => array(
					'scanned_posts'  => 50,
					'eligible_posts' => 3,
					'imported_posts' => 0,
					'updated_fields' => 7,
					'skipped_fields' => 2,
					'next_offset'    => 100,
					'has_more'       => true,
				),
			)
		);
		$tools    = $this->get_tools( $importer );
		$report   = 'Previewed yoast batch: scanned 50 posts, found 3 eligible posts and 7 fillable fields; skipped 2 occupied fields.';

		$this->submit( Lightweight_SEO_Admin_Tools::ACTION_IMPORT_PREVIEW, array( 'import_source' => 'yoast' ) );
		$tools->handle_import_preview();

		$this->assertSame( array( array( 'preview', 'yoast', 50 ) ), $importer->calls );
		$this->assertSame(
			array(
				'source' => 'yoast',
				'cursor' => 50,
				'report' => $report,
			),
			$lightweight_seo_test_options[ Lightweight_SEO_Import_State::OPTION_NAME ]
		);
		$this->assertSame( array( self::TOOLS_URL ), $tools->redirects );
		$this->assertSame(
			array(
				array(
					'setting' => LIGHTWEIGHT_SEO_OPTION_NAME,
					'code'    => 'import_preview',
					'message' => $report,
					'type'    => 'info',
				),
			),
			$lightweight_seo_test_transients['settings_errors']
		);

		// Import progress never touches the autoloaded settings option.
		$this->assertSame( array( 'import_source' => 'yoast' ), $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] );
		$this->assertSame( array( Lightweight_SEO_Import_State::OPTION_NAME => false ), $lightweight_seo_test_option_autoload );
	}

	public function test_import_advances_the_cursor_until_the_source_is_exhausted(): void {
		$importer = $this->get_importer(
			array(
				'import_batch' => array(
					'scanned_posts'      => 50,
					'eligible_posts'     => 4,
					'imported_posts'     => 4,
					'updated_fields'     => 9,
					'skipped_fields'     => 1,
					'unsupported_fields' => 2,
					'next_offset'        => 50,
					'has_more'           => true,
				),
			)
		);
		$tools    = $this->get_tools( $importer );

		$this->submit( Lightweight_SEO_Admin_Tools::ACTION_IMPORT_BATCH, array( 'import_source' => 'yoast' ) );
		$tools->handle_import_batch();

		$state = ( new Lightweight_SEO_Import_State() )->get();

		$this->assertSame( array( array( 'import_batch', 'yoast', 0 ) ), $importer->calls );
		$this->assertSame( 'yoast', $state['source'] );
		$this->assertSame( 50, $state['cursor'] );
		$this->assertSame(
			'Imported yoast batch: scanned 50 posts, found 4 eligible posts and 9 fillable fields; skipped 1 occupied fields. 2 fields use template variables with no Lightweight SEO equivalent and were left for manual review.',
			$state['report']
		);
		$this->assertSame( 'success', $this->get_last_notice()['type'] );

		$importer->reports['import_batch'] = array(
			'scanned_posts'  => 12,
			'imported_posts' => 1,
			'updated_fields' => 1,
			'skipped_fields' => 0,
			'next_offset'    => 62,
			'has_more'       => false,
		);
		$tools->handle_import_batch();

		$state = ( new Lightweight_SEO_Import_State() )->get();

		$this->assertSame( array( 'import_batch', 'yoast', 50 ), $importer->calls[1] );
		$this->assertSame( 0, $state['cursor'] );
		$this->assertSame( 'Imported yoast batch: scanned 12 posts, found 1 eligible posts and 1 fillable fields; skipped 0 occupied fields.', $state['report'] );
		$this->assertSame( array( self::TOOLS_URL, self::TOOLS_URL ), $tools->redirects );
	}

	public function test_changing_the_source_restarts_the_scan_and_discards_the_previous_rollback_snapshot(): void {
		global $lightweight_seo_test_options;

		$this->seed_tool_data();
		$importer = $this->get_importer( array( 'import_batch' => array( 'has_more' => false ) ) );
		$tools    = $this->get_tools( $importer );

		$this->submit( Lightweight_SEO_Admin_Tools::ACTION_IMPORT_BATCH, array( 'import_source' => 'rank_math' ) );
		$tools->handle_import_batch();

		$this->assertSame( array( array( 'import_batch', 'rank_math', 0 ) ), $importer->calls );
		$this->assertSame( 'rank_math', ( new Lightweight_SEO_Import_State() )->get()['source'] );
		$this->assertArrayNotHasKey( Lightweight_SEO_Importer_Service::ROLLBACK_OPTION, $lightweight_seo_test_options );
	}

	public function test_preview_and_import_without_a_supported_source_run_nothing(): void {
		foreach ( array( '', 'unknown-plugin' ) as $source ) {
			foreach ( array( 'handle_import_preview', 'handle_import_batch' ) as $method ) {
				$this->setUp();
				$importer = $this->get_importer();
				$tools    = $this->get_tools( $importer );

				$this->submit( $this->get_handlers()[ $method ], array( 'import_source' => $source ) );
				$tools->$method();

				$state = ( new Lightweight_SEO_Import_State() )->get();

				$this->assertSame( array(), $importer->calls );
				$this->assertSame( '', $state['source'] );
				$this->assertSame( 'Select a supported import source first.', $state['report'] );
				$this->assertSame( 'error', $this->get_last_notice()['type'] );
				$this->assertSame( array( self::TOOLS_URL ), $tools->redirects );
			}
		}
	}

	public function test_rollback_returns_the_cursor_to_the_start_of_the_rolled_back_batch(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ Lightweight_SEO_Import_State::OPTION_NAME ] = array(
			'source' => 'yoast',
			'cursor' => 100,
			'report' => 'Imported yoast batch.',
		);
		$importer = $this->get_importer(
			array(
				'rollback_last_batch' => array(
					'source'          => 'yoast',
					'restored_fields' => 5,
					'restored_posts'  => 2,
					'offset'          => 50,
				),
			)
		);
		$tools    = $this->get_tools( $importer );

		// The source select shares the form; rollback must not act on it.
		$this->submit( Lightweight_SEO_Admin_Tools::ACTION_IMPORT_ROLLBACK, array( 'import_source' => 'rank_math' ) );
		$tools->handle_import_rollback();

		$this->assertSame( array( array( 'rollback_last_batch' ) ), $importer->calls );
		$this->assertSame(
			array(
				'source' => 'yoast',
				'cursor' => 50,
				'report' => 'Rolled back the latest batch: restored 2 posts and 5 fields.',
			),
			$lightweight_seo_test_options[ Lightweight_SEO_Import_State::OPTION_NAME ]
		);
		$this->assertSame( 'success', $this->get_last_notice()['type'] );
		$this->assertSame( array( self::TOOLS_URL ), $tools->redirects );
	}

	public function test_import_and_rollback_change_metadata_through_the_importer_service(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_post_meta = array(
			81 => array(
				'_yoast_wpseo_title'    => 'Imported Title',
				'_yoast_wpseo_metadesc' => 'Imported Description',
			),
		);
		$lightweight_seo_test_posts     = array(
			81 => (object) array(
				'ID'          => 81,
				'post_type'   => 'post',
				'post_status' => 'publish',
			),
		);
		$values                         = array( 81 => array( 'seo_title' => 'Keep Existing' ) );
		$importer                       = new Lightweight_SEO_Importer_Service( $this->get_post_meta( $values ) );
		$tools                          = $this->get_tools( $importer );

		$this->submit( Lightweight_SEO_Admin_Tools::ACTION_IMPORT_PREVIEW, array( 'import_source' => 'yoast' ) );
		$tools->handle_import_preview();

		$this->assertSame( array( 81 => array( 'seo_title' => 'Keep Existing' ) ), $values );
		$this->assertFalse( $importer->has_rollback() );

		$this->submit( Lightweight_SEO_Admin_Tools::ACTION_IMPORT_BATCH, array( 'import_source' => 'yoast' ) );
		$tools->handle_import_batch();

		$this->assertSame( 'Keep Existing', $values[81]['seo_title'] );
		$this->assertSame( 'Imported Description', $values[81]['seo_description'] );
		$this->assertTrue( $importer->has_rollback() );
		$this->assertSame(
			'Imported yoast batch: scanned 1 posts, found 1 eligible posts and 1 fillable fields; skipped 1 occupied fields.',
			( new Lightweight_SEO_Import_State() )->get()['report']
		);

		$this->submit( Lightweight_SEO_Admin_Tools::ACTION_IMPORT_ROLLBACK );
		$tools->handle_import_rollback();

		$this->assertSame( array( 81 => array( 'seo_title' => 'Keep Existing' ) ), $values );
		$this->assertFalse( $importer->has_rollback() );
		$this->assertSame(
			'Rolled back the latest batch: restored 1 posts and 1 fields.',
			( new Lightweight_SEO_Import_State() )->get()['report']
		);
	}

	public function test_deleting_legacy_404_logs_requires_the_confirmation_box(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options['lightweight_seo_404_logs'] = array( '/missing', '/gone' );
		$tools = $this->get_tools( $this->get_importer() );

		$this->submit( Lightweight_SEO_Admin_Tools::ACTION_DELETE_404_LOGS );
		$tools->handle_delete_legacy_404_logs();

		$this->assertSame( array( '/missing', '/gone' ), $lightweight_seo_test_options['lightweight_seo_404_logs'] );
		$this->assertSame( 'legacy_404_logs_unconfirmed', $this->get_last_notice()['code'] );
		$this->assertSame( 'error', $this->get_last_notice()['type'] );

		$this->submit( Lightweight_SEO_Admin_Tools::ACTION_DELETE_404_LOGS, array( 'confirm_delete_404_logs' => '1' ) );
		$tools->handle_delete_legacy_404_logs();

		$this->assertArrayNotHasKey( 'lightweight_seo_404_logs', $lightweight_seo_test_options );
		$this->assertSame( 'Deleted 2 retained legacy 404 log entries.', $this->get_last_notice()['message'] );
		$this->assertSame( 'success', $this->get_last_notice()['type'] );
		$this->assertSame( array( self::TOOLS_URL, self::TOOLS_URL ), $tools->redirects );
	}

	public function test_import_state_continues_from_values_an_earlier_build_kept_in_the_settings_option(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'import_source'      => 'aioseo',
			'import_cursor'      => 150,
			'last_import_report' => 'Imported aioseo batch.',
		);
		$state = new Lightweight_SEO_Import_State();

		$this->assertSame(
			array(
				'source' => 'aioseo',
				'cursor' => 150,
				'report' => 'Imported aioseo batch.',
			),
			$state->get()
		);

		$state->save(
			array(
				'source' => 'aioseo',
				'cursor' => 200,
				'report' => 'Imported the next batch.',
			)
		);

		$this->assertSame( 200, $state->get()['cursor'] );
		$this->assertSame( 150, $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]['import_cursor'] );
	}

	public function test_redirect_import_merges_a_csv_file_into_the_saved_rules(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'title_format'     => 'Kept %title%',
			'enable_redirects' => '1',
			'redirect_rules'   => "/kept /kept-target 301\n/changed /old-target 301\n/same /same-target 302",
		);

		$csv = tempnam( sys_get_temp_dir(), 'lwseo' );
		file_put_contents(
			$csv,
			"\xEF\xBB\xBFSource,Target,Status\n"
			. "/changed,/new-target,302\n"
			. "/same,/same-target,302\n"
			. "/added,\"/with space\",\n"
			. "/gone,,410\n"
			. "/section/*,/moved/*,308\n"
			. "\n"
			. "no-leading-slash,/anywhere,301\n"
			. "/unsafe,https://unapproved.example/x,301\n"
			. "/added,/second-wins,301\n"
		);

		$tools = $this->get_tools( $this->get_importer(), $csv );
		$this->submit( Lightweight_SEO_Admin_Tools::ACTION_REDIRECT_IMPORT );
		$tools->handle_redirect_import();
		unlink( $csv );

		$stored = $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ];

		$this->assertSame(
			array(
				'/kept /kept-target 301',
				'/changed /new-target 302',
				'/same /same-target 302',
				'/added /second-wins 301',
				'/gone 410',
				'/section/* /moved/* 308',
				// A path typed without its leading slash gets one, as on the settings screen.
				'/no-leading-slash /anywhere 301',
			),
			explode( "\n", $stored['redirect_rules'] )
		);
		// Nothing else in the settings is touched.
		$this->assertSame( 'Kept %title%', $stored['title_format'] );
		$this->assertSame( '1', $stored['enable_redirects'] );

		$notice = $this->get_last_notice();

		$this->assertSame( 'success', $notice['type'] );
		$this->assertStringContainsString( '4 added, 1 replaced, 1 already saved, 2 left out', $notice['message'] );
		$this->assertSame( array( 'https://example.com/wp-admin/admin.php?page=lightweight-seo&tab=modules&settings-updated=true' ), $tools->redirects );
	}

	public function test_redirect_import_without_a_usable_file_changes_nothing(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'redirect_rules' => '/kept /kept-target 301' );
		$before = $lightweight_seo_test_options;

		$tools = $this->get_tools( $this->get_importer(), '' );
		$this->submit( Lightweight_SEO_Admin_Tools::ACTION_REDIRECT_IMPORT );
		$tools->handle_redirect_import();

		$this->assertSame( $before, $lightweight_seo_test_options );
		$this->assertSame( 'error', $this->get_last_notice()['type'] );
		$this->assertStringContainsString( 'tab=modules', $tools->redirects[0] );
	}

	/** Handler method => admin-post action. */
	private function get_handlers() {
		return array(
			'handle_import_preview'         => Lightweight_SEO_Admin_Tools::ACTION_IMPORT_PREVIEW,
			'handle_import_batch'           => Lightweight_SEO_Admin_Tools::ACTION_IMPORT_BATCH,
			'handle_import_rollback'        => Lightweight_SEO_Admin_Tools::ACTION_IMPORT_ROLLBACK,
			'handle_delete_legacy_404_logs' => Lightweight_SEO_Admin_Tools::ACTION_DELETE_404_LOGS,
			'handle_redirect_import'        => Lightweight_SEO_Admin_Tools::ACTION_REDIRECT_IMPORT,
		);
	}

	/** Post the fields a tool form sends, including the nonce issued for its action. */
	private function submit( $action, array $fields = array() ) {
		$_POST = array_merge(
			array( Lightweight_SEO_Admin_Tools::get_nonce_name( $action ) => wp_create_nonce( $action ) ),
			$fields
		);
	}

	/** Stored data that each handler would change if it ran. */
	private function seed_tool_data() {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_settings_errors;
		global $lightweight_seo_test_transients;

		$lightweight_seo_test_settings_errors = array();
		$lightweight_seo_test_transients      = array();
		$lightweight_seo_test_options         = array(
			'lightweight_seo_404_logs'                => array( '/missing' ),
			Lightweight_SEO_Importer_Service::ROLLBACK_OPTION => array(
				'source'  => 'yoast',
				'offset'  => 0,
				'changes' => array(
					array(
						'post_id' => 81,
						'field'   => 'seo_title',
					),
				),
			),
			Lightweight_SEO_Import_State::OPTION_NAME => array(
				'source' => 'yoast',
				'cursor' => 50,
				'report' => 'Imported yoast batch.',
			),
		);
	}

	private function assert_request_is_stopped_before_any_work( $method ) {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_transients;

		$options_before = $lightweight_seo_test_options;
		$importer       = $this->get_importer();
		$tools          = $this->get_tools( $importer );

		try {
			$tools->$method();
			$this->fail( $method . ' must stop the request.' );
		} catch ( Lightweight_SEO_Test_WP_Die_Exception $exception ) {
			$this->assertNotSame( '', $exception->getMessage() );
		}

		$this->assertSame( array(), $importer->calls, $method );
		$this->assertSame( $options_before, $lightweight_seo_test_options, $method );
		$this->assertSame( array(), $lightweight_seo_test_transients, $method );
		$this->assertSame( array(), $tools->redirects, $method );
	}

	private function get_last_notice() {
		global $lightweight_seo_test_transients;

		$notices = $lightweight_seo_test_transients['settings_errors'];

		return end( $notices );
	}

	/** The handlers with the redirect observed instead of ending the process. */
	private function get_tools( $importer, $upload = '' ) {
		$tools = new class( 'lightweight-seo', $importer, new Lightweight_SEO_Import_State(), new Lightweight_SEO_Settings() ) extends Lightweight_SEO_Admin_Tools {
			public $redirects = array();

			public $upload = '';

			protected function get_uploaded_file( $field, $max_bytes ) {
				return $this->upload;
			}

			protected function redirect( $url ) {
				$this->redirects[] = $url;

				return false;
			}
		};

		$tools->upload = $upload;

		return $tools;
	}

	/** An importer that records its calls and returns the given reports. */
	private function get_importer( array $reports = array() ) {
		return new class( $reports ) {
			public $calls = array();

			public $reports;

			public function __construct( $reports ) {
				$this->reports = $reports;
			}

			public function preview( $source, $offset = 0 ) {
				$this->calls[] = array( 'preview', $source, $offset );

				return $this->reports['preview'] ?? array();
			}

			public function import_batch( $source, $offset = 0 ) {
				$this->calls[] = array( 'import_batch', $source, $offset );

				return $this->reports['import_batch'] ?? array();
			}

			public function rollback_last_batch() {
				$this->calls[] = array( 'rollback_last_batch' );

				return $this->reports['rollback_last_batch'] ?? array();
			}
		};
	}

	private function get_post_meta( &$values ) {
		return new class( $values ) {
			private $values;

			public function __construct( &$values ) {
				$this->values =& $values;
			}

			public function get_supported_post_types() {
				return array( 'post' );
			}

			public function get( $post_id, $field ) {
				return $this->values[ $post_id ][ $field ] ?? '';
			}

			public function get_meta_key( $field ) {
				return '_lightweight_seo_' . $field;
			}

			public function update( $post_id, $field, $value ) {
				$this->values[ $post_id ][ $field ] = $value;

				return true;
			}

			public function delete( $post_id, $field ) {
				unset( $this->values[ $post_id ][ $field ] );

				return true;
			}
		};
	}
}
