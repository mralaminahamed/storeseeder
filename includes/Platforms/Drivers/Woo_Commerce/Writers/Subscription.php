<?php
/**
 * WooCommerce Subscriptions writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Woo_Commerce\Writers;

use StoreSeeder\Platforms\Drivers\Woo_Commerce\Writer;
use StoreSeeder\Platforms\Resource;
use WC_Subscription;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical subscription into WooCommerce Subscriptions.
 *
 * Subscriptions is a separate plugin, so this writer is only reachable when the driver reports
 * the resource as supported — and it checks again, because a capability matrix computed at page
 * load can be stale by the time a run reaches a writer.
 *
 * `wcs_create_subscription()` rather than a post insert: it validates the status and billing
 * period, links the subscription to its parent order, and sets the date fields Subscriptions
 * reads for renewals. A hand-built record has none of that and never renews.
 *
 * @since 1.1.0
 */
final class Subscription extends Writer {
	/**
	 * Canonical subscription state to a WooCommerce Subscriptions status.
	 *
	 * Subscriptions accepts `active`, `on-hold`, `cancelled`, `expired`, `pending-cancel` and
	 * `pending`, and `wcs_create_subscription()` rejects anything else outright. Three of the
	 * canonical states have no direct equivalent and are mapped to the nearest real one:
	 * trialing is active with a trial, paused and past-due are both on-hold.
	 *
	 * @since 1.1.0
	 * @var array<string, string>
	 */
	private const STATUS = array(
		'active'   => 'active',
		'trialing' => 'active',
		'paused'   => 'on-hold',
		'past_due' => 'on-hold',
		'canceled' => 'cancelled',
		'expired'  => 'expired',
	);

	/**
	 * The resource this writer persists.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function resource(): string {
		return Resource::SUBSCRIPTION;
	}

	/**
	 * Create a WooCommerce subscription.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical subscription entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! function_exists( 'wcs_create_subscription' ) ) {
			return new WP_Error(
				'missing_woocommerce_subscriptions',
				__( 'Subscriptions are stored by WooCommerce Subscriptions, which is not active on this site. Install and activate it to generate them.', 'storeseeder' )
			);
		}

		// A paid order for preference — that is what a real subscription starts from — but any
		// order will do rather than refusing. A store seeded with pending orders only is a
		// normal state, and "generate orders first" would be misleading advice when orders
		// exist.
		$order = $this->random_order( array( 'completed', 'processing' ) ) ?? $this->random_order();

		if ( null === $order ) {
			return $this->missing_prerequisite(
				'no_orders',
				__( 'No orders were found. Generate orders before generating subscriptions.', 'storeseeder' )
			);
		}

		$product = $this->random_product();

		if ( null === $product ) {
			return $this->missing_prerequisite(
				'no_products',
				__( 'No products were found. Generate products before generating subscriptions.', 'storeseeder' )
			);
		}

		$status = self::STATUS[ (string) $entity['status'] ] ?? 'active';

		$subscription = wcs_create_subscription(
			array(
				'order_id'         => $order->get_id(),
				'status'           => $status,
				'customer_id'      => $order->get_customer_id(),
				'billing_period'   => 'month',
				'billing_interval' => max( 1, (int) $entity['billing_interval'] ),
				'created_via'      => 'storeseeder',
			)
		);

		if ( is_wp_error( $subscription ) ) {
			return $subscription;
		}

		// A subscription with no line item renews for nothing and shows an empty table.
		$subscription->add_product( $product, max( 1, (int) $entity['quantity'] ) );
		$subscription->set_payment_method( (string) $entity['current_payment_method'] );
		$subscription->calculate_totals();

		$dates = $this->dates( $status, (int) $entity['trial_days'], (string) $entity['next_billing_date'] );

		if ( array() !== $dates ) {
			// update_dates() validates the order of the dates it is given and throws on a
			// contradiction — an end date before a start date, say — so a failure here is
			// worth reporting rather than swallowing.
			try {
				$subscription->update_dates( $dates );
			} catch ( \Exception $e ) {
				return new WP_Error( 'subscription_dates_failed', $e->getMessage() );
			}
		}

		$id = $subscription->save();

		if ( ! $id ) {
			return new WP_Error( 'subscription_creation_failed', __( 'Failed to save the subscription.', 'storeseeder' ) );
		}

		$data = array(
			'id'       => (int) $id,
			'order_id' => $order->get_id(),
			'status'   => $status,
		);

		$result = array(
			'id'             => (int) $id,
			'order'          => $order->get_order_number(),
			'order_id'       => $order->get_id(),
			'product'        => $product->get_name(),
			'status'         => $subscription->get_status(),
			'total'          => $subscription->get_total(),
			'billing_period' => $subscription->get_billing_period(),
			'interval'       => $subscription->get_billing_interval(),
			'next_payment'   => $subscription->get_date( 'next_payment' ),
			'created_at'     => current_time( 'Y-m-d H:i:s' ),
		);

		return $this->filter_result( $result, (int) $id, $data );
	}

	/**
	 * Remove a generated subscription.
	 *
	 * @since 1.1.0
	 *
	 * @param int|string $id Subscription id.
	 *
	 * @return true|WP_Error
	 */
	public function delete( $id ) {
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return new WP_Error(
				'storeseeder_delete_unsupported',
				__( 'WooCommerce Subscriptions is not active on this site.', 'storeseeder' )
			);
		}

		$subscription = wcs_get_subscription( (int) $id );

		if ( ! $subscription instanceof WC_Subscription ) {
			return true;
		}

		$subscription->delete( true );

		return true;
	}

	/**
	 * The date fields to set, for the status the subscription is in.
	 *
	 * A next-payment date on a cancelled or expired subscription is a contradiction
	 * Subscriptions rejects, and a trial end date only means anything while the trial is what
	 * the subscription is in — so both are conditional rather than always written.
	 *
	 * @since 1.1.0
	 *
	 * @param string $status     WooCommerce Subscriptions status.
	 * @param int    $trial_days Trial length from the entity, zero for none.
	 * @param string $next       Next billing date from the entity.
	 *
	 * @return array<string, string>
	 */
	private function dates( string $status, int $trial_days, string $next ): array {
		$dates = array();

		if ( $trial_days > 0 ) {
			$dates['trial_end'] = gmdate( 'Y-m-d H:i:s', time() + $trial_days * DAY_IN_SECONDS );
		}

		if ( in_array( $status, array( 'active', 'on-hold' ), true ) ) {
			$dates['next_payment'] = $next;
		}

		if ( 'cancelled' === $status ) {
			$dates['cancelled'] = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		}

		if ( 'expired' === $status ) {
			$dates['end'] = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		}

		return $dates;
	}
}
