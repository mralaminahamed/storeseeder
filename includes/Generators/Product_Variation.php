<?php
/**
 * Product Variation Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder
 */

namespace StoreSeeder\Generators;

use StoreSeeder\Abstracts\Generator;

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
		// A simple product legitimately carries several variations as selectable price
		// points; a size/colour label reads as a real option.
		$size  = $this->get_faker()->randomElement( array( 'Small', 'Medium', 'Large', 'X-Large' ) );
		$color = $this->get_faker()->randomElement( array( 'Red', 'Blue', 'Black', 'White', 'Green' ) );

		$stock = $this->get_faker()->numberBetween( 0, 100 );

		return array(
			'title'    => $size . ' / ' . $color,
			'stock'    => $stock,
			// A candidate; the platform owns the unique index and re-rolls.
			'sku'      => strtoupper( $this->get_faker()->bothify( '??-#####' ) ),
			// Integer minor units.
			'price'    => (int) round( $this->get_faker()->randomFloat( 2, 9.99, 999.99 ) * 100 ),
			'quantity' => 1,
		);
	}
}
