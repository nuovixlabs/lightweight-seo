<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The admin-specific functionality of the plugin.
 */
class Lightweight_SEO_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Shared settings service.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      Lightweight_SEO_Settings    $settings
	 */
	private $settings;

	/**
	 * Shared post meta service.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      Lightweight_SEO_Post_Meta    $post_meta
	 */
	private $post_meta;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string    $plugin_name       The name of this plugin.
	 * @param    string    $version           The version of this plugin.
	 */
	public function __construct( $plugin_name, $version, $settings, $post_meta ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->settings    = $settings;
		$this->post_meta   = $post_meta;

		// Add menu item
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add settings link on plugin page
		add_filter( 'plugin_action_links_' . plugin_basename( LIGHTWEIGHT_SEO_PLUGIN_FILE ), array( $this, 'add_action_links' ) );

		// Register settings
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Render compatibility notices
		add_action( 'admin_notices', array( $this, 'maybe_render_safe_mode_notice' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_upgrade_summary' ) );
		add_action( 'admin_post_lightweight_seo_dismiss_upgrade_summary', array( $this, 'dismiss_upgrade_summary' ) );
		add_action( 'admin_post_lightweight_seo_export_legacy_keywords', array( $this, 'export_legacy_keywords' ) );

		// Enqueue admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Register the administration menu for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {
		if ( function_exists( 'is_network_admin' ) && is_network_admin() ) {
			return;
		}

		add_menu_page(
			'Lightweight SEO Settings',
			'SEO',
			'manage_options',
			$this->plugin_name,
			array( $this, 'display_plugin_admin_page' ),
			'dashicons-search',
			100
		);
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 * @param    array    $links    Plugin Action links.
	 * @return   array
	 */
	public function add_action_links( $links ) {
		$settings_link = array(
			'<a href="' . admin_url( 'admin.php?page=' . $this->plugin_name ) . '">' . __( 'Settings', 'lightweight-seo' ) . '</a>',
		);
		return array_merge( $settings_link, $links );
	}

	/**
	 * Render a safe-mode notice when another SEO plugin is active.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function maybe_render_safe_mode_notice() {
		$compatibility_service = new Lightweight_SEO_Compatibility_Service();
		$conflicting_plugins   = $compatibility_service->get_conflicting_plugins();

		if ( empty( $conflicting_plugins ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html(
			sprintf(
				/* translators: %s: comma-separated list of conflicting SEO plugins */
				__( 'Lightweight SEO safe mode is active because %s is also running. Lightweight SEO title, meta, and schema output is disabled to avoid duplicate SEO markup. Core sitemaps and non-overlapping module settings remain available.', 'lightweight-seo' ),
				implode( ', ', $conflicting_plugins )
			)
		);
		echo '</p></div>';
	}

	/** Render the one-time summary created by the 1.0.3 cleanup migration. */
	public function maybe_render_upgrade_summary() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$summary = (array) get_option( 'lightweight_seo_upgrade_summary', array() );

		if ( empty( $summary ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=lightweight_seo_dismiss_upgrade_summary' ),
			'lightweight_seo_dismiss_upgrade_summary'
		);

		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Lightweight SEO upgrade summary', 'lightweight-seo' ) . '</strong></p><ul>';
		echo '<li>' . esc_html__( 'Search Console synchronization, site-wide link/image reports, continuous 404 logging, meta-keywords output, and specialized sitemap providers are no longer active in core.', 'lightweight-seo' ) . '</li>';

		if ( ! empty( $summary['removed_sitemaps'] ) ) {
			echo '<li>' . esc_html__( 'Previously enabled image, video, or news sitemap endpoints were retired. Remove those submitted URLs from external webmaster tools.', 'lightweight-seo' ) . '</li>';
		}

		if ( ! empty( $summary['has_search_console_private'] ) || ! empty( $summary['legacy_404_entries'] ) || ! empty( $summary['has_meta_keywords'] ) ) {
			echo '<li>' . esc_html__( 'Legacy credentials, 404 entries, or keyword values need an explicit export or deletion decision on Tools and migration.', 'lightweight-seo' ) . '</li>';
		}

		echo '</ul><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . $this->plugin_name . '&tab=tools' ) ) . '">' . esc_html__( 'Review migration data', 'lightweight-seo' ) . '</a> <a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss summary', 'lightweight-seo' ) . '</a></p></div>';
	}

	/** Dismiss the one-time upgrade summary. */
	public function dismiss_upgrade_summary() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to dismiss this notice.', 'lightweight-seo' ) );
		}

		check_admin_referer( 'lightweight_seo_dismiss_upgrade_summary' );
		delete_option( 'lightweight_seo_upgrade_summary' );
		wp_safe_redirect( admin_url( 'admin.php?page=' . $this->plugin_name ) );
		exit;
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

		fclose( $output );
		exit;
	}

	/** Prevent spreadsheet formula execution in administrator-initiated CSV exports. */
	private function csv_safe_value( $value ) {
		$value = (string) $value;

		return preg_match( '/^[=+\-@]/', $value ) ? "'" . $value : $value;
	}

	/**
	 * Register plugin settings
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {
		register_setting(
			LIGHTWEIGHT_SEO_OPTION_NAME,
			LIGHTWEIGHT_SEO_OPTION_NAME,
			array( $this, 'validate_settings' )
		);

		// General SEO Settings section
		add_settings_section(
			'lightweight_seo_general_section',
			__( 'Global SEO Settings', 'lightweight-seo' ),
			array( $this, 'general_section_callback' ),
			$this->plugin_name
		);

		// Title Format
		add_settings_field(
			'title_format',
			__( 'Default Title Format', 'lightweight-seo' ),
			array( $this, 'title_format_render' ),
			$this->plugin_name,
			'lightweight_seo_general_section'
		);

		add_settings_field(
			'home_title_format',
			__( 'Home Title Format', 'lightweight-seo' ),
			array( $this, 'home_title_format_render' ),
			$this->plugin_name,
			'lightweight_seo_general_section'
		);

		add_settings_field(
			'archive_title_format',
			__( 'Archive Title Format', 'lightweight-seo' ),
			array( $this, 'archive_title_format_render' ),
			$this->plugin_name,
			'lightweight_seo_general_section'
		);

		add_settings_field(
			'search_title_format',
			__( 'Search Title Format', 'lightweight-seo' ),
			array( $this, 'search_title_format_render' ),
			$this->plugin_name,
			'lightweight_seo_general_section'
		);

		// Meta Description
		add_settings_field(
			'meta_description',
			__( 'Default Meta Description', 'lightweight-seo' ),
			array( $this, 'meta_description_render' ),
			$this->plugin_name,
			'lightweight_seo_general_section'
		);

		// Social Image
		add_settings_field(
			'social_image',
			__( 'Default Social Image', 'lightweight-seo' ),
			array( $this, 'social_image_render' ),
			$this->plugin_name,
			'lightweight_seo_general_section'
		);

		// Indexation Controls Section
		add_settings_section(
			'lightweight_seo_indexation_section',
			__( 'Indexation Controls', 'lightweight-seo' ),
			array( $this, 'indexation_section_callback' ),
			$this->plugin_name
		);

		add_settings_field(
			'noindex_search_results',
			__( 'Search Results Pages', 'lightweight-seo' ),
			array( $this, 'noindex_search_results_render' ),
			$this->plugin_name,
			'lightweight_seo_indexation_section'
		);

		add_settings_field(
			'noindex_attachment_pages',
			__( 'Attachment Pages', 'lightweight-seo' ),
			array( $this, 'noindex_attachment_pages_render' ),
			$this->plugin_name,
			'lightweight_seo_indexation_section'
		);

		add_settings_field(
			'default_max_image_preview',
			__( 'Default Max Image Preview', 'lightweight-seo' ),
			array( $this, 'default_max_image_preview_render' ),
			$this->plugin_name,
			'lightweight_seo_indexation_section'
		);

		add_settings_field(
			'enable_media_x_robots_headers',
			__( 'Media X-Robots Headers', 'lightweight-seo' ),
			array( $this, 'enable_media_x_robots_headers_render' ),
			$this->plugin_name,
			'lightweight_seo_indexation_section'
		);

		// Sitemap Section
		add_settings_section(
			'lightweight_seo_sitemap_section',
			__( 'XML Sitemaps', 'lightweight-seo' ),
			array( $this, 'sitemap_section_callback' ),
			$this->plugin_name
		);

		add_settings_field(
			'exclude_noindex_from_sitemaps',
			__( 'Exclude Noindex Content', 'lightweight-seo' ),
			array( $this, 'exclude_noindex_from_sitemaps_render' ),
			$this->plugin_name,
			'lightweight_seo_sitemap_section'
		);

		add_settings_field(
			'exclude_redirected_from_sitemaps',
			__( 'Exclude Redirected URLs', 'lightweight-seo' ),
			array( $this, 'exclude_redirected_from_sitemaps_render' ),
			$this->plugin_name,
			'lightweight_seo_sitemap_section'
		);

		// Structured Data Section
		add_settings_section(
			'lightweight_seo_schema_section',
			__( 'Structured Data', 'lightweight-seo' ),
			array( $this, 'schema_section_callback' ),
			$this->plugin_name
		);

		add_settings_field(
			'enable_schema_output',
			__( 'Core Schema Output', 'lightweight-seo' ),
			array( $this, 'enable_schema_output_render' ),
			$this->plugin_name,
			'lightweight_seo_schema_section'
		);

		add_settings_field(
			'organization_same_as',
			__( 'Organization Profiles', 'lightweight-seo' ),
			array( $this, 'organization_same_as_render' ),
			$this->plugin_name,
			'lightweight_seo_schema_section'
		);

		add_settings_field(
			'enable_local_business_schema',
			__( 'Local Business Schema', 'lightweight-seo' ),
			array( $this, 'enable_local_business_schema_render' ),
			$this->plugin_name,
			'lightweight_seo_schema_section'
		);

		add_settings_field(
			'local_business_details',
			__( 'Local Business Details', 'lightweight-seo' ),
			array( $this, 'local_business_details_render' ),
			$this->plugin_name,
			'lightweight_seo_schema_section'
		);

		add_settings_field(
			'enable_hreflang_output',
			__( 'Hreflang Output', 'lightweight-seo' ),
			array( $this, 'enable_hreflang_output_render' ),
			$this->plugin_name,
			'lightweight_seo_schema_section'
		);

		add_settings_field(
			'hreflang_mappings',
			__( 'Hreflang Mappings', 'lightweight-seo' ),
			array( $this, 'hreflang_mappings_render' ),
			$this->plugin_name,
			'lightweight_seo_schema_section'
		);

		add_settings_field(
			'enable_hreflang_path_mirroring',
			__( 'Legacy Path Mirroring', 'lightweight-seo' ),
			array( $this, 'enable_hreflang_path_mirroring_render' ),
			$this->plugin_name,
			'lightweight_seo_schema_section'
		);

		// Redirects Section
		add_settings_section(
			'lightweight_seo_redirects_section',
			__( 'Redirects', 'lightweight-seo' ),
			array( $this, 'redirects_section_callback' ),
			$this->plugin_name
		);

		add_settings_field(
			'enable_auto_redirects',
			__( 'Automatic Slug Redirects', 'lightweight-seo' ),
			array( $this, 'enable_auto_redirects_render' ),
			$this->plugin_name,
			'lightweight_seo_redirects_section'
		);

		add_settings_field(
			'redirect_rules',
			__( 'Manual Redirect Rules', 'lightweight-seo' ),
			array( $this, 'redirect_rules_render' ),
			$this->plugin_name,
			'lightweight_seo_redirects_section'
		);

		add_settings_field(
			'generated_redirect_rules',
			__( 'Generated Redirects', 'lightweight-seo' ),
			array( $this, 'generated_redirect_rules_render' ),
			$this->plugin_name,
			'lightweight_seo_redirects_section'
		);

		add_settings_field(
			'redirect_export',
			__( 'Redirect Export', 'lightweight-seo' ),
			array( $this, 'redirect_export_render' ),
			$this->plugin_name,
			'lightweight_seo_redirects_section'
		);

		add_settings_field(
			'redirect_health',
			__( 'Redirect Health', 'lightweight-seo' ),
			array( $this, 'redirect_health_render' ),
			$this->plugin_name,
			'lightweight_seo_redirects_section'
		);

		// Migration Section
		add_settings_section(
			'lightweight_seo_migration_section',
			__( 'Migration & Imports', 'lightweight-seo' ),
			array( $this, 'migration_section_callback' ),
			$this->plugin_name
		);

		add_settings_field(
			'import_source',
			__( 'Import Source', 'lightweight-seo' ),
			array( $this, 'import_source_render' ),
			$this->plugin_name,
			'lightweight_seo_migration_section'
		);

		add_settings_field(
			'import_actions',
			__( 'Preview or Import', 'lightweight-seo' ),
			array( $this, 'import_actions_render' ),
			$this->plugin_name,
			'lightweight_seo_migration_section'
		);

		add_settings_field(
			'import_report',
			__( 'Import Status', 'lightweight-seo' ),
			array( $this, 'import_report_render' ),
			$this->plugin_name,
			'lightweight_seo_migration_section'
		);

		add_settings_field(
			'legacy_data_transition',
			__( 'Legacy Data Transition', 'lightweight-seo' ),
			array( $this, 'legacy_data_transition_render' ),
			$this->plugin_name,
			'lightweight_seo_migration_section'
		);

		add_settings_field(
			'delete_data_on_uninstall',
			__( 'Uninstall Data', 'lightweight-seo' ),
			array( $this, 'delete_data_on_uninstall_render' ),
			$this->plugin_name,
			'lightweight_seo_migration_section'
		);

		// Tracking Codes Section
		add_settings_section(
			'lightweight_seo_tracking_section',
			__( 'Tracking Codes', 'lightweight-seo' ),
			array( $this, 'tracking_section_callback' ),
			$this->plugin_name
		);

		// Google Tag Manager (recommended primary strategy).
		add_settings_field(
			'gtm_container_id',
			__( 'Google Tag Manager Container ID', 'lightweight-seo' ),
			array( $this, 'gtm_container_id_render' ),
			$this->plugin_name,
			'lightweight_seo_tracking_section'
		);

		// Direct Google Analytics 4 alternative.
		add_settings_field(
			'ga4_measurement_id',
			__( 'Direct Google Analytics 4 Measurement ID', 'lightweight-seo' ),
			array( $this, 'ga4_measurement_id_render' ),
			$this->plugin_name,
			'lightweight_seo_tracking_section'
		);

		// Facebook Pixel
		add_settings_field(
			'facebook_pixel_id',
			__( 'Direct Meta Pixel ID', 'lightweight-seo' ),
			array( $this, 'facebook_pixel_id_render' ),
			$this->plugin_name,
			'lightweight_seo_tracking_section'
		);

		add_settings_field(
			'tracking_excluded_roles',
			__( 'Excluded Roles', 'lightweight-seo' ),
			array( $this, 'tracking_excluded_roles_render' ),
			$this->plugin_name,
			'lightweight_seo_tracking_section'
		);

		add_settings_field(
			'tracking_excluded_environments',
			__( 'Excluded Environments', 'lightweight-seo' ),
			array( $this, 'tracking_excluded_environments_render' ),
			$this->plugin_name,
			'lightweight_seo_tracking_section'
		);

		add_settings_section(
			'lightweight_seo_ai_discovery_section',
			__( 'AI Discovery (Experimental)', 'lightweight-seo' ),
			array( $this, 'ai_discovery_section_callback' ),
			$this->plugin_name
		);

		foreach (
			array(
				'enable_ai_discovery'          => array( __( 'Enable Module', 'lightweight-seo' ), 'enable_ai_discovery_render' ),
				'ai_search_crawlers_enabled'   => array( __( 'AI Search Visibility', 'lightweight-seo' ), 'ai_search_crawlers_enabled_render' ),
				'ai_training_crawlers_enabled' => array( __( 'Model Training Access', 'lightweight-seo' ), 'ai_training_crawlers_enabled_render' ),
				'enable_llms_txt'              => array( __( 'Curated llms.txt', 'lightweight-seo' ), 'enable_llms_txt_render' ),
				'llms_txt_post_ids'            => array( __( 'Authoritative Page IDs', 'lightweight-seo' ), 'llms_txt_post_ids_render' ),
				'ai_readiness'                 => array( __( 'Readiness Checks', 'lightweight-seo' ), 'ai_readiness_render' ),
			) as $field_id => $field
		) {
			add_settings_field(
				$field_id,
				$field[0],
				array( $this, $field[1] ),
				$this->plugin_name,
				'lightweight_seo_ai_discovery_section'
			);
		}
	}

	/**
	 * Render the general section information
	 *
	 * @since    1.0.0
	 */
	public function general_section_callback() {
		echo '<p>' . __( 'Configure the global SEO settings for your site. These will be used as defaults for all pages unless overridden.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . __( 'Available variables for title format: &#37;title&#37;, &#37;sitename&#37;, &#37;tagline&#37;, &#37;sep&#37;', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render the title format field
	 *
	 * @since    1.0.0
	 */
	public function title_format_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[title_format]" value="<?php echo esc_attr( wp_specialchars_decode( $options['title_format'] ?? LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT, ENT_QUOTES ) ); ?>" class="regular-text">
			<p class="description"><?php _e( 'Format for page titles. Example: &#37;title&#37; – &#37;sitename&#37;', 'lightweight-seo' ); ?></p>
			<?php
	}

	/**
	 * Render the home title format field.
	 *
	 * @since    1.1.0
	 */
	public function home_title_format_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[home_title_format]" value="<?php echo esc_attr( wp_specialchars_decode( $options['home_title_format'] ?? '%sitename% %sep% %tagline%', ENT_QUOTES ) ); ?>" class="regular-text">
		<p class="description"><?php _e( 'Format for the homepage title. Example: &#37;sitename&#37; &#37;sep&#37; &#37;tagline&#37;', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the archive title format field.
	 *
	 * @since    1.1.0
	 */
	public function archive_title_format_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[archive_title_format]" value="<?php echo esc_attr( wp_specialchars_decode( $options['archive_title_format'] ?? '%title% %sep% %sitename%', ENT_QUOTES ) ); ?>" class="regular-text">
		<p class="description"><?php _e( 'Format for archive, taxonomy, and author titles. Example: &#37;title&#37; &#37;sep&#37; &#37;sitename&#37;', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the search title format field.
	 *
	 * @since    1.1.0
	 */
	public function search_title_format_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[search_title_format]" value="<?php echo esc_attr( wp_specialchars_decode( $options['search_title_format'] ?? 'Search Results for "%search%" %sep% %sitename%', ENT_QUOTES ) ); ?>" class="regular-text">
		<p class="description"><?php _e( 'Format for internal search titles. Available variables include &#37;search&#37;, &#37;sitename&#37;, and &#37;sep&#37;.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the meta description field
	 *
	 * @since    1.0.0
	 */
	public function meta_description_render() {
		$options = $this->settings->get_all();
		?>
		<textarea name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[meta_description]" rows="4" cols="50" class="large-text"><?php echo esc_textarea( $options['meta_description'] ?? '' ); ?></textarea>
		<p class="description"><?php _e( 'Default description for pages without custom descriptions.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the meta keywords field
	 *
	 * @since    1.0.0
	 */
	public function meta_keywords_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[meta_keywords]" value="<?php echo esc_attr( $options['meta_keywords'] ?? '' ); ?>" class="large-text">
		<p class="description"><?php _e( 'Comma-separated list of keywords for your site.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the meta keywords output field.
	 *
	 * @since    1.0.2
	 */
	public function enable_meta_keywords_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_meta_keywords]" value="1" <?php checked( $options['enable_meta_keywords'] ?? '1', '1' ); ?>>
			<?php _e( 'Output the meta keywords tag on the frontend', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php _e( 'Disable this if you do not want meta keywords printed in your page source.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the social image field
	 *
	 * @since    1.0.0
	 */
	public function social_image_render() {
		$options   = $this->settings->get_all();
		$image_url = $this->settings->get_social_image_url();
		$image_id  = absint( $options['social_image_id'] ?? 0 );
		?>
		<div class="lightweight-seo-image-field">
			<input type="hidden" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[social_image_id]" id="lightweight_seo_social_image_id" value="<?php echo esc_attr( $image_id ); ?>" class="lightweight-seo-image-id">
			<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[social_image]" id="lightweight_seo_social_image" value="<?php echo esc_url( $image_url ); ?>" class="regular-text lightweight-seo-image-url">
			<button type="button" class="button button-secondary" id="lightweight_seo_upload_image"><?php _e( 'Upload Image', 'lightweight-seo' ); ?></button>
			<?php if ( ! empty( $image_url ) ) : ?>
				<div class="lightweight-seo-image-preview">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php _e( 'Preview', 'lightweight-seo' ); ?>" style="max-width: 200px; margin-top: 10px;">
				</div>
			<?php endif; ?>
		</div>
		<p class="description"><?php _e( 'Default image for social media sharing.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the indexation section information.
	 *
	 * @since    1.1.0
	 */
	public function indexation_section_callback() {
		echo '<p>' . __( 'Control how your site should be indexed and previewed in search results.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render the search results noindex field.
	 *
	 * @since    1.1.0
	 */
	public function noindex_search_results_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[noindex_search_results]" value="1" <?php checked( $options['noindex_search_results'] ?? '1', '1' ); ?>>
			<?php _e( 'Add a noindex directive to internal search result pages', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php _e( 'Recommended for most sites to prevent low-value internal search pages from being indexed.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the attachment pages noindex field.
	 *
	 * @since    1.1.0
	 */
	public function noindex_attachment_pages_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[noindex_attachment_pages]" value="1" <?php checked( $options['noindex_attachment_pages'] ?? '1', '1' ); ?>>
			<?php _e( 'Add a noindex directive to attachment pages by default', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php _e( 'Recommended for most sites unless attachment pages are being used as standalone landing pages.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the default max-image-preview field.
	 *
	 * @since    1.1.0
	 */
	public function default_max_image_preview_render() {
		$options          = $this->settings->get_all();
		$current_value    = $this->settings->get_default_max_image_preview();
		$selected_value   = $options['default_max_image_preview'] ?? $current_value;
		$normalized_value = $this->settings->normalize_max_image_preview( $selected_value, $current_value );
		?>
		<select name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[default_max_image_preview]">
			<option value="large" <?php selected( $normalized_value, 'large' ); ?>><?php _e( 'Large', 'lightweight-seo' ); ?></option>
			<option value="standard" <?php selected( $normalized_value, 'standard' ); ?>><?php _e( 'Standard', 'lightweight-seo' ); ?></option>
			<option value="none" <?php selected( $normalized_value, 'none' ); ?>><?php _e( 'None', 'lightweight-seo' ); ?></option>
		</select>
		<p class="description"><?php _e( 'Sets the default max-image-preview robots directive for your content.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the media X-Robots header field.
	 *
	 * @since    1.1.0
	 */
	public function enable_media_x_robots_headers_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_media_x_robots_headers]" value="1" <?php checked( $options['enable_media_x_robots_headers'] ?? '1', '1' ); ?>>
			<?php _e( 'Send X-Robots-Tag headers for WordPress attachment pages', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php _e( 'Static files served directly by the web server or CDN require server-level header configuration.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the sitemap section information.
	 *
	 * @since    1.1.0
	 */
	public function sitemap_section_callback() {
		echo '<p>' . __( 'Lightweight SEO extends WordPress core XML sitemaps instead of replacing them.', 'lightweight-seo' ) . '</p>';
		echo '<p><code>' . esc_html( home_url( '/wp-sitemap.xml' ) ) . '</code></p>';
	}

	/**
	 * Render the exclude noindex content from sitemaps field.
	 *
	 * @since    1.1.0
	 */
	public function exclude_noindex_from_sitemaps_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[exclude_noindex_from_sitemaps]" value="1" <?php checked( $options['exclude_noindex_from_sitemaps'] ?? '1', '1' ); ?>>
			<?php _e( 'Exclude noindexed posts from WordPress core XML sitemaps', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php _e( 'Recommended to keep your sitemap focused on indexable content.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the attachment image sitemap field.
	 *
	 * @since    1.1.0
	 */
	public function enable_image_sitemaps_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_image_sitemaps]" value="1" <?php checked( $options['enable_image_sitemaps'] ?? '1', '1' ); ?>>
			<?php _e( 'Publish a dedicated XML sitemap for image attachments', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php _e( 'This adds a separate attachment sitemap alongside WordPress core sitemaps.', 'lightweight-seo' ); ?></p>
		<p class="description"><code><?php echo esc_html( home_url( '/wp-sitemap-lightweightseoimages-1.xml' ) ); ?></code></p>
		<?php
	}

	/**
	 * Render the redirected URL sitemap exclusion field.
	 *
	 * @since    1.1.0
	 */
	public function exclude_redirected_from_sitemaps_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[exclude_redirected_from_sitemaps]" value="1" <?php checked( $options['exclude_redirected_from_sitemaps'] ?? '1', '1' ); ?>>
			<?php _e( 'Exclude content whose live paths are redirected elsewhere', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php _e( 'Prevents sitemap entries from pointing at URLs that are currently redirected.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the attachment video sitemap field.
	 *
	 * @since    1.1.0
	 */
	public function enable_video_sitemaps_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_video_sitemaps]" value="1" <?php checked( $options['enable_video_sitemaps'] ?? '1', '1' ); ?>>
			<?php _e( 'Publish a dedicated XML sitemap for video attachments', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><code><?php echo esc_html( home_url( '/wp-sitemap-lightweightseovideos-1.xml' ) ); ?></code></p>
		<?php
	}

	/**
	 * Render the recent news sitemap field.
	 *
	 * @since    1.1.0
	 */
	public function enable_news_sitemaps_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_news_sitemaps]" value="1" <?php checked( $options['enable_news_sitemaps'] ?? '0', '1' ); ?>>
			<?php _e( 'Publish a recent-post news sitemap for fresh articles', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><code><?php echo esc_html( home_url( '/wp-sitemap-lightweightseonews-1.xml' ) ); ?></code></p>
		<?php
	}

	/**
	 * Render the Search Console sitemap submission field.
	 *
	 * @since    1.1.0
	 */
	public function submit_sitemaps_to_search_console_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[submit_sitemaps_to_search_console]" value="1" <?php checked( $options['submit_sitemaps_to_search_console'] ?? '1', '1' ); ?>>
			<?php _e( 'Submit configured sitemaps during Search Console sync', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php _e( 'When Search Console credentials are configured, Lightweight SEO will submit the sitemap index and enabled module sitemaps before fetching status.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the schema section information.
	 *
	 * @since    1.1.0
	 */
	public function schema_section_callback() {
		echo '<p>' . __( 'Output lightweight JSON-LD schema using your site identity and SEO context.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . __( 'The default social image is used as the organization logo when available.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render the schema output toggle field.
	 *
	 * @since    1.1.0
	 */
	public function enable_schema_output_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_schema_output]" value="1" <?php checked( $options['enable_schema_output'] ?? '1', '1' ); ?>>
			<?php _e( 'Output core Organization, WebSite, Article, and Breadcrumb schema', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php _e( 'Disable this if your theme or another plugin already outputs equivalent structured data.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the organization sameAs field.
	 *
	 * @since    1.1.0
	 */
	public function organization_same_as_render() {
		$options = $this->settings->get_all();
		?>
		<textarea name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[organization_same_as]" rows="5" cols="50" class="large-text"><?php echo esc_textarea( $options['organization_same_as'] ?? '' ); ?></textarea>
		<p class="description"><?php _e( 'Add one profile URL per line for your organization, such as social profiles or knowledge sources.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the product schema toggle field.
	 *
	 * @since    1.1.0
	 */
	public function enable_product_schema_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_product_schema]" value="1" <?php checked( $options['enable_product_schema'] ?? '1', '1' ); ?>>
			<?php _e( 'Output Product schema for WooCommerce-style product pages', 'lightweight-seo' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the LocalBusiness schema toggle field.
	 *
	 * @since    1.1.0
	 */
	public function enable_local_business_schema_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_local_business_schema]" value="1" <?php checked( $options['enable_local_business_schema'] ?? '0', '1' ); ?>>
			<?php _e( 'Output LocalBusiness schema on the homepage', 'lightweight-seo' ); ?>
		</label>
		<?php
	}

	/**
	 * Render LocalBusiness details fields.
	 *
	 * @since    1.1.0
	 */
	public function local_business_details_render() {
		$options = $this->settings->get_all();
		?>
		<p>
			<label><?php _e( 'Business Type', 'lightweight-seo' ); ?><br>
				<select name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_type]">
					<option value="LocalBusiness" <?php selected( $options['local_business_type'] ?? 'LocalBusiness', 'LocalBusiness' ); ?>><?php _e( 'LocalBusiness', 'lightweight-seo' ); ?></option>
					<option value="Restaurant" <?php selected( $options['local_business_type'] ?? '', 'Restaurant' ); ?>><?php _e( 'Restaurant', 'lightweight-seo' ); ?></option>
					<option value="Store" <?php selected( $options['local_business_type'] ?? '', 'Store' ); ?>><?php _e( 'Store', 'lightweight-seo' ); ?></option>
					<option value="MedicalBusiness" <?php selected( $options['local_business_type'] ?? '', 'MedicalBusiness' ); ?>><?php _e( 'MedicalBusiness', 'lightweight-seo' ); ?></option>
					<option value="ProfessionalService" <?php selected( $options['local_business_type'] ?? '', 'ProfessionalService' ); ?>><?php _e( 'ProfessionalService', 'lightweight-seo' ); ?></option>
				</select>
			</label>
		</p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_name]" value="<?php echo esc_attr( $options['local_business_name'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'Business name', 'lightweight-seo' ) ); ?>"></p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_phone]" value="<?php echo esc_attr( $options['local_business_phone'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'Phone number', 'lightweight-seo' ) ); ?>"></p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_price_range]" value="<?php echo esc_attr( $options['local_business_price_range'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'Price range, e.g. $$', 'lightweight-seo' ) ); ?>"></p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_address_street]" value="<?php echo esc_attr( $options['local_business_address_street'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'Street address', 'lightweight-seo' ) ); ?>"></p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_address_locality]" value="<?php echo esc_attr( $options['local_business_address_locality'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'City / locality', 'lightweight-seo' ) ); ?>"></p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_address_region]" value="<?php echo esc_attr( $options['local_business_address_region'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'Region / state', 'lightweight-seo' ) ); ?>"></p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_address_postal_code]" value="<?php echo esc_attr( $options['local_business_address_postal_code'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'Postal code', 'lightweight-seo' ) ); ?>"></p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_address_country]" value="<?php echo esc_attr( $options['local_business_address_country'] ?? '' ); ?>" class="small-text" maxlength="2" placeholder="<?php echo esc_attr( __( 'Country code', 'lightweight-seo' ) ); ?>"></p>
		<p>
			<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_latitude]" value="<?php echo esc_attr( $options['local_business_latitude'] ?? '' ); ?>" class="small-text" placeholder="<?php echo esc_attr( __( 'Latitude', 'lightweight-seo' ) ); ?>">
			<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_longitude]" value="<?php echo esc_attr( $options['local_business_longitude'] ?? '' ); ?>" class="small-text" placeholder="<?php echo esc_attr( __( 'Longitude', 'lightweight-seo' ) ); ?>">
		</p>
		<p><input type="url" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_image]" value="<?php echo esc_attr( $options['local_business_image'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'Dedicated business image or logo URL', 'lightweight-seo' ) ); ?>"></p>
		<p class="description"><?php _e( 'Use a dedicated business image. The general social sharing image is not reused for LocalBusiness.', 'lightweight-seo' ); ?></p>
		<p>
			<textarea name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_opening_hours]" rows="4" cols="50" class="large-text"><?php echo esc_textarea( $options['local_business_opening_hours'] ?? '' ); ?></textarea>
		</p>
		<p class="description"><?php _e( 'Add one opening-hours rule per line, e.g. Mo-Fr 09:00-17:00.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the hreflang output field.
	 *
	 * @since    1.1.0
	 */
	public function enable_hreflang_output_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_hreflang_output]" value="1" <?php checked( $options['enable_hreflang_output'] ?? '0', '1' ); ?>>
			<?php _e( 'Output hreflang alternate links', 'lightweight-seo' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the hreflang mappings field.
	 *
	 * @since    1.1.0
	 */
	public function hreflang_mappings_render() {
		$options = $this->settings->get_all();
		?>
		<textarea name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[hreflang_mappings]" rows="5" cols="50" class="large-text code"><?php echo esc_textarea( $options['hreflang_mappings'] ?? '' ); ?></textarea>
		<p class="description"><?php _e( 'Use one explicit mapping per line: post:123 en-GB https://example.co.uk/page/ (term: and user: are also supported). Language tags must use BCP 47 format; x-default is supported.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/** Render the opt-in retained legacy path-mirroring mode. */
	public function enable_hreflang_path_mirroring_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_hreflang_path_mirroring]" value="1" <?php checked( $options['enable_hreflang_path_mirroring'] ?? '0', '1' ); ?>>
			<?php _e( 'Reuse the current canonical path with retained two-column language/base-URL mappings', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php _e( 'Leave this off unless matching paths are intentionally guaranteed across the configured domains.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the redirects section information.
	 *
	 * @since    1.1.0
	 */
	public function redirects_section_callback() {
		echo '<p>' . __( 'Manage exact redirect rules and optionally preserve traffic after published slugs change. Continuous 404 logging is not part of this module.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render the internal linking section information.
	 *
	 * @since    1.1.0
	 */
	public function internal_links_section_callback() {
		echo '<p>' . __( 'Scan published content for internal links, orphan pages, and broken destinations using a cached report.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . __( 'Reports refresh automatically after content changes and are cached for up to 15 minutes.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render the image Discover section information.
	 *
	 * @since    1.1.0
	 */
	public function image_discover_section_callback() {
		echo '<p>' . __( 'Audit featured images for Discover-friendly sizing, missing alt text, and missing visuals on indexable content.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render the Search Console section information.
	 *
	 * @since    1.1.0
	 */
	public function search_console_section_callback() {
		echo '<p>' . __( 'Connect a Search Console property with a Google service account to surface clicks, impressions, low-CTR pages, and sitemap status.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . __( 'Add the service-account email as an owner or user on the Search Console property before syncing.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . __( 'Snapshots refresh on demand and are scheduled for daily background sync when WordPress cron is available.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . __( 'Important pages from the snapshot are also inspected for indexation and canonical issues, with inspection volume capped to stay within API quotas.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render the migration section information.
	 *
	 * @since    1.1.0
	 */
	public function migration_section_callback() {
		echo '<p>' . __( 'Preview and import saved SEO metadata from Yoast SEO, Rank Math, or All in One SEO in batches of 50 posts.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . __( 'Imports fill empty Lightweight SEO fields only. Each imported batch can be rolled back before the next batch replaces its rollback snapshot.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render the persistent-data uninstall setting.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function delete_data_on_uninstall_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[delete_data_on_uninstall]" value="1" <?php checked( $options['delete_data_on_uninstall'] ?? '0', '1' ); ?>>
			<?php _e( 'Delete SEO settings and object metadata when the plugin is uninstalled', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php _e( 'Leave this off to preserve titles, descriptions, canonicals, robots directives, and social metadata for a later reinstall.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the automatic slug redirect toggle field.
	 *
	 * @since    1.1.0
	 */
	public function enable_auto_redirects_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_auto_redirects]" value="1" <?php checked( $options['enable_auto_redirects'] ?? '1', '1' ); ?>>
			<?php _e( 'Create 301 redirects automatically when a published post or page slug changes', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php _e( 'Generated redirects are stored separately from manual rules so manual overrides always win.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the manual redirect rules field.
	 *
	 * @since    1.1.0
	 */
	public function redirect_rules_render() {
		$options = $this->settings->get_all();
		?>
		<textarea name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[redirect_rules]" rows="8" cols="50" class="large-text code"><?php echo esc_textarea( $options['redirect_rules'] ?? '' ); ?></textarea>
		<p class="description"><?php _e( 'Add one rule per line using the format: /old-path /new-path 301', 'lightweight-seo' ); ?></p>
		<p class="description"><?php _e( 'Targets can be local paths or full URLs. External hosts must be explicitly approved through WordPress’s allowed_redirect_hosts filter. Supported status codes: 301, 302, 307, 308.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render generated redirect rules.
	 *
	 * @since    1.1.0
	 */
	public function generated_redirect_rules_render() {
		if ( ! $this->is_module_enabled( 'redirects' ) ) {
			echo '<p class="description">' . esc_html__( 'The Redirects module is disabled.', 'lightweight-seo' ) . '</p>';

			return;
		}

		$rules = get_option( 'lightweight_seo_generated_redirect_rules', array() );

		if ( empty( $rules ) ) {
			echo '<p class="description">' . __( 'No automatic redirects have been generated yet.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-generated-redirects"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Source', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Target', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Updated', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $rules, 0, 10 ) as $rule ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $rule['source'] ?? '' ) . '</code></td>';
			echo '<td><code>' . esc_html( $rule['target'] ?? '' ) . '</code></td>';
			echo '<td>' . esc_html( $rule['updated_at'] ?? '' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render an exportable redirect rules snapshot.
	 *
	 * @since    1.1.0
	 */
	public function redirect_export_render() {
		if ( ! $this->is_module_enabled( 'redirects' ) ) {
			echo '<p class="description">' . esc_html__( 'Enable Redirects by saving a valid manual rule or automatic slug redirects.', 'lightweight-seo' ) . '</p>';

			return;
		}

		$this->load_redirects_service_for_read_only_admin();
		$redirects_service = new Lightweight_SEO_Redirects_Service( $this->settings, false );
		$rules             = $redirects_service->get_all_redirect_rules();

		if ( empty( $rules ) ) {
			echo '<p class="description">' . __( 'No redirect rules are available to export yet.', 'lightweight-seo' ) . '</p>';

			return;
		}

		$lines = array();
		echo '<label for="lightweight-seo-redirect-search" class="screen-reader-text">' . esc_html__( 'Search redirect rules', 'lightweight-seo' ) . '</label>';
		echo '<input type="search" id="lightweight-seo-redirect-search" class="regular-text" placeholder="' . esc_attr( __( 'Search redirect rules', 'lightweight-seo' ) ) . '">';
		echo '<table class="widefat striped lightweight-seo-redirect-table"><thead><tr><th>' . esc_html__( 'Source', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Target', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Status', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( $rules as $rule ) {
			$lines[] = implode(
				' ',
				array(
					$rule['source'] ?? '',
					$rule['target'] ?? '',
					$rule['status'] ?? 301,
				)
			);
			echo '<tr><td><code>' . esc_html( $rule['source'] ?? '' ) . '</code></td><td><code>' . esc_html( $rule['target'] ?? '' ) . '</code></td><td>' . esc_html( (string) ( $rule['status'] ?? 301 ) ) . '</td></tr>';
		}

		echo '</tbody></table>';
		echo '<textarea rows="8" cols="50" class="large-text code" readonly="readonly">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
		echo '<p class="description">' . __( 'Copy this snapshot to migrate rules or keep an external backup. Manual rules can be imported by pasting them into the redirect rules field above.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render redirect chain and loop health warnings.
	 *
	 * @since    1.1.0
	 */
	public function redirect_health_render() {
		if ( ! $this->is_module_enabled( 'redirects' ) ) {
			echo '<p class="description">' . esc_html__( 'No redirect health checks run while the module is disabled.', 'lightweight-seo' ) . '</p>';

			return;
		}

		$this->load_redirects_service_for_read_only_admin();
		$redirects_service = new Lightweight_SEO_Redirects_Service( $this->settings, false );
		$report            = $redirects_service->get_redirect_health_report();
		$issues            = array_merge(
			array_map(
				function ( $item ) {
					$item['type'] = 'loop';

					return $item;
				},
				$report['loops']
			),
			array_map(
				function ( $item ) {
					$item['type'] = 'chain';

					return $item;
				},
				$report['chains']
			)
		);

		if ( empty( $issues ) ) {
			echo '<p class="description">' . __( 'No redirect chains or loops detected.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-redirect-health"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Type', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Source', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Path', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $issues, 0, 10 ) as $issue ) {
			echo '<tr>';
			echo '<td>' . esc_html( ucfirst( $issue['type'] ) ) . '</td>';
			echo '<td><code>' . esc_html( $issue['source'] ?? '' ) . '</code></td>';
			echo '<td><code>' . esc_html( implode( ' -> ', $issue['sequence'] ?? array() ) ) . '</code></td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Load redirect parsing only when its legacy read-only report is rendered.
	 * No redirect hooks are registered by these views.
	 *
	 * @return void
	 */
	private function load_redirects_service_for_read_only_admin() {
		if ( ! class_exists( 'Lightweight_SEO_Redirects_Service', false ) ) {
			require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-redirects-service.php';
		}
	}

	/** Read bounded module state without loading a module implementation. */
	private function is_module_enabled( $module_id ) {
		$states = get_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME, array() );

		return ! empty( $states[ sanitize_key( $module_id ) ] );
	}

	/**
	 * Render the internal link health report.
	 *
	 * @since    1.1.0
	 */
	public function internal_link_report_render() {
		$internal_links_service = new Lightweight_SEO_Internal_Links_Service( $this->post_meta, false, $this->settings );
		$report                 = $internal_links_service->get_report();

		if ( empty( $report['pages_scanned'] ) ) {
			echo '<p class="description">' . __( 'No published indexable content is available to analyze yet.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<p class="description">';
		echo esc_html(
			sprintf(
				/* translators: 1: pages scanned, 2: internal links found, 3: report timestamp */
				__( 'Scanned %1$d pages and found %2$d internal links. Last generated: %3$s.', 'lightweight-seo' ),
				(int) $report['pages_scanned'],
				(int) $report['internal_links'],
				(string) ( $report['generated_at'] ?? '' )
			)
		);
		echo '</p>';

		$this->render_internal_link_table(
			__( 'Orphan Pages', 'lightweight-seo' ),
			$report['orphan_pages'] ?? array(),
			function ( $row ) {
				echo '<tr>';
				echo '<td><a href="' . esc_url( $row['url'] ?? '' ) . '">' . esc_html( $row['title'] ?? '' ) . '</a></td>';
				echo '<td><code>' . esc_html( $row['path'] ?? '' ) . '</code></td>';
				echo '<td>' . esc_html( (string) ( $row['inbound'] ?? 0 ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['outbound'] ?? 0 ) ) . '</td>';
				echo '</tr>';
			}
		);

		$this->render_internal_link_table(
			__( 'Weakly Linked Pages', 'lightweight-seo' ),
			$report['weak_pages'] ?? array(),
			function ( $row ) {
				echo '<tr>';
				echo '<td><a href="' . esc_url( $row['url'] ?? '' ) . '">' . esc_html( $row['title'] ?? '' ) . '</a></td>';
				echo '<td><code>' . esc_html( $row['path'] ?? '' ) . '</code></td>';
				echo '<td>' . esc_html( (string) ( $row['inbound'] ?? 0 ) ) . '</td>';
				echo '<td>' . esc_html( (string) ( $row['outbound'] ?? 0 ) ) . '</td>';
				echo '</tr>';
			}
		);

		$broken_links = $report['broken_links'] ?? array();

		if ( empty( $broken_links ) ) {
			echo '<p class="description">' . __( 'No broken internal links were detected in the scanned content.', 'lightweight-seo' ) . '</p>';
		} else {
			echo '<h3>' . esc_html__( 'Broken Internal Links', 'lightweight-seo' ) . '</h3>';
			echo '<div class="lightweight-seo-internal-links-table"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Source', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Source URL', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Missing Target', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

			foreach ( array_slice( $broken_links, 0, 10 ) as $broken_link ) {
				echo '<tr>';
				echo '<td>' . esc_html( $broken_link['source_title'] ?? '' ) . '</td>';
				echo '<td><code>' . esc_html( $broken_link['source_url'] ?? '' ) . '</code></td>';
				echo '<td><code>' . esc_html( $broken_link['target_path'] ?? '' ) . '</code></td>';
				echo '</tr>';
			}

			echo '</tbody></table></div>';
		}

		$this->render_internal_link_anchor_issues( $report['anchor_text_issues'] ?? array() );
		$this->render_internal_link_suggestions( $report['link_suggestions'] ?? array() );
		$this->render_internal_link_topic_clusters( $report['topic_clusters'] ?? array() );
	}

	/**
	 * Render the Search Console property field.
	 *
	 * @since    1.1.0
	 */
	public function search_console_property_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[search_console_property]" value="<?php echo esc_attr( $options['search_console_property'] ?? '' ); ?>" class="regular-text">
		<p class="description"><?php _e( 'Use either a URL-prefix property like https://example.com/ or a domain property like sc-domain:example.com.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the Search Console service-account JSON field.
	 *
	 * @since    1.1.0
	 */
	public function search_console_service_account_json_render() {
		$options = $this->settings->get_all();
		?>
		<textarea name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[search_console_service_account_json]" rows="8" cols="50" class="large-text code"><?php echo esc_textarea( $options['search_console_service_account_json'] ?? '' ); ?></textarea>
		<p class="description"><?php _e( 'Paste the full Google service-account JSON. The service-account email must have access to the configured Search Console property.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render a cached Search Console performance snapshot.
	 *
	 * @since    1.1.0
	 */
	public function search_console_report_render() {
		$search_console = new Lightweight_SEO_Search_Console_Service( $this->settings );
		$snapshot       = $search_console->get_snapshot();

		if ( ! $snapshot['configured'] ) {
			echo '<p class="description">' . __( 'Configure a Search Console property and service-account JSON to start syncing performance data.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<p class="description">';
		echo esc_html(
			sprintf(
				/* translators: 1: property identifier, 2: service-account email, 3: sync timestamp */
				__( 'Property: %1$s. Service account: %2$s. Last synced: %3$s.', 'lightweight-seo' ),
				(string) $snapshot['property'],
				(string) $snapshot['service_account_email'],
				(string) $snapshot['last_synced']
			)
		);
		echo '</p>';

		if ( ! empty( $snapshot['last_error'] ) ) {
			echo '<p class="description">' . esc_html( $snapshot['last_error'] ) . '</p>';
		}

		echo '<div class="lightweight-seo-search-console-summary"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Clicks', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Impressions', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Average CTR', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Average Position', 'lightweight-seo' ) . '</th></tr></thead><tbody><tr>';
		echo '<td>' . esc_html( (string) round( (float) ( $snapshot['totals']['clicks'] ?? 0 ) ) ) . '</td>';
		echo '<td>' . esc_html( (string) round( (float) ( $snapshot['totals']['impressions'] ?? 0 ) ) ) . '</td>';
		echo '<td>' . esc_html( $this->format_ctr_value( (float) ( $snapshot['totals']['ctr'] ?? 0 ) ) ) . '</td>';
		echo '<td>' . esc_html( number_format( (float) ( $snapshot['totals']['position'] ?? 0 ), 2 ) ) . '</td>';
		echo '</tr></tbody></table></div>';

		$this->render_search_console_pages_table(
			__( 'Low CTR Pages', 'lightweight-seo' ),
			$snapshot['low_ctr_pages'] ?? array()
		);
		$this->render_search_console_declines_table(
			__( 'Declining Pages', 'lightweight-seo' ),
			$snapshot['declining_pages'] ?? array()
		);
		$this->render_search_console_issues_table(
			__( 'Indexation Issues', 'lightweight-seo' ),
			$snapshot['indexation_issues'] ?? array()
		);
		$this->render_search_console_canonical_table(
			__( 'Canonical Mismatches', 'lightweight-seo' ),
			$snapshot['canonical_mismatches'] ?? array()
		);
		$this->render_search_console_submitted_sitemaps_table(
			__( 'Submitted Sitemaps', 'lightweight-seo' ),
			$snapshot['submitted_sitemaps'] ?? array()
		);

		$sitemaps = $snapshot['sitemaps'] ?? array();

		echo '<h3>' . esc_html__( 'Sitemaps', 'lightweight-seo' ) . '</h3>';

		if ( empty( $sitemaps ) ) {
			echo '<p class="description">' . __( 'No sitemap data is available yet for this property.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-search-console-sitemaps"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Path', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Type', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Last Submitted', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Errors', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Warnings', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $sitemaps, 0, 10 ) as $sitemap ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $sitemap['path'] ?? '' ) . '</code></td>';
			echo '<td>' . esc_html( $sitemap['type'] ?? '' ) . '</td>';
			echo '<td>' . esc_html( $sitemap['last_submitted'] ?? '' ) . '</td>';
			echo '<td>' . esc_html( (string) ( $sitemap['errors'] ?? 0 ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $sitemap['warnings'] ?? 0 ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render a standard internal link report table.
	 *
	 * @since    1.1.0
	 * @param    string      $heading        Table heading.
	 * @param    array       $rows           Report rows.
	 * @param    callable    $row_renderer   Row rendering callback.
	 * @return   void
	 */
	private function render_internal_link_table( $heading, $rows, $row_renderer ) {
		echo '<h3>' . esc_html( $heading ) . '</h3>';

		if ( empty( $rows ) ) {
			echo '<p class="description">' . esc_html__( 'Nothing to report for this segment.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-internal-links-table"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Page', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Path', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Inbound Links', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Outbound Links', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $rows, 0, 10 ) as $row ) {
			call_user_func( $row_renderer, $row );
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render anchor-text issues discovered during internal-link analysis.
	 *
	 * @since    1.1.0
	 * @param    array    $rows    Anchor issue rows.
	 * @return   void
	 */
	private function render_internal_link_anchor_issues( $rows ) {
		echo '<h3>' . esc_html__( 'Anchor Text Issues', 'lightweight-seo' ) . '</h3>';

		if ( empty( $rows ) ) {
			echo '<p class="description">' . esc_html__( 'Nothing to report for this segment.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-internal-links-table"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Page', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Path', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Anchors', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Recommended Anchor', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $rows, 0, 10 ) as $row ) {
			echo '<tr>';
			echo '<td><a href="' . esc_url( $row['url'] ?? '' ) . '">' . esc_html( $row['title'] ?? '' ) . '</a></td>';
			echo '<td><code>' . esc_html( $row['path'] ?? '' ) . '</code></td>';
			echo '<td>' . esc_html( implode( ', ', $row['anchors'] ?? array() ) ) . '</td>';
			echo '<td>' . esc_html( $row['recommended_anchor'] ?? '' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render suggested internal links for weakly linked targets.
	 *
	 * @since    1.1.0
	 * @param    array    $rows    Link suggestion rows.
	 * @return   void
	 */
	private function render_internal_link_suggestions( $rows ) {
		echo '<h3>' . esc_html__( 'Suggested Internal Links', 'lightweight-seo' ) . '</h3>';

		if ( empty( $rows ) ) {
			echo '<p class="description">' . esc_html__( 'Nothing to report for this segment.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-internal-links-table"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Target Page', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Target Path', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Recommended Anchor', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Suggested Sources', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $rows, 0, 10 ) as $row ) {
			$source_labels = array();

			foreach ( $row['suggestions'] ?? array() as $suggestion ) {
				$source_labels[] = sprintf(
					'%1$s (%2$s)',
					(string) ( $suggestion['source_title'] ?? '' ),
					implode(
						', ',
						array_filter(
							array_merge(
								$suggestion['matched_terms'] ?? array(),
								$suggestion['matched_phrases'] ?? array()
							)
						)
					)
				);
			}

			echo '<tr>';
			echo '<td><a href="' . esc_url( $row['target_url'] ?? '' ) . '">' . esc_html( $row['target_title'] ?? '' ) . '</a></td>';
			echo '<td><code>' . esc_html( $row['target_path'] ?? '' ) . '</code></td>';
			echo '<td>' . esc_html( $row['recommended_anchor'] ?? '' ) . '</td>';
			echo '<td>' . esc_html( implode( '; ', $source_labels ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render topic-cluster and hub-page reporting.
	 *
	 * @since    1.1.0
	 * @param    array    $rows    Topic cluster rows.
	 * @return   void
	 */
	private function render_internal_link_topic_clusters( $rows ) {
		echo '<h3>' . esc_html__( 'Topic Clusters', 'lightweight-seo' ) . '</h3>';

		if ( empty( $rows ) ) {
			echo '<p class="description">' . esc_html__( 'Nothing to report for this segment.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-internal-links-table"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Topic', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Hub Page', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Members', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Sample Pages', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $rows, 0, 10 ) as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $row['topic'] ?? '' ) . '</td>';
			echo '<td><a href="' . esc_url( $row['hub_url'] ?? '' ) . '">' . esc_html( $row['hub_title'] ?? '' ) . '</a></td>';
			echo '<td>' . esc_html( (string) ( $row['member_count'] ?? 0 ) ) . '</td>';
			echo '<td>' . esc_html( implode( ', ', $row['members'] ?? array() ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render a Search Console pages table.
	 *
	 * @since    1.1.0
	 * @param    string    $heading    Table heading.
	 * @param    array     $rows       Search Analytics rows.
	 * @return   void
	 */
	private function render_search_console_pages_table( $heading, $rows ) {
		echo '<h3>' . esc_html( $heading ) . '</h3>';

		if ( empty( $rows ) ) {
			echo '<p class="description">' . esc_html__( 'Nothing to report for this segment.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-search-console-pages"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Page', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Clicks', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Impressions', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'CTR', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Position', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $rows, 0, 10 ) as $row ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $row['page'] ?? '' ) . '</code></td>';
			echo '<td>' . esc_html( (string) round( (float) ( $row['clicks'] ?? 0 ) ) ) . '</td>';
			echo '<td>' . esc_html( (string) round( (float) ( $row['impressions'] ?? 0 ) ) ) . '</td>';
			echo '<td>' . esc_html( $this->format_ctr_value( (float) ( $row['ctr'] ?? 0 ) ) ) . '</td>';
			echo '<td>' . esc_html( number_format( (float) ( $row['position'] ?? 0 ), 2 ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render a Search Console declining pages table.
	 *
	 * @since    1.1.0
	 * @param    string    $heading    Table heading.
	 * @param    array     $rows       Declining page rows.
	 * @return   void
	 */
	private function render_search_console_declines_table( $heading, $rows ) {
		echo '<h3>' . esc_html( $heading ) . '</h3>';

		if ( empty( $rows ) ) {
			echo '<p class="description">' . esc_html__( 'Nothing to report for this segment.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-search-console-pages"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Page', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Current Clicks', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Previous Clicks', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Change', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'CTR', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $rows, 0, 10 ) as $row ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $row['page'] ?? '' ) . '</code></td>';
			echo '<td>' . esc_html( (string) round( (float) ( $row['current_clicks'] ?? 0 ) ) ) . '</td>';
			echo '<td>' . esc_html( (string) round( (float) ( $row['previous_clicks'] ?? 0 ) ) ) . '</td>';
			echo '<td>' . esc_html( (string) round( (float) ( $row['click_delta'] ?? 0 ) ) ) . '</td>';
			echo '<td>' . esc_html( $this->format_ctr_value( (float) ( $row['ctr'] ?? 0 ) ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render a Search Console indexation issues table.
	 *
	 * @since    1.1.0
	 * @param    string    $heading    Table heading.
	 * @param    array     $rows       Indexation issue rows.
	 * @return   void
	 */
	private function render_search_console_issues_table( $heading, $rows ) {
		echo '<h3>' . esc_html( $heading ) . '</h3>';

		if ( empty( $rows ) ) {
			echo '<p class="description">' . esc_html__( 'Nothing to report for this segment.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-search-console-pages"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Page', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Type', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Details', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $rows, 0, 10 ) as $row ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $row['page'] ?? '' ) . '</code></td>';
			echo '<td>' . esc_html( ucfirst( (string) ( $row['type'] ?? '' ) ) ) . '</td>';
			echo '<td>' . esc_html( $row['details'] ?? '' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render a Search Console canonical mismatches table.
	 *
	 * @since    1.1.0
	 * @param    string    $heading    Table heading.
	 * @param    array     $rows       Canonical mismatch rows.
	 * @return   void
	 */
	private function render_search_console_canonical_table( $heading, $rows ) {
		echo '<h3>' . esc_html( $heading ) . '</h3>';

		if ( empty( $rows ) ) {
			echo '<p class="description">' . esc_html__( 'Nothing to report for this segment.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-search-console-pages"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Page', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'User Canonical', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Google Canonical', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $rows, 0, 10 ) as $row ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $row['page'] ?? '' ) . '</code></td>';
			echo '<td><code>' . esc_html( $row['user_canonical'] ?? '' ) . '</code></td>';
			echo '<td><code>' . esc_html( $row['google_canonical'] ?? '' ) . '</code></td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render Search Console sitemap submission results.
	 *
	 * @since    1.1.0
	 * @param    string    $heading    Table heading.
	 * @param    array     $rows       Submitted sitemap rows.
	 * @return   void
	 */
	private function render_search_console_submitted_sitemaps_table( $heading, $rows ) {
		echo '<h3>' . esc_html( $heading ) . '</h3>';

		if ( empty( $rows ) ) {
			echo '<p class="description">' . esc_html__( 'Nothing to report for this segment.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-search-console-pages"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Sitemap', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Submitted', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Error', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $rows, 0, 10 ) as $row ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $row['path'] ?? '' ) . '</code></td>';
			echo '<td>' . esc_html( ! empty( $row['submitted'] ) ? __( 'Yes', 'lightweight-seo' ) : __( 'No', 'lightweight-seo' ) ) . '</td>';
			echo '<td>' . esc_html( $row['error'] ?? '' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Format a CTR value for display.
	 *
	 * @since    1.1.0
	 * @param    float    $ctr    Raw CTR decimal.
	 * @return   string
	 */
	private function format_ctr_value( $ctr ) {
		return number_format( $ctr * 100, 2 ) . '%';
	}

	/**
	 * Render the minimum Discover image width field.
	 *
	 * @since    1.1.0
	 */
	public function discover_min_image_width_render() {
		$options = $this->settings->get_all();
		?>
		<input type="number" min="1" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[discover_min_image_width]" value="<?php echo esc_attr( $options['discover_min_image_width'] ?? 1200 ); ?>" class="small-text">
		<?php
	}

	/**
	 * Render the minimum Discover image height field.
	 *
	 * @since    1.1.0
	 */
	public function discover_min_image_height_render() {
		$options = $this->settings->get_all();
		?>
		<input type="number" min="1" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[discover_min_image_height]" value="<?php echo esc_attr( $options['discover_min_image_height'] ?? 900 ); ?>" class="small-text">
		<?php
	}

	/**
	 * Render the image SEO audit report.
	 *
	 * @since    1.1.0
	 */
	public function image_discover_report_render() {
		$image_audit_service = new Lightweight_SEO_Image_Audit_Service( $this->settings, $this->post_meta, false );
		$report              = $image_audit_service->get_report();

		echo '<p class="description">' . esc_html(
			sprintf(
				/* translators: 1: minimum image width, 2: minimum image height, 3: report timestamp */
				__( 'Discover image audit checks featured images against a minimum of %1$d x %2$d pixels. Last generated: %3$s.', 'lightweight-seo' ),
				(int) ( $report['minimum_width'] ?? 0 ),
				(int) ( $report['minimum_height'] ?? 0 ),
				(string) ( $report['generated_at'] ?? '' )
			)
		) . '</p>';

		$this->render_image_discover_table(
			__( 'Missing Featured Images', 'lightweight-seo' ),
			$report['missing_featured_images'] ?? array(),
			function ( $row ) {
				echo '<tr>';
				echo '<td><a href="' . esc_url( $row['url'] ?? '' ) . '">' . esc_html( $row['title'] ?? '' ) . '</a></td>';
				echo '<td>' . esc_html( __( 'No featured image', 'lightweight-seo' ) ) . '</td>';
				echo '</tr>';
			}
		);

		$this->render_image_discover_table(
			__( 'Missing Alt Text', 'lightweight-seo' ),
			$report['missing_alt_text'] ?? array(),
			function ( $row ) {
				echo '<tr>';
				echo '<td><a href="' . esc_url( $row['url'] ?? '' ) . '">' . esc_html( $row['title'] ?? '' ) . '</a></td>';
				echo '<td>' . esc_html( (string) ( $row['attachment_id'] ?? 0 ) ) . '</td>';
				echo '</tr>';
			}
		);

		$this->render_image_discover_table(
			__( 'Undersized Featured Images', 'lightweight-seo' ),
			$report['undersized_images'] ?? array(),
			function ( $row ) {
				echo '<tr>';
				echo '<td><a href="' . esc_url( $row['url'] ?? '' ) . '">' . esc_html( $row['title'] ?? '' ) . '</a></td>';
				echo '<td>' . esc_html( sprintf( '%1$d x %2$d', (int) ( $row['width'] ?? 0 ), (int) ( $row['height'] ?? 0 ) ) ) . '</td>';
				echo '</tr>';
			}
		);
	}

	/**
	 * Render a standard image audit table.
	 *
	 * @since    1.1.0
	 * @param    string      $heading        Table heading.
	 * @param    array       $rows           Report rows.
	 * @param    callable    $row_renderer   Row rendering callback.
	 * @return   void
	 */
	private function render_image_discover_table( $heading, $rows, $row_renderer ) {
		echo '<h3>' . esc_html( $heading ) . '</h3>';

		if ( empty( $rows ) ) {
			echo '<p class="description">' . esc_html__( 'Nothing to report for this segment.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-image-audit-table"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Page', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Details', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $rows, 0, 10 ) as $row ) {
			call_user_func( $row_renderer, $row );
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render the import source field.
	 *
	 * @since    1.1.0
	 */
	public function import_source_render() {
		$options = $this->settings->get_all();
		?>
		<select name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[import_source]">
			<option value="" <?php selected( $options['import_source'] ?? '', '' ); ?>><?php _e( 'Select a source', 'lightweight-seo' ); ?></option>
			<option value="yoast" <?php selected( $options['import_source'] ?? '', 'yoast' ); ?>><?php _e( 'Yoast SEO', 'lightweight-seo' ); ?></option>
			<option value="rank_math" <?php selected( $options['import_source'] ?? '', 'rank_math' ); ?>><?php _e( 'Rank Math', 'lightweight-seo' ); ?></option>
			<option value="aioseo" <?php selected( $options['import_source'] ?? '', 'aioseo' ); ?>><?php _e( 'All in One SEO', 'lightweight-seo' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Render bounded preview, import, and rollback actions.
	 *
	 * @since    1.1.0
	 */
	public function import_actions_render() {
		$options  = $this->settings->get_all();
		$importer = new Lightweight_SEO_Importer_Service( $this->post_meta );
		?>
		<p>
			<button type="submit" class="button" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[import_action]" value="preview"><?php _e( 'Preview next batch', 'lightweight-seo' ); ?></button>
			<button type="submit" class="button button-secondary" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[import_action]" value="import"><?php _e( 'Import next batch', 'lightweight-seo' ); ?></button>
			<?php if ( $importer->has_rollback() ) : ?>
				<button type="submit" class="button-link-delete" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[import_action]" value="rollback"><?php _e( 'Roll back last batch', 'lightweight-seo' ); ?></button>
			<?php endif; ?>
		</p>
		<p class="description">
			<?php
			printf(
				/* translators: %d: zero-based importer cursor. */
				esc_html__( 'The next batch starts after %d scanned posts. Changing the source resets this cursor.', 'lightweight-seo' ),
				absint( $options['import_cursor'] ?? 0 )
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
		$options = $this->settings->get_all();
		?>
		<textarea rows="4" cols="50" class="large-text code" readonly="readonly"><?php echo esc_textarea( $options['last_import_report'] ?? '' ); ?></textarea>
		<?php
	}

	/** Render explicit export/delete choices for retained legacy data. */
	public function legacy_data_transition_render() {
		$options         = (array) get_option( LIGHTWEIGHT_SEO_OPTION_NAME, array() );
		$legacy_404_logs = (array) get_option( 'lightweight_seo_404_logs', array() );
		$has_credentials = ! empty( $options['search_console_property'] ) || ! empty( $options['search_console_service_account_json'] );
		$has_keywords    = ! empty( $options['meta_keywords'] );
		$export_url      = wp_nonce_url(
			admin_url( 'admin-post.php?action=lightweight_seo_export_legacy_keywords' ),
			'lightweight_seo_export_legacy_keywords'
		);
		?>
		<p><?php _e( 'Retired data is never moved into another integration automatically.', 'lightweight-seo' ); ?></p>
		<?php if ( $has_credentials ) : ?>
			<details>
				<summary><?php _e( 'Review retained Search Console configuration', 'lightweight-seo' ); ?></summary>
				<p><strong><?php _e( 'Property:', 'lightweight-seo' ); ?></strong> <?php echo esc_html( $options['search_console_property'] ?? '' ); ?></p>
				<textarea rows="6" class="large-text code" readonly="readonly" aria-label="<?php echo esc_attr__( 'Retained Search Console service account JSON', 'lightweight-seo' ); ?>"><?php echo esc_textarea( $options['search_console_service_account_json'] ?? '' ); ?></textarea>
				<p class="description"><?php _e( 'Copy this only if you need an explicit private backup. Future Insights setup will require a new connection.', 'lightweight-seo' ); ?></p>
				<label><input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[delete_legacy_search_console_data]" value="1"> <?php _e( 'Permanently delete the retained property and private key when settings are saved', 'lightweight-seo' ); ?></label>
			</details>
		<?php else : ?>
			<p><?php _e( 'No legacy Search Console credentials are retained.', 'lightweight-seo' ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $legacy_404_logs ) ) : ?>
			<?php /* translators: %d: Number of retained legacy 404 log entries. */ ?>
			<p><label><input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[delete_legacy_404_logs]" value="1"> <?php printf( esc_html__( 'Permanently delete %d retained legacy 404 log entries when settings are saved', 'lightweight-seo' ), count( $legacy_404_logs ) ); ?></label></p>
		<?php endif; ?>
		<?php if ( $has_keywords || $this->has_legacy_post_keywords() ) : ?>
			<p><a class="button" href="<?php echo esc_url( $export_url ); ?>"><?php _e( 'Export legacy keywords CSV', 'lightweight-seo' ); ?></a></p>
			<p class="description"><?php _e( 'Stored keyword values remain available for export but are not edited or output by Lightweight SEO.', 'lightweight-seo' ); ?></p>
		<?php else : ?>
			<p><?php _e( 'No legacy keyword values were detected.', 'lightweight-seo' ); ?></p>
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
	 * Render the tracking section information
	 *
	 * @since    1.0.1
	 */
	public function tracking_section_callback() {
		$options = $this->settings->get_all();

		echo '<p>' . __( 'Use Google Tag Manager as the primary strategy. Direct GA4 and Meta Pixel IDs are alternatives and do not output while GTM is configured.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . __( 'Tracking is excluded from the selected roles and environments. Consent plugins can use the lightweight_seo_tracking_consent_granted filter, and CSP integrations can supply a nonce through lightweight_seo_tracking_script_nonce.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . __( 'GTM themes must call wp_body_open(). If they do not, Lightweight SEO adds a diagnostic comment to the page source and omits only the noscript fallback.', 'lightweight-seo' ) . '</p>';

		if ( ! empty( $options['gtm_container_id'] ) && ( ! empty( $options['ga4_measurement_id'] ) || ! empty( $options['facebook_pixel_id'] ) ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'GTM is active, so the direct GA4 and Meta Pixel alternatives are stored but suppressed to avoid duplicate events.', 'lightweight-seo' ) . '</p></div>';
		}
	}

	/**
	 * Render the GA4 measurement ID field
	 *
	 * @since    1.0.1
	 */
	public function ga4_measurement_id_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[ga4_measurement_id]" value="<?php echo esc_attr( $options['ga4_measurement_id'] ?? '' ); ?>" class="regular-text">
		<p class="description"><?php _e( 'Direct alternative: enter a GA4 Measurement ID (e.g., G-XXXXXXXXXX). It is suppressed when GTM is configured.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the GTM container ID field
	 *
	 * @since    1.0.1
	 */
	public function gtm_container_id_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[gtm_container_id]" value="<?php echo esc_attr( $options['gtm_container_id'] ?? '' ); ?>" class="regular-text">
		<p class="description"><?php _e( 'Recommended: enter a Google Tag Manager Container ID (e.g., GTM-XXXXXX).', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the Facebook Pixel ID field
	 *
	 * @since    1.0.1
	 */
	public function facebook_pixel_id_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[facebook_pixel_id]" value="<?php echo esc_attr( $options['facebook_pixel_id'] ?? '' ); ?>" class="regular-text">
		<p class="description"><?php _e( 'Enter a numeric Meta Pixel ID. This direct alternative is suppressed when GTM is configured.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/** Render role slugs that suppress tracking for logged-in users. */
	public function tracking_excluded_roles_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[tracking_excluded_roles]" value="<?php echo esc_attr( $options['tracking_excluded_roles'] ?? 'administrator' ); ?>" class="regular-text">
		<p class="description"><?php _e( 'Comma-separated WordPress role slugs, for example administrator, editor. Leave empty to include all logged-in roles.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/** Render environments that suppress tracking. */
	public function tracking_excluded_environments_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[tracking_excluded_environments]" value="<?php echo esc_attr( str_replace( "\n", ', ', $options['tracking_excluded_environments'] ?? "local\ndevelopment\nstaging" ) ); ?>" class="regular-text">
		<p class="description"><?php _e( 'Choose from local, development, staging, and production. WordPress defaults to production when no environment type is set.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/** Explain the bounded, experimental AI Discovery scope. */
	public function ai_discovery_section_callback() {
		echo '<p><strong>' . esc_html__( 'Experimental:', 'lightweight-seo' ) . '</strong> ' . esc_html__( 'These controls separate AI search access from model-training access and can publish a manually curated llms.txt index.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . esc_html__( 'Google Search ignores llms.txt for visibility and rankings. No setting can guarantee crawling, citation, training, inclusion, or ranking in any AI product.', 'lightweight-seo' ) . '</p>';
	}

	/** Render the explicit AI module toggle. */
	public function enable_ai_discovery_render() {
		$options = $this->settings->get_all();
		?>
		<label><input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_ai_discovery]" value="1" <?php checked( $options['enable_ai_discovery'] ?? '0', '1' ); ?>> <?php _e( 'Enable the experimental AI Discovery module', 'lightweight-seo' ); ?></label>
		<?php
	}

	/** Render search and user-directed crawler policy. */
	public function ai_search_crawlers_enabled_render() {
		$options = $this->settings->get_all();
		?>
		<label><input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[ai_search_crawlers_enabled]" value="1" <?php checked( $options['ai_search_crawlers_enabled'] ?? '1', '1' ); ?>> <?php _e( 'Allow bundled AI search and user-directed crawler tokens', 'lightweight-seo' ); ?></label>
		<p class="description"><?php _e( 'This affects documented search and user-fetch tokens such as OAI-SearchBot, Claude-SearchBot, and PerplexityBot. It does not guarantee discovery or citation.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/** Render model-training crawler policy separately from search access. */
	public function ai_training_crawlers_enabled_render() {
		$options = $this->settings->get_all();
		?>
		<label><input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[ai_training_crawlers_enabled]" value="1" <?php checked( $options['ai_training_crawlers_enabled'] ?? '0', '1' ); ?>> <?php _e( 'Allow bundled model-training crawler tokens', 'lightweight-seo' ); ?></label>
		<p class="description"><?php _e( 'Off blocks GPTBot, ClaudeBot, and Google-Extended. Google-Extended also controls some Gemini grounding uses, but does not affect Google Search inclusion or ranking.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/** Render the optional curated llms.txt endpoint toggle. */
	public function enable_llms_txt_render() {
		$options = $this->settings->get_all();
		?>
		<label><input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_llms_txt]" value="1" <?php checked( $options['enable_llms_txt'] ?? '0', '1' ); ?>> <?php _e( 'Publish an experimental /llms.txt Markdown index', 'lightweight-seo' ); ?></label>
		<p class="description"><?php _e( 'Only selected public page summaries are listed. No llms-full.txt or full-page Markdown copies are generated.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/** Render the bounded list of manually selected post and page IDs. */
	public function llms_txt_post_ids_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[llms_txt_post_ids]" value="<?php echo esc_attr( $options['llms_txt_post_ids'] ?? '' ); ?>" class="regular-text">
		<?php /* translators: %d: Maximum number of curated post or page IDs. */ ?>
		<p class="description"><?php printf( esc_html__( 'Enter up to %d comma-separated published post or page IDs. Drafts, private/password-protected, noindexed, redirected, external-canonical, or invalid URLs are excluded.', 'lightweight-seo' ), (int) Lightweight_SEO_Settings::MAX_LLMS_POSTS ); ?></p>
		<?php
	}

	/** Render deterministic, local-only readiness checks. */
	public function ai_readiness_render() {
		if ( ! $this->is_module_enabled( 'ai' ) || ! class_exists( 'Lightweight_SEO_AI_Discovery_Module', false ) ) {
			echo '<p>' . esc_html__( 'Enable and save the module to run readiness checks.', 'lightweight-seo' ) . '</p>';
			return;
		}

		$module = new Lightweight_SEO_AI_Discovery_Module( $this->settings, lightweight_seo_get_api(), 'admin' );

		echo '<ul class="lightweight-seo-checklist">';
		foreach ( $module->get_readiness_checks() as $check ) {
			echo '<li><strong>' . esc_html( strtoupper( $check['status'] ) ) . ' — ' . esc_html( $check['label'] ) . ':</strong> ' . esc_html( $check['details'] ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Validate a tracking ID against a strict format.
	 *
	 * @since    1.0.2
	 * @param    string     $value             The submitted value.
	 * @param    string     $pattern           The validation pattern.
	 * @param    string     $settings_error    The settings error code.
	 * @param    string     $message           The error message.
	 * @param    string     $existing_value    The existing saved value.
	 * @param    bool       $uppercase         Whether to normalize to uppercase.
	 * @return   string
	 */
	private function validate_tracking_id( $value, $pattern, $settings_error, $message, $existing_value = '', $uppercase = false ) {
		$sanitized_value = trim( sanitize_text_field( $value ) );

		if ( $uppercase ) {
			$sanitized_value = strtoupper( $sanitized_value );
		}

		if ( '' === $sanitized_value ) {
			return '';
		}

		if ( 1 === preg_match( $pattern, $sanitized_value ) ) {
			return $sanitized_value;
		}

		add_settings_error( LIGHTWEIGHT_SEO_OPTION_NAME, $settings_error, $message, 'error' );

		return $existing_value;
	}

	/**
	 * Normalize a Search Console property identifier.
	 *
	 * @since    1.1.0
	 * @param    string    $value             Submitted property identifier.
	 * @param    string    $existing_value    Existing stored value.
	 * @return   string
	 */
	private function normalize_search_console_property( $value, $existing_value = '' ) {
		$property = trim( sanitize_text_field( $value ) );

		if ( '' === $property ) {
			return '';
		}

		if ( 0 === strpos( $property, 'sc-domain:' ) ) {
			$domain = trim( substr( $property, strlen( 'sc-domain:' ) ) );

			if ( '' !== $domain ) {
				return 'sc-domain:' . $domain;
			}
		}

		if ( false !== filter_var( $property, FILTER_VALIDATE_URL ) ) {
			$scheme = strtolower( (string) wp_parse_url( $property, PHP_URL_SCHEME ) );

			if ( in_array( $scheme, array( 'http', 'https' ), true ) ) {
				$parts = wp_parse_url( $property );

				if ( empty( $parts['host'] ) ) {
					return $existing_value;
				}

				$normalized = $scheme . '://' . strtolower( (string) $parts['host'] );

				if ( ! empty( $parts['port'] ) ) {
					$normalized .= ':' . (int) $parts['port'];
				}

				$path = (string) ( $parts['path'] ?? '/' );
				$path = '/' . ltrim( $path, '/' );
				$path = trailingslashit( $path );

				return $normalized . $path;
			}
		}

		add_settings_error(
			LIGHTWEIGHT_SEO_OPTION_NAME,
			'invalid_search_console_property',
			__( 'Invalid Search Console property. Use either a URL-prefix property or sc-domain:example.com.', 'lightweight-seo' ),
			'error'
		);

		return $existing_value;
	}

	/**
	 * Normalize Search Console service-account JSON.
	 *
	 * @since    1.1.0
	 * @param    string    $value             Submitted JSON payload.
	 * @param    string    $existing_value    Existing stored value.
	 * @return   string
	 */
	private function normalize_search_console_service_account_json( $value, $existing_value = '' ) {
		$raw_json = trim( (string) $value );

		if ( '' === $raw_json ) {
			return '';
		}

		$decoded = json_decode( $raw_json, true );

		if ( ! is_array( $decoded ) || empty( $decoded['client_email'] ) || empty( $decoded['private_key'] ) ) {
			add_settings_error(
				LIGHTWEIGHT_SEO_OPTION_NAME,
				'invalid_search_console_service_account',
				__( 'Invalid Search Console service-account JSON. The payload must include client_email and private_key.', 'lightweight-seo' ),
				'error'
			);

			return $existing_value;
		}

		$normalized_payload = array(
			'client_email' => sanitize_text_field( $decoded['client_email'] ),
			'private_key'  => (string) $decoded['private_key'],
			'token_uri'    => ! empty( $decoded['token_uri'] ) ? esc_url_raw( $decoded['token_uri'] ) : 'https://oauth2.googleapis.com/token',
		);

		return (string) wp_json_encode( $normalized_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Keep the stored social image URL and attachment ID in sync.
	 *
	 * @since    1.0.2
	 * @param    string    $image_url             Submitted image URL.
	 * @param    int       $image_id              Submitted attachment ID.
	 * @param    string    $previous_image_url    Previously saved image URL.
	 * @param    int       $previous_image_id     Previously saved attachment ID.
	 * @return   array
	 */
	private function normalize_social_image( $image_url, $image_id, $previous_image_url = '', $previous_image_id = 0 ) {
		$image_url          = esc_url_raw( $image_url );
		$image_id           = absint( $image_id );
		$previous_image_url = esc_url_raw( $previous_image_url );
		$previous_image_id  = absint( $previous_image_id );

		if ( '' === $image_url ) {
			return array( $image_url, 0 );
		}

		if ( $image_id && $image_url !== $previous_image_url && $image_id === $previous_image_id ) {
			$attachment_url = wp_get_attachment_image_url( $image_id, 'full' );

			if ( empty( $attachment_url ) || $image_url !== $attachment_url ) {
				$image_id = 0;
			}
		}

		return array( $image_url, $image_id );
	}

	/**
	 * Sanitize and validate settings
	 *
	 * @since    1.0.0
	 * @param    array    $input    The settings array.
	 * @return   array
	 */
	public function validate_settings( $input ) {
		$existing_settings = $this->settings->get_all();
		$sanitized_input   = array();
		$active_tab        = sanitize_key( $input['settings_tab'] ?? 'all' );
		$checkbox_value    = function ( $key, $tab ) use ( $input, $existing_settings, $active_tab ) {
			if ( 'all' === $active_tab || $tab === $active_tab ) {
				return isset( $input[ $key ] ) ? '1' : '0';
			}

			return $existing_settings[ $key ] ?? '0';
		};

		if ( isset( $input['title_format'] ) ) {
			$sanitized_input['title_format'] = sanitize_text_field( $input['title_format'] );
		} else {
			$sanitized_input['title_format'] = $existing_settings['title_format'] ?? LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT;
		}

		if ( isset( $input['home_title_format'] ) ) {
			$sanitized_input['home_title_format'] = sanitize_text_field( $input['home_title_format'] );
		} else {
			$sanitized_input['home_title_format'] = $existing_settings['home_title_format'] ?? '%sitename% %sep% %tagline%';
		}

		if ( isset( $input['archive_title_format'] ) ) {
			$sanitized_input['archive_title_format'] = sanitize_text_field( $input['archive_title_format'] );
		} else {
			$sanitized_input['archive_title_format'] = $existing_settings['archive_title_format'] ?? '%title% %sep% %sitename%';
		}

		if ( isset( $input['search_title_format'] ) ) {
			$sanitized_input['search_title_format'] = sanitize_text_field( $input['search_title_format'] );
		} else {
			$sanitized_input['search_title_format'] = $existing_settings['search_title_format'] ?? 'Search Results for "%search%" %sep% %sitename%';
		}

		if ( isset( $input['meta_description'] ) ) {
			$sanitized_input['meta_description'] = sanitize_textarea_field( $input['meta_description'] );
		} else {
			$sanitized_input['meta_description'] = $existing_settings['meta_description'] ?? '';
		}

		if ( ! empty( $existing_settings['meta_keywords'] ) ) {
			$sanitized_input['meta_keywords'] = $existing_settings['meta_keywords'] ?? '';
		}

		$sanitized_input['noindex_search_results']           = $checkbox_value( 'noindex_search_results', 'indexation' );
		$sanitized_input['noindex_attachment_pages']         = $checkbox_value( 'noindex_attachment_pages', 'indexation' );
		$sanitized_input['enable_media_x_robots_headers']    = $checkbox_value( 'enable_media_x_robots_headers', 'indexation' );
		$sanitized_input['exclude_noindex_from_sitemaps']    = $checkbox_value( 'exclude_noindex_from_sitemaps', 'indexation' );
		$sanitized_input['exclude_redirected_from_sitemaps'] = $checkbox_value( 'exclude_redirected_from_sitemaps', 'indexation' );
		$sanitized_input['enable_schema_output']             = $checkbox_value( 'enable_schema_output', 'identity' );
		$sanitized_input['enable_local_business_schema']     = $checkbox_value( 'enable_local_business_schema', 'modules' );
		$sanitized_input['enable_hreflang_output']           = $checkbox_value( 'enable_hreflang_output', 'modules' );
		$sanitized_input['enable_hreflang_path_mirroring']   = $checkbox_value( 'enable_hreflang_path_mirroring', 'modules' );
		$sanitized_input['enable_auto_redirects']            = $checkbox_value( 'enable_auto_redirects', 'modules' );
		$sanitized_input['enable_ai_discovery']              = $checkbox_value( 'enable_ai_discovery', 'modules' );
		$sanitized_input['ai_search_crawlers_enabled']       = $checkbox_value( 'ai_search_crawlers_enabled', 'modules' );
		$sanitized_input['ai_training_crawlers_enabled']     = $checkbox_value( 'ai_training_crawlers_enabled', 'modules' );
		$sanitized_input['enable_llms_txt']                  = $checkbox_value( 'enable_llms_txt', 'modules' );
		$sanitized_input['delete_data_on_uninstall']         = $checkbox_value( 'delete_data_on_uninstall', 'tools' );
		$sanitized_input['default_max_image_preview']        = $this->settings->normalize_max_image_preview(
			$input['default_max_image_preview'] ?? ( $existing_settings['default_max_image_preview'] ?? 'large' ),
			'large'
		);

		if ( isset( $input['redirect_rules'] ) ) {
			$sanitized_input['redirect_rules'] = $this->settings->normalize_redirect_rules_input( $input['redirect_rules'] );
		} else {
			$sanitized_input['redirect_rules'] = $existing_settings['redirect_rules'] ?? '';
		}

		if ( isset( $input['organization_same_as'] ) ) {
			$raw_lines       = preg_split( "/\r\n|\n|\r/", (string) $input['organization_same_as'] );
			$sanitized_lines = array();

			foreach ( $raw_lines as $line ) {
				$url = esc_url_raw( trim( $line ) );

				if ( empty( $url ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
					continue;
				}

				$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );

				if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
					continue;
				}

				if ( ! empty( $url ) ) {
					$sanitized_lines[] = $url;
				}
			}

			$sanitized_input['organization_same_as'] = implode( "\n", array_values( array_unique( $sanitized_lines ) ) );
		} else {
			$sanitized_input['organization_same_as'] = $existing_settings['organization_same_as'] ?? '';
		}

		$local_business_input                                 = array_merge( $existing_settings, $input );
		$local_business_input['enable_local_business_schema'] = $sanitized_input['enable_local_business_schema'];

		if ( method_exists( $this->settings, 'normalize_local_business_input' ) ) {
			$local_business  = $this->settings->normalize_local_business_input( $local_business_input );
			$sanitized_input = array_merge( $sanitized_input, $local_business['values'] );

			if ( ! empty( $local_business['errors'] ) && in_array( $active_tab, array( 'all', 'modules' ), true ) ) {
				foreach ( $local_business['errors'] as $index => $message ) {
					add_settings_error( LIGHTWEIGHT_SEO_OPTION_NAME, 'invalid_local_business_' . $index, $message, 'error' );
				}

				$sanitized_input['enable_local_business_schema'] = '0';
			}
		} else {
			foreach ( array( 'type', 'name', 'phone', 'price_range', 'address_street', 'address_locality', 'address_region', 'address_postal_code', 'address_country', 'latitude', 'longitude', 'opening_hours', 'image' ) as $field ) {
				$key                     = 'local_business_' . $field;
				$sanitized_input[ $key ] = sanitize_text_field( $local_business_input[ $key ] ?? '' );
			}
		}

		if ( isset( $input['hreflang_mappings'] ) ) {
			$sanitized_input['hreflang_mappings'] = $this->settings->normalize_hreflang_mappings_input( $input['hreflang_mappings'] );
		} else {
			$sanitized_input['hreflang_mappings'] = $existing_settings['hreflang_mappings'] ?? '';
		}

		if ( isset( $input['llms_txt_post_ids'] ) ) {
			$sanitized_input['llms_txt_post_ids'] = $this->settings->normalize_llms_post_ids( $input['llms_txt_post_ids'] );
		} else {
			$sanitized_input['llms_txt_post_ids'] = $existing_settings['llms_txt_post_ids'] ?? '';
		}

		if ( empty( $input['delete_legacy_search_console_data'] ) && ! empty( $existing_settings['search_console_property'] ) ) {
			$sanitized_input['search_console_property'] = $existing_settings['search_console_property'] ?? '';
		}

		if ( empty( $input['delete_legacy_search_console_data'] ) && ! empty( $existing_settings['search_console_service_account_json'] ) ) {
			$sanitized_input['search_console_service_account_json'] = $existing_settings['search_console_service_account_json'] ?? '';
		}

		$sanitized_input['import_source']      = sanitize_key( $input['import_source'] ?? ( $existing_settings['import_source'] ?? '' ) );
		$sanitized_input['import_cursor']      = absint( $existing_settings['import_cursor'] ?? 0 );
		$sanitized_input['last_import_report'] = $existing_settings['last_import_report'] ?? '';

		if ( ( $existing_settings['import_source'] ?? '' ) !== $sanitized_input['import_source'] ) {
			$sanitized_input['import_cursor'] = 0;
			delete_option( Lightweight_SEO_Importer_Service::ROLLBACK_OPTION );
		}

		if ( ! empty( $input['delete_legacy_404_logs'] ) ) {
			delete_option( 'lightweight_seo_404_logs' );
		}

		if ( isset( $input['social_image'] ) ) {
			$sanitized_input['social_image'] = esc_url_raw( $input['social_image'] );
		} else {
			$sanitized_input['social_image'] = $existing_settings['social_image'] ?? '';
		}

		if ( isset( $input['social_image_id'] ) ) {
			$sanitized_input['social_image_id'] = absint( $input['social_image_id'] );
		} else {
			$sanitized_input['social_image_id'] = absint( $existing_settings['social_image_id'] ?? 0 );
		}

		list( $sanitized_input['social_image'], $sanitized_input['social_image_id'] ) = $this->normalize_social_image(
			$sanitized_input['social_image'],
			$sanitized_input['social_image_id'],
			$existing_settings['social_image'] ?? '',
			$existing_settings['social_image_id'] ?? 0
		);

		if ( isset( $input['ga4_measurement_id'] ) ) {
			$sanitized_input['ga4_measurement_id'] = $this->validate_tracking_id(
				$input['ga4_measurement_id'],
				'/^G-[A-Z0-9]+$/',
				'invalid_ga4_measurement_id',
				__( 'Invalid Google Analytics 4 Measurement ID. Use a value like G-XXXXXXXXXX.', 'lightweight-seo' ),
				$existing_settings['ga4_measurement_id'] ?? '',
				true
			);
		} else {
			$sanitized_input['ga4_measurement_id'] = $existing_settings['ga4_measurement_id'] ?? '';
		}

		if ( isset( $input['gtm_container_id'] ) ) {
			$sanitized_input['gtm_container_id'] = $this->validate_tracking_id(
				$input['gtm_container_id'],
				'/^GTM-[A-Z0-9]+$/',
				'invalid_gtm_container_id',
				__( 'Invalid Google Tag Manager Container ID. Use a value like GTM-XXXXXX.', 'lightweight-seo' ),
				$existing_settings['gtm_container_id'] ?? '',
				true
			);
		} else {
			$sanitized_input['gtm_container_id'] = $existing_settings['gtm_container_id'] ?? '';
		}

		if ( isset( $input['facebook_pixel_id'] ) ) {
			$sanitized_input['facebook_pixel_id'] = $this->validate_tracking_id(
				$input['facebook_pixel_id'],
				'/^\d+$/',
				'invalid_facebook_pixel_id',
				__( 'Invalid Facebook Pixel ID. Use a numeric value.', 'lightweight-seo' ),
				$existing_settings['facebook_pixel_id'] ?? ''
			);
		} else {
			$sanitized_input['facebook_pixel_id'] = $existing_settings['facebook_pixel_id'] ?? '';
		}

		foreach ( array( 'tracking_excluded_roles', 'tracking_excluded_environments' ) as $key ) {
			$value = $input[ $key ] ?? ( $existing_settings[ $key ] ?? '' );
			$keys  = method_exists( $this->settings, 'normalize_key_list' ) ? $this->settings->normalize_key_list( $value ) : array_filter( array_map( 'sanitize_key', preg_split( '/[\s,]+/', strtolower( (string) $value ) ) ) );

			if ( 'tracking_excluded_environments' === $key ) {
				$keys = array_intersect( $keys, array( 'local', 'development', 'staging', 'production' ) );
			}

			$sanitized_input[ $key ] = implode( "\n", array_values( array_unique( $keys ) ) );
		}

		$import_action = sanitize_key( $input['import_action'] ?? '' );

		if ( in_array( $import_action, array( 'preview', 'import', 'rollback' ), true ) ) {
			$importer = new Lightweight_SEO_Importer_Service( $this->post_meta );

			if ( 'rollback' === $import_action ) {
				$report                                = $importer->rollback_last_batch();
				$sanitized_input['import_cursor']      = absint( $report['offset'] ?? 0 );
				$sanitized_input['last_import_report'] = sprintf(
					/* translators: 1: restored posts. 2: restored fields. */
					__( 'Rolled back the latest batch: restored %1$d posts and %2$d fields.', 'lightweight-seo' ),
					absint( $report['restored_posts'] ?? 0 ),
					absint( $report['restored_fields'] ?? 0 )
				);
			} elseif ( in_array( $sanitized_input['import_source'], array( 'yoast', 'rank_math', 'aioseo' ), true ) ) {
				$report = 'preview' === $import_action
					? $importer->preview( $sanitized_input['import_source'], $sanitized_input['import_cursor'] )
					: $importer->import_batch( $sanitized_input['import_source'], $sanitized_input['import_cursor'] );

				if ( 'import' === $import_action ) {
					$sanitized_input['import_cursor'] = ! empty( $report['has_more'] ) ? absint( $report['next_offset'] ?? 0 ) : 0;
				}

				$sanitized_input['last_import_report'] = sprintf(
					/* translators: 1: action label. 2: source. 3: scanned posts. 4: eligible/imported posts. 5: changed fields. 6: skipped occupied fields. */
					__( '%1$s %2$s batch: scanned %3$d posts, found %4$d eligible posts and %5$d fillable fields; skipped %6$d occupied fields.', 'lightweight-seo' ),
					'preview' === $import_action ? __( 'Previewed', 'lightweight-seo' ) : __( 'Imported', 'lightweight-seo' ),
					$sanitized_input['import_source'],
					absint( $report['scanned_posts'] ?? 0 ),
					absint( 'preview' === $import_action ? ( $report['eligible_posts'] ?? 0 ) : ( $report['imported_posts'] ?? 0 ) ),
					absint( $report['updated_fields'] ?? 0 ),
					absint( $report['skipped_fields'] ?? 0 )
				);
			} else {
				$sanitized_input['last_import_report'] = __( 'Select a supported import source first.', 'lightweight-seo' );
			}
		}

		return $sanitized_input;
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		$tabs        = $this->get_admin_tabs();
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';

		if ( ! isset( $tabs[ $current_tab ] ) ) {
			$current_tab = 'overview';
		}
		?>
		<div class="wrap lightweight-seo-admin">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>
			<nav class="nav-tab-wrapper lightweight-seo-admin-nav" aria-label="<?php echo esc_attr( __( 'Lightweight SEO settings', 'lightweight-seo' ) ); ?>">
				<?php foreach ( $tabs as $tab_id => $label ) : ?>
					<a class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>" <?php echo $current_tab === $tab_id ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->plugin_name . '&tab=' . $tab_id ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			if ( 'overview' === $current_tab ) {
				$this->render_setup_overview();
			} elseif ( 'developer' === $current_tab ) {
				$this->render_developer_information();
			} else {
				$this->render_settings_tab( $current_tab );
			}
			?>
		</div>
		<?php
	}

	/** @return array */
	private function get_admin_tabs() {
		return array(
			'overview'   => __( 'Overview', 'lightweight-seo' ),
			'appearance' => __( 'Search appearance', 'lightweight-seo' ),
			'indexation' => __( 'Content and indexation', 'lightweight-seo' ),
			'identity'   => __( 'Schema and identity', 'lightweight-seo' ),
			'modules'    => __( 'Modules', 'lightweight-seo' ),
			'tools'      => __( 'Tools and migration', 'lightweight-seo' ),
			'developer'  => __( 'Developer API', 'lightweight-seo' ),
		);
	}

	/** Render the short, dismissible setup checklist. */
	private function render_setup_overview() {
		$options = $this->settings->get_all();
		$items   = array(
			array( ! empty( get_bloginfo( 'name' ) ), __( 'Confirm site identity', 'lightweight-seo' ), 'identity' ),
			array( ! empty( $options['title_format'] ) && ! empty( $options['meta_description'] ), __( 'Choose title and description defaults', 'lightweight-seo' ), 'appearance' ),
			array( isset( $options['noindex_search_results'] ), __( 'Review indexation defaults', 'lightweight-seo' ), 'indexation' ),
			array( ! empty( $options['social_image'] ) || ! empty( $options['social_image_id'] ), __( 'Select a default social image', 'lightweight-seo' ), 'appearance' ),
			array( false, __( 'Review optional modules', 'lightweight-seo' ), 'modules' ),
			array( false, __( 'Verify generated output on one published page', 'lightweight-seo' ), '' ),
		);
		?>
		<section class="lightweight-seo-setup" aria-labelledby="lightweight-seo-setup-title">
			<div class="lightweight-seo-section-heading"><div><h2 id="lightweight-seo-setup-title"><?php _e( 'Essential setup', 'lightweight-seo' ); ?></h2><p><?php _e( 'Finish the essentials in any order. This is a checklist, not a required wizard.', 'lightweight-seo' ); ?></p></div><button type="button" class="button-link lightweight-seo-dismiss-checklist"><?php _e( 'Dismiss checklist', 'lightweight-seo' ); ?></button></div>
			<ul class="lightweight-seo-setup-list">
				<?php foreach ( $items as $item ) : ?>
					<li class="<?php echo $item[0] ? 'is-complete' : 'is-pending'; ?>">
						<span class="dashicons <?php echo esc_attr( $item[0] ? 'dashicons-yes-alt' : 'dashicons-marker' ); ?>" aria-hidden="true"></span>
						<span><?php echo esc_html( $item[1] ); ?></span>
						<?php if ( $item[2] ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->plugin_name . '&tab=' . $item[2] ) ); ?>"><?php _e( 'Review', 'lightweight-seo' ); ?></a>
						<?php elseif ( ! $item[0] ) : ?>
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php _e( 'Open site', 'lightweight-seo' ); ?><span class="screen-reader-text"> <?php _e( '(opens in a new tab)', 'lightweight-seo' ); ?></span></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}

	/** Render a selected subset of the existing Settings API fields. */
	private function render_settings_tab( $tab ) {
		$maps = array(
			'appearance' => array( 'lightweight_seo_general_section' => array( 'title_format', 'home_title_format', 'archive_title_format', 'search_title_format', 'meta_description', 'social_image' ) ),
			'indexation' => array(
				'lightweight_seo_indexation_section' => true,
				'lightweight_seo_sitemap_section'    => array( 'exclude_noindex_from_sitemaps', 'exclude_redirected_from_sitemaps' ),
			),
			'identity'   => array( 'lightweight_seo_schema_section' => array( 'enable_schema_output', 'organization_same_as' ) ),
			'modules'    => array(
				'lightweight_seo_schema_section'       => array( 'enable_local_business_schema', 'local_business_details', 'enable_hreflang_output', 'hreflang_mappings', 'enable_hreflang_path_mirroring' ),
				'lightweight_seo_redirects_section'    => true,
				'lightweight_seo_tracking_section'     => true,
				'lightweight_seo_ai_discovery_section' => true,
			),
			'tools'      => array( 'lightweight_seo_migration_section' => true ),
		);
		?>
		<form method="post" action="options.php" class="lightweight-seo-settings-form">
			<?php settings_fields( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>
			<input type="hidden" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[settings_tab]" value="<?php echo esc_attr( $tab ); ?>">
			<?php $this->render_selected_settings_sections( $maps[ $tab ] ?? array() ); ?>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render Settings API sections without exposing unrelated screens.
	 *
	 * @param array $section_map Section IDs and allowed fields.
	 */
	private function render_selected_settings_sections( $section_map ) {
		global $wp_settings_fields, $wp_settings_sections;

		foreach ( $section_map as $section_id => $allowed_fields ) {
			$section = $wp_settings_sections[ $this->plugin_name ][ $section_id ] ?? null;

			if ( empty( $section ) ) {
				continue;
			}

			echo '<section class="lightweight-seo-settings-section">';
			echo '<h2>' . esc_html( $section['title'] ) . '</h2>';

			if ( ! empty( $section['callback'] ) ) {
				call_user_func( $section['callback'], $section );
			}

			echo '<table class="form-table" role="presentation">';

			foreach ( (array) ( $wp_settings_fields[ $this->plugin_name ][ $section_id ] ?? array() ) as $field_id => $field ) {
				if ( true !== $allowed_fields && ! in_array( $field_id, $allowed_fields, true ) ) {
					continue;
				}

				echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
				call_user_func( $field['callback'], $field['args'] ?? array() );
				echo '</td></tr>';
			}

			echo '</table></section>';
		}
	}

	/** Render the stable public extension contract summary. */
	private function render_developer_information() {
		?>
		<section class="lightweight-seo-settings-section">
			<h2><?php _e( 'Developer API', 'lightweight-seo' ); ?></h2>
			<p><?php _e( 'Extensions can read normalized SEO facts through the versioned facade without reading private settings or credentials.', 'lightweight-seo' ); ?></p>
			<table class="widefat striped"><tbody><tr><th><?php _e( 'Plugin version', 'lightweight-seo' ); ?></th><td><code><?php echo esc_html( LIGHTWEIGHT_SEO_VERSION ); ?></code></td></tr><tr><th><?php _e( 'API version', 'lightweight-seo' ); ?></th><td><code><?php echo esc_html( LIGHTWEIGHT_SEO_API_VERSION ); ?></code></td></tr><tr><th><?php _e( 'Accessor', 'lightweight-seo' ); ?></th><td><code>lightweight_seo_get_api()</code></td></tr><tr><th><?php _e( 'Ready hook', 'lightweight-seo' ); ?></th><td><code>lightweight_seo_loaded</code></td></tr></tbody></table>
		</section>
		<?php
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @since    1.0.0
	 */
	public function enqueue_admin_scripts( $hook ) {
		$screen                     = get_current_screen();
		$is_plugin_page             = 'toplevel_page_' . $this->plugin_name === $hook;
		$is_public_post_type_screen = $screen && 'post' === $screen->base && in_array( $screen->post_type, $this->post_meta->get_supported_post_types(), true );

		// Only load scripts on our plugin page or supported post edit screens
		if ( ! $is_plugin_page && ! $is_public_post_type_screen ) {
			return;
		}

		// Enqueue the WordPress media uploader
		wp_enqueue_media();

		// Enqueue our admin script
		wp_enqueue_script(
			$this->plugin_name . '-admin-script',
			LIGHTWEIGHT_SEO_PLUGIN_URL . 'admin/js/lightweight-seo-admin.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			$this->plugin_name . '-admin-script',
			'lightweightSeoAdmin',
			array(
				'mediaTitle'  => __( 'Select or Upload Image', 'lightweight-seo' ),
				'mediaButton' => __( 'Use this image', 'lightweight-seo' ),
				'previewAlt'  => __( 'Preview', 'lightweight-seo' ),
			)
		);

		// Enqueue our admin styles
		wp_enqueue_style(
			$this->plugin_name . '-admin-style',
			LIGHTWEIGHT_SEO_PLUGIN_URL . 'admin/css/lightweight-seo-admin.css',
			array(),
			$this->version
		);
	}
}
