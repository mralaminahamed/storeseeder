<?php
/**
 * MCP Ability: Generate Order Tax Rates
 *
 * @package StoreSeeder\MCP\Abilities\Resources
 * @since   2.4.0
 */

namespace StoreSeeder\MCP\Abilities\Resources;

use StoreSeeder\MCP\Abilities\Ability;

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
