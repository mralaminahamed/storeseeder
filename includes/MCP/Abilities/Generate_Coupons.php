<?php
/**
 * MCP Ability: Generate Coupons
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Coupons
 *
 * Maps to REST endpoint: POST /storeseeder/v1/coupons/generate
 *
 * @since 1.0.0
 */
class Generate_Coupons extends Ability {

	const REST_BASE = 'coupons';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Coupons', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate realistic Fluent Cart discount coupons with various discount types, usage limits, validity periods, and product or customer restrictions.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function input_properties(): array {
		return array(
			'discount_types'    => array(
				'type'        => 'array',
				'description' => __( 'Discount types to include. Allowed: percentage, fixed, free_shipping. Default: ["percentage","fixed"]. A free-shipping coupon carries no amount.', 'storeseeder' ),
				'items'       => array(
					'type' => 'string',
					'enum' => array( 'percentage', 'fixed', 'free_shipping' ),
				),
				'default'     => array( 'percentage', 'fixed' ),
			),
			'min_percentage'    => array(
				'type'        => 'integer',
				'description' => __( 'Minimum percentage discount (5–95). Default: 10.', 'storeseeder' ),
				'minimum'     => 5,
				'maximum'     => 95,
				'default'     => 10,
			),
			'max_percentage'    => array(
				'type'        => 'integer',
				'description' => __( 'Maximum percentage discount (5–95). Default: 50.', 'storeseeder' ),
				'minimum'     => 5,
				'maximum'     => 95,
				'default'     => 50,
			),
			'min_fixed'         => array(
				'type'        => 'number',
				'description' => __( 'Minimum fixed discount amount (USD). Default: 5.', 'storeseeder' ),
				'default'     => 5,
			),
			'max_fixed'         => array(
				'type'        => 'number',
				'description' => __( 'Maximum fixed discount amount (USD). Default: 100.', 'storeseeder' ),
				'default'     => 100,
			),
			'set_usage_limits'  => array(
				'type'        => 'boolean',
				'description' => __( 'Add usage-limit rules to coupons. Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'max_uses'          => array(
				'type'        => 'integer',
				'description' => __( 'Maximum total uses per coupon when set_usage_limits is true (1–1000). Default: 100.', 'storeseeder' ),
				'minimum'     => 1,
				'maximum'     => 1000,
				'default'     => 100,
			),
			'max_uses_per_user' => array(
				'type'        => 'integer',
				'description' => __( 'Maximum uses per customer (1–10). Never exceeds max_uses, which Fluent Cart rejects outright. Default: 1.', 'storeseeder' ),
				'minimum'     => 1,
				'maximum'     => 10,
				'default'     => 1,
			),
			'restrictions'      => array(
				'type'        => 'object',
				'description' => __( 'Which restrictions to put on the generated coupons.', 'storeseeder' ),
				'properties'  => array(
					'minimum_spend'        => array(
						'type'        => 'boolean',
						'description' => __( 'Require a minimum cart subtotal. Default: true.', 'storeseeder' ),
					),
					'maximum_spend'        => array(
						'type'        => 'boolean',
						'description' => __( 'Cap the cart subtotal the coupon applies to. WooCommerce only. Default: false.', 'storeseeder' ),
					),
					'exclude_sale_items'   => array(
						'type'        => 'boolean',
						'description' => __( 'Refuse the coupon on discounted products. WooCommerce only. Default: false.', 'storeseeder' ),
					),
					'product_restrictions' => array(
						'type'        => 'boolean',
						'description' => __( 'Restrict some coupons to specific products. Default: true.', 'storeseeder' ),
					),
				),
			),
			'validity_min_days' => array(
				'type'        => 'integer',
				'description' => __( 'Minimum coupon validity period in days. Default: 7.', 'storeseeder' ),
				'minimum'     => 1,
				'default'     => 7,
			),
			'validity_max_days' => array(
				'type'        => 'integer',
				'description' => __( 'Maximum coupon validity period in days (1–365). Default: 90.', 'storeseeder' ),
				'minimum'     => 1,
				'maximum'     => 365,
				'default'     => 90,
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'coupons',
			'description' => __( 'Array of generated coupon objects with id, code, type, offer, status, usage_limit, and validity dates.', 'storeseeder' ),
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

		if ( isset( $input['discount_types'] ) ) {
			$payload['discount_types'] = (array) $input['discount_types'];
		}

		$payload['discount_range'] = array(
			'min_percentage' => $input['min_percentage'] ?? 10,
			'max_percentage' => $input['max_percentage'] ?? 50,
			'min_fixed'      => $input['min_fixed'] ?? 5,
			'max_fixed'      => $input['max_fixed'] ?? 100,
		);

		$payload['usage_limits'] = array(
			'set_usage_limits'  => $input['set_usage_limits'] ?? true,
			'max_uses'          => $input['max_uses'] ?? 100,
			'max_uses_per_user' => $input['max_uses_per_user'] ?? 1,
		);

		if ( isset( $input['restrictions'] ) ) {
			$payload['restrictions'] = (array) $input['restrictions'];
		}

		$payload['validity_period'] = array(
			'min_days' => $input['validity_min_days'] ?? 7,
			'max_days' => $input['validity_max_days'] ?? 90,
		);

		return $payload;
	}
}
