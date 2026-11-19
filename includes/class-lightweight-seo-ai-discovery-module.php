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
	private $post_meta;

	/**
	 * @param object      $settings  Shared settings service.
	 * @param object|null $api       Public API facade.
	 * @param string      $context   Request context.
	 * @param object|null $post_meta The plugin's shared post meta service. Without it, a static front page is listed as the public API describes it.
	 */
	public function __construct( $settings, $api = null, $context = 'frontend', $post_meta = null ) {
		$this->settings  = $settings;
		$this->api       = $api;
		$this->context   = $context;
		$this->post_meta = $post_meta;

		if ( 'frontend' === $context ) {
			add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 20, 2 );
			add_action( 'template_redirect', array( $this, 'maybe_render_llms_txt' ), 0 );
		}
	}

	/**
	 * Return the filtered, validated crawler-policy registry.
	 *
	 * Each token, its purpose, and its source page are recorded in docs/ai-crawlers.md.
	 */
	public function get_crawler_registry() {
		$registry = array(
			'OAI-SearchBot'        => array(
				'vendor'  => 'OpenAI',
				'purpose' => 'search',
			),
			'ChatGPT-User'         => array(
				'vendor'  => 'OpenAI',
				'purpose' => 'user',
			),
			'GPTBot'               => array(
				'vendor'  => 'OpenAI',
				'purpose' => 'training',
			),
			'Claude-SearchBot'     => array(
				'vendor'  => 'Anthropic',
				'purpose' => 'search',
			),
			'Claude-User'          => array(
				'vendor'  => 'Anthropic',
				'purpose' => 'user',
			),
			'ClaudeBot'            => array(
				'vendor'  => 'Anthropic',
				'purpose' => 'training',
			),
			'PerplexityBot'        => array(
				'vendor'  => 'Perplexity',
				'purpose' => 'search',
			),
			'Perplexity-User'      => array(
				'vendor'  => 'Perplexity',
				'purpose' => 'user',
			),
			'Google-Extended'      => array(
				'vendor'  => 'Google',
				'purpose' => 'training',
			),
			'Applebot-Extended'    => array(
				'vendor'  => 'Apple',
				'purpose' => 'training',
			),
			'Meta-ExternalAgent'   => array(
				'vendor'  => 'Meta',
				'purpose' => 'training',
			),
			'Meta-WebIndexer'      => array(
				'vendor'  => 'Meta',
				'purpose' => 'search',
			),
			'Meta-ExternalFetcher' => array(
				'vendor'  => 'Meta',
				'purpose' => 'user',
			),
			'Amazonbot'            => array(
				'vendor'  => 'Amazon',
				'purpose' => 'training',
			),
			'Amzn-SearchBot'       => array(
				'vendor'  => 'Amazon',
				'purpose' => 'search',
			),
			'Amzn-User'            => array(
				'vendor'  => 'Amazon',
				'purpose' => 'user',
			),
			'DuckAssistBot'        => array(
				'vendor'  => 'DuckDuckGo',
				'purpose' => 'search',
			),
			'MistralAI-User'       => array(
				'vendor'  => 'Mistral AI',
				'purpose' => 'user',
			),
			'MistralAI-Index'      => array(
				'vendor'  => 'Mistral AI',
				'purpose' => 'search',
			),
			'MistralAI-Training'   => array(
				'vendor'  => 'Mistral AI',
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

		$output = $this->add_content_signal( (string) $output );

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

	/**
	 * Return the content signals this site states, in the order of the Content Signals reference.
	 *
	 * Search is always allowed here; ai-input follows the AI search switch and ai-train the training switch.
	 *
	 * @since 1.2.0
	 * @return string Such as "ai-train=no, search=yes, ai-input=yes".
	 */
	public function get_content_signal() {
		return sprintf(
			'ai-train=%1$s, search=yes, ai-input=%2$s',
			$this->settings->ai_training_crawlers_enabled() ? 'yes' : 'no',
			$this->settings->ai_search_crawlers_enabled() ? 'yes' : 'no'
		);
	}

	/**
	 * Put one Content-Signal line in the group for every crawler, under its User-agent lines.
	 *
	 * contentsignals.org and Cloudflare's managed robots.txt place the line inside the
	 * "User-agent: *" group, directly under the User-agent line and before its rules. Lines
	 * that share that group's User-agent lines are kept together, so no rule changes group.
	 * A file that already states a content signal is left alone.
	 */
	private function add_content_signal( $output ) {
		if ( ! method_exists( $this->settings, 'content_signals_enabled' ) || ! $this->settings->content_signals_enabled() || 1 === preg_match( '/^\s*Content-Signal\s*:/mi', $output ) ) {
			return $output;
		}

		$signal = array(
			'# Content signals, as defined at https://contentsignals.org/',
			'Content-Signal: ' . $this->get_content_signal(),
		);
		$lines  = preg_split( '/\r\n|\n|\r/', $output );
		$star   = null;

		foreach ( $lines as $index => $line ) {
			if ( 1 === preg_match( '/^\s*User-agent\s*:\s*\*\s*(?:#.*)?$/i', $line ) ) {
				$star = $index;
				break;
			}
		}

		if ( null === $star ) {
			// No group for every crawler yet: add one that allows what was allowed anyway.
			return ( '' === trim( $output ) ? '' : rtrim( $output ) . "\n\n" ) . "User-agent: *\n" . implode( "\n", $signal ) . "\nAllow: /\n";
		}

		$last_agent = $star;

		while ( isset( $lines[ $last_agent + 1 ] ) && 1 === preg_match( '/^\s*User-agent\s*:/i', $lines[ $last_agent + 1 ] ) ) {
			++$last_agent;
		}

		array_splice( $lines, $last_agent + 1, 0, $signal );

		return implode( "\n", $lines );
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

		header( 'Cache-Control: public, max-age=300' );
		header( 'ETag: ' . $etag );

		if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) ) === $etag ) {
			status_header( 304 );
			exit;
		}

		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text is normalized in build_llms_txt().
		exit;
	}

	/** Build a concise Markdown index from explicitly selected, public pages only. */
	public function build_llms_txt() {
		$site_name = $this->normalize_markdown_text( get_bloginfo( 'name' ) );
		$summary   = $this->get_site_summary();
		$entries   = $this->get_curated_entries();
		$lines     = array(
			'# ' . ( '' !== $site_name ? $site_name : __( 'Website', 'lightweight-seo' ) ),
		);

		// The format reserves the blockquote for a short summary of the site. The disclaimer is on the settings card.
		if ( '' !== $summary ) {
			$lines[] = '';
			$lines[] = '> ' . $summary;
		}

		if ( ! empty( $entries ) ) {
			$lines[] = '';
			$lines[] = '## ' . __( 'Authoritative pages', 'lightweight-seo' );

			foreach ( $entries as $entry ) {
				$line = '- [' . $entry['title'] . '](' . str_replace( array( '(', ')' ), array( '%28', '%29' ), $entry['url'] ) . ')';

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

			if ( $this->post_meta && $this->is_static_front_page( $post_id ) ) {
				$context = $this->get_front_page_context( $post_id, $context );
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

	/**
	 * Describe what the served /robots.txt should hold, for the check the administrator's browser runs.
	 *
	 * Crawlers read robots.txt only at the root of the host. WordPress answers it there only when it
	 * is installed at the root, so a subdirectory install expects none of the plugin's rules.
	 *
	 * @since 1.2.0
	 * @return array {
	 *     @type string $url          Address of the file crawlers read.
	 *     @type string $subdirectory Path WordPress is installed under, or an empty string at the root.
	 *     @type array  $rules        Whether each crawler token the plugin adds is allowed, keyed by token.
	 *     @type array  $crawlers     Tokens checked for a group that disallows the whole site.
	 *     @type string $sitemapLine  The Sitemap line WordPress would print, or an empty string.
	 *     @type string $contentSignal The content signals this card adds, or an empty string when it adds none.
	 * }
	 */
	public function get_served_robots_expectations() {
		$home         = home_url( '/' );
		$scheme       = (string) wp_parse_url( $home, PHP_URL_SCHEME );
		$host         = (string) wp_parse_url( $home, PHP_URL_HOST );
		$port         = wp_parse_url( $home, PHP_URL_PORT );
		$subdirectory = untrailingslashit( (string) wp_parse_url( $home, PHP_URL_PATH ) );
		$public       = (bool) get_option( 'blog_public', 1 );
		$rules        = array();
		$signal       = '';
		$sitemap_urls = $public && $this->api && method_exists( $this->api, 'get_sitemap_urls' ) ? (array) $this->api->get_sitemap_urls() : array();

		if ( '' === $subdirectory && $public && ! $this->physical_robots_exists() ) {
			$taken            = $this->get_conflicts()['robots_tokens'];
			$search_allowed   = (bool) $this->settings->ai_search_crawlers_enabled();
			$training_allowed = (bool) $this->settings->ai_training_crawlers_enabled();

			if ( method_exists( $this->settings, 'content_signals_enabled' ) && $this->settings->content_signals_enabled() ) {
				$signal = $this->get_content_signal();
			}

			foreach ( $this->get_crawler_registry() as $token => $definition ) {
				// A token another filter already defines is left to it, so its rule is not the plugin's to check.
				if ( ! in_array( $token, $taken, true ) ) {
					$rules[ $token ] = 'training' === $definition['purpose'] ? $training_allowed : $search_allowed;
				}
			}
		}

		return array(
			'url'           => ( '' !== $scheme ? $scheme : 'https' ) . '://' . $host . ( $port ? ':' . (int) $port : '' ) . '/robots.txt',
			'subdirectory'  => $subdirectory,
			'rules'         => $rules,
			'crawlers'      => array( 'Googlebot', 'Bingbot', '*' ),
			'sitemapLine'   => empty( $sitemap_urls ) ? '' : 'Sitemap: ' . reset( $sitemap_urls ),
			'contentSignal' => $signal,
		);
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

	/** Return the homepage meta description, else the tagline, as one line of Markdown text. */
	private function get_site_summary() {
		$summary = $this->normalize_markdown_text( method_exists( $this->settings, 'get' ) ? $this->settings->get( 'meta_description', '' ) : '' );

		return '' !== $summary ? $summary : $this->normalize_markdown_text( get_bloginfo( 'description' ) );
	}

	private function is_static_front_page( $post_id ) {
		return 'page' === get_option( 'show_on_front' ) && absint( get_option( 'page_on_front', 0 ) ) === (int) $post_id;
	}

	/**
	 * Give a static front page the title and description the homepage prints.
	 *
	 * The public API describes a page by its own title and content, which is right for every
	 * other page. The homepage prints the homepage title format unless the page has its own
	 * SEO title, and the homepage meta description unless the page has its own description.
	 */
	private function get_front_page_context( $post_id, $context ) {
		// The shared service is passed in: constructing another one would register its hooks again.
		$meta = (array) $this->post_meta->get_all( $post_id );

		if ( empty( $meta['seo_title'] ) && method_exists( $this->settings, 'get_home_title_format' ) ) {
			$context['title'] = Lightweight_SEO_Text::resolve_tokens( $this->settings->get_home_title_format(), $this->get_front_page_tokens( $post_id ) );
		}

		$site_description = method_exists( $this->settings, 'get' ) ? trim( (string) $this->settings->get( 'meta_description', '' ) ) : '';

		if ( empty( $meta['seo_description'] ) && '' !== $site_description ) {
			$context['description'] = $site_description;
		}

		return $context;
	}

	/** Title variables as the homepage fills them on its first page. */
	private function get_front_page_tokens( $post_id ) {
		$categories = get_the_category( $post_id );

		return array(
			'%title%'    => (string) get_the_title( $post_id ),
			'%sitename%' => (string) get_bloginfo( 'name' ),
			'%tagline%'  => trim( (string) get_bloginfo( 'description' ) ),
			'%sep%'      => method_exists( $this->settings, 'get_title_separator' ) ? $this->settings->get_title_separator() : LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR,
			'%category%' => ! empty( $categories[0]->name ) ? (string) $categories[0]->name : '',
			'%author%'   => (string) get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ),
			'%year%'     => (string) wp_date( 'Y' ),
			'%page%'     => '',
		);
	}

	private function is_llms_txt_request() {
		$request_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only compared against the fixed llms.txt path.
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
