<?php
/**
 * MCP Ability: Generate Product Downloads
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.4.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\Abstracts\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Product_Downloads
 *
 * Maps to REST endpoint: POST /storeseeder/v1/product_downloads/generate
 *
 * @since 1.0.0
 */
class Generate_Product_Downloads extends Ability {

	const REST_BASE = 'product_downloads';
}
