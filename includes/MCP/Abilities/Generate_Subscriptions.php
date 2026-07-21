<?php
/**
 * MCP Ability: Generate Subscriptions
 *
 * @package FluentCartFakerPress\MCP\Abilities
 * @since   2.4.0
 */

namespace FluentCartFakerPress\MCP\Abilities;

use FluentCartFakerPress\Abstracts\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Subscriptions
 *
 * Maps to REST endpoint: POST /fluent-cart-fakerpress/v1/subscriptions/generate
 *
 * @since 1.0.0
 */
class Generate_Subscriptions extends Ability {

	const REST_BASE = 'subscriptions';
}
