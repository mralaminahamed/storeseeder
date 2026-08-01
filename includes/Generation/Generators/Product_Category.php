<?php
/**
 * Product Category Generator Class for StoreSeeder Plugin
 *
 * @since      1.1.0
 * @subpackage Generators
 * @package    StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

use StoreSeeder\Generation\Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Product Category Generator Class
 *
 * Shapes product categories: a name, a slug, a description, how many products to file under it,
 * and whether it belongs beneath an existing category.
 *
 * Categories were a *parameter* of the products generator long before they were a resource — and
 * one that did nothing, because no writer ever read it. Making them a resource of their own is
 * what gives them a writer, and the products generator's `categories` option stays declared until
 * the unified-properties work can wire it to real terms.
 *
 * Which taxonomy they land in is the writer's business: `product_cat` on WooCommerce and
 * EasyCommerce, `product-categories` on Fluent Cart.
 *
 * @since 1.1.0
 */
class Product_Category extends Generator {

	/**
	 * Top-level category names, as a shop would list them.
	 *
	 * FakerPHP has no vocabulary for retail departments — `word()` produces Lorem — so these are
	 * fixed. A shop with categories called "voluptatem" is not a fixture anyone can read.
	 *
	 * @var string[]
	 */
	private const DEPARTMENTS = array(
		'Apparel',
		'Electronics',
		'Home & Kitchen',
		'Outdoors',
		'Beauty',
		'Books',
		'Toys & Games',
		'Sports',
		'Pet Supplies',
		'Groceries',
		'Office',
		'Automotive',
		'Garden',
		'Health',
		'Music',
	);

	/**
	 * Sub-category names, paired with a department by the writer's hierarchy rather than here.
	 *
	 * Generic on purpose: a sub-category has to read sensibly under any department, because which
	 * parent it lands under depends on what already exists — and only the platform knows that.
	 *
	 * @var string[]
	 */
	private const SUBSECTIONS = array(
		'Accessories',
		'Bestsellers',
		'Clearance',
		'Essentials',
		'Gifts',
		'New Arrivals',
		'Premium',
		'Refurbished',
		'Sale',
		'Starter Kits',
	);

	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'product_category';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'product_categories' => __( 'Product Categories', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates product categories, nested where asked, and files existing products under them for testing archives, breadcrumbs and filters.';
	}

	/**
	 * Top-level category names, from the recipe's vocabulary or the shipped default.
	 *
	 * A grocer's departments are not a boutique's, and a recipe that renamed the products but left
	 * "Electronics" and "Home & Kitchen" above them would be a half-recipe — the thing the
	 * completeness bar exists to refuse.
	 *
	 * @since 1.2.0
	 *
	 * @return string[]
	 */
	protected function departments(): array {
		return $this->vocabulary( 'product_categories', 'departments', self::DEPARTMENTS );
	}

	/**
	 * Child category names, from the recipe's vocabulary or the shipped default.
	 *
	 * @since 1.2.0
	 *
	 * @return string[]
	 */
	protected function subsections(): array {
		return $this->vocabulary( 'product_categories', 'subsections', self::SUBSECTIONS );
	}

	/**
	 * Build a canonical product category
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		$nested = $this->get_faker()->boolean( $this->nested_ratio() );
		$name   = $this->get_faker()->randomElement(
			$nested ? $this->subsections() : $this->departments()
		);

		return array(
			'name'        => $name,
			'slug'        => sanitize_title( $name ),
			'description' => $this->get_faker()->sentence( 10 ),
			// A category with no products is an empty archive — worth generating deliberately,
			// not by accident, which is why the count is on the entity.
			'link_count'  => $this->products_per_category(),
			// Category trees are what break breadcrumbs and filtered queries, so a share of
			// these are children. The writer picks the parent, because only it knows what
			// exists.
			'nested'      => $nested,
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
				'label' => __( 'Category', 'storeseeder' ),
			),
			array(
				'key'   => 'slug',
				'label' => __( 'Slug', 'storeseeder' ),
			),
			array(
				'key'   => 'products',
				'label' => __( 'Products', 'storeseeder' ),
			),
			array(
				'key'   => 'nested',
				'label' => __( 'Sub-category', 'storeseeder' ),
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
		$faker  = $this->get_faker();
		$nested = $faker->boolean( $this->nested_ratio() );
		$name   = $nested
			? $faker->randomElement( $this->subsections() )
			: $faker->randomElement( $this->departments() );

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
				'v'    => $this->products_per_category(),
				'kind' => 'num',
			),
			'nested'   => array(
				'v'    => $nested ? __( 'yes', 'storeseeder' ) : __( 'no', 'storeseeder' ),
				'kind' => 'text',
			),
		);
	}

	/**
	 * How many products each category should hold.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function products_per_category(): int {
		$requested = $this->generation_params['products_per_category'] ?? null;

		if ( null === $requested ) {
			return $this->get_faker()->numberBetween( 1, 8 );
		}

		return max( 0, min( 30, (int) $requested ) );
	}

	/**
	 * How often a category is created beneath an existing one, as a percentage.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function nested_ratio(): int {
		$requested = $this->generation_params['nested_ratio'] ?? 35;

		return max( 0, min( 100, (int) $requested ) );
	}
}
