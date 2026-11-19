<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-markdown-converter.php';

use PHPUnit\Framework\TestCase;

/**
 * The HTML to Markdown converter behind Markdown on request.
 *
 * Each fixture is HTML and the exact Markdown expected for it, converted as part of the page
 * https://example.com/blog/post/. A warning from libxml would fail the test, because PHPUnit
 * turns warnings into errors.
 */
final class LightweightSEOMarkdownConverterTest extends TestCase {

	const BASE = 'https://example.com/blog/post/';

	/**
	 * @dataProvider fixtures
	 */
	public function test_fixture_converts_to_the_exact_markdown( string $html, string $expected ): void {
		$converter = new Lightweight_SEO_Markdown_Converter( self::BASE );
		$markdown  = $converter->convert( $html );

		$this->assertSame( $expected, $markdown );

		// Same input, same output, also from the same converter twice.
		$this->assertSame( $markdown, $converter->convert( $html ) );
		$this->assertSame( $markdown, ( new Lightweight_SEO_Markdown_Converter( self::BASE ) )->convert( $html ) );

		if ( '' !== $markdown ) {
			$this->assertDoesNotMatchRegularExpression( '/[ \t]$/m', $markdown, 'No line ends in whitespace.' );
			$this->assertStringEndsWith( "\n", $markdown );
			$this->assertStringEndsNotWith( "\n\n", $markdown );
			$this->assertStringNotContainsString( "\n\n\n", $markdown, 'One blank line between blocks.' );
		}
	}

	public function fixtures(): array {
		return array(
			// Elements one by one.
			'headings'                                    => array( '<h1>One</h1><h2>Two</h2><h3>Three</h3><h4>Four</h4><h5>Five</h5><h6>Six</h6><h3> </h3>', "# One\n\n## Two\n\n### Three\n\n#### Four\n\n##### Five\n\n###### Six\n" ),
			'a heading keeps a trailing hash'             => array( '<h2>Issue #</h2><h2>C#</h2>', "## Issue \\#\n\n## C#\n" ),
			'a heading with a break is one line'          => array( '<h2>First<br>second</h2>', "## First second\n" ),
			'paragraphs'                                  => array( '<p>One.</p><p>Two.</p>', "One.\n\nTwo.\n" ),
			'line break'                                  => array( '<p>One<br>two<br/><br>three<br></p>', "One\\\ntwo\\\nthree\n" ),
			'rule'                                        => array( '<p>a</p><hr class="wp-block-separator"/><p>b</p>', "a\n\n---\n\nb\n" ),
			'strong and emphasis'                         => array( '<p><strong>a</strong> <b>b</b> <em>c</em> <i>d</i></p>', "**a** **b** *c* *d*\n" ),
			'strikethrough'                               => array( '<p><del>old</del> <s>gone</s> <strike>x</strike></p>', "~~old~~ ~~gone~~ ~~x~~\n" ),
			'nested emphasis is opened once'              => array( '<p><strong>a <b>b</b> <em>c <i>d</i></em></strong></p>', "**a b *c d***\n" ),
			'spaces move outside emphasis'                => array( '<p>bare<b> spaced </b>end</p>', "bare **spaced** end\n" ),
			'empty emphasis is dropped'                   => array( '<p>a<strong> </strong>b<em></em>c</p>', "a bc\n" ),
			'inline code'                                 => array( '<p>Run <code>wp plugin list</code> now.</p>', "Run `wp plugin list` now.\n" ),
			'inline code with backticks'                  => array( '<p><code>a``b</code> <code>`x</code> <code> padded </code></p>', "```a``b``` `` `x `` `padded`\n" ),
			'inline code is not escaped'                  => array( '<p><code>*a* &lt;b&gt; [c]_d_</code></p>', "`*a* <b> [c]_d_`\n" ),
			'code block'                                  => array( "<pre>first\n  indented\n</pre>", "```\nfirst\n  indented\n```\n" ),
			'code block language and fence'               => array( "<pre><code class=\"language-php\">echo `x`;\n```\n</code></pre>", "````php\necho `x`;\n```\n````\n" ),
			'code block with markup inside'               => array( '<pre>line1<br>line2 <span class="k">if</span></pre>', "```\nline1\nline2 if\n```\n" ),
			'empty code block'                            => array( "<pre>\n  \n</pre><p>after</p>", "after\n" ),
			'blockquote'                                  => array( '<blockquote><p>Quoted.</p></blockquote>', "> Quoted.\n" ),
			'nested blockquote'                           => array( '<blockquote><p>q</p><blockquote><p>n</p></blockquote></blockquote>', "> q\n>\n> > n\n" ),
			'unordered list'                              => array( '<ul><li>a</li><li>b</li></ul>', "- a\n- b\n" ),
			'ordered list with start'                     => array( '<ol start="3"><li>three</li><li>four</li></ol>', "3. three\n4. four\n" ),
			'ordered list without a valid start'          => array( '<ol start="-2"><li>one</li></ol>', "1. one\n" ),
			'nested lists'                                => array( '<ul><li>a<ol><li>b<ul><li>c</li></ul></li></ol></li><li>d</li></ul>', "- a\n  1. b\n     - c\n- d\n" ),
			'list item with paragraphs'                   => array( '<ul><li><p>p1</p><p>p2</p></li><li>q</li></ul>', "- p1\n\n  p2\n- q\n" ),
			'empty list items are left out'               => array( '<ol><li></li><li>one</li><li> </li><li>two</li></ol>', "1. one\n2. two\n" ),
			'links'                                       => array( '<p><a href="https://example.com/a">Abs</a> <a href="/root">Root</a> <a href="../up/">Up</a> <a href="next">Rel</a> <a href="#top">Top</a> <a href="?p=2">Query</a> <a href="//cdn.example.com/x">Cdn</a></p>', "[Abs](https://example.com/a) [Root](https://example.com/root) [Up](https://example.com/blog/up/) [Rel](https://example.com/blog/post/next) [Top](https://example.com/blog/post/#top) [Query](https://example.com/blog/post/?p=2) [Cdn](https://cdn.example.com/x)\n" ),
			'link schemes'                                => array( '<p><a href="mailto:a@example.com">Mail</a> <a href="tel:+15550100">Call</a> <a href="javascript:alert(1)">Script</a> <a href="data:text/html,x">Data</a> <a href="ftp://example.com/f">Ftp</a> <a href="HTTPS://example.com/">Upper</a></p>', "[Mail](mailto:a@example.com) [Call](tel:+15550100) Script Data Ftp [Upper](https://example.com/)\n" ),
			'link destination is encoded'                 => array( '<p><a href="https://example.com/a b(c)&lt;d&gt;">Odd</a></p>', "[Odd](https://example.com/a%20b%28c%29%3Cd%3E)\n" ),
			'link without text is dropped'                => array( '<p>a<a href="https://example.com/"> </a>b</p>', "a b\n" ),
			'a link inside a link keeps its text'         => array( '<p><a href="https://example.com/a">x <span><a href="https://example.com/b">y</a></span></a></p>', "[x y](https://example.com/a)\n" ),
			'image'                                       => array( '<p><img src="/wp-content/uploads/a.jpg" alt="A [red] cat" width="10"></p>', "![A \\[red\\] cat](https://example.com/wp-content/uploads/a.jpg)\n" ),
			'image without a usable source keeps its alt' => array( '<p><img alt="Only alt"><img src="data:image/png;base64,AAAA" alt="Inline"></p>', "Only altInline\n" ),
			'lazy-loaded image'                           => array( '<img src="data:image/gif;base64,R0lGOD" data-src="/real.png" alt="Lazy">', "![Lazy](https://example.com/real.png)\n" ),
			'linked image'                                => array( '<a href="https://example.com/"><img src="/i.png" alt=""></a>', "[![](https://example.com/i.png)](https://example.com/)\n" ),
			'a link right after ! stays a link'           => array( '<p>Wow!<a href="https://example.com/">link</a></p>', "Wow\\![link](https://example.com/)\n" ),
			'a link after ! in another element'           => array( '<p><span>Wow!</span><a href="/x">x</a> <em>Yes!</em><a href="/y">y</a></p>', "Wow\\![x](https://example.com/x) *Yes!*[y](https://example.com/y)\n" ),
			'a linked image after !'                      => array( '<p>Wow!<a href="/"><img src="/i.png" alt=""></a></p>', "Wow\\![![](https://example.com/i.png)](https://example.com/)\n" ),
			'an embed link after !'                       => array( '<p>Look!<video src="/v.mp4"></video></p>', "Look\\![https://example.com/v.mp4](https://example.com/v.mp4)\n" ),
			'an image right after ! stays an image'       => array( '<p>Wow!<img src="/i.png" alt="i"></p>', "Wow!![i](https://example.com/i.png)\n" ),
			'a ! not before a link is left alone'         => array( '<p>Hi! <a href="/x">x</a> and wow!</p><p>Done!</p>', "Hi! [x](https://example.com/x) and wow!\n\nDone!\n" ),
			'a ! after a backslash before a link'         => array( '<p>a\\!<a href="/x">x</a></p>', "a\\\\\\![x](https://example.com/x)\n" ),
			'a ! before a dropped link'                   => array( '<p>Wow!<a href="javascript:x()">x</a></p>', "Wow!x\n" ),
			'figure with caption'                         => array( '<figure><img src="https://example.com/a.jpg" alt="A"><figcaption>A <em>cat</em></figcaption></figure>', "![A](https://example.com/a.jpg)\n\nA *cat*\n" ),
			'table'                                       => array( '<table><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody></table>', "| A | B |\n| --- | --- |\n| 1 | 2 |\n" ),
			'table without a header row'                  => array( '<table><tr><td>1</td><td>2</td></tr><tr><td>3</td></tr></table>', "| 1 | 2 |\n| --- | --- |\n| 3 |  |\n" ),
			'table cells are one line, pipes escaped'     => array( '<table><caption>Plans</caption><tr><th>Plan</th><th>Note</th></tr><tr><td><p>Basic</p><p>plan</p></td><td>a | b<br>c</td></tr><tr><td colspan="2">wide</td></tr></table>', "Plans\n\n| Plan | Note |\n| --- | --- |\n| Basic plan | a \\| b c |\n| wide |  |\n" ),
			'description list'                            => array( '<dl><dt>Term</dt><dd>Meaning</dd><dt><strong>Bold</strong></dt><dd><p>More</p></dd></dl>', "**Term**\n\nMeaning\n\n**Bold**\n\nMore\n" ),
			'details and summary'                         => array( '<details><summary>Show</summary><p>Hidden text.</p></details>', "**Show**\n\nHidden text.\n" ),
			'embedded media'                              => array( '<iframe title="A video" src="https://www.youtube.com/embed/abc"></iframe><video src="/clip.mp4"></video><audio><source src="javascript:x"><source src="https://example.com/a.mp3"></audio><embed src="https://example.com/e.swf"><object data="/o.pdf"></object><iframe src="javascript:alert(1)"></iframe>', "[A video](https://www.youtube.com/embed/abc)[https://example.com/clip.mp4](https://example.com/clip.mp4)[https://example.com/a.mp3](https://example.com/a.mp3)[https://example.com/e.swf](https://example.com/e.swf)[https://example.com/o.pdf](https://example.com/o.pdf)\n" ),
			'content after an empty element is kept'      => array( '<p><embed src="https://example.com/e.swf"> after</p><p>next<wbr>word</p><picture><source srcset="/a.webp" type="image/webp"><img src="/a.jpg" alt="A"></picture>', "[https://example.com/e.swf](https://example.com/e.swf) after\n\nnextword\n\n![A](https://example.com/a.jpg)\n" ),
			'dropped elements'                            => array( '<script>alert(1)</script><style>p{}</style><noscript>no</noscript><template><p>t</p></template><svg><text>s</text></svg><form><p>f</p><input value="v"><select><option>o</option></select><textarea>t</textarea></form><button>Go</button><p>kept</p>', "kept\n" ),
			'unknown elements keep their text'            => array( '<p>a <x-tag>b</x-tag> <span class="c">c</span></p><custom-box><p>one</p><p>two</p></custom-box>', "a b c\n\none\n\ntwo\n" ),

			// Real block-editor markup, as the_content prints it.
			'block: paragraph'                            => array( '<p class="has-text-align-center">Centered <a href="https://example.com/x">text</a>.</p>', "Centered [text](https://example.com/x).\n" ),
			'block: heading'                              => array( '<h2 class="wp-block-heading" id="intro">Intro</h2>', "## Intro\n" ),
			'block: list'                                 => array( "<ul class=\"wp-block-list\">\n<li>First</li>\n\n\n\n<li>Second\n<ul class=\"wp-block-list\">\n<li>Nested</li>\n</ul>\n</li>\n</ul>", "- First\n- Second\n  - Nested\n" ),
			'block: quote'                                => array( "<blockquote class=\"wp-block-quote is-layout-flow wp-block-quote-is-layout-flow\">\n<p>Be kind.</p>\n<cite>Someone</cite></blockquote>", "> Be kind.\n>\n> Someone\n" ),
			'block: image with caption'                   => array( '<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="1024" height="683" src="https://example.com/wp-content/uploads/cat-1024x683.jpg" alt="A cat" class="wp-image-12" srcset="https://example.com/wp-content/uploads/cat-1024x683.jpg 1024w" sizes="(max-width: 1024px) 100vw, 1024px" /><figcaption class="wp-element-caption">A <strong>sleepy</strong> cat</figcaption></figure>', "![A cat](https://example.com/wp-content/uploads/cat-1024x683.jpg)\n\nA **sleepy** cat\n" ),
			'block: gallery'                              => array( "<figure class=\"wp-block-gallery has-nested-images columns-default is-cropped wp-block-gallery-1 is-layout-flex\">\n<figure class=\"wp-block-image size-large\"><img src=\"https://example.com/one.jpg\" alt=\"One\"/></figure>\n\n<figure class=\"wp-block-image size-large\"><img src=\"/two.jpg\" alt=\"\"/><figcaption class=\"wp-element-caption\">Second</figcaption></figure>\n<figcaption class=\"blocks-gallery-caption wp-element-caption\">Two photos</figcaption></figure>", "![One](https://example.com/one.jpg)\n\n![](https://example.com/two.jpg)\n\nSecond\n\nTwo photos\n" ),
			'block: table'                                => array( '<figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr><th>Plan</th><th>Price</th></tr></thead><tbody><tr><td>Basic</td><td>$5</td></tr><tr><td><strong>Pro</strong></td><td></td></tr></tbody></table><figcaption class="wp-element-caption">Prices</figcaption></figure>', "| Plan | Price |\n| --- | --- |\n| Basic | $5 |\n| **Pro** |  |\n\nPrices\n" ),
			'block: code'                                 => array( "<pre class=\"wp-block-code\"><code>&lt;?php\necho \"hi\";  \n</code></pre>", "```\n<?php\necho \"hi\";\n```\n" ),
			'block: embed, resolved'                      => array( "<figure class=\"wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio\"><div class=\"wp-block-embed__wrapper\">\n<iframe loading=\"lazy\" title=\"Demo video\" width=\"500\" height=\"281\" src=\"https://www.youtube.com/embed/abc?feature=oembed\" frameborder=\"0\" allowfullscreen></iframe>\n</div><figcaption class=\"wp-element-caption\">Watch it</figcaption></figure>", "[Demo video](https://www.youtube.com/embed/abc?feature=oembed)\n\nWatch it\n" ),
			'block: embed, unresolved'                    => array( "<figure class=\"wp-block-embed is-type-video\"><div class=\"wp-block-embed__wrapper\">\nhttps://www.youtube.com/watch?v=abc_def\n</div></figure>", "https://www.youtube.com/watch?v=abc_def\n" ),
			'block: buttons'                              => array( "<div class=\"wp-block-buttons is-layout-flex wp-block-buttons-is-layout-flex\">\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"https://example.com/signup/\">Sign up</a></div>\n\n<div class=\"wp-block-button is-style-outline\"><a class=\"wp-block-button__link wp-element-button\" href=\"/pricing/\">Pricing</a></div>\n</div>", "[Sign up](https://example.com/signup/)\n\n[Pricing](https://example.com/pricing/)\n" ),
			'block: columns'                              => array( "<div class=\"wp-block-columns is-layout-flex wp-container-core-columns-is-layout-1\">\n<div class=\"wp-block-column is-layout-flow\">\n<h3 class=\"wp-block-heading\">Left</h3>\n\n<p>One</p>\n</div>\n\n<div class=\"wp-block-column is-layout-flow\">\n<p>Two</p>\n</div>\n</div>", "### Left\n\nOne\n\nTwo\n" ),
			'block: details'                              => array( "<details class=\"wp-block-details is-layout-flow\"><summary>More</summary>\n<p>Inside.</p>\n</details>", "**More**\n\nInside.\n" ),

			// Escaping: content cannot turn into structure, and ordinary prose stays readable.
			'escaping inside a line'                      => array( '<p>Use 2 * 3, a_b, file__name, <span>_lead</span>, trail_, C:\\dir, `tick`, a &lt; b, a &lt;b, [x], ~ok, &amp;copy; and &amp; alone.</p>', "Use 2 \\* 3, a_b, file\\_\\_name, \\_lead, trail\\_, C:\\\\dir, \\`tick\\`, a < b, a \\<b, \\[x\\], \\~ok, \\&copy; and & alone.\n" ),
			'escaping at the start of a line'             => array( '<p>+ plus<br>&gt; quote<br>- dash<br>---<br>===<br>2) two<br>10. ten<br>#tag<br>## two<br>1st place</p>', "\\+ plus\\\n\\> quote\\\n\\- dash\\\n\\---\\\n\\===\\\n2\\) two\\\n10\\. ten\\\n#tag\\\n\\## two\\\n1st place\n" ),
			'a paragraph cannot become a list item'       => array( '<ul><li>1. not nested</li><li># not a heading</li></ul><blockquote><p>&gt; not nested</p></blockquote>', "- 1\\. not nested\n- \\# not a heading\n\n> \\> not nested\n" ),
			'headings keep numbers'                       => array( '<h2>1. Intro</h2>', "## 1. Intro\n" ),

			// Whitespace, entities, and text.
			'whitespace collapses as in HTML'             => array( "<p>  lots   of\n\tspace&nbsp;&nbsp;here  </p>\n\n<p></p><p> </p>", "lots of space here\n" ),
			'entities are decoded'                        => array( '<p>caf&eacute; &mdash; &#8220;q&#8221; &lt;tag&gt; &amp;</p>', "café — “q” \\<tag> &\n" ),
			'non-Latin text and emoji'                    => array( '<h2>Привет 👋 мир</h2><p>日本語のテキスト <strong>強調</strong> مرحبا 😀</p>', "## Привет 👋 мир\n\n日本語のテキスト **強調** مرحبا 😀\n" ),
			'invalid UTF-8 is replaced'                   => array( "<p>a\xFF\xFEb \xC3\xA9</p>", "a\u{FFFD}\u{FFFD}b é\n" ),
			'control characters are removed'              => array( "<p>a\x01b\x1Ec\x7F</p>", "abc\n" ),

			// Malformed input.
			'unclosed and misnested tags'                 => array( '<p>a <b>b <i>c</b> d</i></p></div></span><li>stray', "a **b *c*** d\n\nstray\n" ),
			'text outside any element'                    => array( 'bare text, then <p>a paragraph</p> and more', "bare text, then\n\na paragraph\n\nand more\n" ),
			'a whole document'                            => array( '<!doctype html><html><head><title>T</title></head><body><p>Body</p></body></html>', "Body\n" ),
			'empty input'                                 => array( '', '' ),
			'only whitespace'                             => array( " \n\t ", '' ),
			'only dropped content'                        => array( '<script>alert(1)</script>', '' ),
		);
	}

	public function test_relative_links_keep_only_their_text_without_a_base(): void {
		$converter = new Lightweight_SEO_Markdown_Converter();

		$this->assertSame( "Rel and [Abs](https://example.com/)\n", $converter->convert( '<p><a href="/rel">Rel</a> and <a href="https://example.com/">Abs</a></p>' ) );
		$this->assertSame( "Image\n", $converter->convert( '<img src="/a.png" alt="Image">' ) );
	}

	public function test_libxml_error_handling_is_left_as_it_was(): void {
		$converter = new Lightweight_SEO_Markdown_Converter( self::BASE );
		$previous  = libxml_use_internal_errors( false );

		$converter->convert( '<p><b>unclosed <i>tags</p><bogus attr=">' );

		$this->assertFalse( libxml_use_internal_errors( true ), 'The setting is restored.' );

		$converter->convert( '<section><figure><bogus>x</figure>' );

		$this->assertTrue( libxml_use_internal_errors( $previous ), 'An earlier choice of internal errors is kept too.' );
		$this->assertSame( array(), libxml_get_errors(), 'The errors the converter caused are cleared.' );
	}

	/**
	 * @dataProvider addresses
	 */
	public function test_resolves_addresses_as_a_browser_does( string $url, string $expected ): void {
		$this->assertSame( $expected, Lightweight_SEO_Markdown_Converter::resolve_url( $url, 'https://example.com/blog/post/page?x=1#f' ) );
	}

	public function addresses(): array {
		return array(
			'absolute'           => array( 'https://other.example/a', 'https://other.example/a' ),
			'scheme is lowered'  => array( 'HTTP://example.com/A', 'http://example.com/A' ),
			'scheme-relative'    => array( '//cdn.example.com/a.js', 'https://cdn.example.com/a.js' ),
			'root-relative'      => array( '/a/b', 'https://example.com/a/b' ),
			'relative'           => array( 'c', 'https://example.com/blog/post/c' ),
			'dot segments'       => array( '../../x/./y/../z', 'https://example.com/x/z' ),
			'ends in dot dot'    => array( '..', 'https://example.com/blog/' ),
			'above the root'     => array( '../../../../a', 'https://example.com/a' ),
			'query only'         => array( '?p=2', 'https://example.com/blog/post/page?p=2' ),
			'fragment only'      => array( '#top', 'https://example.com/blog/post/page?x=1#top' ),
			'query kept on path' => array( 'c?d=1#e', 'https://example.com/blog/post/c?d=1#e' ),
			'spaces and breaks'  => array( " \n /a\tb ", 'https://example.com/ab' ),
			'empty'              => array( '', '' ),
		);
	}

	public function test_resolving_keeps_a_port_and_needs_an_absolute_base(): void {
		$this->assertSame( 'http://localhost:8881/a', Lightweight_SEO_Markdown_Converter::resolve_url( 'a', 'http://localhost:8881/' ) );
		$this->assertSame( '', Lightweight_SEO_Markdown_Converter::resolve_url( 'a', '/relative/base' ) );
		$this->assertSame( 'mailto:a@example.com', Lightweight_SEO_Markdown_Converter::resolve_url( 'mailto:a@example.com', '' ) );
	}

	public function test_helpers_for_text_placed_outside_converted_html(): void {
		$this->assertSame( '# A \\*bold\\* title: part 2', Lightweight_SEO_Markdown_Converter::heading( "A *bold*\n title: part 2" ) );
		$this->assertSame( '###### Deep', Lightweight_SEO_Markdown_Converter::heading( 'Deep', 9 ) );
		$this->assertSame( '', Lightweight_SEO_Markdown_Converter::heading( '  ' ) );
		$this->assertSame( '1\\. \\[Draft\\] notes\\_', Lightweight_SEO_Markdown_Converter::escape_line( '1. [Draft]  notes_' ) );
		$this->assertSame( "```json\n{\"a\":\"`b`\"}\n```", Lightweight_SEO_Markdown_Converter::fenced_code( '{"a":"`b`"}', 'json' ) );
		$this->assertSame( "````\n```\nx\n````", Lightweight_SEO_Markdown_Converter::fenced_code( "```\nx  \n\n", '' ) );
		$this->assertSame( 'https://example.com/a%20b%28c%29', Lightweight_SEO_Markdown_Converter::destination( 'https://example.com/a b(c)' ) );
	}
}
