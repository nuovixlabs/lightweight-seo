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
				__( 'Lightweight SEO safe mode is active because %s is also running. Lightweight SEO title, meta, and schema output is disabled to avoid duplicate SEO markup. Redirects, sitemaps, Search Console, and content audits remain available.', 'lightweight-seo' ),
				implode( ', ', $conflicting_plugins )
			)
		);
		echo '</p></div>';
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

		// Meta Keywords
		add_settings_field(
			'meta_keywords',
			__( 'Default Meta Keywords', 'lightweight-seo' ),
			array( $this, 'meta_keywords_render' ),
			$this->plugin_name,
			'lightweight_seo_general_section'
		);

		// Meta Keywords Output Toggle
		add_settings_field(
			'enable_meta_keywords',
			__( 'Output Meta Keywords', 'lightweight-seo' ),
			array( $this, 'enable_meta_keywords_render' ),
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
			'enable_image_sitemaps',
			__( 'Attachment Image Sitemap', 'lightweight-seo' ),
			array( $this, 'enable_image_sitemaps_render' ),
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

		add_settings_field(
			'enable_video_sitemaps',
			__( 'Attachment Video Sitemap', 'lightweight-seo' ),
			array( $this, 'enable_video_sitemaps_render' ),
			$this->plugin_name,
			'lightweight_seo_sitemap_section'
		);

		add_settings_field(
			'enable_news_sitemaps',
			__( 'Recent News Sitemap', 'lightweight-seo' ),
			array( $this, 'enable_news_sitemaps_render' ),
			$this->plugin_name,
			'lightweight_seo_sitemap_section'
		);

		add_settings_field(
			'submit_sitemaps_to_search_console',
			__( 'Search Console Submission', 'lightweight-seo' ),
			array( $this, 'submit_sitemaps_to_search_console_render' ),
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
			'enable_product_schema',
			__( 'Product Schema', 'lightweight-seo' ),
			array( $this, 'enable_product_schema_render' ),
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

		// Redirects Section
		add_settings_section(
			'lightweight_seo_redirects_section',
			__( 'Redirects & 404 Monitoring', 'lightweight-seo' ),
			array( $this, 'redirects_section_callback' ),
			$this->plugin_name
		);

		add_settings_field(
			'enable_404_monitor',
			__( '404 Monitor', 'lightweight-seo' ),
			array( $this, 'enable_404_monitor_render' ),
			$this->plugin_name,
			'lightweight_seo_redirects_section'
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

		add_settings_field(
			'recent_404_logs',
			__( 'Recent 404s', 'lightweight-seo' ),
			array( $this, 'recent_404_logs_render' ),
			$this->plugin_name,
			'lightweight_seo_redirects_section'
		);

		// Internal Linking Section
		add_settings_section(
			'lightweight_seo_internal_links_section',
			__( 'Internal Linking', 'lightweight-seo' ),
			array( $this, 'internal_links_section_callback' ),
			$this->plugin_name
		);

		add_settings_field(
			'internal_link_report',
			__( 'Link Health Report', 'lightweight-seo' ),
			array( $this, 'internal_link_report_render' ),
			$this->plugin_name,
			'lightweight_seo_internal_links_section'
		);

		// Image SEO & Discover Section
		add_settings_section(
			'lightweight_seo_image_discover_section',
			__( 'Image SEO & Discover', 'lightweight-seo' ),
			array( $this, 'image_discover_section_callback' ),
			$this->plugin_name
		);

		add_settings_field(
			'discover_min_image_width',
			__( 'Minimum Featured Image Width', 'lightweight-seo' ),
			array( $this, 'discover_min_image_width_render' ),
			$this->plugin_name,
			'lightweight_seo_image_discover_section'
		);

		add_settings_field(
			'discover_min_image_height',
			__( 'Minimum Featured Image Height', 'lightweight-seo' ),
			array( $this, 'discover_min_image_height_render' ),
			$this->plugin_name,
			'lightweight_seo_image_discover_section'
		);

		add_settings_field(
			'image_discover_report',
			__( 'Image SEO Audit', 'lightweight-seo' ),
			array( $this, 'image_discover_report_render' ),
			$this->plugin_name,
			'lightweight_seo_image_discover_section'
		);

		// Search Console Section
		add_settings_section(
			'lightweight_seo_search_console_section',
			__( 'Search Console', 'lightweight-seo' ),
			array( $this, 'search_console_section_callback' ),
			$this->plugin_name
		);

		add_settings_field(
			'search_console_property',
			__( 'Property', 'lightweight-seo' ),
			array( $this, 'search_console_property_render' ),
			$this->plugin_name,
			'lightweight_seo_search_console_section'
		);

		add_settings_field(
			'search_console_service_account_json',
			__( 'Service Account JSON', 'lightweight-seo' ),
			array( $this, 'search_console_service_account_json_render' ),
			$this->plugin_name,
			'lightweight_seo_search_console_section'
		);

		add_settings_field(
			'search_console_report',
			__( 'Search Performance Snapshot', 'lightweight-seo' ),
			array( $this, 'search_console_report_render' ),
			$this->plugin_name,
			'lightweight_seo_search_console_section'
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
			'run_import',
			__( 'Run Import', 'lightweight-seo' ),
			array( $this, 'run_import_render' ),
			$this->plugin_name,
			'lightweight_seo_migration_section'
		);

		add_settings_field(
			'import_report',
			__( 'Last Import Report', 'lightweight-seo' ),
			array( $this, 'import_report_render' ),
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

		// Google Analytics 4
		add_settings_field(
			'ga4_measurement_id',
			__( 'Google Analytics 4 Measurement ID', 'lightweight-seo' ),
			array( $this, 'ga4_measurement_id_render' ),
			$this->plugin_name,
			'lightweight_seo_tracking_section'
		);

		// Google Tag Manager
		add_settings_field(
			'gtm_container_id',
			__( 'Google Tag Manager Container ID', 'lightweight-seo' ),
			array( $this, 'gtm_container_id_render' ),
			$this->plugin_name,
			'lightweight_seo_tracking_section'
		);

		// Facebook Pixel
		add_settings_field(
			'facebook_pixel_id',
			__( 'Facebook Pixel ID', 'lightweight-seo' ),
			array( $this, 'facebook_pixel_id_render' ),
			$this->plugin_name,
			'lightweight_seo_tracking_section'
		);
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
			<?php _e( 'Send X-Robots-Tag headers for attachment pages and direct media/document requests', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php _e( 'Useful for PDFs and media files that should stay out of the index even without HTML meta tags.', 'lightweight-seo' ); ?></p>
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
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_address_country]" value="<?php echo esc_attr( $options['local_business_address_country'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'Country', 'lightweight-seo' ) ); ?>"></p>
		<p>
			<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_latitude]" value="<?php echo esc_attr( $options['local_business_latitude'] ?? '' ); ?>" class="small-text" placeholder="<?php echo esc_attr( __( 'Latitude', 'lightweight-seo' ) ); ?>">
			<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_longitude]" value="<?php echo esc_attr( $options['local_business_longitude'] ?? '' ); ?>" class="small-text" placeholder="<?php echo esc_attr( __( 'Longitude', 'lightweight-seo' ) ); ?>">
		</p>
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
		<p class="description"><?php _e( 'Use one mapping per line in the format: en-US https://en.example.com or x-default https://example.com. Root URLs will automatically reuse the current page path.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the redirects section information.
	 *
	 * @since    1.1.0
	 */
	public function redirects_section_callback() {
		echo '<p>' . __( 'Manage simple redirect rules, automatically preserve traffic after slug changes, and review recent 404s captured by the plugin.', 'lightweight-seo' ) . '</p>';
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
		echo '<p>' . __( 'Import saved SEO metadata from Yoast SEO, Rank Math, or All in One SEO into Lightweight SEO fields.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render the 404 monitor toggle field.
	 *
	 * @since    1.1.0
	 */
	public function enable_404_monitor_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_404_monitor]" value="1" <?php checked( $options['enable_404_monitor'] ?? '1', '1' ); ?>>
			<?php _e( 'Log 404 requests so broken URLs can be reviewed in admin', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php _e( 'Recent 404s are stored in a capped list to keep the plugin lightweight.', 'lightweight-seo' ); ?></p>
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
		<p class="description"><?php _e( 'Targets can be local paths or full URLs. Supported status codes: 301, 302, 307, 308.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render generated redirect rules.
	 *
	 * @since    1.1.0
	 */
	public function generated_redirect_rules_render() {
		$rules = get_option( Lightweight_SEO_Redirects_Service::GENERATED_RULES_OPTION_NAME, array() );

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
		$redirects_service = new Lightweight_SEO_Redirects_Service( $this->settings, false );
		$rules             = $redirects_service->get_all_redirect_rules();

		if ( empty( $rules ) ) {
			echo '<p class="description">' . __( 'No redirect rules are available to export yet.', 'lightweight-seo' ) . '</p>';

			return;
		}

		$lines = array();

		foreach ( $rules as $rule ) {
			$lines[] = implode(
				' ',
				array(
					$rule['source'] ?? '',
					$rule['target'] ?? '',
					$rule['status'] ?? 301,
				)
			);
		}

		echo '<textarea rows="8" cols="50" class="large-text code" readonly="readonly">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
		echo '<p class="description">' . __( 'Copy this snapshot to migrate rules or keep an external backup. Manual rules can be imported by pasting them into the redirect rules field above.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render redirect chain and loop health warnings.
	 *
	 * @since    1.1.0
	 */
	public function redirect_health_render() {
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
	 * Render a recent 404 log summary.
	 *
	 * @since    1.1.0
	 */
	public function recent_404_logs_render() {
		$logs = get_option( Lightweight_SEO_Redirects_Service::LOG_OPTION_NAME, array() );

		if ( empty( $logs ) ) {
			echo '<p class="description">' . __( 'No 404s have been logged yet.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-404-log"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Path', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Hits', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Last Seen', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Referrer', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( array_values( $logs ), 0, 10 ) as $log ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $log['path'] ?? '' ) . '</code></td>';
			echo '<td>' . esc_html( (string) ( $log['hits'] ?? 0 ) ) . '</td>';
			echo '<td>' . esc_html( $log['last_seen'] ?? '' ) . '</td>';
			echo '<td>' . esc_html( $log['referer'] ?? '' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render the internal link health report.
	 *
	 * @since    1.1.0
	 */
	public function internal_link_report_render() {
		$internal_links_service = new Lightweight_SEO_Internal_Links_Service( $this->post_meta, false );
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
	 * Render the run-import field.
	 *
	 * @since    1.1.0
	 */
	public function run_import_render() {
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[run_import]" value="1">
			<?php _e( 'Run the selected import the next time settings are saved', 'lightweight-seo' ); ?>
		</label>
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

	/**
	 * Render the tracking section information
	 *
	 * @since    1.0.1
	 */
	public function tracking_section_callback() {
		echo '<p>' . __( 'Add your tracking codes to integrate analytics and marketing tools. These will be automatically added to your site.', 'lightweight-seo' ) . '</p>';
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
		<p class="description"><?php _e( 'Enter your Google Analytics 4 Measurement ID (e.g., G-XXXXXXXXXX)', 'lightweight-seo' ); ?></p>
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
		<p class="description"><?php _e( 'Enter your Google Tag Manager Container ID (e.g., GTM-XXXXXX)', 'lightweight-seo' ); ?></p>
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
		<p class="description"><?php _e( 'Enter your Facebook Pixel ID', 'lightweight-seo' ); ?></p>
		<?php
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
				return $property;
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

		if ( isset( $input['meta_keywords'] ) ) {
			$sanitized_input['meta_keywords'] = sanitize_text_field( $input['meta_keywords'] );
		} else {
			$sanitized_input['meta_keywords'] = $existing_settings['meta_keywords'] ?? '';
		}

		$sanitized_input['enable_meta_keywords']              = isset( $input['enable_meta_keywords'] ) ? '1' : '0';
		$sanitized_input['noindex_search_results']            = isset( $input['noindex_search_results'] ) ? '1' : '0';
		$sanitized_input['noindex_attachment_pages']          = isset( $input['noindex_attachment_pages'] ) ? '1' : '0';
		$sanitized_input['enable_media_x_robots_headers']     = isset( $input['enable_media_x_robots_headers'] ) ? '1' : '0';
		$sanitized_input['exclude_noindex_from_sitemaps']     = isset( $input['exclude_noindex_from_sitemaps'] ) ? '1' : '0';
		$sanitized_input['exclude_redirected_from_sitemaps']  = isset( $input['exclude_redirected_from_sitemaps'] ) ? '1' : '0';
		$sanitized_input['enable_image_sitemaps']             = isset( $input['enable_image_sitemaps'] ) ? '1' : '0';
		$sanitized_input['enable_video_sitemaps']             = isset( $input['enable_video_sitemaps'] ) ? '1' : '0';
		$sanitized_input['enable_news_sitemaps']              = isset( $input['enable_news_sitemaps'] ) ? '1' : '0';
		$sanitized_input['enable_schema_output']              = isset( $input['enable_schema_output'] ) ? '1' : '0';
		$sanitized_input['enable_product_schema']             = isset( $input['enable_product_schema'] ) ? '1' : '0';
		$sanitized_input['enable_local_business_schema']      = isset( $input['enable_local_business_schema'] ) ? '1' : '0';
		$sanitized_input['enable_hreflang_output']            = isset( $input['enable_hreflang_output'] ) ? '1' : '0';
		$sanitized_input['submit_sitemaps_to_search_console'] = isset( $input['submit_sitemaps_to_search_console'] ) ? '1' : '0';
		$sanitized_input['enable_404_monitor']                = isset( $input['enable_404_monitor'] ) ? '1' : '0';
		$sanitized_input['enable_auto_redirects']             = isset( $input['enable_auto_redirects'] ) ? '1' : '0';
		$sanitized_input['default_max_image_preview']         = $this->settings->normalize_max_image_preview(
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

		$sanitized_input['local_business_type']                = sanitize_text_field( $input['local_business_type'] ?? ( $existing_settings['local_business_type'] ?? 'LocalBusiness' ) );
		$sanitized_input['local_business_name']                = sanitize_text_field( $input['local_business_name'] ?? ( $existing_settings['local_business_name'] ?? '' ) );
		$sanitized_input['local_business_phone']               = sanitize_text_field( $input['local_business_phone'] ?? ( $existing_settings['local_business_phone'] ?? '' ) );
		$sanitized_input['local_business_price_range']         = sanitize_text_field( $input['local_business_price_range'] ?? ( $existing_settings['local_business_price_range'] ?? '' ) );
		$sanitized_input['local_business_address_street']      = sanitize_text_field( $input['local_business_address_street'] ?? ( $existing_settings['local_business_address_street'] ?? '' ) );
		$sanitized_input['local_business_address_locality']    = sanitize_text_field( $input['local_business_address_locality'] ?? ( $existing_settings['local_business_address_locality'] ?? '' ) );
		$sanitized_input['local_business_address_region']      = sanitize_text_field( $input['local_business_address_region'] ?? ( $existing_settings['local_business_address_region'] ?? '' ) );
		$sanitized_input['local_business_address_postal_code'] = sanitize_text_field( $input['local_business_address_postal_code'] ?? ( $existing_settings['local_business_address_postal_code'] ?? '' ) );
		$sanitized_input['local_business_address_country']     = sanitize_text_field( $input['local_business_address_country'] ?? ( $existing_settings['local_business_address_country'] ?? '' ) );
		$sanitized_input['local_business_latitude']            = sanitize_text_field( $input['local_business_latitude'] ?? ( $existing_settings['local_business_latitude'] ?? '' ) );
		$sanitized_input['local_business_longitude']           = sanitize_text_field( $input['local_business_longitude'] ?? ( $existing_settings['local_business_longitude'] ?? '' ) );
		$sanitized_input['local_business_opening_hours']       = sanitize_textarea_field( $input['local_business_opening_hours'] ?? ( $existing_settings['local_business_opening_hours'] ?? '' ) );

		if ( isset( $input['hreflang_mappings'] ) ) {
			$lines           = preg_split( "/\r\n|\n|\r/", (string) $input['hreflang_mappings'] );
			$sanitized_lines = array();

			foreach ( $lines as $line ) {
				$line = trim( (string) $line );

				if ( empty( $line ) ) {
					continue;
				}

				$parts = preg_split( '/\s+/', $line, 2 );

				if ( 2 !== count( $parts ) ) {
					continue;
				}

				$language = sanitize_text_field( $parts[0] );
				$url      = esc_url_raw( $parts[1] );

				if ( empty( $language ) || empty( $url ) || false === filter_var( str_replace( '%path%', 'path', $url ), FILTER_VALIDATE_URL ) ) {
					continue;
				}

				$sanitized_lines[] = $language . ' ' . $url;
			}

			$sanitized_input['hreflang_mappings'] = implode( "\n", array_values( array_unique( $sanitized_lines ) ) );
		} else {
			$sanitized_input['hreflang_mappings'] = $existing_settings['hreflang_mappings'] ?? '';
		}

		if ( isset( $input['search_console_property'] ) ) {
			$sanitized_input['search_console_property'] = $this->normalize_search_console_property(
				$input['search_console_property'],
				$existing_settings['search_console_property'] ?? ''
			);
		} else {
			$sanitized_input['search_console_property'] = $existing_settings['search_console_property'] ?? '';
		}

		if ( isset( $input['search_console_service_account_json'] ) ) {
			$sanitized_input['search_console_service_account_json'] = $this->normalize_search_console_service_account_json(
				$input['search_console_service_account_json'],
				$existing_settings['search_console_service_account_json'] ?? ''
			);
		} else {
			$sanitized_input['search_console_service_account_json'] = $existing_settings['search_console_service_account_json'] ?? '';
		}

		$sanitized_input['discover_min_image_width']  = max( 1, absint( $input['discover_min_image_width'] ?? ( $existing_settings['discover_min_image_width'] ?? 1200 ) ) );
		$sanitized_input['discover_min_image_height'] = max( 1, absint( $input['discover_min_image_height'] ?? ( $existing_settings['discover_min_image_height'] ?? 900 ) ) );
		$sanitized_input['import_source']             = sanitize_key( $input['import_source'] ?? ( $existing_settings['import_source'] ?? '' ) );
		$sanitized_input['last_import_report']        = $existing_settings['last_import_report'] ?? '';

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

		if ( ! empty( $input['run_import'] ) && in_array( $sanitized_input['import_source'], array( 'yoast', 'rank_math', 'aioseo' ), true ) ) {
			if ( ! class_exists( 'Lightweight_SEO_Importer_Service' ) ) {
				require_once __DIR__ . '/class-lightweight-seo-importer-service.php';
			}

			$importer = new Lightweight_SEO_Importer_Service( $this->post_meta );
			$report   = $importer->import( $sanitized_input['import_source'] );

			$sanitized_input['last_import_report'] = sprintf(
				/* translators: 1: import source, 2: scanned posts, 3: imported posts, 4: updated fields */
				__( 'Imported from %1$s. Scanned %2$d posts, updated %3$d posts, and changed %4$d fields.', 'lightweight-seo' ),
				$sanitized_input['import_source'],
				(int) ( $report['scanned_posts'] ?? 0 ),
				(int) ( $report['imported_posts'] ?? 0 ),
				(int) ( $report['updated_fields'] ?? 0 )
			);
		}

		return $sanitized_input;
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( LIGHTWEIGHT_SEO_OPTION_NAME );
				do_settings_sections( $this->plugin_name );
				submit_button();
				?>
			</form>
		</div>
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
