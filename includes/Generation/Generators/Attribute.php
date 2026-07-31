<?php
/**
 * Attribute Generator.
 *
 * @since   1.0.0
 * @package StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

defined( 'ABSPATH' ) || exit;

use StoreSeeder\Generation\Generator;

/**
 * Shapes product attribute groups with their terms.
 *
 * @since 1.0.0
 */
class Attribute extends Generator {

	/**
	 * Predefined attribute sets mapping a group name to representative terms.
	 *
	 * @var array<string, string[]>
	 */
	private const ATTRIBUTE_SETS = array(
		'Color'    => array( 'Red', 'Blue', 'Green', 'Black', 'White', 'Yellow', 'Purple', 'Orange', 'Pink', 'Gray' ),
		'Size'     => array( 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL' ),
		'Material' => array( 'Cotton', 'Polyester', 'Wool', 'Silk', 'Leather', 'Denim', 'Linen', 'Nylon' ),
		'Storage'  => array( '64GB', '128GB', '256GB', '512GB', '1TB', '2TB' ),
		'Style'    => array( 'Classic', 'Modern', 'Vintage', 'Sport', 'Casual', 'Formal' ),
		'Pattern'  => array( 'Solid', 'Striped', 'Plaid', 'Polka Dot', 'Floral', 'Geometric' ),
	);

	/**
	 * {@inheritDoc}
	 */
	protected function get_resource_type(): string {
		return 'attribute';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_supported_types(): array {
		return array( 'attributes' => __( 'Product Attribute Groups with Terms', 'storeseeder' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Generates attribute groups (Color, Size, Material, etc.) each with a set of terms for testing product variation functionality.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * `link_count` is how many product variations the group should be bound to. The
	 * number is generated data; finding the variations is not, so the writer does that
	 * part.
	 */
	protected function build_entity() {
		$set_names = array_keys( self::ATTRIBUTE_SETS );
		$base_name = $this->get_faker()->randomElement( $set_names );
		$title     = $base_name . ' ' . $this->get_faker()->numerify( '###' );

		$all_terms = self::ATTRIBUTE_SETS[ $base_name ];

		return array(
			'title'       => $title,
			'slug'        => sanitize_title( $title ),
			'description' => $this->get_faker()->sentence( 8 ),
			'serial'      => $this->get_faker()->numberBetween( 1, 999 ),
			'terms'       => $this->get_faker()->randomElements(
				$all_terms,
				$this->get_faker()->numberBetween( 3, count( $all_terms ) ),
				false
			),
			'link_count'  => $this->get_faker()->numberBetween( 2, 6 ),
		);
	}
}
