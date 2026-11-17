<?php
/**
 * WP-CLI commands for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Settings, module, and redirect commands under "wp lightweight-seo".
 *
 * Loaded only when WordPress runs under WP-CLI.
 */
class Lightweight_SEO_CLI {

	/** Setting that switches each module on or off. */
	const MODULE_SWITCHES = array(
		'redirects' => 'enable_redirects',
		'hreflang'  => 'enable_hreflang_output',
		'tracking'  => 'enable_tracking',
		'local-seo' => 'enable_local_business_schema',
		'ai'        => 'enable_ai_discovery',
	);

	/**
	 * Register every command.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public static function register() {
		$cli = new self();

		WP_CLI::add_command( 'lightweight-seo settings export', array( $cli, 'settings_export' ) );
		WP_CLI::add_command( 'lightweight-seo settings import', array( $cli, 'settings_import' ) );
		WP_CLI::add_command( 'lightweight-seo settings undo-import', array( $cli, 'settings_undo_import' ) );
		WP_CLI::add_command( 'lightweight-seo module list', array( $cli, 'module_list' ) );
		WP_CLI::add_command( 'lightweight-seo module enable', array( $cli, 'module_enable' ) );
		WP_CLI::add_command( 'lightweight-seo module disable', array( $cli, 'module_disable' ) );
		WP_CLI::add_command( 'lightweight-seo redirects import', array( $cli, 'redirects_import' ) );
		WP_CLI::add_command( 'lightweight-seo redirects test', array( $cli, 'redirects_test' ) );
	}

	/**
	 * Exports the plugin settings as JSON.
	 *
	 * Titles and descriptions saved on pages, credentials, and data kept from
	 * retired features are not part of the export.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Write to this file instead of the terminal.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lightweight-seo settings export --file=seo-settings.json
	 *
	 * @since    1.1.0
	 * @param    array    $args          Positional arguments.
	 * @param    array    $assoc_args    Named arguments.
	 * @return   void
	 */
	public function settings_export( $args, $assoc_args ) {
		$json = $this->get_transfer()->export_json();
		$file = (string) ( $assoc_args['file'] ?? '' );

		if ( '' === $file ) {
			WP_CLI::line( $json );

			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writes the file the person running the command named.
		if ( false === file_put_contents( $file, $json . "\n" ) ) {
			WP_CLI::error( sprintf( 'Could not write %s.', $file ) );
		}

		WP_CLI::success( sprintf( 'Settings written to %s.', $file ) );
	}

	/**
	 * Replaces the plugin settings with those of a settings file.
	 *
	 * Every value goes through the checks of the settings screen. A value the
	 * file does not hold, or one that fails a check, is set to its default. The
	 * settings in place before are kept for "settings undo-import".
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : A file made by "settings export" or by the Tools tab.
	 *
	 * [--yes]
	 * : Do not ask for confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lightweight-seo settings import seo-settings.json
	 *
	 * @since    1.1.0
	 * @param    array    $args          Positional arguments.
	 * @param    array    $assoc_args    Named arguments.
	 * @return   void
	 */
	public function settings_import( $args, $assoc_args ) {
		$file     = (string) ( $args[0] ?? '' );
		$transfer = $this->get_transfer();

		if ( ! is_readable( $file ) || filesize( $file ) > Lightweight_SEO_Settings_Transfer::MAX_FILE_BYTES ) {
			WP_CLI::error( sprintf( 'Could not read %s, or it is larger than 1 MB.', $file ) );
		}

		WP_CLI::confirm( 'Replace every Lightweight SEO setting on this site with the file?', $assoc_args );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the local file the person running the command named.
		$this->report( $transfer->import( (string) file_get_contents( $file ) ) );
	}

	/**
	 * Puts back the settings that the last import replaced.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Do not ask for confirmation.
	 *
	 * @since    1.1.0
	 * @subcommand undo-import
	 * @param    array    $args          Positional arguments.
	 * @param    array    $assoc_args    Named arguments.
	 * @return   void
	 */
	public function settings_undo_import( $args, $assoc_args ) {
		WP_CLI::confirm( 'Put back the settings as they were before the last import? Changes saved since then are lost.', $assoc_args );

		$this->report( $this->get_transfer()->undo() );
	}

	/**
	 * Lists the modules and whether each is on.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * @since    1.1.0
	 * @param    array    $args          Positional arguments.
	 * @param    array    $assoc_args    Named arguments.
	 * @return   void
	 */
	public function module_list( $args, $assoc_args ) {
		$states = Lightweight_SEO_Module_State::normalize( get_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME, array() ) );
		$rows   = array();

		foreach ( $states as $module => $enabled ) {
			$rows[] = array(
				'module' => $module,
				'status' => $enabled ? 'on' : 'off',
			);
		}

		WP_CLI\Utils\format_items( (string) ( $assoc_args['format'] ?? 'table' ), $rows, array( 'module', 'status' ) );
	}

	/**
	 * Switches a module on. Its saved settings are used as they are.
	 *
	 * ## OPTIONS
	 *
	 * <module>
	 * : One of: redirects, hreflang, tracking, local-seo, ai.
	 *
	 * @since    1.1.0
	 * @param    array    $args    Positional arguments.
	 * @return   void
	 */
	public function module_enable( $args ) {
		$this->set_module( (string) ( $args[0] ?? '' ), true );
	}

	/**
	 * Switches a module off. Its settings stay saved.
	 *
	 * ## OPTIONS
	 *
	 * <module>
	 * : One of: redirects, hreflang, tracking, local-seo, ai.
	 *
	 * @since    1.1.0
	 * @param    array    $args    Positional arguments.
	 * @return   void
	 */
	public function module_disable( $args ) {
		$this->set_module( (string) ( $args[0] ?? '' ), false );
	}

	/**
	 * Imports redirect rules from a CSV file.
	 *
	 * Each row holds the old path, the new path or address, and an optional
	 * status code; 410 needs no new path. A rule for a path that already has one
	 * replaces it.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The CSV file to read.
	 *
	 * @since    1.1.0
	 * @param    array    $args    Positional arguments.
	 * @return   void
	 */
	public function redirects_import( $args ) {
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-redirect-import.php';

		$file  = (string) ( $args[0] ?? '' );
		$lines = Lightweight_SEO_Redirect_Import::read_csv( $file );

		if ( null === $lines ) {
			WP_CLI::error( sprintf( 'Could not read %s.', $file ) );
		}

		$report = Lightweight_SEO_Redirect_Import::import( new Lightweight_SEO_Settings(), $lines );

		WP_CLI::success( sprintf( '%d added, %d replaced, %d already saved, %d left out.', $report['added'], $report['replaced'], $report['unchanged'], $report['skipped'] ) );
	}

	/**
	 * Shows which saved redirect rule applies to a path.
	 *
	 * Reads the saved rules only; the address is not requested.
	 *
	 * ## OPTIONS
	 *
	 * <path>
	 * : A path or an address on this site.
	 *
	 * @since    1.1.0
	 * @param    array    $args    Positional arguments.
	 * @return   void
	 */
	public function redirects_test( $args ) {
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-redirects-service.php';

		$path  = '/' . ltrim( (string) wp_parse_url( (string) ( $args[0] ?? '' ), PHP_URL_PATH ), '/' );
		$trace = ( new Lightweight_SEO_Redirects_Service( new Lightweight_SEO_Settings(), false ) )->trace_request( $path );

		if ( empty( $trace['hops'] ) ) {
			WP_CLI::line( sprintf( 'No rule applies to %s.', $path ) );

			return;
		}

		foreach ( $trace['hops'] as $hop ) {
			WP_CLI::line( 410 === (int) $hop['status'] ? sprintf( 'Rule %s: gone (410)', $hop['rule'] ) : sprintf( 'Rule %s: to %s (%d)', $hop['rule'], $hop['target'], (int) $hop['status'] ) );
		}

		if ( $trace['loop'] ) {
			WP_CLI::warning( 'These rules loop, so no redirect is sent.' );
		}
	}

	/**
	 * Store a module switch. The module state follows the stored setting.
	 *
	 * @since    1.1.0
	 * @param    string    $module     Module ID.
	 * @param    bool      $enabled    New state.
	 * @return   void
	 */
	private function set_module( $module, $enabled ) {
		if ( ! isset( self::MODULE_SWITCHES[ $module ] ) ) {
			WP_CLI::error( sprintf( 'Unknown module "%s". Use one of: %s.', $module, implode( ', ', array_keys( self::MODULE_SWITCHES ) ) ) );
		}

		$settings                                     = wp_parse_args( (array) get_option( LIGHTWEIGHT_SEO_OPTION_NAME, array() ), ( new Lightweight_SEO_Settings() )->get_defaults() );
		$settings[ self::MODULE_SWITCHES[ $module ] ] = $enabled ? '1' : '0';

		update_option( LIGHTWEIGHT_SEO_OPTION_NAME, $settings );
		update_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME, Lightweight_SEO_Module_State::normalize( Lightweight_SEO_Module_State::derive_from_legacy_settings( $settings ) ) );

		WP_CLI::success( sprintf( 'Module %s is %s.', $module, $enabled ? 'on' : 'off' ) );
	}

	/**
	 * Load the settings transfer service.
	 *
	 * @since    1.1.0
	 * @return   Lightweight_SEO_Settings_Transfer
	 */
	private function get_transfer() {
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-settings-transfer.php';

		return new Lightweight_SEO_Settings_Transfer( new Lightweight_SEO_Settings() );
	}

	/**
	 * Print the outcome of an import or an undo.
	 *
	 * @since    1.1.0
	 * @param    array    $result    Outcome from the settings transfer service.
	 * @return   void
	 */
	private function report( $result ) {
		foreach ( array_merge( $result['notes'], $result['rejected'] ) as $note ) {
			WP_CLI::warning( $note );
		}

		if ( $result['ok'] ) {
			WP_CLI::success( $result['message'] );
		} else {
			WP_CLI::error( $result['message'] );
		}
	}
}
