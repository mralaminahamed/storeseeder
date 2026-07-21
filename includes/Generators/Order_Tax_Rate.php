<?php
/**
 * Order Tax Rate Generator Class for Fluent Cart FakerPress Plugin
 *
 * @since      2.4.0
 * @subpackage Generators
 * @package    FluentCartFakerPress
 */

namespace FluentCartFakerPress\Generators;

use FluentCart\App\Models\Order as OrderModel;
use FluentCart\App\Models\OrderTaxRate as OrderTaxRateModel;
use FluentCart\App\Models\TaxRate as TaxRateModel;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Order Tax Rate Generator Class
 *
 * Generates per-order tax lines that link an order to a tax rate with the tax
 * amounts collected, for testing Fluent Cart tax reporting.
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
			'order_tax_rates' => 'Fluent Cart Order Tax Lines',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates per-order tax lines linking orders to tax rates with collected tax amounts for testing Fluent Cart tax reporting.';
	}

	/**
	 * Generate a single order tax line
	 *
	 * @return WP_Error|array Single order tax line data, error, or false on failure.
	 */
	protected function generate_single_item() {
		if ( ! defined( 'FLUENTCART_VERSION' ) || ! class_exists( OrderTaxRateModel::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart OrderTaxRate model not found. Please ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		$order = OrderModel::query()->inRandomOrder()->first();

		if ( ! $order ) {
			return new WP_Error(
				'no_orders',
				__( 'No orders were found. Generate orders before generating order tax lines.', 'fluent-cart-fakerpress' )
			);
		}

		$tax_rate = TaxRateModel::query()->inRandomOrder()->first();

		if ( ! $tax_rate ) {
			return new WP_Error(
				'no_tax_rates',
				__( 'No tax rates were found. Generate tax classes before generating order tax lines.', 'fluent-cart-fakerpress' )
			);
		}

		// The pair is UNIQUE; an order already carrying this rate cannot take it
		// again. Skip rather than trip the constraint.
		$exists = OrderTaxRateModel::query()
			->where( 'order_id', $order->id )
			->where( 'tax_rate_id', $tax_rate->id )
			->exists();

		if ( $exists ) {
			return new WP_Error(
				'duplicate_order_tax_rate',
				__( 'The drawn order already carries this tax rate. Try again.', 'fluent-cart-fakerpress' )
			);
		}

		$data     = $this->generate_tax_line_data( (int) $order->id, (int) $tax_rate->id );
		$tax_line = $this->create_tax_line( $data );

		if ( ! $tax_line ) {
			return new WP_Error( 'order_tax_rate_creation_failed', __( 'Failed to create order tax line.', 'fluent-cart-fakerpress' ) );
		}

		$result = array(
			'id'          => $tax_line->id,
			'order_id'    => $tax_line->order_id,
			'tax_rate_id' => $tax_line->tax_rate_id,
			// Stored in cents; reported in major units.
			'total_tax'   => round( (int) $data['total_tax'] / 100, 2 ),
			'created_at'  => current_time( 'Y-m-d H:i:s' ),
		);

		/**
		 * Filters the order tax line generation result data.
		 *
		 * @since 1.0.0
		 * @hook  fluent_cart_fakerpress_order_tax_rate_generation_result
		 *
		 * @param array $result The generation result data.
		 * @param int   $id     The created row ID.
		 * @param array $data   The original data used for creation.
		 */
		return apply_filters( 'fluent_cart_fakerpress_order_tax_rate_generation_result', $result, $tax_line->id, $data );
	}

	/**
	 * Build the tax line data.
	 *
	 * @param int $order_id    Order ID.
	 * @param int $tax_rate_id Tax rate ID.
	 *
	 * @return array Tax line data.
	 */
	private function generate_tax_line_data( int $order_id, int $tax_rate_id ): array {
		// Every amount is BIGINT integer cents, read back through
		// Helper::toDecimal(). Dollars would render 100x small.
		$order_tax    = (int) round( $this->get_faker()->randomFloat( 2, 1, 120 ) * 100 );
		$shipping_tax = (int) round( $this->get_faker()->randomFloat( 2, 0, 15 ) * 100 );

		return array(
			'order_id'     => $order_id,
			'tax_rate_id'  => $tax_rate_id,
			'order_tax'    => $order_tax,
			'shipping_tax' => $shipping_tax,
			'total_tax'    => $order_tax + $shipping_tax,
		);
	}

	/**
	 * Create the tax line in Fluent Cart.
	 *
	 * @param array $data Tax line data.
	 *
	 * @return OrderTaxRateModel|null Created instance.
	 */
	private function create_tax_line( array $data ): ?OrderTaxRateModel {
		try {
			return OrderTaxRateModel::query()->create( $data );
		} catch ( \Exception $e ) {
			return null;
		}
	}
}
