<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOSettingsTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_attachment_urls;
		global $lightweight_seo_test_options;

		$lightweight_seo_test_attachment_urls = array();
		$lightweight_seo_test_options         = array();
	}

	public function test_get_social_image_url_returns_current_attachment_url_when_the_attachment_remains_valid(): void {
		global $lightweight_seo_test_attachment_urls;
		global $lightweight_seo_test_options;

		$lightweight_seo_test_attachment_urls[14]                    = 'https://example.com/uploads/current-image.jpg';
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'social_image'    => 'https://example.com/uploads/old-image.jpg',
			'social_image_id' => 14,
		);

		$settings = new Lightweight_SEO_Settings();

		$this->assertSame( 'https://example.com/uploads/current-image.jpg', $settings->get_social_image_url() );
	}

	public function test_new_install_defaults_do_not_use_the_site_tagline_as_description(): void {
		$settings = new Lightweight_SEO_Settings();

		$this->assertSame( '', $settings->get_defaults()['meta_description'] );
		$this->assertArrayNotHasKey( 'enable_product_schema', $settings->get_defaults() );
		$this->assertArrayNotHasKey( 'enable_image_sitemaps', $settings->get_defaults() );
		$this->assertArrayNotHasKey( 'enable_meta_keywords', $settings->get_defaults() );
	}

	public function test_redirect_normalization_rejects_loops_and_caps_storage(): void {
		$settings = new Lightweight_SEO_Settings();
		$lines    = array(
			'/loop-a /loop-b 301',
			'/loop-b /loop-a 302',
			'/valid /destination 308',
			'/with-query?utm_source=test /clean-target?campaign=ignored 301',
			'/unsafe https://unapproved.example/destination 301',
		);

		for ( $index = 0; $index < 510; $index++ ) {
			$lines[] = '/source-' . $index . ' /target-' . $index . ' 301';
		}

		$normalized = $settings->normalize_redirect_rules_input( implode( "\n", $lines ) );
		$rules      = explode( "\n", $normalized );

		$this->assertCount( Lightweight_SEO_Settings::MAX_MANUAL_REDIRECTS, $rules );
		$this->assertStringNotContainsString( '/loop-a', $normalized );
		$this->assertStringNotContainsString( '/loop-b', $normalized );
		$this->assertStringNotContainsString( '/unsafe', $normalized );
		$this->assertSame( '/valid /destination 308', $rules[0] );
		$this->assertSame( '/with-query /clean-target 301', $rules[1] );
	}

	public function test_a_schema_type_is_stored_only_where_it_differs_from_the_default_of_the_post_type(): void {
		$settings   = new Lightweight_SEO_Settings();
		$normalized = $settings->normalize_content_types(
			array(
				'post_types' => array(
					// The default for posts, the default for pages, and something that is not a schema type.
					'post'       => array( 'schema' => 'Article' ),
					'page'       => array( 'schema' => 'WebPage' ),
					'portfolio'  => array( 'schema' => 'Thing' ),
					'news'       => array( 'schema' => 'NewsArticle' ),
					'attachment' => array( 'schema' => 'Article' ),
				),
				'taxonomies' => array(
					'category' => array( 'schema' => 'Article' ),
				),
			),
			array(),
			array( 'post', 'page', 'portfolio', 'news', 'attachment' ),
			array( 'category' )
		);

		$this->assertSame(
			array(
				'post_types' => array(
					'news' => array(
						'noindex'      => '0',
						'sitemap'      => '1',
						'title_format' => '',
						'schema'       => 'NewsArticle',
					),
				),
			),
			$normalized
		);

		// A choice other than the default is kept for posts and pages too, and saving it again changes nothing.
		$chosen = $settings->normalize_content_types(
			array(
				'post_types' => array(
					'post' => array( 'schema' => 'WebPage' ),
					'page' => array( 'schema' => 'BlogPosting' ),
				),
			),
			array(),
			array( 'post', 'page' ),
			array()
		);

		$this->assertSame( 'WebPage', $chosen['post_types']['post']['schema'] );
		$this->assertSame( 'BlogPosting', $chosen['post_types']['page']['schema'] );
		$this->assertSame( $chosen, $settings->normalize_content_types( $chosen, $chosen, array( 'post', 'page' ), array() ) );
	}

	public function test_redirect_rules_accept_gone_and_whole_sections(): void {
		$settings   = new Lightweight_SEO_Settings();
		$normalized = $settings->normalize_redirect_rules_input(
			implode(
				"\n",
				array(
					'/retired 410',
					'/retired-too /ignored-target 410',
					'/old-shop/* 410',
					'/old-blog/* /blog/* 302',
					'/old-docs/* /docs',
					'/moved/* https://example.com/elsewhere/*',
					'/everything/* /* 301',
					// The whole site, and a section carried into itself, are refused.
					'/* /new-home',
					'/grow/* /grow/deeper/*',
					'/same/* /same/*',
				)
			)
		);

		$this->assertSame(
			array(
				'/retired 410',
				'/retired-too 410',
				'/old-shop/* 410',
				'/old-blog/* /blog/* 302',
				'/old-docs/* /docs 301',
				'/moved/* https://example.com/elsewhere/* 301',
				'/everything/* /* 301',
			),
			explode( "\n", $normalized )
		);

		// Saving what was stored changes nothing.
		$this->assertSame( $normalized, $settings->normalize_redirect_rules_input( $normalized ) );

		$rules = $settings->parse_redirect_rules( $normalized );

		$this->assertSame(
			array(
				'source' => '/retired',
				'target' => '',
				'status' => 410,
			),
			$rules[0]
		);
		$this->assertSame( '/old-blog/*', $rules[3]['source'] );
		$this->assertSame( '/blog/*', $rules[3]['target'] );
	}

	public function test_redirect_hops_prefer_exact_rules_then_the_longest_section(): void {
		$settings = new Lightweight_SEO_Settings();
		$index    = Lightweight_SEO_Settings::index_redirect_rules(
			$settings->parse_redirect_rules(
				implode(
					"\n",
					array(
						'/old/* /new/*',
						'/old/special /landing 302',
						'/old/archive/* /archive',
						'/old/archive/2019/* 410',
						'/hub/* /hub/start',
					)
				)
			)
		);

		$hop = Lightweight_SEO_Settings::resolve_redirect_hop( '/old/special', $index );
		$this->assertSame( '/landing', $hop['target'] );
		$this->assertSame( 302, $hop['status'] );
		$this->assertSame( '/old/special', $hop['rule'] );

		// The rest of the path is carried over, and the section itself is covered too.
		$this->assertSame( '/new/a/b', Lightweight_SEO_Settings::resolve_redirect_hop( '/old/a/b', $index )['target'] );
		$this->assertSame( '/new', Lightweight_SEO_Settings::resolve_redirect_hop( '/old', $index )['target'] );
		$this->assertSame( '/old/*', Lightweight_SEO_Settings::resolve_redirect_hop( '/old/a/b', $index )['rule'] );

		// A longer section wins over a shorter one, and a target without "*" drops the rest.
		$this->assertSame( '/archive', Lightweight_SEO_Settings::resolve_redirect_hop( '/old/archive/2020/post', $index )['target'] );
		$this->assertSame( 410, Lightweight_SEO_Settings::resolve_redirect_hop( '/old/archive/2019/post', $index )['status'] );

		// A path that only starts with the same letters is not inside the section.
		$this->assertSame( array(), Lightweight_SEO_Settings::resolve_redirect_hop( '/older', $index ) );

		// A section that points at one of its own addresses leaves that address alone.
		$inside = Lightweight_SEO_Settings::trace_redirect_hops( '/hub/anything', $index );
		$this->assertCount( 1, $inside['hops'] );
		$this->assertFalse( $inside['loop'] );
		$this->assertSame( array(), Lightweight_SEO_Settings::trace_redirect_hops( '/hub/start', $index )['hops'] );
	}

	public function test_redirect_normalization_rejects_loops_between_sections(): void {
		$settings   = new Lightweight_SEO_Settings();
		$normalized = $settings->normalize_redirect_rules_input(
			implode(
				"\n",
				array(
					'/ping/* /pong/*',
					'/pong/* /ping/*',
					'/fine/* /settled/*',
				)
			)
		);

		$this->assertSame( '/fine/* /settled/* 301', $normalized );
	}

	public function test_hreflang_normalization_validates_tags_and_duplicate_targets(): void {
		$settings   = new Lightweight_SEO_Settings();
		$normalized = $settings->normalize_hreflang_mappings_input(
			implode(
				"\n",
				array(
					'post:42 EN-gb https://example.co.uk/page/',
					'post:42 en-GB https://duplicate.example/page/',
					'post:42 fr-FR https://example.co.uk/page/',
					'post:42 invalid_tag https://invalid.example/page/',
					'post:42 x-default https://example.com/page/',
				)
			)
		);

		$this->assertSame( "post:42 en-GB https://example.co.uk/page/\npost:42 x-default https://example.com/page/", $normalized );
	}

	public function test_local_business_normalization_accepts_a_complete_valid_location(): void {
		$settings   = new Lightweight_SEO_Settings();
		$normalized = $settings->normalize_local_business_input(
			array(
				'enable_local_business_schema'       => '1',
				'local_business_type'                => 'Restaurant',
				'local_business_name'                => 'Example Cafe',
				'local_business_phone'               => '+1 (555) 555-5555',
				'local_business_price_range'         => '$$',
				'local_business_address_street'      => '123 Main St',
				'local_business_address_locality'    => 'San Diego',
				'local_business_address_region'      => 'CA',
				'local_business_address_postal_code' => '92101',
				'local_business_address_country'     => 'us',
				'local_business_latitude'            => '32.7157',
				'local_business_longitude'           => '-117.1611',
				'local_business_opening_hours'       => "Mo-Fr 09:00-17:00\nSa 10:00-14:00",
				'local_business_image'               => 'https://example.com/business.jpg',
			)
		);

		$this->assertSame( array(), $normalized['errors'] );
		$this->assertTrue( $normalized['data']['valid'] );
		$this->assertSame( 'US', $normalized['data']['country'] );
		$this->assertSame( array( 'Mo-Fr 09:00-17:00', 'Sa 10:00-14:00' ), $normalized['data']['opening_hours'] );
	}

	public function test_local_business_normalization_rejects_invalid_or_incomplete_data(): void {
		$settings   = new Lightweight_SEO_Settings();
		$normalized = $settings->normalize_local_business_input(
			array(
				'enable_local_business_schema'   => '1',
				'local_business_name'            => 'Example Cafe',
				'local_business_phone'           => 'call-us',
				'local_business_address_street'  => '123 Main St',
				'local_business_address_country' => 'United States',
				'local_business_latitude'        => '91',
				'local_business_longitude'       => '-181',
				'local_business_opening_hours'   => 'weekdays',
			)
		);

		$this->assertFalse( $normalized['data']['valid'] );
		$this->assertNotEmpty( $normalized['errors'] );
		$this->assertSame( '', $normalized['data']['telephone'] );
		$this->assertSame( '', $normalized['data']['country'] );
		$this->assertSame( '', $normalized['data']['latitude'] );
		$this->assertSame( array(), $normalized['data']['opening_hours'] );
	}

	public function test_llms_selection_is_deduplicated_and_bounded(): void {
		$settings = new Lightweight_SEO_Settings();
		$ids      = range( 1, 60 );
		$ids[]    = 1;
		$ids[]    = 2;

		$normalized = $settings->normalize_llms_post_ids( implode( ', ', $ids ) );

		$this->assertCount( Lightweight_SEO_Settings::MAX_LLMS_POSTS, explode( ',', $normalized ) );
		$this->assertSame( '1', explode( ',', $normalized )[0] );
		$this->assertSame( '50', explode( ',', $normalized )[49] );
	}

	public function test_content_types_default_to_shown_listed_and_inheriting_the_site_title_formats(): void {
		$settings = new Lightweight_SEO_Settings();

		$this->assertSame( array(), $settings->get_defaults()['content_types'] );
		$this->assertFalse( $settings->is_post_type_noindex( 'post' ) );
		$this->assertFalse( $settings->is_taxonomy_noindex( 'post_tag' ) );
		$this->assertTrue( $settings->is_post_type_in_sitemap( 'post' ) );
		$this->assertTrue( $settings->is_taxonomy_in_sitemap( 'post_tag' ) );
		$this->assertSame( $settings->get_title_format(), $settings->get_title_format_for_post_type( 'post' ) );
		$this->assertSame( $settings->get_archive_title_format(), $settings->get_title_format_for_taxonomy( 'post_tag' ) );
		$this->assertFalse( $settings->author_archives_noindex_enabled() );
		$this->assertFalse( $settings->date_archives_noindex_enabled() );
	}

	public function test_content_type_rows_are_read_from_the_stored_table(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'noindex_author_archives' => '1',
			'noindex_date_archives'   => '1',
			'content_types'           => array(
				'post_types' => array(
					'portfolio' => array(
						'noindex'      => '1',
						'sitemap'      => '0',
						'title_format' => '%title% %sep% Portfolio',
					),
				),
				'taxonomies' => array(
					'post_tag' => array(
						'noindex'      => '1',
						'title_format' => 'Tagged &quot;%title%&quot;',
					),
				),
			),
		);

		$settings = new Lightweight_SEO_Settings();

		$this->assertTrue( $settings->is_post_type_noindex( 'portfolio' ) );
		$this->assertFalse( $settings->is_post_type_in_sitemap( 'portfolio' ) );
		$this->assertSame( '%title% %sep% Portfolio', $settings->get_title_format_for_post_type( 'portfolio' ) );
		$this->assertTrue( $settings->is_taxonomy_noindex( 'post_tag' ) );
		$this->assertTrue( $settings->is_taxonomy_in_sitemap( 'post_tag' ) );
		$this->assertSame( 'Tagged "%title%"', $settings->get_title_format_for_taxonomy( 'post_tag' ) );
		$this->assertFalse( $settings->is_post_type_noindex( 'post' ) );
		$this->assertTrue( $settings->author_archives_noindex_enabled() );
		$this->assertTrue( $settings->date_archives_noindex_enabled() );
	}

	public function test_attachment_visibility_keeps_its_original_setting(): void {
		global $lightweight_seo_test_options;

		$settings = new Lightweight_SEO_Settings();

		$this->assertTrue( $settings->is_post_type_noindex( 'attachment' ) );

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'noindex_attachment_pages' => '0',
			'content_types'            => array( 'post_types' => array( 'attachment' => array( 'noindex' => '1' ) ) ),
		);

		$this->assertFalse( ( new Lightweight_SEO_Settings() )->is_post_type_noindex( 'attachment' ) );
	}

	public function test_an_item_choice_wins_over_its_content_type_in_both_directions(): void {
		$settings = new Lightweight_SEO_Settings();

		$this->assertTrue( $settings->resolve_noindex( '1', false ) );
		$this->assertFalse( $settings->resolve_noindex( Lightweight_SEO_Settings::INDEX_OVERRIDE, true ) );
		$this->assertTrue( $settings->resolve_noindex( '0', true ) );
		$this->assertTrue( $settings->resolve_noindex( '', true ) );
		$this->assertFalse( $settings->resolve_noindex( '0', false ) );
		$this->assertFalse( $settings->resolve_noindex( null, false ) );

		$this->assertSame( '1', $settings->normalize_noindex_choice( '1' ) );
		$this->assertSame( 'index', $settings->normalize_noindex_choice( 'index' ) );
		$this->assertSame( '0', $settings->normalize_noindex_choice( '' ) );
		$this->assertSame( '0', $settings->normalize_noindex_choice( 'anything' ) );
		$this->assertSame( '0', $settings->normalize_noindex_choice( array( '1' ) ) );
	}

	public function test_content_types_normalization_stores_only_rows_that_differ_from_the_defaults(): void {
		$settings   = new Lightweight_SEO_Settings();
		$normalized = $settings->normalize_content_types(
			array(
				'post_types' => array(
					'post'       => array(
						'noindex'      => '0',
						'sitemap'      => '1',
						'title_format' => '',
					),
					'page'       => array(
						'noindex'      => '0',
						'sitemap'      => '0',
						'title_format' => ' <b>%title%</b> %sep% Pages ',
					),
					'attachment' => array(
						'noindex'      => '1',
						'sitemap'      => '0',
						'title_format' => '',
					),
					'unknown'    => array( 'noindex' => '1' ),
				),
				'taxonomies' => array(
					'category' => 'not a row',
					'post_tag' => array(
						'noindex' => '1',
						'sitemap' => array( '0' ),
					),
				),
				'archives'   => array( 'date' => array( 'noindex' => '1' ) ),
			),
			array(),
			array( 'post', 'page', 'attachment' ),
			array( 'category', 'post_tag' )
		);

		$this->assertSame(
			array(
				'post_types' => array(
					'page' => array(
						'noindex'      => '0',
						'sitemap'      => '0',
						'title_format' => '%title% %sep% Pages',
					),
				),
				'taxonomies' => array(
					'post_tag' => array(
						'noindex'      => '1',
						'sitemap'      => '1',
						'title_format' => '',
					),
				),
			),
			$normalized
		);
		$this->assertSame( array(), $settings->normalize_content_types( array(), array(), array( 'post' ), array( 'category' ) ) );
		$this->assertSame( array(), $settings->normalize_content_types( 'not a table', null, array( 'post' ), array( 'category' ) ) );
	}

	public function test_content_types_normalization_keeps_rows_the_form_did_not_submit(): void {
		$settings = new Lightweight_SEO_Settings();
		$existing = array(
			'post_types' => array(
				'portfolio' => array(
					'noindex'      => '1',
					'sitemap'      => '1',
					'title_format' => '',
				),
				'page'      => array(
					'noindex'      => '1',
					'sitemap'      => '1',
					'title_format' => '',
				),
			),
		);

		// The portfolio post type is not registered right now, and the page row was not part of the request.
		$normalized = $settings->normalize_content_types(
			array( 'post_types' => array( 'post' => array( 'noindex' => '1' ) ) ),
			$existing,
			array( 'post', 'page' ),
			array()
		);

		$this->assertSame( array( 'post', 'page', 'portfolio' ), array_keys( $normalized['post_types'] ) );
		$this->assertSame( '1', $normalized['post_types']['portfolio']['noindex'] );
		$this->assertSame( '1', $normalized['post_types']['page']['noindex'] );

		// Resetting a row to the defaults removes it.
		$reset = $settings->normalize_content_types(
			array(
				'post_types' => array(
					'page' => array(
						'noindex' => '0',
						'sitemap' => '1',
					),
				),
			),
			$existing,
			array( 'post', 'page' ),
			array()
		);

		$this->assertSame( array( 'portfolio' ), array_keys( $reset['post_types'] ) );
	}

	public function test_content_types_normalization_caps_stored_rows(): void {
		$settings = new Lightweight_SEO_Settings();
		$existing = array( 'taxonomies' => array() );

		for ( $index = 0; $index < 130; $index++ ) {
			$existing['taxonomies'][ 'retired_' . $index ] = array( 'noindex' => '1' );
		}

		$normalized = $settings->normalize_content_types( array(), $existing, array(), array() );

		$this->assertCount( Lightweight_SEO_Settings::MAX_CONTENT_TYPE_ROWS, $normalized['taxonomies'] );
	}

	public function test_identity_and_separator_defaults_keep_the_previous_output(): void {
		$settings = new Lightweight_SEO_Settings();

		$this->assertSame( LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR, $settings->get_title_separator() );
		$this->assertSame( LIGHTWEIGHT_SEO_DEFAULT_SEPARATOR, Lightweight_SEO_Settings::TITLE_SEPARATORS[0] );
		$this->assertSame( 'organization', $settings->get_identity_type() );
		$this->assertSame( get_bloginfo( 'name' ), $settings->get_identity_name() );
		$this->assertSame( '', $settings->get_identity_description() );
		$this->assertSame( '0', $settings->get_defaults()['enable_content_signals'] );
		$this->assertFalse( $settings->content_signals_enabled() );
		$this->assertSame( '', $settings->get_defaults()['identity_description'] );
		$this->assertSame( '', $settings->get_identity_logo_url() );
		$this->assertSame( '', $settings->get_x_handle() );
	}

	public function test_identity_description_is_read_as_one_line(): void {
		global $lightweight_seo_test_options;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'identity_description' => "  Makes oak furniture\n\nin   Leeds. " );

		$this->assertSame( 'Makes oak furniture in Leeds.', ( new Lightweight_SEO_Settings() )->get_identity_description() );
	}

	public function test_title_separator_is_limited_to_the_supported_characters(): void {
		global $lightweight_seo_test_options;

		$settings = new Lightweight_SEO_Settings();

		foreach ( Lightweight_SEO_Settings::TITLE_SEPARATORS as $separator ) {
			$this->assertSame( $separator, $settings->normalize_title_separator( $separator ) );
		}

		$this->assertSame( '–', $settings->normalize_title_separator( '<b>' ) );
		$this->assertSame( '|', $settings->normalize_title_separator( 'nope', '|' ) );
		$this->assertSame( '–', $settings->normalize_title_separator( array( '|' ) ) );

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'title_separator' => '|' );
		$this->assertSame( '|', ( new Lightweight_SEO_Settings() )->get_title_separator() );

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array( 'title_separator' => '<script>' );
		$this->assertSame( '–', ( new Lightweight_SEO_Settings() )->get_title_separator() );
	}

	public function test_identity_readers_use_the_stored_values(): void {
		global $lightweight_seo_test_attachment_urls;
		global $lightweight_seo_test_options;

		$lightweight_seo_test_attachment_urls[31]                    = 'https://example.com/uploads/portrait-current.jpg';
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'identity_type'    => 'person',
			'identity_name'    => '  Ada Lovelace ',
			'identity_logo'    => 'https://example.com/uploads/portrait-old.jpg',
			'identity_logo_id' => 31,
			'x_handle'         => 'ada_l',
		);

		$settings = new Lightweight_SEO_Settings();

		$this->assertSame( 'person', $settings->get_identity_type() );
		$this->assertSame( 'Ada Lovelace', $settings->get_identity_name() );
		$this->assertSame( 'https://example.com/uploads/portrait-current.jpg', $settings->get_identity_logo_url() );
		$this->assertSame( 'ada_l', $settings->get_x_handle() );

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'identity_type' => 'robot',
			'identity_logo' => 'https://example.com/uploads/logo.png',
			'x_handle'      => 'not a handle',
		);

		$settings = new Lightweight_SEO_Settings();

		$this->assertSame( 'organization', $settings->get_identity_type() );
		$this->assertSame( 'https://example.com/uploads/logo.png', $settings->get_identity_logo_url() );
		$this->assertSame( '', $settings->get_x_handle() );
	}

	public function test_x_handle_is_read_from_a_handle_or_a_profile_address(): void {
		$settings = new Lightweight_SEO_Settings();

		foreach ( array(
			'example'                          => 'example',
			'@Example_1'                       => 'Example_1',
			' @example '                       => 'example',
			'https://x.com/example'            => 'example',
			'https://x.com/@example/'          => 'example',
			'http://www.twitter.com/example'   => 'example',
			'twitter.com/example?lang=en'      => 'example',
			'https://mobile.twitter.com/a_b#x' => 'a_b',
			''                                 => '',
		) as $typed => $handle ) {
			$this->assertSame( $handle, $settings->normalize_x_handle( $typed ), 'Typed "' . $typed . '".' );
		}

		foreach ( array( 'two words', 'averyveryverylonghandle', 'https://example.com/example', 'bad-handle', 'https://x.com/example/status/1', '@' ) as $typed ) {
			$this->assertNull( $settings->normalize_x_handle( $typed ), 'Typed "' . $typed . '".' );
		}

		$this->assertSame( '', $settings->normalize_x_handle( array( 'example' ) ) );
	}

	public function test_a_verification_code_is_read_from_a_code_or_from_the_whole_meta_tag(): void {
		$settings = new Lightweight_SEO_Settings();

		foreach ( array(
			'abcDEF123_-xyz'       => 'abcDEF123_-xyz',
			'  0123456789ABCDEF  ' => '0123456789ABCDEF',
			'<meta name="google-site-verification" content="abcDEF123_-xyz" />' => 'abcDEF123_-xyz',
			"<meta name='msvalidate.01' content='0123456789ABCDEF'>" => '0123456789ABCDEF',
			'<meta content = " f00ba4 " name="p:domain_verify"/>' => 'f00ba4',
			''                     => '',
		) as $pasted => $code ) {
			$this->assertSame( $code, $settings->normalize_verification_code( $pasted ), 'Pasted "' . $pasted . '".' );
		}

		foreach ( array(
			'two words',
			'"><script>alert(1)</script>',
			'<meta name="google-site-verification" content="bad code!" />',
			'<meta name="google-site-verification" content="" />',
			str_repeat( 'a', 101 ),
		) as $pasted ) {
			$this->assertNull( $settings->normalize_verification_code( $pasted ), 'Pasted "' . $pasted . '".' );
		}

		$this->assertSame( '', $settings->normalize_verification_code( array( 'abc' ) ) );
	}

	public function test_verification_codes_are_listed_under_their_meta_tag_names(): void {
		global $lightweight_seo_test_options;

		$this->assertSame( array(), ( new Lightweight_SEO_Settings() )->get_verification_codes() );

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'verification_google'    => 'google-code_1',
			'verification_bing'      => '',
			'verification_pinterest' => 'not a code',
			'verification_yandex'    => 'f00ba4',
		);

		$this->assertSame(
			array(
				'google-site-verification' => 'google-code_1',
				'yandex-verification'      => 'f00ba4',
			),
			( new Lightweight_SEO_Settings() )->get_verification_codes()
		);
	}
}
