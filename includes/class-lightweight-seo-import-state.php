<?php
/**
 * Import progress for the Tools and migration tab.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Stores the selected import source, the batch cursor, and the last report.
 *
 * The state lives in its own non-autoloaded option. It is written by the tool
 * handlers on administrator requests only, so it has no place in the settings
 * option that every request autoloads and that the settings sanitizer rebuilds.
 */
class Lightweight_SEO_Import_State {

	/** Option holding the import source, cursor, and last report. */
	const OPTION_NAME = 'lightweight_seo_import_state';

	/**
	 * Get the current import state.
	 *
	 * @since    1.1.0
	 * @return   array {
	 *     @type string $source Selected import source, or an empty string.
	 *     @type int    $cursor Number of posts already scanned for that source.
	 *     @type string $report Human-readable result of the last tool action.
	 * }
	 */
	public function get() {
		$stored = get_option( self::OPTION_NAME, null );

		if ( ! is_array( $stored ) ) {
			$stored = $this->get_legacy_state();
		}

		return $this->normalize( $stored );
	}

	/**
	 * Persist the import state without autoloading it.
	 *
	 * @since    1.1.0
	 * @param    array    $state    Import source, cursor, and last report.
	 * @return   void
	 */
	public function save( $state ) {
		update_option( self::OPTION_NAME, $this->normalize( (array) $state ), false );
	}

	/**
	 * Coerce a stored or submitted state into its documented shape.
	 *
	 * @since    1.1.0
	 * @param    array    $state    Raw state.
	 * @return   array
	 */
	private function normalize( $state ) {
		return array(
			'source' => sanitize_key( $state['source'] ?? '' ),
			'cursor' => absint( $state['cursor'] ?? 0 ),
			'report' => (string) ( $state['report'] ?? '' ),
		);
	}

	/**
	 * Read the state that earlier builds kept inside the settings option.
	 *
	 * Used until the first tool action writes the dedicated option, so a site
	 * that was part-way through an import keeps its source and position.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function get_legacy_state() {
		$settings = (array) get_option( LIGHTWEIGHT_SEO_OPTION_NAME, array() );

		return array(
			'source' => $settings['import_source'] ?? '',
			'cursor' => $settings['import_cursor'] ?? 0,
			'report' => $settings['last_import_report'] ?? '',
		);
	}
}
