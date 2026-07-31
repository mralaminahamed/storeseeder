<?php
/**
 * MCP Ability: Generate Orders
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Orders
 *
 * Maps to REST endpoint: POST /storeseeder/v1/orders/generate
 *
 * @since 1.0.0
 */
class Generate_Orders extends Ability {

	const REST_BASE = 'orders';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Orders', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate realistic Fluent Cart orders with line items, addresses, payment details, shipping calculations, tax breakdowns, and fulfilment status. Requires at least one product with variations and one customer to exist.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function input_properties(): array {
		return array(
			'order_status'         => array(
				'type'        => 'string',
				'description' => __( 'Status of generated orders. Allowed: pending, processing, completed, cancelled, on_hold, refunded, mixed. Default: mixed.', 'storeseeder' ),
				'enum'        => array( 'pending', 'processing', 'completed', 'cancelled', 'on_hold', 'refunded', 'mixed' ),
				'default'     => 'mixed',
			),
			'customer_type'        => array(
				'type'        => 'string',
				'description' => __( 'Whose customer accounts to attach. Allowed: existing, new, mixed, specific. Default: mixed.', 'storeseeder' ),
				'enum'        => array( 'existing', 'new', 'mixed', 'specific' ),
				'default'     => 'mixed',
			),
			'specific_customer_id' => array(
				'type'        => 'integer',
				'description' => __( 'WordPress user ID to attach all orders to. Only used when customer_type is "specific".', 'storeseeder' ),
				'minimum'     => 1,
			),
			'min_total'            => array(
				'type'        => 'number',
				'description' => __( 'Minimum order total (USD). Default: 10.', 'storeseeder' ),
				'default'     => 10,
			),
			'max_total'            => array(
				'type'        => 'number',
				'description' => __( 'Maximum order total (USD). Default: 1000.', 'storeseeder' ),
				'default'     => 1000,
			),
			'min_items'            => array(
				'type'        => 'integer',
				'description' => __( 'Minimum line items per order. Default: 1.', 'storeseeder' ),
				'minimum'     => 1,
				'default'     => 1,
			),
			'max_items'            => array(
				'type'        => 'integer',
				'description' => __( 'Maximum line items per order (1–20). Default: 5.', 'storeseeder' ),
				'minimum'     => 1,
				'maximum'     => 20,
				'default'     => 5,
			),
			'payment_methods'      => array(
				'type'        => 'array',
				'description' => __( 'Payment methods to distribute across orders. Allowed: stripe, paypal, bank_transfer, cash_on_delivery, credit_card. Default: ["stripe","paypal","bank_transfer"].', 'storeseeder' ),
				'items'       => array( 'type' => 'string' ),
				'default'     => array( 'stripe', 'paypal', 'bank_transfer' ),
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'orders',
			'description' => __( 'Array of generated order objects with id, order_number, status, total, payment_method, and item count.', 'storeseeder' ),
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

		if ( isset( $input['order_status'] ) ) {
			$payload['order_status'] = $input['order_status'];
		}

		if ( isset( $input['customer_type'] ) ) {
			$payload['customer_type'] = $input['customer_type'];
		}

		if ( isset( $input['specific_customer_id'] ) ) {
			$payload['specific_customer_id'] = (int) $input['specific_customer_id'];
		}

		$payload['order_value'] = array(
			'min_total' => $input['min_total'] ?? 10,
			'max_total' => $input['max_total'] ?? 1000,
		);

		$payload['items_per_order'] = array(
			'min' => $input['min_items'] ?? 1,
			'max' => $input['max_items'] ?? 5,
		);

		if ( isset( $input['payment_methods'] ) ) {
			$payload['payment_methods'] = (array) $input['payment_methods'];
		}

		if ( isset( $input['customer_distribution'] ) ) {
			$payload['customer_distribution'] = $input['customer_distribution'];
		}

		if ( isset( $input['geographical_distribution'] ) ) {
			$payload['geographical_distribution'] = $input['geographical_distribution'];
		}

		return $payload;
	}
}
