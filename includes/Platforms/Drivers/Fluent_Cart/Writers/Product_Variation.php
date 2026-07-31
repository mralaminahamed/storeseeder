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
		$detail = $this->parent_detail();

		if ( ! $detail ) {
			return new WP_Error(
				'no_products',
				__( 'No products were found. Generate products before generating variations.', 'storeseeder' )
			);
		}

		$post_id = (int) $detail->post_id;
		$manage  = ! isset( $entity['manage_stock'] ) || (bool) $entity['manage_stock'];
		$stock   = $manage ? (int) $entity['stock'] : 0;

		// Unmanaged stock is in stock: Fluent Cart's columns are NOT NULL, so a variation that
		// tracks nothing has to say so through `manage_stock` rather than by holding no quantity,
		// and a zero with management off must not read as sold out.
		$stock_status = ( ! $manage || $stock > 0 ) ? 'in-stock' : 'out-of-stock';

		$variation_data = array(
			'post_id'          => $post_id,
			// serial_index orders the variations on the product; colliding with an
			// existing one hides the new row behind it in the admin list.
			'serial_index'     => $this->next_serial_index( $post_id ),
			'variation_title'  => $entity['title'],
			// Null asks for no SKU, and here it has to stay null rather than becoming an empty
			// string: `fct_product_variations` has a unique index on `sku`, so a second row with
			// '' collides — "Duplicate entry '' for key sku_unique" — while MySQL allows any
			// number of NULLs under a unique index.
			'sku'              => null === ( $entity['sku'] ?? null ) ? null : $this->unique_sku( (string) $entity['sku'] ),
			// item_price is integer cents, despite the column being a double —
			// PricingTableRenderer reads it back through Helper::toDecimal(). Priced from the
			// parent where the parent has a price, since `price_variation_range` asks for a
			// percentage of it and only this side knows what it is.
			'item_price'       => $this->variation_price( $detail, $entity ),
			'manage_stock'     => $manage ? 1 : 0,
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
				// The axes this variation is identified by. Fluent Cart has no attribute model of
				// its own — the title is the option — so they are kept here, where its own
				// variation payloads keep everything else that has no column.
				'attributes'   => array_filter( (array) ( $entity['attributes'] ?? array() ), 'is_string' ),
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

		return $this->filter_result( $result, $variation->id, $variation_data );
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
			$created = ProductVariationModel::query()->create( $data );

			// Eloquent's create() is reached through __callStatic, so its declared type is
			// Builder|Model rather than this model. Narrowing here is what makes the
			// nullable return type above true rather than merely intended.
			return $created instanceof ProductVariationModel ? $created : null;
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

	/**
	 * Remove a generated product variation.
	 *
	 * The product keeps its own default-variation pointer; Fluent Cart tolerates a stale one
	 * and repairs it on the next save, whereas guessing a replacement here could make a
	 * variation the store never chose the default.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id The identifier reported when the row was created.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		return $this->delete_model( ProductVariationModel::class, $id );
	}

	/**
	 * What this variation costs, in integer cents.
	 *
	 * `price_variation_range` asks for a percentage of the parent's price, which only the platform
	 * knows. A Fluent Cart product has no price of its own — its price *is* its variations — so the
	 * base is the cheapest one already on the product, read through ProductDetail's `min_price`
	 * accessor, which is `min('item_price')` in cents. A product with no variations yet leaves this
	 * variation on the generated fallback: a percentage of nothing is nothing, and a free variation
	 * is not what was asked for.
	 *
	 * @since 1.1.0
	 *
	 * @param ProductDetailModel   $detail The parent product's detail row.
	 * @param array<string, mixed> $entity Canonical variation entity.
	 *
	 * @return int
	 */
	private function variation_price( ProductDetailModel $detail, array $entity ): int {
		$base = (int) ( $detail->min_price ?? 0 );

		if ( $base <= 0 ) {
			return (int) $entity['price'];
		}

		$delta = (float) ( $entity['price_delta_percent'] ?? 0 );

		// Never free: Fluent Cart treats a zero-priced variation as one, and a catalogue of them is
		// a broken fixture rather than a cheap one.
		return max( 1, (int) round( $base * ( 1 + $delta / 100 ) ) );
	}

	/**
	 * The product this variation attaches to.
	 *
	 * A requested one wins, so a run can build out a single product's option matrix rather than
	 * scattering variations across the catalogue. Excluded ids are skipped.
	 *
	 * @since 1.1.0
	 *
	 * @return ProductDetailModel|null
	 */
	private function parent_detail(): ?ProductDetailModel {
		$requested = (int) ( $this->params['product_id'] ?? 0 );

		if ( $requested > 0 ) {
			$detail = ProductDetailModel::query()->where( 'post_id', $requested )->first();

			return $detail instanceof ProductDetailModel ? $detail : null;
		}

		$query    = ProductDetailModel::query();
		$excluded = array_map( 'intval', (array) ( $this->params['exclude_product_ids'] ?? array() ) );

		if ( array() !== $excluded ) {
			$query->whereNotIn( 'post_id', $excluded );
		}

		$detail = $query->inRandomOrder()->first();

		return $detail instanceof ProductDetailModel ? $detail : null;
	}
}
