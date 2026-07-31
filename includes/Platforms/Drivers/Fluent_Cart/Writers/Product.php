<?php
/**
 * Fluent Cart product writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use FluentCart\App\Models\Product as ProductModel;
use FluentCart\App\Models\ProductDetail as ProductDetailModel;
use FluentCart\App\Models\ProductVariation as ProductVariationModel;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Platforms\Status;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical product into Fluent Cart.
 *
 * @since 1.1.0
 */
final class Product extends Writer {
	/**
	 * The taxonomy Fluent Cart files products under.
	 *
	 * Hyphenated and plural, unlike WooCommerce's `product_cat` — the same difference the category
	 * writer absorbs, and the reason this constant exists rather than a literal at the call site.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const CATEGORY_TAXONOMY = 'product-categories';

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

		$manage_stock = ! isset( $entity['manage_stock'] ) || (bool) $entity['manage_stock'];

		$data = array(
			'title'             => $entity['title'],
			'slug'              => (string) ( $entity['slug'] ?? '' ),
			'description'       => $entity['description'],
			'short_description' => (string) ( $entity['short_description'] ?? '' ),
			// item_price is integer cents, despite the column being a double —
			// PricingTableRenderer reads it back through Helper::toDecimal().
			'price'             => (int) $entity['price'],
			// Fluent Cart's compare_price is a "was" price rather than a sale price: the higher
			// of the two, shown struck through. So the canonical sale price becomes the item
			// price and the regular one becomes what it is compared against — the same discount
			// a shopper sees on WooCommerce, expressed the way this platform expresses it.
			'compare_price'     => isset( $entity['sale_price'] ) ? (int) $entity['price'] : null,
			'sale_price'        => isset( $entity['sale_price'] ) ? (int) $entity['sale_price'] : null,
			'cost'              => isset( $entity['cost'] ) ? (int) $entity['cost'] : null,
			// 'publish' is the WordPress post status; 'published' is not one.
			'status'            => self::POST_STATUS[ $entity['status'] ] ?? 'publish',
			'sku'               => $this->unique_sku( (string) $entity['sku'] ),
			'manage_stock'      => $manage_stock,
			'stock'             => $manage_stock ? (int) $entity['stock'] : 0,
			// Fluent Cart's column is TINYINT(1), not an enum: it stores whether backorders are
			// allowed, with no equivalent of WooCommerce's "allow, but notify". Passing the
			// canonical string would silently become 0 — 'notify' and 'yes' both reading as "no"
			// — so the two allowing values collapse to 1 here and the distinction is reported as
			// ignored rather than lost.
			'backorders'        => 'no' === (string) ( $entity['backorders'] ?? 'no' ) ? 0 : 1,
			'sold_individually' => (bool) ( $entity['sold_individually'] ?? false ),
			'fulfillment_type'  => $entity['fulfillment_type'],
			'category_count'    => (int) ( $entity['category_count'] ?? 0 ),
		);

		$product_id = $this->create_product( $data );

		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		if ( ! $product_id ) {
			return new WP_Error( 'product_creation_failed', __( 'Failed to create product.', 'storeseeder' ) );
		}

		$categories = $this->attach_categories( (int) $product_id, $data['category_count'] );

		$result = array(
			'id'         => $product_id,
			'title'      => $data['title'],
			'categories' => $categories,
			// Stored in cents; reported in major units so the UI shows 45.67
			// rather than 4567.
			'price'      => round( $data['price'] / 100, 2 ),
			'status'     => $data['status'],
			'type'       => 'simple',
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, $product_id, $data );
	}

	/**
	 * Whether generated variations are bought once or on a subscription.
	 *
	 * A Fluent Cart column with no canonical equivalent, declared by the driver as a platform
	 * field. It was hardcoded to `onetime`, which made subscription products impossible to seed.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function payment_type(): string {
		$requested = isset( $this->params['payment_type'] ) ? (string) $this->params['payment_type'] : 'onetime';

		return in_array( $requested, array( 'onetime', 'subscription' ), true ) ? $requested : 'onetime';
	}

	/**
	 * File the product under existing Fluent Cart categories.
	 *
	 * Existing ones only, for the same reason as the WooCommerce writer: creating them here would
	 * duplicate the Product Categories generator and leave two places inventing names. A store
	 * with none gets uncategorised products, which is what an empty taxonomy means.
	 *
	 * @since 1.1.0
	 *
	 * @param int $product_id The product post id.
	 * @param int $count      How many categories to file it under.
	 *
	 * @return int How many were attached.
	 */
	private function attach_categories( int $product_id, int $count ): int {
		if ( $count < 1 || ! taxonomy_exists( self::CATEGORY_TAXONOMY ) ) {
			return 0;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::CATEGORY_TAXONOMY,
				'hide_empty' => false,
				'number'     => 50,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $terms ) || array() === (array) $terms ) {
			return 0;
		}

		$pick = (array) $terms;
		shuffle( $pick );
		$pick = array_slice( $pick, 0, $count );

		$set = wp_set_object_terms( $product_id, array_map( 'intval', $pick ), self::CATEGORY_TAXONOMY );

		return is_wp_error( $set ) ? 0 : count( $pick );
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
			'post_name'    => '' !== $data['slug'] ? $data['slug'] : sanitize_title( $data['title'] ),
			'post_excerpt' => $data['short_description'],
			'post_content' => $data['description'],
			'post_status'  => $data['status'],
			'post_type'    => 'fluent-products',
		);

		$product = ProductModel::query()->create( $product_data );

		if ( ! $product instanceof ProductModel ) {
			return null;
		}

		// Unmanaged stock reads as available, which is what a product without stock tracking is.
		$stock_status = ! $data['manage_stock'] || $data['stock'] > 0 ? 'in-stock' : 'out-of-stock';

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
				'manage_stock'       => $data['manage_stock'] ? 1 : 0,
				'stock_availability' => $stock_status,
			)
		);

		$variation = ProductVariationModel::query()->create(
			array(
				'post_id'           => $product->ID,
				'serial_index'      => 1,
				'variation_title'   => $data['title'],
				'sku'               => $data['sku'],
				// The lower of the two prices is what the shopper pays, so a discounted product
				// sells at its sale price and shows the regular one struck through.
				'item_price'        => $data['sale_price'] ?? $data['price'],
				'compare_price'     => $data['compare_price'],
				// `item_cost` is NOT NULL with a zero default, and `manage_cost` is what Fluent
				// Cart reads to decide whether the figure means anything — so an untracked cost
				// is zero-and-unmanaged rather than null, which fails the insert outright. Only
				// the non-default path was exercised when this was written, which is why the
				// default one was broken.
				'item_cost'         => (int) ( $data['cost'] ?? 0 ),
				'manage_cost'       => null === $data['cost'] ? 0 : 1,
				'manage_stock'      => $data['manage_stock'] ? 1 : 0,
				'stock_status'      => $stock_status,
				'total_stock'       => $data['stock'],
				'available'         => $data['stock'],
				'backorders'        => $data['backorders'],
				'sold_individually' => $data['sold_individually'] ? 1 : 0,
				'payment_type'      => $this->payment_type(),
				'fulfillment_type'  => $data['fulfillment_type'],
				'item_status'       => 'active',
				'other_info'        => array(
					'description'  => '',
					'payment_type' => $this->payment_type(),
					'tax_class'    => 'standard',
					'tax_exempt'   => 'no',
				),
			)
		);

		// The detail row points at the variation customers land on by default. instanceof
		// rather than a truthiness check: create() is typed Builder|Model through
		// __callStatic, so only this narrows it enough to reach save().
		if ( $detail instanceof ProductDetailModel && $variation instanceof ProductVariationModel ) {
			$detail->default_variation_id = $variation->id;
			$detail->save();
		}

		return $product->ID;
	}

	/**
	 * Remove a generated product.
	 *
	 * A Fluent Cart product is a WordPress post plus two rows of its own, and neither
	 * cascades. `wp_delete_post()` rather than a model delete, because the post carries
	 * meta, terms and an attachment relationship that only core knows how to unpick —
	 * deleting the row directly would leave all of it behind.
	 *
	 * Force-deleted rather than trashed: a trashed test product still occupies its slug and
	 * still shows in the admin's Trash, which is not what "delete the generated data" means.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id The product post id.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		$post_id = (int) $id;

		if ( $post_id <= 0 ) {
			return new WP_Error(
				'storeseeder_delete_failed',
				__( 'A generated product was recorded without a usable post id.', 'storeseeder' )
			);
		}

		// Its own rows first: with the post gone, nothing in the admin can reach them.
		$rows = $this->delete_model(
			ProductDetailModel::class,
			$post_id,
			array( ProductVariationModel::class => 'post_id' ),
			'post_id'
		);

		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		$post = get_post( $post_id );

		// Already gone is a success: the ledger can outlive the data it points at, and a
		// permanent failure here would block the rest of the cleanup for ever.
		if ( ! $post ) {
			return true;
		}

		$deleted = wp_delete_post( $post_id, true );

		if ( null === $deleted || false === $deleted ) {
			return new WP_Error(
				'storeseeder_delete_failed',
				sprintf(
					/* translators: %d: post id. */
					__( 'Could not delete generated product %d.', 'storeseeder' ),
					$post_id
				)
			);
		}

		return true;
	}
}
