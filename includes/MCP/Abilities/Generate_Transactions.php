<?php
/**
 * MCP Ability: Generate Transactions
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;
use StoreSeeder\Platforms\Status;

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
			'transaction_types'    => array(
				'type'        => 'array',
				'description' => __( 'Transaction types to draw from. Allowed: charge, refund, dispute — the three every gateway models. Default: ["charge","refund"].', 'storeseeder' ),
				'items'       => array(
					'type' => 'string',
					'enum' => Status::transaction_types(),
				),
				'default'     => array( 'charge', 'refund' ),
			),
			'transaction_statuses' => array(
				'type'        => 'array',
				'description' => __( 'Statuses to draw charges from. Allowed: pending, authorized, completed, failed. A refund is always refunded and a dispute always disputed, so those two are set by the type. Default: ["completed","pending","failed"].', 'storeseeder' ),
				'items'       => array(
					'type' => 'string',
					'enum' => Status::transaction_statuses(),
				),
				'default'     => array( 'completed', 'pending', 'failed' ),
			),
			'payment_methods'      => array(
				'type'        => 'array',
				'description' => __( 'Payment methods to distribute across transactions. Default: ["stripe","paypal","bank_transfer","cod"].', 'storeseeder' ),
				'items'       => array( 'type' => 'string' ),
			),
			'amount_range'         => array(
				'type'        => 'object',
				'description' => __( 'Transaction amount range in major units. Default: 10 to 1000.', 'storeseeder' ),
				'properties'  => array(
					'min' => array( 'type' => 'number' ),
					'max' => array( 'type' => 'number' ),
				),
			),
			'refund_percentage'    => array(
				'type'        => 'number',
				'description' => __( 'Share of transactions that are refunds, where refund is one of the types. Default: 5.', 'storeseeder' ),
				'minimum'     => 0,
				'maximum'     => 100,
				'default'     => 5,
			),
			'gateway_metadata'     => array(
				'type'        => 'boolean',
				'description' => __( 'Include gateway detail — payer email, card brand and last four. Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'customer_id'          => array(
				'type'        => 'integer',
				'description' => __( 'Only draw parent orders belonging to this customer.', 'storeseeder' ),
				'minimum'     => 1,
			),
			'order_statuses'       => array(
				'type'        => 'array',
				'description' => __( 'Only draw parent orders in these statuses. Omit for any.', 'storeseeder' ),
				'items'       => array(
					'type' => 'string',
					'enum' => Status::order_statuses(),
				),
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

		foreach ( array( 'transaction_types', 'transaction_statuses', 'payment_methods', 'amount_range' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$payload[ $key ] = (array) $input[ $key ];
			}
		}

		if ( isset( $input['refund_percentage'] ) ) {
			$payload['refund_percentage'] = (float) $input['refund_percentage'];
		}

		if ( isset( $input['gateway_metadata'] ) ) {
			$payload['include_gateway_metadata'] = (bool) $input['gateway_metadata'];
		}

		if ( isset( $input['customer_id'] ) ) {
			$payload['customer_id'] = (int) $input['customer_id'];
		}

		// One name for the writer, whichever surface asked: `customer_type` and
		// `specific_customer_id` described a new-versus-existing split no writer implemented, the
		// same pair the Orders ability dropped.
		if ( isset( $input['order_statuses'] ) ) {
			$payload['order_status_filter'] = (array) $input['order_statuses'];
		}

		return $payload;
	}
}
