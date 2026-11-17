<?php

require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-schema-service.php';
require_once dirname( __DIR__ ) . '/includes/class-lightweight-seo-local-seo-module.php';

use PHPUnit\Framework\TestCase;

final class LightweightSEOSchemaServiceTest extends TestCase {

	protected function setUp(): void {
		global $lightweight_seo_test_attachment_urls;
		global $lightweight_seo_test_authors;
		global $lightweight_seo_test_home_url;
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_attachment_urls                  = array();
		$lightweight_seo_test_home_url                         = 'https://example.com';
		$lightweight_seo_test_options                          = array();
		$lightweight_seo_test_query_state['is_home']           = false;
		$lightweight_seo_test_query_state['is_front_page']     = false;
		$lightweight_seo_test_query_state['is_singular']       = false;
		$lightweight_seo_test_query_state['is_single']         = false;
		$lightweight_seo_test_query_state['is_author']         = false;
		$lightweight_seo_test_query_state['queried_object_id'] = 0;
		$lightweight_seo_test_query_state['permalink']         = 'https://example.com/post';
		$lightweight_seo_test_query_state['title']             = 'Test Title';
		$lightweight_seo_test_query_state['thumbnail_url']     = '';
		$lightweight_seo_test_query_state['queried_object']    = null;
		$lightweight_seo_test_query_state['post_author']       = 17;
		$lightweight_seo_test_query_state['published_date']    = '2024-01-01T12:00:00+00:00';
		$lightweight_seo_test_query_state['modified_date']     = '2024-01-02T12:00:00+00:00';
		$lightweight_seo_test_authors                          = array(
			17 => array(
				'display_name' => 'Author Name',
				'description'  => 'Author biography',
			),
		);
	}

	public function test_homepage_schema_outputs_organization_and_website_graph(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_home'] = true;

		$page_context = new class() {
			public function get_context() {
				return array(
					'document_title' => 'Test Site – Test Tagline',
					'canonical_url'  => 'https://example.com/',
					'description'    => 'Default description',
				);
			}
		};

		$settings = new class() extends Lightweight_SEO_Settings {
			public function schema_output_enabled() {
				return true;
			}

			public function get_social_image_url() {
				return 'https://example.com/logo.png';
			}

			public function get_organization_same_as() {
				return array( 'https://example.com/x' );
			}

			public function local_business_schema_enabled() {
				return false;
			}

			public function get_local_business_data() {
				return array();
			}

			public function product_schema_enabled() {
				return true;
			}
		};

		$service = new Lightweight_SEO_Schema_Service( $page_context, $settings );

		ob_start();
		$service->add_schema();
		$output = ob_get_clean();

		$this->assertStringContainsString( '"@type":"Organization"', $output );
		$this->assertStringContainsString( '"@type":"WebSite"', $output );
		$this->assertStringContainsString( '"sameAs":["https://example.com/x"]', $output );
		$this->assertStringContainsString( '"logo":"https://example.com/logo.png"', $output );
	}

	public function test_single_post_schema_outputs_article_and_breadcrumbs(): void {
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular']       = true;
		$lightweight_seo_test_query_state['is_single']         = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 42;
		$lightweight_seo_test_query_state['thumbnail_url']     = 'https://example.com/post-image.jpg';
		$lightweight_seo_test_posts[42]                        = (object) array(
			'ID'          => 42,
			'post_type'   => 'post',
			'post_status' => 'publish',
		);

		$page_context = new class() {
			public function get_context() {
				return array(
					'document_title' => 'My Post Title',
					'canonical_url'  => 'https://example.com/my-post',
					'description'    => 'Post description',
					'og_image'       => 'https://example.com/post-image.jpg',
				);
			}
		};

		$settings = new class() extends Lightweight_SEO_Settings {
			public function schema_output_enabled() {
				return true;
			}

			public function get_social_image_url() {
				return '';
			}

			public function get_organization_same_as() {
				return array();
			}

			public function local_business_schema_enabled() {
				return false;
			}

			public function get_local_business_data() {
				return array();
			}

			public function product_schema_enabled() {
				return true;
			}
		};

		$service = new Lightweight_SEO_Schema_Service( $page_context, $settings );

		ob_start();
		$service->add_schema();
		$output = ob_get_clean();

		$this->assertStringContainsString( '"@type":"Article"', $output );
		$this->assertStringContainsString( '"headline":"My Post Title"', $output );
		$this->assertStringContainsString( '"datePublished":"2024-01-01T12:00:00+00:00"', $output );
		$this->assertStringContainsString( '"@type":"BreadcrumbList"', $output );
		$this->assertStringContainsString( '"@type":"Person"', $output );
	}

	public function test_author_archive_outputs_profile_page_schema(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_author']         = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 17;
		$lightweight_seo_test_query_state['queried_object']    = (object) array(
			'ID'           => 17,
			'display_name' => 'Author Name',
		);

		$page_context = new class() {
			public function get_context() {
				return array(
					'document_title' => 'Author Name – Test Site',
					'canonical_url'  => 'https://example.com/author/17',
					'description'    => 'Author biography',
				);
			}
		};

		$settings = new class() extends Lightweight_SEO_Settings {
			public function schema_output_enabled() {
				return true;
			}

			public function get_social_image_url() {
				return '';
			}

			public function get_organization_same_as() {
				return array();
			}

			public function local_business_schema_enabled() {
				return false;
			}

			public function get_local_business_data() {
				return array();
			}

			public function product_schema_enabled() {
				return true;
			}
		};

		$service = new Lightweight_SEO_Schema_Service( $page_context, $settings );

		ob_start();
		$service->add_schema();
		$output = ob_get_clean();

		$this->assertStringContainsString( '"@type":"ProfilePage"', $output );
		$this->assertStringContainsString( '"mainEntity":{"@type":"Person","name":"Author Name","url":"https://example.com/author/17","description":"Author biography"}', $output );
	}

	public function test_homepage_schema_outputs_local_business_when_enabled(): void {
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_home'] = true;

		$page_context = new class() {
			public function get_context() {
				return array(
					'document_title' => 'Test Site – Test Tagline',
					'canonical_url'  => 'https://example.com/',
					'description'    => 'Default description',
				);
			}
		};

		$settings = new class() extends Lightweight_SEO_Settings {
			public function schema_output_enabled() {
				return true;
			}

			public function get_social_image_url() {
				return 'https://example.com/logo.png';
			}

			public function get_organization_same_as() {
				return array();
			}

			public function local_business_schema_enabled() {
				return true;
			}

			public function get_local_business_data() {
				return array(
					'valid'         => true,
					'type'          => 'Restaurant',
					'name'          => 'Example Cafe',
					'telephone'     => '+1-555-555-5555',
					'price_range'   => '$$',
					'street'        => '123 Main St',
					'locality'      => 'San Diego',
					'region'        => 'CA',
					'postal_code'   => '92101',
					'country'       => 'US',
					'latitude'      => '32.7157',
					'longitude'     => '-117.1611',
					'opening_hours' => array( 'Mo-Fr 09:00-17:00' ),
					'image'         => 'https://example.com/business.jpg',
				);
			}

			public function product_schema_enabled() {
				return true;
			}
		};

		$module = new Lightweight_SEO_Local_SEO_Module( $settings );
		$graph  = $module->add_local_business(
			array(
				array(
					'@type'  => 'Organization',
					'@id'    => 'https://example.com/#organization',
					'name'   => 'Test Site',
					'sameAs' => array( 'https://example.com/profile' ),
				),
			),
			$page_context->get_context()
		);
		$output = wp_json_encode( $graph );

		$this->assertCount( 1, $graph );
		$this->assertStringContainsString( '"@type":"Restaurant"', $output );
		$this->assertStringContainsString( '"@id":"https:\/\/example.com\/#organization"', $output );
		$this->assertStringContainsString( '"telephone":"+1-555-555-5555"', $output );
		$this->assertStringContainsString( '"openingHours":["Mo-Fr 09:00-17:00"]', $output );
		$this->assertStringContainsString( '"image":"https:\/\/example.com\/business.jpg"', $output );
		$this->assertStringNotContainsString( 'logo.png', $output );
	}

	public function test_single_product_schema_is_deferred_to_woocommerce(): void {
		global $lightweight_seo_test_post_meta;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular']       = true;
		$lightweight_seo_test_query_state['is_single']         = true;
		$lightweight_seo_test_query_state['queried_object_id'] = 55;
		$lightweight_seo_test_query_state['title']             = 'Example Product';
		$lightweight_seo_test_posts[55]                        = (object) array(
			'ID'           => 55,
			'post_type'    => 'product',
			'post_status'  => 'publish',
			'post_title'   => 'Example Product',
			'post_content' => 'Product description',
			'permalink'    => 'https://example.com/example-product/',
		);
		$lightweight_seo_test_post_meta[55]                    = array(
			'_price'         => '19.99',
			'_regular_price' => '24.99',
			'_sale_price'    => '',
			'_stock_status'  => 'instock',
			'_sku'           => 'SKU-123',
		);

		$page_context = new class() {
			public function get_context() {
				return array(
					'document_title' => 'Example Product',
					'canonical_url'  => 'https://example.com/example-product/',
					'description'    => 'Product description',
					'og_image'       => 'https://example.com/product.jpg',
				);
			}
		};

		$settings = new class() extends Lightweight_SEO_Settings {
			public function schema_output_enabled() {
				return true;
			}

			public function get_social_image_url() {
				return '';
			}

			public function get_organization_same_as() {
				return array();
			}

			public function local_business_schema_enabled() {
				return false;
			}

			public function get_local_business_data() {
				return array();
			}

			public function product_schema_enabled() {
				return true;
			}
		};

		$service = new Lightweight_SEO_Schema_Service( $page_context, $settings );

		ob_start();
		$service->add_schema();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( '"@type":"Product"', $output );
		$this->assertStringNotContainsString( '"@type":"Article"', $output );
	}

	public function test_graph_values_cannot_close_the_script_element(): void {
		global $lightweight_seo_test_filters;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular']              = false;
		$lightweight_seo_test_query_state['is_home']                  = true;
		$lightweight_seo_test_filters['lightweight_seo_schema_graph'] = static function ( $graph ) {
			$graph[] = array(
				'@type' => 'Thing',
				'name'  => '</script><script>alert(1)</script>',
			);

			return $graph;
		};

		$page_context = new class() {
			public function get_context() {
				return array(
					'document_title' => 'Home',
					'canonical_url'  => 'https://example.com/',
				);
			}
		};
		$settings     = new class() extends Lightweight_SEO_Settings {
			public function schema_output_enabled() {
				return true;
			}

			public function get_social_image_url() {
				return '';
			}

			public function get_organization_same_as() {
				return array();
			}
		};

		ob_start();
		( new Lightweight_SEO_Schema_Service( $page_context, $settings ) )->add_schema();
		$output = ob_get_clean();

		$lightweight_seo_test_filters                = array();
		$lightweight_seo_test_query_state['is_home'] = false;

		$this->assertSame( 1, substr_count( $output, '</script>' ), 'Only the closing tag written by the service may appear.' );
		$this->assertStringContainsString( '</script>', $output );
		$this->assertNotNull( json_decode( substr( $output, strlen( '<script type="application/ld+json">' ), -strlen( "</script>\n" ) ), true ) );
	}

	public function test_article_headline_and_breadcrumb_use_the_visible_title_not_the_title_tag(): void {
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_singular']       = true;
		$lightweight_seo_test_query_state['is_home']           = false;
		$lightweight_seo_test_query_state['is_front_page']     = false;
		$lightweight_seo_test_query_state['queried_object_id'] = 42;
		$lightweight_seo_test_posts[42]                        = (object) array(
			'ID'        => 42,
			'post_type' => 'post',
		);

		$page_context = new class() {
			public function get_context() {
				return array(
					'document_title' => 'My Post Title – Test Site',
					'object_title'   => 'My Post Title',
					'canonical_url'  => 'https://example.com/my-post',
				);
			}
		};
		$settings     = new class() extends Lightweight_SEO_Settings {
			public function schema_output_enabled() {
				return true;
			}

			public function get_social_image_url() {
				return '';
			}

			public function get_organization_same_as() {
				return array();
			}
		};

		ob_start();
		( new Lightweight_SEO_Schema_Service( $page_context, $settings ) )->add_schema();
		$output = ob_get_clean();

		$lightweight_seo_test_query_state['is_singular'] = false;

		$this->assertStringContainsString( '"headline":"My Post Title"', $output );
		$this->assertStringContainsString( '"name":"My Post Title","item":"https://example.com/my-post"', $output );
		$this->assertStringNotContainsString( 'My Post Title – Test Site', $output );
	}

	/**
	 * Print the schema of the homepage, or of a single post, for a set of stored settings.
	 *
	 * @param array $options Stored settings that differ from the defaults.
	 * @param bool  $single  Whether the request is a single post instead of the homepage.
	 * @return array Graph nodes keyed by their type.
	 */
	private function get_graph( array $options, $single = false ) {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_posts;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = $options;
		$lightweight_seo_test_query_state['is_home']                 = ! $single;
		$lightweight_seo_test_query_state['is_singular']             = $single;
		$lightweight_seo_test_query_state['is_single']               = $single;
		$lightweight_seo_test_query_state['queried_object_id']       = $single ? 42 : 0;
		$lightweight_seo_test_posts[42]                              = (object) array(
			'ID'          => 42,
			'post_type'   => 'post',
			'post_status' => 'publish',
		);

		$page_context = new class() {
			public function get_context() {
				return array(
					'document_title' => 'A title',
					'canonical_url'  => 'https://example.com/a-page',
				);
			}
		};

		ob_start();
		( new Lightweight_SEO_Schema_Service( $page_context, new Lightweight_SEO_Settings() ) )->add_schema();
		$output = (string) ob_get_clean();

		$this->assertSame( 1, preg_match( '#^<script type="application/ld\+json">(.*)</script>$#s', trim( $output ), $matches ) );

		$nodes = array();

		foreach ( json_decode( $matches[1], true )['@graph'] as $node ) {
			$nodes[ $node['@type'] ] = $node;
		}

		return $nodes;
	}

	public function test_untouched_identity_settings_print_the_same_organization_as_before(): void {
		$graph = $this->get_graph(
			array(
				'social_image'         => 'https://example.com/share.jpg',
				'organization_same_as' => "https://example.com/x\nhttps://example.com/y",
			)
		);

		$this->assertSame(
			array(
				'@type'  => 'Organization',
				'@id'    => 'https://example.com/#organization',
				'name'   => 'Test Site',
				'url'    => 'https://example.com/',
				'logo'   => 'https://example.com/share.jpg',
				'sameAs' => array( 'https://example.com/x', 'https://example.com/y' ),
			),
			$graph['Organization']
		);
		$this->assertSame( array( '@id' => 'https://example.com/#organization' ), $graph['WebSite']['publisher'] );
		$this->assertArrayNotHasKey( 'Person', $graph );

		// With no image at all, the node has no logo, as before.
		$this->assertArrayNotHasKey( 'logo', $this->get_graph( array() )['Organization'] );
	}

	public function test_an_organization_uses_its_own_name_and_logo_when_set(): void {
		global $lightweight_seo_test_attachment_urls;

		$lightweight_seo_test_attachment_urls[31] = 'https://example.com/uploads/logo-current.png';

		$graph = $this->get_graph(
			array(
				'identity_name'    => 'Acme Ltd',
				'identity_logo'    => 'https://example.com/uploads/logo-old.png',
				'identity_logo_id' => 31,
				'social_image'     => 'https://example.com/share.jpg',
			)
		);

		$this->assertSame( 'Acme Ltd', $graph['Organization']['name'] );
		$this->assertSame( 'https://example.com/uploads/logo-current.png', $graph['Organization']['logo'] );
		$this->assertArrayNotHasKey( 'image', $graph['Organization'] );
		// The site itself keeps its own name.
		$this->assertSame( 'Test Site', $graph['WebSite']['name'] );
	}

	public function test_a_person_is_printed_with_a_photo_and_becomes_the_publisher(): void {
		$options = array(
			'identity_type'        => 'person',
			'identity_name'        => 'Ada Lovelace',
			'identity_logo'        => 'https://example.com/uploads/ada.jpg',
			'social_image'         => 'https://example.com/share.jpg',
			'organization_same_as' => 'https://example.com/ada',
		);
		$home    = $this->get_graph( $options );
		$single  = $this->get_graph( $options, true );

		$this->assertSame(
			array(
				'@type'  => 'Person',
				'@id'    => 'https://example.com/#person',
				'name'   => 'Ada Lovelace',
				'url'    => 'https://example.com/',
				'image'  => 'https://example.com/uploads/ada.jpg',
				'sameAs' => array( 'https://example.com/ada' ),
			),
			$home['Person']
		);
		$this->assertArrayNotHasKey( 'Organization', $home );
		$this->assertSame( array( '@id' => 'https://example.com/#person' ), $home['WebSite']['publisher'] );
		$this->assertSame( array( '@id' => 'https://example.com/#person' ), $single['Article']['publisher'] );
		$this->assertSame( array( '@id' => 'https://example.com/#website' ), $single['Article']['isPartOf'] );
	}

	public function test_a_person_never_borrows_the_share_image_or_prints_a_logo(): void {
		$person = $this->get_graph(
			array(
				'identity_type' => 'person',
				'social_image'  => 'https://example.com/share.jpg',
			)
		)['Person'];

		$this->assertSame( 'Test Site', $person['name'] );
		$this->assertArrayNotHasKey( 'image', $person );
		$this->assertArrayNotHasKey( 'logo', $person );
	}

	public function test_a_local_business_is_printed_beside_a_person(): void {
		global $lightweight_seo_test_options;
		global $lightweight_seo_test_query_state;

		$lightweight_seo_test_query_state['is_home']                 = true;
		$lightweight_seo_test_options[ LIGHTWEIGHT_SEO_OPTION_NAME ] = array(
			'enable_local_business_schema'       => '1',
			'local_business_name'                => 'Example Studio',
			'local_business_address_street'      => '1 High Street',
			'local_business_address_locality'    => 'Hereford',
			'local_business_address_postal_code' => 'HR1 1AA',
			'local_business_address_country'     => 'GB',
		);

		$person = array(
			'@type' => 'Person',
			'@id'   => 'https://example.com/#person',
			'name'  => 'Ada Lovelace',
		);
		$graph  = ( new Lightweight_SEO_Local_SEO_Module( new Lightweight_SEO_Settings() ) )->add_local_business( array( $person ), array() );

		$this->assertCount( 2, $graph );
		$this->assertSame( $person, $graph[0] );
		$this->assertSame( 'LocalBusiness', $graph[1]['@type'] );
		$this->assertSame( 'https://example.com/#organization', $graph[1]['@id'] );
	}
}
