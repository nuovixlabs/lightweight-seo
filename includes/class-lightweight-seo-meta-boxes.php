<?php
/**
 * The meta box functionality of the plugin.
 *
 * @since      1.0.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The meta box functionality of the plugin.
 */
class Lightweight_SEO_Meta_Boxes {

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
	 * Initialize the class.
	 *
	 * @since    1.0.0
	 */
	public function __construct( $settings, $post_meta ) {
		$this->settings  = $settings;
		$this->post_meta = $post_meta;

		// Add meta boxes to posts and pages
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		// Save meta box data
		add_action( 'save_post', array( $this, 'save_meta_box_data' ) );
	}

	/**
	 * Add meta boxes to posts and pages.
	 *
	 * @since    1.0.0
	 */
	public function add_meta_boxes() {
		// Get the supported post types
		$post_types = $this->post_meta->get_supported_post_types();

		// Add meta box to all public post types
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'lightweight_seo_meta_box',
				__( 'SEO Settings', 'lightweight-seo' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'advanced',
				'high'
			);
		}
	}

	/**
	 * Render the meta box.
	 *
	 * @since    1.0.0
	 * @param    WP_Post    $post    The post object.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'lightweight_seo_meta_box', 'lightweight_seo_meta_box_nonce' );
		$post_meta             = $this->post_meta->get_all( $post->ID );
		$seo_title             = $post_meta['seo_title'] ?? '';
		$seo_description       = $post_meta['seo_description'] ?? '';
		$seo_canonical_url     = $post_meta['seo_canonical_url'] ?? '';
		$seo_noindex           = $post_meta['seo_noindex'] ?? '';
		$seo_nofollow          = $post_meta['seo_nofollow'] ?? '';
		$seo_noarchive         = $post_meta['seo_noarchive'] ?? '';
		$seo_nosnippet         = $post_meta['seo_nosnippet'] ?? '';
		$seo_max_image_preview = $post_meta['seo_max_image_preview'] ?? '';
		$social_title          = $post_meta['social_title'] ?? '';
		$social_description    = $post_meta['social_description'] ?? '';
		$social_image          = $this->post_meta->get_social_image_url( $post->ID );
		$social_image_id       = absint( $post_meta['social_image_id'] ?? 0 );
		$global_title_format   = $this->settings->get_title_format();
		$current_title         = str_replace(
			array( '%title%', '%sitename%', '%tagline%', '%sep%' ),
			array(
				$post->post_title,
				get_bloginfo( 'name' ),
				get_bloginfo( 'description' ),
				LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR,
			),
			$global_title_format
		);
		$fallback_description  = $this->normalize_preview_text( ! empty( $post->post_excerpt ) ? $post->post_excerpt : ( $post->post_content ?? '' ) );
		$preview_title         = $this->normalize_preview_text( $seo_title ? $seo_title : $current_title );
		$preview_description   = $this->normalize_preview_text( $seo_description ? $seo_description : $fallback_description );
		$preview_url           = $seo_canonical_url ? $seo_canonical_url : get_permalink( $post->ID );
		$social_preview_title  = $this->normalize_preview_text( $social_title ? $social_title : $preview_title );
		$social_preview_desc   = $this->normalize_preview_text( $social_description ? $social_description : $preview_description );

		if ( empty( $social_image ) && has_post_thumbnail( $post->ID ) ) {
			$social_image    = get_the_post_thumbnail_url( $post->ID, 'full' );
			$social_image_id = get_post_thumbnail_id( $post->ID );
		}

		if ( empty( $social_image ) ) {
			$social_image = $this->settings->get_social_image_url();
		}

		$checks = $this->get_editor_checks( $post, $post_meta, $preview_title, $preview_description, $social_image, $social_image_id );

		?>
		<div class="lightweight-seo-meta-box">
			<div class="lightweight-seo-tabs">
				<div class="lightweight-seo-tab-nav" role="tablist" aria-label="<?php echo esc_attr( __( 'SEO editor panels', 'lightweight-seo' ) ); ?>">
					<button type="button" class="nav-tab nav-tab-active" role="tab" aria-selected="true" aria-controls="lightweight-seo-search-panel" id="lightweight-seo-search-tab" data-tab="search"><?php _e( 'Search appearance', 'lightweight-seo' ); ?></button>
					<button type="button" class="nav-tab" role="tab" aria-selected="false" aria-controls="lightweight-seo-social-panel" id="lightweight-seo-social-tab" data-tab="social" tabindex="-1"><?php _e( 'Social appearance', 'lightweight-seo' ); ?></button>
					<button type="button" class="nav-tab" role="tab" aria-selected="false" aria-controls="lightweight-seo-checks-panel" id="lightweight-seo-checks-tab" data-tab="checks" tabindex="-1"><?php _e( 'Checks', 'lightweight-seo' ); ?></button>
				</div>
				<div class="lightweight-seo-tab-content">
					<div class="tab-content active" id="lightweight-seo-search-panel" role="tabpanel" aria-labelledby="lightweight-seo-search-tab" data-panel="search">
						<div class="lightweight-seo-search-preview" aria-live="polite">
							<p class="lightweight-seo-preview-url"><?php echo esc_html( $preview_url ); ?></p>
							<p class="lightweight-seo-preview-title"><?php echo esc_html( $preview_title ); ?></p>
							<p class="lightweight-seo-preview-description"><?php echo esc_html( $preview_description ); ?></p>
						</div>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="lightweight_seo_title"><?php _e( 'SEO Title', 'lightweight-seo' ); ?></label>
								</th>
								<td>
									<input type="text" id="lightweight_seo_title" name="lightweight_seo_title" value="<?php echo esc_attr( $seo_title ); ?>" class="large-text" data-fallback="<?php echo esc_attr( $current_title ); ?>" aria-describedby="lightweight-seo-title-guidance">
									<p class="description" id="lightweight-seo-title-guidance"><span data-count-for="lightweight_seo_title"><?php echo esc_html( (string) strlen( $preview_title ) ); ?></span> <?php _e( 'characters. Aim for a clear, specific title; roughly 30–60 characters is useful preview guidance, not a ranking rule.', 'lightweight-seo' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="lightweight_seo_description"><?php _e( 'Meta Description', 'lightweight-seo' ); ?></label>
								</th>
								<td>
									<textarea id="lightweight_seo_description" name="lightweight_seo_description" rows="3" class="large-text" data-fallback="<?php echo esc_attr( $fallback_description ); ?>" aria-describedby="lightweight-seo-description-guidance"><?php echo esc_textarea( $seo_description ); ?></textarea>
									<p class="description" id="lightweight-seo-description-guidance"><span data-count-for="lightweight_seo_description"><?php echo esc_html( (string) strlen( $preview_description ) ); ?></span> <?php _e( 'characters. Roughly 120–160 characters often fits a search preview, but clarity matters more than hitting a number.', 'lightweight-seo' ); ?></p>
								</td>
							</tr>
						</table>
						<details class="lightweight-seo-advanced">
							<summary><?php _e( 'Advanced canonical and robots controls', 'lightweight-seo' ); ?></summary>
							<p><label for="lightweight_seo_canonical_url"><strong><?php _e( 'Canonical URL', 'lightweight-seo' ); ?></strong></label><br><input type="url" id="lightweight_seo_canonical_url" name="lightweight_seo_canonical_url" value="<?php echo esc_url( $seo_canonical_url ); ?>" class="large-text"><br><span class="description"><?php _e( 'Leave empty to use this page’s permalink.', 'lightweight-seo' ); ?></span></p>
							<fieldset><legend class="screen-reader-text"><?php _e( 'Robots directives', 'lightweight-seo' ); ?></legend>
								<label><input type="checkbox" id="lightweight_seo_noindex" name="lightweight_seo_noindex" value="1" <?php checked( $seo_noindex, '1' ); ?>> <?php _e( 'Prevent search engines from indexing this page', 'lightweight-seo' ); ?></label><br>
									<label>
										<input type="checkbox" name="lightweight_seo_nofollow" value="1" <?php checked( $seo_nofollow, '1' ); ?>>
										<?php _e( 'Prevent search engines from following links on this page', 'lightweight-seo' ); ?>
									</label>
									<br>
									<label>
										<input type="checkbox" name="lightweight_seo_noarchive" value="1" <?php checked( $seo_noarchive, '1' ); ?>>
										<?php _e( 'Prevent search engines from showing cached copies', 'lightweight-seo' ); ?>
									</label>
									<br>
									<label>
										<input type="checkbox" name="lightweight_seo_nosnippet" value="1" <?php checked( $seo_nosnippet, '1' ); ?>>
										<?php _e( 'Prevent text snippets from showing in search results', 'lightweight-seo' ); ?>
									</label>
									<p class="description">
										<label for="lightweight_seo_max_image_preview"><?php _e( 'Max Image Preview', 'lightweight-seo' ); ?></label><br>
										<select id="lightweight_seo_max_image_preview" name="lightweight_seo_max_image_preview">
											<option value="" <?php selected( $seo_max_image_preview, '' ); ?>><?php _e( 'Use global default', 'lightweight-seo' ); ?></option>
											<option value="large" <?php selected( $seo_max_image_preview, 'large' ); ?>><?php _e( 'Large', 'lightweight-seo' ); ?></option>
											<option value="standard" <?php selected( $seo_max_image_preview, 'standard' ); ?>><?php _e( 'Standard', 'lightweight-seo' ); ?></option>
											<option value="none" <?php selected( $seo_max_image_preview, 'none' ); ?>><?php _e( 'None', 'lightweight-seo' ); ?></option>
										</select>
									</p>
							</fieldset>
						</details>
					</div>
					<div class="tab-content" id="lightweight-seo-social-panel" role="tabpanel" aria-labelledby="lightweight-seo-social-tab" data-panel="social" hidden>
						<div class="lightweight-seo-social-preview" aria-live="polite">
							<?php
							if ( $social_image ) :
								?>
								<img src="<?php echo esc_url( $social_image ); ?>" alt=""><?php endif; ?>
							<div><p class="lightweight-seo-social-site"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p><p class="lightweight-seo-social-title-preview"><?php echo esc_html( $social_preview_title ); ?></p><p class="lightweight-seo-social-description-preview"><?php echo esc_html( $social_preview_desc ); ?></p></div>
						</div>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="lightweight_seo_social_title"><?php _e( 'Social Title', 'lightweight-seo' ); ?></label>
								</th>
								<td>
									<input type="text" id="lightweight_seo_social_title" name="lightweight_seo_social_title" value="<?php echo esc_attr( $social_title ); ?>" class="large-text" data-fallback="<?php echo esc_attr( $preview_title ); ?>">
									<p class="description">
										<?php _e( 'Title used when shared on social media. If empty, the SEO title or post title will be used.', 'lightweight-seo' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="lightweight_seo_social_description"><?php _e( 'Social Description', 'lightweight-seo' ); ?></label>
								</th>
								<td>
									<textarea id="lightweight_seo_social_description" name="lightweight_seo_social_description" rows="3" class="large-text" data-fallback="<?php echo esc_attr( $preview_description ); ?>"><?php echo esc_textarea( $social_description ); ?></textarea>
									<p class="description">
										<?php _e( 'Description used when shared on social media. If empty, the Meta Description will be used.', 'lightweight-seo' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="lightweight_seo_social_image"><?php _e( 'Social Image', 'lightweight-seo' ); ?></label>
								</th>
								<td>
									<div class="lightweight-seo-image-field">
										<input type="hidden" id="lightweight_seo_social_image_id" name="lightweight_seo_social_image_id" value="<?php echo esc_attr( $social_image_id ); ?>" class="lightweight-seo-image-id">
										<input type="text" id="lightweight_seo_social_image" name="lightweight_seo_social_image" value="<?php echo esc_url( $social_image ); ?>" class="large-text lightweight-seo-image-url">
										<button type="button" class="button button-secondary lightweight-seo-upload-image"><?php _e( 'Upload Image', 'lightweight-seo' ); ?></button>
										<?php if ( ! empty( $social_image ) ) : ?>
											<div class="lightweight-seo-image-preview">
												<img src="<?php echo esc_url( $social_image ); ?>" alt="<?php echo esc_attr( __( 'Selected social image', 'lightweight-seo' ) ); ?>">
											</div>
										<?php endif; ?>
									</div>
									<p class="description">
										<?php _e( 'Image used when shared on social media. Recommended size: 1200x630px.', 'lightweight-seo' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>
					<div class="tab-content" id="lightweight-seo-checks-panel" role="tabpanel" aria-labelledby="lightweight-seo-checks-tab" data-panel="checks" hidden>
						<h3><?php _e( 'On-page checks', 'lightweight-seo' ); ?></h3>
						<p><?php _e( 'These checks report facts and preview guidance only. They do not predict rankings.', 'lightweight-seo' ); ?></p>
						<ul class="lightweight-seo-checks">
							<?php foreach ( $checks as $check ) : ?>
								<li class="is-<?php echo esc_attr( $check['status'] ); ?>"><span class="dashicons <?php echo esc_attr( 'success' === $check['status'] ? 'dashicons-yes-alt' : 'dashicons-warning' ); ?>" aria-hidden="true"></span><div><strong><?php echo esc_html( $check['label'] ); ?></strong><p><?php echo esc_html( $check['message'] ); ?>
								<?php
								if ( ! empty( $check['target'] ) ) :
									?>
									<a href="<?php echo esc_attr( $check['target'] ); ?>"><?php _e( 'Edit', 'lightweight-seo' ); ?></a><?php endif; ?></p></div></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Build deterministic, non-scored editor checks.
	 *
	 * @return array
	 */
	private function get_editor_checks( $post, $post_meta, $title, $description, $social_image, $social_image_id ) {
		$checks             = array();
		$checks[]           = $this->make_check( $title, __( 'SEO title', 'lightweight-seo' ), __( 'A title is available for the search preview.', 'lightweight-seo' ), __( 'Add a clear SEO title.', 'lightweight-seo' ), '#lightweight_seo_title' );
		$checks[]           = $this->make_check( $description, __( 'Meta description', 'lightweight-seo' ), __( 'A description is available for the search preview.', 'lightweight-seo' ), __( 'Add a useful description of this page.', 'lightweight-seo' ), '#lightweight_seo_description' );
		$title_length       = strlen( $title );
		$checks[]           = $this->make_check( $title_length >= 30 && $title_length <= 60, __( 'Title preview length', 'lightweight-seo' ), __( 'The title is within the usual preview guidance range.', 'lightweight-seo' ), __( 'The title may be shortened or expanded in search results; review it for clarity.', 'lightweight-seo' ), '#lightweight_seo_title' );
		$description_length = strlen( $description );
		$checks[]           = $this->make_check( $description_length >= 120 && $description_length <= 160, __( 'Description preview length', 'lightweight-seo' ), __( 'The description is within the usual preview guidance range.', 'lightweight-seo' ), __( 'The description may truncate or leave unused preview space; review it for clarity.', 'lightweight-seo' ), '#lightweight_seo_description' );
		$checks[]           = $this->make_check( $social_image, __( 'Social image', 'lightweight-seo' ), __( 'A social image is available.', 'lightweight-seo' ), __( 'Choose an image for richer social previews.', 'lightweight-seo' ), '#lightweight_seo_social_image' );

		if ( $social_image_id ) {
			$image_data  = wp_get_attachment_metadata( $social_image_id );
			$image_width = absint( $image_data['width'] ?? 0 );

			if ( $image_width ) {
				$checks[] = $this->make_check( $image_width >= 1200, __( 'Social image width', 'lightweight-seo' ), __( 'The selected image meets the 1200px width recommendation.', 'lightweight-seo' ), __( 'The selected image is below the 1200px width recommendation.', 'lightweight-seo' ), '#lightweight_seo_social_image' );
			}
		}

		$checks[]  = $this->make_check( '1' !== (string) ( $post_meta['seo_noindex'] ?? '' ), __( 'Indexing', 'lightweight-seo' ), __( 'This page can be indexed.', 'lightweight-seo' ), __( 'This page is intentionally set to noindex. Confirm that is expected.', 'lightweight-seo' ), '#lightweight_seo_noindex' );
		$canonical = (string) ( $post_meta['seo_canonical_url'] ?? '' );

		if ( $canonical ) {
			$valid_url = false !== filter_var( $canonical, FILTER_VALIDATE_URL );
			$same_host = $valid_url && strtolower( (string) wp_parse_url( $canonical, PHP_URL_HOST ) ) === strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
			$checks[]  = $this->make_check( $valid_url && $same_host, __( 'Custom canonical', 'lightweight-seo' ), __( 'The custom canonical uses this site’s host.', 'lightweight-seo' ), $valid_url ? __( 'The custom canonical points to a different host. Confirm that this is intentional.', 'lightweight-seo' ) : __( 'The custom canonical URL is invalid.', 'lightweight-seo' ), '#lightweight_seo_canonical_url' );
		}

		if ( class_exists( 'Lightweight_SEO_Compatibility_Service' ) ) {
			$conflicts = ( new Lightweight_SEO_Compatibility_Service() )->get_conflicting_plugins();
			/* translators: %s: comma-separated list of conflicting SEO plugins. */
			$checks[] = $this->make_check( empty( $conflicts ), __( 'SEO output conflicts', 'lightweight-seo' ), __( 'No conflicting SEO plugin was detected.', 'lightweight-seo' ), sprintf( __( 'Another SEO plugin is active: %s. Lightweight SEO suppresses overlapping output.', 'lightweight-seo' ), implode( ', ', $conflicts ) ), '' );
		}

		return $checks;
	}

	private function make_check( $passes, $label, $success, $warning, $target ) {
		return array(
			'status'  => $passes ? 'success' : 'warning',
			'label'   => $label,
			'message' => $passes ? $success : $warning,
			'target'  => $passes ? '' : $target,
		);
	}

	private function normalize_preview_text( $value ) {
		$value = function_exists( 'strip_shortcodes' ) ? strip_shortcodes( (string) $value ) : (string) $value;
		$value = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $value, true ) : strip_tags( $value );

		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}

	/**
	 * Save meta box data.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id    The post ID.
	 */
	public function save_meta_box_data( $post_id ) {
		// Check if our nonce is set
		if ( ! isset( $_POST['lightweight_seo_meta_box_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['lightweight_seo_meta_box_nonce'] ) );

		// Verify the nonce
		if ( ! wp_verify_nonce( $nonce, 'lightweight_seo_meta_box' ) ) {
			return;
		}

		// If this is an autosave, we don't want to do anything
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check the user's permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Sanitize and save the data
		if ( isset( $_POST['lightweight_seo_title'] ) ) {
			$this->post_meta->update( $post_id, 'seo_title', sanitize_text_field( wp_unslash( $_POST['lightweight_seo_title'] ) ) );
		}

		if ( isset( $_POST['lightweight_seo_description'] ) ) {
			$this->post_meta->update( $post_id, 'seo_description', sanitize_textarea_field( wp_unslash( $_POST['lightweight_seo_description'] ) ) );
		}

		if ( isset( $_POST['lightweight_seo_keywords'] ) ) {
			$this->post_meta->update( $post_id, 'seo_keywords', sanitize_text_field( wp_unslash( $_POST['lightweight_seo_keywords'] ) ) );
		}

		if ( isset( $_POST['lightweight_seo_canonical_url'] ) ) {
			$this->post_meta->update( $post_id, 'seo_canonical_url', esc_url_raw( wp_unslash( $_POST['lightweight_seo_canonical_url'] ) ) );
		}

		// Checkbox fields need to be handled differently
		$noindex = isset( $_POST['lightweight_seo_noindex'] ) ? '1' : '0';
		$this->post_meta->update( $post_id, 'seo_noindex', $noindex );
		$this->post_meta->update( $post_id, 'seo_nofollow', isset( $_POST['lightweight_seo_nofollow'] ) ? '1' : '0' );
		$this->post_meta->update( $post_id, 'seo_noarchive', isset( $_POST['lightweight_seo_noarchive'] ) ? '1' : '0' );
		$this->post_meta->update( $post_id, 'seo_nosnippet', isset( $_POST['lightweight_seo_nosnippet'] ) ? '1' : '0' );

		if ( isset( $_POST['lightweight_seo_max_image_preview'] ) ) {
			$allowed_values = array( '', 'large', 'standard', 'none' );
			$max_preview    = sanitize_text_field( wp_unslash( $_POST['lightweight_seo_max_image_preview'] ) );

			if ( ! in_array( $max_preview, $allowed_values, true ) ) {
				$max_preview = '';
			}

			$this->post_meta->update( $post_id, 'seo_max_image_preview', $max_preview );
		}

		if ( isset( $_POST['lightweight_seo_social_title'] ) ) {
			$this->post_meta->update( $post_id, 'social_title', sanitize_text_field( wp_unslash( $_POST['lightweight_seo_social_title'] ) ) );
		}

		if ( isset( $_POST['lightweight_seo_social_description'] ) ) {
			$this->post_meta->update( $post_id, 'social_description', sanitize_textarea_field( wp_unslash( $_POST['lightweight_seo_social_description'] ) ) );
		}

		$existing_social_image    = $this->post_meta->get( $post_id, 'social_image' );
		$existing_social_image_id = absint( $this->post_meta->get( $post_id, 'social_image_id' ) );
		$social_image             = isset( $_POST['lightweight_seo_social_image'] ) ? esc_url_raw( wp_unslash( $_POST['lightweight_seo_social_image'] ) ) : $existing_social_image;
		$social_image_id          = isset( $_POST['lightweight_seo_social_image_id'] ) ? absint( $_POST['lightweight_seo_social_image_id'] ) : $existing_social_image_id;

		list( $social_image, $social_image_id ) = $this->post_meta->normalize_social_image(
			$social_image,
			$social_image_id,
			$existing_social_image,
			$existing_social_image_id
		);

		if ( isset( $_POST['lightweight_seo_social_image'] ) ) {
			$this->post_meta->update( $post_id, 'social_image', $social_image );
		}

		if ( isset( $_POST['lightweight_seo_social_image_id'] ) ) {
			$this->post_meta->update( $post_id, 'social_image_id', $social_image_id );
		}
	}
}
