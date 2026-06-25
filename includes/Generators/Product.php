<?php
/**
 * Product Generator Class for Fluent Cart FakerPress Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    FluentCartFakerPress
 */

namespace FluentCartFakerPress\Generators;

use FluentCart\App\Models\Product as ProductModel;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Product Generator Class
 *
 * Generates realistic fake product data for Fluent Cart testing and development.
 */
class Product extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'product';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'products' => 'Fluent Cart Products',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates comprehensive product data with pricing, inventory, categories, and attributes for testing Fluent Cart functionality.';
	}

	/**
	 * Generate a single product
	 *
	 * @return WP_Error|array Single product data, error, or false on failure.
	 */
	protected function generate_single_item() {
		// Check if Fluent Cart is active.
		if ( ! defined( 'FLUENTCART_VERSION' ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart plugin not found. Please ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		$product_data = $this->generate_product_data();
		$product_id   = $this->create_product( $product_data );

		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		if ( ! $product_id ) {
			return new WP_Error( 'product_creation_failed', __( 'Failed to create product.', 'fluent-cart-fakerpress' ) );
		}

		$result = array(
			'id'         => $product_id,
			'title'      => $product_data['title'],
			'price'      => $product_data['price'],
			'status'     => $product_data['status'],
			'type'       => 'simple',
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		/**
		 * Filters the product generation result data.
		 *
		 * Allows developers to modify the returned product data after generation.
		 *
		 * @since 1.0.0
		 * @hook  fluent_cart_fakerpress_product_generation_result
		 *
		 * @param array $result       The product generation result data.
		 * @param int   $product_id   The created product ID.
		 * @param array $product_data The original product data used for creation.
		 */
		return apply_filters( 'fluent_cart_fakerpress_product_generation_result', $result, $product_id, $product_data );
	}

	/**
	 * Generate product data
	 *
	 * @return array Product data
	 */
	private function generate_product_data(): array {
		$sample_data = $this->load_sample_data();
		$adjectives  = $sample_data['adjectives'] ?? array( 'Amazing', 'Premium', 'Deluxe', 'Professional' );
		$products    = $sample_data['products'] ?? array( 'Widget', 'Gadget', 'Tool', 'Device', 'System' );

		$title = $this->get_faker()->randomElement( $adjectives ) . ' ' . $this->get_faker()->randomElement( $products );

		return array(
			'title'       => $title,
			'description' => $this->get_faker()->paragraphs( 3, true ),
			'price'       => $this->get_faker()->randomFloat( 2, 9.99, 999.99 ),
			'status'      => 'published',
			'sku'         => strtoupper( $this->get_faker()->bothify( '??-#####' ) ),
			'stock'       => $this->get_faker()->numberBetween( 0, 100 ),
		);
	}

	/**
	 * Create a Fluent Cart product
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Product data.
	 *
	 * @return int|WP_Error|null Created product ID or error
	 */
	private function create_product( array $data ) {
		// Check if Fluent Cart Product model is available.
		if ( ! class_exists( ProductModel::class ) ) {
			return new WP_Error( 'missing_model', __( 'Fluent Cart Product model not found. Please ensure Fluent Cart plugin is active.', 'fluent-cart-fakerpress' ) );
		}

		// Use Fluent Cart Product model with compatible data structure.
		$product_data = array(
			'post_title'   => $data['title'],
			'post_content' => $data['description'],
			'post_status'  => 'publish',
			'post_type'    => 'fluentcart_product',
		);

		$product = ProductModel::query()->create( $product_data );

		if ( ! $product ) {
			return null;
		}

		// Add product meta data.
		$product->updateProductMeta( '_price', $data['price'] );
		$product->updateProductMeta( '_sku', $data['sku'] );
		$product->updateProductMeta( '_stock', $data['stock'] );

		return $product->ID;
	}
}
