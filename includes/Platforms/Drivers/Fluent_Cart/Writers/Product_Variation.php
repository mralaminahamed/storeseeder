<?php
/**
 * Fluent Cart product variation writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use Exception;
use FluentCart\App\Models\ProductDetail as ProductDetailModel;
use FluentCart\App\Models\ProductVariation as ProductVariationModel;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical product variation into Fluent Cart.
 *
 * @since 1.1.0
 */
final class Product_Variation extends Writer {
	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::PRODUCT_VARIATION;
	}

	/**
	 * Create a Fluent Cart product variation.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical product variation entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! defined( 'FLUENTCART_VERSION' ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart plugin not found. Please ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		// A variation is a child of a product: post_id is a foreign key into wp_posts
		// and the row is joined to fct_product_details. Inventing the post_id — as this
		// generator used to, with numberBetween( 1, 1000 ) — produces variations
		// attached to no product, invisible everywhere. Selecting a fct_product_details
		// row guarantees both a wp_posts product and the detail row whose price range
		// has to be kept in sync.
		$detail = ProductDetailModel::query()->inRandomOrder()->first();

		if ( ! $detail ) {
			return new WP_Error(
				'no_products',
				__( 'No products were found. Generate products before generating variations.', 'storeseeder' )
			);
		}

		$post_id      = (int) $detail->post_id;
		$stock        = (int) $entity['stock'];
		$stock_status = $stock > 0 ? 'in-stock' : 'out-of-stock';

		$variation_data = array(
			'post_id'          => $post_id,
			// serial_index orders the variations on the product; colliding with an
			// existing one hides the new row behind it in the admin list.
			'serial_index'     => $this->next_serial_index( $post_id ),
			'variation_title'  => $entity['title'],
			'sku'              => $this->unique_sku( (string) $entity['sku'] ),
			// item_price is integer cents, despite the column being a double —
			// PricingTableRenderer reads it back through Helper::toDecimal().
			'item_price'       => (int) $entity['price'],
			'manage_stock'     => 1,
			'stock_status'     => $stock_status,
			'total_stock'      => $stock,
			'available'        => $stock,
			'payment_type'     => 'onetime',
			// Inherit the parent's fulfillment type so the variation does not
			// claim to ship when the product is digital.
			'fulfillment_type' => $detail->fulfillment_type ? $detail->fulfillment_type : 'physical',
			'item_status'      => 'active',
			'other_info'       => array(
				'description'  => '',
				'payment_type' => 'onetime',
				'tax_class'    => 'standard',
				'tax_exempt'   => 'no',
			),
		);

		$variation = $this->create_product_variation( $variation_data );

		if ( ! $variation ) {
			return new WP_Error( 'variation_creation_failed', __( 'Failed to create product variation.', 'storeseeder' ) );
		}

		// Keep the parent product's stored price range in step with its
		// variations so listing queries that read the columns directly — rather
		// than through ProductDetail's computed accessors — stay correct.
		$this->sync_product_price_range( $post_id );

		$result = array(
			'id'         => $variation->id,
			'product_id' => $variation->post_id,
			'title'      => $variation->variation_title,
			// Stored in cents; reported in major units so the UI shows 45.67
			// rather than 4567.
			'price'      => round( $variation->item_price / 100, 2 ),
			'sku'        => $variation->sku,
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		/**
		 * Filters the product variation generation result data.
		 *
		 * Allows developers to modify the returned product variation data after generation.
		 *
		 * @since 1.0.0
		 * @hook  storeseeder_product_variation_generation_result
		 *
		 * @param array $result          The product variation generation result data.
		 * @param int   $variation_id    The created product variation ID.
		 * @param array $variation_data  The original product variation data used for creation.
		 */
		return apply_filters( 'storeseeder_product_variation_generation_result', $result, $variation->id, $variation_data );
	}

	/**
	 * Next free serial_index for a product's variations.
	 *
	 * @since 1.1.0
	 *
	 * @param int $post_id Parent product post ID.
	 *
	 * @return int One past the current highest serial_index.
	 */
	private function next_serial_index( int $post_id ): int {
		$max = ProductVariationModel::query()->where( 'post_id', $post_id )->max( 'serial_index' );

		return (int) $max + 1;
	}

	/**
	 * Ensure an SKU is not already taken.
	 *
	 * The fct_product_variations table carries a UNIQUE index on sku, so a
	 * collision is a database error rather than a silently overwritten row.
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
	 * Create product variation in Fluent Cart.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $data Fluent-Cart-shaped variation data.
	 *
	 * @return ProductVariationModel|null Created variation instance.
	 */
	private function create_product_variation( array $data ): ?ProductVariationModel {
		if ( ! class_exists( ProductVariationModel::class ) ) {
			return null;
		}

		try {
			return ProductVariationModel::query()->create( $data );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Recompute a product's stored min/max price from its variations.
	 *
	 * ProductDetail shadows min_price and max_price with computed accessors, so a
	 * plain model assignment would not persist reliably. Writing the columns
	 * through the query builder keeps the stored values — which some listing and
	 * sort queries read directly — in step with the variation just added.
	 *
	 * @since 1.1.0
	 *
	 * @param int $post_id Parent product post ID.
	 *
	 * @return void
	 */
	private function sync_product_price_range( int $post_id ): void {
		$min = ProductVariationModel::query()->where( 'post_id', $post_id )->min( 'item_price' );
		$max = ProductVariationModel::query()->where( 'post_id', $post_id )->max( 'item_price' );

		ProductDetailModel::query()->where( 'post_id', $post_id )->update(
			array(
				'min_price' => (int) $min,
				'max_price' => (int) $max,
			)
		);
	}
}
