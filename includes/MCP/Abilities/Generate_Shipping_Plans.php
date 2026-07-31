<?php
/**
 * MCP Ability: Generate Shipping Plans
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Shipping_Plans
 *
 * Maps to REST endpoint: POST /storeseeder/v1/shipping-plans/generate
 *
 * @since 1.0.0
 */
class Generate_Shipping_Plans extends Ability {

	const REST_BASE = 'shipping-plans';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Shipping Plans', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate realistic shipping plans with tiered pricing methods (price-based, weight-based, quantity-based), regional coverage, delivery timeframes, and taxability settings.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function input_properties(): array {
		return array(
			'shipping_types' => array(
				'type'        => 'array',
				'description' => __( 'Shipping method types. Allowed: standard, express, overnight, pickup, free, weight_based, flat_rate. Default: ["standard","express","free"].', 'storeseeder' ),
				'items'       => array( 'type' => 'string' ),
				'default'     => array( 'standard', 'express', 'free' ),
			),
			'cost_min'       => array(
				'type'        => 'number',
				'description' => __( 'Minimum shipping cost (USD). Default: 0.', 'storeseeder' ),
				'minimum'     => 0,
				'default'     => 0,
			),
			'cost_max'       => array(
				'type'        => 'number',
				'description' => __( 'Maximum shipping cost (USD). Default: 50.', 'storeseeder' ),
				'minimum'     => 0,
				'default'     => 50,
			),
			'coverage_areas' => array(
				'type'        => 'array',
				'description' => __( 'Geographic coverage. Allowed: domestic, international, regional, worldwide. Default: ["domestic","international"].', 'storeseeder' ),
				'items'       => array( 'type' => 'string' ),
				'default'     => array( 'domestic', 'international' ),
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'shipping_plans',
			'description' => __( 'Array of generated shipping plan objects with id, name, active status, calculation_base, methods count, and regions count.', 'storeseeder' ),
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $input Validated input from the MCP client.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function execute( array $input = array() ) {
		return static::dispatch( static::build_payload( $input ) );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $input Raw MCP input.
	 * @return array<string, mixed>
	 */
	protected static function build_payload( array $input ): array {
		$payload = array(
			'count'  => $input['count'] ?? 5,
			'locale' => $input['locale'] ?? 'en_US',
		);

		if ( isset( $input['seed'] ) ) {
			$payload['seed'] = (int) $input['seed'];
		}

		if ( isset( $input['shipping_types'] ) ) {
			$payload['shipping_types'] = (array) $input['shipping_types'];
		}

		$payload['cost_range'] = array(
			'min' => $input['cost_min'] ?? 0,
			'max' => $input['cost_max'] ?? 50,
		);

		if ( isset( $input['coverage_areas'] ) ) {
			$payload['coverage_areas'] = (array) $input['coverage_areas'];
		}

		return $payload;
	}
}
