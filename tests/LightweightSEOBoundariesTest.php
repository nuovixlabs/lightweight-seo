<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/fixtures/wp-cli-stub.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-module-state.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-module-registry.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-data-registry.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-cli.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo.php';

/**
 * The product rules of docs/architecture.md, checked against the shipped source.
 *
 * Each test reads the code a release contains. A failure means a rule was broken
 * or an exception was added: either undo the change, or agree the exception and
 * extend the list in this file together with the documentation.
 */
final class LightweightSEOBoundariesTest extends TestCase {

	/** Functions that send a request to another server. */
	const REMOTE_FUNCTIONS = array(
		'wp_remote_get',
		'wp_remote_post',
		'wp_remote_head',
		'wp_remote_request',
		'wp_safe_remote_get',
		'wp_safe_remote_post',
		'wp_safe_remote_head',
		'wp_safe_remote_request',
		'download_url',
		'curl_init',
		'curl_exec',
		'fsockopen',
		'pfsockopen',
		'stream_socket_client',
	);

	/** The only files that may send one: the IndexNow module, which is off by default. */
	const REMOTE_FILES = array( 'includes/class-lightweight-seo-indexnow-module.php' );

	/** The only files that may enqueue a script or a style: administration screens. */
	const ASSET_FILES = array( 'includes/class-lightweight-seo-admin.php' );

	/** @var array<string, array> */
	private static $tokens = array();

	public function test_only_the_indexnow_module_sends_a_remote_request(): void {
		$this->assertSame(
			self::REMOTE_FILES,
			$this->get_files_calling( self::REMOTE_FUNCTIONS ),
			'Core makes no remote request. Found: ' . $this->describe_calls( self::REMOTE_FUNCTIONS )
		);
	}

	public function test_no_recurring_event_is_scheduled(): void {
		$this->assertSame(
			array(),
			$this->get_files_calling( array( 'wp_schedule_event' ) ),
			'The plugin schedules no recurring job. Found: ' . $this->describe_calls( array( 'wp_schedule_event' ) )
		);
	}

	public function test_no_custom_table_is_created(): void {
		$this->assertSame(
			array(),
			$this->get_files_calling( array( 'dbdelta' ) ),
			'The plugin creates no custom table. Found: ' . $this->describe_calls( array( 'dbdelta' ) )
		);

		$files = array();

		foreach ( self::get_shipped_files() as $file ) {
			foreach ( $this->get_tokens( $file ) as $token ) {
				if ( in_array( $token[0], array( 'T_CONSTANT_ENCAPSED_STRING', 'T_ENCAPSED_AND_WHITESPACE' ), true ) && 1 === preg_match( '/\bCREATE\s+TABLE\b/i', $token[1] ) ) {
					$files[] = $file . ':' . $token[2];
				}
			}
		}

		$this->assertSame( array(), $files, 'The plugin creates no custom table.' );
	}

	public function test_only_administration_screens_enqueue_assets(): void {
		$functions = array( 'wp_enqueue_script', 'wp_enqueue_style', 'wp_register_script', 'wp_register_style' );

		$this->assertSame(
			self::ASSET_FILES,
			$this->get_files_calling( $functions ),
			'Core adds no CSS or JavaScript to a visitor request. Found: ' . $this->describe_calls( $functions )
		);
	}

	public function test_every_option_the_plugin_writes_is_in_the_data_registry(): void {
		$registered = array_merge(
			Lightweight_SEO_Data_Registry::get_persistent_options(),
			Lightweight_SEO_Data_Registry::get_ephemeral_options()
		);
		$problems   = array();

		foreach ( self::get_shipped_files() as $file ) {
			foreach ( $this->get_calls( $file, array( 'add_option', 'update_option' ) ) as $call ) {
				$option = $this->resolve_first_argument( $file, $call['index'] );

				if ( null === $option ) {
					$problems[] = $file . ':' . $call['line'] . ' names its option in a way this test cannot read; use a string or a constant';
				} elseif ( ! in_array( $option, $registered, true ) ) {
					$problems[] = $file . ':' . $call['line'] . ' writes ' . $option . ', which uninstall would leave behind';
				}
			}
		}

		$this->assertSame( array(), $problems, 'Every option belongs in Lightweight_SEO_Data_Registry.' );
	}

	public function test_the_module_lists_agree(): void {
		$state    = array_keys( Lightweight_SEO_Module_State::normalize( array() ) );
		$derived  = array_keys( Lightweight_SEO_Module_State::derive_from_legacy_settings( array() ) );
		$commands = array_keys( Lightweight_SEO_CLI::MODULE_SWITCHES );

		$plugin   = ( new ReflectionClass( 'Lightweight_SEO' ) )->newInstanceWithoutConstructor();
		$register = new ReflectionMethod( 'Lightweight_SEO', 'register_builtin_modules' );
		$registry = new Lightweight_SEO_Module_Registry( new Lightweight_SEO_Module_State() );

		$register->setAccessible( true );
		$register->invoke( $plugin, $registry );

		$registered = array_keys( $registry->get_public_modules() );

		sort( $state );
		sort( $derived );
		sort( $commands );
		sort( $registered );

		$this->assertNotEmpty( $registered );
		$this->assertSame( $registered, $state, 'Lightweight_SEO_Module_State::normalize() and the registered modules differ.' );
		$this->assertSame( $registered, $derived, 'Lightweight_SEO_Module_State::derive_from_legacy_settings() and the registered modules differ.' );
		$this->assertSame( $registered, $commands, 'Lightweight_SEO_CLI::MODULE_SWITCHES and the registered modules differ.' );
	}

	public function test_every_module_is_off_on_a_new_install(): void {
		$defaults = ( new Lightweight_SEO_Settings() )->get_defaults();

		foreach ( Lightweight_SEO_CLI::MODULE_SWITCHES as $module_id => $setting ) {
			$this->assertArrayHasKey( $setting, $defaults, 'The switch of the ' . $module_id . ' module has no default.' );
			$this->assertSame( '0', $defaults[ $setting ], 'The ' . $module_id . ' module must be off on a new install.' );
		}

		$this->assertSame(
			array( false ),
			array_values( array_unique( Lightweight_SEO_Module_State::derive_from_legacy_settings( $defaults ) ) ),
			'The default settings must leave every module off.'
		);
	}

	/**
	 * List the PHP files a release contains.
	 *
	 * @return string[] Paths relative to the plugin root.
	 */
	private static function get_shipped_files() {
		$root  = dirname( __DIR__ );
		$files = array( 'lightweight-seo.php', 'uninstall.php' );

		foreach ( array( 'includes', 'admin' ) as $directory ) {
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $directory, FilesystemIterator::SKIP_DOTS ) );

			foreach ( $iterator as $entry ) {
				if ( 'php' === $entry->getExtension() ) {
					$files[] = substr( $entry->getPathname(), strlen( $root ) + 1 );
				}
			}
		}

		sort( $files );

		return $files;
	}

	/**
	 * Read a file as tokens, without whitespace and comments.
	 *
	 * @param string $file Path relative to the plugin root.
	 * @return array[] Token name, text, and line of each token.
	 */
	private function get_tokens( $file ) {
		if ( ! isset( self::$tokens[ $file ] ) ) {
			$tokens = array();
			$line   = 0;

			foreach ( token_get_all( (string) file_get_contents( dirname( __DIR__ ) . '/' . $file ) ) as $token ) {
				if ( ! is_array( $token ) ) {
					$tokens[] = array( $token, $token, $line );
					continue;
				}

				$line = $token[2];

				if ( ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
					$tokens[] = array( token_name( $token[0] ), $token[1], $line );
				}
			}

			self::$tokens[ $file ] = $tokens;
		}

		return self::$tokens[ $file ];
	}

	/**
	 * Find the calls of global functions in a file. Methods of the same name are not calls of them.
	 *
	 * @param string   $file      Path relative to the plugin root.
	 * @param string[] $functions Lowercase function names.
	 * @return array[] Name, line, and token index of each call.
	 */
	private function get_calls( $file, array $functions ) {
		$tokens = $this->get_tokens( $file );
		$calls  = array();

		foreach ( $tokens as $index => $token ) {
			if ( ! in_array( $token[0], array( 'T_STRING', 'T_NAME_FULLY_QUALIFIED' ), true ) ) {
				continue;
			}

			$name = strtolower( ltrim( $token[1], '\\' ) );

			if ( ! in_array( $name, $functions, true ) || '(' !== ( $tokens[ $index + 1 ][0] ?? '' ) ) {
				continue;
			}

			if ( in_array( $tokens[ $index - 1 ][0] ?? '', array( 'T_OBJECT_OPERATOR', 'T_NULLSAFE_OBJECT_OPERATOR', 'T_DOUBLE_COLON', 'T_FUNCTION', 'T_NEW' ), true ) ) {
				continue;
			}

			$calls[] = array(
				'name'  => $name,
				'line'  => $token[2],
				'index' => $index,
			);
		}

		return $calls;
	}

	/**
	 * List the shipped files that call one of the functions.
	 *
	 * @param string[] $functions Lowercase function names.
	 * @return string[]
	 */
	private function get_files_calling( array $functions ) {
		$files = array();

		foreach ( self::get_shipped_files() as $file ) {
			if ( array() !== $this->get_calls( $file, $functions ) ) {
				$files[] = $file;
			}
		}

		return $files;
	}

	/**
	 * Describe every call of the functions for a failure message.
	 *
	 * @param string[] $functions Lowercase function names.
	 * @return string
	 */
	private function describe_calls( array $functions ) {
		$found = array();

		foreach ( self::get_shipped_files() as $file ) {
			foreach ( $this->get_calls( $file, $functions ) as $call ) {
				$found[] = $call['name'] . '() in ' . $file . ':' . $call['line'];
			}
		}

		return array() === $found ? 'nothing' : implode( ', ', $found );
	}

	/**
	 * Read the first argument of a call when it is a string, a constant, or a constant of its own class.
	 *
	 * @param string $file  Path relative to the plugin root.
	 * @param int    $index Token index of the function name.
	 * @return string|null Value, or null when it cannot be read from the source.
	 */
	private function resolve_first_argument( $file, $index ) {
		$tokens   = $this->get_tokens( $file );
		$argument = $tokens[ $index + 2 ] ?? array( '', '', 0 );
		$next     = $tokens[ $index + 3 ] ?? array( '', '', 0 );

		if ( 'T_CONSTANT_ENCAPSED_STRING' === $argument[0] && in_array( $next[0], array( ',', ')' ), true ) ) {
			return trim( $argument[1], '\'"' );
		}

		if ( 'T_STRING' !== $argument[0] ) {
			return null;
		}

		if ( 'self' === strtolower( $argument[1] ) && 'T_DOUBLE_COLON' === $next[0] ) {
			$constant = $tokens[ $index + 4 ][1] ?? '';
			$source   = (string) file_get_contents( dirname( __DIR__ ) . '/' . $file );

			return 1 === preg_match( '/\bconst\s+' . preg_quote( $constant, '/' ) . '\s*=\s*\'([^\']+)\'\s*;/', $source, $match ) ? $match[1] : null;
		}

		return in_array( $next[0], array( ',', ')' ), true ) && defined( $argument[1] ) ? (string) constant( $argument[1] ) : null;
	}
}
