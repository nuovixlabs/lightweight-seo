<?php

require_once __DIR__ . '/fixtures/wp-cli-stub.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-module-state.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-data-registry.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-cli.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOCLITest extends TestCase {

	/** @var string[] */
	private $files = array();

	protected function setUp(): void {
		global $lightweight_seo_test_home_url;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_settings_errors;

		$lightweight_seo_test_home_url        = 'https://example.com';
		$lightweight_seo_test_options         = array();
		$lightweight_seo_test_settings_errors = array();
		WP_CLI::$commands                     = array();
		WP_CLI::$output                       = array();
		WP_CLI::$confirmations                = 0;
	}

	protected function tearDown(): void {
		array_map( 'unlink', array_filter( $this->files, 'file_exists' ) );
	}

	public function test_every_command_is_registered_under_one_name(): void {
		Lightweight_SEO_CLI::register();

		$this->assertSame(
			array(
				'lightweight-seo settings export',
				'lightweight-seo settings import',
				'lightweight-seo settings undo-import',
				'lightweight-seo module list',
				'lightweight-seo module enable',
				'lightweight-seo module disable',
				'lightweight-seo redirects import',
				'lightweight-seo redirects test',
			),
			array_keys( WP_CLI::$commands )
		);

		foreach ( WP_CLI::$commands as $callable ) {
			$this->assertTrue( is_callable( $callable ) );
		}
	}

	public function test_settings_travel_through_a_file_and_an_import_can_be_undone(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'title_format' => 'First %title%' );

		$cli  = new Lightweight_SEO_CLI();
		$file = $this->temporary_file();

		$cli->settings_export( array(), array( 'file' => $file ) );

		$this->assertSame( 'First %title%', json_decode( file_get_contents( $file ), true )['settings']['title_format'] );

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'title_format' => 'Second %title%' );

		$cli->settings_import( array( $file ), array() );

		$this->assertSame( 1, WP_CLI::$confirmations );
		$this->assertSame( 'First %title%', $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]['title_format'] );

		$cli->settings_undo_import( array(), array( 'yes' => true ) );

		$this->assertSame( array( 'title_format' => 'Second %title%' ), $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] );
		$this->assertSame( array( 'success', 'success', 'success' ), array_column( WP_CLI::$output, 0 ) );

		// Without a file the export is printed.
		WP_CLI::$output = array();
		$cli->settings_export( array(), array() );

		$this->assertSame( 'line', WP_CLI::$output[0][0] );
		$this->assertSame( 'lightweight-seo-settings', json_decode( WP_CLI::$output[0][1], true )['format'] );
	}

	public function test_an_unreadable_or_foreign_file_is_reported_as_an_error_and_changes_nothing(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'title_format' => 'Kept' );

		$before = $lightweight_seo_test_options;
		$cli    = new Lightweight_SEO_CLI();
		$file   = $this->temporary_file( '{"format":"something-else"}' );

		foreach ( array( '/no/such/file.json', $file ) as $path ) {
			try {
				$cli->settings_import( array( $path ), array( 'yes' => true ) );
				$this->fail( 'The import must stop with an error.' );
			} catch ( Lightweight_SEO_Test_CLI_Error $error ) {
				$this->assertNotSame( '', $error->getMessage() );
			}

			$this->assertSame( $before, $lightweight_seo_test_options );
		}
	}

	public function test_module_commands_set_the_switch_and_the_state_together(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'redirect_rules'   => '/old /new 301',
			'gtm_container_id' => 'GTM-ABC123',
		);

		$cli = new Lightweight_SEO_CLI();

		$cli->module_enable( array( 'redirects' ) );

		$this->assertSame( '1', $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]['enable_redirects'] );
		$this->assertTrue( $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ]['redirects'] );
		// A module is never switched on by what is configured for it.
		$this->assertFalse( $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ]['tracking'] );

		$cli->module_disable( array( 'redirects' ) );

		$this->assertSame( '0', $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]['enable_redirects'] );
		$this->assertSame( '/old /new 301', $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]['redirect_rules'] );
		$this->assertFalse( $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_MODULES_OPTION_NAME ]['redirects'] );

		WP_CLI::$output = array();
		$cli->module_list( array(), array() );

		$this->assertSame( array( 'redirects', 'hreflang', 'tracking', 'local-seo', 'ai', 'indexnow' ), array_column( WP_CLI::$output[0][1], 'module' ) );
		$this->assertSame( array( 'off' ), array_unique( array_column( WP_CLI::$output[0][1], 'status' ) ) );

		$this->expectException( Lightweight_SEO_Test_CLI_Error::class );
		$cli->module_enable( array( 'no-such-module' ) );
	}

	public function test_redirect_commands_import_a_csv_file_and_follow_a_path(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'redirect_rules' => '/kept /kept-target 301' );

		$cli = new Lightweight_SEO_CLI();

		$cli->redirects_import( array( $this->temporary_file( "from,to,status\n/old-blog/*,/blog/*,301\n/retired,,410\n" ) ) );

		$this->assertSame( "/kept /kept-target 301\n/old-blog/* /blog/* 301\n/retired 410", $lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ]['redirect_rules'] );
		$this->assertSame( array( 'success', '2 added, 0 replaced, 0 already saved, 0 left out.' ), WP_CLI::$output[0] );

		WP_CLI::$output = array();
		$cli->redirects_test( array( 'https://example.com/old-blog/2024/post/' ) );
		$cli->redirects_test( array( '/retired' ) );
		$cli->redirects_test( array( '/nothing' ) );

		$this->assertSame(
			array(
				'Rule /old-blog/*: to /blog/2024/post (301)',
				'Rule /retired: gone (410)',
				'No rule applies to /nothing.',
			),
			array_column( WP_CLI::$output, 1 )
		);
	}

	private function temporary_file( $contents = '' ) {
		$file = tempnam( sys_get_temp_dir(), 'lwseo' );

		file_put_contents( $file, $contents );

		$this->files[] = $file;

		return $file;
	}
}
