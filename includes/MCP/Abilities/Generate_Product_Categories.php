<?php
/**
 * MCP Ability: Generate Product Categories
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   1.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Product_Categories
 *
 * Maps to REST endpoint: POST /storeseeder/v1/product_categories/generate
 *
 * @since 1.1.0
 */
class Generate_Product_Categories extends Ability {

	const REST_BASE = 'product_categories';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Product Categories', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate product categories, nested where asked, and file existing products under them — for testing category archives, breadcrumbs and filtered queries. The store must already have products for them to hold.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'product_categories',
			'description' => __( 'Array of generated category objects with id, name, slug, how many products were filed under it, and its parent category when it is nested.', 'storeseeder' ),
		);
	}
}
