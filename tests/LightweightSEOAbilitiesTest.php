<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-post-meta.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-abilities.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOAbilitiesTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_abilities;
		global $lightweight_seo_test_ability_categories;
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_user_can;

		$lightweight_seo_test_abilities          = array();
		$lightweight_seo_test_ability_categories = array();
		$lightweight_seo_test_post_meta          = array();
		$lightweight_seo_test_user_can           = true;
		$lightweight_seo_test_posts              = array(
			42 => (object) array(
				'ID'          => 42,
				'post_type'   => 'post',
				'post_status' => 'publish',
			),
			50 => (object) array(
				'ID'          => 50,
				'post_type'   => 'nav_menu_item',
				'post_status' => 'publish',
			),
		);
	}

	protected function tearDown(): void {
		global $lightweight_seo_test_user_can;

		$lightweight_seo_test_user_can = true;
	}

	public function test_three_abilities_are_registered_in_their_own_category_for_rest_and_agent_clients(): void {
		global $lightweight_seo_test_abilities;
		global $lightweight_seo_test_ability_categories;

		$abilities = $this->get_abilities();

		$abilities->register_category();
		$abilities->register();

		$this->assertSame( array( 'lightweight-seo' ), array_keys( $lightweight_seo_test_ability_categories ) );
		$this->assertSame(
			array( 'lightweight-seo/get-seo-fields', 'lightweight-seo/update-seo-fields', 'lightweight-seo/get-seo-context' ),
			array_keys( $lightweight_seo_test_abilities )
		);

		foreach ( $lightweight_seo_test_abilities as $name => $ability ) {
			$this->assertMatchesRegularExpression( '/^[a-z0-9-]+\/[a-z0-9-]+$/', $name );
			$this->assertSame( 'lightweight-seo', $ability['category'] );
			$this->assertNotSame( '', $ability['label'] );
			$this->assertNotSame( '', $ability['description'] );
			$this->assertTrue( is_callable( $ability['execute_callback'] ) );
			$this->assertTrue( is_callable( $ability['permission_callback'] ) );
			$this->assertSame( array( 'post_id' ), $ability['input_schema']['required'] );
			$this->assertFalse( $ability['input_schema']['additionalProperties'] );
			$this->assertTrue( $ability['meta']['public'] );
			$this->assertTrue( $ability['meta']['show_in_rest'] );
		}

		$this->assertTrue( $lightweight_seo_test_abilities['lightweight-seo/get-seo-fields']['meta']['annotations']['readonly'] );
		$this->assertFalse( $lightweight_seo_test_abilities['lightweight-seo/update-seo-fields']['meta']['annotations']['readonly'] );
		$this->assertTrue( $lightweight_seo_test_abilities['lightweight-seo/update-seo-fields']['meta']['annotations']['destructive'] );
		$this->assertSame( array( 'default', 'show', 'hide' ), $lightweight_seo_test_abilities['lightweight-seo/update-seo-fields']['input_schema']['properties']['search_visibility']['enum'] );
	}

	public function test_every_ability_needs_the_right_to_edit_that_post(): void {
		global $lightweight_seo_test_user_can;

		$abilities = $this->get_abilities();

		$this->assertTrue( $abilities->can_edit( array( 'post_id' => 42 ) ) );
		$this->assertFalse( $abilities->can_edit( array() ) );
		$this->assertFalse( $abilities->can_edit( null ) );
		$this->assertFalse( $abilities->can_edit( array( 'post_id' => 'not a number' ) ) );

		$lightweight_seo_test_user_can = false;

		$this->assertFalse( $abilities->can_edit( array( 'post_id' => 42 ) ) );
	}

	public function test_fields_are_read_as_they_are_stored(): void {
		global $lightweight_seo_test_post_meta;

		$lightweight_seo_test_post_meta[42] = array(
			'_lightweight_seo_title'       => '%title% %sep% %category%',
			'_lightweight_seo_description' => 'A description',
			'_lightweight_seo_noindex'     => 'index',
			'_lightweight_seo_keywords'    => 'never,returned',
		);

		$this->assertSame(
			array(
				'post_id'            => 42,
				'title'              => '%title% %sep% %category%',
				'description'        => 'A description',
				'canonical_url'      => '',
				'social_title'       => '',
				'social_description' => '',
				'social_image'       => '',
				'search_visibility'  => 'show',
			),
			$this->get_abilities()->get_fields( array( 'post_id' => 42 ) )
		);

		// A post that does not exist, and a content type without SEO fields, are refused.
		$this->assertTrue( is_wp_error( $this->get_abilities()->get_fields( array( 'post_id' => 999 ) ) ) );
		$this->assertTrue( is_wp_error( $this->get_abilities()->get_fields( array( 'post_id' => 50 ) ) ) );
	}

	public function test_an_update_changes_only_what_was_sent_and_sanitizes_like_the_editor(): void {
		global $lightweight_seo_test_post_meta;

		$lightweight_seo_test_post_meta[42] = array(
			'_lightweight_seo_title'        => 'Old title',
			'_lightweight_seo_description'  => 'Kept description',
			'_lightweight_seo_social_title' => 'Cleared below',
		);

		$abilities = $this->get_abilities();
		$result    = $abilities->update_fields(
			array(
				'post_id'           => 42,
				'title'             => '  <b>New</b> %title% %sep% %category%  ',
				'canonical_url'     => 'https://example.com/canonical/',
				'social_title'      => '',
				'search_visibility' => 'hide',
				'keywords'          => 'not a field',
			)
		);

		$this->assertSame( 'New %title% %sep% %category%', $result['title'] );
		$this->assertSame( 'Kept description', $result['description'] );
		$this->assertSame( 'https://example.com/canonical/', $result['canonical_url'] );
		$this->assertSame( '', $result['social_title'] );
		$this->assertSame( 'hide', $result['search_visibility'] );

		$stored = $lightweight_seo_test_post_meta[42];

		$this->assertSame( '1', $stored['_lightweight_seo_noindex'] );
		// Hidden for the first time: "noindex" only.
		$this->assertSame( '0', $stored['_lightweight_seo_nofollow'] );
		$this->assertArrayNotHasKey( '_lightweight_seo_social_title', $stored );
		$this->assertArrayNotHasKey( '_lightweight_seo_keywords', $stored );

		// Back to the default of the content type.
		$this->assertSame(
			'default',
			$abilities->update_fields(
				array(
					'post_id'           => 42,
					'search_visibility' => 'default',
				)
			)['search_visibility']
		);
		$this->assertSame( '0', $lightweight_seo_test_post_meta[42]['_lightweight_seo_noindex'] );
	}

	public function test_an_update_with_a_value_that_is_not_a_web_address_saves_nothing(): void {
		global $lightweight_seo_test_post_meta;

		$lightweight_seo_test_post_meta[42] = array( '_lightweight_seo_title' => 'Old title' );

		$before = $lightweight_seo_test_post_meta;

		foreach ( array( 'javascript:alert(1)', 'not an address', 'ftp://example.com/file' ) as $address ) {
			$result = $this->get_abilities()->update_fields(
				array(
					'post_id'       => 42,
					'title'         => 'New title',
					'canonical_url' => $address,
				)
			);

			$this->assertTrue( is_wp_error( $result ), $address );
			$this->assertSame( $before, $lightweight_seo_test_post_meta, $address );
		}

		$this->assertTrue(
			is_wp_error(
				$this->get_abilities()->update_fields(
					array(
						'post_id' => 50,
						'title'   => 'x',
					)
				)
			)
		);
	}

	public function test_the_seo_result_comes_from_the_public_api(): void {
		$api = new class() {
			public function get_object_context( $object_type, $object_id ) {
				return array(
					'object_type'   => $object_type,
					'object_id'     => $object_id,
					'title'         => 'Resolved title',
					'description'   => 'Resolved description',
					'canonical_url' => 'https://example.com/a-post/',
					'robots'        => 'noindex, max-image-preview:large',
					'indexable'     => false,
					'private'       => 'never returned',
				);
			}
		};

		$this->assertSame(
			array(
				'post_id'       => 42,
				'title'         => 'Resolved title',
				'description'   => 'Resolved description',
				'canonical_url' => 'https://example.com/a-post/',
				'robots'        => 'noindex, max-image-preview:large',
				'indexable'     => false,
			),
			$this->get_abilities( $api )->get_context( array( 'post_id' => 42 ) )
		);
		$this->assertTrue( is_wp_error( $this->get_abilities( $api )->get_context( array( 'post_id' => 50 ) ) ) );
		$this->assertTrue( is_wp_error( $this->get_abilities()->get_context( array( 'post_id' => 42 ) ) ) );
	}

	private function get_abilities( $api = null ) {
		return new Lightweight_SEO_Abilities( new Lightweight_SEO_Post_Meta(), $api );
	}
}
