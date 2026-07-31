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

		$price   = $this->to_decimal( (int) $entity['price'] );
		$virtual = 'digital' === ( $entity['fulfillment_type'] ?? 'physical' );
		$stock   = (int) $entity['stock'];

		$product = new WC_Product_Simple();
		$product->set_props(
			array(
				'name'               => $entity['title'],
				'status'             => $this->map_post_status( (string) $entity['status'] ),
				'description'        => $entity['description'],
				'short_description'  => wp_trim_words( (string) $entity['description'], 24 ),
				'sku'                => $this->unique_sku( (string) $entity['sku'] ),
				'regular_price'      => $price,
				'virtual'            => $virtual,
				// A digital product is downloadable only once it has a file, which is the
				// Product Downloads generator's job. Marking it downloadable with no file
				// produces a product WooCommerce refuses to let anyone buy.
				'downloadable'       => false,
				'manage_stock'       => true,
				'stock_quantity'     => $stock,
				'stock_status'       => $stock > 0 ? 'instock' : 'outofstock',
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
			'status'     => $product->get_status(),
			'type'       => $virtual ? 'virtual' : 'simple',
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, (int) $id, $data );
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
