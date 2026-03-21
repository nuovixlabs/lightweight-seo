<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-meta-boxes.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOMetaBoxesTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_nonce_is_valid;
		global $lightweight_seo_test_user_can;

		$lightweight_seo_test_nonce_is_valid = true;
		$lightweight_seo_test_user_can       = true;
	}

	protected function tearDown(): void {
		$_POST = array();
	}

	public function test_save_meta_box_data_updates_expected_fields(): void {
		$settings = new class() {
			public function get_all() {
				return array();
			}

			public function get_title_format() {
				return LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT;
			}
		};

		$post_meta = new class() {
			public $updates = array();

			public function update( $post_id, $field, $value ) {
				$this->updates[ $field ] = $value;

				return true;
			}

			public function get_all( $post_id ) {
				return array(
					'seo_title'             => '',
					'seo_description'       => '',
					'seo_keywords'          => '',
					'seo_canonical_url'     => '',
					'seo_noindex'           => '',
					'seo_nofollow'          => '',
					'seo_noarchive'         => '',
					'seo_nosnippet'         => '',
					'seo_max_image_preview' => '',
					'social_title'          => '',
					'social_description'    => '',
					'social_image'          => '',
					'social_image_id'       => 0,
				);
			}

			public function get( $post_id, $field ) {
				return '';
			}

			public function get_social_image_url( $post_id ) {
				return '';
			}

			public function normalize_social_image( $image_url, $image_id, $previous_image_url = '', $previous_image_id = 0 ) {
				return array( $image_url, $image_id );
			}

			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}
		};

		$_POST = array(
			'lightweight_seo_meta_box_nonce'     => 'nonce',
			'lightweight_seo_title'              => '  My Title  ',
			'lightweight_seo_description'        => ' My Description ',
			'lightweight_seo_keywords'           => 'alpha, beta',
			'lightweight_seo_canonical_url'      => 'https://example.com/canonical-url',
			'lightweight_seo_noindex'            => '1',
			'lightweight_seo_nofollow'           => '1',
			'lightweight_seo_noarchive'          => '1',
			'lightweight_seo_nosnippet'          => '1',
			'lightweight_seo_max_image_preview'  => 'standard',
			'lightweight_seo_social_title'       => ' Social Title ',
			'lightweight_seo_social_description' => ' Social Description ',
			'lightweight_seo_social_image'       => 'https://example.com/social-image.jpg',
			'lightweight_seo_social_image_id'    => '42',
		);

		$meta_boxes = new Lightweight_SEO_Meta_Boxes( $settings, $post_meta );
		$meta_boxes->save_meta_box_data( 99 );

		$this->assertSame( 'My Title', $post_meta->updates['seo_title'] );
		$this->assertSame( 'My Description', $post_meta->updates['seo_description'] );
		$this->assertSame( 'alpha, beta', $post_meta->updates['seo_keywords'] );
		$this->assertSame( 'https://example.com/canonical-url', $post_meta->updates['seo_canonical_url'] );
		$this->assertSame( '1', $post_meta->updates['seo_noindex'] );
		$this->assertSame( '1', $post_meta->updates['seo_nofollow'] );
		$this->assertSame( '1', $post_meta->updates['seo_noarchive'] );
		$this->assertSame( '1', $post_meta->updates['seo_nosnippet'] );
		$this->assertSame( 'standard', $post_meta->updates['seo_max_image_preview'] );
		$this->assertSame( 'Social Title', $post_meta->updates['social_title'] );
		$this->assertSame( 'Social Description', $post_meta->updates['social_description'] );
		$this->assertSame( 'https://example.com/social-image.jpg', $post_meta->updates['social_image'] );
		$this->assertSame( 42, $post_meta->updates['social_image_id'] );
	}

	public function test_save_meta_box_data_uses_normalized_social_image_id(): void {
		$settings = new class() {
			public function get_all() {
				return array();
			}

			public function get_title_format() {
				return LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT;
			}
		};

		$post_meta = new class() {
			public $updates = array();

			public function update( $post_id, $field, $value ) {
				$this->updates[ $field ] = $value;

				return true;
			}

			public function get_all( $post_id ) {
				return array();
			}

			public function get( $post_id, $field ) {
				if ( 'social_image' === $field ) {
					return 'https://example.com/uploads/old-image.jpg';
				}

				if ( 'social_image_id' === $field ) {
					return 42;
				}

				return '';
			}

			public function get_social_image_url( $post_id ) {
				return '';
			}

			public function normalize_social_image( $image_url, $image_id, $previous_image_url = '', $previous_image_id = 0 ) {
				return array( $image_url, 0 );
			}

			public function get_supported_post_types() {
				return array( 'post', 'page' );
			}
		};

		$_POST = array(
			'lightweight_seo_meta_box_nonce'  => 'nonce',
			'lightweight_seo_social_image'    => 'https://cdn.example.com/manual-image.jpg',
			'lightweight_seo_social_image_id' => '42',
		);

		$meta_boxes = new Lightweight_SEO_Meta_Boxes( $settings, $post_meta );
		$meta_boxes->save_meta_box_data( 99 );

		$this->assertSame( 'https://cdn.example.com/manual-image.jpg', $post_meta->updates['social_image'] );
		$this->assertSame( 0, $post_meta->updates['social_image_id'] );
	}
}
