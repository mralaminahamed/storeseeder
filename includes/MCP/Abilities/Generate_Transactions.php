<?php
/**
 * MCP Ability: Generate Transactions
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Transactions
 *
 * Maps to REST endpoint: POST /storeseeder/v1/transactions/generate
 *
 * @since 1.0.0
 */
class Generate_Transactions extends Ability {

	const REST_BASE = 'transactions';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Transactions', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate realistic payment transaction records linked to existing orders. Creates transactions with gateway-specific IDs, amounts, statuses, and currency assignments. Requires orders to exist.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function input_properties(): array {
		return array(
			'customer_type'        => array(
				'type'        => 'string',
				'description' => __( 'Filter orders by customer type. Allowed: all, specific, existing_customers_only, new_customers_only. Default: all.', 'storeseeder' ),
				'enum'        => array( 'all', 'specific', 'existing_customers_only', 'new_customers_only' ),
				'default'     => 'all',
			),
			'specific_customer_id' => array(
				'type'        => 'integer',
				'description' => __( 'Only create transactions for orders belonging to this customer ID. Requires customer_type="specific".', 'storeseeder' ),
				'minimum'     => 1,
			),
			'transaction_types'    => array(
				'type'        => 'array',
				'description' => __( 'Transaction types to generate. Allowed: payment, refund, adjustment, fee, commission. Default: ["payment","refund"].', 'storeseeder' ),
				'items'       => array( 'type' => 'string' ),
				'default'     => array( 'payment', 'refund' ),
			),
			'payment_gateways'     => array(
				'type'        => 'array',
				'description' => __( 'Payment gateways to use. Allowed: stripe, paypal, square, authorize_net, braintree, razorpay, mollie. Default: ["stripe","paypal","square"].', 'storeseeder' ),
				'items'       => array( 'type' => 'string' ),
				'default'     => array( 'stripe', 'paypal', 'square' ),
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'transactions',
			'description' => __( 'Array of generated transaction objects with id, order_id, transaction_id, payment_gateway, amount, currency, status, and type.', 'storeseeder' ),
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $input Validated input from the MCP client.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute( array $input = array() ) {
		return static::dispatch( static::build_payload( $input ) );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $input Raw MCP input.
	 * @return array<string, mixed>
	 */
	protected static function build_payload( array $input ): array {
		$payload = array(
			'count'  => $input['count'] ?? 5,
			'locale' => $input['locale'] ?? 'en_US',
		);

		if ( isset( $input['seed'] ) ) {
			$payload['seed'] = (int) $input['seed'];
		}

		if ( isset( $input['customer_type'] ) ) {
			$payload['customer_type'] = $input['customer_type'];
		}

		if ( isset( $input['specific_customer_id'] ) ) {
			$payload['specific_customer_id'] = (int) $input['specific_customer_id'];
		}

		if ( isset( $input['transaction_types'] ) ) {
			$payload['transaction_types'] = (array) $input['transaction_types'];
		}

		if ( isset( $input['payment_gateways'] ) ) {
			$payload['payment_gateways'] = (array) $input['payment_gateways'];
		}

		return $payload;
	}
}
