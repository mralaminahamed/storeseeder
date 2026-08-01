<?php
/**
 * Brand Generator Class for StoreSeeder Plugin
 *
 * @since      1.1.0
 * @subpackage Generators
 * @package    StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

use StoreSeeder\Generation\Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Brand Generator Class
 *
 * Shapes product brands: a name, a slug, a description, and how many products the brand should
 * end up on. Every platform that has brands stores them as a taxonomy, so a brand attached to
 * nothing is invisible outside the taxonomy screen — which is why `link_count` is part of the
 * entity rather than an afterthought, the same reasoning the Attributes generator uses.
 *
 * Which taxonomy it lands in is the writer's business: `product_brand` on WooCommerce and
 * EasyCommerce, `product-brands` on Fluent Cart. The hyphen is exactly the kind of difference
 * this class must not know about.
 *
 * @since 1.1.0
 */
class Brand extends Generator {

	/**
	 * Word stems that read like brand names once combined.
	 *
	 * FakerPHP's `company()` returns things like "Bauch, Kessler and Sons", which reads as a
	 * supplier rather than a brand on a product page. These are shorter and look like labels a
	 * store would actually carry.
	 *
	 * @var string[]
	 */
	private const STEMS = array(
		'Aura',
		'Nord',
		'Vertex',
		'Lumen',
		'Onyx',
		'Ridge',
		'Solace',
		'Aster',
		'Cobalt',
		'Harbour',
		'Juniper',
		'Meridian',
		'Quill',
		'Slate',
		'Thistle',
		'Wilder',
	);

	/**
	 * Suffixes that turn a stem into a brand.
	 *
	 * @var string[]
	 */
	private const SUFFIXES = array(
		'& Co',
		'Supply',
		'Studio',
		'Works',
		'Goods',
		'Collective',
		'Atelier',
		'Labs',
		'',
	);

	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'brand';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'brands' => __( 'Brands', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates product brands and attaches them to existing products, for testing brand archives, filters and product pages.';
	}

	/**
	 * Build a canonical brand
	 *
	 * The name is a candidate: brand names are unique within their taxonomy, and only the
	 * platform knows which are taken, so the writer disambiguates. Same for the parent — every
	 * platform's brand taxonomy is hierarchical, and which brand could be a parent depends on
	 * what already exists.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		$name = $this->brand_name();

		return array(
			'name'        => $name,
			'slug'        => sanitize_title( $name ),
			'description' => $this->get_faker()->sentence( 10 ),
			// A brand on no products is invisible everywhere except the taxonomy screen, so
			// the entity says how many to reach. The writer finds them.
			'link_count'  => $this->products_per_brand(),
			// Brand taxonomies are hierarchical on all three platforms that have them, and a
			// sub-brand is a case worth having in the fixture. The writer decides which
			// existing brand becomes the parent, because it is the only thing that knows.
			'nested'      => $this->get_faker()->boolean( $this->nested_ratio() ),
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
				'label' => __( 'Brand', 'storeseeder' ),
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
				'label' => __( 'Sub-brand', 'storeseeder' ),
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
		$faker = $this->get_faker();
		$name  = $this->brand_name();

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
				'v'    => $faker->numberBetween( 1, 5 ),
				'kind' => 'num',
			),
			'nested'   => array(
				'v'    => $faker->boolean( 25 ) ? __( 'yes', 'storeseeder' ) : __( 'no', 'storeseeder' ),
				'kind' => 'text',
			),
		);
	}

	/**
	 * How many products each brand should end up on.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function products_per_brand(): int {
		$requested = $this->generation_params['products_per_brand'] ?? null;

		if ( null === $requested ) {
			return $this->get_faker()->numberBetween( 1, 5 );
		}

		return max( 0, min( 20, (int) $requested ) );
	}

	/**
	 * How often a brand is created as a sub-brand, as a percentage.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function nested_ratio(): int {
		$requested = $this->generation_params['nested_ratio'] ?? 25;

		return max( 0, min( 100, (int) $requested ) );
	}

	/**
	 * A brand name, built from a stem and an optional suffix.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function brand_name(): string {
		$stem   = $this->get_faker()->randomElement( $this->vocabulary( 'brands', 'stems', self::STEMS ) );
		$suffix = $this->get_faker()->randomElement( $this->vocabulary( 'brands', 'suffixes', self::SUFFIXES ) );

		return '' === $suffix ? $stem : $stem . ' ' . $suffix;
	}
}
