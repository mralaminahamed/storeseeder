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
 * Persists a canonical product category into WooCommerce's `product_cat` taxonomy.
 *
 * `product_cat`, not `product_category`: WooCommerce shortened it, and the full name belongs to
 * nothing — a writer using it would create terms in a taxonomy no WooCommerce screen reads.
 *
 * @since 1.1.0
 */
final class Product_Category extends Writer {
	/**
	 * The taxonomy WooCommerce keeps product categories in.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const TAXONOMY = 'product_cat';

	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::PRODUCT_CATEGORY;
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
				__( 'WooCommerce is not active on this site, so its product category taxonomy is not registered.', 'storeseeder' )
			);
		}

		return $this->create_term_in(
			self::TAXONOMY,
			$entity,
			$this->product_ids( max( 1, (int) $entity['link_count'] ) )
		);
	}

	/**
	 * Remove a generated category.
	 *
	 * The products keep existing; only the term relationship goes. WordPress reassigns anything
	 * left uncategorised on its own, so nothing is orphaned.
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
