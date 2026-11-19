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
				__( 'SEO settings', 'lightweight-seo' ),
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
		$own_social_image      = (string) $this->post_meta->get_social_image_url( $post->ID );
		$own_social_image_id   = absint( $post_meta['social_image_id'] ?? 0 );
		$post_type             = (string) ( $post->post_type ?? '' );
		$post_type_object      = get_post_type_object( $post_type );
		$type_label            = (string) ( $post_type_object->labels->name ?? $post_type );
		$type_hidden           = $this->settings->is_post_type_noindex( $post_type );
		$global_title_format   = $this->settings->get_title_format_for_post_type( $post_type );
		$tokens                = array(
			'%title%'    => (string) $post->post_title,
			'%sitename%' => (string) get_bloginfo( 'name' ),
			'%tagline%'  => (string) get_bloginfo( 'description' ),
			'%sep%'      => $this->settings->get_title_separator(),
		);
		$categories            = get_the_category( $post->ID );

		// The editor always shows the first page, and the category and author as they were last saved.
		$tokens['%page%']     = '';
		$tokens['%category%'] = ! empty( $categories[0]->name ) ? (string) $categories[0]->name : '';
		$tokens['%author%']   = (string) get_the_author_meta( 'display_name', (int) ( $post->post_author ?? 0 ) );
		$tokens['%year%']     = (string) wp_date( 'Y' );

		$resolve_tokens = static function ( $text ) use ( $tokens ) {
			return Lightweight_SEO_Text::resolve_tokens( $text, $tokens );
		};
		$current_title  = $resolve_tokens( $global_title_format );
		// Mirror the frontend fallback: a short summary of the excerpt or content, never the whole document.
		$fallback_description = Lightweight_SEO_Text::summarize( $this->normalize_preview_text( ! empty( $post->post_excerpt ) ? $post->post_excerpt : ( $post->post_content ?? '' ) ) );
		// A static front page without a description of its own prints the homepage meta description instead.
		$is_front_page    = 'page' === get_option( 'show_on_front' ) && absint( get_option( 'page_on_front', 0 ) ) === (int) $post->ID;
		$site_description = $is_front_page ? $this->normalize_preview_text( $this->settings->get( 'meta_description', '' ) ) : '';
		$is_protected     = '' !== (string) ( $post->post_password ?? '' );
		$content_facts    = Lightweight_SEO_Text::content_facts( $post->post_content ?? '' );

		if ( '' !== $site_description ) {
			$fallback_description = $site_description;
		}

		$preview_title        = $this->normalize_preview_text( $seo_title ? $resolve_tokens( $seo_title ) : $current_title );
		$preview_description  = $this->normalize_preview_text( $seo_description ? $resolve_tokens( $seo_description ) : $fallback_description );
		$preview_url          = $seo_canonical_url ? $seo_canonical_url : get_permalink( $post->ID );
		$social_preview_title = $this->normalize_preview_text( $social_title ? $resolve_tokens( $social_title ) : $preview_title );
		$social_preview_desc  = $this->normalize_preview_text( $social_description ? $resolve_tokens( $social_description ) : $preview_description );

		// The field holds only this page's own image. The featured image and the site default are fallbacks
		// for the preview and checks, and are never written back as the page's own choice.
		$featured_image    = has_post_thumbnail( $post->ID ) ? (string) get_the_post_thumbnail_url( $post->ID, 'full' ) : '';
		$featured_image_id = '' !== $featured_image ? absint( get_post_thumbnail_id( $post->ID ) ) : 0;
		$default_image     = (string) $this->settings->get_social_image_url();
		$social_image      = $own_social_image;
		$social_image_id   = $own_social_image_id;

		if ( '' === $social_image && '' !== $featured_image ) {
			$social_image    = $featured_image;
			$social_image_id = $featured_image_id;
		}

		if ( '' === $social_image ) {
			$social_image    = $default_image;
			$social_image_id = 0;
		}

		$checks    = $this->get_editor_checks(
			$post_meta,
			$preview_title,
			$preview_description,
			$social_image,
			$social_image_id,
			$type_hidden,
			array(
				'own_description'  => '' !== trim( (string) $seo_description ),
				'site_description' => $site_description,
				'protected'        => $is_protected,
				'content'          => $content_facts,
			)
		);
		$to_review = count(
			array_filter(
				$checks,
				static function ( $check ) {
					return 'warning' === $check['status'] && empty( $check['hidden'] );
				}
			)
		);
		// The script re-counts as the page is edited, so it needs both forms of the label.
		/* translators: %d: number of on-page checks that need a review. */
		$review_one = _n( '%d check to review', '%d checks to review', 1, 'lightweight-seo' );
		/* translators: %d: number of on-page checks that need a review. */
		$review_many  = _n( '%d check to review', '%d checks to review', 2, 'lightweight-seo' );
		$review_clear = __( 'All checks passed', 'lightweight-seo' );
		/* translators: %d: number of on-page checks that need a review. */
		$review_label  = $to_review ? sprintf( _n( '%d check to review', '%d checks to review', $to_review, 'lightweight-seo' ), $to_review ) : $review_clear;
		$title_tokens  = array( '%title%', '%sitename%', '%tagline%', '%sep%', '%category%', '%author%', '%year%', '%page%' );
		$robots_fields = array(
			'nofollow'  => array( $seo_nofollow, __( 'Prevent search engines from following links on this page', 'lightweight-seo' ) ),
			'noarchive' => array( $seo_noarchive, __( 'Prevent search engines from showing cached copies', 'lightweight-seo' ) ),
			'nosnippet' => array( $seo_nosnippet, __( 'Prevent text snippets from showing in search results', 'lightweight-seo' ) ),
		);
		?>
		<div
			class="lwseo lwseo-metabox"
			data-tokens="<?php echo esc_attr( wp_json_encode( $tokens ) ); ?>"
			data-title-format="<?php echo esc_attr( $global_title_format ); ?>"
			data-home-host="<?php echo esc_attr( strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) ); ?>"
			data-featured-image="<?php echo esc_url( $featured_image ); ?>"
			data-featured-width="<?php echo esc_attr( (string) $this->get_image_width( $featured_image_id ) ); ?>"
			data-default-image="<?php echo esc_url( $default_image ); ?>"
			data-type-noindex="<?php echo $type_hidden ? '1' : '0'; ?>"
			data-site-description="<?php echo esc_attr( $site_description ); ?>"
			data-protected="<?php echo $is_protected ? '1' : '0'; ?>"
			data-content-facts="<?php echo esc_attr( (string) wp_json_encode( $content_facts ) ); ?>"
		>
			<div class="lwseo-tabs" role="tablist" aria-label="<?php echo esc_attr( __( 'SEO editor panels', 'lightweight-seo' ) ); ?>">
				<button type="button" class="lwseo-tabs__tab is-active" role="tab" aria-selected="true" aria-controls="lightweight-seo-search-panel" id="lightweight-seo-search-tab"><?php esc_html_e( 'Search appearance', 'lightweight-seo' ); ?></button>
				<button type="button" class="lwseo-tabs__tab" role="tab" aria-selected="false" aria-controls="lightweight-seo-social-panel" id="lightweight-seo-social-tab" tabindex="-1"><?php esc_html_e( 'Social appearance', 'lightweight-seo' ); ?></button>
				<button type="button" class="lwseo-tabs__tab" role="tab" aria-selected="false" aria-controls="lightweight-seo-checks-panel" id="lightweight-seo-checks-tab" tabindex="-1">
					<?php esc_html_e( 'Checks', 'lightweight-seo' ); ?>
					<span class="lwseo-badge lwseo-checks-badge <?php echo $to_review ? 'is-warning' : 'is-success'; ?>" data-review-one="<?php echo esc_attr( $review_one ); ?>" data-review-many="<?php echo esc_attr( $review_many ); ?>" data-review-clear="<?php echo esc_attr( $review_clear ); ?>">
						<span class="lwseo-checks-badge__count<?php echo $to_review ? '' : ' dashicons dashicons-yes'; ?>" aria-hidden="true"><?php echo $to_review ? esc_html( (string) $to_review ) : ''; ?></span>
						<span class="screen-reader-text"><?php echo esc_html( $review_label ); ?></span>
					</span>
				</button>
			</div>
			<?php // Announces a changed count once, instead of on every keystroke. ?>
			<span class="screen-reader-text lwseo-checks-live" aria-live="polite"></span>
			<div class="lwseo-panel" id="lightweight-seo-search-panel" role="tabpanel" aria-labelledby="lightweight-seo-search-tab">
				<p class="lwseo-preview__caption"><?php esc_html_e( 'Search result preview', 'lightweight-seo' ); ?></p>
				<?php
				Lightweight_SEO_Admin_UI::search_preview(
					array(
						'url'         => $preview_url,
						'title'       => $preview_title,
						'description' => $preview_description,
						'live'        => true,
					)
				);
				?>
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
								<p class="lwseo-help"><?php esc_html_e( 'Leave empty to use this page’s own address. Set it only when this page is a copy of another page.', 'lightweight-seo' ); ?></p>
							</div>
						</div>
						<div class="lwseo-field lwseo-field--stacked">
							<label class="lwseo-field__label" for="lightweight_seo_noindex"><?php esc_html_e( 'Search results', 'lightweight-seo' ); ?></label>
							<div class="lwseo-field__control">
								<?php Lightweight_SEO_Admin_UI::visibility_select( 'lightweight_seo_noindex', $seo_noindex, $type_label, $type_hidden ); ?>
								<p class="lwseo-help"><?php esc_html_e( 'Decides whether search engines may show this page, whatever its content type is set to.', 'lightweight-seo' ); ?></p>
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
							<label class="lwseo-field__label" for="lightweight_seo_max_image_preview"><?php esc_html_e( 'Image preview size', 'lightweight-seo' ); ?></label>
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
				<?php
				Lightweight_SEO_Admin_UI::share_preview(
					array(
						'image'       => $social_image,
						'title'       => $social_preview_title,
						'description' => $social_preview_desc,
						'live'        => true,
					)
				);
				?>
				<div class="lwseo-field lwseo-field--stacked">
					<label class="lwseo-field__label" for="lightweight_seo_social_title"><?php esc_html_e( 'Social title', 'lightweight-seo' ); ?></label>
					<div class="lwseo-field__control">
						<input type="text" id="lightweight_seo_social_title" name="lightweight_seo_social_title" value="<?php echo esc_attr( $social_title ); ?>" class="large-text" data-fallback="<?php echo esc_attr( $preview_title ); ?>">
						<p class="lwseo-help"><?php esc_html_e( 'Leave empty to use the SEO title.', 'lightweight-seo' ); ?></p>
					</div>
				</div>
				<div class="lwseo-field lwseo-field--stacked">
					<label class="lwseo-field__label" for="lightweight_seo_social_description"><?php esc_html_e( 'Social description', 'lightweight-seo' ); ?></label>
					<div class="lwseo-field__control">
						<textarea id="lightweight_seo_social_description" name="lightweight_seo_social_description" rows="3" class="large-text" data-fallback="<?php echo esc_attr( $preview_description ); ?>"><?php echo esc_textarea( $social_description ); ?></textarea>
						<p class="lwseo-help"><?php esc_html_e( 'Leave empty to use the meta description.', 'lightweight-seo' ); ?></p>
					</div>
				</div>
				<div class="lwseo-field lwseo-field--stacked">
					<label class="lwseo-field__label" for="lightweight_seo_social_image"><?php esc_html_e( 'Social image', 'lightweight-seo' ); ?></label>
					<div class="lwseo-field__control">
						<?php
						Lightweight_SEO_Admin_UI::image_field(
							array(
								'url'      => $own_social_image,
								'id'       => $own_social_image_id,
								'width'    => $this->get_image_width( $own_social_image_id ),
								'url_name' => 'lightweight_seo_social_image',
								'id_name'  => 'lightweight_seo_social_image_id',
								'url_id'   => 'lightweight_seo_social_image',
								'id_id'    => 'lightweight_seo_social_image_id',
								'alt'      => __( 'Selected social image', 'lightweight-seo' ),
							)
						);
						?>
						<p class="lwseo-help"><?php esc_html_e( 'Leave empty to use the featured image, or the site default when there is none. Recommended size: 1200 × 630 pixels.', 'lightweight-seo' ); ?></p>
					</div>
				</div>
			</div>
			<div class="lwseo-panel" id="lightweight-seo-checks-panel" role="tabpanel" aria-labelledby="lightweight-seo-checks-tab" hidden>
				<p class="lwseo-help"><?php esc_html_e( 'These checks report facts and preview guidance only. They do not predict rankings.', 'lightweight-seo' ); ?></p>
				<ul class="lwseo-status-list lwseo-checks" data-edit-label="<?php echo esc_attr( __( 'Edit', 'lightweight-seo' ) ); ?>" data-prefix-success="<?php echo esc_attr( Lightweight_SEO_Admin_UI::status_prefix( 'success' ) ); ?>" data-prefix-warning="<?php echo esc_attr( Lightweight_SEO_Admin_UI::status_prefix( 'warning' ) ); ?>">
					<?php
					foreach ( $checks as $check ) {
						$passes = 'success' === $check['status'];
						$action = $passes || '' === $check['target'] ? array() : array(
							'label' => __( 'Edit', 'lightweight-seo' ),
							'url'   => $check['target'],
						);

						// Both outcomes travel with the row so the script can switch it as the page is edited.
						$attributes = array(
							'data-check'  => $check['id'],
							'data-pass'   => $check['pass'],
							'data-fail'   => $check['fail'],
							'data-target' => $check['target'],
						);

						foreach ( $check['variants'] as $variant => $message ) {
							$attributes[ 'data-fail-' . $variant ] = $message;
						}

						foreach ( $check['pass_variants'] as $variant => $message ) {
							$attributes[ 'data-pass-' . $variant ] = $message;
						}

						$attributes['hidden'] = ! empty( $check['hidden'] );

						Lightweight_SEO_Admin_UI::status( $check['status'], $check['label'], $check['message'], $action, $attributes );
					}
					?>
				</ul>
				<?php // The report is produced by the public page, so there is one only once the page is published. ?>
				<?php if ( 'publish' === (string) ( $post->post_status ?? '' ) && current_user_can( 'manage_options' ) ) : ?>
					<p class="lwseo-help"><a href="<?php echo esc_url( Lightweight_SEO_Admin_UI::inspect_url( (string) get_permalink( $post->ID ) ) ); ?>"><?php esc_html_e( 'Inspect what is sent for this page', 'lightweight-seo' ); ?></a></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Build deterministic, non-scored editor checks.
	 *
	 * @return array
	 */
	private function get_editor_checks( $post_meta, $title, $description, $social_image, $social_image_id, $type_hidden = false, $page = array() ) {
		$page               = wp_parse_args(
			$page,
			array(
				'own_description'  => '' !== trim( (string) ( $post_meta['seo_description'] ?? '' ) ),
				'site_description' => '',
				'protected'        => false,
				'content'          => Lightweight_SEO_Text::content_facts( '' ),
			)
		);
		$checks             = array();
		$checks[]           = $this->make_check( 'title', '' !== (string) $title, __( 'SEO title', 'lightweight-seo' ), __( 'A title is available for the search preview.', 'lightweight-seo' ), __( 'Add a clear SEO title.', 'lightweight-seo' ), '#lightweight_seo_title' );
		$checks[]           = $this->get_description_check( $description, $page );
		$title_length       = Lightweight_SEO_Text::length( $title );
		$checks[]           = $this->make_check( 'title_length', $title_length >= 30 && $title_length <= 60, __( 'Title preview length', 'lightweight-seo' ), __( 'The title is within the usual preview guidance range.', 'lightweight-seo' ), __( 'The title may be shortened or expanded in search results; review it for clarity.', 'lightweight-seo' ), '#lightweight_seo_title' );
		$description_length = Lightweight_SEO_Text::length( $description );
		$checks[]           = $this->make_check( 'description_length', $description_length >= 120 && $description_length <= 160, __( 'Description preview length', 'lightweight-seo' ), __( 'The description is within the usual preview guidance range.', 'lightweight-seo' ), __( 'The description may truncate or leave unused preview space; review it for clarity.', 'lightweight-seo' ), '#lightweight_seo_description' );
		$checks[]           = $this->make_check( 'social_image', '' !== (string) $social_image, __( 'Social image', 'lightweight-seo' ), __( 'A social image is available.', 'lightweight-seo' ), __( 'Choose an image for richer social previews.', 'lightweight-seo' ), '#lightweight_seo_social_image' );

		// A null result means the check does not apply yet; the row is printed hidden so the script can show it later.
		$image_width = $this->get_image_width( $social_image_id );
		$checks[]    = $this->make_check( 'social_image_width', $image_width ? $image_width >= 1200 : null, __( 'Social image width', 'lightweight-seo' ), __( 'The selected image meets the 1200px width recommendation.', 'lightweight-seo' ), __( 'The selected image is below the 1200px width recommendation.', 'lightweight-seo' ), '#lightweight_seo_social_image' );

		// A page is hidden by its own choice or, without one, by its content type. Each has its own message.
		$own_choice     = (string) ( $post_meta['seo_noindex'] ?? '' );
		$hidden_by_type = __( 'This content type is hidden from search results, so this page is too. Choose “Show in search results” to make an exception.', 'lightweight-seo' );
		$check          = $this->make_check( 'indexing', ! $this->settings->resolve_noindex( $own_choice, $type_hidden ), __( 'Indexing', 'lightweight-seo' ), __( 'This page can be indexed.', 'lightweight-seo' ), __( 'This page is intentionally set to noindex. Confirm that is expected.', 'lightweight-seo' ), '#lightweight_seo_noindex' );

		$check['variants']['type'] = $hidden_by_type;

		if ( 'warning' === $check['status'] && '1' !== $own_choice ) {
			$check['message'] = $hidden_by_type;
		}

		$checks[] = $check;

		$canonical   = (string) ( $post_meta['seo_canonical_url'] ?? '' );
		$valid_url   = '' !== $canonical && false !== filter_var( $canonical, FILTER_VALIDATE_URL );
		$same_host   = $valid_url && strtolower( (string) wp_parse_url( $canonical, PHP_URL_HOST ) ) === strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$invalid_url = __( 'The custom canonical URL is invalid.', 'lightweight-seo' );
		$check       = $this->make_check( 'canonical', '' === $canonical ? null : $valid_url && $same_host, __( 'Custom canonical', 'lightweight-seo' ), __( 'The custom canonical uses this site’s host.', 'lightweight-seo' ), __( 'The custom canonical points to a different host. Confirm that this is intentional.', 'lightweight-seo' ), '#lightweight_seo_canonical_url' );

		$check['variants']['invalid'] = $invalid_url;

		if ( '' !== $canonical && ! $valid_url ) {
			$check['message'] = $invalid_url;
		}

		$checks[] = $check;

		$facts = $page['content'];
		/* translators: 1: number of images without alternative text. 2: number of images in the content. */
		$no_alt   = __( 'Images in the content without alternative text: %1$d of %2$d. Describe each image that carries meaning; an image that is only decorative may keep an empty one.', 'lightweight-seo' );
		$check    = $this->make_check( 'image_alt', $facts['images'] ? 0 === $facts['imagesWithoutAlt'] : null, __( 'Image alternative text', 'lightweight-seo' ), __( 'Every image in the content has alternative text.', 'lightweight-seo' ), $no_alt, '' );
		$checks[] = $this->with_counts( $check, array( $facts['imagesWithoutAlt'], $facts['images'] ) );

		/* translators: %d: number of H1 headings in the content, two or more. */
		$many_h1  = __( 'The content has %d H1 headings. The theme usually prints the page title as the H1, so use Heading 2 and below inside the content.', 'lightweight-seo' );
		$check    = $this->make_check( 'content_headings', $facts['h1'] <= 1, __( 'Main headings', 'lightweight-seo' ), __( 'The content has no more than one H1 heading.', 'lightweight-seo' ), $many_h1, '' );
		$checks[] = $this->with_counts( $check, array( $facts['h1'] ) );

		if ( class_exists( 'Lightweight_SEO_Compatibility_Service' ) ) {
			$conflicts = ( new Lightweight_SEO_Compatibility_Service() )->get_conflicting_plugins();
			/* translators: %s: comma-separated list of conflicting SEO plugins. */
			$checks[] = $this->make_check( 'conflicts', empty( $conflicts ), __( 'SEO output conflicts', 'lightweight-seo' ), __( 'No conflicting SEO plugin was detected.', 'lightweight-seo' ), sprintf( __( 'Another SEO plugin is active: %s. Lightweight SEO suppresses overlapping output.', 'lightweight-seo' ), implode( ', ', $conflicts ) ), '' );
		}

		return $checks;
	}

	/**
	 * Say which description the page prints, and when it prints none.
	 *
	 * The order is the page's: a password removes it, the description written here wins,
	 * a static front page then uses the homepage meta description, and other pages a
	 * summary of the excerpt or content.
	 *
	 * @param string $description Description the preview shows.
	 * @param array  $page        Facts about the page: own_description, site_description, protected.
	 * @return array
	 */
	private function get_description_check( $description, $page ) {
		$passes = '' !== (string) $description && ! $page['protected'];
		$check  = $this->make_check(
			'description',
			$passes,
			__( 'Meta description', 'lightweight-seo' ),
			__( 'The description written here is printed.', 'lightweight-seo' ),
			__( 'No description is printed for this page: nothing is written here, and the page has no excerpt or content text to summarize. Write one, especially when the theme or custom fields hold the page text.', 'lightweight-seo' ),
			'#lightweight_seo_description'
		);

		$check['pass_variants'] = array(
			'summary' => __( 'Nothing is written here, so a summary of the excerpt or content is printed.', 'lightweight-seo' ),
			'site'    => __( 'Nothing is written here, so the homepage meta description is printed.', 'lightweight-seo' ),
		);
		$check['variants']      = array(
			'protected' => __( 'No description is printed while the page is password protected.', 'lightweight-seo' ),
		);

		if ( $page['protected'] ) {
			$check['message'] = $check['variants']['protected'];
		} elseif ( $passes && ! $page['own_description'] ) {
			$check['message'] = $check['pass_variants'][ '' !== (string) $page['site_description'] ? 'site' : 'summary' ];
		}

		return $check;
	}

	/**
	 * Fill the counts of a check's message, keeping the template for the script.
	 *
	 * @param array $check  Check from make_check().
	 * @param int[] $counts Values for %1$d, %2$d, and so on.
	 * @return array
	 */
	private function with_counts( $check, $counts ) {
		$check['message'] = vsprintf( str_replace( '%d', '%1$d', $check['message'] ), $counts );

		return $check;
	}

	/**
	 * Describe one check with both of its outcomes.
	 *
	 * The IDs match the rules in admin/js/lightweight-seo-admin.js, which re-evaluate
	 * every check except "conflicts" while the page is being edited.
	 *
	 * @param string    $id      Stable check ID.
	 * @param bool|null $passes  Result, or null when the check does not apply.
	 * @param string    $label   Check name.
	 * @param string    $success Message shown when the check passes.
	 * @param string    $warning Message shown when it does not.
	 * @param string    $target  Fragment of the field that fixes it, or an empty string.
	 * @return array
	 */
	private function make_check( $id, $passes, $label, $success, $warning, $target ) {
		return array(
			'id'            => $id,
			'status'        => false === $passes ? 'warning' : 'success',
			'label'         => $label,
			'message'       => false === $passes ? $warning : $success,
			'pass'          => $success,
			'fail'          => $warning,
			'variants'      => array(),
			'pass_variants' => array(),
			'target'        => $target,
			'hidden'        => null === $passes,
		);
	}

	/**
	 * Get the stored width of an attachment, or 0 when it is unknown.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return int
	 */
	private function get_image_width( $attachment_id ) {
		if ( ! $attachment_id ) {
			return 0;
		}

		$image_data = wp_get_attachment_metadata( $attachment_id );

		return absint( $image_data['width'] ?? 0 );
	}

	private function normalize_preview_text( $value ) {
		return Lightweight_SEO_Text::plain( $value );
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
			$this->post_meta->update( $post_id, 'seo_title', Lightweight_SEO_Text::sanitize_template( wp_unslash( $_POST['lightweight_seo_title'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Lightweight_SEO_Text, which wraps the WordPress text sanitizer so title variables survive it.
		}

		if ( isset( $_POST['lightweight_seo_description'] ) ) {
			$this->post_meta->update( $post_id, 'seo_description', Lightweight_SEO_Text::sanitize_textarea_template( wp_unslash( $_POST['lightweight_seo_description'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Lightweight_SEO_Text, which wraps the WordPress text sanitizer so title variables survive it.
		}

		if ( isset( $_POST['lightweight_seo_canonical_url'] ) ) {
			$this->post_meta->update( $post_id, 'seo_canonical_url', esc_url_raw( wp_unslash( $_POST['lightweight_seo_canonical_url'] ) ) );
		}

		// The search visibility choice is hide, show, or follow the content type.
		$noindex = isset( $_POST['lightweight_seo_noindex'] ) ? sanitize_text_field( wp_unslash( $_POST['lightweight_seo_noindex'] ) ) : '';
		$this->post_meta->update( $post_id, 'seo_noindex', $this->settings->normalize_noindex_choice( $noindex ) );

		// Checkbox fields need to be handled differently
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
			$this->post_meta->update( $post_id, 'social_title', Lightweight_SEO_Text::sanitize_template( wp_unslash( $_POST['lightweight_seo_social_title'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Lightweight_SEO_Text, which wraps the WordPress text sanitizer so title variables survive it.
		}

		if ( isset( $_POST['lightweight_seo_social_description'] ) ) {
			$this->post_meta->update( $post_id, 'social_description', Lightweight_SEO_Text::sanitize_textarea_template( wp_unslash( $_POST['lightweight_seo_social_description'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by Lightweight_SEO_Text, which wraps the WordPress text sanitizer so title variables survive it.
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
