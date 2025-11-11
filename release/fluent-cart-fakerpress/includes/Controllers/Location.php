<?php
/**
 * Location REST Controller
 *
 * @since   1.0.0
 * @package FluentCartFakerPress\Controllers
 */

namespace FluentCartFakerPress\Controllers;

use FluentCartFakerPress\Abstracts\Controller;
use FluentCartFakerPress\Generators\Location as LocationGenerator;

/**
 * Location REST Controller Class
 *
 * Handles REST API endpoints for location data generation
 *
 * @since 1.0.0
 */
class Location extends Controller {


	/**
	 * Get resource type name
	 *
	 * @since 1.0.0
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'location';
	}

	/**
	 * Get resource type label for locations
	 *
	 * @since 1.0.0
	 *
	 * @return string The translated label for location resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'Location', 'fluent-cart-fakerpress' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * @since 1.0.0
	 *
	 * @return string REST base.
	 */
	protected function get_rest_base(): string {
		return 'locations';
	}

	/**
	 * Get generator instance
	 *
	 * @since 1.0.0
	 *
	 * @return LocationGenerator Generator instance.
	 */
	protected function get_generator_instance(): LocationGenerator {
		return new LocationGenerator();
	}

	/**
	 * Get resource-specific generation parameters
	 *
	 * @since 1.0.0
	 *
	 * @return array Resource-specific parameters.
	 */
	protected function get_resource_specific_params(): array {
		return array(
			'regions'          => array(
				'description'       => __( 'Geographic regions to generate locations for.', 'fluent-cart-fakerpress' ),
				'type'              => 'array',
				'items'             => array(
					'type' => 'string',
					'enum' => array(
						'Americas',
						'Europe',
						'Asia',
						'Africa',
						'Oceania',
						'Northern America',
						'Western Europe',
						'Eastern Europe',
						'Southern Europe',
						'Northern Europe',
						'Southeast Asia',
						'East Asia',
						'South Asia',
						'Western Asia',
						'North Africa',
						'Sub-Saharan Africa',
						'Australia and New Zealand',
					),
				),
				'sanitize_callback' => array( $this, 'sanitize_array' ),
			),
			'countries'        => array(
				'description'       => __( 'Specific countries to generate (ISO2, ISO3, or full names).', 'fluent-cart-fakerpress' ),
				'type'              => 'array',
				'items'             => array(
					'type' => 'string',
				),
				'sanitize_callback' => array( $this, 'sanitize_array' ),
			),
			'max_countries'    => array(
				'description'       => __( 'Maximum number of countries to generate.', 'fluent-cart-fakerpress' ),
				'type'              => 'integer',
				'minimum'           => 1,
				'maximum'           => 50,
				'default'           => 10,
				'sanitize_callback' => 'absint',
			),
			'include_states'   => array(
				'description' => __( 'Include states/provinces for countries.', 'fluent-cart-fakerpress' ),
				'type'        => 'boolean',
				'default'     => true,
			),
			'include_cities'   => array(
				'description' => __( 'Include cities for states/provinces.', 'fluent-cart-fakerpress' ),
				'type'        => 'boolean',
				'default'     => true,
			),
			'cities_per_state' => array(
				'description' => __( 'Maximum cities per state/province.', 'fluent-cart-fakerpress' ),
				'type'        => 'object',
				'properties'  => array(
					'min' => array(
						'description' => __( 'Minimum cities per state.', 'fluent-cart-fakerpress' ),
						'type'        => 'integer',
						'minimum'     => 1,
						'default'     => 3,
					),
					'max' => array(
						'description' => __( 'Maximum cities per state.', 'fluent-cart-fakerpress' ),
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 50,
						'default'     => 15,
					),
				),
			),
		);
	}

	/**
	 * Get resource-specific schema properties
	 *
	 * @since 1.0.0
	 *
	 * @return array Resource-specific properties.
	 */
	protected function get_resource_specific_properties(): array {
		return array(
			'locations' => array(
				'description' => __( 'Generated location hierarchy with countries, states, and cities.', 'fluent-cart-fakerpress' ),
				'type'        => 'array',
				'context'     => array( 'view' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'countries_created' => array(
							'type' => 'integer',
						),
						'total_states'      => array(
							'type' => 'integer',
						),
						'total_cities'      => array(
							'type' => 'integer',
						),
						'data_file_path'    => array(
							'type' => 'string',
						),
						'created_date'      => array(
							'type' => 'string',
						),
					),
				),
			),
		);
	}
}
