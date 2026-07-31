<?php
/**
 * Order Tax Rate Generator Class for StoreSeeder Plugin
 *
 * @since      2.4.0
 * @subpackage Generators
 * @package    StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

use StoreSeeder\Generation\Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Order Tax Rate Generator Class
 *
 * Shapes the tax amounts on a per-order tax line. Which order and which rate the line
 * links is resolved by the platform writer, since both are existing rows.
 */
class Order_Tax_Rate extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'order_tax_rate';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'order_tax_rates' => __( 'Order Tax Lines', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates per-order tax lines linking orders to tax rates with collected tax amounts for testing tax reporting.';
	}

	/**
	 * Build a canonical order tax line
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function build_entity() {
		// Integer minor units, read back through the platform's own decimal helper.
		// Major units here would render a hundred times small.
		$order_tax    = (int) round( $this->get_faker()->randomFloat( 2, 1, 120 ) * 100 );
		$shipping_tax = (int) round( $this->get_faker()->randomFloat( 2, 0, 15 ) * 100 );

		return array(
			'order_tax'    => $order_tax,
			'shipping_tax' => $shipping_tax,
			'total_tax'    => $order_tax + $shipping_tax,
		);
	}
}
