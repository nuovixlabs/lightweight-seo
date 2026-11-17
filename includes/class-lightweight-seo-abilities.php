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
 * Lets agents and other clients read and fill the SEO fields of a post, a term, or an author.
 *
 * Loaded only when WordPress (6.9 or later) collects abilities. Every ability
 * checks that the current user may edit the item it is asked about, and writes
 * go through the same sanitizers as the editing screens.
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

	/** Fields that only posts have. A term or author archive has no sharing fields of its own. */
	const POST_ONLY_FIELDS = array( 'social_title', 'social_description', 'social_image' );

	/** Kinds of item that have SEO fields, and the capability that allows editing one. */
	const OBJECT_TYPES = array(
		'post' => 'edit_post',
		'term' => 'edit_term',
		'user' => 'edit_user',
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
	 * Shared term and author meta service.
	 *
	 * @var Lightweight_SEO_Archive_Meta
	 */
	private $archive_meta;

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
	 * @param    Lightweight_SEO_Post_Meta       $post_meta       Shared post meta service.
	 * @param    Lightweight_SEO_Archive_Meta    $archive_meta    Shared term and author meta service.
	 * @param    Lightweight_SEO_API|null        $api             Public API of the plugin.
	 */
	public function __construct( $post_meta, $archive_meta, $api ) {
		$this->post_meta    = $post_meta;
		$this->archive_meta = $archive_meta;
		$this->api          = $api;
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
				'description' => __( 'Read and fill the search and sharing fields of posts, pages, terms, and authors.', 'lightweight-seo' ),
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
		$target = array(
			'object_type' => array(
				'type'        => 'string',
				'enum'        => array_keys( self::OBJECT_TYPES ),
				'default'     => 'post',
				'description' => __( 'What the ID belongs to: "post" for a post, page, or other content item, "term" for a category, tag, or other term, "user" for an author.', 'lightweight-seo' ),
			),
			'object_id'   => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => __( 'ID of the post, term, or user.', 'lightweight-seo' ),
			),
		);
		$fields = array(
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
				'description' => __( 'Title used when the page is shared. Empty means the SEO title. Posts only.', 'lightweight-seo' ),
			),
			'social_description' => array(
				'type'        => 'string',
				'description' => __( 'Description used when the page is shared. Empty means the meta description. Posts only.', 'lightweight-seo' ),
			),
			'social_image'       => array(
				'type'        => 'string',
				'description' => __( 'Address of the image used when the page is shared. Empty means the featured image or the site default. Posts only.', 'lightweight-seo' ),
			),
		);
		$stored = array(
			'type'       => 'object',
			'properties' => $target + $fields,
		);
		$one    = array(
			'type'                 => 'object',
			'properties'           => $target,
			'required'             => array( 'object_id' ),
			'additionalProperties' => false,
		);

		wp_register_ability(
			'lightweight-seo/get-seo-fields',
			array(
				'label'               => __( 'Get SEO fields', 'lightweight-seo' ),
				'description'         => __( 'Returns the SEO fields saved on one post, term, or author, exactly as they are stored. An empty field means the default is used. Terms and authors have no sharing fields of their own.', 'lightweight-seo' ),
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
				'description'         => __( 'Saves SEO fields on one post, term, or author. Only the fields that are sent change; an empty string clears a field. Returns the fields as they are stored afterwards. Sharing fields can only be saved on posts.', 'lightweight-seo' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => $target + $fields,
					'required'             => array( 'object_id' ),
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
				'description'         => __( 'Returns what search engines are told about one post, term archive, or author archive after its own fields and the site settings are combined: the title and description with their variables filled in, the canonical address, the robots directives, and whether the page may be indexed.', 'lightweight-seo' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $one,
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $target + array(
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
	 * Check that the current user may edit the item an ability is asked about.
	 *
	 * @since    1.1.0
	 * @param    mixed    $input    Ability input.
	 * @return   bool
	 */
	public function can_edit( $input ) {
		list( $type, $object_id ) = $this->get_target( $input );

		return '' !== $type && $object_id > 0 && current_user_can( self::OBJECT_TYPES[ $type ], $object_id );
	}

	/**
	 * Return the stored SEO fields of a post, term, or author.
	 *
	 * @since    1.1.0
	 * @param    mixed    $input    Ability input.
	 * @return   array|WP_Error
	 */
	public function get_fields( $input ) {
		$target = $this->get_supported_target( $input );

		if ( is_wp_error( $target ) ) {
			return $target;
		}

		list( $type, $object_id ) = $target;

		$meta   = $this->read( $type, $object_id );
		$fields = array(
			'object_type' => $type,
			'object_id'   => $object_id,
		);

		foreach ( $this->get_text_fields( $type ) as $name => $field ) {
			$fields[ $name ] = (string) ( $meta[ $field ] ?? '' );
		}

		$choice                      = array_search( (string) ( $meta['seo_noindex'] ?? '' ), self::VISIBILITY, true );
		$fields['search_visibility'] = false === $choice ? 'default' : $choice;

		return $fields;
	}

	/**
	 * Save the SEO fields that were sent for a post, term, or author.
	 *
	 * @since    1.1.0
	 * @param    mixed    $input    Ability input.
	 * @return   array|WP_Error
	 */
	public function update_fields( $input ) {
		$target = $this->get_supported_target( $input );

		if ( is_wp_error( $target ) ) {
			return $target;
		}

		list( $type, $object_id ) = $target;

		$input  = (array) $input;
		$values = array();

		foreach ( self::TEXT_FIELDS as $name => $field ) {
			if ( ! isset( $input[ $name ] ) || ! is_scalar( $input[ $name ] ) ) {
				continue;
			}

			if ( 'post' !== $type && in_array( $name, self::POST_ONLY_FIELDS, true ) ) {
				/* translators: %s: name of an SEO field, such as social_title. */
				return new WP_Error( 'lightweight_seo_invalid_field', sprintf( __( 'Only posts have the field %s. Nothing was saved.', 'lightweight-seo' ), $name ) );
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
			$this->write( $type, $object_id, $field, $value );
		}

		if ( isset( $input['search_visibility'] ) && isset( self::VISIBILITY[ (string) $input['search_visibility'] ] ) ) {
			$choice = self::VISIBILITY[ (string) $input['search_visibility'] ];
			$meta   = $this->read( $type, $object_id );

			// A post hidden for the first time prints "noindex" only, not the "nofollow" kept for pages hidden before 1.1.
			if ( 'post' === $type && '1' === $choice && '1' !== (string) ( $meta['seo_noindex'] ?? '' ) && '' === (string) ( $meta['seo_nofollow'] ?? '' ) ) {
				$this->write( $type, $object_id, 'seo_nofollow', '0' );
			}

			$this->write( $type, $object_id, 'seo_noindex', $choice );
		}

		return $this->get_fields(
			array(
				'object_type' => $type,
				'object_id'   => $object_id,
			)
		);
	}

	/**
	 * Return what the public API resolves for a post, term, or author.
	 *
	 * @since    1.1.0
	 * @param    mixed    $input    Ability input.
	 * @return   array|WP_Error
	 */
	public function get_context( $input ) {
		$target = $this->get_supported_target( $input );

		if ( is_wp_error( $target ) ) {
			return $target;
		}

		list( $type, $object_id ) = $target;

		$context = $this->api ? $this->api->get_object_context( $type, $object_id ) : array();

		if ( empty( $context ) ) {
			return new WP_Error( 'lightweight_seo_unavailable', __( 'The SEO result of this item is not available.', 'lightweight-seo' ) );
		}

		return array(
			'object_type'   => $type,
			'object_id'     => $object_id,
			'title'         => (string) ( $context['title'] ?? '' ),
			'description'   => (string) ( $context['description'] ?? '' ),
			'canonical_url' => (string) ( $context['canonical_url'] ?? '' ),
			'robots'        => (string) ( $context['robots'] ?? '' ),
			'indexable'     => ! empty( $context['indexable'] ),
		);
	}

	/**
	 * Get the text fields a kind of item has.
	 *
	 * @since    1.1.0
	 * @param    string    $type    Object type.
	 * @return   array<string,string> Stored fields keyed by ability field name.
	 */
	private function get_text_fields( $type ) {
		return 'post' === $type ? self::TEXT_FIELDS : array_diff_key( self::TEXT_FIELDS, array_flip( self::POST_ONLY_FIELDS ) );
	}

	/**
	 * Read every stored SEO field of an item.
	 *
	 * @since    1.1.0
	 * @param    string    $type         Object type.
	 * @param    int       $object_id    Object ID.
	 * @return   array
	 */
	private function read( $type, $object_id ) {
		if ( 'term' === $type ) {
			return $this->archive_meta->get_term_all( $object_id );
		}

		return 'user' === $type ? $this->archive_meta->get_user_all( $object_id ) : $this->post_meta->get_all( $object_id );
	}

	/**
	 * Store one SEO field of an item. An empty value clears it.
	 *
	 * @since    1.1.0
	 * @param    string    $type         Object type.
	 * @param    int       $object_id    Object ID.
	 * @param    string    $field        Stored field name.
	 * @param    string    $value        Sanitized value.
	 * @return   void
	 */
	private function write( $type, $object_id, $field, $value ) {
		if ( 'term' === $type ) {
			$this->archive_meta->update_term( $object_id, $field, $value );
		} elseif ( 'user' === $type ) {
			$this->archive_meta->update_user( $object_id, $field, $value );
		} elseif ( '' === $value ) {
			$this->post_meta->delete( $object_id, $field );
		} else {
			$this->post_meta->update( $object_id, $field, $value );
		}
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
				// Nothing is deleted: a field is edited, and another edit puts it back. WordPress asks for the
				// DELETE method over REST when an ability is marked destructive, which an edit is not.
				'destructive' => false,
				'idempotent'  => true,
			),
			'public'       => true,
			'show_in_rest' => true,
		);
	}

	/**
	 * Read the kind of item and its ID from ability input.
	 *
	 * @since    1.1.0
	 * @param    mixed    $input    Ability input.
	 * @return   array{0:string,1:int} Object type, or an empty string when it is not one of OBJECT_TYPES, and the ID.
	 */
	private function get_target( $input ) {
		$input = is_array( $input ) || is_object( $input ) ? (array) $input : array();
		$type  = isset( $input['object_type'] ) && is_scalar( $input['object_type'] ) ? (string) $input['object_type'] : 'post';

		return array( isset( self::OBJECT_TYPES[ $type ] ) ? $type : '', absint( $input['object_id'] ?? 0 ) );
	}

	/**
	 * Read the target of ability input, when the item exists and has SEO fields.
	 *
	 * @since    1.1.0
	 * @param    mixed    $input    Ability input.
	 * @return   array{0:string,1:int}|WP_Error
	 */
	private function get_supported_target( $input ) {
		list( $type, $object_id ) = $this->get_target( $input );

		$supported = false;

		if ( 'post' === $type && $object_id ) {
			$post      = get_post( $object_id );
			$supported = ! empty( $post ) && in_array( (string) ( $post->post_type ?? '' ), $this->post_meta->get_supported_post_types(), true );
		} elseif ( 'term' === $type && $object_id ) {
			$term      = get_term( $object_id );
			$supported = ! empty( $term ) && ! is_wp_error( $term ) && in_array( (string) ( $term->taxonomy ?? '' ), $this->archive_meta->get_supported_taxonomies(), true );
		} elseif ( 'user' === $type && $object_id ) {
			$supported = false !== get_userdata( $object_id );
		}

		if ( ! $supported ) {
			return new WP_Error( 'lightweight_seo_unsupported_object', __( 'This item does not exist, or it has no SEO fields.', 'lightweight-seo' ) );
		}

		return array( $type, $object_id );
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
