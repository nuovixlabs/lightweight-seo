<?php
/**
 * Experimental AI Discovery module.
 *
 * @since 1.1.0
 * @package Lightweight_SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/** Adds bounded crawler policies, a curated llms.txt, and deterministic checks. */
class Lightweight_SEO_AI_Discovery_Module {

	private $settings;
	private $api;
	private $context;

	public function __construct( $settings, $api = null, $context = 'frontend' ) {
		$this->settings = $settings;
		$this->api      = $api;
		$this->context  = $context;

		if ( 'frontend' === $context ) {
			add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 20, 2 );
			add_action( 'template_redirect', array( $this, 'maybe_render_llms_txt' ), 0 );
		}
	}

	/** Return the filtered, validated crawler-policy registry. */
	public function get_crawler_registry() {
		$registry = array(
			'OAI-SearchBot'    => array(
				'vendor'  => 'OpenAI',
				'purpose' => 'search',
			),
			'ChatGPT-User'     => array(
				'vendor'  => 'OpenAI',
				'purpose' => 'user',
			),
			'GPTBot'           => array(
				'vendor'  => 'OpenAI',
				'purpose' => 'training',
			),
			'Claude-SearchBot' => array(
				'vendor'  => 'Anthropic',
				'purpose' => 'search',
			),
			'Claude-User'      => array(
				'vendor'  => 'Anthropic',
				'purpose' => 'user',
			),
			'ClaudeBot'        => array(
				'vendor'  => 'Anthropic',
				'purpose' => 'training',
			),
			'PerplexityBot'    => array(
				'vendor'  => 'Perplexity',
				'purpose' => 'search',
			),
			'Perplexity-User'  => array(
				'vendor'  => 'Perplexity',
				'purpose' => 'user',
			),
			'Google-Extended'  => array(
				'vendor'  => 'Google',
				'purpose' => 'training',
			),
		);
		$registry = (array) apply_filters( 'lightweight_seo_ai_crawler_registry', $registry );
		$valid    = array();

		foreach ( $registry as $token => $definition ) {
			$token      = (string) $token;
			$definition = is_array( $definition ) ? $definition : array();
			$purpose    = sanitize_key( $definition['purpose'] ?? '' );

			if ( 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $token ) || ! in_array( $purpose, array( 'search', 'user', 'training' ), true ) ) {
				continue;
			}

			$valid[ $token ] = array(
				'vendor'  => sanitize_text_field( $definition['vendor'] ?? '' ),
				'purpose' => $purpose,
			);
		}

		return $valid;
	}

	/** Append explicit policies only when WordPress owns the virtual robots.txt. */
	public function filter_robots_txt( $output, $is_public ) {
		if ( ! $is_public || $this->physical_robots_exists() ) {
			return $output;
		}

		$search_allowed   = $this->settings->ai_search_crawlers_enabled();
		$training_allowed = $this->settings->ai_training_crawlers_enabled();
		$append           = array();

		foreach ( $this->get_crawler_registry() as $token => $definition ) {
			if ( $this->robots_output_contains_token( $output, $token ) ) {
				continue;
			}

			$allowed  = 'training' === $definition['purpose'] ? $training_allowed : $search_allowed;
			$append[] = 'User-agent: ' . $token;
			$append[] = ( $allowed ? 'Allow' : 'Disallow' ) . ': /';
			$append[] = '';
		}

		if ( empty( $append ) ) {
			return $output;
		}

		return rtrim( (string) $output ) . "\n\n# Lightweight SEO AI Discovery (experimental)\n" . implode( "\n", $append );
	}

	/** Return physical-file and pre-existing crawler-policy conflicts. */
	public function get_conflicts( $existing_robots_output = null ) {
		$conflicts = array(
			'physical_robots' => $this->physical_robots_exists(),
			'robots_tokens'   => array(),
			'physical_llms'   => $this->physical_llms_exists(),
			'llms_endpoint'   => $this->third_party_llms_endpoint_exists(),
		);

		if ( null === $existing_robots_output ) {
			$existing_robots_output = apply_filters( 'robots_txt', '', (bool) get_option( 'blog_public', 1 ) );
		}

		foreach ( array_keys( $this->get_crawler_registry() ) as $token ) {
			if ( $this->robots_output_contains_token( $existing_robots_output, $token ) ) {
				$conflicts['robots_tokens'][] = $token;
			}
		}

		return $conflicts;
	}

	/** Render the exact root llms.txt request and leave llms-full.txt untouched. */
	public function maybe_render_llms_txt() {
		if ( ! $this->settings->llms_txt_enabled() || ! $this->is_llms_txt_request() ) {
			return;
		}

		$conflicts = $this->get_conflicts( '' );

		if ( $conflicts['physical_llms'] || $conflicts['llms_endpoint'] ) {
			return;
		}

		$body = $this->build_llms_txt();
		$etag = '"' . md5( $body ) . '"';

		if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) ) === $etag ) {
			status_header( 304 );
			exit;
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: public, max-age=300' );
		header( 'ETag: ' . $etag );
		header( 'X-Content-Type-Options: nosniff' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text is normalized in build_llms_txt().
		exit;
	}

	/** Build a concise Markdown index from explicitly selected, public pages only. */
	public function build_llms_txt() {
		$site_name = $this->normalize_markdown_text( get_bloginfo( 'name' ) );
		$entries   = $this->get_curated_entries();
		$lines     = array(
			'# ' . ( '' !== $site_name ? $site_name : __( 'Website', 'lightweight-seo' ) ),
			'',
			'> ' . __( 'Experimental community proposal. Google Search ignores llms.txt for visibility and rankings; publication does not guarantee crawling, citation, training, or inclusion.', 'lightweight-seo' ),
		);

		if ( ! empty( $entries ) ) {
			$lines[] = '';
			$lines[] = '## ' . __( 'Authoritative pages', 'lightweight-seo' );

			foreach ( $entries as $entry ) {
				$line = '- [' . $entry['title'] . '](' . $entry['url'] . ')';

				if ( '' !== $entry['description'] ) {
					$line .= ': ' . $entry['description'];
				}

				$lines[] = $line;
			}
		}

		return implode( "\n", $lines ) . "\n";
	}

	/** Return valid curated page summaries without copying full page content. */
	public function get_curated_entries() {
		$entries = array();

		foreach ( $this->settings->get_llms_post_ids() as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $this->post_is_public( $post ) ) {
				continue;
			}

			$context = $this->api && method_exists( $this->api, 'get_object_context' ) ? (array) $this->api->get_object_context( 'post', $post_id ) : array();
			$url     = esc_url_raw( $context['canonical_url'] ?? get_permalink( $post_id ) );

			if ( empty( $context['indexable'] ) || ! $this->url_is_local_and_valid( $url ) || $this->url_is_redirected( $url ) ) {
				continue;
			}

			$title       = $this->normalize_markdown_text( $context['title'] ?? get_the_title( $post_id ) );
			$description = $this->normalize_markdown_text( $context['description'] ?? '' );

			if ( '' === $title ) {
				continue;
			}

			$entries[] = array(
				'id'          => $post_id,
				'title'       => $title,
				'url'         => $url,
				'description' => wp_trim_words( $description, 30, '…' ),
			);
		}

		return $entries;
	}

	/** Return factual, deterministic readiness checks with no ranking claims. */
	public function get_readiness_checks() {
		$conflicts     = $this->get_conflicts();
		$selected_ids  = $this->settings->get_llms_post_ids();
		$entries       = $this->get_curated_entries();
		$search_public = (bool) get_option( 'blog_public', 1 );

		return array(
			array(
				'status'  => $search_public ? 'pass' : 'fail',
				'label'   => __( 'Site crawlability', 'lightweight-seo' ),
				'details' => $search_public ? __( 'WordPress search visibility is public.', 'lightweight-seo' ) : __( 'WordPress asks search engines not to index this site.', 'lightweight-seo' ),
			),
			array(
				'status'  => $conflicts['physical_robots'] || ! empty( $conflicts['robots_tokens'] ) ? 'warn' : 'pass',
				'label'   => __( 'Crawler policy ownership', 'lightweight-seo' ),
				'details' => $conflicts['physical_robots'] ? __( 'A physical robots.txt file takes precedence; Lightweight SEO will not overwrite it.', 'lightweight-seo' ) : ( ! empty( $conflicts['robots_tokens'] ) ? __( 'Another robots.txt filter already defines one or more bundled crawler tokens.', 'lightweight-seo' ) : __( 'WordPress can safely append the configured virtual crawler policies.', 'lightweight-seo' ) ),
			),
			array(
				'status'  => count( $entries ) === count( $selected_ids ) && ! empty( $entries ) ? 'pass' : ( empty( $selected_ids ) ? 'warn' : 'fail' ),
				'label'   => __( 'Curated public pages', 'lightweight-seo' ),
				/* translators: 1: Number of valid curated pages. 2: Number of selected pages. */
				'details' => sprintf( __( '%1$d of %2$d selected pages are published, public, indexable, canonical, local, and not redirected.', 'lightweight-seo' ), count( $entries ), count( $selected_ids ) ),
			),
			array(
				'status'  => ! empty( $entries ) && count( array_filter( wp_list_pluck( $entries, 'description' ) ) ) === count( $entries ) ? 'pass' : 'warn',
				'label'   => __( 'Page snippets', 'lightweight-seo' ),
				'details' => __( 'Each curated page should have a visible, normalized description.', 'lightweight-seo' ),
			),
			array(
				'status'  => '' !== trim( get_bloginfo( 'name' ) ) && $this->settings->schema_output_enabled() ? 'pass' : 'warn',
				'label'   => __( 'Site identity', 'lightweight-seo' ),
				'details' => __( 'A site name and visible structured-data graph provide the identity signals checked here.', 'lightweight-seo' ),
			),
			array(
				'status'  => $conflicts['physical_llms'] || $conflicts['llms_endpoint'] ? 'warn' : 'pass',
				'label'   => __( 'llms.txt endpoint', 'lightweight-seo' ),
				'details' => $conflicts['physical_llms'] ? __( 'A physical llms.txt file takes precedence; Lightweight SEO will not replace it.', 'lightweight-seo' ) : ( $conflicts['llms_endpoint'] ? __( 'Another rewrite rule or integration claims the llms.txt endpoint; Lightweight SEO will not replace it.', 'lightweight-seo' ) : __( 'No physical or routed llms.txt conflict was detected.', 'lightweight-seo' ) ),
			),
		);
	}

	private function is_llms_txt_request() {
		$request_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
		$llms_path    = wp_parse_url( home_url( '/llms.txt' ), PHP_URL_PATH );

		return (string) $request_path === (string) $llms_path;
	}

	private function physical_robots_exists() {
		$path = apply_filters( 'lightweight_seo_physical_robots_path', ( defined( 'ABSPATH' ) ? ABSPATH : dirname( __DIR__ ) . '/' ) . 'robots.txt' );

		return is_file( $path );
	}

	private function physical_llms_exists() {
		$path = apply_filters( 'lightweight_seo_physical_llms_path', ( defined( 'ABSPATH' ) ? ABSPATH : dirname( __DIR__ ) . '/' ) . 'llms.txt' );

		return is_file( $path );
	}

	private function third_party_llms_endpoint_exists() {
		$rules = (array) get_option( 'rewrite_rules', array() );

		foreach ( array_keys( $rules ) as $pattern ) {
			$pattern = ltrim( (string) $pattern, '^' );

			if ( 0 === stripos( $pattern, 'llms\.txt' ) || 0 === stripos( $pattern, 'llms.txt' ) ) {
				return true;
			}
		}

		return (bool) apply_filters( 'lightweight_seo_llms_txt_conflict', false );
	}

	private function robots_output_contains_token( $output, $token ) {
		return 1 === preg_match( '/^\s*User-agent\s*:\s*' . preg_quote( $token, '/' ) . '\s*$/mi', (string) $output );
	}

	private function post_is_public( $post ) {
		if ( ! $post || 'publish' !== ( $post->post_status ?? '' ) || '' !== (string) ( $post->post_password ?? '' ) ) {
			return false;
		}

		$post_type = get_post_type_object( $post->post_type ?? '' );

		return $post_type && ( function_exists( 'is_post_type_viewable' ) ? is_post_type_viewable( $post_type ) : ! empty( $post_type->public ) );
	}

	private function url_is_local_and_valid( $url ) {
		if ( empty( $url ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		return strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) === strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
	}

	private function url_is_redirected( $url ) {
		$source = '/' . ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		$rules  = array_merge( $this->settings->get_manual_redirect_rules(), (array) get_option( 'lightweight_seo_generated_redirect_rules', array() ) );

		foreach ( $rules as $rule ) {
			if ( untrailingslashit( (string) ( $rule['source'] ?? '' ) ) === untrailingslashit( $source ) ) {
				return true;
			}
		}

		return false;
	}

	private function normalize_markdown_text( $value ) {
		$value = wp_strip_all_tags( strip_shortcodes( (string) $value ), true );
		$value = trim( preg_replace( '/\s+/', ' ', $value ) );

		return str_replace( array( '[', ']' ), array( '\\[', '\\]' ), $value );
	}
}
