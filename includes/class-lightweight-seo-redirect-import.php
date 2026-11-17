<?php
/**
 * Redirect rule import for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Reads redirect rules from a CSV file and merges them into the manual rules.
 *
 * Loaded only for an import request from the settings screen or WP-CLI.
 */
class Lightweight_SEO_Redirect_Import {

	/** Largest file that is read. */
	const MAX_FILE_BYTES = 1048576;

	/** Most rows that are read from one file. */
	const MAX_ROWS = 5000;

	/**
	 * Read a CSV file into rule lines in the stored format.
	 *
	 * Each row holds the old path, the new path or address, and an optional status
	 * code. A first row that does not start with a path is a header and is ignored.
	 *
	 * @since    1.1.0
	 * @param    string    $path    File to read.
	 * @return   string[]|null Rule lines, or null when the file cannot be read.
	 */
	public static function read_csv( $path ) {
		$handle = is_readable( $path ) ? fopen( $path, 'r' ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Reads an uploaded or local file row by row; nothing is written.

		if ( false === $handle ) {
			return null;
		}

		$lines = array();
		$first = true;

		while ( count( $lines ) < self::MAX_ROWS ) {
			$row = fgetcsv( $handle, 0, ',', '"', '\\' );

			if ( false === $row ) {
				break;
			}

			$cells = array_map( array( __CLASS__, 'one_word' ), (array) $row );
			$cells = array_pad( $cells, 3, '' );

			if ( $first ) {
				$first = false;
				// Spreadsheet exports start with a byte order mark.
				$cells[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $cells[0] );

				if ( '' !== $cells[0] && 0 !== strpos( $cells[0], '/' ) ) {
					continue;
				}
			}

			if ( '' === implode( '', $cells ) ) {
				continue;
			}

			$lines[] = trim( $cells[0] . ' ' . $cells[1] . ' ' . $cells[2] );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the read handle opened above.

		return $lines;
	}

	/**
	 * Merge rule lines into the saved manual rules.
	 *
	 * An imported rule replaces a saved rule for the same path. Every line goes
	 * through the same checks as a rule typed on the settings screen.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings    $settings    Shared settings service.
	 * @param    string[]                    $lines       Rule lines in the stored format.
	 * @return   array{added:int,replaced:int,unchanged:int,skipped:int}
	 */
	public static function import( $settings, $lines ) {
		$stored   = (string) $settings->get( 'redirect_rules', '' );
		$before   = array_column( $settings->parse_redirect_rules( $stored ), null, 'source' );
		$incoming = array();

		foreach ( $lines as $line ) {
			foreach ( $settings->parse_redirect_rules( $line ) as $rule ) {
				$incoming[ $rule['source'] ] = $rule;
			}
		}

		$merged = $settings->normalize_redirect_rules_input( $stored . "\n" . implode( "\n", $lines ) );
		$after  = array_column( $settings->parse_redirect_rules( $merged ), null, 'source' );
		$report = array(
			'added'     => 0,
			'replaced'  => 0,
			'unchanged' => 0,
			'skipped'   => count( $lines ) - count( $incoming ),
		);

		foreach ( $incoming as $source => $rule ) {
			if ( ! isset( $after[ $source ] ) || $after[ $source ] !== $rule ) {
				// Left out by the loop check or the rule limit.
				++$report['skipped'];
			} elseif ( ! isset( $before[ $source ] ) ) {
				++$report['added'];
			} elseif ( $before[ $source ] !== $rule ) {
				++$report['replaced'];
			} else {
				++$report['unchanged'];
			}
		}

		if ( $merged !== $stored ) {
			$all                   = (array) get_option( LIGHTWEIGHT_SEO_OPTION_NAME, array() );
			$all['redirect_rules'] = $merged;

			update_option( LIGHTWEIGHT_SEO_OPTION_NAME, $all );
		}

		return $report;
	}

	/**
	 * Keep one cell as one word, because a stored rule is split on spaces.
	 *
	 * @since    1.1.0
	 * @param    mixed    $value    Cell value.
	 * @return   string
	 */
	private static function one_word( $value ) {
		return preg_replace( '/\s+/', '%20', trim( (string) $value ) );
	}
}
