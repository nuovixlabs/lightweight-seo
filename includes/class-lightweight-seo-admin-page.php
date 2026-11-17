<?php
/**
 * The Lightweight SEO settings page.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Renders the settings page shell, its tabs, and the selected tab.
 */
class Lightweight_SEO_Admin_Page {

	/**
	 * The admin-post action that hides or shows the setup checklist.
	 */
	const CHECKLIST_ACTION = 'lightweight_seo_setup_checklist';

	/**
	 * The user meta key that remembers a dismissed setup checklist.
	 */
	const CHECKLIST_META_KEY = 'lightweight_seo_checklist_dismissed';

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings    $settings
	 */
	private $settings;

	/**
	 * Tools tab renderer, which owns the standalone tool action forms.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Admin_Tools_Fields    $tools_fields
	 */
	private $tools_fields;

	/**
	 * Shared post meta service, used to read the static front page's own SEO fields.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Post_Meta    $post_meta
	 */
	private $post_meta;

	/**
	 * Initialize the page.
	 *
	 * @since    1.1.0
	 * @param    string                                $plugin_name     The name of this plugin.
	 * @param    Lightweight_SEO_Settings              $settings        Shared settings service.
	 * @param    Lightweight_SEO_Admin_Tools_Fields    $tools_fields    Tools tab renderer.
	 * @param    Lightweight_SEO_Post_Meta             $post_meta       Shared post meta service.
	 */
	public function __construct( $plugin_name, $settings, $tools_fields, $post_meta ) {
		$this->plugin_name  = $plugin_name;
		$this->settings     = $settings;
		$this->tools_fields = $tools_fields;
		$this->post_meta    = $post_meta;
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		$tabs        = $this->get_admin_tabs();
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection checked against an allowlist.

		// The Developer API tab moved into Tools. Links to it still work and open that card.
		$show_developer = 'developer' === $current_tab;

		if ( $show_developer ) {
			$current_tab = 'tools';
		}

		if ( ! isset( $tabs[ $current_tab ] ) ) {
			$current_tab = 'overview';
		}

		// WordPress files its own "Settings saved." under another setting, so a top-level page confirms the save itself.
		if ( isset( $_GET['settings-updated'] ) && empty( get_settings_errors( LIGHTWEIGHT_SEO_OPTION_NAME ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag set by the options.php redirect.
			add_settings_error( LIGHTWEIGHT_SEO_OPTION_NAME, 'settings_updated', __( 'Settings saved.', 'lightweight-seo' ), 'success' );
		}
		?>
		<div class="wrap lwseo lwseo-page">
			<header class="lwseo-header">
				<span class="lwseo-header__mark dashicons dashicons-search" aria-hidden="true"></span>
				<h1 class="lwseo-header__title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
				<?php
				/* translators: %s: plugin version number. */
				Lightweight_SEO_Admin_UI::badge( sprintf( __( 'Version %s', 'lightweight-seo' ), LIGHTWEIGHT_SEO_VERSION ) );
				?>
			</header>
			<?php // WordPress moves admin notices to this marker, keeping them out of the header row. ?>
			<hr class="wp-header-end">
			<?php settings_errors( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>
			<nav class="lwseo-tabs" aria-label="<?php echo esc_attr( __( 'Lightweight SEO settings', 'lightweight-seo' ) ); ?>">
				<?php foreach ( $tabs as $tab_id => $label ) : ?>
					<a class="lwseo-tabs__tab <?php echo $current_tab === $tab_id ? 'is-active' : ''; ?>" <?php echo $current_tab === $tab_id ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( $this->get_tab_url( $tab_id ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			if ( 'overview' === $current_tab ) {
				$checklist_dismissed = $this->is_checklist_dismissed();

				$this->render_home_preview();

				if ( ! $checklist_dismissed ) {
					$this->render_setup_checklist();
				}

				$this->render_site_status();

				if ( $checklist_dismissed ) {
					echo '<p class="lwseo-help"><a href="' . esc_url( $this->get_checklist_url( true ) ) . '">' . esc_html__( 'Show setup checklist', 'lightweight-seo' ) . '</a></p>';
				}
			} else {
				$this->render_settings_tab( $current_tab, $show_developer );
			}
			?>
		</div>
		<?php
	}

	/** @return array */
	private function get_admin_tabs() {
		return array(
			'overview'   => __( 'Overview', 'lightweight-seo' ),
			'appearance' => __( 'Search appearance', 'lightweight-seo' ),
			'indexation' => __( 'Content and indexation', 'lightweight-seo' ),
			'identity'   => __( 'Schema and identity', 'lightweight-seo' ),
			'modules'    => __( 'Modules', 'lightweight-seo' ),
			'tools'      => __( 'Tools', 'lightweight-seo' ),
		);
	}

	/**
	 * Build the URL of a settings tab.
	 *
	 * @param string $tab_id   Tab ID.
	 * @param string $fragment Optional. Element ID to jump to.
	 * @return string
	 */
	private function get_tab_url( $tab_id, $fragment = '' ) {
		return admin_url( 'admin.php?page=' . $this->plugin_name . '&tab=' . $tab_id ) . ( $fragment ? '#' . $fragment : '' );
	}

	/**
	 * Describe the cards on each settings tab.
	 *
	 * A card shows the fields of one Settings API section, or a subset of them.
	 * Supported keys: section, fields (omit for all), title (defaults to the
	 * section title), description, intro (print the section callback), module
	 * (collapsible card with a status badge), badge, and plain (hide the field
	 * label when the card title already says it).
	 *
	 * A module that is off shows only what turns it on. Its reports appear once it is in use.
	 *
	 * @param string $tab Tab ID.
	 * @return array Cards of that tab.
	 */
	private function get_tab_cards( $tab ) {
		$hreflang_fields  = array( 'enable_hreflang_output', 'hreflang_mappings' );
		$redirects_fields = array( 'enable_redirects', 'enable_auto_redirects', 'redirect_rules' );
		$ai_fields        = array( 'enable_ai_discovery', 'ai_search_crawlers_enabled', 'ai_training_crawlers_enabled', 'enable_llms_txt', 'llms_txt_post_ids' );

		if ( 'modules' === $tab ) {
			// Path mirroring is a retained 1.0 behavior, offered only to sites that still hold its data.
			if ( $this->settings->hreflang_path_mirroring_enabled() || $this->settings->has_legacy_hreflang_mirrors() ) {
				$hreflang_fields[] = 'enable_hreflang_path_mirroring';
			}

			if ( $this->is_module_enabled( 'redirects' ) ) {
				array_push( $redirects_fields, 'generated_redirect_rules', 'redirect_export', 'redirect_health' );
			}

			if ( $this->is_module_enabled( 'ai' ) ) {
				$ai_fields[] = 'ai_readiness';
			}
		}

		$cards = array(
			'appearance' => array(
				array(
					'section'     => 'lightweight_seo_general_section',
					'fields'      => array( 'title_separator', 'title_format', 'home_title_format', 'archive_title_format', 'search_title_format' ),
					'title'       => __( 'Title formats', 'lightweight-seo' ),
					'description' => __( 'How page titles are built across the site. Any page can override its own title.', 'lightweight-seo' ),
					'intro'       => true,
				),
				array(
					'section'     => 'lightweight_seo_general_section',
					'fields'      => array( 'meta_description', 'social_image' ),
					'title'       => __( 'Description and sharing', 'lightweight-seo' ),
					'description' => __( 'Defaults used when a page has no description or image of its own.', 'lightweight-seo' ),
				),
			),
			'indexation' => array(
				array(
					'section'     => 'lightweight_seo_indexation_section',
					'fields'      => array( 'content_types' ),
					'title'       => __( 'Content types', 'lightweight-seo' ),
					'description' => __( 'What search engines may show, what the sitemap lists, and how titles are built for each kind of content. Any page, term, or author can make its own choice.', 'lightweight-seo' ),
					'intro'       => true,
					'plain'       => true,
				),
				array(
					'section'     => 'lightweight_seo_indexation_section',
					'fields'      => array( 'default_max_image_preview', 'enable_media_x_robots_headers' ),
					'title'       => __( 'Previews and headers', 'lightweight-seo' ),
					'description' => __( 'How large an image preview may be, and how attachment pages repeat their instructions.', 'lightweight-seo' ),
				),
				array(
					'section'     => 'lightweight_seo_sitemap_section',
					'fields'      => array( 'exclude_noindex_from_sitemaps', 'exclude_redirected_from_sitemaps' ),
					'description' => __( 'Lightweight SEO extends the WordPress core sitemap instead of replacing it.', 'lightweight-seo' ),
					'intro'       => true,
				),
			),
			'identity'   => array(
				array(
					'section'     => 'lightweight_seo_schema_section',
					'fields'      => array( 'identity_type', 'identity_name', 'identity_logo', 'x_handle', 'organization_same_as' ),
					'title'       => __( 'Site identity', 'lightweight-seo' ),
					'description' => __( 'Who this site belongs to. Used in structured data and when pages are shared.', 'lightweight-seo' ),
				),
				array(
					'section'     => 'lightweight_seo_verification_section',
					'description' => __( 'Proves to each service that you own this site, with a meta tag on the homepage.', 'lightweight-seo' ),
				),
				array(
					'section'     => 'lightweight_seo_schema_section',
					'fields'      => array( 'enable_schema_output' ),
					'description' => __( 'JSON-LD schema built from your site identity and SEO context.', 'lightweight-seo' ),
				),
			),
			'modules'    => array(
				array(
					'section'     => 'lightweight_seo_schema_section',
					'fields'      => array( 'enable_local_business_schema', 'local_business_details' ),
					'title'       => __( 'Local business', 'lightweight-seo' ),
					'description' => __( 'LocalBusiness schema for a business with a physical location.', 'lightweight-seo' ),
					'module'      => 'local-seo',
				),
				array(
					'section'     => 'lightweight_seo_schema_section',
					'fields'      => $hreflang_fields,
					'title'       => __( 'Hreflang', 'lightweight-seo' ),
					'description' => __( 'Alternate-language links for translated or regional pages.', 'lightweight-seo' ),
					'module'      => 'hreflang',
				),
				array(
					'section'     => 'lightweight_seo_redirects_section',
					'fields'      => $redirects_fields,
					'description' => __( 'Exact redirect rules, plus automatic redirects when a published slug changes.', 'lightweight-seo' ),
					'intro'       => true,
					'module'      => 'redirects',
				),
				array(
					'section'     => 'lightweight_seo_tracking_section',
					'description' => __( 'Google Tag Manager, or direct GA4 and Meta Pixel IDs.', 'lightweight-seo' ),
					'intro'       => true,
					'module'      => 'tracking',
				),
				array(
					'section'     => 'lightweight_seo_ai_discovery_section',
					'fields'      => $ai_fields,
					'description' => __( 'Separate AI search access from model-training access, and publish a curated llms.txt.', 'lightweight-seo' ),
					'intro'       => true,
					'module'      => 'ai',
					'badge'       => __( 'Experimental', 'lightweight-seo' ),
				),
			),
			'tools'      => array(
				array(
					'section'     => 'lightweight_seo_inspect_section',
					'description' => __( 'See what Lightweight SEO sends for one address on this site, and where each value comes from.', 'lightweight-seo' ),
				),
				array(
					'section'     => 'lightweight_seo_migration_section',
					'fields'      => array( 'import_source', 'import_actions', 'import_report' ),
					'title'       => __( 'Import from another SEO plugin', 'lightweight-seo' ),
					'description' => __( 'Preview and import saved metadata from Yoast SEO, Rank Math, or All in One SEO, 50 posts at a time.', 'lightweight-seo' ),
					'intro'       => true,
				),
				'legacy' => array(
					'section'     => 'lightweight_seo_migration_section',
					'fields'      => array( 'legacy_data_transition' ),
					'title'       => __( 'Legacy data', 'lightweight-seo' ),
					'description' => __( 'Export or delete data kept from features that have been retired.', 'lightweight-seo' ),
					'plain'       => true,
				),
				array(
					'section'     => 'lightweight_seo_migration_section',
					'fields'      => array( 'delete_data_on_uninstall' ),
					'title'       => __( 'Uninstall', 'lightweight-seo' ),
					'description' => __( 'Choose what happens to your SEO data if the plugin is removed.', 'lightweight-seo' ),
					'plain'       => true,
				),
			),
		);

		// A site that never used the retired features has nothing to export or delete.
		if ( 'tools' === $tab && ! $this->tools_fields->has_legacy_data() ) {
			unset( $cards['tools']['legacy'] );
		}

		return $cards[ $tab ] ?? array();
	}

	/** @return array Module IDs and their display names. */
	private function get_module_labels() {
		return array(
			'local-seo' => __( 'Local business', 'lightweight-seo' ),
			'hreflang'  => __( 'Hreflang', 'lightweight-seo' ),
			'redirects' => __( 'Redirects', 'lightweight-seo' ),
			'tracking'  => __( 'Tracking codes', 'lightweight-seo' ),
			'ai'        => __( 'AI Discovery', 'lightweight-seo' ),
		);
	}

	/** Read bounded module state without loading a module implementation. */
	private function is_module_enabled( $module_id ) {
		$states = (array) get_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME, array() );

		return ! empty( $states[ $module_id ] );
	}

	/**
	 * Hide or show the setup checklist for the current user, then return to the overview.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function handle_setup_checklist() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change this screen.', 'lightweight-seo' ) );
		}

		check_admin_referer( self::CHECKLIST_ACTION );

		if ( empty( $_GET['show'] ) ) {
			update_user_meta( get_current_user_id(), self::CHECKLIST_META_KEY, '1' );
		} else {
			delete_user_meta( get_current_user_id(), self::CHECKLIST_META_KEY );
		}

		if ( $this->redirect( admin_url( 'admin.php?page=' . $this->plugin_name ) ) ) {
			exit;
		}
	}

	/**
	 * Send the redirect. Kept separate so the response can be observed without ending the process.
	 *
	 * @since    1.1.0
	 * @param    string    $url    Destination inside the administration area.
	 * @return   bool Whether the redirect was sent.
	 */
	protected function redirect( $url ) {
		return wp_safe_redirect( $url );
	}

	/** @return bool Whether the current user dismissed the setup checklist. */
	private function is_checklist_dismissed() {
		return '1' === (string) get_user_meta( get_current_user_id(), self::CHECKLIST_META_KEY, true );
	}

	/**
	 * Build the link that hides or shows the setup checklist.
	 *
	 * @param bool $show Whether the link brings the checklist back.
	 * @return string
	 */
	private function get_checklist_url( $show ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::CHECKLIST_ACTION . ( $show ? '&show=1' : '' ) ), self::CHECKLIST_ACTION );
	}

	/**
	 * Tell whether the homepage has a description of its own.
	 *
	 * A static front page can carry one in its SEO fields. Otherwise the homepage
	 * description on the Search appearance tab is used.
	 *
	 * @param array $options Plugin settings.
	 * @return bool
	 */
	private function home_has_description( $options ) {
		if ( '' !== trim( (string) ( $options['meta_description'] ?? '' ) ) ) {
			return true;
		}

		$front_page_id = $this->get_front_page_id();

		if ( ! $front_page_id ) {
			return false;
		}

		$post_meta = $this->post_meta->get_all( $front_page_id );

		return '' !== trim( (string) ( $post_meta['seo_description'] ?? '' ) );
	}

	/** @return int ID of the static front page, or 0 when the homepage lists posts. */
	private function get_front_page_id() {
		$front_page_id = 'page' === get_option( 'show_on_front' ) ? absint( get_option( 'page_on_front', 0 ) ) : 0;

		return $front_page_id && get_post( $front_page_id ) ? $front_page_id : 0;
	}

	/**
	 * Resolve what the homepage shows in search results and when shared.
	 *
	 * Follows the same order as the frontend: a static front page's own SEO fields
	 * first, then the homepage defaults from the Search appearance tab.
	 *
	 * @since    1.1.0
	 * @return   array Preview values: url, title, description, image, edit_url, and edit_label.
	 */
	private function get_home_preview() {
		$options     = $this->settings->get_all();
		$tokens      = array(
			'%title%'    => (string) get_bloginfo( 'name' ),
			'%sitename%' => (string) get_bloginfo( 'name' ),
			'%tagline%'  => (string) get_bloginfo( 'description' ),
			'%sep%'      => $this->settings->get_title_separator(),
			// The homepage preview is its first page; it has no category, and an author only when it is a page.
			'%page%'     => '',
			'%category%' => '',
			'%author%'   => '',
			'%year%'     => (string) wp_date( 'Y' ),
		);
		$title       = $this->settings->get_home_title_format();
		$description = (string) ( $options['meta_description'] ?? '' );
		$preview     = array(
			'url'        => home_url( '/' ),
			'image'      => (string) $this->settings->get_social_image_url(),
			'edit_url'   => $this->get_tab_url( 'appearance' ),
			'edit_label' => __( 'Edit homepage defaults', 'lightweight-seo' ),
		);
		$social      = array(
			'title'       => '',
			'description' => '',
		);

		$front_page_id = $this->get_front_page_id();

		if ( $front_page_id ) {
			$front_page         = get_post( $front_page_id );
			$post_meta          = $this->post_meta->get_all( $front_page_id );
			$tokens['%title%']  = (string) get_the_title( $front_page_id );
			$tokens['%author%'] = (string) get_the_author_meta( 'display_name', (int) ( $front_page->post_author ?? 0 ) );

			if ( ! empty( $post_meta['seo_title'] ) ) {
				$title = $post_meta['seo_title'];
			}

			if ( ! empty( $post_meta['seo_description'] ) ) {
				$description = $post_meta['seo_description'];
			} elseif ( '' === $description ) {
				$description = Lightweight_SEO_Text::summarize( Lightweight_SEO_Text::plain( ! empty( $front_page->post_excerpt ) ? $front_page->post_excerpt : ( $front_page->post_content ?? '' ) ) );
			}

			$own_image = (string) $this->post_meta->get_social_image_url( $front_page_id );

			if ( '' !== $own_image ) {
				$preview['image'] = $own_image;
			} elseif ( has_post_thumbnail( $front_page_id ) ) {
				$preview['image'] = (string) get_the_post_thumbnail_url( $front_page_id, 'full' );
			}

			$social                = array(
				'title'       => (string) ( $post_meta['social_title'] ?? '' ),
				'description' => (string) ( $post_meta['social_description'] ?? '' ),
			);
			$preview['url']        = (string) get_permalink( $front_page_id );
			$preview['edit_url']   = (string) get_edit_post_link( $front_page_id );
			$preview['edit_label'] = __( 'Edit front page', 'lightweight-seo' );
		}

		$preview['title']              = Lightweight_SEO_Text::plain( Lightweight_SEO_Text::resolve_tokens( $title, $tokens ) );
		$preview['description']        = Lightweight_SEO_Text::plain( Lightweight_SEO_Text::resolve_tokens( $description, $tokens ) );
		$preview['social_title']       = '' !== $social['title'] ? Lightweight_SEO_Text::plain( Lightweight_SEO_Text::resolve_tokens( $social['title'], $tokens ) ) : $preview['title'];
		$preview['social_description'] = '' !== $social['description'] ? Lightweight_SEO_Text::plain( Lightweight_SEO_Text::resolve_tokens( $social['description'], $tokens ) ) : $preview['description'];

		return $preview;
	}

	/** Render the homepage as it appears in search results and when shared. */
	private function render_home_preview() {
		$preview = $this->get_home_preview();
		?>
		<section class="lwseo-card" aria-labelledby="lightweight-seo-home-preview-title">
			<div class="lwseo-card__header">
				<div>
					<h2 class="lwseo-card__title" id="lightweight-seo-home-preview-title"><?php esc_html_e( 'Your homepage', 'lightweight-seo' ); ?></h2>
					<p class="lwseo-card__description"><?php esc_html_e( 'What your current settings send to search engines and social networks. Each service decides how it is finally shown.', 'lightweight-seo' ); ?></p>
				</div>
				<?php if ( '' !== $preview['edit_url'] ) : ?>
					<div class="lwseo-card__meta">
						<a href="<?php echo esc_url( $preview['edit_url'] ); ?>"><?php echo esc_html( $preview['edit_label'] ); ?></a>
					</div>
				<?php endif; ?>
			</div>
			<div class="lwseo-card__body lwseo-previews">
				<div>
					<p class="lwseo-preview__caption"><?php esc_html_e( 'Search result preview', 'lightweight-seo' ); ?></p>
					<?php
					Lightweight_SEO_Admin_UI::search_preview(
						array(
							'url'         => $preview['url'],
							'title'       => $preview['title'],
							'description' => $preview['description'],
							'placeholder' => __( 'No description is set, so search engines will pick text from the page.', 'lightweight-seo' ),
						)
					);
					?>
				</div>
				<div>
					<p class="lwseo-preview__caption"><?php esc_html_e( 'Share preview', 'lightweight-seo' ); ?></p>
					<?php
					Lightweight_SEO_Admin_UI::share_preview(
						array(
							'image'       => $preview['image'],
							'title'       => $preview['social_title'],
							'description' => $preview['social_description'],
						)
					);
					?>
				</div>
			</div>
		</section>
		<?php
	}

	/** Render the short, dismissible setup checklist. */
	private function render_setup_checklist() {
		$options       = $this->settings->get_all();
		$needs_tagline = false !== strpos( (string) ( $options['home_title_format'] ?? '%sitename% %sep% %tagline%' ), '%tagline%' );
		$has_identity  = '' !== trim( (string) get_bloginfo( 'name' ) ) && ( ! $needs_tagline || '' !== trim( (string) get_bloginfo( 'description' ) ) );
		$review        = __( 'Review', 'lightweight-seo' );
		// Each step reports something that is true of the site right now, so it turns green only when it is done.
		$tracked = array(
			array(
				$has_identity,
				__( 'Set a site name and tagline', 'lightweight-seo' ),
				$needs_tagline ? __( 'The homepage title format uses both.', 'lightweight-seo' ) : '',
				array(
					'label' => __( 'General settings', 'lightweight-seo' ),
					'url'   => admin_url( 'options-general.php' ),
				),
			),
			array(
				$this->home_has_description( $options ),
				__( 'Write a homepage description', 'lightweight-seo' ),
				'',
				array(
					'label' => $review,
					'url'   => $this->get_tab_url( 'appearance' ),
				),
			),
			array(
				! empty( $options['social_image'] ) || ! empty( $options['social_image_id'] ),
				__( 'Select a default social image', 'lightweight-seo' ),
				'',
				array(
					'label' => $review,
					'url'   => $this->get_tab_url( 'appearance' ),
				),
			),
		);
		// Only the site owner can judge these, so they are offered, never counted.
		$optional = array(
			array( __( 'Review what search engines may index', 'lightweight-seo' ), 'indexation' ),
			array( __( 'Review optional modules', 'lightweight-seo' ), 'modules' ),
		);
		$done     = count( array_filter( array_column( $tracked, 0 ) ) );
		$all_done = count( $tracked ) === $done;
		?>
		<section class="lwseo-card lwseo-setup" aria-labelledby="lightweight-seo-setup-title">
			<div class="lwseo-card__header">
				<div>
					<h2 class="lwseo-card__title" id="lightweight-seo-setup-title"><?php esc_html_e( 'Essential setup', 'lightweight-seo' ); ?></h2>
					<p class="lwseo-card__description"><?php esc_html_e( 'Finish the essentials in any order. This is a checklist, not a required wizard.', 'lightweight-seo' ); ?></p>
				</div>
				<div class="lwseo-card__meta">
					<?php
					Lightweight_SEO_Admin_UI::badge(
						/* translators: 1: number of completed setup steps, 2: total number of tracked setup steps. */
						sprintf( __( '%1$d of %2$d ready', 'lightweight-seo' ), $done, count( $tracked ) ),
						$all_done ? 'success' : ''
					);
					?>
					<a class="lwseo-setup__dismiss" href="<?php echo esc_url( $this->get_checklist_url( false ) ); ?>"><?php esc_html_e( 'Dismiss checklist', 'lightweight-seo' ); ?></a>
				</div>
			</div>
			<div class="lwseo-card__body">
				<ul class="lwseo-status-list">
					<?php
					if ( $all_done ) {
						Lightweight_SEO_Admin_UI::status( 'success', __( 'The essentials are set', 'lightweight-seo' ), __( 'Your site identity, homepage description, and default social image are in place.', 'lightweight-seo' ) );
					} else {
						foreach ( $tracked as $item ) {
							Lightweight_SEO_Admin_UI::status( $item[0] ? 'success' : 'pending', $item[1], $item[0] ? '' : $item[2], $item[3] );
						}
					}

					foreach ( $optional as $item ) {
						Lightweight_SEO_Admin_UI::status(
							'info',
							$item[0],
							__( 'Optional', 'lightweight-seo' ),
							array(
								'label' => $review,
								'url'   => $this->get_tab_url( $item[1] ),
							)
						);
					}
					?>
				</ul>
			</div>
		</section>
		<?php
	}

	/** Render what the plugin is doing right now, from values WordPress already has loaded. */
	private function render_site_status() {
		$is_public = '0' !== (string) get_option( 'blog_public', '1' );
		$conflicts = class_exists( 'Lightweight_SEO_Compatibility_Service' ) ? ( new Lightweight_SEO_Compatibility_Service() )->get_conflicting_plugins() : array();
		?>
		<section class="lwseo-card" aria-labelledby="lightweight-seo-status-title">
			<div class="lwseo-card__header">
				<div>
					<h2 class="lwseo-card__title" id="lightweight-seo-status-title"><?php esc_html_e( 'At a glance', 'lightweight-seo' ); ?></h2>
					<p class="lwseo-card__description"><?php esc_html_e( 'What Lightweight SEO is doing on this site right now.', 'lightweight-seo' ); ?></p>
				</div>
			</div>
			<div class="lwseo-card__body">
				<ul class="lwseo-status-list">
					<?php
					if ( $is_public ) {
						Lightweight_SEO_Admin_UI::status( 'success', __( 'Search visibility', 'lightweight-seo' ), __( 'Search engines are allowed to index this site.', 'lightweight-seo' ) );
					} else {
						Lightweight_SEO_Admin_UI::status(
							'warning',
							__( 'Search visibility', 'lightweight-seo' ),
							__( 'WordPress is set to discourage search engines from indexing this site.', 'lightweight-seo' ),
							array(
								'label' => __( 'Reading settings', 'lightweight-seo' ),
								'url'   => admin_url( 'options-reading.php' ),
							)
						);
					}

					if ( empty( $conflicts ) ) {
						Lightweight_SEO_Admin_UI::status( 'success', __( 'SEO output', 'lightweight-seo' ), __( 'Titles, descriptions, canonicals, robots directives, and schema are being written.', 'lightweight-seo' ) );
					} else {
						/* translators: %s: comma-separated list of conflicting SEO plugins. */
						Lightweight_SEO_Admin_UI::status( 'warning', __( 'SEO output', 'lightweight-seo' ), sprintf( __( 'Safe mode is on because %s is also active. Overlapping output is paused to avoid duplicates.', 'lightweight-seo' ), implode( ', ', $conflicts ) ) );
					}

					Lightweight_SEO_Admin_UI::status(
						'info',
						__( 'XML sitemap', 'lightweight-seo' ),
						home_url( '/wp-sitemap.xml' ),
						array(
							'label'   => __( 'Open', 'lightweight-seo' ),
							'url'     => home_url( '/wp-sitemap.xml' ),
							'new_tab' => true,
						)
					);
					?>
					<li class="lwseo-status is-info">
						<span class="lwseo-status__icon dashicons dashicons-admin-plugins" aria-hidden="true"></span>
						<div class="lwseo-status__body">
							<span class="lwseo-status__title"><?php esc_html_e( 'Modules', 'lightweight-seo' ); ?></span>
							<div class="lwseo-badges lwseo-status__text">
								<?php
								foreach ( $this->get_module_labels() as $module_id => $label ) {
									$enabled = $this->is_module_enabled( $module_id );

									Lightweight_SEO_Admin_UI::badge(
										/* translators: 1: module name, 2: module status, either Active or Off. */
										sprintf( __( '%1$s: %2$s', 'lightweight-seo' ), $label, $enabled ? __( 'Active', 'lightweight-seo' ) : __( 'Off', 'lightweight-seo' ) ),
										$enabled ? 'success' : '',
										$this->get_tab_url( 'modules', 'lwseo-module-' . $module_id )
									);
								}
								?>
							</div>
						</div>
					</li>
				</ul>
			</div>
		</section>
		<?php
	}

	/**
	 * Render the cards of one settings tab inside the settings form.
	 *
	 * @param string $tab            Tab ID.
	 * @param bool   $open_developer Whether the developer card on the Tools tab starts open.
	 */
	private function render_settings_tab( $tab, $open_developer = false ) {
		?>
		<form method="post" action="options.php" class="lwseo-form">
			<?php settings_fields( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>
			<input type="hidden" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[settings_tab]" value="<?php echo esc_attr( $tab ); ?>">
			<?php
			foreach ( $this->get_tab_cards( $tab ) as $card ) {
				$this->render_card( $card );
			}

			if ( 'tools' === $tab ) {
				$this->render_developer_information( $open_developer );
			}
			?>
			<div class="lwseo-actions">
				<?php submit_button( null, 'primary', 'submit', false ); ?>
				<span class="lwseo-actions__status" role="status" data-unsaved="<?php echo esc_attr( __( 'Unsaved changes', 'lightweight-seo' ) ); ?>"></span>
			</div>
		</form>
		<?php

		if ( 'tools' === $tab ) {
			// Tool actions post to their own handlers, and a form cannot nest inside the settings form.
			$this->tools_fields->render_action_forms();
		}
	}

	/**
	 * Render one card of Settings API fields.
	 *
	 * @param array $card Card definition from get_tab_cards().
	 */
	private function render_card( $card ) {
		global $wp_settings_fields, $wp_settings_sections;

		$section = $wp_settings_sections[ $this->plugin_name ][ $card['section'] ] ?? null;

		if ( empty( $section ) ) {
			return;
		}

		$fields = (array) ( $wp_settings_fields[ $this->plugin_name ][ $card['section'] ] ?? array() );

		if ( isset( $card['fields'] ) ) {
			$fields = array_intersect_key( $fields, array_flip( $card['fields'] ) );
		}

		$module   = $card['module'] ?? '';
		$enabled  = $module && $this->is_module_enabled( $module );
		$title_id = 'lwseo-card-' . sanitize_key( $module ? $module : $card['section'] . '-' . (string) key( $fields ) );

		// A module card collapses, and starts open only when the module is in use.
		if ( $module ) {
			echo '<details class="lwseo-card" id="' . esc_attr( 'lwseo-module-' . $module ) . '"' . ( $enabled ? ' open' : '' ) . '><summary class="lwseo-card__header">';
		} else {
			echo '<section class="lwseo-card" aria-labelledby="' . esc_attr( $title_id ) . '"><div class="lwseo-card__header">';
		}

		echo '<div><h2 class="lwseo-card__title" id="' . esc_attr( $title_id ) . '">' . esc_html( $card['title'] ?? $section['title'] ) . '</h2>';

		if ( ! empty( $card['description'] ) ) {
			echo '<p class="lwseo-card__description">' . esc_html( $card['description'] ) . '</p>';
		}

		echo '</div>';

		if ( $module || ! empty( $card['badge'] ) ) {
			echo '<div class="lwseo-card__meta">';

			if ( ! empty( $card['badge'] ) ) {
				Lightweight_SEO_Admin_UI::badge( $card['badge'], 'info' );
			}

			if ( $module ) {
				Lightweight_SEO_Admin_UI::badge( $enabled ? __( 'Active', 'lightweight-seo' ) : __( 'Off', 'lightweight-seo' ), $enabled ? 'success' : '' );
				echo '<span class="lwseo-card__chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
			}

			echo '</div>';
		}

		echo $module ? '</summary>' : '</div>';
		echo '<div class="lwseo-card__body">';

		if ( ! empty( $card['intro'] ) && ! empty( $section['callback'] ) ) {
			echo '<div class="lwseo-card__intro">';
			call_user_func( $section['callback'], $section );
			echo '</div>';
		}

		foreach ( $fields as $field_id => $field ) {
			$this->render_field( $field_id, $field, ! empty( $card['plain'] ) );
		}

		echo '</div>' . ( $module ? '</details>' : '</section>' );
	}

	/**
	 * Render one labelled field row.
	 *
	 * A field with a single control gets a real label. A field holding several
	 * controls is announced as a group named by its row title.
	 *
	 * @param string $field_id Field ID.
	 * @param array  $field    Settings API field.
	 * @param bool   $plain    Whether to hide the row title visually.
	 */
	private function render_field( $field_id, $field, $plain ) {
		$label_for   = $field['args']['label_for'] ?? '';
		$label_class = $plain ? 'screen-reader-text' : 'lwseo-field__label';
		$row_class   = 'lwseo-field' . ( $plain ? ' lwseo-field--stacked' : '' );

		if ( $label_for ) {
			echo '<div class="' . esc_attr( $row_class ) . '"><label class="' . esc_attr( $label_class ) . '" for="' . esc_attr( $label_for ) . '">' . esc_html( $field['title'] ) . '</label>';
		} else {
			$label_id = 'lwseo-label-' . $field_id;

			echo '<div class="' . esc_attr( $row_class ) . '" role="group" aria-labelledby="' . esc_attr( $label_id ) . '"><div class="' . esc_attr( $label_class ) . '" id="' . esc_attr( $label_id ) . '">' . esc_html( $field['title'] ) . '</div>';
		}

		echo '<div class="lwseo-field__control">';
		call_user_func( $field['callback'], $field['args'] ?? array() );
		echo '</div></div>';
	}

	/**
	 * Render the stable public extension contract summary as a card that starts collapsed.
	 *
	 * @param bool $open Whether the card starts open.
	 */
	private function render_developer_information( $open = false ) {
		$rows = array(
			__( 'Plugin version', 'lightweight-seo' ) => LIGHTWEIGHT_SEO_VERSION,
			__( 'API version', 'lightweight-seo' )    => LIGHTWEIGHT_SEO_API_VERSION,
			__( 'Accessor', 'lightweight-seo' )       => 'lightweight_seo_get_api()',
			__( 'Ready hook', 'lightweight-seo' )     => 'lightweight_seo_loaded',
		);
		?>
		<details class="lwseo-card" id="lwseo-developer-api"<?php echo $open ? ' open' : ''; ?>>
			<summary class="lwseo-card__header">
				<div>
					<h2 class="lwseo-card__title" id="lightweight-seo-developer-title"><?php esc_html_e( 'Developer API', 'lightweight-seo' ); ?></h2>
					<p class="lwseo-card__description"><?php esc_html_e( 'Extensions can read normalized SEO facts through the versioned facade without reading private settings or credentials.', 'lightweight-seo' ); ?></p>
				</div>
				<div class="lwseo-card__meta">
					<span class="lwseo-card__chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				</div>
			</summary>
			<div class="lwseo-card__body">
				<div class="lwseo-card__section lwseo-table-wrap">
				<table class="lwseo-table">
					<tbody>
						<?php foreach ( $rows as $label => $value ) : ?>
							<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><code><?php echo esc_html( $value ); ?></code></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			</div>
		</details>
		<?php
	}
}
