<?php
/**
 * A stand-in for WP-CLI that records what a command prints.
 *
 * @package Lightweight_SEO
 */

namespace {

	/**
	 * Thrown where WP-CLI would end the process with an error.
	 */
	class Lightweight_SEO_Test_CLI_Error extends Exception {}

	/**
	 * Records commands and output.
	 */
	class WP_CLI {

		/** @var array */
		public static $commands = array();

		/** @var array */
		public static $output = array();

		/** @var int */
		public static $confirmations = 0;

		public static function add_command( $name, $callable ) {
			self::$commands[ $name ] = $callable;
		}

		public static function line( $message = '' ) {
			self::$output[] = array( 'line', $message );
		}

		public static function success( $message ) {
			self::$output[] = array( 'success', $message );
		}

		public static function warning( $message ) {
			self::$output[] = array( 'warning', $message );
		}

		public static function error( $message ) {
			self::$output[] = array( 'error', $message );

			throw new Lightweight_SEO_Test_CLI_Error( $message );
		}

		public static function confirm( $question, $assoc_args = array() ) {
			++self::$confirmations;
		}
	}
}

namespace WP_CLI\Utils {

	function format_items( $format, $items, $fields ) {
		\WP_CLI::$output[] = array( 'items', $items );
	}
}
