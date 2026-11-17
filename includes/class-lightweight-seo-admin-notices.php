<?php
/**
 * Administration notices for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Renders the safe-mode notice and the one-time upgrade summary.
 */
class Lightweight_SEO_Admin_Notices {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * Initialize the notices.
	 *
	 * @since    1.1.0
	 * @param    string    $plugin_name    The name of this plugin.
	 */
	public function __construct( $plugin_name ) {
		$this->plugin_name = $plugin_name;
	}

	/**
	 * Render a safe-mode notice when another SEO plugin is active.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function maybe_render_safe_mode_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Limit the reminder to screens where an administrator can act on it.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && ! in_array( (string) $screen->id, array( 'dashboard', 'plugins', 'toplevel_page_' . $this->plugin_name ), true ) ) {
			return;
		}

		$compatibility_service = new Lightweight_SEO_Compatibility_Service();
		$conflicting_plugins   = $compatibility_service->get_conflicting_plugins();

		if ( empty( $conflicting_plugins ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html(
			sprintf(
				/* translators: %s: comma-separated list of conflicting SEO plugins */
				__( 'Lightweight SEO safe mode is active because %s is also running. Lightweight SEO title, meta, robots, canonical, and schema output is disabled to avoid duplicate SEO markup. Core sitemaps and non-overlapping modules remain available.', 'lightweight-seo' ),
				implode( ', ', $conflicting_plugins )
			)
		);
		echo '</p></div>';
	}

	/** Render the one-time summary created by the 1.0.3 cleanup migration. */
	public function maybe_render_upgrade_summary() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$summary = (array) get_option( 'lightweight_seo_upgrade_summary', array() );

		if ( empty( $summary ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=lightweight_seo_dismiss_upgrade_summary' ),
			'lightweight_seo_dismiss_upgrade_summary'
		);

		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Lightweight SEO upgrade summary', 'lightweight-seo' ) . '</strong></p><ul>';
		echo '<li>' . esc_html__( 'Search Console synchronization, site-wide link/image reports, continuous 404 logging, meta-keywords output, and specialized sitemap providers are no longer active in core.', 'lightweight-seo' ) . '</li>';

		if ( ! empty( $summary['removed_sitemaps'] ) ) {
			echo '<li>' . esc_html__( 'Previously enabled image, video, or news sitemap endpoints were retired. Remove those submitted URLs from external webmaster tools.', 'lightweight-seo' ) . '</li>';
		}

		if ( ! empty( $summary['has_search_console_private'] ) || ! empty( $summary['legacy_404_entries'] ) || ! empty( $summary['has_meta_keywords'] ) ) {
			echo '<li>' . esc_html__( 'Legacy credentials, 404 entries, or keyword values need an explicit export or deletion decision on the Tools tab.', 'lightweight-seo' ) . '</li>';
		}

		echo '</ul><p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . $this->plugin_name . '&tab=tools' ) ) . '">' . esc_html__( 'Review migration data', 'lightweight-seo' ) . '</a> <a href="' . esc_url( $dismiss_url ) . '">' . esc_html__( 'Dismiss summary', 'lightweight-seo' ) . '</a></p></div>';
	}

	/** Dismiss the one-time upgrade summary. */
	public function dismiss_upgrade_summary() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to dismiss this notice.', 'lightweight-seo' ) );
		}

		check_admin_referer( 'lightweight_seo_dismiss_upgrade_summary' );
		delete_option( 'lightweight_seo_upgrade_summary' );
		wp_safe_redirect( admin_url( 'admin.php?page=' . $this->plugin_name ) );
		exit;
	}
}
