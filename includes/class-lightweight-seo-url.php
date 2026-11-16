<?php
/**
 * URL helpers for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Stateless URL helpers shared by core services and modules.
 *
 * Request paths and redirect rules are host-absolute (they include the
 * subdirectory of a subdirectory install), so they must be joined to the site
 * origin rather than passed to home_url(), which would repeat the subdirectory.
 */
final class Lightweight_SEO_Url {

	/**
	 * Get the scheme, host, and optional port of the site without a path.
	 *
	 * @return string
	 */
	public static function origin() {
		$home   = home_url( '/' );
		$host   = (string) wp_parse_url( $home, PHP_URL_HOST );
		$scheme = (string) wp_parse_url( $home, PHP_URL_SCHEME );
		$port   = wp_parse_url( $home, PHP_URL_PORT );

		if ( '' === $host ) {
			return untrailingslashit( $home );
		}

		return ( '' !== $scheme ? $scheme : 'https' ) . '://' . $host . ( $port ? ':' . (int) $port : '' );
	}

	/**
	 * Build an absolute URL from a host-absolute path.
	 *
	 * @param string $path Host-absolute path such as /blog/sample-page/.
	 * @return string
	 */
	public static function from_path( $path ) {
		return self::origin() . '/' . ltrim( (string) $path, '/' );
	}
}
