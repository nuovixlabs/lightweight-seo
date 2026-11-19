<?php

use PHPUnit\Framework\TestCase;

final class LightweightSEOTextTest extends TestCase {

	public function test_length_counts_characters_not_bytes(): void {
		$this->assertSame( 5, Lightweight_SEO_Text::length( 'héllo' ) );
		$this->assertSame( 4, Lightweight_SEO_Text::length( '日本語版' ) );
	}

	public function test_short_text_is_returned_unchanged(): void {
		$this->assertSame( 'A short description.', Lightweight_SEO_Text::summarize( 'A short description.' ) );
	}

	public function test_long_text_is_cut_on_a_word_boundary_with_an_ellipsis(): void {
		$summary = Lightweight_SEO_Text::summarize( str_repeat( 'lightweight words, ', 40 ) );

		$this->assertLessThanOrEqual( 161, Lightweight_SEO_Text::length( $summary ) );
		$this->assertStringEndsWith( 'words…', $summary );
	}

	public function test_unspaced_multibyte_text_is_cut_without_splitting_a_character(): void {
		$summary = Lightweight_SEO_Text::summarize( str_repeat( '日本語', 100 ) );

		$this->assertSame( 161, Lightweight_SEO_Text::length( $summary ) );
		$this->assertTrue( mb_check_encoding( $summary, 'UTF-8' ) );
		$this->assertStringEndsWith( '…', $summary );
	}

	public function test_the_first_four_variables_resolve_and_an_empty_tagline_leaves_no_separator(): void {
		$tokens = array(
			'%title%'    => 'A page',
			'%sitename%' => 'Site',
			'%tagline%'  => '',
			'%sep%'      => '–',
		);

		$this->assertSame( 'A page – Site', Lightweight_SEO_Text::resolve_tokens( '%title% %sep% %sitename%', $tokens ) );
		// An empty tagline, the WordPress default, leaves with its separator and no dangling dash.
		$this->assertSame( 'Site', Lightweight_SEO_Text::resolve_tokens( '%sitename% %sep% %tagline%', $tokens ) );
		$this->assertSame( 'Site – A page', Lightweight_SEO_Text::resolve_tokens( '%sitename% %sep% %tagline% %sep% %title%', $tokens ) );
		$this->assertSame( 'Site – Photographs', Lightweight_SEO_Text::resolve_tokens( '%sitename% %sep% %tagline%', array( '%tagline%' => 'Photographs' ) + $tokens ) );
		$this->assertSame( 'No variables – at all', Lightweight_SEO_Text::resolve_tokens( 'No variables – at all', $tokens ) );
		$this->assertSame( '%unknown% A page', Lightweight_SEO_Text::resolve_tokens( '%unknown% %title%', $tokens ) );
	}

	public function test_an_empty_optional_variable_leaves_with_one_separator_beside_it(): void {
		$tokens = array(
			'%title%'    => 'Archive',
			'%sitename%' => 'Site',
			'%sep%'      => '|',
			'%page%'     => '',
			'%category%' => '',
		);

		// The separator before the variable goes with it.
		$this->assertSame( 'Archive | Site', Lightweight_SEO_Text::resolve_tokens( '%title% %sep% %page% %sep% %sitename%', $tokens ) );
		$this->assertSame( 'Archive', Lightweight_SEO_Text::resolve_tokens( '%title% %sep% %page%', $tokens ) );
		// When the variable comes first, the separator after it goes instead.
		$this->assertSame( 'Archive | Site', Lightweight_SEO_Text::resolve_tokens( '%page% %sep% %title% %sep% %sitename%', $tokens ) );
		// Two empty variables in a row leave one clean title.
		$this->assertSame( 'Archive | Site', Lightweight_SEO_Text::resolve_tokens( '%title% %sep% %category% %sep% %page% %sep% %sitename%', $tokens ) );
		// A separator typed as a character counts too, but a hyphen inside a word does not.
		$this->assertSame( 'Archive – Site', Lightweight_SEO_Text::resolve_tokens( '%title% – %page% – %sitename%', $tokens ) );
		$this->assertSame( 'Archive well-known', Lightweight_SEO_Text::resolve_tokens( '%title% well-known%page%', $tokens ) );
		// A variable the caller did not supply is treated as empty instead of being printed.
		$this->assertSame( 'Archive', Lightweight_SEO_Text::resolve_tokens( '%title% %sep% %author%', $tokens ) );
		$this->assertSame( '', Lightweight_SEO_Text::resolve_tokens( '%page%', $tokens ) );
	}

	public function test_an_optional_variable_with_a_value_is_replaced_like_any_other(): void {
		$tokens = array(
			'%title%'    => 'Archive',
			'%sitename%' => 'Site',
			'%sep%'      => '·',
			'%page%'     => 'Page 2',
			'%year%'     => '2026',
		);

		$this->assertSame( 'Archive · Page 2 · Site', Lightweight_SEO_Text::resolve_tokens( '%title% %sep% %page% %sep% %sitename%', $tokens ) );
		$this->assertSame( 'Best of 2026', Lightweight_SEO_Text::resolve_tokens( 'Best of %year%', $tokens ) );
	}

	public function test_every_supported_separator_counts_as_one_beside_an_empty_variable(): void {
		require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';

		foreach ( Lightweight_SEO_Settings::TITLE_SEPARATORS as $separator ) {
			$this->assertSame( 'A ' . $separator . ' B', Lightweight_SEO_Text::resolve_tokens( 'A ' . $separator . ' %page% ' . $separator . ' B', array() ), 'Separator "' . $separator . '".' );
		}
	}

	public function test_the_preview_script_tidies_the_same_variables_and_separators(): void {
		$script = (string) file_get_contents( dirname( __DIR__ ) . '/admin/js/lightweight-seo-formats.js' );

		$this->assertStringContainsString( "var OPTIONAL_TOKENS = ['" . implode( "', '", Lightweight_SEO_Text::OPTIONAL_TOKENS ) . "'];", $script );
		// The script writes the pattern as a string, so each backslash of the server pattern is doubled.
		$this->assertStringContainsString( "var SEPARATOR_PATTERN = '" . str_replace( '\\', '\\\\', Lightweight_SEO_Text::SEPARATOR_PATTERN ) . "';", $script );

		// The editor panel and the settings previews both use that one copy.
		$this->assertStringNotContainsString( 'separatorPattern', (string) file_get_contents( dirname( __DIR__ ) . '/admin/js/lightweight-seo-admin.js' ) );
	}

	public function test_variables_survive_sanitizing_that_strips_percent_encoded_bytes(): void {
		// WordPress removes "%ca" from "%category%" on its own; this is what the helper prevents.
		$this->assertSame( 'tegory%', sanitize_text_field( '%category%' ) );

		$format = '%title% %sep% %category% %sep% %author% %sep% %year% %sep% %page% %sep% %sitename% %tagline% %search%';

		$this->assertSame( $format, Lightweight_SEO_Text::sanitize_template( $format ) );
		$this->assertSame( "By %author%\nin %category%.", Lightweight_SEO_Text::sanitize_textarea_template( "By %author%\nin %category%." ) );
		// Variables side by side, and one WordPress would otherwise read as two encoded bytes.
		$this->assertSame( '%category%%sep%%title%', Lightweight_SEO_Text::sanitize_template( '%category%%sep%%title%' ) );
		$this->assertSame( '%face% value', Lightweight_SEO_Text::sanitize_template( '%face% value' ) );
	}

	public function test_template_sanitizing_still_removes_markup_and_real_encoded_bytes(): void {
		$this->assertSame( 'Bold %title%', Lightweight_SEO_Text::sanitize_template( ' <b>Bold</b> %title% ' ) );
		$this->assertSame( 'ab %title%', Lightweight_SEO_Text::sanitize_template( 'a%3Cb %title%' ) );
		$this->assertSame( '50% off', Lightweight_SEO_Text::sanitize_template( '50% off' ) );
		$this->assertSame( '', Lightweight_SEO_Text::sanitize_template( array( '%title%' ) ) );
		// It is registered as a meta sanitizer, which WordPress calls with the key and object type too.
		$this->assertSame( 'In %category%', call_user_func( array( 'Lightweight_SEO_Text', 'sanitize_template' ), 'In %category%', '_lightweight_seo_title', 'post' ) );
	}

	public function test_content_facts_count_like_the_editor_script(): void {
		// The same cases run against contentFacts() in tests/js/content.test.js.
		$cases = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/content-facts.json' ), true );

		$this->assertNotEmpty( $cases );

		foreach ( $cases as $case ) {
			$this->assertSame( $case['expected'], Lightweight_SEO_Text::content_facts( $case['html'] ), $case['name'] );
		}
	}
}
