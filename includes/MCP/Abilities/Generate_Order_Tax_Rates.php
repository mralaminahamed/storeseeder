<?php
/**
 * MCP Ability: Generate Order Tax Rates
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.4.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Order_Tax_Rates
 *
 * Maps to REST endpoint: POST /storeseeder/v1/order_tax_rates/generate
 *
 * @since 1.0.0
 */
class Generate_Order_Tax_Rates extends Ability {

	const REST_BASE = 'order_tax_rates';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Order Tax Lines', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate per-order tax lines linking orders to tax rates with the tax amounts collected. Requires existing orders and tax rates.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'order_tax_rates',
			'description' => __( 'Array of generated order tax line objects with id, order_id, tax_rate_id, and total_tax.', 'storeseeder' ),
		);
	}
}
