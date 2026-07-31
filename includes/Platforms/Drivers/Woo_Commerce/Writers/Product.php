<?php
/**
 * WooCommerce product writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WC_Product_Simple;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical product into WooCommerce.
 *
 * Through `WC_Product_Simple` rather than `wp_insert_post`, which is what makes the product
 * appear correctly under HPOS or the legacy post store, fires the hooks other extensions
 * listen for, and keeps the record valid across a WooCommerce upgrade. A hand-inserted post
 * with the right meta looks fine in the database and is missing from half the admin.
 *
 * @since 1.1.0
 */
final class Product extends Writer {
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
	 * Create a WooCommerce product.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical product entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return new WP_Error(
				'missing_woocommerce',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$price        = $this->to_decimal( (int) $entity['price'] );
		$virtual      = 'digital' === ( $entity['fulfillment_type'] ?? 'physical' );
		$manage_stock = ! isset( $entity['manage_stock'] ) || (bool) $entity['manage_stock'];
		$stock        = $manage_stock ? (int) $entity['stock'] : null;

		$product = new WC_Product_Simple();
		$product->set_props(
			array(
				'name'               => $entity['title'],
				'slug'               => (string) ( $entity['slug'] ?? '' ),
				'status'             => $this->map_post_status( (string) $entity['status'] ),
				'description'        => $entity['description'],
				'short_description'  => (string) ( $entity['short_description'] ?? '' ),
				'sku'                => $this->unique_sku( (string) $entity['sku'] ),
				'regular_price'      => $price,
				// Null means no sale, and WooCommerce reads an empty string for that rather
				// than a zero — which would be a product given away.
				'sale_price'         => isset( $entity['sale_price'] ) ? $this->to_decimal( (int) $entity['sale_price'] ) : '',
				'backorders'         => $this->backorders( $entity ),
				'sold_individually'  => (bool) ( $entity['sold_individually'] ?? false ),
				'virtual'            => $virtual,
				// A digital product is downloadable only once it has a file, which is the
				// Product Downloads generator's job. Marking it downloadable with no file
				// produces a product WooCommerce refuses to let anyone buy.
				'downloadable'       => false,
				'manage_stock'       => $manage_stock,
				'stock_quantity'     => $stock,
				// Without stock management there is no quantity to compare, and WooCommerce
				// treats the product as available — which is what an unmanaged product means.
				'stock_status'       => ! $manage_stock || $stock > 0 ? 'instock' : 'outofstock',
				// Platform fields, declared by the driver and read from the run's parameters.
				// See Platform::platform_fields(): these are WooCommerce properties no canonical
				// entity carries, because no other platform has them.
				'tax_status'         => $this->platform_param( 'tax_status', 'taxable', array( 'taxable', 'shipping', 'none' ) ),
				'catalog_visibility' => $this->platform_param( 'catalog_visibility', 'visible', array( 'visible', 'catalog', 'search', 'hidden' ) ),
				'featured'           => $this->faker()->boolean( $this->featured_ratio() ),
				// Weight and dimensions on a virtual product are contradictory, and
				// WooCommerce hides the fields — so an empty string rather than a number.
				'weight'             => $virtual ? '' : (string) $this->faker()->numberBetween( 1, 40 ),
			)
		);

		$id = $product->save();

		if ( ! $id ) {
			return new WP_Error( 'product_creation_failed', __( 'Failed to create the product.', 'storeseeder' ) );
		}

		// Cost of goods is a WooCommerce 10 feature that is off on most stores, and setting it
		// while disabled throws rather than being ignored.
		$this->maybe_set_cost( $product, $entity );

		$categories = $this->attach_categories( (int) $id, (int) ( $entity['category_count'] ?? 0 ) );

		$data = array(
			'id'     => (int) $id,
			'name'   => $product->get_name(),
			'sku'    => $product->get_sku(),
			'price'  => $price,
			'status' => $product->get_status(),
		);

		$result = array(
			'id'         => (int) $id,
			'title'      => $product->get_name(),
			'price'      => $price,
			'sale_price' => $product->get_sale_price(),
			'status'     => $product->get_status(),
			'type'       => $virtual ? 'virtual' : 'simple',
			'categories' => $categories,
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, (int) $id, $data );
	}

	/**
	 * The canonical backorder setting, in WooCommerce's spelling.
	 *
	 * The three values happen to coincide. Mapped anyway, so a platform that renames one does not
	 * silently store something WooCommerce reads as "no".
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical product entity.
	 *
	 * @return string
	 */
	private function backorders( array $entity ): string {
		$value = (string) ( $entity['backorders'] ?? 'no' );

		return in_array( $value, array( 'no', 'notify', 'yes' ), true ) ? $value : 'no';
	}

	/**
	 * Record cost of goods, when this WooCommerce has the feature switched on.
	 *
	 * Wrapped because `cogs_value` is a WooCommerce 10 feature that is disabled by default, and
	 * the setter throws on a store where it is off rather than ignoring the call. The method
	 * itself always exists in the versions this supports — it is the feature flag that varies.
	 *
	 * @since 1.1.0
	 *
	 * @param \WC_Product          $product The saved product.
	 * @param array<string, mixed> $entity  Canonical product entity.
	 *
	 * @return void
	 */
	private function maybe_set_cost( \WC_Product $product, array $entity ): void {
		if ( ! isset( $entity['cost'] ) ) {
			return;
		}

		try {
			$product->set_cogs_value( (float) $this->to_decimal( (int) $entity['cost'] ) );
			$product->save();
		} catch ( \Exception $e ) {
			// The feature is off on this store. A missing cost is not worth failing a product
			// that is otherwise complete.
			return;
		}
	}

	/**
	 * File the product under existing categories.
	 *
	 * Existing ones only. Creating them here would duplicate the Product Categories generator and
	 * leave two places inventing category names; a store with none simply gets uncategorised
	 * products, which is what an empty catalogue taxonomy means.
	 *
	 * @since 1.1.0
	 *
	 * @param int $product_id The saved product.
	 * @param int $count      How many categories to file it under.
	 *
	 * @return int How many were attached.
	 */
	private function attach_categories( int $product_id, int $count ): int {
		if ( $count < 1 ) {
			return 0;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 50,
				'fields'     => 'ids',
				'exclude'    => array( (int) get_option( 'default_product_cat', 0 ) ),
			)
		);

		if ( is_wp_error( $terms ) || array() === (array) $terms ) {
			return 0;
		}

		$pick = (array) $terms;
		shuffle( $pick );
		$pick = array_slice( $pick, 0, $count );

		$set = wp_set_object_terms( $product_id, array_map( 'intval', $pick ), 'product_cat' );

		return is_wp_error( $set ) ? 0 : count( $pick );
	}

	/**
	 * One of this platform's declared fields, validated against what it allows.
	 *
	 * Validated here as well as in the REST schema because a writer can be driven from the CLI,
	 * from an MCP tool, or from a test — and a value WooCommerce does not recognise is stored
	 * without complaint and then read as empty.
	 *
	 * @since 1.1.0
	 *
	 * @param string             $name     Parameter name.
	 * @param string             $fallback Value when unset or unrecognised.
	 * @param array<int, string> $allowed  Values WooCommerce accepts.
	 *
	 * @return string
	 */
	private function platform_param( string $name, string $fallback, array $allowed ): string {
		$value = isset( $this->params[ $name ] ) ? (string) $this->params[ $name ] : $fallback;

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * How often a generated product is featured, as a percentage.
	 *
	 * Zero by default: a shop where every other product is featured is not a useful fixture, and
	 * the front page treats featured products specially.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function featured_ratio(): int {
		$ratio = isset( $this->params['featured_ratio'] ) ? (int) $this->params['featured_ratio'] : 0;

		return max( 0, min( 100, $ratio ) );
	}

	/**
	 * An SKU nothing else is using.
	 *
	 * WooCommerce enforces SKU uniqueness and `save()` throws a `WC_Data_Exception` on a
	 * duplicate, so this is not a nicety — the generator proposes and the platform that owns
	 * the constraint checks.
	 *
	 * @since 1.1.0
	 *
	 * @param string $proposed SKU the generator proposed.
	 *
	 * @return string
	 */
	private function unique_sku( string $proposed ): string {
		$sku = $proposed;

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			if ( ! wc_get_product_id_by_sku( $sku ) ) {
				return $sku;
			}

			$sku = $proposed . '-' . strtoupper( $this->faker()->bothify( '??##' ) );
		}

		return $proposed . '-' . strtoupper( substr( md5( (string) wp_generate_uuid4() ), 0, 6 ) );
	}
}
