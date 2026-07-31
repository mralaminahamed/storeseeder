<?php
/**
 * Product Variation Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

use StoreSeeder\Generation\Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Product Variation Generator Class
 *
 * Shapes product variations. A variation is a child of a product, so the writer draws
 * the parent — inventing a product id, as this generator once did with
 * numberBetween( 1, 1000 ), produces variations attached to nothing and invisible
 * everywhere.
 */
class Product_Variation extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'product_variation';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'product_variations' => __( 'Product Variations', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates product variations with attributes and pricing for testing variable product functionality.';
	}

	/**
	 * Build a canonical product variation
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		$attributes = $this->attributes();
		$manage     = $this->manage_stock();

		return array(
			// "Large / Red" — the label a shopper reads, assembled from the axes so it stays in
			// step with whichever `variation_types` were asked for.
			'title'               => implode( ' / ', array_values( $attributes ) ),
			// The axes themselves, so a platform modelling each as its own attribute can, and one
			// keeping a single label still has the label above.
			'attributes'          => $attributes,
			'stock'               => $manage ? $this->stock() : null,
			// Unmanaged stock has no quantity at all rather than a quantity of zero, which every
			// platform reads as out of stock.
			'manage_stock'        => $manage,
			// A candidate; the platform owns the unique index and re-rolls. Null asks for no SKU,
			// which is a variation identified only by its options — legal everywhere, and the case
			// a lookup by SKU has to survive.
			'sku'                 => $this->skus_enabled() ? strtoupper( $this->get_faker()->bothify( '??-#####' ) ) : null,
			// Integer minor units, and a fallback: the writer prices the variation from its parent
			// where the parent has a price, since a variation costing a quarter of the product it
			// belongs to is not a fixture anybody wants.
			'price'               => (int) round( $this->get_faker()->randomFloat( 2, 9.99, 999.99 ) * 100 ),
			// How far this variation sits from the parent's price, as a percentage — the only form
			// the generator can express it in, since only the platform knows the parent.
			'price_delta_percent' => $this->price_delta_percent(),
			'quantity'            => 1,
		);
	}

	/**
	 * The attribute axes this variation is identified by.
	 *
	 * `variation_types` and `attributes_per_product` were both declared on three surfaces and read
	 * by none: every variation came out as a size and a colour, whatever was asked for.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string> Axis name to value, e.g. `Size` => `Large`.
	 */
	private function attributes(): array {
		$pools = array(
			'size'      => array( 'Small', 'Medium', 'Large', 'X-Large', 'XX-Large', 'One Size', 'Petite', 'Tall', 'Regular', 'Slim' ),
			'color'     => array( 'Red', 'Blue', 'Black', 'White', 'Green', 'Charcoal', 'Navy', 'Sand', 'Olive', 'Burgundy' ),
			'material'  => array( 'Cotton', 'Linen', 'Leather', 'Denim', 'Wool', 'Silk', 'Canvas', 'Bamboo', 'Recycled PET', 'Merino' ),
			'style'     => array( 'Classic', 'Modern', 'Vintage', 'Minimal', 'Sport', 'Casual', 'Formal', 'Utility', 'Relaxed', 'Fitted' ),
			'flavor'    => array( 'Vanilla', 'Chocolate', 'Strawberry', 'Mint', 'Caramel', 'Espresso', 'Hazelnut', 'Lemon', 'Matcha', 'Coconut' ),
			'weight'    => array( '250g', '500g', '1kg', '2kg', '5kg', '10kg', '100g', '750g', '1.5kg', '3kg' ),
			'dimension' => array( '10cm', '20cm', '30cm', '45cm', '60cm', '75cm', '90cm', '120cm', '150cm', '180cm' ),
		);

		$requested = (array) ( $this->generation_params['variation_types'] ?? array() );
		$types     = array_values( array_intersect( array_filter( $requested, 'is_string' ), array_keys( $pools ) ) );

		if ( array() === $types ) {
			$types = array( 'size', 'color' );
		}

		$count = min( count( $types ), $this->attributes_per_product() );
		$axes  = array();

		foreach ( (array) $this->get_faker()->randomElements( $types, $count ) as $type ) {
			// `variations_per_attribute` decides how wide the vocabulary is, which is the only way
			// a generator producing one variation at a time can influence how many distinct values
			// a product ends up carrying.
			$pool = array_slice( $pools[ $type ], 0, $this->values_per_attribute() );

			$axes[ ucfirst( $type ) ] = (string) $this->get_faker()->randomElement( $pool );
		}

		return $axes;
	}

	/**
	 * How many attribute axes this variation carries.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function attributes_per_product(): int {
		$range = (array) ( $this->generation_params['attributes_per_product'] ?? array() );
		$min   = isset( $range['min'] ) ? (int) $range['min'] : 1;
		$max   = isset( $range['max'] ) ? (int) $range['max'] : 2;

		return $this->get_faker()->numberBetween( max( 1, min( $min, $max ) ), max( 1, $min, $max ) );
	}

	/**
	 * How many distinct values each axis draws from.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function values_per_attribute(): int {
		$range = (array) ( $this->generation_params['variations_per_attribute'] ?? array() );
		$min   = isset( $range['min'] ) ? (int) $range['min'] : 3;
		$max   = isset( $range['max'] ) ? (int) $range['max'] : 8;

		return max( 2, min( 10, $this->get_faker()->numberBetween( min( $min, $max ), max( $min, $max ) ) ) );
	}

	/**
	 * How far this variation's price sits from its parent's, as a percentage.
	 *
	 * @since 1.1.0
	 *
	 * @return float
	 */
	private function price_delta_percent(): float {
		$range = (array) ( $this->generation_params['price_variation_range'] ?? array() );
		$min   = isset( $range['min_percentage'] ) ? (float) $range['min_percentage'] : -20.0;
		$max   = isset( $range['max_percentage'] ) ? (float) $range['max_percentage'] : 50.0;

		return $this->get_faker()->randomFloat( 2, min( $min, $max ), max( $min, $max ) );
	}

	/**
	 * Whether this variation tracks stock.
	 *
	 * `inventory.manage_stock` is the name Products already uses for this, and the one the admin and
	 * the MCP ability sent as `stock_settings.manage_stock`. `include_inventory` was the endpoint's
	 * third name for it. All three are accepted, because two of them shipped.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	private function manage_stock(): bool {
		foreach ( array( 'inventory', 'stock_settings' ) as $group ) {
			$settings = (array) ( $this->generation_params[ $group ] ?? array() );

			if ( isset( $settings['manage_stock'] ) ) {
				return (bool) $settings['manage_stock'];
			}
		}

		if ( isset( $this->generation_params['include_inventory'] ) ) {
			return (bool) $this->generation_params['include_inventory'];
		}

		return true;
	}

	/**
	 * How much stock this variation carries.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function stock(): int {
		$range = array();

		foreach ( array( 'inventory', 'stock_settings' ) as $group ) {
			$settings = (array) ( $this->generation_params[ $group ] ?? array() );

			if ( isset( $settings['stock_range'] ) ) {
				$range = (array) $settings['stock_range'];
				break;
			}
		}

		$min = isset( $range['min'] ) ? (int) $range['min'] : 0;
		$max = isset( $range['max'] ) ? (int) $range['max'] : 100;

		return $this->get_faker()->numberBetween( max( 0, min( $min, $max ) ), max( 0, $min, $max ) );
	}

	/**
	 * Whether variations carry a SKU.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	private function skus_enabled(): bool {
		if ( ! isset( $this->generation_params['generate_skus'] ) ) {
			return true;
		}

		return (bool) $this->generation_params['generate_skus'];
	}
}
