<?php
/**
 * Shipping Class Generator Class for StoreSeeder Plugin
 *
 * @since      2.4.0
 * @subpackage Generators
 * @package    StoreSeeder
 */

namespace StoreSeeder\Generators;

use StoreSeeder\Abstracts\Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping Class Generator Class
 *
 * Shapes shipping classes used to group products with similar shipping requirements.
 */
class Shipping_Class extends Generator {


	/**
	 * Representative shipping class names.
	 *
	 * @var string[]
	 */
	private const CLASS_NAMES = array(
		'Standard',
		'Bulky Items',
		'Fragile Goods',
		'Heavy',
		'Oversized',
		'Lightweight',
		'Hazardous',
		'Refrigerated',
		'Express Only',
		'Flat Pack',
	);

	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'shipping_class';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'shipping_classes' => __( 'Shipping Classes', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates shipping classes that group products with similar shipping requirements for testing shipping rate functionality.';
	}

	/**
	 * Build a canonical shipping class
	 *
	 * The name is a candidate. Names have to be unique, and only the platform knows
	 * which are taken, so the writer is what disambiguates.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		return array(
			'name'        => $this->get_faker()->randomElement( self::CLASS_NAMES ),
			'description' => $this->get_faker()->sentence( 8 ),
			// Integer minor units, like every other money value in a canonical
			// entity. Fluent Cart's column happens to be decimal dollars, so its
			// writer converts.
			'cost'        => (int) round( $this->get_faker()->randomFloat( 2, 0, 25 ) * 100 ),
			'per_item'    => $this->get_faker()->boolean( 40 ),
		);
	}
}
