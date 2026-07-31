<?php
/**
 * Product Tag Generator Class for StoreSeeder Plugin
 *
 * @since      1.1.0
 * @subpackage Generators
 * @package    StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

use StoreSeeder\Generation\Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Product Tag Generator Class
 *
 * Shapes product tags: a label, a slug, a description, and how many products to apply it to.
 *
 * Tags are flat where categories and brands are hierarchical, so there is no nesting here — a tag
 * inside a tag is not something any platform models, and offering the option would produce a
 * parameter that silently does nothing.
 *
 * This is the first resource one shipped driver has and the other does not: WooCommerce and
 * EasyCommerce register `product_tag`, Fluent Cart registers no tag taxonomy at all. Which is a
 * capability question, answered by the driver, not by this class.
 *
 * @since 1.1.0
 */
class Product_Tag extends Generator {

	/**
	 * Tag labels, as a shop would apply them.
	 *
	 * Attributes of a product rather than departments — the distinction that makes a tag a tag.
	 * Fixed rather than faked for the same reason the category names are: `word()` returns Lorem,
	 * and a shop tagged "voluptatem" tells a tester nothing.
	 *
	 * @var string[]
	 */
	private const LABELS = array(
		'Bestseller',
		'Eco-friendly',
		'Handmade',
		'Limited Edition',
		'Gift Idea',
		'Vegan',
		'Organic',
		'On Sale',
		'New Season',
		'Premium',
		'Waterproof',
		'Lightweight',
		'Wireless',
		'Refurbished',
		'Unisex',
		'Made Locally',
		'Travel Size',
		'Bundle',
	);

	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'product_tag';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'product_tags' => __( 'Product Tags', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates product tags and applies them to existing products, for testing tag archives, related products and faceted search.';
	}

	/**
	 * Build a canonical product tag
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		$name = $this->get_faker()->randomElement( self::LABELS );

		return array(
			'name'        => $name,
			'slug'        => sanitize_title( $name ),
			'description' => $this->get_faker()->sentence( 8 ),
			// A tag on nothing is invisible outside the tags screen, and tags are applied more
			// widely than categories — several per product is normal.
			'link_count'  => $this->products_per_tag(),
			// Flat by nature. Present and always false so the shared term writer, which handles
			// hierarchical resources too, has one shape to read rather than two.
			'nested'      => false,
		);
	}

	/**
	 * Column definitions for the preview table.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array{key: string, label: string}>
	 */
	protected function get_preview_columns(): array {
		return array(
			array(
				'key'   => 'name',
				'label' => __( 'Tag', 'storeseeder' ),
			),
			array(
				'key'   => 'slug',
				'label' => __( 'Slug', 'storeseeder' ),
			),
			array(
				'key'   => 'products',
				'label' => __( 'Products', 'storeseeder' ),
			),
		);
	}

	/**
	 * Build one representative preview row.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array{v: mixed, kind: string}>
	 */
	protected function build_preview_row(): array {
		$name = $this->get_faker()->randomElement( self::LABELS );

		return array(
			'name'     => array(
				'v'    => $name,
				'kind' => 'text',
			),
			'slug'     => array(
				'v'    => sanitize_title( $name ),
				'kind' => 'mono',
			),
			'products' => array(
				'v'    => $this->products_per_tag(),
				'kind' => 'num',
			),
		);
	}

	/**
	 * How many products each tag should be applied to.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function products_per_tag(): int {
		$requested = $this->generation_params['products_per_tag'] ?? null;

		if ( null === $requested ) {
			return $this->get_faker()->numberBetween( 2, 10 );
		}

		return max( 0, min( 50, (int) $requested ) );
	}
}
