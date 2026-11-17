<?php
/**
 * Read-only report of what Lightweight SEO sends for the current request.
 *
 * @since      1.1.0
 * @package    Lightweight_SEO
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Answers an administrator's inspect request with a report instead of the page.
 *
 * WordPress has resolved the request exactly as it would for a visitor, so the
 * report reads the same context, tags, and graph the page would print. This
 * file is loaded only for such a request.
 */
class Lightweight_SEO_Inspector {

	/** Query argument that asks for the report: 1 for a standalone page, "fragment" for the Tools tab. */
	const QUERY_ARG = LIGHTWEIGHT_SEO_INSPECT_ARG;

	/** Nonce action of an inspect request. */
	const NONCE_ACTION = LIGHTWEIGHT_SEO_INSPECT_ARG;

	/** Response header that tells the Tools tab it received a report and not the page. */
	const HEADER = 'X-Lightweight-SEO-Inspect';

	private $api;
	private $meta_tags;
	private $schema;
	private $settings;
	private $post_meta;
	private $archive_meta;
	private $compatibility;

	/**
	 * Initialize the inspector.
	 *
	 * @since    1.1.0
	 * @param    Lightweight_SEO_API                      $api              Public API facade.
	 * @param    Lightweight_SEO_Meta_Tags_Service        $meta_tags        Meta tag service of this request.
	 * @param    Lightweight_SEO_Schema_Service           $schema           Schema service of this request.
	 * @param    Lightweight_SEO_Settings                 $settings         Shared settings service.
	 * @param    Lightweight_SEO_Post_Meta                $post_meta        Shared post meta service.
	 * @param    Lightweight_SEO_Archive_Meta             $archive_meta     Shared archive meta service.
	 * @param    Lightweight_SEO_Compatibility_Service    $compatibility    Safe mode detection.
	 */
	public function __construct( $api, $meta_tags, $schema, $settings, $post_meta, $archive_meta, $compatibility ) {
		$this->api           = $api;
		$this->meta_tags     = $meta_tags;
		$this->schema        = $schema;
		$this->settings      = $settings;
		$this->post_meta     = $post_meta;
		$this->archive_meta  = $archive_meta;
		$this->compatibility = $compatibility;
	}

	/**
	 * Answer the request with the report when it is an authorized inspect request.
	 *
	 * @since    1.1.0
	 * @return   bool Whether the report was sent. False leaves the page to render as usual.
	 */
	public function respond() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return false;
		}

		$fragment = 'fragment' === sanitize_key( wp_unslash( $_GET[ self::QUERY_ARG ] ?? '' ) );
		$report   = $this->get_report();

		$this->send_headers();

		if ( $fragment ) {
			$this->render( $report );
		} else {
			$this->render_document( $report );
		}

		$this->finish();

		return true;
	}

	/**
	 * Send the headers of a report. Kept separate so the response can be observed in tests.
	 *
	 * The page being inspected may be a 404; the report about it is still a success.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	protected function send_headers() {
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		header( self::HEADER . ': 1' );
		header( 'X-Robots-Tag: noindex, nofollow' );
	}

	/**
	 * End the request. Kept separate so the response can be observed without ending the process.
	 *
	 * @since    1.1.0
	 * @return   void
	 */
	protected function finish() {
		exit;
	}

	/**
	 * Build the report of the current request.
	 *
	 * @since    1.1.0
	 * @return   array Address, safe mode state, and sections of rows.
	 */
	public function get_report() {
		$subject = $this->get_subject();
		$context = (array) $this->api->get_current_context();
		$tags    = $this->allowed( 'meta' ) ? (array) $this->meta_tags->get_meta_tags() : array();

		return array(
			'address'  => $this->get_address(),
			'plugins'  => (array) $this->compatibility->get_conflicting_plugins(),
			'features' => (array) $this->compatibility->get_suppressed_features(),
			'sections' => array_values(
				array_filter(
					array(
						$this->get_page_section( $subject ),
						$this->get_search_section( $subject ),
						$this->get_title_section( $subject ),
						$this->get_description_section( $subject, $context, $tags ),
						$this->get_canonical_section( $subject, $context ),
						$this->get_sharing_section( $tags ),
						$this->get_sitemap_section( $subject ),
						$this->get_schema_section(),
					)
				)
			),
		);
	}

	/**
	 * Tell whether an output feature is printed, or held back by safe mode.
	 *
	 * @param string $feature One of title, meta, robots, canonical, or schema.
	 * @return bool
	 */
	private function allowed( $feature ) {
		return (bool) $this->compatibility->feature_output_allowed( $feature );
	}

	/**
	 * Get the address being inspected, without the arguments that asked for the report.
	 *
	 * @return string
	 */
	private function get_address() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return Lightweight_SEO_Url::from_path( remove_query_arg( array( self::QUERY_ARG, '_wpnonce' ), $request_uri ) );
	}

	/**
	 * Describe what WordPress resolved the request to.
	 *
	 * @return array Kind, object ID, content type name and label, and the object's stored SEO meta.
	 */
	private function get_subject() {
		$subject = array(
			'kind'  => 'other',
			'id'    => 0,
			'type'  => '',
			'label' => '',
			'meta'  => array(),
			'post'  => null,
		);

		if ( is_404() ) {
			$subject['kind'] = 'not_found';
		} elseif ( is_singular() || ( is_home() && ! is_front_page() && get_option( 'page_for_posts' ) ) ) {
			$post_id = is_singular() ? get_queried_object_id() : absint( get_option( 'page_for_posts' ) );
			$post    = get_post( $post_id );
			$type    = (string) ( $post->post_type ?? '' );
			$object  = get_post_type_object( $type );

			$subject = array(
				'kind'  => 'post',
				'id'    => $post_id,
				'type'  => $type,
				'label' => (string) ( $object->labels->name ?? $type ),
				'meta'  => (array) $this->post_meta->get_all( $post_id ),
				'post'  => $post,
			);
		} elseif ( is_home() || is_front_page() ) {
			$subject['kind'] = 'home';
		} elseif ( is_search() ) {
			$subject['kind'] = 'search';
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term   = get_queried_object();
			$type   = (string) ( $term->taxonomy ?? '' );
			$object = get_taxonomy( $type );

			$subject['kind']  = 'term';
			$subject['id']    = (int) ( $term->term_id ?? 0 );
			$subject['type']  = $type;
			$subject['label'] = (string) ( $object->labels->name ?? $type );
			$subject['meta']  = $subject['id'] ? (array) $this->archive_meta->get_term_all( $subject['id'] ) : array();
		} elseif ( is_author() ) {
			$subject['kind'] = 'user';
			$subject['id']   = (int) get_queried_object_id();
			$subject['meta'] = $subject['id'] ? (array) $this->archive_meta->get_user_all( $subject['id'] ) : array();
		} elseif ( is_date() ) {
			$subject['kind'] = 'date';
		} elseif ( is_post_type_archive() ) {
			$object = get_queried_object();
			$type   = (string) ( $object->name ?? '' );

			$subject['kind']  = 'post_type_archive';
			$subject['type']  = $type;
			$subject['label'] = (string) ( $object->labels->name ?? $type );
		}

		return $subject;
	}

	/**
	 * Say what kind of page was resolved.
	 *
	 * @param array $subject Resolved subject of the request.
	 * @return array
	 */
	private function get_page_section( $subject ) {
		switch ( $subject['kind'] ) {
			case 'not_found':
				$kind = __( 'No page exists at this address (404).', 'lightweight-seo' );
				break;
			case 'post':
				if ( is_front_page() ) {
					$kind = __( 'The homepage, which is a page.', 'lightweight-seo' );
				} elseif ( ! is_singular() ) {
					$kind = __( 'The posts page.', 'lightweight-seo' );
				} else {
					/* translators: %s: plural name of a content type, such as Posts. */
					$kind = sprintf( __( 'A single item of the content type “%s”.', 'lightweight-seo' ), $subject['label'] );
				}
				break;
			case 'home':
				$kind = __( 'The homepage, which lists the latest posts.', 'lightweight-seo' );
				break;
			case 'search':
				$kind = __( 'A page of internal search results.', 'lightweight-seo' );
				break;
			case 'term':
				/* translators: %s: plural name of a taxonomy, such as Categories. */
				$kind = sprintf( __( 'An archive of the taxonomy “%s”.', 'lightweight-seo' ), $subject['label'] );
				break;
			case 'user':
				$kind = __( 'An author archive.', 'lightweight-seo' );
				break;
			case 'date':
				$kind = __( 'A date archive.', 'lightweight-seo' );
				break;
			case 'post_type_archive':
				/* translators: %s: plural name of a content type, such as Portfolio Items. */
				$kind = sprintf( __( 'The archive page of the content type “%s”.', 'lightweight-seo' ), $subject['label'] );
				break;
			default:
				$kind = __( 'A page of another kind.', 'lightweight-seo' );
		}

		$lines = array( $kind );
		$page  = max( absint( get_query_var( 'paged' ) ), absint( get_query_var( 'page' ) ) );

		if ( $page > 1 ) {
			/* translators: %d: page number of a paginated archive or post. */
			$lines[] = sprintf( __( 'This is page %d of it.', 'lightweight-seo' ), $page );
		}

		return array(
			'title' => __( 'Page', 'lightweight-seo' ),
			'rows'  => array( $this->row( 'not_found' === $subject['kind'] ? 'warning' : 'info', __( 'What WordPress found', 'lightweight-seo' ), $lines ) ),
		);
	}

	/**
	 * Report whether the page may be shown in search results, and why not when it may not.
	 *
	 * @param array $subject Resolved subject of the request.
	 * @return array
	 */
	private function get_search_section( $subject ) {
		$directives = array();

		// The same filter WordPress prints its robots meta tag from, so core, the theme, and other plugins are included.
		foreach ( (array) apply_filters( 'wp_robots', array() ) as $directive => $value ) {
			if ( is_string( $value ) ) {
				$directives[] = $directive . ':' . $value;
			} elseif ( $value ) {
				$directives[] = $directive;
			}
		}

		$hidden  = in_array( 'noindex', $directives, true );
		$reasons = $this->get_hidden_reasons( $subject );
		$rows    = array();

		if ( ! $hidden ) {
			$rows[] = $this->row( 'success', __( 'Search results', 'lightweight-seo' ), __( 'Search engines may show this page.', 'lightweight-seo' ) );
		} else {
			if ( empty( $reasons ) ) {
				$reasons[] = __( 'Lightweight SEO does not hide it: the noindex directive comes from WordPress, the theme, or another plugin.', 'lightweight-seo' );
			}

			$rows[] = $this->row( 'warning', __( 'Search results', 'lightweight-seo' ), array_merge( array( __( 'Search engines are asked not to show this page.', 'lightweight-seo' ) ), $reasons ) );
		}

		$own_choice = (string) ( $subject['meta']['seo_noindex'] ?? '' );

		if ( ! $hidden && Lightweight_SEO_Settings::INDEX_OVERRIDE === $own_choice && $this->is_type_hidden( $subject ) ) {
			$rows[] = $this->row( 'info', __( 'Exception', 'lightweight-seo' ), __( 'Its content type is hidden from search results, but this one is set to “Show in search results”.', 'lightweight-seo' ) );
		}

		$robots_lines = array( empty( $directives ) ? __( 'No robots meta tag is printed.', 'lightweight-seo' ) : implode( ', ', $directives ) );

		if ( ! $this->allowed( 'robots' ) ) {
			$robots_lines[] = __( 'Safe mode: Lightweight SEO adds no directive of its own.', 'lightweight-seo' );
		}

		$rows[] = $this->row( 'info', __( 'Robots directives', 'lightweight-seo' ), $robots_lines );

		return array(
			'title' => __( 'Search results', 'lightweight-seo' ),
			'rows'  => $rows,
		);
	}

	/**
	 * Tell whether the content type of the subject is hidden from search results.
	 *
	 * @param array $subject Resolved subject of the request.
	 * @return bool
	 */
	private function is_type_hidden( $subject ) {
		if ( 'post' === $subject['kind'] ) {
			return $this->settings->is_post_type_noindex( $subject['type'] );
		}

		if ( 'term' === $subject['kind'] ) {
			return $this->settings->is_taxonomy_noindex( $subject['type'] );
		}

		return 'user' === $subject['kind'] && $this->settings->author_archives_noindex_enabled();
	}

	/**
	 * List every reason Lightweight SEO or WordPress hides the page, in the words of the settings screen.
	 *
	 * @param array $subject Resolved subject of the request.
	 * @return array
	 */
	private function get_hidden_reasons( $subject ) {
		$reasons    = array();
		$own_choice = (string) ( $subject['meta']['seo_noindex'] ?? '' );

		if ( '0' === (string) get_option( 'blog_public', '1' ) ) {
			$reasons[] = __( 'WordPress is set to discourage search engines from indexing this site (Settings → Reading).', 'lightweight-seo' );
		}

		if ( 'not_found' === $subject['kind'] ) {
			$reasons[] = __( 'A missing page is never shown.', 'lightweight-seo' );

			return $reasons;
		}

		if ( ! $this->allowed( 'robots' ) ) {
			return $reasons;
		}

		if ( 'post' === $subject['kind'] && ! empty( $subject['post'] ) && post_password_required( $subject['post'] ) ) {
			$reasons[] = __( 'The page is protected by a password.', 'lightweight-seo' );
		}

		if ( '1' === $own_choice ) {
			$reasons[] = 'post' === $subject['kind']
				? __( 'This page is set to “Hide from search results” in its SEO settings.', 'lightweight-seo' )
				: __( 'This archive is set to “Hide from search results” in its SEO settings.', 'lightweight-seo' );
		} elseif ( Lightweight_SEO_Settings::INDEX_OVERRIDE !== $own_choice && $this->is_type_hidden( $subject ) ) {
			if ( 'user' === $subject['kind'] ) {
				$reasons[] = __( 'Author archives are hidden on the “Content and indexation” tab, and this author follows that.', 'lightweight-seo' );
			} elseif ( 'post' === $subject['kind'] && 'attachment' === $subject['type'] ) {
				$reasons[] = __( 'Media pages are hidden on the “Content and indexation” tab, and this one follows that.', 'lightweight-seo' );
			} else {
				/* translators: %s: plural name of a content type or taxonomy, such as Posts or Tags. */
				$reasons[] = sprintf( __( '“%s” is hidden on the “Content and indexation” tab, and this one follows that.', 'lightweight-seo' ), $subject['label'] );
			}
		}

		if ( 'search' === $subject['kind'] ) {
			$reasons[] = $this->settings->search_results_noindex_enabled()
				? __( 'Internal search pages are hidden on the “Content and indexation” tab.', 'lightweight-seo' )
				: __( 'WordPress hides its own search result pages.', 'lightweight-seo' );
		}

		if ( 'date' === $subject['kind'] && $this->settings->date_archives_noindex_enabled() ) {
			$reasons[] = __( 'Date archives are hidden on the “Content and indexation” tab.', 'lightweight-seo' );
		}

		if ( 'post_type_archive' === $subject['kind'] && $this->settings->is_post_type_noindex( $subject['type'] ) ) {
			/* translators: %s: plural name of a content type, such as Portfolio Items. */
			$reasons[] = sprintf( __( '“%s” is hidden on the “Content and indexation” tab, which also hides its archive page.', 'lightweight-seo' ), $subject['label'] );
		}

		return $reasons;
	}

	/**
	 * Report the title tag and where it comes from.
	 *
	 * @param array $subject Resolved subject of the request.
	 * @return array
	 */
	private function get_title_section( $subject ) {
		$title = Lightweight_SEO_Text::plain( html_entity_decode( (string) wp_get_document_title(), ENT_QUOTES, 'UTF-8' ) );
		$own   = '' !== trim( (string) ( $subject['meta']['seo_title'] ?? '' ) );

		if ( ! $this->allowed( 'title' ) ) {
			$source = __( 'Safe mode: the title is written by WordPress or another plugin, not by Lightweight SEO.', 'lightweight-seo' );
		} elseif ( 'not_found' === $subject['kind'] || 'other' === $subject['kind'] ) {
			$source = __( 'Written by WordPress; Lightweight SEO has no format for this kind of page.', 'lightweight-seo' );
		} elseif ( $own ) {
			$source = 'post' === $subject['kind']
				? __( 'From this page’s own SEO title.', 'lightweight-seo' )
				: __( 'From this archive’s own SEO title.', 'lightweight-seo' );
		} elseif ( 'home' === $subject['kind'] || ( 'post' === $subject['kind'] && is_front_page() ) ) {
			$source = __( 'From the homepage title format.', 'lightweight-seo' );
		} elseif ( 'search' === $subject['kind'] ) {
			$source = __( 'From the search title format.', 'lightweight-seo' );
		} elseif ( in_array( $subject['kind'], array( 'post', 'term' ), true ) && '' !== $this->settings->get_content_type( 'post' === $subject['kind'] ? 'post_types' : 'taxonomies', $subject['type'] )['title_format'] ) {
			/* translators: %s: plural name of a content type or taxonomy, such as Posts or Tags. */
			$source = sprintf( __( 'From the title format of “%s” on the “Content and indexation” tab.', 'lightweight-seo' ), $subject['label'] );
		} elseif ( 'post' === $subject['kind'] ) {
			$source = __( 'From the default title format.', 'lightweight-seo' );
		} else {
			$source = __( 'From the archive title format.', 'lightweight-seo' );
		}

		/* translators: %d: number of characters in a title or description. */
		$length = sprintf( _n( '%d character.', '%d characters.', Lightweight_SEO_Text::length( $title ), 'lightweight-seo' ), Lightweight_SEO_Text::length( $title ) );

		return array(
			'title' => __( 'Title', 'lightweight-seo' ),
			'rows'  => array( $this->row( '' !== $title ? 'success' : 'warning', __( 'Title tag', 'lightweight-seo' ), array( '' !== $title ? $title : __( 'No title is sent.', 'lightweight-seo' ), $source, $length ) ) ),
		);
	}

	/**
	 * Report the meta description and where it comes from.
	 *
	 * @param array $subject Resolved subject of the request.
	 * @param array $context Public context of the request.
	 * @param array $tags    Printed meta tags.
	 * @return array
	 */
	private function get_description_section( $subject, $context, $tags ) {
		$description = '';

		foreach ( $tags as $tag ) {
			if ( 'description' === $tag['key'] ) {
				$description = (string) $tag['value'];
			}
		}

		$own = '' !== trim( (string) ( $subject['meta']['seo_description'] ?? '' ) );

		if ( ! $this->allowed( 'meta' ) ) {
			$lines = array( __( 'Safe mode: Lightweight SEO prints no description.', 'lightweight-seo' ) );
		} elseif ( '' === $description ) {
			$lines = array( __( 'No description is sent, so search engines pick text from the page.', 'lightweight-seo' ) );
		} else {
			if ( $own ) {
				$source = 'post' === $subject['kind']
					? __( 'From this page’s own meta description.', 'lightweight-seo' )
					: __( 'From this archive’s own meta description.', 'lightweight-seo' );
			} elseif ( 'home' === $subject['kind'] || ( 'post' === $subject['kind'] && is_front_page() && (string) $this->settings->get( 'meta_description', '' ) === (string) ( $context['description'] ?? '' ) ) ) {
				$source = __( 'From the homepage meta description on the “Search appearance” tab.', 'lightweight-seo' );
			} elseif ( 'post' === $subject['kind'] ) {
				$source = __( 'A short summary of the excerpt or content, because the page has no description of its own.', 'lightweight-seo' );
			} elseif ( 'term' === $subject['kind'] ) {
				$source = __( 'A short summary of the term’s description.', 'lightweight-seo' );
			} elseif ( 'user' === $subject['kind'] ) {
				$source = __( 'A short summary of the author’s biography.', 'lightweight-seo' );
			} else {
				$source = __( 'From the archive’s own description.', 'lightweight-seo' );
			}

			/* translators: %d: number of characters in a title or description. */
			$length = sprintf( _n( '%d character.', '%d characters.', Lightweight_SEO_Text::length( $description ), 'lightweight-seo' ), Lightweight_SEO_Text::length( $description ) );
			$lines  = array( $description, $source, $length );
		}

		return array(
			'title' => __( 'Description', 'lightweight-seo' ),
			'rows'  => array( $this->row( '' !== $description ? 'success' : 'info', __( 'Meta description', 'lightweight-seo' ), $lines ) ),
		);
	}

	/**
	 * Report the canonical address and whether it is custom.
	 *
	 * @param array $subject Resolved subject of the request.
	 * @param array $context Public context of the request.
	 * @return array
	 */
	private function get_canonical_section( $subject, $context ) {
		// WordPress prints the canonical of a singular page itself; the plugin prints it for everything else.
		$canonical = is_singular() ? (string) wp_get_canonical_url() : ( $this->allowed( 'canonical' ) ? (string) ( $context['canonical_url'] ?? '' ) : '' );

		if ( '' === $canonical ) {
			$lines = array( 'not_found' === $subject['kind'] ? __( 'None is sent for a missing page.', 'lightweight-seo' ) : __( 'No canonical address is sent.', 'lightweight-seo' ) );
		} elseif ( ! $this->allowed( 'canonical' ) ) {
			$lines = array( $canonical, __( 'Safe mode: decided by WordPress or another plugin, not by Lightweight SEO.', 'lightweight-seo' ) );
		} elseif ( '' !== trim( (string) ( $subject['meta']['seo_canonical_url'] ?? '' ) ) ) {
			$lines = array( $canonical, __( 'A custom address, set in the SEO settings of this page or archive.', 'lightweight-seo' ) );
		} else {
			$lines = array( $canonical, __( 'The page’s own address.', 'lightweight-seo' ) );
		}

		return array(
			'title' => __( 'Canonical', 'lightweight-seo' ),
			'rows'  => array( $this->row( 'info', __( 'Canonical address', 'lightweight-seo' ), $lines ) ),
		);
	}

	/**
	 * List the sharing and verification tags exactly as they are printed.
	 *
	 * @param array $tags Printed meta tags.
	 * @return array
	 */
	private function get_sharing_section( $tags ) {
		$printed = array();

		foreach ( $tags as $tag ) {
			if ( 'description' !== $tag['key'] ) {
				$printed[ (string) $tag['key'] ] = (string) $tag['value'];
			}
		}

		$section = array(
			'title' => __( 'Sharing and other tags', 'lightweight-seo' ),
			'rows'  => array(),
			'tags'  => $printed,
		);

		if ( ! $this->allowed( 'meta' ) ) {
			$section['rows'][] = $this->row( 'info', __( 'Open Graph and X tags', 'lightweight-seo' ), __( 'Safe mode: Lightweight SEO prints none.', 'lightweight-seo' ) );
		} elseif ( empty( $printed ) ) {
			$section['rows'][] = $this->row( 'info', __( 'Open Graph and X tags', 'lightweight-seo' ), __( 'None are sent for this page.', 'lightweight-seo' ) );
		} elseif ( empty( $printed['og:image'] ) ) {
			$section['rows'][] = $this->row( 'info', __( 'Share image', 'lightweight-seo' ), __( 'No image is sent, so a share shows text only.', 'lightweight-seo' ) );
		}

		return $section;
	}

	/**
	 * Say whether Lightweight SEO leaves the object out of the XML sitemap.
	 *
	 * @param array $subject Resolved subject of the request.
	 * @return array|null Section, or null for a page that is not a sitemap entry of its own.
	 */
	private function get_sitemap_section( $subject ) {
		if ( ! in_array( $subject['kind'], array( 'post', 'term', 'user' ), true ) ) {
			return null;
		}

		$own_choice = (string) ( $subject['meta']['seo_noindex'] ?? '' );
		$hidden     = $this->settings->resolve_noindex( $own_choice, $this->is_type_hidden( $subject ) );
		$protected  = 'post' === $subject['kind'] && ! empty( $subject['post'] ) && '' !== (string) ( $subject['post']->post_password ?? '' );
		$excluding  = $this->settings->exclude_noindex_from_sitemaps_enabled();
		$reason     = '';

		if ( empty( $this->api->get_sitemap_urls() ) ) {
			$reason = __( 'WordPress sitemaps are switched off on this site.', 'lightweight-seo' );
		} elseif ( 'post' === $subject['kind'] && 'attachment' === $subject['type'] ) {
			$reason = __( 'WordPress does not list media pages in its sitemap.', 'lightweight-seo' );
		} elseif ( ( 'post' === $subject['kind'] && ! $this->settings->is_post_type_in_sitemap( $subject['type'] ) ) || ( 'term' === $subject['kind'] && ! $this->settings->is_taxonomy_in_sitemap( $subject['type'] ) ) ) {
			/* translators: %s: plural name of a content type or taxonomy, such as Posts or Tags. */
			$reason = sprintf( __( '“In sitemap” is off for “%s” on the “Content and indexation” tab.', 'lightweight-seo' ), $subject['label'] );
		} elseif ( $excluding && ( $hidden || $protected ) ) {
			$reason = __( 'It is hidden from search results, and hidden pages are left out of the sitemap.', 'lightweight-seo' );
		}

		$row = '' !== $reason
			? $this->row( 'info', __( 'XML sitemap', 'lightweight-seo' ), array( __( 'Left out.', 'lightweight-seo' ), $reason ) )
			: $this->row( 'success', __( 'XML sitemap', 'lightweight-seo' ), array( __( 'Lightweight SEO does not leave it out.', 'lightweight-seo' ), __( 'WordPress lists published content; an address that a redirect rule sends elsewhere is left out.', 'lightweight-seo' ) ) );

		return array(
			'title' => __( 'Sitemap', 'lightweight-seo' ),
			'rows'  => array( $row ),
		);
	}

	/**
	 * Report the structured data graph exactly as it is printed.
	 *
	 * @return array
	 */
	private function get_schema_section() {
		$section = array(
			'title' => __( 'Structured data', 'lightweight-seo' ),
			'rows'  => array(),
		);

		if ( ! $this->allowed( 'schema' ) ) {
			$section['rows'][] = $this->row( 'info', __( 'Schema', 'lightweight-seo' ), __( 'Safe mode: Lightweight SEO prints none.', 'lightweight-seo' ) );

			return $section;
		}

		$graph = (array) $this->schema->get_graph();

		if ( empty( $graph ) ) {
			$section['rows'][] = $this->row( 'info', __( 'Schema', 'lightweight-seo' ), $this->settings->schema_output_enabled() ? __( 'None is sent for this page.', 'lightweight-seo' ) : __( 'Core schema output is switched off on the “Schema and identity” tab.', 'lightweight-seo' ) );

			return $section;
		}

		$types = array();

		foreach ( $graph as $node ) {
			$types[] = implode( ', ', (array) ( $node['@type'] ?? __( 'Untyped node', 'lightweight-seo' ) ) );
		}

		$section['rows'][] = $this->row( 'success', __( 'Schema', 'lightweight-seo' ), implode( ', ', $types ) );
		$section['code']   = (string) wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
		);

		return $section;
	}

	/**
	 * Describe one row of the report.
	 *
	 * @param string       $state One of success, warning, or info.
	 * @param string       $title What the row is about.
	 * @param string|array $lines What is sent, followed by where it comes from.
	 * @return array
	 */
	private function row( $state, $title, $lines ) {
		return array(
			'state' => $state,
			'title' => $title,
			'lines' => array_values( array_filter( (array) $lines, 'strlen' ) ),
		);
	}

	/**
	 * Print the report as a fragment for the Tools tab.
	 *
	 * @since    1.1.0
	 * @param    array    $report    Report from get_report().
	 * @return   void
	 */
	public function render( $report ) {
		echo '<div class="lwseo-inspect__result">';
		echo '<p class="lwseo-inspect__address">' . esc_html__( 'Report for', 'lightweight-seo' ) . ' <code>' . esc_html( $report['address'] ) . '</code></p>';

		if ( ! empty( $report['features'] ) ) {
			Lightweight_SEO_Admin_UI::callout(
				/* translators: 1: comma-separated list of active SEO plugins, 2: comma-separated list of outputs. */
				sprintf( __( 'Safe mode is on because %1$s is also active. Lightweight SEO is holding back: %2$s.', 'lightweight-seo' ), implode( ', ', $report['plugins'] ), implode( ', ', $report['features'] ) ),
				'warning'
			);
		}

		foreach ( $report['sections'] as $section ) {
			echo '<div class="lwseo-inspect__section"><h3 class="lwseo-inspect__heading">' . esc_html( $section['title'] ) . '</h3>';

			if ( ! empty( $section['rows'] ) ) {
				echo '<ul class="lwseo-status-list">';

				foreach ( $section['rows'] as $row ) {
					Lightweight_SEO_Admin_UI::status( $row['state'], $row['title'], $row['lines'] );
				}

				echo '</ul>';
			}

			if ( ! empty( $section['tags'] ) ) {
				echo '<div class="lwseo-table-wrap"><table class="lwseo-table"><tbody>';

				foreach ( $section['tags'] as $name => $value ) {
					echo '<tr><th scope="row"><code>' . esc_html( $name ) . '</code></th><td>' . esc_html( $value ) . '</td></tr>';
				}

				echo '</tbody></table></div>';
			}

			if ( ! empty( $section['code'] ) ) {
				echo '<details class="lwseo-details"><summary>' . esc_html__( 'Show the JSON', 'lightweight-seo' ) . '</summary><div class="lwseo-details__body"><pre class="lwseo-log">' . esc_html( $section['code'] ) . '</pre></div></details>';
			}

			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Print the report as a small page of its own, for a browser without JavaScript.
	 *
	 * @since    1.1.0
	 * @param    array    $report    Report from get_report().
	 * @return   void
	 */
	private function render_document( $report ) {
		$styles = array(
			includes_url( 'css/dashicons.min.css' ),
			LIGHTWEIGHT_SEO_PLUGIN_URL . 'admin/css/lightweight-seo-admin.css?ver=' . rawurlencode( LIGHTWEIGHT_SEO_VERSION ),
		);

		echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_language_attributes() returns escaped attributes.
		echo '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">';
		echo '<title>' . esc_html__( 'SEO report', 'lightweight-seo' ) . '</title>';

		foreach ( $styles as $style ) {
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- A standalone document outside the theme, where nothing is enqueued.
			echo '<link rel="stylesheet" href="' . esc_url( $style ) . '">';
		}

		echo '</head><body class="lwseo lwseo-inspect-page"><h1>' . esc_html__( 'SEO report', 'lightweight-seo' ) . '</h1>';

		$this->render( $report );

		echo '</body></html>';
	}
}
