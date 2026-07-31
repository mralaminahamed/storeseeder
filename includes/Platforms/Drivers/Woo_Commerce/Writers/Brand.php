<?php
/**
 * WooCommerce brand writer
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
 * Persists a canonical brand into WooCommerce's `product_brand` taxonomy.
 *
 * Brands are core in WooCommerce 9.6 and later. Before that they came from the Brands extension,
 * which used the same taxonomy name — so the check that matters is whether the taxonomy is
 * registered, not which WooCommerce version is installed.
 *
 * @since 1.1.0
 */
final class Brand extends Writer {
	/**
	 * The taxonomy WooCommerce keeps brands in.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const TAXONOMY = 'product_brand';

	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::BRAND;
	}

	/**
	 * Create a WooCommerce brand and attach it to products.
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
				'missing_woocommerce_brands',
				__( 'WooCommerce does not have brands registered on this site. They are core in WooCommerce 9.6 and later.', 'storeseeder' )
			);
		}

		return $this->create_brand_in(
			self::TAXONOMY,
			$entity,
			$this->product_ids( max( 1, (int) $entity['link_count'] ) )
		);
	}

	/**
	 * Remove a generated brand.
	 *
	 * The products keep existing; only the term relationship goes, which is what deleting a
	 * brand means on every platform that has them.
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
