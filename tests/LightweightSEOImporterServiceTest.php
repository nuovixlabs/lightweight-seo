<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-importer-service.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOImporterServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_options   = array();
		$lightweight_seo_test_post_meta = array();
		$lightweight_seo_test_posts     = array();
	}

	public function test_preview_is_read_only_and_import_fills_empty_fields_only(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_post_meta = array(
			81 => array(
				'_yoast_wpseo_title'               => 'Imported Title',
				'_yoast_wpseo_metadesc'            => 'Imported Description',
				'_yoast_wpseo_meta-robots-noindex' => '1',
			),
		);
		$lightweight_seo_test_posts     = array(
			81 => (object) array(
				'ID'          => 81,
				'post_type'   => 'post',
				'post_status' => 'publish',
			),
		);
		$values                         = array( 81 => array( 'seo_title' => 'Keep Existing' ) );
		$service                        = new Lightweight_SEO_Importer_Service( $this->get_post_meta_stub( $values ) );

		$preview = $service->preview( 'yoast' );

		$this->assertSame( 1, $preview['scanned_posts'] );
		$this->assertSame( 1, $preview['eligible_posts'] );
		// The description, the hidden state, and "follow links" for the page this import hides.
		$this->assertSame( 3, $preview['updated_fields'] );
		$this->assertSame( 1, $preview['skipped_fields'] );
		$this->assertSame( array( 81 => array( 'seo_title' => 'Keep Existing' ) ), $values );

		$report = $service->import_batch( 'yoast' );

		$this->assertSame( 1, $report['imported_posts'] );
		$this->assertSame( 'Keep Existing', $values[81]['seo_title'] );
		$this->assertSame( 'Imported Description', $values[81]['seo_description'] );
		$this->assertSame( '1', $values[81]['seo_noindex'] );
		// Hidden by the import, so it prints "noindex" without the "nofollow" kept for pages hidden before 1.1.
		$this->assertSame( '0', $values[81]['seo_nofollow'] );
		$this->assertTrue( $service->has_rollback() );
	}

	public function test_a_page_that_was_already_hidden_keeps_its_follow_choice(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_post_meta = array( 81 => array( '_yoast_wpseo_meta-robots-noindex' => '1' ) );
		$lightweight_seo_test_posts     = array(
			81 => (object) array(
				'ID'          => 81,
				'post_type'   => 'post',
				'post_status' => 'publish',
			),
		);
		$values                         = array( 81 => array( 'seo_noindex' => '1' ) );

		( new Lightweight_SEO_Importer_Service( $this->get_post_meta_stub( $values ) ) )->import_batch( 'yoast' );

		$this->assertSame( array( 81 => array( 'seo_noindex' => '1' ) ), $values );
	}

	public function test_seopress_the_seo_framework_and_slim_seo_fields_are_read_from_where_each_plugin_stores_them(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_posts = array(
			55 => (object) array(
				'ID'             => 55,
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'attachment_url' => 'https://example.com/uploads/slim.jpg',
			),
		);

		foreach ( array( 81, 82, 83, 84 ) as $post_id ) {
			$lightweight_seo_test_posts[ $post_id ] = (object) array(
				'ID'          => $post_id,
				'post_type'   => 'post',
				'post_status' => 'publish',
			);
		}

		$lightweight_seo_test_post_meta = array(
			81 => array(
				'_seopress_titles_title'       => '%%post_title%% %%page%% %%sep%% %%sitetitle%%',
				'_seopress_titles_desc'        => 'About %%post_category%% by %%post_author%%, %%currentyear%%',
				'_seopress_analysis_target_kw' => 'kept, for, export',
				'_seopress_robots_canonical'   => 'https://example.com/canonical/',
				'_seopress_robots_index'       => 'yes',
				'_seopress_robots_follow'      => 'yes',
				'_seopress_robots_snippet'     => 'yes',
				'_seopress_social_fb_title'    => 'Shared %%wc_single_price%%',
				'_seopress_social_fb_desc'     => 'Shared description',
				'_seopress_social_fb_img'      => 'https://example.com/share.jpg',
			),
			82 => array(
				'_genesis_title'          => 'Framework title',
				'_genesis_description'    => 'Framework description',
				'_genesis_canonical_uri'  => 'https://example.com/framework/',
				'_genesis_noindex'        => '1',
				'_genesis_nofollow'       => '-1',
				'_genesis_noarchive'      => '1',
				'_open_graph_title'       => 'Framework share title',
				'_open_graph_description' => 'Framework share description',
				'_social_image_url'       => 'https://example.com/framework.jpg',
				'_social_image_id'        => '77',
			),
			83 => array(
				// "Always show" for one page, with the other choices left at the site default.
				'_genesis_noindex'   => -1,
				'_genesis_nofollow'  => 0,
				'_genesis_noarchive' => '',
			),
			84 => array(
				'slim_seo' => array(
					'title'          => '{{ post.title }} {{sep}} {{ page }} {{ sep }} {{ site.title }}',
					'description'    => 'By {{ author.display_name }} in {{ current.year }}, {{ post.custom_field }}',
					'canonical'      => 'https://example.com/slim/',
					'noindex'        => 1,
					'facebook_image' => '55',
				),
			),
		);

		$values  = array();
		$service = new Lightweight_SEO_Importer_Service( $this->get_post_meta_stub( $values ) );

		$report = $service->import_batch( 'seopress' );

		$this->assertSame(
			array(
				'seo_title'          => '%title% %sep% %page% %sep% %sitename%',
				'seo_description'    => 'About %category% by %author%, %year%',
				'seo_keywords'       => 'kept, for, export',
				'seo_canonical_url'  => 'https://example.com/canonical/',
				'seo_noindex'        => '1',
				'seo_nofollow'       => '1',
				'seo_nosnippet'      => '1',
				'social_description' => 'Shared description',
				'social_image'       => 'https://example.com/share.jpg',
			),
			$values[81]
		);
		// The WooCommerce price has no equivalent, so that one field waits for a person.
		$this->assertSame( 1, $report['unsupported_fields'] );
		$this->assertArrayNotHasKey( 82, $values );

		$values = array();
		$report = $service->import_batch( 'the_seo_framework' );

		$this->assertSame(
			array(
				'seo_title'          => 'Framework title',
				'seo_description'    => 'Framework description',
				'seo_canonical_url'  => 'https://example.com/framework/',
				'seo_noindex'        => '1',
				'seo_nofollow'       => '0',
				'seo_noarchive'      => '1',
				'social_title'       => 'Framework share title',
				'social_description' => 'Framework share description',
				'social_image'       => 'https://example.com/framework.jpg',
				'social_image_id'    => '77',
			),
			$values[82]
		);
		$this->assertSame( array( 'seo_noindex' => 'index' ), $values[83] );
		$this->assertSame( 0, $report['unsupported_fields'] );

		$values = array();
		$report = $service->import_batch( 'slim_seo' );

		$this->assertSame(
			array(
				'seo_title'         => '%title% %sep% %page% %sep% %sitename%',
				'seo_canonical_url' => 'https://example.com/slim/',
				'seo_noindex'       => '1',
				'social_image'      => 'https://example.com/uploads/slim.jpg',
				'seo_nofollow'      => '0',
			),
			$values[84]
		);
		$this->assertSame( 1, $report['unsupported_fields'] );

		// A source this importer does not know reads nothing.
		$values = array();
		$this->assertSame( 0, $service->import_batch( 'another_plugin' )['scanned_posts'] );
		$this->assertSame( array(), $values );
	}

	public function test_import_is_batched_and_exposes_a_stable_cursor(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		for ( $post_id = 1; $post_id <= 55; ++$post_id ) {
			$lightweight_seo_test_posts[ $post_id ]                           = (object) array(
				'ID'          => $post_id,
				'post_type'   => 'post',
				'post_status' => 'publish',
			);
			$lightweight_seo_test_post_meta[ $post_id ]['_yoast_wpseo_title'] = 'Title ' . $post_id;
		}

		$values  = array();
		$service = new Lightweight_SEO_Importer_Service( $this->get_post_meta_stub( $values ) );
		$first   = $service->import_batch( 'yoast', 0 );
		$second  = $service->import_batch( 'yoast', $first['next_offset'] );

		$this->assertSame( 50, $first['scanned_posts'] );
		$this->assertTrue( $first['has_more'] );
		$this->assertSame( 50, $first['next_offset'] );
		$this->assertSame( 5, $second['scanned_posts'] );
		$this->assertFalse( $second['has_more'] );
		$this->assertCount( 55, $values );
	}

	public function test_latest_batch_can_be_rolled_back(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_posts[9]                           = (object) array(
			'ID'          => 9,
			'post_type'   => 'post',
			'post_status' => 'publish',
		);
		$lightweight_seo_test_post_meta[9]['_yoast_wpseo_title'] = 'Imported';
		$values  = array();
		$service = new Lightweight_SEO_Importer_Service( $this->get_post_meta_stub( $values ) );

		$service->import_batch( 'yoast' );
		$report = $service->rollback_last_batch();

		$this->assertSame( 1, $report['restored_posts'] );
		$this->assertSame( 1, $report['restored_fields'] );
		$this->assertArrayNotHasKey( 'seo_title', $values[9] );
		$this->assertFalse( $service->has_rollback() );
	}

	private function get_post_meta_stub( &$values ) {
		return new class( $values ) {
			private $values;

			public function __construct( &$values ) {
				$this->values =& $values;
			}

			public function get_supported_post_types() {
				return array( 'post' );
			}

			public function get( $post_id, $field ) {
				return $this->values[ $post_id ][ $field ] ?? '';
			}

			public function get_meta_key( $field ) {
				return '_lightweight_seo_' . $field;
			}

			public function update( $post_id, $field, $value ) {
				$this->values[ $post_id ][ $field ] = $value;

				return true;
			}

			public function delete( $post_id, $field ) {
				unset( $this->values[ $post_id ][ $field ] );

				return true;
			}
		};
	}

	public function test_source_template_variables_are_converted_or_left_for_review(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_post_meta = array(
			91 => array(
				'_yoast_wpseo_title'    => 'Guide %%page%% %%sep%% %%sitename%%',
				'_yoast_wpseo_metadesc' => 'Read %%title%% in %%primary_category%%.',
			),
		);
		$lightweight_seo_test_posts     = array(
			91 => (object) array(
				'ID'          => 91,
				'post_type'   => 'post',
				'post_status' => 'publish',
			),
		);
		$values                         = array();
		$service                        = new Lightweight_SEO_Importer_Service( $this->get_post_meta_stub( $values ) );

		$report = $service->import_batch( 'yoast' );

		// The page variable has an equivalent now, so it is kept instead of dropped.
		$this->assertSame( 'Guide %page% %sep% %sitename%', $values[91]['seo_title'] );
		$this->assertArrayNotHasKey( 'seo_description', $values[91], 'An unconvertible variable must not be imported as literal text.' );
		$this->assertSame( 1, $report['updated_fields'] );
		$this->assertSame( 1, $report['unsupported_fields'] );
	}

	public function test_rank_math_and_aioseo_variables_map_to_native_tokens(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_post_meta = array(
			92 => array(
				'rank_math_title' => '%title% %page% %sep% %sitedesc%',
				'_aioseo_title'   => '#post_title #separator_sa #site_title',
			),
		);
		$lightweight_seo_test_posts     = array(
			92 => (object) array(
				'ID'          => 92,
				'post_type'   => 'post',
				'post_status' => 'publish',
			),
		);

		$rank_math_values = array();
		( new Lightweight_SEO_Importer_Service( $this->get_post_meta_stub( $rank_math_values ) ) )->import_batch( 'rank_math' );

		$aioseo_values = array();
		( new Lightweight_SEO_Importer_Service( $this->get_post_meta_stub( $aioseo_values ) ) )->import_batch( 'aioseo' );

		$this->assertSame( '%title% %page% %sep% %tagline%', $rank_math_values[92]['seo_title'] );
		$this->assertSame( '%title% %sep% %sitename%', $aioseo_values[92]['seo_title'] );
	}

	public function test_year_author_and_category_variables_convert_only_where_they_mean_the_same(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;

		$lightweight_seo_test_post_meta = array(
			93 => array(
				'_yoast_wpseo_title'    => 'Best lenses %%currentyear%% %%sep%% by %%name%%',
				// Yoast lists every category, which is not what %category% means here.
				'_yoast_wpseo_metadesc' => 'Filed under %%category%%.',
				'rank_math_title'       => '%title% %sep% %category% %sep% %currentyear% %sep% %name%',
				'rank_math_description' => 'Tagged %tags%.',
				'_aioseo_title'         => '#post_title #separator_sa #author_name #separator_sa #current_year',
				// A bare page number and a list of categories have no equivalent.
				'_aioseo_description'   => 'In #categories, page #page_number.',
			),
		);
		$lightweight_seo_test_posts     = array(
			93 => (object) array(
				'ID'          => 93,
				'post_type'   => 'post',
				'post_status' => 'publish',
			),
		);

		$yoast = array();
		( new Lightweight_SEO_Importer_Service( $this->get_post_meta_stub( $yoast ) ) )->import_batch( 'yoast' );

		$rank_math = array();
		( new Lightweight_SEO_Importer_Service( $this->get_post_meta_stub( $rank_math ) ) )->import_batch( 'rank_math' );

		$aioseo = array();
		( new Lightweight_SEO_Importer_Service( $this->get_post_meta_stub( $aioseo ) ) )->import_batch( 'aioseo' );

		$this->assertSame( 'Best lenses %year% %sep% by %author%', $yoast[93]['seo_title'] );
		$this->assertArrayNotHasKey( 'seo_description', $yoast[93] );
		$this->assertSame( '%title% %sep% %category% %sep% %year% %sep% %author%', $rank_math[93]['seo_title'] );
		$this->assertArrayNotHasKey( 'seo_description', $rank_math[93] );
		$this->assertSame( '%title% %sep% %author% %sep% %year%', $aioseo[93]['seo_title'] );
		$this->assertArrayNotHasKey( 'seo_description', $aioseo[93] );
	}
}
