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
}
