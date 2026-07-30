<?php
/**
 * Product Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder
 */

namespace StoreSeeder\Generators;

use FluentCart\App\Models\Product as ProductModel;
use FluentCart\App\Models\ProductDetail as ProductDetailModel;
use FluentCart\App\Models\ProductVariation as ProductVariationModel;
use StoreSeeder\Abstracts\Generator;
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
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart plugin not found. Please ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		$product_data = $this->generate_product_data();
		$product_id   = $this->create_product( $product_data );

		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		if ( ! $product_id ) {
			return new WP_Error( 'product_creation_failed', __( 'Failed to create product.', 'storeseeder' ) );
		}

		$result = array(
			'id'         => $product_id,
			'title'      => $product_data['title'],
			// Stored in cents; reported in major units so the UI shows 45.67
			// rather than 4567.
			'price'      => round( $product_data['price'] / 100, 2 ),
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
		 * @hook  storeseeder_product_generation_result
		 *
		 * @param array $result       The product generation result data.
		 * @param int   $product_id   The created product ID.
		 * @param array $product_data The original product data used for creation.
		 */
		return apply_filters( 'storeseeder_product_generation_result', $result, $product_id, $product_data );
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

		$fulfillment_type = $this->get_faker()->randomElement( array( 'physical', 'digital' ) );

		return array(
			'title'            => $title,
			'description'      => $this->get_faker()->paragraphs( 3, true ),
			// item_price is integer cents, despite the column being a double —
			// PricingTableRenderer reads it back through Helper::toDecimal().
			'price'            => (int) round( $this->get_faker()->randomFloat( 2, 9.99, 999.99 ) * 100 ),
			// 'publish' is the WordPress post status; 'published' is not one.
			'status'           => 'publish',
			'sku'              => $this->unique_sku(),
			'stock'            => $this->get_faker()->numberBetween( 0, 100 ),
			'fulfillment_type' => $fulfillment_type,
		);
	}

	/**
	 * Build an SKU that is not already taken.
	 *
	 * The fct_product_variations table carries a UNIQUE index on sku, so a
	 * collision is a database error rather than a silently overwritten row.
	 *
	 * @since 1.0.0
	 *
	 * @return string Unused SKU.
	 */
	private function unique_sku(): string {
		do {
			$sku = strtoupper( $this->get_faker()->bothify( '??-#####' ) );
		} while ( ProductVariationModel::query()->where( 'sku', $sku )->exists() );

		return $sku;
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
			return new WP_Error( 'missing_model', __( 'Fluent Cart Product model not found. Please ensure Fluent Cart plugin is active.', 'storeseeder' ) );
		}

		// post_type is forced to the fluent-products CPT by Product::boot(), but
		// naming it here keeps the intent readable.
		$product_data = array(
			'post_title'   => $data['title'],
			'post_name'    => sanitize_title( $data['title'] ),
			'post_content' => $data['description'],
			'post_status'  => $data['status'],
			'post_type'    => 'fluent-products',
		);

		$product = ProductModel::query()->create( $product_data );

		if ( ! $product ) {
			return null;
		}

		$stock_status = $data['stock'] > 0 ? 'in-stock' : 'out-of-stock';

		// A product Fluent Cart can actually sell needs three rows, not one:
		// the post, a fct_product_details row, and at least one variation.
		// Price, SKU and stock live on the variation — the _price/_sku/_stock
		// post meta this generator used to write is read by nothing in Fluent
		// Cart, so those products had no price and could not be bought.
		$detail = ProductDetailModel::query()->create(
			array(
				'post_id'            => $product->ID,
				'fulfillment_type'   => $data['fulfillment_type'],
				'variation_type'     => 'simple',
				'min_price'          => $data['price'],
				'max_price'          => $data['price'],
				'manage_stock'       => 1,
				'stock_availability' => $stock_status,
			)
		);

		$variation = ProductVariationModel::query()->create(
			array(
				'post_id'          => $product->ID,
				'serial_index'     => 1,
				'variation_title'  => $data['title'],
				'sku'              => $data['sku'],
				'item_price'       => $data['price'],
				'manage_stock'     => 1,
				'stock_status'     => $stock_status,
				'total_stock'      => $data['stock'],
				'available'        => $data['stock'],
				'payment_type'     => 'onetime',
				'fulfillment_type' => $data['fulfillment_type'],
				'item_status'      => 'active',
				'other_info'       => array(
					'description'  => '',
					'payment_type' => 'onetime',
					'tax_class'    => 'standard',
					'tax_exempt'   => 'no',
				),
			)
		);

		// The detail row points at the variation customers land on by default.
		if ( $detail && $variation ) {
			$detail->default_variation_id = $variation->id;
			$detail->save();
		}

		return $product->ID;
	}

	/**
	 * Preview columns for products
	 *
	 * @since 1.0.1
	 *
	 * @return array<int, array{key: string, label: string}>
	 */
	protected function get_preview_columns(): array {
		return array(
			array(
				'key'   => 'name',
				'label' => __( 'Name', 'storeseeder' ),
			),
			array(
				'key'   => 'sku',
				'label' => __( 'SKU', 'storeseeder' ),
			),
			array(
				'key'   => 'price',
				'label' => __( 'Price', 'storeseeder' ),
			),
			array(
				'key'   => 'stock',
				'label' => __( 'Stock', 'storeseeder' ),
			),
			array(
				'key'   => 'status',
				'label' => __( 'Status', 'storeseeder' ),
			),
		);
	}

	/**
	 * Build a product preview row
	 *
	 * @since 1.0.1
	 *
	 * @return array<string, array{v: mixed, kind: string}>
	 */
	protected function build_preview_row(): array {
		$faker = $this->get_faker();

		return array(
			'name'   => array(
				// words() without the $asText flag returns an array, which joins
				// cleanly — asking for the string form types as array|string.
				'v'    => ucwords( implode( ' ', (array) $faker->words( 3 ) ) ),
				'kind' => 'text',
			),
			'sku'    => array(
				'v'    => 'SKU-' . $faker->numberBetween( 1000, 9999 ),
				'kind' => 'mono',
			),
			'price'  => array(
				'v'    => '$' . number_format( $faker->randomFloat( 2, 5, 500 ), 2 ),
				'kind' => 'money',
			),
			'stock'  => array(
				'v'    => $faker->numberBetween( 0, 250 ),
				'kind' => 'num',
			),
			'status' => array(
				'v'    => $faker->randomElement( array( 'publish', 'draft', 'pending' ) ),
				'kind' => 'status',
			),
		);
	}
}
