<?php
/**
 * Shared term and author SEO meta service for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Shared archive meta service.
 */
class Lightweight_SEO_Archive_Meta {

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings    $settings
	 */
	private $settings;

	/**
	 * Registered archive meta keys.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      array    $meta_keys
	 */
	private $meta_keys = array(
		'seo_title'             => '_lightweight_seo_title',
		'seo_description'       => '_lightweight_seo_description',
		'seo_canonical_url'     => '_lightweight_seo_canonical_url',
		'seo_noindex'           => '_lightweight_seo_noindex',
		'seo_nofollow'          => '_lightweight_seo_nofollow',
		'seo_noarchive'         => '_lightweight_seo_noarchive',
		'seo_nosnippet'         => '_lightweight_seo_nosnippet',
		'seo_max_image_preview' => '_lightweight_seo_max_image_preview',
	);

	/**
	 * Cached term meta values.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      array
	 */
	private $term_cache = array();

	/**
	 * Cached user meta values.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      array
	 */
	private $user_cache = array();

	/**
	 * Initialize the service.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings    $settings    Shared settings service.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;

		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_action( 'admin_init', array( $this, 'register_admin_hooks' ) );
	}

	/**
	 * Get supported public taxonomies.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	public function get_supported_taxonomies() {
		return array_values(
			array_filter(
				apply_filters( 'lightweight_seo_supported_taxonomies', get_taxonomies( array( 'public' => true ), 'names' ) ),
				'taxonomy_exists'
			)
		);
	}

	/**
	 * Register term and user meta support.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function register_meta() {
		$meta_config = $this->get_meta_config();

		foreach ( $this->get_supported_taxonomies() as $taxonomy ) {
			foreach ( $meta_config as $field => $args ) {
				register_term_meta(
					$taxonomy,
					$this->meta_keys[ $field ],
					array(
						'single'            => true,
						'show_in_rest'      => true,
						'type'              => $args['type'],
						'sanitize_callback' => $args['sanitize_callback'],
						'auth_callback'     => array( $this, 'can_edit_term_meta' ),
					)
				);
			}
		}

		foreach ( $meta_config as $field => $args ) {
			register_meta(
				'user',
				$this->meta_keys[ $field ],
				array(
					'object_subtype'    => 'user',
					'single'            => true,
					'show_in_rest'      => true,
					'type'              => $args['type'],
					'sanitize_callback' => $args['sanitize_callback'],
					'auth_callback'     => array( $this, 'can_edit_user_meta' ),
				)
			);
		}
	}

	/**
	 * Register taxonomy and user admin hooks.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function register_admin_hooks() {
		foreach ( $this->get_supported_taxonomies() as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( $this, 'render_term_add_fields' ) );
			add_action( $taxonomy . '_edit_form_fields', array( $this, 'render_term_edit_fields' ) );
			add_action( 'created_' . $taxonomy, array( $this, 'save_term_fields' ) );
			add_action( 'edited_' . $taxonomy, array( $this, 'save_term_fields' ) );
		}

		add_action( 'show_user_profile', array( $this, 'render_user_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_user_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_fields' ) );
	}

	/**
	 * Get a registered archive meta key by field alias.
	 *
	 * @since    1.1.0
	 * @param    string    $field    Meta field alias.
	 * @return   string
	 */
	public function get_meta_key( $field ) {
		return $this->meta_keys[ $field ] ?? '';
	}

	/**
	 * Get all registered SEO meta for a term.
	 *
	 * @since    1.1.0
	 * @param    int    $term_id    Term ID.
	 * @return   array
	 */
	public function get_term_all( $term_id ) {
		$term_id = (int) $term_id;

		if ( ! isset( $this->term_cache[ $term_id ] ) ) {
			$meta_values = array();

			foreach ( $this->meta_keys as $field => $meta_key ) {
				$meta_values[ $field ] = get_term_meta( $term_id, $meta_key, true );
			}

			$this->term_cache[ $term_id ] = $meta_values;
		}

		return $this->term_cache[ $term_id ];
	}

	/**
	 * Get all registered SEO meta for an author.
	 *
	 * @since    1.1.0
	 * @param    int    $user_id    User ID.
	 * @return   array
	 */
	public function get_user_all( $user_id ) {
		$user_id = (int) $user_id;

		if ( ! isset( $this->user_cache[ $user_id ] ) ) {
			$meta_values = array();

			foreach ( $this->meta_keys as $field => $meta_key ) {
				$meta_values[ $field ] = get_user_meta( $user_id, $meta_key, true );
			}

			$this->user_cache[ $user_id ] = $meta_values;
		}

		return $this->user_cache[ $user_id ];
	}

	/**
	 * Update a single SEO meta value for a term.
	 *
	 * @since    1.1.0
	 * @param    int       $term_id    Term ID.
	 * @param    string    $field      Meta field alias.
	 * @param    mixed     $value      Value to store.
	 * @return   bool|int
	 */
	public function update_term( $term_id, $field, $value ) {
		if ( ! isset( $this->meta_keys[ $field ] ) ) {
			return false;
		}

		unset( $this->term_cache[ (int) $term_id ] );

		return update_term_meta( $term_id, $this->meta_keys[ $field ], $value );
	}

	/**
	 * Update a single SEO meta value for an author.
	 *
	 * @since    1.1.0
	 * @param    int       $user_id    User ID.
	 * @param    string    $field      Meta field alias.
	 * @param    mixed     $value      Value to store.
	 * @return   bool|int
	 */
	public function update_user( $user_id, $field, $value ) {
		if ( ! isset( $this->meta_keys[ $field ] ) ) {
			return false;
		}

		unset( $this->user_cache[ (int) $user_id ] );

		return update_user_meta( $user_id, $this->meta_keys[ $field ], $value );
	}

	/**
	 * Render SEO fields on the taxonomy add screen.
	 *
	 * @since    1.1.0
	 * @param    string    $taxonomy    Taxonomy name.
	 * @return   void
	 */
	public function render_term_add_fields( $taxonomy ) {
		wp_nonce_field( 'lightweight_seo_term_meta', 'lightweight_seo_term_meta_nonce' );

		foreach ( $this->get_fields() as $field => $definition ) {
			$value = $definition['default'];
			?>
			<div class="form-field term-<?php echo esc_attr( $field ); ?>-wrap">
				<label for="lightweight_seo_archive_<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $definition['label'] ); ?></label>
				<?php $this->render_field_control( $field, $value ); ?>
				<p class="description"><?php echo esc_html( $definition['description'] ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Render SEO fields on the taxonomy edit screen.
	 *
	 * @since    1.1.0
	 * @param    WP_Term    $term    Current term object.
	 * @return   void
	 */
	public function render_term_edit_fields( $term ) {
		$values = $this->get_term_all( $term->term_id );

		wp_nonce_field( 'lightweight_seo_term_meta', 'lightweight_seo_term_meta_nonce' );

		foreach ( $this->get_fields() as $field => $definition ) {
			$value = $values[ $field ] ?? $definition['default'];
			?>
			<tr class="form-field term-<?php echo esc_attr( $field ); ?>-wrap">
				<th scope="row"><label for="lightweight_seo_archive_<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $definition['label'] ); ?></label></th>
				<td>
					<?php $this->render_field_control( $field, $value ); ?>
					<p class="description"><?php echo esc_html( $definition['description'] ); ?></p>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Save taxonomy SEO fields.
	 *
	 * @since    1.1.0
	 * @param    int    $term_id    Term ID.
	 * @return   void
	 */
	public function save_term_fields( $term_id ) {
		if ( ! isset( $_POST['lightweight_seo_term_meta_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lightweight_seo_term_meta_nonce'] ) ), 'lightweight_seo_term_meta' ) ) {
			return;
		}

		$this->save_meta_values(
			(int) $term_id,
			array( $this, 'update_term' )
		);
	}

	/**
	 * Render SEO fields on user profile screens.
	 *
	 * @since    1.1.0
	 * @param    WP_User    $user    Current user object.
	 * @return   void
	 */
	public function render_user_fields( $user ) {
		$values = $this->get_user_all( $user->ID );
		?>
		<h2><?php esc_html_e( 'SEO Settings', 'lightweight-seo' ); ?></h2>
		<?php wp_nonce_field( 'lightweight_seo_user_meta', 'lightweight_seo_user_meta_nonce' ); ?>
		<table class="form-table" role="presentation">
			<?php foreach ( $this->get_fields() as $field => $definition ) : ?>
				<tr>
					<th><label for="lightweight_seo_archive_<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $definition['label'] ); ?></label></th>
					<td>
						<?php $this->render_field_control( $field, $values[ $field ] ?? $definition['default'] ); ?>
						<p class="description"><?php echo esc_html( $definition['description'] ); ?></p>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Save user SEO fields.
	 *
	 * @since    1.1.0
	 * @param    int    $user_id    User ID.
	 * @return   void
	 */
	public function save_user_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		if ( ! isset( $_POST['lightweight_seo_user_meta_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lightweight_seo_user_meta_nonce'] ) ), 'lightweight_seo_user_meta' ) ) {
			return;
		}

		$this->save_meta_values(
			(int) $user_id,
			array( $this, 'update_user' )
		);
	}

	/**
	 * Check whether the current user can edit term meta.
	 *
	 * @since    1.1.0
	 * @param    bool      $allowed     Whether editing is allowed.
	 * @param    string    $meta_key    Meta key.
	 * @param    int       $term_id     Term ID.
	 * @return   bool
	 */
	public function can_edit_term_meta( $allowed, $meta_key, $term_id ) {
		return current_user_can( 'edit_term', $term_id ) || current_user_can( 'manage_categories' );
	}

	/**
	 * Check whether the current user can edit user meta.
	 *
	 * @since    1.1.0
	 * @param    bool      $allowed     Whether editing is allowed.
	 * @param    string    $meta_key    Meta key.
	 * @param    int       $user_id     User ID.
	 * @return   bool
	 */
	public function can_edit_user_meta( $allowed, $meta_key, $user_id ) {
		return current_user_can( 'edit_user', $user_id );
	}

	/**
	 * Save posted SEO fields through the supplied updater callback.
	 *
	 * @since    1.1.0
	 * @param    int         $object_id    Term or user ID.
	 * @param    callable    $updater      Meta update callback.
	 * @return   void
	 */
	private function save_meta_values( $object_id, $updater ) {
		foreach ( array_keys( $this->get_fields() ) as $field ) {
			$raw_value = $this->get_posted_value( $field );
			$value     = $this->sanitize_meta_value( $field, $raw_value );

			call_user_func( $updater, $object_id, $field, $value );
		}
	}

	/**
	 * Get configured field definitions for archive SEO.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function get_fields() {
		return array(
			'seo_title'             => array(
				'label'       => __( 'SEO Title', 'lightweight-seo' ),
				'description' => __( 'Optional title override for this archive page.', 'lightweight-seo' ),
				'default'     => '',
			),
			'seo_description'       => array(
				'label'       => __( 'Meta Description', 'lightweight-seo' ),
				'description' => __( 'Optional description override for this archive page.', 'lightweight-seo' ),
				'default'     => '',
			),
			'seo_canonical_url'     => array(
				'label'       => __( 'Canonical URL', 'lightweight-seo' ),
				'description' => __( 'Leave empty to use the archive URL.', 'lightweight-seo' ),
				'default'     => '',
			),
			'seo_noindex'           => array(
				'label'       => __( 'Noindex', 'lightweight-seo' ),
				'description' => __( 'Prevent this archive page from being indexed.', 'lightweight-seo' ),
				'default'     => '0',
			),
			'seo_nofollow'          => array(
				'label'       => __( 'Nofollow', 'lightweight-seo' ),
				'description' => __( 'Prevent search engines from following links on this archive page.', 'lightweight-seo' ),
				'default'     => '0',
			),
			'seo_noarchive'         => array(
				'label'       => __( 'Noarchive', 'lightweight-seo' ),
				'description' => __( 'Prevent cached copies for this archive page.', 'lightweight-seo' ),
				'default'     => '0',
			),
			'seo_nosnippet'         => array(
				'label'       => __( 'Nosnippet', 'lightweight-seo' ),
				'description' => __( 'Prevent text snippets in search results.', 'lightweight-seo' ),
				'default'     => '0',
			),
			'seo_max_image_preview' => array(
				'label'       => __( 'Max Image Preview', 'lightweight-seo' ),
				'description' => __( 'Override the global image preview robots directive for this archive page.', 'lightweight-seo' ),
				'default'     => '',
			),
		);
	}

	/**
	 * Get the registered meta configuration.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	private function get_meta_config() {
		return array(
			'seo_title'             => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'seo_description'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'seo_canonical_url'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			'seo_noindex'           => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'seo_nofollow'          => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'seo_noarchive'         => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'seo_nosnippet'         => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'seo_max_image_preview' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * Render a single control for a field.
	 *
	 * @since    1.1.0
	 * @param    string    $field    Field alias.
	 * @param    mixed     $value    Current field value.
	 * @return   void
	 */
	private function render_field_control( $field, $value ) {
		$field_id   = 'lightweight_seo_archive_' . $field;
		$field_name = 'lightweight_seo_archive_' . $field;

		if ( 'seo_description' === $field ) {
			echo '<textarea id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" rows="3" class="large-text">' . esc_textarea( $value ) . '</textarea>';

			return;
		}

		if ( 'seo_max_image_preview' === $field ) {
			?>
			<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>">
				<option value="" <?php selected( $value, '' ); ?>><?php _e( 'Use global default', 'lightweight-seo' ); ?></option>
				<option value="large" <?php selected( $value, 'large' ); ?>><?php _e( 'Large', 'lightweight-seo' ); ?></option>
				<option value="standard" <?php selected( $value, 'standard' ); ?>><?php _e( 'Standard', 'lightweight-seo' ); ?></option>
				<option value="none" <?php selected( $value, 'none' ); ?>><?php _e( 'None', 'lightweight-seo' ); ?></option>
			</select>
			<?php

			return;
		}

		if ( in_array( $field, array( 'seo_noindex', 'seo_nofollow', 'seo_noarchive', 'seo_nosnippet' ), true ) ) {
			echo '<label><input type="checkbox" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" value="1" ' . checked( $value, '1', false ) . '> ' . esc_html__( 'Enabled', 'lightweight-seo' ) . '</label>';

			return;
		}

		$input_type = 'seo_canonical_url' === $field ? 'url' : 'text';
		echo '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" value="' . ( 'url' === $input_type ? esc_url( $value ) : esc_attr( $value ) ) . '" class="regular-text">';
	}

	/**
	 * Get the posted value for a field.
	 *
	 * @since    1.1.0
	 * @param    string    $field    Field alias.
	 * @return   mixed
	 */
	private function get_posted_value( $field ) {
		$post_key = 'lightweight_seo_archive_' . $field;

		if ( in_array( $field, array( 'seo_noindex', 'seo_nofollow', 'seo_noarchive', 'seo_nosnippet' ), true ) ) {
			return isset( $_POST[ $post_key ] ) ? '1' : '0';
		}

		if ( ! isset( $_POST[ $post_key ] ) ) {
			return '';
		}

		return wp_unslash( $_POST[ $post_key ] );
	}

	/**
	 * Sanitize a posted archive meta field.
	 *
	 * @since    1.1.0
	 * @param    string    $field    Field alias.
	 * @param    mixed     $value    Raw value.
	 * @return   mixed
	 */
	private function sanitize_meta_value( $field, $value ) {
		switch ( $field ) {
			case 'seo_title':
				return sanitize_text_field( $value );
			case 'seo_description':
				return sanitize_textarea_field( $value );
			case 'seo_canonical_url':
				return esc_url_raw( $value );
			case 'seo_noindex':
			case 'seo_nofollow':
			case 'seo_noarchive':
			case 'seo_nosnippet':
				return '1' === (string) $value ? '1' : '0';
			case 'seo_max_image_preview':
				return $this->settings->normalize_max_image_preview( sanitize_text_field( $value ), '' );
		}

		return sanitize_text_field( $value );
	}
}
