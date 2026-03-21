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
		'seo_title'          => '_lightweight_seo_title',
		'seo_description'    => '_lightweight_seo_description',
		'seo_keywords'       => '_lightweight_seo_keywords',
		'seo_noindex'        => '_lightweight_seo_noindex',
		'social_title'       => '_lightweight_seo_social_title',
		'social_description' => '_lightweight_seo_social_description',
		'social_image'       => '_lightweight_seo_social_image',
		'social_image_id'    => '_lightweight_seo_social_image_id',
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
	 * Register post meta support.
	 *
	 * @since    1.0.2
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
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
			'seo_title'          => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'seo_description'    => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'seo_keywords'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'seo_noindex'        => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'social_title'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'social_description' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'social_image'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			'social_image_id'    => array(
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
