<?php
/**
 * Subscription Generator Class for Fluent Cart FakerPress Plugin
 *
 * @since      2.4.0
 * @subpackage Generators
 * @package    FluentCartFakerPress
 */

namespace FluentCartFakerPress\Generators;

use FluentCart\App\Models\Order as OrderModel;
use FluentCart\App\Models\ProductVariation as ProductVariationModel;
use FluentCart\App\Models\Subscription as SubscriptionModel;
use FluentCartFakerPress\Abstracts\Generator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Subscription Generator Class
 *
 * Generates subscription records against existing orders for testing Fluent Cart
 * recurring-billing views. The subscriptions table ships in Fluent Cart core, so
 * rows can be generated as fixtures, but active billing and management require
 * Fluent Cart Pro and a subscription-capable gateway.
 */
class Subscription extends Generator {


	/**
	 * Billing intervals Fluent Cart recognises (Status::BILLING_*).
	 *
	 * @var string[]
	 */
	private const INTERVALS = array( 'daily', 'weekly', 'monthly', 'quarterly', 'half_yearly', 'yearly' );

	/**
	 * Subscription statuses worth generating (Status::SUBSCRIPTION_*).
	 *
	 * @var string[]
	 */
	private const STATUSES = array( 'active', 'trialing', 'paused', 'past_due', 'canceled', 'expired' );

	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'subscription';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'subscriptions' => 'Fluent Cart Subscriptions',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates subscription records against existing orders for testing Fluent Cart recurring-billing views. Active billing requires Fluent Cart Pro.';
	}

	/**
	 * Generate a single subscription
	 *
	 * @return WP_Error|array Single subscription data, error, or false on failure.
	 */
	protected function generate_single_item() {
		if ( ! defined( 'FLUENTCART_VERSION' ) || ! class_exists( SubscriptionModel::class ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart Subscription model not found. Please ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		// A subscription belongs to a parent order and its customer; without one
		// customer_id and parent_order_id would have to be invented.
		$order = OrderModel::query()->whereNotNull( 'customer_id' )->inRandomOrder()->first();

		if ( ! $order ) {
			return new WP_Error(
				'no_orders',
				__( 'No orders were found. Generate orders before generating subscriptions.', 'fluent-cart-fakerpress' )
			);
		}

		// The subscribed product comes from a real variation, which carries both
		// the product post_id and the variation id.
		$variation = ProductVariationModel::query()->with( 'product' )->inRandomOrder()->first();

		if ( ! $variation ) {
			return new WP_Error(
				'no_products',
				__( 'No product variations were found. Generate products before generating subscriptions.', 'fluent-cart-fakerpress' )
			);
		}

		$data         = $this->generate_subscription_data( $order, $variation );
		$subscription = $this->create_subscription( $data );

		if ( ! $subscription ) {
			return new WP_Error( 'subscription_creation_failed', __( 'Failed to create subscription.', 'fluent-cart-fakerpress' ) );
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
		 * @hook  fluent_cart_fakerpress_subscription_generation_result
		 *
		 * @param array $result The generation result data.
		 * @param int   $id     The created subscription ID.
		 * @param array $data   The original data used for creation.
		 */
		return apply_filters( 'fluent_cart_fakerpress_subscription_generation_result', $result, $subscription->id, $data );
	}

	/**
	 * Build the subscription data.
	 *
	 * @param object $order     Parent order (id, customer_id).
	 * @param object $variation Subscribed variation (id, post_id, product).
	 *
	 * @return array Subscription data.
	 */
	private function generate_subscription_data( object $order, object $variation ): array {
		$product = $variation->product;
		$name    = ( $product && $product->post_title ) ? $product->post_title : ( $variation->variation_title ?? 'Subscription' );

		// Every money column is BIGINT integer cents, read back through
		// Helper::toDecimal(). Dollars would render 100x small.
		$recurring_amount = (int) round( $this->get_faker()->randomFloat( 2, 5, 200 ) * 100 );
		$recurring_tax    = (int) round( $recurring_amount * 0.08 );
		$signup_fee       = $this->get_faker()->boolean( 30 ) ? (int) round( $this->get_faker()->randomFloat( 2, 5, 50 ) * 100 ) : 0;

		$interval   = $this->get_faker()->randomElement( self::INTERVALS );
		$trial_days = $this->get_faker()->boolean( 25 ) ? $this->get_faker()->numberBetween( 3, 30 ) : 0;

		return array(
			'customer_id'            => (int) $order->customer_id,
			'parent_order_id'        => (int) $order->id,
			'product_id'             => (int) $variation->post_id,
			'variation_id'           => (int) $variation->id,
			'item_name'              => $name,
			'quantity'               => 1,
			'billing_interval'       => $interval,
			'signup_fee'             => $signup_fee,
			'recurring_amount'       => $recurring_amount,
			'recurring_tax_total'    => $recurring_tax,
			'recurring_total'        => $recurring_amount + $recurring_tax,
			'bill_times'             => $this->get_faker()->randomElement( array( 0, 6, 12, 24 ) ),
			'bill_count'             => $this->get_faker()->numberBetween( 0, 6 ),
			'trial_days'             => $trial_days,
			'collection_method'      => 'automatic',
			'status'                 => $this->get_faker()->randomElement( self::STATUSES ),
			'next_billing_date'      => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
			'current_payment_method' => $this->get_faker()->randomElement( array( 'stripe', 'paypal' ) ),
		);
	}

	/**
	 * Create the subscription in Fluent Cart.
	 *
	 * @param array $data Subscription data.
	 *
	 * @return SubscriptionModel|null Created instance.
	 */
	private function create_subscription( array $data ): ?SubscriptionModel {
		try {
			return SubscriptionModel::query()->create( $data );
		} catch ( \Exception $e ) {
			return null;
		}
	}
}
