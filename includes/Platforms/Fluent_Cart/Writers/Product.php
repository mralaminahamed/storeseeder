<?php
/**
 * Fluent Cart product writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Fluent_Cart\Writers;

use FluentCart\App\Models\Product as ProductModel;
use FluentCart\App\Models\ProductDetail as ProductDetailModel;
use FluentCart\App\Models\ProductVariation as ProductVariationModel;
use StoreSeeder\Abstracts\Writer;
use StoreSeeder\Platform\Resource;
use StoreSeeder\Platform\Status;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical product into Fluent Cart.
 *
 * @since 1.1.0
 */
final class Product extends Writer {
	/**
	 * Canonical publication status to WordPress post status.
	 *
	 * @since 1.1.0
	 * @var array<string, string>
	 */
	private const POST_STATUS = array(
		Status::PUBLISHED => 'publish',
		Status::DRAFT     => 'draft',
		Status::PRIVATE   => 'private',
	);

	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::PRODUCT;
	}

	/**
	 * Create a Fluent Cart product.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical product entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! defined( 'FLUENTCART_VERSION' ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart plugin not found. Please ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		if ( ! class_exists( ProductModel::class ) ) {
			return new WP_Error( 'missing_model', __( 'Fluent Cart Product model not found. Please ensure Fluent Cart plugin is active.', 'storeseeder' ) );
		}

		$data = array(
			'title'            => $entity['title'],
			'description'      => $entity['description'],
			// item_price is integer cents, despite the column being a double —
			// PricingTableRenderer reads it back through Helper::toDecimal().
			'price'            => (int) $entity['price'],
			// 'publish' is the WordPress post status; 'published' is not one.
			'status'           => self::POST_STATUS[ $entity['status'] ] ?? 'publish',
			'sku'              => $this->unique_sku( (string) $entity['sku'] ),
			'stock'            => (int) $entity['stock'],
			'fulfillment_type' => $entity['fulfillment_type'],
		);

		$product_id = $this->create_product( $data );

		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		if ( ! $product_id ) {
			return new WP_Error( 'product_creation_failed', __( 'Failed to create product.', 'storeseeder' ) );
		}

		$result = array(
			'id'         => $product_id,
			'title'      => $data['title'],
			// Stored in cents; reported in major units so the UI shows 45.67
			// rather than 4567.
			'price'      => round( $data['price'] / 100, 2 ),
			'status'     => $data['status'],
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
		return apply_filters( 'storeseeder_product_generation_result', $result, $product_id, $data );
	}

	/**
	 * Ensure an SKU is not already taken.
	 *
	 * The fct_product_variations table carries a UNIQUE index on sku, so a collision is
	 * a database error rather than a silently overwritten row. The generator supplies a
	 * candidate — checking it is the platform's business, since only the platform knows
	 * what is already in use.
	 *
	 * @since 1.1.0
	 *
	 * @param string $candidate SKU proposed by the generator.
	 *
	 * @return string Unused SKU.
	 */
	private function unique_sku( string $candidate ): string {
		$sku = $candidate;

		while ( ProductVariationModel::query()->where( 'sku', $sku )->exists() ) {
			$sku = strtoupper( $this->faker()->bothify( '??-#####' ) );
		}

		return $sku;
	}

	/**
	 * Create the three rows a sellable Fluent Cart product needs.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $data Fluent-Cart-shaped product data.
	 *
	 * @return int|WP_Error|null Created product ID, error, or null when the post failed.
	 */
	private function create_product( array $data ) {
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
}
