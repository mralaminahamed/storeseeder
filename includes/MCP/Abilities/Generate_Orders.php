<?php
/**
 * MCP Ability: Generate Orders
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;
use StoreSeeder\Platforms\Status;

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
		return __( 'Generate realistic orders with line items priced from the catalogue, billing and shipping addresses, payment details, shipping and tax, a shopper note and the dates a paid or completed order carries. Requires at least one product to exist, and one customer unless guest orders are asked for.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function input_properties(): array {
		return array(
			'order_status'     => array(
				'type'        => 'array',
				'description' => __( 'Statuses to draw from. Allowed: pending, processing, on_hold, completed, cancelled, refunded, failed. Default: a spread across all of them.', 'storeseeder' ),
				'items'       => array(
					'type' => 'string',
					'enum' => Status::order_statuses(),
				),
			),
			'items_per_order'  => array(
				'type'        => 'object',
				'description' => __( 'Line items per order, as a min and max. Default: 1 to 3.', 'storeseeder' ),
				'properties'  => array(
					'min' => array( 'type' => 'integer' ),
					'max' => array( 'type' => 'integer' ),
				),
			),
			'payment_methods'  => array(
				'type'        => 'array',
				'description' => __( 'Payment methods to distribute across orders. Default: ["stripe","paypal","cod"].', 'storeseeder' ),
				'items'       => array( 'type' => 'string' ),
			),
			'countries'        => array(
				'type'        => 'array',
				'description' => __( 'Two-letter country codes to draw order addresses from. Default: US. A non-US address carries no state, since a US abbreviation on a French address is what makes generated data obviously fake.', 'storeseeder' ),
				'items'       => array( 'type' => 'string' ),
			),
			'include_customer' => array(
				'type'        => 'boolean',
				'description' => __( 'Attach a customer account. False generates guest orders, which every store takes and no fixture had. Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'customer_id'      => array(
				'type'        => 'integer',
				'description' => __( 'Attach every order to this customer, for giving one account an order history. Ignored when include_customer is false.', 'storeseeder' ),
				'minimum'     => 1,
			),
			'include_shipping' => array(
				'type'        => 'boolean',
				'description' => __( 'Charge shipping. False generates orders with no shipping line, which is what a download-only store looks like. Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'include_tax'      => array(
				'type'        => 'boolean',
				'description' => __( 'Apply tax. Default: true.', 'storeseeder' ),
				'default'     => true,
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
			$payload['order_status'] = (array) $input['order_status'];
		}

		if ( isset( $input['items_per_order'] ) ) {
			$payload['items_per_order'] = (array) $input['items_per_order'];
		}

		if ( isset( $input['payment_methods'] ) ) {
			$payload['payment_methods'] = (array) $input['payment_methods'];
		}

		// Flattened for the client, nested for the generator: an MCP client writes a list of
		// countries and should not have to know the shape the admin form happens to send.
		if ( isset( $input['countries'] ) ) {
			$payload['geographical_distribution'] = array( 'countries' => (array) $input['countries'] );
		}

		foreach ( array( 'include_customer', 'include_shipping', 'include_tax' ) as $flag ) {
			if ( isset( $input[ $flag ] ) ) {
				$payload[ $flag ] = (bool) $input[ $flag ];
			}
		}

		if ( isset( $input['customer_id'] ) ) {
			$payload['customer_id'] = (int) $input['customer_id'];
		}

		return $payload;
	}
}
