<?php
/**
 * MCP Ability: Generate Shipping Classes
 *
 * @package FluentCartFakerPress\MCP\Abilities
 * @since   2.4.0
 */

namespace FluentCartFakerPress\MCP\Abilities;

use FluentCartFakerPress\Abstracts\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Shipping_Classes
 *
 * Maps to REST endpoint: POST /fluent-cart-fakerpress/v1/shipping_classes/generate
 *
 * @since 2.4.0
 */
class Generate_Shipping_Classes extends Ability {

	const REST_BASE = 'shipping_classes';
}
