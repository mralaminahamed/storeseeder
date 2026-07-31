<?php
/**
 * Fluent Cart subscription writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use Exception;
use FluentCart\App\Models\Order as OrderModel;
use FluentCart\App\Models\ProductVariation as ProductVariationModel;
use FluentCart\App\Models\Subscription as SubscriptionModel;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical subscription into Fluent Cart.
 *
 * The subscriptions table ships in Fluent Cart core, so rows generate as fixtures
 * without Pro. Active billing and management require Fluent Cart Pro and a
 * subscription-capable gateway — but that is billing, not seeding.
 *
 * @since 1.1.0
 */
final class Subscription extends Writer {
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
	 * Create a Fluent Cart subscription.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical subscription entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! defined( 'FLUENTCART_VERSION' ) || ! class_exists( SubscriptionModel::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart Subscription model not found. Please ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		// A subscription belongs to a parent order and its customer; without one
		// customer_id and parent_order_id would have to be invented.
		$order = OrderModel::query()->whereNotNull( 'customer_id' )->inRandomOrder()->first();

		if ( ! $order ) {
			return new WP_Error(
				'no_orders',
				__( 'No orders were found. Generate orders before generating subscriptions.', 'storeseeder' )
			);
		}

		// The subscribed product comes from a real variation, which carries both
		// the product post_id and the variation id.
		$variation = ProductVariationModel::query()->with( 'product' )->inRandomOrder()->first();

		if ( ! $variation ) {
			return new WP_Error(
				'no_products',
				__( 'No product variations were found. Generate products before generating subscriptions.', 'storeseeder' )
			);
		}

		$product = $variation->product;
		$name    = ( $product && $product->post_title ) ? $product->post_title : ( $variation->variation_title ?? 'Subscription' );

		$data = array(
			'customer_id'            => (int) $order->customer_id,
			'parent_order_id'        => (int) $order->id,
			'product_id'             => (int) $variation->post_id,
			'variation_id'           => (int) $variation->id,
			'item_name'              => $name,
			'quantity'               => $entity['quantity'],
			'billing_interval'       => $entity['billing_interval'],
			// Every money column is BIGINT integer cents, read back through
			// Helper::toDecimal(), so the canonical minor units go straight in.
			'signup_fee'             => (int) $entity['signup_fee'],
			'recurring_amount'       => (int) $entity['recurring_amount'],
			'recurring_tax_total'    => (int) $entity['recurring_tax_total'],
			'recurring_total'        => (int) $entity['recurring_total'],
			'bill_times'             => $entity['bill_times'],
			'bill_count'             => $entity['bill_count'],
			'trial_days'             => $entity['trial_days'],
			'collection_method'      => $entity['collection_method'],
			'status'                 => $entity['status'],
			'next_billing_date'      => $entity['next_billing_date'],
			'current_payment_method' => $entity['current_payment_method'],
		);

		$subscription = $this->create_subscription( $data );

		if ( ! $subscription ) {
			return new WP_Error( 'subscription_creation_failed', __( 'Failed to create subscription.', 'storeseeder' ) );
		}

		$result = array(
			'id'               => $subscription->id,
			'uuid'             => $subscription->uuid,
			'customer_id'      => $subscription->customer_id,
			'item_name'        => $subscription->item_name,
			'billing_interval' => $subscription->billing_interval,
			'status'           => $subscription->status,
			// Stored in cents; reported in major units.
			'recurring_total'  => round( (int) $data['recurring_total'] / 100, 2 ),
			'created_at'       => current_time( 'Y-m-d H:i:s' ),
		);

		/**
		 * Filters the subscription generation result data.
		 *
		 * @since 1.0.0
		 * @hook  storeseeder_subscription_generation_result
		 *
		 * @param array $result The generation result data.
		 * @param int   $id     The created subscription ID.
		 * @param array $data   The original data used for creation.
		 */
		return apply_filters( 'storeseeder_subscription_generation_result', $result, $subscription->id, $data );
	}

	/**
	 * Create the subscription in Fluent Cart.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $data Fluent-Cart-shaped subscription data.
	 *
	 * @return SubscriptionModel|null Created instance.
	 */
	private function create_subscription( array $data ): ?SubscriptionModel {
		try {
			return SubscriptionModel::query()->create( $data );
		} catch ( Exception $e ) {
			return null;
		}
	}
}
