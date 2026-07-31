<?php
/**
 * MCP Ability: Generate Licenses
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   1.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Licenses
 *
 * Maps to REST endpoint: POST /storeseeder/v1/licenses/generate
 *
 * @since 1.1.0
 */
class Generate_Licenses extends Ability {

	const REST_BASE = 'licenses';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Licenses', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate software licences against existing orders — keys, site limits, activation counts and expiry dates, including some already at their activation limit and some expired. On Fluent Cart this requires Fluent Cart Pro, which owns the licensing tables; the request is refused with that reason when it is not active. Requires existing orders and products.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'licenses',
			'description' => __( 'Array of generated licence objects with id, license_key, status, order_id, customer_id, site_limit, activation_count and expiration_date.', 'storeseeder' ),
		);
	}
}
