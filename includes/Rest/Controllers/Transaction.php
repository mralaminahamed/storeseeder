<?php
/**
 * Transaction Generator REST Controller
 *
 * Handles REST API endpoints for transaction data generation in StoreSeeder.
 * Provides endpoints for generating payment transactions with various methods and statuses.
 *
 * @since   1.0.0
 * @package StoreSeeder\Rest\Controllers
 */

namespace StoreSeeder\Rest\Controllers;

use StoreSeeder\Platforms\Status;

use StoreSeeder\Rest\Controller;
use StoreSeeder\Generation\Generators\Transaction as TransactionGenerator;

/**
 * Transaction Generator REST Controller
 *
 * Handles REST API endpoints for generating transaction data in Fluent Cart stores.
 * Provides comprehensive transaction generation with support for payment methods,
 * transaction statuses, amounts, and order associations.
 *
 * Endpoints:
 * - POST /wp-json/storeseeder/v1/transactions/generate
 *
 * Features:
 * - Full transaction creation with Fluent Cart integration
 * - Multiple payment method support
 * - Transaction status management
 * - Order association and amount tracking
 * - Gateway-specific metadata
 *
 * @since 1.0.0
 */
class Transaction extends Controller {


	/**
	 * Get resource type name
	 *
	 * @since 1.0.0
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'transaction';
	}

	/**
	 * Get resource type label for transactions
	 *
	 * @since 1.0.0
	 *
	 * @return string The translated label for transaction resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'Transaction', 'storeseeder' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * Returns the REST API base path for transaction generation endpoints.
	 * Forms the endpoint URL: /wp-json/storeseeder/v1/transactions/generate
	 *
	 * @since 1.0.0
	 *
	 * @return string REST base path segment ('transactions').
	 */
	protected function get_rest_base(): string {
		return 'transactions';
	}

	/**
	 * Get generator instance
	 *
	 * @since 1.0.0
	 *
	 * @return TransactionGenerator Generator instance.
	 */
	protected function get_generator_instance(): TransactionGenerator {
		return new TransactionGenerator();
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
			'payment_methods'          => array(
				'description'       => __( 'Payment methods to generate transactions for.', 'storeseeder' ),
				'type'              => 'array',
				'items'             => array(
					'type' => 'string',
					'enum' => array( 'stripe', 'paypal', 'cod', 'bank_transfer', 'check', 'credit_card', 'debit_card' ),
				),
				'default'           => array( 'stripe', 'paypal', 'cod' ),
				'sanitize_callback' => array( $this, 'sanitize_array' ),
			),
			'transaction_statuses'     => array(
				// The canonical vocabulary. `cancelled` and `partially_refunded` were offered here
				// and are not transaction states any gateway or platform models — a cancelled
				// payment is a failed one, and a partial refund is a refund transaction for part of
				// the amount. `refunded` and `disputed` are set by the *type*, so asking for them
				// here does nothing; they are accepted and ignored rather than removed, because a
				// caller listing every status should not get an error.
				'description'       => __( 'Statuses to draw charges from. Refunds and disputes carry their own status.', 'storeseeder' ),
				'type'              => 'array',
				'items'             => array(
					'type' => 'string',
					'enum' => Status::transaction_statuses(),
				),
				'default'           => array( 'completed', 'pending', 'failed' ),
				'sanitize_callback' => array( $this, 'sanitize_array' ),
			),
			'amount_range'             => array(
				'description' => __( 'Transaction amount range.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'min' => array(
						'description' => __( 'Minimum transaction amount.', 'storeseeder' ),
						'type'        => 'number',
						'minimum'     => 0.01,
						'default'     => 10,
					),
					'max' => array(
						'description' => __( 'Maximum transaction amount.', 'storeseeder' ),
						'type'        => 'number',
						'minimum'     => 0.01,
						'default'     => 1000,
					),
				),
			),
			'transaction_types'        => array(
				// The three every gateway models. The admin and the MCP ability offered `payment`,
				// `adjustment`, `fee` and `commission`, none of which is a type any platform stores
				// — and `payment` is `charge` under another name.
				'description'       => __( 'Transaction types to draw from.', 'storeseeder' ),
				'type'              => 'array',
				'items'             => array(
					'type' => 'string',
					'enum' => Status::transaction_types(),
				),
				'default'           => array( 'charge', 'refund' ),
				'sanitize_callback' => array( $this, 'sanitize_array' ),
			),
			'customer_id'              => array(
				'description' => __( 'Only draw parent orders belonging to this customer.', 'storeseeder' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
			'order_status_filter'      => array(
				'description' => __( 'Only draw parent orders in these statuses.', 'storeseeder' ),
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
					'enum' => Status::order_statuses(),
				),
				'default'     => array(),
			),
			'include_refunds'          => array(
				'description' => __( 'Include refund transactions. The older form of leaving `refund` out of transaction_types; both work.', 'storeseeder' ),
				'type'        => 'boolean',
				'default'     => true,
			),
			'refund_percentage'        => array(
				'description' => __( 'Percentage of transactions that should be refunds.', 'storeseeder' ),
				'type'        => 'number',
				'minimum'     => 0,
				'maximum'     => 50,
				'default'     => 5,
			),
			'include_gateway_metadata' => array(
				'description' => __( 'Include payment gateway-specific metadata.', 'storeseeder' ),
				'type'        => 'boolean',
				'default'     => true,
			),
			'associate_with_orders'    => array(
				'description' => __( 'Associate transactions with existing orders.', 'storeseeder' ),
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
			'transactions' => array(
				'description' => __( 'Generated transactions with payment details and metadata.', 'storeseeder' ),
				'type'        => 'array',
				'context'     => array( 'view' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'             => array(
							'type' => 'integer',
						),
						'order_id'       => array(
							'type' => 'integer',
						),
						'amount'         => array(
							'type' => 'string',
						),
						'currency'       => array(
							'type' => 'string',
						),
						'payment_method' => array(
							'type' => 'string',
						),
						'status'         => array(
							'type' => 'string',
						),
						'transaction_id' => array(
							'type' => 'string',
						),
					),
				),
			),
		);
	}
}
