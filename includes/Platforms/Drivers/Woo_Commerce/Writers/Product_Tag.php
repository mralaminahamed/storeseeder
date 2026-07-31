<?php
/**
 * WooCommerce category writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical product tag into WooCommerce's `product_tag` taxonomy.
 *
 * Unlike the category and brand taxonomies, this one has no Fluent Cart counterpart — that driver
 * reports tags unsupported rather than shipping a writer. So this file is the whole of tag support
 * until another platform with a tag taxonomy gets a driver.
 *
 * @since 1.1.0
 */
final class Product_Tag extends Writer {
	/**
	 * The taxonomy WooCommerce keeps product tags in.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const TAXONOMY = 'product_tag';

	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::PRODUCT_TAG;
	}

	/**
	 * Create a WooCommerce product category and attach it to products.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical brand entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return new WP_Error(
				'missing_woocommerce',
				__( 'WooCommerce is not active on this site, so its product tag taxonomy is not registered.', 'storeseeder' )
			);
		}

		return $this->create_term_in(
			self::TAXONOMY,
			$entity,
			$this->product_ids( max( 1, (int) $entity['link_count'] ) )
		);
	}

	/**
	 * Remove a generated tag.
	 *
	 * The products keep existing; only the term relationship goes. A product with no tags is a
	 * normal product, so unlike a category there is nothing to reassign.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id Term id.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		return $this->delete_term( self::TAXONOMY, $id );
	}
}
