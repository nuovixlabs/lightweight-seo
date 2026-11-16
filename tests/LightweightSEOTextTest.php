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
}
