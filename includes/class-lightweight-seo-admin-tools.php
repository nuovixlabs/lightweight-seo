<?php
/**
 * Administrator-initiated tool actions for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Handles the import, rollback, deletion, and export requests of the Tools tab.
 *
 * Every handler is an admin-post action that requires the manage_options
 * capability and its own nonce. Saving settings never reaches this class.
 */
class Lightweight_SEO_Admin_Tools {

	/** Admin-post action and nonce action for previewing the next import batch. */
	const ACTION_IMPORT_PREVIEW = 'lightweight_seo_import_preview';

	/** Admin-post action and nonce action for importing the next batch. */
	const ACTION_IMPORT_BATCH = 'lightweight_seo_import_batch';

	/** Admin-post action and nonce action for rolling back the latest batch. */
	const ACTION_IMPORT_ROLLBACK = 'lightweight_seo_import_rollback';

	/** Admin-post action and nonce action for deleting retained legacy 404 log entries. */
	const ACTION_DELETE_404_LOGS = 'lightweight_seo_delete_legacy_404_logs';

	/** Sources the importer can read. */
	const IMPORT_SOURCES = array( 'yoast', 'rank_math', 'aioseo' );

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * Metadata importer.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Importer_Service    $importer
	 */
	private $importer;

	/**
	 * Import source, cursor, and last report.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Import_State    $import_state
	 */
	private $import_state;

	/**
	 * Initialize the handlers.
	 *
	 * @since    1.1.0
	 * @param    string                              $plugin_name     The name of this plugin.
	 * @param    Lightweight_SEO_Importer_Service    $importer        Metadata importer.
	 * @param    Lightweight_SEO_Import_State        $import_state    Import source, cursor, and last report.
	 */
	public function __construct( $plugin_name, $importer, $import_state ) {
		$this->plugin_name  = $plugin_name;
		$this->importer     = $importer;
		$this->import_state = $import_state;
	}

	/**
	 * Get the request field that carries the nonce for one tool action.
	 *
	 * The import actions share one form, so each nonce needs its own field name.
	 *
	 * @since    1.1.0
	 * @param    string    $action    Tool action.
	 * @return   string
	 */
	public static function get_nonce_name( $action ) {
		return $action . '_nonce';
	}

	/**
	 * Preview the next batch for the submitted source without writing metadata.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function handle_import_preview() {
		$this->require_capability();
		check_admin_referer( self::ACTION_IMPORT_PREVIEW, self::get_nonce_name( self::ACTION_IMPORT_PREVIEW ) );

		$source = isset( $_POST['import_source'] ) ? sanitize_key( wp_unslash( $_POST['import_source'] ) ) : '';

		$this->run_batch( $source, false );
	}

	/**
	 * Import the next batch for the submitted source.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function handle_import_batch() {
		$this->require_capability();
		check_admin_referer( self::ACTION_IMPORT_BATCH, self::get_nonce_name( self::ACTION_IMPORT_BATCH ) );

		$source = isset( $_POST['import_source'] ) ? sanitize_key( wp_unslash( $_POST['import_source'] ) ) : '';

		$this->run_batch( $source, true );
	}

	/**
	 * Roll back the most recently imported batch.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function handle_import_rollback() {
		$this->require_capability();
		check_admin_referer( self::ACTION_IMPORT_ROLLBACK, self::get_nonce_name( self::ACTION_IMPORT_ROLLBACK ) );

		$state           = $this->import_state->get();
		$report          = $this->importer->rollback_last_batch();
		$state['cursor'] = absint( $report['offset'] ?? 0 );
		$state['report'] = sprintf(
			/* translators: 1: restored posts. 2: restored fields. */
			__( 'Rolled back the latest batch: restored %1$d posts and %2$d fields.', 'lightweight-seo' ),
			absint( $report['restored_posts'] ?? 0 ),
			absint( $report['restored_fields'] ?? 0 )
		);

		$this->import_state->save( $state );
		$this->finish( 'import_rollback', $state['report'], 'success' );
	}

	/**
	 * Permanently delete the 404 log entries retained from earlier releases.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function handle_delete_legacy_404_logs() {
		$this->require_capability();
		check_admin_referer( self::ACTION_DELETE_404_LOGS, self::get_nonce_name( self::ACTION_DELETE_404_LOGS ) );

		if ( empty( $_POST['confirm_delete_404_logs'] ) ) {
			$this->finish(
				'legacy_404_logs_unconfirmed',
				__( 'Tick the confirmation box before deleting the retained 404 log entries.', 'lightweight-seo' ),
				'error'
			);

			return;
		}

		$entries = count( (array) get_option( 'lightweight_seo_404_logs', array() ) );

		delete_option( 'lightweight_seo_404_logs' );

		$this->finish(
			'legacy_404_logs_deleted',
			sprintf(
				/* translators: %d: Number of deleted legacy 404 log entries. */
				__( 'Deleted %d retained legacy 404 log entries.', 'lightweight-seo' ),
				$entries
			),
			'success'
		);
	}

	/** Export preserved global and post-level legacy keyword values as CSV. */
	public function export_legacy_keywords() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to export this data.', 'lightweight-seo' ) );
		}

		check_admin_referer( 'lightweight_seo_export_legacy_keywords' );
		$settings = (array) get_option( LIGHTWEIGHT_SEO_OPTION_NAME, array() );
		$post_ids = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_lightweight_seo_keywords', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Explicit administrator export.
			)
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="lightweight-seo-legacy-keywords.csv"' );

		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			wp_die( esc_html__( 'The export stream could not be opened.', 'lightweight-seo' ) );
		}

		fputcsv( $output, array( 'object_type', 'object_id', 'value' ) );

		if ( ! empty( $settings['meta_keywords'] ) ) {
			fputcsv( $output, array( 'site', 0, $this->csv_safe_value( $settings['meta_keywords'] ) ) );
		}

		foreach ( $post_ids as $post_id ) {
			$value = get_post_meta( $post_id, '_lightweight_seo_keywords', true );

			if ( '' !== (string) $value ) {
				fputcsv( $output, array( 'post', absint( $post_id ), $this->csv_safe_value( $value ) ) );
			}
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the php://output download stream, not a file on disk.
		exit;
	}

	/** Prevent spreadsheet formula execution in administrator-initiated CSV exports. */
	private function csv_safe_value( $value ) {
		$value = (string) $value;

		return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
	}

	/**
	 * Preview or import one bounded batch and record the outcome.
	 *
	 * @since    1.1.0
	 * @param    string    $source    Submitted import source.
	 * @param    bool      $write     Whether to import rather than preview.
	 * @return   void
	 */
	private function run_batch( $source, $write ) {
		$state  = $this->import_state->get();
		$source = in_array( $source, self::IMPORT_SOURCES, true ) ? $source : '';

		if ( $state['source'] !== $source ) {
			// A different source restarts the scan, and the previous source's rollback snapshot no longer applies.
			$state['source'] = $source;
			$state['cursor'] = 0;

			delete_option( Lightweight_SEO_Importer_Service::ROLLBACK_OPTION );
		}

		if ( '' === $source ) {
			$state['report'] = __( 'Select a supported import source first.', 'lightweight-seo' );

			$this->import_state->save( $state );
			$this->finish( 'import_source_missing', $state['report'], 'error' );

			return;
		}

		$report = $write
			? $this->importer->import_batch( $source, $state['cursor'] )
			: $this->importer->preview( $source, $state['cursor'] );

		if ( $write ) {
			$state['cursor'] = ! empty( $report['has_more'] ) ? absint( $report['next_offset'] ?? 0 ) : 0;
		}

		$state['report'] = $this->describe_batch( $source, $report, $write );

		$this->import_state->save( $state );
		$this->finish( $write ? 'import_batch' : 'import_preview', $state['report'], $write ? 'success' : 'info' );
	}

	/**
	 * Summarize a preview or import batch for the administrator.
	 *
	 * @since    1.1.0
	 * @param    string    $source    Import source.
	 * @param    array     $report    Importer batch report.
	 * @param    bool      $write     Whether the batch was imported rather than previewed.
	 * @return   string
	 */
	private function describe_batch( $source, $report, $write ) {
		$summary = sprintf(
			/* translators: 1: action label. 2: source. 3: scanned posts. 4: eligible/imported posts. 5: changed fields. 6: skipped occupied fields. */
			__( '%1$s %2$s batch: scanned %3$d posts, found %4$d eligible posts and %5$d fillable fields; skipped %6$d occupied fields.', 'lightweight-seo' ),
			$write ? __( 'Imported', 'lightweight-seo' ) : __( 'Previewed', 'lightweight-seo' ),
			$source,
			absint( $report['scanned_posts'] ?? 0 ),
			absint( $write ? ( $report['imported_posts'] ?? 0 ) : ( $report['eligible_posts'] ?? 0 ) ),
			absint( $report['updated_fields'] ?? 0 ),
			absint( $report['skipped_fields'] ?? 0 )
		);

		if ( ! empty( $report['unsupported_fields'] ) ) {
			$summary .= ' ' . sprintf(
				/* translators: %d: number of fields that were not imported. */
				__( '%d fields use template variables with no Lightweight SEO equivalent and were left for manual review.', 'lightweight-seo' ),
				absint( $report['unsupported_fields'] )
			);
		}

		return $summary;
	}

	/**
	 * Stop the request unless the current user may manage plugin settings.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	private function require_capability() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to run SEO migration tools.', 'lightweight-seo' ) );
		}
	}

	/**
	 * Carry the outcome across the redirect and return to the Tools tab.
	 *
	 * The message uses the settings-error transient that options.php uses, so the
	 * settings page displays it once without a second notice mechanism.
	 *
	 * @since    1.1.0
	 * @param    string    $code       Notice code.
	 * @param    string    $message    Outcome shown to the administrator.
	 * @param    string    $type       Notice type: success, info, or error.
	 * @return   void
	 */
	private function finish( $code, $message, $type ) {
		// WordPress prints settings errors as HTML, so the plain-text outcome is escaped here.
		add_settings_error( LIGHTWEIGHT_SEO_OPTION_NAME, $code, esc_html( $message ), $type );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		if ( $this->redirect( admin_url( 'admin.php?page=' . $this->plugin_name . '&tab=tools&settings-updated=true' ) ) ) {
			exit;
		}
	}

	/**
	 * Send the redirect. Kept separate so the response can be observed without ending the process.
	 *
	 * @since    1.1.0
	 * @param    string    $url    Destination inside the administration area.
	 * @return   bool Whether the redirect was sent.
	 */
	protected function redirect( $url ) {
		return wp_safe_redirect( $url );
	}
}
