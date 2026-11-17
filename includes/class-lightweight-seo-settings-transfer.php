<?php
/**
 * Settings export and import for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Moves the plugin settings between sites as one JSON file.
 *
 * Loaded only for an export, import, or undo request from the Tools tab or
 * WP-CLI. Titles, descriptions, and other fields saved on pages, terms, and
 * authors are never part of a file.
 */
class Lightweight_SEO_Settings_Transfer {

	/** Marker that identifies a settings file. */
	const FORMAT = 'lightweight-seo-settings';

	/** Version of the file layout. */
	const FORMAT_VERSION = 1;

	/** Option that keeps the settings an import replaced, until the next import or an undo. */
	const BACKUP_OPTION = 'lightweight_seo_settings_backup';

	/** Largest file that is read. */
	const MAX_FILE_BYTES = 1048576;

	/**
	 * Whether an undo is writing its saved values back.
	 *
	 * @var bool
	 */
	private static $restoring = false;

	/**
	 * Shared settings service.
	 *
	 * @var Lightweight_SEO_Settings
	 */
	private $settings;

	/**
	 * Initialize the service.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings    $settings    Shared settings service.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Say whether an undo is in progress, so its values are stored exactly as they were kept.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public static function is_restoring() {
		return self::$restoring;
	}

	/**
	 * Name the settings that stay on the site they were made on.
	 *
	 * Credentials, data kept from retired features, and the IndexNow key of the site
	 * are never exported, and an import never changes them.
	 *
	 * @since    1.1.0
	 * @return   string[]
	 */
	public static function get_local_keys() {
		return array_merge(
			Lightweight_SEO_Data_Registry::get_ephemeral_setting_keys(),
			array( 'meta_keywords', 'import_source', 'indexnow_key' )
		);
	}

	/**
	 * Build the contents of a settings file.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	public function export() {
		$settings = array_diff_key( $this->settings->get_all(), array_flip( self::get_local_keys() ) );

		ksort( $settings );

		return array(
			'format'         => self::FORMAT,
			'format_version' => self::FORMAT_VERSION,
			'plugin_version' => LIGHTWEIGHT_SEO_VERSION,
			'schema_version' => LIGHTWEIGHT_SEO_SCHEMA_VERSION,
			'site_url'       => home_url( '/' ),
			'exported_at'    => gmdate( 'c' ),
			'settings'       => $settings,
			'modules'        => Lightweight_SEO_Module_State::normalize( get_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME, array() ) ),
		);
	}

	/**
	 * Encode a settings file.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	public function export_json() {
		return (string) wp_json_encode( $this->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Replace the settings of this site with those of a settings file.
	 *
	 * Every value passes the checks of the settings screen. A value the file does
	 * not hold, or one that fails a check, is set to its default. The settings in
	 * place before the import are kept for one undo.
	 *
	 * @since    1.1.0
	 * @param    string    $json    Contents of a settings file.
	 * @return   array{ok:bool,message:string,notes:string[],rejected:string[]}
	 */
	public function import( $json ) {
		$data = json_decode( (string) $json, true );

		if ( ! is_array( $data ) || self::FORMAT !== ( $data['format'] ?? '' ) || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return $this->result( false, __( 'This is not a Lightweight SEO settings file. Nothing was changed.', 'lightweight-seo' ) );
		}

		if ( (int) ( $data['format_version'] ?? 0 ) > self::FORMAT_VERSION || (int) ( $data['schema_version'] ?? 0 ) > LIGHTWEIGHT_SEO_SCHEMA_VERSION ) {
			return $this->result( false, __( 'This settings file was made by a newer version of Lightweight SEO. Update the plugin on this site first. Nothing was changed.', 'lightweight-seo' ) );
		}

		$notes    = array();
		$incoming = array_diff_key( $data['settings'], array_flip( self::get_local_keys() ) );

		if ( untrailingslashit( (string) ( $data['site_url'] ?? '' ) ) !== untrailingslashit( home_url( '/' ) ) ) {
			$incoming = $this->remove_site_bound_values( $incoming );
			$notes[]  = __( 'The file comes from another site, so image IDs, the llms.txt page list, and translations tied to a page were left out. Image addresses were kept.', 'lightweight-seo' );
		}

		$current   = (array) get_option( LIGHTWEIGHT_SEO_OPTION_NAME, array() );
		$known     = count( get_settings_errors( LIGHTWEIGHT_SEO_OPTION_NAME ) );
		$validated = $this->get_validator()->validate_settings( $incoming );

		$rejected = array();

		foreach ( array_slice( get_settings_errors( LIGHTWEIGHT_SEO_OPTION_NAME ), $known ) as $error ) {
			$rejected[] = wp_strip_all_tags( (string) ( $error['message'] ?? '' ) );
		}

		// What never leaves a site stays as it is on this one. The file's own values for it were removed above.
		$validated = array_merge( $validated, array_intersect_key( $current, array_flip( self::get_local_keys() ) ) );

		update_option(
			self::BACKUP_OPTION,
			array(
				'settings' => $current,
				'modules'  => get_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME, array() ),
				'saved_at' => time(),
			),
			false
		);

		update_option( LIGHTWEIGHT_SEO_OPTION_NAME, $validated );
		update_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME, Lightweight_SEO_Module_State::normalize( Lightweight_SEO_Module_State::derive_from_legacy_settings( $validated ) ) );

		return $this->result( true, __( 'Settings imported. The settings that were in place before are kept, so the import can be undone.', 'lightweight-seo' ), $notes, $rejected );
	}

	/**
	 * Say when the settings kept for an undo were saved.
	 *
	 * @since    1.1.0
	 * @return   int Unix time, or 0 when there is nothing to undo.
	 */
	public function get_undo_time() {
		$backup = get_option( self::BACKUP_OPTION, array() );

		return is_array( $backup ) && isset( $backup['settings'] ) && is_array( $backup['settings'] ) ? (int) ( $backup['saved_at'] ?? 0 ) : 0;
	}

	/**
	 * Put back the settings the last import replaced, exactly as they were stored.
	 *
	 * @since    1.1.0
	 * @return   array{ok:bool,message:string,notes:string[],rejected:string[]}
	 */
	public function undo() {
		$backup = get_option( self::BACKUP_OPTION, array() );

		if ( ! is_array( $backup ) || ! isset( $backup['settings'] ) || ! is_array( $backup['settings'] ) ) {
			return $this->result( false, __( 'There is no import to undo.', 'lightweight-seo' ) );
		}

		self::$restoring = true;

		update_option( LIGHTWEIGHT_SEO_OPTION_NAME, $backup['settings'] );
		update_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME, Lightweight_SEO_Module_State::normalize( $backup['modules'] ?? array() ) );

		self::$restoring = false;

		delete_option( self::BACKUP_OPTION );

		return $this->result( true, __( 'The last import was undone. The settings are as they were before it.', 'lightweight-seo' ) );
	}

	/**
	 * Remove values that point at media or pages of the site the file came from.
	 *
	 * @since    1.1.0
	 * @param    array    $settings    Settings from the file.
	 * @return   array
	 */
	private function remove_site_bound_values( $settings ) {
		unset( $settings['social_image_id'], $settings['identity_logo_id'], $settings['llms_txt_post_ids'] );

		if ( isset( $settings['hreflang_mappings'] ) ) {
			// A mapping line that starts with "post:12", "term:3", or "user:1" names an item by its ID.
			$lines = preg_split( "/\r\n|\n|\r/", (string) $settings['hreflang_mappings'] );
			$lines = preg_grep( '/^\s*(post|term|user):\d+\s/i', $lines, PREG_GREP_INVERT );

			$settings['hreflang_mappings'] = implode( "\n", $lines );
		}

		return $settings;
	}

	/**
	 * Get the checks of the settings screen, measured against the defaults.
	 *
	 * The settings screen keeps the stored value of anything a form does not send
	 * or that fails a check. An import replaces everything, so those values fall
	 * back to the defaults, not to what this site holds.
	 *
	 * @since    1.1.0
	 * @return   Lightweight_SEO_Admin_Settings
	 */
	private function get_validator() {
		if ( ! function_exists( 'add_settings_error' ) ) {
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}

		if ( ! class_exists( 'Lightweight_SEO_Admin_Settings', false ) ) {
			require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-admin-settings.php';
		}

		$defaults = new class() extends Lightweight_SEO_Settings {
			/**
			 * Report the defaults as the stored settings.
			 *
			 * @return array
			 */
			public function get_all() {
				return $this->get_defaults();
			}
		};

		return new Lightweight_SEO_Admin_Settings( 'lightweight-seo', $defaults, null, null, null );
	}

	/**
	 * Shape an outcome.
	 *
	 * @since    1.1.0
	 * @param    bool        $ok         Whether the request was carried out.
	 * @param    string      $message    What happened.
	 * @param    string[]    $notes      Optional. What was left out on purpose.
	 * @param    string[]    $rejected   Optional. What the settings checks did not accept; those values are at their defaults.
	 * @return   array{ok:bool,message:string,notes:string[],rejected:string[]}
	 */
	private function result( $ok, $message, $notes = array(), $rejected = array() ) {
		return array(
			'ok'       => (bool) $ok,
			'message'  => $message,
			'notes'    => array_values( array_filter( $notes ) ),
			'rejected' => array_values( array_filter( $rejected ) ),
		);
	}
}
