<?php
/**
 * WooCommerce product variation writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical product variation into WooCommerce.
 *
 * A variation cannot exist alone: WooCommerce requires a **variable** parent carrying an
 * attribute marked "used for variations", and a variation whose attribute value is empty is
 * treated as a catch-all that hides its siblings. So this writer resolves a parent first, and
 * promotes a simple product when the store has no variable one — the same kind of
 * read-then-write a writer is allowed to do for a foreign key, and the reason the FK rules sit
 * with the writer rather than the generator.
 *
 * The attribute is local to the product rather than a global `pa_` taxonomy. A global one
 * would make every generated variation share a term list with every other, which is a
 * different fixture than the one asked for.
 *
 * @since 1.1.0
 */
final class Product_Variation extends Writer {
	/**
	 * The product-level attribute variations vary on.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const ATTRIBUTE = 'Variant';

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
	 * Create a WooCommerce product variation.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical variation entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! class_exists( 'WC_Product_Variation' ) ) {
			return new WP_Error(
				'missing_woocommerce',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$parent = $this->parent_product();

		if ( is_wp_error( $parent ) ) {
			return $parent;
		}

		// The value this variation is identified by, e.g. "L / Red". Registering it on the
		// parent's attribute is what makes the variation selectable.
		$value = (string) $entity['title'];
		$this->register_option( $parent, $value );

		$stock     = (int) $entity['stock'];
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_props(
			array(
				'status'         => 'publish',
				'sku'            => $this->unique_sku( (string) $entity['sku'] ),
				'regular_price'  => $this->to_decimal( (int) $entity['price'] ),
				'manage_stock'   => true,
				'stock_quantity' => $stock,
				'stock_status'   => $stock > 0 ? 'instock' : 'outofstock',
			)
		);
		$variation->set_attributes( array( sanitize_title( self::ATTRIBUTE ) => $value ) );

		$id = $variation->save();

		if ( ! $id ) {
			return new WP_Error( 'variation_creation_failed', __( 'Failed to create the product variation.', 'storeseeder' ) );
		}

		// Prices are cached per parent; without this the new variation's price is absent from
		// the range shown on the shop page until something else clears the transient.
		WC_Product_Variable::sync( $parent->get_id() );

		$data = array(
			'id'        => (int) $id,
			'parent_id' => $parent->get_id(),
			'value'     => $value,
		);

		$result = array(
			'id'         => (int) $id,
			'product'    => $parent->get_name(),
			'product_id' => $parent->get_id(),
			'title'      => $value,
			'sku'        => $variation->get_sku(),
			'price'      => $variation->get_regular_price(),
			'stock'      => $stock,
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, (int) $id, $data );
	}

	/**
	 * Remove a generated variation.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id Variation id.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		$variation = wc_get_product( (int) $id );
		$parent_id = $variation instanceof WC_Product ? $variation->get_parent_id() : 0;

		$deleted = $this->delete_crud( $id );

		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		// The parent's price range was cached with this variation in it.
		if ( $parent_id > 0 && class_exists( 'WC_Product_Variable' ) ) {
			WC_Product_Variable::sync( $parent_id );
		}

		return true;
	}

	/**
	 * A variable product to attach the variation to.
	 *
	 * Prefers one that already exists; promotes a simple product when none does. Promotion is
	 * bookkeeping rather than invention — the same product, now able to hold variations — and
	 * it is what lets this generator work on a store seeded only with simple products.
	 *
	 * @since 1.1.0
	 *
	 * @return WC_Product|WP_Error
	 */
	private function parent_product() {
		$variable = $this->random_product( 'variable' );

		if ( null !== $variable ) {
			return $variable;
		}

		$simple = $this->random_product( 'simple' );

		if ( null === $simple ) {
			return $this->missing_prerequisite(
				'no_products',
				__( 'No products were found. Generate products before generating variations.', 'storeseeder' )
			);
		}

		$promoted = new WC_Product_Variable( $simple->get_id() );
		$promoted->save();

		return $promoted;
	}

	/**
	 * Make sure the parent's attribute exists and lists this value.
	 *
	 * WooCommerce matches a variation to its parent by attribute *name*, and shows the
	 * dropdown from the parent's option list — so a value missing from that list produces a
	 * variation the shop cannot select.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Product $variable The variable product.
	 * @param string     $value    The option this variation is identified by.
	 *
	 * @return void
	 */
	private function register_option( WC_Product $variable, string $value ): void {
		$attributes = $variable->get_attributes();
		$key        = sanitize_title( self::ATTRIBUTE );
		$options    = array();

		if ( isset( $attributes[ $key ] ) ) {
			$options = $attributes[ $key ]->get_options();
		}

		if ( in_array( $value, $options, true ) ) {
			return;
		}

		$options[] = $value;

		$attribute = new WC_Product_Attribute();
		$attribute->set_name( self::ATTRIBUTE );
		$attribute->set_options( $options );
		$attribute->set_position( 0 );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$attributes[ $key ] = $attribute;

		$variable->set_attributes( $attributes );
		$variable->save();
	}

	/**
	 * An SKU nothing else is using.
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
