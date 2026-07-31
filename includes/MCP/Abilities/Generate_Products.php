<?php
/**
 * MCP Ability: Generate Products
 *
 * @package StoreSeeder\MCP\Abilities
 * @since   2.1.0
 */

namespace StoreSeeder\MCP\Abilities;

use StoreSeeder\MCP\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Generate_Products
 *
 * Maps to REST endpoint: POST /storeseeder/v1/products/generate
 *
 * @since 1.0.0
 */
class Generate_Products extends Ability {

	const REST_BASE = 'products';

	/**
	 * {@inheritdoc}
	 */
	public static function label(): string {
		return __( 'Generate Products', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	public static function description(): string {
		return __( 'Generate realistic Fluent Cart products with attributes, variations, categories, pricing strategies, and inventory data. Returns an array of created product IDs and summaries.', 'storeseeder' );
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function input_properties(): array {
		return array(
			'product_type'       => array(
				'type'        => 'string',
				'description' => __( 'Type of products to generate. Allowed: simple, variable, grouped, external, digital, mixed. Default: mixed.', 'storeseeder' ),
				'enum'        => array( 'simple', 'variable', 'grouped', 'external', 'digital', 'mixed' ),
				'default'     => 'mixed',
			),
			'price_min'          => array(
				'type'        => 'number',
				'description' => __( 'Minimum product price (USD). Default: 10.', 'storeseeder' ),
				'default'     => 10,
			),
			'price_max'          => array(
				'type'        => 'number',
				'description' => __( 'Maximum product price (USD). Default: 500.', 'storeseeder' ),
				'default'     => 500,
			),
			'include_attributes' => array(
				'type'        => 'boolean',
				'description' => __( 'Generate product attributes (size, colour, material). Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'variation_count'    => array(
				'type'        => 'integer',
				'description' => __( 'Number of variations per variable product (1–20). Default: 5.', 'storeseeder' ),
				'minimum'     => 1,
				'maximum'     => 20,
				'default'     => 5,
			),
			'manage_stock'       => array(
				'type'        => 'boolean',
				'description' => __( 'Enable stock management and generate inventory levels. Default: true.', 'storeseeder' ),
				'default'     => true,
			),
			'description_length' => array(
				'type'        => 'string',
				'description' => __( 'Length of product descriptions. Allowed: short, medium, long. Default: medium.', 'storeseeder' ),
				'enum'        => array( 'short', 'medium', 'long' ),
				'default'     => 'medium',
			),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected static function output(): array {
		return array(
			'key'         => 'products',
			'description' => __( 'Array of generated product objects with id, title, type, variations count, price_range, and stock_status.', 'storeseeder' ),
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

		if ( isset( $input['product_type'] ) ) {
			$payload['product_type'] = $input['product_type'];
		}

		// Nest price into the expected object shape.
		if ( isset( $input['price_min'] ) || isset( $input['price_max'] ) ) {
			$payload['price_range'] = array(
				'min' => $input['price_min'] ?? 10,
				'max' => $input['price_max'] ?? 500,
			);
		}

		// Nest attributes options.
		$payload['attributes'] = array(
			'include_attributes' => $input['include_attributes'] ?? true,
			'variation_count'    => $input['variation_count'] ?? 5,
		);

		// Nest inventory options including optional stock_range.
		$inventory = array(
			'manage_stock' => $input['manage_stock'] ?? true,
		);
		if ( isset( $input['stock_min'] ) || isset( $input['stock_max'] ) ) {
			$inventory['stock_range'] = array(
				'min' => $input['stock_min'] ?? 0,
				'max' => $input['stock_max'] ?? 100,
			);
		}
		$payload['inventory'] = $inventory;

		// Nest categories options.
		if ( isset( $input['categories_create_new'] ) || isset( $input['categories_max_per_product'] ) ) {
			$payload['categories'] = array(
				'create_new'      => $input['categories_create_new'] ?? true,
				'max_per_product' => $input['categories_max_per_product'] ?? 3,
			);
		}

		// Nest content options including optional include_images.
		$content_options = array(
			'description_length' => $input['description_length'] ?? 'medium',
		);
		if ( isset( $input['include_images'] ) ) {
			$content_options['include_images'] = (bool) $input['include_images'];
		}
		$payload['content_options'] = $content_options;

		return $payload;
	}
}
