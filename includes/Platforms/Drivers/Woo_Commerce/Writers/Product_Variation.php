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

		// One WooCommerce attribute per axis, which is what `variation_types` asks for: a
		// size-and-colour variation belongs under a Size attribute and a Colour attribute, not
		// under one called "Variant" holding "Large / Red" as a single option. Falls back to the
		// old single axis for an entity that carries no map.
		$axes = array_filter( (array) ( $entity['attributes'] ?? array() ), 'is_string' );

		if ( array() === $axes ) {
			$axes = array( self::ATTRIBUTE => $value );
		}

		$axes  = $this->align_axes( $parent, $axes );
		$value = implode( ' / ', array_values( $axes ) );

		$this->register_options( $parent, $axes );

		$manage = ! isset( $entity['manage_stock'] ) || (bool) $entity['manage_stock'];
		$stock  = $manage ? (int) $entity['stock'] : null;
		$props  = array(
			'status'        => 'publish',
			'regular_price' => $this->to_decimal( $this->variation_price( $parent, $entity ) ),
			'manage_stock'  => $manage,
		);

		if ( $manage ) {
			$props['stock_quantity'] = $stock;
			$props['stock_status']   = $stock > 0 ? 'instock' : 'outofstock';
		} else {
			// Unmanaged: no quantity at all, and in stock, which is what a variation with no
			// inventory tracking means. A quantity of zero would read as sold out.
			$props['stock_status'] = 'instock';
		}

		// Null asks for no SKU, and WooCommerce spells that as an empty string.
		$props['sku'] = null === ( $entity['sku'] ?? null ) ? '' : $this->unique_sku( (string) $entity['sku'] );

		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_props( $props );

		$attributes = array();

		foreach ( $axes as $axis => $option ) {
			$attributes[ sanitize_title( (string) $axis ) ] = (string) $option;
		}

		$variation->set_attributes( $attributes );

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
		// A requested product wins, so a run can build out one product's option matrix — the
		// fixture a variable-product screen needs, which a random spread never produces.
		$requested = (int) ( $this->params['product_id'] ?? 0 );

		if ( $requested > 0 ) {
			$product = wc_get_product( $requested );

			if ( ! $product instanceof WC_Product ) {
				return $this->missing_prerequisite(
					'no_products',
					__( 'The requested product was not found.', 'storeseeder' )
				);
			}

			// Promoted where it is not already variable, since a simple product cannot hold one.
			if ( 'variable' === $product->get_type() ) {
				return $product;
			}

			$promoted = new WC_Product_Variable( $product->get_id() );
			$promoted->save();

			return $promoted;
		}

		$variable = $this->eligible_product( 'variable' );

		if ( null !== $variable ) {
			return $variable;
		}

		$simple = $this->eligible_product( 'simple' );

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
	 * One product of a type, skipping any the run excluded.
	 *
	 * @since 1.1.0
	 *
	 * @param string $type WooCommerce product type.
	 *
	 * @return WC_Product|null
	 */
	private function eligible_product( string $type ): ?WC_Product {
		$excluded = array_map( 'intval', (array) ( $this->params['exclude_product_ids'] ?? array() ) );

		if ( array() === $excluded ) {
			return $this->random_product( $type );
		}

		// Drawn from a wider set and filtered, rather than re-rolled until something sticks: a run
		// excluding most of a small catalogue would otherwise loop.
		foreach ( $this->product_ids( 50, $type ) as $id ) {
			if ( in_array( (int) $id, $excluded, true ) ) {
				continue;
			}

			$product = wc_get_product( (int) $id );

			if ( $product instanceof WC_Product ) {
				return $product;
			}
		}

		return null;
	}

	/**
	 * Line this variation's axes up with the ones its parent already has.
	 *
	 * The first variation on a product establishes the axes; every later one fills the same set. An
	 * unfilled axis is not an error in WooCommerce — it means "any size" — but a product whose
	 * variations each specify a different subset shows a dropdown per axis with half the options
	 * matching anything, which is a confusing fixture rather than a realistic one. A value for an
	 * axis this variation did not bring is drawn from what the parent already offers, since
	 * inventing one is the generator's job and not this writer's.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Product            $variable The variable product.
	 * @param array<string, string> $axes     Axes the entity carried.
	 *
	 * @return array<string, string>
	 */
	private function align_axes( WC_Product $variable, array $axes ): array {
		$existing = array();

		foreach ( $variable->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->get_variation() ) {
				continue;
			}

			$options = $attribute->get_options();

			if ( array() !== $options ) {
				$existing[ $attribute->get_name() ] = $options;
			}
		}

		if ( array() === $existing ) {
			return $axes;
		}

		$aligned = array();

		foreach ( $existing as $name => $options ) {
			$aligned[ $name ] = isset( $axes[ $name ] )
				? (string) $axes[ $name ]
				: (string) $options[ array_rand( $options ) ];
		}

		return $aligned;
	}

	/**
	 * Make sure the parent carries each axis and lists this variation's value on it.
	 *
	 * WooCommerce matches a variation to its parent by attribute *name*, and shows the dropdown
	 * from the parent's option list — so a value missing from that list produces a variation the
	 * shop cannot select. One attribute per axis, so a size-and-colour variation gets a Size
	 * dropdown and a Colour dropdown rather than one holding "Large / Red".
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Product            $variable The variable product.
	 * @param array<string, string> $axes     Attribute name to the option this variation carries.
	 *
	 * @return void
	 */
	private function register_options( WC_Product $variable, array $axes ): void {
		$attributes = $variable->get_attributes();
		$changed    = false;
		$position   = count( $attributes );

		foreach ( $axes as $name => $value ) {
			$key     = sanitize_title( (string) $name );
			$options = isset( $attributes[ $key ] ) ? $attributes[ $key ]->get_options() : array();

			if ( in_array( (string) $value, $options, true ) ) {
				continue;
			}

			$options[] = (string) $value;

			$attribute = new WC_Product_Attribute();
			$attribute->set_name( (string) $name );
			$attribute->set_options( $options );
			$attribute->set_position( isset( $attributes[ $key ] ) ? $attributes[ $key ]->get_position() : $position++ );
			$attribute->set_visible( true );
			$attribute->set_variation( true );

			$attributes[ $key ] = $attribute;
			$changed            = true;
		}

		// Saving the parent on every variation is a write per item for nothing; only a new option
		// changes anything.
		if ( ! $changed ) {
			return;
		}

		$variable->set_attributes( $attributes );
		$variable->save();
	}

	/**
	 * What this variation costs, in minor units.
	 *
	 * `price_variation_range` asks for a percentage of the *parent's* price, which only the platform
	 * knows — so the entity carries the percentage and this applies it. A parent with no price of
	 * its own leaves the variation on the generated fallback, since a percentage of nothing is
	 * nothing and a free variation is not what was asked for.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Product           $variable The variable product this belongs to.
	 * @param array<string, mixed> $entity   Canonical variation entity.
	 *
	 * @return int
	 */
	private function variation_price( WC_Product $variable, array $entity ): int {
		// `$parent` is a reserved word to WPCS, which is why this reads `$variable` like the
		// method above it.
		$base = (string) $variable->get_regular_price();

		// A variable product has no price of its own — its price *is* its variations, exactly as on
		// Fluent Cart — so the base is the cheapest one already on it. Reading only
		// `get_regular_price()` meant the percentage silently never applied, because promotion to
		// variable happens before this and empties that field.
		if ( ( '' === $base || (float) $base <= 0 ) && $variable instanceof WC_Product_Variable ) {
			$base = (string) $variable->get_variation_regular_price( 'min' );
		}

		if ( '' === $base || (float) $base <= 0 ) {
			return (int) $entity['price'];
		}

		$delta = (float) ( $entity['price_delta_percent'] ?? 0 );
		$price = (int) round( (float) $base * 100 * ( 1 + $delta / 100 ) );

		// A variation is never free, whatever percentage was asked for: WooCommerce treats a zero
		// price as one, and a shop full of free variations is a broken fixture rather than a cheap
		// one.
		return max( 1, $price );
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
