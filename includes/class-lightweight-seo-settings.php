<?php
/**
 * Shared settings service for Lightweight SEO.
 *
 * @since      1.0.2
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Shared settings service.
 */
class Lightweight_SEO_Settings {

	/**
	 * Cached settings for the current request.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      array|null    $settings
	 */
	private $settings;

	/**
	 * Get default plugin settings.
	 *
	 * @since    1.0.2
	 * @return   array
	 */
	public function get_defaults() {
		return array(
			'title_format'         => LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT,
			'meta_description'     => get_bloginfo( 'description' ),
			'meta_keywords'        => '',
			'enable_meta_keywords' => '1',
			'social_image'         => '',
			'social_image_id'      => 0,
			'ga4_measurement_id'   => '',
			'gtm_container_id'     => '',
			'facebook_pixel_id'    => '',
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @since    1.0.2
	 * @return   array
	 */
	public function get_all() {
		if ( null === $this->settings ) {
			$this->settings = wp_parse_args( get_option( LIGHTWEIGHT_SEO_OPTION_NAME, array() ), $this->get_defaults() );
		}

		return $this->settings;
	}

	/**
	 * Get a single setting value.
	 *
	 * @since    1.0.2
	 * @param    string    $key         Setting key.
	 * @param    mixed     $fallback    Fallback value.
	 * @return   mixed
	 */
	public function get( $key, $fallback = null ) {
		$settings = $this->get_all();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $fallback;
	}

	/**
	 * Get the decoded title format.
	 *
	 * @since    1.0.2
	 * @return   string
	 */
	public function get_title_format() {
		return wp_specialchars_decode( $this->get( 'title_format', LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT ), ENT_QUOTES );
	}

	/**
	 * Determine whether meta keywords output is enabled.
	 *
	 * @since    1.0.2
	 * @return   bool
	 */
	public function meta_keywords_enabled() {
		return '1' === (string) $this->get( 'enable_meta_keywords', '1' );
	}

	/**
	 * Get the global social image URL.
	 *
	 * @since    1.0.2
	 * @return   string
	 */
	public function get_social_image_url() {
		$image_url = $this->get( 'social_image', '' );
		$image_id  = absint( $this->get( 'social_image_id', 0 ) );

		if ( $image_id ) {
			$attachment_url = wp_get_attachment_image_url( $image_id, 'full' );

			if ( ! empty( $attachment_url ) ) {
				if ( ! empty( $image_url ) && $image_url !== $attachment_url ) {
					return $image_url;
				}

				return $attachment_url;
			}
		}

		return $image_url;
	}
}
