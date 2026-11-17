<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-admin-ui.php';
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
		$settings = new class() extends Lightweight_SEO_Settings {
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
		$this->assertArrayNotHasKey( 'seo_keywords', $post_meta->updates );
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
		$settings = new class() extends Lightweight_SEO_Settings {
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

	public function test_editor_renders_accessible_previews_checks_and_advanced_controls(): string {
		global $lightweight_seo_test_posts;

		$post                           = (object) array(
			'ID'           => 99,
			'post_title'   => 'A useful page title',
			'post_excerpt' => '',
			'post_content' => '<p>A concise page description for visitors and search previews.</p>',
			'permalink'    => 'https://example.com/useful-page/',
		);
		$lightweight_seo_test_posts[99] = $post;
		$lightweight_seo_test_posts[42] = (object) array(
			'metadata' => array(
				'width'  => 800,
				'height' => 420,
			),
		);

		$settings  = new class() extends Lightweight_SEO_Settings {
			public function get_all() {
				return array();
			}
			public function get_title_format() {
				return LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT;
			}
			public function get_social_image_url() {
				return '';
			}
		};
		$post_meta = new class() {
			public function get_all( $post_id ) {
				return array(
					'seo_title'          => '',
					'seo_description'    => '',
					'seo_canonical_url'  => 'https://outside.example/canonical/',
					'seo_noindex'        => '1',
					'social_title'       => '',
					'social_description' => '',
					'social_image_id'    => 42,
				);
			}
			public function get_social_image_url( $post_id ) {
				return 'https://example.com/social.jpg';
			}
		};

		ob_start();
		( new Lightweight_SEO_Meta_Boxes( $settings, $post_meta ) )->render_meta_box( $post );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'role="tablist"', $output );
		$this->assertStringContainsString( 'Search appearance', $output );
		$this->assertStringContainsString( 'lwseo-preview--search', $output );
		$this->assertStringContainsString( 'lwseo-preview--social', $output );
		$this->assertStringContainsString( 'Advanced canonical and robots controls', $output );
		$this->assertStringContainsString( 'below the 1200px width recommendation', $output );
		$this->assertStringContainsString( 'points to a different host', $output );
		$this->assertStringContainsString( 'do not predict rankings', $output );
		$this->assertStringNotContainsString( 'Meta Keywords', $output );

		return $output;
	}

	/**
	 * @depends test_editor_renders_accessible_previews_checks_and_advanced_controls
	 */
	public function test_every_check_carries_both_outcomes_for_the_live_script( string $output ): void {
		preg_match_all( '/<li class="lwseo-status is-(success|warning)"([^>]*)>/', $output, $rows, PREG_SET_ORDER );

		$this->assertNotEmpty( $rows );

		$states = array();

		foreach ( $rows as $row ) {
			$this->assertMatchesRegularExpression( '/ data-check="([a-z_]+)"/', $row[2] );
			$this->assertStringContainsString( ' data-pass="', $row[2] );
			$this->assertStringContainsString( ' data-fail="', $row[2] );
			preg_match( '/ data-check="([a-z_]+)"/', $row[2], $id );

			$states[ $id[1] ] = $row[1];
		}

		// The post has a foreign canonical, a noindex flag, and an 800px image; its short title is within range.
		$this->assertSame( 'warning', $states['canonical'] );
		$this->assertSame( 'warning', $states['indexing'] );
		$this->assertSame( 'warning', $states['social_image_width'] );
		$this->assertSame( 'success', $states['social_image'] );
		$this->assertStringContainsString( 'data-fail-invalid="The custom canonical URL is invalid."', $output );
		$this->assertStringContainsString( 'data-review-one="%d check to review"', $output );
		$this->assertStringContainsString( 'data-home-host="example.com"', $output );
	}

	/**
	 * @depends test_editor_renders_accessible_previews_checks_and_advanced_controls
	 */
	public function test_live_script_has_a_rule_for_every_check_the_server_prints( string $output ): void {
		$script = (string) file_get_contents( dirname( __DIR__ ) . '/admin/js/lightweight-seo-admin.js' );

		preg_match_all( '/ data-check="([a-z_]+)"/', $output, $ids );

		$ids = array_diff( array_unique( $ids[1] ), array( 'conflicts' ) );

		$this->assertNotEmpty( $ids );

		foreach ( $ids as $id ) {
			$this->assertMatchesRegularExpression( '/^\s*' . preg_quote( $id, '/' ) . ': function\(state\)/m', $script, 'No live rule for the "' . $id . '" check.' );
		}
	}

	public function test_checks_that_do_not_apply_are_printed_hidden(): void {
		global $lightweight_seo_test_posts;

		$post                           = (object) array(
			'ID'           => 77,
			'post_title'   => 'A page without a custom canonical or image',
			'post_excerpt' => '',
			'post_content' => '',
			'permalink'    => 'https://example.com/plain/',
		);
		$lightweight_seo_test_posts[77] = $post;

		$settings  = new class() extends Lightweight_SEO_Settings {
			public function get_all() {
				return array();
			}
			public function get_title_format() {
				return LIGHTWEIGHT_SEO_DEFAULT_TITLE_FORMAT;
			}
			public function get_social_image_url() {
				return 'https://example.com/default.jpg';
			}
		};
		$post_meta = new class() {
			public function get_all( $post_id ) {
				return array();
			}
			public function get_social_image_url( $post_id ) {
				return '';
			}
		};

		ob_start();
		( new Lightweight_SEO_Meta_Boxes( $settings, $post_meta ) )->render_meta_box( $post );
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-success" data-check="canonical"[^>]* hidden>/', $output );
		$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-success" data-check="social_image_width"[^>]* hidden>/', $output );
		// The site default feeds the preview and the check, but is not written into this page's own field.
		$this->assertStringContainsString( 'data-default-image="https://example.com/default.jpg"', $output );
		$this->assertMatchesRegularExpression( '/name="lightweight_seo_social_image" value="" class="lwseo-image__url"/', $output );
		$this->assertMatchesRegularExpression( '/<div class="lwseo-preview__media">\s*<img src="https:\/\/example\.com\/default\.jpg"/', $output );
	}

	/**
	 * Render the editor panel of a post with one stored visibility choice.
	 *
	 * @param string $own_choice  Stored per-page choice.
	 * @param bool   $type_hidden Whether posts are hidden from search results.
	 */
	private function render_with_visibility( $own_choice, $type_hidden ) {
		global $lightweight_seo_test_posts;

		$post                           = (object) array(
			'ID'           => 55,
			'post_type'    => 'post',
			'post_title'   => 'A post about visibility',
			'post_excerpt' => '',
			'post_content' => '',
			'permalink'    => 'https://example.com/visibility/',
		);
		$lightweight_seo_test_posts[55] = $post;

		$settings  = new class( $type_hidden ) extends Lightweight_SEO_Settings {
			private $type_hidden;

			public function __construct( $type_hidden ) {
				$this->type_hidden = $type_hidden;
			}

			public function get_all() {
				return array(
					'content_types' => array(
						'post_types' => array(
							'post' => array(
								'noindex'      => $this->type_hidden ? '1' : '0',
								'title_format' => '%title% %sep% Journal',
							),
						),
					),
				);
			}
		};
		$post_meta = new class( $own_choice ) {
			private $own_choice;

			public function __construct( $own_choice ) {
				$this->own_choice = $own_choice;
			}

			public function get_all( $post_id ) {
				return array( 'seo_noindex' => $this->own_choice );
			}

			public function get_social_image_url( $post_id ) {
				return '';
			}
		};

		ob_start();
		( new Lightweight_SEO_Meta_Boxes( $settings, $post_meta ) )->render_meta_box( $post );

		return (string) ob_get_clean();
	}

	public function test_search_visibility_is_a_three_way_choice_that_names_the_content_type_default(): void {
		$default_shown  = $this->render_with_visibility( '0', false );
		$default_hidden = $this->render_with_visibility( '', true );
		$own_show       = $this->render_with_visibility( 'index', true );
		$own_hide       = $this->render_with_visibility( '1', false );

		$this->assertStringContainsString( '<select id="lightweight_seo_noindex" name="lightweight_seo_noindex">', $default_shown );
		$this->assertStringContainsString( '<label class="lwseo-field__label" for="lightweight_seo_noindex">Search results</label>', $default_shown );
		$this->assertMatchesRegularExpression( '/<option value=""\s+selected=\'selected\'>Default for Posts \(currently shown\)<\/option>/', $default_shown );
		$this->assertStringContainsString( 'data-type-noindex="0"', $default_shown );
		$this->assertStringNotContainsString( 'type="checkbox" id="lightweight_seo_noindex"', $default_shown );
		$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-success" data-check="indexing"/', $default_shown );

		$this->assertMatchesRegularExpression( '/<option value=""\s+selected=\'selected\'>Default for Posts \(currently hidden\)<\/option>/', $default_hidden );
		$this->assertStringContainsString( 'data-type-noindex="1"', $default_hidden );
		$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-warning" data-check="indexing"[^>]*>.*?<p class="lwseo-status__text">This content type is hidden from search results/s', $default_hidden );
		$this->assertStringContainsString( 'data-fail-type="This content type is hidden from search results', $default_hidden );

		$this->assertMatchesRegularExpression( '/<option value="index"\s+selected=\'selected\'>Show in search results<\/option>/', $own_show );
		$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-success" data-check="indexing"/', $own_show );

		$this->assertMatchesRegularExpression( '/<option value="1"\s+selected=\'selected\'>Hide from search results<\/option>/', $own_hide );
		$this->assertMatchesRegularExpression( '/<li class="lwseo-status is-warning" data-check="indexing"[^>]*>.*?<p class="lwseo-status__text">This page is intentionally set to noindex/s', $own_hide );

		// The editor script resolves %sep% from these tokens, so it follows the default separator here.
		$this->assertStringContainsString( '&quot;%sep%&quot;:&quot;\u2013&quot;', $default_shown );

		// The request-dependent variables are printed for the script: the first page, and the year.
		$this->assertStringContainsString( '&quot;%page%&quot;:&quot;&quot;', $default_shown );
		$this->assertStringContainsString( '&quot;%year%&quot;:&quot;' . gmdate( 'Y' ) . '&quot;', $default_shown );
		$this->assertStringContainsString( 'data-token="%category%"', $default_shown );
		$this->assertStringContainsString( 'data-token="%page%"', $default_shown );

		// The preview and the title field fall back to the title format of the post type.
		$this->assertStringContainsString( 'data-title-format="%title% %sep% Journal"', $default_shown );
		$this->assertStringContainsString( 'data-fallback="A post about visibility – Journal"', $default_shown );
	}

	public function test_search_visibility_saves_one_of_three_values(): void {
		$post_meta  = new class() {
			public $updates = array();

			public function update( $post_id, $field, $value ) {
				$this->updates[ $field ] = $value;

				return true;
			}

			public function get( $post_id, $field ) {
				return '';
			}

			public function normalize_social_image( $image_url, $image_id, $previous_url, $previous_id ) {
				return array( $image_url, $image_id );
			}
		};
		$meta_boxes = new Lightweight_SEO_Meta_Boxes( new Lightweight_SEO_Settings(), $post_meta );

		foreach ( array(
			'index'  => 'index',
			'1'      => '1',
			''       => '0',
			'0'      => '0',
			'noidea' => '0',
		) as $posted => $stored ) {
			$_POST = array(
				'lightweight_seo_meta_box_nonce' => 'nonce',
				'lightweight_seo_noindex'        => (string) $posted,
			);

			$meta_boxes->save_meta_box_data( 55 );

			$this->assertSame( $stored, $post_meta->updates['seo_noindex'], 'Posted "' . $posted . '".' );
		}

		$_POST = array( 'lightweight_seo_meta_box_nonce' => 'nonce' );
		$meta_boxes->save_meta_box_data( 55 );

		$this->assertSame( '0', $post_meta->updates['seo_noindex'] );
	}

	public function test_every_title_variable_survives_saving_a_page(): void {
		$post_meta = new class() {
			public $updates = array();

			public function update( $post_id, $field, $value ) {
				$this->updates[ $field ] = $value;

				return true;
			}

			public function get( $post_id, $field ) {
				return '';
			}

			public function normalize_social_image( $image_url, $image_id, $previous_url, $previous_id ) {
				return array( $image_url, $image_id );
			}
		};

		$_POST = array(
			'lightweight_seo_meta_box_nonce'     => 'nonce',
			'lightweight_seo_title'              => '%title% %sep% %category% %sep% %year%',
			'lightweight_seo_description'        => 'By %author% in %category%.',
			'lightweight_seo_social_title'       => '%category%: %title%',
			'lightweight_seo_social_description' => 'Filed under %category%, page %page%.',
		);

		( new Lightweight_SEO_Meta_Boxes( new Lightweight_SEO_Settings(), $post_meta ) )->save_meta_box_data( 55 );

		$this->assertSame( '%title% %sep% %category% %sep% %year%', $post_meta->updates['seo_title'] );
		$this->assertSame( 'By %author% in %category%.', $post_meta->updates['seo_description'] );
		$this->assertSame( '%category%: %title%', $post_meta->updates['social_title'] );
		$this->assertSame( 'Filed under %category%, page %page%.', $post_meta->updates['social_description'] );
	}

	public function test_a_published_page_links_to_its_report_for_administrators(): void {
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_user_can;

		$render = function ( $status ) {
			global $lightweight_seo_test_posts;

			$post                           = (object) array(
				'ID'           => 64,
				'post_type'    => 'post',
				'post_status'  => $status,
				'post_title'   => 'A page to inspect',
				'post_excerpt' => '',
				'post_content' => '',
				'permalink'    => 'https://example.com/inspect-me/',
			);
			$lightweight_seo_test_posts[64] = $post;
			$post_meta                      = new class() {
				public function get_all( $post_id ) {
					return array();
				}

				public function get_social_image_url( $post_id ) {
					return '';
				}
			};

			ob_start();
			( new Lightweight_SEO_Meta_Boxes( new Lightweight_SEO_Settings(), $post_meta ) )->render_meta_box( $post );

			return (string) ob_get_clean();
		};

		$published = $render( 'publish' );

		$this->assertStringContainsString( '>Inspect what is sent for this page</a>', $published );
		$this->assertStringContainsString( 'tab=tools&amp;inspect=https%3A%2F%2Fexample.com%2Finspect-me%2F#lwseo-inspect', $published );
		$this->assertStringNotContainsString( 'Inspect what is sent', $render( 'draft' ) );

		$lightweight_seo_test_user_can = false;

		$this->assertStringNotContainsString( 'Inspect what is sent', $render( 'publish' ) );

		$lightweight_seo_test_user_can = true;
	}
}
