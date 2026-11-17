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

	/** Variables that often have no value, and then leave no trace: not themselves, and not a separator beside them. */
	const OPTIONAL_TOKENS = array( '%page%', '%category%', '%author%', '%year%' );

	/** What counts as a separator beside an empty variable: %sep%, or one of the characters it can become. */
	const SEPARATOR_PATTERN = '(?:%sep%|[–—\\-|·•»\/])';

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

	/**
	 * Replace title variables such as %title% in a format or a custom value.
	 *
	 * @param mixed $template Text that may contain variables.
	 * @param array $tokens   Variables and their values, keyed by the variable including its percent signs.
	 * @return string
	 */
	public static function resolve_tokens( $template, $tokens ) {
		$template = (string) $template;

		foreach ( self::OPTIONAL_TOKENS as $token ) {
			if ( false !== strpos( $template, $token ) && '' === (string) ( $tokens[ $token ] ?? '' ) ) {
				$template = self::remove_token( $template, $token );
			}
		}

		return str_replace( array_keys( $tokens ), array_values( $tokens ), $template );
	}

	/**
	 * Take an empty variable out of a format together with one separator beside it.
	 *
	 * The separator before the variable goes with it, or the one after it when the
	 * variable comes first, so "%title% %sep% %page% %sep% %sitename%" reads as
	 * "%title% %sep% %sitename%" on the first page.
	 *
	 * @param string $template Format or custom value.
	 * @param string $token    Variable to remove, including its percent signs.
	 * @return string
	 */
	private static function remove_token( $template, $token ) {
		$quoted  = preg_quote( $token, '/' );
		$removed = preg_replace(
			array(
				'/\s+' . self::SEPARATOR_PATTERN . '\s+' . $quoted . '/u',
				'/' . $quoted . '\s+' . self::SEPARATOR_PATTERN . '\s+/u',
				'/' . $quoted . '/u',
			),
			'',
			$template
		);

		// A value that is not valid UTF-8 cannot be matched; leave only the bare variable out of it.
		return null === $removed ? str_replace( $token, '', $template ) : $removed;
	}

	/**
	 * Sanitize one line of text that may hold title variables, such as a title format.
	 *
	 * WordPress removes anything shaped like a percent-encoded byte, which would cut
	 * "%ca" out of "%category%". Variables are set aside while the text is sanitized.
	 *
	 * @param mixed $value Submitted text.
	 * @return string
	 */
	public static function sanitize_template( $value ) {
		return self::sanitize_keeping_variables( $value, 'sanitize_text_field' );
	}

	/**
	 * Sanitize text of several lines that may hold title variables, such as a description.
	 *
	 * @param mixed $value Submitted text.
	 * @return string
	 */
	public static function sanitize_textarea_template( $value ) {
		return self::sanitize_keeping_variables( $value, 'sanitize_textarea_field' );
	}

	/**
	 * Run a WordPress text sanitizer without letting it damage title variables.
	 *
	 * Only text shaped like a variable, letters between two percent signs, is set
	 * aside. Real percent-encoded bytes such as %3C are still removed.
	 *
	 * @param mixed    $value     Submitted text.
	 * @param callable $sanitizer WordPress sanitizer to apply.
	 * @return string
	 */
	private static function sanitize_keeping_variables( $value, $sanitizer ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		$held  = preg_replace( '/%([a-z_]+)%/', '{{lwseo-variable:$1}}', $value );
		$clean = (string) call_user_func( $sanitizer, null === $held ? $value : $held );

		return (string) preg_replace( '/\{\{lwseo-variable:([a-z_]+)\}\}/', '%$1%', $clean );
	}

	/**
	 * Reduce content to the plain, single-spaced text an administration preview shows.
	 *
	 * @param mixed $value Text that may contain markup or shortcodes.
	 * @return string
	 */
	public static function plain( $value ) {
		$value = wp_strip_all_tags( strip_shortcodes( (string) $value ), true );

		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}
}
