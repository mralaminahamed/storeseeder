<?php
/**
 * Attribute Generator.
 *
 * @since   1.0.0
 * @package FluentCartFakerPress\Generators
 */

namespace FluentCartFakerPress\Generators;

defined( 'ABSPATH' ) || exit;

use FluentCart\App\Models\AttributeGroup;
use FluentCart\App\Models\AttributeTerm;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

/**
 * Generates Fluent Cart product attribute groups with terms.
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
		return array( 'attributes' => __( 'Product Attribute Groups with Terms', 'fluent-cart-fakerpress' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Generates Fluent Cart attribute groups (Color, Size, Material, etc.) each with a set of terms for testing product variation functionality.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function generate_single_item() {
		if ( ! defined( 'FLUENT_CART_VERSION' ) || ! class_exists( AttributeGroup::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart attribute models not found. Ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		$set_names = array_keys( self::ATTRIBUTE_SETS );
		$base_name = $this->get_faker()->randomElement( $set_names );
		$title     = $base_name . ' ' . $this->get_faker()->numerify( '###' );
		$slug      = sanitize_title( $title );

		$group = AttributeGroup::create(
			array(
				'title'       => $title,
				'slug'        => $slug,
				'description' => $this->get_faker()->sentence( 8 ),
				'settings'    => array(),
				'serial'      => $this->get_faker()->numberBetween( 1, 999 ),
			)
		);

		if ( ! $group || ! $group->id ) {
			return new WP_Error( 'attribute_creation_failed', __( 'Failed to create attribute group.', 'fluent-cart-fakerpress' ) );
		}

		$all_terms = self::ATTRIBUTE_SETS[ $base_name ];
		$count     = $this->get_faker()->numberBetween( 3, count( $all_terms ) );
		$selected  = $this->get_faker()->randomElements( $all_terms, $count, false );
		$values    = array();

		foreach ( $selected as $i => $label ) {
			$term = AttributeTerm::create(
				array(
					'group_id'    => $group->id,
					'serial'      => $i + 1,
					'title'       => $label,
					'slug'        => sanitize_title( $title . '-' . $label ),
					'description' => '',
					'settings'    => array(),
				)
			);
			if ( $term && $term->id ) {
				$values[] = array(
					'id'    => (int) $term->id,
					'label' => $label,
				);
			}
		}

		return array(
			'id'     => (int) $group->id,
			'name'   => $title,
			'slug'   => $slug,
			'values' => $values,
		);
	}
}
