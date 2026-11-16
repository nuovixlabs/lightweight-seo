<?php

use PHPUnit\Framework\TestCase;

final class LightweightSEOUrlTest extends TestCase {

	protected function tearDown(): void {
		global $lightweight_seo_test_home_url;

		$lightweight_seo_test_home_url = 'https://example.com';
	}

	public function test_root_install_joins_paths_to_the_site_origin(): void {
		$this->assertSame( 'https://example.com', Lightweight_SEO_Url::origin() );
		$this->assertSame( 'https://example.com/category/news/', Lightweight_SEO_Url::from_path( '/category/news/' ) );
	}

	public function test_subdirectory_install_does_not_repeat_the_home_path(): void {
		global $lightweight_seo_test_home_url;

		$lightweight_seo_test_home_url = 'https://example.com:8443/blog';

		$this->assertSame( 'https://example.com:8443', Lightweight_SEO_Url::origin() );
		$this->assertSame( 'https://example.com:8443/blog/category/news/', Lightweight_SEO_Url::from_path( '/blog/category/news/' ) );
	}
}
