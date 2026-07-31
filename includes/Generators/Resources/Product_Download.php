<?php
/**
 * Product Download Generator Class for StoreSeeder Plugin
 *
 * @since      2.4.0
 * @subpackage Generators
 * @package    StoreSeeder\Generators\Resources
 */

namespace StoreSeeder\Generators\Resources;

use StoreSeeder\Generators\Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Product Download Generator Class
 *
 * Shapes downloadable files. Which product the file hangs off, and which order gets a
 * permission for it, are existing rows the writer draws.
 */
class Product_Download extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'product_download';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'product_downloads' => __( 'Product Downloads', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates downloadable files for products and grants download permissions on existing orders for testing digital fulfillment.';
	}

	/**
	 * Build a canonical product download
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		$slug = $this->get_faker()->slug( 3 );

		return array(
			// A candidate: the unique index on it belongs to the platform, so the
			// writer is what checks and re-rolls.
			'download_identifier' => md5( $this->get_faker()->unique()->uuid() ),
			'title'               => ucwords( str_replace( '-', ' ', $slug ) ),
			'type'                => 'file',
			'file_name'           => $slug . '.zip',
			'file_url'            => 'https://downloads.example.test/' . $slug . '.zip',
			'file_size'           => (string) $this->get_faker()->numberBetween( 51200, 52428800 ),
			// Null would mean unlimited; a small cap is more typical of a real store.
			'download_limit'      => $this->get_faker()->numberBetween( 1, 5 ),
		);
	}
}
