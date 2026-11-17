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
	 * Print a checkbox setting.
	 *
	 * @since    1.2.0
	 * @param    string    $key        Settings key.
	 * @param    string    $fallback   Stored value used when nothing is saved.
	 * @param    string    $label      Checkbox label.
	 * @param    string    $help       Optional. Help text.
	 * @return   void
	 */
	private function render_choice( $key, $fallback, $label, $help = '' ) {
		$options = $this->settings->get_all();
		$checked = '1' === (string) ( $options[ $key ] ?? $fallback );

		Lightweight_SEO_Admin_UI::choice( LIGHTWEIGHT_SEO_OPTION_NAME . '[' . $key . ']', $checked, $label, $help );
	}

	/**
	 * Print a single-line text setting with help text.
	 *
	 * @since    1.2.0
	 * @param    string    $key      Settings key.
	 * @param    string    $value    Current value.
	 * @param    string    $help     Help text.
	 * @return   void
	 */
	private function render_text( $key, $value, $help ) {
		?>
		<input type="text" id="<?php echo esc_attr( 'lightweight_seo_' . $key ); ?>" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<p class="lwseo-help"><?php echo esc_html( $help ); ?></p>
		<?php
	}

	/**
	 * Render the LocalBusiness schema toggle field.
	 *
	 * @since    1.1.0
	 */
	public function enable_local_business_schema_render() {
		$this->render_choice( 'enable_local_business_schema', '0', __( 'Output LocalBusiness schema on the homepage', 'lightweight-seo' ) );
	}

	/**
	 * Render LocalBusiness details fields.
	 *
	 * @since    1.1.0
	 */
	public function local_business_details_render() {
		$options = $this->settings->get_all();
		$types   = array( 'LocalBusiness', 'Restaurant', 'Store', 'MedicalBusiness', 'ProfessionalService' );
		// Each entry: settings key suffix, label, placeholder, and whether it spans the full width.
		$inputs = array(
			array( 'name', __( 'Business name', 'lightweight-seo' ), '', false ),
			array( 'phone', __( 'Phone number', 'lightweight-seo' ), '', false ),
			array( 'price_range', __( 'Price range', 'lightweight-seo' ), '$$', false ),
			array( 'address_street', __( 'Street address', 'lightweight-seo' ), '', true ),
			array( 'address_locality', __( 'City or locality', 'lightweight-seo' ), '', false ),
			array( 'address_region', __( 'Region or state', 'lightweight-seo' ), '', false ),
			array( 'address_postal_code', __( 'Postal code', 'lightweight-seo' ), '', false ),
			array( 'address_country', __( 'Country code', 'lightweight-seo' ), 'GB', false ),
			array( 'latitude', __( 'Latitude', 'lightweight-seo' ), '', false ),
			array( 'longitude', __( 'Longitude', 'lightweight-seo' ), '', false ),
		);
		?>
		<div class="lwseo-subfields">
			<label class="lwseo-subfield">
				<span class="lwseo-subfield__label"><?php esc_html_e( 'Business type', 'lightweight-seo' ); ?></span>
				<select name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_type]">
					<?php foreach ( $types as $type ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $options['local_business_type'] ?? 'LocalBusiness', $type ); ?>><?php echo esc_html( $type ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php foreach ( $inputs as $input ) : ?>
				<label class="lwseo-subfield<?php echo $input[3] ? ' lwseo-subfield--wide' : ''; ?>">
					<span class="lwseo-subfield__label"><?php echo esc_html( $input[1] ); ?></span>
					<input type="text" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME . '[local_business_' . $input[0] . ']' ); ?>" value="<?php echo esc_attr( $options[ 'local_business_' . $input[0] ] ?? '' ); ?>"<?php echo 'address_country' === $input[0] ? ' maxlength="2"' : ''; ?> placeholder="<?php echo esc_attr( $input[2] ); ?>">
				</label>
			<?php endforeach; ?>
			<label class="lwseo-subfield lwseo-subfield--wide">
				<span class="lwseo-subfield__label"><?php esc_html_e( 'Business image or logo URL', 'lightweight-seo' ); ?></span>
				<input type="url" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_image]" value="<?php echo esc_attr( $options['local_business_image'] ?? '' ); ?>">
			</label>
			<p class="lwseo-help lwseo-subfield--wide"><?php esc_html_e( 'Use a dedicated business image. The general social sharing image is not reused for LocalBusiness.', 'lightweight-seo' ); ?></p>
			<label class="lwseo-subfield lwseo-subfield--wide">
				<span class="lwseo-subfield__label"><?php esc_html_e( 'Opening hours', 'lightweight-seo' ); ?></span>
				<textarea name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_opening_hours]" rows="4" class="code"><?php echo esc_textarea( $options['local_business_opening_hours'] ?? '' ); ?></textarea>
			</label>
			<p class="lwseo-help lwseo-subfield--wide"><?php esc_html_e( 'Add one opening-hours rule per line, e.g. Mo-Fr 09:00-17:00.', 'lightweight-seo' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the hreflang output field.
	 *
	 * @since    1.1.0
	 */
	public function enable_hreflang_output_render() {
		$this->render_choice( 'enable_hreflang_output', '0', __( 'Output hreflang alternate links', 'lightweight-seo' ) );
	}

	/**
	 * Render the hreflang mappings field.
	 *
	 * @since    1.1.0
	 */
	public function hreflang_mappings_render() {
		$options = $this->settings->get_all();
		?>
		<textarea id="lightweight_seo_hreflang_mappings" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[hreflang_mappings]" rows="5" class="large-text code"><?php echo esc_textarea( $options['hreflang_mappings'] ?? '' ); ?></textarea>
		<p class="lwseo-help"><?php esc_html_e( 'Use one explicit mapping per line: post:123 en-GB https://example.co.uk/page/ (term: and user: are also supported). Language tags must use BCP 47 format; x-default is supported.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/** Render the opt-in retained legacy path-mirroring mode. */
	public function enable_hreflang_path_mirroring_render() {
		$this->render_choice( 'enable_hreflang_path_mirroring', '0', __( 'Reuse the current canonical path with retained two-column language/base-URL mappings', 'lightweight-seo' ), __( 'Leave this off unless matching paths are intentionally guaranteed across the configured domains.', 'lightweight-seo' ) );
	}

	/**
	 * Render the redirects section information.
	 *
	 * @since    1.1.0
	 */
	public function redirects_section_callback() {
		echo '<p class="lwseo-help">' . esc_html__( 'Continuous 404 logging is not part of this module.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render the automatic slug redirect toggle field.
	 *
	 * @since    1.1.0
	 */
	public function enable_auto_redirects_render() {
		$this->render_choice( 'enable_auto_redirects', '1', __( 'Create 301 redirects automatically when a published post or page slug changes', 'lightweight-seo' ), __( 'Generated redirects are stored separately from manual rules so manual overrides always win.', 'lightweight-seo' ) );
	}

	/**
	 * Render the manual redirect rules field.
	 *
	 * @since    1.1.0
	 */
	public function redirect_rules_render() {
		$options = $this->settings->get_all();
		?>
		<textarea id="lightweight_seo_redirect_rules" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[redirect_rules]" rows="8" class="large-text code" placeholder="/old-path /new-path 301"><?php echo esc_textarea( $options['redirect_rules'] ?? '' ); ?></textarea>
		<p class="lwseo-help"><?php esc_html_e( 'Add one rule per line using the format: /old-path /new-path 301', 'lightweight-seo' ); ?></p>
		<p class="lwseo-help"><?php esc_html_e( 'Targets can be local paths or full URLs. External hosts must be explicitly approved through WordPress’s allowed_redirect_hosts filter. Supported status codes: 301, 302, 307, 308.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render generated redirect rules.
	 *
	 * @since    1.1.0
	 */
	public function generated_redirect_rules_render() {
		if ( ! $this->is_module_enabled( 'redirects' ) ) {
			echo '<p class="lwseo-help">' . esc_html__( 'The Redirects module is disabled.', 'lightweight-seo' ) . '</p>';

			return;
		}

		$rules = get_option( 'lightweight_seo_generated_redirect_rules', array() );

		if ( empty( $rules ) ) {
			echo '<p class="lwseo-help">' . esc_html__( 'No automatic redirects have been generated yet.', 'lightweight-seo' ) . '</p>';

			return;
		}

		echo '<div class="lwseo-table-wrap"><table class="lwseo-table"><thead><tr><th>' . esc_html__( 'Source', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Target', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Updated', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

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
			echo '<p class="lwseo-help">' . esc_html__( 'Enable Redirects by saving a valid manual rule or automatic slug redirects.', 'lightweight-seo' ) . '</p>';

			return;
		}

		$this->load_redirects_service_for_read_only_admin();
		$redirects_service = new Lightweight_SEO_Redirects_Service( $this->settings, false );
		$rules             = $redirects_service->get_all_redirect_rules();

		if ( empty( $rules ) ) {
			echo '<p class="lwseo-help">' . esc_html__( 'No redirect rules are available to export yet.', 'lightweight-seo' ) . '</p>';

			return;
		}

		$lines = array();
		echo '<label for="lightweight-seo-redirect-search" class="screen-reader-text">' . esc_html__( 'Search redirect rules', 'lightweight-seo' ) . '</label>';
		echo '<input type="search" id="lightweight-seo-redirect-search" class="regular-text" placeholder="' . esc_attr( __( 'Search redirect rules', 'lightweight-seo' ) ) . '">';
		echo '<div class="lwseo-table-wrap"><table class="lwseo-table lwseo-redirect-table"><thead><tr><th>' . esc_html__( 'Source', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Target', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Status', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

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

		echo '</tbody></table></div>';
		echo '<textarea rows="6" class="large-text code" readonly="readonly" aria-label="' . esc_attr__( 'Redirect rules snapshot', 'lightweight-seo' ) . '">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
		echo '<p class="lwseo-help">' . esc_html__( 'Copy this snapshot to migrate rules or keep an external backup. Manual rules can be imported by pasting them into the redirect rules field above.', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render redirect chain and loop health warnings.
	 *
	 * @since    1.1.0
	 */
	public function redirect_health_render() {
		if ( ! $this->is_module_enabled( 'redirects' ) ) {
			echo '<p class="lwseo-help">' . esc_html__( 'No redirect health checks run while the module is disabled.', 'lightweight-seo' ) . '</p>';

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
			echo '<ul class="lwseo-status-list">';
			Lightweight_SEO_Admin_UI::status( 'success', __( 'No redirect chains or loops detected.', 'lightweight-seo' ) );
			echo '</ul>';

			return;
		}

		echo '<div class="lwseo-table-wrap"><table class="lwseo-table"><thead><tr><th>' . esc_html__( 'Type', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Source', 'lightweight-seo' ) . '</th><th>' . esc_html__( 'Path', 'lightweight-seo' ) . '</th></tr></thead><tbody>';

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

		if ( ! empty( $options['gtm_container_id'] ) && ( ! empty( $options['ga4_measurement_id'] ) || ! empty( $options['facebook_pixel_id'] ) ) ) {
			Lightweight_SEO_Admin_UI::callout( __( 'GTM is active, so the direct GA4 and Meta Pixel alternatives are stored but suppressed to avoid duplicate events.', 'lightweight-seo' ), 'warning' );
		}

		echo '<details class="lwseo-details"><summary>' . esc_html__( 'Consent, CSP, and theme notes', 'lightweight-seo' ) . '</summary><div class="lwseo-details__body">';
		echo '<p>' . esc_html__( 'Tracking is excluded from the selected roles and environments. Consent plugins can use the lightweight_seo_tracking_consent_granted filter, and CSP integrations can supply a nonce through lightweight_seo_tracking_script_nonce.', 'lightweight-seo' ) . '</p>';
		echo '<p>' . esc_html__( 'GTM themes must call wp_body_open(). If they do not, Lightweight SEO adds a diagnostic comment to the page source and omits only the noscript fallback.', 'lightweight-seo' ) . '</p>';
		echo '</div></details>';
	}

	/**
	 * Render the GTM container ID field
	 *
	 * @since    1.0.1
	 */
	public function gtm_container_id_render() {
		$options = $this->settings->get_all();

		$this->render_text( 'gtm_container_id', $options['gtm_container_id'] ?? '', __( 'Recommended: enter a Google Tag Manager Container ID (e.g., GTM-XXXXXX).', 'lightweight-seo' ) );
	}

	/**
	 * Render the GA4 measurement ID field
	 *
	 * @since    1.0.1
	 */
	public function ga4_measurement_id_render() {
		$options = $this->settings->get_all();

		$this->render_text( 'ga4_measurement_id', $options['ga4_measurement_id'] ?? '', __( 'Direct alternative: enter a GA4 Measurement ID (e.g., G-XXXXXXXXXX). It is suppressed when GTM is configured.', 'lightweight-seo' ) );
	}

	/**
	 * Render the Facebook Pixel ID field
	 *
	 * @since    1.0.1
	 */
	public function facebook_pixel_id_render() {
		$options = $this->settings->get_all();

		$this->render_text( 'facebook_pixel_id', $options['facebook_pixel_id'] ?? '', __( 'Enter a numeric Meta Pixel ID. This direct alternative is suppressed when GTM is configured.', 'lightweight-seo' ) );
	}

	/** Render role slugs that suppress tracking for logged-in users. */
	public function tracking_excluded_roles_render() {
		$options = $this->settings->get_all();

		$this->render_text( 'tracking_excluded_roles', $options['tracking_excluded_roles'] ?? 'administrator', __( 'Comma-separated WordPress role slugs, for example administrator, editor. Leave empty to include all logged-in roles.', 'lightweight-seo' ) );
	}

	/** Render environments that suppress tracking. */
	public function tracking_excluded_environments_render() {
		$options = $this->settings->get_all();

		$this->render_text( 'tracking_excluded_environments', str_replace( "\n", ', ', $options['tracking_excluded_environments'] ?? "local\ndevelopment\nstaging" ), __( 'Choose from local, development, staging, and production. WordPress defaults to production when no environment type is set.', 'lightweight-seo' ) );
	}

	/** Explain the bounded, experimental AI Discovery scope. */
	public function ai_discovery_section_callback() {
		Lightweight_SEO_Admin_UI::callout( __( 'Google Search ignores llms.txt for visibility and rankings. No setting can guarantee crawling, citation, training, inclusion, or ranking in any AI product.', 'lightweight-seo' ) );
	}

	/** Render the explicit AI module toggle. */
	public function enable_ai_discovery_render() {
		$this->render_choice( 'enable_ai_discovery', '0', __( 'Enable the experimental AI Discovery module', 'lightweight-seo' ) );
	}

	/** Render search and user-directed crawler policy. */
	public function ai_search_crawlers_enabled_render() {
		$this->render_choice( 'ai_search_crawlers_enabled', '1', __( 'Allow bundled AI search and user-directed crawler tokens', 'lightweight-seo' ), __( 'This affects documented search and user-fetch tokens such as OAI-SearchBot, Claude-SearchBot, and PerplexityBot. It does not guarantee discovery or citation.', 'lightweight-seo' ) );
	}

	/** Render model-training crawler policy separately from search access. */
	public function ai_training_crawlers_enabled_render() {
		$this->render_choice( 'ai_training_crawlers_enabled', '0', __( 'Allow bundled model-training crawler tokens', 'lightweight-seo' ), __( 'Off blocks GPTBot, ClaudeBot, and Google-Extended. Google-Extended also controls some Gemini grounding uses, but does not affect Google Search inclusion or ranking.', 'lightweight-seo' ) );
	}

	/** Render the optional curated llms.txt endpoint toggle. */
	public function enable_llms_txt_render() {
		$this->render_choice( 'enable_llms_txt', '0', __( 'Publish an experimental /llms.txt Markdown index', 'lightweight-seo' ), __( 'Only selected public page summaries are listed. No llms-full.txt or full-page Markdown copies are generated.', 'lightweight-seo' ) );
	}

	/** Render the bounded list of manually selected post and page IDs. */
	public function llms_txt_post_ids_render() {
		$options = $this->settings->get_all();

		$this->render_text(
			'llms_txt_post_ids',
			$options['llms_txt_post_ids'] ?? '',
			/* translators: %d: Maximum number of curated post or page IDs. */
			sprintf( __( 'Enter up to %d comma-separated published post or page IDs. Drafts, private/password-protected, noindexed, redirected, external-canonical, or invalid URLs are excluded.', 'lightweight-seo' ), (int) Lightweight_SEO_Settings::MAX_LLMS_POSTS )
		);
	}

	/** Render deterministic, local-only readiness checks. */
	public function ai_readiness_render() {
		if ( ! $this->is_module_enabled( 'ai' ) || ! class_exists( 'Lightweight_SEO_AI_Discovery_Module', false ) ) {
			echo '<p class="lwseo-help">' . esc_html__( 'Enable and save the module to run readiness checks.', 'lightweight-seo' ) . '</p>';
			return;
		}

		$module = new Lightweight_SEO_AI_Discovery_Module( $this->settings, lightweight_seo_get_api(), 'admin' );

		echo '<ul class="lwseo-status-list">';
		foreach ( $module->get_readiness_checks() as $check ) {
			Lightweight_SEO_Admin_UI::status( 'pass' === $check['status'] ? 'success' : 'warning', $check['label'], $check['details'] );
		}
		echo '</ul>';
	}
}
