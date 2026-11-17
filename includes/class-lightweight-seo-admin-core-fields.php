<?php
/**
 * Settings fields for the Search appearance, Content and indexation, and Schema and identity tabs.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Renders the core SEO settings fields.
 */
class Lightweight_SEO_Admin_Core_Fields {

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings    $settings
	 */
	private $settings;

	/**
	 * Initialize the renderer.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings    $settings    Shared settings service.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Print a single-line text setting.
	 *
	 * @since    1.1.0
	 * @param    string    $key        Settings key.
	 * @param    string    $fallback   Value used when nothing is saved.
	 * @param    string    $help       Help text.
	 * @return   void
	 */
	private function render_title_format( $key, $fallback, $help ) {
		$options = $this->settings->get_all();
		?>
		<input type="text" id="<?php echo esc_attr( 'lightweight_seo_' . $key ); ?>" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( wp_specialchars_decode( $options[ $key ] ?? $fallback, ENT_QUOTES ) ); ?>" class="regular-text" data-lwseo-tokens>
		<p class="lwseo-help"><?php echo esc_html( $help ); ?></p>
		<?php
	}

	/**
	 * Print a checkbox setting.
	 *
	 * @since    1.1.0
	 * @param    string    $key        Settings key.
	 * @param    string    $fallback   Stored value used when nothing is saved.
	 * @param    string    $label      Checkbox label.
	 * @param    string    $help       Help text.
	 * @return   void
	 */
	private function render_choice( $key, $fallback, $label, $help ) {
		$options = $this->settings->get_all();
		$checked = '1' === (string) ( $options[ $key ] ?? $fallback );

		Lightweight_SEO_Admin_UI::choice( LIGHTWEIGHT_SEO_OPTION_NAME . '[' . $key . ']', $checked, $label, $help );
	}

	/**
	 * Render the title variables above the title format fields.
	 *
	 * @since    1.0.0
	 */
	public function general_section_callback() {
		Lightweight_SEO_Admin_UI::tokens( array( '%title%', '%sitename%', '%tagline%', '%sep%' ) );
	}

	/**
	 * Render the title format field
	 *
	 * @since    1.0.0
	 */
	public function title_format_render() {
		$this->render_title_format( 'title_format', LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT, __( 'Used for posts and pages. Example: &#37;title&#37; – &#37;sitename&#37;', 'lightweight-seo' ) );
	}

	/**
	 * Render the home title format field.
	 *
	 * @since    1.1.0
	 */
	public function home_title_format_render() {
		$this->render_title_format( 'home_title_format', '%sitename% %sep% %tagline%', __( 'Used for the homepage. Example: &#37;sitename&#37; &#37;sep&#37; &#37;tagline&#37;', 'lightweight-seo' ) );
	}

	/**
	 * Render the archive title format field.
	 *
	 * @since    1.1.0
	 */
	public function archive_title_format_render() {
		$this->render_title_format( 'archive_title_format', '%title% %sep% %sitename%', __( 'Used for archive, taxonomy, and author pages. Example: &#37;title&#37; &#37;sep&#37; &#37;sitename&#37;', 'lightweight-seo' ) );
	}

	/**
	 * Render the search title format field.
	 *
	 * @since    1.1.0
	 */
	public function search_title_format_render() {
		$this->render_title_format( 'search_title_format', 'Search Results for "%search%" %sep% %sitename%', __( 'Used for internal search results. This format also supports &#37;search&#37;.', 'lightweight-seo' ) );
	}

	/**
	 * Render the meta description field
	 *
	 * @since    1.0.0
	 */
	public function meta_description_render() {
		$options = $this->settings->get_all();
		?>
		<textarea id="lightweight_seo_meta_description" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[meta_description]" rows="3" class="large-text"><?php echo esc_textarea( $options['meta_description'] ?? '' ); ?></textarea>
		<p class="lwseo-help"><?php esc_html_e( 'Used for the homepage. Other pages use their own SEO description, or a short summary of their excerpt or content.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the social image field
	 *
	 * @since    1.0.0
	 */
	public function social_image_render() {
		$options = $this->settings->get_all();

		Lightweight_SEO_Admin_UI::image_field(
			array(
				'url'      => $this->settings->get_social_image_url(),
				'id'       => $options['social_image_id'] ?? 0,
				'url_name' => LIGHTWEIGHT_SEO_OPTION_NAME . '[social_image]',
				'id_name'  => LIGHTWEIGHT_SEO_OPTION_NAME . '[social_image_id]',
				'url_id'   => 'lightweight_seo_social_image',
				'id_id'    => 'lightweight_seo_social_image_id',
				'alt'      => __( 'Default social image', 'lightweight-seo' ),
			)
		);
		?>
		<p class="lwseo-help"><?php esc_html_e( 'Shown when a page without its own image is shared. Recommended size: 1200 × 630 pixels.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Warn when WordPress itself discourages indexing, which outranks these controls.
	 *
	 * @since    1.1.0
	 */
	public function indexation_section_callback() {
		if ( '0' === (string) get_option( 'blog_public', '1' ) ) {
			Lightweight_SEO_Admin_UI::callout( __( 'WordPress is set to discourage search engines from indexing this site (Settings → Reading). That setting applies before the controls below.', 'lightweight-seo' ), 'warning' );
		}
	}

	/**
	 * Render the search results noindex field.
	 *
	 * @since    1.1.0
	 */
	public function noindex_search_results_render() {
		$this->render_choice( 'noindex_search_results', '1', __( 'Hide internal search result pages from search engines', 'lightweight-seo' ), __( 'Adds a noindex directive, which is recommended for most sites.', 'lightweight-seo' ) );
	}

	/**
	 * Render the attachment pages noindex field.
	 *
	 * @since    1.1.0
	 */
	public function noindex_attachment_pages_render() {
		$this->render_choice( 'noindex_attachment_pages', '1', __( 'Hide attachment pages from search engines', 'lightweight-seo' ), __( 'Adds a noindex directive, which is recommended unless attachment pages are used as landing pages.', 'lightweight-seo' ) );
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
		<select id="lightweight_seo_default_max_image_preview" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[default_max_image_preview]">
			<option value="large" <?php selected( $normalized_value, 'large' ); ?>><?php esc_html_e( 'Large', 'lightweight-seo' ); ?></option>
			<option value="standard" <?php selected( $normalized_value, 'standard' ); ?>><?php esc_html_e( 'Standard', 'lightweight-seo' ); ?></option>
			<option value="none" <?php selected( $normalized_value, 'none' ); ?>><?php esc_html_e( 'None', 'lightweight-seo' ); ?></option>
		</select>
		<p class="lwseo-help"><?php esc_html_e( 'Sets how large an image search engines may show beside your pages, through the max-image-preview directive.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the media X-Robots header field.
	 *
	 * @since    1.1.0
	 */
	public function enable_media_x_robots_headers_render() {
		$this->render_choice( 'enable_media_x_robots_headers', '1', __( 'Send the same instruction as an HTTP header on attachment pages', 'lightweight-seo' ), __( 'Adds an X-Robots-Tag header; files served directly by the web server or a CDN need it set there instead.', 'lightweight-seo' ) );
	}

	/**
	 * Render the address of the core sitemap index.
	 *
	 * @since    1.1.0
	 */
	public function sitemap_section_callback() {
		$sitemap_url = home_url( '/wp-sitemap.xml' );

		echo '<p class="lwseo-help">' . esc_html__( 'Sitemap index:', 'lightweight-seo' ) . ' <a href="' . esc_url( $sitemap_url ) . '" target="_blank" rel="noopener noreferrer"><code>' . esc_html( $sitemap_url ) . '</code><span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'lightweight-seo' ) . '</span></a></p>';
	}

	/**
	 * Render the exclude noindex content from sitemaps field.
	 *
	 * @since    1.1.0
	 */
	public function exclude_noindex_from_sitemaps_render() {
		$this->render_choice( 'exclude_noindex_from_sitemaps', '1', __( 'Leave hidden pages out of the sitemap', 'lightweight-seo' ), __( 'Keeps the sitemap to pages that search engines may show, which is recommended.', 'lightweight-seo' ) );
	}

	/**
	 * Render the redirected URL sitemap exclusion field.
	 *
	 * @since    1.1.0
	 */
	public function exclude_redirected_from_sitemaps_render() {
		$this->render_choice( 'exclude_redirected_from_sitemaps', '1', __( 'Leave redirected pages out of the sitemap', 'lightweight-seo' ), __( 'Keeps the sitemap from listing addresses that currently redirect somewhere else.', 'lightweight-seo' ) );
	}

	/**
	 * Explain where the organization logo comes from.
	 *
	 * @since    1.1.0
	 */
	public function schema_section_callback() {
		echo '<p class="lwseo-help">' . esc_html__( 'The default social image is used as the organization logo when available.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render the schema output toggle field.
	 *
	 * @since    1.1.0
	 */
	public function enable_schema_output_render() {
		$this->render_choice( 'enable_schema_output', '1', __( 'Output core Organization, WebSite, Article, and Breadcrumb schema', 'lightweight-seo' ), __( 'Disable this if your theme or another plugin already outputs equivalent structured data.', 'lightweight-seo' ) );
	}

	/**
	 * Render the organization sameAs field.
	 *
	 * @since    1.1.0
	 */
	public function organization_same_as_render() {
		$options = $this->settings->get_all();
		?>
		<textarea id="lightweight_seo_organization_same_as" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[organization_same_as]" rows="5" class="large-text code"><?php echo esc_textarea( $options['organization_same_as'] ?? '' ); ?></textarea>
		<p class="lwseo-help"><?php esc_html_e( 'Add one profile URL per line for your organization, such as social profiles or knowledge sources.', 'lightweight-seo' ); ?></p>
		<?php
	}
}
