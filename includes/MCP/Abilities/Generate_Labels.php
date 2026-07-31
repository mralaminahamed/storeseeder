<?php
/**
 * MCP Ability: Generate Labels
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.4.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Labels
 *
 * Maps to REST endpoint: POST /storeseeder/v1/labels/generate
 *
 * @since 1.0.0
 */
class Generate_Labels extends Ability {

	const REST_BASE = 'labels';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Labels', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate labels (tags) and attach them to existing orders and customers for testing Fluent Cart segmentation. Requires existing orders or customers to attach to.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'labels',
			'description' => __( 'Array of generated label objects with id, value, and attached count.', 'storeseeder' ),
		);
	}
}
