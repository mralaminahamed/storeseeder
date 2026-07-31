<?php
/**
 * Fluent Cart order tax line writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use Exception;
use FluentCart\App\Models\Order as OrderModel;
use FluentCart\App\Models\OrderTaxRate as OrderTaxRateModel;
use FluentCart\App\Models\TaxRate as TaxRateModel;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical order tax line into Fluent Cart.
 *
 * @since 1.1.0
 */
final class Order_Tax_Rate extends Writer {
	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::ORDER_TAX_RATE;
	}

	/**
	 * Create a Fluent Cart order tax line.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical order tax line entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! defined( 'FLUENTCART_VERSION' ) || ! class_exists( OrderTaxRateModel::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart OrderTaxRate model not found. Please ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		$order = OrderModel::query()->inRandomOrder()->first();

		if ( ! $order ) {
			return new WP_Error(
				'no_orders',
				__( 'No orders were found. Generate orders before generating order tax lines.', 'storeseeder' )
			);
		}

		$tax_rate = TaxRateModel::query()->inRandomOrder()->first();

		if ( ! $tax_rate ) {
			return new WP_Error(
				'no_tax_rates',
				__( 'No tax rates were found. Generate tax classes before generating order tax lines.', 'storeseeder' )
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
				__( 'The drawn order already carries this tax rate. Try again.', 'storeseeder' )
			);
		}

		$data = array(
			'order_id'     => (int) $order->id,
			'tax_rate_id'  => (int) $tax_rate->id,
			'order_tax'    => (int) $entity['order_tax'],
			'shipping_tax' => (int) $entity['shipping_tax'],
			'total_tax'    => (int) $entity['total_tax'],
		);

		$tax_line = $this->create_tax_line( $data );

		if ( ! $tax_line ) {
			return new WP_Error( 'order_tax_rate_creation_failed', __( 'Failed to create order tax line.', 'storeseeder' ) );
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
		 * @hook  storeseeder_order_tax_rate_generation_result
		 *
		 * @param array $result The generation result data.
		 * @param int   $id     The created row ID.
		 * @param array $data   The original data used for creation.
		 */
		return apply_filters( 'storeseeder_order_tax_rate_generation_result', $result, $tax_line->id, $data );
	}

	/**
	 * Create the tax line in Fluent Cart.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $data Fluent-Cart-shaped tax line data.
	 *
	 * @return OrderTaxRateModel|null Created instance.
	 */
	private function create_tax_line( array $data ): ?OrderTaxRateModel {
		try {
			return OrderTaxRateModel::query()->create( $data );
		} catch ( Exception $e ) {
			return null;
		}
	}
}
