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
		if ( ! defined( 'FLUENT_CART_VERSION' ) ) {
			return new WP_Error( 'missing_fluent_cart', __( 'Fluent Cart plugin not found. Please ensure Fluent Cart is active.', 'fluent-cart-fakerpress' ) );
		}

		$order_data = $this->generate_order_data();
		$order_id   = $this->create_order( $order_data );

		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}

		if ( ! $order_id ) {
			return new WP_Error( 'order_creation_failed', __( 'Failed to create order.', 'fluent-cart-fakerpress' ) );
		}

		$result = array(
			'id'          => $order_id,
			'customer_id' => $order_data['customer_id'],
			'total'       => $order_data['total'],
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
		$total       = 0;

		for ( $i = 0; $i < $items_count; $i++ ) {
			$price    = $this->get_faker()->randomFloat( 2, 10, 500 );
			$quantity = $this->get_faker()->numberBetween( 1, 2 );
			$subtotal = $price * $quantity;

			$items[] = array(
				'product_id' => $this->get_faker()->numberBetween( 1, 1000 ),
				'name'       => $this->get_faker()->words( 3, true ),
				'price'      => $price,
				'quantity'   => $quantity,
				'subtotal'   => $subtotal,
			);

			$total += $subtotal;
		}

		return array(
			'customer_id'     => $this->get_faker()->numberBetween( 1, 100 ),
			'customer_email'  => $this->get_faker()->email(),
			'customer_name'   => $this->get_faker()->name(),
			'items'           => $items,
			'total'           => $total,
			'subtotal'        => $total,
			'tax_amount'      => $total * 0.08, // 8% tax
			'shipping_amount' => $this->get_faker()->randomFloat( 2, 0, 20 ),
			'discount_amount' => 0,
			'currency'        => 'USD',
			'status'          => $this->get_faker()->randomElement( array( 'completed', 'processing', 'pending' ) ),
			'payment_method'  => $this->get_faker()->randomElement( array( 'stripe', 'paypal', 'cod' ) ),
			'created_at'      => current_time( 'Y-m-d H:i:s' ),
		);
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
		if ( ! empty( $data['items'] ) ) {
			foreach ( $data['items'] as $item ) {
				$order->orderItems()->create(
					array(
						'product_id' => $item['product_id'],
						'item_name'  => $item['name'],
						'quantity'   => $item['quantity'],
						'unit_price' => $item['price'],
						'line_total' => $item['subtotal'],
						'created_at' => $data['created_at'],
					)
				);
			}
		}

		return $order->id;
	}
}
