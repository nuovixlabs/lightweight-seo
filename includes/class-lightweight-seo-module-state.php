<?php
/**
 * Bounded module state storage for Lightweight SEO.
 *
 * @since 1.1.0
 * @package Lightweight_SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Reads and updates the small module enablement option.
 */
class Lightweight_SEO_Module_State {

	/** @var array|null */
	private $states;

	/**
	 * Keep module state in step with the switches saved on the settings screen.
	 */
	public function __construct() {
		add_action( 'update_option_' . LIGHTWEIGHT_SEO_OPTION_NAME, array( $this, 'sync_legacy_settings' ), 10, 2 );
	}

	/**
	 * Determine whether a module is enabled.
	 *
	 * @param string $module_id Module identifier.
	 * @return bool
	 */
	public function is_enabled( $module_id ) {
		$states = $this->get_all();

		return ! empty( $states[ sanitize_key( $module_id ) ] );
	}

	/**
	 * Return normalized module states.
	 *
	 * @return array
	 */
	public function get_all() {
		if ( null === $this->states ) {
			$this->states = self::normalize( get_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME, array() ) );
		}

		return $this->states;
	}

	/**
	 * Update one module state.
	 *
	 * @param string $module_id Module identifier.
	 * @param bool   $enabled   New state.
	 * @return bool
	 */
	public function set_enabled( $module_id, $enabled ) {
		$states                               = $this->get_all();
		$states[ sanitize_key( $module_id ) ] = (bool) $enabled;
		$this->states                         = self::normalize( $states );

		return update_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME, $this->states );
	}

	/**
	 * Follow the module switches whenever the settings are saved.
	 *
	 * @param mixed $old_value Previous settings.
	 * @param mixed $new_value Updated settings.
	 * @return void
	 */
	public function sync_legacy_settings( $old_value, $new_value ) {
		if ( ! is_array( $new_value ) ) {
			return;
		}

		$states       = $this->get_all();
		$derived      = self::derive_from_legacy_settings( $new_value );
		$this->states = array_merge( $states, $derived );
		$this->states = self::normalize( $this->states );

		update_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME, $this->states );
	}

	/**
	 * Derive module states from the settings without exposing those settings.
	 *
	 * Every module follows its own switch. Settings saved before Redirects and Tracking had
	 * one carry no switch for them, so those two fall back to what was configured.
	 *
	 * @param array $settings Stored settings.
	 * @return array
	 */
	public static function derive_from_legacy_settings( $settings ) {
		$redirect_rules = trim( (string) ( $settings['redirect_rules'] ?? '' ) );

		return array(
			'redirects' => isset( $settings['enable_redirects'] )
				? '1' === (string) $settings['enable_redirects']
				: '1' === (string) ( $settings['enable_auto_redirects'] ?? '' ) || '' !== $redirect_rules,
			'hreflang'  => '1' === (string) ( $settings['enable_hreflang_output'] ?? '' ),
			'tracking'  => isset( $settings['enable_tracking'] )
				? '1' === (string) $settings['enable_tracking']
				: self::has_tracking_identifier( $settings ),
			'local-seo' => '1' === (string) ( $settings['enable_local_business_schema'] ?? '' ),
			'ai'        => '1' === (string) ( $settings['enable_ai_discovery'] ?? '' ),
		);
	}

	/**
	 * Normalize option values to a bounded boolean map.
	 *
	 * @param mixed $states Raw option value.
	 * @return array
	 */
	public static function normalize( $states ) {
		$normalized = array();
		$states     = is_array( $states ) ? $states : array();

		foreach ( array( 'redirects', 'hreflang', 'tracking', 'local-seo', 'ai' ) as $module_id ) {
			$normalized[ $module_id ] = ! empty( $states[ $module_id ] );
		}

		return $normalized;
	}

	/**
	 * Check only recognized tracking identifier formats.
	 *
	 * @param array $settings Legacy settings.
	 * @return bool
	 */
	private static function has_tracking_identifier( $settings ) {
		return 1 === preg_match( '/^G-[A-Z0-9]+$/i', (string) ( $settings['ga4_measurement_id'] ?? '' ) )
			|| 1 === preg_match( '/^GTM-[A-Z0-9]+$/i', (string) ( $settings['gtm_container_id'] ?? '' ) )
			|| 1 === preg_match( '/^[0-9]+$/', (string) ( $settings['facebook_pixel_id'] ?? '' ) );
	}
}
