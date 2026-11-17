<?php
/**
 * Settings fields and action forms for the Tools tab.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Renders the Tools tab.
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

	/** ID of the form that uploads a settings file. */
	const SETTINGS_IMPORT_FORM_ID = 'lightweight-seo-settings-import-form';

	/** ID of the form that undoes the last settings import. */
	const SETTINGS_UNDO_FORM_ID = 'lightweight-seo-settings-undo-form';

	/** ID of the form that uploads a CSV file of redirect rules. */
	const REDIRECT_IMPORT_FORM_ID = 'lightweight-seo-redirect-import-form';

	/** ID of the form that asks which redirect rule applies to an address. */
	const REDIRECT_TEST_FORM_ID = 'lightweight-seo-redirect-test-form';

	/** Query argument that carries the address to test against the redirect rules. */
	const REDIRECT_TEST_ARG = 'lightweight_seo_test_path';

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
	 * What is still stored from retired features, read once per request.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      array|null    $legacy_state
	 */
	private $legacy_state = null;

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

	/**
	 * Find what is still stored from retired features.
	 *
	 * Read once per request, because the keyword check is a database query.
	 *
	 * @return array Keys: options, logs, has_credentials, and has_keywords.
	 */
	private function get_legacy_state() {
		if ( null === $this->legacy_state ) {
			$options = (array) get_option( LIGHTWEIGHT_SEO_OPTION_NAME, array() );

			$this->legacy_state = array(
				'options'         => $options,
				'logs'            => (array) get_option( 'lightweight_seo_404_logs', array() ),
				'has_credentials' => ! empty( $options['search_console_property'] ) || ! empty( $options['search_console_service_account_json'] ),
				'has_keywords'    => ! empty( $options['meta_keywords'] ) || $this->has_legacy_post_keywords(),
			);
		}

		return $this->legacy_state;
	}

	/**
	 * Tell whether anything from a retired feature is left to export or delete.
	 *
	 * @since    1.1.0
	 * @return   bool
	 */
	public function has_legacy_data() {
		$state = $this->get_legacy_state();

		return $state['has_credentials'] || $state['has_keywords'] || ! empty( $state['logs'] );
	}

	/** Render explicit export/delete choices for retained legacy data. */
	public function legacy_data_transition_render() {
		$state           = $this->get_legacy_state();
		$options         = $state['options'];
		$legacy_404_logs = $state['logs'];
		$has_credentials = $state['has_credentials'];
		$has_keywords    = $state['has_keywords'];
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
	 * Render the "Inspect a page" field: an address, a button, and the region the report appears in.
	 *
	 * The field has no name, so it is never saved. The script asks the address
	 * itself for its report; without the script, the link opens the same report.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function inspect_page_render() {
		$address = $this->get_inspect_address();
		$nonce   = wp_create_nonce( LIGHTWEIGHT_SEO_INSPECT_ARG );
		$report  = '' !== $address ? add_query_arg(
			array(
				LIGHTWEIGHT_SEO_INSPECT_ARG => '1',
				'_wpnonce'                  => $nonce,
			),
			$address
		) : '';
		?>
		<div
			class="lwseo-inspect"
			id="lwseo-inspect"
			data-arg="<?php echo esc_attr( LIGHTWEIGHT_SEO_INSPECT_ARG ); ?>"
			data-nonce="<?php echo esc_attr( $nonce ); ?>"
			data-home-host="<?php echo esc_attr( $this->get_home_host() ); ?>"
			data-working="<?php echo esc_attr( __( 'Asking the page…', 'lightweight-seo' ) ); ?>"
			data-outside="<?php echo esc_attr( __( 'Enter an address on this site.', 'lightweight-seo' ) ); ?>"
			data-error="<?php echo esc_attr( __( 'The report could not be loaded here. Open it in a new tab instead, or reload this screen and try again.', 'lightweight-seo' ) ); ?>"
			<?php /* translators: %s: address a redirect leads to. */ ?>
			data-redirected="<?php echo esc_attr( __( 'This address sends visitors to %s, so there is nothing to report for it. That address is now in the field.', 'lightweight-seo' ) ); ?>"
		>
			<div class="lwseo-inspect__row">
				<input type="url" id="lightweight_seo_inspect_page" value="<?php echo esc_url( $address ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" class="regular-text" autocomplete="off">
				<button type="button" class="button lwseo-inspect__run"><?php esc_html_e( 'Inspect', 'lightweight-seo' ); ?></button>
			</div>
			<p class="lwseo-help"><?php esc_html_e( 'Asks the page itself, so the report shows what a visitor’s browser and a search engine receive right now.', 'lightweight-seo' ); ?></p>
			<p class="lwseo-help lwseo-inspect__status" role="status"></p>
			<p class="lwseo-inspect__fallback"<?php echo '' === $report ? ' hidden' : ''; ?>>
				<a href="<?php echo esc_url( $report ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open the report in a new tab', 'lightweight-seo' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'lightweight-seo' ); ?></span></a>
			</p>
			<div class="lwseo-inspect__report" aria-live="polite"></div>
		</div>
		<?php
	}

	/**
	 * Get the host of this site, with its port when it has one, as a browser reports it.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	private function get_home_host() {
		$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$port = wp_parse_url( home_url( '/' ), PHP_URL_PORT );

		return $host . ( $port ? ':' . (int) $port : '' );
	}

	/**
	 * Get the address the screen was opened to inspect, when it is on this site.
	 *
	 * @since    1.1.0
	 * @return   string Address, or an empty string.
	 */
	private function get_inspect_address() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: the address only prefills a field, and is dropped unless it is on this site.
		$address = isset( $_GET['inspect'] ) ? esc_url_raw( wp_unslash( $_GET['inspect'] ) ) : '';
		$host    = strtolower( (string) wp_parse_url( $address, PHP_URL_HOST ) );
		$port    = wp_parse_url( $address, PHP_URL_PORT );

		return '' !== $address && $host . ( $port ? ':' . (int) $port : '' ) === $this->get_home_host() ? $address : '';
	}

	/**
	 * Render the settings file download.
	 *
	 * @since    1.1.0
	 */
	public function settings_export_render() {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . Lightweight_SEO_Admin_Tools::ACTION_SETTINGS_EXPORT ), Lightweight_SEO_Admin_Tools::ACTION_SETTINGS_EXPORT );
		?>
		<div class="lwseo-buttons">
			<a class="button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Download settings file', 'lightweight-seo' ); ?></a>
		</div>
		<p class="lwseo-help"><?php esc_html_e( 'A JSON file with the settings of every tab and which modules are on, as they are saved now. It holds no credentials and nothing kept from retired features.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the settings file upload, its confirmation, and the undo of the last import.
	 *
	 * @since    1.1.0
	 */
	public function settings_import_render() {
		$undo_time = (int) ( get_option( 'lightweight_seo_settings_backup', array() )['saved_at'] ?? 0 );
		?>
		<div class="lwseo-inline">
			<input type="file" id="lightweight_seo_settings_import" name="lightweight_seo_settings_file" accept=".json,application/json" form="<?php echo esc_attr( self::SETTINGS_IMPORT_FORM_ID ); ?>">
		</div>
		<div class="lwseo-buttons">
			<label class="lwseo-choice"><input type="checkbox" name="confirm_settings_import" value="1" required form="<?php echo esc_attr( self::SETTINGS_IMPORT_FORM_ID ); ?>"><span><?php esc_html_e( 'Replace every Lightweight SEO setting on this site with the file', 'lightweight-seo' ); ?></span></label>
			<button type="submit" class="button" form="<?php echo esc_attr( self::SETTINGS_IMPORT_FORM_ID ); ?>"><?php esc_html_e( 'Import settings', 'lightweight-seo' ); ?></button>
		</div>
		<p class="lwseo-help"><?php esc_html_e( 'Every value goes through the same checks as on the settings tabs. A value the file does not hold, or one that fails a check, is set to its default. The settings in place now are kept, so the import can be undone.', 'lightweight-seo' ); ?></p>
		<?php if ( $undo_time > 0 ) : ?>
			<div class="lwseo-buttons">
				<button type="submit" class="button-link button-link-delete" form="<?php echo esc_attr( self::SETTINGS_UNDO_FORM_ID ); ?>"><?php esc_html_e( 'Undo last import', 'lightweight-seo' ); ?></button>
			</div>
			<p class="lwseo-help">
				<?php
				printf(
					/* translators: %s: date and time of the last settings import. */
					esc_html__( 'Puts back the settings as they were before the import of %s. Changes saved since then are lost.', 'lightweight-seo' ),
					esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $undo_time ) )
				);
				?>
			</p>
		<?php endif; ?>
		<?php
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
		<form id="<?php echo esc_attr( self::SETTINGS_IMPORT_FORM_ID ); ?>" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( Lightweight_SEO_Admin_Tools::ACTION_SETTINGS_IMPORT ); ?>">
			<?php wp_nonce_field( Lightweight_SEO_Admin_Tools::ACTION_SETTINGS_IMPORT, Lightweight_SEO_Admin_Tools::get_nonce_name( Lightweight_SEO_Admin_Tools::ACTION_SETTINGS_IMPORT ) ); ?>
		</form>
		<form id="<?php echo esc_attr( self::SETTINGS_UNDO_FORM_ID ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( Lightweight_SEO_Admin_Tools::ACTION_SETTINGS_UNDO ); ?>">
			<?php wp_nonce_field( Lightweight_SEO_Admin_Tools::ACTION_SETTINGS_UNDO, Lightweight_SEO_Admin_Tools::get_nonce_name( Lightweight_SEO_Admin_Tools::ACTION_SETTINGS_UNDO ) ); ?>
		</form>
		<form id="<?php echo esc_attr( self::DELETE_404_LOGS_FORM_ID ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( Lightweight_SEO_Admin_Tools::ACTION_DELETE_404_LOGS ); ?>">
			<?php wp_nonce_field( Lightweight_SEO_Admin_Tools::ACTION_DELETE_404_LOGS, Lightweight_SEO_Admin_Tools::get_nonce_name( Lightweight_SEO_Admin_Tools::ACTION_DELETE_404_LOGS ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render the standalone forms that the Redirects card submits.
	 *
	 * The test is a plain GET of this screen, so it works without JavaScript and
	 * changes nothing. The import posts a file to its own handler and nonce.
	 *
	 * @since    1.1.0
	 * @param    string    $plugin_name    Menu slug of the settings screen.
	 * @return   void
	 */
	public function render_redirect_forms( $plugin_name ) {
		?>
		<form id="<?php echo esc_attr( self::REDIRECT_TEST_FORM_ID ); ?>" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) . '#lwseo-module-redirects' ); ?>">
			<input type="hidden" name="page" value="<?php echo esc_attr( $plugin_name ); ?>">
			<input type="hidden" name="tab" value="modules">
		</form>
		<form id="<?php echo esc_attr( self::REDIRECT_IMPORT_FORM_ID ); ?>" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( Lightweight_SEO_Admin_Tools::ACTION_REDIRECT_IMPORT ); ?>">
			<?php wp_nonce_field( Lightweight_SEO_Admin_Tools::ACTION_REDIRECT_IMPORT, Lightweight_SEO_Admin_Tools::get_nonce_name( Lightweight_SEO_Admin_Tools::ACTION_REDIRECT_IMPORT ) ); ?>
		</form>
		<?php
	}
}
