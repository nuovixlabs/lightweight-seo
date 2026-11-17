<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-post-meta.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-archive-meta.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-abilities.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOAbilitiesTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_abilities;
		global $lightweight_seo_test_ability_categories;
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_term_meta;
		global $lightweight_seo_test_terms;
		global $lightweight_seo_test_user_can;
		global $lightweight_seo_test_user_meta;
		global $lightweight_seo_test_users;

		$lightweight_seo_test_abilities          = array();
		$lightweight_seo_test_ability_categories = array();
		$lightweight_seo_test_post_meta          = array();
		$lightweight_seo_test_term_meta          = array();
		$lightweight_seo_test_user_meta          = array();
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
		$lightweight_seo_test_terms              = array(
			7 => (object) array(
				'term_id'  => 7,
				'name'     => 'Travel',
				'taxonomy' => 'category',
			),
			8 => (object) array(
				'term_id'  => 8,
				'name'     => 'Main menu',
				'taxonomy' => 'nav_menu',
			),
		);
		$lightweight_seo_test_users              = array(
			17 => (object) array(
				'ID'           => 17,
				'display_name' => 'Ada Lovelace',
			),
		);
	}

	protected function tearDown(): void {
		global $lightweight_seo_test_terms;
		global $lightweight_seo_test_user_can;
		global $lightweight_seo_test_users;

		$lightweight_seo_test_user_can = true;
		$lightweight_seo_test_terms    = array();
		$lightweight_seo_test_users    = array();
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
			// An ID is always needed; the kind of item is "post" unless it is named.
			$this->assertSame( array( 'object_id' ), $ability['input_schema']['required'] );
			$this->assertSame( array( 'post', 'term', 'user' ), $ability['input_schema']['properties']['object_type']['enum'] );
			$this->assertSame( 'post', $ability['input_schema']['properties']['object_type']['default'] );
			$this->assertFalse( $ability['input_schema']['additionalProperties'] );
			$this->assertTrue( $ability['meta']['public'] );
			$this->assertTrue( $ability['meta']['show_in_rest'] );
		}

		$this->assertTrue( $lightweight_seo_test_abilities['lightweight-seo/get-seo-fields']['meta']['annotations']['readonly'] );
		$this->assertFalse( $lightweight_seo_test_abilities['lightweight-seo/update-seo-fields']['meta']['annotations']['readonly'] );
		$this->assertTrue( $lightweight_seo_test_abilities['lightweight-seo/update-seo-fields']['meta']['annotations']['destructive'] );
		$this->assertSame( array( 'default', 'show', 'hide' ), $lightweight_seo_test_abilities['lightweight-seo/update-seo-fields']['input_schema']['properties']['search_visibility']['enum'] );
	}

	public function test_every_ability_needs_the_right_to_edit_that_item(): void {
		global $lightweight_seo_test_user_can;

		$abilities = $this->get_abilities();

		$this->assertTrue( $abilities->can_edit( array( 'object_id' => 42 ) ) );
		$this->assertTrue(
			$abilities->can_edit(
				array(
					'object_type' => 'term',
					'object_id'   => 7,
				)
			)
		);
		$this->assertTrue(
			$abilities->can_edit(
				array(
					'object_type' => 'user',
					'object_id'   => 17,
				)
			)
		);
		$this->assertFalse( $abilities->can_edit( array() ) );
		$this->assertFalse( $abilities->can_edit( null ) );
		$this->assertFalse( $abilities->can_edit( array( 'object_id' => 'not a number' ) ) );
		$this->assertFalse(
			$abilities->can_edit(
				array(
					'object_type' => 'comment',
					'object_id'   => 42,
				)
			)
		);

		$lightweight_seo_test_user_can = false;

		foreach ( array(
			'post' => 42,
			'term' => 7,
			'user' => 17,
		) as $type => $object_id ) {
			$this->assertFalse(
				$abilities->can_edit(
					array(
						'object_type' => $type,
						'object_id'   => $object_id,
					)
				),
				$type
			);
		}
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
				'object_type'        => 'post',
				'object_id'          => 42,
				'title'              => '%title% %sep% %category%',
				'description'        => 'A description',
				'canonical_url'      => '',
				'social_title'       => '',
				'social_description' => '',
				'social_image'       => '',
				'search_visibility'  => 'show',
			),
			$this->get_abilities()->get_fields( array( 'object_id' => 42 ) )
		);

		// A post that does not exist, and a content type without SEO fields, are refused.
		$this->assertTrue( is_wp_error( $this->get_abilities()->get_fields( array( 'object_id' => 999 ) ) ) );
		$this->assertTrue( is_wp_error( $this->get_abilities()->get_fields( array( 'object_id' => 50 ) ) ) );
	}

	public function test_terms_and_authors_have_the_same_fields_without_the_sharing_ones(): void {
		global $lightweight_seo_test_term_meta;
		global $lightweight_seo_test_user_meta;

		$lightweight_seo_test_term_meta[7] = array(
			'_lightweight_seo_title'   => 'Travel stories %sep% %sitename%',
			'_lightweight_seo_noindex' => '1',
		);

		$abilities = $this->get_abilities();
		$term      = array(
			'object_type' => 'term',
			'object_id'   => 7,
		);
		$author    = array(
			'object_type' => 'user',
			'object_id'   => 17,
		);

		$this->assertSame(
			array(
				'object_type'       => 'term',
				'object_id'         => 7,
				'title'             => 'Travel stories %sep% %sitename%',
				'description'       => '',
				'canonical_url'     => '',
				'search_visibility' => 'hide',
			),
			$abilities->get_fields( $term )
		);

		$updated = $abilities->update_fields(
			$term + array(
				'description'       => ' Stories from <em>the road</em> ',
				'search_visibility' => 'default',
			)
		);

		$this->assertSame( 'Stories from the road', $updated['description'] );
		$this->assertSame( 'default', $updated['search_visibility'] );
		$this->assertSame( 'Travel stories %sep% %sitename%', $updated['title'] );
		// Only posts carry the follow choice that goes with hiding a page.
		$this->assertArrayNotHasKey( '_lightweight_seo_nofollow', $lightweight_seo_test_term_meta[7] );

		$updated = $abilities->update_fields(
			$author + array(
				'title'             => 'Posts by %author%',
				'canonical_url'     => 'https://example.com/team/ada/',
				'search_visibility' => 'hide',
			)
		);

		$this->assertSame( 'user', $updated['object_type'] );
		$this->assertSame( 'Posts by %author%', $updated['title'] );
		$this->assertSame( 'hide', $updated['search_visibility'] );
		$this->assertSame( 'https://example.com/team/ada/', $lightweight_seo_test_user_meta[17]['_lightweight_seo_canonical_url'] );
		$this->assertArrayNotHasKey( '_lightweight_seo_nofollow', $lightweight_seo_test_user_meta[17] );

		// A sharing field sent for a term or an author saves nothing at all.
		$before = array( $lightweight_seo_test_term_meta, $lightweight_seo_test_user_meta );

		foreach ( array( $term, $author ) as $target ) {
			$result = $abilities->update_fields(
				$target + array(
					'title'        => 'Changed',
					'social_title' => 'Not for this kind of item',
				)
			);

			$this->assertTrue( is_wp_error( $result ) );
		}

		$this->assertSame( $before, array( $lightweight_seo_test_term_meta, $lightweight_seo_test_user_meta ) );

		// A term of a taxonomy without SEO fields, and a user that does not exist, are refused.
		$this->assertTrue(
			is_wp_error(
				$abilities->get_fields(
					array(
						'object_type' => 'term',
						'object_id'   => 8,
					)
				)
			)
		);
		$this->assertTrue(
			is_wp_error(
				$abilities->get_fields(
					array(
						'object_type' => 'user',
						'object_id'   => 99,
					)
				)
			)
		);
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
				'object_id'         => 42,
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
		$reset = $abilities->update_fields(
			array(
				'object_id'         => 42,
				'search_visibility' => 'default',
			)
		);

		$this->assertSame( 'default', $reset['search_visibility'] );
		$this->assertSame( '0', $lightweight_seo_test_post_meta[42]['_lightweight_seo_noindex'] );
	}

	public function test_an_update_with_a_value_that_is_not_a_web_address_saves_nothing(): void {
		global $lightweight_seo_test_post_meta;

		$lightweight_seo_test_post_meta[42] = array( '_lightweight_seo_title' => 'Old title' );

		$before = $lightweight_seo_test_post_meta;

		foreach ( array( 'javascript:alert(1)', 'not an address', 'ftp://example.com/file' ) as $address ) {
			$result = $this->get_abilities()->update_fields(
				array(
					'object_id'     => 42,
					'title'         => 'New title',
					'canonical_url' => $address,
				)
			);

			$this->assertTrue( is_wp_error( $result ), $address );
			$this->assertSame( $before, $lightweight_seo_test_post_meta, $address );
		}

		$unsupported = $this->get_abilities()->update_fields(
			array(
				'object_id' => 50,
				'title'     => 'x',
			)
		);

		$this->assertTrue( is_wp_error( $unsupported ) );
	}

	public function test_the_seo_result_comes_from_the_public_api_for_each_kind_of_item(): void {
		$api = new class() {
			public $calls = array();

			public function get_object_context( $object_type, $object_id ) {
				$this->calls[] = array( $object_type, $object_id );

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
				'object_type'   => 'post',
				'object_id'     => 42,
				'title'         => 'Resolved title',
				'description'   => 'Resolved description',
				'canonical_url' => 'https://example.com/a-post/',
				'robots'        => 'noindex, max-image-preview:large',
				'indexable'     => false,
			),
			$this->get_abilities( $api )->get_context( array( 'object_id' => 42 ) )
		);

		$term = $this->get_abilities( $api )->get_context(
			array(
				'object_type' => 'term',
				'object_id'   => 7,
			)
		);

		$this->assertSame( 'term', $term['object_type'] );
		$this->assertSame( array( array( 'post', 42 ), array( 'term', 7 ) ), $api->calls );
		$this->assertTrue( is_wp_error( $this->get_abilities( $api )->get_context( array( 'object_id' => 50 ) ) ) );
		$this->assertTrue( is_wp_error( $this->get_abilities()->get_context( array( 'object_id' => 42 ) ) ) );
	}

	private function get_abilities( $api = null ) {
		$settings = new Lightweight_SEO_Settings();

		return new Lightweight_SEO_Abilities( new Lightweight_SEO_Post_Meta(), new Lightweight_SEO_Archive_Meta( $settings ), $api );
	}
}
