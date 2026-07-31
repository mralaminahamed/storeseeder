<?php
/**
 * MCP Ability: Generate Product Variations
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Product_Variations
 *
 * Maps to REST endpoint: POST /storeseeder/v1/product-variations/generate
 *
 * @since 1.0.0
 */
class Generate_Product_Variations extends Ability {

	const REST_BASE = 'product-variations';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Product Variations', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate product variations (size/colour/storage combinations) for existing products. Creates variation records with unique SKUs, individual pricing, stock levels, and dimension metadata. Requires products to exist.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function input_properties(): array {
		return array(
			'specific_product_id' => array(
				'type'        => 'integer',
				'description' => __( 'Generate variations only for this product ID. Omit to pick a random eligible product.', 'storeseeder' ),
				'minimum'     => 1,
			),
			'exclude_product_ids' => array(
				'type'        => 'array',
				'description' => __( 'Array of product IDs to skip during generation.', 'storeseeder' ),
				'items'       => array( 'type' => 'integer' ),
				'default'     => array(),
			),
			'manage_stock'        => array(
				'type'        => 'boolean',
				'description' => __( 'Enable inventory tracking for variations. Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'stock_min'           => array(
				'type'        => 'integer',
				'description' => __( 'Minimum stock quantity per variation. Default: 0.', 'storeseeder' ),
				'minimum'     => 0,
				'default'     => 0,
			),
			'stock_max'           => array(
				'type'        => 'integer',
				'description' => __( 'Maximum stock quantity per variation. Default: 100.', 'storeseeder' ),
				'minimum'     => 1,
				'default'     => 100,
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'product_variations',
			'description' => __( 'Array of generated variation objects with id, product_id, name, sku, price, stock_quantity, type, status, and attributes.', 'storeseeder' ),
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

		if ( isset( $input['specific_product_id'] ) ) {
			$payload['specific_product_id'] = (int) $input['specific_product_id'];
		}

		if ( ! empty( $input['exclude_product_ids'] ) ) {
			$payload['exclude_products'] = array_map( 'intval', (array) $input['exclude_product_ids'] );
		}

		$payload['stock_settings'] = array(
			'manage_stock' => $input['manage_stock'] ?? true,
			'stock_range'  => array(
				'min' => $input['stock_min'] ?? 0,
				'max' => $input['stock_max'] ?? 100,
			),
		);

		return $payload;
	}
}
