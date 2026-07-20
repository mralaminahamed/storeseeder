<?php
/**
 * Order Generator Class for Fluent Cart FakerPress Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    FluentCartFakerPress
 */

namespace FluentCartFakerPress\Generators;

use FluentCart\App\Models\Order as OrderModel;
use FluentCartFakerPress\Abstracts\Generator;
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
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart plugin not found. Please ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		$order_data = $this->generate_order_data();

		// An order with no line items is not a useful fixture, and inserting
		// one leaves an orphan row behind. Fail before touching the database
		// and say what is actually missing.
		if ( empty( $order_data['items'] ) ) {
			return new WP_Error(
				'no_products',
				__( 'No published products with variations were found. Generate products before generating orders.', 'fluent-cart-fakerpress' )
			);
		}

		// Fluent Cart orders always belong to a customer; one without is
		// invisible in the admin customer view and breaks lifetime-value
		// reporting.
		if ( null === $order_data['customer_id'] ) {
			return new WP_Error(
				'no_customers',
				__( 'No customers were found. Generate customers before generating orders.', 'fluent-cart-fakerpress' )
			);
		}

		$order_id = $this->create_order( $order_data );

		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}

		if ( ! $order_id ) {
			return new WP_Error( 'order_creation_failed', __( 'Failed to create order.', 'fluent-cart-fakerpress' ) );
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
		 * @hook  fluent_cart_fakerpress_order_generation_result
		 *
		 * @param array $result     The order generation result data.
		 * @param int   $order_id   The created order ID.
		 * @param array $order_data The original order data used for creation.
		 */
		return apply_filters( 'fluent_cart_fakerpress_order_generation_result', $result, $order_id, $order_data );
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

		return array(
			'customer_id'     => $this->random_customer_id(),
			'items'           => $items,
			'subtotal'        => $subtotal,
			'tax_amount'      => $tax_total,
			'total'           => $subtotal + $tax_total,
			'shipping_amount' => 0,
			'discount_amount' => 0,
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
		$variations = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT v.id, v.post_id, v.variation_title, p.post_title
				 FROM {$this->wpdb->prefix}fct_product_variations AS v
				 INNER JOIN {$this->wpdb->posts} AS p ON p.ID = v.post_id
				 WHERE p.post_status = 'publish'
				 ORDER BY RAND()
				 LIMIT %d",
				$count
			)
		);

		return is_array( $variations ) ? $variations : array();
	}

	/**
	 * Draw a real customer ID.
	 *
	 * @return int|null Customer ID, or null when the store has no customers yet.
	 */
	private function random_customer_id(): ?int {
		$customer_id = $this->wpdb->get_var(
			"SELECT id FROM {$this->wpdb->prefix}fct_customers ORDER BY RAND() LIMIT 1"
		);

		return null === $customer_id ? null : (int) $customer_id;
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
			return new WP_Error( 'missing_model', __( 'Fluent Cart Order model not found. Please ensure Fluent Cart plugin is active.', 'fluent-cart-fakerpress' ) );
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
			return new WP_Error( 'order_creation_failed', __( 'Failed to create order using Fluent Cart model.', 'fluent-cart-fakerpress' ) );
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

		return $order->id;
	}
}
