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
	 * Initialize the page.
	 *
	 * @since    1.1.0
	 * @param    string                                $plugin_name     The name of this plugin.
	 * @param    Lightweight_SEO_Settings              $settings        Shared settings service.
	 * @param    Lightweight_SEO_Admin_Tools_Fields    $tools_fields    Tools tab renderer.
	 */
	public function __construct( $plugin_name, $settings, $tools_fields ) {
		$this->plugin_name  = $plugin_name;
		$this->settings     = $settings;
		$this->tools_fields = $tools_fields;
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		$tabs        = $this->get_admin_tabs();
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection checked against an allowlist.

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
				$this->render_setup_checklist();
				$this->render_site_status();
			} elseif ( 'developer' === $current_tab ) {
				$this->render_developer_information();
			} else {
				$this->render_settings_tab( $current_tab );
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
			'tools'      => __( 'Tools and migration', 'lightweight-seo' ),
			'developer'  => __( 'Developer API', 'lightweight-seo' ),
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
	 * @return array
	 */
	private function get_tab_cards() {
		return array(
			'appearance' => array(
				array(
					'section'     => 'lightweight_seo_general_section',
					'fields'      => array( 'title_format', 'home_title_format', 'archive_title_format', 'search_title_format' ),
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
					'description' => __( 'Keep low-value pages out of search results and choose how previews appear.', 'lightweight-seo' ),
					'intro'       => true,
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
					'fields'      => array( 'enable_schema_output', 'organization_same_as' ),
					'description' => __( 'JSON-LD schema built from your site identity and SEO context.', 'lightweight-seo' ),
					'intro'       => true,
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
					'fields'      => array( 'enable_hreflang_output', 'hreflang_mappings', 'enable_hreflang_path_mirroring' ),
					'title'       => __( 'Hreflang', 'lightweight-seo' ),
					'description' => __( 'Alternate-language links for translated or regional pages.', 'lightweight-seo' ),
					'module'      => 'hreflang',
				),
				array(
					'section'     => 'lightweight_seo_redirects_section',
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
					'description' => __( 'Separate AI search access from model-training access, and publish a curated llms.txt.', 'lightweight-seo' ),
					'intro'       => true,
					'module'      => 'ai',
					'badge'       => __( 'Experimental', 'lightweight-seo' ),
				),
			),
			'tools'      => array(
				array(
					'section'     => 'lightweight_seo_migration_section',
					'fields'      => array( 'import_source', 'import_actions', 'import_report' ),
					'title'       => __( 'Import from another SEO plugin', 'lightweight-seo' ),
					'description' => __( 'Preview and import saved metadata from Yoast SEO, Rank Math, or All in One SEO, 50 posts at a time.', 'lightweight-seo' ),
					'intro'       => true,
				),
				array(
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

	/** Render the short, dismissible setup checklist. */
	private function render_setup_checklist() {
		$options = $this->settings->get_all();
		// A null state marks a step only the site owner can judge, so it never counts against progress.
		$items   = array(
			array( ! empty( get_bloginfo( 'name' ) ), __( 'Confirm site identity', 'lightweight-seo' ), 'identity' ),
			array( ! empty( $options['title_format'] ) && ! empty( $options['meta_description'] ), __( 'Choose title and description defaults', 'lightweight-seo' ), 'appearance' ),
			array( isset( $options['noindex_search_results'] ), __( 'Review indexation defaults', 'lightweight-seo' ), 'indexation' ),
			array( ! empty( $options['social_image'] ) || ! empty( $options['social_image_id'] ), __( 'Select a default social image', 'lightweight-seo' ), 'appearance' ),
			array( null, __( 'Review optional modules', 'lightweight-seo' ), 'modules' ),
			array( null, __( 'Verify generated output on one published page', 'lightweight-seo' ), '' ),
		);
		$tracked = array_filter(
			array_column( $items, 0 ),
			static function ( $state ) {
				return null !== $state;
			}
		);
		$done    = count( array_filter( $tracked ) );
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
						count( $tracked ) === $done ? 'success' : ''
					);
					?>
					<button type="button" class="button-link lwseo-setup__dismiss"><?php esc_html_e( 'Dismiss checklist', 'lightweight-seo' ); ?></button>
				</div>
			</div>
			<div class="lwseo-card__body">
				<ul class="lwseo-status-list">
					<?php
					foreach ( $items as $item ) {
						$action = $item[2]
							? array(
								'label' => __( 'Review', 'lightweight-seo' ),
								'url'   => $this->get_tab_url( $item[2] ),
							)
							: array(
								'label'   => __( 'Open site', 'lightweight-seo' ),
								'url'     => home_url( '/' ),
								'new_tab' => true,
							);

						Lightweight_SEO_Admin_UI::status( $item[0] ? 'success' : 'pending', $item[1], '', $action );
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

	/** Render the cards of one settings tab inside the settings form. */
	private function render_settings_tab( $tab ) {
		$cards = $this->get_tab_cards();
		?>
		<form method="post" action="options.php" class="lwseo-form">
			<?php settings_fields( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>
			<input type="hidden" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[settings_tab]" value="<?php echo esc_attr( $tab ); ?>">
			<?php
			foreach ( $cards[ $tab ] ?? array() as $card ) {
				$this->render_card( $card );
			}
			?>
			<div class="lwseo-actions"><?php submit_button( null, 'primary', 'submit', false ); ?></div>
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

	/** Render the stable public extension contract summary. */
	private function render_developer_information() {
		$rows = array(
			__( 'Plugin version', 'lightweight-seo' ) => LIGHTWEIGHT_SEO_VERSION,
			__( 'API version', 'lightweight-seo' )    => LIGHTWEIGHT_SEO_API_VERSION,
			__( 'Accessor', 'lightweight-seo' )       => 'lightweight_seo_get_api()',
			__( 'Ready hook', 'lightweight-seo' )     => 'lightweight_seo_loaded',
		);
		?>
		<section class="lwseo-card" aria-labelledby="lightweight-seo-developer-title">
			<div class="lwseo-card__header">
				<div>
					<h2 class="lwseo-card__title" id="lightweight-seo-developer-title"><?php esc_html_e( 'Developer API', 'lightweight-seo' ); ?></h2>
					<p class="lwseo-card__description"><?php esc_html_e( 'Extensions can read normalized SEO facts through the versioned facade without reading private settings or credentials.', 'lightweight-seo' ); ?></p>
				</div>
			</div>
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
		</section>
		<?php
	}
}
