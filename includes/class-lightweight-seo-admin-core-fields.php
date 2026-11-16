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
	 * Render the general section information
	 *
	 * @since    1.0.0
	 */
	public function general_section_callback() {
		echo '<p>' . esc_html__( 'Configure the global SEO settings for your site. These will be used as defaults for all pages unless overridden.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . esc_html__( 'Available variables for title format: &#37;title&#37;, &#37;sitename&#37;, &#37;tagline&#37;, &#37;sep&#37;', 'lightweight-seo' ) . '</p>';
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
			<p class="description"><?php esc_html_e( 'Format for page titles. Example: &#37;title&#37; – &#37;sitename&#37;', 'lightweight-seo' ); ?></p>
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
		<p class="description"><?php esc_html_e( 'Format for the homepage title. Example: &#37;sitename&#37; &#37;sep&#37; &#37;tagline&#37;', 'lightweight-seo' ); ?></p>
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
		<p class="description"><?php esc_html_e( 'Format for archive, taxonomy, and author titles. Example: &#37;title&#37; &#37;sep&#37; &#37;sitename&#37;', 'lightweight-seo' ); ?></p>
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
		<p class="description"><?php esc_html_e( 'Format for internal search titles. Available variables include &#37;search&#37;, &#37;sitename&#37;, and &#37;sep&#37;.', 'lightweight-seo' ); ?></p>
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
		<p class="description"><?php esc_html_e( 'Used for the homepage. Other pages use their own SEO description, or a short summary of their excerpt or content.', 'lightweight-seo' ); ?></p>
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
			<button type="button" class="button button-secondary" id="lightweight_seo_upload_image"><?php esc_html_e( 'Upload Image', 'lightweight-seo' ); ?></button>
			<?php if ( ! empty( $image_url ) ) : ?>
				<div class="lightweight-seo-image-preview">
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php esc_attr_e( 'Preview', 'lightweight-seo' ); ?>" style="max-width: 200px; margin-top: 10px;">
				</div>
			<?php endif; ?>
		</div>
		<p class="description"><?php esc_html_e( 'Default image for social media sharing.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the indexation section information.
	 *
	 * @since    1.1.0
	 */
	public function indexation_section_callback() {
		echo '<p>' . esc_html__( 'Control how your site should be indexed and previewed in search results.', 'lightweight-seo' ) . '</p>';
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
			<?php esc_html_e( 'Add a noindex directive to internal search result pages', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Recommended for most sites to prevent low-value internal search pages from being indexed.', 'lightweight-seo' ); ?></p>
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
			<?php esc_html_e( 'Add a noindex directive to attachment pages by default', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Recommended for most sites unless attachment pages are being used as standalone landing pages.', 'lightweight-seo' ); ?></p>
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
			<option value="large" <?php selected( $normalized_value, 'large' ); ?>><?php esc_html_e( 'Large', 'lightweight-seo' ); ?></option>
			<option value="standard" <?php selected( $normalized_value, 'standard' ); ?>><?php esc_html_e( 'Standard', 'lightweight-seo' ); ?></option>
			<option value="none" <?php selected( $normalized_value, 'none' ); ?>><?php esc_html_e( 'None', 'lightweight-seo' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'Sets the default max-image-preview robots directive for your content.', 'lightweight-seo' ); ?></p>
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
			<?php esc_html_e( 'Send X-Robots-Tag headers for WordPress attachment pages', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Static files served directly by the web server or CDN require server-level header configuration.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the sitemap section information.
	 *
	 * @since    1.1.0
	 */
	public function sitemap_section_callback() {
		echo '<p>' . esc_html__( 'Lightweight SEO extends WordPress core XML sitemaps instead of replacing them.', 'lightweight-seo' ) . '</p>';
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
			<?php esc_html_e( 'Exclude noindexed posts from WordPress core XML sitemaps', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Recommended to keep your sitemap focused on indexable content.', 'lightweight-seo' ); ?></p>
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
			<?php esc_html_e( 'Exclude content whose live paths are redirected elsewhere', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Prevents sitemap entries from pointing at URLs that are currently redirected.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the schema section information.
	 *
	 * @since    1.1.0
	 */
	public function schema_section_callback() {
		echo '<p>' . esc_html__( 'Output lightweight JSON-LD schema using your site identity and SEO context.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . esc_html__( 'The default social image is used as the organization logo when available.', 'lightweight-seo' ) . '</p>';
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
			<?php esc_html_e( 'Output core Organization, WebSite, Article, and Breadcrumb schema', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Disable this if your theme or another plugin already outputs equivalent structured data.', 'lightweight-seo' ); ?></p>
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
		<p class="description"><?php esc_html_e( 'Add one profile URL per line for your organization, such as social profiles or knowledge sources.', 'lightweight-seo' ); ?></p>
		<?php
	}
}
