<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-ui.php';
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

	public function test_save_term_fields_requires_the_edit_term_capability(): void {
		global $lightweight_seo_test_user_can;

		$lightweight_seo_test_user_can = false;
		$service                       = new Lightweight_SEO_Archive_Meta( $this->get_settings_stub() );

		$_POST = array(
			'lightweight_seo_term_meta_nonce'           => 'nonce',
			'lightweight_seo_archive_seo_canonical_url' => 'https://attacker.example/',
			'lightweight_seo_archive_seo_noindex'       => '1',
		);

		$service->save_term_fields( 23 );
		$values = $service->get_term_all( 23 );

		$this->assertSame( '', $values['seo_canonical_url'] );
		$this->assertSame( '', $values['seo_noindex'] );
	}

	public function test_search_visibility_saves_one_of_three_values_for_terms_and_authors(): void {
		$service = new Lightweight_SEO_Archive_Meta( $this->get_settings_stub() );

		foreach ( array(
			'index'  => 'index',
			'1'      => '1',
			''       => '0',
			'noidea' => '0',
		) as $posted => $stored ) {
			$_POST = array(
				'lightweight_seo_term_meta_nonce'     => 'nonce',
				'lightweight_seo_user_meta_nonce'     => 'nonce',
				'lightweight_seo_archive_seo_noindex' => (string) $posted,
			);

			$service->save_term_fields( 23 );
			$service->save_user_fields( 17 );

			$this->assertSame( $stored, $service->get_term_all( 23 )['seo_noindex'], 'Posted "' . $posted . '" for a term.' );
			$this->assertSame( $stored, $service->get_user_all( 17 )['seo_noindex'], 'Posted "' . $posted . '" for an author.' );
		}
	}

	public function test_term_and_author_screens_offer_the_three_way_choice_with_their_content_type_default(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_term_meta;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'noindex_author_archives' => '1',
			'content_types'           => array( 'taxonomies' => array( 'post_tag' => array( 'noindex' => '1' ) ) ),
		);

		$service = new Lightweight_SEO_Archive_Meta( new Lightweight_SEO_Settings() );
		$service->update_term( 9, 'seo_noindex', 'index' );

		ob_start();
		$service->render_term_edit_fields(
			(object) array(
				'term_id'  => 9,
				'taxonomy' => 'post_tag',
			)
		);
		$tag = (string) ob_get_clean();

		ob_start();
		$service->render_term_add_fields( 'category' );
		$category = (string) ob_get_clean();

		ob_start();
		$service->render_user_fields( (object) array( 'ID' => 17 ) );
		$author = (string) ob_get_clean();

		$this->assertStringContainsString( '<label for="lightweight_seo_archive_seo_noindex">Search results</label>', $tag );
		$this->assertStringContainsString( '<select id="lightweight_seo_archive_seo_noindex" name="lightweight_seo_archive_seo_noindex">', $tag );
		$this->assertMatchesRegularExpression( '/<option value="" >Default for Tags \(currently hidden\)<\/option>/', $tag );
		$this->assertMatchesRegularExpression( '/<option value="index"\s+selected=\'selected\'>Show in search results<\/option>/', $tag );
		$this->assertMatchesRegularExpression( '/<option value=""\s+selected=\'selected\'>Default for Categories \(currently shown\)<\/option>/', $category );
		$this->assertMatchesRegularExpression( '/<option value=""\s+selected=\'selected\'>Default for author archives \(currently hidden\)<\/option>/', $author );
		$this->assertStringNotContainsString( 'type="checkbox" id="lightweight_seo_archive_seo_noindex"', $tag . $category . $author );

		$lightweight_seo_test_options   = array();
		$lightweight_seo_test_term_meta = array();
	}

	private function get_settings_stub() {
		return new Lightweight_SEO_Settings();
	}
}
