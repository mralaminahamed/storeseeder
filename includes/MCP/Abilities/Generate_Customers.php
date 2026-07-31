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
				'description' => __( 'Customer segments to mix. Allowed: regular, vip, wholesale, guest, returning. A guest holds no account, a wholesale buyer always carries a company, a returning customer has definitely bought something. Default: ["regular","returning"].', 'storeseeder' ),
				'items'       => array(
					'type' => 'string',
					'enum' => array( 'regular', 'vip', 'wholesale', 'guest', 'returning' ),
				),
				'default'     => array( 'regular', 'returning' ),
			),
			'countries'                 => array(
				'type'        => 'array',
				'description' => __( 'Two-letter country codes to draw addresses from. Default: the sample data\'s own spread.', 'storeseeder' ),
				'items'       => array( 'type' => 'string' ),
			),
			'age_groups'                => array(
				'type'        => 'array',
				'description' => __( 'Age groups to draw birth dates from. Allowed: 18-25, 26-35, 36-45, 46-55, 56-65, 65+. A third of customers give no birth date at all.', 'storeseeder' ),
				'items'       => array(
					'type' => 'string',
					'enum' => array( '18-25', '26-35', '36-45', '46-55', '56-65', '65+' ),
				),
			),
			'include_shipping'          => array(
				'type'        => 'boolean',
				'description' => __( 'Generate a shipping address. False leaves it the same as billing. Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'different_addresses_ratio' => array(
				'type'        => 'integer',
				'description' => __( 'Percentage of customers whose shipping address differs from billing (0–100). Default: 30.', 'storeseeder' ),
				'minimum'     => 0,
				'maximum'     => 100,
				'default'     => 30,
			),
			'phone_numbers'             => array(
				'type'        => 'boolean',
				'description' => __( 'Include phone numbers. Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'marketing_opt_in_ratio'    => array(
				'type'        => 'integer',
				'description' => __( 'Percentage of customers opted into marketing (0–100). Default: 60.', 'storeseeder' ),
				'minimum'     => 0,
				'maximum'     => 100,
				'default'     => 60,
			),
			'simulate_purchase_history' => array(
				'type'        => 'boolean',
				'description' => __( 'Populate lifetime totals, loyalty tier and purchase dates. This is metadata, not orders — use generate-orders for those. Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'loyalty_tier_focus'        => array(
				'type'        => 'array',
				'description' => __( 'Loyalty tiers to draw from. Where set, the tier is chosen from this list rather than derived from spend. Allowed: bronze, silver, gold, platinum.', 'storeseeder' ),
				'items'       => array(
					'type' => 'string',
					'enum' => array( 'bronze', 'silver', 'gold', 'platinum' ),
				),
			),
			'account_status'            => array(
				'type'        => 'string',
				'description' => __( 'Account status for generated customers. `mixed` spreads across all three. Default: mixed.', 'storeseeder' ),
				'enum'        => array( 'active', 'inactive', 'pending', 'mixed' ),
				'default'     => 'mixed',
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

		foreach ( array( 'customer_types', 'loyalty_tier_focus' ) as $list ) {
			if ( isset( $input[ $list ] ) ) {
				$payload[ $list ] = (array) $input[ $list ];
			}
		}

		// Flattened for the client, nested for the generator: `countries` and `age_groups` are what
		// a caller thinks in, and the nesting is an artefact of the admin form's grouping.
		if ( isset( $input['countries'] ) ) {
			$payload['country_focus'] = (array) $input['countries'];
		}

		if ( isset( $input['age_groups'] ) ) {
			$payload['demographics'] = array( 'age_groups' => (array) $input['age_groups'] );
		}

		// `include_billing` is gone: every platform requires a billing address on a customer, so
		// switching it off could only produce a record nothing could use.
		$payload['address_preferences'] = array(
			'include_shipping'          => $input['include_shipping'] ?? true,
			'different_addresses_ratio' => $input['different_addresses_ratio'] ?? 30,
		);

		$payload['contact_preferences'] = array(
			'phone_numbers'          => $input['phone_numbers'] ?? true,
			'marketing_opt_in_ratio' => $input['marketing_opt_in_ratio'] ?? 60,
		);

		$payload['include_history'] = $input['simulate_purchase_history'] ?? true;

		if ( isset( $input['account_status'] ) ) {
			$payload['account_status'] = (string) $input['account_status'];
		}

		return $payload;
	}
}
