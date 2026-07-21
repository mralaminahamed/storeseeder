<?php
/**
 * MCP Ability: Generate Labels
 *
 * @package FluentCartFakerPress\MCP\Abilities
 * @since   2.4.0
 */

namespace FluentCartFakerPress\MCP\Abilities;

use FluentCartFakerPress\Abstracts\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Labels
 *
 * Maps to REST endpoint: POST /fluent-cart-fakerpress/v1/labels/generate
 *
 * @since 1.0.0
 */
class Generate_Labels extends Ability {

	const REST_BASE = 'labels';
}
