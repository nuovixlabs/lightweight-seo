<?php
/**
 * Settings fields for the Modules tab.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Renders the Local SEO, Hreflang, Redirects, Tracking, and AI Discovery fields.
 */
class Lightweight_SEO_Admin_Module_Fields {

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
	 * Render the LocalBusiness schema toggle field.
	 *
	 * @since    1.1.0
	 */
	public function enable_local_business_schema_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_local_business_schema]" value="1" <?php checked( $options['enable_local_business_schema'] ?? '0', '1' ); ?>>
			<?php esc_html_e( 'Output LocalBusiness schema on the homepage', 'lightweight-seo' ); ?>
		</label>
		<?php
	}

	/**
	 * Render LocalBusiness details fields.
	 *
	 * @since    1.1.0
	 */
	public function local_business_details_render() {
		$options = $this->settings->get_all();
		?>
		<p>
			<label><?php esc_html_e( 'Business Type', 'lightweight-seo' ); ?><br>
				<select name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_type]">
					<option value="LocalBusiness" <?php selected( $options['local_business_type'] ?? 'LocalBusiness', 'LocalBusiness' ); ?>><?php esc_html_e( 'LocalBusiness', 'lightweight-seo' ); ?></option>
					<option value="Restaurant" <?php selected( $options['local_business_type'] ?? '', 'Restaurant' ); ?>><?php esc_html_e( 'Restaurant', 'lightweight-seo' ); ?></option>
					<option value="Store" <?php selected( $options['local_business_type'] ?? '', 'Store' ); ?>><?php esc_html_e( 'Store', 'lightweight-seo' ); ?></option>
					<option value="MedicalBusiness" <?php selected( $options['local_business_type'] ?? '', 'MedicalBusiness' ); ?>><?php esc_html_e( 'MedicalBusiness', 'lightweight-seo' ); ?></option>
					<option value="ProfessionalService" <?php selected( $options['local_business_type'] ?? '', 'ProfessionalService' ); ?>><?php esc_html_e( 'ProfessionalService', 'lightweight-seo' ); ?></option>
				</select>
			</label>
		</p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_name]" value="<?php echo esc_attr( $options['local_business_name'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'Business name', 'lightweight-seo' ) ); ?>"></p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_phone]" value="<?php echo esc_attr( $options['local_business_phone'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'Phone number', 'lightweight-seo' ) ); ?>"></p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_price_range]" value="<?php echo esc_attr( $options['local_business_price_range'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'Price range, e.g. $$', 'lightweight-seo' ) ); ?>"></p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_address_street]" value="<?php echo esc_attr( $options['local_business_address_street'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'Street address', 'lightweight-seo' ) ); ?>"></p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_address_locality]" value="<?php echo esc_attr( $options['local_business_address_locality'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'City / locality', 'lightweight-seo' ) ); ?>"></p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_address_region]" value="<?php echo esc_attr( $options['local_business_address_region'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'Region / state', 'lightweight-seo' ) ); ?>"></p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_address_postal_code]" value="<?php echo esc_attr( $options['local_business_address_postal_code'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'Postal code', 'lightweight-seo' ) ); ?>"></p>
		<p><input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_address_country]" value="<?php echo esc_attr( $options['local_business_address_country'] ?? '' ); ?>" class="small-text" maxlength="2" placeholder="<?php echo esc_attr( __( 'Country code', 'lightweight-seo' ) ); ?>"></p>
		<p>
			<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_latitude]" value="<?php echo esc_attr( $options['local_business_latitude'] ?? '' ); ?>" class="small-text" placeholder="<?php echo esc_attr( __( 'Latitude', 'lightweight-seo' ) ); ?>">
			<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_longitude]" value="<?php echo esc_attr( $options['local_business_longitude'] ?? '' ); ?>" class="small-text" placeholder="<?php echo esc_attr( __( 'Longitude', 'lightweight-seo' ) ); ?>">
		</p>
		<p><input type="url" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_image]" value="<?php echo esc_attr( $options['local_business_image'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( __( 'Dedicated business image or logo URL', 'lightweight-seo' ) ); ?>"></p>
		<p class="description"><?php esc_html_e( 'Use a dedicated business image. The general social sharing image is not reused for LocalBusiness.', 'lightweight-seo' ); ?></p>
		<p>
			<textarea name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_opening_hours]" rows="4" cols="50" class="large-text"><?php echo esc_textarea( $options['local_business_opening_hours'] ?? '' ); ?></textarea>
		</p>
		<p class="description"><?php esc_html_e( 'Add one opening-hours rule per line, e.g. Mo-Fr 09:00-17:00.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the hreflang output field.
	 *
	 * @since    1.1.0
	 */
	public function enable_hreflang_output_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_hreflang_output]" value="1" <?php checked( $options['enable_hreflang_output'] ?? '0', '1' ); ?>>
			<?php esc_html_e( 'Output hreflang alternate links', 'lightweight-seo' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the hreflang mappings field.
	 *
	 * @since    1.1.0
	 */
	public function hreflang_mappings_render() {
		$options = $this->settings->get_all();
		?>
		<textarea name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[hreflang_mappings]" rows="5" cols="50" class="large-text code"><?php echo esc_textarea( $options['hreflang_mappings'] ?? '' ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Use one explicit mapping per line: post:123 en-GB https://example.co.uk/page/ (term: and user: are also supported). Language tags must use BCP 47 format; x-default is supported.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/** Render the opt-in retained legacy path-mirroring mode. */
	public function enable_hreflang_path_mirroring_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_hreflang_path_mirroring]" value="1" <?php checked( $options['enable_hreflang_path_mirroring'] ?? '0', '1' ); ?>>
			<?php esc_html_e( 'Reuse the current canonical path with retained two-column language/base-URL mappings', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Leave this off unless matching paths are intentionally guaranteed across the configured domains.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the redirects section information.
	 *
	 * @since    1.1.0
	 */
	public function redirects_section_callback() {
		echo '<p>' . esc_html__( 'Manage exact redirect rules and optionally preserve traffic after published slugs change. Continuous 404 logging is not part of this module.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render the automatic slug redirect toggle field.
	 *
	 * @since    1.1.0
	 */
	public function enable_auto_redirects_render() {
		$options = $this->settings->get_all();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_auto_redirects]" value="1" <?php checked( $options['enable_auto_redirects'] ?? '1', '1' ); ?>>
			<?php esc_html_e( 'Create 301 redirects automatically when a published post or page slug changes', 'lightweight-seo' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Generated redirects are stored separately from manual rules so manual overrides always win.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the manual redirect rules field.
	 *
	 * @since    1.1.0
	 */
	public function redirect_rules_render() {
		$options = $this->settings->get_all();
		?>
		<textarea name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[redirect_rules]" rows="8" cols="50" class="large-text code"><?php echo esc_textarea( $options['redirect_rules'] ?? '' ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Add one rule per line using the format: /old-path /new-path 301', 'lightweight-seo' ); ?></p>
		<p class="description"><?php esc_html_e( 'Targets can be local paths or full URLs. External hosts must be explicitly approved through WordPress’s allowed_redirect_hosts filter. Supported status codes: 301, 302, 307, 308.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render generated redirect rules.
	 *
	 * @since    1.1.0
	 */
	public function generated_redirect_rules_render() {
		if ( ! $this->is_module_enabled( 'redirects' ) ) {
			echo '<p class="description">' . esc_html__( 'The Redirects module is disabled.', 'lightweight-seo' ) . '</p>';

			return;
		}

		$rules = get_option( 'lightweight_seo_generated_redirect_rules', array() );

		if ( empty( $rules ) ) {
			echo '<p class="description">' . esc_html__( 'No automatic redirects have been generated yet.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-generated-redirects"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Source', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Target', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Updated', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $rules, 0, 10 ) as $rule ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $rule['source'] ?? '' ) . '</code></td>';
			echo '<td><code>' . esc_html( $rule['target'] ?? '' ) . '</code></td>';
			echo '<td>' . esc_html( $rule['updated_at'] ?? '' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render an exportable redirect rules snapshot.
	 *
	 * @since    1.1.0
	 */
	public function redirect_export_render() {
		if ( ! $this->is_module_enabled( 'redirects' ) ) {
			echo '<p class="description">' . esc_html__( 'Enable Redirects by saving a valid manual rule or automatic slug redirects.', 'lightweight-seo' ) . '</p>';

			return;
		}

		$this->load_redirects_service_for_read_only_admin();
		$redirects_service = new Lightweight_SEO_Redirects_Service( $this->settings, false );
		$rules             = $redirects_service->get_all_redirect_rules();

		if ( empty( $rules ) ) {
			echo '<p class="description">' . esc_html__( 'No redirect rules are available to export yet.', 'lightweight-seo' ) . '</p>';

			return;
		}

		$lines = array();
		echo '<label for="lightweight-seo-redirect-search" class="screen-reader-text">' . esc_html__( 'Search redirect rules', 'lightweight-seo' ) . '</label>';
		echo '<input type="search" id="lightweight-seo-redirect-search" class="regular-text" placeholder="' . esc_attr( __( 'Search redirect rules', 'lightweight-seo' ) ) . '">';
		echo '<table class="widefat striped lightweight-seo-redirect-table"><thead><tr><th>' . esc_html__( 'Source', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Target', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Status', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( $rules as $rule ) {
			$lines[] = implode(
				' ',
				array(
					$rule['source'] ?? '',
					$rule['target'] ?? '',
					$rule['status'] ?? 301,
				)
			);
			echo '<tr><td><code>' . esc_html( $rule['source'] ?? '' ) . '</code></td><td><code>' . esc_html( $rule['target'] ?? '' ) . '</code></td><td>' . esc_html( (string) ( $rule['status'] ?? 301 ) ) . '</td></tr>';
		}

		echo '</tbody></table>';
		echo '<textarea rows="8" cols="50" class="large-text code" readonly="readonly">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Copy this snapshot to migrate rules or keep an external backup. Manual rules can be imported by pasting them into the redirect rules field above.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render redirect chain and loop health warnings.
	 *
	 * @since    1.1.0
	 */
	public function redirect_health_render() {
		if ( ! $this->is_module_enabled( 'redirects' ) ) {
			echo '<p class="description">' . esc_html__( 'No redirect health checks run while the module is disabled.', 'lightweight-seo' ) . '</p>';

			return;
		}

		$this->load_redirects_service_for_read_only_admin();
		$redirects_service = new Lightweight_SEO_Redirects_Service( $this->settings, false );
		$report            = $redirects_service->get_redirect_health_report();
		$issues            = array_merge(
			array_map(
				function ( $item ) {
					$item['type'] = 'loop';

					return $item;
				},
				$report['loops']
			),
			array_map(
				function ( $item ) {
					$item['type'] = 'chain';

					return $item;
				},
				$report['chains']
			)
		);

		if ( empty( $issues ) ) {
			echo '<p class="description">' . esc_html__( 'No redirect chains or loops detected.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lightweight-seo-redirect-health"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Type', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Source', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Path', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $issues, 0, 10 ) as $issue ) {
			echo '<tr>';
			echo '<td>' . esc_html( ucfirst( $issue['type'] ) ) . '</td>';
			echo '<td><code>' . esc_html( $issue['source'] ?? '' ) . '</code></td>';
			echo '<td><code>' . esc_html( implode( ' -> ', $issue['sequence'] ?? array() ) ) . '</code></td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Load redirect parsing only when its legacy read-only report is rendered.
	 * No redirect hooks are registered by these views.
	 *
	 * @return void
	 */
	private function load_redirects_service_for_read_only_admin() {
		if ( ! class_exists( 'Lightweight_SEO_Redirects_Service', false ) ) {
			require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-redirects-service.php';
		}
	}

	/** Read bounded module state without loading a module implementation. */
	private function is_module_enabled( $module_id ) {
		$states = get_option( LIGHTWEIGHT_SEO_MODULES_OPTION_NAME, array() );

		return ! empty( $states[ sanitize_key( $module_id ) ] );
	}

	/**
	 * Render the tracking section information
	 *
	 * @since    1.0.1
	 */
	public function tracking_section_callback() {
		$options = $this->settings->get_all();

		echo '<p>' . esc_html__( 'Use Google Tag Manager as the primary strategy. Direct GA4 and Meta Pixel IDs are alternatives and do not output while GTM is configured.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . esc_html__( 'Tracking is excluded from the selected roles and environments. Consent plugins can use the lightweight_seo_tracking_consent_granted filter, and CSP integrations can supply a nonce through lightweight_seo_tracking_script_nonce.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . esc_html__( 'GTM themes must call wp_body_open(). If they do not, Lightweight SEO adds a diagnostic comment to the page source and omits only the noscript fallback.', 'lightweight-seo' ) . '</p>';

		if ( ! empty( $options['gtm_container_id'] ) && ( ! empty( $options['ga4_measurement_id'] ) || ! empty( $options['facebook_pixel_id'] ) ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'GTM is active, so the direct GA4 and Meta Pixel alternatives are stored but suppressed to avoid duplicate events.', 'lightweight-seo' ) . '</p></div>';
		}
	}

	/**
	 * Render the GA4 measurement ID field
	 *
	 * @since    1.0.1
	 */
	public function ga4_measurement_id_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[ga4_measurement_id]" value="<?php echo esc_attr( $options['ga4_measurement_id'] ?? '' ); ?>" class="regular-text">
		<p class="description"><?php esc_html_e( 'Direct alternative: enter a GA4 Measurement ID (e.g., G-XXXXXXXXXX). It is suppressed when GTM is configured.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the GTM container ID field
	 *
	 * @since    1.0.1
	 */
	public function gtm_container_id_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[gtm_container_id]" value="<?php echo esc_attr( $options['gtm_container_id'] ?? '' ); ?>" class="regular-text">
		<p class="description"><?php esc_html_e( 'Recommended: enter a Google Tag Manager Container ID (e.g., GTM-XXXXXX).', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the Facebook Pixel ID field
	 *
	 * @since    1.0.1
	 */
	public function facebook_pixel_id_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[facebook_pixel_id]" value="<?php echo esc_attr( $options['facebook_pixel_id'] ?? '' ); ?>" class="regular-text">
		<p class="description"><?php esc_html_e( 'Enter a numeric Meta Pixel ID. This direct alternative is suppressed when GTM is configured.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/** Render role slugs that suppress tracking for logged-in users. */
	public function tracking_excluded_roles_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[tracking_excluded_roles]" value="<?php echo esc_attr( $options['tracking_excluded_roles'] ?? 'administrator' ); ?>" class="regular-text">
		<p class="description"><?php esc_html_e( 'Comma-separated WordPress role slugs, for example administrator, editor. Leave empty to include all logged-in roles.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/** Render environments that suppress tracking. */
	public function tracking_excluded_environments_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[tracking_excluded_environments]" value="<?php echo esc_attr( str_replace( "\n", ', ', $options['tracking_excluded_environments'] ?? "local\ndevelopment\nstaging" ) ); ?>" class="regular-text">
		<p class="description"><?php esc_html_e( 'Choose from local, development, staging, and production. WordPress defaults to production when no environment type is set.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/** Explain the bounded, experimental AI Discovery scope. */
	public function ai_discovery_section_callback() {
		echo '<p><strong>' . esc_html__( 'Experimental:', 'lightweight-seo' ) . '</strong> ' . esc_html__( 'These controls separate AI search access from model-training access and can publish a manually curated llms.txt index.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . esc_html__( 'Google Search ignores llms.txt for visibility and rankings. No setting can guarantee crawling, citation, training, inclusion, or ranking in any AI product.', 'lightweight-seo' ) . '</p>';
	}

	/** Render the explicit AI module toggle. */
	public function enable_ai_discovery_render() {
		$options = $this->settings->get_all();
		?>
		<label><input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_ai_discovery]" value="1" <?php checked( $options['enable_ai_discovery'] ?? '0', '1' ); ?>> <?php esc_html_e( 'Enable the experimental AI Discovery module', 'lightweight-seo' ); ?></label>
		<?php
	}

	/** Render search and user-directed crawler policy. */
	public function ai_search_crawlers_enabled_render() {
		$options = $this->settings->get_all();
		?>
		<label><input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[ai_search_crawlers_enabled]" value="1" <?php checked( $options['ai_search_crawlers_enabled'] ?? '1', '1' ); ?>> <?php esc_html_e( 'Allow bundled AI search and user-directed crawler tokens', 'lightweight-seo' ); ?></label>
		<p class="description"><?php esc_html_e( 'This affects documented search and user-fetch tokens such as OAI-SearchBot, Claude-SearchBot, and PerplexityBot. It does not guarantee discovery or citation.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/** Render model-training crawler policy separately from search access. */
	public function ai_training_crawlers_enabled_render() {
		$options = $this->settings->get_all();
		?>
		<label><input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[ai_training_crawlers_enabled]" value="1" <?php checked( $options['ai_training_crawlers_enabled'] ?? '0', '1' ); ?>> <?php esc_html_e( 'Allow bundled model-training crawler tokens', 'lightweight-seo' ); ?></label>
		<p class="description"><?php esc_html_e( 'Off blocks GPTBot, ClaudeBot, and Google-Extended. Google-Extended also controls some Gemini grounding uses, but does not affect Google Search inclusion or ranking.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/** Render the optional curated llms.txt endpoint toggle. */
	public function enable_llms_txt_render() {
		$options = $this->settings->get_all();
		?>
		<label><input type="checkbox" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[enable_llms_txt]" value="1" <?php checked( $options['enable_llms_txt'] ?? '0', '1' ); ?>> <?php esc_html_e( 'Publish an experimental /llms.txt Markdown index', 'lightweight-seo' ); ?></label>
		<p class="description"><?php esc_html_e( 'Only selected public page summaries are listed. No llms-full.txt or full-page Markdown copies are generated.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/** Render the bounded list of manually selected post and page IDs. */
	public function llms_txt_post_ids_render() {
		$options = $this->settings->get_all();
		?>
		<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[llms_txt_post_ids]" value="<?php echo esc_attr( $options['llms_txt_post_ids'] ?? '' ); ?>" class="regular-text">
		<?php /* translators: %d: Maximum number of curated post or page IDs. */ ?>
		<p class="description"><?php printf( esc_html__( 'Enter up to %d comma-separated published post or page IDs. Drafts, private/password-protected, noindexed, redirected, external-canonical, or invalid URLs are excluded.', 'lightweight-seo' ), (int) Lightweight_SEO_Settings::MAX_LLMS_POSTS ); ?></p>
		<?php
	}

	/** Render deterministic, local-only readiness checks. */
	public function ai_readiness_render() {
		if ( ! $this->is_module_enabled( 'ai' ) || ! class_exists( 'Lightweight_SEO_AI_Discovery_Module', false ) ) {
			echo '<p>' . esc_html__( 'Enable and save the module to run readiness checks.', 'lightweight-seo' ) . '</p>';
			return;
		}

		$module = new Lightweight_SEO_AI_Discovery_Module( $this->settings, lightweight_seo_get_api(), 'admin' );

		echo '<ul class="lightweight-seo-checklist">';
		foreach ( $module->get_readiness_checks() as $check ) {
			echo '<li><strong>' . esc_html( strtoupper( $check['status'] ) ) . ' — ' . esc_html( $check['label'] ) . ':</strong> ' . esc_html( $check['details'] ) . '</li>';
		}
		echo '</ul>';
	}
}
