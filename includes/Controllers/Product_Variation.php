<?php
/**
 * Product Variation Generator REST Controller
 *
 * Handles REST API endpoints for product variation data generation in StoreSeeder.
 * Provides endpoints for generating product variations with attributes and pricing.
 *
 * @since   1.0.0
 * @package StoreSeeder\Controllers
 */

namespace StoreSeeder\Controllers;

use StoreSeeder\Abstracts\Controller;
use StoreSeeder\Generators\Product_Variation as ProductVariationGenerator;

/**
 * Product Variation Generator REST Controller
 *
 * Handles REST API endpoints for generating product variation data in Fluent Cart stores.
 * Provides comprehensive product variation generation with support for attributes,
 * pricing variations, inventory, and variable product relationships.
 *
 * Endpoints:
 * - POST /wp-json/storeseeder/v1/product-variations/generate
 *
 * Features:
 * - Full product variation creation with Fluent Cart integration
 * - Attribute-based variations (size, color, etc.)
 * - Pricing variations and inventory management
 * - Variable product relationships
 * - SKU generation and metadata
 *
 * @since 1.0.0
 */
class Product_Variation extends Controller {


	/**
	 * Get resource type name
	 *
	 * @since 1.0.0
	 *
	 * @return string Resource type.
	 */
	protected function get_resource_type(): string {
		return 'product_variation';
	}

	/**
	 * Get resource type label for product variations
	 *
	 * @since 1.0.0
	 *
	 * @return string The translated label for product variation resource type.
	 */
	protected function get_resource_type_label(): string {
		return __( 'Product Variation', 'storeseeder' );
	}

	/**
	 * Get REST base for the endpoint
	 *
	 * Returns the REST API base path for product variation generation endpoints.
	 * Forms the endpoint URL: /wp-json/storeseeder/v1/product-variations/generate
	 *
	 * @since 1.0.0
	 *
	 * @return string REST base path segment ('product-variations').
	 */
	protected function get_rest_base(): string {
		return 'product-variations';
	}

	/**
	 * Get generator instance
	 *
	 * @since 1.0.0
	 *
	 * @return ProductVariationGenerator Generator instance.
	 */
	protected function get_generator_instance(): ProductVariationGenerator {
		return new ProductVariationGenerator();
	}

	/**
	 * Get resource-specific generation parameters
	 *
	 * @since 1.0.0
	 *
	 * @return array Resource-specific parameters.
	 */
	protected function get_resource_specific_params(): array {
		return array(
			'variation_types'          => array(
				'description'       => __( 'Types of product variations to generate.', 'storeseeder' ),
				'type'              => 'array',
				'items'             => array(
					'type' => 'string',
					'enum' => array( 'size', 'color', 'material', 'style', 'flavor', 'weight', 'dimension' ),
				),
				'default'           => array( 'size', 'color' ),
				'sanitize_callback' => array( $this, 'sanitize_array' ),
			),
			'attributes_per_product'   => array(
				'description' => __( 'Number of attributes per variable product.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'min' => array(
						'description' => __( 'Minimum attributes per product.', 'storeseeder' ),
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 5,
						'default'     => 1,
					),
					'max' => array(
						'description' => __( 'Maximum attributes per product.', 'storeseeder' ),
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 5,
						'default'     => 3,
					),
				),
			),
			'variations_per_attribute' => array(
				'description' => __( 'Number of variations per attribute.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'min' => array(
						'description' => __( 'Minimum variations per attribute.', 'storeseeder' ),
						'type'        => 'integer',
						'minimum'     => 2,
						'maximum'     => 10,
						'default'     => 3,
					),
					'max' => array(
						'description' => __( 'Maximum variations per attribute.', 'storeseeder' ),
						'type'        => 'integer',
						'minimum'     => 2,
						'maximum'     => 20,
						'default'     => 8,
					),
				),
			),
			'price_variation_range'    => array(
				'description' => __( 'Price variation range as percentage of base price.', 'storeseeder' ),
				'type'        => 'object',
				'properties'  => array(
					'min_percentage' => array(
						'description' => __( 'Minimum price variation percentage.', 'storeseeder' ),
						'type'        => 'number',
						'minimum'     => -50,
						'maximum'     => 0,
						'default'     => -20,
					),
					'max_percentage' => array(
						'description' => __( 'Maximum price variation percentage.', 'storeseeder' ),
						'type'        => 'number',
						'minimum'     => 0,
						'maximum'     => 200,
						'default'     => 50,
					),
				),
			),
			'include_inventory'        => array(
				'description' => __( 'Include inventory management for variations.', 'storeseeder' ),
				'type'        => 'boolean',
				'default'     => true,
			),
			'generate_skus'            => array(
				'description' => __( 'Generate unique SKUs for each variation.', 'storeseeder' ),
				'type'        => 'boolean',
				'default'     => true,
			),
		);
	}

	/**
	 * Get resource-specific schema properties
	 *
	 * @since 1.0.0
	 *
	 * @return array Resource-specific properties.
	 */
	protected function get_resource_specific_properties(): array {
		return array(
			'product_variations' => array(
				'description' => __( 'Generated product variations with attributes and pricing.', 'storeseeder' ),
				'type'        => 'array',
				'context'     => array( 'view' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'product_id'     => array(
							'type' => 'integer',
						),
						'variation_id'   => array(
							'type' => 'integer',
						),
						'attributes'     => array(
							'type' => 'object',
						),
						'sku'            => array(
							'type' => 'string',
						),
						'price'          => array(
							'type' => 'string',
						),
						'stock_quantity' => array(
							'type' => 'integer',
						),
					),
				),
			),
		);
	}
}
