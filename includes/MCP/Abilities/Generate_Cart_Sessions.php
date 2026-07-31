<?php
/**
 * MCP Ability: Generate Cart Sessions
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Cart_Sessions
 *
 * Maps to REST endpoint: POST /storeseeder/v1/cart-sessions/generate
 *
 * @since 1.0.0
 */
class Generate_Cart_Sessions extends Ability {

	const REST_BASE = 'cart-sessions';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Cart Sessions', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate realistic shopping cart sessions including pending, abandoned, completed, and cancelled states. Useful for testing abandoned-cart recovery workflows and marketing-automation integrations.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function input_properties(): array {
		return array(
			'customer_type'        => array(
				'type'        => 'string',
				'description' => __( 'Cart owner type. Allowed: existing, new, mixed, specific, guest_only. Default: mixed.', 'storeseeder' ),
				'enum'        => array( 'existing', 'new', 'mixed', 'specific', 'guest_only' ),
				'default'     => 'mixed',
			),
			'specific_customer_id' => array(
				'type'        => 'integer',
				'description' => __( 'Attach all carts to this customer ID. Requires customer_type="specific".', 'storeseeder' ),
				'minimum'     => 1,
			),
			'guest_cart_ratio'     => array(
				'type'        => 'integer',
				'description' => __( 'Percentage of guest (unauthenticated) carts when customer_type is "mixed" (0–100). Default: 40.', 'storeseeder' ),
				'minimum'     => 0,
				'maximum'     => 100,
				'default'     => 40,
			),
			'abandonment_rate'     => array(
				'type'        => 'integer',
				'description' => __( 'Percentage of carts with "abandoned" status (0–100). Default: 30.', 'storeseeder' ),
				'minimum'     => 0,
				'maximum'     => 100,
				'default'     => 30,
			),
			'cart_value_min'       => array(
				'type'        => 'number',
				'description' => __( 'Minimum cart value (USD). Default: 5.', 'storeseeder' ),
				'minimum'     => 0,
				'default'     => 5,
			),
			'cart_value_max'       => array(
				'type'        => 'number',
				'description' => __( 'Maximum cart value (USD). Default: 500.', 'storeseeder' ),
				'minimum'     => 1,
				'default'     => 500,
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'cart_sessions',
			'description' => __( 'Array of generated cart session objects with hash, user_id, status, items_count, total_amount, customer details, and timestamps.', 'storeseeder' ),
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

		if ( isset( $input['guest_cart_ratio'] ) ) {
			$payload['guest_cart_ratio'] = (int) $input['guest_cart_ratio'];
		}

		if ( isset( $input['abandonment_rate'] ) ) {
			$payload['abandonment_rate'] = (int) $input['abandonment_rate'];
		}

		$payload['cart_value_range'] = array(
			'min' => $input['cart_value_min'] ?? 5,
			'max' => $input['cart_value_max'] ?? 500,
		);

		return $payload;
	}
}
