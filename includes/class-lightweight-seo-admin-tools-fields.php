<?php
/**
 * Settings fields and action forms for the Tools and migration tab.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Renders the Tools and migration tab.
 *
 * Import and deletion controls belong to standalone forms that post to the
 * handlers in Lightweight_SEO_Admin_Tools. The controls keep their place in the
 * settings table and join those forms through the HTML form attribute, because
 * a form cannot nest inside the settings form.
 */
class Lightweight_SEO_Admin_Tools_Fields {

	/** ID of the form that carries the import source and the three import actions. */
	const IMPORT_FORM_ID = 'lightweight-seo-import-form';

	/** ID of the form that deletes retained legacy 404 log entries. */
	const DELETE_404_LOGS_FORM_ID = 'lightweight-seo-delete-404-logs-form';

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings    $settings
	 */
	private $settings;

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
	 * Initialize the renderer.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings            $settings        Shared settings service.
	 * @param    Lightweight_SEO_Importer_Service    $importer        Metadata importer.
	 * @param    Lightweight_SEO_Import_State        $import_state    Import source, cursor, and last report.
	 */
	public function __construct( $settings, $importer, $import_state ) {
		$this->settings     = $settings;
		$this->importer     = $importer;
		$this->import_state = $import_state;
	}

	/**
	 * Render the migration section information.
	 *
	 * @since    1.1.0
	 */
	public function migration_section_callback() {
		echo '<p class="lwseo-help">' . esc_html__( 'Imports fill empty Lightweight SEO fields only. Each imported batch can be rolled back before the next batch replaces its rollback snapshot.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render the import source field.
	 *
	 * @since    1.1.0
	 */
	public function import_source_render() {
		$state = $this->import_state->get();
		?>
		<select id="lightweight_seo_import_source" name="import_source" form="<?php echo esc_attr( self::IMPORT_FORM_ID ); ?>">
			<option value="" <?php selected( $state['source'], '' ); ?>><?php esc_html_e( 'Select a source', 'lightweight-seo' ); ?></option>
			<option value="yoast" <?php selected( $state['source'], 'yoast' ); ?>><?php esc_html_e( 'Yoast SEO', 'lightweight-seo' ); ?></option>
			<option value="rank_math" <?php selected( $state['source'], 'rank_math' ); ?>><?php esc_html_e( 'Rank Math', 'lightweight-seo' ); ?></option>
			<option value="aioseo" <?php selected( $state['source'], 'aioseo' ); ?>><?php esc_html_e( 'All in One SEO', 'lightweight-seo' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Render bounded preview, import, and rollback actions.
	 *
	 * @since    1.1.0
	 */
	public function import_actions_render() {
		$state = $this->import_state->get();
		?>
		<div class="lwseo-buttons">
			<button type="submit" class="button" form="<?php echo esc_attr( self::IMPORT_FORM_ID ); ?>" name="action" value="<?php echo esc_attr( Lightweight_SEO_Admin_Tools::ACTION_IMPORT_PREVIEW ); ?>"><?php esc_html_e( 'Preview next batch', 'lightweight-seo' ); ?></button>
			<button type="submit" class="button" form="<?php echo esc_attr( self::IMPORT_FORM_ID ); ?>" name="action" value="<?php echo esc_attr( Lightweight_SEO_Admin_Tools::ACTION_IMPORT_BATCH ); ?>"><?php esc_html_e( 'Import next batch', 'lightweight-seo' ); ?></button>
			<?php if ( $this->importer->has_rollback() ) : ?>
				<button type="submit" class="button-link button-link-delete" form="<?php echo esc_attr( self::IMPORT_FORM_ID ); ?>" name="action" value="<?php echo esc_attr( Lightweight_SEO_Admin_Tools::ACTION_IMPORT_ROLLBACK ); ?>"><?php esc_html_e( 'Roll back last batch', 'lightweight-seo' ); ?></button>
			<?php endif; ?>
		</div>
		<p class="lwseo-help">
			<?php
			printf(
				/* translators: %d: zero-based importer cursor. */
				esc_html__( 'The next batch starts after %d scanned posts. Changing the source resets this cursor.', 'lightweight-seo' ),
				absint( $state['cursor'] )
			);
			?>
		</p>
		<?php
	}

	/**
	 * Render the last import report.
	 *
	 * @since    1.1.0
	 */
	public function import_report_render() {
		$state = $this->import_state->get();

		if ( '' === trim( (string) $state['report'] ) ) {
			echo '<p class="lwseo-help">' . esc_html__( 'No batch has been previewed or imported yet.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<p class="lwseo-log">' . esc_html( $state['report'] ) . '</p>';
	}

	/** Render explicit export/delete choices for retained legacy data. */
	public function legacy_data_transition_render() {
		$options         = (array) get_option( LIGHTWEIGHT_SEO_OPTION_NAME, array() );
		$legacy_404_logs = (array) get_option( 'lightweight_seo_404_logs', array() );
		$has_credentials = ! empty( $options['search_console_property'] ) || ! empty( $options['search_console_service_account_json'] );
		$has_keywords    = ! empty( $options['meta_keywords'] ) || $this->has_legacy_post_keywords();
		$export_url      = wp_nonce_url(
			admin_url( 'admin-post.php?action=lightweight_seo_export_legacy_keywords' ),
			'lightweight_seo_export_legacy_keywords'
		);
		?>
		<p class="lwseo-help"><?php esc_html_e( 'Retired data is never moved into another integration automatically.', 'lightweight-seo' ); ?></p>
		<ul class="lwseo-status-list">
			<?php
			if ( ! $has_credentials ) {
				Lightweight_SEO_Admin_UI::status( 'success', __( 'Search Console credentials', 'lightweight-seo' ), __( 'No legacy Search Console credentials are retained.', 'lightweight-seo' ) );
			}

			if ( empty( $legacy_404_logs ) ) {
				Lightweight_SEO_Admin_UI::status( 'success', __( '404 log entries', 'lightweight-seo' ), __( 'No legacy 404 log entries are retained.', 'lightweight-seo' ) );
			}

			if ( $has_keywords ) {
				Lightweight_SEO_Admin_UI::status(
					'info',
					__( 'Keyword values', 'lightweight-seo' ),
					__( 'Stored keyword values remain available for export but are not edited or output by Lightweight SEO.', 'lightweight-seo' ),
					array(
						'label' => __( 'Export legacy keywords CSV', 'lightweight-seo' ),
						'url'   => $export_url,
					)
				);
			} else {
				Lightweight_SEO_Admin_UI::status( 'success', __( 'Keyword values', 'lightweight-seo' ), __( 'No legacy keyword values were detected.', 'lightweight-seo' ) );
			}
			?>
		</ul>
		<?php if ( $has_credentials ) : ?>
			<details class="lwseo-details">
				<summary><?php esc_html_e( 'Review retained Search Console configuration', 'lightweight-seo' ); ?></summary>
				<div class="lwseo-details__body">
					<p><strong><?php esc_html_e( 'Property:', 'lightweight-seo' ); ?></strong> <?php echo esc_html( $options['search_console_property'] ?? '' ); ?></p>
					<textarea rows="6" class="large-text code" readonly="readonly" aria-label="<?php echo esc_attr__( 'Retained Search Console service account JSON', 'lightweight-seo' ); ?>"><?php echo esc_textarea( $options['search_console_service_account_json'] ?? '' ); ?></textarea>
					<p class="lwseo-help"><?php esc_html_e( 'Copy this only if you need an explicit private backup. Future Insights setup will require a new connection.', 'lightweight-seo' ); ?></p>
					<?php Lightweight_SEO_Admin_UI::choice( LIGHTWEIGHT_SEO_OPTION_NAME . '[delete_legacy_search_console_data]', false, __( 'Permanently delete the retained property and private key when settings are saved', 'lightweight-seo' ) ); ?>
				</div>
			</details>
		<?php endif; ?>
		<?php if ( ! empty( $legacy_404_logs ) ) : ?>
			<div class="lwseo-buttons">
				<?php /* translators: %d: Number of retained legacy 404 log entries. */ ?>
				<label class="lwseo-choice"><input type="checkbox" name="confirm_delete_404_logs" value="1" required form="<?php echo esc_attr( self::DELETE_404_LOGS_FORM_ID ); ?>"><span><?php printf( esc_html__( 'Permanently delete %d retained legacy 404 log entries', 'lightweight-seo' ), count( $legacy_404_logs ) ); ?></span></label>
				<button type="submit" class="button" form="<?php echo esc_attr( self::DELETE_404_LOGS_FORM_ID ); ?>"><?php esc_html_e( 'Delete log entries', 'lightweight-seo' ); ?></button>
			</div>
		<?php endif; ?>
		<?php
	}

	/** Detect keyword data with one bounded existence query on the Tools screen only. */
	private function has_legacy_post_keywords() {
		$post_ids = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_lightweight_seo_keywords', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded administrator transition check.
			)
		);

		return ! empty( $post_ids );
	}

	/**
	 * Render the persistent-data uninstall setting.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function delete_data_on_uninstall_render() {
		$options = $this->settings->get_all();

		Lightweight_SEO_Admin_UI::choice(
			LIGHTWEIGHT_SEO_OPTION_NAME . '[delete_data_on_uninstall]',
			'1' === (string) ( $options['delete_data_on_uninstall'] ?? '0' ),
			__( 'Delete SEO settings and object metadata when the plugin is uninstalled', 'lightweight-seo' ),
			__( 'Leave this off to preserve titles, descriptions, canonicals, robots directives, and social metadata for a later reinstall.', 'lightweight-seo' )
		);
	}

	/**
	 * Render the standalone forms that the import and deletion controls submit.
	 *
	 * Each action carries its own nonce, so one forged or replayed request cannot
	 * stand in for another.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function render_action_forms() {
		$import_actions = array(
			Lightweight_SEO_Admin_Tools::ACTION_IMPORT_PREVIEW,
			Lightweight_SEO_Admin_Tools::ACTION_IMPORT_BATCH,
			Lightweight_SEO_Admin_Tools::ACTION_IMPORT_ROLLBACK,
		);
		?>
		<form id="<?php echo esc_attr( self::IMPORT_FORM_ID ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php
			foreach ( $import_actions as $index => $action ) {
				// Only the first field needs the referer input.
				wp_nonce_field( $action, Lightweight_SEO_Admin_Tools::get_nonce_name( $action ), 0 === $index );
			}
			?>
		</form>
		<form id="<?php echo esc_attr( self::DELETE_404_LOGS_FORM_ID ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( Lightweight_SEO_Admin_Tools::ACTION_DELETE_404_LOGS ); ?>">
			<?php wp_nonce_field( Lightweight_SEO_Admin_Tools::ACTION_DELETE_404_LOGS, Lightweight_SEO_Admin_Tools::get_nonce_name( Lightweight_SEO_Admin_Tools::ACTION_DELETE_404_LOGS ) ); ?>
		</form>
		<?php
	}
}
