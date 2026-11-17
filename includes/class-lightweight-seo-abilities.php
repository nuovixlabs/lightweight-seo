<?php
/**
 * Abilities API registration for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Lets agents and other clients read and fill the SEO fields of a post.
 *
 * Loaded only when WordPress (6.9 or later) collects abilities. Every ability
 * checks that the current user may edit the post it is asked about, and writes
 * go through the same sanitizers as the editor panel.
 */
class Lightweight_SEO_Abilities {

	/** Category the abilities are listed under. */
	const CATEGORY = 'lightweight-seo';

	/** Ability input and output names, and the stored field each one maps to. */
	const TEXT_FIELDS = array(
		'title'              => 'seo_title',
		'description'        => 'seo_description',
		'canonical_url'      => 'seo_canonical_url',
		'social_title'       => 'social_title',
		'social_description' => 'social_description',
		'social_image'       => 'social_image',
	);

	/** Search visibility choices, and the value each one is stored as. */
	const VISIBILITY = array(
		'default' => '0',
		'show'    => Lightweight_SEO_Settings::INDEX_OVERRIDE,
		'hide'    => '1',
	);

	/**
	 * Shared post meta service.
	 *
	 * @var Lightweight_SEO_Post_Meta
	 */
	private $post_meta;

	/**
	 * Public API of the plugin.
	 *
	 * @var Lightweight_SEO_API|null
	 */
	private $api;

	/**
	 * Initialize the abilities.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Post_Meta    $post_meta    Shared post meta service.
	 * @param    Lightweight_SEO_API|null     $api          Public API of the plugin.
	 */
	public function __construct( $post_meta, $api ) {
		$this->post_meta = $post_meta;
		$this->api       = $api;
	}

	/**
	 * Register the category the abilities belong to.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function register_category() {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Lightweight SEO', 'lightweight-seo' ),
				'description' => __( 'Read and fill the search and sharing fields of posts and pages.', 'lightweight-seo' ),
			)
		);
	}

	/**
	 * Register the abilities.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function register() {
		$post_id = array(
			'type'        => 'integer',
			'minimum'     => 1,
			'description' => __( 'ID of the post, page, or other content item.', 'lightweight-seo' ),
		);
		$fields  = array(
			'title'              => array(
				'type'        => 'string',
				/* translators: %s: examples of title variables, such as %title%. */
				'description' => sprintf( __( 'SEO title. Empty means the title format of the content type is used. May hold variables such as %s.', 'lightweight-seo' ), '%title%, %sitename%, %sep%' ),
			),
			'description'        => array(
				'type'        => 'string',
				'description' => __( 'Meta description. Empty means a summary of the excerpt or content is used.', 'lightweight-seo' ),
			),
			'canonical_url'      => array(
				'type'        => 'string',
				'description' => __( 'Canonical address. Empty means the page\'s own address.', 'lightweight-seo' ),
			),
			'search_visibility'  => array(
				'type'        => 'string',
				'enum'        => array_keys( self::VISIBILITY ),
				'description' => __( 'Whether search engines may show the page: "default" follows its content type, "show" and "hide" are the page\'s own choice.', 'lightweight-seo' ),
			),
			'social_title'       => array(
				'type'        => 'string',
				'description' => __( 'Title used when the page is shared. Empty means the SEO title.', 'lightweight-seo' ),
			),
			'social_description' => array(
				'type'        => 'string',
				'description' => __( 'Description used when the page is shared. Empty means the meta description.', 'lightweight-seo' ),
			),
			'social_image'       => array(
				'type'        => 'string',
				'description' => __( 'Address of the image used when the page is shared. Empty means the featured image or the site default.', 'lightweight-seo' ),
			),
		);
		$stored  = array(
			'type'       => 'object',
			'properties' => array( 'post_id' => $post_id ) + $fields,
		);
		$one     = array(
			'type'                 => 'object',
			'properties'           => array( 'post_id' => $post_id ),
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
		);

		wp_register_ability(
			'lightweight-seo/get-seo-fields',
			array(
				'label'               => __( 'Get SEO fields', 'lightweight-seo' ),
				'description'         => __( 'Returns the SEO fields saved on one post or page, exactly as they are stored. An empty field means the page uses the default.', 'lightweight-seo' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $one,
				'output_schema'       => $stored,
				'execute_callback'    => array( $this, 'get_fields' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'meta'                => $this->get_meta( true ),
			)
		);

		wp_register_ability(
			'lightweight-seo/update-seo-fields',
			array(
				'label'               => __( 'Update SEO fields', 'lightweight-seo' ),
				'description'         => __( 'Saves SEO fields on one post or page. Only the fields that are sent change; an empty string clears a field. Returns the fields as they are stored afterwards.', 'lightweight-seo' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'post_id' => $post_id ) + $fields,
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => $stored,
				'execute_callback'    => array( $this, 'update_fields' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'meta'                => $this->get_meta( false ),
			)
		);

		wp_register_ability(
			'lightweight-seo/get-seo-context',
			array(
				'label'               => __( 'Get SEO result', 'lightweight-seo' ),
				'description'         => __( 'Returns what search engines are told about one post or page after its own fields and the site settings are combined: the title and description in use, the canonical address, the robots directives, and whether the page may be indexed. Variables in the title and description are returned as written, not filled in.', 'lightweight-seo' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $one,
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'       => $post_id,
						'title'         => array( 'type' => 'string' ),
						'description'   => array( 'type' => 'string' ),
						'canonical_url' => array( 'type' => 'string' ),
						'robots'        => array( 'type' => 'string' ),
						'indexable'     => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => array( $this, 'get_context' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'meta'                => $this->get_meta( true ),
			)
		);
	}

	/**
	 * Check that the current user may edit the post an ability is asked about.
	 *
	 * @since    1.1.0
	 * @param    mixed    $input    Ability input.
	 * @return   bool
	 */
	public function can_edit( $input ) {
		$post_id = $this->get_post_id( $input );

		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Return the stored SEO fields of a post.
	 *
	 * @since    1.1.0
	 * @param    mixed    $input    Ability input.
	 * @return   array|WP_Error
	 */
	public function get_fields( $input ) {
		$post_id = $this->get_supported_post_id( $input );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$meta   = $this->post_meta->get_all( $post_id );
		$fields = array( 'post_id' => $post_id );

		foreach ( self::TEXT_FIELDS as $name => $field ) {
			$fields[ $name ] = (string) ( $meta[ $field ] ?? '' );
		}

		$choice                      = array_search( (string) ( $meta['seo_noindex'] ?? '' ), self::VISIBILITY, true );
		$fields['search_visibility'] = false === $choice ? 'default' : $choice;

		return $fields;
	}

	/**
	 * Save the SEO fields that were sent for a post.
	 *
	 * @since    1.1.0
	 * @param    mixed    $input    Ability input.
	 * @return   array|WP_Error
	 */
	public function update_fields( $input ) {
		$post_id = $this->get_supported_post_id( $input );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$input  = (array) $input;
		$values = array();

		foreach ( self::TEXT_FIELDS as $name => $field ) {
			if ( ! isset( $input[ $name ] ) || ! is_scalar( $input[ $name ] ) ) {
				continue;
			}

			$value = $this->sanitize_field( $name, (string) $input[ $name ] );

			if ( null === $value ) {
				/* translators: %s: name of an SEO field, such as canonical_url. */
				return new WP_Error( 'lightweight_seo_invalid_field', sprintf( __( 'The value for %s is not a web address. Nothing was saved.', 'lightweight-seo' ), $name ) );
			}

			$values[ $field ] = $value;
		}

		// Everything is checked before anything is written, so a refused request changes nothing.
		foreach ( $values as $field => $value ) {
			if ( '' === $value ) {
				$this->post_meta->delete( $post_id, $field );
			} else {
				$this->post_meta->update( $post_id, $field, $value );
			}
		}

		if ( isset( $input['search_visibility'] ) && isset( self::VISIBILITY[ (string) $input['search_visibility'] ] ) ) {
			$choice = self::VISIBILITY[ (string) $input['search_visibility'] ];
			$meta   = $this->post_meta->get_all( $post_id );

			// A page hidden for the first time prints "noindex" only, not the "nofollow" kept for pages hidden before 1.1.
			if ( '1' === $choice && '1' !== (string) ( $meta['seo_noindex'] ?? '' ) && '' === (string) ( $meta['seo_nofollow'] ?? '' ) ) {
				$this->post_meta->update( $post_id, 'seo_nofollow', '0' );
			}

			$this->post_meta->update( $post_id, 'seo_noindex', $choice );
		}

		return $this->get_fields( array( 'post_id' => $post_id ) );
	}

	/**
	 * Return what the public API resolves for a post.
	 *
	 * @since    1.1.0
	 * @param    mixed    $input    Ability input.
	 * @return   array|WP_Error
	 */
	public function get_context( $input ) {
		$post_id = $this->get_supported_post_id( $input );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$context = $this->api ? $this->api->get_object_context( 'post', $post_id ) : array();

		if ( empty( $context ) ) {
			return new WP_Error( 'lightweight_seo_unavailable', __( 'The SEO result of this item is not available.', 'lightweight-seo' ) );
		}

		return array(
			'post_id'       => $post_id,
			'title'         => (string) ( $context['title'] ?? '' ),
			'description'   => (string) ( $context['description'] ?? '' ),
			'canonical_url' => (string) ( $context['canonical_url'] ?? '' ),
			'robots'        => (string) ( $context['robots'] ?? '' ),
			'indexable'     => ! empty( $context['indexable'] ),
		);
	}

	/**
	 * Describe how an ability behaves, and make it available to REST and agent clients.
	 *
	 * @since    1.1.0
	 * @param    bool    $reads_only    Whether the ability only reads.
	 * @return   array
	 */
	private function get_meta( $reads_only ) {
		return array(
			'annotations'  => array(
				'readonly'    => $reads_only,
				// An update replaces what a field held before.
				'destructive' => ! $reads_only,
				'idempotent'  => true,
			),
			'public'       => true,
			'show_in_rest' => true,
		);
	}

	/**
	 * Read the post ID from ability input.
	 *
	 * @since    1.1.0
	 * @param    mixed    $input    Ability input.
	 * @return   int
	 */
	private function get_post_id( $input ) {
		return is_array( $input ) || is_object( $input ) ? absint( ( (array) $input )['post_id'] ?? 0 ) : 0;
	}

	/**
	 * Read the post ID from ability input, when the post has SEO fields.
	 *
	 * @since    1.1.0
	 * @param    mixed    $input    Ability input.
	 * @return   int|WP_Error
	 */
	private function get_supported_post_id( $input ) {
		$post_id = $this->get_post_id( $input );
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( empty( $post ) || ! in_array( (string) ( $post->post_type ?? '' ), $this->post_meta->get_supported_post_types(), true ) ) {
			return new WP_Error( 'lightweight_seo_unsupported_post', __( 'This item does not exist, or its content type has no SEO fields.', 'lightweight-seo' ) );
		}

		return $post_id;
	}

	/**
	 * Sanitize one field the way the editor panel does.
	 *
	 * @since    1.1.0
	 * @param    string    $name     Ability field name.
	 * @param    string    $value    Submitted value.
	 * @return   string|null Sanitized value, or null when a web address was expected and not given.
	 */
	private function sanitize_field( $name, $value ) {
		if ( in_array( $name, array( 'canonical_url', 'social_image' ), true ) ) {
			$value  = trim( $value );
			$url    = esc_url_raw( $value );
			$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );

			if ( '' !== $value && ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
				return null;
			}

			return $url;
		}

		return in_array( $name, array( 'description', 'social_description' ), true )
			? Lightweight_SEO_Text::sanitize_textarea_template( $value )
			: Lightweight_SEO_Text::sanitize_template( $value );
	}
}
