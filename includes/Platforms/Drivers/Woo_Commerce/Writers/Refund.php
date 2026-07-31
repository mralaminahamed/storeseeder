<?php
/**
 * WooCommerce refund writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical refund into WooCommerce.
 *
 * `wc_create_refund()` rather than a refund object built by hand: it validates the amount
 * against what the order has left to refund, writes the refund line items, and updates the
 * order's status to `refunded` when nothing remains. A refund inserted directly leaves the
 * order claiming to be paid in full.
 *
 * `refund_payment` is deliberately false. Asking the gateway to move money is not something a
 * seeder should do — on a site with live keys that would issue a real refund.
 *
 * @since 1.1.0
 */
final class Refund extends Writer {
	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::REFUND;
	}

	/**
	 * Create a WooCommerce refund against an existing order.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical refund entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! function_exists( 'wc_create_refund' ) ) {
			return new WP_Error(
				'missing_woocommerce',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		// Only an order that was actually paid can be refunded, and only for what is left.
		$order = $this->random_order( array( 'completed', 'processing', 'on-hold' ) );

		if ( null === $order ) {
			return $this->missing_prerequisite(
				'no_orders',
				__( 'No paid orders were found. Generate orders before generating refunds.', 'storeseeder' )
			);
		}

		$remaining = (float) $order->get_total() - (float) $order->get_total_refunded();

		if ( $remaining <= 0 ) {
			return new WP_Error(
				'nothing_to_refund',
				__( 'The order drawn has already been refunded in full.', 'storeseeder' )
			);
		}

		$full     = 'full' === (string) $entity['kind'];
		$fraction = $full ? 1.0 : (float) $entity['fraction'];
		$amount   = (string) wc_format_decimal( $remaining * $fraction, wc_get_price_decimals() );

		if ( (float) $amount <= 0 ) {
			// A partial refund that rounds to nothing is not a refund; the smallest amount
			// the currency can express is.
			$amount = (string) wc_format_decimal( 1 / ( 10 ** wc_get_price_decimals() ), wc_get_price_decimals() );
		}

		$refund = wc_create_refund(
			array(
				'order_id'       => $order->get_id(),
				'amount'         => $amount,
				'reason'         => (string) $entity['reason'],
				'refund_payment' => false,
				'restock_items'  => true,
			)
		);

		if ( is_wp_error( $refund ) ) {
			return $refund;
		}

		$data = array(
			'id'       => $refund->get_id(),
			'order_id' => $order->get_id(),
			'amount'   => $amount,
		);

		$result = array(
			'id'         => $refund->get_id(),
			'order_id'   => $order->get_id(),
			'order'      => $order->get_order_number(),
			'kind'       => $full ? __( 'full', 'storeseeder' ) : __( 'partial', 'storeseeder' ),
			'amount'     => $amount,
			'currency'   => $order->get_currency(),
			'reason'     => (string) $entity['reason'],
			'created_at' => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, $refund->get_id(), $data );
	}

	/**
	 * Remove a generated refund.
	 *
	 * A refund is an order in WooCommerce's data model, so the order loader finds it. Deleting
	 * it restores the parent order's refunded total, because that figure is summed from the
	 * refund children rather than stored.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id Refund id.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		return $this->delete_crud( $id, 'wc_get_order' );
	}
}
