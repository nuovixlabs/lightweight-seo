<?php
/**
 * Sitemap integration service for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Sitemap integration service.
 */
class Lightweight_SEO_Sitemap_Service {

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings    $settings
	 */
	private $settings;

	/**
	 * Shared post meta service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Post_Meta    $post_meta
	 */
	private $post_meta;

	/**
	 * Shared archive meta service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Archive_Meta    $archive_meta
	 */
	private $archive_meta;

	/**
	 * Initialize the service.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings        $settings        Shared settings service.
	 * @param    Lightweight_SEO_Post_Meta       $post_meta       Shared post meta service.
	 * @param    Lightweight_SEO_Archive_Meta    $archive_meta    Shared archive meta service.
	 */
	public function __construct( $settings, $post_meta, $archive_meta ) {
		$this->settings     = $settings;
		$this->post_meta    = $post_meta;
		$this->archive_meta = $archive_meta;

		add_action( 'init', array( $this, 'register_image_sitemap_provider' ), 25 );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'filter_posts_query_args' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies_query_args', array( $this, 'filter_taxonomies_query_args' ), 10, 2 );
		add_filter( 'wp_sitemaps_users_query_args', array( $this, 'filter_users_query_args' ) );
	}

	/**
	 * Register the attachment image sitemap provider with WordPress core.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function register_image_sitemap_provider() {
		if ( ! $this->settings->image_sitemaps_enabled() || ! class_exists( 'WP_Sitemaps_Provider' ) || ! function_exists( 'wp_register_sitemap_provider' ) ) {
			return;
		}

		if ( ! class_exists( 'Lightweight_SEO_Image_Sitemap_Provider' ) ) {
			require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-image-sitemap-provider.php';
		}

		wp_register_sitemap_provider(
			'lightweightseoimages',
			new Lightweight_SEO_Image_Sitemap_Provider( $this->settings )
		);
	}

	/**
	 * Exclude noindexed content from WordPress core sitemap post queries.
	 *
	 * @since    1.1.0
	 * @param    array     $args         Current query arguments.
	 * @param    string    $post_type    Post type being queried.
	 * @return   array
	 */
	public function filter_posts_query_args( $args, $post_type ) {
		if ( ! $this->settings->exclude_noindex_from_sitemaps_enabled() ) {
			return $args;
		}

		return $this->append_noindex_meta_query(
			$args,
			$this->post_meta->get_meta_key( 'seo_noindex' )
		);
	}

	/**
	 * Exclude noindexed terms from taxonomy sitemap queries.
	 *
	 * @since    1.1.0
	 * @param    array     $args        Current query arguments.
	 * @param    string    $taxonomy    Taxonomy being queried.
	 * @return   array
	 */
	public function filter_taxonomies_query_args( $args, $taxonomy ) {
		if ( ! $this->settings->exclude_noindex_from_sitemaps_enabled() ) {
			return $args;
		}

		return $this->append_noindex_meta_query(
			$args,
			$this->archive_meta->get_meta_key( 'seo_noindex' )
		);
	}

	/**
	 * Exclude noindexed author archives from user sitemap queries.
	 *
	 * @since    1.1.0
	 * @param    array    $args    Current user query arguments.
	 * @return   array
	 */
	public function filter_users_query_args( $args ) {
		if ( ! $this->settings->exclude_noindex_from_sitemaps_enabled() ) {
			return $args;
		}

		return $this->append_noindex_meta_query(
			$args,
			$this->archive_meta->get_meta_key( 'seo_noindex' )
		);
	}

	/**
	 * Append a "not noindexed" constraint to a sitemap meta query.
	 *
	 * @since    1.1.0
	 * @param    array     $args        Current query arguments.
	 * @param    string    $meta_key    Registered noindex meta key.
	 * @return   array
	 */
	private function append_noindex_meta_query( $args, $meta_key ) {
		$noindex_query = array(
			'relation' => 'OR',
			array(
				'key'     => $meta_key,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => $meta_key,
				'value'   => '1',
				'compare' => '!=',
			),
		);

		if ( empty( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
			$args['meta_query'] = array( $noindex_query );

			return $args;
		}

		$args['meta_query'] = array(
			'relation' => 'AND',
			$args['meta_query'],
			$noindex_query,
		);

		return $args;
	}
}
