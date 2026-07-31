<?php
/**
 * Shipping Plan REST Controller
 *
 * @since   1.0.0
 * @package StoreSeeder\Rest\Controllers
 */

namespace StoreSeeder\Rest\Controllers;

use StoreSeeder\Rest\Controller;
use StoreSeeder\Generation\Generators\Shipping_Plan as ShippingPlanGenerator;

/**
 * Shipping Plan REST Controller Class
 *
 * Handles REST API endpoints for shipping plan generation
 *
 * @since 1.0.0
 */
class Shipping_Plan extends Controller {


	/**
	 * Get resource type name
	 *
	 * @since 1.0.0
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'shipping_plan';
	}

	/**
	 * Get resource type label for shipping plans
	 *
	 * @since 1.0.0
	 *
	 * @return string The translated label for shipping plan resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'Shipping Plan', 'storeseeder' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * @since 1.0.0
	 *
	 * @return string REST base.
	 */
	protected function get_rest_base(): string {
		return 'shipping-plans';
	}

	/**
	 * Get generator instance
	 *
	 * @since 1.0.0
	 *
	 * @return ShippingPlanGenerator Generator instance.
	 */
	protected function get_generator_instance(): ShippingPlanGenerator {
		return new ShippingPlanGenerator();
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
			'shipping_types'      => array(
				// Service levels, which is what these are. The old enum mixed three ideas: a service
				// (`standard`, `express`), a method type (`flat_rate`, `free`) and a calculation
				// method (`weight_based`). Neither platform has an "express" method *type* — both
				// have a flat rate that can be called one, with a delivery window and a name, which
				// is what the difference is. `flat_rate` is still accepted and means `standard`.
				'description'       => __( 'Service levels to generate. A flat rate underneath, named and timed for the service.', 'storeseeder' ),
				'type'              => 'array',
				'items'             => array(
					'type' => 'string',
					'enum' => array( 'standard', 'express', 'overnight', 'free', 'flat_rate' ),
				),
				'default'           => array( 'standard', 'express', 'free' ),
				'sanitize_callback' => array( $this, 'sanitize_array' ),
			),
			'cost_range'          => array(
				'description' => __( 'Shipping cost range. Free shipping costs nothing regardless.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'min' => array(
						'description' => __( 'Minimum shipping cost.', 'storeseeder' ),
						'type'        => 'number',
						'minimum'     => 0,
						'default'     => 5,
					),
					'max' => array(
						'description' => __( 'Maximum shipping cost.', 'storeseeder' ),
						'type'        => 'number',
						'minimum'     => 0,
						'default'     => 50,
					),
				),
			),
			'coverage_areas'      => array(
				'description'       => __( 'Which countries the zone covers. Domestic means wherever the store sells from.', 'storeseeder' ),
				'type'              => 'array',
				'items'             => array(
					'type' => 'string',
					'enum' => array( 'domestic', 'international', 'regional', 'worldwide' ),
				),
				'default'           => array( 'domestic', 'international' ),
				'sanitize_callback' => array( $this, 'sanitize_array' ),
			),
			'delivery_timeframes' => array(
				// `calculation_methods` used to sit beside this and is gone: neither platform has a
				// weight-, price- or quantity-based method in core, so three of its four values could
				// only ever have produced a flat rate under another name.
				'description' => __( 'Delivery estimate bounds, in days. The service level narrows them.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'min_days' => array(
						'description' => __( 'Minimum delivery days.', 'storeseeder' ),
						'type'        => 'integer',
						'minimum'     => 0,
						'default'     => 1,
					),
					'max_days' => array(
						'description' => __( 'Maximum delivery days.', 'storeseeder' ),
						'type'        => 'integer',
						'minimum'     => 0,
						'default'     => 14,
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
			'shipping_plans' => array(
				'description' => __( 'Generated shipping plans with methods and regions.', 'storeseeder' ),
				'type'        => 'array',
				'context'     => array( 'view' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'               => array(
							'type' => 'integer',
						),
						'name'             => array(
							'type' => 'string',
						),
						'description'      => array(
							'type' => 'string',
						),
						'active'           => array(
							'type' => 'boolean',
						),
						'taxable'          => array(
							'type' => 'boolean',
						),
						'calculation_base' => array(
							'type' => 'string',
						),
						'methods'          => array(
							'type' => 'array',
						),
						'regions'          => array(
							'type' => 'array',
						),
					),
				),
			),
		);
	}
}
