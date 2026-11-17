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
	 * Render the title separator field.
	 *
	 * @since    1.1.0
	 */
	public function title_separator_render() {
		$current = $this->settings->get_title_separator();
		$names   = array(
			'–' => __( 'En dash', 'lightweight-seo' ),
			'—' => __( 'Em dash', 'lightweight-seo' ),
			'-' => __( 'Hyphen', 'lightweight-seo' ),
			'|' => __( 'Vertical bar', 'lightweight-seo' ),
			'·' => __( 'Middle dot', 'lightweight-seo' ),
			'•' => __( 'Bullet', 'lightweight-seo' ),
			'»' => __( 'Angle quote', 'lightweight-seo' ),
			'/' => __( 'Slash', 'lightweight-seo' ),
		);
		?>
		<select id="lightweight_seo_title_separator" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[title_separator]">
			<?php foreach ( Lightweight_SEO_Settings::TITLE_SEPARATORS as $separator ) : ?>
				<option value="<?php echo esc_attr( $separator ); ?>" <?php selected( $current, $separator ); ?>><?php echo esc_html( $separator . '  ' . ( $names[ $separator ] ?? '' ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="lwseo-help"><?php esc_html_e( 'Sets what the &#37;sep&#37; variable becomes in every title.', 'lightweight-seo' ); ?></p>
		<?php
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
	 * Warn when WordPress itself discourages indexing, which outranks these controls,
	 * and when hiding pages also hides a homepage that is a page.
	 *
	 * @since    1.1.0
	 */
	public function indexation_section_callback() {
		if ( '0' === (string) get_option( 'blog_public', '1' ) ) {
			Lightweight_SEO_Admin_UI::callout( __( 'WordPress is set to discourage search engines from indexing this site (Settings → Reading). That setting applies before the controls below.', 'lightweight-seo' ), 'warning' );
		}

		if ( 'page' === get_option( 'show_on_front' ) && absint( get_option( 'page_on_front', 0 ) ) && $this->settings->is_post_type_noindex( 'page' ) ) {
			Lightweight_SEO_Admin_UI::callout( __( 'Pages are hidden from search results, and your homepage is a page. It stays in search results only while its own SEO settings say “Show in search results”.', 'lightweight-seo' ), 'warning' );
		}
	}

	/**
	 * Render the content types table: search visibility, sitemap, and title format per kind of content.
	 *
	 * @since    1.1.0
	 */
	public function content_types_render() {
		$option       = LIGHTWEIGHT_SEO_OPTION_NAME;
		$sitemap_note = $this->settings->exclude_noindex_from_sitemaps_enabled() ? __( 'Shown items only', 'lightweight-seo' ) : '';
		$post_types   = array();
		$taxonomies   = array();

		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
			$object = get_post_type_object( $post_type );
			$row    = $this->settings->get_content_type( 'post_types', $post_type );
			$name   = $option . '[content_types][post_types][' . $post_type . ']';
			$cells  = array(
				'show'    => $this->get_show_cell( $name . '[noindex]', '1' !== $row['noindex'] ),
				'sitemap' => $this->get_sitemap_cell( $name . '[sitemap]', $row, $sitemap_note ),
				'title'   => $this->get_title_cell( $name . '[title_format]', $row, $this->settings->get_title_format() ),
			);

			if ( 'attachment' === $post_type ) {
				// Attachment visibility predates this table and keeps its own setting.
				$cells['show']    = $this->get_show_cell( $option . '[noindex_attachment_pages]', '1' !== $row['noindex'] );
				$cells['sitemap'] = array(
					'type' => 'none',
					'text' => __( 'Not listed by WordPress', 'lightweight-seo' ),
				);
			}

			$post_types[] = array(
				'label' => (string) ( $object->labels->name ?? $post_type ),
				'cells' => $cells,
			);
		}

		foreach ( get_taxonomies( array( 'public' => true ), 'names' ) as $taxonomy ) {
			// WordPress registers post formats on every site; they only have archives when the theme uses them.
			if ( 'post_format' === $taxonomy && ! current_theme_supports( 'post-formats' ) ) {
				continue;
			}

			$object = get_taxonomy( $taxonomy );
			$row    = $this->settings->get_content_type( 'taxonomies', $taxonomy );
			$name   = $option . '[content_types][taxonomies][' . $taxonomy . ']';

			$taxonomies[] = array(
				'label' => (string) ( $object->labels->name ?? $taxonomy ),
				'cells' => array(
					'show'    => $this->get_show_cell( $name . '[noindex]', '1' !== $row['noindex'] ),
					'sitemap' => $this->get_sitemap_cell( $name . '[sitemap]', $row, $sitemap_note ),
					'title'   => $this->get_title_cell( $name . '[title_format]', $row, $this->settings->get_archive_title_format() ),
				),
			);
		}

		$archives = array(
			array(
				'label' => __( 'Author archives', 'lightweight-seo' ),
				'cells' => array( 'show' => $this->get_show_cell( $option . '[noindex_author_archives]', ! $this->settings->author_archives_noindex_enabled() ) ),
			),
			array(
				'label' => __( 'Date archives', 'lightweight-seo' ),
				'cells' => array( 'show' => $this->get_show_cell( $option . '[noindex_date_archives]', ! $this->settings->date_archives_noindex_enabled() ) ),
			),
			array(
				'label' => __( 'Internal search pages', 'lightweight-seo' ),
				'cells' => array( 'show' => $this->get_show_cell( $option . '[noindex_search_results]', ! $this->settings->search_results_noindex_enabled() ) ),
			),
		);

		Lightweight_SEO_Admin_UI::matrix(
			array(
				'first'   => __( 'Content type', 'lightweight-seo' ),
				'columns' => array(
					'show'    => __( 'Show in search results', 'lightweight-seo' ),
					'sitemap' => __( 'In sitemap', 'lightweight-seo' ),
					'title'   => __( 'Title format', 'lightweight-seo' ),
				),
				'groups'  => array(
					array(
						'label' => __( 'Post types', 'lightweight-seo' ),
						'rows'  => $post_types,
					),
					array(
						'label' => __( 'Taxonomies', 'lightweight-seo' ),
						'rows'  => $taxonomies,
					),
					array(
						'label' => __( 'Archives and search', 'lightweight-seo' ),
						'rows'  => $archives,
					),
				),
			)
		);
		Lightweight_SEO_Admin_UI::tokens( array( '%title%', '%sitename%', '%tagline%', '%sep%' ) );
		?>
		<p class="lwseo-help"><?php esc_html_e( 'An empty title format uses the site-wide format shown in the field, and hiding a post type also hides its archive page.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Describe a "Show in search results" tick box, which stores the opposite: a noindex flag.
	 *
	 * @since    1.1.0
	 * @param    string    $name     Input name of the noindex setting.
	 * @param    bool      $shown    Whether the content is shown in search results.
	 * @return   array
	 */
	private function get_show_cell( $name, $shown ) {
		return array(
			'type'          => 'check',
			'name'          => $name,
			'checked'       => $shown,
			'on'            => '0',
			'off'           => '1',
			'toggles_notes' => true,
		);
	}

	/**
	 * Describe an "In sitemap" tick box, with the remark shown while its content type is hidden.
	 *
	 * @since    1.1.0
	 * @param    string    $name    Input name of the sitemap setting.
	 * @param    array     $row     Stored row of the content type.
	 * @param    string    $note    Remark for a hidden content type, or an empty string when hidden pages stay listed.
	 * @return   array
	 */
	private function get_sitemap_cell( $name, $row, $note ) {
		return array(
			'type'        => 'check',
			'name'        => $name,
			'checked'     => '1' === $row['sitemap'],
			'on'          => '1',
			'off'         => '0',
			'note'        => $note,
			'note_hidden' => '1' !== $row['noindex'],
		);
	}

	/**
	 * Describe a title format field that shows the format it inherits while empty.
	 *
	 * @since    1.1.0
	 * @param    string    $name         Input name of the title format setting.
	 * @param    array     $row          Stored row of the content type.
	 * @param    string    $inherited    Site-wide format used while the field is empty.
	 * @return   array
	 */
	private function get_title_cell( $name, $row, $inherited ) {
		return array(
			'type'        => 'text',
			'name'        => $name,
			'value'       => wp_specialchars_decode( $row['title_format'], ENT_QUOTES ),
			'placeholder' => $inherited,
			'tokens'      => true,
		);
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
	 * Render the choice of what the site represents.
	 *
	 * @since    1.1.0
	 */
	public function identity_type_render() {
		$current = $this->settings->get_identity_type();
		?>
		<select id="lightweight_seo_identity_type" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[identity_type]">
			<option value="organization" <?php selected( $current, 'organization' ); ?>><?php esc_html_e( 'An organization', 'lightweight-seo' ); ?></option>
			<option value="person" <?php selected( $current, 'person' ); ?>><?php esc_html_e( 'A person', 'lightweight-seo' ); ?></option>
		</select>
		<p class="lwseo-help"><?php esc_html_e( 'Sets whether structured data describes the publisher of this site as an organization or as a person.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the name of the organization or person.
	 *
	 * @since    1.1.0
	 */
	public function identity_name_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" id="lightweight_seo_identity_name" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[identity_name]" value="<?php echo esc_attr( $options['identity_name'] ?? '' ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" class="regular-text">
		<p class="lwseo-help"><?php esc_html_e( 'Names the organization or person; leave empty to use the site title.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the logo of the organization or the photo of the person.
	 *
	 * @since    1.1.0
	 */
	public function identity_logo_render() {
		$options = $this->settings->get_all();

		Lightweight_SEO_Admin_UI::image_field(
			array(
				'url'      => $this->settings->get_identity_logo_url(),
				'id'       => $options['identity_logo_id'] ?? 0,
				'url_name' => LIGHTWEIGHT_SEO_OPTION_NAME . '[identity_logo]',
				'id_name'  => LIGHTWEIGHT_SEO_OPTION_NAME . '[identity_logo_id]',
				'url_id'   => 'lightweight_seo_identity_logo',
				'id_id'    => 'lightweight_seo_identity_logo_id',
				'alt'      => __( 'Logo or photo', 'lightweight-seo' ),
			)
		);
		?>
		<p class="lwseo-help"><?php esc_html_e( 'Shows the logo of an organization or the photo of a person in structured data; an organization without one uses the default social image.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the X handle field.
	 *
	 * @since    1.1.0
	 */
	public function x_handle_render() {
		$handle = $this->settings->get_x_handle();
		?>
		<input type="text" id="lightweight_seo_x_handle" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[x_handle]" value="<?php echo esc_attr( '' !== $handle ? '@' . $handle : '' ); ?>" placeholder="@example" class="regular-text" autocomplete="off">
		<p class="lwseo-help"><?php esc_html_e( 'Credits this account when a page is shared on X, through the twitter:site tag.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the schema output toggle field.
	 *
	 * @since    1.1.0
	 */
	public function enable_schema_output_render() {
		$this->render_choice( 'enable_schema_output', '1', __( 'Output core Organization or Person, WebSite, Article, and Breadcrumb schema', 'lightweight-seo' ), __( 'Disable this if your theme or another plugin already outputs equivalent structured data.', 'lightweight-seo' ) );
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
		<p class="lwseo-help"><?php esc_html_e( 'Add one profile URL per line for the organization or person, such as social profiles or knowledge sources.', 'lightweight-seo' ); ?></p>
		<?php
	}
}
