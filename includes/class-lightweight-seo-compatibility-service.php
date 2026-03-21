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

	/**
	 * Get detected SEO plugin conflicts.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	public function get_conflicting_plugins() {
		$active_plugins      = (array) get_option( 'active_plugins', array() );
		$conflicting_plugins = array();

		foreach ( $this->known_plugins as $basename => $label ) {
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
		return empty( $this->get_conflicting_plugins() );
	}
}
