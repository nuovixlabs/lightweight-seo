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
	 * Shared post meta service, handed to the AI Discovery checks.
	 *
	 * @since    1.2.0
	 * @access   private
	 * @var      Lightweight_SEO_Post_Meta|null    $post_meta
	 */
	private $post_meta;

	/**
	 * Initialize the renderer.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings          $settings     Shared settings service.
	 * @param    Lightweight_SEO_Post_Meta|null    $post_meta    Optional. Shared post meta service.
	 */
	public function __construct( $settings, $post_meta = null ) {
		$this->settings  = $settings;
		$this->post_meta = $post_meta;
	}

	/**
	 * Print a checkbox setting.
	 *
	 * @since    1.1.0
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
	 * @since    1.1.0
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
			<div class="lwseo-subfield lwseo-subfield--wide">
				<label class="lwseo-subfield__label" for="lightweight_seo_local_business_image"><?php esc_html_e( 'Business image or logo', 'lightweight-seo' ); ?></label>
				<?php
				// The image is stored as a URL only, so the field carries no attachment ID.
				Lightweight_SEO_Admin_UI::image_field(
					array(
						'url'      => $options['local_business_image'] ?? '',
						'url_name' => LIGHTWEIGHT_SEO_OPTION_NAME . '[local_business_image]',
						'url_id'   => 'lightweight_seo_local_business_image',
						'alt'      => __( 'Business image', 'lightweight-seo' ),
					)
				);
				?>
			</div>
			<p class="lwseo-help lwseo-subfield--wide"><?php esc_html_e( 'Use a dedicated business image. The general social sharing image is not reused for LocalBusiness.', 'lightweight-seo' ); ?></p>
			<div class="lwseo-subfield lwseo-subfield--wide" role="group" aria-labelledby="lwseo-label-opening-hours">
				<span class="lwseo-subfield__label" id="lwseo-label-opening-hours"><?php esc_html_e( 'Opening hours', 'lightweight-seo' ); ?></span>
				<textarea id="lightweight_seo_local_business_opening_hours" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[local_business_opening_hours]" rows="4" class="code" aria-labelledby="lwseo-label-opening-hours"><?php echo esc_textarea( $options['local_business_opening_hours'] ?? '' ); ?></textarea>
				<p class="lwseo-help" data-lwseo-text-for="lightweight_seo_local_business_opening_hours"><?php esc_html_e( 'Add one opening-hours rule per line, e.g. Mo-Fr 09:00-17:00.', 'lightweight-seo' ); ?></p>
				<?php Lightweight_SEO_Admin_UI::hours( 'lightweight_seo_local_business_opening_hours' ); ?>
			</div>
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
		$labels  = array();

		// Name the pages, terms, and authors already mapped. A bounded lookup by ID.
		foreach ( $this->settings->get_hreflang_mappings() as $mapping ) {
			$object_id = absint( $mapping['object_id'] );
			$key       = $mapping['object_type'] . ':' . $object_id;

			if ( isset( $labels[ $key ] ) || 'mirror' === $mapping['object_type'] ) {
				continue;
			}

			if ( 'post' === $mapping['object_type'] ) {
				$label = get_post( $object_id ) ? wp_strip_all_tags( (string) get_the_title( $object_id ), true ) : '';
				/* translators: %d: post ID. */
				$fallback = __( 'Page #%d', 'lightweight-seo' );
			} elseif ( 'term' === $mapping['object_type'] ) {
				$term  = get_term( $object_id );
				$label = $term && ! is_wp_error( $term ) ? (string) $term->name : '';
				/* translators: %d: term ID. */
				$fallback = __( 'Term #%d', 'lightweight-seo' );
			} else {
				$user  = get_userdata( $object_id );
				$label = $user ? (string) $user->display_name : '';
				/* translators: %d: user ID. */
				$fallback = __( 'Author #%d', 'lightweight-seo' );
			}

			$labels[ $key ] = '' !== $label ? $label : sprintf( $fallback, $object_id );
		}
		?>
		<textarea id="lightweight_seo_hreflang_mappings" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[hreflang_mappings]" rows="5" class="large-text code" aria-labelledby="lwseo-label-hreflang_mappings"><?php echo esc_textarea( $options['hreflang_mappings'] ?? '' ); ?></textarea>
		<p class="lwseo-help" data-lwseo-text-for="lightweight_seo_hreflang_mappings"><?php esc_html_e( 'Add one line per translation: the page on this site, its language, and the address of the translated page, for example post:123 en-GB https://example.co.uk/page/', 'lightweight-seo' ); ?></p>
		<?php
		Lightweight_SEO_Admin_UI::rows(
			array(
				'format'  => 'hreflang',
				'field'   => 'lightweight_seo_hreflang_mappings',
				'columns' => array(
					array(
						'key'    => 'page',
						'label'  => __( 'Page on this site', 'lightweight-seo' ),
						'type'   => 'page',
						'choose' => __( 'Choose a page', 'lightweight-seo' ),
						'search' => __( 'Search pages, posts, and terms', 'lightweight-seo' ),
						'mirror' => __( 'Every page (legacy)', 'lightweight-seo' ),
					),
					array(
						'key'         => 'language',
						'label'       => __( 'Language', 'lightweight-seo' ),
						'type'        => 'text',
						'placeholder' => 'en-GB',
						'list'        => 'lwseo-hreflang-languages',
					),
					array(
						'key'         => 'url',
						'label'       => __( 'Address of the translated page', 'lightweight-seo' ),
						'type'        => 'url',
						'placeholder' => 'https://example.co.uk/page/',
					),
				),
				'add'     => __( 'Add translation', 'lightweight-seo' ),
				/* translators: %d: position of the translation in the list. */
				'item'    => __( 'Translation %d', 'lightweight-seo' ),
				'empty'   => __( 'No translations mapped yet.', 'lightweight-seo' ),
				'removed' => __( 'Translation removed.', 'lightweight-seo' ),
				'filter'  => __( 'Filter translations', 'lightweight-seo' ),
				'limit'   => Lightweight_SEO_Settings::MAX_HREFLANG_MAPPINGS,
				'labels'  => $labels,
				'hints'   => array(
					'incomplete' => __( 'Choose the page, its language, and the address of the translation. This row is not saved until it has all three.', 'lightweight-seo' ),
					'duplicate'  => __( 'An earlier row already maps this page to this language, so this one is ignored.', 'lightweight-seo' ),
				),
			)
		);
		?>
		<datalist id="lwseo-hreflang-languages">
			<?php foreach ( array( 'x-default', 'en', 'en-GB', 'en-US', 'en-AU', 'en-CA', 'en-IN', 'fr', 'fr-FR', 'fr-CA', 'de', 'de-DE', 'de-AT', 'de-CH', 'es', 'es-ES', 'es-MX', 'it', 'pt', 'pt-BR', 'pt-PT', 'nl', 'sv', 'da', 'nb', 'fi', 'pl', 'ja', 'ko', 'zh-Hans', 'zh-Hant', 'ar', 'hi' ) as $language ) : ?>
				<option value="<?php echo esc_attr( $language ); ?>"></option>
			<?php endforeach; ?>
		</datalist>
		<details class="lwseo-details">
			<summary><?php esc_html_e( 'Format details', 'lightweight-seo' ); ?></summary>
			<div class="lwseo-details__body">
				<p><?php esc_html_e( 'As text, each line starts with post:, term:, or user: followed by the ID. Language tags follow BCP 47, such as en, en-GB, or pt-BR, and x-default is supported. Author pages are mapped as text, for example user:2 en-GB https://example.co.uk/author/jane/', 'lightweight-seo' ); ?></p>
			</div>
		</details>
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
	 * Render the Redirects module switch.
	 *
	 * @since    1.1.0
	 */
	public function enable_redirects_render() {
		$this->render_choice( 'enable_redirects', '0', __( 'Apply the redirects below on this site', 'lightweight-seo' ), __( 'Rules stay saved while the module is off.', 'lightweight-seo' ) );
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
		<textarea id="lightweight_seo_redirect_rules" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME ); ?>[redirect_rules]" rows="8" class="large-text code" placeholder="/old-path /new-path 301" aria-labelledby="lwseo-label-redirect_rules"><?php echo esc_textarea( $options['redirect_rules'] ?? '' ); ?></textarea>
		<p class="lwseo-help" data-lwseo-text-for="lightweight_seo_redirect_rules"><?php esc_html_e( 'Add one rule per line: the old path, then the new path or full address, for example /old-path /new-path', 'lightweight-seo' ); ?></p>
		<?php
		Lightweight_SEO_Admin_UI::rows(
			array(
				'format'  => 'redirects',
				'field'   => 'lightweight_seo_redirect_rules',
				'columns' => array(
					array(
						'key'         => 'source',
						'label'       => __( 'From', 'lightweight-seo' ),
						'type'        => 'text',
						'placeholder' => '/old-path',
					),
					array(
						'key'         => 'target',
						'label'       => __( 'To', 'lightweight-seo' ),
						'type'        => 'text',
						'placeholder' => '/new-path',
					),
					array(
						'key'     => 'status',
						'label'   => __( 'Type', 'lightweight-seo' ),
						'type'    => 'select',
						'options' => array(
							'301' => __( '301 Permanent', 'lightweight-seo' ),
							'302' => __( '302 Temporary', 'lightweight-seo' ),
							'307' => __( '307 Temporary, same method', 'lightweight-seo' ),
							'308' => __( '308 Permanent, same method', 'lightweight-seo' ),
							'410' => __( '410 Gone', 'lightweight-seo' ),
						),
					),
				),
				'add'     => __( 'Add redirect', 'lightweight-seo' ),
				/* translators: %d: position of the redirect in the list. */
				'item'    => __( 'Redirect %d', 'lightweight-seo' ),
				'empty'   => __( 'No redirects yet.', 'lightweight-seo' ),
				'removed' => __( 'Redirect removed.', 'lightweight-seo' ),
				'filter'  => __( 'Filter redirects', 'lightweight-seo' ),
				'limit'   => Lightweight_SEO_Settings::MAX_MANUAL_REDIRECTS,
				'hints'   => array(
					'incomplete' => __( 'Add both a path to redirect from and somewhere to go, or choose "410 Gone". This row is not saved until then.', 'lightweight-seo' ),
					'same'       => __( 'From and To are the same, so this redirect would do nothing.', 'lightweight-seo' ),
					'duplicate'  => __( 'A later row redirects the same path, so this one is ignored.', 'lightweight-seo' ),
				),
			)
		);
		?>
		<details class="lwseo-details">
			<summary><?php esc_html_e( 'Status codes, whole sections, and other sites', 'lightweight-seo' ); ?></summary>
			<div class="lwseo-details__body">
				<p><?php esc_html_e( 'A rule is a permanent 301 redirect unless it ends with 302, 307, or 308.', 'lightweight-seo' ); ?></p>
				<p><?php esc_html_e( 'End the old path with /* to cover everything under it, for example /old-section/*. End the new path with /* as well to keep the rest of each address: /old-section/* /new-section/*. A rule for one exact path always wins, then the longest matching section.', 'lightweight-seo' ); ?></p>
				<p><?php esc_html_e( 'Choose "410 Gone" for a page that was removed on purpose and has no replacement. Visitors see the "not found" page of your theme, and search engines are told the page will not come back. As text: /old-path 410', 'lightweight-seo' ); ?></p>
				<p><?php esc_html_e( 'A redirect to another site works only after that host is approved through the WordPress allowed_redirect_hosts filter.', 'lightweight-seo' ); ?></p>
			</div>
		</details>
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
			echo '<p class="lwseo-help">' . esc_html__( 'Switch the Redirects module on to export its rules.', 'lightweight-seo' ) . '</p>';

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
			$lines[] = Lightweight_SEO_Settings::format_redirect_rule( $rule + array( 'status' => 301 ) );
			echo '<tr><td><code>' . esc_html( $rule['source'] ?? '' ) . '</code></td><td><code>' . esc_html( $rule['target'] ?? '' ) . '</code></td><td>' . esc_html( (string) ( $rule['status'] ?? 301 ) ) . '</td></tr>';
		}

		echo '</tbody></table></div>';
		echo '<textarea rows="6" class="large-text code" readonly="readonly" aria-label="' . esc_attr__( 'Redirect rules snapshot', 'lightweight-seo' ) . '">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
		echo '<p class="lwseo-help">' . esc_html__( 'Copy this snapshot to migrate rules or keep an external backup. It can be pasted into the rules above through "Edit as text".', 'lightweight-seo' ) . '</p>';
	}

	/**
	 * Render the CSV import control. It belongs to its own form, outside the settings form.
	 *
	 * @since    1.1.0
	 */
	public function redirect_import_render() {
		$form = Lightweight_SEO_Admin_Tools_Fields::REDIRECT_IMPORT_FORM_ID;
		?>
		<div class="lwseo-inline">
			<input type="file" id="lightweight_seo_redirect_import" name="lightweight_seo_redirect_csv" accept=".csv,text/csv" form="<?php echo esc_attr( $form ); ?>">
			<button type="submit" class="button" form="<?php echo esc_attr( $form ); ?>"><?php esc_html_e( 'Import rules', 'lightweight-seo' ); ?></button>
		</div>
		<p class="lwseo-help"><?php esc_html_e( 'One rule per row: the old path, the new path or address, and the status code (301 when left out; 410 needs no new path). A header row is ignored. A rule for a path that already has one replaces it.', 'lightweight-seo' ); ?></p>
		<p class="lwseo-help"><?php esc_html_e( 'The import saves straight away. Save other changes on this screen first, because they are not kept.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the box that shows which saved rule applies to one address.
	 *
	 * @since    1.1.0
	 */
	public function redirect_test_render() {
		$form = Lightweight_SEO_Admin_Tools_Fields::REDIRECT_TEST_FORM_ID;
		$path = $this->get_redirect_test_path();
		?>
		<div class="lwseo-inline">
			<input type="text" id="lightweight_seo_redirect_test" name="<?php echo esc_attr( Lightweight_SEO_Admin_Tools_Fields::REDIRECT_TEST_ARG ); ?>" value="<?php echo esc_attr( $path ); ?>" placeholder="/old-path" class="regular-text" autocomplete="off" form="<?php echo esc_attr( $form ); ?>">
			<button type="submit" class="button" form="<?php echo esc_attr( $form ); ?>"><?php esc_html_e( 'Test', 'lightweight-seo' ); ?></button>
		</div>
		<?php
		if ( '' !== $path ) {
			$this->render_redirect_test_result( $path );
		}
		?>
		<p class="lwseo-help"><?php esc_html_e( 'Enter a path or an address on this site to see which saved rule applies to it. Rules are tested as they are saved, without visiting the address.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Get the path the screen was opened to test, reduced to a path on this site.
	 *
	 * @since    1.1.0
	 * @return   string Path, or an empty string.
	 */
	private function get_redirect_test_path() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: the path is only looked up in the saved rules and shown.
		$value = isset( $_GET[ Lightweight_SEO_Admin_Tools_Fields::REDIRECT_TEST_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ Lightweight_SEO_Admin_Tools_Fields::REDIRECT_TEST_ARG ] ) ) : '';
		$path  = (string) wp_parse_url( trim( $value ), PHP_URL_PATH );

		return '' === $path ? '' : '/' . ltrim( $path, '/' );
	}

	/**
	 * Say what the saved rules do with one path.
	 *
	 * @since    1.1.0
	 * @param    string    $path    Path to follow through the rules.
	 * @return   void
	 */
	private function render_redirect_test_result( $path ) {
		$this->load_redirects_service_for_read_only_admin();

		$trace = ( new Lightweight_SEO_Redirects_Service( $this->settings, false ) )->trace_request( $path );
		$hops  = $trace['hops'];
		$last  = empty( $hops ) ? array() : $hops[ count( $hops ) - 1 ];
		$steps = array();

		foreach ( $hops as $hop ) {
			if ( 410 === (int) $hop['status'] ) {
				/* translators: %s: redirect rule as it is saved, for example "/old-path". */
				$steps[] = sprintf( __( 'Rule %s: gone (410)', 'lightweight-seo' ), $hop['rule'] );
			} else {
				/* translators: 1: redirect rule as it is saved. 2: where it sends the visitor. 3: status code. */
				$steps[] = sprintf( __( 'Rule %1$s: to %2$s (%3$d)', 'lightweight-seo' ), $hop['rule'], $hop['target'], (int) $hop['status'] );
			}
		}

		echo '<ul class="lwseo-status-list" role="status">';

		if ( empty( $hops ) ) {
			/* translators: %s: tested path. */
			Lightweight_SEO_Admin_UI::status( 'info', sprintf( __( 'No rule applies to %s', 'lightweight-seo' ), $path ), __( 'WordPress handles this address as usual.', 'lightweight-seo' ) );
		} elseif ( $trace['loop'] ) {
			/* translators: %s: tested path. */
			Lightweight_SEO_Admin_UI::status( 'warning', sprintf( __( 'The rules for %s loop', 'lightweight-seo' ), $path ), array_merge( $steps, array( __( 'No redirect is sent for a loop. Change one of these rules.', 'lightweight-seo' ) ) ) );
		} elseif ( 410 === (int) $last['status'] ) {
			/* translators: %s: tested path. */
			Lightweight_SEO_Admin_UI::status( 'info', sprintf( __( '%s answers "410 Gone"', 'lightweight-seo' ), $path ), $steps );
		} else {
			if ( count( $hops ) > 1 ) {
				$steps[] = __( 'Visitors are sent straight to the last address.', 'lightweight-seo' );
			}

			/* translators: 1: tested path. 2: address the visitor ends up at. */
			Lightweight_SEO_Admin_UI::status( 'info', sprintf( __( '%1$s redirects to %2$s', 'lightweight-seo' ), $path, $last['target'] ), $steps );
		}

		echo '</ul>';
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
	 * Render the Tracking module switch.
	 *
	 * @since    1.1.0
	 */
	public function enable_tracking_render() {
		$this->render_choice( 'enable_tracking', '0', __( 'Add the tracking codes below to the site', 'lightweight-seo' ), __( 'IDs stay saved while the module is off.', 'lightweight-seo' ) );
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

	/**
	 * Print a list of tick boxes that is saved as one list of keys.
	 *
	 * An empty first entry keeps the setting in the request when every box is cleared,
	 * so an empty list can be saved.
	 *
	 * @since    1.1.0
	 * @param    string    $key         Settings key.
	 * @param    array     $choices     Labels keyed by the value each box submits.
	 * @param    array     $selected    Values that are ticked.
	 * @param    string    $help        Help text shown under the list.
	 * @return   void
	 */
	private function render_choice_list( $key, $choices, $selected, $help ) {
		$name = LIGHTWEIGHT_SEO_OPTION_NAME . '[' . $key . '][]';

		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="">';
		echo '<div class="lwseo-choices">';

		foreach ( $choices as $value => $label ) {
			Lightweight_SEO_Admin_UI::choice( $name, in_array( (string) $value, $selected, true ), $label, '', '', (string) $value );
		}

		echo '</div><p class="lwseo-help">' . esc_html( $help ) . '</p>';
	}

	/** Render the roles whose logged-in visits are not tracked. */
	public function tracking_excluded_roles_render() {
		$options = $this->settings->get_all();
		$roles   = array();

		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$roles[ $slug ] = translate_user_role( $name );
		}

		$this->render_choice_list( 'tracking_excluded_roles', $roles, $this->settings->normalize_key_list( $options['tracking_excluded_roles'] ?? 'administrator' ), __( 'Visits by signed-in users with a ticked role are not tracked.', 'lightweight-seo' ) );
	}

	/** Render the environments where tracking stays off. */
	public function tracking_excluded_environments_render() {
		$options      = $this->settings->get_all();
		$environments = array(
			'local'       => __( 'Local', 'lightweight-seo' ),
			'development' => __( 'Development', 'lightweight-seo' ),
			'staging'     => __( 'Staging', 'lightweight-seo' ),
			'production'  => __( 'Production', 'lightweight-seo' ),
		);

		$this->render_choice_list( 'tracking_excluded_environments', $environments, $this->settings->normalize_key_list( $options['tracking_excluded_environments'] ?? "local\ndevelopment\nstaging" ), __( 'Tracking stays off in a ticked environment. WordPress treats a site as production unless it is told otherwise.', 'lightweight-seo' ) );
	}

	/** Say plainly that this module contacts another service. */
	public function indexnow_section_callback() {
		Lightweight_SEO_Admin_UI::callout(
			array(
				__( 'When this module is on, the address of a page you publish, change, or remove is sent to api.indexnow.org, which passes it to the search engines that take part, such as Bing and Yandex. Google does not use IndexNow.', 'lightweight-seo' ),
				__( 'Besides tracking codes you add yourself, this is the only part of Lightweight SEO that contacts another service. Nothing is sent while the module is off.', 'lightweight-seo' ),
			)
		);
	}

	/** Render the IndexNow module switch. */
	public function enable_indexnow_render() {
		$this->render_choice( 'enable_indexnow', '0', __( 'Send changed addresses to IndexNow', 'lightweight-seo' ), __( 'Only public pages that search engines may show are sent, once per save, and saving never waits for the answer.', 'lightweight-seo' ) );
	}

	/** Render what the IndexNow module is doing: its key file, whether it sends, and what it last sent. */
	public function indexnow_status_render() {
		if ( ! class_exists( 'Lightweight_SEO_IndexNow_Module', false ) ) {
			require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-indexnow-module.php';
		}

		$module  = new Lightweight_SEO_IndexNow_Module( $this->settings, null, false );
		$key_url = $module->get_key_url();
		$reason  = $module->get_blocking_reason();
		$last    = get_option( Lightweight_SEO_IndexNow_Module::LAST_OPTION, array() );
		$form    = Lightweight_SEO_Admin_Tools_Fields::INDEXNOW_TEST_FORM_ID;

		echo '<ul class="lwseo-status-list">';

		if ( '' === $reason ) {
			Lightweight_SEO_Admin_UI::status( 'success', __( 'Changed pages are sent when they are saved', 'lightweight-seo' ) );
		} else {
			Lightweight_SEO_Admin_UI::status( 'info', __( 'Nothing is sent from this site', 'lightweight-seo' ), $reason );
		}

		if ( '' !== $key_url ) {
			Lightweight_SEO_Admin_UI::status(
				'info',
				__( 'Key file', 'lightweight-seo' ),
				array( $key_url, __( 'Search engines read this address to confirm that the site sends its own pages. It is answered by the plugin; no file is written.', 'lightweight-seo' ) ),
				array(
					'label' => __( 'Open', 'lightweight-seo' ),
					'url'   => $key_url,
				)
			);
		}

		if ( is_array( $last ) && ! empty( $last['time'] ) ) {
			$sent = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last['time'] );
			$more = max( 0, (int) ( $last['count'] ?? 1 ) - 1 );

			Lightweight_SEO_Admin_UI::status(
				'info',
				/* translators: %s: date and time. */
				sprintf( __( 'Last sent on %s', 'lightweight-seo' ), $sent ),
				$more > 0
					/* translators: 1: address. 2: number of further addresses. */
					? sprintf( _n( '%1$s and %2$d more address', '%1$s and %2$d more addresses', $more, 'lightweight-seo' ), (string) ( $last['url'] ?? '' ), $more )
					: (string) ( $last['url'] ?? '' )
			);
		} else {
			Lightweight_SEO_Admin_UI::status( 'info', __( 'Nothing has been sent yet', 'lightweight-seo' ) );
		}

		echo '</ul>';
		?>
		<div class="lwseo-buttons">
			<button type="submit" class="button" form="<?php echo esc_attr( $form ); ?>"><?php esc_html_e( 'Send a test', 'lightweight-seo' ); ?></button>
		</div>
		<p class="lwseo-help"><?php esc_html_e( 'Sends the address of the homepage once, waits for the answer, and shows it.', 'lightweight-seo' ); ?></p>
		<?php
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
		$this->render_choice( 'ai_search_crawlers_enabled', '1', __( 'Allow bundled AI search and user-directed crawler tokens', 'lightweight-seo' ), __( 'On, Lightweight SEO does not block the documented AI search crawlers and user-requested fetchers, such as OAI-SearchBot, Claude-SearchBot, PerplexityBot, Meta-WebIndexer, Amzn-SearchBot, DuckAssistBot, MistralAI-Index, and ChatGPT-User, so they follow the site’s rules for every crawler; off, it adds a Disallow group for each. Some user-requested fetchers do not follow robots.txt, and none of this guarantees discovery or citation.', 'lightweight-seo' ) );
	}

	/** Render model-training crawler policy separately from search access. */
	public function ai_training_crawlers_enabled_render() {
		$this->render_choice( 'ai_training_crawlers_enabled', '0', __( 'Allow bundled model-training crawler tokens', 'lightweight-seo' ), __( 'Off, Lightweight SEO adds a Disallow group for GPTBot, ClaudeBot, Google-Extended, Applebot-Extended, Meta-ExternalAgent, Amazonbot, and MistralAI-Training; on, it does not block them, so they follow the site’s rules for every crawler. Meta-ExternalAgent and Amazonbot are blocked for every use, not only training. Google-Extended also controls some Gemini grounding uses, but does not affect Google Search inclusion or ranking.', 'lightweight-seo' ) );
	}

	/** Render the Content-Signal line switch. */
	public function enable_content_signals_render() {
		$this->render_choice( 'enable_content_signals', '0', __( 'State how this site’s content may be used for search, AI answers, and AI training', 'lightweight-seo' ), __( 'Adds one Content-Signal line to the virtual robots.txt: search=yes, ai-input following the AI search switch above, and ai-train following the model-training switch. It states a preference; crawlers may ignore it.', 'lightweight-seo' ) );
	}

	/** Render the optional curated llms.txt endpoint toggle. */
	public function enable_llms_txt_render() {
		$this->render_choice( 'enable_llms_txt', '0', __( 'Publish an experimental /llms.txt Markdown index', 'lightweight-seo' ), __( 'Only selected public page summaries are listed, and no llms-full.txt is generated; a whole page is sent as Markdown only by the Markdown on request switch.', 'lightweight-seo' ) );
	}

	/**
	 * Render the Markdown on request switch.
	 *
	 * @since 1.3.0
	 */
	public function enable_markdown_on_request_render() {
		$this->render_choice( 'enable_markdown_on_request', '0', __( 'Answer a request for Markdown with a Markdown version of the page (experimental)', 'lightweight-seo' ), __( 'Only a client whose Accept header asks for text/markdown gets it, and no setting guarantees that an agent asks for it or uses it; browsers and search engines get the same HTML as before.', 'lightweight-seo' ) );
	}

	/** Render the bounded list of manually selected post and page IDs. */
	public function llms_txt_post_ids_render() {
		$options = $this->settings->get_all();
		$items   = array();

		// Name the pages already chosen. A bounded lookup by ID, never a site-wide query.
		foreach ( $this->settings->get_llms_post_ids() as $post_id ) {
			$post  = get_post( $post_id );
			$title = $post ? wp_strip_all_tags( (string) get_the_title( $post_id ), true ) : '';
			$note  = '';

			if ( ! $post ) {
				$note = __( 'No longer exists', 'lightweight-seo' );
			} elseif ( 'publish' !== (string) ( $post->post_status ?? '' ) || '' !== (string) ( $post->post_password ?? '' ) ) {
				$note = __( 'Not public, so it is left out', 'lightweight-seo' );
			}

			$items[ $post_id ] = array(
				'title' => $title,
				'note'  => $note,
			);
		}
		?>
		<input type="text" id="lightweight_seo_llms_txt_post_ids" name="<?php echo esc_attr( LIGHTWEIGHT_SEO_OPTION_NAME . '[llms_txt_post_ids]' ); ?>" value="<?php echo esc_attr( $options['llms_txt_post_ids'] ?? '' ); ?>" class="regular-text" aria-labelledby="lwseo-label-llms_txt_post_ids">
		<p class="lwseo-help" data-lwseo-text-for="lightweight_seo_llms_txt_post_ids">
			<?php
			/* translators: %d: Maximum number of curated post or page IDs. */
			echo esc_html( sprintf( __( 'Enter up to %d post or page IDs, separated by commas.', 'lightweight-seo' ), (int) Lightweight_SEO_Settings::MAX_LLMS_POSTS ) );
			?>
		</p>
		<?php
		Lightweight_SEO_Admin_UI::picker(
			array(
				'field'    => 'lightweight_seo_llms_txt_post_ids',
				'items'    => $items,
				'limit'    => Lightweight_SEO_Settings::MAX_LLMS_POSTS,
				'search'   => __( 'Search posts and pages to add', 'lightweight-seo' ),
				'chosen'   => __( 'Pages listed in llms.txt', 'lightweight-seo' ),
				'empty'    => __( 'No pages chosen yet.', 'lightweight-seo' ),
				'removed'  => __( 'Page removed.', 'lightweight-seo' ),
				/* translators: %d: post ID. */
				'fallback' => __( 'Page #%d', 'lightweight-seo' ),
			)
		);
		?>
		<p class="lwseo-help"><?php esc_html_e( 'Drafts, private or password-protected pages, pages hidden from search engines, and redirected pages are left out of llms.txt.', 'lightweight-seo' ); ?></p>
		<?php
	}

	/**
	 * Render the AI readiness report, grouped by area.
	 *
	 * Rows about the site's settings are decided here. Rows about a served address are printed
	 * in a pending state with every message they can show; the administrator's browser fetches
	 * the addresses and lightweight-seo-robots.js decides them. PHP requests nothing. Without
	 * the script, those rows keep their pending text and a link that opens the address.
	 */
	public function ai_readiness_render() {
		if ( ! $this->is_module_enabled( 'ai' ) || ! class_exists( 'Lightweight_SEO_AI_Discovery_Module', false ) ) {
			echo '<p class="lwseo-help">' . esc_html__( 'Enable and save the module to run readiness checks.', 'lightweight-seo' ) . '</p>';
			return;
		}

		$module   = new Lightweight_SEO_AI_Discovery_Module( $this->settings, function_exists( 'lightweight_seo_get_api' ) ? lightweight_seo_get_api() : null, 'admin', $this->post_meta );
		$expected = $module->get_served_robots_expectations();
		$keys     = array( 'rules', 'marker', 'crawlers', 'sitemapLine', 'sitemapFromRobots', 'contentSignal', 'signalOff', 'llmsEnabled', 'markdownEnabled', 'probes' );

		echo '<div class="lwseo-readiness" data-lwseo-served';
		echo ' data-url="' . esc_url( $expected['url'] ) . '"';
		echo ' data-expected="' . esc_attr( (string) wp_json_encode( array_intersect_key( $expected, array_flip( $keys ) ) ) ) . '"';
		echo ' data-msg-unchecked="' . esc_attr( __( 'Not checked, because robots.txt could not be read.', 'lightweight-seo' ) ) . '"';

		foreach ( array( 'success', 'warning', 'info', 'off', 'na' ) as $state ) {
			echo ' data-prefix-' . esc_attr( $state ) . '="' . esc_attr( Lightweight_SEO_Admin_UI::status_prefix( $state ) ) . '"';
		}

		echo '>';

		foreach ( $module->get_readiness_report() as $area ) {
			echo '<h4 class="lwseo-readiness__area">' . esc_html( $area['title'] ) . '</h4><ul class="lwseo-status-list">';

			foreach ( $area['rows'] as $row ) {
				if ( 'browser' !== $row['state'] ) {
					Lightweight_SEO_Admin_UI::status( $row['state'], $row['label'], $row['text'], array(), array( 'data-readiness' => $row['id'] ) + (array) ( $row['attributes'] ?? array() ) );
					continue;
				}

				$attributes = array(
					'data-readiness'    => $row['id'],
					'data-served-check' => $row['id'],
				);

				foreach ( $row['messages'] as $key => $message ) {
					$attributes[ 'data-msg-' . $key ] = $message;
				}

				Lightweight_SEO_Admin_UI::status(
					'pending',
					$row['label'],
					$row['text'],
					array(
						'label'   => __( 'Open', 'lightweight-seo' ),
						'url'     => $row['url'],
						'new_tab' => true,
					),
					$attributes
				);
			}

			echo '</ul>';
		}

		echo '</div>';
		?>
		<p class="lwseo-help"><?php esc_html_e( 'Each row states a fact. There is no score: a standard Lightweight SEO does not take part in says why, a file this version does not publish is “Not offered”, not a problem, and an address this browser could not fetch is “Not checked”.', 'lightweight-seo' ); ?></p>
		<?php
	}
}
