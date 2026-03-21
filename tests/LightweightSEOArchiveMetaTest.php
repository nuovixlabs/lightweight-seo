<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-archive-meta.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOArchiveMetaTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_nonce_is_valid;
		global $lightweight_seo_test_term_meta;
		global $lightweight_seo_test_user_can;
		global $lightweight_seo_test_user_meta;

		$lightweight_seo_test_nonce_is_valid = true;
		$lightweight_seo_test_user_can       = true;
		$lightweight_seo_test_term_meta      = array();
		$lightweight_seo_test_user_meta      = array();
	}

	protected function tearDown(): void {
		$_POST = array();
	}

	public function test_save_term_fields_sanitizes_and_stores_expected_values(): void {
		$service = new Lightweight_SEO_Archive_Meta( $this->get_settings_stub() );

		$_POST = array(
			'lightweight_seo_term_meta_nonce'           => 'nonce',
			'lightweight_seo_archive_seo_title'         => '  News Archive  ',
			'lightweight_seo_archive_seo_description'   => ' Latest updates ',
			'lightweight_seo_archive_seo_canonical_url' => 'https://example.com/news',
			'lightweight_seo_archive_seo_noindex'       => '1',
			'lightweight_seo_archive_seo_noarchive'     => '1',
			'lightweight_seo_archive_seo_max_image_preview' => 'standard',
		);

		$service->save_term_fields( 23 );
		$values = $service->get_term_all( 23 );

		$this->assertSame( 'News Archive', $values['seo_title'] );
		$this->assertSame( 'Latest updates', $values['seo_description'] );
		$this->assertSame( 'https://example.com/news', $values['seo_canonical_url'] );
		$this->assertSame( '1', $values['seo_noindex'] );
		$this->assertSame( '0', $values['seo_nofollow'] );
		$this->assertSame( '1', $values['seo_noarchive'] );
		$this->assertSame( '0', $values['seo_nosnippet'] );
		$this->assertSame( 'standard', $values['seo_max_image_preview'] );
	}

	public function test_save_user_fields_sanitizes_and_stores_expected_values(): void {
		$service = new Lightweight_SEO_Archive_Meta( $this->get_settings_stub() );

		$_POST = array(
			'lightweight_seo_user_meta_nonce'           => 'nonce',
			'lightweight_seo_archive_seo_title'         => '  Jane Doe  ',
			'lightweight_seo_archive_seo_description'   => ' Founder and editor ',
			'lightweight_seo_archive_seo_canonical_url' => 'https://example.com/authors/jane',
			'lightweight_seo_archive_seo_nofollow'      => '1',
			'lightweight_seo_archive_seo_nosnippet'     => '1',
			'lightweight_seo_archive_seo_max_image_preview' => 'none',
		);

		$service->save_user_fields( 17 );
		$values = $service->get_user_all( 17 );

		$this->assertSame( 'Jane Doe', $values['seo_title'] );
		$this->assertSame( 'Founder and editor', $values['seo_description'] );
		$this->assertSame( 'https://example.com/authors/jane', $values['seo_canonical_url'] );
		$this->assertSame( '0', $values['seo_noindex'] );
		$this->assertSame( '1', $values['seo_nofollow'] );
		$this->assertSame( '0', $values['seo_noarchive'] );
		$this->assertSame( '1', $values['seo_nosnippet'] );
		$this->assertSame( 'none', $values['seo_max_image_preview'] );
	}

	private function get_settings_stub() {
		return new class() {
			public function normalize_max_image_preview( $value, $fallback = '' ) {
				$allowed = array( 'none', 'standard', 'large' );

				return in_array( $value, $allowed, true ) ? $value : $fallback;
			}
		};
	}
}
