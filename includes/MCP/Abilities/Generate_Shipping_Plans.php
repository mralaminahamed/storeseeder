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
		return __( 'Generate shipping methods with their zones: a service level, a cost, the countries the zone covers and a delivery estimate. Tiered weight- and quantity-based rates are not generated — neither platform has one in core.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function input_properties(): array {
		return array(
			'shipping_types'    => array(
				'type'        => 'array',
				'description' => __( 'Service levels to generate. Allowed: standard, express, overnight, free. A flat rate underneath, named and timed for the service — neither platform has an "express" method type. Default: ["standard","express","free"].', 'storeseeder' ),
				'items'       => array(
					'type' => 'string',
					'enum' => array( 'standard', 'express', 'overnight', 'free', 'flat_rate' ),
				),
				'default'     => array( 'standard', 'express', 'free' ),
			),
			'cost_min'          => array(
				'type'        => 'number',
				'description' => __( 'Minimum shipping cost (USD). Default: 0.', 'storeseeder' ),
				'minimum'     => 0,
				'default'     => 0,
			),
			'cost_max'          => array(
				'type'        => 'number',
				'description' => __( 'Maximum shipping cost (USD). Default: 50.', 'storeseeder' ),
				'minimum'     => 0,
				'default'     => 50,
			),
			'coverage_areas'    => array(
				'type'        => 'array',
				'description' => __( 'Which countries a zone covers. Allowed: domestic, international, regional, worldwide. Domestic means wherever the store sells from. Default: ["domestic","international"].', 'storeseeder' ),
				'items'       => array(
					'type' => 'string',
					'enum' => array( 'domestic', 'international', 'regional', 'worldwide' ),
				),
				'default'     => array( 'domestic', 'international' ),
			),
			'delivery_min_days' => array(
				'type'        => 'integer',
				'description' => __( 'Lower bound for the delivery estimate, in days. The service level narrows it. Default: 1.', 'storeseeder' ),
				'minimum'     => 0,
				'default'     => 1,
			),
			'delivery_max_days' => array(
				'type'        => 'integer',
				'description' => __( 'Upper bound for the delivery estimate, in days. Default: 14.', 'storeseeder' ),
				'minimum'     => 0,
				'default'     => 14,
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

		$payload['delivery_timeframes'] = array(
			'min_days' => $input['delivery_min_days'] ?? 1,
			'max_days' => $input['delivery_max_days'] ?? 14,
		);

		if ( isset( $input['coverage_areas'] ) ) {
			$payload['coverage_areas'] = (array) $input['coverage_areas'];
		}

		return $payload;
	}
}
