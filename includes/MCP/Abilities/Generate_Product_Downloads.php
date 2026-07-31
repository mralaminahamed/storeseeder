<?php
/**
 * MCP Ability: Generate Product Downloads
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.4.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

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

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Product Downloads', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate downloadable files for products and grant download permissions on existing orders, for testing Fluent Cart digital fulfillment. Requires existing products.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'product_downloads',
			'description' => __( 'Array of generated product download objects with id, post_id, title, download_identifier, and permission_granted.', 'storeseeder' ),
		);
	}
}
