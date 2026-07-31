<?php
/**
 * MCP Ability: Generate Subscriptions
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.4.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Subscriptions
 *
 * Maps to REST endpoint: POST /storeseeder/v1/subscriptions/generate
 *
 * @since 1.0.0
 */
class Generate_Subscriptions extends Ability {

	const REST_BASE = 'subscriptions';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Subscriptions', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate subscription records against existing orders for testing Fluent Cart recurring-billing views. The subscriptions table ships in core, but active billing requires Fluent Cart Pro. Requires existing orders and products.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'subscriptions',
			'description' => __( 'Array of generated subscription objects with id, uuid, customer_id, item_name, billing_interval, status, and recurring_total.', 'storeseeder' ),
		);
	}
}
