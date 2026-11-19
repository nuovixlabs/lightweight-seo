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

	/** Comment that opens the plugin's part of the virtual robots.txt, also how the served-file check recognises it. */
	const ROBOTS_MARKER = '# Lightweight SEO AI Discovery (experimental)';

	/**
	 * Priority of the Markdown answer on template_redirect.
	 *
	 * After WordPress's canonical redirect (10), the plugin's own redirects, the access checks
	 * membership and maintenance plugins usually make on this hook, and the inspector (99999):
	 * a visitor who would have been redirected or refused never gets Markdown instead.
	 *
	 * @since 1.3.0
	 */
	const MARKDOWN_PRIORITY = 100000;

	/** Media types that ask for the HTML page. */
	const HTML_TYPES = array( 'text/html', 'application/xhtml+xml' );

	private $settings;
	private $api;
	private $context;
	private $post_meta;
	private $page_context;

	/**
	 * @param object      $settings  Shared settings service.
	 * @param object|null $api       Public API facade.
	 * @param string      $context   Request context.
	 * @param object|null $post_meta The plugin's shared post meta service. Without it, a static front page is listed as the public API describes it.
	 * @param object|null $page_context The plugin's shared page context service, which a Markdown answer reads its head values from.
	 */
	public function __construct( $settings, $api = null, $context = 'frontend', $post_meta = null, $page_context = null ) {
		$this->settings     = $settings;
		$this->api          = $api;
		$this->context      = $context;
		$this->post_meta    = $post_meta;
		$this->page_context = $page_context;

		if ( 'frontend' === $context ) {
			add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 20, 2 );
			add_action( 'template_redirect', array( $this, 'maybe_render_llms_txt' ), 0 );

			// With the switch off, a page view pays for nothing; with it on, for one check of the Accept header.
			if ( $this->markdown_switch_on() ) {
				add_action( 'template_redirect', array( $this, 'maybe_answer_with_markdown' ), self::MARKDOWN_PRIORITY );
			}
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

	/**
	 * Add the crawler policy, only when WordPress owns the virtual robots.txt.
	 *
	 * A crawler that is switched off gets a group of its own with "Disallow: /". A crawler that is
	 * allowed gets no group: under RFC 9309 a crawler that matches a named group ignores the group
	 * for every crawler, so a group holding only "Allow: /" would free it from the site's general
	 * rules, such as WordPress's "Disallow: /wp-admin/". Without a group it follows them, including
	 * rules another filter adds later. A comment names the allowed crawlers so the intent is readable.
	 * A token another filter already names is left to it.
	 */
	public function filter_robots_txt( $output, $is_public ) {
		if ( ! $is_public || $this->physical_robots_exists() ) {
			return $output;
		}

		$output = $this->add_content_signal( (string) $output );

		if ( false !== strpos( $output, self::ROBOTS_MARKER ) ) {
			return $output;
		}

		$allowed = array();
		$groups  = array();

		foreach ( $this->get_crawler_policy() as $token => $is_allowed ) {
			if ( $this->robots_output_contains_token( $output, $token ) ) {
				continue;
			}

			if ( $is_allowed ) {
				$allowed[] = $token;
			} else {
				array_push( $groups, 'User-agent: ' . $token, 'Disallow: /', '' );
			}
		}

		$lines = array( self::ROBOTS_MARKER );

		if ( ! empty( $allowed ) ) {
			$lines[] = '# Not blocked here, so they follow the rules for all crawlers: ' . implode( ', ', $allowed );
		}

		if ( ! empty( $groups ) ) {
			$lines[] = '';
		}

		return rtrim( $output ) . "\n\n" . implode( "\n", array_merge( $lines, $groups ) ) . ( empty( $groups ) ? "\n" : '' );
	}

	/**
	 * Whether each crawler token is allowed by the switches on the card, keyed by token.
	 *
	 * @since 1.2.0
	 * @return bool[]
	 */
	public function get_crawler_policy() {
		$search_allowed   = (bool) $this->settings->ai_search_crawlers_enabled();
		$training_allowed = (bool) $this->settings->ai_training_crawlers_enabled();
		$policy           = array();

		foreach ( $this->get_crawler_registry() as $token => $definition ) {
			$policy[ $token ] = 'training' === $definition['purpose'] ? $training_allowed : $search_allowed;
		}

		return $policy;
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

	/**
	 * Answer a request that prefers Markdown with a Markdown version of the page, and exit.
	 *
	 * Every other request for a page that can be answered in Markdown gets its HTML as before,
	 * with Accept added to Vary, so a cache that honors Vary keeps the two apart.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function maybe_answer_with_markdown() {
		$view = $this->get_markdown_view();

		if ( '' === $view ) {
			return;
		}

		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? (string) wp_unslash( $_SERVER['HTTP_ACCEPT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only parsed for media types and quality values; never printed or stored.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';

		if ( ! $this->markdown_requested( $view, $accept, $method ) ) {
			if ( ! headers_sent() && self::needs_vary_accept( headers_list() ) ) {
				header( 'Vary: Accept', false );
			}

			return;
		}

		$this->send_markdown_response( $view, $method );
		exit;
	}

	/**
	 * Build the Markdown answer and send it.
	 *
	 * The answer is built first: building it loads the class that sends it.
	 *
	 * @since 1.3.0
	 * @param string $view   From get_markdown_view().
	 * @param string $method GET or HEAD.
	 * @return void
	 */
	protected function send_markdown_response( $view, $method ) {
		$response = $this->build_markdown_response( $view, $method );

		Lightweight_SEO_Markdown_Response::send( $response );
	}

	/**
	 * Whether this request is answered in Markdown, for a page that can be.
	 *
	 * @since 1.3.0
	 * @param string $view   From get_markdown_view().
	 * @param string $accept The Accept header.
	 * @param string $method The request method, upper case.
	 * @return bool
	 */
	public function markdown_requested( $view, $accept, $method ) {
		if ( '' === $view || ! in_array( $method, array( 'GET', 'HEAD' ), true ) || ! self::prefers_markdown( $accept ) || ! $this->markdown_available() ) {
			return false;
		}

		/**
		 * Filters whether a page is answered in Markdown when the request asks for it.
		 *
		 * Return false to send the HTML page instead, as a membership or paywall plugin may
		 * for content a visitor has not unlocked.
		 *
		 * @since 1.3.0
		 * @param bool         $supported Whether to answer in Markdown. Default true.
		 * @param WP_Post|null $post      The post or page shown, the posts page, or null for the latest-posts homepage.
		 */
		return (bool) apply_filters( 'lightweight_seo_markdown_supported', true, $this->get_markdown_post( $view ) );
	}

	/**
	 * Build the Markdown answer for this request: status, headers, and body.
	 *
	 * Loads the converter and the response builder, which an ordinary page view never does.
	 *
	 * @since 1.3.0
	 * @param string $view   From get_markdown_view().
	 * @param string $method GET or HEAD. A HEAD answer has headers and no body.
	 * @return array { @type int $status, @type array $headers Each name, value, and whether it replaces one sent before. @type string $body }
	 */
	public function build_markdown_response( $view, $method = 'GET' ) {
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-markdown-converter.php';
		require_once LIGHTWEIGHT_SEO_PLUGIN_DIR . 'includes/class-lightweight-seo-markdown-response.php';

		$signal = method_exists( $this->settings, 'content_signals_enabled' ) && $this->settings->content_signals_enabled() ? $this->get_content_signal() : '';

		return ( new Lightweight_SEO_Markdown_Response( $this->settings, $this->page_context ) )->build( $view, $this->get_markdown_post( $view ), $method, $signal );
	}

	/**
	 * Name the kind of page this request shows, when it can be answered in Markdown.
	 *
	 * Only a published, public post, page, or other viewable post type (not an attachment, not
	 * password protected, not a preview), a static front page, the latest-posts homepage, and
	 * the posts page are. Archives, search, 404, feeds, embeds, REST, robots.txt, the favicon,
	 * sitemaps, llms.txt, trackbacks, and the customizer are left alone.
	 *
	 * @since 1.3.0
	 * @return string singular, home, posts, or an empty string.
	 */
	public function get_markdown_view() {
		$excluded = is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() || is_embed() || is_robots() || is_trackback()
			|| ( function_exists( 'is_favicon' ) && is_favicon() ) || is_404() || is_search() || is_archive() || is_preview()
			|| ( function_exists( 'is_customize_preview' ) && is_customize_preview() )
			|| '' !== (string) get_query_var( 'sitemap' ) || '' !== (string) get_query_var( 'sitemap-stylesheet' ) || $this->is_llms_txt_request();

		if ( $excluded ) {
			return '';
		}

		if ( is_singular() ) {
			$post = $this->get_markdown_post( 'singular' );

			return $this->post_is_public( $post ) && 'attachment' !== (string) ( $post->post_type ?? '' ) ? 'singular' : '';
		}

		if ( ! is_home() ) {
			return '';
		}

		if ( is_front_page() ) {
			return 'home';
		}

		return $this->post_is_public( $this->get_markdown_post( 'posts' ) ) ? 'posts' : '';
	}

	/**
	 * Whether an Accept header prefers Markdown to HTML.
	 *
	 * text/markdown must be named with a quality above 0, and no text/html or
	 * application/xhtml+xml entry may have a higher one. A wildcard such as * / * alone never
	 * selects Markdown, and neither does a missing header. An entry whose quality cannot be
	 * read never helps Markdown win: it counts as 0 for text/markdown and as 1 for HTML.
	 *
	 * @since 1.3.0
	 * @param string $accept The Accept header.
	 * @return bool
	 */
	public static function prefers_markdown( $accept ) {
		$markdown = 0.0;
		$html     = 0.0;

		foreach ( explode( ',', (string) $accept ) as $range ) {
			$parameters = explode( ';', $range );
			$type       = strtolower( (string) preg_replace( '/\s+/', '', (string) array_shift( $parameters ) ) );

			if ( 'text/markdown' !== $type && ! in_array( $type, self::HTML_TYPES, true ) ) {
				continue;
			}

			$quality = 1.0;

			foreach ( $parameters as $parameter ) {
				$pair = explode( '=', $parameter, 2 );

				if ( 'q' === strtolower( trim( $pair[0] ) ) ) {
					$value   = trim( $pair[1] ?? '', " \t\"" );
					$quality = 1 === preg_match( '/^(?:0(?:\.\d{0,3})?|1(?:\.0{0,3})?)$/', $value ) ? (float) $value : null;
					break;
				}
			}

			if ( 'text/markdown' === $type ) {
				$markdown = max( $markdown, null === $quality ? 0.0 : $quality );
			} else {
				$html = max( $html, null === $quality ? 1.0 : $quality );
			}
		}

		return $markdown > 0 && $markdown >= $html;
	}

	/**
	 * Whether a Vary: Accept line still has to be added to the headers already set.
	 *
	 * @since 1.3.0
	 * @param string[] $headers Header lines, as headers_list() returns them.
	 * @return bool
	 */
	public static function needs_vary_accept( $headers ) {
		foreach ( (array) $headers as $line ) {
			$parts = explode( ':', (string) $line, 2 );

			if ( 'vary' === strtolower( trim( $parts[0] ) ) && isset( $parts[1] ) && 1 === preg_match( '/(?:^|,)\s*(?:accept|\*)\s*(?:,|$)/i', $parts[1] ) ) {
				return false;
			}
		}

		return true;
	}

	/** The post a Markdown answer is about: the one shown, the posts page, or none for the latest-posts homepage. */
	private function get_markdown_post( $view ) {
		$post_id = 0;

		if ( 'singular' === $view ) {
			$post_id = absint( get_queried_object_id() );
		} elseif ( 'posts' === $view ) {
			$post_id = absint( get_option( 'page_for_posts', 0 ) );
		}

		// get_post( 0 ) returns whatever post is global at the time, which is not the one meant.
		return $post_id ? get_post( $post_id ) : null;
	}

	/** Whether the Markdown on request switch is on. */
	private function markdown_switch_on() {
		return method_exists( $this->settings, 'markdown_on_request_enabled' ) && $this->settings->markdown_on_request_enabled();
	}

	/**
	 * Whether this server can build Markdown. The converter needs the DOM extension.
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	protected function markdown_available() {
		return class_exists( 'DOMDocument' );
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
	 *     @type array  $rules        Whether each crawler token the plugin governs is allowed, keyed by token.
	 *     @type string $marker       The comment that opens the plugin's part of the file, or an empty string.
	 *     @type array  $crawlers     Tokens checked for a group that disallows the whole site.
	 *     @type string $sitemapLine  The Sitemap line WordPress would print, or an empty string.
	 *     @type string $contentSignal The content signals this card adds, or an empty string when it adds none.
	 *     @type bool   $signalOff    Whether the content signals switch is off.
	 *     @type bool   $llmsEnabled  Whether the llms.txt switch is on.
	 *     @type array  $probes       Addresses the readiness report fetches, keyed by result ID. On a
	 *                                subdirectory install the sitemap and llms.txt are this site's own.
	 *     @type bool   $sitemapFromRobots Only on a subdirectory install: false, because the robots.txt at
	 *                                the root of the host is not this site's.
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
			$taken = $this->get_conflicts()['robots_tokens'];

			if ( method_exists( $this->settings, 'content_signals_enabled' ) && $this->settings->content_signals_enabled() ) {
				$signal = $this->get_content_signal();
			}

			// A token another filter already defines is left to it, so its rule is not the plugin's to check.
			$rules = array_diff_key( $this->get_crawler_policy(), array_flip( $taken ) );
		}

		$origin   = ( '' !== $scheme ? $scheme : 'https' ) . '://' . $host . ( $port ? ':' . (int) $port : '' );
		$expected = array(
			'url'           => $origin . '/robots.txt',
			'subdirectory'  => $subdirectory,
			'rules'         => $rules,
			// The comment that opens the plugin's part of the file, which shows the file served is WordPress's with it.
			'marker'        => empty( $rules ) ? '' : self::ROBOTS_MARKER,
			'crawlers'      => array( 'Googlebot', 'Bingbot', '*' ),
			'sitemapLine'   => empty( $sitemap_urls ) ? '' : 'Sitemap: ' . reset( $sitemap_urls ),
			'contentSignal' => $signal,
			// Off by choice only when the switch is off; a line from elsewhere still counts as served.
			'signalOff'     => ! ( method_exists( $this->settings, 'content_signals_enabled' ) && $this->settings->content_signals_enabled() ),
			'llmsEnabled'   => (bool) $this->settings->llms_txt_enabled(),
			// Agents look for these at the root of the host, as crawlers do for robots.txt.
			'probes'        => array(
				'sitemapXml'  => $origin . '/sitemap.xml',
				'linkHeaders' => home_url( '/' ),
				'markdown'    => home_url( '/' ),
				'llms'        => $origin . '/llms.txt',
				'apiCatalog'  => array( $origin . '/.well-known/api-catalog' ),
				'skills'      => array( $origin . '/.well-known/agent-skills/index.json', $origin . '/.well-known/skills/index.json' ),
				'mcp'         => array( $origin . '/.well-known/mcp/server-card.json', $origin . '/.well-known/mcp/server-cards.json', $origin . '/.well-known/mcp.json' ),
				'ard'         => array( $origin . '/.well-known/ard.json', $origin . '/.well-known/ai-catalog.json' ),
			),
		);

		if ( '' !== $subdirectory ) {
			// The root of the host is not this site's. Its own sitemap and llms.txt answer under its path,
			// and a Sitemap line in the root robots.txt may belong to another site, so it is no fallback.
			$expected['probes']['sitemapXml'] = empty( $sitemap_urls ) ? home_url( '/sitemap.xml' ) : (string) reset( $sitemap_urls );
			$expected['probes']['llms']       = home_url( '/llms.txt' );
			$expected['sitemapFromRobots']    = false;
		}

		return $expected;
	}

	/**
	 * Return the AI readiness report: rows grouped by area, each stating one fact.
	 *
	 * A row is in one of four states: success (passed), warning (needs attention), off (off by
	 * choice), or na (not applicable), or info for a neutral fact. A row whose state is browser
	 * is decided by the administrator's browser, which fetches the served address; it carries
	 * every message it can show, keyed by what lightweight-seo-robots.js returns. PHP requests
	 * nothing. The areas and check names follow https://isitagentready.com/llms-full.txt; there
	 * is no score, level, or percentage.
	 *
	 * @since 1.2.0
	 * @return array[] Areas, each with title and rows. A row has id, state, label, text, and for
	 *                 browser rows url and messages.
	 */
	public function get_readiness_report() {
		$conflicts = $this->get_conflicts();
		$expected  = $this->get_served_robots_expectations();
		$public    = (bool) get_option( 'blog_public', 1 );
		$in_folder = '' !== $expected['subdirectory'];
		$na        = static function ( $id, $label, $reason ) {
			return array(
				'id'    => $id,
				'state' => 'na',
				'label' => $label,
				'text'  => $reason,
			);
		};

		return array(
			array(
				'title' => __( 'Discoverability', 'lightweight-seo' ),
				'rows'  => array_values(
					array_filter(
						array(
							array(
								'id'    => 'search_visibility',
								'state' => $public ? 'success' : 'warning',
								'label' => __( 'Search visibility', 'lightweight-seo' ),
								'text'  => $public ? __( 'WordPress lets search engines and crawlers index this site.', 'lightweight-seo' ) : ( $in_folder ? __( 'WordPress asks search engines not to index this site. Change it under Settings → Reading if that is not intended.', 'lightweight-seo' ) : __( 'WordPress asks search engines not to index this site, and robots.txt disallows every crawler. Change it under Settings → Reading if that is not intended.', 'lightweight-seo' ) ),
							),
							$this->browser_row(
								'fetch',
								__( 'robots.txt', 'lightweight-seo' ),
								$expected['url'],
								array(
									/* translators: %s: address of the robots.txt file. */
									'ok'     => __( '%s answers with a plain text file.', 'lightweight-seo' ),
									'failed' => __( 'This browser could not fetch the file. Open it to see what crawlers receive.', 'lightweight-seo' ),
									/* translators: %s: HTTP status code, such as 404. */
									'status' => __( 'The address answers with status %s instead of 200, so crawlers find no rules there.', 'lightweight-seo' ),
									/* translators: %s: media type the file is served as, such as text/html. */
									'type'   => __( 'The file is served as %s. Crawlers expect text/plain; check the server or plugin that answers this address.', 'lightweight-seo' ),
								)
							),
							$in_folder ? $this->get_host_root_row( $expected ) : null,
							$this->browser_row(
								'sitemapXml',
								__( 'Sitemap', 'lightweight-seo' ),
								$expected['probes']['sitemapXml'],
								$in_folder ? array(
									/* translators: %s: address the sitemap was found at, after redirects. */
									'valid'   => __( '%s answers with a valid XML sitemap.', 'lightweight-seo' ),
									'missing' => sprintf(
										/* translators: %s: address of this site's sitemap. */
										__( 'This site’s sitemap address, %s, does not answer with a valid XML sitemap.', 'lightweight-seo' ),
										$expected['probes']['sitemapXml']
									),
								) : array(
									/* translators: %s: address the sitemap was found at, after redirects. */
									'valid'   => __( '%s answers with a valid XML sitemap.', 'lightweight-seo' ),
									/* translators: %s: sitemap addresses listed in robots.txt. */
									'listed'  => __( '/sitemap.xml is not a valid XML sitemap, but robots.txt points crawlers to %s.', 'lightweight-seo' ),
									'missing' => __( 'Neither /sitemap.xml nor a Sitemap line in robots.txt leads to a sitemap.', 'lightweight-seo' ),
								)
							),
							// The robots.txt at the root of the host is not this site's; the row above gives its Sitemap line.
							$in_folder ? null : $this->browser_row(
								'sitemap',
								__( 'Sitemap line in robots.txt', 'lightweight-seo' ),
								$expected['url'],
								array(
									/* translators: %s: list of sitemap addresses. */
									'present' => __( 'Points crawlers to %s.', 'lightweight-seo' ),
									/* translators: %s: the exact line to add, such as "Sitemap: https://example.com/wp-sitemap.xml". */
									'missing' => __( 'No Sitemap line is served. Add this line to the file: %s', 'lightweight-seo' ),
									'none'    => __( 'No Sitemap line is served, and WordPress sitemaps are off.', 'lightweight-seo' ),
								)
							),
							$this->browser_row(
								'linkHeaders',
								__( 'Link headers', 'lightweight-seo' ),
								$expected['probes']['linkHeaders'],
								array(
									/* translators: %s: link relations found, such as api-catalog. */
									'found' => __( 'The homepage sends Link headers for agents: %s.', 'lightweight-seo' ),
									'none'  => __( 'Not offered. The homepage sends no Link header that points agents to an API description or catalog; this version of Lightweight SEO adds none.', 'lightweight-seo' ),
								)
							),
							$na( 'dnsAid', __( 'DNS records for agents', 'lightweight-seo' ), __( 'DNS records for agents announce agent services under a domain, and they are set at the DNS provider, where Lightweight SEO can neither publish nor check them.', 'lightweight-seo' ) ),
						)
					)
				),
			),
			array(
				'title' => __( 'Content', 'lightweight-seo' ),
				'rows'  => array_values(
					array_filter(
						array(
							$this->browser_row(
								'markdown',
								__( 'Markdown on request', 'lightweight-seo' ),
								$expected['probes']['markdown'],
								array(
									'found' => __( 'The homepage answers a request for text/markdown with Markdown.', 'lightweight-seo' ),
									'none'  => __( 'Not offered. The homepage answers a request for text/markdown with HTML; this version of Lightweight SEO does not provide Markdown.', 'lightweight-seo' ),
								)
							),
							$this->browser_row(
								'llms',
								__( 'llms.txt', 'lightweight-seo' ),
								$expected['probes']['llms'],
								array(
									/* translators: %s: address of the llms.txt file. */
									'found'   => __( '%s answers with a Markdown index that starts with a heading.', 'lightweight-seo' ),
									'missing' => __( 'The llms.txt switch on this card is on, but the address does not answer with a Markdown index. Check the conflicts below and any cache in front of the site.', 'lightweight-seo' ),
									'off'     => __( 'The llms.txt switch on this card is off, and nothing else answers the address.', 'lightweight-seo' ),
								)
							),
							$this->get_llms_pages_row(),
							$conflicts['physical_llms'] || $conflicts['llms_endpoint'] ? array(
								'id'    => 'llms_owner',
								'state' => 'warning',
								'label' => __( 'Who answers llms.txt', 'lightweight-seo' ),
								'text'  => $conflicts['physical_llms'] ? __( 'A physical llms.txt file takes precedence; Lightweight SEO will not replace it.', 'lightweight-seo' ) : __( 'Another rewrite rule or integration claims the llms.txt address; Lightweight SEO will not replace it.', 'lightweight-seo' ),
							) : null,
						)
					)
				),
			),
			array(
				'title' => __( 'Bot governance', 'lightweight-seo' ),
				'rows'  => array_values(
					array_filter(
						array(
							$this->get_robots_owner_row( $conflicts, ! empty( $expected['rules'] ), $in_folder ),
							array(
								'id'    => 'training',
								'state' => $this->settings->ai_training_crawlers_enabled() ? 'success' : 'off',
								'label' => __( 'Model-training crawlers', 'lightweight-seo' ),
								'text'  => $this->settings->ai_training_crawlers_enabled() ? __( 'Allowed, as chosen on this card.', 'lightweight-seo' ) : __( 'Disallowed, as chosen on this card.', 'lightweight-seo' ),
							),
							empty( $expected['rules'] ) ? null : $this->browser_row(
								'rules',
								__( 'AI crawler rules', 'lightweight-seo' ),
								$expected['url'],
								array(
									/* translators: %s: number of AI crawlers. */
									'present'   => __( 'The served file follows this card for all %s crawlers: each one switched off has a group that disallows it, and the others are not shut out of the site.', 'lightweight-seo' ),
									'absent'    => __( 'The served file does not have the part Lightweight SEO adds. Another file, plugin, or server setting answers /robots.txt instead of WordPress.', 'lightweight-seo' ),
									/* translators: %s: list of crawler names. */
									'missing'   => __( 'The served file has no group that disallows these crawlers, which are switched off here: %s.', 'lightweight-seo' ),
									/* translators: %s: list of crawler names. */
									'different' => __( 'The served file gives a different answer than this card for: %s. A crawler allowed here is shut out of the whole site, or one switched off here is not.', 'lightweight-seo' ),
								)
							),
							$this->browser_row(
								'blocked',
								__( 'Whole-site blocks', 'lightweight-seo' ),
								$expected['url'],
								array(
									/* translators: %s: list of crawler names, where * means every crawler. */
									'open'    => __( 'No group for %s disallows the whole site.', 'lightweight-seo' ),
									/* translators: %s: list of crawler names, where * means every crawler. */
									'blocked' => __( 'These groups disallow the whole site: %s. The crawlers they name may not fetch any page.', 'lightweight-seo' ),
									/* translators: %s: list of crawler names, where * means every crawler. */
									'partly'  => __( 'These groups disallow the homepage and every page their Allow lines do not list: %s.', 'lightweight-seo' ),
								)
							),
							$this->browser_row(
								'signals',
								__( 'Content signals', 'lightweight-seo' ),
								$expected['url'],
								array(
									/* translators: %s: the content signals served, such as "ai-train=no, search=yes, ai-input=yes". */
									'present' => __( 'Served: %s.', 'lightweight-seo' ),
									/* translators: %s: the expected Content-Signal line. */
									'missing' => __( 'No Content-Signal line is served, although this card adds one. Expected: %s', 'lightweight-seo' ),
									'none'    => __( 'No Content-Signal line is served.', 'lightweight-seo' ),
									'off'     => __( 'The content signals switch on this card is off, and no Content-Signal line is served.', 'lightweight-seo' ),
								)
							),
							$na( 'webBotAuth', __( 'Web Bot Auth key directory', 'lightweight-seo' ), __( 'A Web Bot Auth key directory publishes the keys an operator’s own bots sign their requests with; Lightweight SEO runs no bot and publishes no keys.', 'lightweight-seo' ) ),
						)
					)
				),
			),
			array(
				'title' => __( 'Structured data', 'lightweight-seo' ),
				'rows'  => array( $this->get_identity_row() ),
			),
			array(
				'title' => __( 'Capabilities', 'lightweight-seo' ),
				'rows'  => array(
					$this->browser_row(
						'apiCatalog',
						__( 'API catalog', 'lightweight-seo' ),
						$expected['probes']['apiCatalog'][0],
						$this->found_messages()
					),
					$this->browser_row(
						'skills',
						__( 'Agent skills index', 'lightweight-seo' ),
						$expected['probes']['skills'][0],
						$this->found_messages()
					),
					$this->browser_row(
						'mcp',
						__( 'MCP server card', 'lightweight-seo' ),
						$expected['probes']['mcp'][0],
						$this->found_messages()
					),
					$this->browser_row(
						'ard',
						__( 'Agent resource discovery manifest', 'lightweight-seo' ),
						$expected['probes']['ard'][0],
						$this->found_messages()
					),
					$na( 'openApi', __( 'OpenAPI description', 'lightweight-seo' ), __( 'An OpenAPI document describes an API for the plugin or service that provides it; Lightweight SEO adds no API, and the WordPress REST API describes itself at /wp-json/.', 'lightweight-seo' ) ),
					$na( 'webMcp', __( 'WebMCP', 'lightweight-seo' ), __( 'WebMCP lets a page offer its actions as tools to an agent in the browser, which the code behind those actions registers; Lightweight SEO registers none.', 'lightweight-seo' ) ),
				),
			),
			array(
				'title' => __( 'Authentication', 'lightweight-seo' ),
				'rows'  => array(
					$na( 'oauth', __( 'OAuth metadata and auth.md', 'lightweight-seo' ), __( 'They describe an OAuth server an agent can sign in with; WordPress has none, its REST API signs in with application passwords, and Lightweight SEO adds no sign-in.', 'lightweight-seo' ) ),
				),
			),
			array(
				'title' => __( 'Agent protocols', 'lightweight-seo' ),
				'rows'  => array(
					$na( 'a2a', __( 'A2A agent card', 'lightweight-seo' ), __( 'An A2A agent card describes an AI agent that answers at the site, for whichever plugin or service runs it; Lightweight SEO runs no agent.', 'lightweight-seo' ) ),
					$na( 'commerce', __( 'Commerce protocols', 'lightweight-seo' ), __( 'Agent payment and checkout protocols are provided by the shop or payment plugin that handles the sale; Lightweight SEO handles no products or payments.', 'lightweight-seo' ) ),
				),
			),
		);
	}

	/**
	 * Describe a row the administrator's browser decides by fetching an address.
	 *
	 * @param string $id       Result ID from lightweight-seo-robots.js.
	 * @param string $label    Row title.
	 * @param string $url      Address that is fetched, linked from the row.
	 * @param array  $messages Message for each result key.
	 * @return array
	 */
	private function browser_row( $id, $label, $url, $messages ) {
		// A failed request says nothing about the site; it is often an administration on another address.
		$messages += array(
			'unchecked' => __( 'Not checked: this browser could not fetch the address. That happens when the administration runs on another address than the site, such as www or http, or when something blocks the request. Open it to see the answer.', 'lightweight-seo' ),
		);

		return array(
			'id'       => $id,
			'state'    => 'browser',
			'label'    => $label,
			'text'     => __( 'Checked by this browser, which fetches the address the way a crawler would.', 'lightweight-seo' ),
			'url'      => $url,
			'messages' => $messages,
		);
	}

	/** Messages of a file this version does not publish, which another plugin or a CDN may. */
	private function found_messages() {
		return array(
			/* translators: %s: address the file was found at. */
			'found' => __( 'Found at %s.', 'lightweight-seo' ),
			'none'  => __( 'Not offered. This version of Lightweight SEO does not publish it, and nothing else answers the address with one.', 'lightweight-seo' ),
		);
	}

	/**
	 * State once that the root of the host, where crawlers and agents look, is not this site's.
	 *
	 * On a subdirectory install WordPress does not answer robots.txt, llms.txt, or the well-known
	 * files at the root of the host. That is a fact about the install, not a problem with each file,
	 * so it is stated here once, with the Sitemap line for whoever maintains the root robots.txt.
	 *
	 * @param array $expected From get_served_robots_expectations().
	 * @return array
	 */
	private function get_host_root_row( $expected ) {
		$root  = substr( $expected['url'], 0, -strlen( 'robots.txt' ) );
		$lines = array(
			sprintf(
				/* translators: 1: path WordPress is installed under, such as /blog. 2: address of the root of the host, such as https://example.com/. */
				__( 'WordPress is installed under %1$s. Crawlers and agents look for robots.txt, llms.txt, and the well-known files at the root of the host, %2$s, which WordPress does not answer, so the crawler rules set on this card are not served there.', 'lightweight-seo' ),
				$expected['subdirectory'],
				$root
			),
		);

		if ( '' !== $expected['sitemapLine'] ) {
			$lines[] = sprintf(
				/* translators: 1: address of the root robots.txt. 2: the line to add, such as "Sitemap: https://example.com/blog/wp-sitemap.xml". */
				__( 'Whoever maintains %1$s can point crawlers to this site’s sitemap with this line: %2$s', 'lightweight-seo' ),
				$expected['url'],
				$expected['sitemapLine']
			);
		}

		return array(
			'id'    => 'host_root',
			'state' => 'info',
			'label' => __( 'Root of the host', 'lightweight-seo' ),
			'text'  => $lines,
		);
	}

	/**
	 * Say who writes robots.txt, as far as the server can know.
	 *
	 * The server sees a file on disk and other robots_txt filters it can run. It cannot see a
	 * filter that runs later, a cache, a CDN, or a server rule that answers /robots.txt instead
	 * of WordPress. So with nothing in the way, the row states only that, as a neutral fact,
	 * and the browser settles it from the "rules" row: passed when the served file holds the
	 * rules set here, needs attention when it does not.
	 *
	 * @param array $conflicts       From get_conflicts().
	 * @param bool  $rules_expected  Whether the served file should hold the card's rules.
	 * @param bool  $in_folder       Whether WordPress is installed under a path of the host.
	 * @return array
	 */
	private function get_robots_owner_row( $conflicts, $rules_expected, $in_folder = false ) {
		$row = array(
			'id'    => 'robots_owner',
			'label' => __( 'Who writes robots.txt', 'lightweight-seo' ),
		);

		if ( $in_folder ) {
			return $row + array(
				'state' => 'info',
				'text'  => __( 'Not WordPress: the robots.txt crawlers read is at the root of the host, which belongs to the server or another site.', 'lightweight-seo' ),
			);
		}

		if ( $conflicts['physical_robots'] ) {
			return $row + array(
				'state' => 'warning',
				'text'  => __( 'A physical robots.txt file takes precedence; Lightweight SEO will not overwrite it.', 'lightweight-seo' ),
			);
		}

		if ( ! empty( $conflicts['robots_tokens'] ) ) {
			return $row + array(
				'state' => 'warning',
				/* translators: %s: list of crawler names. */
				'text'  => sprintf( __( 'Another robots.txt filter already writes rules for %s, so Lightweight SEO leaves them alone.', 'lightweight-seo' ), implode( ', ', $conflicts['robots_tokens'] ) ),
			);
		}

		$row += array(
			'state' => 'info',
			'text'  => __( 'No robots.txt file is on disk, and no other plugin the server can see adds rules for these crawlers. Whether the served file has the part Lightweight SEO adds is checked in this browser.', 'lightweight-seo' ),
		);

		if ( $rules_expected ) {
			$row['attributes'] = array(
				'data-settled-by'  => 'rules',
				'data-msg-present' => __( 'No robots.txt file is on disk, no other plugin the server can see adds rules for these crawlers, and the served file has the part Lightweight SEO adds.', 'lightweight-seo' ),
				'data-msg-absent'  => __( 'No robots.txt file is on disk and no other plugin the server can see adds rules for these crawlers, but the served file does not have the part Lightweight SEO adds. Something else answers or rewrites /robots.txt: a filter that runs later, a cache, a CDN, or a server setting.', 'lightweight-seo' ),
			);
		}

		return $row;
	}

	/** Describe the pages chosen for llms.txt. */
	private function get_llms_pages_row() {
		$row = array(
			'id'    => 'llms_pages',
			'label' => __( 'Pages listed in llms.txt', 'lightweight-seo' ),
		);

		if ( ! $this->settings->llms_txt_enabled() ) {
			return $row + array(
				'state' => 'off',
				'text'  => __( 'The llms.txt switch on this card is off.', 'lightweight-seo' ),
			);
		}

		$selected  = count( $this->settings->get_llms_post_ids() );
		$entries   = $this->get_curated_entries();
		$described = count( array_filter( wp_list_pluck( $entries, 'description' ) ) );

		return $row + array(
			'state' => $selected && count( $entries ) === $selected && count( $entries ) === $described ? 'success' : 'warning',
			'text'  => array(
				/* translators: 1: number of pages listed. 2: number of pages selected. */
				sprintf( __( '%1$d of %2$d selected pages are listed: published, public, indexable, canonical, on this site, and not redirected.', 'lightweight-seo' ), count( $entries ), $selected ),
				/* translators: 1: number of listed pages with a description. 2: number of listed pages. */
				sprintf( __( '%1$d of %2$d listed pages have a description.', 'lightweight-seo' ), $described, count( $entries ) ),
			),
		);
	}

	/** Say what the Organization or Person node holds, and name what it lacks. */
	private function get_identity_row() {
		$row = array(
			'id'    => 'identity',
			'label' => __( 'Organization or person', 'lightweight-seo' ),
		);

		if ( class_exists( 'Lightweight_SEO_Compatibility_Service' ) && ! ( new Lightweight_SEO_Compatibility_Service() )->feature_output_allowed( 'schema' ) ) {
			return $row + array(
				'state' => 'na',
				'text'  => __( 'Another SEO plugin prints the structured data, so Lightweight SEO prints none to check.', 'lightweight-seo' ),
			);
		}

		if ( ! $this->settings->schema_output_enabled() ) {
			return $row + array(
				'state' => 'off',
				'text'  => __( 'Core schema output is switched off on the “Schema and identity” tab.', 'lightweight-seo' ),
			);
		}

		$is_person = 'person' === $this->settings->get_identity_type();
		$image     = $this->settings->get_identity_logo_url();
		$missing   = array();

		if ( ! $is_person && '' === (string) $image ) {
			$image = $this->settings->get_social_image_url();
		}

		if ( '' === trim( (string) $this->settings->get_identity_name() ) ) {
			$missing[] = __( 'name', 'lightweight-seo' );
		}

		if ( '' === (string) $image ) {
			$missing[] = $is_person ? __( 'photo', 'lightweight-seo' ) : __( 'logo', 'lightweight-seo' );
		}

		if ( '' === (string) $this->settings->get_identity_description() ) {
			$missing[] = __( 'description', 'lightweight-seo' );
		}

		if ( empty( $this->settings->get_organization_same_as() ) ) {
			$missing[] = __( 'profile links', 'lightweight-seo' );
		}

		$node = $is_person ? 'Person' : 'Organization';

		return $row + array(
			'state' => empty( $missing ) ? 'success' : 'warning',
			'text'  => empty( $missing )
				/* translators: %s: schema type, Organization or Person. */
				? sprintf( __( 'The %s node has a name, an image, a description, and profile links.', 'lightweight-seo' ), $node )
				/* translators: 1: schema type, Organization or Person. 2: list of missing properties, such as "logo, description". */
				: sprintf( __( 'The %1$s node is missing: %2$s. Add them on the “Site identity” card.', 'lightweight-seo' ), $node, implode( ', ', $missing ) ),
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
