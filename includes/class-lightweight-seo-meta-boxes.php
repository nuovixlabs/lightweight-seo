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
		$tokens                = array(
			'%title%'    => (string) $post->post_title,
			'%sitename%' => (string) get_bloginfo( 'name' ),
			'%tagline%'  => (string) get_bloginfo( 'description' ),
			'%sep%'      => LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR,
		);
		$resolve_tokens        = static function ( $text ) use ( $tokens ) {
			return str_replace( array_keys( $tokens ), array_values( $tokens ), (string) $text );
		};
		$current_title         = $resolve_tokens( $global_title_format );
		// Mirror the frontend fallback: a short summary of the excerpt or content, never the whole document.
		$fallback_description = Lightweight_SEO_Text::summarize( $this->normalize_preview_text( ! empty( $post->post_excerpt ) ? $post->post_excerpt : ( $post->post_content ?? '' ) ) );
		$preview_title        = $this->normalize_preview_text( $seo_title ? $resolve_tokens( $seo_title ) : $current_title );
		$preview_description  = $this->normalize_preview_text( $seo_description ? $resolve_tokens( $seo_description ) : $fallback_description );
		$preview_url          = $seo_canonical_url ? $seo_canonical_url : get_permalink( $post->ID );
		$social_preview_title = $this->normalize_preview_text( $social_title ? $resolve_tokens( $social_title ) : $preview_title );
		$social_preview_desc  = $this->normalize_preview_text( $social_description ? $resolve_tokens( $social_description ) : $preview_description );

		if ( empty( $social_image ) && has_post_thumbnail( $post->ID ) ) {
			$social_image    = get_the_post_thumbnail_url( $post->ID, 'full' );
			$social_image_id = get_post_thumbnail_id( $post->ID );
		}

		if ( empty( $social_image ) ) {
			$social_image = $this->settings->get_social_image_url();
		}

		$checks        = $this->get_editor_checks( $post, $post_meta, $preview_title, $preview_description, $social_image, $social_image_id );
		$to_review     = count(
			array_filter(
				$checks,
				static function ( $check ) {
					return 'success' !== $check['status'];
				}
			)
		);
		$title_tokens  = array( '%title%', '%sitename%', '%tagline%', '%sep%' );
		$robots_fields = array(
			'noindex'   => array( $seo_noindex, __( 'Prevent search engines from indexing this page', 'lightweight-seo' ) ),
			'nofollow'  => array( $seo_nofollow, __( 'Prevent search engines from following links on this page', 'lightweight-seo' ) ),
			'noarchive' => array( $seo_noarchive, __( 'Prevent search engines from showing cached copies', 'lightweight-seo' ) ),
			'nosnippet' => array( $seo_nosnippet, __( 'Prevent text snippets from showing in search results', 'lightweight-seo' ) ),
		);
		?>
		<div class="lwseo lwseo-metabox" data-tokens="<?php echo esc_attr( wp_json_encode( $tokens ) ); ?>">
			<div class="lwseo-tabs" role="tablist" aria-label="<?php echo esc_attr( __( 'SEO editor panels', 'lightweight-seo' ) ); ?>">
				<button type="button" class="lwseo-tabs__tab is-active" role="tab" aria-selected="true" aria-controls="lightweight-seo-search-panel" id="lightweight-seo-search-tab"><?php esc_html_e( 'Search appearance', 'lightweight-seo' ); ?></button>
				<button type="button" class="lwseo-tabs__tab" role="tab" aria-selected="false" aria-controls="lightweight-seo-social-panel" id="lightweight-seo-social-tab" tabindex="-1"><?php esc_html_e( 'Social appearance', 'lightweight-seo' ); ?></button>
				<button type="button" class="lwseo-tabs__tab" role="tab" aria-selected="false" aria-controls="lightweight-seo-checks-panel" id="lightweight-seo-checks-tab" tabindex="-1">
					<?php
					esc_html_e( 'Checks', 'lightweight-seo' );

					if ( $to_review ) {
						/* translators: %d: number of on-page checks that need a review. */
						$review_label = sprintf( _n( '%d check to review', '%d checks to review', $to_review, 'lightweight-seo' ), $to_review );

						echo ' <span class="lwseo-badge is-warning"><span aria-hidden="true">' . esc_html( (string) $to_review ) . '</span><span class="screen-reader-text">' . esc_html( $review_label ) . '</span></span>';
					}
					?>
				</button>
			</div>
			<div class="lwseo-panel" id="lightweight-seo-search-panel" role="tabpanel" aria-labelledby="lightweight-seo-search-tab">
				<p class="lwseo-preview__caption"><?php esc_html_e( 'Search result preview', 'lightweight-seo' ); ?></p>
				<div class="lwseo-preview lwseo-preview--search" aria-live="polite">
					<div class="lwseo-preview__body">
						<p class="lwseo-preview__url"><?php echo esc_html( $preview_url ); ?></p>
						<p class="lwseo-preview__title" data-preview="title"><?php echo esc_html( $preview_title ); ?></p>
						<p class="lwseo-preview__description" data-preview="description"><?php echo esc_html( $preview_description ); ?></p>
					</div>
				</div>
				<div class="lwseo-field lwseo-field--stacked">
					<label class="lwseo-field__label" for="lightweight_seo_title"><?php esc_html_e( 'SEO title', 'lightweight-seo' ); ?></label>
					<div class="lwseo-field__control">
						<input type="text" id="lightweight_seo_title" name="lightweight_seo_title" value="<?php echo esc_attr( $seo_title ); ?>" class="large-text" data-fallback="<?php echo esc_attr( $current_title ); ?>" aria-describedby="lightweight-seo-title-guidance" data-lwseo-tokens>
						<div class="lwseo-meter" data-meter-for="lightweight_seo_title" data-min="30" data-max="60" aria-hidden="true"><span class="lwseo-meter__bar"></span></div>
						<p class="lwseo-help" id="lightweight-seo-title-guidance"><span data-count-for="lightweight_seo_title"><?php echo esc_html( (string) Lightweight_SEO_Text::length( $preview_title ) ); ?></span> <?php esc_html_e( 'characters. Aim for a clear, specific title; roughly 30–60 characters is useful preview guidance, not a ranking rule.', 'lightweight-seo' ); ?></p>
						<?php Lightweight_SEO_Admin_UI::tokens( $title_tokens ); ?>
					</div>
				</div>
				<div class="lwseo-field lwseo-field--stacked">
					<label class="lwseo-field__label" for="lightweight_seo_description"><?php esc_html_e( 'Meta description', 'lightweight-seo' ); ?></label>
					<div class="lwseo-field__control">
						<textarea id="lightweight_seo_description" name="lightweight_seo_description" rows="3" class="large-text" data-fallback="<?php echo esc_attr( $fallback_description ); ?>" aria-describedby="lightweight-seo-description-guidance"><?php echo esc_textarea( $seo_description ); ?></textarea>
						<div class="lwseo-meter" data-meter-for="lightweight_seo_description" data-min="120" data-max="160" aria-hidden="true"><span class="lwseo-meter__bar"></span></div>
						<p class="lwseo-help" id="lightweight-seo-description-guidance"><span data-count-for="lightweight_seo_description"><?php echo esc_html( (string) Lightweight_SEO_Text::length( $preview_description ) ); ?></span> <?php esc_html_e( 'characters. Roughly 120–160 characters often fits a search preview, but clarity matters more than hitting a number.', 'lightweight-seo' ); ?></p>
					</div>
				</div>
				<details class="lwseo-details">
					<summary><?php esc_html_e( 'Advanced canonical and robots controls', 'lightweight-seo' ); ?></summary>
					<div class="lwseo-details__body">
						<div class="lwseo-field lwseo-field--stacked">
							<label class="lwseo-field__label" for="lightweight_seo_canonical_url"><?php esc_html_e( 'Canonical URL', 'lightweight-seo' ); ?></label>
							<div class="lwseo-field__control">
								<input type="url" id="lightweight_seo_canonical_url" name="lightweight_seo_canonical_url" value="<?php echo esc_url( $seo_canonical_url ); ?>" class="large-text">
								<p class="lwseo-help"><?php esc_html_e( 'Leave empty to use this page’s permalink.', 'lightweight-seo' ); ?></p>
							</div>
						</div>
						<fieldset class="lwseo-field lwseo-field--stacked">
							<legend class="lwseo-field__label"><?php esc_html_e( 'Robots directives', 'lightweight-seo' ); ?></legend>
							<div class="lwseo-field__control lwseo-choices">
								<?php
								foreach ( $robots_fields as $directive => $robots_field ) {
									Lightweight_SEO_Admin_UI::choice( 'lightweight_seo_' . $directive, '1' === (string) $robots_field[0], $robots_field[1], '', 'lightweight_seo_' . $directive );
								}
								?>
							</div>
						</fieldset>
						<div class="lwseo-field lwseo-field--stacked">
							<label class="lwseo-field__label" for="lightweight_seo_max_image_preview"><?php esc_html_e( 'Max image preview', 'lightweight-seo' ); ?></label>
							<div class="lwseo-field__control">
								<select id="lightweight_seo_max_image_preview" name="lightweight_seo_max_image_preview">
									<option value="" <?php selected( $seo_max_image_preview, '' ); ?>><?php esc_html_e( 'Use global default', 'lightweight-seo' ); ?></option>
									<option value="large" <?php selected( $seo_max_image_preview, 'large' ); ?>><?php esc_html_e( 'Large', 'lightweight-seo' ); ?></option>
									<option value="standard" <?php selected( $seo_max_image_preview, 'standard' ); ?>><?php esc_html_e( 'Standard', 'lightweight-seo' ); ?></option>
									<option value="none" <?php selected( $seo_max_image_preview, 'none' ); ?>><?php esc_html_e( 'None', 'lightweight-seo' ); ?></option>
								</select>
							</div>
						</div>
					</div>
				</details>
			</div>
			<div class="lwseo-panel" id="lightweight-seo-social-panel" role="tabpanel" aria-labelledby="lightweight-seo-social-tab" hidden>
				<p class="lwseo-preview__caption"><?php esc_html_e( 'Share preview', 'lightweight-seo' ); ?></p>
				<div class="lwseo-preview lwseo-preview--social" aria-live="polite">
					<div class="lwseo-preview__media">
						<?php if ( $social_image ) : ?>
							<img src="<?php echo esc_url( $social_image ); ?>" alt="">
						<?php endif; ?>
					</div>
					<div class="lwseo-preview__body">
						<p class="lwseo-preview__site"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
						<p class="lwseo-preview__title" data-preview="social-title"><?php echo esc_html( $social_preview_title ); ?></p>
						<p class="lwseo-preview__description" data-preview="social-description"><?php echo esc_html( $social_preview_desc ); ?></p>
					</div>
				</div>
				<div class="lwseo-field lwseo-field--stacked">
					<label class="lwseo-field__label" for="lightweight_seo_social_title"><?php esc_html_e( 'Social title', 'lightweight-seo' ); ?></label>
					<div class="lwseo-field__control">
						<input type="text" id="lightweight_seo_social_title" name="lightweight_seo_social_title" value="<?php echo esc_attr( $social_title ); ?>" class="large-text" data-fallback="<?php echo esc_attr( $preview_title ); ?>">
						<p class="lwseo-help"><?php esc_html_e( 'Title used when shared on social media. If empty, the SEO title or post title will be used.', 'lightweight-seo' ); ?></p>
					</div>
				</div>
				<div class="lwseo-field lwseo-field--stacked">
					<label class="lwseo-field__label" for="lightweight_seo_social_description"><?php esc_html_e( 'Social description', 'lightweight-seo' ); ?></label>
					<div class="lwseo-field__control">
						<textarea id="lightweight_seo_social_description" name="lightweight_seo_social_description" rows="3" class="large-text" data-fallback="<?php echo esc_attr( $preview_description ); ?>"><?php echo esc_textarea( $social_description ); ?></textarea>
						<p class="lwseo-help"><?php esc_html_e( 'Description used when shared on social media. If empty, the Meta Description will be used.', 'lightweight-seo' ); ?></p>
					</div>
				</div>
				<div class="lwseo-field lwseo-field--stacked">
					<label class="lwseo-field__label" for="lightweight_seo_social_image"><?php esc_html_e( 'Social image', 'lightweight-seo' ); ?></label>
					<div class="lwseo-field__control">
						<?php
						Lightweight_SEO_Admin_UI::image_field(
							array(
								'url'      => $social_image,
								'id'       => $social_image_id,
								'url_name' => 'lightweight_seo_social_image',
								'id_name'  => 'lightweight_seo_social_image_id',
								'url_id'   => 'lightweight_seo_social_image',
								'id_id'    => 'lightweight_seo_social_image_id',
								'alt'      => __( 'Selected social image', 'lightweight-seo' ),
							)
						);
						?>
						<p class="lwseo-help"><?php esc_html_e( 'Image used when shared on social media. Recommended size: 1200x630px.', 'lightweight-seo' ); ?></p>
					</div>
				</div>
			</div>
			<div class="lwseo-panel" id="lightweight-seo-checks-panel" role="tabpanel" aria-labelledby="lightweight-seo-checks-tab" hidden>
				<p class="lwseo-help"><?php esc_html_e( 'These checks report facts and preview guidance only. They do not predict rankings.', 'lightweight-seo' ); ?></p>
				<ul class="lwseo-status-list lwseo-checks">
					<?php
					foreach ( $checks as $check ) {
						$action = empty( $check['target'] ) ? array() : array(
							'label' => __( 'Edit', 'lightweight-seo' ),
							'url'   => $check['target'],
						);

						Lightweight_SEO_Admin_UI::status( $check['status'], $check['label'], $check['message'], $action );
					}
					?>
				</ul>
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
		$title_length       = Lightweight_SEO_Text::length( $title );
		$checks[]           = $this->make_check( $title_length >= 30 && $title_length <= 60, __( 'Title preview length', 'lightweight-seo' ), __( 'The title is within the usual preview guidance range.', 'lightweight-seo' ), __( 'The title may be shortened or expanded in search results; review it for clarity.', 'lightweight-seo' ), '#lightweight_seo_title' );
		$description_length = Lightweight_SEO_Text::length( $description );
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
		$value = wp_strip_all_tags( strip_shortcodes( (string) $value ), true );

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
