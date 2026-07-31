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

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Shipping Classes', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate shipping classes that group products with similar shipping requirements, each with a cost and per-item flag, for a Fluent Cart store.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'shipping_classes',
			'description' => __( 'Array of generated shipping class objects with id, name, cost, and per_item.', 'storeseeder' ),
		);
	}
}
