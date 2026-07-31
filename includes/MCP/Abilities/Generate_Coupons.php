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
				'description' => __( 'Discount types to include. Allowed: percentage, fixed, free_shipping, products. Default: ["percentage","fixed"].', 'storeseeder' ),
				'items'       => array( 'type' => 'string' ),
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
			'set_usage_limits' => $input['set_usage_limits'] ?? true,
			'max_uses'         => $input['max_uses'] ?? 100,
		);

		$payload['validity_period'] = array(
			'min_days' => $input['validity_min_days'] ?? 7,
			'max_days' => $input['validity_max_days'] ?? 90,
		);

		return $payload;
	}
}
