<?php
/**
 * MCP Ability: Generate Product Downloads
 *
 * @package FluentCartFakerPress\MCP\Abilities
 * @since   2.4.0
 */

namespace FluentCartFakerPress\MCP\Abilities;

use FluentCartFakerPress\Abstracts\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Product_Downloads
 *
 * Maps to REST endpoint: POST /fluent-cart-fakerpress/v1/product_downloads/generate
 *
 * @since 2.4.0
 */
class Generate_Product_Downloads extends Ability {

	const REST_BASE = 'product_downloads';
}
