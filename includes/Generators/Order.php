<?php
/**
 * Order Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder
 */

namespace StoreSeeder\Generators;

use FluentCart\App\Models\AppliedCoupon as AppliedCouponModel;
use FluentCart\App\Models\Coupon as CouponModel;
use FluentCart\App\Models\Customer as CustomerModel;
use FluentCart\App\Models\Order as OrderModel;
use FluentCart\App\Models\OrderAddress as OrderAddressModel;
use FluentCart\App\Models\ProductVariation as ProductVariationModel;
use StoreSeeder\Abstracts\Generator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Order Generator Class
 *
 * Generates realistic fake order data for Fluent Cart testing and development.
 */
class Order extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'order';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'orders' => 'Fluent Cart Orders',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates complete orders with customer data, items, payments, and shipping for testing Fluent Cart order processing.';
	}

	/**
	 * Generate a single order
	 *
	 * @return WP_Error|array Single order data, error, or false on failure.
	 */
	protected function generate_single_item() {
		// Check if Fluent Cart is active.
		if ( ! defined( 'FLUENTCART_VERSION' ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart plugin not found. Please ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		$order_data = $this->generate_order_data();

		// An order with no line items is not a useful fixture, and inserting
		// one leaves an orphan row behind. Fail before touching the database
		// and say what is actually missing.
		if ( empty( $order_data['items'] ) ) {
			return new WP_Error(
				'no_products',
				__( 'No published products with variations were found. Generate products before generating orders.', 'storeseeder' )
			);
		}

		// Fluent Cart orders always belong to a customer; one without is
		// invisible in the admin customer view and breaks lifetime-value
		// reporting.
		if ( null === $order_data['customer_id'] ) {
			return new WP_Error(
				'no_customers',
				__( 'No customers were found. Generate customers before generating orders.', 'storeseeder' )
			);
		}

		$order_id = $this->create_order( $order_data );

		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}

		if ( ! $order_id ) {
			return new WP_Error( 'order_creation_failed', __( 'Failed to create order.', 'storeseeder' ) );
		}

		$result = array(
			'id'          => $order_id,
			'customer_id' => $order_data['customer_id'],
			// Stored in cents; reported in major units so the UI shows 456.78
			// rather than 45678.
			'total'       => round( $order_data['total'] / 100, 2 ),
			'status'      => $order_data['status'],
			'items_count' => count( $order_data['items'] ),
			'created_at'  => current_time( 'Y-m-d H:i:s' ),
		);

		/**
		 * Filters the order generation result data.
		 *
		 * Allows developers to modify the returned order data after generation.
		 *
		 * @since 1.0.0
		 * @hook  storeseeder_order_generation_result
		 *
		 * @param array $result     The order generation result data.
		 * @param int   $order_id   The created order ID.
		 * @param array $order_data The original order data used for creation.
		 */
		return apply_filters( 'storeseeder_order_generation_result', $result, $order_id, $order_data );
	}

	/**
	 * Generate order data
	 *
	 * @return array Order data
	 */
	private function generate_order_data(): array {
		$items       = array();
		$items_count = $this->get_faker()->numberBetween( 1, 3 );
		$subtotal    = 0;

		$variations = $this->random_variations( $items_count );

		foreach ( $variations as $index => $variation ) {
			// Every money column on fct_order_items is BIGINT holding integer
			// cents, so the value has to be scaled on the way in. Storing
			// dollars makes a $456.78 line render as $4.57.
			$unit_price = $this->to_cent( $this->get_faker()->randomFloat( 2, 10, 500 ) );
			$quantity   = $this->get_faker()->numberBetween( 1, 2 );
			$line_total = $unit_price * $quantity;

			$items[] = array(
				'post_id'    => (int) $variation->post_id,
				'object_id'  => (int) $variation->id,
				'title'      => $variation->variation_title,
				'post_title' => $variation->post_title,
				'quantity'   => $quantity,
				'unit_price' => $unit_price,
				'subtotal'   => $line_total,
				'cart_index' => $index,
			);

			$subtotal += $line_total;
		}

		$tax_total = (int) round( $subtotal * 0.08 );

		// Some orders carry a coupon. Drawing a real one and recording the
		// discount ties the order to fct_applied_coupons, so coupon usage counts
		// and revenue reports stop reading zero.
		$coupon   = $this->random_applicable_coupon();
		$discount = $this->coupon_discount( $coupon, $subtotal );

		return array(
			'customer_id'     => $this->random_customer_id(),
			'items'           => $items,
			'subtotal'        => $subtotal,
			'tax_amount'      => $tax_total,
			'total'           => max( 0, $subtotal + $tax_total - $discount ),
			'shipping_amount' => 0,
			'discount_amount' => $discount,
			'coupon'          => ( $coupon && $discount > 0 )
				? array(
					'id'     => (int) $coupon->id,
					'code'   => $coupon->code,
					'amount' => $discount,
				)
				: null,
			'currency'        => 'USD',
			// Status::getOrderStatuses() is the allowed set. 'pending' is a
			// payment_status, not an order status, and an order carrying it
			// disappears from every admin status filter.
			'status'          => $this->get_faker()->randomElement(
				array( 'completed', 'processing', 'on-hold', 'canceled', 'failed' )
			),
			'payment_method'  => $this->get_faker()->randomElement( array( 'stripe', 'paypal', 'cod' ) ),
			'created_at'      => current_time( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Convert a dollar amount to the integer cents Fluent Cart stores.
	 *
	 * Mirrors FluentCart\App\Helpers\Helper::toCent(), reimplemented here so the
	 * generator does not depend on a helper that is not part of Fluent Cart's
	 * public surface.
	 *
	 * @param float $amount Amount in major currency units.
	 *
	 * @return int Amount in cents.
	 */
	private function to_cent( float $amount ): int {
		return (int) round( $amount * 100 );
	}

	/**
	 * Draw real product variations to put on the order.
	 *
	 * Order items are foreign keys into fct_product_variations and wp_posts.
	 * Inventing the IDs produces line items pointing at products that do not
	 * exist, which render blank in the admin and break every report that joins
	 * back to the product.
	 *
	 * @param int $count How many variations are wanted.
	 *
	 * @return array<int, object> Variation rows, possibly fewer than requested.
	 */
	private function random_variations( int $count ): array {
		$variations = ProductVariationModel::query()
			->with( 'product' )
			->inRandomOrder()
			->limit( $count )
			->get();

		$rows = array();

		foreach ( $variations as $variation ) {
			$product = $variation->product;

			if ( ! $product || 'publish' !== $product->post_status ) {
				continue;
			}

			$rows[] = (object) array(
				'id'              => $variation->id,
				'post_id'         => $variation->post_id,
				'variation_title' => $variation->variation_title,
				'post_title'      => $product->post_title,
			);
		}

		return $rows;
	}

	/**
	 * Draw a real customer ID.
	 *
	 * @return int|null Customer ID, or null when the store has no customers yet.
	 */
	private function random_customer_id(): ?int {
		$customer = CustomerModel::query()->inRandomOrder()->first();

		return $customer ? (int) $customer->id : null;
	}

	/**
	 * Create order in Fluent Cart
	 *
	 * @param array $data Order data.
	 *
	 * @return int|WP_Error|null Created order ID or error
	 */
	private function create_order( array $data ) {
		// Check if Fluent Cart Order model is available.
		if ( ! class_exists( OrderModel::class ) ) {
			return new WP_Error( 'missing_model', __( 'Fluent Cart Order model not found. Please ensure Fluent Cart plugin is active.', 'storeseeder' ) );
		}

		// Prepare order data for Fluent Cart Order model.
		$order_data = array(
			'parent_id'             => 0,
			'invoice_no'            => 'FC-' . $this->get_faker()->numberBetween( 1000, 9999 ),
			'receipt_number'        => $this->get_faker()->numberBetween( 1000, 9999 ),
			'customer_id'           => $data['customer_id'],
			'payment_method'        => $data['payment_method'],
			'payment_method_title'  => ucfirst( $data['payment_method'] ),
			'payment_status'        => 'paid',
			'currency'              => $data['currency'],
			'subtotal'              => $data['subtotal'],
			'shipping_tax'          => 0,
			'fulfillment_type'      => 'physical',
			'tax_total'             => $data['tax_amount'],
			'manual_discount_total' => 0,
			'coupon_discount_total' => $data['discount_amount'],
			'total_amount'          => $data['total'],
			'total_paid'            => $data['total'],
			'status'                => $data['status'],
			'created_at'            => $data['created_at'],
		);

		// Create order using Fluent Cart Order model.
		$order = OrderModel::query()->create( $order_data );

		if ( ! $order ) {
			return new WP_Error( 'order_creation_failed', __( 'Failed to create order using Fluent Cart model.', 'storeseeder' ) );
		}

		// Create order items if provided.
		//
		// The relation is order_items(), snake_case. Calling orderItems()
		// resolves no relation, forwards to the query builder, and throws
		// BadMethodCallException — after the order row is already inserted,
		// leaving an orphan order with no line items.
		if ( ! empty( $data['items'] ) ) {
			foreach ( $data['items'] as $item ) {
				$order->order_items()->create(
					array(
						'post_id'    => $item['post_id'],
						'object_id'  => $item['object_id'],
						'post_title' => $item['post_title'],
						'title'      => $item['title'],
						'quantity'   => $item['quantity'],
						'unit_price' => $item['unit_price'],
						'subtotal'   => $item['subtotal'],
						'line_total' => $item['subtotal'],
						'cart_index' => $item['cart_index'],
						'created_at' => $data['created_at'],
					)
				);
			}
		}

		// Give the order the billing and shipping addresses it was missing —
		// without them the admin order view is blank and tax and shipping cannot
		// resolve a region.
		$this->create_order_addresses( $order );

		// Record the applied coupon, if any, so the discount traces to a real
		// coupon rather than an unexplained number.
		if ( ! empty( $data['coupon'] ) ) {
			$this->create_applied_coupon( $order, $data['coupon'] );
		}

		return $order->id;
	}

	/**
	 * Draw a coupon whose discount can be applied to an order.
	 *
	 * Only 'fixed' and 'percentage' coupons map to a line discount; free_shipping
	 * and other types are skipped. Returns null ~60% of the time so most orders
	 * carry no coupon.
	 *
	 * @since 1.0.0
	 *
	 * @return CouponModel|null Coupon model, or null.
	 */
	private function random_applicable_coupon(): ?CouponModel {
		if ( ! class_exists( CouponModel::class ) || ! $this->get_faker()->boolean( 40 ) ) {
			return null;
		}

		return CouponModel::query()
			->whereIn( 'type', array( 'fixed', 'percentage' ) )
			->inRandomOrder()
			->first();
	}

	/**
	 * Compute a coupon's discount in cents, capped at the subtotal.
	 *
	 * A 'fixed' coupon stores its amount in integer cents already; a 'percentage'
	 * coupon stores the percent, applied against the subtotal.
	 *
	 * @since 1.0.0
	 *
	 * @param CouponModel|null $coupon   Coupon, or null.
	 * @param int              $subtotal Order subtotal in cents.
	 *
	 * @return int Discount in cents.
	 */
	private function coupon_discount( ?CouponModel $coupon, int $subtotal ): int {
		if ( null === $coupon ) {
			return 0;
		}

		if ( 'percentage' === $coupon->type ) {
			$discount = (int) round( $subtotal * (float) $coupon->amount / 100 );
		} else {
			$discount = (int) $coupon->amount;
		}

		return (int) min( $discount, $subtotal );
	}

	/**
	 * Create the billing and shipping addresses for an order.
	 *
	 * @since 1.0.0
	 *
	 * @param OrderModel $order Parent order.
	 *
	 * @return void
	 */
	private function create_order_addresses( OrderModel $order ): void {
		if ( ! class_exists( OrderAddressModel::class ) ) {
			return;
		}

		foreach ( array( 'billing', 'shipping' ) as $type ) {
			OrderAddressModel::query()->create( $this->generate_order_address( (int) $order->id, $type ) );
		}
	}

	/**
	 * Build one order address row.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $order_id Parent order ID.
	 * @param string $type     'billing' or 'shipping'.
	 *
	 * @return array Column map for OrderAddress::create().
	 */
	private function generate_order_address( int $order_id, string $type ): array {
		return array(
			'order_id'  => $order_id,
			'type'      => $type,
			'name'      => $this->get_faker()->name(),
			'address_1' => $this->get_faker()->streetAddress(),
			'address_2' => $this->get_faker()->optional( 0.3 )->secondaryAddress() ?? '',
			'city'      => $this->get_faker()->city(),
			'state'     => $this->get_faker()->stateAbbr(),
			'postcode'  => $this->get_faker()->postcode(),
			'country'   => 'US',
			// phone lives under meta.other_data, not as a column.
			'meta'      => array(
				'other_data' => array(
					'phone' => $this->get_faker()->phoneNumber(),
				),
			),
		);
	}

	/**
	 * Record a coupon applied to an order.
	 *
	 * @since 1.0.0
	 *
	 * @param OrderModel $order  Parent order.
	 * @param array      $coupon Coupon data (id, code, amount in cents).
	 *
	 * @return void
	 */
	private function create_applied_coupon( OrderModel $order, array $coupon ): void {
		if ( ! class_exists( AppliedCouponModel::class ) ) {
			return;
		}

		AppliedCouponModel::query()->create(
			array(
				'order_id'  => (int) $order->id,
				'coupon_id' => $coupon['id'],
				'code'      => $coupon['code'],
				// Stored in integer cents, like every other order money column.
				'amount'    => $coupon['amount'],
			)
		);
	}
}
