<?php
/**
 * MCP Ability: Generate Shipping Classes
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.4.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Shipping_Classes
 *
 * Maps to REST endpoint: POST /storeseeder/v1/shipping_classes/generate
 *
 * @since 1.0.0
 */
class Generate_Shipping_Classes extends Ability {

	const REST_BASE = 'shipping_classes';
}
