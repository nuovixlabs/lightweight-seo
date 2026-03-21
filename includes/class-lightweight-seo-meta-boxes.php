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
		// Add nonce for security
		wp_nonce_field( 'lightweight_seo_meta_box', 'lightweight_seo_meta_box_nonce' );

		// Get saved values
		$post_meta             = $this->post_meta->get_all( $post->ID );
		$seo_title             = $post_meta['seo_title'] ?? '';
		$seo_description       = $post_meta['seo_description'] ?? '';
		$seo_keywords          = $post_meta['seo_keywords'] ?? '';
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

		// Get global settings for reference
		$global_settings     = $this->settings->get_all();
		$global_title_format = $this->settings->get_title_format();
		$global_description  = $global_settings['meta_description'] ?? '';

		// Calculate current title
		$current_title = str_replace(
			array( '%title%', '%sitename%', '%tagline%', '%sep%' ),
			array(
				$post->post_title,
				get_bloginfo( 'name' ),
				get_bloginfo( 'description' ),
				LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR,
			),
			$global_title_format
		);

		// Start output
		?>
		<div class="lightweight-seo-meta-box">
			<div class="lightweight-seo-tabs">
				<div class="lightweight-seo-tab-nav">
					<span class="nav-tab nav-tab-active" data-tab="general"><?php _e( 'General SEO', 'lightweight-seo' ); ?></span>
					<span class="nav-tab" data-tab="social"><?php _e( 'Social Media', 'lightweight-seo' ); ?></span>
				</div>
				
				<div class="lightweight-seo-tab-content">
					<!-- General SEO Tab -->
					<div class="tab-content active" id="general">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="lightweight_seo_title"><?php _e( 'SEO Title', 'lightweight-seo' ); ?></label>
								</th>
								<td>
									<input type="text" id="lightweight_seo_title" name="lightweight_seo_title" value="<?php echo esc_attr( $seo_title ); ?>" class="large-text">
									<p class="description">
										<?php _e( 'Current title (if not customized):', 'lightweight-seo' ); ?> 
										<strong><?php echo esc_html( $current_title ); ?></strong>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="lightweight_seo_description"><?php _e( 'Meta Description', 'lightweight-seo' ); ?></label>
								</th>
								<td>
									<textarea id="lightweight_seo_description" name="lightweight_seo_description" rows="3" class="large-text"><?php echo esc_textarea( $seo_description ); ?></textarea>
									<p class="description">
										<?php _e( 'Optimal length: 150-160 characters', 'lightweight-seo' ); ?>
										<?php if ( ! empty( $global_description ) ) : ?>
											<br>
											<?php _e( 'Global default description:', 'lightweight-seo' ); ?> 
											<em><?php echo esc_html( $global_description ); ?></em>
										<?php endif; ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="lightweight_seo_keywords"><?php _e( 'Meta Keywords', 'lightweight-seo' ); ?></label>
								</th>
								<td>
									<input type="text" id="lightweight_seo_keywords" name="lightweight_seo_keywords" value="<?php echo esc_attr( $seo_keywords ); ?>" class="large-text">
									<p class="description">
										<?php _e( 'Comma-separated list of keywords', 'lightweight-seo' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="lightweight_seo_canonical_url"><?php _e( 'Canonical URL', 'lightweight-seo' ); ?></label>
								</th>
								<td>
									<input type="url" id="lightweight_seo_canonical_url" name="lightweight_seo_canonical_url" value="<?php echo esc_url( $seo_canonical_url ); ?>" class="large-text">
									<p class="description">
										<?php _e( 'Optional canonical URL override. Leave empty to use the page permalink.', 'lightweight-seo' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php _e( 'Search Engine Indexing', 'lightweight-seo' ); ?>
								</th>
								<td>
									<label>
										<input type="checkbox" name="lightweight_seo_noindex" value="1" <?php checked( $seo_noindex, '1' ); ?>>
										<?php _e( 'Prevent search engines from indexing this page', 'lightweight-seo' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<?php _e( 'Advanced Robots', 'lightweight-seo' ); ?>
								</th>
								<td>
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
									<p class="description" style="margin-top: 10px;">
										<label for="lightweight_seo_max_image_preview"><?php _e( 'Max Image Preview', 'lightweight-seo' ); ?></label><br>
										<select id="lightweight_seo_max_image_preview" name="lightweight_seo_max_image_preview">
											<option value="" <?php selected( $seo_max_image_preview, '' ); ?>><?php _e( 'Use global default', 'lightweight-seo' ); ?></option>
											<option value="large" <?php selected( $seo_max_image_preview, 'large' ); ?>><?php _e( 'Large', 'lightweight-seo' ); ?></option>
											<option value="standard" <?php selected( $seo_max_image_preview, 'standard' ); ?>><?php _e( 'Standard', 'lightweight-seo' ); ?></option>
											<option value="none" <?php selected( $seo_max_image_preview, 'none' ); ?>><?php _e( 'None', 'lightweight-seo' ); ?></option>
										</select>
									</p>
								</td>
							</tr>
						</table>
					</div>
					
					<!-- Social Media Tab -->
					<div class="tab-content" id="social">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="lightweight_seo_social_title"><?php _e( 'Social Title', 'lightweight-seo' ); ?></label>
								</th>
								<td>
									<input type="text" id="lightweight_seo_social_title" name="lightweight_seo_social_title" value="<?php echo esc_attr( $social_title ); ?>" class="large-text">
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
									<textarea id="lightweight_seo_social_description" name="lightweight_seo_social_description" rows="3" class="large-text"><?php echo esc_textarea( $social_description ); ?></textarea>
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
												<img src="<?php echo esc_url( $social_image ); ?>" alt="<?php _e( 'Preview', 'lightweight-seo' ); ?>" style="max-width: 300px; margin-top: 10px;">
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
				</div>
			</div>
		</div>
		<?php
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
