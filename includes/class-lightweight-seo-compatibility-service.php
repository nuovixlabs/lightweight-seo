<?php
/**
 * Compatibility and safe-mode service for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Compatibility service.
 */
class Lightweight_SEO_Compatibility_Service {

	/**
	 * Known plugin basenames and user-facing labels.
	 *
	 * @since    1.1.0
	 * @var      array
	 */
	private $known_plugins = array(
		'wordpress-seo/wp-seo.php'                    => 'Yoast SEO',
		'seo-by-rank-math/rank-math.php'              => 'Rank Math SEO',
		'all-in-one-seo-pack/all_in_one_seo_pack.php' => 'All in One SEO',
		'aioseo/aioseo.php'                           => 'All in One SEO',
	);

	/** Features normally owned by a full SEO plugin. */
	private $overlapping_features = array( 'title', 'meta', 'robots', 'canonical', 'schema' );

	/**
	 * Get detected SEO plugin conflicts.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	public function get_conflicting_plugins() {
		$known_plugins       = (array) apply_filters( 'lightweight_seo_compatibility_plugins', $this->known_plugins );
		$active_plugins      = (array) get_option( 'active_plugins', array() );
		$network_plugins     = is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array();
		$active_plugins      = array_unique( array_merge( $active_plugins, $network_plugins ) );
		$conflicting_plugins = array();

		foreach ( $known_plugins as $basename => $label ) {
			if ( in_array( $basename, $active_plugins, true ) ) {
				$conflicting_plugins[] = $label;
			}
		}

		if ( defined( 'WPSEO_VERSION' ) ) {
			$conflicting_plugins[] = 'Yoast SEO';
		}

		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			$conflicting_plugins[] = 'Rank Math SEO';
		}

		if ( defined( 'AIOSEO_VERSION' ) || defined( 'AIOSEO_PLUGIN_NAME' ) ) {
			$conflicting_plugins[] = 'All in One SEO';
		}

		return array_values( array_unique( $conflicting_plugins ) );
	}

	/**
	 * Determine whether safe mode should suppress frontend SEO head output.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function frontend_head_output_allowed() {
		return empty( $this->get_suppressed_features() );
	}

	/** Get the output features suppressed by active compatibility providers. */
	public function get_suppressed_features() {
		$features = empty( $this->get_conflicting_plugins() ) ? array() : $this->overlapping_features;

		return array_values( array_unique( array_map( 'sanitize_key', (array) apply_filters( 'lightweight_seo_suppressed_features', $features, $this->get_conflicting_plugins() ) ) ) );
	}

	/** Determine whether one frontend output feature remains available. */
	public function feature_output_allowed( $feature ) {
		return ! in_array( sanitize_key( $feature ), $this->get_suppressed_features(), true );
	}
}
