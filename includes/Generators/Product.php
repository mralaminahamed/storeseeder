<?php
/**
 * Product Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder
 */

namespace StoreSeeder\Generators;

use StoreSeeder\Abstracts\Generator;
use StoreSeeder\Platform\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Product Generator Class
 *
 * Shapes realistic product data. Persisting it is a platform writer's job — see
 * StoreSeeder\Platforms\Fluent_Cart\Writers\Product.
 */
class Product extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'product';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'products' => 'Fluent Cart Products',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates comprehensive product data with pricing, inventory, categories, and attributes for testing Fluent Cart functionality.';
	}

	/**
	 * Build a canonical product
	 *
	 * FakerPHP and sample data only. The SKU here is a *candidate*: the unique index
	 * that makes it matter lives in the platform, so the writer is what checks it and
	 * re-rolls on a collision.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed> Canonical product entity.
	 */
	protected function build_entity() {
		$sample_data = $this->load_sample_data();
		$adjectives  = $sample_data['adjectives'] ?? array( 'Amazing', 'Premium', 'Deluxe', 'Professional' );
		$products    = $sample_data['products'] ?? array( 'Widget', 'Gadget', 'Tool', 'Device', 'System' );

		$title = $this->get_faker()->randomElement( $adjectives ) . ' ' . $this->get_faker()->randomElement( $products );

		$fulfillment_type = $this->get_faker()->randomElement( array( 'physical', 'digital' ) );

		return array(
			'title'            => $title,
			'description'      => $this->get_faker()->paragraphs( 3, true ),
			// Integer minor units. Fluent Cart stores cents directly; platforms that
			// want decimals divide in their own writer.
			'price'            => (int) round( $this->get_faker()->randomFloat( 2, 9.99, 999.99 ) * 100 ),
			'status'           => Status::PUBLISHED,
			'sku'              => strtoupper( $this->get_faker()->bothify( '??-#####' ) ),
			'stock'            => $this->get_faker()->numberBetween( 0, 100 ),
			'fulfillment_type' => $fulfillment_type,
		);
	}

	/**
	 * Preview columns for products
	 *
	 * @since 1.0.1
	 *
	 * @return array<int, array{key: string, label: string}>
	 */
	protected function get_preview_columns(): array {
		return array(
			array(
				'key'   => 'name',
				'label' => __( 'Name', 'storeseeder' ),
			),
			array(
				'key'   => 'sku',
				'label' => __( 'SKU', 'storeseeder' ),
			),
			array(
				'key'   => 'price',
				'label' => __( 'Price', 'storeseeder' ),
			),
			array(
				'key'   => 'stock',
				'label' => __( 'Stock', 'storeseeder' ),
			),
			array(
				'key'   => 'status',
				'label' => __( 'Status', 'storeseeder' ),
			),
		);
	}

	/**
	 * Build a product preview row
	 *
	 * @since 1.0.1
	 *
	 * @return array<string, array{v: mixed, kind: string}>
	 */
	protected function build_preview_row(): array {
		$faker = $this->get_faker();

		return array(
			'name'   => array(
				// words() without the $asText flag returns an array, which joins
				// cleanly — asking for the string form types as array|string.
				'v'    => ucwords( implode( ' ', (array) $faker->words( 3 ) ) ),
				'kind' => 'text',
			),
			'sku'    => array(
				'v'    => 'SKU-' . $faker->numberBetween( 1000, 9999 ),
				'kind' => 'mono',
			),
			'price'  => array(
				'v'    => '$' . number_format( $faker->randomFloat( 2, 5, 500 ), 2 ),
				'kind' => 'money',
			),
			'stock'  => array(
				'v'    => $faker->numberBetween( 0, 250 ),
				'kind' => 'num',
			),
			'status' => array(
				'v'    => $faker->randomElement( array( 'publish', 'draft', 'pending' ) ),
				'kind' => 'status',
			),
		);
	}
}
