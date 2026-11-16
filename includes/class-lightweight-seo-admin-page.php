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
		?>
		<div class="wrap lightweight-seo-admin">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>
			<nav class="nav-tab-wrapper lightweight-seo-admin-nav" aria-label="<?php echo esc_attr( __( 'Lightweight SEO settings', 'lightweight-seo' ) ); ?>">
				<?php foreach ( $tabs as $tab_id => $label ) : ?>
					<a class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>" <?php echo $current_tab === $tab_id ? 'aria-current="page"' : ''; ?> href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->plugin_name . '&tab=' . $tab_id ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			if ( 'overview' === $current_tab ) {
				$this->render_setup_overview();
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

	/** Render the short, dismissible setup checklist. */
	private function render_setup_overview() {
		$options = $this->settings->get_all();
		$items   = array(
			array( ! empty( get_bloginfo( 'name' ) ), __( 'Confirm site identity', 'lightweight-seo' ), 'identity' ),
			array( ! empty( $options['title_format'] ) && ! empty( $options['meta_description'] ), __( 'Choose title and description defaults', 'lightweight-seo' ), 'appearance' ),
			array( isset( $options['noindex_search_results'] ), __( 'Review indexation defaults', 'lightweight-seo' ), 'indexation' ),
			array( ! empty( $options['social_image'] ) || ! empty( $options['social_image_id'] ), __( 'Select a default social image', 'lightweight-seo' ), 'appearance' ),
			array( false, __( 'Review optional modules', 'lightweight-seo' ), 'modules' ),
			array( false, __( 'Verify generated output on one published page', 'lightweight-seo' ), '' ),
		);
		?>
		<section class="lightweight-seo-setup" aria-labelledby="lightweight-seo-setup-title">
			<div class="lightweight-seo-section-heading"><div><h2 id="lightweight-seo-setup-title"><?php esc_html_e( 'Essential setup', 'lightweight-seo' ); ?></h2><p><?php esc_html_e( 'Finish the essentials in any order. This is a checklist, not a required wizard.', 'lightweight-seo' ); ?></p></div><button type="button" class="button-link lightweight-seo-dismiss-checklist"><?php esc_html_e( 'Dismiss checklist', 'lightweight-seo' ); ?></button></div>
			<ul class="lightweight-seo-setup-list">
				<?php foreach ( $items as $item ) : ?>
					<li class="<?php echo $item[0] ? 'is-complete' : 'is-pending'; ?>">
						<span class="dashicons <?php echo esc_attr( $item[0] ? 'dashicons-yes-alt' : 'dashicons-marker' ); ?>" aria-hidden="true"></span>
						<span><?php echo esc_html( $item[1] ); ?></span>
						<?php if ( $item[2] ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->plugin_name . '&tab=' . $item[2] ) ); ?>"><?php esc_html_e( 'Review', 'lightweight-seo' ); ?></a>
						<?php elseif ( ! $item[0] ) : ?>
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open site', 'lightweight-seo' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'lightweight-seo' ); ?></span></a>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}

	/** Render a selected subset of the existing Settings API fields. */
	private function render_settings_tab( $tab ) {
		$maps = array(
			'appearance' => array( 'lightweight_seo_general_section' => array( 'title_format', 'home_title_format', 'archive_title_format', 'search_title_format', 'meta_description', 'social_image' ) ),
			'indexation' => array(
				'lightweight_seo_indexation_section' => true,
				'lightweight_seo_sitemap_section'    => array( 'exclude_noindex_from_sitemaps', 'exclude_redirected_from_sitemaps' ),
			),
			'identity'   => array( 'lightweight_seo_schema_section' => array( 'enable_schema_output', 'organization_same_as' ) ),
			'modules'    => array(
				'lightweight_seo_schema_section'       => array( 'enable_local_business_schema', 'local_business_details', 'enable_hreflang_output', 'hreflang_mappings', 'enable_hreflang_path_mirroring' ),
				'lightweight_seo_redirects_section'    => true,
				'lightweight_seo_tracking_section'     => true,
				'lightweight_seo_ai_discovery_section' => true,
			),
			'tools'      => array( 'lightweight_seo_migration_section' => true ),
		);
		?>
		<form method="post" action="options.php" class="lightweight-seo-settings-form">
			<?php settings_fields( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>
			<input type="hidden" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[settings_tab]" value="<?php echo esc_attr( $tab ); ?>">
			<?php $this->render_selected_settings_sections( $maps[ $tab ] ?? array() ); ?>
			<?php submit_button(); ?>
		</form>
		<?php

		if ( 'tools' === $tab ) {
			// Tool actions post to their own handlers, and a form cannot nest inside the settings form.
			$this->tools_fields->render_action_forms();
		}
	}

	/**
	 * Render Settings API sections without exposing unrelated screens.
	 *
	 * @param array $section_map Section IDs and allowed fields.
	 */
	private function render_selected_settings_sections( $section_map ) {
		global $wp_settings_fields, $wp_settings_sections;

		foreach ( $section_map as $section_id => $allowed_fields ) {
			$section = $wp_settings_sections[ $this->plugin_name ][ $section_id ] ?? null;

			if ( empty( $section ) ) {
				continue;
			}

			echo '<section class="lightweight-seo-settings-section">';
			echo '<h2>' . esc_html( $section['title'] ) . '</h2>';

			if ( ! empty( $section['callback'] ) ) {
				call_user_func( $section['callback'], $section );
			}

			echo '<table class="form-table" role="presentation">';

			foreach ( (array) ( $wp_settings_fields[ $this->plugin_name ][ $section_id ] ?? array() ) as $field_id => $field ) {
				if ( true !== $allowed_fields && ! in_array( $field_id, $allowed_fields, true ) ) {
					continue;
				}

				echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
				call_user_func( $field['callback'], $field['args'] ?? array() );
				echo '</td></tr>';
			}

			echo '</table></section>';
		}
	}

	/** Render the stable public extension contract summary. */
	private function render_developer_information() {
		?>
		<section class="lightweight-seo-settings-section">
			<h2><?php esc_html_e( 'Developer API', 'lightweight-seo' ); ?></h2>
			<p><?php esc_html_e( 'Extensions can read normalized SEO facts through the versioned facade without reading private settings or credentials.', 'lightweight-seo' ); ?></p>
			<table class="widefat striped"><tbody><tr><th><?php esc_html_e( 'Plugin version', 'lightweight-seo' ); ?></th><td><code><?php echo esc_html( LIGHTWEIGHT_SEO_VERSION ); ?></code></td></tr><tr><th><?php esc_html_e( 'API version', 'lightweight-seo' ); ?></th><td><code><?php echo esc_html( LIGHTWEIGHT_SEO_API_VERSION ); ?></code></td></tr><tr><th><?php esc_html_e( 'Accessor', 'lightweight-seo' ); ?></th><td><code>lightweight_seo_get_api()</code></td></tr><tr><th><?php esc_html_e( 'Ready hook', 'lightweight-seo' ); ?></th><td><code>lightweight_seo_loaded</code></td></tr></tbody></table>
		</section>
		<?php
	}
}
