<?php
/**
 * Local SEO module implementation.
 *
 * @since 1.1.0
 * @package Lightweight_SEO
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/** Adds the configured LocalBusiness node to the core schema graph. */
class Lightweight_SEO_Local_SEO_Module {

	private $settings;

	public function __construct( $settings ) {
		$this->settings = $settings;
		add_filter( 'lightweight_seo_schema_graph', array( $this, 'add_local_business' ), 10, 2 );
	}

	public function add_local_business( $graph, $context ) {
		if ( ! is_home() && ! is_front_page() ) {
			return $graph;
		}

		$business = $this->settings->get_local_business_data();

		if ( ( isset( $business['valid'] ) && ! $business['valid'] ) || empty( $business['name'] ) ) {
			return $graph;
		}

		$schema = array(
			'@type' => $business['type'],
			'@id'   => home_url( '/#organization' ),
			'name'  => $business['name'],
			'url'   => home_url( '/' ),
		);

		if ( ! empty( $business['image'] ) ) {
			$schema['image'] = $business['image'];
			$schema['logo']  = $business['image'];
		}

		foreach ( array(
			'telephone'     => 'telephone',
			'price_range'   => 'priceRange',
			'opening_hours' => 'openingHours',
		) as $key => $schema_key ) {
			if ( ! empty( $business[ $key ] ) ) {
				$schema[ $schema_key ] = $business[ $key ];
			}
		}

		$address = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $business['street'],
				'addressLocality' => $business['locality'],
				'addressRegion'   => $business['region'],
				'postalCode'      => $business['postal_code'],
				'addressCountry'  => $business['country'],
			)
		);

		if ( count( $address ) > 1 ) {
			$schema['address'] = $address;
		}

		if ( ! empty( $business['latitude'] ) && ! empty( $business['longitude'] ) ) {
			$schema['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => $business['latitude'],
				'longitude' => $business['longitude'],
			);
		}

		$replaced = false;

		foreach ( $graph as $index => $node ) {
			if ( home_url( '/#organization' ) !== ( $node['@id'] ?? '' ) ) {
				continue;
			}

			if ( ! empty( $node['sameAs'] ) ) {
				$schema['sameAs'] = $node['sameAs'];
			}

			$graph[ $index ] = $schema;
			$replaced        = true;
			break;
		}

		if ( ! $replaced ) {
			$graph[] = $schema;
		}

		return $graph;
	}
}
