<?php
/**
 * HTML to Markdown converter for Markdown on request.
 *
 * @since 1.3.0
 * @package Lightweight_SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument names its properties in camelCase.

/**
 * Turns an HTML fragment into CommonMark with GFM tables and strikethrough.
 *
 * Pure: an HTML string goes in and a Markdown string comes out, built on DOMDocument with
 * no WordPress call. The same input always gives the same output: no trailing whitespace on a
 * line, one blank line between blocks, and one newline at the end. Text is escaped so content
 * cannot turn into Markdown structure by accident. The file is loaded only when a Markdown
 * document is being built, never on an ordinary page view.
 */
class Lightweight_SEO_Markdown_Converter {

	/** Stands for a <br> while a paragraph is assembled. Control characters are removed from text, so it cannot occur there. */
	const BREAK_MARK = "\x1E";

	/** Elements that start a block of their own. */
	const BLOCK_TAGS = array( 'address', 'article', 'aside', 'blockquote', 'body', 'caption', 'center', 'dd', 'details', 'dialog', 'div', 'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'html', 'legend', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'summary', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'ul' );

	/** Elements that stay inside a line of text, even when they hold a block. */
	const INLINE_TAGS = array( 'a', 'abbr', 'acronym', 'audio', 'b', 'bdi', 'bdo', 'big', 'br', 'cite', 'code', 'data', 'del', 'dfn', 'em', 'embed', 'font', 'i', 'iframe', 'img', 'ins', 'kbd', 'label', 'mark', 'object', 'picture', 'q', 's', 'samp', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'time', 'tt', 'u', 'var', 'video', 'wbr' );

	/** Elements dropped with their content: code, styling, drawings, forms, and what a document head holds. */
	const DROP_TAGS = array( 'area', 'base', 'button', 'canvas', 'datalist', 'form', 'head', 'input', 'link', 'map', 'meta', 'noscript', 'optgroup', 'option', 'output', 'param', 'script', 'select', 'source', 'style', 'svg', 'template', 'textarea', 'title', 'track' );

	/** Elements shown as a link to what they embed. */
	const MEDIA_TAGS = array( 'audio', 'embed', 'iframe', 'object', 'video' );

	/** @var string Address relative links are resolved against. */
	private $base_url;

	/** @var int[] How deeply the converter is inside each kind of emphasis, so none is opened twice. */
	private $depth = array(
		'**' => 0,
		'*'  => 0,
		'~~' => 0,
	);

	/** @var bool Whether the converter is inside a link, which cannot hold another one. */
	private $in_link = false;

	/**
	 * @param string $base_url Absolute address of the page the HTML belongs to. Without one, relative links keep only their text.
	 */
	public function __construct( $base_url = '' ) {
		$this->base_url = (string) $base_url;
	}

	/**
	 * Convert an HTML fragment.
	 *
	 * @param string $html HTML, UTF-8.
	 * @return string Markdown ending in one newline, or an empty string when nothing is left.
	 */
	public function convert( $html ) {
		$html = self::scrub_utf8( (string) $html );

		if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
			return '';
		}

		$document = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		// The http-equiv line makes libxml read the fragment as UTF-8 on every version, without converting it first.
		$loaded = $document->loadHTML( '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$body = $loaded ? $document->getElementsByTagName( 'body' )->item( 0 ) : null;

		if ( ! $body ) {
			return '';
		}

		self::unnest_empty_elements( $document );

		$this->in_link = false;
		$this->depth   = array_fill_keys( array_keys( $this->depth ), 0 );

		$markdown = preg_replace( '/[ \t]+$/m', '', implode( "\n\n", $this->render_blocks( $body ) ) );
		$markdown = trim( (string) $markdown, "\n" );

		return '' === $markdown ? '' : $markdown . "\n";
	}

	/**
	 * Escape plain text so it reads as itself in Markdown, wherever it is placed on a line.
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	public static function escape_line( $text ) {
		return self::escape_line_start( self::escape_text( self::collapse( self::clean_text( $text ) ) ) );
	}

	/**
	 * Build an ATX heading from plain text.
	 *
	 * @param string $text  Plain text.
	 * @param int    $level 1 to 6.
	 * @return string An empty string when the text is empty.
	 */
	public static function heading( $text, $level = 1 ) {
		$text = self::heading_text( self::escape_text( self::collapse( self::clean_text( $text ) ) ) );

		return '' === $text ? '' : str_repeat( '#', max( 1, min( 6, (int) $level ) ) ) . ' ' . $text;
	}

	/**
	 * Build a fenced code block whose fence is longer than any run of backticks in the code.
	 *
	 * @param string $code     Code, kept as it is apart from line endings and trailing whitespace.
	 * @param string $language Info string, such as json.
	 * @return string
	 */
	public static function fenced_code( $code, $language = '' ) {
		$code  = rtrim( preg_replace( '/[ \t]+$/m', '', str_replace( array( "\r\n", "\r" ), "\n", (string) $code ) ) );
		$fence = str_repeat( '`', max( 3, self::longest_run( $code, '`' ) + 1 ) );

		return $fence . preg_replace( '/[^A-Za-z0-9_+#.\-]/', '', (string) $language ) . "\n" . $code . "\n" . $fence;
	}

	/**
	 * Make a link destination safe to print between parentheses.
	 *
	 * @param string $url Absolute address.
	 * @return string
	 */
	public static function destination( $url ) {
		return (string) preg_replace_callback(
			'/[\s<>()\\\\\x00-\x1F\x7F]/',
			static function ( $matches ) {
				return rawurlencode( $matches[0] );
			},
			(string) $url
		);
	}

	/**
	 * Resolve an address against a base address, as a browser does.
	 *
	 * @param string $url  Address as written, absolute or relative.
	 * @param string $base Absolute base address.
	 * @return string The absolute address, or an empty string when it cannot be made absolute.
	 */
	public static function resolve_url( $url, $base ) {
		// Browsers remove tabs and line breaks anywhere in an address, and spaces around it.
		$url = trim( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', (string) $url ) );

		if ( '' === $url ) {
			return '';
		}

		if ( 1 === preg_match( '/^([A-Za-z][A-Za-z0-9+.\-]*):/', $url, $matches ) ) {
			return strtolower( $matches[1] ) . substr( $url, strlen( $matches[1] ) );
		}

		$parts = parse_url( (string) $base ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- The converter makes no WordPress call; PHP 7.4 parses addresses correctly.

		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		if ( 0 === strpos( $url, '//' ) ) {
			return strtolower( $parts['scheme'] ) . ':' . $url;
		}

		$origin = strtolower( $parts['scheme'] ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' );
		$path   = isset( $parts['path'] ) && '' !== $parts['path'] ? $parts['path'] : '/';
		$query  = isset( $parts['query'] ) ? '?' . $parts['query'] : '';

		if ( '#' === $url[0] ) {
			return $origin . $path . $query . $url;
		}

		if ( '?' === $url[0] ) {
			return $origin . $path . $url;
		}

		$target = '/' === $url[0] ? $url : substr( $path, 0, (int) strrpos( $path, '/' ) + 1 ) . $url;
		$split  = strcspn( $target, '?#' );

		return $origin . self::remove_dot_segments( substr( $target, 0, $split ) ) . substr( $target, $split );
	}

	/* Blocks ------------------------------------------------------------------ */

	/**
	 * Render the children of a container as blocks. Inline content between blocks becomes a paragraph.
	 *
	 * @param DOMNode $container Container.
	 * @return string[]
	 */
	private function render_blocks( $container ) {
		$blocks = array();
		$inline = '';

		foreach ( $container->childNodes as $child ) {
			if ( ! $this->is_block( $child ) ) {
				$inline .= $this->render_inline( $child );
				continue;
			}

			$blocks = array_merge( $blocks, $this->paragraph_blocks( $inline ), $this->render_block( $child ) );
			$inline = '';
		}

		return array_values( array_filter( array_merge( $blocks, $this->paragraph_blocks( $inline ) ), 'strlen' ) );
	}

	/**
	 * Render one block-level element.
	 *
	 * @param DOMElement $element Element.
	 * @return string[]
	 */
	private function render_block( $element ) {
		$tag = strtolower( $element->nodeName );

		switch ( $tag ) {
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$text = $this->assemble( $this->render_children_inline( $element ), 'heading' );

				return '' === $text ? array() : array( str_repeat( '#', (int) substr( $tag, 1 ) ) . ' ' . $text );
			case 'p':
				return $this->paragraph_blocks( $this->render_children_inline( $element ) );
			case 'hr':
				return array( '---' );
			case 'pre':
				return $this->render_code_block( $element );
			case 'blockquote':
				return $this->render_quote( $element );
			case 'ul':
			case 'ol':
				return $this->render_list( $element, 'ol' === $tag );
			case 'table':
				return $this->render_table( $element );
			case 'dt':
			case 'summary':
				// A term or the visible line of a disclosure reads as a bold line above what it introduces.
				return $this->paragraph_blocks( $this->emphasize( '**', $element ) );
			default:
				return $this->render_blocks( $element );
		}
	}

	/** @return string[] */
	private function render_quote( $element ) {
		$inner = $this->render_blocks( $element );

		if ( empty( $inner ) ) {
			return array();
		}

		$lines = explode( "\n", implode( "\n\n", $inner ) );

		foreach ( $lines as $index => $line ) {
			$lines[ $index ] = '' === $line ? '>' : '> ' . $line;
		}

		return array( implode( "\n", $lines ) );
	}

	/** @return string[] */
	private function render_code_block( $element ) {
		$source = $element;
		$code   = null;

		// <pre><code class="language-php"> is how the block editor and most highlighters mark code.
		foreach ( $element->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType && 'code' === strtolower( $child->nodeName ) && null === $code ) {
				$code = $child;
			} elseif ( XML_ELEMENT_NODE === $child->nodeType || '' !== trim( (string) $child->nodeValue ) ) {
				$code = false;
			}
		}

		if ( $code ) {
			$source = $code;
		}

		$language = '';

		foreach ( array( $source, $element ) as $node ) {
			if ( '' === $language && 1 === preg_match( '/(?:^|\s)lang(?:uage)?-([A-Za-z0-9_+#.\-]+)/', (string) $node->getAttribute( 'class' ), $matches ) ) {
				$language = $matches[1];
			}
		}

		$text = str_replace( array( "\r\n", "\r", "\xC2\xA0" ), array( "\n", "\n", ' ' ), $this->preformatted_text( $source ) );
		$text = (string) preg_replace( '/[\x00-\x08\x0B-\x1F\x7F]/', '', $text );
		$text = (string) preg_replace( '/^(?:[ \t]*\n)+/', '', $text );

		return '' === trim( $text ) ? array() : array( self::fenced_code( $text, $language ) );
	}

	/** Text of preformatted content, where a <br> is a line break. */
	private function preformatted_text( $node ) {
		$text = '';

		foreach ( $node->childNodes as $child ) {
			if ( XML_TEXT_NODE === $child->nodeType || XML_CDATA_SECTION_NODE === $child->nodeType ) {
				$text .= $child->nodeValue;
			} elseif ( XML_ELEMENT_NODE === $child->nodeType ) {
				$tag = strtolower( $child->nodeName );

				if ( 'br' === $tag ) {
					$text .= "\n";
				} elseif ( ! in_array( $tag, self::DROP_TAGS, true ) ) {
					$text .= $this->preformatted_text( $child );
				}
			}
		}

		return $text;
	}

	/** @return string[] */
	private function render_list( $list_element, $ordered ) {
		$number = 1;

		if ( $ordered && 1 === preg_match( '/^\s*(\d{1,9})\s*$/', (string) $list_element->getAttribute( 'start' ), $matches ) ) {
			$number = (int) $matches[1];
		}

		$items = array();

		foreach ( $list_element->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType && 'li' === strtolower( $child->nodeName ) ) {
				$blocks = $this->render_blocks( $child );
			} elseif ( $this->is_block( $child ) ) {
				$blocks = $this->render_block( $child );
			} else {
				// Text or an inline element straight inside a list is shown as an item of its own.
				$blocks = $this->paragraph_blocks( $this->render_inline( $child ) );
			}

			if ( empty( $blocks ) ) {
				continue;
			}

			$items[] = $this->list_item( $blocks, $ordered ? $number . '.' : '-' );
			++$number;
		}

		return empty( $items ) ? array() : array( implode( "\n", $items ) );
	}

	/**
	 * Put a marker in front of an item and indent its other lines under the first.
	 *
	 * A paragraph never starts with a list marker, because escape_line_start() escapes one,
	 * so a block that starts with one is a nested list and follows the line above it directly.
	 */
	private function list_item( $blocks, $marker ) {
		$body = '';

		foreach ( $blocks as $index => $block ) {
			if ( $index > 0 ) {
				$body .= 1 === preg_match( '/^(?:-|\d+\.)(?: |$)/', $block ) ? "\n" : "\n\n";
			}

			$body .= $block;
		}

		$indent = str_repeat( ' ', strlen( $marker ) + 1 );
		$lines  = explode( "\n", $body );
		$item   = $marker . ( '' === $lines[0] ? '' : ' ' . $lines[0] );

		foreach ( array_slice( $lines, 1 ) as $line ) {
			$item .= "\n" . ( '' === $line ? '' : $indent . $line );
		}

		return $item;
	}

	/** @return string[] */
	private function render_table( $table ) {
		$blocks = array();
		$head   = array();
		$body   = array();

		foreach ( $table->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) {
				continue;
			}

			$tag = strtolower( $child->nodeName );

			if ( 'caption' === $tag ) {
				$blocks = array_merge( $blocks, $this->paragraph_blocks( $this->render_children_inline( $child ) ) );
			} elseif ( 'tr' === $tag ) {
				$body[] = $child;
			} elseif ( in_array( $tag, array( 'thead', 'tbody', 'tfoot' ), true ) ) {
				foreach ( $child->childNodes as $row ) {
					if ( XML_ELEMENT_NODE === $row->nodeType && 'tr' === strtolower( $row->nodeName ) ) {
						if ( 'thead' === $tag ) {
							$head[] = $row;
						} else {
							$body[] = $row;
						}
					}
				}
			}
		}

		$rows    = array();
		$columns = 0;

		foreach ( array_merge( $head, $body ) as $row ) {
			$cells = array();

			foreach ( $row->childNodes as $cell ) {
				if ( XML_ELEMENT_NODE !== $cell->nodeType || ! in_array( strtolower( $cell->nodeName ), array( 'td', 'th' ), true ) ) {
					continue;
				}

				$cells[] = str_replace( '|', '\\|', $this->assemble( $this->render_children_inline( $cell ), 'cell' ) );
				$span    = (int) $cell->getAttribute( 'colspan' );

				// A cell that spans columns is followed by empty ones, so the columns below stay in line.
				for ( $extra = 1; $extra < min( $span, 100 ); $extra++ ) {
					$cells[] = '';
				}
			}

			if ( ! empty( $cells ) ) {
				$rows[]  = $cells;
				$columns = max( $columns, count( $cells ) );
			}
		}

		if ( empty( $rows ) ) {
			return $blocks;
		}

		// GFM needs a header row: the table's own, or else its first row.
		$lines = array();

		foreach ( $rows as $index => $cells ) {
			$lines[] = '| ' . implode( ' | ', array_pad( $cells, $columns, '' ) ) . ' |';

			if ( 0 === $index ) {
				$lines[] = '| ' . implode( ' | ', array_fill( 0, $columns, '---' ) ) . ' |';
			}
		}

		$blocks[] = implode( "\n", $lines );

		return $blocks;
	}

	/* Inline content ---------------------------------------------------------- */

	private function render_children_inline( $element ) {
		$text = '';

		foreach ( $element->childNodes as $child ) {
			$text .= $this->render_inline( $child );
		}

		return $text;
	}

	/**
	 * Render a node as part of a line. Whitespace is collapsed later, when the paragraph is assembled.
	 *
	 * @param DOMNode $node Node.
	 * @return string
	 */
	private function render_inline( $node ) {
		if ( XML_TEXT_NODE === $node->nodeType || XML_CDATA_SECTION_NODE === $node->nodeType ) {
			return self::escape_text( self::clean_text( $node->nodeValue ) );
		}

		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			return '';
		}

		$tag = strtolower( $node->nodeName );

		if ( in_array( $tag, self::DROP_TAGS, true ) ) {
			return '';
		}

		if ( in_array( $tag, self::MEDIA_TAGS, true ) ) {
			return $this->render_media( $node );
		}

		switch ( $tag ) {
			case 'br':
				return self::BREAK_MARK;
			case 'wbr':
				return '';
			case 'strong':
			case 'b':
				return $this->emphasize( '**', $node );
			case 'em':
			case 'i':
				return $this->emphasize( '*', $node );
			case 'del':
			case 's':
			case 'strike':
				return $this->emphasize( '~~', $node );
			case 'code':
				return self::code_span( (string) $node->textContent );
			case 'a':
				return $this->render_link( $node );
			case 'img':
				return $this->render_image( $node );
		}

		// A block inside a line, such as a paragraph in a link, is kept apart from its neighbours by spaces.
		$text = $this->render_children_inline( $node );

		return in_array( $tag, self::BLOCK_TAGS, true ) ? ' ' . $text . ' ' : $text;
	}

	/** Wrap an element's content in an emphasis marker, unless the same emphasis is already open. */
	private function emphasize( $marker, $element ) {
		if ( $this->depth[ $marker ] > 0 ) {
			return $this->render_children_inline( $element );
		}

		++$this->depth[ $marker ];
		$text = $this->render_children_inline( $element );
		--$this->depth[ $marker ];

		list( $before, $core, $after ) = self::split_edges( $text );

		// "** bold**" is not emphasis, so the spaces at the edges go outside the markers.
		return '' === $core ? $text : $before . $marker . $core . $marker . $after;
	}

	private function render_link( $element ) {
		if ( $this->in_link ) {
			return $this->render_children_inline( $element );
		}

		$this->in_link = true;
		$text          = $this->render_children_inline( $element );
		$this->in_link = false;

		$url = self::resolve_url( $element->getAttribute( 'href' ), $this->base_url );

		list( $before, $core, $after ) = self::split_edges( $text );

		if ( '' === $core || ! in_array( self::scheme_of( $url ), array( 'http', 'https', 'mailto', 'tel' ), true ) ) {
			return $text;
		}

		return $before . '[' . $core . '](' . self::destination( $url ) . ')' . $after;
	}

	private function render_image( $element ) {
		$alt = self::escape_text( self::collapse( self::clean_text( $element->getAttribute( 'alt' ) ) ) );
		$src = '';

		// A lazy-loading plugin keeps the real address in a data attribute and a placeholder in src.
		foreach ( array( 'src', 'data-src', 'data-lazy-src' ) as $attribute ) {
			$candidate = self::resolve_url( $element->getAttribute( $attribute ), $this->base_url );

			if ( in_array( self::scheme_of( $candidate ), array( 'http', 'https' ), true ) ) {
				$src = $candidate;
				break;
			}
		}

		return '' === $src ? $alt : '![' . $alt . '](' . self::destination( $src ) . ')';
	}

	private function render_media( $element ) {
		$sources = array( $element->getAttribute( 'object' === strtolower( $element->nodeName ) ? 'data' : 'src' ) );

		// libxml does not know <source> is empty, so a second one ends up inside the first.
		foreach ( $element->getElementsByTagName( 'source' ) as $source ) {
			$sources[] = $source->getAttribute( 'src' );
		}

		foreach ( $sources as $source ) {
			$url = self::resolve_url( $source, $this->base_url );

			if ( in_array( self::scheme_of( $url ), array( 'http', 'https' ), true ) ) {
				$label = self::collapse( self::clean_text( $element->getAttribute( 'title' ) ) );

				return '[' . self::escape_text( '' !== $label ? $label : $url ) . '](' . self::destination( $url ) . ')';
			}
		}

		return '';
	}

	/* Paragraphs and text ----------------------------------------------------- */

	/** @return string[] A paragraph, or nothing when the content is blank. */
	private function paragraph_blocks( $inline ) {
		$text = $this->assemble( $inline, 'paragraph' );

		return '' === $text ? array() : array( $text );
	}

	/**
	 * Turn rendered inline content into the text of one block.
	 *
	 * @param string $inline Rendered content.
	 * @param string $mode   paragraph: hard breaks kept, line starts escaped; heading or cell: one line.
	 * @return string
	 */
	private function assemble( $inline, $mode ) {
		$text = self::collapse( $inline );

		if ( 'paragraph' !== $mode ) {
			$text = trim( self::collapse( str_replace( self::BREAK_MARK, ' ', $text ) ) );

			return 'heading' === $mode ? self::heading_text( $text ) : $text;
		}

		$lines = array();

		foreach ( explode( self::BREAK_MARK, $text ) as $line ) {
			$line = trim( $line, ' ' );

			if ( '' !== $line ) {
				$lines[] = self::escape_line_start( $line );
			}
		}

		// A backslash at the end of a line is a hard line break in CommonMark, and leaves no trailing space.
		return implode( "\\\n", $lines );
	}

	/** Escape a trailing run of # in a heading, which CommonMark would otherwise drop as a closing sequence. */
	private static function heading_text( $text ) {
		$text = trim( $text );

		return (string) preg_replace( '/(^|\s)#(#*)$/', '$1\\\\#$2', $text );
	}

	/**
	 * Escape what would start a block at the beginning of a line: a heading, quote, list item, or rule.
	 *
	 * Backticks, asterisks, underscores, and tildes are already escaped wherever they are.
	 */
	private static function escape_line_start( $line ) {
		if ( 1 === preg_match( '/^(?:#{1,6}(?:\s|$)|>|[-+](?:\s|$)|-[-\s]*$|=+\s*$)/', $line ) ) {
			return '\\' . $line;
		}

		return (string) preg_replace( '/^(\d{1,9})([.)])(?=\s|$)/', '$1\\\\$2', $line );
	}

	/**
	 * Escape the characters that make inline structure, without turning ordinary prose into noise.
	 *
	 * An underscore between two letters or digits cannot start emphasis, so snake_case stays as it
	 * is. A "<" is escaped only where it could open a tag, and a "&" only where it could be read as
	 * an entity. Tildes are escaped because GFM strikes through text between a pair of them.
	 */
	private static function escape_text( $text ) {
		$text = str_replace( '\\', '\\\\', (string) $text );
		$text = (string) preg_replace( '/([`*\[\]~])/', '\\\\$1', $text );
		$text = (string) preg_replace( '/<(?=[A-Za-z\/!?])/', '\\\\<', $text );
		$text = (string) preg_replace( '/&(?=#?[A-Za-z0-9]+;)/', '\\\\&', $text );

		return (string) preg_replace( '/(?<![\p{L}\p{N}])_|_(?![\p{L}\p{N}])/u', '\\\\_', $text );
	}

	/** A code span with a fence longer than any backtick run in the code. */
	private static function code_span( $code ) {
		list( $before, $code, $after ) = self::split_edges( self::collapse( self::clean_text( $code ) ) );

		if ( '' === $code ) {
			return $before . $after;
		}

		$fence = str_repeat( '`', self::longest_run( $code, '`' ) + 1 );
		// Code that starts or ends with a backtick needs a space between it and the fence, which CommonMark strips again.
		$pad = '`' === $code[0] || '`' === substr( $code, -1 ) ? ' ' : '';

		// Spaces at the edges go outside, where collapsing whitespace cannot change what the span holds.
		return $before . $fence . $pad . $code . $pad . $fence . $after;
	}

	/** Remove control characters, and turn a non-breaking space into a space. */
	private static function clean_text( $text ) {
		return (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', str_replace( "\xC2\xA0", ' ', (string) $text ) );
	}

	/** Collapse whitespace as HTML does. */
	private static function collapse( $text ) {
		return (string) preg_replace( '/[ \t\n\r\f]+/', ' ', (string) $text );
	}

	/** @return string[] Leading whitespace, the content, and trailing whitespace. */
	private static function split_edges( $text ) {
		preg_match( '/^([\s' . self::BREAK_MARK . ']*)(.*?)([\s' . self::BREAK_MARK . ']*)$/s', (string) $text, $matches );

		return array( $matches[1] ?? '', $matches[2] ?? '', $matches[3] ?? '' );
	}

	private static function longest_run( $text, $character ) {
		$longest = 0;

		if ( preg_match_all( '/' . preg_quote( $character, '/' ) . '+/', (string) $text, $matches ) ) {
			foreach ( $matches[0] as $run ) {
				$longest = max( $longest, strlen( $run ) );
			}
		}

		return $longest;
	}

	/* Structure --------------------------------------------------------------- */

	/**
	 * Move content out of the empty elements libxml does not know are empty.
	 *
	 * libxml parses HTML 4, so it puts everything after an <embed>, <source>, <track>, or <wbr>
	 * inside it: a paragraph after an embed, or the <img> of a <picture>, would be lost with it.
	 */
	private static function unnest_empty_elements( $document ) {
		foreach ( array( 'embed', 'source', 'track', 'wbr' ) as $tag ) {
			foreach ( iterator_to_array( $document->getElementsByTagName( $tag ) ) as $element ) {
				while ( $element->lastChild && $element->parentNode ) {
					$element->parentNode->insertBefore( $element->lastChild, $element->nextSibling );
				}
			}
		}
	}

	private function is_block( $node ) {
		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			return false;
		}

		$tag = strtolower( $node->nodeName );

		if ( in_array( $tag, self::BLOCK_TAGS, true ) ) {
			return true;
		}

		// An element this converter does not know is a container when it holds a block, and a run of text otherwise.
		return ! in_array( $tag, self::INLINE_TAGS, true ) && ! in_array( $tag, self::DROP_TAGS, true ) && $this->contains_block( $node );
	}

	private function contains_block( $element ) {
		foreach ( $element->getElementsByTagName( '*' ) as $descendant ) {
			if ( in_array( strtolower( $descendant->nodeName ), self::BLOCK_TAGS, true ) ) {
				return true;
			}
		}

		return false;
	}

	private static function scheme_of( $url ) {
		return 1 === preg_match( '/^([a-z][a-z0-9+.\-]*):/', (string) $url, $matches ) ? $matches[1] : '';
	}

	/** Remove "." and ".." segments from a path, as RFC 3986 section 5.2.4 does. */
	private static function remove_dot_segments( $path ) {
		$output   = array();
		$segments = explode( '/', $path );
		$last     = count( $segments ) - 1;

		foreach ( $segments as $index => $segment ) {
			if ( '.' === $segment || '..' === $segment ) {
				if ( '..' === $segment && count( $output ) > 1 ) {
					array_pop( $output );
				}

				// A path that ends in a dot segment ends in a slash.
				if ( $index === $last ) {
					$output[] = '';
				}

				continue;
			}

			$output[] = $segment;
		}

		$path = implode( '/', $output );

		return '/' === substr( $path, 0, 1 ) ? $path : '/' . $path;
	}

	/** Replace each byte that is not part of valid UTF-8 with U+FFFD, without mbstring or iconv. */
	private static function scrub_utf8( $text ) {
		if ( 1 === preg_match( '//u', $text ) ) {
			return $text;
		}

		return (string) preg_replace_callback(
			'/[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}|(.)/s',
			static function ( $matches ) {
				return isset( $matches[1] ) ? "\xEF\xBF\xBD" : $matches[0];
			},
			$text
		);
	}
}
