<?php
/**
 * WooCommerce order writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Platforms\Status;
use WC_Customer;
use WC_Order;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical order into WooCommerce.
 *
 * The line items come from real products, and the totals come from WooCommerce rather than
 * from the entity: `add_product()` then `calculate_totals()` is what makes an order whose
 * subtotal, tax and total agree with each other and with the tax settings in force. Setting
 * the totals by hand produces an order that looks right until something recalculates it.
 *
 * The entity's per-item prices are therefore only used where the product has no price of its
 * own — see `add_items()`.
 *
 * @since 1.1.0
 */
final class Order extends Writer {
	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::ORDER;
	}

	/**
	 * Create a WooCommerce order.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical order entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! function_exists( 'wc_create_order' ) ) {
			return new WP_Error(
				'missing_woocommerce',
				__( 'WooCommerce is not active on this site.', 'storeseeder' )
			);
		}

		$products = $this->product_ids( count( (array) $entity['items'] ) );

		if ( array() === $products ) {
			return $this->missing_prerequisite(
				'no_products',
				__( 'No products were found. Generate products before generating orders.', 'storeseeder' )
			);
		}

		$order = new WC_Order();
		$order->set_currency( (string) $entity['currency'] );
		// Recorded so a site owner can tell generated orders from real ones in the admin's
		// own "created via" column, without needing StoreSeeder's ledger to hand.
		$order->set_created_via( 'storeseeder' );

		$items = $this->add_items( $order, (array) $entity['items'], $products );

		if ( 0 === $items ) {
			return new WP_Error(
				'order_items_failed',
				__( 'No line items could be added to the order.', 'storeseeder' )
			);
		}

		$addresses = (array) $entity['addresses'];
		$this->set_address( $order, 'billing', (array) ( $addresses['billing'] ?? array() ) );
		$this->set_address( $order, 'shipping', (array) ( $addresses['shipping'] ?? array() ) );

		$customer_id = $this->random_customer_id();

		if ( $customer_id > 0 ) {
			$order->set_customer_id( $customer_id );
			$this->copy_customer_email( $order, $customer_id );
		}

		$order->set_payment_method( (string) $entity['payment_method'] );
		$order->set_date_created( (string) $entity['created_at'] );

		// Totals before the status: setting a paid status recalculates and stamps date_paid,
		// and it should do so against the finished order rather than an empty one.
		$order->calculate_totals( true );
		$order->set_status( $this->map_order_status( (string) $entity['status'] ) );

		$id = $order->save();

		if ( ! $id ) {
			return new WP_Error( 'order_creation_failed', __( 'Failed to create the order.', 'storeseeder' ) );
		}

		$data = array(
			'id'          => (int) $id,
			'status'      => $order->get_status(),
			'total'       => $order->get_total(),
			'customer_id' => $customer_id,
			'items'       => $items,
		);

		$result = array(
			'id'             => (int) $id,
			'number'         => $order->get_order_number(),
			'status'         => $order->get_status(),
			'customer'       => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'items'          => $items,
			'total'          => $order->get_total(),
			'currency'       => $order->get_currency(),
			'payment_method' => $order->get_payment_method(),
			'created_at'     => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
		);

		return $this->filter_result( $result, (int) $id, $data );
	}

	/**
	 * Remove a generated order.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id Order id.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		// Deleting the order takes its items, addresses, tax lines, refunds and notes with
		// it — WooCommerce's data store owns those, which is the reason to go through it.
		return $this->delete_crud( $id, 'wc_get_order' );
	}

	/**
	 * Add line items drawn from real products.
	 *
	 * The entity proposes as many items as it wants; this pairs each with a product the store
	 * actually has and stops when it runs out. A quantity and a unit price are generated data
	 * and arrive on the entity — but the price is only applied when the product has none,
	 * because an order whose lines disagree with the catalogue is a confusing fixture.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Order          $order    The order being built.
	 * @param array<int, mixed> $items    Canonical items.
	 * @param array<int, int>   $products Product ids to draw from.
	 *
	 * @return int How many lines were added.
	 */
	private function add_items( WC_Order $order, array $items, array $products ): int {
		$added = 0;

		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) || ! isset( $products[ $index ] ) ) {
				continue;
			}

			$product = wc_get_product( $products[ $index ] );

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$quantity = max( 1, (int) ( $item['quantity'] ?? 1 ) );
			$args     = array();

			if ( '' === (string) $product->get_price() ) {
				$unit          = $this->to_decimal( (int) ( $item['unit_price'] ?? 0 ) );
				$args['total'] = (string) wc_format_decimal( (float) $unit * $quantity );
			}

			$order->add_product( $product, $quantity, $args );
			++$added;
		}

		return $added;
	}

	/**
	 * Copy the customer's billing email onto the order.
	 *
	 * An order with no email is one no notification can reach, and testing emails is a
	 * common reason to generate orders in the first place.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Order $order       The order being built.
	 * @param int      $customer_id The customer it belongs to.
	 *
	 * @return void
	 */
	private function copy_customer_email( WC_Order $order, int $customer_id ): void {
		try {
			$customer = new WC_Customer( $customer_id );
			$email    = $customer->get_billing_email();

			if ( '' === $email ) {
				$email = $customer->get_email();
			}

			if ( '' !== $email ) {
				$order->set_billing_email( $email );
			}
		} catch ( \Exception $e ) {
			// A user id that no longer resolves is not worth failing an order for; the
			// billing address the entity carries stands on its own.
			return;
		}
	}

	/**
	 * Copy one canonical address onto the order.
	 *
	 * @since 1.1.0
	 *
	 * @param WC_Order             $order   The order being built.
	 * @param string               $type    `billing` or `shipping`.
	 * @param array<string, mixed> $address Canonical address.
	 *
	 * @return void
	 */
	private function set_address( WC_Order $order, string $type, array $address ): void {
		if ( array() === $address ) {
			return;
		}

		$name = explode( ' ', (string) ( $address['name'] ?? '' ), 2 );

		$props = array(
			// explode() always yields the first element; the second only when there was a
			// space to split on.
			'first_name' => $name[0],
			'last_name'  => $name[1] ?? '',
			'address_1'  => $address['address_1'] ?? '',
			'address_2'  => $address['address_2'] ?? '',
			'city'       => $address['city'] ?? '',
			'state'      => $address['state'] ?? '',
			'postcode'   => $address['postcode'] ?? '',
			'country'    => $address['country'] ?? '',
		);

		if ( 'billing' === $type ) {
			$props['phone'] = $address['phone'] ?? '';
		}

		$order->set_address( $props, $type );
	}
}
