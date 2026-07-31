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
			// `customer_type` — singular, and enumerating individual/business/mixed — used to live
			// here while the admin and the MCP ability both declared `customer_types` with five
			// entirely different values. Nothing read either. One name and one vocabulary now.
			'customer_types'      => array(
				'description'       => __( 'Customer segments to draw from.', 'storeseeder' ),
				'type'              => 'array',
				'items'             => array(
					'type' => 'string',
					'enum' => array( 'regular', 'vip', 'wholesale', 'guest', 'returning' ),
				),
				'default'           => array( 'regular', 'returning' ),
				'sanitize_callback' => array( $this, 'sanitize_array' ),
			),
			'country_focus'       => array(
				'description' => __( 'Two-letter country codes to draw addresses from.', 'storeseeder' ),
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'default'     => array(),
			),
			'demographics'        => array(
				'description' => __( 'Demographic distribution.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'age_groups' => array(
						'description' => __( 'Age groups to draw birth dates from.', 'storeseeder' ),
						'type'        => 'array',
						'items'       => array(
							'type' => 'string',
							'enum' => array( '18-25', '26-35', '36-45', '46-55', '56-65', '65+' ),
						),
					),
				),
			),
			'address_preferences' => array(
				'description' => __( 'Address generation preferences.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'include_shipping'          => array(
						'description' => __( 'Generate a shipping address. Off leaves it the same as billing.', 'storeseeder' ),
						'type'        => 'boolean',
						'default'     => true,
					),
					'different_addresses_ratio' => array(
						'description' => __( 'Percentage whose shipping address differs from billing.', 'storeseeder' ),
						'type'        => 'integer',
						'minimum'     => 0,
						'maximum'     => 100,
						'default'     => 30,
					),
				),
			),
			'contact_preferences' => array(
				'description' => __( 'Contact and communication preferences.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'phone_numbers'          => array(
						'description' => __( 'Include phone numbers.', 'storeseeder' ),
						'type'        => 'boolean',
						'default'     => true,
					),
					'marketing_opt_in_ratio' => array(
						'description' => __( 'Percentage opted in to marketing.', 'storeseeder' ),
						'type'        => 'integer',
						'minimum'     => 0,
						'maximum'     => 100,
						'default'     => 60,
					),
				),
			),
			'include_history'     => array(
				'description' => __( 'Generate purchase-history metadata. This writes lifetime totals; it does not create orders.', 'storeseeder' ),
				'type'        => 'boolean',
				'default'     => true,
			),
			'loyalty_tier_focus'  => array(
				'description' => __( 'Loyalty tiers to draw from. Where set, the tier is chosen from this list rather than derived from spend.', 'storeseeder' ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
					'enum' => array( 'bronze', 'silver', 'gold', 'platinum' ),
				),
				'default'     => array(),
			),
			'account_status'      => array(
				'description' => __( 'Account status for generated customers. `mixed` spreads across all three.', 'storeseeder' ),
				'type'        => 'string',
				'enum'        => array( 'active', 'inactive', 'pending', 'mixed' ),
				'default'     => 'mixed',
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
