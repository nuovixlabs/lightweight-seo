<?php
/**
 * Shared post meta service for Lightweight SEO.
 *
 * @since      1.0.2
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Shared post meta service.
 */
class Lightweight_SEO_Post_Meta {

	/**
	 * Registered meta keys.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      array    $meta_keys
	 */
	private $meta_keys = array(
		'seo_title'             => '_lightweight_seo_title',
		'seo_description'       => '_lightweight_seo_description',
		'seo_keywords'          => '_lightweight_seo_keywords',
		'seo_canonical_url'     => '_lightweight_seo_canonical_url',
		'seo_noindex'           => '_lightweight_seo_noindex',
		'seo_nofollow'          => '_lightweight_seo_nofollow',
		'seo_noarchive'         => '_lightweight_seo_noarchive',
		'seo_nosnippet'         => '_lightweight_seo_nosnippet',
		'seo_max_image_preview' => '_lightweight_seo_max_image_preview',
		'social_title'          => '_lightweight_seo_social_title',
		'social_description'    => '_lightweight_seo_social_description',
		'social_image'          => '_lightweight_seo_social_image',
		'social_image_id'       => '_lightweight_seo_social_image_id',
	);

	/**
	 * Cached meta values for the current request.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      array    $cache
	 */
	private $cache = array();

	/**
	 * Social image state captured before post meta updates.
	 *
	 * @since    1.0.2
	 * @access   private
	 * @var      array    $pending_social_image_updates
	 */
	private $pending_social_image_updates = array();

	/**
	 * Register post meta support.
	 *
	 * @since    1.0.2
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_filter( 'add_post_metadata', array( $this, 'remember_social_image_state' ), 10, 5 );
		add_filter( 'update_post_metadata', array( $this, 'remember_social_image_state' ), 10, 5 );
		add_action( 'added_post_meta', array( $this, 'maybe_clear_stale_social_image_id' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'maybe_clear_stale_social_image_id' ), 10, 4 );
	}

	/**
	 * Get the supported post types for SEO meta.
	 *
	 * @since    1.0.2
	 * @return   array
	 */
	public function get_supported_post_types() {
		return array_values(
			array_filter(
				apply_filters( 'lightweight_seo_supported_post_types', get_post_types( array( 'public' => true ), 'names' ) ),
				'post_type_exists'
			)
		);
	}

	/**
	 * Register SEO post meta for supported post types.
	 *
	 * @since    1.0.2
	 * @return   void
	 */
	public function register_meta() {
		$meta_config = array(
			'seo_title'             => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'seo_description'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'seo_keywords'          => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
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
			'social_title'          => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'social_description'    => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'social_image'          => array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			'social_image_id'       => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
		);

		foreach ( $this->get_supported_post_types() as $post_type ) {
			foreach ( $meta_config as $field => $args ) {
				register_post_meta(
					$post_type,
					$this->meta_keys[ $field ],
					array(
						'single'            => true,
						'show_in_rest'      => true,
						'type'              => $args['type'],
						'sanitize_callback' => $args['sanitize_callback'],
						'auth_callback'     => array( $this, 'can_edit_post_meta' ),
					)
				);
			}
		}
	}

	/**
	 * Check whether the current user can edit a post meta value.
	 *
	 * @since    1.0.2
	 * @param    bool      $allowed     Whether editing is allowed.
	 * @param    string    $meta_key    Meta key.
	 * @param    int       $post_id     Post ID.
	 * @return   bool
	 */
	public function can_edit_post_meta( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Get all registered SEO meta for a post.
	 *
	 * @since    1.0.2
	 * @param    int    $post_id    Post ID.
	 * @return   array
	 */
	public function get_all( $post_id ) {
		$post_id = (int) $post_id;

		if ( ! isset( $this->cache[ $post_id ] ) ) {
			$meta_values = array();

			foreach ( $this->meta_keys as $field => $meta_key ) {
				$meta_values[ $field ] = get_post_meta( $post_id, $meta_key, true );
			}

			$this->cache[ $post_id ] = $meta_values;
		}

		return $this->cache[ $post_id ];
	}

	/**
	 * Get a single SEO meta value.
	 *
	 * @since    1.0.2
	 * @param    int       $post_id    Post ID.
	 * @param    string    $field      Meta field alias.
	 * @return   string
	 */
	public function get( $post_id, $field ) {
		$meta_values = $this->get_all( $post_id );

		return $meta_values[ $field ] ?? '';
	}

	/**
	 * Get a registered SEO meta key by field alias.
	 *
	 * @since    1.1.0
	 * @param    string    $field    Meta field alias.
	 * @return   string
	 */
	public function get_meta_key( $field ) {
		return $this->meta_keys[ $field ] ?? '';
	}

	/**
	 * Update a single SEO meta value.
	 *
	 * @since    1.0.2
	 * @param    int       $post_id    Post ID.
	 * @param    string    $field      Meta field alias.
	 * @param    mixed     $value      Value to store.
	 * @return   bool|int
	 */
	public function update( $post_id, $field, $value ) {
		if ( ! isset( $this->meta_keys[ $field ] ) ) {
			return false;
		}

		unset( $this->cache[ (int) $post_id ] );

		return update_post_meta( $post_id, $this->meta_keys[ $field ], $value );
	}

	/**
	 * Capture the current social image state before metadata changes.
	 *
	 * @since    1.0.2
	 * @param    mixed     $check        Short-circuit value.
	 * @param    int       $post_id      Post ID.
	 * @param    string    $meta_key     Meta key.
	 * @param    mixed     $meta_value   Updated meta value.
	 * @param    mixed     $extra        Extra hook argument.
	 * @return   mixed
	 */
	public function remember_social_image_state( $check, $post_id, $meta_key, $meta_value, $extra ) {
		if ( $this->meta_keys['social_image'] !== $meta_key ) {
			return $check;
		}

		$this->pending_social_image_updates[ (int) $post_id ] = array(
			'social_image'    => get_post_meta( $post_id, $this->meta_keys['social_image'], true ),
			'social_image_id' => absint( get_post_meta( $post_id, $this->meta_keys['social_image_id'], true ) ),
		);

		return $check;
	}

	/**
	 * Keep the stored social image URL and attachment ID in sync.
	 *
	 * @since    1.0.2
	 * @param    string    $image_url             Submitted image URL.
	 * @param    int       $image_id              Submitted attachment ID.
	 * @param    string    $previous_image_url    Previously saved image URL.
	 * @param    int       $previous_image_id     Previously saved attachment ID.
	 * @return   array
	 */
	public function normalize_social_image( $image_url, $image_id, $previous_image_url = '', $previous_image_id = 0 ) {
		$image_url          = esc_url_raw( $image_url );
		$image_id           = absint( $image_id );
		$previous_image_url = esc_url_raw( $previous_image_url );
		$previous_image_id  = absint( $previous_image_id );

		if ( '' === $image_url ) {
			return array( $image_url, 0 );
		}

		if ( $image_id && $image_url !== $previous_image_url && $image_id === $previous_image_id ) {
			$attachment_url = wp_get_attachment_image_url( $image_id, 'full' );

			if ( empty( $attachment_url ) || $image_url !== $attachment_url ) {
				$image_id = 0;
			}
		}

		return array( $image_url, $image_id );
	}

	/**
	 * Clear stale attachment IDs after social image updates.
	 *
	 * @since    1.0.2
	 * @param    int       $meta_id      Meta ID.
	 * @param    int       $post_id      Post ID.
	 * @param    string    $meta_key     Meta key.
	 * @param    mixed     $meta_value   Updated meta value.
	 * @return   void
	 */
	public function maybe_clear_stale_social_image_id( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( $this->meta_keys['social_image'] !== $meta_key ) {
			return;
		}

		$post_id  = (int) $post_id;
		$previous = $this->pending_social_image_updates[ $post_id ] ?? array(
			'social_image'    => '',
			'social_image_id' => 0,
		);

		unset( $this->pending_social_image_updates[ $post_id ] );
		unset( $this->cache[ $post_id ] );

		$current_image_id              = absint( get_post_meta( $post_id, $this->meta_keys['social_image_id'], true ) );
		list( , $normalized_image_id ) = $this->normalize_social_image(
			(string) $meta_value,
			$current_image_id,
			$previous['social_image'],
			$previous['social_image_id']
		);

		if ( $normalized_image_id !== $current_image_id ) {
			update_post_meta( $post_id, $this->meta_keys['social_image_id'], $normalized_image_id );
			unset( $this->cache[ $post_id ] );
		}
	}

	/**
	 * Get the resolved social image URL for a post.
	 *
	 * @since    1.0.2
	 * @param    int    $post_id    Post ID.
	 * @return   string
	 */
	public function get_social_image_url( $post_id ) {
		$post_meta = $this->get_all( $post_id );
		$image_id  = absint( $post_meta['social_image_id'] ?? 0 );

		if ( $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'full' );

			if ( ! empty( $image_url ) ) {
				return $image_url;
			}
		}

		return $post_meta['social_image'] ?? '';
	}
}
