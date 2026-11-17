<?php
/**
 * IndexNow module for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Tells search engines that support IndexNow when a page is published, changed, or removed.
 *
 * This is the one part of the plugin that makes a request from the server to
 * another service, so it is off by default and loads nothing until it is
 * switched on. It keeps no queue and schedules nothing: the addresses changed
 * by a request are sent once, at the end of that request, without waiting for
 * an answer.
 */
class Lightweight_SEO_IndexNow_Module {

	/** Where addresses are submitted. Every participating search engine shares what it receives here. */
	const ENDPOINT = 'https://api.indexnow.org/indexnow';

	/** Option that remembers what was last sent, for the settings screen. */
	const LAST_OPTION = 'lightweight_seo_indexnow_last';

	/** Most addresses sent by one request. */
	const MAX_URLS = 100;

	/**
	 * Shared settings service.
	 *
	 * @var Lightweight_SEO_Settings
	 */
	private $settings;

	/**
	 * Public API of the plugin.
	 *
	 * @var Lightweight_SEO_API|null
	 */
	private $api;

	/**
	 * Posts saved during this request, keyed by ID.
	 *
	 * @var array<int,bool>
	 */
	private $changed = array();

	/**
	 * Public addresses that posts had before this request changed them, keyed by post ID.
	 *
	 * @var array<int,string>
	 */
	private $previous = array();

	/**
	 * Initialize the module.
	 *
	 * Visitor requests only answer the key file. Every context in which a post can
	 * be saved collects what changed. Passing false registers no hooks.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings    $settings    Shared settings service.
	 * @param    Lightweight_SEO_API|null    $api         Public API of the plugin.
	 * @param    string|false                $context     Request context, or false for use by the settings screen.
	 */
	public function __construct( $settings, $api = null, $context = 'frontend' ) {
		$this->settings = $settings;
		$this->api      = $api;

		if ( 'frontend' === $context ) {
			add_action( 'template_redirect', array( $this, 'maybe_render_key_file' ), 0 );
		} elseif ( in_array( $context, array( 'admin', 'editor', 'rest', 'cron' ), true ) ) {
			add_action( 'pre_post_update', array( $this, 'remember_previous_address' ), 10, 1 );
			add_action( 'transition_post_status', array( $this, 'collect_changed_post' ), 10, 3 );
			add_action( 'shutdown', array( $this, 'submit_changes' ), 5 );
		}
	}

	/**
	 * Get the key that proves this site sends its own addresses.
	 *
	 * @since    1.1.0
	 * @return   string Key, or an empty string when none is stored yet.
	 */
	public function get_key() {
		return Lightweight_SEO_Settings::normalize_indexnow_key( $this->settings->get( 'indexnow_key', '' ) );
	}

	/**
	 * Get the address of the key file.
	 *
	 * @since    1.1.0
	 * @return   string Address, or an empty string when no key is stored yet.
	 */
	public function get_key_url() {
		$key = $this->get_key();

		return '' === $key ? '' : home_url( '/' . $key . '.txt' );
	}

	/**
	 * Answer a request for the key file with the key. No file is written.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function maybe_render_key_file() {
		$key_url = $this->get_key_url();

		if ( '' === $key_url ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only the parsed path is compared against the key file's path.

		if ( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) !== (string) wp_parse_url( $key_url, PHP_URL_PATH ) ) {
			return;
		}

		if ( $this->send_key_file( $this->get_key() ) ) {
			exit;
		}
	}

	/**
	 * Print the key file. Kept separate so the response can be observed without ending the process.
	 *
	 * @since    1.1.0
	 * @param    string    $key    Key to print.
	 * @return   bool Whether the response was sent.
	 */
	protected function send_key_file( $key ) {
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );

		echo esc_html( $key );

		return true;
	}

	/**
	 * Remember the public address of a published post before an update changes or removes it.
	 *
	 * @since    1.1.0
	 * @param    int    $post_id    Post ID.
	 * @return   void
	 */
	public function remember_previous_address( $post_id ) {
		$post = get_post( $post_id );

		if ( ! empty( $post ) && 'publish' === (string) ( $post->post_status ?? '' ) && ! isset( $this->previous[ (int) $post_id ] ) ) {
			$this->previous[ (int) $post_id ] = (string) get_permalink( $post );
		}
	}

	/**
	 * Note a post that was published, changed while published, or taken offline.
	 *
	 * @since    1.1.0
	 * @param    string     $new_status    Status after the change.
	 * @param    string     $old_status    Status before the change.
	 * @param    WP_Post    $post          Post object.
	 * @return   void
	 */
	public function collect_changed_post( $new_status, $old_status, $post ) {
		if ( empty( $post->ID ) || ( 'publish' !== $new_status && 'publish' !== $old_status ) ) {
			return;
		}

		if ( wp_is_post_revision( $post ) || ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post ) ) ) {
			return;
		}

		$this->changed[ (int) $post->ID ] = true;
	}

	/**
	 * Send the addresses this request changed, once, without waiting for an answer.
	 *
	 * Runs at the end of the request, after the editor has saved the SEO fields
	 * too, so a page that was just hidden is not announced.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function submit_changes() {
		if ( empty( $this->changed ) || '' !== $this->get_blocking_reason() ) {
			return;
		}

		$urls = array();

		foreach ( array_keys( $this->changed ) as $post_id ) {
			$post     = get_post( $post_id );
			$previous = (string) ( $this->previous[ $post_id ] ?? '' );
			$current  = ! empty( $post ) && $this->is_announceable( $post ) ? (string) get_permalink( $post ) : '';

			if ( '' !== $current ) {
				$urls[] = $current;
			}

			// An address that stopped working is announced too, unless the page was hidden from search engines by choice.
			if ( '' !== $previous && $previous !== $current && ! empty( $post ) && ! $this->is_hidden_by_choice( $post ) ) {
				$urls[] = $previous;
			}
		}

		$this->changed  = array();
		$this->previous = array();

		$this->submit( $urls, false );
	}

	/**
	 * Submit addresses to IndexNow.
	 *
	 * @since    1.1.0
	 * @param    string[]    $urls        Addresses on this site.
	 * @param    bool        $blocking    Whether to wait for the answer.
	 * @return   array|WP_Error|null The response, or null when nothing was sent.
	 */
	public function submit( $urls, $blocking ) {
		$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$urls = array_values(
			array_unique(
				array_filter(
					(array) apply_filters( 'lightweight_seo_indexnow_urls', $urls ),
					static function ( $url ) use ( $host ) {
						return is_string( $url ) && strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) === $host;
					}
				)
			)
		);
		$urls = array_slice( $urls, 0, self::MAX_URLS );

		if ( empty( $urls ) || '' !== $this->get_blocking_reason() ) {
			return null;
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'blocking'    => (bool) $blocking,
				'timeout'     => $blocking ? 10 : 3,
				'redirection' => 0,
				'headers'     => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'        => wp_json_encode(
					array(
						'host'        => $host,
						'key'         => $this->get_key(),
						'keyLocation' => $this->get_key_url(),
						'urlList'     => $urls,
					)
				),
			)
		);

		update_option(
			self::LAST_OPTION,
			array(
				'time'  => time(),
				'count' => count( $urls ),
				'url'   => $urls[0],
			),
			false
		);

		return $response;
	}

	/**
	 * Say why nothing is sent from this site right now.
	 *
	 * @since    1.1.0
	 * @return   string Reason for the administrator, or an empty string when sending is possible.
	 */
	public function get_blocking_reason() {
		if ( '' === $this->get_key() ) {
			return __( 'No key has been created yet. Save this tab with the module switched on.', 'lightweight-seo' );
		}

		if ( '0' === (string) get_option( 'blog_public', '1' ) ) {
			return __( 'WordPress is set to discourage search engines from indexing this site.', 'lightweight-seo' );
		}

		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

		if ( 'production' !== $environment ) {
			/* translators: %s: environment type of the site, such as staging. */
			return sprintf( __( 'This is a %s site, not a production site.', 'lightweight-seo' ), $environment );
		}

		if ( ! $this->is_public_host( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) ) {
			return __( 'The address of this site is one search engines cannot reach, such as localhost.', 'lightweight-seo' );
		}

		return '';
	}

	/**
	 * Say whether a host name is one a search engine could reach.
	 *
	 * @since    1.1.0
	 * @param    string    $host    Host name of the site.
	 * @return   bool
	 */
	private function is_public_host( $host ) {
		$host = strtolower( trim( $host, '[]' ) );

		// A name without a dot, such as localhost, and a bare IP address are never public sites.
		if ( '' === $host || false === strpos( $host, '.' ) || false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		return 1 !== preg_match( '/\.(?:local|localhost|test|example|invalid|internal|lan|home\.arpa)$/', $host );
	}

	/**
	 * Say whether a post is public and may be shown by search engines.
	 *
	 * @since    1.1.0
	 * @param    WP_Post|object    $post    Post object.
	 * @return   bool
	 */
	private function is_announceable( $post ) {
		if ( 'publish' !== (string) ( $post->post_status ?? '' ) || '' !== (string) ( $post->post_password ?? '' ) ) {
			return false;
		}

		$type = get_post_type_object( (string) ( $post->post_type ?? '' ) );

		if ( empty( $type ) || ! is_post_type_viewable( $type ) ) {
			return false;
		}

		return $this->api ? (bool) $this->api->is_indexable( 'post', (int) $post->ID ) : ! $this->is_hidden_by_choice( $post );
	}

	/**
	 * Say whether a post, or its content type, is set to stay out of search results.
	 *
	 * @since    1.1.0
	 * @param    WP_Post|object    $post    Post object.
	 * @return   bool
	 */
	private function is_hidden_by_choice( $post ) {
		return $this->settings->resolve_noindex(
			get_post_meta( (int) $post->ID, '_lightweight_seo_noindex', true ),
			$this->settings->is_post_type_noindex( (string) ( $post->post_type ?? '' ) )
		);
	}
}
