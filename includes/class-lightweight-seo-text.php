<?php
/**
 * Text helpers for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Stateless, multibyte-safe text helpers shared by output, API, and editor code.
 */
final class Lightweight_SEO_Text {

	/** Characters kept when a description is derived from content instead of written by an editor. */
	const SUMMARY_LENGTH = 160;

	/**
	 * Count characters rather than bytes so non-Latin text is measured correctly.
	 *
	 * @param mixed $value Text to measure.
	 * @return int
	 */
	public static function length( $value ) {
		$value = (string) $value;

		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/**
	 * Shorten already-normalized plain text to a search-snippet sized summary.
	 *
	 * @param mixed $value Plain text without markup.
	 * @param int   $limit Maximum number of characters before the ellipsis.
	 * @return string
	 */
	public static function summarize( $value, $limit = self::SUMMARY_LENGTH ) {
		$text  = trim( (string) $value );
		$limit = max( 1, (int) $limit );

		if ( self::length( $text ) <= $limit ) {
			return $text;
		}

		$multibyte = function_exists( 'mb_substr' ) && function_exists( 'mb_strrpos' );
		$summary   = $multibyte ? mb_substr( $text, 0, $limit, 'UTF-8' ) : substr( $text, 0, $limit );
		$boundary  = $multibyte ? mb_strrpos( $summary, ' ', 0, 'UTF-8' ) : strrpos( $summary, ' ' );

		// Break on a word unless that would discard most of the summary (unspaced scripts).
		if ( false !== $boundary && $boundary >= $limit / 2 ) {
			$summary = $multibyte ? mb_substr( $summary, 0, $boundary, 'UTF-8' ) : substr( $summary, 0, $boundary );
		}

		// Only single-byte characters are trimmed so a multibyte character is never split.
		return rtrim( $summary, " \t\n\r.,;:-" ) . '…';
	}
}
