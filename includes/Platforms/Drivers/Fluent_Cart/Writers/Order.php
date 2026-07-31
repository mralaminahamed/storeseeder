<?php
/**
 * Fluent Cart order writer
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers
 */

namespace StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers;

use FluentCart\App\Models\AppliedCoupon as AppliedCouponModel;
use FluentCart\App\Models\Coupon as CouponModel;
use FluentCart\App\Models\Customer as CustomerModel;
use FluentCart\App\Models\Order as OrderModel;
use FluentCart\App\Models\OrderAddress as OrderAddressModel;
use FluentCart\App\Models\ProductVariation as ProductVariationModel;
use StoreSeeder\Platforms\Writer;
use StoreSeeder\Platforms\Resource;
use StoreSeeder\Platforms\Status;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persists a canonical order into Fluent Cart.
 *
 * @since 1.1.0
 */
final class Order extends Writer {
	/**
	 * Canonical order status to Fluent Cart's spelling.
	 *
	 * Status::getOrderStatuses() is the allowed set. 'pending' is a payment_status,
	 * not an order status, and an order carrying it disappears from every admin
	 * status filter — which is why the canonical vocabulary is mapped rather than
	 * passed through.
	 *
	 * @since 1.1.0
	 * @var array<string, string>
	 */
	private const ORDER_STATUS = array(
		Status::COMPLETED  => 'completed',
		Status::PROCESSING => 'processing',
		Status::ON_HOLD    => 'on-hold',
		Status::CANCELLED  => 'canceled',
		Status::FAILED     => 'failed',
		Status::REFUNDED   => 'refunded',
	);

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
	 * Create a Fluent Cart order with its items, addresses and coupon.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $entity Canonical order entity.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function write( array $entity ) {
		if ( ! defined( 'FLUENTCART_VERSION' ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart plugin not found. Please ensure Fluent Cart is active.', 'storeseeder' ) );
		}

		if ( ! class_exists( OrderModel::class ) ) {
			return new WP_Error( 'missing_model', __( 'Fluent Cart Order model not found. Please ensure Fluent Cart plugin is active.', 'storeseeder' ) );
		}

		$items = $this->build_items( (array) $entity['items'] );

		// An order with no line items is not a useful fixture, and inserting
		// one leaves an orphan row behind. Fail before touching the database
		// and say what is actually missing.
		if ( array() === $items ) {
			return new WP_Error(
				'no_products',
				__( 'No published products with variations were found. Generate products before generating orders.', 'storeseeder' )
			);
		}

		$customer_id = $this->random_customer_id();

		// Fluent Cart orders always belong to a customer; one without is
		// invisible in the admin customer view and breaks lifetime-value
		// reporting.
		if ( null === $customer_id ) {
			return new WP_Error(
				'no_customers',
				__( 'No customers were found. Generate customers before generating orders.', 'storeseeder' )
			);
		}

		$subtotal = 0;
		foreach ( $items as $item ) {
			$subtotal += $item['subtotal'];
		}

		$tax_total = (int) round( $subtotal * (float) $entity['tax_rate'] );

		// Drawing a real coupon and recording the discount ties the order to
		// fct_applied_coupons, so coupon usage counts and revenue reports stop
		// reading zero.
		$coupon   = $entity['use_coupon'] ? $this->random_applicable_coupon() : null;
		$discount = $this->coupon_discount( $coupon, $subtotal );

		$order_data = array(
			'parent_id'             => 0,
			'invoice_no'            => 'FC-' . $entity['invoice_no'],
			'receipt_number'        => $entity['receipt_number'],
			'customer_id'           => $customer_id,
			'payment_method'        => $entity['payment_method'],
			'payment_method_title'  => ucfirst( (string) $entity['payment_method'] ),
			'payment_status'        => 'paid',
			'currency'              => $entity['currency'],
			'subtotal'              => $subtotal,
			'shipping_tax'          => 0,
			'fulfillment_type'      => 'physical',
			'tax_total'             => $tax_total,
			'manual_discount_total' => 0,
			'coupon_discount_total' => $discount,
			'total_amount'          => max( 0, $subtotal + $tax_total - $discount ),
			'total_paid'            => max( 0, $subtotal + $tax_total - $discount ),
			'status'                => self::ORDER_STATUS[ $entity['status'] ] ?? 'completed',
			'created_at'            => $entity['created_at'],
		);

		// Create order using Fluent Cart Order model.
		$order = OrderModel::query()->create( $order_data );

		if ( ! $order instanceof OrderModel ) {
			return new WP_Error( 'order_creation_failed', __( 'Failed to create order using Fluent Cart model.', 'storeseeder' ) );
		}

		// The relation is order_items(), snake_case. Calling orderItems()
		// resolves no relation, forwards to the query builder, and throws
		// BadMethodCallException — after the order row is already inserted,
		// leaving an orphan order with no line items.
		foreach ( $items as $item ) {
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
					'created_at' => $entity['created_at'],
				)
			);
		}

		// Give the order the billing and shipping addresses it was missing —
		// without them the admin order view is blank and tax and shipping cannot
		// resolve a region.
		$this->create_order_addresses( $order, (array) $entity['addresses'] );

		// Record the applied coupon, if any, so the discount traces to a real
		// coupon rather than an unexplained number.
		if ( $coupon && $discount > 0 ) {
			$this->create_applied_coupon( $order, $coupon, $discount );
		}

		$result = array(
			'id'          => $order->id,
			'customer_id' => $customer_id,
			// Stored in cents; reported in major units so the UI shows 456.78
			// rather than 45678.
			'total'       => round( $order_data['total_amount'] / 100, 2 ),
			'status'      => $order_data['status'],
			'items_count' => count( $items ),
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
		return apply_filters( 'storeseeder_order_generation_result', $result, $order->id, $order_data );
	}

	/**
	 * Pair the entity's item shapes with real product variations.
	 *
	 * Order items are foreign keys into fct_product_variations and wp_posts.
	 * Inventing the IDs produces line items pointing at products that do not
	 * exist, which render blank in the admin and break every report that joins
	 * back to the product.
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, array<string, mixed>> $shapes Canonical item shapes.
	 *
	 * @return array<int, array<string, mixed>> Fluent Cart order item rows.
	 */
	private function build_items( array $shapes ): array {
		if ( array() === $shapes ) {
			return array();
		}

		$variations = ProductVariationModel::query()
			->with( 'product' )
			->inRandomOrder()
			->limit( count( $shapes ) )
			->get();

		$items = array();
		$index = 0;

		foreach ( $variations as $variation ) {
			$product = $variation->product;

			if ( ! $product || 'publish' !== $product->post_status ) {
				continue;
			}

			$shape = $shapes[ $index ] ?? null;

			if ( null === $shape ) {
				break;
			}

			// Every money column on fct_order_items is BIGINT holding integer
			// cents, which is what the canonical entity already carries. Storing
			// dollars makes a $456.78 line render as $4.57.
			$unit_price = (int) $shape['unit_price'];
			$quantity   = (int) $shape['quantity'];

			$items[] = array(
				'post_id'    => (int) $variation->post_id,
				'object_id'  => (int) $variation->id,
				'title'      => $variation->variation_title,
				'post_title' => $product->post_title,
				'quantity'   => $quantity,
				'unit_price' => $unit_price,
				'subtotal'   => $unit_price * $quantity,
				'cart_index' => $index,
			);

			++$index;
		}

		return $items;
	}

	/**
	 * Draw a real customer ID.
	 *
	 * @since 1.1.0
	 *
	 * @return int|null Customer ID, or null when the store has no customers yet.
	 */
	private function random_customer_id(): ?int {
		$customer = CustomerModel::query()->inRandomOrder()->first();

		return $customer ? (int) $customer->id : null;
	}

	/**
	 * Draw a coupon whose discount can be applied to an order.
	 *
	 * Only 'fixed' and 'percentage' coupons map to a line discount; free_shipping
	 * and other types are skipped.
	 *
	 * @since 1.1.0
	 *
	 * @return CouponModel|null Coupon model, or null.
	 */
	private function random_applicable_coupon(): ?CouponModel {
		if ( ! class_exists( CouponModel::class ) ) {
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
	 * @since 1.1.0
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
	 * @since 1.1.0
	 *
	 * @param OrderModel                          $order     Parent order.
	 * @param array<string, array<string, mixed>> $addresses Canonical addresses, keyed by type.
	 *
	 * @return void
	 */
	private function create_order_addresses( OrderModel $order, array $addresses ): void {
		if ( ! class_exists( OrderAddressModel::class ) ) {
			return;
		}

		foreach ( array( 'billing', 'shipping' ) as $type ) {
			$address = $addresses[ $type ] ?? null;

			if ( null === $address ) {
				continue;
			}

			OrderAddressModel::query()->create(
				array(
					'order_id'  => (int) $order->id,
					'type'      => $type,
					'name'      => $address['name'],
					'address_1' => $address['address_1'],
					'address_2' => $address['address_2'],
					'city'      => $address['city'],
					'state'     => $address['state'],
					'postcode'  => $address['postcode'],
					'country'   => $address['country'],
					// phone lives under meta.other_data, not as a column.
					'meta'      => array(
						'other_data' => array(
							'phone' => $address['phone'],
						),
					),
				)
			);
		}
	}

	/**
	 * Record a coupon applied to an order.
	 *
	 * @since 1.1.0
	 *
	 * @param OrderModel  $order    Parent order.
	 * @param CouponModel $coupon   The applied coupon.
	 * @param int         $discount Discount in integer cents.
	 *
	 * @return void
	 */
	private function create_applied_coupon( OrderModel $order, CouponModel $coupon, int $discount ): void {
		if ( ! class_exists( AppliedCouponModel::class ) ) {
			return;
		}

		AppliedCouponModel::query()->create(
			array(
				'order_id'  => (int) $order->id,
				'coupon_id' => (int) $coupon->id,
				'code'      => $coupon->code,
				// Stored in integer cents, like every other order money column.
				'amount'    => $discount,
			)
		);
	}
}
