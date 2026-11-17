<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-ui.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-post-list.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOPostListTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_hook_callbacks;
		global $lightweight_seo_test_nonce_is_valid;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_user_can;

		$lightweight_seo_test_hook_callbacks = array();
		$lightweight_seo_test_nonce_is_valid = true;
		$lightweight_seo_test_options        = array();
		$lightweight_seo_test_user_can       = true;
		$lightweight_seo_test_posts          = array(
			7 => (object) array(
				'ID'           => 7,
				'post_type'    => 'post',
				'post_excerpt' => '',
				'post_content' => '<p>Some content.</p>',
			),
			8 => (object) array(
				'ID'           => 8,
				'post_type'    => 'page',
				'post_excerpt' => '',
				'post_content' => '',
			),
		);
	}

	protected function tearDown(): void {
		$_POST    = array();
		$_REQUEST = array();
	}

	/**
	 * Build the list integration over a post meta service that records what is written.
	 *
	 * @param array $meta Stored SEO meta keyed by post ID.
	 */
	private function get_list( array $meta = array() ) {
		$post_meta = new class( $meta ) {
			public $meta;
			public $updates = array();

			public function __construct( array $meta ) {
				$this->meta = $meta;
			}

			public function get_supported_post_types() {
				return array( 'post', 'page', 'attachment' );
			}

			public function get_all( $post_id ) {
				return $this->meta[ $post_id ] ?? array();
			}

			public function update( $post_id, $field, $value ) {
				$this->updates[ $post_id ][ $field ] = $value;

				return true;
			}
		};

		return array( new Lightweight_SEO_Post_List( new Lightweight_SEO_Settings(), $post_meta ), $post_meta );
	}

	private function render_column( $post_list, $post_id ) {
		ob_start();
		$post_list->render_column( Lightweight_SEO_Post_List::COLUMN, $post_id );

		return (string) ob_get_clean();
	}

	public function test_the_column_and_inline_edit_hooks_cover_every_supported_list_except_media(): void {
		global $lightweight_seo_test_hook_callbacks;

		list( $post_list ) = $this->get_list();

		$this->assertArrayHasKey( 'admin_init', $lightweight_seo_test_hook_callbacks );
		$this->assertArrayNotHasKey( 'save_post', $lightweight_seo_test_hook_callbacks, 'Nothing is hooked before WordPress has registered its post types.' );

		$post_list->register_hooks();

		$this->assertSame( array( 'post', 'page' ), $post_list->get_post_types() );

		foreach ( array( 'manage_post_posts_columns', 'manage_post_posts_custom_column', 'manage_page_posts_columns', 'manage_page_posts_custom_column', 'quick_edit_custom_box', 'bulk_edit_custom_box', 'save_post' ) as $hook ) {
			$this->assertArrayHasKey( $hook, $lightweight_seo_test_hook_callbacks, $hook );
		}

		$this->assertArrayNotHasKey( 'manage_attachment_posts_columns', $lightweight_seo_test_hook_callbacks );
	}

	public function test_the_column_sits_before_the_date_or_last(): void {
		list( $post_list ) = $this->get_list();

		$this->assertSame(
			array( 'cb', 'title', 'author', Lightweight_SEO_Post_List::COLUMN, 'date' ),
			array_keys(
				$post_list->add_column(
					array(
						'cb'     => '',
						'title'  => 'Title',
						'author' => 'Author',
						'date'   => 'Date',
					)
				)
			)
		);
		$this->assertSame( array( 'title', Lightweight_SEO_Post_List::COLUMN ), array_keys( $post_list->add_column( array( 'title' => 'Title' ) ) ) );
		$this->assertSame( 'SEO', $post_list->add_column( array() )[ Lightweight_SEO_Post_List::COLUMN ] );
	}

	public function test_the_column_states_facts_about_the_title_description_and_visibility(): void {
		list( $post_list ) = $this->get_list(
			array(
				8 => array(
					'seo_title'       => '%title% %sep% "About"',
					'seo_description' => 'Written by hand.',
					'seo_noindex'     => '1',
				),
			)
		);

		$defaults = $this->render_column( $post_list, 7 );
		$own      = $this->render_column( $post_list, 8 );

		$this->assertStringStartsWith( 'Title: default<br>Description: from content<span', $defaults );
		$this->assertStringNotContainsString( 'Hidden from search', $defaults );
		$this->assertStringContainsString( 'data-title="" data-description="" data-noindex=""', $defaults );

		$this->assertStringStartsWith( 'Title: own<br>Description: own<br>Hidden from search<span', $own );
		// Quick Edit starts from the stored text, variables and all, not from what the page resolves to.
		$this->assertStringContainsString( 'data-title="%title% %sep% &quot;About&quot;" data-description="Written by hand." data-noindex="1"', $own );

		// Another column prints nothing.
		ob_start();
		$post_list->render_column( 'date', 7 );
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_the_column_says_when_there_is_nothing_to_describe_a_page_with(): void {
		list( $post_list ) = $this->get_list();

		$this->assertStringStartsWith( 'Title: default<br>Description: none<span', $this->render_column( $post_list, 8 ) );
	}

	public function test_the_column_follows_the_content_type_unless_the_item_chooses(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'content_types' => array( 'post_types' => array( 'post' => array( 'noindex' => '1' ) ) ) );

		list( $hidden_type )  = $this->get_list();
		list( $shown_anyway ) = $this->get_list( array( 7 => array( 'seo_noindex' => 'index' ) ) );

		$this->assertStringContainsString( 'Hidden from search', $this->render_column( $hidden_type, 7 ) );
		$this->assertStringNotContainsString( 'Hidden from search', $this->render_column( $hidden_type, 8 ) );
		$this->assertStringNotContainsString( 'Hidden from search', $this->render_column( $shown_anyway, 7 ) );
		$this->assertStringContainsString( 'data-noindex="index"', $this->render_column( $shown_anyway, 7 ) );
	}

	public function test_quick_edit_prints_native_fields_that_start_empty_and_unloaded(): void {
		list( $post_list ) = $this->get_list();

		ob_start();
		$post_list->render_quick_edit( Lightweight_SEO_Post_List::COLUMN, 'post' );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<fieldset class="inline-edit-col-left lightweight-seo-quick-edit">', $output );
		$this->assertStringContainsString( 'name="lightweight_seo_list_nonce" value="nonce-lightweight_seo_post_list"', $output );
		$this->assertStringContainsString( 'name="lightweight_seo_list_loaded" value=""', $output );
		$this->assertStringContainsString( 'name="lightweight_seo_list_title" value=""', $output );
		$this->assertStringContainsString( '<textarea name="lightweight_seo_list_description" rows="2"></textarea>', $output );
		// WordPress copies this row for every edit, so the select carries no ID to repeat.
		$this->assertStringContainsString( '<select name="lightweight_seo_list_noindex">', $output );
		$this->assertStringContainsString( 'Default for Posts (currently shown)', $output );
		$this->assertStringNotContainsString( ' id=', $output );
		$this->assertStringNotContainsString( 'lwseo-', $output, 'The list is a WordPress screen and uses no plugin component classes.' );

		foreach ( array( array( 'date', 'post' ), array( Lightweight_SEO_Post_List::COLUMN, 'attachment' ), array( Lightweight_SEO_Post_List::COLUMN, 'product' ) ) as $arguments ) {
			ob_start();
			$post_list->render_quick_edit( $arguments[0], $arguments[1] );
			$this->assertSame( '', ob_get_clean() );
		}
	}

	public function test_bulk_edit_offers_search_visibility_only_and_starts_on_no_change(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'content_types' => array( 'post_types' => array( 'page' => array( 'noindex' => '1' ) ) ) );

		list( $post_list ) = $this->get_list();

		ob_start();
		$post_list->render_bulk_edit( Lightweight_SEO_Post_List::COLUMN, 'page' );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( '<select name="lightweight_seo_list_bulk_noindex">', $output );
		$this->assertMatchesRegularExpression( '/<select name="lightweight_seo_list_bulk_noindex">\s*<option value="">— No change —<\/option>\s*<option value="default">Default for Pages \(currently hidden\)<\/option>\s*<option value="show">Show in search results<\/option>\s*<option value="hide">Hide from search results<\/option>/', $output );
		$this->assertStringContainsString( 'name="lightweight_seo_list_nonce"', $output );
		$this->assertStringNotContainsString( 'lightweight_seo_list_title', $output );
		$this->assertStringNotContainsString( 'lightweight_seo_list_description', $output );
		$this->assertStringNotContainsString( 'lightweight_seo_list_loaded', $output );
	}

	public function test_quick_edit_saves_the_three_fields_it_shows_and_nothing_else(): void {
		list( $post_list, $post_meta ) = $this->get_list();

		$_POST    = array(
			'lightweight_seo_list_nonce'       => 'nonce',
			'lightweight_seo_list_loaded'      => '1',
			'lightweight_seo_list_title'       => ' <b>%category%</b>: %title% %sep% %year% ',
			'lightweight_seo_list_description' => "By %author%\nin %category%.",
			'lightweight_seo_list_noindex'     => 'index',
		);
		$_REQUEST = $_POST;

		$post_list->save( 7 );

		$this->assertSame(
			array(
				'seo_title'       => '%category%: %title% %sep% %year%',
				'seo_description' => "By %author%\nin %category%.",
				'seo_noindex'     => 'index',
			),
			$post_meta->updates[7]
		);

		// The choice is stored the way the editor stores it.
		foreach ( array(
			''      => '0',
			'1'     => '1',
			'bogus' => '0',
		) as $posted => $stored ) {
			$_POST['lightweight_seo_list_noindex'] = (string) $posted;
			$post_list->save( 7 );

			$this->assertSame( $stored, $post_meta->updates[7]['seo_noindex'], 'Posted "' . $posted . '".' );
		}
	}

	public function test_hiding_a_page_from_the_list_does_not_make_it_look_like_a_page_hidden_before_1_1(): void {
		$hide = array(
			'bulk_edit'                         => 'Update',
			'lightweight_seo_list_nonce'        => 'nonce',
			'lightweight_seo_list_bulk_noindex' => 'hide',
		);

		// Never hidden before: its nofollow is recorded as off, so only noindex is printed.
		list( $post_list, $post_meta ) = $this->get_list();
		$_REQUEST                      = $hide;
		$post_list->save( 7 );
		$this->assertSame(
			array(
				'seo_nofollow' => '0',
				'seo_noindex'  => '1',
			),
			$post_meta->updates[7]
		);

		// Hidden before 1.1 and never saved since: it keeps its inherited nofollow.
		list( $post_list, $post_meta ) = $this->get_list( array( 7 => array( 'seo_noindex' => '1' ) ) );
		$post_list->save( 7 );
		$this->assertSame( array( 'seo_noindex' => '1' ), $post_meta->updates[7] );

		// A stored nofollow choice, either way, is left alone.
		foreach ( array( '1', '0' ) as $nofollow ) {
			list( $post_list, $post_meta ) = $this->get_list( array( 7 => array( 'seo_nofollow' => $nofollow ) ) );
			$post_list->save( 7 );
			$this->assertSame( array( 'seo_noindex' => '1' ), $post_meta->updates[7], 'Stored nofollow "' . $nofollow . '".' );
		}

		// Quick Edit follows the same rule.
		list( $post_list, $post_meta ) = $this->get_list();
		$_POST                         = array(
			'lightweight_seo_list_nonce'   => 'nonce',
			'lightweight_seo_list_loaded'  => '1',
			'lightweight_seo_list_noindex' => '1',
		);
		$_REQUEST                      = $_POST;
		$post_list->save( 7 );
		$this->assertSame(
			array(
				'seo_nofollow' => '0',
				'seo_noindex'  => '1',
			),
			$post_meta->updates[7]
		);
	}

	public function test_quick_edit_saves_nothing_unless_it_was_filled_from_the_row_and_allowed(): void {
		global $lightweight_seo_test_nonce_is_valid;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_user_can;

		$fields = array(
			'lightweight_seo_list_nonce'       => 'nonce',
			'lightweight_seo_list_loaded'      => '1',
			'lightweight_seo_list_title'       => '',
			'lightweight_seo_list_description' => '',
			'lightweight_seo_list_noindex'     => '',
		);

		// The fields were never filled from the row, so their blanks must not be saved.
		list( $post_list, $post_meta ) = $this->get_list();
		$_POST                         = array_merge( $fields, array( 'lightweight_seo_list_loaded' => '' ) );
		$_REQUEST                      = $_POST;
		$post_list->save( 7 );
		$this->assertSame( array(), $post_meta->updates, 'Not loaded.' );

		// No SEO nonce in the request: an ordinary save from somewhere else.
		$_POST    = array_diff_key( $fields, array( 'lightweight_seo_list_nonce' => '' ) );
		$_REQUEST = $_POST;
		$post_list->save( 7 );
		$this->assertSame( array(), $post_meta->updates, 'No nonce.' );

		$_POST    = $fields;
		$_REQUEST = $_POST;

		$lightweight_seo_test_nonce_is_valid = false;
		$post_list->save( 7 );
		$this->assertSame( array(), $post_meta->updates, 'Invalid nonce.' );
		$lightweight_seo_test_nonce_is_valid = true;

		$lightweight_seo_test_user_can = false;
		$post_list->save( 7 );
		$this->assertSame( array(), $post_meta->updates, 'Not allowed to edit this post.' );
		$lightweight_seo_test_user_can = true;

		// A revision, and a post type the list does not cover.
		$lightweight_seo_test_posts[9]  = (object) array(
			'ID'          => 9,
			'post_type'   => 'revision',
			'post_parent' => 7,
		);
		$lightweight_seo_test_posts[10] = (object) array(
			'ID'        => 10,
			'post_type' => 'attachment',
		);
		$post_list->save( 9 );
		$post_list->save( 10 );
		$this->assertSame( array(), $post_meta->updates, 'Revision or uncovered post type.' );

		// With everything in order, the same request saves.
		$post_list->save( 7 );
		$this->assertArrayHasKey( 7, $post_meta->updates );
	}

	public function test_bulk_edit_changes_search_visibility_only_when_a_choice_is_made(): void {
		list( $post_list, $post_meta ) = $this->get_list();

		foreach ( array(
			'hide'    => '1',
			'show'    => 'index',
			'default' => '0',
		) as $choice => $stored ) {
			$_REQUEST = array(
				'bulk_edit'                         => 'Update',
				'lightweight_seo_list_nonce'        => 'nonce',
				'lightweight_seo_list_bulk_noindex' => $choice,
			);

			$post_list->save( 7 );
			$post_list->save( 8 );

			$this->assertSame( $stored, $post_meta->updates[7]['seo_noindex'], 'Chose "' . $choice . '".' );
			$this->assertSame( $stored, $post_meta->updates[8]['seo_noindex'] );
		}

		list( $post_list, $post_meta ) = $this->get_list();

		foreach ( array( '', 'bogus' ) as $choice ) {
			$_REQUEST = array(
				'bulk_edit'                         => 'Update',
				'lightweight_seo_list_nonce'        => 'nonce',
				'lightweight_seo_list_bulk_noindex' => $choice,
				// Quick Edit fields that strayed into a bulk request are ignored.
				'lightweight_seo_list_loaded'       => '1',
				'lightweight_seo_list_title'        => 'Same for all',
			);
			$_POST    = $_REQUEST;

			$post_list->save( 7 );
		}

		$this->assertSame( array(), $post_meta->updates );
	}
}
