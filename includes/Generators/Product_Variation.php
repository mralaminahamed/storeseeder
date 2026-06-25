<?php
/**
 * Product Variation Generator Class for Fluent Cart FakerPress Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    FluentCartFakerPress
 */

namespace FluentCartFakerPress\Generators;

use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Product Variation Generator Class
 *
 * Generates realistic fake product variation data for Fluent Cart testing and development.
 */
class Product_Variation extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'product_variation';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'product_variations' => 'Fluent Cart Product Variations',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates product variations with attributes and pricing for testing Fluent Cart variable product functionality.';
	}

	/**
	 * Generate a single product variation
	 *
	 * @return WP_Error|array Single product variation data, error, or false on failure.
	 */
	protected function generate_single_item() {
		// Check if Fluent Cart is active.
		if ( ! defined( 'FLUENTCART_VERSION' ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart plugin not found. Please ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		$variation_data = $this->generate_variation_data();
		$variation_id   = $this->create_product_variation( $variation_data );

		if ( ! $variation_id ) {
			return new WP_Error( 'variation_creation_failed', __( 'Failed to create product variation.', 'fluent-cart-fakerpress' ) );
		}

		$result = array(
			'id'         => $variation_id,
			'product_id' => $variation_data['product_id'],
			'attributes' => $variation_data['attributes'],
			'price'      => $variation_data['price'],
			'sku'        => $variation_data['sku'],
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		/**
		 * Filters the product variation generation result data.
		 *
		 * Allows developers to modify the returned product variation data after generation.
		 *
		 * @since 1.0.0
		 * @hook  fluent_cart_fakerpress_product_variation_generation_result
		 *
		 * @param array $result          The product variation generation result data.
		 * @param int   $variation_id    The created product variation ID.
		 * @param array $variation_data  The original product variation data used for creation.
		 */
		return apply_filters( 'fluent_cart_fakerpress_product_variation_generation_result', $result, $variation_id, $variation_data );
	}

	/**
	 * Generate product variation data
	 *
	 * @return array Product variation data
	 */
	private function generate_variation_data(): array {
		$attributes      = array();
		$attribute_count = $this->get_faker()->numberBetween( 1, 2 );

		for ( $i = 0; $i < $attribute_count; $i++ ) {
			$attribute_name  = $this->get_faker()->randomElement( array( 'Size', 'Color', 'Style', 'Material' ) );
			$attribute_value = $this->get_faker()->randomElement( array( 'Small', 'Medium', 'Large', 'Red', 'Blue', 'Black', 'Cotton', 'Polyester' ) );

			$attributes[ $attribute_name ] = $attribute_value;
		}

		return array(
			'product_id' => $this->get_faker()->numberBetween( 1, 1000 ),
			'attributes' => $attributes,
			'price'      => $this->get_faker()->randomFloat( 2, 10, 500 ),
			'sku'        => strtoupper( $this->get_faker()->bothify( 'VAR-#####' ) ),
			'stock'      => $this->get_faker()->numberBetween( 0, 50 ),
			'status'     => 'active',
		);
	}

	/**
	 * Create product variation in Fluent Cart
	 *
	 * @param array $data Product variation data.
	 *
	 * @return int|null Created product variation ID
	 */
	private function create_product_variation( array $data ): ?int {
		// Use Fluent Cart's product variation creation API if available.
		if ( function_exists( 'fluentCartCreateProductVariation' ) ) {
			return fluentCartCreateProductVariation( $data );
		}

		// Fallback: create as WordPress post.
		$post_data = array(
			'post_title'   => 'Variation for Product ' . $data['product_id'],
			'post_content' => '',
			'post_status'  => 'publish',
			'post_type'    => 'fluentcart_variation',
			'post_parent'  => $data['product_id'],
			'meta_input'   => array(
				'_attributes' => wp_json_encode( $data['attributes'] ),
				'_price'      => $data['price'],
				'_sku'        => $data['sku'],
				'_stock'      => $data['stock'],
				'_status'     => $data['status'],
			),
		);

		$variation_id = wp_insert_post( $post_data );

		return $variation_id ? $variation_id : null;
	}
}
