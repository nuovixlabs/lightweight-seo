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
	 * What the title format previews are filled with, read once per request.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      array|null    $preview_sample
	 */
	private $preview_sample = null;

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
		<?php
		$format = wp_specialchars_decode( (string) ( $options[ $key ] ?? '' ), ENT_QUOTES );
		$tokens = $this->get_preview_tokens( $key );
		?>
		<p class="lwseo-format-preview" data-for="<?php echo esc_attr( 'lightweight_seo_' . $key ); ?>" data-fallback="<?php echo esc_attr( $fallback ); ?>" data-tokens="<?php echo esc_attr( wp_json_encode( $tokens ) ); ?>">
			<span class="lwseo-format-preview__label"><?php esc_html_e( 'Preview:', 'lightweight-seo' ); ?></span>
			<span class="lwseo-format-preview__text"><?php echo esc_html( $this->resolve_preview( '' !== trim( $format ) ? $format : $fallback, $tokens ) ); ?></span>
		</p>
		<p class="lwseo-help"><?php echo esc_html( $help ); ?></p>
		<?php
	}

	/**
	 * Fill the variables of a format for its preview, as a page would.
	 *
	 * @since    1.1.0
	 * @param    string    $format    Title format.
	 * @param    array     $tokens    Sample values keyed by variable.
	 * @return   string
	 */
	private function resolve_preview( $format, $tokens ) {
		return trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( Lightweight_SEO_Text::resolve_tokens( $format, $tokens ) ) ) );
	}

	/**
	 * Get sample values for the preview of one title format.
	 *
	 * The values come from the newest published post, so the preview reads like
	 * a real title of this site. A site without posts gets plain examples.
	 *
	 * @since    1.1.0
	 * @param    string    $key    Settings key of the title format.
	 * @return   array<string,string> Values keyed by variable.
	 */
	private function get_preview_tokens( $key ) {
		if ( null === $this->preview_sample ) {
			$posts    = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				)
			);
			$post     = $posts[0] ?? null;
			$category = $post ? get_the_category( $post->ID ) : array();

			$this->preview_sample = array(
				'title'    => $post ? wp_strip_all_tags( get_the_title( $post->ID ) ) : __( 'A page title', 'lightweight-seo' ),
				'category' => ! empty( $category[0]->name ) ? (string) $category[0]->name : __( 'A category', 'lightweight-seo' ),
				'author'   => $post ? (string) get_the_author_meta( 'display_name', (int) ( $post->post_author ?? 0 ) ) : '',
			);
		}

		$sample = $this->preview_sample;
		$tokens = array(
			'%title%'    => $sample['title'],
			'%sitename%' => (string) get_bloginfo( 'name' ),
			'%tagline%'  => (string) get_bloginfo( 'description' ),
			'%sep%'      => $this->settings->get_title_separator(),
			'%category%' => $sample['category'],
			'%author%'   => $sample['author'],
			'%year%'     => wp_date( 'Y' ),
			// The first page of anything has no page number.
			'%page%'     => '',
		);

		if ( 'home_title_format' === $key ) {
			$tokens['%title%']    = $tokens['%sitename%'];
			$tokens['%category%'] = '';
			$tokens['%author%']   = '';
		} elseif ( 'archive_title_format' === $key ) {
			$tokens['%title%']  = $sample['category'];
			$tokens['%author%'] = '';
		} elseif ( 'search_title_format' === $key ) {
			$tokens['%search%']   = _x( 'coffee', 'example of a search a visitor makes', 'lightweight-seo' );
			$tokens['%title%']    = $tokens['%search%'];
			$tokens['%category%'] = '';
			$tokens['%author%']   = '';
		}

		return $tokens;
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
	 * Describe the title variables, in the order they are offered.
	 *
	 * @since    1.1.0
	 * @return   array<string,string> What each variable becomes, keyed by the variable.
	 */
	private function get_title_variables() {
		return array(
			'%title%'    => __( 'The title of the page, or the name of the term or author.', 'lightweight-seo' ),
			'%sitename%' => __( 'The site title.', 'lightweight-seo' ),
			'%tagline%'  => __( 'The site tagline. Left out when the site has none.', 'lightweight-seo' ),
			'%sep%'      => __( 'The title separator chosen below.', 'lightweight-seo' ),
			'%category%' => __( 'The first category of a post, or the category of a category archive.', 'lightweight-seo' ),
			'%author%'   => __( 'The author of a post, or the author of an author archive.', 'lightweight-seo' ),
			'%year%'     => __( 'The current year.', 'lightweight-seo' ),
			'%page%'     => __( '“Page 2” and so on, from the second page of an archive or of a post split into pages.', 'lightweight-seo' ),
		);
	}

	/**
	 * Render the title variables above the title format fields, with what each one becomes.
	 *
	 * @since    1.0.0
	 */
	public function general_section_callback() {
		$variables = $this->get_title_variables();

		Lightweight_SEO_Admin_UI::tokens( array_keys( $variables ) );
		?>
		<details class="lwseo-details">
			<summary><?php esc_html_e( 'What the variables mean', 'lightweight-seo' ); ?></summary>
			<div class="lwseo-details__body">
				<div class="lwseo-table-wrap">
					<table class="lwseo-table">
						<tbody>
							<?php foreach ( $variables as $variable => $meaning ) : ?>
								<tr><th scope="row"><code><?php echo esc_html( $variable ); ?></code></th><td><?php echo esc_html( $meaning ); ?></td></tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p class="lwseo-help"><?php esc_html_e( 'A tagline, category, author, year, or page variable that has no value on a page is left out together with the separator beside it.', 'lightweight-seo' ); ?></p>
			</div>
		</details>
		<?php
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
				'schema'  => array(
					'type'    => 'select',
					'name'    => $name . '[schema]',
					'value'   => $this->settings->get_schema_type_for_post_type( $post_type ),
					'options' => array(
						'WebPage'     => __( 'Web page', 'lightweight-seo' ),
						'Article'     => __( 'Article', 'lightweight-seo' ),
						'BlogPosting' => __( 'Blog post', 'lightweight-seo' ),
						'NewsArticle' => __( 'News article', 'lightweight-seo' ),
					),
				),
			);

			if ( 'attachment' === $post_type ) {
				// Attachment visibility predates this table and keeps its own setting.
				$cells['show']    = $this->get_show_cell( $option . '[noindex_attachment_pages]', '1' !== $row['noindex'] );
				$cells['sitemap'] = array(
					'type' => 'none',
					'text' => __( 'Not listed by WordPress', 'lightweight-seo' ),
				);
				// An attachment page is always described as a web page.
				unset( $cells['schema'] );
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
					'title'   => $this->get_title_cell( $name . '[title_format]', $row, $this->settings->get_archive_title_format(), 'archive_title_format' ),
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
					'schema'  => __( 'Schema type', 'lightweight-seo' ),
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
		Lightweight_SEO_Admin_UI::tokens( array_keys( $this->get_title_variables() ) );
		?>
		<p class="lwseo-help"><?php esc_html_e( 'An empty title format uses the site-wide format shown in the field, and hiding a post type also hides its archive page.', 'lightweight-seo' ); ?></p>
		<p class="lwseo-help"><?php esc_html_e( 'The schema type says what a single item is in structured data. Every page is described as a web page; "Article", "Blog post", and "News article" add a node with the headline, dates, author, and image.', 'lightweight-seo' ); ?></p>
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
	private function get_title_cell( $name, $row, $inherited, $sample = 'title_format' ) {
		$format = wp_specialchars_decode( $row['title_format'], ENT_QUOTES );
		$tokens = $this->get_preview_tokens( $sample );

		return array(
			'type'        => 'text',
			'name'        => $name,
			// The preview names its field by ID, so each title format of the table needs one.
			'id'          => 'lwseo-' . trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) ), '-' ),
			'value'       => $format,
			'placeholder' => $inherited,
			'tokens'      => true,
			'preview'     => array(
				'label'    => __( 'Preview:', 'lightweight-seo' ),
				'text'     => $this->resolve_preview( '' !== trim( $format ) ? $format : $inherited, $tokens ),
				'fallback' => $inherited,
				'tokens'   => $tokens,
			),
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
	 * Print a verification code field.
	 *
	 * @since    1.1.0
	 * @param    string    $service    Service key from Lightweight_SEO_Settings::VERIFICATION_TAGS.
	 * @param    string    $help       Help text.
	 * @return   void
	 */
	private function render_verification_code( $service, $help ) {
		$key  = 'verification_' . $service;
		$code = (string) $this->settings->normalize_verification_code( $this->settings->get( $key, '' ) );
		?>
		<input type="text" id="<?php echo esc_attr( 'lightweight_seo_' . $key ); ?>" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $code ); ?>" class="regular-text code" autocomplete="off">
		<p class="lwseo-help"><?php echo esc_html( $help ); ?></p>
		<?php
	}

	/**
	 * Render the Google Search Console verification field.
	 *
	 * @since    1.1.0
	 */
	public function verification_google_render() {
		$this->render_verification_code( 'google', __( 'Paste the code, or the whole HTML tag, from the “HTML tag” method in Google Search Console.', 'lightweight-seo' ) );
	}

	/**
	 * Render the Bing Webmaster Tools verification field.
	 *
	 * @since    1.1.0
	 */
	public function verification_bing_render() {
		$this->render_verification_code( 'bing', __( 'Paste the code, or the whole meta tag, from Bing Webmaster Tools.', 'lightweight-seo' ) );
	}

	/**
	 * Render the Pinterest verification field.
	 *
	 * @since    1.1.0
	 */
	public function verification_pinterest_render() {
		$this->render_verification_code( 'pinterest', __( 'Paste the code, or the whole meta tag, from the “Claim your website” step on Pinterest.', 'lightweight-seo' ) );
	}

	/**
	 * Render the Yandex Webmaster verification field.
	 *
	 * @since    1.1.0
	 */
	public function verification_yandex_render() {
		$this->render_verification_code( 'yandex', __( 'Paste the code, or the whole meta tag, from Yandex Webmaster.', 'lightweight-seo' ) );
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
