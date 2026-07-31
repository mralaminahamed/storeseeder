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
			'variation_types'          => array(
				'type'        => 'array',
				'description' => __( 'Attribute axes to build variations from. Allowed: size, color, material, style, flavor, weight, dimension. Default: ["size","color"].', 'storeseeder' ),
				'items'       => array(
					'type' => 'string',
					'enum' => array( 'size', 'color', 'material', 'style', 'flavor', 'weight', 'dimension' ),
				),
				'default'     => array( 'size', 'color' ),
			),
			'attributes_per_product'   => array(
				'type'        => 'object',
				'description' => __( 'How many axes each variation carries, as a min and max. Default: 1 to 2.', 'storeseeder' ),
				'properties'  => array(
					'min' => array( 'type' => 'integer' ),
					'max' => array( 'type' => 'integer' ),
				),
			),
			'variations_per_attribute' => array(
				'type'        => 'object',
				'description' => __( 'How many distinct values each axis draws from, which is how wide a product\'s option list gets. Default: 3 to 8.', 'storeseeder' ),
				'properties'  => array(
					'min' => array( 'type' => 'integer' ),
					'max' => array( 'type' => 'integer' ),
				),
			),
			'price_variation_range'    => array(
				'type'        => 'object',
				'description' => __( 'How far a variation\'s price sits from its parent\'s, as a percentage. Default: -20 to 50. A variation is never priced at zero.', 'storeseeder' ),
				'properties'  => array(
					'min_percentage' => array( 'type' => 'number' ),
					'max_percentage' => array( 'type' => 'number' ),
				),
			),
			'manage_stock'             => array(
				'type'        => 'boolean',
				'description' => __( 'Track stock per variation. False leaves them in stock with no quantity. Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'stock_min'                => array(
				'type'        => 'integer',
				'description' => __( 'Minimum stock quantity per variation. Default: 0.', 'storeseeder' ),
				'minimum'     => 0,
				'default'     => 0,
			),
			'stock_max'                => array(
				'type'        => 'integer',
				'description' => __( 'Maximum stock quantity per variation. Default: 100.', 'storeseeder' ),
				'minimum'     => 0,
				'default'     => 100,
			),
			'generate_skus'            => array(
				'type'        => 'boolean',
				'description' => __( 'Give each variation a SKU. False generates variations identified only by their options, which is legal everywhere and worth testing. Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'specific_product_id'      => array(
				'type'        => 'integer',
				'description' => __( 'Attach every variation to this product, to build out one option matrix. Omit to pick a random eligible product.', 'storeseeder' ),
				'minimum'     => 1,
			),
			'exclude_product_ids'      => array(
				'type'        => 'array',
				'description' => __( 'Products to skip when choosing a parent.', 'storeseeder' ),
				'items'       => array( 'type' => 'integer' ),
				'default'     => array(),
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

		foreach ( array( 'variation_types', 'exclude_product_ids' ) as $list ) {
			if ( isset( $input[ $list ] ) ) {
				$payload[ $list ] = (array) $input[ $list ];
			}
		}

		foreach ( array( 'attributes_per_product', 'variations_per_attribute', 'price_variation_range' ) as $range ) {
			if ( isset( $input[ $range ] ) ) {
				$payload[ $range ] = (array) $input[ $range ];
			}
		}

		if ( isset( $input['specific_product_id'] ) ) {
			// One name for the writers, whichever surface asked.
			$payload['product_id'] = (int) $input['specific_product_id'];
		}

		if ( isset( $input['generate_skus'] ) ) {
			$payload['generate_skus'] = (bool) $input['generate_skus'];
		}

		$payload['inventory'] = array(
			'manage_stock' => $input['manage_stock'] ?? true,
			'stock_range'  => array(
				'min' => $input['stock_min'] ?? 0,
				'max' => $input['stock_max'] ?? 100,
			),
		);

		return $payload;
	}
}
