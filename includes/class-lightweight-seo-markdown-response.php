<?php
/**
 * The Markdown answer to a request that prefers text/markdown.
 *
 * @since 1.3.0
 * @package Lightweight_SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Builds the Markdown version of a page, with its headers, and sends it.
 *
 * The document follows the layout of Cloudflare's Markdown for Agents: YAML front matter with
 * the title, description, and image the HTML head prints; the page content; and the JSON-LD
 * graph the head prints, in one fenced block at the end. It is built for one request and
 * discarded: nothing is stored. Loaded only when a Markdown answer is being built.
 */
class Lightweight_SEO_Markdown_Response {

	/** Robots directives that restrict nothing beyond what a page without directives allows. */
	const PERMISSIVE_ROBOTS = array( 'index', 'follow', 'all', 'max-image-preview:large', 'max-snippet:-1', 'max-video-preview:-1' );

	/** @var Lightweight_SEO_Settings */
	private $settings;

	/** @var Lightweight_SEO_Page_Context_Service|null */
	private $page_context;

	/** @var Lightweight_SEO_Compatibility_Service */
	private $compatibility;

	/**
	 * @param object      $settings      Shared settings service.
	 * @param object|null $page_context  Shared page context service, the one the HTML head reads.
	 * @param object|null $compatibility Safe mode service.
	 */
	public function __construct( $settings, $page_context, $compatibility = null ) {
		$this->settings      = $settings;
		$this->page_context  = $page_context;
		$this->compatibility = $compatibility ? $compatibility : new Lightweight_SEO_Compatibility_Service();
	}

	/**
	 * Build the answer for the current request.
	 *
	 * @param string       $view           singular, home, or posts.
	 * @param WP_Post|null $post           The post shown, the posts page, or null for the latest-posts homepage.
	 * @param string       $method         GET or HEAD. A HEAD answer has no body and builds none.
	 * @param string       $content_signal Value of the Content-Signal header, or an empty string for none.
	 * @return array { @type int $status, @type array $headers, @type string $body }
	 */
	public function build( $view, $post, $method = 'GET', $content_signal = '' ) {
		$body = '';

		if ( 'HEAD' !== $method ) {
			// A shortcode or loop hook that prints instead of returning would land in front of the document.
			ob_start();
			$document = $this->build_document( $view, $post );
			ob_end_clean();

			/**
			 * Filters the Markdown document sent for a page.
			 *
			 * @since 1.3.0
			 * @param string       $markdown The document: front matter, content, and JSON-LD.
			 * @param WP_Post|null $post     The post or page shown, the posts page, or null for the latest-posts homepage.
			 */
			$body = (string) apply_filters( 'lightweight_seo_markdown_document', $document, $post );
		}

		return array(
			'status'  => 200,
			'headers' => self::get_headers( $this->get_canonical( $view, $post ), $this->get_robots(), $content_signal ),
			'body'    => $body,
		);
	}

	/**
	 * Send an answer from build(). A thin wrapper, so the answer itself can be tested.
	 *
	 * @param array $response From build().
	 * @return void
	 */
	public static function send( $response ) {
		// Page caches that key on the address alone would otherwise hand this to browsers.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! headers_sent() ) {
			status_header( (int) $response['status'] );
			// They describe the HTML page; a request cannot be answered with "not modified" from them.
			header_remove( 'ETag' );
			header_remove( 'Last-Modified' );

			foreach ( $response['headers'] as $header ) {
				header( $header[0] . ': ' . $header[1], $header[2] );
			}
		}

		echo $response['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A text/markdown document sent with nosniff; its text is escaped as Markdown when it is built.
	}

	/**
	 * The headers of a Markdown answer.
	 *
	 * @param string $canonical      Canonical address, or an empty string.
	 * @param string $robots         Robots directives to send, or an empty string.
	 * @param string $content_signal Content signals, or an empty string.
	 * @return array[] Each name, value, and whether it replaces a header of the same name sent before.
	 */
	public static function get_headers( $canonical, $robots, $content_signal ) {
		$headers = array(
			array( 'Content-Type', 'text/markdown; charset=UTF-8', true ),
			array( 'Vary', 'Accept', false ),
			array( 'X-Content-Type-Options', 'nosniff', true ),
			// Many page caches and some CDNs store a page by its address alone and would hand Markdown to browsers.
			array( 'Cache-Control', 'private, no-store', true ),
			array( 'X-LiteSpeed-Cache-Control', 'no-cache', true ),
		);
		$canonical = (string) preg_replace( '/[\x00-\x20<>"]/', '', (string) $canonical );

		if ( '' !== $canonical ) {
			$headers[] = array( 'Link', '<' . $canonical . '>; rel="canonical"', false );
		}

		if ( '' !== trim( (string) $robots ) ) {
			$headers[] = array( 'X-Robots-Tag', self::header_value( $robots ), false );
		}

		if ( '' !== trim( (string) $content_signal ) ) {
			$headers[] = array( 'Content-Signal', self::header_value( $content_signal ), true );
		}

		return $headers;
	}

	/**
	 * Robots directives to repeat in an X-Robots-Tag header, when they restrict anything.
	 *
	 * @param array $robots Directives as the wp_robots filter returns them: name => true, or name => value.
	 * @return string The directives as the robots meta tag prints them, or an empty string.
	 */
	public static function restrictive_robots( $robots ) {
		$robots = (array) $robots;

		// WordPress drops the positive directive when its negative is present, as wp_robots() does.
		if ( ! empty( $robots['noindex'] ) ) {
			unset( $robots['index'] );
		}

		if ( ! empty( $robots['nofollow'] ) ) {
			unset( $robots['follow'] );
		}

		$directives = array();

		foreach ( $robots as $name => $value ) {
			if ( is_string( $value ) ) {
				$directives[] = $name . ':' . $value;
			} elseif ( $value ) {
				$directives[] = (string) $name;
			}
		}

		return array() === array_diff( $directives, self::PERMISSIVE_ROBOTS ) ? '' : implode( ', ', $directives );
	}

	/**
	 * YAML front matter with the fields that have a value, or an empty string when none has.
	 *
	 * @param array $fields Values keyed by field name, in order.
	 * @return string
	 */
	public static function front_matter( $fields ) {
		$lines = array();

		foreach ( (array) $fields as $name => $value ) {
			$value = trim( (string) $value );

			if ( '' !== $value ) {
				$lines[] = $name . ': ' . self::yaml_scalar( $value );
			}
		}

		return empty( $lines ) ? '' : "---\n" . implode( "\n", $lines ) . "\n---";
	}

	/**
	 * Write a string as a YAML scalar on one line.
	 *
	 * Plain when it cannot be read as anything else, so a title reads as itself; double-quoted
	 * when it holds a colon followed by a space, a quote or other indicator at its start, a
	 * control character, or could be taken for a number, a boolean, or null.
	 *
	 * @param string $value Text, UTF-8.
	 * @return string
	 */
	public static function yaml_scalar( $value ) {
		$value = (string) $value;
		$plain = '' !== $value
			&& 1 !== preg_match( '/^[\s\-?:,\[\]{}#&*!|>\'"%@`.+0-9]/', $value )
			&& 1 !== preg_match( '/[\x00-\x1F\x7F]|\xC2\x85|\xE2\x80[\xA8\xA9]|\xEF\xBB\xBF|: |:$| #|\s$/', $value )
			&& 1 !== preg_match( '/^(?:true|false|yes|no|on|off|y|n|null|~)$/i', $value );

		if ( $plain ) {
			return $value;
		}

		$escaped = strtr(
			$value,
			array(
				'\\'           => '\\\\',
				'"'            => '\\"',
				"\n"           => '\\n',
				"\r"           => '\\r',
				"\t"           => '\\t',
				"\xC2\x85"     => '\\N',
				"\xE2\x80\xA8" => '\\L',
				"\xE2\x80\xA9" => '\\P',
				"\xEF\xBB\xBF" => '\\uFEFF',
			)
		);

		return '"' . preg_replace_callback(
			'/[\x00-\x1F\x7F]/',
			static function ( $matches ) {
				return sprintf( '\\x%02X', ord( $matches[0] ) );
			},
			$escaped
		) . '"';
	}

	/**
	 * The whole document: front matter, content, and JSON-LD, each when it has something.
	 *
	 * @param string       $view singular, home, or posts.
	 * @param WP_Post|null $post The post shown, the posts page, or null.
	 * @return string
	 */
	public function build_document( $view, $post ) {
		$parts = array(
			self::front_matter( $this->get_front_matter_fields() ),
			'singular' === $view ? $this->build_singular_body( $post ) : $this->build_listing_body( $view, $post ),
		);
		$graph = $this->get_schema_json();

		if ( '' !== $graph ) {
			$parts[] = Lightweight_SEO_Markdown_Converter::fenced_code( $graph, 'json' );
		}

		return implode( "\n\n", array_filter( array_map( 'trim', $parts ), 'strlen' ) ) . "\n";
	}

	/**
	 * Title, description, and image, from the same sources the HTML head prints.
	 *
	 * The title is the document title WordPress prints, with every filter on it, the plugin's
	 * included. The description and image are the plugin's meta tags; when safe mode leaves the
	 * meta tags to another SEO plugin, WordPress prints neither, so neither is given.
	 *
	 * @return array
	 */
	private function get_front_matter_fields() {
		$fields = array(
			'title'       => self::plain_text( wp_get_document_title() ),
			'description' => '',
			'image'       => '',
		);

		if ( ! $this->page_context || ! $this->compatibility->feature_output_allowed( 'meta' ) ) {
			return $fields;
		}

		foreach ( ( new Lightweight_SEO_Meta_Tags_Service( $this->page_context ) )->get_meta_tags() as $tag ) {
			if ( 'name' === $tag['attribute'] && 'description' === $tag['key'] && '' === $fields['description'] ) {
				$fields['description'] = $tag['value'];
			} elseif ( 'property' === $tag['attribute'] && 'og:image' === $tag['key'] && '' === $fields['image'] ) {
				$fields['image'] = $tag['value'];
			}
		}

		return $fields;
	}

	/** The title of the post, then its content after the_content filters, for the page requested of a split post. */
	private function build_singular_body( $post ) {
		$content = '';

		// The main query holds this post; the_post() sets up the page a post split with <!--nextpage--> was asked for.
		if ( have_posts() ) {
			the_post();

			$content = str_replace( ']]>', ']]&gt;', (string) apply_filters( 'the_content', get_the_content() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The WordPress filter that renders content.
		}

		rewind_posts();
		wp_reset_postdata();

		$converter = new Lightweight_SEO_Markdown_Converter( (string) get_permalink( $post ) );

		return trim( Lightweight_SEO_Markdown_Converter::heading( self::plain_text( get_the_title( $post ) ) ) . "\n\n" . $converter->convert( $content ) );
	}

	/** A heading, then the posts of the main query as linked titles, each with its excerpt. */
	private function build_listing_body( $view, $post ) {
		$heading = 'posts' === $view && $post ? get_the_title( $post ) : get_bloginfo( 'name' );
		$items   = array();

		while ( have_posts() ) {
			the_post();

			$url = (string) get_permalink();

			if ( '' === $url ) {
				continue;
			}

			$title = self::plain_text( get_the_title() );
			$line  = '- [' . Lightweight_SEO_Markdown_Converter::escape_line( '' !== $title ? $title : $url ) . '](' . Lightweight_SEO_Markdown_Converter::destination( $url ) . ')';

			// A protected post's excerpt only says that it is protected.
			$excerpt = post_password_required() ? '' : self::plain_text( get_the_excerpt() );

			if ( '' !== $excerpt ) {
				$line .= ': ' . Lightweight_SEO_Markdown_Converter::escape_line( $excerpt );
			}

			$items[] = $line;
		}

		rewind_posts();
		wp_reset_postdata();

		return trim( Lightweight_SEO_Markdown_Converter::heading( self::plain_text( $heading ) ) . "\n\n" . implode( "\n", $items ) );
	}

	/** The schema graph the head prints for this request, as JSON, or an empty string when it prints none. */
	private function get_schema_json() {
		if ( ! $this->page_context || ! $this->compatibility->feature_output_allowed( 'schema' ) ) {
			return '';
		}

		$graph = ( new Lightweight_SEO_Schema_Service( $this->page_context, $this->settings ) )->get_graph();

		if ( empty( $graph ) ) {
			return '';
		}

		return (string) wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
	}

	/** The canonical address the head prints, or an empty string when the plugin prints none. */
	private function get_canonical( $view, $post ) {
		if ( ! $this->page_context || ! $this->compatibility->feature_output_allowed( 'canonical' ) ) {
			return '';
		}

		// WordPress prints a singular canonical, through the filter the plugin adds; the plugin prints the others.
		if ( 'singular' === $view ) {
			return $post ? esc_url_raw( (string) wp_get_canonical_url( $post ) ) : '';
		}

		$context = $this->page_context->get_context();

		return empty( $context['is_404'] ) ? esc_url_raw( (string) ( $context['canonical_url'] ?? '' ) ) : '';
	}

	/** The robots directives the meta tag prints, when they restrict anything. */
	private function get_robots() {
		return self::restrictive_robots( apply_filters( 'wp_robots', array() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The WordPress filter that builds the robots meta tag.
	}

	/** Reduce HTML-ish text, such as a filtered title, to plain text on one line. */
	private static function plain_text( $value ) {
		$value = html_entity_decode( wp_strip_all_tags( (string) $value, true ), ENT_QUOTES, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', str_replace( "\xC2\xA0", ' ', $value ) ) );
	}

	/** A header value on one line. */
	private static function header_value( $value ) {
		return trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/', ' ', (string) $value ) );
	}
}
