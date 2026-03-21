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

		// Enqueue admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Register the administration menu for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {
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

		$sanitized_input['enable_meta_keywords'] = isset( $input['enable_meta_keywords'] ) ? '1' : '0';

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
