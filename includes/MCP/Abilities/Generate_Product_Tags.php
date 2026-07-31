<?php
/**
 * MCP Ability: Generate Product Tags
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   1.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Product_Tags
 *
 * Maps to REST endpoint: POST /storeseeder/v1/product_tags/generate
 *
 * @since 1.1.0
 */
class Generate_Product_Tags extends Ability {

	const REST_BASE = 'product_tags';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Product Tags', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate product tags and apply them to existing products, for testing tag archives, related products and faceted search. WooCommerce and EasyCommerce have tags; Fluent Cart does not, and reports the resource unsupported rather than inventing somewhere to put them.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'product_tags',
			'description' => __( 'Array of generated tag objects with id, name, slug, and how many products it was applied to.', 'storeseeder' ),
		);
	}
}
