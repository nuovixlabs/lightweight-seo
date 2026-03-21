<?php
/**
 * Redirect and 404 monitoring service for Lightweight SEO.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Redirect and 404 monitoring service.
 */
class Lightweight_SEO_Redirects_Service {

	/**
	 * Recent 404 log option name.
	 *
	 * @since    1.1.0
	 * @var      string
	 */
	const LOG_OPTION_NAME = 'lightweight_seo_404_logs';

	/**
	 * Generated redirect rule option name.
	 *
	 * @since    1.1.0
	 * @var      string
	 */
	const GENERATED_RULES_OPTION_NAME = 'lightweight_seo_generated_redirect_rules';

	/**
	 * Maximum number of 404 log entries to retain.
	 *
	 * @since    1.1.0
	 * @var      int
	 */
	const MAX_LOG_ENTRIES = 50;

	/**
	 * Maximum number of generated redirect entries to retain.
	 *
	 * @since    1.1.0
	 * @var      int
	 */
	const MAX_GENERATED_REDIRECTS = 100;

	/**
	 * Shared settings service.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      Lightweight_SEO_Settings    $settings
	 */
	private $settings;

	/**
	 * Captured pre-update paths keyed by post ID.
	 *
	 * @since    1.1.0
	 * @access   private
	 * @var      array
	 */
	private $previous_post_paths = array();

	/**
	 * Initialize the service.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_Settings    $settings    Shared settings service.
	 */
	public function __construct( $settings, $register_hooks = true ) {
		$this->settings = $settings;

		if ( $register_hooks ) {
			add_action( 'pre_post_update', array( $this, 'capture_previous_post_path' ), 10, 2 );
			add_action( 'post_updated', array( $this, 'maybe_store_slug_redirect' ), 10, 3 );
			add_action( 'template_redirect', array( $this, 'maybe_redirect_request' ), 0 );
			add_action( 'template_redirect', array( $this, 'log_404_request' ), 99 );
		}
	}

	/**
	 * Redirect matching requests before template rendering.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function maybe_redirect_request() {
		$current_path = $this->get_current_request_path();
		$rule         = $this->find_matching_redirect( $current_path );

		if ( empty( $rule ) ) {
			return;
		}

		$target_url  = $this->resolve_redirect_target( $rule['target'] );
		$target_path = $this->normalize_path( (string) wp_parse_url( $target_url, PHP_URL_PATH ) );

		if ( empty( $target_url ) || $target_path === $current_path ) {
			return;
		}

		wp_safe_redirect( $target_url, (int) $rule['status'], 'Lightweight SEO' );
		exit;
	}

	/**
	 * Find a matching redirect rule for a request path.
	 *
	 * @since    1.1.0
	 * @param    string    $path    Request path.
	 * @return   array
	 */
	public function find_matching_redirect( $path ) {
		$normalized_path = $this->normalize_path( $path );

		foreach ( $this->get_all_redirect_rules() as $rule ) {
			if ( $rule['source'] === $normalized_path ) {
				return $rule;
			}
		}

		return array();
	}

	/**
	 * Capture the current published path before a post update changes it.
	 *
	 * @since    1.1.0
	 * @param    int      $post_id    Post ID.
	 * @param    array    $data       Pending post data.
	 * @return   void
	 */
	public function capture_previous_post_path( $post_id, $data ) {
		if ( ! $this->settings->auto_redirects_enabled() ) {
			return;
		}

		$post = get_post( $post_id );

		if ( empty( $post ) || empty( $data['post_name'] ) || $post->post_name === $data['post_name'] || ! $this->should_track_auto_redirect_for_post( $post ) ) {
			return;
		}

		$old_path = $this->get_post_path( $post );

		if ( ! empty( $old_path ) ) {
			$this->previous_post_paths[ (int) $post_id ] = $old_path;
		}
	}

	/**
	 * Create or update generated redirects when a post slug changes.
	 *
	 * @since    1.1.0
	 * @param    int        $post_id        Post ID.
	 * @param    WP_Post    $post_after     Updated post object.
	 * @param    WP_Post    $post_before    Pre-update post object.
	 * @return   void
	 */
	public function maybe_store_slug_redirect( $post_id, $post_after, $post_before ) {
		if ( ! $this->settings->auto_redirects_enabled() ) {
			return;
		}

		if ( empty( $post_after ) || empty( $post_before ) || empty( $post_before->post_name ) || empty( $post_after->post_name ) ) {
			return;
		}

		if ( $post_before->post_name === $post_after->post_name ) {
			return;
		}

		if ( ! $this->should_track_auto_redirect_for_post( $post_before ) || ! $this->should_track_auto_redirect_for_post( $post_after ) ) {
			return;
		}

		$old_path = $this->previous_post_paths[ (int) $post_id ] ?? $this->get_post_path( $post_before );
		$new_path = $this->get_post_path( $post_after );

		unset( $this->previous_post_paths[ (int) $post_id ] );

		if ( empty( $old_path ) || empty( $new_path ) || $old_path === $new_path ) {
			return;
		}

		$this->store_generated_redirect( (int) $post_id, $old_path, $new_path );
	}

	/**
	 * Log current 404 requests into a capped option-backed store.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	public function log_404_request() {
		if ( ! $this->settings->not_found_monitor_enabled() || ! function_exists( 'is_404' ) || ! is_404() ) {
			return;
		}

		$path = $this->get_current_request_path();

		if ( empty( $path ) ) {
			return;
		}

		$logs = get_option( self::LOG_OPTION_NAME, array() );
		$log  = $logs[ $path ] ?? array(
			'path'      => $path,
			'hits'      => 0,
			'last_seen' => '',
			'referer'   => '',
		);

		$log['hits']      = (int) $log['hits'] + 1;
		$log['last_seen'] = gmdate( 'c' );

		if ( empty( $log['referer'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$log['referer'] = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		$logs[ $path ] = $log;

		uasort(
			$logs,
			function ( $left, $right ) {
				return strcmp( $right['last_seen'], $left['last_seen'] );
			}
		);

		$logs = array_slice( $logs, 0, self::MAX_LOG_ENTRIES, true );

		update_option( self::LOG_OPTION_NAME, $logs );
	}

	/**
	 * Get recent 404 logs sorted by most recent hit.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	public function get_recent_not_found_logs() {
		$logs = get_option( self::LOG_OPTION_NAME, array() );

		uasort(
			$logs,
			function ( $left, $right ) {
				return strcmp( $right['last_seen'], $left['last_seen'] );
			}
		);

		return array_values( $logs );
	}

	/**
	 * Get generated redirect rules ordered by most recent update.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	public function get_generated_redirect_rules() {
		$rules = get_option( self::GENERATED_RULES_OPTION_NAME, array() );

		if ( ! is_array( $rules ) ) {
			return array();
		}

		$normalized_rules = array();

		foreach ( $rules as $rule ) {
			$normalized_rule = $this->normalize_generated_rule( $rule );

			if ( ! empty( $normalized_rule ) ) {
				$normalized_rules[] = $normalized_rule;
			}
		}

		usort(
			$normalized_rules,
			function ( $left, $right ) {
				return strcmp( $right['updated_at'], $left['updated_at'] );
			}
		);

		return $normalized_rules;
	}

	/**
	 * Get all redirect rules with manual entries taking precedence.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	public function get_all_redirect_rules() {
		return array_merge(
			$this->settings->get_manual_redirect_rules(),
			$this->get_generated_redirect_rules()
		);
	}

	/**
	 * Analyze redirect rules for loops and multi-hop chains.
	 *
	 * @since    1.1.0
	 * @return   array
	 */
	public function get_redirect_health_report() {
		$rules    = $this->get_all_redirect_rules();
		$rule_map = array();
		$chains   = array();
		$loops    = array();

		foreach ( $rules as $rule ) {
			$rule_map[ $rule['source'] ] = $rule;
		}

		foreach ( array_keys( $rule_map ) as $source ) {
			$path_report = $this->trace_redirect_path( $source, $rule_map );

			if ( ! empty( $path_report['loop'] ) ) {
				$loops[ $source ] = $path_report;

				continue;
			}

			if ( ! empty( $path_report['chain'] ) ) {
				$chains[ $source ] = $path_report;
			}
		}

		return array(
			'chains' => array_values( $chains ),
			'loops'  => array_values( $loops ),
		);
	}

	/**
	 * Resolve a redirect target into an absolute URL when needed.
	 *
	 * @since    1.1.0
	 * @param    string    $target    Normalized redirect target.
	 * @return   string
	 */
	private function resolve_redirect_target( $target ) {
		if ( 0 === strpos( $target, '/' ) ) {
			return home_url( $target );
		}

		return $target;
	}

	/**
	 * Resolve a post permalink into a normalized local path.
	 *
	 * @since    1.1.0
	 * @param    WP_Post|object    $post    Post object.
	 * @return   string
	 */
	private function get_post_path( $post ) {
		$permalink = get_permalink( $post );
		$path      = (string) wp_parse_url( (string) $permalink, PHP_URL_PATH );

		if ( empty( $path ) && ! empty( $post->post_name ) ) {
			$path = '/' . ltrim( $post->post_name, '/' );
		}

		return $this->normalize_path( $path );
	}

	/**
	 * Determine whether automatic redirect tracking applies to a post object.
	 *
	 * @since    1.1.0
	 * @param    WP_Post|object    $post    Post object.
	 * @return   bool
	 */
	private function should_track_auto_redirect_for_post( $post ) {
		return ! empty( $post->ID ) && ! empty( $post->post_name ) && 'publish' === (string) ( $post->post_status ?? '' );
	}

	/**
	 * Store or update a generated redirect rule for a post.
	 *
	 * @since    1.1.0
	 * @param    int       $post_id     Post ID.
	 * @param    string    $old_path    Previous request path.
	 * @param    string    $new_path    Current request path.
	 * @return   void
	 */
	private function store_generated_redirect( $post_id, $old_path, $new_path ) {
		$rules      = $this->get_generated_redirect_rules();
		$updated_at = gmdate( 'c' );
		$found_rule = false;

		foreach ( $rules as &$rule ) {
			if ( $rule['source'] === $new_path ) {
				$rule = array();

				continue;
			}

			if ( (int) $rule['object_id'] === (int) $post_id ) {
				$rule['target']     = $new_path;
				$rule['status']     = 301;
				$rule['updated_at'] = $updated_at;
			}

			if ( $rule['source'] === $old_path ) {
				$rule['target']     = $new_path;
				$rule['status']     = 301;
				$rule['updated_at'] = $updated_at;
				$rule['object_id']  = (int) $post_id;
				$found_rule         = true;
			}
		}
		unset( $rule );

		$rules = array_values( array_filter( $rules ) );

		if ( ! $found_rule ) {
			$rules[] = array(
				'source'     => $old_path,
				'target'     => $new_path,
				'status'     => 301,
				'object_id'  => (int) $post_id,
				'updated_at' => $updated_at,
			);
		}

		usort(
			$rules,
			function ( $left, $right ) {
				return strcmp( $right['updated_at'], $left['updated_at'] );
			}
		);

		$rules = array_slice( $rules, 0, self::MAX_GENERATED_REDIRECTS );

		update_option( self::GENERATED_RULES_OPTION_NAME, $rules );
	}

	/**
	 * Normalize a stored generated redirect rule.
	 *
	 * @since    1.1.0
	 * @param    array    $rule    Raw stored rule.
	 * @return   array
	 */
	private function normalize_generated_rule( $rule ) {
		if ( ! is_array( $rule ) ) {
			return array();
		}

		$source = $this->normalize_path( $rule['source'] ?? '' );
		$target = (string) ( $rule['target'] ?? '' );
		$status = absint( $rule['status'] ?? 301 );

		if ( empty( $source ) || empty( $target ) ) {
			return array();
		}

		if ( 0 === strpos( $target, '/' ) ) {
			$target = $this->normalize_path( $target );
		} else {
			$scheme = strtolower( (string) wp_parse_url( $target, PHP_URL_SCHEME ) );

			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
				return array();
			}
		}

		if ( ! in_array( $status, array( 301, 302, 307, 308 ), true ) ) {
			$status = 301;
		}

		return array(
			'source'     => $source,
			'target'     => $target,
			'status'     => $status,
			'object_id'  => absint( $rule['object_id'] ?? 0 ),
			'updated_at' => sanitize_text_field( $rule['updated_at'] ?? '' ),
		);
	}

	/**
	 * Trace a redirect path to detect chains and loops.
	 *
	 * @since    1.1.0
	 * @param    string    $source      Redirect source path.
	 * @param    array     $rule_map    Redirect rules keyed by source.
	 * @return   array
	 */
	private function trace_redirect_path( $source, $rule_map ) {
		$visited_paths = array();
		$sequence      = array( $source );
		$current       = $source;

		while ( isset( $rule_map[ $current ] ) ) {
			if ( isset( $visited_paths[ $current ] ) ) {
				$sequence[] = $current;

				return array(
					'source'   => $source,
					'sequence' => $sequence,
					'loop'     => true,
					'chain'    => false,
				);
			}

			$visited_paths[ $current ] = true;
			$target                    = $rule_map[ $current ]['target'];

			if ( 0 !== strpos( $target, '/' ) ) {
				break;
			}

			$target     = $this->normalize_path( $target );
			$sequence[] = $target;
			$current    = $target;

			if ( count( $sequence ) > 10 ) {
				return array(
					'source'   => $source,
					'sequence' => $sequence,
					'loop'     => false,
					'chain'    => true,
				);
			}
		}

		return array(
			'source'   => $source,
			'sequence' => $sequence,
			'loop'     => false,
			'chain'    => count( $sequence ) > 2,
		);
	}

	/**
	 * Get the current request path.
	 *
	 * @since    1.1.0
	 * @return   string
	 */
	private function get_current_request_path() {
		$request_uri  = $_SERVER['REQUEST_URI'] ?? '/';
		$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

		return $this->normalize_path( $request_path );
	}

	/**
	 * Normalize a request path for matching and storage.
	 *
	 * @since    1.1.0
	 * @param    string    $path    Raw request path.
	 * @return   string
	 */
	private function normalize_path( $path ) {
		$normalized_path = trim( (string) $path );

		if ( '' === $normalized_path ) {
			return '/';
		}

		$normalized_path = '/' . ltrim( $normalized_path, '/' );

		if ( '/' !== $normalized_path ) {
			$normalized_path = rtrim( $normalized_path, '/' );
		}

		return $normalized_path;
	}
}
