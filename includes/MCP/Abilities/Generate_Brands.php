<?php
/**
 * MCP Ability: Generate Brands
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   1.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Brands
 *
 * Maps to REST endpoint: POST /storeseeder/v1/brands/generate
 *
 * @since 1.1.0
 */
class Generate_Brands extends Ability {

	const REST_BASE = 'brands';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Product Brands', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate product brands and attach them to existing products, for testing brand archives, filters and product pages. Brands need a platform that has them: WooCommerce, EasyCommerce and Fluent Cart do, and the store must already have products to attach them to.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'brands',
			'description' => __( 'Array of generated brand objects with id, name, slug, how many products it was attached to, and its parent brand when it is a sub-brand.', 'storeseeder' ),
		);
	}
}
