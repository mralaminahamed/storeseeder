<?php
/**
 * Fluent Cart product category writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use FluentCart\App\Models\Product as ProductModel;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Platforms\Writer;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical product category into Fluent Cart's `product-categories` taxonomy.
 *
 * Hyphenated and plural, where WooCommerce uses `product_cat` and EasyCommerce `product_cat`
 * too — three spellings of one idea, which is precisely the knowledge that belongs in a writer
 * and nowhere above it.
 *
 * @since 1.1.0
 */
final class Product_Category extends Writer {
	/**
	 * The taxonomy Fluent Cart keeps product categories in.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const TAXONOMY = 'product-categories';

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
	 * Create a Fluent Cart product category and file products under it.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical category entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! taxonomy_exists( self::TAXONOMY ) ) {
			return new WP_Error(
				'missing_fluent_cart',
				__( 'Fluent Cart is not active on this site, so its product category taxonomy is not registered.', 'storeseeder' )
			);
		}

		return $this->create_term_in( self::TAXONOMY, $entity, $this->product_ids( (int) $entity['link_count'] ) );
	}

	/**
	 * Remove a generated category.
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

	/**
	 * Ids of existing Fluent Cart products.
	 *
	 * A Fluent Cart product is a post of its own type, so the ids are post ids — which is what
	 * `wp_set_object_terms()` needs.
	 *
	 * @since 1.1.0
	 *
	 * @param int $limit How many to return.
	 *
	 * @return array<int, int>
	 */
	private function product_ids( int $limit ): array {
		$products = ProductModel::query()
			->select( array( 'ID' ) )
			->inRandomOrder()
			->limit( max( 1, $limit ) )
			->get();

		$ids = array();

		foreach ( $products as $product ) {
			$ids[] = (int) $product->ID;
		}

		return $ids;
	}
}
