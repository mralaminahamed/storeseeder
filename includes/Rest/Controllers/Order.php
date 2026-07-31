<?php
/**
 * Order Generator REST Controller
 *
 * Handles REST API endpoints for order data generation in StoreSeeder.
 * Provides endpoints for generating orders with customer data, items, and payments.
 *
 * @since   1.0.0
 * @package StoreSeeder\Rest\Controllers
 */

namespace StoreSeeder\Rest\Controllers;

use StoreSeeder\Platforms\Status;
use StoreSeeder\Rest\Controller;
use StoreSeeder\Generation\Generators\Order as OrderGenerator;

/**
 * Order Generator REST Controller
 *
 * Handles REST API endpoints for generating order data in Fluent Cart stores.
 * Provides comprehensive order generation with support for customer data, items,
 * payments, shipping, and order status through the REST API.
 *
 * Endpoints:
 * - POST /wp-json/storeseeder/v1/orders/generate
 *
 * Features:
 * - Full order creation with Fluent Cart integration
 * - Customer and item association
 * - Payment method and status configuration
 * - Shipping and tax calculation
 * - Order status management
 *
 * @since 1.0.0
 */
class Order extends Controller {


	/**
	 * Get resource type name
	 *
	 * @since 1.0.0
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'order';
	}

	/**
	 * Get resource type label for orders
	 *
	 * @since 1.0.0
	 *
	 * @return string The translated label for order resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'Order', 'storeseeder' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * Returns the REST API base path for order generation endpoints.
	 * Forms the endpoint URL: /wp-json/storeseeder/v1/orders/generate
	 *
	 * @since 1.0.0
	 *
	 * @return string REST base path segment ('orders').
	 */
	protected function get_rest_base(): string {
		return 'orders';
	}

	/**
	 * Get generator instance
	 *
	 * @since 1.0.0
	 *
	 * @return OrderGenerator Generator instance.
	 */
	protected function get_generator_instance(): OrderGenerator {
		return new OrderGenerator();
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
			'order_status'              => array(
				// The canonical vocabulary, all of it. The enum was missing `on_hold` and
				// `failed`, which the generator's own default spread uses — so two of the
				// statuses it produced could not be asked for.
				'description'       => __( 'Statuses to draw from for generated orders.', 'storeseeder' ),
				'type'              => 'array',
				'items'             => array(
					'type' => 'string',
					'enum' => Status::order_statuses(),
				),
				'default'           => array( 'completed', 'processing', 'pending' ),
				'sanitize_callback' => array( $this, 'sanitize_array' ),
			),
			'payment_methods'           => array(
				'description' => __( 'Payment methods to use for orders.', 'storeseeder' ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
					'enum' => array( 'stripe', 'paypal', 'cod', 'bank_transfer', 'check' ),
				),
				'default'     => array( 'stripe', 'paypal', 'cod' ),
			),
			// `order_value_range` was declared here and is gone. An order's total is the sum of
			// the catalogue prices of the products it points at, plus tax and shipping — so a
			// requested range could only be honoured by pricing line items at something other
			// than what the store sells them for, which makes every revenue figure disagree with
			// the catalogue it came from. Control the range through `price_range` on products.
			'geographical_distribution' => array(
				// Declared at last: the admin has been sending this and the generator reading it,
				// with nothing in between describing or validating it.
				'description' => __( 'Countries to draw order addresses from.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'countries' => array(
						'description' => __( 'Two-letter country codes.', 'storeseeder' ),
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
					),
				),
			),
			'include_customer'          => array(
				'description' => __( 'Attach a customer account. Off generates guest orders.', 'storeseeder' ),
				'type'        => 'boolean',
				'default'     => true,
			),
			'customer_id'               => array(
				'description' => __( 'Attach every order to this customer, for building one account an order history.', 'storeseeder' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
			'items_per_order'           => array(
				'description' => __( 'Number of items per order range.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'min' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 10,
						'default' => 1,
					),
					'max' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 20,
						'default' => 5,
					),
				),
			),
			'include_shipping'          => array(
				'description' => __( 'Include shipping costs in orders.', 'storeseeder' ),
				'type'        => 'boolean',
				'default'     => true,
			),
			'include_tax'               => array(
				'description' => __( 'Include tax calculations in orders.', 'storeseeder' ),
				'type'        => 'boolean',
				'default'     => true,
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
			'orders' => array(
				'description' => __( 'Generated orders data.', 'storeseeder' ),
				'type'        => 'array',
				'context'     => array( 'view' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array(
							'type' => 'integer',
						),
						'customer_id' => array(
							'type' => 'integer',
						),
						'total'       => array(
							'type' => 'string',
						),
						'status'      => array(
							'type' => 'string',
						),
						'items_count' => array(
							'type' => 'integer',
						),
					),
				),
			),
		);
	}
}
