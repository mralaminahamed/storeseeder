<?php
/**
 * Customer Generator REST Controller
 *
 * Handles REST API endpoints for customer data generation in StoreSeeder.
 * Provides endpoints for generating customers with addresses, metadata, and preferences.
 *
 * @since   1.0.0
 * @package StoreSeeder\Rest\Controllers
 */

namespace StoreSeeder\Rest\Controllers;

use StoreSeeder\Rest\Controller;
use StoreSeeder\Generation\Generators\Customer as CustomerGenerator;

/**
 * Customer Generator REST Controller
 *
 * Handles REST API endpoints for generating customer data in Fluent Cart stores.
 * Provides comprehensive customer generation with support for addresses, preferences,
 * purchase history, and metadata through the REST API.
 *
 * Endpoints:
 * - POST /wp-json/storeseeder/v1/customers/generate
 *
 * Features:
 * - Full customer creation with Fluent Cart integration
 * - Billing and shipping address generation
 * - Customer preferences and metadata
 * - Purchase history and loyalty data
 * - Multi-locale support for international customers
 *
 * @since 1.0.0
 */
class Customer extends Controller {


	/**
	 * Get resource type name
	 *
	 * @since 1.0.0
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'customer';
	}

	/**
	 * Get resource type label for customers
	 *
	 * @since 1.0.0
	 *
	 * @return string The translated label for customer resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'Customer', 'storeseeder' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * Returns the REST API base path for customer generation endpoints.
	 * Forms the endpoint URL: /wp-json/storeseeder/v1/customers/generate
	 *
	 * @since 1.0.0
	 *
	 * @return string REST base path segment ('customers').
	 */
	protected function get_rest_base(): string {
		return 'customers';
	}

	/**
	 * Get generator instance
	 *
	 * @since 1.0.0
	 *
	 * @return CustomerGenerator Generator instance.
	 */
	protected function get_generator_instance(): CustomerGenerator {
		return new CustomerGenerator();
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
			'customer_type'      => array(
				'description'       => __( 'Type of customers to generate.', 'storeseeder' ),
				'type'              => 'string',
				'enum'              => array( 'individual', 'business', 'mixed' ),
				'default'           => 'mixed',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'country_focus'      => array(
				'description' => __( 'Focus generation on specific countries.', 'storeseeder' ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
				),
				'default'     => array(),
			),
			'include_history'    => array(
				'description' => __( 'Include purchase history and loyalty data.', 'storeseeder' ),
				'type'        => 'boolean',
				'default'     => true,
			),
			'loyalty_tier_focus' => array(
				'description' => __( 'Focus on specific loyalty tiers.', 'storeseeder' ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
					'enum' => array( 'bronze', 'silver', 'gold', 'platinum' ),
				),
				'default'     => array(),
			),
			'account_status'     => array(
				'description' => __( 'Account status for generated customers.', 'storeseeder' ),
				'type'        => 'string',
				'enum'        => array( 'active', 'inactive', 'pending' ),
				'default'     => 'active',
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
			'customers' => array(
				'description' => __( 'Generated customers data.', 'storeseeder' ),
				'type'        => 'array',
				'context'     => array( 'view' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array(
							'type' => 'integer',
						),
						'name'    => array(
							'type' => 'string',
						),
						'email'   => array(
							'type' => 'string',
						),
						'phone'   => array(
							'type' => 'string',
						),
						'country' => array(
							'type' => 'string',
						),
					),
				),
			),
		);
	}
}
