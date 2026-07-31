<?php
/**
 * WooCommerce order tax line writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WC_Order_Item_Tax;
use WC_Tax;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a canonical tax line to an existing WooCommerce order.
 *
 * WooCommerce keeps an order's tax as line items — one `WC_Order_Item_Tax` per rate — and the
 * order's own `cart_tax` and `shipping_tax` are the sums of them. Both have to be written: the
 * item is what the order screen itemises, and the totals are what every report and the
 * customer's receipt read.
 *
 * The totals are then set explicitly rather than recalculated. `calculate_totals()` would
 * recompute tax from the store's own rate settings and discard the generated figures, which is
 * the opposite of what a tax-line fixture is for.
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
	 * Add a tax line to an order.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical order-tax entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! class_exists( 'WC_Order_Item_Tax' ) ) {
			return new WP_Error(
				'missing_woocommerce',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$order = $this->random_order();

		if ( null === $order ) {
			return $this->missing_prerequisite(
				'no_orders',
				__( 'No orders were found. Generate orders before generating order tax lines.', 'storeseeder' )
			);
		}

		$rate_id = $this->rate_id();

		if ( 0 === $rate_id ) {
			return $this->missing_prerequisite(
				'no_tax_rates',
				__( 'No tax rates were found. Generate tax classes before generating order tax lines.', 'storeseeder' )
			);
		}

		$order_tax    = $this->to_decimal( (int) $entity['order_tax'] );
		$shipping_tax = $this->to_decimal( (int) $entity['shipping_tax'] );

		$item = new WC_Order_Item_Tax();
		$item->set_rate_id( $rate_id );
		$item->set_label( WC_Tax::get_rate_label( $rate_id ) );
		$item->set_compound( false );
		$item->set_tax_total( $order_tax );
		$item->set_shipping_tax_total( $shipping_tax );
		$item->set_order_id( $order->get_id() );

		$id = $item->save();

		if ( ! $id ) {
			return new WP_Error( 'order_tax_failed', __( 'Failed to add the tax line to the order.', 'storeseeder' ) );
		}

		$order->add_item( $item );

		// The order's own tax totals, and the grand total that includes them. Written rather
		// than recalculated, for the reason in the class docblock.
		// Strings, not floats: WooCommerce's setters are typed for the decimal strings it
		// stores, and handing them a float is how a rounding difference creeps into a total.
		$order->set_cart_tax( (string) wc_format_decimal( (float) $order->get_cart_tax() + (float) $order_tax ) );
		$order->set_shipping_tax( (string) wc_format_decimal( (float) $order->get_shipping_tax() + (float) $shipping_tax ) );
		$order->set_total( (string) wc_format_decimal( (float) $order->get_total() + (float) $order_tax + (float) $shipping_tax ) );
		$order->save();

		$data = array(
			'id'       => (int) $id,
			'order_id' => $order->get_id(),
			'rate_id'  => $rate_id,
		);

		$result = array(
			'id'           => (int) $id,
			'order'        => $order->get_order_number(),
			'order_id'     => $order->get_id(),
			'label'        => $item->get_label(),
			'order_tax'    => $order_tax,
			'shipping_tax' => $shipping_tax,
			'total_tax'    => $this->to_decimal( (int) $entity['total_tax'] ),
			'created_at'   => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, (int) $id, $data );
	}

	/**
	 * Remove a generated tax line.
	 *
	 * The order's totals are left as they are. Recomputing them would mean deciding what the
	 * order's tax *should* be, which is a different question from "remove the row StoreSeeder
	 * added" — and on a test order the totals were generated anyway.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id Order item id.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		if ( ! function_exists( 'wc_delete_order_item' ) ) {
			return new WP_Error(
				'storeseeder_delete_unsupported',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		wc_delete_order_item( (int) $id );

		return true;
	}

	/**
	 * The id of a tax rate to attach the line to, creating one if the store has none.
	 *
	 * A tax line without a rate id shows as an unlabelled row and reports as tax belonging to
	 * nothing, so this is a foreign key worth resolving rather than skipping.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function rate_id(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- WC_Tax offers no "any rate" lookup.
		$existing = $wpdb->get_col( "SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates ORDER BY RAND() LIMIT 10" );

		if ( array() !== $existing ) {
			return (int) $this->faker()->randomElement( $existing );
		}

		return (int) WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => 'US',
				'tax_rate'          => '8.0000',
				'tax_rate_name'     => __( 'Tax', 'storeseeder' ),
				'tax_rate_priority' => 1,
				'tax_rate_shipping' => 1,
				'tax_rate_class'    => '',
			)
		);
	}
}
