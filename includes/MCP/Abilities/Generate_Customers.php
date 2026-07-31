<?php
/**
 * MCP Ability: Generate Customers
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Customers
 *
 * Maps to REST endpoint: POST /storeseeder/v1/customers/generate
 *
 * @since 1.0.0
 */
class Generate_Customers extends Ability {

	const REST_BASE = 'customers';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Customers', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate realistic WordPress customer accounts with billing/shipping addresses, demographic metadata, purchase history, and loyalty tier assignments.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function input_properties(): array {
		return array(
			'customer_types'            => array(
				'type'        => 'array',
				'description' => __( 'Customer segment types to mix. Allowed values: regular, vip, wholesale, guest, returning. Default: ["regular","returning"].', 'storeseeder' ),
				'items'       => array( 'type' => 'string' ),
				'default'     => array( 'regular', 'returning' ),
			),
			'include_billing'           => array(
				'type'        => 'boolean',
				'description' => __( 'Generate billing addresses. Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'include_shipping'          => array(
				'type'        => 'boolean',
				'description' => __( 'Generate shipping addresses. Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'different_addresses_ratio' => array(
				'type'        => 'integer',
				'description' => __( 'Percentage of customers with a different shipping address (0–100). Default: 30.', 'storeseeder' ),
				'minimum'     => 0,
				'maximum'     => 100,
				'default'     => 30,
			),
			'simulate_purchase_history' => array(
				'type'        => 'boolean',
				'description' => __( 'Populate realistic purchase history metadata (order counts, spend totals, loyalty tier). Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'marketing_opt_in_ratio'    => array(
				'type'        => 'integer',
				'description' => __( 'Percentage of customers opted into marketing emails (0–100). Default: 65.', 'storeseeder' ),
				'minimum'     => 0,
				'maximum'     => 100,
				'default'     => 65,
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'customers',
			'description' => __( 'Array of generated customer objects with id, name, email, billing_country, loyalty_tier, total_orders, and total_spent.', 'storeseeder' ),
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

		if ( isset( $input['customer_types'] ) ) {
			$payload['customer_types'] = (array) $input['customer_types'];
		}

		$payload['address_preferences'] = array(
			'include_billing'           => $input['include_billing'] ?? true,
			'include_shipping'          => $input['include_shipping'] ?? true,
			'different_addresses_ratio' => $input['different_addresses_ratio'] ?? 30,
		);

		$payload['purchase_history'] = array(
			'simulate_history' => $input['simulate_purchase_history'] ?? true,
			'loyalty_tiers'    => $input['loyalty_tiers'] ?? true,
		);

		$payload['contact_preferences'] = array(
			'phone_numbers'          => true,
			'marketing_opt_in_ratio' => $input['marketing_opt_in_ratio'] ?? 65,
		);

		return $payload;
	}
}
